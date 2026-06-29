<?php
// Mock Checkout Page for Kesara Enterprises
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Kesara Enterprises</title>
    <link href="/dist/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body class="bg-gray-50 min-h-screen text-gray-900 selection:bg-brand selection:text-white">

    <!-- Simple Checkout Header -->
    <header class="bg-white border-b border-gray-100 py-6">
        <div class="max-w-6xl mx-auto px-6 flex justify-between items-center">
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand text-brand-light flex items-center justify-center rounded-xl font-black text-xl tracking-tighter transform -rotate-2">
                    K
                </div>
                <span class="text-xl font-black tracking-tight text-gray-900 uppercase">Kesara Enterprises</span>
            </a>
            <div class="text-sm font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                <i class="ti ti-lock text-brand"></i> Secure Checkout
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-12 md:py-20 grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-12">
        
        <!-- Left: Payment Form -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-2">Payment Details</h1>
            <p class="text-sm text-gray-500 mb-8">Please enter your payment information to finalize the order.</p>
            
            <form id="payment-form" onsubmit="processPayment(event)">
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-6">
                    
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Name on Card</label>
                        <input type="text" id="card_name" required placeholder="John Doe" 
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all">
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Card Number</label>
                        <div class="relative">
                            <i class="ti ti-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="card_number" required placeholder="0000 0000 0000 0000" maxlength="19"
                                class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all font-mono"
                                oninput="formatCardNumber(this)">
                        </div>
                        <p id="card-error" class="text-xs text-red-500 font-bold hidden">Invalid card number. Must be 16 digits.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Expiry (MM/YY)</label>
                            <input type="text" id="card_expiry" required placeholder="MM/YY" maxlength="5"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all font-mono"
                                oninput="formatExpiry(this)">
                            <p id="expiry-error" class="text-xs text-red-500 font-bold hidden">Invalid expiry.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">CVV</label>
                            <div class="relative">
                                <input type="password" id="card_cvv" required placeholder="123" maxlength="4"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all font-mono"
                                    oninput="this.value = this.value.replace(/\D/g, '')">
                                <i class="ti ti-help absolute right-4 top-1/2 -translate-y-1/2 text-gray-300 cursor-help" title="3 or 4 digits on the back of your card"></i>
                            </div>
                            <p id="cvv-error" class="text-xs text-red-500 font-bold hidden">Invalid CVV.</p>
                        </div>
                    </div>

                </div>

                <div class="mt-8">
                    <button type="submit" id="pay-btn" class="w-full bg-brand text-brand-light font-bold py-5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 text-lg flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-lock text-xl"></i>
                        Pay LKR <span id="btn-total">0.00</span>
                    </button>
                    <div id="processing-loader" class="hidden justify-center items-center gap-3 mt-4 text-sm font-bold text-gray-500 uppercase tracking-widest">
                        <i class="ti ti-loader animate-spin text-brand text-lg"></i> Processing Secure Payment...
                    </div>
                </div>
            </form>
        </div>

        <!-- Right: Order Summary -->
        <div>
            <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm sticky top-10">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-widest mb-6 border-b border-gray-100 pb-4">Order Summary</h2>
                
                <div id="summary-items" class="space-y-4 mb-6 max-h-60 overflow-y-auto pr-2">
                    <!-- Items injected by JS -->
                    <div class="text-center text-gray-400 text-xs py-4">Loading cart items...</div>
                </div>

                <div class="border-t border-gray-100 pt-6 space-y-4">
                    <div class="flex justify-between text-sm text-gray-500 font-medium">
                        <span>Subtotal</span>
                        <span id="summary-subtotal">LKR 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-500 font-medium">
                        <span>VAT (18%)</span>
                        <span id="summary-vat">LKR 0.00</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold text-gray-900 pt-4 border-t border-gray-100">
                        <span>Total</span>
                        <span id="summary-total" class="text-brand">LKR 0.00</span>
                    </div>
                </div>
            </div>
        </div>

    </main>

<script>
// Mock Payment Logic & DB Cart integration

let cartItems = [];
let dbProducts = {};
let finalTotalAmount = 0;
let finalItemsPayload = [];

function loadCartFromStorage() {
    const saved = localStorage.getItem('kesara_cart');
    return saved ? JSON.parse(saved) : [];
}

async function initializeCheckout() {
    const storageCart = loadCartFromStorage();
    if (storageCart.length === 0) {
        window.location.href = '/cart.php';
        return;
    }

    const ids = storageCart.map(i => i.id);
    try {
        const res = await fetch('/api/cart_items.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_ids: ids})
        });
        const data = await res.json();
        if (data.status === 'success') {
            const products = data.data;
            products.forEach(p => dbProducts[p.id] = p);
            
            cartItems = storageCart.map(item => {
                const dbP = dbProducts[item.id];
                if (!dbP) return null;
                return {
                    id: dbP.id,
                    name: dbP.name,
                    moq: dbP.moq,
                    tiers: dbP.tiers,
                    qty: item.qty
                };
            }).filter(i => i !== null);
            
            renderSummary();
        }
    } catch (e) {
        console.error("Failed to load cart items:", e);
        alert("Failed to load checkout details.");
    }
}

