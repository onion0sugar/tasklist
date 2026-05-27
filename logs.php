<?php
require 'config.php';
requireLogin();

$db   = getDB();
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stmt = $db->prepare("SELECT * FROM logs WHERE date = :date ORDER BY logged_at DESC");
$stmt->execute([':date' => $date]);
$rows = $stmt->fetchAll();

$dates = $db->query("SELECT DISTINCT date FROM logs ORDER BY date DESC LIMIT 90")->fetchAll(PDO::FETCH_COLUMN);

$labels = [
    'completed' => ['Wykonane',      '#0f5132', '#d1e7dd'],
    'repeat'    => ['Ponowna próba', '#856404', '#fff3cd'],
    'created'   => ['Utworzone',     '#084298', '#cfe2ff'],
    'deleted'   => ['Usunięte',      '#842029', '#f8d7da'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logi – <?= htmlspecialchars($date) ?></title>
<style>
  * { box-sizing: border-box; }
  body  { font-family: sans-serif; max-width: 700px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
  h1    { margin-bottom: 4px; }
  nav   { margin-bottom: 20px; }
  nav a { color: #333; text-decoration: none; }
  nav a:hover { text-decoration: underline; }
  .filters { margin-bottom: 16px; }
  select { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #f0f0f0; font-size: .85em; color: #555; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .78em; font-weight: bold; }
</style>
</head>
<body>
<h1>Logi</h1>
<nav><a href="index.php">&larr; Lista zadań</a></nav>

<div class="filters">
  <form method="get">
    <label>Dzień:
      <select name="date" onchange="this.form.submit()">
        <?php
        $allDates = in_array($date, $dates) ? $dates : array_merge([$date], $dates);
        foreach ($allDates as $d):
        ?>
          <option value="<?= $d ?>" <?= $d === $date ? 'selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
</div>

<table>
  <thead><tr><th>Zadanie</th><th>Akcja</th><th>Czas</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
    $action = $r['action'] ?? 'completed';
    [$label, $color, $bg] = $labels[$action] ?? [$action, '#333', '#eee'];
  ?>
  <tr>
    <td><?= htmlspecialchars($r['task_name']) ?></td>
    <td><span class="badge" style="color:<?= $color ?>;background:<?= $bg ?>"><?= $label ?></span></td>
    <td><?= $r['logged_at'] ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($rows)): ?>
  <tr><td colspan="3" style="color:#aaa;text-align:center">Brak logów dla <?= htmlspecialchars($date) ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</body>
</html>
