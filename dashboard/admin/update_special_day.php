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

// Handle special day deletion
if (isset($_GET['delete_id'])) {
    $special_day_id = intval($_GET['delete_id']);
    
    try {
        $delete_query = "DELETE FROM special_days WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $special_day_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: bookings.php?tab=special-days&success=8");
        exit;
    } catch (Exception $e) {
        header("Location: bookings.php?tab=special-days&error=" . urlencode("Error deleting special day: " . $e->getMessage()));
        exit;
    }
}

// Handle special day addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_special_day'])) {
    $special_day_id = isset($_POST['special_day_id']) ? trim($_POST['special_day_id']) : '';
    $special_date = $_POST['special_date'];
    $description = trim($_POST['description']);
    $is_closed = isset($_POST['is_closed']) ? 1 : 0;
    $alternative_open_time = isset($_POST['alternative_open_time']) ? $_POST['alternative_open_time'] . ':00' : NULL;
    $alternative_close_time = isset($_POST['alternative_close_time']) ? $_POST['alternative_close_time'] . ':00' : NULL;
    
    // Validate inputs
    $errors = [];
    if (empty($special_date)) {
        $errors[] = "Date is required";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if (!$is_closed && (empty($alternative_open_time) || empty($alternative_close_time))) {
        $errors[] = "Alternative hours are required when office is not closed";
    }
    
    if (empty($errors)) {
        try {
            if (empty($special_day_id)) {
                // Check for existing date
                $check_query = "SELECT id FROM special_days WHERE date = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param('s', $special_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    header("Location: bookings.php?tab=special-days&error=" . urlencode("A special day already exists for this date"));
                    exit;
                }
                
                // Insert new special day
                $insert_query = "INSERT INTO special_days (date, description, is_closed, alternative_open_time, alternative_close_time) 
                                VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param('ssisd', $special_date, $description, $is_closed, $alternative_open_time, $alternative_close_time);
                $stmt->execute();
                $stmt->close();
                
                header("Location: bookings.php?tab=special-days&success=7");
                exit;
            } else {
                // Update existing special day
                $update_query = "UPDATE special_days SET date = ?, description = ?, is_closed = ?, 
                                alternative_open_time = ?, alternative_close_time = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('ssissi', $special_date, $description, $is_closed, 
                                $alternative_open_time, $alternative_close_time, $special_day_id);
                $stmt->execute();
                $stmt->close();
                
                header("Location: bookings.php?tab=special-days&success=9");
                exit;
            }
        } catch (Exception $e) {
            header("Location: bookings.php?tab=special-days&error=" . urlencode("Error saving special day: " . $e->getMessage()));
            exit;
        }
    } else {
        $error_message = implode(", ", $errors);
        header("Location: bookings.php?tab=special-days&error=" . urlencode($error_message));
        exit;
    }
} else {
    header("Location: bookings.php");
    exit;
}

// End output buffering and send content to browser
ob_end_flush();
?> 