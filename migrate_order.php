<?php
// Run ONCE on existing installation, then DELETE this file from server.
require 'config.php';

$db = getDB();

// 1. Dodaj kolumnę sort_order jeśli nie istnieje
$cols = $db->query("SHOW COLUMNS FROM tasks LIKE 'sort_order'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE tasks ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER location_id");
    echo "✓ Kolumna sort_order dodana.<br>";
} else {
    echo "· Kolumna sort_order już istnieje, pomijam.<br>";
}

// 2. Inicjalizuj sort_order wg aktualnych ID (zachowanie dotychczasowej kolejności)
$db->exec("UPDATE tasks SET sort_order = id WHERE sort_order = 0");
echo "✓ Inicjalizacja sort_order zakończona.<br>";

echo "<br><strong style='color:red'>Usuń plik migrate_order.php z serwera!</strong>";
