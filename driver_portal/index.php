<?php 
/**
 * Driver Portal
 * Standalone interface for delivery personnel.
 * Handles driver authentication, viewing assigned deliveries, updating delivery statuses, and GPS location tracking.
 */
require_once __DIR__ . "/../database/connection.php"; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error_message = "Email and password are required.";
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM delivery_personnel WHERE email = ?");
                    $stmt->execute([$email]);
                    $driver = $stmt->fetch();
                    
                    if ($driver && password_verify($password, $driver['password'])) {
                        $_SESSION['driver_id'] = $driver['id'];
                        $_SESSION['driver_name'] = $driver['name'];
                        $_SESSION['driver_vehicle'] = ucfirst($driver['vehicle_type']) . ' · ' . $driver['vehicle_number'];
                        
                        header("Location: /driver");
                        exit;
                    } else {
                        $error_message = "Invalid email or password.";
                    }
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "No database connection.";
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nic = trim($_POST['nic'] ?? '');
        $licence_class = trim($_POST['licence_class'] ?? 'B');
        $licence_expiry = trim($_POST['licence_expiry'] ?? '');
        $vehicle_type = $_POST['vehicle_type'] ?? 'motorbike';
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($phone) || empty($nic) || empty($vehicle_number) || empty($password) || empty($licence_expiry)) {
            $error_message = "All fields are required.";
        } else {
            if ($pdo) {
                try {
                    $check = $pdo->prepare("SELECT id FROM delivery_personnel WHERE email = ?");
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $error_message = "A driver account with this email already exists.";
                    } else {
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare("INSERT INTO delivery_personnel (name, email, password, phone, nic, licence_class, licence_expiry, vehicle_type, vehicle_number, status, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', CURDATE())");
                        $ins->execute([$name, $email, $hashed, $phone, $nic, $licence_class, $licence_expiry, $vehicle_type, $vehicle_number]);
                        
                        // Send welcome email to driver
                        require_once __DIR__ . "/../src/Mailer.php";
                        $subject = "Welcome to Kesara Delivery Team!";
                        $body = "<h3>Hello " . htmlspecialchars($name) . ",</h3>" .
                                "<p>Your driver registration was successful.</p>" .
                                "<p>You can now log in to the driver portal using your email address and password.</p>";
                        \App\Mailer::send($email, $subject, $body);

                        $success_message = "Registration successful! You can now log in.";
                    }
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "No database connection.";
            }
        }
    } elseif ($_POST['action'] === 'request_leave') {
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $driver_id = $_SESSION['driver_id'] ?? null;
        
        if ($driver_id && !empty($start_date) && !empty($end_date) && !empty($reason)) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO driver_leaves (personnel_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$driver_id, $start_date, $end_date, $reason]);
                    $success_message = "Day off request submitted successfully.";
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "No database connection.";
            }
        } else {
            $error_message = "Both dates and reason are required.";
        }
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['driver_id']);
    unset($_SESSION['driver_name']);
    unset($_SESSION['driver_vehicle']);
    header("Location: /driver");
    exit;
}

$is_logged_in = isset($_SESSION['driver_id']);

