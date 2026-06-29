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
                    <option value="">Category</option>
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

<?php
$admin_products = [];
$all_categories = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $all_categories = $cat_stmt->fetchAll();

        $stmt = $pdo->query("SELECT p.id, p.name, p.sku, c.name AS cat, p.moq, p.base_price AS price, p.status, p.description AS `desc`, p.images, p.colors 
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
                'images' => json_decode($pr['images'] ?? '[]', true) ?: [],
                'colors' => $pr['colors'] ?? '',
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

<script>
const products = <?php echo json_encode($admin_products); ?>;

let selectedIdx = 0;

function renderProdList() {
    const list = document.getElementById('prod-list');
    list.innerHTML = products.map((p, i) => `
        <div onclick="selectProd(this, ${i})" class="prod-card p-4 bg-white border border-transparent rounded-3xl cursor-pointer transition-all hover:bg-gray-50 grid grid-cols-[48px_1fr_80px_80px_100px_80px] gap-4 items-center group ${i === selectedIdx ? 'selected shadow-lg' : ''}">
            <div class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-gray-300 group-hover:bg-brand-light group-hover:text-brand transition-all overflow-hidden">
                ${p.images && p.images[0] ? `<img src="${p.images[0]}" alt="${p.name}" class="w-full h-full object-cover">` : `<i class="ti ti-shirt text-2xl"></i>`}
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
    document.getElementById('f-id').value = p.id;
    document.getElementById('f-name').value = p.name;
    document.getElementById('f-sku').value = p.sku;
    document.getElementById('f-cat').value = p.cat;
    document.getElementById('f-desc').value = p.desc;

    // Set existing images across 6 slots
    const prodImages = p.images || [];
    for (let i = 0; i < 6; i++) {
        const imgUrl = prodImages[i] || '';
        document.getElementById('f-image-url-' + i).value = imgUrl;
        document.getElementById('f-image-file-' + i).value = ''; // Reset file input
        updateProductPreviewFromUrl(imgUrl, i);
    }

    // Set colors
    const colorsStr = p.colors || '';
    document.getElementById('f-colors').value = colorsStr;
    const colorsArray = colorsStr.split(',').map(c => c.trim().toLowerCase());
    document.querySelectorAll('.color-chip').forEach(btn => {
        if (colorsArray.includes(btn.textContent.trim().toLowerCase())) {
            btn.classList.add('chip-active');
        } else {
            btn.classList.remove('chip-active');
        }
    });

    // Show delete button for existing products, hide if id is 0/temp
    if (p.id > 0) {
        document.getElementById('btn-prod-delete').classList.remove('hidden');
    } else {
        document.getElementById('btn-prod-delete').classList.add('hidden');
    }

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
    document.getElementById('f-id').value = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-sku').value = '';
    document.getElementById('f-desc').value = '';
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
        btn.classList.remove('chip-active');
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
    btn.classList.toggle('chip-active');
    updateColorsValue();
}

function updateColorsValue() {
    const activeColors = [];
    document.querySelectorAll('.color-chip.chip-active').forEach(btn => {
        activeColors.push(btn.textContent.trim());
    });
    document.getElementById('f-colors').value = activeColors.join(', ');
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

    if (confirm("Are you sure you want to delete this product?")) {
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
}

// Initial render
renderProdList();
selectProd(null, 0, false);
closeProductFormPane();
</script>
