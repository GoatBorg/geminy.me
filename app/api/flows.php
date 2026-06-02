<?php
/**
 * flows.php — geminy.me Flow Engine
 *
 * Kullanım:
 *   flows.php?users=5               → 5 random kullanıcı oluşturur
 *   flows.php?message=random        → tüm kullanıcılara random mesaj atar
 *   flows.php?users=5&message=random → ikisini birden yapar
 */

header('Content-Type: application/json; charset=utf-8');

define('USERS_FILE', __DIR__ . '../../data/users.json');
define('MSGS_FILE',  __DIR__ . '../../data/all_global_messages.json');
define('DATA_DIR',   __DIR__ . '/data');

// ─── JSON yardımcıları ────────────────────────────────────────────────────────

function readJson(string $path): array {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?? [];
}

function writeJson(string $path, mixed $data): void {
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// ─── Havuzlar ─────────────────────────────────────────────────────────────────

$PREFIXES = ['kara','yıldız','mavi','gece','güneş','ay','ateş','rüzgar','bulut','deniz',
             'taş','çiçek','yağmur','kar','sis','şimşek','duman','toprak','demir','altın'];
$SUFFIXES = ['0','1','7','21','42','55','99','_x','_tr','_v2','05','07','34','pro','_real',
             '007','_g','_k','_s','33','_m'];
$BIOS     = [
    'Hayat güzeldir 🌹', 'Boşver kafana takma 🎯', 'Vice City ruh halinde 🚗',
    'Müzik dinleyip kayboluyorum 🎧', 'Kod yazıyorum hayat yaşıyorum 💻',
    'Sessizliği seviyorum ✨', 'Anı yaşa kardeşim 🔥', 'Gece kuşuyum 🦉',
    'Kafam karışık ama kalbim temiz 💜', 'Yavaş yavaş her şey düzelir 🌙',
    'Merhaba dünya 👋', 'Geliyorum izleyin 🎬', 'Sadece ben varım burada 🫂',
    'Rüzgarım savuruyor beni 🌬️', 'Her sabah yeni başlangıç ☀️'
];
$MESSAGES = [
    'Naber kanki 🙌', 'Selam bebeğim 💜', 'Noluyorsun moruk 😎',
    'Nasılsın tatlım 🌹', 'Yeni gün nasıl başladı?', 'Uyandın mı hala 😂',
    'Hey! Burada mısın?', 'Selamlar efendim 👋', 'Merhaba şekerim ✨',
    'Oo seni gördüm 👀', 'Ne yapıyorsun bu saatte 🌙', 'Bugün ne yaptın anlat',
    'En son ne izledin?', 'Müzik önerisi var mı?', 'Hayat nasıl gidiyor?',
    'Planların ne bu hafta?', 'Hep böyle mi meşgulsün 😅',
    'Seni düşündüm, sonra bıraktım 😂', 'Umarım iyisindir 💙',
    'Seni özledim be kanki', 'Bir ara takılalım mı?', 'İyi günler ✨',
    'hey', 'selam!', 'iyimisin', 'naber?', '🌹', '🔥'
];

// ─── Kullanıcı üret ───────────────────────────────────────────────────────────

function createRandomUsers(int $count): array {
    global $PREFIXES, $SUFFIXES, $BIOS;

    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);

    $users   = readJson(USERS_FILE);
    $created = [];

    for ($i = 0; $i < $count; $i++) {
        $attempts = 0;
        do {
            $uname = $PREFIXES[array_rand($PREFIXES)] . $SUFFIXES[array_rand($SUFFIXES)];
        } while (isset($users[$uname]) && ++$attempts < 50);

        $bio = $BIOS[array_rand($BIOS)];
        $users[$uname] = ['bio' => $bio, 'date' => date('d.m.Y H:i'), 'auto' => true];

        $msgFile = DATA_DIR . "/msg_{$uname}.json";
        if (!file_exists($msgFile)) file_put_contents($msgFile, '[]');

        $created[] = ['username' => $uname, 'bio' => $bio];
    }

    writeJson(USERS_FILE, $users);
    return $created;
}

// ─── Mesaj gönder ─────────────────────────────────────────────────────────────

function sendRandomMessages(array $targets): array {
    global $MESSAGES;

    $users = readJson(USERS_FILE);
    if (empty($targets)) $targets = array_keys($users);

    $globalMsgs = readJson(MSGS_FILE);
    if (!isset($globalMsgs[0])) $globalMsgs = array_values($globalMsgs);

    $sent = [];

    foreach ($targets as $target) {
        $uname = is_array($target) ? $target['username'] : $target;
        if (!isset($users[$uname])) continue;

        $text  = $MESSAGES[array_rand($MESSAGES)];
        $time  = date('H:i');

        // Kullanıcı dosyası
        $msgFile  = DATA_DIR . "/msg_{$uname}.json";
        $userMsgs = file_exists($msgFile) ? (json_decode(file_get_contents($msgFile), true) ?? []) : [];
        $userMsgs[] = ['id' => uniqid(), 'text' => $text, 'time' => $time];
        file_put_contents($msgFile, json_encode($userMsgs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        // Global akış
        array_unshift($globalMsgs, ['text' => $text, 'to' => $uname, 'time' => $time]);

        $sent[] = ['to' => $uname, 'message' => $text, 'time' => $time];
    }

    writeJson(MSGS_FILE, array_slice($globalMsgs, 0, 100));
    return $sent;
}

// ─── Ana akış ─────────────────────────────────────────────────────────────────

$result = [
    'status'    => 'ok',
    'project'   => 'geminy.me',
    'timestamp' => date('d.m.Y H:i:s'),
    'actions'   => [],
];

$newUsers = [];

if (isset($_GET['users'])) {
    $count    = max(1, min(50, (int)$_GET['users']));
    $newUsers = createRandomUsers($count);

    $result['actions'][]  = 'create_users';
    $result['users'] = [
        'requested' => $count,
        'created'   => count($newUsers),
        'list'      => $newUsers,
    ];
}

if (isset($_GET['message']) && strtolower($_GET['message']) === 'random') {
    $sent = sendRandomMessages($newUsers);

    $result['actions'][]   = 'send_messages';
    $result['messages'] = [
        'sent_count' => count($sent),
        'list'       => $sent,
    ];
}

if (empty($result['actions'])) {
    $result['status'] = 'idle';
    $result['hint']   = 'Parametresiz istek. ?users=5 veya ?message=random dene.';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
