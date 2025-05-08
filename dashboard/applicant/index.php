<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Dashboard";
$page_specific_css = "assets/css/dashboard.css"; // Optional: specific CSS for this page
require_once 'includes/header.php';

// Get application statistics
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM applications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_applications = $result->fetch_assoc()['total'];
    $stmt->close();
} catch (Exception $e) {
    error_log("Error counting applications: " . $e->getMessage());
    $total_applications = 0;
}

// Get applications by status
try {
    $stmt = $conn->prepare("SELECT s.name as status, COUNT(*) as count 
                        FROM applications a
                        JOIN application_statuses s ON a.status_id = s.id
                        WHERE a.user_id = ? 
                        GROUP BY s.name");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts = [];

    while ($row = $result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching application status counts: " . $e->getMessage());
    $status_counts = [];
}

// Get pending documents
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_docs 
                        FROM application_documents ad
                        JOIN applications a ON ad.application_id = a.id
                        WHERE a.user_id = ? AND ad.status = 'pending'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_documents = $result->fetch_assoc()['pending_docs'];
    $stmt->close();
} catch (Exception $e) {
    error_log("Error counting pending documents: " . $e->getMessage());
    $pending_documents = 0;
}

// Get upcoming bookings
try {
    $stmt = $conn->prepare("SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, 
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
                           WHERE b.user_id = ? 
                           AND b.booking_datetime >= NOW()
                           AND b.deleted_at IS NULL
                           AND bs.name != 'cancelled'
                           ORDER BY b.booking_datetime ASC
                           LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $upcoming_bookings = [];

    while ($row = $result->fetch_assoc()) {
        $upcoming_bookings[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching upcoming bookings: " . $e->getMessage());
    $upcoming_bookings = [];
}

// Get recent applications
try {
    $stmt = $conn->prepare("SELECT a.id, v.visa_type, s.name as status, a.created_at, a.updated_at,
                        c.country_name
                        FROM applications a
                        JOIN visas v ON a.visa_id = v.visa_id
                        JOIN countries c ON v.country_id = c.country_id
                        JOIN application_statuses s ON a.status_id = s.id
                        WHERE a.user_id = ?
                        ORDER BY a.updated_at DESC
                        LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_applications = [];

    while ($row = $result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching recent applications: " . $e->getMessage());
    $recent_applications = [];
}
?>

<div class="content">
    <div class="dashboard-welcome">
        <div class="welcome-message">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h1>
            <p class="date-today"><?php echo date('l, F j, Y'); ?></p>
        </div>
        
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $total_applications; ?></h3>
                <p>Total Applications</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo isset($status_counts['approved']) ? $status_counts['approved'] : 0; ?></h3>
                <p>Approved Applications</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo isset($status_counts['in_progress']) ? $status_counts['in_progress'] : 0; ?></h3>
                <p>In Progress</p>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $pending_documents; ?></h3>
                <p>Pending Documents</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content Sections -->
    <div class="dashboard-sections">
        <!-- Recent Applications -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Applications</h2>
                <a href="applications.php" class="view-all">View All</a>
            </div>
            <div class="section-content">
                <?php if (empty($recent_applications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>You don't have any visa applications yet.</p>
                        <a href="applications.php?action=new" class="btn-link">Start a new application</a>
                    </div>
                <?php else: ?>
                    <div class="application-list">
                        <?php foreach ($recent_applications as $application): ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <div class="visa-type">
                                        <i class="fas fa-passport"></i>
                                        <span><?php echo htmlspecialchars($application['visa_type']); ?></span>
                                    </div>
                                    <div class="status-badge <?php echo strtolower($application['status']); ?>">
                                        <?php echo htmlspecialchars($application['status']); ?>
                                    </div>
                                </div>
                                <div class="application-details">
                                    <p class="country"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($application['country_name']); ?></p>
                                    <p class="date"><i class="fas fa-clock"></i> Updated: <?php echo date('M j, Y', strtotime($application['updated_at'])); ?></p>
                                </div>
                                <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn-view">View Application</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Bookings -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Upcoming Bookings</h2>
                <a href="bookings.php" class="view-all">View All</a>
            </div>
            <div class="section-content">
                <?php if (empty($upcoming_bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar"></i>
                        <p>You don't have any upcoming bookings.</p>
                        <a href="bookings.php?action=new" class="btn-link">Schedule a consultation</a>
                    </div>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-date">
                                    <div class="date-badge">
                                        <span class="month"><?php echo date('M', strtotime($booking['booking_datetime'])); ?></span>
                                        <span class="day"><?php echo date('d', strtotime($booking['booking_datetime'])); ?></span>
                                    </div>
                                    <div class="time">
                                        <?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?> - 
                                        <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                                    </div>
                                </div>
                                <div class="booking-details">
                                    <h3><?php echo htmlspecialchars($booking['visa_type'] . ' - ' . $booking['country_name']); ?></h3>
                                    <p class="consultation-mode">
                                        <i class="fas fa-video"></i> 
                                        <?php echo htmlspecialchars($booking['consultation_mode']); ?>
                                    </p>
                                    <p class="service-name">
                                        <?php echo htmlspecialchars($booking['service_name']); ?>
                                    </p>
                                    <div class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                        <?php echo htmlspecialchars($booking['status_name']); ?>
                                    </div>
                                </div>
                                <div class="booking-actions">
                                    <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-view">View Details</a>
                                    <?php if ($booking['status_name'] != 'cancelled'): ?>
                                        <a href="reschedule_booking.php?id=<?php echo $booking['id']; ?>" class="btn-link">Reschedule</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Links Section -->
    <div class="quick-links-section">
        <h2>Quick Links</h2>
        <div class="quick-links">
            <a href="documents.php" class="quick-link-card">
                <i class="fas fa-file-upload"></i>
                <span>Upload Documents</span>
            </a>
            <a href="bookings.php?action=new" class="quick-link-card">
                <i class="fas fa-calendar-plus"></i>
                <span>Book a Consultation</span>
            </a>
            <a href="messages.php" class="quick-link-card">
                <i class="fas fa-comments"></i>
                <span>Contact Support</span>
            </a>
            <a href="profile.php" class="quick-link-card">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
}

/* Welcome Section */
.dashboard-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.welcome-message h1 {
    font-size: 1.8rem;
    color: var(--primary-color);
    margin: 0;
}

.date-today {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 1rem;
}

.quick-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 10px 16px;
    border-radius: 4px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: all 0.2s;
}

.action-btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.action-btn.primary:hover {
    background-color: #031c56;
}

.action-btn.secondary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.action-btn.secondary:hover {
    background-color: var(--light-color);
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    border-left: 4px solid var(--primary-color);
}

.stat-card.primary {
    border-left-color: var(--primary-color);
}

.stat-card.success {
    border-left-color: var(--success-color);
}

.stat-card.warning {
    border-left-color: var(--warning-color);
}

.stat-card.danger {
    border-left-color: var(--danger-color);
}

.stat-icon {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background-color: var(--light-color);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stat-card.primary .stat-icon {
    color: var(--primary-color);
}

.stat-card.success .stat-icon {
    color: var(--success-color);
}

.stat-card.warning .stat-icon {
    color: var(--warning-color);
}

.stat-card.danger .stat-icon {
    color: var(--danger-color);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-color);
}

.stat-details p {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

/* Dashboard Sections */
.dashboard-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.section-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
    font-weight: 600;
}

.view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
}

.section-content {
    padding: 20px;
    min-height: 320px;
}

/* Empty States */
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
    opacity: 0.6;
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

/* Application Cards */
.application-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.application-card {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    transition: box-shadow 0.2s;
}

.application-card:hover {
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
}

.application-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.visa-type {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--dark-color);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
}

.status-badge.approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.in_progress {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge.pending {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.application-details {
    margin-bottom: 15px;
}

.application-details p {
    margin: 5px 0;
    color: var(--secondary-color);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-view {
    display: inline-block;
    padding: 8px 12px;
    background-color: var(--light-color);
    color: var(--primary-color);
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    transition: background-color 0.2s;
}

.btn-view:hover {
    background-color: #eaecf4;
}

/* Booking Cards */
.booking-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.booking-card {
    display: flex;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.booking-card:hover {
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
}

.booking-date {
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    text-align: center;
    min-width: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 10px;
}

.date-badge .month {
    font-size: 14px;
    text-transform: uppercase;
}

.date-badge .day {
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
    margin: 0 0 8px;
    font-size: 16px;
    color: var(--dark-color);
}

.booking-details p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

.booking-details .consultation-mode {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    font-weight: 500;
    margin: 5px 0;
}

.booking-details .description {
    color: var(--secondary-color);
    font-size: 14px;
    margin: 8px 0;
}

.booking-details .status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    margin-top: 8px;
}

.booking-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 15px;
    border-left: 1px solid var(--border-color);
}

.booking-actions .btn-view {
    background-color: var(--primary-color);
    color: white;
    text-align: center;
}

.booking-actions .btn-view:hover {
    background-color: #031c56;
}

.booking-actions .btn-link {
    text-align: center;
    color: var(--primary-color);
    text-decoration: none;
}

.booking-actions .btn-link:hover {
    text-decoration: underline;
}

/* Quick Links Section */
.quick-links-section {
    margin-bottom: 25px;
}

.quick-links-section h2 {
    margin: 0 0 15px;
    font-size: 1.1rem;
    color: var(--primary-color);
    font-weight: 600;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-link-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: var(--dark-color);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 120px;
}

.quick-link-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.quick-link-card i {
    font-size: 24px;
    margin-bottom: 10px;
    color: var(--primary-color);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-welcome {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .dashboard-sections {
        grid-template-columns: 1fr;
    }
    
    .booking-card {
        flex-direction: column;
    }
    
    .booking-date {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
    }
    
    .date-badge {
        margin-bottom: 0;
        flex-direction: row;
        gap: 5px;
    }
    
    .booking-actions {
        flex-direction: row;
        border-left: none;
        border-top: 1px solid var(--border-color);
    }
    
    .quick-links {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 