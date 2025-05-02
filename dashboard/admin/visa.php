<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Visa Management";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Get all countries - Using prepared statement
$query = "SELECT country_id, country_name, country_code, is_active, inactive_reason, inactive_since 
          FROM countries 
          ORDER BY country_name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$countries = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
}
$stmt->close();

// Get visas for each country
$visas = [];
if (!empty($countries)) {
    $country_ids = array_column($countries, 'country_id');
    $country_ids_str = implode(',', $country_ids);
    
    $visa_query = "SELECT v.visa_id, v.country_id, v.visa_type, v.description, v.validity_period, 
                   v.fee, v.requirements, v.is_active, v.inactive_reason, v.inactive_since, 
                   c.country_name 
                   FROM visas v 
                   JOIN countries c ON v.country_id = c.country_id 
                   WHERE v.country_id IN ($country_ids_str) 
                   ORDER BY c.country_name, v.visa_type";
    
    $stmt = $conn->prepare($visa_query);
    $stmt->execute();
    $visa_result = $stmt->get_result();
    
    if ($visa_result && $visa_result->num_rows > 0) {
        while ($row = $visa_result->fetch_assoc()) {
            if (!isset($visas[$row['country_id']])) {
                $visas[$row['country_id']] = [];
            }
            $visas[$row['country_id']][] = $row;
        }
    }
    $stmt->close();
}

// Handle country creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_country'])) {
    $country_name = trim($_POST['country_name']);
    $country_code = trim($_POST['country_code']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_name)) {
        $errors[] = "Country name is required";
    }
    if (empty($country_code)) {
        $errors[] = "Country code is required";
    } elseif (strlen($country_code) !== 3) {
        $errors[] = "Country code must be exactly 3 characters";
    }
    
    // Check if country code already exists
    $check_query = "SELECT country_id FROM countries WHERE country_code = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $country_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Country code already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Insert new country
        $insert_query = "INSERT INTO countries (country_name, country_code, is_active) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssi', $country_name, $country_code, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Country added successfully";
            $stmt->close();
            header("Location: visa.php?success=1");
            exit;
        } else {
            $error_message = "Error adding country: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle visa creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visa'])) {
    $country_id = $_POST['country_id'];
    $visa_type = trim($_POST['visa_type']);
    $description = trim($_POST['description']);
    $validity_period = !empty($_POST['validity_period']) ? intval($_POST['validity_period']) : null;
    $fee = !empty($_POST['fee']) ? floatval($_POST['fee']) : null;
    $requirements = trim($_POST['requirements']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_id)) {
        $errors[] = "Please select a country";
    }
    if (empty($visa_type)) {
        $errors[] = "Visa type is required";
    }
    
    if (empty($errors)) {
        // Insert new visa
        $insert_query = "INSERT INTO visas (country_id, visa_type, description, validity_period, fee, requirements, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('issiisi', $country_id, $visa_type, $description, $validity_period, $fee, $requirements, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Visa added successfully";
            $stmt->close();
            header("Location: visa.php?success=2");
            exit;
        } else {
            $error_message = "Error adding visa: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle country toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_country'])) {
    $country_id = $_POST['country_id'];
    $new_status = $_POST['new_status'];
    $inactive_reason = isset($_POST['inactive_reason']) ? trim($_POST['inactive_reason']) : null;
    
    // Update status
    $inactive_since = null;
    if ($new_status == 0) {
        $inactive_since = date('Y-m-d');
        $update_query = "UPDATE countries SET is_active = ?, inactive_reason = ?, inactive_since = ? WHERE country_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issi', $new_status, $inactive_reason, $inactive_since, $country_id);
    } else {
        $update_query = "UPDATE countries SET is_active = ?, inactive_reason = NULL, inactive_since = NULL WHERE country_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $new_status, $country_id);
    }
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Country activated successfully" : "Country deactivated successfully";
        $stmt->close();
        header("Location: visa.php?success=3&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating country status: " . $conn->error;
        $stmt->close();
    }
}

