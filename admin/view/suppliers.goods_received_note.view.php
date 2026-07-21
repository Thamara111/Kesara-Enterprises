<?php
/**
 * Goods Received Note View - Database Integration & Form Processing
 * Handles the creation of GRNs when a purchase order is fulfilled by a supplier.
 */

$success_msg = "";
$error_msg = "";

$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : (isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_grn' && isset($pdo)) {
    $received_by = trim($_POST['received_by'] ?? '');
    $received_at = trim($_POST['received_date'] ?? date('Y-m-d'));
    $note = trim($_POST['note'] ?? '');
    $qtys_received = $_POST['qtys_received'] ?? []; // Map of item_id -> quantity_received
    $admin_id = $_SESSION['admin_id'] ?? 1;

    try {
        $pdo->beginTransaction();

        // 1. Insert GRN record
        $stmt = $pdo->prepare("INSERT INTO goods_received_notes (po_id, received_by, received_at, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$po_id, $received_by, $received_at, $note]);
        $grn_id = $pdo->lastInsertId();

        // 2. Update PO line item quantities and Inventory
        $stmt_up_status = $pdo->prepare("
            UPDATE products 
            SET status = CASE 
                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) = 0 THEN 'Out of Stock'
                WHEN (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = products.id) <= 50 THEN 'Low Stock'
                ELSE 'In Stock'
            END
            WHERE id = ?
        ");
        foreach ($qtys_received as $poi_id => $qty_rcvd) {
            $qty_rcvd = (int)$qty_rcvd;
            if ($qty_rcvd <= 0) continue;

            // Fetch PO item details
            $item_stmt = $pdo->prepare("SELECT product_id, item_name, qty_ordered, qty_received FROM purchase_order_items WHERE id = ?");
            $item_stmt->execute([$poi_id]);
            $item = $item_stmt->fetch();
            if (!$item) continue;

            // Update qty_received in PO items
            $up_poi = $pdo->prepare("UPDATE purchase_order_items SET qty_received = qty_received + ? WHERE id = ?");
            $up_poi->execute([$qty_rcvd, $poi_id]);

            // Find or create inventory item for this product
            if ($item['product_id']) {
                $inv_stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? LIMIT 1");
                $inv_stmt->execute([$item['product_id']]);
                $inv = $inv_stmt->fetch();

                if (!$inv) {
                    // Create inventory item
                    $ins_inv = $pdo->prepare("INSERT INTO inventory (product_id, size, colour, quantity, restock_min) VALUES (?, 'M', 'Standard', 0, 200)");
                    $ins_inv->execute([$item['product_id']]);
                    $inv_id = $pdo->lastInsertId();
                    $qty_before = 0;
                } else {
                    $inv_id = $inv['id'];
                    $qty_before = (int)$inv['quantity'];
                }

                $qty_after = $qty_before + $qty_rcvd;

                // Update inventory quantity
                $up_inv = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $up_inv->execute([$qty_after, $inv_id]);

                // Log inventory change
                $log_stmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, adj_type, qty_before, qty_after, note, admin_id) VALUES (?, 'add', ?, ?, ?, ?)");
                $log_stmt->execute([$inv_id, $qty_before, $qty_after, "Received via GRN-2025-" . str_pad($grn_id, 4, '0', STR_PAD_LEFT) . " against PO-2025-" . str_pad($po_id, 4, '0', STR_PAD_LEFT), $admin_id]);
                
                // Dynamically update product status based on new total stock
                $stmt_up_status->execute([$item['product_id']]);
            }
        }

        // 3. Automatically check and update PO status (partial or received)
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE po_id = ? AND qty_ordered > qty_received");
        $check_stmt->execute([$po_id]);
        $remaining_items = (int)$check_stmt->fetchColumn();

        $new_status = ($remaining_items === 0) ? 'received' : 'partial';
        $up_po = $pdo->prepare("UPDATE purchase_orders SET status = ?, received_at = ? WHERE id = ?");
        $up_po->execute([$new_status, $received_at, $po_id]);

        $pdo->commit();
        
        // Send email notification to admin about stock update
        require_once __DIR__ . "/../../src/Mailer.php";
        $subject = "Stock Updated: GRN-2025-" . str_pad($grn_id, 4, '0', STR_PAD_LEFT);
        $body = "<h3>Goods Received Note Processed</h3>" .
                "<p><strong>PO ID:</strong> PO-2025-" . str_pad($po_id, 4, '0', STR_PAD_LEFT) . "</p>" .
                "<p><strong>Received By:</strong> " . htmlspecialchars($received_by) . "</p>" .
                "<p>Inventory has been updated successfully.</p>";
        \App\Mailer::send('admin@kesara.lk', $subject, $body);

        $success_msg = "Goods receipt confirmed and inventory updated successfully!";
        
        // Redirect to PO view
        echo "<script>showToast('Goods receipt confirmed and inventory updated successfully!', 'success'); setTimeout(() => window.location.href = '/admin-purchase-orders', 3000);</script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "Transaction failed: " . $e->getMessage();
    }
}

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

