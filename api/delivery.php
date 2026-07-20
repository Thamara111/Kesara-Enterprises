<?php
/**
 * REST API - Delivery Assignments
 * Handles assigning wholesale orders to specific delivery personnel (drivers).
 * Tracks changes in the order status log.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Start session to track which admin is making the assignment
session_start();
// Include centralized database connection
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Handle POST requests for creating assignments
if ($method === 'POST') {
    // Parse JSON payload or fallback to POST array
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    // Determine the exact action requested via query string
    $action = $_GET['action'] ?? '';

    // Flow for creating a new delivery assignment
    if ($action === 'create_assignment') {
        // Extract assignment details
        $driver_id = (int)($input['driver_id'] ?? 0);
        $orders = $input['orders'] ?? []; // Array of formatted order IDs (e.g., 'KE-2025-00123')
        $notes = $input['notes'] ?? '';
        
        // Track which user/admin is making this change (defaulting to 1 if session lost)
        $user_id = $_SESSION['user_id'] ?? 1;

        // Ensure a valid driver is selected and there is at least one order
        if ($driver_id > 0 && !empty($orders)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    // Start a transaction to ensure all database changes succeed or fail together
                    $pdo->beginTransaction();

                    // Prepare statements for bulk execution:
                    // 1. Insert the assignment record
                    $stmt_assign = $pdo->prepare("INSERT INTO delivery_assignments (order_id, personnel_id, status, notes) VALUES (?, ?, 'pending', ?)");
                    // 2. Update the parent order's status to reflect the assignment
                    $stmt_update_order = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
                    // 3. Keep an audit trail in the order_status_log
                    $stmt_log = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'assigned', 'Order assigned to delivery personnel.', ?)");

                    // Loop through each provided order ID and execute the prepared statements
                    foreach ($orders as $formatted_id) {
                        // Extract numeric ID from formatted string (e.g. KE-2025-123 -> 123)
                        $parts = explode('-', $formatted_id);
                        $order_id = (int)end($parts);

                        if ($order_id > 0) {
                            $stmt_assign->execute([$order_id, $driver_id, $notes]);
                            $stmt_update_order->execute([$order_id]);
                            $stmt_log->execute([$order_id, $user_id]);
                        }
                    }
                    // If all iterations succeed, commit the transaction to the database
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Delivery assignment created successfully."]);
                } catch (\Exception $e) {
                    // In case of an error, rollback all changes made during this transaction
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "DB error: " . $e->getMessage()]);
                }
            } else {
                // Fallback for demo environments without DB access
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Demo Mode"]);
            }
        } else {
            // Validation error when inputs are incomplete
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid driver or missing orders."]);
        }
        exit;
    }
}

// Block any non-POST requests with a 405 Method Not Allowed error
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
