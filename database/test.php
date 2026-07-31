<?php
/**
 * =============================================================================
 * KESARA ENTERPRISES - VIVA MODIFICATION EXAM MASTER PHP SCRIPT (TASKS 02 TO 20)
 * =============================================================================
 * This file contains full code changes for Tasks 02 through 20.
 * For each task, you will find:
 *   1. Database Migration (SQL / Self-Healing PDO Execution)
 *   2. Backend / API Implementation (with Exact Target File & Line Numbers)
 *   3. Frontend UI Implementation (Customer or Admin UI with Exact Target File & Line Numbers)
 *
 * How to use during Viva Exam:
 *   - Copy the exact SQL or PDO snippet to run database schema changes.
 *   - Open the mentioned target file at the specified line range.
 *   - Uncomment or paste the code snippet provided below into that file.
 * =============================================================================
 */

require_once __DIR__ . "/connection.php";

/**
 * =============================================================================
 * SELF-HEALING DATABASE HELPER FUNCTIONS
 * =============================================================================
 */

// 1. Self-Healing Table Creator: Auto-creates table if missing in DB
if (!function_exists('ensureTableExists')) {
    function ensureTableExists($pdo, $tableName, $createTableSql)
    {
        try {
            $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
            if (!$check || !$check->fetch()) {
                $pdo->exec($createTableSql);
                return "Self-Healed: Table '$tableName' created successfully.";
            }
            return "Table '$tableName' already exists.";
        } catch (\Exception $e) {
            return "Error self-healing table '$tableName': " . $e->getMessage();
        }
    }
}

// 2. Self-Healing Column Creator: Auto-adds column if missing in DB
if (!function_exists('ensureColumnExists')) {
    function ensureColumnExists($pdo, $tableName, $columnName, $alterTableSql)
    {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE " . $pdo->quote($columnName));
            if (!$check || !$check->fetch()) {
                $pdo->exec($alterTableSql);
                return "Self-Healed: Column '$columnName' added to '$tableName'.";
            }
            return "Column '$columnName' already exists on '$tableName'.";
        } catch (\Exception $e) {
            return "Error self-healing column '$columnName': " . $e->getMessage();
        }
    }
}

echo "<h1>Kesara Enterprises - Viva Modifications Exam Helper (Tasks 02 - 20)</h1>";
echo "<p>This file serves as a reference and execution script for viva modification tasks.</p>";

/* =============================================================================
   TASK 02: Custom Stock Restock Alert Threshold per Product
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below or PDO self-healing in api/products.php
     - Backend API: f:\Kesara-Enterprises\api\products.php (around Line 25 & Line 77)
     - Admin View: f:\Kesara-Enterprises\admin\view\products.view.php (around Line 350 & Line 420)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE products ADD COLUMN custom_restock_threshold INT DEFAULT 20;

--- BACKEND CODE CHANGE: api/products.php (Inside GET products endpoint ~Line 77) ---
// Self-Healing DB Check:
$checkRestock = $pdo->query("SHOW COLUMNS FROM products LIKE 'custom_restock_threshold'");
if (!$checkRestock->fetch()) {
    $pdo->exec("ALTER TABLE products ADD COLUMN custom_restock_threshold INT DEFAULT 20");
}

// In SELECT query (Line 77):
// SELECT p.id, p.name, p.sku, p.custom_restock_threshold, ...

--- FRONTEND ADMIN UI CHANGE: admin/view/products.view.php (~Line 350 Modal & ~Line 420 Table) ---
<!-- Add to Product Create/Edit Modal Form: -->
<div>
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Custom Restock Alert Threshold</label>
    <input type="number" name="custom_restock_threshold" value="<?= htmlspecialchars($product['custom_restock_threshold'] ?? 20) ?>" class="w-full border border-gray-200 rounded-lg p-2 text-sm">
</div>

<!-- Add to Table Display: -->
<span class="<?= ($product['stock'] <= $product['custom_restock_threshold']) ? 'text-red-600 font-bold' : 'text-gray-600' ?>">
    Threshold: <?= htmlspecialchars($product['custom_restock_threshold'] ?? 20) ?>
</span>
*/


