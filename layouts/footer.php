<footer class="bg-gray-900 border-t border-gray-800 pt-16 pb-8">
    <div class="max-w-8xl mx-auto px-6 md:px-12">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-12 mb-16">
            <div class="col-span-2 lg:col-span-1">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-lg bg-brand-light flex items-center justify-center">
                        <i class="ti ti-building-store text-brand text-lg"></i>
                    </div>
                    <span class="text-md font-bold text-white">Kesara Enterprises</span>
                </div>
                <p class="text-sm text-gray-400 leading-relaxed mb-6">
                    Sri Lanka's leading wholesale supplier of quality innerwear since 2012.
                </p>
            </div>
            <div>
                <p class="text-sm font-bold text-white mb-4 uppercase tracking-wider">Company</p>
                <a href="/about" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">About us</a>
                <a href="/contact" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Contact</a>
            </div>
            <div>
                <p class="text-sm font-bold text-white mb-4 uppercase tracking-wider">Buyers</p>
                <a href="/login" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Register</a>
                <a href="/login" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Sign in</a>
                <a href="/about" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">How it works</a>
            </div>
            <div>
                <p class="text-sm font-bold text-white mb-4 uppercase tracking-wider">Products</p>
                <a href="/catalog" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Men's range</a>
                <a href="/catalog" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Ladies range</a>
                <a href="/catalog" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Children's range</a>
            </div>
            <div>
                <p class="text-sm font-bold text-white mb-4 uppercase tracking-wider">Legal</p>
                <a href="/terms" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Terms of sale</a>
                <a href="/terms" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Privacy policy</a>
                <a href="/terms" class="text-sm text-gray-400 block mb-2.5 hover:text-brand transition-colors no-underline">Returns policy</a>
            </div>
        </div>
        
        <hr class="border-gray-800 mb-8">
        
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-gray-400">
                © <?= date('Y') ?> Kesara Enterprises (Pvt) Ltd. All rights reserved.
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
            count = cart.length;
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

// Global button loading helper for frontend actions
function setButtonLoading(btn, isLoading, customText) {
    if (typeof btn === 'string') btn = document.getElementById(btn);
    if (!btn) return;
    
    if (isLoading) {
        btn.disabled = true;
        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }
        var text = customText || btn.dataset.loadingText || 'Processing...';
        btn.innerHTML = `<i class="ti ti-loader animate-spin text-lg"></i> <span>${text}</span>`;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        btn.disabled = false;
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
        }
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    }
}
window.setButtonLoading = setButtonLoading;
</script>
</body>
</html>