// Handle visa toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visa'])) {
    $visa_id = $_POST['visa_id'];
    $new_status = $_POST['new_status'];
    $inactive_reason = isset($_POST['inactive_reason']) ? trim($_POST['inactive_reason']) : null;
    
    // Update status
    $inactive_since = null;
    if ($new_status == 0) {
        $inactive_since = date('Y-m-d');
        $update_query = "UPDATE visas SET is_active = ?, inactive_reason = ?, inactive_since = ? WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issi', $new_status, $inactive_reason, $inactive_since, $visa_id);
    } else {
        $update_query = "UPDATE visas SET is_active = ?, inactive_reason = NULL, inactive_since = NULL WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $new_status, $visa_id);
    }
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Visa activated successfully" : "Visa deactivated successfully";
        $stmt->close();
        header("Location: visa.php?success=4&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating visa status: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Country added successfully";
            break;
        case 2:
            $success_message = "Visa added successfully";
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
            <h1>Visa Management</h1>
            <p>Manage countries and their available visa types.</p>
        </div>
        <div class="action-buttons">
            <button type="button" class="btn primary-btn" id="addCountryBtn">
                <i class="fas fa-plus"></i> Add Country
            </button>
            <button type="button" class="btn primary-btn" id="addVisaBtn">
                <i class="fas fa-plus"></i> Add Visa
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="visa-filters">
        <div class="filter-group">
            <label for="country-filter">Country:</label>
            <select id="country-filter" class="filter-control">
                <option value="all">All Countries</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?php echo $country['country_id']; ?>">
                        <?php echo htmlspecialchars($country['country_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-control">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="search-group">
            <input type="text" id="search-visa" class="search-control" placeholder="Search...">
            <button id="search-btn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <!-- Countries and Visas Section -->
    <div class="countries-container">
        <?php if (empty($countries)): ?>
            <div class="empty-state">
                <i class="fas fa-globe"></i>
                <p>No countries added yet. Add a country to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($countries as $country): ?>
                <div class="country-card" data-country-id="<?php echo $country['country_id']; ?>" data-status="<?php echo $country['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="country-header">
                        <div class="country-info">
                            <h3>
                                <?php echo htmlspecialchars($country['country_name']); ?>
                                <span class="country-code">(<?php echo htmlspecialchars($country['country_code']); ?>)</span>
                            </h3>
                            <?php if ($country['is_active']): ?>
                                <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                            <?php else: ?>
                                <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                <?php if (!empty($country['inactive_reason'])): ?>
                                    <span class="inactive-reason">Reason: <?php echo htmlspecialchars($country['inactive_reason']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="country-actions">
                            <?php if ($country['is_active']): ?>
                                <button type="button" class="btn-action deactivate-btn" onclick="toggleCountryStatus(<?php echo $country['country_id']; ?>, 0)">
                                    <i class="fas fa-ban"></i> Deactivate
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action activate-btn" onclick="toggleCountryStatus(<?php echo $country['country_id']; ?>, 1)">
                                    <i class="fas fa-check"></i> Activate
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-action edit-btn" onclick="editCountry(<?php echo $country['country_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" class="btn-action add-visa-btn" onclick="addVisaForCountry(<?php echo $country['country_id']; ?>)">
                                <i class="fas fa-plus"></i> Add Visa
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($visas[$country['country_id']]) && !empty($visas[$country['country_id']])): ?>
                        <div class="visas-table-container">
                            <table class="visas-table">
                                <thead>
                                    <tr>
                                        <th>Visa Type</th>
                                        <th>Validity</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visas[$country['country_id']] as $visa): ?>
                                        <tr class="visa-row" data-status="<?php echo $visa['is_active'] ? 'active' : 'inactive'; ?>">
                                            <td>
                                                <div class="visa-name"><?php echo htmlspecialchars($visa['visa_type']); ?></div>
                                                <?php if (!empty($visa['description'])): ?>
                                                    <div class="visa-description"><?php echo htmlspecialchars(substr($visa['description'], 0, 80)) . (strlen($visa['description']) > 80 ? '...' : ''); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($visa['validity_period'])): ?>
                                                    <?php echo $visa['validity_period']; ?> days
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($visa['fee'])): ?>
                                                    $<?php echo number_format($visa['fee'], 2); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($visa['is_active']): ?>
                                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="dropdown">
                                                    <button class="btn-action dropdown-toggle">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a href="visa_details.php?id=<?php echo $visa['visa_id']; ?>" class="dropdown-item">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                        <a href="edit_visa.php?id=<?php echo $visa['visa_id']; ?>" class="dropdown-item">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <?php if ($visa['is_active']): ?>
                                                            <button type="button" class="dropdown-item" 
                                                                    onclick="toggleVisaStatus(<?php echo $visa['visa_id']; ?>, 0)">
                                                                <i class="fas fa-ban"></i> Deactivate
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="dropdown-item" 
                                                                    onclick="toggleVisaStatus(<?php echo $visa['visa_id']; ?>, 1)">
                                                                <i class="fas fa-check"></i> Activate
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-visas-message">
                            <i class="fas fa-info-circle"></i> No visas available for this country.
                            <button type="button" class="btn-link" onclick="addVisaForCountry(<?php echo $country['country_id']; ?>)">
                                Add a visa
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Country Modal -->
<div class="modal" id="addCountryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Country</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="addCountryForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country_name">Country Name*</label>
                            <input type="text" name="country_name" id="country_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="country_code">Country Code* (3 Characters)</label>
                            <input type="text" name="country_code" id="country_code" class="form-control" maxlength="3" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="country_is_active" value="1" checked>
                            <label for="country_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_country" class="btn submit-btn">Add Country</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Visa Modal -->
<div class="modal" id="addVisaModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="addVisaForm">
                    <div class="form-group">
                        <label for="visa_country_id">Country*</label>
                        <select name="country_id" id="visa_country_id" class="form-control" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <?php if ($country['is_active']): ?>
                                    <option value="<?php echo $country['country_id']; ?>">
                                        <?php echo htmlspecialchars($country['country_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visa_type">Visa Type*</label>
                        <input type="text" name="visa_type" id="visa_type" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_period">Validity Period (days)</label>
                            <input type="number" name="validity_period" id="validity_period" class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label for="fee">Fee ($)</label>
                            <input type="number" name="fee" id="fee" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="visa_is_active" value="1" checked>
                            <label for="visa_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_visa" class="btn submit-btn">Add Visa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Country Status Modal -->
<div class="modal" id="toggleCountryStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="toggleCountryStatusTitle">Deactivate Country</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="toggleCountryStatusForm">
                    <input type="hidden" name="country_id" id="toggle_country_id">
                    <input type="hidden" name="new_status" id="toggle_country_status">
                    <input type="hidden" name="toggle_country" value="1">
                    
                    <div class="form-group" id="inactiveReasonGroup">
                        <label for="inactive_reason">Reason for Deactivation</label>
                        <textarea name="inactive_reason" id="inactive_reason" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="toggleCountryStatusBtn">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Visa Status Modal -->
<div class="modal" id="toggleVisaStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="toggleVisaStatusTitle">Deactivate Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="toggleVisaStatusForm">
                    <input type="hidden" name="visa_id" id="toggle_visa_id">
                    <input type="hidden" name="new_status" id="toggle_visa_status">
                    <input type="hidden" name="toggle_visa" value="1">
                    
                    <div class="form-group" id="visaInactiveReasonGroup">
                        <label for="visa_inactive_reason">Reason for Deactivation</label>
                        <textarea name="inactive_reason" id="visa_inactive_reason" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="toggleVisaStatusBtn">Deactivate</button>
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

.visa-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 120px;
}

.search-group {
    display: flex;
    margin-left: auto;
}

.search-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px 0 0 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 200px;
}

.search-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
}

.countries-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
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

.country-code {
    color: var(--secondary-color);
    font-size: 1rem;
    font-weight: normal;
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

.inactive-reason {
    margin-left: 10px;
    font-size: 12px;
    color: var(--secondary-color);
    font-style: italic;
}

.country-actions {
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    background-color: var(--secondary-color);
    color: white;
}

.activate-btn {
    background-color: var(--success-color);
}

.activate-btn:hover {
    background-color: #18b07b;
}

.deactivate-btn {
    background-color: var(--danger-color);
}

.deactivate-btn:hover {
    background-color: #d44235;
}

.edit-btn {
    background-color: var(--warning-color);
}

.edit-btn:hover {
    background-color: #e0b137;
}

.add-visa-btn {
    background-color: var(--primary-color);
}

.add-visa-btn:hover {
    background-color: #031c56;
}

.visas-table-container {
    padding: 15px;
}

.visas-table {
    width: 100%;
    border-collapse: collapse;
}

.visas-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.visas-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.visas-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.visas-table tbody tr:last-child td {
    border-bottom: none;
}

.visa-name {
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.visa-description {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.actions-cell {
    width: 10%;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    background-color: transparent;
    border: none;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 4px;
}

.dropdown-toggle:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background-color: white;
    min-width: 160px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    z-index: 1;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    color: var(--dark-color);
    text-decoration: none;
    gap: 8px;
    cursor: pointer;
}

.dropdown-item:hover {
    background-color: var(--light-color);
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}

.no-visas-message {
    padding: 20px;
    text-align: center;
    color: var(--secondary-color);
    font-style: italic;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary-color);
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
    font-size: inherit;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
    max-width: 600px;
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
    
    .visa-filters {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .search-group {
        width: 100%;
        margin-left: 0;
    }
    
    .search-control {
        flex: 1;
    }
    
    .country-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .country-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .visas-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
// Modal functionality
// Open Add Country modal
document.getElementById('addCountryBtn').addEventListener('click', function() {
    document.getElementById('addCountryModal').style.display = 'block';
});

// Open Add Visa modal
document.getElementById('addVisaBtn').addEventListener('click', function() {
    document.getElementById('addVisaModal').style.display = 'block';
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

// Dropdown functionality for visa actions
document.querySelectorAll('.dropdown-toggle').forEach(function(button) {
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        const menu = this.nextElementSibling;
        
        // Close all other open dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
            if (openMenu !== menu) {
                openMenu.classList.remove('show');
            }
        });
        
        // Toggle this dropdown
        menu.classList.toggle('show');
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
        menu.classList.remove('show');
    });
});

// Function to add visa for specific country
function addVisaForCountry(countryId) {
    document.getElementById('visa_country_id').value = countryId;
    document.getElementById('addVisaModal').style.display = 'block';
}

// Function to edit country
function editCountry(countryId) {
    // Redirect to edit country page or show edit modal
    window.location.href = 'edit_country.php?id=' + countryId;
}

// Function to toggle country status
function toggleCountryStatus(countryId, newStatus) {
    document.getElementById('toggle_country_id').value = countryId;
    document.getElementById('toggle_country_status').value = newStatus;
    
    const modal = document.getElementById('toggleCountryStatusModal');
    const title = document.getElementById('toggleCountryStatusTitle');
    const button = document.getElementById('toggleCountryStatusBtn');
    const reasonGroup = document.getElementById('inactiveReasonGroup');
    
    if (newStatus == 1) {
        title.textContent = 'Activate Country';
        button.textContent = 'Activate';
        reasonGroup.style.display = 'none';
    } else {
        title.textContent = 'Deactivate Country';
        button.textContent = 'Deactivate';
        reasonGroup.style.display = 'block';
    }
    
    modal.style.display = 'block';
}

// Function to toggle visa status
function toggleVisaStatus(visaId, newStatus) {
    document.getElementById('toggle_visa_id').value = visaId;
    document.getElementById('toggle_visa_status').value = newStatus;
    
    const modal = document.getElementById('toggleVisaStatusModal');
    const title = document.getElementById('toggleVisaStatusTitle');
    const button = document.getElementById('toggleVisaStatusBtn');
    const reasonGroup = document.getElementById('visaInactiveReasonGroup');
    
    if (newStatus == 1) {
        title.textContent = 'Activate Visa';
        button.textContent = 'Activate';
        reasonGroup.style.display = 'none';
    } else {
        title.textContent = 'Deactivate Visa';
        button.textContent = 'Deactivate';
        reasonGroup.style.display = 'block';
    }
    
    modal.style.display = 'block';
}

// Country filtering functionality
function filterCountries() {
    const countryFilter = document.getElementById('country-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    const searchQuery = document.getElementById('search-visa').value.toLowerCase();
    
    document.querySelectorAll('.country-card').forEach(function(card) {
        const countryId = card.getAttribute('data-country-id');
        const status = card.getAttribute('data-status');
        const countryName = card.querySelector('h3').textContent.toLowerCase();
        const visaRows = card.querySelectorAll('.visa-row');
        
        let showCountry = true;
        
        // Apply country filter
        if (countryFilter !== 'all' && countryId !== countryFilter) {
            showCountry = false;
        }
        
        // Apply status filter
        if (statusFilter !== 'all' && status !== statusFilter) {
            showCountry = false;
        }
        
        // Apply search filter to country name
        if (searchQuery && !countryName.includes(searchQuery)) {
            // If search doesn't match country name, check visa names
            let visaMatch = false;
            visaRows.forEach(function(row) {
                const visaName = row.querySelector('.visa-name').textContent.toLowerCase();
                if (visaName.includes(searchQuery)) {
                    visaMatch = true;
                }
            });
            
            if (!visaMatch) {
                showCountry = false;
            }
        }
        
        card.style.display = showCountry ? 'block' : 'none';
    });
}

// Add event listeners for filters
document.getElementById('country-filter').addEventListener('change', filterCountries);
document.getElementById('status-filter').addEventListener('change', filterCountries);
document.getElementById('search-btn').addEventListener('click', filterCountries);
document.getElementById('search-visa').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterCountries();
    }
});

// Country code validation - force uppercase
document.getElementById('country_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
