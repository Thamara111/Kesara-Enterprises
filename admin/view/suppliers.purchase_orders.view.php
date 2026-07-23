<?php
/**
 * Purchase Orders View - Database Integration & Interaction
 * Manages purchase orders sent to suppliers for restocking inventory.
 */

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $po_id = (int)($_POST['po_id'] ?? 0);
    
    if ($action === 'raise_po' && isset($pdo)) {
        $supplier_id = (int)$_POST['supplier_id'];
        $expected_at = $_POST['expected_at'];
        $item_names = $_POST['item_names'] ?? [];
        $item_qtys = $_POST['item_qtys'] ?? [];
        $item_costs = $_POST['item_costs'] ?? [];
        
        $total = 0;
        for ($i = 0; $i < count($item_names); $i++) {
            $total += (int)$item_qtys[$i] * (float)$item_costs[$i];
        }
        
        try {
            // Fetch supplier details (name, email, contact)
            $supp_stmt = $pdo->prepare("SELECT name, email, contact_person, payment_terms FROM suppliers WHERE id = ?");
            $supp_stmt->execute([$supplier_id]);
            $supp = $supp_stmt->fetch();
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, status, ordered_at, expected_at, total) VALUES (?, 'sent', NOW(), ?, ?)");
            $stmt->execute([$supplier_id, $expected_at, $total]);
            $new_po_id = $pdo->lastInsertId();
            $po_ref = 'PO-2025-' . str_pad($new_po_id, 4, '0', STR_PAD_LEFT);
            
            $item_stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, item_name, qty_ordered, qty_received, unit_cost) VALUES (?, NULL, ?, ?, 0, ?)");
            $item_rows_html = '';
            $item_total = 0;
            for ($i = 0; $i < count($item_names); $i++) {
                if (empty($item_names[$i])) continue;
                $qty  = (int)$item_qtys[$i];
                $cost = (float)$item_costs[$i];
                $item_stmt->execute([$new_po_id, $item_names[$i], $qty, $cost]);
                $line_total = $qty * $cost;
                $item_total += $line_total;
                $item_rows_html .= '
                    <tr>
                        <td style="padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;color:#1f2937;">' . htmlspecialchars($item_names[$i]) . '</td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;color:#374151;text-align:center;">' . $qty . '</td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;color:#374151;text-align:right;">LKR ' . number_format($cost, 2) . '</td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:700;color:#0F6E56;text-align:right;">LKR ' . number_format($line_total, 2) . '</td>
                    </tr>';
            }
            
            $pdo->commit();
            
            // Send PO email to supplier
            require_once __DIR__ . '/../../src/Mailer.php';
            $contact_name = $supp['contact_person'] ?: $supp['name'];
            $expected_formatted = date('d M Y', strtotime($expected_at));
            $email_body = '
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f9fafb;">
<div style="max-width:600px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);">
    <div style="background:#0F6E56;padding:32px 40px;">
        <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;letter-spacing:-0.5px;">Purchase Order — ' . $po_ref . '</h1>
        <p style="margin:8px 0 0;color:rgba(255,255,255,0.75);font-size:13px;">Kesara Enterprises · ' . date('d M Y') . '</p>
    </div>
    <div style="padding:32px 40px;">
        <p style="font-size:14px;color:#374151;margin:0 0 24px;">Dear <strong>' . htmlspecialchars($contact_name) . '</strong>,</p>
        <p style="font-size:14px;color:#374151;margin:0 0 24px;">Please find below our Purchase Order <strong>' . $po_ref . '</strong>. Kindly confirm receipt and arrange delivery by the expected date.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;border-collapse:collapse;margin-bottom:24px;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="padding:10px 16px;font-size:11px;text-transform:uppercase;color:#6b7280;text-align:left;letter-spacing:0.05em;">Item</th>
                    <th style="padding:10px 16px;font-size:11px;text-transform:uppercase;color:#6b7280;text-align:center;letter-spacing:0.05em;">Qty</th>
                    <th style="padding:10px 16px;font-size:11px;text-transform:uppercase;color:#6b7280;text-align:right;letter-spacing:0.05em;">Unit Cost</th>
                    <th style="padding:10px 16px;font-size:11px;text-transform:uppercase;color:#6b7280;text-align:right;letter-spacing:0.05em;">Total</th>
                </tr>
            </thead>
            <tbody>' . $item_rows_html . '</tbody>
            <tfoot>
                <tr style="background:#f9fafb;">
                    <td colspan="3" style="padding:12px 16px;font-size:13px;font-weight:700;color:#1f2937;text-align:right;">Order Total</td>
                    <td style="padding:12px 16px;font-size:15px;font-weight:800;color:#0F6E56;text-align:right;">LKR ' . number_format($item_total, 2) . '</td>
                </tr>
            </tfoot>
        </table>
        
        <table width="100%" style="margin-bottom:28px;">
            <tr>
                <td style="font-size:12px;color:#6b7280;padding:4px 0;">Expected Delivery</td>
                <td style="font-size:12px;font-weight:700;color:#1f2937;text-align:right;padding:4px 0;">' . $expected_formatted . '</td>
            </tr>
            <tr>
                <td style="font-size:12px;color:#6b7280;padding:4px 0;">Payment Terms</td>
                <td style="font-size:12px;font-weight:700;color:#1f2937;text-align:right;padding:4px 0;">' . htmlspecialchars($supp['payment_terms'] ?: 'Net 30') . '</td>
            </tr>
        </table>
        
        <p style="font-size:13px;color:#6b7280;margin:0;">Please reply to this email to confirm the order. Thank you for your continued partnership.</p>
    </div>
    <div style="background:#f9fafb;padding:20px 40px;border-top:1px solid #f0f0f0;">
        <p style="margin:0;font-size:11px;color:#9ca3af;">Kesara Enterprises · Wholesale Garment Manufacturer · noreply@kesara.lk</p>
    </div>
