<?php
/**
 * Delivery Personnel Management View
 * Handles adding, editing, and viewing details of delivery drivers.
 * Also processes POST actions for driver operations directly in the view.
 */

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    
    if (($action === 'add_driver' || $action === 'edit_driver') && isset($pdo)) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $nic = trim($_POST['nic'] ?? '');
        $licence_class = trim($_POST['licence_class'] ?? 'B');
        $licence_expiry = $_POST['licence_expiry'] ?? date('Y-m-d', strtotime('+3 years'));
        $vehicle_type = $_POST['vehicle_type'] ?? 'motorbike';
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $status = $_POST['status'] ?? 'available';
        
        if (empty($name) || empty($phone) || empty($email)) {
            $error_msg = "Name, Email, and Phone number are required.";
        } else {
            try {
                if ($action === 'add_driver') {
                    $hashed_password = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : password_hash('driver123', PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO delivery_personnel (name, email, password, phone, nic, licence_class, licence_expiry, vehicle_type, vehicle_number, status, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                    $stmt->execute([$name, $email, $hashed_password, $phone, $nic, $licence_class, $licence_expiry, $vehicle_type, $vehicle_number, $status]);
                    $success_msg = "Delivery personnel added successfully!";
                } else {
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE delivery_personnel SET name = ?, email = ?, password = ?, phone = ?, nic = ?, licence_class = ?, licence_expiry = ?, vehicle_type = ?, vehicle_number = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hashed_password, $phone, $nic, $licence_class, $licence_expiry, $vehicle_type, $vehicle_number, $status, $driver_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE delivery_personnel SET name = ?, email = ?, phone = ?, nic = ?, licence_class = ?, licence_expiry = ?, vehicle_type = ?, vehicle_number = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $nic, $licence_class, $licence_expiry, $vehicle_type, $vehicle_number, $status, $driver_id]);
                    }
                    $success_msg = "Profile updated successfully!";
                }
            } catch (Exception $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_driver_status' && $driver_id > 0 && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT status FROM delivery_personnel WHERE id = ?");
            $stmt->execute([$driver_id]);
            $current_status = $stmt->fetchColumn();
            
            $new_status = ($current_status === 'inactive') ? 'available' : 'inactive';
            $up_stmt = $pdo->prepare("UPDATE delivery_personnel SET status = ? WHERE id = ?");
            $up_stmt->execute([$new_status, $driver_id]);
            
            $success_msg = "Status updated to " . $new_status;
        } catch (Exception $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

$admin_drivers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT dp.id, dp.name, dp.email, dp.phone, dp.nic, dp.licence_class, dp.licence_expiry, dp.vehicle_type, dp.vehicle_number, dp.status, dp.joined_date 
                             FROM delivery_personnel dp ORDER BY dp.name ASC");
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
            $badge = 'bg-gray-50 text-gray-500 border-gray-100';
            $badgeText = ucfirst($drv['status']);
            
            if ($status_lower === 'available') {
                $badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                $badgeText = 'Available';
            } elseif ($status_lower === 'on_run') {
                $badge = 'bg-blue-50 text-blue-700 border-blue-100';
                $badgeText = 'On run';
            } elseif ($status_lower === 'day_off') {
                $badge = 'bg-gray-50 text-gray-500 border-gray-100';
                $badgeText = 'Day off';
            } elseif ($status_lower === 'inactive') {
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

            // Calculate dynamic delivered & failed stops counts
            $del_stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE personnel_id = ? AND status = 'completed'");
            $del_stmt->execute([$drv['id']]);
            $del = (int)$del_stmt->fetchColumn();

            $fail_stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE personnel_id = ? AND status = 'failed'");
            $fail_stmt->execute([$drv['id']]);
            $fail = (int)$fail_stmt->fetchColumn();

            $total_asgns = $del + $fail;
            if ($total_asgns > 0) {
                $otW = round(($del / $total_asgns) * 100);
            } else {
                $otW = 100; // default to 100% if no historical runs
            }
            $ot = $otW . '%';

            // Average per day
            $days_stmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(assigned_at)) FROM delivery_assignments WHERE personnel_id = ?");
            $days_stmt->execute([$drv['id']]);
            $days_active = (int)$days_stmt->fetchColumn() ?: 1;
            
            $runs_stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE personnel_id = ?");
            $runs_stmt->execute([$drv['id']]);
            $total_runs = (int)$runs_stmt->fetchColumn();
            
            $avg_val = round($total_runs / $days_active, 1);
            $avg = "$avg_val runs";

            // Recent runs (last 3 assignments)
            $rec_stmt = $pdo->prepare("
                SELECT da.id, da.assigned_at, da.status, u.business_name
                FROM delivery_assignments da
                JOIN orders o ON da.order_id = o.id
                JOIN users u ON o.user_id = u.id
                WHERE da.personnel_id = ?
                ORDER BY da.assigned_at DESC
                LIMIT 3
            ");
            $rec_stmt->execute([$drv['id']]);
            $rec_db = $rec_stmt->fetchAll();

            $recent = [];
            foreach ($rec_db as $rec_row) {
                $status_txt = $rec_row['status'] === 'completed' ? 'Done' : ($rec_row['status'] === 'failed' ? 'Failed' : 'Active');
                $recent[] = [
                    'date' => date('d M', strtotime($rec_row['assigned_at'])),
                    'desc' => "1 drop · " . $rec_row['business_name'],
                    'status' => $status_txt,
                    'failed' => ($rec_row['status'] === 'failed')
                ];
            }

            if (empty($recent)) {
                $recent = [
                    [ 'date' => date('d M'), 'desc' => 'No recent runs', 'status' => 'None', 'failed' => false ]
                ];
            }

            $todayRun = null;
            if ($status_lower === 'on_run') {
                $todayRun = [
                    'id' => 'Assignment DA-2025-' . str_pad($drv['id'], 4, '0', STR_PAD_LEFT),
                    'desc' => '3 deliveries · ' . $zones[0],
                    'prog' => '2 of 3 delivered'
                ];
            }

            $admin_drivers[] = [
                'id' => $drv['id'],
                'av' => $initials,
                'avColor' => $avColor,
                'name' => $drv['name'],
                'email' => $drv['email'] ?? '',
                'phone' => $drv['phone'],
                'status' => $drv['status'],
                'badge' => $badge,
                'badgeText' => $badgeText,
                'nic' => $drv['nic'],
                'vehicle_type' => $drv['vehicle_type'],
                'vehicle_number' => $drv['vehicle_number'],
                'licence_class' => $drv['licence_class'],
                'licence_expiry' => $drv['licence_expiry'],
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
        $error_msg = "Error: " . $e->getMessage();
    }
}

if (empty($admin_drivers)) {
    $admin_drivers = [];
}

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
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all shadow-sm" onclick="window.print()">
                    <i class="ti ti-download text-lg"></i>
                    Export / Print
                </button>
                <button onclick="openAddDriverModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                    <i class="ti ti-plus text-lg"></i>
                    Add Person
                </button>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="m-6 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl text-xs font-semibold text-emerald-700">
                <?= htmlspecialchars($success_msg) ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showToast(<?= json_encode($success_msg) ?>, 'success');
                });
            </script>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="m-6 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-semibold text-red-700">
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showToast(<?= json_encode($error_msg) ?>, 'error');
                });
            </script>
        <?php endif; ?>

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
            <div class="flex gap-2">
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 chip on">All</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Available</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">On a run</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Day off</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Inactive</button>
            </div>
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
                    <?php foreach ($admin_drivers as $d): ?>
                    <div class="driver-row group grid grid-cols-[40px_1fr_120px_120px_100px] gap-4 items-center p-4 rounded-2xl transition-all cursor-pointer bg-white border border-gray-100 hover:border-brand/30 hover:bg-gray-50"
                         onclick="selectRow(this)"
                         data-id="<?= $d['id'] ?>"
                         data-av="<?= htmlspecialchars($d['av']) ?>"
                         data-av-color="<?= htmlspecialchars($d['avColor']) ?>"
                         data-name="<?= htmlspecialchars($d['name']) ?>"
                         data-email="<?= htmlspecialchars($d['email']) ?>"
                         data-phone="<?= htmlspecialchars($d['phone']) ?>"
                         data-status="<?= htmlspecialchars($d['status']) ?>"
                         data-badge="<?= htmlspecialchars($d['badge']) ?>"
                         data-badge-text="<?= htmlspecialchars($d['badgeText']) ?>"
                         data-nic="<?= htmlspecialchars($d['nic']) ?>"
                         data-vehicle-type="<?= htmlspecialchars($d['vehicle_type']) ?>"
                         data-vehicle-number="<?= htmlspecialchars($d['vehicle_number']) ?>"
                         data-licence-class="<?= htmlspecialchars($d['licence_class']) ?>"
                         data-licence-expiry="<?= htmlspecialchars($d['licence_expiry']) ?>"
                         data-vehicle="<?= htmlspecialchars($d['vehicle']) ?>"
                         data-licence="<?= htmlspecialchars($d['licence']) ?>"
                         data-zones="<?= htmlspecialchars(json_encode($d['zones']), ENT_QUOTES, 'UTF-8') ?>"
                         data-joined="<?= htmlspecialchars($d['joined'], ENT_QUOTES, 'UTF-8') ?>"
                         data-today-run="<?= htmlspecialchars(json_encode($d['todayRun']), ENT_QUOTES, 'UTF-8') ?>"
                         data-ot="<?= htmlspecialchars($d['ot'], ENT_QUOTES, 'UTF-8') ?>"
                         data-ot-w="<?= htmlspecialchars($d['otW'], ENT_QUOTES, 'UTF-8') ?>"
                         data-del="<?= htmlspecialchars($d['del'], ENT_QUOTES, 'UTF-8') ?>"
                         data-fail="<?= htmlspecialchars($d['fail'], ENT_QUOTES, 'UTF-8') ?>"
                         data-avg="<?= htmlspecialchars($d['avg'], ENT_QUOTES, 'UTF-8') ?>"
                         data-recent="<?= htmlspecialchars(json_encode($d['recent']), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold border shadow-sm group-hover:scale-105 transition-all <?= $d['avColor'] ?>">
                            <?= $d['av'] ?>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 leading-tight"><?= htmlspecialchars($d['name']) ?></p>
                            <p class="text-xs text-gray-400 font-medium mt-0.5"><?= htmlspecialchars($d['phone']) ?></p>
                        </div>
                        <span class="text-xs font-semibold text-gray-600"><?= implode(', ', $d['zones']) ?></span>
                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-600">
                            <i class="ti ti-truck text-base text-gray-400"></i>
                            <span><?= htmlspecialchars($d['vehicle']) ?></span>
                        </div>
                        <div class="text-right">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $d['badge'] ?>"><?= $d['badgeText'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Backdrop -->
    <div id="driver-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeDriverDetailPane()"></div>

    <!-- Detail Pane (Right) -->
    <div id="driver-detail-pane" class="fixed inset-y-0 right-0 z-50 w-[380px] max-w-full bg-white flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto space-y-8 relative">
            <button onclick="closeDriverDetailPane()" class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                <i class="ti ti-x text-xl"></i>
            </button>

            <!-- Driver Header details -->
            <div class="flex flex-col items-center text-center">
                <div id="d-av" class="w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4"></div>
                <h2 id="d-name" class="text-lg font-bold text-gray-900 tracking-tight"></h2>
                <p id="d-phone" class="text-xs text-gray-500 font-medium mt-0.5"></p>
                <div class="flex gap-2">
                    <span id="d-badge" class="mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border"></span>
                </div>
            </div>

            <!-- Profile Info -->
            <section class="space-y-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Credentials &amp; Vehicle</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">NIC / ID Number</span>
                        <span id="d-nic" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Vehicle info</span>
                        <span id="d-vehicle" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Driver Licence</span>
                        <span id="d-licence" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Active Zones</span>
                        <div id="d-zones" class="flex gap-1.5 flex-wrap justify-end"></div>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Joined Date</span>
                        <span id="d-joined" class="font-bold text-gray-900"></span>
                    </div>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Active Run -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Active Run (Today)</h3>
                <div id="d-today-run" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl shadow-sm"></div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Performance Metrics -->
            <section class="space-y-5">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Overall Metrics</h3>
                
                <div>
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-gray-600 font-semibold">On-Time Delivery Rate</span>
                        <span id="d-ot" class="font-extrabold text-sm"></span>
                    </div>
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div id="d-bar-ot" class="h-full rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 pt-2">
                    <div class="bg-gray-50 p-3 rounded-2xl text-center border border-gray-100 shadow-sm">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Delivered</p>
                        <p id="d-del" class="text-base font-extrabold text-gray-900"></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-2xl text-center border border-gray-100 shadow-sm">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Failed</p>
                        <p id="d-fail" class="text-base font-extrabold text-red-600"></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-2xl text-center border border-gray-100 shadow-sm">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Avg per day</p>
                        <p id="d-avg" class="text-xs font-bold text-gray-900 mt-1"></p>
                    </div>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Recent Runs -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Recent Runs</h3>
                <div id="d-recent" class="space-y-3"></div>
            </section>
        </div>

        <!-- Action Footer (Sticky) -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
            <a id="d-assign-btn" href="/admin-assignments" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all">
                <i class="ti ti-map-pin text-lg"></i>
                Assign Order
            </a>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="openEditDriverModal()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-edit text-base"></i>
                    Edit Profile
                </button>
                <button id="d-deactivate" onclick="toggleDriverStatus()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all">
                    <i class="ti ti-ban text-base"></i>
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Personnel Modal -->
<div id="driverModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 items-center justify-center p-4 hidden">
    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full">
        <h2 id="modalTitle" class="text-xl font-bold text-gray-900 mb-6">Add New Personnel</h2>
        
        <form method="POST" id="driverForm" data-turbo="false">
            <input type="hidden" name="action" id="driverActionInput" value="add_driver">
            <input type="hidden" name="driver_id" id="driverIdInput" value="">
            
            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Driver Full Name *</label>
                    <input type="text" name="name" id="driverName" required placeholder="e.g. Nuwan Karunaratne" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Email Address *</label>
                        <input type="email" name="email" id="driverEmail" required placeholder="e.g. nuwan@kesara.lk" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Password</label>
                        <input type="password" name="password" id="driverPassword" placeholder="Password (default: driver123)" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Phone Number *</label>
                        <input type="text" name="phone" id="driverPhone" required placeholder="e.g. +94 77 111 2222" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">NIC / Identity No *</label>
                        <input type="text" name="nic" id="driverNIC" required placeholder="e.g. 199012345678" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Licence Class</label>
                        <input type="text" name="licence_class" id="driverLicenceClass" value="B" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Licence Expiry</label>
                        <input type="date" name="licence_expiry" id="driverLicenceExpiry" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Vehicle Type</label>
                        <select name="vehicle_type" id="driverVehicleType" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none cursor-pointer">
                            <option value="motorbike">Motorbike</option>
                            <option value="van">Van</option>
                            <option value="lorry">Lorry</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Vehicle Number</label>
                        <input type="text" name="vehicle_number" id="driverVehicleNumber" placeholder="e.g. WP CAB-1234" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Status</label>
                    <select name="status" id="driverStatus" class="w-full px-4 py-2 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none cursor-pointer">
                        <option value="available">Available</option>
                        <option value="on_run">On run</option>
                        <option value="day_off">Day off</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeDriverModal()" class="px-6 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20">Save Personnel</button>
            </div>
        </form>
    </div>
