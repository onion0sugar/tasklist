<?php
require 'config.php';
requireLogin();

$db    = getDB();
$today = date('Y-m-d');

$db->prepare("
    INSERT IGNORE INTO daily_tasks (task_id, date, status)
    SELECT id, :date, 0 FROM tasks WHERE active = 1
")->execute([':date' => $today]);

$stmt = $db->prepare("
    SELECT t.id, t.name, COALESCE(dt.status, 0) AS status
    FROM tasks t
    LEFT JOIN daily_tasks dt ON dt.task_id = t.id AND dt.date = :date
    WHERE t.active = 1
    ORDER BY t.name
");
$stmt->execute([':date' => $today]);
$tasks = $stmt->fetchAll();

$total = count($tasks);
$done  = array_sum(array_column($tasks, 'status'));
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista zadań – <?= $today ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: sans-serif; max-width: 960px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
  h1 { margin-bottom: 4px; }
  .sub { color: #666; margin-bottom: 20px; }
  nav { margin-bottom: 20px; }
  nav a { margin-right: 12px; color: #333; text-decoration: none; }
  nav a:hover { text-decoration: underline; }
  .progress { background: #ddd; border-radius: 4px; height: 12px; margin-bottom: 24px; }
  .progress-bar { background: #2a7; height: 100%; border-radius: 4px; transition: width .3s; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
  .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; text-align: center; }
  .card.done { opacity: .5; }
  .card h3 { margin: 0 0 12px; font-size: 1em; }
  .card img { width: 160px; height: 160px; display: block; margin: 0 auto 10px; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: .8em; }
  .badge.pending { background: #fef3cd; color: #856404; }
  .badge.done    { background: #d1e7dd; color: #0f5132; }
  .actions { display: flex; gap: 6px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }
  .btn { padding: 5px 10px; font-size: .78em; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; background: #fff; text-decoration: none; color: #333; }
  .btn:hover { background: #f0f0f0; }
  .copied { color: #2a7; border-color: #2a7; }
</style>
</head>
<body>
<h1>Lista zadań</h1>
<div class="sub"><?= $today ?> &mdash; wykonano <?= $done ?> / <?= $total ?></div>
<nav>
  <a href="admin.php">+ Zarządzaj zadaniami</a>
  <a href="logs.php">Logi</a>
  <a href="print.php" target="_blank">&#128438; Pobierz PDF</a>
  <a href="logout.php" style="float:right;color:#999">Wyloguj</a>
</nav>
<?php if ($total > 0): ?>
<div class="progress">
  <div class="progress-bar" style="width:<?= round($done / $total * 100) ?>%"></div>
</div>
<?php endif; ?>
<?php if (empty($tasks)): ?>
  <p>Brak zadań. <a href="admin.php">Dodaj zadanie</a>.</p>
<?php else: ?>
<div class="grid">
<?php foreach ($tasks as $t):
    $url = APP_URL . '/scan.php?task_id=' . $t['id'];
    $qr  = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($url);
?>
  <div class="card<?= $t['status'] ? ' done' : '' ?>">
    <h3><?= htmlspecialchars($t['name']) ?></h3>
    <img src="<?= $qr ?>" alt="QR: <?= htmlspecialchars($t['name']) ?>">
    <span class="badge <?= $t['status'] ? 'done' : 'pending' ?>">
      <?= $t['status'] ? '&#10003; Wykonane' : 'Oczekuje' ?>
    </span>
    <div class="actions">
      <button class="btn" onclick="copyLink(this, '<?= htmlspecialchars($url, ENT_QUOTES) ?>')">&#128279; Kopiuj link</button>
      <a class="btn" href="print.php?task_id=<?= $t['id'] ?>" target="_blank">&#128438; PDF</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<script>
function copyLink(btn, url) {
  var ta = document.createElement('textarea');
  ta.value = url;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  document.execCommand('copy');
  document.body.removeChild(ta);
  btn.textContent = '✓ Skopiowano';
  btn.classList.add('copied');
  setTimeout(function() {
    btn.textContent = '🔗 Kopiuj link';
    btn.classList.remove('copied');
  }, 2000);
}
</script>
</body>
</html>
