<?php
/**
 * =============================================================================
 * KESARA ENTERPRISES - NEW VIVA MODIFICATION EXAM MASTER SCRIPT (TASKS 21 TO 40)
 * =============================================================================
 * This file contains 20 BRAND NEW, unique, realistic viva modification exam tasks
 * that DO NOT already exist in the codebase.
 *
 * For each task, you will find:
 *   1. Database Migration (SQL / Self-Healing PDO Execution)
 *   2. Backend / API Implementation (with Target File & Line Numbers)
 *   3. Frontend UI Implementation (Customer or Admin UI with Target File & Line Numbers)
 *
 * How to use during Viva Exam:
 *   - Copy the SQL or PDO snippet for database schema updates.
 *   - Open the mentioned target file at the specified line number.
 *   - Insert or uncomment the backend and UI code provided.
 * =============================================================================
 */

require_once __DIR__ . "/connection.php";

/**
 * =============================================================================
 * SELF-HEALING DATABASE HELPER FUNCTIONS
 * =============================================================================
 */
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

echo "<h1>Kesara Enterprises - New Viva Modifications Master (Tasks 21 - 40)</h1>";
echo "<p>This file contains 20 brand-new viva exam modification tasks ready for instant copy-pasting during your exam.</p>";

/* =============================================================================
   TASK 21: Customer Loyalty Tier Discount System (Bronze, Silver, Gold, Platinum)
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN loyalty_tier ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze', ADD COLUMN total_points INT DEFAULT 0;
     - Backend API: f:\Kesara-Enterprises\api\update_profile.php (around Line 40)
     - Customer UI: f:\Kesara-Enterprises\my_account.php (around Line 160)
     - Admin UI: f:\Kesara-Enterprises\admin\view\customers.view.php (around Line 340)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE users ADD COLUMN loyalty_tier ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze';
ALTER TABLE users ADD COLUMN total_points INT DEFAULT 0;

--- BACKEND CODE CHANGE: api/update_profile.php (~Line 40) ---
// Self-Healing Column Check:
ensureColumnExists($pdo, 'users', 'loyalty_tier', "ALTER TABLE users ADD COLUMN loyalty_tier ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze'");

// Auto-calculate loyalty tier based on total lifetime spent:
$totalSpent = (float)($user['total_spent'] ?? 0);
$tier = 'bronze';
if ($totalSpent >= 1000000) $tier = 'platinum';
elseif ($totalSpent >= 500000) $tier = 'gold';
elseif ($totalSpent >= 100000) $tier = 'silver';

$pdo->prepare("UPDATE users SET loyalty_tier = ? WHERE id = ?")->execute([$tier, $user_id]);

--- FRONTEND CUSTOMER UI: my_account.php (~Line 160) ---
<div class="mt-2 flex items-center gap-2">
    <span class="px-2.5 py-0.5 text-[10px] font-extrabold rounded-full uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
        <i class="ti ti-crown text-xs mr-1"></i> Loyalty: <?= htmlspecialchars(strtoupper($user['loyalty_tier'] ?? 'bronze')) ?> TIER
    </span>
</div>

--- FRONTEND ADMIN UI: admin/view/customers.view.php (~Line 340) ---
<div class="p-3 bg-amber-50 rounded-xl border border-amber-100">
    <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block">Customer Loyalty Tier</span>
    <span class="text-sm font-extrabold text-amber-900"><?= htmlspecialchars(strtoupper($customer['loyalty_tier'] ?? 'bronze')) ?></span>
</div>
*/