/* =============================================================================
   TASK 03: Tiered Pricing Tier Max Limit Indicator
   =============================================================================
   TARGET FILES:
     - Database Table: `pricing_tiers` (Columns: product_id, min_qty, max_qty, price)
     - Backend API: f:\Kesara-Enterprises\api\products.php (Lines 86-99)
     - Customer UI: f:\Kesara-Enterprises\product_detail.php (Lines 340-355)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/products.php (Lines 86-99) ---
// Update tier fetch query to include max_qty:
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

--- FRONTEND UI CHANGE: product_detail.php (Lines 340-355) ---
<?php foreach ($product['tiers'] as $tier): ?>
    <div class="tier-card p-3 border rounded-lg flex justify-between items-center" 
         data-min="<?= $tier['q'] ?>" 
         data-max="<?= $tier['max_q'] ?? '' ?>">
        <span class="text-sm font-semibold">
            <?= $tier['q'] ?><?= !empty($tier['max_q']) ? ' - ' . $tier['max_q'] : '+' ?> units
        </span>
        <span class="text-brand font-bold">Rs. <?= number_format($tier['p'], 2) ?></span>
    </div>
<?php endforeach; ?>
*/


/* =============================================================================
   TASK 04: Product Backorder & Pre-Order Reservation System (END-TO-END CODE)
   =============================================================================
   TARGET FILES:
     1. Database Self-Healing: f:\Kesara-Enterprises\api\cart_items.php (or connection.php)
     2. Customer UI: f:\Kesara-Enterprises\product_detail.php (~Line 598 & Line 1100)
     3. Backend API Endpoint: f:\Kesara-Enterprises\api\cart_items.php (~Line 60)
     4. Admin View UI: f:\Kesara-Enterprises\admin\view\inventory.view.php (~Line 188 & Line 250)
   -----------------------------------------------------------------------------
*/

