<?php
/**
 * Raw Materials Cutting View
 * Standard template view with breakdown by Fabric Color | S | M | L | XL | XXL | Qty | Total
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Tables Creation
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS raw_material_cuttings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cut_number VARCHAR(100) NOT NULL,
            raw_material VARCHAR(255) NOT NULL,
            cutting_date DATE NOT NULL,
            total_pieces INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS raw_material_cutting_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cutting_id INT NOT NULL,
            fabric_color VARCHAR(50) NOT NULL,
            qty_s INT DEFAULT 0,
            qty_m INT DEFAULT 0,
            qty_l INT DEFAULT 0,
            qty_xl INT DEFAULT 0,
            qty_xxl INT DEFAULT 0,
            quantity INT NOT NULL,
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            total_cost DECIMAL(10,2) DEFAULT 0.00,
            FOREIGN KEY (cutting_id) REFERENCES raw_material_cuttings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns if table pre-existed
        $cols = ['qty_s', 'qty_m', 'qty_l', 'qty_xl', 'qty_xxl', 'unit_cost', 'total_cost'];
        foreach ($cols as $col) {
            $chk = $pdo->query("SHOW COLUMNS FROM raw_material_cutting_items LIKE '$col'");
            if (!$chk->fetch()) {
                $type = str_contains($col, 'cost') ? "DECIMAL(10,2) DEFAULT 0.00" : "INT DEFAULT 0";
                $pdo->exec("ALTER TABLE raw_material_cutting_items ADD COLUMN $col $type");
            }
        }
    } catch (\Exception $e) {
        // Tables exist
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'add_cutting' && isset($pdo)) {
        $cut_number = trim($_POST['cut_number'] ?? '');
        $raw_material = trim($_POST['raw_material'] ?? '');
        $cutting_date = $_POST['cutting_date'] ?? date('Y-m-d');
        
        $colors = $_POST['colors'] ?? [];
        $s_arr  = $_POST['qty_s'] ?? [];
        $m_arr  = $_POST['qty_m'] ?? [];
        $l_arr  = $_POST['qty_l'] ?? [];
        $xl_arr = $_POST['qty_xl'] ?? [];
        $xxl_arr= $_POST['qty_xxl'] ?? [];
        $cost_arr= $_POST['unit_cost'] ?? [];

        $total_pieces = 0;
        for ($i = 0; $i < count($colors); $i++) {
            $row_qty = (int)($s_arr[$i] ?? 0) + (int)($m_arr[$i] ?? 0) + (int)($l_arr[$i] ?? 0) + (int)($xl_arr[$i] ?? 0) + (int)($xxl_arr[$i] ?? 0);
            $total_pieces += $row_qty;
        }

        if ($cut_number && $raw_material && $total_pieces > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO raw_material_cuttings (cut_number, raw_material, cutting_date, total_pieces, status) VALUES (?, ?, ?, ?, 'Pending')");
                $stmt->execute([$cut_number, $raw_material, $cutting_date, $total_pieces]);
                $cut_id = $pdo->lastInsertId();

                $item_stmt = $pdo->prepare("INSERT INTO raw_material_cutting_items (cutting_id, fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($colors); $i++) {
                    if (empty(trim($colors[$i]))) continue;
                    $qs = (int)($s_arr[$i] ?? 0);
                    $qm = (int)($m_arr[$i] ?? 0);
                    $ql = (int)($l_arr[$i] ?? 0);
                    $qxl = (int)($xl_arr[$i] ?? 0);
                    $qxxl = (int)($xxl_arr[$i] ?? 0);
                    $rqty = $qs + $qm + $ql + $qxl + $qxxl;
                    $ucost = (float)($cost_arr[$i] ?? 0);
                    $tcost = $rqty * $ucost;

                    $item_stmt->execute([$cut_id, trim($colors[$i]), $qs, $qm, $ql, $qxl, $qxxl, $rqty, $ucost, $tcost]);
                }
                $pdo->commit();
                $success_msg = "Raw material cutting batch " . htmlspecialchars($cut_number) . " saved successfully!";
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Error saving cutting entry: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please enter valid cutting details and at least one item row with quantity.";
        }
    } elseif ($act === 'approve_cutting' && isset($_POST['cutting_id']) && isset($pdo)) {
        $c_id = (int) $_POST['cutting_id'];
        try {
            $stmt = $pdo->prepare("UPDATE raw_material_cuttings SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$c_id]);
            $success_msg = "Cutting batch approved successfully!";
        } catch (\Exception $e) {
            $error_msg = "Error approving cutting batch: " . $e->getMessage();
        }
    }
}

// Fetch Cutting Records
$cuttings = [];
$total_pcs = 0;
$approved_count = 0;
$pending_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $cuttings_db = $pdo->query("SELECT * FROM raw_material_cuttings ORDER BY cutting_date DESC, id DESC")->fetchAll();
        foreach ($cuttings_db as $c) {
            $item_stmt = $pdo->prepare("SELECT fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, unit_cost, total_cost FROM raw_material_cutting_items WHERE cutting_id = ?");
            $item_stmt->execute([$c['id']]);
            $items = $item_stmt->fetchAll();

            $total_pcs += (int)$c['total_pieces'];
            if ($c['status'] === 'Approved') $approved_count++;
            else $pending_count++;

            $cuttings[] = [
                'id' => $c['id'],
                'cut_number' => $c['cut_number'],
                'raw_material' => $c['raw_material'],
                'cutting_date' => date('d M Y', strtotime($c['cutting_date'])),
                'total_pieces' => (int) $c['total_pieces'],
                'status' => $c['status'],
                'items' => json_encode($items)
            ];
        }
    } catch (\Exception $e) {}
}
?>

<div class="flex-1 flex overflow-hidden">
    <div id="cutting-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Raw Materials Cutting View</h1>
                <p class="text-sm text-gray-500 mt-1">Standalone cutting batch execution &amp; output tracking (Disconnected from stock)</p>
            </div>

            <div class="flex items-center gap-6">
                <!-- Stats -->
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= count($cuttings) ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Batches</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-brand"><?= number_format($total_pcs) ?> pcs</p>
                        <p class="text-[9px] font-bold text-brand uppercase tracking-widest mt-0.5">Output</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-emerald-600"><?= $approved_count ?></p>
                        <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mt-0.5">Approved</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-amber-600"><?= $pending_count ?></p>
                        <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest mt-0.5">Pending</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <button onclick="downloadPDF('cutting-table-card', 'Raw_Materials_Cutting_Report')" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-printer text-lg"></i> Export PDF
                    </button>
                    <button onclick="openCuttingModal()" 
                        class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        <i class="ti ti-scissors text-lg"></i> Create Cutting Batch
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
                <input id="cutting-search" type="text" placeholder="Search by Cut number or raw material..." onkeyup="filterCuttingTable()"
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all">
            </div>
        </div>

        <!-- Table Card -->
        <div class="flex-1 overflow-y-auto overflow-x-auto p-8" id="cutting-table-card">
            <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-4 py-3 rounded-l-xl">Cut Number</th>
                        <th class="px-4 py-3">Raw Material Fabric</th>
                        <th class="px-4 py-3">Cutting Date</th>
                        <th class="px-4 py-3 text-center">Output Pieces</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Status</th>
                    </tr>
                </thead>
                <tbody id="cutting-tbody">
                    <?php if (empty($cuttings)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                No cutting records available. Click "+ Create Cutting Batch" to start.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuttings as $c): ?>
                            <tr class="cutting-row bg-white cursor-pointer hover:bg-gray-50/60 transition-all group shadow-sm"
                                onclick="openCuttingDrawer(<?= htmlspecialchars(json_encode($c)) ?>)">
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 font-bold text-brand text-xs">
                                    <?= htmlspecialchars($c['cut_number']) ?>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-bold text-gray-900">
                                    <?= htmlspecialchars($c['raw_material']) ?>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-gray-500 font-medium">
                                    <?= htmlspecialchars($c['cutting_date']) ?>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-center font-extrabold text-gray-900">
                                    <?= number_format($c['total_pieces']) ?> pcs
                                </td>
                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-xs text-right">
                                    <?php if ($c['status'] === 'Approved'): ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">Approved</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase">Pending</span>
                                    <?php endif; ?>
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
<div id="cutting-modal" class="hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-4xl w-full p-8 space-y-6 max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
                <i class="ti ti-scissors text-brand text-xl"></i> Create Cutting Batch
            </h2>
            <button onclick="closeCuttingModal()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="add_cutting">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cut Number <span class="text-red-500">*</span></label>
                    <input type="text" name="cut_number" placeholder="e.g. CUT-2025-089" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Raw Material Fabric <span class="text-red-500">*</span></label>
                    <input type="text" name="raw_material" placeholder="e.g. Cotton White Roll #40" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cutting Date <span class="text-red-500">*</span></label>
                    <input type="date" name="cutting_date" value="<?= date('Y-m-d') ?>" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 transition-all">
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cutting Items Breakdown</label>
                    <button type="button" onclick="addCuttingRowModal()"
                        class="px-3 py-1.5 bg-brand/10 text-brand border border-brand/20 rounded-xl text-xs font-bold hover:bg-brand hover:text-white transition-all">
                        + Add Row
                    </button>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-2xl bg-white">
                    <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                        <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Fabric Color</th>
                                <th class="py-2.5 px-2 text-center w-14">S</th>
                                <th class="py-2.5 px-2 text-center w-14">M</th>
                                <th class="py-2.5 px-2 text-center w-14">L</th>
                                <th class="py-2.5 px-2 text-center w-14">XL</th>
                                <th class="py-2.5 px-2 text-center w-14">XXL</th>
                                <th class="py-2.5 px-3 text-center w-20">Qty</th>
                                <th class="py-2.5 px-3 text-right w-28">Total</th>
                                <th class="py-2.5 px-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="cutting-rows-container-modal" class="divide-y divide-gray-100">
                            <tr>
                                <td class="p-2"><input type="text" name="colors[]" placeholder="Color" required value="White" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none"></td>
                                <td class="p-2"><input type="number" name="qty_s[]" value="0" min="0" oninput="recalcModalTotal()" class="c-s w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
                                <td class="p-2"><input type="number" name="qty_m[]" value="50" min="0" oninput="recalcModalTotal()" class="c-m w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
                                <td class="p-2"><input type="number" name="qty_l[]" value="50" min="0" oninput="recalcModalTotal()" class="c-l w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
                                <td class="p-2"><input type="number" name="qty_xl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
                                <td class="p-2"><input type="number" name="qty_xxl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xxl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
                                <td class="p-2 text-center font-extrabold text-gray-900"><span class="c-modal-row-qty">100</span></td>
                                <td class="p-2 text-right"><span class="c-modal-row-total font-extrabold text-brand">100 pcs</span></td>
                                <td class="p-2 text-center"><button type="button" onclick="removeCuttingRowModal(this)" class="p-1 text-gray-400 hover:text-red-600"><i class="ti ti-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold border-t border-gray-200 text-xs">
                            <tr>
                                <td colspan="7" class="py-2.5 px-3 text-right text-gray-600">Grand Total Output:</td>
                                <td id="c-modal-grand-total" class="py-2.5 px-3 text-right text-brand font-black text-xs">100 pcs</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeCuttingModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light font-bold rounded-xl text-xs hover:opacity-90 transition-all shadow-md shadow-brand/20">Save Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Slide Drawer -->
<div id="cutting-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeCuttingDrawer()"></div>
<div id="cutting-drawer" class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="cutting-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 id="cd-cut-num" class="text-xl font-black text-gray-900 tracking-tight"></h2>
                <p id="cd-mat" class="text-xs font-semibold text-gray-500 mt-1"></p>
            </div>
            <button onclick="closeCuttingDrawer()" class="p-1 text-gray-400 hover:text-gray-900"><i class="ti ti-x text-xl"></i></button>
        </div>

        <div class="flex items-center justify-between bg-gray-50 p-4 rounded-2xl border border-gray-100 text-xs">
            <span class="text-gray-500 font-medium">Cutting Date: <strong id="cd-date" class="text-gray-900"></strong></span>
            <span id="cd-status-badge" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase"></span>
        </div>

        <section class="space-y-3">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cut Pieces Breakdown</h3>
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
                    <tbody id="cd-items-tbody" class="divide-y divide-gray-50"></tbody>
                    <tfoot class="bg-gray-50 font-bold border-t border-gray-200">
                        <tr>
                            <td colspan="6" class="py-3 px-3 text-right text-gray-600">Total Output:</td>
                            <td id="cd-total-pcs" class="py-3 px-3 text-center text-brand font-black text-sm">0 pcs</td>
                            <td id="cd-total-cost" class="py-3 px-3 text-right text-emerald-700 font-black text-sm">LKR 0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
        <form method="POST" action="" id="approve-cutting-form" class="flex-1">
            <input type="hidden" name="action" value="approve_cutting">
            <input type="hidden" name="cutting_id" id="cd-cutting-id">
            <button type="submit" id="cd-approve-btn" class="w-full px-4 py-3 bg-emerald-600 text-white font-bold rounded-2xl text-xs hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 shadow-md shadow-emerald-600/10">
                <i class="ti ti-check text-base"></i> Approved
            </button>
        </form>
        <button onclick="downloadPDF('cutting-drawer-content', 'Cutting_Batch_Report')" class="px-4 py-3 bg-white border border-gray-200 text-gray-700 font-bold rounded-2xl text-xs hover:bg-gray-50 transition-all flex items-center gap-2">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
function openCuttingModal() { 
    document.getElementById('cutting-modal').classList.remove('hidden'); 
    recalcModalTotal();
}
function closeCuttingModal() { document.getElementById('cutting-modal').classList.add('hidden'); }

function addCuttingRowModal() {
    var container = document.getElementById('cutting-rows-container-modal');
    var tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-2"><input type="text" name="colors[]" placeholder="Color" required value="White" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none"></td>
        <td class="p-2"><input type="number" name="qty_s[]" value="0" min="0" oninput="recalcModalTotal()" class="c-s w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
        <td class="p-2"><input type="number" name="qty_m[]" value="0" min="0" oninput="recalcModalTotal()" class="c-m w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
        <td class="p-2"><input type="number" name="qty_l[]" value="0" min="0" oninput="recalcModalTotal()" class="c-l w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
        <td class="p-2"><input type="number" name="qty_xl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
        <td class="p-2"><input type="number" name="qty_xxl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xxl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none"></td>
        <td class="p-2 text-center font-extrabold text-gray-900"><span class="c-modal-row-qty">0</span></td>
        <td class="p-2 text-right"><span class="c-modal-row-total font-extrabold text-brand">0 pcs</span></td>
        <td class="p-2 text-center"><button type="button" onclick="removeCuttingRowModal(this)" class="p-1 text-gray-400 hover:text-red-600"><i class="ti ti-trash"></i></button></td>
    `;
    container.appendChild(tr);
    recalcModalTotal();
}

function removeCuttingRowModal(btn) {
    var rows = document.getElementById('cutting-rows-container-modal').children;
    if (rows.length > 1) {
        btn.closest('tr').remove();
        recalcModalTotal();
    } else {
        alert("At least one cutting item row is required.");
    }
}

function recalcModalTotal() {
    var grandQty = 0;
    document.querySelectorAll('#cutting-rows-container-modal tr').forEach(tr => {
        var qs = parseInt(tr.querySelector('.c-s')?.value || 0) || 0;
        var qm = parseInt(tr.querySelector('.c-m')?.value || 0) || 0;
        var ql = parseInt(tr.querySelector('.c-l')?.value || 0) || 0;
        var qxl = parseInt(tr.querySelector('.c-xl')?.value || 0) || 0;
        var qxxl = parseInt(tr.querySelector('.c-xxl')?.value || 0) || 0;
        var rowSum = qs + qm + ql + qxl + qxxl;

        var rowQtySpan = tr.querySelector('.c-modal-row-qty');
        if (rowQtySpan) rowQtySpan.textContent = rowSum;

        var rowTotalSpan = tr.querySelector('.c-modal-row-total');
        if (rowTotalSpan) rowTotalSpan.textContent = rowSum + ' pcs';

        grandQty += rowSum;
    });
    document.getElementById('c-modal-grand-total').textContent = grandQty + ' pcs';
}

function openCuttingDrawer(data) {
    document.getElementById('cd-cut-num').textContent = data.cut_number;
    document.getElementById('cd-mat').textContent = data.raw_material;
    document.getElementById('cd-date').textContent = data.cutting_date;
    document.getElementById('cd-cutting-id').value = data.id;

    var badge = document.getElementById('cd-status-badge');
    badge.textContent = data.status;
    if (data.status === 'Approved') {
        badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase';
        document.getElementById('cd-approve-btn').classList.add('hidden');
    } else {
        badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase';
        document.getElementById('cd-approve-btn').classList.remove('hidden');
    }

    var items = [];
    try { items = JSON.parse(data.items || '[]'); } catch(e){}
    var sumPcs = 0;
    var sumCost = 0;
    document.getElementById('cd-items-tbody').innerHTML = items.map(it => {
        var qs = parseInt(it.qty_s || 0) || 0;
        var qm = parseInt(it.qty_m || 0) || 0;
        var ql = parseInt(it.qty_l || 0) || 0;
        var qxl = parseInt(it.qty_xl || 0) || 0;
        var qxxl = parseInt(it.qty_xxl || 0) || 0;
        var qty = parseInt(it.quantity || (qs + qm + ql + qxl + qxxl)) || 0;
        var tcost = parseFloat(it.total_cost || 0) || 0;

        sumPcs += qty;
        sumCost += tcost;

        return `
            <tr>
                <td class="py-3 px-3 font-bold text-gray-900">${it.fabric_color}</td>
                <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qs}</td>
                <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qm}</td>
                <td class="py-3 px-2 text-center text-gray-600 font-semibold">${ql}</td>
                <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxl}</td>
                <td class="py-3 px-2 text-center text-gray-600 font-semibold">${qxxl}</td>
                <td class="py-3 px-3 text-center font-black text-brand">${qty} pcs</td>
                <td class="py-3 px-3 text-right font-extrabold text-gray-900">${qty} pcs</td>
            </tr>
        `;
    }).join('');
    document.getElementById('cd-total-pcs').textContent = sumPcs + ' pcs';
    document.getElementById('cd-total-cost').textContent = sumPcs + ' pcs';

    document.getElementById('cutting-drawer-backdrop').classList.remove('hidden');
    document.getElementById('cutting-drawer').classList.remove('translate-x-full');
}

function closeCuttingDrawer() {
    document.getElementById('cutting-drawer-backdrop').classList.add('hidden');
    document.getElementById('cutting-drawer').classList.add('translate-x-full');
}

function filterCuttingTable() {
    var q = document.getElementById('cutting-search').value.toLowerCase().trim();
    document.querySelectorAll('#cutting-tbody tr.cutting-row').forEach(row => {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
