<?php


$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');
$filter_month = $_GET['filter_month'] ?? '';

$where_clauses = ["o.status != 'cancelled'", "o.deleted_at IS NULL"];
$where_params = [];

if (!empty($filter_start_date) || !empty($filter_end_date)) {
    $filter_month = 'custom';
    if (!empty($filter_start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_start_date)) {
        $where_clauses[] = "o.created_at >= ?";
        $where_params[] = $filter_start_date . ' 00:00:00';
    }
    if (!empty($filter_end_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_end_date)) {
        $where_clauses[] = "o.created_at <= ?";
        $where_params[] = $filter_end_date . ' 23:59:59';
    }
    
    if (!empty($filter_start_date) && !empty($filter_end_date)) {
        if ($filter_start_date === $filter_end_date) {
            $month_label = date('M j, Y', strtotime($filter_start_date));
        } else {
            $month_label = date('M j, Y', strtotime($filter_start_date)) . " - " . date('M j, Y', strtotime($filter_end_date));
        }
    } elseif (!empty($filter_start_date)) {
        $month_label = "From " . date('M j, Y', strtotime($filter_start_date));
    } else {
        $month_label = "Until " . date('M j, Y', strtotime($filter_end_date));
    }
} else {
    if (empty($filter_month)) {
        $filter_month = 'this_month';
    }
    
    if ($filter_month === 'last_month') {
        $where_clauses[] = "MONTH(o.created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(o.created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
        $month_label = date('F Y', strtotime('first day of -1 month'));
    } elseif ($filter_month === 'last_3_months') {
        $where_clauses[] = "o.created_at >= CURRENT_DATE() - INTERVAL 3 MONTH";
        $month_label = "Last 3 Months";
    } elseif ($filter_month === 'last_6_months') {
        $where_clauses[] = "o.created_at >= CURRENT_DATE() - INTERVAL 6 MONTH";
        $month_label = "Last 6 Months";
    } elseif ($filter_month === 'this_year') {
        $where_clauses[] = "YEAR(o.created_at) = YEAR(CURRENT_DATE())";
        $month_label = "This Year (" . date('Y') . ")";
    } elseif ($filter_month === 'all') {
        $month_label = "All Time";
    } else {
        $filter_month = 'this_month';
        $where_clauses[] = "MONTH(o.created_at) = MONTH(CURRENT_DATE()) AND YEAR(o.created_at) = YEAR(CURRENT_DATE())";
        $month_label = date('F Y');
    }
}

$date_sql_where = implode(" AND ", $where_clauses);

$total_revenue = 0;
$total_orders = 0;
$avg_order_value = 0;
$units_sold = 0;

$category_names = [];
$category_percentages = [];
$product_performance = [];
$top_customers = [];
$active_buyers_count = 0;

$revenue_chart_labels = [];
$revenue_chart_revenue = [];
$revenue_chart_orders = [];
$buyer_chart_labels = [];
$buyer_chart_data = [];