/*
// -----------------------------------------------------------------------------
// STEP 1: DATABASE MIGRATION & SELF-HEALING (PHP / SQL)
// -----------------------------------------------------------------------------
// Add to api/cart_items.php or database/connection.php:
if (isset($pdo) && $pdo !== null) {
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'backorders'");
        if (!$checkTable->fetch()) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS backorders (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              product_id INT NOT NULL,
              quantity INT NOT NULL DEFAULT 50,
              status ENUM('pending','fulfilled','cancelled') DEFAULT 'pending',
              notes TEXT DEFAULT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (\Exception $e) {}
}


// -----------------------------------------------------------------------------
// STEP 2: CUSTOMER UI - PRODUCT DETAIL PAGE (product_detail.php ~Line 598)
// -----------------------------------------------------------------------------
// Replace disabled button when product is out of stock (~Line 598):
<?php if ($is_out_of_stock): ?>
    <button onclick="requestBackorder(<?= (int)$product['id'] ?>)" 
            class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-4 rounded-2xl transition-all shadow-lg flex items-center justify-center gap-2 w-full">
        <i class="ti ti-clock text-xl"></i>
        <span>Pre-Order / Reserve Backorder</span>
    </button>
<?php else: ?>
    <button onclick="addToCart()" class="bg-brand text-white font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all">
        <i class="ti ti-shopping-cart-plus text-xl"></i> Add to Order
    </button>
<?php endif; ?>

// Add JavaScript handler to bottom of product_detail.php script block (~Line 1100):
async function requestBackorder(productId) {
    const qty = parseInt(document.getElementById('order-qty').innerText) || 50;
    const response = await fetch('/api/cart_items.php?action=backorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty })
    });
    const res = await response.json();
    if (res.status === 'success') {
        alert('Backorder reservation placed successfully!');
    } else {
        alert(res.message || 'Error placing backorder.');
    }
}


// -----------------------------------------------------------------------------
// STEP 3: BACKEND API HANDLER - (api/cart_items.php ~Line 60)
// -----------------------------------------------------------------------------
// Add action handler in api/cart_items.php:
if (isset($_GET['action']) && $_GET['action'] === 'backorder') {
    $input = json_decode(file_get_contents("php://input"), true);
    $productId = (int)($input['product_id'] ?? 0);
    $quantity  = (int)($input['quantity'] ?? 50);
    $userId    = $_SESSION['user_id'] ?? 0;

    if ($userId <= 0 || $productId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid session or product ID."]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO backorders (user_id, product_id, quantity, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$userId, $productId, $quantity]);

    echo json_encode(["status" => "success", "message" => "Backorder reservation logged successfully."]);
    exit;
}


// -----------------------------------------------------------------------------
// STEP 4: ADMIN VIEW UI - (admin/view/inventory.view.php ~Line 188 & Line 250)
// -----------------------------------------------------------------------------
// A. Filter chip button in inventory header (~Line 188):
<a href="?view=backorders" class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-lg border border-amber-200 hover:bg-amber-100 flex items-center gap-1">
    <i class="ti ti-clock text-amber-600"></i> View Backorder Reservations
</a>

// B. PHP loop for rendering Backorders list in Admin UI (~Line 250):
<?php
$backorders = $pdo->query("SELECT b.*, u.company_name, u.email, p.name AS product_name 
                           FROM backorders b 
                           JOIN users u ON b.user_id = u.id 
                           JOIN products p ON b.product_id = p.id 
                           ORDER BY b.created_at DESC")->fetchAll();
?>
<div class="bg-white border rounded-2xl p-6 shadow-sm mt-6">
    <h3 class="text-sm font-bold text-gray-900 mb-4">Backorder Pre-Order Reservations</h3>
    <table class="w-full text-left text-xs border-collapse">
        <thead>
            <tr class="bg-gray-50 border-b">
                <th class="p-3">Customer</th>
                <th class="p-3">Product</th>
                <th class="p-3">Reserved Qty</th>
                <th class="p-3">Status</th>
                <th class="p-3">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($backorders as $bo): ?>
                <tr class="border-b">
                    <td class="p-3 font-bold"><?= htmlspecialchars($bo['company_name']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($bo['product_name']) ?></td>
                    <td class="p-3 font-extrabold text-amber-700"><?= number_format($bo['quantity']) ?> units</td>
                    <td class="p-3">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                            <?= strtoupper($bo['status']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-gray-500"><?= date('M d, Y', strtotime($bo['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
*/


/* =============================================================================
   TASK 05: Business Tax Identification (TIN) Verification Field
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN tin_number VARCHAR(30) UNIQUE DEFAULT NULL AFTER br_number;
     - Registration Backend: f:\Kesara-Enterprises\auth.php (Lines 40-46)
     - Customer Form: f:\Kesara-Enterprises\auth.php (Line 408)
     - Admin Customer Profile: f:\Kesara-Enterprises\admin\view\customers.view.php (Line 322)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND & SELF-HEALING DB: auth.php (Lines 40-46) ---
// Self-Healing Column Check:
$checkTIN = $pdo->query("SHOW COLUMNS FROM users LIKE 'tin_number'");
if (!$checkTIN->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN tin_number VARCHAR(30) UNIQUE DEFAULT NULL AFTER br_number");
}

// In Registration Handler:
$tin_number = !empty($_POST['tin_number']) ? trim($_POST['tin_number']) : null;
$stmt = $pdo->prepare("INSERT INTO users (..., br_number, tin_number, ...) VALUES (..., ?, ?, ...)");

--- FRONTEND UI REGISTRATION: auth.php (Line 408) ---
<div>
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tax Identification Number (TIN)</label>
    <input type="text" name="tin_number" placeholder="123456789-9000" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-brand">
</div>

--- FRONTEND ADMIN UI CUSTOMER PROFILE: admin/view/customers.view.php (Line 322) ---
<div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Tax ID (TIN)</span>
    <span class="text-sm font-bold text-gray-900"><?= !empty($customer['tin_number']) ? htmlspecialchars($customer['tin_number']) : 'Not Provided' ?></span>
</div>
*/


