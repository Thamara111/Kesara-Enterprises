<?php
/**
 * REST API - Categories
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . "/../database/connection.php";

// Self-healing database check
if (isset($pdo) && $pdo !== null) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        }
    } catch (\Exception $e) {
        // Ignored
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS for preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $categories = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT c.*, COUNT(p.id) AS product_count 
                                 FROM categories c 
                                 LEFT JOIN products p ON p.category_id = c.id 
                                 GROUP BY c.id 
                                 ORDER BY c.name ASC");
            $categories = $stmt->fetchAll();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
            exit;
        }
    } else {
        $categories = [
            ['id' => 1, 'name' => "Men's Briefs", 'slug' => 'mens-briefs', 'icon' => 'ti-shirt', 'description' => 'Comfortable cotton briefs for men.', 'image' => '', 'product_count' => 1],
            ['id' => 2, 'name' => "Men's Boxers", 'slug' => 'mens-boxers', 'icon' => 'ti-shirt', 'description' => 'Premium stretch boxers.', 'image' => '', 'product_count' => 1],
            ['id' => 3, 'name' => 'Ladies Innerwear', 'slug' => 'ladies-innerwear', 'icon' => 'ti-shirt', 'description' => 'Soft touch and premium hipster wear.', 'image' => '', 'product_count' => 1]
        ];
    }
    echo json_encode(["status" => "success", "data" => $categories]);
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            // Check JSON body
            $input = json_decode(file_get_contents("php://input"), true);
            $id = isset($input['id']) ? (int)$input['id'] : 0;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid Category ID for deletion."]);
            exit;
        }

        if (isset($pdo) && $pdo !== null) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(["status" => "success", "message" => "Category deleted successfully."]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["status" => "success", "message" => "Category deleted successfully (Demo Mode)."]);
        }
        exit;
    }

    // Default: Save
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $name = trim($input['name'] ?? '');
    $slug = trim($input['slug'] ?? '');
    $icon = trim($input['icon'] ?? 'ti-tag');
    $description = trim($input['description'] ?? '');
    $image = trim($input['image'] ?? '');

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Category Name is required."]);
        exit;
    }

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }

    // Process image file upload if provided
    if (isset($_FILES['category_image_file']) && $_FILES['category_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['category_image_file']['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("File upload failed with error code: " . $_FILES['category_image_file']['error']);
        }
        
        $fileTmpPath = $_FILES['category_image_file']['tmp_name'];
        $fileName = $_FILES['category_image_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new \Exception("Failed to create uploads directory: " . $uploadDir);
                }
            }
            
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $image = '/assets/uploads/' . $newFileName;
            } else {
                throw new \Exception("Failed to move uploaded file. Check directory permissions.");
            }
        } else {
            throw new \Exception("Unsupported image format. Allowed: JPG, JPEG, PNG, GIF, WEBP.");
        }
    }

    if (isset($pdo) && $pdo !== null) {
        try {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $icon, $description, $image, $id]);
                echo json_encode(["status" => "success", "message" => "Category updated successfully."]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, description, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon, $description, $image]);
                echo json_encode(["status" => "success", "message" => "Category created successfully."]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "success", "message" => "Category saved successfully (Demo Mode)."]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