$leave_notifications = [];
$php_assignments = [];
if ($is_logged_in && isset($pdo) && $pdo !== null) {
    try {
        $n_stmt = $pdo->prepare("SELECT id, start_date, end_date, status FROM driver_leaves WHERE personnel_id = ? AND status IN ('approved', 'rejected') AND notified = FALSE");
        $n_stmt->execute([$_SESSION['driver_id']]);
        $leave_notifications = $n_stmt->fetchAll();
        
        if (!empty($leave_notifications)) {
            $ids = array_column($leave_notifications, 'id');
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $up_stmt = $pdo->prepare("UPDATE driver_leaves SET notified = TRUE WHERE id IN ($inQuery)");
            $up_stmt->execute($ids);
        }
    } catch (\Exception $e) {}

    try {
        $stmt = $pdo->prepare("SELECT da.id, da.assigned_at, da.status, da.notes,
                                    dp.id AS driver_id, dp.name AS driver_name, dp.vehicle_type, dp.vehicle_number,
                                    o.id AS order_id,
                                    u.business_name AS company, u.address AS company_address
                             FROM delivery_assignments da
                             JOIN delivery_personnel dp ON da.personnel_id = dp.id
                             JOIN orders o ON da.order_id = o.id
                             JOIN users u ON o.user_id = u.id
                             WHERE da.personnel_id = ?
                             ORDER BY da.assigned_at DESC");
        $stmt->execute([$_SESSION['driver_id']]);
        $asgn_db = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($asgn_db as $row) {
            $key = $row['id'];
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
            
            // Map coordinates based on company name
            $hash = crc32($row['company'] ?: 'default');
            $lat = 6.9000 + (($hash % 100) - 50) / 1000.0;
            $lng = 79.8700 + ((intval($hash / 100) % 100) - 50) / 1000.0;
            
            $grouped[$key]['stops'][] = [
                'num' => count($grouped[$key]['stops']) + 1,
                'name' => 'KE-2025-' . str_pad($row['order_id'], 5, '0', STR_PAD_LEFT) . ' · ' . htmlspecialchars($row['company']),
                'addr' => htmlspecialchars($row['company_address']),
                'status' => $status_text,
                'lat' => $lat,
                'lng' => $lng
            ];
        }
        
        foreach ($grouped as $g) {
            $badge = 'bg-amber-50 text-amber-700 border border-amber-100';
            $badgeText = 'Pending';
            if ($g['status'] === 'completed') {
                $badge = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                $badgeText = 'Completed';
            } elseif ($g['status'] === 'in_progress') {
                $badge = 'bg-blue-50 text-blue-700 border border-blue-100';
                $badgeText = 'Active';
            } elseif ($g['status'] === 'failed') {
                $badge = 'bg-red-50 text-red-700 border border-red-100';
                $badgeText = 'Failed';
            }
            
            $words = explode(" ", $g['driver']);
            $av = "";
            foreach ($words as $w) {
                $av .= strtoupper(substr($w, 0, 1));
            }
            $av = substr($av, 0, 2);
            
            $php_assignments[] = [
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
    } catch (\Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dispatch Portal | Kesara Enterprises</title>
    <!-- Tailwind CSS production output -->
    <link href="/dist/output.css" rel="stylesheet">
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="/assets/tabler-icons.min.css">
    
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Geograph', sans-serif;
        }
        /* Custom styles for mobile viewport wrapper */
        .mobile-shell {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            background-color: #ffffff;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-col: true;
            position: relative;
            border-left: 1px solid #f3f4f6;
            border-right: 1px solid #f3f4f6;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

<div class="mobile-shell w-full flex flex-col bg-white">
    
    <!-- Login / Registration Screen -->
    <div id="login-screen" class="flex-1 flex flex-col justify-center p-6 space-y-6">
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-2xl bg-brand flex items-center justify-center mx-auto shadow-lg shadow-brand/20">
                <i class="ti ti-truck-delivery text-3xl text-brand-light"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Driver Dispatch Portal</h1>
            <p class="text-xs text-gray-500" id="auth-subtitle">Sign in to access your daily assignments</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-150 text-red-700 text-xs font-semibold px-4 py-3 rounded-xl">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="bg-emerald-50 border border-emerald-150 text-emerald-700 text-xs font-semibold px-4 py-3 rounded-xl">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="auth-login-form" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Email Address</label>
                <input type="email" name="email" required placeholder="e.g. sunil@kesara.lk" class="w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
            </div>
            <button type="submit" class="w-full bg-brand text-brand-light font-bold py-4 rounded-xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/10 text-xs uppercase tracking-widest mt-2">Log In</button>
            <p class="text-xs text-center text-gray-500 font-semibold mt-3">
                New driver? <a href="#" onclick="toggleAuthMode('register')" class="text-brand hover:underline">Register here</a>
            </p>
        </form>

        <!-- Register Form -->
        <form method="POST" id="auth-register-form" class="space-y-4" style="display: none;">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Full Name</label>
                <input type="text" name="name" required placeholder="e.g. Sunil Perera" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Email Address</label>
                    <input type="email" name="email" required placeholder="e.g. sunil@kesara.lk" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Phone Number</label>
                    <input type="text" name="phone" required placeholder="e.g. +94 77 123 4567" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">NIC / ID No</label>
                    <input type="text" name="nic" required placeholder="e.g. 199012345678" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Licence Class</label>
                    <input type="text" name="licence_class" value="B" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Licence Expiry</label>
                    <input type="date" name="licence_expiry" required class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Vehicle Type</label>
                    <select name="vehicle_type" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-xs font-bold text-gray-705 outline-none cursor-pointer">
                        <option value="motorbike">Motorbike</option>
                        <option value="van">Van</option>
                        <option value="lorry">Lorry</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Vehicle Number</label>
                    <input type="text" name="vehicle_number" required placeholder="e.g. WP CBA-1234" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Password</label>
                <div class="relative">
                    <input type="password" id="driver-register-password" name="password" required maxlength="8" placeholder="Min. 8 characters" class="w-full pl-4 pr-10 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all" oninput="checkDriverPasswordStrength(this.value)">
                    <i class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-base cursor-pointer toggle-password-btn" data-target="driver-register-password"></i>
                </div>
                <!-- Strength bar -->
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div id="driver-strengthBar" class="h-full rounded-full transition-all duration-400 w-0 bg-gray-300"></div>
                </div>
                <p id="driver-strengthLabel" class="text-[10px] mt-1 text-gray-400 font-semibold"></p>
            </div>
            <button type="submit" class="w-full bg-brand text-brand-light font-bold py-4 rounded-xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/10 text-xs uppercase tracking-widest mt-2">Register</button>
            <p class="text-xs text-center text-gray-500 font-semibold mt-3">
                Already registered? <a href="#" onclick="toggleAuthMode('login')" class="text-brand hover:underline">Log in here</a>
            </p>
        </form>

        <p class="text-[10px] text-center text-gray-400">© 2026 Kesara Enterprises. Restricted logistics access.</p>
    </div>

    <!-- Active Portal Screen -->
    <div id="portal-screen" class="flex-1 flex flex-col overflow-hidden" style="display: none;">
        <!-- Header -->
        <header class="bg-gray-900 text-white p-4 sticky top-0 z-20 flex justify-between items-center shadow-md">
            <div class="flex items-center gap-3">
                <div id="driver-avatar" class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center font-bold text-xs text-brand-light">--</div>
                <div>
                    <h2 id="driver-name-title" class="text-xs font-bold leading-none">Driver Name</h2>
                    <p id="driver-vehicle-title" class="text-[10px] text-gray-400 mt-1">Vehicle Info</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openDayOffModal()" class="px-2 py-1 bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold rounded-lg transition-colors border border-white/20">
                    Day Off
                </button>
                <button onclick="logoutDriver()" class="text-gray-400 hover:text-red-400 transition-colors p-1.5" title="Logout">
                    <i class="ti ti-logout text-lg"></i>
                </button>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
            <!-- Active Run Details -->
            <div id="active-run-card" class="bg-white p-4 rounded-2xl border border-gray-150 shadow-sm space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Active Delivery Run</span>
                        <h3 id="active-run-id" class="text-base font-bold text-gray-900 mt-0.5">DA-2025-0312</h3>
                    </div>
                    <span id="active-run-badge" class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border">Active</span>
                </div>
                
                <!-- Progress Line -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-[10px] font-semibold text-gray-500">
                        <span>Delivery progress</span>
                        <span id="active-run-progress">0 / 0 Stops</span>
                    </div>
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div id="active-run-progress-bar" class="h-full bg-brand rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <!-- GPS Simulation Panel -->
                <div class="p-3 bg-brand-light rounded-xl border border-brand/10 space-y-2">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-map-pin-cog text-brand text-lg"></i>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900 leading-none">Simulate Driver GPS</h4>
                                <p id="gps-status-desc" class="text-[9px] text-gray-500 mt-0.5">Stream coordinates to admin map</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="gps-toggle" onchange="toggleGPS()" class="sr-only peer">
                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand"></div>
                        </label>
                    </div>
                    <div id="gps-telemetry-hud" style="display: none;" class="flex items-center justify-between text-[9px] font-mono text-brand font-bold bg-white/70 px-2.5 py-1 rounded-lg border border-brand/5 mt-2">
                        <span>LAT/LNG: <span id="sim-coords-text">Searching GPS...</span></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    </div>
                </div>

                <!-- Simulation Controls Panel (Driver Only) -->
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Simulation Controls</span>
                        <span id="drv-sim-status" class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Stopped</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button id="drv-btn-play" onclick="driverToggleSimulation()" class="flex flex-col items-center justify-center gap-1 p-2 bg-brand text-brand-light rounded-xl hover:opacity-90 transition-all font-bold text-[10px] shadow-md shadow-brand/10">
                            <i class="ti ti-player-play text-sm"></i>
                            <span id="drv-btn-play-text">Start</span>
                        </button>
                        <!-- <button id="drv-btn-speed" onclick="driverToggleSpeed()" class="flex flex-col items-center justify-center gap-1 p-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-all font-bold text-[10px]">
                            <i class="ti ti-bolt text-sm text-amber-500"></i>
                            <span id="drv-speed-label">1x</span>
                        </button> -->
                        <button onclick="driverStepNextStop()" class="flex flex-col items-center justify-center gap-1 p-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-all font-bold text-[10px]">
                            <i class="ti ti-arrow-forward-up text-sm text-blue-500"></i>
                            <span>Skip</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stops List -->
            <div class="space-y-3">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">Stops List</h3>
                <div id="stops-container" class="space-y-3">
                    <!-- Loaded dynamically via JS -->
                </div>
            </div>
        </main>
    </div>

</div>

<!-- Delivery Confirmation Modal (Slider Style for Native feel) -->
<div id="confirm-modal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white w-full max-w-[380px] rounded-t-3xl p-6 space-y-6 transform translate-y-full transition-all duration-300">
        <div class="w-12 h-1 bg-gray-200 rounded-full mx-auto"></div>
        <div class="text-center space-y-1">
            <h3 class="text-lg font-bold text-gray-900">Confirm Delivery</h3>
            <p id="confirm-stop-name" class="text-xs text-gray-500"></p>
        </div>

        <!-- Signature Area -->
        <div class="space-y-1.5">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Customer Signature</label>
            <div id="signature-pad" class="h-32 bg-gray-50 border border-gray-200 border-dashed rounded-2xl flex items-center justify-center text-xs text-gray-400 font-medium relative overflow-hidden select-none cursor-pointer hover:bg-gray-100 transition-colors" onclick="addSignature()">
                <div class="space-y-1 text-center" id="signature-placeholder">
                    <i class="ti ti-signature text-2xl text-gray-300"></i>
                    <p>Signature Pad (Tap to sign)</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="closeConfirmation()" class="px-4 py-3 bg-gray-100 hover:bg-gray-150 rounded-xl text-xs font-bold text-gray-700 transition-all">Cancel</button>
            <button onclick="submitDelivery()" class="px-4 py-3 bg-brand text-brand-light hover:opacity-90 rounded-xl text-xs font-bold transition-all shadow-lg shadow-brand/10">Confirm &amp; Upload</button>
        </div>
    </div>
</div>

<!-- Did Not Deliver Modal (Enhanced) -->
<div id="fail-modal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white w-full max-w-[380px] rounded-t-3xl p-6 space-y-5 transform translate-y-full transition-all duration-300">
        <div class="w-12 h-1 bg-gray-200 rounded-full mx-auto"></div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-red-50 flex items-center justify-center flex-shrink-0">
                <i class="ti ti-truck-off text-xl text-red-500"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Did Not Deliver</h3>
                <p id="fail-stop-name" class="text-xs text-gray-500 mt-0.5"></p>
            </div>
        </div>

        <!-- Quick-select reasons -->
        <div class="space-y-2">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Select Reason</label>
            <div class="grid grid-cols-2 gap-2" id="fail-reason-chips">
                <button onclick="selectFailReason(this, 'Customer warehouse closed')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-building-off block text-base mb-0.5"></i>Warehouse Closed
                </button>
                <button onclick="selectFailReason(this, 'Representative not available')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-user-off block text-base mb-0.5"></i>No Representative
                </button>
                <button onclick="selectFailReason(this, 'Wrong address / location not found')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-map-pin-off block text-base mb-0.5"></i>Wrong Address
                </button>
                <button onclick="selectFailReason(this, 'Customer refused delivery')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-hand-stop block text-base mb-0.5"></i>Customer Refused
                </button>
                <button onclick="selectFailReason(this, 'Damaged goods — rejected')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-package-off block text-base mb-0.5"></i>Damaged Goods
                </button>
                <button onclick="selectFailReason(this, 'Other reason (see notes)')" class="fail-chip px-3 py-2.5 rounded-xl border border-gray-200 text-[10px] font-bold text-gray-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition-all text-left">
                    <i class="ti ti-dots-circle-horizontal block text-base mb-0.5"></i>Other
                </button>
            </div>
        </div>

        <!-- Optional notes -->
        <div class="space-y-1.5">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Additional Notes <span class="text-gray-300">(optional)</span></label>
            <textarea id="fail-notes" rows="2" placeholder="Add any extra details here..." class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-semibold text-gray-800 outline-none focus:border-red-200 resize-none transition-all"></textarea>
        </div>

        <input type="hidden" id="fail-reason" value="">

        <div class="grid grid-cols-2 gap-3">
            <button onclick="closeFailure()" class="px-4 py-3 bg-gray-100 hover:bg-gray-150 rounded-xl text-xs font-bold text-gray-700 transition-all">Cancel</button>
            <button onclick="submitFailure()" style="background-color: #dc2626; color: white;" class="px-4 py-3 hover:opacity-90 rounded-xl text-xs font-bold transition-all shadow-lg shadow-red-100">Confirm Undelivered</button>
        </div>
    </div>
</div>

<!-- Pickup Confirmation Modal -->
<div id="pickup-modal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white w-full max-w-[380px] rounded-t-3xl p-6 space-y-5 transform translate-y-full transition-all duration-300">
        <div class="w-12 h-1 bg-gray-200 rounded-full mx-auto"></div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-brand-light flex items-center justify-center flex-shrink-0">
                <i class="ti ti-package-import text-xl text-brand"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Confirm Pickup</h3>
                <p class="text-xs text-gray-500 mt-0.5">Verify you have collected all items</p>
            </div>
        </div>

        <!-- Order list being picked up -->
        <div class="space-y-1.5">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Orders in this Run</label>
            <div id="pickup-order-list" class="bg-gray-50 rounded-xl divide-y divide-gray-100 max-h-40 overflow-y-auto">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Checklist confirmation -->
        <label class="flex items-start gap-3 cursor-pointer group">
            <input type="checkbox" id="pickup-confirmed-check" class="mt-0.5 w-4 h-4 accent-brand rounded cursor-pointer">
            <span class="text-xs font-semibold text-gray-700 leading-relaxed">I confirm that I have physically collected <strong>all packages</strong> listed above from the warehouse and they are loaded in my vehicle.</span>
        </label>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="closePickupModal()" class="px-4 py-3 bg-gray-100 hover:bg-gray-150 rounded-xl text-xs font-bold text-gray-700 transition-all">Cancel</button>
            <button onclick="confirmPickup()" class="px-4 py-3 bg-brand text-brand-light hover:opacity-90 rounded-xl text-xs font-bold transition-all shadow-lg shadow-brand/10">
                <i class="ti ti-truck-delivery mr-1"></i>Depart Now
            </button>
        </div>
    </div>
</div>

<!-- Day Off Request Modal -->
<div id="day-off-modal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white w-full max-w-[380px] rounded-t-3xl p-6 space-y-5 transform translate-y-full transition-all duration-300">
        <div class="w-12 h-1 bg-gray-200 rounded-full mx-auto"></div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-brand-light flex items-center justify-center flex-shrink-0">
                <i class="ti ti-calendar-off text-xl text-brand"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Request Day Off</h3>
                <p class="text-xs text-gray-500 mt-0.5">Submit your leave application</p>
            </div>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="request_leave">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Date Range</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="start_date" required class="w-full px-3 py-2.5 bg-gray-50 border border-transparent rounded-xl text-[11px] font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                    <span class="text-gray-400 font-bold">-</span>
                    <input type="date" name="end_date" required class="w-full px-3 py-2.5 bg-gray-50 border border-transparent rounded-xl text-[11px] font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Reason</label>
                <textarea name="reason" required rows="3" placeholder="Enter reason for leave..." class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-xs font-bold text-gray-750 outline-none focus:bg-white focus:border-brand/20 resize-none transition-all"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-3 pt-2">
                <button type="button" onclick="closeDayOffModal()" class="px-4 py-3 bg-gray-100 hover:bg-gray-150 rounded-xl text-xs font-bold text-gray-700 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-3 bg-brand text-brand-light hover:opacity-90 rounded-xl text-xs font-bold transition-all shadow-lg shadow-brand/10">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Global Toasts -->
<div id="toast-wrapper" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[2000] w-11/12 max-w-[400px] pointer-events-none transform translate-y-12 opacity-0 transition-all duration-300">
    <div class="bg-gray-900 text-white px-4 py-2.5 rounded-xl shadow-2xl flex items-center gap-2.5 text-xs font-semibold justify-center">
        <i id="toast-icon" class="ti ti-bell"></i>
        <span id="toast-msg">Success</span>
    </div>
</div>

<script>
// Available drivers matching assignments page
<?php
$php_drivers = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT id, name, vehicle_type, vehicle_number FROM delivery_personnel WHERE status != 'inactive'");
        $drivers_db = $stmt->fetchAll();
        
        $color_classes = [
            'bg-emerald-100 text-emerald-700 border-emerald-200',
            'bg-blue-100 text-blue-700 border-blue-200',
            'bg-amber-100 text-amber-700 border-amber-200',
            'bg-indigo-100 text-indigo-700 border-indigo-200',
            'bg-purple-100 text-purple-700 border-purple-200'
        ];
        
        foreach ($drivers_db as $index => $row) {
            $words = explode(" ", $row['name']);
            $initials = "";
            foreach ($words as $w) {
                $initials .= strtoupper(substr($w, 0, 1));
            }
            $initials = substr($initials, 0, 2);
            if (empty($initials)) {
                $initials = 'D' . $row['id'];
            }
            
            $color = $color_classes[$index % count($color_classes)];
            
            $php_drivers[] = [
                'id' => $initials,
                'name' => $row['name'],
                'vehicle' => ucfirst($row['vehicle_type']) . ' · ' . $row['vehicle_number'],
                'avColor' => $color
            ];
        }
    } catch (\Exception $e) {
        // Fallback
    }
}
?>
const DRIVERS = <?php echo !empty($php_drivers) ? json_encode($php_drivers) : '[
  { id: "NK", name: "Nuwan Karunaratne", vehicle: "Motorbike · WP CAB-4421", avColor: "bg-emerald-100 text-emerald-700 border-emerald-200" },
  { id: "PF", name: "Pradeep Fernando", vehicle: "Motorbike · WP CBA-7788", avColor: "bg-blue-100 text-blue-700 border-blue-200" },
  { id: "LW", name: "Lalith Wickrama", vehicle: "Van · CP AAB-3344", avColor: "bg-amber-100 text-amber-700 border-amber-200" },
  { id: "SR", name: "Saman Rajapaksa", vehicle: "Van · WP CBB-1122", avColor: "bg-indigo-100 text-indigo-700 border-indigo-200" }
]'; ?>;

// Warehouse coords
const WAREHOUSES = {
  'Colombo': [6.9535, 79.8886],
  'Gampaha': [7.0873, 80.0144],
  'Kandy': [7.2906, 80.6337],
  'Southern': [6.0329, 80.2170]
};

// Global App State
let activeDriver = <?php echo json_encode($is_logged_in ? $_SESSION['driver_id'] : null); ?>;
const activeDriverName = <?php echo json_encode($is_logged_in ? $_SESSION['driver_name'] : null); ?>;
const activeDriverVehicle = <?php echo json_encode($is_logged_in ? $_SESSION['driver_vehicle'] : null); ?>;
const defaultAssignments = <?php echo json_encode($php_assignments); ?>;
let assignments = [];
let activeRun = null;
let gpsTimer = null;
let currentActiveStopIdx = -1;
let currentModalStopNum = -1;

function toggleAuthMode(mode) {
    const loginForm = document.getElementById('auth-login-form');
    const registerForm = document.getElementById('auth-register-form');
    const subtitle = document.getElementById('auth-subtitle');
    
    if (mode === 'register') {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        subtitle.textContent = 'Register a new driver profile';
    } else {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        subtitle.textContent = 'Sign in to access your daily assignments';
    }
}

function showToast(message, type = 'success') {
    const wrapper = document.getElementById('toast-wrapper');
    const msgEl = document.getElementById('toast-msg');
    const iconEl = document.getElementById('toast-icon');
    
    msgEl.textContent = message;
    iconEl.className = type === 'error' ? 'ti ti-alert-circle text-red-500' : 'ti ti-circle-check text-emerald-500';
    
    wrapper.classList.remove('translate-y-12', 'opacity-0');
    wrapper.classList.add('translate-y-0', 'opacity-100');
    
    setTimeout(() => {
        wrapper.classList.remove('translate-y-0', 'opacity-100');
        wrapper.classList.add('translate-y-12', 'opacity-0');
    }, 3000);
}

// Load assignments from default assignments
function loadState() {
    assignments = [...defaultAssignments];
}

// Log out driver
function logoutDriver() {
    if (gpsTimer) {
        clearInterval(gpsTimer);
        gpsTimer = null;
        document.getElementById('gps-toggle').checked = false;
    }
    window.location.href = '?logout=1';
}

function renderApp() {
    loadState();
    
    const loginScreen = document.getElementById('login-screen');
    const portalScreen = document.getElementById('portal-screen');
    
    if (!activeDriver) {
        loginScreen.style.display = 'flex';
        portalScreen.style.display = 'none';
    } else {
        loginScreen.style.display = 'none';
        portalScreen.style.display = 'flex';
        
        // Find driver details
        const getInitials = (name) => {
            if (!name) return '--';
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        };
        document.getElementById('driver-avatar').textContent = getInitials(activeDriverName);
        document.getElementById('driver-name-title').textContent = activeDriverName || 'Driver';
        document.getElementById('driver-vehicle-title').textContent = activeDriverVehicle || 'Vehicle Info';
        
        // Find active run for this driver
        // Note: Active runs are status "Active" or "Pending"
        activeRun = assignments.find(a => a.badgeText === 'Active' || a.badgeText === 'Pending');
        
        if (!activeRun) {
            // Check if there is a completed run
            activeRun = assignments.find(a => a.badgeText === 'Completed');
        }
        
        renderDashboard();
    }
}



function renderDashboard() {
    const activeRunCard = document.getElementById('active-run-card');
    const stopsContainer = document.getElementById('stops-container');
    
    if (!activeRun) {
        stopsContainer.innerHTML = `
            <div class="bg-white p-8 rounded-2xl border border-gray-150 text-center space-y-4 shadow-sm">
                <i class="ti ti-bike text-5xl text-gray-300"></i>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-gray-800">You are all caught up!</h4>
                    <p class="text-xs text-gray-500">No active runs assigned for you today.</p>
                </div>
            </div>
        `;
        activeRunCard.style.display = 'none';
        return;
    }
    
    activeRunCard.style.display = 'block';
    document.getElementById('active-run-id').textContent = activeRun.id;
    
    const badge = document.getElementById('active-run-badge');
    badge.textContent = activeRun.badgeText;
    if (activeRun.badgeText === 'Completed') {
        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-emerald-50 text-emerald-700 border-emerald-100';
        document.getElementById('gps-toggle').disabled = true;
    } else if (activeRun.badgeText === 'Pending') {
        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-amber-50 text-amber-700 border-amber-100';
        document.getElementById('gps-toggle').disabled = false;
    } else {
        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-blue-50 text-blue-770 border-blue-100';
        document.getElementById('gps-toggle').disabled = false;
    }
    
    const completedStops = activeRun.stops.filter(s => s.status.startsWith('Delivered') || s.status.startsWith('Failed')).length;
    const totalStops = activeRun.stops.length;
    document.getElementById('active-run-progress').textContent = `${completedStops} / ${totalStops} Stops`;
    
    const pct = (completedStops / totalStops) * 100;
    document.getElementById('active-run-progress-bar').style.width = pct + '%';
    
    // Render Stops
    stopsContainer.innerHTML = activeRun.stops.map((s, idx) => {
        let cardBorder = 'border-gray-200';
        let badgeStyle = 'bg-gray-50 text-gray-500 border-gray-100';
        let actionsHtml = '';
        
        if (s.status.startsWith('Delivered')) {
            badgeStyle = 'bg-emerald-50 text-emerald-700 border-emerald-100';
            cardBorder = 'border-emerald-100 bg-emerald-50/10';
        } else if (s.status.startsWith('Failed')) {
            badgeStyle = 'bg-red-50 text-red-700 border-red-100';
            cardBorder = 'border-red-100 bg-red-50/10';
        } else if (s.status === 'In progress') {
            badgeStyle = 'bg-blue-50 text-blue-700 border-blue-100 animate-pulse';
            cardBorder = 'border-blue-200 ring-1 ring-blue-100';
            
            // Actions when en route
            const mapSearchUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(s.addr + ', Sri Lanka')}`;
            actionsHtml = `
                <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-gray-100">
                    <a href="${mapSearchUrl}" target="_blank" class="flex items-center justify-center gap-1.5 py-2.5 bg-white border border-gray-200 hover:bg-gray-55 rounded-xl text-[10px] font-bold text-gray-700 transition-colors shadow-sm">
                        <i class="ti ti-navigation text-base text-brand"></i>
                        <span>Map Nav</span>
                    </a>
                    <button onclick="openConfirmationModal(${s.num})" class="flex items-center justify-center gap-1.5 py-2.5 bg-brand text-brand-light hover:opacity-90 rounded-xl text-[10px] font-bold transition-colors shadow-md shadow-brand/10">
                        <i class="ti ti-checkbox text-base"></i>
                        <span>Deliver</span>
                    </button>
                    <button onclick="openFailureModal(${s.num})" class="flex items-center justify-center gap-1.5 py-2.5 bg-white border border-red-100 hover:bg-red-50/50 rounded-xl text-[10px] font-bold text-red-600 transition-colors">
                        <i class="ti ti-ban text-base"></i>
                        <span>Fail</span>
                    </button>
                </div>
            `;
        } else {
            // Not started / Pending
            // Only enable "Depart" button on the first incomplete stop
            const firstIncomplete = activeRun.stops.find(stop => stop.status === 'Not started' || stop.status === 'In progress');
            const isNextStop = firstIncomplete && firstIncomplete.num === s.num;
            
            if (isNextStop && activeRun.badgeText !== 'Completed') {
                if (activeRun.badgeText === 'Pending') {
                    actionsHtml = `
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <button onclick="openPickupModal(${s.num})" class="w-full flex items-center justify-center gap-2 py-2.5 bg-brand text-brand-light hover:opacity-90 rounded-xl text-[10px] font-bold transition-colors shadow-md shadow-brand/10">
                                <i class="ti ti-package-import text-base"></i>
                                Confirm Pickup & Depart
                            </button>
                        </div>
                    `;
                } else {
                    actionsHtml = `
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <button onclick="departStop(${s.num})" class="w-full flex items-center justify-center gap-2 py-2.5 bg-brand text-brand-light hover:opacity-90 rounded-xl text-[10px] font-bold transition-colors shadow-md shadow-brand/10">
                                <i class="ti ti-truck-delivery text-base"></i>
                                Depart towards Stop ${s.num}
                            </button>
                        </div>
                    `;
                }
            }
        }
        
        return `
            <div class="bg-white p-4 rounded-2xl border ${cardBorder} shadow-sm space-y-2">
                <div class="flex justify-between items-start">
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-700 mt-0.5">${s.num}</div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900">${s.name}</h4>
                            <p class="text-[11px] text-gray-500 mt-0.5">${s.addr}</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border ${badgeStyle}">${s.status}</span>
                </div>
                ${actionsHtml}
            </div>
        `;
    }).join('');
    
    // Sync HUD coordinates
    if (activeRun.sim_coords) {
        document.getElementById('sim-coords-text').textContent = `${activeRun.sim_coords[0].toFixed(5)}, ${activeRun.sim_coords[1].toFixed(5)}`;
    }
}

// Extract numeric order ID from stop name "KE-2025-00123 · Company"
function extractOrderId(stopName) {
    const match = stopName.match(/KE-\d{4}-(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

// POST order status update to DB via API
function updateOrderStatus(orderId, status) {
    if (!orderId) return;
    fetch('/api/orders.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: orderId, status: status })
    }).catch(() => { /* silently fail — localStorage state stays intact */ });
}

// Pickup Confirmation Modal
let pendingDepartStopNum = null;

function openPickupModal(stopNum) {
    pendingDepartStopNum = stopNum;
    
    // Populate order list
    const listEl = document.getElementById('pickup-order-list');
    if (activeRun && activeRun.stops) {
        listEl.innerHTML = activeRun.stops.map(s => `
            <div class="px-4 py-2.5 flex items-center gap-3">
                <i class="ti ti-package text-brand text-sm flex-shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-gray-800 truncate">${s.name.split(' · ')[0]}</p>
                    <p class="text-[10px] text-gray-400 truncate">${s.name.split(' · ')[1] || ''} · ${s.addr}</p>
                </div>
            </div>
        `).join('');
    }
    
    // Reset checkbox
    document.getElementById('pickup-confirmed-check').checked = false;
    
    const modal = document.getElementById('pickup-modal');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.classList.add('opacity-100');
    const panel = modal.querySelector('div');
    panel.classList.remove('translate-y-full');
    panel.classList.add('translate-y-0');
}

function closePickupModal() {
    const modal = document.getElementById('pickup-modal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.classList.remove('opacity-100');
    const panel = modal.querySelector('div');
    panel.classList.add('translate-y-full');
    panel.classList.remove('translate-y-0');
    pendingDepartStopNum = null;
}

function openDayOffModal() {
    const modal = document.getElementById('day-off-modal');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.classList.add('opacity-100');
    const panel = modal.querySelector('div');
    panel.classList.remove('translate-y-full');
    panel.classList.add('translate-y-0');
}

function closeDayOffModal() {
    const modal = document.getElementById('day-off-modal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.classList.remove('opacity-100');
    const panel = modal.querySelector('div');
    panel.classList.add('translate-y-full');
    panel.classList.remove('translate-y-0');
}

function confirmPickup() {
    const checked = document.getElementById('pickup-confirmed-check').checked;
    if (!checked) {
        showToast('Please confirm you have collected all packages', 'error');
        return;
    }
    closePickupModal();
    departStop(pendingDepartStopNum);
}

// Start moving towards stop
function departStop(stopNum) {
    if (activeRun.badgeText === 'Pending') {
        activeRun.badgeText = 'Active';
        activeRun.badge = 'bg-blue-50 text-blue-770 border-blue-100';
        logDriverTelemetry(activeRun, `Run dispatched from depot.`, 'SYSTEM');
        
        // Mark ALL stops in this run as shipped in DB
        activeRun.stops.forEach(s => {
            const orderId = extractOrderId(s.name);
            updateOrderStatus(orderId, 'shipped');
        });
        showToast('Orders marked as Shipped in system', 'info');
    }
    
    const stop = activeRun.stops.find(s => s.num === stopNum);
    if (!stop) return;
    
    stop.status = 'In progress';
    logDriverTelemetry(activeRun, `En route to Stop ${stop.num}: ${stop.name.split(' · ')[1]}`, 'GPS');
    
    // Save to localStorage
    localStorage.setItem('ke_assignments', JSON.stringify(assignments));
    
    renderApp();
    showToast(`Departed towards Stop ${stopNum}!`, 'success');
}

// Logger helper for telemetry
function logDriverTelemetry(run, message, type = 'INFO') {
    if (!run.telemetry_history) {
        run.telemetry_history = [];
    }
    const timestamp = new Date().toLocaleTimeString([], { hour12: false });
    run.telemetry_history.push(`[${timestamp}] [${type}] ${message}`);
}

// Confirm Delivery Modal Slider Actions
function openConfirmationModal(stopNum) {
    currentModalStopNum = stopNum;
    const stop = activeRun.stops.find(s => s.num === stopNum);
    document.getElementById('confirm-stop-name').textContent = stop ? stop.name : '';
    
    // Signature pad not implemented yet
    
    const modal = document.getElementById('confirm-modal');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.classList.add('opacity-100');
    
    const panel = modal.querySelector('div');
    panel.classList.remove('translate-y-full');
    panel.classList.add('translate-y-0');
}

function addSignature() {
    const pad = document.getElementById('signature-pad');
    if (pad) {
        pad.innerHTML = '<div class="w-full h-full flex items-center justify-center bg-white"><span style="font-family: \'Brush Script MT\', \'Dancing Script\', cursive, sans-serif; font-size: 2.5rem; color: #1f2937; transform: rotate(-5deg); padding: 10px;">Kesara Ent.</span></div>';
        pad.classList.remove('cursor-pointer', 'hover:bg-gray-100', 'border-dashed');
        pad.onclick = null;
    }
}

function closeConfirmation() {
    const modal = document.getElementById('confirm-modal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.classList.remove('opacity-100');
    
    const panel = modal.querySelector('div');
    panel.classList.add('translate-y-full');
    panel.classList.remove('translate-y-0');
}

function submitDelivery() {
    
    const stop = activeRun.stops.find(s => s.num === currentModalStopNum);
    if (stop) {
        const timeStr = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        stop.status = `Delivered ${timeStr}`;
        logDriverTelemetry(activeRun, `Stop ${stop.num} Delivered: Proof of delivery verified.`, 'DELIVERED');
        
        // Update this stop's order to 'delivered' in DB
        const orderId = extractOrderId(stop.name);
        updateOrderStatus(orderId, 'delivered');
        
        // If it was the last stop, mark run as completed!
        const nextIncomplete = activeRun.stops.find(s => s.status === 'Not started' || s.status === 'In progress');
        if (!nextIncomplete) {
            activeRun.badgeText = 'Completed';
            activeRun.badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
            logDriverTelemetry(activeRun, `All deliveries completed. Returning to warehouse.`, 'SYSTEM');
            showToast('Excellent! All stops completed.', 'success');
            
            // Turn off GPS simulator
            if (gpsTimer) {
                clearInterval(gpsTimer);
                gpsTimer = null;
                document.getElementById('gps-toggle').checked = false;
                document.getElementById('gps-telemetry-hud').style.display = 'none';
            }
            
            // Stop simulation ticker if running
            stopDriverSimulation();
        }
        
        localStorage.setItem('ke_assignments', JSON.stringify(assignments));
        closeConfirmation();
        renderApp();
        showToast('Delivery confirmed successfully!', 'success');
    }
}

// Reason chip selection for Did Not Deliver modal
function selectFailReason(btn, reason) {
    // Clear all chips
    document.querySelectorAll('.fail-chip').forEach(c => {
        c.classList.remove('border-red-400', 'bg-red-50', 'text-red-700');
        c.classList.add('border-gray-200', 'text-gray-600');
    });
    // Highlight selected
    btn.classList.add('border-red-400', 'bg-red-50', 'text-red-700');
    btn.classList.remove('border-gray-200', 'text-gray-600');
    document.getElementById('fail-reason').value = reason;
}

// Failure Modal Actions
function openFailureModal(stopNum) {
    currentModalStopNum = stopNum;
    const stop = activeRun.stops.find(s => s.num === stopNum);
    document.getElementById('fail-stop-name').textContent = stop ? stop.name : '';
    
    // Reset reason state
    document.getElementById('fail-reason').value = '';
    document.getElementById('fail-notes').value = '';
    document.querySelectorAll('.fail-chip').forEach(c => {
        c.classList.remove('border-red-400', 'bg-red-50', 'text-red-700');
        c.classList.add('border-gray-200', 'text-gray-600');
    });
    
    const modal = document.getElementById('fail-modal');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.classList.add('opacity-100');
    
    const panel = modal.querySelector('div');
    panel.classList.remove('translate-y-full');
    panel.classList.add('translate-y-0');
}

function closeFailure() {
    const modal = document.getElementById('fail-modal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.classList.remove('opacity-100');
    
    const panel = modal.querySelector('div');
    panel.classList.add('translate-y-full');
    panel.classList.remove('translate-y-0');
}

function submitFailure() {
    const reason = document.getElementById('fail-reason').value;
    const notes = document.getElementById('fail-notes').value.trim();
    
    if (!reason) {
        showToast('Please select a reason', 'error');
        return;
    }
    
    const stop = activeRun.stops.find(s => s.num === currentModalStopNum);
    if (stop) {
        const fullReason = notes ? `${reason} — ${notes}` : reason;
        stop.status = `Failed: ${fullReason}`;
        logDriverTelemetry(activeRun, `Stop ${stop.num} Not Delivered: ${fullReason}`, 'WARN');
        
        // Update order status to failed in DB
        const orderId = extractOrderId(stop.name);
        updateOrderStatus(orderId, 'failed');
        
        // Check if run completed (even with failures)
        const nextIncomplete = activeRun.stops.find(s => s.status === 'Not started' || s.status === 'In progress');
        if (!nextIncomplete) {
            activeRun.badgeText = 'Completed';
            activeRun.badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
            logDriverTelemetry(activeRun, `Run complete with issues. Returning to warehouse.`, 'SYSTEM');
            stopDriverSimulation();
        }
        
        localStorage.setItem('ke_assignments', JSON.stringify(assignments));
        closeFailure();
        renderApp();
        showToast('Non-delivery recorded', 'error');
    }
}

// ============================================================
// DRIVER SIMULATION CONTROLS
// ============================================================
let driverSimInterval = null;
let driverSimSpeed = 1; // 1x, 2x, 5x
let driverIsSimulating = false;

function driverToggleSimulation() {
    if (!activeRun) return;
    if (activeRun.badgeText === 'Completed') {
        showToast('This run is already completed.', 'error');
        return;
    }

    if (activeRun.badgeText === 'Pending') {
        // Kick off the run: mark Active, set first stop In Progress
        activeRun.badgeText = 'Active';
        activeRun.badge = 'bg-blue-50 text-blue-770 border-blue-100';
        activeRun.stops[0].status = 'In progress';
        logDriverTelemetry(activeRun, `Run ${activeRun.id} started by driver. En route to Stop 1.`, 'SYSTEM');

        // Mark ALL orders in this run as shipped
        activeRun.stops.forEach(s => {
            const orderId = extractOrderId(s.name);
            updateOrderStatus(orderId, 'shipped');
        });
        showToast('Run started — orders marked as Shipped', 'success');

        localStorage.setItem('ke_assignments', JSON.stringify(assignments));
        renderApp();
    }

    if (driverIsSimulating) {
        stopDriverSimulation();
        showToast('Simulation paused', 'info');
    } else {
        driverIsSimulating = true;
        document.getElementById('drv-sim-status').textContent = 'Live';
        document.getElementById('drv-sim-status').className = 'text-[10px] font-bold uppercase tracking-wider text-emerald-600';
        document.getElementById('drv-btn-play-text').textContent = 'Pause';
        document.querySelector('#drv-btn-play i').className = 'ti ti-player-pause text-sm';
        startDriverSimInterval();
        showToast('Simulation running', 'success');
    }
}

function stopDriverSimulation() {
    driverIsSimulating = false;
    if (driverSimInterval) { clearInterval(driverSimInterval); driverSimInterval = null; }
    const statusEl = document.getElementById('drv-sim-status');
    if (statusEl) {
        statusEl.textContent = 'Stopped';
        statusEl.className = 'text-[10px] font-bold uppercase tracking-wider text-amber-600';
    }
    const playTextEl = document.getElementById('drv-btn-play-text');
    if (playTextEl) playTextEl.textContent = 'Start';
    const playIconEl = document.querySelector('#drv-btn-play i');
    if (playIconEl) playIconEl.className = 'ti ti-player-play text-sm';
}

function startDriverSimInterval() {
    if (driverSimInterval) clearInterval(driverSimInterval);
    const ms = Math.max(1500 / driverSimSpeed, 300);
    driverSimInterval = setInterval(driverSimulateTick, ms);
}

function driverSimulateTick() {
    if (!activeRun) return;
    const inProgressStop = activeRun.stops.find(s => s.status === 'In progress');
    if (!inProgressStop) {
        // All stops done — complete the run
        stopDriverSimulation();
        activeRun.badgeText = 'Completed';
        activeRun.badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
        logDriverTelemetry(activeRun, 'All stops completed. Returning to warehouse.', 'SYSTEM');
        showToast('Run completed! All orders delivered.', 'success');
        localStorage.setItem('ke_assignments', JSON.stringify(assignments));
        renderApp();
        return;
    }

    // Arrived at current stop — wait for driver action
    stopDriverSimulation();
    showToast(`Arrived at Stop ${inProgressStop.num}. Awaiting delivery confirmation.`, 'info');
    logDriverTelemetry(activeRun, `Arrived at Stop ${inProgressStop.num}. Awaiting signature.`, 'SYSTEM');

    localStorage.setItem('ke_assignments', JSON.stringify(assignments));
    renderApp();
}

function driverToggleSpeed() {
    if (driverSimSpeed === 1) {
        driverSimSpeed = 2;
        document.getElementById('drv-speed-label').textContent = '2x';
    } else if (driverSimSpeed === 2) {
        driverSimSpeed = 5;
        document.getElementById('drv-speed-label').textContent = '5x';
    } else {
        driverSimSpeed = 1;
        document.getElementById('drv-speed-label').textContent = '1x';
    }
    if (driverIsSimulating) {
        clearInterval(driverSimInterval);
        startDriverSimInterval();
    }
    showToast(`Speed set to ${driverSimSpeed}x`, 'info');
}

function driverStepNextStop() {
    if (!activeRun) return;
    if (activeRun.badgeText === 'Pending') { driverToggleSimulation(); return; }
    if (activeRun.badgeText === 'Completed') { showToast('Already completed', 'error'); return; }
    driverSimulateTick();
}

// GPS Simulation Ticker
function toggleGPS() {
    const checked = document.getElementById('gps-toggle').checked;
    const hud = document.getElementById('gps-telemetry-hud');
    
    if (checked) {
        hud.style.display = 'flex';
        startGPSSimulation();
        showToast('GPS Telemetry Started', 'info');
    } else {
        hud.style.display = 'none';
        stopGPSSimulation();
        showToast('GPS Telemetry Stopped', 'info');
    }
}

function getWarehouseCoords(zone) {
    return WAREHOUSES[zone] || WAREHOUSES['Colombo'];
}

function startGPSSimulation() {
    if (gpsTimer) clearInterval(gpsTimer);
    
    gpsTimer = setInterval(() => {
        if (!activeRun || activeRun.badgeText !== 'Active') return;
        
        const activeStop = activeRun.stops.find(s => s.status === 'In progress');
        if (!activeStop) {
            document.getElementById('sim-coords-text').textContent = 'Awaiting active leg...';
            return;
        }
        
        // Fallback coordinates if missing
        if (!activeStop.lat || !activeStop.lng) {
            const wh = getWarehouseCoords(activeRun.zone);
            activeStop.lat = wh[0] + (Math.random() - 0.5) * 0.04;
            activeStop.lng = wh[1] + (Math.random() - 0.5) * 0.04;
        }
        
        let currentPos = activeRun.sim_coords;
        if (!currentPos) {
            // Locate starting point of current leg
            const stopIdx = activeRun.stops.findIndex(s => s.num === activeStop.num);
            if (stopIdx > 0) {
                const prev = activeRun.stops[stopIdx - 1];
                currentPos = [prev.lat, prev.lng];
            } else {
                currentPos = getWarehouseCoords(activeRun.zone);
            }
        }
        
        const targetPos = [activeStop.lat, activeStop.lng];
        
        let latDiff = targetPos[0] - currentPos[0];
        let lngDiff = targetPos[1] - currentPos[1];
        let distance = Math.sqrt(latDiff * latDiff + lngDiff * lngDiff);
        
        if (distance < 0.0008) {
            // Reached stop!
            activeRun.sim_coords = targetPos;
            document.getElementById('sim-coords-text').textContent = `${targetPos[0].toFixed(5)}, ${targetPos[1].toFixed(5)} (Arrived)`;
            showToast(`Arrived at Stop ${activeStop.num}!`, 'info');
            
            // Save to localStorage
            localStorage.setItem('ke_assignments', JSON.stringify(assignments));
            renderDashboard();
            return;
        }
        
        // Take a small step towards target
        const speed = 0.0015; // movement speed
        const nextLat = currentPos[0] + (latDiff / distance) * speed;
        const nextLng = currentPos[1] + (lngDiff / distance) * speed;
        
        activeRun.sim_coords = [nextLat, nextLng];
        document.getElementById('sim-coords-text').textContent = `${nextLat.toFixed(5)}, ${nextLng.toFixed(5)}`;
        
        // Save to localStorage
        localStorage.setItem('ke_assignments', JSON.stringify(assignments));
    }, 1000);
}

function stopGPSSimulation() {
    if (gpsTimer) {
        clearInterval(gpsTimer);
        gpsTimer = null;
    }
}

// Window local storage change synchronization
window.addEventListener('storage', (e) => {
    if (e.key === 'ke_assignments') {
        loadState();
        if (activeDriver) {
            activeRun = assignments.find(a => a.av === activeDriver && (a.badgeText === 'Active' || a.badgeText === 'Pending'));
            if (!activeRun) {
                activeRun = assignments.find(a => a.av === activeDriver && a.badgeText === 'Completed');
            }
        }
        renderDashboard();
    }
});

// Password Visibility Toggle for Registration
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('toggle-password-btn')) {
        const targetId = e.target.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                e.target.classList.remove('ti-eye');
                e.target.classList.add('ti-eye-off');
            } else {
                input.type = 'password';
                e.target.classList.remove('ti-eye-off');
                e.target.classList.add('ti-eye');
            }
        }
    }
});

// Password Strength Checker for Driver Registration
window.checkDriverPasswordStrength = function(password) {
    const bar = document.getElementById('driver-strengthBar');
    const label = document.getElementById('driver-strengthLabel');
    if (!bar || !label) return;

    if (!password) {
        bar.className = "h-full rounded-full transition-all duration-400 w-0 bg-gray-300";
        bar.style.width = "0%";
        label.textContent = "";
        return;
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    let width = "0%";
    let colorClass = "bg-gray-300";
    let text = "";

    if (password.length < 8) {
        width = "20%";
        colorClass = "bg-red-500";
        text = "Too Short (Min. 8 characters)";
    } else {
        if (score <= 2) {
            width = "40%";
            colorClass = "bg-orange-500";
            text = "Weak";
        } else if (score === 3 || score === 4) {
            width = "70%";
            colorClass = "bg-yellow-500";
            text = "Medium";
        } else if (score >= 5) {
            width = "100%";
            colorClass = "bg-green-500";
            text = "Strong";
        }
    }

    bar.style.width = width;
    bar.className = "h-full rounded-full transition-all duration-400 " + colorClass;
    label.textContent = text;
    
    if (colorClass === 'bg-red-500') {
        label.className = "text-[10px] mt-1 text-red-500 font-semibold";
    } else if (colorClass === 'bg-orange-500') {
        label.className = "text-[10px] mt-1 text-orange-500 font-semibold";
    } else if (colorClass === 'bg-yellow-500') {
        label.className = "text-[10px] mt-1 text-yellow-650 font-semibold";
    } else if (colorClass === 'bg-green-500') {
        label.className = "text-[10px] mt-1 text-green-600 font-semibold";
    }
};

// Init on Load
document.addEventListener('DOMContentLoaded', () => {
    renderApp();
<?php if (!empty($leave_notifications)): ?>
        <?php foreach ($leave_notifications as $notif): ?>
            <?php 
            $msg = "Your leave request for {$notif['start_date']} to {$notif['end_date']} has been " . $notif['status'] . "."; 
            $type = $notif['status'] === 'approved' ? 'success' : 'error';
            ?>
            setTimeout(() => showToast(<?= json_encode($msg) ?>, <?= json_encode($type) ?>), 1500);
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>
</body>
</html>
