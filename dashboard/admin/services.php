<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Service Management";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Get all service types
$query = "SELECT service_type_id, service_name, description, is_active, created_at 
          FROM service_types 
          ORDER BY service_name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$service_types = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $service_types[] = $row;
    }
}
$stmt->close();

// Get all consultation modes
$query = "SELECT consultation_mode_id, mode_name, description, is_custom, is_active, created_at 
          FROM consultation_modes 
          ORDER BY mode_name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$consultation_modes = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $consultation_modes[] = $row;
    }
}
$stmt->close();

// Handle service type creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service_type'])) {
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($service_name)) {
        $errors[] = "Service name is required";
    }
    
    // Check if service name already exists
    $check_query = "SELECT service_type_id FROM service_types WHERE service_name = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $service_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Service name already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Insert new service type
        $insert_query = "INSERT INTO service_types (service_name, description, is_active) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssi', $service_name, $description, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Service type added successfully";
            $stmt->close();
            header("Location: services.php?success=1");
            exit;
        } else {
            $error_message = "Error adding service type: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle consultation mode creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consultation_mode'])) {
    $mode_name = trim($_POST['mode_name']);
    $description = trim($_POST['description']);
    $is_custom = isset($_POST['is_custom']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($mode_name)) {
        $errors[] = "Mode name is required";
    }
    
    // Check if mode name already exists
    $check_query = "SELECT consultation_mode_id FROM consultation_modes WHERE mode_name = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $mode_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Consultation mode name already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Insert new consultation mode
        $insert_query = "INSERT INTO consultation_modes (mode_name, description, is_custom, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssii', $mode_name, $description, $is_custom, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Consultation mode added successfully";
            $stmt->close();
            header("Location: services.php?success=2");
            exit;
        } else {
            $error_message = "Error adding consultation mode: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle service type toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_service_type'])) {
    $service_type_id = $_POST['service_type_id'];
    $new_status = $_POST['new_status'];
    
    // Update status
    $update_query = "UPDATE service_types SET is_active = ? WHERE service_type_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $new_status, $service_type_id);
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Service type activated successfully" : "Service type deactivated successfully";
        $stmt->close();
        header("Location: services.php?success=3&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating service type status: " . $conn->error;
        $stmt->close();
    }
}

