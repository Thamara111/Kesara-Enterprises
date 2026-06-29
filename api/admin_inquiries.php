<?php
/**
 * REST API - Admin Inquiries
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid ID."]);
    exit;
}

if ($action === 'update_assignment') {
    // Only admin and finance manager can assign
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, ['admin', 'finance_manager'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "You don't have permission to assign inquiries."]);
        exit;
    }

    $assigned_to = isset($input['assigned_to']) && $input['assigned_to'] !== '' ? (int)$input['assigned_to'] : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$assigned_to, $id]);
        echo json_encode(["status" => "success", "message" => "Assignment updated successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} elseif ($action === 'update_status') {
    $status = $input['status'] ?? 'pending';
    
    // Check if user has permission (must be admin/finance, OR assigned to this inquiry)
    $role = $_SESSION['admin_role'] ?? '';
    $user_id = $_SESSION['admin_id'];
    
    try {
        if (!in_array($role, ['admin', 'finance_manager'])) {
            $check = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND assigned_to = ?");
            $check->execute([$id, $user_id]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "You don't have permission to update this inquiry."]);
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
