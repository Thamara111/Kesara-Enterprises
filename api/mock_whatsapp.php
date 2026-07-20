<?php
/**
 * Mock WhatsApp API
 * Receives internal requests and logs them as simulated WhatsApp messages.
 * This replaces actual WhatsApp integration for the demo/development environment.
 */
header("Content-Type: application/json; charset=UTF-8");

// Connect to the central database
require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

// Ensure the endpoint is only accessible via POST requests
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Read the incoming payload, supporting both JSON and URL-encoded formats
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

// Extract message details
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');
$customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : null;

// Validate mandatory fields before attempting to insert
if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone and message are required."]);
    exit;
}

// Log the message into the mock_whatsapp_messages table to simulate a sent state
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO mock_whatsapp_messages (customer_id, phone, message, status) VALUES (?, ?, ?, 'delivered')");
        $stmt->execute([$customer_id, $phone, $message]);
        
        echo json_encode(["status" => "success", "message" => "Mock WhatsApp message sent successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Fallback if the database connection isn't available
    echo json_encode(["status" => "success", "message" => "Mock WhatsApp message sent (Demo Mode)."]);
}
