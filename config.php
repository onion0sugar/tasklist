<?php
date_default_timezone_set('Europe/Warsaw');

define('DB_HOST', 'localhost');
define('DB_NAME', 'tasklist');
define('DB_USER', 'tasklist_user');
define('DB_PASS', 'pass_db');
define('APP_URL', 'http://192.168.1.1/tasklist');

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'pass_admin');

define('MANAGER_USER', 'manager');
define('MANAGER_PASS', 'pass_manager');

// SMTP – konfiguracja własnej skrzynki
define('SMTP_HOST',       'smtp.example.com');   // smtp.gmail.com 
define('SMTP_PORT',       587);                  // 587 (TLS) / 465 (SSL)
define('SMTP_ENCRYPTION', 'tls');                // 'tls' / 'ssl'
define('SMTP_USER',       'raport@example.com'); // login do skrzynki
define('SMTP_PASS',       'haslo_skrzynki');     // hasło do skrzynki
define('SMTP_FROM_NAME',  'System Zadań');       // nazwa nadawcy
define('REPORT_TO',       'odbiorca@example.com'); // adres(y) docelowy raportu — wiele rozdziel przecinkami 

function checkAutoLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin']) && empty($_SESSION['manager']) && isset($_COOKIE['remember_auth'])) {
        $parts = explode(':', $_COOKIE['remember_auth'], 2);
        if (count($parts) === 2) {
            [$role, $hash] = $parts;
            if ($role === 'admin') {
                $expected = hash('sha256', 'admin:' . ADMIN_USER . ':' . ADMIN_PASS);
                if (hash_equals($expected, $hash)) {
                    $_SESSION['admin'] = true;
                }
            } elseif ($role === 'manager') {
                $expected = hash('sha256', 'manager:' . MANAGER_USER . ':' . MANAGER_PASS);
                if (hash_equals($expected, $hash)) {
                    $_SESSION['manager'] = true;
                }
            }
        }
    }
}

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    checkAutoLogin();
    if (empty($_SESSION['admin'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireManager(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    checkAutoLogin();
    if (empty($_SESSION['admin']) && empty($_SESSION['manager'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
