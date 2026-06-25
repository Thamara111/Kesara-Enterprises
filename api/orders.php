<?php
/**
 * REST API - Orders
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return all orders
    $orders = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT o.*, u.business_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
            $orders = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Ignore
        }
    }
    echo json_encode(["status" => "success", "data" => $orders]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    // Determine User ID (fallback to 1 if not logged in)
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 1;
    $total_amount = (float)($input['total_amount'] ?? 0);
    $items = $input['items'] ?? [];

    if ($total_amount <= 0 || empty($items)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid order total or items list."]);
        exit;
    }

    if (isset($pdo) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // 1. Insert into orders
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount) VALUES (?, 'pending', ?)");
            $stmt->execute([$user_id, $total_amount]);
            $order_id = $pdo->lastInsertId();

            // 2. Insert order items
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);

                if ($pid > 0 && $qty > 0 && $price > 0) {
                    $stmt_item->execute([$order_id, $pid, $qty, $price]);
                }
            }

            // 3. Create log
            $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'pending', 'Order placed from public website.', ?)");
            $log_stmt->execute([$order_id, $user_id]);

            $pdo->commit();

            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Order placed successfully.", "order_id" => $order_id]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Order placed successfully (Demo Mode).", "order_id" => rand(100, 999)]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