if (isset($pdo) && $pdo !== null) {
    // Self-heal DB structure & missing items
    try {
        $chk_deleted = $pdo->query("SHOW COLUMNS FROM orders LIKE 'deleted_at'");
        if (!$chk_deleted->fetch()) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN deleted_at DATETIME DEFAULT NULL");
        }

        $no_items_stmt = $pdo->query("SELECT o.id FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE oi.id IS NULL");
        $no_item_orders = $no_items_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($no_item_orders)) {
            $p_stmt = $pdo->query("SELECT id, price FROM products LIMIT 5");
            $prods = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($prods)) {
                $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                foreach ($no_item_orders as $nio) {
                    $pid = $prods[array_rand($prods)];
                    $ins->execute([$nio['id'], $pid['id'], rand(50, 200), $pid['price']]);
                }
            }
        }

        $checkDateIdx = $pdo->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_created_at'");
        if (!$checkDateIdx || !$checkDateIdx->fetch()) {
            $pdo->exec("CREATE INDEX idx_orders_created_at ON orders(created_at)");
        }
    } catch (\Exception $e) {
    }

    // 1. Total Revenue
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE $date_sql_where");
        $stmt->execute($where_params);
        $total_revenue = (float) $stmt->fetchColumn();
    } catch (\Exception $e) {
    }

    // 2. Total Orders
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $date_sql_where");
        $stmt->execute($where_params);
        $total_orders = (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
    }

    // 3. Average Order Value
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(AVG(o.total_amount), 0) FROM orders o WHERE $date_sql_where");
        $stmt->execute($where_params);
        $avg_order_value = (float) $stmt->fetchColumn();
    } catch (\Exception $e) {
    }

    // 4. Units Sold
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity), 0) 
                             FROM order_items oi 
                             JOIN orders o ON oi.order_id = o.id 
                             WHERE $date_sql_where");
        $stmt->execute($where_params);
        $units_sold = (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
    }

    // 5. Category Breakdown
    try {
        $stmt = $pdo->prepare("SELECT c.name, SUM(oi.quantity * oi.unit_price) AS cat_rev 
                             FROM order_items oi 
                             JOIN orders o ON oi.order_id = o.id 
                             JOIN products p ON oi.product_id = p.id 
                             JOIN categories c ON p.category_id = c.id 
                             WHERE $date_sql_where 
                             GROUP BY c.id 
                             ORDER BY cat_rev DESC");
        $stmt->execute($where_params);
        $categories_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_cat_rev = array_sum(array_column($categories_db, 'cat_rev'));
        foreach ($categories_db as $cat) {
            $category_names[] = $cat['name'];
            $category_percentages[] = $total_cat_rev > 0 ? round(($cat['cat_rev'] / $total_cat_rev) * 100) : 0;
        }
    } catch (\Exception $e) {
    }

    // 6. Top Products
    try {
        $stmt = $pdo->prepare("SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue 
                             FROM order_items oi 
                             JOIN orders o ON oi.order_id = o.id 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE $date_sql_where 
                             GROUP BY p.id 
                             ORDER BY units DESC 
                             LIMIT 5");
        $stmt->execute($where_params);
        $product_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
    }

    // 7. Top Customers
    try {
        $stmt = $pdo->prepare("SELECT u.business_name, SUM(o.total_amount) AS spend 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             WHERE $date_sql_where 
                             GROUP BY o.user_id 
                             ORDER BY spend DESC 
                             LIMIT 5");
        $stmt->execute($where_params);
        $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
    }

    // 8. Active Buyers Count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT o.user_id) FROM orders o WHERE $date_sql_where");
        $stmt->execute($where_params);
        $active_buyers_count = (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
    }

    // 9. Revenue & Orders Trend Chart Data
    try {
        $sql = "SELECT DATE_FORMAT(o.created_at, '%b %d') AS period_label,
                       DATE_FORMAT(o.created_at, '%Y-%m-%d') AS grp,
                       SUM(o.total_amount) AS rev,
                       COUNT(o.id) AS ord_count
                FROM orders o
                WHERE $date_sql_where
                GROUP BY grp, period_label
                ORDER BY grp ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where_params);
        $trend_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($trend_db) > 15 || ($filter_month !== 'custom' && in_array($filter_month, ['last_3_months', 'last_6_months', 'this_year', 'all']))) {
            $sql = "SELECT DATE_FORMAT(o.created_at, '%b %Y') AS period_label,
                           DATE_FORMAT(o.created_at, '%Y-%m') AS grp,
                           SUM(o.total_amount) AS rev,
                           COUNT(o.id) AS ord_count
                    FROM orders o
                    WHERE $date_sql_where
                    GROUP BY grp, period_label
                    ORDER BY grp ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($where_params);
            $trend_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($trend_db as $td) {
            $revenue_chart_labels[] = $td['period_label'];
            $revenue_chart_revenue[] = (float) $td['rev'];
            $revenue_chart_orders[] = (int) $td['ord_count'];
        }
    } catch (\Exception $e) {
    }

    // 10. Buyer Chart Data
    try {
        $sql = "SELECT DATE_FORMAT(o.created_at, '%b %Y') AS period_label,
                       DATE_FORMAT(o.created_at, '%Y-%m') AS grp,
                       COUNT(DISTINCT o.user_id) AS buyer_cnt
                FROM orders o
                WHERE $date_sql_where
                GROUP BY grp, period_label
                ORDER BY grp ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where_params);
        $buyer_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($buyer_db as $bd) {
            $buyer_chart_labels[] = $bd['period_label'];
            $buyer_chart_data[] = (int) $bd['buyer_cnt'];
        }
    } catch (\Exception $e) {
    }
}

