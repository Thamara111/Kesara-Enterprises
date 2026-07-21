<?php
/**
 * Product Detail Page
 * Displays comprehensive details for a specific product including images, attributes, and pricing tiers.
 * Integrates 'Add to Cart' functionality gated by user approval status.
 */
require_once __DIR__ . "/database/connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$buyer_approved = false;

if ($is_logged_in && isset($pdo)) {
    try {
        $auth_stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
        $auth_stmt->execute([$_SESSION['user_id']]);
        $auth_user = $auth_stmt->fetch();

        if ($auth_user && $auth_user['status'] === 'approved') {
            $buyer_approved = true;
        }
    } catch (\Exception $e) {
        // Fail closed — treat as not authorized on DB error
        $buyer_approved = false;
    }
}
$can_see_prices = $is_logged_in && $buyer_approved;


$sku = $_GET['sku'] ?? 'KB-001';

// Query product
$product = null;
$pricing_tiers = [];
$variations = [];
$category_name = "Briefs";

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.sku = ?");
        $stmt->execute([$sku]);
        $product = $stmt->fetch();

        if ($product) {
            $category_name = $product['category_name'];

            // Query pricing tiers
            $stmt_tiers = $pdo->prepare("SELECT * FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC");
            $stmt_tiers->execute([$product['id']]);
            $pricing_tiers = $stmt_tiers->fetchAll();

            // Query variations (sizes and colours)
            $stmt_vars = $pdo->prepare("SELECT DISTINCT size, colour, quantity FROM inventory WHERE product_id = ?");
            $stmt_vars->execute([$product['id']]);
            $variations = $stmt_vars->fetchAll();
        }
    } catch (\Exception $e) {
        // Fallback
    }
}

// Mock Product Fallback
if (!$product) {
    $product = [
        'id' => 999,
        'sku' => $sku,
        'name' => 'Premium Men\'s Briefs (Classic Fit)',
        'description' => 'Crafted from 100% breathable combed cotton, these premium briefs offer all-day comfort and support. Features a durable elastic waistband and double-stitched hems. Ideal for everyday wear.',
        'sizes' => 'S, M, L, XL',
        'colors' => 'White, Black, Navy, Grey',
        'images' => '["https://images.unsplash.com/photo-1582214399042-16a224a51e60?q=80&w=800"]',
        'discount' => 5
    ];
    
    $pricing_tiers = [
        ['min_qty' => 1, 'price' => 450.00],
        ['min_qty' => 50, 'price' => 420.00],
        ['min_qty' => 100, 'price' => 390.00],
        ['min_qty' => 500, 'price' => 360.00]
    ];
    
    $variations = [
        ['size' => 'M', 'colour' => 'White', 'quantity' => 120],
        ['size' => 'L', 'colour' => 'Black', 'quantity' => 50]
    ];
}

// Parse JSON specs if we added them in future, for now use empty or generic
$prod_specs = [
    'material' => 'Cotton Blend',
    'gsm'      => !empty($product['gsm'] ?? '') ? $product['gsm'] : '180 GSM',
    'waistband'=> !empty($product['waistband'] ?? '') ? $product['waistband'] : 'Elastic',
    'packaging' => 'Bulk pack',
    'lead'     => '3–5 Business Days'
];
// Extract discount
$discount = isset($product['discount']) ? (float) $product['discount'] : 0;

// Extract sizes
if (!empty(trim($product['sizes'] ?? ''))) {
    $sizes = array_filter(array_map('trim', explode(',', $product['sizes'])));
} else {
    $sizes = array_unique(array_filter(array_column($variations, 'size')));
}

if (!empty(trim($product['colors'] ?? ''))) {
    $colours = array_filter(array_map('trim', explode(',', $product['colors'])));
} else {
    $colours = array_unique(array_filter(array_column($variations, 'colour')));
}

// Default selected attributes
$default_colour = !empty($colours) ? reset($colours) : 'White';
$default_size = !empty($sizes) ? reset($sizes) : 'M';

// Color classes mapping helper
function getColorClass($colorName)
{
    $colors = [
        'white' => 'bg-white border border-gray-200',
        'black' => 'bg-gray-900',
        'grey' => 'bg-gray-400',
        'blue' => 'bg-blue-700',
        'red' => 'bg-red-900',
        'pink' => 'bg-pink-400',
        'navy' => 'bg-blue-900'
    ];
    return $colors[strtolower($colorName)] ?? 'bg-gray-200';
}

