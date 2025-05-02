<?php
require_once '../../../config/db_connect.php';
session_start();
header('Content-Type: application/json');

// Get user_id from session
$user_id = $_SESSION['id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (!$user_id || !$user_type) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// For team members, get their team_member_id
$team_member_id = null;
if ($user_type == 'member') {
    $stmt = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $team_member = $result->fetch_assoc();
    $team_member_id = $team_member['id'] ?? null;
}

// Load environment variables if not already loaded from db_connect.php
function loadEnv($path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load the .env file with explicit error checking if API key not found
$api_key = getenv('OPENAI_API_KEY');
if (!$api_key) {
    $envPath = __DIR__ . '/../../../../config/.env';
    if (!file_exists($envPath)) {
        error_log("Error: .env file not found at: " . $envPath);
        echo json_encode(['success' => false, 'error' => 'Configuration file not found. See @ai-chat.php to understand its configuration carefully']);
        exit;
    }
    
    // Read the entire .env file
    $envContent = file_get_contents($envPath);
    if ($envContent === false) {
        error_log("Error: Could not read .env file at: " . $envPath);
        echo json_encode(['success' => false, 'error' => 'Could not read configuration file']);
        exit;
    }
    
    // Extract OPENAI_API_KEY using regex to handle multi-line value
    if (preg_match('/OPENAI_API_KEY=(.+?)(?=\n[A-Za-z0-9_]+=|\z)/s', $envContent, $matches)) {
        $api_key = trim(str_replace(["\r", "\n"], '', $matches[1]));
    }
    
    if (!$api_key) {
        error_log("Error: OPENAI_API_KEY not found in environment variables");
        echo json_encode(['success' => false, 'error' => 'API key not configured. See @ai-chat.php to understand its configuration carefully']);
        exit;
    }
}

// Check if it's a POST request and decode JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_str = file_get_contents('php://input');
    $data = json_decode($json_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }
    
    $document_type = $data['document_type'] ?? '';
    $user_prompt = $data['prompt'] ?? '';
    
    if (empty($document_type) || empty($user_prompt)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    // Create a conversation record for the AI chat
    $conn->begin_transaction();
    
    try {
        // Create a conversation
        $chat_title = "Template Generation: " . $document_type;
        $sql = "INSERT INTO ai_chat_conversations (user_id, title, chat_type) VALUES (?, ?, 'templates')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $chat_title);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create conversation: ' . $conn->error);
        }
        
        $conversation_id = $conn->insert_id;
        
        // Add system message
        $system_message = "You are a visa document template generator assistant. You excel at creating professional, 
                          well-structured document templates for immigration and visa purposes. You follow instructions 
                          carefully and create templates with appropriate placeholders for dynamic content.";
        
        $sql = "INSERT INTO ai_chat_messages (conversation_id, user_id, role, content) VALUES (?, ?, 'system', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $conversation_id, $user_id, $system_message);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save system message: ' . $conn->error);
        }
        
        // Add user message (the prompt)
        $sql = "INSERT INTO ai_chat_messages (conversation_id, user_id, role, content) VALUES (?, ?, 'user', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $conversation_id, $user_id, $user_prompt);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save user message: ' . $conn->error);
        }
        
        // Call OpenAI API with error handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        
        // Enable verbose error reporting
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        // Prepare messages array
        $messages = [
            ["role" => "system", "content" => $system_message],
            ["role" => "user", "content" => $user_prompt]
        ];
        
        $api_data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1500  // Allow more tokens for template generation
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
        $response = curl_exec($ch);
        
        if ($response === false) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log("cURL Error: " . curl_error($ch) . "\n" . $verboseLog);
            throw new Exception('Could not connect to the OpenAI API: ' . curl_error($ch));
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code >= 400) {
            error_log("OpenAI API HTTP Error: " . $http_code . " - " . $response);
            throw new Exception('API returned error code: ' . $http_code);
        }
        
        $result = json_decode($response, true);
        curl_close($ch);
        
        if (isset($result['error'])) {
            error_log("OpenAI API Error: " . json_encode($result['error']));
            throw new Exception($result['error']['message'] ?? 'An unknown error occurred');
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            error_log("OpenAI API Invalid Response: " . json_encode($result));
            throw new Exception('Invalid response format from AI service');
        }
        
        $ai_response = $result['choices'][0]['message']['content'];
        
        // Save AI response to the conversation
        $sql = "INSERT INTO ai_chat_messages (conversation_id, user_id, role, content) VALUES (?, ?, 'assistant', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $conversation_id, $user_id, $ai_response);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save AI response: ' . $conn->error);
        }
        
        // Update usage for team members
        if ($user_type == 'member' && $team_member_id) {
            // Calculate tokens for usage tracking
            $prompt_tokens = isset($result['usage']['prompt_tokens']) ? $result['usage']['prompt_tokens'] : 0;
            $completion_tokens = isset($result['usage']['completion_tokens']) ? $result['usage']['completion_tokens'] : 0;
            $total_tokens = $prompt_tokens + $completion_tokens;
            
            $month = date('Y-m');
            $sql = "INSERT INTO ai_chat_usage (team_member_id, month, message_count, token_count) 
                    VALUES (?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE message_count = message_count + 1, token_count = token_count + ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isii", $team_member_id, $month, $total_tokens, $total_tokens);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update usage: ' . $conn->error);
            }
        }
        
        $conn->commit();
        
        // Return the AI-generated template content
        echo json_encode(['success' => true, 'content' => $ai_response]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in AI template generation: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?> 