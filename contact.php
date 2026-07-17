<?php
require_once __DIR__ . "/database/connection.php";

$errors = [];
$success = isset($_GET['success']) && $_GET['success'] == 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $inquiry_type = trim($_POST['inquiry_type'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation
    if (empty($name)) {
        $errors['name'] = 'Your name is required.';
    }
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (empty($inquiry_type)) {
        $errors['inquiry_type'] = 'Please select an inquiry type.';
    }
    if (empty($message)) {
        $errors['message'] = 'Message content is required.';
    }

    // Save to Database
    if (empty($errors)) {
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO inquiries (name, business_name, email, phone, inquiry_type, message) 
                                       VALUES (:name, :business_name, :email, :phone, :inquiry_type, :message)");
                $stmt->execute([
                    ':name' => $name,
                    ':business_name' => $business_name ?: null,
                    ':email' => $email,
                    ':phone' => $phone ?: null,
                    ':inquiry_type' => $inquiry_type,
                    ':message' => $message
                ]);
                $success = true;
                
                // Send email notification to admin
                require_once __DIR__ . "/src/Mailer.php";
                $subject = "New Inquiry: " . htmlspecialchars($inquiry_type);
                $body = "<h3>New Inquiry Received</h3>" .
                        "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>" .
                        "<p><strong>Business:</strong> " . htmlspecialchars($business_name) . "</p>" .
                        "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>" .
                        "<p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>" .
                        "<p><strong>Message:</strong><br/>" . nl2br(htmlspecialchars($message)) . "</p>";
                \App\Mailer::send('admin@kesara.lk', $subject, $body);

                // Clear fields on success
                $name = $business_name = $email = $phone = $inquiry_type = $message = '';
            } catch (\Exception $e) {
                $errors['db'] = 'Failed to submit inquiry. Please try again later. (' . $e->getMessage() . ')';
            }
        } else {
            $errors['db'] = 'Database connection offline. Unable to process inquiry.';
        }
    }
}

$page_meta = [
    'title' => 'Contact Us | Kesara Enterprises - Wholesale Inquiries',
    'description' => 'Get in touch with Kesara Enterprises. Submit a wholesale inquiry, find our location in Colombo 10, or call our customer team.',
];