$page_meta = [
    'title' => htmlspecialchars($product['name']) . ' | Kesara Enterprises',
    'description' => htmlspecialchars(substr($product['description'], 0, 150)) . '... Wholesale innerwear for Sri Lankan retailers.',
];
require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 py-12 min-h-screen">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        
        <!-- BREADCRUMBS -->
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-400 mb-8 overflow-x-auto whitespace-nowrap">
            <a href="/" class="hover:text-brand transition-colors">Home</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <a href="/catalog" class="hover:text-brand transition-colors">Products</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="hover:text-brand transition-colors"><?= htmlspecialchars($category_name) ?></span>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900 font-bold tracking-tight uppercase"><?= htmlspecialchars($product['name']) ?></span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-12 items-start">
            
            <!-- LEFT: IMAGES & SPECS -->
            <div class="space-y-10">
                <!-- Image Gallery -->
                <?php
                $prod_images = json_decode($product['images'] ?? '[]', true) ?: [];
                $primary_image = !empty($prod_images) ? $prod_images[0] : '';
                ?>
                <div class="space-y-4">
                    <div class="bg-white border border-gray-100 rounded-3xl flex items-center justify-center shadow-sm relative overflow-hidden group aspect-square lg:aspect-video">
                        <?php if (!empty($primary_image)): ?>
                                <img id="main-product-image" src="<?= htmlspecialchars($primary_image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                                <i class="ti ti-shirt text-[120px] text-gray-100 group-hover:scale-110 transition-transform duration-700"></i>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-500"></div>
                    </div>
                    
                    <?php if (!empty($prod_images) && count($prod_images) > 1): ?>
                        <div class="flex gap-4 overflow-x-auto py-2">
                            <?php foreach ($prod_images as $idx => $img): ?>
                                    <div onclick="changeMainImage('<?= htmlspecialchars($img) ?>', this)" 
                                         class="thumb-btn w-20 h-20 bg-white rounded-2xl overflow-hidden cursor-pointer shadow-sm transition-all hover:border-brand <?= $idx === 0 ? 'border-2 border-brand' : 'border border-gray-100' ?>">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                                    </div>
                            <?php endforeach; ?>
                        </div>
                        <script>
                        function changeMainImage(url, el) {
                            document.getElementById('main-product-image').src = url;
                            document.querySelectorAll('.thumb-btn').forEach(btn => {
                                btn.classList.remove('border-brand', 'border-2');
                                btn.classList.add('border-gray-100', 'border');
                            });
                            el.classList.remove('border-gray-100', 'border');
                            el.classList.add('border-brand', 'border-2');
                        }
                        </script>
                    <?php endif; ?>
                </div>

                <!-- Product Specifications -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest mb-6 border-b border-gray-50 pb-4">Product Specifications</h2>
                    <div class="grid grid-cols-2 gap-y-4 text-sm">
                        <span class="text-gray-400 font-medium">SKU</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($product['sku']) ?></span>
                        
                        <span class="text-gray-400 font-medium">Material</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($prod_specs['material']) ?></span>
                        
                        <span class="text-gray-400 font-medium">GSM</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($prod_specs['gsm']) ?></span>
                        
                        <span class="text-gray-400 font-medium">Waistband</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($prod_specs['waistband']) ?></span>
                        
                        <span class="text-gray-400 font-medium">Packaging</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($prod_specs['packaging']) ?></span>
                        
                        <span class="text-gray-400 font-medium">Lead Time</span>
                        <span class="text-gray-900 font-bold text-right md:text-left"><?= htmlspecialchars($prod_specs['lead']) ?></span>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest mb-4">Description</h2>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        <?= htmlspecialchars($product['description']) ?>
                    </p>
                </div>
            </div>

            <!-- RIGHT: ORDER PANEL -->
            <div class="bg-white border border-gray-100 rounded-3xl p-8 md:p-10 shadow-sm sticky top-24">
                
                <div class="flex flex-wrap gap-2 mb-6">
                    <span class="px-3 py-1 bg-brand-light text-brand text-[10px] font-bold rounded-full border border-brand/10 uppercase"><?= htmlspecialchars($category_name) ?></span>
                    <?php 
                        $stat = strtolower($product['status'] ?? '');
                        $is_out_of_stock = ($stat === 'out of stock');
                        if ($stat === 'in stock') {
                            $sc_bg = 'bg-green-50 border-green-100';
                            $sc_tx = 'text-green-600';
                            $sc_dot = 'bg-green-500';
                        } elseif ($stat === 'out of stock') {
                            $sc_bg = 'bg-red-50 border-red-100';
                            $sc_tx = 'text-red-600';
                            $sc_dot = 'bg-red-500';
                        } else {
                            $sc_bg = 'bg-amber-50 border-amber-100';
                            $sc_tx = 'text-amber-600';
                            $sc_dot = 'bg-amber-500';
                        }
                    ?>
                    <span class="px-3 py-1 <?= $sc_bg ?> <?= $sc_tx ?> text-[10px] font-bold rounded-full border flex items-center gap-1.5 uppercase">
                        <div class="w-1.5 h-1.5 rounded-full <?= $sc_dot ?> animate-pulse"></div>
                        <?= htmlspecialchars($product['status']) ?>
                    </span>
                </div>

                <h1 class="text-3xl font-bold text-gray-900 mb-2 leading-tight"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="text-xs font-medium text-gray-400 tracking-wider mb-4">SKU: <?= htmlspecialchars($product['sku']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($category_name) ?></p>

                <?php if ($discount > 0): ?>
                        <div class="mb-6 inline-block px-3 py-1 bg-red-50 border border-red-100 rounded-lg text-red-600 font-bold text-xs uppercase tracking-widest">
                            <?= $discount ?>% OFF
                        </div>
                <?php endif; ?>

                <hr class="border-gray-50 mb-8">

                <!-- Wholesale Pricing Tiers -->
                <div class="p-6 bg-gradient-to-br from-brand/5 to-transparent border-2 border-brand/20 rounded-3xl mb-8 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="ti ti-tags text-brand text-lg"></i>
                        <label class="block text-[11px] font-bold text-brand uppercase tracking-widest">Wholesale Volume-Based Pricing</label>
                    </div>
                    <div class="border border-brand/10 rounded-2xl overflow-hidden divide-y divide-brand/10 shadow-sm bg-white">
                        <?php $tier_idx = 1;
                        foreach ($pricing_tiers as $index => $t): ?>
                            <?php
                            // Determine if this is the default middle tier for active class (e.g. 2nd tier or 1st tier if count is small)
                            $is_active = (count($pricing_tiers) > 1 && $index === 1) || (count($pricing_tiers) === 1 && $index === 0);
                            ?>
                            <div class="flex justify-between items-center p-4 text-sm <?= $is_active ? 'bg-brand-light/30 border-y border-brand/10 text-brand' : 'bg-white text-gray-500' ?>" id="tier-<?= $tier_idx++ ?>">
                                <div class="flex items-center gap-3">
                                    <span class="<?= $is_active ? 'font-bold' : '' ?>">
                                        <?= htmlspecialchars($t['min_qty']) ?>    <?= $t['max_qty'] ? ' – ' . htmlspecialchars($t['max_qty']) : '+' ?> units
                                    </span>
                                    <?php if ($is_active): ?>
                                        <span class="px-2 py-0.5 bg-brand text-brand-light text-[9px] font-bold rounded-full">YOUR QTY</span>
                                    <?php endif; ?>
                                </div>
                                <span class="<?= $is_active ? 'text-brand font-extrabold' : 'text-gray-900 font-bold' ?>">
                                    <?php if ($can_see_prices): ?>
                                            <?php if ($discount > 0): ?>
                                                    <span class="text-gray-400 line-through text-xs mr-2">LKR <?= number_format($t['price']) ?></span>
                                                    LKR <?= number_format($t['price'] * (1 - $discount / 100)) ?> / pc
                                            <?php else: ?>
                                                    LKR <?= number_format($t['price']) ?> / pc
                                            <?php endif; ?>
                                    <?php else: ?>
                                            <span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none" title="Log in to view prices">
                                                <?php if ($discount > 0): ?>
                                                        LKR <?= number_format($t['price'] * (1 - $discount / 100)) ?> / pc
                                                <?php else: ?>
                                                        LKR <?= number_format($t['price']) ?> / pc
                                                <?php endif; ?>
                                            </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pricing_tiers)): ?>
                            <div class="flex justify-between items-center p-4 text-sm bg-brand-light/30 border-y border-brand/10 text-brand" id="tier-1">
                                <div class="flex items-center gap-3">
                                    <span class="font-bold">Base Wholesale Price</span>
                                </div>
                                <span class="text-brand font-extrabold">
                                    <?php if ($can_see_prices): ?>
                                            <?php if ($discount > 0): ?>
                                                    <span class="text-gray-400 line-through text-xs mr-2">LKR <?= number_format($product['base_price']) ?></span>
                                                    LKR <?= number_format($product['base_price'] * (1 - $discount / 100)) ?> / pc
                                            <?php else: ?>
                                                    LKR <?= number_format($product['base_price']) ?> / pc
                                            <?php endif; ?>
                                    <?php else: ?>
                                            <span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none" title="Log in to view prices">
                                                <?php if ($discount > 0): ?>
                                                        LKR <?= number_format($product['base_price'] * (1 - $discount / 100)) ?> / pc
                                                <?php else: ?>
                                                        LKR <?= number_format($product['base_price']) ?> / pc
                                                <?php endif; ?>
                                            </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attributes: Color & Size (Alibaba-Style Selector) -->
                <div class="space-y-6 mb-8">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Colour</label>
                            <span class="text-xs font-bold text-gray-500">Selected: <span id="selected-color-name" class="text-brand font-extrabold"><?= htmlspecialchars($default_colour) ?></span></span>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <?php foreach ($colours as $idx => $c): 
                                $is_selected = ($c === $default_colour);
                            ?>
                            <div class="relative inline-block">
                                <button id="color-btn-<?= $idx ?>" type="button" onclick="selectColor(<?= $idx ?>, '<?= htmlspecialchars($c) ?>')" 
                                        class="color-select-btn w-10 h-10 rounded-full <?= getColorClass($c) ?> <?= $is_selected ? 'ring-2 ring-brand ring-offset-2' : 'hover:ring-2 hover:ring-gray-300' ?> ring-offset-2 transition-all shadow-sm" 
                                        title="<?= htmlspecialchars($c) ?>"></button>
                                <span id="color-qty-badge-<?= htmlspecialchars($c) ?>" 
                                      class="shadow-md hidden"
                                      style="background-color: #ff6600; color: #ffffff; position: absolute; top: -8px; right: -8px; padding: 2px 6px; font-size: 8px; font-weight: 900; line-height: 1; border-radius: 9999px; border: 1.5px solid #ffffff; z-index: 10;"></span>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($colours)): ?>
                            <div class="relative inline-block">
                                <button class="w-10 h-10 rounded-full bg-white border border-gray-200 ring-2 ring-brand ring-offset-2 ring-offset-2 transition-all shadow-sm" title="Standard"></button>
                                <span id="color-qty-badge-Standard" 
                                      class="shadow-md hidden"
                                      style="background-color: #ff6600; color: #ffffff; position: absolute; top: -8px; right: -8px; padding: 2px 6px; font-size: 8px; font-weight: 900; line-height: 1; border-radius: 9999px; border: 1.5px solid #ffffff; z-index: 10;"></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Select Quantity by Size</label>
                        <div class="divide-y divide-gray-100 border border-gray-100 rounded-3xl overflow-hidden bg-white shadow-sm">
                            <?php 
                                $size_options = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                foreach ($size_options as $idx => $so):
                                    $is_available = in_array($so, $sizes) || empty($sizes);
                                    if (!$is_available) continue;
                                    $is_selected = ($so === $default_size);
                            ?>
                            <div id="size-row-card-<?= htmlspecialchars($so) ?>" onclick="selectSize(<?= $idx ?>, '<?= htmlspecialchars($so) ?>')"
                                 class="size-row-el flex items-center justify-between p-4 hover:bg-gray-50/50 cursor-pointer transition-colors <?= $is_selected ? 'bg-brand-light/10' : '' ?>">
                                <div class="flex items-center gap-4">
                                    <span class="w-10 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-xs font-extrabold text-gray-900 bg-gray-50"><?= htmlspecialchars($so) ?></span>
                                    <span class="text-xs font-bold text-gray-400">
                                        <?php if ($can_see_prices): ?>
                                            LKR <span class="size-price-display">...</span>
                                        <?php else: ?>
                                            <span style="filter: blur(4px);">LKR 000</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex items-center bg-gray-100 border border-gray-200 rounded-xl overflow-hidden shadow-sm" onclick="event.stopPropagation()">
                                    <button type="button" onclick="changeSizeQty('<?= htmlspecialchars($so) ?>', -10)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-brand transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?= $is_out_of_stock ? 'disabled' : '' ?>><i class="ti ti-minus text-xs"></i></button>
                                    <input type="number" id="size-qty-<?= htmlspecialchars($so) ?>" value="<?= $is_selected && !$is_out_of_stock ? (int)$product['moq'] : 0 ?>" min="0" step="10" 
                                           onfocus="selectSize(<?= $idx ?>, '<?= htmlspecialchars($so) ?>')"
                                           onchange="onSizeQtyChange('<?= htmlspecialchars($so) ?>')" 
                                           class="w-12 text-center text-xs font-black text-gray-900 bg-transparent border-none outline-none focus:ring-0 transition-all duration-300 py-1 disabled:opacity-50 disabled:cursor-not-allowed" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                    <button type="button" onclick="changeSizeQty('<?= htmlspecialchars($so) ?>', 10)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-brand transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?= $is_out_of_stock ? 'disabled' : '' ?>><i class="ti ti-plus text-xs"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quantity Control -->
                <div class="space-y-4 mb-8">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Order Quantity <span class="text-gray-300 font-medium lowercase tracking-normal">(min. <?= htmlspecialchars($product['moq']) ?>)</span></label>
                    <div class="flex items-center gap-6">
                        <div class="flex items-center bg-gray-50 border border-gray-100 rounded-xl overflow-hidden group focus-within:ring-2 focus-within:ring-brand/20 transition-all">
                            <button onclick="changeQty(-10)" class="w-12 h-12 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?= $is_out_of_stock ? 'disabled' : '' ?>><i class="ti ti-minus text-sm"></i></button>
                            <span id="qty-display" class="w-16 text-center text-sm font-bold <?= $is_out_of_stock ? 'text-gray-400' : 'text-gray-900' ?>"><?= $is_out_of_stock ? 0 : (int) $product['moq'] ?></span>
                            <button onclick="changeQty(10)" class="w-12 h-12 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?= $is_out_of_stock ? 'disabled' : '' ?>><i class="ti ti-plus text-sm"></i></button>
                        </div>
                        <span class="text-sm font-bold text-gray-400 uppercase tracking-widest">Units</span>
                    </div>
                    <!-- MOQ Warning -->
                    <div id="moq-warn" class="hidden items-center gap-3 p-3 bg-red-50 border border-red-100 rounded-xl text-red-600">
                        <i class="ti ti-alert-triangle text-lg"></i>
                        <span class="text-xs font-bold uppercase tracking-wide">Min. <?= htmlspecialchars($product['moq']) ?> units required</span>
                    </div>
                </div>

                <!-- Order Summary Box -->
                <div class="bg-gray-50 rounded-2xl p-6 space-y-4 mb-8">
                    <div class="flex justify-between text-xs font-bold text-gray-400 uppercase tracking-widest">
                        <span>Unit Price</span>
                        <span id="unit-price" class="text-gray-900">
                            <?php if ($can_see_prices): ?>
                                    LKR <?= !empty($pricing_tiers) ? number_format($pricing_tiers[0]['price']) : number_format($product['base_price']) ?>
                            <?php else: ?>
                                    <span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none">LKR <?= !empty($pricing_tiers) ? number_format($pricing_tiers[0]['price']) : number_format($product['base_price']) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-gray-400 uppercase tracking-widest">
                        <span>Total Quantity</span>
                        <span id="order-qty" class="text-gray-900"><?= $is_out_of_stock ? 0 : (int) $product['moq'] ?> units</span>
                    </div>
                    <div class="h-px bg-gray-200"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-900 uppercase tracking-widest">Subtotal</span>
                        <span id="subtotal" class="text-2xl font-extrabold text-brand font-sans">
                            <?php if ($can_see_prices): ?>
                                    LKR <span id="subtotal-val"><?= $is_out_of_stock ? 0 : number_format($product['moq'] * (!empty($pricing_tiers) ? $pricing_tiers[0]['price'] : $product['base_price'])) ?></span>
                            <?php else: ?>
                                    <span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none">LKR <?= number_format($product['moq'] * (!empty($pricing_tiers) ? $pricing_tiers[0]['price'] : $product['base_price'])) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-gray-400 leading-tight">VAT and island-wide delivery calculated at final checkout.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if ($buyer_approved): ?>
                            <!-- ✅ Approved buyer: show full action buttons -->
                            <button onclick="addToCart()" <?= $is_out_of_stock ? 'disabled' : '' ?>
                                class="<?= $is_out_of_stock ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-brand text-brand-light hover:bg-brand-dark active:scale-95 hover:-translate-y-px shadow-lg shadow-brand/20' ?> font-bold py-4 rounded-2xl transition-all transform flex items-center justify-center gap-2">
                                <i class="ti ti-shopping-cart-plus text-xl"></i>
                                <?= $is_out_of_stock ? 'Out of Stock' : 'Add to Order' ?>
                            </button>
                            <button onclick="requestQuote()"
                                class="bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl
                                   hover:bg-gray-50 hover:border-brand hover:text-brand transition-all
                                   transform hover:-translate-y-px active:scale-95">
                                Request Quote
                            </button>

                    <?php elseif ($is_logged_in && !$buyer_approved): ?>
                            <!-- ⏳ Logged in but account still pending/suspended -->
                            <div class="col-span-2 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200
                                    rounded-2xl text-amber-800">
                                <i class="ti ti-clock-hour-4 text-xl mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-sm font-bold">Account Pending Approval</p>
                                    <p class="text-xs font-medium mt-1 text-amber-700">
                                        Your wholesale account is currently under review by our administrative team. No further action is required on your part. We typically verify and approve accounts within 24 business hours. If you require urgent access, please <a href="/contact" class="underline hover:text-amber-900">contact us</a>.
                                    </p>
                                </div>
                            </div>

                    <?php else: ?>
                            <!-- 🔒 Guest: prompt to sign in or register -->
                            <a href="/login"
                                class="bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark
                                   transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20
                                   active:scale-95 flex items-center justify-center gap-2">
                                <i class="ti ti-lock text-xl"></i>
                                Sign In to Order
                            </a>
                            <a href="/login?mode=register"
                                class="bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl
                                   hover:bg-gray-50 hover:border-brand hover:text-brand transition-all
                                   transform hover:-translate-y-px active:scale-95 flex items-center justify-center gap-2">
                                <i class="ti ti-user-plus text-xl"></i>
                                Apply for Wholesale
                            </a>
                    <?php endif; ?>
                </div>

                <!-- Trust Badges -->
                <div class="flex justify-between mt-8">
                    <div class="flex flex-col items-center gap-2 text-[9px] font-bold text-gray-400 uppercase tracking-widest">
                        <i class="ti ti-truck text-xl text-brand"></i>
                        <span>Islandwide Delivery</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-[9px] font-bold text-gray-400 uppercase tracking-widest">
                        <i class="ti ti-certificate text-xl text-brand"></i>
                        <span>Quality Assured</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-[9px] font-bold text-gray-400 uppercase tracking-widest">
                        <i class="ti ti-receipt text-xl text-brand"></i>
                        <span>VAT Invoices</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
