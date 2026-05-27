<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tasklist');
define('DB_USER', 'tasklist_user');
define('DB_PASS', 'zmien_haslo_db');
define('APP_URL', 'http://192.168.24.90/tasklist');

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'zmien_haslo_admina');

// SMTP – konfiguracja własnej skrzynki
define('SMTP_HOST',       'smtp.example.com');   // np. smtp.gmail.com / smtp.o2.pl
define('SMTP_PORT',       587);                  // 587 (TLS) lub 465 (SSL)
define('SMTP_ENCRYPTION', 'tls');                // 'tls' lub 'ssl'
define('SMTP_USER',       'raport@example.com'); // login do skrzynki
define('SMTP_PASS',       'haslo_skrzynki');     // hasło do skrzynki
define('SMTP_FROM_NAME',  'System Zadań');       // nazwa nadawcy
define('REPORT_TO',       'odbiorca@example.com'); // adres(y) docelowy raportu — wiele rozdziel przecinkami

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin'])) {
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
    }
    return $pdo;
}