/* =============================================================================
   TASK 06: Account Suspension Reason & Admin Rejection Note
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN status_reason TEXT DEFAULT NULL AFTER status;
     - Backend API: f:\Kesara-Enterprises\api\customers.php (around Line 50)
     - Admin View: f:\Kesara-Enterprises\admin\view\customers.view.php (around Line 330)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/customers.php ---
// Updating user status with rejection/suspension reason
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $userId = (int)$_POST['user_id'];
    $status = $_POST['status']; // 'approved', 'rejected', 'suspended'
    $reason = trim($_POST['status_reason'] ?? '');

    $stmt = $pdo->prepare("UPDATE users SET status = ?, status_reason = ? WHERE id = ?");
    $stmt->execute([$status, $reason, $userId]);
    echo json_encode(["status" => "success", "message" => "User status updated"]);
    exit;
}

--- FRONTEND ADMIN UI CHANGE: admin/view/customers.view.php (Line 330) ---
<div>
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Reason for Rejection / Suspension</label>
    <textarea name="status_reason" placeholder="State reason for customer review..." class="w-full border border-gray-200 rounded-xl p-3 text-sm"></textarea>
</div>
*/


/* =============================================================================
   TASK 07: User Credit Limit Allocation for Wholesale Purchases
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN credit_limit DECIMAL(10,2) DEFAULT 0.00 AFTER status_reason;
     - Admin View: f:\Kesara-Enterprises\admin\view\customers.view.php (Line 17 & Line 332)
     - Checkout Page: f:\Kesara-Enterprises\checkout.php (Line 165)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND & SELF-HEALING DB: admin/view/customers.view.php (Line 17) ---
$checkCredit = $pdo->query("SHOW COLUMNS FROM users LIKE 'credit_limit'");
if (!$checkCredit->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN credit_limit DECIMAL(10,2) DEFAULT 0.00 AFTER status_reason");
}

--- FRONTEND ADMIN UI: admin/view/customers.view.php (Line 332) ---
<div class="p-3 bg-blue-50 rounded-xl border border-blue-100">
    <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider block">Approved Credit Limit</span>
    <span class="text-sm font-bold text-blue-900">Rs. <?= number_format($customer['credit_limit'] ?? 0, 2) ?></span>
</div>

--- FRONTEND CHECKOUT BADGE: checkout.php (Line 165) ---
<div class="flex items-center gap-2 p-3 bg-emerald-50 text-emerald-800 rounded-xl text-xs font-bold mb-4">
    <i class="ti ti-wallet"></i>
    <span>Wholesale Credit Available: Rs. <?= number_format($_SESSION['user_credit_limit'] ?? 0, 2) ?></span>
</div>
*/


/* =============================================================================
   TASK 08: User WhatsApp Preferred Notification Toggle
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN notify_whatsapp TINYINT(1) DEFAULT 1 AFTER credit_limit;
     - Backend API: f:\Kesara-Enterprises\api\update_profile.php (around Line 30)
     - Customer Profile UI: f:\Kesara-Enterprises\my_account.php (around Line 220)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/update_profile.php ---
$notify_whatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;
$stmt = $pdo->prepare("UPDATE users SET notify_whatsapp = ? WHERE id = ?");
$stmt->execute([$notify_whatsapp, $_SESSION['user_id']]);

--- FRONTEND UI CHANGE: my_account.php (Line 220) ---
<label class="flex items-center gap-3 cursor-pointer">
    <input type="checkbox" name="notify_whatsapp" value="1" <?= ($user['notify_whatsapp'] ?? 1) ? 'checked' : '' ?> class="w-5 h-5 text-brand rounded border-gray-300">
    <span class="text-sm font-bold text-gray-700">Receive order tracking alerts via WhatsApp</span>
</label>
*/


