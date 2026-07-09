<?php
require_once __DIR__ . "/connection.php";

if (isset($pdo) && $pdo !== null) {
    try {
        // Add columns if they do not exist
        $pdo->exec("ALTER TABLE delivery_personnel ADD COLUMN email VARCHAR(255) NULL UNIQUE AFTER name");
        $pdo->exec("ALTER TABLE delivery_personnel ADD COLUMN password VARCHAR(255) NULL AFTER email");
        echo "Columns email and password added successfully.\n";
        
        // Update existing drivers
        $stmt = $pdo->query("SELECT id, name FROM delivery_personnel");
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hash = password_hash('driver123', PASSWORD_BCRYPT);
        $update_stmt = $pdo->prepare("UPDATE delivery_personnel SET email = ?, password = ? WHERE id = ?");
        
        foreach ($drivers as $d) {
            $name_parts = explode(' ', strtolower($d['name']));
            $email = $name_parts[0] . "@kesara.lk";
            $update_stmt->execute([$email, $hash, $d['id']]);
            echo "Updated driver {$d['name']} with email $email\n";
        }
        
    } catch (\Exception $e) {
        echo "Error or already run: " . $e->getMessage() . "\n";
    }
} else {
    echo "No DB Connection.\n";
}
