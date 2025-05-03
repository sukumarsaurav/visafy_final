<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'applicant') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Validate inputs
if (!isset($_GET['service_id']) || !is_numeric($_GET['service_id']) ||
    !isset($_GET['consultation_mode_id']) || !is_numeric($_GET['consultation_mode_id']) ||
    !isset($_GET['date']) || !validateDate($_GET['date'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

$serviceId = intval($_GET['service_id']);
$consultationModeId = intval($_GET['consultation_mode_id']);
$bookingDate = $_GET['date'];

// Check if date is in the future
$currentDate = date('Y-m-d');
if ($bookingDate <= $currentDate) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a future date'
    ]);
    exit;
}

// Get day of week (0 = Sunday, 6 = Saturday)
$dayOfWeek = date('w', strtotime($bookingDate));

try {
    // Get business hours for the day
    $query = "SELECT opening_time as open_time, closing_time as close_time, is_closed as is_open 
              FROM business_hours 
              WHERE day_of_week = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $dayOfWeek);
    $stmt->execute();
    $businessHoursResult = $stmt->get_result();
    
    if ($businessHoursResult->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'slots' => []
        ]);
        exit;
    }
    
    $businessHours = $businessHoursResult->fetch_assoc();
    
    // Check if business is closed for the day (is_open = 0 means closed)
    if (!$businessHours['is_open']) {
        echo json_encode([
            'success' => true,
            'slots' => []
        ]);
        exit;
    }
    
    // Check for holiday
    $holidayQuery = "SELECT id FROM special_days WHERE date = ? AND is_closed = 1";
    $holidayStmt = $conn->prepare($holidayQuery);
    $holidayStmt->bind_param("s", $bookingDate);
    $holidayStmt->execute();
    $holidayResult = $holidayStmt->get_result();
    
    if ($holidayResult->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'slots' => []
        ]);
        exit;
    }
    
    // Get consultation mode duration
    $durationQuery = "SELECT duration_minutes FROM consultation_modes WHERE id = ?";
    $durationStmt = $conn->prepare($durationQuery);
    $durationStmt->bind_param("i", $consultationModeId);
    $durationStmt->execute();
    $durationResult = $durationStmt->get_result();
    
    if ($durationResult->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid consultation mode'
        ]);
        exit;
    }
    
    $consultationMode = $durationResult->fetch_assoc();
    $slotDuration = $consultationMode['duration_minutes'];
    
    // Get existing bookings for the day
    $bookingsQuery = "SELECT booking_time, 
                      ADDTIME(booking_time, SEC_TO_TIME(cm.duration_minutes * 60)) as end_time
                      FROM bookings b
                      JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
                      WHERE b.booking_date = ? 
                      AND b.status NOT IN ('cancelled', 'rejected')";
    $bookingsStmt = $conn->prepare($bookingsQuery);
    $bookingsStmt->bind_param("s", $bookingDate);
    $bookingsStmt->execute();
    $bookingsResult = $bookingsStmt->get_result();
    
    $existingBookings = [];
    while ($booking = $bookingsResult->fetch_assoc()) {
        $existingBookings[] = [
            'start' => $booking['booking_time'],
            'end' => $booking['end_time']
        ];
    }
    
    // Generate available time slots
    $openingTime = strtotime($businessHours['open_time']);
    $closingTime = strtotime($businessHours['close_time']);
    $slotDurationSeconds = $slotDuration * 60;
    
    $availableSlots = [];
    
    // Generate slots at 30-minute intervals
    $slotInterval = 30 * 60; // 30 minutes in seconds
    for ($time = $openingTime; $time < $closingTime - $slotDurationSeconds; $time += $slotInterval) {
        $startTime = date('H:i:s', $time);
        $endTime = date('H:i:s', $time + $slotDurationSeconds);
        
        // Check if this slot overlaps with any existing bookings
        $isAvailable = true;
        foreach ($existingBookings as $booking) {
            if (
                ($startTime >= $booking['start'] && $startTime < $booking['end']) ||  // Start time falls within existing booking
                ($endTime > $booking['start'] && $endTime <= $booking['end']) ||     // End time falls within existing booking
                ($startTime <= $booking['start'] && $endTime >= $booking['end'])     // Slot encompasses existing booking
            ) {
                $isAvailable = false;
                break;
            }
        }
        
        if ($isAvailable) {
            $availableSlots[] = $startTime;
        }
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $availableSlots
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching available time slots: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching available time slots'
    ]);
}

// Helper function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
