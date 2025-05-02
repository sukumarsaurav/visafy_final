<?php
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['visa_id']) || empty($_GET['visa_id'])) {
    echo json_encode([]);
    exit;
}

$visa_id = intval($_GET['visa_id']);

// Get required documents for the selected visa with explicit collation
$query = "SELECT vrd.is_mandatory, vrd.notes, dt.id as document_type_id, dt.name as document_name, dc.name as document_type 
          FROM visa_required_documents vrd 
          JOIN document_types dt ON vrd.document_type_id = dt.id 
          JOIN document_categories dc ON dt.category_id = dc.id
          WHERE vrd.visa_id = ? COLLATE utf8mb4_general_ci";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();
$documents = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

echo json_encode($documents);
