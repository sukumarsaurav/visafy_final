<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

require_once '../../config/db_connect.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business_hours'])) {
    $is_open_array = isset($_POST['is_open']) ? $_POST['is_open'] : [];
    $open_time_array = isset($_POST['open_time']) ? $_POST['open_time'] : [];
    $close_time_array = isset($_POST['close_time']) ? $_POST['close_time'] : [];
    
    $days = [0, 1, 2, 3, 4, 5, 6]; // 0 = Sunday, 1 = Monday, etc.
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        foreach ($days as $day) {
            $is_open = isset($is_open_array[$day]) ? 1 : 0;
            $open_time = isset($open_time_array[$day]) ? $open_time_array[$day] . ':00' : '00:00:00';
            $close_time = isset($close_time_array[$day]) ? $close_time_array[$day] . ':00' : '00:00:00';
            
            // If day is marked as closed, set times to 00:00:00
            if (!$is_open) {
                $open_time = '00:00:00';
                $close_time = '00:00:00';
            }
            
            // Check if record exists
            $check_query = "SELECT id FROM business_hours WHERE day_of_week = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('i', $day);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $update_query = "UPDATE business_hours SET is_open = ?, open_time = ?, close_time = ? WHERE day_of_week = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('issi', $is_open, $open_time, $close_time, $day);
                $stmt->execute();
            } else {
                // Insert new record
                $insert_query = "INSERT INTO business_hours (day_of_week, is_open, open_time, close_time) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param('iiss', $day, $is_open, $open_time, $close_time);
                $stmt->execute();
            }
            
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect back with success message
        header("Location: bookings.php?tab=business-hours&success=6");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        header("Location: bookings.php?tab=business-hours&error=" . urlencode("Error updating business hours: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: bookings.php");
    exit;
}

// End output buffering and send content to browser
ob_end_flush();
?> 