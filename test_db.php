<?php
require __DIR__ . '/admin/../database/connection.php';
echo "DB Error: " . $db_error . "\n";
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        echo "Users count: " . $stmt->fetchColumn() . "\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        echo "Products count: " . $stmt->fetchColumn() . "\n";
    } catch (Exception $e) {
        echo "Query Error: " . $e->getMessage() . "\n";
    }
}
