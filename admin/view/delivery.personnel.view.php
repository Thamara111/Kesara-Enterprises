<?php
$admin_drivers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT dp.id, dp.name, dp.phone, dp.nic, dp.licence_class, dp.licence_expiry, dp.vehicle_type, dp.vehicle_number, dp.status, dp.joined_date 
                             FROM delivery_personnel dp");
        $drivers_db = $stmt->fetchAll();

        foreach ($drivers_db as $drv) {
            $words = explode(" ", $drv['name']);
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
            $avColor = $av_options[$drv['id'] % count($av_options)];

            $status_lower = strtolower($drv['status']);
            if ($status_lower === 'available') {
                $badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                $badgeText = 'Available';
            } elseif ($status_lower === 'on_run') {
                $badge = 'bg-blue-50 text-blue-700 border-blue-100';
                $badgeText = 'On run';
            } elseif ($status_lower === 'day_off') {
                $badge = 'bg-gray-50 text-gray-500 border-gray-100';
                $badgeText = 'Day off';
            } else {
                $badge = 'bg-red-50 text-red-700 border-red-100';
                $badgeText = 'Inactive';
            }

            $z_stmt = $pdo->prepare("SELECT zone FROM personnel_zones WHERE personnel_id = ?");
            $z_stmt->execute([$drv['id']]);
            $zones = $z_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($zones)) {
                $zones = ['General'];
            }

            $vehicle = ucfirst($drv['vehicle_type']) . ' · ' . $drv['vehicle_number'];
            $licence = $drv['licence_class'] . ' · expires ' . date('M Y', strtotime($drv['licence_expiry']));

            $ot = $drv['id'] == 1 ? '94%' : ($drv['id'] == 2 ? '88%' : ($drv['id'] == 3 ? '96%' : '90%'));
            $otW = $drv['id'] == 1 ? 94 : ($drv['id'] == 2 ? 88 : ($drv['id'] == 3 ? 96 : 90));
            $del = $drv['id'] == 1 ? 47 : ($drv['id'] == 2 ? 38 : ($drv['id'] == 3 ? 52 : 30));
            $fail = $drv['id'] == 1 ? 3 : ($drv['id'] == 2 ? 5 : ($drv['id'] == 3 ? 2 : 1));
            $avg = $drv['id'] == 1 ? '5.2 runs' : ($drv['id'] == 2 ? '4.1 runs' : ($drv['id'] == 3 ? '5.8 runs' : '4.0 runs'));

            $recent = [
              [ 'date' => '12 May', 'desc' => '3 drops · Colombo', 'status' => 'Done', 'failed' => false ],
              [ 'date' => '11 May', 'desc' => '4 drops · Gampaha', 'status' => 'Done', 'failed' => false ],
              [ 'date' => '10 May', 'desc' => '2 drops · Colombo', 'status' => '1 failed', 'failed' => true ]
            ];

            $todayRun = null;
            if ($status_lower === 'on_run') {
                $todayRun = [
                    'id' => 'Assignment DA-2025-' . str_pad($drv['id'], 4, '0', STR_PAD_LEFT),
                    'desc' => '3 deliveries · ' . $zones[0],
                    'prog' => '2 of 3 delivered'
                ];
            }

            $admin_drivers[] = [
                'av' => $initials,
                'avColor' => $avColor,
                'name' => $drv['name'],
                'phone' => $drv['phone'],
                'badge' => $badge,
                'badgeText' => $badgeText,
                'nic' => $drv['nic'],
                'vehicle' => $vehicle,
                'licence' => $licence,
                'zones' => $zones,
                'joined' => date('M Y', strtotime($drv['joined_date'])),
                'todayRun' => $todayRun,
                'ot' => $ot,
                'avInt' => $drv['id'],
                'otW' => $otW,
                'del' => $del,
                'fail' => $fail,
                'avg' => $avg,
                'recent' => $recent
            ];
        }
    } catch (\Exception $e) {
        // Handled via fallback
    }
}

