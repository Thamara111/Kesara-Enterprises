<?php
/**
 * Admin Categories View
 */

$error_msg = '';

// Self-healing: Check and add 'image' column to categories table if it does not exist
if (isset($pdo) && $pdo !== null) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        }
    } catch (\Exception $e) {
        // Safely ignore
    }
}

// POST actions are handled externally via /api/categories.php REST endpoint.

// Fetch categories
$categories_data = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT c.*, COUNT(p.id) AS product_count 
                             FROM categories c 
                             LEFT JOIN products p ON p.category_id = c.id 
                             GROUP BY c.id");
        $categories_data = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}

if (empty($categories_data)) {
    $categories_data = [
        ['id' => 1, 'name' => "Men's Briefs", 'slug' => 'mens-briefs', 'icon' => 'ti-shirt', 'description' => 'Comfortable cotton briefs for men.', 'image' => '', 'product_count' => 1],
        ['id' => 2, 'name' => "Men's Boxers", 'slug' => 'mens-boxers', 'icon' => 'ti-shirt', 'description' => 'Premium stretch boxers.', 'image' => '', 'product_count' => 1],
        ['id' => 3, 'name' => 'Ladies Innerwear', 'slug' => 'ladies-innerwear', 'icon' => 'ti-shirt', 'description' => 'Soft touch and premium hipster wear.', 'image' => '', 'product_count' => 1]
    ];
}
?>

