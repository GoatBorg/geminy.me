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

$stmt2 = db()->prepare('SELECT * FROM users WHERE username=?');
$stmt2->execute([$loggedUser]);
$me = $stmt2->fetch();

// ── Mesaj gönder ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pm_text'])) {
    $text = mb_substr(trim($_POST['pm_text'] ?? ''), 0, 1000);
    if ($text !== '') {
        db()->prepare('INSERT INTO privacy_messages (from_user,to_user,text) VALUES (?,?,?)')
            ->execute([$loggedUser, $withUser, $text]);
    }
    redirect(SITE_URL . '/dm/' . $withUser);
}

// ── Okundu ───────────────────────────────────────────────────
db()->prepare('UPDATE privacy_messages SET is_read=1 WHERE to_user=? AND from_user=?')
    ->execute([$loggedUser, $withUser]);

// ── Mesajları çek ────────────────────────────────────────────
$msgs = db()->prepare("
    SELECT id, from_user, text, is_read, created_at
    FROM privacy_messages
    WHERE (from_user=? AND to_user=?) OR (from_user=? AND to_user=?)
    ORDER BY created_at ASC LIMIT 200
");
$msgs->execute([$loggedUser, $withUser, $withUser, $loggedUser]);
$messages = $msgs->fetchAll();

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
  <?php foreach ($grouped as $day => $dayMsgs): ?>
    <div class="dmc-date-sep">
      <?php $ts=strtotime($day); $today=date('Y-m-d'); $yest=date('Y-m-d',strtotime('-1 day'));
        if ($day===$today) echo 'Bugün'; elseif ($day===$yest) echo 'Dün'; else echo date('d F Y',$ts); ?>
    </div>
    <?php foreach ($dayMsgs as $m): $isMine = $m['from_user']===$loggedUser; ?>
    <div class="dmc-bubble-wrap <?= $isMine?'mine':'theirs' ?>">
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

  <?php if (empty($messages)): ?>
  <div class="dmc-empty">
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
  <form method="POST" class="dmc-form" id="pmForm">
    <div class="dmc-textarea-wrap">
      <textarea name="pm_text" id="pmText" placeholder="Mesaj yaz..." rows="1"
        oninput="autoResize(this)" onkeydown="sendOnEnter(event)" maxlength="1000"></textarea>
    </div>
    <button type="submit" class="dmc-send-btn"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<script>
const scroll = document.getElementById('chatScroll');
scroll.scrollTop = scroll.scrollHeight;
function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,120)+'px'; }
function sendOnEnter(e){
  if(e.key==='Enter'&&!e.shiftKey){
    e.preventDefault();
    if(document.getElementById('pmText').value.trim()) document.getElementById('pmForm').submit();
  }
}
</script>
</body>
</html>
