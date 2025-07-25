<?php
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['visa_id']) || empty($_GET['visa_id'])) {
    echo json_encode([]);
    exit;
}

$visa_id = intval($_GET['visa_id']);

// Get required documents for the selected visa
$query = "SELECT vrd.is_mandatory, dt.name as document_name, dt.document_type 
          FROM visa_required_documents vrd 
          JOIN document_types dt ON vrd.document_type_id = dt.id 
          WHERE vrd.visa_id = ?";
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
