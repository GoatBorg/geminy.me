<?php
// =============================================
// geminy.me v6.0 — SMTP Mail Helper
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

// ── İstek meta bilgilerini topla ─────────────────────────────
function getRequestMeta(): array {
    $ip        = $_SERVER['HTTP_CF_CONNECTING_IP']
              ?? $_SERVER['HTTP_X_FORWARDED_FOR']
              ?? $_SERVER['REMOTE_ADDR']
              ?? 'Bilinmiyor';
    // Birden fazla IP varsa ilkini al
    $ip = trim(explode(',', $ip)[0]);

    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor';
    $time      = date('d.m.Y H:i:s T');
    $lang      = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $lang      = $lang ? substr($lang, 0, 5) : 'Bilinmiyor';

    // Basit cihaz/tarayıcı tespiti
    $device = 'Masaüstü';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
        $device = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Mobil';
    }
    $browser = 'Bilinmiyor';
    if (preg_match('/Edg\//i', $ua))         $browser = 'Microsoft Edge';
    elseif (preg_match('/OPR\//i', $ua))     $browser = 'Opera';
    elseif (preg_match('/Chrome\//i', $ua))  $browser = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua))  $browser = 'Safari';

    $os = 'Bilinmiyor';
    if (preg_match('/Windows NT/i', $ua))     $os = 'Windows';
    elseif (preg_match('/Macintosh/i', $ua))  $os = 'macOS';
    elseif (preg_match('/Android/i', $ua))    $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua))      $os = 'Linux';

    return compact('ip', 'time', 'device', 'browser', 'os', 'lang');
}

