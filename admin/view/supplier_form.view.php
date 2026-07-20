<?php
$mode = $_GET['mode'] ?? 'add';
$is_edit = ($mode === 'edit');
$title = $is_edit ? 'Edit Supplier' : 'Add New Supplier';

$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$supplier = [
    'name' => '',
    'email' => '',
    'contact_person' => '',
    'phone' => '',
    'address' => '',
    'payment_terms' => 'Net 30',
    'category' => 'Fabric',
    'status' => 'active',
    'hold_reason' => '',
    'hold_since' => ''
];
$supplied_items = [];

$success_msg = '';
$error_msg = '';

if ($is_edit && $supplier_id > 0 && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $res = $stmt->fetch();
        if ($res) {
            $supplier = $res;
            
            // Get supplied items
            $item_stmt = $pdo->prepare("SELECT item_name FROM supplier_items WHERE supplier_id = ?");
            $item_stmt->execute([$supplier_id]);
            $supplied_items = $item_stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $error_msg = "Supplier not found.";
            $is_edit = false;
            $title = 'Add New Supplier';
        }
    } catch (Exception $e) {
        $error_msg = "Error loading supplier: " . $e->getMessage();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
        $category = trim($_POST['category'] ?? 'Fabric');
        $status = trim($_POST['status'] ?? 'active');
        $hold_reason = trim($_POST['hold_reason'] ?? '');
        $hold_since = ($status === 'on_hold') ? date('Y-m-d') : null;
        
        $items_raw = trim($_POST['supplied_items'] ?? '');
        $items_arr = array_filter(array_map('trim', explode(',', $items_raw)));

        if (empty($name) || empty($email)) {
            $error_msg = "Company name and Email are required.";
        } else {
            try {
                if ($is_edit && $supplier_id > 0) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, email = ?, contact_person = ?, phone = ?, address = ?, payment_terms = ?, category = ?, status = ?, hold_reason = ?, hold_since = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $category, $status, $hold_reason, $hold_since, $supplier_id]);
                    $success_msg = "Supplier updated successfully.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, email, contact_person, phone, address, payment_terms, category, status, hold_reason, hold_since) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $contact_person, $phone, $address, $payment_terms, $category, $status, $hold_reason, $hold_since]);
                    $supplier_id = $pdo->lastInsertId();
                    
                    // Send welcome email to supplier
                    require_once __DIR__ . "/../../src/Mailer.php";
                    $subject = "Welcome to Kesara Enterprises Supplier Network";
                    $body = "<h3>Hello " . htmlspecialchars($contact_person) . ",</h3>" .
                            "<p>Your company <strong>" . htmlspecialchars($name) . "</strong> has been registered as a supplier with Kesara Enterprises.</p>" .
                            "<p>We look forward to working with you.</p>";
                    \App\Mailer::send($email, $subject, $body);

                    $success_msg = "Supplier created successfully.";
                }

                // Update supplied items
                $del_stmt = $pdo->prepare("DELETE FROM supplier_items WHERE supplier_id = ?");
                $del_stmt->execute([$supplier_id]);
                
                if (!empty($items_arr)) {
                    $ins_stmt = $pdo->prepare("INSERT INTO supplier_items (supplier_id, item_name) VALUES (?, ?)");
                    foreach ($items_arr as $itm) {
                        $ins_stmt->execute([$supplier_id, $itm]);
                    }
                }

                echo "<script>showToast('Supplier saved successfully.', 'success'); setTimeout(() => window.location.href = '/admin-suppliers', 3000);</script>";
                exit;
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete' && $is_edit && $supplier_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$supplier_id]);
            echo "<script>showToast('Supplier deleted successfully.', 'success'); setTimeout(() => window.location.href = '/admin-suppliers', 3000);</script>";
            exit;
        } catch (Exception $e) {
            $error_msg = "Database error during deletion: " . $e->getMessage();
        }
    }
}
?>

