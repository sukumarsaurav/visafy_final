<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Messages";
$page_specific_css = "assets/css/messages.css";
require_once 'includes/header.php';

// Define formatTimeAgo function at the top to ensure it's available
function formatTimeAgo($timestamp) {
    $current_time = time();
    $time_difference = $current_time - strtotime($timestamp);
    
    if ($time_difference < 60) {
        return "just now";
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', strtotime($timestamp));
    }
}

// Get all conversations for current user
$user_id = $_SESSION['id'];

// Fetch all conversations the current user is participating in
$query = "SELECT c.id, c.title, c.type, c.application_id, c.created_at, c.last_message_at,
          (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
          (SELECT CONCAT(u.first_name, ' ', u.last_name) 
           FROM messages m 
           JOIN users u ON m.user_id = u.id 
           WHERE m.conversation_id = c.id 
           ORDER BY m.created_at DESC LIMIT 1) as last_message_sender,
          (SELECT COUNT(m.id) 
           FROM messages m 
           LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
           WHERE m.conversation_id = c.id AND m.user_id != ? AND mrs.id IS NULL) as unread_count
          FROM conversations c
          JOIN conversation_participants cp ON c.id = cp.conversation_id
          WHERE cp.user_id = ? AND cp.left_at IS NULL
          ORDER BY c.last_message_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
}
$stmt->close();

// Get specific conversation if ID is provided
$selected_conversation = null;
$conversation_messages = [];
$conversation_participants = [];