const tiers = <?php
$js_tiers = [];
foreach ($pricing_tiers as $t) {
    $js_tiers[] = [
        'min' => (int) $t['min_qty'],
        'max' => $t['max_qty'] !== null ? (int) $t['max_qty'] : 999999,
        'price' => (float) $t['price']
    ];
}
if (empty($js_tiers)) {
    $js_tiers[] = [
        'min' => (int) $product['moq'],
        'max' => 999999,
        'price' => (float) $product['base_price']
    ];
}
echo json_encode($js_tiers);
?>;
const discount = <?= (float) $discount ?>;
const moq = <?= (int) $product['moq'] ?>;
const canSeePrices = <?= $can_see_prices ? 'true' : 'false' ?>;
let qty = <?= $is_out_of_stock ? 0 : (int) $product['moq'] ?>;
const sizeOptions = <?php echo json_encode($size_options); ?>;
const availableSizes = <?php echo json_encode($sizes); ?>;
const defaultSize = '<?= htmlspecialchars($default_size) ?>';
const colorsArray = <?php echo json_encode($colours); ?>;
const defaultColor = '<?= htmlspecialchars($default_colour) ?>';

// Initialize nested quantities: selectedQuantities[color][size]
let selectedQuantities = {};
colorsArray.forEach(c => {
    selectedQuantities[c] = {};
    sizeOptions.forEach(s => {
        selectedQuantities[c][s] = 0;
    });
});
if (selectedQuantities[defaultColor]) {
    selectedQuantities[defaultColor][defaultSize] = qty;
}

