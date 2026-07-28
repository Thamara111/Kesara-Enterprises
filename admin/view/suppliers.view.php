<?php
/**
 * Suppliers Management View
 * Handles the display and management of vendor/supplier profiles.
 * Includes status tracking (Active, On Hold) and contact details.
 */

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        $name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
        $category = trim($_POST['category'] ?? 'Fabric');
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
                    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, email = ?, contact_person = ?, phone = ?, address = ?, payment_terms = ?, category = ?, status = ?, hold_reason = ?, hold_since = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $category, $status, $hold_reason, $hold_since, $supplier_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, email, contact_person, phone, address, payment_terms, category, status, hold_reason, hold_since) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $category, $status, $hold_reason, $hold_since]);
                    $supplier_id = $pdo->lastInsertId();
                    
                    if (file_exists(__DIR__ . "/../../src/Mailer.php")) {
                        require_once __DIR__ . "/../../src/Mailer.php";
                        $subject = "Welcome to Kesara Enterprises Supplier Network";
                        $body = "<h3>Hello " . htmlspecialchars($contact_person) . ",</h3><p>Your company <strong>" . htmlspecialchars($name) . "</strong> has been registered as a supplier with Kesara Enterprises.</p><p>We look forward to working with you.</p>";
                        if(class_exists('\App\Mailer')) {
                            \App\Mailer::send($email, $subject, $body);
                        }
                    }
                }

                $del_stmt = $pdo->prepare("DELETE FROM supplier_items WHERE supplier_id = ?");
                $del_stmt->execute([$supplier_id]);
                
                if (!empty($items_arr)) {
                    $ins_stmt = $pdo->prepare("INSERT INTO supplier_items (supplier_id, item_name, unit_cost) VALUES (?, ?, ?)");
                    foreach ($items_arr as $itm) {
                        $cost = isset($itm['cost']) && is_numeric($itm['cost']) ? $itm['cost'] : null;
                        $ins_stmt->execute([$supplier_id, $itm['name'], $cost]);
                    }
                }
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Supplier saved successfully.', 'success'); });</script>";
            } catch (Exception $e) {
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Error saving supplier.', 'error'); });</script>";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        if ($supplier_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$supplier_id]);
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Supplier deleted successfully.', 'success'); });</script>";
            } catch (Exception $e) {
                echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('Error deleting supplier.', 'error'); });</script>";
            }
        }
    }
}

