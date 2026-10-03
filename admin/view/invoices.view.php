<?php
/**
 * Admin Invoices Management View
 * Features:
 * 1. Invoice History List with status badges & filter tabs.
 * 2. Interactive Invoice Creator with:
 *    - Auto-generated next Invoice Number.
 *    - Wholesale Shop / Customer Suggestions (auto-fills details)
 *    - Product & Live Stock Availability Suggestions (auto-fills SKU, Name, Size, Unit Price)
 *    - Header details (Kesara Enterprises + Customer)
 *    - Product Table: Product Number | Product Name | Size | Qty | Unit Price | Total
 *    - Payment Policy terms
 *    - Dual Signatures (Customer & Kesara Enterprises) with Digital Canvas + Printable signature lines.
 * 3. Printable / View Modal for full invoice preview.
 */

// Fetch initial metrics and invoices list directly via PDO if available
$invoices_list = [];
$total_invoices_count = 0;
$total_issued_amount = 0.00;
$total_paid_amount = 0.00;
$pending_invoices_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        // Self-heal DB tables if missing
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NULL,
            customer_name VARCHAR(100) NOT NULL,
            business_name VARCHAR(100) NULL,
            customer_email VARCHAR(100) NULL,
            customer_phone VARCHAR(30) NULL,
            customer_address TEXT NULL,
            br_number VARCHAR(50) NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_policy TEXT NULL,
            customer_signature LONGTEXT NULL,
            kesara_signature LONGTEXT NULL,
            status ENUM('draft', 'issued', 'paid', 'cancelled') DEFAULT 'issued',
            invoice_date DATE NOT NULL,
            due_date DATE NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            product_id INT NULL,
            product_sku VARCHAR(50) NOT NULL,
            product_name VARCHAR(150) NOT NULL,
            size VARCHAR(30) DEFAULT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure customer_order_number column exists
        $checkOrderNum = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'customer_order_number'");
        if (!$checkOrderNum->fetch()) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN customer_order_number VARCHAR(100) NULL AFTER br_number");
        }

        // Ensure size column exists
        $checkSize = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'size'");
        if (!$checkSize->fetch()) {
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN size VARCHAR(30) DEFAULT NULL AFTER product_name");
        }

        // Fetch invoice history list
        $stmt = $pdo->query("SELECT i.*, COUNT(ii.id) AS item_count 
                             FROM invoices i 
                             LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
                             GROUP BY i.id 
                             ORDER BY i.created_at DESC, i.id DESC");
        $invoices_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_invoices_count = count($invoices_list);
        foreach ($invoices_list as $inv) {
            $amt = (float)$inv['total_amount'];
            if ($inv['status'] === 'paid') {
                $total_paid_amount += $amt;
            } elseif ($inv['status'] === 'issued' || $inv['status'] === 'draft') {
                $total_issued_amount += $amt;
                $pending_invoices_count++;
            }
        }
    } catch (\Exception $e) {
        $invoices_list = [];
    }
}
?>

