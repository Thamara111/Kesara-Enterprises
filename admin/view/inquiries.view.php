<?php
/**
 * Inquiries View
 * Manages customer inquiries (e.g., from the Contact Us page).
 * Allows admins and finance managers to view, assign, and respond to messages.
 */

$role = $_SESSION['admin_role'] ?? 'guest';
$user_id = $_SESSION['admin_id'] ?? 0;
$can_assign = in_array($role, ['admin', 'finance_manager']);

$inquiries = [];
$staff_list = [];

$filter_status = $_GET['status'] ?? 'all';
$status_clause = "";
$queryParams = [];

if (in_array($filter_status, ['pending', 'in_progress', 'resolved'])) {
    $status_clause = " AND (i.status = ? OR (? = 'pending' AND i.status IS NULL)) ";
    $queryParams[] = $filter_status;
    $queryParams[] = $filter_status;
}

if (isset($pdo) && $pdo !== null) {
    try {
        // Fetch staff members for the dropdown
        if ($can_assign) {
            $stmt = $pdo->query("SELECT id, username, role FROM admins WHERE deleted_at IS NULL ORDER BY username ASC");
            $staff_list = $stmt->fetchAll();
        }

        // Fetch inquiries based on role
        if ($can_assign) {
            $sql = "
                SELECT i.*, a.username as assigned_name 
                FROM inquiries i 
                LEFT JOIN admins a ON i.assigned_to = a.id 
                WHERE 1=1 {$status_clause}
                ORDER BY i.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($queryParams);
            $inquiries = $stmt->fetchAll();
        } else {
            $sql = "
                SELECT i.*, a.username as assigned_name 
                FROM inquiries i 
                LEFT JOIN admins a ON i.assigned_to = a.id 
                WHERE i.assigned_to = ? {$status_clause}
                ORDER BY i.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$user_id], $queryParams));
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

        <!-- Global Status Filter -->
        <div class="flex items-center gap-2 mb-8 bg-gray-50 p-1.5 rounded-2xl w-max border border-gray-100">
            <a href="?status=all" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter_status === 'all' ? 'bg-white text-brand shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-900' ?>">All</a>
            <a href="?status=pending" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter_status === 'pending' ? 'bg-white text-yellow-600 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-900' ?>">Pending</a>
            <a href="?status=in_progress" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter_status === 'in_progress' ? 'bg-white text-blue-600 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-900' ?>">In Progress</a>
            <a href="?status=resolved" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter_status === 'resolved' ? 'bg-white text-green-600 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-900' ?>">Resolved</a>
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
                <table class="w-full text-left border-collapse table-fixed">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-6 py-4 text-[10px] w-4/12 font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Details</th>
                            <th class="px-6 py-4 text-[10px] w-3/12 font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer Info</th>
                            <th class="px-6 py-4 text-[10px] w-2/12 font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Status</th>
                            <th class="px-6 py-4 text-[10px] w-2/12 font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap">Assigned To</th>
                            <th class="px-6 py-4 text-[10px] w-1/12 font-bold text-gray-500 uppercase tracking-widest whitespace-nowrap text-right">Actions</th>
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
                                <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full border border-gray-200 <?= getStatusBadgeClass($iq['status'] ?: 'pending') ?>">
                                    <?= match($iq['status'] ?: 'pending') {
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        default => 'Pending'
                                    } ?>
                                </span>
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
                            <td class="px-6 py-4 text-right">
                                <button onclick="openReplyModal(<?= $iq['id'] ?>, '<?= htmlspecialchars(addslashes($iq['email'])) ?>', '<?= htmlspecialchars(addslashes($iq['name'])) ?>', '<?= htmlspecialchars(addslashes($iq['inquiry_type'])) ?>')" class="w-8 h-8 rounded-xl bg-brand-light text-brand hover:bg-brand hover:text-white transition-colors flex items-center justify-center ml-auto" title="Reply to Customer">
                                    <i class="ti ti-mail-forward text-lg"></i>
                                </button>
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

<!-- Reply Modal -->
<div id="reply-modal" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-opacity duration-300">
    <div class="absolute inset-0 bg-black/50" onclick="closeReplyModal()"></div>
    <div class="bg-white rounded-3xl w-full max-w-lg mx-4 z-10 shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh]">
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-extrabold text-gray-900 tracking-tight">Reply to Customer</h3>
                <p id="reply-customer-name" class="text-xs text-gray-500 mt-1"></p>
            </div>
            <button type="button" onclick="closeReplyModal()" class="text-gray-400 hover:text-red-500 transition-colors">
                <i class="ti ti-x text-2xl"></i>
            </button>
        </div>
        <form id="reply-form" onsubmit="submitReply(event)" class="flex-1 overflow-y-auto p-8 custom-scrollbar" enctype="multipart/form-data">
            <input type="hidden" id="reply-inquiry-id" name="id">
            
            <div class="space-y-6">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">To</label>
                    <input type="email" id="reply-email" name="to_email" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-900 outline-none" readonly>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Subject</label>
                    <input type="text" id="reply-subject" name="subject" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-semibold text-gray-900 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Message</label>
                    <textarea id="reply-message" name="message" rows="5" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm text-gray-900 focus:border-brand focus:ring-4 focus:ring-brand/10 outline-none transition-all custom-scrollbar resize-none" required placeholder="Write your reply or quote here..."></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Attachment (Optional)</label>
                    <input type="file" id="reply-attachment" name="attachment" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-900 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-brand/10 file:text-brand hover:file:bg-brand/20 transition-all cursor-pointer">
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeReplyModal()" class="px-6 py-2.5 rounded-xl font-bold text-sm text-gray-500 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" id="reply-submit-btn" class="px-6 py-2.5 rounded-xl font-bold text-sm bg-brand text-white hover:bg-brand-dark hover:shadow-lg hover:shadow-brand/20 transition-all flex items-center gap-2">
                    <i class="ti ti-send"></i> Send Reply
                </button>
            </div>
        </form>
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
            setTimeout(() => window.location.reload(), 3000); // Reload to update badge colors
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

function openReplyModal(id, email, name, type) {
    document.getElementById('reply-inquiry-id').value = id;
    document.getElementById('reply-email').value = email;
    document.getElementById('reply-customer-name').textContent = 'Replying to: ' + name;
    document.getElementById('reply-subject').value = 'Re: ' + type;
    document.getElementById('reply-message').value = '';
    
    var modal = document.getElementById('reply-modal');
    var modalInner = modal.querySelector('.bg-white');
    
    modal.classList.remove('pointer-events-none', 'opacity-0');
    modalInner.classList.remove('scale-95');
    modalInner.classList.add('scale-100');
}

function closeReplyModal() {
    var modal = document.getElementById('reply-modal');
    var modalInner = modal.querySelector('.bg-white');
    
    modalInner.classList.remove('scale-100');
    modalInner.classList.add('scale-95');
    modal.classList.add('opacity-0');
    
    setTimeout(() => {
        modal.classList.add('pointer-events-none');
    }, 300);
}

function submitReply(e) {
    e.preventDefault();
    var form = document.getElementById('reply-form');
    var btn = document.getElementById('reply-submit-btn');
    var originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Sending...';
    btn.disabled = true;
    
    var formData = new FormData(form);
    formData.append('action', 'send_reply');
    formData.append('new_status', 'resolved');
    
    fetch('/api/admin_inquiries.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.status === 'success') {
            showToast('Reply sent successfully!');
            closeReplyModal();
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showToast('Network error occurred.', 'error');
    });
}
</script>