$admin_suppliers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT s.id, s.name, s.email, s.contact_person AS contact, s.phone, s.address AS addr, s.payment_terms AS terms, s.category AS cat, s.status, s.hold_reason, s.hold_since 
                             FROM suppliers s");
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
            $products_html = "";
            foreach ($items_rows as $row) {
                $items_arr[] = ['name' => $row['item_name'], 'cost' => $row['unit_cost']];
                $cst_str = $row['unit_cost'] !== null ? ' - LKR ' . number_format((float)$row['unit_cost'], 2) : '';
                $products_html .= '<span class="px-3 py-1 bg-gray-50 border border-gray-100 rounded-lg text-[10px] font-medium text-gray-600 uppercase tracking-wider">' . htmlspecialchars($row['item_name']) . $cst_str . '</span>';
            }
            $items_raw = json_encode($items_arr);

            $sp_stmt = $pdo->prepare("SELECT AVG(lead_days) FROM supplier_products WHERE supplier_id = ?");
            $sp_stmt->execute([$s['id']]);
            $avg_lead = $sp_stmt->fetchColumn();
            $lead = $avg_lead ? round($avg_lead) . ' days' : '7 days';

            $po_stmt = $pdo->prepare("SELECT COUNT(*) AS pos, SUM(total) AS spend FROM purchase_orders WHERE supplier_id = ?");
            $po_stmt->execute([$s['id']]);
            $po_metrics = $po_stmt->fetch();
            $pos_count = (int) ($po_metrics['pos'] ?? 0);
            $spend_val = (float) ($po_metrics['spend'] ?? 0);
            $spend = $spend_val >= 1000000 ? 'LKR ' . number_format($spend_val / 1000000, 1) . 'M' : 'LKR ' . number_format($spend_val / 1000, 0) . 'K';

            $ontime = $s['id'] == 1 ? '96%' : ($s['id'] == 2 ? '88%' : ($s['id'] == 3 ? '94%' : ($s['id'] == 4 ? '71%' : '—')));
            $ontimeW = $s['id'] == 1 ? 96 : ($s['id'] == 2 ? 88 : ($s['id'] == 3 ? 94 : ($s['id'] == 4 ? 71 : 0)));
            $quality = $s['id'] == 1 ? '98%' : ($s['id'] == 2 ? '91%' : ($s['id'] == 3 ? '99%' : ($s['id'] == 4 ? '84%' : '—')));
            $qualityW = $s['id'] == 1 ? 98 : ($s['id'] == 2 ? 91 : ($s['id'] == 3 ? 99 : ($s['id'] == 4 ? 84 : 0)));

            $status_lower = strtolower($s['status']);
            if ($status_lower === 'preferred') {
                $badge = 'bg-blue-50 text-blue-700';
                $badgeText = 'Preferred';
            } elseif ($status_lower === 'active') {
                $badge = 'bg-emerald-50 text-emerald-700';
                $badgeText = 'Active';
            } elseif ($status_lower === 'on_hold') {
                $badge = 'bg-amber-50 text-amber-700';
                $badgeText = 'On hold';
            } else {
                $badge = 'bg-gray-100 text-gray-500';
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
                'products' => $products_html,
                'items_raw' => $items_raw,
                'hold_reason' => $s['hold_reason'] ?? '',
                'lead' => $lead,
                'cat' => $s['cat'] ?? 'Fabric',
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

$inv_options = '<option value="">Select an item to add...</option>';
if (isset($pdo) && $pdo !== null) {
    try {
        $prod_list = $pdo->query("SELECT p.id AS p_id, p.name AS p_name FROM products p ORDER BY p.name ASC")->fetchAll();
        foreach ($prod_list as $prod) {
            $pName = htmlspecialchars($prod['p_name']);
            $inv_options .= '<option value="' . $pName . '">' . $pName . '</option>';
        }
    } catch (\Exception $e) {
        // Ignore
    }
}


// Calculate dynamic stats
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
<!-- Suppliers View -->
<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div id="suppliers-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Suppliers</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your supply chain and partner relationships.</p>
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
                    <button class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm"
                        onclick="downloadPDF('suppliers-list-container', 'Suppliers_List')">
                        <i class="ti ti-printer text-lg"></i> Export PDF
                    </button>
                    <button onclick="openSupplierModal('add')" class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        <i class="ti ti-plus text-lg"></i> Add Supplier
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-8 py-4 border-b border-gray-100 bg-gray-50/30 flex items-center gap-4">
            <div class="relative flex-1 group">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                <input id="supp-search" type="text" placeholder="Search supplier name, email or contact..."
                    class="w-full pl-11 pr-4 py-2.5 bg-white border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand rounded-xl text-sm transition-all outline-none">
            </div>
            <select id="supp-cat"
                class="px-4 py-2.5 bg-white border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand rounded-xl text-sm font-medium transition-all outline-none cursor-pointer">
                <option value="all">All Categories</option>
                <option value="fabric">Fabric</option>
                <option value="elastic / trims">Elastic / Trims</option>
                <option value="packaging">Packaging</option>
            </select>
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
                            <th class="px-4 py-3 rounded-l-xl w-64">Supplier Name</th>
                            <th class="px-4 py-3 w-40">Category</th>
                            <th class="px-4 py-3 w-40">Contact Person</th>
                            <th class="px-4 py-3 w-32">Lead Time</th>
                            <th class="px-4 py-3 text-right rounded-r-xl w-32">Status</th>
                        </tr>
                    </thead>
                    <tbody id="supplier-list">
                        <?php if (empty($admin_suppliers)): ?>
                            <tr id="empty-state">
                                <td colspan="5" class="p-12 text-center text-gray-400 text-sm">No suppliers found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admin_suppliers as $idx => $s): ?>
                                <tr id="supplier-row-<?= $idx ?>"
                                    class="supplier-row bg-white cursor-pointer hover:bg-gray-50/50 transition-all group shadow-sm"
                                    data-idx="<?= $idx ?>" data-id="<?= htmlspecialchars($s['id']) ?>"
                                    data-initials="<?= htmlspecialchars($s['initials']) ?>"
                                    data-av="<?= htmlspecialchars($s['av']) ?>" data-name="<?= htmlspecialchars($s['name']) ?>"
                                    data-email="<?= htmlspecialchars($s['email']) ?>" data-cat="<?= htmlspecialchars($s['cat']) ?>"
                                    data-contact="<?= htmlspecialchars($s['contact']) ?>"
                                    data-lead="<?= htmlspecialchars($s['lead']) ?>"
                                    data-badge="<?= htmlspecialchars($s['badge']) ?>"
                                    data-badgetext="<?= htmlspecialchars($s['badgeText']) ?>"
                                    data-status="<?= htmlspecialchars(strtolower($s['status'])) ?>"
                                    data-phone="<?= htmlspecialchars($s['phone']) ?>"
                                    data-addr="<?= htmlspecialchars($s['addr']) ?>"
                                    data-terms="<?= htmlspecialchars($s['terms']) ?>"
                                    data-products="<?= htmlspecialchars($s['products']) ?>"
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
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                        <span class="text-xs font-semibold text-gray-500 bg-gray-50 px-2 py-1 rounded-lg border"><?= htmlspecialchars($s['cat']) ?></span>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-medium text-gray-650">
                                        <?= htmlspecialchars($s['contact']) ?>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-xs font-medium text-gray-900">
                                        <?= htmlspecialchars($s['lead']) ?>
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
                <div class="flex items-center gap-2" id="pagination-buttons">
                    <!-- Buttons injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Pane -->
    <!-- Backdrop -->
    <div id="supplier-detail-backdrop"
        class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300"
        onclick="closeSupplierDetailPane()"></div>
    <div id="supplier-detail-pane"
        class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto space-y-8">
            <!-- Profile Header -->
            <div class="flex flex-col items-center text-center relative">
                <button onclick="closeSupplierDetailPane()"
                    class="absolute top-0 right-0 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none"
                    aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
                <div id="d-av"
                    class="w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4">
                    NK</div>
                <h2 id="d-name" class="text-xl font-bold text-gray-900 tracking-tight">Sri Lanka Cotton Mills</h2>
                <p id="d-email" class="text-sm text-gray-500 mt-1">slcm@cottonmills.lk</p>
                <span id="d-badge"
                    class="mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border">Preferred</span>
            </div>

            <!-- Details Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Details</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Contact Person</span>
                        <span id="d-contact" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Phone</span>
                        <span id="d-phone" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Address</span>
                        <span id="d-addr" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Payment Terms</span>
                        <span id="d-terms" class="text-xs font-bold text-gray-900"></span>
                    </div>
                </div>
            </section>

            <!-- Supplied Items Badges -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Supplied Items</h3>
                <div id="d-products" class="flex flex-wrap gap-2">
                    <!-- Badges injected dynamically -->
                </div>
            </section>

            <!-- Metrics -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Performance</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">On-time delivery</span>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-16 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                                <div id="d-bar-ot" class="h-full rounded-full transition-all duration-500"
                                    style="width: 96%"></div>
                            </div>
                            <span id="d-ot" class="text-xs font-bold">96%</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Quality pass rate</span>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-16 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                                <div id="d-bar-qual" class="h-full rounded-full transition-all duration-500"
                                    style="width: 98%"></div>
                            </div>
                            <span id="d-qual" class="text-xs font-bold">98%</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Active purchase orders</span>
                        <span id="d-pos" class="text-xs font-bold text-gray-900">47</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Total spend</span>
                        <span id="d-spend" class="text-xs font-bold text-gray-900">LKR 1.2M</span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Action Footer (Sticky) -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
            <a href="/admin-purchase-orders"
                class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all">
                <i class="ti ti-shopping-cart text-lg"></i>
                Create Purchase Order ↗
            </a>
            <div class="grid grid-cols-2 gap-3">
                <button id="d-edit-btn" onclick="openSupplierModal('edit', currentSupplierId)"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-edit text-base"></i>
                    Edit
                </button>
                <button id="d-hold-btn"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-650 hover:bg-red-50 transition-all">
                    <i class="ti ti-ban text-base"></i>
                    Hold
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div id="supplierModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h2 id="modalTitle" class="text-xl font-bold text-gray-900">Add New Supplier</h2>
            <button onclick="closeSupplierModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="ti ti-x text-2xl"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <form method="POST" id="supplierForm" action="/admin-suppliers" class="space-y-6" data-turbo="false">
                <input type="hidden" name="action" id="formAction" value="save">
                <input type="hidden" name="supplier_id" id="supplierIdInput" value="">
                <input type="hidden" name="supplied_items" id="suppliedItemsInput" value="">

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Supplier Name *</label>
                        <input type="text" name="company_name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Name *</label>
                        <input type="text" name="contact_person" required class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Email Address *</label>
                        <input type="email" name="email" required pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number *</label>
                        <input type="tel" name="phone" required pattern="^0[0-9]{9}$" title="Phone number must start with 0 and contain exactly 10 digits" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                    </div>
                    <div class="space-y-2 col-span-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Registered Address</label>
                        <textarea name="address" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none resize-none"></textarea>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Current Status *</label>
                        <select name="status" id="modalStatusSelect" onchange="toggleHoldReason()" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                            <option value="active">Active</option>
                            <option value="preferred">Preferred</option>
                            <option value="on_hold">On Hold</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Terms</label>
                        <input type="text" name="payment_terms" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none" placeholder="e.g. Net 30">
                    </div>
                    <div class="space-y-2 hidden" id="holdReasonContainer">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Hold Reason</label>
                        <input type="text" name="hold_reason" id="modalHoldReason" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none" placeholder="If on hold">
                    </div>
                </div>

                <!-- Supplied Items -->
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-3">Supplied Items</label>
                    <div class="flex flex-wrap gap-2 mb-4" id="modalSuppliedItemsContainer"></div>
                    <div class="flex gap-2">
                        <select id="modalAddItemInput" class="flex-1 px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none">
                            <?= $inv_options ?>
                        </select>
                        <input type="number" id="modalAddItemCost" step="0.01" min="0" class="w-32 px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-brand/20 outline-none" placeholder="Unit Cost">
                        <button type="button" onclick="modalAddSuppliedItem()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-xs font-bold hover:bg-gray-300 transition-all">Add Item</button>
                    </div>
                </div>

                <div class="pt-8 border-t border-gray-100 flex justify-between items-center">
                    <div id="deleteBtnContainer" style="display: none;">
                        <button type="button" onclick="modalDeleteSupplier()" class="px-4 py-2.5 bg-red-50 text-red-600 rounded-xl text-sm font-bold hover:bg-red-100 transition-all"><i class="ti ti-trash"></i> Delete</button>
                    </div>
                    <div class="flex gap-3 ml-auto">
                        <button type="button" onclick="closeSupplierModal()" class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:opacity-90 transition-all">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

        // Open drawer
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

        document.getElementById('d-products').innerHTML = el.dataset.products;

        // Performance
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

    function renderModalTags() {
        var container = document.getElementById('modalSuppliedItemsContainer');
        container.innerHTML = '';
        modalSuppliedItems.forEach((item, idx) => {
            var tag = document.createElement('span');
            tag.className = 'group flex items-center gap-2 px-3 py-1.5 bg-brand/5 border border-brand/20 rounded-lg text-xs font-bold text-brand';
            let costStr = item.cost ? ' - LKR ' + parseFloat(item.cost).toFixed(2) : '';
            tag.innerHTML = `${item.name}${costStr} <button type="button" onclick="modalRemoveTag(${idx})" class="ti ti-x hover:text-red-500"></button>`;
            container.appendChild(tag);
        });
        document.getElementById('suppliedItemsInput').value = JSON.stringify(modalSuppliedItems);
    }

    function modalAddSuppliedItem() {
        var input = document.getElementById('modalAddItemInput');
        var costInput = document.getElementById('modalAddItemCost');
        var val = input.value.trim();
        var cost = costInput ? costInput.value.trim() : null;
        var exists = modalSuppliedItems.some(i => i.name === val);
        if (val && !exists) {
            modalSuppliedItems.push({name: val, cost: cost || null});
            renderModalTags();
            input.value = '';
            if (costInput) costInput.value = '';
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

        if (mode === 'edit' && id) {
            document.getElementById('modalTitle').textContent = 'Edit Supplier';
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
                form.querySelector('[name="hold_reason"]').value = row.dataset.holdReason || '';
                toggleHoldReason();
                
                var itemsRaw = row.dataset.itemsRaw;
                if (itemsRaw) {
                    try {
                        modalSuppliedItems = JSON.parse(itemsRaw);
                    } catch(e) {
                        modalSuppliedItems = itemsRaw.split(',').map(s => s.trim()).filter(s => s).map(s => ({name: s, cost: null}));
                    }
                    renderModalTags();
                }
                
                document.getElementById('deleteBtnContainer').style.display = 'block';
            }
        } else {
            document.getElementById('modalTitle').textContent = 'Add New Supplier';
            document.getElementById('deleteBtnContainer').style.display = 'none';
        }
        document.getElementById('supplierModal').classList.remove('hidden');
    }

    function closeSupplierModal() {
        document.getElementById('supplierModal').classList.add('hidden');
    }

    function toggleHoldReason() {
        var status = document.getElementById('modalStatusSelect');
        var container = document.getElementById('holdReasonContainer');
        if (status && container) {
            if (status.value === 'on_hold') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
                document.getElementById('modalHoldReason').value = '';
            }
        }
    }

    function modalDeleteSupplier() {
        if(confirm("Are you sure you want to delete this supplier?")) {
            document.getElementById('formAction').value = 'delete';
            document.getElementById('supplierForm').submit();
        }
    }

    var currentPage = 1;
    var itemsPerPage = 15;

    function goToPage(page) {
        currentPage = page;
        applyFilters();
    }

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
        
        var prevDisabled = currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
        html += `<button onclick="${currentPage === 1 ? '' : 'goToPage(' + (currentPage - 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${prevDisabled}"><i class="ti ti-chevron-left"></i></button>`;

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

        var nextDisabled = currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
        html += `<button onclick="${currentPage === totalPages ? '' : 'goToPage(' + (currentPage + 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${nextDisabled}"><i class="ti ti-chevron-right"></i></button>`;

        buttons.innerHTML = html;
    }

    function applyFilters() {
        var q = (document.getElementById('supp-search')?.value || '').toLowerCase().trim();
        var cat = (document.getElementById('supp-cat')?.value || '').toLowerCase();
        var status = (document.getElementById('supp-status')?.value || '').toLowerCase();
        
        var list = document.getElementById('supplier-list');
        var rows = Array.from(document.querySelectorAll('.supplier-row'));
        var visibleRows = [];

        rows.forEach(r => {
            var match = true;
            var name = r.dataset.name.toLowerCase();
            var email = r.dataset.email.toLowerCase();
            var contact = r.dataset.contact.toLowerCase();
            var rowCat = r.dataset.cat.toLowerCase();
            var rowStatus = r.dataset.status.toLowerCase();

            if (q && !name.includes(q) && !email.includes(q) && !contact.includes(q)) match = false;
            if (cat !== 'all' && rowCat !== cat) match = false;
            if (status !== 'all' && rowStatus !== status) match = false;

            if (match) {
                visibleRows.push(r);
            } else {
                r.hidden = true;
                r.style.display = 'none';
            }
        });

        // Add empty state if needed
        var emptyState = document.getElementById('empty-state');
        if (emptyState) emptyState.remove();

        if (visibleRows.length === 0) {
            var tr = document.createElement('tr');
            tr.id = 'empty-state';
            tr.innerHTML = '<td colspan="5" class="p-12 text-center text-gray-400 text-sm">No suppliers match these filters.</td>';
            list.appendChild(tr);
            renderPagination(0, 0);
            return;
        }

        // Sort latest first (highest id)
        visibleRows.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));

        var totalItems = visibleRows.length;
        var totalPages = Math.ceil(totalItems / itemsPerPage);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var start = (currentPage - 1) * itemsPerPage;
        var end = start + itemsPerPage;

        visibleRows.forEach((r, index) => {
            if (index >= start && index < end) {
                r.hidden = false;
                r.style.display = '';
            } else {
                r.hidden = true;
                r.style.display = 'none';
            }
        });

        visibleRows.forEach(r => list.appendChild(r));
        renderPagination(totalItems, totalPages);
    }

    document.getElementById('supp-search')?.addEventListener('input', () => { currentPage = 1; applyFilters(); });
    document.getElementById('supp-cat')?.addEventListener('change', () => { currentPage = 1; applyFilters(); });
    document.getElementById('supp-status')?.addEventListener('change', () => { currentPage = 1; applyFilters(); });

    function closeSupplierDetailPane() {
        var pane = document.getElementById('supplier-detail-pane');
        var backdrop = document.getElementById('supplier-detail-backdrop');
        if (pane) pane.classList.add('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('hidden');
        }
        document.querySelectorAll('.supplier-row').forEach(r => {
            r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
            r.classList.add('bg-white', 'border-gray-100');
        });
    }

    // Initial Render
    applyFilters();
    var firstSupplier = document.querySelector('.supplier-row');
    if (firstSupplier) selectSupplier(firstSupplier, false);
    closeSupplierDetailPane();
</script>