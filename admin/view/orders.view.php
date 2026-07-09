<?php
$admin_orders = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT o.id, o.status, o.total_amount AS total, o.created_at, u.business_name AS company, u.first_name, u.last_name, u.email 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             WHERE o.deleted_at IS NULL");
        $orders_db = $stmt->fetchAll();

        foreach ($orders_db as $ord) {
            $order_id_formatted = 'KE-2025-' . str_pad($ord['id'], 5, '0', STR_PAD_LEFT);
            
            $status_lower = strtolower($ord['status']);
            $badgeText = strtoupper($ord['status']);
            if ($status_lower === 'pending') {
                $badgeClass = 'bg-amber-50 text-amber-605 text-amber-600 border-amber-100';
                $badgeText = 'PENDING PAYMENT';
            } elseif ($status_lower === 'processing') {
                $badgeClass = 'bg-blue-50 text-blue-600 border-blue-100';
            } elseif ($status_lower === 'shipped') {
                $badgeClass = 'bg-indigo-50 text-indigo-600 border-indigo-100';
            } elseif ($status_lower === 'delivered') {
                $badgeClass = 'bg-green-50 text-green-600 border-green-100';
            } else {
                $badgeClass = 'bg-red-50 text-red-600 border-red-100';
            }

            $item_stmt = $pdo->prepare("SELECT p.name AS n, oi.quantity AS q, (oi.quantity * oi.unit_price) AS p 
                                        FROM order_items oi 
                                        JOIN products p ON oi.product_id = p.id 
                                        WHERE oi.order_id = ?");
            $item_stmt->execute([$ord['id']]);
            $raw_items = $item_stmt->fetchAll();
            $items = [];
            foreach ($raw_items as $item) {
                $items[] = [
                    'n' => $item['n'],
                    'q' => (int)$item['q'],
                    'p' => number_format((float)$item['p'], 0)
                ];
            }

            $timeline_stmt = $pdo->prepare("SELECT status AS t, changed_at AS d, note FROM order_status_log WHERE order_id = ? ORDER BY changed_at ASC");
            $timeline_stmt->execute([$ord['id']]);
            $raw_timeline = $timeline_stmt->fetchAll();
            $timeline = [];
            
            $states = ['pending', 'processing', 'shipped', 'delivered'];
            $currentStateIdx = array_search($status_lower, $states);
            if ($currentStateIdx === false) $currentStateIdx = -1;

            $log_map = [];
            foreach ($raw_timeline as $log) {
                $log_map[strtolower($log['t'])] = $log;
            }

            $step_titles = [
                'pending' => 'Order Placed',
                'processing' => 'Processing',
                'shipped' => 'Shipped',
                'delivered' => 'Delivered'
            ];
            if ($status_lower === 'cancelled') {
                $timeline[] = [
                    't' => 'Order Cancelled',
                    'd' => isset($log_map['cancelled']) ? date('d M, g:i A', strtotime($log_map['cancelled']['d'])) : '',
                    's' => 'now'
                ];
            } else {
                foreach ($states as $idx => $st) {
                    $has_log = isset($log_map[$st]);
                    $title = $step_titles[$st];
                    
                    if ($idx < $currentStateIdx) {
                        $s = 'done';
                        $date = $has_log ? date('d M, g:i A', strtotime($log_map[$st]['d'])) : 'Completed';
                    } elseif ($idx == $currentStateIdx) {
                        $s = 'now';
                        $date = $has_log ? ($log_map[$st]['note'] ?: date('d M, g:i A', strtotime($log_map[$st]['d']))) : 'In progress';
                    } else {
                        $s = 'pend';
                        $date = '';
                    }

                    $timeline[] = [
                        't' => $title,
                        'd' => $date,
                        's' => $s
                    ];
                }
            }

            $admin_orders[] = [
                'id' => $ord['id'],
                'formattedId' => $order_id_formatted,
                'status' => $ord['status'],
                'badge' => $badgeClass,
                'badgeText' => $badgeText,
                'company' => $ord['company'],
                'clientName' => $ord['first_name'] . ' ' . $ord['last_name'],
                'clientEmail' => $ord['email'],
                'date' => date('d M Y, g:i A', strtotime($ord['created_at'])),
                'total' => number_format((float)$ord['total'], 2),
                'items' => $items,
                'timeline' => $timeline
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}

if (empty($admin_orders)) {
    $admin_orders = [];
}


// Calculate dynamic stats
$total_orders = count($admin_orders);
$pending_orders = 0;
$processing_orders = 0;
$shipped_orders = 0;
foreach ($admin_orders as $o) {
    $status = strtolower($o['status']);
    if ($status === 'pending') $pending_orders++;
    elseif ($status === 'processing') $processing_orders++;
    elseif ($status === 'shipped') $shipped_orders++;
}

$available_drivers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt_d = $pdo->query("SELECT id, name FROM delivery_personnel WHERE status = 'available'");
        $available_drivers = $stmt_d->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}
}
?>
<!-- MAIN CONTENT AREA (SPLIT LAYOUT) -->
<main class="flex-1 flex overflow-hidden">
    
    <!-- LEFT: ORDER LIST -->
    <div id="orders-container" class="flex-1 flex flex-col bg-white border-r border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-gray-50 flex items-center justify-between gap-4">
            <h1 class="text-xl font-extrabold text-gray-900 tracking-tight uppercase">Orders</h1>
            <button class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all" onclick="downloadPDF('orders-container', 'Orders_List')">
                <i class="ti ti-download text-xl"></i>Export PDF
            </button>
        </div>

        <!-- Stats Bar -->
        <div class="px-8 py-6 grid grid-cols-4 gap-4 border-b border-gray-50">
            <div class="text-center p-3 bg-gray-50 rounded-2xl border border-gray-100">
                <p class="text-[15px] font-black text-gray-900"><?= $total_orders ?></p>
                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Total</p>
            </div>
            <div class="text-center p-3 bg-amber-50/50 rounded-2xl border border-amber-100">
                <p class="text-[15px] font-black text-amber-600"><?= $pending_orders ?></p>
                <p class="text-[9px] font-bold text-amber-400 uppercase tracking-widest mt-0.5">Pending</p>
            </div>
            <div class="text-center p-3 bg-blue-50/50 rounded-2xl border border-blue-100">
                <p class="text-[15px] font-black text-blue-600"><?= $processing_orders ?></p>
                <p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-0.5">Process</p>
            </div>
            <div class="text-center p-3 bg-brand-light/50 rounded-2xl border border-brand/10">
                <p class="text-[15px] font-black text-brand"><?= $shipped_orders ?></p>
                <p class="text-[9px] font-bold text-brand/40 uppercase tracking-widest mt-0.5">Shipped</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-8 space-y-6 overflow-y-auto flex-1">
            <div class="flex gap-4">
                <div class="relative flex-1 group">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input id="order-search" type="text" placeholder="Order ID or Business..." class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-transparent rounded-2xl text-xs font-medium outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <select id="order-sort" class="bg-gray-50 border border-transparent rounded-2xl px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest outline-none cursor-pointer focus:bg-white focus:border-brand/20 transition-all">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>

            <!-- Status Tabs -->
            <div class="flex flex-wrap gap-2 pb-2">
                <button class="status-tab active-tab px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest transition-all" data-status="all">All</button>
                <button class="status-tab px-4 py-1.5 bg-gray-50 text-gray-400 border border-transparent rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-gray-100 transition-all" data-status="pending">Pending</button>
                <button class="status-tab px-4 py-1.5 bg-gray-50 text-gray-400 border border-transparent rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-gray-100 transition-all" data-status="processing">Processing</button>
                <button class="status-tab px-4 py-1.5 bg-gray-50 text-gray-400 border border-transparent rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-gray-100 transition-all" data-status="shipped">Shipped</button>
                <button class="status-tab px-4 py-1.5 bg-gray-50 text-gray-400 border border-transparent rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-gray-100 transition-all" data-status="delivered">Delivered</button>
                <button class="status-tab px-4 py-1.5 bg-gray-50 text-gray-400 border border-transparent rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-gray-100 transition-all" data-status="cancelled">Cancelled</button>
            </div>

            <!-- Order List Cards -->
            <div id="order-list" class="space-y-3 pb-8">
                <?php if (empty($admin_orders)): ?>
                <div id="empty-state" class="flex flex-col items-center justify-center py-16 text-center hidden">
                    <div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center mb-4">
                        <i class="ti ti-search-off text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">No orders found</p>
                    <p class="text-[11px] text-gray-300 mt-1">Try adjusting your filters</p>
                </div>
                <?php else: ?>
                <?php foreach ($admin_orders as $idx => $o): ?>
                <div id="order-card-<?= $idx ?>" class="order-card border border-gray-100 rounded-3xl p-6 bg-white cursor-pointer relative"
                     data-idx="<?= $idx ?>"
                     data-id="<?= htmlspecialchars($o['id']) ?>"
                     data-formatted-id="<?= htmlspecialchars($o['formattedId']) ?>"
                     data-status="<?= htmlspecialchars($o['status']) ?>"
                     data-badge="<?= htmlspecialchars($o['badge']) ?>"
                     data-badgetext="<?= htmlspecialchars($o['badgeText']) ?>"
                     data-company="<?= htmlspecialchars($o['company']) ?>"
                     data-clientname="<?= htmlspecialchars($o['clientName']) ?>"
                     data-clientemail="<?= htmlspecialchars($o['clientEmail']) ?>"
                     data-date="<?= htmlspecialchars($o['date']) ?>"
                     data-total="<?= htmlspecialchars($o['total']) ?>"
                     data-items="<?= htmlspecialchars(json_encode($o['items'])) ?>"
                     data-timeline="<?= htmlspecialchars(json_encode($o['timeline'])) ?>"
                     onclick="selectOrder(this)">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900"><?= htmlspecialchars($o['formattedId']) ?></h3>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars($o['company']) ?></p>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold border uppercase tracking-wider <?= $o['badge'] ?>"><?= htmlspecialchars(str_replace('PENDING PAYMENT', 'PENDING', $o['badgeText'])) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xs font-medium">
                        <span class="text-gray-400"><?= htmlspecialchars(explode(',', $o['date'])[0]) ?></span>
                        <span class="font-extrabold text-gray-950">LKR <?= htmlspecialchars(explode('.', $o['total'])[0]) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Backdrop (all screen sizes) -->
    <div id="order-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeOrderDetailPane()"></div>

    <!-- RIGHT: ORDER PROFILE & DETAIL -->
    <div id="order-detail-pane" class="fixed inset-y-0 right-0 z-50 w-[500px] max-w-[92vw] bg-gray-50 border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out overflow-y-auto">
        <!-- Profile Header -->
        <div class="p-8 border-b border-gray-100 bg-white flex justify-between items-center">
            <div>
                <h2 id="d-id" class="text-xl font-extrabold text-gray-900 tracking-tight">KE-2025-00847</h2>
                <p id="d-company" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">ABC Garments</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="d-badge" class="px-3 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider">Pending</span>
                <button class="flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-900 bg-white border border-gray-200 rounded-xl transition-all shadow-sm" onclick="downloadPDF('order-detail-pane', 'Invoice')" title="Download Invoice PDF">
                    <i class="ti ti-file-text text-xl"></i>
                </button>
                <button onclick="closeOrderDetailPane()" class="p-1.5 text-gray-400 hover:text-brand hover:bg-brand-light rounded-xl transition-all focus:outline-none" aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Detail Content -->
        <div class="flex-1 overflow-y-auto p-10 space-y-10">
            <!-- Client Info Card -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-brand-light text-brand rounded-2xl flex items-center justify-center font-black text-sm">KP</div>
                <div>
                    <h3 id="d-client" class="text-sm font-bold text-gray-900">Kamal Perera</h3>
                    <p id="d-email" class="text-xs text-gray-400 font-semibold mt-0.5">kamal@abc.lk</p>
                </div>
            </div>

            <!-- Order Items -->
            <div class="space-y-6">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Order Details</h3>
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-6">
                    <div id="d-items" class="divide-y divide-gray-50">
                        <!-- Line items injected dynamically -->
                    </div>
                    
                    <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Grand Total</span>
                        <span id="d-total" class="text-xl font-extrabold text-brand">LKR 71,933.00</span>
                    </div>
                </div>
            </div>

            <!-- Timeline Tracker -->
            <div class="space-y-6">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Timeline Tracker</h3>
                <div id="d-timeline" class="relative pl-8 space-y-8 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-px before:bg-gray-200">
                    <!-- Dynamic timeline steps -->
                </div>
            </div>
        </div>

        <!-- Sticky Footer Action -->
        <div class="p-8 border-t border-gray-150 bg-white" id="d-actions">
            <!-- Action buttons injected dynamically -->
        </div>
    </div>