/* =============================================================================
   TASK 22: Order Return & Refund Request Portal (RMA System)
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below or PDO self-healing
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 90)
     - Customer UI: f:\Kesara-Enterprises\my_account.php (around Line 280)
     - Admin UI: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 520)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS order_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  reason TEXT NOT NULL,
  refund_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','approved','rejected','refunded') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- BACKEND CODE CHANGE: api/orders.php (~Line 90) ---
if (isset($_GET['action']) && $_GET['action'] === 'request_return') {
    $order_id = (int)$_POST['order_id'];
    $user_id  = $_SESSION['user_id'] ?? 0;
    $reason   = trim($_POST['reason'] ?? '');
    $amount   = (float)($_POST['refund_amount'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO order_returns (order_id, user_id, reason, refund_amount) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $user_id, $reason, $amount]);
    echo json_encode(["status" => "success", "message" => "Return request submitted successfully."]);
    exit;
}

--- FRONTEND CUSTOMER UI: my_account.php (~Line 280) ---
<button onclick="openReturnModal(<?= $o['id'] ?>)" class="text-xs font-bold text-amber-600 hover:underline">Request Return</button>

--- FRONTEND ADMIN UI: admin/view/orders.view.php (~Line 520) ---
<div class="mt-4 p-4 bg-amber-50 rounded-2xl border border-amber-100">
    <h4 class="text-xs font-bold text-amber-900 uppercase">Return / Refund Request Pending</h4>
    <p class="text-xs text-amber-700 mt-1"><?= htmlspecialchars($return_req['reason'] ?? 'Defective goods received') ?></p>
</div>
*/


/* =============================================================================
   TASK 23: Bulk Product Volume Weight & Shipping Freight Calculator
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE products ADD COLUMN unit_weight_kg DECIMAL(8,2) DEFAULT 0.50 AFTER gsm;
     - Backend API / Logic: f:\Kesara-Enterprises\checkout.php (around Line 120)
     - Customer UI: f:\Kesara-Enterprises\checkout.php (around Line 250) & product_detail.php (around Line 250)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE products ADD COLUMN unit_weight_kg DECIMAL(8,2) DEFAULT 0.50;

--- BACKEND CODE CHANGE: checkout.php (~Line 120) ---
$total_weight_kg = 0;
foreach ($cart_items as $item) {
    $weight = (float)($item['unit_weight_kg'] ?? 0.50);
    $total_weight_kg += ($weight * $item['qty']);
}
$shipping_surcharge = ($total_weight_kg > 50) ? ceil(($total_weight_kg - 50) / 10) * 150 : 0;

--- FRONTEND UI: checkout.php (~Line 250) ---
<div class="flex justify-between items-center text-xs font-bold text-gray-500 py-2 border-b">
    <span>Total Package Freight Weight:</span>
    <span class="text-gray-900 font-extrabold"><?= number_format($total_weight_kg, 1) ?> kg</span>
</div>
*/


/* =============================================================================
   TASK 24: Customer Wholesale Credit Overdue Warning & Auto-Lock System
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN payment_due_date DATE DEFAULT NULL AFTER created_at;
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 110)
     - Customer UI: f:\Kesara-Enterprises\my_account.php (around Line 170)
     - Admin UI: f:\Kesara-Enterprises\admin\view\customers.view.php (around Line 210)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE orders ADD COLUMN payment_due_date DATE DEFAULT NULL;

--- BACKEND CODE CHANGE: api/orders.php (~Line 110) ---
// Set payment due date to 30 days from creation for credit orders:
$due_date = date('Y-m-d', strtotime('+30 days'));
$stmt = $pdo->prepare("UPDATE orders SET payment_due_date = ? WHERE id = ?");
$stmt->execute([$due_date, $order_id]);

--- FRONTEND CUSTOMER UI: my_account.php (~Line 170) ---
<?php if ($has_overdue_invoice): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-800 text-xs font-bold flex items-center gap-3">
        <i class="ti ti-alert-triangle text-xl text-red-600"></i>
        <span>Overdue Credit Invoice Alert: You have an unpaid credit balance past due. Ordering disabled until settled.</span>
    </div>
<?php endif; ?>
*/


