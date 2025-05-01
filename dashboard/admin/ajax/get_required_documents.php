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

// Get visa type ID from GET parameter
$visa_type_id = isset($_GET['visa_type_id']) ? intval($_GET['visa_type_id']) : 0;

// Validate visa type ID
if ($visa_type_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid visa type ID is required']);
    exit;
}

try {
    // Get all required documents for the selected visa type
    $stmt = $conn->prepare("SELECT vrd.id, dt.name, vrd.is_mandatory, vrd.additional_requirements 
                           FROM visa_required_documents vrd
                           JOIN document_types dt ON vrd.document_type_id = dt.id
                           WHERE vrd.visa_type_id = ? 
                           ORDER BY vrd.order_display, dt.name");
    $stmt->bind_param("i", $visa_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    echo json_encode(['success' => true, 'documents' => $documents]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 