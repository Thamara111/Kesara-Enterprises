<?php
/**
 * Suppliers & Garments Management View
 * Natural Language Overview:
 * 1. Fetching Data -> Getting partner profiles (Garments / Material Suppliers), supplied items, lead times, and PO expenditures.
 * 2. Self-Healing -> Ensuring unit_cost, deleted_at, lead_time, and supplier_type columns exist on supplier tables.
 * 3. Processing -> Handling POST actions for saving, updating, and soft-deleting Garments & Material Suppliers.
 */

// Self-Healing DB: Ensure supplier_items has unit_cost column & suppliers has deleted_at, lead_time, and supplier_type columns
if (isset($pdo) && $pdo !== null) {
    try {
        $checkUnitCost = $pdo->query("SHOW COLUMNS FROM supplier_items LIKE 'unit_cost'");
        if (!$checkUnitCost->fetch()) {
            $pdo->exec("ALTER TABLE supplier_items ADD COLUMN unit_cost DECIMAL(10,2) DEFAULT NULL");
        }

        $checkDeleted = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'deleted_at'");
        if (!$checkDeleted->fetch()) {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN deleted_at DATETIME DEFAULT NULL");
        }

        $checkLeadTime = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'lead_time'");
        if (!$checkLeadTime->fetch()) {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN lead_time INT DEFAULT 7");
        }

        $checkType = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'supplier_type'");
        if (!$checkType->fetch()) {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN supplier_type ENUM('supplier', 'garment') DEFAULT 'supplier'");
            $pdo->exec("UPDATE suppliers SET supplier_type = 'garment' WHERE category IN ('Innerwear Manufacturing', 'Cut-Make-Trim (CMT)', 'Apparel Finishing') OR id >= 6");
        }

        // Ensure default sample Garment factories exist if none are populated
        $checkGarmentCount = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE supplier_type = 'garment' AND deleted_at IS NULL")->fetchColumn();
        if ($checkGarmentCount == 0) {
            $pdo->exec("INSERT INTO suppliers (name, email, contact_person, phone, address, payment_terms, category, supplier_type, status, lead_time) VALUES
                ('MAS Matrix Garment Factory', 'info@masmatrix.lk', 'Mr. Kanishka Jayawardena', '0112233445', 'Biyagama EPZ, WP', 'Net 60', 'Innerwear Manufacturing', 'garment', 'preferred', 60),
                ('Apex Apparel Manufacturing', 'orders@apexapparel.lk', 'Ms. Dilhani Perera', '0314567890', 'Katunayake EPZ, WP', 'Net 60', 'Cut-Make-Trim (CMT)', 'garment', 'active', 75),
                ('Lanka Stitching Mills', 'contact@lankastitch.lk', 'Mr. Chaminda Bandara', '0338901234', 'Veyangoda, WP', 'Net 45', 'Apparel Finishing', 'garment', 'active', 60)");
        }
    } catch (\Exception $e) {
        // Ignored
    }
}

// Active View Type: Default to 'garment' (current Suppliers view transformed to Garments)
$view_type = isset($_GET['type']) && $_GET['type'] === 'supplier' ? 'supplier' : 'garment';
$is_garment = ($view_type === 'garment');

// Processing -> Handling POST actions for saving, updating, and soft-deleting partner profiles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $supplier_id = isset($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : 0;
        $name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
        $lead_time = (isset($_POST['lead_time']) && is_numeric($_POST['lead_time'])) ? max(1, (int)$_POST['lead_time']) : ($is_garment ? 60 : 7);
        $category = trim($_POST['category'] ?? ($is_garment ? 'Innerwear Manufacturing' : 'Fabric'));
        $supplier_type = trim($_POST['supplier_type'] ?? $view_type);
        if (!in_array($supplier_type, ['supplier', 'garment'])) {
            $supplier_type = 'garment';
        }
        $status = trim($_POST['status'] ?? 'active');
        $hold_reason = trim($_POST['hold_reason'] ?? '');
        $hold_since = ($status === 'on_hold') ? date('Y-m-d') : null;

        $items_raw = trim($_POST['supplied_items'] ?? '');
        $items_arr = json_decode($items_raw, true);
        if (!is_array($items_arr)) {
            $temp = array_filter(array_map('trim', explode(',', $items_raw)));
            $items_arr = [];
            foreach ($temp as $t) {
                $items_arr[] = ['name' => $t, 'cost' => null];
            }
        }

        if (empty($name) || empty($email) || empty($phone)) {
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Name, email, and phone are required.', 'error'); });</script>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Invalid email format.', 'error'); });</script>";
        } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Phone number must start with 0 and contain exactly 10 digits.', 'error'); });</script>";
        } else {
            try {
                if ($supplier_id > 0) {
                    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, email = ?, contact_person = ?, phone = ?, address = ?, payment_terms = ?, lead_time = ?, category = ?, supplier_type = ?, status = ?, hold_reason = ?, hold_since = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $lead_time, $category, $supplier_type, $status, $hold_reason, $hold_since, $supplier_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, email, contact_person, phone, address, payment_terms, lead_time, category, supplier_type, status, hold_reason, hold_since) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $lead_time, $category, $supplier_type, $status, $hold_reason, $hold_since]);
                    $supplier_id = $pdo->lastInsertId();

                    if (file_exists(__DIR__ . "/../../src/Mailer.php")) {
                        require_once __DIR__ . "/../../src/Mailer.php";
                        $subject = $supplier_type === 'garment' ? "Welcome to Kesara Enterprises Garment Manufacturing Network" : "Welcome to Kesara Enterprises Supplier Network";
                        $body = "<h3>Hello " . htmlspecialchars($contact_person) . ",</h3><p>Your company <strong>" . htmlspecialchars($name) . "</strong> has been registered as a partner with Kesara Enterprises.</p><p>We look forward to working with you.</p>";
                        if (class_exists('\App\Mailer')) {
                            \App\Mailer::send($email, $subject, $body);
                        }
                    }
                }

                $del_stmt = $pdo->prepare("DELETE FROM supplier_items WHERE supplier_id = ?");
                $del_stmt->execute([$supplier_id]);

                $del_sp_stmt = $pdo->prepare("DELETE FROM supplier_products WHERE supplier_id = ?");
                $del_sp_stmt->execute([$supplier_id]);

                if (!empty($items_arr)) {
                    $ins_stmt = $pdo->prepare("INSERT INTO supplier_items (supplier_id, item_name, unit_cost) VALUES (?, ?, ?)");
                    $ins_sp_stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, product_id, unit_cost) VALUES (?, ?, ?)");

                    // Fetch active products map for cross-referencing supplier_products table
                    $prod_map = [];
                    $pm_rows = $pdo->query("SELECT id, name FROM products WHERE deleted_at IS NULL")->fetchAll();
                    foreach ($pm_rows as $pm) {
                        $prod_map[mb_strtolower(trim($pm['name']))] = (int)$pm['id'];
                    }

                    foreach ($items_arr as $itm) {
                        $itm_name = trim($itm['name'] ?? '');
                        if ($itm_name === '') continue;
                        $cost = (isset($itm['cost']) && is_numeric($itm['cost'])) ? (float)$itm['cost'] : null;
                        $ins_stmt->execute([$supplier_id, $itm_name, $cost]);

                        $lowercase_name = mb_strtolower($itm_name);
                        if (isset($prod_map[$lowercase_name])) {
                            $pid = $prod_map[$lowercase_name];
                            $ins_sp_stmt->execute([$supplier_id, $pid, $cost]);
                        }
                    }
                }
                $saved_msg = $supplier_type === 'garment' ? 'Garment factory saved successfully.' : 'Supplier saved successfully.';
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast(" . json_encode($saved_msg) . ", 'success'); });</script>";
            } catch (Exception $e) {
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Error saving record.', 'error'); });</script>";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $supplier_id = isset($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : 0;
        if ($supplier_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$supplier_id]);
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Record moved to Recycle Bin.', 'success'); });</script>";
            } catch (Exception $e) {
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Error deleting record.', 'error'); });</script>";
            }
        }
    }
}

