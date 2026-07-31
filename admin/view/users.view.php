<?php
/**
 * Staff User Management View (Admin Only)
 * Natural Language Overview:
 * 1. Fetching Data -> Getting active staff managers directory (System Admin, Finance, Delivery, Supplier) from the database.
 * 2. Self-Healing -> Ensuring deleted_at column and role ENUM values exist in the admins table.
 * 3. Processing -> Handling staff registration (password hashing with bcrypt) and soft deleting staff users to Recycle Bin.
 */

$success_message = "";
$error_message = "";

// Processing -> Handle POST form submission to register a new user/manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_user') {
    // Sanitize and read input fields
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    // Basic required fields validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        if (isset($pdo) && $pdo !== null) {
            try {
                // Fetching -> Check if the provided email or username already exists to prevent duplicates
                $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? OR username = ?");
                $check_stmt->execute([$email, $username]);
                if ($check_stmt->fetch()) {
                    $error_message = "A user with this email or username already exists.";
                } else {
                    // Hash the password securely using bcrypt before storing it
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    // Insert the new manager into the admins table
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

// Processing -> Handle POST request to soft-delete a staff user to Recycle Bin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $delete_id = intval($_POST['user_id'] ?? 0);
    if ($delete_id > 0 && isset($pdo) && $pdo !== null) {
        try {
            $del_stmt = $pdo->prepare("UPDATE admins SET deleted_at = NOW() WHERE id = ?");
            $del_stmt->execute([$delete_id]);
            $success_message = "Staff user moved to Recycle Bin successfully!";
        } catch (\Exception $e) {
            $error_message = "Error deleting staff user: " . $e->getMessage();
        }
    }
}

// Fetching -> Fetch all active staff users from the database for the directory table
$staff_users = [];
if (isset($pdo) && $pdo !== null) {
    try {
        // Self-heal: ensure deleted_at column exists on admins
        $chk = $pdo->query("SHOW COLUMNS FROM admins LIKE 'deleted_at'");
        if (!$chk->fetch())
            $pdo->exec("ALTER TABLE admins ADD COLUMN deleted_at DATETIME DEFAULT NULL");

        // Self-heal: ensure role ENUM contains example_manager
        $r_chk = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
        $r_col = $r_chk ? $r_chk->fetch() : null;
        if ($r_col && isset($r_col['Type']) && strpos($r_col['Type'], 'example_manager') === false) {
            $pdo->exec("ALTER TABLE admins MODIFY COLUMN role ENUM('admin', 'finance_manager', 'supplier_manager', 'delivery_manager', 'example_manager') DEFAULT 'admin'");
        }

        // Fetching -> Retrieve all staff users who are not deleted
        $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM admins WHERE deleted_at IS NULL ORDER BY created_at DESC");
        $staff_users = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}

// Show empty list if no staff found in DB
if (empty($staff_users)) {
    $staff_users = [];
}

// Processing -> Role label & badge class helper function
function getRoleMeta($role)
{
    $meta = [
        'admin' => ['label' => 'System Admin', 'class' => 'bg-red-50 text-red-700 border-red-100'],
        'finance_manager' => ['label' => 'Finance Manager', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
        'supplier_manager' => ['label' => 'Supplier Manager', 'class' => 'bg-blue-50 text-blue-700 border-blue-100'],
        'delivery_manager' => ['label' => 'Delivery Manager', 'class' => 'bg-amber-50 text-amber-700 border-amber-100'],
        'example_manager' => ['label' => 'Example Manager', 'class' => 'bg-amber-50 text-amber-700 border-amber-100']
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
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?= json_encode($success_message) ?>, 'success');
            });
        </script>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-8 flex items-center gap-3 p-4 bg-red-50 border border-red-100 rounded-2xl text-red-650">
            <i class="ti ti-alert-triangle text-xl shrink-0"></i>
            <p class="text-xs font-bold"><?= htmlspecialchars($error_message) ?></p>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?= json_encode($error_message) ?>, 'error');
            });
        </script>
    <?php endif; ?>

    <div class="grid lg:grid-cols-[380px_1fr] gap-10 items-start">
        <!-- LEFT COLUMN: REGISTRATION FORM -->
        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm space-y-6">
            <div>
                <h2 class="text-base font-bold text-gray-900">Register Staff User</h2>
                <p class="text-[11px] text-gray-400 font-medium mt-1">Create access credentials for managers.</p>
            </div>

            <form action="/admin-users" method="POST" class="space-y-4" onsubmit="handleRegisterUser(event)">
                <input type="hidden" name="action" value="register_user">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="username"
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Username</label>
                        <input type="text" id="username" name="username" required placeholder="e.g. nimal"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label for="email"
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Email
                            Address</label>
                        <input type="email" id="email" name="email" required
                            pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                            placeholder="e.g. nimal@kesara.lk"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
                    </div>

                    <div class="space-y-1.5">
                        <label for="password"
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Default
                            Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required maxlength="12"
                                placeholder="Min. 8 characters"
                                class="w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all"
                                oninput="checkStaffPasswordStrength(this.value)">
                            <i class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg cursor-pointer toggle-password-btn"
                                onclick="toggleStaffPassword(this, 'password')"></i>
                        </div>
                        <!-- Strength bar -->
                        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div id="staff-strengthBar"
                                class="h-full rounded-full transition-all duration-400 w-0 bg-gray-300"></div>
                        </div>
                        <p id="staff-strengthLabel" class="text-[10px] mt-1 text-gray-400 font-semibold"></p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="role"
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block ml-1">Staff
                            Role</label>
                        <select id="role" name="role" required
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all cursor-pointer">
                            <option value="admin">System Admin</option>
                            <option value="finance_manager">Finance Manager</option>
                            <option value="supplier_manager">Supplier Manager</option>
                            <option value="delivery_manager">Delivery Manager</option>
                            <!-- <option value="example_manager">Example Manager</option> -->
                        </select>
                    </div>
                </div>

                <button type="submit" id="register-manager-btn"
                    class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2 mt-6">
                    <i class="ti ti-user-plus text-lg"></i>
                    <span>Register Manager</span>
                </button>
            </form>
        </div>

        <!-- RIGHT COLUMN: DIRECTORY TABLE -->
        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-tight">Active Staff Directory</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="pb-4">Username</th>
                            <th class="pb-4">Email</th>
                            <th class="pb-4">Assigned Role</th>
                            <th class="pb-4">Joined Date</th>
                            <th class="pb-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($staff_users as $su): ?>
                            <?php
                            $meta = getRoleMeta($su['role']);
                            ?>
                            <tr class="group hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 font-bold text-gray-900 text-sm"><?= htmlspecialchars($su['username']) ?>
                                </td>
                                <td class="py-4 text-gray-500 text-xs"><?= htmlspecialchars($su['email']) ?></td>
                                <td class="py-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $meta['class'] ?>">
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-400 text-xs font-semibold">
                                    <?= date('d M Y', strtotime($su['created_at'])) ?></td>
                                <td class="py-4 text-right">
                                    <button type="button" onclick="openDeleteUserModal(<?= $su['id'] ?>, '<?= htmlspecialchars($su['username'], ENT_QUOTES) ?>')" title="Delete Staff User" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-xl transition-all">
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-user-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity duration-200 hidden">
    <div id="delete-user-modal-content" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-sm w-full text-center flex flex-col items-center transform scale-95 opacity-0 transition-all duration-200">
        <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-6">
            <i class="ti ti-alert-triangle text-3xl"></i>
        </div>
        <h3 class="text-xl font-extrabold text-gray-900 mb-2">Delete Staff User?</h3>
        <p class="text-sm font-medium text-gray-500 mb-8">
            Are you sure you want to delete <span id="delete-user-name" class="font-bold text-gray-900"></span>? It will be moved to the Recycle Bin.
        </p>
        <form onsubmit="event.preventDefault(); submitDeleteUser();" class="flex gap-3 w-full">
            <input type="hidden" id="delete-user-id" value="">
            <button type="button" onclick="closeDeleteUserModal()" class="flex-1 bg-gray-50 text-gray-700 font-bold py-3.5 rounded-2xl hover:bg-gray-100 transition-colors">Cancel</button>
            <button type="submit" id="delete-confirm-btn" class="flex-1 bg-red-500 text-white font-bold py-3.5 rounded-2xl hover:bg-red-600 shadow-lg shadow-red-500/20 transition-all transform hover:-translate-y-px flex items-center justify-center gap-2">
                <span>Yes, Delete</span>
            </button>
        </form>
    </div>
