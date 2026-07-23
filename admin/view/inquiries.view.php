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
        
        // Fetch pending count
        if ($can_assign) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE status IS NULL OR status = 'pending'");
            $stmt->execute();
            $pending_count = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE assigned_to = ? AND (status IS NULL OR status = 'pending')");
            $stmt->execute([$user_id]);
            $pending_count = $stmt->fetchColumn();
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

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Customer Inquiries</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and assign customer inquiries.</p>
            </div>
        </div>

        <?php if (!empty($pending_count) && $pending_count > 0): ?>
            <div class="px-8 pt-6 pb-2">
                <div class="p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-start gap-3">
                    <i class="ti ti-alert-triangle text-amber-600 text-lg flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-sm font-bold text-amber-900">Pending Inquiries</p>
                        <p class="text-xs text-amber-700 mt-1">You have <strong><?= $pending_count ?></strong> inquiries awaiting your response.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex flex-nowrap gap-2 overflow-x-auto no-scrollbar" id="inquiry-filters">
                <button onclick="loadInquiries('all', this)" class="<?= $filter_status === 'all' ? 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on' : 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip' ?>" data-inactiveclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip" data-activeclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on">All</button>
                <button onclick="loadInquiries('pending', this)" class="<?= $filter_status === 'pending' ? 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on' : 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip' ?>" data-inactiveclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip" data-activeclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on">Pending</button>
                <button onclick="loadInquiries('in_progress', this)" class="<?= $filter_status === 'in_progress' ? 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on' : 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip' ?>" data-inactiveclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip" data-activeclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on">In Progress</button>
                <button onclick="loadInquiries('resolved', this)" class="<?= $filter_status === 'resolved' ? 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on' : 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip' ?>" data-inactiveclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-gray-500 border border-gray-200 hover:bg-gray-50 chip" data-activeclass="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-brand text-brand-light shadow-md shadow-brand/10 border border-transparent chip on">Resolved</button>
            </div>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar pb-10" id="inquiries-table-container">
            <div class="min-w-[800px] p-6 space-y-1">

        <?php if (empty($inquiries)): ?>
            <div class="flex flex-col items-center justify-center py-24 text-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-300">
                    <i class="ti ti-inbox text-3xl"></i>
                </div>
                <div class="max-w-xs">
                    <h3 class="text-sm font-bold text-gray-400">No Inquiries Found</h3>
                    <p class="text-xs text-gray-300 mt-1">You don't have any inquiries assigned to you right now.</p>
                </div>
            </div>
        <?php else: ?>
                <table class="w-full text-left border-separate table-fixed" style="border-spacing: 0 4px;">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                            <th class="px-6 py-3 rounded-l-xl w-4/12">Details</th>
                            <th class="px-6 py-3 w-3/12">Customer Info</th>
                            <th class="px-6 py-3 w-2/12">Status</th>
                            <th class="px-6 py-3 w-2/12">Assigned To</th>
                            <th class="px-6 py-3 text-right rounded-r-xl w-1/12">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inquiry-table-body">
                        <?php foreach($inquiries as $iq): ?>
                        <tr class="inquiry-row bg-white hover:bg-gray-50/50 transition-colors group shadow-sm" data-id="<?= $iq['id'] ?>">
                            <td class="p-4 border-y border-l border-gray-100 rounded-l-2xl group-hover:border-brand/30">
                                <div class="font-bold text-sm text-gray-900"><?= htmlspecialchars($iq['inquiry_type']) ?></div>
                                <div class="text-xs text-gray-500 mt-1 truncate max-w-xs" title="<?= htmlspecialchars($iq['message']) ?>">
                                    <?= htmlspecialchars($iq['message']) ?>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-1"><?= date('M d, Y h:i A', strtotime($iq['created_at'])) ?></div>
                            </td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                <div class="font-bold text-sm text-gray-900"><?= htmlspecialchars($iq['name']) ?></div>
                                <div class="text-xs text-gray-500">
                                    <a href="mailto:<?= htmlspecialchars($iq['email']) ?>" class="hover:text-brand"><?= htmlspecialchars($iq['email']) ?></a>
                                    <?php if ($iq['phone']): ?> | <a href="tel:<?= htmlspecialchars($iq['phone']) ?>" class="hover:text-brand"><?= htmlspecialchars($iq['phone']) ?></a><?php endif; ?>
                                </div>
                                <?php if ($iq['business_name']): ?>
                                <div class="text-[10px] font-bold text-gray-400 mt-0.5 uppercase tracking-wider"><?= htmlspecialchars($iq['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                <span class="inline-block text-[9px] uppercase tracking-wider font-bold px-3 py-1 rounded-full border <?= getStatusBadgeClass($iq['status'] ?: 'pending') ?>">
                                    <?= match($iq['status'] ?: 'pending') {
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        default => 'Pending'
                                    } ?>
                                </span>
                            </td>
                            <td class="p-4 border-y border-gray-100 group-hover:border-brand/30">
                                <?php if ($can_assign): ?>
                                    <select onchange="updateAssignment(<?= $iq['id'] ?>, this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-xl focus:ring-brand focus:border-brand block w-full p-2 outline-none font-bold">
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
                            <td class="p-4 border-y border-r border-gray-100 rounded-r-2xl group-hover:border-brand/30 text-right">
                                <button onclick="openReplyModal(<?= $iq['id'] ?>, '<?= htmlspecialchars(addslashes($iq['email'])) ?>', '<?= htmlspecialchars(addslashes($iq['name'])) ?>', '<?= htmlspecialchars(addslashes($iq['inquiry_type'])) ?>')" class="w-8 h-8 rounded-xl bg-brand-light text-brand hover:bg-brand hover:text-white transition-colors inline-flex items-center justify-center ml-auto" title="Reply to Customer">
                                    <i class="ti ti-mail-forward text-lg"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        <?php endif; ?>
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

function loadInquiries(status, btnElement) {
    if (btnElement) setButtonLoading(btnElement, true);
    
    // Update active states
    document.querySelectorAll('#inquiry-filters .chip').forEach(btn => {
        btn.className = btn.dataset.inactiveclass;
    });
    btnElement.className = btnElement.dataset.activeclass;
    
    fetch('/admin-inquiries?status=' + status)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('inquiries-table-container');
            if (newTable) {
                document.getElementById('inquiries-table-container').innerHTML = newTable.innerHTML;
            }
            if (btnElement) setButtonLoading(btnElement, false);
            window.history.pushState({}, '', '/admin-inquiries?status=' + status);
            currentPage = 1;
            applyFilters();
        })
        .catch(err => {
            console.error(err);
            if (btnElement) setButtonLoading(btnElement, false);
        });
}

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
    
    // Prev button
    var prevDisabled = currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === 1 ? '' : 'goToPage(' + (currentPage - 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${prevDisabled}"><i class="ti ti-chevron-left"></i></button>`;

    // Page numbers
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

    // Next button
    var nextDisabled = currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
    html += `<button onclick="${currentPage === totalPages ? '' : 'goToPage(' + (currentPage + 1) + ')'}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-all ${nextDisabled}"><i class="ti ti-chevron-right"></i></button>`;

    buttons.innerHTML = html;
}

function applyFilters() {
    var list = document.getElementById('inquiry-table-body');
    if (!list) return; // if empty state
    var rows = Array.from(list.querySelectorAll('.inquiry-row'));
    var visibleRows = rows; // They are already filtered by the server fetch

    // They are already sorted by DESC (newest first) from server, but to be sure:
    visibleRows.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));

    var totalItems = visibleRows.length;
    var totalPages = Math.ceil(totalItems / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    var start = (currentPage - 1) * itemsPerPage;
    var end = start + itemsPerPage;

    visibleRows.forEach((r, index) => {
        if (index >= start && index < end) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    visibleRows.forEach(r => list.appendChild(r));
    renderPagination(totalItems, totalPages);
}

// Initial render
document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});

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
