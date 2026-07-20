<?php
// Mock Checkout Page for Kesara Enterprises
require_once __DIR__ . "/database/connection.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Cache-control headers to prevent stale display of approval-gated content
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

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
        $buyer_approved = false;
    }
}
$can_see_prices = $is_logged_in && $buyer_approved;

if (!$can_see_prices) {
    header("Location: /login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Kesara Enterprises</title>
    <link href="/dist/output.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/tabler-icons.min.css">
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
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-2">Payment Verification</h1>
            <p class="text-sm text-gray-500 mb-8">Review your order and click 'Place Order' below to submit it directly.</p>
            
            <form id="payment-form" onsubmit="processPayment(event)">
                <input type="hidden" id="payment_method" name="payment_method" value="bank">

                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-6">
                    
                    <!-- Bank Details Panel -->
                    <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Our Bank Details</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-brand-light text-brand border border-brand/10 uppercase tracking-wider">Bank Transfer</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider mb-0.5">Bank</span>
                                <span class="font-extrabold text-gray-800">Sampath Bank PLC</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider mb-0.5">Branch</span>
                                <span class="font-extrabold text-gray-800">Colombo Main Branch</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider mb-0.5">Account Name</span>
                                <span class="font-extrabold text-gray-800">Kesara Enterprises (Pvt) Ltd</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider mb-0.5">Account Number</span>
                                <span class="font-black text-brand text-base tracking-wider font-mono">1009 5432 1987</span>
                            </div>
                        </div>
                    </div>

                    <!-- Receipt Upload -->
                    <div class="space-y-4">
                        <label class="block text-sm font-bold text-gray-900">Upload Transfer Receipt <span class="text-red-500">*</span></label>
                        <div id="drop-zone" class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center cursor-pointer hover:border-brand hover:bg-brand-light/10 transition-all">
                            <i class="ti ti-cloud-upload text-3xl text-brand mb-2"></i>
                            <p class="text-sm text-gray-500 font-medium">Click to browse or drag and drop</p>
                            <p class="text-xs text-gray-400 mt-1">PNG, JPG or PDF (Max 5MB)</p>
                        </div>
                        <input type="file" id="receipt_file" name="receipt_file" class="hidden" accept="image/png, image/jpeg, application/pdf" onchange="handleFileSelect(this)">
                        
                        <div id="file-error" class="hidden text-red-500 text-xs font-bold mt-2"></div>
                        <div id="file-preview-container" class="hidden flex items-center justify-between bg-brand-light/20 border border-brand/20 rounded-xl p-3 mt-2">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <i id="file-icon" class="ti ti-file-text text-brand text-lg"></i>
                                <div>
                                    <span id="preview-filename" class="block text-sm font-bold text-gray-900 truncate"></span>
                                    <span id="preview-filesize" class="block text-xs text-gray-500"></span>
                                </div>
                            </div>
                            <button type="button" onclick="clearSelectedFile()" class="text-gray-400 hover:text-red-500 transition-colors p-2">
                                <i class="ti ti-x text-lg"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <div class="mt-8">
                    <button type="submit" id="pay-btn" class="w-full bg-brand text-brand-light font-bold py-5 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 text-lg flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-check text-xl"></i>
                        Place Order (LKR <span id="btn-total">0.00</span>)
                    </button>
                    <div id="processing-loader" class="hidden justify-center items-center gap-3 mt-4 text-sm font-bold text-gray-500 uppercase tracking-widest">
                        <i class="ti ti-loader animate-spin text-brand text-lg"></i> Submitting order...
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
// Checkout & DB Cart integration

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
        const res = await fetch('api/cart_items.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_ids: ids})
        });
        const data = await res.json();
        if (data.status === 'success') {
            const products = data.data;
            products.forEach(p => dbProducts[p.id] = p);
            
            cartItems = storageCart.filter(item => item.selected !== false).map(item => {
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

function getProductTotalQty(productId) {
    return cartItems.filter(i => i.id === productId).reduce((sum, i) => sum + i.qty, 0);
}

function getPrice(item) {
  if (!item.tiers || item.tiers.length === 0) return 0;
  let totalQty = getProductTotalQty(item.id);

  for (let t of item.tiers) {
    const max = t.max === null ? Infinity : t.max;
    if (totalQty >= t.min && totalQty <= max) {
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

// File Drag & Drop + Selection Logic
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('receipt_file');

    if (dropZone && fileInput) {
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-gray-200');
            dropZone.classList.add('border-brand', 'bg-brand-light/10');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-brand', 'bg-brand-light/10');
            dropZone.classList.add('border-gray-200');
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-brand', 'bg-brand-light/10');
            dropZone.classList.add('border-gray-200');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(fileInput);
            }
        });
    }
});

function handleFileSelect(input) {
    const errorEl = document.getElementById('file-error');
    if (errorEl) errorEl.classList.add('hidden');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Size validation
        if (file.size > 5 * 1024 * 1024) {
            alert("File is too large. Max size is 5MB.");
            input.value = '';
            return;
        }
        
        // Extension validation
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['jpg', 'jpeg', 'png', 'pdf'].includes(ext)) {
            alert("Invalid file format. Allowed types: JPG, PNG, PDF.");
            input.value = '';
            return;
        }

        // Show preview
        document.getElementById('preview-filename').textContent = file.name;
        document.getElementById('preview-filesize').textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        
        // Update icon based on file type
        const fileIcon = document.getElementById('file-icon');
        if (ext === 'pdf') {
            fileIcon.className = 'ti ti-file-type-pdf text-xl text-red-500';
        } else {
            fileIcon.className = 'ti ti-photo text-xl text-brand';
        }
        
        document.getElementById('file-preview-container').classList.remove('hidden');
        document.getElementById('file-preview-container').classList.add('flex');
        document.getElementById('drop-zone').classList.add('hidden');
    }
}

