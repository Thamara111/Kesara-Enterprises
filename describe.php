<?php
require 'database/connection.php';
$stmt = $pdo->query('DESCRIBE delivery_assignments');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
