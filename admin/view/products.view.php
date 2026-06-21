<!-- MAIN CONTENT AREA (SPLIT LAYOUT) -->
<main class="flex-1 flex overflow-hidden">
    
    <!-- LEFT: PRODUCT LIST -->
    <div class="flex-1 flex flex-col bg-white border-r border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-gray-50 flex items-center justify-between gap-4">
            <h1 class="text-xl font-extrabold text-gray-900 tracking-tight uppercase">Products</h1>
            <button onclick="showNew()" class="bg-brand text-brand-light font-bold px-6 py-3 rounded-2xl text-xs uppercase tracking-widest hover:bg-brand-dark transition-all flex items-center gap-2 shadow-lg shadow-brand/20">
                <i class="ti ti-plus"></i> Add Product
            </button>
        </div>

        <!-- Search & Filters -->
        <div class="px-8 py-6 space-y-4 border-b border-gray-50">
            <div class="flex gap-4">
                <div class="relative flex-1 group">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input type="text" placeholder="Search SKU or Name..." class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-xs font-medium outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <select class="bg-gray-50 border border-transparent rounded-2xl px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest outline-none cursor-pointer focus:bg-white focus:border-brand/20 transition-all">
                    <option>Category</option>
                    <option>Briefs</option>
                    <option>Boxers</option>
                </select>
            </div>
        </div>

        <!-- Product Table -->
        <div class="flex-1 overflow-auto px-8 py-4">
            <div class="min-w-[650px]">
                <div class="grid grid-cols-[48px_1fr_80px_80px_100px_80px] gap-4 pb-4 text-[10px] font-bold text-gray-300 uppercase tracking-widest border-b border-gray-50 mb-4 px-2">
                    <span>IMG</span>
                    <span>Product Detail</span>
                    <span>MOQ</span>
                    <span>Base</span>
                    <span>Status</span>
                    <span class="text-right">Actions</span>
                </div>
                
                <div id="prod-list" class="space-y-1 pb-10">
                    <!-- Cards injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: EDIT/ADD FORM -->
    <!-- Backdrop -->
    <div id="product-form-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeProductFormPane()"></div>
    <div id="product-form-pane" class="fixed inset-y-0 right-0 z-50 w-[500px] max-w-full bg-gray-50 border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <!-- Form Header -->
        <div class="p-8 border-b border-gray-100 bg-white flex items-center justify-between">
            <h2 id="form-mode-label" class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Edit Product</h2>
            <button onclick="closeProductFormPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close form">
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>

        <!-- Form Content -->
        <div class="flex-1 overflow-y-auto p-10 space-y-10">
            
            <!-- Main Info -->
            <div class="space-y-6">
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Product Title</label>
                    <input type="text" id="f-name" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-sm font-bold text-gray-900 outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">SKU Code</label>
                        <input type="text" id="f-sku" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Category</label>
                        <select id="f-cat" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm appearance-none">
                            <option>Men's Briefs</option>
                            <option>Men's Boxers</option>
                            <option>Ladies Innerwear</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Product Description</label>
                    <textarea id="f-desc" rows="4" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-medium outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm resize-none"></textarea>
                </div>
            </div>

            <!-- Attributes -->
            <div class="space-y-6">
                <h4 class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] border-b border-gray-100 pb-2">Variations</h4>
                <div class="space-y-4">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Available Sizes</label>
                    <div class="flex flex-wrap gap-2">
                        <button class="chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase hover:bg-brand hover:text-white transition-all">XS</button>
                        <button class="chip chip-active px-4 py-2 bg-brand text-white border border-brand rounded-xl text-[10px] font-bold uppercase transition-all">S</button>
                        <button class="chip chip-active px-4 py-2 bg-brand text-white border border-brand rounded-xl text-[10px] font-bold uppercase transition-all">M</button>
                        <button class="chip chip-active px-4 py-2 bg-brand text-white border border-brand rounded-xl text-[10px] font-bold uppercase transition-all">L</button>
                        <button class="chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase hover:bg-brand hover:text-white transition-all">XL</button>
                    </div>
                </div>
            </div>

            <!-- Pricing Tiers -->
            <div class="space-y-6">
                <h4 class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] border-b border-gray-100 pb-2">Wholesale Tiers</h4>
                <div id="tier-rows" class="space-y-3">
                    <!-- Injected by JS -->
                </div>
                <button onclick="addTier()" class="text-[10px] font-bold text-brand uppercase tracking-widest flex items-center gap-2 hover:underline">
                    <i class="ti ti-plus"></i> Add Price Tier
                </button>
            </div>

            <!-- Media -->
            <div class="space-y-6">
                <h4 class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] border-b border-gray-100 pb-2">Media Assets</h4>
                <div class="border-2 border-dashed border-gray-200 rounded-[2rem] p-10 flex flex-col items-center justify-center text-center group hover:border-brand/40 hover:bg-white transition-all cursor-pointer">
                    <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300 group-hover:bg-brand-light group-hover:text-brand transition-all mb-4">
                        <i class="ti ti-cloud-upload text-3xl"></i>
                    </div>
                    <p class="text-xs font-bold text-gray-900">Upload Product Images</p>
                    <p class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-tighter">PNG, JPG up to 5MB</p>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex gap-4 pt-10">
                <button class="flex-[2] bg-brand text-brand-light font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest shadow-xl shadow-brand/20 hover:bg-brand-dark transition-all transform hover:-translate-y-px">
                    Save Changes
                </button>
                <button class="flex-1 bg-white border border-red-100 text-red-500 font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest hover:bg-red-50 transition-all">
                    Delete
                </button>
            </div>

        </div>
    </div>