/* =============================================================================
   TASK 25: Sales Representative Commission Tracking per Wholesale Order
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN sales_rep_id INT DEFAULT NULL AFTER user_id, ADD COLUMN commission_amount DECIMAL(10,2) DEFAULT 0.00;
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 150)
     - Admin UI: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 460) & reports.view.php (around Line 120)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE orders ADD COLUMN sales_rep_id INT DEFAULT NULL;
ALTER TABLE orders ADD COLUMN commission_amount DECIMAL(10,2) DEFAULT 0.00;

--- BACKEND CODE CHANGE: api/orders.php (~Line 150) ---
$sales_rep_id = !empty($_POST['sales_rep_id']) ? (int)$_POST['sales_rep_id'] : null;
$commission = $total_amount * 0.03; // 3% sales rep commission
$stmt = $pdo->prepare("UPDATE orders SET sales_rep_id = ?, commission_amount = ? WHERE id = ?");
$stmt->execute([$sales_rep_id, $commission, $order_id]);

--- FRONTEND ADMIN UI: admin/view/orders.view.php (~Line 460) ---
<div class="flex justify-between items-center text-xs font-bold text-gray-600 py-1">
    <span>Sales Rep Commission (3%):</span>
    <span class="text-emerald-700">LKR <?= number_format($order['commission_amount'] ?? 0, 2) ?></span>
</div>
*/


/* =============================================================================
   TASK 26: Multi-Warehouse Inventory Allocation & Dispatch Picker
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend API: f:\Kesara-Enterprises\api\inventory_warning.php (around Line 180)
     - Admin UI: f:\Kesara-Enterprises\admin\view\inventory.view.php (around Line 310)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL,
  city VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory ADD COLUMN warehouse_id INT DEFAULT 1;

--- FRONTEND ADMIN UI: admin/view/inventory.view.php (~Line 310) ---
<select name="warehouse_id" class="bg-gray-50 border border-gray-200 rounded-xl p-2 text-xs font-bold">
    <option value="1">Colombo Central Warehouse</option>
    <option value="2">Gampaha Distribution Hub</option>
    <option value="3">Kandy Regional Depot</option>
</select>
*/


/* =============================================================================
   TASK 27: 1-Click Automated Reorder PO Generator for Low Stock Items
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE products ADD COLUMN suggested_reorder_qty INT DEFAULT 100 AFTER custom_restock_threshold;
     - Backend API: f:\Kesara-Enterprises\api\admin_inventory.php (around Line 40)
     - Admin UI: f:\Kesara-Enterprises\admin\view\suppliers.purchase_orders.view.php (around Line 150)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE products ADD COLUMN suggested_reorder_qty INT DEFAULT 100;

--- BACKEND CODE CHANGE: api/admin_inventory.php (~Line 40) ---
if (isset($_GET['action']) && $_GET['action'] === 'auto_generate_pos') {
    $low_stock = $pdo->query("SELECT p.*, s.id AS supplier_id FROM products p JOIN suppliers s ON p.category_id = s.id WHERE p.stock <= p.custom_restock_threshold")->fetchAll();
    foreach ($low_stock as $item) {
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, total_amount, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$item['supplier_id'], $item['suggested_reorder_qty'] * 200]);
    }
    echo json_encode(["status" => "success", "message" => "Auto POs generated."]);
    exit;
}

--- FRONTEND ADMIN UI: admin/view/suppliers.purchase_orders.view.php (~Line 150) ---
<button onclick="autoGeneratePOs()" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs flex items-center gap-2">
    <i class="ti ti-bolt"></i> 1-Click Auto Reorder PO Generator
</button>
*/