// ── Meta bilgi HTML bloğu oluştur ────────────────────────────
function buildMetaBlock(array $meta, string $username = ''): string {
    $rows = '';
    if ($username) {
        $rows .= '<tr><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">Kullanıcı Adı</td>'
               . '<td style="padding:7px 12px;font-weight:700;color:#EEF0FF;">@' . htmlspecialchars($username, ENT_QUOTES) . '</td></tr>';
    }
    $rows .= '<tr style="background:rgba(255,255,255,.03);"><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">IP Adresi</td>'
           . '<td style="padding:7px 12px;font-family:monospace;color:#00D4FF;">' . htmlspecialchars($meta['ip'], ENT_QUOTES) . '</td></tr>';
    $rows .= '<tr><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">Tarih / Saat</td>'
           . '<td style="padding:7px 12px;color:#EEF0FF;">' . htmlspecialchars($meta['time'], ENT_QUOTES) . '</td></tr>';
    $rows .= '<tr style="background:rgba(255,255,255,.03);"><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">Cihaz</td>'
           . '<td style="padding:7px 12px;color:#EEF0FF;">' . htmlspecialchars($meta['device'], ENT_QUOTES) . '</td></tr>';
    $rows .= '<tr><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">Tarayıcı</td>'
           . '<td style="padding:7px 12px;color:#EEF0FF;">' . htmlspecialchars($meta['browser'], ENT_QUOTES) . '</td></tr>';
    $rows .= '<tr style="background:rgba(255,255,255,.03);"><td style="padding:7px 12px;color:#8B8FA8;font-size:.78rem;white-space:nowrap;">İşletim Sistemi</td>'
           . '<td style="padding:7px 12px;color:#EEF0FF;">' . htmlspecialchars($meta['os'], ENT_QUOTES) . '</td></tr>';

    return <<<HTML
<div style="margin:20px 0;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.08);">
  <div style="background:rgba(255,255,255,.05);padding:9px 14px;font-size:.75rem;color:#6B6F9A;letter-spacing:.05em;text-transform:uppercase;">
    <i>🔍</i> Talep Bilgileri
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
    {$rows}
  </table>
</div>
HTML;
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
function mailPasswordReset(string $to, string $name, string $resetUrl, string $username = ''): bool {
    $subject = 'Şifre Sıfırlama — geminy.me';
    $eName   = htmlspecialchars($name, ENT_QUOTES);
    $eUrl    = htmlspecialchars($resetUrl, ENT_QUOTES);
    $meta    = getRequestMeta();
    $metaBlock = buildMetaBlock($meta, $username);

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="background:#0A0A0F;font-family:Arial,sans-serif;color:#EEF0FF;margin:0;padding:0;">
<div style="max-width:500px;margin:40px auto;padding:32px;background:rgba(255,255,255,.05);border-radius:20px;border:1px solid rgba(233,30,140,.2);">

  <!-- Logo & Başlık -->
  <div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-size:1.8rem;background:linear-gradient(135deg,#00D4FF,#E91E8C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 4px;">geminy.me</h1>
    <p style="color:#6B6F9A;font-size:.8rem;margin:0;letter-spacing:.08em;text-transform:uppercase;">Şifre Sıfırlama Talebi</p>
  </div>

  <!-- Selamlama -->
  <p style="margin:0 0 6px;">Merhaba <strong style="color:#EEF0FF;">$eName</strong>,</p>
  <p style="color:#B0B3D6;margin:0 0 22px;line-height:1.6;">Hesabın için bir şifre sıfırlama talebi alındı. Aşağıdaki butona tıklayarak şifreni sıfırlayabilirsin. Bu link <strong style="color:#E91E8C;">15 dakika</strong> geçerlidir.</p>

  <!-- Buton -->
  <div style="text-align:center;margin:28px 0;">
    <a href="$eUrl" style="display:inline-block;padding:15px 36px;background:linear-gradient(135deg,#E91E8C,#7C3AED);color:#fff;text-decoration:none;border-radius:14px;font-weight:700;font-size:1rem;letter-spacing:.02em;">Şifremi Sıfırla →</a>
  </div>

  <!-- Meta Bilgiler -->
  $metaBlock

  <!-- Uyarı -->
  <div style="background:rgba(233,30,140,.08);border:1px solid rgba(233,30,140,.2);border-radius:12px;padding:14px 16px;margin:16px 0;">
    <p style="margin:0;font-size:.8rem;color:#B0B3D6;line-height:1.6;">
      ⚠️ <strong>Bu isteği sen yapmadıysan</strong> bu e-postayı yoksay. Hesabın güvende, herhangi bir değişiklik yapılmadı.
    </p>
  </div>

  <!-- Link yedek -->
  <p style="color:#6B6F9A;font-size:.72rem;word-break:break-all;margin:12px 0 0;">Buton çalışmıyorsa: $eUrl</p>

  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0;">
  <p style="color:#6B6F9A;font-size:.72rem;margin:0;text-align:center;">2026 · geminy.me · Dijital mahremiyet bir lüks değil, haktır.</p>
  <p style="color:#3D3F5A;font-size:.65rem;margin:6px 0 0;text-align:center;">✦ Powered by Claude · Anthropic</p>
</div>
</body></html>
HTML;
    return geminyMail($to, $subject, $body);
}

function mailTwoFactorCode(string $to, string $name, string $code, string $username = ''): bool {
    $subject = 'Giriş Doğrulama Kodu — geminy.me';
    $eName   = htmlspecialchars($name, ENT_QUOTES);
    $meta    = getRequestMeta();
    $metaBlock = buildMetaBlock($meta, $username);

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="background:#0A0A0F;font-family:Arial,sans-serif;color:#EEF0FF;margin:0;padding:0;">
<div style="max-width:500px;margin:40px auto;padding:32px;background:rgba(255,255,255,.05);border-radius:20px;border:1px solid rgba(0,212,255,.2);">

  <!-- Logo & Başlık -->
  <div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-size:1.8rem;background:linear-gradient(135deg,#00D4FF,#E91E8C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 4px;">geminy.me</h1>
    <p style="color:#6B6F9A;font-size:.8rem;margin:0;letter-spacing:.08em;text-transform:uppercase;">İki Faktörlü Doğrulama</p>
  </div>

  <!-- Selamlama -->
  <p style="margin:0 0 6px;">Merhaba <strong style="color:#EEF0FF;">$eName</strong>,</p>
  <p style="color:#B0B3D6;margin:0 0 20px;line-height:1.6;">Hesabına giriş yapılmak isteniyor. Aşağıdaki tek kullanımlık kodu gir:</p>

  <!-- Kod Kutusu -->
  <div style="font-size:2.8rem;font-weight:900;letter-spacing:14px;text-align:center;margin:24px 0;padding:22px;background:rgba(0,212,255,.08);border-radius:16px;border:1px solid rgba(0,212,255,.25);color:#00D4FF;font-family:monospace;">$code</div>

  <p style="color:#B0B3D6;font-size:.82rem;text-align:center;margin:0 0 20px;">Bu kod <strong style="color:#EEF0FF;">10 dakika</strong> geçerlidir. Kimseyle paylaşma.</p>

  <!-- Meta Bilgiler -->
  $metaBlock

  <!-- Uyarı -->
  <div style="background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.15);border-radius:12px;padding:14px 16px;margin:16px 0;">
    <p style="margin:0;font-size:.8rem;color:#B0B3D6;line-height:1.6;">
      🛡️ <strong>Bu girişi sen yapmadıysan</strong> bu kodu kimseyle paylaşma ve hemen şifreni değiştir.
    </p>
  </div>

  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0;">
  <p style="color:#6B6F9A;font-size:.72rem;margin:0;text-align:center;">2026 · geminy.me</p>
  <p style="color:#3D3F5A;font-size:.65rem;margin:6px 0 0;text-align:center;">✦ Powered by Claude · Anthropic</p>
</div>
</body></html>
HTML;
    return geminyMail($to, $subject, $body);
}

// ── Hesap Silme Doğrulama E-postası ──────────────────────────
function mailDeleteConfirm(string $to, string $name, string $code, string $username = ''): bool {
    $subject = 'Hesap Silme Doğrulama — geminy.me';
    $eName   = htmlspecialchars($name, ENT_QUOTES);
    $meta    = getRequestMeta();
    $metaBlock = buildMetaBlock($meta, $username);

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="background:#0A0A0F;font-family:Arial,sans-serif;color:#EEF0FF;margin:0;padding:0;">
<div style="max-width:500px;margin:40px auto;padding:32px;background:rgba(255,255,255,.05);border-radius:20px;border:1px solid rgba(239,68,68,.25);">

  <div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-size:1.8rem;background:linear-gradient(135deg,#00D4FF,#E91E8C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 4px;">geminy.me</h1>
    <p style="color:#6B6F9A;font-size:.8rem;margin:0;letter-spacing:.08em;text-transform:uppercase;">Hesap Silme Talebi</p>
  </div>

  <p style="margin:0 0 6px;">Merhaba <strong style="color:#EEF0FF;">$eName</strong>,</p>
  <p style="color:#B0B3D6;margin:0 0 20px;line-height:1.6;">Hesabını silmek için bir talep alındı. Devam etmek istiyorsan aşağıdaki kodu gir. Bu kod <strong style="color:#EF4444;">10 dakika</strong> geçerlidir.</p>

  <div style="font-size:2.8rem;font-weight:900;letter-spacing:14px;text-align:center;margin:24px 0;padding:22px;background:rgba(239,68,68,.08);border-radius:16px;border:1px solid rgba(239,68,68,.25);color:#EF4444;font-family:monospace;">$code</div>

  <p style="color:#B0B3D6;font-size:.82rem;text-align:center;margin:0 0 20px;">Kimseyle paylaşma. Hesabın ve tüm verilerin kalıcı olarak silinir.</p>

  $metaBlock

  <div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.15);border-radius:12px;padding:14px 16px;margin:16px 0;">
    <p style="margin:0;font-size:.8rem;color:#B0B3D6;line-height:1.6;">
      🛡️ <strong>Bu isteği sen yapmadıysan</strong> bu e-postayı yoksay. Hesabın güvende.
    </p>
  </div>

  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0;">
  <p style="color:#6B6F9A;font-size:.72rem;margin:0;text-align:center;">2026 · geminy.me · Dijital mahremiyet bir lüks değil, haktır.</p>
  <p style="color:#3D3F5A;font-size:.65rem;margin:6px 0 0;text-align:center;">✦ Powered by Claude · Anthropic</p>
</div>
</body></html>
HTML;
    return geminyMail($to, $subject, $body);
}
