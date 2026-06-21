<?php
require_once __DIR__ . "/database/connection.php";

$user_id = 1; // Default to Kamal Perera (User ID 1)
$user = null;
$orders = [];
$total_orders = 0;
$total_spent = 0;
$units_ordered = 0;
$last_order_date = "--";
$last_order_year = "";

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch User details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
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

// Fallback to mock data if database connection fails or user is not found
if (!$user) {
    $user = [
        'first_name' => 'Kamal',
        'last_name' => 'Perera',
        'email' => 'kamal@abcgarments.lk',
        'phone' => '+94 77 123 4567',
        'business_name' => 'ABC Garments (Pvt) Ltd',
        'br_number' => 'PV 12345',
        'status' => 'approved',
        'address' => "No. 45, Factory Road\nKatunayake, Western Province\nSri Lanka"
    ];
    $total_orders = 14;
    $total_spent = 1200000; // 1.2M LKR
    $units_ordered = 8400;
    $last_order_date = '12 May';
    $last_order_year = '2025';
    
    $orders = [
        [
            'id' => 1,
            'created_at' => '2025-05-12 09:14:00',
            'items_count' => 3,
            'total_units' => 420,
            'total_amount' => 71933.00,
            'status' => 'pending'
        ],
        [
            'id' => 2,
            'created_at' => '2025-04-28 14:30:00',
            'items_count' => 2,
            'total_units' => 300,
            'total_amount' => 48600.00,
            'status' => 'shipped'
        ],
        [
            'id' => 3,
            'created_at' => '2025-03-15 10:00:00',
            'items_count' => 5,
            'total_units' => 800,
            'total_amount' => 124000.00,
            'status' => 'delivered'
        ]
    ];
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
                <span><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
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
                        <div class="w-12 h-12 rounded-full bg-brand-light text-brand flex items-center justify-center font-bold shadow-sm border border-brand/10">
                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
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
                        <button onclick="showSection('orders')" id="nav-orders" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold transition-all active-nav">
                            <i class="ti ti-package text-xl"></i>
                            Order History
                        </button>
                        <button onclick="showSection('profile')" id="nav-profile" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all">
                            <i class="ti ti-user text-xl"></i>
                            My Profile
                        </button>
                        <button onclick="showSection('addresses')" id="nav-addresses" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all">
                            <i class="ti ti-map-pin text-xl"></i>
                            Addresses
                        </button>
                        <button onclick="showSection('invoices')" id="nav-invoices" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all">
                            <i class="ti ti-file-invoice text-xl"></i>
                            Invoices
                        </button>
                        <button onclick="showSection('security')" id="nav-security" class="nav-btn group flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-50 hover:text-brand transition-all">
                            <i class="ti ti-lock text-xl"></i>
                            Security
                        </button>
                        
                        <div class="h-px bg-gray-50 my-4"></div>
                        
                        <a href="/login" class="flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-bold text-red-400 hover:bg-red-50 hover:text-red-600 transition-all">
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
                                <select class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-[11px] font-bold text-gray-500 uppercase tracking-widest outline-none focus:ring-1 focus:ring-brand">
                                    <option>All Statuses</option>
                                    <option>Pending</option>
                                    <option>Shipped</option>
                                    <option>Delivered</option>
                                </select>
                                <div class="relative">
                                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input type="text" placeholder="Search orders..." class="bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-2 text-xs outline-none focus:ring-1 focus:ring-brand w-full md:w-48">
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
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach ($orders as $o): ?>
                                    <tr class="group">
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
                                    
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-sm text-gray-400">No orders found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- SECTION: PROFILE -->
                <section id="sec-profile" class="hidden">
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900 mb-8 tracking-tight">My Profile</h2>
                        
                        <div class="space-y-8">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">First Name</label>
                                    <input type="text" value="<?= htmlspecialchars($user['first_name']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Last Name</label>
                                    <input type="text" value="<?= htmlspecialchars($user['last_name']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Email Address</label>
                                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phone Number</label>
                                    <input type="tel" value="<?= htmlspecialchars($user['phone']) ?>" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
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

                            <button class="bg-brand text-brand-light font-bold px-8 py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95">
                                Save Profile Changes
                            </button>
                        </div>
                    </div>
                </section>

                <!-- SECTION: ADDRESSES -->
                <section id="sec-addresses" class="hidden space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <h2 class="text-lg font-bold text-gray-900 tracking-tight uppercase">Delivery Addresses</h2>
                        <button class="flex items-center gap-2 text-xs font-bold text-brand hover:underline">
                            <i class="ti ti-plus"></i> Add New Address
                        </button>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-brand-light/30 border-2 border-brand rounded-3xl p-8 relative shadow-sm">
                            <span class="absolute top-4 right-4 px-2 py-0.5 bg-brand text-brand-light text-[9px] font-bold rounded-full uppercase">Default</span>
                            <h3 class="text-[15px] font-bold text-brand mb-4"><?= htmlspecialchars($user['business_name']) ?> Warehouse</h3>
                            <p class="text-sm text-brand/70 leading-relaxed font-medium">
                                <?= nl2br(htmlspecialchars($user['address'])) ?>
                            </p>
                            <div class="mt-8 flex gap-4">
                                <button class="text-xs font-bold text-brand hover:underline">Edit Address</button>
                            </div>
                        </div>
                        <div class="bg-white border border-gray-150 rounded-3xl p-8 relative shadow-sm group hover:border-brand/30 transition-all">
                            <h3 class="text-[15px] font-bold text-gray-900 mb-4 group-hover:text-brand transition-colors">Colombo Showroom</h3>
                            <p class="text-sm text-gray-500 leading-relaxed font-medium">
                                No. 12, Main Street<br>
                                Colombo 03, Western Province<br>
                                Sri Lanka
                            </p>
                            <div class="mt-8 flex gap-4">
                                <button class="text-xs font-bold text-brand hover:underline">Edit Address</button>
                                <button class="text-xs font-bold text-red-400 hover:text-red-600">Delete</button>
                            </div>
                        </div>
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
                                    <button class="bg-gray-50 text-gray-900 hover:bg-brand hover:text-brand-light font-bold px-4 py-2 rounded-xl text-xs transition-all flex items-center gap-2 border border-gray-100 group-hover:border-brand/10">
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
                        
                        <div class="max-w-md space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Current Password</label>
                                <input type="password" placeholder="••••••••" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">New Password</label>
                                <input type="password" placeholder="Min. 8 characters" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Confirm New Password</label>
                                <input type="password" placeholder="Repeat new password" class="w-full px-5 py-3.5 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 transition-all">
                            </div>

                            <button class="bg-brand text-brand-light font-bold px-8 py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95">
                                Update Password
                            </button>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </div>
</main>

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
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
