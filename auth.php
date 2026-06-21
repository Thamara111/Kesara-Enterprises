<?php
require_once __DIR__ . "/database/connection.php";

$page_mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($page_mode === 'register') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $business_name = trim($_POST['business_name'] ?? '');
        $br_number = trim($_POST['br_number'] ?? '');
        $business_type = trim($_POST['business_type'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($business_name) || empty($br_number) || empty($business_type) || empty($address)) {
            $error_message = "All fields are required.";
        } else {
            if ($pdo) {
                try {
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    if ($check_stmt->fetch()) {
                        $error_message = "An account with this email address already exists.";
                    } else {
                        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                        $insert_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, business_name, br_number, business_type, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                        $insert_stmt->execute([$first_name, $last_name, $email, $phone, $hashed_pass, $business_name, $br_number, $business_type, $address]);
                        $success_message = "Your wholesale account request has been submitted successfully! We will contact you within 24h.";
                    }
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $success_message = "Your wholesale account request has been submitted successfully! We will contact you within 24h. (Offline Mode)";
            }
        }
    } elseif ($page_mode === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error_message = "Email and password are required.";
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        if ($user['status'] === 'approved') {
                            header("Location: /account");
                            exit;
                        } else {
                            $error_message = "Your account is currently " . htmlspecialchars($user['status']) . ".";
                        }
                    } else {
                        $error_message = "Invalid email or password.";
                    }
                } catch (\Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                if ($email === 'kamal@abc.lk' && $password === 'admin123') {
                    header("Location: /account");
                    exit;
                } else {
                    $error_message = "Invalid email or password. (Offline Mode)";
                }
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

<main class="bg-gray-50 py-4 min-h-screen">
    <div class="w-full mx-auto px-6">
        
        <?php if (!empty($success_message)): ?>
        <!-- SUCCESS STATE -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-10 max-w-xl mx-auto text-center space-y-6 animate-in fade-in zoom-in duration-500">
            <div class="w-20 h-20 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto shadow-md border border-emerald-100">
                <i class="ti ti-circle-check text-4xl"></i>
            </div>
            <div class="space-y-3">
                <h2 class="text-2xl font-bold text-gray-900">Application Submitted</h2>
                <p class="text-sm font-semibold text-gray-500 leading-relaxed">
                    <?= htmlspecialchars($success_message) ?>
                </p>
            </div>
            <div class="pt-4">
                <a href="/login" class="inline-block bg-brand text-brand-light font-bold px-8 py-3.5 rounded-xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 text-sm">
                    Return to Login
                </a>
            </div>
        </div>
        
        <?php elseif ($page_mode === 'login'): ?>
        <!-- LOGIN FORM -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-8 md:p-10 max-w-xl mx-auto">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center">
                    <i class="ti ti-building-store text-brand text-xl"></i>
                </div>
                <span class="text-lg font-bold text-gray-900">Kesara Enterprises</span>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome back</h2>
            <p class="text-sm text-gray-500 mb-8">Sign in to your wholesale account</p>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-650 text-xs font-bold flex items-center gap-3">
                <i class="ti ti-alert-circle text-lg"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email address <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="email" name="email" required placeholder="you@company.com" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        <i class="ti ti-mail absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between mb-2">
                        <label class="block text-sm font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                        <a href="#" class="text-xs font-semibold text-brand hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        <i class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg cursor-pointer"></i>
                    </div>
                </div>

                <button type="submit" class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-lg hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg">
                    Sign in
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    New wholesale buyer? <a href="/register" class="text-brand font-bold hover:underline">Request an account</a>
                </p>
            </div>
        </div>

        <?php else: ?>
        <!-- REGISTER FORM -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-8 md:p-10 max-w-6xl mx-auto">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center">
                    <i class="ti ti-building-store text-brand text-xl"></i>
                </div>
                <span class="text-lg font-bold text-gray-900">Kesara Enterprises</span>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Request wholesale access</h2>
            <p class="text-sm text-gray-500 mb-8">Your account will be reviewed before activation</p>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-650 text-xs font-bold flex items-center gap-3">
                <i class="ti ti-alert-circle text-lg"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <div class="bg-brand-light/50 border border-brand/20 rounded-xl p-4 flex gap-4 mb-8">
                <i class="ti ti-info-circle text-brand text-xl shrink-0 mt-0.5"></i>
                <p class="text-xs text-brand leading-relaxed">
                    This platform is for registered businesses only. Personal orders are not accepted.
                </p>
            </div>

            <form action="" method="POST" class="space-y-8">
                <!-- Contact Details -->
                <div>
                    <h3 class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mb-6 flex items-center gap-4">
                        Contact Details
                        <div class="h-px bg-gray-100 flex-1"></div>
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">First name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" required placeholder="Kamal" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Last name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" required placeholder="Perera" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Email address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required placeholder="you@company.com" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Phone number <span class="text-red-500">*</span></label>
                            <input type="tel" name="phone" required placeholder="+94 77 123 4567" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password" required placeholder="Min. 8 characters" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                                <i class="ti ti-eye absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg cursor-pointer"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Info -->
                <div>
                    <h3 class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mb-6 flex items-center gap-4">
                        Business Information
                        <div class="h-px bg-gray-100 flex-1"></div>
                    </h3>

                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Business name <span class="text-red-500">*</span></label>
                            <input type="text" name="business_name" required placeholder="ABC Garments (Pvt) Ltd" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">BR Number <span class="text-red-500">*</span></label>
                                <input type="text" name="br_number" required placeholder="PV 12345" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Business type <span class="text-red-500">*</span></label>
                                <select name="business_type" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm appearance-none">
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
                            <textarea name="address" required rows="2" placeholder="No. 12, Main Street, Colombo 03" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <input type="checkbox" id="terms" required class="mt-1 rounded text-brand focus:ring-brand border-gray-300">
                    <label for="terms" class="text-xs text-gray-500 leading-relaxed">
                        I confirm this is a registered business and I agree to the <a href="#" class="text-brand font-semibold hover:underline">wholesale terms and conditions</a>.
                    </label>
                </div>

                <button type="submit" class="w-full bg-brand text-brand-light font-bold py-3.5 rounded-lg hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg">
                    Submit application
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