<!-- Print-Only CSS Rules for Invoice PDF / Browser Printing -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #invoice-print-area, #invoice-print-area * {
        visibility: visible;
    }
    #invoice-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-4 md:p-8 w-full">
    <div class="w-full space-y-6">

        <!-- Header Title & Top Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center font-bold text-xl">
                        <i class="ti ti-receipt"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Invoice Management</h1>
                        <p class="text-xs text-gray-500 font-medium mt-0.5">Create, issue, and manage wholesale invoices with live stock & customer auto-complete.</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openCreateInvoiceModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand hover:bg-brand-dark text-white font-bold text-sm rounded-xl shadow-lg shadow-brand/20 transition-all transform active:scale-95">
                    <i class="ti ti-plus text-lg"></i>
                    <span>Create New Invoice</span>
                </button>
            </div>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total Invoices</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1"><?= number_format($total_invoices_count) ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-files"></i>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Issued / Pending</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1">LKR <?= number_format($total_issued_amount, 2) ?></h3>
                    <p class="text-[10px] font-semibold text-gray-400 mt-0.5"><?= $pending_invoices_count ?> pending invoices</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-clock-hour-4"></i>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Total Paid</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1">LKR <?= number_format($total_paid_amount, 2) ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-circle-check"></i>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-purple-600">Quick Actions</p>
                    <button onclick="openCreateInvoiceModal()" class="mt-2 text-xs font-bold text-brand hover:underline flex items-center gap-1">
                        <span>+ Issue Invoice Now</span>
                    </button>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold">
                    <i class="ti ti-file-text"></i>
                </div>
            </div>
        </div>

        <!-- Invoice History Table & Controls -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <!-- Filter Bar -->
            <div class="p-4 sm:p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0 scrollbar-none">
                    <button onclick="filterInvoices('all')" id="tab-all" class="inv-tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all bg-brand text-white shadow-md shadow-brand/10">All Invoices</button>
                    <button onclick="filterInvoices('issued')" id="tab-issued" class="inv-tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all bg-gray-100 text-gray-600 hover:bg-gray-200">Issued</button>
                    <button onclick="filterInvoices('paid')" id="tab-paid" class="inv-tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all bg-gray-100 text-gray-600 hover:bg-gray-200">Paid</button>
                    <button onclick="filterInvoices('draft')" id="tab-draft" class="inv-tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all bg-gray-100 text-gray-600 hover:bg-gray-200">Draft</button>
                    <button onclick="filterInvoices('cancelled')" id="tab-cancelled" class="inv-tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all bg-gray-100 text-gray-600 hover:bg-gray-200">Cancelled</button>
                </div>

                <div class="relative w-56 px-2">
                    <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-base px-2"></i>
                    <input type="text" id="invoiceSearchInput" onkeyup="handleInvoiceSearch()" placeholder="Search invoice # or shop..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="invoicesTable">
                    <thead>
                        <tr class="bg-gray-50/70 border-b border-gray-100 text-[11px] font-black uppercase text-gray-400 tracking-wider">
                            <th class="py-3.5 px-6">Invoice #</th>
                            <th class="py-3.5 px-6">Wholesale Shop / Customer</th>
                            <th class="py-3.5 px-6">Date</th>
                            <th class="py-3.5 px-6">Due Date</th>
                            <th class="py-3.5 px-6 text-center">Items</th>
                            <th class="py-3.5 px-6 text-right">Total Amount (LKR)</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700 font-medium" id="invoicesTableBody">
                        <?php if (empty($invoices_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-gray-400">
                                    <i class="ti ti-file-off text-4xl block mb-2 opacity-50"></i>
                                    <span>No invoices found. Click "Create New Invoice" to issue one.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices_list as $inv): 
                                $statusClass = 'bg-gray-100 text-gray-700 border-gray-200';
                                if ($inv['status'] === 'paid') $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                elseif ($inv['status'] === 'issued') $statusClass = 'bg-blue-50 text-blue-700 border-blue-200';
                                elseif ($inv['status'] === 'draft') $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';
                                elseif ($inv['status'] === 'cancelled') $statusClass = 'bg-red-50 text-red-700 border-red-200';
                            ?>
                                <tr class="hover:bg-gray-50/80 transition-colors invoice-row" data-status="<?= htmlspecialchars($inv['status']) ?>">
                                    <td class="py-4 px-6 font-bold text-gray-900">
                                        <button onclick="viewInvoice(<?= $inv['id'] ?>)" class="text-brand hover:underline font-mono">
                                            <?= htmlspecialchars($inv['invoice_number']) ?>
                                        </button>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-900"><?= htmlspecialchars($inv['business_name'] ?: $inv['customer_name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($inv['customer_name']) ?> <?= $inv['customer_phone'] ? '| ' . htmlspecialchars($inv['customer_phone']) : '' ?></div>
                                    </td>
                                    <td class="py-4 px-6 text-gray-600 font-mono"><?= date('Y-m-d', strtotime($inv['invoice_date'])) ?></td>
                                    <td class="py-4 px-6 text-gray-600 font-mono"><?= $inv['due_date'] ? date('Y-m-d', strtotime($inv['due_date'])) : '-' ?></td>
                                    <td class="py-4 px-6 text-center font-bold text-gray-600"><?= (int)$inv['item_count'] ?></td>
                                    <td class="py-4 px-6 text-right font-black text-gray-900">LKR <?= number_format((float)$inv['total_amount'], 2) ?></td>
                                    <td class="py-4 px-6 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border <?= $statusClass ?>">
                                            <?= htmlspecialchars($inv['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right space-x-2">
                                        <button onclick="viewInvoice(<?= $inv['id'] ?>)" title="View / Print Invoice" class="p-2 text-brand hover:bg-brand/10 rounded-lg transition-colors">
                                            <i class="ti ti-printer text-base"></i>
                                        </button>
                                        <button onclick="quickUpdateStatus(<?= $inv['id'] ?>, '<?= $inv['status'] ?>')" title="Update Status" class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors">
                                            <i class="ti ti-edit text-base"></i>
                                        </button>
                                        <button onclick="deleteInvoice(<?= $inv['id'] ?>)" title="Delete Invoice" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>


<!-- ========================================================================= -->
<!-- MODAL 1: CREATE NEW INVOICE MODAL -->
<!-- ========================================================================= -->
<div id="createInvoiceModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-10 flex justify-center items-start">
    <div class="bg-white w-full max-w-5xl rounded-3xl shadow-2xl border border-gray-100 overflow-hidden my-auto transform transition-all">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 bg-gray-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center text-white text-xl font-bold">
                    <i class="ti ti-file-invoice"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Create Wholesale Invoice</h3>
                    <p class="text-xs text-gray-400">Fill in details or select from customer and product suggestions.</p>
                </div>
            </div>
            <button onclick="closeCreateInvoiceModal()" class="text-gray-400 hover:text-white p-2 rounded-xl hover:bg-white/10 transition-colors">
                <i class="ti ti-x text-2xl"></i>
            </button>
        </div>

        <!-- Invoice Form / Preview Container -->
        <form id="invoiceForm" onsubmit="handleSaveInvoice(event)" class="p-6 sm:p-8 space-y-8">
            
            <!-- TOP INVOICE HEADER DETAILS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 p-6 bg-gray-50 rounded-2xl border border-gray-200/60">
                
                <!-- LEFT SIDE: KESARA ENTERPRISES DETAILS (Company Info) -->
                <div class="space-y-3 border-b md:border-b-0 md:border-r border-gray-200 pb-6 md:pb-0 md:pr-6">
                    <div class="flex items-center gap-2 text-brand font-black tracking-tight text-lg uppercase">
                        <i class="ti ti-building font-bold"></i>
                        <span>Kesara Enterprises (Pvt) Ltd</span>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed font-medium">
                        123 Industrial Zone, Colombo, Sri Lanka<br>
                        <strong>Phone:</strong> +94 11 234 5678 / +94 77 123 4567<br>
                        <strong>Email:</strong> sales@kesaraenterprises.lk<br>
                        <strong>BR Reg No:</strong> PV-123456
                    </p>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Invoice Number (Auto)</label>
                            <input type="text" id="inv_number" name="invoice_number" placeholder="Loading invoice #..." class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-mono font-bold text-gray-900 focus:outline-none focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Invoice Date</label>
                            <input type="date" id="inv_date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold text-gray-900 focus:outline-none focus:border-brand">
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: CUSTOMER DETAILS + SUGGEST WHOLESALE SHOPS -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Bill To (Wholesale Customer)</span>
                        <span class="text-[10px] bg-brand/10 text-brand font-bold px-2 py-0.5 rounded-full">Live Search Active</span>
                    </div>

                    <!-- CUSTOMER SUGGESTION INPUT -->
                    <div class="relative">
                        <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Suggest Wholesale Shop (Customer)</label>
                        <div class="relative">
                            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="cust_search" autocomplete="off" oninput="onCustomerSearchInput(this.value)" onfocus="onCustomerSearchInput(this.value)" placeholder="Type shop name, email, owner name, or phone..." class="w-full pl-9 pr-4 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold text-gray-900 focus:outline-none focus:border-brand">
                        </div>
                        <!-- Suggestions Dropdown -->
                        <div id="cust_suggestions" class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-xl z-30 max-h-48 overflow-y-auto hidden"></div>
                    </div>

                    <input type="hidden" id="cust_user_id" name="user_id" value="">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Shop / Business Name *</label>
                            <input type="text" id="cust_business_name" required placeholder="Shop Name" class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Contact Person Name *</label>
                            <input type="text" id="cust_name" required placeholder="Owner/Contact Name" class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Phone Number</label>
                            <input type="text" id="cust_phone" placeholder="+94 7X XXX XXXX" class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Email Address</label>
                            <input type="email" id="cust_email" placeholder="customer@email.com" class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Customer Order Number</label>
                            <input type="text" id="cust_order_number" name="customer_order_number" placeholder="e.g. PO-7890 / ORD-123" class="w-full px-3 py-1.5 bg-white border border-brand/40 rounded-lg text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-brand">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400">Billing Address & BR No.</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" id="cust_address" placeholder="Billing Address" class="col-span-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                            <input type="text" id="cust_br" placeholder="BR No." class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold">
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRODUCT SUGGESTION & STOCK AVAILABILITY SEARCH BAR -->
            <div class="p-5 bg-brand/5 border border-brand/20 rounded-2xl space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold uppercase tracking-wider text-brand flex items-center gap-2">
                        <i class="ti ti-package text-base"></i>
                        <span>Auto Suggest SKU & Product Name with Live Stock Availability</span>
                    </label>
                    <span class="text-[10px] text-gray-500 font-medium">Click suggested item to auto-fill SKU, Name, Size & Price</span>
                </div>
                
                <div class="relative">
                    <div class="relative">
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-brand text-base px-2"></i>
                        <input type="text" id="prod_search" autocomplete="off" oninput="onProductSearchInput(this.value)" onfocus="onProductSearchInput(this.value)" placeholder="Search products by SKU (e.g. KB-001) or Product Name..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-brand/30 rounded-xl text-xs font-bold text-gray-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 shadow-sm">
                    </div>
                    <!-- Product Suggestions Dropdown -->
                    <div id="prod_suggestions" class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-xl z-30 max-h-60 overflow-y-auto hidden"></div>
                </div>
            </div>

            <!-- PRODUCT ITEMS TABLE TEMPLATE -->
            <!-- Template Table Headers: Product Number | Product Name | Size | Qty | Unit Price | Total -->
            <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="bg-gray-100/70 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <span class="text-xs font-black uppercase text-gray-600 tracking-wider">Invoice Items Table</span>
                    <button type="button" onclick="addInvoiceItemRow()" class="text-xs font-bold text-brand hover:underline flex items-center gap-1">
                        <i class="ti ti-plus"></i> Add Blank Row
                    </button>
                </div>
                
                <table class="w-full text-left border-collapse" id="invoiceItemsTable">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-black uppercase text-gray-500 tracking-wider">
                            <th class="py-3 px-4 w-32">Product Number</th>
                            <th class="py-3 px-4">Product Name</th>
                            <th class="py-3 px-4 w-28">Size</th>
                            <th class="py-3 px-4 w-20 text-center">Qty</th>
                            <th class="py-3 px-4 w-32 text-right">Unit Price (LKR)</th>
                            <th class="py-3 px-4 w-36 text-right">Total (LKR)</th>
                            <th class="py-3 px-3 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItemsBody" class="divide-y divide-gray-100 text-xs">
                        <!-- Dynamic Item Rows inserted here -->
                    </tbody>
                </table>

                <!-- Summary Totals Footer -->
                <div class="bg-gray-50/80 px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-end items-end sm:items-center gap-6">
                    <div class="w-full sm:w-72 space-y-2 text-xs">
                        <div class="flex justify-between font-medium text-gray-600">
                            <span>Subtotal:</span>
                            <span id="summarySubtotal" class="font-mono font-bold text-gray-900">LKR 0.00</span>
                        </div>
                        <div class="flex justify-between items-center gap-2 font-medium text-gray-600">
                            <span>Discount (LKR):</span>
                            <input type="number" step="0.01" min="0" id="inv_discount" value="0.00" oninput="calculateInvoiceTotals()" class="w-24 px-2 py-1 bg-white border border-gray-300 rounded text-right text-xs font-mono font-bold">
                        </div>
                        <div class="flex justify-between items-center gap-2 font-medium text-gray-600">
                            <span>Tax / VAT (LKR):</span>
                            <input type="number" step="0.01" min="0" id="inv_tax" value="0.00" oninput="calculateInvoiceTotals()" class="w-24 px-2 py-1 bg-white border border-gray-300 rounded text-right text-xs font-mono font-bold">
                        </div>
                        <div class="flex justify-between font-black text-sm text-gray-900 border-t border-gray-300 pt-2">
                            <span>Grand Total:</span>
                            <span id="summaryGrandTotal" class="font-mono text-brand">LKR 0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM INVOICE DETAILS: PAYMENT POLICY & DUAL SIGNATURES -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200">
                
                <!-- PAYMENT POLICY SECTION -->
                <div class="space-y-2 bg-gray-50 p-5 rounded-2xl border border-gray-200">
                    <label class="block text-xs font-bold uppercase text-gray-700 tracking-wider flex items-center gap-2">
                        <i class="ti ti-file-text text-brand"></i>
                        <span>Payment Policy & Terms</span>
                    </label>
                    <textarea id="payment_policy" rows="5" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs text-gray-700 leading-relaxed focus:outline-none focus:border-brand">1. Payment is due within 14 days of invoice issuance date.
2. Cheques payable to Kesara Enterprises (Pvt) Ltd or Direct Bank Transfer to Commercial Bank AC #1234567890 (Branch: Pettah).
3. Goods once delivered cannot be returned without prior authorization from Kesara Enterprises management.</textarea>
                </div>

                <!-- SIGNATURES OF CUSTOMER AND KESARA SIDE -->
                <div class="space-y-4 bg-gray-50 p-5 rounded-2xl border border-gray-200">
                    <span class="block text-xs font-bold uppercase text-gray-700 tracking-wider flex items-center gap-2">
                        <i class="ti ti-signature text-brand"></i>
                        <span>Signatures (Customer & Kesara Enterprises)</span>
                    </span>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Customer Side Signature -->
                        <div class="bg-white p-3 rounded-xl border border-gray-200 text-center space-y-2">
                            <p class="text-[10px] font-bold uppercase text-gray-500">Customer Signature / Stamp</p>
                            <canvas id="customerCanvas" width="200" height="70" class="border border-dashed border-gray-300 rounded mx-auto cursor-crosshair bg-gray-50"></canvas>
                            <div class="flex justify-between items-center px-1">
                                <button type="button" onclick="clearCanvas('customerCanvas')" class="text-[10px] text-red-500 hover:underline">Clear</button>
                                <span class="text-[9px] text-gray-400">Draw signature</span>
                            </div>
                        </div>

                        <!-- Kesara Enterprises Side Signature -->
                        <div class="bg-white p-3 rounded-xl border border-gray-200 text-center space-y-2">
                            <p class="text-[10px] font-bold uppercase text-gray-500">Kesara Enterprises Signatory</p>
                            <canvas id="kesaraCanvas" width="200" height="70" class="border border-dashed border-gray-300 rounded mx-auto cursor-crosshair bg-gray-50"></canvas>
                            <div class="flex justify-between items-center px-1">
                                <button type="button" onclick="clearCanvas('kesaraCanvas')" class="text-[10px] text-red-500 hover:underline">Clear</button>
                                <span class="text-[9px] text-gray-400">Draw signature</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL ACTIONS / SUBMIT -->
            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeCreateInvoiceModal()" class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">
                    Cancel
                </button>
                <button type="submit" id="saveInvoiceBtn" class="px-8 py-2.5 bg-brand hover:bg-brand-dark text-white text-xs font-bold rounded-xl shadow-lg shadow-brand/20 transition-all flex items-center gap-2">
                    <i class="ti ti-check text-base"></i>
                    <span>Save & Issue Invoice</span>
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ========================================================================= -->
<!-- MODAL 2: PRINTABLE INVOICE VIEW MODAL -->
<!-- ========================================================================= -->
<div id="viewInvoiceModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-10 flex justify-center items-start">
    <div class="bg-white w-full max-w-4xl rounded-3xl shadow-2xl border border-gray-100 overflow-hidden my-auto">
        <!-- Actions Toolbar (No Print) -->
        <div class="no-print px-6 py-4 bg-gray-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i class="ti ti-printer text-brand text-2xl"></i>
                <h3 class="text-sm font-bold">Invoice Preview & Print</h3>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="triggerInvoicePrint()" class="px-5 py-2 bg-brand hover:bg-brand-dark text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                    <i class="ti ti-printer text-base"></i>
                    <span>Print / Save as PDF</span>
                </button>
                <button onclick="closeViewInvoiceModal()" class="text-gray-400 hover:text-white p-2 rounded-xl hover:bg-white/10 transition-colors">
                    <i class="ti ti-x text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- PRINT AREA (This content gets printed) -->
        <div id="invoice-print-area" class="p-8 md:p-12 space-y-8 bg-white text-gray-900 font-sans">
            
            <!-- TOP INVOICE HEADER -->
            <div class="flex justify-between items-start border-b-2 border-brand pb-6">
                <div>
                    <div class="flex items-center gap-2 text-2xl font-black text-brand tracking-tight uppercase">
                        <span>KESARA ENTERPRISES (PVT) LTD</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                        123 Industrial Zone, Colombo, Sri Lanka<br>
                        Phone: +94 11 234 5678 / +94 77 123 4567<br>
                        Email: sales@kesaraenterprises.lk | BR No: PV-123456
                    </p>
                </div>
                <div class="text-right space-y-1">
                    <span class="inline-block px-3 py-1 bg-brand text-white font-black text-sm tracking-wider uppercase rounded">INVOICE</span>
                    <h2 id="view_inv_number" class="text-lg font-mono font-bold text-gray-900 mt-1">INV-2026-0000</h2>
                    <p class="text-xs text-gray-500">Date: <span id="view_inv_date" class="font-mono text-gray-800">2026-08-28</span></p>
                    <p class="text-xs text-gray-500">Due Date: <span id="view_inv_due" class="font-mono text-gray-800">2026-09-11</span></p>
                </div>
            </div>

            <!-- CUSTOMER DETAILS HEADER -->
            <div class="bg-gray-50 p-5 rounded-xl border border-gray-200 flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block">Billed To (Customer):</span>
                    <h3 id="view_cust_business" class="text-base font-bold text-gray-900 mt-0.5">Wholesale Customer Name</h3>
                    <p id="view_cust_contact" class="text-xs text-gray-600 font-medium">Attn: Contact Person</p>
                    <p id="view_cust_address" class="text-xs text-gray-600 mt-1">Billing Address</p>
                </div>
                <div class="text-right text-xs text-gray-600 space-y-0.5">
                    <p>Customer Order No: <span id="view_cust_order_no" class="font-bold text-gray-900 font-mono">-</span></p>
                    <p>Phone: <span id="view_cust_phone" class="font-semibold text-gray-800">-</span></p>
                    <p>Email: <span id="view_cust_email" class="font-semibold text-gray-800">-</span></p>
                    <p>BR No: <span id="view_cust_br" class="font-semibold text-gray-800">-</span></p>
                </div>
            </div>

            <!-- INVOICE ITEMS TABLE -->
            <div>
                <table class="w-full text-left border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100 text-gray-800 text-xs font-black uppercase tracking-wider border-b border-gray-300">
                            <th class="py-3 px-4 border-r border-gray-300">Product Number</th>
                            <th class="py-3 px-4 border-r border-gray-300">Product Name</th>
                            <th class="py-3 px-4 border-r border-gray-300 w-24">Size</th>
                            <th class="py-3 px-4 text-center border-r border-gray-300 w-20">Qty</th>
                            <th class="py-3 px-4 text-right border-r border-gray-300 w-32">Unit Price (LKR)</th>
                            <th class="py-3 px-4 text-right w-36">Total (LKR)</th>
                        </tr>
                    </thead>
                    <tbody id="view_items_body" class="divide-y divide-gray-200 text-xs font-medium text-gray-800">
                        <!-- View items rows inserted dynamically -->
                    </tbody>
                </table>
            </div>

            <!-- TOTALS SUMMARY -->
            <div class="flex justify-end pt-2">
                <div class="w-72 space-y-1.5 text-xs text-gray-700">
                    <div class="flex justify-between border-b border-gray-200 py-1">
                        <span>Subtotal:</span>
                        <span id="view_subtotal" class="font-mono font-bold">LKR 0.00</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 py-1">
                        <span>Discount:</span>
                        <span id="view_discount" class="font-mono font-bold text-red-600">LKR 0.00</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 py-1">
                        <span>Tax / VAT:</span>
                        <span id="view_tax" class="font-mono font-bold">LKR 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm font-black text-gray-900 pt-2 border-t-2 border-gray-900">
                        <span>Total Payable:</span>
                        <span id="view_total" class="font-mono text-brand">LKR 0.00</span>
                    </div>
                </div>
            </div>

            <!-- PAYMENT POLICY & SIGNATURES -->
            <div class="grid grid-cols-2 gap-8 pt-6 border-t border-gray-300 text-xs">
                <div class="space-y-1">
                    <h4 class="font-bold text-gray-900 uppercase text-[11px] tracking-wider">Payment Policy & Notes:</h4>
                    <p id="view_policy" class="text-gray-600 leading-relaxed whitespace-pre-line text-[11px] bg-gray-50 p-3 rounded border border-gray-200">
                        Standard Payment Policy
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="flex flex-col justify-end items-center h-28 border-b border-gray-400 pb-1">
                        <img id="view_cust_sig_img" src="" class="max-h-16 hidden mb-1" alt="Customer Signature">
                        <span class="text-[10px] font-bold uppercase text-gray-500">Authorized Customer Signature</span>
                    </div>
                    <div class="flex flex-col justify-end items-center h-28 border-b border-gray-400 pb-1">
                        <img id="view_kesara_sig_img" src="" class="max-h-16 hidden mb-1" alt="Kesara Signature">
                        <span class="text-[10px] font-bold uppercase text-gray-500">For Kesara Enterprises</span>
                    </div>
                </div>
            </div>

            <div class="text-center text-[10px] text-gray-400 pt-6 border-t border-gray-200">
                Thank you for your business with Kesara Enterprises (Pvt) Ltd.
            </div>
        </div>
    </div>
</div>


<!-- ========================================================================= -->
<!-- JAVASCRIPT CONTROLLERS FOR INVOICES -->
<!-- ========================================================================= -->
<script>
// Global invoice state variables
let invoiceItems = [];
let customerCanvases = {};

document.addEventListener('DOMContentLoaded', function() {
    initSignatureCanvas('customerCanvas');
    initSignatureCanvas('kesaraCanvas');
    
    // Close search dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#cust_search') && !e.target.closest('#cust_suggestions')) {
            document.getElementById('cust_suggestions').classList.add('hidden');
        }
        if (!e.target.closest('#prod_search') && !e.target.closest('#prod_suggestions')) {
            document.getElementById('prod_suggestions').classList.add('hidden');
        }
    });
});

