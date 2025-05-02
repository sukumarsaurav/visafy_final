<?php
require_once '../../../config/db_connect.php';
header('Content-Type: application/json');

// Get JSON data from request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['visa_id']) || !isset($input['documents']) || !is_array($input['documents'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$visa_id = intval($input['visa_id']);
$documents = $input['documents'];

// Validate visa_id
if ($visa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid visa ID']);
    exit;
}

// Check if visa exists - with explicit collation
$check_visa = "SELECT visa_id FROM visas WHERE visa_id = ? COLLATE utf8mb4_general_ci";
$stmt = $conn->prepare($check_visa);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Visa not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // First, delete all existing required document mappings for this visa
    $delete_query = "DELETE FROM visa_required_documents WHERE visa_id = ? COLLATE utf8mb4_general_ci";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $visa_id);
    $stmt->execute();
    $stmt->close();
    
    // Now insert new required documents
    if (!empty($documents)) {
        $insert_query = "INSERT INTO visa_required_documents (visa_id, document_type_id, is_mandatory, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        foreach ($documents as $doc) {
            $document_id = intval($doc['document_id']);
            $is_mandatory = intval($doc['is_mandatory']);
            $notes = $doc['notes'] ?? null;
            
            $stmt->bind_param('iiis', $visa_id, $document_id, $is_mandatory, $notes);
            $stmt->execute();
        }
        
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Required documents updated successfully']);
    
} catch (Exception $e) {
    // Rollback in case of error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 