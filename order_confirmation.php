<?php
require_once __DIR__ . "/database/connection.php";

$order_id = $_GET['id'] ?? ($_GET['order_id'] ?? 1);

$order = null;
$order_items = [];
$subtotal = 0;
$vat = 0;
$total = 0;
$total_units = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch order details
        $stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.business_name, u.address 
                               FROM orders o 
                               JOIN users u ON o.user_id = u.id 
                               WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Fetch order items with product details and default variations
            $stmt_items = $pdo->prepare("SELECT oi.*, p.name AS product_name, p.sku
                                         FROM order_items oi 
                                         JOIN products p ON oi.product_id = p.id 
                                         WHERE oi.order_id = ?");
            $stmt_items->execute([$order_id]);
            $order_items = $stmt_items->fetchAll();
            
            $total = (float)$order['total_amount'];
            // Reverse calculate subtotal and VAT
            $subtotal = $total / 1.18;
            $vat = $total - $subtotal;
            
            foreach ($order_items as $oi) {
                $total_units += $oi['quantity'];
            }
        }
    } catch (\Exception $e) {
        // Fallback
    }
}

// Fallback to mock data if database is offline or order is not found
if (!$order) {
    $order = [
        'id' => 1,
        'email' => 'kamal@abcgarments.lk',
        'first_name' => 'Kamal',
        'last_name' => 'Perera',
        'phone' => '+94 77 123 4567',
        'business_name' => 'ABC Garments (Pvt) Ltd',
        'address' => "No. 45, Factory Road\nKatunayake, Western Province",
        'total_amount' => 71933.00
    ];
    
    $order_items = [
        [
            'product_name' => 'Classic Cotton Brief',
            'colour' => 'Black',
            'size' => 'M',
            'sku' => 'KB-001',
            'quantity' => 120,
            'unit_price' => 108.00
        ],
        [
            'product_name' => 'Ladies Hipster',
            'colour' => 'White',
            'size' => 'S',
            'sku' => 'KL-003',
            'quantity' => 100,
            'unit_price' => 130.00
        ],
        [
            'product_name' => 'Stretch Boxer',
            'colour' => 'Navy',
            'size' => 'L',
            'sku' => 'KB-008',
            'quantity' => 200,
            'unit_price' => 175.00
        ]
    ];
    $subtotal = 60960.00;
    $vat = 10973.00;
    $total = 71933.00;
    $total_units = 420;
}

