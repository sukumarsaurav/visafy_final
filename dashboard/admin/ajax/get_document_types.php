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

// Get all document types grouped by categories with explicit collation
$query = "SELECT dt.id, dt.name, dt.description, dc.name as category_name
          FROM document_types dt
          JOIN document_categories dc ON dt.category_id = dc.id COLLATE utf8mb4_general_ci
          WHERE dt.is_active = 1
          ORDER BY dc.name, dt.name";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Group results by category
$document_types = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $category = $row['category_name'];
        
        if (!isset($document_types[$category])) {
            $document_types[$category] = [];
        }
        
        $document_types[$category][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description']
        ];
    }
}
$stmt->close();

echo json_encode($document_types);
?>
