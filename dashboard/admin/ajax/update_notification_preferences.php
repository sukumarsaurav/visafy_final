<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['id'];
$notifications = $_POST['notifications'];

// Begin transaction
$conn->begin_transaction();

try {
    // Delete existing preferences
    $stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Insert new preferences
    $stmt = $conn->prepare("INSERT INTO notification_preferences 
        (user_id, notification_type, email_enabled, push_enabled, in_app_enabled) 
        VALUES (?, ?, ?, ?, ?)");
    
    foreach ($notifications as $type => $settings) {
        $email_enabled = isset($settings['email']) ? 1 : 0;
        $push_enabled = isset($settings['push']) ? 1 : 0;
        $in_app_enabled = isset($settings['inapp']) ? 1 : 0;
        
        $stmt->bind_param("isiii", 
            $user_id, 
            $type, 
            $email_enabled, 
            $push_enabled, 
            $in_app_enabled
        );
        $stmt->execute();
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to update notification preferences']);
}
