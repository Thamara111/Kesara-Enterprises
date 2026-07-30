<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    
    /**
     * Determines a clean subtitle label based on email subject or custom input
     */
    public static function determineSubtitle($subject, $customSubtitle = null) {
        if (!empty($customSubtitle)) return $customSubtitle;
        
        $sub = strtolower($subject);
        if (strpos($sub, 'security') !== false || strpos($sub, 'password') !== false) {
            return 'Security Notification';
        } elseif (strpos($sub, 'order confirmation') !== false || strpos($sub, 'order status') !== false || strpos($sub, 'order') !== false || strpos($sub, 'ke-') !== false) {
            return 'Order Notification';
        } elseif (strpos($sub, 'account') !== false || strpos($sub, 'welcome') !== false || strpos($sub, 'approved') !== false || strpos($sub, 'registration') !== false) {
            return 'Account Notification';
        } elseif (strpos($sub, 'inquiry') !== false || strpos($sub, 'reply') !== false) {
            return 'Customer Inquiry';
        } elseif (strpos($sub, 'inventory') !== false || strpos($sub, 'stock') !== false || strpos($sub, 'warning') !== false) {
            return 'Inventory Alert';
        } elseif (strpos($sub, 'purchase order') !== false || strpos($sub, 'po-') !== false || strpos($sub, 'grn') !== false || strpos($sub, 'supplier') !== false) {
            return 'Supplier Notification';
        } elseif (strpos($sub, 'contact') !== false) {
            return 'Contact Message';
        }
        return 'System Notification';
    }

    /**
     * Wraps raw HTML content in the official Kesara Enterprises branded email card template
     */
    public static function wrapTemplate($subject, $contentHtml, $subtitle = null) {
        // If content is already a full HTML document, return as is
        if (strpos($contentHtml, '<html') !== false || strpos($contentHtml, '<HTML') !== false) {
            return $contentHtml;
        }

        $subtitleText = self::determineSubtitle($subject, $subtitle);
        $year = date('Y');

        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($subject) . "</title>
</head>
<body style='margin: 0; padding: 30px 10px; background-color: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;'>
    <div style='max-width: 580px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 32px 36px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);'>
        
        <!-- Header / Branding -->
        <div style='text-align: center; margin-bottom: 24px; border-bottom: 1px solid #f3f4f6; padding-bottom: 20px;'>
            <h2 style='color: #0F6E56; margin: 0 0 4px 0; font-size: 24px; font-weight: 800; tracking-tight: -0.02em;'>Kesara Enterprises</h2>
            <p style='color: #6b7280; font-size: 13px; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;'>" . htmlspecialchars($subtitleText) . "</p>
        </div>
        
        <!-- Body Content -->
        <div style='color: #1f2937; font-size: 14px; line-height: 1.6; margin-bottom: 28px;'>
            {$contentHtml}
        </div>

        <!-- Footer -->
        <div style='border-top: 1px solid #f3f4f6; padding-top: 20px; text-align: center; color: #9ca3af; font-size: 12px;'>
            <p style='margin: 0;'>&copy; {$year} Kesara Enterprises (Pvt) Ltd. All rights reserved.</p>
            <p style='margin: 4px 0 0 0; font-size: 11px; color: #9ca3af;'>Sri Lanka's Premier Wholesale Innerwear Supplier</p>
        </div>
    </div>
</body>
</html>
";
    }

    /**
     * Send an email using local MailDev server
     * 
     * @param string $to Recipient email address
     * @param string $subject Subject of the email
     * @param string $body HTML body of the email
     * @param mixed $attachment Optional file attachment
     * @param string $subtitle Optional custom subtitle for header
     * @return bool True if email was sent, false otherwise
     */
    public static function send($to, $subject, $body, $attachment = null, $subtitle = null) {
        $mail = new PHPMailer(true);

        try {
            // Server settings for MailDev
            $mail->isSMTP();                                            
            $mail->Host       = '127.0.0.1';                     
            $mail->SMTPAuth   = false;                                   
            $mail->Port       = 1025;                                    

            // Sender and recipient
            $mail->setFrom('noreply@kesara.lk', 'Kesara Enterprises');
            $mail->addAddress($to);

            // Wrap body in universal HTML card template
            $formattedBody = self::wrapTemplate($subject, $body, $subtitle);

            if ($attachment && is_array($attachment) && isset($attachment['tmp_name']) && is_uploaded_file($attachment['tmp_name'])) {
                $mime = mime_content_type($attachment['tmp_name']);
                if (strpos($mime, 'image/') === 0) {
                    $cid = 'embedded_img_' . time();
                    $mail->addEmbeddedImage($attachment['tmp_name'], $cid, $attachment['name']);
                    $formattedBody .= '<br><br><img src="cid:' . $cid . '" alt="' . htmlspecialchars($attachment['name']) . '" style="max-width:100%;">';
                } else {
                    $mail->addAttachment($attachment['tmp_name'], $attachment['name']);
                }
            }

            // Content
            $mail->isHTML(true);                                  
            $mail->Subject = $subject;
            $mail->Body    = $formattedBody;
            $mail->AltBody = strip_tags($formattedBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
