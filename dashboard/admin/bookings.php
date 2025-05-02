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
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>