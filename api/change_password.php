<?php
/**
 * Change Password API
 * Handles updating passwords for logged-in users and sending email security notifications.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Checking data -> Verifying user session is active
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Please sign in to change your password."]);
    exit;
}

// Checking request -> Ensuring HTTP request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Getting data -> Loading database connection and mailer helper
require_once __DIR__ . "/../database/connection.php";
require_once __DIR__ . "/../src/Mailer.php";

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$current_password = $input['current_password'] ?? '';
$new_password     = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Checking data -> Ensuring all password fields are filled
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All password fields are required."]);
    exit;
}

// Checking data -> Checking minimum password length
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "New password must be at least 8 characters long."]);
    exit;
}

// Checking data -> Making sure new password matches confirmation
if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "New password and confirmation password do not match."]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (isset($pdo) && $pdo !== null) {
    try {
        // Getting data -> Fetching user details and current password hash
        $stmt = $pdo->prepare("SELECT first_name, email, password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Checking data -> Verifying if current password matches existing hash
        if (!$user || !password_verify($current_password, $user['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Current password is incorrect."]);
            exit;
        }

        // Updating data -> Hashing new password and saving to database
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$new_hash, $user_id]);

        // Sending email -> Sending password change confirmation email
        $user_email = $user['email'] ?? ($_SESSION['user_email'] ?? '');
        $first_name = !empty($user['first_name']) ? htmlspecialchars($user['first_name']) : 'Customer';

        if (!empty($user_email)) {
            $emailSubject = "Password Changed Successfully - Kesara Enterprises";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #0F6E56; margin: 0;'>Kesara Enterprises</h2>
                    <p style='color: #6b7280; font-size: 14px;'>Security Notification</p>
                </div>
                <p>Dear <strong>{$first_name}</strong>,</p>
                <p>Your account password has been successfully changed.</p>
                <p style='background-color: #f3f4f6; padding: 12px; border-left: 4px solid #0F6E56; font-size: 13px; color: #374151;'>
                    If you did not perform this change, please contact our support team immediately.
                </p>
                <br>
                <p style='font-size: 12px; color: #9ca3af;'>© " . date('Y') . " Kesara Enterprises (Pvt) Ltd. All rights reserved.</p>
            </div>
            ";

            try {
                \App\Mailer::send($user_email, $emailSubject, $emailBody);
            } catch (\Exception $mailEx) {
                // Handling errors -> Ignoring email sending failure so password update still succeeds
            }
        }

        echo json_encode(["status" => "success", "message" => "Your password has been updated successfully!"]);
    } catch (\Exception $e) {
        // Handling errors -> Returning error response on database failure
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback -> Returning success response in demo mode
    echo json_encode(["status" => "success", "message" => "Your password has been updated successfully! (Demo Mode)"]);
}
