<?php
/**
 * geminy.me v8.0 — VIP Kurulum Sistemi (GTA Vice City Edition)
 */

define('CONFIG_FILE', __DIR__ . '/../app/config.php');
define('SQL_FILE',    __DIR__ . '/ezyro_41408315_plus.sql');
define('KEY_FILE',    __DIR__ . '/key/setupkey.txt');

// Kurulum zaten yapıldıysa engelle
$is_installed = false;
if (file_exists(CONFIG_FILE)) {
    $config_content = file_get_contents(CONFIG_FILE);
    if (strpos($config_content, "'DB_HOST',    ''") === false && strpos($config_content, "define('DB_HOST',    '')") === false) {
        $is_installed = true;
    }
}

$step = $_GET['step'] ?? 'auth';
$error = '';
$success = '';

// Auth Kontrolü
if ($step !== 'auth') {
    session_start();
    if (!isset($_SESSION['setup_authorized'])) {
        header('Location: /setup');
        exit;
    }
}

// ── ADIM 1: Anahtar Doğrulama ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_submit'])) {
    $key = trim($_POST['setup_key'] ?? '');
    $valid_key = trim(file_get_contents(KEY_FILE));
    
    if ($key === $valid_key) {
        session_start();
        $_SESSION['setup_authorized'] = true;
        header('Location: /setup?step=db');
        exit;
    } else {
        $error = 'Geçersiz kurulum anahtarı!';
    }
}

