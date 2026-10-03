<?php
/**
 * Raw Materials View
 * Dynamic Raw Materials Management with 10 Component Boxes & Moving 5-Batch Average Cost Price Engine.
 */

$success_msg = "";
$error_msg = "";

// Self-Healing Database Table Creation & Column Expansion
if (isset($pdo) && $pdo !== null) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS raw_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_name VARCHAR(255) NOT NULL,
            material_category VARCHAR(100) NOT NULL,
            material_name VARCHAR(255) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            weight DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            weight_unit VARCHAR(20) DEFAULT 'Pieces',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Ensure unit_price column exists
        $chkPrice = $pdo->query("SHOW COLUMNS FROM raw_materials LIKE 'unit_price'");
        if (!$chkPrice->fetch()) {
            $pdo->exec("ALTER TABLE raw_materials ADD COLUMN unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER material_name");
            // If existing data stored price in weight column, backfill unit_price
            $pdo->exec("UPDATE raw_materials SET unit_price = weight WHERE unit_price = 0.00 AND weight > 0");
        }

        // Ensure notes column exists
        $chkNotes = $pdo->query("SHOW COLUMNS FROM raw_materials LIKE 'notes'");
        if (!$chkNotes->fetch()) {
            $pdo->exec("ALTER TABLE raw_materials ADD COLUMN notes TEXT NULL AFTER weight_unit");
        }
    } catch (\Exception $e) {
        // Table exists
    }
}

// Master Predefined Categories and Standard Material Names
$master_material_boxes = [
    'Fabric' => [
        'icon' => 'ti-layers-intersect',
        'color' => 'blue',
        'bg_light' => 'bg-blue-50',
        'border' => 'border-blue-200',
        'badge_color' => 'bg-blue-100 text-blue-800',
        'materials' => [
            'Lycra',
            'Single Jersey'
        ]
    ],
    'Thread' => [
        'icon' => 'ti-needle-thread',
        'color' => 'indigo',
        'bg_light' => 'bg-indigo-50',
        'border' => 'border-indigo-200',
        'badge_color' => 'bg-indigo-100 text-indigo-800',
        'materials' => [
            'yarn 2500',
            'yarn 5000',
            'Cotton 2500'
        ]
    ],
    'Elastic' => [
        'icon' => 'ti-line-dashed',
        'color' => 'emerald',
        'bg_light' => 'bg-emerald-50',
        'border' => 'border-emerald-200',
        'badge_color' => 'bg-emerald-100 text-emerald-800',
        'materials' => [
            '1" inches white 33m',
            '3/4" inches white 33m',
            '1 1/4" inches white 33m',
            '1/4" inches black 33m bobbin',
            'Gold Jacquard 33m',
            'Ex Jacquard 33m'
        ]
    ],
    'Fabric Printing' => [
        'icon' => 'ti-printer',
        'color' => 'purple',
        'bg_light' => 'bg-purple-50',
        'border' => 'border-purple-200',
        'badge_color' => 'bg-purple-100 text-purple-800',
        'materials' => [
            'Front',
            'Back',
            'Flower'
        ]
    ],
    'Cutting' => [
        'icon' => 'ti-scissors',
        'color' => 'amber',
        'bg_light' => 'bg-amber-50',
        'border' => 'border-amber-200',
        'badge_color' => 'bg-amber-100 text-amber-800',
        'materials' => [
            'Cutting'
        ]
    ],
    'Corrugated Box' => [
        'icon' => 'ti-box',
        'color' => 'orange',
        'bg_light' => 'bg-orange-50',
        'border' => 'border-orange-200',
        'badge_color' => 'bg-orange-100 text-orange-800',
        'materials' => [
            'Corrugated Box per piece'
        ]
    ],
    '2pc Box' => [
        'icon' => 'ti-package',
        'color' => 'teal',
        'bg_light' => 'bg-teal-50',
        'border' => 'border-teal-200',
        'badge_color' => 'bg-teal-100 text-teal-800',
        'materials' => [
            '2pc Box per piece'
        ]
    ],
    'Transport' => [
        'icon' => 'ti-truck-delivery',
        'color' => 'cyan',
        'bg_light' => 'bg-cyan-50',
        'border' => 'border-cyan-200',
        'badge_color' => 'bg-cyan-100 text-cyan-800',
        'materials' => [
            'Transport'
        ]
    ],
    'Bank Interest' => [
        'icon' => 'ti-building-bank',
        'color' => 'rose',
        'bg_light' => 'bg-rose-50',
        'border' => 'border-rose-200',
        'badge_color' => 'bg-rose-100 text-rose-800',
        'materials' => [
            'Bank Interest'
        ]
    ],
    'Sewing' => [
        'icon' => 'ti-shirt',
        'color' => 'fuchsia',
        'bg_light' => 'bg-fuchsia-50',
        'border' => 'border-fuchsia-200',
        'badge_color' => 'bg-fuchsia-100 text-fuchsia-800',
        'materials' => [
            'Sewing'
        ]
    ]
];