function getTier(q) {
  return tiers.find(t => q >= t.min && q <= t.max) || tiers[0];
}

function updateUI() {
  const tier = getTier(qty);
  const isOutOfStock = <?= $is_out_of_stock ? 'true' : 'false' ?>;
  const belowMOQ = !isOutOfStock && qty < moq;
  
  let activePrice = tier.price;
  if (discount > 0) {
      activePrice = activePrice * (1 - (discount / 100));
  }
  
  document.getElementById('qty-display').textContent = qty;
  document.getElementById('order-qty').textContent = qty + ' units';
  if (canSeePrices) {
      document.getElementById('unit-price').textContent = 'LKR ' + activePrice.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
      document.getElementById('subtotal').textContent = 'LKR ' + (qty * activePrice).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
      
      // Update size price display inside list
      document.querySelectorAll('.size-price-display').forEach(el => {
          el.textContent = activePrice.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
      });
  } else {
      document.getElementById('unit-price').innerHTML = '<span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none">LKR ' + activePrice.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + '</span>';
      document.getElementById('subtotal').innerHTML = '<span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none">LKR ' + (qty * activePrice).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + '</span>';
  }
  
  const warn = document.getElementById('moq-warn');
  if(belowMOQ) {
      warn.classList.remove('hidden');
      warn.classList.add('flex');
  } else {
      warn.classList.add('hidden');
      warn.classList.remove('flex');
  }

  // Update Tier Visuals dynamically for elements that match tiers index
  for (let i = 1; i <= tiers.length; i++) {
      const el = document.getElementById('tier-' + i);
      if (el) {
          const currentTierIndex = tiers.indexOf(tier);
          if (i === currentTierIndex + 1 && !belowMOQ) {
              el.classList.add('bg-brand-light/30', 'border-brand/10', 'text-brand');
              el.classList.remove('bg-white', 'text-gray-500');
              const lastChild = el.querySelector('span:last-child');
              if (lastChild) {
                  lastChild.classList.add('text-brand', 'font-extrabold');
                  lastChild.classList.remove('text-gray-900');
              }
          } else {
              el.classList.remove('bg-brand-light/30', 'border-brand/10', 'text-brand');
              el.classList.add('bg-white', 'text-gray-500');
              const lastChild = el.querySelector('span:last-child');
              if (lastChild) {
                  lastChild.classList.remove('text-brand', 'font-extrabold');
                  lastChild.classList.add('text-gray-900');
              }
          }
      }
  }
}

