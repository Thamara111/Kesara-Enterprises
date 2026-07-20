<?php
/**
 * Trash API
 * Manages the restoration and permanent deletion (hard delete) of soft-deleted records.
 * Accessible only by administrators.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Security Check: Verify that the user is logged in and has the 'admin' role
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

// Parse the JSON request body
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$table = $input['table'] ?? '';
$id = (int)($input['id'] ?? 0);

// Restrict operations to a specific whitelist of tables to prevent SQL injection or unintended table modifications
$allowed_tables = ['products', 'categories', 'orders', 'admins', 'users'];

if (!in_array($table, $allowed_tables) || $id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

try {
    if ($action === 'restore') {
        // Handle restoration of soft-deleted records
        if ($table === 'users') {
            // Users use a 'status' column instead of deleted_at for their soft deletes
            $stmt = $pdo->prepare("UPDATE `$table` SET status = 'approved' WHERE id = ?");
        } else {
            // Most tables use the 'deleted_at' timestamp
            $stmt = $pdo->prepare("UPDATE `$table` SET deleted_at = NULL WHERE id = ?");
        }
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Restored successfully."]);
        
    } elseif ($action === 'hard_delete') {
        // Handle permanent deletion of records from the database
        if ($table === 'products') {
            // Clean up related pricing tiers before deleting a product to maintain referential integrity
            $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?")->execute([$id]);
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