<form method="POST" id="supplierForm" class="flex-1 flex overflow-hidden bg-gray-50/50" data-turbo="false">
    <input type="hidden" name="action" id="formAction" value="save">
    <input type="hidden" name="supplied_items" id="suppliedItemsInput" value="<?php echo htmlspecialchars(implode(',', $supplied_items)); ?>">

    <!-- Form Content -->
    <div class="flex-1 overflow-y-auto p-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest mb-6">
            <a href="/admin-suppliers" class="hover:text-brand transition-colors">Suppliers</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900"><?php echo $title; ?></span>
        </nav>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-semibold text-red-700">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showToast(<?= json_encode($error_msg) ?>, 'error');
                });
            </script>
        <?php endif; ?>

        <div class="max-w-4xl">
            <!-- Header -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight"><?php echo $title; ?></h1>
                    <p class="text-gray-500 mt-1">
                        <?php echo $is_edit ? 'Update supplier information and procurement details' : 'Register a new supply chain partner in the system'; ?>
                    </p>
                </div>
                <div class="flex gap-3">
                    <a href="/admin-suppliers" class="px-6 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all flex items-center justify-center">Discard</a>
                    <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:opacity-90 transition-all">Save Changes</button>
                </div>
            </div>

            <!-- Form Sections -->
            <div class="space-y-6">
                <!-- Section 1: Basic Info -->
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/30">
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Basic Information</h2>
                    </div>
                    <div class="p-8 space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Supplier / Company Name <span class="text-red-500">*</span></label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($supplier['name']); ?>" placeholder="Enter legal company name" required
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Category <span class="text-red-500">*</span></label>
                                <select name="category" class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium cursor-pointer">
                                    <option value="Fabric" <?php echo $supplier['category'] === 'Fabric' ? 'selected' : ''; ?>>Fabric</option>
                                    <option value="Elastic / Trims" <?php echo $supplier['category'] === 'Elastic / Trims' ? 'selected' : ''; ?>>Elastic / Trims</option>
                                    <option value="Packaging" <?php echo $supplier['category'] === 'Packaging' ? 'selected' : ''; ?>>Packaging</option>
                                    <option value="Full Product (CMT)" <?php echo $supplier['category'] === 'Full Product (CMT)' ? 'selected' : ''; ?>>Full Product (CMT)</option>
                                    <option value="Other" <?php echo $supplier['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Current Status <span class="text-red-500">*</span></label>
                                <select name="status" id="statusSelect" class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium cursor-pointer">
                                    <option value="active" <?php echo $supplier['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="preferred" <?php echo $supplier['status'] === 'preferred' ? 'selected' : ''; ?>>Preferred</option>
                                    <option value="on_hold" <?php echo $supplier['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                    <option value="inactive" <?php echo $supplier['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Registered Address</label>
                            <textarea name="address" rows="3" placeholder="Full business address for invoicing" 
                                class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium resize-none"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Primary Contact -->
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/30">
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Primary Contact</h2>
                    </div>
                    <div class="p-8 space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Name <span class="text-red-500">*</span></label>
                                <input type="text" name="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>" placeholder="Full name of representative" required
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Terms</label>
                                <input type="text" name="payment_terms" value="<?php echo htmlspecialchars($supplier['payment_terms']); ?>" placeholder="e.g. Net 30" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>" placeholder="company@email.com" required
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($supplier['phone']); ?>" placeholder="+94 XX XXX XXXX" required
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Supply Logic -->
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/30">
                        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Procurement Details</h2>
                    </div>
                    <div class="p-8 space-y-8">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-4">Supplied Items</label>
                            <div class="flex flex-wrap gap-2 mb-4" id="suppliedItemsContainer">
                                <!-- Tags dynamically added by JS -->
                            </div>
                            <div class="flex gap-2">
                                <input type="text" id="addItemInput" placeholder="Add custom item name..." 
                                    class="flex-1 px-4 py-2 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                                <button type="button" onclick="addSuppliedItem()" class="px-4 py-2 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all">Add Item</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-8 border-t border-gray-200 flex justify-between items-center">
                    <?php if ($is_edit): ?>
                    <button type="button" onclick="deleteSupplier()" class="flex items-center gap-2 px-6 py-3 bg-red-50 text-red-600 rounded-2xl text-sm font-bold hover:bg-red-100 transition-all">
                        <i class="ti ti-trash text-lg"></i>
                        Delete Supplier
                    </button>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    <div class="flex gap-4">
                        <a href="/admin-suppliers" class="px-8 py-3 bg-white border border-gray-200 rounded-2xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all flex items-center justify-center">Discard</a>
                        <button type="submit" class="px-8 py-3 bg-brand text-brand-light rounded-2xl text-sm font-bold shadow-xl shadow-brand/20 hover:opacity-90 transition-all">Save Supplier</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Metadata -->
    <div class="w-80 bg-white border-l border-gray-100 p-8 overflow-y-auto">
        <div class="p-6 bg-amber-50 rounded-3xl border border-amber-100" id="holdNoteContainer">
            <h3 class="text-[10px] font-bold text-amber-800 uppercase tracking-widest mb-3">Internal Hold Note</h3>
            <textarea name="hold_reason" rows="4" placeholder="Reason for hold..." 
                class="w-full p-3 bg-white/50 border-none rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-200 outline-none resize-none placeholder:text-amber-300"><?php echo htmlspecialchars($supplier['hold_reason']); ?></textarea>
            <p class="text-[9px] text-amber-600 mt-2 italic leading-tight">This note is for internal audit only and is not shared with the supplier.</p>
        </div>
    </div>
</form>

<script>
var suppliedItems = [];

// Initialize supplied items
var initialItems = document.getElementById('suppliedItemsInput').value;
if (initialItems) {
    suppliedItems = initialItems.split(',').filter(item => item.trim() !== '');
}

function renderTags() {
    var container = document.getElementById('suppliedItemsContainer');
    container.innerHTML = '';
    
    suppliedItems.forEach((item, idx) => {
        var tag = document.createElement('span');
        tag.className = 'group flex items-center gap-2 px-4 py-2 bg-brand/5 border border-brand/20 rounded-xl text-xs font-bold text-brand';
        tag.innerHTML = `${item} <button type="button" onclick="removeTag(${idx})" class="ti ti-x text-brand/40 group-hover:text-brand"></button>`;
        container.appendChild(tag);
    });
    
    document.getElementById('suppliedItemsInput').value = suppliedItems.join(',');
}

function addSuppliedItem() {
    var input = document.getElementById('addItemInput');
    var val = input.value.trim();
    if (val && !suppliedItems.includes(val)) {
        suppliedItems.push(val);
        renderTags();
        input.value = '';
    }
}

function removeTag(idx) {
    suppliedItems.splice(idx, 1);
    renderTags();
}

function deleteSupplier() {
    uiConfirm("Are you sure you want to delete this supplier? This action cannot be undone.", () => {
        document.getElementById('formAction').value = 'delete';
        document.getElementById('supplierForm').submit();
    });
}

// Initial render
renderTags();
</script>