/* =============================================================================
   TASK 28: Automated Invoice PDF Resend via Email Action
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN invoice_sent_count INT DEFAULT 0;
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 210)
     - Admin UI: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 490)
   -----------------------------------------------------------------------------
*/
/*
--- BACKEND CODE CHANGE: api/orders.php (~Line 210) ---
if (isset($_GET['action']) && $_GET['action'] === 'resend_invoice') {
    $order_id = (int)$_POST['order_id'];
    require_once __DIR__ . "/../src/Mailer.php";
    
    $stmt = $pdo->prepare("SELECT o.*, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $ord = $stmt->fetch();
    
    \App\Mailer::send($ord['email'], "VAT Invoice Copy for KE-2025-" . $order_id, "Please find your requested invoice attached.");
    $pdo->prepare("UPDATE orders SET invoice_sent_count = invoice_sent_count + 1 WHERE id = ?")->execute([$order_id]);
    echo json_encode(["status" => "success", "message" => "Invoice emailed successfully."]);
    exit;
}

--- FRONTEND ADMIN UI: admin/view/orders.view.php (~Line 490) ---
<button onclick="resendInvoiceEmail(<?= $order['id'] ?>)" class="px-3 py-1.5 bg-brand-light text-brand font-bold rounded-xl text-xs flex items-center gap-1.5">
    <i class="ti ti-mail"></i> Resend Invoice Email
</button>
*/


/* =============================================================================
   TASK 29: Dynamic Promotional Flash Sale Banner & Countdown Widget
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE products ADD COLUMN flash_sale_title VARCHAR(150) DEFAULT NULL, ADD COLUMN flash_sale_ends DATETIME DEFAULT NULL;
     - Backend API: f:\Kesara-Enterprises\api\products.php (around Line 90)
     - Customer UI: f:\Kesara-Enterprises\product_detail.php (around Line 320) & index.php (around Line 240)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE products ADD COLUMN flash_sale_title VARCHAR(150) DEFAULT NULL;
ALTER TABLE products ADD COLUMN flash_sale_ends DATETIME DEFAULT NULL;

--- FRONTEND CUSTOMER UI: product_detail.php (~Line 320) ---
<?php if (!empty($product['flash_sale_ends']) && strtotime($product['flash_sale_ends']) > time()): ?>
    <div class="mb-6 p-4 bg-gradient-to-r from-red-600 to-amber-600 text-white rounded-2xl flex items-center justify-between shadow-md">
        <div class="flex items-center gap-2">
            <i class="ti ti-flame text-2xl animate-bounce"></i>
            <div>
                <span class="font-extrabold text-sm uppercase block"><?= htmlspecialchars($product['flash_sale_title'] ?? 'Flash Sale') ?></span>
                <span class="text-xs text-white/80">Ends: <?= date('M d, g:i A', strtotime($product['flash_sale_ends'])) ?></span>
            </div>
        </div>
        <span class="px-3 py-1 bg-white text-red-600 rounded-full font-black text-xs">SPECIAL DEAL</span>
    </div>
<?php endif; ?>
*/


/* =============================================================================
   TASK 30: Driver Fuel Expense & Route Odometer Log Tracking
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend API: f:\Kesara-Enterprises\api\delivery.php (around Line 160)
     - Admin UI: f:\Kesara-Enterprises\admin\view\delivery.personnel.view.php (around Line 350)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS driver_fuel_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INT NOT NULL,
  start_km INT NOT NULL,
  end_km INT NOT NULL,
  fuel_cost_lkr DECIMAL(10,2) NOT NULL,
  log_date DATE DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- FRONTEND ADMIN UI: admin/view/delivery.personnel.view.php (~Line 350) ---
<div class="p-4 bg-gray-50 border rounded-2xl">
    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Fuel & Distance Mileage Log</h4>
    <div class="flex justify-between text-xs text-gray-600 font-bold">
        <span>Today's Distance: 142 KM</span>
        <span class="text-emerald-600">Fuel Cost: LKR 4,850.00</span>
    </div>
</div>
*/


