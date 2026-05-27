<?php
// Fresh install only — run once, then delete this file.
require 'config.php';

$db = getDB();

$db->exec("
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS daily_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    date DATE NOT NULL,
    status TINYINT DEFAULT 0,
    UNIQUE KEY uq_task_date (task_id, date),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    action VARCHAR(30) NOT NULL DEFAULT 'completed',
    date DATE NOT NULL,
    logged_at DATETIME NOT NULL,
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "Tabele utworzone. Usuń plik setup.php z serwera.";