if (isset($_GET['conversation_id']) && !empty($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];
    
    // Get conversation details
    $query = "SELECT c.*, 
              CASE WHEN c.type = 'direct' THEN 
                (SELECT CONCAT(u.first_name, ' ', u.last_name)
                 FROM conversation_participants cp
                 JOIN users u ON cp.user_id = u.id
                 WHERE cp.conversation_id = c.id 
                 AND cp.user_id <> ? AND cp.left_at IS NULL
                 LIMIT 1)
              ELSE c.title END as display_name
              FROM conversations c
              WHERE c.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $conversation_id);
    $stmt->execute();
    $selected_conversation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($selected_conversation) {
        // Get conversation messages
        $query = "SELECT m.*, 
                  u.first_name, u.last_name, u.profile_picture,
                  (SELECT COUNT(*) FROM message_reactions mr WHERE mr.message_id = m.id) as reaction_count
                  FROM messages m
                  JOIN users u ON m.user_id = u.id
                  WHERE m.conversation_id = ? AND m.deleted_at IS NULL
                  ORDER BY m.created_at ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $conversation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conversation_messages[] = $row;
        }
        $stmt->close();
        
        // Get conversation participants
        $query = "SELECT cp.role, cp.joined_at, 
                 u.id, u.first_name, u.last_name, u.profile_picture, u.status
                 FROM conversation_participants cp
                 JOIN users u ON cp.user_id = u.id
                 WHERE cp.conversation_id = ? AND cp.left_at IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $conversation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conversation_participants[] = $row;
        }
        $stmt->close();
        
        // Mark messages as read
        $query = "INSERT INTO message_read_status (message_id, user_id, read_at)
                 SELECT m.id, ?, NOW()
                 FROM messages m
                 LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
                 WHERE m.conversation_id = ? 
                 AND m.user_id != ? 
                 AND mrs.id IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiii', $user_id, $user_id, $conversation_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $conversation_id = $_POST['conversation_id'];
    $message_text = trim($_POST['message_text']);
    
    if (!empty($message_text)) {
        $query = "INSERT INTO messages (conversation_id, user_id, message, created_at)
                 VALUES (?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iis', $conversation_id, $user_id, $message_text);
        
        if ($stmt->execute()) {
            // Redirect to prevent form resubmission
            header("Location: messages.php?conversation_id=" . $conversation_id);
            exit;
        } else {
            $error_message = "Error sending message: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_message = "Message cannot be empty";
    }
}

// Handle creating a new conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conversation'])) {
    $participants = isset($_POST['participants']) ? $_POST['participants'] : [];
    $title = isset($_POST['group_title']) ? trim($_POST['group_title']) : null;
    $type = (count($participants) > 1 || !empty($title)) ? 'group' : 'direct';
    
    $errors = [];
    if (empty($participants)) {
        $errors[] = "Please select at least one participant";
    }
    
    if ($type === 'group' && empty($title)) {
        $errors[] = "Group title is required";
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create new conversation
            $query = "INSERT INTO conversations (title, type, created_by, created_at, last_message_at)
                     VALUES (?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $title, $type, $user_id);
            $stmt->execute();
            
            $new_conversation_id = $conn->insert_id;
            $stmt->close();
            
            // Add current user as participant
            $query = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at)
                     VALUES (?, ?, 'admin', NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $new_conversation_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Add other participants
            foreach ($participants as $participant_id) {
                // Get user type for role
                $query = "SELECT user_type FROM users WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $participant_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $role = $user['user_type'];
                $stmt->close();
                
                $query = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at)
                         VALUES (?, ?, ?, NOW())";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iis', $new_conversation_id, $participant_id, $role);
                $stmt->execute();
                $stmt->close();
            }
            
            // Add system message
            $system_message = $type === 'direct' ? 'Conversation started' : 'Group conversation created';
            $query = "INSERT INTO messages (conversation_id, user_id, message, is_system_message, created_at)
                     VALUES (?, ?, ?, 1, NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iis', $new_conversation_id, $user_id, $system_message);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to the new conversation
            header("Location: messages.php?conversation_id=" . $new_conversation_id);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error creating conversation: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get all possible participants for new conversations
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.user_type, u.profile_picture
          FROM users u
          WHERE u.id <> ? AND u.status = 'active' AND u.deleted_at IS NULL
          ORDER BY u.first_name, u.last_name";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$potential_participants = [];

while ($row = $result->fetch_assoc()) {
    $potential_participants[] = $row;
}
$stmt->close();
?>

<div class="content-wrapper">
    <div class="messaging-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h1>Conversations</h1>
                <button type="button" class="btn-new-conversation" id="newConversationBtn">
                    <i class="fas fa-plus"></i> New
                </button>
            </div>
            
            <div class="search-bar">
                <input type="text" id="conversation-search" placeholder="Search conversations...">
                <i class="fas fa-search"></i>
            </div>
            
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="loading-conversations">
                        Loading conversations...
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="messages.php?conversation_id=<?php echo $conversation['id']; ?>" 
                           class="conversation-item <?php echo (isset($_GET['conversation_id']) && $_GET['conversation_id'] == $conversation['id']) ? 'active' : ''; ?>">
                            <div class="conversation-avatar">
                                <?php if ($conversation['type'] === 'group'): ?>
                                    <div class="group-avatar">
                                        <i class="fas fa-users"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="initials">
                                        <?php 
                                        // Get the other participant's name in direct conversations
                                        $query = "SELECT CONCAT(u.first_name, ' ', u.last_name) as name
                                                  FROM conversation_participants cp
                                                  JOIN users u ON cp.user_id = u.id
                                                  WHERE cp.conversation_id = ? AND cp.user_id <> ? AND cp.left_at IS NULL
                                                  LIMIT 1";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param('ii', $conversation['id'], $user_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $other_user = $result->fetch_assoc();
                                        $stmt->close();
                                        
                                        $name_parts = explode(' ', $other_user['name']);
                                        echo substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-details">
                                <div class="conversation-header">
                                    <h4>
                                        <?php 
                                        if ($conversation['type'] === 'group') {
                                            echo htmlspecialchars($conversation['title']);
                                        } else {
                                            echo htmlspecialchars($other_user['name']);
                                        }
                                        ?>
                                    </h4>
                                    <span class="conversation-time">
                                        <?php echo formatTimeAgo($conversation['last_message_at']); ?>
                                    </span>
                                </div>
                                <div class="conversation-preview">
                                    <p>
                                        <?php if ($conversation['last_message']): ?>
                                            <?php echo htmlspecialchars(substr($conversation['last_message'], 0, 50) . (strlen($conversation['last_message']) > 50 ? '...' : '')); ?>
                                        <?php else: ?>
                                            No messages yet
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($conversation['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="messages-area">
            <?php if ($selected_conversation): ?>
                <div class="message-header">
                    <div class="conversation-info">
                        <div class="conversation-avatar large">
                            <?php if ($selected_conversation['type'] === 'group'): ?>
                                <div class="group-avatar">
                                    <i class="fas fa-users"></i>
                                </div>
                            <?php else: ?>
                                <div class="initials">
                                    <?php 
                                    $name_parts = explode(' ', $selected_conversation['display_name']);
                                    echo substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($selected_conversation['display_name']); ?></h3>
                            <p>
                                <?php if ($selected_conversation['type'] === 'group'): ?>
                                    <?php echo count($conversation_participants); ?> participants
                                <?php else: ?>
                                    Active now
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="conversation-actions">
                        <button type="button" class="btn-icon" title="Info" id="infoBtn">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <div class="message-content">
                    <?php if (empty($conversation_messages)): ?>
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $date_groups = [];
                        foreach ($conversation_messages as $message) {
                            $date = date('Y-m-d', strtotime($message['created_at']));
                            if (!isset($date_groups[$date])) {
                                $date_groups[$date] = [];
                            }
                            $date_groups[$date][] = $message;
                        }
                        ?>
                        
                        <?php foreach ($date_groups as $date => $messages): ?>
                            <div class="message-date-divider">
                                <span>
                                    <?php 
                                    $today = date('Y-m-d');
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    
                                    if ($date === $today) {
                                        echo 'Today';
                                    } elseif ($date === $yesterday) {
                                        echo 'Yesterday';
                                    } else {
                                        echo date('F j, Y', strtotime($date));
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php foreach ($messages as $message): ?>
                                <div class="message-bubble <?php echo $message['user_id'] == $user_id ? 'outgoing' : 'incoming'; ?>">
                                    <?php if ($message['user_id'] != $user_id): ?>
                                        <div class="message-avatar">
                                            <?php if (!empty($message['profile_picture'])): ?>
                                                <img src="../../uploads/profiles/<?php echo $message['profile_picture']; ?>" alt="Profile">
                                            <?php else: ?>
                                                <div class="initials">
                                                    <?php echo substr($message['first_name'], 0, 1) . substr($message['last_name'], 0, 1); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="message-container">
                                        <?php if ($message['user_id'] != $user_id): ?>
                                            <div class="message-sender">
                                                <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="message-content <?php echo $message['is_system_message'] ? 'system-message' : ''; ?>">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        
                                        <div class="message-info">
                                            <span class="message-time">
                                                <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                            </span>
                                            
                                            <?php if ($message['reaction_count'] > 0): ?>
                                                <span class="reaction-count" title="<?php echo $message['reaction_count']; ?> reactions">
                                                    <i class="fas fa-thumbs-up"></i> <?php echo $message['reaction_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="message-input">
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="conversation_id" value="<?php echo $selected_conversation['id']; ?>">
                        <div class="input-container">
                            <textarea name="message_text" placeholder="Type a message..." rows="1" required></textarea>
                            <div class="input-actions">
                                <button type="button" class="btn-icon" title="Attach File">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button type="button" class="btn-icon" title="Emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="send_message" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
                
                <div class="conversation-info-panel" id="infoPanel">
                    <div class="info-header">
                        <h3>Conversation Info</h3>
                        <button type="button" class="close-info" id="closeInfoBtn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="info-content">
                        <div class="info-section">
                            <h4>Participants (<?php echo count($conversation_participants); ?>)</h4>
                            <div class="participant-list">
                                <?php foreach ($conversation_participants as $participant): ?>
                                    <div class="participant-item">
                                        <div class="participant-avatar">
                                            <?php if (!empty($participant['profile_picture'])): ?>
                                                <img src="../../uploads/profiles/<?php echo $participant['profile_picture']; ?>" alt="Profile">
                                            <?php else: ?>
                                                <div class="initials">
                                                    <?php echo substr($participant['first_name'], 0, 1) . substr($participant['last_name'], 0, 1); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="participant-details">
                                            <h5><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></h5>
                                            <span class="participant-role"><?php echo ucfirst($participant['role']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if ($selected_conversation['type'] === 'group'): ?>
                            <div class="info-section">
                                <h4>Created</h4>
                                <p><?php echo date('F j, Y', strtotime($selected_conversation['created_at'])); ?></p>
                            </div>
                            
                            <div class="info-actions">
                                <button type="button" class="btn danger-btn">
                                    <i class="fas fa-sign-out-alt"></i> Leave Conversation
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-chat-state">
                    <img src="../../assets/images/chat-icon.png" alt="Chat" class="chat-icon" onerror="this.src='../../assets/images/chat-placeholder.svg'">
                    <h3>Select a conversation or start a new one</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal" id="newConversationModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">New Conversation</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="messages.php" method="POST" id="newConversationForm">
                    <div class="form-group">
                        <label for="conversation_type">Conversation Type</label>
                        <div class="type-selector">
                            <label class="type-option">
                                <input type="radio" name="conversation_type" value="direct" checked>
                                <span class="type-label">Direct Message</span>
                            </label>
                            <label class="type-option">
                                <input type="radio" name="conversation_type" value="group">
                                <span class="type-label">Group Conversation</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="groupTitleContainer" style="display: none;">
                        <label for="group_title">Group Title*</label>
                        <input type="text" name="group_title" id="group_title" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="participants">Participants*</label>
                        <div class="search-participants">
                            <input type="text" id="participant-search" placeholder="Search users...">
                        </div>
                        
                        <div class="participant-selection">
                            <?php foreach ($potential_participants as $participant): ?>
                                <div class="participant-option">
                                    <label>
                                        <input type="checkbox" name="participants[]" value="<?php echo $participant['id']; ?>">
                                        <div class="participant-info">
                                            <div class="participant-avatar small">
                                                <?php if (!empty($participant['profile_picture'])): ?>
                                                    <img src="../../uploads/profiles/<?php echo $participant['profile_picture']; ?>" alt="Profile">
                                                <?php else: ?>
                                                    <div class="initials">
                                                        <?php echo substr($participant['first_name'], 0, 1) . substr($participant['last_name'], 0, 1); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="participant-details">
                                                <h5><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></h5>
                                                <span class="participant-role"><?php echo ucfirst($participant['user_type']); ?></span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_conversation" class="btn submit-btn">Create Conversation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --message-outgoing-bg: #e1f0ff;
    --message-incoming-bg: #f1f3f8;
    --system-message-bg: #f8f9fc;
}

.content-wrapper {
    height: calc(100vh - 70px);
}

.messaging-container {
    display: flex;
    height: 100%;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

/* Conversations Sidebar */
.conversations-sidebar {
    width: 320px;
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    background-color: white;
}

.conversations-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.conversations-header h1 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--dark-color);
}

.btn-new-conversation {
    background-color: #4e73df;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-new-conversation:hover {
    background-color: #375ad3;
}

.search-bar {
    padding: 10px 15px;
    position: relative;
    border-bottom: 1px solid var(--border-color);
}

.search-bar input {
    width: 100%;
    padding: 8px 30px 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.search-bar i {
    position: absolute;
    right: 25px;
    top: 50%;
    transform: translateY(-50%);
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
}

.loading-conversations {
    padding: 20px;
    text-align: center;
    color: var(--secondary-color);
    font-size: 14px;
}

.conversation-item {
    display: flex;
    padding: 12px 15px;
    text-decoration: none;
    color: var(--dark-color);
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    gap: 12px;
}

.conversation-item:hover {
    background-color: #f8f9fc;
}

.conversation-item.active {
    background-color: #f0f2f8;
    border-left: 3px solid var(--primary-color);
}

.conversation-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4e73df;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    flex-shrink: 0;
}

.conversation-avatar.large {
    width: 48px;
    height: 48px;
    font-size: 18px;
}

.conversation-avatar.small {
    width: 32px;
    height: 32px;
    font-size: 12px;
}

.group-avatar {
    background-color: #4e73df;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.conversation-details {
    flex: 1;
    min-width: 0;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.conversation-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--dark-color);
}

.conversation-time {
    font-size: 12px;
    color: var(--secondary-color);
    white-space: nowrap;
}

.conversation-preview {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-preview p {
    margin: 0;
    font-size: 13px;
    color: var(--secondary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-badge {
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    background-color: #4e73df;
    color: white;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

/* Messages Area */
.messages-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    position: relative;
}

.message-header {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.conversation-info h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--dark-color);
}

.conversation-info p {
    margin: 3px 0 0;
    font-size: 13px;
    color: var(--secondary-color);
}

.conversation-actions {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background-color: transparent;
    color: var(--secondary-color);
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
}

.btn-icon:hover {
    background-color: #f0f2f8;
    color: var(--primary-color);
}

.message-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 15px;
    background-color: #f8f9fc;
}

.message-date-divider {
    text-align: center;
    margin: 15px 0;
    position: relative;
}

.message-date-divider::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 100%;
    height: 1px;
    background-color: var(--border-color);
    z-index: 1;
}

.message-date-divider span {
    background-color: #f8f9fc;
    padding: 0 10px;
    font-size: 12px;
    color: var(--secondary-color);
    position: relative;
    z-index: 2;
}

.message-bubble {
    display: flex;
    margin-bottom: 10px;
    gap: 10px;
    max-width: 70%;
}

.message-bubble.incoming {
    align-self: flex-start;
}

.message-bubble.outgoing {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4e73df;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
    flex-shrink: 0;
}
.participant-avatar img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.message-container {
    display: flex;
    flex-direction: column;
}

.message-sender {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--dark-color);
}

.message-bubble .message-content {
    padding: 12px 15px;
    border-radius: 18px;
    background-color: white;
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    width: auto;
    flex: 0;
    height: auto;
    overflow: visible;
}

.message-bubble.outgoing .message-content {
    background-color: #4e73df;
    color: white;
}

.system-message {
    background-color: var(--system-message-bg) !important;
    font-size: 12px !important;
    font-style: italic !important;
    color: var(--secondary-color) !important;
    text-align: center !important;
    border: 1px dashed var(--border-color) !important;
}

.message-info {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
}

.message-time {
    font-size: 11px;
    color: var(--secondary-color);
}

.message-bubble.outgoing .message-time {
    color: #e1e1e1;
}

.reaction-count {
    font-size: 11px;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 3px;
}

.message-input {
    padding: 15px;
    border-top: 1px solid var(--border-color);
    background-color: white;
}

.message-input form {
    display: flex;
    gap: 10px;
}

.input-container {
    flex: 1;
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 8px 15px;
    display: flex;
    align-items: center;
    background-color: #f8f9fc;
}

.input-container textarea {
    flex: 1;
    border: none;
    resize: none;
    height: 24px;
    max-height: 80px;
    font-size: 14px;
    outline: none;
    padding: 0;
    font-family: inherit;
    background-color: transparent;
}

.input-actions {
    display: flex;
    gap: 5px;
}

.send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4e73df;
    color: white;
    border: none;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
}

.send-btn:hover {
    background-color: #375ad3;
}

.empty-chat-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    background-color: #f8f9fc;
}

.chat-icon {
    width: 100px;
    height: 100px;
    opacity: 0.6;
    margin-bottom: 20px;
}

.empty-chat-state h3 {
    margin: 0;
    color: var(--secondary-color);
    font-size: 16px;
    font-weight: 500;
}

.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--secondary-color);
    text-align: center;
}

