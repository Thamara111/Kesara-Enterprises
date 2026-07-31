<?php
/**
 * User Account Dashboard
 * Displays the logged-in user's profile, recent orders, delivery addresses, and account status.
 * Also handles user logout logic.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: /login");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: /login");
    exit;
}

require_once __DIR__ . "/database/connection.php";
$user = null;
$user_addresses = [];
$orders = [];
$total_orders = 0;
$total_spent = 0;
$units_ordered = 0;
$last_order_date = "--";
$last_order_year = "";

if (isset($pdo) && $pdo !== null) {
    try {
        // Self-healing database setup for delivery addresses
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) DEFAULT NULL,
            province VARCHAR(100) DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default address if user_addresses is empty for this user
        $checkCount = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
        $checkCount->execute([$user_id]);
        if ($checkCount->fetchColumn() == 0) {
            $uStmt = $pdo->prepare("SELECT business_name, address FROM users WHERE id = ?");
            $uStmt->execute([$user_id]);
            $uData = $uStmt->fetch();
            if ($uData && !empty($uData['address'])) {
                $seedTitle = !empty($uData['business_name']) ? $uData['business_name'] . ' Warehouse' : 'Primary Address';
                $pdo->prepare("INSERT INTO user_addresses (user_id, title, address, is_default) VALUES (?, ?, ?, 1)")
                    ->execute([$user_id, $seedTitle, $uData['address']]);
            }
        }

        // Fetch User details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Fetch User Delivery Addresses
            $stmt_addr = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
            $stmt_addr->execute([$user_id]);
            $user_addresses = $stmt_addr->fetchAll();

            // Fetch Order statistics & list
            $stmt_orders = $pdo->prepare("SELECT o.*, 
                                                 (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count,
                                                 (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS total_units
                                          FROM orders o 
                                          WHERE o.user_id = ? 
                                          ORDER BY o.created_at DESC");
            $stmt_orders->execute([$user_id]);
            $orders = $stmt_orders->fetchAll();
            
            $total_orders = count($orders);
            foreach ($orders as $o) {
                $total_spent += $o['total_amount'];
                $units_ordered += $o['total_units'];
            }
            
            if ($total_orders > 0) {
                $last_order_date = date('d M', strtotime($orders[0]['created_at']));
                $last_order_year = date('Y', strtotime($orders[0]['created_at']));
            }
        }
    } catch (\Exception $e) {
        // Fallback
    }
}

// If user data could not be loaded, redirect to login
if (!$user) {
    header("Location: /login");
    exit;
}

function formatSpent($amount) {
    if ($amount >= 1000000) {
        return 'LKR ' . number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return 'LKR ' . number_format($amount / 1000, 1) . 'K';
    }
    return 'LKR ' . number_format($amount);
}

$page_meta = [
    'title' => 'My Account | Kesara Enterprises',
    'description' => 'Manage your wholesale account, track orders, and update your business profile.',
];
require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 py-12 min-h-screen">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        
        <!-- BREADCRUMBS -->
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-400 mb-10 overflow-x-auto whitespace-nowrap">
            <a href="/" class="hover:text-brand transition-colors">Home</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900 font-bold tracking-tight uppercase">My Account</span>
            <div class="ml-auto hidden md:flex items-center gap-2 text-gray-400 font-medium tracking-tight">
                <span id="header-user-fullname"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <span class="text-gray-200">/</span>
                <span><?= htmlspecialchars($user['business_name']) ?></span>
            </div>
        </nav>

        <div class="grid lg:grid-cols-[280px_1fr] gap-10 items-start">
            
            <!-- LEFT SIDEBAR: NAVIGATION -->
            <aside class="space-y-6">
                <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm overflow-hidden">
                    <!-- User Brief -->
                    <div class="flex items-center gap-4 mb-8 border-b border-gray-50 pb-6">
                        <div id="brief-user-avatar" class="w-12 h-12 rounded-full bg-brand-light text-brand flex items-center justify-center font-bold shadow-sm border border-brand/10">
                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 id="brief-user-name" class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                            <p class="text-[11px] text-gray-400 font-medium truncate mb-1"><?= htmlspecialchars($user['business_name']) ?></p>
                            <?php
                                $status_lbl = strtolower($user['status']);
                                $status_badge_classes = [
                                    'approved' => 'bg-green-50 text-green-600 border-green-100',
                                    'pending' => 'bg-amber-50 text-amber-600 border-amber-100',
                                    'suspended' => 'bg-red-50 text-red-600 border-red-100',
                                    'rejected' => 'bg-gray-100 text-gray-600 border-gray-200'
                                ];
                                $badge_class = $status_badge_classes[$status_lbl] ?? 'bg-gray-100 text-gray-600';
                            ?>
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full border uppercase tracking-wider <?= $badge_class ?>"><?= htmlspecialchars($user['status']) ?></span>
                        </div>
                    </div>

                    <!-- Nav List -->
                    <nav class="space-y-1">
                        <button onclick="showSection('orders')" id="nav-orders" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold transition-all active-nav cursor-pointer">
                            <i class="ti ti-package text-xl"></i>
                            Order History
                        </button>
                        <button onclick="showSection('profile')" id="nav-profile" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all cursor-pointer">
                            <i class="ti ti-user text-xl"></i>
                            My Profile
                        </button>
                        <button onclick="showSection('addresses')" id="nav-addresses" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all cursor-pointer">
                            <i class="ti ti-map-pin text-xl"></i>
                            Addresses
                        </button>
                        <button onclick="showSection('invoices')" id="nav-invoices" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all cursor-pointer">
                            <i class="ti ti-file-invoice text-xl"></i>
                            Invoices
                        </button>
                        <button onclick="showSection('security')" id="nav-security" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all cursor-pointer">
                            <i class="ti ti-lock text-xl"></i>
                            Security
                        </button>
                        
                        <div class="h-px bg-gray-50 my-4"></div>
                        
                        <a href="?action=logout" class="flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-red-400 hover:bg-red-50 hover:text-red-600 transition-all">
                            <i class="ti ti-logout text-xl"></i>
                            Sign Out
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- RIGHT CONTENT AREA -->
            <div class="space-y-8">
                
                <!-- SECTION: ORDERS -->
                <section id="sec-orders" class="space-y-8">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Orders</p>
                            <h3 class="text-2xl font-extrabold text-gray-900"><?= htmlspecialchars($total_orders) ?></h3>
                            <p class="text-[10px] font-medium text-gray-300 mt-1">Since Jan 2024</p>
                        </div>
                        <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Spent</p>
                            <h3 class="text-2xl font-extrabold text-gray-900"><?= formatSpent($total_spent) ?></h3>
                            <p class="text-[10px] font-medium text-gray-300 mt-1">Lifetime</p>
                        </div>
                        <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Units Ordered</p>
                            <h3 class="text-2xl font-extrabold text-gray-900"><?= number_format($units_ordered) ?></h3>
                            <p class="text-[10px] font-medium text-gray-300 mt-1">All Time</p>
                        </div>
                        <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Last Order</p>
                            <h3 class="text-2xl font-extrabold text-gray-900"><?= htmlspecialchars($last_order_date) ?></h3>
                            <p class="text-[10px] font-medium text-gray-300 mt-1"><?= htmlspecialchars($last_order_year) ?></p>
                        </div>
                    </div>

                    <!-- Order List Table -->
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 border-b border-gray-50 pb-6">
                            <h2 class="text-lg font-bold text-gray-900 uppercase tracking-tight">Recent Orders</h2>
                            <div class="flex items-center gap-4">
                                <select id="order-status-filter" onchange="filterAccountOrders()" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-[11px] font-bold text-gray-500 uppercase tracking-widest outline-none focus:ring-1 focus:ring-brand cursor-pointer">
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <div class="relative">
                                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input type="text" id="order-search-input" oninput="filterAccountOrders()" placeholder="Search orders..." class="bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-2 text-xs outline-none focus:ring-1 focus:ring-brand w-full md:w-48">
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                        <th class="pb-4">Order ID</th>
                                        <th class="pb-4">Items</th>
                                        <th class="pb-4">Total Amount</th>
                                        <th class="pb-4">Status</th>
                                        <th class="pb-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50" id="account-orders-tbody">
                                    <?php foreach ($orders as $o): ?>
                                    <tr class="account-order-row group" data-status="<?= htmlspecialchars(strtolower($o['status'])) ?>" data-search="ke-2025-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?> <?= htmlspecialchars(mb_strtolower($o['items_count'].' products '.$o['total_units'].' units LKR '.$o['total_amount'])) ?>">
                                        <td class="py-5 font-bold text-gray-900 text-sm">KE-2025-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                        <td class="py-5 text-gray-500 text-xs"><?= htmlspecialchars($o['items_count']) ?> products · <?= htmlspecialchars($o['total_units']) ?> units</td>
                                        <td class="py-5 font-bold text-gray-900 text-sm">LKR <?= number_format($o['total_amount']) ?></td>
                                        <td class="py-5">
                                            <?php 
                                                $status = strtolower($o['status']);
                                                $status_classes = [
                                                    'pending' => 'bg-amber-50 text-amber-600 border border-amber-100',
                                                    'processing' => 'bg-blue-50 text-blue-600 border border-blue-100',
                                                    'shipped' => 'bg-blue-50 text-blue-600 border border-blue-100',
                                                    'delivered' => 'bg-green-50 text-green-600 border border-green-100',
                                                    'cancelled' => 'bg-red-50 text-red-600 border border-red-100'
                                                ];
                                                $class = $status_classes[$status] ?? 'bg-gray-50 text-gray-550';
                                            ?>
                                            <span class="px-3 py-1 text-[10px] font-bold rounded-full uppercase <?= $class ?>"><?= htmlspecialchars($o['status']) ?></span>
                                        </td>
                                        <td class="py-5 text-right">
                                            <a href="/order-success?id=<?= htmlspecialchars($o['id']) ?>" class="text-xs font-bold text-brand hover:underline">View Details</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <tr id="account-orders-empty" class="<?= empty($orders) ? '' : 'hidden' ?>">
                                        <td colspan="5" class="py-8 text-center text-sm text-gray-400">No orders found matching the filter criteria.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- SECTION: PROFILE -->
                <section id="sec-profile" class="hidden">
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900 mb-8 tracking-tight">My Profile</h2>
                        
                        <!-- Profile Feedback Alert -->
                        <div id="profile-alert" class="hidden mb-6 p-4 rounded-2xl text-xs font-bold flex items-center gap-3"></div>

                        <form id="form-update-profile" onsubmit="updateProfile(event)" class="space-y-8">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">First Name *</label>
                                    <input type="text" id="profile-first-name" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Last Name *</label>
                                    <input type="text" id="profile-last-name" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Email Address *</label>
                                    <input type="email" id="profile-email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phone Number *</label>
                                    <input type="tel" id="profile-phone" name="phone" required value="<?= htmlspecialchars($user['phone']) ?>" maxlength="10" pattern="^0[0-9]{9}$" title="Phone number must start with 0 and contain exactly 10 digits" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                            </div>

                            <hr class="border-gray-50">

                            <div class="bg-brand-light/30 border border-brand/10 rounded-2xl p-6">
                                <h3 class="text-xs font-bold text-brand uppercase tracking-widest mb-4">Business Verification</h3>
                                <div class="grid md:grid-cols-2 gap-y-4 text-sm">
                                    <div class="flex justify-between md:block">
                                        <p class="text-[10px] font-bold text-brand/50 uppercase tracking-widest">Company Name</p>
                                        <p class="font-bold text-brand"><?= htmlspecialchars($user['business_name']) ?></p>
                                    </div>
                                    <div class="flex justify-between md:block">
                                        <p class="text-[10px] font-bold text-brand/50 uppercase tracking-widest">BR Number</p>
                                        <p class="font-bold text-brand"><?= htmlspecialchars($user['br_number']) ?></p>
                                    </div>
                                </div>
                                <p class="text-[10px] font-medium text-brand/40 mt-6 italic">Business details cannot be changed online. Please contact our support team for updates.</p>
                            </div>

                            <button type="submit" id="btn-save-profile" class="bg-brand text-brand-light font-bold px-8 py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                Save Profile Changes
                            </button>
                        </form>
                    </div>
                </section>

                <!-- SECTION: ADDRESSES -->
                <section id="sec-addresses" class="hidden space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <h2 class="text-lg font-bold text-gray-900 tracking-tight uppercase">Delivery Addresses</h2>
                        <button onclick="openAddressModal('add')" class="flex items-center gap-2 text-xs font-bold text-brand hover:underline cursor-pointer">
                            <i class="ti ti-plus"></i> Add New Address
                        </button>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6" id="addresses-grid">
                        <?php foreach ($user_addresses as $addr): ?>
                        <?php $isDef = !empty($addr['is_default']); ?>
                        <div class="<?= $isDef ? 'bg-brand-light/30 border-2 border-brand' : 'bg-white border border-gray-150 group hover:border-brand/30' ?> rounded-3xl p-8 relative shadow-sm transition-all flex flex-col justify-between">
                            <div>
                                <?php if ($isDef): ?>
                                <span class="absolute top-4 right-4 px-2.5 py-0.5 bg-brand text-brand-light text-[9px] font-bold rounded-full uppercase tracking-wider">Default</span>
                                <?php endif; ?>
                                <h3 class="text-[15px] font-bold <?= $isDef ? 'text-brand' : 'text-gray-900 group-hover:text-brand' ?> mb-4 transition-colors"><?= htmlspecialchars($addr['title']) ?></h3>
                                <p class="text-sm <?= $isDef ? 'text-brand/70' : 'text-gray-500' ?> leading-relaxed font-medium">
                                    <?= nl2br(htmlspecialchars($addr['address'])) ?>
                                    <?php if ($addr['city']): ?><br><?= htmlspecialchars($addr['city']) ?><?php endif; ?>
                                    <?php if ($addr['province']): ?>, <?= htmlspecialchars($addr['province']) ?><?php endif; ?>
                                    <?php if ($addr['postal_code']): ?> <?= htmlspecialchars($addr['postal_code']) ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="mt-8 flex items-center gap-4 flex-wrap">
                                <button onclick='openAddressModal("edit", <?= htmlspecialchars(json_encode($addr), ENT_QUOTES, "UTF-8") ?>)' class="text-xs font-bold text-brand hover:underline cursor-pointer">Edit Address</button>
                                <?php if (!$isDef): ?>
                                <button onclick="setDefaultAddress(<?= $addr['id'] ?>)" class="text-xs font-bold text-gray-600 hover:text-brand hover:underline cursor-pointer">Set as Default</button>
                                <button onclick="deleteAddress(<?= $addr['id'] ?>)" class="text-xs font-bold text-red-500 hover:text-red-700 hover:underline cursor-pointer">Delete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($user_addresses)): ?>
                        <div class="col-span-2 bg-white border border-gray-100 rounded-3xl p-12 text-center text-gray-400 text-sm font-medium">
                            No delivery addresses saved yet. Click "Add New Address" above to add one.
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- SECTION: INVOICES -->
                <section id="sec-invoices" class="hidden">
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900 mb-8 tracking-tight uppercase">Tax Invoices</h2>
                        
                        <div class="divide-y divide-gray-50">
                            <?php foreach ($orders as $o): ?>
                            <div class="py-6 flex flex-col md:flex-row md:items-center justify-between gap-4 group">
                                <div class="flex items-center gap-6">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center text-brand border border-gray-100 group-hover:bg-brand-light transition-all">
                                        <i class="ti ti-file-invoice text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors tracking-tight">INV-2025-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></h3>
                                        <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mt-1"><?= date('d M Y', strtotime($o['created_at'])) ?> · KE-2025-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between md:justify-end gap-10">
                                    <span class="text-sm font-bold text-gray-900">LKR <?= number_format($o['total_amount']) ?></span>
                                    <button onclick="window.open('/order-success?id=<?= htmlspecialchars($o['id']) ?>&print=1', '_blank')" class="bg-gray-50 text-gray-900 hover:bg-brand hover:text-brand-light font-bold px-4 py-2 rounded-xl text-xs transition-all flex items-center gap-2 border border-gray-100 group-hover:border-brand/10 cursor-pointer">
                                        <i class="ti ti-download"></i> PDF
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($orders)): ?>
                            <div class="py-8 text-center text-sm text-gray-400">No invoices found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- SECTION: SECURITY -->
                <section id="sec-security" class="hidden">
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900 mb-8 tracking-tight uppercase">Change Password</h2>
                        
                        <!-- Feedback Alert Container -->
                        <div id="acc-pwd-alert" class="hidden mb-6 p-4 rounded-2xl text-xs font-bold flex items-center gap-3"></div>

                        <form id="form-change-password" onsubmit="updateAccountPassword(event)" class="max-w-md space-y-6">
                            <!-- Current Password -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Current Password</label>
                                <div class="relative">
                                    <input type="password" id="acc-curr-password" required placeholder=" ••••••••" class="w-full pl-5 pr-12 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                    <button type="button" onclick="toggleAccPasswordVisibility('acc-curr-password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand transition-colors focus:outline-none">
                                        <i class="ti ti-eye text-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">New Password</label>
                                <div class="relative">
                                    <input type="password" id="acc-new-password" required maxlength="20" placeholder=" Min. 8 characters" class="w-full pl-5 pr-12 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all" oninput="checkAccountPasswordStrength(this.value)">
                                    <button type="button" onclick="toggleAccPasswordVisibility('acc-new-password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand transition-colors focus:outline-none">
                                        <i class="ti ti-eye text-lg"></i>
                                    </button>
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div id="account-strengthBar" class="h-full rounded-full transition-all duration-400 w-0 bg-gray-300"></div>
                                </div>
                                <p id="account-strengthLabel" class="text-[10px] mt-1 text-gray-400 font-semibold"></p>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" id="acc-confirm-password" required maxlength="20" placeholder=" Repeat new password" class="w-full pl-5 pr-12 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                    <button type="button" onclick="toggleAccPasswordVisibility('acc-confirm-password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand transition-colors focus:outline-none">
                                        <i class="ti ti-eye text-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" id="btn-update-password" class="bg-brand text-brand-light font-bold px-8 py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                Update Password
                            </button>
                        </form>
                    </div>
                </section>

            </div>
        </div>
    </div>
</main>

<!-- Address Modal Popup -->
<div id="address-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4 transition-opacity duration-200 opacity-0">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full p-8 transform scale-95 transition-all duration-200">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
            <h3 id="address-modal-title" class="text-xl font-extrabold text-gray-900 tracking-tight">Add New Address</h3>
            <button onclick="closeAddressModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <i class="ti ti-x text-2xl"></i>
            </button>
        </div>

        <form id="form-address" onsubmit="saveAddress(event)" class="space-y-5">
            <input type="hidden" id="addr-id" value="">
            
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Address Label / Title *</label>
                <input type="text" id="addr-title" required placeholder="e.g. Colombo Branch, Main Warehouse" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Street Address *</label>
                <textarea id="addr-street" required rows="2" placeholder="e.g. No. 12, Main Street, Fort" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">City</label>
                    <input type="text" id="addr-city" placeholder="e.g. Colombo" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Province / State</label>
                    <input type="text" id="addr-province" placeholder="e.g. Western Province" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Postal Code</label>
                <input type="text" id="addr-postal" placeholder="e.g. 00300" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <input type="checkbox" id="addr-default" class="w-4 h-4 text-brand rounded border-gray-300 focus:ring-brand cursor-pointer">
                <label for="addr-default" class="text-xs font-bold text-gray-700 cursor-pointer">Set as default delivery address</label>
            </div>

            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeAddressModal()" class="flex-1 bg-gray-50 text-gray-700 font-bold py-3.5 rounded-2xl hover:bg-gray-100 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" id="btn-save-address" class="flex-1 bg-brand text-brand-light font-bold py-3.5 rounded-2xl hover:bg-brand-dark shadow-lg shadow-brand/20 transition-all transform hover:-translate-y-px cursor-pointer">Save Address</button>
            </div>
        </form>
    </div>
</div>

<style>
.active-nav {
    background-color: #0F6E56;
    color: #E1F5EE;
    box-shadow: 0 10px 15px -3px rgba(15, 110, 86, 0.2);
}
</style>

<script>
const sections = ['orders', 'profile', 'addresses', 'invoices', 'security'];

function showSection(id) {
  sections.forEach(s => {
    const sectionEl = document.getElementById('sec-' + s);
    const navEl = document.getElementById('nav-' + s);
    
    if (s === id) {
        sectionEl.classList.remove('hidden');
        navEl.classList.add('active-nav');
        navEl.classList.remove('text-gray-400', 'hover:bg-gray-50', 'hover:text-brand');
    } else {
        sectionEl.classList.add('hidden');
        navEl.classList.remove('active-nav');
        navEl.classList.add('text-gray-400', 'hover:bg-gray-50', 'hover:text-brand');
    }
  });
}

// 1. Account Orders Filter
function filterAccountOrders() {
    var status = (document.getElementById('order-status-filter')?.value || 'all').toLowerCase();
    var query = (document.getElementById('order-search-input')?.value || '').toLowerCase().trim();
    
    var rows = document.querySelectorAll('.account-order-row');
    var visibleCount = 0;

    rows.forEach(r => {
        var rStatus = (r.dataset.status || '').toLowerCase();
        var rSearch = (r.dataset.search || '').toLowerCase();
        
        var matchStatus = (status === 'all' || rStatus === status);
        var matchSearch = (!query || rSearch.includes(query));

        if (matchStatus && matchSearch) {
            r.classList.remove('hidden');
            visibleCount++;
        } else {
            r.classList.add('hidden');
        }
    });

    var emptyEl = document.getElementById('account-orders-empty');
    if (emptyEl) {
        if (visibleCount === 0) emptyEl.classList.remove('hidden');
        else emptyEl.classList.add('hidden');
    }
}

// 2. Profile Update
function updateProfile(e) {
    e.preventDefault();
    var firstName = document.getElementById('profile-first-name').value.trim();
    var lastName = document.getElementById('profile-last-name').value.trim();
    var email = document.getElementById('profile-email').value.trim();
    var phone = document.getElementById('profile-phone').value.trim();
    var alertContainer = document.getElementById('profile-alert');
    var submitBtn = document.getElementById('btn-save-profile');

    function showAlert(msg, isSuccess) {
        if (!alertContainer) return;
        alertContainer.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border-red-100', 'bg-green-50', 'text-green-700', 'border-green-100');
        if (isSuccess) {
            alertContainer.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-100');
            alertContainer.innerHTML = `<i class="ti ti-circle-check text-lg shrink-0"></i> <span>${msg}</span>`;
        } else {
            alertContainer.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-100');
            alertContainer.innerHTML = `<i class="ti ti-alert-circle text-lg shrink-0"></i> <span>${msg}</span>`;
        }
    }

    if (submitBtn) setButtonLoading(submitBtn, true, 'Saving...');

    fetch('/api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            first_name: firstName,
            last_name: lastName,
            email: email,
            phone: phone
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) setButtonLoading(submitBtn, false);
        if (data.status === 'success') {
            showAlert(data.message || 'Profile updated successfully!', true);
            if (typeof showToast === 'function') showToast(data.message || 'Profile updated successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Failed to update profile.', false);
        }
    })
    .catch(err => {
        if (submitBtn) setButtonLoading(submitBtn, false);
        console.error(err);
        showAlert("Network error occurred.", false);
    });
}

// 3, 4, 5. Delivery Address Management
function openAddressModal(mode, data = null) {
    var modal = document.getElementById('address-modal');
    var title = document.getElementById('address-modal-title');
    document.getElementById('form-address').reset();

    if (mode === 'edit' && data) {
        title.textContent = 'Edit Address';
        document.getElementById('addr-id').value = data.id || '';
        document.getElementById('addr-title').value = data.title || '';
        document.getElementById('addr-street').value = data.address || '';
        document.getElementById('addr-city').value = data.city || '';
        document.getElementById('addr-province').value = data.province || '';
        document.getElementById('addr-postal').value = data.postal_code || '';
        document.getElementById('addr-default').checked = !!parseInt(data.is_default);
    } else {
        title.textContent = 'Add New Address';
        document.getElementById('addr-id').value = '';
        document.getElementById('addr-default').checked = false;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    void modal.offsetWidth;
    modal.classList.remove('opacity-0');
    if (modal.firstElementChild) {
        modal.firstElementChild.classList.remove('scale-95');
    }
}

function closeAddressModal() {
    var modal = document.getElementById('address-modal');
    modal.classList.add('opacity-0');
    if (modal.firstElementChild) {
        modal.firstElementChild.classList.add('scale-95');
    }
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

function saveAddress(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-save-address');
    var payload = {
        action: 'save',
        address_id: document.getElementById('addr-id').value,
        title: document.getElementById('addr-title').value.trim(),
        address: document.getElementById('addr-street').value.trim(),
        city: document.getElementById('addr-city').value.trim(),
        province: document.getElementById('addr-province').value.trim(),
        postal_code: document.getElementById('addr-postal').value.trim(),
        is_default: document.getElementById('addr-default').checked ? 1 : 0
    };

    if (btn) setButtonLoading(btn, true, 'Saving...');

    fetch('/api/addresses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (btn) setButtonLoading(btn, false);
        if (data.status === 'success') {
            if (typeof showToast === 'function') showToast(data.message || 'Address saved.', 'success');
            closeAddressModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        if (btn) setButtonLoading(btn, false);
        if (typeof showToast === 'function') showToast('Network error occurred.', 'error');
    });
}

function setDefaultAddress(id) {
    uiConfirm("Set this address as your default delivery address?", () => {
        fetch('/api/addresses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_default', address_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(data.message || 'Default address updated.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(err => {
            if (typeof showToast === 'function') showToast('Network error occurred.', 'error');
        });
    }, "Set Default Address?", "Set Default");
}

function deleteAddress(id) {
    uiConfirm("Are you sure you want to delete this address?", () => {
        fetch('/api/addresses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', address_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(data.message || 'Address deleted.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(err => {
            if (typeof showToast === 'function') showToast('Network error occurred.', 'error');
        });
    }, "Delete Address?", "Delete");
}

function checkAccountPasswordStrength(password) {
    const bar = document.getElementById('account-strengthBar');
    const label = document.getElementById('account-strengthLabel');
    if (!bar || !label) return;

    if (!password) {
        bar.className = "h-full rounded-full transition-all duration-400 w-0 bg-gray-300";
        bar.style.width = "0%";
        label.textContent = "";
        return;
    }

    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNum = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    let width = "30%";
    let colorClass = "bg-red-500";
    let text = "Low";
    let textClass = "text-[10px] mt-1 text-red-500 font-semibold";

    if (hasUpper && hasLower && hasNum && hasSpecial) {
        width = "100%";
        colorClass = "bg-green-500";
        text = "Strong";
        textClass = "text-[10px] mt-1 text-green-600 font-semibold";
    } else if (hasUpper && hasLower && hasNum) {
        width = "65%";
        colorClass = "bg-yellow-500";
        text = "Medium";
        textClass = "text-[10px] mt-1 text-yellow-600 font-semibold";
    }

    bar.style.width = width;
    bar.className = "h-full rounded-full transition-all duration-400 " + colorClass;
    label.textContent = text;
    label.className = textClass;
}

function toggleAccPasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('ti-eye');
            icon.classList.add('ti-eye-off');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('ti-eye-off');
            icon.classList.add('ti-eye');
        }
    }
}

function updateAccountPassword(e) {
    e.preventDefault();
    const currPass = document.getElementById('acc-curr-password').value;
    const newPass = document.getElementById('acc-new-password').value;
    const confirmPass = document.getElementById('acc-confirm-password').value;
    const alertContainer = document.getElementById('acc-pwd-alert');
    const submitBtn = document.getElementById('btn-update-password');

    function showAlert(msg, isSuccess) {
        if (!alertContainer) return;
        alertContainer.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border-red-100', 'bg-green-50', 'text-green-700', 'border-green-100');
        if (isSuccess) {
            alertContainer.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-100');
            alertContainer.innerHTML = `<i class="ti ti-circle-check text-lg shrink-0"></i> <span>${msg}</span>`;
        } else {
            alertContainer.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-100');
            alertContainer.innerHTML = `<i class="ti ti-alert-circle text-lg shrink-0"></i> <span>${msg}</span>`;
        }
    }

    if (!currPass || !newPass || !confirmPass) {
        showAlert("All fields are required.", false);
        return;
    }

    if (newPass.length < 8) {
        showAlert("New password must be at least 8 characters long.", false);
        return;
    }

    if (newPass !== confirmPass) {
        showAlert("New password and confirmation password do not match.", false);
        return;
    }

    if (submitBtn) setButtonLoading(submitBtn, true, 'Updating Password...');

    fetch('/api/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            current_password: currPass,
            new_password: newPass,
            confirm_password: confirmPass
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) setButtonLoading(submitBtn, false);
        if (data.status === 'success') {
            showAlert(data.message || 'Password updated successfully!', true);
            document.getElementById('form-change-password').reset();
            checkAccountPasswordStrength('');
        } else {
            showAlert(data.message || 'Failed to update password.', false);
        }
    })
    .catch(err => {
        if (submitBtn) setButtonLoading(submitBtn, false);
        console.error(err);
        showAlert("Network error occurred. Please try again.", false);
    });
}
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
