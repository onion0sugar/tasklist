<?php
require 'config.php';

$db    = getDB();
$today = date('Y-m-d');

$db->prepare("
    INSERT IGNORE INTO daily_tasks (task_id, date, status)
    SELECT id, :date, 0 FROM tasks WHERE active = 1
")->execute([':date' => $today]);

$stmt = $db->prepare("UPDATE daily_tasks SET status = 0 WHERE date = :date");
$stmt->execute([':date' => $today]);

echo "[" . date('Y-m-d H:i:s') . "] Reset: zresetowano " . $stmt->rowCount() . " zadań na dzień $today\n";
