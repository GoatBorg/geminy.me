<?php
// =============================================
// geminy.me v3.5 — Config & Helpers
// =============================================

define('DB_HOST',    '');
define('DB_NAME',    '');
define('DB_USER',    '');
define('DB_PASS',    '96fcd4c');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   'https://geminyask.unaux.com'); // 

// ── SMTP (MailerSend) ──────────────────────────────────────────
define('SMTP_HOST',     'smtp.mailersend.net');
define('SMTP_PORT',     587);
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_FROM',     'mlsender.net');
define('SMTP_NAME',     'geminy.me');

// Yedek SMTP (farklı port)
define('SMTP_PORT_ALT', 2525);

// ── PDO ───────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Yardımcılar ───────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void {
    header("Location: $url"); exit;
}

// Brute-force: son 15 dakikada 5'ten fazla deneme → kilitle
function checkBruteForce(): bool {
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    $cnt = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_hash=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $cnt->execute([$ipHash]);
    return (int)$cnt->fetchColumn() >= 5;
}
function logAttempt(?string $username = null): void {
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    db()->prepare("INSERT INTO login_attempts (ip_hash,username) VALUES (?,?)")
        ->execute([$ipHash, $username]);
}

// Güvenli session başlat
function secureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