// Handle consultation mode toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_consultation_mode'])) {
    $consultation_mode_id = $_POST['consultation_mode_id'];
    $new_status = $_POST['new_status'];
    
    // Update status
    $update_query = "UPDATE consultation_modes SET is_active = ? WHERE consultation_mode_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $new_status, $consultation_mode_id);
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Consultation mode activated successfully" : "Consultation mode deactivated successfully";
        $stmt->close();
        header("Location: services.php?success=4&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating consultation mode status: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Service type added successfully";
            break;
        case 2:
            $success_message = "Consultation mode added successfully";
            break;
        case 3:
        case 4:
            $success_message = isset($_GET['message']) ? $_GET['message'] : "Status updated successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Service Management</h1>
            <p>Manage service types and consultation modes for visa services.</p>
        </div>
        <div class="action-buttons">
            <button type="button" class="btn primary-btn" id="addServiceTypeBtn">
                <i class="fas fa-plus"></i> Add Service Type
            </button>
            <button type="button" class="btn primary-btn" id="addConsultationModeBtn">
                <i class="fas fa-plus"></i> Add Consultation Mode
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tabs for different sections -->
    <ul class="nav nav-tabs" id="serviceTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="service-types-tab" data-toggle="tab" href="#serviceTypes" role="tab">
                Service Types
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="consultation-modes-tab" data-toggle="tab" href="#consultationModes" role="tab">
                Consultation Modes
            </a>
        </li>
    </ul>
    
    <div class="tab-content" id="serviceTabsContent">
        <!-- Service Types Tab -->
        <div class="tab-pane fade show active" id="serviceTypes" role="tabpanel">
            <div class="table-container">
                <?php if (empty($service_types)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <p>No service types found. Add a service type to get started.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th>Created Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($service_types as $service): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($service['description']) ? htmlspecialchars($service['description']) : '<span class="no-data">No description</span>'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $created_date = new DateTime($service['created_at']);
                                            echo $created_date->format('M d, Y'); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($service['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="edit_service_type.php?id=<?php echo $service['service_type_id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($service['is_active']): ?>
                                            <button type="button" class="btn-action btn-deactivate" 
                                                    title="Deactivate" onclick="toggleServiceType(<?php echo $service['service_type_id']; ?>, 0)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action btn-activate" 
                                                    title="Activate" onclick="toggleServiceType(<?php echo $service['service_type_id']; ?>, 1)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="service_pricing.php?id=<?php echo $service['service_type_id']; ?>" class="btn-action btn-pricing" title="Manage Pricing">
                                            <i class="fas fa-dollar-sign"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Consultation Modes Tab -->
        <div class="tab-pane fade" id="consultationModes" role="tabpanel">
            <div class="table-container">
                <?php if (empty($consultation_modes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No consultation modes found. Add a consultation mode to get started.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mode Name</th>
                                <th>Description</th>
                                <th>Custom</th>
                                <th>Created Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultation_modes as $mode): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($mode['mode_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($mode['description']) ? htmlspecialchars($mode['description']) : '<span class="no-data">No description</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if ($mode['is_custom']): ?>
                                            <span class="custom-badge"><i class="fas fa-check-circle"></i> Yes</span>
                                        <?php else: ?>
                                            <span class="standard-badge"><i class="fas fa-times-circle"></i> No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $created_date = new DateTime($mode['created_at']);
                                            echo $created_date->format('M d, Y'); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($mode['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="edit_consultation_mode.php?id=<?php echo $mode['consultation_mode_id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($mode['is_active']): ?>
                                            <button type="button" class="btn-action btn-deactivate" 
                                                    title="Deactivate" onclick="toggleConsultationMode(<?php echo $mode['consultation_mode_id']; ?>, 0)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action btn-activate" 
                                                    title="Activate" onclick="toggleConsultationMode(<?php echo $mode['consultation_mode_id']; ?>, 1)">
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
    </div>
</div>

<!-- Add Service Type Modal -->
<div class="modal" id="addServiceTypeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Service Type</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="services.php" method="POST" id="addServiceTypeForm">
                    <div class="form-group">
                        <label for="service_name">Service Name*</label>
                        <input type="text" name="service_name" id="service_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                            <label for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_service_type" class="btn submit-btn">Add Service Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Consultation Mode Modal -->
<div class="modal" id="addConsultationModeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Consultation Mode</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="services.php" method="POST" id="addConsultationModeForm">
                    <div class="form-group">
                        <label for="mode_name">Mode Name*</label>
                        <input type="text" name="mode_name" id="mode_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="mode_description">Description</label>
                        <textarea name="description" id="mode_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_custom" id="is_custom" value="1">
                            <label for="is_custom">Custom Mode</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="mode_is_active" value="1" checked>
                            <label for="mode_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_consultation_mode" class="btn submit-btn">Add Consultation Mode</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="toggleServiceTypeForm" action="services.php" method="POST" style="display: none;">
    <input type="hidden" name="service_type_id" id="toggle_service_type_id">
    <input type="hidden" name="new_status" id="toggle_service_type_status">
    <input type="hidden" name="toggle_service_type" value="1">
</form>

<form id="toggleConsultationModeForm" action="services.php" method="POST" style="display: none;">
    <input type="hidden" name="consultation_mode_id" id="toggle_consultation_mode_id">
    <input type="hidden" name="new_status" id="toggle_consultation_mode_status">
    <input type="hidden" name="toggle_consultation_mode" value="1">
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

.nav-tabs {
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    list-style: none;
    padding: 0;
}

.nav-item {
    margin-bottom: -1px;
}

.nav-link {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: var(--secondary-color);
    border: 1px solid transparent;
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
}

.nav-link:hover {
    border-color: var(--border-color) var(--border-color) transparent;
    color: var(--primary-color);
}

.nav-link.active {
    color: var(--primary-color);
    background-color: white;
    border-color: var(--border-color) var(--border-color) white;
    font-weight: 500;
}

.table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
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

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.no-data {
    color: var(--secondary-color);
    font-style: italic;
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

.custom-badge, .standard-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
}

.custom-badge {
    color: var(--primary-color);
}

.standard-badge {
    color: var(--secondary-color);
}

.actions-cell {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
}

.btn-activate {
    background-color: var(--success-color);
}

.btn-activate:hover {
    background-color: #18b07b;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
}

.btn-pricing {
    background-color: #4e73df;
}

.btn-pricing:hover {
    background-color: #4262c3;
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

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.checkbox-group {
    display: flex;
    align-items: center;
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .primary-btn {
        flex: 1;
        justify-content: center;
    }
}

.data-table {
    display: block;
    overflow-x: auto;
}

.actions-cell {
    flex-direction: column;
}

.btn-action {
    width: 100%;
    justify-content: center;
}

.nav-tabs {
    flex-wrap: wrap;
}

.nav-link {
    width: 100%;
    text-align: center;
}
</style>

<script>
// Tab functionality
document.querySelectorAll('.nav-link').forEach(function(tabLink) {
    tabLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and tab panes
        document.querySelectorAll('.nav-link').forEach(function(link) {
            link.classList.remove('active');
        });
        document.querySelectorAll('.tab-pane').forEach(function(pane) {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Show corresponding tab content
        const tabId = this.getAttribute('href').substring(1);
        document.getElementById(tabId).classList.add('show', 'active');
    });
});

// Modal functionality
document.getElementById('addServiceTypeBtn').addEventListener('click', function() {
    document.getElementById('addServiceTypeModal').style.display = 'block';
});

document.getElementById('addConsultationModeBtn').addEventListener('click', function() {
    document.getElementById('addConsultationModeModal').style.display = 'block';
});

// Close modals when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.style.display = 'none';
        });
    });
});

// Close modals when clicking outside of it
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// Function to toggle service type status
function toggleServiceType(serviceTypeId, newStatus) {
    document.getElementById('toggle_service_type_id').value = serviceTypeId;
    document.getElementById('toggle_service_type_status').value = newStatus;
    
    if (newStatus == 0) {
        if (confirm('Are you sure you want to deactivate this service type?')) {
            document.getElementById('toggleServiceTypeForm').submit();
        }
    } else {
        document.getElementById('toggleServiceTypeForm').submit();
    }
}

// Function to toggle consultation mode status
function toggleConsultationMode(consultationModeId, newStatus) {
    document.getElementById('toggle_consultation_mode_id').value = consultationModeId;
    document.getElementById('toggle_consultation_mode_status').value = newStatus;
    
    if (newStatus == 0) {
        if (confirm('Are you sure you want to deactivate this consultation mode?')) {
            document.getElementById('toggleConsultationModeForm').submit();
        }
    } else {
        document.getElementById('toggleConsultationModeForm').submit();
    }
}

// Auto-focus first input in modals when opened
document.getElementById('addServiceTypeBtn').addEventListener('click', function() {
    setTimeout(function() {
        document.getElementById('service_name').focus();
    }, 100);
});

document.getElementById('addConsultationModeBtn').addEventListener('click', function() {
    setTimeout(function() {
        document.getElementById('mode_name').focus();
    }, 100);
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
