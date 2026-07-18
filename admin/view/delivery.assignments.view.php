<?php
/**
 * Delivery Assignments View - Database Integration
 */
$admin_assignments = [];
$available_drivers = [];
$unassigned_orders = [];
$driver_info_map = [];

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch all assignments with driver and order info
        $stmt = $pdo->query("SELECT da.id, da.assigned_at, da.status, da.notes,
                                    dp.id AS driver_id, dp.name AS driver_name, dp.vehicle_type, dp.vehicle_number,
                                    o.id AS order_id,
                                    u.business_name AS company, u.address AS company_address
                             FROM delivery_assignments da
                             JOIN delivery_personnel dp ON da.personnel_id = dp.id
                             JOIN orders o ON da.order_id = o.id
                             JOIN users u ON o.user_id = u.id
                             ORDER BY da.assigned_at DESC");
        $asgn_db = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($asgn_db as $row) {
            $key = $row['driver_id'] . '_' . date('Y-m-d_H-i', strtotime($row['assigned_at']));
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => 'DA-2025-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                    'date' => 'Assigned ' . date('d M, g:i A', strtotime($row['assigned_at'])),
                    'driver' => $row['driver_name'],
                    'vehicle' => ucfirst($row['vehicle_type']) . ' · ' . $row['vehicle_number'],
                    'status' => $row['status'],
                    'stops' => [],
                    'driver_id' => $row['driver_id']
                ];
            }
            $status_text = $row['status'] === 'completed' ? 'Delivered' : ($row['status'] === 'in_progress' ? 'In progress' : 'Pending');
            $grouped[$key]['stops'][] = [
                'num' => count($grouped[$key]['stops']) + 1,
                'name' => 'KE-2025-' . str_pad($row['order_id'], 5, '0', STR_PAD_LEFT) . ' · ' . htmlspecialchars($row['company']),
                'addr' => htmlspecialchars($row['company_address']),
                'status' => $status_text,
                'lat' => null,
                'lng' => null
            ];
        }
        
        foreach ($grouped as $key => $g) {
            $badge = 'bg-amber-50 text-amber-700 border-amber-100';
            $badgeText = 'Pending';
            if ($g['status'] === 'completed') {
                $badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                $badgeText = 'Completed';
            } elseif ($g['status'] === 'in_progress') {
                $badge = 'bg-blue-50 text-blue-750 border-blue-100';
                $badgeText = 'Active';
            } elseif ($g['status'] === 'failed') {
                $badge = 'bg-red-50 text-red-700 border-red-100';
                $badgeText = 'Failed';
            }
            
            $words = explode(" ", $g['driver']);
            $av = "";
            foreach ($words as $w) {
                $av .= strtoupper(substr($w, 0, 1));
            }
            $av = substr($av, 0, 2);
            
            $admin_assignments[] = [
                'id' => $g['id'],
                'date' => $g['date'],
                'badge' => $badge,
                'badgeText' => $badgeText,
                'av' => $av,
                'avColor' => 'bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-emerald-100',
                'driver' => $g['driver'],
                'vehicle' => $g['vehicle'],
                'canCancel' => $g['status'] === 'pending',
                'zone' => 'Colombo',
                'stops' => $g['stops']
            ];
        }
        
        // Fetch available drivers
        $driver_stmt = $pdo->query("SELECT id, name, vehicle_type, vehicle_number FROM delivery_personnel WHERE status = 'available'");
        $available_drivers = $driver_stmt->fetchAll();
        
        // Fetch driver performance info
        $all_drivers_stmt = $pdo->query("SELECT id, name, vehicle_type, vehicle_number FROM delivery_personnel");
        $all_drivers = $all_drivers_stmt->fetchAll();
        foreach ($all_drivers as $d) {
            $words = explode(" ", $d['name']);
            $initials = "";
            foreach ($words as $w) {
                $initials .= strtoupper(substr($w, 0, 1));
            }
            $initials = substr($initials, 0, 2);
            
            $driver_info_map[$d['id']] = [
                'vehicle' => ucfirst($d['vehicle_type']) . ' · ' . $d['vehicle_number'],
                'zone' => 'Colombo',
                'ot' => '90%'
            ];
            $driver_info_map[$initials] = [
                'vehicle' => ucfirst($d['vehicle_type']) . ' · ' . $d['vehicle_number'],
                'zone' => 'Colombo',
                'ot' => '90%'
            ];
        }
        
        // Fetch unassigned orders
        $unassigned_stmt = $pdo->query("SELECT o.id 
                                        FROM orders o 
                                        LEFT JOIN delivery_assignments da ON o.id = da.order_id 
                                        WHERE da.id IS NULL AND o.status IN ('processing', 'pending') 
                                        LIMIT 5");
        $unassigned_orders = $unassigned_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Exception $e) {
        $db_error = $e->getMessage();
    }
}

