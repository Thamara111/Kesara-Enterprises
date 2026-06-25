<?php
/**
 * REST API - Inquiries
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . "/../database/connection.php";

// Self-healing database check
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            business_name VARCHAR(100),
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            inquiry_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (\Exception $e) {
        // Ignored
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Get POST input (handle both JSON and application/x-www-form-urlencoded)
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$name = trim($input['name'] ?? '');
$business_name = trim($input['business_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$inquiry_type = trim($input['inquiry_type'] ?? '');
$message = trim($input['message'] ?? '');

$errors = [];
if (empty($name)) $errors['name'] = 'Name is required.';
if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (empty($inquiry_type)) $errors['inquiry_type'] = 'Inquiry type is required.';
if (empty($message)) $errors['message'] = 'Message is required.';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Validation failed", "errors" => $errors]);
    exit;
}

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO inquiries (name, business_name, email, phone, inquiry_type, message) 
                               VALUES (:name, :business_name, :email, :phone, :inquiry_type, :message)");
        $stmt->execute([
            ':name' => $name,
            ':business_name' => $business_name ?: null,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':inquiry_type' => $inquiry_type,
            ':message' => $message
        ]);
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Inquiry submitted successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Offline / demo fallback mode
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Inquiry submitted successfully (Demo Mode)."]);
}
