<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Ensure user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// Validate booking ID
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Get booking details
$booking_query = "
    SELECT b.*, 
           u.email as user_email,
           u.phone as user_phone,
           CONCAT(u.first_name, ' ', u.last_name) as client_name,
           vt.name as visa_type,
           st.name as service_type,
           cm.name as consultation_mode,
           CONCAT(tm_u.first_name, ' ', tm_u.last_name) as team_member_name,
           tm.role as team_member_role
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    LEFT JOIN team_members tm ON b.team_member_id = tm.id
    LEFT JOIN users tm_u ON tm.user_id = tm_u.id
    WHERE b.id = ?
";

$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

$booking = $result->fetch_assoc();

// Format the response
echo json_encode([
    'success' => true,
    'booking' => $booking
]);
?> 