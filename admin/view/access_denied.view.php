<!-- Access Denied Page -->
<!-- Displayed when a staff member attempts to access a page they do not have permissions for based on their role. -->
<div class="flex-1 bg-gray-50 flex items-center justify-center p-8">
    <div class="max-w-md w-full bg-white border border-gray-100 rounded-[2.5rem] p-10 md:p-12 shadow-2xl text-center space-y-8 relative overflow-hidden group">
        <!-- Abstract top glow -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-48 h-1 bg-red-500 rounded-b-full shadow-[0_0_20px_2px_rgba(239,68,68,0.5)]"></div>
        
        <div class="space-y-4">
            <!-- Warning Badge & Icon -->
            <div class="w-20 h-20 rounded-3xl bg-red-50 text-red-500 flex items-center justify-center mx-auto shadow-md border border-red-100/50 group-hover:scale-105 transition-transform duration-500">
                <i class="ti ti-lock-square-rounded text-4xl"></i>
            </div>
            
            <div class="space-y-2">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Access Denied</h1>
                <p class="text-xs font-semibold text-red-500 uppercase tracking-widest">Restricted Administrative Area</p>
            </div>
        </div>

        <hr class="border-gray-50">

        <p class="text-sm text-gray-500 leading-relaxed font-medium">
            Your staff account role (<span class="font-bold text-gray-900"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'guest'))) ?></span>) is not authorised to view this page. If you believe this is an error, please contact your logistics administrator.
        </p>

        <div class="space-y-3">
            <a href="/admin-dashboard" class="w-full bg-gray-900 text-white font-bold py-4 px-6 rounded-2xl hover:bg-gray-800 transition-all transform hover:-translate-y-px shadow-lg shadow-gray-900/10 active:scale-95 flex items-center justify-center gap-2">
                <i class="ti ti-arrow-left text-lg"></i>
                Return to Dashboard
            </a>
            <a href="/admin-login?action=logout" class="w-full bg-white text-red-500 border border-red-100 font-bold py-3.5 px-6 rounded-2xl hover:bg-red-50/50 hover:border-red-200 transition-all flex items-center justify-center gap-2 text-xs">
                <i class="ti ti-logout text-lg"></i>
                Sign In with Different Account
            </a>
        </div>

        <!-- Decorative corner icon -->
        <div class="absolute -bottom-6 -right-6 opacity-[0.02] text-gray-900 group-hover:rotate-12 transition-transform duration-700">
            <i class="ti ti-shield text-[120px]"></i>
        </div>
    </div>
</div>
