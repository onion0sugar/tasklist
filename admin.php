<?php
require 'config.php';
requireLogin();

$db  = getDB();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- TASK ACTIONS ---
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $locId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO tasks (name, location_id) VALUES (:name, :loc_id)");
            $stmt->execute([':name' => $name, ':loc_id' => $locId]);
            $newId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'created', CURDATE(), NOW())")
               ->execute([':tid' => $newId, ':name' => $name]);
            $msg = 'Zadanie zostało dodane.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE tasks SET active = 1 - active WHERE id = :id")->execute([':id' => $id]);
        $msg = 'Status zadania został zmieniony.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT name FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $toDelete = $stmt->fetch();
        if ($toDelete) {
            $db->prepare("INSERT INTO logs (task_id, task_name, action, date, logged_at) VALUES (:tid, :name, 'deleted', CURDATE(), NOW())")
               ->execute([':tid' => $id, ':name' => $toDelete['name']]);
            $db->prepare("DELETE FROM tasks WHERE id = :id")->execute([':id' => $id]);
            $msg = 'Zadanie zostało usunięte.';
        }
    }
    // --- LOCATION ACTIONS ---
    elseif ($action === 'add_location') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $db->prepare("INSERT INTO locations (name) VALUES (:name)")->execute([':name' => $name]);
            $msg = 'Lokalizacja została dodana.';
        }
    } elseif ($action === 'delete_location') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM locations WHERE id = :id")->execute([':id' => $id]);
        $msg = 'Lokalizacja została usunięta.';
    }
    // --- EMPLOYEE ACTIONS ---
    elseif ($action === 'add_employee') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $db->prepare("INSERT INTO employees (name) VALUES (:name)")->execute([':name' => $name]);
            $msg = 'Pracownik został dodany.';
        }
    } elseif ($action === 'delete_employee') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM employees WHERE id = :id")->execute([':id' => $id]);
        $msg = 'Pracownik został usunięty.';
    }
}

