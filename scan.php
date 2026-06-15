<?php
require 'config.php';

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$today  = date('Y-m-d');
$db     = getDB();

// --- Walidacja zadania ---
if ($taskId <= 0) {
    http_response_code(400);
    die('Nieprawidłowy kod QR.');
}

$stmt = $db->prepare("SELECT id, name, location_id FROM tasks WHERE id = :id AND active = 1");
$stmt->execute([':id' => $taskId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    die('Zadanie nie istnieje lub jest nieaktywne.');
}

// Zapewnij wiersz w daily_tasks
$db->prepare("INSERT IGNORE INTO daily_tasks (task_id, date, status) VALUES (:tid, :date, 0)")
   ->execute([':tid' => $taskId, ':date' => $today]);

// Pobierz aktualny status
$stmt = $db->prepare("SELECT status, scanned_by, scanned_at FROM daily_tasks WHERE task_id = :tid AND date = :date");
$stmt->execute([':tid' => $taskId, ':date' => $today]);
$row = $stmt->fetch();
$alreadyDone = $row && $row['status'] == 1;

// Lista pracowników
$employees = $db->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();

// Zapamiętany pracownik z cookie
$rememberedEmpId = isset($_COOKIE['remembered_employee']) ? (int)$_COOKIE['remembered_employee'] : 0;

$confirmed = false;
$confirmedBy = '';
$confirmedAt = '';
$error = '';

// --- Obsługa potwierdzenia ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyDone) {
    $empId    = (int)($_POST['employee_id'] ?? 0);
    $remember = !empty($_POST['remember']);

    // Znajdź pracownika
    $empName = '';
    foreach ($employees as $e) {
        if ((int)$e['id'] === $empId) {
            $empName = $e['name'];
            break;
        }
    }

    if (!$empName) {
        $error = 'Proszę wybrać pracownika z listy.';
    } else {
        // Obsługa cookie "zapamiętaj mnie"
        if ($remember) {
            // Cookie ważne 8h (do końca zmiany)
            setcookie('remembered_employee', $empId, time() + 8 * 3600, '/');
            $rememberedEmpId = $empId;
        } else {
            setcookie('remembered_employee', '', time() - 3600, '/');
            $rememberedEmpId = 0;
        }

        $now = date('Y-m-d H:i:s');

        // Oznacz jako wykonane
        $db->prepare("
            UPDATE daily_tasks
            SET status = 1, scanned_by = :by, scanned_at = :at
            WHERE task_id = :tid AND date = :date
        ")->execute([':by' => $empName, ':at' => $now, ':tid' => $taskId, ':date' => $today]);

        // Zapisz log
        $db->prepare("
            INSERT INTO logs (task_id, task_name, action, scanned_by, date, logged_at)
            VALUES (:tid, :name, 'completed', :by, :date, NOW())
        ")->execute([':tid' => $taskId, ':name' => $task['name'], ':by' => $empName, ':date' => $today]);

        $confirmed   = true;
        $confirmedBy = $empName;
        $confirmedAt = date('H:i', strtotime($now));
    }
}

// Pobierz pozostałe zadania do wykonania w tej samej lokalizacji dzisiaj
$remainingTasks = [];
if (!empty($task['location_id'])) {
    $stmt = $db->prepare("
        SELECT t.id, t.name
        FROM tasks t
        LEFT JOIN daily_tasks dt ON dt.task_id = t.id AND dt.date = :date
        WHERE t.location_id = :loc_id 
          AND t.active = 1 
          AND t.id != :task_id 
          AND (dt.status IS NULL OR dt.status = 0)
        ORDER BY t.name
    ");
    $stmt->execute([
        ':date' => $today,
        ':loc_id' => $task['location_id'],
        ':task_id' => $taskId
    ]);
    $remainingTasks = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Skan zadania</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: sans-serif; max-width: 400px; margin: 0 auto; padding: 60px 20px; text-align: center; background: #f5f5f5; }
  .icon      { font-size: 4em; margin-bottom: 16px; }
  .ok        { color: #2a7; }
  .dup       { color: #aaa; }
  h2         { margin: 0 0 8px; font-size: 1.2em; }
  p          { color: #555; margin: 0 0 6px; }
  .meta      { font-size: .85em; color: #888; margin-top: 10px; }
  .card      { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 24px 20px; margin-top: 8px; word-break: break-word; overflow-wrap: break-word; }

  /* Formularz wyboru */
  .form-group { text-align: left; margin-bottom: 16px; }
  label       { display: block; font-size: .85em; color: #555; margin-bottom: 4px; }
  select      { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; background: #fff; }
  .check-row  { display: flex; align-items: center; gap: 8px; font-size: .9em; color: #555; margin-bottom: 20px; }
  .check-row input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
  .btn-confirm { width: 100%; padding: 12px; background: #333; color: #fff; border: none; border-radius: 6px; font-size: 1em; cursor: pointer; letter-spacing: .03em; }
  .btn-confirm:hover { background: #111; }
  .error { color: #c00; font-size: .9em; margin-bottom: 12px; }
  .no-employees { color: #c00; font-size: .9em; padding: 10px; background: #fff0f0; border-radius: 4px; }
</style>
</head>
<body>

<?php if ($alreadyDone): ?>
  <!-- ── JUŻ WYKONANE ─────────────────────────────── -->
  <div class="icon dup">&#10003;</div>
  <h2>Już wykonane</h2>
  <div class="card">
    <p><?= htmlspecialchars($task['name']) ?></p>
    <?php if (!empty($row['scanned_by'])): ?>
    <div class="meta">
      Wykonał(a): <strong><?= htmlspecialchars($row['scanned_by']) ?></strong><br>
      o godz. <strong><?= date('H:i', strtotime($row['scanned_at'])) ?></strong>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($remainingTasks)): ?>
  <div class="card" style="margin-top: 16px; text-align: left;">
    <h3 style="margin-top: 0; font-size: 0.9em; color: #475569; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; font-weight: 600;">
      Pozostałe zadania w tej lokalizacji (<?= count($remainingTasks) ?>):
    </h3>
    <ul style="margin: 8px 0 0; padding-left: 20px; font-size: 0.88em; color: #1e293b; line-height: 1.6;">
      <?php foreach ($remainingTasks as $rt): ?>
        <li><?= htmlspecialchars($rt['name']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

<?php elseif ($confirmed): ?>
  <!-- ── WŁAŚNIE WYKONANE ─────────────────────────── -->
  <div class="icon ok">&#10003;</div>
  <h2>Wykonane!</h2>
  <div class="card">
    <p><?= htmlspecialchars($task['name']) ?></p>
    <div class="meta">
      Wykonał(a): <strong><?= htmlspecialchars($confirmedBy) ?></strong><br>
      o godz. <strong><?= $confirmedAt ?></strong>
    </div>
  </div>

  <?php if (!empty($remainingTasks)): ?>
  <div class="card" style="margin-top: 16px; text-align: left;">
    <h3 style="margin-top: 0; font-size: 0.9em; color: #475569; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; font-weight: 600;">
      Pozostałe zadania w tej lokalizacji (<?= count($remainingTasks) ?>):
    </h3>
    <ul style="margin: 8px 0 0; padding-left: 20px; font-size: 0.88em; color: #1e293b; line-height: 1.6;">
      <?php foreach ($remainingTasks as $rt): ?>
        <li><?= htmlspecialchars($rt['name']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ── FORMULARZ POTWIERDZENIA ───────────────────── -->
  <h2>Potwierdź wykonanie</h2>
  <div class="card">
    <p style="font-weight:600;font-size:1.05em;margin-bottom:20px"><?= htmlspecialchars($task['name']) ?></p>

    <?php if (empty($employees)): ?>
      <div class="no-employees">Brak pracowników w systemie.<br>Dodaj pracowników w panelu administracyjnym.</div>
    <?php else: ?>
      <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" action="scan.php?task_id=<?= $taskId ?>">
        <div class="form-group">
          <label for="employee_id">Kto wykonuje zadanie?</label>
          <select name="employee_id" id="employee_id" required>
            <option value="">— wybierz z listy —</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $rememberedEmpId === (int)$e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="check-row">
          <input type="checkbox" name="remember" id="remember" value="1"
            <?= $rememberedEmpId ? 'checked' : '' ?>>
          <label for="remember" style="margin:0;font-size:.9em;cursor:pointer">Zapamiętaj mnie na tę zmianę (8h)</label>
        </div>

        <button type="submit" class="btn-confirm">&#10003;&nbsp; Potwierdź wykonanie</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

</body>
</html>