$page_meta = [
    'title' => 'Order Confirmed | Kesara Enterprises',
    'description' => 'Thank you for your order. Your wholesale innerwear order has been successfully placed.',
];
require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 py-12 min-h-screen">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        
        <!-- BREADCRUMBS -->
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-400 mb-10 overflow-x-auto whitespace-nowrap">
            <a href="/" class="hover:text-brand transition-colors">Home</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="hover:text-brand transition-colors">My Account</span>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900 font-bold tracking-tight uppercase">Order Confirmed</span>
        </nav>

        <!-- SUCCESS BANNER -->
        <div class="bg-brand-light/50 border border-brand/20 rounded-3xl p-8 mb-10 flex flex-col md:flex-row items-center gap-8 shadow-sm">
            <div class="w-16 h-16 rounded-full bg-brand flex items-center justify-center shrink-0 shadow-lg shadow-brand/20">
                <i class="ti ti-check text-3xl text-brand-light"></i>
            </div>
            <div class="text-center md:text-left flex-1">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Order Placed Successfully</h1>
                <p class="text-sm text-brand font-medium leading-relaxed mb-4">
                    A confirmation has been sent to <span class="font-bold"><?= htmlspecialchars($order['email']) ?></span>
                </p>
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-4">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Order ID:</span>
                    <div class="flex items-center gap-3 px-4 py-2 bg-brand/10 text-brand rounded-full text-sm font-bold border border-brand/5">
                        KE-2025-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>
                        <button onclick="navigator.clipboard.writeText('KE-2025-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>')" class="hover:scale-110 transition-transform">
                            <i class="ti ti-copy text-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-[1fr_380px] gap-12 items-start">
            
            <!-- LEFT COLUMN: ORDER DETAILS -->
            <div class="space-y-8">
                
                <!-- Items Ordered -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">Items Ordered</h2>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($order_items as $item): ?>
                        <div class="py-4 flex justify-between items-center gap-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900"><?= htmlspecialchars($item['product_name']) ?></h3>
                                <p class="text-[11px] text-gray-400 font-medium"><?= htmlspecialchars($item['color'] ?? 'Standard Color') ?> · Size <?= htmlspecialchars($item['size'] ?? 'M') ?> · SKU <?= htmlspecialchars($item['sku']) ?> · <?= htmlspecialchars($item['quantity']) ?> units @ LKR <?= number_format($item['unit_price']) ?>/pc</p>
                            </div>
                            <span class="text-sm font-bold text-gray-900 whitespace-nowrap">LKR <?= number_format($item['quantity'] * $item['unit_price']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Delivery Details -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">Delivery Details</h2>
                    <div class="grid md:grid-cols-2 gap-10">
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest mb-1.5">Deliver To</p>
                                <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($order['business_name']) ?></p>
                                <p class="text-[13px] text-gray-500 leading-relaxed"><?= nl2br(htmlspecialchars($order['address'])) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest mb-1.5">Contact</p>
                                <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
                                <p class="text-[13px] text-gray-500 leading-relaxed"><?= htmlspecialchars($order['phone']) ?></p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest mb-1.5">Estimated Delivery</p>
                                <p class="text-sm font-bold text-gray-900">3–5 Business Days</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest mb-1.5">Payment Method</p>
                                <p class="text-sm font-bold text-gray-900">Bank Transfer</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 p-4 bg-gray-50 rounded-2xl flex items-start gap-4">
                        <i class="ti ti-note text-lg text-gray-400 shrink-0 mt-0.5"></i>
                        <p class="text-[13px] text-gray-500 italic">"Please deliver to warehouse entrance. Contact representative upon arrival."</p>
                    </div>
                </div>

                <!-- Timeline / Next Steps -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-8">What Happens Next</h2>
                    <div class="relative space-y-12 pl-8 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-px before:bg-gray-100">
                        <div class="relative">
                            <div class="absolute -left-[31px] top-1 w-6 h-6 rounded-full bg-brand text-brand-light flex items-center justify-center text-[11px] font-bold shadow-md shadow-brand/20">1</div>
                            <h3 class="text-sm font-bold text-gray-900 mb-2 tracking-tight uppercase">Payment Confirmation</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Complete your bank transfer to the account details provided. Your order will be processed once payment is confirmed by our accounts team.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-[31px] top-1 w-6 h-6 rounded-full bg-brand-light text-brand flex items-center justify-center text-[11px] font-bold border border-brand/20">2</div>
                            <h3 class="text-sm font-bold text-gray-900 mb-2 tracking-tight uppercase">Order Processing</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Our warehouse team will pick and pack your order within 1–2 business days of payment receipt. You'll receive an email update.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-[31px] top-1 w-6 h-6 rounded-full bg-brand-light text-brand flex items-center justify-center text-[11px] font-bold border border-brand/20">3</div>
                            <h3 class="text-sm font-bold text-gray-900 mb-2 tracking-tight uppercase">Dispatch and Delivery</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">You'll receive a dispatch notification with tracking info. Your official VAT invoice will be attached to the physical shipment.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: SUMMARY & ACTIONS -->
            <aside class="space-y-8 sticky top-24">
                
                <!-- Order Total & Bank Info -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm overflow-hidden relative">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-brand/5 -mr-16 -mt-16 rounded-full"></div>
                    
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-8">Order Summary</h2>
                    
                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-400 font-medium tracking-wide">Subtotal (<?= htmlspecialchars($total_units) ?> units)</span>
                            <span class="text-gray-900 font-bold">LKR <?= number_format($subtotal) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-400 font-medium tracking-wide">VAT (18%)</span>
                            <span class="text-gray-900 font-bold">LKR <?= number_format($vat) ?></span>
                        </div>
                    </div>

                    <div class="bg-brand-light/30 border border-brand/10 rounded-2xl p-6 mb-8">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-brand uppercase tracking-widest">Total Payable</span>
                            <span class="text-2xl font-extrabold text-brand">LKR <?= number_format($total) ?></span>
                        </div>
                    </div>

                    <div class="bg-gray-900 rounded-2xl p-6 text-white space-y-4 relative overflow-hidden">
                        <div class="relative z-10">
                            <h3 class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-4">Bank Transfer Details</h3>
                            <div class="space-y-3 text-[13px] font-medium">
                                <div class="flex justify-between"><span class="text-white/50">Bank</span><span>Commercial Bank</span></div>
                                <div class="flex justify-between"><span class="text-white/50">Account</span><span>Kesara Enterprises</span></div>
                                <div class="flex justify-between"><span class="text-white/50">Acc No</span><span>1234567890</span></div>
                                <div class="flex justify-between border-t border-white/10 pt-3">
                                    <span class="text-white/50 uppercase tracking-widest text-[10px] mt-0.5">Reference</span>
                                    <span class="text-brand-light font-bold">KE-2025-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </div>
                            </div>
                        </div>
                        <i class="ti ti-building-bank absolute -bottom-4 -right-4 text-7xl text-white/5"></i>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm space-y-4">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Available Actions</h2>
                    <button class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2">
                        <i class="ti ti-download text-xl"></i>
                        Download Invoice
                    </button>
                    <a href="/account" class="w-full bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px active:scale-95 flex items-center justify-center gap-2">
                        <i class="ti ti-package text-xl"></i>
                        View My Orders
                    </a>
                    <a href="/catalog" class="w-full bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px active:scale-95 flex items-center justify-center gap-2">
                        <i class="ti ti-refresh text-xl"></i>
                        Place Another Order
                    </a>
                </div>

                <!-- Help -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">Need Assistance?</h2>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-brand border border-gray-100">
                                <i class="ti ti-phone text-lg"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phone</p>
                                <p class="text-sm font-bold text-gray-900">+94 11 234 5678</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-brand border border-gray-100">
                                <i class="ti ti-mail text-lg"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Email</p>
                                <p class="text-sm font-bold text-gray-900">sales@kesara.lk</p>
                            </div>
                        </div>
                    </div>
                </div>

            </aside>

        </div>
    </div>
</main>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
