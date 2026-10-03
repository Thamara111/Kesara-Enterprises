<?php
/**
 * B-Grade Stock Management View
 * Features:
 * 1. Dedicated tracking of secondary / factory-second B-Grade apparel stock.
 * 2. Real-time sizing matrix breakdown (Fabric Color | S | M | L | XL | XXL | Total Qty).
 * 3. Automatic stock synchronization from "Damage Goods & Quality Logs" when items are flagged as "B - grade".
 * 4. Manual Stock Adjustments and Clearance / Discount Sales recording.
 * 5. Comprehensive audit trail & Printable PDF reports.
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Tables Creation
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS b_grade_stock (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(255) NOT NULL,
            fabric_color VARCHAR(50) DEFAULT 'Standard',
            qty_s INT DEFAULT 0,
            qty_m INT DEFAULT 0,
            qty_l INT DEFAULT 0,
            qty_xl INT DEFAULT 0,
            qty_xxl INT DEFAULT 0,
            quantity INT DEFAULT 0,
            source_damage_id INT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS b_grade_stock_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            b_grade_stock_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            qty_change INT NOT NULL,
            qty_after INT NOT NULL,
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {
        // Tables exist
    }
}

// Fetch Catalog Products for Autocomplete Suggestions
$catalog_products = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $catalog_products = $pdo->query("SELECT id, name, sku FROM products WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}
}

// Handle Form Actions (Manual Add or Stock Adjustment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'adjust_b_grade_stock' && isset($pdo)) {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $adj_action = trim($_POST['adj_action'] ?? 'clearance'); // 'clearance', 'manual_add', 'dispose', 'correction'
        $adj_reason = trim($_POST['adj_reason'] ?? '');
        $qs_adj = (int)($_POST['qty_s'] ?? 0);
        $qm_adj = (int)($_POST['qty_m'] ?? 0);
        $ql_adj = (int)($_POST['qty_l'] ?? 0);
        $qxl_adj = (int)($_POST['qty_xl'] ?? 0);
        $qxxl_adj = (int)($_POST['qty_xxl'] ?? 0);
        $total_adj = $qs_adj + $qm_adj + $ql_adj + $qxl_adj + $qxxl_adj;

        if ($stock_id > 0 && $total_adj > 0) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM b_grade_stock WHERE id = ? FOR UPDATE");
                $stmt->execute([$stock_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    $is_reduction = in_array($adj_action, ['clearance', 'dispose', 'sale']);
                    $multiplier = $is_reduction ? -1 : 1;

                    $new_s = max(0, (int)$item['qty_s'] + ($qs_adj * $multiplier));
                    $new_m = max(0, (int)$item['qty_m'] + ($qm_adj * $multiplier));
                    $new_l = max(0, (int)$item['qty_l'] + ($ql_adj * $multiplier));
                    $new_xl = max(0, (int)$item['qty_xl'] + ($qxl_adj * $multiplier));
                    $new_xxl = max(0, (int)$item['qty_xxl'] + ($qxxl_adj * $multiplier));
                    $new_tot = $new_s + $new_m + $new_l + $new_xl + $new_xxl;

                    $upd = $pdo->prepare("UPDATE b_grade_stock SET qty_s = ?, qty_m = ?, qty_l = ?, qty_xl = ?, qty_xxl = ?, quantity = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
                    $upd->execute([$new_s, $new_m, $new_l, $new_xl, $new_xxl, $new_tot, $stock_id]);

                    $log_type = $is_reduction ? ("Deduction: " . ucfirst($adj_action)) : ("Addition: " . ucfirst($adj_action));
                    $qty_change_log = $total_adj * $multiplier;

                    $ins_log = $pdo->prepare("INSERT INTO b_grade_stock_logs (b_grade_stock_id, action_type, qty_change, qty_after, note) VALUES (?, ?, ?, ?, ?)");
                    $ins_log->execute([$stock_id, $log_type, $qty_change_log, $new_tot, $adj_reason ?: "Stock adjustment"]);

                    $pdo->commit();
                    $success_msg = "B-Grade stock for '" . htmlspecialchars($item['product_name']) . "' adjusted successfully!";
                } else {
                    $pdo->rollBack();
                    $error_msg = "B-Grade stock item not found.";
                }
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Error updating B-Grade stock: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please enter valid adjustment quantities.";
        }
    } elseif ($act === 'manual_add_b_grade' && isset($pdo)) {
        $p_name = trim($_POST['product_name'] ?? '');
        $f_color = trim($_POST['fabric_color'] ?? 'Standard');
        $qs = (int)($_POST['qty_s'] ?? 0);
        $qm = (int)($_POST['qty_m'] ?? 0);
        $ql = (int)($_POST['qty_l'] ?? 0);
        $qxl = (int)($_POST['qty_xl'] ?? 0);
        $qxxl = (int)($_POST['qty_xxl'] ?? 0);
        $tot_qty = $qs + $qm + $ql + $qxl + $qxxl;
        $note = trim($_POST['note'] ?? 'Manual B-grade stock addition');

        if ($p_name && $tot_qty > 0) {
            try {
                $pdo->beginTransaction();
                $check = $pdo->prepare("SELECT * FROM b_grade_stock WHERE product_name = ? AND fabric_color = ?");
                $check->execute([$p_name, $f_color]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $ns = (int)$existing['qty_s'] + $qs;
                    $nm = (int)$existing['qty_m'] + $qm;
                    $nl = (int)$existing['qty_l'] + $ql;
                    $nxl = (int)$existing['qty_xl'] + $qxl;
                    $nxxl = (int)$existing['qty_xxl'] + $qxxl;
                    $ntot = $ns + $nm + $nl + $nxl + $nxxl;

                    $upd = $pdo->prepare("UPDATE b_grade_stock SET qty_s = ?, qty_m = ?, qty_l = ?, qty_xl = ?, qty_xxl = ?, quantity = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
                    $upd->execute([$ns, $nm, $nl, $nxl, $nxxl, $ntot, $existing['id']]);
                    $b_id = $existing['id'];
                    $qty_after = $ntot;
                } else {
                    $ins = $pdo->prepare("INSERT INTO b_grade_stock (product_name, fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$p_name, $f_color, $qs, $qm, $ql, $qxl, $qxxl, $tot_qty]);
                    $b_id = $pdo->lastInsertId();
                    $qty_after = $tot_qty;
                }

                $ins_log = $pdo->prepare("INSERT INTO b_grade_stock_logs (b_grade_stock_id, action_type, qty_change, qty_after, note) VALUES (?, 'Manual Intake', ?, ?, ?)");
                $ins_log->execute([$b_id, $tot_qty, $qty_after, $note]);

                $pdo->commit();
                $success_msg = "B-Grade stock added successfully for '" . htmlspecialchars($p_name) . "'!";
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Error adding B-Grade stock: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please specify product name and quantities.";
        }
    }
}

// Fetch All B-Grade Stock Items with History Logs
$b_grade_items = [];
$total_b_grade_units = 0;
$in_stock_skus = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $b_grade_items = $pdo->query("SELECT * FROM b_grade_stock ORDER BY quantity DESC, last_updated DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($b_grade_items as &$item) {
            $qty = (int)$item['quantity'];
            $total_b_grade_units += $qty;
            if ($qty > 0) $in_stock_skus++;

            // Fetch recent logs
            $l_stmt = $pdo->prepare("SELECT action_type, qty_change, qty_after, note, created_at FROM b_grade_stock_logs WHERE b_grade_stock_id = ? ORDER BY created_at DESC LIMIT 5");
            $l_stmt->execute([$item['id']]);
            $item['logs'] = $l_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($item);
    } catch (\Exception $e) {}
}
?>

<div class="flex-1 flex overflow-hidden">
    <div id="bgrade-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-sm">
                        <i class="ti ti-boxes"></i>
                    </div>
                    <h1 class="text-2xl font-black text-gray-900">B-Grade Stock Management</h1>
                </div>
                <p class="text-sm text-gray-500 mt-1">Secondary quality &amp; factory-second finished goods inventory (kept separate from main stock).</p>
            </div>

            <div class="flex items-center gap-6">
                <!-- Stats -->
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= count($b_grade_items) ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Total SKUs</p>
                    </div>
                    <div class="w-px h-8 bg-gray-100 self-center"></div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-amber-600"><?= number_format($total_b_grade_units) ?> pcs</p>
                        <p class="text-[9px] font-bold text-amber-600 uppercase tracking-widest mt-0.5">Available Stock</p>
                    </div>
                    <div class="w-px h-8 bg-gray-100 self-center"></div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-emerald-600"><?= $in_stock_skus ?></p>
                        <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-widest mt-0.5">Active Lines</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <a href="/admin-damage-goods" class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-alert-triangle text-base text-red-500"></i> Quality / Damage Logs
                    </a>
                    <button onclick="downloadPDF('bgrade-table-card', 'B_Grade_Stock_Inventory_Report')" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-printer text-base"></i> Export PDF
                    </button>
                    <button onclick="openManualAddModal()" 
                        class="flex items-center gap-2 px-5 py-2.5 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition-all shadow-lg shadow-amber-600/20 active:scale-95">
                        <i class="ti ti-plus text-base"></i> Add B-Grade Stock
                    </button>
                </div>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="mx-8 mt-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-in fade-in">
                <div class="flex items-center gap-2.5">
                    <i class="ti ti-circle-check text-lg text-emerald-600"></i>
                    <span><?= htmlspecialchars($success_msg) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><i class="ti ti-x"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="mx-8 mt-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-in fade-in">
                <div class="flex items-center gap-2.5">
                    <i class="ti ti-alert-triangle text-lg text-red-600"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-800"><i class="ti ti-x"></i></button>
            </div>
        <?php endif; ?>

        <!-- Search Bar & Filters -->
        <div class="px-8 py-4 border-b border-gray-100 bg-gray-50/40 flex items-center justify-between gap-4">
            <div class="relative flex-1 group max-w-lg">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors text-sm"></i>
                <input id="bgrade-search" type="text" placeholder="Search B-grade inventory by product name or fabric color..." onkeyup="filterBGradeTable()"
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-all">
            </div>

            <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                <span class="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl flex items-center gap-1.5">
                    <i class="ti ti-info-circle"></i> Auto-synced from QC &amp; Damage Logs
                </span>
            </div>
        </div>

        <!-- Table Card -->
        <div class="flex-1 overflow-y-auto overflow-x-auto p-8" id="bgrade-table-card">
            <table class="w-full text-left border-separate" style="border-spacing: 0 6px;">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-wider bg-gray-50">
                        <th class="px-4 py-3 rounded-l-xl">Product Name</th>
                        <th class="px-4 py-3">Fabric Color</th>
                        <th class="px-2 py-3 text-center w-14">S</th>
                        <th class="px-2 py-3 text-center w-14">M</th>
                        <th class="px-2 py-3 text-center w-14">L</th>
                        <th class="px-2 py-3 text-center w-14">XL</th>
                        <th class="px-2 py-3 text-center w-14">XXL</th>
                        <th class="px-4 py-3 text-center w-28">Available Stock</th>
                        <th class="px-4 py-3 text-right">Last Updated</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Actions</th>
                    </tr>
                </thead>
                <tbody id="bgrade-tbody">
                    <?php if (empty($b_grade_items)): ?>
                        <tr>
                            <td colspan="10" class="py-14 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                <i class="ti ti-box-model-2-off text-4xl block mb-2 opacity-40"></i>
                                No B-Grade stock records found. When logging defect goods with type <strong>"B - grade"</strong>, they will automatically appear here.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($b_grade_items as $item): 
                            $qty = (int)$item['quantity'];
                            $itemJson = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="bgrade-row bg-white cursor-pointer hover:bg-amber-50/40 transition-all group shadow-xs border border-gray-100"
                                onclick="openBGradeDrawer(<?= $itemJson ?>)">
                                
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-amber-300 font-bold text-gray-900 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-xs">
                                            <i class="ti ti-shirt"></i>
                                        </div>
                                        <div>
                                            <span class="block font-black text-gray-900"><?= htmlspecialchars($item['product_name']) ?></span>
                                            <span class="text-[10px] text-gray-400 font-medium">B-Grade SKU #<?= $item['id'] ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4 border-y border-gray-100 text-xs font-bold text-gray-700">
                                    <?= htmlspecialchars($item['fabric_color']) ?>
                                </td>

                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$item['qty_s'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$item['qty_m'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$item['qty_l'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$item['qty_xl'] ?></td>
                                <td class="p-2 border-y border-gray-100 text-xs text-center text-gray-600 font-semibold"><?= (int)$item['qty_xxl'] ?></td>

                                <td class="p-4 border-y border-gray-100 text-xs text-center">
                                    <?php if ($qty > 0): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-black font-mono bg-amber-100 text-amber-800 border border-amber-200">
                                            <?= number_format($qty) ?> pcs
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-black font-mono bg-gray-100 text-gray-500 border border-gray-200">
                                            0 pcs
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 border-y border-gray-100 text-xs text-right text-gray-500 font-medium">
                                    <?= date('d M Y, h:i A', strtotime($item['last_updated'])) ?>
                                </td>

                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-amber-300 text-xs text-right">
                                    <div class="flex items-center justify-end gap-2" onclick="event.stopPropagation()">
                                        <button onclick="openAdjustModal(<?= $itemJson ?>)"
                                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-[11px] font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all flex items-center gap-1 shadow-xs">
                                            <i class="ti ti-adjustments-horizontal text-xs"></i> Adjust
                                        </button>
                                        <button onclick="openBGradeDrawer(<?= $itemJson ?>)"
                                            class="p-1.5 text-gray-400 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                                            <i class="ti ti-chevron-right text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: MANUAL ADD B-GRADE STOCK -->
<!-- ========================================================================= -->
<div id="manual-add-modal" class="hidden fixed inset-0 bg-black/60 z-50 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-xl w-full p-8 space-y-6 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-boxes"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-gray-900">Add B-Grade Stock</h2>
                    <p class="text-xs text-gray-400">Record secondary quality inventory directly to B-Grade stock.</p>
                </div>
            </div>
            <button onclick="closeManualAddModal()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="manual_add_b_grade">

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Product Name <span class="text-red-500">*</span></label>
                <input type="text" name="product_name" list="catalog-products-list" placeholder="Select or enter product name..." required
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-900 outline-none focus:bg-white focus:border-amber-500 transition-all">
                <datalist id="catalog-products-list">
                    <?php foreach ($catalog_products as $p): ?>
                        <option value="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['sku']) ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Fabric Color <span class="text-red-500">*</span></label>
                <input type="text" name="fabric_color" value="White" required
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-amber-500 transition-all">
            </div>

            <!-- Size Breakdown -->
            <div class="space-y-2 pt-2 border-t border-gray-100">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Quantity Breakdown (S, M, L, XL, XXL)</label>
                <div class="overflow-x-auto border border-gray-200 rounded-2xl bg-white">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2 px-3 text-center text-[10px] font-black text-gray-500 uppercase w-1/5">S</th>
                                <th class="py-2 px-3 text-center text-[10px] font-black text-gray-500 uppercase w-1/5">M</th>
                                <th class="py-2 px-3 text-center text-[10px] font-black text-gray-500 uppercase w-1/5">L</th>
                                <th class="py-2 px-3 text-center text-[10px] font-black text-gray-500 uppercase w-1/5">XL</th>
                                <th class="py-2 px-3 text-center text-[10px] font-black text-gray-500 uppercase w-1/5">XXL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="p-2"><input type="number" name="qty_s" id="m-s" value="0" min="0" oninput="recalcManualTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></td>
                                <td class="p-2"><input type="number" name="qty_m" id="m-m" value="0" min="0" oninput="recalcManualTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></td>
                                <td class="p-2"><input type="number" name="qty_l" id="m-l" value="0" min="0" oninput="recalcManualTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></td>
                                <td class="p-2"><input type="number" name="qty_xl" id="m-xl" value="0" min="0" oninput="recalcManualTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></td>
                                <td class="p-2"><input type="number" name="qty_xxl" id="m-xxl" value="0" min="0" oninput="recalcManualTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-xs font-bold text-amber-800">
                <span>Total B-Grade Pieces to Add:</span>
                <span id="m-total-display" class="font-black text-sm font-mono">0 pcs</span>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Intake Note / Reason</label>
                <input type="text" name="note" placeholder="e.g. Factory second batch intake from production run"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-amber-500 transition-all">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeManualAddModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-amber-600 text-white font-bold rounded-xl text-xs hover:bg-amber-700 transition-all shadow-md shadow-amber-600/20">Save B-Grade Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: ADJUST B-GRADE STOCK / CLEARANCE SALE -->
<!-- ========================================================================= -->
<div id="adjust-modal" class="hidden fixed inset-0 bg-black/60 z-50 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-xl w-full p-8 space-y-6 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-adjustments-horizontal"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-gray-900">Adjust B-Grade Stock</h2>
                    <p id="adj-product-title" class="text-xs text-gray-400 font-bold"></p>
                </div>
            </div>
            <button onclick="closeAdjustModal()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="adjust_b_grade_stock">
            <input type="hidden" name="stock_id" id="adj-stock-id">

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Adjustment Type <span class="text-red-500">*</span></label>
                <select name="adj_action" id="adj-action-select" required
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-amber-500 transition-all cursor-pointer">
                    <option value="clearance">Clearance Sale / Factory Store Sale (-)</option>
                    <option value="dispose">Scrap / Final Disposal (-)</option>
                    <option value="manual_add">Manual Restock / Inflow (+)</option>
                    <option value="correction">Inventory Audit Correction</option>
                </select>
            </div>

            <!-- Size Adjustment Inputs -->
            <div class="space-y-2 pt-2 border-t border-gray-100">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Quantities to Adjust</label>
                <div class="grid grid-cols-5 gap-2">
                    <div><label class="text-[10px] font-bold text-gray-400 text-center block mb-1">S</label><input type="number" name="qty_s" id="a-s" value="0" min="0" oninput="recalcAdjTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></div>
                    <div><label class="text-[10px] font-bold text-gray-400 text-center block mb-1">M</label><input type="number" name="qty_m" id="a-m" value="0" min="0" oninput="recalcAdjTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></div>
                    <div><label class="text-[10px] font-bold text-gray-400 text-center block mb-1">L</label><input type="number" name="qty_l" id="a-l" value="0" min="0" oninput="recalcAdjTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></div>
                    <div><label class="text-[10px] font-bold text-gray-400 text-center block mb-1">XL</label><input type="number" name="qty_xl" id="a-xl" value="0" min="0" oninput="recalcAdjTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></div>
                    <div><label class="text-[10px] font-bold text-gray-400 text-center block mb-1">XXL</label><input type="number" name="qty_xxl" id="a-xxl" value="0" min="0" oninput="recalcAdjTotal()" class="w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-amber-500"></div>
                </div>
            </div>

            <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-2xl border border-gray-200 text-xs font-bold text-gray-700">
                <span>Total Quantity to Adjust:</span>
                <span id="a-total-display" class="font-black text-sm font-mono text-gray-900">0 pcs</span>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider">Adjustment Reason / Notes <span class="text-red-500">*</span></label>
                <input type="text" name="adj_reason" placeholder="e.g. Sold 20 pcs at factory discount outlet / Recounted during audit" required
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-amber-500 transition-all">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeAdjustModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-gray-900 text-white font-bold rounded-xl text-xs hover:bg-black transition-all shadow-md shadow-gray-900/20">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SLIDE DRAWER: B-GRADE STOCK ITEM DETAILS & HISTORY -->
<!-- ========================================================================= -->
<div id="bgrade-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeBGradeDrawer()"></div>
<div id="bgrade-drawer" class="fixed inset-y-0 right-0 z-50 w-full sm:w-[540px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="bgrade-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start border-b border-gray-100 pb-4">
            <div>
                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">B-Grade SKU</span>
                <h2 id="bd-product-name" class="text-xl font-black text-gray-900 mt-2"></h2>
                <p id="bd-fabric-color" class="text-xs font-bold text-gray-500 mt-0.5"></p>
            </div>
            <button onclick="closeBGradeDrawer()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <div class="bg-amber-50 p-5 rounded-2xl border border-amber-200 space-y-3 text-xs">
            <div class="flex justify-between items-center"><span class="text-amber-900 font-medium">Total B-Grade Stock:</span><strong id="bd-total-qty" class="text-lg font-black font-mono text-amber-900"></strong></div>
            <div class="flex justify-between items-center"><span class="text-amber-800 font-medium">Last Inventory Update:</span><strong id="bd-updated" class="text-gray-900 font-bold"></strong></div>
        </div>

        <!-- Sizing Table Breakdown -->
        <section class="space-y-3">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Size Quantities Breakdown</h3>
            <div class="overflow-x-auto border border-gray-100 rounded-2xl shadow-xs">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="py-2.5 px-3">Fabric Color</th>
                            <th class="py-2.5 px-2 text-center">S</th>
                            <th class="py-2.5 px-2 text-center">M</th>
                            <th class="py-2.5 px-2 text-center">L</th>
                            <th class="py-2.5 px-2 text-center">XL</th>
                            <th class="py-2.5 px-2 text-center">XXL</th>
                            <th class="py-2.5 px-3 text-right">Available</th>
                        </tr>
                    </thead>
                    <tbody id="bd-breakdown-tbody" class="divide-y divide-gray-50"></tbody>
                </table>
            </div>
        </section>

        <!-- Stock History / Audit Logs -->
        <section class="space-y-3">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Recent Stock Intake &amp; Adjustment Logs</h3>
            <div id="bd-logs-container" class="space-y-2.5"></div>
        </section>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
        <button id="bd-adjust-btn" class="flex-1 px-4 py-2.5 bg-amber-600 text-white font-bold rounded-xl text-xs hover:bg-amber-700 transition-all flex items-center justify-center gap-2 shadow-sm">
            <i class="ti ti-adjustments-horizontal text-base"></i> Adjust This Item
        </button>
        <button onclick="downloadPDF('bgrade-drawer-content', 'B_Grade_SKU_Report')" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all flex items-center gap-2 shadow-xs">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
let currentActiveDrawerItem = null;

function openManualAddModal() {
    document.getElementById('manual-add-modal').classList.remove('hidden');
    recalcManualTotal();
}
function closeManualAddModal() {
    document.getElementById('manual-add-modal').classList.add('hidden');
}

function openAdjustModal(item) {
    document.getElementById('adj-stock-id').value = item.id;
    document.getElementById('adj-product-title').textContent = item.product_name + ' (' + item.fabric_color + ') - Available: ' + item.quantity + ' pcs';
    
    document.getElementById('a-s').value = 0;
    document.getElementById('a-m').value = 0;
    document.getElementById('a-l').value = 0;
    document.getElementById('a-xl').value = 0;
    document.getElementById('a-xxl').value = 0;
    recalcAdjTotal();

    document.getElementById('adjust-modal').classList.remove('hidden');
}
function closeAdjustModal() {
    document.getElementById('adjust-modal').classList.add('hidden');
}

function recalcManualTotal() {
    var qs = parseInt(document.getElementById('m-s')?.value || 0) || 0;
    var qm = parseInt(document.getElementById('m-m')?.value || 0) || 0;
    var ql = parseInt(document.getElementById('m-l')?.value || 0) || 0;
    var qxl = parseInt(document.getElementById('m-xl')?.value || 0) || 0;
    var qxxl = parseInt(document.getElementById('m-xxl')?.value || 0) || 0;
    var tot = qs + qm + ql + qxl + qxxl;
    document.getElementById('m-total-display').textContent = tot + ' pcs';
}

function recalcAdjTotal() {
    var qs = parseInt(document.getElementById('a-s')?.value || 0) || 0;
    var qm = parseInt(document.getElementById('a-m')?.value || 0) || 0;
    var ql = parseInt(document.getElementById('a-l')?.value || 0) || 0;
    var qxl = parseInt(document.getElementById('a-xl')?.value || 0) || 0;
    var qxxl = parseInt(document.getElementById('a-xxl')?.value || 0) || 0;
    var tot = qs + qm + ql + qxl + qxxl;
    document.getElementById('a-total-display').textContent = tot + ' pcs';
}

function openBGradeDrawer(item) {
    currentActiveDrawerItem = item;
    document.getElementById('bd-product-name').textContent = item.product_name;
    document.getElementById('bd-fabric-color').textContent = 'Fabric Color: ' + item.fabric_color;
    document.getElementById('bd-total-qty').textContent = item.quantity + ' pcs';
    document.getElementById('bd-updated').textContent = item.last_updated;

    document.getElementById('bd-adjust-btn').onclick = () => {
        closeBGradeDrawer();
        openAdjustModal(item);
    };

    var qs = parseInt(item.qty_s || 0) || 0;
    var qm = parseInt(item.qty_m || 0) || 0;
    var ql = parseInt(item.qty_l || 0) || 0;
    var qxl = parseInt(item.qty_xl || 0) || 0;
    var qxxl = parseInt(item.qty_xxl || 0) || 0;
    var tot = parseInt(item.quantity || 0) || 0;

    document.getElementById('bd-breakdown-tbody').innerHTML = `
        <tr>
            <td class="py-3 px-3 font-bold text-gray-900">${escapeHtml(item.fabric_color)}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qs}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qm}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${ql}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxl}</td>
            <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxxl}</td>
            <td class="py-3 px-3 text-right font-black text-amber-700">${tot} pcs</td>
        </tr>
    `;

    // Render Logs
    const logs = item.logs || [];
    if (logs.length > 0) {
        document.getElementById('bd-logs-container').innerHTML = logs.map(l => {
            const chg = parseInt(l.qty_change);
            const sign = chg >= 0 ? '+' : '';
            const colorClass = chg >= 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-red-700 bg-red-50 border-red-200';
            return `
                <div class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs space-y-1">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-900">${escapeHtml(l.action_type)}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black border ${colorClass}">${sign}${chg} pcs</span>
                    </div>
                    <p class="text-gray-500 font-medium text-[11px]">${escapeHtml(l.note || '')}</p>
                    <span class="text-[10px] text-gray-400 block">${l.created_at}</span>
                </div>
            `;
        }).join('');
    } else {
        document.getElementById('bd-logs-container').innerHTML = `
            <div class="p-4 text-center text-gray-400 text-xs bg-gray-50 rounded-xl">No adjustment logs recorded yet.</div>
        `;
    }

    document.getElementById('bgrade-drawer-backdrop').classList.remove('hidden');
    document.getElementById('bgrade-drawer').classList.remove('translate-x-full');
}

function closeBGradeDrawer() {
    document.getElementById('bgrade-drawer-backdrop').classList.add('hidden');
    document.getElementById('bgrade-drawer').classList.add('translate-x-full');
}

function filterBGradeTable() {
    var q = document.getElementById('bgrade-search').value.toLowerCase().trim();
    document.querySelectorAll('#bgrade-tbody tr.bgrade-row').forEach(row => {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
