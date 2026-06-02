<?php
require_once __DIR__ . '/app/config.php';
secureSession();

$error   = '';
$success = '';
$loggedUser = $_SESSION['username'] ?? null;

// ── Çıkış ────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    redirect(SITE_URL . '/');
}

// ── Giriş ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if (checkBruteForce()) {
        $error = 'Çok fazla deneme. 15 dakika bekle.';
    } else {
        $loginId   = trim($_POST['login_id']   ?? '');
        $loginPass = $_POST['login_pass'] ?? '';
        $stmt = db()->prepare('SELECT * FROM users WHERE username=? OR email=? LIMIT 1');
        $stmt->execute([$loginId, $loginId]);
        $row = $stmt->fetch();

        if ($row && $row['password_hash'] && password_verify($loginPass, $row['password_hash'])) {
            // 2FA aktif mi?
            if ($row['two_fa_enabled']) {
                $_SESSION['2fa_pending'] = $row['username'];
                // Kod üret → hash DB'ye, düz kod sadece maile gider
                require_once __DIR__ . '/app/smtp/mail.php';
                $code = generate2FACode($row['username'], 'login');
                mailTwoFactorCode($row['email'], $row['display_name'] ?? $row['username'], $code);
                redirect(SITE_URL . '/?tfa=1');
            }
            $_SESSION['username'] = $row['username'];
            redirect(SITE_URL . '/profile/' . $row['username']);
        } else {
            logAttempt($loginId);
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

// ── 2FA doğrulama ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfa_code'])) {
    $pending = $_SESSION['2fa_pending'] ?? null;
    $code    = trim($_POST['tfa_code'] ?? '');
    if ($pending && $code) {
        require_once __DIR__ . '/app/smtp/mail.php';
        if (verify2FACode($pending, $code, 'login')) {
            unset($_SESSION['2fa_pending']);
            $_SESSION['username'] = $pending;
            redirect(SITE_URL . '/profile/' . $pending);
        } else {
            $error = 'Kod hatalı veya süresi dolmuş.';
        }
    }
}

// ── Kayıt ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_submit'])) {
    $username     = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['username'] ?? '')));
    $display_name = mb_substr(trim($_POST['display_name'] ?? ''), 0, 50);
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password     = $_POST['password'] ?? '';
    $avatar_url   = trim($_POST['avatar_url'] ?? '');

    if (strlen($username) < 3)  $error = 'Kullanıcı adı en az 3 karakter.';
    elseif (!$email)             $error = 'Geçerli e-posta gir.';
    elseif (strlen($password)<6) $error = 'Şifre en az 6 karakter.';
    else {
        $chk = db()->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $error = '@'.$username.' veya bu e-posta zaten kayıtlı.';
        } else {
            if ($avatar_url && !preg_match('/^https?:\/\//i', $avatar_url)) $avatar_url = '';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare(
                'INSERT INTO users (username,display_name,email,password_hash,avatar_url) VALUES (?,?,?,?,?)'
            )->execute([$username, $display_name ?: null, $email, $hash, $avatar_url ?: null]);
            $_SESSION['username'] = $username;
            redirect(SITE_URL . '/profile/' . $username);
        }
    }
}

// ── Beğeni AJAX ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_id'])) {
    header('Content-Type: application/json');
    $mid    = preg_replace('/[^a-z0-9.]/', '', $_POST['like_id']);
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    try {
        db()->prepare('INSERT INTO message_likes (message_id,ip_hash) VALUES (?,?)')->execute([$mid,$ipHash]);
        $cnt = db()->prepare('SELECT COUNT(*) FROM message_likes WHERE message_id=?');
        $cnt->execute([$mid]);
        echo json_encode(['ok'=>true,'count'=>(int)$cnt->fetchColumn()]);
    } catch (PDOException) {
        $cnt = db()->prepare('SELECT COUNT(*) FROM message_likes WHERE message_id=?');
        $cnt->execute([$mid]);
        echo json_encode(['ok'=>false,'count'=>(int)$cnt->fetchColumn()]);
    }
    exit;
}

