<?php
require_once __DIR__ . '/../app/config.php';
secureSession();

$error   = '';
$success = '';
$loggedUser = $_SESSION['username'] ?? null;

ini_set('display_errors', 1);
error_reporting(E_ALL);


// ── Çıkış ────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect(SITE_URL . '/');
}

// ── Hesap Silme ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account_confirm'])) {
    $delUser = $_SESSION['user'] ?? null;
    if ($delUser) {
        try {
            // Cascade sırası: likes → replies → messages → follows → user
            db()->prepare('DELETE ml FROM message_likes ml JOIN messages m ON m.id=ml.message_id WHERE m.to_user=?')->execute([$delUser]);
            db()->prepare('DELETE r FROM replies r JOIN messages m ON m.id=r.message_id WHERE m.to_user=?')->execute([$delUser]);
            db()->prepare('DELETE FROM messages WHERE to_user=?')->execute([$delUser]);
            db()->prepare('DELETE FROM follows WHERE follower=? OR following=?')->execute([$delUser, $delUser]);
            db()->prepare('DELETE FROM profile_views WHERE username=?')->execute([$delUser]);
            db()->prepare('DELETE FROM password_resets WHERE username=?')->execute([$delUser]);
            db()->prepare('DELETE FROM login_attempts WHERE username=?')->execute([$delUser]);
            db()->prepare('DELETE FROM users WHERE username=?')->execute([$delUser]);
        } catch (Exception $e) { /* hata logla */ }
    }
    $_SESSION = [];
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
            if ($row['two_fa_enabled']) {
                $_SESSION['2fa_pending'] = $row['username'];
                require_once __DIR__ . '/../app/smtp/mail.php';
                $code = generate2FACode($row['username'], 'login');
                mailTwoFactorCode($row['email'], $row['display_name'] ?? $row['username'], $code, $row['username']);
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
        require_once __DIR__ . '/../app/smtp/mail.php';
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
$signupMode = isset($_GET['signup']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>geminy.me — Anonim Sorular</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
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
      <a href="/signup" class="icon-btn" title="Kaydol"><i class="bi bi-person-plus"></i></a>
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
          <span class="uname"><?= e($msg['display_name'] ?? $msg['to_user']) ?><?= renderTick((int)$msg['likes'], 'sm') ?></span>
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

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
<?php if ($loggedUser): ?>
  <a href="/"             class="nav-item active"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"       class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"     class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications" class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item">
    <?php if (!empty($me['avatar_url'])): ?>
      <img src="<?= e($me['avatar_url']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
    <?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
<?php else: ?>
  <a href="/" class="nav-item active"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search" class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <button id="openLoginBtn2" class="nav-item" style="background:none;border:none;cursor:pointer;color:var(--muted);">
    <i class="bi bi-person-fill"></i><span>Giriş</span>
  </button>
<?php endif; ?>
</nav>

<!-- ── KAYIT OVERLAY ── -->
<div id="regOverlay" class="auth-overlay" style="display:<?= $signupMode ? 'flex' : 'none' ?>;">
  <div class="auth-sheet">
    <div class="auth-drag-handle"></div>
    <div class="auth-inner">
      <div class="auth-logo">geminy.me</div>
      <p class="auth-sub">Şehrini kur, anını paylaş 🌴</p>
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
        <div class="reg-step active" id="step1">
          <p class="step-title">Profil <span>fotoğrafın</span></p>
          <div class="avatar-preview-wrap">
            <div class="avatar-preview-ring"><div class="inner" id="avatarPreview">?</div></div>
          </div>
          <div class="field">
            <label><i class="bi bi-link-45deg"></i> Fotoğraf URL</label>
            <input type="url" name="avatar_url" id="avatarUrlInput" class="input-glass" placeholder="https://...">
          </div>
          <button type="button" class="btn-neon" onclick="goStep(2)">Devam Et →</button>
          <p class="hint"><a href="/" class="closeAuthBtn">Vazgeç</a></p>
        </div>
        <div class="reg-step" id="step2">
          <p class="step-title">Hesap <span>bilgilerin</span></p>
          <div class="field">
            <label><i class="bi bi-at"></i> Kullanıcı Adı *</label>
            <input type="text" name="username" class="input-glass" placeholder="vice_city_07" required maxlength="30">
          </div>
          <div class="field">
            <label><i class="bi bi-envelope"></i> E-posta *</label>
            <input type="email" name="email" class="input-glass" placeholder="ornek@mail.com" required>
          </div>
          <div class="field">
            <label><i class="bi bi-lock"></i> Şifre *</label>
            <div style="position:relative;">
              <input type="password" name="password" id="passInput" class="input-glass" placeholder="En az 6 karakter" required minlength="6">
              <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;"><i class="bi bi-eye" id="eyeIcon"></i></button>
            </div>
          </div>
          <div style="display:flex;gap:8px;">
            <button type="button" class="btn-outline" onclick="goStep(1)" style="width:auto;padding:12px 20px;">←</button>
            <button type="button" class="btn-neon" onclick="goStep(3)">Devam Et →</button>
          </div>
          <p class="hint"><a href="/" class="closeAuthBtn">Vazgeç</a></p>
        </div>
        <div class="reg-step" id="step3">
          <p class="step-title">Hazır <span>mısın?</span> 🌴</p>
          <div style="background:rgba(255,45,120,.06);border:1px solid rgba(255,45,120,.15);border-radius:14px;padding:14px;margin-bottom:18px;font-size:.82rem;color:var(--muted);line-height:1.7;">
            ✅ Fotoğrafın URL olarak kaydedildi<br>
            🔒 Şifren güvenli şifrelendi<br>
            🚀 geminy.me'ye hoş geldin
          </div>
          <button type="submit" class="btn-neon">Profili Oluştur 🥂</button>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="button" class="btn-outline" onclick="goStep(2)" style="width:auto;padding:12px 20px;">←</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- GİRİŞ OVERLAY -->
<div id="loginOverlay" class="auth-overlay" style="display:<?= $tfa ? 'flex' : 'none' ?>;">
  <div class="auth-sheet">
    <div class="auth-drag-handle"></div>
    <div class="auth-inner">
      <div class="auth-logo">geminy.me</div>
      <?php if ($tfa): ?>
        <p class="auth-sub">Güvenlik Doğrulaması 🔒</p>
        <form method="POST">
          <div class="field">
            <label>E-posta ile gelen kod</label>
            <input type="text" name="tfa_code" class="input-glass" placeholder="000000" required autofocus maxlength="6">
          </div>
          <button type="submit" class="btn-neon">Doğrula ve Gir</button>
          <p class="hint"><a href="/">Vazgeç</a></p>
        </form>
      <?php else: ?>
        <p class="auth-sub">Tekrar hoş geldin kanki! 🥃</p>
        <?php if ($error && !isset($_POST['reg_submit'])): ?>
          <div class="flash flash-err"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="login_submit" value="1">
          <div class="field">
            <label>Kullanıcı Adı veya E-posta</label>
            <input type="text" name="login_id" class="input-glass" placeholder="tommy_v" required>
          </div>
          <div class="field">
            <label>Şifre</label>
            <input type="password" name="login_pass" class="input-glass" placeholder="••••••••" required>
          </div>
          <div style="text-align:right; margin-bottom:15px;">
            <a href="/reset" style="color:var(--pink); font-size:0.75rem; text-decoration:none; font-weight:600;">Şifremi Unuttum?</a>
          </div>
          <button type="submit" class="btn-neon">Giriş Yap 🥂</button>
          <p class="hint">Hesabın yok mu? <a href="/signup" style="color:var(--pink);font-weight:700;">Kaydol</a></p>
          <p class="hint"><a href="/" class="closeAuthBtn">Vazgeç</a></p>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function goStep(n){
  document.querySelectorAll('.reg-step').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.reg-step-dot').forEach(d => d.classList.remove('active'));
  document.getElementById('step'+n).classList.add('active');
  document.getElementById('dot'+n).classList.add('active');
}
function togglePass(){
  const p = document.getElementById('passInput');
  const i = document.getElementById('eyeIcon');
  if(p.type==='password'){ p.type='text'; i.classList.replace('bi-eye','bi-eye-slash'); }
  else { p.type='password'; i.classList.replace('bi-eye-slash','bi-eye'); }
}
document.getElementById('openLoginBtn')?.addEventListener('click', () => { document.getElementById('loginOverlay').style.display='flex'; });
document.getElementById('openLoginBtn2')?.addEventListener('click', () => { document.getElementById('loginOverlay').style.display='flex'; });
document.querySelectorAll('.closeAuthBtn').forEach(b => b.addEventListener('click', (e) => { 
  e.preventDefault();
  document.getElementById('loginOverlay').style.display='none';
  document.getElementById('regOverlay').style.display='none';
}));
</script>
</body>
</html>