function getPrice(item) {
  if (!item.tiers || item.tiers.length === 0) return 0;
  for (let t of item.tiers) {
    const max = t.max === null ? Infinity : t.max;
    if (item.qty >= t.min && item.qty <= max) {
      return t.price;
    }
  }
  return item.tiers[item.tiers.length - 1].price;
}

function renderSummary() {
    let subtotal = 0;
    const summaryContainer = document.getElementById('summary-items');
    summaryContainer.innerHTML = '';
    finalItemsPayload = [];

    cartItems.forEach(item => {
        const price = getPrice(item);
        const sub = price * item.qty;
        subtotal += sub;
        
        finalItemsPayload.push({
            product_id: item.id,
            quantity: item.qty,
            unit_price: price
        });

        const row = document.createElement('div');
        row.className = 'flex justify-between items-start text-sm';
        row.innerHTML = `
            <div class="pr-4">
                <p class="font-bold text-gray-900">${item.name}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-widest mt-0.5">Qty: ${item.qty} &times; LKR ${price.toLocaleString()}</p>
            </div>
            <div class="font-bold text-gray-900 whitespace-nowrap">LKR ${sub.toLocaleString()}</div>
        `;
        summaryContainer.appendChild(row);
    });

    const vat = subtotal * 0.18;
    finalTotalAmount = subtotal + vat;

    document.getElementById('summary-subtotal').textContent = 'LKR ' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summary-vat').textContent = 'LKR ' + vat.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    const finalFormatted = finalTotalAmount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summary-total').textContent = 'LKR ' + finalFormatted;
    document.getElementById('btn-total').textContent = finalFormatted;
}

// Input Formatting
function formatCardNumber(input) {
    let val = input.value.replace(/\D/g, '');
    let formatted = val.match(/.{1,4}/g);
    input.value = formatted ? formatted.join(' ') : val;
    document.getElementById('card-error').classList.add('hidden');
}

function formatExpiry(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length >= 2) {
        val = val.substring(0,2) + '/' + val.substring(2,4);
    }
    input.value = val;
    document.getElementById('expiry-error').classList.add('hidden');
}

// Validation & Submission
async function processPayment(e) {
    e.preventDefault();
    
    let valid = true;
    
    // Validate Card Number (16 digits)
    const cardNo = document.getElementById('card_number').value.replace(/\s/g, '');
    if (cardNo.length < 15 || cardNo.length > 19) {
        document.getElementById('card-error').classList.remove('hidden');
        valid = false;
    }
    
    // Validate Expiry (MM/YY)
    const exp = document.getElementById('card_expiry').value;
    const expParts = exp.split('/');
    if (expParts.length !== 2 || parseInt(expParts[0]) > 12 || parseInt(expParts[0]) === 0 || expParts[1].length !== 2) {
        document.getElementById('expiry-error').classList.remove('hidden');
        valid = false;
    } else {
        // Optional: Check if expired
        const now = new Date();
        const year = parseInt("20" + expParts[1]);
        const month = parseInt(expParts[0]);
        if (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) {
            document.getElementById('expiry-error').textContent = "Card has expired.";
            document.getElementById('expiry-error').classList.remove('hidden');
            valid = false;
        }
    }
    
    // Validate CVV (3 or 4 digits)
    const cvv = document.getElementById('card_cvv').value;
    if (cvv.length < 3) {
        document.getElementById('cvv-error').classList.remove('hidden');
        valid = false;
    }

    if (!valid) return;

    // Simulate Processing
    const payBtn = document.getElementById('pay-btn');
    const loader = document.getElementById('processing-loader');
    
    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="ti ti-check text-xl"></i> Submitting...';
    loader.classList.remove('hidden');
    loader.classList.add('flex');

    // Wait 2 seconds for dramatic effect
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Submit Order to API
    try {
        const res = await fetch('/api/orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                total_amount: finalTotalAmount,
                items: finalItemsPayload
            })
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            // Clear cart
            localStorage.removeItem('kesara_cart');
            window.location.href = `/order-success?order_id=${data.order_id}`;
        } else {
            alert(data.message || 'Error processing order.');
            payBtn.disabled = false;
            payBtn.innerHTML = `<i class="ti ti-lock text-xl"></i> Pay LKR <span id="btn-total">${finalTotalAmount.toLocaleString()}</span>`;
            loader.classList.add('hidden');
            loader.classList.remove('flex');
        }
    } catch (err) {
        console.error(err);
        alert('Network error occurred.');
        payBtn.disabled = false;
        payBtn.innerHTML = `<i class="ti ti-lock text-xl"></i> Pay LKR <span id="btn-total">${finalTotalAmount.toLocaleString()}</span>`;
        loader.classList.add('hidden');
        loader.classList.remove('flex');
    }
}

document.addEventListener('DOMContentLoaded', initializeCheckout);
</script>
</body>
</html>
