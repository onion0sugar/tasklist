<?php
require 'config.php';
requireLogin();

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $db->prepare("INSERT INTO tasks (name) VALUES (:name)")->execute([':name' => $name]);
            $newId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'created', CURDATE(), NOW())")
               ->execute([':tid' => $newId, ':name' => $name]);
            $msg = 'Zadanie dodane.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE tasks SET active = 1 - active WHERE id = :id")->execute([':id' => $id]);
        $msg = 'Status zmieniony.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT name FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $toDelete = $stmt->fetch();
        if ($toDelete) {
            $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'deleted', CURDATE(), NOW())")
               ->execute([':tid' => $id, ':name' => $toDelete['name']]);
            $db->prepare("DELETE FROM tasks WHERE id = :id")->execute([':id' => $id]);
            $msg = 'Zadanie usunięte.';
        }
    }
}

$tasks = $db->query("SELECT * FROM tasks ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zarządzanie zadaniami</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: sans-serif; max-width: 700px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
  h1 { margin-bottom: 4px; }
  nav { margin-bottom: 20px; }
  nav a { color: #333; text-decoration: none; }
  nav a:hover { text-decoration: underline; }
  .msg { background: #d1e7dd; border: 1px solid #badbcc; padding: 8px 12px; border-radius: 4px; margin-bottom: 16px; }
  form.add { display: flex; gap: 8px; margin-bottom: 24px; }
  form.add input[type=text] { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; }
  button { padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; background: #fff; font-size: .9em; }
  button.primary { background: #333; color: #fff; border-color: #333; }
  button.danger  { color: #c00; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #f0f0f0; font-size: .85em; color: #555; }
  .inactive td { color: #aaa; }
</style>
</head>
<body>
<h1>Zarządzanie zadaniami</h1>
<nav><a href="index.php">&larr; Lista zadań</a></nav>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form class="add" method="post">
  <input type="hidden" name="action" value="add">
  <input type="text" name="name" placeholder="Nazwa nowego zadania" required>
  <button type="submit" class="primary">Dodaj</button>
</form>

<table>
  <thead><tr><th>#</th><th>Nazwa</th><th>Status</th><th>Akcje</th></tr></thead>
  <tbody>
  <?php foreach ($tasks as $t): ?>
  <tr class="<?= $t['active'] ? '' : 'inactive' ?>">
    <td><?= $t['id'] ?></td>
    <td><?= htmlspecialchars($t['name']) ?></td>
    <td><?= $t['active'] ? 'Aktywne' : 'Nieaktywne' ?></td>
    <td>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button type="submit"><?= $t['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Na pewno usunąć?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button type="submit" class="danger">Usuń</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($tasks)): ?>
  <tr><td colspan="4" style="color:#aaa;text-align:center">Brak zadań</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</body>
</html>
