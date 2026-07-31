<?php
/**
 * Wholesale Customer Registration API
 * Creates new customer accounts, hashes passwords securely, and sets account status to pending for admin approval.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Checking request -> Ensuring request method is POST
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Getting data -> Reading JSON payload or POST form input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

// Getting data -> Extracting and trimming registration fields
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

// Checking data -> Ensuring no required registration fields are empty
if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required, including your WhatsApp number."]);
    exit;
}

// Checking data -> Validating email address format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter a valid email address."]);
    exit;
}

// Saving data -> Saving new wholesale customer account to database
if (isset($pdo) && $pdo !== null) {
    try {
        // Checking data -> Checking if an account with this email already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "An account with this email address already exists."]);
            exit;
        }

        // Processing data -> Hashing password using bcrypt algorithm
        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        
        // Saving data -> Inserting new customer account into users table with pending status
        $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass, $business_name, $br_number, $business_type, $address]);

        // Response -> Returning success response for account creation
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h."]);
    } catch (\Exception $e) {
        // Handling errors -> Catching database exceptions and returning error response
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback -> Returning success response in demo mode
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h. (Demo Mode)"]);
}
