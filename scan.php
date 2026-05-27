<?php
require 'config.php';

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$today  = date('Y-m-d');
$db     = getDB();

if ($taskId <= 0) {
    http_response_code(400);
    die('Nieprawidłowy kod QR.');
}

$stmt = $db->prepare("SELECT id, name FROM tasks WHERE id = :id AND active = 1");
$stmt->execute([':id' => $taskId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    die('Zadanie nie istnieje lub jest nieaktywne.');
}

$db->prepare("INSERT IGNORE INTO daily_tasks (task_id, date, status) VALUES (:tid, :date, 0)")
   ->execute([':tid' => $taskId, ':date' => $today]);

$stmt = $db->prepare("SELECT status FROM daily_tasks WHERE task_id = :tid AND date = :date");
$stmt->execute([':tid' => $taskId, ':date' => $today]);
$row = $stmt->fetch();

$alreadyDone = $row && $row['status'] == 1;

if (!$alreadyDone) {
    $db->prepare("UPDATE daily_tasks SET status = 1 WHERE task_id = :tid AND date = :date")
       ->execute([':tid' => $taskId, ':date' => $today]);
    $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'completed', :date, NOW())")
       ->execute([':tid' => $taskId, ':name' => $task['name'], ':date' => $today]);
} else {
    $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'repeat', :date, NOW())")
       ->execute([':tid' => $taskId, ':name' => $task['name'], ':date' => $today]);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Skan zadania</title>
<style>
  body { font-family: sans-serif; max-width: 400px; margin: 0 auto; padding: 60px 20px; text-align: center; }
  .icon { font-size: 4em; margin-bottom: 16px; }
  .ok  { color: #2a7; }
  .dup { color: #aaa; }
  h2   { margin: 0 0 8px; font-size: 1.2em; }
  p    { color: #555; margin: 0; }
</style>
</head>
<body>
<?php if ($alreadyDone): ?>
  <div class="icon dup">&#10003;</div>
  <h2>Już wykonane</h2>
  <p><?= htmlspecialchars($task['name']) ?></p>
<?php else: ?>
  <div class="icon ok">&#10003;</div>
  <h2>Wykonane!</h2>
  <p><?= htmlspecialchars($task['name']) ?></p>
<?php endif; ?>
</body>
</html>
