<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Booking";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: bookings.php");
    exit;
}

$booking_id = (int)$_GET['id'];

// Get booking details
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color, bs.id as status_id,
          CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email as client_email, u.id as client_id,
          vs.visa_service_id, v.visa_id, v.visa_type, c.country_id, c.country_name, st.service_type_id, st.service_name,
          cm.consultation_mode_id, cm.mode_name as consultation_mode,
          tm.id as team_member_id, CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name,
          vs.base_price, scm.additional_fee, scm.service_consultation_id,
          (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price,
          bp.payment_status, bp.id as payment_id, bp.payment_method
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
          WHERE b.id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found, redirect to bookings list
    $stmt->close();
    header("Location: bookings.php");
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

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

// Get all countries
$query = "SELECT * FROM countries WHERE is_active = 1 ORDER BY country_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$countries_result = $stmt->get_result();
$countries = [];

if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[$row['country_id']] = $row;
    }
}
$stmt->close();

// Get all visa types for selected country
$query = "SELECT * FROM visas WHERE country_id = ? AND is_active = 1 ORDER BY visa_type";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking['country_id']);
$stmt->execute();
$visas_result = $stmt->get_result();
$visas = [];

if ($visas_result && $visas_result->num_rows > 0) {
    while ($row = $visas_result->fetch_assoc()) {
        $visas[$row['visa_id']] = $row;
    }
}
$stmt->close();

// Get service types
$query = "SELECT * FROM service_types WHERE is_active = 1 ORDER BY service_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$service_types_result = $stmt->get_result();
$service_types = [];

if ($service_types_result && $service_types_result->num_rows > 0) {
    while ($row = $service_types_result->fetch_assoc()) {
        $service_types[$row['service_type_id']] = $row;
    }
}
$stmt->close();

// Get visa services for selected visa and service type
$query = "SELECT * FROM visa_services WHERE visa_id = ? AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking['visa_id']);
$stmt->execute();
$visa_services_result = $stmt->get_result();
$visa_services = [];

if ($visa_services_result && $visa_services_result->num_rows > 0) {
    while ($row = $visa_services_result->fetch_assoc()) {
        $visa_services[$row['visa_service_id']] = $row;
    }
}
$stmt->close();

// Get consultation modes for selected visa service
$query = "SELECT scm.*, cm.mode_name, cm.description as mode_description 
          FROM service_consultation_modes scm
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          WHERE scm.visa_service_id = ? AND scm.is_available = 1
          ORDER BY cm.mode_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking['visa_service_id']);
$stmt->execute();
$modes_result = $stmt->get_result();
$consultation_modes = [];

if ($modes_result && $modes_result->num_rows > 0) {
    while ($row = $modes_result->fetch_assoc()) {
        $consultation_modes[$row['service_consultation_id']] = $row;
    }
}
$stmt->close();

