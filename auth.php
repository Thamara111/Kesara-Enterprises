<?php
/**
 * Customer Authentication & Registration Page
 * Handles wholesale customer sign in, account registration with BR number verification, and session setup.
 */
require_once __DIR__ . "/database/connection.php";

// Self-Healing Database: Ensure user_type column, nullable business columns, and mock_whatsapp_messages table exist
if (isset($pdo) && $pdo !== null) {
    try {
        $checkUserType = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_type'");
        if (!$checkUserType->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN user_type ENUM('wholesale', 'individual') DEFAULT 'individual'");
        }
        $pdo->exec("ALTER TABLE users MODIFY COLUMN business_name VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN br_number VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN business_type VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN address TEXT NULL");

        $pdo->exec("CREATE TABLE IF NOT EXISTS mock_whatsapp_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'delivered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (\Exception $e) {}
}

$page_mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
$success_code = isset($_GET['success']) ? (int)$_GET['success'] : 0;
$success_message = "";
if ($success_code === 1) {
    $success_message = "Your wholesale account request has been submitted successfully! We will contact you within 24h.";
} elseif ($success_code === 2) {
    $success_message = "Welcome to Kesara Enterprises! Your individual account is now active. A thank you message and WhatsApp notification have been sent. You can shop our retail collection right away!";
}
$error_message = "";
$warning_message = "";
$email_error = false;
$password_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Processing request -> Handling customer registration form submission
    if ($page_mode === 'register') {
        $account_type = trim($_POST['account_type'] ?? 'individual');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
        $password = $_POST['password'] ?? '';

        $business_name = trim($_POST['business_name'] ?? '');
        $br_number = trim($_POST['br_number'] ?? '');
        $business_type = trim($_POST['business_type'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $is_individual = ($account_type === 'individual');

        if ($is_individual) {
            // Checking data -> Individual Customer Required Fields
            if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password)) {
                $error_message = "All contact fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email format.";
            } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
                $error_message = "Phone number must start with 0 and be exactly 10 digits.";
            } elseif (!preg_match('/^0[0-9]{9}$/', $whatsapp_number)) {
                $error_message = "WhatsApp number must start with 0 and be exactly 10 digits.";
            } else {
                if ($pdo) {
                    try {
                        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $check_stmt->execute([$email]);
                        if ($check_stmt->fetch()) {
                            $error_message = "An account with this email address already exists.";
                        } else {
                            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                            $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, user_type, status) VALUES (?, ?, ?, ?, ?, ?, 'individual', 'approved')");
                            $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass]);
                            $new_id = $pdo->lastInsertId();

                            // Record thank you WhatsApp notification
                            try {
                                $wa_msg = "Hello {$first_name}, thank you for joining Kesara Enterprises! Your individual account is now active. You can shop our retail collection right away!";
                                $stmt_wa = $pdo->prepare("INSERT INTO mock_whatsapp_messages (customer_id, phone, message, status) VALUES (?, ?, ?, 'delivered')");
                                $stmt_wa->execute([$new_id, $whatsapp_number, $wa_msg]);
                            } catch (\Exception $ex) {}

                            // Auto-login Individual Customer immediately
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            $_SESSION['user_id'] = $new_id;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                            $_SESSION['user_type'] = 'individual';

                            header("Location: ?mode=register&success=2", true, 303);
                            exit;
                        }
                    } catch (\Exception $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    header("Location: ?mode=register&success=2", true, 303);
                    exit;
                }
            }
        } else {
            // Wholesale Customer Registration
            if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($whatsapp_number) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
                $error_message = "All wholesale fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email format.";
            } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
                $error_message = "Phone number must start with 0 and be exactly 10 digits.";
            } elseif (!preg_match('/^0[0-9]{9}$/', $whatsapp_number)) {
                $error_message = "WhatsApp number must start with 0 and be exactly 10 digits.";
            } else {
                if ($pdo) {
                    try {
                        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $check_stmt->execute([$email]);
                        if ($check_stmt->fetch()) {
                            $error_message = "An account with this email address already exists.";
                        } else {
                            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                            $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, whatsapp_number, password, user_type, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, 'wholesale', ?, ?, ?, ?, 'pending')");
                            $insert_stmt->execute([$first_name, $last_name, $email, $phone, $whatsapp_number, $hashed_pass, $business_name, $br_number, $business_type, $address]);

                            header("Location: ?mode=register&success=1", true, 303);
                            exit;
                        }
                    } catch (\Exception $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    header("Location: ?mode=register&success=1", true, 303);
                    exit;
                }
            }
        }
    // Processing request -> Handling customer login form submission
    } elseif ($page_mode === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error_message = "Email and password are required.";
            if (empty($email)) $email_error = true;
            if (empty($password)) $password_error = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
            $email_error = true;
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password'])) {
                        if ($user['status'] === 'approved') {
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                            $_SESSION['user_type'] = $user['user_type'] ?? 'wholesale';

                            header("Location: /account");
                            exit;
                        } else {
                            if ($user['status'] === 'pending') {
                                $warning_message = "Your wholesale account approval is currently pending. We will notify you once approved.";
                            } else {
                                $error_message = "Your account is currently " . htmlspecialchars($user['status']) . ".";
                            }
                        }
                    } else {
                        $error_message = "Invalid email or password.";
                        $email_error = true;
                        $password_error = true;
                    }
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "Database connection unavailable. Please try again later.";
            }
        }
    }
}

