<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

// Check if admin is logged in
if (!is_admin_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get current admin ID
$current_admin_id = $_SESSION['id'];

try {
    // Prepare users query - filter by active users, exclude current admin
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.user_type, 
            CASE 
                WHEN u.user_type = 'applicant' THEN 'Applicant'
                WHEN u.user_type = 'admin' THEN 'Admin'
                WHEN u.user_type = 'member' THEN 'Team Member'
            END as role_label
            FROM users u
            WHERE u.status = 'active' 
            AND u.deleted_at IS NULL
            AND u.id != ?
            ORDER BY 
                CASE WHEN u.user_type = 'admin' THEN 1
                     WHEN u.user_type = 'member' THEN 2
                     ELSE 3 END,
                u.first_name, u.last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email'],
            'user_type' => $row['user_type'],
            'role_label' => $row['role_label']
        ];
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
    
} catch (Exception $e) {
    error_log('Error fetching users: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch users']);
}