// Handle booking update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    // Get form data
    $country_id = isset($_POST['country_id']) ? (int)$_POST['country_id'] : $booking['country_id'];
    $visa_id = isset($_POST['visa_id']) ? (int)$_POST['visa_id'] : $booking['visa_id'];
    $service_type_id = isset($_POST['service_type_id']) ? (int)$_POST['service_type_id'] : $booking['service_type_id'];
    $visa_service_id = isset($_POST['visa_service_id']) ? (int)$_POST['visa_service_id'] : $booking['visa_service_id'];
    $consultation_mode_id = isset($_POST['service_consultation_id']) ? (int)$_POST['service_consultation_id'] : $booking['service_consultation_id'];
    $team_member_id = isset($_POST['team_member_id']) ? (int)$_POST['team_member_id'] : $booking['team_member_id'];
    $status_id = isset($_POST['status_id']) ? (int)$_POST['status_id'] : $booking['status_id'];
    $booking_datetime = isset($_POST['booking_datetime']) ? trim($_POST['booking_datetime']) : $booking['booking_datetime'];
    $duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : $booking['duration_minutes'];
    $client_notes = isset($_POST['client_notes']) ? trim($_POST['client_notes']) : $booking['client_notes'];
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : $booking['location'];
    $meeting_link = isset($_POST['meeting_link']) ? trim($_POST['meeting_link']) : $booking['meeting_link'];
    $time_zone = isset($_POST['time_zone']) ? trim($_POST['time_zone']) : $booking['time_zone'];
    $language_preference = isset($_POST['language_preference']) ? trim($_POST['language_preference']) : $booking['language_preference'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($booking_datetime)) {
        $errors[] = "Booking date and time is required";
    }
    
    if ($duration_minutes < 15) {
        $errors[] = "Duration must be at least 15 minutes";
    }
    
    // If no errors, update booking
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Prepare admin notes with timestamp
            $notes_update = '';
            if (!empty($admin_notes)) {
                $notes_update = ", admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[" . date('Y-m-d H:i:s') . "] " . $admin_notes . "')";
            }
            
            // Update booking
            $update_query = "UPDATE bookings SET 
                            visa_service_id = ?,
                            service_consultation_id = ?,
                            team_member_id = ?,
                            status_id = ?,
                            booking_datetime = ?,
                            duration_minutes = ?,
                            client_notes = ?,
                            location = ?,
                            meeting_link = ?,
                            time_zone = ?,
                            language_preference = ?,
                            updated_at = NOW()
                            $notes_update
                            WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            
            if (!empty($admin_notes)) {
                $stmt->bind_param('iiiisisssssi', 
                               $visa_service_id, 
                               $consultation_mode_id, 
                               $team_member_id, 
                               $status_id, 
                               $booking_datetime, 
                               $duration_minutes,
                               $client_notes,
                               $location,
                               $meeting_link,
                               $time_zone,
                               $language_preference,
                               $booking_id);
            } else {
                $stmt->bind_param('iiiisisssssi', 
                               $visa_service_id, 
                               $consultation_mode_id, 
                               $team_member_id, 
                               $status_id, 
                               $booking_datetime, 
                               $duration_minutes,
                               $client_notes,
                               $location,
                               $meeting_link,
                               $time_zone,
                               $language_preference,
                               $booking_id);
            }
            
            $stmt->execute();
            $stmt->close();
            
            // Add activity log
            $log_query = "INSERT INTO booking_activity_logs 
                         (booking_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'updated', ?)";
            $description = "Booking details updated by admin";
            if (!empty($admin_notes)) {
                $description .= " with notes: " . $admin_notes;
            }
            
            $stmt = $conn->prepare($log_query);
            $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Booking updated successfully";
            header("Location: view_booking.php?id=$booking_id&success=4");
            exit;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error updating booking: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
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
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Booking</h1>
            <p>Update booking details for reference #<?php echo htmlspecialchars($booking['reference_number']); ?></p>
        </div>
        <div>
            <a href="view_booking.php?id=<?php echo $booking_id; ?>" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="booking-form-container">
        <form action="edit_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="editBookingForm">
            <div class="form-section">
                <h3>Client Information</h3>
                <div class="client-info-display">
                    <div class="info-item">
                        <label>Client Name:</label>
                        <span><?php echo htmlspecialchars($booking['client_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($booking['client_email']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Service Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="country_id">Country</label>
                        <select name="country_id" id="country_id" class="form-control" required>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['country_id']; ?>" <?php echo ($country['country_id'] == $booking['country_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country['country_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="visa_id">Visa Type</label>
                        <select name="visa_id" id="visa_id" class="form-control" required>
                            <?php foreach ($visas as $visa): ?>
                                <option value="<?php echo $visa['visa_id']; ?>" <?php echo ($visa['visa_id'] == $booking['visa_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($visa['visa_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_type_id">Service Type</label>
                        <select name="service_type_id" id="service_type_id" class="form-control" required>
                            <?php foreach ($service_types as $service_type): ?>
                                <option value="<?php echo $service_type['service_type_id']; ?>" <?php echo ($service_type['service_type_id'] == $booking['service_type_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service_type['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="visa_service_id">Visa Service</label>
                        <select name="visa_service_id" id="visa_service_id" class="form-control" required>
                            <?php foreach ($visa_services as $visa_service): ?>
                                <option value="<?php echo $visa_service['visa_service_id']; ?>" <?php echo ($visa_service['visa_service_id'] == $booking['visa_service_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($visa_service['description'] ?? $booking['visa_type'] . ' - ' . $booking['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_consultation_id">Consultation Mode</label>
                        <select name="service_consultation_id" id="service_consultation_id" class="form-control" required>
                            <?php foreach ($consultation_modes as $mode): ?>
                                <option value="<?php echo $mode['service_consultation_id']; ?>" <?php echo ($mode['service_consultation_id'] == $booking['service_consultation_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mode['mode_name']); ?> 
                                    <?php if ($mode['additional_fee'] > 0): ?>
                                        (Additional fee: $<?php echo number_format($mode['additional_fee'], 2); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="language_preference">Language</label>
                        <select name="language_preference" id="language_preference" class="form-control">
                            <option value="English" <?php echo ($booking['language_preference'] == 'English') ? 'selected' : ''; ?>>English</option>
                            <option value="Spanish" <?php echo ($booking['language_preference'] == 'Spanish') ? 'selected' : ''; ?>>Spanish</option>
                            <option value="French" <?php echo ($booking['language_preference'] == 'French') ? 'selected' : ''; ?>>French</option>
                            <option value="German" <?php echo ($booking['language_preference'] == 'German') ? 'selected' : ''; ?>>German</option>
                            <option value="Chinese" <?php echo ($booking['language_preference'] == 'Chinese') ? 'selected' : ''; ?>>Chinese</option>
                            <option value="Arabic" <?php echo ($booking['language_preference'] == 'Arabic') ? 'selected' : ''; ?>>Arabic</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Scheduling Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="booking_datetime">Date & Time</label>
                        <input type="datetime-local" name="booking_datetime" id="booking_datetime" class="form-control" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($booking['booking_datetime'])); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_minutes">Duration (minutes)</label>
                        <select name="duration_minutes" id="duration_minutes" class="form-control" required>
                            <option value="15" <?php echo ($booking['duration_minutes'] == 15) ? 'selected' : ''; ?>>15 minutes</option>
                            <option value="30" <?php echo ($booking['duration_minutes'] == 30) ? 'selected' : ''; ?>>30 minutes</option>
                            <option value="45" <?php echo ($booking['duration_minutes'] == 45) ? 'selected' : ''; ?>>45 minutes</option>
                            <option value="60" <?php echo ($booking['duration_minutes'] == 60) ? 'selected' : ''; ?>>60 minutes</option>
                            <option value="90" <?php echo ($booking['duration_minutes'] == 90) ? 'selected' : ''; ?>>90 minutes</option>
                            <option value="120" <?php echo ($booking['duration_minutes'] == 120) ? 'selected' : ''; ?>>120 minutes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_zone">Time Zone</label>
                        <select name="time_zone" id="time_zone" class="form-control" required>
                            <option value="UTC" <?php echo ($booking['time_zone'] == 'UTC') ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($booking['time_zone'] == 'America/New_York') ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                            <option value="America/Chicago" <?php echo ($booking['time_zone'] == 'America/Chicago') ? 'selected' : ''; ?>>Central Time (CT)</option>
                            <option value="America/Denver" <?php echo ($booking['time_zone'] == 'America/Denver') ? 'selected' : ''; ?>>Mountain Time (MT)</option>
                            <option value="America/Los_Angeles" <?php echo ($booking['time_zone'] == 'America/Los_Angeles') ? 'selected' : ''; ?>>Pacific Time (PT)</option>
                            <option value="Europe/London" <?php echo ($booking['time_zone'] == 'Europe/London') ? 'selected' : ''; ?>>London (GMT)</option>
                            <option value="Europe/Paris" <?php echo ($booking['time_zone'] == 'Europe/Paris') ? 'selected' : ''; ?>>Central European Time (CET)</option>
                            <option value="Asia/Dubai" <?php echo ($booking['time_zone'] == 'Asia/Dubai') ? 'selected' : ''; ?>>Dubai (GST)</option>
                            <option value="Asia/Singapore" <?php echo ($booking['time_zone'] == 'Asia/Singapore') ? 'selected' : ''; ?>>Singapore Time (SGT)</option>
                            <option value="Australia/Sydney" <?php echo ($booking['time_zone'] == 'Australia/Sydney') ? 'selected' : ''; ?>>Sydney (AEST)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="team_member_id">Assign Consultant</label>
                        <select name="team_member_id" id="team_member_id" class="form-control">
                            <option value="">Not Assigned</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo ($member['id'] == $booking['team_member_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                                    (<?php echo $member['role'] === 'Custom' ? $member['custom_role_name'] : $member['role']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_id">Status</label>
                        <select name="status_id" id="status_id" class="form-control" required>
                            <?php foreach ($booking_statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo ($status['id'] == $booking['status_id']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group location-group" id="location-container" style="<?php echo $booking['consultation_mode'] === 'In-Person' ? 'display: block;' : 'display: none;'; ?>">
                        <label for="location">Location</label>
                        <textarea name="location" id="location" class="form-control" rows="3"><?php echo htmlspecialchars($booking['location']); ?></textarea>
                    </div>
                    
                    <div class="form-group meeting-link-group" id="meeting-link-container" style="<?php echo $booking['consultation_mode'] === 'Virtual' ? 'display: block;' : 'display: none;'; ?>">
                        <label for="meeting_link">Meeting Link</label>
                        <input type="text" name="meeting_link" id="meeting_link" class="form-control" value="<?php echo htmlspecialchars($booking['meeting_link']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Notes</h3>
                <div class="form-group">
                    <label for="client_notes">Client Notes</label>
                    <textarea name="client_notes" id="client_notes" class="form-control" rows="3"><?php echo htmlspecialchars($booking['client_notes']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="admin_notes">Admin Notes</label>
                    <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3" placeholder="Add new notes here. Previous notes will be preserved."></textarea>
                </div>
            </div>
            
            <div class="form-buttons">
                <a href="view_booking.php?id=<?php echo $booking_id; ?>" class="btn cancel-btn">Cancel</a>
                <button type="submit" name="update_booking" class="btn submit-btn">Update Booking</button>
            </div>
        </form>
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

.primary-btn, .secondary-btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.secondary-btn:hover {
    background-color: var(--light-color);
    color: var(--primary-color);
    text-decoration: none;
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

.booking-form-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    min-width: 200px;
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

textarea.form-control {
    resize: vertical;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 30px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.cancel-btn:hover {
    background-color: #f9f9f9;
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

.client-info-display {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    margin-bottom: 15px;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 4px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item label {
    font-weight: 500;
    color: var(--secondary-color);
    font-size: 0.85rem;
    margin-bottom: 5px;
}

.info-item span {
    font-size: 1rem;
    color: var(--dark-color);
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .form-group {
        width: 100%;
    }
    
    .form-buttons {
        flex-direction: column-reverse;
    }
    
    .info-item {
        width: 100%;
    }
}
</style>

<script>
// Handle consultation mode change
document.getElementById('service_consultation_id').addEventListener('change', function() {
    const selectedMode = this.options[this.selectedIndex].text.toLowerCase();
    const locationContainer = document.getElementById('location-container');
    const meetingLinkContainer = document.getElementById('meeting-link-container');
    
    if (selectedMode.includes('in-person')) {
        locationContainer.style.display = 'block';
        meetingLinkContainer.style.display = 'none';
    } else if (selectedMode.includes('virtual') || selectedMode.includes('online') || selectedMode.includes('video')) {
        locationContainer.style.display = 'none';
        meetingLinkContainer.style.display = 'block';
    } else {
        locationContainer.style.display = 'none';
        meetingLinkContainer.style.display = 'none';
    }
});

// Country selection change handler
document.getElementById('country_id').addEventListener('change', function() {
    const countryId = this.value;
    
    // Fetch visa types for selected country
    fetch('ajax/get_visas.php?country_id=' + countryId)
        .then(response => response.json())
        .then(data => {
            // Update visa dropdown
            const visaSelect = document.getElementById('visa_id');
            visaSelect.innerHTML = '';
            
            data.forEach(visa => {
                const option = document.createElement('option');
                option.value = visa.visa_id;
                option.textContent = visa.visa_type;
                visaSelect.appendChild(option);
            });
            
            // Trigger visa change to update services
            visaSelect.dispatchEvent(new Event('change'));
        })
        .catch(error => console.error('Error fetching visa types:', error));
});

// Visa selection change handler
document.getElementById('visa_id').addEventListener('change', function() {
    const visaId = this.value;
    const serviceTypeId = document.getElementById('service_type_id').value;
    
    // Fetch visa services for selected visa and service type
    fetch(`ajax/get_visa_services.php?visa_id=${visaId}&service_type_id=${serviceTypeId}`)
        .then(response => response.json())
        .then(data => {
            // Update visa services dropdown
            const servicesSelect = document.getElementById('visa_service_id');
            servicesSelect.innerHTML = '';
            
            data.forEach(service => {
                const option = document.createElement('option');
                option.value = service.visa_service_id;
                option.textContent = service.description || `${service.visa_type} - ${service.service_name}`;
                servicesSelect.appendChild(option);
            });
            
            // Trigger service change to update consultation modes
            servicesSelect.dispatchEvent(new Event('change'));
        })
        .catch(error => console.error('Error fetching visa services:', error));
});

// Service type selection change handler
document.getElementById('service_type_id').addEventListener('change', function() {
    const serviceTypeId = this.value;
    const visaId = document.getElementById('visa_id').value;
    
    // Fetch visa services for selected visa and service type
    fetch(`ajax/get_visa_services.php?visa_id=${visaId}&service_type_id=${serviceTypeId}`)
        .then(response => response.json())
        .then(data => {
            // Update visa services dropdown
            const servicesSelect = document.getElementById('visa_service_id');
            servicesSelect.innerHTML = '';
            
            data.forEach(service => {
                const option = document.createElement('option');
                option.value = service.visa_service_id;
                option.textContent = service.description || `${service.visa_type} - ${service.service_name}`;
                servicesSelect.appendChild(option);
            });
            
            // Trigger service change to update consultation modes
            servicesSelect.dispatchEvent(new Event('change'));
        })
        .catch(error => console.error('Error fetching visa services:', error));
});

// Visa service selection change handler
document.getElementById('visa_service_id').addEventListener('change', function() {
    const visaServiceId = this.value;
    
    // Fetch consultation modes for selected visa service
    fetch('ajax/get_consultation_modes.php?visa_service_id=' + visaServiceId)
        .then(response => response.json())
        .then(data => {
            // Update consultation modes dropdown
            const modesSelect = document.getElementById('service_consultation_id');
            modesSelect.innerHTML = '';
            
            data.forEach(mode => {
                const option = document.createElement('option');
                option.value = mode.service_consultation_id;
                option.textContent = `${mode.mode_name}${mode.additional_fee > 0 ? ' (Additional fee: $' + mode.additional_fee.toFixed(2) + ')' : ''}`;
                modesSelect.appendChild(option);
            });
            
            // Trigger consultation mode change
            modesSelect.dispatchEvent(new Event('change'));
        })
        .catch(error => console.error('Error fetching consultation modes:', error));
});

// Form validation
document.getElementById('editBookingForm').addEventListener('submit', function(e) {
    const bookingDateTime = document.getElementById('booking_datetime').value;
    const teamMember = document.getElementById('team_member_id').value;
    const consultationMode = document.getElementById('service_consultation_id');
    const selectedMode = consultationMode.options[consultationMode.selectedIndex].text.toLowerCase();
    const location = document.getElementById('location').value;
    const meetingLink = document.getElementById('meeting_link').value;
    
    let isValid = true;
    let errorMessage = '';
    
    // Validate date/time
    if (!bookingDateTime) {
        isValid = false;
        errorMessage += 'Please select a date and time.\n';
    }
    
    // Validate location if in-person
    if (selectedMode.includes('in-person') && !location.trim()) {
        isValid = false;
        errorMessage += 'Please provide a location for the in-person consultation.\n';
    }
    
    // Validate meeting link if virtual
    if ((selectedMode.includes('virtual') || selectedMode.includes('online') || selectedMode.includes('video')) && !meetingLink.trim()) {
        isValid = false;
        errorMessage += 'Please provide a meeting link for the virtual consultation.\n';
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please correct the following errors:\n\n' + errorMessage);
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
