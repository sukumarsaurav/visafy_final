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

// Validate required parameters
if (!isset($_GET['service_id']) || !is_numeric($_GET['service_id']) ||
    !isset($_GET['consultation_mode_id']) || !is_numeric($_GET['consultation_mode_id']) ||
    !isset($_GET['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

$serviceId = intval($_GET['service_id']);
$consultationModeId = intval($_GET['consultation_mode_id']);
$selectedDate = $_GET['date'];

try {
    // Get duration from service_consultation_modes
    $query = "SELECT duration_minutes FROM service_consultation_modes 
              WHERE visa_service_id = ? AND consultation_mode_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $serviceId, $consultationModeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Service and consultation mode combination not found'
        ]);
        exit;
    }
    
    $duration = $result->fetch_assoc()['duration_minutes'];
    if (!$duration) {
        $duration = 60; // Default duration of 60 minutes
    }
    $stmt->close();
    
    // Check if business is open on the selected day
    $dayOfWeek = date('w', strtotime($selectedDate)); // 0 (Sunday) to 6 (Saturday)
    
    $query = "SELECT is_open, open_time, close_time FROM business_hours WHERE day_of_week = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $dayOfWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no business hours are defined or the business is closed on this day
    if ($result->num_rows === 0) {
        // Default business hours if none are defined
        $hours = [
            'is_open' => 1,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00'
        ];
    } else {
        $hours = $result->fetch_assoc();
        
        if (!$hours['is_open']) {
            echo json_encode([
                'success' => true,
                'slots' => [] // Business closed on this day
            ]);
            exit;
        }
    }
    $stmt->close();
    
    // Check if it's a special day (holiday or different hours)
    $query = "SELECT is_closed, alternative_open_time, alternative_close_time 
              FROM special_days WHERE date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $specialDay = $result->fetch_assoc();
        if ($specialDay['is_closed']) {
            echo json_encode([
                'success' => true,
                'slots' => [] // Closed for holiday
            ]);
            exit;
        }
        
        // Use alternative hours if specified
        if ($specialDay['alternative_open_time'] && $specialDay['alternative_close_time']) {
            $hours['open_time'] = $specialDay['alternative_open_time'];
            $hours['close_time'] = $specialDay['alternative_close_time'];
        }
    }
    $stmt->close();
    
    // Check if there are any team members with availability set
    $query = "SELECT COUNT(*) as count FROM team_member_availability";
    $result = $conn->query($query);
    $hasTeamAvailability = ($result && $result->fetch_assoc()['count'] > 0);
    
    // Get already booked slots
    $query = "SELECT booking_datetime, end_datetime 
              FROM bookings 
              WHERE DATE(booking_datetime) = ? 
              AND deleted_at IS NULL 
              AND status_id IN (SELECT id FROM booking_statuses WHERE name IN ('pending', 'confirmed'))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookedSlots = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = [
            'start' => strtotime($row['booking_datetime']),
            'end' => strtotime($row['end_datetime'])
        ];
    }
    $stmt->close();
    
    // Generate available time slots based on business hours
    // In a real-world system, this would be based on team member availability
    $startTime = strtotime($selectedDate . ' ' . $hours['open_time']);
    $endTime = strtotime($selectedDate . ' ' . $hours['close_time']);
    $slotDuration = $duration * 60; // Convert minutes to seconds
    
    $availableSlots = [];
    
    for ($time = $startTime; $time <= $endTime - $slotDuration; $time += 30 * 60) { // 30-minute intervals
        $slotEnd = $time + $slotDuration;
        $isAvailable = true;
        
        // Check if slot overlaps with any booked slot
        foreach ($bookedSlots as $booked) {
            if (($time >= $booked['start'] && $time < $booked['end']) || 
                ($slotEnd > $booked['start'] && $slotEnd <= $booked['end']) ||
                ($time <= $booked['start'] && $slotEnd >= $booked['end'])) {
                $isAvailable = false;
                break;
            }
        }
        
        // Don't include past times for today
        if (date('Y-m-d') == $selectedDate && $time < time()) {
            $isAvailable = false;
        }
        
        if ($isAvailable) {
            $availableSlots[] = date('H:i', $time);
        }
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $availableSlots,
        'using_team_availability' => $hasTeamAvailability
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching available time slots: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching available time slots: ' . $e->getMessage()
    ]);
}
?>
