<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Booking Management";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Get all booking statuses
$query = "SELECT * FROM booking_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$booking_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $booking_statuses[$row['id']] = $row;
    }
}
$stmt->close();

// Get all team members
$query = "SELECT tm.id, tm.role, tm.custom_role_name, 
          u.id as user_id, u.first_name, u.last_name, u.email 
          FROM team_members tm 
          JOIN users u ON tm.user_id = u.id 
          WHERE tm.deleted_at IS NULL AND u.status = 'active'
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[$row['id']] = $row;
    }
}
$stmt->close();

// Get active status filter from query params
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$consultant_filter = isset($_GET['consultant']) ? intval($_GET['consultant']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get active tab from query params
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'bookings';

// Build booking query with filters
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email as client_email,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name,
          cm.mode_name as consultation_mode,
          CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name,
          vs.base_price, scm.additional_fee,
          (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price,
          bp.payment_status
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN users u ON b.user_id = u.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users team_u ON tm.user_id = team_u.id
          LEFT JOIN booking_payments bp ON b.id = bp.booking_id
          WHERE b.deleted_at IS NULL";

// Apply filters
$params = [];
$param_types = "";

if (!empty($status_filter)) {
    $query .= " AND bs.name = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($consultant_filter > 0) {
    $query .= " AND b.team_member_id = ?";
    $params[] = $consultant_filter;
    $param_types .= "i";
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.booking_datetime) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.booking_datetime) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if (!empty($search_term)) {
    $search_term = "%$search_term%";
    $query .= " AND (b.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

$query .= " ORDER BY b.booking_datetime DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$stmt->close();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status_id = $_POST['status_id'];
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $update_query = "UPDATE bookings SET status_id = ?, admin_notes = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('isi', $new_status_id, $admin_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $status_name = $booking_statuses[$new_status_id]['name'];
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'status_changed', ?)";
        $description = "Status changed to '{$status_name}'";
        if (!empty($admin_notes)) {
            $description .= " with notes: {$admin_notes}";
        }
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking status updated successfully";
        header("Location: bookings.php?success=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating booking status: " . $e->getMessage();
    }
}

// Handle booking assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_consultant'])) {
    $booking_id = $_POST['booking_id'];
    $consultant_id = $_POST['consultant_id'];
    
    // Start transaction
        $conn->begin_transaction();
        
        try {
        // Update booking
        $update_query = "UPDATE bookings SET team_member_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $consultant_id, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $consultant_name = $team_members[$consultant_id]['first_name'] . ' ' . $team_members[$consultant_id]['last_name'];
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'assigned', ?)";
        $description = "Booking assigned to {$consultant_name}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
            $stmt->execute();
            $stmt->close();
            
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking assigned successfully";
        header("Location: bookings.php?success=2");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error assigning booking: " . $e->getMessage();
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // Get the cancellation status ID
    $cancel_status_query = "SELECT id FROM booking_statuses WHERE name = 'cancelled_by_admin'";
    $stmt = $conn->prepare($cancel_status_query);
            $stmt->execute();
    $cancel_status = $stmt->get_result()->fetch_assoc();
    $cancel_status_id = $cancel_status['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET status_id = ?, cancelled_by = ?, cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iisi', $cancel_status_id, $_SESSION['id'], $cancellation_reason, $booking_id);
        $stmt->execute();
            $stmt->close();
            
            // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'cancelled', ?)";
        $description = "Booking cancelled by admin with reason: {$cancellation_reason}";
        
            $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
            $stmt->execute();
            $stmt->close();
            
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking cancelled successfully";
        header("Location: bookings.php?success=3");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error cancelling booking: " . $e->getMessage();
    }
}

// Handle rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_booking'])) {
    $booking_id = $_POST['booking_id'];
    $new_datetime = $_POST['new_datetime'];
    $duration_minutes = $_POST['duration_minutes'];
    $reschedule_notes = trim($_POST['reschedule_notes']);
    
    // Get the rescheduled status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'rescheduled'";
    $stmt = $conn->prepare($status_query);
                $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
                $stmt->close();
                
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        booking_datetime = ?,
                        duration_minutes = ?,
                        end_datetime = DATE_ADD(?, INTERVAL ? MINUTE),
                        reschedule_count = reschedule_count + 1,
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Rescheduled: ', ?)
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ississi', $status_id, $new_datetime, $duration_minutes, $new_datetime, $duration_minutes, $reschedule_notes, $booking_id);
                $stmt->execute();
                $stmt->close();
        
        // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'rescheduled', ?)";
        $description = "Booking rescheduled to {$new_datetime} with notes: {$reschedule_notes}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking rescheduled successfully";
        header("Location: bookings.php?success=4");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error rescheduling booking: " . $e->getMessage();
    }
}

