<?php
/**
 * Purchase Orders View - Database Integration
 */
$admin_pos = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT po.id, po.status, po.ordered_at, po.expected_at, po.received_at, po.total, 
                                    s.name AS supplier_name, s.contact_person, s.payment_terms
                             FROM purchase_orders po
                             JOIN suppliers s ON po.supplier_id = s.id
                             ORDER BY po.ordered_at DESC");
        $pos_db = $stmt->fetchAll();

        foreach ($pos_db as $po) {
            $po_id_formatted = 'PO-2025-' . str_pad($po['id'], 4, '0', STR_PAD_LEFT);
            $ordered_date = date('d M Y', strtotime($po['ordered_at']));
            $expected_date = date('d M Y', strtotime($po['expected_at']));
            
            // Map statuses
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
            } elseif ($status === 'partial') {
                $badge = 'bg-amber-50 border-amber-100 text-amber-700';
                $badgeText = 'Partial';
                $alertType = 'warn';
                $expectedColorClass = 'text-amber-700';
            } elseif ($status === 'received') {
                $badge = 'bg-emerald-50 border-emerald-100 text-emerald-700';
                $badgeText = 'Received';
                $alertType = 'ok';
            } elseif ($status === 'sent') {
                $badge = 'bg-blue-50 border-blue-100 text-blue-700';
                $badgeText = 'Sent';
                $alertType = 'info';
            }
            
            // Fetch items
            $item_stmt = $pdo->prepare("SELECT product_id, item_name, qty_ordered, qty_received, unit_cost FROM purchase_order_items WHERE po_id = ?");
            $item_stmt->execute([$po['id']]);
            $items_db = $item_stmt->fetchAll();
            
            $items = [];
            $total_ordered = 0;
            $total_received = 0;
            
            foreach ($items_db as $it) {
                $qty_ordered = (int)$it['qty_ordered'];
                $qty_received = (int)$it['qty_received'];
                $total_ordered += $qty_ordered;
                $total_received += $qty_received;
                
                $pct_val = $qty_ordered > 0 ? min(100, round(($qty_received / $qty_ordered) * 100)) . '%' : '0%';
                
                $item_val = $qty_ordered * (float)$it['unit_cost'];
                $item_val_formatted = 'LKR ' . ($item_val >= 1000 ? number_format($item_val/1000, 0) . 'K' : number_format($item_val, 2));
                
                $items[] = [
                    'name' => htmlspecialchars($it['item_name']),
                    'desc' => number_format($qty_ordered) . ' ordered · ' . number_format($qty_received) . ' received',
                    'val' => $item_val_formatted,
                    'pct' => $pct_val
                ];
            }
            
            // Expected date details and alert texts
            if ($status === 'overdue') {
                $overdue_days = max(1, round((time() - strtotime($po['expected_at'])) / (60 * 60 * 24)));
                $alertText = "This PO is {$overdue_days} days overdue. Contact " . htmlspecialchars($po['supplier_name']) . " to confirm delivery date.";
            } elseif ($status === 'partial') {
                $rec_pct = $total_ordered > 0 ? round(($total_received / $total_ordered) * 100) : 0;
                $alertText = "{$rec_pct}% received. Remaining " . (100 - $rec_pct) . "% expected {$expected_date}.";
            } elseif ($status === 'received') {
                $rec_date = $po['received_at'] ? date('d M Y', strtotime($po['received_at'])) : date('d M Y');
                $alertText = "Fully received {$rec_date}. Closed.";
            } else {
                $rem_days = max(0, round((strtotime($po['expected_at']) - time()) / (60 * 60 * 24)));
                $alertText = "PO sent " . date('d M', strtotime($po['ordered_at'])) . ". Awaiting delivery by {$expected_date} ({$rem_days} days remaining).";
            }
            
            // Format Total
            $po_total = (float)$po['total'];
            $total_formatted = 'LKR ' . ($po_total >= 1000 ? number_format($po_total/1000, 0) . 'K' : number_format($po_total, 2));
            
            // Create timeline
            $timeline = [
                ['t' => 'PO raised', 'd' => date('d M Y', strtotime($po['ordered_at'])), 's' => 'done'],
                ['t' => 'Sent to supplier', 'd' => date('d M Y', strtotime($po['ordered_at'])), 's' => 'done']
            ];
            
            if ($status === 'overdue') {
                $timeline[] = ['t' => 'Overdue — not yet received', 'd' => 'Expected ' . date('d M', strtotime($po['expected_at'])), 's' => 'warn'];
                $timeline[] = ['t' => 'GRN and close', 'd' => '', 's' => 'pend'];
            } elseif ($status === 'partial') {
                $timeline[] = ['t' => 'Partial delivery received', 'd' => 'GRN-2025-' . str_pad($po['id'], 4, '0', STR_PAD_LEFT) . 'A', 's' => 'now'];
                $timeline[] = ['t' => 'Full receipt & GRN close', 'd' => '', 's' => 'pend'];
            } elseif ($status === 'received') {
                $timeline[] = ['t' => 'Goods fully received', 'd' => date('d M Y', strtotime($po['received_at'])), 's' => 'done'];
                $timeline[] = ['t' => 'GRN closed', 'd' => 'GRN-2025-' . str_pad($po['id'], 4, '0', STR_PAD_LEFT), 's' => 'done'];
            } else {
                $timeline[] = ['t' => 'Sent to supplier', 'd' => date('d M', strtotime($po['ordered_at'])) . ', awaiting delivery', 's' => 'now'];
                $timeline[] = ['t' => 'Goods received', 'd' => '', 's' => 'pend'];
                $timeline[] = ['t' => 'GRN and close', 'd' => '', 's' => 'pend'];
            }
            
            $admin_pos[] = [
                'num' => $po_id_formatted,
                'date' => 'Raised ' . $ordered_date,
                'badge' => $badge,
                'badgeText' => $badgeText,
                'supp' => $po['supplier_name'],
                'contact' => $po['contact_person'],
                'payment' => $po['payment_terms'],
                'expected' => $expected_date,
                'expectedColorClass' => $expectedColorClass,
                'total' => $total_formatted,
                'alert' => $alertType,
                'alertText' => $alertText,
                'items' => $items,
                'timeline' => $timeline
            ];
        }
    } catch (\Exception $e) {
        $db_error = $e->getMessage();
    }
}