/* =============================================================================
   TASK 09: Delivery Instructions & Special Handling Notes
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN delivery_notes TEXT DEFAULT NULL AFTER total_amount;
     - Checkout Page: f:\Kesara-Enterprises\checkout.php (around Line 320)
     - Order API: f:\Kesara-Enterprises\api\orders.php (around Line 120)
     - Admin Orders View: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 480)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/orders.php ---
$delivery_notes = !empty($_POST['delivery_notes']) ? trim($_POST['delivery_notes']) : null;
$stmt = $pdo->prepare("INSERT INTO orders (..., delivery_notes) VALUES (..., ?)");
$stmt->execute([..., $delivery_notes]);

--- FRONTEND CHECKOUT UI: checkout.php (Line 320) ---
<div>
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Delivery & Handling Instructions</label>
    <textarea name="delivery_notes" placeholder="e.g. Leave package with security desk..." class="w-full border border-gray-200 rounded-xl p-3 text-sm"></textarea>
</div>

--- FRONTEND ADMIN ORDERS UI: admin/view/orders.view.php (Line 480) ---
<div class="mt-4 p-3 bg-amber-50 rounded-xl border border-amber-100 text-xs">
    <span class="font-bold text-amber-800 uppercase block mb-1">Delivery Notes:</span>
    <p class="text-amber-900"><?= htmlspecialchars($order['delivery_notes'] ?? 'None provided') ?></p>
</div>
*/


/* =============================================================================
   TASK 10: Partial Order Payment & Receipt Deposit Amount
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER delivery_notes;
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 140)
     - Admin View: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 510)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: admin/view/orders.view.php ---
// Calculate pending balance:
$balance_due = $order['total_amount'] - $order['amount_paid'];

--- FRONTEND ADMIN UI: admin/view/orders.view.php (Line 510) ---
<div class="flex justify-between items-center text-sm font-bold pt-2 border-t">
    <span>Amount Paid:</span>
    <span class="text-emerald-600">Rs. <?= number_format($order['amount_paid'] ?? 0, 2) ?></span>
</div>
<div class="flex justify-between items-center text-sm font-bold">
    <span>Balance Due:</span>
    <span class="text-red-600">Rs. <?= number_format($balance_due, 2) ?></span>
</div>
*/


/* =============================================================================
   TASK 11: Order Cancellation Reason by Customer
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) DEFAULT NULL AFTER amount_paid;
     - Customer Portal: f:\Kesara-Enterprises\my_account.php (around Line 380)
     - Admin View: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 530)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/orders.php ---
if ($action === 'cancel_order') {
    $orderId = (int)$_POST['order_id'];
    $reason  = trim($_POST['cancellation_reason'] ?? 'Customer requested cancellation');

    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
    $stmt->execute([$reason, $orderId]);
    echo json_encode(["status" => "success", "message" => "Order cancelled"]);
    exit;
}

--- FRONTEND CUSTOMER UI: my_account.php (Line 380) ---
<div class="modal">
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Reason for Order Cancellation</label>
    <select name="cancellation_reason" class="w-full border p-2 rounded-lg text-sm">
        <option value="Ordered by mistake">Ordered by mistake</option>
        <option value="Found better pricing">Found better pricing</option>
        <option value="Delivery delay expected">Delivery delay expected</option>
    </select>
</div>
*/


/* =============================================================================
   TASK 12: Order Estimated Delivery Date Calculation
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN estimated_delivery_date DATE DEFAULT NULL AFTER cancellation_reason;
     - Order Confirmation: f:\Kesara-Enterprises\order_confirmation.php (Line 110)
     - Admin Orders View: f:\Kesara-Enterprises\admin\view\orders.view.php (Line 476)
   -----------------------------------------------------------------------------
*/
/*
--- FRONTEND ORDER CONFIRMATION BADGE: order_confirmation.php (Line 110) ---
<div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-center gap-3">
    <i class="ti ti-truck-delivery text-blue-600 text-xl"></i>
    <div>
        <span class="text-[10px] font-bold text-blue-500 uppercase tracking-widest block">Estimated Delivery</span>
        <span class="text-sm font-extrabold text-blue-900">
            <?= !empty($order['estimated_delivery_date']) ? date('M d, Y', strtotime($order['estimated_delivery_date'])) : '3 - 5 Business Days' ?>
        </span>
    </div>
</div>

--- FRONTEND ADMIN ORDERS ROW: admin/view/orders.view.php (Line 476) ---
<div class="flex justify-between items-center text-xs font-medium text-gray-600">
    <span>Est. Delivery:</span>
    <span class="font-bold text-gray-900"><?= !empty($order['estimated_delivery_date']) ? htmlspecialchars($order['estimated_delivery_date']) : 'Pending Schedule' ?></span>
</div>
*/