if (empty($admin_assignments)) {
    $admin_assignments = [];
}

if (empty($driver_info_map)) {
    $driver_info_map = [];
}

if (empty($unassigned_orders)) {
    $unassigned_orders = [];
}

$today_total = count($admin_assignments);
$in_progress_cnt = 0;
$completed_cnt = 0;
$pending_cnt = 0;
foreach ($admin_assignments as $a) {
    $stat_lower = strtolower($a['badgeText']);
    if ($stat_lower === 'active' || $stat_lower === 'in progress') {
        $in_progress_cnt++;
    } elseif ($stat_lower === 'completed') {
        $completed_cnt++;
    } elseif ($stat_lower === 'pending') {
        $pending_cnt++;
    }
}
?>
<!-- Delivery Assignments View -->
<h2 class="sr-only">Delivery assignments management page mockup for Kesara Enterprises wholesale admin platform</h2>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane (Left) -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-md sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Delivery Assignments</h1>
                <p class="text-sm text-gray-500 mt-1">Tuesday, 13 May 2025</p>
            </div>
            <div class="flex gap-3">
                <input type="date" value="2025-05-13" class="px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-brand/20" onclick="showNewForm()">
                    <i class="ti ti-plus text-lg"></i>
                    New Assignment
                </button>
            </div>
        </div>

        <!-- Warning Banner (Unassigned Orders) -->
        <?php if (!empty($unassigned_orders)): ?>
        <div class="m-6 p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-start gap-3 text-xs text-amber-700">
            <i class="ti ti-alert-triangle text-amber-600 text-lg flex-shrink-0 mt-0.5" aria-hidden="true"></i>
            <span class="leading-relaxed"><strong><?= count($unassigned_orders) ?> orders</strong> are ready to dispatch but not yet assigned — <?= implode(', ', array_map(fn($id) => 'KE-2025-' . str_pad($id, 5, '0', STR_PAD_LEFT), $unassigned_orders)) ?>.</span>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-4 gap-4 px-6 pb-6 bg-white">
            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Today Total</p>
                <p class="text-2xl font-bold text-gray-900"><?= $today_total ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-blue-500">
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">In Progress</p>
                <p class="text-2xl font-bold text-gray-900"><?= $in_progress_cnt ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-emerald-500">
                <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Completed</p>
                <p class="text-2xl font-bold text-gray-900"><?= $completed_cnt ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-amber-500">
                <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">Pending Start</p>
                <p class="text-2xl font-bold text-gray-900"><?= $pending_cnt ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-100 bg-white flex flex-col gap-4">
            <div class="flex gap-2">
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 chip on">All</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Pending</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">In progress</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Completed</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Failed</button>
            </div>
        </div>

        <!-- List Content -->
        <div class="flex-1 overflow-auto p-6">
            <div class="min-w-[700px]">
                <div class="grid grid-cols-[1fr_100px_80px_100px_100px] gap-4 px-4 py-3 bg-gray-50 rounded-xl mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    <span>Assignment / Driver</span>
                    <span>Zone</span>
                    <span class="text-center">Orders</span>
                    <span class="text-center">Progress</span>
                    <span class="text-right">Status</span>
                </div>

                <div id="asgn-list" class="space-y-2">
                    <?php foreach ($admin_assignments as $idx => $a): ?>
                    <?php
                        $completedStops = count(array_filter($a['stops'], function($s) { return strpos($s['status'], 'Delivered') === 0; }));
                        $totalStops = count($a['stops']);
                        $progressText = $completedStops . ' / ' . $totalStops;
                        $zone = (stripos($a['vehicle'], 'motorbike') !== false) ? 'Colombo' : ((stripos($a['vehicle'], 'van') !== false) ? 'Gampaha' : 'Kandy');
                        
                        $driverNameParts = explode(' ', $a['driver']);
                        $driverShortName = $driverNameParts[0] . (isset($driverNameParts[1]) ? ' ' . substr($driverNameParts[1], 0, 1) . '.' : '');
                    ?>
                    <div class="asgn-row group grid grid-cols-[1fr_100px_80px_100px_100px] gap-4 items-center p-4 rounded-2xl transition-all cursor-pointer bg-white border border-gray-100 hover:border-brand/30 hover:bg-gray-50"
                         data-idx="<?= $idx ?>"
                         data-id="<?= htmlspecialchars($a['id']) ?>"
                         data-date="<?= htmlspecialchars($a['date']) ?>"
                         data-badge="<?= htmlspecialchars($a['badge']) ?>"
                         data-badge-text="<?= htmlspecialchars($a['badgeText']) ?>"
                         data-av="<?= htmlspecialchars($a['av']) ?>"
                         data-av-color="<?= htmlspecialchars($a['avColor']) ?>"
                         data-driver="<?= htmlspecialchars($a['driver']) ?>"
                         data-vehicle="<?= htmlspecialchars($a['vehicle']) ?>"
                         data-can-cancel="<?= htmlspecialchars($a['canCancel'] ? '1' : '0') ?>"
                         data-stops="<?= htmlspecialchars(json_encode($a['stops'])) ?>"
                         onclick="selectRow(this)">
                        <div>
                            <p class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors"><?= htmlspecialchars($a['id']) ?></p>
                            <p class="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[9px] <?= $a['avColor'] ?>"><?= htmlspecialchars($a['av']) ?></span>
                                <?= htmlspecialchars($driverShortName) ?>
                            </p>
                        </div>
                        <span class="text-xs font-medium text-gray-600"><?= htmlspecialchars($zone) ?></span>
                        <span class="text-xs font-bold text-gray-900 text-center"><?= $totalStops ?></span>
                        <span class="text-xs font-bold text-blue-700 text-center"><?= $progressText ?></span>
                        <div class="text-right">
                            <span class="px-3 py-1 <?= $a['badge'] ?> border rounded-full text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($a['badgeText']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($admin_assignments)): ?>
                        <div class="text-xs text-gray-400 text-center py-10 italic">No assignments match this filter.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Pane (Details or Form) -->
    <!-- Backdrop -->
    <div id="asgn-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeAsgnDetailPane()"></div>
    <div id="asgn-detail-pane" class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto">
            
            <!-- DETAIL VIEW -->
            <div id="detail-view" class="space-y-8 relative">
                <!-- Header Block -->
                <div class="flex justify-between items-start">
                    <div>
                        <h2 id="d-id" class="text-xl font-bold text-gray-900 tracking-tight"></h2>
                        <p id="d-date" class="text-xs text-gray-500 mt-1"></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="d-badge" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase border"></span>
                        <button onclick="closeAsgnDetailPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Driver Info Card -->
                <section>
                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Driver</h3>
                    <div class="flex items-center gap-3 p-4 bg-gray-50 border border-gray-100 rounded-2xl shadow-sm">
                        <div id="d-av" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs"></div>
                        <div>
                            <p id="d-driver" class="text-sm font-bold text-gray-900"></p>
                            <p id="d-vehicle" class="text-xs text-gray-500 mt-0.5"></p>
                        </div>
                    </div>
                </section>

                <!-- Stops Timeline Section -->
                <section>
                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Delivery Stops</h3>
                    <div id="d-stops" class="space-y-6 pl-4 relative before:absolute before:left-[9px] before:top-2 before:bottom-2 before:w-px before:bg-gray-100">
                        <!-- Timelines injected here -->
                    </div>
                </section>

                <div class="h-px bg-gray-100"></div>

                <!-- Actions -->
                <section class="space-y-3">
                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Actions</h3>
                    <button class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all" onclick="trackLiveRun()">
                        <i class="ti ti-radar text-lg"></i>
                        Track Live Run ↗
                    </button>
                    <div class="grid grid-cols-2 gap-3">
                        <button id="d-reassign-btn" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                            <i class="ti ti-refresh text-base"></i>
                            Reassign Driver
                        </button>
                        <button id="d-cancel-btn" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all">
                            <i class="ti ti-x text-base"></i>
                            Cancel Run
                        </button>
                    </div>
                </section>
            </div>

            <!-- NEW FORM VIEW -->
            <div id="new-form" class="space-y-6 relative" style="display: none;">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">New Assignment</h2>
                    <button onclick="closeAsgnDetailPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <!-- Assign Driver -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Assign to Driver <span class="text-red-500">*</span></label>
                    <select id="driver-select" onchange="updateDriverInfo()" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none cursor-pointer">
                        <option value="">Select available driver...</option>
                        <?php foreach ($available_drivers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> — <?= ucfirst($d['vehicle_type']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($available_drivers)): ?>
                        <option value="SR">Saman Rajapaksa — Van · Gampaha</option>
                        <option value="LW">Lalith Wickrama — Van · Kandy</option>
                        <option value="KP">Kasun Perera — Lorry · Southern</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Driver Info Card -->
                <div id="driver-info" style="display: none;" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl text-xs space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 font-medium">Vehicle</span>
                        <span id="di-vehicle" class="font-bold text-gray-900">—</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 font-medium">Zone</span>
                        <span id="di-zone" class="font-bold text-gray-900">—</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 font-medium">On-time rate</span>
                        <span id="di-ot" class="font-bold text-emerald-600">—</span>
                    </div>
                </div>

                <!-- Orders Checklist -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500 block">Select Orders to Include <span class="text-red-500">*</span></label>
                    <p class="text-[11px] text-gray-400 font-medium">Ready-to-dispatch orders not yet assigned</p>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <?php foreach ($unassigned_orders as $ord_id): 
                            $formatted_ord_id = 'KE-2025-' . str_pad($ord_id, 5, '0', STR_PAD_LEFT);
                        ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-200 bg-white text-gray-500 hover:border-brand/30 text-xs font-bold cursor-pointer select-none transition-all order-chip" onclick="toggleOrderChip(this)">
                            <i class="ti ti-package text-sm"></i> <?= $formatted_ord_id ?>
                        </span>
                        <?php endforeach; ?>
                        
                        <?php if (empty($unassigned_orders)): ?>
                            <span class="text-xs text-gray-400 italic">No unassigned processing orders available.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Zone -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Delivery Zone</label>
                    <select class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none cursor-pointer">
                        <option>Colombo</option>
                        <option>Gampaha</option>
                        <option>Kandy</option>
                        <option>Southern</option>
                    </select>
                </div>

                <!-- Scheduled Departure -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Scheduled Departure</label>
                    <input type="time" value="14:00" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-850 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Notes for Driver</label>
                    <textarea rows="2" placeholder="e.g. Call ahead to Fashion Hub — ask for Nimal..." class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-850 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none resize-none"></textarea>
                </div>

                <!-- Cancel / Dispatch buttons -->
                <div class="grid grid-cols-2 gap-3 pt-4 border-t border-gray-100">
                    <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all" onclick="showDetailView(0)">Cancel</button>
                    <button class="px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 shadow-lg shadow-brand/10 transition-all" onclick="dispatchNewAssignment()">Create &amp; Dispatch ↗</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
var driverInfo = <?php echo json_encode($driver_info_map); ?>;
var activeFilter = 'All';

function applyFilters() {
    document.querySelectorAll('.asgn-row').forEach(r => {
        var status = r.dataset.badgeText;
        var visible = true;
        if (activeFilter !== 'All' && status !== activeFilter) {
            if (activeFilter === 'In progress' && status !== 'Active') visible = false;
            else if (activeFilter !== 'In progress') visible = false;
        }
        r.style.display = visible ? '' : 'none';
    });
}

function selectRow(el, openDrawer = true) {
  if (!el) return;
  document.querySelectorAll('.asgn-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
  el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
  el.classList.remove('bg-white', 'border-gray-100');
  
  // Open drawer
  if (openDrawer) {
    var pane = document.getElementById('asgn-detail-pane');
    var backdrop = document.getElementById('asgn-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
  
  showDetailView(el);
}

function getStopsHTML(stops) {
  return stops.map(s => {
    var statusClass = 'bg-gray-50 text-gray-500 border border-gray-100';
    if (s.status.startsWith('Delivered')) {
      statusClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    } else if (s.status === 'In progress') {
      statusClass = 'bg-blue-50 text-blue-700 border border-blue-100 animate-pulse';
    } else if (s.status === 'Not started') {
      statusClass = 'bg-amber-50 text-amber-700 border border-amber-100';
    }
    
    return `
      <div class="relative flex gap-4">
          <!-- Step circle -->
          <div class="w-5 h-5 rounded-full bg-brand-light border border-brand/20 text-brand text-xs font-bold flex items-center justify-center shrink-0 z-10">${s.num}</div>
          <div class="flex-1 min-w-0 pb-4">
              <p class="text-xs font-bold text-gray-900">${s.name}</p>
              <p class="text-[11px] text-gray-500 mt-0.5">${s.addr}</p>
              <div class="mt-2">
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${statusClass}">${s.status}</span>
              </div>
          </div>
      </div>
    `;
  }).join('');
}

function showDetailView(el) {
  if (!el) return;
  document.getElementById('new-form').style.display = 'none';
  document.getElementById('detail-view').style.display = 'block';
  
  document.getElementById('d-id').textContent = el.dataset.id;
  document.getElementById('d-date').textContent = el.dataset.date;
  
  var badge = document.getElementById('d-badge');
  badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase border ' + el.dataset.badge;
  badge.textContent = el.dataset.badgeText;
  
  var av = document.getElementById('d-av');
  av.textContent = el.dataset.av;
  av.className = 'w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs ' + el.dataset.avColor;
  
  document.getElementById('d-driver').textContent = el.dataset.driver;
  document.getElementById('d-vehicle').textContent = el.dataset.vehicle;
  
  // Render timeline drops
  var stops = [];
  try { stops = JSON.parse(el.dataset.stops || '[]'); } catch (e) {}
  document.getElementById('d-stops').innerHTML = getStopsHTML(stops);
  
  var cancelBtn = document.getElementById('d-cancel-btn');
  cancelBtn.style.display = el.dataset.canCancel === '1' ? 'block' : 'none';
  
  var reassignBtn = document.getElementById('d-reassign-btn');
  reassignBtn.style.display = el.dataset.badgeText === 'Completed' ? 'none' : 'block';
}

function showNewForm() {
  document.querySelectorAll('.asgn-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
  document.getElementById('detail-view').style.display = 'none';
  document.getElementById('new-form').style.display = 'block';
  
  // Open drawer
  var pane = document.getElementById('asgn-detail-pane');
  var backdrop = document.getElementById('asgn-detail-backdrop');
  if (pane) pane.classList.remove('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('hidden');
      requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
  }
}

function updateDriverInfo() {
  var val = document.getElementById('driver-select').value;
  var info = document.getElementById('driver-info');
  if (val && driverInfo[val]) {
    info.style.display = 'block';
    document.getElementById('di-vehicle').textContent = driverInfo[val].vehicle;
    document.getElementById('di-zone').textContent = driverInfo[val].zone;
    document.getElementById('di-ot').textContent = driverInfo[val].ot;
  } else {
    info.style.display = 'none';
  }
}

function toggleOrderChip(el) {
  el.classList.toggle('sel');
  if (el.classList.contains('sel')) {
    el.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-brand bg-brand-light text-brand text-xs font-bold cursor-pointer select-none transition-all order-chip sel';
  } else {
    el.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-200 bg-white text-gray-500 hover:border-brand/30 text-xs font-bold cursor-pointer select-none transition-all order-chip';
  }
}

function chipFilter(el) {
  document.querySelectorAll('.chip').forEach(c => {
    c.classList.remove('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
    c.classList.add('bg-white', 'text-gray-500', 'border-gray-200');
  });
  el.classList.add('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
  el.classList.remove('bg-white', 'text-gray-500', 'border-gray-200');
  
  activeFilter = el.textContent.trim();
  closeAsgnDetailPane();
  applyFilters();
  
  var firstVisible = Array.from(document.querySelectorAll('.asgn-row')).find(r => r.style.display !== 'none');
  if (firstVisible) {
      selectRow(firstVisible, false);
  }
}

function trackLiveRun() {
  var currentId = document.getElementById('d-id').textContent;
  if (currentId) {
    window.location.href = '/admin-tracking?id=' + currentId;
  } else {
    window.location.href = '/admin-tracking';
  }
}

function dispatchNewAssignment() {
  var driverSelect = document.getElementById('driver-select');
  var driverVal = driverSelect.value;
  if (!driverVal) {
    showToast('Please select an available driver.', 'error');
    return;
  }
  
  var driverName = driverSelect.options[driverSelect.selectedIndex].text;
  
  var selectedChips = document.querySelectorAll('.order-chip.sel');
  if (selectedChips.length === 0) {
    showToast('Please select at least one order to include.', 'error');
    return;
  }
  
  var selectedOrders = Array.from(selectedChips).map(chip => chip.textContent.trim());
  var notes = document.querySelector('textarea') ? document.querySelector('textarea').value : '';
  
  var driverId = parseInt(driverVal);
  if (isNaN(driverId)) {
      showToast('Invalid driver selected.', 'error');
      return;
  }
  
  fetch('/api/delivery.php?action=create_assignment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ driver_id: driverId, orders: selectedOrders, notes: notes })
  })
  .then(res => res.json())
  .then(data => {
      if (data.status === 'success') {
          showToast(`Assigned ${selectedOrders.length} orders to ${driverName} and dispatched!`, 'success');
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

function closeAsgnDetailPane() {
  var pane = document.getElementById('asgn-detail-pane');
  var backdrop = document.getElementById('asgn-detail-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
  document.querySelectorAll('.asgn-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
}

// Initial Render
applyFilters();
var firstRow = document.querySelector('.asgn-row');
if (firstRow) {
  setTimeout(() => {
    selectRow(firstRow, false);
    closeAsgnDetailPane();
  }, 100);
}
</script>

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
</script>
