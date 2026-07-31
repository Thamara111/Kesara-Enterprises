<?php
/**
 * Customer Inquiries API
 * Handles public submissions for contact forms and business inquiries while ensuring the inquiries table exists in database.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

// Getting data -> Creating inquiries table if it does not exist yet
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
        // Ignore database table setup errors if table already exists
    }
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

// Getting data -> Trimming and sanitizing incoming form fields
$name = trim($input['name'] ?? '');
$business_name = trim($input['business_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$inquiry_type = trim($input['inquiry_type'] ?? '');
$message = trim($input['message'] ?? '');

// Checking data -> Creating empty list for validation errors
$errors = [];

// Checking data -> Validating required input fields, email format, and phone number
if (empty($name)) $errors['name'] = 'Name is required.';
if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (!empty($phone) && !preg_match('/^0[0-9]{9}$/', $phone)) {
    $errors['phone'] = 'Phone number must start with 0 and contain exactly 10 digits.';
}
if (empty($inquiry_type)) $errors['inquiry_type'] = 'Inquiry type is required.';
if (empty($message)) $errors['message'] = 'Message is required.';

// Checking data -> Returning validation errors if any fields are invalid
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Validation failed", "errors" => $errors]);
    exit;
}

// Saving data -> Inserting submitted inquiry into inquiries table
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
        
        // Response -> Returning success response for submitted inquiry
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Inquiry submitted successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback -> Returning success response in demo mode
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Inquiry submitted successfully (Demo Mode)."]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/inquiries.php (Public Contact Us Form Handler API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - contact.php
   - src/Mailer.php
   - admin/view/inquiries.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Contact Us Form (contact.php) and Admin Inquiries View
=============================================================================
*/

