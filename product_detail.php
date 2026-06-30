<?php
require_once __DIR__ . "/database/connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in   = isset($_SESSION['user_id']);
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

// Fallback to mock data if product is not found or database is offline
if (!$product) {
    $product = [
        'id' => 1,
        'name' => 'Classic Cotton Brief',
        'sku' => 'KB-001',
        'description' => "Classic cut men's brief made from soft combed cotton. Suitable for all-day wear. Available in solid colours. Ideal for retail bundles and supermarket stocking.",
        'moq' => 50,
        'base_price' => 108.00,
        'status' => 'In Stock'
    ];
    $category_name = "Briefs";
    
    $pricing_tiers = [
        ['min_qty' => 50, 'max_qty' => 99, 'price' => 120.00],
        ['min_qty' => 100, 'max_qty' => 499, 'price' => 108.00],
        ['min_qty' => 500, 'max_qty' => null, 'price' => 95.00]
    ];
    
    $variations = [
        ['size' => 'S', 'colour' => 'White', 'quantity' => 10],
        ['size' => 'M', 'colour' => 'White', 'quantity' => 10],
        ['size' => 'L', 'colour' => 'White', 'quantity' => 10],
        ['size' => 'XL', 'colour' => 'White', 'quantity' => 10],
        ['size' => 'M', 'colour' => 'Black', 'quantity' => 10],
        ['size' => 'L', 'colour' => 'Black', 'quantity' => 10]
    ];
}

