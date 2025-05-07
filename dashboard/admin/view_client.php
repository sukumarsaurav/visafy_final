<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Client Details";
$page_specific_css = "assets/css/view_client.css";
require_once 'includes/header.php';

// Check if client ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to clients list if no ID provided
    header("Location: clients.php");
    exit;
}

$client_id = (int)$_GET['id'];

// Get client details
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, 
           u.email_verified, u.created_at, u.auth_provider
          FROM users u
          WHERE u.id = ? AND u.deleted_at IS NULL AND u.user_type = 'applicant'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Client not found, redirect to clients list
    $stmt->close();
    header("Location: clients.php");
    exit;
}

$client = $result->fetch_assoc();
$stmt->close();

// Get booking statistics
$query = "SELECT COUNT(*) as total_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'completed') THEN 1 ELSE 0 END) as completed_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'cancelled_by_user') THEN 1 ELSE 0 END) as cancelled_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'pending') THEN 1 ELSE 0 END) as pending_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'confirmed') THEN 1 ELSE 0 END) as upcoming_bookings,
          MAX(b.booking_datetime) as last_booking_date
          FROM bookings b
          WHERE b.user_id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$booking_stats = $stats_result->fetch_assoc();
$stmt->close();

// Get last 5 bookings
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
          WHERE b.user_id = ? AND b.deleted_at IS NULL
          ORDER BY b.booking_datetime DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$bookings_result = $stmt->get_result();
$recent_bookings = [];

