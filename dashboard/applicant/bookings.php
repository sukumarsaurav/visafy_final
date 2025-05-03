<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Bookings";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Get upcoming bookings
try {
    $query = "SELECT b.id, b.booking_datetime, b.end_datetime, b.status, 
             b.service_id, vs.service_name, vs.description, 
             u.first_name, u.last_name, tm.role as member_role
             FROM bookings b
             JOIN visa_services vs ON b.service_id = vs.id
             LEFT JOIN team_members tm ON b.team_member_id = tm.id
             LEFT JOIN users u ON tm.user_id = u.id
             WHERE b.user_id = ? AND b.booking_datetime >= NOW()
             ORDER BY b.booking_datetime ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $upcoming = [];
    
    while ($row = $result->fetch_assoc()) {
        $upcoming[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching upcoming bookings: " . $e->getMessage());
    $upcoming = [];
}

// Get past bookings
try {
    $query = "SELECT b.id, b.booking_datetime, b.end_datetime, b.status, 
             b.service_id, vs.service_name, vs.description, 
             u.first_name, u.last_name, tm.role as member_role
             FROM bookings b
             JOIN visa_services vs ON b.service_id = vs.id
             LEFT JOIN team_members tm ON b.team_member_id = tm.id
             LEFT JOIN users u ON tm.user_id = u.id
             WHERE b.user_id = ? AND b.booking_datetime < NOW()
             ORDER BY b.booking_datetime DESC LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $past = [];
    
    while ($row = $result->fetch_assoc()) {
        $past[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching past bookings: " . $e->getMessage());
    $past = [];
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Bookings</h1>
            <p>View and manage your appointments</p>
        </div>
        <div>
            <a href="book_appointment.php" class="btn primary-btn">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </a>
        </div>
    </div>
    
    <!-- Upcoming Bookings -->
    <div class="section">
        <div class="section-header">
            <h2>Upcoming Appointments</h2>
        </div>
        <div class="booking-list">
            <?php if (empty($upcoming)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>You don't have any upcoming appointments.</p>
                    <a href="book_appointment.php" class="btn-link">Schedule a consultation</a>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming as $booking): ?>
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
                            <h3><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                            
                            <?php if (!empty($booking['first_name'])): ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?> 
                                    (<?php echo htmlspecialchars($booking['member_role']); ?>)
                                </p>
                            <?php else: ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> Not assigned yet
                                </p>
                            <?php endif; ?>
                            
                            <p class="status">
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                View Details
                            </a>
                            
                            <?php if ($booking['status'] !== 'cancelled'): ?>
                                <a href="reschedule_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                                    Reschedule
                                </a>
                                <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-danger">
                                    Cancel
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
        <div class="booking-list past-list">
            <?php if (empty($past)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>You don't have any past appointments.</p>
                </div>
            <?php else: ?>
                <?php foreach ($past as $booking): ?>
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
                            <h3><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                            
                            <?php if (!empty($booking['first_name'])): ?>
                                <p class="consultant">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?> 
                                    (<?php echo htmlspecialchars($booking['member_role']); ?>)
                                </p>
                            <?php endif; ?>
                            
                            <p class="status">
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                View Details
                            </a>
                            
                            <?php if ($booking['status'] == 'completed'): ?>
                                <a href="leave_feedback.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                                    Leave Feedback
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
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
    cursor: pointer;
    text-decoration: none;
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
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
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
    text-decoration: none;
}

.btn-link:hover {
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

.status-confirmed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-pending {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.status-cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
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

.btn {
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-size: 13px;
    white-space: nowrap;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-secondary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--border-color);
}

.btn-danger {
    background-color: white;
    color: var(--danger-color);
    border: 1px solid var(--border-color);
}

.past-list .booking-card {
    opacity: 0.75;
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
        width: 100%;
    }
    
    .date {
        flex-direction: row;
        gap: 5px;
        margin-bottom: 0;
    }
    
    .booking-actions {
        flex-direction: row;
        border-left: none;
        border-top: 1px solid var(--border-color);
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 