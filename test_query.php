<?php
require 'database/connection.php';
try {
    $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.whatsapp_number, u.business_name AS company, u.business_type AS type, u.br_number AS br, u.address AS addr, u.status, u.created_at FROM users u");
    echo "Customers Success!\n";
} catch(Exception $e) {
    echo "Customers Error: " . $e->getMessage() . "\n";
}
try {
    $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS descr, p.images, p.colors FROM products p LEFT JOIN categories c ON p.category_id = c.id");
    echo "Products Success!\n";
} catch(Exception $e) {
    echo "Products Error: " . $e->getMessage() . "\n";
}
