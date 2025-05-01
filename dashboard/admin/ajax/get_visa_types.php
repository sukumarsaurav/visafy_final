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

// Get country ID from GET parameter
$country_id = isset($_GET['country_id']) ? intval($_GET['country_id']) : 0;

// Validate country ID
if ($country_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid country ID is required']);
    exit;
}

try {
    // Get all visa types for the selected country
    $stmt = $conn->prepare("SELECT id, name, code, description, processing_time, validity_period 
                           FROM visa_types 
                           WHERE country_id = ? AND is_active = 1 
                           ORDER BY name");
    $stmt->bind_param("i", $country_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $visa_types = [];
    while ($row = $result->fetch_assoc()) {
        $visa_types[] = $row;
    }
    
    echo json_encode(['success' => true, 'visa_types' => $visa_types]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 