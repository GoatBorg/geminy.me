<?php
// =============================================
// geminy.me v3.5 — Config & Helpers
// =============================================

define('DB_HOST',    '');
define('DB_NAME',    '');
define('DB_USER',    '');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   ''); // trailing slash YOK

// ── SMTP (MailerSend) ──────────────────────────────────────────
define('SMTP_HOST',     '');
define('SMTP_PORT',     587);
define('SMTP_USER',     'mlsender.net');
define('SMTP_PASS',     '');
define('SMTP_FROM',     'mlsender.net');
define('SMTP_NAME',     'geminy.me');

// Yedek SMTP (farklı port)
define('SMTP_PORT_ALT', 25);

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

// ── Beğeni bazlı tik sistemi ─────────────────────────────────
// 1+ Yeşil, 10+ Sarı, 20+ Mavi, 30+ Mor
function getUserTick(int $totalLikes): array {
    if ($totalLikes >= 30) return ['color' => '#A855F7', 'label' => 'Mor Tık',  'icon' => '✦', 'class' => 'tick-purple'];
    if ($totalLikes >= 20) return ['color' => '#3B82F6', 'label' => 'Mavi Tık', 'icon' => '✦', 'class' => 'tick-blue'];
    if ($totalLikes >= 10) return ['color' => '#EAB308', 'label' => 'Sarı Tık', 'icon' => '✦', 'class' => 'tick-yellow'];
    if ($totalLikes >= 1)  return ['color' => '#22C55E', 'label' => 'Yeşil Tık','icon' => '✦', 'class' => 'tick-green'];
    return [];
}

// Tik HTML'i döndür (profil ve feed için)
function renderTick(int $totalLikes, string $size = 'md'): string {
    $tick = getUserTick($totalLikes);
    if (empty($tick)) return '';
    $px = $size === 'sm' ? '13px' : ($size === 'lg' ? '18px' : '15px');
    $title = htmlspecialchars($tick['label'] . ' — ' . $totalLikes . ' beğeni', ENT_QUOTES);
    return '<span class="geminy-tick ' . $tick['class'] . '" '
         . 'title="' . $title . '" '
         . 'style="color:' . $tick['color'] . ';font-size:' . $px . ';display:inline-block;vertical-align:middle;margin-left:3px;line-height:1;filter:drop-shadow(0 0 4px ' . $tick['color'] . '88);">'
         . '✦</span>';
}

// ── Seviye Sistemi v8 ─────────────────────────────────────────
function calcLevelScore(int $questions, int $replies, int $likes): int {
    return ($questions * 1) + ($replies * 3) + ($likes * 2);
}

function getUserLevel(int $score): array {
    $levels = [
        ['min' => 500, 'label' => 'Efsane',    'color' => '#FF6B35', 'class' => 'tick-legend',  'icon' => '⚡'],
        ['min' => 200, 'label' => 'Mor Tık',   'color' => '#A855F7', 'class' => 'tick-purple',  'icon' => '✦'],
        ['min' => 80,  'label' => 'Mavi Tık',  'color' => '#3B82F6', 'class' => 'tick-blue',    'icon' => '✦'],
        ['min' => 25,  'label' => 'Sarı Tık',  'color' => '#EAB308', 'class' => 'tick-yellow',  'icon' => '✦'],
        ['min' => 5,   'label' => 'Yeşil Tık', 'color' => '#22C55E', 'class' => 'tick-green',   'icon' => '✦'],
    ];
    foreach ($levels as $lv) {
        if ($score >= $lv['min']) return $lv;
    }
    return [];
}

function getNextLevelInfo(int $score): array {
    foreach ([5, 25, 80, 200, 500] as $t) {
        if ($score < $t) {
            $lv = getUserLevel($t);
            return [
                'next_label'   => $lv['label'],
                'next_color'   => $lv['color'],
                'next_min'     => $t,
                'remaining'    => $t - $score,
                'progress_pct' => round(($score / $t) * 100),
            ];
        }
    }
    return ['next_label' => null, 'remaining' => 0, 'progress_pct' => 100];
}

function getUserLevelByUsername(string $username): array {
    try {
        $q = db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?');
        $q->execute([$username]); $questions = (int)$q->fetchColumn();
        $r = db()->prepare('SELECT COUNT(*) FROM replies r JOIN messages m ON m.id=r.message_id WHERE m.to_user=?');
        $r->execute([$username]); $replies = (int)$r->fetchColumn();
        $l = db()->prepare('SELECT COUNT(*) FROM message_likes ml JOIN messages m ON m.id=ml.message_id WHERE m.to_user=?');
        $l->execute([$username]); $likes = (int)$l->fetchColumn();
        $score = calcLevelScore($questions, $replies, $likes);
        $level = getUserLevel($score);
        $level['score'] = $score;
        return $level;
    } catch (Exception $e) { return []; }
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
