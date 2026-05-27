<?php
require 'config.php';
requireLogin();

$db     = getDB();
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if ($taskId > 0) {
    $stmt = $db->prepare("SELECT id, name FROM tasks WHERE id = :id AND active = 1");
    $stmt->execute([':id' => $taskId]);
    $tasks = $stmt->fetchAll();
} else {
    $tasks = $db->query("SELECT id, name FROM tasks WHERE active = 1 ORDER BY name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Kody QR – wydruk</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; background: #fff; padding: 20px; }
  .no-print { margin-bottom: 20px; }
  .no-print button { padding: 8px 16px; background: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: .9em; margin-right: 8px; }
  .no-print a { color: #333; font-size: .9em; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
  .card { border: 1px solid #ccc; border-radius: 6px; padding: 14px; text-align: center; page-break-inside: avoid; }
  .card img { width: 140px; height: 140px; display: block; margin: 0 auto 10px; }
  .card p { font-size: .9em; color: #333; word-break: break-word; }
  @media print {
    .no-print { display: none; }
    body { padding: 10px; }
  }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">&#128438; Drukuj / Zapisz jako PDF</button>
  <a href="index.php">&larr; Wróć</a>
</div>
<?php if (empty($tasks)): ?>
  <p>Brak zadań do wydruku.</p>
<?php else: ?>
<div class="grid">
<?php foreach ($tasks as $t):
  $url = APP_URL . '/scan.php?task_id=' . $t['id'];
  $qr  = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($url);
?>
  <div class="card">
    <img src="<?= $qr ?>" alt="QR">
    <p><?= htmlspecialchars($t['name']) ?></p>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
