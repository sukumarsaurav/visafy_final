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
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get country ID from GET parameter
$country_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate country ID
if ($country_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid country ID']);
    exit;
}

try {
    // Get country details
    $stmt = $conn->prepare("SELECT country_id, country_name, country_code, is_active 
                           FROM countries 
                           WHERE country_id = ?");
    $stmt->bind_param("i", $country_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Country not found']);
        exit;
    }
    
    $country = $result->fetch_assoc();
    echo json_encode(['success' => true, 'country' => $country]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 