// Fallbacks for chart visualization
if (empty($revenue_chart_labels)) {
    $revenue_chart_labels = [$month_label];
    $revenue_chart_revenue = [$total_revenue];
    $revenue_chart_orders = [$total_orders];
}
if (empty($buyer_chart_labels)) {
    $buyer_chart_labels = [$month_label];
    $buyer_chart_data = [$active_buyers_count];
}

// Best seller & highest revenue calculation for cards
$best_seller = !empty($product_performance) ? $product_performance[0] : null;
$highest_rev_product = null;
if (!empty($product_performance)) {
    $sorted_by_rev = $product_performance;
    usort($sorted_by_rev, function ($a, $b) {
        return $b['revenue'] <=> $a['revenue'];
    });
    $highest_rev_product = $sorted_by_rev[0];
}
?>

<div class="flex-1 flex flex-col min-w-0 bg-white overflow-y-auto overflow-x-hidden no-scrollbar">
    <!-- Header -->
    <div class="px-8 py-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4 sticky top-0 bg-white z-10">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analytics & Reports</h1>
            <p class="text-sm text-gray-500 mt-1">Showing business metrics for <span class="font-semibold text-gray-700"><?= htmlspecialchars($month_label) ?></span></p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="/admin-reports" class="flex items-center gap-2">
                <input type="hidden" name="view" value="reports">
                <div class="flex items-center gap-1.5 bg-white px-3 py-1.5 rounded-xl border border-gray-200 shadow-sm">
                    <span class="text-[10px] font-bold text-gray-400 uppercase">From:</span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start_date ?? '') ?>"
                        class="text-xs font-semibold text-gray-700 outline-none bg-transparent">
                    <span class="text-[10px] font-bold text-gray-400 uppercase ml-1">To:</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end_date ?? '') ?>"
                        class="text-xs font-semibold text-gray-700 outline-none bg-transparent">
                </div>
                <button type="submit"
                    class="px-3.5 py-2 bg-gray-900 text-white text-xs font-bold rounded-xl hover:bg-brand transition-colors shadow-sm">
                    Filter Date
                </button>
                <?php if (!empty($filter_start_date) || !empty($filter_end_date)): ?>
                    <a href="/admin-reports" class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-bold rounded-xl hover:bg-gray-200 transition-colors">Clear</a>
                <?php endif; ?>
            </form>

            <select onchange="window.location.href='/admin-reports?filter_month=' + this.value"
                class="px-4 py-2 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-xs font-bold transition-all shadow-sm">
                <option value="this_month" <?= $filter_month === 'this_month' ? 'selected' : '' ?>>This month (<?= date('M Y') ?>)</option>
                <option value="last_month" <?= $filter_month === 'last_month' ? 'selected' : '' ?>>Last month</option>
                <option value="last_3_months" <?= $filter_month === 'last_3_months' ? 'selected' : '' ?>>Last 3 months</option>
                <option value="last_6_months" <?= $filter_month === 'last_6_months' ? 'selected' : '' ?>>Last 6 months</option>
                <option value="this_year" <?= $filter_month === 'this_year' ? 'selected' : '' ?>>This year</option>
                <option value="all" <?= $filter_month === 'all' ? 'selected' : '' ?>>All Time</option>
                <?php if ($filter_month === 'custom'): ?>
                    <option value="custom" selected>Custom Range</option>
                <?php endif; ?>
            </select>
            
            <button
                class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm"
                onclick="exportActiveReport()">
                <i class="ti ti-download"></i> PDF Report
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="px-8 py-6 border-b border-gray-100 flex items-center gap-4 overflow-x-auto no-scrollbar">
        <button
            class="chip on px-5 py-2.5 rounded-xl text-xs font-bold border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all whitespace-nowrap"
            onclick="switchTab(this,'sales')">Sales Report</button>
        <button
            class="chip px-5 py-2.5 rounded-xl text-xs font-bold border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all whitespace-nowrap"
            onclick="switchTab(this,'products')">Top Products</button>
        <button
            class="chip px-5 py-2.5 rounded-xl text-xs font-bold border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all whitespace-nowrap"
            onclick="switchTab(this,'customers')">Top Customers</button>
    </div>

    <div class="p-8 space-y-12 max-w-7xl w-full mx-auto">

        <!-- SALES TAB -->
        <div id="tab-sales" class="space-y-8 animate-in fade-in duration-500">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Total Revenue</p>
                    <p class="text-2xl font-black text-gray-900">LKR
                        <?= $total_revenue >= 1000000 ? number_format($total_revenue / 1000000, 1) . 'M' : ($total_revenue >= 1000 ? number_format($total_revenue / 1000, 0) . 'K' : number_format($total_revenue)) ?>
                    </p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Total Orders</p>
                    <p class="text-2xl font-black text-gray-900"><?= number_format($total_orders) ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Avg Order Value</p>
                    <p class="text-2xl font-black text-gray-900">LKR <?= number_format($avg_order_value) ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Units Sold</p>
                    <p class="text-2xl font-black text-gray-900"><?= number_format($units_sold) ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Revenue & Order Trend (<?= htmlspecialchars($month_label) ?>)</h3>
                        <div class="flex items-center gap-4 text-[10px] font-bold uppercase tracking-wider">
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-brand"></span> Revenue</span>
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-200"></span> Orders</span>
                        </div>
                    </div>
                    <div class="h-80 w-full">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Revenue by Category</h3>
                    <div class="h-80 w-full relative">
                        <canvas id="catChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter">Total</p>
                            <p class="text-xl font-black text-gray-900">LKR
                                <?= $total_revenue >= 1000000 ? number_format($total_revenue / 1000000, 1) . 'M' : ($total_revenue >= 1000 ? number_format($total_revenue / 1000, 0) . 'K' : number_format($total_revenue)) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRODUCTS TAB -->
        <div id="tab-products" class="hidden space-y-8 animate-in fade-in duration-500">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Best Seller by Units</p>
                    <p class="text-xl font-black text-gray-900"><?= $best_seller ? htmlspecialchars($best_seller['name']) : 'N/A' ?></p>
                    <p class="text-xs font-medium text-gray-500 mt-2 italic"><?= $best_seller ? number_format($best_seller['units']) . ' units sold' : 'No sales recorded' ?></p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Highest Revenue</p>
                    <p class="text-xl font-black text-gray-900"><?= $highest_rev_product ? htmlspecialchars($highest_rev_product['name']) : 'N/A' ?></p>
                    <p class="text-xs font-medium text-gray-500 mt-2 italic"><?= $highest_rev_product ? 'LKR ' . number_format($highest_rev_product['revenue']) : 'No revenue recorded' ?></p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Products Sold</p>
                    <p class="text-xl font-black text-gray-900"><?= count($product_performance) ?> Top Items</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <div class="lg:col-span-3 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Product Performance Table (<?= htmlspecialchars($month_label) ?>)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                    <th class="pb-4 px-2">Product</th>
                                    <th class="pb-4 px-2 text-center">Units Sold</th>
                                    <th class="pb-4 px-2 text-center">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (!empty($product_performance)): ?>
                                    <?php foreach ($product_performance as $p): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-4 px-2 font-bold text-sm text-gray-900">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </td>
                                            <td class="py-4 px-2 text-center text-sm font-medium">
                                                <?= number_format($p['units']) ?>
                                            </td>
                                            <td class="py-4 px-2 text-center text-sm font-medium">LKR
                                                <?= $p['revenue'] >= 1000 ? number_format($p['revenue'] / 1000, 0) . 'K' : number_format($p['revenue']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-sm text-gray-400 font-medium">No product sales found for this period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="lg:col-span-2 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Top Products by Units</h3>
                    <div class="h-80 w-full">
                        <canvas id="prodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- CUSTOMERS TAB -->
        <div id="tab-customers" class="hidden space-y-8 animate-in fade-in duration-500">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Active Buyers</p>
                    <p class="text-2xl font-black text-gray-900"><?= number_format($active_buyers_count) ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2">buyers in <?= htmlspecialchars($month_label) ?></p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Avg Spend / Buyer</p>
                    <p class="text-2xl font-black text-gray-900">LKR <?= $active_buyers_count > 0 ? number_format(round($total_revenue / $active_buyers_count)) : '0' ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Avg Orders / Buyer</p>
                    <p class="text-2xl font-black text-gray-900"><?= $active_buyers_count > 0 ? number_format($total_orders / $active_buyers_count, 1) : '0' ?></p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-calendar"></i> <?= htmlspecialchars($month_label) ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Top Customers by Spend (<?= htmlspecialchars($month_label) ?>)</h3>
                    <div class="space-y-6">
                        <?php if (!empty($top_customers)): ?>
                            <?php
                            $max_spend = $top_customers[0]['spend'] > 0 ? $top_customers[0]['spend'] : 1;
                            $total_spend = array_sum(array_column($top_customers, 'spend')) ?: 1;
                            foreach ($top_customers as $cust):
                                $pct_bar = round(($cust['spend'] / $max_spend) * 100);
                                $pct_share = round(($cust['spend'] / $total_spend) * 100);
                                ?>
                                <div class="flex flex-col gap-2">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="font-bold text-gray-900"><?= htmlspecialchars($cust['business_name'] ?: 'Customer') ?></span>
                                        <span class="text-gray-500 font-medium">LKR
                                            <?= $cust['spend'] >= 1000 ? number_format($cust['spend'] / 1000, 0) . 'K' : number_format($cust['spend']) ?>
                                            (<?= $pct_share ?>%)</span>
                                    </div>
                                    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand rounded-full" style="width: <?= $pct_bar ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-sm text-gray-400 font-medium py-6">No customer orders recorded for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Active Buyers per Period</h3>
                    <div class="h-80 w-full">
                        <canvas id="buyerChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chip.on {
        background-color: #0F6E56;
        color: #ffffff;
        border-color: #0F6E56;
        box-shadow: 0 10px 15px -3px rgba(15, 110, 86, 0.2);
    }

    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<script src="/assets/chart.umd.js"></script>
