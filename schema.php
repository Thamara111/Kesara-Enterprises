<?php
require 'database/connection.php';
$stmt = $pdo->query('DESCRIBE products');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