// Handle booking completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_booking'])) {
    $booking_id = $_POST['booking_id'];
    $completion_notes = trim($_POST['completion_notes']);
    
    // Get the completed status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'completed'";
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        completed_by = ?,
                        completion_notes = ?
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iisi', $status_id, $_SESSION['id'], $completion_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'completed', ?)";
        $description = "Booking marked as completed with notes: {$completion_notes}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
        $success_message = "Booking marked as completed";
        header("Location: bookings.php?success=5");
            exit;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
        $error_message = "Error completing booking: " . $e->getMessage();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Booking status updated successfully";
            break;
        case 2:
            $success_message = "Booking assigned successfully";
            break;
        case 3:
            $success_message = "Booking cancelled successfully";
            break;
        case 4:
            $success_message = "Booking rescheduled successfully";
            break;
        case 5:
            $success_message = "Booking marked as completed";
            break;
        case 6:
            $success_message = "Business hours updated successfully";
            $active_tab = "business-hours";
            break;
        case 7:
            $success_message = "Special day added successfully";
            $active_tab = "special-days";
            break;
        case 8:
            $success_message = "Special day deleted successfully";
            $active_tab = "special-days";
            break;
        case 9:
            $success_message = "Special day updated successfully";
            $active_tab = "special-days";
            break;
    }
}