</div>
</body>
</html>';
            
            \App\Mailer::send($supp['email'], 'Purchase Order ' . $po_ref . ' from Kesara Enterprises', $email_body);
            
            // JS redirect — header() can't be used here as head.php has already sent HTML output
            echo "<script>showToast('Purchase Order " . $po_ref . " raised and emailed to " . htmlspecialchars($supp['name'], ENT_QUOTES) . " successfully!', 'success'); setTimeout(() => window.location.href = '/admin-purchase-orders', 3000);</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Error creating Purchase Order: " . $e->getMessage();
        }
    } elseif ($action === 'cancel_po' && $po_id > 0 && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $success_msg = "Purchase Order cancelled and deleted successfully.";
        } catch (Exception $e) {
            $error_msg = "Error cancelling Purchase Order: " . $e->getMessage();
        }
    } elseif ($action === 'resend_po' && $po_id > 0 && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'sent', ordered_at = NOW() WHERE id = ?");
            $stmt->execute([$po_id]);
            $success_msg = "Purchase Order resent/dispatched successfully.";
        } catch (Exception $e) {
            $error_msg = "Error resending Purchase Order: " . $e->getMessage();
        }
    }
}

$admin_pos = [];
$suppliers_list = [];
$total_pos = 0;
$sent_pending_count = 0;
$partial_count = 0;
$overdue_count = 0;

