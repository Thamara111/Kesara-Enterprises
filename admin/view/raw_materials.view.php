<?php
/**
 * Raw Materials View
 * Standard template view for raw materials inventory directory.
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Table Creation
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS raw_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_name VARCHAR(255) NOT NULL,
            material_category VARCHAR(100) NOT NULL,
            material_name VARCHAR(255) NOT NULL,
            weight DECIMAL(10,2) NOT NULL,
            weight_unit VARCHAR(20) DEFAULT 'kg',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {
        // Table exists
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_raw_material') {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $material_category = trim($_POST['material_category'] ?? '');
    $material_name = trim($_POST['material_name'] ?? '');
    $weight = (float) ($_POST['weight'] ?? 0);
    $weight_unit = trim($_POST['weight_unit'] ?? 'kg');

    if ($supplier_name && $material_category && $material_name && $weight > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO raw_materials (supplier_name, material_category, material_name, weight, weight_unit) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_name, $material_category, $material_name, $weight, $weight_unit]);
            $success_msg = "Raw material entry saved successfully!";
        } catch (\Exception $e) {
            $error_msg = "Error saving raw material: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all required fields accurately.";
    }
}

// Fetch Raw Materials Records
$raw_materials = [];
$total_weight_kg = 0;
$categories_count = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $raw_materials = $pdo->query("SELECT * FROM raw_materials ORDER BY created_at DESC")->fetchAll();
        foreach ($raw_materials as $rm) {
            $total_weight_kg += (float)$rm['weight'];
            $cat = $rm['material_category'];
            $categories_count[$cat] = ($categories_count[$cat] ?? 0) + 1;
        }
    } catch (\Exception $e) {}
}
?>

<div class="flex-1 flex overflow-hidden">
    <div id="raw-materials-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Raw Materials Directory</h1>
                <p class="text-sm text-gray-500 mt-1">Data entry &amp; monitoring ledger for raw fabric, elastic, and supplies.</p>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- Stats -->
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= count($raw_materials) ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Entries</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-emerald-600"><?= number_format($total_weight_kg, 1) ?> kg</p>
                        <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mt-0.5">Total Weight</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-brand"><?= count($categories_count) ?></p>
                        <p class="text-[9px] font-bold text-brand uppercase tracking-widest mt-0.5">Categories</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <button onclick="downloadPDF('rm-table-card', 'Raw_Materials_Report')" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-printer text-lg"></i> Export PDF
                    </button>
                    <button onclick="openRMModal()" 
                        class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        <i class="ti ti-plus text-lg"></i> Add Raw Material
                    </button>
                </div>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="mx-8 mt-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <i class="ti ti-circle-check text-lg text-emerald-600"></i> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="mx-8 mt-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <i class="ti ti-alert-triangle text-lg text-red-600"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Search Filter -->
        <div class="px-8 py-4 border-b border-gray-100 bg-gray-50/30 flex items-center gap-4">
            <div class="relative flex-1 group">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                <input id="rm-search" type="text" placeholder="Search by supplier name, material category, or description..." onkeyup="filterRMTable()"
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all">
            </div>
        </div>

        <!-- Main Content Table -->
        <div class="flex-1 overflow-y-auto overflow-x-auto p-8" id="rm-table-card">
            <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-4 py-3 rounded-l-xl">Supplier Name</th>
                        <th class="px-4 py-3">Material Category</th>
                        <th class="px-4 py-3">Material Description</th>
                        <th class="px-4 py-3 text-right">Weight / Qty</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Date Added</th>
                    </tr>
                </thead>
                <tbody id="rm-tbody">
                    <?php if (empty($raw_materials)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                No raw materials logged yet. Click "+ Add Raw Material" to record your first entry.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($raw_materials as $rm): ?>
                            <tr class="rm-row bg-white cursor-pointer hover:bg-gray-50/60 transition-all group shadow-sm"
                                onclick="openRMDrawer(<?= htmlspecialchars(json_encode($rm)) ?>)">
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 font-bold text-gray-900 text-xs">
                                    <?= htmlspecialchars($rm['supplier_name']) ?>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-100">
                                        <?= htmlspecialchars($rm['material_category']) ?>
                                    </span>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-semibold text-gray-800">
                                    <?= htmlspecialchars($rm['material_name']) ?>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-right font-black text-brand">
                                    <?= number_format((float)$rm['weight'], 2) ?> <?= htmlspecialchars($rm['weight_unit']) ?>
                                </td>
                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-xs text-right text-gray-400 font-semibold">
                                    <?= date('d M Y, h:i A', strtotime($rm['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Overlay -->
<div id="rm-modal" class="hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-xl w-full p-8 space-y-6 animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
                <i class="ti ti-plus-circle text-brand text-xl"></i> Enter Raw Material
            </h2>
            <button onclick="closeRMModal()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="add_raw_material">

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Supplier Name <span class="text-red-500">*</span></label>
                <input type="text" name="supplier_name" placeholder="e.g. Ceylon Textile Mills" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Material Category <span class="text-red-500">*</span></label>
                <select name="material_category" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all cursor-pointer">
                    <option value="">Select Category...</option>
                    <option value="Cotton Fabric">Cotton Fabric</option>
                    <option value="Elastic Band">Elastic Band</option>
                    <option value="Sewing Thread">Sewing Thread</option>
                    <option value="Labels & Tags">Labels &amp; Tags</option>
                    <option value="Packaging Polybag">Packaging Polybag</option>
                    <option value="Accessories">Accessories</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Material Description <span class="text-red-500">*</span></label>
                <input type="text" name="material_name" placeholder="e.g. 100% Combed Cotton Single Jersey" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Weight / Quantity <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="number" step="0.01" name="weight" placeholder="0.00" min="0.01" required
                        class="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                    <select name="weight_unit" class="w-28 px-3 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-bold text-gray-700 outline-none">
                        <option value="kg">kg</option>
                        <option value="GSM">GSM</option>
                        <option value="Meters">Meters</option>
                        <option value="Rolls">Rolls</option>
                        <option value="Pieces">Pieces</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRMModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light font-bold rounded-xl text-xs hover:opacity-90 transition-all shadow-md shadow-brand/20">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Slide Drawer -->
<div id="rm-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeRMDrawer()"></div>
<div id="rm-drawer" class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="rm-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 id="rmd-supplier" class="text-xl font-black text-gray-900 tracking-tight"></h2>
                <p id="rmd-cat" class="text-xs font-bold text-brand mt-1"></p>
            </div>
            <button onclick="closeRMDrawer()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-3 text-xs">
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Material Description:</span><strong id="rmd-material" class="text-gray-900"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Weight / Qty:</span><strong id="rmd-weight" class="text-brand font-black"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Date Added:</span><strong id="rmd-date" class="text-gray-900"></strong></div>
        </div>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
        <button onclick="downloadPDF('rm-drawer-content', 'Raw_Material_Entry')" class="px-6 py-3 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all flex items-center gap-2">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
function openRMModal() { document.getElementById('rm-modal').classList.remove('hidden'); }
function closeRMModal() { document.getElementById('rm-modal').classList.add('hidden'); }

function openRMDrawer(data) {
    document.getElementById('rmd-supplier').textContent = data.supplier_name;
    document.getElementById('rmd-cat').textContent = data.material_category;
    document.getElementById('rmd-material').textContent = data.material_name;
    document.getElementById('rmd-weight').textContent = data.weight + ' ' + data.weight_unit;
    document.getElementById('rmd-date').textContent = data.created_at;

    document.getElementById('rm-drawer-backdrop').classList.remove('hidden');
    document.getElementById('rm-drawer').classList.remove('translate-x-full');
}

function closeRMDrawer() {
    document.getElementById('rm-drawer-backdrop').classList.add('hidden');
    document.getElementById('rm-drawer').classList.add('translate-x-full');
}

function filterRMTable() {
    var q = document.getElementById('rm-search').value.toLowerCase().trim();
    document.querySelectorAll('#rm-tbody tr.rm-row').forEach(row => {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
