<?php
session_start();
require_once '../../../config/db_connect.php';
// Include PHPMailer if it's installed
require_once '../../../vendor/autoload.php'; // Adjust path as needed for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if email_id is provided
if (!isset($_POST['email_id']) || !is_numeric($_POST['email_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email ID']);
    exit();
}

$email_id = (int)$_POST['email_id'];

try {
    // First, update the email status to 'processing' to prevent duplicate sends
    $stmt = $conn->prepare("UPDATE email_queue SET scheduled_time = NOW(), status = 'processing' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $email_id);
    
    if (!$stmt->execute() || $stmt->affected_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found or already being processed']);
        exit();
    }
    
    // Now get the email details
    $stmt = $conn->prepare("SELECT recipient_id, recipient_email, subject, content, cc_emails, bcc_emails FROM email_queue WHERE id = ?");
    $stmt->bind_param("i", $email_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit();
    }
    
    $email = $result->fetch_assoc();
    
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
        
        // Check if we have cc_emails column and process it if it exists
        if (isset($email['cc_emails']) && !is_null($email['cc_emails'])) {
            $cc_emails = json_decode($email['cc_emails'], true);
            if (is_array($cc_emails)) {
                foreach ($cc_emails as $cc) {
                    $mail->addCC($cc);
                }
            }
        }
        
        // Check if we have bcc_emails column and process it if it exists
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
            $update->bind_param("i", $email_id);
            $update->execute();
            
            // Log this action if activity_logs table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
            if ($table_check->num_rows > 0) {
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) 
                                           VALUES (?, 'send_email', 'email', ?, 'Email sent to " . $email['recipient_email'] . "', ?)");
                $log_stmt->bind_param("iis", $_SESSION['id'], $email_id, $_SERVER['REMOTE_ADDR']);
                $log_stmt->execute();
            }
            
            echo json_encode(['success' => true]);
        } else {
            // Update status to 'failed'
            $update = $conn->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
            $update->bind_param("i", $email_id);
            $update->execute();
            
            echo json_encode(['success' => false, 'message' => 'Failed to send email']);
        }
    } catch (Exception $e) {
        // Update status to 'failed'
        $update = $conn->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
        $update->bind_param("i", $email_id);
        $update->execute();
        
        echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 