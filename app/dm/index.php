<?php
require_once __DIR__ . '/../../app/config.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;
if (!$loggedUser) redirect(SITE_URL . '/');

$withUser = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_GET['with'] ?? '')));
if (!$withUser || $withUser === $loggedUser) redirect(SITE_URL . '/messages');

$stmt = db()->prepare('SELECT * FROM users WHERE username=?');
$stmt->execute([$withUser]);
$other = $stmt->fetch();
if (!$other) redirect(SITE_URL . '/messages');

// ── AJAX: Mesajları Çek ────────────────────────────────────────
if (isset($_GET['ajax_fetch'])) {
    header('Content-Type: application/json');
    $lastId = (int)($_GET['last_id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT id, from_user, text, is_read, created_at FROM privacy_messages 
         WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?))
         AND id > ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([$loggedUser, $withUser, $withUser, $loggedUser, $lastId]);
    $newMsgs = $stmt->fetchAll();
    
    // Okundu yap
    db()->prepare('UPDATE privacy_messages SET is_read=1 WHERE to_user=? AND from_user=? AND is_read=0')
        ->execute([$loggedUser, $withUser]);
        
    echo json_encode($newMsgs);
    exit;
}

// ── AJAX: Mesaj Gönder ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    $text = mb_substr(trim($_POST['text'] ?? ''), 0, 1000);
    if ($text !== '') {
        db()->prepare('INSERT INTO privacy_messages (from_user,to_user,text) VALUES (?,?,?)')
            ->execute([$loggedUser, $withUser, $text]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ── Okundu ───────────────────────────────────────────────────
db()->prepare('UPDATE privacy_messages SET is_read=1 WHERE to_user=? AND from_user=?')
    ->execute([$loggedUser, $withUser]);

// ── Mesajları çek (İlk yükleme) ───────────────────────────────
$msgs = db()->prepare("
    SELECT id, from_user, text, is_read, created_at
    FROM privacy_messages
    WHERE (from_user=? AND to_user=?) OR (from_user=? AND to_user=?)
    ORDER BY created_at ASC LIMIT 200
");
$msgs->execute([$loggedUser, $withUser, $withUser, $loggedUser]);
$messages = $msgs->fetchAll();

$lastMsgId = 0;
if (!empty($messages)) {
    $lastMsgId = end($messages)['id'];
}

$grouped = [];
foreach ($messages as $m) {
    $day = date('Y-m-d', strtotime($m['created_at']));
    $grouped[$day][] = $m;
}

function msgTime(string $dt): string {
    return date(time()-strtotime($dt)<86400?'H:i':'d.m H:i', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($other['display_name'] ?? $withUser) ?> | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/dm.css">
</head>
<body class="dm-chat-body">

<div class="dmc-header">
  <a href="/messages" class="dmc-back"><i class="bi bi-arrow-left"></i></a>
  <a href="/profile/<?= e($withUser) ?>" class="dmc-user">
    <div class="dmc-avatar">
      <?php if (!empty($other['avatar_url'])): ?><img src="<?= e($other['avatar_url']) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($withUser,0,1)); endif; ?>
    </div>
    <div class="dmc-info">
      <div class="dmc-name"><?= e($other['display_name'] ?? $withUser) ?></div>
      <div class="dmc-handle">@<?= e($withUser) ?></div>
    </div>
  </a>
  <a href="/profile/<?= e($withUser) ?>" class="dmc-action"><i class="bi bi-person"></i></a>
</div>

<div class="dmc-scroll" id="chatScroll">
  <div id="msgContainer">
    <?php foreach ($grouped as $day => $dayMsgs): ?>
      <div class="dmc-date-sep">
        <?php $ts=strtotime($day); $today=date('Y-m-d'); $yest=date('Y-m-d',strtotime('-1 day'));
          if ($day===$today) echo 'Bugün'; elseif ($day===$yest) echo 'Dün'; else echo date('d F Y',$ts); ?>
      </div>
      <?php foreach ($dayMsgs as $m): $isMine = $m['from_user']===$loggedUser; ?>
      <div class="dmc-bubble-wrap <?= $isMine?'mine':'theirs' ?>" data-id="<?= $m['id'] ?>">
        <?php if (!$isMine): ?>
        <div class="dmc-bav">
          <?php if (!empty($other['avatar_url'])): ?><img src="<?= e($other['avatar_url']) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($withUser,0,1)); endif; ?>
        </div>
        <?php endif; ?>
        <div class="dmc-bubble">
          <?= nl2br(e($m['text'])) ?>
          <span class="dmc-time">
            <?= msgTime($m['created_at']) ?>
            <?php if ($isMine): ?>
              <i class="bi bi-check<?= $m['is_read']?'2-all':'2' ?>" style="color:<?= $m['is_read']?'var(--cyan)':'rgba(255,255,255,.4)' ?>;"></i>
            <?php endif; ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php if (empty($messages)): ?>
  <div class="dmc-empty" id="emptyHint">
    <div class="dmc-empty-ava">
      <?php if (!empty($other['avatar_url'])): ?><img src="<?= e($other['avatar_url']) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($withUser,0,1)); endif; ?>
    </div>
    <div class="dmc-empty-name"><?= e($other['display_name'] ?? $withUser) ?></div>
    <div class="dmc-empty-sub">@<?= e($withUser) ?> — geminy.me</div>
    <p>Henüz mesaj yok. İlk mesajı sen gönder! 👋</p>
  </div>
  <?php endif; ?>
</div>

<div class="dmc-input-bar">
  <form id="ajaxPmForm" class="dmc-form">
    <div class="dmc-textarea-wrap">
      <textarea name="text" id="pmText" placeholder="Mesaj yaz..." rows="1"
        oninput="autoResize(this)" onkeydown="sendOnEnter(event)" maxlength="1000"></textarea>
    </div>
    <button type="submit" class="dmc-send-btn"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<script>
const scroll = document.getElementById('chatScroll');
const msgContainer = document.getElementById('msgContainer');
const pmText = document.getElementById('pmText');
const ajaxPmForm = document.getElementById('ajaxPmForm');
const emptyHint = document.getElementById('emptyHint');
const loggedUser = '<?= $loggedUser ?>';
const otherAvatar = '<?= $other['avatar_url'] ?? '' ?>';
const otherInit = '<?= mb_strtoupper(mb_substr($withUser,0,1)) ?>';

let lastId = <?= $lastMsgId ?>;

scroll.scrollTop = scroll.scrollHeight;

function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,120)+'px'; }

function sendOnEnter(e){
  if(e.key==='Enter'&&!e.shiftKey){
    e.preventDefault();
    sendMessage();
  }
}

ajaxPmForm.onsubmit = (e) => {
    e.preventDefault();
    sendMessage();
};

async function sendMessage() {
    const text = pmText.value.trim();
    if (!text) return;
    
    pmText.value = '';
    pmText.style.height = 'auto';
    
    const fd = new FormData();
    fd.append('ajax_send', '1');
    fd.append('text', text);
    
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            fetchMessages();
        }
    } catch (e) {}
}

async function fetchMessages() {
    try {
        const res = await fetch(window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'ajax_fetch=1&last_id=' + lastId);
        const data = await res.json();
        
        if (data.length > 0) {
            if (emptyHint) emptyHint.style.display = 'none';
            
            data.forEach(m => {
                if (document.querySelector(`[data-id="${m.id}"]`)) return;
                
                const isMine = m.from_user === loggedUser;
                const div = document.createElement('div');
                div.className = 'dmc-bubble-wrap ' + (isMine ? 'mine' : 'theirs');
                div.setAttribute('data-id', m.id);
                
                let html = '';
                if (!isMine) {
                    html += `<div class="dmc-bav">${otherAvatar ? `<img src="${otherAvatar}">` : otherInit}</div>`;
                }
                
                const time = new Date(m.created_at);
                const timeStr = time.getHours().toString().padStart(2,'0') + ':' + time.getMinutes().toString().padStart(2,'0');
                
                html += `
                    <div class="dmc-bubble">
                        ${m.text.replace(/\n/g, '<br>')}
                        <span class="dmc-time">
                            ${timeStr}
                            ${isMine ? `<i class="bi bi-check${m.is_read=='1'?'2-all':'2'}" style="color:${m.is_read=='1'?'var(--cyan)':'rgba(255,255,255,.4)'};"></i>` : ''}
                        </span>
                    </div>
                `;
                div.innerHTML = html;
                msgContainer.appendChild(div);
                lastId = m.id;
            });
            scroll.scrollTop = scroll.scrollHeight;
        }
    } catch (e) {}
}

setInterval(fetchMessages, 3000);
</script>
</body>
</html>
