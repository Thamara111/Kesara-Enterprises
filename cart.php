<?php
/**
 * Shopping Cart Page
 * Displays items added to the cart, calculates totals (including MOQ logic and pricing tiers), and prepares for checkout.
 */
ob_start();
$page_meta = [
    'title' => 'Your Cart | Kesara Enterprises',
    'description' => 'Review your wholesale order before checkout. Quality innerwear supplied across Sri Lanka.',
];
require_once __DIR__ . "/layouts/head.php";
if (!$can_see_prices) {
    header("Location: /login");
    exit;
}
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
                        <span class="px-3 py-1 bg-gray-50 text-gray-400 text-xs font-bold rounded-full border border-gray-100" id="cart-count-badge">0 ITEMS</span>
                    </div>

                    <!-- Cart Header -->
                    <div class="hidden md:grid gap-6 px-4 mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest items-center" style="grid-template-columns: 40px 80px 1fr 120px 120px 40px;">
                        <div class="flex items-center">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-5 h-5 text-brand bg-gray-50 border-gray-200 rounded focus:ring-brand focus:ring-2 cursor-pointer">
                        </div>
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
                        <button id="checkout-btn" onclick="submitOrder()" class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center">
                            Proceed to Checkout
                        </button>
                        <button onclick="downloadQuote()" class="w-full bg-white text-gray-900 border border-gray-200 font-bold py-4 rounded-2xl hover:bg-gray-50 hover:border-brand hover:text-brand transition-all transform hover:-translate-y-px active:scale-95 flex items-center justify-center gap-2">
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

<!-- Custom Confirmation Modal -->
<div id="clear-cart-modal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-all">
    <div class="bg-white rounded-3xl p-8 max-w-sm w-full border border-gray-100 shadow-xl">
        <div class="w-12 h-12 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-6">
            <i class="ti ti-trash text-2xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Clear Entire Cart?</h3>
        <p class="text-sm text-gray-500 mb-8 leading-relaxed">Are you sure you want to clear your entire cart? This action cannot be undone.</p>
        <div class="flex gap-4">
            <button onclick="closeClearCartModal()" class="flex-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-100 font-bold py-3.5 rounded-2xl transition-all">
                Cancel
            </button>
            <button onclick="confirmClearCart()" class="flex-1 bg-red-500 hover:bg-red-600 text-brand-light font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-red-500/20">
                Clear Cart
            </button>
        </div>
    </div>
</div>

<script>
let cartItems = [];
let dbProducts = {};

// Load cart from LocalStorage
function loadCartFromStorage() {
    const saved = localStorage.getItem('kesara_cart');
    if (saved) {
        try {
            return JSON.parse(saved);
        } catch (e) {
            return [];
        }
    }
    return [];
}

function saveCartToStorage() {
    // Only save id and qty and selection state
    const toSave = cartItems.map(item => ({id: item.id, qty: item.qty, color: item.color, size: item.size, selected: item.selected}));
    localStorage.setItem('kesara_cart', JSON.stringify(toSave));
    if (typeof window.updateCartBadges === 'function') {
        window.updateCartBadges();
    }
}

// Fetch DB details for the items
async function initializeCart() {
    const storageCart = loadCartFromStorage();
    
    // Set initial count synchronously based on localStorage
    const cartCountBadge = document.getElementById('cart-count-badge');
    if (cartCountBadge) {
        cartCountBadge.textContent = `${storageCart.length} ITEMS`;
    }

    if (storageCart.length === 0) {
        cartItems = [];
        render();
        return;
    }

    const ids = storageCart.map(i => i.id);
    try {
        const res = await fetch('api/cart_items.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_ids: ids})
        });
        const data = await res.json();
        if (data.status === 'success') {
            const products = data.data;
            products.forEach(p => dbProducts[p.id] = p);
            
            // Rebuild cartItems with db details
            cartItems = storageCart.map(item => {
                const dbP = dbProducts[item.id];
                if (!dbP) return null;
                // Enforce MOQ on load
                let finalQty = item.qty < dbP.moq ? dbP.moq : item.qty;
                return {
                    id: dbP.id,
                    name: dbP.name,
                    meta: `${item.color || 'Standard Color'} • Size ${item.size || 'M'}`,
                    image: dbP.image,
                    moq: dbP.moq,
                    tiers: dbP.tiers,
                    qty: finalQty,
                    color: item.color,
                    size: item.size,
                    selected: item.selected !== false
                };
            }).filter(i => i !== null);
            saveCartToStorage(); // Save validated quantities
            render();
        } else {
            document.getElementById('cart-items').innerHTML = `<div class="p-4 text-red-500 bg-red-50 rounded-xl border border-red-100 text-sm">Failed to load cart items: ${data.message || 'Unknown error'}</div>`;
            render();
        }
    } catch (e) {
        console.error("Failed to load cart items:", e);
        document.getElementById('cart-items').innerHTML = `<div class="p-4 text-red-500 bg-red-50 rounded-xl border border-red-100 text-sm">Network error loading cart: ${e.message}</div>`;
        render();
    }
}

