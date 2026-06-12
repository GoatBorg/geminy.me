<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/smtp/mail.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;
if (!$loggedUser) redirect(SITE_URL . '/');

$me = db()->prepare('SELECT * FROM users WHERE username=?');
$me->execute([$loggedUser]);
$me = $me->fetch();
if (!$me) redirect(SITE_URL . '/');

$success = '';
$error   = '';
$tab     = $_GET['tab'] ?? 'profile'; // profile | social | music | privacy | security | account

// ── Profil Güncelle ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $f = fn($k, $l=100) => mb_substr(trim($_POST[$k] ?? ''), 0, $l);
    $display_name = $f('display_name', 50);
    $bio          = $f('bio', 150);
    $avatar_url   = $f('avatar_url', 500);
    $website      = $f('website', 200);
    if ($website && !preg_match('/^https?:\/\//i', $website)) $website = 'https://'.$website;
    if ($avatar_url && !preg_match('/^https?:\/\//i', $avatar_url)) $avatar_url = '';
    db()->prepare('UPDATE users SET display_name=?,bio=?,avatar_url=?,website=? WHERE username=?')
        ->execute([$display_name?:null, $bio?:null, $avatar_url?:null, $website?:null, $loggedUser]);
    $me['display_name'] = $display_name; $me['bio'] = $bio;
    $me['avatar_url']   = $avatar_url;   $me['website'] = $website;
    $success = 'Profil güncellendi ✅'; $tab = 'profile';
}

// ── Sosyal Medya ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social'])) {
    $f = fn($k) => mb_substr(trim($_POST[$k] ?? ''), 0, 60) ?: null;
    db()->prepare('UPDATE users SET instagram=?,tiktok=?,twitter=?,pinterest=? WHERE username=?')
        ->execute([$f('instagram'), $f('tiktok'), $f('twitter'), $f('pinterest'), $loggedUser]);
    $success = 'Sosyal medya güncellendi ✅'; $tab = 'social';
}

// ── Müzik Kartı ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_music'])) {
    $f   = fn($k, $l=120) => mb_substr(trim($_POST[$k] ?? ''), 0, $l) ?: null;
    $url = fn($k) => (($v = mb_substr(trim($_POST[$k] ?? ''), 0, 500)) && preg_match('/^https?:\/\//i', $v)) ? $v : null;
    db()->prepare('UPDATE users SET song_title=?,song_artist=?,song_cover=?,song_url=? WHERE username=?')
        ->execute([$f('song_title'), $f('song_artist', 80), $url('song_cover'), $url('song_url'), $loggedUser]);
    $success = 'Müzik kartı güncellendi 🎵'; $tab = 'music';
}

// ── Gizlilik ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_privacy'])) {
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    db()->prepare('UPDATE users SET is_private=? WHERE username=?')->execute([$is_private, $loggedUser]);
    $me['is_private'] = $is_private;
    $success = 'Gizlilik ayarları güncellendi 🔒'; $tab = 'privacy';
}

// ── Şifre Değiştir ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $old = $_POST['old_pass'] ?? '';
    $new = $_POST['new_pass'] ?? '';
    $rep = $_POST['rep_pass'] ?? '';
    if (!password_verify($old, $me['password_hash'] ?? '')) $error = 'Mevcut şifre yanlış.';
    elseif (strlen($new) < 6) $error = 'Yeni şifre en az 6 karakter.';
    elseif ($new !== $rep)    $error = 'Şifreler eşleşmiyor.';
    else {
        db()->prepare('UPDATE users SET password_hash=? WHERE username=?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $loggedUser]);
        $success = 'Şifre güncellendi 🔐';
    }
    $tab = 'security';
}

// ── 2FA Toggle ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    if (!$me['two_fa_enabled'] && empty($me['email'])) {
        $error = 'E-posta adresi olmayan hesapta 2FA açılamaz.';
    } else {
        $new2fa = $me['two_fa_enabled'] ? 0 : 1;
        db()->prepare('UPDATE users SET two_fa_enabled=? WHERE username=?')->execute([$new2fa, $loggedUser]);
        $me['two_fa_enabled'] = $new2fa;
        $success = $new2fa ? '2FA aktif edildi 🔒 Bir sonraki girişten itibaren geçerli.' : '2FA devre dışı bırakıldı.';
    }
    $tab = 'security';
}

// Güncel me
$stmt2 = db()->prepare('SELECT * FROM users WHERE username=?');
$stmt2->execute([$loggedUser]);
$me = $stmt2->fetch();

