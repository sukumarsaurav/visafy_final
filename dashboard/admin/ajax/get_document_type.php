<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get document ID
$document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;

// Validate document ID
if ($document_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
    exit;
}

try {
    // Fetch document
    $stmt = $conn->prepare("SELECT id, category_id, name, description, is_active 
                          FROM document_types WHERE id = ?");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Document type not found']);
        exit;
    }
    
    $document = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'document' => $document]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