function changeQty(delta) {
  if (selectedSize && selectedColor) {
      const currentVal = (selectedQuantities[selectedColor] && selectedQuantities[selectedColor][selectedSize]) || 0;
      const newVal = Math.max(0, currentVal + delta);
      selectedQuantities[selectedColor][selectedSize] = newVal;
      
      const input = document.getElementById('size-qty-' + selectedSize);
      if (input) {
          input.value = newVal;
          updateSizeInputStyles(selectedSize, newVal);
      }
      
      recalculateTotalQty();
  }
}

function changeSizeQty(size, delta) {
    if (selectedColor) {
        const input = document.getElementById('size-qty-' + size);
        if (input) {
            let val = (selectedQuantities[selectedColor] && selectedQuantities[selectedColor][size]) || 0;
            val = Math.max(0, val + delta);
            input.value = val;
            selectedQuantities[selectedColor][size] = val;
            updateSizeInputStyles(size, val);
            recalculateTotalQty();
        }
    }
}

function onSizeQtyChange(size) {
    if (selectedColor) {
        const input = document.getElementById('size-qty-' + size);
        if (input) {
            let val = parseInt(input.value) || 0;
            val = Math.max(0, val);
            input.value = val;
            selectedQuantities[selectedColor][size] = val;
            updateSizeInputStyles(size, val);
            recalculateTotalQty();
        }
    }
}

