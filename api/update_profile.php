<?php
/**
 * API - Update User Profile
 * Handles profile updates (first_name, last_name, email, phone) for logged-in customers.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "First name, last name, email, and phone are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email address format."]);
    exit;
}

if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone number must start with 0 and contain exactly 10 digits."]);
    exit;
}

if (isset($pdo) && $pdo !== null) {
    try {
        // Check if email is used by another user
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $user_id]);
        if ($stmt_check->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "The email address is already registered to another account."]);
            exit;
        }

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
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "success", "message" => "Profile updated (Demo Mode)."]);
}
