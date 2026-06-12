<?php
// =============================================
// geminy.me v7.0 — Kullanıcı İstatistikleri
// Sadece hesap sahibi görebilir
// =============================================
require_once __DIR__ . '/../../app/config.php';
secureSession();

$loggedUser = $_SESSION['username'] ?? null;
if (!$loggedUser) redirect(SITE_URL . '/');

// ── Kullanıcı bilgileri ───────────────────────────────────────
$me = db()->prepare('SELECT * FROM users WHERE username=?');
$me->execute([$loggedUser]);
$me = $me->fetch();
if (!$me) redirect(SITE_URL . '/');

$userId = $me['id'];

// ── Genel istatistikler ───────────────────────────────────────
// Toplam soru sayısı
$qStmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE to_user=?');
$qStmt->execute([$loggedUser]);
$totalQuestions = (int)$qStmt->fetchColumn();

// Toplam beğeni
$lStmt = db()->prepare(
    'SELECT COUNT(*) FROM message_likes ml
     JOIN messages m ON m.id = ml.message_id
     WHERE m.to_user=?'
);
$lStmt->execute([$loggedUser]);
$totalLikes = (int)$lStmt->fetchColumn();

// Toplam yanıt
$rStmt = db()->prepare(
    'SELECT COUNT(*) FROM replies r
     JOIN messages m ON m.id = r.message_id
     WHERE m.to_user=?'
);
$rStmt->execute([$loggedUser]);
$totalReplies = (int)$rStmt->fetchColumn();

// Toplam Takipçi
$fStmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE following_id=?');
$fStmt->execute([$userId]);
$totalFollowers = (int)$fStmt->fetchColumn();

// ── Son 7 günlük profil görüntülenme trendi ────────────────────
$viewTrendStmt = db()->prepare(
    'SELECT view_date, view_count
     FROM profile_views
     WHERE user_id=? AND view_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     ORDER BY view_date ASC'
);
$viewTrendStmt->execute([$userId]);
$viewTrendRaw = $viewTrendStmt->fetchAll();

$viewTrendData = [];
$trendLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('d.m', strtotime($d));
    $viewTrendData[$d] = 0;
}
foreach ($viewTrendRaw as $row) {
    if (isset($viewTrendData[$row['view_date']])) {
        $viewTrendData[$row['view_date']] = (int)$row['view_count'];
    }
}
$viewTrendValues = array_values($viewTrendData);

// ── Son 7 günlük soru trendi ──────────────────────────────────
$qTrendStmt = db()->prepare(
    'SELECT DATE(created_at) as day, COUNT(*) as cnt
     FROM messages
     WHERE to_user=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)'
);
$qTrendStmt->execute([$loggedUser]);
$qTrendRaw = $qTrendStmt->fetchAll();

$qTrendData = array_fill_keys(array_keys($viewTrendData), 0);
foreach ($qTrendRaw as $row) {
    if (isset($qTrendData[$row['day']])) {
        $qTrendData[$row['day']] = (int)$row['cnt'];
    }
}
$qTrendValues = array_values($qTrendData);

// JSON encode for JS
$jsLabels = json_encode($trendLabels);
$jsViewData = json_encode($viewTrendValues);
$jsQData = json_encode($qTrendValues);

