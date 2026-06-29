<?php
require 'database/connection.php';
$stmt = $pdo->query('SHOW TABLES');
while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $r[0] . PHP_EOL;
}
