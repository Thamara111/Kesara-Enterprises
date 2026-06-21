<?php
/**
 * Goods Received Note View - Database Integration
 */

$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
// Fallback if not specified: get the first non-received PO
if ($po_id === 0 && isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT id FROM purchase_orders WHERE status IN ('sent', 'partial', 'overdue') ORDER BY ordered_at DESC LIMIT 1");
        $po_row = $stmt->fetch();
        if ($po_row) {
            $po_id = (int)$po_row['id'];
        }
    } catch (\Exception $e) {}
}

$po_data = null;
$po_items = [];
$past_grns = [];

if ($po_id > 0 && isset($pdo) && $pdo !== null) {
    try {
        // Fetch PO and supplier
        $stmt = $pdo->prepare("SELECT po.id, po.status, po.ordered_at, po.expected_at, po.total, 
                                     s.name AS supplier_name, s.contact_person
                              FROM purchase_orders po
                              JOIN suppliers s ON po.supplier_id = s.id
                              WHERE po.id = ?");
        $stmt->execute([$po_id]);
        $po_data = $stmt->fetch();
        
        if ($po_data) {
            // Fetch PO items
            $item_stmt = $pdo->prepare("SELECT poi.id, poi.product_id, poi.item_name, poi.qty_ordered, poi.qty_received, poi.unit_cost,
                                               inv.quantity AS inv_before, inv.restock_min AS threshold
                                        FROM purchase_order_items poi
                                        LEFT JOIN inventory inv ON inv.product_id = poi.product_id
                                        WHERE poi.po_id = ?");
            $item_stmt->execute([$po_id]);
            $po_items = $item_stmt->fetchAll();
            
            // Fetch past GRNs
            $grn_stmt = $pdo->prepare("SELECT id, received_by, received_at, note FROM goods_received_notes WHERE po_id = ? ORDER BY received_at DESC");
            $grn_stmt->execute([$po_id]);
            $past_grns = $grn_stmt->fetchAll();
        }
    } catch (\Exception $e) {
        $db_error = $e->getMessage();
    }
}

$lines = [];
if (!empty($po_items)) {
    foreach ($po_items as $item) {
        $lines[] = [
            'ordered' => (int)$item['qty_ordered'],
            'prev' => (int)$item['qty_received'],
            'remaining' => max(0, (int)$item['qty_ordered'] - (int)$item['qty_received']),
            'invBefore' => (int)($item['inv_before'] ?? 0),
            'threshold' => (int)($item['threshold'] ?? 100),
            'unit' => 'pcs',
            'name' => $item['item_name']
        ];
    }
}

if (empty($lines)) {
    $lines = [
      [ 'ordered'=>500, 'prev'=>300, 'remaining'=>200, 'invBefore'=>820, 'threshold'=>1000, 'unit'=>'rolls', 'name'=>'Branded elastic' ],
      [ 'ordered'=>1000, 'prev'=>600, 'remaining'=>400, 'invBefore'=>240, 'threshold'=>1000, 'unit'=>'rolls', 'name'=>'Plain elastic 2cm' ]
    ];
}

$po_num = $po_data ? 'PO-2025-' . str_pad($po_data['id'], 4, '0', STR_PAD_LEFT) : 'PO-2025-0041';
$po_supplier = $po_data ? htmlspecialchars($po_data['supplier_name']) : 'Premium Elastic';
$po_raised = $po_data ? date('d M Y', strtotime($po_data['ordered_at'])) : '8 May 2025';
$po_total_val = $po_data ? (float)$po_data['total'] : 94000.00;
$po_value = 'LKR ' . ($po_total_val >= 1000 ? number_format($po_total_val/1000, 0) . 'K' : number_format($po_total_val, 2));
$grn_ref = 'GRN-2025-' . str_pad($po_id, 4, '0', STR_PAD_LEFT) . 'B';
?>
<!-- Goods Received Note View -->
<h2 class="sr-only">Goods received note form mockup for Kesara Enterprises wholesale admin platform</h2>

<div class="flex-1 flex overflow-hidden">
    <!-- Form Pane (Left) -->
    <div class="flex-1 flex flex-col min-w-0 bg-white border-r border-gray-100 overflow-hidden">
        <!-- Scrollable content wrapper -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="hover:text-brand cursor-pointer font-semibold transition-colors" onclick="location.href='admin_index.php?view=purchase_orders'">Purchase orders</span>
                <i class="ti ti-chevron-right text-[10px]" aria-hidden="true"></i>
                <span class="hover:text-brand cursor-pointer font-semibold transition-colors" onclick="location.href='admin_index.php?view=purchase_orders'"><?= $po_num ?></span>
                <i class="ti ti-chevron-right text-[10px]" aria-hidden="true"></i>
                <span class="text-gray-900 font-extrabold">New GRN</span>
            </div>

            <!-- Page Header -->
            <div class="flex justify-between items-center flex-wrap gap-4 border-b border-gray-50 pb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Record Goods Received</h1>
                    <p class="text-sm text-gray-500 mt-1"><?= $grn_ref ?> · against <?= $po_num ?></p>
                </div>
                <div class="flex gap-3">
                    <button id="open-grn-ref" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-all shadow-sm">View Ref</button>
                    <button class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm" onclick="sendPrompt('What should the purchase orders page show for Kesara Enterprises admin?')">Cancel</button>
                    <button class="px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20" onclick="confirmGRN()">Confirm Receipt & Update Stock ↗</button>
                </div>
            </div>

            <!-- Warning Banner (Partial Delivery) -->
            <?php if (!empty($past_grns)): ?>
            <div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-start gap-3 text-xs text-blue-700">
                <i class="ti ti-info-circle text-blue-600 text-lg flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                <span class="leading-relaxed font-medium">This is a subsequent delivery against PO <?= $po_num ?>. Enter only the quantities received in this delivery batch.</span>
            </div>
            <?php endif; ?>

            <!-- Delivery Details Form -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-2">Delivery details</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Date Received <span class="text-red-500">*</span></label>
                        <input type="date" value="2025-05-13" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Received By <span class="text-red-500">*</span></label>
                        <input type="text" value="Kamal Rathnayake" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Delivery Note / Ref No.</label>
                        <input type="text" placeholder="Supplier's delivery note number" value="DN-PE-4421" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Condition on Arrival</label>
                        <select class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none cursor-pointer">
                            <option selected>Good — no damage</option>
                            <option>Minor damage noted</option>
                            <option>Significant damage — rejected</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Notes</label>
                    <textarea rows="2" placeholder="Any notes about this delivery..." class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none resize-none">Second batch delivered on time. All items inspected and counted.</textarea>
                </div>
            </div>

            <!-- Line Items Received Grid -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <!-- Grid Headers -->
                <div class="grid grid-cols-[1fr_80px_80px_110px_120px] gap-4 pb-3 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                    <span>Item</span>
                    <span class="text-center">On PO</span>
                    <span class="text-center">Prev. Rcvd</span>
                    <span class="text-center">This Delivery</span>
                    <span class="text-right">Status</span>
                </div>

                <?php foreach ($lines as $idx => $line): ?>
                <div class="grid grid-cols-[1fr_80px_80px_110px_120px] gap-4 items-center py-4 border-b border-gray-50 last:border-b-0">
                    <div>
                        <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($line['name']) ?></p>
                        <p class="text-[11px] text-gray-400 font-medium mt-0.5">Expected remaining: <?= $line['remaining'] ?> <?= $line['unit'] ?></p>
                    </div>
                    <span class="text-sm text-gray-500 text-center font-bold"><?= $line['ordered'] ?></span>
                    <span class="text-sm text-gray-500 text-center font-bold"><?= $line['prev'] ?></span>
                    <div class="flex items-center gap-1.5 justify-center">
                        <input type="number" id="qty-<?= $idx ?>" value="<?= $line['remaining'] ?>" min="0" max="<?= $line['remaining'] ?>" 
                            class="w-16 px-2 py-1 bg-gray-50 border border-gray-200 rounded-lg text-sm text-center font-extrabold text-gray-900 outline-none focus:bg-white focus:border-brand/20" oninput="updateLine(<?= $idx ?>)">
                        <span class="text-xs text-gray-400 font-medium"><?= $line['unit'] ?></span>
                    </div>
                    <div class="text-right" id="status-<?= $idx ?>">
                        <span class="badge px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full text-[10px] font-bold">Full receipt</span>
                        <p class="text-[10px] font-bold text-emerald-600 mt-1" id="total-label-<?= $idx ?>"><?= $line['ordered'] ?> / <?= $line['ordered'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Shortage Warning banner -->
                <div id="shortage-warn" class="mt-4 p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-start gap-3 text-xs text-amber-700">
                    <i class="ti ti-alert-triangle text-amber-600 text-lg flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                    <span id="shortage-text" class="leading-relaxed font-semibold">Shortage detected. PO will remain open for the remaining quantity.</span>
                </div>
            </div>

            <!-- Quality Check Section -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-2">Quality Check</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Qty Rejected (Damaged)</label>
                        <input type="number" value="0" min="0" placeholder="0" class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                        <p class="text-[10px] text-gray-400 font-medium">Rejected items are not added to stock</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Rejection Reason</label>
                        <select class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none cursor-pointer">
                            <option selected>None</option>
                            <option>Damaged in transit</option>
                            <option>Wrong specification</option>
                            <option>Quality below standard</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Action buttons bottom -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-50">
                <button class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">Save as draft</button>
                <button class="px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20" onclick="confirmGRN()">Confirm receipt &amp; update stock ↗</button>
            </div>
        </div>
    </div>

    <!-- Side Reference Pane (Right) -->
    <!-- Backdrop -->
    <div id="grn-ref-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeGRNRefPane()"></div>
    <div id="grn-ref-pane" class="fixed inset-y-0 right-0 z-50 w-[300px] max-w-full bg-white flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto space-y-8 relative">
            <button onclick="closeGRNRefPane()" class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                <i class="ti ti-x text-xl"></i>
            </button>
            <!-- PO Reference Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">PO Reference</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">PO number</span>
                        <span class="text-xs font-bold text-brand hover:underline cursor-pointer"><?= $po_num ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Supplier</span>
                        <span class="text-xs font-bold text-gray-900"><?= $po_supplier ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Raised</span>
                        <span class="text-xs font-bold text-gray-900"><?= $po_raised ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">PO value</span>
                        <span class="text-xs font-bold text-gray-900"><?= $po_value ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">GRNs so far</span>
                        <span class="text-xs font-bold text-gray-900"><?= count($past_grns) ?></span>
                    </div>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Inventory Impact Preview -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-1">Inventory Impact</h3>
                <p class="text-[10px] text-gray-400 mb-4">Stock will be updated on confirm</p>

                <div class="space-y-6">
                    <?php foreach ($lines as $idx => $line): ?>
                    <div>
                        <div class="flex justify-between items-center text-xs mb-2">
                            <span class="text-gray-600 font-semibold"><?= htmlspecialchars($line['name']) ?></span>
                            <span class="font-bold text-emerald-600" id="inv-<?= $idx ?>-after"><?= $line['invBefore'] ?> → <?= $line['invBefore'] + $line['remaining'] ?></span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <?php 
                            $pct = $line['threshold'] > 0 ? min(100, round((($line['invBefore'] + $line['remaining']) / $line['threshold']) * 100)) : 0;
                            ?>
                            <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width: <?= $pct ?>%" id="inv-<?= $idx ?>-bar"></div>
                        </div>
                        <p class="text-[10px] font-bold text-emerald-600 mt-2" id="inv-<?= $idx ?>-label">+<?= $line['remaining'] ?> <?= $line['unit'] ?> · above threshold</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Past GRNs -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Past GRNs on this PO</h3>
                <?php if (empty($past_grns)): ?>
                <p class="text-xs text-gray-400 italic">No past GRNs recorded on this PO.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($past_grns as $grn): ?>
                    <div class="p-4 bg-gray-50 border border-gray-100 rounded-2xl shadow-sm">
                        <p class="text-xs font-bold text-gray-900">GRN-2025-<?= str_pad($grn['id'], 5, '0', STR_PAD_LEFT) ?></p>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5"><?= date('d M Y', strtotime($grn['received_at'])) ?> · <?= htmlspecialchars($grn['received_by']) ?></p>
                        <?php if ($grn['note']): ?>
                        <p class="text-xs text-gray-600 mt-2 leading-relaxed italic"><?= htmlspecialchars($grn['note']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Actions Checkbox flow list -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">What happens on confirm</h3>
                <div class="space-y-3">
                    <div class="flex gap-2.5 items-start text-xs text-gray-600">
                        <i class="ti ti-circle-check text-emerald-500 text-base flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                        <span>GRN record saved with timestamp</span>
                    </div>
                    <div class="flex gap-2.5 items-start text-xs text-gray-600">
                        <i class="ti ti-circle-check text-emerald-500 text-base flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                        <span>Inventory quantities updated immediately</span>
                    </div>
                    <div class="flex gap-2.5 items-start text-xs text-gray-600">
                        <i class="ti ti-circle-check text-emerald-500 text-base flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                        <span>PO status updated to partial or received</span>
                    </div>
                    <div class="flex gap-2.5 items-start text-xs text-gray-600">
                        <i class="ti ti-circle-check text-emerald-500 text-base flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                        <span>Inventory adjustment log entry written</span>
                    </div>
                    <div class="flex gap-2.5 items-start text-xs text-gray-600">
                        <i class="ti ti-circle-check text-emerald-500 text-base flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                        <span>Supplier on-time score recalculated</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Confirm Overlay Dialog Modal -->
<div id="confirm-overlay" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-sm w-full text-center flex flex-col items-center">
        <div class="w-12 h-12 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
            <i class="ti ti-circle-check text-2xl" aria-hidden="true"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 tracking-tight"><?= $grn_ref ?> confirmed</h3>
        <p class="text-xs text-gray-500 mt-2 mb-6 leading-relaxed">Stock has been updated successfully.</p>
        <div class="grid grid-cols-2 gap-3 w-full">
            <button class="px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all" onclick="sendPrompt('What should the purchase orders page show for Kesara Enterprises admin?')">Back to PO ↗</button>
            <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all" onclick="sendPrompt('What should the inventory management page include for Kesara Enterprises admin?')">View inventory ↗</button>
        </div>
    </div>
</div>

<script>
const lines = <?php echo json_encode($lines); ?>;

function updateLine(idx) {
  const input = document.getElementById('qty-'+idx);
  const qty = Math.min(parseInt(input.value)||0, lines[idx].remaining);
  input.value = qty;
  const totalRcvd = lines[idx].prev + qty;
  const remaining = lines[idx].ordered - totalRcvd;
  const statusEl = document.getElementById('status-'+idx);
  const labelEl = document.getElementById('total-label-'+idx);
  
  if (remaining <= 0) {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'Full receipt';
    labelEl.className = 'text-[10px] font-bold text-emerald-600 mt-1';
    labelEl.textContent = totalRcvd + ' / ' + lines[idx].ordered;
  } else if (qty === 0) {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-red-50 text-red-700 border border-red-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'None received';
    labelEl.className = 'text-[10px] font-bold text-red-600 mt-1';
    labelEl.textContent = lines[idx].prev + ' / ' + lines[idx].ordered;
  } else {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-amber-50 text-amber-700 border border-amber-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'Short by ' + remaining;
    labelEl.className = 'text-[10px] font-bold text-amber-600 mt-1';
    labelEl.textContent = totalRcvd + ' / ' + lines[idx].ordered;
  }
  
  const newInv = lines[idx].invBefore + qty;
  const pct = Math.min(100, Math.round(newInv / lines[idx].threshold * 100));
  
  const barEl = document.getElementById('inv-'+idx+'-bar');
  barEl.style.width = pct + '%';
  barEl.className = 'h-full rounded-full transition-all duration-500 ' + (pct >= 80 ? 'bg-emerald-500' : pct >= 40 ? 'bg-amber-500' : 'bg-red-500');

  document.getElementById('inv-'+idx+'-after').textContent = lines[idx].invBefore + ' → ' + newInv;
  
  const aboveThresh = newInv >= lines[idx].threshold;
  const labelTextEl = document.getElementById('inv-'+idx+'-label');
  labelTextEl.textContent = '+' + qty + ' ' + lines[idx].unit + ' · ' + (aboveThresh ? 'above threshold' : 'still below threshold');
  labelTextEl.className = 'text-[10px] font-bold mt-2 ' + (aboveThresh ? 'text-emerald-600' : 'text-amber-600');
  
  updateShortageWarn();
}

function updateShortageWarn() {
  const shortages = [];
  lines.forEach((l,i) => {
    const qty = parseInt(document.getElementById('qty-'+i).value)||0;
    const rem = l.ordered - l.prev - qty;
    if (rem > 0) shortages.push(l.name + ': ' + rem + ' ' + l.unit + ' short');
  });
  const warn = document.getElementById('shortage-warn');
  if (shortages.length) {
    warn.style.display = 'flex';
    document.getElementById('shortage-text').textContent = shortages.join('. ') + '. PO will remain open for the remaining quantity.';
  } else {
    warn.style.display = 'none';
  }
}

function confirmGRN() {
  const overlay = document.getElementById('confirm-overlay');
  overlay.style.display = 'flex';
}

function closeGRNRefPane() {
  const pane = document.getElementById('grn-ref-pane');
  const backdrop = document.getElementById('grn-ref-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
}

function openGRNRefPane() {
  const pane = document.getElementById('grn-ref-pane');
  const backdrop = document.getElementById('grn-ref-backdrop');
  if (pane) pane.classList.remove('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('hidden');
      requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
  }
}

document.getElementById('open-grn-ref').addEventListener('click', openGRNRefPane);

lines.forEach((_, idx) => updateLine(idx));

closeGRNRefPane();
</script>