$tabs = [
    'profile'  => ['icon' => 'bi-person',           'label' => 'Profil'],
    'social'   => ['icon' => 'bi-share',             'label' => 'Sosyal'],
    'music'    => ['icon' => 'bi-music-note-beamed', 'label' => 'Müzik'],
    'privacy'  => ['icon' => 'bi-eye-slash',         'label' => 'Gizlilik'],
    'security' => ['icon' => 'bi-shield-lock',       'label' => 'Güvenlik'],
    'account'  => ['icon' => 'bi-person-gear',       'label' => 'Hesap'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Ayarlar | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>

<!-- ── Top Bar ── -->
<div class="set-header">
  <a href="/profile/<?=e($loggedUser)?>" class="set-back"><i class="bi bi-chevron-left"></i></a>
  <h1>Ayarlar</h1>
  <div style="width:40px;"></div>
</div>

<!-- ── Profil Özeti ── -->
<div class="set-profile-summary">
  <div class="set-sum-avatar">
    <?php if(!empty($me['avatar_url'])): ?>
      <img src="<?=e($me['avatar_url'])?>" alt="">
    <?php else: echo mb_strtoupper(mb_substr($loggedUser,0,1)); endif;?>
  </div>
  <div class="set-sum-info">
    <div class="set-sum-name"><?=e($me['display_name']??$loggedUser)?></div>
    <div class="set-sum-handle">@<?=e($loggedUser)?></div>
  </div>
  <?php if(!empty($me['email'])): ?>
  <div class="set-sum-email"><i class="bi bi-envelope"></i><?=e($me['email'])?></div>
  <?php endif;?>
</div>

<!-- ── Tab Bar ── -->
<div class="set-tabbar">
  <?php foreach($tabs as $key=>$t): ?>
  <a href="/settings?tab=<?=$key?>" class="set-tab <?=$tab===$key?'active':''?>">
    <i class="bi <?=$t['icon']?>"></i>
    <span><?=$t['label']?></span>
  </a>
  <?php endforeach;?>
</div>

<div class="set-body">

  <?php if($success): ?><div class="flash flash-ok set-flash"><?=e($success)?></div><?php endif;?>
  <?php if($error):   ?><div class="flash flash-err set-flash"><?=e($error)?></div><?php endif;?>

  <!-- ══════════════ PROFİL ══════════════ -->
  <?php if($tab==='profile'): ?>
  <form method="POST">
    <input type="hidden" name="save_profile" value="1">

    <!-- Canlı avatar önizleme -->
    <div class="set-avatar-preview-row">
      <div class="set-avatar-ring" id="aRing">
        <?php if(!empty($me['avatar_url'])): ?><img src="<?=e($me['avatar_url'])?>" alt=""><?php else: echo mb_strtoupper(mb_substr($loggedUser,0,1)); endif;?>
      </div>
      <div class="set-avatar-hint">
        <span>Fotoğraf URL</span>
        <small>Instagram veya başka bir profil linki yapıştır</small>
      </div>
    </div>

    <div class="set-section-label">Temel Bilgiler</div>
    <div class="set-group">
      <div class="set-row">
        <label>Fotoğraf URL</label>
        <input type="url" name="avatar_url" class="set-input" placeholder="https://..." value="<?=e($me['avatar_url']??'')?>" oninput="liveAvatar(this.value)">
      </div>
      <div class="set-row">
        <label>Görünen Ad</label>
        <input type="text" name="display_name" class="set-input" placeholder="Adın Soyadın" value="<?=e($me['display_name']??'')?>" maxlength="50">
      </div>
      <div class="set-row">
        <label>Kullanıcı Adı</label>
        <input type="text" class="set-input set-input-ro" value="@<?=e($loggedUser)?>" readonly>
      </div>
      <div class="set-row">
        <label>Biyografi</label>
        <textarea name="bio" class="set-input set-textarea" placeholder="Kendini anlat..." maxlength="150" oninput="bioC(this)"><?=e($me['bio']??'')?></textarea>
        <div class="set-char-count" id="bC"><?=strlen($me['bio']??'')?>/150</div>
      </div>
      <div class="set-row">
        <label>Website</label>
        <input type="text" name="website" class="set-input" placeholder="geminy.me" value="<?=e($me['website']??'')?>" maxlength="200">
      </div>
    </div>
    <button type="submit" class="set-save-btn">Kaydet</button>
  </form>

  <!-- API Dokümantasyon Linki -->
  <div style="margin-top:18px;">
    <div class="set-section-label">Geliştirici</div>
    <div class="set-group">
      <a href="/api-docs" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;color:#EEF0FF;">
        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,rgba(233,30,140,.15),rgba(124,58,237,.15));border:1px solid rgba(233,30,140,.25);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">🔑</div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:.9rem;">API Dokümantasyon</div>
          <div style="font-size:.75rem;color:#6B6F9A;margin-top:2px;">Profil ve soru verilerini JSON olarak çek</div>
        </div>
        <i class="bi bi-chevron-right" style="color:#6B6F9A;"></i>
      </a>
    </div>
  </div>

  <!-- ══════════════ SOSYAL MEDYA ══════════════ -->
  <?php elseif($tab==='social'): ?>
  <form method="POST">
    <input type="hidden" name="save_social" value="1">
    <div class="set-section-label">Sosyal Medya Hesapları</div>
    <div class="set-group">
      <?php
      $socials=[
        'instagram'=>['bi-instagram','#E91E8C','Instagram'],
        'tiktok'   =>['bi-tiktok','#EEF0FF','TikTok'],
        'twitter'  =>['bi-twitter-x','#EEF0FF','X (Twitter)'],
        'pinterest'=>['bi-pinterest','#E60023','Pinterest'],
      ];
      foreach($socials as $k=>[$icon,$color,$label]): ?>
      <div class="set-row set-social-row">
        <i class="bi <?=$icon?> set-social-icon" style="color:<?=$color?>;"></i>
        <div style="flex:1;">
          <label><?=$label?> kullanıcı adı</label>
          <input type="text" name="<?=$k?>" class="set-input" placeholder="kullaniciadi" value="<?=e($me[$k]??'')?>">
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <div class="set-info-box"><i class="bi bi-info-circle"></i> Sadece kullanıcı adını yaz, tam URL değil.</div>
    <button type="submit" class="set-save-btn">Kaydet</button>
  </form>

  <!-- ══════════════ MÜZİK KARTI ══════════════ -->
  <?php elseif($tab==='music'): ?>
  <form method="POST">
    <input type="hidden" name="save_music" value="1">
    <div class="set-section-label">Şu An Dinlediğin Şarkı</div>
    <?php if(!empty($me['song_title'])): ?>
    <div class="set-song-preview">
      <div class="set-song-cover">
        <?php if(!empty($me['song_cover'])): ?><img src="<?=e($me['song_cover'])?>" alt=""><?php else: ?><i class="bi bi-music-note-beamed"></i><?php endif;?>
        <div class="set-song-wave"><span></span><span></span><span></span><span></span><span></span></div>
      </div>
      <div>
        <div style="font-weight:800;font-size:.93rem;"><?=e($me['song_title'])?></div>
        <div style="font-size:.75rem;color:var(--muted);"><?=e($me['song_artist']??'')?></div>
      </div>
    </div>
    <?php endif;?>
    <div class="set-group">
      <div class="set-row"><label>Şarkı Adı</label>
        <input type="text" name="song_title" class="set-input" placeholder="Snowfall" value="<?=e($me['song_title']??'')?>" maxlength="120"></div>
      <div class="set-row"><label>Sanatçı</label>
        <input type="text" name="song_artist" class="set-input" placeholder="Oneheart" value="<?=e($me['song_artist']??'')?>" maxlength="80"></div>
      <div class="set-row"><label>Kapak Fotoğrafı URL</label>
        <input type="url" name="song_cover" class="set-input" placeholder="https://..." value="<?=e($me['song_cover']??'')?>"></div>
      <div class="set-row"><label>Spotify / YouTube Linki</label>
        <input type="url" name="song_url" class="set-input" placeholder="https://open.spotify.com/..." value="<?=e($me['song_url']??'')?>"></div>
    </div>
    <div class="set-info-box"><i class="bi bi-info-circle"></i> Şarkı kartı profilinde görünür, ziyaretçiler tıklayarak dinleyebilir.</div>
    <div style="display:flex;gap:8px;">
      <button type="submit" class="set-save-btn">Kaydet</button>
      <?php if(!empty($me['song_title'])): ?>
      <button type="submit" name="save_music" value="1" onclick="document.querySelectorAll('[name^=song]').forEach(i=>i.value='')" class="set-save-btn set-save-btn-secondary" style="width:auto;padding:15px 18px;" title="Şarkıyı Kaldır">
        <i class="bi bi-trash"></i>
      </button>
      <?php endif;?>
    </div>
  </form>

  <!-- ══════════════ GİZLİLİK ══════════════ -->
  <?php elseif($tab==='privacy'): ?>
  <form method="POST">
    <input type="hidden" name="save_privacy" value="1">
    <div class="set-section-label">Hesap Görünürlüğü</div>
    <div class="set-group">
      <div class="set-toggle-row">
        <div>
          <div class="set-toggle-label">Özel Hesap</div>
          <div class="set-toggle-sub">Sorularım ana akışta görünmesin. Profilim herkese açık olmaya devam eder.</div>
        </div>
        <label class="set-toggle">
          <input type="checkbox" name="is_private" value="1" <?=$me['is_private']?'checked':''?> onchange="this.form.submit()">
          <span class="set-toggle-slider"></span>
        </label>
      </div>
    </div>
    <div class="set-info-box" style="margin-top:12px;">
      <i class="bi bi-<?=$me['is_private']?'lock':'globe'?>"></i>
      <?=$me['is_private']
        ? 'Hesabın özel. Sorularınla ana akışta görünmüyorsun.'
        : 'Hesabın herkese açık. Soruların ana akışta görünür.'
      ?>
    </div>
  </form>

  <!-- ══════════════ GÜVENLİK ══════════════ -->
  <?php elseif($tab==='security'): ?>

  <!-- Şifre -->
  <div class="set-section-label">Şifre Değiştir</div>
  <form method="POST">
    <input type="hidden" name="change_pass" value="1">
    <div class="set-group">
      <div class="set-row"><label>Mevcut Şifre</label>
        <div class="set-pass-wrap"><input type="password" name="old_pass" class="set-input" placeholder="••••••••" id="op">
        <button type="button" class="set-eye" onclick="tP('op',this)"><i class="bi bi-eye"></i></button></div></div>
      <div class="set-row"><label>Yeni Şifre</label>
        <div class="set-pass-wrap"><input type="password" name="new_pass" class="set-input" placeholder="En az 6 karakter" id="np">
        <button type="button" class="set-eye" onclick="tP('np',this)"><i class="bi bi-eye"></i></button></div></div>
      <div class="set-row"><label>Tekrar</label>
        <div class="set-pass-wrap"><input type="password" name="rep_pass" class="set-input" placeholder="Tekrar gir" id="rp">
        <button type="button" class="set-eye" onclick="tP('rp',this)"><i class="bi bi-eye"></i></button></div></div>
    </div>
    <button type="submit" class="set-save-btn set-save-btn-secondary">Şifreyi Güncelle</button>
  </form>

  <!-- 2FA -->
  <div class="set-section-label" style="margin-top:22px;">İki Faktörlü Doğrulama</div>
  <div class="set-group">
    <div class="set-toggle-row">
      <div>
        <div class="set-toggle-label">2FA — E-posta Kodu</div>
        <div class="set-toggle-sub">Girişte e-postana 6 haneli tek kullanımlık kod gönderilir</div>
      </div>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="toggle_2fa" value="1">
        <label class="set-toggle">
          <input type="checkbox" onchange="this.form.submit()" <?=$me['two_fa_enabled']?'checked':''?>>
          <span class="set-toggle-slider"></span>
        </label>
      </form>
    </div>
    <?php if($me['two_fa_enabled']): ?>
    <div style="padding:10px 16px;font-size:.78rem;color:var(--green);display:flex;align-items:center;gap:7px;">
      <i class="bi bi-shield-check"></i> 2FA aktif — <?=e($me['email']??'e-posta yok')?>
    </div>
    <?php endif;?>
  </div>
  <div class="set-info-box" style="margin-top:10px;">
    <i class="bi bi-shield-exclamation"></i>
    Kodlar veritabanında <strong>şifreli</strong> saklanır. Düz kod hiçbir zaman kayıt edilmez.
  </div>

  <!-- ══════════════ HESAP ══════════════ -->
  <?php elseif($tab==='account'): ?>
  <div class="set-section-label">Oturum</div>
  <div class="set-group">
    <a href="/?logout=1" class="set-danger-btn"><i class="bi bi-box-arrow-right"></i> Çıkış Yap</a>
  </div>

  <div class="set-section-label" style="margin-top:22px;color:#EF4444;">Tehlikeli Bölge</div>
  <div class="set-group">
    <div class="set-row">
      <div>
        <label style="color:#EF4444;font-weight:600;">Hesabı Sil</label>
        <p style="font-size:.78rem;color:var(--muted);margin:2px 0 0;">Tüm sorular, yanıtlar ve veriler kalıcı olarak silinir. Bu işlem geri alınamaz.</p>
      </div>
      <button type="button" onclick="showDeleteModal()" class="set-danger-btn" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.4);color:#EF4444;flex-shrink:0;">
        <i class="bi bi-trash3"></i> Sil
      </button>
    </div>
  </div>

  <!-- Hesap Silme Onay Modal -->
  <div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);">
    <div style="background:linear-gradient(145deg,#13131f,#1a1a2e);border:1px solid rgba(239,68,68,.35);border-radius:22px;padding:32px 26px;max-width:360px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.6),0 0 0 1px rgba(239,68,68,.08);animation:modalIn .2s ease;">
      <div style="font-size:2.2rem;margin-bottom:10px;">🗑️</div>
      <h3 style="color:#EF4444;margin:0 0 8px;font-size:1.1rem;">Hesabını silmek istediğine emin misin?</h3>
      <p style="color:var(--muted);font-size:.82rem;margin:0 0 20px;line-height:1.5;">Tüm sorular, yanıtlar ve veriler <strong style="color:#EF4444;">kalıcı olarak</strong> silinecek. Bu işlem geri alınamaz.</p>
      <form method="POST" action="/">
        <input type="hidden" name="delete_account_confirm" value="1">
        <div style="display:flex;gap:10px;justify-content:center;">
          <button type="button" onclick="hideDeleteModal()" style="flex:1;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:transparent;color:var(--text);cursor:pointer;font-size:.9rem;">
            Vazgeç
          </button>
          <button type="submit" style="flex:1;padding:10px;border-radius:10px;border:none;background:linear-gradient(135deg,#EF4444,#DC2626);color:#fff;cursor:pointer;font-weight:700;font-size:.9rem;">
            Evet, Sil
          </button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function showDeleteModal(){ document.getElementById('deleteModal').style.display='flex'; }
    function hideDeleteModal(){ document.getElementById('deleteModal').style.display='none'; }
    document.getElementById('deleteModal').addEventListener('click', function(e){ if(e.target===this) hideDeleteModal(); });
    // Modal giriş animasyonu
    const _style = document.createElement('style');
    _style.textContent = '@keyframes modalIn{from{opacity:0;transform:scale(.94) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}';
    document.head.appendChild(_style);
  </script>

  <div class="set-section-label" style="margin-top:22px;color:var(--muted);">Uygulama</div>
  <div class="set-group">
    <div class="set-row" style="pointer-events:none;">
      <label>Sürüm</label>
      <span class="set-input" style="color:var(--muted);">geminy.me v5.5</span>
    </div>
    <div class="set-row" style="pointer-events:none;">
      <label>Katılma Tarihi</label>
      <span class="set-input" style="color:var(--muted);"><?=date('d F Y', strtotime($me['created_at']))?></span>
    </div>
  </div>

  <div class="set-section-label" style="color:var(--red);margin-top:22px;">Tehlikeli Alan</div>
  <div class="set-group">
    <div class="set-row" style="color:var(--muted);font-size:.82rem;line-height:1.6;">
      Hesap silme özelliği yakında gelecek. Şu an için destek için iletişime geçin.
    </div>
  </div>
  <?php endif;?>

  <div style="height:32px;"></div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <a href="/"                              class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"                        class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"                      class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications"                 class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?=e($loggedUser)?>"   class="nav-item active">
    <?php if(!empty($me['avatar_url'])): ?><img src="<?=e($me['avatar_url'])?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid var(--pink);"><?php else: ?><i class="bi bi-person-circle"></i><?php endif;?>
    <span>Profil</span>
  </a>
</nav>

<script>
function liveAvatar(url){
  const r=document.getElementById('aRing');
  r.innerHTML=url?`<img src="${url}" onerror="this.remove()" alt="">`:
    '<?=mb_strtoupper(mb_substr($loggedUser,0,1))?>';
}
function bioC(el){document.getElementById('bC').textContent=el.value.length+'/150';}
function tP(id,btn){
  const i=document.getElementById(id);
  i.type=i.type==='password'?'text':'password';
  btn.innerHTML=`<i class="bi bi-eye${i.type==='text'?'-slash':''}"></i>`;
}
</script>
</body>
</html>
