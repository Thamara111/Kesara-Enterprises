<?php
/**
 * Orders Management API
 * Handles getting order history, updating order statuses, placing new wholesale orders, reducing stock, and emailing receipts and updates.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Getting data -> Starting user session and loading database connection settings
session_start();
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Getting data -> Fetching order history list
if ($method === 'GET') {
    // Getting data -> Fetching all customer orders joined with user business names
    $orders = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            // Getting data -> Fetching customer business name for each order
            $stmt = $pdo->query("SELECT o.*, u.business_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
            $orders = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Handling errors -> Ignoring database exception to return empty order list
        }
    }
    // Response -> Returning JSON list of orders
    echo json_encode(["status" => "success", "data" => $orders]);
    exit;
}

// Processing request -> Handling POST requests for placing orders or updating order statuses
if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    // Getting data -> Reading JSON body or form payload including items and uploaded files
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
        // Getting data -> Decoding JSON items string if sent within form-data
        if (isset($input['items']) && is_string($input['items'])) {
            $input['items'] = json_decode($input['items'], true);
        }
    }

    // Updating status -> Updating status of an existing order
    if ($action === 'update_status') {
        $order_id = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 1;

        // Checking data -> Ensuring valid order ID and status string are provided
        if ($order_id > 0 && !empty($status)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    // Getting data -> Ensuring cancellation_reason column exists in orders table
                    $checkCancel = $pdo->query("SHOW COLUMNS FROM orders LIKE 'cancellation_reason'");
                    if (!$checkCancel->fetch()) {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) DEFAULT NULL AFTER status");
                    }

                    $custom_note = trim($input['note'] ?? ($input['cancellation_reason'] ?? ''));

                    // Database transaction -> Starting transaction to safely update order status and log history
                    $pdo->beginTransaction();

                    // Getting data -> Fetching previous order status before applying update
                    $prev_status_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                    $prev_status_stmt->execute([$order_id]);
                    $prev_status = strtolower($prev_status_stmt->fetchColumn() ?: '');

                    // Restocking stock -> Restocking inventory items if order is being cancelled for the first time
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

                        // Updating status -> Recalculating stock availability status (In Stock, Low Stock, Out of Stock) for affected products
                        foreach (array_keys($affected_products) as $aff_pid) {
                            $stmt_up_status->execute([$aff_pid]);
                        }
                    }
                    
                    // Updating data -> Saving new status in orders table
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
                    
                    // Formatting data -> Creating human-readable log note for order status change
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
                    
                    // Sending email -> Emailing order status update or cancellation notice to customer
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

                    // Database transaction -> Committing status update transaction
                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Status updated."]);
                } catch (\Exception $e) {
                    // Handling errors -> Rolling back transaction if an error occurs
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

    // Saving data -> Processing new wholesale order placement from customer

    // Getting data -> Getting customer user ID and order items list
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 1;
    $items = $input['items'] ?? [];

    // Checking data -> Ensuring order items list is not empty
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid order items list."]);
        exit;
    }

    // Setting status -> Setting initial order status based on payment method (pending for bank, processing for card)
    $payment_method = $input['payment_method'] ?? 'bank';
    $order_status = 'pending';
    if ($payment_method === 'card') {
        $order_status = 'processing';
    }

    // Uploading file -> Saving payment receipt image file to uploads directory
    $receipt_path = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        // Formatting data -> Generating secure random filename for uploaded receipt
        $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        // Uploading file -> Moving uploaded receipt file to destination folder
        if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
            $receipt_path = '/uploads/receipts/' . $filename;
        }
    }

    if (isset($pdo) && $pdo !== null) {
        try {
            // Getting data -> Loading server-side order validation helper functions
            require_once __DIR__ . '/model_validation.php';

            // Checking data -> Validating minimum order quantities (MOQ) and server-side tier pricing
            $validation = validateOrderItems($pdo, $items);
            if (!empty($validation['errors'])) {
                // Checking data -> Returning validation error if MOQ or pricing checks fail
                http_response_code(400);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Validation failed: " . implode(" ", $validation['errors'])
                ]);
                exit;
            }

            // Getting data -> Extracting verified total amount and item list
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

            // Getting data -> Ensuring color and size columns exist in order_items table
            $checkOrderColor = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'color'");
            if (!$checkOrderColor->fetch()) {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN color VARCHAR(50) DEFAULT NULL");
                $pdo->exec("ALTER TABLE order_items ADD COLUMN size VARCHAR(50) DEFAULT NULL");
            }

            // Getting data -> Ensuring payment_receipt column exists in orders table
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

            // Database transaction -> Starting transaction to save new order and deduct inventory stock
            $pdo->beginTransaction();

            // Saving data -> Creating new order record in orders table
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_receipt) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $order_status, $total_amount, $receipt_path]);
            $order_id = $pdo->lastInsertId();

            // Preparing queries -> Preparing statements to insert order items and update stock
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

            // Saving data -> Inserting each item line into order_items table
            foreach ($validated_items as $item) {
                $pid   = $item['product_id'];
                $qty   = $item['quantity'];
                $price = $item['unit_price'];
                $color = trim($item['color']);
                $size  = trim($item['size']);

                // Saving data -> Saving item line details to database
                $stmt_item->execute([$order_id, $pid, $qty, $price, $color, $size]);

                // Deducting stock -> Reducing stock quantity for ordered item
                $inv_id = null;
                
                // Getting data -> Looking up exact inventory item matching size and color
                if (!empty($size) && !empty($color)) {
                    $stmt_inv_check->execute([$pid, $size, $color]);
                    $inv_row = $stmt_inv_check->fetch();
                    if ($inv_row) {
                        $inv_id = $inv_row['id'];
                    }
                }
                
                // Fallback -> Grabbing generic inventory item if exact color and size match does not exist
                if (!$inv_id) {
                    $stmt_inv_any = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? LIMIT 1");
                    $stmt_inv_any->execute([$pid]);
                    $inv_row_any = $stmt_inv_any->fetch();
                    if ($inv_row_any) {
                        $inv_id = $inv_row_any['id'];
                    }
                }
                
                // Deducting stock -> Decreasing inventory stock by ordered quantity
                if ($inv_id) {
                    $stmt_inv_dec->execute([$qty, $inv_id]);
                    // Updating status -> Updating overall product availability status (In Stock, Low Stock, Out of Stock)
                    $stmt_up_status->execute([$pid]);
                }
            }

            // Saving log -> Recording initial order placement status log entry
            $note = $payment_method === 'card' ? 'Order placed and paid via Credit Card.' : 'Order placed with bank transfer receipt.';
            $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([$order_id, $order_status, $note, $user_id]);

            // Sending email -> Sending order confirmation email with reference and total to customer
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
