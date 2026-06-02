<?php
require_once __DIR__ . '/../../app/config.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;
if (!$loggedUser) redirect(SITE_URL . '/');

$stmt = db()->prepare('SELECT * FROM users WHERE username=?');
$stmt->execute([$loggedUser]);
$me = $stmt->fetch();

$notifs = [];

// Sorular
$q1 = db()->prepare('SELECT id,text,created_at FROM messages WHERE to_user=? ORDER BY created_at DESC LIMIT 30');
$q1->execute([$loggedUser]);
foreach ($q1->fetchAll() as $r) {
    $notifs[] = ['type'=>'question','actor'=>'Anonim','avatar'=>null,'text'=>'sana soru sordu: "'.mb_substr($r['text'],0,50).(mb_strlen($r['text'])>50?'…':'').'"','time'=>$r['created_at'],'link'=>SITE_URL.'/profile/'.$loggedUser,'unread'=>(strtotime($r['created_at'])>time()-3600*6),'badge'=>'comment'];
}
// Beğeniler
$q2 = db()->prepare('SELECT ml.created_at,m.text FROM message_likes ml JOIN messages m ON m.id=ml.message_id WHERE m.to_user=? ORDER BY ml.created_at DESC LIMIT 20');
$q2->execute([$loggedUser]);
foreach ($q2->fetchAll() as $r) {
    $notifs[] = ['type'=>'like','actor'=>'Biri','avatar'=>null,'text'=>'soruyu beğendi: "'.mb_substr($r['text'],0,40).'…"','time'=>$r['created_at'],'link'=>SITE_URL.'/profile/'.$loggedUser,'unread'=>false,'badge'=>'like'];
}
// Yanıtlar
$q3 = db()->prepare('SELECT r.created_at,r.text AS rtext FROM replies r JOIN messages m ON m.id=r.message_id WHERE m.to_user=? ORDER BY r.created_at DESC LIMIT 20');
$q3->execute([$loggedUser]);
foreach ($q3->fetchAll() as $r) {
    $notifs[] = ['type'=>'reply','actor'=>'Sen','avatar'=>$me['avatar_url']??null,'text'=>'soruyu yanıtladın: "'.mb_substr($r['rtext'],0,45).'…"','time'=>$r['created_at'],'link'=>SITE_URL.'/profile/'.$loggedUser,'unread'=>false,'badge'=>'reply'];
}
usort($notifs, fn($a,$b) => strtotime($b['time'])-strtotime($a['time']));

function timeAgo(string $dt): string {
    $d=time()-strtotime($dt);
    if ($d<60) return 'şimdi';
    if ($d<3600) return intval($d/60).'d';
    if ($d<86400) return intval($d/3600).'sa';
    return intval($d/86400).'g';
}
$badgeEmoji=['like'=>'♥','comment'=>'💬','reply'=>'↩','follow'=>'+'];
$badgeColor=['like'=>'#e91e8c','comment'=>'#4f46e5','reply'=>'#00d4ff','follow'=>'#22c55e'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Bildirimler | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/Notifications.css">
</head>
<body>
<div class="notif-page">
  <div class="notif-top">
    <div class="notif-top-row">
      <h1>Bildirimler</h1>
      <button class="notif-mark-btn" onclick="markAllRead()">Tümünü oku</button>
    </div>
    <div class="notif-tabs">
      <button class="notif-tab active" onclick="filterTab(this,'all')">Tümü</button>
      <button class="notif-tab" onclick="filterTab(this,'question')">💬 Sorular</button>
      <button class="notif-tab" onclick="filterTab(this,'like')">❤️ Beğeniler</button>
      <button class="notif-tab" onclick="filterTab(this,'reply')">↩️ Yanıtlar</button>
    </div>
  </div>

  <?php if (empty($notifs)): ?>
  <div class="notif-empty"><i class="bi bi-bell-slash"></i><p>Henüz bildirim yok ✨</p></div>
  <?php else:
    $today=[]; $older=[];
    foreach ($notifs as $n) { if (strtotime($n['time'])>strtotime('today')) $today[]=$n; else $older[]=$n; }
    function renderN(array $n, array $be, array $bc): void {
        $cls='notif-item'.($n['unread']?' unread':'');
        $bE=$be[$n['badge']]??'🔔'; $bC=$bc[$n['badge']]??'#7c3aed';
        echo '<a href="'.e($n['link']).'" class="'.$cls.'" data-type="'.e($n['type']).'">';
        echo '<div class="ni-avatar-wrap"><div class="ni-avatar">';
        if ($n['avatar']) echo '<img src="'.e($n['avatar']).'" alt="">';
        else echo mb_strtoupper(mb_substr($n['actor'],0,1));
        echo '</div><i class="ni-badge '.$n['badge'].'" style="background:'.$bC.'">'.$bE.'</i></div>';
        echo '<div class="ni-body"><div class="ni-text"><strong>'.e($n['actor']).'</strong> '.e($n['text']).'</div><div class="ni-time">'.timeAgo($n['time']).'</div></div>';
        echo '</a><div class="ni-divider"></div>';
    }
    if (!empty($today)): ?>
    <div class="notif-section">Bugün</div>
    <?php foreach ($today as $n) renderN($n,$badgeEmoji,$badgeColor); endif;
    if (!empty($older)): ?>
    <div class="notif-section">Daha Önce</div>
    <?php foreach ($older as $n) renderN($n,$badgeEmoji,$badgeColor); endif;
  endif; ?>
</div>

<nav class="bottom-nav">
  <a href="/"             class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"       class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"     class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications" class="nav-item active"><i class="bi bi-bell-fill"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item">
    <?php if (!empty($me['avatar_url'])): ?><img src="<?= e($me['avatar_url']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;"><?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
</nav>
<script>
function filterTab(btn,type){
  document.querySelectorAll('.notif-tab').forEach(t=>t.classList.remove('active')); btn.classList.add('active');
  document.querySelectorAll('.notif-item').forEach(item=>{
    const show=type==='all'||item.dataset.type===type;
    item.style.display=show?'flex':'none';
    const d=item.nextElementSibling; if(d&&d.classList.contains('ni-divider')) d.style.display=show?'block':'none';
  });
}
function markAllRead(){ document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread')); }
</script>
</body>
</html>