<!-- MAIN CONTENT AREA (SPLIT LAYOUT) -->
<main class="flex-1 flex overflow-hidden">
    
    <!-- LEFT: CATEGORIES LIST -->
    <div class="flex-1 flex flex-col bg-white border-r border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-gray-50 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-gray-900 tracking-tight uppercase">Categories</h1>
                <p class="text-xs font-semibold text-gray-400 mt-1">Manage product classification and catalog sections.</p>
            </div>
            <button onclick="showNew()" class="bg-brand text-brand-light font-bold px-6 py-3 rounded-2xl text-xs uppercase tracking-widest hover:bg-brand-dark transition-all flex items-center gap-2 shadow-lg shadow-brand/20">
                <i class="ti ti-plus"></i> Add Category
            </button>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="mx-8 mt-6 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-bold text-red-600">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Categories Table -->
        <div class="flex-1 overflow-auto px-8 py-6">
            <div class="min-w-[600px] bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <th class="p-6 w-[100px]">Preview</th>
                            <th class="p-6">Category Detail</th>
                            <th class="p-6 w-[150px]">Slug</th>
                            <th class="p-6 w-[150px]">Product Count</th>
                            <th class="p-6 w-[100px] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cat-table-body" class="divide-y divide-gray-50">
                        <!-- Table rows rendered dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: EDIT/ADD FORM -->
    <!-- Backdrop -->
    <div id="cat-form-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeCatFormPane()"></div>
    <div id="cat-form-pane" class="fixed inset-y-0 right-0 z-50 w-[500px] max-w-full bg-gray-50 border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <!-- Form Header -->
        <div class="p-8 border-b border-gray-100 bg-white flex items-center justify-between">
            <h2 id="form-mode-label" class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Edit Category</h2>
            <button onclick="closeCatFormPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close form">
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>

        <!-- Form Content -->
        <form method="POST" id="cat-form" enctype="multipart/form-data" class="flex-1 flex flex-col justify-between p-10 space-y-10">
            <input type="hidden" name="action" id="f-action" value="save">
            <input type="hidden" name="id" id="f-id" value="">

            <div class="space-y-6">
                <!-- Name -->
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Category Name</label>
                    <input type="text" name="name" id="f-name" required class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-sm font-bold text-gray-900 outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                </div>

                <!-- Slug -->
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Slug (URL identifier)</label>
                    <input type="text" name="slug" id="f-slug" placeholder="e.g. mens-briefs (Auto-generated if empty)" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                </div>

                <!-- Image Upload (Styled Media Assets Card) -->
                <div class="space-y-4">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Category Image</label>
                    <input type="file" name="category_image_file" id="f-image-file" accept="image/*" class="hidden" onchange="previewSelectedImage(this)">
                    <div onclick="document.getElementById('f-image-file').click();" class="border-2 border-dashed border-gray-200 rounded-[2rem] p-8 flex flex-col items-center justify-center text-center group hover:border-brand/40 hover:bg-white transition-all cursor-pointer bg-white shadow-sm relative overflow-hidden min-h-[160px]">
                        <div id="upload-placeholder" class="flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-300 group-hover:bg-brand-light group-hover:text-brand transition-all mb-3">
                                <i class="ti ti-cloud-upload text-2xl"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-900">Upload Category Image</p>
                            <p class="text-[9px] font-medium text-gray-400 mt-0.5 uppercase tracking-tighter">PNG, JPG, WEBP up to 5MB</p>
                        </div>
                        <img id="form-image-preview" src="" alt="Preview" class="hidden absolute inset-0 w-full h-full object-cover">
                        <!-- Change image label on hover -->
                        <div id="preview-hover-overlay" class="hidden absolute inset-0 bg-black/40 flex items-center justify-center text-white text-xs font-bold opacity-0 hover:opacity-100 transition-opacity">
                            Change Image
                        </div>
                    </div>
                    <!-- Manual image URL input underneath -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Or Image URL</label>
                        <input type="text" name="image" id="f-image" oninput="updatePreviewFromUrl(this.value)" placeholder="e.g. /assets/images/category-briefs.jpg" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                    </div>
                </div>

                <!-- Icon -->
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Icon Class (Fallback if no image)</label>
                    <select name="icon" id="f-icon" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm">
                        <option value="ti-shirt">Shirt (ti-shirt)</option>
                        <option value="ti-tag">Tag (ti-tag)</option>
                        <option value="ti-package">Package (ti-package)</option>
                        <option value="ti-folder">Folder (ti-folder)</option>
                        <option value="ti-list">List (ti-list)</option>
                    </select>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Description</label>
                    <textarea name="description" id="f-desc" rows="4" class="w-full px-5 py-4 bg-white border border-gray-100 rounded-2xl text-xs font-medium outline-none focus:ring-1 focus:ring-brand transition-all shadow-sm resize-none"></textarea>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex gap-4 pt-10">
                <button type="submit" class="flex-[2] bg-brand text-brand-light font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest shadow-xl shadow-brand/20 hover:bg-brand-dark transition-all transform hover:-translate-y-px">
                    Save Changes
                </button>
                <button type="button" id="btn-delete" onclick="submitDelete()" class="hidden flex-1 bg-white border border-red-100 text-red-500 font-bold py-5 rounded-[1.5rem] text-xs uppercase tracking-widest hover:bg-red-50 transition-all">
                    Delete
                </button>
            </div>
        </form>
    </div>
</main>

<style>
.cat-row.selected {
    background-color: #E1F5EE !important;
}
</style>

<script>
const categories = <?php echo json_encode($categories_data); ?>;
let selectedIdx = -1;

