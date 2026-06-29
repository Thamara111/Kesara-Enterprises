<?php
/**
 * Admin Index Controller
 * Handles routing and layout assembly for the Kesara Enterprises administrative panel.
 */

session_start();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: /admin-login");
    exit;
}

require_once __DIR__ . "/../database/connection.php";

// Define the current view based on the GET parameter, defaulting to 'dashboard'
$view = $_GET['view'] ?? 'dashboard';

// If already logged in and requesting login page, redirect to dashboard
if (isset($_SESSION['admin_id']) && $view === 'login') {
    header("Location: /admin-dashboard");
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && $view !== 'login') {
    header("Location: /admin-login");
    exit;
}

// Handle login POST request immediately before any HTML headers/output are sent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $view === 'login') {
    require_once __DIR__ . "/view/login.view.php";
    exit;
}

// Check role permissions if logged in
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'] ?? 'guest';

    $role_access = [
        'admin' => ['dashboard', 'orders', 'products', 'categories', 'customers', 'users', 'inventory', 'reports', 'suppliers', 'supplier_form', 'purchase_orders', 'goods_received', 'personnel', 'assignments', 'tracking', 'login', 'trash', 'inquiries'],
        'finance_manager' => ['dashboard', 'orders', 'products', 'categories', 'inventory', 'reports', 'login', 'inquiries'],
        'supplier_manager' => ['dashboard', 'suppliers', 'supplier_form', 'purchase_orders', 'goods_received', 'login', 'inquiries'],
        'delivery_manager' => ['dashboard', 'personnel', 'assignments', 'tracking', 'login', 'inquiries']
    ];

    $allowed_views = $role_access[$role] ?? [];
    if (!in_array($view, $allowed_views)) {
        $view = 'access_denied';
    }
}

// Configuration mapping for page metadata and view titles
$view_config = [
    'dashboard' => [
        'title' => 'Admin Dashboard | Kesara Enterprises',
        'description' => 'System overview and real-time metrics for Kesara Enterprises.',
        'show_sidebar' => true
    ],
    'orders' => [
        'title' => 'Order Management | Kesara Enterprises',
        'description' => 'Manage wholesale orders and fulfillment status.',
        'show_sidebar' => true
    ],
    'products' => [
        'title' => 'Product Catalog | Kesara Enterprises',
        'description' => 'Manage wholesale products and pricing tiers.',
        'show_sidebar' => true
    ],
    'categories' => [
        'title' => 'Category Management | Kesara Enterprises',
        'description' => 'Manage product categories and catalog sections.',
        'show_sidebar' => true
    ],
    'reports' => [
        'title' => 'Analytics & Reports | Kesara Enterprises',
        'description' => 'System performance and wholesale business analytics.',
        'show_sidebar' => true
    ],
    'inventory' => [
        'title' => 'Inventory Management | Kesara Enterprises',
        'description' => 'Manage stock levels and warehouse replenishment.',
        'show_sidebar' => true
    ],
    'customers' => [
        'title' => 'Customer Management | Kesara Enterprises',
        'description' => 'Manage wholesale buyer accounts and verification.',
        'show_sidebar' => true
    ],
    'users' => [
        'title' => 'Staff User Management | Kesara Enterprises',
        'description' => 'Register and manage administrative manager credentials.',
        'show_sidebar' => true
    ],
    'suppliers' => [
        'title' => 'Supplier Management | Kesara Enterprises',
        'description' => 'Manage supply chain partners and procurement.',
        'show_sidebar' => true
    ],
    'supplier_form' => [
        'title' => 'Supplier Form | Kesara Enterprises',
        'description' => 'Add or edit supply chain partners.',
        'show_sidebar' => true
    ],
    'purchase_orders' => [
        'title' => 'Purchase Orders | Kesara Enterprises',
        'description' => 'Manage purchase orders and procurement.',
        'show_sidebar' => true
    ],
    'goods_received' => [
        'title' => 'Goods Received Note | Kesara Enterprises',
        'description' => 'Record and manage goods received notes.',
        'show_sidebar' => true
    ],
    'personnel' => [
        'title' => 'Delivery Personnel | Kesara Enterprises',
        'description' => 'Manage delivery personnel and performance.',
        'show_sidebar' => true
    ],
    'assignments' => [
        'title' => 'Delivery Assignments | Kesara Enterprises',
        'description' => 'Manage driver delivery assignments.',
        'show_sidebar' => true
    ],
    'tracking' => [
        'title' => 'Delivery Tracking | Kesara Enterprises',
        'description' => 'Live tracking and status updates of deliveries.',
        'show_sidebar' => true
    ],
    'access_denied' => [
        'title' => 'Access Denied | Kesara Enterprises',
        'description' => 'Unauthorised access attempt to restricted page.',
        'show_sidebar' => true
    ],
    'trash' => [
        'title' => 'Recycle Bin | Kesara Enterprises',
        'description' => 'Manage deleted records and suspended customers.',
        'show_sidebar' => true
    ],
    'inquiries' => [
        'title' => 'Customer Inquiries | Kesara Enterprises',
        'description' => 'Manage and assign customer inquiries.',
        'show_sidebar' => true
    ],
    'login' => [
        'title' => 'Admin Access | Kesara Enterprises',
        'description' => 'Restricted administrative access.',
        'show_sidebar' => false
    ]
];