// Call on load
document.addEventListener('DOMContentLoaded', initializeCart);

// Helper to calculate price based on total selected qty of the same product
function getProductTotalQty(productId) {
    return cartItems.filter(i => i.id === productId && i.selected).reduce((sum, i) => sum + i.qty, 0);
}

function getPrice(item) {
  if (!item.tiers || item.tiers.length === 0) return 0;
  let totalQty = getProductTotalQty(item.id);
  // If item is unselected, we still show a price based on its own qty just for display
  if (!item.selected) totalQty = item.qty;

  for (let t of item.tiers) {
    const max = t.max === null ? Infinity : t.max;
    if (totalQty >= t.min && totalQty <= max) {
      return t.price;
    }
  }
  // Fallback to highest tier if over max, or lowest if under min
  return item.tiers[item.tiers.length - 1].price;
}

function getTierLabel(item) {
  let totalQty = item.selected ? getProductTotalQty(item.id) : item.qty;
  const t = item.tiers.find(t=>totalQty>=t.min&&(t.max===null||totalQty<=t.max))||item.tiers[0];
  return t.max===null ? `500+ tier` : `${t.min}–${t.max} tier`;
}

function render() {
  const container = document.getElementById('cart-items');
  const summaryContainer = document.getElementById('summary-lines');
  const cartCountBadge = document.getElementById('cart-count-badge');
  
  container.innerHTML = '';
  summaryContainer.innerHTML = '';
  cartCountBadge.textContent = `${cartItems.length} ITEMS`;

  let allSelected = cartItems.length > 0 && cartItems.every(i => i.selected);
  const selectAllCb = document.getElementById('selectAllCheckbox');
  if (selectAllCb) selectAllCb.checked = allSelected;

  let subtotal = 0;
  let belowMoqCount = 0;


  cartItems.forEach((item, i) => {
    // For MOQ check, we check the total qty of that product ID
    const productTotalQty = getProductTotalQty(item.id);
    const belowMoq = item.selected && productTotalQty < item.moq;
    if(belowMoq) belowMoqCount++;
    const price = getPrice(item);
    const sub = item.qty * price;
    
    if (item.selected) {
        subtotal += sub;
    }

    const row = document.createElement('div');
    row.className = `py-8 flex flex-col md:grid gap-6 items-center ${item.selected ? '' : 'opacity-50'}`;
    row.style.gridTemplateColumns = '40px 80px 1fr 120px 120px 40px';
    row.innerHTML = `
      <div class="flex items-center">
         <input type="checkbox" ${item.selected ? 'checked' : ''} onchange="toggleItemSelect(${i})" class="w-5 h-5 text-brand bg-gray-50 border-gray-200 rounded focus:ring-brand focus:ring-2 cursor-pointer">
      </div>
      <div class="w-20 h-20 bg-gray-50 border border-gray-100 rounded-2xl flex items-center justify-center shrink-0 overflow-hidden">
        ${item.image ? `<img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover">` : `<i class="ti ti-shirt text-3xl text-gray-200"></i>`}
      </div>
      <div class="w-full text-center md:text-left flex-1">
        <h3 class="text-[15px] font-bold text-gray-900 mb-1">${item.name}</h3>
        <p class="text-xs text-gray-400 font-medium mb-3">${item.meta}</p>
        <div class="flex flex-wrap justify-center md:justify-start items-center gap-2">
            <span class="text-xs font-bold text-gray-900">LKR ${price}/pc</span>
            <span class="px-2 py-0.5 bg-brand-light text-brand text-[9px] font-bold rounded-full uppercase">${getTierLabel(item)}</span>
            ${belowMoq ? `<span class="px-2 py-0.5 bg-red-50 text-red-600 text-[9px] font-bold rounded-full flex items-center gap-1 border border-red-100 animate-pulse"><i class="ti ti-alert-triangle text-xs"></i>BELOW MOQ (${item.moq})</span>` : ''}
        </div>
      </div>
      <div class="flex items-center bg-gray-50 border border-gray-100 rounded-xl overflow-hidden shadow-sm">
          <button onclick="changeQty(${i}, -10)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-minus text-xs"></i></button>
          <span class="w-10 text-center text-sm font-bold text-gray-900">${item.qty}</span>
          <button onclick="changeQty(${i}, 10)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-brand transition-all"><i class="ti ti-plus text-xs"></i></button>
      </div>
      <div class="text-right w-full md:w-auto min-w-[120px]">
        <p class="text-sm font-bold ${belowMoq ? 'text-red-500' : 'text-gray-900'}">LKR ${sub.toLocaleString()}</p>
      </div>
      <button onclick="removeItem(${i})" class="text-gray-300 hover:text-red-500 transition-all p-2">
        <i class="ti ti-trash text-lg"></i>
      </button>
    `;
    container.appendChild(row);

    if (item.selected) {
        // Summary Line
        const sLine = document.createElement('div');
        sLine.className = 'flex justify-between items-start text-[13px]';
        sLine.innerHTML = `
            <span class="text-gray-500 font-medium leading-tight max-w-[200px]">${item.name} <span class="text-gray-300">× ${item.qty}</span></span>
            <span class="text-gray-900 font-bold whitespace-nowrap">LKR ${sub.toLocaleString()}</span>
        `;
        summaryContainer.appendChild(sLine);
    }
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
            <p class="text-[11px] leading-relaxed font-medium">Some selected items belong to products below their minimum order quantity. Please adjust quantities to proceed.</p>
        </div>
      </div>
    `;
    checkoutBtn.disabled = true;
  } else {
    alerts.innerHTML = '';
    const hasSelected = cartItems.some(i => i.selected);
    checkoutBtn.disabled = !hasSelected;
  }
}

function toggleItemSelect(index) {
    if (cartItems[index]) {
        cartItems[index].selected = !cartItems[index].selected;
        saveCartToStorage();
        render();
    }
}

function toggleSelectAll(checkbox) {
    const isChecked = checkbox.checked;
    cartItems.forEach(item => item.selected = isChecked);
    saveCartToStorage();
    render();
}

function changeQty(index, delta) {
  const item = cartItems[index];
  if(item) {
      item.qty += delta;
      if (item.qty < item.moq) item.qty = item.moq;
      saveCartToStorage();
      render();
  }
}

function removeItem(index) {
  cartItems.splice(index, 1);
  saveCartToStorage();
  render();
}

function clearCart() {
  const modal = document.getElementById('clear-cart-modal');
  modal.classList.remove('hidden');
}

function closeClearCartModal() {
  const modal = document.getElementById('clear-cart-modal');
  modal.classList.add('hidden');
}

function confirmClearCart() {
  cartItems.length = 0;
  saveCartToStorage();
  render();
  closeClearCartModal();
}

let isSubmitting = false;
function submitOrder() {
  if (isSubmitting) return;
  const checkoutBtn = document.getElementById('checkout-btn');
  const selectedItems = cartItems.filter(i => i.selected);
  if (selectedItems.length === 0) {
      showToast('Please select at least one item', 'error');
      return;
  }
  
  isSubmitting = true;
  checkoutBtn.disabled = true;
  checkoutBtn.textContent = 'Redirecting to Checkout...';

  setTimeout(() => {
      window.location.href = '/checkout';
  }, 500);
}

function downloadQuote() {
  const selectedItems = cartItems.filter(i => i.selected);
  if (selectedItems.length === 0) {
      uiAlert("Please select items to download a quote.");
      return;
  }
  
  let total = 0;
  let itemsHtml = selectedItems.map(item => {
      let price = getPrice(item);
      let subtotal = item.qty * price;
      total += subtotal;
      return `
          <tr>
              <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                  <strong>${item.name}</strong><br>
                  <small>${item.meta}</small>
              </td>
              <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${item.qty}</td>
              <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">LKR ${price.toFixed(2)}</td>
              <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">LKR ${subtotal.toFixed(2)}</td>
          </tr>
      `;
  }).join('');

  let printWindow = window.open('', '_blank');
  printWindow.document.write(`
      <html>
      <head>
          <title>Wholesale Price Quote - Kesara Enterprises</title>
          <style>
              body { font-family: sans-serif; color: #333; padding: 40px; }
              .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0F6E56; padding-bottom: 20px; margin-bottom: 30px; }
              .logo { font-size: 24px; font-weight: bold; color: #0F6E56; }
              table { width: 100%; border-collapse: collapse; margin-top: 20px; }
              th { background-color: #f5f5f5; padding: 10px; text-align: left; }
              .totals { margin-top: 30px; text-align: right; font-size: 18px; }
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
                  <h2>PRICE QUOTE</h2>
                  <div>Date: ${new Date().toLocaleDateString()}</div>
                  <div>Reference: QT-${Date.now().toString().slice(-6)}</div>
              </div>
          </div>
          <p>Thank you for requesting a wholesale access quote. Below are the details for your estimated order:</p>
          <table>
              <thead>
                  <tr>
                      <th>Product Details</th>
                      <th style="text-align: center;">Quantity</th>
                      <th style="text-align: right;">Unit Price</th>
                      <th style="text-align: right;">Subtotal</th>
                  </tr>
              </thead>
              <tbody>
                  ${itemsHtml}
              </tbody>
          </table>
          <div class="totals">
              <strong>Estimated Total: LKR ${total.toFixed(2)}</strong>
          </div>
          <div class="footer">
              <p>This is an estimated price quote. Final tax invoice and delivery charges will be calculated during order processing.</p>
              <p>© ${new Date().getFullYear()} Kesara Enterprises. All rights reserved.</p>
          </div>
          <script>
              window.onload = function() {
                  window.print();
              }
          <\/script>
      </body>
      </html>
  `);
  printWindow.document.close();
}

// Initial Render
render();
</script>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
