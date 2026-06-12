<?php
// =============================================
// geminy.me v6.0 — API Dokümantasyon Sayfası
// Giriş yapan kullanıcılar için VIP API rehberi
// =============================================
require_once __DIR__ . '/../../app/config.php';
secureSession();

$loggedUser = $_SESSION['username'] ?? null;
$isLoggedIn = (bool)$loggedUser;

// Kullanıcı bilgileri (giriş yaptıysa)
$me = null;
if ($loggedUser) {
    $s = db()->prepare('SELECT * FROM users WHERE username=?');
    $s->execute([$loggedUser]);
    $me = $s->fetch();
}

// ── Canlı API Endpoint'leri ───────────────────────────────────
// Profil JSON
// GET /api/profile/{username}
// Sorular JSON
// GET /api/tweets/{username}

$siteUrl = SITE_URL;
$exUser  = $loggedUser ?: 'kullaniciadi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>API Dokümantasyon | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
body { background:#0A0A0F; color:#EEF0FF; font-family:Arial,sans-serif; }

/* ── Top Bar ────────────────────────────────────────────────── */
.api-topbar {
  position:sticky; top:0; z-index:100;
  display:flex; align-items:center; gap:12px;
  padding:14px 16px; background:rgba(10,10,15,.92);
  backdrop-filter:blur(14px); border-bottom:1px solid rgba(255,255,255,.06);
}
.api-topbar-title { flex:1; font-weight:800; font-size:1.05rem; }
.api-back { color:#EEF0FF; text-decoration:none; font-size:1.3rem; }

/* ── VIP Banner ─────────────────────────────────────────────── */
.api-vip-banner {
  margin:16px 14px;
  background:linear-gradient(135deg,rgba(233,30,140,.15),rgba(124,58,237,.15));
  border:1px solid rgba(233,30,140,.3);
  border-radius:18px; padding:20px 18px;
  display:flex; align-items:center; gap:14px;
}
.api-vip-icon { font-size:2.2rem; flex-shrink:0; }
.api-vip-title { font-weight:900; font-size:1rem; margin-bottom:3px; }
.api-vip-sub   { color:#B0B3D6; font-size:.8rem; line-height:1.5; }

/* ── Bölüm ──────────────────────────────────────────────────── */
.api-section {
  margin:0 14px 16px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:18px; overflow:hidden;
}
.api-section-header {
  padding:14px 18px;
  background:rgba(255,255,255,.03);
  border-bottom:1px solid rgba(255,255,255,.06);
  display:flex; align-items:center; gap:10px;
}
.api-section-title { font-weight:800; font-size:.95rem; }
.api-method-badge {
  padding:3px 10px; border-radius:6px;
  font-size:.7rem; font-weight:900; letter-spacing:.05em;
  font-family:monospace;
}
.badge-get  { background:rgba(34,197,94,.2);  color:#22C55E; border:1px solid rgba(34,197,94,.3); }
.badge-post { background:rgba(59,130,246,.2); color:#3B82F6; border:1px solid rgba(59,130,246,.3); }

/* ── Endpoint URL ───────────────────────────────────────────── */
.api-url-box {
  padding:14px 18px;
  border-bottom:1px solid rgba(255,255,255,.06);
}
.api-url-label { font-size:.72rem; color:#6B6F9A; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
.api-url-code {
  font-family:monospace; font-size:.82rem;
  background:rgba(0,212,255,.06); border:1px solid rgba(0,212,255,.15);
  border-radius:10px; padding:10px 14px; color:#00D4FF;
  word-break:break-all; display:flex; align-items:center; justify-content:space-between; gap:8px;
}
.api-copy-btn {
  background:none; border:none; color:#6B6F9A; cursor:pointer;
  font-size:.9rem; padding:2px 6px; border-radius:6px;
  transition:.2s; flex-shrink:0;
}
.api-copy-btn:hover { color:#00D4FF; background:rgba(0,212,255,.1); }

/* ── Parametreler ───────────────────────────────────────────── */
.api-params { padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.06); }
.api-params-title { font-size:.72rem; color:#6B6F9A; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
.api-param-row {
  display:flex; align-items:flex-start; gap:10px;
  padding:8px 0; border-bottom:1px solid rgba(255,255,255,.04);
}
.api-param-row:last-child { border-bottom:none; }
.api-param-name {
  font-family:monospace; font-size:.78rem; color:#EAB308;
  background:rgba(234,179,8,.08); border:1px solid rgba(234,179,8,.2);
  border-radius:6px; padding:2px 8px; white-space:nowrap; flex-shrink:0;
}
.api-param-type  { font-size:.72rem; color:#6B6F9A; flex-shrink:0; padding-top:2px; }
.api-param-desc  { font-size:.78rem; color:#B0B3D6; line-height:1.5; }
.api-param-req   { font-size:.65rem; color:#E91E8C; font-weight:700; }

/* ── JSON Örneği ────────────────────────────────────────────── */
.api-example { padding:14px 18px; }
.api-example-title { font-size:.72rem; color:#6B6F9A; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
.api-json-block {
  background:rgba(0,0,0,.4); border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:14px 16px; overflow-x:auto;
  font-family:monospace; font-size:.75rem; line-height:1.7;
  position:relative;
}
.api-json-block pre { margin:0; white-space:pre-wrap; word-break:break-word; }
.json-key    { color:#00D4FF; }
.json-str    { color:#22C55E; }
.json-num    { color:#F97316; }
.json-bool   { color:#A855F7; }
.json-null   { color:#6B6F9A; }

/* ── Hız Limiti ─────────────────────────────────────────────── */
.api-rate-box {
  margin:0 14px 16px;
  background:rgba(234,179,8,.06);
  border:1px solid rgba(234,179,8,.2);
  border-radius:14px; padding:14px 16px;
  display:flex; gap:10px; align-items:flex-start;
}
.api-rate-icon { font-size:1.2rem; flex-shrink:0; }
.api-rate-text { font-size:.8rem; color:#B0B3D6; line-height:1.6; }

/* ── Kullanıcı API Anahtarı ─────────────────────────────────── */
.api-key-section {
  margin:0 14px 16px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:18px; padding:18px 16px;
}
.api-key-title { font-weight:800; font-size:.95rem; margin-bottom:4px; }
.api-key-sub   { font-size:.78rem; color:#6B6F9A; margin-bottom:14px; }
.api-key-display {
  font-family:monospace; font-size:.82rem;
  background:rgba(233,30,140,.06); border:1px solid rgba(233,30,140,.2);
  border-radius:10px; padding:12px 14px; color:#E91E8C;
  display:flex; align-items:center; justify-content:space-between; gap:8px;
  word-break:break-all;
}
.api-key-note { font-size:.72rem; color:#6B6F9A; margin-top:8px; line-height:1.5; }

/* ── Durum Kodları ──────────────────────────────────────────── */
.api-status-grid {
  margin:0 14px 16px;
  display:grid; grid-template-columns:1fr 1fr;
  gap:8px;
}
.api-status-card {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:12px; padding:12px 14px;
}
.api-status-code { font-family:monospace; font-size:1.1rem; font-weight:900; }
.api-status-desc { font-size:.72rem; color:#6B6F9A; margin-top:3px; }
</style>
</head>
<body>

<!-- Top Bar -->
<div class="api-topbar">
  <a href="/settings" class="api-back"><i class="bi bi-chevron-left"></i></a>
  <span class="api-topbar-title"><i class="bi bi-code-slash" style="color:#E91E8C;margin-right:6px;"></i>API Dokümantasyon</span>
  <span style="font-size:.7rem;color:#6B6F9A;background:rgba(233,30,140,.1);border:1px solid rgba(233,30,140,.2);padding:4px 10px;border-radius:8px;font-weight:700;">v1.0</span>
</div>

<!-- VIP Banner -->
<div class="api-vip-banner">
  <div class="api-vip-icon">🔑</div>
  <div>
    <div class="api-vip-title">geminy.me Genel API</div>
    <div class="api-vip-sub">Profil bilgilerini ve soruları JSON formatında çek. Kendi uygulamanı, botunu veya widget'ını oluştur.</div>
  </div>
</div>

<?php if ($isLoggedIn && $me): ?>
<!-- Kullanıcı API Anahtarı -->
<div class="api-key-section">
  <div class="api-key-title"><i class="bi bi-key-fill" style="color:#EAB308;"></i> Senin API Anahtarın</div>
  <div class="api-key-sub">Bu anahtarı kimseyle paylaşma. Sadece kendi profiline yazma erişimi verir.</div>
  <?php
    // Kullanıcıya özel deterministik API anahtarı (hash tabanlı, DB gerektirmez)
    $apiKey = 'gmny_' . substr(hash('sha256', $loggedUser . 'geminy_api_salt_2026'), 0, 32);
  ?>
  <div class="api-key-display">
    <span id="apiKeyText"><?= e($apiKey) ?></span>
    <button class="api-copy-btn" onclick="copyApiKey()" title="Kopyala"><i class="bi bi-copy"></i></button>
  </div>
  <div class="api-key-note">⚠️ Bu anahtar şu an sadece okuma (GET) işlemleri için geçerlidir. Yazma API'si yakında gelecek.</div>
</div>
<?php endif; ?>

<!-- Hız Limiti -->
<div class="api-rate-box">
  <div class="api-rate-icon">⚡</div>
  <div class="api-rate-text">
    <strong style="color:#EAB308;">Hız Limiti:</strong> Dakikada 60 istek / IP başına. Aşılırsa <code style="color:#EAB308;">429 Too Many Requests</code> döner. Tüm yanıtlar <strong>JSON</strong> formatındadır.
  </div>
</div>

<!-- ══ ENDPOINT 1: Profil JSON ════════════════════════════════ -->
<div class="api-section">
  <div class="api-section-header">
    <span class="api-method-badge badge-get">GET</span>
    <span class="api-section-title">Profil Bilgileri</span>
  </div>

  <div class="api-url-box">
    <div class="api-url-label">Endpoint URL</div>
    <div class="api-url-code">
      <span><?= e($siteUrl) ?>/api/profile/<strong>{username}</strong></span>
      <button class="api-copy-btn" onclick="copyText('<?= e($siteUrl) ?>/api/profile/<?= e($exUser) ?>')" title="Kopyala"><i class="bi bi-copy"></i></button>
    </div>
  </div>

  <div class="api-params">
    <div class="api-params-title">Parametreler</div>
    <div class="api-param-row">
      <span class="api-param-name">username</span>
      <span class="api-param-type">string</span>
      <div>
        <div class="api-param-desc">Profil bilgisi alınacak kullanıcı adı (URL path parametresi)</div>
        <span class="api-param-req">ZORUNLU</span>
      </div>
    </div>
  </div>

  <div class="api-example">
    <div class="api-example-title">Örnek Yanıt</div>
    <div class="api-json-block">
      <pre>{
  <span class="json-key">"status"</span>: <span class="json-str">"ok"</span>,
  <span class="json-key">"profile"</span>: {
    <span class="json-key">"username"</span>:     <span class="json-str">"<?= e($exUser) ?>"</span>,
    <span class="json-key">"display_name"</span>: <span class="json-str">"<?= e($me['display_name'] ?? $exUser) ?>"</span>,
    <span class="json-key">"bio"</span>:          <span class="json-str">"<?= e($me['bio'] ?? 'Biyografi...') ?>"</span>,
    <span class="json-key">"avatar_url"</span>:   <span class="json-str">"https://..."</span>,
    <span class="json-key">"website"</span>:      <span class="json-null">null</span>,
    <span class="json-key">"is_private"</span>:   <span class="json-bool">false</span>,
    <span class="json-key">"joined_at"</span>:    <span class="json-str">"2026-05-29T17:22:24Z"</span>,
    <span class="json-key">"stats"</span>: {
      <span class="json-key">"total_questions"</span>: <span class="json-num">42</span>,
      <span class="json-key">"total_likes"</span>:     <span class="json-num">128</span>,
      <span class="json-key">"total_replies"</span>:   <span class="json-num">35</span>
    },
    <span class="json-key">"tick"</span>: {
      <span class="json-key">"level"</span>: <span class="json-str">"blue"</span>,
      <span class="json-key">"label"</span>: <span class="json-str">"Mavi Tık"</span>,
      <span class="json-key">"color"</span>: <span class="json-str">"#3B82F6"</span>
    },
    <span class="json-key">"socials"</span>: {
      <span class="json-key">"instagram"</span>: <span class="json-str">"kullaniciadi"</span>,
      <span class="json-key">"tiktok"</span>:    <span class="json-null">null</span>,
      <span class="json-key">"twitter"</span>:   <span class="json-null">null</span>
    }
  }
}</pre>
    </div>
  </div>
</div>

<!-- ══ ENDPOINT 2: Sorular (Tweets) JSON ═════════════════════ -->
<div class="api-section">
  <div class="api-section-header">
    <span class="api-method-badge badge-get">GET</span>
    <span class="api-section-title">Sorular (Tweet Listesi)</span>
  </div>

  <div class="api-url-box">
    <div class="api-url-label">Endpoint URL</div>
    <div class="api-url-code">
      <span><?= e($siteUrl) ?>/api/tweets/<strong>{username}</strong></span>
      <button class="api-copy-btn" onclick="copyText('<?= e($siteUrl) ?>/api/tweets/<?= e($exUser) ?>')" title="Kopyala"><i class="bi bi-copy"></i></button>
    </div>
  </div>

  <div class="api-params">
    <div class="api-params-title">Query Parametreleri</div>
    <div class="api-param-row">
      <span class="api-param-name">username</span>
      <span class="api-param-type">string</span>
      <div>
        <div class="api-param-desc">Sorular alınacak kullanıcı adı (URL path parametresi)</div>
        <span class="api-param-req">ZORUNLU</span>
      </div>
    </div>
    <div class="api-param-row">
      <span class="api-param-name">limit</span>
      <span class="api-param-type">int</span>
      <div>
        <div class="api-param-desc">Döndürülecek maksimum soru sayısı (varsayılan: 20, max: 100)</div>
        <span style="font-size:.65rem;color:#6B6F9A;">opsiyonel</span>
      </div>
    </div>
    <div class="api-param-row">
      <span class="api-param-name">page</span>
      <span class="api-param-type">int</span>
      <div>
        <div class="api-param-desc">Sayfalama için sayfa numarası (varsayılan: 1)</div>
        <span style="font-size:.65rem;color:#6B6F9A;">opsiyonel</span>
      </div>
    </div>
    <div class="api-param-row">
      <span class="api-param-name">sort</span>
      <span class="api-param-type">string</span>
      <div>
        <div class="api-param-desc"><code>newest</code> (varsayılan) veya <code>popular</code> (beğeniye göre)</div>
        <span style="font-size:.65rem;color:#6B6F9A;">opsiyonel</span>
      </div>
    </div>
  </div>

  <div class="api-example">
    <div class="api-example-title">Örnek İstek</div>
    <div class="api-json-block" style="margin-bottom:12px;">
      <pre><span class="json-key">GET</span> <?= e($siteUrl) ?>/api/tweets/<?= e($exUser) ?>?limit=5&sort=popular</pre>
    </div>
    <div class="api-example-title">Örnek Yanıt</div>
    <div class="api-json-block">
      <pre>{
  <span class="json-key">"status"</span>:   <span class="json-str">"ok"</span>,
  <span class="json-key">"username"</span>: <span class="json-str">"<?= e($exUser) ?>"</span>,
  <span class="json-key">"total"</span>:    <span class="json-num">42</span>,
  <span class="json-key">"page"</span>:     <span class="json-num">1</span>,
  <span class="json-key">"limit"</span>:    <span class="json-num">5</span>,
  <span class="json-key">"tweets"</span>: [
    {
      <span class="json-key">"id"</span>:         <span class="json-str">"686a1f2c3d4e5"</span>,
      <span class="json-key">"text"</span>:       <span class="json-str">"En sevdiğin renk ne?"</span>,
      <span class="json-key">"likes"</span>:      <span class="json-num">17</span>,
      <span class="json-key">"reply_count"</span>: <span class="json-num">1</span>,
      <span class="json-key">"created_at"</span>: <span class="json-str">"2026-06-10T14:32:00Z"</span>,
      <span class="json-key">"reply"</span>: {
        <span class="json-key">"text"</span>:       <span class="json-str">"Mor! 💜"</span>,
        <span class="json-key">"created_at"</span>: <span class="json-str">"2026-06-10T15:00:00Z"</span>
      }
    }
  ]
}</pre>
    </div>
  </div>
</div>

<!-- ══ ENDPOINT 3: Canlı API ══════════════════════════════════ -->
<div class="api-section">
  <div class="api-section-header">
    <span class="api-method-badge badge-get">GET</span>
    <span class="api-section-title">Kullanıcı Arama</span>
  </div>

  <div class="api-url-box">
    <div class="api-url-label">Endpoint URL</div>
    <div class="api-url-code">
      <span><?= e($siteUrl) ?>/search?json=1&q=<strong>{sorgu}</strong></span>
      <button class="api-copy-btn" onclick="copyText('<?= e($siteUrl) ?>/search?json=1&q=test')" title="Kopyala"><i class="bi bi-copy"></i></button>
    </div>
  </div>

  <div class="api-params">
    <div class="api-params-title">Query Parametreleri</div>
    <div class="api-param-row">
      <span class="api-param-name">json</span>
      <span class="api-param-type">int</span>
      <div>
        <div class="api-param-desc">JSON modunu aktif et (değer: <code>1</code>)</div>
        <span class="api-param-req">ZORUNLU</span>
      </div>
    </div>
    <div class="api-param-row">
      <span class="api-param-name">q</span>
      <span class="api-param-type">string</span>
      <div>
        <div class="api-param-desc">Arama sorgusu (kullanıcı adı veya görünen ad)</div>
        <span class="api-param-req">ZORUNLU</span>
      </div>
    </div>
  </div>

  <div class="api-example">
    <div class="api-example-title">Örnek Yanıt</div>
    <div class="api-json-block">
      <pre>{
  <span class="json-key">"users"</span>: [
    {
      <span class="json-key">"username"</span>:     <span class="json-str">"selena"</span>,
      <span class="json-key">"display_name"</span>: <span class="json-str">"Selena"</span>,
      <span class="json-key">"bio"</span>:          <span class="json-str">"Sihirli dünyanın günlüğü ✨"</span>,
      <span class="json-key">"avatar_url"</span>:   <span class="json-null">null</span>
    }
  ]
}</pre>
    </div>
  </div>
</div>

<!-- Durum Kodları -->
<div style="margin:0 14px 8px;font-size:.78rem;color:#6B6F9A;text-transform:uppercase;letter-spacing:.06em;">HTTP Durum Kodları</div>
<div class="api-status-grid">
  <div class="api-status-card">
    <div class="api-status-code" style="color:#22C55E;">200</div>
    <div class="api-status-desc">Başarılı</div>
  </div>
  <div class="api-status-card">
    <div class="api-status-code" style="color:#EAB308;">400</div>
    <div class="api-status-desc">Geçersiz istek</div>
  </div>
  <div class="api-status-card">
    <div class="api-status-code" style="color:#F97316;">404</div>
    <div class="api-status-desc">Kullanıcı bulunamadı</div>
  </div>
  <div class="api-status-card">
    <div class="api-status-code" style="color:#E91E8C;">429</div>
    <div class="api-status-desc">Hız limiti aşıldı</div>
  </div>
  <div class="api-status-card">
    <div class="api-status-code" style="color:#6B6F9A;">500</div>
    <div class="api-status-desc">Sunucu hatası</div>
  </div>
  <div class="api-status-card">
    <div class="api-status-code" style="color:#A855F7;">403</div>
    <div class="api-status-desc">Özel hesap</div>
  </div>
</div>

<!-- Yakında Gelecek -->
<div style="margin:8px 14px 16px;background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.2);border-radius:14px;padding:14px 16px;">
  <div style="font-weight:800;font-size:.85rem;margin-bottom:8px;color:#A855F7;"><i class="bi bi-clock-history"></i> Yakında Gelecek</div>
  <div style="font-size:.78rem;color:#B0B3D6;line-height:1.8;">
    • <strong>POST /api/reply</strong> — Soruya yanıt ver (API anahtarı gerekli)<br>
    • <strong>Webhook desteği</strong> — Yeni soru geldiğinde bildirim al<br>
    • <strong>OAuth 2.0</strong> — Üçüncü taraf uygulama entegrasyonu<br>
    • <strong>Rate limit artışı</strong> — VIP kullanıcılar için 300 istek/dk
  </div>
</div>

<div style="height:32px;"></div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
<?php if ($isLoggedIn): ?>
  <a href="/"                              class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"                        class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"                      class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications"                 class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item">
    <?php if (!empty($me['avatar_url'])): ?>
      <img src="<?= e($me['avatar_url']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid transparent;">
    <?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
<?php else: ?>
  <a href="/"       class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search" class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <div class="nav-item"></div><div class="nav-item"></div>
  <a href="/"       class="nav-item"><i class="bi bi-person-fill"></i><span>Giriş</span></a>
<?php endif; ?>
</nav>

<script>
function copyText(text) {
  navigator.clipboard.writeText(text).then(() => showToast('Kopyalandı! 📋'));
}
function copyApiKey() {
  const key = document.getElementById('apiKeyText').textContent;
  navigator.clipboard.writeText(key).then(() => showToast('API anahtarı kopyalandı! 🔑'));
}
function showToast(msg) {
  const t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:rgba(10,10,15,.95);border:1px solid rgba(255,255,255,.1);color:#EEF0FF;padding:10px 20px;border-radius:12px;font-size:.82rem;z-index:9999;transition:.3s;opacity:0;';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.style.opacity = '1', 50);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
}
</script>
</body>
</html>