</div>

<form id="statusActionForm" method="POST" class="hidden" data-turbo="false">
    <input type="hidden" name="action" value="toggle_driver_status">
    <input type="hidden" name="driver_id" id="toggleDriverIdInput">
</form>

<script>
var currentSelectedDriver = null;

function barColor(w){ return w>=90?'#10b981':w>=75?'#f59e0b':'#ef4444'; }
function barText(w){ return w>=90?'#047857':w>=75?'#b45309':'#b91c1c'; }

var activeFilter = 'All';

function applyFilters() {
    document.querySelectorAll('.driver-row').forEach(r => {
        var status = r.dataset.badgeText;
        var visible = true;
        if (activeFilter !== 'All' && status !== activeFilter) {
            visible = false;
        }
        r.style.display = visible ? '' : 'none';
    });
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
  
  var firstVisible = Array.from(document.querySelectorAll('.driver-row')).find(r => r.style.display !== 'none');
  if (firstVisible) {
      selectRow(firstVisible, false);
  }
}

function selectRow(el, openDrawer = true) {
  if (!el) return;
  document.querySelectorAll('.driver-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
  el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
  el.classList.remove('bg-white', 'border-gray-100');
  
  currentSelectedDriver = {
      id: el.dataset.id,
      name: el.dataset.name,
      email: el.dataset.email,
      phone: el.dataset.phone,
      nic: el.dataset.nic,
      licence_class: el.dataset.licenceClass,
      licence_expiry: el.dataset.licenceExpiry,
      vehicle_type: el.dataset.vehicleType,
      vehicle_number: el.dataset.vehicleNumber,
      status: el.dataset.status
  };

  // Open drawer
  if (openDrawer) {
    var pane = document.getElementById('driver-detail-pane');
    var backdrop = document.getElementById('driver-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
  
  var av = document.getElementById('d-av');
  av.textContent = el.dataset.av;
  av.className = 'w-20 h-20 rounded-3xl flex items-center justify-center text-2xl font-bold border shadow-lg mb-4 ' + el.dataset.avColor;
  
  document.getElementById('d-name').textContent = el.dataset.name;
  document.getElementById('d-phone').textContent = el.dataset.phone;
  
  var badge = document.getElementById('d-badge');
  badge.className = 'mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border ' + el.dataset.badge;
  badge.textContent = el.dataset.badgeText;
  
  document.getElementById('d-nic').textContent = el.dataset.nic;
  document.getElementById('d-vehicle').textContent = el.dataset.vehicle;
  document.getElementById('d-licence').textContent = el.dataset.licence;
  
  // Update assign button URL
  document.getElementById('d-assign-btn').href = '/admin-assignments?driver_id=' + el.dataset.id;
  
  // Toggle Deactivate/Activate button label
  var deactivateBtn = document.getElementById('d-deactivate');
  if (el.dataset.status === 'inactive') {
      deactivateBtn.className = 'flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-emerald-100 rounded-xl text-xs font-bold text-emerald-600 hover:bg-emerald-50 transition-all';
      deactivateBtn.innerHTML = '<i class="ti ti-circle-check text-base"></i> Activate';
  } else {
      deactivateBtn.className = 'flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all';
      deactivateBtn.innerHTML = '<i class="ti ti-ban text-base"></i> Deactivate';
  }

  // Zones as badges
  var zones = [];
  try { zones = JSON.parse(el.dataset.zones || '[]'); } catch (e) {}
  document.getElementById('d-zones').innerHTML = zones.map(z => `
    <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-100 rounded-full text-[10px] font-bold">${z}</span>
  `).join('');
  
  document.getElementById('d-joined').textContent = el.dataset.joined;
  
  // Today's active run
  var todayRun = null;
  try { todayRun = JSON.parse(el.dataset.todayRun || 'null'); } catch (e) {}
  var runDiv = document.getElementById('d-today-run');
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
        <a class="text-brand font-bold cursor-pointer hover:underline flex items-center gap-1" href="/admin-tracking?assignment_id=${todayRun.id}">Track live ↗</a>
      </div>
    `;
  } else {
    runDiv.innerHTML = `<p class="text-xs text-gray-400 font-medium text-center py-2">No active run today</p>`;
  }
  
  // Performance
  var otW = parseInt(el.dataset.otW);
  document.getElementById('d-bar-ot').style.width = otW + '%';
  document.getElementById('d-bar-ot').style.backgroundColor = barColor(otW);
  document.getElementById('d-ot').textContent = el.dataset.ot;
  document.getElementById('d-ot').style.color = barText(otW);
  
  document.getElementById('d-del').textContent = el.dataset.del;
  document.getElementById('d-fail').textContent = el.dataset.fail;
  document.getElementById('d-avg').textContent = el.dataset.avg;
  
  // Recent runs
  var recent = [];
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

function closeDriverDetailPane() {
  var pane = document.getElementById('driver-detail-pane');
  var backdrop = document.getElementById('driver-detail-backdrop');
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

// Add/Edit Modals logic
function openAddDriverModal() {
    document.getElementById('modalTitle').textContent = 'Add New Personnel';
    document.getElementById('driverActionInput').value = 'add_driver';
    document.getElementById('driverIdInput').value = '';
    
    document.getElementById('driverName').value = '';
    document.getElementById('driverEmail').value = '';
    document.getElementById('driverPassword').value = '';
    document.getElementById('driverPhone').value = '';
    document.getElementById('driverNIC').value = '';
    document.getElementById('driverLicenceClass').value = 'B';
    document.getElementById('driverLicenceExpiry').value = '';
    document.getElementById('driverVehicleType').value = 'motorbike';
    document.getElementById('driverVehicleNumber').value = '';
    document.getElementById('driverStatus').value = 'available';

    document.getElementById('driverModal').classList.remove('hidden');
    document.getElementById('driverModal').classList.add('flex');
}

function openEditDriverModal() {
    if (!currentSelectedDriver) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Profile';
    document.getElementById('driverActionInput').value = 'edit_driver';
    document.getElementById('driverIdInput').value = currentSelectedDriver.id;
    
    document.getElementById('driverName').value = currentSelectedDriver.name;
    document.getElementById('driverEmail').value = currentSelectedDriver.email || '';
    document.getElementById('driverPassword').value = '';
    document.getElementById('driverPhone').value = currentSelectedDriver.phone;
    document.getElementById('driverNIC').value = currentSelectedDriver.nic;
    document.getElementById('driverLicenceClass').value = currentSelectedDriver.licence_class;
    document.getElementById('driverLicenceExpiry').value = currentSelectedDriver.licence_expiry;
    document.getElementById('driverVehicleType').value = currentSelectedDriver.vehicle_type;
    document.getElementById('driverVehicleNumber').value = currentSelectedDriver.vehicle_number;
    document.getElementById('driverStatus').value = currentSelectedDriver.status;

    document.getElementById('driverModal').classList.remove('hidden');
    document.getElementById('driverModal').classList.add('flex');
}

function closeDriverModal() {
    document.getElementById('driverModal').classList.add('hidden');
    document.getElementById('driverModal').classList.remove('flex');
}

function toggleDriverStatus() {
    if (currentSelectedDriver) {
        uiConfirm(`Are you sure you want to change the status of ${currentSelectedDriver.name}?`, () => {
            document.getElementById('toggleDriverIdInput').value = currentSelectedDriver.id;
            document.getElementById('statusActionForm').submit();
        });
    }
}

// Initial Render
applyFilters();
var firstRow = document.querySelector('.driver-row');
if (firstRow) {
  setTimeout(() => {
    selectRow(firstRow, false);
    closeDriverDetailPane();
  }, 100);
}
</script>
