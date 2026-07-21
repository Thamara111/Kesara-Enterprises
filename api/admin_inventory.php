<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid ID."]);
    exit;
}

$admin_id = $_SESSION['admin_id'];

if ($action === 'adjust_stock') {
    $adj_type = $input['type'] ?? 'add';
    $qty = (int)($input['qty'] ?? 0);
    $note = $input['note'] ?? 'Manual adjustment';
    $newStock = (int)($input['newStock'] ?? 0);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT product_id, quantity FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        
        if (!$inv) {
            throw new Exception("Inventory item not found.");
        }

        $product_id = (int)$inv['product_id'];
        $qty_before = (int)$inv['quantity'];

        $up_stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $up_stmt->execute([$newStock, $id]);

        $log_stmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, adj_type, qty_before, qty_after, note, admin_id) VALUES (?, ?, ?, ?, ?, ?)");
        $log_stmt->execute([$id, $adj_type, $qty_before, $newStock, $note, $admin_id]);

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

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Stock adjusted successfully."]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} elseif ($action === 'update_thresh') {
    $thresh = (int)($input['thresh'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET restock_min = ? WHERE id = ?");
        $stmt->execute([$thresh, $id]);
        echo json_encode(["status" => "success", "message" => "Threshold updated successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
