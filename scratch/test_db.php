<?php
require_once __DIR__ . '/../database/connection.php';

if (!$pdo) {
    echo "DB Connection failed: " . ($db_error ?? 'Unknown error') . "\n";
    exit(1);
}

echo "DB Connected successfully!\n";

$stmt = $pdo->query("SELECT id, first_name, last_name, email, password, status FROM users");
$users = $stmt->fetchAll();

echo "Found " . count($users) . " users:\n";
foreach ($users as $u) {
    echo "ID: {$u['id']} | Name: {$u['first_name']} {$u['last_name']} | Email: '{$u['email']}' | Status: '{$u['status']}' | PassHash: {$u['password']}\n";
}
