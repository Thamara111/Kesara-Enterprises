<?php
/**
 * REST API - Orders
 * Handles fetching order history, updating order statuses, and processing new orders.
 * Integrates with inventory reduction, email notifications, and file uploads (receipts).
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Resume user session and connect to database
session_start();
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET requests: Fetch order history
if ($method === 'GET') {
    // Return all orders across the platform (typically for admin use)
    $orders = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            // Join with users table to get the business_name associated with each order
            $stmt = $pdo->query("SELECT o.*, u.business_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
            $orders = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Ignore exceptions to fall back to an empty array
        }
    }
    // Return JSON response with the order data
    echo json_encode(["status" => "success", "data" => $orders]);
    exit;
}

// Handle POST requests: New Orders & Status Updates
if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    // Parse the input data
    // If the Content-Type is multipart/form-data (as when uploading file), php://input will be empty
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST; // Fallback to $_POST
        // If items are sent as a JSON string within form-data, decode them
        if (isset($input['items']) && is_string($input['items'])) {
            $input['items'] = json_decode($input['items'], true);
        }
    }

    // Flow for Admins to update the status of an existing order
    if ($action === 'update_status') {
        $order_id = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 1;

        // Ensure valid parameters before attempting a database update
        if ($order_id > 0 && !empty($status)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    // Ensure 'cancellation_reason' column exists in orders table
                    $checkCancel = $pdo->query("SHOW COLUMNS FROM orders LIKE 'cancellation_reason'");
                    if (!$checkCancel->fetch()) {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) DEFAULT NULL AFTER status");
                    }

                    $custom_note = trim($input['note'] ?? ($input['cancellation_reason'] ?? ''));

                    // Start transaction to ensure order status and log update together
                    $pdo->beginTransaction();

                    // Check previous status before updating
                    $prev_status_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                    $prev_status_stmt->execute([$order_id]);
                    $prev_status = strtolower($prev_status_stmt->fetchColumn() ?: '');

                    // Restock inventory if order is being cancelled for the first time
                    if ($status === 'cancelled' && $prev_status !== 'cancelled') {
                        $items_stmt = $pdo->prepare("SELECT product_id, quantity, color, size FROM order_items WHERE order_id = ?");
                        $items_stmt->execute([$order_id]);
                        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                        $stmt_inv_check = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? AND size = ? AND colour = ? LIMIT 1");
                        $stmt_inv_fallback = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? LIMIT 1");
                        $stmt_inv_inc   = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                        $stmt_up_status = $pdo->prepare("
                            UPDATE products 
                            SET status = CASE 
                                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) = 0 THEN 'Out of Stock'
                                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) <= 50 THEN 'Low Stock'
                                ELSE 'In Stock'
                            END
                            WHERE id = ?
                        ");

                        $affected_products = [];

                        foreach ($order_items as $item) {
                            $pid   = (int)$item['product_id'];
                            $qty   = (int)$item['quantity'];
                            $color = trim($item['color'] ?? '');
                            $size  = trim($item['size'] ?? '');
                            $inv_id = null;

                            if (!empty($size) && !empty($color)) {
                                $stmt_inv_check->execute([$pid, $size, $color]);
                                $inv_row = $stmt_inv_check->fetch();
                                if ($inv_row) {
                                    $inv_id = $inv_row['id'];
                                }
                            }

                            if (!$inv_id) {
                                $stmt_inv_fallback->execute([$pid]);
                                $inv_row = $stmt_inv_fallback->fetch();
                                if ($inv_row) {
                                    $inv_id = $inv_row['id'];
                                }
                            }

                            if ($inv_id) {
                                $stmt_inv_inc->execute([$qty, $inv_id]);
                                $affected_products[$pid] = true;
                            }
                        }

                        // Recalculate stock statuses for affected products
                        foreach (array_keys($affected_products) as $aff_pid) {
                            $stmt_up_status->execute([$aff_pid]);
                        }
                    }
                    
                    // Update the primary order status
                    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $order_id]);
                    
                    if ($status === 'cancelled' && !empty($custom_note)) {
                        $cancel_stmt = $pdo->prepare("UPDATE orders SET cancellation_reason = ? WHERE id = ?");
                        $cancel_stmt->execute([$custom_note, $order_id]);
                    }

                    if ($status === 'delivered') {
                        $stmt_assign = $pdo->prepare("UPDATE delivery_assignments SET status = 'completed' WHERE order_id = ?");
                        $stmt_assign->execute([$order_id]);
                    }
                    
                    // Generate a human-readable log note based on the new status
                    if (!empty($custom_note)) {
                        $note = $custom_note;
                    } else {
                        $note = "Status updated to " . ucfirst($status) . ".";
                        if ($status === 'processing') $note = "Payment accepted — order is now processing.";
                        if ($status === 'shipped')    $note = "Order dispatched and marked as shipped.";
                        if ($status === 'delivered')  $note = "Delivery confirmed successfully.";
                        if ($status === 'cancelled')  $note = "Order has been cancelled.";
                    }
                    
                    $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
                    $log_stmt->execute([$order_id, $status, $note, $user_id]);
                    
                    // Send an automated email notification to the customer regarding the status change
                    require_once __DIR__ . "/../src/Mailer.php";
                    $user_stmt = $pdo->prepare("SELECT u.email, u.first_name FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ?");
                    $user_stmt->execute([$order_id]);
                    $user_data = $user_stmt->fetch();
                    
                    if ($user_data && $user_data['email']) {
                        $order_ref = "KE-2025-" . str_pad($order_id, 5, '0', STR_PAD_LEFT);
                        if ($status === 'cancelled') {
                            $subject = "Order Cancellation Notice: " . $order_ref;
                            $body = "
                            <p>Dear <strong>" . htmlspecialchars($user_data['first_name']) . "</strong>,</p>
                            <p>We regret to inform you that your order (<strong>" . $order_ref . "</strong>) has been <strong style='color: #dc2626;'>Cancelled</strong>.</p>
                            <div style='background-color: #fef2f2; border: 1px solid #fee2e2; border-left: 4px solid #dc2626; border-radius: 12px; padding: 16px; margin: 16px 0;'>
                                <p style='margin: 0 0 6px 0; font-size: 11px; font-weight: bold; color: #991b1b; text-transform: uppercase; letter-spacing: 0.05em;'>Cancellation Reason / Note:</p>
                                <p style='margin: 0; font-size: 14px; color: #7f1d1d; line-height: 1.5; font-weight: 500;'>" . nl2br(htmlspecialchars($note)) . "</p>
                            </div>
                            <p style='color: #6b7280; font-size: 13px;'>If you have any questions regarding this cancellation, please feel free to reach out to our support team.</p>
                            ";
                        } else {
                            $subject = "Order Status Update: " . ucfirst($status);
                            $body = "
                            <p>Dear <strong>" . htmlspecialchars($user_data['first_name']) . "</strong>,</p>
                            <p>Your order (<strong>" . $order_ref . "</strong>) status has been updated to: <strong style='color: #0F6E56;'>" . ucfirst($status) . "</strong>.</p>
                            " . (!empty($note) ? "<div style='background-color: #f3f4f6; border-left: 4px solid #0F6E56; padding: 12px 16px; margin: 16px 0; border-radius: 4px; font-size: 13px; color: #374151;'><strong>Note:</strong> " . htmlspecialchars($note) . "</div>" : "") . "
                            ";
                        }
                        \App\Mailer::send($user_data['email'], $subject, $body);
                    }

                    // Commit transaction
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Status updated."]);
                } catch (\Exception $e) {
                    // Rollback if anything fails (e.g. email or db error)
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
            echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
        }
        exit;
    }

    // -------------------------------------------------------------
    // Flow for Customers placing a New Order
    // -------------------------------------------------------------

    // Determine the User ID (from session or payload)
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 1;
    $items = $input['items'] ?? [];

    // Ensure the cart isn't empty
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid order items list."]);
        exit;
    }

    // Determine the initial order status based on payment method
    // Bank transfers start as 'pending' to await receipt verification.
    // Card payments start as 'processing' (assuming pre-authorized).
    $payment_method = $input['payment_method'] ?? 'bank';
    $order_status = 'pending';
    if ($payment_method === 'card') {
        $order_status = 'processing';
    }

    // Handle Payment Receipt Image Upload (if present via multipart form)
    $receipt_path = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        // Extract extension and generate a secure random filename
        $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        // Move file and save the relative path to be inserted into the DB
        if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
            $receipt_path = '/uploads/receipts/' . $filename;
        }
    }

    if (isset($pdo) && $pdo !== null) {
        try {
            // Require the model layer validation functions for centralized business logic
            require_once __DIR__ . '/model_validation.php';

            // Validate order items (e.g. MOQ compliance and dynamic Pricing Tiers are resolved server-side)
            $validation = validateOrderItems($pdo, $items);
            if (!empty($validation['errors'])) {
                // Reject the order if items violate MOQ or other rules
                http_response_code(400);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Validation failed: " . implode(" ", $validation['errors'])
                ]);
                exit;
            }

            // Extract the validated secure totals and items
            $total_amount = $validation['calculated_total'];
            $validated_items = $validation['validated_items'];

            /*
            // TASK 06: Wholesale Credit Limit - STEP 3: Backend Credit Limit Enforcer
            $user_stmt = $pdo->prepare("SELECT credit_limit FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_data = $user_stmt->fetch();
            $credit_limit = (float)($user_data['credit_limit'] ?? 0.00);

            if ($credit_limit > 0 && $total_amount > $credit_limit) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Order total (LKR " . number_format($total_amount, 2) . ") exceeds your approved Wholesale Credit Limit (LKR " . number_format($credit_limit, 2) . ")."
                ]);
                exit;
            }
            */

            // Self-healing database: Ensure 'color' and 'size' columns exist in order_items
            $checkOrderColor = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'color'");
            if (!$checkOrderColor->fetch()) {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN color VARCHAR(50) DEFAULT NULL");
                $pdo->exec("ALTER TABLE order_items ADD COLUMN size VARCHAR(50) DEFAULT NULL");
            }

            // Self-healing database: Ensure 'payment_receipt' column exists in orders
            $checkReceipt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_receipt'");
            if (!$checkReceipt->fetch()) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL");
            }

            /*
            // TASK 08: Estimated Delivery Date - STEP 1: Self-Healing DB
            $checkEstDate = $pdo->query("SHOW COLUMNS FROM orders LIKE 'estimated_delivery_date'");
            if (!$checkEstDate->fetch()) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN estimated_delivery_date DATE DEFAULT NULL AFTER cancellation_reason");
            }

            // TASK 08: Estimated Delivery Date - STEP 2: Calculate & Store Estimated Delivery Date
            $estimated_delivery_date = date('Y-m-d', strtotime('+3 weekdays'));
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_receipt, estimated_delivery_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $order_status, $total_amount, $receipt_path, $estimated_delivery_date]);
            */

            // Start a transaction so that if inserting items fails, the order is completely rolled back
            $pdo->beginTransaction();

            // Step 1: Insert the parent record into the `orders` table
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_receipt) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $order_status, $total_amount, $receipt_path]);
            $order_id = $pdo->lastInsertId();

            // Step 2: Prepare statements for inserting items and updating inventory
            $stmt_item      = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, color, size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_inv_check = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? AND size = ? AND colour = ? LIMIT 1");
            $stmt_inv_dec   = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt_up_status = $pdo->prepare("
                UPDATE products 
                SET status = CASE 
                    WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) = 0 THEN 'Out of Stock'
                    WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) <= 50 THEN 'Low Stock'
                    ELSE 'In Stock'
                END
                WHERE id = ?
            ");

            // Loop through the validated items, inserting each into the database
            foreach ($validated_items as $item) {
                $pid   = $item['product_id'];
                $qty   = $item['quantity'];
                $price = $item['unit_price'];
                $color = trim($item['color']);
                $size  = trim($item['size']);

                // Insert the specific item line
                $stmt_item->execute([$order_id, $pid, $qty, $price, $color, $size]);

                // Step 2.1: Deduct from inventory immediately upon order placement
                $inv_id = null;
                
                // If variant info exists, attempt to find the exact inventory record
                if (!empty($size) && !empty($color)) {
                    $stmt_inv_check->execute([$pid, $size, $color]);
                    $inv_row = $stmt_inv_check->fetch();
                    if ($inv_row) {
                        $inv_id = $inv_row['id'];
                    }
                }
                
                // If no exact match found (or no variants provided), grab the generic product stock row
                if (!$inv_id) {
                    $stmt_inv_any = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? LIMIT 1");
                    $stmt_inv_any->execute([$pid]);
                    $inv_row_any = $stmt_inv_any->fetch();
                    if ($inv_row_any) {
                        $inv_id = $inv_row_any['id'];
                    }
                }
                
                // Reduce the found inventory stock by the ordered quantity
                if ($inv_id) {
                    $stmt_inv_dec->execute([$qty, $inv_id]);
                    // Dynamically update product status based on new total stock
                    $stmt_up_status->execute([$pid]);
                }
            }

            // Step 3: Create the initial order status log entry for audit history
            $note = $payment_method === 'card' ? 'Order placed and paid via Credit Card.' : 'Order placed with bank transfer receipt.';
            $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([$order_id, $order_status, $note, $user_id]);

            // Send order confirmation email
            require_once __DIR__ . "/../src/Mailer.php";
            $user_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_data = $user_stmt->fetch();
            if ($user_data && $user_data['email']) {
                $order_ref = "KE-2025-" . str_pad($order_id, 5, '0', STR_PAD_LEFT);
                $subject = "Order Confirmation: " . $order_ref;
                $body = "
                <p>Dear <strong>" . htmlspecialchars($user_data['first_name']) . "</strong>,</p>
                <p>Thank you for your order! Your wholesale order has been received and is currently marked as <strong style='color: #0F6E56;'>" . ucfirst($order_status) . "</strong>.</p>
                <div style='background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 12px; padding: 16px; margin: 16px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 13px;'><strong>Order Reference:</strong> <span style='color: #0F6E56; font-weight: bold;'>" . $order_ref . "</span></p>
                    <p style='margin: 0; font-size: 13px;'><strong>Total Amount:</strong> <span style='color: #111827; font-weight: bold;'>LKR " . number_format($total_amount, 2) . "</span></p>
                </div>
                <p style='color: #6b7280; font-size: 13px;'>We will process your order shortly. You can track your order status anytime from your account dashboard.</p>
                ";
                \App\Mailer::send($user_data['email'], $subject, $body);
            }

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