function updateSizeInputStyles(size, val) {
    const input = document.getElementById('size-qty-' + size);
    if (input) {
        if (val > 0) {
            input.style.backgroundColor = "#ff6600";
            input.style.color = "#ffffff";
            input.className = "w-12 text-center text-xs font-black rounded-lg outline-none focus:ring-0 transition-all duration-300 py-1";
        } else {
            input.style.backgroundColor = "transparent";
            input.style.color = "#111827";
            input.className = "w-12 text-center text-xs font-black border-none outline-none focus:ring-0 transition-all duration-300 py-1";
        }
    }
}

function recalculateTotalQty() {
    let total = 0;
    for (let c in selectedQuantities) {
        for (let s in selectedQuantities[c]) {
            total += selectedQuantities[c][s] || 0;
        }
    }
    qty = total;
    updateUI();
    updateColorBadges();
}

function updateColorBadges() {
    for (let c in selectedQuantities) {
        let colorTotal = 0;
        for (let s in selectedQuantities[c]) {
            colorTotal += selectedQuantities[c][s] || 0;
        }
        const badge = document.getElementById('color-qty-badge-' + c);
        if (badge) {
            if (colorTotal > 0) {
                badge.textContent = 'x' + colorTotal;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }
}

let selectedColor = '<?= htmlspecialchars($default_colour) ?>';
let selectedSize = '<?= htmlspecialchars($default_size) ?>';

function selectColor(idx, color) {
    selectedColor = color;
    document.getElementById('selected-color-name').textContent = color;
    
    document.querySelectorAll('.color-select-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-brand', 'ring-offset-2');
        btn.classList.add('hover:ring-2', 'hover:ring-gray-300');
    });
    const btn = document.getElementById('color-btn-' + idx);
    if (btn) {
        btn.classList.add('ring-2', 'ring-brand', 'ring-offset-2');
        btn.classList.remove('hover:ring-2', 'hover:ring-gray-300');
    }
    
    // Load saved quantities for the newly selected color into the size fields
    sizeOptions.forEach(size => {
        const sizeVal = (selectedQuantities[color] && selectedQuantities[color][size]) || 0;
        const input = document.getElementById('size-qty-' + size);
        if (input) {
            input.value = sizeVal;
            updateSizeInputStyles(size, sizeVal);
        }
    });
}

