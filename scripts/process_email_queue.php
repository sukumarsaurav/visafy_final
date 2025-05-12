<?php
// This script should be called by a cron job to process emails in the queue
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Define the batch size (how many emails to process at once)
$batch_size = defined('EMAIL_BATCH_SIZE') ? EMAIL_BATCH_SIZE : 10;

// Get pending emails that are due for sending
$query = "SELECT id, recipient_id, recipient_email, subject, content, cc_emails, bcc_emails 
          FROM email_queue 
          WHERE status = 'pending' AND scheduled_time <= NOW()
          ORDER BY scheduled_time ASC
          LIMIT " . $batch_size;

$result = $conn->query($query);

$sent_count = 0;
$failed_count = 0;

while ($email = $result->fetch_assoc()) {
    // Mark as processing
    $update = $conn->prepare("UPDATE email_queue SET status = 'processing' WHERE id = ?");
    $update->bind_param("i", $email['id']);
    $update->execute();
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        if (defined('MAIL_DRIVER') && MAIL_DRIVER == 'smtp') {
            $mail->isSMTP();
            $mail->Host = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
            $mail->Password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
            $mail->SMTPSecure = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls';
            $mail->Port = defined('MAIL_PORT') ? MAIL_PORT : 587;
        }
        
        // Recipients
        $mail->setFrom(defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@example.com', 
                     defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Visafy System');
        $mail->addAddress($email['recipient_email']);
        
        // CC and BCC
        $cc_emails = null;
        $bcc_emails = null;
        
        // Check if we have cc_emails column and process it
        if (isset($email['cc_emails']) && !is_null($email['cc_emails'])) {
            $cc_emails = json_decode($email['cc_emails'], true);
            if (is_array($cc_emails)) {
                foreach ($cc_emails as $cc) {
                    $mail->addCC($cc);
                }
            }
        }
        
        // Check if we have bcc_emails column and process it
        if (isset($email['bcc_emails']) && !is_null($email['bcc_emails'])) {
            $bcc_emails = json_decode($email['bcc_emails'], true);
            if (is_array($bcc_emails)) {
                foreach ($bcc_emails as $bcc) {
                    $mail->addBCC($bcc);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $email['subject'];
        $mail->Body = $email['content'];
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $email['content']));
        
        // Send the email
        if ($mail->send()) {
            // Update the status to 'sent'
            $update = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update->bind_param("i", $email['id']);
            $update->execute();
            $sent_count++;
        } else {
            // Update status to 'failed'
            $update = $conn->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
            $update->bind_param("i", $email['id']);
            $update->execute();
            $failed_count++;
        }
    } catch (Exception $e) {
        // Update status to 'failed'
        $update = $conn->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
        $update->bind_param("i", $email['id']);
        $update->execute();
        $failed_count++;
        
        // Log error
        error_log("Email sending failed for ID {$email['id']}: " . $mail->ErrorInfo);
    }
    
    // Sleep briefly to prevent overwhelming the mail server
    usleep(100000); // 100ms delay
}

echo "Processed emails: " . ($sent_count + $failed_count) . ", Sent: " . $sent_count . ", Failed: " . $failed_count . "\n"; 