if (empty($admin_pos)) {
    $admin_pos = [
      [ 
        'num' => 'PO-2025-0042', 
        'date' => 'Raised 4 May 2025', 
        'badge' => 'bg-red-50 border-red-100 text-red-700', 
        'badgeText' => 'Overdue', 
        'supp' => 'SL Cotton Mills', 
        'contact' => 'Roshan Silva', 
        'payment' => 'Net 30', 
        'expected' => '9 May 2025', 
        'expectedColorClass' => 'text-red-600', 
        'total' => 'LKR 480K', 
        'alert' => 'overdue', 
        'alertText' => 'This PO is 3 days overdue. Contact SL Cotton Mills to confirm delivery date.',
        'items' => [
          [ 'name' => 'Combed cotton 180GSM', 'desc' => '2,000m ordered · 0 received', 'val' => 'LKR 320K', 'pct' => '0%' ],
          [ 'name' => 'Modal fabric 160GSM', 'desc' => '800m ordered · 0 received', 'val' => 'LKR 160K', 'pct' => '0%' ]
        ],
        'timeline' => [
          [ 't' => 'PO raised', 'd' => '4 May 2025', 's' => 'done' ],
          [ 't' => 'Sent to supplier', 'd' => '4 May 2025', 's' => 'done' ],
          [ 't' => 'Overdue — not yet received', 'd' => 'Expected 9 May', 's' => 'warn' ],
          [ 't' => 'GRN and close', 'd' => '', 's' => 'pend' ]
        ]
      ],
      [ 
        'num' => 'PO-2025-0041', 
        'date' => 'Raised 8 May 2025', 
        'badge' => 'bg-amber-50 border-amber-100 text-amber-700', 
        'badgeText' => 'Partial', 
        'supp' => 'Premium Elastic Co.', 
        'contact' => 'Nishantha Kumar', 
        'payment' => 'Net 15', 
        'expected' => '15 May 2025', 
        'expectedColorClass' => 'text-amber-700', 
        'total' => 'LKR 94K', 
        'alert' => 'warn', 
        'alertText' => '60% received. Remaining 40% expected 15 May.',
        'items' => [
          [ 'name' => 'Branded elastic waistband', 'desc' => '500 rolls ordered · 300 received', 'val' => 'LKR 75K', 'pct' => '60%' ],
          [ 'name' => 'Plain elastic 2cm', 'desc' => '1,000 rolls ordered · 600 received', 'val' => 'LKR 19K', 'pct' => '60%' ]
        ],
        'timeline' => [
          [ 't' => 'PO raised', 'd' => '8 May 2025', 's' => 'done' ],
          [ 't' => 'Sent to supplier', 'd' => '8 May 2025, email', 's' => 'done' ],
          [ 't' => 'Partial delivery received', 'd' => '11 May · GRN-2025-0041A', 's' => 'now' ],
          [ 't' => 'Full receipt & GRN close', 'd' => '', 's' => 'pend' ]
        ]
      ],
      [ 
        'num' => 'PO-2025-0040', 
        'date' => 'Raised 7 May 2025', 
        'badge' => 'bg-blue-50 border-blue-100 text-blue-700', 
        'badgeText' => 'Sent', 
        'supp' => 'Kandy Textiles', 
        'contact' => 'Priya Weerakoon', 
        'payment' => 'Net 45', 
        'expected' => '20 May 2025', 
        'expectedColorClass' => 'text-gray-900', 
        'total' => 'LKR 210K', 
        'alert' => 'info', 
        'alertText' => 'PO sent 7 May. Awaiting delivery by 20 May (8 days remaining).',
        'items' => [
          [ 'name' => 'Cotton fabric 180GSM white', 'desc' => '1,500m ordered · 0 received', 'val' => 'LKR 210K', 'pct' => 'Pending' ]
        ],
        'timeline' => [
          [ 't' => 'PO raised', 'd' => '7 May 2025', 's' => 'done' ],
          [ 't' => 'Sent to supplier', 'd' => '7 May, awaiting delivery', 's' => 'now' ],
          [ 't' => 'Goods received', 'd' => '', 's' => 'pend' ],
          [ 't' => 'GRN and close', 'd' => '', 's' => 'pend' ]
        ]
      ],
      [ 
        'num' => 'PO-2025-0039', 
        'date' => 'Raised 6 May 2025', 
        'badge' => 'bg-blue-50 border-blue-100 text-blue-700', 
        'badgeText' => 'Sent', 
        'supp' => 'Pacific Packaging', 
        'contact' => 'Saman Dias', 
        'payment' => 'Net 30', 
        'expected' => '22 May 2025', 
        'expectedColorClass' => 'text-gray-900', 
        'total' => 'LKR 38K', 
        'alert' => 'info', 
        'alertText' => 'PO sent 6 May. Awaiting delivery by 22 May.',
        'items' => [
          [ 'name' => 'Polybags 30×40cm', 'desc' => '5,000 pcs ordered', 'val' => 'LKR 25K', 'pct' => 'Pending' ],
          [ 'name' => 'Printed cartons', 'desc' => '200 boxes ordered', 'val' => 'LKR 13K', 'pct' => 'Pending' ]
        ],
        'timeline' => [
          [ 't' => 'PO raised', 'd' => '6 May 2025', 's' => 'done' ],
          [ 't' => 'Sent to supplier', 'd' => '6 May, awaiting delivery', 's' => 'now' ],
          [ 't' => 'Goods received', 'd' => '', 's' => 'pend' ],
          [ 't' => 'GRN and close', 'd' => '', 's' => 'pend' ]
        ]
      ],
      [ 
        'num' => 'PO-2025-0038', 
        'date' => 'Raised 22 Apr 2025', 
        'badge' => 'bg-emerald-50 border-emerald-100 text-emerald-700', 
        'badgeText' => 'Received', 
        'supp' => 'Sri Lanka Cotton Mills', 
        'contact' => 'Roshan Silva', 
        'payment' => 'Net 30', 
        'expected' => '1 May 2025', 
        'expectedColorClass' => 'text-gray-900', 
        'total' => 'LKR 620K', 
        'alert' => 'ok', 
        'alertText' => 'Fully received 30 Apr 2025. GRN-2025-0038 closed.',
        'items' => [
          [ 'name' => 'Combed cotton 180GSM', 'desc' => '2,500m ordered · 2,500 received', 'val' => 'LKR 400K', 'pct' => '100%' ],
          [ 'name' => 'Spandex blend 200GSM', 'desc' => '1,000m ordered · 1,000 received', 'val' => 'LKR 220K', 'pct' => '100%' ]
        ],
        'timeline' => [
          [ 't' => 'PO raised', 'd' => '22 Apr 2025', 's' => 'done' ],
          [ 't' => 'Sent to supplier', 'd' => '22 Apr 2025', 's' => 'done' ],
          [ 't' => 'Goods fully received', 'd' => '30 Apr 2025', 's' => 'done' ],
          [ 't' => 'GRN closed', 'd' => 'GRN-2025-0038', 's' => 'done' ]
        ]
      ]
    ];
}

