<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Service";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Check if service ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: services.php");
    exit;
}

$service_id = intval($_GET['id']);

// Get service details
$query = "SELECT vs.*, v.visa_type, v.country_id, c.country_name, c.country_code, st.service_name 
          FROM visa_services vs 
          JOIN visas v ON vs.visa_id = v.visa_id 
          JOIN countries c ON v.country_id = c.country_id 
          JOIN service_types st ON vs.service_type_id = st.service_type_id 
          WHERE vs.visa_service_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: services.php");
    exit;
}

$service = $result->fetch_assoc();
$stmt->close();

// Get all service types
$query = "SELECT * FROM service_types WHERE is_active = 1 ORDER BY service_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$service_types_result = $stmt->get_result();
$service_types = [];

if ($service_types_result && $service_types_result->num_rows > 0) {
    while ($row = $service_types_result->fetch_assoc()) {
        $service_types[] = $row;
    }
}
$stmt->close();

// Get all visas for the country
$query = "SELECT visa_id, visa_type FROM visas WHERE country_id = ? AND is_active = 1 ORDER BY visa_type";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service['country_id']);
$stmt->execute();
$visas_result = $stmt->get_result();
$visas = [];

if ($visas_result && $visas_result->num_rows > 0) {
    while ($row = $visas_result->fetch_assoc()) {
        $visas[] = $row;
    }
}
$stmt->close();

// Get all consultation modes
$query = "SELECT * FROM consultation_modes WHERE is_active = 1 ORDER BY mode_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$consultation_modes_result = $stmt->get_result();
$consultation_modes = [];

if ($consultation_modes_result && $consultation_modes_result->num_rows > 0) {
    while ($row = $consultation_modes_result->fetch_assoc()) {
        $consultation_modes[] = $row;
    }
}
$stmt->close();

// Get current consultation modes for this service
$query = "SELECT scm.*, cm.mode_name 
          FROM service_consultation_modes scm
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          WHERE scm.visa_service_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$service_modes_result = $stmt->get_result();
$service_modes = [];

