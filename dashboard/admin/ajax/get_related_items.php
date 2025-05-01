<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';


// Check if admin is logged in
if (!is_admin_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get the type parameter
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (empty($type)) {
    echo json_encode(['error' => 'Invalid type']);
    exit();
}

try {
    $items = [];
    
    switch ($type) {
        case 'application':
            // Get visa applications
            $sql = "SELECT va.id, CONCAT('Application #', va.reference_number, ' - ', 
                   (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = va.user_id)) as name
                   FROM visa_applications va
                   WHERE va.deleted_at IS NULL
                   ORDER BY va.created_at DESC 
                   LIMIT 100";
            break;
            
        case 'booking':
            // Get bookings
            $sql = "SELECT b.id, CONCAT('Booking #', b.reference_number, ' - ', 
                   (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = b.user_id)) as name
                   FROM bookings b
                   ORDER BY b.created_at DESC 
                   LIMIT 100";
            break;
            
        case 'task':
            // Get tasks
            $sql = "SELECT t.id, CONCAT('Task: ', t.name) as name
                   FROM tasks t
                   WHERE t.deleted_at IS NULL
                   ORDER BY t.created_at DESC 
                   LIMIT 100";
            break;
            
        case 'general':
            // General topics
            $items = [
                ['id' => 'general_inquiry', 'name' => 'General Inquiry'],
                ['id' => 'feedback', 'name' => 'Feedback'],
                ['id' => 'support', 'name' => 'Technical Support'],
                ['id' => 'billing', 'name' => 'Billing and Payments'],
                ['id' => 'other', 'name' => 'Other']
            ];
            echo json_encode(['success' => true, 'items' => $items]);
            exit();
            
        default:
            echo json_encode(['error' => 'Invalid type']);
            exit();
    }
    
    // Execute SQL query for non-general types
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
        
        echo json_encode(['success' => true, 'items' => $items]);
    } else {
        throw new Exception("Query failed: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log('Error fetching related items: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch related items']);
} 