</main>

<style>
.active-tab {
    background-color: #0F6E56 !important;
    color: #E1F5EE !important;
    box-shadow: 0 10px 15px -3px rgba(15, 110, 86, 0.2);
}
.order-card {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.order-card:hover {
    transform: translateY(-2px);
    border-color: rgba(15, 110, 86, 0.2);
    box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.05);
}
.order-card.selected {
    background-color: #E1F5EE;
    border-color: #0F6E56;
    transform: translateX(4px);
}

/* Toast Notifications */
#toast-container {
    position: fixed;
    top: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    pointer-events: none;
}
.toast {
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 280px;
    max-width: 360px;
    padding: 1rem 1.125rem;
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.04);
    transform: translateX(120%);
    opacity: 0;
    transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease;
    overflow: hidden;
    position: relative;
}
.toast.toast-show { transform: translateX(0); opacity: 1; }
.toast-icon {
    width: 2rem; height: 2rem;
    border-radius: 0.625rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 1rem;
}
.toast-body { flex: 1; min-width: 0; }
.toast-title {
    font-size: 0.72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2;
}
.toast-msg { font-size: 0.75rem; font-weight: 500; color: #6b7280; margin-top: 0.2rem; line-height: 1.4; }
.toast-close {
    background: none; border: none; cursor: pointer;
    padding: 0; color: #9ca3af; font-size: 1rem; flex-shrink: 0; transition: color 0.15s;
}
.toast-close:hover { color: #374151; }
.toast-progress {
    position: absolute; bottom: 0; left: 0;
    height: 3px; border-radius: 0 0 1rem 1rem;
    animation: toast-shrink linear forwards;
}
@keyframes toast-shrink { from { width: 100%; } to { width: 0%; } }
.toast-success .toast-icon { background: #ecfdf5; color: #0F6E56; }
.toast-success .toast-title { color: #0F6E56; }
.toast-success .toast-progress { background: #0F6E56; }
.toast-error .toast-icon { background: #fef2f2; color: #dc2626; }
.toast-error .toast-title { color: #dc2626; }
.toast-error .toast-progress { background: #dc2626; }
.toast-info .toast-icon { background: #f3f4f6; color: #4b5563; }
.toast-info .toast-title { color: #374151; }
.toast-info .toast-progress { background: #6b7280; }
</style>

<div id="toast-container"></div>
<script>
const availableDrivers = <?php echo json_encode($available_drivers); ?>;

function getInitials(name) {
    const parts = name.split(' ');
    let initials = '';
    parts.forEach(p => initials += p.charAt(0).toUpperCase());
    return initials.substring(0, 2);
}

function selectOrder(el, openDrawer = true) {
    if (!el) return;
    
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    
    // Open detail pane drawer if requested
    if (openDrawer) {
        const pane = document.getElementById('order-detail-pane');
        const backdrop = document.getElementById('order-detail-backdrop');
        if (pane) pane.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
        }
    }
    
    document.getElementById('d-id').textContent = el.dataset.formattedId;
    document.getElementById('d-company').textContent = el.dataset.company;
    
    const badge = document.getElementById('d-badge');
    badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider ' + el.dataset.badge;
    badge.textContent = el.dataset.badgetext;
    
    document.querySelector('.w-12.h-12.bg-brand-light').textContent = getInitials(el.dataset.clientname);
    document.getElementById('d-client').textContent = el.dataset.clientname;
    document.getElementById('d-email').textContent = el.dataset.clientemail;
    
    document.getElementById('d-total').textContent = 'LKR ' + el.dataset.total;
    
    // Render Items
    const itemsContainer = document.getElementById('d-items');
    let items = [];
    try { items = JSON.parse(el.dataset.items || '[]'); } catch (e) {}
    itemsContainer.innerHTML = items.map(item => `
        <div class="py-4 flex justify-between items-center text-xs first:pt-0 last:pb-0">
            <div>
                <p class="font-bold text-gray-900">${item.n}</p>
                <p class="text-gray-400 font-medium mt-0.5">${item.q} units</p>
            </div>
            <span class="font-bold text-gray-900">LKR ${item.p}</span>
        </div>
    `).join('');
    
    // Render Timeline
    const timelineContainer = document.getElementById('d-timeline');
    let timeline = [];
    try { timeline = JSON.parse(el.dataset.timeline || '[]'); } catch (e) {}
    timelineContainer.innerHTML = timeline.map((step, sIdx) => {
        let dotClass = 'bg-white border-gray-250 text-gray-300';
        let titleClass = 'text-gray-400';
        let descClass = 'text-gray-300';
        
        if (step.s === 'done') {
            dotClass = 'bg-brand text-brand-light shadow-md shadow-brand/10 border-brand';
            titleClass = 'text-gray-900';
            descClass = 'text-gray-400';
        } else if (step.s === 'now') {
            dotClass = 'bg-brand text-brand-light shadow-lg ring-4 ring-brand-light/50 border-brand animate-pulse';
            titleClass = 'text-brand font-black';
            descClass = 'text-brand/80 font-bold';
        }
        
        return `
            <div class="relative">
                <div class="absolute -left-[32px] top-0.5 w-6 h-6 rounded-full border flex items-center justify-center text-[10px] font-bold z-10 transition-all ${dotClass}">
                    ${sIdx + 1}
                </div>
                <h4 class="text-xs font-bold uppercase tracking-wider transition-colors ${titleClass}">${step.t}</h4>
                <p class="text-[11px] font-medium mt-1 transition-colors ${descClass}">${step.d}</p>
            </div>
        `;
    }).join('');
    
    // Actions Footer
    const actionContainer = document.getElementById('d-actions');
    const status_lower = el.dataset.status.toLowerCase();
    const oid = el.dataset.id;
    
    if (status_lower === 'pending') {
        actionContainer.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <button onclick="updateStatus(${oid}, 'processing')" class="bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/10 text-xs uppercase tracking-widest">Accept Payment</button>
                <button onclick="updateStatus(${oid}, 'cancelled')" class="bg-white border border-gray-200 text-red-600 font-bold py-4 rounded-2xl hover:bg-red-50 hover:border-red-200 transition-all text-xs uppercase tracking-widest">Cancel Order</button>
            </div>
        `;
    } else if (status_lower === 'processing') {
        let driverSelectHTML = '';
        if (availableDrivers && availableDrivers.length > 0) {
            driverSelectHTML = `
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Assign Driver</label>
                    <select id="assign-driver-select" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 outline-none focus:bg-white focus:border-brand/20 transition-all">
                        <option value="">Select available driver...</option>
                        ${availableDrivers.map(d => `<option value="${d.id}">${d.name}</option>`).join('')}
                    </select>
                </div>
            `;
        } else {
            driverSelectHTML = `
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Assign Driver</label>
                    <p class="text-xs text-amber-600 font-semibold bg-amber-50 border border-amber-100 p-3 rounded-xl"><i class="ti ti-alert-triangle mr-1"></i> No available drivers.</p>
                </div>
            `;
        }

        actionContainer.innerHTML = `
            ${driverSelectHTML}
            <div class="grid grid-cols-2 gap-4">
                <button onclick="dispatchOrder(${oid})" class="bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/10 text-xs uppercase tracking-widest">Dispatch Cargo</button>
                <button onclick="updateStatus(${oid}, 'cancelled')" class="bg-white border border-gray-200 text-red-650 font-bold py-4 rounded-2xl hover:bg-red-50 hover:border-red-200 transition-all text-xs uppercase tracking-widest">Cancel Order</button>
            </div>
        `;
    } else if (status_lower === 'shipped') {
        actionContainer.innerHTML = `
            <button onclick="updateStatus(${oid}, 'delivered')" class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/10 text-xs uppercase tracking-widest">Confirm Delivery</button>
        `;
    } else {
        actionContainer.innerHTML = `
            <p class="text-xs text-gray-400 font-medium text-center py-2 uppercase tracking-wider"><i class="ti ti-lock mr-1"></i> Order Closed (${el.dataset.status})</p>
        `;
    }
}

// Filter state
let activeStatus = 'all';
let searchQuery   = '';
let sortOrder     = 'newest';

function applyFilters() {
    const q = searchQuery.toLowerCase().trim();
    const list = document.getElementById('order-list');
    const rows = Array.from(document.querySelectorAll('.order-card'));
    let visibleCount = 0;

    rows.forEach(r => {
        let visible = true;
        
        if (activeStatus !== 'all' && r.dataset.status.toLowerCase() !== activeStatus) {
            visible = false;
        }

        if (q && !r.dataset.formattedId.toLowerCase().includes(q) &&
                 !r.dataset.company.toLowerCase().includes(q) &&
                 !r.dataset.clientname.toLowerCase().includes(q)) {
            visible = false;
        }

        r.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });

    rows.sort((a, b) => {
        const da = new Date(a.dataset.date);
        const db = new Date(b.dataset.date);
        return sortOrder === 'newest' ? db - da : da - db;
    });

    rows.forEach(r => list.appendChild(r));

    let emptyState = document.getElementById('empty-state');
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';
    }

    const firstVisible = rows.find(r => r.style.display !== 'none');
    if (firstVisible) {
        selectOrder(firstVisible, false);
    }
}

// ── Tab click handler ──────────────────────────────────────────────────────
document.querySelectorAll('.status-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        // Update active state styles
        document.querySelectorAll('.status-tab').forEach(b => {
            b.classList.remove('active-tab');
            b.classList.add('bg-gray-50', 'text-gray-400', 'border', 'border-transparent');
        });
        btn.classList.add('active-tab');
        btn.classList.remove('bg-gray-50', 'text-gray-400', 'border', 'border-transparent');

        activeStatus = btn.dataset.status;
        applyFilters();
    });
});

// ── Search handler ─────────────────────────────────────────────────────────
document.getElementById('order-search').addEventListener('input', e => {
    searchQuery = e.target.value;
    applyFilters();
});

// ── Sort handler ───────────────────────────────────────────────────────────
document.getElementById('order-sort').addEventListener('change', e => {
    sortOrder = e.target.value;
    applyFilters();
});

function showToast(message, variant = 'success', duration = 3500) {
    const icons = {
        success: '<i class="ti ti-circle-check"></i>',
        error:   '<i class="ti ti-circle-x"></i>',
        info:    '<i class="ti ti-info-circle"></i>'
    };
    const titles = { success: 'Success', error: 'Error', info: 'Info' };

    const t = document.createElement('div');
    t.className = `toast toast-${variant}`;
    t.innerHTML = `
        <div class="toast-icon">${icons[variant]}</div>
        <div class="toast-body">
            <p class="toast-title">${titles[variant]}</p>
            <p class="toast-msg">${message}</p>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">
            <i class="ti ti-x"></i>
        </button>
        <div class="toast-progress" style="animation-duration:${duration}ms"></div>
    `;

    document.getElementById('toast-container').appendChild(t);
    requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('toast-show')));
    setTimeout(() => {
        t.classList.remove('toast-show');
        t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, duration);
}

function updateStatus(id, status) {
    const labels = {
        processing: 'Payment accepted — order is now processing.',
        shipped:    'Order dispatched and marked as shipped.',
        delivered:  'Delivery confirmed successfully.',
        cancelled:  'Order has been cancelled.'
    };
    
    fetch('/api/orders.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const variant = status === 'cancelled' ? 'error' : 'success';
            showToast(labels[status] || `Status updated to ${status}.`, variant);
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message || 'Error updating status', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error updating status.', 'error');
    });
}

function dispatchOrder(oid) {
    const driverSelect = document.getElementById('assign-driver-select');
    const driverId = driverSelect ? driverSelect.value : '';
    if (!driverId) {
        showToast('Please select a driver to dispatch this cargo.', 'error');
        return;
    }
    
    fetch('/api/delivery.php?action=create_assignment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ driver_id: parseInt(driverId), orders: ['KE-2025-' + String(oid).padStart(5, '0')] })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Order dispatched and delivery assignment created!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message || 'Error creating assignment', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error creating assignment.', 'error');
    });
}

function closeOrderDetailPane() {
    const pane = document.getElementById('order-detail-pane');
    const backdrop = document.getElementById('order-detail-backdrop');
    if (pane) pane.classList.add('translate-x-full');
    if (backdrop) backdrop.classList.add('hidden');
    // Deselect cards
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('selected'));
}

// ── Initial render ─────────────────────────────────────────────────────────
applyFilters();
// Pane starts hidden on all screen sizes — opens only when a row is clicked
</script>
