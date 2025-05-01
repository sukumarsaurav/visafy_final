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
$country_id = isset($_POST['country_id']) ? intval($_POST['country_id']) : 0;
$visa_type_name = isset($_POST['visa_type_name']) ? trim($_POST['visa_type_name']) : '';
$visa_code = isset($_POST['visa_code']) ? trim($_POST['visa_code']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$processing_time = isset($_POST['processing_time']) ? trim($_POST['processing_time']) : '';
$validity_period = isset($_POST['validity_period']) ? trim($_POST['validity_period']) : '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Documents and mandatory status
$documents = isset($_POST['documents']) ? $_POST['documents'] : [];
$mandatory = isset($_POST['mandatory']) ? $_POST['mandatory'] : [];

// Validate inputs
if (empty($visa_type_name)) {
    echo json_encode(['success' => false, 'error' => 'Visa type name is required']);
    exit;
}

if ($country_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid country ID is required']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Verify country exists
    $check_country_stmt = $conn->prepare("SELECT id FROM countries WHERE id = ? AND is_active = 1");
    $check_country_stmt->bind_param("i", $country_id);
    $check_country_stmt->execute();
    $country_result = $check_country_stmt->get_result();
    
    if ($country_result->num_rows == 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Country not found or inactive']);
        exit;
    }
    
    // Check if visa type already exists for this country
    $check_stmt = $conn->prepare("SELECT id FROM visa_types WHERE name = ? AND country_id = ?");
    $check_stmt->bind_param("si", $visa_type_name, $country_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Visa type already exists for this country']);
        exit;
    }
    
    // Generate a code for the visa type if not provided
    if (empty($visa_code)) {
        $visa_code = strtoupper(substr(str_replace(' ', '', $visa_type_name), 0, 5));
    }
    
    // Insert new visa type
    $stmt = $conn->prepare("INSERT INTO visa_types 
                            (country_id, name, code, description, processing_time, validity_period, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssssi", $country_id, $visa_type_name, $visa_code, $description, $processing_time, $validity_period, $is_active);
    
    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to add visa type: ' . $conn->error]);
        exit;
    }
    
    $visa_type_id = $conn->insert_id;
    
    // Add required documents if any
    if (!empty($documents)) {
        $order_display = 1;
        foreach ($documents as $document_id) {
            $document_id = intval($document_id);
            $is_mandatory = isset($mandatory[$document_id]) ? 1 : 0;
            
            $doc_stmt = $conn->prepare("INSERT INTO visa_required_documents 
                                       (visa_type_id, document_type_id, is_mandatory, order_display, created_at) 
                                       VALUES (?, ?, ?, ?, NOW())");
            $doc_stmt->bind_param("iiii", $visa_type_id, $document_id, $is_mandatory, $order_display);
            
            if (!$doc_stmt->execute()) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Failed to add document requirement: ' . $conn->error]);
                exit;
            }
            
            $order_display++;
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Visa type added successfully', 
        'visa_type_id' => $visa_type_id,
        'visa_type_name' => $visa_type_name
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 