// Pobierz dane
$tasks = $db->query("
    SELECT t.*, l.name AS location_name 
    FROM tasks t 
    LEFT JOIN locations l ON t.location_id = l.id 
    ORDER BY t.name
")->fetchAll();

$locations = $db->query("SELECT * FROM locations ORDER BY name")->fetchAll();
$employees = $db->query("SELECT * FROM employees ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zarządzanie Systemem Zadań</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f8fafc; color: #1e293b; }
  h1 { margin: 0 0 4px; font-size: 1.8em; color: #0f172a; }
  .sub { color: #64748b; margin-bottom: 20px; font-size: 0.95em; }
  
  nav { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
  nav a { color: #3b82f6; text-decoration: none; font-weight: 500; font-size: 0.95em; }
  nav a:hover { text-decoration: underline; }
  
  .msg { background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95em; font-weight: 500; }
  
  .tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
  .tab-btn { padding: 8px 16px; border: none; background: none; font-weight: 600; color: #64748b; cursor: pointer; border-radius: 6px; transition: all 0.2s; }
  .tab-btn.active { background: #0f172a; color: #fff; }
  .tab-btn:hover:not(.active) { background: #e2e8f0; color: #0f172a; }
  
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
  .card-title { font-size: 1.25em; font-weight: 600; color: #0f172a; margin: 0 0 16px; }
  
  form.add-form { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
  form.add-form input[type=text], form.add-form select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95em; outline: none; background: #fff; }
  form.add-form input[type=text] { flex: 2; min-width: 200px; }
  form.add-form select { flex: 1; min-width: 150px; }
  
  button { padding: 10px 18px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-size: 0.9em; font-weight: 600; background: #fff; color: #475569; transition: all 0.2s; }
  button:hover { background: #f1f5f9; }
  button.primary { background: #0f172a; color: #fff; border-color: #0f172a; }
  button.primary:hover { background: #1e293b; }
  button.danger { color: #ef4444; border-color: #fecaca; }
  button.danger:hover { background: #fef2f2; }
  
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
  th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.95em; }
  th { background: #f8fafc; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
  tr:last-child td { border-bottom: none; }
  .inactive td { color: #94a3b8; }
  .inactive td.actions button { opacity: 0.7; }
  
  .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.8em; font-weight: 600; }
  .badge.active { background: #d1fae5; color: #065f46; }
  .badge.inactive { background: #f1f5f9; color: #475569; }
  
  .loc-tag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; background: #e2e8f0; color: #475569; font-weight: 500; }
</style>
</head>
<body>

<h1>Panel Administracyjny</h1>
<div class="sub">Zarządzaj zadaniami, lokalizacjami i pracownikami</div>

<nav>
  <a href="index.php">&larr; Powrót do tablicy głównej</a>
  <a href="manager.php">Panel Kierownika &rarr;</a>
</nav>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="tabs">
  <button class="tab-btn active" onclick="switchTab('tasks')">Zadania</button>
  <button class="tab-btn" onclick="switchTab('locations')">Lokalizacje</button>
  <button class="tab-btn" onclick="switchTab('employees')">Pracownicy</button>
</div>

<!-- ================= ZADANIA ================= -->
<div id="tasks-tab" class="tab-content active">
  <div class="card">
    <div class="card-title">Dodaj nowe zadanie</div>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="Nazwa zadania (np. Kontrola czystości biura)" required>
      <select name="location_id">
        <option value="">— wybierz lokalizację (opcjonalnie) —</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="primary">Dodaj zadanie</button>
    </form>
  </div>

  <div class="card" style="padding: 0; overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nazwa zadania</th>
          <th>Lokalizacja</th>
          <th>Status</th>
          <th style="text-align: right;">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr class="<?= $t['active'] ? '' : 'inactive' ?>">
          <td><?= $t['id'] ?></td>
          <td style="font-weight: 500;"><?= htmlspecialchars($t['name']) ?></td>
          <td>
            <?php if (!empty($t['location_name'])): ?>
              <span class="loc-tag"><?= htmlspecialchars($t['location_name']) ?></span>
            <?php else: ?>
              <span style="color: #cbd5e1; font-size: 0.9em;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $t['active'] ? 'active' : 'inactive' ?>">
              <?= $t['active'] ? 'Aktywne' : 'Nieaktywne' ?>
            </span>
          </td>
          <td class="actions" style="text-align: right;">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button type="submit"><?= $t['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Czy na pewno chcesz usunąć to zadanie? Usunie to również jego dzisiejsze statusy.')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button type="submit" class="danger">Usuń</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tasks)): ?>
        <tr><td colspan="5" style="color:#94a3b8; text-align:center; padding: 24px;">Brak zdefiniowanych zadań.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= LOKALIZACJE ================= -->
<div id="locations-tab" class="tab-content">
  <div class="card">
    <div class="card-title">Dodaj nową lokalizację</div>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add_location">
      <input type="text" name="name" placeholder="Nazwa lokalizacji (np. Piętro 1, Magazyn A)" required>
      <button type="submit" class="primary">Dodaj lokalizację</button>
    </form>
  </div>

  <div class="card" style="padding: 0; overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nazwa lokalizacji</th>
          <th style="text-align: right;">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locations as $loc): ?>
        <tr>
          <td><?= $loc['id'] ?></td>
          <td style="font-weight: 500;"><?= htmlspecialchars($loc['name']) ?></td>
          <td style="text-align: right;">
            <form method="post" style="display:inline" onsubmit="return confirm('Czy na pewno chcesz usunąć tę lokalizację? Przypisane do niej zadania zostaną odpięte.')">
              <input type="hidden" name="action" value="delete_location">
              <input type="hidden" name="id" value="<?= $loc['id'] ?>">
              <button type="submit" class="danger">Usuń</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($locations)): ?>
        <tr><td colspan="3" style="color:#94a3b8; text-align:center; padding: 24px;">Brak zdefiniowanych lokalizacji.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= PRACOWNICY ================= -->
<div id="employees-tab" class="tab-content">
  <div class="card">
    <div class="card-title">Dodaj nowego pracownika</div>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add_employee">
      <input type="text" name="name" placeholder="Imię i nazwisko pracownika" required>
      <button type="submit" class="primary">Dodaj pracownika</button>
    </form>
  </div>

  <div class="card" style="padding: 0; overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Imię i nazwisko</th>
          <th style="text-align: right;">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
        <tr>
          <td><?= $emp['id'] ?></td>
          <td style="font-weight: 500;"><?= htmlspecialchars($emp['name']) ?></td>
          <td style="text-align: right;">
            <form method="post" style="display:inline" onsubmit="return confirm('Czy na pewno chcesz usunąć tego pracownika?')">
              <input type="hidden" name="action" value="delete_employee">
              <input type="hidden" name="id" value="<?= $emp['id'] ?>">
              <button type="submit" class="danger">Usuń</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($employees)): ?>
        <tr><td colspan="3" style="color:#94a3b8; text-align:center; padding: 24px;">Brak zdefiniowanych pracowników.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function switchTab(tabId) {
  // Ukryj wszystkie zakładki
  document.querySelectorAll('.tab-content').forEach(function(el) {
    el.classList.remove('active');
  });
  // Usuń klasę active ze wszystkich przycisków
  document.querySelectorAll('.tab-btn').forEach(function(el) {
    el.classList.remove('active');
  });
  
  // Pokaż wybraną zakładkę i aktywuj przycisk
  document.getElementById(tabId + '-tab').classList.add('active');
  event.currentTarget.classList.add('active');
}
</script>
</body>
</html>
