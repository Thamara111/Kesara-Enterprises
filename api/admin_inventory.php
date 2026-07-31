<?php
/**
 * Admin Inventory Management API
 * Handles adjusting stock quantities, logging inventory adjustments, updating stock status, and setting restock threshold levels.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Checking data -> Making sure admin user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

// Checking request -> Ensuring request method is POST
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Getting data -> Reading JSON request payload for action and target inventory ID
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$id = (int)($input['id'] ?? 0);

// Checking data -> Validating that inventory ID is positive
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid ID."]);
    exit;
}

// Getting data -> Getting logged in admin ID for audit trail
$admin_id = $_SESSION['admin_id'];

// Updating stock -> Adjusting inventory stock quantity and logging changes
if ($action === 'adjust_stock') {
    $adj_type = $input['type'] ?? 'add';
    $qty = (int)($input['qty'] ?? 0);
    $note = $input['note'] ?? 'Manual adjustment';
    $newStock = (int)($input['newStock'] ?? 0);

    try {
        // Database transaction -> Starting transaction for safe stock update
        $pdo->beginTransaction();

        // Getting data -> Fetching current product ID and existing quantity
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        
        if (!$inv) {
            throw new Exception("Inventory item not found.");
        }

        $product_id = (int)$inv['product_id'];
        $qty_before = (int)$inv['quantity'];

        // Updating data -> Updating new stock quantity in inventory table
        $up_stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $up_stmt->execute([$newStock, $id]);

        // Saving log -> Recording adjustment details into inventory_log
        $log_stmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, adj_type, qty_before, qty_after, note, admin_id) VALUES (?, ?, ?, ?, ?, ?)");
        $log_stmt->execute([$id, $adj_type, $qty_before, $newStock, $note, $admin_id]);

        // Updating status -> Recalculating overall product availability status (In Stock, Low Stock, Out of Stock)
        $up_status_stmt = $pdo->prepare("
            UPDATE products 
            SET status = CASE 
                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) = 0 THEN 'Out of Stock'
                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) <= 50 THEN 'Low Stock'
                ELSE 'In Stock'
            END
            WHERE id = ?
        ");
        $up_status_stmt->execute([$product_id]);

        // Database transaction -> Committing transaction
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Stock adjusted successfully."]);
    } catch (\Exception $e) {
        // Handling errors -> Rolling back transaction on error
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} 
// Updating threshold -> Updating minimum restock threshold level for inventory item
elseif ($action === 'update_thresh') {
    $thresh = (int)($input['thresh'] ?? 0);
    
    try {
        // Updating data -> Saving new restock minimum limit to database
        $stmt = $pdo->prepare("UPDATE inventory SET restock_min = ? WHERE id = ?");
        $stmt->execute([$thresh, $id]);
        echo json_encode(["status" => "success", "message" => "Threshold updated successfully."]);
    } catch (\Exception $e) {
        // Handling errors -> Returning error response on database failure
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} 
// Checking data -> Returning error if action is unrecognized
else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/admin_inventory.php (Admin Per-Variant Inventory Stock API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - admin/view/inventory.view.php
   - product_detail.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Admin Inventory Manager (admin/view/inventory.view.php) and Product Details Stock checks
=============================================================================
*/