// Fallback to dashboard if the requested view doesn't exist
if (!isset($view_config[$view])) {
    $view = 'dashboard';
}

$current_config = $view_config[$view];

// Set metadata for layouts/head.php
$page_meta = [
    'title' => $current_config['title'],
    'description' => $current_config['description'],
];

// Path adjustment for head.php since we are in /admin/
require_once __DIR__ . "/../layouts/head.php";

$view_file_mappings = [
    'purchase_orders' => 'suppliers.purchase_orders',
    'goods_received' => 'suppliers.goods_received_note',
    'personnel' => 'delivery.personnel',
    'assignments' => 'delivery.assignments',
    'tracking' => 'delivery.tracking'
];
$view_file_name = $view_file_mappings[$view] ?? $view;
$view_file = __DIR__ . "/view/{$view_file_name}.view.php";

if ($current_config['show_sidebar']): ?>
<div class="h-screen bg-gray-50 overflow-hidden font-sans">
    <?php require_once __DIR__ . "/layouts/sidebar.php"; ?>

    <!-- Main Content and Mobile Header wrapper -->
    <div class="h-screen flex flex-col admin-content-area overflow-hidden">

        <!-- Mobile Top Nav Bar (hidden on desktop) -->
        <header class="lg:hidden bg-gray-900 text-white px-6 py-4 flex items-center justify-between border-b border-white/5 shrink-0 z-30 shadow-md">
            <div class="flex items-center gap-3">
                <button id="admin-sidebar-toggle" class="p-2 -ml-2 text-gray-400 hover:text-white transition-colors focus:outline-none" aria-label="Toggle Sidebar">
                    <i class="ti ti-menu-2 text-2xl"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center">
                        <i class="ti ti-shield-lock text-brand-light"></i>
                    </div>
                    <span class="text-sm font-bold tracking-tight uppercase">Control Panel</span>
                </div>
            </div>
            <div class="text-[10px] font-bold text-brand-light bg-brand/10 border border-brand/20 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                <?= htmlspecialchars(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'Admin')) ?>
            </div>
        </header>

        <?php if (file_exists($view_file)): ?>
            <?php require_once $view_file; ?>
        <?php else: ?>
            <div class="p-10"><h1 class="text-xl font-bold text-red-500">Error: View file not found (<?= basename($view_file) ?>)</h1></div>
        <?php endif; ?>

    </div><!-- /main content wrapper -->
</div><!-- /outer container -->
<?php else:
    // Unauthenticated layout (e.g. Login)
    if (file_exists($view_file)) {
        require_once $view_file;
    }
endif;
?>
</body>

</html>