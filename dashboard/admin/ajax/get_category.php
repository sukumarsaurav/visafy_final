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

// Get category ID
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Validate category ID
if ($category_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid category ID']);
    exit;
}

try {
    // Fetch category
    $stmt = $conn->prepare("SELECT id, name, description FROM document_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }
    
    $category = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'category' => $category]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
