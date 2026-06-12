<?php
    
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
   
// =============================================
// geminy.me — Public API: Kullanıcı Soruları
// GET /app/api/tweet.php?u=kullaniciadi
// GET /app/api/tweet.php?u=kullaniciadi&page=2
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
        'usage' => '/app/api/tweet.php?u=kullaniciadi',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Kullanıcı var mı?
try {
    $uChk = db()->prepare('SELECT username FROM users WHERE username=?');
    $uChk->execute([$username]);
    if (!$uChk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Kullanıcı bulunamadı: ' . $username], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Veritabanı hatası.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sayfalama
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Toplam
$total = (int)db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?')
    ->execute([$username]) ? db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?') : 0;
$tStmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?');
$tStmt->execute([$username]);
$total = (int)$tStmt->fetchColumn();

// Sorular + yanıtlar
$stmt = db()->prepare(
    'SELECT m.id, m.text AS question, m.created_at, m.likes,
            r.text AS reply, r.created_at AS replied_at
     FROM messages m
     LEFT JOIN replies r ON r.message_id = m.id
     WHERE m.to_user = ?
     ORDER BY m.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$username, $perPage, $offset]);
$rows = $stmt->fetchAll();

// Grupla: her soru tek obje
$items = [];
foreach ($rows as $row) {
    $id = $row['id'];
    if (!isset($items[$id])) {
        $items[$id] = [
            'id'         => $id,
            'question'   => $row['question'],
            'likes'      => (int)$row['likes'],
            'asked_at'   => $row['created_at'],
            'reply'      => null,
            'replied_at' => null,
            'answered'   => false,
        ];
    }
    if ($row['reply']) {
        $items[$id]['reply']      = $row['reply'];
        $items[$id]['replied_at'] = $row['replied_at'];
        $items[$id]['answered']   = true;
    }
}

echo json_encode([
    'ok'       => true,
    'username' => $username,
    'page'     => $page,
    'per_page' => $perPage,
    'total'    => $total,
    'pages'    => ceil($total / $perPage),
    'items'    => array_values($items),
    'api'      => ['version' => '8.0', 'platform' => 'geminy.me'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