if (isset($pdo) && $pdo !== null) {
    try {
        $suppliers_list = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();
        
        $prod_list = $pdo->query("SELECT p.name AS p_name, p.colors, p.sizes FROM products p ORDER BY p.name ASC")->fetchAll();
        $inv_options = '<option value="">Select an Item...</option>';
        foreach ($prod_list as $prod) {
            $colors = !empty($prod['colors']) ? array_map('trim', explode(',', $prod['colors'])) : ['Default'];
            $sizes = !empty($prod['sizes']) ? array_map('trim', explode(',', $prod['sizes'])) : ['Default'];
            
            foreach ($colors as $c) {
                foreach ($sizes as $s) {
                    if (empty($c)) $c = 'Default';
                    if (empty($s)) $s = 'Default';
                    $comboName = htmlspecialchars($prod['p_name'] . ' · ' . $c . ' · ' . $s);
                    $inv_options .= '<option value="' . $comboName . '">' . $comboName . '</option>';
                }
            }
        }
        
        $stmt = $pdo->query("SELECT po.id, po.status, po.ordered_at, po.expected_at, po.received_at, po.total, 
                                    s.name AS supplier_name, s.contact_person, s.payment_terms
                             FROM purchase_orders po
                             JOIN suppliers s ON po.supplier_id = s.id
                             ORDER BY po.ordered_at DESC");
        $pos_db = $stmt->fetchAll();
        $total_pos = count($pos_db);

        foreach ($pos_db as $po) {
            $po_id_formatted = 'PO-2025-' . str_pad($po['id'], 4, '0', STR_PAD_LEFT);
            $ordered_date = date('d M Y', strtotime($po['ordered_at']));
            $expected_date = date('d M Y', strtotime($po['expected_at']));
            
            $status = strtolower($po['status']);
            $badge = 'bg-gray-50 border-gray-100 text-gray-700';
            $badgeText = ucfirst($status);
            $alertType = 'info';
            $expectedColorClass = 'text-gray-900';
            
            if ($status === 'overdue') {
                $badge = 'bg-red-50 border-red-100 text-red-700';
                $badgeText = 'Overdue';
                $alertType = 'overdue';
                $expectedColorClass = 'text-red-600';
                $overdue_count++;
            } elseif ($status === 'partial') {
                $badge = 'bg-amber-50 border-amber-100 text-amber-700';
                $badgeText = 'Partial';
                $alertType = 'warn';
                $expectedColorClass = 'text-amber-700';
                $partial_count++;
            } elseif ($status === 'received') {
                $badge = 'bg-emerald-50 border-emerald-100 text-emerald-700';
                $badgeText = 'Received';
                $alertType = 'ok';
            } elseif ($status === 'sent') {
                $badge = 'bg-blue-50 border-blue-100 text-blue-700';
                $badgeText = 'Sent';
                $alertType = 'info';
                $sent_pending_count++;
            } else {
                $sent_pending_count++;
            }
            
            // Fetch items
            $item_stmt = $pdo->prepare("SELECT product_id, item_name, qty_ordered, qty_received, unit_cost FROM purchase_order_items WHERE po_id = ?");
            $item_stmt->execute([$po['id']]);
            $items_db = $item_stmt->fetchAll();
            
            $items = [];
            foreach ($items_db as $it) {
                $qty_ordered = (int)$it['qty_ordered'];
                $qty_received = (int)$it['qty_received'];
                $val = 'LKR ' . number_format($qty_ordered * (float)$it['unit_cost'], 2);
                
                $pct = 'Pending';
                if ($qty_received > 0) {
                    $pct = round(($qty_received / $qty_ordered) * 100) . '%';
                }
                
                $items[] = [
                    'name' => $it['item_name'],
                    'desc' => $qty_ordered . ' ordered · ' . $qty_received . ' received',
                    'val' => $val,
                    'pct' => $pct
                ];
            }
            
            $timeline = [
                ['t' => 'Purchase Order Created', 'd' => $ordered_date, 's' => 'done'],
                ['t' => 'PO Dispatched to Supplier', 'd' => $ordered_date, 's' => 'done']
            ];
            
            if ($status === 'received') {
                $timeline[] = ['t' => 'Goods Fully Received', 'd' => $po['received_at'] ? date('d M Y', strtotime($po['received_at'])) : $expected_date, 's' => 'done'];
            } elseif ($status === 'partial') {
                $timeline[] = ['t' => 'Partial Delivery Received', 'd' => date('d M Y'), 's' => 'now'];
                $timeline[] = ['t' => 'Awaiting Remaining Goods', 'd' => 'Expected soon', 's' => 'pend'];
            } elseif ($status === 'overdue') {
                $timeline[] = ['t' => 'Delivery Overdue', 'd' => 'Missed ' . $expected_date, 's' => 'warn'];
            } else {
                $timeline[] = ['t' => 'Awaiting Delivery', 'd' => 'Expected ' . $expected_date, 's' => 'now'];
            }

            $total_formatted = 'LKR ' . number_format((float)$po['total'], 2);
            
            $alertText = "This purchase order was sent on " . $ordered_date . ". Expected delivery date is " . $expected_date . ".";
            if ($status === 'overdue') {
                $alertText = "CRITICAL: Shipment is overdue. Expected delivery date was " . $expected_date . ". Contact supplier immediately.";
            } elseif ($status === 'partial') {
                $alertText = "Warning: Received partial delivery. Remaining items expected soon.";
            } elseif ($status === 'received') {
                $alertText = "Success: All items received and logged into inventory.";
            }

            $admin_pos[] = [
                'id' => $po['id'],
                'num' => $po_id_formatted,
                'date' => $ordered_date,
                'status' => $status,
                'badge' => $badge,
                'badgeText' => $badgeText,
                'supp' => $po['supplier_name'],
                'contact' => $po['contact_person'] ?? 'Primary contact',
                'payment' => $po['payment_terms'] ?? 'Net 30',
                'expected' => $expected_date,
                'expectedColor' => $expectedColorClass,
                'total' => $total_formatted,
                'alert' => $alertType,
                'alertText' => $alertText,
                'items' => json_encode($items),
                'timeline' => json_encode($timeline)
            ];
        }
    } catch (\Exception $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
?>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div id="purchase-orders-list-container" class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Purchase Orders</h1>
                <p class="text-sm text-gray-500 mt-1">Manage wholesale procurement and supplier shipments.</p>
            </div>
            <!-- Stats -->
            <div class="flex items-center gap-6">
                <div class="flex gap-4">
                    <div class="text-center">
                        <p class="text-[15px] font-black text-gray-900"><?= $total_pos ?></p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Total</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-blue-600"><?= $sent_pending_count ?></p>
                        <p class="text-[9px] font-bold text-blue-500 uppercase tracking-widest mt-0.5">Sent/Pending</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-amber-600"><?= $partial_count ?></p>
                        <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest mt-0.5">Partial</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[15px] font-black text-red-600"><?= $overdue_count ?></p>
                        <p class="text-[9px] font-bold text-red-500 uppercase tracking-widest mt-0.5">Overdue</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 border-l border-gray-100 pl-6">
                    <button onclick="downloadPDF('purchase-orders-list-container', 'Purchase_Orders_List')" class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="ti ti-download text-lg"></i> Export PDF
                    </button>
                    <button onclick="openRaisePOModal()" class="flex items-center gap-2 px-4 py-2.5 bg-brand text-brand-light rounded-xl text-xs font-bold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                        <i class="ti ti-plus text-lg"></i> Raise PO ↗
                    </button>
                </div>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="m-6 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl text-xs font-semibold text-emerald-700">
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="m-6 p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-semibold text-red-700">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>


        <!-- Filters Chips -->
        <div class="px-8 py-4 border-b border-gray-100 flex items-center gap-2 overflow-x-auto bg-gray-50/30">
            <button onclick="chipFilter(this)" class="chip px-4 py-2 bg-brand text-brand-light rounded-xl text-xs font-bold shadow-md shadow-brand/10 on transition-all border border-transparent">All</button>
            <button onclick="chipFilter(this)" class="chip px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">Sent</button>
            <button onclick="chipFilter(this)" class="chip px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">Partial</button>
            <button onclick="chipFilter(this)" class="chip px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">Received</button>
            <button onclick="chipFilter(this)" class="chip px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">Overdue</button>
        </div>

        <!-- List Pane Body -->
        <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar pb-10">
            <div class="min-w-[800px] p-6 space-y-1">
                <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                            <th class="px-4 py-3 rounded-l-xl w-32">PO Code</th>
                            <th class="px-4 py-3 w-64">Supplier</th>
                            <th class="px-4 py-3 w-40">Expected At</th>
                            <th class="px-4 py-3 w-40 text-right">Total Amount</th>
                            <th class="px-4 py-3 text-right rounded-r-xl w-32">Status</th>
                        </tr>
                    </thead>
                    <tbody id="po-list">
                        <?php if (empty($admin_pos)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-gray-400 text-sm">No purchase orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admin_pos as $po): ?>
                                <tr class="po-row bg-white cursor-pointer hover:bg-gray-50/50 transition-all group shadow-sm"
                                    id="po-row-<?= $po['id'] ?>"
                                    onclick="selectPO(this)"
                                    data-id="<?= $po['id'] ?>"
                                    data-num="<?= htmlspecialchars($po['num']) ?>"
                                    data-date="<?= htmlspecialchars($po['date']) ?>"
                                    data-badge="<?= htmlspecialchars($po['badge']) ?>"
                                    data-badge-text="<?= htmlspecialchars($po['badgeText']) ?>"
                                    data-supp="<?= htmlspecialchars($po['supp']) ?>"
                                    data-contact="<?= htmlspecialchars($po['contact']) ?>"
                                    data-payment="<?= htmlspecialchars($po['payment']) ?>"
                                    data-expected="<?= htmlspecialchars($po['expected']) ?>"
                                    data-expected-color="<?= htmlspecialchars($po['expectedColor']) ?>"
                                    data-total="<?= htmlspecialchars($po['total']) ?>"
                                    data-alert="<?= htmlspecialchars($po['alert']) ?>"
                                    data-alert-text="<?= htmlspecialchars($po['alertText']) ?>"
                                    data-items="<?= htmlspecialchars($po['items']) ?>"
                                    data-timeline="<?= htmlspecialchars($po['timeline']) ?>"
                                    data-status="<?= htmlspecialchars(strtolower($po['badgeText'])) ?>">
                                    <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30">
                                        <p class="font-bold text-sm text-gray-900 group-hover:text-brand transition-colors"><?= $po['num'] ?></p>
                                        <p class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-tight"><?= $po['date'] ?></p>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                        <p class="text-sm font-bold text-gray-700"><?= $po['supp'] ?></p>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                        <p class="text-xs font-semibold <?= $po['expectedColor'] ?> bg-gray-50 px-2 py-1 rounded-lg border w-max"><?= $po['expected'] ?></p>
                                    </td>
                                    <td class="p-4 border-y border-gray-100 group-hover:border-brand/30 text-right">
                                        <p class="text-sm font-extrabold text-brand"><?= $po['total'] ?></p>
                                    </td>
                                    <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-right">
                                        <span class="px-3 py-1 rounded-full text-[9px] font-bold border <?= $po['badge'] ?> uppercase tracking-wider whitespace-nowrap shadow-sm"><?= $po['badgeText'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between bg-white" id="pagination-controls">
                <p class="text-xs text-gray-500 font-medium" id="pagination-info">Showing 0 to 0 of 0 entries</p>
                <div class="flex items-center gap-2" id="pagination-buttons">
                    <!-- Buttons injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- PO Details Backdrop -->
    <div id="po-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closePODetailPane()"></div>

    <!-- PO Details Pane (Right) -->
    <div id="po-detail-pane" class="fixed inset-y-0 right-0 z-50 w-1/2 max-w-full bg-white flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto border-l border-gray-200">
        <div id="detail-print-area" class="p-8 flex-1 overflow-y-auto space-y-8 relative">
            <button onclick="closePODetailPane()" class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                <i class="ti ti-x text-xl"></i>
            </button>

            <!-- PO Title Details -->
            <div>
                <span id="d-po-date" class="text-xs text-gray-400 font-medium"></span>
                <h2 id="d-po-num" class="text-xl font-bold text-gray-900 tracking-tight mt-1"></h2>
                <div class="flex gap-2 items-center">
                    <span id="d-badge" class="mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest"></span>
                </div>
            </div>

            <!-- Warning Banner -->
            <div id="d-alert" class="p-4 rounded-2xl flex items-start gap-3 border text-xs bg-blue-50 border-blue-100 text-blue-700">
                <i class="ti ti-info-circle text-lg flex-shrink-0" aria-hidden="true"></i>
                <span id="d-alert-text" class="leading-relaxed font-medium"></span>
            </div>

            <!-- Supplier Contact Details -->
            <section class="space-y-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Supplier Partner</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Supplier Name</span>
                        <span id="d-supp" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Contact Person</span>
                        <span id="d-contact" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Payment Terms</span>
                        <span id="d-payment" class="font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Expected Arrival</span>
                        <span id="d-expected" class="font-bold"></span>
                    </div>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Line Items Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Line Items</h3>
                <div id="d-items" class="space-y-4"></div>
                <div class="pt-4 mt-4 flex justify-between items-center border-t border-gray-100">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Value</span>
                    <span id="d-total" class="text-lg font-black text-brand tracking-tight"></span>
                </div>
            </section>

            <div class="h-px bg-gray-100"></div>

            <!-- Status Timeline Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Status Timeline</h3>
                <div id="d-timeline" class="space-y-6 pt-4 relative before:absolute before:left-[5.5px] before:top-2 before:bottom-2 before:w-px before:bg-gray-100"></div>
            </section>
        </div>

        <!-- Action Footer (Sticky) -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3 print:hidden">
            <a id="d-record-grn-link" href="#" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all">
                <i class="ti ti-truck-delivery text-lg"></i>
                Record Goods Received
            </a>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="printPO()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-download text-base"></i>
                    Print PO
                </button>
                <button onclick="resendPO()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-mail text-base"></i>
                    Resend
                </button>
            </div>
            <button id="d-cancel-btn" onclick="cancelPO()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all">
                <i class="ti ti-x text-base"></i>
                Cancel Purchase Order
            </button>
        </div>
    </div>
</div>

<!-- Forms for actions -->
<form id="actionForm" method="POST" class="hidden" data-turbo="false">
    <input type="hidden" name="action" id="actionInput">
    <input type="hidden" name="po_id" id="poIdInput">
</form>

<!-- Raise PO Modal -->
<div id="raisePOModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 items-center justify-center p-4 hidden">
    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-2xl w-full">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Raise New Purchase Order</h2>
        
        <form method="POST" data-turbo="false">
            <input type="hidden" name="action" value="raise_po">
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Select Supplier</label>
                    <select name="supplier_id" required class="w-full px-4 py-3 bg-gray-50 border border-gray-250 rounded-2xl text-sm font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all cursor-pointer">
                        <?php foreach ($suppliers_list as $supp): ?>
                            <option value="<?= $supp['id'] ?>"><?= htmlspecialchars($supp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Expected Delivery Date</label>
                    <input type="date" name="expected_at" required class="w-full px-4 py-3 bg-gray-50 border border-gray-250 rounded-2xl text-sm font-semibold text-gray-800 outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all">
                </div>
            </div>
            
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Purchase Items</label>
                    <button type="button" onclick="addPOItemRow()" class="px-3 py-1.5 bg-brand/5 border border-brand/20 rounded-xl text-[10px] font-bold text-brand hover:bg-brand/10 transition-all">+ Add Item</button>
                </div>
                
                <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl bg-white">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Item Name / Description</th>
                                <th class="py-3 px-4 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-24">Quantity</th>
                                <th class="py-3 px-4 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-36">Unit Cost (LKR)</th>
                                <th class="py-3 px-3 w-14"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsContainer" class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-2">
                                    <select name="item_names[]" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-semibold cursor-pointer">
                                        <?= $inv_options ?>
                                    </select>
                                </td>
                                <td class="p-2"><input type="number" name="item_qtys[]" placeholder="Qty" min="1" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs text-center outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-bold"></td>
                                <td class="p-2"><input type="number" name="item_costs[]" placeholder="Cost" step="0.01" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-semibold"></td>
                                <td class="p-2 text-center"><button type="button" onclick="removePOItemRow(this)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"><i class="ti ti-trash text-base"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRaisePOModal()" class="px-6 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:opacity-90 transition-all">Raise Order</button>
            </div>
        </form>
    </div>
</div>

<template id="inv-options-template">
    <?= $inv_options ?>
</template>

<!-- Print Styles -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #detail-print-area, #detail-print-area * {
        visibility: visible;
    }
    #detail-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>

<script>
var currentSelectedPOId = 0;

function getItemBadgeClass(pct) {
  if (pct === '100%') return 'bg-emerald-50 border-emerald-100 text-emerald-700';
  if (pct === '0%' || pct === 'Pending') {
    return 'bg-red-50 border-red-100 text-red-700';
  }
  return 'bg-amber-50 border-amber-100 text-amber-700';
}

function getTimelineDotClass(s) {
  if (s === 'done') return 'bg-emerald-500 ring-white';
  if (s === 'now') return 'bg-blue-500 ring-blue-100 animate-pulse';
  if (s === 'warn') return 'bg-red-500 ring-red-100';
  return 'bg-gray-200 ring-white';
}

function getTimelineTextClass(s) {
  if (s === 'pend') return 'text-gray-400 font-medium';
  if (s === 'warn') return 'text-red-700 font-bold';
  return 'text-gray-900 font-bold';
}

function selectPO(el, openDrawer = true) {
  if (!el) return;
  document.querySelectorAll('.po-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
  el.classList.add('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
  el.classList.remove('bg-white', 'border-gray-100');
  
  currentSelectedPOId = el.dataset.id;
  
  // Set Record Goods Received URL
  document.getElementById('d-record-grn-link').href = '/admin-goods-received?po_id=' + el.dataset.id;
  
  // Toggle Visibility of GRN button
  var status_lower = el.dataset.status;
  if (status_lower === 'received') {
      document.getElementById('d-record-grn-link').classList.add('hidden');
  } else {
      document.getElementById('d-record-grn-link').classList.remove('hidden');
  }

  // Open drawer
  if (openDrawer) {
    var pane = document.getElementById('po-detail-pane');
    var backdrop = document.getElementById('po-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
  
  document.getElementById('d-po-num').textContent = el.dataset.num;
  document.getElementById('d-po-date').textContent = el.dataset.date;
  
  var badge = document.getElementById('d-badge');
  badge.className = 'mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest ' + el.dataset.badge;
  badge.textContent = el.dataset.badgeText;
  
  document.getElementById('d-supp').textContent = el.dataset.supp;
  document.getElementById('d-contact').textContent = el.dataset.contact;
  
  var payment = document.getElementById('d-payment');
  payment.textContent = el.dataset.payment;
  
  var expected = document.getElementById('d-expected');
  expected.textContent = el.dataset.expected;
  expected.className = 'text-xs font-bold ' + el.dataset.expectedColor;
  
  document.getElementById('d-total').textContent = el.dataset.total;
  
  var alertStyles = {
    overdue: { bg: 'bg-red-50', border: 'border-red-100', text: 'text-red-700', icon: 'ti-alert-triangle' },
    warn: { bg: 'bg-amber-50', border: 'border-amber-100', text: 'text-amber-700', icon: 'ti-clock' },
    info: { bg: 'bg-blue-50', border: 'border-blue-100', text: 'text-blue-700', icon: 'ti-info-circle' },
    ok: { bg: 'bg-emerald-50', border: 'border-emerald-100', text: 'text-emerald-700', icon: 'ti-circle-check' }
  };
  
  var al = document.getElementById('d-alert');
  var style = alertStyles[el.dataset.alert] || alertStyles['info'];
  al.className = `p-4 rounded-2xl flex items-start gap-3 border text-xs ${style.bg} ${style.border} ${style.text}`;
  al.querySelector('i').className = `ti ${style.icon} text-lg flex-shrink-0`;
  document.getElementById('d-alert-text').textContent = el.dataset.alertText;
  
  // Render items
  var items = [];
  try { items = JSON.parse(el.dataset.items || '[]'); } catch (e) {}
  document.getElementById('d-items').innerHTML = items.map(item => `
    <div class="flex justify-between items-start gap-4 py-2 border-b border-gray-100 last:border-b-0">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-gray-900">${item.name}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">${item.desc}</p>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="text-xs font-bold text-gray-900">${item.val}</p>
            <span class="inline-block mt-1 text-[9px] font-bold px-2 py-0.5 rounded-full border ${getItemBadgeClass(item.pct)}">${item.pct}</span>
        </div>
    </div>
  `).join('');
  
  // Render timeline
  var timeline = [];
  try { timeline = JSON.parse(el.dataset.timeline || '[]'); } catch (e) {}
  document.getElementById('d-timeline').innerHTML = timeline.map(t => `
    <div class="relative flex gap-4">
        <div class="w-3 h-3 rounded-full ${getTimelineDotClass(t.s)} shrink-0 mt-1 ring-4 z-10"></div>
        <div>
            <p class="text-xs ${getTimelineTextClass(t.s)}">${t.t}</p>
            ${t.d ? `<p class="text-[10px] text-gray-500 mt-0.5">${t.d}</p>` : ''}
        </div>
    </div>
  `).join('');
  
  // Toggle Cancel button visibility
  var cancelBtn = document.getElementById('d-cancel-btn');
  if (el.dataset.badgeText === 'Received' || el.dataset.badgeText === 'Fully received') {
    cancelBtn.classList.add('hidden');
  } else {
    cancelBtn.classList.remove('hidden');
  }
}

var activeFilter = 'All';

var currentPage = 1;
var itemsPerPage = 15;

function goToPage(page) {
    currentPage = page;
    applyFilters();
}

function renderPagination(totalItems, totalPages) {
    var info = document.getElementById('pagination-info');
    var buttons = document.getElementById('pagination-buttons');
    if (!info || !buttons) return;

    if (totalItems === 0) {
        info.textContent = 'Showing 0 entries';
        buttons.innerHTML = '';
        return;
    }

    var start = (currentPage - 1) * itemsPerPage + 1;
    var end = Math.min(currentPage * itemsPerPage, totalItems);
    info.textContent = `Showing ${start} to ${end} of ${totalItems} entries`;

    var html = '';
    
    var prevDisabled = currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === 1 ? '' : 'goToPage(' + (currentPage - 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${prevDisabled}"><i class="ti ti-chevron-left"></i></button>`;

    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += `<button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-brand-light font-bold text-xs shadow-md shadow-brand/20">${i}</button>`;
        } else if (
            i === 1 || 
            i === totalPages || 
            (i >= currentPage - 1 && i <= currentPage + 1)
        ) {
            html += `<button onclick="goToPage(${i})" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 font-bold text-xs transition-all">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">...</span>`;
        }
    }

    var nextDisabled = currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === totalPages ? '' : 'goToPage(' + (currentPage + 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${nextDisabled}"><i class="ti ti-chevron-right"></i></button>`;

    buttons.innerHTML = html;
}

function applyFilters() {
    var list = document.getElementById('po-list');
    var rows = Array.from(document.querySelectorAll('.po-row'));
    var visibleRows = [];

    rows.forEach(r => {
        var status = r.dataset.badgeText;
        var visible = true;
        if (activeFilter !== 'All' && status !== activeFilter) {
            visible = false;
        }
        
        if (visible) {
            visibleRows.push(r);
        } else {
            r.hidden = true;
            r.style.display = 'none';
        }
    });

    var emptyState = document.getElementById('empty-state');
    if (emptyState) emptyState.remove();

    if (visibleRows.length === 0) {
        var tr = document.createElement('tr');
        tr.id = 'empty-state';
        tr.innerHTML = '<td colspan="5" class="p-12 text-center text-gray-400 text-sm">No purchase orders match this filter.</td>';
        list.appendChild(tr);
        renderPagination(0, 0);
        return;
    }

    // Sort latest first (highest id)
    visibleRows.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));

    var totalItems = visibleRows.length;
    var totalPages = Math.ceil(totalItems / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    var start = (currentPage - 1) * itemsPerPage;
    var end = start + itemsPerPage;

    visibleRows.forEach((r, index) => {
        if (index >= start && index < end) {
            r.hidden = false;
            r.style.display = '';
        } else {
            r.hidden = true;
            r.style.display = 'none';
        }
    });

    visibleRows.forEach(r => list.appendChild(r));
    renderPagination(totalItems, totalPages);
}
}

function chipFilter(btn) {
    document.querySelectorAll('.chip').forEach(c => {
        c.classList.remove('on', 'bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'border-transparent');
        c.classList.add('bg-white', 'text-gray-500', 'border-gray-200');
    });
    btn.classList.add('on', 'bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'border-transparent');
    btn.classList.remove('bg-white', 'text-gray-500', 'border-gray-200');
    
    activeFilter = btn.innerText.trim();
    currentPage = 1;
    closePODetailPane();
    applyFilters();
    
    var firstVisible = Array.from(document.querySelectorAll('.po-row')).find(r => r.style.display !== 'none');
    if (firstVisible) {
        selectPO(firstVisible, false);
    }
}

function closePODetailPane() {
  var pane = document.getElementById('po-detail-pane');
  var backdrop = document.getElementById('po-detail-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
  document.querySelectorAll('.po-row').forEach(r => {
    r.classList.remove('selected', 'bg-brand/5', 'border-brand/20', 'shadow-sm');
    r.classList.add('bg-white', 'border-gray-100');
  });
}

// Raise PO Modal Actions
function openRaisePOModal() {
    document.getElementById('raisePOModal').classList.remove('hidden');
    document.getElementById('raisePOModal').classList.add('flex');
}

function closeRaisePOModal() {
    document.getElementById('raisePOModal').classList.add('hidden');
    document.getElementById('raisePOModal').classList.remove('flex');
}

function addPOItemRow() {
    var container = document.getElementById('poItemsContainer');
    var row = document.createElement('tr');
    row.className = 'hover:bg-gray-50/50 transition-colors';
    row.innerHTML = `
        <td class="p-2"><select name="item_names[]" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-semibold cursor-pointer">
            ${document.getElementById('inv-options-template').innerHTML}
        </select></td>
        <td class="p-2"><input type="number" name="item_qtys[]" placeholder="Qty" min="1" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs text-center outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-bold"></td>
        <td class="p-2"><input type="number" name="item_costs[]" placeholder="Cost" step="0.01" required class="w-full px-3 py-2 bg-transparent border border-transparent hover:border-gray-200 rounded-lg text-xs outline-none focus:bg-white focus:border-brand/35 focus:ring-2 focus:ring-brand/10 transition-all font-semibold"></td>
        <td class="p-2 text-center"><button type="button" onclick="removePOItemRow(this)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"><i class="ti ti-trash text-base"></i></button></td>
    `;
    container.appendChild(row);
}

function removePOItemRow(btn) {
    var rows = document.getElementById('poItemsContainer').children;
    if (rows.length > 1) {
        btn.closest('tr').remove();
    } else {
        uiAlert("At least one purchase item is required.");
    }
}

// Actions from Detail Pane
function printPO() {
    downloadPDF('detail-print-area', 'Purchase_Order');
}

function resendPO() {
    if (currentSelectedPOId > 0) {
        uiConfirm("Simulate sending this PO again to the supplier?", () => {
            document.getElementById('actionInput').value = 'resend_po';
            document.getElementById('poIdInput').value = currentSelectedPOId;
            document.getElementById('actionForm').submit();
        });
    }
}

function cancelPO() {
    if (currentSelectedPOId > 0) {
        uiConfirm("Are you sure you want to cancel and delete this Purchase Order?", () => {
            document.getElementById('actionInput').value = 'cancel_po';
            document.getElementById('poIdInput').value = currentSelectedPOId;
            document.getElementById('actionForm').submit();
        });
    }
}

// Initial Render
applyFilters();
var firstRow = document.querySelector('.po-row');
if (firstRow) {
    setTimeout(() => {
        selectPO(firstRow, false);
        closePODetailPane();
    }, 100);
}
</script>
