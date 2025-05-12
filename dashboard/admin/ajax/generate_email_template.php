<?php
session_start();
require_once '../../../config/db_connect.php';

// Create a log file specifically for API errors
function logError($message) {
    $logFile = __DIR__ . '/email_template_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['prompt']) || empty($data['prompt'])) {
    echo json_encode(['success' => false, 'error' => 'Missing prompt parameter']);
    exit();
}

$prompt = $data['prompt'];

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
    logError("Looking for .env file at: $envPath");
    
    if (!file_exists($envPath)) {
        logError("Error: .env file not found at: $envPath");
        echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
        exit;
    }
    
    loadEnv($envPath);
    $api_key = getenv('OPENAI_API_KEY');
    
    if (!$api_key) {
        logError("Error: OPENAI_API_KEY not found in environment variables after loading .env");
        echo json_encode(['success' => false, 'error' => 'API key not configured']);
        exit;
    }
}

logError("API Key found (first 5 chars): " . substr($api_key, 0, 5) . "...");

// Fallback to predefined templates if there's an issue with API
function getFallbackTemplate($prompt) {
    $templates = [
        'welcome' => '<!DOCTYPE html><html><head><title>Welcome Email</title></head><body><h1>Welcome, {first_name}!</h1><p>Thank you for joining our platform.</p></body></html>',
        'booking' => '<!DOCTYPE html><html><head><title>Booking Confirmation</title></head><body><h1>Booking Confirmed</h1><p>Dear {first_name}, your booking on {booking_date} has been confirmed.</p></body></html>',
        'document' => '<!DOCTYPE html><html><head><title>Document Request</title></head><body><h1>Document Required</h1><p>Dear {first_name}, please submit your {document_name}.</p></body></html>'
    ];
    
    // Default template
    $selectedTemplate = $templates['welcome'];
    
    // Try to match based on keywords in prompt
    foreach ($templates as $key => $template) {
        if (stripos($prompt, $key) !== false) {
            $selectedTemplate = $template;
            break;
        }
    }
    
    return $selectedTemplate;
}

try {
    // Call OpenAI API with error handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    // Set timeout values to prevent hanging
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);        // 30 seconds total timeout
    
    // Enable verbose error reporting
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Prepare the system message and user prompt
    $system_message = "You are an HTML email template generator. Create professional, responsive email templates with clean HTML and CSS. Include placeholder variables in curly braces like {first_name}, {email}, etc. where appropriate. Make templates visually appealing and optimized for email clients.";
    
    $messages = [
        ["role" => "system", "content" => $system_message],
        ["role" => "user", "content" => $prompt]
    ];
    
    $requestData = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1500
    ];
    
    $requestJson = json_encode($requestData);
    logError("Request data: $requestJson");
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestJson);
    $response = curl_exec($ch);
    
    if ($response === false) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        $error = curl_error($ch);
        logError("cURL Error: $error\nVerbose log: $verboseLog");
        
        // Use fallback template
        $fallbackTemplate = getFallbackTemplate($prompt);
        logError("Using fallback template");
        
        echo json_encode([
            'success' => true,
            'template' => $fallbackTemplate,
            'message' => 'Used fallback template due to API connection issues'
        ]);
        curl_close($ch);
        exit;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logError("API HTTP response code: $http_code");
    
    if ($http_code >= 400) {
        logError("OpenAI API HTTP Error: $http_code - $response");
        
        // Use fallback template
        $fallbackTemplate = getFallbackTemplate($prompt);
        echo json_encode([
            'success' => true,
            'template' => $fallbackTemplate,
            'message' => 'Used fallback template due to API error'
        ]);
        curl_close($ch);
        exit;
    }
    
    $result = json_decode($response, true);
    curl_close($ch);
    
    if (isset($result['error'])) {
        $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
        logError("OpenAI API Error: $errorMsg\nFull response: " . json_encode($result));
        
        // Use fallback template
        $fallbackTemplate = getFallbackTemplate($prompt);
        echo json_encode([
            'success' => true,
            'template' => $fallbackTemplate,
            'message' => 'Used fallback template due to API error: ' . $errorMsg
        ]);
        exit;
    }
    
    if (!isset($result['choices'][0]['message']['content'])) {
        logError("OpenAI API Invalid Response: " . json_encode($result));
        
        // Use fallback template
        $fallbackTemplate = getFallbackTemplate($prompt);
        echo json_encode([
            'success' => true,
            'template' => $fallbackTemplate,
            'message' => 'Used fallback template due to invalid API response'
        ]);
        exit;
    }
    
    // Get the generated template
    $generated_template = $result['choices'][0]['message']['content'];
    
    // Handle case where API might return markdown code blocks instead of pure HTML
    if (strpos($generated_template, '```html') !== false) {
        // Extract HTML from markdown code block
        preg_match('/```html\s*([\s\S]*?)\s*```/', $generated_template, $matches);
        if (isset($matches[1])) {
            $generated_template = $matches[1];
        }
    }
    
    // Log the AI prompt for auditing
    $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, 'ai_template_generation', ?, ?)";
    $stmt = $conn->prepare($logQuery);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iss', $_SESSION['id'], $prompt, $ip);
    $stmt->execute();
    
    // Return the generated template
    echo json_encode([
        'success' => true,
        'template' => $generated_template
    ]);

} catch (Exception $e) {
    logError("Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Use fallback template
    $fallbackTemplate = getFallbackTemplate($prompt);
    echo json_encode([
        'success' => true,
        'template' => $fallbackTemplate,
        'message' => 'Used fallback template due to an unexpected error'
    ]);
}
?> 