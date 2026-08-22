<?php
/**
 * Damage Goods View
 * Standard template view with breakdown by Fabric Color | S | M | L | XL | XXL | Qty | Total
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Table Creation
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS damage_goods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            fabric_color VARCHAR(50) DEFAULT 'Standard',
            qty_s INT DEFAULT 0,
            qty_m INT DEFAULT 0,
            qty_l INT DEFAULT 0,
            qty_xl INT DEFAULT 0,
            qty_xxl INT DEFAULT 0,
            quantity INT NOT NULL,
            reason TEXT NOT NULL,
            reported_by VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns if table pre-existed
        $cols = ['qty_s', 'qty_m', 'qty_l', 'qty_xl', 'qty_xxl'];
        foreach ($cols as $col) {
            $chk = $pdo->query("SHOW COLUMNS FROM damage_goods LIKE '$col'");
            if (!$chk->fetch()) {
                $pdo->exec("ALTER TABLE damage_goods ADD COLUMN $col INT DEFAULT 0");
            }
        }
    } catch (\Exception $e) {
        // Table exists
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_damage_goods') {
    $item_name = trim($_POST['item_name'] ?? '');
    $fabric_color = trim($_POST['fabric_color'] ?? 'Standard');
    $qs = (int)($_POST['qty_s'] ?? 0);
    $qm = (int)($_POST['qty_m'] ?? 0);
    $ql = (int)($_POST['qty_l'] ?? 0);
    $qxl = (int)($_POST['qty_xl'] ?? 0);
    $qxxl = (int)($_POST['qty_xxl'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? ($qs + $qm + $ql + $qxl + $qxxl));
    if ($quantity <= 0) $quantity = $qs + $qm + $ql + $qxl + $qxxl;
    
    $reason = trim($_POST['reason'] ?? '');
    $reported_by = trim($_POST['reported_by'] ?? 'Admin');

    if ($item_name && $quantity > 0 && $reason) {
        try {
            $stmt = $pdo->prepare("INSERT INTO damage_goods (item_name, fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, reason, reported_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $fabric_color, $qs, $qm, $ql, $qxl, $qxxl, $quantity, $reason, $reported_by]);
            $success_msg = "Damage goods entry logged successfully!";
        } catch (\Exception $e) {
            $error_msg = "Error logging damage goods: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide valid item details, quantity, and reason.";
    }
}

// Fetch Damage Goods Records
$damage_records = [];
$total_damaged_pcs = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $damage_records = $pdo->query("SELECT * FROM damage_goods ORDER BY created_at DESC")->fetchAll();
        foreach ($damage_records as $dr) {
            $total_damaged_pcs += (int)$dr['quantity'];
        }
    } catch (\Exception $e) {}
}
?>

<div class="flex-1 flex overflow-hidden">
    <div id="damage-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Damage Goods &amp; B-Grade Stock</h1>
                <p class="text-sm text-gray-500 mt-1">Logging and tracking of damaged, sub-standard, or B-grade products.</p>
            </div>

            <div class="flex items-center gap-6">
                <!-- Stats -->
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= count($damage_records) ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Logs</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-red-600"><?= number_format($total_damaged_pcs) ?> pcs</p>
                        <p class="text-[9px] font-bold text-red-500 uppercase tracking-widest mt-0.5">Damaged Qty</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <button onclick="downloadPDF('damage-table-card', 'Damage_Goods_Report')" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-printer text-lg"></i> Export PDF
                    </button>
                    <button onclick="openDamageModal()" 
                        class="flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 transition-all shadow-lg shadow-red-600/20">
                        <i class="ti ti-alert-octagon text-lg"></i> Log Damage Goods
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

        <!-- Search Bar -->
        <div class="px-8 py-4 border-b border-gray-100 bg-gray-50/30 flex items-center gap-4">
            <div class="relative flex-1 group">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                <input id="damage-search" type="text" placeholder="Search by item name, defect reason, or inspector..." onkeyup="filterDamageTable()"
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all">
            </div>
        </div>

        <!-- Table Card -->
        <div class="flex-1 overflow-y-auto overflow-x-auto p-8" id="damage-table-card">
            <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-4 py-3 rounded-l-xl">Fabric Color</th>
                        <th class="px-2 py-3 text-center w-14">S</th>
                        <th class="px-2 py-3 text-center w-14">M</th>
                        <th class="px-2 py-3 text-center w-14">L</th>
                        <th class="px-2 py-3 text-center w-14">XL</th>
                        <th class="px-2 py-3 text-center w-14">XXL</th>
                        <th class="px-4 py-3 text-center w-24">Qty</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Total / Item Details</th>
                    </tr>
                </thead>
                <tbody id="damage-tbody">
                    <?php if (empty($damage_records)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                No damaged goods logged yet. Click "+ Log Damage Goods" to record entry.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($damage_records as $dr): ?>
                            <tr class="damage-row bg-white cursor-pointer hover:bg-gray-50/60 transition-all group shadow-sm"
                                onclick="openDamageDrawer(<?= htmlspecialchars(json_encode($dr)) ?>)">
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 font-bold text-gray-900 text-xs">
                                    <?= htmlspecialchars($dr['fabric_color']) ?>
                                </td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$dr['qty_s'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$dr['qty_m'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$dr['qty_l'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$dr['qty_xl'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$dr['qty_xxl'] ?></td>
                                <td class="p-4 border-y border-gray-100 text-xs text-center font-black text-red-600">
                                    <?= number_format($dr['quantity']) ?> pcs
                                </td>
                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-xs text-right font-bold text-gray-900">
                                    <?= htmlspecialchars($dr['item_name']) ?> (<?= number_format($dr['quantity']) ?> pcs)
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
<div id="damage-modal" class="hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-2xl w-full p-8 space-y-6 animate-in fade-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
                <i class="ti ti-alert-circle text-red-500 text-xl"></i> Log Damage Goods
            </h2>
            <button onclick="closeDamageModal()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="add_damage_goods">

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Item / Product Name <span class="text-red-500">*</span></label>
                <input type="text" name="item_name" placeholder="e.g. Mens Classic Cotton Briefs" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fabric Color <span class="text-red-500">*</span></label>
                <input type="text" name="fabric_color" value="White" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none">
            </div>

            <!-- Size Quantities Breakdown -->
            <div class="space-y-2 pt-2 border-t border-gray-100">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Quantity Breakdown (S, M, L, XL, XXL)</label>
                <div class="grid grid-cols-5 gap-2">
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">S</label><input type="number" name="qty_s" id="d-s" value="0" min="0" oninput="recalcDmgTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">M</label><input type="number" name="qty_m" id="d-m" value="10" min="0" oninput="recalcDmgTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">L</label><input type="number" name="qty_l" id="d-l" value="0" min="0" oninput="recalcDmgTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">XL</label><input type="number" name="qty_xl" id="d-xl" value="0" min="0" oninput="recalcDmgTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">XXL</label><input type="number" name="qty_xxl" id="d-xxl" value="0" min="0" oninput="recalcDmgTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                </div>
            </div>

            <div class="flex items-center justify-between p-3 bg-red-50 rounded-2xl border border-red-100 text-xs font-bold text-red-700">
                <span>Total Damaged Quantity:</span>
                <span id="d-total-display" class="font-black text-sm">10 pcs</span>
                <input type="hidden" name="quantity" id="d-quantity-hidden" value="10">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Defect Reason / Inspection Note <span class="text-red-500">*</span></label>
                <input type="text" name="reason" placeholder="e.g. Staining on waist elastic, hem seam tear" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Reported By</label>
                <input type="text" name="reported_by" value="Quality Inspector" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeDamageModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-red-600 text-white font-bold rounded-xl text-xs hover:bg-red-700 transition-all shadow-md shadow-red-600/20">Log Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Slide Drawer -->
<div id="damage-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeDamageDrawer()"></div>
<div id="damage-drawer" class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="damage-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 id="dd-title" class="text-xl font-black text-gray-900 tracking-tight"></h2>
                <p id="dd-variant" class="text-xs font-bold text-brand mt-1"></p>
            </div>
            <button onclick="closeDamageDrawer()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <div class="bg-red-50 p-5 rounded-2xl border border-red-100 space-y-3 text-xs">
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Damaged Quantity:</span><strong id="dd-qty" class="text-red-700 font-black"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Reported By:</span><strong id="dd-by" class="text-gray-900"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Log Date:</span><strong id="dd-date" class="text-gray-900"></strong></div>
        </div>

        <!-- Table Breakdown in Drawer -->
        <section class="space-y-3">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Damage Size Breakdown</h3>
            <div class="overflow-x-auto border border-gray-100 rounded-2xl">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="py-2.5 px-3">Fabric Color</th>
                            <th class="py-2.5 px-2 text-center">S</th>
                            <th class="py-2.5 px-2 text-center">M</th>
                            <th class="py-2.5 px-2 text-center">L</th>
                            <th class="py-2.5 px-2 text-center">XL</th>
                            <th class="py-2.5 px-2 text-center">XXL</th>
                            <th class="py-2.5 px-3 text-center">Qty</th>
                            <th class="py-2.5 px-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="dd-breakdown-tbody" class="divide-y divide-gray-50"></tbody>
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Damage Reason &amp; Inspection Note</h3>
            <div id="dd-reason" class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs font-semibold text-gray-800 leading-relaxed"></div>
        </section>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
        <button onclick="downloadPDF('damage-drawer-content', 'Damage_Goods_Inspection_Report')" class="px-6 py-3 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all flex items-center gap-2">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
function openDamageModal() { 
    document.getElementById('damage-modal').classList.remove('hidden'); 
    recalcDmgTotal();
}
function closeDamageModal() { document.getElementById('damage-modal').classList.add('hidden'); }

function recalcDmgTotal() {
    var qs = parseInt(document.getElementById('d-s')?.value || 0) || 0;
    var qm = parseInt(document.getElementById('d-m')?.value || 0) || 0;
    var ql = parseInt(document.getElementById('d-l')?.value || 0) || 0;
    var qxl = parseInt(document.getElementById('d-xl')?.value || 0) || 0;
    var qxxl = parseInt(document.getElementById('d-xxl')?.value || 0) || 0;
    var tot = qs + qm + ql + qxl + qxxl;

    document.getElementById('d-total-display').textContent = tot + ' pcs';
    document.getElementById('d-quantity-hidden').value = tot;
}

function openDamageDrawer(data) {
    document.getElementById('dd-title').textContent = data.item_name;
    document.getElementById('dd-variant').textContent = 'Fabric Color: ' + data.fabric_color;
    document.getElementById('dd-qty').textContent = data.quantity + ' pcs';
    document.getElementById('dd-by').textContent = data.reported_by;
    document.getElementById('dd-date').textContent = data.created_at;
    document.getElementById('dd-reason').textContent = data.reason;

    var qs = parseInt(data.qty_s || 0) || 0;
    var qm = parseInt(data.qty_m || 0) || 0;
    var ql = parseInt(data.qty_l || 0) || 0;
    var qxl = parseInt(data.qty_xl || 0) || 0;
    var qxxl = parseInt(data.qty_xxl || 0) || 0;
    var tot = parseInt(data.quantity || (qs + qm + ql + qxl + qxxl)) || 0;

    document.getElementById('dd-breakdown-tbody').innerHTML = `
        <tr>
            <td class="py-3 px-3 font-bold text-gray-900">${data.fabric_color}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qs}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qm}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${ql}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxl}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxxl}</td>
            <td class="py-3 px-3 text-center font-black text-red-600">${tot} pcs</td>
            <td class="py-3 px-3 text-right font-black text-gray-900">${tot} pcs</td>
        </tr>
    `;

    document.getElementById('damage-drawer-backdrop').classList.remove('hidden');
    document.getElementById('damage-drawer').classList.remove('translate-x-full');
}

function closeDamageDrawer() {
    document.getElementById('damage-drawer-backdrop').classList.add('hidden');
    document.getElementById('damage-drawer').classList.add('translate-x-full');
}

function filterDamageTable() {
    var q = document.getElementById('damage-search').value.toLowerCase().trim();
    document.querySelectorAll('#damage-tbody tr.damage-row').forEach(row => {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
