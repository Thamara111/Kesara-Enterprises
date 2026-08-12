<?php
/**
 * Customer & Wholesale REST API Login Endpoint
 * Authenticates user credentials and returns a signed JSON Web Token (JWT).
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

require_once __DIR__ . "/../database/connection.php";
require_once __DIR__ . "/../src/JWT.php";

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and password are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit;
}

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'approved') {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_type'] = $user['user_type'] ?? 'wholesale';

                $jwtToken = \App\JWT::encode([
                    'user_id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'user_type' => $user['user_type'] ?? 'wholesale',
                    'business_name' => $user['business_name'] ?? '',
                    'role' => 'customer'
                ]);

                \App\JWT::setAuthCookie(\App\JWT::COOKIE_USER, $jwtToken);
                $_SESSION['jwt_token'] = $jwtToken;

                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "token" => $jwtToken,
                    "user" => [
                        "id" => (int)$user['id'],
                        "email" => $user['email'],
                        "name" => $user['first_name'] . ' ' . $user['last_name'],
                        "user_type" => $user['user_type'] ?? 'wholesale',
                        "business_name" => $user['business_name'] ?? ''
                    ],
                    "message" => "Authentication successful."
                ]);
                exit;
            } elseif ($user['status'] === 'pending') {
                http_response_code(403);
                echo json_encode([
                    "status" => "pending",
                    "message" => "Your wholesale account approval is currently pending."
                ]);
                exit;
            } else {
                http_response_code(403);
                echo json_encode([
                    "status" => "inactive",
                    "message" => "Your account is currently " . htmlspecialchars($user['status']) . "."
                ]);
                exit;
            }
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        exit;
    }
}

http_response_code(401);
echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
