<?php
/**
 * Staff User Management View (Admin Only)
 */

$success_message = "";
$error_message = "";

// Handle POST form submission to register a new user/manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_user') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        if (isset($pdo) && $pdo !== null) {
            try {
                // Check if email or username already exists
                $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? OR username = ?");
                $check_stmt->execute([$email, $username]);
                if ($check_stmt->fetch()) {
                    $error_message = "A user with this email or username already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $insert_stmt = $pdo->prepare("INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)");
                    $insert_stmt->execute([$username, $hashed_password, $email, $role]);
                    $success_message = "Staff user registered successfully!";
                }
            } catch (\Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        } else {
            $error_message = "Database connection is offline. Cannot register user.";
        }
    }
}

// Fetch all staff users from the database
$staff_users = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM admins ORDER BY created_at DESC");
        $staff_users = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}

// Fallback to seeded / mock staff if database is offline or empty
if (empty($staff_users)) {
    $staff_users = [
        ['id' => 1, 'username' => 'admin', 'email' => 'admin@kesara.lk', 'role' => 'admin', 'created_at' => '2026-06-09 08:00:00'],
        ['id' => 2, 'username' => 'finance', 'email' => 'finance@kesara.lk', 'role' => 'finance_manager', 'created_at' => '2026-06-09 08:00:00'],
        ['id' => 3, 'username' => 'supplier', 'email' => 'supplier@kesara.lk', 'role' => 'supplier_manager', 'created_at' => '2026-06-09 08:00:00'],
        ['id' => 4, 'username' => 'delivery', 'email' => 'delivery@kesara.lk', 'role' => 'delivery_manager', 'created_at' => '2026-06-09 08:00:00']
    ];
}

// Role label & badge class helpers
function getRoleMeta($role) {
    $meta = [
        'admin' => ['label' => 'System Admin', 'class' => 'bg-red-50 text-red-700 border-red-100'],
        'finance_manager' => ['label' => 'Finance Manager', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
        'supplier_manager' => ['label' => 'Supplier Manager', 'class' => 'bg-blue-50 text-blue-700 border-blue-100'],
        'delivery_manager' => ['label' => 'Delivery Manager', 'class' => 'bg-amber-50 text-amber-700 border-amber-100']
    ];
    return $meta[$role] ?? ['label' => ucwords(str_replace('_', ' ', $role)), 'class' => 'bg-gray-50 text-gray-700 border-gray-100'];
}
?>

<div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-8 uppercase tracking-wider">
        <a href="/admin-dashboard" class="hover:text-brand transition-all">Dashboard</a>
        <i class="ti ti-chevron-right text-[10px]"></i>
        <span class="text-gray-900 font-bold">Staff Directory &amp; Roles</span>
    </nav>

    <!-- Header Section -->
    <div class="flex items-center justify-between mb-10 border-b border-gray-150 pb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Staff User Directory</h1>
            <p class="text-xs text-gray-500 mt-1">Register logistics managers and assign administrative roles.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3.5 py-1.5 bg-brand-light text-brand text-xs font-bold rounded-full border border-brand/10">
                Total Staff: <?= count($staff_users) ?>
            </span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-8 flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl text-emerald-700">
            <i class="ti ti-circle-check text-xl shrink-0"></i>
            <p class="text-xs font-bold"><?= htmlspecialchars($success_message) ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-8 flex items-center gap-3 p-4 bg-red-50 border border-red-100 rounded-2xl text-red-650">
            <i class="ti ti-alert-triangle text-xl shrink-0"></i>
            <p class="text-xs font-bold"><?= htmlspecialchars($error_message) ?></p>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-[380px_1fr] gap-10 items-start">
        <!-- LEFT COLUMN: REGISTRATION FORM -->
        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm space-y-6">
            <div>
                <h2 class="text-base font-bold text-gray-900">Register Staff User</h2>
                <p class="text-[11px] text-gray-400 font-medium mt-1">Create access credentials for managers.</p>
            </div>
            
            <form action="/admin-users" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="register_user">
                <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="username" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Username</label>
                    <input type="text" id="username" name="username" required placeholder="e.g. nimal" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
                </div>

                <div class="space-y-1.5">
                    <label for="email" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. nimal@kesara.lk" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Default Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
                </div>

                <div class="space-y-1.5">
                    <label for="role" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Staff Role</label>
                    <select id="role" name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all cursor-pointer">
                        <option value="admin">System Admin</option>
                        <option value="finance_manager">Finance Manager</option>
                        <option value="supplier_manager">Supplier Manager</option>
                        <option value="delivery_manager">Delivery Manager</option>
                    </select>
                </div>
                </div>

                <button type="submit" class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2 mt-6">
                    <i class="ti ti-user-plus text-lg"></i>
                    Register Manager
                </button>
            </form>
        </div>

        <!-- RIGHT COLUMN: DIRECTORY TABLE -->
        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-tight">Active Staff Directory</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="pb-4">Username</th>
                            <th class="pb-4">Email</th>
                            <th class="pb-4">Assigned Role</th>
                            <th class="pb-4">Joined Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($staff_users as $su): ?>
                        <?php 
                            $meta = getRoleMeta($su['role']); 
                        ?>
                        <tr class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 font-bold text-gray-900 text-sm"><?= htmlspecialchars($su['username']) ?></td>
                            <td class="py-4 text-gray-500 text-xs"><?= htmlspecialchars($su['email']) ?></td>
                            <td class="py-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $meta['class'] ?>">
                                    <?= htmlspecialchars($meta['label']) ?>
                                </span>
                            </td>
                            <td class="py-4 text-gray-400 text-xs font-semibold"><?= date('d M Y', strtotime($su['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
