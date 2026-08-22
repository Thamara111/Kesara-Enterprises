<?php
/**
 * Goods Return View
 * Standard template view with breakdown by Fabric Color | S | M | L | XL | XXL | Qty | Total
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Tables Creation
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS goods_returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            return_ref VARCHAR(100) NOT NULL,
            entity_name VARCHAR(255) NOT NULL,
            return_reason VARCHAR(100) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            fabric_color VARCHAR(50) DEFAULT 'Standard',
            qty_s INT DEFAULT 0,
            qty_m INT DEFAULT 0,
            qty_l INT DEFAULT 0,
            qty_xl INT DEFAULT 0,
            qty_xxl INT DEFAULT 0,
            quantity INT NOT NULL,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns if table pre-existed
        $cols = ['qty_s', 'qty_m', 'qty_l', 'qty_xl', 'qty_xxl'];
        foreach ($cols as $col) {
            $chk = $pdo->query("SHOW COLUMNS FROM goods_returns LIKE '$col'");
            if (!$chk->fetch()) {
                $pdo->exec("ALTER TABLE goods_returns ADD COLUMN $col INT DEFAULT 0");
            }
        }

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
    } catch (\Exception $e) {
        // Tables exist
    }
}

// Handle Goods Return Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_goods_return') {
    $return_ref = trim($_POST['return_ref'] ?? '');
    $entity_name = trim($_POST['entity_name'] ?? '');
    $return_reason = trim($_POST['return_reason'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $fabric_color = trim($_POST['fabric_color'] ?? 'Standard');
    $qs = (int)($_POST['qty_s'] ?? 0);
    $qm = (int)($_POST['qty_m'] ?? 0);
    $ql = (int)($_POST['qty_l'] ?? 0);
    $qxl = (int)($_POST['qty_xl'] ?? 0);
    $qxxl = (int)($_POST['qty_xxl'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? ($qs + $qm + $ql + $qxl + $qxxl));
    if ($quantity <= 0) $quantity = $qs + $qm + $ql + $qxl + $qxxl;
    
    $remarks = trim($_POST['remarks'] ?? '');

    if ($return_ref && $entity_name && $return_reason && $product_name && $quantity > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Record Goods Return Entry
            $stmt = $pdo->prepare("INSERT INTO goods_returns (return_ref, entity_name, return_reason, product_name, fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$return_ref, $entity_name, $return_reason, $product_name, $fabric_color, $qs, $qm, $ql, $qxl, $qxxl, $quantity, $remarks]);

            // 2. Business Logic Handler per Return Reason
            if ($return_reason === 'Damage Goods') {
                // Log to damage_goods table
                $dmg_stmt = $pdo->prepare("INSERT INTO damage_goods (item_name, fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, reason, reported_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $dmg_reason = "Customer Return (Ref: " . $return_ref . ") — " . ($remarks ?: 'Damaged on delivery');
                $dmg_stmt->execute([$product_name, $fabric_color, $qs, $qm, $ql, $qxl, $qxxl, $quantity, $dmg_reason, $entity_name]);

                // Also restock into inventory stock
                $inv_stmt = $pdo->prepare("UPDATE inventory i JOIN products p ON i.product_id = p.id SET i.quantity = i.quantity + ? WHERE p.name LIKE ? LIMIT 1");
                $inv_stmt->execute([$quantity, '%' . $product_name . '%']);
            } elseif ($return_reason === 'Customer returns' || $return_reason === 'Wrong Size') {
                // Restore item quantity to inventory
                $inv_stmt = $pdo->prepare("UPDATE inventory i JOIN products p ON i.product_id = p.id SET i.quantity = i.quantity + ? WHERE p.name LIKE ? LIMIT 1");
                $inv_stmt->execute([$quantity, '%' . $product_name . '%']);
            }

            $pdo->commit();
            $success_msg = "Goods return " . htmlspecialchars($return_ref) . " processed successfully! Inventory updated accordingly.";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Error processing goods return: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all required return details.";
    }
}

// Fetch Goods Returns Records
$goods_returns = [];
$total_returned_pcs = 0;
$damage_returns_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $goods_returns = $pdo->query("SELECT * FROM goods_returns ORDER BY created_at DESC")->fetchAll();
        foreach ($goods_returns as $gr) {
            $total_returned_pcs += (int)$gr['quantity'];
            if ($gr['return_reason'] === 'Damage Goods') $damage_returns_count++;
        }
    } catch (\Exception $e) {}
}
?>

<div class="flex-1 flex overflow-hidden">
    <div id="return-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Goods Return Directory</h1>
                <p class="text-sm text-gray-500 mt-1">Manage customer &amp; supplier product returns with automatic stock adjustment.</p>
            </div>

            <div class="flex items-center gap-6">
                <!-- Stats -->
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= count($goods_returns) ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Returns</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-brand"><?= number_format($total_returned_pcs) ?> pcs</p>
                        <p class="text-[9px] font-bold text-brand uppercase tracking-widest mt-0.5">Returned Qty</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-red-600"><?= $damage_returns_count ?></p>
                        <p class="text-[9px] font-bold text-red-500 uppercase tracking-widest mt-0.5">Damaged</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <button onclick="downloadPDF('return-table-card', 'Goods_Returns_Report')" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-printer text-lg"></i> Export PDF
                    </button>
                    <button onclick="openReturnModal()" 
                        class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        <i class="ti ti-rotate-rectangle text-lg"></i> Record Goods Return
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
                <input id="return-search" type="text" placeholder="Search by Return Ref, customer/supplier name, or product..." onkeyup="filterReturnTable()"
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all">
            </div>
        </div>

        <!-- Table Card -->
        <div class="flex-1 overflow-y-auto overflow-x-auto p-8" id="return-table-card">
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
                        <th class="px-4 py-3 text-right rounded-r-xl">Total / Product Details</th>
                    </tr>
                </thead>
                <tbody id="return-tbody">
                    <?php if (empty($goods_returns)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                No goods returns recorded yet. Click "+ Record Goods Return" to process an entry.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($goods_returns as $gr): ?>
                            <tr class="return-row bg-white cursor-pointer hover:bg-gray-50/60 transition-all group shadow-sm"
                                onclick="openReturnDrawer(<?= htmlspecialchars(json_encode($gr)) ?>)">
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 font-bold text-gray-900 text-xs">
                                    <?= htmlspecialchars($gr['fabric_color']) ?>
                                </td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$gr['qty_s'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$gr['qty_m'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$gr['qty_l'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$gr['qty_xl'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$gr['qty_xxl'] ?></td>
                                <td class="p-4 border-y border-gray-100 text-xs text-center font-black text-gray-900">
                                    <?= number_format($gr['quantity']) ?> pcs
                                </td>
                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-xs text-right font-bold text-gray-900">
                                    <?= htmlspecialchars($gr['product_name']) ?> (Ref: <?= htmlspecialchars($gr['return_ref']) ?>)
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
<div id="return-modal" class="hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-2xl w-full p-8 space-y-6 max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
                <i class="ti ti-rotate-rectangle text-brand text-xl"></i> Record Goods Return
            </h2>
            <button onclick="closeReturnModal()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="add_goods_return">

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Return Ref / Note # <span class="text-red-500">*</span></label>
                    <input type="text" name="return_ref" placeholder="e.g. RET-2025-014" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Customer / Supplier <span class="text-red-500">*</span></label>
                    <input type="text" name="entity_name" placeholder="e.g. Royal Apparel Dealers" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Return Reason <span class="text-red-500">*</span></label>
                <select name="return_reason" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all cursor-pointer">
                    <option value="">Select Reason...</option>
                    <option value="Damage Goods">Damage Goods (Log to Damage &amp; Restock)</option>
                    <option value="Customer returns">Customer returns (Restock)</option>
                    <option value="Wrong Size">Wrong Size (Restock)</option>
                    <option value="Over-supplied">Over-supplied</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Product Name <span class="text-red-500">*</span></label>
                <input type="text" name="product_name" placeholder="e.g. Classic Boxer Briefs" required
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
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">S</label><input type="number" name="qty_s" id="r-s" value="0" min="0" oninput="recalcRetTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">M</label><input type="number" name="qty_m" id="r-m" value="10" min="0" oninput="recalcRetTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">L</label><input type="number" name="qty_l" id="r-l" value="0" min="0" oninput="recalcRetTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">XL</label><input type="number" name="qty_xl" id="r-xl" value="0" min="0" oninput="recalcRetTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                    <div><label class="text-[9px] font-bold text-gray-400 text-center block mb-1">XXL</label><input type="number" name="qty_xxl" id="r-xxl" value="0" min="0" oninput="recalcRetTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></div>
                </div>
            </div>

            <div class="flex items-center justify-between p-3 bg-brand/5 rounded-2xl border border-brand/10 text-xs font-bold text-brand">
                <span>Total Returned Quantity:</span>
                <span id="r-total-display" class="font-black text-sm">10 pcs</span>
                <input type="hidden" name="quantity" id="r-quantity-hidden" value="10">
            </div>

            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Remarks / Inspection Notes</label>
                <input type="text" name="remarks" placeholder="Optional notes..."
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeReturnModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light font-bold rounded-xl text-xs hover:opacity-90 transition-all shadow-md shadow-brand/20">Process Return</button>
            </div>
        </form>
    </div>
</div>

<!-- Slide Drawer -->
<div id="return-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeReturnDrawer()"></div>
<div id="return-drawer" class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="return-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 id="rd-ref" class="text-xl font-black text-gray-900 tracking-tight"></h2>
                <p id="rd-entity" class="text-xs font-bold text-gray-500 mt-1"></p>
            </div>
            <button onclick="closeReturnDrawer()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-3 text-xs">
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Return Reason:</span><strong id="rd-reason" class="text-brand font-bold"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Returned Qty:</span><strong id="rd-qty" class="text-gray-900 font-black"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Date Logged:</span><strong id="rd-date" class="text-gray-900"></strong></div>
        </div>

        <!-- Table Breakdown in Drawer -->
        <section class="space-y-3">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Goods Return Size Breakdown</h3>
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
                    <tbody id="rd-breakdown-tbody" class="divide-y divide-gray-50"></tbody>
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Product Details</h3>
            <div id="rd-product" class="p-4 bg-white rounded-2xl border border-gray-200 text-xs font-extrabold text-gray-900"></div>
        </section>

        <section class="space-y-2">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Remarks &amp; Notes</h3>
            <div id="rd-remarks" class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs font-semibold text-gray-700"></div>
        </section>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
        <button onclick="downloadPDF('return-drawer-content', 'Goods_Return_Receipt')" class="px-6 py-3 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all flex items-center gap-2">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
function openReturnModal() { 
    document.getElementById('return-modal').classList.remove('hidden'); 
    recalcRetTotal();
}
function closeReturnModal() { document.getElementById('return-modal').classList.add('hidden'); }

function recalcRetTotal() {
    var qs = parseInt(document.getElementById('r-s')?.value || 0) || 0;
    var qm = parseInt(document.getElementById('r-m')?.value || 0) || 0;
    var ql = parseInt(document.getElementById('r-l')?.value || 0) || 0;
    var qxl = parseInt(document.getElementById('r-xl')?.value || 0) || 0;
    var qxxl = parseInt(document.getElementById('r-xxl')?.value || 0) || 0;
    var tot = qs + qm + ql + qxl + qxxl;

    document.getElementById('r-total-display').textContent = tot + ' pcs';
    document.getElementById('r-quantity-hidden').value = tot;
}

function openReturnDrawer(data) {
    document.getElementById('rd-ref').textContent = data.return_ref;
    document.getElementById('rd-entity').textContent = data.entity_name;
    document.getElementById('rd-reason').textContent = data.return_reason;
    document.getElementById('rd-qty').textContent = data.quantity + ' pcs';
    document.getElementById('rd-date').textContent = data.created_at;
    document.getElementById('rd-product').textContent = data.product_name + ' (Fabric Color: ' + data.fabric_color + ')';
    document.getElementById('rd-remarks').textContent = data.remarks || 'No additional remarks.';

    var qs = parseInt(data.qty_s || 0) || 0;
    var qm = parseInt(data.qty_m || 0) || 0;
    var ql = parseInt(data.qty_l || 0) || 0;
    var qxl = parseInt(data.qty_xl || 0) || 0;
    var qxxl = parseInt(data.qty_xxl || 0) || 0;
    var tot = parseInt(data.quantity || (qs + qm + ql + qxl + qxxl)) || 0;

    document.getElementById('rd-breakdown-tbody').innerHTML = `
        <tr>
            <td class="py-3 px-3 font-bold text-gray-900">${data.fabric_color}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qs}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qm}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${ql}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxl}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxxl}</td>
            <td class="py-3 px-3 text-center font-black text-brand">${tot} pcs</td>
            <td class="py-3 px-3 text-right font-black text-gray-900">${tot} pcs</td>
        </tr>
    `;

    document.getElementById('return-drawer-backdrop').classList.remove('hidden');
    document.getElementById('return-drawer').classList.remove('translate-x-full');
}

function closeReturnDrawer() {
    document.getElementById('return-drawer-backdrop').classList.add('hidden');
    document.getElementById('return-drawer').classList.add('translate-x-full');
}

function filterReturnTable() {
    var q = document.getElementById('return-search').value.toLowerCase().trim();
    document.querySelectorAll('#return-tbody tr.return-row').forEach(row => {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
