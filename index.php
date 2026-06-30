<?php
require_once __DIR__ . "/database/connection.php";

$categories = [];
$featured_products = [];

if ($pdo) {
    try {
        // Fetch categories with style counts
        $stmt = $pdo->query("SELECT c.id, c.name, c.slug, c.icon, c.image, COUNT(p.id) AS style_count 
                             FROM categories c 
                             LEFT JOIN products p ON p.category_id = c.id 
                             GROUP BY c.id");
        $categories = $stmt->fetchAll();

        // Fetch featured products (first 4 latest products)
        $stmt = $pdo->query("SELECT p.id, p.name, p.sku, p.moq, p.base_price, p.status, p.images 
                             FROM products p 
                             WHERE p.deleted_at IS NULL
                             ORDER BY p.id DESC
                             LIMIT 4");
        $featured_products = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Silently catch database query issues
    }
}



$page_meta = [
    'title' => 'Kesara Enterprises | Wholesale Underwear Supplier Sri Lanka',
    'description' => 'Quality innerwear supplied in bulk to local retailers, supermarkets, and boutiques across Sri Lanka.',
];
require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main>
    <!-- HERO SECTION -->
    <section
        style="background-image: url('/assets/images/hero/hero.jpg'); background-size: cover; background-position: center;"
        class="py-16 border-b border-gray-100 relative z-0">

        <!-- Overlay to make text readable -->
        <div class="absolute inset-0 z-0" style="background: linear-gradient(to right, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.3) 50%, rgba(0, 0, 0, 0) 80%);"></div>

        <div class="max-w-8xl mx-auto px-6 md:px-12 relative z-10">
            <span class="text-[10px] font-medium tracking-wider text-gray-300 uppercase mb-1.5 block">Wholesale
                underwear supplier</span>
            <h1 class="text-4xl md:text-6xl font-bold text-gray-100 mb-6 max-w-4xl leading-tight">
                Quality innerwear, supplied in bulk to local retailers
            </h1>
            <p class="text-lg md:text-xl text-gray-200 mb-10 max-w-2xl leading-relaxed">
                Kesara Enterprises supplies briefs, boxers, trunks and ladies wear to supermarkets, boutiques, and
                distributors across Sri Lanka. Minimum orders from 50 units.
            </p>
            <div class="flex flex-wrap gap-4 mb-16">
                <a href="/catalog"
                    class="bg-brand text-brand-light py-2.5 px-6 rounded-md text-sm font-semibold hover:bg-brand-dark transition-all transform hover:-translate-y-px">Browse
                    catalog</a>
                <a href="/register"
                    class="bg-white/30 backdrop-blur-lg text-gray-300 border border-gray-300 py-2.5 px-6 rounded-md text-sm font-medium hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px">Request
                    wholesale access</a>
            </div>

            <div class="flex gap-12 md:gap-24 flex-wrap">
                <div>
                    <p class="text-3xl font-bold text-brand">50+</p>
                    <p class="text-sm text-gray-200 font-medium">Product SKUs</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-brand">200+</p>
                    <p class="text-sm text-gray-200 font-medium">Active buyers</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-brand">10+ yrs</p>
                    <p class="text-sm text-gray-200 font-medium">In business</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUCT CATEGORIES -->
    <section id="categories" class="py-20 bg-white border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">Shop by
                category</span>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-10">What are you looking for?</h2>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                <?php foreach ($categories as $cat): ?>
                <a href="/catalog?category=<?= htmlspecialchars($cat['slug']) ?>"
                    class="bg-white rounded-md p-6 text-center border border-gray-100 cursor-pointer transition-all hover:border-brand hover:shadow-lg hover:-translate-y-0.5 flex flex-col items-center justify-center">
                    <?php if (!empty($cat['image'])): ?>
                        <div class="w-16 h-16 mb-3 rounded-full overflow-hidden border border-gray-100 flex items-center justify-center bg-gray-50">
                            <img src="<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <i class="ti <?= htmlspecialchars($cat['icon'] ?? 'ti-tag') ?> text-3xl text-brand mb-3"></i>
                    <?php endif; ?>
                    <p class="text-[15px] font-semibold text-gray-900 mb-1"><?= htmlspecialchars($cat['name']) ?></p>
                    <p class="text-[12px] text-gray-400"><?= $cat['style_count'] ?> styles</p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FEATURED PRODUCTS -->
    <section id="products" class="py-20 bg-gray-50 border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">Featured
                        products</span>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Best sellers this season</h2>
                </div>
                <a href="/catalog"
                    class="bg-transparent text-gray-900 border border-gray-300 py-2 px-6 rounded-md text-sm font-medium hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px">View
                    all</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($featured_products as $p): ?>
                <a href="/product?sku=<?= htmlspecialchars($p['sku']) ?>"
                    class="bg-white border border-gray-100 rounded-lg overflow-hidden transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="bg-gray-50 h-40 flex items-center justify-center border-b border-gray-100 overflow-hidden relative">
                        <?php 
                        $prod_images = json_decode($p['images'] ?? '[]', true);
                        if (!empty($prod_images) && !empty($prod_images[0])): 
                        ?>
                            <img src="<?= htmlspecialchars($prod_images[0]) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="ti ti-shirt text-5xl text-gray-300"></i>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <p class="text-[15px] font-semibold mb-1"><?= htmlspecialchars($p['name']) ?></p>
                        <p class="text-[12px] text-gray-400 mb-2">MOQ: <?= $p['moq'] ?> pcs</p>
                        <p class="text-[15px] text-brand font-bold">LKR <?= number_format($p['base_price'], 2) ?>/pc</p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="mt-12 text-center">
                <p class="text-sm text-gray-500">
                    <i class="ti ti-lock mr-1"></i> Full pricing visible after account approval
                </p>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="py-20 bg-white border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">How it
                works</span>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-12">Simple wholesale ordering process</h2>

            <div class="flex flex-col gap-6">
                <div class="flex gap-6 items-start">
                    <div
                        class="w-9 h-9 rounded-full bg-brand-light flex items-center justify-center text-base font-semibold text-brand shrink-0">
                        1</div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Register your business</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Submit your company name, BR number, and contact details. Approval takes 1–2 business days.
                        </p>
                    </div>
                </div>
                <div class="flex gap-6 items-start">
                    <div
                        class="w-9 h-9 rounded-full bg-brand-light flex items-center justify-center text-base font-semibold text-brand shrink-0">
                        2</div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Browse and place order</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Access the full catalog with wholesale pricing. Select variants, quantities, and add to
                            cart.
                        </p>
                    </div>
                </div>
                <div class="flex gap-6 items-start">
                    <div
                        class="w-9 h-9 rounded-full bg-brand-light flex items-center justify-center text-base font-semibold text-brand shrink-0">
                        3</div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Confirm and delivery</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Place your order, receive a tax invoice, and we'll arrange delivery to your location.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- WHY CHOOSE US -->
    <section class="py-20 bg-gray-50 border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">Why choose
                us</span>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-12">Built for Sri Lankan wholesale buyers</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white border border-gray-100 rounded-md p-5 flex gap-4 items-start">
                    <i class="ti ti-package text-2xl text-brand shrink-0"></i>
                    <div>
                        <p class="text-[15px] font-semibold text-gray-900 mb-1.5">Low minimum orders</p>
                        <p class="text-[13px] text-gray-600 leading-relaxed">Start from just 50 units per style. Scale
                            as your business grows.</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-100 rounded-md p-5 flex gap-4 items-start">
                    <i class="ti ti-certificate text-2xl text-brand shrink-0"></i>
                    <div>
                        <p class="text-[15px] font-semibold text-gray-900 mb-1.5">Quality guaranteed</p>
                        <p class="text-[13px] text-gray-600 leading-relaxed">All products meet local fabric and
                            stitching standards.</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-100 rounded-md p-5 flex gap-4 items-start">
                    <i class="ti ti-truck text-2xl text-brand shrink-0"></i>
                    <div>
                        <p class="text-[15px] font-semibold text-gray-900 mb-1.5">Island-wide delivery</p>
                        <p class="text-[13px] text-gray-600 leading-relaxed">We deliver across all 9 provinces of Sri
                            Lanka.</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-100 rounded-md p-5 flex gap-4 items-start">
                    <i class="ti ti-receipt text-2xl text-brand shrink-0"></i>
                    <div>
                        <p class="text-[15px] font-semibold text-gray-900 mb-1.5">Tax invoices issued</p>
                        <p class="text-[13px] text-gray-600 leading-relaxed">Every order comes with a proper VAT invoice
                            for your accounts.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT & ABOUT -->
    <section id="contact" class="py-20 bg-white">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div>
                    <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">Get in
                        touch</span>
                    <h2 class="text-3xl font-bold text-gray-900 mb-8">Contact us before you register</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-10">
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-100">
                            <i class="ti ti-phone text-2xl text-[#1D9E75]"></i>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Phone</p>
                                <p class="font-bold text-gray-900">+94 11 234 5678</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-100">
                            <i class="ti ti-mail text-2xl text-[#1D9E75]"></i>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Email</p>
                                <p class="font-bold text-gray-900">sales@kesara.lk</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-100">
                            <i class="ti ti-map-pin text-2xl text-[#1D9E75]"></i>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Address</p>
                                <p class="font-bold text-gray-900">Colombo 10, Sri Lanka</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-100">
                            <i class="ti ti-clock text-2xl text-[#1D9E75]"></i>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Hours</p>
                                <p class="font-bold text-gray-900">Mon–Fri, 8am–5pm</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-10 bg-brand-light rounded-3xl relative overflow-hidden" style="background-image: url('/assets/images/Rastaman/rasthaman_15.jpg'); background-size: cover; background-position: center;">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative z-10">
                        <span class="text-[10px] font-medium tracking-wider text-gray-400 uppercase mb-1.5 block">About
                            us</span>
                        <h2 class="text-2xl font-bold text-gray-200 mb-6">A trusted name in local wholesale</h2>
                        <p class="text-gray-300 leading-relaxed mb-8">
                            Kesara Enterprises has been supplying quality innerwear to Sri Lankan retailers since 2012.
                            We work exclusively with registered businesses and pride ourselves on consistent stock and
                            honest pricing.
                        </p>
                        <a href="/about"
                            class="inline-block bg-brand text-brand-light py-2.5 px-6 rounded-md text-sm font-semibold hover:bg-brand-dark transition-all transform hover:-translate-y-px">Our
                            story</a>
                    </div>
                    <i class="ti ti-building-warehouse absolute -bottom-10 -right-10 text-[200px] text-white/20"></i>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>