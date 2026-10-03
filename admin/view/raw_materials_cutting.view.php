<?php
/**
 * Raw Materials Cutting View
 * Features:
 * 1. Product Name assignment (Replaces old Raw Material Fabric field).
 * 2. Cutting Items Breakdown by Fabric Color & Sizes (S, M, L, XL, XXL).
 * 3. Dynamic "Raw Materials" cost allocation section linked directly to Raw Materials Average Costs.
 * 4. Automatic Batch Total Cost & Unit Cost Per Piece computation.
 * 5. Batch Approval and Printable PDF execution.
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Tables Creation & Column Expansion
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS raw_material_cuttings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cut_number VARCHAR(100) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            raw_material VARCHAR(255) NULL,
            cutting_date DATE NOT NULL,
            total_pieces INT DEFAULT 0,
            total_cost DECIMAL(10,2) DEFAULT 0.00,
            cost_per_piece DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(50) DEFAULT 'Pending',
            raw_materials_json LONGTEXT NULL,
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

        // Add missing columns if tables pre-existed
        $cuttingCols = [
            'product_name' => "VARCHAR(255) NULL AFTER cut_number",
            'total_cost' => "DECIMAL(10,2) DEFAULT 0.00 AFTER total_pieces",
            'cost_per_piece' => "DECIMAL(10,2) DEFAULT 0.00 AFTER total_cost",
            'raw_materials_json' => "LONGTEXT NULL AFTER status"
        ];
        foreach ($cuttingCols as $cCol => $cType) {
            $chk = $pdo->query("SHOW COLUMNS FROM raw_material_cuttings LIKE '$cCol'");
            if (!$chk->fetch()) {
                $pdo->exec("ALTER TABLE raw_material_cuttings ADD COLUMN $cCol $cType");
            }
        }

        // Backfill product_name if empty
        $pdo->exec("UPDATE raw_material_cuttings SET product_name = raw_material WHERE (product_name IS NULL OR product_name = '') AND raw_material IS NOT NULL");
    } catch (\Exception $e) {
        // Tables exist
    }
}

// Fetch Master Products Catalog for Product Name Suggestions
$catalog_products = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $catalog_products = $pdo->query("SELECT id, name, sku FROM products WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}
}

// Master 10 Manufacturing Component Boxes and Materials
$master_material_boxes = [
    'Fabric' => ['Lycra', 'Single Jersey'],
    'Thread' => ['yarn 2500', 'yarn 5000', 'Cotton 2500'],
    'Elastic' => ['1" inches white 33m', '3/4" inches white 33m', '1 1/4" inches white 33m', '1/4" inches black 33m bobbin', 'Gold Jacquard 33m', 'Ex Jacquard 33m'],
    'Fabric Printing' => ['Front', 'Back', 'Flower'],
    'Cutting' => ['Cutting'],
    'Corrugated Box' => ['Corrugated Box per piece'],
    '2pc Box' => ['2pc Box per piece'],
    'Transport' => ['Transport'],
    'Bank Interest' => ['Bank Interest'],
    'Sewing' => ['Sewing']
];

// Fetch Raw Materials 5-Batch Average Cost Engine from raw_materials Table
$raw_material_averages = [];
$raw_material_options_list = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $rm_rows = $pdo->query("SELECT * FROM raw_materials ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $grouped_rms = [];
        foreach ($rm_rows as $row) {
            $cat = $row['material_category'];
            $mat = $row['material_name'];
            $keyFull = $cat . ' - ' . $mat;
            $keyMat = $mat;
            $keyCat = $cat;

            if (!isset($grouped_rms[$keyFull])) $grouped_rms[$keyFull] = [];
            if (!isset($grouped_rms[$keyMat])) $grouped_rms[$keyMat] = [];
            if (!isset($grouped_rms[$keyCat])) $grouped_rms[$keyCat] = [];

            $price = (float)($row['unit_price'] > 0 ? $row['unit_price'] : $row['weight']);
            $grouped_rms[$keyFull][] = $price;
            $grouped_rms[$keyMat][] = $price;
            $grouped_rms[$keyCat][] = $price;
        }

        foreach ($grouped_rms as $key => $prices) {
            $recent_5 = array_slice($prices, 0, 5);
            $avg = count($recent_5) > 0 ? (array_sum($recent_5) / count($recent_5)) : 0.00;
            $raw_material_averages[$key] = round($avg, 2);
        }
    } catch (\Exception $e) {}
}

// Build unified suggestions list for raw materials
foreach ($master_material_boxes as $boxCat => $boxMats) {
    foreach ($boxMats as $bMat) {
        $fullLabel = $boxCat . ' - ' . $bMat;
        $cost = $raw_material_averages[$fullLabel] ?? $raw_material_averages[$bMat] ?? 0.00;
        $raw_material_options_list[$fullLabel] = $cost;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'add_cutting' && isset($pdo)) {
        $cut_number   = trim($_POST['cut_number'] ?? '');
        $product_name = trim($_POST['product_name'] ?? '');
        $cutting_date = $_POST['cutting_date'] ?? date('Y-m-d');
        
        // Colors and Sizes
        $colors = $_POST['colors'] ?? [];
        $s_arr  = $_POST['qty_s'] ?? [];
        $m_arr  = $_POST['qty_m'] ?? [];
        $l_arr  = $_POST['qty_l'] ?? [];
        $xl_arr = $_POST['qty_xl'] ?? [];
        $xxl_arr= $_POST['qty_xxl'] ?? [];

        // Raw Materials Breakdown Section
        $rm_names = $_POST['rm_names'] ?? [];
        $rm_costs = $_POST['rm_costs'] ?? [];
        $rm_qtys  = $_POST['rm_qtys'] ?? [];

        $total_pieces = 0;
        for ($i = 0; $i < count($colors); $i++) {
            $row_qty = (int)($s_arr[$i] ?? 0) + (int)($m_arr[$i] ?? 0) + (int)($l_arr[$i] ?? 0) + (int)($xl_arr[$i] ?? 0) + (int)($xxl_arr[$i] ?? 0);
            $total_pieces += $row_qty;
        }

        // Calculate total raw material cost
        $total_batch_cost = 0.00;
        $rm_items_structured = [];
        for ($k = 0; $k < count($rm_names); $k++) {
            $m_name = trim($rm_names[$k] ?? '');
            if (empty($m_name)) continue;
            $m_cost = (float)($rm_costs[$k] ?? 0);
            $m_qty  = (float)($rm_qtys[$k] ?? $total_pieces);
            $line_t = $m_cost * $m_qty;
            $total_batch_cost += $line_t;

            $rm_items_structured[] = [
                'name' => $m_name,
                'unit_cost' => $m_cost,
                'qty' => $m_qty,
                'total_cost' => $line_t
            ];
        }

        $cost_per_piece = $total_pieces > 0 ? ($total_batch_cost / $total_pieces) : 0.00;
        $raw_materials_json = json_encode($rm_items_structured);

        if ($cut_number && $product_name && $total_pieces > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO raw_material_cuttings (cut_number, product_name, raw_material, cutting_date, total_pieces, total_cost, cost_per_piece, status, raw_materials_json) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
                $stmt->execute([$cut_number, $product_name, $product_name, $cutting_date, $total_pieces, $total_batch_cost, $cost_per_piece, $raw_materials_json]);
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
                    $item_unit_cost = $cost_per_piece;
                    $item_total_cost = $rqty * $item_unit_cost;

                    $item_stmt->execute([$cut_id, trim($colors[$i]), $qs, $qm, $ql, $qxl, $qxxl, $rqty, $item_unit_cost, $item_total_cost]);
                }
                $pdo->commit();
                $success_msg = "Cutting batch " . htmlspecialchars($cut_number) . " for '" . htmlspecialchars($product_name) . "' saved successfully!";
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Error saving cutting entry: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please enter valid Cut Number, Product Name, and at least one item row with quantity.";
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
$total_batch_expenses = 0.00;
$approved_count = 0;
$pending_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $cuttings_db = $pdo->query("SELECT * FROM raw_material_cuttings ORDER BY cutting_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cuttings_db as $c) {
            $item_stmt = $pdo->prepare("SELECT fabric_color, qty_s, qty_m, qty_l, qty_xl, qty_xxl, quantity, unit_cost, total_cost FROM raw_material_cutting_items WHERE cutting_id = ?");
            $item_stmt->execute([$c['id']]);
            $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

            $p_name = $c['product_name'] ?: ($c['raw_material'] ?: 'Underwear Batch');
            $pcs = (int)$c['total_pieces'];
            $b_cost = (float)($c['total_cost'] ?? 0.00);
            $cpp = (float)($c['cost_per_piece'] ?? ($pcs > 0 ? $b_cost / $pcs : 0.00));

            $total_pcs += $pcs;
            $total_batch_expenses += $b_cost;

            if ($c['status'] === 'Approved') $approved_count++;
            else $pending_count++;

            $cuttings[] = [
                'id' => $c['id'],
                'cut_number' => $c['cut_number'],
                'product_name' => $p_name,
                'cutting_date' => date('d M Y', strtotime($c['cutting_date'])),
                'total_pieces' => $pcs,
                'total_cost' => $b_cost,
                'cost_per_piece' => $cpp,
                'status' => $c['status'],
                'raw_materials_json' => $c['raw_materials_json'] ?? '[]',
                'items' => json_encode($items)
            ];
        }
    } catch (\Exception $e) {}
}

// Generate Next Suggested Cut Number (e.g. CUT-2026-001)
$nextCutNumber = 'CUT-' . date('Y') . '-' . str_pad(count($cuttings) + 1, 3, '0', STR_PAD_LEFT);
?>

<div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-y-auto overflow-x-hidden no-scrollbar">
    
    <!-- TOP HEADER -->
    <div class="px-8 py-6 bg-white border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 sticky top-0 z-20 shadow-xs">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand/10 text-brand flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-scissors"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-gray-900 tracking-tight">Raw Materials Cutting View</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Production cutting batch execution, sizing breakdown &amp; live raw material costing ledger.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <!-- Stats -->
            <div class="hidden sm:flex items-center gap-4 bg-gray-50 px-4 py-2 rounded-2xl border border-gray-100">
                <div class="text-center px-2">
                    <p class="text-sm font-black text-gray-900"><?= count($cuttings) ?></p>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Batches</p>
                </div>
                <div class="w-px h-6 bg-gray-200"></div>
                <div class="text-center px-2">
                    <p class="text-sm font-black text-brand"><?= number_format($total_pcs) ?> pcs</p>
                    <p class="text-[9px] font-bold text-brand uppercase tracking-widest">Total Output</p>
                </div>
                <div class="w-px h-6 bg-gray-200"></div>
                <div class="text-center px-2">
                    <p class="text-sm font-black text-emerald-600"><?= $approved_count ?></p>
                    <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-widest">Approved</p>
                </div>
                <div class="w-px h-6 bg-gray-200"></div>
                <div class="text-center px-2">
                    <p class="text-sm font-black text-amber-600"><?= $pending_count ?></p>
                    <p class="text-[9px] font-bold text-amber-600 uppercase tracking-widest">Pending</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="downloadPDF('cutting-table-card', 'Raw_Materials_Cutting_Report')" 
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all shadow-sm">
                    <i class="ti ti-printer text-base"></i> Export PDF
                </button>
                <button onclick="openCuttingModal()" 
                    class="flex items-center gap-2 px-5 py-2.5 bg-brand text-white rounded-xl text-xs font-bold hover:bg-brand-dark transition-all shadow-lg shadow-brand/20 active:scale-95">
                    <i class="ti ti-scissors text-base"></i> Create Cutting Batch
                </button>
            </div>
        </div>
    </div>

    <div class="p-8 space-y-8 max-w-7xl w-full mx-auto">
        
        <!-- Alerts -->
        <?php if ($success_msg): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-in fade-in">
                <div class="flex items-center gap-2.5">
                    <i class="ti ti-circle-check text-lg text-emerald-600"></i>
                    <span><?= htmlspecialchars($success_msg) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><i class="ti ti-x"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-in fade-in">
                <div class="flex items-center gap-2.5">
                    <i class="ti ti-alert-triangle text-lg text-red-600"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-800"><i class="ti ti-x"></i></button>
            </div>
        <?php endif; ?>

        <!-- Search Bar & Filters -->
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-xs overflow-hidden" id="cutting-table-card">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/40">
                <div>
                    <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider">Cutting Batches &amp; Production Ledger</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Monitor cut quantities, material cost allocations, and cost-per-piece metrics.</p>
                </div>

                <div class="relative min-w-[280px]">
                    <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input id="cutting-search" type="text" placeholder="Search by Cut #, product name, or status..." onkeyup="filterCuttingTable()"
                        class="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-all">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto px-6 pb-6">
                <table class="w-full text-left border-separate" style="border-spacing: 0 6px;">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-wider bg-gray-50">
                            <th class="px-4 py-3 rounded-l-xl">Cut Number</th>
                            <th class="px-4 py-3">Product Name</th>
                            <th class="px-4 py-3">Cutting Date</th>
                            <th class="px-4 py-3 text-center">Output Pieces</th>
                            <th class="px-4 py-3 text-right">Batch Total Cost</th>
                            <th class="px-4 py-3 text-right">Cost / Piece</th>
                            <th class="px-4 py-3 text-right rounded-r-xl">Status</th>
                        </tr>
                    </thead>
                    <tbody id="cutting-tbody">
                        <?php if (empty($cuttings)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-gray-400 font-semibold bg-white rounded-2xl border border-gray-100">
                                    <i class="ti ti-scissors-off text-4xl block mb-2 opacity-40"></i>
                                    No cutting records available. Click "+ Create Cutting Batch" to start your first production run.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cuttings as $c): ?>
                                <tr class="cutting-row bg-white cursor-pointer hover:bg-brand/5 transition-all group shadow-xs border border-gray-100"
                                    onclick="openCuttingDrawer(<?= htmlspecialchars(json_encode($c)) ?>)">
                                    <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 font-bold text-brand text-xs font-mono">
                                        <?= htmlspecialchars($c['cut_number']) ?>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-bold text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-brand/10 text-brand flex items-center justify-center font-bold text-xs">
                                                <i class="ti ti-shirt"></i>
                                            </div>
                                            <span><?= htmlspecialchars($c['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-gray-500 font-medium">
                                        <?= htmlspecialchars($c['cutting_date']) ?>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-center font-black text-gray-900">
                                        <?= number_format($c['total_pieces']) ?> pcs
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-right font-black font-mono text-gray-900">
                                        LKR <?= number_format((float)$c['total_cost'], 2) ?>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs text-right font-black font-mono text-emerald-700">
                                        LKR <?= number_format((float)$c['cost_per_piece'], 2) ?>
                                    </td>
                                    <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-xs text-right">
                                        <?php if ($c['status'] === 'Approved'): ?>
                                            <span class="px-3 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase">Approved</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200 uppercase">Pending</span>
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
</div>

<!-- ========================================================================= -->
<!-- MODAL: CREATE CUTTING BATCH (WITH RAW MATERIALS SECTION) -->
<!-- ========================================================================= -->
<div id="cutting-modal" class="hidden fixed inset-0 bg-black/60 z-50 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-4xl w-full flex flex-col max-h-[95dvh] overflow-hidden animate-in fade-in zoom-in duration-200">
        
        <!-- MODAL HEADER -->
        <div class="px-6 py-4 sm:px-8 sm:py-5 border-b border-gray-100 flex justify-between items-center bg-white rounded-t-3xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand/10 text-brand flex items-center justify-center text-xl font-bold shrink-0">
                    <i class="ti ti-scissors"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-gray-900">Create Cutting Batch</h2>
                    <p class="text-xs text-gray-400">Specify product name, sizing matrix, and calculate manufacturing cost via Raw Materials.</p>
                </div>
            </div>
            <button type="button" onclick="closeCuttingModal()" class="p-2 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100 transition-colors shrink-0">
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>

        <!-- MODAL BODY -->
        <form id="create-cutting-form" method="POST" action="" class="flex-1 min-h-0 overflow-y-auto px-6 py-6 sm:px-8 space-y-6 custom-scrollbar" onsubmit="return validateCuttingForm()">
            <input type="hidden" name="action" value="add_cutting">

            <!-- TOP FIELDS: Cut Number, Product Name, Cutting Date -->
            <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cut Number <span class="text-red-500">*</span></label>
                    <input type="text" name="cut_number" value="<?= htmlspecialchars($nextCutNumber) ?>" required
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-bold text-gray-900 outline-none focus:bg-white focus:border-brand transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-brand uppercase tracking-wider">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="product_name" id="cutting_product_name" list="catalog-products-list" placeholder="e.g. Classic Brief / Cotton Boxer" required
                        class="w-full px-4 py-2.5 bg-white border border-brand/40 rounded-xl text-xs font-bold text-gray-900 outline-none focus:ring-2 focus:ring-brand/20 transition-all">
                    <datalist id="catalog-products-list">
                        <?php foreach ($catalog_products as $p): ?>
                            <option value="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['sku']) ?></option>
                        <?php endforeach; ?>
                        <option value="Classic Combed Cotton Brief"></option>
                        <option value="Modal Trunk"></option>
                        <option value="Stretch Cotton Boxer"></option>
                        <option value="Seamless Microfiber Brief"></option>
                    </datalist>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cutting Date <span class="text-red-500">*</span></label>
                    <input type="date" name="cutting_date" value="<?= date('Y-m-d') ?>" required
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all">
                </div>
            </div>

            <!-- SECTION 1: CUTTING ITEMS BREAKDOWN -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-ruler text-brand"></i>
                            <span>Cutting Items Breakdown (Size Matrix)</span>
                        </label>
                        <p class="text-[10px] text-gray-400">Specify quantities per color and size (S, M, L, XL, XXL).</p>
                    </div>
                    <button type="button" onclick="addCuttingRowModal()"
                        class="px-3 py-1.5 bg-brand/10 text-brand border border-brand/20 rounded-xl text-xs font-bold hover:bg-brand hover:text-white transition-all flex items-center gap-1">
                        <i class="ti ti-plus text-xs"></i> Add Color Row
                    </button>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-2xl bg-white shadow-xs">
                    <table class="w-full text-left text-xs border-collapse min-w-[650px]">
                        <thead class="bg-gray-50 text-[10px] uppercase font-black text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Fabric Color</th>
                                <th class="py-2.5 px-2 text-center w-14">S</th>
                                <th class="py-2.5 px-2 text-center w-14">M</th>
                                <th class="py-2.5 px-2 text-center w-14">L</th>
                                <th class="py-2.5 px-2 text-center w-14">XL</th>
                                <th class="py-2.5 px-2 text-center w-14">XXL</th>
                                <th class="py-2.5 px-3 text-center w-24">Total Qty</th>
                                <th class="py-2.5 px-2 w-10 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="cutting-rows-container-modal" class="divide-y divide-gray-100">
                            <tr>
                                <td class="p-2"><input type="text" name="colors[]" placeholder="Color (e.g. White)" required value="White" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2"><input type="number" name="qty_s[]" value="0" min="0" oninput="recalcModalTotal()" class="c-s w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2"><input type="number" name="qty_m[]" value="0" min="0" oninput="recalcModalTotal()" class="c-m w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2"><input type="number" name="qty_l[]" value="0" min="0" oninput="recalcModalTotal()" class="c-l w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2"><input type="number" name="qty_xl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2"><input type="number" name="qty_xxl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xxl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand"></td>
                                <td class="p-2 text-center font-black text-brand"><span class="c-modal-row-qty">0 pcs</span></td>
                                <td class="p-2 text-center"><button type="button" onclick="removeCuttingRowModal(this)" class="p-1 text-gray-400 hover:text-red-600"><i class="ti ti-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold border-t border-gray-200 text-xs">
                            <tr>
                                <td colspan="6" class="py-2.5 px-3 text-right text-gray-600 font-bold uppercase text-[11px]">Grand Total Output Pieces:</td>
                                <td id="c-modal-grand-total" class="py-2.5 px-3 text-center text-brand font-black text-sm">0 pcs</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- SECTION 2: RAW MATERIALS ALLOCATION & COSTING -->
            <div class="space-y-3 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-packages text-brand"></i>
                            <span>Raw Materials &amp; Component Costs</span>
                        </label>
                        <p class="text-[10px] text-gray-400">Allocate component costs for this batch. Unit costs are auto-fetched from Raw Materials moving averages.</p>
                    </div>
                    <button type="button" onclick="addRawMaterialRowModal()"
                        class="px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold hover:bg-emerald-600 hover:text-white transition-all flex items-center gap-1">
                        <i class="ti ti-plus text-xs"></i> Add Material Row
                    </button>
                </div>

                <!-- Raw Materials Suggestions Datalist -->
                <datalist id="rm-catalog-datalist">
                    <?php foreach ($raw_material_options_list as $optLabel => $optAvgCost): ?>
                        <option value="<?= htmlspecialchars($optLabel) ?>">LKR <?= number_format((float)$optAvgCost, 2) ?></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="overflow-x-auto border border-gray-200 rounded-2xl bg-white shadow-xs">
                    <table class="w-full text-left text-xs border-collapse min-w-[650px]">
                        <thead class="bg-gray-50 text-[10px] uppercase font-black text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Material Component</th>
                                <th class="py-2.5 px-3 text-right w-36">Unit Cost (LKR)</th>
                                <th class="py-2.5 px-3 text-center w-28">Qty</th>
                                <th class="py-2.5 px-3 text-right w-36">Total Cost (LKR)</th>
                                <th class="py-2.5 px-2 w-10 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="rm-rows-container-modal" class="divide-y divide-gray-100">
                            <tr id="rm-empty-state">
                                <td colspan="5" class="py-8 text-center text-gray-400 font-medium text-xs bg-gray-50/50 rounded-xl">
                                    <i class="ti ti-package-off text-3xl block mb-1.5 opacity-40"></i>
                                    <span>No raw materials added yet. Click <strong>"+ Add Material Row"</strong> above to allocate manufacturing components.</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CUTTING BATCH TOTAL SUMMARY CARD -->
            <div class="bg-gray-900 text-white p-5 rounded-2xl border border-gray-800 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <span class="text-xs font-black uppercase tracking-wider text-brand">Cutting Batch Cost Summary</span>
                    <span class="text-[10px] text-gray-400">Total Materials + Cutting Allocation</span>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-3 bg-gray-800/80 rounded-xl border border-gray-700">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Output</p>
                        <p id="summary-total-pcs" class="text-xl font-black text-white mt-1">0 pcs</p>
                    </div>

                    <div class="p-3 bg-gray-800/80 rounded-xl border border-gray-700">
                        <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Batch Total Cost</p>
                        <p id="summary-grand-cost" class="text-xl font-black text-emerald-400 mt-1 font-mono">LKR 0.00</p>
                    </div>

                    <div class="p-3 bg-gray-800/80 rounded-xl border border-gray-700">
                        <p class="text-[10px] font-bold text-brand uppercase tracking-widest">Cost Per Piece</p>
                        <p id="summary-cost-per-pc" class="text-xl font-black text-brand mt-1 font-mono">LKR 0.00</p>
                    </div>
                </div>
            </div>
        </form>

        <!-- MODAL FOOTER -->
        <div class="px-6 py-4 sm:px-8 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 rounded-b-3xl">
            <button type="button" onclick="closeCuttingModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-white transition-all">Cancel</button>
            <button type="submit" form="create-cutting-form" class="px-10 py-2.5 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all shadow-md shadow-brand/20 active:scale-95">Save Batch</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SLIDE DRAWER: VIEW CUTTING BATCH DETAILS & RAW MATERIALS BREAKDOWN -->
<!-- ========================================================================= -->
<div id="cutting-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeCuttingDrawer()"></div>
<div id="cutting-drawer" class="fixed inset-y-0 right-0 z-50 w-full sm:w-[580px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="cutting-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start border-b border-gray-100 pb-4">
            <div>
                <span id="cd-status-badge" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"></span>
                <h2 id="cd-product-name" class="text-xl font-black text-gray-900 mt-2"></h2>
                <p id="cd-cut-num" class="text-xs font-mono font-bold text-brand mt-0.5"></p>
            </div>
            <button onclick="closeCuttingDrawer()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <!-- Cutting Date & Metrics -->
        <div class="grid grid-cols-3 gap-3 bg-gray-50 p-4 rounded-2xl border border-gray-100 text-center text-xs">
            <div>
                <span class="text-[10px] font-bold text-gray-400 uppercase block">Cutting Date</span>
                <strong id="cd-date" class="text-gray-900 text-xs font-semibold"></strong>
            </div>
            <div>
                <span class="text-[10px] font-bold text-gray-400 uppercase block">Output Pieces</span>
                <strong id="cd-pcs-badge" class="text-brand text-sm font-black font-mono"></strong>
            </div>
            <div>
                <span class="text-[10px] font-bold text-gray-400 uppercase block">Cost / Piece</span>
                <strong id="cd-cpp-badge" class="text-emerald-700 text-sm font-black font-mono"></strong>
            </div>
        </div>

        <!-- Sizing Matrix Table -->
        <section class="space-y-2.5">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Cutting Items Sizing Breakdown</h3>
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
                            <th class="py-2.5 px-3 text-right">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody id="cd-items-tbody" class="divide-y divide-gray-50"></tbody>
                </table>
            </div>
        </section>

        <!-- Raw Materials Cost Breakdown Table -->
        <section class="space-y-2.5">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Allocated Raw Materials &amp; Costs</h3>
            <div class="overflow-x-auto border border-gray-100 rounded-2xl shadow-xs">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="py-2.5 px-3">Material Component</th>
                            <th class="py-2.5 px-2 text-right">Unit Cost</th>
                            <th class="py-2.5 px-2 text-center">Qty</th>
                            <th class="py-2.5 px-3 text-right">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody id="cd-rm-tbody" class="divide-y divide-gray-50"></tbody>
                    <tfoot class="bg-gray-50 font-bold border-t border-gray-200 text-xs">
                        <tr>
                            <td colspan="3" class="py-2.5 px-3 text-right text-gray-600 font-bold">Cutting Batch Total:</td>
                            <td id="cd-grand-cost" class="py-2.5 px-3 text-right text-brand font-black font-mono">LKR 0.00</td>
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
            <button type="submit" id="cd-approve-btn" class="w-full px-4 py-2.5 bg-emerald-600 text-white font-bold rounded-xl text-xs hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 shadow-sm">
                <i class="ti ti-check text-base"></i> Approve Batch
            </button>
        </form>
        <button onclick="downloadPDF('cutting-drawer-content', 'Cutting_Batch_Report')" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all flex items-center gap-2 shadow-xs">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
const rawMaterialAvgCosts = <?= json_encode($raw_material_averages) ?>;

function openCuttingModal() { 
    document.getElementById('cutting-modal').classList.remove('hidden'); 
    recalcModalTotal();
}

function closeCuttingModal() { 
    document.getElementById('cutting-modal').classList.add('hidden'); 
}

function addCuttingRowModal() {
    const container = document.getElementById('cutting-rows-container-modal');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-2"><input type="text" name="colors[]" placeholder="Color (e.g. Navy)" required value="" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2"><input type="number" name="qty_s[]" value="0" min="0" oninput="recalcModalTotal()" class="c-s w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2"><input type="number" name="qty_m[]" value="0" min="0" oninput="recalcModalTotal()" class="c-m w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2"><input type="number" name="qty_l[]" value="0" min="0" oninput="recalcModalTotal()" class="c-l w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2"><input type="number" name="qty_xl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2"><input type="number" name="qty_xxl[]" value="0" min="0" oninput="recalcModalTotal()" class="c-xxl w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all"></td>
        <td class="p-2 text-center font-black text-brand"><span class="c-modal-row-qty">0 pcs</span></td>
        <td class="p-2 text-center"><button type="button" onclick="removeCuttingRowModal(this)" class="p-1 text-gray-400 hover:text-red-600"><i class="ti ti-trash"></i></button></td>
    `;
    container.appendChild(tr);
    recalcModalTotal();
}

function removeCuttingRowModal(btn) {
    const rows = document.getElementById('cutting-rows-container-modal').children;
    if (rows.length > 1) {
        btn.closest('tr').remove();
        recalcModalTotal();
    } else {
        alert("At least one cutting item color row is required.");
    }
}

function addRawMaterialRowModal(defaultName = '', defaultCost = null) {
    const emptyState = document.getElementById('rm-empty-state');
    if (emptyState) emptyState.remove();

    const container = document.getElementById('rm-rows-container-modal');
    const grandQty = parseInt(document.getElementById('c-modal-grand-total').textContent) || 0;
    const tr = document.createElement('tr');
    tr.className = 'rm-cost-row';

    let initialCost = defaultCost !== null ? defaultCost : 0.00;
    if (defaultName && rawMaterialAvgCosts[defaultName]) {
        initialCost = rawMaterialAvgCosts[defaultName];
    }

    tr.innerHTML = `
        <td class="p-2">
            <input type="text" name="rm_names[]" list="rm-catalog-datalist" placeholder="Select or type material (e.g. Elastic, Fabric, Thread)..." 
                value="${escapeHtml(defaultName)}" required oninput="onRMNameInput(this)" onchange="onRMNameInput(this)"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" name="rm_costs[]" value="${parseFloat(initialCost).toFixed(2)}" min="0" oninput="recalcRMTotals()"
                class="rm-unit-cost w-full px-3 py-2 bg-emerald-50/50 border border-emerald-200 rounded-xl text-xs font-black font-mono text-right outline-none focus:bg-white focus:border-brand transition-all">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" name="rm_qtys[]" value="${grandQty}" min="0" oninput="this.dataset.manual='true'; recalcRMTotals();"
                class="rm-qty w-full px-2 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-center outline-none focus:bg-white focus:border-brand transition-all">
        </td>
        <td class="p-2 text-right font-black font-mono text-gray-900">
            <span class="rm-row-total">LKR ${(parseFloat(initialCost) * grandQty).toFixed(2)}</span>
        </td>
        <td class="p-2 text-center">
            <button type="button" onclick="removeRMRowModal(this)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-all"><i class="ti ti-trash"></i></button>
        </td>
    `;
    container.appendChild(tr);
    recalcRMTotals();
}

function onRMNameInput(input) {
    const val = input.value.trim();
    const row = input.closest('tr');
    if (!row) return;

    const costInput = row.querySelector('.rm-unit-cost');
    if (!costInput) return;

    if (rawMaterialAvgCosts[val] !== undefined) {
        costInput.value = parseFloat(rawMaterialAvgCosts[val]).toFixed(2);
        recalcRMTotals();
    } else {
        for (let key in rawMaterialAvgCosts) {
            if (key.toLowerCase() === val.toLowerCase() || key.toLowerCase().endsWith(' - ' + val.toLowerCase()) || val.toLowerCase().endsWith(' - ' + key.toLowerCase())) {
                costInput.value = parseFloat(rawMaterialAvgCosts[key]).toFixed(2);
                recalcRMTotals();
                break;
            }
        }
    }
}

function removeRMRowModal(btn) {
    const row = btn.closest('tr');
    if (row) row.remove();

    const container = document.getElementById('rm-rows-container-modal');
    if (container.querySelectorAll('tr.rm-cost-row').length === 0) {
        container.innerHTML = `
            <tr id="rm-empty-state">
                <td colspan="5" class="py-8 text-center text-gray-400 font-medium text-xs bg-gray-50/50 rounded-xl">
                    <i class="ti ti-package-off text-3xl block mb-1.5 opacity-40"></i>
                    <span>No raw materials added yet. Click <strong>"+ Add Material Row"</strong> above to allocate manufacturing components.</span>
                </td>
            </tr>
        `;
    }
    recalcRMTotals();
}

function recalcModalTotal() {
    let grandQty = 0;
    document.querySelectorAll('#cutting-rows-container-modal tr').forEach(tr => {
        const qs = parseInt(tr.querySelector('.c-s')?.value || 0) || 0;
        const qm = parseInt(tr.querySelector('.c-m')?.value || 0) || 0;
        const ql = parseInt(tr.querySelector('.c-l')?.value || 0) || 0;
        const qxl = parseInt(tr.querySelector('.c-xl')?.value || 0) || 0;
        const qxxl = parseInt(tr.querySelector('.c-xxl')?.value || 0) || 0;
        const rowSum = qs + qm + ql + qxl + qxxl;

        const rowQtySpan = tr.querySelector('.c-modal-row-qty');
        if (rowQtySpan) rowQtySpan.textContent = rowSum + ' pcs';

        grandQty += rowSum;
    });

    document.getElementById('c-modal-grand-total').textContent = grandQty + ' pcs';
    document.getElementById('summary-total-pcs').textContent = grandQty + ' pcs';

    // Auto-update raw material quantity fields if they match standard batch output
    document.querySelectorAll('#rm-rows-container-modal tr.rm-cost-row .rm-qty').forEach(qInput => {
        if (!qInput.dataset.manual) {
            qInput.value = grandQty;
        }
    });

    recalcRMTotals();
}

function recalcRMTotals() {
    let grandCost = 0;
    const grandQty = parseInt(document.getElementById('c-modal-grand-total').textContent) || 0;

    document.querySelectorAll('#rm-rows-container-modal tr.rm-cost-row').forEach(tr => {
        const cost = parseFloat(tr.querySelector('.rm-unit-cost')?.value || 0) || 0;
        const qty = parseFloat(tr.querySelector('.rm-qty')?.value || 0) || 0;
        const lineTotal = cost * qty;

        const totalSpan = tr.querySelector('.rm-row-total');
        if (totalSpan) totalSpan.textContent = 'LKR ' + lineTotal.toFixed(2);

        grandCost += lineTotal;
    });

    const costPerPc = grandQty > 0 ? (grandCost / grandQty) : 0;

    document.getElementById('summary-grand-cost').textContent = 'LKR ' + grandCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-cost-per-pc').textContent = 'LKR ' + costPerPc.toFixed(2) + ' / pc';
}

function validateCuttingForm() {
    const pName = document.getElementById('cutting_product_name').value.trim();
    if (!pName) {
        alert('Please enter a valid Product Name.');
        return false;
    }
    const grandQty = parseInt(document.getElementById('c-modal-grand-total').textContent) || 0;
    if (grandQty <= 0) {
        alert('Please enter sizing quantities for at least one fabric color row.');
        return false;
    }
    return true;
}

function openCuttingDrawer(data) {
    document.getElementById('cd-product-name').textContent = data.product_name;
    document.getElementById('cd-cut-num').textContent = data.cut_number;
    document.getElementById('cd-date').textContent = data.cutting_date;
    document.getElementById('cd-pcs-badge').textContent = data.total_pieces + ' pcs';
    document.getElementById('cd-cpp-badge').textContent = 'LKR ' + parseFloat(data.cost_per_piece || 0).toFixed(2);
    document.getElementById('cd-cutting-id').value = data.id;

    const badge = document.getElementById('cd-status-badge');
    badge.textContent = data.status;
    if (data.status === 'Approved') {
        badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase';
        document.getElementById('cd-approve-btn').classList.add('hidden');
    } else {
        badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 uppercase';
        document.getElementById('cd-approve-btn').classList.remove('hidden');
    }

    // Render Cut Pieces Breakdown
    let items = [];
    try { items = JSON.parse(data.items || '[]'); } catch(e){}
    document.getElementById('cd-items-tbody').innerHTML = items.map(it => {
        const qs = parseInt(it.qty_s || 0) || 0;
        const qm = parseInt(it.qty_m || 0) || 0;
        const ql = parseInt(it.qty_l || 0) || 0;
        const qxl = parseInt(it.qty_xl || 0) || 0;
        const qxxl = parseInt(it.qty_xxl || 0) || 0;
        const qty = parseInt(it.quantity || (qs + qm + ql + qxl + qxxl)) || 0;

        return `
            <tr>
                <td class="py-2.5 px-3 font-bold text-gray-900">${escapeHtml(it.fabric_color)}</td>
                <td class="py-2.5 px-2 text-center text-gray-600 font-semibold">${qs}</td>
                <td class="py-2.5 px-2 text-center text-gray-600 font-semibold">${qm}</td>
                <td class="py-2.5 px-2 text-center text-gray-600 font-semibold">${ql}</td>
                <td class="py-2.5 px-2 text-center text-gray-600 font-semibold">${qxl}</td>
                <td class="py-2.5 px-2 text-center text-gray-600 font-semibold">${qxxl}</td>
                <td class="py-2.5 px-3 text-right font-black text-brand">${qty} pcs</td>
            </tr>
        `;
    }).join('');

    // Render Raw Materials Breakdown
    let rmList = [];
    try { rmList = JSON.parse(data.raw_materials_json || '[]'); } catch(e){}
    if (rmList.length > 0) {
        document.getElementById('cd-rm-tbody').innerHTML = rmList.map(rm => `
            <tr>
                <td class="py-2.5 px-3 font-bold text-gray-800">${escapeHtml(rm.name)}</td>
                <td class="py-2.5 px-2 text-right font-mono font-bold text-gray-700">LKR ${parseFloat(rm.unit_cost || 0).toFixed(2)}</td>
                <td class="py-2.5 px-2 text-center font-bold text-gray-800">${rm.qty}</td>
                <td class="py-2.5 px-3 text-right font-mono font-black text-gray-900">LKR ${parseFloat(rm.total_cost || 0).toFixed(2)}</td>
            </tr>
        `).join('');
    } else {
        document.getElementById('cd-rm-tbody').innerHTML = `<tr><td colspan="4" class="py-3 text-center text-gray-400">Standard batch production components.</td></tr>`;
    }

    document.getElementById('cd-grand-cost').textContent = 'LKR ' + parseFloat(data.total_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    document.getElementById('cutting-drawer-backdrop').classList.remove('hidden');
    document.getElementById('cutting-drawer').classList.remove('translate-x-full');
}

function closeCuttingDrawer() {
    document.getElementById('cutting-drawer-backdrop').classList.add('hidden');
    document.getElementById('cutting-drawer').classList.add('translate-x-full');
}

function filterCuttingTable() {
    const q = document.getElementById('cutting-search').value.toLowerCase().trim();
    document.querySelectorAll('#cutting-tbody tr.cutting-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Initial calculation on load
document.addEventListener('DOMContentLoaded', () => {
    recalcModalTotal();
});
</script>
