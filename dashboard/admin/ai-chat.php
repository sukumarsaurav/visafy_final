<?php
$page_title = "Visafy AI Chat";
require_once 'includes/header.php';

// Get user_id from session (already verified in header.php)
$user_id = $_SESSION['id'];
$user_type = $_SESSION['user_type'];

// Check if tables exist, create them if not
$check_tables_sql = "
SHOW TABLES LIKE 'ai_chat_conversations';
";
$tables_result = $conn->query($check_tables_sql);

if ($tables_result->num_rows == 0) {
    // Include the SQL file to create tables
    $sql_file = file_get_contents('../../chatbot.sql');
    $conn->multi_query($sql_file);
    // Clear results
    while ($conn->more_results() && $conn->next_result()) {
        $dump = $conn->use_result();
        if ($dump) $dump->free();
    }
}

// Get existing conversations
$sql = "SELECT c.*, m.content as last_message, m.created_at as last_message_time
        FROM ai_chat_conversations c 
        LEFT JOIN (
            SELECT conversation_id, content, created_at,
                   ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) as rn
            FROM ai_chat_messages
            WHERE role = 'user' AND deleted_at IS NULL
        ) m ON m.conversation_id = c.id AND m.rn = 1
        WHERE c.user_id = ? AND c.deleted_at IS NULL 
        ORDER BY COALESCE(m.created_at, c.created_at) DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly usage if user is a team member
$messages_remaining = "Unlimited"; // Default for admins

if ($user_type == 'member') {
    // Get the team_member_id for this user
    $stmt = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $team_member = $result->fetch_assoc();
    $team_member_id = $team_member['id'] ?? null;
    
    if ($team_member_id) {
        $month = date('Y-m');
        $sql = "SELECT message_count FROM ai_chat_usage WHERE team_member_id = ? AND month = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $team_member_id, $month);
        $stmt->execute();
        $usage = $stmt->get_result()->fetch_assoc();
        $messages_used = $usage ? $usage['message_count'] : 0;
        $messages_remaining = 50 - $messages_used;
    }
}
?>


    <div class="chat-wrapper">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="sidebar-content">
                    <button id="new-chat" class="new-chat-btn">
                        <i class="fas fa-plus"></i> Start New AI Chat
                    </button>
                    
                    <div id="conversations-list" class="conversations-list">
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item" data-id="<?php echo htmlspecialchars($conv['id']); ?>">
                                <div class="conversation-text">
                                    <div class="conversation-title"><?php echo htmlspecialchars($conv['title']); ?></div>
                                    <div class="conversation-preview"><?php echo htmlspecialchars($conv['last_message'] ?? ''); ?></div>
                                </div>
                                <button class="delete-chat" data-id="<?php echo htmlspecialchars($conv['id']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <div class="chat-header">
                    <div class="header-left">
                        <button id="toggle-sidebar" class="toggle-sidebar-btn">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h4>New Conversation</h4>
                    </div>
                    <div class="messages-remaining">
                        <i class="fas fa-message"></i>
                        <span><?php echo $messages_remaining; ?> <?php echo $user_type == 'admin' ? '' : 'messages remaining'; ?></span>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div class="chat-messages" id="chat-messages">
                    <div class="welcome-content">
                        <img src="../../assets/images/ai-chatbot.svg" alt="AI Chat Bot" class="chat-bot-icon">
                        <h3>Welcome to AI Assistant</h3>
                        <p>I'm your visa and immigration consultant assistant. How can I help you today?</p>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="chat-input-container">
                    <form id="chat-form">
                        <input type="text" id="user-input" placeholder="Type your message here..." required>
                        <button type="submit" id="send-button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>


<script>
let activeConversationId = null;
let isSidebarVisible = true;
let userType = '<?php echo $user_type; ?>';

// Toggle sidebar
document.getElementById('toggle-sidebar').addEventListener('click', function() {
    document.querySelector('.chat-container').classList.toggle('sidebar-hidden');
    isSidebarVisible = !isSidebarVisible;
});

