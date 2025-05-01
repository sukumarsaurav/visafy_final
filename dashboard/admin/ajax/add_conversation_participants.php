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
$conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
$new_participants = isset($_POST['new_participants']) ? $_POST['new_participants'] : [];

// Validate required fields
if (empty($conversation_id)) {
    echo json_encode(['error' => 'Conversation ID is required']);
    exit();
}

if (empty($new_participants) || !is_array($new_participants)) {
    echo json_encode(['error' => 'At least one participant is required']);
    exit();
}

try {
    // Check if conversation exists and if current user has access to it
    $sql = "SELECT c.id, c.type, c.title
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE c.id = ? AND cp.user_id = ? AND cp.left_at IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $conversation_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Conversation not found or you don't have access to it");
    }
    
    $conversation = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    // Check which users are already in the conversation
    $sql = "SELECT user_id FROM conversation_participants 
            WHERE conversation_id = ? AND left_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $existing_participants = [];
    while ($row = $result->fetch_assoc()) {
        $existing_participants[] = $row['user_id'];
    }
    
    // Filter out users who are already participants
    $new_users = array_diff($new_participants, $existing_participants);
    
    if (empty($new_users)) {
        // No new users to add
        echo json_encode(['success' => true, 'message' => 'All selected users are already participants']);
        exit();
    }
    
    // Add the new participants
    $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at) 
            VALUES (?, ?, 'participant', NOW())";
    $stmt = $conn->prepare($sql);
    
    foreach ($new_users as $user_id) {
        $stmt->bind_param("ii", $conversation_id, $user_id);
        $stmt->execute();
    }
    
    // Add a system message about the new participants
    $sql = "SELECT CONCAT(first_name, ' ', last_name) as name 
            FROM users WHERE id IN (" . implode(',', array_fill(0, count($new_users), '?')) . ")";
    
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($new_users));
    $stmt->bind_param($types, ...$new_users);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $added_names = [];
    while ($row = $result->fetch_assoc()) {
        $added_names[] = $row['name'];
    }
    
    $admin_name = $_SESSION["first_name"] . ' ' . $_SESSION["last_name"];
    $message_content = $admin_name . ' added ' . implode(', ', $added_names) . ' to the conversation';
    
    $sql = "INSERT INTO messages (conversation_id, sender_id, message_type, content, created_at) 
            VALUES (?, NULL, 'system_notification', ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $conversation_id, $message_content);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Participants added successfully',
        'added_count' => count($new_users)
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('Error adding participants: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to add participants: ' . $e->getMessage()]);
} 