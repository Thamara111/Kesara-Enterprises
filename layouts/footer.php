<footer class="bg-gray-900 border-t border-gray-100 pt-16 pb-8">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-12 mb-16">
            <div class="col-span-2 lg:col-span-1">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-lg bg-brand-light flex items-center justify-center">
                        <i class="ti ti-building-store text-brand text-lg"></i>
                    </div>
                    <span class="text-md font-bold text-gray-400">Kesara Enterprises</span>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Sri Lanka's leading wholesale supplier of quality innerwear since 2012.
                </p>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-400 mb-4">Company</p>
                <a href="/about" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">About us</a>
                <a href="/contact" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Contact</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Careers</a>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-400 mb-4">Buyers</p>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Register</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Sign in</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">How it works</a>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-400 mb-4">Products</p>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Men's range</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Ladies range</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Children's range</a>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-400 mb-4">Legal</p>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Terms of sale</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Privacy policy</a>
                <a href="#" class="text-sm text-gray-500 block mb-2.5 hover:text-brand transition-colors no-underline">Returns policy</a>
            </div>
        </div>
        
        <hr class="border-gray-100 mb-8">
        
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-gray-400">
                © 2025 Kesara Enterprises (Pvt) Ltd. All rights reserved.
            </p>
            <p class="text-sm text-gray-400 font-medium">
                Reg. No: PV 00000 · VAT: 123456789
            </p>
        </div>
    </div>
</footer>

<script src="dist/app.js"></script>
<script>
function updateCartBadges() {
    const saved = localStorage.getItem('kesara_cart');
    let count = 0;
    if (saved) {
        try {
            const cart = JSON.parse(saved);
            count = cart.length; // Count of distinct items, or use cart.reduce((sum, item) => sum + item.qty, 0) for total qty
        } catch(e) {}
    }
    
    const desktopBadge = document.getElementById('cart-badge-desktop');
    const mobileBadge = document.getElementById('cart-badge-mobile');
    
    if (desktopBadge) {
        desktopBadge.textContent = count;
        if (count > 0) desktopBadge.classList.remove('hidden');
        else desktopBadge.classList.add('hidden');
    }
    
    if (mobileBadge) {
        mobileBadge.textContent = count;
        if (count > 0) mobileBadge.classList.remove('hidden');
        else mobileBadge.classList.add('hidden');
    }
}
window.updateCartBadges = updateCartBadges;

document.addEventListener('DOMContentLoaded', function() {
    updateCartBadges();
    
    // Listen for storage changes from other tabs
    window.addEventListener('storage', function(e) {
        if (e.key === 'kesara_cart') {
            updateCartBadges();
        }
    });
});
</script>
</body>
</html>