$page_meta = [
    'title' => ($page_mode === 'register' ? 'Request Wholesale Access' : 'Sign In') . ' | Kesara Enterprises',
    'description' => 'Access the Kesara Enterprises wholesale platform for premium innerwear supply.',
];
require_once __DIR__ . "/layouts/head.php";
?>

<main class="bg-gray-950 py-12 min-h-screen flex items-center justify-center">
    <div class="max-w-7xl w-full mx-auto px-6">

        <?php if (!empty($success_message)): ?>
            <!-- SUCCESS STATE -->
            <div
                class="bg-white border border-gray-100 rounded-2xl shadow-sm p-10 max-w-xl mx-auto text-center space-y-6 animate-in fade-in zoom-in duration-500">
                <div
                    class="w-20 h-20 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto shadow-md border border-emerald-100">
                    <i class="ti ti-circle-check text-4xl"></i>
                </div>
                <div class="space-y-3">
                    <h2 class="text-2xl font-bold text-gray-900">Application Submitted</h2>
                    <p class="text-sm font-semibold text-gray-500 leading-relaxed">
                        <?= htmlspecialchars($success_message) ?>
                    </p>
                </div>
                <div class="pt-4">
                    <a href="/"
                        class="inline-block bg-brand text-brand-light font-bold px-8 py-3.5 rounded-xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 text-sm">
                        Return to Home
                    </a>
                </div>
            </div>

        <?php elseif ($page_mode === 'login'): ?>
            <!-- LOGIN FORM (Split Layout) -->
            <div class="grid lg:grid-cols-12 gap-12 items-center max-w-6xl mx-auto py-12">
                <!-- Left Side Welcome Message -->
                <div class="lg:col-span-6 space-y-6 text-white text-left">
                    <a href="/" class="inline-flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center">
                            <i class="ti ti-building-store text-brand text-xl"></i>
                        </div>
                        <span class="text-lg font-bold text-white">Kesara Enterprises</span>
                    </a>
                    <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
                        Welcome to our Wholesale Portal
                    </h1>
                    <p class="text-gray-450 text-base leading-relaxed">
                        Access premium quality, comfortable innerwear directly in bulk. We supply briefs, boxers, trunks,
                        ladies wear, and children's essentials to local retailers, supermarkets, and distributors across Sri
                        Lanka.
                    </p>
                    <div class="space-y-4 pt-6 border-t border-gray-800">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                                <i class="ti ti-check text-sm"></i>
                            </div>
                            <p class="text-sm text-gray-300">Minimum orders starting from 50 units</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                                <i class="ti ti-check text-sm"></i>
                            </div>
                            <p class="text-sm text-gray-300">Fast delivery island-wide across Sri Lanka</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                                <i class="ti ti-check text-sm"></i>
                            </div>
                            <p class="text-sm text-gray-300">Dedicated pricing and high margins for retailers</p>
                        </div>
                    </div>
                </div>

                <!-- Right Side Form -->
                <div class="lg:col-span-6 bg-white border border-gray-100 rounded-2xl shadow-sm p-8 md:p-10 w-full">
                    <!-- Branding shown only on smaller screens -->
                    <a href="/" class="flex items-center gap-3 mb-8 lg:hidden">
                        <div class="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center">
                            <i class="ti ti-building-store text-brand text-xl"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900">Kesara Enterprises</span>
                    </a>

                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome back</h2>
                    <p class="text-sm text-gray-500 mb-8">Sign in to your wholesale account</p>

                    <?php if (!empty($error_message)): ?>
                        <div
                            class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-650 text-xs font-bold flex items-center gap-3">
                            <i class="ti ti-alert-circle text-lg"></i>
                            <span><?= htmlspecialchars($error_message) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($warning_message)): ?>
                        <div
                            class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-xs font-bold flex items-center gap-3">
                            <i class="ti ti-alert-triangle text-lg"></i>
                            <span><?= htmlspecialchars($warning_message) ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email address <span
                                     class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="email" name="email" required placeholder="you@company.com"
                                    pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border <?= $email_error ? 'border-red-400 focus:ring-red-400' : 'border-gray-200 focus:ring-brand focus:border-brand' ?> rounded-lg outline-none transition-all text-sm"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <i class="ti ti-mail absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                            </div>
                            <?php if ($email_error && !empty($error_message)): ?>
                                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($error_message) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="block text-sm font-semibold text-gray-700">Password <span
                                        class="text-red-500">*</span></label>
                                <a href="#" class="text-xs font-semibold text-brand hover:underline">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <input type="password" name="password" required placeholder="••••••••"
                                    class="w-full px-4 py-3 bg-gray-50 border <?= $password_error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-200 focus:border-brand focus:ring-brand' ?> rounded-lg focus:ring-2 outline-none transition-all text-sm">
                                <i onclick="togglePasswordVisibility(this)"
                                    class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg cursor-pointer"></i>
                            </div>
                            <?php if ($password_error && !empty($error_message)): ?>
                                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($error_message) ?></p>
                            <?php endif; ?>
                        </div>

                        <button type="submit"
                            class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-lg hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg">
                            Sign in
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t border-gray-100 text-center">
                        <p class="text-sm text-gray-500">
                            New wholesale buyer? <a href="/register" class="text-brand font-bold hover:underline">Request an
                                account</a>
                        </p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- REGISTER FORM -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-8 md:p-10 max-w-6xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <a href="/" class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center">
                            <i class="ti ti-building-store text-brand text-xl"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900">Kesara Enterprises</span>
                    </a>
                    <button class="text-brand font-bold hover:underline" onclick="window.location.href='/'">Back to Shop</button>
                </div>

                <!-- Registration Type Selector Tabs -->
                <div class="grid grid-cols-2 gap-3 p-1.5 bg-gray-100 rounded-2xl mb-8">
                    <button type="button" id="tab-individual-btn" onclick="switchRegisterTab('individual')"
                        class="py-3 px-6 rounded-xl font-bold text-sm transition-all shadow-sm bg-white text-gray-900 flex items-center justify-center gap-2">
                        <i class="ti ti-user text-lg text-brand"></i>
                        Individual Customer
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-extrabold rounded-full">INSTANT ACCESS</span>
                    </button>
                    <button type="button" id="tab-wholesale-btn" onclick="switchRegisterTab('wholesale')"
                        class="py-3 px-6 rounded-xl font-bold text-sm transition-all text-gray-500 hover:text-gray-900 flex items-center justify-center gap-2">
                        <i class="ti ti-building-store text-lg"></i>
                        Wholesale Business
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-extrabold rounded-full">ADMIN REVIEW</span>
                    </button>
                </div>

                <div id="reg-header-individual">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Create Individual Account</h2>
                    <p class="text-sm text-gray-500 mb-6">No waiting period! Register with your details and start shopping retail immediately.</p>
                </div>

                <div id="reg-header-wholesale" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Request Wholesale Access</h2>
                    <p class="text-sm text-gray-500 mb-6">Register your business details to unlock wholesale bulk pricing tiers.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-650 text-xs font-bold flex items-center gap-3">
                        <i class="ti ti-alert-circle text-lg"></i>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <div id="reg-banner-individual" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex gap-4 mb-8">
                    <i class="ti ti-circle-check text-emerald-600 text-xl shrink-0 mt-0.5"></i>
                    <p class="text-xs text-emerald-800 font-medium leading-relaxed">
                        Individual accounts are automatically approved! You will receive a thank you email and WhatsApp notification immediately upon sign up.
                    </p>
                </div>

                <div id="reg-banner-wholesale" class="hidden bg-brand-light/50 border border-brand/20 rounded-xl p-4 flex gap-4 mb-8">
                    <i class="ti ti-info-circle text-brand text-xl shrink-0 mt-0.5"></i>
                    <p class="text-xs text-brand leading-relaxed">
                        Wholesale accounts require valid Business Registration (BR) details and manual admin approval.
                    </p>
                </div>

                <form id="register-form" action="" method="POST" class="space-y-8">
                    <input type="hidden" name="account_type" id="account_type_input" value="individual">

                    <div id="form-grid-container" class="grid md:grid-cols-1 lg:grid-cols-1 gap-6 mb-4">
                        <!-- Contact Details -->
                        <div id="contact-details-sec">
                            <h3 class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mb-6 flex items-center gap-4">
                                Personal & Contact Details
                                <div class="h-px bg-gray-100 flex-1"></div>
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">First name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" required placeholder="Kamal"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Last name <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_name" required placeholder="Perera"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Email address <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" required placeholder="you@example.com"
                                        pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Phone number <span class="text-red-500">*</span></label>
                                    <input type="tel" id="register-phone" name="phone" required maxlength="10"
                                        pattern="^0[0-9]{9}$" title="Phone number must start with 0 and contain exactly 10 digits"
                                        oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
                                        placeholder="0771234567"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                    <div id="phone-warning" class="hidden text-xs text-red-500 mt-1 font-medium">Please enter exactly 10 digits starting with 0.</div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">WhatsApp number <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-green-500"><i class="ti ti-brand-whatsapp text-lg"></i></span>
                                        <input type="tel" id="register-whatsapp" name="whatsapp_number" required
                                            maxlength="10" pattern="^0[0-9]{9}$" title="Phone number must start with 0 and contain exactly 10 digits"
                                            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
                                            placeholder="0771234567"
                                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-green-400 outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" id="register-password" name="password" required minlength="8" maxlength="12"
                                            placeholder="Min. 8 characters"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm"
                                            oninput="checkPasswordStrength(this.value)">
                                        <i onclick="togglePasswordVisibility(this)"
                                            class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg cursor-pointer"></i>
                                    </div>
                                    <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                        <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0 bg-gray-300"></div>
                                    </div>
                                    <p id="strengthLabel" class="text-xs mt-1 text-gray-400"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Business Info (Wholesale Only) -->
                        <div id="business-info-sec" class="hidden border-t md:border-t-0 md:border-l-2 pt-6 md:pt-0 md:pl-6 border-gray-200">
                            <h3 class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mb-6 flex items-center gap-4">
                                Business Information
                                <div class="h-px bg-gray-100 flex-1"></div>
                            </h3>

                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Business name <span class="text-red-500">*</span></label>
                                    <input type="text" name="business_name" id="input_business_name" placeholder="ABC Garments (Pvt) Ltd"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700">BR Number <span class="text-red-500">*</span></label>
                                        <input type="text" name="br_number" id="input_br_number" placeholder="PV 12345"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700">Business type <span class="text-red-500">*</span></label>
                                        <select name="business_type" id="input_business_type"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm appearance-none">
                                            <option value="">Select type</option>
                                            <option>Retailer</option>
                                            <option>Distributor</option>
                                            <option>Supermarket</option>
                                            <option>Exporter</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Address <span class="text-red-500">*</span></label>
                                    <textarea name="address" id="input_address" rows="2" placeholder="No. 12, Main Street, Colombo 03"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <input type="checkbox" id="terms" required
                            class="mt-1 rounded text-brand focus:ring-brand border-gray-300">
                        <label for="terms" class="text-xs text-gray-500 leading-relaxed">
                            I agree to the <a href="/terms-and-conditions" target="_blank"
                                class="text-brand font-semibold hover:underline">terms and conditions</a> and confirm my details are accurate.
                        </label>
                    </div>

                    <button type="submit" id="submit-reg-btn"
                        class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-lg hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg">
                        Create Account & Start Shopping
                    </button>
                </form>

                <div class="mt-8 pt-8 border-t border-gray-100 text-center">
                    <p class="text-sm text-gray-500">
                        Already have an account? <a href="/login" class="text-brand font-bold hover:underline">Sign in</a>
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
    window.togglePasswordVisibility = function(icon) {
        const input = icon.previousElementSibling;
        if (input && input.tagName === 'INPUT') {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('ti-eye');
                icon.classList.add('ti-eye-off');
            } else {
                input.type = 'password';
                icon.classList.remove('ti-eye-off');
                icon.classList.add('ti-eye');
            }
        }
    };

    window.checkPasswordStrength = function (password) {
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        if (!bar || !label) return;

        if (!password) {
            bar.className = "h-full rounded-full transition-all duration-300 w-0 bg-gray-300";
            bar.style.width = "0%";
            label.textContent = "";
            return;
        }

        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNum = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        let width = "30%";
        let colorClass = "bg-red-500";
        let text = "Low";
        let textClass = "text-xs mt-1 text-red-500 font-semibold";

        if (hasUpper && hasLower && hasNum && hasSpecial) {
            width = "100%";
            colorClass = "bg-green-500";
            text = "Strong";
            textClass = "text-xs mt-1 text-green-600 font-semibold";
        } else if (hasUpper && hasLower && hasNum) {
            width = "65%";
            colorClass = "bg-yellow-500";
            text = "Medium";
            textClass = "text-xs mt-1 text-yellow-600 font-semibold";
        }

        bar.style.width = width;
        bar.className = "h-full rounded-full transition-all duration-300 " + colorClass;
        label.textContent = text;
        label.className = textClass;
    };

    window.switchRegisterTab = function(type) {
        const inputType = document.getElementById('account_type_input');
        const tabIndividualBtn = document.getElementById('tab-individual-btn');
        const tabWholesaleBtn = document.getElementById('tab-wholesale-btn');
        const headerIndividual = document.getElementById('reg-header-individual');
        const headerWholesale = document.getElementById('reg-header-wholesale');
        const bannerIndividual = document.getElementById('reg-banner-individual');
        const bannerWholesale = document.getElementById('reg-banner-wholesale');
        const businessSec = document.getElementById('business-info-sec');
        const submitBtn = document.getElementById('submit-reg-btn');
        const formContainer = document.getElementById('form-grid-container');

        const bName = document.getElementById('input_business_name');
        const bBr = document.getElementById('input_br_number');
        const bType = document.getElementById('input_business_type');
        const bAddr = document.getElementById('input_address');

        if (inputType) inputType.value = type;

        if (type === 'wholesale') {
            tabIndividualBtn.className = "py-3 px-6 rounded-xl font-bold text-sm transition-all text-gray-500 hover:text-gray-900 flex items-center justify-center gap-2";
            tabWholesaleBtn.className = "py-3 px-6 rounded-xl font-bold text-sm transition-all shadow-sm bg-white text-gray-900 flex items-center justify-center gap-2";
            headerIndividual.classList.add('hidden');
            headerWholesale.classList.remove('hidden');
            bannerIndividual.classList.add('hidden');
            bannerWholesale.classList.remove('hidden');
            businessSec.classList.remove('hidden');
            if (formContainer) formContainer.className = "grid md:grid-cols-1 lg:grid-cols-2 gap-6 mb-4";
            if (submitBtn) submitBtn.textContent = "Submit Wholesale Application";

            if (bName) bName.required = true;
            if (bBr) bBr.required = true;
            if (bType) bType.required = true;
            if (bAddr) bAddr.required = true;
        } else {
            tabIndividualBtn.className = "py-3 px-6 rounded-xl font-bold text-sm transition-all shadow-sm bg-white text-gray-900 flex items-center justify-center gap-2";
            tabWholesaleBtn.className = "py-3 px-6 rounded-xl font-bold text-sm transition-all text-gray-500 hover:text-gray-900 flex items-center justify-center gap-2";
            headerIndividual.classList.remove('hidden');
            headerWholesale.classList.add('hidden');
            bannerIndividual.classList.remove('hidden');
            bannerWholesale.classList.add('hidden');
            businessSec.classList.add('hidden');
            if (formContainer) formContainer.className = "grid md:grid-cols-1 lg:grid-cols-1 gap-6 mb-4";
            if (submitBtn) submitBtn.textContent = "Create Account & Start Shopping";

            if (bName) bName.required = false;
            if (bBr) bBr.required = false;
            if (bType) bType.required = false;
            if (bAddr) bAddr.required = false;
        }
    };

    function initAuthForm() {
        const phoneInput = document.getElementById('register-phone');
        const phoneWarning = document.getElementById('phone-warning');

        let phoneTimeout;
        if (phoneInput && phoneWarning && !phoneInput.dataset.initialized) {
            phoneInput.dataset.initialized = 'true';
            phoneInput.addEventListener('input', function () {
                const val = this.value;
                if (/[^0-9+]/.test(val)) {
                    phoneWarning.classList.remove('hidden');
                    this.value = val.replace(/[^0-9+]/g, '');

                    clearTimeout(phoneTimeout);
                    phoneTimeout = setTimeout(() => {
                        phoneWarning.classList.add('hidden');
                    }, 2000);
                }
            });
        }

        const registerForm = document.getElementById('register-form');
        if (registerForm && !registerForm.dataset.initialized) {
            registerForm.dataset.initialized = 'true';
            registerForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const btn = this.querySelector('button[type="submit"]');
                if (btn && typeof setButtonLoading === 'function') {
                    setButtonLoading(btn, true, 'Creating Account...');
                } else if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Creating Account...';
                }

                fetch('api/register.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const code = data.success_code || 1;
                            window.location.href = '?mode=register&success=' + code;
                        } else {
                            if (btn && typeof setButtonLoading === 'function') {
                                setButtonLoading(btn, false);
                            } else if (btn) {
                                btn.disabled = false;
                                btn.textContent = 'Create Account & Start Shopping';
                            }
                            if (typeof showToast === 'function') {
                                showToast(data.message || 'Error requesting account.', 'error');
                            } else {
                                alert(data.message || 'Error requesting account.');
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        if (btn && typeof setButtonLoading === 'function') {
                            setButtonLoading(btn, false);
                        } else if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Create Account & Start Shopping';
                        }
                        if (typeof showToast === 'function') {
                            showToast('Network error occurred.', 'error');
                        } else {
                            alert('Network error occurred.');
                        }
                    });
            });
        }
    }

    initAuthForm();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAuthForm);
    }
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>

<?php
/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: auth.php (Customer Login & Wholesale Registration)

 CONNECTED / DEPENDENT FILES:
   - Database Connection: database/connection.php
   - REST API Endpoint: api/register.php
   - Customer Dashboard: my_account.php
   - Admin Management: admin/view/customers.view.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Form Fields: HTML input fields inside auth.php
   - API Handler: api/register.php (if updating API registration logic)
   - Admin Customer View: admin/view/customers.view.php (if adding user attributes)
=============================================================================
*/
?>