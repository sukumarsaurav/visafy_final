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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get form data
$document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$document_name = isset($_POST['document_name']) ? trim($_POST['document_name']) : '';
$document_description = isset($_POST['document_description']) ? trim($_POST['document_description']) : '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Validate inputs
if (empty($document_name)) {
    echo json_encode(['success' => false, 'error' => 'Document name is required']);
    exit;
}

if ($category_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid category ID is required']);
    exit;
}

try {
    // Verify category exists
    $check_cat_stmt = $conn->prepare("SELECT id FROM document_categories WHERE id = ?");
    $check_cat_stmt->bind_param("i", $category_id);
    $check_cat_stmt->execute();
    $cat_result = $check_cat_stmt->get_result();
    
    if ($cat_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }
    
    // Check if document name already exists in the same category (for new documents)
    if ($document_id === 0) {
        $check_stmt = $conn->prepare("SELECT id FROM document_types WHERE name = ? AND category_id = ?");
        $check_stmt->bind_param("si", $document_name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'A document type with this name already exists in this category']);
            exit;
        }
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($document_id > 0) {
        // Update existing document
        $stmt = $conn->prepare("UPDATE document_types SET name = ?, description = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssii", $document_name, $document_description, $is_active, $document_id);
    } else {
        // Create new document
        $stmt = $conn->prepare("INSERT INTO document_types (category_id, name, description, is_active, created_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issi", $category_id, $document_name, $document_description, $is_active);
    }
    
    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to save document type: ' . $conn->error]);
        exit;
    }
    
    if ($document_id === 0) {
        $document_id = $conn->insert_id;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document type saved successfully',
        'document_id' => $document_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