// Parse JSON specs if we added them in future, for now use empty or generic
$prod_specs = [
    'material' => 'Cotton Blend', 
    'gsm' => !empty($product['gsm']) ? $product['gsm'] : '180 GSM', 
    'waistband' => !empty($product['waistband']) ? $product['waistband'] : 'Elastic', 
    'packaging' => 'Bulk pack', 
    'lead' => '3–5 Business Days'
];
// Extract discount
$discount = isset($product['discount']) ? (float)$product['discount'] : 0;

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
function getColorClass($colorName) {
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
                    <span class="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-bold rounded-full border border-green-100 flex items-center gap-1.5 uppercase">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
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
                <div class="space-y-4 mb-8">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Wholesale Pricing Tiers</label>
                    <div class="border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
                        <?php $tier_idx = 1; foreach ($pricing_tiers as $index => $t): ?>
                        <?php 
                            // Determine if this is the default middle tier for active class (e.g. 2nd tier or 1st tier if count is small)
                            $is_active = (count($pricing_tiers) > 1 && $index === 1) || (count($pricing_tiers) === 1 && $index === 0);
                        ?>
                        <div class="flex justify-between items-center p-4 text-sm <?= $is_active ? 'bg-brand-light/30 border-y border-brand/10 text-brand' : 'bg-white text-gray-500' ?>" id="tier-<?= $tier_idx++ ?>">
                            <div class="flex items-center gap-3">
                                <span class="<?= $is_active ? 'font-bold' : '' ?>">
                                    <?= htmlspecialchars($t['min_qty']) ?><?= $t['max_qty'] ? ' – ' . htmlspecialchars($t['max_qty']) : '+' ?> units
                                </span>
                                <?php if ($is_active): ?>
                                <span class="px-2 py-0.5 bg-brand text-brand-light text-[9px] font-bold rounded-full">YOUR QTY</span>
                                <?php endif; ?>
                            </div>
                            <span class="<?= $is_active ? 'text-brand font-extrabold' : 'text-gray-900 font-bold' ?>">
                                <?php if ($discount > 0): ?>
                                    <span class="text-gray-400 line-through text-xs mr-2">LKR <?= number_format($t['price']) ?></span>
                                    LKR <?= number_format($t['price'] * (1 - $discount / 100)) ?> / pc
                                <?php else: ?>
                                    LKR <?= number_format($t['price']) ?> / pc
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
                                <?php if ($discount > 0): ?>
                                    <span class="text-gray-400 line-through text-xs mr-2">LKR <?= number_format($product['base_price']) ?></span>
                                    LKR <?= number_format($product['base_price'] * (1 - $discount / 100)) ?> / pc
                                <?php else: ?>
                                    LKR <?= number_format($product['base_price']) ?> / pc
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attributes: Color & Size -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div class="space-y-4">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Colour</label>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($colours as $c): ?>
                            <button id="color-btn-<?= $idx ?>" type="button" onclick="selectColor(<?= $idx ?>, '<?= htmlspecialchars($c) ?>')" class="color-select-btn w-8 h-8 rounded-full <?= getColorClass($c) ?> <?= $is_selected ? 'ring-2 ring-brand ring-offset-2' : 'hover:ring-2 hover:ring-gray-300' ?> ring-offset-2 transition-all" title="<?= htmlspecialchars($c) ?>"></button>
                            <?php endforeach; ?>
                            
                            <?php if (empty($colours)): ?>
                            <button class="w-8 h-8 rounded-full bg-white border border-gray-200 ring-2 ring-brand ring-offset-2 ring-offset-2 transition-all" title="Standard"></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Size</label>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                                $size_options = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                foreach ($size_options as $idx => $so):
                                    $is_available = in_array($so, $sizes) || empty($sizes);
                                    $is_selected = ($so === $default_size);
                                    
                                    if (!$is_available):
                            ?>
                            <button type="button" class="size-select-btn w-9 h-8 rounded-lg flex items-center justify-center text-[11px] font-bold bg-gray-50 text-gray-300 cursor-not-allowed line-through"><?= htmlspecialchars($so) ?></button>
                            <?php elseif ($is_selected): ?>
                            <button id="size-btn-<?= $idx ?>" type="button" onclick="selectSize(<?= $idx ?>, '<?= htmlspecialchars($so) ?>')" class="size-select-btn w-9 h-8 rounded-lg flex items-center justify-center text-[11px] font-bold bg-brand text-brand-light shadow-sm shadow-brand/20"><?= htmlspecialchars($so) ?></button>
                            <?php else: ?>
                            <button id="size-btn-<?= $idx ?>" type="button" onclick="selectSize(<?= $idx ?>, '<?= htmlspecialchars($so) ?>')" class="size-select-btn w-9 h-8 rounded-lg flex items-center justify-center text-[11px] font-bold border border-gray-100 hover:border-brand hover:text-brand transition-colors"><?= htmlspecialchars($so) ?></button>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quantity Control -->
                <div class="space-y-4 mb-8">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Order Quantity <span class="text-gray-300 font-medium lowercase tracking-normal">(min. <?= htmlspecialchars($product['moq']) ?>)</span></label>
                    <div class="flex items-center gap-6">
                        <div class="flex items-center bg-gray-50 border border-gray-100 rounded-xl overflow-hidden group focus-within:ring-2 focus-within:ring-brand/20 transition-all">
                            <button onclick="changeQty(-10)" class="w-12 h-12 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-minus text-sm"></i></button>
                            <span id="qty-display" class="w-16 text-center text-sm font-bold text-gray-900"><?= (int)$product['moq'] ?></span>
                            <button onclick="changeQty(10)" class="w-12 h-12 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-plus text-sm"></i></button>
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
                        <span id="unit-price" class="text-gray-900">LKR <?= !empty($pricing_tiers) ? number_format($pricing_tiers[0]['price']) : number_format($product['base_price']) ?></span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-gray-400 uppercase tracking-widest">
                        <span>Total Quantity</span>
                        <span id="order-qty" class="text-gray-900"><?= (int)$product['moq'] ?> units</span>
                    </div>
                    <div class="h-px bg-gray-200"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-900 uppercase tracking-widest">Subtotal</span>
                        <span id="subtotal" class="text-2xl font-extrabold text-brand font-sans">LKR <?= number_format($product['moq'] * (!empty($pricing_tiers) ? $pricing_tiers[0]['price'] : $product['base_price'])) ?></span>
                    </div>
                    <p class="text-[10px] font-medium text-gray-400 leading-tight">VAT and island-wide delivery calculated at final checkout.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if ($buyer_approved): ?>
                        <!-- ✅ Approved buyer: show full action buttons -->
                        <button onclick="addToCart()"
                            class="bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark
                                   transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20
                                   active:scale-95 flex items-center justify-center gap-2">
                            <i class="ti ti-shopping-cart-plus text-xl"></i>
                            Add to Order
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
                                    Your wholesale account is under review. We typically approve within 24 hours.
                                    <a href="/contact" class="underline hover:text-amber-900">Contact us</a> if you need urgent access.
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
        'min' => (int)$t['min_qty'],
        'max' => $t['max_qty'] !== null ? (int)$t['max_qty'] : 999999,
        'price' => (float)$t['price']
    ];
}
if (empty($js_tiers)) {
    $js_tiers[] = [
        'min' => (int)$product['moq'],
        'max' => 999999,
        'price' => (float)$product['base_price']
    ];
}
echo json_encode($js_tiers);
?>;
const discount = <?= (float)$discount ?>;
const moq = <?= (int)$product['moq'] ?>;
let qty = <?= (int)$product['moq'] ?>;