</div>

<script>
    // API Registration -> Submitting new staff user registration form via AJAX
    function handleRegisterUser(e) {
        e.preventDefault();
        var form = e.target;
        var btn = document.getElementById('register-manager-btn');

        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<i class="ti ti-loader animate-spin text-lg"></i> <span>Registering...</span>`;
        }

        var formData = new FormData(form);

        fetch('/admin-users', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.text(); })
        .then(function(html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var errDiv = doc.querySelector('.bg-red-50 p');
            
            if (errDiv && errDiv.textContent) {
                if (typeof showToast === 'function') {
                    showToast(errDiv.textContent.trim(), 'error');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btn.innerHTML = `<i class="ti ti-user-plus text-lg"></i> <span>Register Manager</span>`;
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('Staff user registered successfully!', 'success');
                }
                setTimeout(function() {
                    window.location.reload();
                }, 800);
            }
        })
        .catch(function() {
            if (typeof showToast === 'function') {
                showToast('Error registering staff user.', 'error');
            }
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = `<i class="ti ti-user-plus text-lg"></i> <span>Register Manager</span>`;
            }
        });
    }

    // Modal -> Opening staff user deletion confirmation popup
    function openDeleteUserModal(id, username) {
        document.getElementById('delete-user-id').value = id;
        document.getElementById('delete-user-name').textContent = username;
        var modal = document.getElementById('delete-user-modal');
        var content = document.getElementById('delete-user-modal-content');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        setTimeout(function() {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    // Modal -> Closing staff user deletion confirmation popup
    function closeDeleteUserModal() {
        var modal = document.getElementById('delete-user-modal');
        var content = document.getElementById('delete-user-modal-content');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(function() {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }, 200);
    }

    // API Deletion -> Submitting soft-delete request for staff user to Recycle Bin via AJAX
    function submitDeleteUser() {
        var userId = document.getElementById('delete-user-id').value;
        var btn = document.getElementById('delete-confirm-btn');
        if (!userId) return;

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="ti ti-loader animate-spin text-lg"></i> <span>Deleting...</span>`;
        }

        var formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);

        fetch('/admin-users', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.text(); })
        .then(function() {
            closeDeleteUserModal();
            if (typeof showToast === 'function') {
                showToast('Staff user moved to Recycle Bin successfully!', 'success');
            }
            setTimeout(function() {
                window.location.reload();
            }, 800);
        })
        .catch(function() {
            if (typeof showToast === 'function') {
                showToast('Error deleting staff user.', 'error');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span>Yes, Delete</span>';
            }
        });
    }

    // Password Visibility Toggle -> Toggling password text masking with eye icon
    function toggleStaffPassword(btn, targetId) {
        var input = document.getElementById(targetId);
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                btn.classList.remove('ti-eye');
                btn.classList.add('ti-eye-off');
            } else {
                input.type = 'password';
                btn.classList.remove('ti-eye-off');
                btn.classList.add('ti-eye');
            }
        }
    }

    // Password Strength Checker -> Dynamically evaluating password security score (Low, Medium, Strong)
    window.checkStaffPasswordStrength = function (password) {
        var bar = document.getElementById('staff-strengthBar');
        var label = document.getElementById('staff-strengthLabel');
        if (!bar || !label) return;

        if (!password) {
            bar.className = "h-full rounded-full transition-all duration-400 w-0 bg-gray-300";
            bar.style.width = "0%";
            label.textContent = "";
            return;
        }

        var hasUpper = /[A-Z]/.test(password);
        var hasLower = /[a-z]/.test(password);
        var hasNum = /[0-9]/.test(password);
        var hasSpecial = /[^A-Za-z0-9]/.test(password);

        var width = "30%";
        var colorClass = "bg-red-500";
        var text = "Low";
        var textClass = "text-[10px] mt-1 text-red-500 font-semibold";

        if (hasUpper && hasLower && hasNum && hasSpecial) {
            width = "100%";
            colorClass = "bg-green-500";
            text = "Strong";
            textClass = "text-[10px] mt-1 text-green-600 font-semibold";
        } else if (hasUpper && hasLower && hasNum) {
            width = "65%";
            colorClass = "bg-yellow-500";
            text = "Medium";
            textClass = "text-[10px] mt-1 text-yellow-600 font-semibold";
        }

        bar.style.width = width;
        bar.className = "h-full rounded-full transition-all duration-400 " + colorClass;
        label.textContent = text;
        label.className = textClass;
    };
</script>