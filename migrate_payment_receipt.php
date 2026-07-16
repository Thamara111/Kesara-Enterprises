<?php
require 'database/connection.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'payment_receipt'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_receipt` VARCHAR(255) DEFAULT NULL");
        echo "Successfully added payment_receipt column to orders table.\n";
    } else {
        echo "Column payment_receipt already exists in orders table.\n";
    }
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