function renderCatList() {
    const tbody = document.getElementById('cat-table-body');
    tbody.innerHTML = categories.map((c, i) => `
        <tr onclick="selectCat(this, ${i})" class="cat-row cursor-pointer transition-colors hover:bg-gray-50/50 ${i === selectedIdx ? 'selected' : ''}">
            <td class="p-6">
                <div class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-gray-400 transition-all overflow-hidden">
                    ${c.image ? `<img src="${c.image}" alt="${c.name}" class="w-full h-full object-cover">` : `<i class="ti ${c.icon || 'ti-tag'} text-xl"></i>`}
                </div>
            </td>
            <td class="p-6">
                <h4 class="text-sm font-bold text-gray-900 truncate">${c.name}</h4>
                <p class="text-[10px] font-medium text-gray-400 truncate mt-0.5">${c.description || 'No description'}</p>
            </td>
            <td class="p-6 text-xs font-bold text-gray-500">${c.slug}</td>
            <td class="p-6 text-xs font-bold text-gray-950">${c.product_count || 0} Products</td>
            <td class="p-6 text-right">
                <div class="flex justify-end gap-2 text-gray-300">
                    <button class="p-2 hover:text-brand transition-colors" aria-label="Edit"><i class="ti ti-edit text-base"></i></button>
                    <button onclick="event.stopPropagation(); selectCat(this.parentElement.parentElement.parentElement, ${i}); submitDelete();" class="p-2 hover:text-red-500 transition-colors" aria-label="Delete"><i class="ti ti-trash text-base"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

function selectCat(el, idx, openDrawer = true) {
    selectedIdx = idx;
    renderCatList();
    const c = categories[idx];
    
    document.getElementById('form-mode-label').textContent = 'Edit Category';
    document.getElementById('f-id').value = c.id;
    document.getElementById('f-name').value = c.name;
    document.getElementById('f-slug').value = c.slug;
    document.getElementById('f-image').value = c.image || '';
    document.getElementById('f-image-file').value = ''; // Reset file input
    updatePreviewFromUrl(c.image || '');
    document.getElementById('f-icon').value = c.icon || 'ti-tag';
    document.getElementById('f-desc').value = c.description || '';
    
    // Show delete button for existing categories
    document.getElementById('btn-delete').classList.remove('hidden');

    if (openDrawer) {
        const pane = document.getElementById('cat-form-pane');
        const backdrop = document.getElementById('cat-form-backdrop');
        if (pane) pane.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
        }
    }
}

function showNew() {
    selectedIdx = -1;
    renderCatList();
    document.getElementById('form-mode-label').textContent = 'Add New Category';
    document.getElementById('f-id').value = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-slug').value = '';
    document.getElementById('f-image').value = '';
    document.getElementById('f-image-file').value = '';
    updatePreviewFromUrl('');
    document.getElementById('f-icon').value = 'ti-tag';
    document.getElementById('f-desc').value = '';
    
    // Hide delete button for new category
    document.getElementById('btn-delete').classList.add('hidden');
    
    const pane = document.getElementById('cat-form-pane');
    const backdrop = document.getElementById('cat-form-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
}

function previewSelectedImage(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('form-image-preview');
            const placeholder = document.getElementById('upload-placeholder');
            const overlay = document.getElementById('preview-hover-overlay');
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (overlay) overlay.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}

function updatePreviewFromUrl(url) {
    const previewImg = document.getElementById('form-image-preview');
    const placeholder = document.getElementById('upload-placeholder');
    const overlay = document.getElementById('preview-hover-overlay');
    if (url.trim()) {
        previewImg.src = url;
        previewImg.classList.remove('hidden');
        placeholder.classList.add('hidden');
        if (overlay) overlay.classList.remove('hidden');
    } else {
        previewImg.src = '';
        previewImg.classList.add('hidden');
        placeholder.classList.remove('hidden');
        if (overlay) overlay.classList.add('hidden');
    }
}

// Controls
function closeCatFormPane() {
    const pane = document.getElementById('cat-form-pane');
    const backdrop = document.getElementById('cat-form-backdrop');
    if (pane) pane.classList.add('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('hidden');
    }
    selectedIdx = -1;
    renderCatList();
}

// Intercept form submissions and route through our REST API
document.getElementById('cat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const action = document.getElementById('f-action').value;
    const url = '/api/categories.php' + (action === 'delete' ? '?action=delete' : '');

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message || 'Category saved successfully.', 'success');
            setTimeout(() => {
                window.location.href = '/admin-categories';
            }, 1000);
        } else {
            showToast(data.message || 'An error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error occurred.', 'error');
    });
});

function submitDelete() {
    if (confirm("Are you sure you want to delete this category?")) {
        document.getElementById('f-action').value = 'delete';
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', document.getElementById('f-id').value);

        fetch('/api/categories.php?action=delete', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message || 'Category deleted successfully.', 'success');
                setTimeout(() => {
                    window.location.href = '/admin-categories';
                }, 1000);
            } else {
                showToast(data.message || 'An error occurred.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error occurred.', 'error');
        });
    }
}

// Initial render
renderCatList();
</script>
