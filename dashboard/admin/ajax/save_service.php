<?php
// Fix database connection include path
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debugging: Check if database connection is working
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Debugging: Log received data
$debug_data = json_encode($_POST);
error_log("Save service data: " . $debug_data);

// Get and validate input
$id = isset($_POST['id']) ? intval($_POST['id']) : null;
$visa_type_id = isset($_POST['visa_type_id']) ? intval($_POST['visa_type_id']) : null;
$service_type_id = isset($_POST['service_type_id']) ? intval($_POST['service_type_id']) : null;
$consultation_mode_id = isset($_POST['consultation_mode_id']) ? intval($_POST['consultation_mode_id']) : null;
$price = isset($_POST['price']) ? floatval($_POST['price']) : null;
$is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

// Validate required fields
if (!$visa_type_id || !$service_type_id || !$consultation_mode_id || $price === null) {
    echo json_encode(['success' => false, 'message' => 'All fields are required', 'debug' => [
        'visa_type_id' => $visa_type_id,
        'service_type_id' => $service_type_id,
        'consultation_mode_id' => $consultation_mode_id,
        'price' => $price
    ]]);
    exit;
}

// Debugging: Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'visa_service_configurations'");
if ($table_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Table visa_service_configurations does not exist']);
    exit;
}

// Check for duplicate configuration
$check_query = "
    SELECT id FROM visa_service_configurations 
    WHERE visa_type_id = ? AND service_type_id = ? AND consultation_mode_id = ?
    AND id != IFNULL(?, 0)
";
$stmt = $conn->prepare($check_query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iiii", $visa_type_id, $service_type_id, $consultation_mode_id, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'A configuration with these settings already exists'
    ]);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    if ($id) {
        // Debugging: Log update operation
        error_log("Updating service ID: $id");
        
        // Update existing configuration
        $query = "
            UPDATE visa_service_configurations 
            SET visa_type_id = ?, 
                service_type_id = ?, 
                consultation_mode_id = ?, 
                price = ?, 
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare update statement failed: ' . $conn->error);
        }
        
        $stmt->bind_param("iiidii", 
            $visa_type_id, 
            $service_type_id, 
            $consultation_mode_id, 
            $price, 
            $is_active,
            $id
        );
    } else {
        // Debugging: Log insert operation
        error_log("Inserting new service");
        
        // Insert new configuration
        $query = "
            INSERT INTO visa_service_configurations 
            (visa_type_id, service_type_id, consultation_mode_id, price, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare insert statement failed: ' . $conn->error);
        }
        
        $stmt->bind_param("iiidi", 
            $visa_type_id, 
            $service_type_id, 
            $consultation_mode_id, 
            $price, 
            $is_active
        );
    }

    // Execute the statement
    $execution_result = $stmt->execute();
    if (!$execution_result) {
        throw new Exception('Statement execution failed: ' . $stmt->error);
    }

    // For new records, get the insert ID
    $lastId = $id;
    if (!$id) {
        $lastId = $conn->insert_id;
        error_log("New service ID: $lastId");
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Service configuration saved successfully',
        'id' => $lastId
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in save_service.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save service configuration: ' . $e->getMessage()
    ]);
}
