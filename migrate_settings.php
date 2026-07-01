<?php
// Run ONCE on existing installation, then DELETE this file from server.
require 'config.php';

try {
    $db = getDB();
    $db->exec("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key   VARCHAR(255) PRIMARY KEY,
        setting_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✓ Tabela settings została utworzona.<br>";
} catch (Exception $e) {
    echo "❌ Błąd: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<br><strong style='color:red'>Usuń plik migrate_settings.php z serwera!</strong>";
