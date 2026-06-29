<?php
require 'database/connection.php';

$tables = ['products', 'categories', 'orders', 'admins'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'deleted_at'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
            echo "Added deleted_at to $table\n";
        } else {
            echo "Column deleted_at already exists in $table\n";
        }
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
