<?php
/**
 * Admin Invoices API Endpoint
 * Handles:
 * 1. DB Self-Healing (creating `invoices` and `invoice_items` tables and adding `size` column if missing).
 * 2. Auto-generate next Invoice Number.
 * 3. Search & Suggest Wholesale Shops (Customers).
 * 4. Search & Suggest Products with SKU, Name, Size options & live Stock Availability metrics.
 * 5. Save/Create New Invoices with line items (including Size), policy, and signatures.
 * 6. List Invoice History & Fetch Invoice Details.
 * 7. Update Invoice Status / Delete Invoice.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../database/connection.php";

// Helper function to send JSON response
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if (!isset($pdo) || $pdo === null) {
    sendJson(["status" => "error", "message" => "Database connection failed."], 500);
}

// ----------------------------------------------------
// 1. SELF-HEALING DATABASE SETUP
// ----------------------------------------------------
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL UNIQUE,
        user_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        business_name VARCHAR(100) NULL,
        customer_email VARCHAR(100) NULL,
        customer_phone VARCHAR(30) NULL,
        customer_address TEXT NULL,
        br_number VARCHAR(50) NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_policy TEXT NULL,
        customer_signature LONGTEXT NULL,
        kesara_signature LONGTEXT NULL,
        status ENUM('draft', 'issued', 'paid', 'cancelled') DEFAULT 'issued',
        invoice_date DATE NOT NULL,
        due_date DATE NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        product_id INT NULL,
        product_sku VARCHAR(50) NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        size VARCHAR(30) DEFAULT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure size column exists in invoice_items if table already existed without it
    $checkSize = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'size'");
    if (!$checkSize->fetch()) {
        $pdo->exec("ALTER TABLE invoice_items ADD COLUMN size VARCHAR(30) DEFAULT NULL AFTER product_name");
    }
} catch (\Exception $e) {
    // Database table auto-creation error log
}

// ----------------------------------------------------
// 2. REQUEST INPUT PARSING
// ----------------------------------------------------
$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true) ?? [];

$action = $_GET['action'] ?? $inputData['action'] ?? $_POST['action'] ?? '';

// ----------------------------------------------------
// 3. ACTION DISPATCHER
// ----------------------------------------------------
switch ($action) {

    // --- Generate Next Invoice Number ---
    case 'next_invoice_number':
        try {
            $maxStmt = $pdo->query("SELECT MAX(id) FROM invoices");
            $nextId = ((int)$maxStmt->fetchColumn()) + 1;
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            sendJson(["status" => "success", "invoice_number" => $invoice_number]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Suggest Wholesale Shops (Customers) ---
    case 'search_customers':
        $query = trim($_GET['query'] ?? $inputData['query'] ?? $_POST['query'] ?? '');
        try {
            if ($query !== '') {
                $stmt = $pdo->prepare("SELECT id, first_name, last_name, business_name, email, phone, whatsapp_number, address, br_number, business_type, status 
                                       FROM users 
                                       WHERE business_name LIKE ? 
                                          OR first_name LIKE ? 
                                          OR last_name LIKE ? 
                                          OR email LIKE ? 
                                          OR phone LIKE ? 
                                          OR br_number LIKE ?
                                       ORDER BY business_name ASC 
                                       LIMIT 15");
                $searchTerm = "%{$query}%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            } else {
                $stmt = $pdo->query("SELECT id, first_name, last_name, business_name, email, phone, whatsapp_number, address, br_number, business_type, status 
                                     FROM users 
                                     ORDER BY business_name ASC 
                                     LIMIT 15");
            }
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format customer full names for easy display
            foreach ($customers as &$c) {
                $c['full_name'] = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                if (empty($c['business_name'])) {
                    $c['business_name'] = $c['full_name'];
                }
            }

            sendJson(["status" => "success", "data" => $customers]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Suggest Products & Live Stock Availability ---
    case 'search_products':
        $query = trim($_GET['query'] ?? $inputData['query'] ?? $_POST['query'] ?? '');
        try {
            // Check if sizes column exists in products
            $hasSizesCol = false;
            try {
                $chkSizes = $pdo->query("SHOW COLUMNS FROM products LIKE 'sizes'");
                if ($chkSizes && $chkSizes->fetch()) {
                    $hasSizesCol = true;
                }
            } catch (\Exception $ex) {}

            $sizesSelect = $hasSizesCol ? "p.sizes," : "'' AS sizes,";

            $sql = "SELECT p.id, p.sku, p.name, {$sizesSelect} p.base_price, p.status AS product_status, p.moq, c.name AS category_name,
                           COALESCE(SUM(inv.quantity), 0) AS total_stock
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN inventory inv ON p.id = inv.product_id
                    WHERE p.deleted_at IS NULL";

            $params = [];
            if ($query !== '') {
                $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)";
                $searchTerm = "%{$query}%";
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }

            $sql .= " GROUP BY p.id ORDER BY p.name ASC LIMIT 20";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format availability badges and metadata
            foreach ($products as &$prod) {
                $totalStock = (int)$prod['total_stock'];
                if ($totalStock > 50) {
                    $prod['availability_status'] = 'In Stock';
                    $prod['availability_class'] = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                } elseif ($totalStock > 0) {
                    $prod['availability_status'] = 'Low Stock';
                    $prod['availability_class'] = 'bg-amber-100 text-amber-800 border-amber-300';
                } else {
                    $prod['availability_status'] = 'Out of Stock';
                    $prod['availability_class'] = 'bg-red-100 text-red-800 border-red-300';
                }

                // Fetch sizes from inventory variants if p.sizes is empty
                if (empty($prod['sizes'])) {
                    $invStmt = $pdo->prepare("SELECT DISTINCT size FROM inventory WHERE product_id = ? AND size IS NOT NULL AND size != ''");
                    $invStmt->execute([$prod['id']]);
                    $invSizes = $invStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($invSizes)) {
                        $prod['sizes'] = implode(', ', $invSizes);
                    } else {
                        $prod['sizes'] = 'S, M, L, XL, XXL';
                    }
                }
            }

            sendJson(["status" => "success", "data" => $products]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Save / Create New Invoice ---
    case 'save_invoice':
        try {
            $user_id = !empty($inputData['user_id']) ? (int)$inputData['user_id'] : null;
            $customer_name = trim($inputData['customer_name'] ?? '');
            $business_name = trim($inputData['business_name'] ?? '');
            $customer_email = trim($inputData['customer_email'] ?? '');
            $customer_phone = trim($inputData['customer_phone'] ?? '');
            $customer_address = trim($inputData['customer_address'] ?? '');
            $br_number = trim($inputData['br_number'] ?? '');

            $invoice_number = trim($inputData['invoice_number'] ?? '');
            if (empty($invoice_number)) {
                // Generate auto invoice number e.g. INV-2026-0001
                $maxStmt = $pdo->query("SELECT MAX(id) FROM invoices");
                $nextId = ((int)$maxStmt->fetchColumn()) + 1;
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            $invoice_date = !empty($inputData['invoice_date']) ? $inputData['invoice_date'] : date('Y-m-d');
            $due_date = !empty($inputData['due_date']) ? $inputData['due_date'] : date('Y-m-d', strtotime('+14 days'));
            $status = in_array($inputData['status'] ?? '', ['draft', 'issued', 'paid', 'cancelled']) ? $inputData['status'] : 'issued';

            $subtotal = (float)($inputData['subtotal'] ?? 0.00);
            $tax_amount = (float)($inputData['tax_amount'] ?? 0.00);
            $discount_amount = (float)($inputData['discount_amount'] ?? 0.00);
            $total_amount = (float)($inputData['total_amount'] ?? 0.00);

            $payment_policy = trim($inputData['payment_policy'] ?? '');
            $customer_signature = $inputData['customer_signature'] ?? null;
            $kesara_signature = $inputData['kesara_signature'] ?? null;
            $created_by = $_SESSION['admin_id'] ?? null;

            $items = $inputData['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                sendJson(["status" => "error", "message" => "Invoice must contain at least one product item."], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO invoices (
                invoice_number, user_id, customer_name, business_name, customer_email, customer_phone, 
                customer_address, br_number, subtotal, tax_amount, discount_amount, total_amount, 
                payment_policy, customer_signature, kesara_signature, status, invoice_date, due_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $invoice_number, $user_id, $customer_name, $business_name, $customer_email, $customer_phone,
                $customer_address, $br_number, $subtotal, $tax_amount, $discount_amount, $total_amount,
                $payment_policy, $customer_signature, $kesara_signature, $status, $invoice_date, $due_date, $created_by
            ]);

            $invoice_id = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO invoice_items (
                invoice_id, product_id, product_sku, product_name, size, quantity, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $prod_id = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                $sku = trim($item['product_sku'] ?? 'N/A');
                $pname = trim($item['product_name'] ?? 'Product');
                $size = trim($item['size'] ?? 'Standard');
                $qty = (int)($item['quantity'] ?? 1);
                $unit_price = (float)($item['unit_price'] ?? 0.00);
                $line_total = (float)($item['total_price'] ?? ($qty * $unit_price));

                $itemStmt->execute([$invoice_id, $prod_id, $sku, $pname, $size, $qty, $unit_price, $line_total]);
            }

            $pdo->commit();

            sendJson([
                "status" => "success",
                "message" => "Invoice created successfully!",
                "invoice_id" => $invoice_id,
                "invoice_number" => $invoice_number
            ]);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJson(["status" => "error", "message" => "Failed to save invoice: " . $e->getMessage()], 500);
        }
        break;

    // --- List Invoices / History ---
    case 'list_invoices':
        try {
            $search = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');

            $sql = "SELECT i.*, 
                           COUNT(ii.id) AS item_count 
                    FROM invoices i 
                    LEFT JOIN invoice_items ii ON i.id = ii.invoice_id";
            $where = [];
            $params = [];

            if ($search !== '') {
                $where[] = "(i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.business_name LIKE ? OR i.customer_email LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_fill(0, 4, $searchTerm);
            }

            if (!empty($statusFilter) && $statusFilter !== 'all') {
                $where[] = "i.status = ?";
                $params[] = $statusFilter;
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " GROUP BY i.id ORDER BY i.created_at DESC, i.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJson(["status" => "success", "data" => $invoices]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Get Single Invoice Details ---
    case 'get_invoice':
        $id = (int)($_GET['id'] ?? $inputData['id'] ?? 0);
        if ($id <= 0) {
            sendJson(["status" => "error", "message" => "Invalid invoice ID."], 400);
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt->execute([$id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                sendJson(["status" => "error", "message" => "Invoice not found."], 404);
            }

            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
            $itemStmt->execute([$id]);
            $invoice['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            sendJson(["status" => "success", "data" => $invoice]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Update Status ---
    case 'update_status':
        $id = (int)($inputData['id'] ?? 0);
        $newStatus = trim($inputData['status'] ?? '');
        if ($id <= 0 || !in_array($newStatus, ['draft', 'issued', 'paid', 'cancelled'])) {
            sendJson(["status" => "error", "message" => "Invalid parameters."], 400);
        }
        try {
            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            sendJson(["status" => "success", "message" => "Invoice status updated to " . ucfirst($newStatus)]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    // --- Delete Invoice ---
    case 'delete_invoice':
        $id = (int)($inputData['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            sendJson(["status" => "error", "message" => "Invalid invoice ID."], 400);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$id]);
            sendJson(["status" => "success", "message" => "Invoice deleted successfully."]);
        } catch (\Exception $e) {
            sendJson(["status" => "error", "message" => $e->getMessage()], 500);
        }
        break;

    default:
        sendJson(["status" => "error", "message" => "Action '{$action}' not recognized."], 400);
        break;
}
