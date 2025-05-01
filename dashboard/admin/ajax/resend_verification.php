<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['id'];

// Get user details
$stmt = $conn->prepare("SELECT email, email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if email is already verified
if ($user['email_verified']) {
    echo json_encode(['success' => false, 'message' => 'Email is already verified']);
    exit;
}

// Generate verification token
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Store verification token
$stmt = $conn->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $token, $expiry);

if ($stmt->execute()) {
    // Send verification email
    $verification_link = SITE_URL . "/verify-email.php?token=" . $token;
    $to = $user['email'];
    $subject = "Email Verification - Visafy";
    
    $message = "Hello,\n\n";
    $message .= "Please click the following link to verify your email address:\n";
    $message .= $verification_link . "\n\n";
    $message .= "This link will expire in 24 hours.\n\n";
    $message .= "If you didn't request this verification, please ignore this email.\n\n";
    $message .= "Best regards,\nVisafy Team";
    
    $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Verification email sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to generate verification token']);
}
$stmt->close();