// Handle Form Submissions: Add, Edit, Delete Raw Material Entries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_raw_material' || $_POST['action'] === 'edit_raw_material') {
        $entry_id          = (int) ($_POST['entry_id'] ?? 0);
        $supplier_name     = trim($_POST['supplier_name'] ?? '');
        $material_category = trim($_POST['material_category'] ?? '');
        $material_name     = trim($_POST['material_name'] ?? '');
        $unit_price        = (float) ($_POST['unit_price'] ?? 0);
        $weight            = (float) ($_POST['weight'] ?? 1);
        $weight_unit       = trim($_POST['weight_unit'] ?? 'Pieces');
        $notes             = trim($_POST['notes'] ?? '');

        // Fallback: If unit_price wasn't set but weight holds price
        if ($unit_price <= 0 && $weight > 0) {
            $unit_price = $weight;
        }

        if ($supplier_name && $material_category && $material_name && $unit_price > 0) {
            try {
                if ($_POST['action'] === 'edit_raw_material' && $entry_id > 0) {
                    $stmt = $pdo->prepare("UPDATE raw_materials SET supplier_name = ?, material_category = ?, material_name = ?, unit_price = ?, weight = ?, weight_unit = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$supplier_name, $material_category, $material_name, $unit_price, $weight, $weight_unit, $notes, $entry_id]);
                    $success_msg = "Raw material entry for '{$material_name}' updated successfully!";
                } elseif ($_POST['action'] === 'edit_raw_material') {
                    // Check if latest entry exists to update or insert new
                    $chk = $pdo->prepare("SELECT id FROM raw_materials WHERE material_category = ? AND material_name = ? ORDER BY id DESC LIMIT 1");
                    $chk->execute([$material_category, $material_name]);
                    $latest_id = $chk->fetchColumn();
                    if ($latest_id) {
                        $stmt = $pdo->prepare("UPDATE raw_materials SET supplier_name = ?, material_category = ?, material_name = ?, unit_price = ?, weight = ?, weight_unit = ?, notes = ? WHERE id = ?");
                        $stmt->execute([$supplier_name, $material_category, $material_name, $unit_price, $weight, $weight_unit, $notes, $latest_id]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO raw_materials (supplier_name, material_category, material_name, unit_price, weight, weight_unit, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$supplier_name, $material_category, $material_name, $unit_price, $weight, $weight_unit, $notes]);
                    }
                    $success_msg = "Raw material purchase rate for '{$material_name}' updated successfully!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO raw_materials (supplier_name, material_category, material_name, unit_price, weight, weight_unit, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$supplier_name, $material_category, $material_name, $unit_price, $weight, $weight_unit, $notes]);
                    $success_msg = "Raw material purchase entry for '{$material_name}' (LKR " . number_format($unit_price, 2) . ") saved successfully!";
                }
            } catch (\Exception $e) {
                $error_msg = "Error processing raw material: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please fill in all required fields (Supplier, Material Name, and Price).";
        }
    } elseif ($_POST['action'] === 'delete_raw_material') {
        $material_category = trim($_POST['material_category'] ?? '');
        $material_name     = trim($_POST['material_name'] ?? '');
        $entry_id          = (int) ($_POST['entry_id'] ?? 0);

        try {
            if ($entry_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM raw_materials WHERE id = ?");
                $stmt->execute([$entry_id]);
                $success_msg = "Material purchase entry deleted successfully!";
            } elseif ($material_category && $material_name) {
                $stmt = $pdo->prepare("DELETE FROM raw_materials WHERE material_category = ? AND material_name = ?");
                $stmt->execute([$material_category, $material_name]);
                $success_msg = "All purchase entries for '{$material_name}' deleted successfully!";
            }
        } catch (\Exception $e) {
            $error_msg = "Error deleting raw material entries: " . $e->getMessage();
        }
    }
}

