<?php
require_once __DIR__ . "/database/connection.php";

$catalog_products = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT p.name, p.sku, p.moq, p.base_price AS price, p.status 
                             FROM products p");
        $catalog_products = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Silently fail to fallback
    }
}

// Fallback to mock data if DB is offline or empty
if (empty($catalog_products)) {
    $catalog_products = [
        ['name' => 'Classic Brief', 'sku' => 'KB-001', 'moq' => 50, 'price' => 120.00, 'status' => 'In stock'],
        ['name' => 'Stretch Boxer', 'sku' => 'KB-008', 'moq' => 100, 'price' => 195.00, 'status' => 'In stock'],
        ['name' => 'Ladies Hipster', 'sku' => 'KL-003', 'moq' => 50, 'price' => 145.00, 'status' => 'Low stock'],
        ['name' => 'Kids Trunk Set', 'sku' => 'KC-012', 'moq' => 60, 'price' => 98.00, 'status' => 'In stock'],
        ['name' => 'Modal Trunk', 'sku' => 'KB-015', 'moq' => 100, 'price' => 260.00, 'status' => 'In stock'],
        ['name' => 'Sports Brief', 'sku' => 'KB-022', 'moq' => 50, 'price' => 175.00, 'status' => 'Low stock'],
        ['name' => 'Cotton Trunk', 'sku' => 'KB-034', 'moq' => 80, 'price' => 210.00, 'status' => 'In stock'],
        ['name' => 'Seamless Brief', 'sku' => 'KL-009', 'moq' => 50, 'price' => 180.00, 'status' => 'In stock'],
    ];
}

