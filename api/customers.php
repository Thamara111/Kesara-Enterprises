<?php
/**
 * REST API - Customers Management
 * Handles: status updates, admin comment saving
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Self-healing: ensure admin_comment column exists
if (isset($pdo) && $pdo !== null) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'admin_comment'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN admin_comment TEXT DEFAULT NULL");
        }
    } catch (\Exception $e) {
        // Ignored
    }
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$action = trim($input['action'] ?? 'update_status');
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid customer ID."]);
    exit;
}

if ($method === 'POST' && $action === 'save_comment') {
    // Save admin comment
    $comment = trim($input['comment'] ?? '');

    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET admin_comment = ? WHERE id = ?");
            $stmt->execute([$comment, $id]);
            echo json_encode(["status" => "success", "message" => "Comment saved successfully."]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "success", "message" => "Comment saved (Demo Mode)."]);
    }
    exit;
}

if ($method === 'GET') {
    // Return a specific customer's admin comment
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare("SELECT admin_comment FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            echo json_encode(["status" => "success", "comment" => $row['admin_comment'] ?? '']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "success", "comment" => ""]);
    }
    exit;
}

// Default: POST - update status
$status = trim($input['status'] ?? '');

if (empty($status)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Status is required."]);
    exit;
}

$allowed_statuses = ['pending', 'approved', 'suspended', 'rejected'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid status value."]);
    exit;
}

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Mock WhatsApp integration
        if ($status === 'approved' || $status === 'suspended') {
            $userStmt = $pdo->prepare("SELECT phone, first_name, admin_comment FROM users WHERE id = ?");
            $userStmt->execute([$id]);
            $user = $userStmt->fetch();
            
            if ($user && !empty($user['phone'])) {
                $greeting = "Hello " . ($user['first_name'] ?: 'Customer') . ",\n\n";
                if ($status === 'approved') {
                    $body = "Your account at Kesara Enterprises has been approved! You can now log in and place orders.\n";
                } else {
                    $body = "Your account at Kesara Enterprises has been temporarily suspended.\n";
                }
                
                if (!empty($user['admin_comment'])) {
                    $body .= "\nAdmin Note:\n" . $user['admin_comment'];
                }
                
                $fullMessage = $greeting . $body;
                
                $waStmt = $pdo->prepare("INSERT INTO mock_whatsapp_messages (customer_id, phone, message) VALUES (?, ?, ?)");
                $waStmt->execute([$id, $user['phone'], $fullMessage]);
            }
        }
        
        echo json_encode(["status" => "success", "message" => "Customer status updated to " . ucfirst($status) . "."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "success", "message" => "Customer status updated to " . ucfirst($status) . " (Demo Mode)."]);
}