// Fetch ALL open POs for the selector dropdown
$open_pos_list = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $open_stmt = $pdo->query("SELECT po.id, po.status, po.expected_at, s.name AS supplier_name
                                  FROM purchase_orders po
                                  JOIN suppliers s ON po.supplier_id = s.id
                                  WHERE po.status IN ('sent', 'partial', 'overdue')
                                  ORDER BY po.ordered_at DESC");
        $open_pos_list = $open_stmt->fetchAll();
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
        $error_msg = "Database Load Error: " . $e->getMessage();
    }
}

$lines = [];
if (!empty($po_items)) {
    foreach ($po_items as $item) {
        $lines[] = [
            'id' => $item['id'],
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

$po_num = $po_data ? 'PO-2025-' . str_pad($po_data['id'], 4, '0', STR_PAD_LEFT) : 'PO-2025-0000';
$po_supplier = $po_data ? htmlspecialchars($po_data['supplier_name']) : 'N/A';
$po_raised = $po_data ? date('d M Y', strtotime($po_data['ordered_at'])) : 'N/A';
$po_total_val = $po_data ? (float)$po_data['total'] : 0.00;
$po_value = 'LKR ' . number_format($po_total_val, 2);
$grn_ref = 'GRN-2025-' . str_pad($po_id, 4, '0', STR_PAD_LEFT) . 'B';
?>

<?php if (!empty($error_msg)): ?>
    <div class="m-8 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-semibold text-red-700">
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast(<?= json_encode($error_msg) ?>, 'error');
        });
    </script>
<?php endif; ?>

<form method="POST" class="flex-1 flex overflow-hidden">
    <input type="hidden" name="action" value="confirm_grn">
    <input type="hidden" name="po_id" value="<?= $po_id ?>">
    
    <!-- Form Pane (Left) -->
    <div class="flex-1 flex flex-col min-w-0 bg-white border-r border-gray-100 overflow-hidden">
        <!-- Scrollable content wrapper -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <a href="/admin-purchase-orders" class="hover:text-brand cursor-pointer font-semibold transition-colors">Purchase orders</a>
                <i class="ti ti-chevron-right text-[10px]" aria-hidden="true"></i>
                <a href="/admin-purchase-orders" class="hover:text-brand cursor-pointer font-semibold transition-colors"><?= $po_num ?></a>
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
                    <button type="button" id="open-grn-ref" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-all shadow-sm">View Ref</button>
                    <a href="/admin-purchase-orders" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm flex items-center justify-center">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">Confirm Receipt & Update Stock</button>
                </div>
            </div>

            <!-- Warning Banner (Partial Delivery) -->
            <?php if (!empty($past_grns)): ?>
            <div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-start gap-3 text-xs text-blue-700">
                <i class="ti ti-info-circle text-blue-600 text-lg flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                <span class="leading-relaxed font-medium">This is a subsequent delivery against PO <?= $po_num ?>. Enter only the quantities received in this delivery batch.</span>
            </div>
            <?php endif; ?>

            <!-- PO Selector -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-3">Purchase Order</h3>
                <?php if (empty($open_pos_list)): ?>
                    <p class="text-sm text-gray-400 font-medium">No open purchase orders found.</p>
                <?php else: ?>
                <select onchange="window.location.href='/admin-goods-received?po_id='+this.value"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-800 outline-none focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all cursor-pointer">
                    <?php foreach ($open_pos_list as $op): ?>
                        <?php
                            $op_ref = 'PO-2025-' . str_pad($op['id'], 4, '0', STR_PAD_LEFT);
                            $op_status_labels = ['sent' => '● Sent', 'partial' => '◑ Partial', 'overdue' => '⚠ Overdue'];
                            $op_label = $op_status_labels[$op['status']] ?? ucfirst($op['status']);
                            $op_date = date('d M Y', strtotime($op['expected_at']));
                        ?>
                        <option value="<?= $op['id'] ?>" <?= $op['id'] == $po_id ? 'selected' : '' ?>>
                            <?= $op_ref ?> — <?= htmlspecialchars($op['supplier_name']) ?> · Expected <?= $op_date ?> [<?= $op_label ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Delivery Details Form -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-2">Delivery details</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Date Received <span class="text-red-500">*</span></label>
                        <input type="date" name="received_date" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-500">Received By <span class="text-red-500">*</span></label>
                        <input type="text" name="received_by" value="" required class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none">
                    </div>
                </div>
                
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-500">Notes</label>
                    <textarea name="note" rows="2" placeholder="Any notes about this delivery..." class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none resize-none">Batch delivered. Inspected and counted.</textarea>
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
                <div class="grn-line-item grid grid-cols-[1fr_80px_80px_110px_120px] gap-4 items-center py-4 border-b border-gray-50 last:border-b-0"
                     id="grn-line-<?= $idx ?>"
                     data-idx="<?= $idx ?>"
                     data-name="<?= htmlspecialchars($line['name']) ?>"
                     data-ordered="<?= $line['ordered'] ?>"
                     data-prev="<?= $line['prev'] ?>"
                     data-remaining="<?= $line['remaining'] ?>"
                     data-inv-before="<?= $line['invBefore'] ?>"
                     data-threshold="<?= $line['threshold'] ?>"
                     data-unit="<?= htmlspecialchars($line['unit']) ?>">
                    <div>
                        <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($line['name']) ?></p>
                        <p class="text-[11px] text-gray-400 font-medium mt-0.5">Expected remaining: <?= $line['remaining'] ?> <?= $line['unit'] ?></p>
                    </div>
                    <span class="text-sm text-gray-500 text-center font-bold"><?= $line['ordered'] ?></span>
                    <span class="text-sm text-gray-500 text-center font-bold"><?= $line['prev'] ?></span>
                    <div class="flex items-center gap-1.5 justify-center">
                        <input type="number" name="qtys_received[<?= $line['id'] ?>]" id="qty-<?= $idx ?>" value="<?= $line['remaining'] ?>" min="0" max="<?= $line['remaining'] ?>" 
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
                <div id="shortage-warn" class="mt-4 p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-start gap-3 text-xs text-amber-700" style="display:none">
                    <i class="ti ti-alert-triangle text-amber-600 text-lg flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                    <span id="shortage-text" class="leading-relaxed font-semibold">Shortage detected. PO will remain open for the remaining quantity.</span>
                </div>
            </div>

            <!-- Action buttons bottom -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-50">
                <a href="/admin-purchase-orders" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm flex items-center justify-center">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">Confirm Receipt & Update Stock</button>
            </div>
        </div>
    </div>

    <!-- Side Reference Pane (Right) -->
    <!-- Backdrop -->
    <div id="grn-ref-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px]" onclick="closeGRNRefPane()"></div>
    <div id="grn-ref-pane" class="fixed inset-y-0 right-0 z-50 w-[300px] max-w-full bg-white flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto space-y-8 relative">
            <button type="button" onclick="closeGRNRefPane()" class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
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
        </div>
    </div>
</form>

<script>
function updateLine(idx) {
  var lineEl = document.getElementById('grn-line-'+idx);
  if (!lineEl) return;
  var line = {
      remaining: parseInt(lineEl.dataset.remaining),
      prev: parseInt(lineEl.dataset.prev),
      ordered: parseInt(lineEl.dataset.ordered),
      invBefore: parseInt(lineEl.dataset.invBefore),
      threshold: parseInt(lineEl.dataset.threshold),
      unit: lineEl.dataset.unit,
      name: lineEl.dataset.name
  };
  var input = document.getElementById('qty-'+idx);
  var qty = Math.min(parseInt(input.value)||0, line.remaining);
  input.value = qty;
  var totalRcvd = line.prev + qty;
  var remaining = line.ordered - totalRcvd;
  var statusEl = document.getElementById('status-'+idx);
  var labelEl = document.getElementById('total-label-'+idx);
  
  if (remaining <= 0) {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'Full receipt';
    labelEl.className = 'text-[10px] font-bold text-emerald-600 mt-1';
    labelEl.textContent = totalRcvd + ' / ' + line.ordered;
  } else if (qty === 0) {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-red-50 text-red-700 border border-red-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'None received';
    labelEl.className = 'text-[10px] font-bold text-red-600 mt-1';
    labelEl.textContent = line.prev + ' / ' + line.ordered;
  } else {
    statusEl.querySelector('.badge').className = 'badge px-3 py-1 bg-amber-50 text-amber-700 border border-amber-100 rounded-full text-[10px] font-bold';
    statusEl.querySelector('.badge').textContent = 'Short by ' + remaining;
    labelEl.className = 'text-[10px] font-bold text-amber-600 mt-1';
    labelEl.textContent = totalRcvd + ' / ' + line.ordered;
  }
  
  var newInv = line.invBefore + qty;
  var pct = Math.min(100, Math.round(newInv / line.threshold * 100));
  
  var barEl = document.getElementById('inv-'+idx+'-bar');
  barEl.style.width = pct + '%';
  barEl.className = 'h-full rounded-full transition-all duration-500 ' + (pct >= 80 ? 'bg-emerald-500' : pct >= 40 ? 'bg-amber-500' : 'bg-red-500');

  document.getElementById('inv-'+idx+'-after').textContent = line.invBefore + ' → ' + newInv;
  
  var aboveThresh = newInv >= line.threshold;
  var labelTextEl = document.getElementById('inv-'+idx+'-label');
  labelTextEl.textContent = '+' + qty + ' ' + line.unit + ' · ' + (aboveThresh ? 'above threshold' : 'still below threshold');
  labelTextEl.className = 'text-[10px] font-bold mt-2 ' + (aboveThresh ? 'text-emerald-600' : 'text-amber-600');
  
  updateShortageWarn();
}

function updateShortageWarn() {
  var shortages = [];
  document.querySelectorAll('.grn-line-item').forEach(lineEl => {
    var i = lineEl.dataset.idx;
    var ordered = parseInt(lineEl.dataset.ordered);
    var prev = parseInt(lineEl.dataset.prev);
    var name = lineEl.dataset.name;
    var unit = lineEl.dataset.unit;
    var qty = parseInt(document.getElementById('qty-'+i).value)||0;
    var rem = ordered - prev - qty;
    if (rem > 0) shortages.push(name + ': ' + rem + ' ' + unit + ' short');
  });
  var warn = document.getElementById('shortage-warn');
  if (shortages.length) {
    warn.style.display = 'flex';
    document.getElementById('shortage-text').textContent = shortages.join('. ') + '. PO will remain open for the remaining quantity.';
  } else {
    warn.style.display = 'none';
  }
}

function closeGRNRefPane() {
  var pane = document.getElementById('grn-ref-pane');
  var backdrop = document.getElementById('grn-ref-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
}

function openGRNRefPane() {
  var pane = document.getElementById('grn-ref-pane');
  var backdrop = document.getElementById('grn-ref-backdrop');
  if (pane) pane.classList.remove('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('hidden');
      requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
  }
}

document.getElementById('open-grn-ref').addEventListener('click', openGRNRefPane);

document.querySelectorAll('.grn-line-item').forEach(el => updateLine(el.dataset.idx));

closeGRNRefPane();
</script>
