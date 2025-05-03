<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: ../../login.php");
    exit;
}

$page_title = "My Bookings";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Get all available visa services
$query = "SELECT vs.visa_service_id, v.visa_type, c.country_name, st.service_name, 
          vs.base_price, vs.description, vs.is_active
          FROM visa_services vs
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          WHERE vs.is_active = 1
          ORDER BY c.country_name, v.visa_type, st.service_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$services_result = $stmt->get_result();
$visa_services = [];

if ($services_result && $services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $visa_services[] = $row;
    }
}
$stmt->close();

// Get all consultation modes
$query = "SELECT consultation_mode_id, mode_name, description 
          FROM consultation_modes 
          WHERE is_active = 1
          ORDER BY mode_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$modes_result = $stmt->get_result();
$consultation_modes = [];

if ($modes_result && $modes_result->num_rows > 0) {
    while ($row = $modes_result->fetch_assoc()) {
        $consultation_modes[] = $row;
    }
}
$stmt->close();

// Get upcoming bookings
$user_id = $_SESSION['id'];
$query = "SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, 
          bs.name as status_name, bs.color as status_color,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name, 
          cm.mode_name as consultation_mode,
          b.meeting_link, b.location, 
          CONCAT(u.first_name, ' ', u.last_name) as consultant_name,
          tm.role as consultant_role
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users u ON tm.user_id = u.id
          WHERE b.user_id = ? AND b.booking_datetime >= NOW() AND b.deleted_at IS NULL
          ORDER BY b.booking_datetime ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$upcoming_result = $stmt->get_result();
$upcoming_bookings = [];

if ($upcoming_result && $upcoming_result->num_rows > 0) {
    while ($row = $upcoming_result->fetch_assoc()) {
        $upcoming_bookings[] = $row;
    }
}
$stmt->close();

// Get past bookings
$query = "SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, 
          bs.name as status_name, bs.color as status_color,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name, 
          cm.mode_name as consultation_mode,
          b.meeting_link, b.location, 
          CONCAT(u.first_name, ' ', u.last_name) as consultant_name,
          tm.role as consultant_role
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users u ON tm.user_id = u.id
          WHERE b.user_id = ? AND b.booking_datetime < NOW() AND b.deleted_at IS NULL
          ORDER BY b.booking_datetime DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$past_result = $stmt->get_result();
$past_bookings = [];

if ($past_result && $past_result->num_rows > 0) {
    while ($row = $past_result->fetch_assoc()) {
        $past_bookings[] = $row;
    }
}
$stmt->close();

