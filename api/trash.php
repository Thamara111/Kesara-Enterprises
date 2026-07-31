<?php
/**
 * Recycle Bin & Trash API
 * Manages restoring deleted records or permanently deleting soft-deleted items across products, categories, orders, and users.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Checking permission -> Making sure logged in user has admin privileges
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

// Getting data -> Reading JSON payload for action, target table, and record ID
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$table = $input['table'] ?? '';
$id = (int)($input['id'] ?? 0);

// Checking data -> Restricting operations to allowed database tables only
$allowed_tables = ['products', 'categories', 'orders', 'admins', 'users', 'suppliers'];

if (!in_array($table, $allowed_tables) || $id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

try {
    if ($action === 'restore') {
        // Restoring data -> Restoring soft-deleted record
        if ($table === 'users') {
            // Restoring data -> Resetting user account status to approved
            $stmt = $pdo->prepare("UPDATE `$table` SET status = 'approved' WHERE id = ?");
        } else {
            // Restoring data -> Clearing deleted_at timestamp to restore record
            $stmt = $pdo->prepare("UPDATE `$table` SET deleted_at = NULL WHERE id = ?");
        }
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Restored successfully."]);
        
    } elseif ($action === 'hard_delete') {
        // Deleting data -> Permanently deleting record from database
        if ($table === 'products') {
            // Deleting data -> Deleting associated pricing tiers for product
            $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?")->execute([$id]);
        } elseif ($table === 'suppliers') {
            // Deleting data -> Deleting associated supplier items and products relations
            $pdo->prepare("DELETE FROM supplier_items WHERE supplier_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM supplier_products WHERE supplier_id = ?")->execute([$id]);
        }
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Permanently deleted."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/trash.php (Soft Delete & Restore Operations API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - admin/view/trash.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Admin Trash & Recovery Center (admin/view/trash.view.php)
=============================================================================
*/

