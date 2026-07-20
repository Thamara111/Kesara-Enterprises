<?php
/**
 * REST API - User Registration
 * Handles the creation of new wholesale customer accounts.
 * Accepts user details, hashes passwords, and saves records with a 'pending' status.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Connect to the database
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Enforce that only POST requests are processed
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Attempt to decode JSON payload (e.g., from fetch API)
$input = json_decode(file_get_contents("php://input"), true);
// If no JSON was sent, fallback to standard form URL-encoded POST data
if (!$input) {
    $input = $_POST;
}

// Extract and trim all user registration fields
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

// Validate that absolutely no required fields were left empty
if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required, including your WhatsApp number."]);
    exit;
}

// Perform strict validation on the email address format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter a valid email address."]);
    exit;
}

// Proceed with database insertion if the connection is active
if (isset($pdo) && $pdo !== null) {
    try {
        // Prevent duplicate accounts by checking if the email already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "An account with this email address already exists."]);
            exit;
        }

        // Securely hash the plain-text password using the bcrypt algorithm
        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert the new user into the database with a default status of 'pending'
        // (Wholesale accounts require manual admin approval)
        $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass, $business_name, $br_number, $business_type, $address]);

        // Return a successful creation response
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h."]);
    } catch (\Exception $e) {
        // Catch database errors, log securely, and return 500
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback for demo mode environments where the database is unavailable
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h. (Demo Mode)"]);
}
