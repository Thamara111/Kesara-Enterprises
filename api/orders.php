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
    $action = $_GET['action'] ?? '';
    
    // If the Content-Type is multipart/form-data (as when uploading file), php://input will be empty
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
        if (isset($input['items']) && is_string($input['items'])) {
            $input['items'] = json_decode($input['items'], true);
        }
    }

    if ($action === 'update_status') {
        $order_id = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 1;

        if ($order_id > 0 && !empty($status)) {
            if (isset($pdo) && $pdo !== null) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $order_id]);
                    
                    // Create log
                    $note = "Status updated to " . ucfirst($status) . ".";
                    if ($status === 'processing') $note = "Payment accepted — order is now processing.";
                    if ($status === 'shipped')    $note = "Order dispatched and marked as shipped.";
                    if ($status === 'delivered')  $note = "Delivery confirmed successfully.";
                    if ($status === 'cancelled')  $note = "Order has been cancelled.";
                    
                    $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
                    $log_stmt->execute([$order_id, $status, $note, $user_id]);
                    
                    // Send email notification for status update
                    require_once __DIR__ . "/../src/Mailer.php";
                    $user_stmt = $pdo->prepare("SELECT u.email, u.first_name FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ?");
                    $user_stmt->execute([$order_id]);
                    $user_data = $user_stmt->fetch();
                    if ($user_data && $user_data['email']) {
                        $subject = "Order Status Update: " . ucfirst($status);
                        $body = "<h3>Hello " . htmlspecialchars($user_data['first_name']) . ",</h3>" .
                                "<p>Your order (ID: KE-2025-" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . ") status has been updated to: <strong>" . ucfirst($status) . "</strong>.</p>" .
                                "<p>Note: " . htmlspecialchars($note) . "</p>";
                        \App\Mailer::send($user_data['email'], $subject, $body);
                    }

                    $pdo->commit();
                    http_response_code(200);
                    echo json_encode(["status" => "success", "message" => "Status updated."]);
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
            echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
        }
        exit;
    }

    // Determine User ID (fallback to 1 if not logged in)
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 1;
    $items = $input['items'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid order items list."]);
        exit;
    }

    // Determine payment method and order status
    $payment_method = $input['payment_method'] ?? 'bank';
    $order_status = 'pending';
    if ($payment_method === 'card') {
        $order_status = 'processing';
    }

    // Handle Payment Receipt Upload
    $receipt_path = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
            $receipt_path = '/uploads/receipts/' . $filename;
        }
    }

    if (isset($pdo) && $pdo !== null) {
        try {
            // Require the model layer validation functions
            require_once __DIR__ . '/model_validation.php';

            // Validate order items (MOQ and Pricing Tiers resolved server-side)
            $validation = validateOrderItems($pdo, $items);
            if (!empty($validation['errors'])) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Validation failed: " . implode(" ", $validation['errors'])
                ]);
                exit;
            }

            $total_amount = $validation['calculated_total'];
            $validated_items = $validation['validated_items'];

            $checkOrderColor = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'color'");
            if (!$checkOrderColor->fetch()) {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN color VARCHAR(50) DEFAULT NULL");
                $pdo->exec("ALTER TABLE order_items ADD COLUMN size VARCHAR(50) DEFAULT NULL");
            }

            $checkReceipt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_receipt'");
            if (!$checkReceipt->fetch()) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL");
            }

            $pdo->beginTransaction();

            // 1. Insert into orders with payment receipt and status based on payment method
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_receipt) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $order_status, $total_amount, $receipt_path]);
            $order_id = $pdo->lastInsertId();

            // 2. Insert order items and decrement inventory
            $stmt_item      = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, color, size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_inv_check = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? AND size = ? AND colour = ? LIMIT 1");
            $stmt_inv_dec   = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");

            foreach ($validated_items as $item) {
                $pid   = $item['product_id'];
                $qty   = $item['quantity'];
                $price = $item['unit_price'];
                $color = trim($item['color']);
                $size  = trim($item['size']);

                $stmt_item->execute([$order_id, $pid, $qty, $price, $color, $size]);

                // Decrement inventory immediately on order placement
                $inv_id = null;
                if (!empty($size) && !empty($color)) {
                    $stmt_inv_check->execute([$pid, $size, $color]);
                    $inv_row = $stmt_inv_check->fetch();
                    if ($inv_row) {
                        $inv_id = $inv_row['id'];
                    }
                }
                if (!$inv_id) {
                    $stmt_inv_any = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? LIMIT 1");
                    $stmt_inv_any->execute([$pid]);
                    $inv_row_any = $stmt_inv_any->fetch();
                    if ($inv_row_any) {
                        $inv_id = $inv_row_any['id'];
                    }
                }
                if ($inv_id) {
                    $stmt_inv_dec->execute([$qty, $inv_id]);
                }
            }

            // 3. Create log
            $note = $payment_method === 'card' ? 'Order placed and paid via Credit Card.' : 'Order placed with bank transfer receipt.';
            $log_stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([$order_id, $order_status, $note, $user_id]);

            // Send order confirmation email
            require_once __DIR__ . "/../src/Mailer.php";
            $user_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_data = $user_stmt->fetch();
            if ($user_data && $user_data['email']) {
                $subject = "Order Confirmation: KE-2025-" . str_pad($order_id, 5, '0', STR_PAD_LEFT);
                $body = "<h3>Thank you for your order, " . htmlspecialchars($user_data['first_name']) . "!</h3>" .
                        "<p>Your order has been received and is currently marked as <strong>" . ucfirst($order_status) . "</strong>.</p>" .
                        "<p>Total Amount: LKR " . number_format($total_amount, 2) . "</p>" .
                        "<p>We will process it shortly.</p>";
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
