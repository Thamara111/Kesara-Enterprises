<?php
/**
 * Suppliers Management View
 * Handles the display and management of vendor/supplier profiles.
 * Includes status tracking (Active, On Hold) and contact details.
 */
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

            $p_stmt = $pdo->prepare("SELECT item_name FROM supplier_items WHERE supplier_id = ?");
            $p_stmt->execute([$s['id']]);
            $items = $p_stmt->fetchAll(PDO::FETCH_COLUMN);

            $products_html = "";
            foreach ($items as $it) {
                $products_html .= '<span class="px-3 py-1 bg-gray-50 border border-gray-100 rounded-lg text-[10px] font-medium text-gray-600 uppercase tracking-wider">' . htmlspecialchars($it) . '</span>';
            }

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
        <div
            class="p-6 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-md sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Suppliers</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your supply chain and partner relationships</p>
            </div>
            <!-- Stats Grid -->
            <div class="grid grid-cols-4 gap-4 p-6">
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $total_suppliers ?></p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-t-4 border-t-emerald-500 text-center">
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Active</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $active_suppliers ?></p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-t-4 border-t-blue-500 text-center">
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">Preferred</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $preferred_suppliers ?></p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-t-4 border-t-amber-500 text-center">
                    <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">On Hold</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $on_hold_suppliers ?></p>
                </div>
            </div>
            <div class="flex gap-3">
                <button
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all shadow-sm"
                    onclick="downloadPDF('suppliers-container', 'Suppliers_List')">
                    <i class="ti ti-download text-lg"></i>
                    Export PDF
                </button>
                <a href="/admin-supplier-add"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                    <i class="ti ti-plus text-lg"></i>
                    Add Supplier
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-100 bg-white flex flex-col gap-4">
            <div class="flex gap-3">
                <div class="relative flex-1 group">
                    <i
                        class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input id="supp-search" type="text" placeholder="Search supplier name, email or contact..."
                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none">
                </div>
                <select id="supp-cat"
                    class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                    <option value="all">All Categories</option>
                    <option value="fabric">Fabric</option>
                    <option value="elastic / trims">Elastic / Trims</option>
                    <option value="packaging">Packaging</option>
                </select>
                <select id="supp-status"
                    class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="preferred">Preferred</option>
                    <option value="on_hold">On Hold</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- List Content -->
        <div class="flex-1 overflow-auto p-6">
            <div class="min-w-[750px]">
                <div
                    class="grid grid-cols-[40px_1.5fr_1fr_1fr_120px_100px] gap-4 px-4 py-3 bg-gray-50 rounded-xl mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    <span></span>
                    <span>Supplier Name</span>
                    <span>Category</span>
                    <span>Contact Person</span>
                    <span>Lead Time</span>
                    <span class="text-right">Status</span>
                </div>

                <div id="supplier-list" class="space-y-2">
                    <?php if (empty($admin_suppliers)): ?>
                        <p class="text-center text-gray-500 py-8 text-sm">No suppliers found.</p>
                    <?php else: ?>
                        <?php foreach ($admin_suppliers as $idx => $s): ?>
                            <div id="supplier-row-<?= $idx ?>"
                                class="supplier-row group grid grid-cols-[40px_1.5fr_1fr_1fr_120px_100px] gap-4 items-center p-4 rounded-2xl transition-all cursor-pointer bg-white border border-gray-100 hover:border-brand/30 hover:bg-gray-50/50"
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
                                data-ontimew="<?= htmlspecialchars($s['ontimeW']) ?>"
                                data-ontime="<?= htmlspecialchars($s['ontime']) ?>"
                                data-qualityw="<?= htmlspecialchars($s['qualityW']) ?>"
                                data-quality="<?= htmlspecialchars($s['quality']) ?>"
                                data-orders="<?= htmlspecialchars($s['orders']) ?>"
                                data-spend="<?= htmlspecialchars($s['spend']) ?>" onclick="selectSupplier(this)">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs <?= $s['av'] ?>">
                                    <?= $s['initials'] ?></div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors">
                                        <?= htmlspecialchars($s['name']) ?></p>
                                    <p class="text-xs text-gray-400 font-medium mt-0.5"><?= htmlspecialchars($s['email']) ?></p>
                                </div>
                                <span class="text-xs font-semibold text-gray-500"><?= htmlspecialchars($s['cat']) ?></span>
                                <span class="text-xs font-medium text-gray-650"><?= htmlspecialchars($s['contact']) ?></span>
                                <span class="text-xs font-medium text-gray-900"><?= htmlspecialchars($s['lead']) ?></span>
                                <div class="text-right">
                                    <span
                                        class="px-3 py-1 <?= $s['badge'] ?> border rounded-full text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($s['badgeText']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex justify-between items-center bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <span class="text-xs font-bold text-gray-400">SHOWING <?= $total_suppliers ?> OF
                        <?= $total_suppliers ?> SUPPLIERS</span>
                    <div class="flex gap-2">
                        <button
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-brand transition-colors"><i
                                class="ti ti-chevron-left"></i></button>
                        <button
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-brand-light font-bold text-xs">1</button>
                        <button
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-450 hover:text-brand transition-colors"><i
                                class="ti ti-chevron-right"></i></button>
                    </div>
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
        class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
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
                <a id="d-edit-link" href="#"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-edit text-base"></i>
                    Edit
                </a>
                <button id="d-hold-btn"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-650 hover:bg-red-50 transition-all">
                    <i class="ti ti-ban text-base"></i>
                    Hold
                </button>
            </div>
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

        document.getElementById('d-edit-link').href = '/admin-supplier-edit?id=' + el.dataset.id;
    }

    function applyFilters() {
        var q = (document.getElementById('supp-search')?.value || '').toLowerCase().trim();
        var cat = (document.getElementById('supp-cat')?.value || '').toLowerCase();
        var status = (document.getElementById('supp-status')?.value || '').toLowerCase();

        document.querySelectorAll('.supplier-row').forEach(r => {
            var visible = true;

            if (cat !== 'all' && r.dataset.cat.toLowerCase() !== cat) {
                visible = false;
            }
            if (status !== 'all' && r.dataset.status.toLowerCase() !== status) {
                visible = false;
            }
            if (q && !r.dataset.name.toLowerCase().includes(q) &&
                !r.dataset.email.toLowerCase().includes(q) &&
                !r.dataset.contact.toLowerCase().includes(q)) {
                visible = false;
            }

            r.style.display = visible ? '' : 'none';
        });
    }

    document.getElementById('supp-search')?.addEventListener('input', applyFilters);
    document.getElementById('supp-cat')?.addEventListener('change', applyFilters);
    document.getElementById('supp-status')?.addEventListener('change', applyFilters);

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
    var firstSupplier = document.querySelector('.supplier-row');
    if (firstSupplier) selectSupplier(firstSupplier, false);
    closeSupplierDetailPane();
</script>