// Handle error messages
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
    if (isset($_GET['tab'])) {
        $active_tab = $_GET['tab'];
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Booking Management</h1>
            <p>Manage consultation bookings, schedule appointments, and track booking status</p>
        </div>
        <div>
            <a href="create_booking.php" class="btn primary-btn">
                <i class="fas fa-plus"></i> Create Booking
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>" data-tab="bookings">Bookings</button>
            <button class="tab-btn <?php echo $active_tab === 'team-availability' ? 'active' : ''; ?>" data-tab="team-availability">Team Availability</button>
            <button class="tab-btn <?php echo $active_tab === 'business-hours' ? 'active' : ''; ?>" data-tab="business-hours">Business Hours</button>
            <button class="tab-btn <?php echo $active_tab === 'special-days' ? 'active' : ''; ?>" data-tab="special-days">Special Days</button>
            </div>
        
        <!-- Bookings Tab -->
        <div class="tab-content <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>" id="bookings-tab">
            <!-- Filters Section -->
            <div class="filters-container">
                <form action="bookings.php" method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Statuses</option>
                            <?php foreach ($booking_statuses as $status): ?>
                                <option value="<?php echo $status['name']; ?>" <?php echo ($status_filter === $status['name']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="consultant">Consultant</label>
                        <select name="consultant" id="consultant" class="form-control">
                            <option value="0">All Consultants</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo ($consultant_filter === $member['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="filter-group search-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Ref #, Client name or email" value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn filter-btn">Apply Filters</button>
                        <a href="bookings.php" class="btn reset-btn">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Bookings Table Section -->
            <div class="bookings-table-container">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <p>No bookings found. Adjust your filters or create a new booking.</p>
                    </div>
                <?php else: ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Date & Time</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Consultation</th>
                                <th>Consultant</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($booking['booking_datetime'])); ?><br>
                                        <span class="time"><?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?></span>
                                        <span class="duration">(<?php echo $booking['duration_minutes']; ?> min)</span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['client_name']); ?><br>
                                        <span class="email"><?php echo htmlspecialchars($booking['client_email']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['visa_type']); ?></strong><br>
                                        <span><?php echo htmlspecialchars($booking['service_name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                                    <td>
                                        <?php if (!empty($booking['consultant_name'])): ?>
                                            <?php echo htmlspecialchars($booking['consultant_name']); ?>
                                        <?php else: ?>
                                            <span class="not-assigned">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button type="button" class="btn-action btn-edit" title="Edit Booking" 
                                                onclick="openEditModal(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if (empty($booking['consultant_name']) && in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                                            <button type="button" class="btn-action btn-assign" title="Assign Consultant" 
                                                    onclick="openAssignModal(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['client_name']); ?>')">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                                            <button type="button" class="btn-action btn-reschedule" title="Reschedule" 
                                                    onclick="openRescheduleModal(<?php echo $booking['id']; ?>, '<?php echo $booking['booking_datetime']; ?>', <?php echo $booking['duration_minutes']; ?>)">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            
                                            <button type="button" class="btn-action btn-cancel" title="Cancel Booking" 
                                                    onclick="openCancelModal(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['reference_number']); ?>')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($booking['status_name'] === 'confirmed'): ?>
                                            <button type="button" class="btn-action btn-complete" title="Mark as Completed" 
                                                    onclick="openCompleteModal(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['reference_number']); ?>')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Team Availability Tab -->
        <div class="tab-content <?php echo $active_tab === 'team-availability' ? 'active' : ''; ?>" id="team-availability-tab">
            <div class="section-header">
                <h3>Team Member Availability</h3>
                <p>View team members' availability and schedule appointments</p>
            </div>
            
            <div class="team-members-list">
                <?php foreach ($team_members as $member): ?>
                    <div class="team-member-card" data-member-id="<?php echo $member['id']; ?>">
                        <div class="team-member-info">
                            <div class="member-avatar">
                                <?php if (!empty($member['profile_picture']) && file_exists('../../uploads/profiles/' . $member['profile_picture'])): ?>
                                    <img src="../../uploads/profiles/<?php echo $member['profile_picture']; ?>" alt="Profile picture">
                                <?php else: ?>
                                    <div class="initials">
                                        <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="member-details">
                                <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                                <p class="member-role"><?php echo $member['role'] === 'Custom' ? htmlspecialchars($member['custom_role_name']) : $member['role']; ?></p>
                            </div>
                        </div>
                        <button class="btn view-availability-btn" onclick="loadMemberAvailability(<?php echo $member['id']; ?>)">
                            <i class="fas fa-calendar-alt"></i> View Availability
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="member-availability-container" style="display: none;">
                <div class="back-to-list">
                    <button class="btn" onclick="hideAvailabilityDetails()">
                        <i class="fas fa-arrow-left"></i> Back to Team List
                    </button>
                </div>
                
                <div class="section-subheader">
                    <h4 id="selected-member-name">Team Member Availability</h4>
                </div>
                
                <div class="date-picker-container">
                    <div class="date-navigation">
                        <button class="btn" id="prev-week-btn">
                            <i class="fas fa-chevron-left"></i> Previous Week
                        </button>
                        <span id="current-week-range">Loading...</span>
                        <button class="btn" id="next-week-btn">
                            <i class="fas fa-chevron-right"></i> Next Week
                        </button>
                    </div>
                </div>
                
                <div id="availability-loading" class="text-center p-4" style="display: none;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading availability...</p>
                </div>
                
                <div id="weekly-availability-grid" class="weekly-calendar">
                    <div class="weekday-headers">
                        <div class="time-column-header"></div>
                        <div class="weekday-header">Sun</div>
                        <div class="weekday-header">Mon</div>
                        <div class="weekday-header">Tue</div>
                        <div class="weekday-header">Wed</div>
                        <div class="weekday-header">Thu</div>
                        <div class="weekday-header">Fri</div>
                        <div class="weekday-header">Sat</div>
                    </div>
                    <div id="availability-grid-body">
                        <!-- Time slots will be dynamically loaded here -->
                    </div>
                </div>
                
                <div id="no-availability" class="empty-state" style="display: none;">
                    <i class="fas fa-calendar-times"></i>
                    <p>No availability set for this team member.</p>
                </div>
            </div>
        </div>
        
        <!-- Business Hours Tab -->
        <div class="tab-content <?php echo $active_tab === 'business-hours' ? 'active' : ''; ?>" id="business-hours-tab">
            <div class="section-header">
                <h3>Business Hours</h3>
                <p>Set the regular operating hours for consultation bookings</p>
            </div>
            
            <?php
            // Get business hours
            $query = "SELECT * FROM business_hours ORDER BY day_of_week";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $business_hours_result = $stmt->get_result();
            $business_hours = [];
            
            if ($business_hours_result && $business_hours_result->num_rows > 0) {
                while ($row = $business_hours_result->fetch_assoc()) {
                    $business_hours[$row['day_of_week']] = $row;
                }
            }
            $stmt->close();
            
            $days = [
                0 => 'Sunday',
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday'
            ];
            ?>
            
            <form action="update_business_hours.php" method="POST" class="business-hours-form">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Open?</th>
                                <th>Open Time</th>
                                <th>Close Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day_num => $day_name): ?>
                                <?php 
                                $is_open = isset($business_hours[$day_num]) ? $business_hours[$day_num]['is_open'] : 0;
                                $open_time = isset($business_hours[$day_num]) ? $business_hours[$day_num]['open_time'] : '09:00:00';
                                $close_time = isset($business_hours[$day_num]) ? $business_hours[$day_num]['close_time'] : '17:00:00';
                                ?>
                                <tr>
                                    <td><?php echo $day_name; ?></td>
                                    <td>
                                        <div class="toggle-switch">
                                            <input type="checkbox" name="is_open[<?php echo $day_num; ?>]" id="is_open_<?php echo $day_num; ?>" 
                                                   value="1" <?php echo $is_open ? 'checked' : ''; ?> class="toggle-input">
                                            <label for="is_open_<?php echo $day_num; ?>" class="toggle-label"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="time" name="open_time[<?php echo $day_num; ?>]" class="form-control time-input" 
                                               value="<?php echo substr($open_time, 0, 5); ?>" <?php echo !$is_open ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="time" name="close_time[<?php echo $day_num; ?>]" class="form-control time-input" 
                                               value="<?php echo substr($close_time, 0, 5); ?>" <?php echo !$is_open ? 'disabled' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_business_hours" class="btn primary-btn">
                        <i class="fas fa-save"></i> Save Business Hours
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Special Days Tab -->
        <div class="tab-content <?php echo $active_tab === 'special-days' ? 'active' : ''; ?>" id="special-days-tab">
            <div class="section-header">
                <h3>Special Days & Holidays</h3>
                <p>Manage holidays and special operating hours</p>
            </div>
            
            <?php
            // Get special days
            $query = "SELECT * FROM special_days WHERE date >= CURDATE() ORDER BY date";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $special_days_result = $stmt->get_result();
            $special_days = [];
            
            if ($special_days_result && $special_days_result->num_rows > 0) {
                while ($row = $special_days_result->fetch_assoc()) {
                    $special_days[] = $row;
                }
            }
            $stmt->close();
            ?>
            
            <div class="actions-bar">
                <button type="button" class="btn primary-btn" onclick="openAddSpecialDayModal()">
                    <i class="fas fa-plus"></i> Add Special Day
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Alternative Hours</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($special_days)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No special days or holidays set</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($special_days as $day): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($day['description']); ?></td>
                                    <td>
                                        <?php if ($day['is_closed']): ?>
                                            <span class="status-badge inactive">Closed</span>
                                        <?php else: ?>
                                            <span class="status-badge active">Open (Modified Hours)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$day['is_closed']): ?>
                                            <?php echo substr($day['alternative_open_time'], 0, 5); ?> - 
                                            <?php echo substr($day['alternative_close_time'], 0, 5); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editSpecialDay(<?php echo $day['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="deleteSpecialDay(<?php echo $day['id']; ?>, '<?php echo date('M d, Y', strtotime($day['date'])); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Assign Consultant Modal -->
<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Consultant</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="assignForm">
                    <input type="hidden" name="booking_id" id="assign_booking_id">
                    
                    <p>Assigning consultant for booking with client: <strong id="assign_client_name"></strong></p>
                    
                        <div class="form-group">
                        <label for="consultant_id">Select Consultant*</label>
                        <select name="consultant_id" id="consultant_id" class="form-control" required>
                            <option value="">Select Consultant</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                                    (<?php echo $member['role'] === 'Custom' ? $member['custom_role_name'] : $member['role']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_consultant" class="btn submit-btn">Assign Consultant</button>
                    </div>
                </form>
            </div>
                        </div>
                    </div>
                </div>
                
<!-- Cancel Booking Modal -->
<div class="modal" id="cancelModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="cancelForm">
                    <input type="hidden" name="booking_id" id="cancel_booking_id">
                    
                    <p>You are about to cancel booking <strong id="cancel_reference"></strong>. This action cannot be undone.</p>
                    
                        <div class="form-group">
                        <label for="cancellation_reason">Cancellation Reason*</label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" required></textarea>
                        </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Close</button>
                        <button type="submit" name="cancel_booking" class="btn submit-btn danger-btn">Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
                        </div>
                    </div>
                    
<!-- Reschedule Booking Modal -->
<div class="modal" id="rescheduleModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reschedule Booking</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="rescheduleForm">
                    <input type="hidden" name="booking_id" id="reschedule_booking_id">
                    
                    <div class="form-group">
                        <label for="new_datetime">New Date and Time*</label>
                        <input type="datetime-local" name="new_datetime" id="new_datetime" class="form-control" required>
                    </div>
                    
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes)*</label>
                        <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="15" step="15" required>
                        </div>
                    
                        <div class="form-group">
                        <label for="reschedule_notes">Rescheduling Notes*</label>
                        <textarea name="reschedule_notes" id="reschedule_notes" class="form-control" rows="3" required></textarea>
                        </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="reschedule_booking" class="btn submit-btn">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
                    </div>
                </div>
                
<!-- Complete Booking Modal -->
<div class="modal" id="completeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mark Booking as Completed</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="completeForm">
                    <input type="hidden" name="booking_id" id="complete_booking_id">
                    
                    <p>You are marking booking <strong id="complete_reference"></strong> as completed.</p>
                    
                        <div class="form-group">
                        <label for="completion_notes">Completion Notes*</label>
                        <textarea name="completion_notes" id="completion_notes" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="complete_booking" class="btn submit-btn">Mark as Completed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Booking Status</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="statusForm">
                    <input type="hidden" name="booking_id" id="status_booking_id">
                    
                    <div class="form-group">
                        <label for="status_id">New Status*</label>
                        <select name="status_id" id="status_id" class="form-control" required>
                            <option value="">Select Status</option>
                            <?php foreach ($booking_statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Special Day Modal -->
<div class="modal" id="specialDayModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Special Day</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="update_special_day.php" method="POST" id="specialDayForm">
                    <input type="hidden" name="special_day_id" id="special_day_id" value="">
                    
                        <div class="form-group">
                        <label for="special_date">Date*</label>
                        <input type="date" name="special_date" id="special_date" class="form-control" required>
                        </div>
                    
                        <div class="form-group">
                        <label for="description">Description*</label>
                        <input type="text" name="description" id="description" class="form-control" 
                               placeholder="e.g., Christmas Day, Company Event" required>
                        </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="is_closed" id="is_closed" value="1" checked>
                        <label for="is_closed">Office Closed</label>
                    </div>
                    
                    <div id="alternative_hours" style="display: none;">
                        <div class="form-row">
                    <div class="form-group">
                                <label for="alternative_open_time">Open Time*</label>
                                <input type="time" name="alternative_open_time" id="alternative_open_time" class="form-control">
                    </div>
                    <div class="form-group">
                                <label for="alternative_close_time">Close Time*</label>
                                <input type="time" name="alternative_close_time" id="alternative_close_time" class="form-control">
                            </div>
                    </div>
                </div>
                
                <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_special_day" class="btn submit-btn">Save</button>
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
    --info-color: #36b9cc;
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
    text-decoration: none;
}

.primary-btn:hover {
    background-color: #031c56;
    text-decoration: none;
    color: white;
}

/* Filters Styling */
.filters-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    margin-bottom: 20px;
}

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.search-group {
    flex: 2;
    min-width: 250px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

.filter-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.reset-btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.reset-btn:hover {
    background-color: #717380;
    color: white;
    text-decoration: none;
}

/* Bookings Table */
.bookings-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
}

.bookings-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.bookings-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.bookings-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.bookings-table tbody tr:last-child td {
    border-bottom: none;
}

.time, .email, .duration {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.duration {
    margin-left: 5px;
}

.not-assigned {
    font-style: italic;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.actions-cell {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
}

.btn-assign {
    background-color: var(--info-color);
}

.btn-assign:hover {
    background-color: #2fa6b9;
}

.btn-reschedule {
    background-color: #9932CC;
}

.btn-reschedule:hover {
    background-color: #8021a8;
}

.btn-cancel {
    background-color: var(--danger-color);
}

.btn-cancel:hover {
    background-color: #d44235;
}

.btn-complete {
    background-color: var(--success-color);
}

.btn-complete:hover {
    background-color: #18b07b;
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
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
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

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
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

.danger-btn {
    background-color: var(--danger-color);
}

.danger-btn:hover {
    background-color: #d44235;
}

.submit-btn:hover {
    background-color: #031c56;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .filters-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .bookings-table {
        display: block;
        overflow-x: auto;
    }
    
    .actions-cell {
        flex-direction: row;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}

.tabs-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--secondary-color);
    font-weight: 500;
    position: relative;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.section-header {
    margin-bottom: 20px;
}

.section-header h3 {
    margin: 0 0 5px 0;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.section-header p {
    margin: 0;
    color: var(--secondary-color);
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.table th, .table td {
    padding: 10px 15px;
    border-bottom: 1px solid var(--border-color);
}

.table th {
    background-color: var(--light-color);
    text-align: left;
    font-weight: 600;
    color: var(--primary-color);
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-label {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.toggle-label:before {
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

.toggle-input:checked + .toggle-label {
    background-color: var(--success-color);
}

.toggle-input:checked + .toggle-label:before {
    transform: translateX(26px);
}

.time-input {
    width: 120px;
}

.actions-bar {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
}

/* Additional styles for availability tab */
.availability-checker {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    margin-bottom: 20px;
}

.availability-form {
    display: flex;
    flex-wrap: wrap;
}

.availability-results {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    margin-top: 20px;
}

.section-subheader {
    margin-bottom: 15px;
}

.section-subheader h4 {
    margin: 0 0 5px 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-subheader p {
    margin: 0;
    color: var(--secondary-color);
}

.member-available {
    color: var(--success-color);
    font-weight: 500;
}

.member-unavailable {
    color: var(--danger-color);
    font-weight: 500;
}

.p-4 {
    padding: 1rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.text-center {
    text-align: center;
}

.schedule-btn {
    background-color: var(--success-color);
}

.schedule-btn:hover {
    background-color: #18b07b;
}

/* Team Members List Styles */
.team-members-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.team-member-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.team-member-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-avatar .initials {
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.member-details h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.member-role {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 0.85rem;
}

.view-availability-btn {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background-color 0.2s;
}

.view-availability-btn:hover {
    background-color: #f0f3f9;
}

.back-to-list {
    margin-bottom: 20px;
}

.date-picker-container {
    margin: 20px 0;
}

.date-navigation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 15px;
}

.weekly-calendar {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 30px;
}

.weekday-headers {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    border-bottom: 1px solid var(--border-color);
}

.weekday-header, .time-column-header {
    padding: 10px;
    text-align: center;
    font-weight: 600;
    color: var(--primary-color);
    background-color: var(--light-color);
}

.time-slot-row {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    border-bottom: 1px solid var(--border-color);
}

.time-slot-row:last-child {
    border-bottom: none;
}

.time-label {
    padding: 10px;
    text-align: right;
    color: var(--secondary-color);
    font-weight: 500;
    font-size: 0.85rem;
    background-color: var(--light-color);
}

.time-slot {
    padding: 10px;
    text-align: center;
    height: 40px;
    position: relative;
}

.time-slot.available {
    background-color: rgba(28, 200, 138, 0.1);
    cursor: pointer;
}

.time-slot.available:hover {
    background-color: rgba(28, 200, 138, 0.2);
}

.time-slot.unavailable {
    background-color: #f8f9fc;
}

.time-slot.booked {
    background-color: rgba(231, 74, 59, 0.1);
}

.time-slot.holiday {
    background-color: rgba(246, 194, 62, 0.1);
}

.mt-4 {
    margin-top: 2rem;
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

// Open assign modal
function openAssignModal(bookingId, clientName) {
    document.getElementById('assign_booking_id').value = bookingId;
    document.getElementById('assign_client_name').textContent = clientName;
    openModal('assignModal');
}

// Open cancel modal
function openCancelModal(bookingId, reference) {
    document.getElementById('cancel_booking_id').value = bookingId;
    document.getElementById('cancel_reference').textContent = reference;
    openModal('cancelModal');
}

// Open reschedule modal
function openRescheduleModal(bookingId, datetime, duration) {
    document.getElementById('reschedule_booking_id').value = bookingId;
    
    // Format datetime for datetime-local input
    const date = new Date(datetime);
    const formattedDate = date.toISOString().slice(0, 16);
    document.getElementById('new_datetime').value = formattedDate;
    document.getElementById('duration_minutes').value = duration;
    
    openModal('rescheduleModal');
}

// Open complete modal
function openCompleteModal(bookingId, reference) {
    document.getElementById('complete_booking_id').value = bookingId;
    document.getElementById('complete_reference').textContent = reference;
    openModal('completeModal');
}

// Open edit status modal
function openEditModal(bookingId) {
    document.getElementById('status_booking_id').value = bookingId;
    openModal('statusModal');
}

// Prevent form submission if cancellation reason is empty
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('cancellation_reason').value.trim();
    if (reason === '') {
        e.preventDefault();
        alert('Please provide a cancellation reason.');
    }
});

// Tab functionality
document.querySelectorAll('.tab-btn').forEach(function(tab) {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-btn').forEach(function(t) {
            t.classList.remove('active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all tab content
        document.querySelectorAll('.tab-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        // Show corresponding tab content
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId + '-tab').classList.add('active');
    });
});

// Toggle business hours open/closed
document.querySelectorAll('.toggle-input').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const dayNum = this.id.replace('is_open_', '');
        const timeInputs = document.querySelectorAll(`input[name^="open_time[${dayNum}]"], input[name^="close_time[${dayNum}]"]`);
        
        timeInputs.forEach(function(input) {
            input.disabled = !toggle.checked;
        });
    });
});

// Special day modal
function openAddSpecialDayModal() {
    document.getElementById('special_day_id').value = '';
    document.getElementById('specialDayForm').reset();
    document.querySelector('#specialDayModal .modal-title').textContent = 'Add Special Day';
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('special_date').value = tomorrow.toISOString().split('T')[0];
    
    openModal('specialDayModal');
}

// Toggle alternative hours when is_closed changes
document.getElementById('is_closed').addEventListener('change', function() {
    const alternativeHours = document.getElementById('alternative_hours');
    alternativeHours.style.display = this.checked ? 'none' : 'block';
    
    const timeInputs = document.querySelectorAll('#alternative_open_time, #alternative_close_time');
    timeInputs.forEach(function(input) {
        input.required = !this.checked;
    }, this);
});

function editSpecialDay(id) {
    // This function would be implemented to load special day data via AJAX and populate the form
    fetch('get_special_day.php?id=' + id)
            .then(response => response.json())
            .then(data => {
            document.getElementById('special_day_id').value = data.id;
            document.getElementById('special_date').value = data.date;
            document.getElementById('description').value = data.description;
            document.getElementById('is_closed').checked = data.is_closed == 1;
            
            if (data.is_closed != 1) {
                document.getElementById('alternative_hours').style.display = 'block';
                document.getElementById('alternative_open_time').value = data.alternative_open_time.substring(0, 5);
                document.getElementById('alternative_close_time').value = data.alternative_close_time.substring(0, 5);
                } else {
                document.getElementById('alternative_hours').style.display = 'none';
                }
            
            document.querySelector('#specialDayModal .modal-title').textContent = 'Edit Special Day';
            openModal('specialDayModal');
            })
            .catch(error => {
            console.error('Error fetching special day:', error);
            alert('Error loading special day data.');
        });
}

function deleteSpecialDay(id, date) {
    if (confirm('Are you sure you want to delete the special day for ' + date + '?')) {
        window.location.href = 'update_special_day.php?delete_id=' + id;
    }
}

// Variables to track current state
let currentMemberId = null;
let currentStartDate = null;

// Function to load member availability
function loadMemberAvailability(memberId) {
    // Hide team members list and show availability container
    document.querySelector('.team-members-list').style.display = 'none';
    document.getElementById('member-availability-container').style.display = 'block';
    
    // Set current member ID
    currentMemberId = memberId;
    
    // Get team member name
    const memberCard = document.querySelector(`.team-member-card[data-member-id="${memberId}"]`);
    const memberName = memberCard.querySelector('h4').textContent;
    document.getElementById('selected-member-name').textContent = `${memberName}'s Availability`;
    
    // Set current week to this week
    currentStartDate = getStartOfWeek(new Date());
    
    // Update week display and load availability
    updateWeekDisplay();
    fetchMemberAvailability();
    
    // Setup navigation buttons
    document.getElementById('prev-week-btn').addEventListener('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() - 7);
        updateWeekDisplay();
        fetchMemberAvailability();
    });
    
    document.getElementById('next-week-btn').addEventListener('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() + 7);
        updateWeekDisplay();
        fetchMemberAvailability();
    });
}

// Function to hide availability details and show team list
function hideAvailabilityDetails() {
    document.querySelector('.team-members-list').style.display = 'grid';
    document.getElementById('member-availability-container').style.display = 'none';
    currentMemberId = null;
}

// Function to get the start of the week (Sunday)
function getStartOfWeek(date) {
    const result = new Date(date);
    result.setDate(date.getDate() - date.getDay()); // go to Sunday
    result.setHours(0, 0, 0, 0);
    return result;
}

// Function to update the week display
function updateWeekDisplay() {
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + 6);
    
    const formatDate = (date) => {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };
    
    document.getElementById('current-week-range').textContent = 
        `${formatDate(currentStartDate)} - ${formatDate(endDate)}`;
}

// Function to fetch member availability
function fetchMemberAvailability() {
    if (!currentMemberId) return;
    
    // Show loading
    document.getElementById('availability-loading').style.display = 'block';
    document.getElementById('weekly-availability-grid').style.display = 'none';
    document.getElementById('no-availability').style.display = 'none';
    
    // Format dates for the request
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + 6);
    
    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };
    
    // Make AJAX request
    fetch(`ajax/get_team_availability.php?team_member_id=${currentMemberId}`)
        .then(response => response.json())
        .then(data => {
            // Hide loading
            document.getElementById('availability-loading').style.display = 'none';
            
            if (data.error) {
                alert('Error loading availability: ' + data.error);
                return;
            }
            
            if (data.availability.length === 0) {
                document.getElementById('no-availability').style.display = 'block';
                return;
            }
            
            // Build availability grid
            buildAvailabilityGrid(data.availability, data.time_off);
            document.getElementById('weekly-availability-grid').style.display = 'block';
        })
        .catch(error => {
            document.getElementById('availability-loading').style.display = 'none';
            console.error('Error fetching availability:', error);
            alert('Error loading availability. Please try again.');
        });
}

// Function to build the availability grid
function buildAvailabilityGrid(availability, timeOff) {
    const gridBody = document.getElementById('availability-grid-body');
    gridBody.innerHTML = '';
    
    // Create time slots from 8 AM to 8 PM
    const timeSlots = [];
    for (let hour = 8; hour <= 20; hour++) {
        timeSlots.push(`${hour.toString().padStart(2, '0')}:00`);
        if (hour < 20) {
            timeSlots.push(`${hour.toString().padStart(2, '0')}:30`);
        }
    }
    
    // Create a map of days and their availability
    const availabilityMap = {};
    availability.forEach(slot => {
        availabilityMap[slot.day_of_week] = {
            start: slot.start_time,
            end: slot.end_time
        };
    });
    
    // Create time off map
    const timeOffMap = {};
    timeOff.forEach(off => {
        const startDate = new Date(off.start_datetime);
        const endDate = new Date(off.end_datetime);
        
        // Iterate through days covered by time off
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            const dateString = d.toISOString().split('T')[0];
            if (!timeOffMap[dateString]) {
                timeOffMap[dateString] = [];
            }
            
            timeOffMap[dateString].push({
                start: startDate.toTimeString().substring(0, 5),
                end: endDate.toTimeString().substring(0, 5),
                reason: off.reason
            });
        }
    });
    
    // Build grid
    timeSlots.forEach(timeSlot => {
        const row = document.createElement('div');
        row.className = 'time-slot-row';
        
        // Add time label
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-label';
        timeLabel.textContent = timeSlot;
        row.appendChild(timeLabel);
        
        // Add slots for each day
        for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
            const slot = document.createElement('div');
            slot.className = 'time-slot';
            
            // Get current date for this slot
            const slotDate = new Date(currentStartDate);
            slotDate.setDate(slotDate.getDate() + dayIndex);
            const dateString = slotDate.toISOString().split('T')[0];
            
            // Check if it's a time off day
            const isTimeOff = timeOffMap[dateString] && timeOffMap[dateString].some(off => {
                return timeSlot >= off.start && timeSlot <= off.end;
            });
            
            // Check if it's within availability hours
            const isAvailable = !isTimeOff && availabilityMap[dayIndex] && 
                             timeSlot >= availabilityMap[dayIndex].start.substring(0, 5) && 
                             timeSlot < availabilityMap[dayIndex].end.substring(0, 5);
            
            if (isTimeOff) {
                slot.className += ' holiday';
                slot.title = 'Time off';
            } else if (isAvailable) {
                slot.className += ' available';
                slot.title = 'Available';
                
                // Add click handler to create booking
                slot.onclick = function() {
                    const bookingDate = `${dateString}T${timeSlot}`;
                    window.location.href = `create_booking.php?date=${dateString}&time=${timeSlot}&consultant=${currentMemberId}`;
                };
            } else {
                slot.className += ' unavailable';
                slot.title = 'Unavailable';
            }
            
            row.appendChild(slot);
        }
        
        gridBody.appendChild(row);
    });
}

