<?php
// Cron: 0 23 * * * php /var/www/html/tasklist/report.php
// Wysyła raport za bieżący dzień o godzinie 23:00.
// Aby wysłać za poprzedni dzień, ustaw cron na 00:05 i zmień: date('Y-m-d', strtotime('yesterday'))

require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db   = getDB();
$date = date('Y-m-d'); // zmień na: date('Y-m-d', strtotime('yesterday')) jeśli cron o północy

// Pobierz wszystkie aktywne zadania
$tasks = $db->query("SELECT id, name FROM tasks WHERE active = 1 ORDER BY name")->fetchAll();

// Pobierz statusy za dany dzień
$stmt = $db->prepare("
    SELECT task_id, status FROM daily_tasks WHERE date = :date
");
$stmt->execute([':date' => $date]);
$statuses = [];
foreach ($stmt->fetchAll() as $row) {
    $statuses[$row['task_id']] = $row['status'];
}

// Pobierz godziny wykonania z logów (tylko 'completed')
$stmt = $db->prepare("
    SELECT task_id, MIN(logged_at) AS completed_at
    FROM logs
    WHERE date = :date AND action = 'completed'
    GROUP BY task_id
");
$stmt->execute([':date' => $date]);
$times = [];
foreach ($stmt->fetchAll() as $row) {
    $times[$row['task_id']] = $row['completed_at'];
}

// Podziel zadania na wykonane i niewykonane
$done    = [];
$missing = [];
foreach ($tasks as $t) {
    if (!empty($statuses[$t['id']]) && $statuses[$t['id']] == 1) {
        $done[] = [
            'name' => $t['name'],
            'time' => isset($times[$t['id']]) ? date('H:i', strtotime($times[$t['id']])) : '—',
        ];
    } else {
        $missing[] = $t['name'];
    }
}

$totalDone    = count($done);
$totalMissing = count($missing);
$totalAll     = count($tasks);
$dateFormatted = date('d.m.Y', strtotime($date));

// Buduj HTML maila
ob_start();
?>
<!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333">
  <h2 style="margin-bottom:4px">Raport dzienny</h2>
  <p style="color:#666;margin-top:0"><?= $dateFormatted ?></p>

  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <tr>
      <td style="background:#d1e7dd;border-radius:6px;padding:12px 16px;text-align:center;width:48%">
        <div style="font-size:2em;font-weight:bold;color:#0f5132"><?= $totalDone ?></div>
        <div style="color:#0f5132;font-size:.9em">Wykonane</div>
      </td>
      <td width="4%"></td>
      <td style="background:#f8d7da;border-radius:6px;padding:12px 16px;text-align:center;width:48%">
        <div style="font-size:2em;font-weight:bold;color:#842029"><?= $totalMissing ?></div>
        <div style="color:#842029;font-size:.9em">Niewykonane</div>
      </td>
    </tr>
  </table>

  <?php if (!empty($done)): ?>
  <h3 style="color:#0f5132;border-bottom:1px solid #d1e7dd;padding-bottom:6px">✓ Wykonane (<?= $totalDone ?>)</h3>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <?php foreach ($done as $t): ?>
    <tr>
      <td style="padding:8px 0;border-bottom:1px solid #eee"><?= htmlspecialchars($t['name']) ?></td>
      <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;color:#555;font-size:.9em"><?= $t['time'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php if (!empty($missing)): ?>
  <h3 style="color:#842029;border-bottom:1px solid #f8d7da;padding-bottom:6px">✗ Niewykonane (<?= $totalMissing ?>)</h3>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <?php foreach ($missing as $name): ?>
    <tr>
      <td style="padding:8px 0;border-bottom:1px solid #eee;color:#842029"><?= htmlspecialchars($name) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <p style="color:#aaa;font-size:.8em;margin-top:32px;border-top:1px solid #eee;padding-top:12px">
    Raport wygenerowany automatycznie — <?= $dateFormatted ?> | System Zadań
  </p>
</body>
</html>
<?php
$html = ob_get_clean();

// Wyślij mail
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    foreach (array_filter(array_map('trim', explode(',', REPORT_TO))) as $addr) {
        $mail->addAddress($addr);
    }

    $mail->isHTML(true);
    $mail->Subject = "Raport zadań – $dateFormatted ($totalDone/$totalAll wykonanych)";
    $mail->Body    = $html;
    $mail->AltBody = "Wykonane: $totalDone/$totalAll\nNiewykonane: $totalMissing";

    $mail->send();
    $db->prepare("
        INSERT INTO logs (task_id, task_name, action, date, logged_at)
        VALUES (0, :name, 'report_sent', :date, NOW())
    ")->execute([
        ':name' => "Raport wysłany do " . REPORT_TO,
        ':date' => $date,
    ]);
    echo "[" . date('Y-m-d H:i:s') . "] Raport wysłany do " . REPORT_TO . "\n";
} catch (Exception $e) {
    $db->prepare("
        INSERT INTO logs (task_id, task_name, action, date, logged_at)
        VALUES (0, :name, 'report_failed', :date, NOW())
    ")->execute([
        ':name' => "Błąd wysyłki raportu: " . $mail->ErrorInfo,
        ':date' => $date,
    ]);
    echo "[" . date('Y-m-d H:i:s') . "] Błąd wysyłki: " . $mail->ErrorInfo . "\n";
}