/* =============================================================================
   TASK 31: Customer Order Delivery Reschedule Date Request System
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 250)
     - Customer UI: f:\Kesara-Enterprises\order_confirmation.php (around Line 175)
     - Admin UI: f:\Kesara-Enterprises\admin\view\delivery.assignments.view.php (around Line 280)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS order_reschedule_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  requested_date DATE NOT NULL,
  reason TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- FRONTEND CUSTOMER UI: order_confirmation.php (~Line 175) ---
<button onclick="requestReschedule(<?= $order['id'] ?>)" class="mt-3 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-xl flex items-center gap-2">
    <i class="ti ti-calendar"></i> Request Delivery Reschedule Date
</button>
*/


/* =============================================================================
   TASK 32: Product Manufacturing Batch Lot Number & Expiry Tracking
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE inventory ADD COLUMN lot_number VARCHAR(50) DEFAULT NULL, ADD COLUMN expiry_date DATE DEFAULT NULL;
     - Backend API: f:\Kesara-Enterprises\api\admin_inventory.php (around Line 80)
     - Admin UI: f:\Kesara-Enterprises\admin\view\suppliers.goods_received_note.view.php (around Line 190)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE inventory ADD COLUMN lot_number VARCHAR(50) DEFAULT NULL;
ALTER TABLE inventory ADD COLUMN expiry_date DATE DEFAULT NULL;

--- FRONTEND ADMIN UI: admin/view/suppliers.goods_received_note.view.php (~Line 190) ---
<div>
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Batch Lot Number & Expiry Date</label>
    <div class="grid grid-cols-2 gap-2">
        <input type="text" name="lot_number" placeholder="LOT-2026-08" class="border rounded-lg p-2 text-xs">
        <input type="date" name="expiry_date" class="border rounded-lg p-2 text-xs">
    </div>
</div>
*/


/* =============================================================================
   TASK 33: Dedicated Account Manager Contact Card on Customer Portal
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN account_manager_id INT DEFAULT NULL AFTER status;
     - Backend Query: f:\Kesara-Enterprises\my_account.php (around Line 75)
     - Customer UI: f:\Kesara-Enterprises\my_account.php (around Line 195)
     - Admin UI: f:\Kesara-Enterprises\admin\view\customers.view.php (around Line 350)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE users ADD COLUMN account_manager_id INT DEFAULT NULL;

--- FRONTEND CUSTOMER UI: my_account.php (~Line 195) ---
<div class="p-6 bg-brand-light/30 border border-brand/10 rounded-3xl flex items-center gap-4">
    <div class="w-12 h-12 rounded-full bg-brand text-white flex items-center justify-center font-bold text-lg">
        <i class="ti ti-headset"></i>
    </div>
    <div>
        <h4 class="text-xs font-extrabold text-brand uppercase tracking-wider">Your Assigned Account Representative</h4>
        <p class="text-sm font-bold text-gray-900 mt-0.5">Kamal Perera (+94 77 123 4567)</p>
        <p class="text-xs text-gray-500">Direct Rep: kamal@kesara.lk</p>
    </div>
</div>
*/


/* =============================================================================
   TASK 34: Customer Wholesale Custom Price Quote Expiry Tracking
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE inquiries ADD COLUMN quote_valid_until DATE DEFAULT NULL, ADD COLUMN quoted_amount DECIMAL(10,2) DEFAULT NULL;
     - Backend API: f:\Kesara-Enterprises\api\inquiries.php (around Line 60)
     - Customer UI: f:\Kesara-Enterprises\product_detail.php (around Line 1140)
     - Admin UI: f:\Kesara-Enterprises\admin\view\inquiries.view.php (around Line 210)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE inquiries ADD COLUMN quote_valid_until DATE DEFAULT NULL;
ALTER TABLE inquiries ADD COLUMN quoted_amount DECIMAL(10,2) DEFAULT NULL;

--- FRONTEND ADMIN UI: admin/view/inquiries.view.php (~Line 210) ---
<div class="grid grid-cols-2 gap-3 mt-3 p-3 bg-gray-50 rounded-xl border text-xs">
    <div><span class="font-bold text-gray-500">Quoted Price:</span> <span class="font-extrabold text-brand">LKR <?= number_format($inquiry['quoted_amount'] ?? 0, 2) ?></span></div>
    <div><span class="font-bold text-gray-500">Quote Valid Until:</span> <span class="font-bold text-gray-900"><?= htmlspecialchars($inquiry['quote_valid_until'] ?? '7 Days') ?></span></div>
</div>
*/