if ($service_modes_result && $service_modes_result->num_rows > 0) {
    while ($row = $service_modes_result->fetch_assoc()) {
        $service_modes[$row['consultation_mode_id']] = $row;
    }
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $visa_id = intval($_POST['visa_id']);
    $service_type_id = intval($_POST['service_type_id']);
    $base_price = floatval($_POST['base_price']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($visa_id)) {
        $errors[] = "Visa type is required";
    }
    if (empty($service_type_id)) {
        $errors[] = "Service type is required";
    }
    if (empty($base_price) || $base_price < 0) {
        $errors[] = "Valid base price is required";
    }
    
    if (empty($errors)) {
        // Check if visa service combination already exists (excluding current service)
        $check_query = "SELECT visa_service_id FROM visa_services 
                       WHERE visa_id = ? AND service_type_id = ? AND visa_service_id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('iii', $visa_id, $service_type_id, $service_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "This visa service combination already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update visa service
            $update_query = "UPDATE visa_services SET 
                             visa_id = ?, 
                             service_type_id = ?, 
                             base_price = ?, 
                             description = ?, 
                             is_active = ?,
                             updated_at = NOW() 
                             WHERE visa_service_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('iidsii', $visa_id, $service_type_id, $base_price, $description, $is_active, $service_id);
            $stmt->execute();
            $stmt->close();
            
            // Remove existing consultation modes
            $delete_query = "DELETE FROM service_consultation_modes WHERE visa_service_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param('i', $service_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Add selected consultation modes
            if (isset($_POST['consultation_modes']) && is_array($_POST['consultation_modes'])) {
                foreach ($_POST['consultation_modes'] as $mode_id) {
                    $additional_fee = isset($_POST['fee_'.$mode_id]) ? floatval($_POST['fee_'.$mode_id]) : 0;
                    $duration = isset($_POST['duration_'.$mode_id]) ? intval($_POST['duration_'.$mode_id]) : NULL;
                    
                    $mode_query = "INSERT INTO service_consultation_modes 
                                  (visa_service_id, consultation_mode_id, additional_fee, duration_minutes, is_available) 
                                  VALUES (?, ?, ?, ?, 1)";
                    $mode_stmt = $conn->prepare($mode_query);
                    $mode_stmt->bind_param('iidi', $service_id, $mode_id, $additional_fee, $duration);
                    $mode_stmt->execute();
                    $mode_stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Service updated successfully";
            
            // Redirect to prevent form resubmission
            header("Location: edit_service.php?id=$service_id&success=1");
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error updating service: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Service updated successfully";
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Service</h1>
            <p>Edit details for: <?php echo htmlspecialchars($service['service_name']); ?> - <?php echo htmlspecialchars($service['visa_type']); ?></p>
        </div>
        <div class="action-buttons">
            <a href="services.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Services
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
                <h3>Service Information</h3>
            </div>
        </div>
        <div class="visas-table-container">
            <form action="edit_service.php?id=<?php echo $service_id; ?>" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="visa_id">Visa Type*</label>
                        <select name="visa_id" id="visa_id" class="form-control" required>
                            <?php foreach ($visas as $visa): ?>
                                <option value="<?php echo $visa['visa_id']; ?>" <?php echo ($visa['visa_id'] == $service['visa_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($visa['visa_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="service_type_id">Service Type*</label>
                        <select name="service_type_id" id="service_type_id" class="form-control" required>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo $type['service_type_id']; ?>" <?php echo ($type['service_type_id'] == $service['service_type_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="base_price">Base Price ($)*</label>
                        <input type="number" name="base_price" id="base_price" class="form-control" step="0.01" min="0" value="<?php echo $service['base_price']; ?>" required>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active">Active</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($service['description']); ?></textarea>
                </div>
                
                <h4>Consultation Modes</h4>
                <div class="consultation-modes-container">
                    <?php if (empty($consultation_modes)): ?>
                        <p class="notice">No consultation modes available. Please add consultation modes first.</p>
                    <?php else: ?>
                        <?php foreach ($consultation_modes as $mode): ?>
                            <?php 
                                $isSelected = isset($service_modes[$mode['consultation_mode_id']]);
                                $additionalFee = $isSelected ? $service_modes[$mode['consultation_mode_id']]['additional_fee'] : 0;
                                $duration = $isSelected ? $service_modes[$mode['consultation_mode_id']]['duration_minutes'] : '';
                            ?>
                            <div class="consultation-mode-item">
                                <div class="mode-checkbox">
                                    <input type="checkbox" name="consultation_modes[]" id="mode_<?php echo $mode['consultation_mode_id']; ?>" 
                                           value="<?php echo $mode['consultation_mode_id']; ?>" 
                                           class="consultation-mode-checkbox" <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <label for="mode_<?php echo $mode['consultation_mode_id']; ?>"><?php echo htmlspecialchars($mode['mode_name']); ?></label>
                                </div>
                                <div class="mode-details">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="fee_<?php echo $mode['consultation_mode_id']; ?>">Additional Fee ($)</label>
                                            <input type="number" name="fee_<?php echo $mode['consultation_mode_id']; ?>" 
                                                   id="fee_<?php echo $mode['consultation_mode_id']; ?>" 
                                                   class="form-control consultation-fee" step="0.01" min="0" 
                                                   value="<?php echo $additionalFee; ?>" 
                                                   <?php echo !$isSelected ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="form-group">
                                            <label for="duration_<?php echo $mode['consultation_mode_id']; ?>">Duration (minutes)</label>
                                            <input type="number" name="duration_<?php echo $mode['consultation_mode_id']; ?>" 
                                                   id="duration_<?php echo $mode['consultation_mode_id']; ?>" 
                                                   class="form-control consultation-duration" min="0" 
                                                   value="<?php echo $duration; ?>" 
                                                   <?php echo !$isSelected ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="form-buttons">
                    <a href="services.php" class="btn cancel-btn">Cancel</a>
                    <button type="submit" name="update_service" class="btn submit-btn">Update Service</button>
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

.checkbox-group {
    display: flex;
    align-items: center;
    flex-direction: row;
    margin-top: 30px;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 10px;
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

h4 {
    color: var(--primary-color);
    margin-top: 25px;
    margin-bottom: 10px;
}

.consultation-modes-container {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    max-height: 300px;
    overflow-y: auto;
}

.consultation-mode-item {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.consultation-mode-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.mode-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.mode-details {
    padding-left: 25px;
}

.notice {
    color: var(--secondary-color);
    font-style: italic;
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
    
    .primary-btn, .secondary-btn {
        flex: 1;
        justify-content: center;
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
}
</style>

<script>
// Handle consultation mode checkboxes
document.querySelectorAll('.consultation-mode-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        updateConsultationModeFields(this);
    });
});

function updateConsultationModeFields(checkbox) {
    const modeId = checkbox.value;
    const feeInput = document.getElementById('fee_' + modeId);
    const durationInput = document.getElementById('duration_' + modeId);
    
    if (checkbox.checked) {
        feeInput.disabled = false;
        durationInput.disabled = false;
    } else {
        feeInput.disabled = true;
        durationInput.disabled = true;
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
