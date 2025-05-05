<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Lead Details";
$page_specific_css = "assets/css/lead_details.css";
require_once 'includes/header.php';

// Check if lead ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: leads.php?error=1&message=" . urlencode("Lead ID is required"));
    exit;
}

$lead_id = $_GET['id'];

// Get lead details - Using prepared statement with only existing columns
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.email_verified, u.status, 
          u.created_at, u.profile_picture, u.auth_provider, u.user_type,
          COUNT(DISTINCT a.id) as application_count,
          COUNT(DISTINCT b.id) as booking_count
          FROM users u
          LEFT JOIN applications a ON u.id = a.user_id AND a.deleted_at IS NULL
          LEFT JOIN bookings b ON u.id = b.user_id AND b.deleted_at IS NULL
          WHERE u.id = ? AND u.user_type = 'applicant' AND u.deleted_at IS NULL
          GROUP BY u.id";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $lead_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $lead = $result->fetch_assoc();
} else {
    header("Location: leads.php?error=2&message=" . urlencode("Lead not found"));
    exit;
}
$stmt->close();

// Get applications for this lead
$app_query = "SELECT a.id, a.reference_number, v.visa_type, c.country_name, 
              s.name AS status, s.color AS status_color, a.created_at, a.updated_at
              FROM applications a
              JOIN visas v ON a.visa_id = v.visa_id
              JOIN countries c ON v.country_id = c.country_id
              JOIN application_statuses s ON a.status_id = s.id
              WHERE a.user_id = ? AND a.deleted_at IS NULL
              ORDER BY a.updated_at DESC";