// Canvas Signature Initialization
function initSignatureCanvas(id) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0f172a';

    let drawing = false;

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDraw(e) {
        drawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDraw() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);

    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDraw);

    customerCanvases[id] = { canvas, ctx };
}

function clearCanvas(id) {
    const obj = customerCanvases[id];
    if (obj) {
        obj.ctx.clearRect(0, 0, obj.canvas.width, obj.canvas.height);
    }
}

// ----------------------------------------------------
// WHOLESALE SHOP (CUSTOMER) LIVE AUTO-COMPLETE
// ----------------------------------------------------
let custSearchTimer = null;
function onCustomerSearchInput(val) {
    clearTimeout(custSearchTimer);
    custSearchTimer = setTimeout(() => {
        fetch(`/api/invoices.php?action=search_customers&query=${encodeURIComponent(val)}`)
            .then(res => res.json())
            .then(res => {
                const box = document.getElementById('cust_suggestions');
                if (res.status === 'success' && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(c => {
                        html += `
                        <div onclick='selectCustomer(${JSON.stringify(c).replace(/'/g, "&#39;")})' class="p-3 hover:bg-brand/5 border-b border-gray-100 cursor-pointer transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900 text-xs">${escapeHtml(c.business_name)}</span>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase ${c.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}">${c.status}</span>
                            </div>
                            <div class="text-[11px] text-gray-500 mt-0.5">Owner: ${escapeHtml(c.full_name)} | ${escapeHtml(c.phone || c.email)}</div>
                        </div>`;
                    });
                    box.innerHTML = html;
                    box.classList.remove('hidden');
                } else {
                    box.innerHTML = `<div class="p-3 text-xs text-gray-400 text-center">No matching wholesale shops found</div>`;
                    box.classList.remove('hidden');
                }
            })
            .catch(() => {});
    }, 200);
}

