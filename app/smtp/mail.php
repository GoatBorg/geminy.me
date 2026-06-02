<?php
// =============================================
// geminy.me v5.5 — SMTP Mail Helper
// Kodlar veritabanında HASH olarak saklanır
// =============================================
require_once __DIR__ . '/../../app/config.php';

// ── Ana mail gönderici ────────────────────────────────────────
function geminyMail(string $to, string $subject, string $body): bool {
    $host  = SMTP_HOST;
    $ports = [SMTP_PORT, SMTP_PORT_ALT];
    $user  = SMTP_USER;
    $pass  = SMTP_PASS;
    $from  = SMTP_FROM;
    $name  = SMTP_NAME;

    $socket = false;
    foreach ($ports as $port) {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
        if ($socket) break;
    }
    if (!$socket) {
        error_log("[geminy SMTP] Bağlantı başarısız: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $buf = '';
        while ($line = fgets($socket, 512)) {
            $buf .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $buf;
    };
    $cmd = function (string $c) use ($socket, $read): string {
        fwrite($socket, $c);
        return $read();
    };
    $ok = fn(string $r, string $exp = '') =>
        $r !== '' && $r[0] !== '5' && (!$exp || str_starts_with($r, $exp));

    // Banner
    if (!$ok($read(), '220'))       { fclose($socket); error_log('[geminy SMTP] Banner fail'); return false; }
    // EHLO
    if (!$ok($cmd("EHLO geminy.me\r\n"))) { fclose($socket); return false; }
    // STARTTLS
    $tls = $cmd("STARTTLS\r\n");
    if ($ok($tls, '220')) {
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket); error_log('[geminy SMTP] TLS el sıkışması başarısız'); return false;
        }
        if (!$ok($cmd("EHLO geminy.me\r\n"))) { fclose($socket); return false; }
    }
    // AUTH
    if (!$ok($cmd("AUTH LOGIN\r\n"), '334'))           { fclose($socket); error_log('[geminy SMTP] AUTH fail'); return false; }
    if (!$ok($cmd(base64_encode($user) . "\r\n"), '334')) { fclose($socket); return false; }
    if (!$ok($cmd(base64_encode($pass) . "\r\n"), '235')) { fclose($socket); error_log('[geminy SMTP] Şifre reddedildi'); return false; }
    // MAIL / RCPT
    if (!$ok($cmd("MAIL FROM:<{$from}>\r\n"), '250')) { fclose($socket); return false; }
    if (!$ok($cmd("RCPT TO:<{$to}>\r\n"),   '250')) { fclose($socket); return false; }
    // DATA
    if (!$ok($cmd("DATA\r\n"), '354')) { fclose($socket); return false; }

    $body64 = chunk_split(base64_encode($body));
    $msgId  = '<' . uniqid() . '@geminy.me>';
    $raw    = "Date: " . date('r') . "\r\n"
            . "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n"
            . "To: {$to}\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "Message-ID: {$msgId}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "\r\n"
            . $body64
            . "\r\n.\r\n";

    if (!$ok($cmd($raw), '250')) { fclose($socket); error_log('[geminy SMTP] DATA kabul edilmedi'); return false; }
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

// ── Güvenli 2FA kodu oluştur & kaydet ────────────────────────
// Düz kod ASLA veritabanına yazılmaz, sadece hash saklanır
function generate2FACode(string $username, string $purpose = 'login'): string {
    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash    = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 dk

    // Eski kodları geçersiz yap
    db()->prepare(
        "UPDATE two_factor_codes SET used_at=NOW()
         WHERE username=? AND purpose=? AND used_at IS NULL"
    )->execute([$username, $purpose]);

    db()->prepare(
        "INSERT INTO two_factor_codes (username, code_hash, purpose, expires_at)
         VALUES (?, ?, ?, ?)"
    )->execute([$username, $hash, $purpose, $expires]);

    return $code; // sadece mail'e gönderilir, DB'ye yazılmaz
}

// ── 2FA kodunu doğrula ────────────────────────────────────────
function verify2FACode(string $username, string $inputCode, string $purpose = 'login'): bool {
    $rows = db()->prepare(
        "SELECT id, code_hash FROM two_factor_codes
         WHERE username=? AND purpose=? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 5"
    );
    $rows->execute([$username, $purpose]);

    foreach ($rows->fetchAll() as $row) {
        if (password_verify($inputCode, $row['code_hash'])) {
            // Kullanıldı olarak işaretle
            db()->prepare("UPDATE two_factor_codes SET used_at=NOW() WHERE id=?")
                ->execute([$row['id']]);
            return true;
        }
    }
    return false;
}

// ── Güvenli reset token oluştur & kaydet ─────────────────────
// Token düz hex, DB'de SHA-256 hash'i saklanır
function generateResetToken(string $email): string {
    $token     = bin2hex(random_bytes(32));           // 64 hex char
    $tokenHash = hash('sha256', $token);              // DB'ye bu gider
    $expires   = date('Y-m-d H:i:s', time() + 900);  // 15 dk

    // Eski tokenları geçersiz yap
    db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE email=? AND used_at IS NULL")
        ->execute([$email]);

    db()->prepare(
        "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
    )->execute([$email, $tokenHash, $expires]);

    return $token; // sadece URL'e yazılır
}

// ── Reset token doğrula ───────────────────────────────────────
function verifyResetToken(string $rawToken): ?array {
    $tokenHash = hash('sha256', $rawToken);
    $row = db()->prepare(
        "SELECT * FROM password_resets
         WHERE token=? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $row->execute([$tokenHash]);
    return $row->fetch() ?: null;
}

function useResetToken(string $rawToken): void {
    $tokenHash = hash('sha256', $rawToken);
    db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE token=?")
        ->execute([$tokenHash]);
}

// ── Mail şablonları ───────────────────────────────────────────
function mailPasswordReset(string $to, string $name, string $resetUrl): bool {
    $subject = 'Şifre Sıfırlama — geminy.me';
    $eName = htmlspecialchars($name, ENT_QUOTES);
    $eUrl  = htmlspecialchars($resetUrl, ENT_QUOTES);
    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="background:#0A0A0F;font-family:Arial,sans-serif;color:#EEF0FF;margin:0;padding:0;">
<div style="max-width:480px;margin:40px auto;padding:32px;background:rgba(255,255,255,.05);border-radius:20px;border:1px solid rgba(233,30,140,.2);">
  <h1 style="font-size:1.6rem;background:linear-gradient(135deg,#00D4FF,#E91E8C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 8px;">geminy.me</h1>
  <p style="color:#B0B3D6;margin:0 0 24px;">Şifre Sıfırlama Talebi</p>
  <p>Merhaba <strong>$eName</strong>,</p>
  <p style="color:#B0B3D6;margin:10px 0 22px;">Şifreni sıfırlamak için aşağıdaki butona bas. Link <strong>15 dakika</strong> geçerlidir.</p>
  <div style="text-align:center;margin:28px 0;">
    <a href="$eUrl" style="display:inline-block;padding:15px 32px;background:linear-gradient(135deg,#E91E8C,#7C3AED);color:#fff;text-decoration:none;border-radius:14px;font-weight:700;font-size:1rem;">Şifremi Sıfırla →</a>
  </div>
  <p style="color:#6B6F9A;font-size:.8rem;">Bu isteği sen yapmadıysan bu e-postayı görmezden gel.</p>
  <p style="color:#6B6F9A;font-size:.75rem;word-break:break-all;">Link: $eUrl</p>
  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0;">
  <p style="color:#6B6F9A;font-size:.72rem;margin:0;text-align:center;">2026 · geminy.me · Dijital mahremiyet bir lüks değil, haktır.</p>
</div></body></html>
HTML;
    return geminyMail($to, $subject, $body);
}

function mailTwoFactorCode(string $to, string $name, string $code): bool {
    $subject = 'Giriş Doğrulama Kodu — geminy.me';
    $eName = htmlspecialchars($name, ENT_QUOTES);
    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="background:#0A0A0F;font-family:Arial,sans-serif;color:#EEF0FF;margin:0;padding:0;">
<div style="max-width:480px;margin:40px auto;padding:32px;background:rgba(255,255,255,.05);border-radius:20px;border:1px solid rgba(0,212,255,.2);">
  <h1 style="font-size:1.6rem;background:linear-gradient(135deg,#00D4FF,#E91E8C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 8px;">geminy.me</h1>
  <p style="color:#B0B3D6;margin:0 0 24px;">İki Faktörlü Doğrulama</p>
  <p>Merhaba <strong>$eName</strong>,</p>
  <p style="color:#B0B3D6;margin:10px 0;">Giriş doğrulama kodun:</p>
  <div style="font-size:2.8rem;font-weight:900;letter-spacing:14px;text-align:center;margin:24px 0;padding:22px;background:rgba(0,212,255,.08);border-radius:16px;border:1px solid rgba(0,212,255,.25);color:#00D4FF;font-family:monospace;">$code</div>
  <p style="color:#6B6F9A;font-size:.8rem;">Bu kod <strong>10 dakika</strong> geçerlidir. Kimseyle paylaşma.</p>
  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0;">
  <p style="color:#6B6F9A;font-size:.72rem;margin:0;text-align:center;">2026 · geminy.me</p>
</div></body></html>
HTML;
    return geminyMail($to, $subject, $body);
}