// Update the existing checkAvailability function
function checkTeamAvailability() {
    const date = document.getElementById('availability_date').value;
    const time = document.getElementById('availability_time').value;
    const duration = document.getElementById('availability_duration').value;
    
    if (!date || !time) {
        alert('Please select a date and time');
        return;
    }
    
    // Show loading
    document.getElementById('availability-check-loading').style.display = 'block';
    document.querySelector('.availability-results').style.display = 'none';
    document.getElementById('no-availability-results').style.display = 'none';
    
    // Format datetime for display
    const displayDate = new Date(date + 'T' + time);
    document.getElementById('availability-date-display').textContent = 
        `${displayDate.toLocaleDateString()} at ${displayDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} for ${duration} minutes`;
    
    // Make AJAX request to check availability
    fetch('ajax/check_team_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            date: date,
            time: time,
            duration: duration
        })
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading
        document.getElementById('availability-check-loading').style.display = 'none';
        
        if (data.length === 0) {
            // No available team members
            document.getElementById('no-availability-results').style.display = 'block';
            document.querySelector('.availability-results').style.display = 'none';
        } else {
            // Display results
            const tbody = document.getElementById('availability-results-body');
            tbody.innerHTML = '';
            
            data.forEach(member => {
                const tr = document.createElement('tr');
                
                // Member name
                const tdName = document.createElement('td');
                tdName.textContent = member.team_member_name;
                tr.appendChild(tdName);
                
                // Role
                const tdRole = document.createElement('td');
                tdRole.textContent = member.custom_role_name || member.role;
                tr.appendChild(tdRole);
                
                // Status
                const tdStatus = document.createElement('td');
                if (member.is_available) {
                    tdStatus.innerHTML = '<span class="member-available"><i class="fas fa-check-circle"></i> Available</span>';
                } else {
                    tdStatus.innerHTML = '<span class="member-unavailable"><i class="fas fa-times-circle"></i> Unavailable</span>';
                }
                tr.appendChild(tdStatus);
                
                // Actions
                const tdActions = document.createElement('td');
                tdActions.className = 'actions-cell';
                if (member.is_available) {
                    const scheduleBtn = document.createElement('button');
                    scheduleBtn.type = 'button';
                    scheduleBtn.className = 'btn-action schedule-btn';
                    scheduleBtn.title = 'Schedule Booking';
                    scheduleBtn.innerHTML = '<i class="fas fa-calendar-plus"></i>';
                    scheduleBtn.onclick = function() {
                        window.location.href = `create_booking.php?date=${date}&time=${time}&duration=${duration}&consultant=${member.team_member_id}`;
                    };
                    tdActions.appendChild(scheduleBtn);
                }
                tr.appendChild(tdActions);
                
                tbody.appendChild(tr);
            });
            
            document.querySelector('.availability-results').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error checking availability:', error);
        document.getElementById('availability-check-loading').style.display = 'none';
        alert('Error checking team availability. Please try again.');
    });
}

// Update the existing event listener
document.getElementById('checkAvailabilityBtn').addEventListener('click', function() {
    checkTeamAvailability();
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>