function setActiveConversation(conversationId, title = null) {
    activeConversationId = conversationId;
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    if (conversationId) {
        document.querySelector(`.conversation-item[data-id="${conversationId}"]`)?.classList.add('active');
    }
    if (title) {
        document.querySelector('.chat-header h4').textContent = title;
    }
}

function updateUsageCounter() {
    // Only update usage for team members, not admins
    if (userType !== 'admin') {
        fetch('ajax/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_usage'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const remaining = 50 - data.usage;
                document.querySelector('.messages-remaining span').textContent = 
                    `${remaining} messages remaining`;
            }
        });
    }
}

// Function to show typing indicator
function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'typing-indicator';
    typingDiv.id = 'typing-indicator';
    typingDiv.innerHTML = '<span></span><span></span><span></span>';
    
    const chatMessages = document.getElementById('chat-messages');
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Function to remove typing indicator
function removeTypingIndicator() {
    const typingIndicator = document.getElementById('typing-indicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

function appendMessage(content, isUser = false, isError = false) {
    // Remove typing indicator if present
    removeTypingIndicator();
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isUser ? 'user-message' : 'ai-message'} ${isError ? 'error-message' : ''}`;
    
    // For AI messages, preserve formatting
    if (!isUser && !isError) {
        messageDiv.innerHTML = content;
    } else {
        messageDiv.textContent = content;
    }
    
    const chatMessages = document.getElementById('chat-messages');
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function loadConversation(conversationId) {
    const chatMessages = document.getElementById('chat-messages');
    chatMessages.innerHTML = '<div class="loading-messages">Loading conversation...</div>';
    document.querySelector('.chat-header h4').textContent = 'Loading...';
    
    fetch('ajax/chat_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_conversation&conversation_id=${conversationId}`
    })
    .then(response => response.json())
    .then(data => {
        chatMessages.innerHTML = '';
        if (data.success) {
            setActiveConversation(conversationId, data.conversation.title);
            data.messages.forEach(msg => {
                appendMessage(msg.content, msg.role === 'user');
            });
        } else {
            appendMessage('Error loading conversation: ' + data.error, false, true);
        }
    })
    .catch(error => {
        chatMessages.innerHTML = '';
        appendMessage('Error loading conversation. Please try again.', false, true);
    });
}

