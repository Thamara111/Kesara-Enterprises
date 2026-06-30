<?php
/**
 * REST API - Products
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . "/../database/connection.php";

// Self-healing database check
if (isset($pdo) && $pdo !== null) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM products LIKE 'images'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN images TEXT DEFAULT NULL");
        }
        $checkColors = $pdo->query("SHOW COLUMNS FROM products LIKE 'colors'");
        if (!$checkColors->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN colors VARCHAR(255) DEFAULT NULL");
        }
        $checkDiscount = $pdo->query("SHOW COLUMNS FROM products LIKE 'discount'");
        if (!$checkDiscount->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
        }
        $checkSizes = $pdo->query("SHOW COLUMNS FROM products LIKE 'sizes'");
        if (!$checkSizes->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN sizes VARCHAR(255) DEFAULT NULL");
        }
        $checkGsm = $pdo->query("SHOW COLUMNS FROM products LIKE 'gsm'");
        if (!$checkGsm->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN gsm VARCHAR(100) DEFAULT NULL");
        }
        $checkWaistband = $pdo->query("SHOW COLUMNS FROM products LIKE 'waistband'");
        if (!$checkWaistband->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN waistband VARCHAR(150) DEFAULT NULL");
        }
    } catch (\Exception $e) {
        // Ignored
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $products = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc`, p.images, p.colors, p.sizes, p.discount, p.gsm, p.waistband 
                                 FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.id
                                 ORDER BY p.name ASC");
            $prods = $stmt->fetchAll();

            foreach ($prods as $pr) {
                $t_stmt = $pdo->prepare("SELECT min_qty AS q, price AS p FROM pricing_tiers WHERE product_id = ?");
                $t_stmt->execute([$pr['id']]);
                $tiers = $t_stmt->fetchAll();

                $formatted_tiers = [];
                foreach ($tiers as $t) {
                    $formatted_tiers[] = [
                        'q' => (int)$t['q'],
                        'p' => (float)$t['p']
                    ];
                }

                $products[] = [
                    'id' => (int)$pr['id'],
                    'name' => $pr['name'],
                    'sku' => $pr['sku'],
                    'cat' => $pr['cat'] ?? 'Uncategorized',
                    'moq' => (int)$pr['moq'],
                    'price' => (float)$pr['price'],
                    'status' => $pr['status'],
                    'desc' => $pr['desc'] ?? '',
                    'images' => json_decode($pr['images'] ?? '[]', true) ?: [],
                    'colors' => $pr['colors'] ?? '',
                    'sizes' => $pr['sizes'] ?? '',
                    'discount' => (float)($pr['discount'] ?? 0),
                    'gsm' => $pr['gsm'] ?? '',
                    'waistband' => $pr['waistband'] ?? '',
                    'tiers' => $formatted_tiers
                ];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
            exit;
        }
    } else {
        $products = [
            [ 'id' => 0, 'name' => 'Classic Cotton Brief', 'sku' => 'KB-001', 'cat' => "Men's Briefs", 'moq' => 50, 'price' => 95, 'status' => 'In Stock', 'desc' => "Classic cut men's brief. Suitable for all-day wear.", 'images' => [], 'colors' => '', 'sizes' => 'S,M,L,XL', 'discount' => 0, 'gsm' => '180 GSM', 'waistband' => 'Elastic', 'tiers' => [['q' => 50, 'p' => 120], ['q' => 100, 'p' => 108], ['q' => 500, 'p' => 95]] ]
        ];
    }
    echo json_encode(["status" => "success", "data" => $products]);
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            $input = json_decode(file_get_contents("php://input"), true);
            $id = isset($input['id']) ? (int)$input['id'] : 0;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid Product ID for deletion."]);
            exit;
        }

        if (isset($pdo) && $pdo !== null) {
            try {
                $pdo->beginTransaction();
                // Soft delete product
                $stmt2 = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
                $stmt2->execute([$id]);
                
                $pdo->commit();
                echo json_encode(["status" => "success", "message" => "Product deleted successfully."]);
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["status" => "success", "message" => "Product deleted successfully (Demo Mode)."]);
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
    $sku = trim($input['sku'] ?? '');
    $category_name = trim($input['category_name'] ?? '');
    $description = trim($input['description'] ?? '');
    $moq = isset($input['moq']) ? (int)$input['moq'] : 50;
    $base_price = isset($input['base_price']) ? (float)$input['base_price'] : 0;
    $status = trim($input['status'] ?? 'In Stock');
    $colors = trim($input['colors'] ?? '');
    $sizes = trim($input['sizes'] ?? '');
    $discount = isset($input['discount']) ? (float)$input['discount'] : 0;
    $gsm = trim($input['gsm'] ?? '');
    $waistband = trim($input['waistband'] ?? '');
    
    $tiers = $input['tiers'] ?? []; 
    if (is_string($tiers)) {
        $tiers = json_decode($tiers, true) ?: [];
    }

    if (empty($name) || empty($sku)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Product Name and SKU are required."]);
        exit;
    }

    // Process up to 6 product images
    $images = [];
    
    // Parse manual image URLs first
    if (isset($input['images_urls']) && is_array($input['images_urls'])) {
        $images = $input['images_urls'];
    } elseif (isset($_POST['images_urls']) && is_array($_POST['images_urls'])) {
        $images = $_POST['images_urls'];
    } else {
        for ($i = 0; $i < 6; $i++) {
            $val = trim($input["image_url_$i"] ?? $_POST["image_url_$i"] ?? '');
            $images[$i] = $val;
        }
    }

    // Handle files upload
    $uploadDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    for ($i = 0; $i < 6; $i++) {
        $fileKey = "product_image_file_$i";
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES[$fileKey]['tmp_name'];
                $fileName = $_FILES[$fileKey]['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = md5(time() . $fileName . $i) . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $images[$i] = '/assets/uploads/' . $newFileName;
                    } else {
                        http_response_code(500);
                        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file in slot " . ($i + 1)]);
                        exit;
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Unsupported file type in slot " . ($i + 1)]);
                    exit;
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "File upload error in slot " . ($i + 1) . ": " . $_FILES[$fileKey]['error']]);
                exit;
            }
        }
    }

    // Clean and compact to standard array
    $final_images = [];
    for ($i = 0; $i < 6; $i++) {
        if (!empty($images[$i])) {
            $final_images[] = $images[$i];
        }
    }
    $images_json = json_encode($final_images);

    if (isset($pdo) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Resolve Category ID from Category Name
            $cat_id = null;
            if (!empty($category_name)) {
                $c_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $c_stmt->execute([$category_name]);
                $c_res = $c_stmt->fetch();
                if ($c_res) {
                    $cat_id = $c_res['id'];
                }
            }

            if ($id > 0) {
                // Update product
                $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, category_id = ?, description = ?, moq = ?, base_price = ?, status = ?, images = ?, colors = ?, sizes = ?, discount = ?, gsm = ?, waistband = ? WHERE id = ?");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $gsm, $waistband, $id]);
                $product_id = $id;
            } else {
                // Insert product
                $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, description, moq, base_price, status, images, colors, sizes, discount, gsm, waistband) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $gsm, $waistband]);
                $product_id = $pdo->lastInsertId();
            }

            // Sync Pricing Tiers
            $del_tiers = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?");
            $del_tiers->execute([$product_id]);

            $ins_tier = $pdo->prepare("INSERT INTO pricing_tiers (product_id, min_qty, max_qty, price) VALUES (?, ?, ?, ?)");
            foreach ($tiers as $index => $tier) {
                $min_qty = (int)$tier['q'];
                $price = (float)$tier['p'];
                $next_min = isset($tiers[$index + 1]) ? (int)$tiers[$index + 1]['q'] : null;
                $max_qty = $next_min ? ($next_min - 1) : 999999;
                
                $ins_tier->execute([$product_id, $min_qty, $max_qty, $price]);
            }

            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "Product saved successfully.", "product_id" => $product_id]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "success", "message" => "Product saved successfully (Demo Mode)."]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
