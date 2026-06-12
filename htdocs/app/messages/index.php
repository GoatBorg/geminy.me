<?php
require_once __DIR__ . '/../../app/config.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;
if (!$loggedUser) redirect(SITE_URL . '/');

$stmt = db()->prepare('SELECT * FROM users WHERE username=?');
$stmt->execute([$loggedUser]);
$me = $stmt->fetch();

$convs = db()->prepare("
    SELECT u.username, u.display_name, u.avatar_url,
           pm.text AS last_msg, pm.created_at AS last_time, pm.from_user AS last_from,
           (SELECT COUNT(*) FROM privacy_messages WHERE to_user=? AND from_user=u.username AND is_read=0) AS unread_count
    FROM users u
    JOIN privacy_messages pm ON (
        (pm.from_user=? AND pm.to_user=u.username) OR (pm.to_user=? AND pm.from_user=u.username)
    )
    WHERE u.username != ?
      AND pm.id = (
        SELECT MAX(id) FROM privacy_messages pm2
        WHERE (pm2.from_user=? AND pm2.to_user=u.username) OR (pm2.to_user=? AND pm2.from_user=u.username)
      )
    ORDER BY last_time DESC LIMIT 50
");
$convs->execute([$loggedUser,$loggedUser,$loggedUser,$loggedUser,$loggedUser,$loggedUser]);
$conversations = $convs->fetchAll();

function dmTimeAgo(?string $dt): string {
    if (!$dt) return '';
    $d = time()-strtotime($dt);
    if ($d<60) return 'şimdi';
    if ($d<3600) return intval($d/60).'d';
    if ($d<86400) return intval($d/3600).'sa';
    if ($d<604800) return intval($d/86400).'g';
    return date('d.m',strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Mesajlar | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/messges.css">
</head>
<body>
<div class="dm-page">
  <div class="dm-top">
    <h1><?= e($me['display_name'] ?? $loggedUser) ?></h1>
    <a href="/search" class="dm-new-btn"><i class="bi bi-pencil-square"></i></a>
  </div>
  <div class="dm-search-area">
    <div class="dm-search">
      <i class="bi bi-search"></i>
      <input type="text" id="dmQ" placeholder="Ara..." oninput="dmSearch()">
    </div>
  </div>

  <?php if (empty($conversations)): ?>
  <div class="dm-empty">
    <i class="bi bi-chat-heart"></i>
    <h3>Henüz mesaj yok</h3>
    <p>Birinin profiline git ve <strong>Mesaj</strong> butonuna bas</p>
    <a href="/search" class="btn-neon" style="display:inline-block;width:auto;padding:12px 28px;font-size:.9rem;border-radius:50px;text-decoration:none;">Kişi Bul <i class="bi bi-search"></i></a>
  </div>
  <?php else: ?>
  <div id="dmList">
    <?php foreach ($conversations as $c):
      $unread=$c['unread_count']>0; $isMe=$c['last_from']===$loggedUser;
    ?>
    <a href="/dm/<?= e($c['username']) ?>" class="dm-row <?= $unread?'unread':'' ?>"
       data-name="<?= e(strtolower(($c['display_name']??'').' '.$c['username'])) ?>">
      <div class="dm-ava">
        <?php if (!empty($c['avatar_url'])): ?><img src="<?= e($c['avatar_url']) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($c['username'],0,1)); endif; ?>
        <?php if ($unread): ?><span class="dm-online-dot"></span><?php endif; ?>
      </div>
      <div class="dm-info">
        <div class="dm-name"><?= e($c['display_name']??$c['username']) ?> <span style="font-weight:400;color:var(--muted);font-size:.77rem;">@<?= e($c['username']) ?></span></div>
        <div class="dm-preview <?= $unread?'dm-preview-bold':'' ?>">
          <?php if ($c['last_msg']): ?>
            <?= $isMe?'<span style="color:var(--muted);">Sen: </span>':'' ?><?= e(mb_substr($c['last_msg'],0,50)) ?>
          <?php else: ?><span style="opacity:.4;font-style:italic;">Henüz mesaj yok</span><?php endif; ?>
        </div>
      </div>
      <div class="dm-meta">
        <div class="dm-time"><?= dmTimeAgo($c['last_time']) ?></div>
        <?php if ($unread): ?><div class="dm-unread-badge"><?= $c['unread_count']>9?'9+':$c['unread_count'] ?></div><?php else: ?><div class="dm-unread-dot"></div><?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<nav class="bottom-nav">
  <a href="/"             class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"       class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"     class="nav-item active"><i class="bi bi-send-fill"></i><span>Mesajlar</span></a>
  <a href="/notifications" class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item">
    <?php if (!empty($me['avatar_url'])): ?><img src="<?= e($me['avatar_url']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;"><?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
</nav>
<script>
function dmSearch(){
  const q=document.getElementById('dmQ').value.toLowerCase();
  document.querySelectorAll('.dm-row').forEach(r=>{ r.style.display=(r.dataset.name||'').includes(q)?'flex':'none'; });
}
</script>
</body>
</html>