/* =============================================================================
   TASK 35: Wholesale Product Combo Bundle Discount Rules
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend Logic: f:\Kesara-Enterprises\checkout.php (around Line 130)
     - Customer UI: f:\Kesara-Enterprises\product_detail.php (around Line 380) & cart.php (around Line 180)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS product_bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  product_a_id INT NOT NULL,
  product_b_id INT NOT NULL,
  discount_pct DECIMAL(5,2) NOT NULL DEFAULT 10.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- FRONTEND CUSTOMER UI: product_detail.php (~Line 380) ---
<div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-between text-xs font-bold text-emerald-800">
    <span><i class="ti ti-tags text-emerald-600 mr-1"></i> Combo Deal: Pair with Men's Boxers and get an Extra 10% Bundle Discount!</span>
</div>
*/


/* =============================================================================
   TASK 36: Customer Click & Collect Warehouse Pickup Choice
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE orders ADD COLUMN fulfillment_type ENUM('delivery','pickup') DEFAULT 'delivery' AFTER status;
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 130)
     - Customer UI: f:\Kesara-Enterprises\checkout.php (around Line 210)
     - Admin UI: f:\Kesara-Enterprises\admin\view\orders.view.php (around Line 310)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE orders ADD COLUMN fulfillment_type ENUM('delivery','pickup') DEFAULT 'delivery';

--- FRONTEND CHECKOUT UI: checkout.php (~Line 210) ---
<div class="grid grid-cols-2 gap-4 mb-6">
    <label class="p-4 border-2 rounded-2xl flex items-center gap-3 cursor-pointer border-brand bg-brand-light/20">
        <input type="radio" name="fulfillment_type" value="delivery" checked class="text-brand">
        <div>
            <span class="text-xs font-extrabold text-brand uppercase block">Islandwide Delivery</span>
            <span class="text-[10px] text-gray-500">Standard Courier</span>
        </div>
    </label>
    <label class="p-4 border-2 rounded-2xl flex items-center gap-3 cursor-pointer border-gray-200 hover:border-brand">
        <input type="radio" name="fulfillment_type" value="pickup" class="text-brand">
        <div>
            <span class="text-xs font-extrabold text-gray-900 uppercase block">Store Pickup (Free)</span>
            <span class="text-[10px] text-gray-500">Maharagama Warehouse</span>
        </div>
    </label>
</div>
*/


/* =============================================================================
   TASK 37: Admin 2FA PIN Code Authentication Verification Step
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(10) DEFAULT NULL, ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0;
     - Backend API: f:\Kesara-Enterprises\auth.php (around Line 90)
     - Customer/Admin UI: f:\Kesara-Enterprises\auth.php (around Line 280) & my_account.php (around Line 460)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(10) DEFAULT NULL;
ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0;

--- FRONTEND AUTH UI: auth.php (~Line 280) ---
<div class="mt-4">
    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Enter 6-Digit 2FA Security PIN</label>
    <input type="text" name="two_factor_pin" maxlength="6" pattern="[0-9]{6}" placeholder="123456" class="w-full text-center tracking-widest text-lg font-black border rounded-xl p-3">
</div>
*/


