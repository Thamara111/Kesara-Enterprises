<?php
$pdo = new PDO('mysql:host=localhost;dbname=kesara_db', 'root', '');
$stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Before: " . $col['Type'] . "\n";

$pdo->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'assigned', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
$pdo->exec("UPDATE orders SET status = 'assigned' WHERE status = ''");

$stmt2 = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'");
$col2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "After: " . $col2['Type'] . "\n";
echo "Fixed!";
?>