// Fetch All Raw Materials Records
$raw_materials = [];
$total_spend = 0.00;
$supplier_names = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $raw_materials = $pdo->query("SELECT * FROM raw_materials ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw_materials as &$rm) {
            $price = (float)($rm['unit_price'] > 0 ? $rm['unit_price'] : $rm['weight']);
            $rm['effective_price'] = $price;
            $total_spend += $price * max(1, (float)$rm['weight']);
            if (!empty($rm['supplier_name']) && !in_array($rm['supplier_name'], $supplier_names)) {
                $supplier_names[] = $rm['supplier_name'];
            }
        }
    } catch (\Exception $e) {}
}

// Compute Recent 5 Average Cost Price Engine for each Category & Material Name
$material_averages = [];
$material_history_map = [];

foreach ($raw_materials as $rm) {
    $cat = $rm['material_category'];
    $mat = $rm['material_name'];
    $key = $cat . '___' . $mat;

    if (!isset($material_history_map[$key])) {
        $material_history_map[$key] = [];
    }
    $material_history_map[$key][] = $rm;
}

// Calculate 5-Entry Moving Average for each material
foreach ($material_history_map as $key => $entries) {
    list($cat, $mat) = explode('___', $key, 2);
    // Take the most recent 5 or fewer entries
    $recent_5 = array_slice($entries, 0, 5);
    $prices = array_map(function($e) { return (float)$e['effective_price']; }, $recent_5);
    $count = count($prices);
    $avg = $count > 0 ? (array_sum($prices) / $count) : 0.00;
    $latest = $recent_5[0]['effective_price'] ?? 0.00;
    $latest_supplier = $recent_5[0]['supplier_name'] ?? '-';
    $latest_date = $recent_5[0]['created_at'] ?? '';

    $material_averages[$key] = [
        'category' => $cat,
        'material_name' => $mat,
        'avg_price' => $avg,
        'latest_price' => $latest,
        'latest_supplier' => $latest_supplier,
        'latest_date' => $latest_date,
        'sample_count' => $count,
        'history' => $recent_5
    ];
}

// Calculate Estimated Total Unit Production Cost (sum of standard 10 components)
$combined_unit_cost = 0.00;
foreach ($master_material_boxes as $box_name => $box_info) {
    $first_mat = $box_info['materials'][0] ?? $box_name;
    $key = $box_name . '___' . $first_mat;
    if (isset($material_averages[$key])) {
        $combined_unit_cost += $material_averages[$key]['avg_price'];
    }
}
?>

