<?php
require_once __DIR__ . '/connection.php';

try {
    if (!$pdo) {
        die("No PDO connection");
    }
    
    $colCheck = $pdo->query("SHOW COLUMNS FROM driver_leaves LIKE 'notified'");
    if (!$colCheck->fetch()) {
        $sql = "ALTER TABLE driver_leaves ADD COLUMN notified BOOLEAN DEFAULT FALSE AFTER status;";
        $pdo->exec($sql);
        echo "Column notified added successfully.\n";
    } else {
        echo "Column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error altering table: " . $e->getMessage() . "\n";
}
