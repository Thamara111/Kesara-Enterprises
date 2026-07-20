<?php
/**
 * REST API - Delivery Assignments
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'create_assignment') {
        $driver_id = (int)($input['driver_id'] ?? 0);
        $orders = $input['orders'] ?? []; // Array of formatted IDs like 'KE-2025-00123'
        $notes = $input['notes'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 1;

        if ($driver_id > 0 && !empty($orders)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    $pdo->beginTransaction();

                    $stmt_assign = $pdo->prepare("INSERT INTO delivery_assignments (order_id, personnel_id, status, notes) VALUES (?, ?, 'pending', ?)");
                    $stmt_update_order = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
                    $stmt_log = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'assigned', 'Order assigned to delivery personnel.', ?)");

                    foreach ($orders as $formatted_id) {
                        // Extract numeric ID
                        $parts = explode('-', $formatted_id);
                        $order_id = (int)end($parts);

                        if ($order_id > 0) {
                            $stmt_assign->execute([$order_id, $driver_id, $notes]);
                            $stmt_update_order->execute([$order_id]);
                            $stmt_log->execute([$order_id, $user_id]);
                        }
                    }
                    
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Delivery assignment created successfully."]);
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "DB error: " . $e->getMessage()]);
                }
            } else {
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Demo Mode"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid driver or missing orders."]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
