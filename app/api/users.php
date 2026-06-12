<?php
    
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
    
// =============================================
// geminy.me — Public API: Kullanıcı Profili
// GET /app/api/users.php?u=kullaniciadi
// =============================================
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Powered-By: geminy.me');

// ?u=username VEYA eski ?=username formatını destekle
$username = preg_replace('/[^a-z0-9_]/', '', strtolower(trim(
    $_GET['u'] ?? array_values($_GET)[0] ?? ''
)));

if (!$username) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Kullanıcı adı belirtilmedi.',
        'usage' => '/app/api/users.php?u=kullaniciadi',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT id, username, display_name, bio, avatar_url,
                instagram, tiktok, twitter, pinterest, website,
                created_at
         FROM users WHERE username = ?'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Veritabanı hatası.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Kullanıcı bulunamadı: ' . $username], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// İstatistikler
$qStmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?');
$qStmt->execute([$username]); $questions = (int)$qStmt->fetchColumn();

$rStmt = db()->prepare('SELECT COUNT(*) FROM replies r JOIN messages m ON m.id=r.message_id WHERE m.to_user=?');
$rStmt->execute([$username]); $replies = (int)$rStmt->fetchColumn();

$lStmt = db()->prepare('SELECT COUNT(*) FROM message_likes ml JOIN messages m ON m.id=ml.message_id WHERE m.to_user=?');
$lStmt->execute([$username]); $likes = (int)$lStmt->fetchColumn();

// Seviye (config'deki fonksiyonları kullan)
$score = function_exists('calcLevelScore') ? calcLevelScore($questions, $replies, $likes) : ($likes * 2);
$level = function_exists('getUserLevel') ? getUserLevel($score) : [];

// Sosyal linkler (sadece dolu olanlar)
$socials = array_filter([
    'instagram' => $user['instagram'] ? 'https://instagram.com/' . $user['instagram'] : null,
    'tiktok'    => $user['tiktok']    ? 'https://tiktok.com/@' . $user['tiktok']     : null,
    'twitter'   => $user['twitter']   ? 'https://x.com/' . $user['twitter']          : null,
    'pinterest' => $user['pinterest'] ? 'https://pinterest.com/' . $user['pinterest']: null,
    'website'   => $user['website']   ?: null,
]);

echo json_encode([
    'ok'   => true,
    'user' => [
        'username'     => $user['username'],
        'display_name' => $user['display_name'] ?: $user['username'],
        'bio'          => $user['bio'],
        'avatar_url'   => $user['avatar_url'],
        'profile_url'  => SITE_URL . '/profile/' . $user['username'],
        'joined_at'    => $user['created_at'],
        'socials'      => $socials ?: null,
        'level'        => $level ? [
            'label' => $level['label'],
            'score' => $score,
            'color' => $level['color'],
        ] : null,
        'stats' => [
            'questions' => $questions,
            'replies'   => $replies,
            'likes'     => $likes,
        ],
    ],
    'api' => ['version' => '8.0', 'platform' => 'geminy.me'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
