<?php
/**
 * Delivery Addresses API
 * Helps manage customer delivery addresses like adding, listing, updating, deleting, and setting default address.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Getting data -> Checking logged in user session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

// Getting data -> Making sure user_addresses table exists in database
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) DEFAULT NULL,
            province VARCHAR(100) DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Getting data -> Checking if user has any addresses, and adding initial default address if empty
        $checkCount = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
        $checkCount->execute([$user_id]);
        if ($checkCount->fetchColumn() == 0) {
            $uStmt = $pdo->prepare("SELECT business_name, address FROM users WHERE id = ?");
            $uStmt->execute([$user_id]);
            $uData = $uStmt->fetch();
            if ($uData && !empty($uData['address'])) {
                $seedTitle = !empty($uData['business_name']) ? $uData['business_name'] . ' Warehouse' : 'Primary Address';
                $pdo->prepare("INSERT INTO user_addresses (user_id, title, address, is_default) VALUES (?, ?, ?, 1)")
                    ->execute([$user_id, $seedTitle, $uData['address']]);
            }
        }
    } catch (\Exception $e) {
        // Ignore database table setup errors if already set up
    }
}

// Getting data -> Reading request payload from JSON input or POST form data
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

// Getting data -> Getting action parameter from request
$action = trim($input['action'] ?? 'list');

try {
    // Getting data -> Getting all saved delivery addresses for this user
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $stmt->execute([$user_id]);
        $addresses = $stmt->fetchAll();
        echo json_encode(["status" => "success", "addresses" => $addresses]);
        exit;
    }

    // Saving data -> Adding a new address or updating existing delivery address
    if ($action === 'save') {
        $address_id = (int)($input['address_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $address = trim($input['address'] ?? '');
        $city = trim($input['city'] ?? '');
        $province = trim($input['province'] ?? '');
        $postal_code = trim($input['postal_code'] ?? '');
        $is_default = !empty($input['is_default']) ? 1 : 0;

        // Checking data -> Ensuring title and street address are not empty
        if (empty($title) || empty($address)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Title and street address are required."]);
            exit;
        }

        // Getting data -> Counting total saved addresses for this user
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
        $cntStmt->execute([$user_id]);
        $totalCount = (int)$cntStmt->fetchColumn();

        if ($totalCount === 0) {
            $is_default = 1;
        }

        if ($is_default == 1) {
            // Updating data -> Removing default status from previous default address
            $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
        }

        if ($address_id > 0) {
            // Updating data -> Updating existing address in database
            $stmt = $pdo->prepare("UPDATE user_addresses SET title = ?, address = ?, city = ?, province = ?, postal_code = ?, is_default = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $address, $city, $province, $postal_code, $is_default, $address_id, $user_id]);
            $message = "Address updated successfully.";
        } else {
            // Saving data -> Inserting new address into database
            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, title, address, city, province, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $address, $city, $province, $postal_code, $is_default]);
            $address_id = $pdo->lastInsertId();
            $message = "Address added successfully.";
        }

        // Updating data -> Updating main user profile address if set as default
        if ($is_default == 1) {
            $fullAddr = $address;
            if ($city) $fullAddr .= "\n" . $city;
            if ($province) $fullAddr .= ", " . $province;
            if ($postal_code) $fullAddr .= " " . $postal_code;
            $pdo->prepare("UPDATE users SET address = ? WHERE id = ?")->execute([$fullAddr, $user_id]);
        }

        echo json_encode(["status" => "success", "message" => $message, "address_id" => $address_id]);
        exit;
    }

    // Setting data -> Changing default delivery address
    if ($action === 'set_default') {
        $address_id = (int)($input['address_id'] ?? 0);
        if ($address_id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid address ID."]);
            exit;
        }

        // Checking data -> Verifying address exists and belongs to logged in user
        $chk = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
        $chk->execute([$address_id, $user_id]);
        $target = $chk->fetch();
        if (!$target) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Address not found."]);
            exit;
        }

        // Updating data -> Removing default flag from all other addresses for this user
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
        // Updating data -> Setting selected address as default
        $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$address_id, $user_id]);

        // Updating data -> Syncing selected default address to primary user profile
        $fullAddr = $target['address'];
        if ($target['city']) $fullAddr .= "\n" . $target['city'];
        if ($target['province']) $fullAddr .= ", " . $target['province'];
        if ($target['postal_code']) $fullAddr .= " " . $target['postal_code'];
        $pdo->prepare("UPDATE users SET address = ? WHERE id = ?")->execute([$fullAddr, $user_id]);

        echo json_encode(["status" => "success", "message" => "Default address updated successfully."]);
        exit;
    }

    // Deleting data -> Removing delivery address
    if ($action === 'delete') {
        $address_id = (int)($input['address_id'] ?? 0);
        if ($address_id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid address ID."]);
            exit;
        }

        // Checking data -> Checking if target address is default
        $chk = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
        $chk->execute([$address_id, $user_id]);
        $row = $chk->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Address not found."]);
            exit;
        }

        // Deleting data -> Deleting address record from database
        $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$address_id, $user_id]);

        // Updating data -> Promoting latest remaining address as new default if deleted address was default
        if ($row['is_default'] == 1) {
            $rem = $pdo->prepare("SELECT id, address, city, province, postal_code FROM user_addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $rem->execute([$user_id]);
            $nextDef = $rem->fetch();
            if ($nextDef) {
                $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?")->execute([$nextDef['id']]);
                $fullAddr = $nextDef['address'];
                if ($nextDef['city']) $fullAddr .= "\n" . $nextDef['city'];
                if ($nextDef['province']) $fullAddr .= ", " . $nextDef['province'];
                if ($nextDef['postal_code']) $fullAddr .= " " . $nextDef['postal_code'];
                $pdo->prepare("UPDATE users SET address = ? WHERE id = ?")->execute([$fullAddr, $user_id]);
            }
        }

        echo json_encode(["status" => "success", "message" => "Address deleted successfully."]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/addresses.php (Customer Address Book API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - my_account.php
   - checkout.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Address management in my_account.php and checkout.php delivery selection
=============================================================================
*/
