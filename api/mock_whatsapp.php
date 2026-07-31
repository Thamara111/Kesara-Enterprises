<?php
/**
 * Mock WhatsApp Notification API
 * Saves simulated WhatsApp notification messages to the database for testing and demonstration.
 */
header("Content-Type: application/json; charset=UTF-8");

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

// Getting data -> Extracting phone number, message content, and customer ID
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');
$customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : null;

// Checking data -> Ensuring phone number and message content are provided
if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone and message are required."]);
    exit;
}

// Saving data -> Recording mock WhatsApp message in database as delivered
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
    // Fallback -> Returning success response in demo mode
    echo json_encode(["status" => "success", "message" => "Mock WhatsApp message sent (Demo Mode)."]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/mock_whatsapp.php (WhatsApp Notification Simulation API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - admin/view/whatsapp.view.php
   - checkout.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Admin WhatsApp Manager (admin/view/whatsapp.view.php)
=============================================================================
*/

