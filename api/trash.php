<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$table = $input['table'] ?? '';
$id = (int)($input['id'] ?? 0);

$allowed_tables = ['products', 'categories', 'orders', 'admins', 'users'];

if (!in_array($table, $allowed_tables) || $id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

try {
    if ($action === 'restore') {
        if ($table === 'users') {
            $stmt = $pdo->prepare("UPDATE `$table` SET status = 'approved' WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE `$table` SET deleted_at = NULL WHERE id = ?");
        }
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "Restored successfully."]);
    } elseif ($action === 'hard_delete') {
        if ($table === 'products') {
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
