<?php
/**
 * Products Management API
 * Helps fetch product catalog details, add new products, edit existing items, handle multi-image uploads, and soft-delete products.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Getting data -> Loading database connection settings
require_once __DIR__ . "/../database/connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Getting data -> Ensuring products table contains all required columns
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
        // Ignore database structure check errors if columns already exist
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Checking request -> Handling browser OPTIONS preflight CORS checks
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Getting data -> Fetching all active products with categories and tier pricing
if ($method === 'GET') {
    $products = [];
    if (isset($pdo) && $pdo !== null) {
        try {
            // Getting data -> Fetching products joined with category names
            $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc`, p.images, p.colors, p.sizes, p.discount, p.discount_start, p.discount_end, p.gsm, p.waistband 
                                 FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.id
                                 ORDER BY p.name ASC");
            $prods = $stmt->fetchAll();

            // Processing data -> Fetching pricing tiers and calculating discounts for each product
            foreach ($prods as $pr) {
                /*
                // [VIVA TASK 03 - API: Tiered Pricing Max Limit Query]
                $t_stmt = $pdo->prepare("SELECT min_qty AS q, max_qty AS max_q, price AS p FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC");
                $t_stmt->execute([$pr['id']]);
                $tiers = $t_stmt->fetchAll();

                $formatted_tiers = [];
                foreach ($tiers as $t) {
                    $formatted_tiers[] = [
                        'q' => (int)$t['q'],
                        'max_q' => !empty($t['max_q']) ? (int)$t['max_q'] : null,
                        'p' => (float)$t['p']
                    ];
                }
                */
                // Getting data -> Fetching pricing tiers for current product
                $t_stmt = $pdo->prepare("SELECT min_qty AS q, price AS p FROM pricing_tiers WHERE product_id = ?");
                $t_stmt->execute([$pr['id']]);
                $tiers = $t_stmt->fetchAll();

                // Formatting data -> Formatting pricing tiers for catalog display
                $formatted_tiers = [];
                foreach ($tiers as $t) {
                    $formatted_tiers[] = [
                        'q' => (int)$t['q'],
                        'p' => (float)$t['p']
                    ];
                }

                // Processing data -> Calculating active discount status and effective price
                $today_str = date('Y-m-d');
                $base_price = (float)$pr['price'];
                $discount_val = (float)($pr['discount'] ?? 0);
                $d_start = !empty($pr['discount_start']) ? $pr['discount_start'] : null;
                $d_end   = !empty($pr['discount_end'])   ? $pr['discount_end']   : null;
                $is_discount_active = false;
                if ($discount_val > 0) {
                    $valid_s = empty($d_start) || ($today_str >= $d_start);
                    $valid_e = empty($d_end)   || ($today_str <= $d_end);
                    if ($valid_s && $valid_e) {
                        $is_discount_active = true;
                    }
                }
                $effective_price = $is_discount_active ? round($base_price * (1 - ($discount_val / 100)), 2) : $base_price;

                // Formatting data -> Building product payload for response
                $effective_product_moq = !empty($formatted_tiers) ? (int)$formatted_tiers[0]['q'] : (int)$pr['moq'];
                $products[] = [
                    'id' => (int)$pr['id'],
                    'name' => $pr['name'],
                    'sku' => $pr['sku'],
                    'cat' => $pr['cat'] ?? 'Uncategorized',
                    'moq' => $effective_product_moq,
                    'price' => $base_price,
                    'status' => $pr['status'],
                    'desc' => $pr['desc'] ?? '',
                    'images' => json_decode($pr['images'] ?? '[]', true) ?: [],
                    'colors' => $pr['colors'] ?? '',
                    'sizes' => $pr['sizes'] ?? '',
                    'discount' => $discount_val,
                    'is_discount_active' => $is_discount_active,
                    'effective_price' => $effective_price,
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

// Processing request -> Handling POST requests for saving or soft-deleting products
if ($method === 'POST') {
    // Getting data -> Reading requested action parameter
    $action = $_GET['action'] ?? $_POST['action'] ?? 'save';

    // Deleting data -> Soft-deleting product record
    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        // Getting data -> Reading product ID from JSON payload
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
                // Getting data -> Fetching product name before deletion
                $stmt_name = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt_name->execute([$id]);
                $prod = $stmt_name->fetch();
                $prod_name = $prod ? $prod['name'] : 'Unknown Product';

                // Deleting data -> Setting deleted_at timestamp for soft deletion
                $stmt2 = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
                $stmt2->execute([$id]);

                // Saving log -> Recording audit log for deleted product
                $details = json_encode(['id' => $id, 'name' => $prod_name]);
                $admin_id = $_SESSION['admin_id'] ?? 1;
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

    // Saving data -> Creating or updating product record
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }

    // Getting data -> Extracting product fields with type formatting
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
    
    // Getting data -> Reading and parsing pricing tiers array
    $tiers = $input['tiers'] ?? []; 
    if (is_string($tiers)) {
        $tiers = json_decode($tiers, true) ?: [];
    }

    // Checking data -> Validating that product name and SKU are provided
    if (empty($name) || empty($sku)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Product Name and SKU are required."]);
        exit;
    }

    // Getting data -> Processing product image URLs
    $images = [];
    
    // Getting data -> Reading image URLs from request
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

    // Uploading file -> Saving uploaded image files to server uploads directory
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

    // Formatting data -> Compacting image list and encoding to JSON
    $final_images = [];
    for ($i = 0; $i < 6; $i++) {
        if (!empty($images[$i])) {
            $final_images[] = $images[$i];
        }
    }
    $images_json = json_encode($final_images);

    // Saving data -> Inserting or updating product in database
    if (isset($pdo) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Getting data -> Looking up category ID from category name
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
                // Updating data -> Saving updated product details to database
                $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, category_id = ?, description = ?, moq = ?, base_price = ?, status = ?, images = ?, colors = ?, sizes = ?, discount = ?, discount_start = ?, discount_end = ?, gsm = ?, waistband = ? WHERE id = ?");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $discount_start, $discount_end, $gsm, $waistband, $id]);
                $product_id = $id;
            } else {
                // Saving data -> Inserting new product record into database
                $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, description, moq, base_price, status, images, colors, sizes, discount, discount_start, discount_end, gsm, waistband) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $cat_id, $description, $moq, $base_price, $status, $images_json, $colors, $sizes, $discount, $discount_start, $discount_end, $gsm, $waistband]);
                $product_id = $pdo->lastInsertId();
            }

            // Updating data -> Saving pricing tiers for product
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

            // Updating data -> Creating or updating inventory variants for colors and sizes
            $color_variations_json = $input['color_variations'] ?? $_POST['color_variations'] ?? '';
            $color_variations = !empty($color_variations_json) ? json_decode($color_variations_json, true) : null;

            $check_inv = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ? AND colour = ? AND size = ?");
            $ins_inv = $pdo->prepare("INSERT INTO inventory (product_id, colour, size, quantity, restock_min) VALUES (?, ?, ?, 0, 50)");

            if (is_array($color_variations) && !empty($color_variations)) {
                // Updating data -> Removing obsolete inventory variant rows
                if ($id > 0) {
                    $existing_inv = $pdo->prepare("SELECT id, colour, size FROM inventory WHERE product_id = ?");
                    $existing_inv->execute([$id]);
                    $all_existing = $existing_inv->fetchAll();

                    foreach ($all_existing as $e_row) {
                        $e_color = trim($e_row['colour'] ?? '');
                        $e_size = trim($e_row['size'] ?? '');
                        $still_exists = isset($color_variations[$e_color]) && is_array($color_variations[$e_color]) && in_array($e_size, $color_variations[$e_color]);
                        if (!$still_exists) {
                            $del_inv = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                            $del_inv->execute([$e_row['id']]);
                        }
                    }
                }

                // Saving data -> Inserting new color and size inventory variant rows
                $derived_colors_arr = [];
                $derived_sizes_set = [];

                foreach ($color_variations as $c_name => $sizes_arr) {
                    if (!is_array($sizes_arr)) continue;
                    $c_clean = trim($c_name);
                    if (empty($c_clean)) continue;
                    $derived_colors_arr[] = $c_clean;

                    foreach ($sizes_arr as $s_name) {
                        $s_clean = trim($s_name);
                        if (empty($s_clean)) continue;
                        if (!in_array($s_clean, $derived_sizes_set)) {
                            $derived_sizes_set[] = $s_clean;
                        }

                        $check_inv->execute([$product_id, $c_clean, $s_clean]);
                        if (!$check_inv->fetch()) {
                            $ins_inv->execute([$product_id, $c_clean, $s_clean]);
                        }
                    }
                }

                $derived_colors_str = implode(',', $derived_colors_arr);
                $derived_sizes_str = implode(',', $derived_sizes_set);

                $upd_p_attrs = $pdo->prepare("UPDATE products SET colors = ?, sizes = ? WHERE id = ?");
                $upd_p_attrs->execute([$derived_colors_str, $derived_sizes_str, $product_id]);

            } else {
                // Fallback -> Creating standard inventory color and size combinations
                $inv_colors = !empty(trim($colors)) ? array_map('trim', explode(',', $colors)) : ['Standard'];
                $inv_sizes = !empty(trim($sizes)) ? array_map('trim', explode(',', $sizes)) : ['M'];

                foreach ($inv_colors as $c) {
                    foreach ($inv_sizes as $s) {
                        $check_inv->execute([$product_id, $c, $s]);
                        if (!$check_inv->fetch()) {
                            $ins_inv->execute([$product_id, $c, $s]);
                        }
                    }
                }
            }

            // Updating data -> Syncing supplier assignment and unit cost
            $supplier_name = trim($input['supplier_name'] ?? $_POST['supplier_name'] ?? '');
            if (!empty($supplier_name)) {
                // Getting data -> Ensuring supplier_items table has unit_cost column
                try {
                    $checkUC = $pdo->query("SHOW COLUMNS FROM supplier_items LIKE 'unit_cost'");
                    if (!$checkUC->fetch()) {
                        $pdo->exec("ALTER TABLE supplier_items ADD COLUMN unit_cost DECIMAL(10,2) DEFAULT NULL");
                    }
                } catch (\Exception $e) {}

                $s_stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
                $s_stmt->execute([$supplier_name]);
                $supp_res = $s_stmt->fetch();
                if ($supp_res) {
                    $supp_id = (int)$supp_res['id'];

                    // Updating data -> Syncing supplier item record
                    $chk_si = $pdo->prepare("SELECT id FROM supplier_items WHERE supplier_id = ? AND item_name = ?");
                    $chk_si->execute([$supp_id, $name]);
                    if (!$chk_si->fetch()) {
                        $ins_si = $pdo->prepare("INSERT INTO supplier_items (supplier_id, item_name, unit_cost) VALUES (?, ?, ?)");
                        $ins_si->execute([$supp_id, $name, $base_price]);
                    } else {
                        $upd_si = $pdo->prepare("UPDATE supplier_items SET unit_cost = ? WHERE supplier_id = ? AND item_name = ?");
                        $upd_si->execute([$base_price, $supp_id, $name]);
                    }

                    // Updating data -> Syncing supplier product relation
                    $chk_sp = $pdo->prepare("SELECT id FROM supplier_products WHERE supplier_id = ? AND product_id = ?");
                    $chk_sp->execute([$supp_id, $product_id]);
                    if (!$chk_sp->fetch()) {
                        $ins_sp = $pdo->prepare("INSERT INTO supplier_products (supplier_id, product_id, unit_cost) VALUES (?, ?, ?)");
                        $ins_sp->execute([$supp_id, $product_id, $base_price]);
                    } else {
                        $upd_sp = $pdo->prepare("UPDATE supplier_products SET unit_cost = ? WHERE supplier_id = ? AND product_id = ?");
                        $upd_sp->execute([$base_price, $supp_id, $product_id]);
                    }
                }
            }

            // Saving log -> Recording audit log for created or updated product
            $actionName = ($id > 0) ? 'Update Product' : 'Create Product';
            $details = json_encode(['name' => $name, 'sku' => $sku]);
            $admin_id = $_SESSION['admin_id'] ?? 1;
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

/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/products.php (REST API Product Catalog & Admin Inventory Handler)

 CONNECTED / DEPENDENT FILES:
   - Database Connection: database/connection.php
   - Customer Catalog: product_catalog.php
   - Product Details: product_detail.php
   - Admin Inventory View: admin/view/products.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Database Schema: `products`, `categories`, `pricing_tiers` tables
   - Catalog Display: product_catalog.php (if changing returned JSON keys)
   - Admin Product Management: admin/view/products.view.php (if updating product creation fields)
=============================================================================
*/