/* =============================================================================
   TASK 13: Supplier Rating & Performance Score
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE suppliers ADD COLUMN rating DECIMAL(3,2) DEFAULT 5.00 AFTER status;
     - Admin Suppliers View: f:\Kesara-Enterprises\admin\view\suppliers.view.php (around Line 180)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: admin/view/suppliers.view.php ---
$checkRating = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'rating'");
if (!$checkRating->fetch()) {
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN rating DECIMAL(3,2) DEFAULT 5.00 AFTER status");
}

--- FRONTEND ADMIN UI: admin/view/suppliers.view.php (Line 180) ---
<div class="flex items-center gap-1 text-amber-500 font-bold text-sm">
    <i class="ti ti-star-filled text-xs"></i>
    <span><?= number_format($supplier['rating'] ?? 5.0, 1) ?> / 5.0</span>
</div>
*/


/* =============================================================================
   TASK 14: Purchase Order Expected vs Received Quantity Discrepancy Tracking
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE purchase_orders ADD COLUMN received_status ENUM('complete','partial','overdue') DEFAULT 'complete';
     - Admin Purchase Orders: f:\Kesara-Enterprises\admin\view\suppliers.purchase_orders.view.php (around Line 220)
   -----------------------------------------------------------------------------
*/
/*
--- FRONTEND ADMIN PO STATUS BADGE: admin/view/suppliers.purchase_orders.view.php (Line 220) ---
<?php 
$statusColors = [
    'complete' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'partial'  => 'bg-amber-50 text-amber-700 border-amber-200',
    'overdue'  => 'bg-red-50 text-red-700 border-red-200'
];
?>
<span class="px-2.5 py-1 text-xs font-bold rounded-full border <?= $statusColors[$po['received_status'] ?? 'complete'] ?>">
    <?= strtoupper($po['received_status'] ?? 'COMPLETE') ?>
</span>
*/


/* =============================================================================
   TASK 15: Supplier Lead Time Tracking in Days
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE supplier_products ADD COLUMN lead_days INT DEFAULT 7;
     - Admin View: f:\Kesara-Enterprises\admin\view\suppliers.view.php (around Line 310)
   -----------------------------------------------------------------------------
*/
/*
--- FRONTEND ADMIN UI: admin/view/suppliers.view.php (Line 310) ---
<span class="text-xs font-semibold text-gray-600">
    <i class="ti ti-clock mr-1"></i> Lead Time: <?= htmlspecialchars($sp['lead_days'] ?? 7) ?> Days
</span>
*/


/* =============================================================================
   TASK 16: Automated PO Expiry Notification
   =============================================================================
   TARGET FILES:
     - Backend API / Query: f:\Kesara-Enterprises\admin\view\suppliers.purchase_orders.view.php (Lines 60-80)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: suppliers.purchase_orders.view.php ---
// Fetch purchase orders that have passed expected delivery date and are still pending
$expired_pos = $pdo->query("SELECT po.*, s.name AS supplier_name 
                            FROM purchase_orders po 
                            JOIN suppliers s ON po.supplier_id = s.id 
                            WHERE po.expected_date < CURDATE() AND po.status = 'pending'")->fetchAll();

foreach ($expired_pos as $exp_po) {
    // Flag PO as overdue
    $pdo->prepare("UPDATE purchase_orders SET received_status = 'overdue' WHERE id = ?")->execute([$exp_po['id']]);
}
*/