.empty-chat i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Conversation Info Panel */
.conversation-info-panel {
    position: absolute;
    top: 0;
    right: -300px;
    width: 300px;
    height: 100%;
    background-color: white;
    border-left: 1px solid var(--border-color);
    transition: right 0.3s;
    z-index: 10;
    display: flex;
    flex-direction: column;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.05);
}

.conversation-info-panel.open {
    right: 0;
}

.info-header {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.info-header h3 {
    margin: 0;
    font-size: 16px;
    color: var(--dark-color);
    font-weight: 600;
}

.close-info {
    background: none;
    border: none;
    font-size: 16px;
    color: var(--secondary-color);
    cursor: pointer;
}

.info-content {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

.info-section {
    margin-bottom: 20px;
}

.info-section h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: var(--secondary-color);
    font-weight: 500;
}

.participant-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.participant-details h5 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--dark-color);
}

.participant-role {
    font-size: 12px;
    color: var(--secondary-color);
}

.info-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.danger-btn {
    background-color: var(--danger-color);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 14px;
    transition: background-color 0.2s;
}

.danger-btn:hover {
    background-color: #d44235;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
}

.modal-dialog {
    margin: 60px auto;
    max-width: 500px;
    width: calc(100% - 40px);
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    margin: 0;
    color: var(--dark-color);
    font-size: 18px;
    font-weight: 600;
}