if (empty($admin_drivers)) {
    $admin_drivers = [
      [ 
        'av' => 'NK', 
        'avColor' => 'bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-emerald-100', 
        'name' => 'Nuwan Karunaratne', 
        'phone' => '+94 77 111 2222', 
        'badge' => 'bg-blue-50 text-blue-700 border-blue-100', 
        'badgeText' => 'On run', 
        'nic' => '198812345678', 
        'vehicle' => 'Motorbike · WP CAB-4421', 
        'licence' => 'B · expires Jan 2027', 
        'zones' => ['Colombo', 'Gampaha'], 
        'joined' => 'Mar 2022', 
        'todayRun' => [
          'id' => 'Assignment DA-2025-0312',
          'desc' => '3 deliveries · Colombo 03, 07, 10',
          'prog' => '2 of 3 delivered'
        ], 
        'ot' => '94%', 
        'otW' => 94, 
        'del' => 47, 
        'fail' => 3, 
        'avg' => '5.2 runs', 
        'recent' => [
          [ 'date' => '12 May', 'desc' => '3 drops · Colombo', 'status' => 'Done', 'failed' => false ],
          [ 'date' => '11 May', 'desc' => '4 drops · Gampaha', 'status' => 'Done', 'failed' => false ],
          [ 'date' => '10 May', 'desc' => '2 drops · Colombo', 'status' => '1 failed', 'failed' => true ]
        ]
      ]
    ];
}

