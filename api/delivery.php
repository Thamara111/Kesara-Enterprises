<?php
/**
 * Delivery Assignments API
 * Handles assigning orders to delivery personnel (drivers) and tracking assignment status updates.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Checking session -> Starting user session to track active admin
session_start();
// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Processing request -> Handling POST request for creating delivery assignments
if ($method === 'POST') {
    // Getting data -> Reading JSON payload or POST array
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    // Getting data -> Reading requested action parameter
    $action = $_GET['action'] ?? '';

    // Saving data -> Creating a new delivery assignment for driver
    if ($action === 'create_assignment') {
        // Getting data -> Extracting driver ID, order IDs list, and notes
        $driver_id = (int)($input['driver_id'] ?? 0);
        $orders = $input['orders'] ?? [];
        $notes = $input['notes'] ?? '';
        
        // Getting data -> Getting logged in admin user ID for audit log
        $user_id = $_SESSION['user_id'] ?? 1;

        // Checking data -> Ensuring a driver is selected and order list is not empty
        if ($driver_id > 0 && !empty($orders)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    // Database transaction -> Starting transaction to save all assignment updates together
                    $pdo->beginTransaction();

                    // Preparing queries -> Preparing statements to insert assignment record, update order status, and log audit trail
                    $stmt_assign = $pdo->prepare("INSERT INTO delivery_assignments (order_id, personnel_id, status, notes) VALUES (?, ?, 'pending', ?)");
                    $stmt_update_order = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
                    $stmt_log = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'assigned', 'Order assigned to delivery personnel.', ?)");

                    // Updating data -> Assigning each order in list to selected driver
                    foreach ($orders as $formatted_id) {
                        // Formatting data -> Extracting numeric ID from formatted order string
                        $parts = explode('-', $formatted_id);
                        $order_id = (int)end($parts);

                        if ($order_id > 0) {
                            $stmt_assign->execute([$order_id, $driver_id, $notes]);
                            $stmt_update_order->execute([$order_id]);
                            $stmt_log->execute([$order_id, $user_id]);
                        }
                    }
                    // Database transaction -> Committing transaction after all assignments are saved
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Delivery assignment created successfully."]);
                } catch (\Exception $e) {
                    // Handling errors -> Rolling back transaction if an exception occurs
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "DB error: " . $e->getMessage()]);
                }
            } else {
                // Fallback -> Returning success response in demo mode
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Demo Mode"]);
            }
        } else {
            // Checking data -> Returning error if driver or order list is invalid
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid driver or missing orders."]);
        }
        exit;
    }
}

// Checking request -> Blocking non-POST requests with Method Not Allowed error
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);


/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/delivery.php (Delivery Dispatch & Status Update API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - driver_portal/index.php
   - admin/view/orders.view.php
   - admin/view/delivery.assignments.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Driver Portal (driver_portal/index.php) and Admin Order Dispatch
=============================================================================
*/

