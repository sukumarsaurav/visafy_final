<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Services Management";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Get all service types
$query = "SELECT * FROM service_types WHERE 1 ORDER BY service_name";
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

// Get all consultation modes
$query = "SELECT * FROM consultation_modes WHERE 1 ORDER BY mode_name";
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

// Get all countries
$query = "SELECT * FROM countries WHERE is_active = 1 ORDER BY country_name";
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

// Handle service type form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service_type'])) {
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($service_name)) {
        $errors[] = "Service name is required";
    }
    
    if (empty($errors)) {
        // Check if service type already exists
        $check_query = "SELECT service_type_id FROM service_types WHERE service_name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $service_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Service type already exists";
        }
        $check_stmt->close();
    }
    
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

// Handle consultation mode form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consultation_mode'])) {
    $mode_name = trim($_POST['mode_name']);
    $mode_description = trim($_POST['mode_description']);
    $is_custom = isset($_POST['is_custom']) ? 1 : 0;
    $is_active = isset($_POST['mode_is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($mode_name)) {
        $errors[] = "Mode name is required";
    }
    
    if (empty($errors)) {
        // Check if consultation mode already exists
        $check_query = "SELECT consultation_mode_id FROM consultation_modes WHERE mode_name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $mode_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Consultation mode already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new consultation mode
        $insert_query = "INSERT INTO consultation_modes (mode_name, description, is_custom, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssii', $mode_name, $mode_description, $is_custom, $is_active);
        
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

// Handle visa service form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visa_service'])) {
    $visa_id = $_POST['visa_id'];
    $service_type_id = $_POST['service_type_id'];
    $base_price = $_POST['base_price'];
    $service_description = trim($_POST['service_description']);
    $is_active = isset($_POST['service_is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($visa_id)) {
        $errors[] = "Visa type is required";
    }
    if (empty($service_type_id)) {
        $errors[] = "Service type is required";
    }
    if (empty($base_price) || !is_numeric($base_price) || $base_price < 0) {
        $errors[] = "Valid base price is required";
    }
    
    if (empty($errors)) {
        // Check if visa service combination already exists
        $check_query = "SELECT visa_service_id FROM visa_services WHERE visa_id = ? AND service_type_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ii', $visa_id, $service_type_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "This visa service combination already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new visa service
        $insert_query = "INSERT INTO visa_services (visa_id, service_type_id, base_price, description, is_active) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('iidsi', $visa_id, $service_type_id, $base_price, $service_description, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Visa service added successfully";
            $visa_service_id = $conn->insert_id;
            
            // Add consultation modes if selected
            if (isset($_POST['consultation_modes']) && is_array($_POST['consultation_modes'])) {
                foreach ($_POST['consultation_modes'] as $mode_id) {
                    $additional_fee = isset($_POST['fee_'.$mode_id]) ? $_POST['fee_'.$mode_id] : 0;
                    $duration = isset($_POST['duration_'.$mode_id]) ? $_POST['duration_'.$mode_id] : NULL;
                    
                    $mode_query = "INSERT INTO service_consultation_modes 
                                (visa_service_id, consultation_mode_id, additional_fee, duration_minutes, is_available) 
                                VALUES (?, ?, ?, ?, 1)";
                    $mode_stmt = $conn->prepare($mode_query);
                    $mode_stmt->bind_param('iidi', $visa_service_id, $mode_id, $additional_fee, $duration);
                    $mode_stmt->execute();
                    $mode_stmt->close();
                }
            }
            
            $stmt->close();
            header("Location: services.php?success=3");
            exit;
        } else {
            $error_message = "Error adding visa service: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle service type deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service_type'])) {
    $service_type_id = $_POST['service_type_id'];
    
    // Check if service type is in use
    $check_query = "SELECT visa_service_id FROM visa_services WHERE service_type_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $service_type_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete service type as it is currently in use";
    } else {
        // Delete service type
        $delete_query = "DELETE FROM service_types WHERE service_type_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $service_type_id);
        
        if ($stmt->execute()) {
            $success_message = "Service type deleted successfully";
            $stmt->close();
            header("Location: services.php?success=4");
            exit;
        } else {
            $error_message = "Error deleting service type: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Handle consultation mode deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_consultation_mode'])) {
    $consultation_mode_id = $_POST['consultation_mode_id'];
    
    // Check if consultation mode is in use
    $check_query = "SELECT service_consultation_id FROM service_consultation_modes WHERE consultation_mode_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $consultation_mode_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete consultation mode as it is currently in use";
    } else {
        // Delete consultation mode
        $delete_query = "DELETE FROM consultation_modes WHERE consultation_mode_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $consultation_mode_id);
        
        if ($stmt->execute()) {
            $success_message = "Consultation mode deleted successfully";
            $stmt->close();
            header("Location: services.php?success=5");
            exit;
        } else {
            $error_message = "Error deleting consultation mode: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Get visa service listings with related data and consultation modes
$query = "SELECT * FROM visa_services_with_modes ORDER BY country_name, visa_type, service_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$visa_services_result = $stmt->get_result();
$visa_services = [];

if ($visa_services_result && $visa_services_result->num_rows > 0) {
    while ($row = $visa_services_result->fetch_assoc()) {
        $visa_services[] = $row;
    }
}
$stmt->close();

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
            $success_message = "Visa service added successfully";
            break;
        case 4:
            $success_message = "Service type deleted successfully";
            break;
        case 5:
            $success_message = "Consultation mode deleted successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Services Management</h1>
            <p>Manage visa services, service types, and consultation modes</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="services">Visa Services</button>
            <button class="tab-btn" data-tab="service-types">Service Types</button>
            <button class="tab-btn" data-tab="consultation-modes">Consultation Modes</button>
        </div>
        
        <!-- Visa Services Tab -->
        <div class="tab-content active" id="services-tab">
            <div class="tab-header">
                <h2>Visa Services</h2>
                <button type="button" class="btn primary-btn" id="addServiceBtn">
                    <i class="fas fa-plus"></i> Add Visa Service
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($visa_services)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No visa services yet. Add a service to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Visa Type</th>
                                <th>Service</th>
                                <th>Base Price</th>
                                <th>Consultation Modes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visa_services as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['country_name']); ?></td>
                                    <td><?php echo htmlspecialchars($service['visa_type']); ?></td>
                                    <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                    <td>$<?php echo number_format($service['base_price'], 2); ?></td>
                                    <td>
                                        <?php if (!empty($service['available_modes'])): ?>
                                            <?php echo htmlspecialchars($service['available_modes']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($service['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="edit_service.php?id=<?php echo $service['visa_service_id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-action btn-view" title="View Details" 
                                                onclick="viewServiceDetails(<?php echo $service['visa_service_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Service Types Tab -->
        <div class="tab-content" id="service-types-tab">
            <div class="tab-header">
                <h2>Service Types</h2>
                <button type="button" class="btn primary-btn" id="addServiceTypeBtn">
                    <i class="fas fa-plus"></i> Add Service Type
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($service_types)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <p>No service types yet. Add a service type to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($service_types as $type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type['service_name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($type['description']) 
                                                ? htmlspecialchars(substr($type['description'], 0, 100)) . (strlen($type['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($type['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editServiceType(<?php echo $type['service_type_id']; ?>, '<?php echo addslashes($type['service_name']); ?>', '<?php echo addslashes($type['description']); ?>', <?php echo $type['is_active']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteServiceType(<?php echo $type['service_type_id']; ?>, '<?php echo addslashes($type['service_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Consultation Modes Tab -->
        <div class="tab-content" id="consultation-modes-tab">
            <div class="tab-header">
                <h2>Consultation Modes</h2>
                <button type="button" class="btn primary-btn" id="addConsultationModeBtn">
                    <i class="fas fa-plus"></i> Add Consultation Mode
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($consultation_modes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No consultation modes yet. Add a consultation mode to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mode Name</th>
                                <th>Description</th>
                                <th>Custom</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultation_modes as $mode): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mode['mode_name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($mode['description']) 
                                                ? htmlspecialchars(substr($mode['description'], 0, 100)) . (strlen($mode['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $mode['is_custom'] ? 'Yes' : 'No'; ?>
                                    </td>
                                    <td>
                                        <?php if ($mode['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editConsultationMode(<?php echo $mode['consultation_mode_id']; ?>, '<?php echo addslashes($mode['mode_name']); ?>', '<?php echo addslashes($mode['description']); ?>', <?php echo $mode['is_custom']; ?>, <?php echo $mode['is_active']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteConsultationMode(<?php echo $mode['consultation_mode_id']; ?>, '<?php echo addslashes($mode['mode_name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
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
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        <label for="is_active">Active</label>
                    </div>
                    <input type="hidden" name="service_type_id" id="service_type_id" value="">
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_service_type" class="btn submit-btn">Save Service Type</button>
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
                        <textarea name="mode_description" id="mode_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="is_custom" id="is_custom">
                        <label for="is_custom">Custom Mode</label>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="mode_is_active" id="mode_is_active" checked>
                        <label for="mode_is_active">Active</label>
                    </div>
                    <input type="hidden" name="consultation_mode_id" id="consultation_mode_id" value="">
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_consultation_mode" class="btn submit-btn">Save Mode</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Visa Service Modal -->
<div class="modal" id="addServiceModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Visa Service</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="services.php" method="POST" id="addServiceForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country_id">Country*</label>
                            <select name="country_id" id="country_id" class="form-control" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['country_id']; ?>"><?php echo htmlspecialchars($country['country_name']); ?></option>
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
                            <label for="service_type_id">Service Type*</label>
                            <select name="service_type_id" id="service_type_id" class="form-control" required>
                                <option value="">Select Service Type</option>
                                <?php foreach ($service_types as $type): ?>
                                    <?php if ($type['is_active']): ?>
                                        <option value="<?php echo $type['service_type_id']; ?>"><?php echo htmlspecialchars($type['service_name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="base_price">Base Price ($)*</label>
                            <input type="number" name="base_price" id="base_price" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="service_description">Description</label>
                        <textarea name="service_description" id="service_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="service_is_active" id="service_is_active" checked>
                        <label for="service_is_active">Active</label>
                    </div>
                    
                    <h4>Consultation Modes</h4>
                    <div class="consultation-modes-container">
                        <?php if (empty($consultation_modes)): ?>
                            <p class="notice">No consultation modes available. Please add consultation modes first.</p>
                        <?php else: ?>
                            <?php foreach ($consultation_modes as $mode): ?>
                                <?php if ($mode['is_active']): ?>
                                    <div class="consultation-mode-item">
                                        <div class="mode-checkbox">
                                            <input type="checkbox" name="consultation_modes[]" id="mode_<?php echo $mode['consultation_mode_id']; ?>" 
                                                   value="<?php echo $mode['consultation_mode_id']; ?>" 
                                                   class="consultation-mode-checkbox">
                                            <label for="mode_<?php echo $mode['consultation_mode_id']; ?>"><?php echo htmlspecialchars($mode['mode_name']); ?></label>
                                        </div>
                                        <div class="mode-details">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="fee_<?php echo $mode['consultation_mode_id']; ?>">Additional Fee ($)</label>
                                                    <input type="number" name="fee_<?php echo $mode['consultation_mode_id']; ?>" 
                                                           id="fee_<?php echo $mode['consultation_mode_id']; ?>" 
                                                           class="form-control consultation-fee" step="0.01" min="0" value="0.00" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <label for="duration_<?php echo $mode['consultation_mode_id']; ?>">Duration (minutes)</label>
                                                    <input type="number" name="duration_<?php echo $mode['consultation_mode_id']; ?>" 
                                                           id="duration_<?php echo $mode['consultation_mode_id']; ?>" 
                                                           class="form-control consultation-duration" min="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_visa_service" class="btn submit-btn">Save Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="deleteServiceTypeForm" action="services.php" method="POST" style="display: none;">
    <input type="hidden" name="service_type_id" id="delete_service_type_id">
    <input type="hidden" name="delete_service_type" value="1">
</form>

<form id="deleteConsultationModeForm" action="services.php" method="POST" style="display: none;">
    <input type="hidden" name="consultation_mode_id" id="delete_consultation_mode_id">
    <input type="hidden" name="delete_consultation_mode" value="1">
</form>

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

.tabs-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
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

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tab-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
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

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
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

.modal-dialog.modal-lg {
    max-width: 700px;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
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

.notice {
    color: var(--secondary-color);
    font-style: italic;
}

/* Consultation modes styling */
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

h4 {
    color: var(--primary-color);
    margin-top: 25px;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .tabs {
        overflow-x: auto;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}
</style>

<script>
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

// Open modals when buttons are clicked
document.getElementById('addServiceTypeBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addServiceTypeForm').reset();
    document.getElementById('service_type_id').value = '';
    document.querySelector('#addServiceTypeModal .modal-title').textContent = 'Add Service Type';
    document.querySelector('#addServiceTypeForm button[type="submit"]').textContent = 'Save Service Type';
    openModal('addServiceTypeModal');
});

document.getElementById('addConsultationModeBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addConsultationModeForm').reset();
    document.getElementById('consultation_mode_id').value = '';
    document.querySelector('#addConsultationModeModal .modal-title').textContent = 'Add Consultation Mode';
    document.querySelector('#addConsultationModeForm button[type="submit"]').textContent = 'Save Mode';
    openModal('addConsultationModeModal');
});

document.getElementById('addServiceBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addServiceForm').reset();
    
    // Reset visa dropdown
    const visaSelect = document.getElementById('visa_id');
    visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
    visaSelect.disabled = true;
    
    // Reset consultation mode checkboxes
    document.querySelectorAll('.consultation-mode-checkbox').forEach(function(checkbox) {
        checkbox.checked = false;
        updateConsultationModeFields(checkbox);
    });
    
    openModal('addServiceModal');
});

// Function to edit service type
function editServiceType(id, name, description, isActive) {
    document.getElementById('service_type_id').value = id;
    document.getElementById('service_name').value = name;
    document.getElementById('description').value = description;
    document.getElementById('is_active').checked = isActive === 1;
    
    document.querySelector('#addServiceTypeModal .modal-title').textContent = 'Edit Service Type';
    document.querySelector('#addServiceTypeForm button[type="submit"]').textContent = 'Update Service Type';
    
    openModal('addServiceTypeModal');
}

// Function to edit consultation mode
function editConsultationMode(id, name, description, isCustom, isActive) {
    document.getElementById('consultation_mode_id').value = id;
    document.getElementById('mode_name').value = name;
    document.getElementById('mode_description').value = description;
    document.getElementById('is_custom').checked = isCustom === 1;
    document.getElementById('mode_is_active').checked = isActive === 1;
    
    document.querySelector('#addConsultationModeModal .modal-title').textContent = 'Edit Consultation Mode';
    document.querySelector('#addConsultationModeForm button[type="submit"]').textContent = 'Update Mode';
    
    openModal('addConsultationModeModal');
}

// Function to confirm service type deletion
function confirmDeleteServiceType(id, name) {
    if (confirm('Are you sure you want to delete the service type "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_service_type_id').value = id;
        document.getElementById('deleteServiceTypeForm').submit();
    }
}

// Function to confirm consultation mode deletion
function confirmDeleteConsultationMode(id, name) {
    if (confirm('Are you sure you want to delete the consultation mode "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_consultation_mode_id').value = id;
        document.getElementById('deleteConsultationModeForm').submit();
    }
}

// Function to view service details (placeholder - implement as needed)
function viewServiceDetails(id) {
    // Redirect to service details page
    window.location.href = 'service_details.php?id=' + id;
}

// Load visa types based on country selection
document.getElementById('country_id').addEventListener('change', function() {
    const countryId = this.value;
    const visaSelect = document.getElementById('visa_id');
    
    if (countryId) {
        // Enable the visa select
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
                } else {
                    visaSelect.innerHTML = '<option value="">No visa types found</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching visa types:', error);
                visaSelect.innerHTML = '<option value="">Error loading visa types</option>';
            });
    } else {
        // Reset and disable the visa select
        visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
        visaSelect.disabled = true;
    }
});

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