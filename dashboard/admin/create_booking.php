<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Create Booking";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Get booking statuses
$query = "SELECT * FROM booking_statuses ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $statuses[] = $row;
    }
}
$stmt->close();

// Get all countries with active visas
$query = "SELECT DISTINCT c.country_id, c.country_name, c.country_code 
          FROM countries c 
          JOIN visas v ON c.country_id = v.country_id 
          WHERE c.is_active = 1 AND v.is_active = 1 
          ORDER BY c.country_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$countries_result = $stmt->get_result();
$countries = [];

if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[] = $row;
    }
}
$stmt->close();

// Get all team members
$query = "SELECT tm.id, u.first_name, u.last_name, tm.role, tm.custom_role_name 
          FROM team_members tm 
          JOIN users u ON tm.user_id = u.id 
          WHERE u.status = 'active' AND u.deleted_at IS NULL 
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[] = $row;
    }
}
$stmt->close();

// Get all clients
$query = "SELECT id, first_name, last_name, email 
          FROM users 
          WHERE user_type = 'applicant' AND status = 'active' AND deleted_at IS NULL 
          ORDER BY first_name, last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = [];

if ($clients_result && $clients_result->num_rows > 0) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}
$stmt->close();

// Check for URL parameters
$prefill_date = isset($_GET['date']) ? $_GET['date'] : '';
$prefill_time = isset($_GET['time']) ? $_GET['time'] : '';
$prefill_duration = isset($_GET['duration']) ? intval($_GET['duration']) : 60;
$prefill_consultant = isset($_GET['consultant']) ? intval($_GET['consultant']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    $user_id = $_POST['user_id'];
    $visa_service_id = $_POST['visa_service_id'];
    $service_consultation_id = $_POST['service_consultation_id'];
    $team_member_id = !empty($_POST['team_member_id']) ? $_POST['team_member_id'] : null;
    $status_id = $_POST['status_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $duration_minutes = $_POST['duration_minutes'];
    $client_notes = trim($_POST['client_notes']);
    $admin_notes = trim($_POST['admin_notes']);
    $location = trim($_POST['location']);
    $meeting_link = trim($_POST['meeting_link']);
    $time_zone = $_POST['time_zone'];
    $language_preference = $_POST['language_preference'];
    
    // Combine date and time
    $booking_datetime = date('Y-m-d H:i:s', strtotime("$booking_date $booking_time"));
    $end_datetime = date('Y-m-d H:i:s', strtotime("$booking_datetime +$duration_minutes minutes"));
    
    // Validate inputs
    $errors = [];
    if (empty($user_id)) {
        $errors[] = "Please select a client";
    }
    if (empty($visa_service_id)) {
        $errors[] = "Please select a visa service";
    }
    if (empty($service_consultation_id)) {
        $errors[] = "Please select a consultation mode";
    }
    if (empty($status_id)) {
        $errors[] = "Please select a status";
    }
    if (empty($booking_date) || empty($booking_time)) {
        $errors[] = "Please select a booking date and time";
    }
    if (empty($duration_minutes) || $duration_minutes <= 0) {
        $errors[] = "Please enter a valid duration";
    }
    
    // Check team member availability if assigned
    if (!empty($team_member_id)) {
        $is_available = false;
        
        // Use the stored procedure to check availability
        $check_availability_query = "CALL check_team_member_availability(?, ?, ?, @is_available)";
        $stmt = $conn->prepare($check_availability_query);
        $stmt->bind_param('iss', $team_member_id, $booking_datetime, $end_datetime);
        $stmt->execute();
        $stmt->close();
        
        // Get the output parameter
        $result = $conn->query("SELECT @is_available AS is_available");
        if ($result && $row = $result->fetch_assoc()) {
            $is_available = (bool)$row['is_available'];
        }
        
        if (!$is_available) {
            $errors[] = "The selected team member is not available at the chosen time";
        }
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert booking
            $insert_query = "INSERT INTO bookings (
                user_id, visa_service_id, service_consultation_id, 
                team_member_id, status_id, booking_datetime, end_datetime, 
                duration_minutes, client_notes, admin_notes, location, meeting_link, 
                time_zone, language_preference
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";
            
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('iiiiiisssssss' . 's', 
                $user_id, $visa_service_id, $service_consultation_id, 
                $team_member_id, $status_id, $booking_datetime, $end_datetime,
                $duration_minutes, $client_notes, $admin_notes, $location, $meeting_link, 
                $time_zone, $language_preference
            );
            
            $stmt->execute();
            $booking_id = $conn->insert_id;
            $stmt->close();
            
            // Get the generated reference number
            $ref_query = "SELECT reference_number FROM bookings WHERE id = ?";
            $stmt = $conn->prepare($ref_query);
            $stmt->bind_param('i', $booking_id);
            $stmt->execute();
            $ref_result = $stmt->get_result();
            if ($ref_row = $ref_result->fetch_assoc()) {
                $reference_number = $ref_row['reference_number'];
            }
            $stmt->close();
            
            // Add activity log
            $activity_type = 'created';
            $description = "Booking {$reference_number} created";
            $log_query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description, ip_address)
                          VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iisss', $booking_id, $_SESSION['user_id'], $activity_type, $description, $ip_address);
            $stmt->execute();
            $stmt->close();
            
            // Create reminders if booking status is pending or confirmed
            $pending_status_id = 0;
            $confirmed_status_id = 0;
            
            // Get the IDs for pending and confirmed status
            foreach ($statuses as $status) {
                if ($status['name'] == 'pending') {
                    $pending_status_id = $status['id'];
                } else if ($status['name'] == 'confirmed') {
                    $confirmed_status_id = $status['id'];
                }
            }
            
            if ($status_id == $pending_status_id || $status_id == $confirmed_status_id) {
                // Email reminder 24 hours before
                $reminder_time = date('Y-m-d H:i:s', strtotime("$booking_datetime -24 hours"));
                $reminder_query = "INSERT INTO booking_reminders (booking_id, reminder_type, scheduled_time)
                                  VALUES (?, 'email', ?)";
                $stmt = $conn->prepare($reminder_query);
                $stmt->bind_param('is', $booking_id, $reminder_time);
                $stmt->execute();
                $stmt->close();
                
                // System notification 1 hour before
                $reminder_time = date('Y-m-d H:i:s', strtotime("$booking_datetime -1 hour"));
                $reminder_query = "INSERT INTO booking_reminders (booking_id, reminder_type, scheduled_time)
                                  VALUES (?, 'system', ?)";
                $stmt = $conn->prepare($reminder_query);
                $stmt->bind_param('is', $booking_id, $reminder_time);
                $stmt->execute();
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Booking created successfully. Reference number: {$reference_number}";
            
            // Redirect to prevent form resubmission
            header("Location: booking_details.php?id=$booking_id&success=1");
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error creating booking: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get all time zones
$time_zones = DateTimeZone::listIdentifiers();

// Get all available languages
$languages = [
    'English', 'Spanish', 'French', 'German', 'Chinese', 'Arabic', 
    'Russian', 'Japanese', 'Portuguese', 'Hindi', 'Urdu'
];
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Create New Booking</h1>
            <p>Schedule a consultation appointment for a client</p>
        </div>
        <div class="action-buttons">
            <a href="bookings.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="country-card">
        <div class="country-header">
            <div class="country-info">
                <h3>Booking Information</h3>
            </div>
        </div>
        <div class="visas-table-container">
            <form action="create_booking.php" method="POST">
                <div class="form-section">
                    <h4>Client & Service Details</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user_id">Client*</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country_id">Country*</label>
                            <select name="country_id" id="country_id" class="form-control" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['country_id']; ?>">
                                        <?php echo htmlspecialchars($country['country_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visa_id">Visa Type*</label>
                            <select name="visa_id" id="visa_id" class="form-control" required disabled>
                                <option value="">Select Visa Type</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="visa_service_id">Service Type*</label>
                            <select name="visa_service_id" id="visa_service_id" class="form-control" required disabled>
                                <option value="">Select Service Type</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="service_consultation_id">Consultation Mode*</label>
                            <select name="service_consultation_id" id="service_consultation_id" class="form-control" required disabled>
                                <option value="">Select Consultation Mode</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="team_member_id">Consultant</label>
                            <select name="team_member_id" id="team_member_id" class="form-control">
                                <option value="">None (Auto-assign later)</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>" <?php echo ($prefill_consultant == $member['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . ($member['custom_role_name'] ?: $member['role']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_id">Status*</label>
                            <select name="status_id" id="status_id" class="form-control" required>
                                <option value="">Select Status</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status['id']; ?>" <?php echo ($status['name'] == 'pending') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($status['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Appointment Date & Time</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking_date">Date*</label>
                            <input type="date" name="booking_date" id="booking_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="booking_time">Time*</label>
                            <input type="time" name="booking_time" id="booking_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <!-- Available time slots will appear here when a team member is selected -->
                    <div id="available-slots-container" style="display: none;" class="mb-3">
                        <div class="availability-heading">
                            <h5>Available Time Slots</h5>
                            <p class="availability-date" id="availability-date"></p>
                        </div>
                        <div class="time-slots-grid" id="time-slots-grid">
                            <!-- Time slots will be populated here -->
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes)*</label>
                            <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" required min="15" step="15" value="60">
                        </div>
                        <div class="form-group">
                            <label for="time_zone">Time Zone*</label>
                            <select name="time_zone" id="time_zone" class="form-control" required>
                                <?php foreach ($time_zones as $tz): ?>
                                    <option value="<?php echo $tz; ?>" <?php echo ($tz == 'UTC') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Additional Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="language_preference">Preferred Language</label>
                            <select name="language_preference" id="language_preference" class="form-control">
                                <option value="English" selected>English</option>
                                <?php foreach ($languages as $language): ?>
                                    <?php if ($language != 'English'): ?>
                                        <option value="<?php echo $language; ?>"><?php echo $language; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Location (for in-person meetings)</label>
                            <input type="text" name="location" id="location" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="meeting_link">Meeting Link (for virtual meetings)</label>
                            <input type="url" name="meeting_link" id="meeting_link" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_notes">Client Notes</label>
                        <textarea name="client_notes" id="client_notes" class="form-control" rows="3" placeholder="Notes from the client about this booking"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes (internal only)</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3" placeholder="Internal notes about this booking"></textarea>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <a href="bookings.php" class="btn cancel-btn">Cancel</a>
                    <button type="submit" name="create_booking" class="btn submit-btn">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

.secondary-btn {
    background-color: var(--secondary-color);
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

.secondary-btn:hover {
    background-color: #7d7f88;
    color: white;
    text-decoration: none;
}

.country-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.country-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.country-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.country-info h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.visas-table-container {
    padding: 20px;
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

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

h4 {
    color: var(--primary-color);
    margin-top: 0;
    margin-bottom: 15px;
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
    min-height: 80px;
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
    text-decoration: none;
}

.cancel-btn:hover {
    background-color: #f8f9fc;
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

.mb-3 {
    margin-bottom: 1rem;
}

/* Availability section styles */
.availability-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.availability-heading h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.availability-date {
    margin: 0;
    color: var(--secondary-color);
    font-weight: 500;
}

.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.time-slot {
    padding: 10px;
    text-align: center;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.time-slot.available {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid rgba(28, 200, 138, 0.3);
    color: var(--success-color);
}

.time-slot.available:hover {
    background-color: rgba(28, 200, 138, 0.2);
}

.time-slot.selected {
    background-color: var(--primary-color);
    color: white;
    border: 1px solid var(--primary-color);
}

.time-slot.unavailable {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid rgba(231, 74, 59, 0.3);
    color: var(--danger-color);
    cursor: not-allowed;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .country-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .time-slots-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }
}
</style>

<script>
// Handle country change to load visa types
document.getElementById('country_id').addEventListener('change', function() {
    const countryId = this.value;
    const visaSelect = document.getElementById('visa_id');
    
    // Reset service-related dropdowns
    document.getElementById('visa_service_id').innerHTML = '<option value="">Select Service Type</option>';
    document.getElementById('visa_service_id').disabled = true;
    document.getElementById('service_consultation_id').innerHTML = '<option value="">Select Consultation Mode</option>';
    document.getElementById('service_consultation_id').disabled = true;
    
    if (countryId) {
        // Enable visa select
        visaSelect.disabled = false;
        
        // Use AJAX to fetch visa types for the selected country
        fetch('ajax/get_visa_types.php?country_id=' + countryId)
            .then(response => response.json())
            .then(data => {
                visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
                
                if (data.length > 0) {
                    data.forEach(function(visa) {
                        const option = document.createElement('option');
                        option.value = visa.visa_id;
                        option.textContent = visa.visa_type;
                        visaSelect.appendChild(option);
                    });
                }
            });
    } else {
        visaSelect.disabled = true;
        visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
    }
});

// Handle visa type change to load service types
document.getElementById('visa_id').addEventListener('change', function() {
    const visaId = this.value;
    const serviceSelect = document.getElementById('visa_service_id');
    
    // Reset consultation mode dropdown
    document.getElementById('service_consultation_id').innerHTML = '<option value="">Select Consultation Mode</option>';
    document.getElementById('service_consultation_id').disabled = true;
    
    if (visaId) {
        // Enable service select
        serviceSelect.disabled = false;
        
        // Use AJAX to fetch service types for the selected visa
        fetch('ajax/get_service_types.php?visa_id=' + visaId)
            .then(response => response.json())
            .then(data => {
                serviceSelect.innerHTML = '<option value="">Select Service Type</option>';
                
                if (data.length > 0) {
                    data.forEach(function(service) {
                        const option = document.createElement('option');
                        option.value = service.visa_service_id;
                        option.textContent = service.service_name;
                        serviceSelect.appendChild(option);
                    });
                }
            });
    } else {
        serviceSelect.disabled = true;
        serviceSelect.innerHTML = '<option value="">Select Service Type</option>';
    }
});

// Handle service type change to load consultation modes
document.getElementById('visa_service_id').addEventListener('change', function() {
    const serviceId = this.value;
    const consultationSelect = document.getElementById('service_consultation_id');
    
    if (serviceId) {
        // Enable consultation mode select
        consultationSelect.disabled = false;
        
        // Use AJAX to fetch consultation modes for the selected service
        fetch('ajax/get_consultation_modes.php?service_id=' + serviceId)
            .then(response => response.json())
            .then(data => {
                consultationSelect.innerHTML = '<option value="">Select Consultation Mode</option>';
                
                if (data.length > 0) {
                    data.forEach(function(mode) {
                        const option = document.createElement('option');
                        option.value = mode.service_consultation_id;
                        option.textContent = mode.mode_name;
                        
                        // Add price info if available
                        if (mode.additional_fee > 0) {
                            option.textContent += ` (+$${parseFloat(mode.additional_fee).toFixed(2)})`;
                        }
                        
                        consultationSelect.appendChild(option);
                    });
                }
            });
    } else {
        consultationSelect.disabled = true;
        consultationSelect.innerHTML = '<option value="">Select Consultation Mode</option>';
    }
});

// Handle team member and date selection to show availability
const teamMemberSelect = document.getElementById('team_member_id');
const bookingDateInput = document.getElementById('booking_date');
const availableSlotsContainer = document.getElementById('available-slots-container');
const availabilityDate = document.getElementById('availability-date');
const timeSlotsGrid = document.getElementById('time-slots-grid');
const bookingTimeInput = document.getElementById('booking_time');
const durationInput = document.getElementById('duration_minutes');

// Function to update availability slots
function updateAvailabilitySlots() {
    const teamMemberId = teamMemberSelect.value;
    const bookingDate = bookingDateInput.value;
    const duration = durationInput.value || 60;
    
    if (teamMemberId && bookingDate) {
        // Show loading state
        availableSlotsContainer.style.display = 'block';
        timeSlotsGrid.innerHTML = '<div class="loading">Loading available time slots...</div>';
        availabilityDate.textContent = new Date(bookingDate).toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Use AJAX to fetch available time slots
        fetch(`ajax/get_available_slots.php?team_member_id=${teamMemberId}&date=${bookingDate}&duration=${duration}`)
            .then(response => response.json())
            .then(data => {
                timeSlotsGrid.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach(slot => {
                        const timeSlot = document.createElement('div');
                        timeSlot.className = `time-slot ${slot.available ? 'available' : 'unavailable'}`;
                        timeSlot.textContent = slot.time;
                        
                        if (slot.available) {
                            timeSlot.addEventListener('click', function() {
                                // Remove selected class from all slots
                                document.querySelectorAll('.time-slot.selected').forEach(el => {
                                    el.classList.remove('selected');
                                });
                                
                                // Add selected class to this slot
                                this.classList.add('selected');
                                
                                // Set the time in the booking time input
                                bookingTimeInput.value = slot.time;
                            });
                        }
                        
                        timeSlotsGrid.appendChild(timeSlot);
                    });
                } else {
                    timeSlotsGrid.innerHTML = '<div class="no-slots">No available time slots for this date</div>';
                }
            })
            .catch(error => {
                timeSlotsGrid.innerHTML = '<div class="error">Error loading time slots</div>';
                console.error('Error fetching time slots:', error);
            });
    } else {
        availableSlotsContainer.style.display = 'none';
    }
}

// Update availability when team member or date changes
teamMemberSelect.addEventListener('change', updateAvailabilitySlots);
bookingDateInput.addEventListener('change', updateAvailabilitySlots);
durationInput.addEventListener('change', updateAvailabilitySlots);

// Handle form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const teamMemberId = teamMemberSelect.value;
    const bookingDate = bookingDateInput.value;
    const bookingTime = bookingTimeInput.value;
    
    if (teamMemberId && (!bookingDate || !bookingTime)) {
        e.preventDefault();
        alert('Please select a date and time for the booking');
    }
});

// Prefill values from URL parameters if they exist
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('date')) {
        bookingDateInput.value = urlParams.get('date');
    }
    
    if (urlParams.has('time')) {
        bookingTimeInput.value = urlParams.get('time');
    }
    
    if (urlParams.has('duration')) {
        durationInput.value = urlParams.get('duration');
    }
    
    // If consultant is prefilled, trigger availability check
    if (urlParams.has('consultant') && urlParams.has('date')) {
        updateAvailabilitySlots();
    }
});
</script>

<!-- Create the AJAX endpoint for getting available slots -->
<?php
// Create the following files if they don't exist
// 1. ajax/get_visa_types.php
// 2. ajax/get_service_types.php
// 3. ajax/get_consultation_modes.php
// 4. ajax/get_available_slots.php (new file needed for availability)
?>

<?php
// End output buffering
ob_end_flush();
?>