$page_meta = [
    'title' => 'Product Catalog | Kesara Enterprises',
    'description' => 'Browse our extensive range of quality innerwear for wholesale orders. Briefs, boxers, trunks, and more.',
];
require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 py-12 min-h-screen">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        
        <!-- HEADER -->
        <div class="mb-10">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Product Catalog</h1>
            <p class="text-gray-500">Premium wholesale innerwear for your business.</p>
        </div>

        <!-- Mobile Filters Backdrop -->
        <div id="filters-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

        <div class="grid lg:grid-cols-[280px_1fr] gap-8">
            
            <!-- SIDEBAR FILTERS -->
            <aside id="catalog-filters" class="fixed inset-y-0 left-0 w-80 bg-white z-50 p-6 border-r border-gray-100 overflow-y-auto transform -translate-x-full transition-transform duration-300 ease-in-out lg:relative lg:w-auto lg:bg-transparent lg:z-0 lg:p-0 lg:border-none lg:translate-x-0 lg:transition-none lg:block shadow-2xl lg:shadow-none">
                <div class="bg-white lg:border lg:border-gray-100 lg:rounded-2xl lg:p-6 lg:shadow-sm lg:sticky lg:top-24">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="ti ti-adjustments-horizontal text-brand"></i>
                            Filters
                        </h2>
                        <div class="flex items-center gap-3">
                            <button class="text-xs text-brand font-semibold hover:underline">Reset</button>
                            <button id="close-filters" class="lg:hidden p-1 text-gray-450 hover:text-brand transition-colors focus:outline-none">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Category</label>
                        <div class="flex flex-wrap gap-2">
                            <button class="px-3 py-1.5 rounded-full text-xs font-semibold bg-brand text-brand-light">All</button>
                            <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand transition-colors">Briefs</button>
                            <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand transition-colors">Boxers</button>
                            <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand transition-colors">Trunks</button>
                            <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand transition-colors">Ladies</button>
                        </div>
                    </div>

                    <hr class="border-gray-50 my-6">

                    <!-- Size -->
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Size</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button class="py-2 rounded-lg text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand">XS</button>
                            <button class="py-2 rounded-lg text-xs font-semibold bg-brand-light text-brand border border-brand/20">S</button>
                            <button class="py-2 rounded-lg text-xs font-semibold bg-brand-light text-brand border border-brand/20">M</button>
                            <button class="py-2 rounded-lg text-xs font-semibold bg-brand-light text-brand border border-brand/20">L</button>
                            <button class="py-2 rounded-lg text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand">XL</button>
                            <button class="py-2 rounded-lg text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand">XXL</button>
                        </div>
                    </div>

                    <hr class="border-gray-50 my-6">

                    <!-- Availability -->
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Stock Status</label>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked class="rounded text-brand focus:ring-brand border-gray-300">
                                <span class="text-sm text-gray-600">In Stock</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" class="rounded text-brand focus:ring-brand border-gray-300">
                                <span class="text-sm text-gray-600">On Order</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Min. Order Qty (MOQ)</label>
                        <input type="number" placeholder="Min. units" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:ring-1 focus:ring-brand">
                    </div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <div class="space-y-6">
                
                <!-- TOP BAR -->
                <div class="bg-white border border-gray-100 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
                    <div class="flex items-center gap-3 w-full md:w-96">
                        <button id="open-filters" class="lg:hidden px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-600 flex items-center gap-2 hover:bg-gray-100 transition-all shrink-0">
                            <i class="ti ti-adjustments-horizontal text-brand"></i>
                            Filters
                        </button>
                        <div class="relative flex-1">
                            <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" placeholder="Search products..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm outline-none focus:ring-1 focus:ring-brand">
                        </div>
                    </div>
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <span class="text-xs font-semibold text-gray-400 uppercase whitespace-nowrap">Sort by</span>
                        <select class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-1 focus:ring-brand flex-1 md:flex-none appearance-none pr-10 relative">
                            <option>Newest Arrivals</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                            <option>Alphabetical</option>
                        </select>
                        <span class="text-xs text-gray-400 whitespace-nowrap">24 items</span>
                    </div>
                </div>

                <!-- ACTIVE FILTERS -->
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Active:</span>
                    <span class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm">
                        Category: All
                        <i class="ti ti-x cursor-pointer hover:scale-110 transition-transform"></i>
                    </span>
                    <span class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm">
                        Sizes: S, M, L
                        <i class="ti ti-x cursor-pointer hover:scale-110 transition-transform"></i>
                    </span>
                </div>

                <!-- PRODUCT GRID -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    
                    <?php foreach ($catalog_products as $p): ?>
                    <a href="/product?sku=<?= htmlspecialchars($p['sku']) ?>" class="bg-white border border-gray-100 rounded-2xl overflow-hidden group hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="bg-gray-50 h-48 flex items-center justify-center border-b border-gray-50 relative overflow-hidden">
                            <i class="ti ti-shirt text-6xl text-gray-200 group-hover:scale-110 transition-transform duration-500"></i>
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300"></div>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-[15px] font-bold text-gray-900 group-hover:text-brand transition-colors"><?= htmlspecialchars($p['name']) ?></h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= strtolower($p['status']) === 'in stock' ? 'bg-brand-light text-brand' : 'bg-amber-50 text-amber-600 border border-amber-100' ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-400 font-medium mb-4 tracking-wider uppercase"><?= htmlspecialchars($p['sku']) ?></p>
                            
                            <div class="flex items-center justify-between mt-auto">
                                <div class="space-y-1">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Wholesale Price</p>
                                    <p class="text-sm font-bold text-gray-900">LKR <?= is_numeric($p['price']) ? number_format($p['price'], 2) : htmlspecialchars($p['price']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Min Order</p>
                                    <p class="text-[12px] font-bold text-gray-600"><?= htmlspecialchars($p['moq']) ?> pcs</p>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>

                </div>

                <!-- PAGINATION -->
                <div class="flex justify-center gap-2 py-10">
                    <button class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-100 bg-white hover:bg-brand hover:text-white transition-all shadow-sm">
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <button class="w-10 h-10 rounded-xl flex items-center justify-center bg-brand text-white font-bold shadow-md">1</button>
                    <button class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-gray-100 hover:border-brand hover:text-brand font-bold transition-all shadow-sm">2</button>
                    <button class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-gray-100 hover:border-brand hover:text-brand font-bold transition-all shadow-sm">3</button>
                    <button class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-100 bg-white hover:bg-brand hover:text-white transition-all shadow-sm">
                        <i class="ti ti-chevron-right"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
    const openFiltersBtn = document.getElementById('open-filters');
    const closeFiltersBtn = document.getElementById('close-filters');
    const filtersSidebar = document.getElementById('catalog-filters');
    const filtersBackdrop = document.getElementById('filters-backdrop');

    function toggleFilters() {
        filtersSidebar.classList.toggle('-translate-x-full');
        filtersBackdrop.classList.toggle('hidden');
    }

    if (openFiltersBtn) openFiltersBtn.addEventListener('click', toggleFilters);
    if (closeFiltersBtn) closeFiltersBtn.addEventListener('click', toggleFilters);
    if (filtersBackdrop) filtersBackdrop.addEventListener('click', toggleFilters);
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
