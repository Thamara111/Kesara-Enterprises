<?php
require 'database/connection.php';
try {
    $stmt = $pdo->query("SELECT i.id, i.product_id, i.size, i.colour, i.quantity AS stock, i.restock_min AS thresh, p.name AS product_name, p.sku 
                         FROM inventory i 
                         JOIN products p ON i.product_id = p.id");
    print_r($stmt->fetchAll());
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
