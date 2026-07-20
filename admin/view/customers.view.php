<?php
/**
 * Customer Management View
 * Converted to Tailwind CSS for Kesara Enterprises Admin Panel
 * Handles listing, filtering, and displaying detailed information about registered customers.
 * It also computes customer-specific metrics such as total spent and recent orders.
 */
$admin_customers = [];
$total_count = 0;
$pending_count = 0;
$approved_count = 0;
$suspended_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch all users to display in the customer list
        $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.phone AS whatsapp_number, u.business_name AS company, u.business_type AS type, u.br_number AS br, u.address AS addr, u.status, u.created_at 
                             FROM users u");
        $users_db = $stmt->fetchAll();

        foreach ($users_db as $usr) {
            $words = explode(" ", $usr['company']);
            $initials = "";
            foreach ($words as $w) {
                $initials .= strtoupper(substr($w, 0, 1));
            }
            $initials = substr($initials, 0, 2);

            $status_lower = strtolower($usr['status']);
            if ($status_lower === 'approved') {
                $badgeClass = 'bg-emerald-100 text-emerald-700';
                $badgeText = 'Approved';
                $actions = 'normal';
                $note = '';
                $approved_count++;
            } elseif ($status_lower === 'pending') {
                $badgeClass = 'bg-amber-100 text-amber-700';
                $badgeText = 'Pending';
                $actions = 'pending';
                $note = '<div class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex gap-3"><i class="ti ti-clock text-amber-600 mt-0.5"></i><p class="text-xs text-amber-700 leading-relaxed font-medium">Awaiting approval. BR number not yet verified.</p></div>';
                $pending_count++;
            } elseif ($status_lower === 'suspended') {
                $badgeClass = 'bg-purple-100 text-purple-700';
                $badgeText = 'Suspended';
                $actions = 'suspended';
                $note = '<div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex gap-3"><i class="ti ti-ban text-red-600 mt-0.5"></i><p class="text-xs text-red-700 leading-relaxed font-medium">Account suspended.</p></div>';
                $suspended_count++;
            } else {
                $badgeClass = 'bg-red-100 text-red-700';
                $badgeText = 'Rejected';
                $actions = 'rejected';
                $note = '<div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex gap-3"><i class="ti ti-x text-red-600 mt-0.5"></i><p class="text-xs text-red-700 leading-relaxed font-medium">Account rejected.</p></div>';
            }
            $total_count++;

            $spent_stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(total_amount) as total FROM orders WHERE user_id = ? AND status != 'cancelled'");
            $spent_stmt->execute([$usr['id']]);
            $spent_metrics = $spent_stmt->fetch();
            $orders_cnt = (int)($spent_metrics['cnt'] ?? 0);
            $total_spent = (float)($spent_metrics['total'] ?? 0);
            $spent = $total_spent > 0 ? 'LKR ' . ($total_spent >= 1000000 ? number_format($total_spent/1000000, 1) . 'M' : number_format($total_spent/1000, 0) . 'K') : '—';

            $orders_stmt = $pdo->prepare("SELECT id, status, total_amount FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
            $orders_stmt->execute([$usr['id']]);
            $recent_orders_db = $orders_stmt->fetchAll();
            $recentOrders = "";
            if (empty($recent_orders_db)) {
                $recentOrders = '<p class="text-xs text-gray-400 text-center py-4 italic">No orders yet.</p>';
            } else {
                foreach ($recent_orders_db as $ro) {
                    $ro_id = 'KE-2025-' . str_pad($ro['id'], 5, '0', STR_PAD_LEFT);
                    $ro_status = ucfirst($ro['status']);
                    $ro_badge = strtolower($ro['status']) === 'pending' ? 'bg-amber-100 text-amber-700' : (strtolower($ro['status']) === 'shipped' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700');
                    $recentOrders .= '<div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 text-xs"><span class="font-bold text-gray-900">' . htmlspecialchars($ro_id) . '</span><span class="px-2 py-0.5 rounded-full ' . $ro_badge . ' font-bold uppercase text-[9px]">' . $ro_status . '</span><span class="font-bold">LKR ' . number_format($ro['total_amount'], 0) . '</span></div>';
                }
            }

            $admin_customers[] = [
                'id' => $usr['id'],
                'av' => $initials,
                'name' => $usr['first_name'] . ' ' . $usr['last_name'],
                'company' => $usr['company'],
                'email' => $usr['email'],
                'phone' => $usr['phone'],
                'whatsapp' => $usr['whatsapp_number'] ?? '',
                'type' => $usr['type'],
                'br' => $usr['br'],
                'addr' => $usr['addr'],
                'badge' => $badgeClass,
                'badgeText' => $badgeText,
                'spent' => $spent,
                'orders' => $orders_cnt,
                'actions' => $actions,
                'note' => $note,
                'recentOrders' => $recentOrders
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}


if (empty($admin_customers)) {
    $admin_customers = [];
    // Stats already zero from initialization above if DB returned nothing
}

?>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
                <p class="text-sm text-gray-500 mt-1">Manage wholesale buyer accounts and verification.</p>
            </div>
                <button class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm" onclick="downloadPDF('customer-list-container', 'Customers_List')">
                    <i class="ti ti-download"></i> Export PDF
                </button>
        </div>

        <!-- Stats -->
        <div class="px-8 py-6 grid grid-cols-4 gap-4 border-b border-gray-100">
            <div class="bg-gray-50/50 rounded-2xl p-4 border border-gray-100">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Customers</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_count ?></p>
            </div>
            <div class="bg-amber-50/50 rounded-2xl p-4 border border-amber-100">
                <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Pending Approval</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?= $pending_count ?></p>
            </div>
            <div class="bg-emerald-50/50 rounded-2xl p-4 border border-emerald-100">
                <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Approved</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1"><?= $approved_count ?></p>
            </div>
            <div class="bg-purple-50/50 rounded-2xl p-4 border border-purple-100">
                <p class="text-[10px] font-bold text-purple-500 uppercase tracking-wider">Suspended</p>
                <p class="text-2xl font-bold text-purple-600 mt-1"><?= $suspended_count ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-8 py-4 bg-gray-50/30 flex items-center gap-4 border-b border-gray-100">
            <div class="relative flex-1">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="customer-search" placeholder="Search company, email or contact..."
                       oninput="applyFilters()"
                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm transition-all">
            </div>
            <select id="customer-sort" onchange="applyFilters()"
                    class="px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm font-medium transition-all">
                <option value="newest">Newest first</option>
                <option value="spend">Highest spend</option>
                <option value="alpha">Name A–Z</option>
            </select>
        </div>

        <!-- Tabs -->
        <div class="px-8 py-3 flex flex-nowrap gap-2 border-b border-gray-100 overflow-x-auto no-scrollbar">
            <button class="chip on" onclick="filterChip(this)">All</button>
            <button class="chip" onclick="filterChip(this)">Pending</button>
            <button class="chip" onclick="filterChip(this)">Approved</button>
            <button class="chip" onclick="filterChip(this)">Suspended</button>
            <button class="chip" onclick="filterChip(this)">Rejected</button>
        </div>

        <!-- List Header -->
        <div class="px-8 py-3 bg-gray-50/50 grid grid-cols-[48px_minmax(200px,2fr)_minmax(120px,1fr)_100px_100px] gap-4 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider items-center">
            <span></span>
            <span>Company / Contact</span>
            <span>Business Type</span>
            <span class="text-center">Total Spent</span>
            <span class="text-right">Status</span>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar" id="customer-list-container">
            <div class="min-w-[800px] p-6 space-y-1" id="customer-list">
                <?php if (empty($admin_customers)): ?>
                <div id="empty-state" class="flex flex-col items-center justify-center py-24 text-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-300"><i class="ti ti-users text-3xl"></i></div>
                    <p class="text-sm font-bold text-gray-400">No customers found</p>
                    <p class="text-xs text-gray-300">Customers who register through the site will appear here.</p>
                </div>
                <?php else: ?>
                <?php foreach ($admin_customers as $idx => $c): ?>
                <div id="customer-row-<?= $idx ?>" class="customer-row grid grid-cols-[48px_minmax(200px,2fr)_minmax(120px,1fr)_100px_100px] gap-4 items-center p-4 bg-white border border-gray-100 rounded-2xl cursor-pointer hover:border-brand/30 hover:bg-gray-50/50 transition-all"
                     data-idx="<?= $idx ?>"
                     data-id="<?= htmlspecialchars($c['id']) ?>"
                     data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>"
                     data-company="<?= htmlspecialchars(strtolower($c['company'])) ?>"
                     data-email="<?= htmlspecialchars(strtolower($c['email'])) ?>"
                     data-phone="<?= htmlspecialchars(strtolower($c['phone'])) ?>"
                     data-status="<?= htmlspecialchars(strtolower($c['badgeText'])) ?>"
                     data-av="<?= htmlspecialchars($c['av']) ?>"
                     data-original-name="<?= htmlspecialchars($c['name']) ?>"
                     data-original-company="<?= htmlspecialchars($c['company']) ?>"
                     data-original-email="<?= htmlspecialchars($c['email']) ?>"
                     data-original-phone="<?= htmlspecialchars($c['phone']) ?>"
                     data-whatsapp="<?= htmlspecialchars($c['whatsapp']) ?>"
                     data-type="<?= htmlspecialchars($c['type']) ?>"
                     data-br="<?= htmlspecialchars($c['br']) ?>"
                     data-addr="<?= htmlspecialchars($c['addr']) ?>"
                     data-badge="<?= htmlspecialchars($c['badge']) ?>"
                     data-badgetext="<?= htmlspecialchars($c['badgeText']) ?>"
                     data-spent="<?= htmlspecialchars($c['spent']) ?>"
                     data-orders="<?= htmlspecialchars($c['orders']) ?>"
                     data-actions="<?= htmlspecialchars($c['actions']) ?>"
                     data-note="<?= htmlspecialchars($c['note']) ?>"
                     data-recentorders="<?= htmlspecialchars($c['recentOrders']) ?>"
                     onclick="selectCustomer(this)">
                    <div class="w-10 h-10 rounded-xl bg-brand-light text-brand flex items-center justify-center font-bold text-xs shadow-sm border border-brand/5"><?= htmlspecialchars($c['av']) ?></div>
                    <div>
                        <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($c['company']) ?></p>
                        <p class="text-xs text-gray-400 font-medium mt-0.5"><?= htmlspecialchars($c['name']) ?></p>
                    </div>
                    <span class="text-xs text-gray-500 font-semibold"><?= htmlspecialchars($c['type']) ?></span>
                    <span class="text-center font-bold text-gray-900 text-xs"><?= htmlspecialchars($c['spent']) ?></span>
                    <div class="text-right">
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider <?= $c['badge'] ?> border"><?= htmlspecialchars($c['badgeText']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs font-medium text-gray-500">Showing <span class="text-gray-900"><?= count($admin_customers) ?></span> of <span class="text-gray-900"><?= $total_count ?></span> customers</p>
            <div class="flex gap-2">
                <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 transition-all"><i class="ti ti-chevron-left text-sm"></i></button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-white font-bold text-xs shadow-lg shadow-brand/20">1</button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 transition-all"><i class="ti ti-chevron-right text-sm"></i></button>
            </div>
        </div>
    </div>

    <!-- Detail Pane -->
    <!-- Backdrop -->
    <div id="customer-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeDetailPane()"></div>
    <div class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-gray-50 border-l border-gray-200 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto" id="detail-pane">
        <!-- Detail Header -->
        <div class="p-8 border-b border-gray-200 bg-white">
            <div class="flex items-start justify-between mb-6">
                <div class="w-16 h-16 rounded-2xl bg-brand flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-brand/20" id="d-avatar">&mdash;</div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider hidden" id="d-badge"></span>
                    <button onclick="closeDetailPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>
            </div>
            <h2 class="text-xl font-bold text-gray-900" id="d-company">&mdash;</h2>
            <p class="text-xs text-gray-500 mt-1" id="d-name">&mdash;</p>
        </div>

        <!-- Detail Content -->
        <div class="p-8 space-y-8 flex-1">
            <!-- Contact -->
            <div class="space-y-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Contact Info</h3>
                <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-3 text-xs font-medium">
                    <span class="text-gray-400">Email</span>
                    <span class="text-gray-900 break-all" id="d-email">&mdash;</span>
                    <span class="text-gray-400">Phone</span>
                    <span class="text-gray-900" id="d-phone">&mdash;</span>
                    <span class="text-gray-400">WhatsApp</span>
                    <a id="d-whatsapp" href="#" target="_blank" class="text-green-600 font-semibold flex items-center gap-1 hover:underline"><i class="ti ti-brand-whatsapp"></i><span id="d-whatsapp-text">—</span></a>
                </div>
            </div>

            <!-- Business Details -->
            <div class="space-y-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Business Details</h3>
                <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-3 text-xs font-medium">
                    <span class="text-gray-400">BR Number</span>
                    <span class="text-gray-900" id="d-br">&mdash;</span>
                    <span class="text-gray-400">Type</span>
                    <span class="text-gray-900" id="d-type">&mdash;</span>
                    <span class="text-gray-400">Address</span>
                    <span class="text-gray-900 leading-relaxed" id="d-address">&mdash;</span>
                </div>
            </div>

            <!-- Custom Notification for Pending -->
            <div id="d-note-container">
                <!-- Special status notification if needed -->
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Orders Count</p>
                    <p class="text-xl font-bold text-gray-900 mt-1" id="d-orders">0</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total spent</p>
                    <p class="text-xl font-bold text-brand mt-1" id="d-spent">LKR 0</p>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="space-y-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Recent Orders</h3>
                <div class="space-y-2.5" id="d-recent-orders">
                    <!-- Orders markup -->
                </div>
            </div>

            <!-- Admin Note / Comment -->
            <div class="space-y-3">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Admin Note</h3>
                <div class="relative">
                    <textarea id="d-comment" rows="4" placeholder="Add a private note about this customer..." class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-white text-xs font-medium text-gray-700 placeholder-gray-400 resize-none focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition-all"></textarea>
                </div>
                <button onclick="saveComment()" class="w-full flex items-center justify-center gap-2 bg-gray-900 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-widest hover:bg-gray-700 transition-all">
                    <i class="ti ti-device-floppy"></i> Save Note
                </button>
            </div>
        </div>

        <!-- Detail Actions -->
        <div class="p-8 border-t border-gray-200 bg-white" id="d-actions">
            <!-- Dynamic action buttons -->
        </div>
    </div>
</div>

<style>
     .chip {
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid #e5e7eb;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        white-space: nowrap;
        flex-shrink: 0;
        background-color: transparent;
    }
    .chip:hover {
        background-color: #f3f4f6;
    }
    .chip.on {
        background-color: #0F6E56;
        color: #ffffff;
        border-color: #0F6E56;
        box-shadow: 0 10px 15px -3px rgba(15, 110, 86, 0.2);
    }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<!-- Toast Notification System -->
<style>
#toast-container {
    position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
    display: flex; flex-direction: column; gap: 0.625rem; pointer-events: none;
}
.toast {
    pointer-events: auto; display: flex; align-items: flex-start; gap: 0.75rem;
    min-width: 280px; max-width: 360px; padding: 1rem 1.125rem; background: #fff;
    border-radius: 1rem; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.04);
    transform: translateX(120%); opacity: 0;
    transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease;
    overflow: hidden; position: relative;
}
.toast.toast-show { transform: translateX(0); opacity: 1; }
.toast-icon { width:2rem; height:2rem; border-radius:0.625rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1rem; }
.toast-body { flex:1; min-width:0; }
.toast-title { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; line-height:1.2; }
.toast-msg { font-size:0.75rem; font-weight:500; color:#6b7280; margin-top:0.2rem; line-height:1.4; }
.toast-close { background:none; border:none; cursor:pointer; padding:0; color:#9ca3af; font-size:1rem; flex-shrink:0; transition:color 0.15s; }
.toast-close:hover { color:#374151; }
.toast-progress { position:absolute; bottom:0; left:0; height:3px; border-radius:0 0 1rem 1rem; animation:toast-shrink linear forwards; }
@keyframes toast-shrink { from{width:100%} to{width:0%} }
.toast-success .toast-icon { background:#ecfdf5; color:#0F6E56; }
.toast-success .toast-title { color:#0F6E56; }
.toast-success .toast-progress { background:#0F6E56; }
.toast-error .toast-icon { background:#fef2f2; color:#dc2626; }
.toast-error .toast-title { color:#dc2626; }
.toast-error .toast-progress { background:#dc2626; }
.toast-info .toast-icon { background:#f3f4f6; color:#4b5563; }
.toast-info .toast-title { color:#374151; }
.toast-info .toast-progress { background:#6b7280; }
</style>
<div id="toast-container"></div>


<script>
function selectCustomer(el, openDrawer = true) {
    if (!el) return;
    
    document.querySelectorAll('.customer-row').forEach(r => {
        r.classList.remove('bg-brand/5', 'border-brand/20', 'shadow-sm');
        r.classList.add('bg-white', 'border-gray-100');
    });
    
    el.classList.add('bg-brand/5', 'border-brand/20', 'shadow-sm');
    el.classList.remove('bg-white', 'border-gray-100');

    document.getElementById('d-avatar').textContent = el.dataset.av;
    
    // Open drawer
    if (openDrawer) {
        var pane = document.getElementById('detail-pane');
        var backdrop = document.getElementById('customer-detail-backdrop');
        if (pane) pane.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
        }
    }
    document.getElementById('d-badge').className = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ' + el.dataset.badge;
    document.getElementById('d-badge').textContent = el.dataset.badgetext;
    document.getElementById('d-company').textContent = el.dataset.originalCompany;
    document.getElementById('d-name').textContent = el.dataset.originalName;
    document.getElementById('d-email').textContent = el.dataset.originalEmail;
    document.getElementById('d-phone').textContent = el.dataset.originalPhone;
    var waNum = (el.dataset.whatsapp || '').replace(/\D/g, '');
    var waEl = document.getElementById('d-whatsapp');
    var waTxt = document.getElementById('d-whatsapp-text');
    if (waTxt) waTxt.textContent = el.dataset.whatsapp || '—';
    if (waEl) waEl.href = waNum ? `https://wa.me/${waNum}` : '#';
    document.getElementById('d-br').textContent = el.dataset.br;
    document.getElementById('d-type').textContent = el.dataset.type;
    document.getElementById('d-address').textContent = el.dataset.addr;

    document.getElementById('d-note-container').innerHTML = el.dataset.note;

    document.getElementById('d-orders').textContent = el.dataset.orders;
    document.getElementById('d-spent').textContent = el.dataset.spent;

    document.getElementById('d-recent-orders').innerHTML = el.dataset.recentorders;

    var actionDiv = document.getElementById('d-actions');
    var actions = el.dataset.actions;
    var cid = el.dataset.id;
    if (actions === 'pending') {
        actionDiv.innerHTML = `
            <div class="grid grid-cols-2 gap-3">
                <button onclick="updateStatus(${cid}, 'approved')" class="bg-brand text-brand-light font-bold py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-brand-dark transition-all shadow-lg shadow-brand/10">Approve</button>
                <button onclick="updateStatus(${cid}, 'rejected')" class="bg-white border border-gray-200 text-red-600 font-bold py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-red-50 hover:border-red-200 transition-all">Reject</button>
            </div>
        `;
    } else if (actions === 'normal') {
        actionDiv.innerHTML = `
            <button onclick="updateStatus(${cid}, 'suspended')" class="w-full bg-white border border-red-100 text-red-650 font-bold py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-red-50 hover:border-red-200 transition-all flex items-center justify-center gap-2">
                <i class="ti ti-ban"></i> Suspend Account
            </button>
        `;
    } else if (actions === 'suspended') {
        actionDiv.innerHTML = `
            <button onclick="updateStatus(${cid}, 'approved')" class="w-full bg-brand text-brand-light font-bold py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-brand-dark transition-all shadow-lg shadow-brand/10 flex items-center justify-center gap-2">
                <i class="ti ti-circle-check"></i> Activate Account
            </button>
        `;
    } else {
        actionDiv.innerHTML = `
            <button onclick="updateStatus(${cid}, 'approved')" class="w-full bg-brand text-brand-light font-bold py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-brand-dark transition-all shadow-lg shadow-brand/10 flex items-center justify-center gap-2">
                <i class="ti ti-rotate"></i> Re-evaluate Request
            </button>
        `;
    }

    // Load persisted admin comment for this customer
    loadComment(cid);
}

// ── Filtering state ──────────────────────────────────────────────
var _activeChip  = 'all';  // 'all' | 'pending' | 'approved' | 'suspended' | 'rejected'

function applyFilters() {
    var q    = (document.getElementById('customer-search')?.value || '').toLowerCase().trim();
    var sort = document.getElementById('customer-sort')?.value || 'newest';
    var list = document.getElementById('customer-list');
    var rows = Array.from(document.querySelectorAll('.customer-row'));

    rows.forEach(r => {
        var visible = true;
        if (_activeChip !== 'all' && r.dataset.status !== _activeChip) visible = false;
        
        if (q && !r.dataset.company.includes(q)
               && !r.dataset.name.includes(q)
               && !r.dataset.email.includes(q)
               && !r.dataset.phone.includes(q)) {
            visible = false;
        }
        
        r.style.display = visible ? '' : 'none';
    });

    if (sort === 'alpha') {
        rows.sort((a, b) => a.dataset.originalCompany.localeCompare(b.dataset.originalCompany));
    } else if (sort === 'spend') {
        var parseSpend = s => {
            if (!s || s === '\u2014') return 0;
            var n = parseFloat(s.replace(/[^\d.]/g, ''));
            return s.includes('M') ? n * 1e6 : s.includes('K') ? n * 1e3 : n;
        };
        rows.sort((a, b) => parseSpend(b.dataset.spent) - parseSpend(a.dataset.spent));
    } else if (sort === 'newest') {
        rows.sort((a, b) => parseInt(a.dataset.idx) - parseInt(b.dataset.idx));
    }

    rows.forEach(r => list.appendChild(r));

    // Show empty state if no visible rows
    var visibleCount = rows.filter(r => r.style.display !== 'none').length;
    var emptyState = document.getElementById('empty-state');
    if (!emptyState) {
        emptyState = document.createElement('div');
        emptyState.id = 'empty-state';
        emptyState.className = 'flex flex-col items-center justify-center py-24 text-center gap-4';
        emptyState.innerHTML = `
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-300"><i class="ti ti-users text-3xl"></i></div>
            <p class="text-sm font-bold text-gray-400">No customers found</p>
            <p class="text-xs text-gray-300">Adjust your search or filter to find customers.</p>
        `;
        list.appendChild(emptyState);
    }
    emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';

    closeDetailPane();
}

// ── Initial render ────────────────────────────────────────────────
applyFilters();
var firstRow = document.querySelector('.customer-row');
if (firstRow) {
    selectCustomer(firstRow, false);
}
closeDetailPane();


function filterChip(el) {
    document.querySelectorAll('.chip').forEach(c => c.classList.remove('on'));
    el.classList.add('on');
    _activeChip = el.textContent.trim().toLowerCase();
    applyFilters();
}

// refreshRowStatus removed as it's now handled inline in updateStatus

function showToast(message, variant = 'success', duration = 3500) {
    var icons = { success: '<i class="ti ti-circle-check"></i>', error: '<i class="ti ti-circle-x"></i>', info: '<i class="ti ti-info-circle"></i>' };
    var titles = { success: 'Success', error: 'Error', info: 'Info' };
    var t = document.createElement('div');
    t.className = `toast toast-${variant}`;
    t.innerHTML = `
        <div class="toast-icon">${icons[variant]}</div>
        <div class="toast-body"><p class="toast-title">${titles[variant]}</p><p class="toast-msg">${message}</p></div>
        <button class="toast-close" onclick="this.closest('.toast').remove()"><i class="ti ti-x"></i></button>
        <div class="toast-progress" style="animation-duration:${duration}ms"></div>`;
    document.getElementById('toast-container').appendChild(t);
    requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('toast-show')));
    setTimeout(() => { t.classList.remove('toast-show'); t.addEventListener('transitionend', () => t.remove(), { once: true }); }, duration);
}

function updateStatus(id, newStatus) {
    fetch('/api/customers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            var rowEl = document.querySelector(`.customer-row[data-id="${id}"]`);
            if (rowEl) {
                rowEl.dataset.actions = newStatus === 'approved' ? 'normal' : newStatus;

                var statusMap = {
                    approved:  { badge: 'bg-emerald-100 text-emerald-700', badgeText: 'Approved',  note: '' },
                    pending:   { badge: 'bg-amber-100 text-amber-700',    badgeText: 'Pending',    note: '<div class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex gap-3"><i class="ti ti-clock text-amber-600 mt-0.5"></i><p class="text-xs text-amber-700 leading-relaxed font-medium">Awaiting approval. BR number not yet verified.</p></div>' },
                    suspended: { badge: 'bg-purple-100 text-purple-700',  badgeText: 'Suspended',  note: '<div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex gap-3"><i class="ti ti-ban text-red-600 mt-0.5"></i><p class="text-xs text-red-700 leading-relaxed font-medium">Account suspended.</p></div>' },
                    rejected:  { badge: 'bg-red-100 text-red-700',        badgeText: 'Rejected',   note: '<div class="p-4 bg-red-50 rounded-2xl border border-red-100 flex gap-3"><i class="ti ti-x text-red-600 mt-0.5"></i><p class="text-xs text-red-700 leading-relaxed font-medium">Account rejected.</p></div>' }
                };
                var m = statusMap[newStatus];
                if (m) { 
                    rowEl.dataset.badge = m.badge; 
                    rowEl.dataset.badgetext = m.badgeText; 
                    rowEl.dataset.note = m.note; 
                    rowEl.dataset.status = m.badgeText.toLowerCase();
                    
                    var badgeSpan = rowEl.querySelector('span.rounded-full');
                    if (badgeSpan) {
                        badgeSpan.className = `px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ${m.badge} border`;
                        badgeSpan.textContent = m.badgeText;
                    }
                }

                // Re-select the same row to refresh the detail pane
                try {
                    selectCustomer(rowEl, false);
                } catch (e) {
                    console.error("Error in selectCustomer:", e);
                }
            }
            
            var labels = { approved: 'Customer account approved.', suspended: 'Customer account suspended.', rejected: 'Customer account rejected.' };
            var variant = (newStatus === 'rejected') ? 'error' : 'success';
            showToast(labels[newStatus] || data.message || `Status updated to ${newStatus}.`, variant);
        } else {
            showToast(data.message || 'Failed to update customer status.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error: ' + err.message, 'error');
    });
}

var _activeCustomerId = null;

function loadComment(customerId) {
    _activeCustomerId = customerId;
    var ta = document.getElementById('d-comment');
    if (!ta) return;
    ta.value = '';
    ta.placeholder = 'Loading...';
    fetch(`/api/customers.php?id=${customerId}`)
        .then(res => res.json())
        .then(data => {
            ta.value = data.comment || '';
            ta.placeholder = 'Add a private note about this customer...';
        })
        .catch(() => { ta.placeholder = 'Add a private note about this customer...'; });
}

function saveComment() {
    var ta = document.getElementById('d-comment');
    if (!ta || !_activeCustomerId) return;
    var comment = ta.value.trim();
    fetch('/api/customers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_comment', id: _activeCustomerId, comment: comment })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Admin note saved.', 'success');
        } else {
            showToast(data.message || 'Failed to save note.', 'error');
        }
    })
    .catch(() => showToast('An error occurred while saving note.', 'error'));
}

function closeDetailPane() {
    var pane = document.getElementById('detail-pane');
    var backdrop = document.getElementById('customer-detail-backdrop');
    if (pane) pane.classList.add('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('hidden');
    }
    document.querySelectorAll('.customer-row').forEach(r => {
        r.classList.remove('bg-brand/5', 'border-brand/20', 'shadow-sm');
        r.classList.add('bg-white', 'border-gray-100');
    });
}



</script>