function selectSize(idx, size) {
    selectedSize = size;
    document.querySelectorAll('.size-row-el').forEach(row => {
        row.classList.remove('bg-brand-light/10');
    });
    
    const activeRow = document.getElementById('size-row-card-' + size);
    if (activeRow) {
        activeRow.classList.add('bg-brand-light/10');
    }
}

function addToCart() {
    if (qty < moq) {
        uiAlert("Minimum Order Quantity is " + moq + " units in total across all selections.");
        return;
    }
    const productId = <?= (int) $product['id'] ?>;
    let saved = localStorage.getItem('kesara_cart');
    let cart = [];
    if (saved) {
        try { cart = JSON.parse(saved); } catch(e){}
    }
    
    let addedCount = 0;
    for (let c in selectedQuantities) {
        for (let s in selectedQuantities[c]) {
            const sizeQty = selectedQuantities[c][s] || 0;
            if (sizeQty > 0) {
                let existing = cart.find(i => i.id === productId && i.color === c && i.size === s);
                if (existing) {
                    existing.qty += sizeQty;
                } else {
                    cart.push({ id: productId, qty: sizeQty, color: c, size: s });
                }
                addedCount++;
            }
        }
    }

    if (addedCount === 0) {
        uiAlert("Please select a quantity for at least one color and size combination.");
        return;
    }
    
    localStorage.setItem('kesara_cart', JSON.stringify(cart));
    
    // Redirect to cart
    window.location.href = '/cart.php';
}

