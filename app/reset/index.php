<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/smtp/mail.php';
secureSession();

$step    = $_GET['step'] ?? 'request';
$token   = trim($_GET['token'] ?? '');
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_email'])) {
    $email = filter_var(trim($_POST['req_email']), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $error = 'Geçerli bir e-posta gir.';
    } else {
        $u = db()->prepare('SELECT username, display_name FROM users WHERE email=?');
        $u->execute([$email]);
        $user = $u->fetch();
        if ($user) {
            $rawToken = generateResetToken($email);
            $url = SITE_URL . '/reset?step=verify&token=' . $rawToken;
            mailPasswordReset($email, $user['display_name'] ?? $user['username'], $url, $user['username']);
        }
        $success = 'Eğer bu e-posta kayıtlıysa, sıfırlama linki gönderildi.';
        $step = 'sent';
    }
}

$tokenRow = null;
if ($token && $step === 'verify') {
    $tokenRow = verifyResetToken($token);
    if (!$tokenRow) { $error = 'Link geçersiz veya süresi dolmuş.'; $step = 'request'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_pass'], $_POST['token'])) {
    $rawTok   = trim($_POST['token']);
    $pass     = $_POST['new_pass'] ?? '';
    $rep      = $_POST['rep_pass'] ?? '';
    $tokenRow = verifyResetToken($rawTok);
    if (!$tokenRow)          $error = 'Link geçersiz veya süresi dolmuş.';
    elseif (strlen($pass)<6) $error = 'Şifre en az 6 karakter olmalı.';
    elseif ($pass !== $rep)  $error = 'Şifreler eşleşmiyor.';
    else {
        db()->prepare('UPDATE users SET password_hash=? WHERE email=?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $tokenRow['email']]);
        useResetToken($rawTok);
        $success = 'Şifren güncellendi! Şimdi giriş yapabilirsin.';
        $step = 'done';
    }
}
?>
<!DOCTYPE html><html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Şifre Sıfırla | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.reset-page{min-height:100svh;display:flex;align-items:center;justify-content:center;padding:24px 16px;}
.reset-card{width:100%;max-width:400px;background:rgba(255,255,255,.04);border:1px solid rgba(233,30,140,.18);border-radius:24px;padding:32px 24px;}
.reset-icon{text-align:center;font-size:3rem;margin-bottom:16px;}
.reset-sub{text-align:center;color:var(--muted);font-size:.85rem;margin-bottom:28px;}
.pass-wrap{position:relative;}
.pass-wrap input{padding-right:46px!important;}
.pass-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.1rem;}
</style>
</head>
<body>
<div class="reset-page"><div class="reset-card">
  <a href="/" class="brand" style="display:block;text-align:center;margin-bottom:6px;font-size:1.9rem;">geminy.me</a>

  <?php if ($step==='request'||$step==='sent'): ?>
  <div class="reset-icon">🔑</div>
  <p class="reset-sub">Şifreni sıfırlamak için e-postanı gir</p>
  <?php if($error):   ?><div class="flash flash-err" style="margin-bottom:16px;"><?=e($error)?></div><?php endif;?>
  <?php if($success): ?><div class="flash flash-ok"  style="margin-bottom:16px;"><?=e($success)?></div><?php endif;?>
  <?php if($step!=='sent'): ?>
  <form method="POST">
    <div class="field"><label><i class="bi bi-envelope"></i> E-posta</label>
      <input type="email" name="req_email" class="input-glass" placeholder="ornek@mail.com" required autofocus></div>
    <button type="submit" class="btn-neon">Link Gönder <i class="bi bi-send"></i></button>
  </form>
  <?php endif;?>

  <?php elseif($step==='verify'&&$tokenRow): ?>
  <div class="reset-icon">🔐</div>
  <p class="reset-sub">Yeni şifreni belirle</p>
  <?php if($error): ?><div class="flash flash-err" style="margin-bottom:16px;"><?=e($error)?></div><?php endif;?>
  <form method="POST">
    <input type="hidden" name="token" value="<?=e($token)?>">
    <div class="field"><label><i class="bi bi-lock"></i> Yeni Şifre</label>
      <div class="pass-wrap"><input type="password" name="new_pass" id="np" class="input-glass" placeholder="En az 6 karakter" required minlength="6">
      <button type="button" class="pass-eye" onclick="tog('np',this)"><i class="bi bi-eye"></i></button></div></div>
    <div class="field"><label><i class="bi bi-lock-fill"></i> Tekrar</label>
      <div class="pass-wrap"><input type="password" name="rep_pass" id="rp" class="input-glass" placeholder="Tekrar gir" required>
      <button type="button" class="pass-eye" onclick="tog('rp',this)"><i class="bi bi-eye"></i></button></div></div>
    <button type="submit" class="btn-neon">Şifreyi Güncelle ✅</button>
  </form>

  <?php elseif($step==='done'): ?>
  <div class="reset-icon">🎉</div>
  <p class="reset-sub"><?=e($success)?></p>
  <a href="/" class="btn-neon" style="text-decoration:none;display:block;text-align:center;">Giriş Yap →</a>
  <?php endif;?>

  <p style="text-align:center;margin-top:18px;font-size:.82rem;"><a href="/" style="color:var(--muted);">← Ana Sayfaya Dön</a></p>
</div></div>
<script>
function tog(id,btn){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.innerHTML=i.type==='password'?'<i class="bi bi-eye"></i>':'<i class="bi bi-eye-slash"></i>';}
</script>
</body></html>
