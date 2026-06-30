<?php
$admin_products = [];
$all_categories = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $checkColors = $pdo->query("SHOW COLUMNS FROM products LIKE 'colors'");
        if (!$checkColors->fetch()) $pdo->exec("ALTER TABLE products ADD COLUMN colors VARCHAR(255) DEFAULT NULL");
        $checkSizes = $pdo->query("SHOW COLUMNS FROM products LIKE 'sizes'");
        if (!$checkSizes->fetch()) $pdo->exec("ALTER TABLE products ADD COLUMN sizes VARCHAR(255) DEFAULT NULL");
        $checkDiscount = $pdo->query("SHOW COLUMNS FROM products LIKE 'discount'");
        if (!$checkDiscount->fetch()) $pdo->exec("ALTER TABLE products ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
        $checkGsm = $pdo->query("SHOW COLUMNS FROM products LIKE 'gsm'");
        if (!$checkGsm->fetch()) $pdo->exec("ALTER TABLE products ADD COLUMN gsm VARCHAR(100) DEFAULT NULL");
        $checkWaistband = $pdo->query("SHOW COLUMNS FROM products LIKE 'waistband'");
        if (!$checkWaistband->fetch()) $pdo->exec("ALTER TABLE products ADD COLUMN waistband VARCHAR(150) DEFAULT NULL");

        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $all_categories = $cat_stmt->fetchAll();

        $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc`, p.images, p.colors, p.sizes, p.discount, p.gsm, p.waistband 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.deleted_at IS NULL");
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
                'images' => json_decode($pr['images'] ?? '[]', true) ?: [],
                'colors' => $pr['colors'] ?? '',
                'sizes' => $pr['sizes'] ?? '',
                'discount' => (float)($pr['discount'] ?? 0),
                'gsm' => $pr['gsm'] ?? '',
                'waistband' => $pr['waistband'] ?? '',
                'tiers' => $formatted_tiers
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}

if (empty($admin_products)) {
    $admin_products = [];
}

if (empty($all_categories)) {
    $all_categories = [];
}
?>
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
                    <input type="text" id="prod-search" placeholder="Search SKU or Name..." class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-xs font-medium outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <select id="prod-cat-filter" class="bg-gray-50 border border-transparent rounded-2xl px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest outline-none cursor-pointer focus:bg-white focus:border-brand/20 transition-all">
                    <option value="">Category</option>
                    <option value="all">All Categories</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
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
                    <?php if (empty($admin_products)): ?>
                    <div class="flex flex-col items-center justify-center py-24 text-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-300"><i class="ti ti-shirt text-3xl"></i></div>
                        <p class="text-sm font-bold text-gray-400">No products found</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($admin_products as $idx => $p): ?>
                    <div id="prod-row-<?= $idx ?>" class="prod-card p-4 bg-white border border-transparent rounded-3xl cursor-pointer transition-all hover:bg-gray-50 grid grid-cols-[48px_1fr_80px_80px_100px_80px] gap-4 items-center group"
                         data-idx="<?= $idx ?>"
                         data-id="<?= htmlspecialchars($p['id']) ?>"
                         data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                         data-sku="<?= htmlspecialchars(strtolower($p['sku'])) ?>"
                         data-cat="<?= htmlspecialchars(strtolower($p['cat'])) ?>"
                         data-original-name="<?= htmlspecialchars($p['name']) ?>"
                         data-original-sku="<?= htmlspecialchars($p['sku']) ?>"
                         data-original-cat="<?= htmlspecialchars($p['cat']) ?>"
                         data-desc="<?= htmlspecialchars($p['desc']) ?>"
                         data-images="<?= htmlspecialchars(json_encode($p['images'])) ?>"
                         data-colors="<?= htmlspecialchars($p['colors']) ?>"
                         data-sizes="<?= htmlspecialchars($p['sizes']) ?>"
                         data-discount="<?= htmlspecialchars($p['discount']) ?>"
                         data-gsm="<?= htmlspecialchars($p['gsm']) ?>"
                         data-waistband="<?= htmlspecialchars($p['waistband']) ?>"
                         data-tiers="<?= htmlspecialchars(json_encode($p['tiers'])) ?>"
                         onclick="selectProd(this)">
                        <div class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-gray-300 group-hover:bg-brand-light group-hover:text-brand transition-all overflow-hidden">
                            <?= (!empty($p['images']) && isset($p['images'][0])) ? '<img src="'.htmlspecialchars($p['images'][0]).'" alt="'.htmlspecialchars($p['name']).'" class="w-full h-full object-cover">' : '<i class="ti ti-shirt text-2xl"></i>' ?>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors truncate"><?= htmlspecialchars($p['name']) ?></h4>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5 truncate"><?= htmlspecialchars($p['sku']) ?> · <?= htmlspecialchars($p['cat']) ?></p>
                        </div>
                        <span class="text-xs font-bold text-gray-500"><?= htmlspecialchars($p['moq']) ?></span>
                        <span class="text-xs font-black text-gray-900 truncate">LKR <?= htmlspecialchars($p['price']) ?></span>
                        <span class="px-3 py-1 <?= $p['badge'] ?> text-[9px] font-bold rounded-full border uppercase tracking-tighter text-center truncate"><?= htmlspecialchars($p['status']) ?></span>
                        <div class="flex justify-end gap-2 text-gray-300">
                            <button class="p-2 hover:text-brand transition-colors"><i class="ti ti-edit"></i></button>
                            <button class="p-2 hover:text-red-500 transition-colors" onclick="event.stopPropagation(); selectProd(this.closest('.prod-card')); deleteProduct();"><i class="ti ti-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
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
            <input type="hidden" id="f-id" value="">
            
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
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">GSM</label>
                        <input type="text" id="f-gsm" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Waistband</label>
                        <input type="text" id="f-waistband" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Discount (%)</label>
                    <input type="number" step="0.01" id="f-discount" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm" value="0">
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
                    <div class="flex flex-wrap gap-2" id="size-chips">
                        <button type="button" onclick="toggleSizeChip(this, 'XS')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">XS</button>
                        <button type="button" onclick="toggleSizeChip(this, 'S')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">S</button>
                        <button type="button" onclick="toggleSizeChip(this, 'M')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">M</button>
                        <button type="button" onclick="toggleSizeChip(this, 'L')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">L</button>
                        <button type="button" onclick="toggleSizeChip(this, 'XL')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">XL</button>
                        <button type="button" onclick="toggleSizeChip(this, 'XXL')" class="size-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">XXL</button>
                    </div>
                    <input type="hidden" id="f-sizes" name="sizes" value="">
                </div>
                <div class="space-y-4">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Available Colours</label>
                    <div class="flex flex-wrap gap-2" id="color-chips">
                        <button type="button" onclick="toggleColorChip(this, 'White')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">White</button>
                        <button type="button" onclick="toggleColorChip(this, 'Black')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Black</button>
                        <button type="button" onclick="toggleColorChip(this, 'Grey')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Grey</button>
                        <button type="button" onclick="toggleColorChip(this, 'Blue')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Blue</button>
                        <button type="button" onclick="toggleColorChip(this, 'Red')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Red</button>
                        <button type="button" onclick="toggleColorChip(this, 'Pink')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Pink</button>
                        <button type="button" onclick="toggleColorChip(this, 'Navy')" class="color-chip px-4 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold uppercase transition-all">Navy</button>
                    </div>
                    <input type="hidden" id="f-colors" name="colors" value="">
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
                <h4 class="text-[10px] font-bold text-gray-300 uppercase tracking-[0.2em] border-b border-gray-100 pb-2">Media Assets (Upload up to 6 images, PNG/JPG up to 5MB)</h4>
                
                <div class="grid grid-cols-3 gap-4">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="space-y-2">
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block text-center">
                            <?= $i === 0 ? 'Slot 1 (Primary)' : 'Slot ' . ($i + 1) ?>
                        </span>
                        
                        <input type="file" id="f-image-file-<?= $i ?>" accept="image/*" class="hidden" onchange="previewProductImage(this, <?= $i ?>)">
                        
                        <div onclick="document.getElementById('f-image-file-<?= $i ?>').click();" class="border-2 border-dashed border-gray-200 rounded-2xl p-4 flex flex-col items-center justify-center text-center group hover:border-brand/40 hover:bg-white transition-all cursor-pointer bg-white shadow-sm relative overflow-hidden min-h-[100px] w-full">
                            <div id="upload-placeholder-<?= $i ?>" class="flex flex-col items-center justify-center">
                                <i class="ti ti-cloud-upload text-xl text-gray-300 group-hover:text-brand transition-all mb-1"></i>
                                <p class="text-[9px] font-bold text-gray-900">Upload</p>
                            </div>
                            <img id="form-image-preview-<?= $i ?>" src="" alt="Preview" class="hidden absolute inset-0 w-full h-full object-cover">
                            
                            <!-- Overlay to clear or change image -->
                            <div id="preview-actions-<?= $i ?>" class="hidden absolute inset-0 bg-black/40 flex flex-col items-center justify-center gap-1.5 opacity-0 hover:opacity-100 transition-opacity">
                                <button type="button" onclick="event.stopPropagation(); document.getElementById('f-image-file-<?= $i ?>').click();" class="bg-brand text-white text-[9px] font-bold py-1 px-2.5 rounded-lg hover:bg-brand-dark transition-all">Change</button>
                                <button type="button" onclick="event.stopPropagation(); clearProductImage(<?= $i ?>);" class="bg-red-500 text-white text-[9px] font-bold py-1 px-2.5 rounded-lg hover:bg-red-600 transition-all">Delete</button>
                            </div>
                        </div>
                        
                        <!-- Manual image URL input -->
                        <input type="text" id="f-image-url-<?= $i ?>" oninput="updateProductPreviewFromUrl(this.value, <?= $i ?>)" placeholder="Image URL" class="w-full px-3 py-2 bg-white border border-gray-100 rounded-xl text-[10px] font-bold outline-none focus:ring-1 focus:ring-brand shadow-sm">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex gap-4 pt-10">
                <button onclick="saveProduct()" class="flex-[2] bg-brand text-brand-light font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest shadow-xl shadow-brand/20 hover:bg-brand-dark transition-all transform hover:-translate-y-px">
                    Save Changes
                </button>
                <button id="btn-prod-delete" onclick="deleteProduct()" class="flex-1 bg-white border border-red-100 text-red-500 font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest hover:bg-red-50 transition-all">
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



<script>
let selectedIdx = -1;

function selectProd(el, openDrawer = true) {
    if (el) {
        selectedIdx = parseInt(el.dataset.idx);
        document.querySelectorAll('.prod-card').forEach(c => c.classList.remove('selected', 'shadow-lg'));
        el.classList.add('selected', 'shadow-lg');
    } else {
        return;
    }
    
    const id = parseInt(el.dataset.id || "0");
    document.getElementById('form-mode-label').textContent = 'Edit Product';
    document.getElementById('f-id').value = id;
    document.getElementById('f-name').value = el.dataset.originalName || '';
    document.getElementById('f-sku').value = el.dataset.originalSku || '';
    document.getElementById('f-cat').value = el.dataset.originalCat || '';
    document.getElementById('f-desc').value = el.dataset.desc || '';
    document.getElementById('f-discount').value = el.dataset.discount || '0';
    document.getElementById('f-gsm').value = el.dataset.gsm || '';
    document.getElementById('f-waistband').value = el.dataset.waistband || '';

    // Set existing images across 6 slots
    let prodImages = [];
    try { prodImages = JSON.parse(el.dataset.images || '[]'); } catch (e) {}
    for (let i = 0; i < 6; i++) {
        const imgUrl = prodImages[i] || '';
        document.getElementById('f-image-url-' + i).value = imgUrl;
        document.getElementById('f-image-file-' + i).value = ''; // Reset file input
        updateProductPreviewFromUrl(imgUrl, i);
    }
    // Set colors
    const colors = el.dataset.colors ? el.dataset.colors.split(',').map(c => c.trim()) : [];
    document.getElementById('f-colors').value = el.dataset.colors || '';
    document.querySelectorAll('.color-chip').forEach(btn => {
        if (colors.includes(btn.textContent.trim())) {
            btn.classList.add('bg-brand', 'text-white', 'border-brand');
            btn.classList.remove('bg-white');
        } else {
            btn.classList.remove('bg-brand', 'text-white', 'border-brand');
            btn.classList.add('bg-white');
        }
    });

    // Set sizes
    const sizes = el.dataset.sizes ? el.dataset.sizes.split(',').map(s => s.trim()) : [];
    document.getElementById('f-sizes').value = el.dataset.sizes || '';
    document.querySelectorAll('.size-chip').forEach(btn => {
        if (sizes.includes(btn.textContent.trim())) {
            btn.classList.add('bg-brand', 'text-white', 'border-brand');
            btn.classList.remove('bg-white');
        } else {
            btn.classList.remove('bg-brand', 'text-white', 'border-brand');
            btn.classList.add('bg-white');
        }
    });

    // Set tiers
    if (id > 0) {
        document.getElementById('btn-prod-delete').classList.remove('hidden');
    } else {
        document.getElementById('btn-prod-delete').classList.add('hidden');
    }
    let tiers = [];
    try { tiers = JSON.parse(el.dataset.tiers || '[]'); } catch (e) {}
    renderTiers(tiers);
    
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
    document.querySelectorAll('.prod-card').forEach(c => c.classList.remove('selected', 'shadow-lg'));
    document.getElementById('form-mode-label').textContent = 'Add New Product';
    document.getElementById('f-id').value = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-sku').value = '';
    document.getElementById('f-desc').value = '';
    document.getElementById('f-discount').value = '0';
    document.getElementById('f-gsm').value = '';
    document.getElementById('f-waistband').value = '';
    document.getElementById('tier-rows').innerHTML = '';
    addTier();
    
    // Reset all 6 image slots
    for (let i = 0; i < 6; i++) {
        document.getElementById('f-image-url-' + i).value = '';
        document.getElementById('f-image-file-' + i).value = '';
        updateProductPreviewFromUrl('', i);
    }

    // Reset colors
    document.getElementById('f-colors').value = '';
    document.querySelectorAll('.color-chip').forEach(btn => {
        btn.classList.remove('bg-brand', 'text-white', 'border-brand');
        btn.classList.add('bg-white');
    });
    
    document.getElementById('f-sizes').value = '';
    document.querySelectorAll('.size-chip').forEach(btn => {
        btn.classList.remove('bg-brand', 'text-white', 'border-brand');
        btn.classList.add('bg-white');
    });

    document.getElementById('btn-prod-delete').classList.add('hidden');
    
    // Open drawer
    const pane = document.getElementById('product-form-pane');
    const backdrop = document.getElementById('product-form-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
}

function toggleColorChip(btn, color) {
    btn.classList.toggle('bg-brand');
    btn.classList.toggle('text-white');
    btn.classList.toggle('border-brand');
    btn.classList.toggle('bg-white');
    
    updateHiddenField('color-chips', 'f-colors');
}

function toggleSizeChip(btn, size) {
    btn.classList.toggle('bg-brand');
    btn.classList.toggle('text-white');
    btn.classList.toggle('border-brand');
    btn.classList.toggle('bg-white');
    
    updateHiddenField('size-chips', 'f-sizes');
}

function updateHiddenField(containerId, inputId) {
    const container = document.getElementById(containerId);
    const activeBtns = container.querySelectorAll('button.bg-brand');
    const values = Array.from(activeBtns).map(b => b.textContent.trim());
    document.getElementById(inputId).value = values.join(',');
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
    document.querySelectorAll('.prod-card').forEach(c => c.classList.remove('selected', 'shadow-lg'));
}

function previewProductImage(input, index) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('form-image-preview-' + index);
            const placeholder = document.getElementById('upload-placeholder-' + index);
            const actions = document.getElementById('preview-actions-' + index);
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (actions) actions.classList.remove('hidden');
            document.getElementById('f-image-url-' + index).value = ''; // Reset url
        }
        reader.readAsDataURL(file);
    }
}

function updateProductPreviewFromUrl(url, index) {
    const previewImg = document.getElementById('form-image-preview-' + index);
    const placeholder = document.getElementById('upload-placeholder-' + index);
    const actions = document.getElementById('preview-actions-' + index);
    if (url.trim()) {
        previewImg.src = url;
        previewImg.classList.remove('hidden');
        placeholder.classList.add('hidden');
        if (actions) actions.classList.remove('hidden');
        document.getElementById('f-image-file-' + index).value = ''; // Reset file input
    } else {
        previewImg.src = '';
        previewImg.classList.add('hidden');
        placeholder.classList.remove('hidden');
        if (actions) actions.classList.add('hidden');
    }
}

function clearProductImage(index) {
    document.getElementById('f-image-file-' + index).value = '';
    document.getElementById('f-image-url-' + index).value = '';
    updateProductPreviewFromUrl('', index);
}

function saveProduct() {
    const id = document.getElementById('f-id').value;
    const name = document.getElementById('f-name').value;
    const sku = document.getElementById('f-sku').value;
    const category_name = document.getElementById('f-cat').value;
    const description = document.getElementById('f-desc').value;
    const discount = document.getElementById('f-discount').value;
    const gsm = document.getElementById('f-gsm').value;
    const waistband = document.getElementById('f-waistband').value;

    // Get pricing tiers from inputs
    const tiers = [];
    const tierRows = document.getElementById('tier-rows').children;
    for (let row of tierRows) {
        const inputs = row.getElementsByTagName('input');
        if (inputs.length >= 2) {
            const qty = parseInt(inputs[0].value);
            const price = parseFloat(inputs[1].value);
            if (!isNaN(qty) && !isNaN(price)) {
                tiers.push({ q: qty, p: price });
            }
        }
    }

    if (!name || !sku) {
        showToast("Product Title and SKU Code are required.", "error");
        return;
    }

    const formData = new FormData();
    formData.append('id', id ? parseInt(id) : 0);
    formData.append('name', name);
    formData.append('sku', sku);
    formData.append('category_name', category_name);
    formData.append('description', description);
    formData.append('moq', tiers.length > 0 ? tiers[0].q : 50);
    formData.append('base_price', tiers.length > 0 ? tiers[0].p : 0);
    formData.append('status', 'In Stock');
    formData.append('tiers', JSON.stringify(tiers));
    formData.append('colors', document.getElementById('f-colors').value);
    formData.append('sizes', document.getElementById('f-sizes').value);
    formData.append('discount', discount);
    formData.append('gsm', gsm);
    formData.append('waistband', waistband);

    // Append 6 slots
    for (let i = 0; i < 6; i++) {
        const urlVal = document.getElementById('f-image-url-' + i).value;
        formData.append('image_url_' + i, urlVal);
        
        const fileInput = document.getElementById('f-image-file-' + i);
        if (fileInput.files[0]) {
            formData.append('product_image_file_' + i, fileInput.files[0]);
        }
    }

    fetch('/api/products.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message || 'Product saved successfully.', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Error saving product.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error saving product.', 'error');
    });
}

function deleteProduct() {
    const id = document.getElementById('f-id').value;
    if (!id) return;

    // Show custom modal instead of alert
    const modal = document.getElementById('delete-confirm-modal');
    const content = document.getElementById('delete-confirm-content');
    modal.style.display = 'flex';
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-confirm-modal');
    const content = document.getElementById('delete-confirm-content');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

function confirmDeleteProduct() {
    const id = document.getElementById('f-id').value;
    if (!id) return;

        fetch('/api/products.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: parseInt(id) })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Product deleted successfully.', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error deleting product.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error deleting product.', 'error');
        });
}

// Filters
function applyFilters() {
    const q = (document.getElementById('prod-search')?.value || '').toLowerCase().trim();
    const cat = (document.getElementById('prod-cat-filter')?.value || '').toLowerCase();
    
    document.querySelectorAll('.prod-card').forEach(row => {
        const rName = row.dataset.name || '';
        const rSku = row.dataset.sku || '';
        const rCat = row.dataset.cat || '';
        
        const matchQ = !q || rName.includes(q) || rSku.includes(q);
        const matchCat = !cat || cat === 'all' || rCat === cat;
        
        row.hidden = !(matchQ && matchCat);
    });
}

document.getElementById('prod-search')?.addEventListener('input', applyFilters);
document.getElementById('prod-cat-filter')?.addEventListener('change', applyFilters);

// Initial render logic
const firstRow = document.querySelector('.prod-card');
if (firstRow) {
    selectProd(firstRow, false);
}
closeProductFormPane();
</script>

<!-- Delete Confirmation Modal -->
<div id="delete-confirm-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity duration-200" style="display: none;">
    <div id="delete-confirm-content" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-sm w-full text-center flex flex-col items-center transform scale-95 opacity-0 transition-all duration-200">
        <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-6">
            <i class="ti ti-trash text-3xl"></i>
        </div>
        <h3 class="text-xl font-extrabold text-gray-900 mb-2">Delete Product?</h3>
        <p class="text-sm font-medium text-gray-500 mb-8">Are you sure you want to delete this product? This action cannot be undone.</p>
        <div class="flex gap-3 w-full">
            <button onclick="closeDeleteModal()" class="flex-1 bg-gray-50 text-gray-700 font-bold py-3.5 rounded-2xl hover:bg-gray-100 transition-colors">Cancel</button>
            <button onclick="confirmDeleteProduct()" class="flex-1 bg-red-500 text-white font-bold py-3.5 rounded-2xl hover:bg-red-600 shadow-lg shadow-red-500/20 transition-all transform hover:-translate-y-px">Delete</button>
        </div>
    </div>
</div>