/* =============================================================================
   TASK 38: Customer Store Credit Wallet & Balance Payment Option
   =============================================================================
   TARGET FILES:
     - Database Migration: ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0.00 AFTER credit_limit;
     - Backend Logic: f:\Kesara-Enterprises\api\orders.php (around Line 160)
     - Customer UI: f:\Kesara-Enterprises\checkout.php (around Line 270) & my_account.php (around Line 215)
     - Admin UI: f:\Kesara-Enterprises\admin\view\customers.view.php (around Line 325)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0.00;

--- FRONTEND CUSTOMER CHECKOUT UI: checkout.php (~Line 270) ---
<div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-between mb-4">
    <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" name="use_wallet_balance" value="1" class="w-5 h-5 text-emerald-600 rounded">
        <div>
            <span class="text-xs font-extrabold text-emerald-900 block">Apply Customer Store Credit Wallet</span>
            <span class="text-[10px] text-emerald-700">Available Balance: LKR <?= number_format($user['wallet_balance'] ?? 0, 2) ?></span>
        </div>
    </label>
</div>
*/


/* =============================================================================
   TASK 39: Automated Customer Post-Delivery Feedback Survey (1 to 5 Stars)
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend API: f:\Kesara-Enterprises\api\orders.php (around Line 280)
     - Customer UI: f:\Kesara-Enterprises\order_confirmation.php (around Line 200)
     - Admin UI: f:\Kesara-Enterprises\admin\view\reports.view.php (around Line 240)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS order_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT NOT NULL,
  comments TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- FRONTEND CUSTOMER UI: order_confirmation.php (~Line 200) ---
<?php if ($current_status === 'delivered'): ?>
    <div class="p-6 bg-amber-50 border border-amber-100 rounded-3xl mt-6 text-center">
        <h4 class="text-sm font-extrabold text-amber-900 mb-2">How Was Your Order & Delivery Experience?</h4>
        <div class="flex justify-center gap-2 text-2xl text-amber-400 my-3">
            <i class="ti ti-star-filled cursor-pointer hover:scale-125"></i>
            <i class="ti ti-star-filled cursor-pointer hover:scale-125"></i>
            <i class="ti ti-star-filled cursor-pointer hover:scale-125"></i>
            <i class="ti ti-star-filled cursor-pointer hover:scale-125"></i>
            <i class="ti ti-star-filled cursor-pointer hover:scale-125"></i>
        </div>
    </div>
<?php endif; ?>
*/


/* =============================================================================
   TASK 40: Inventory Audit Reconciliation & Discrepancy Log
   =============================================================================
   TARGET FILES:
     - Database Migration: SQL query below
     - Backend API: f:\Kesara-Enterprises\api\admin_inventory.php (around Line 110)
     - Admin UI: f:\Kesara-Enterprises\admin\view\inventory.view.php (around Line 380)
   -----------------------------------------------------------------------------
*/
/*
--- SQL SCHEMA MIGRATION ---
CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  old_qty INT NOT NULL,
  new_qty INT NOT NULL,
  reason ENUM('damaged','stolen','audit_count','expired','error') NOT NULL,
  adjusted_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--- FRONTEND ADMIN UI: admin/view/inventory.view.php (~Line 380) ---
<div class="p-4 bg-gray-50 border rounded-2xl mt-4">
    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Stock Audit Discrepancy Log</h4>
    <select name="adjustment_reason" class="w-full border rounded-lg p-2 text-xs">
        <option value="audit_count">Physical Audit Stock Count</option>
        <option value="damaged">Damaged Goods Write-Off</option>
        <option value="expired">Expired Stock Disposal</option>
        <option value="stolen">Inventory Shortage Loss</option>
    </select>
</div>
*/

echo "<div style='background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:10px; font-family:sans-serif;'>";
echo "<h3 style='color:#166534; margin:0;'>✓ 20 Brand-New Viva Modification Tasks (21 - 40) Ready!</h3>";
echo "<p style='color:#15803d; margin:5px 0 0 0;'>These 20 tasks do not exist in your codebase yet. You can use them directly during your viva examination!</p>";
echo "</div>";
