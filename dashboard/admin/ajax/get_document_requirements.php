<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 to prevent HTML error messages in JSON response

// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');

// Set character set to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
}

try {
    // Check if user is logged in as admin
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Check if visa_id is provided
    if (!isset($_GET['visa_id']) || empty($_GET['visa_id'])) {
        echo json_encode([]);
        exit;
    }

    $visa_id = intval($_GET['visa_id']);

    // Get document requirements for the selected visa
    $query = "SELECT dt.id, dt.name, dt.description, vrd.is_mandatory, dc.name as category_name 
            FROM visa_required_documents vrd
            JOIN document_types dt ON vrd.document_type_id = dt.id
            JOIN document_categories dc ON dt.category_id = dc.id
            WHERE vrd.visa_id = ? AND dt.is_active = 1
            ORDER BY dc.name, vrd.is_mandatory DESC, dt.name ASC";

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

    // If no documents are found for this visa, let's get some default ones
    if (empty($documents)) {
        // Get common document types
        $default_query = "SELECT dt.id, dt.name, dt.description, 1 as is_mandatory, dc.name as category_name
                        FROM document_types dt
                        JOIN document_categories dc ON dt.category_id = dc.id
                        WHERE dt.is_active = 1
                        AND (
                            dt.name IN ('Passport', 'National ID Card', 'Photographs') OR
                            dc.name IN ('Identity', 'Supporting')
                        )
                        ORDER BY dc.name, dt.name";
        
        $default_stmt = $conn->prepare($default_query);
        $default_stmt->execute();
        $default_result = $default_stmt->get_result();
        
        if ($default_result && $default_result->num_rows > 0) {
            while ($row = $default_result->fetch_assoc()) {
                $documents[] = $row;
            }
        }
        
        $default_stmt->close();
    }

    $stmt->close();

    // Return documents as JSON
    echo json_encode($documents);
    
} catch (Exception $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