$myAvatar = $me['avatar_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>İstatistikler | geminy.me</title>
<meta name="theme-color" content="#0A0A0F">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
body { background:#0A0A0F; color:#EEF0FF; font-family:Arial,sans-serif; margin:0; padding-bottom:80px; }
.stats-topbar {
  position:sticky; top:0; z-index:100;
  display:flex; align-items:center; gap:12px;
  padding:14px 16px; background:rgba(10,10,15,.92);
  backdrop-filter:blur(14px); border-bottom:1px solid rgba(255,255,255,.06);
}
.stats-topbar-title { flex:1; font-weight:800; font-size:1.05rem; }
.stats-back { color:#EEF0FF; text-decoration:none; font-size:1.3rem; }

.stats-profile { display:flex; align-items:center; gap:14px; padding:20px 16px; }
.stats-avatar {
  width:54px; height:54px; border-radius:50%; overflow:hidden;
  background:linear-gradient(135deg,#E91E8C,#7C3AED);
  display:flex; align-items:center; justify-content:center;
  font-size:1.4rem; font-weight:900; color:#fff; border:2px solid rgba(233,30,140,.4);
}
.stats-avatar img { width:100%; height:100%; object-fit:cover; }
.stats-username { font-weight:800; font-size:1rem; }
.stats-handle { color:#6B6F9A; font-size:.8rem; }

.stats-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:0 14px 14px; }
.stats-card {
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  border-radius:18px; padding:18px 16px; position:relative; overflow:hidden;
}
.stats-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--accent, #E91E8C); }
.stats-card-num { font-size:1.8rem; font-weight:900; background:var(--accent, linear-gradient(135deg,#E91E8C,#7C3AED)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.stats-card-label { font-size:.75rem; color:#6B6F9A; margin-top:4px; }

.chart-section { margin:0 14px 14px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:18px; padding:18px 16px; }
.chart-title { font-size:.8rem; color:#6B6F9A; text-transform:uppercase; margin-bottom:14px; font-weight:700; }
.chart-wrap { height:200px; }
</style>
</head>
<body>

<div class="stats-topbar">
  <a href="/profile/<?= e($loggedUser) ?>" class="stats-back"><i class="bi bi-chevron-left"></i></a>
  <span class="stats-topbar-title">İstatistikler</span>
</div>

<div class="stats-profile">
  <div class="stats-avatar">
    <?php if ($myAvatar): ?><img src="<?= e($myAvatar) ?>" alt=""><?php else: echo mb_strtoupper(mb_substr($loggedUser,0,1)); endif; ?>
  </div>
  <div>
    <div class="stats-username"><?= e($me['display_name'] ?? $loggedUser) ?></div>
    <div class="stats-handle">@<?= e($loggedUser) ?></div>
  </div>
</div>

<div class="stats-grid">
  <div class="stats-card" style="--accent:#E91E8C">
    <div class="stats-card-num"><?= $totalQuestions ?></div>
    <div class="stats-card-label">Toplam Soru</div>
  </div>
  <div class="stats-card" style="--accent:#7C3AED">
    <div class="stats-card-num"><?= $totalFollowers ?></div>
    <div class="stats-card-label">Takipçi</div>
  </div>
  <div class="stats-card" style="--accent:#22C55E">
    <div class="stats-card-num"><?= $totalReplies ?></div>
    <div class="stats-card-label">Yanıt</div>
  </div>
  <div class="stats-card" style="--accent:#EAB308">
    <div class="stats-card-num"><?= $totalLikes ?></div>
    <div class="stats-card-label">Beğeni</div>
  </div>
</div>

<div class="chart-section">
  <div class="chart-title"><i class="bi bi-eye-fill"></i> Profil Görüntülenme (Son 7 Gün)</div>
  <div class="chart-wrap"><canvas id="viewChart"></canvas></div>
</div>

<div class="chart-section">
  <div class="chart-title"><i class="bi bi-chat-dots-fill"></i> Gelen Sorular (Son 7 Gün)</div>
  <div class="chart-wrap"><canvas id="qChart"></canvas></div>
</div>

<script>
const labels = <?= $jsLabels ?>;
const viewData = <?= $jsViewData ?>;
const qData = <?= $jsQData ?>;

const ctx1 = document.getElementById('viewChart').getContext('2d');
new Chart(ctx1, {
  type: 'line',
  data: {
    labels: labels,
    datasets: [{
      label: 'Görüntülenme',
      data: viewData,
      borderColor: '#E91E8C',
      backgroundColor: 'rgba(233, 30, 140, 0.1)',
      fill: true,
      tension: 0.4,
      borderWidth: 3,
      pointRadius: 4,
      pointBackgroundColor: '#E91E8C'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6B6F9A' } },
      x: { grid: { display: false }, ticks: { color: '#6B6F9A' } }
    }
  }
});

const ctx2 = document.getElementById('qChart').getContext('2d');
new Chart(ctx2, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'Sorular',
      data: qData,
      backgroundColor: '#7C3AED',
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6B6F9A' } },
      x: { grid: { display: false }, ticks: { color: '#6B6F9A' } }
    }
  }
});
</script>
</body>
</html>
