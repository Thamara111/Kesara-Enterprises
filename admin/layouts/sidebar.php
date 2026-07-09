<?php
$current_page = $view ?? 'dashboard';
$role = $_SESSION['admin_role'] ?? 'guest';

// Role helper flags
$is_admin = ($role === 'admin');
$has_finance = in_array($role, ['admin', 'finance_manager']);
$has_supplier = in_array($role, ['admin', 'supplier_manager']);
$has_delivery = in_array($role, ['admin', 'delivery_manager']);
?>
<!-- Sidebar Backdrop (Mobile Only) -->
<div id="admin-sidebar-backdrop" class="hidden fixed inset-0 bg-black/60 z-30 lg:hidden"></div>

<!-- SIDEBAR -->
<aside id="admin-sidebar" class="w-72 bg-gray-900 flex-shrink-0 flex flex-col fixed inset-y-0 left-0 z-40 shadow-2xl h-screen overflow-hidden border-r border-white/5 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <!-- Brand Header -->
    <div class="p-8 border-b border-white/5 bg-gray-950/20 flex items-center justify-between">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center shadow-lg shadow-brand/20 ring-4 ring-brand/10 shrink-0">
                <i class="ti ti-shield-lock text-xl text-brand-light"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'KE Admin') ?></h2>
                <p class="text-[9px] font-bold text-brand-light uppercase tracking-wider truncate mt-0.5"><?= htmlspecialchars(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'CONTROL PANEL')) ?></p>
            </div>
        </div>
        <button id="admin-sidebar-close" class="lg:hidden p-2 text-gray-400 hover:text-white transition-colors focus:outline-none" aria-label="Close Sidebar">
            <i class="ti ti-x text-xl"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-6 space-y-1 overflow-y-auto custom-scrollbar">
        <!-- Main Section (Dashboard, Orders, Products, Customers, Inventory, Reports) -->
        <div class="space-y-1 mb-4">
            <a href="/admin-dashboard" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'dashboard' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-layout-grid text-xl"></i>
                Dashboard
            </a>
            <a href="/admin-inquiries" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'inquiries' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-inbox text-xl"></i>
                Inquiries
            </a>
            
            <?php if ($has_finance): ?>
            <a href="/admin-orders" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'orders' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-box text-xl"></i>
                Orders
            </a>
            <a href="/admin-products" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'products' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-shirt text-xl"></i>
                Products
            </a>
            <a href="/admin-categories" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'categories' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-category text-xl"></i>
                Categories
            </a>
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
            <a href="/admin-customers" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'customers' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-users text-xl"></i>
                Customers
            </a>
            <a href="/admin-users" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'users' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-user-cog text-xl"></i>
                Users
            </a>
            <a href="/admin-trash" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'trash' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-trash text-xl"></i>
                Recycle Bin
            </a>
            <a href="/admin-whatsapp" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'whatsapp' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-brand-whatsapp text-xl"></i>
                WhatsApp Simulator
            </a>
            <?php endif; ?>
            
            <?php if ($has_finance): ?>
            <a href="/admin-inventory" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'inventory' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-package text-xl"></i>
                Inventory
            </a>
            <a href="/admin-reports" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'reports' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-chart-bar text-xl"></i>
                Reports
            </a>
            <?php endif; ?>
        </div>

        <?php if ($has_supplier): ?>
        <div class="h-px bg-white/5 my-4"></div>

        <!-- Supply Chain Section -->
        <div class="space-y-1 mb-4">
            <p class="px-5 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Supply Chain</p>
            <a href="/admin-suppliers" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo ($current_page === 'suppliers' || $current_page === 'supplier_form') ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-building-warehouse text-xl"></i>
                Suppliers
            </a>
            <div class="pl-12 space-y-1">
                <a href="/admin-purchase-orders" class="flex items-center gap-4 w-full py-2 text-xs font-bold transition-all <?php echo $current_page === 'purchase_orders' ? 'text-brand-light font-bold' : 'text-gray-500 hover:text-white'; ?>">
                    <i class="ti ti-file-invoice text-lg"></i>
                    Purchase orders
                </a>
                <a href="/admin-goods-received" class="flex items-center gap-4 w-full py-2 text-xs font-bold transition-all <?php echo $current_page === 'goods_received' ? 'text-brand-light font-bold' : 'text-gray-500 hover:text-white'; ?>">
                    <i class="ti ti-truck-delivery text-lg"></i>
                    Goods received
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($has_delivery): ?>
        <div class="h-px bg-white/5 my-4"></div>

        <!-- Delivery Section -->
        <div class="space-y-1 mb-4">
            <p class="px-5 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Delivery</p>
            <a href="/admin-personnel" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'personnel' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-motorbike text-xl"></i>
                Personnel
            </a>
            <a href="/admin-assignments" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'assignments' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-map-pin text-xl"></i>
                Assignments
            </a>
            <a href="/admin-tracking" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold transition-all <?php echo $current_page === 'tracking' ? 'bg-brand-light text-brand shadow-lg shadow-brand/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="ti ti-radar text-xl"></i>
                Live Tracking
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Footer / Logout -->
    <div class="p-8 border-t border-white/5">
        <a href="/" target="_blank" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold text-green-100 hover:bg-green-400/10 transition-all">
            <i class="ti ti-world text-xl"></i>
            Go to Live Site
        </a>
        <a href="/admin/admin_index.php?action=logout" class="flex items-center gap-4 w-full px-5 py-3 rounded-xl text-sm font-bold text-red-400 hover:bg-red-400/10 transition-all">
            <i class="ti ti-logout text-xl"></i>
            Sign Out
        </a>
    </div>
</aside>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.1);
}
</style>

<script>
    (function() {
        const toggleBtn = document.getElementById('admin-sidebar-toggle');
        const closeBtn = document.getElementById('admin-sidebar-close');
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');

        function toggleSidebar() {
            if (sidebar && backdrop) {
                sidebar.classList.toggle('-translate-x-full');
                backdrop.classList.toggle('hidden');
            }
        }

        if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
        if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
        if (backdrop) backdrop.addEventListener('click', toggleSidebar);
    })();
</script>
