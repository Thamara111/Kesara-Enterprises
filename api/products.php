<?php
/**
 * REST API - Products
 * Manages fetching, creating, updating, and soft-deleting products.
 * Includes dynamic pricing tiers and multiple image uploads logic.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Connect to database
require_once __DIR__ . "/../database/connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Self-healing database check: Ensure all necessary columns exist on the products table
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
        $checkDiscountStart = $pdo->query("SHOW COLUMNS FROM products LIKE 'discount_start'");
        if (!$checkDiscountStart->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN discount_start DATE DEFAULT NULL");
        }
        $checkDiscountEnd = $pdo->query("SHOW COLUMNS FROM products LIKE 'discount_end'");
        if (!$checkDiscountEnd->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN discount_end DATE DEFAULT NULL");
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
        $checkDeleted = $pdo->query("SHOW COLUMNS FROM products LIKE 'deleted_at'");
        if (!$checkDeleted->fetch()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN deleted_at DATETIME DEFAULT NULL");
        }
    } catch (\Exception $e) {
        // Ignored
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS requests (CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET requests to fetch all available products
if ($method === 'GET') {
    $products = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            // Join products with their respective category names
            $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc`, p.images, p.colors, p.sizes, p.discount, p.discount_start, p.discount_end, p.gsm, p.waistband 
                                 FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.id
                                 ORDER BY p.name ASC");
            $prods = $stmt->fetchAll();

            // Iterate over each product to attach its pricing tiers
            foreach ($prods as $pr) {
                // Fetch dynamic pricing tiers for the current product
                $t_stmt = $pdo->prepare("SELECT min_qty AS q, price AS p FROM pricing_tiers WHERE product_id = ?");
                $t_stmt->execute([$pr['id']]);
                $tiers = $t_stmt->fetchAll();

                // Format the tiers for the frontend
                $formatted_tiers = [];
                foreach ($tiers as $t) {
                    $formatted_tiers[] = [
                        'q' => (int)$t['q'],
                        'p' => (float)$t['p']
                    ];
                }

                // Construct the structured response array for this specific product
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
                    'discount_start' => $pr['discount_start'] ?? '',
                    'discount_end' => $pr['discount_end'] ?? '',
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

// Handle POST requests for creating, updating, or deleting products
if ($method === 'POST') {
    // Determine the action (default is 'save' for create/update)
    $action = $_GET['action'] ?? $_POST['action'] ?? 'save';

    // Flow for soft-deleting a product
    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        // Fallback to JSON payload if $_POST is empty
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
                // Fetch product name before deleting
                $stmt_name = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt_name->execute([$id]);
                $prod = $stmt_name->fetch();
                $prod_name = $prod ? $prod['name'] : 'Unknown Product';

                // Soft delete product by setting the deleted_at timestamp
                $stmt2 = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
                $stmt2->execute([$id]);

                // Audit Log
                $details = json_encode(['id' => $id, 'name' => $prod_name]);
                $admin_id = $_SESSION['admin_id'] ?? 1; // Fallback to 1
                $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)")->execute([$admin_id, 'Delete Product', 'Product', $id, $details]);
                
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

    // Default Flow: Save (Create or Update a product)
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    // Extract all product fields, enforcing correct data types
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
    $discount_start = trim($input['discount_start'] ?? '');
    if ($discount_start === '') $discount_start = null;
    $discount_end = trim($input['discount_end'] ?? '');
    if ($discount_end === '') $discount_end = null;
    $gsm = trim($input['gsm'] ?? '');
    $waistband = trim($input['waistband'] ?? '');
    
    // Parse dynamic pricing tiers array (could come as a JSON string from FormData)
    $tiers = $input['tiers'] ?? []; 
    if (is_string($tiers)) {
        $tiers = json_decode($tiers, true) ?: [];
    }

    // Basic required fields validation
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

    // Clean and compact the images array to remove empty slots before converting to JSON
    $final_images = [];
    for ($i = 0; $i < 6; $i++) {
        if (!empty($images[$i])) {
            $final_images[] = $images[$i];
        }
    }
    $images_json = json_encode($final_images);

    // Proceed with inserting or updating the product in the database
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
                // Update an existing product record
                $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, category_id = ?, description = ?, moq = ?, base_price = ?, status = ?, images = ?, colors = ?, sizes = ?, discount = ?, discount_start = ?, discount_end = ?, gsm = ?, waistband = ? WHERE id = ?");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $discount_start, $discount_end, $gsm, $waistband, $id]);
                $product_id = $id;
            } else {
                // Insert a brand new product record
                $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, description, moq, base_price, status, images, colors, sizes, discount, discount_start, discount_end, gsm, waistband) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $discount_start, $discount_end, $gsm, $waistband]);
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

            // Sync Inventory Variants
            $inv_colors = !empty(trim($colors)) ? array_map('trim', explode(',', $colors)) : ['Standard'];
            $inv_sizes = !empty(trim($sizes)) ? array_map('trim', explode(',', $sizes)) : ['M'];

            $check_inv = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? AND colour = ? AND size = ?");
            $ins_inv = $pdo->prepare("INSERT INTO inventory (product_id, colour, size, quantity, restock_min) VALUES (?, ?, ?, 0, 50)");

            foreach ($inv_colors as $c) {
                foreach ($inv_sizes as $s) {
                    $check_inv->execute([$product_id, $c, $s]);
                    if (!$check_inv->fetch()) {
                        $ins_inv->execute([$product_id, $c, $s]);
                    }
                }
            }

            // Audit Log
            $actionName = ($id > 0) ? 'Update Product' : 'Create Product';
            $details = json_encode(['name' => $name, 'sku' => $sku]);
            $admin_id = $_SESSION['admin_id'] ?? 1; // Fallback to 1 if session lost to prevent FK error
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)")->execute([$admin_id, $actionName, 'Product', $product_id, $details]);

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