function createNewChat() {
    fetch('ajax/chat_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=create_conversation'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            activeConversationId = data.conversation_id;
            
            // Create new conversation item
            const newConv = document.createElement('div');
            newConv.className = 'conversation-item active';
            newConv.dataset.id = data.conversation_id;
            newConv.innerHTML = `
                <div class="conversation-text">
                    <div class="conversation-title">New Chat</div>
                    <div class="conversation-preview"></div>
                </div>
                <button class="delete-chat" data-id="${data.conversation_id}">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            // Add to list
            const conversationsList = document.getElementById('conversations-list');
            conversationsList.insertBefore(newConv, conversationsList.firstChild);
            
            // Reset chat area
            document.getElementById('chat-messages').innerHTML = `
                <div class="welcome-content">
                    <img src="../../assets/images/ai-chat-bot.png" alt="AI Chat Bot" class="chat-bot-icon">
                    <h3>Welcome to AI Assistant</h3>
                    <p>${data.welcome_message || "I'm your visa and immigration consultant assistant. How can I help you today?"}</p>
                </div>
            `;
            document.querySelector('.chat-header h4').textContent = 'New Conversation';
            
            // Focus input
            document.getElementById('user-input').focus();
        } else {
            appendMessage('Error creating new chat: ' + data.error, false, true);
        }
    })
    .catch(error => {
        appendMessage('Error creating new chat. Please try again.', false, true);
    });
}

// New Chat button
document.getElementById('new-chat').addEventListener('click', function() {
    createNewChat();
});

// Select conversation
document.addEventListener('click', function(e) {
    if (e.target.closest('.conversation-item') && !e.target.closest('.delete-chat')) {
        const item = e.target.closest('.conversation-item');
        const conversationId = item.dataset.id;
        loadConversation(conversationId);
    }
});

// Delete conversation
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-chat')) {
        e.stopPropagation();
        const button = e.target.closest('.delete-chat');
        const conversationId = button.dataset.id;
        if (confirm('Are you sure you want to delete this conversation?')) {
            fetch('ajax/chat_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_conversation&conversation_id=${conversationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = button.closest('.conversation-item');
                    item.style.opacity = '0';
                    setTimeout(() => {
                        item.remove();
                        if (conversationId === activeConversationId) {
                            createNewChat();
                        }
                    }, 300);
                } else {
                    appendMessage('Error deleting conversation: ' + data.error, false, true);
                }
            })
            .catch(error => {
                appendMessage('Error deleting conversation. Please try again.', false, true);
            });
        }
    }
});

// Send message
document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Disable input and button
    input.disabled = true;
    sendButton.disabled = true;
    
    // If no active conversation, create one
    if (!activeConversationId) {
        fetch('ajax/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_conversation'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                activeConversationId = data.conversation_id;
                sendMessage(message);
            } else {
                input.disabled = false;
                sendButton.disabled = false;
                appendMessage('Error creating conversation: ' + data.error, false, true);
            }
        })
        .catch(error => {
            input.disabled = false;
            sendButton.disabled = false;
            appendMessage('Error creating conversation. Please try again.', false, true);
        });
    } else {
        sendMessage(message);
    }
});

function sendMessage(message) {
    const input = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    input.value = '';
    
    // Clear welcome message if present
    const welcomeContent = document.querySelector('.welcome-content');
    if (welcomeContent) {
        welcomeContent.remove();
    }
    
    appendMessage(message, true);
    
    // Show typing indicator
    showTypingIndicator();
    
    fetch('ajax/chat_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=send_message&conversation_id=${activeConversationId}&message=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        // Remove typing indicator before adding response
        removeTypingIndicator();
        
        if (data.success) {
            appendMessage(data.message);
            if (userType !== 'admin') {
                updateUsageCounter();
            }
            
            // Update conversation preview
            const conversationItem = document.querySelector(`.conversation-item[data-id="${activeConversationId}"]`);
            if (conversationItem) {
                const preview = conversationItem.querySelector('.conversation-preview');
                if (preview) {
                    preview.textContent = message;
                }
                // Move conversation to top
                const parent = conversationItem.parentNode;
                parent.insertBefore(conversationItem, parent.firstChild);
            }
            
            // Update header
            document.querySelector('.chat-header h4').textContent = 
                message.substring(0, 30) + (message.length > 30 ? '...' : '');
        } else {
            appendMessage('Error: ' + (data.error || 'Failed to get response'), false, true);
        }
    })
    .catch(error => {
        // Remove typing indicator on error
        removeTypingIndicator();
        appendMessage('Error: Failed to send message', false, true);
    })
    .finally(() => {
        input.disabled = false;
        sendButton.disabled = false;
        input.focus();
    });
}

// Create initial chat if no conversations exist
if (!document.querySelector('.conversation-item')) {
    createNewChat();
}
</script>

<style>
.chat-wrapper {
    height: calc(100vh - 60px); 
}

.chat-container {
    display: flex;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    height: 100%;
    position: relative;
    transition: all 0.3s ease;
}

.chat-sidebar {
    width: 280px;
    border-right: 1px solid #e0e0e0;
    transition: all 0.3s ease;
    background: white;
}
.chat-container.sidebar-hidden .chat-sidebar {
    margin-left: -280px;
}
.sidebar-content {
    padding: 15px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.new-chat-btn {
    width: 100%;
    padding: 12px;
    background:  #042167;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 20px;
    transition: background-color 0.2s;
}

.new-chat-btn:hover {
    background: #0056b3;
}

.new-chat-btn i {
    margin-right: 8px;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 15px;
}

.conversation-item {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.2s;
}

.conversation-item:hover {
    background-color: #e6e9ed;
}

.conversation-item.active {
    background-color: #edf2f7;
    border-left: 3px solid #3498db;
}

.conversation-text {
    flex: 1;
    overflow: hidden;
}

.conversation-title {
    font-weight: 600;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-preview {
    font-size: 12px;
    color: #718096;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.delete-chat {
    background: none;
    border: none;
    color: #a0aec0;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.7;
    transition: opacity 0.2s, color 0.2s;
}

.delete-chat:hover {
    opacity: 1;
    color: #e53e3e;
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: white;
}

.chat-header {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e6e9ed;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-left {
    display: flex;
    align-items: center;
}

.toggle-sidebar-btn {
    background: none;
    border: none;
    font-size: 18px;
    color: #4a5568;
    cursor: pointer;
    margin-right: 15px;
    display: none;
}

.chat-header h4 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
}

.messages-remaining {
    font-size: 14px;
    color: #718096;
    display: flex;
    align-items: center;
}

.messages-remaining i {
    margin-right: 6px;
}

.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background-color: #ffffff;
}

.welcome-content {
    text-align: center;
    padding: 30px;
    background-color: #f8f9fa;
    border-radius: 10px;
    margin: 20px auto;
    max-width: 600px;
}

.chat-bot-icon {
    width: 80px;
    height: 80px;
    margin-bottom: 20px;
}

.message {
    margin-bottom: 15px;
    padding: 12px 18px;
    border-radius: 18px;
    max-width: 70%;
    word-wrap: break-word;
}

.user-message {
    background-color: #3498db;
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 4px;
}

.ai-message {
    background-color: #f0f2f5;
    color: #2d3748;
    margin-right: auto;
    border-bottom-left-radius: 4px;
}

.ai-message a {
    color: #3498db;
    text-decoration: underline;
}

.ai-message ul, .ai-message ol {
    margin-top: 10px;
    margin-bottom: 10px;
    padding-left: 20px;
}

.error-message {
    background-color: #fed7d7;
    color: #c53030;
    margin-right: auto;
    border-bottom-left-radius: 4px;
}

.chat-input-container {
    padding: 15px;
    border-top: 1px solid #e6e9ed;
}

#chat-form {
    display: flex;
    align-items: center;
}

#user-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    font-size: 14px;
    outline: none;
    transition: border 0.2s;
}

#user-input:focus {
    border-color: #3498db;
}

#send-button {
    background-color: #3498db;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 10px;
    cursor: pointer;
    transition: background-color 0.2s;
}

#send-button:hover {
    background-color: #2980b9;
}

.loading-messages {
    text-align: center;
    padding: 20px;
    color: #718096;
}

/* Typing animation */
.typing-indicator {
    background-color: #f0f2f5;
    border-radius: 18px;
    border-bottom-left-radius: 4px;
    padding: 12px 18px;
    margin-bottom: 15px;
    margin-right: auto;
    max-width: 10%;
    display: flex;
    align-items: center;
}

.typing-indicator span {
    height: 8px;
    width: 8px;
    margin: 0 2px;
    background-color: #3498db;
    border-radius: 50%;
    display: inline-block;
    opacity: 0.4;
}

.typing-indicator span:nth-child(1) {
    animation: typing 1.2s infinite ease-in-out;
    animation-delay: 0s;
}

.typing-indicator span:nth-child(2) {
    animation: typing 1.2s infinite ease-in-out;
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation: typing 1.2s infinite ease-in-out;
    animation-delay: 0.4s;
}

@keyframes typing {
    0% { transform: translateY(0); opacity: 0.4; }
    50% { transform: translateY(-5px); opacity: 1; }
    100% { transform: translateY(0); opacity: 0.4; }
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 120px);
        border-radius: 0;
    }
    
    .chat-sidebar {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        z-index: 10;
        width: 280px;
    }
    
    .chat-container.sidebar-hidden .chat-sidebar {
        width: 0;
        padding: 0;
        overflow: hidden;
    }
    
    .toggle-sidebar-btn {
        display: block;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?> 