// Fetching Data -> Fetch registered suppliers / garments matching active type filter
$admin_suppliers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT s.id, s.name, s.email, s.contact_person AS contact, s.phone, s.address AS addr, s.payment_terms AS terms, s.category AS cat, s.supplier_type, s.status, s.hold_reason, s.hold_since, s.lead_time 
                               FROM suppliers s 
                               WHERE s.deleted_at IS NULL AND (s.supplier_type = ? OR (s.supplier_type IS NULL AND ? = 'supplier'))
                               ORDER BY s.id DESC");
        $stmt->execute([$view_type, $view_type]);
        $supps = $stmt->fetchAll();

        foreach ($supps as $s) {
            $words = explode(" ", $s['name']);
            $initials = "";
            foreach ($words as $w) {
                $initials .= strtoupper(substr($w, 0, 1));
            }
            $initials = substr($initials, 0, 2);

            $av_options = [
                'bg-emerald-100 text-emerald-700 border-emerald-200 shadow-emerald-100',
                'bg-indigo-100 text-indigo-700 border-indigo-200 shadow-indigo-100',
                'bg-blue-100 text-blue-700 border-blue-200 shadow-blue-100',
                'bg-amber-100 text-amber-700 border-amber-200 shadow-amber-100',
                'bg-lime-100 text-lime-700 border-lime-200 shadow-lime-100'
            ];
            $av = $av_options[$s['id'] % count($av_options)];

            $p_stmt = $pdo->prepare("SELECT item_name, unit_cost FROM supplier_items WHERE supplier_id = ?");
            $p_stmt->execute([$s['id']]);
            $items_rows = $p_stmt->fetchAll();
            $items_arr = [];
            foreach ($items_rows as $row) {
                $items_arr[] = ['name' => $row['item_name'], 'cost' => $row['unit_cost']];
            }

            // Also check products table directly connected to this garment via supplier_products
            if ($is_garment && empty($items_rows)) {
                $gp_stmt = $pdo->prepare("SELECT p.name, sp.unit_cost FROM products p JOIN supplier_products sp ON p.id = sp.product_id WHERE sp.supplier_id = ? AND p.deleted_at IS NULL");
                $gp_stmt->execute([$s['id']]);
                $gp_rows = $gp_stmt->fetchAll();
                foreach ($gp_rows as $gp) {
                    $items_arr[] = ['name' => $gp['name'], 'cost' => $gp['unit_cost']];
                }
            }

            $all_products_html = "";
            $products_html = "";
            $total_items = count($items_arr);

            foreach ($items_arr as $idx => $item) {
                $cst_str = $item['cost'] !== null ? ' - LKR ' . number_format((float) $item['cost'], 2) : '';
                $chip_style = $is_garment 
                    ? 'bg-indigo-50 border border-indigo-100 text-indigo-700 font-bold' 
                    : 'bg-gray-50 border border-gray-100 text-gray-600 font-medium';
                
                $chip_table = '<span class="px-2.5 py-1 rounded-lg text-[10px] uppercase tracking-wider shrink-0 truncate max-w-[130px] ' . $chip_style . '" title="' . htmlspecialchars($item['name']) . $cst_str . '">' . htmlspecialchars($item['name']) . '</span>';
                $chip_drawer = '<div class="px-3 py-2 border rounded-xl flex flex-col justify-center ' . $chip_style . '"><span class="text-xs uppercase tracking-wider font-bold truncate">' . htmlspecialchars($item['name']) . '</span>' . ($item['cost'] !== null ? '<span class="text-[10px] opacity-75 font-semibold mt-0.5">LKR ' . number_format((float)$item['cost'], 2) . '</span>' : '') . '</div>';

                $all_products_html .= $chip_drawer;
                if ($idx < 2) {
                    $products_html .= $chip_table;
                }
            }

            if ($total_items > 2) {
                $rem = $total_items - 2;
                $products_html .= '<span class="px-2 py-1 bg-gray-100 border border-gray-200 text-gray-500 rounded-lg text-[10px] font-extrabold uppercase tracking-wider shrink-0">+' . $rem . ' more</span>';
            }

            if ($total_items === 0) {
                $products_html = '<span class="text-xs text-gray-400 italic">None assigned</span>';
                $all_products_html = '<span class="text-xs text-gray-400 italic col-span-2">No items assigned</span>';
            }

            $items_raw = json_encode($items_arr);

            $sp_stmt = $pdo->prepare("SELECT AVG(lead_days) FROM supplier_products WHERE supplier_id = ?");
            $sp_stmt->execute([$s['id']]);
            $avg_lead = $sp_stmt->fetchColumn();
            $lead_days_val = (isset($s['lead_time']) && is_numeric($s['lead_time'])) ? (int)$s['lead_time'] : ($avg_lead ? (int)round($avg_lead) : ($is_garment ? 60 : 7));
            
            $lead = $lead_days_val >= 30 ? (round($lead_days_val / 30, 1) . ' months (' . $lead_days_val . ' days)') : ($lead_days_val . ' days');

            $po_stmt = $pdo->prepare("SELECT COUNT(*) AS pos, SUM(total) AS spend FROM purchase_orders WHERE supplier_id = ?");
            $po_stmt->execute([$s['id']]);
            $po_metrics = $po_stmt->fetch();
            $pos_count = (int) ($po_metrics['pos'] ?? 0);
            $spend_val = (float) ($po_metrics['spend'] ?? 0);
            $spend = $spend_val >= 1000000 ? 'LKR ' . number_format($spend_val / 1000000, 1) . 'M' : 'LKR ' . number_format($spend_val / 1000, 0) . 'K';

            $ontime = $s['id'] == 1 ? '96%' : ($s['id'] == 2 ? '88%' : ($s['id'] == 3 ? '94%' : ($s['id'] == 4 ? '71%' : '95%')));
            $ontimeW = $s['id'] == 1 ? 96 : ($s['id'] == 2 ? 88 : ($s['id'] == 3 ? 94 : ($s['id'] == 4 ? 71 : 95)));
            $quality = $s['id'] == 1 ? '98%' : ($s['id'] == 2 ? '91%' : ($s['id'] == 3 ? '99%' : ($s['id'] == 4 ? '84%' : '97%')));
            $qualityW = $s['id'] == 1 ? 98 : ($s['id'] == 2 ? 91 : ($s['id'] == 3 ? 99 : ($s['id'] == 4 ? 84 : 97)));

            $status_lower = strtolower($s['status']);
            if ($status_lower === 'preferred') {
                $badge = 'bg-blue-50 text-blue-700 border-blue-200';
                $badgeText = 'Preferred';
            } elseif ($status_lower === 'active') {
                $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                $badgeText = 'Active';
            } elseif ($status_lower === 'on_hold') {
                $badge = 'bg-amber-50 text-amber-700 border-amber-200';
                $badgeText = 'On hold';
            } else {
                $badge = 'bg-gray-100 text-gray-500 border-gray-200';
                $badgeText = ucfirst($s['status']);
            }

            $admin_suppliers[] = [
                'id' => $s['id'],
                'initials' => $initials,
                'av' => $av,
                'name' => $s['name'],
                'email' => $s['email'],
                'contact' => $s['contact'] ?? '',
                'phone' => $s['phone'] ?? '',
                'addr' => $s['addr'] ?? '',
                'terms' => $s['terms'] ?? 'Net 30',
                'lead_days' => $lead_days_val,
                'products' => $products_html ?: '<span class="text-xs text-gray-400 italic">None assigned</span>',
                'all_products' => $all_products_html ?: '<span class="text-xs text-gray-400 italic">No items assigned</span>',
                'items_raw' => $items_raw,
                'hold_reason' => $s['hold_reason'] ?? '',
                'lead' => $lead,
                'cat' => $s['cat'] ?? ($is_garment ? 'Innerwear Manufacturing' : 'Fabric'),
                'supplier_type' => $s['supplier_type'] ?? $view_type,
                'ontime' => $ontime,
                'ontimeW' => $ontimeW,
                'quality' => $quality,
                'qualityW' => $qualityW,
                'badge' => $badge,
                'badgeText' => $badgeText,
                'status' => $s['status'],
                'orders' => $pos_count,
                'spend' => $spend
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}

if (empty($admin_suppliers)) {
    $admin_suppliers = [];
}

$inv_products_json = '[]';
if (isset($pdo) && $pdo !== null) {
    try {
        $prod_list = $pdo->query("SELECT p.name AS p_name FROM products p WHERE p.deleted_at IS NULL ORDER BY p.name ASC")->fetchAll();
        $prod_names = [];
        foreach ($prod_list as $prod) {
            $prod_names[] = $prod['p_name'];
        }
        $inv_products_json = json_encode($prod_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    } catch (\Exception $e) {
        // Ignore
    }
}

// Processing -> Calculating dynamic statistics (Total, Active, Preferred, On Hold)
$total_suppliers = count($admin_suppliers);
$active_suppliers = 0;
$preferred_suppliers = 0;
$on_hold_suppliers = 0;
foreach ($admin_suppliers as $s) {
    $status = strtolower($s['status']);
    if ($status === 'active')
        $active_suppliers++;
    elseif ($status === 'preferred')
        $preferred_suppliers++;
    elseif ($status === 'on_hold')
        $on_hold_suppliers++;
}
?>

<!-- Suppliers / Garments View -->
<div class="flex-1 flex flex-col overflow-hidden bg-gray-50/50">
    <!-- Top Navigation Tabs (Garments vs Suppliers) -->
    <div class="flex border-b border-gray-200 px-8 bg-white shrink-0">
        <a href="/admin-suppliers?type=garment" class="px-6 py-4 font-bold text-sm border-b-2 flex items-center gap-2.5 transition-all <?php echo $is_garment ? 'border-brand text-brand bg-brand/5' : 'border-transparent text-gray-500 hover:text-gray-900'; ?>">
            <i class="ti ti-building-factory-2 text-xl"></i>
            <span>Garments (End Products)</span>
        </a>
        <a href="/admin-suppliers?type=supplier" class="px-6 py-4 font-bold text-sm border-b-2 flex items-center gap-2.5 transition-all <?php echo !$is_garment ? 'border-brand text-brand bg-brand/5' : 'border-transparent text-gray-500 hover:text-gray-900'; ?>">
            <i class="ti ti-truck text-xl"></i>
            <span>Suppliers (Raw Materials)</span>
        </a>
    </div>

    <!-- Main Container -->
    <div class="flex-1 flex overflow-hidden">
        <!-- List Pane -->
        <div id="suppliers-container" class="flex-1 flex flex-col min-w-0 bg-white">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= $is_garment ? 'Garments Management' : 'Raw Material Suppliers' ?></h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $is_garment ? 'Manage apparel manufacturing partners, garment production orders, and finished goods.' : 'Manage suppliers of raw fabrics, elastics, thread, and packaging materials.' ?>
                    </p>
                </div>
                <!-- Stats -->
                <div class="flex items-center gap-6">
                    <div class="flex gap-4">
                        <div class="text-center">
                            <p class="text-[15px] font-black text-gray-900"><?= $total_suppliers ?></p>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Total</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[15px] font-black text-emerald-600"><?= $active_suppliers ?></p>
                            <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mt-0.5">Active</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[15px] font-black text-blue-600"><?= $preferred_suppliers ?></p>
                            <p class="text-[9px] font-bold text-blue-500 uppercase tracking-widest mt-0.5">Preferred</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[15px] font-black text-amber-600"><?= $on_hold_suppliers ?></p>
                            <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest mt-0.5">On Hold</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                        <button
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm"
                            onclick="downloadPDF('suppliers-list-container', '<?= $is_garment ? 'Garments_List' : 'Suppliers_List' ?>')">
                            <i class="ti ti-printer text-lg"></i> Export PDF
                        </button>
                        <button onclick="openSupplierModal('add')"
                            class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                            <i class="ti ti-plus text-lg"></i> <?= $is_garment ? 'Register Garment Factory' : 'Add Material Supplier' ?>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($is_garment): ?>
            <!-- Business Workflow Informational Banner for Garments -->
            <div class="px-8 py-3 bg-amber-500/10 border-b border-amber-500/20 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <i class="ti ti-scissors text-amber-700 text-xl shrink-0"></i>
                    <p class="text-xs text-amber-900 font-medium">
                        <strong>Garment Process:</strong> Kesara Enterprises cuts raw fabrics in-house and dispatches cut components to external garment partners. Large orders typically take <strong>2–3 months (60–90 days)</strong> for garments to finish.
                    </p>
                </div>
                <span class="text-[10px] font-extrabold bg-amber-200/60 text-amber-800 px-3 py-1 rounded-full uppercase tracking-wider shrink-0">Internal Cut & CMT</span>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="px-8 py-4 border-b border-gray-100 bg-gray-50/30 flex items-center gap-4">
                <div class="relative flex-1 group">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input id="supp-search" type="text" placeholder="<?= $is_garment ? 'Search garment factory name, contact or email...' : 'Search raw material supplier, fabric type or contact...' ?>"
                        class="w-full pl-11 pr-4 py-2.5 bg-white border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand rounded-xl text-sm transition-all outline-none">
                </div>
                <select id="supp-status"
                    class="px-4 py-2.5 bg-white border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand rounded-xl text-sm font-medium transition-all outline-none cursor-pointer">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="preferred">Preferred</option>
                    <option value="on_hold">On Hold</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- List Content -->
            <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar pb-10" id="suppliers-list-container">
                <div class="min-w-[800px] p-6 space-y-1">
                    <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                        <thead>
                            <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                <th class="px-4 py-3 rounded-l-xl w-64"><?= $is_garment ? 'Garment Factory' : 'Supplier Name' ?></th>
                                <th class="px-4 py-3 w-40">Contact Person</th>
                                <th class="px-4 py-3 w-48"><?= $is_garment ? 'Products Manufactured' : 'Supplied Raw Materials' ?></th>
                                <th class="px-4 py-3 w-44">Production Lead Time</th>
                                <th class="px-4 py-3 text-right rounded-r-xl w-32">Status</th>
                            </tr>
                        </thead>
                        <tbody id="supplier-list">
                            <?php if (empty($admin_suppliers)): ?>
                                <tr id="empty-state">
                                    <td colspan="5" class="p-12 text-center text-gray-400 text-sm">No <?= $is_garment ? 'garment factories' : 'suppliers' ?> found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admin_suppliers as $idx => $s): ?>
                                    <tr id="supplier-row-<?= $idx ?>"
                                        class="supplier-row bg-white cursor-pointer hover:bg-gray-50/50 transition-all group shadow-sm"
                                        data-idx="<?= $idx ?>" data-id="<?= htmlspecialchars($s['id']) ?>"
                                        data-initials="<?= htmlspecialchars($s['initials']) ?>"
                                        data-av="<?= htmlspecialchars($s['av']) ?>" data-name="<?= htmlspecialchars($s['name']) ?>"
                                        data-email="<?= htmlspecialchars($s['email']) ?>"
                                        data-cat="<?= htmlspecialchars($s['cat']) ?>"
                                        data-contact="<?= htmlspecialchars($s['contact']) ?>"
                                        data-lead="<?= htmlspecialchars($s['lead']) ?>"
                                        data-lead-days="<?= htmlspecialchars($s['lead_days']) ?>"
                                        data-badge="<?= htmlspecialchars($s['badge']) ?>"
                                        data-badgetext="<?= htmlspecialchars($s['badgeText']) ?>"
                                        data-status="<?= htmlspecialchars(strtolower($s['status'])) ?>"
                                        data-phone="<?= htmlspecialchars($s['phone']) ?>"
                                        data-addr="<?= htmlspecialchars($s['addr']) ?>"
                                        data-terms="<?= htmlspecialchars($s['terms']) ?>"
                                        data-products="<?= htmlspecialchars($s['products']) ?>"
                                        data-all-products="<?= htmlspecialchars($s['all_products']) ?>"
                                        data-items-raw="<?= htmlspecialchars($s['items_raw'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-hold-reason="<?= htmlspecialchars($s['hold_reason']) ?>"
                                        data-ontimew="<?= htmlspecialchars($s['ontimeW']) ?>"
                                        data-ontime="<?= htmlspecialchars($s['ontime']) ?>"
                                        data-qualityw="<?= htmlspecialchars($s['qualityW']) ?>"
                                        data-quality="<?= htmlspecialchars($s['quality']) ?>"
                                        data-orders="<?= htmlspecialchars($s['orders']) ?>"
                                        data-spend="<?= htmlspecialchars($s['spend']) ?>" onclick="selectSupplier(this)">
                                        <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs shadow-sm <?= $s['av'] ?>">
                                                    <?= $s['initials'] ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors">
                                                        <?= htmlspecialchars($s['name']) ?>
                                                    </p>
                                                    <p class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-tight">
                                                        <?= htmlspecialchars($s['email']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-medium text-gray-650">
                                            <?= htmlspecialchars($s['contact']) ?>
                                        </td>
                                        <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-medium text-gray-650">
                                            <div class="flex items-center gap-1.5 flex-nowrap whitespace-nowrap overflow-hidden max-w-[280px]">
                                                <?= $s['products'] ?>
                                            </div>
                                        </td>
                                        <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-bold text-gray-900">
                                            <span class="px-2.5 py-1 bg-gray-100 rounded-lg text-gray-700">
                                                <i class="ti ti-clock text-gray-400 mr-1"></i><?= htmlspecialchars($s['lead']) ?>
                                            </span>
                                        </td>
                                        <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-right">
                                            <span class="px-3 py-1 <?= $s['badge'] ?> border rounded-full text-[9px] font-bold uppercase tracking-wider whitespace-nowrap shadow-sm">
                                                <?= htmlspecialchars($s['badgeText']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Controls -->
                    <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between bg-white" id="pagination-controls">
                        <p class="text-xs text-gray-500 font-medium" id="pagination-info">Showing 0 to 0 of 0 entries</p>
                        <div class="flex items-center gap-2" id="pagination-buttons"></div>
                    </div>
                </div>
            </div>

            <!-- Detail Pane -->
            <div id="supplier-detail-backdrop"
                class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300"
                onclick="closeSupplierDetailPane()"></div>
            <div id="supplier-detail-pane"
                class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
                <div class="p-8 flex-1 overflow-y-auto space-y-8">
                    <!-- Header -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div id="d-av" class="w-16 h-16 rounded-2xl flex items-center justify-center text-xl font-bold border shadow-md"></div>
                            <div>
                                <h2 id="d-name" class="text-xl font-extrabold text-gray-900"></h2>
                                <p id="d-email" class="text-xs font-semibold text-gray-400 mt-0.5"></p>
                                <span id="d-badge" class="inline-block mt-2"></span>
                            </div>
                        </div>
                        <button onclick="closeSupplierDetailPane()" class="p-2 text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-50 transition-colors">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>

                    <!-- Contact & Operations -->
                    <div class="grid grid-cols-2 gap-4 p-5 bg-gray-50/50 rounded-2xl border border-gray-100 text-xs">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Contact Person</p>
                            <p id="d-contact" class="font-bold text-gray-900 mt-1"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Phone</p>
                            <p id="d-phone" class="font-bold text-gray-900 mt-1"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Factory / Office Address</p>
                            <p id="d-addr" class="font-medium text-gray-700 mt-1"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Payment Terms</p>
                            <p id="d-terms" class="font-bold text-brand mt-1"></p>
                        </div>
                    </div>

                    <!-- Products / Materials List -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider"><?= $is_garment ? 'End Products Manufactured' : 'Raw Materials Supplied' ?></h3>
                        <div id="d-products" class="grid grid-cols-2 gap-2.5"></div>
                    </div>

                    <!-- Performance Ratings -->
                    <div class="space-y-4 p-5 bg-gray-50/30 rounded-2xl border border-gray-100">
                        <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider">Partner Metrics & History</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1">
                                    <span class="text-gray-500">On-Time Delivery Rate</span>
                                    <span id="d-ot" class="text-emerald-700"></span>
                                </div>
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="d-bar-ot" class="h-full rounded-full transition-all duration-500"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1">
                                    <span class="text-gray-500">Quality Assurance Score</span>
                                    <span id="d-qual" class="text-emerald-700"></span>
                                </div>
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="d-bar-qual" class="h-full rounded-full transition-all duration-500"></div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-gray-200/60 text-center">
                            <div>
                                <p id="d-pos" class="text-lg font-black text-gray-900"></p>
                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Total Orders Raised</p>
                            </div>
                            <div>
                                <p id="d-spend" class="text-lg font-black text-brand"></p>
                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Total Expenditure</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-6 border-t border-gray-100 bg-white flex gap-3">
                    <button onclick="openSupplierModal('edit', currentSupplierId)"
                        class="flex-1 py-3 bg-brand text-brand-light font-bold rounded-xl text-xs hover:opacity-90 transition-all shadow-md shadow-brand/10 flex items-center justify-center gap-2">
                        <i class="ti ti-edit text-base"></i> Edit <?= $is_garment ? 'Garment Factory' : 'Supplier' ?>
                    </button>
                    <button onclick="closeSupplierDetailPane()"
                        class="px-6 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl text-xs hover:bg-gray-200 transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dialog (Add / Edit) -->
<div id="supplierModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h3 id="modalTitle" class="text-xl font-extrabold text-gray-900"><?= $is_garment ? 'Register Garment Factory' : 'Add Material Supplier' ?></h3>
                <p class="text-xs text-gray-500 mt-0.5"><?= $is_garment ? 'Add an external garment factory partner for innerwear finishing.' : 'Add a raw material vendor supplying fabric, elastic, or packaging.' ?></p>
            </div>
            <button onclick="closeSupplierModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-xl hover:bg-gray-50"><i class="ti ti-x text-xl"></i></button>
        </div>

        <form id="supplierForm" method="POST" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="save">
            <input type="hidden" name="supplier_id" id="supplierIdInput" value="">
            <input type="hidden" name="supplier_type" value="<?= htmlspecialchars($view_type) ?>">
            <input type="hidden" name="supplied_items" id="suppliedItemsInput" value="[]">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5"><?= $is_garment ? 'Garment Company / Factory Name' : 'Company Name' ?> *</label>
                    <input type="text" name="company_name" required placeholder="<?= $is_garment ? 'e.g. MAS Matrix Garment Factory' : 'e.g. Sri Lanka Cotton Mills' ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Email Address *</label>
                    <input type="email" name="email" required placeholder="orders@factory.lk" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Contact Person</label>
                    <input type="text" name="contact_person" placeholder="Mr. / Ms. Contact Name" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Phone (0XXXXXXXXX) *</label>
                    <input type="text" name="phone" required placeholder="0771234567" pattern="0[0-9]{9}" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Payment Terms</label>
                    <select name="payment_terms" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                        <option value="Net 30">Net 30 Days</option>
                        <option value="Net 45">Net 45 Days</option>
                        <option value="Net 60" <?= $is_garment ? 'selected' : '' ?>>Net 60 Days</option>
                        <option value="COD">Cash on Delivery (COD)</option>
                        <option value="Net 15">Net 15 Days</option>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Factory / Office Address</label>
                    <input type="text" name="address" placeholder="EPZ Biyagama, Western Province" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                        <?php if ($is_garment): ?>
                            <option value="Innerwear Manufacturing">Innerwear Manufacturing</option>
                            <option value="Cut-Make-Trim (CMT)">Cut-Make-Trim (CMT)</option>
                            <option value="Apparel Finishing">Apparel Finishing</option>
                            <option value="Sub-contractor Factory">Sub-contractor Factory</option>
                        <?php else: ?>
                            <option value="Fabric">Fabric (Cotton, Spandex, Modal)</option>
                            <option value="Elastic / Trims">Elastic & Trims</option>
                            <option value="Packaging">Packaging (Boxes, Polybags)</option>
                            <option value="Threads & Buttons">Threads & Accessories</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Estimated Lead Time (Days)</label>
                    <input type="number" name="lead_time" min="1" value="<?= $is_garment ? '60' : '7' ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" id="modalStatusSelect" onchange="toggleHoldReason()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand focus:bg-white transition-all">
                        <option value="active">Active</option>
                        <option value="preferred">Preferred Partner</option>
                        <option value="on_hold">On Hold</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div id="holdReasonContainer" class="hidden">
                <label class="block text-xs font-bold text-amber-700 uppercase tracking-wider mb-1.5">Reason for Hold</label>
                <input type="text" name="hold_reason" placeholder="e.g. Production capacity review" class="w-full px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-xs font-bold text-amber-900 outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <!-- Items / Products Tag Chips Builder -->
            <div class="space-y-2 pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider"><?= $is_garment ? 'Finished Products Manufactured' : 'Raw Material Items Supplied' ?></label>
                    <span id="modalItemCountBadge" class="hidden text-[10px] font-extrabold bg-brand/10 text-brand px-2 py-0.5 rounded-full">0</span>
                </div>
                <div id="modalSuppliedItemsContainer" class="p-3 bg-gray-50 border border-gray-200 rounded-2xl flex flex-wrap gap-2 min-h-[52px] items-center">
                    <p id="modalItemsEmptyHint" class="text-xs text-gray-400 italic font-medium px-1">No items added yet. Use the inputs below.</p>
                </div>

                <div class="flex gap-2 relative" id="itemPickerWrapper">
                    <input type="text" id="modalAddItemInput" placeholder="<?= $is_garment ? 'Item name (e.g. Mens Cotton Briefs)' : 'Raw item (e.g. Combed Cotton Fabric)' ?>" oninput="onItemPickerInput(this.value)" onkeydown="onItemPickerKeyDown(event)" autocomplete="off" class="flex-1 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand">
                    <input type="number" id="modalAddItemCost" step="0.01" min="0" placeholder="Unit Cost LKR (Optional)" class="w-36 px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand">
                    <button type="button" onclick="modalAddSuppliedItem()" class="px-4 py-2.5 bg-gray-900 text-white rounded-xl text-xs font-bold hover:bg-black transition-all flex items-center gap-1.5"><i class="ti ti-plus"></i> Add</button>

                    <!-- Auto-complete dropdown -->
                    <div id="itemDropdownList" class="hidden absolute top-full left-0 right-36 mt-1 bg-white border border-gray-200 rounded-2xl shadow-2xl z-50 max-h-48 overflow-y-auto"></div>
                </div>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                <div id="deleteBtnContainer" class="hidden">
                    <button type="button" onclick="confirmDeleteFromModal()" class="px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50 rounded-xl transition-all"><i class="ti ti-trash mr-1"></i> Delete</button>
                </div>
                <div class="flex gap-3 ml-auto">
                    <button type="button" onclick="closeSupplierModal()" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl text-xs hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light font-bold rounded-xl text-xs hover:opacity-90 transition-all shadow-lg shadow-brand/20">Save Record</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    var availableProductsList = <?= $inv_products_json ?>;
    var dropdownFocusIdx = -1;

    function toggleHoldReason() {
        var statusSelect = document.getElementById('modalStatusSelect');
        var container = document.getElementById('holdReasonContainer');
        if (statusSelect && container) {
            if (statusSelect.value === 'on_hold') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
    }

    function onItemPickerInput(val) {
        var list = document.getElementById('itemDropdownList');
        if (!list) return;
        dropdownFocusIdx = -1;
        var query = val.trim().toLowerCase();
        if (!query) { hideItemDropdown(); return; }

        var matches = availableProductsList.filter(p => p.toLowerCase().includes(query));
        if (matches.length === 0) { hideItemDropdown(); return; }

        list.innerHTML = matches.map((m) => `
            <div class="px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-brand/10 hover:text-brand cursor-pointer transition-colors border-b border-gray-50 last:border-none" onclick="selectItemFromDropdown('${escapeHtml(m)}')">
                <i class="ti ti-package text-gray-400 mr-2"></i>${escapeHtml(m)}
            </div>
        `).join('');
        list.classList.remove('hidden');
    }

    function selectItemFromDropdown(val) {
        document.getElementById('modalAddItemInput').value = val;
        hideItemDropdown();
        document.getElementById('modalAddItemCost').focus();
    }

    function hideItemDropdown() {
        var list = document.getElementById('itemDropdownList');
        if (list) list.classList.add('hidden');
    }

    function onItemPickerKeyDown(e) {
        var list = document.getElementById('itemDropdownList');
        if (!list || list.classList.contains('hidden')) return;
        var rows = list.querySelectorAll('div');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            dropdownFocusIdx = Math.min(dropdownFocusIdx + 1, rows.length - 1);
            rows.forEach((r, i) => r.style.background = (i === dropdownFocusIdx) ? '#e8edf9' : '');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            dropdownFocusIdx = Math.max(dropdownFocusIdx - 1, 0);
            rows.forEach((r, i) => r.style.background = (i === dropdownFocusIdx) ? '#e8edf9' : '');
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (dropdownFocusIdx >= 0 && rows[dropdownFocusIdx]) {
                document.getElementById('modalAddItemInput').value = rows[dropdownFocusIdx].textContent.trim();
                hideItemDropdown();
                document.getElementById('modalAddItemCost').focus();
            } else { modalAddSuppliedItem(); }
        } else if (e.key === 'Escape') {
            hideItemDropdown();
        }
    }

    function barColor(w) { return w >= 90 ? '#10b981' : w >= 75 ? '#f59e0b' : '#ef4444'; }
    function barText(w) { return w >= 90 ? '#047857' : w >= 75 ? '#b45309' : '#b91c1c'; }

    function selectSupplier(el, openDrawer = true) {
        if (!el) return;
        document.querySelectorAll('.supplier-row').forEach(r => {
            r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
            r.classList.add('bg-white', 'border-gray-100');
        });
        el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
        el.classList.remove('bg-white', 'border-gray-100');

        if (openDrawer) {
            var pane = document.getElementById('supplier-detail-pane');
            var backdrop = document.getElementById('supplier-detail-backdrop');
            if (pane) pane.classList.remove('translate-x-full');
            if (backdrop) {
                backdrop.classList.remove('hidden');
                requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
            }
        }

        var av = document.getElementById('d-av');
        av.textContent = el.dataset.initials;
        av.className = 'w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4 ' + el.dataset.av;

        document.getElementById('d-name').textContent = el.dataset.name;
        document.getElementById('d-email').textContent = el.dataset.email;

        var badge = document.getElementById('d-badge');
        badge.className = 'mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border ' + el.dataset.badge;
        badge.textContent = el.dataset.badgetext;

        document.getElementById('d-contact').textContent = el.dataset.contact;
        document.getElementById('d-phone').textContent = el.dataset.phone;
        document.getElementById('d-addr').textContent = el.dataset.addr;
        document.getElementById('d-terms').textContent = el.dataset.terms;

        document.getElementById('d-products').innerHTML = el.dataset.allProducts || el.dataset.products;

        var ontimeW = parseInt(el.dataset.ontimew);
        document.getElementById('d-bar-ot').style.width = ontimeW + '%';
        document.getElementById('d-bar-ot').style.backgroundColor = barColor(ontimeW);
        document.getElementById('d-ot').textContent = el.dataset.ontime;
        document.getElementById('d-ot').style.color = barText(ontimeW);

        var qualityW = parseInt(el.dataset.qualityw);
        document.getElementById('d-bar-qual').style.width = qualityW + '%';
        document.getElementById('d-bar-qual').style.backgroundColor = barColor(qualityW);
        document.getElementById('d-qual').textContent = el.dataset.quality;
        document.getElementById('d-qual').style.color = barText(qualityW);

        document.getElementById('d-pos').textContent = el.dataset.orders;
        document.getElementById('d-spend').textContent = el.dataset.spend;

        currentSupplierId = el.dataset.id;
    }

    var currentSupplierId = null;
    var modalSuppliedItems = [];

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderModalTags() {
        var container = document.getElementById('modalSuppliedItemsContainer');
        if (!container) return;
        container.querySelectorAll('.supplied-tag').forEach(t => t.remove());

        var hint  = document.getElementById('modalItemsEmptyHint');
        var badge = document.getElementById('modalItemCountBadge');

        if (modalSuppliedItems.length === 0) {
            if (hint)  hint.classList.remove('hidden');
            if (badge) badge.classList.add('hidden');
        } else {
            if (hint)  hint.classList.add('hidden');
            if (badge) { badge.textContent = modalSuppliedItems.length; badge.classList.remove('hidden'); }
        }

        modalSuppliedItems.forEach((item, idx) => {
            var tag = document.createElement('span');
            tag.className = 'supplied-tag group flex items-center gap-1.5 pl-2 pr-2.5 py-1.5 bg-brand/5 border border-brand/20 rounded-xl text-xs font-bold text-brand hover:bg-brand/10 transition-all';
            let costStr = (item.cost !== null && item.cost !== undefined && item.cost !== '')
                ? `<span class="text-[10px] font-semibold text-gray-400 ml-0.5 mr-1">· LKR ${parseFloat(item.cost).toFixed(2)}</span>`
                : '';
            tag.innerHTML = `<i class="ti ti-package text-[10px] text-brand/50 mr-0.5"></i>${escapeHtml(item.name)}${costStr}<button type="button" onclick="modalRemoveTag(${idx})" class="ti ti-x text-[11px] text-brand/40 hover:text-red-500 transition-colors" title="Remove"></button>`;
            container.appendChild(tag);
        });
        document.getElementById('suppliedItemsInput').value = JSON.stringify(modalSuppliedItems);
    }

    function modalAddSuppliedItem() {
        var input = document.getElementById('modalAddItemInput');
        var costInput = document.getElementById('modalAddItemCost');
        var val = input.value.trim();
        var cost = costInput ? costInput.value.trim() : null;
        var exists = modalSuppliedItems.some(i => i.name.toLowerCase() === val.toLowerCase());
        if (val && !exists) {
            modalSuppliedItems.push({ name: val, cost: cost || null });
            renderModalTags();
            input.value = '';
            if (costInput) costInput.value = '';
        } else if (exists) {
            if (typeof showToast === 'function') showToast('Item already added.', 'warning');
        }
    }

    function modalRemoveTag(idx) {
        modalSuppliedItems.splice(idx, 1);
        renderModalTags();
    }

    function openSupplierModal(mode, id = null) {
        var form = document.getElementById('supplierForm');
        form.reset();
        document.getElementById('formAction').value = 'save';
        document.getElementById('supplierIdInput').value = '';
        modalSuppliedItems = [];
        renderModalTags();
        toggleHoldReason();
        if (form.querySelector('[name="lead_time"]')) {
            form.querySelector('[name="lead_time"]').value = <?= $is_garment ? 60 : 7 ?>;
        }

        if (mode === 'edit' && id) {
            document.getElementById('modalTitle').textContent = '<?= $is_garment ? 'Edit Garment Factory' : 'Edit Material Supplier' ?>';
            document.getElementById('supplierIdInput').value = id;
            var row = document.querySelector(`.supplier-row[data-id="${id}"]`);
            if (row) {
                form.querySelector('[name="company_name"]').value = row.dataset.name;
                form.querySelector('[name="email"]').value = row.dataset.email;
                form.querySelector('[name="contact_person"]').value = row.dataset.contact;
                form.querySelector('[name="phone"]').value = row.dataset.phone;
                form.querySelector('[name="address"]').value = row.dataset.addr;

                let statusVal = row.dataset.status;
                let statusSelect = form.querySelector('[name="status"]');
                Array.from(statusSelect.options).forEach(opt => {
                    if (opt.value.toLowerCase() === statusVal.toLowerCase()) {
                        statusSelect.value = opt.value;
                    }
                });

                form.querySelector('[name="payment_terms"]').value = row.dataset.terms;
                if (form.querySelector('[name="lead_time"]')) {
                    form.querySelector('[name="lead_time"]').value = row.dataset.leadDays || (<?= $is_garment ? 60 : 7 ?>);
                }
                form.querySelector('[name="hold_reason"]').value = row.dataset.holdReason || '';
                toggleHoldReason();

                var itemsRaw = row.dataset.itemsRaw;
                if (itemsRaw) {
                    try {
                        modalSuppliedItems = JSON.parse(itemsRaw);
                    } catch (e) {
                        modalSuppliedItems = itemsRaw.split(',').map(s => s.trim()).filter(s => s).map(s => ({ name: s, cost: null }));
                    }
                    renderModalTags();
                }

                document.getElementById('deleteBtnContainer').style.display = 'block';
            }
        } else {
            document.getElementById('modalTitle').textContent = '<?= $is_garment ? 'Register Garment Factory' : 'Add Material Supplier' ?>';
            document.getElementById('deleteBtnContainer').style.display = 'none';
        }

        document.getElementById('supplierModal').classList.remove('hidden');
    }

    function closeSupplierModal() {
        document.getElementById('supplierModal').classList.add('hidden');
    }

    function confirmDeleteFromModal() {
        if (confirm('Are you sure you want to move this record to the Recycle Bin?')) {
            document.getElementById('formAction').value = 'delete';
            document.getElementById('supplierForm').submit();
        }
    }

    function closeSupplierDetailPane() {
        var pane = document.getElementById('supplier-detail-pane');
        var backdrop = document.getElementById('supplier-detail-backdrop');
        if (pane) pane.classList.add('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('opacity-100');
            setTimeout(() => backdrop.classList.add('hidden'), 300);
        }
    }

    // Pagination & Search
    var itemsPerPage = 10;
    var currentPage = 1;

    function applySupplierFilters() {
        var q = (document.getElementById('supp-search')?.value || '').toLowerCase().trim();
        var status = (document.getElementById('supp-status')?.value || '').toLowerCase();
        var rows = Array.from(document.querySelectorAll('.supplier-row'));
        var visibleRows = [];

        rows.forEach(r => {
            var name = (r.dataset.name || '').toLowerCase();
            var email = (r.dataset.email || '').toLowerCase();
            var contact = (r.dataset.contact || '').toLowerCase();
            var rStatus = (r.dataset.status || '').toLowerCase();

            var matchQ = !q || name.includes(q) || email.includes(q) || contact.includes(q);
            var matchStatus = !status || status === 'all' || rStatus === status;

            if (matchQ && matchStatus) {
                visibleRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });

        var totalItems = visibleRows.length;
        var totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;

        var start = (currentPage - 1) * itemsPerPage;
        var end = start + itemsPerPage;

        visibleRows.forEach((r, idx) => {
            if (idx >= start && idx < end) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });

        var info = document.getElementById('pagination-info');
        if (info) info.textContent = `Showing ${totalItems > 0 ? start + 1 : 0} to ${Math.min(end, totalItems)} of ${totalItems} entries`;
        renderPaginationButtons(totalPages);
    }

    function renderPaginationButtons(totalPages) {
        var btnCont = document.getElementById('pagination-buttons');
        if (!btnCont) return;
        btnCont.innerHTML = '';

        for (let i = 1; i <= totalPages; i++) {
            var btn = document.createElement('button');
            btn.className = `px-3 py-1.5 rounded-lg text-xs font-bold ${i === currentPage ? 'bg-brand text-brand-light' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`;
            btn.textContent = i;
            btn.onclick = () => { currentPage = i; applySupplierFilters(); };
            btnCont.appendChild(btn);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('supp-search')?.addEventListener('input', () => { currentPage = 1; applySupplierFilters(); });
        document.getElementById('supp-status')?.addEventListener('change', () => { currentPage = 1; applySupplierFilters(); });
        applySupplierFilters();
        var firstRow = document.querySelector('.supplier-row');
        if (firstRow) selectSupplier(firstRow, false);
    });
</script>