</main>

<style>
.prod-card.selected {
    background-color: #E1F5EE;
    border-color: #0F6E56;
    transform: scale(1.02);
}
.chip-active {
    background-color: #0F6E56 !important;
    color: #E1F5EE !important;
    border-color: #0F6E56 !important;
}
</style>

<?php
$admin_products = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc` 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id");
        $prods = $stmt->fetchAll();

        foreach ($prods as $pr) {
            $t_stmt = $pdo->prepare("SELECT min_qty AS q, price AS p FROM pricing_tiers WHERE product_id = ?");
            $t_stmt->execute([$pr['id']]);
            $tiers = $t_stmt->fetchAll();

            // Convert types appropriately for JSON/JS consumption
            $formatted_tiers = [];
            foreach ($tiers as $t) {
                $formatted_tiers[] = [
                    'q' => (int)$t['q'],
                    'p' => (float)$t['p']
                ];
            }

            $status_lower = strtolower($pr['status']);
            if ($status_lower === 'in stock') {
                $badge = 'bg-green-50 text-green-600 border-green-100';
            } elseif ($status_lower === 'low stock') {
                $badge = 'bg-amber-50 text-amber-600 border-amber-100';
            } else {
                $badge = 'bg-red-50 text-red-600 border-red-100';
            }

            $admin_products[] = [
                'id' => (int)$pr['id'],
                'name' => $pr['name'],
                'sku' => $pr['sku'],
                'cat' => $pr['cat'] ?? 'Uncategorized',
                'moq' => (int)$pr['moq'],
                'price' => (float)$pr['price'],
                'status' => $pr['status'],
                'badge' => $badge,
                'desc' => $pr['desc'] ?? '',
                'tiers' => $formatted_tiers
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}

if (empty($admin_products)) {
    $admin_products = [
      [ 'id' => 0, 'name' => 'Classic Cotton Brief', 'sku' => 'KB-001', 'cat' => "Men's Briefs", 'moq' => 50, 'price' => 95, 'status' => 'In Stock', 'badge' => 'bg-green-50 text-green-600 border-green-100', 'desc' => "Classic cut men's brief. Suitable for all-day wear. Ideal for retail bundles.", 'tiers' => [['q' => 50, 'p' => 120], ['q' => 100, 'p' => 108], ['q' => 500, 'p' => 95]] ],
      [ 'id' => 1, 'name' => 'Stretch Boxer', 'sku' => 'KB-008', 'cat' => "Men's Boxers", 'moq' => 100, 'price' => 155, 'status' => 'In Stock', 'badge' => 'bg-green-50 text-green-600 border-green-100', 'desc' => "Premium stretch cotton boxers with reinforced stitching.", 'tiers' => [['q' => 100, 'p' => 185], ['q' => 500, 'p' => 155]] ],
      [ 'id' => 2, 'name' => 'Ladies Hipster', 'sku' => 'KL-003', 'cat' => "Ladies", 'moq' => 50, 'price' => 115, 'status' => 'Low Stock', 'badge' => 'bg-amber-50 text-amber-600 border-amber-100', 'desc' => "Soft touch hipster briefs for maximum comfort.", 'tiers' => [['q' => 50, 'p' => 135], ['q' => 200, 'p' => 115]] ]
    ];
}
?>

<script>
const products = <?php echo json_encode($admin_products); ?>;

let selectedIdx = 0;

function renderProdList() {
    const list = document.getElementById('prod-list');
    list.innerHTML = products.map((p, i) => `
        <div onclick="selectProd(this, ${i})" class="prod-card p-4 bg-white border border-transparent rounded-3xl cursor-pointer transition-all hover:bg-gray-50 grid grid-cols-[48px_1fr_80px_80px_100px_80px] gap-4 items-center group ${i === selectedIdx ? 'selected shadow-lg' : ''}">
            <div class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-gray-300 group-hover:bg-brand-light group-hover:text-brand transition-all">
                <i class="ti ti-shirt text-2xl"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors truncate">${p.name}</h4>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">${p.sku} · ${p.cat}</p>
            </div>
            <span class="text-xs font-bold text-gray-500">${p.moq}</span>
            <span class="text-xs font-black text-gray-900">LKR ${p.price}</span>
            <span class="px-3 py-1 ${p.badge} text-[9px] font-bold rounded-full border uppercase tracking-tighter text-center">${p.status}</span>
            <div class="flex justify-end gap-2 text-gray-300">
                <button class="p-2 hover:text-brand transition-colors"><i class="ti ti-edit"></i></button>
                <button class="p-2 hover:text-red-500 transition-colors"><i class="ti ti-trash"></i></button>
            </div>
        </div>
    `).join('');
}

function selectProd(el, idx, openDrawer = true) {
    selectedIdx = idx;
    renderProdList();
    const p = products[idx];
    
    document.getElementById('form-mode-label').textContent = 'Edit Product';
    document.getElementById('f-name').value = p.name;
    document.getElementById('f-sku').value = p.sku;
    document.getElementById('f-cat').value = p.cat;
    document.getElementById('f-desc').value = p.desc;

    // Tiers
    renderTiers(p.tiers);
    
    // Open drawer
    if (openDrawer) {
        const pane = document.getElementById('product-form-pane');
        const backdrop = document.getElementById('product-form-backdrop');
        if (pane) pane.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
        }
    }
}

function renderTiers(tiers) {
    const cont = document.getElementById('tier-rows');
    cont.innerHTML = tiers.map((t, i) => `
        <div class="grid grid-cols-[1fr_1fr_40px] gap-3">
            <input type="number" value="${t.q}" placeholder="Min Qty" class="px-4 py-3 bg-white border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand shadow-sm">
            <input type="number" value="${t.p}" placeholder="Price" class="px-4 py-3 bg-white border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand shadow-sm">
            <button onclick="this.parentElement.remove()" class="text-gray-300 hover:text-red-500 transition-colors text-lg flex items-center justify-center">
                <i class="ti ti-x"></i>
            </button>
        </div>
    `).join('');
}

function addTier() {
    const div = document.createElement('div');
    div.className = 'grid grid-cols-[1fr_1fr_40px] gap-3';
    div.innerHTML = `
        <input type="number" placeholder="Min Qty" class="px-4 py-3 bg-white border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand shadow-sm">
        <input type="number" placeholder="Price" class="px-4 py-3 bg-white border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand shadow-sm">
        <button onclick="this.parentElement.remove()" class="text-gray-300 hover:text-red-500 transition-colors text-lg flex items-center justify-center">
            <i class="ti ti-x"></i>
        </button>
    `;
    document.getElementById('tier-rows').appendChild(div);
}

function showNew() {
    selectedIdx = -1;
    renderProdList();
    document.getElementById('form-mode-label').textContent = 'Add New Product';
    document.getElementById('f-name').value = '';
    document.getElementById('f-sku').value = '';
    document.getElementById('f-desc').value = '';
    document.getElementById('tier-rows').innerHTML = '';
    addTier();
    
    // Open drawer
    const pane = document.getElementById('product-form-pane');
    const backdrop = document.getElementById('product-form-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
}

function closeProductFormPane() {
    const pane = document.getElementById('product-form-pane');
    const backdrop = document.getElementById('product-form-backdrop');
    if (pane) pane.classList.add('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('hidden');
    }
    selectedIdx = -1;
    renderProdList();
}

// Initial render
renderProdList();
selectProd(null, 0, false);
closeProductFormPane();
</script>
