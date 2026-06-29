<?php
require 'database/connection.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mock_whatsapp_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        phone VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'delivered',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "mock_whatsapp_messages table created.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