<div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-y-auto overflow-x-hidden no-scrollbar">
    
    <!-- TOP HEADER -->
    <div class="px-8 py-6 bg-white border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 sticky top-0 z-20 shadow-xs">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand/10 text-brand flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-packages"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-gray-900 tracking-tight">Raw Materials Management</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Dynamic cost pricing engine &amp; 5-batch moving average calculation across suppliers.</p>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-4">
            <!-- Stat Pills -->
            <div class="hidden sm:flex items-center gap-4 bg-gray-50 px-4 py-2 rounded-2xl border border-gray-100">
                <div class="text-center px-2">
                    <p class="text-sm font-black text-gray-900"><?= count($raw_materials) ?></p>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Entries</p>
                </div>
                <div class="w-px h-6 bg-gray-200"></div>
                <div class="text-center px-2">
                    <p class="text-sm font-black text-brand">10 Boxes</p>
                    <p class="text-[9px] font-bold text-brand uppercase tracking-widest">Materials</p>
                </div>
                <div class="w-px h-6 bg-gray-200"></div>
                <div class="text-center px-2">
                    <p class="text-sm font-black text-emerald-600">LKR <?= number_format($combined_unit_cost, 2) ?></p>
                    <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-widest">Avg Item Cost</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="downloadPDF('rm-full-view', 'Raw_Materials_Ledger_Report')" 
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all shadow-sm">
                    <i class="ti ti-printer text-base"></i> Export PDF
                </button>
                <button onclick="openRMModal()" 
                    class="flex items-center gap-2 px-5 py-2.5 bg-brand text-white rounded-xl text-xs font-bold hover:bg-brand-dark transition-all shadow-lg shadow-brand/20 active:scale-95">
                    <i class="ti ti-plus text-base"></i> Add New Material
                </button>
            </div>
        </div>
    </div>

    <div id="rm-full-view" class="p-8 space-y-10 max-w-7xl w-full mx-auto">

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

        <!-- SECTION: 10 MATERIAL CATEGORY BOXES GRID -->
        <section class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-200 pb-3">
                <div>
                    <h2 class="text-base font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="ti ti-layout-grid text-brand text-lg"></i>
                        <span>Raw Material Component Boxes &amp; Dynamic Average Costs</span>
                    </h2>
                    <p class="text-xs text-gray-500">Each box calculates its live cost price from the most recent 5 supplier purchases.</p>
                </div>
                <span class="text-[11px] font-bold text-gray-400 bg-white px-3 py-1 rounded-full border border-gray-200 self-start sm:self-auto">
                    10 Standard Manufacturing Components
                </span>
            </div>

            <!-- GRID OF 10 BOXES -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($master_material_boxes as $box_category => $box_meta): 
                    $box_entries_count = 0;
                    foreach ($raw_materials as $r) {
                        if ($r['material_category'] === $box_category) $box_entries_count++;
                    }
                ?>
                    <div class="bg-white rounded-3xl p-5 border border-gray-200/80 shadow-xs hover:shadow-md hover:border-brand/40 transition-all flex flex-col justify-between group">
                        <div class="space-y-4">
                            <!-- Box Header -->
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl <?= $box_meta['bg_light'] ?> text-gray-800 flex items-center justify-center font-bold text-lg group-hover:scale-110 transition-transform">
                                        <i class="ti <?= $box_meta['icon'] ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-gray-900 text-sm leading-tight"><?= htmlspecialchars($box_category) ?></h3>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"><?= $box_entries_count ?> Purchases</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Material Names & Moving Average Prices -->
                            <div class="space-y-3 pt-1">
                                <?php foreach ($box_meta['materials'] as $sub_material): 
                                    $mKey = $box_category . '___' . $sub_material;
                                    $avgInfo = $material_averages[$mKey] ?? null;
                                    $avgPrice = $avgInfo ? $avgInfo['avg_price'] : 0.00;
                                    $sampleCount = $avgInfo ? $avgInfo['sample_count'] : 0;
                                    $latestPrice = $avgInfo ? $avgInfo['latest_price'] : 0.00;
                                ?>
                                    <div class="p-4 bg-gray-50/90 rounded-2xl border border-gray-100 hover:border-brand/40 hover:bg-white hover:shadow-md transition-all cursor-pointer group/item space-y-2.5"
                                        title="Click to view 5-batch moving average history"
                                        onclick="openMaterialDrawer('<?= htmlspecialchars(addslashes($box_category)) ?>', '<?= htmlspecialchars(addslashes($sub_material)) ?>')">
                                        
                                        <!-- Top Row: Name, Badge, and Edit/Delete Actions -->
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-black text-gray-900 text-xs truncate group-hover/item:text-brand transition-colors" title="<?= htmlspecialchars($sub_material) ?>">
                                                    <?= htmlspecialchars($sub_material) ?>
                                                </span>
                                                <?php if ($sampleCount > 0): ?>
                                                    <span class="shrink-0 text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                        <?= $sampleCount ?>/5
                                                    </span>
                                                <?php else: ?>
                                                    <span class="shrink-0 text-[9px] font-semibold text-gray-400 italic">No entry</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Line Action Buttons: Edit & Delete -->
                                            <div class="flex items-center gap-1 shrink-0" onclick="event.stopPropagation()">
                                                <button type="button" 
                                                    title="Edit Material / Update Rate"
                                                    onclick="openEditMaterialModal('<?= htmlspecialchars(addslashes($box_category)) ?>', '<?= htmlspecialchars(addslashes($sub_material)) ?>')"
                                                    class="p-1.5 text-gray-400 hover:text-brand hover:bg-brand/10 rounded-lg transition-all">
                                                    <i class="ti ti-edit text-sm"></i>
                                                </button>
                                                <button type="button" 
                                                    title="Delete Material Entries"
                                                    onclick="confirmDeleteMaterial('<?= htmlspecialchars(addslashes($box_category)) ?>', '<?= htmlspecialchars(addslashes($sub_material)) ?>')"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">
                                                    <i class="ti ti-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bottom Row: Avg Cost -->
                                        <div class="flex items-baseline justify-between pt-1 border-t border-gray-100/80">
                                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Avg Cost:</span>
                                            <span class="text-xs font-black font-mono <?= $avgPrice > 0 ? 'text-brand' : 'text-gray-400' ?>">
                                                LKR <?= number_format($avgPrice, 2) ?>
                                            </span>
                                        </div>

                                        <?php if ($sampleCount > 0): ?>
                                            <div class="flex items-center justify-between text-[10px] text-gray-500 pt-0.5 font-medium">
                                                <span>Last: <strong class="text-gray-700 font-mono font-bold">LKR <?= number_format($latestPrice, 2) ?></strong></span>
                                                <span class="truncate max-w-[100px] text-gray-400 text-right" title="<?= htmlspecialchars($avgInfo['latest_supplier']) ?>"><?= htmlspecialchars($avgInfo['latest_supplier']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Add New Button for this Box -->
                        <div class="pt-4 mt-3 border-t border-gray-100">
                            <button onclick="openRMModalForCategory('<?= htmlspecialchars($box_category) ?>')" 
                                class="w-full py-2.5 px-3 bg-white hover:bg-brand hover:text-white text-gray-700 font-bold text-xs rounded-xl border border-gray-200 hover:border-brand transition-all flex items-center justify-center gap-1.5 shadow-xs">
                                <i class="ti ti-plus text-xs"></i>
                                <span>Add New</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD / EDIT RAW MATERIAL ENTRY -->
<!-- ========================================================================= -->
<div id="rm-modal" class="hidden fixed inset-0 bg-black/60 z-50 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full p-8 space-y-6 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand/10 text-brand flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-packages" id="rm-modal-icon"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-gray-900" id="rm-modal-title">Add Raw Material Entry</h2>
                    <p class="text-xs text-gray-400" id="rm-modal-subtitle">Record supplier purchase rate to update the moving average cost.</p>
                </div>
            </div>
            <button onclick="closeRMModal()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form method="POST" action="" class="space-y-4" onsubmit="return validateRMForm()">
            <input type="hidden" name="action" id="rm-modal-action" value="add_raw_material">
            <input type="hidden" name="entry_id" id="rm-modal-entry-id" value="">

            <!-- Supplier Name -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Supplier Name <span class="text-red-500">*</span></label>
                <input type="text" name="supplier_name" id="form-supplier-name" list="suppliers-datalist" placeholder="e.g. Ceylon Textile Mills / Chami / Tripal" required
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all">
                <datalist id="suppliers-datalist">
                    <?php foreach ($supplier_names as $sname): ?>
                        <option value="<?= htmlspecialchars($sname) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Material Category (Box) -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Material Box / Category <span class="text-red-500">*</span></label>
                <select name="material_category" id="form-category-select" required onchange="onCategoryChanged()"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all cursor-pointer">
                    <option value="">Select Material Box...</option>
                    <?php foreach (array_keys($master_material_boxes) as $catName): ?>
                        <option value="<?= htmlspecialchars($catName) ?>"><?= htmlspecialchars($catName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Material Name -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Material Name <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <select id="form-material-select" onchange="onMaterialSelectChanged()"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all cursor-pointer">
                        <option value="">Select Material Name...</option>
                    </select>
                    <input type="text" name="material_name" id="form-material-custom" placeholder="Type custom material name..." style="display: none;"
                        class="hidden w-full px-4 py-2.5 bg-white border border-brand/50 rounded-xl text-xs font-semibold text-gray-800 outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all shadow-inner">
                </div>
            </div>

            <!-- Price / Unit Cost (The primary number entered) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-brand uppercase tracking-wider">Price / Cost (LKR) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-xs text-gray-400">LKR</span>
                        <input type="number" step="0.01" name="unit_price" id="form-price" placeholder="0.00" min="0.01" required
                            class="w-full pl-12 pr-3 py-2.5 bg-emerald-50/40 border border-emerald-300 rounded-xl text-xs font-black text-gray-900 outline-none focus:bg-white focus:border-brand transition-all">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Weight / Quantity</label>
                    <div class="grid grid-cols-5 gap-2">
                        <input type="number" step="0.01" name="weight" id="form-weight" value="1.00" min="0.01"
                            class="col-span-2 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 outline-none focus:bg-white focus:border-brand transition-all">
                        <select name="weight_unit" id="form-weight-unit" class="col-span-3 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 outline-none cursor-pointer focus:bg-white focus:border-brand transition-all">
                            <option value="Pieces">Pieces</option>
                            <option value="kg">kg</option>
                            <option value="Meters">Meters</option>
                            <option value="Rolls">Rolls</option>
                            <option value="Spools">Spools</option>
                            <option value="Batch">Batch</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Notes / Batch Remarks</label>
                <input type="text" name="notes" id="form-notes" placeholder="Optional notes (e.g. Invoice #, Lot #, Grade)" 
                    class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-800 outline-none focus:bg-white focus:border-brand transition-all">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRMModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" id="rm-modal-submit-btn" class="px-6 py-2.5 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all shadow-md shadow-brand/20">Save Purchase Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DELETE MATERIAL CONFIRMATION -->
<!-- ========================================================================= -->
<div id="rm-delete-modal" class="hidden fixed inset-0 bg-black/60 z-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-md w-full p-6 sm:p-8 space-y-6 animate-in fade-in zoom-in duration-200">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-2xl font-bold shrink-0">
                <i class="ti ti-trash"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-gray-900">Delete Material Entries</h3>
                <p class="text-xs text-gray-500 mt-0.5">This will delete all purchase records and reset the moving average for this item.</p>
            </div>
        </div>

        <div class="p-4 bg-red-50/60 border border-red-100 rounded-2xl space-y-1.5 text-xs">
            <p class="text-gray-500 font-medium">Material Box: <strong id="del-modal-cat" class="text-gray-900 font-bold"></strong></p>
            <p class="text-gray-500 font-medium">Material Name: <strong id="del-modal-name" class="text-red-700 font-bold"></strong></p>
        </div>

        <form method="POST" action="" class="flex justify-end gap-3 pt-2">
            <input type="hidden" name="action" value="delete_raw_material">
            <input type="hidden" name="material_category" id="del-input-cat" value="">
            <input type="hidden" name="material_name" id="del-input-name" value="">
            <button type="button" onclick="closeRMDeleteModal()" class="px-5 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50 transition-all">Cancel</button>
            <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-red-600/20">Confirm Delete</button>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SLIDE DRAWER: VIEW MATERIAL DETAILS & 5-ENTRY MOVING AVERAGE -->
<!-- ========================================================================= -->
<div id="rm-drawer-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeRMDrawer()"></div>
<div id="rm-drawer" class="fixed inset-y-0 right-0 z-50 w-full sm:w-[480px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div id="rm-drawer-content" class="p-8 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-between items-start border-b border-gray-100 pb-4">
            <div>
                <span id="rmd-cat-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-brand/10 text-brand"></span>
                <h2 id="rmd-material" class="text-xl font-black text-gray-900 mt-2"></h2>
                <p id="rmd-supplier" class="text-xs text-gray-500 mt-0.5"></p>
            </div>
            <button onclick="closeRMDrawer()" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-xl hover:bg-gray-100"><i class="ti ti-x text-xl"></i></button>
        </div>

        <!-- Metric Card -->
        <div class="bg-brand/5 p-5 rounded-2xl border border-brand/20 space-y-3">
            <div class="flex justify-between items-baseline">
                <span class="text-xs font-bold text-brand uppercase tracking-wider">Recent 5-Batch Average Cost:</span>
                <span id="rmd-avg" class="text-xl font-black font-mono text-brand"></span>
            </div>
            <p class="text-[11px] text-gray-500 leading-relaxed">
                Calculated by taking up to the last 5 material entries recorded across different suppliers for this item.
            </p>
        </div>

        <!-- Entry Details -->
        <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-3 text-xs">
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Selected Entry Price:</span><strong id="rmd-price" class="text-gray-900 font-mono font-bold"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Weight / Quantity:</span><strong id="rmd-weight" class="text-gray-900"></strong></div>
            <div class="flex justify-between"><span class="text-gray-500 font-medium">Date Recorded:</span><strong id="rmd-date" class="text-gray-900"></strong></div>
            <div class="flex justify-between" id="rmd-notes-row"><span class="text-gray-500 font-medium">Notes:</span><strong id="rmd-notes" class="text-gray-700"></strong></div>
        </div>

        <!-- 5-Batch History List -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Most Recent 5 Purchases:</h3>
            <div id="rmd-history-list" class="space-y-2"></div>
        </div>
    </div>

    <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
        <button onclick="downloadPDF('rm-drawer-content', 'Raw_Material_Detail')" class="px-5 py-2.5 bg-brand text-white font-bold rounded-xl text-xs hover:bg-brand-dark transition-all flex items-center gap-2 shadow-sm">
            <i class="ti ti-printer text-base"></i> Export PDF
        </button>
    </div>
</div>

<script>
const masterMaterialsMap = <?= json_encode($master_material_boxes) ?>;
const masterAveragesMap = <?= json_encode($material_averages) ?>;

function openRMModal() {
    document.getElementById('rm-modal-title').textContent = 'Add Raw Material Entry';
    document.getElementById('rm-modal-subtitle').textContent = 'Record supplier purchase rate to update the moving average cost.';
    document.getElementById('rm-modal-action').value = 'add_raw_material';
    document.getElementById('rm-modal-entry-id').value = '';
    document.getElementById('rm-modal-submit-btn').textContent = 'Save Purchase Entry';
    document.getElementById('form-supplier-name').value = '';
    document.getElementById('form-price').value = '';
    document.getElementById('form-weight').value = '1.00';
    document.getElementById('form-weight-unit').value = 'Pieces';
    document.getElementById('form-notes').value = '';
    document.getElementById('rm-modal').classList.remove('hidden');
    onCategoryChanged();
}

function openRMModalForCategory(category) {
    openRMModal();
    document.getElementById('form-category-select').value = category;
    onCategoryChanged();
}

function openEditMaterialModal(category, materialName) {
    const key = category + '___' + materialName;
    const avgData = masterAveragesMap[key] || null;

    document.getElementById('rm-modal-title').textContent = 'Edit Material / Update Rate';
    document.getElementById('rm-modal-subtitle').textContent = 'Update supplier purchase rate for ' + materialName;
    document.getElementById('rm-modal-action').value = 'edit_raw_material';
    document.getElementById('rm-modal-submit-btn').textContent = 'Update Material Entry';

    document.getElementById('form-category-select').value = category;
    onCategoryChanged();

    // Select the material in dropdown
    const matSelect = document.getElementById('form-material-select');
    const customInput = document.getElementById('form-material-custom');
    
    let found = false;
    for (let opt of matSelect.options) {
        if (opt.value === materialName) {
            matSelect.value = materialName;
            customInput.style.display = 'none';
            customInput.classList.add('hidden');
            found = true;
            break;
        }
    }
    if (!found) {
        matSelect.value = '__custom__';
        customInput.value = materialName;
        customInput.style.display = 'block';
        customInput.classList.remove('hidden');
    }

    // Pre-populate latest entry values if exists
    if (avgData && avgData.history && avgData.history.length > 0) {
        const latest = avgData.history[0];
        document.getElementById('rm-modal-entry-id').value = latest.id || '';
        document.getElementById('form-supplier-name').value = latest.supplier_name || '';
        document.getElementById('form-price').value = parseFloat(latest.effective_price || latest.unit_price || latest.weight || 0).toFixed(2);
        document.getElementById('form-weight').value = latest.weight || '1.00';
        document.getElementById('form-weight-unit').value = latest.weight_unit || 'Pieces';
        document.getElementById('form-notes').value = latest.notes || '';
    } else {
        document.getElementById('rm-modal-entry-id').value = '';
        document.getElementById('form-supplier-name').value = '';
        document.getElementById('form-price').value = '';
        document.getElementById('form-weight').value = '1.00';
        document.getElementById('form-weight-unit').value = 'Pieces';
        document.getElementById('form-notes').value = '';
    }

    document.getElementById('rm-modal').classList.remove('hidden');
}

function closeRMModal() {
    document.getElementById('rm-modal').classList.add('hidden');
}

function confirmDeleteMaterial(category, materialName) {
    document.getElementById('del-modal-cat').textContent = category;
    document.getElementById('del-modal-name').textContent = materialName;
    document.getElementById('del-input-cat').value = category;
    document.getElementById('del-input-name').value = materialName;
    document.getElementById('rm-delete-modal').classList.remove('hidden');
}

function closeRMDeleteModal() {
    document.getElementById('rm-delete-modal').classList.add('hidden');
}

function onCategoryChanged() {
    const cat = document.getElementById('form-category-select').value;
    const matSelect = document.getElementById('form-material-select');
    const customInput = document.getElementById('form-material-custom');

    matSelect.innerHTML = '<option value="">Select Material Name...</option>';

    if (cat && masterMaterialsMap[cat]) {
        const mats = masterMaterialsMap[cat].materials || [];
        mats.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            matSelect.appendChild(opt);
        });
        const otherOpt = document.createElement('option');
        otherOpt.value = '__custom__';
        otherOpt.textContent = '+ Custom / Other Material...';
        matSelect.appendChild(otherOpt);

        if (mats.length > 0) {
            matSelect.value = mats[0];
            customInput.value = '';
            customInput.style.display = 'none';
            customInput.classList.add('hidden');
        } else {
            matSelect.value = '__custom__';
            customInput.value = '';
            customInput.style.display = 'block';
            customInput.classList.remove('hidden');
            customInput.focus();
        }
    } else {
        customInput.value = '';
        customInput.style.display = 'none';
        customInput.classList.add('hidden');
    }
}

