<?php
/**
 * REST API - User Registration
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . "/../database/connection.php";

// Self-healing: ensure whatsapp_number column exists
if (isset($pdo) && $pdo !== null) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'whatsapp_number'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(20) DEFAULT NULL");
        }
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

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$first_name      = trim($input['first_name'] ?? '');
$last_name       = trim($input['last_name'] ?? '');
$email           = trim($input['email'] ?? '');
$phone           = trim($input['phone'] ?? '');
$whatsapp_number = trim($input['whatsapp_number'] ?? '');
$password        = $input['password'] ?? '';
$business_name   = trim($input['business_name'] ?? '');
$br_number       = trim($input['br_number'] ?? '');
$business_type   = trim($input['business_type'] ?? '');
$address         = trim($input['address'] ?? '');

if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required, including your WhatsApp number."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter a valid email address."]);
    exit;
}

if (isset($pdo) && $pdo !== null) {
    try {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "An account with this email address already exists."]);
            exit;
        }

        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass, $business_name, $br_number, $business_type, $address]);

        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h. (Demo Mode)"]);
}