function getTier(q) {
  return tiers.find(t => q >= t.min && q <= t.max) || tiers[0];
}

function updateUI() {
  const tier = getTier(qty);
  const belowMOQ = qty < moq;
  
  let activePrice = tier.price;
  if (discount > 0) {
      activePrice = activePrice * (1 - (discount / 100));
  }
  
  document.getElementById('qty-display').textContent = qty;
  document.getElementById('order-qty').textContent = qty + ' units';
  document.getElementById('unit-price').textContent = 'LKR ' + activePrice.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
  document.getElementById('subtotal').textContent = 'LKR ' + (qty * activePrice).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
  
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
  qty = Math.max(0, qty + delta);
  updateUI();
}

let selectedColor = '<?= htmlspecialchars($default_colour) ?>';
let selectedSize = '<?= htmlspecialchars($default_size) ?>';

function selectColor(idx, color) {
    selectedColor = color;
    document.querySelectorAll('.color-select-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-brand', 'ring-offset-2');
        btn.classList.add('hover:ring-2', 'hover:ring-gray-300');
    });
    const btn = document.getElementById('color-btn-' + idx);
    if (btn) {
        btn.classList.add('ring-2', 'ring-brand', 'ring-offset-2');
        btn.classList.remove('hover:ring-2', 'hover:ring-gray-300');
    }
}

function selectSize(idx, size) {
    selectedSize = size;
    document.querySelectorAll('.size-select-btn:not(.cursor-not-allowed)').forEach(btn => {
        btn.classList.remove('bg-brand', 'text-brand-light', 'shadow-sm', 'shadow-brand/20');
        btn.classList.add('border', 'border-gray-100', 'hover:border-brand', 'hover:text-brand');
    });
    const btn = document.getElementById('size-btn-' + idx);
    if (btn) {
        btn.classList.add('bg-brand', 'text-brand-light', 'shadow-sm', 'shadow-brand/20');
        btn.classList.remove('border', 'border-gray-100', 'hover:border-brand', 'hover:text-brand');
    }
}

function addToCart() {
    if (qty < moq) {
        alert("Minimum Order Quantity is " + moq);
        return;
    }
    const productId = <?= (int)$product['id'] ?>;
    let saved = localStorage.getItem('kesara_cart');
    let cart = [];
    if (saved) {
        try { cart = JSON.parse(saved); } catch(e){}
    }
    
    // Check if already in cart
    let existing = cart.find(i => i.id === productId && i.color === selectedColor && i.size === selectedSize);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({ id: productId, qty: qty, color: selectedColor, size: selectedSize });
    }
    
    localStorage.setItem('kesara_cart', JSON.stringify(cart));
    
    // Redirect to cart
    window.location.href = '/cart.php';
}

// Initial render
updateUI();
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