// ── Feed ─────────────────────────────────────────────────────
// Özel hesapların soruları feed'de gözükmez (is_private=1)
$feed = db()->query(
    'SELECT m.id, m.to_user, m.text, m.created_at,
            u.display_name, u.avatar_url,
            (SELECT COUNT(*) FROM message_likes l WHERE l.message_id=m.id) AS likes,
            (SELECT COUNT(*) FROM replies r WHERE r.message_id=m.id) AS reply_count
     FROM messages m
     LEFT JOIN users u ON u.username = m.to_user
     WHERE (u.is_private = 0 OR u.is_private IS NULL)
     ORDER BY m.created_at DESC
     LIMIT 40'
)->fetchAll();

$me = null;
if ($loggedUser) {
    $s = db()->prepare('SELECT * FROM users WHERE username=?');
    $s->execute([$loggedUser]);
    $me = $s->fetch();
}

$tfa = isset($_GET['tfa']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>geminy.me — Anonim Sorular</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="top-bar">
  <a href="/" class="brand-soft">geminy.me</a>
  <div class="top-bar-actions">
    <?php if ($loggedUser): ?>
      <a href="/notifications" class="icon-btn"><i class="bi bi-bell"></i></a>
      <a href="/profile/<?= e($loggedUser) ?>" class="icon-btn" title="Profil">
        <?php if (!empty($me['avatar_url'])): ?>
          <img src="<?= e($me['avatar_url']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
        <?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
      </a>
    <?php else: ?>
      <button class="icon-btn" id="openLoginBtn"><i class="bi bi-box-arrow-in-right"></i></button>
    <?php endif; ?>
  </div>
</header>

<!-- FEED -->
<div style="padding:0 14px;">
  <p class="section-label">🔥 Canlı Akış</p>
  <?php if (empty($feed)): ?>
    <div class="glass-card" style="text-align:center;opacity:.4;padding:28px;">Henüz hiç fısıltı yok... İlk sen ol ✨</div>
  <?php else: foreach ($feed as $msg): ?>
  <div class="post-card">
    <div class="post-header">
      <a href="/profile/<?= e($msg['to_user']) ?>" style="text-decoration:none;">
        <div class="avatar-grad avatar-sm">
          <?php if (!empty($msg['avatar_url'])): ?>
            <img src="<?= e($msg['avatar_url']) ?>" alt="">
          <?php else: echo strtoupper($msg['to_user'][0]); endif; ?>
        </div>
      </a>
      <div class="info">
        <a href="/profile/<?= e($msg['to_user']) ?>" style="text-decoration:none;">
          <span class="uname"><?= e($msg['display_name'] ?? $msg['to_user']) ?></span>
          <span class="handle">@<?= e($msg['to_user']) ?></span>
        </a>
      </div>
      <span class="time"><?= date('d.m H:i', strtotime($msg['created_at'])) ?></span>
    </div>
    <div class="post-body"><?= e($msg['text']) ?></div>
    <div class="post-actions">
      <?php if ($loggedUser): ?>
        <button class="post-action like-btn" data-id="<?= e($msg['id']) ?>" onclick="likePost(this)">
          <i class="bi bi-heart"></i><span><?= $msg['likes'] ?: '' ?></span>
        </button>
      <?php else: ?>
        <button class="post-action" onclick="document.getElementById('loginOverlay').style.display='flex'">
          <i class="bi bi-heart"></i><span><?= $msg['likes'] ?: '' ?></span>
        </button>
      <?php endif; ?>
      <a href="/profile/<?= e($msg['to_user']) ?>" class="post-action">
        <i class="bi bi-chat"></i><span><?= $msg['reply_count'] ?: '' ?></span>
      </a>
      <button class="post-action" onclick="sharePost('<?= e($msg['to_user']) ?>')">
        <i class="bi bi-arrow-up-right-square"></i>
      </button>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<!-- BOTTOM NAV — Misafir: iOS tarzı -->
<nav class="bottom-nav">
<?php if ($loggedUser): ?>
  <a href="/"             class="nav-item active"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"       class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"     class="nav-item">
    <i class="bi bi-send"></i><span>Mesajlar</span>
  </a>
  <a href="/notifications" class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item">
    <?php if (!empty($me['avatar_url'])): ?>
      <img src="<?= e($me['avatar_url']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
    <?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
<?php else: ?>
  <!-- Misafir nav: Ana Sayfa, Keşfet, Giriş -->
  <a href="/" class="nav-item active"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search" class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <button id="openLoginBtn2" class="nav-item" style="background:none;border:none;cursor:pointer;color:var(--muted);">
    <i class="bi bi-person-fill"></i><span>Giriş</span>
  </button>
<?php endif; ?>
</nav>

<!-- ── KAYIT OVERLAY ── -->
<div id="regOverlay" class="auth-overlay" style="display:none;">
  <div class="auth-sheet">
    <div class="auth-drag-handle"></div>
    <div class="auth-inner">

      <div class="auth-logo">geminy.me</div>
      <p class="auth-sub">Şehrini kur, anını paylaş 🌴</p>

      <!-- Step dots -->
      <div class="reg-steps">
        <div class="reg-step-dot active" id="dot1"></div>
        <div class="reg-step-dot" id="dot2"></div>
        <div class="reg-step-dot" id="dot3"></div>
      </div>

      <?php if ($error && isset($_POST['reg_submit'])): ?>
        <div class="flash flash-err"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="regForm">
        <input type="hidden" name="reg_submit" value="1">

        <!-- Adım 1 -->
        <div class="reg-step active" id="step1">
          <p class="step-title">Profil <span>fotoğrafın</span></p>
          <div class="avatar-preview-wrap">
            <div class="avatar-preview-ring">
              <div class="inner" id="avatarPreview">?</div>
            </div>
          </div>
          <div class="field">
            <label><i class="bi bi-link-45deg"></i> Fotoğraf URL</label>
            <input type="url" name="avatar_url" id="avatarUrlInput" class="input-glass"
                   placeholder="https://..." value="">
            <div style="font-size:.7rem;color:var(--muted);margin-top:5px;">Instagram / Twitter profil linki yapıştır 🔒</div>
          </div>
          <button type="button" class="btn-neon" onclick="goStep(2)">Devam Et →</button>
          <p class="hint"><a href="#" class="closeAuthBtn">Vazgeç</a></p>
        </div>

        <!-- Adım 2 -->
        <div class="reg-step" id="step2">
          <p class="step-title">Hesap <span>bilgilerin</span></p>
          <div class="field">
            <label><i class="bi bi-at"></i> Kullanıcı Adı *</label>
            <input type="text" name="username" class="input-glass" placeholder="vice_city_07" required maxlength="30" pattern="[a-zA-Z0-9_]+">
          </div>
          <div class="field">
            <label><i class="bi bi-person"></i> Görünen Ad</label>
            <input type="text" name="display_name" class="input-glass" placeholder="Tommy V." maxlength="50">
          </div>
          <div class="field">
            <label><i class="bi bi-envelope"></i> E-posta *</label>
            <input type="email" name="email" class="input-glass" placeholder="ornek@mail.com" required>
          </div>
          <div class="field">
            <label><i class="bi bi-lock"></i> Şifre *</label>
            <div style="position:relative;">
              <input type="password" name="password" id="passInput" class="input-glass" placeholder="En az 6 karakter" required minlength="6" style="padding-right:46px!important;">
              <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.1rem;">
                <i class="bi bi-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>
          <div style="display:flex;gap:8px;">
            <button type="button" class="btn-outline" onclick="goStep(1)" style="width:auto;padding:12px 20px;">←</button>
            <button type="button" class="btn-neon" onclick="goStep(3)">Devam Et →</button>
          </div>
          <p class="hint"><a href="#" class="closeAuthBtn">Vazgeç</a></p>
        </div>

        <!-- Adım 3 -->
        <div class="reg-step" id="step3">
          <p class="step-title">Hazır <span>mısın?</span> 🌴</p>
          <div style="text-align:center;margin-bottom:22px;">
            <div class="avatar-grad avatar-xl" id="finalAvatar" style="margin:0 auto 12px;">?</div>
            <div id="finalName" style="font-weight:800;font-size:1.1rem;"></div>
            <div id="finalHandle" style="color:var(--muted);font-size:.82rem;margin-top:2px;"></div>
          </div>
          <div style="background:rgba(255,45,120,.06);border:1px solid rgba(255,45,120,.15);border-radius:14px;padding:14px;margin-bottom:18px;font-size:.82rem;color:var(--muted);line-height:1.7;">
            ✅ Fotoğrafın URL olarak kaydedildi<br>
            🔒 Şifren güvenli şifrelendi<br>
            🚀 geminy.me'ye hoş geldin
          </div>
          <button type="submit" class="btn-neon">Profili Oluştur 🥂</button>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="button" class="btn-outline" onclick="goStep(2)" style="width:auto;padding:11px 20px;">←</button>
            <button type="button" class="btn-outline closeAuthBtn" style="flex:1;">Vazgeç</button>
          </div>
        </div>
      </form>

      <p style="text-align:center;margin-top:16px;font-size:.82rem;">
        Hesabın var mı? <a href="#" onclick="showLogin()">Giriş Yap</a>
      </p>
    </div>
  </div>
</div>

<!-- ── GİRİŞ OVERLAY ── -->
<div id="loginOverlay" class="auth-overlay" style="display:none;">
  <div class="auth-sheet">
    <div class="auth-drag-handle"></div>
    <div class="auth-inner">

      <?php if ($tfa): ?>
      <!-- 2FA kod girişi -->
      <div class="auth-logo">geminy.me</div>
      <p class="auth-sub">Doğrulama kodu e-postana gönderildi 📧</p>
      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <div class="field">
          <label><i class="bi bi-shield-lock"></i> 6 Haneli Kod</label>
          <input type="text" name="tfa_code" class="input-glass" placeholder="000000"
                 maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autofocus
                 style="font-size:1.6rem;letter-spacing:10px;text-align:center;">
        </div>
        <button type="submit" class="btn-neon">Doğrula ✅</button>
      </form>
      <p class="hint"><a href="/">← Geri dön</a></p>

      <?php else: ?>
      <div class="auth-logo">geminy.me</div>
      <p class="auth-sub">Hesabına giriş yap ✨</p>
      <?php if ($error && isset($_POST['login_submit'])): ?>
        <div class="flash flash-err"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="login_submit" value="1">
        <div class="field">
          <label><i class="bi bi-at"></i> Kullanıcı Adı veya E-posta</label>
          <input type="text" name="login_id" class="input-glass" placeholder="vice_city_07" required>
        </div>
        <div class="field">
          <label><i class="bi bi-lock"></i> Şifre</label>
          <div style="position:relative;">
            <input type="password" name="login_pass" id="lpass" class="input-glass" placeholder="••••••" required style="padding-right:46px!important;">
            <button type="button" onclick="togL()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.1rem;">
              <i class="bi bi-eye" id="lEye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-neon">Giriş Yap 🚀</button>
      </form>
      <p class="hint" style="margin-top:14px;text-align:center;">
        <a href="/reset" style="color:var(--muted);">Şifremi unuttum</a>
      </p>
      <p class="hint" style="text-align:center;">
        Hesabın yok mu? <a href="#" onclick="showReg()">Kayıt Ol</a>
      </p>
      <p class="hint" style="text-align:center;">
        <a href="#" class="closeAuthBtn" style="color:var(--muted);">Kapat</a>
      </p>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Overlay backdrop -->
<div id="authBackdrop" class="auth-backdrop" style="display:none;" onclick="closeAuth()"></div>

<script>
const regOverlay   = document.getElementById('regOverlay');
const loginOverlay = document.getElementById('loginOverlay');
const backdrop     = document.getElementById('authBackdrop');

function openAuth(el) { el.style.display='flex'; backdrop.style.display='block'; document.body.style.overflow='hidden'; }
function closeAuth()  { regOverlay.style.display='none'; loginOverlay.style.display='none'; backdrop.style.display='none'; document.body.style.overflow=''; }
function showReg()    { closeAuth(); openAuth(regOverlay); }
function showLogin()  { closeAuth(); openAuth(loginOverlay); }

document.getElementById('openRegBtn')?.addEventListener('click',  () => showReg());
document.getElementById('guestRegBtn')?.addEventListener('click', () => showReg());
document.querySelectorAll('#openLoginBtn,#openLoginBtn2').forEach(b => b?.addEventListener('click', () => showLogin()));
document.querySelectorAll('.closeAuthBtn').forEach(b => b.addEventListener('click', e => { e.preventDefault(); closeAuth(); }));

<?php if ($error && isset($_POST['reg_submit'])): ?>regOverlay.style.display='flex'; backdrop.style.display='block';<?php endif; ?>
<?php if ($error && isset($_POST['login_submit'])): ?>loginOverlay.style.display='flex'; backdrop.style.display='block';<?php endif; ?>
<?php if ($tfa): ?>loginOverlay.style.display='flex'; backdrop.style.display='block';<?php endif; ?>

// Avatar önizleme
const urlInput = document.getElementById('avatarUrlInput');
const preview  = document.getElementById('avatarPreview');
urlInput?.addEventListener('input', () => {
    const v = urlInput.value.trim();
    preview.innerHTML = v ? `<img src="${v}" onerror="this.parentElement.textContent='?'" alt="">` : '?';
});

// Şifre toggle
function togglePass() {
    const i=document.getElementById('passInput'), ic=document.getElementById('eyeIcon');
    i.type=i.type==='password'?'text':'password';
    ic.className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash';
}
function togL() {
    const i=document.getElementById('lpass'), ic=document.getElementById('lEye');
    i.type=i.type==='password'?'text':'password';
    ic.className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash';
}

// Adım geçişi
function goStep(n) {
    if (n===3) updateSummary();
    document.querySelectorAll('.reg-step').forEach((s,i) => s.classList.toggle('active', i+1===n));
    document.querySelectorAll('.reg-step-dot').forEach((d,i) => {
        d.classList.remove('active','done');
        if (i+1<n) d.classList.add('done');
        if (i+1===n) d.classList.add('active');
    });
}
function updateSummary() {
    const avatarUrl = document.getElementById('avatarUrlInput')?.value.trim();
    const username  = document.querySelector('[name=username]')?.value.trim();
    const dispName  = document.querySelector('[name=display_name]')?.value.trim();
    const fa = document.getElementById('finalAvatar');
    fa.innerHTML = avatarUrl ? `<img src="${avatarUrl}" onerror="this.parentElement.textContent='${username?.[0]?.toUpperCase()||'?'}'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">` : (username?.[0]?.toUpperCase()||'?');
    document.getElementById('finalName').textContent   = dispName || username || '—';
    document.getElementById('finalHandle').textContent = username ? '@'+username : '';
}

// Beğeni
async function likePost(btn) {
    const id = btn.dataset.id;
    const res = await fetch('/', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`like_id=${encodeURIComponent(id)}` });
    const data = await res.json();
    btn.classList.toggle('liked', data.ok);
    btn.querySelector('span').textContent = data.count || '';
    btn.querySelector('i').className = data.ok ? 'bi bi-heart-fill' : 'bi bi-heart';
    if (data.ok) btn.querySelector('i').style.color='var(--pink)';
}

function sharePost(user) {
    const url='<?= SITE_URL ?>/profile/'+user;
    if (navigator.share) navigator.share({url});
    else navigator.clipboard.writeText(url);
}
</script>
</body>
</html>