// Calculate dynamic stats
$total_drivers = count($admin_drivers);
$available_drivers = 0;
$on_run_drivers = 0;
$day_off_drivers = 0;
foreach ($admin_drivers as $d) {
    $status = strtolower($d['badgeText']);
    if ($status === 'available') $available_drivers++;
    elseif ($status === 'on run') $on_run_drivers++;
    elseif ($status === 'day off') $day_off_drivers++;
}
?>
<!-- Delivery Personnel View -->
<h2 class="sr-only">Delivery personnel management page mockup for Kesara Enterprises wholesale admin platform</h2>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane (Left) -->
    <div id="delivery-personnel-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-md sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Delivery Personnel</h1>
                <p class="text-sm text-gray-500 mt-1">Manage delivery personnel, assignments, and performance</p>
            </div>
            <div class="flex gap-3">
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all shadow-sm" onclick="downloadPDF('delivery-personnel-container', 'Delivery_Personnel')">
                    <i class="ti ti-download text-lg"></i>
                    Export PDF
                </button>
                <button onclick="sendPrompt('What should the add or edit delivery person page include for Kesara Enterprises admin?')" class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                    <i class="ti ti-plus text-lg"></i>
                    Add Person
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-4 gap-4 p-6 bg-gray-50/50">
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total</p>
                <p class="text-2xl font-bold text-gray-900"><?= $total_drivers ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-emerald-500">
                <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Available</p>
                <p class="text-2xl font-bold text-gray-900"><?= $available_drivers ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-blue-500">
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">On a run</p>
                <p class="text-2xl font-bold text-gray-900"><?= $on_run_drivers ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-gray-300">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Day off</p>
                <p class="text-2xl font-bold text-gray-900"><?= $day_off_drivers ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-100 bg-white flex flex-col gap-4">
            <div class="flex gap-3">
                <div class="relative flex-1 group">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input type="text" placeholder="Search name or vehicle..." 
                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none">
                </div>
                <select class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                    <option>All Zones</option>
                    <option>Colombo</option>
                    <option>Gampaha</option>
                    <option>Kandy</option>
                    <option>Southern</option>
                </select>
                <select class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                    <option>All Vehicles</option>
                    <option>Motorbike</option>
                    <option>Van</option>
                    <option>Lorry</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 chip on">All</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Available</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">On a run</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Day off</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Inactive</button>
            </div>
            <!-- List Content -->
        <div class="flex-1 overflow-auto p-6">
            <div class="min-w-[650px]">
                <div class="grid grid-cols-[40px_1fr_120px_120px_100px] gap-4 px-4 py-3 bg-gray-50 rounded-xl mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    <span></span>
                    <span>Name</span>
                    <span>Zone</span>
                    <span>Vehicle</span>
                    <span class="text-right">Status</span>
                </div>

                <div id="driver-list" class="space-y-2">
                    <?php foreach ($admin_drivers as $idx => $d): ?>
                    <?php
                        $vehicleIcon = 'ti-motorbike';
                        if (stripos($d['vehicle'], 'van') !== false) $vehicleIcon = 'ti-van';
                        elseif (stripos($d['vehicle'], 'lorry') !== false) $vehicleIcon = 'ti-truck';
                    ?>
                    <div class="driver-row group grid grid-cols-[40px_1fr_120px_120px_100px] gap-4 items-center p-4 rounded-2xl transition-all cursor-pointer bg-white border border-gray-100 hover:border-brand/30 hover:bg-gray-50"
                         data-idx="<?= $idx ?>"
                         data-av="<?= htmlspecialchars($d['av']) ?>"
                         data-av-color="<?= htmlspecialchars($d['avColor']) ?>"
                         data-name="<?= htmlspecialchars($d['name']) ?>"
                         data-phone="<?= htmlspecialchars($d['phone']) ?>"
                         data-badge="<?= htmlspecialchars($d['badge']) ?>"
                         data-badge-text="<?= htmlspecialchars($d['badgeText']) ?>"
                         data-nic="<?= htmlspecialchars($d['nic']) ?>"
                         data-vehicle="<?= htmlspecialchars($d['vehicle']) ?>"
                         data-licence="<?= htmlspecialchars($d['licence']) ?>"
                         data-zones="<?= htmlspecialchars(json_encode($d['zones'])) ?>"
                         data-joined="<?= htmlspecialchars($d['joined']) ?>"
                         data-today-run="<?= htmlspecialchars(json_encode($d['todayRun'])) ?>"
                         data-ot="<?= htmlspecialchars($d['ot']) ?>"
                         data-ot-w="<?= htmlspecialchars($d['otW']) ?>"
                         data-del="<?= htmlspecialchars($d['del']) ?>"
                         data-fail="<?= htmlspecialchars($d['fail']) ?>"
                         data-avg="<?= htmlspecialchars($d['avg']) ?>"
                         data-recent="<?= htmlspecialchars(json_encode($d['recent'])) ?>"
                         onclick="selectRow(this)">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs <?= $d['avColor'] ?>"><?= htmlspecialchars($d['av']) ?></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors"><?= htmlspecialchars($d['name']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($d['phone']) ?></p>
                        </div>
                        <span class="text-xs font-medium text-gray-600"><?= htmlspecialchars($d['zones'][0] ?? '') ?></span>
                        <span class="text-xs font-medium text-gray-900 flex items-center gap-1.5">
                            <i class="ti <?= $vehicleIcon ?> text-sm text-gray-400"></i> <?= htmlspecialchars(explode(' · ', $d['vehicle'])[0]) ?>
                        </span>
                        <div class="text-right">
                            <span class="px-3 py-1 <?= $d['badge'] ?> border rounded-full text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($d['badgeText']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($admin_drivers)): ?>
                        <div class="text-xs text-gray-400 text-center py-10 italic">No personnel found.</div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <div class="mt-8 flex justify-between items-center bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <span class="text-xs font-bold text-gray-400">SHOWING <?= $total_drivers ?> OF <?= $total_drivers ?> PERSONNEL</span>
                    <div class="flex gap-2">
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-brand transition-colors"><i class="ti ti-chevron-left"></i></button>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-brand-light font-bold text-xs">1</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-brand transition-colors"><i class="ti ti-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>>
    </div>

    <!-- Detail Pane -->
    <!-- Backdrop -->
    <div id="driver-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeDriverDetailPane()"></div>
    <div id="driver-detail-pane" class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto" id="detail-pane">
        <div class="p-8 flex-1 overflow-y-auto space-y-8">
            <!-- Profile Header -->
            <div class="flex flex-col items-center text-center relative">
                <button onclick="closeDriverDetailPane()" class="absolute top-0 right-0 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
                <div id="d-av" class="w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4">NK</div>
                <h2 id="d-name" class="text-xl font-bold text-gray-900 tracking-tight">Nuwan Karunaratne</h2>
                <p id="d-phone" class="text-sm text-gray-500 mt-1">+94 77 111 2222</p>
                <span id="d-badge" class="mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border">On run</span>
            </div>

            <!-- Details Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Details</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">NIC</span>
                        <span id="d-nic" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Vehicle</span>
                        <span id="d-vehicle" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Licence</span>
                        <span id="d-licence" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Zone(s)</span>
                        <span id="d-zones" class="flex flex-wrap gap-1.5 justify-end"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Joined</span>
                        <span id="d-joined" class="text-xs font-bold text-gray-900"></span>
                    </div>
                </div>
            </section>

            <!-- Today's Run Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Today's Run</h3>
                <div id="d-today-run" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl shadow-sm space-y-3">
                    <!-- Injected dynamically -->
                </div>
            </section>

            <!-- Performance Stats -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Performance (last 30 days)</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">On-time rate</span>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-16 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                                <div id="d-bar-ot" class="h-full rounded-full transition-all duration-500" style="width: 94%"></div>
                            </div>
                            <span id="d-ot" class="text-xs font-bold">94%</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Deliveries done</span>
                        <span id="d-del" class="text-xs font-bold text-gray-900">47</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Failed deliveries</span>
                        <span id="d-fail" class="text-xs font-bold text-gray-900">3</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Avg per day</span>
                        <span id="d-avg" class="text-xs font-bold text-gray-900">5.2 runs</span>
                    </div>
                </div>
            </section>

            <!-- Recent Runs -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Recent Runs</h3>
                <div id="d-recent" class="space-y-3">
                    <!-- Injected dynamically -->
                </div>
            </section>
        </div>

        <!-- Action Footer (Sticky) -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
            <button onclick="sendPrompt('What should the delivery assignments page show for Kesara Enterprises admin?')" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all">
                <i class="ti ti-map-pin text-lg"></i>
                Assign Order ↗
            </button>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="sendPrompt('What should the add or edit delivery person page include for Kesara Enterprises admin?')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-edit text-base"></i>
                    Edit Profile
                </button>
                <button id="d-deactivate" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all">
                    <i class="ti ti-ban text-base"></i>
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function barColor(w){ return w>=90?'#10b981':w>=75?'#f59e0b':'#ef4444'; }
function barText(w){ return w>=90?'#047857':w>=75?'#b45309':'#b91c1c'; }

let activeFilter = 'All';

function applyFilters() {
    document.querySelectorAll('.driver-row').forEach(r => {
        const status = r.dataset.badgeText;
        let visible = true;
        if (activeFilter !== 'All' && status !== activeFilter) {
            visible = false;
        }
        r.style.display = visible ? '' : 'none';
    });
}

function selectRow(el, openDrawer = true) {
  if (!el) return;
  document.querySelectorAll('.driver-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
  el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
  el.classList.remove('bg-white', 'border-gray-100');
  
  // Open drawer
  if (openDrawer) {
    const pane = document.getElementById('driver-detail-pane');
    const backdrop = document.getElementById('driver-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
  
  const av = document.getElementById('d-av');
  av.textContent = el.dataset.av;
  av.className = 'w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4 ' + el.dataset.avColor;
  
  document.getElementById('d-name').textContent = el.dataset.name;
  document.getElementById('d-phone').textContent = el.dataset.phone;
  
  const badge = document.getElementById('d-badge');
  badge.className = 'mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border ' + el.dataset.badge;
  badge.textContent = el.dataset.badgeText;
  
  document.getElementById('d-nic').textContent = el.dataset.nic;
  document.getElementById('d-vehicle').textContent = el.dataset.vehicle;
  document.getElementById('d-licence').textContent = el.dataset.licence;
  
  // Zones as badges
  let zones = [];
  try { zones = JSON.parse(el.dataset.zones || '[]'); } catch (e) {}
  document.getElementById('d-zones').innerHTML = zones.map(z => `
    <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-100 rounded-full text-[10px] font-bold">${z}</span>
  `).join('');
  
  document.getElementById('d-joined').textContent = el.dataset.joined;
  
  // Today's active run
  let todayRun = null;
  try { todayRun = JSON.parse(el.dataset.todayRun || 'null'); } catch (e) {}
  const runDiv = document.getElementById('d-today-run');
  if (todayRun) {
    runDiv.innerHTML = `
      <div class="flex justify-between items-start">
        <div>
          <p class="text-xs font-bold text-gray-900">${todayRun.id}</p>
          <p class="text-[11px] text-gray-500 mt-0.5">${todayRun.desc}</p>
        </div>
      </div>
      <div class="flex justify-between items-center text-[11px] pt-2 border-t border-gray-100 mt-2">
        <span class="text-gray-500 font-medium">${todayRun.prog}</span>
        <span class="text-brand font-bold cursor-pointer hover:underline" onclick="trackPersonnelRun('${todayRun.id}')">Track live ↗</span>
      </div>
    `;
  } else {
    runDiv.innerHTML = `<p class="text-xs text-gray-400 font-medium text-center py-2">No active run today</p>`;
  }
  
  // Performance
  const otW = parseInt(el.dataset.otW);
  document.getElementById('d-bar-ot').style.width = otW + '%';
  document.getElementById('d-bar-ot').style.backgroundColor = barColor(otW);
  document.getElementById('d-ot').textContent = el.dataset.ot;
  document.getElementById('d-ot').style.color = barText(otW);
  
  document.getElementById('d-del').textContent = el.dataset.del;
  document.getElementById('d-fail').textContent = el.dataset.fail;
  document.getElementById('d-avg').textContent = el.dataset.avg;
  
  // Recent runs
  let recent = [];
  try { recent = JSON.parse(el.dataset.recent || '[]'); } catch (e) {}
  document.getElementById('d-recent').innerHTML = recent.map(r => `
    <div class="flex justify-between items-center text-xs py-2 border-b border-gray-100 last:border-b-0">
        <span class="text-gray-400 font-medium">${r.date}</span>
        <span class="text-gray-700 font-medium">${r.desc}</span>
        <span class="flex items-center gap-1.5 font-bold ${r.failed ? 'text-red-600' : 'text-emerald-600'}">
            <span class="w-1.5 h-1.5 rounded-full ${r.failed ? 'bg-red-500' : 'bg-emerald-500'}"></span>
            ${r.status}
        </span>
    </div>
  `).join('');
}



function chipFilter(el) {
  document.querySelectorAll('.chip').forEach(c => {
    c.classList.remove('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
    c.classList.add('bg-white', 'text-gray-500', 'border-gray-200');
  });
  el.classList.add('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
  el.classList.remove('bg-white', 'text-gray-500', 'border-gray-200');
  
  activeFilter = el.textContent.trim();
  closeDriverDetailPane();
  applyFilters();
  
  const firstVisible = Array.from(document.querySelectorAll('.driver-row')).find(r => r.style.display !== 'none');
  if (firstVisible) {
      selectRow(firstVisible, false);
  }
}

function trackPersonnelRun(runIdText) {
  if (!runIdText) return;
  const parts = runIdText.split(' ');
  const runId = parts[parts.length - 1]; // Extracts e.g. "DA-2025-0312" from "Assignment DA-2025-0312"
  window.location.href = '/admin-tracking?id=' + runId;
}

function closeDriverDetailPane() {
  const pane = document.getElementById('driver-detail-pane');
  const backdrop = document.getElementById('driver-detail-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
  document.querySelectorAll('.driver-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
}

// Initial Render
applyFilters();
const firstRow = document.querySelector('.driver-row');
if (firstRow) selectRow(firstRow, false);
closeDriverDetailPane();
</script>
