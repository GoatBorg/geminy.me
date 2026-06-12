<?php
    
ini_set('display_errors', 1);
error_reporting(E_ALL);
    
require_once __DIR__ . '/../../app/config.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;

$username = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_GET['u'] ?? '')));
if (!$username) redirect('/');

$stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([$username]);
$u = $stmt->fetch();
if (!$u) redirect('/');

$isOwn = $loggedUser === $username;

// ── Profil Görüntülenme Logu ──────────────────────────────────
$today = date('Y-m-d');
db()->prepare("INSERT INTO profile_views (user_id, view_date, view_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE view_count = view_count + 1")
    ->execute([$u['id'], $today]);

// ── Beğeni İşlemi ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_id'])) {
    header('Content-Type: application/json');
    $mid = trim($_POST['like_id']); // char(36) UUID — int cast yok!

    // Mesaj bu profile ait mi?
    $owns = db()->prepare('SELECT id FROM messages WHERE id=? AND to_user=?');
    $owns->execute([$mid, $username]);
    if (!$owns->fetchColumn()) {
        echo json_encode(['ok' => false, 'count' => 0]);
        exit;
    }

    // IP hash — DB'de ip_hash kolonu var
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '0.0.0.0';
    $ip     = trim(explode(',', $ip)[0]);
    $ipHash = hash('sha256', $ip); // ip_hash olarak sakla

    $exists = db()->prepare('SELECT id FROM message_likes WHERE message_id=? AND ip_hash=?');
    $exists->execute([$mid, $ipHash]);
    if (!$exists->fetchColumn()) {
        db()->prepare('INSERT INTO message_likes (message_id, ip_hash) VALUES (?,?)')->execute([$mid, $ipHash]);
    }

    // Beğeni sayısı COUNT ile (messages'ta likes kolonu yok)
    $cnt = db()->prepare('SELECT COUNT(*) FROM message_likes WHERE message_id=?');
    $cnt->execute([$mid]);
    echo json_encode(['ok' => true, 'count' => (int)$cnt->fetchColumn()]);
    exit;
}

// ── Takip İşlemi ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $loggedUser) {
    $meStmt = db()->prepare('SELECT id FROM users WHERE username=?');
    $meStmt->execute([$loggedUser]);
    $myId = $meStmt->fetchColumn();

    if ($_POST['action'] === 'follow' && !$isOwn) {
        db()->prepare('INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?,?)')
            ->execute([$myId, $u['id']]);
    } elseif ($_POST['action'] === 'unfollow' && !$isOwn) {
        db()->prepare('DELETE FROM follows WHERE follower_id=? AND following_id=?')
            ->execute([$myId, $u['id']]);
    }
    
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    redirect('/profile/' . $username);
}

// ── Takip Durumu ve Sayıları ──────────────────────────────────
$isFollowing = false;
if ($loggedUser && !$isOwn) {
    $meStmt = db()->prepare('SELECT id FROM users WHERE username=?');
    $meStmt->execute([$loggedUser]);
    $myId = $meStmt->fetchColumn();
    
    $fChk = db()->prepare('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?');
    $fChk->execute([$myId, $u['id']]);
    $isFollowing = (bool)$fChk->fetchColumn();
}

$f1 = db()->prepare('SELECT COUNT(*) FROM follows WHERE following_id=?'); $f1->execute([$u['id']]); $followerCount = (int)$f1->fetchColumn();
$f2 = db()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id=?'); $f2->execute([$u['id']]); $followingCount = (int)$f2->fetchColumn();

// ── Anonim soru gönder ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $text = mb_substr(trim($_POST['message']), 0, 600);
    if ($text !== '') {
        $id = uniqid('', true);
        db()->prepare('INSERT INTO messages (id,to_user,text) VALUES (?,?,?)')->execute([$id, $username, $text]);
    }
    redirect('/profile/' . $username);
}

// ── Yanıt ekle (sadece profil sahibi) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_text'], $_POST['parent_id']) && $isOwn) {
    $mid  = $_POST['parent_id'];
    $text = mb_substr(trim($_POST['reply_text']), 0, 300);
    $chk  = db()->prepare('SELECT id FROM messages WHERE id=? AND to_user=?');
    $chk->execute([$mid, $username]);
    if ($chk->fetch() && $text !== '') {
        db()->prepare('INSERT INTO replies (message_id,text) VALUES (?,?)')->execute([$mid, $text]);
    }
    redirect('/profile/' . $username);
}

// ── Mesajları çek (sadece hesap sahibi veya herkese açıksa) ──
$msgs = db()->prepare(
    'SELECT m.id, m.text, m.created_at,
            (SELECT COUNT(*) FROM message_likes l WHERE l.message_id=m.id) AS likes,
            r.id AS rid, r.text AS rtext, r.created_at AS rtime
     FROM messages m
     LEFT JOIN replies r ON r.message_id = m.id
     WHERE m.to_user = ?
     ORDER BY m.created_at DESC, r.created_at ASC'
);
$msgs->execute([$username]);
$rows = $msgs->fetchAll();

