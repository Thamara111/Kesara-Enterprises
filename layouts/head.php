<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Cache-control headers to prevent stale display of approval-gated content
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

$is_logged_in = isset($_SESSION['user_id']);
$buyer_approved = false;
$user_type = $_SESSION['user_type'] ?? 'individual';

if ($is_logged_in) {
    if (!isset($pdo)) {
        try {
            require_once __DIR__ . "/../database/connection.php";
        } catch (\Exception $e) {
            // connection file issues
        }
    }
    if (isset($pdo)) {
        try {
            $auth_stmt = $pdo->prepare("SELECT status, user_type FROM users WHERE id = ? LIMIT 1");
            $auth_stmt->execute([$_SESSION['user_id']]);
            $auth_user = $auth_stmt->fetch();
            if ($auth_user) {
                if ($auth_user['status'] === 'approved') {
                    $buyer_approved = true;
                }
                $user_type = $auth_user['user_type'] ?? 'individual';
                $_SESSION['user_type'] = $user_type;
            }
        } catch (\Exception $e) {
            $buyer_approved = false;
        }
    }
}

$is_wholesale_buyer = $is_logged_in && ($user_type === 'wholesale') && $buyer_approved;
$can_see_prices = true;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kesara Enterprises</title>
    <link href="/dist/output.css" rel="stylesheet">
    <link href="/dist/admin-layout.css" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="/assets/alpine.min.js"></script>
    
    <!-- PDF Generation (Local) -->
    <script src="/assets/js/html2pdf.bundle.min.js"></script>
    <script src="/assets/js/pdf-helper.js"></script>
    
    <link rel="stylesheet" href="/assets/tabler-icons.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        body {
            background-color: #f9fafb;
        }

        @font-face {
            font-family: "Geograph";
            src: url("/fonts/Geograph-Regular.woff2") format("woff2");
            font-weight: 400;
        }

        @font-face {
            font-family: "Geograph";
            src: url("/fonts/Geograph-Medium.woff2") format("woff2");
            font-weight: 500;
        }

        @font-face {
            font-family: "Self Modern";
            src: url("/fonts/SelfModern-Regular.woff2") format("woff2");
            font-weight: 400;
        }
    </style>
    
    <!-- Global Toast Notifications System -->
    <style>
        #toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 380px;
            width: calc(100% - 48px);
        }

        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            z-index: 9999;
            transform: translateY(100px) scale(0.9);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
            pointer-events: none;
            max-width: 320px;
            border-left: 4px solid #3b82f6;
        }

        .toast-notification.show {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: auto;
        }

        .toast-notification.success {
            border-left-color: #0F6E56;
        }

        .toast-notification.error {
            border-left-color: #ef4444;
        }

        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 14px;
            flex-shrink: 0;
        }

        .success .toast-icon {
            background-color: #E1F5EE;
            color: #0F6E56;
        }

        .error .toast-icon {
            background-color: #fef2f2;
            color: #ef4444;
        }

        .toast-message {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            font-family: sans-serif;
            line-height: 1.4;
        }
    </style>
    <script type="module" src="/assets/turbo.es2017-esm.js"></script>
    <script>
        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;

            const iconClass = type === 'success' ? 'ti ti-circle-check' : 'ti ti-circle-x';
            toast.innerHTML = `
                <div class="toast-icon"><i class="${iconClass}"></i></div>
                <div class="toast-message">${message}</div>
            `;

            container.appendChild(toast);

            // Trigger animation
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            // Auto dismiss after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                const onTransitionEnd = () => {
                    toast.remove();
                    toast.removeEventListener('transitionend', onTransitionEnd);
                };
                toast.addEventListener('transitionend', onTransitionEnd);
            }, 4000);
        }
        window.uiConfirm = function(message, onConfirm, title = null, confirmText = null) {
            const modal = document.getElementById('global-confirm-modal');
            const box = document.getElementById('global-confirm-box');
            const titleEl = document.getElementById('global-confirm-title');
            const msgEl = document.getElementById('global-confirm-message');
            const okBtn = document.getElementById('global-confirm-ok');

            var isDeleteAction = message.toLowerCase().includes('delete') || message.toLowerCase().includes('remove') || message.toLowerCase().includes('cancel');

            if (!title) {
                if (message.toLowerCase().includes('supplier')) title = 'Delete Supplier?';
                else if (message.toLowerCase().includes('product')) title = 'Delete Product?';
                else if (message.toLowerCase().includes('category')) title = 'Delete Category?';
                else if (message.toLowerCase().includes('order')) title = 'Delete Order?';
                else if (message.toLowerCase().includes('user') || message.toLowerCase().includes('customer') || message.toLowerCase().includes('staff')) title = 'Delete Account?';
                else title = isDeleteAction ? 'Delete Item?' : 'Confirm Action';
            }

            if (!confirmText) {
                confirmText = isDeleteAction ? 'Delete' : 'Confirm';
            }

            titleEl.textContent = title;
            
            var formattedMsg = message;
            if (isDeleteAction && !formattedMsg.toLowerCase().includes('cannot be undone')) {
                formattedMsg = formattedMsg.trim();
                if (!formattedMsg.endsWith('.')) formattedMsg += '.';
                formattedMsg += ' This action cannot be undone.';
            }
            msgEl.textContent = formattedMsg;
            okBtn.textContent = confirmText;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            void modal.offsetWidth; // force reflow
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95');

            const close = () => {
                modal.classList.add('opacity-0');
                box.classList.add('scale-95');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }, 300);
                
                document.getElementById('global-confirm-cancel').onclick = null;
                document.getElementById('global-confirm-ok').onclick = null;
            };

            document.getElementById('global-confirm-cancel').onclick = close;
            document.getElementById('global-confirm-ok').onclick = () => {
                close();
                if (onConfirm) onConfirm();
            };
        };

        window.uiAlert = function(message, type = 'error') {
            showToast(message, type);
        };
        
        // Override Turbo Confirm if Turbo is active
        document.addEventListener("turbo:load", function() {
            if (window.Turbo) {
                Turbo.setConfirmMethod((message, element) => {
                    return new Promise(resolve => {
                        window.uiConfirm(message, () => resolve(true));
                        const oldClose = document.getElementById('global-confirm-cancel').onclick;
                        document.getElementById('global-confirm-cancel').onclick = () => {
                            if(oldClose) oldClose();
                            resolve(false);
                        };
                    });
                });
            }
        });
    </script>
</head>

<body class="font-sans">
    
    <!-- Global Confirm Modal -->
    <div id="global-confirm-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[10000] hidden items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-2xl max-w-sm w-full text-center flex flex-col items-center transform scale-95 transition-all duration-300" id="global-confirm-box">
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-6">
                <i class="ti ti-trash text-3xl"></i>
            </div>
            <h3 class="text-xl font-extrabold text-gray-900 mb-2" id="global-confirm-title">Delete Product?</h3>
            <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed" id="global-confirm-message">Are you sure you want to delete this product? This action cannot be undone.</p>
            <div class="flex gap-3 w-full">
                <button id="global-confirm-cancel" class="flex-1 bg-gray-50 text-gray-700 font-bold py-3.5 rounded-2xl hover:bg-gray-100 transition-colors">Cancel</button>
                <button id="global-confirm-ok" class="flex-1 bg-red-500 text-white font-bold py-3.5 rounded-2xl hover:bg-red-600 shadow-lg shadow-red-500/20 transition-all transform hover:-translate-y-px">Delete</button>
            </div>
        </div>
    </div>