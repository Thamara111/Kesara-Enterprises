<?php
/**
 * Trash / Deleted Items View
 * Displays soft-deleted records across products, categories, orders, and admins.
 * Allows restoring or permanently deleting these records.
 */

$deleted_products = [];
$deleted_categories = [];
$deleted_orders = [];
$deleted_admins = [];
$suspended_customers = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE deleted_at IS NOT NULL");
        $deleted_products = $stmt->fetchAll();

        $stmt = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NOT NULL");
        $deleted_categories = $stmt->fetchAll();

        $stmt = $pdo->query("SELECT * FROM orders WHERE deleted_at IS NOT NULL");
        $deleted_orders = $stmt->fetchAll();

        $stmt = $pdo->query("SELECT * FROM admins WHERE deleted_at IS NOT NULL");
        $deleted_admins = $stmt->fetchAll();

        $stmt = $pdo->query("SELECT * FROM users WHERE status = 'suspended'");
        $suspended_customers = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}
?>

<div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-8 uppercase tracking-wider">
        <a href="/admin-dashboard" class="hover:text-brand transition-all">Dashboard</a>
        <i class="ti ti-chevron-right text-[10px]"></i>
        <span class="text-gray-900 font-bold">Recycle Bin</span>
    </nav>

    <div class="flex items-center justify-between mb-10 border-b border-gray-150 pb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Recycle Bin</h1>
            <p class="text-xs text-gray-500 mt-1">Manage deleted records and suspended customers.</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 mb-8 border-b border-gray-100 pb-2">
        <button class="trash-tab text-sm font-bold pb-2 border-b-2 border-brand text-brand" onclick="switchTrashTab('products', this)">Products (<?= count($deleted_products) ?>)</button>
        <button class="trash-tab text-sm font-bold pb-2 border-b-2 border-transparent text-gray-400 hover:text-gray-700" onclick="switchTrashTab('categories', this)">Categories (<?= count($deleted_categories) ?>)</button>
        <button class="trash-tab text-sm font-bold pb-2 border-b-2 border-transparent text-gray-400 hover:text-gray-700" onclick="switchTrashTab('orders', this)">Orders (<?= count($deleted_orders) ?>)</button>
        <?php if ($role === 'admin'): ?>
        <button class="trash-tab text-sm font-bold pb-2 border-b-2 border-transparent text-gray-400 hover:text-gray-700" onclick="switchTrashTab('users', this)">Staff (<?= count($deleted_admins) ?>)</button>
        <button class="trash-tab text-sm font-bold pb-2 border-b-2 border-transparent text-gray-400 hover:text-gray-700" onclick="switchTrashTab('customers', this)">Customers (<?= count($suspended_customers) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Content Sections -->
    <div id="tab-products" class="trash-section block space-y-4">
        <?php if (empty($deleted_products)): ?>
            <p class="text-gray-400 text-sm font-bold">No deleted products found.</p>
        <?php else: ?>
            <?php foreach($deleted_products as $item): ?>
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-xs text-gray-400">Deleted: <?= $item['deleted_at'] ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="restoreItem('products', <?= $item['id'] ?>)" class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200">Restore</button>
                    <button onclick="confirmHardDelete('products', <?= $item['id'] ?>)" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100">Delete Permanently</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tab-categories" class="trash-section hidden space-y-4">
        <?php if (empty($deleted_categories)): ?>
            <p class="text-gray-400 text-sm font-bold">No deleted categories found.</p>
        <?php else: ?>
            <?php foreach($deleted_categories as $item): ?>
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-xs text-gray-400">Deleted: <?= $item['deleted_at'] ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="restoreItem('categories', <?= $item['id'] ?>)" class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200">Restore</button>
                    <button onclick="confirmHardDelete('categories', <?= $item['id'] ?>)" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100">Delete Permanently</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tab-orders" class="trash-section hidden space-y-4">
        <?php if (empty($deleted_orders)): ?>
            <p class="text-gray-400 text-sm font-bold">No deleted orders found.</p>
        <?php else: ?>
            <?php foreach($deleted_orders as $item): ?>
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-900">Order #<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></h3>
                    <p class="text-xs text-gray-400">Deleted: <?= $item['deleted_at'] ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="restoreItem('orders', <?= $item['id'] ?>)" class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200">Restore</button>
                    <button onclick="confirmHardDelete('orders', <?= $item['id'] ?>)" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100">Delete Permanently</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($role === 'admin'): ?>
    <div id="tab-users" class="trash-section hidden space-y-4">
        <?php if (empty($deleted_admins)): ?>
            <p class="text-gray-400 text-sm font-bold">No deleted staff found.</p>
        <?php else: ?>
            <?php foreach($deleted_admins as $item): ?>
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-900"><?= htmlspecialchars($item['username']) ?></h3>
                    <p class="text-xs text-gray-400">Deleted: <?= $item['deleted_at'] ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="restoreItem('admins', <?= $item['id'] ?>)" class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200">Restore</button>
                    <button onclick="confirmHardDelete('admins', <?= $item['id'] ?>)" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100">Delete Permanently</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tab-customers" class="trash-section hidden space-y-4">
        <?php if (empty($suspended_customers)): ?>
            <p class="text-gray-400 text-sm font-bold">No suspended customers found.</p>
        <?php else: ?>
            <?php foreach($suspended_customers as $item): ?>
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-900"><?= htmlspecialchars($item['business_name'] ?: ($item['first_name'].' '.$item['last_name'])) ?></h3>
                    <p class="text-xs text-gray-400">Status: Suspended</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="restoreItem('users', <?= $item['id'] ?>)" class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200">Unsuspend</button>
                    <button onclick="confirmHardDelete('users', <?= $item['id'] ?>)" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100">Delete Permanently</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Hard Delete Confirm Modal -->
<div id="hard-delete-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity duration-200" style="display: none;">
    <div id="hard-delete-content" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-sm w-full text-center flex flex-col items-center transform scale-95 opacity-0 transition-all duration-200">
        <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-6">
            <i class="ti ti-alert-triangle text-3xl"></i>
        </div>
        <h3 class="text-xl font-extrabold text-gray-900 mb-2">Delete Permanently?</h3>
        <p class="text-sm font-medium text-gray-500 mb-8">Are you sure you want to permanently delete this item? This action cannot be undone.</p>
        <input type="hidden" id="hd-table" value="">
        <input type="hidden" id="hd-id" value="">
        <div class="flex gap-3 w-full">
            <button onclick="closeHardDeleteModal()" class="flex-1 bg-gray-50 text-gray-700 font-bold py-3.5 rounded-2xl hover:bg-gray-100 transition-colors">Cancel</button>
            <button onclick="executeHardDelete()" class="flex-1 bg-red-500 text-white font-bold py-3.5 rounded-2xl hover:bg-red-600 shadow-lg shadow-red-500/20 transition-all transform hover:-translate-y-px">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
function switchTrashTab(tabName, btn) {
    document.querySelectorAll('.trash-section').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-' + tabName).classList.remove('hidden');
    
    document.querySelectorAll('.trash-tab').forEach(el => {
        el.classList.remove('border-brand', 'text-brand');
        el.classList.add('border-transparent', 'text-gray-400');
    });
    btn.classList.remove('border-transparent', 'text-gray-400');
    btn.classList.add('border-brand', 'text-brand');
}

function restoreItem(table, id) {
    fetch('/api/trash.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'restore', table: table, id: id })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            showToast('Item restored successfully.', 'success');
            setTimeout(() => location.reload(), 3000);
        }
        else showToast('Error: ' + data.message, 'error');
    });
}

function confirmHardDelete(table, id) {
    document.getElementById('hd-table').value = table;
    document.getElementById('hd-id').value = id;
    var modal = document.getElementById('hard-delete-modal');
    var content = document.getElementById('hard-delete-content');
    modal.style.display = 'flex';
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeHardDeleteModal() {
    var modal = document.getElementById('hard-delete-modal');
    var content = document.getElementById('hard-delete-content');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

function executeHardDelete() {
    var table = document.getElementById('hd-table').value;
    var id = document.getElementById('hd-id').value;
    fetch('/api/trash.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hard_delete', table: table, id: id })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            showToast('Item permanently deleted.', 'success');
            setTimeout(() => location.reload(), 3000);
        }
        else showToast('Error: ' + data.message, 'error');
    });
}
</script>