.close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: var(--secondary-color);
    line-height: 1;
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.type-selector {
    display: flex;
    gap: 15px;
}

.type-option {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.type-option input {
    margin-bottom: 8px;
}

.type-option:has(input:checked) {
    border-color: #4e73df;
    background-color: rgba(78, 115, 223, 0.05);
}

.search-participants {
    margin-bottom: 10px;
}

.search-participants input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.participant-selection {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.participant-option {
    padding: 10px;
    border-bottom: 1px solid var(--border-color);
}

.participant-option:last-child {
    border-bottom: none;
}

.participant-option label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    margin: 0;
    width: 100%;
}

.participant-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.submit-btn {
    background-color: #4e73df;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.submit-btn:hover {
    background-color: #375ad3;
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 14px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

@media (max-width: 768px) {
    .messaging-container {
        flex-direction: column;
    }
    
    .conversations-sidebar {
        width: 100%;
        height: 300px;
    }
    
    .message-bubble {
        max-width: 85%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // New conversation modal
    const newConversationBtn = document.getElementById('newConversationBtn');
    const newConversationModal = document.getElementById('newConversationModal');
    const closeModalBtns = document.querySelectorAll('[data-dismiss="modal"]');
    
    if (newConversationBtn) {
        newConversationBtn.addEventListener('click', function() {
            newConversationModal.style.display = 'block';
        });
    }
    
    closeModalBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            newConversationModal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === newConversationModal) {
            newConversationModal.style.display = 'none';
        }
    });
    
    // Toggle conversation type
    const conversationTypeRadios = document.querySelectorAll('input[name="conversation_type"]');
    const groupTitleContainer = document.getElementById('groupTitleContainer');
    
    conversationTypeRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'group') {
                groupTitleContainer.style.display = 'block';
                document.getElementById('group_title').setAttribute('required', 'required');
            } else {
                groupTitleContainer.style.display = 'none';
                document.getElementById('group_title').removeAttribute('required');
            }
        });
    });
    
    // Filter participants
    const participantSearch = document.getElementById('participant-search');
    if (participantSearch) {
        participantSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = document.querySelectorAll('.participant-option');
            
            options.forEach(function(option) {
                const name = option.querySelector('h5').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
    
    // Toggle conversation info panel
    const infoBtn = document.getElementById('infoBtn');
    const infoPanel = document.getElementById('infoPanel');
    const closeInfoBtn = document.getElementById('closeInfoBtn');
    
    if (infoBtn && infoPanel) {
        infoBtn.addEventListener('click', function() {
            infoPanel.classList.toggle('open');
        });
        
        closeInfoBtn.addEventListener('click', function() {
            infoPanel.classList.remove('open');
        });
    }
    
    // Filter conversations
    const conversationSearch = document.getElementById('conversation-search');
    if (conversationSearch) {
        conversationSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            
            items.forEach(function(item) {
                const name = item.querySelector('h4').textContent.toLowerCase();
                const message = item.querySelector('.conversation-preview p').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || message.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Auto-resize textarea
    const messageTextarea = document.querySelector('.message-input textarea');
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    // Scroll to bottom of messages
    const messageContent = document.querySelector('.message-content');
    if (messageContent) {
        messageContent.scrollTop = messageContent.scrollHeight;
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