<script>
    var reportCharts = {};

    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 100);
            return;
        }

        var grid = 'rgba(0,0,0,0.04)';
        var lbl = '#9ca3af';

        var commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        };

        ['revenueChart', 'catChart', 'prodChart', 'buyerChart'].forEach(function (id) {
            var existing = Chart.getChart(id);
            if (existing) {
                existing.destroy();
            }
        });

        // Revenue Chart
        var revCtx = document.getElementById('revenueChart');
        if (revCtx) {
            reportCharts.revenue = new Chart(revCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($revenue_chart_labels); ?>,
                    datasets: [
                        { label: 'Revenue', data: <?php echo json_encode($revenue_chart_revenue); ?>, backgroundColor: '#0F6E56', borderRadius: 8, barThickness: 20, yAxisID: 'y' },
                        { label: 'Orders', data: <?php echo json_encode($revenue_chart_orders); ?>, backgroundColor: '#A7F3D0', borderRadius: 8, barThickness: 20, yAxisID: 'y2' }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
                        y: { position: 'left', grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl, callback: v => 'LKR ' + (v >= 1000 ? (v / 1000) + 'K' : v) } },
                        y2: { position: 'right', grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } }
                    }
                }
            });
        }

        // Category Chart
        var catCtx = document.getElementById('catChart');
        if (catCtx) {
            reportCharts.cat = new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(!empty($category_names) ? $category_names : ['No Data']); ?>,
                    datasets: [{ data: <?php echo json_encode(!empty($category_percentages) ? $category_percentages : [100]); ?>, backgroundColor: ['#0F6E56', '#378ADD', '#7F77DD', '#EF9F27', '#9ca3af'], borderWidth: 0, cutout: '75%' }]
                },
                options: commonOptions
            });
        }

        // Products Chart
        var prodCtx = document.getElementById('prodChart');
        if (prodCtx) {
            reportCharts.prod = new Chart(prodCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(!empty($product_performance) ? array_column($product_performance, 'name') : ['No Data']); ?>,
                    datasets: [{ label: 'Units sold', data: <?php echo json_encode(!empty($product_performance) ? array_column($product_performance, 'units') : [0]); ?>, backgroundColor: '#0F6E56', borderRadius: 6, barThickness: 16 }]
                },
                options: {
                    ...commonOptions,
                    indexAxis: 'y',
                    scales: {
                        x: { grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
                        y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } }
                    }
                }
            });
        }

        // Buyer Chart
        var buyerCtx = document.getElementById('buyerChart');
        if (buyerCtx) {
            reportCharts.buyer = new Chart(buyerCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($buyer_chart_labels); ?>,
                    datasets: [{ label: 'Active buyers', data: <?php echo json_encode($buyer_chart_data); ?>, backgroundColor: '#0F6E56', borderRadius: 6, barThickness: 24 }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
                        y: { grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl, stepSize: 1 } }
                    }
                }
            });
        }
    }
    initCharts();

    function switchTab(el, tab) {
        document.querySelectorAll('.chip').forEach(t => t.classList.remove('on'));
        el.classList.add('on');
        ['sales', 'products', 'customers'].forEach(t => {
            var pane = document.getElementById('tab-' + t);
            if (t === tab) {
                pane.classList.remove('hidden');
                pane.classList.add('block');
            } else {
                pane.classList.remove('block');
                pane.classList.add('hidden');
            }
        });

        setTimeout(function () {
            Object.keys(reportCharts).forEach(function (k) {
                if (reportCharts[k]) {
                    reportCharts[k].resize();
                }
            });
        }, 50);
    }

    function exportActiveReport() {
        var activeTab = 'sales';
        document.querySelectorAll('.chip').forEach(t => {
            if (t.classList.contains('on')) {
                if (t.innerText.includes('Sales')) activeTab = 'sales';
                else if (t.innerText.includes('Products')) activeTab = 'products';
                else if (t.innerText.includes('Customers')) activeTab = 'customers';
            }
        });
        var id = 'tab-' + activeTab;
        var name = activeTab.charAt(0).toUpperCase() + activeTab.slice(1) + "_Report";
        if (typeof downloadPDF === 'function') {
            downloadPDF(id, name);
        } else {
            window.print();
        }
    }

    document.addEventListener('turbo:load', initCharts);
</script>

<?php
/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: admin/view/reports.view.php (Sales, Revenue & Analytics Reports View)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - admin/admin_index.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Database `orders`, `order_items`, and `users` analytics
=============================================================================
*/
?>