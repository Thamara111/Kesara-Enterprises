<?php
require_once __DIR__ . "/database/connection.php";

$page_meta = [
    'title' => 'About Us | Kesara Enterprises - Premium Wholesale Innerwear',
    'description' => 'Learn about Kesara Enterprises, Sri Lanka\'s leading innerwear supplier since 2012. Our mission, values, and leadership team.',
];

require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 min-h-screen">
    <section
        style="background-image: url('/assets/images/hero/hero.jpg'); background-size: cover; background-position: center;"
        class="py-16 border-b border-gray-100 relative z-0">

        <!-- Overlay to make text readable -->
        <div class="absolute inset-0 z-0" style="background: linear-gradient(to right, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.3) 50%, rgba(0, 0, 0, 0) 80%);"></div>

        <div class="max-w-8xl mx-auto px-6 md:px-12 relative z-10 text-center max-w-3xl">
            <span class="text-[10px] font-bold tracking-widest text-brand-light/70 uppercase mb-3 block">Est. 2012</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white mb-6 leading-tight font-serif tracking-tight">
                Our Story & Commitment
            </h1>
            <p class="text-lg md:text-xl text-brand-light/90 leading-relaxed font-sans font-light">
                Providing high-quality innerwear and exceptional wholesale service to local retailers, supermarkets, and distributors across Sri Lanka.
            </p>
        </div>
    </section>

    <!-- OUR STORY SECTION -->
    <section class="py-20 bg-white border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12">
            <div class="grid lg:grid-cols-12 gap-12 items-center">
                <!-- Text Area -->
                <div class="lg:col-span-7 space-y-6">
                    <span class="text-[10px] font-bold tracking-widest text-brand uppercase block">Empowering Retailers</span>
                    <h2 class="text-3xl font-extrabold text-gray-900 leading-tight">
                        A Decades-Long Legacy of Quality and Trust
                    </h2>
                    <p class="text-gray-600 leading-relaxed text-[15px]">
                        Founded in 2012 by Nalin, Kesara Enterprises began with a simple mission: to make premium quality, comfortable innerwear accessible to retail businesses across Sri Lanka. Recognizing the gap in consistent wholesale supply, we built an integrated supply chain that values reliability, quality assurance, and fair, transparent pricing.
                    </p>
                    <p class="text-gray-600 leading-relaxed text-[15px]">
                        Over the past decade, we have grown from a local distributor to one of the island's most reliable innerwear suppliers. We cater to grocery chains, prominent supermarkets, small-town boutiques, and regional distributors, keeping minimum order quantities low (starting at 50 units) to help businesses of all sizes grow and succeed.
                    </p>
                    <p class="text-gray-600 leading-relaxed text-[15px]">
                        Every product we distribute—from classic cotton briefs and stretch boxers to breathable ladies' wear and children's essentials—goes through rigorous stitching and fabric quality control. We believe that good business is built on good relationships, honest terms, and consistent standards.
                    </p>
                </div>

                <!-- Stats/Visual Card Area -->
                <div class="lg:col-span-5 space-y-8">
                    <div class="absolute -bottom-8 -right-8 text-brand/5">
                        <i class="ti ti-building-warehouse text-[180px]"></i>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-900">Why Sri Lanka Chooses Kesara</h3>
                    
                    <div class="grid grid-cols-4 gap-6 relative z-10">
                        <div class="p-4 bg-white rounded-2xl shadow-sm border border-gray-100">
                            <p class="text-3xl font-extrabold text-brand mb-1">50+</p>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Premium SKUs</p>
                        </div>
                        <div class="p-4 bg-white rounded-2xl shadow-sm border border-gray-100">
                            <p class="text-3xl font-extrabold text-brand mb-1">200+</p>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Active Buyers</p>
                        </div>
                        <div class="p-4 bg-white rounded-2xl shadow-sm border border-gray-100">
                            <p class="text-3xl font-extrabold text-brand mb-1">100%</p>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Quality Tested</p>
                        </div>
                        <div class="p-4 bg-white rounded-2xl shadow-sm border border-gray-100">
                            <p class="text-3xl font-extrabold text-brand mb-1">9</p>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Provinces Served</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MISSION & VISION -->
    <section class="py-20 bg-gray-50 border-b border-gray-100">
        <div class="max-w-8xl mx-auto px-6 md:px-12 text-center mb-16">
            <span class="text-[10px] font-bold tracking-widest text-brand uppercase block mb-3">Our Foundation</span>
            <h2 class="text-3xl font-extrabold text-gray-900">Mission, Vision & Core Values</h2>
        </div>

        <div class="max-w-8xl mx-auto px-6 md:px-12 grid md:grid-cols-3 gap-8">
            <!-- Card 1 -->
            <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all group hover:-translate-y-1">
                <div class="w-12 h-12 bg-brand-light rounded-xl flex items-center justify-center text-brand mb-6 group-hover:scale-110 transition-transform">
                    <i class="ti ti-target text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Our Mission</h3>
                <p class="text-gray-600 leading-relaxed text-sm">
                    To deliver reliable, high-quality innerwear products that combine modern comfort and durability, supporting retailers with flexible bulk purchasing options and honest, transparent partnerships.
                </p>
            </div>

            <!-- Card 2 -->
            <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all group hover:-translate-y-1">
                <div class="w-12 h-12 bg-brand-light rounded-xl flex items-center justify-center text-brand mb-6 group-hover:scale-110 transition-transform">
                    <i class="ti ti-eye text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Our Vision</h3>
                <p class="text-gray-600 leading-relaxed text-sm">
                    To become the premier choice for innerwear distribution in Sri Lanka, setting local industry benchmarks for supply chain consistency, exceptional customer service, and absolute product integrity.
                </p>
            </div>

            <!-- Card 3 -->
            <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all group hover:-translate-y-1">
                <div class="w-12 h-12 bg-brand-light rounded-xl flex items-center justify-center text-brand mb-6 group-hover:scale-110 transition-transform">
                    <i class="ti ti-heart text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Our Core Values</h3>
                <p class="text-gray-600 leading-relaxed text-sm">
                    Striving for excellence with customer-centric support, strict quality assurance, transparent terms, and supporting local communities through fair practices and domestic fabric choices.
                </p>
            </div>
        </div>
    </section>

    <!-- MEET THE TEAM SECTION -->
    <section class="py-20 bg-white">
        <div class="max-w-8xl mx-auto px-6 md:px-12 text-center mb-16">
            <span class="text-[10px] font-bold tracking-widest text-brand uppercase block mb-3">Our People</span>
            <h2 class="text-3xl font-extrabold text-gray-900">Meet the Leadership Team</h2>
            <p class="text-gray-500 max-w-xl mx-auto mt-3 text-[15px]">
                The dedicated professionals behind Kesara Enterprises, driving supply consistency and quality control every single day.
            </p>
        </div>

        <div class="max-w-8xl mx-auto px-6 md:px-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-12">
            <!-- Founder -->
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all group text-center">
                <div class="aspect-[4/5] bg-gray-50 relative overflow-hidden">
                    <img src="/assets/images/team_founder.png" alt="Nalin - Founder" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">Nalin</h3>
                    <p class="text-xs font-bold text-brand uppercase tracking-wider mb-4">Founder</p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Nalin established Kesara Enterprises in 2012, bringing years of textile trading expertise and structural vision to local distribution.
                    </p>
                </div>
            </div>

            <!-- CEO -->
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all group text-center">
                <div class="aspect-[4/5] bg-gray-50 relative overflow-hidden">
                    <img src="/assets/images/team_ceo.png" alt="Wasantha Perera - CEO" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">Wasantha Perera</h3>
                    <p class="text-xs font-bold text-brand uppercase tracking-wider mb-4">Chief Executive Officer</p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Wasantha manages our overall corporate strategy, partnership expansion, and coordinates bulk distribution networks across Sri Lanka.
                    </p>
                </div>
            </div>

            <!-- Sales Director -->
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all group text-center sm:col-span-2 lg:col-span-1 max-w-sm mx-auto sm:max-w-none w-full">
                <div class="aspect-[4/5] bg-gray-50 relative overflow-hidden">
                    <img src="/assets/images/team_sales.png" alt="Dilini Jayasekara - Sales Director" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">Dilini Jayasekara</h3>
                    <p class="text-xs font-bold text-brand uppercase tracking-wider mb-4">Sales & Accounts Director</p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Dilini handles retailer relations, customer onboarding, and leads our customer support team to resolve questions efficiently.
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