// ── ADIM 2: Veritabanı ve SMTP Bilgileri ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_submit'])) {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $site_url = rtrim($_POST['site_url'], '/');

    $smtp_host = $_POST['smtp_host'];
    $smtp_user = $_POST['smtp_user'];
    $smtp_pass = $_POST['smtp_pass'];

    try {
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name` ");

        if (file_exists(SQL_FILE)) {
            $sql = file_get_contents(SQL_FILE);
            $pdo->exec($sql);
        }

        $config_template = "<?php
// =============================================
// geminy.me v8.0 — Config & Helpers
// =============================================

define('DB_HOST',    '$db_host');
define('DB_NAME',    '$db_name');
define('DB_USER',    '$db_user');
define('DB_PASS',    '$db_pass');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   '$site_url'); 

// ── SMTP (MailerSend) ──────────────────────────────────────────
define('SMTP_HOST',     '$smtp_host');
define('SMTP_PORT',     587);
define('SMTP_USER',     '$smtp_user');
define('SMTP_PASS',     '$smtp_pass');
define('SMTP_FROM',     '$smtp_user');
define('SMTP_NAME',     'geminy.me');

define('SMTP_PORT_ALT', 2525);

function db(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return \$pdo;
}

function e(string \$s): string {
    return htmlspecialchars(\$s, ENT_QUOTES, 'UTF-8');
}
function redirect(string \$url): void {
    header(\"Location: \$url\"); exit;
}

function checkBruteForce(): bool {
    \$ipHash = hash('sha256', \$_SERVER['REMOTE_ADDR'] ?? '');
    \$cnt = db()->prepare(
        \"SELECT COUNT(*) FROM login_attempts
         WHERE ip_hash=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)\"
    );
    \$cnt->execute([\$ipHash]);
    return (int)\$cnt->fetchColumn() >= 5;
}
function logAttempt(?string \$username = null): void {
    \$ipHash = hash('sha256', \$_SERVER['REMOTE_ADDR'] ?? '');
    db()->prepare(\"INSERT INTO login_attempts (ip_hash,username) VALUES (?,?)\")
        ->execute([\$ipHash, \$username]);
}

function getUserTick(int \$totalLikes): array {
    if (\$totalLikes >= 30) return ['color' => '#A855F7', 'label' => 'Mor Tık',  'icon' => '✦', 'class' => 'tick-purple'];
    if (\$totalLikes >= 20) return ['color' => '#3B82F6', 'label' => 'Mavi Tık', 'icon' => '✦', 'class' => 'tick-blue'];
    if (\$totalLikes >= 10) return ['color' => '#EAB308', 'label' => 'Sarı Tık', 'icon' => '✦', 'class' => 'tick-yellow'];
    if (\$totalLikes >= 1)  return ['color' => '#22C55E', 'label' => 'Yeşil Tık','icon' => '✦', 'class' => 'tick-green'];
    return [];
}

function renderTick(int \$totalLikes, string \$size = 'md'): string {
    \$tick = getUserTick(\$totalLikes);
    if (empty(\$tick)) return '';
    \$px = \$size === 'sm' ? '13px' : (\$size === 'lg' ? '18px' : '15px');
    \$title = htmlspecialchars(\$tick['label'] . ' — ' . \$totalLikes . ' beğeni', ENT_QUOTES);
    return '<span class=\"geminy-tick ' . \$tick['class'] . '\" '
         . 'title=\"' . \$title . '\" '
         . 'style=\"color:' . \$tick['color'] . ';font-size:' . \$px . ';display:inline-block;vertical-align:middle;margin-left:3px;line-height:1;filter:drop-shadow(0 0 4px ' . \$tick['color'] . '88);\">'
         . '✦</span>';
}

function secureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset(\$_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
";
        file_put_contents(CONFIG_FILE, $config_template);
        
        $success = 'Kurulum başarıyla tamamlandı!';
        $step = 'finish';
        session_destroy();

    } catch (Exception $e) {
        $error = 'Hata: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>GeminyV8 VIP Setup | Vice City</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --pink: #FF2D78;
  --cyan: #00E5FF;
  --purple: #7C3AED;
  --bg: #0A0A0F;
  --glass: rgba(255,255,255,0.03);
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body {
  background: var(--bg);
  background-image: 
    radial-gradient(circle at 10% 20%, rgba(255, 45, 120, 0.05) 0%, transparent 40%),
    radial-gradient(circle at 90% 80%, rgba(0, 229, 255, 0.05) 0%, transparent 40%);
  color: #fff;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  margin: 0;
  padding: 20px;
}
.setup-container {
  width: 100%;
  max-width: 400px;
  animation: fadeIn 0.8s ease-out;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.setup-card {
  background: var(--glass);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 32px;
  padding: 35px 25px;
  backdrop-filter: blur(20px);
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
  position: relative;
  overflow: hidden;
}
.setup-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--pink), var(--cyan));
}

.logo-area { text-align: center; margin-bottom: 30px; }
.logo-text {
  font-size: 2.2rem;
  font-weight: 900;
  letter-spacing: -1px;
  background: linear-gradient(135deg, var(--pink), var(--purple));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 10px 20px rgba(255,45,120,0.2);
}
.logo-sub { color: #8B8FA8; font-size: 0.85rem; margin-top: 5px; font-weight: 500; }

.field { margin-bottom: 18px; }
.field label { display: block; font-size: 0.75rem; color: #6B6F9A; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.input-glass {
  width: 100%;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 14px 18px;
  color: #fff;
  font-size: 0.95rem;
  outline: none;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.input-glass:focus {
  border-color: var(--pink);
  background: rgba(255,255,255,0.07);
  box-shadow: 0 0 0 4px rgba(255,45,120,0.1);
}

.btn-neon {
  width: 100%;
  background: linear-gradient(135deg, var(--pink), var(--purple));
  border: none;
  border-radius: 16px;
  padding: 16px;
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s;
  margin-top: 10px;
  box-shadow: 0 10px 20px -5px rgba(255,45,120,0.4);
}
.btn-neon:active { transform: scale(0.98); }

.flash {
  padding: 14px;
  border-radius: 16px;
  margin-bottom: 20px;
  font-size: 0.85rem;
  text-align: center;
  font-weight: 500;
}
.flash-err { background: rgba(255, 45, 120, 0.1); border: 1px solid rgba(255, 45, 120, 0.2); color: var(--pink); }
.flash-ok { background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.2); color: var(--cyan); }

.details-toggle {
  text-align: center;
  color: #6B6F9A;
  font-size: 0.8rem;
  margin-top: 20px;
  cursor: pointer;
  font-weight: 600;
}
.details-toggle:hover { color: #fff; }

#smtp-details { display: none; margin-top: 25px; padding-top: 20px; border-top: 1px dashed rgba(255,255,255,0.1); }

.footer-text { text-align: center; margin-top: 25px; color: #4A4D66; font-size: 0.75rem; font-weight: 500; }
</style>
</head>
<body>

<div class="setup-container">
  <div class="setup-card">
    <div class="logo-area">
      <div class="logo-text">GeminyV8</div>
      <div class="logo-sub">VICE CITY EDITION 🌴</div>
    </div>

    <?php if ($is_installed && $step !== 'finish'): ?>
      <div class="flash flash-ok"><i class="bi bi-shield-check"></i> Sistem zaten kurulu!</div>
      <a href="/" class="btn-neon" style="display:block; text-align:center; text-decoration:none;">Ana Sayfaya Git</a>
    <?php else: ?>

      <?php if ($error): ?> <div class="flash flash-err"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div> <?php endif; ?>
      <?php if ($success): ?> <div class="flash flash-ok"><i class="bi bi-check-circle"></i> <?= $success ?></div> <?php endif; ?>

      <?php if ($step === 'auth'): ?>
        <form method="POST">
          <div class="field">
            <label>Kurulum Anahtarı</label>
            <input type="password" name="setup_key" class="input-glass" placeholder="setupkey.txt içindeki kod" required autofocus>
          </div>
          <button type="submit" name="auth_submit" class="btn-neon">Sistemi Aç 🥂</button>
        </form>

      <?php elseif ($step === 'db'): ?>
        <form method="POST">
          <div class="field">
            <label>Site URL</label>
            <input type="text" name="site_url" class="input-glass" value="http://<?= $_SERVER['HTTP_HOST'] ?>" required>
          </div>
          <div class="field">
            <label>Database Host</label>
            <input type="text" name="db_host" class="input-glass" value="localhost" required>
          </div>
          <div class="field">
            <label>Database Name</label>
            <input type="text" name="db_name" class="input-glass" placeholder="ezyro_..." required>
          </div>
          <div class="field">
            <label>Database User</label>
            <input type="text" name="db_user" class="input-glass" placeholder="ezyro_..." required>
          </div>
          <div class="field">
            <label>Database Password</label>
            <input type="password" name="db_pass" class="input-glass" placeholder="••••••••">
          </div>

          <div class="details-toggle" onclick="document.getElementById('smtp-details').style.display='block'; this.style.display='none'">
            <i class="bi bi-plus-circle"></i> SMTP Ayarlarını Yapılandır
          </div>

          <div id="smtp-details">
            <div class="field">
              <label>SMTP Host</label>
              <input type="text" name="smtp_host" class="input-glass" value="smtp.mailersend.net">
            </div>
            <div class="field">
              <label>SMTP User</label>
              <input type="text" name="smtp_user" class="input-glass" placeholder="user@domain.com">
            </div>
            <div class="field">
              <label>SMTP Password</label>
              <input type="password" name="smtp_pass" class="input-glass" placeholder="••••••••">
            </div>
          </div>

          <button type="submit" name="setup_submit" class="btn-neon">Kurulumu Başlat 🚀</button>
        </form>

      <?php elseif ($step === 'finish'): ?>
        <div style="text-align:center;">
          <div style="width:70px; height:70px; background:rgba(0, 229, 255, 0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; color:var(--cyan); font-size:2rem;">
            <i class="bi bi-check2-all"></i>
          </div>
          <h3 style="margin:0 0 10px; font-size:1.3rem;">Hazırsın Kanki!</h3>
          <p style="color:#8B8FA8; font-size:0.9rem; line-height:1.6; margin-bottom:25px;">GeminyV8 başarıyla kuruldu. Şimdi şehri yönetme zamanı. 🥃</p>
          <a href="/" class="btn-neon" style="display:block; text-align:center; text-decoration:none;">Sisteme Giriş Yap</a>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
  <div class="footer-text">GEMINY V8 • MADE WITH ❤️</div>
</div>

</body>
</html>
