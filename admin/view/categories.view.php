<?php
/**
 * Admin Categories View
 * Natural Language Overview:
 * 1. Fetching Data -> Getting product categories and calculating product counts per category from the database.
 * 2. Self-Healing -> Ensuring the 'image' column exists in the categories table.
 * 3. Processing -> Handling REST API calls for adding, editing, and soft-deleting categories via side-panel form.
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

// Fetching -> Getting categories list along with matching active product counts
$categories_data = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT c.*, COUNT(p.id) AS product_count 
                             FROM categories c 
                             LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL
                             WHERE c.deleted_at IS NULL
                             GROUP BY c.id");
        $categories_data = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}

if (empty($categories_data)) {
    $categories_data = [];
}
?>

<!-- MAIN CONTENT AREA (SPLIT LAYOUT) -->
<main class="flex-1 flex overflow-hidden">
    
    <!-- LEFT: CATEGORIES LIST -->
    <div class="flex-1 flex flex-col bg-white border-r border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Categories</h1>
                <p class="text-sm text-gray-500 mt-1">Manage product classification and catalog sections.</p>
            </div>
            
            <button onclick="showNew()"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand text-white text-xs font-bold hover:bg-brand-dark transition-all shadow-sm">
                <i class="ti ti-plus text-lg"></i> Add Category
            </button>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="mx-8 mt-6 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-bold text-red-600">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Categories Table -->
        <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar pb-10">
            <div class="min-w-[600px] p-6 space-y-1">
                <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                            <th class="px-4 py-3 rounded-l-xl w-24">Preview</th>
                            <th class="px-4 py-3">Category Detail</th>
                            <th class="px-4 py-3 w-32">Slug</th>
                            <th class="px-4 py-3 w-32">Product Count</th>
                            <th class="px-4 py-3 text-right rounded-r-xl w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cat-table-body">
                        <?php foreach ($categories_data as $idx => $c): ?>
                        <tr class="cat-row bg-white cursor-pointer hover:bg-gray-50/50 transition-all group shadow-sm"
                            data-idx="<?= $idx ?>"
                            data-id="<?= htmlspecialchars($c['id']) ?>"
                            data-name="<?= htmlspecialchars($c['name']) ?>"
                            data-slug="<?= htmlspecialchars($c['slug']) ?>"
                            data-image="<?= htmlspecialchars($c['image']) ?>"
                            data-icon="<?= htmlspecialchars($c['icon'] ?? 'ti-tag') ?>"
                            data-description="<?= htmlspecialchars($c['description']) ?>"
                            onclick="selectCat(this)">
                            <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 w-24">
                                <div class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-gray-400 transition-all overflow-hidden">
                                    <?php if (!empty($c['image'])): ?>
                                        <img src="<?= htmlspecialchars($c['image']) ?>" alt="<?= htmlspecialchars($c['name']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="ti <?= htmlspecialchars($c['icon'] ?? 'ti-tag') ?> text-xl"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                <h4 class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($c['name']) ?></h4>
                                <p class="text-[10px] font-medium text-gray-400 truncate mt-0.5"><?= htmlspecialchars($c['description'] ?: 'No description') ?></p>
                            </td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-bold text-gray-500"><?= htmlspecialchars($c['slug']) ?></td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-bold text-gray-950"><?= htmlspecialchars($c['product_count'] ?? 0) ?> Products</td>
                            <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-right">
                                <div class="flex justify-end gap-2 text-gray-300">
                                    <button class="p-2 hover:text-brand transition-colors" aria-label="Edit"><i class="ti ti-edit text-lg"></i></button>
                                    <button onclick="event.stopPropagation(); selectCat(this.closest('tr')); submitDelete();" class="p-2 hover:text-red-500 transition-colors" aria-label="Delete"><i class="ti ti-trash text-lg"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories_data)): ?>
                            <tr><td colspan="5" class="p-6 text-center text-gray-400 text-xs">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between bg-white" id="pagination-controls">
                <p class="text-xs text-gray-500 font-medium" id="pagination-info">Showing 0 to 0 of 0 entries</p>
                <div class="flex items-center gap-2" id="pagination-buttons">
                    <!-- Buttons injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: EDIT/ADD FORM -->
    <!-- Backdrop -->
    <div id="cat-form-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeCatFormPane()"></div>

    <div id="cat-form-pane" class="fixed inset-y-0 right-0 z-50 w-[520px] max-w-full bg-[#f5f6fa] border-l border-gray-200 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300">

        <!-- ── Form Header ── -->
        <div class="shrink-0 px-8 py-5 bg-white border-b border-gray-100 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-brand/10 flex items-center justify-center">
                    <i class="ti ti-folder text-brand text-base"></i>
                </div>
                <div>
                    <p id="form-mode-label" class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] leading-none">Edit Category</p>
                    <p class="text-xs font-bold text-gray-900 mt-0.5" id="form-cat-name-preview">—</p>
                </div>
            </div>
            <button onclick="closeCatFormPane()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all" aria-label="Close form">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- ── Scrollable Form Body ── -->
        <form method="POST" id="cat-form" enctype="multipart/form-data" class="flex-1 flex flex-col overflow-hidden">
            <input type="hidden" name="action" id="f-action" value="save">
            <input type="hidden" name="id" id="f-id" value="">

            <div class="flex-1 overflow-y-auto">
                <div class="p-6 space-y-4 bg-white">

                    <!-- ┌─ SECTION 1: Identity & URL ───────────────────────────┐ -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-brand/5 to-transparent">
                            <i class="ti ti-id-badge text-brand text-lg"></i>
                            <h3 class="text-[10px] font-black text-brand uppercase tracking-[0.2em]">Identity &amp; URL</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Category Name -->
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Category Name <span class="text-red-400">*</span></label>
                                <input type="text" name="name" id="f-name" required
                                    oninput="document.getElementById('form-cat-name-preview').textContent = this.value || '—'; autoSlug(this.value);"
                                    placeholder="e.g. Men's Briefs"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-900 outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand/40 focus:bg-white transition-all">
                            </div>
                            <!-- Slug -->
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">
                                    URL Slug <span class="normal-case font-normal text-gray-400">(auto-generated if empty)</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-300">/</span>
                                    <input type="text" name="slug" id="f-slug"
                                        placeholder="e.g. mens-briefs"
                                        class="w-full pl-7 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold text-gray-700 outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand/40 focus:bg-white transition-all">
                                </div>
                                <p class="text-[9px] text-gray-400 ml-1">Lowercase letters, numbers, and hyphens only.</p>
                            </div>
                        </div>
                    </div>
                    <!-- └──────────────────────────────────────────────────────┘ -->

                    <!-- ┌─ SECTION 2: Visual Identity ──────────────────────────┐ -->
                    <div class="bg-white rounded-2xl border border-purple-100 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-6 py-4 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-transparent">
                            <i class="ti ti-photo text-purple-600 text-lg"></i>
                            <div>
                                <h3 class="text-[10px] font-black text-purple-700 uppercase tracking-[0.2em]">Visual Identity</h3>
                                <p class="text-[9px] text-purple-500 font-semibold mt-0.5">Image shown in catalog — icon used as fallback</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-5">

                            <!-- Image Upload -->
                            <div class="space-y-3">
                                <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Category Image</label>
                                <input type="file" name="category_image_file" id="f-image-file" accept="image/*" class="hidden" onchange="previewSelectedImage(this)">
                                <div onclick="document.getElementById('f-image-file').click();"
                                    class="border-2 border-dashed border-gray-200 rounded-2xl p-8 flex flex-col items-center justify-center text-center group hover:border-purple-300 hover:bg-purple-50/30 transition-all cursor-pointer bg-gray-50 relative overflow-hidden min-h-[140px]">
                                    <div id="upload-placeholder" class="flex flex-col items-center justify-center">
                                        <div class="w-12 h-12 rounded-xl bg-white border border-gray-100 flex items-center justify-center text-gray-300 group-hover:border-purple-200 group-hover:text-purple-400 transition-all mb-3 shadow-sm">
                                            <i class="ti ti-cloud-upload text-2xl"></i>
                                        </div>
                                        <p class="text-xs font-bold text-gray-700">Click to upload image</p>
                                        <p class="text-[9px] font-medium text-gray-400 mt-0.5 uppercase tracking-tighter">PNG · JPG · WEBP — max 5 MB</p>
                                    </div>
                                    <img id="form-image-preview" src="" alt="Preview" class="hidden absolute inset-0 w-full h-full object-cover">
                                    <div id="preview-hover-overlay" class="hidden absolute inset-0 bg-black/40 flex items-center justify-center text-white text-xs font-bold opacity-0 hover:opacity-100 transition-opacity">
                                        Change Image
                                    </div>
                                </div>
                                <!-- URL input -->
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Or paste image URL</label>
                                    <input type="text" name="image" id="f-image"
                                        oninput="updatePreviewFromUrl(this.value)"
                                        placeholder="e.g. /assets/images/category.jpg"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-purple-300 focus:border-purple-400 focus:bg-white transition-all">
                                </div>
                            </div>

                            <!-- Icon Fallback -->
                            <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-4 space-y-3">
                                <p class="text-[9px] font-bold text-purple-600 uppercase tracking-widest flex items-center gap-1.5">
                                    <i class="ti ti-wand text-xs"></i> Icon Fallback (shown when no image is set)
                                </p>
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Icon Class</label>
                                    <div class="flex items-center gap-3">
                                        <div id="icon-preview" class="w-10 h-10 rounded-xl bg-white border border-purple-100 flex items-center justify-center text-gray-500 shrink-0 shadow-sm">
                                            <i id="icon-preview-i" class="ti ti-shirt text-xl"></i>
                                        </div>
                                        <select name="icon" id="f-icon"
                                            onchange="document.getElementById('icon-preview-i').className = 'ti ' + this.value + ' text-xl';"
                                            class="flex-1 px-4 py-3 bg-white border border-gray-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-purple-300 focus:border-purple-400 transition-all appearance-none">
                                            <option value="ti-shirt">👕  Shirt (ti-shirt)</option>
                                            <option value="ti-tag">🏷  Tag (ti-tag)</option>
                                            <option value="ti-package">📦  Package (ti-package)</option>
                                            <option value="ti-folder">📁  Folder (ti-folder)</option>
                                            <option value="ti-list">📋  List (ti-list)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- └──────────────────────────────────────────────────────┘ -->

                    <!-- ┌─ SECTION 3: Details ──────────────────────────────────┐ -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-500/5 to-transparent">
                            <i class="ti ti-align-left text-gray-500 text-lg"></i>
                            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em]">Details</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest ml-1">Description <span class="normal-case font-normal">(optional)</span></label>
                                <textarea name="description" id="f-desc" rows="4"
                                    placeholder="Briefly describe what products belong to this category..."
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-medium outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand/40 focus:bg-white transition-all resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- └──────────────────────────────────────────────────────┘ -->

                </div><!-- /p-6 space-y-4 -->
            </div><!-- /scrollable body -->

            <!-- ── Sticky Footer Actions ── -->
            <div class="shrink-0 px-6 py-4 bg-white border-t border-gray-100 shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-[2] bg-brand text-white font-bold py-4 rounded-2xl text-xs uppercase tracking-widest shadow-lg shadow-brand/20 hover:bg-brand-dark transition-all hover:-translate-y-px flex items-center justify-center gap-2">
                        <i class="ti ti-device-floppy text-base"></i>
                        <span>Save Category</span>
                    </button>
                    <button type="button" id="btn-delete" onclick="submitDelete()"
                        class="hidden flex-1 bg-white border border-red-200 text-red-500 font-bold py-4 rounded-2xl text-xs uppercase tracking-widest hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                        <i class="ti ti-trash text-base"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>

        </form>
    </div>
</main>


<style>
.cat-row.selected {
    background-color: #f9fafb !important;
}
</style>

<script>
var currentPage = 1;
var itemsPerPage = 15;

// Pagination -> Changing current active page number
function goToPage(page) {
    currentPage = page;
    applyFilters();
}

// Pagination -> Rendering page navigation buttons at the bottom
function renderPagination(totalItems, totalPages) {
    var info = document.getElementById('pagination-info');
    var buttons = document.getElementById('pagination-buttons');
    if (!info || !buttons) return;

    if (totalItems === 0) {
        info.textContent = 'Showing 0 entries';
        buttons.innerHTML = '';
        return;
    }

    var start = (currentPage - 1) * itemsPerPage + 1;
    var end = Math.min(currentPage * itemsPerPage, totalItems);
    info.textContent = `Showing ${start} to ${end} of ${totalItems} entries`;

    var html = '';
    
    // Prev button
    var prevDisabled = currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === 1 ? '' : 'goToPage(' + (currentPage - 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${prevDisabled}"><i class="ti ti-chevron-left"></i></button>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += `<button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-brand-light font-bold text-xs shadow-md shadow-brand/20">${i}</button>`;
        } else if (
            i === 1 || 
            i === totalPages || 
            (i >= currentPage - 1 && i <= currentPage + 1)
        ) {
            html += `<button onclick="goToPage(${i})" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 font-bold text-xs transition-all">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">...</span>`;
        }
    }

    // Next button
    var nextDisabled = currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === totalPages ? '' : 'goToPage(' + (currentPage + 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${nextDisabled}"><i class="ti ti-chevron-right"></i></button>`;

    buttons.innerHTML = html;
}

// Filtering -> Sorting categories newest first and displaying rows for current page
function applyFilters() {
    var list = document.getElementById('cat-table-body');
    if (!list) return;
    var rows = Array.from(list.querySelectorAll('.cat-row'));

    // Sort latest first (highest id)
    rows.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));

    var totalItems = rows.length;
    var totalPages = Math.ceil(totalItems / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    var start = (currentPage - 1) * itemsPerPage;
    var end = start + itemsPerPage;

    rows.forEach((r, index) => {
        if (index >= start && index < end) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    rows.forEach(r => list.appendChild(r));
    renderPagination(totalItems, totalPages);
}

document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});

// Selection -> Clicking a category row to fill the side form panel inputs
function selectCat(el, openDrawer = true) {
    if (!el) return;
    document.querySelectorAll('.cat-row').forEach(r => {
        r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
        r.classList.add('bg-white', 'border-gray-100');
    });
    el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    el.classList.remove('bg-white', 'border-gray-100');
    
    document.getElementById('form-mode-label').textContent = 'Edit Category';
    document.getElementById('form-cat-name-preview').textContent = el.dataset.name || '—';
    document.getElementById('f-id').value = el.dataset.id;
    document.getElementById('f-name').value = el.dataset.name;
    document.getElementById('f-slug').value = el.dataset.slug;
    document.getElementById('f-image').value = el.dataset.image || '';
    document.getElementById('f-image-file').value = ''; // Reset file input
    updatePreviewFromUrl(el.dataset.image || '');
    var iconVal = el.dataset.icon || 'ti-tag';
    document.getElementById('f-icon').value = iconVal;
    var iconPrev = document.getElementById('icon-preview-i');
    if (iconPrev) iconPrev.className = 'ti ' + iconVal + ' text-xl';
    document.getElementById('f-desc').value = el.dataset.description || '';
    
    // Show delete button for existing categories
    document.getElementById('btn-delete').classList.remove('hidden');

    if (openDrawer) {
        var pane = document.getElementById('cat-form-pane');
        var backdrop = document.getElementById('cat-form-backdrop');
        if (pane) pane.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
        }
    }
}

// Action -> Resetting form inputs to prepare for creating a new category
function showNew() {
    document.querySelectorAll('.cat-row').forEach(r => {
        r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
        r.classList.add('bg-white', 'border-gray-100');
    });
    document.getElementById('form-mode-label').textContent = 'Add New Category';
    document.getElementById('form-cat-name-preview').textContent = 'New Category';
    document.getElementById('f-id').value = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-slug').value = '';
    document.getElementById('f-image').value = '';
    document.getElementById('f-image-file').value = '';
    updatePreviewFromUrl('');
    document.getElementById('f-icon').value = 'ti-tag';
    var iconPrev = document.getElementById('icon-preview-i');
    if (iconPrev) iconPrev.className = 'ti ti-tag text-xl';
    document.getElementById('f-desc').value = '';
    
    // Hide delete button for new category
    document.getElementById('btn-delete').classList.add('hidden');
    
    var pane = document.getElementById('cat-form-pane');
    var backdrop = document.getElementById('cat-form-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
}

// Helper -> Auto-generate URL slug from category name (only if slug field is empty)
function autoSlug(name) {
    var slugField = document.getElementById('f-slug');
    if (!slugField || slugField.dataset.manuallyEdited === 'true') return;
    slugField.value = name.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-');
}

// Mark slug field as manually edited if user types in it
document.addEventListener('DOMContentLoaded', () => {
    var slugField = document.getElementById('f-slug');
    if (slugField) {
        slugField.addEventListener('input', () => {
            slugField.dataset.manuallyEdited = slugField.value ? 'true' : 'false';
        });
    }
});

// Image Preview -> Displaying preview when selecting a local image file
function previewSelectedImage(input) {
    var file = input.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var previewImg = document.getElementById('form-image-preview');
            var placeholder = document.getElementById('upload-placeholder');
            var overlay = document.getElementById('preview-hover-overlay');
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (overlay) overlay.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}

// Image Preview -> Updating image preview when typing a web link URL
function updatePreviewFromUrl(url) {
    var previewImg = document.getElementById('form-image-preview');
    var placeholder = document.getElementById('upload-placeholder');
    var overlay = document.getElementById('preview-hover-overlay');
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

// Controls -> Closing the right side edit panel
function closeCatFormPane() {
    var pane = document.getElementById('cat-form-pane');
    var backdrop = document.getElementById('cat-form-backdrop');
    if (pane) pane.classList.add('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('hidden');
    }
    document.querySelectorAll('.cat-row').forEach(r => {
        r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
        r.classList.add('bg-white', 'border-gray-100');
    });
}

// API Saving -> Intercepting form submissions and routing data to REST API endpoint
document.getElementById('cat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) setButtonLoading(submitBtn, true);

    var formData = new FormData(this);
    var action = document.getElementById('f-action').value;
    var url = '/api/categories.php' + (action === 'delete' ? '?action=delete' : '');

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
            }, 3000);
        } else {
            if (submitBtn) setButtonLoading(submitBtn, false);
            showToast(data.message || 'An error occurred.', 'error');
        }
    })
    .catch(err => {
        if (submitBtn) setButtonLoading(submitBtn, false);
        console.error(err);
        showToast('Network error occurred.', 'error');
    });
});

// API Deleting -> Confirming deletion and sending delete request to REST API
function submitDelete() {
    uiConfirm("Are you sure you want to delete this category?", () => {
        var deleteBtn = document.getElementById('btn-delete');
        if (deleteBtn) setButtonLoading(deleteBtn, true, 'Deleting...');

        document.getElementById('f-action').value = 'delete';
        var formData = new FormData();
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
                }, 3000);
            } else {
                if (deleteBtn) setButtonLoading(deleteBtn, false);
                showToast(data.message || 'An error occurred.', 'error');
            }
        })
        .catch(err => {
            if (deleteBtn) setButtonLoading(deleteBtn, false);
            console.error(err);
            showToast('Network error occurred.', 'error');
        });
    });
}

// Initial render
applyFilters();
</script>


<?php
/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: admin/view/categories.view.php (Admin Category Management View)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - api/categories.php
   - product_catalog.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Categories API (api/categories.php) and Catalog Sidebar
=============================================================================
*/
?>