require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        <!-- Hero text / Heading -->
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-[10px] font-bold tracking-widest text-brand uppercase block mb-3">Get in Touch</span>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 leading-tight">
                Connect With Our Wholesale Team
            </h1>
            <p class="text-gray-500 mt-4 text-[15px] leading-relaxed">
                Have questions about pricing, stock availability, or registration? Fill out the form below or reach out to us directly.
            </p>
        </div>

        <div class="grid lg:grid-cols-12 gap-12 items-start">
            <!-- Contact Info & Map (Left Column) -->
            <div class="lg:col-span-5 space-y-8">
                <!-- Info cards grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-brand-light flex items-center justify-center text-brand shrink-0">
                            <i class="ti ti-phone text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Phone</p>
                            <p class="text-sm font-bold text-gray-900">+94 11 234 5678</p>
                            <p class="text-[11px] text-gray-500 mt-1">Mon–Fri, 8am–5pm</p>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-brand-light flex items-center justify-center text-brand shrink-0">
                            <i class="ti ti-mail text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Email</p>
                            <p class="text-sm font-bold text-gray-900">sales@kesara.lk</p>
                            <p class="text-[11px] text-gray-500 mt-1">24/7 Response time</p>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-start gap-4 sm:col-span-2">
                        <div class="w-10 h-10 rounded-xl bg-brand-light flex items-center justify-center text-brand shrink-0">
                            <i class="ti ti-map-pin text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Office Address</p>
                            <p class="text-sm font-bold text-gray-900">Colombo 10, Sri Lanka</p>
                            <p class="text-[11px] text-gray-500 mt-1">Visits by appointment only</p>
                        </div>
                    </div>
                </div>

                <!-- Location Map -->
                <div class="bg-white p-4 rounded-3xl border border-gray-100 shadow-sm space-y-4">
                    <div class="flex justify-between items-center px-2">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Our Location</h3>
                        <span class="text-xs font-medium text-brand flex items-center gap-1">
                            <i class="ti ti-map"></i> Colombo 10
                        </span>
                    </div>
                    <!-- OpenStreetMap iframe -->
                    <div class="w-full h-80 rounded-2xl overflow-hidden border border-gray-100">
                        <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=79.855%2C6.915%2C79.885%2C6.945&amp;layer=mapnik&amp;marker=6.930%2C79.870" style="border: 0;"></iframe>
                    </div>
                </div>
            </div>

            <!-- Inquiry Form (Right Column) -->
            <div class="lg:col-span-7">
                <div class="bg-white p-8 md:p-10 rounded-3xl border border-gray-100 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Send an Inquiry</h2>
                    
                    <?php if ($success): ?>
                        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-800 rounded-2xl flex items-start gap-3">
                            <i class="ti ti-circle-check text-xl text-emerald-600 shrink-0"></i>
                            <div>
                                <h4 class="font-bold text-sm">Inquiry Submitted Successfully</h4>
                                <p class="text-xs text-emerald-700 mt-1">Thank you for contacting us! Our wholesale team has received your details and will get back to you within 1 business day.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors['db'])): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-100 text-red-800 rounded-2xl flex items-start gap-3">
                            <i class="ti ti-alert-circle text-xl text-red-600 shrink-0"></i>
                            <div>
                                <h4 class="font-bold text-sm">Submission Error</h4>
                                <p class="text-xs text-red-700 mt-1"><?= htmlspecialchars($errors['db']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="inquiry-form" action="/contact" method="POST" class="space-y-6">
                        <!-- Two Column Row -->
                        <div class="grid sm:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Your Name *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" 
                                       class="w-full bg-gray-50 border <?= isset($errors['name']) ? 'border-red-300 focus:ring-red-200' : 'border-gray-200 focus:ring-brand/20' ?> rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 transition-all" 
                                       placeholder="John Doe">
                                <?php if (isset($errors['name'])): ?>
                                    <p class="text-red-500 text-xs mt-1.5 font-medium"><?= $errors['name'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="business_name" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Business Name</label>
                                <input type="text" id="business_name" name="business_name" value="<?= htmlspecialchars($business_name ?? '') ?>" 
                                       class="w-full bg-gray-50 border border-gray-200 focus:ring-brand/20 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 transition-all" 
                                       placeholder="Optional (e.g. Acme Stores)">
                            </div>
                        </div>

                        <!-- Email & Phone Row -->
                        <div class="grid sm:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" 
                                       class="w-full bg-gray-50 border <?= isset($errors['email']) ? 'border-red-300 focus:ring-red-200' : 'border-gray-200 focus:ring-brand/20' ?> rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 transition-all" 
                                       placeholder="you@example.com">
                                <?php if (isset($errors['email'])): ?>
                                    <p class="text-red-500 text-xs mt-1.5 font-medium"><?= $errors['email'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="phone" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" 
                                       class="w-full bg-gray-50 border border-gray-200 focus:ring-brand/20 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 transition-all" 
                                       placeholder="Optional (e.g. +94 77 123 4567)">
                            </div>
                        </div>

                        <!-- Inquiry Type -->
                        <div>
                            <label for="inquiry_type" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Inquiry Type *</label>
                            <select id="inquiry_type" name="inquiry_type" 
                                    class="w-full bg-gray-50 border <?= isset($errors['inquiry_type']) ? 'border-red-300 focus:ring-red-200' : 'border-gray-200 focus:ring-brand/20' ?> rounded-xl px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-4 transition-all">
                                <option value="">Select an option</option>
                                <option value="wholesale" <?= isset($inquiry_type) && $inquiry_type === 'wholesale' ? 'selected' : '' ?>>Wholesale Pricing</option>
                                <option value="partnership" <?= isset($inquiry_type) && $inquiry_type === 'partnership' ? 'selected' : '' ?>>Retail Partnership</option>
                                <option value="custom_order" <?= isset($inquiry_type) && $inquiry_type === 'custom_order' ? 'selected' : '' ?>>Custom Manufacture</option>
                                <option value="delivery" <?= isset($inquiry_type) && $inquiry_type === 'delivery' ? 'selected' : '' ?>>Delivery & Shipping</option>
                                <option value="other" <?= isset($inquiry_type) && $inquiry_type === 'other' ? 'selected' : '' ?>>Other Question</option>
                            </select>
                            <?php if (isset($errors['inquiry_type'])): ?>
                                <p class="text-red-500 text-xs mt-1.5 font-medium"><?= $errors['inquiry_type'] ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Your Message *</label>
                            <textarea id="message" name="message" rows="5" 
                                      class="w-full bg-gray-50 border <?= isset($errors['message']) ? 'border-red-300 focus:ring-red-200' : 'border-gray-200 focus:ring-brand/20' ?> rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 transition-all" 
                                      placeholder="How can we help your business?"><?= htmlspecialchars($message ?? '') ?></textarea>
                            <?php if (isset($errors['message'])): ?>
                                <p class="text-red-500 text-xs mt-1.5 font-medium"><?= $errors['message'] ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full bg-brand text-white font-bold py-4 rounded-xl hover:bg-brand-dark transition-all transform hover:-translate-y-px active:scale-[0.98] shadow-lg shadow-brand/10">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('inquiry-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    fetch('api/inquiries.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Inquiry submitted successfully! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = '/contact?success=1';
            }, 1000);
        } else {
            showToast(data.message || 'Error submitting inquiry.', 'error');
            btn.disabled = false;
            btn.textContent = 'Send Message';
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error occurred.', 'error');
        btn.disabled = false;
        btn.textContent = 'Send Message';
    });
});
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