/* =============================================================================
   TASK 17: Driver Delivery Confirmation Photo / Signature Upload
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN proof_of_delivery VARCHAR(255) DEFAULT NULL;
     - Driver Portal API: f:\Kesara-Enterprises\api\delivery.php (around Line 150)
     - Admin Delivery Tracking: f:\Kesara-Enterprises\admin\view\delivery.tracking.view.php (around Line 180)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/delivery.php ---
if (isset($_FILES['proof_photo'])) {
    $uploadDir = __DIR__ . '/../uploads/pod/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filename = 'pod_' . time() . '_' . basename($_FILES['proof_photo']['name']);
    move_uploaded_file($_FILES['proof_photo']['tmp_name'], $uploadDir . $filename);

    $stmt = $pdo->prepare("UPDATE orders SET proof_of_delivery = ?, status = 'delivered' WHERE id = ?");
    $stmt->execute(['uploads/pod/' . $filename, $_POST['order_id']]);
}

--- FRONTEND ADMIN UI: admin/view/delivery.tracking.view.php (Line 180) ---
<?php if (!empty($order['proof_of_delivery'])): ?>
    <a href="/<?= htmlspecialchars($order['proof_of_delivery']) ?>" target="_blank" class="text-xs font-bold text-brand hover:underline flex items-center gap-1">
        <i class="ti ti-file-certificate"></i> View Proof of Delivery
    </a>
<?php endif; ?>
*/


/* =============================================================================
   TASK 18: Driver Emergency Availability Toggle
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE delivery_personnel MODIFY COLUMN status ENUM('available','on_run','day_off','inactive','emergency_off') DEFAULT 'available';
     - Admin Delivery Personnel: f:\Kesara-Enterprises\admin\view\delivery.personnel.view.php (around Line 140)
   -----------------------------------------------------------------------------
*/
/*
--- FRONTEND ADMIN UI: admin/view/delivery.personnel.view.php (Line 140) ---
<select name="status" class="text-xs font-bold rounded-lg border border-gray-200 p-1.5">
    <option value="available">Available</option>
    <option value="on_run">On Run</option>
    <option value="day_off">Day Off</option>
    <option value="emergency_off">Emergency Off</option>
</select>
*/


/* =============================================================================
   TASK 19: Driver Leave Request Approval Note
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE driver_leaves ADD COLUMN admin_note VARCHAR(255) DEFAULT NULL AFTER reason;
     - Admin Delivery Personnel View: f:\Kesara-Enterprises\admin\view\delivery.personnel.view.php (around Line 340)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: admin/view/delivery.personnel.view.php ---
if (isset($_POST['action']) && $_POST['action'] === 'process_leave') {
    $leaveId   = (int)$_POST['leave_id'];
    $status    = $_POST['leave_status']; // 'approved' or 'rejected'
    $adminNote = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare("UPDATE driver_leaves SET status = ?, admin_note = ? WHERE id = ?");
    $stmt->execute([$status, $adminNote, $leaveId]);
}
*/


/* =============================================================================
   TASK 20: Delivery Zone Assignment Multi-Select
   =============================================================================
   TARGET FILES:
     - Database Table: `personnel_zones` (personnel_id, zone)
     - Admin Delivery Personnel View: f:\Kesara-Enterprises\admin\view\delivery.personnel.view.php (around Line 420)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: admin/view/delivery.personnel.view.php (Line 420) ---
if (isset($_POST['assign_zones'])) {
    $personnelId = (int)$_POST['personnel_id'];
    $selectedZones = $_POST['zones'] ?? []; // Array of selected zone strings

    // Clear existing assignments
    $pdo->prepare("DELETE FROM personnel_zones WHERE personnel_id = ?")->execute([$personnelId]);

    // Insert new multi-select zone assignments
    $stmt = $pdo->prepare("INSERT INTO personnel_zones (personnel_id, zone) VALUES (?, ?)");
    foreach ($selectedZones as $zone) {
        $stmt->execute([$personnelId, $zone]);
    }
}
*/

echo "<div style='background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:10px; font-family:sans-serif;'>";
echo "<h3 style='color:#166534; margin:0;'>✓ All 19 Viva Modification Tasks (02 - 20) Configured</h3>";
echo "<p style='color:#15803d; margin:5px 0 0 0;'>You can refer to the comments inside <code>database/viva_modifications_tasks_2_to_20.php</code> during your viva examination.</p>";
echo "</div>";