function selectCustomer(cust) {
    document.getElementById('cust_user_id').value = cust.id || '';
    document.getElementById('cust_business_name').value = cust.business_name || '';
    document.getElementById('cust_name').value = cust.full_name || '';
    document.getElementById('cust_phone').value = cust.phone || cust.whatsapp_number || '';
    document.getElementById('cust_email').value = cust.email || '';
    document.getElementById('cust_address').value = cust.address || '';
    document.getElementById('cust_br').value = cust.br_number || '';
    document.getElementById('cust_search').value = cust.business_name;

    document.getElementById('cust_suggestions').classList.add('hidden');
}

// ----------------------------------------------------
// PRODUCT & STOCK AVAILABILITY LIVE AUTO-COMPLETE
// ----------------------------------------------------
let prodSearchTimer = null;
function onProductSearchInput(val) {
    clearTimeout(prodSearchTimer);
    prodSearchTimer = setTimeout(() => {
        fetch(`/api/invoices.php?action=search_products&query=${encodeURIComponent(val)}`)
            .then(res => res.json())
            .then(res => {
                const box = document.getElementById('prod_suggestions');
                if (res.status === 'success' && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(p => {
                        html += `
                        <div onclick='selectProduct(${JSON.stringify(p).replace(/'/g, "&#39;")})' class="p-3 hover:bg-brand/5 border-b border-gray-100 cursor-pointer transition-colors flex items-center justify-between">
                            <div>
                                <div class="font-bold text-gray-900 text-xs">${escapeHtml(p.name)} <span class="font-mono text-gray-500 font-bold bg-gray-100 px-1.5 py-0.5 rounded">SKU: ${escapeHtml(p.sku)}</span></div>
                                <div class="text-[11px] text-gray-500 mt-0.5">Sizes: ${escapeHtml(p.sizes || 'Free Size')} | Category: ${escapeHtml(p.category_name || 'General')} | Price: LKR ${parseFloat(p.base_price).toFixed(2)}</div>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold rounded-full border ${p.availability_class}">
                                    ${p.availability_status} (${p.total_stock} available)
                                </span>
                            </div>
                        </div>`;
                    });
                    box.innerHTML = html;
                    box.classList.remove('hidden');
                } else {
                    box.innerHTML = `<div class="p-3 text-xs text-gray-400 text-center">No matching products found</div>`;
                    box.classList.remove('hidden');
                }
            })
            .catch(() => {});
    }, 200);
}