if ($bookings_result && $bookings_result->num_rows > 0) {
    while ($row = $bookings_result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
}
$stmt->close();

// Handle account status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['deactivate_client'])) {
        // Update status to suspended
        $update_query = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('i', $client_id);
        
        if ($stmt->execute()) {
            $client['status'] = 'suspended';
            $success_message = "Client account deactivated successfully";
            $stmt->close();
        } else {
            $error_message = "Error deactivating client account: " . $conn->error;
            $stmt->close();
        }
    } elseif (isset($_POST['reactivate_client'])) {
        // Update status to active
        $update_query = "UPDATE users SET status = 'active' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('i', $client_id);
        
        if ($stmt->execute()) {
            $client['status'] = 'active';
            $success_message = "Client account reactivated successfully";
            $stmt->close();
        } else {
            $error_message = "Error reactivating client account: " . $conn->error;
            $stmt->close();
        }
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h1>
            <p>Client Details</p>
        </div>
        <div class="action-buttons">
            <a href="clients.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Clients
            </a>
            <a href="edit_client.php?id=<?php echo $client_id; ?>" class="btn-edit">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <?php if ($client['status'] === 'active'): ?>
                <form action="view_client.php?id=<?php echo $client_id; ?>" method="POST" class="inline-form">
                    <button type="submit" name="deactivate_client" class="btn-deactivate" 
                            onclick="return confirm('Are you sure you want to deactivate this client account?')">
                        <i class="fas fa-user-slash"></i> Deactivate Account
                    </button>
                </form>
            <?php else: ?>
                <form action="view_client.php?id=<?php echo $client_id; ?>" method="POST" class="inline-form">
                    <button type="submit" name="reactivate_client" class="btn-activate">
                        <i class="fas fa-user-check"></i> Activate Account
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="client-info-grid">
        <!-- Client Profile Section -->
        <div class="card client-profile">
            <div class="profile-header">
                <div class="profile-picture">
                    <?php if (!empty($client['profile_picture']) && file_exists('../../uploads/profiles/' . $client['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo $client['profile_picture']; ?>" alt="Profile picture">
                    <?php else: ?>
                        <div class="profile-initials">
                            <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="status-indicator">
                    <?php if ($client['status'] === 'active'): ?>
                        <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-details">
                <h3><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h3>
                
                <div class="detail-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email:</span>
                    <span class="value">
                        <?php echo htmlspecialchars($client['email']); ?>
                        <?php if (!$client['email_verified']): ?>
                            <span class="verification-status" title="Email not verified"><i class="fas fa-exclamation-triangle"></i></span>
                        <?php else: ?>
                            <span class="verification-status verified" title="Email verified"><i class="fas fa-check-circle"></i></span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="label"><i class="fas fa-sign-in-alt"></i> Auth Provider:</span>
                    <span class="value">
                        <?php 
                        if ($client['auth_provider'] === 'google') {
                            echo 'Google Account';
                        } else {
                            echo 'Email & Password';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar-alt"></i> Member Since:</span>
                    <span class="value"><?php echo date('F d, Y', strtotime($client['created_at'])); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar-check"></i> Last Booking:</span>
                    <span class="value">
                        <?php echo !empty($booking_stats['last_booking_date']) ? date('F d, Y', strtotime($booking_stats['last_booking_date'])) : 'No bookings'; ?>
                    </span>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="client_bookings.php?client_id=<?php echo $client_id; ?>" class="btn-secondary">
                    <i class="fas fa-calendar-alt"></i> View All Bookings
                </a>
                <a href="send_message.php?client_id=<?php echo $client_id; ?>" class="btn-secondary">
                    <i class="fas fa-envelope"></i> Send Message
                </a>
            </div>
        </div>
        
        <!-- Booking Statistics Section -->
        <div class="card booking-stats">
            <h3>Booking Statistics</h3>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo intval($booking_stats['total_bookings'] ?? 0); ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-value"><?php echo intval($booking_stats['upcoming_bookings'] ?? 0); ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-value"><?php echo intval($booking_stats['completed_bookings'] ?? 0); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-value"><?php echo intval($booking_stats['cancelled_bookings'] ?? 0); ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings Section -->
        <div class="card recent-bookings">
            <div class="card-header">
                <h3>Recent Bookings</h3>
                <a href="client_bookings.php?client_id=<?php echo $client_id; ?>" class="view-all">View All</a>
            </div>
            
            <?php if (empty($recent_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No bookings found for this client.</p>
                </div>
            <?php else: ?>
                <div class="bookings-list">
                    <?php foreach ($recent_bookings as $booking): ?>
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
                                <h4><?php echo htmlspecialchars($booking['service_name'] . ' (' . $booking['visa_type'] . ' - ' . $booking['country_name'] . ')'); ?></h4>
                                <p class="booking-mode">
                                    <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($booking['consultation_mode']); ?>
                                </p>
                                <p class="booking-ref">
                                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($booking['reference_number']); ?>
                                </p>
                                <p class="booking-status">
                                    <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                                    </span>
                                </p>
                            </div>
                            
                            <div class="booking-actions">
                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Client Notes Section -->
        <div class="card client-notes">
            <h3>Admin Notes</h3>
            
            <div class="notes-form">
                <form action="save_client_note.php" method="POST">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <textarea name="note_content" placeholder="Add a note about this client..."></textarea>
                    <button type="submit" class="btn-save-note">
                        <i class="fas fa-save"></i> Save Note
                    </button>
                </form>
            </div>
            
            <div class="notes-list">
                <div class="empty-state">
                    <i class="fas fa-sticky-note"></i>
                    <p>No notes added yet.</p>
                </div>
                
                <!-- Notes will appear here -->
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

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-back, .btn-edit, .btn-deactivate, .btn-activate {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-back {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-edit {
    background-color: var(--primary-color);
    color: white;
}

.btn-deactivate {
    background-color: var(--danger-color);
    color: white;
}

.btn-activate {
    background-color: var(--success-color);
    color: white;
}

.inline-form {
    display: inline;
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

.client-info-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 20px;
}

.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.client-profile {
    grid-column: span 4;
}

.booking-stats {
    grid-column: span 8;
}

.recent-bookings {
    grid-column: span 8;
}

.client-notes {
    grid-column: span 4;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.profile-picture {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-initials {
    color: white;
    font-weight: 600;
    font-size: 36px;
}

.status-indicator {
    align-self: flex-start;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge i {
    font-size: 8px;
}

.profile-details h3 {
    margin: 0 0 15px;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
    font-size: 14px;
}

.detail-row .label {
    width: 130px;
    font-weight: 600;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-row .value {
    flex: 1;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.verification-status {
    color: var(--warning-color);
    font-size: 14px;
}

.verification-status.verified {
    color: var(--success-color);
}

.profile-actions {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    text-decoration: none;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    text-align: center;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 15px;
}

.stat-box {
    text-align: center;
    padding: 15px;
    border-radius: 5px;
    background-color: var(--light-color);
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--secondary-color);
    font-size: 14px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 14px;
}

.view-all:hover {
    text-decoration: underline;
}

.bookings-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.booking-card {
    display: flex;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
}

.booking-date {
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    text-align: center;
}

.date {
    display: flex;
    flex-direction: column;
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

.booking-details h4 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 16px;
}

.booking-details p {
    margin: 5px 0;
    font-size: 14px;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.booking-actions {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: var(--light-color);
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 14px;
    color: white;
    text-decoration: none;
    background-color: var(--primary-color);
}

.notes-form {
    margin-bottom: 20px;
}

.notes-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-height: 100px;
    resize: vertical;
    margin-bottom: 10px;
}

.btn-save-note {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    background-color: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    float: right;
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
}

@media (max-width: 992px) {
    .client-info-grid {
        grid-template-columns: 1fr;
    }
    
    .client-profile, .booking-stats, .recent-bookings, .client-notes {
        grid-column: 1;
    }
    
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
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
        align-items: center;
        gap: 10px;
        margin-bottom: 0;
    }
    
    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .status-indicator {
        margin-top: 10px;
    }
    
    .detail-row {
        flex-direction: column;
        margin-bottom: 15px;
    }
    
    .detail-row .label {
        width: 100%;
        margin-bottom: 5px;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
