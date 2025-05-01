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

// Get visa type ID
$visa_type_id = isset($_GET['visa_type_id']) ? intval($_GET['visa_type_id']) : 0;

// Validate visa type ID
if ($visa_type_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid visa type ID is required']);
    exit;
}

try {
    // Verify visa type exists
    $check_visa_stmt = $conn->prepare("SELECT id, name FROM visa_types WHERE id = ?");
    $check_visa_stmt->bind_param("i", $visa_type_id);
    $check_visa_stmt->execute();
    $visa_result = $check_visa_stmt->get_result();
    
    if ($visa_result->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Visa type not found']);
        exit;
    }
    
    $visa_data = $visa_result->fetch_assoc();
    
    // Get document categories
    $categories_query = "SELECT DISTINCT c.id, c.name
                         FROM document_categories c
                         JOIN document_types d ON c.id = d.category_id
                         JOIN visa_required_documents r ON d.id = r.document_type_id
                         WHERE r.visa_type_id = ?
                         ORDER BY c.name";
    
    $categories_stmt = $conn->prepare($categories_query);
    $categories_stmt->bind_param("i", $visa_type_id);
    $categories_stmt->execute();
    $categories_result = $categories_stmt->get_result();
    
    $categories = [];
    while ($category = $categories_result->fetch_assoc()) {
        $category_id = $category['id'];
        
        // Get documents for this category
        $docs_query = "SELECT r.id as requirement_id, d.id as document_id, d.name, r.is_mandatory, 
                       r.additional_requirements, r.order_display
                       FROM visa_required_documents r
                       JOIN document_types d ON r.document_type_id = d.id
                       WHERE r.visa_type_id = ? AND d.category_id = ?
                       ORDER BY r.order_display";
        
        $docs_stmt = $conn->prepare($docs_query);
        $docs_stmt->bind_param("ii", $visa_type_id, $category_id);
        $docs_stmt->execute();
        $docs_result = $docs_stmt->get_result();
        
        $documents = [];
        while ($doc = $docs_result->fetch_assoc()) {
            $documents[] = [
                'requirement_id' => $doc['requirement_id'],
                'document_id' => $doc['document_id'],
                'name' => $doc['name'],
                'is_mandatory' => (bool)$doc['is_mandatory'],
                'additional_requirements' => $doc['additional_requirements'],
                'order_display' => $doc['order_display']
            ];
        }
        
        $categories[] = [
            'id' => $category_id,
            'name' => $category['name'],
            'documents' => $documents
        ];
    }
    
    // Get all available categories for the dropdown
    $all_categories_query = "SELECT id, name FROM document_categories ORDER BY name";
    $all_categories_stmt = $conn->prepare($all_categories_query);
    $all_categories_stmt->execute();
    $all_categories_result = $all_categories_stmt->get_result();
    
    $all_categories = [];
    while ($cat = $all_categories_result->fetch_assoc()) {
        $all_categories[] = [
            'id' => $cat['id'],
            'name' => $cat['name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'visa_type' => [
            'id' => $visa_data['id'],
            'name' => $visa_data['name']
        ],
        'categories' => $categories,
        'all_categories' => $all_categories
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 