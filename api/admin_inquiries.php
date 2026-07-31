<?php
/**
 * Admin Inquiries API
 * Handles administrative actions for customer inquiries like updating staff assignments, changing statuses, and emailing replies.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Checking data -> Making sure admin user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Checking request -> Making sure request method is POST
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Getting data -> Reading request payload and extracting action and inquiry ID
$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? '';
$id = (int)($input['id'] ?? 0);

// Checking data -> Making sure inquiry ID is a valid number
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid ID."]);
    exit;
}

// Updating data -> Assigning inquiry to a specific staff member
if ($action === 'update_assignment') {
    // Checking permission -> Checking if logged in admin is allowed to assign inquiries
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, ['admin', 'finance_manager'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "You don't have permission to assign inquiries."]);
        exit;
    }

    // Getting data -> Getting assigned staff ID from input
    $assigned_to = isset($input['assigned_to']) && $input['assigned_to'] !== '' ? (int)$input['assigned_to'] : null;
    
    try {
        // Updating data -> Saving assigned staff ID to database for this inquiry
        $stmt = $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$assigned_to, $id]);
        echo json_encode(["status" => "success", "message" => "Assignment updated successfully."]);
    } catch (\Exception $e) {
        // Handling errors -> Catching database exceptions and returning error response
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} 
// Updating status -> Changing status of inquiry (like pending or resolved)
elseif ($action === 'update_status') {
    $status = $input['status'] ?? 'pending';
    
    // Getting data -> Checking admin user role and ID for permission check
    $role = $_SESSION['admin_role'] ?? '';
    $user_id = $_SESSION['admin_id'];
    
    try {
        // Checking permission -> Verifying staff is assigned to this inquiry if not an admin
        if (!in_array($role, ['admin', 'finance_manager'])) {
            $check = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND assigned_to = ?");
            $check->execute([$id, $user_id]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "You don't have permission to update this inquiry."]);
                exit;
            }
        }

        // Updating data -> Saving new status in database for this inquiry
        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} 
// Sending email -> Replying to customer inquiry via email
elseif ($action === 'send_reply') {
    $to_email = $input['to_email'] ?? '';
    $subject = $input['subject'] ?? '';
    $message = $input['message'] ?? '';
    $new_status = $input['new_status'] ?? '';
    
    if (empty($to_email) || empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }
    
    // Getting data -> Checking admin user role and ID for permission check
    $role = $_SESSION['admin_role'] ?? '';
    $user_id = $_SESSION['admin_id'];
    
    if (!in_array($role, ['admin', 'finance_manager'])) {
        $check = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND assigned_to = ?");
        $check->execute([$id, $user_id]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "You don't have permission to reply to this inquiry."]);
            exit;
        }
    }
    
    require_once __DIR__ . '/../src/Mailer.php';
    
    // Formatting message -> Converting newlines to line break tags for HTML email
    $html_message = nl2br(htmlspecialchars($message));
    
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment = $_FILES['attachment'];
    }
    
    $sent = \App\Mailer::send($to_email, $subject, $html_message, $attachment);
    
    if ($sent) {
        // Updating status -> Updating inquiry status after sending reply
        if (in_array($new_status, ['in_progress', 'resolved'])) {
            try {
                $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
            } catch (\Exception $e) {
                // Handling errors -> Ignoring status update DB error if email was already sent successfully
            }
        }
        echo json_encode(["status" => "success", "message" => "Reply sent successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to send email."]);
    }
}
// Checking data -> Returning error if requested action is not recognized
else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
