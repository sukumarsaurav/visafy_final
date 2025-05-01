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
$visa_type_id = isset($_POST['visa_type_id']) ? intval($_POST['visa_type_id']) : 0;
$category_id = isset($_POST['category_id']) ? trim($_POST['category_id']) : '';
$document_name = isset($_POST['document_name']) ? trim($_POST['document_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$additional_requirements = isset($_POST['additional_requirements']) ? trim($_POST['additional_requirements']) : '';
$is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

// Check for new category
$new_category_name = '';
if ($category_id === 'new' && isset($_POST['new_category_name'])) {
    $new_category_name = trim($_POST['new_category_name']);
    if (empty($new_category_name)) {
        echo json_encode(['success' => false, 'error' => 'New category name is required']);
        exit;
    }
}

// Validate inputs
if (empty($document_name)) {
    echo json_encode(['success' => false, 'error' => 'Document name is required']);
    exit;
}

if ($visa_type_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid visa type ID is required']);
    exit;
}

if (empty($category_id) && empty($new_category_name)) {
    echo json_encode(['success' => false, 'error' => 'Document category is required']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Verify visa type exists
    $check_visa_stmt = $conn->prepare("SELECT id FROM visa_types WHERE id = ?");
    $check_visa_stmt->bind_param("i", $visa_type_id);
    $check_visa_stmt->execute();
    $visa_result = $check_visa_stmt->get_result();
    
    if ($visa_result->num_rows == 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Visa type not found']);
        exit;
    }
    
    // Handle new category creation if needed
    if ($category_id === 'new' && !empty($new_category_name)) {
        // Check if the category already exists
        $check_cat_stmt = $conn->prepare("SELECT id FROM document_categories WHERE name = ?");
        $check_cat_stmt->bind_param("s", $new_category_name);
        $check_cat_stmt->execute();
        $cat_result = $check_cat_stmt->get_result();
        
        if ($cat_result->num_rows > 0) {
            $category_row = $cat_result->fetch_assoc();
            $category_id = $category_row['id'];
        } else {
            // Create new category
            $cat_stmt = $conn->prepare("INSERT INTO document_categories (name, created_at) VALUES (?, NOW())");
            $cat_stmt->bind_param("s", $new_category_name);
            
            if (!$cat_stmt->execute()) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Failed to create new category: ' . $conn->error]);
                exit;
            }
            
            $category_id = $conn->insert_id;
        }
    }
    
    // Check if document already exists in the same category
    $check_doc_stmt = $conn->prepare("SELECT id FROM document_types WHERE name = ? AND category_id = ?");
    $check_doc_stmt->bind_param("si", $document_name, $category_id);
    $check_doc_stmt->execute();
    $doc_result = $check_doc_stmt->get_result();
    
    if ($doc_result->num_rows > 0) {
        // Document exists, get its ID
        $doc_row = $doc_result->fetch_assoc();
        $document_id = $doc_row['id'];
    } else {
        // Create new document type
        $doc_stmt = $conn->prepare("INSERT INTO document_types 
                                    (category_id, name, description, created_at) 
                                    VALUES (?, ?, ?, NOW())");
        $doc_stmt->bind_param("iss", $category_id, $document_name, $description);
        
        if (!$doc_stmt->execute()) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to create new document type: ' . $conn->error]);
            exit;
        }
        
        $document_id = $conn->insert_id;
    }
    
    // Check if this document is already required for this visa type
    $check_req_stmt = $conn->prepare("SELECT id FROM visa_required_documents 
                                     WHERE visa_type_id = ? AND document_type_id = ?");
    $check_req_stmt->bind_param("ii", $visa_type_id, $document_id);
    $check_req_stmt->execute();
    $req_result = $check_req_stmt->get_result();
    
    if ($req_result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This document is already required for this visa type']);
        exit;
    }
    
    // Get the next display order
    $order_stmt = $conn->prepare("SELECT MAX(order_display) as max_order FROM visa_required_documents 
                                 WHERE visa_type_id = ?");
    $order_stmt->bind_param("i", $visa_type_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_row = $order_result->fetch_assoc();
    $order_display = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 1;
    
    // Add the document requirement
    $req_stmt = $conn->prepare("INSERT INTO visa_required_documents 
                               (visa_type_id, document_type_id, is_mandatory, additional_requirements, 
                                order_display, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
    $req_stmt->bind_param("iiisi", $visa_type_id, $document_id, $is_mandatory, $additional_requirements, $order_display);
    
    if (!$req_stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to add document requirement: ' . $conn->error]);
        exit;
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Document added successfully', 
        'document_id' => $document_id,
        'requirement_id' => $conn->insert_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 