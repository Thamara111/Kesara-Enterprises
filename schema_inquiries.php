<?php
require 'database/connection.php';
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t) {
    echo "TABLE: $t\n";
    if ($t === 'inquiries') {
        $stmt2 = $pdo->query("DESCRIBE `$t`");
        print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));
    }
}
