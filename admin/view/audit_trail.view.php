<?php
/**
 * Audit Trail View (Admin Only)
 * Allows System Admins to view the activity log of all managers and admins.
 */

$logs = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("
            SELECT al.*, a.username, a.role 
            FROM audit_logs al 
            LEFT JOIN admins a ON al.admin_id = a.id 
            ORDER BY al.created_at DESC
        ");
        $logs = $stmt->fetchAll();
    } catch (\Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Role label & badge class helpers for styling
function getRoleMetaBadge($role) {
    $meta = [
        'admin' => ['label' => 'System Admin', 'class' => 'bg-red-50 text-red-700 border-red-100'],
        'finance_manager' => ['label' => 'Finance Manager', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
        'supplier_manager' => ['label' => 'Supplier Manager', 'class' => 'bg-blue-50 text-blue-700 border-blue-100'],
        'delivery_manager' => ['label' => 'Delivery Manager', 'class' => 'bg-amber-50 text-amber-700 border-amber-100']
    ];
    return $meta[$role] ?? ['label' => ucwords(str_replace('_', ' ', $role ?? 'System')), 'class' => 'bg-gray-50 text-gray-700 border-gray-100'];
}

function getEntityLink($type) {
    $type = strtolower($type);
    if ($type === 'product') return '/admin-products';
    if ($type === 'order') return '/admin-orders';
    if ($type === 'user') return '/admin-users';
    if ($type === 'customer') return '/admin-customers';
    if ($type === 'category') return '/admin-categories';
    return '/admin-dashboard';
}

function getActionColor($action) {
    $action = strtolower($action);
    if (strpos($action, 'delete') !== false) return 'text-red-600 bg-red-50';
    if (strpos($action, 'create') !== false) return 'text-emerald-600 bg-emerald-50';
    if (strpos($action, 'update') !== false) return 'text-blue-600 bg-blue-50';
    return 'text-gray-600 bg-gray-50';
}
?>

<div class="flex-1 flex flex-col bg-white overflow-hidden">
    <!-- Header -->
    <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">System Audit Trail</h1>
            <p class="text-sm text-gray-500 mt-1">Review actions and changes made by system administrators and managers.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-4 py-2.5 bg-brand-light text-brand text-xs font-bold rounded-xl border border-brand/10 shadow-sm">
                Total Logs: <?= count($logs) ?>
            </span>
        </div>
    </div>

    <!-- Error State -->
    <?php if (isset($error_message)): ?>
        <div class="mx-8 mt-6 p-4 bg-red-50 border border-red-100 rounded-2xl flex items-start gap-3">
            <i class="ti ti-alert-circle text-red-500 mt-0.5 text-lg"></i>
            <div>
                <h4 class="text-sm font-bold text-red-800">Error fetching logs</h4>
                <p class="text-xs text-red-600 mt-0.5"><?= htmlspecialchars($error_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table Section -->
    <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar pb-10">
        <div class="min-w-[800px] p-6 space-y-1">
            <table class="w-full text-left border-separate" style="border-spacing: 0 4px;">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-4 py-3 rounded-l-xl w-32">Timestamp</th>
                        <th class="px-4 py-3 w-64">Admin / Manager</th>
                        <th class="px-4 py-3 w-40">Action</th>
                        <th class="px-4 py-3 w-48">Entity</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Details</th>
                    </tr>
                </thead>
                <tbody id="audit-list">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-gray-400">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-50 mb-4">
                                    <i class="ti ti-history text-3xl text-gray-300"></i>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 mb-1">No activity logged</h3>
                                <p class="text-xs text-gray-400">System changes will appear here once actions are performed.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $meta = getRoleMetaBadge($log['role']);
                            $date = new DateTime($log['created_at']);
                            $link = getEntityLink($log['entity_type']);
                            $actionClass = getActionColor($log['action']);
                        ?>
                            <tr class="audit-row bg-white cursor-pointer hover:bg-gray-50/50 transition-all group shadow-sm" onclick="window.location.href='<?= $link ?>'">
                                <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30 w-32">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-900 group-hover:text-brand transition-colors"><?= $date->format('M d, Y') ?></span>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5"><?= $date->format('h:i A') ?></span>
                                    </div>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs uppercase shadow-sm">
                                            <?= substr(htmlspecialchars($log['username'] ?? '?'), 0, 1) ?>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                                            <span class="text-[9px] font-bold <?= $meta['class'] ?> px-2 py-0.5 rounded-full uppercase tracking-widest mt-1 w-max border">
                                                <?= htmlspecialchars($meta['label']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                    <span class="px-2.5 py-1 rounded-full text-[9px] font-bold border uppercase tracking-widest <?= $actionClass ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($log['entity_type']) ?></span>
                                        <span class="text-[10px] font-bold text-gray-400 tracking-widest mt-0.5 uppercase">ID: <?= htmlspecialchars($log['entity_id']) ?></span>
                                    </div>
                                </td>
                                <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-right">
                                    <p class="text-xs font-medium text-gray-600 truncate max-w-[300px] ml-auto">
                                        <?php 
                                            $details = json_decode($log['details'], true);
                                            $actionStr = strtolower($log['action']);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($details) && isset($details['name'])) {
                                                $name = htmlspecialchars($details['name']);
                                                if (strpos($actionStr, 'delete') !== false) {
                                                    echo "Deleted product <strong>{$name}</strong>";
                                                } elseif (strpos($actionStr, 'create') !== false) {
                                                    echo "Added product <strong>{$name}</strong>";
                                                } elseif (strpos($actionStr, 'update') !== false) {
                                                    echo "Edited product <strong>{$name}</strong>";
                                                } else {
                                                    echo htmlspecialchars($log['details']);
                                                }
                                            } elseif (json_last_error() === JSON_ERROR_NONE && is_array($details)) {
                                                $parts = [];
                                                foreach ($details as $k => $v) {
                                                    $parts[] = ucfirst($k) . ": " . htmlspecialchars($v);
                                                }
                                                echo implode(" · ", $parts);
                                            } else {
                                                echo htmlspecialchars($log['details']);
                                            }
                                        ?>
                                    </p>
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

<script>
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
    var list = document.getElementById('audit-list');
    if (!list) return;
    var rows = Array.from(list.querySelectorAll('.audit-row'));

    var totalItems = rows.length;
    var totalPages = Math.ceil(totalItems / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    var start = (currentPage - 1) * itemsPerPage;
    var end = start + itemsPerPage;

    rows.forEach((r, index) => {
        if (index >= start && index < end) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    rows.forEach(r => list.appendChild(r));
    renderPagination(totalItems, totalPages);
}

document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});
</script>
