<?php
require_once __DIR__ . '/../../app/config.php';
secureSession();
$loggedUser = $_SESSION['username'] ?? null;

// JSON modu (AJAX)
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    $q    = trim($_GET['q'] ?? '');
    $like = '%' . $q . '%';
    if ($q === '') {
        $rows = db()->query('SELECT username,display_name,bio,avatar_url FROM users ORDER BY created_at DESC LIMIT 20')->fetchAll();
    } else {
        $s = db()->prepare('SELECT username,display_name,bio,avatar_url FROM users WHERE username LIKE ? OR display_name LIKE ? ORDER BY username LIMIT 30');
        $s->execute([$like, $like]);
        $rows = $s->fetchAll();
    }
    echo json_encode($rows);
    exit;
}

// İlk yükleme için seed
$q    = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
if ($q === '') {
    $users = db()->query('SELECT username,display_name,bio,avatar_url FROM users ORDER BY created_at DESC LIMIT 20')->fetchAll();
} else {
    $s = db()->prepare('SELECT username,display_name,bio,avatar_url FROM users WHERE username LIKE ? OR display_name LIKE ? ORDER BY username LIMIT 30');
    $s->execute([$like, $like]);
    $users = $s->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Keşfet | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="top-bar">
  <a href="/" style="color:var(--text);text-decoration:none;font-size:1.3rem;"><i class="bi bi-chevron-left"></i></a>
  <div class="search-pill" style="cursor:auto;">
    <i class="bi bi-search"></i>
    <input type="text" id="q" placeholder="Arkadaşlarını keşfet..."
           value="<?= e($q) ?>" autofocus autocomplete="off">
  </div>
</header>

<div style="padding:16px;">
  <p class="section-label" id="lbl"><?= $q ? 'Arama Sonuçları' : 'Önerilen Kişiler' ?></p>
  <div id="list">
    <?php foreach ($users as $u): ?>
    <a href="/profile/<?= e($u['username']) ?>" class="user-card">
      <div class="avatar-grad avatar-md">
        <?php if (!empty($u['avatar_url'])): ?><img src="<?= e($u['avatar_url']) ?>" alt=""><?php else: echo strtoupper($u['username'][0]); endif; ?>
      </div>
      <div class="details">
        <span class="uname">@<?= e($u['username']) ?></span>
        <span class="ubio"><?= e($u['bio'] ?? 'Henüz biyografi yok.') ?></span>
      </div>
      <i class="bi bi-chevron-right" style="color:var(--muted);"></i>
    </a>
    <?php endforeach; ?>
    <?php if (empty($users) && $q): ?>
      <div style="text-align:center;padding:40px 0;opacity:.4;">
        <i class="bi bi-person-x" style="font-size:3rem;"></i><p>Kimse bulunamadı 🫠</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
<?php if ($loggedUser): ?>
  <a href="/"             class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search"       class="nav-item active"><i class="bi bi-compass-fill"></i><span>Keşfet</span></a>
  <a href="/messages"     class="nav-item"><i class="bi bi-send"></i><span>Mesajlar</span></a>
  <a href="/notifications" class="nav-item"><i class="bi bi-bell"></i><span>Bildirimler</span></a>
  <a href="/profile/<?= e($loggedUser) ?>" class="nav-item"><i class="bi bi-person-circle"></i><span>Profil</span></a>
<?php else: ?>
  <a href="/"       class="nav-item"><i class="bi bi-house-fill"></i><span>Ana Sayfa</span></a>
  <a href="/search" class="nav-item active"><i class="bi bi-compass-fill"></i><span>Keşfet</span></a>
  <div class="nav-item"></div>
  <div class="nav-item"></div>
  <a href="/"       class="nav-item"><i class="bi bi-person-fill"></i><span>Giriş</span></a>
<?php endif; ?>
</nav>

<script>
const input = document.getElementById('q');
const list  = document.getElementById('list');
const lbl   = document.getElementById('lbl');
let   timer;

// ── AJAX arama, sayfa yenileme YOK ──────────────────────────
input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const v = input.value.trim();
        lbl.textContent = v ? 'Arama Sonuçları' : 'Önerilen Kişiler';
        const res  = await fetch('/search?json=1&q=' + encodeURIComponent(v));
        const data = await res.json();
        renderUsers(data, v);
    }, 300);
});

function renderUsers(users, q) {
    if (!users.length && q) {
        list.innerHTML = '<div style="text-align:center;padding:40px 0;opacity:.4;"><i class="bi bi-person-x" style="font-size:3rem;"></i><p>Kimse bulunamadı 🫠</p></div>';
        return;
    }
    list.innerHTML = users.map(u => {
        const ava = u.avatar_url
            ? `<img src="${escHtml(u.avatar_url)}" alt="">`
            : escHtml((u.username||'?')[0].toUpperCase());
        const bio = escHtml(u.bio || 'Henüz biyografi yok.');
        return `<a href="/profile/${escHtml(u.username)}" class="user-card">
            <div class="avatar-grad avatar-md">${ava}</div>
            <div class="details">
              <span class="uname">@${escHtml(u.username)}</span>
              <span class="ubio">${bio}</span>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--muted);"></i>
          </a>`;
    }).join('');
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
