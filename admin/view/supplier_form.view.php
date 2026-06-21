<?php
$mode = $_GET['mode'] ?? 'add';
$is_edit = ($mode === 'edit');
$title = $is_edit ? 'Edit Supplier' : 'Add New Supplier';
?>

<div class="flex-1 flex overflow-hidden bg-gray-50/50">
    <!-- Form Content -->
    <div class="flex-1 overflow-y-auto p-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest mb-6">
            <a href="/admin-suppliers" class="hover:text-brand transition-colors">Suppliers</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900"><?php echo $title; ?></span>
        </nav>

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
                    <button class="px-6 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">Discard</button>
                    <button class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:opacity-90 transition-all">Save Changes</button>
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
                                <input type="text" value="<?php echo $is_edit ? 'Sri Lanka Cotton Mills' : ''; ?>" placeholder="Enter legal company name" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Category <span class="text-red-500">*</span></label>
                                <select class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium cursor-pointer">
                                    <option>Fabric</option>
                                    <option>Elastic / Trims</option>
                                    <option>Packaging</option>
                                    <option>Full Product (CMT)</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Current Status <span class="text-red-500">*</span></label>
                                <select class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium cursor-pointer">
                                    <option>Active</option>
                                    <option selected>Preferred</option>
                                    <option>On Hold</option>
                                    <option>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Registered Address</label>
                            <textarea rows="3" placeholder="Full business address for invoicing" 
                                class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium resize-none"><?php echo $is_edit ? 'No. 45, Cotton Lane, Colombo 10, Western Province' : ''; ?></textarea>
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
                                <input type="text" value="<?php echo $is_edit ? 'Mr. Roshan Silva' : ''; ?>" placeholder="Full name of representative" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Job Title</label>
                                <input type="text" value="<?php echo $is_edit ? 'Sales Manager' : ''; ?>" placeholder="e.g. Sales Director" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" value="<?php echo $is_edit ? 'slcm@cottonmills.lk' : ''; ?>" placeholder="company@email.com" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" value="<?php echo $is_edit ? '+94 11 456 7890' : ''; ?>" placeholder="+94 XX XXX XXXX" 
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
                        <div class="grid grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Lead Time (Days)</label>
                                <input type="number" value="<?php echo $is_edit ? '7' : ''; ?>" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Min Order Qty</label>
                                <input type="number" value="<?php echo $is_edit ? '500' : ''; ?>" 
                                    class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">MOQ Unit</label>
                                <select class="w-full px-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium cursor-pointer">
                                    <option>Metres</option>
                                    <option>Kg</option>
                                    <option>Pieces</option>
                                    <option>Rolls</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-4">Supplied Items</label>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <button class="group flex items-center gap-2 px-4 py-2 bg-brand/5 border border-brand/20 rounded-xl text-xs font-bold text-brand hover:bg-brand/10 transition-all">
                                    Combed Cotton Fabric <i class="ti ti-x text-brand/40 group-hover:text-brand"></i>
                                </button>
                                <button class="group flex items-center gap-2 px-4 py-2 bg-brand/5 border border-brand/20 rounded-xl text-xs font-bold text-brand hover:bg-brand/10 transition-all">
                                    Modal Fabric <i class="ti ti-x text-brand/40 group-hover:text-brand"></i>
                                </button>
                                <button class="group flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-400 hover:border-brand/30 hover:text-brand transition-all">
                                    <i class="ti ti-plus"></i> Add Item
                                </button>
                            </div>
                            <div class="relative group">
                                <input type="text" placeholder="Search or add items..." 
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none font-medium">
                                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-8 border-t border-gray-200 flex justify-between items-center">
                    <?php if ($is_edit): ?>
                    <button class="flex items-center gap-2 px-6 py-3 bg-red-50 text-red-600 rounded-2xl text-sm font-bold hover:bg-red-100 transition-all">
                        <i class="ti ti-trash text-lg"></i>
                        Delete Supplier
                    </button>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    <div class="flex gap-4">
                        <button class="px-8 py-3 bg-white border border-gray-200 rounded-2xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">Discard</button>
                        <button class="px-8 py-3 bg-brand text-brand-light rounded-2xl text-sm font-bold shadow-xl shadow-brand/20 hover:opacity-90 transition-all">Save Supplier</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Metadata -->
    <div class="w-80 bg-white border-l border-gray-100 p-8 overflow-y-auto">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-[0.2em] mb-6">Supplier Visibility</h3>
        
        <div class="space-y-6">
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                <div>
                    <p class="text-xs font-bold text-gray-900">Active Status</p>
                    <p class="text-[10px] text-gray-500">Visible in procurement</p>
                </div>
                <button class="w-10 h-6 bg-emerald-500 rounded-full relative transition-all">
                    <div class="absolute right-1 top-1 w-4 h-4 bg-white rounded-full"></div>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                <div>
                    <p class="text-xs font-bold text-gray-900">Preferred Partner</p>
                    <p class="text-[10px] text-gray-500">Highlight in lists</p>
                </div>
                <button class="w-10 h-6 bg-emerald-500 rounded-full relative transition-all">
                    <div class="absolute right-1 top-1 w-4 h-4 bg-white rounded-full"></div>
                </button>
            </div>
        </div>

        <div class="my-8 border-t border-gray-50"></div>

        <?php if ($is_edit): ?>
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-[0.2em] mb-6">Legacy Metrics</h3>
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">Total Orders</span>
                <span class="text-xs font-bold text-gray-900">34</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">YTD Spend</span>
                <span class="text-xs font-bold text-gray-900 text-brand">LKR 2.4M</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">Lead Variance</span>
                <span class="text-xs font-bold text-emerald-600">-0.2 Days</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-12 p-6 bg-amber-50 rounded-3xl border border-amber-100">
            <h3 class="text-[10px] font-bold text-amber-800 uppercase tracking-widest mb-3">Internal Hold Note</h3>
            <textarea rows="4" placeholder="Reason for hold..." 
                class="w-full p-3 bg-white/50 border-none rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-200 outline-none resize-none placeholder:text-amber-300"></textarea>
            <p class="text-[9px] text-amber-600 mt-2 italic leading-tight">This note is for internal audit only and is not shared with the supplier.</p>
        </div>
    </div>
</div>