function clearSelectedFile() {
    const fileInput = document.getElementById('receipt_file');
    if (fileInput) fileInput.value = '';
    document.getElementById('file-preview-container').classList.remove('flex');
    document.getElementById('file-preview-container').classList.add('hidden');
    document.getElementById('drop-zone').classList.remove('hidden');
}

// Validation & Submission
async function processPayment(e) {
    e.preventDefault();
    const fileInput = document.getElementById('receipt_file');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please upload your transfer receipt to proceed.');
        return;
    }
    const payBtn = document.getElementById('pay-btn');
    const loader = document.getElementById('processing-loader');
    
    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="ti ti-loader animate-spin text-xl"></i> Submitting...';
    loader.classList.remove('hidden');
    loader.classList.add('flex');

    const formData = new FormData();
    formData.append('payment_method', 'bank');
    formData.append('total_amount', finalTotalAmount);
    formData.append('items', JSON.stringify(finalItemsPayload));
    if (fileInput.files.length > 0) {
        formData.append('receipt_file', fileInput.files[0]);
    }

    // Submit Order to API
    try {
        const res = await fetch('api/orders.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            // Clear cart
            localStorage.removeItem('kesara_cart');
            window.location.href = `/order-success?order_id=${data.order_id}`;
        } else {
            alert(data.message || 'Error processing order.');
            payBtn.disabled = false;
            payBtn.innerHTML = `<i class="ti ti-check text-xl"></i> Place Order (LKR <span id="btn-total">${finalTotalAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>)`;
            loader.classList.add('hidden');
            loader.classList.remove('flex');
        }
    } catch (err) {
        console.error(err);
        alert('Network error occurred.');
        payBtn.disabled = false;
        payBtn.innerHTML = `<i class="ti ti-check text-xl"></i> Place Order (LKR <span id="btn-total">${finalTotalAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>)`;
        loader.classList.add('hidden');
        loader.classList.remove('flex');
    }
}

document.addEventListener('DOMContentLoaded', initializeCheckout);
</script>
</body>
</html>
