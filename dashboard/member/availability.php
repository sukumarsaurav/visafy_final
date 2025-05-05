<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = "Manage Availability";
$page_specific_css = "../assets/css/availability.css";

// Include header
include_once 'includes/header.php';

// Process form submission for regular availability
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_regular_availability'])) {
    // First, get team member ID from the database
    $get_team_id = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
    $get_team_id->bind_param("i", $user_id);
    $get_team_id->execute();
    $result = $get_team_id->get_result();
    $team_member_data = $result->fetch_assoc();
    $team_member_id = $team_member_data['id'];
    $get_team_id->close();

    // Delete existing availability records for this team member
    $stmt = $conn->prepare("DELETE FROM team_member_availability WHERE team_member_id = ?");
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $stmt->close();
    
    // Insert new availability records
    for ($i = 0; $i < 7; $i++) {
        if (isset($_POST['is_available'][$i]) && $_POST['is_available'][$i] == 1) {
            $stmt = $conn->prepare("INSERT INTO team_member_availability 
                (team_member_id, day_of_week, is_available, start_time, end_time, slot_duration_minutes, buffer_time_minutes) 
                VALUES (?, ?, 1, ?, ?, ?, ?)");
                
            $start_time = $_POST['start_time'][$i];
            $end_time = $_POST['end_time'][$i];
            $slot_duration = $_POST['slot_duration'][$i];
            $buffer_time = $_POST['buffer_time'][$i];
            
            $stmt->bind_param("iissii", $team_member_id, $i, $start_time, $end_time, $slot_duration, $buffer_time);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert as not available
            $stmt = $conn->prepare("INSERT INTO team_member_availability 
                (team_member_id, day_of_week, is_available, start_time, end_time, slot_duration_minutes, buffer_time_minutes) 
                VALUES (?, ?, 0, '00:00:00', '00:00:00', 0, 0)");
                
            $stmt->bind_param("ii", $team_member_id, $i);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Redirect to avoid form resubmission
    $_SESSION['success_message'] = "Your regular availability has been updated successfully.";
    header("Location: availability.php");
    exit();
}

// Process time off request form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_time_off'])) {
    // Get team member ID from the database
    $get_team_id = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
    $get_team_id->bind_param("i", $user_id);
    $get_team_id->execute();
    $result = $get_team_id->get_result();
    $team_member_data = $result->fetch_assoc();
    $team_member_id = $team_member_data['id'];
    $get_team_id->close();
    
    $start_datetime = $_POST['start_date'] . ' ' . $_POST['start_time'] . ':00';
    $end_datetime = $_POST['end_date'] . ' ' . $_POST['end_time'] . ':00';
    $reason = $_POST['reason'];
    
    $stmt = $conn->prepare("INSERT INTO team_member_time_off 
        (team_member_id, start_datetime, end_datetime, reason, status) 
        VALUES (?, ?, ?, ?, 'pending')");
        
    $stmt->bind_param("isss", $team_member_id, $start_datetime, $end_datetime, $reason);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Your time off request has been submitted and is pending approval.";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    
    $stmt->close();
    
    // Redirect to avoid form resubmission
    header("Location: availability.php");
    exit();
}

// Get team member ID
$stmt = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$team_member_data = $result->fetch_assoc();
$team_member_id = $team_member_data['id'];
$stmt->close();

// Get the team member's current availability
$availability = [];
$stmt = $conn->prepare("SELECT * FROM team_member_availability WHERE team_member_id = ? ORDER BY day_of_week");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $availability[$row['day_of_week']] = $row;
}
$stmt->close();

// Initialize default availability if not set
$days_of_week = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

foreach ($days_of_week as $key => $day) {
    if (!isset($availability[$key])) {
        $availability[$key] = [
            'day_of_week' => $key,
            'is_available' => 0,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration_minutes' => 60,
            'buffer_time_minutes' => 0
        ];
    }
}

// Get booking statistics for this team member
// 1. Total upcoming bookings
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings 
                       WHERE team_member_id = ? 
                       AND booking_datetime >= NOW() 
                       AND deleted_at IS NULL");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_upcoming_bookings = $row['total'];
$stmt->close();

// 2. Today's bookings
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$stmt = $conn->prepare("SELECT COUNT(*) as today FROM bookings 
                       WHERE team_member_id = ? 
                       AND booking_datetime BETWEEN ? AND ? 
                       AND deleted_at IS NULL");
$stmt->bind_param("iss", $team_member_id, $today_start, $today_end);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$today_bookings = $row['today'];
$stmt->close();

// 3. This week's bookings
$week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
$week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COUNT(*) as week FROM bookings 
                       WHERE team_member_id = ? 
                       AND booking_datetime BETWEEN ? AND ? 
                       AND deleted_at IS NULL");
$stmt->bind_param("iss", $team_member_id, $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$week_bookings = $row['week'];
$stmt->close();

// 4. Completed bookings
$stmt = $conn->prepare("SELECT COUNT(*) as completed FROM bookings b
                       JOIN booking_statuses bs ON b.status_id = bs.id
                       WHERE b.team_member_id = ? 
                       AND bs.name = 'completed'
                       AND b.deleted_at IS NULL");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$completed_bookings = $row['completed'];
$stmt->close();

// Get pending and approved time off requests
$stmt = $conn->prepare("SELECT * FROM team_member_time_off 
                       WHERE team_member_id = ? 
                       AND (status = 'pending' OR status = 'approved')
                       AND end_datetime >= NOW()
                       ORDER BY start_datetime");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$time_off_requests = $stmt->get_result();
$stmt->close();

// Get past time off requests (for history)
$stmt = $conn->prepare("SELECT * FROM team_member_time_off 
                       WHERE team_member_id = ? 
                       AND (end_datetime < NOW() OR status = 'rejected')
                       ORDER BY start_datetime DESC LIMIT 10");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$past_time_off = $stmt->get_result();
$stmt->close();

// Get upcoming bookings for this team member
$stmt = $conn->prepare("SELECT b.*, bs.name as status_name, bs.color as status_color,
                      CONCAT(u.first_name, ' ', u.last_name) as client_name,
                      v.visa_type, st.service_name, cm.mode_name
                      FROM bookings b
                      JOIN booking_statuses bs ON b.status_id = bs.id
                      JOIN users u ON b.user_id = u.id
                      JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                      JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
                      JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                      JOIN visas v ON vs.visa_id = v.visa_id
                      JOIN service_types st ON vs.service_type_id = st.service_type_id
                      WHERE b.team_member_id = ?
                      AND b.booking_datetime >= NOW() 
                      AND bs.name IN ('pending', 'confirmed')
                      AND b.deleted_at IS NULL
                      ORDER BY b.booking_datetime
                      LIMIT 10");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$upcoming_bookings = $stmt->get_result();
$stmt->close();

// Get next booking details
$stmt = $conn->prepare("SELECT b.*, bs.name as status_name,
                      CONCAT(u.first_name, ' ', u.last_name) as client_name,
                      st.service_name, cm.mode_name
                      FROM bookings b
                      JOIN booking_statuses bs ON b.status_id = bs.id
                      JOIN users u ON b.user_id = u.id
                      JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                      JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
                      JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                      JOIN service_types st ON vs.service_type_id = st.service_type_id
                      WHERE b.team_member_id = ?
                      AND b.booking_datetime >= NOW() 
                      AND bs.name IN ('pending', 'confirmed')
                      AND b.deleted_at IS NULL
                      ORDER BY b.booking_datetime
                      LIMIT 1");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$next_booking_result = $stmt->get_result();
$next_booking = $next_booking_result->num_rows > 0 ? $next_booking_result->fetch_assoc() : null;
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Manage Availability</h1>
            <p>Set your working hours and request time off. This helps clients know when you're available for appointments.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
            <span class="close-alert"><i class="fas fa-times"></i></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
            <span class="close-alert"><i class="fas fa-times"></i></span>
        </div>
    <?php endif; ?>

    <!-- Booking Stats Cards -->
    <div class="stats-container">
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stats-info">
                <h3>Today's Bookings</h3>
                <p class="stats-value"><?php echo $today_bookings; ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stats-info">
                <h3>This Week</h3>
                <p class="stats-value"><?php echo $week_bookings; ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stats-info">
                <h3>Upcoming</h3>
                <p class="stats-value"><?php echo $total_upcoming_bookings; ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-info">
                <h3>Completed</h3>
                <p class="stats-value"><?php echo $completed_bookings; ?></p>
            </div>
        </div>

        <?php if ($next_booking): ?>
        <div class="stats-card next-booking">
            <div class="stats-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-info">
                <h3>Next Appointment</h3>
                <p class="next-booking-details">
                    <?php 
                    $booking_date = date('M d, g:i A', strtotime($next_booking['booking_datetime']));
                    echo $booking_date . ' • ' . htmlspecialchars($next_booking['client_name']);
                    ?>
                </p>
                <p class="next-booking-service">
                    <?php echo htmlspecialchars($next_booking['service_name'] . ' (' . $next_booking['mode_name'] . ')'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="availability-container">
        <div class="availability-column main-column">
            <div class="card">
                <div class="card-header">
                    <h2>Regular Weekly Availability</h2>
                </div>
                <div class="card-body">
                    <p>Set your regular working hours for each day of the week. This helps clients know when you're available for appointments.</p>
                    
                    <form action="" method="post">
                        <div class="table-responsive">
                            <table class="availability-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Available</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Slot Duration</th>
                                        <th>Buffer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days_of_week as $day_number => $day_name): ?>
                                        <tr>
                                            <td><?php echo $day_name; ?></td>
                                            <td>
                                                <div class="toggle-switch">
                                                    <input type="checkbox" class="availability-toggle" 
                                                        name="is_available[<?php echo $day_number; ?>]" 
                                                        value="1" 
                                                        id="available_<?php echo $day_number; ?>"
                                                        <?php echo ($availability[$day_number]['is_available'] == 1) ? 'checked' : ''; ?>>
                                                    <label for="available_<?php echo $day_number; ?>"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="time" class="time-input" 
                                                    name="start_time[<?php echo $day_number; ?>]" 
                                                    value="<?php echo substr($availability[$day_number]['start_time'], 0, 5); ?>"
                                                    <?php echo ($availability[$day_number]['is_available'] == 0) ? 'disabled' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="time" class="time-input" 
                                                    name="end_time[<?php echo $day_number; ?>]" 
                                                    value="<?php echo substr($availability[$day_number]['end_time'], 0, 5); ?>"
                                                    <?php echo ($availability[$day_number]['is_available'] == 0) ? 'disabled' : ''; ?>>
                                            </td>
                                            <td>
                                                <select class="select-input" 
                                                    name="slot_duration[<?php echo $day_number; ?>]"
                                                    <?php echo ($availability[$day_number]['is_available'] == 0) ? 'disabled' : ''; ?>>
                                                    <option value="15" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 15) ? 'selected' : ''; ?>>15 min</option>
                                                    <option value="30" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 30) ? 'selected' : ''; ?>>30 min</option>
                                                    <option value="45" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 45) ? 'selected' : ''; ?>>45 min</option>
                                                    <option value="60" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 60) ? 'selected' : ''; ?>>1 hour</option>
                                                    <option value="90" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 90) ? 'selected' : ''; ?>>1.5 hours</option>
                                                    <option value="120" <?php echo ($availability[$day_number]['slot_duration_minutes'] == 120) ? 'selected' : ''; ?>>2 hours</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="select-input" 
                                                    name="buffer_time[<?php echo $day_number; ?>]"
                                                    <?php echo ($availability[$day_number]['is_available'] == 0) ? 'disabled' : ''; ?>>
                                                    <option value="0" <?php echo ($availability[$day_number]['buffer_time_minutes'] == 0) ? 'selected' : ''; ?>>None</option>
                                                    <option value="5" <?php echo ($availability[$day_number]['buffer_time_minutes'] == 5) ? 'selected' : ''; ?>>5 min</option>
                                                    <option value="10" <?php echo ($availability[$day_number]['buffer_time_minutes'] == 10) ? 'selected' : ''; ?>>10 min</option>
                                                    <option value="15" <?php echo ($availability[$day_number]['buffer_time_minutes'] == 15) ? 'selected' : ''; ?>>15 min</option>
                                                    <option value="30" <?php echo ($availability[$day_number]['buffer_time_minutes'] == 30) ? 'selected' : ''; ?>>30 min</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_regular_availability" class="btn-primary">
                                <i class="fas fa-save"></i> Save Availability
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Upcoming Bookings</h2>
                </div>
                <div class="card-body">
                    <?php if ($upcoming_bookings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="bookings-table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $upcoming_bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($booking['booking_datetime'])); ?></td>
                                            <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['service_name'] . ' (' . $booking['visa_type'] . ')'); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['mode_name']); ?></small>
                                            </td>
                                            <td class="duration-cell"><?php echo $booking['duration_minutes']; ?> min</td>
                                            <td>
                                                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                                    <?php echo ucfirst($booking['status_name']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>You have no upcoming bookings.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="availability-column side-column">
            <div class="card">
                <div class="card-header">
                    <h2>Request Time Off</h2>
                </div>
                <div class="card-body">
                    <p>Need time off? Submit your request below. Your availability will be automatically blocked during this period once approved.</p>
                    
                    <form action="" method="post" id="time-off-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-input" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input type="time" class="form-input" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" class="form-input" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input type="time" class="form-input" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <textarea class="form-input" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="request_time_off" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Pending & Approved Time Off</h2>
                </div>
                <div class="card-body">
                    <?php if ($time_off_requests->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="time-off-table">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($time_off = $time_off_requests->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $start = new DateTime($time_off['start_datetime']);
                                                $end = new DateTime($time_off['end_datetime']);
                                                
                                                // Same day
                                                if ($start->format('Y-m-d') == $end->format('Y-m-d')) {
                                                    echo $start->format('M d, Y H:i') . ' - ' . $end->format('H:i');
                                                } else {
                                                    echo $start->format('M d, Y H:i') . ' - <br>' . $end->format('M d, Y H:i');
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($time_off['reason']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo ($time_off['status'] == 'approved') ? 'approved' : 'pending'; ?>">
                                                    <?php echo ucfirst($time_off['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clock"></i>
                            <p>You have no pending or approved time off requests.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Time Off History</h2>
                </div>
                <div class="card-body">
                    <?php if ($past_time_off->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="time-off-table">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($time_off = $past_time_off->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $start = new DateTime($time_off['start_datetime']);
                                                $end = new DateTime($time_off['end_datetime']);
                                                
                                                // Same day
                                                if ($start->format('Y-m-d') == $end->format('Y-m-d')) {
                                                    echo $start->format('M d, Y') . '<br>' . $start->format('H:i') . ' - ' . $end->format('H:i');
                                                } else {
                                                    echo $start->format('M d') . ' - ' . $end->format('M d, Y');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge 
                                                    <?php 
                                                    if ($time_off['status'] == 'approved') echo 'approved';
                                                    else if ($time_off['status'] == 'rejected') echo 'rejected';
                                                    else echo 'default';
                                                    ?>">
                                                    <?php echo ucfirst($time_off['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No time off history to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
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

.availability-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.availability-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.main-column {
    flex: 1;
    min-width: 60%;
}

.side-column {
    width: 350px;
}

.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
}

.card-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.card-body p {
    margin-top: 0;
    color: var(--secondary-color);
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.close-alert {
    cursor: pointer;
}

.table-responsive {
    overflow-x: auto;
}

.availability-table, .bookings-table, .time-off-table {
    width: 100%;
    border-collapse: collapse;
}

.availability-table th, .bookings-table th, .time-off-table th {
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
    font-weight: 600;
}

.availability-table td, .bookings-table td, .time-off-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.availability-table tr:last-child td,
.bookings-table tr:last-child td,
.time-off-table tr:last-child td {
    border-bottom: none;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch label {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-switch label:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.toggle-switch input:checked + label {
    background-color: var(--primary-color);
}

.toggle-switch input:checked + label:before {
    transform: translateX(26px);
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--dark-color);
    font-weight: 500;
}

.form-input, .time-input, .select-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-input:focus, .time-input:focus, .select-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    background-color: #f8f9fc;
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-secondary:hover {
    background-color: #eaecf4;
}

.duration-cell {
    font-weight: 600;
    text-align: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.status-badge.approved {
    background-color: var(--success-color);
}

.status-badge.pending {
    background-color: var(--warning-color);
}

.status-badge.rejected {
    background-color: var(--danger-color);
}

.status-badge.default {
    background-color: var(--secondary-color);
}

.text-muted {
    color: var(--secondary-color);
    font-size: 0.9em;
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 992px) {
    .availability-container {
        flex-direction: column;
    }
    
    .side-column {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .availability-table, .bookings-table, .time-off-table {
        display: block;
        overflow-x: auto;
    }
}

.stats-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.stats-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 200px;
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(4, 33, 103, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.stats-icon i {
    font-size: 20px;
    color: var(--primary-color);
}

.stats-info h3 {
    margin: 0;
    font-size: 14px;
    color: var(--secondary-color);
    font-weight: 500;
}

.stats-value {
    margin: 5px 0 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
}

.next-booking {
    flex: 2;
    min-width: 300px;
}

.next-booking-details {
    margin: 5px 0 0;
    color: var(--primary-color);
    font-weight: 600;
}

.next-booking-service {
    margin: 3px 0 0;
    font-size: 13px;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .stats-card {
        min-width: 100%;
    }
    
    .next-booking {
        min-width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle availability toggle
    const availabilityToggles = document.querySelectorAll('.availability-toggle');
    
    availabilityToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const dayNumber = this.id.split('_')[1];
            const rowInputs = document.querySelectorAll(`input[name^="start_time[${dayNumber}]"], input[name^="end_time[${dayNumber}]"], select[name^="slot_duration[${dayNumber}]"], select[name^="buffer_time[${dayNumber}]"]`);
            
            rowInputs.forEach(input => {
                input.disabled = !this.checked;
            });
        });
    });
    
    // Validate time off form
    const timeOffForm = document.getElementById('time-off-form');
    if (timeOffForm) {
        timeOffForm.addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            const startDateTime = new Date(`${startDate}T${startTime}`);
            const endDateTime = new Date(`${endDate}T${endTime}`);
            
            if (endDateTime <= startDateTime) {
                e.preventDefault();
                alert('End time must be after start time');
                return false;
            }
            
            return true;
        });
    }
    
    // Date validation for time off request
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = this.value;
            }
        });
    }
    
    // Handle alert close buttons
    const closeButtons = document.querySelectorAll('.close-alert');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentNode.style.display = 'none';
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
// End output buffering
ob_end_flush();
?>