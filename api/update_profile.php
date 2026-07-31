<?php
/**
 * User Profile Update API
 * Handles updating customer profile details (first name, last name, email address, phone number) for active user sessions.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Checking session -> Getting active user session ID
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

// Getting data -> Reading JSON payload or POST form input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

// Getting data -> Extracting and trimming profile fields
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

// Checking data -> Ensuring required profile fields are not empty
if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "First name, last name, email, and phone are required."]);
    exit;
}

// Checking data -> Validating email address format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email address format."]);
    exit;
}

// Checking data -> Validating Sri Lankan 10-digit phone number format
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone number must start with 0 and contain exactly 10 digits."]);
    exit;
}

// Updating data -> Saving updated profile details in users table
if (isset($pdo) && $pdo !== null) {
    try {
        // Checking data -> Checking if new email address is already in use by another account
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $user_id]);
        if ($stmt_check->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "The email address is already registered to another account."]);
            exit;
        }

        // Updating data -> Executing SQL update for user profile
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);

        echo json_encode([
            "status" => "success",
            "message" => "Profile updated successfully.",
            "user" => [
                "first_name" => $first_name,
                "last_name" => $last_name,
                "email" => $email,
                "phone" => $phone
            ]
        ]);
    } catch (\Exception $e) {
        // Handling errors -> Catching database exceptions and returning error response
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback -> Returning success response in demo mode
    echo json_encode(["status" => "success", "message" => "Profile updated (Demo Mode)."]);
}