function requestQuote() {
    const productName = "<?= htmlspecialchars($product['name']) ?>";
    const quantity = qty;
    
    let details = [];
    for (let c in selectedQuantities) {
        for (let s in selectedQuantities[c]) {
            const sizeQty = selectedQuantities[c][s] || 0;
            if (sizeQty > 0) {
                details.push(`${sizeQty} units of ${c} (Size ${s})`);
            }
        }
    }
    
    const summary = details.join(', ');
    
    // Set pre-filled message
    document.getElementById('quoteMessage').value = `Hello, I would like to request a wholesale price quote for a total of ${quantity} units of ${productName} (${summary}). Please let us know the availability and best tiered pricing.`;
    
    // Open modal
    document.getElementById('quoteModal').classList.remove('hidden');
    document.getElementById('quoteModal').classList.add('flex');
}

// Initial badge render on load
updateColorBadges();
sizeOptions.forEach(s => {
    const sizeVal = (selectedQuantities[defaultColor] && selectedQuantities[defaultColor][s]) || 0;
    updateSizeInputStyles(s, sizeVal);
});

function closeQuoteModal() {
    document.getElementById('quoteModal').classList.add('hidden');
    document.getElementById('quoteModal').classList.remove('flex');
}

function submitQuoteRequest(e) {
    e.preventDefault();
    const btn = document.getElementById('submitQuoteBtn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    const data = {
        name: document.getElementById('quoteName').value,
        business_name: document.getElementById('quoteBusiness').value,
        email: document.getElementById('quoteEmail').value,
        phone: document.getElementById('quotePhone').value,
        inquiry_type: 'Wholesale Quote',
        message: document.getElementById('quoteMessage').value
    };

    fetch('api/inquiries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resData => {
        if (resData.status === 'success') {
            showToast('Your quote request has been submitted successfully! Our wholesale team will contact you shortly.', 'success');
            closeQuoteModal();
        } else {
            uiAlert('Error: ' + (resData.message || 'Validation failed.'));
        }
    })
    .catch(err => {
        console.error(err);
        uiAlert('Network error submitting quote request.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Submit Request';
    });
}

// Initial render
updateUI();
</script>

<!-- Quote Request Modal -->
<div id="quoteModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 items-center justify-center p-4 hidden">
    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Request Wholesale Quote</h2>
        <p class="text-xs text-gray-500 mb-6">Enter your details and our team will get back to you with custom pricing.</p>
        
        <form onsubmit="submitQuoteRequest(event)" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Contact Name *</label>
                <input type="text" id="quoteName" required placeholder="e.g. Kamal Perera" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Business Name</label>
                    <input type="text" id="quoteBusiness" placeholder="e.g. ABC Retailers" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Phone Number</label>
                    <input type="text" id="quotePhone" placeholder="e.g. +94 77 123 4567" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Email Address *</label>
                <input type="email" id="quoteEmail" required placeholder="e.g. contact@business.com" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Quote Request Message *</label>
                <textarea id="quoteMessage" rows="3" required class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold outline-none focus:ring-2 focus:ring-brand/20 resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeQuoteModal()" class="px-6 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="submitQuoteBtn" class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