function selectProduct(prod) {
    let firstSize = 'Free Size';
    if (prod.sizes && prod.sizes.trim() !== '') {
        const parts = prod.sizes.split(',');
        if (parts.length > 0) firstSize = parts[0].trim();
    }

    addInvoiceItemRow({
        product_id: prod.id,
        product_sku: prod.sku,
        product_name: prod.name,
        size: firstSize,
        quantity: 1,
        unit_price: parseFloat(prod.base_price) || 0
    });
    document.getElementById('prod_search').value = '';
    document.getElementById('prod_suggestions').classList.add('hidden');
}

// ----------------------------------------------------
// DYNAMIC TABLE ROW MANAGEMENT
// ----------------------------------------------------
function addInvoiceItemRow(item = {}) {
    const tbody = document.getElementById('invoiceItemsBody');

    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50/50 transition-colors';
    row.innerHTML = `
        <td class="py-2 px-3">
            <input type="hidden" class="item-prod-id" value="${item.product_id || ''}">
            <input type="text" class="item-sku w-full px-2 py-1 bg-white border border-gray-300 rounded font-mono text-xs font-bold" value="${escapeHtml(item.product_sku || '')}" placeholder="e.g. KB-001">
        </td>
        <td class="py-2 px-3">
            <input type="text" class="item-name w-full px-2 py-1 bg-white border border-gray-300 rounded text-xs font-semibold" value="${escapeHtml(item.product_name || '')}" placeholder="Product Name">
        </td>
        <td class="py-2 px-3">
            <input type="text" class="item-size w-full px-2 py-1 bg-white border border-gray-300 rounded text-xs font-semibold" value="${escapeHtml(item.size || 'M')}" placeholder="e.g. M, L, XL">
        </td>
        <td class="py-2 px-3">
            <input type="number" min="1" oninput="calculateInvoiceTotals()" class="item-qty w-full px-2 py-1 bg-white border border-gray-300 rounded text-center text-xs font-bold" value="${item.quantity || 1}">
        </td>
        <td class="py-2 px-3">
            <input type="number" step="0.01" min="0" oninput="calculateInvoiceTotals()" class="item-price w-full px-2 py-1 bg-white border border-gray-300 rounded text-right text-xs font-mono font-bold" value="${parseFloat(item.unit_price || 0).toFixed(2)}">
        </td>
        <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 item-row-total">
            LKR ${(parseFloat(item.unit_price || 0) * (item.quantity || 1)).toFixed(2)}
        </td>
        <td class="py-2 px-2 text-center">
            <button type="button" onclick="removeInvoiceItemRow(this)" class="p-1 text-red-400 hover:text-red-600 transition-colors">
                <i class="ti ti-trash text-base"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    calculateInvoiceTotals();
}

function removeInvoiceItemRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    calculateInvoiceTotals();
}

function calculateInvoiceTotals() {
    const rows = document.querySelectorAll('#invoiceItemsBody tr');
    let subtotal = 0;

    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const lineTotal = qty * price;
        row.querySelector('.item-row-total').textContent = 'LKR ' + lineTotal.toFixed(2);
        subtotal += lineTotal;
    });

    const discount = parseFloat(document.getElementById('inv_discount').value) || 0;
    const tax = parseFloat(document.getElementById('inv_tax').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount + tax);

    document.getElementById('summarySubtotal').textContent = 'LKR ' + subtotal.toFixed(2);
    document.getElementById('summaryGrandTotal').textContent = 'LKR ' + grandTotal.toFixed(2);
}

// ----------------------------------------------------
// SAVE & SUBMIT INVOICE
// ----------------------------------------------------
function handleSaveInvoice(e) {
    e.preventDefault();

    const items = [];
    const rows = document.querySelectorAll('#invoiceItemsBody tr');
    
    rows.forEach(row => {
        const prodId = row.querySelector('.item-prod-id').value;
        const sku = row.querySelector('.item-sku').value.trim();
        const name = row.querySelector('.item-name').value.trim();
        const size = row.querySelector('.item-size').value.trim();
        const qty = parseInt(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;

        if (name !== '' && qty > 0) {
            items.push({
                product_id: prodId,
                product_sku: sku || 'N/A',
                product_name: name,
                size: size || 'Free Size',
                quantity: qty,
                unit_price: price,
                total_price: qty * price
            });
        }
    });

    if (items.length === 0) {
        alert('Please add at least one valid product item to the invoice table.');
        return;
    }

    const customerCanvas = document.getElementById('customerCanvas');
    const kesaraCanvas = document.getElementById('kesaraCanvas');

    const payload = {
        action: 'save_invoice',
        user_id: document.getElementById('cust_user_id').value,
        business_name: document.getElementById('cust_business_name').value,
        customer_name: document.getElementById('cust_name').value,
        customer_phone: document.getElementById('cust_phone').value,
        customer_email: document.getElementById('cust_email').value,
        customer_order_number: document.getElementById('cust_order_number').value.trim(),
        customer_address: document.getElementById('cust_address').value,
        br_number: document.getElementById('cust_br').value,
        invoice_number: document.getElementById('inv_number').value,
        invoice_date: document.getElementById('inv_date').value,
        subtotal: parseFloat(document.getElementById('summarySubtotal').textContent.replace('LKR ', '')) || 0,
        discount_amount: parseFloat(document.getElementById('inv_discount').value) || 0,
        tax_amount: parseFloat(document.getElementById('inv_tax').value) || 0,
        total_amount: parseFloat(document.getElementById('summaryGrandTotal').textContent.replace('LKR ', '')) || 0,
        payment_policy: document.getElementById('payment_policy').value,
        customer_signature: customerCanvas ? customerCanvas.toDataURL() : null,
        kesara_signature: kesaraCanvas ? kesaraCanvas.toDataURL() : null,
        items: items
    };

    const submitBtn = document.getElementById('saveInvoiceBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="ti ti-loader animate-spin"></i> Saving...`;

    fetch('/api/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="ti ti-check"></i> Save & Issue Invoice`;

        if (res.status === 'success') {
            closeCreateInvoiceModal();
            // Open print view directly for newly created invoice
            viewInvoice(res.invoice_id);
            // Refresh background invoice list
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            alert('Error creating invoice: ' + res.message);
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="ti ti-check"></i> Save & Issue Invoice`;
        alert('Server communication error.');
    });
}

// ----------------------------------------------------
// VIEW & PRINT INVOICE DETAILS
// ----------------------------------------------------
function viewInvoice(id) {
    fetch(`/api/invoices.php?action=get_invoice&id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const inv = res.data;

                document.getElementById('view_inv_number').textContent = inv.invoice_number;
                document.getElementById('view_inv_date').textContent = inv.invoice_date || '-';
                document.getElementById('view_inv_due').textContent = inv.due_date || '-';

                document.getElementById('view_cust_business').textContent = inv.business_name || inv.customer_name;
                document.getElementById('view_cust_contact').textContent = 'Attn: ' + (inv.customer_name || 'N/A');
                document.getElementById('view_cust_address').textContent = inv.customer_address || 'Address Not Specified';
                document.getElementById('view_cust_order_no').textContent = inv.customer_order_number || '-';
                document.getElementById('view_cust_phone').textContent = inv.customer_phone || '-';
                document.getElementById('view_cust_email').textContent = inv.customer_email || '-';
                document.getElementById('view_cust_br').textContent = inv.br_number || '-';

                document.getElementById('view_subtotal').textContent = 'LKR ' + parseFloat(inv.subtotal).toFixed(2);
                document.getElementById('view_discount').textContent = 'LKR ' + parseFloat(inv.discount_amount).toFixed(2);
                document.getElementById('view_tax').textContent = 'LKR ' + parseFloat(inv.tax_amount).toFixed(2);
                document.getElementById('view_total').textContent = 'LKR ' + parseFloat(inv.total_amount).toFixed(2);

                document.getElementById('view_policy').textContent = inv.payment_policy || 'Standard payment terms apply.';

                // Items table
                let itemsHtml = '';
                if (inv.items && inv.items.length > 0) {
                    inv.items.forEach(item => {
                        itemsHtml += `
                            <tr class="border-b border-gray-200">
                                <td class="py-2.5 px-4 font-mono font-bold border-r border-gray-200">${escapeHtml(item.product_sku)}</td>
                                <td class="py-2.5 px-4 border-r border-gray-200">${escapeHtml(item.product_name)}</td>
                                <td class="py-2.5 px-4 border-r border-gray-200 font-semibold">${escapeHtml(item.size || 'Free Size')}</td>
                                <td class="py-2.5 px-4 text-center font-bold border-r border-gray-200">${item.quantity}</td>
                                <td class="py-2.5 px-4 text-right font-mono border-r border-gray-200">LKR ${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold">LKR ${parseFloat(item.total_price).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('view_items_body').innerHTML = itemsHtml;

                // Signatures
                const custImg = document.getElementById('view_cust_sig_img');
                if (inv.customer_signature && inv.customer_signature.length > 100) {
                    custImg.src = inv.customer_signature;
                    custImg.classList.remove('hidden');
                } else {
                    custImg.classList.add('hidden');
                }

                const kesaraImg = document.getElementById('view_kesara_sig_img');
                if (inv.kesara_signature && inv.kesara_signature.length > 100) {
                    kesaraImg.src = inv.kesara_signature;
                    kesaraImg.classList.remove('hidden');
                } else {
                    kesaraImg.classList.add('hidden');
                }

                document.getElementById('viewInvoiceModal').classList.remove('hidden');
            }
        });
}

function triggerInvoicePrint() {
    window.print();
}

function closeViewInvoiceModal() {
    document.getElementById('viewInvoiceModal').classList.add('hidden');
}

// ----------------------------------------------------
// INVOICE MODAL HELPERS & SEARCH FILTERS
// ----------------------------------------------------
function openCreateInvoiceModal() {
    document.getElementById('invoiceForm').reset();
    document.getElementById('cust_user_id').value = '';
    document.getElementById('invoiceItemsBody').innerHTML = '';
    clearCanvas('customerCanvas');
    clearCanvas('kesaraCanvas');

    // Auto-generate next Invoice Number
    fetch('/api/invoices.php?action=next_invoice_number')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.invoice_number) {
                document.getElementById('inv_number').value = res.invoice_number;
            }
        })
        .catch(() => {});

    // Add 2 initial blank rows
    addInvoiceItemRow();
    addInvoiceItemRow();

    document.getElementById('createInvoiceModal').classList.remove('hidden');
}

function closeCreateInvoiceModal() {
    document.getElementById('createInvoiceModal').classList.add('hidden');
}

function filterInvoices(status) {
    document.querySelectorAll('.inv-tab-btn').forEach(btn => {
        btn.classList.remove('bg-brand', 'text-white', 'shadow-md');
        btn.classList.add('bg-gray-100', 'text-gray-600');
    });

    const activeTab = document.getElementById('tab-' + status);
    if (activeTab) {
        activeTab.classList.remove('bg-gray-100', 'text-gray-600');
        activeTab.classList.add('bg-brand', 'text-white', 'shadow-md');
    }

    const rows = document.querySelectorAll('.invoice-row');
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function handleInvoiceSearch() {
    const q = document.getElementById('invoiceSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.invoice-row');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function quickUpdateStatus(id, currentStatus) {
    const newStatus = prompt(`Update status for invoice #${id}:\nOptions: issued, paid, draft, cancelled`, currentStatus);
    if (newStatus && ['issued', 'paid', 'draft', 'cancelled'].includes(newStatus.toLowerCase())) {
        fetch('/api/invoices.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', id: id, status: newStatus.toLowerCase() })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    }
}

function deleteInvoice(id) {
    if (confirm('Are you sure you want to delete this invoice record?')) {
        fetch('/api/invoices.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_invoice', id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
