<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    
    /**
     * Send an email using local MailDev server
     * 
     * @param string $to Recipient email address
     * @param string $subject Subject of the email
     * @param string $body HTML body of the email
     * @return bool True if email was sent, false otherwise
     */
    public static function send($to, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            // Server settings for MailDev
            $mail->isSMTP();                                            
            $mail->Host       = '127.0.0.1';                     
            $mail->SMTPAuth   = false;                                   
            $mail->Port       = 1025;                                    

            // Sender and recipient
            // Using a default sender for the system
            $mail->setFrom('noreply@kesara.lk', 'Kesara Enterprises');
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);                                  
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log error or handle it as needed
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
