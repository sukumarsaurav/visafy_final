<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Get parameters from request
$team_member_id = isset($_GET['team_member_id']) ? intval($_GET['team_member_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
$duration = isset($_GET['duration']) ? intval($_GET['duration']) : 60;

// Validate parameters
if ($team_member_id <= 0 || empty($date) || !strtotime($date)) {
    echo json_encode([]);
    exit;
}

// Get day of week (0 = Sunday, 1 = Monday, etc.)
$date_obj = new DateTime($date);
$day_of_week = (int)$date_obj->format('w'); // PHP's date format 'w' is already 0 (Sunday) to 6 (Saturday)

$slots = [];

try {
    // 1. Check if it's a special day (holiday or modified hours)
    $special_day_query = "SELECT * FROM special_days WHERE date = ?";
    $stmt = $conn->prepare($special_day_query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $special_day_result = $stmt->get_result();
    $special_day = $special_day_result->fetch_assoc();
    $stmt->close();
    
    $is_business_day = true;
    $open_time = '';
    $close_time = '';
    
    if ($special_day) {
        // It's a special day
        if ($special_day['is_closed']) {
            // Business is closed on this special day
            echo json_encode([]);
            exit;
        } else {
            // Special opening hours
            $open_time = $special_day['alternative_open_time'];
            $close_time = $special_day['alternative_close_time'];
        }
    } else {
        // Regular day - check business hours
        $business_hours_query = "SELECT * FROM business_hours WHERE day_of_week = ?";
        $stmt = $conn->prepare($business_hours_query);
        $stmt->bind_param('i', $day_of_week);
        $stmt->execute();
        $business_hours_result = $stmt->get_result();
        $business_hours = $business_hours_result->fetch_assoc();
        $stmt->close();
        
        if (!$business_hours || !$business_hours['is_open']) {
            // Business is closed on this regular day
            echo json_encode([]);
            exit;
        }
        
        $open_time = $business_hours['open_time'];
        $close_time = $business_hours['close_time'];
    }
    
    // 2. Check team member's availability for this day
    $availability_query = "SELECT * FROM team_member_availability 
                          WHERE team_member_id = ? AND day_of_week = ?";
    $stmt = $conn->prepare($availability_query);
    $stmt->bind_param('ii', $team_member_id, $day_of_week);
    $stmt->execute();
    $availability_result = $stmt->get_result();
    $availability = $availability_result->fetch_assoc();
    $stmt->close();
    
    if (!$availability || !$availability['is_available']) {
        // Team member is not available on this day
        echo json_encode([]);
        exit;
    }
    
    // Use the more restrictive time range between business hours and team member availability
    $start_time = max(strtotime($open_time), strtotime($availability['start_time']));
    $end_time = min(strtotime($close_time), strtotime($availability['end_time']));
    
    // If there's no valid time range, exit
    if ($start_time >= $end_time) {
        echo json_encode([]);
        exit;
    }
    
    // Use team member's slot duration if available
    $slot_duration = $availability['slot_duration_minutes'];
    $buffer_time = $availability['buffer_time_minutes'];
    
    // If requested duration is greater than default slot duration, adjust slot_duration
    if ($duration > $slot_duration) {
        $slot_duration = $duration;
    }
    
    // 3. Get team member's time off for this date
    $time_off_query = "SELECT * FROM team_member_time_off 
                      WHERE team_member_id = ? 
                      AND DATE(start_datetime) <= ? 
                      AND DATE(end_datetime) >= ? 
                      AND status = 'approved'";
    $stmt = $conn->prepare($time_off_query);
    $stmt->bind_param('iss', $team_member_id, $date, $date);
    $stmt->execute();
    $time_off_result = $stmt->get_result();
    $time_off_periods = [];
    
    while ($time_off = $time_off_result->fetch_assoc()) {
        $time_off_periods[] = [
            'start' => strtotime($time_off['start_datetime']),
            'end' => strtotime($time_off['end_datetime'])
        ];
    }
    $stmt->close();
    
    // 4. Get existing bookings for this team member on this date
    $bookings_query = "SELECT b.booking_datetime, b.end_datetime FROM bookings b
                      JOIN booking_statuses bs ON b.status_id = bs.id
                      WHERE b.team_member_id = ? 
                      AND DATE(b.booking_datetime) = ?
                      AND bs.name IN ('pending', 'confirmed')
                      AND b.deleted_at IS NULL";
    $stmt = $conn->prepare($bookings_query);
    $stmt->bind_param('is', $team_member_id, $date);
    $stmt->execute();
    $bookings_result = $stmt->get_result();
    $existing_bookings = [];
    
    while ($booking = $bookings_result->fetch_assoc()) {
        $existing_bookings[] = [
            'start' => strtotime($booking['booking_datetime']),
            'end' => strtotime($booking['end_datetime'])
        ];
    }
    $stmt->close();
    
    // 5. Generate time slots
    $current_time = $start_time;
    $total_slot_time = ($slot_duration + $buffer_time) * 60; // Convert to seconds
    
    while ($current_time + ($slot_duration * 60) <= $end_time) {
        $slot_start = $current_time;
        $slot_end = $slot_start + ($slot_duration * 60);
        
        // Check if this slot overlaps with time off
        $has_time_off_overlap = false;
        foreach ($time_off_periods as $period) {
            if (max($slot_start, $period['start']) < min($slot_end, $period['end'])) {
                $has_time_off_overlap = true;
                break;
            }
        }
        
        // Check if this slot overlaps with existing bookings
        $has_booking_overlap = false;
        foreach ($existing_bookings as $booking) {
            if (max($slot_start, $booking['start']) < min($slot_end, $booking['end'])) {
                $has_booking_overlap = true;
                break;
            }
        }
        
        // Only add available slots
        $slot_time = date('H:i', $slot_start);
        $slots[] = [
            'time' => $slot_time,
            'available' => !$has_time_off_overlap && !$has_booking_overlap
        ];
        
        // Move to next slot start time
        $current_time += $total_slot_time;
    }
    
    echo json_encode($slots);
    
} catch (Exception $e) {
    // Return empty array on error
    echo json_encode([]);
}
?>