$app_stmt = $conn->prepare($app_query);
$app_stmt->bind_param('i', $lead_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();
$applications = [];

if ($app_result && $app_result->num_rows > 0) {
    while ($row = $app_result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$app_stmt->close();

// Get bookings for this lead
$booking_query = "SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, 
                 bs.name as status_name, bs.color as status_color,
                 vs.visa_service_id, v.visa_type, c.country_name, st.service_name, 
                 cm.mode_name as consultation_mode
                 FROM bookings b
                 JOIN booking_statuses bs ON b.status_id = bs.id
                 JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                 JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
                 JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                 JOIN visas v ON vs.visa_id = v.visa_id
                 JOIN countries c ON v.country_id = c.country_id
                 JOIN service_types st ON vs.service_type_id = st.service_type_id
                 WHERE b.user_id = ? AND b.deleted_at IS NULL
                 ORDER BY b.booking_datetime DESC";
$booking_stmt = $conn->prepare($booking_query);
$booking_stmt->bind_param('i', $lead_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$bookings = [];

if ($booking_result && $booking_result->num_rows > 0) {
    while ($row = $booking_result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$booking_stmt->close();

// Handle lead status toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $new_status = $_POST['new_status'];
    
    // Update status
    $update_query = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('si', $new_status, $lead_id);
    
    if ($stmt->execute()) {
        $status_message = ($new_status === 'active') ? "Lead activated successfully" : "Lead deactivated successfully";
        $stmt->close();
        header("Location: lead_details.php?id=$lead_id&success=1&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating lead status: " . $conn->error;
        $stmt->close();
    }
}

// Handle lead deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lead'])) {
    // Soft delete the lead
    $delete_query = "UPDATE users SET deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $lead_id);
    
    if ($stmt->execute()) {
        $success_message = "Lead deleted successfully";
        $stmt->close();
        header("Location: leads.php?success=2");
        exit;
    } else {
        $error_message = "Error deleting lead: " . $conn->error;
        $stmt->close();
    }
}

// Handle converting lead to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_client'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // We don't need to change the user_type since clients.php looks for applicants with bookings
        // Instead, check if we need to create a dummy booking to make this user appear in clients list
        $check_booking_query = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND deleted_at IS NULL";
        $check_stmt = $conn->prepare($check_booking_query);
        $check_stmt->bind_param('i', $lead_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        // If user has no bookings, create a dummy entry to make them show up in clients.php
        if ($check_result['count'] == 0) {
            // Get an active visa service
            $service_query = "SELECT vs.visa_service_id, scm.service_consultation_id 
                             FROM visa_services vs 
                             JOIN service_consultation_modes scm ON vs.visa_service_id = scm.visa_service_id
                             WHERE vs.is_active = 1 AND scm.is_available = 1
                             LIMIT 1";
            $service_stmt = $conn->prepare($service_query);
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();
            
            if ($service_result->num_rows > 0) {
                $service = $service_result->fetch_assoc();
                $visa_service_id = $service['visa_service_id'];
                $service_consultation_id = $service['service_consultation_id'];
                $service_stmt->close();
                
                // Get pending status ID
                $status_query = "SELECT id FROM booking_statuses WHERE name = 'pending' LIMIT 1";
                $status_stmt = $conn->prepare($status_query);
                $status_stmt->execute();
                $status_id = $status_stmt->get_result()->fetch_assoc()['id'];
                $status_stmt->close();
                
                // Generate reference number
                $ref_number = "BK" . time() . rand(1000, 9999);
                
                // Set booking date to tomorrow
                $booking_date = date('Y-m-d H:i:s', strtotime('+1 day'));
                $end_date = date('Y-m-d H:i:s', strtotime('+1 day +30 minutes'));
                
                // Insert dummy booking
                $booking_query = "INSERT INTO bookings (reference_number, user_id, visa_service_id, 
                                 service_consultation_id, status_id, booking_datetime, end_datetime, 
                                 created_by, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $booking_stmt = $conn->prepare($booking_query);
                $booking_stmt->bind_param('siiisssi', $ref_number, $lead_id, $visa_service_id, 
                                        $service_consultation_id, $status_id, $booking_date, $end_date, 
                                        $_SESSION['id']);
                $booking_stmt->execute();
                $booking_stmt->close();
            }
        }
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Lead converted to client successfully";
        header("Location: leads.php?success=3");
        exit;
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        $error_message = "Error converting lead to client: " . $e->getMessage();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    $success_message = isset($_GET['message']) ? $_GET['message'] : "Operation completed successfully";
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <div class="back-link">
                <a href="leads.php"><i class="fas fa-arrow-left"></i> Back to Leads</a>
            </div>
            <h1>Lead Details</h1>
        </div>
        <div class="action-buttons">
            <a href="send_message.php?id=<?php echo $lead_id; ?>" class="btn action-btn">
                <i class="fas fa-envelope"></i> Send Message
            </a>
            
            <?php if ($lead['status'] === 'active'): ?>
                <button type="button" class="btn action-btn btn-danger" 
                        onclick="toggleLeadStatus(<?php echo $lead_id; ?>, 'suspended')">
                    <i class="fas fa-user-slash"></i> Deactivate
                </button>
            <?php else: ?>
                <button type="button" class="btn action-btn btn-success" 
                        onclick="toggleLeadStatus(<?php echo $lead_id; ?>, 'active')">
                    <i class="fas fa-user-check"></i> Activate
                </button>
            <?php endif; ?>
            
            <button type="button" class="btn action-btn btn-primary" 
                    onclick="convertToClient(<?php echo $lead_id; ?>)">
                <i class="fas fa-user-graduate"></i> Convert to Client
            </button>
            
            <button type="button" class="btn action-btn btn-danger" 
                    onclick="confirmDeleteLead(<?php echo $lead_id; ?>)">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="lead-details-container">
        <!-- Lead Profile Section -->
        <div class="lead-profile">
            <div class="profile-header">
                <div class="profile-image">
                    <?php if (!empty($lead['profile_picture']) && file_exists('../../uploads/profiles/' . $lead['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo $lead['profile_picture']; ?>" alt="Profile picture">
                    <?php else: ?>
                        <div class="initials">
                            <?php echo substr($lead['first_name'], 0, 1) . substr($lead['last_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']); ?></h2>
                    <div class="profile-meta">
                        <span class="meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lead['email']); ?>
                        </span>
                    </div>
                    <div class="profile-badges">
                        <?php if ($lead['status'] === 'active'): ?>
                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                        <?php else: ?>
                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Suspended</span>
                        <?php endif; ?>
                        
                        <?php if ($lead['email_verified']): ?>
                            <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php else: ?>
                            <span class="status-badge unverified"><i class="fas fa-times-circle"></i> Unverified</span>
                        <?php endif; ?>
                        
                        <?php if ($lead['auth_provider'] === 'google'): ?>
                            <span class="provider-badge google"><i class="fab fa-google"></i> Google</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-section">
                    <h3>Account Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Registered Date</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y', strtotime($lead['created_at'])); ?>
                                <span class="detail-subtext">
                                    <?php 
                                        $created_date = new DateTime($lead['created_at']);
                                        $now = new DateTime();
                                        $interval = $created_date->diff($now);
                                        
                                        if ($interval->y > 0) {
                                            echo $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->m > 0) {
                                            echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->d > 0) {
                                            echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->h > 0) {
                                            echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                        } else {
                                            echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Last Login</div>
                            <div class="detail-value">
                                <?php if (!empty($lead['last_login_at'])): ?>
                                    <?php echo date('M d, Y g:i A', strtotime($lead['last_login_at'])); ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Account Status</div>
                            <div class="detail-value">
                                <?php if ($lead['status'] === 'active'): ?>
                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Suspended</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Email Verification</div>
                            <div class="detail-value">
                                <?php if ($lead['email_verified']): ?>
                                    <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="status-badge unverified"><i class="fas fa-times-circle"></i> Unverified</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Authentication Provider</div>
                            <div class="detail-value">
                                <?php if ($lead['auth_provider'] === 'google'): ?>
                                    <span class="provider-badge google"><i class="fab fa-google"></i> Google</span>
                                <?php else: ?>
                                    <span class="provider-badge local"><i class="fas fa-lock"></i> Local</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">User Type</div>
                            <div class="detail-value">
                                <span class="status-badge applicant"><i class="fas fa-user"></i> Applicant</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Contact Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($lead['email']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Contact Information</div>
                            <div class="detail-value">
                                No additional contact information available
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Activity Summary</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Applications</div>
                            <div class="detail-value">
                                <div class="count"><?php echo $lead['application_count']; ?></div>
                                <?php if ($lead['application_count'] > 0): ?>
                                    <a href="#applications" class="view-link">View Applications</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Bookings</div>
                            <div class="detail-value">
                                <div class="count"><?php echo $lead['booking_count']; ?></div>
                                <?php if ($lead['booking_count'] > 0): ?>
                                    <a href="#bookings" class="view-link">View Bookings</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lead Applications Section -->
        <?php if (!empty($applications)): ?>
            <div id="applications" class="lead-applications">
                <h3><i class="fas fa-folder-open"></i> Applications (<?php echo count($applications); ?>)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Visa Type</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['reference_number']); ?></td>
                                    <td><?php echo htmlspecialchars($app['visa_type']); ?></td>
                                    <td><?php echo htmlspecialchars($app['country_name']); ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $app['status_color']; ?>10; color: <?php echo $app['status_color']; ?>;">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['updated_at'])); ?></td>
                                    <td>
                                        <a href="application_details.php?id=<?php echo $app['id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Lead Bookings Section -->
        <?php if (!empty($bookings)): ?>
            <div id="bookings" class="lead-bookings">
                <h3><i class="fas fa-calendar-alt"></i> Bookings (<?php echo count($bookings); ?>)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Service</th>
                                <th>Consultation</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                        <div class="subtext"><?php echo htmlspecialchars($booking['visa_type'] . ' - ' . $booking['country_name']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                                    <td>
                                        <?php 
                                            $booking_date = new DateTime($booking['booking_datetime']);
                                            echo $booking_date->format('M d, Y g:i A'); 
                                            
                                            $end_date = new DateTime($booking['end_datetime']);
                                            $duration = $booking_date->diff($end_date);
                                            $minutes = ($duration->h * 60) + $duration->i;
                                        ?>
                                        <div class="subtext"><?php echo $minutes; ?> minutes</div>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                            <?php echo $booking['status_name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="statusToggleForm" action="lead_details.php?id=<?php echo $lead_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="new_status" id="new_lead_status">
    <input type="hidden" name="toggle_status" value="1">
</form>

<form id="deleteLeadForm" action="lead_details.php?id=<?php echo $lead_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="delete_lead" value="1">
</form>

<form id="convertClientForm" action="lead_details.php?id=<?php echo $lead_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="convert_to_client" value="1">
</form>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
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

.back-link {
    margin-bottom: 10px;
}

.back-link a {
    color: var(--secondary-color);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.back-link a:hover {
    color: var(--primary-color);
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border-radius: 4px;
    color: white;
    border: none;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
}

.action-btn:hover {
    text-decoration: none;
    color: white;
}

.btn-primary {
    background-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-success {
    background-color: var(--success-color);
}

.btn-success:hover {
    background-color: #18b07b;
}

.btn-danger {
    background-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: #d44235;
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

.lead-details-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.lead-profile {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.profile-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-image .initials {
    color: white;
    font-weight: 600;
    font-size: 28px;
}

.profile-info {
    flex: 1;
}

.profile-info h2 {
    margin: 0 0 5px;
    font-size: 1.5rem;
    color: var(--dark-color);
}

.profile-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.profile-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.status-badge, .provider-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge.verified {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.unverified {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.applicant {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.provider-badge {
    background-color: white;
    border: 1px solid var(--border-color);
}

.provider-badge.google {
    color: #DB4437;
    border-color: #DB4437;
}

.provider-badge.local {
    color: var(--dark-color);
    border-color: var(--border-color);
}

.profile-details {
    padding: 20px;
}

.detail-section {
    margin-bottom: 30px;
}

.detail-section h3 {
    margin: 0 0 15px;
    color: var(--primary-color);
    font-size: 1.1rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-label {
    font-size: 0.85rem;
    color: var(--secondary-color);
    font-weight: 500;
}

.detail-value {
    font-size: 0.95rem;
    color: var(--dark-color);
}

.detail-subtext {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-left: 5px;
}

.count {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
}

.view-link {
    font-size: 0.85rem;
    color: var(--primary-color);
    text-decoration: none;
}

.view-link:hover {
    text-decoration: underline;
}

.lead-applications, .lead-bookings {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.lead-applications h3, .lead-bookings h3 {
    margin: 0 0 15px;
    color: var(--primary-color);
    font-size: 1.1rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.data-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.data-table .subtext {
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-top: 3px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .profile-badges {
        justify-content: center;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: space-between;
    }
    
    .action-btn {
        padding: 8px 12px;
        font-size: 0.85rem;
    }
}
</style>

<script>
// Function to toggle lead status
function toggleLeadStatus(leadId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this lead?`)) {
        document.getElementById('new_lead_status').value = newStatus;
        document.getElementById('statusToggleForm').submit();
    }
}

// Function to confirm lead deletion
function confirmDeleteLead(leadId) {
    if (confirm('Are you sure you want to delete this lead? This action cannot be undone.')) {
        document.getElementById('deleteLeadForm').submit();
    }
}

// Function to convert lead to client
function convertToClient(leadId) {
    if (confirm('Are you sure you want to convert this lead to a client?')) {
        document.getElementById('convertClientForm').submit();
    }
}

// Smooth scrolling for anchor links
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