// Function to get available time slots
function getAvailableTimeSlots($conn, $service_id, $consultation_mode_id, $selected_date) {
    // Get service duration
    $query = "SELECT duration_minutes FROM service_consultation_modes 
              WHERE visa_service_id = ? AND consultation_mode_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $service_id, $consultation_mode_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [];
    }
    
    $duration = $result->fetch_assoc()['duration_minutes'];
    if (!$duration) {
        $duration = 60; // Default duration of 60 minutes
    }
    $stmt->close();
    
    // Get business hours for the selected day
    $day_of_week = date('w', strtotime($selected_date)); // 0 (Sunday) to 6 (Saturday)
    
    $query = "SELECT is_open, open_time, close_time FROM business_hours WHERE day_of_week = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $day_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || !$result->fetch_assoc()['is_open']) {
        return []; // Business closed on this day
    }
    
    $stmt->execute(); // Re-execute to get the result again
    $hours = $result->fetch_assoc();
    $stmt->close();
    
    // Check if it's a special day (holiday)
    $query = "SELECT is_closed, alternative_open_time, alternative_close_time 
              FROM special_days WHERE date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $special_day = $result->fetch_assoc();
        if ($special_day['is_closed']) {
            return []; // Closed for holiday
        }
        
        // Use alternative hours if specified
        if ($special_day['alternative_open_time'] && $special_day['alternative_close_time']) {
            $hours['open_time'] = $special_day['alternative_open_time'];
            $hours['close_time'] = $special_day['alternative_close_time'];
        }
    }
    $stmt->close();
    
    // Get already booked slots
    $query = "SELECT booking_datetime, end_datetime 
              FROM bookings 
              WHERE DATE(booking_datetime) = ? 
              AND deleted_at IS NULL 
              AND status_id IN (SELECT id FROM booking_statuses WHERE name IN ('pending', 'confirmed'))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = [
            'start' => strtotime($row['booking_datetime']),
            'end' => strtotime($row['end_datetime'])
        ];
    }
    $stmt->close();
    
    // Generate available time slots
    $start_time = strtotime($selected_date . ' ' . $hours['open_time']);
    $end_time = strtotime($selected_date . ' ' . $hours['close_time']);
    $slot_duration = $duration * 60; // Convert minutes to seconds
    
    $available_slots = [];
    
    for ($time = $start_time; $time <= $end_time - $slot_duration; $time += 30 * 60) { // 30-minute intervals
        $slot_end = $time + $slot_duration;
        $is_available = true;
        
        // Check if slot overlaps with any booked slot
        foreach ($booked_slots as $booked) {
            if (($time >= $booked['start'] && $time < $booked['end']) || 
                ($slot_end > $booked['start'] && $slot_end <= $booked['end']) ||
                ($time <= $booked['start'] && $slot_end >= $booked['end'])) {
                $is_available = false;
                break;
            }
        }
        
        // Don't include past times for today
        if (date('Y-m-d') == $selected_date && $time < time()) {
            $is_available = false;
        }
        
        if ($is_available) {
            $available_slots[] = date('H:i', $time);
        }
    }
    
    return $available_slots;
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    $visa_service_id = $_POST['visa_service_id'];
    $consultation_mode_id = $_POST['consultation_mode_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $client_notes = isset($_POST['client_notes']) ? trim($_POST['client_notes']) : '';
    
    // Validate inputs
    $errors = [];
    if (empty($visa_service_id)) {
        $errors[] = "Please select a service";
    }
    if (empty($consultation_mode_id)) {
        $errors[] = "Please select a consultation mode";
    }
    if (empty($booking_date)) {
        $errors[] = "Please select a date";
    }
    if (empty($booking_time)) {
        $errors[] = "Please select a time";
    }
    
    if (empty($errors)) {
        // Get service consultation id
        $query = "SELECT service_consultation_id, additional_fee, duration_minutes FROM service_consultation_modes 
                  WHERE visa_service_id = ? AND consultation_mode_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $visa_service_id, $consultation_mode_id);
        $stmt->execute();
        $service_result = $stmt->get_result();
        
        if ($service_result->num_rows === 0) {
            $errors[] = "Selected service and consultation mode combination is not available";
        } else {
            $service_consultation = $service_result->fetch_assoc();
            $service_consultation_id = $service_consultation['service_consultation_id'];
            $duration_minutes = $service_consultation['duration_minutes'] ?: 60; // Default to 60 if not set
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        // Get pending status ID
        $query = "SELECT id FROM booking_statuses WHERE name = 'pending'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $status_result = $stmt->get_result();
        $status_id = $status_result->fetch_assoc()['id'];
        $stmt->close();
        
        // Create booking datetime
        $booking_datetime = $booking_date . ' ' . $booking_time;
        $end_datetime = date('Y-m-d H:i:s', strtotime($booking_datetime) + ($duration_minutes * 60));
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert booking
            $query = "INSERT INTO bookings (user_id, visa_service_id, service_consultation_id, 
                                         status_id, booking_datetime, end_datetime, duration_minutes, 
                                         client_notes, time_zone) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $timezone = date_default_timezone_get(); // Use server timezone or get from user
            $stmt->bind_param("iiisssisd", $user_id, $visa_service_id, $service_consultation_id, 
                            $status_id, $booking_datetime, $end_datetime, $duration_minutes, 
                            $client_notes, $timezone);
            $stmt->execute();
            $booking_id = $conn->insert_id;
            $stmt->close();
            
            // Log activity
            $query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description, ip_address) 
                      VALUES (?, ?, 'created', 'Booking created by applicant', ?)";
            $stmt = $conn->prepare($query);
            $activity_type = 'created';
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iis", $booking_id, $user_id, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Your booking has been created successfully. We will confirm your appointment shortly.";
            header("Location: bookings.php?success=1");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error creating booking: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle cancel booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    $cancellation_reason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : '';
    
    // Verify booking belongs to user
    $query = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $booking_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        $error_message = "Invalid booking selected";
    } else {
        // Get cancelled status ID
        $query = "SELECT id FROM booking_statuses WHERE name = 'cancelled_by_user'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $status_result = $stmt->get_result();
        $status_id = $status_result->fetch_assoc()['id'];
        $stmt->close();
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update booking status
            $query = "UPDATE bookings SET status_id = ?, cancelled_by = ?, cancellation_reason = ?, cancelled_at = NOW() 
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iisi', $status_id, $user_id, $cancellation_reason, $booking_id);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            $query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description, ip_address) 
                      VALUES (?, ?, 'cancelled', 'Booking cancelled by applicant', ?)";
            $stmt = $conn->prepare($query);
            $activity_type = 'cancelled';
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iis", $booking_id, $user_id, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Your booking has been cancelled.";
            header("Location: bookings.php?success=2");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error cancelling booking: " . $e->getMessage();
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Your booking has been created successfully.";
            break;
        case 2:
            $success_message = "Your booking has been cancelled.";
            break;
        case 3:
            $success_message = "Your feedback has been submitted. Thank you!";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Bookings</h1>
            <p>Schedule and manage your consultations</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="createBookingBtn">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Upcoming Bookings -->
    <div class="section">
        <div class="section-header">
            <h2>Upcoming Appointments</h2>
        </div>
        <div class="booking-list">
            <?php if (empty($upcoming_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>You don't have any upcoming appointments.</p>
                    <button type="button" class="btn-link" id="noBookingsBtn">Schedule a consultation</button>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-date">
                            <div class="date">
                                <span class="month"><?php echo date('M', strtotime($booking['booking_datetime'])); ?></span>
                                <span class="day"><?php echo date('d', strtotime($booking['booking_datetime'])); ?></span>
                            </div>
                            <div class="time">
                                <?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?> - 
                                <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <h3><?php echo htmlspecialchars($booking['service_name'] . ' (' . $booking['visa_type'] . ' - ' . $booking['country_name'] . ')'); ?></h3>
                            <p class="booking-info">
                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($booking['consultation_mode']); ?>
                            </p>
                            <?php if (!empty($booking['consultant_name'])): ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($booking['consultant_name']); ?> 
                                    (<?php echo htmlspecialchars($booking['consultant_role']); ?>)
                                </p>
                            <?php else: ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> Consultant not yet assigned
                                </p>
                            <?php endif; ?>
                            
                            <p class="status">
                                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                    <?php echo ucfirst($booking['status_name']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($booking['status_name'] !== 'cancelled_by_user' && $booking['status_name'] !== 'cancelled_by_admin'): ?>
                                <button type="button" class="btn-action btn-cancel" 
                                        onclick="prepareCancel(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['reference_number']); ?>')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!empty($booking['meeting_link']) && $booking['status_name'] === 'confirmed'): ?>
                                <a href="<?php echo htmlspecialchars($booking['meeting_link']); ?>" target="_blank" class="btn-action btn-primary">
                                    <i class="fas fa-video"></i> Join Meeting
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Past Bookings -->
    <div class="section">
        <div class="section-header">
            <h2>Past Appointments</h2>
            <a href="booking_history.php" class="view-all">View All</a>
        </div>
        <div class="booking-list">
            <?php if (empty($past_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>You don't have any past appointments.</p>
                </div>
            <?php else: ?>
                <?php foreach ($past_bookings as $booking): ?>
                    <div class="booking-card past">
                        <div class="booking-date">
                            <div class="date">
                                <span class="month"><?php echo date('M', strtotime($booking['booking_datetime'])); ?></span>
                                <span class="day"><?php echo date('d', strtotime($booking['booking_datetime'])); ?></span>
                            </div>
                            <div class="time">
                                <?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?> - 
                                <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <h3><?php echo htmlspecialchars($booking['service_name'] . ' (' . $booking['visa_type'] . ' - ' . $booking['country_name'] . ')'); ?></h3>
                            <p class="booking-info">
                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($booking['consultation_mode']); ?>
                            </p>
                            <?php if (!empty($booking['consultant_name'])): ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($booking['consultant_name']); ?> 
                                    (<?php echo htmlspecialchars($booking['consultant_role']); ?>)
                                </p>
                            <?php endif; ?>
                            
                            <p class="status">
                                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                    <?php echo ucfirst($booking['status_name']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($booking['status_name'] === 'completed'): ?>
                                <a href="leave_feedback.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-secondary">
                                    <i class="fas fa-star"></i> Leave Feedback
                                </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-action btn-view" 
                                    onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Booking Modal -->
<div class="modal" id="createBookingModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Book an Appointment</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="createBookingForm">
                    <div class="form-group">
                        <label for="visa_service_id">Service*</label>
                        <select name="visa_service_id" id="visa_service_id" class="form-control" required>
                            <option value="">Select Service</option>
                            <?php foreach ($visa_services as $service): ?>
                                <option value="<?php echo $service['visa_service_id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name'] . ' (' . $service['visa_type'] . ' - ' . $service['country_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_mode_id">Consultation Mode*</label>
                        <select name="consultation_mode_id" id="consultation_mode_id" class="form-control" required disabled>
                            <option value="">Select Service First</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_date">Date*</label>
                        <input type="date" name="booking_date" id="booking_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                               required disabled>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_time">Time*</label>
                        <select name="booking_time" id="booking_time" class="form-control" required disabled>
                            <option value="">Select Date First</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_notes">Additional Notes</label>
                        <textarea name="client_notes" id="client_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="booking-summary" id="booking-summary" style="display: none;">
                        <h4>Booking Summary</h4>
                        <p><strong>Service:</strong> <span id="summary-service"></span></p>
                        <p><strong>Mode:</strong> <span id="summary-mode"></span></p>
                        <p><strong>Date & Time:</strong> <span id="summary-datetime"></span></p>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_booking" class="btn submit-btn">Book Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal" id="cancelBookingModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel your booking <span id="cancel-booking-ref"></span>?</p>
                
                <form action="bookings.php" method="POST" id="cancelBookingForm">
                    <input type="hidden" name="booking_id" id="cancel_booking_id">
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Reason for Cancellation</label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">No, Keep Booking</button>
                        <button type="submit" name="cancel_booking" class="btn submit-btn btn-danger">Yes, Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.section {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
}

.view-all:hover {
    text-decoration: underline;
}

.booking-list {
    padding: 20px;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0 0 15px;
}

.btn-link {
    color: var(--primary-color);
    background: none;
    border: none;
    padding: 0;
    font: inherit;
    cursor: pointer;
    text-decoration: underline;
}

.booking-card {
    display: flex;
    border-radius: 5px;
    border: 1px solid var(--border-color);
    margin-bottom: 15px;
    overflow: hidden;
}

.booking-card:last-child {
    margin-bottom: 0;
}

.booking-card.past {
    opacity: 0.8;
}

.booking-date {
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    text-align: center;
}

.date {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 5px;
}

.month {
    font-size: 14px;
    text-transform: uppercase;
}

.day {
    font-size: 24px;
    font-weight: 700;
}

.time {
    font-size: 12px;
}

.booking-details {
    padding: 15px;
    flex: 1;
}

.booking-details h3 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.booking-details p {
    margin: 5px 0;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.booking-actions {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    background-color: #f8f9fc;
    border-left: 1px solid var(--border-color);
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-size: 13px;
    white-space: nowrap;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
    color: white;
}

.btn-secondary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background-color: var(--light-color);
}

.btn-cancel {
    background-color: white;
    color: var(--danger-color);
    border: 1px solid var(--border-color);
}

.btn-cancel:hover {
    background-color: var(--light-color);
}

.btn-view {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-view:hover {
    background-color: var(--light-color);
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
}

.modal-dialog {
    margin: 80px auto;
    max-width: 500px;
}

.modal-content {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control:disabled {
    background-color: #f0f0f0;
    cursor: not-allowed;
}

.booking-summary {
    margin-top: 20px;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 5px;
    border: 1px solid var(--border-color);
}

.booking-summary h4 {
    margin: 0 0 10px;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.booking-summary p {
    margin: 5px 0;
    font-size: 14px;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn:hover {
    background-color: #031c56;
}

.btn-danger {
    background-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: #d44235;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .booking-card {
        flex-direction: column;
    }
    
    .booking-date {
        flex-direction: row;
        justify-content: space-between;
        min-width: auto;
    }
    
    .date {
        flex-direction: row;
        gap: 5px;
        margin-bottom: 0;
    }
    
    .booking-actions {
        flex-direction: row;
        flex-wrap: wrap;
        border-left: none;
        border-top: 1px solid var(--border-color);
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}
</style>

<script>
// Modal functionality
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        const modal = this.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// Open booking modal
document.getElementById('createBookingBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('createBookingForm').reset();
    
    // Reset and disable form fields in sequence
    document.getElementById('consultation_mode_id').innerHTML = '<option value="">Select Service First</option>';
    document.getElementById('consultation_mode_id').disabled = true;
    
    document.getElementById('booking_date').disabled = true;
    
    document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
    document.getElementById('booking_time').disabled = true;
    
    // Hide booking summary
    document.getElementById('booking-summary').style.display = 'none';
    
    openModal('createBookingModal');
});

// Also add event listener to the empty state button
const noBookingsBtn = document.getElementById('noBookingsBtn');
if (noBookingsBtn) {
    noBookingsBtn.addEventListener('click', function() {
        document.getElementById('createBookingBtn').click();
    });
}

// Prepare cancel booking form
function prepareCancel(bookingId, referenceNumber) {
    document.getElementById('cancel_booking_id').value = bookingId;
    document.getElementById('cancel-booking-ref').textContent = '#' + referenceNumber;
    document.getElementById('cancellation_reason').value = '';
    
    openModal('cancelBookingModal');
}

// Handle service selection
document.getElementById('visa_service_id').addEventListener('change', function() {
    const serviceId = this.value;
    const consultationModeSelect = document.getElementById('consultation_mode_id');
    
    // Reset date and time fields
    document.getElementById('booking_date').disabled = true;
    document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
    document.getElementById('booking_time').disabled = true;
    
    if (serviceId) {
        // Enable and populate consultation modes
        fetchConsultationModes(serviceId, consultationModeSelect);
    } else {
        // Reset and disable consultation mode selection
        consultationModeSelect.innerHTML = '<option value="">Select Service First</option>';
        consultationModeSelect.disabled = true;
    }
});

// Handle consultation mode selection
document.getElementById('consultation_mode_id').addEventListener('change', function() {
    const consultationModeId = this.value;
    const dateInput = document.getElementById('booking_date');
    
    // Reset time field
    document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
    document.getElementById('booking_time').disabled = true;
    
    if (consultationModeId) {
        // Enable date selection
        dateInput.disabled = false;
    } else {
        // Disable date selection
        dateInput.disabled = true;
    }
});

// Handle date selection
document.getElementById('booking_date').addEventListener('change', function() {
    const selectedDate = this.value;
    const serviceId = document.getElementById('visa_service_id').value;
    const consultationModeId = document.getElementById('consultation_mode_id').value;
    const timeSelect = document.getElementById('booking_time');
    
    if (selectedDate && serviceId && consultationModeId) {
        // Fetch available time slots
        fetchAvailableTimeSlots(serviceId, consultationModeId, selectedDate, timeSelect);
    } else {
        // Reset time selection
        timeSelect.innerHTML = '<option value="">Select Date First</option>';
        timeSelect.disabled = true;
    }
});

// Handle time selection
document.getElementById('booking_time').addEventListener('change', function() {
    const selectedTime = this.value;
    const selectedDate = document.getElementById('booking_date').value;
    const serviceSelect = document.getElementById('visa_service_id');
    const modeSelect = document.getElementById('consultation_mode_id');
    
    if (selectedTime) {
        // Show booking summary
        const summaryService = serviceSelect.options[serviceSelect.selectedIndex].text;
        const summaryMode = modeSelect.options[modeSelect.selectedIndex].text;
        const summaryDatetime = formatDate(selectedDate) + ' at ' + formatTime(selectedTime);
        
        document.getElementById('summary-service').textContent = summaryService;
        document.getElementById('summary-mode').textContent = summaryMode;
        document.getElementById('summary-datetime').textContent = summaryDatetime;
        
        document.getElementById('booking-summary').style.display = 'block';
    } else {
        document.getElementById('booking-summary').style.display = 'none';
    }
});

// Function to fetch consultation modes
function fetchConsultationModes(serviceId, selectElement) {
    // Show loading state
    selectElement.innerHTML = '<option value="">Loading...</option>';
    selectElement.disabled = true;
    
    // Make AJAX request to get consultation modes
    fetch(`ajax/get_consultation_modes.php?service_id=${serviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate select options
                selectElement.innerHTML = '<option value="">Select Consultation Mode</option>';
                
                data.modes.forEach(mode => {
                    const option = document.createElement('option');
                    option.value = mode.consultation_mode_id;
                    option.textContent = `${mode.mode_name} (${formatPrice(mode.total_price)})`;
                    selectElement.appendChild(option);
                });
                
                selectElement.disabled = false;
            } else {
                // Show error
                selectElement.innerHTML = '<option value="">Error loading consultation modes</option>';
            }
        })
        .catch(error => {
            console.error('Error fetching consultation modes:', error);
            selectElement.innerHTML = '<option value="">Error: Could not load consultation modes</option>';
        });
}

// Function to fetch available time slots
function fetchAvailableTimeSlots(serviceId, consultationModeId, date, selectElement) {
    // Show loading state
    selectElement.innerHTML = '<option value="">Loading available times...</option>';
    selectElement.disabled = true;
    
    // Make AJAX request to get available slots
    fetch(`ajax/get_available_slots.php?service_id=${serviceId}&consultation_mode_id=${consultationModeId}&date=${date}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.slots.length > 0) {
                    // Populate select options
                    selectElement.innerHTML = '<option value="">Select Time</option>';
                    
                    data.slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot;
                        option.textContent = formatTime(slot);
                        selectElement.appendChild(option);
                    });
                    
                    selectElement.disabled = false;
                } else {
                    // No slots available
                    selectElement.innerHTML = '<option value="">No times available on this date</option>';
                }
            } else {
                // Show error
                selectElement.innerHTML = '<option value="">Error loading time slots</option>';
            }
        })
        .catch(error => {
            console.error('Error fetching time slots:', error);
            selectElement.innerHTML = '<option value="">Error: Could not load time slots</option>';
        });
}

// Helper function to format price
function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}

// Helper function to format date
function formatDate(dateString) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Helper function to format time
function formatTime(timeString) {
    // If it's already in 12-hour format, return as is
    if (timeString.includes('AM') || timeString.includes('PM')) {
        return timeString;
    }
    
    // Otherwise convert from 24-hour format
    const [hours, minutes] = timeString.split(':');
    let hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    hour = hour ? hour : 12; // Convert 0 to 12
    return `${hour}:${minutes} ${ampm}`;
}

// Function to view booking details
function viewBookingDetails(bookingId) {
    // Redirect to booking details page or show details in a modal
    window.location.href = `booking_details.php?id=${bookingId}`;
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 