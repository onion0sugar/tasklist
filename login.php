<?php
require 'config.php';
session_start();

if (!empty($_SESSION['admin'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    $error = 'Nieprawidłowy login lub hasło.';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie</title>
<style>
  body { font-family: sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 32px; width: 100%; max-width: 320px; }
  h1 { margin: 0 0 24px; font-size: 1.3em; }
  label { display: block; margin-bottom: 4px; font-size: .85em; color: #555; }
  input[type=text], input[type=password] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 16px; font-size: 1em; box-sizing: border-box; }
  button { width: 100%; padding: 10px; background: #333; color: #fff; border: none; border-radius: 4px; font-size: 1em; cursor: pointer; }
  .error { color: #c00; margin-bottom: 16px; font-size: .9em; }
</style>
</head>
<body>
<div class="box">
  <h1>Panel admina</h1>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Login</label>
    <input type="text" name="username" autocomplete="username" required>
    <label>Hasło</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Zaloguj</button>
  </form>
</div>
</body>
</html>
