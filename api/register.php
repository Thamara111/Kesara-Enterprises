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

// Self-Healing Database: Ensure user_type column, nullable business columns, and mock_whatsapp_messages table exist
if (isset($pdo) && $pdo !== null) {
    try {
        $checkUserType = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_type'");
        if (!$checkUserType->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN user_type ENUM('wholesale', 'individual') DEFAULT 'individual'");
        }
        $pdo->exec("ALTER TABLE users MODIFY COLUMN business_name VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN br_number VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN business_type VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN address TEXT NULL");

        $pdo->exec("CREATE TABLE IF NOT EXISTS mock_whatsapp_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'delivered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (\Exception $e) {}
}

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
$account_type    = trim($input['account_type'] ?? 'individual');
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

$is_individual = ($account_type === 'individual');

if ($is_individual) {
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "All contact fields are required."]);
        exit;
    }
} else {
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "All wholesale business fields are required."]);
        exit;
    }
}

// Checking data -> Validating email address format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter a valid email address."]);
    exit;
}

// Saving data -> Saving customer account to database
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
        
        if ($is_individual) {
            $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, user_type, status) VALUES (?, ?, ?, ?, ?, ?, 'individual', 'approved')");
            $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass]);
            $new_id = $pdo->lastInsertId();

            // Record thank-you WhatsApp message
            try {
                $wa_msg = "Hello {$first_name}, thank you for joining Kesara Enterprises! Your individual account is active. You can shop our retail collection right away!";
                $stmt_wa = $pdo->prepare("INSERT INTO mock_whatsapp_messages (customer_id, phone, message, status) VALUES (?, ?, ?, 'delivered')");
                $stmt_wa->execute([$new_id, $whatsapp_number, $wa_msg]);
            } catch (\Exception $ex) {}

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $new_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['user_type'] = 'individual';

            http_response_code(201);
            echo json_encode(["status" => "success", "success_code" => 2, "message" => "Your account has been created successfully! Welcome to Kesara Enterprises."]);
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, user_type, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, 'wholesale', ?, ?, ?, ?, 'pending')");
            $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass, $business_name, $br_number, $business_type, $address]);

            http_response_code(201);
            echo json_encode(["status" => "success", "success_code" => 1, "message" => "Your wholesale account request has been submitted successfully! We will contact you within 24h."]);
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(201);
    echo json_encode(["status" => "success", "success_code" => $is_individual ? 2 : 1, "message" => "Account created (Demo Mode)."]);
}

/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/register.php (REST API Wholesale Registration Endpoint)

 CONNECTED / DEPENDENT FILES:
   - Database Connection: database/connection.php
   - Web Authentication Form: auth.php
   - Admin Customer Management: admin/view/customers.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Web Registration Handler: auth.php (if updating web registration logic)
   - Admin Customer View: admin/view/customers.view.php (if adding user attributes)
=============================================================================
*/

