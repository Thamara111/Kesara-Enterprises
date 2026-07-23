<?php
/**
 * REST API - Admin Inquiries
 * Handles POST requests to manage customer inquiries from the admin panel.
 * Responsible for updating assignments and inquiry statuses.
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Validate that an admin session is active
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

// Include database connection settings
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Enforce that only POST requests are allowed for this endpoint
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Decode the JSON body containing the action and inquiry ID
$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? '';
$id = (int)($input['id'] ?? 0);

// Validate that a valid positive ID was provided
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid ID."]);
    exit;
}

// Handle inquiry assignment to specific staff
if ($action === 'update_assignment') {
    // Only System Admins and Finance Managers possess permission to assign inquiries
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, ['admin', 'finance_manager'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "You don't have permission to assign inquiries."]);
        exit;
    }

    // Parse the assigned_to ID from the input; null if empty or unassigned
    $assigned_to = isset($input['assigned_to']) && $input['assigned_to'] !== '' ? (int)$input['assigned_to'] : null;
    
    try {
        // Execute the database update to assign the inquiry
        $stmt = $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$assigned_to, $id]);
        echo json_encode(["status" => "success", "message" => "Assignment updated successfully."]);
    } catch (\Exception $e) {
        // Catch any SQL exceptions and return a generic 500 error
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} 
// Handle updating the status of an inquiry (e.g. pending, resolved)
elseif ($action === 'update_status') {
    $status = $input['status'] ?? 'pending';
    
    // Determine the current user's role and ID to check permissions
    $role = $_SESSION['admin_role'] ?? '';
    $user_id = $_SESSION['admin_id'];
    
    try {
        // Enforce access control: If the user is not an Admin or Finance Manager,
        // they MUST be explicitly assigned to this specific inquiry to update it.
        if (!in_array($role, ['admin', 'finance_manager'])) {
            $check = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND assigned_to = ?");
            $check->execute([$id, $user_id]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "You don't have permission to update this inquiry."]);
                exit;
            }
        }

        // Update the status in the database
        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} 
// Handle sending an email reply to the customer
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
    
    // Determine the current user's role and ID to check permissions
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
    
    // Convert newlines to <br> for HTML email
    $html_message = nl2br(htmlspecialchars($message));
    
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment = $_FILES['attachment'];
    }
    
    $sent = \App\Mailer::send($to_email, $subject, $html_message, $attachment);
    
    if ($sent) {
        // Update status if requested
        if (in_array($new_status, ['in_progress', 'resolved'])) {
            try {
                $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
            } catch (\Exception $e) {
                // Ignore DB error for status if email sent successfully
            }
        }
        echo json_encode(["status" => "success", "message" => "Reply sent successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to send email."]);
    }
}
// Invalid action provided
else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
