<?php
/**
 * WhatsApp Simulator View
 */

$role = $_SESSION['admin_role'] ?? 'guest';
$is_admin = ($role === 'admin');

if (!$is_admin) {
    echo "<div class='p-10'><h1 class='text-2xl font-bold text-red-600'>Access Denied</h1></div>";
    return;
}

$messages = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("
            SELECT w.*, u.first_name, u.last_name, u.business_name 
            FROM mock_whatsapp_messages w 
            LEFT JOIN users u ON w.customer_id = u.id 
            ORDER BY w.created_at DESC
        ");
        $messages = $stmt->fetchAll();
    } catch (\Exception $e) {
        // Fallback
    }
}
?>

<div class="flex-1 flex flex-col min-w-0 bg-white lg:rounded-tl-3xl shadow-2xl overflow-hidden relative">
    <div class="flex-1 overflow-y-auto p-10 custom-scrollbar bg-gray-50/30">
        <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-8 uppercase tracking-wider">
            <a href="/admin-dashboard" class="hover:text-brand transition-all">Dashboard</a>
            <i class="ti ti-chevron-right text-[10px]"></i>
            <span class="text-gray-900 font-bold">WhatsApp Simulator</span>
        </nav>

        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
                    <i class="ti ti-brand-whatsapp text-[#25D366]"></i> WhatsApp Simulator
                </h1>
                <p class="text-xs text-gray-500 mt-1">View intercepted WhatsApp notifications sent from the system.</p>
            </div>
        </div>

        <?php if (empty($messages)): ?>
            <div class="flex flex-col items-center justify-center py-24 text-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-white border border-gray-100 flex items-center justify-center shadow-sm">
                    <i class="ti ti-message-2 text-3xl text-gray-300"></i>
                </div>
                <div class="max-w-xs">
                    <h3 class="text-gray-900 font-bold text-lg">No Messages Yet</h3>
                    <p class="text-xs font-medium text-gray-500 mt-1">When the system sends a WhatsApp message, it will appear here instead of being delivered.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($messages as $msg): ?>
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden flex flex-col">
                        <div class="absolute top-0 left-0 w-full h-1.5 bg-[#25D366]"></div>
                        
                        <div class="flex justify-between items-start mb-4 mt-2">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">To</p>
                                <?php if ($msg['business_name'] || $msg['first_name']): ?>
                                    <h3 class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($msg['business_name'] ?: ($msg['first_name'] . ' ' . $msg['last_name'])) ?></h3>
                                <?php endif; ?>
                                <p class="text-sm font-semibold text-brand flex items-center gap-1">
                                    <i class="ti ti-phone text-xs"></i> <?= htmlspecialchars($msg['phone']) ?>
                                </p>
                            </div>
                            <span class="bg-[#25D366]/10 text-[#25D366] px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider flex items-center gap-1 border border-[#25D366]/20">
                                <i class="ti ti-check"></i> Delivered
                            </span>
                        </div>
                        
                        <div class="flex-1 bg-gray-50 rounded-2xl p-4 border border-gray-100 mb-4 relative">
                            <!-- WhatsApp chat bubble tail -->
                            <div class="absolute top-4 -left-2 w-4 h-4 bg-gray-50 border-l border-t border-gray-100 transform -rotate-45"></div>
                            
                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed relative z-10"><?= htmlspecialchars($msg['message']) ?></p>
                        </div>
                        
                        <div class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                            <i class="ti ti-clock"></i>
                            <?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