function onMaterialSelectChanged() {
    const matSelect = document.getElementById('form-material-select');
    const customInput = document.getElementById('form-material-custom');

    if (matSelect.value === '__custom__') {
        customInput.value = '';
        customInput.style.display = 'block';
        customInput.classList.remove('hidden');
        customInput.focus();
    } else {
        customInput.value = '';
        customInput.style.display = 'none';
        customInput.classList.add('hidden');
    }
}

function validateRMForm() {
    const matSelect = document.getElementById('form-material-select');
    const customInput = document.getElementById('form-material-custom');

    if (matSelect.value === '__custom__') {
        if (!customInput.value.trim()) {
            alert('Please enter a custom Material Name.');
            customInput.focus();
            return false;
        }
        customInput.name = 'material_name';
        matSelect.removeAttribute('name');
    } else {
        if (!matSelect.value) {
            alert('Please select a Material Name.');
            return false;
        }
        matSelect.name = 'material_name';
        customInput.removeAttribute('name');
    }

    const price = parseFloat(document.getElementById('form-price').value) || 0;
    if (price <= 0) {
        alert('Please enter a valid Price / Cost greater than 0.');
        document.getElementById('form-price').focus();
        return false;
    }
    return true;
}

function openMaterialDrawer(cat, mat) {
    const key = cat + '___' + mat;
    const avgData = masterAveragesMap[key] || null;

    document.getElementById('rmd-cat-badge').textContent = cat;
    document.getElementById('rmd-material').textContent = mat;
    document.getElementById('rmd-supplier').textContent = (avgData && avgData.latest_supplier) ? ('Latest Supplier: ' + avgData.latest_supplier) : 'No recent supplier logged';
    document.getElementById('rmd-price').textContent = avgData ? ('LKR ' + parseFloat(avgData.latest_price || 0).toFixed(2)) : 'LKR 0.00';
    document.getElementById('rmd-weight').textContent = '-';
    document.getElementById('rmd-date').textContent = (avgData && avgData.latest_date) ? avgData.latest_date : '-';
    document.getElementById('rmd-notes-row').classList.add('hidden');

    const avgPrice = avgData ? parseFloat(avgData.avg_price).toFixed(2) : '0.00';
    document.getElementById('rmd-avg').textContent = 'LKR ' + avgPrice;

    // Render 5-Batch History List
    const historyContainer = document.getElementById('rmd-history-list');
    if (avgData && avgData.history && avgData.history.length > 0) {
        historyContainer.innerHTML = avgData.history.map((h, idx) => `
            <div class="p-3 bg-white border border-gray-200 rounded-xl flex items-center justify-between text-xs">
                <div>
                    <span class="font-bold text-gray-900">${idx + 1}. ${escapeHtml(h.supplier_name)}</span>
                    <span class="block text-[10px] text-gray-400">${h.created_at}</span>
                </div>
                <div class="text-right font-mono font-black text-brand">
                    LKR ${parseFloat(h.effective_price || h.unit_price || h.weight).toFixed(2)}
                </div>
            </div>
        `).join('');
    } else {
        historyContainer.innerHTML = `<div class="p-4 bg-gray-50 border border-gray-100 rounded-xl text-center text-xs text-gray-400 font-medium">No supplier purchases logged yet for this material. Click "+ Add New" to record.</div>`;
    }

    document.getElementById('rm-drawer-backdrop').classList.remove('hidden');
    document.getElementById('rm-drawer').classList.remove('translate-x-full');
}

function closeRMDrawer() {
    document.getElementById('rm-drawer-backdrop').classList.add('hidden');
    document.getElementById('rm-drawer').classList.add('translate-x-full');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
