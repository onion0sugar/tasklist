<?php
// Run once on existing installation, then delete this file.
require 'config.php';

$db = getDB();

// Add 'action' column if missing
$db->exec("ALTER TABLE logs ADD COLUMN IF NOT EXISTS action VARCHAR(30) NOT NULL DEFAULT 'completed' AFTER task_name;");

// Rename completed_at -> logged_at if old column still exists
$cols = $db->query("SHOW COLUMNS FROM logs LIKE 'completed_at'")->fetchAll();
if (!empty($cols)) {
    $db->exec("ALTER TABLE logs CHANGE completed_at logged_at DATETIME NOT NULL;");
}

// Add logged_at if neither column exists yet
$cols2 = $db->query("SHOW COLUMNS FROM logs LIKE 'logged_at'")->fetchAll();
if (empty($cols2)) {
    $db->exec("ALTER TABLE logs ADD COLUMN logged_at DATETIME NOT NULL DEFAULT NOW();");
}

echo "Migracja zakończona. Usuń plik migrate.php z serwera.";
