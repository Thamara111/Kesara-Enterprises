<nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-sm relative">
    <div class="max-w-8xl mx-auto px-6 py-3 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-[#E1F5EE] flex items-center justify-center">
                <i class="ti ti-building-store text-[#0F6E56] text-xl"></i>
            </div>
            <span class="text-lg font-bold text-gray-900 tracking-tight">Kesara Enterprises</span>
        </a>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center gap-8">
            <a href="/catalog" class="text-sm font-medium text-gray-600 hover:text-brand transition-colors cursor-pointer">Products</a>
            <a href="/#about" class="text-sm font-medium text-gray-600 hover:text-brand transition-colors cursor-pointer">About</a>
            <a href="/#contact" class="text-sm font-medium text-gray-600 hover:text-brand transition-colors cursor-pointer">Contact</a>
            <div class="w-px h-6 bg-gray-100"></div>
            <a href="/cart" class="relative group p-2">
                <i class="ti ti-shopping-cart text-xl text-gray-600 group-hover:text-brand transition-colors"></i>
                <span class="absolute top-0 right-0 w-4 h-4 bg-brand text-brand-light text-[9px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm">3</span>
            </a>
            <a href="/account" class="text-sm font-medium text-gray-600 hover:text-brand transition-colors cursor-pointer">Account</a>
            <a href="/login" class="bg-brand text-brand-light text-sm font-semibold py-2 px-6 rounded-md hover:bg-brand-dark transition-all transform hover:-translate-y-px">Sign in</a>
        </div>

        <!-- Mobile: Cart + Hamburger -->
        <div class="flex items-center md:hidden gap-3">
            <a href="/cart" class="relative group p-2">
                <i class="ti ti-shopping-cart text-xl text-gray-650"></i>
                <span class="absolute top-0 right-0 w-4 h-4 bg-brand text-brand-light text-[9px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm">3</span>
            </a>
            <button id="mobile-menu-toggle" class="p-2 text-gray-600 hover:text-brand transition-colors focus:outline-none" aria-label="Toggle Navigation">
                <i class="ti ti-menu-2 text-2xl" id="menu-icon"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Dropdown Menu -->
    <div id="mobile-menu" class="hidden md:hidden absolute top-full left-0 w-full bg-white border-b border-gray-100 shadow-lg px-6 py-6 space-y-4 z-50">
        <a href="/catalog" class="block text-sm font-bold text-gray-600 hover:text-brand transition-colors">Products</a>
        <a href="/#about" class="block text-sm font-bold text-gray-600 hover:text-brand transition-colors">About</a>
        <a href="/#contact" class="block text-sm font-bold text-gray-600 hover:text-brand transition-colors">Contact</a>
        <hr class="border-gray-100">
        <a href="/account" class="block text-sm font-bold text-gray-600 hover:text-brand transition-colors">Account</a>
        <a href="/login" class="block bg-brand text-brand-light text-center text-sm font-semibold py-3 px-6 rounded-xl hover:bg-brand-dark transition-all">Sign in</a>
    </div>

    <script>
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            const icon = document.getElementById('menu-icon');
            menu.classList.toggle('hidden');
            if (menu.classList.contains('hidden')) {
                icon.className = 'ti ti-menu-2 text-2xl';
            } else {
                icon.className = 'ti ti-x text-2xl';
            }
        });
    </script>
</nav>