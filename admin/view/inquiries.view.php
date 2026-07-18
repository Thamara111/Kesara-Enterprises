<?php
/**
 * Inquiries View
 */

$role = $_SESSION['admin_role'] ?? 'guest';
$user_id = $_SESSION['admin_id'] ?? 0;
$can_assign = in_array($role, ['admin', 'finance_manager']);

$inquiries = [];
$staff_list = [];

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch staff members for the dropdown
        if ($can_assign) {
            $stmt = $pdo->query("SELECT id, username, role FROM admins WHERE deleted_at IS NULL ORDER BY username ASC");
            $staff_list = $stmt->fetchAll();
        }

        // Fetch inquiries based on role
        if ($can_assign) {
            $stmt = $pdo->query("
                SELECT i.*, a.username as assigned_name 
                FROM inquiries i 
                LEFT JOIN admins a ON i.assigned_to = a.id 
                ORDER BY i.created_at DESC
            ");
            $inquiries = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT i.*, a.username as assigned_name 
                FROM inquiries i 
                LEFT JOIN admins a ON i.assigned_to = a.id 
                WHERE i.assigned_to = ? 
                ORDER BY i.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $inquiries = $stmt->fetchAll();
        }
    } catch (\Exception $e) {
        // Fallback
    }
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'pending': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'in_progress': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'resolved': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}
?>

<div class="flex-1 flex flex-col min-w-0 bg-white lg:rounded-tl-3xl shadow-2xl overflow-hidden relative">
    <div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
        <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-8 uppercase tracking-wider">
            <a href="/admin-dashboard" class="hover:text-brand transition-all">Dashboard</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900 font-bold">Inquiries</span>
        </nav>

        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Customer Inquiries</h1>
                <p class="text-xs text-gray-500 mt-1">Manage and assign customer inquiries.</p>
            </div>
        </div>

        <?php if (empty($inquiries)): ?>
            <div class="flex flex-col items-center justify-center py-24 text-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center shadow-inner">
                    <i class="ti ti-inbox text-3xl text-gray-300"></i>
                </div>
                <div class="max-w-xs">
                    <h3 class="text-gray-900 font-bold text-lg">No Inquiries Found</h3>
                    <p class="text-xs font-medium text-gray-500 mt-1">You don't have any inquiries assigned to you right now.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Details</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer Info</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Assigned To</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($inquiries as $iq): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-gray-900"><?= htmlspecialchars($iq['inquiry_type']) ?></div>
                                <div class="text-xs text-gray-500 mt-1 truncate max-w-xs" title="<?= htmlspecialchars($iq['message']) ?>">
                                    <?= htmlspecialchars($iq['message']) ?>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-1"><?= date('M d, Y h:i A', strtotime($iq['created_at'])) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-sm text-gray-900"><?= htmlspecialchars($iq['name']) ?></div>
                                <div class="text-xs text-gray-500">
                                    <a href="mailto:<?= htmlspecialchars($iq['email']) ?>" class="hover:text-brand"><?= htmlspecialchars($iq['email']) ?></a>
                                    <?php if ($iq['phone']): ?> | <a href="tel:<?= htmlspecialchars($iq['phone']) ?>" class="hover:text-brand"><?= htmlspecialchars($iq['phone']) ?></a><?php endif; ?>
                                </div>
                                <?php if ($iq['business_name']): ?>
                                <div class="text-[10px] font-bold text-gray-400 mt-0.5 uppercase tracking-wider"><?= htmlspecialchars($iq['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <select onchange="updateStatus(<?= $iq['id'] ?>, this.value)" class="text-xs font-bold px-3 py-1.5 rounded-full border border-gray-200 outline-none cursor-pointer focus:ring-1 focus:ring-brand transition-all <?= getStatusBadgeClass($iq['status'] ?: 'pending') ?>">
                                    <option value="pending" <?= ($iq['status'] === 'pending' || !$iq['status']) ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $iq['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $iq['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($can_assign): ?>
                                    <select onchange="assignInquiry(<?= $iq['id'] ?>, this.value)" class="w-full text-xs font-bold px-3 py-2 bg-gray-50 border border-gray-100 rounded-xl outline-none cursor-pointer focus:bg-white focus:ring-1 focus:ring-brand transition-all">
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($staff_list as $staff): ?>
                                            <option value="<?= $staff['id'] ?>" <?= ($iq['assigned_to'] == $staff['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($staff['username']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $staff['role'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-brand bg-brand/10 px-3 py-1.5 rounded-lg">
                                        <?= htmlspecialchars($iq['assigned_name'] ?: 'Me') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-6 right-6 transform translate-y-20 opacity-0 transition-all duration-300 z-50 pointer-events-none">
    <div id="toast-content" class="bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-3">
        <i id="toast-icon" class="ti ti-check text-green-400 text-xl"></i>
        <span id="toast-message">Action successful</span>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    var toast = document.getElementById('toast');
    var msgEl = document.getElementById('toast-message');
    var icon = document.getElementById('toast-icon');
    
    msgEl.textContent = message;
    icon.className = type === 'success' ? 'ti ti-check text-green-400 text-xl' : 'ti ti-alert-triangle text-red-400 text-xl';
    
    toast.classList.remove('translate-y-20', 'opacity-0');
    
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
    }, 3000);
}

function updateStatus(id, status) {
    fetch('/api/admin_inquiries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', id: id, status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Status updated!');
            setTimeout(() => window.location.reload(), 500); // Reload to update badge colors
        } else {
            showToast(data.message, 'error');
        }
    });
}

function assignInquiry(id, assigned_to) {
    fetch('/api/admin_inquiries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_assignment', id: id, assigned_to: assigned_to })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Inquiry assigned successfully!');
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>
