<?php
require 'database/connection.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `inquiries` LIKE 'assigned_to'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `inquiries` ADD COLUMN `assigned_to` INT NULL");
        echo "Added assigned_to\n";
    }
    
    $stmt2 = $pdo->query("SHOW COLUMNS FROM `inquiries` LIKE 'status'");
    if (!$stmt2->fetch()) {
        $pdo->exec("ALTER TABLE `inquiries` ADD COLUMN `status` VARCHAR(50) DEFAULT 'pending'");
        echo "Added status\n";
    }
    echo "Done";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
