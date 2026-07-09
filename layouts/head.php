<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$buyer_approved = false;

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
            $auth_stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
            $auth_stmt->execute([$_SESSION['user_id']]);
            $auth_user = $auth_stmt->fetch();
            if ($auth_user && $auth_user['status'] === 'approved') {
                $buyer_approved = true;
            }
        } catch (\Exception $e) {
            $buyer_approved = false;
        }
    }
}
$can_see_prices = $is_logged_in && $buyer_approved;
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- PDF Generation (Local) -->
    <script src="/assets/js/html2pdf.bundle.min.js"></script>
    <script src="/assets/js/pdf-helper.js"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

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
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
            opacity: 0;
            border-left: 4px solid #cbd5e1;
        }

        .toast-notification.show {
            transform: translateX(0);
            opacity: 1;
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
    </script>
</head>

<body class="font-sans">