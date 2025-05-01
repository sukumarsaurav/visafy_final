<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

// Check if admin is logged in
if (!is_admin_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Get current user ID
$current_user_id = $_SESSION['id'];

// Get input data
$conversation_type = isset($_POST['conversation_type']) ? $_POST['conversation_type'] : '';
$group_title = isset($_POST['group_title']) ? $_POST['group_title'] : '';
$participants = isset($_POST['participants']) ? $_POST['participants'] : [];
$initial_message = isset($_POST['initial_message']) ? $_POST['initial_message'] : '';
$related_to_type = isset($_POST['related_to_type']) ? $_POST['related_to_type'] : '';
$related_to_id = isset($_POST['related_to_id']) ? $_POST['related_to_id'] : null;

// Validate required fields
if (empty($conversation_type)) {
    echo json_encode(['error' => 'Conversation type is required']);
    exit();
}

if ($conversation_type === 'group' && empty($group_title)) {
    echo json_encode(['error' => 'Group title is required for group conversations']);
    exit();
}

if (empty($participants) || !is_array($participants)) {
    echo json_encode(['error' => 'At least one participant is required']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Create conversation
    $sql = "INSERT INTO conversations (title, type, created_by, related_to_type, related_to_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $title = ($conversation_type === 'direct') ? null : $group_title;
    
    $stmt = $conn->prepare($sql);
    
    // Handle null values for related_to_type and related_to_id
    if (empty($related_to_type)) {
        $related_to_type = null;
    }
    if (empty($related_to_id)) {
        $related_to_id = null;
        $stmt->bind_param("ssisi", $title, $conversation_type, $current_user_id, $related_to_type, $related_to_id);
    } else {
        $stmt->bind_param("ssisi", $title, $conversation_type, $current_user_id, $related_to_type, $related_to_id);
    }
    
    $stmt->execute();
    $conversation_id = $conn->insert_id;
    
    // Add the current user as admin participant
    $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at) 
            VALUES (?, ?, 'admin', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $conversation_id, $current_user_id);
    $stmt->execute();
    
    // Add other participants
    $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at) 
            VALUES (?, ?, 'participant', NOW())";
    $stmt = $conn->prepare($sql);
    
    foreach ($participants as $participant_id) {
        $stmt->bind_param("ii", $conversation_id, $participant_id);
        $stmt->execute();
    }
    
    // If there's an initial message, add it
    if (!empty($initial_message)) {
        $sql = "INSERT INTO messages (conversation_id, sender_id, message_type, content, created_at) 
                VALUES (?, ?, 'text', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $conversation_id, $current_user_id, $initial_message);
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'conversation_id' => $conversation_id,
        'message' => 'Conversation created successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('Error creating conversation: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to create conversation: ' . $e->getMessage()]);
} 