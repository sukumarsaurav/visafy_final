<?php
require_once '../../../config/db_connect.php';
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debug_log($message) {
    error_log("[get_templates.php] " . $message);
}

debug_log("Script started");

// Check if user is logged in
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    debug_log("Unauthorized access attempt");
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Log user info
debug_log("User ID: " . $_SESSION['id']);

// Validate document_type_id parameter
if (!isset($_GET['document_type_id']) || empty($_GET['document_type_id']) || !is_numeric($_GET['document_type_id'])) {
    debug_log("Invalid or missing document_type_id parameter");
    echo json_encode([]);
    exit;
}

$document_type_id = intval($_GET['document_type_id']);
debug_log("Looking for templates with document_type_id: " . $document_type_id);

try {
    // Fetch active templates for the given document type
    $query = "SELECT id, name 
              FROM document_templates 
              WHERE document_type_id = ? 
              AND is_active = 1 
              ORDER BY name";
    
    debug_log("Preparing query: " . $query);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param('i', $document_type_id);
    
    debug_log("Executing query");
    $success = $stmt->execute();
    if (!$success) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $templates = [];
    
    debug_log("Query executed, found " . $result->num_rows . " templates");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
    }
    
    // Return the array of templates
    echo json_encode($templates);
    debug_log("Response sent with " . count($templates) . " templates");
    
} catch (Exception $e) {
    // Log the error
    $error_message = "Error fetching templates: " . $e->getMessage();
    debug_log($error_message);
    error_log($error_message);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $error_message
    ]);
} finally {
    // Close statement if it exists
    if (isset($stmt)) {
        $stmt->close();
        debug_log("Statement closed");
    }
}

debug_log("Script completed");
?> 