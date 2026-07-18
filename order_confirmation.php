<?php
require_once __DIR__ . "/database/connection.php";

$order_id = $_GET['id'] ?? ($_GET['order_id'] ?? 1);

$order = null;
$order_items = [];
$status_logs = [];
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

            // Fetch order status logs
            $stmt_logs = $pdo->prepare("SELECT * FROM order_status_log WHERE order_id = ? ORDER BY changed_at ASC");
            $stmt_logs->execute([$order_id]);
            $status_logs = $stmt_logs->fetchAll();
        }
    } catch (\Exception $e) {
        // Fallback
    }
}

// Redirect to account if order is not found or database is offline
if (!$order) {
    header("Location: /account");
    exit;
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
                <p class="text-sm text-brand font-bold leading-relaxed mb-1">
                    Thank you for uploading your payment receipt.
                </p>
                <p class="text-xs text-gray-500 font-semibold leading-relaxed mb-4">
                    Our administration team is currently verifying the transaction and will initialize the order process shortly. We will notify you at <span class="font-bold text-gray-700"><?= htmlspecialchars($order['email']) ?></span> via email once confirmed.
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
                        <?php
                        $current_status = $order ? strtolower($order['status']) : 'pending';
                        
                        $log_map = [];
                        foreach ($status_logs as $log) {
                            $log_map[strtolower($log['status'])] = $log;
                        }

                        $states = ['pending', 'processing', 'shipped', 'delivered'];
                        $currentStateIdx = array_search($current_status, $states);
                        if ($currentStateIdx === false) $currentStateIdx = -1;

                        $step_titles = [
                            'pending' => 'Order Placed',
                            'processing' => 'Payment & Processing',
                            'shipped' => 'Dispatched & Shipped',
                            'delivered' => 'Delivered'
                        ];

                        $step_descriptions = [
                            'pending' => 'Our administration team is currently reviewing your uploaded payment receipt. We will verify the transaction and initialize order processing shortly, and notify you via email.',
                            'processing' => 'Payment confirmed. Our warehouse team is picking and packing your order.',
                            'shipped' => 'Your order has been dispatched! It will be delivered in 1-2 business days.',
                            'delivered' => 'Order delivered. Thank you for shopping with Kesara Enterprises!'
                        ];

                        if ($current_status === 'cancelled'):
                            $cancel_log = $log_map['cancelled'] ?? null;
                            $cancel_date = $cancel_log ? date('d M Y, g:i A', strtotime($cancel_log['changed_at'])) : date('d M Y, g:i A');
                            $cancel_note = $cancel_log && !empty($cancel_log['note']) ? $cancel_log['note'] : 'This order has been cancelled.';
                        ?>
                            <div class="relative">
                                <div class="absolute -left-[31px] top-1 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center text-[11px] font-bold shadow-md shadow-red-500/20">
                                    <i class="ti ti-x"></i>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <h3 class="text-sm font-bold text-red-600 tracking-tight uppercase">Order Cancelled</h3>
                                    <span class="text-[10px] text-gray-400 font-bold">(<?= $cancel_date ?>)</span>
                                </div>
                                <p class="text-sm text-gray-500 leading-relaxed"><?= htmlspecialchars($cancel_note) ?></p>
                            </div>
                        <?php
                        else:
                            foreach ($states as $idx => $st):
                                $has_log = isset($log_map[$st]);
                                $title = $step_titles[$st];
                                $default_desc = $step_descriptions[$st];
                                
                                if ($idx < $currentStateIdx) {
                                    // Completed step
                                    $circle_bg = 'bg-brand text-brand-light';
                                    $circle_icon = '<i class="ti ti-check text-xs"></i>';
                                    $title_class = 'text-gray-900';
                                    $log_time = $has_log ? date('d M Y, g:i A', strtotime($log_map[$st]['changed_at'])) : '';
                                    $desc = ($has_log && $log_map[$st]['note'] && trim($log_map[$st]['note']) !== '') ? $log_map[$st]['note'] : $default_desc;
                                } elseif ($idx === $currentStateIdx) {
                                    // Current active step
                                    $circle_bg = 'bg-brand text-brand-light ring-4 ring-brand-light';
                                    $circle_icon = '<i class="ti ti-loader text-xs animate-spin"></i>';
                                    $title_class = 'text-brand';
                                    $log_time = $has_log ? date('d M Y, g:i A', strtotime($log_map[$st]['changed_at'])) : 'In Progress';
                                    $desc = ($has_log && $log_map[$st]['note'] && trim($log_map[$st]['note']) !== '') ? $log_map[$st]['note'] : $default_desc;
                                } else {
                                    // Pending upcoming step
                                    $circle_bg = 'bg-brand-light text-brand border border-brand/20';
                                    $circle_icon = ($idx + 1);
                                    $title_class = 'text-gray-400';
                                    $log_time = '';
                                    $desc = $default_desc;
                                }
                        ?>
                                <div class="relative">
                                    <div class="absolute -left-[31px] top-1 w-6 h-6 rounded-full <?= $circle_bg ?> flex items-center justify-center text-[11px] font-bold shadow-md">
                                        <?= $circle_icon ?>
                                    </div>
                                    <div class="flex flex-wrap items-baseline gap-2 mb-2">
                                        <h3 class="text-sm font-bold <?= $title_class ?> tracking-tight uppercase"><?= htmlspecialchars($title) ?></h3>
                                        <?php if (!empty($log_time)): ?>
                                            <span class="text-[10px] text-gray-400 font-bold">(<?= $log_time ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500 leading-relaxed"><?= htmlspecialchars($desc) ?></p>
                                </div>
                        <?php
                            endforeach;
                        endif;
                        ?>
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
                    <button onclick="downloadInvoice()" class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2">
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

<script>
const orderData = {
    id: <?= json_encode('KE-2025-' . str_pad($order['id'], 5, '0', STR_PAD_LEFT)) ?>,
    businessName: <?= json_encode($order['business_name']) ?>,
    address: <?= json_encode($order['address']) ?>,
    phone: <?= json_encode($order['phone']) ?>,
    email: <?= json_encode($order['email']) ?>,
    total: <?= json_encode(number_format($total, 2)) ?>,
    subtotal: <?= json_encode(number_format($subtotal, 2)) ?>,
    vat: <?= json_encode(number_format($vat, 2)) ?>,
    items: <?= json_encode($order_items) ?>
};

function downloadInvoice() {
    let itemsHtml = orderData.items.map(item => `
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                <strong>${item.product_name}</strong><br>
                <small>Size: ${item.size} · Colour: ${item.colour}</small>
            </td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${item.quantity}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">LKR ${parseFloat(item.unit_price).toFixed(2)}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">LKR ${(item.quantity * parseFloat(item.unit_price)).toFixed(2)}</td>
        </tr>
    `).join('');

    let printWindow = window.open('', '_blank');
    printWindow.document.write(\`
        <html>
        <head>
            <title>VAT Invoice \${orderData.id} - Kesara Enterprises</title>
            <style>
                body { font-family: sans-serif; color: #333; padding: 40px; }
                .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0F6E56; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #0F6E56; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #f5f5f5; padding: 10px; text-align: left; }
                .totals { margin-top: 30px; text-align: right; font-size: 14px; line-height: 2; }
                .footer { margin-top: 50px; font-size: 11px; color: #777; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <div class="logo">Kesara Enterprises</div>
                    <div>Wholesale Underwear Supplier Sri Lanka</div>
                    <div>Colombo, Sri Lanka</div>
                </div>
                <div style="text-align: right;">
                    <h2>VAT INVOICE</h2>
                    <div>Invoice: \${orderData.id}</div>
                    <div>Date: \${new Date().toLocaleDateString()}</div>
                </div>
            </div>
            <div style="margin-bottom: 30px;">
                <strong>Billed To:</strong><br>
                \${orderData.businessName}<br>
                \${orderData.address.replace(/\\n/g, '<br>')}<br>
                Phone: \${orderData.phone} · Email: \${orderData.email}
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    \${itemsHtml}
                </tbody>
            </table>
            <div class="totals">
                <div>Subtotal: LKR \${orderData.subtotal}</div>
                <div>VAT (18%): LKR \${orderData.vat}</div>
                <div style="font-size: 18px; font-weight: bold; margin-top: 10px; color: #0F6E56;">Total Amount: LKR \${orderData.total}</div>
            </div>
            <div class="footer">
                <p>This is a computer generated VAT invoice for your wholesale order.</p>
                <p>© \${new Date().getFullYear()} Kesara Enterprises. All rights reserved.</p>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                }
            <\/script>
        </body>
        </html>
    \`);
    printWindow.document.close();
}
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