$messages = [];
foreach ($rows as $row) {
    $mid = $row['id'];
    if (!isset($messages[$mid])) {
        $messages[$mid] = ['id'=>$row['id'],'text'=>$row['text'],'created_at'=>$row['created_at'],'likes'=>$row['likes'],'replies'=>[]];
    }
    if ($row['rid']) $messages[$mid]['replies'][] = ['text'=>$row['rtext'],'time'=>$row['rtime']];
}

$questionCount = count($messages);
$totalLikes    = array_sum(array_column($messages, 'likes'));
$totalReplies  = array_sum(array_map(fn($m) => count($m['replies']), $messages));
$profileUrl    = SITE_URL . '/profile/' . $username;
$joinDate      = date('F Y', strtotime($u['created_at']));

// ── Seviye Sistemi ────────────────────────────────────────────
$levelScore    = calcLevelScore($questionCount, $totalReplies, $totalLikes);
$levelInfo     = getUserLevel($levelScore);
$nextLevelInfo = getNextLevelInfo($levelScore);

// Logged user avatar for nav
$myAvatar = '';
if ($loggedUser && !$isOwn) {
    $ns = db()->prepare('SELECT avatar_url FROM users WHERE username=?');
    $ns->execute([$loggedUser]);
    $myAvatar = $ns->fetchColumn() ?: '';
} elseif ($isOwn) {
    $myAvatar = $u['avatar_url'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>@<?= e($username) ?> | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<meta property="og:title" content="@<?= e($username) ?> | geminy.me">
<meta property="og:description" content="<?= e($u['bio'] ?? 'Anonim soru sor!') ?>">
<?php if (!empty($u['avatar_url'])): ?><meta property="og:image" content="<?= e($u['avatar_url']) ?>"><?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/profile.css">
</head>
<body class="profile-body">

<!-- Top Bar -->
<div class="prof-topbar">
  <a href="/" class="prof-back"><i class="bi bi-chevron-left"></i></a>
  <span class="prof-topbar-title">@<?= e($username) ?></span>
  <div class="prof-topbar-actions">
    <?php if ($isOwn): ?>
      <a href="/settings" class="prof-topbar-btn"><i class="bi bi-gear"></i></a>
    <?php else: ?>
      <button onclick="shareProfile()" class="prof-topbar-btn"><i class="bi bi-box-arrow-up"></i></button>
    <?php endif; ?>
  </div>
</div>

<!-- Profile Card -->
<div class="prof-card">
  <!-- Avatar + Stats (Instagram layout) -->
  <div class="prof-header-row">
    <div class="prof-avatar-ring">
      <?php if (!empty($u['avatar_url'])): ?>
        <img src="<?= e($u['avatar_url']) ?>" alt="">
      <?php else: ?>
        <span><?= mb_strtoupper(mb_substr($username, 0, 1)) ?></span>
      <?php endif; ?>
    </div>
	    <div class="prof-stats-row">
	      <div class="prof-stat"><span class="prof-stat-num"><?= $questionCount ?></span><span class="prof-stat-lbl">Soru</span></div>
	      <div class="prof-stat"><span class="prof-stat-num"><?= $totalReplies ?></span><span class="prof-stat-lbl">Yanıt</span></div>
	      <div class="prof-stat"><span class="prof-stat-num"><?= $totalLikes ?></span><span class="prof-stat-lbl">Beğeni</span></div>
	      <?php if ($isOwn): ?>
	      <div class="prof-stat prof-stat-private" title="Sadece sen görürsün 🔒"><span class="prof-stat-num"><?= $followerCount ?></span><span class="prof-stat-lbl">Takipçi</span></div>
	      <?php endif; ?>
	    </div>
  </div>

  <!-- Bio -->
  <div class="prof-bio-area">
    <div class="prof-name"><?= e($u['display_name'] ?? $username) ?><?= renderTick(0, 'lg', $levelScore) ?></div>
    <?php if (!empty($levelInfo)): ?>
    <div class="prof-level-badge" style="--lc:<?= $levelInfo['color'] ?>;">
      <?= $levelInfo['icon'] ?> <span><?= $levelInfo['label'] ?></span>
      <small><?= $levelScore ?> puan</small>
    </div>
    <?php endif; ?>
    <?php if (!empty($nextLevelInfo['next_label'])): ?>
    <div class="prof-level-progress">
      <div class="prof-level-bar"><div class="prof-level-fill" style="width:<?= $nextLevelInfo['progress_pct'] ?>%;background:<?= $nextLevelInfo['next_color'] ?>;"></div></div>
      <span><?= $nextLevelInfo['next_label'] ?>'e <?= $nextLevelInfo['remaining'] ?> puan kaldı</span>
    </div>
    <?php endif; ?>
    <?php if (!empty($u['bio'])): ?>
      <p class="prof-bio"><?= nl2br(e($u['bio'])) ?></p>
    <?php endif; ?>
    <div class="prof-join"><i class="bi bi-calendar3"></i> <?= $joinDate ?>'den beri geminy.me'de</div>
    <?php if (!empty($u['website'])): ?>
      <a href="<?= e($u['website']) ?>" target="_blank" rel="noopener" class="prof-website">
        <i class="bi bi-link-45deg"></i> <?= e(preg_replace('/^https?:\/\//', '', $u['website'])) ?>
      </a>
    <?php endif; ?>
  </div>

  <!-- Sosyal medya butonları -->
  <?php
    $socials = ['instagram'=>'instagram','tiktok'=>'tiktok','twitter'=>'twitter-x','pinterest'=>'pinterest'];
    $hasSocial = false;
    foreach ($socials as $k=>$_) { if (!empty($u[$k])) { $hasSocial = true; break; } }
  ?>
  <?php if ($hasSocial): ?>
  <div class="prof-socials">
    <?php foreach ($socials as $k => $icon): if (!empty($u[$k])): ?>
      <a href="https://<?= $k==='twitter'?'x.com':$k.'.com' ?>/<?= e($u[$k]) ?>" target="_blank" class="prof-social-btn" title="<?= $k ?>">
        <i class="bi bi-<?= $icon ?>"></i>
      </a>
    <?php endif; endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Şarkı Kartı (Instagram 2026 Android style) -->
  <?php if (!empty($u['song_title'])): ?>
  <div class="prof-song-card">
    <div class="prof-song-cover">
      <?php if (!empty($u['song_cover'])): ?>
        <img src="<?= e($u['song_cover']) ?>" alt="">
      <?php else: ?>
        <i class="bi bi-music-note-beamed"></i>
      <?php endif; ?>
      <div class="prof-song-wave">
        <span></span><span></span><span></span><span></span><span></span>
      </div>
    </div>
    <div class="prof-song-info">
      <div class="prof-song-label"><i class="bi bi-headphones"></i> Şu an dinliyor</div>
      <div class="prof-song-title"><?= e($u['song_title']) ?></div>
      <div class="prof-song-artist"><?= e($u['song_artist'] ?? '') ?></div>
    </div>
    <?php if (!empty($u['song_url'])): ?>
    <a href="<?= e($u['song_url']) ?>" target="_blank" class="prof-song-play">
      <i class="bi bi-play-fill"></i>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Action buttons -->
	  <div class="prof-actions">
	    <?php if ($isOwn): ?>
	      <a href="/settings" class="prof-btn-edit">Profili Düzenle</a>
	      <a href="/stats" class="prof-btn-share" title="İstatistikler"><i class="bi bi-bar-chart-line"></i></a>
	    <?php elseif ($loggedUser): ?>
	      <button onclick="toggleFollow('<?= $username ?>', this)" class="prof-btn-edit <?= $isFollowing ? 'following' : '' ?>">
	        <?= $isFollowing ? 'Takibi Bırak' : 'Takip Et' ?>
	      </button>
	      <a href="/dm/<?= e($username) ?>" class="prof-btn-share"><i class="bi bi-send"></i></a>
	      <button onclick="shareProfile()" class="prof-btn-share"><i class="bi bi-box-arrow-up"></i></button>
	    <?php else: ?>
	      <button onclick="shareProfile()" class="prof-btn-edit">Profili Paylaş <i class="bi bi-box-arrow-up"></i></button>
	    <?php endif; ?>
	  </div>
</div>

<!-- Soru Gönder -->
<div class="prof-ask-section">
  <form method="POST" class="prof-ask-form">
    <div class="prof-ask-box">
      <i class="bi bi-incognito prof-ask-icon"></i>
      <textarea name="message"
        placeholder="<?= e($isOwn ? 'Kendine anonim soru sor... 🤫' : '@'.$username."'e anonim soru sor... 💬") ?>"
        required maxlength="600" rows="1"
        oninput="autoResize(this)"></textarea>
    </div>
    <button type="submit" class="prof-ask-send"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<!-- Sorular -->
<?php if (!empty($messages)): ?>
<div class="prof-sep"><i class="bi bi-grid-3x3"></i><span>Sorular</span></div>
<div class="prof-questions">
<?php foreach ($messages as $m): ?>
  <div class="prof-qcard">
    <div class="prof-qcard-header">
      <i class="bi bi-incognito prof-qcard-anon"></i>
      <span class="prof-qcard-time"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></span>
      <?php if ($m['likes'] > 0): ?>
        <span class="prof-qcard-likes"><i class="bi bi-heart-fill"></i><?= $m['likes'] ?></span>
      <?php endif; ?>
    </div>
    <p class="prof-qcard-text"><?= nl2br(e($m['text'])) ?></p>

    <!-- Yanıtlar -->
    <?php foreach ($m['replies'] as $r): ?>
    <div class="prof-reply">
      <div class="prof-reply-avatar">
        <?php if (!empty($u['avatar_url'])): ?><img src="<?= e($u['avatar_url']) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($username,0,1)); endif; ?>
      </div>
      <div class="prof-reply-bubble">
        <span class="prof-reply-name"><?= e($u['display_name'] ?? $username) ?></span>
        <p><?= nl2br(e($r['text'])) ?></p>
        <span class="prof-reply-time"><?= date('H:i', strtotime($r['time'])) ?></span>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Yanıt formu (sadece profil sahibi) -->
    <?php if ($isOwn): ?>
    <div class="prof-reply-toggle-wrap">
      <button class="prof-reply-toggle" onclick="toggleReply('<?= $m['id'] ?>')">
        <i class="bi bi-chat"></i> Yanıtla
      </button>
    </div>
    <div id="rep-<?= $m['id'] ?>" class="prof-reply-form" style="display:none;">
      <form method="POST" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="parent_id" value="<?= $m['id'] ?>">
        <input type="text" name="reply_text" class="input-glass" placeholder="Yanıtın..." required style="flex:1;padding:10px 14px;">
        <button type="submit" style="background:var(--pink);border:none;border-radius:12px;color:#fff;padding:10px 14px;cursor:pointer;font-size:1rem;">
          <i class="bi bi-send"></i>
        </button>
      </form>
    </div>
    <?php endif; ?>

    <div class="prof-qcard-actions">
      <button class="prof-like-btn" onclick="likeMsg('<?= $m['id'] ?>',this)">
        <i class="bi bi-heart"></i>
        <span id="lc-<?= $m['id'] ?>"><?= $m['likes'] ?></span>
      </button>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="prof-empty">
  <i class="bi bi-chat-heart"></i>
  <p>Henüz soru yok. İlk soruyu sor! ☝️</p>
</div>
<?php endif; ?>

<!-- Bottom Nav -->
<nav class="bottom-nav">
<?php if ($loggedUser): ?>
  <a href="/"                              class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"                        class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <a href="/messages"                      class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications"                 class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item <?= $isOwn ? 'active' : '' ?>">
    <?php if ($myAvatar): ?>
      <img src="<?= e($myAvatar) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid <?= $isOwn?'var(--pink)':'transparent' ?>;">
    <?php else: ?><i class="bi bi-person-circle"></i><?php endif; ?>
    <span>Profil</span>
  </a>
<?php else: ?>
  <a href="/"       class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search" class="nav-item"><i class="bi bi-compass"></i><span>Keşfet</span></a>
  <div class="nav-item"></div>
  <div class="nav-item"></div>
  <a href="/"       class="nav-item"><i class="bi bi-person-fill"></i><span>Giriş</span></a>
<?php endif; ?>
</nav>

<script>
function toggleReply(id){ const el=document.getElementById('rep-'+id); el.style.display=el.style.display==='none'?'block':'none'; }
function shareProfile(){ if(navigator.share) navigator.share({title:'@<?= e($username) ?>',url:'<?= $profileUrl ?>'}); else navigator.clipboard.writeText('<?= $profileUrl ?>').then(()=>showToast('Link kopyalandı! 🔗')); }
function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,120)+'px'; }
async function likeMsg(id,btn){
	    const fd=new FormData(); fd.append('like_id',id);
	    const res=await fetch(window.location.href,{method:'POST',body:fd});
	    const data=await res.json();
	    const cnt=document.getElementById('lc-'+id);
	    if(cnt) cnt.textContent=data.count;
	    if(data.ok){ btn.querySelector('i').className='bi bi-heart-fill'; btn.style.color='var(--pink)'; }
	}
async function toggleFollow(username, btn) {
    const isFollowing = btn.classList.contains('following');
    const action = isFollowing ? 'unfollow' : 'follow';
    const fd = new FormData();
    fd.append('action', action);
    const res = await fetch(window.location.href + '?ajax=1', {method: 'POST', body: fd});
    const data = await res.json();
    if (data.ok) {
        if (action === 'follow') {
            btn.classList.add('following');
            btn.textContent = 'Takibi Bırak';
        } else {
            btn.classList.remove('following');
            btn.textContent = 'Takip Et';
        }
        location.reload(); // Sayıları güncellemek için
    }
}
function showToast(msg){ const t=document.createElement('div'); t.className='prof-toast'; t.textContent=msg; document.body.appendChild(t); setTimeout(()=>t.classList.add('show'),50); setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},2500); }
</script>
</body>
</html>
