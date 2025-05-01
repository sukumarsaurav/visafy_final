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
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
$category_description = isset($_POST['category_description']) ? trim($_POST['category_description']) : '';

// Validate category name
if (empty($category_name)) {
    echo json_encode(['success' => false, 'error' => 'Category name is required']);
    exit;
}

try {
    // Check if category name already exists (for new categories)
    if ($category_id === 0) {
        $check_stmt = $conn->prepare("SELECT id FROM document_categories WHERE name = ?");
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'A category with this name already exists']);
            exit;
        }
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($category_id > 0) {
        // Update existing category
        $stmt = $conn->prepare("UPDATE document_categories SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $category_name, $category_description, $category_id);
    } else {
        // Create new category
        $stmt = $conn->prepare("INSERT INTO document_categories (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $category_name, $category_description);
    }
    
    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to save category: ' . $conn->error]);
        exit;
    }
    
    if ($category_id === 0) {
        $category_id = $conn->insert_id;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Category saved successfully',
        'category_id' => $category_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
