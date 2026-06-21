<?php
$page_meta = [
    'title' => 'Your Cart | Kesara Enterprises',
    'description' => 'Review your wholesale order before checkout. Quality innerwear supplied across Sri Lanka.',
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
            <span class="text-gray-900 font-bold tracking-tight uppercase">Your Cart</span>
        </nav>

        <div class="grid lg:grid-cols-[1fr_380px] gap-12 items-start">
            
            <!-- LEFT: CART ITEMS -->
            <div class="space-y-8">
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-8 border-b border-gray-50 pb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Order Items</h1>
                        <span class="px-3 py-1 bg-gray-50 text-gray-400 text-xs font-bold rounded-full border border-gray-100" id="cart-count-badge">3 ITEMS</span>
                    </div>

                    <!-- Cart Header -->
                    <div class="hidden md:grid grid-cols-[80px_1fr_120px_120px_40px] gap-6 px-4 mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                        <span>Product</span>
                        <span></span>
                        <span>Quantity</span>
                        <span class="text-right">Subtotal</span>
                        <span></span>
                    </div>

                    <div id="cart-items" class="divide-y divide-gray-50">
                        <!-- Items injected by JS -->
                    </div>

                    <!-- Cart Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-10 mt-10 border-t border-gray-50">
                        <a href="/catalog" class="flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-brand transition-all group">
                            <i class="ti ti-arrow-left text-lg group-hover:-translate-x-1 transition-transform"></i>
                            Continue Shopping
                        </a>
                        <button onclick="clearCart()" class="flex items-center gap-2 text-sm font-bold text-red-400 hover:text-red-600 transition-all">
                            <i class="ti ti-trash text-lg"></i>
                            Clear Cart
                        </button>
                    </div>
                </div>

                <!-- Delivery Note -->
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Delivery Note (Optional)</h2>
                    <textarea rows="3" placeholder="e.g. Please deliver to warehouse entrance. Contact Nimal on 077 xxx xxxx." class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all resize-none"></textarea>
                </div>
            </div>

            <!-- RIGHT: SUMMARY -->
            <aside class="space-y-8 sticky top-24">
                <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-8">Order Summary</h2>

                    <div id="summary-lines" class="space-y-4 mb-8">
                        <!-- Summary injected by JS -->
                    </div>

                    <hr class="border-gray-50 mb-8">

                    <div class="space-y-4 mb-8 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-medium tracking-wide">Subtotal</span>
                            <span id="subtotal-val" class="text-gray-900 font-bold">—</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div class="flex flex-col">
                                <span class="text-gray-400 font-medium tracking-wide">VAT (18%)</span>
                                <span class="text-[10px] text-gray-300">Reg. PV 00000</span>
                            </div>
                            <span id="vat-val" class="text-gray-900 font-bold">—</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-medium tracking-wide">Delivery</span>
                            <span class="text-gray-300 font-medium tracking-wide uppercase text-[10px]">At Checkout</span>
                        </div>
                    </div>

                    <div class="bg-brand-light/30 border border-brand/10 rounded-2xl p-6 mb-8">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-brand uppercase tracking-widest">Estimated Total</span>
                            <span id="total-val" class="text-2xl font-extrabold text-brand">—</span>
                        </div>
                        <p class="text-[10px] font-medium text-brand/60 leading-tight">Final total including delivery will be confirmed on the next step.</p>
                    </div>

                    <div id="moq-alerts" class="mb-8">
                        <!-- Alerts injected by JS -->
                    </div>

                    <div class="space-y-4">
                        <a href="/order-success" id="checkout-btn" class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center">
                            Proceed to Checkout
                        </a>
                        <button class="w-full bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px active:scale-95 flex items-center justify-center gap-2">
                            <i class="ti ti-file-text text-xl"></i>
                            Download as Quote
                        </button>
                    </div>

                    <!-- Trust Points -->
                    <div class="mt-8 space-y-4">
                        <div class="flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <i class="ti ti-receipt text-lg text-brand"></i>
                            <span>Tax Invoice on Dispatch</span>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <i class="ti ti-truck text-lg text-brand"></i>
                            <span>Island-wide Delivery</span>
                        </div>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</main>

<script>
const TIERS_A = [
  {min:50,  max:99,  price:120},
  {min:100, max:499, price:108},
  {min:500, max:Infinity, price:95}
];
const TIERS_B = [
  {min:50,  max:99,  price:145},
  {min:100, max:499, price:130},
  {min:500, max:Infinity, price:115}
];
const TIERS_C = [
  {min:50,  max:99,  price:195},
  {min:100, max:499, price:175},
  {min:500, max:Infinity, price:155}
];

const cartItems = [
  {id:0, name:'Classic Cotton Brief', meta:'Black · Size M · SKU KB-001', qty:120, moq:50, tiers:TIERS_A},
  {id:1, name:'Ladies Hipster', meta:'White · Size S · SKU KL-003', qty:40,  moq:50, tiers:TIERS_B},
  {id:2, name:'Stretch Boxer', meta:'Navy · Size L · SKU KB-008', qty:200, moq:100, tiers:TIERS_C}
];

function getPrice(item) {
  return (item.tiers.find(t=>item.qty>=t.min&&item.qty<=t.max)||item.tiers[0]).price;
}

function getTierLabel(item) {
  const t = item.tiers.find(t=>item.qty>=t.min&&item.qty<=t.max)||item.tiers[0];
  return t.max===Infinity ? `500+ tier` : `${t.min}–${t.max} tier`;
}

function render() {
  const container = document.getElementById('cart-items');
  const summaryContainer = document.getElementById('summary-lines');
  const cartCountBadge = document.getElementById('cart-count-badge');
  
  container.innerHTML = '';
  summaryContainer.innerHTML = '';
  cartCountBadge.textContent = `${cartItems.length} ITEMS`;

  let subtotal = 0;
  let belowMoqCount = 0;

  cartItems.forEach((item, i) => {
    const belowMoq = item.qty < item.moq;
    if(belowMoq) belowMoqCount++;
    const price = getPrice(item);
    const sub = item.qty * price;
    subtotal += sub;

    const row = document.createElement('div');
    row.className = 'py-8 flex flex-col md:grid md:grid-cols-[80px_1fr_120px_120px_40px] gap-6 items-center';
    row.innerHTML = `
      <div class="w-20 h-20 bg-gray-50 border border-gray-100 rounded-2xl flex items-center justify-center shrink-0">
        <i class="ti ti-shirt text-3xl text-gray-200"></i>
      </div>
      <div class="w-full text-center md:text-left">
        <h3 class="text-[15px] font-bold text-gray-900 mb-1">${item.name}</h3>
        <p class="text-xs text-gray-400 font-medium mb-3">${item.meta}</p>
        <div class="flex flex-wrap justify-center md:justify-start items-center gap-2">
            <span class="text-xs font-bold text-gray-900">LKR ${price}/pc</span>
            <span class="px-2 py-0.5 bg-brand-light text-brand text-[9px] font-bold rounded-full uppercase">${getTierLabel(item)}</span>
            ${belowMoq ? `<span class="px-2 py-0.5 bg-red-50 text-red-600 text-[9px] font-bold rounded-full flex items-center gap-1 border border-red-100 animate-pulse"><i class="ti ti-alert-triangle text-xs"></i>BELOW MOQ (${item.moq})</span>` : ''}
        </div>
      </div>
      <div class="flex items-center bg-gray-50 border border-gray-100 rounded-xl overflow-hidden shadow-sm">
        <button onclick="changeQty(${i},-10)" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-minus text-xs"></i></button>
        <span class="w-12 text-center text-xs font-bold text-gray-900">${item.qty}</span>
        <button onclick="changeQty(${i},10)" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-plus text-xs"></i></button>
      </div>
      <div class="text-right w-full md:w-auto">
        <p class="text-sm font-bold ${belowMoq ? 'text-red-500' : 'text-gray-900'}">LKR ${sub.toLocaleString()}</p>
      </div>
      <button onclick="removeItem(${i})" class="text-gray-300 hover:text-red-500 transition-all p-2">
        <i class="ti ti-trash text-lg"></i>
      </button>
    `;
    container.appendChild(row);

    // Summary Line
    const sLine = document.createElement('div');
    sLine.className = 'flex justify-between items-start text-[13px]';
    sLine.innerHTML = `
        <span class="text-gray-500 font-medium leading-tight max-w-[200px]">${item.name} <span class="text-gray-300">× ${item.qty}</span></span>
        <span class="text-gray-900 font-bold whitespace-nowrap">LKR ${sub.toLocaleString()}</span>
    `;
    summaryContainer.appendChild(sLine);
  });

  const vat = Math.round(subtotal * 0.18);
  const total = subtotal + vat;

  document.getElementById('subtotal-val').textContent = 'LKR ' + subtotal.toLocaleString();
  document.getElementById('vat-val').textContent = 'LKR ' + vat.toLocaleString();
  document.getElementById('total-val').textContent = 'LKR ' + total.toLocaleString();

  // Alerts & Checkout state
  const alerts = document.getElementById('moq-alerts');
  const checkoutBtn = document.getElementById('checkout-btn');
  
  if (belowMoqCount > 0) {
    alerts.innerHTML = `
      <div class="p-4 bg-red-50 border border-red-100 rounded-2xl flex gap-3 text-red-600">
        <i class="ti ti-alert-triangle text-xl shrink-0 mt-0.5"></i>
        <div class="space-y-1">
            <p class="text-xs font-bold uppercase tracking-wide">Cannot Proceed</p>
            <p class="text-[11px] leading-relaxed font-medium">Some items are below their minimum order quantity. Please adjust quantities to proceed.</p>
        </div>
      </div>
    `;
    checkoutBtn.disabled = true;
  } else {
    alerts.innerHTML = '';
    checkoutBtn.disabled = false;
  }
}

function changeQty(i, delta) {
  cartItems[i].qty = Math.max(0, cartItems[i].qty + delta);
  render();
}

function removeItem(i) {
  cartItems.splice(i, 1);
  render();
}

function clearCart() {
  if(confirm('Are you sure you want to clear your entire cart?')) {
      cartItems.length = 0;
      render();
  }
}

// Initial Render
render();
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