$total_pos = count($admin_pos);
$sent_pending_count = 0;
$partial_count = 0;
$overdue_count = 0;
foreach ($admin_pos as $p) {
    $stat_lower = strtolower($p['badgeText']);
    if ($stat_lower === 'sent' || $stat_lower === 'pending' || $stat_lower === 'draft') {
        $sent_pending_count++;
    } elseif ($stat_lower === 'partial') {
        $partial_count++;
    } elseif ($stat_lower === 'overdue') {
        $overdue_count++;
    }
}
?>
<!-- Purchase Orders View -->
<h2 class="sr-only">Purchase orders management page mockup for Kesara Enterprises wholesale admin platform</h2>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-md sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Purchase Orders</h1>
                <p class="text-sm text-gray-500 mt-1">Manage wholesale procurement and supplier shipments</p>
            </div>
            <div class="flex gap-3">
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all shadow-sm">
                    <i class="ti ti-download text-lg"></i>
                    Export
                </button>
                <button onclick="sendPrompt('How should I design the raise new purchase order form for Kesara Enterprises admin?')" class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-brand-light rounded-xl text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-brand/20">
                    <i class="ti ti-plus text-lg"></i>
                    Raise PO ↗
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-4 gap-4 p-6 bg-gray-50/50">
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total (2025)</p>
                <p class="text-2xl font-bold text-gray-900"><?= $total_pos ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-blue-500">
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">Sent / Pending</p>
                <p class="text-2xl font-bold text-gray-900"><?= $sent_pending_count ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-amber-500">
                <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">Partial Receipt</p>
                <p class="text-2xl font-bold text-gray-900"><?= $partial_count ?></p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 border-l-red-500">
                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-1">Overdue</p>
                <p class="text-2xl font-bold text-gray-900"><?= $overdue_count ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-100 bg-white flex flex-col gap-4">
            <div class="flex gap-3">
                <div class="relative flex-1 group">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
                    <input type="text" placeholder="Search PO number or supplier..." 
                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand/20 transition-all outline-none">
                </div>
                <select class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
                    <option>All Suppliers</option>
                    <option>SL Cotton Mills</option>
                    <option>Premium Elastic</option>
                    <option>Pacific Packaging</option>
                </select>
                <input type="date" class="px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand/20 outline-none cursor-pointer">
            </div>
            <div class="flex gap-2">
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 chip on">All</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Draft</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Sent</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Partial</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Received</button>
                <button onclick="chipFilter(this)" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand/30 chip">Overdue</button>
            </div>
        </div>

        <!-- List Content -->
        <div class="flex-1 overflow-auto p-6">
            <div class="min-w-[750px]">
                <div class="grid grid-cols-[1fr_100px_100px_120px_110px] gap-4 px-4 py-3 bg-gray-50 rounded-xl mb-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    <span>PO / Supplier</span>
                    <span>Expected</span>
                    <span>Value</span>
                    <span>Received</span>
                    <span class="text-right">Status</span>
                </div>

                <div id="po-list" class="space-y-2">
                    <?php foreach ($admin_pos as $idx => $p): ?>
                    <?php
                        $progressWidth = '0%';
                        if ($p['badgeText'] === 'Fully received' || $p['badgeText'] === 'Received') {
                          $progressWidth = '100%';
                        } else if ($p['badgeText'] === 'Partial' || $p['badgeText'] === 'Partial receipt') {
                          $progressWidth = '60%';
                        }
                        
                        $expectedText = $p['expected'];
                        if ($p['alert'] === 'overdue') {
                          $expectedText .= ' <i class="ti ti-alert-triangle text-sm"></i>';
                        }
                    ?>
                    <div class="po-row group grid grid-cols-[1fr_100px_100px_120px_110px] gap-4 items-center p-4 rounded-2xl transition-all cursor-pointer bg-white border border-gray-100 hover:border-brand/30 hover:bg-gray-50"
                         data-idx="<?= $idx ?>"
                         data-num="<?= htmlspecialchars($p['num']) ?>"
                         data-date="<?= htmlspecialchars($p['date']) ?>"
                         data-badge="<?= htmlspecialchars($p['badge']) ?>"
                         data-badge-text="<?= htmlspecialchars($p['badgeText']) ?>"
                         data-supp="<?= htmlspecialchars($p['supp']) ?>"
                         data-contact="<?= htmlspecialchars($p['contact']) ?>"
                         data-payment="<?= htmlspecialchars($p['payment']) ?>"
                         data-expected="<?= htmlspecialchars($p['expected']) ?>"
                         data-expected-color="<?= htmlspecialchars($p['expectedColorClass']) ?>"
                         data-total="<?= htmlspecialchars($p['total']) ?>"
                         data-alert="<?= htmlspecialchars($p['alert']) ?>"
                         data-alert-text="<?= htmlspecialchars($p['alertText']) ?>"
                         data-items="<?= htmlspecialchars(json_encode($p['items'])) ?>"
                         data-timeline="<?= htmlspecialchars(json_encode($p['timeline'])) ?>"
                         onclick="selectPO(this)">
                        <div>
                            <p class="text-sm font-bold text-gray-900 group-hover:text-brand transition-colors"><?= htmlspecialchars($p['num']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($p['supp']) ?></p>
                        </div>
                        <span class="text-xs font-semibold <?= $p['alert'] === 'overdue' ? 'text-red-600' : 'text-gray-500' ?> flex items-center gap-1"><?= $expectedText ?></span>
                        <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($p['total']) ?></span>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-16 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                                <div class="h-full bg-brand rounded-full transition-all duration-500" style="width: <?= $progressWidth ?>"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-400"><?= $progressWidth ?></span>
                        </div>
                        <div class="text-right">
                            <span class="px-3 py-1 <?= htmlspecialchars($p['badge']) ?> rounded-full text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($p['badgeText']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($admin_pos)): ?>
                        <div class="text-xs text-gray-400 text-center py-10 italic">No purchase orders found.</div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <div class="mt-8 flex justify-between items-center bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <span class="text-xs font-bold text-gray-400">SHOWING <?= count($admin_pos) ?> OF <?= $total_pos ?> POS</span>
                    <div class="flex gap-2">
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-brand transition-colors"><i class="ti ti-chevron-left"></i></button>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-brand-light font-bold text-xs">1</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-brand transition-colors"><i class="ti ti-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Pane -->
    <!-- Backdrop -->
    <div id="po-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closePODetailPane()"></div>
    <div id="po-detail-pane" class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-white border-l border-gray-100 flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-8 flex-1 overflow-y-auto space-y-8">
            <!-- Header Block -->
            <div class="flex flex-col items-center text-center relative">
                <button onclick="closePODetailPane()" class="absolute top-0 right-0 p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
                <div class="w-16 h-16 rounded-3xl flex items-center justify-center text-2xl font-bold bg-brand/5 border border-brand/20 shadow-lg shadow-brand/10 text-brand mb-4">
                    <i class="ti ti-file-invoice"></i>
                </div>
                <h2 id="d-po-num" class="text-xl font-bold text-gray-900 tracking-tight"></h2>
                <p id="d-po-date" class="text-sm text-gray-500 mt-1"></p>
                <span id="d-badge" class="mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border"></span>
            </div>

            <!-- Alert Card -->
            <div id="d-alert" class="p-4 rounded-2xl flex items-start gap-3 border text-xs">
                <i class="text-lg flex-shrink-0" aria-hidden="true"></i>
                <span id="d-alert-text" class="leading-relaxed"></span>
            </div>

            <!-- Supplier Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Supplier Details</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Name</span>
                        <span id="d-supp" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Contact</span>
                        <span id="d-contact" class="text-xs font-bold text-gray-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Payment Terms</span>
                        <span id="d-payment" class="text-xs font-bold text-gray-900 px-2 py-1 bg-gray-100 rounded-lg"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500 font-medium">Expected By</span>
                        <span id="d-expected" class="text-xs font-bold"></span>
                    </div>
                </div>
            </section>

            <!-- Line Items Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Line Items</h3>
                <div id="d-items" class="space-y-4">
                    <!-- Dynamic Items injected here -->
                </div>
                <div class="pt-4 mt-4 flex justify-between items-center border-t border-gray-100">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Value</span>
                    <span id="d-total" class="text-lg font-black text-brand tracking-tight"></span>
                </div>
            </section>

            <!-- Status Timeline Section -->
            <section>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Status Timeline</h3>
                <div id="d-timeline" class="space-y-6 pl-4 relative before:absolute before:left-[5.5px] before:top-2 before:bottom-2 before:w-px before:bg-gray-100">
                    <!-- Dynamic Timeline items injected here -->
                </div>
            </section>
        </div>

        <!-- Action Footer (Sticky) -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
            <button onclick="sendPrompt('What should the goods received note page show for Kesara Enterprises admin?')" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-brand text-brand-light rounded-xl text-sm font-bold shadow-lg shadow-brand/10 hover:opacity-90 transition-all">
                <i class="ti ti-truck-delivery text-lg"></i>
                Record Goods Received ↗
            </button>
            <div class="grid grid-cols-2 gap-3">
                <button class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-download text-base"></i>
                    PDF
                </button>
                <button class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-mail text-base"></i>
                    Resend
                </button>
            </div>
            <button id="d-cancel-btn" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-100 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 transition-all">
                <i class="ti ti-x text-base"></i>
                Cancel Purchase Order
            </button>
        </div>
    </div>
</div>

<script>

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
  
  // Open drawer
  if (openDrawer) {
    const pane = document.getElementById('po-detail-pane');
    const backdrop = document.getElementById('po-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
  
  document.getElementById('d-po-num').textContent = el.dataset.num;
  document.getElementById('d-po-date').textContent = el.dataset.date;
  
  const badge = document.getElementById('d-badge');
  badge.className = 'mt-3 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest ' + el.dataset.badge;
  badge.textContent = el.dataset.badgeText;
  
  document.getElementById('d-supp').textContent = el.dataset.supp;
  document.getElementById('d-contact').textContent = el.dataset.contact;
  
  const payment = document.getElementById('d-payment');
  payment.textContent = el.dataset.payment;
  
  const expected = document.getElementById('d-expected');
  expected.textContent = el.dataset.expected;
  expected.className = 'text-xs font-bold ' + el.dataset.expectedColor;
  
  document.getElementById('d-total').textContent = el.dataset.total;
  
  const alertStyles = {
    overdue: { bg: 'bg-red-50', border: 'border-red-100', text: 'text-red-700', icon: 'ti-alert-triangle' },
    warn: { bg: 'bg-amber-50', border: 'border-amber-100', text: 'text-amber-700', icon: 'ti-clock' },
    info: { bg: 'bg-blue-50', border: 'border-blue-100', text: 'text-blue-700', icon: 'ti-info-circle' },
    ok: { bg: 'bg-emerald-50', border: 'border-emerald-100', text: 'text-emerald-700', icon: 'ti-circle-check' }
  };
  
  const al = document.getElementById('d-alert');
  const style = alertStyles[el.dataset.alert];
  al.className = `p-4 rounded-2xl flex items-start gap-3 border text-xs ${style.bg} ${style.border} ${style.text}`;
  al.querySelector('i').className = `ti ${style.icon} text-lg flex-shrink-0`;
  document.getElementById('d-alert-text').textContent = el.dataset.alertText;
  
  // Render items
  let items = [];
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
  let timeline = [];
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
  const cancelBtn = document.getElementById('d-cancel-btn');
  if (el.dataset.badgeText === 'Received' || el.dataset.badgeText === 'Fully received') {
    cancelBtn.classList.add('hidden');
  } else {
    cancelBtn.classList.remove('hidden');
  }
}

let activeFilter = 'All';

function applyFilters() {
    document.querySelectorAll('.po-row').forEach(r => {
        const status = r.dataset.badgeText;
        let visible = true;
        if (activeFilter !== 'All' && status !== activeFilter) {
            visible = false;
        }
        r.style.display = visible ? '' : 'none';
    });
}

function chipFilter(el) {
  document.querySelectorAll('.chip').forEach(c => {
    c.classList.remove('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
    c.classList.add('bg-white', 'text-gray-500', 'border-gray-200');
  });
  el.classList.add('bg-brand', 'text-brand-light', 'shadow-md', 'shadow-brand/10', 'on');
  el.classList.remove('bg-white', 'text-gray-500', 'border-gray-200');
  
  activeFilter = el.textContent.trim();
  closePODetailPane();
  applyFilters();
  
  const firstVisible = Array.from(document.querySelectorAll('.po-row')).find(r => r.style.display !== 'none');
  if (firstVisible) {
      selectPO(firstVisible, false);
  }
}

function closePODetailPane() {
  const pane = document.getElementById('po-detail-pane');
  const backdrop = document.getElementById('po-detail-backdrop');
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

// Initial Render
applyFilters();
const firstRow = document.querySelector('.po-row');
if (firstRow) {
  setTimeout(() => {
    selectPO(firstRow, false);
    closePODetailPane();
  }, 100);
}
</script>
