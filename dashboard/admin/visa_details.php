<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Visa Details";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Check if visa ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: visa.php");
    exit;
}

$visa_id = intval($_GET['id']);

// Get visa details
$query = "SELECT v.*, c.country_name, c.country_code 
          FROM visas v 
          JOIN countries c ON v.country_id = c.country_id 
          WHERE v.visa_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: visa.php");
    exit;
}

$visa = $result->fetch_assoc();
$stmt->close();

// Get required documents for this visa
$required_docs = [];
$docs_query = "SELECT vrd.id, vrd.is_mandatory, vrd.notes, dt.name as document_name, dc.name as category_name
              FROM visa_required_documents vrd
              JOIN document_types dt ON vrd.document_type_id = dt.id
              JOIN document_categories dc ON dt.category_id = dc.id
              WHERE vrd.visa_id = ?
              ORDER BY dc.name, dt.name";
              
$stmt = $conn->prepare($docs_query);
if ($stmt) {
    $stmt->bind_param('i', $visa_id);
    $stmt->execute();
    $docs_result = $stmt->get_result();
    
    if ($docs_result && $docs_result->num_rows > 0) {
        while ($doc = $docs_result->fetch_assoc()) {
            if (!isset($required_docs[$doc['category_name']])) {
                $required_docs[$doc['category_name']] = [];
            }
            $required_docs[$doc['category_name']][] = $doc;
        }
    }
    $stmt->close();
}

// Get visa services
$services = [];
$services_query = "SELECT vs.*, st.service_name 
                  FROM visa_services vs
                  JOIN service_types st ON vs.service_type_id = st.service_type_id
                  WHERE vs.visa_id = ? AND vs.is_active = 1
                  ORDER BY st.service_name";
                  
$stmt = $conn->prepare($services_query);
if ($stmt) {
    $stmt->bind_param('i', $visa_id);
    $stmt->execute();
    $services_result = $stmt->get_result();
    
    if ($services_result && $services_result->num_rows > 0) {
        while ($service = $services_result->fetch_assoc()) {
            $services[] = $service;
        }
    }
    $stmt->close();
}

// Handle visa editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_visa'])) {
    $visa_type = trim($_POST['visa_type']);
    $description = trim($_POST['description']);
    $validity_period = !empty($_POST['validity_period']) ? intval($_POST['validity_period']) : null;
    $fee = !empty($_POST['fee']) ? floatval($_POST['fee']) : null;
    $requirements = trim($_POST['requirements']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($visa_type)) {
        $errors[] = "Visa type is required";
    }
    
    if (empty($errors)) {
        // Update visa
        $update_query = "UPDATE visas SET 
                         visa_type = ?, 
                         description = ?, 
                         validity_period = ?, 
                         fee = ?, 
                         requirements = ?, 
                         is_active = ? 
                         WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssiisii', $visa_type, $description, $validity_period, $fee, $requirements, $is_active, $visa_id);
        
        if ($stmt->execute()) {
            $success_message = "Visa updated successfully";
            $stmt->close();
            
            // Reload visa data
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $visa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $visa = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Error updating visa: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

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

.visa-details-container {
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

.country-actions {
    display: flex;
    gap: 10px;
}

.visas-table-container {
    padding: 15px;
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

.detail-rows {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.detail-row {
    display: flex;
    align-items: flex-start;
}

.detail-label {
    width: 200px;
    font-weight: 600;
    color: var(--primary-color);
    padding-right: 20px;
}

.detail-value {
    flex: 1;
    color: var(--dark-color);
}

.document-category-group {
    margin-bottom: 20px;
}

.document-category-group:last-child {
    margin-bottom: 0;
}

.document-category-name {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin: 0 0 10px 0;
    padding-bottom: 5px;
    border-bottom: 1px solid var(--border-color);
}

.document-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.document-item {
    margin-bottom: 10px;
    padding-left: 20px;
    position: relative;
}

.document-item:before {
    content: '\f15b';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 0;
    color: var(--secondary-color);
}

.document-name {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.document-notes {
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-top: 3px;
    padding-left: 5px;
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
    width: 120px;
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

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
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

.document-categories {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 15px;
}

.document-category {
    margin-bottom: 20px;
}

.category-title {
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    color: var(--primary-color);
}

.document-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-left: 15px;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.document-checkbox {
    margin: 0;
}

.mandatory-checkbox {
    margin-left: auto;
}

.notes-field {
    width: 100%;
    margin-top: 8px;
    display: none;
}

.notes-toggle {
    font-size: 12px;
    color: var(--secondary-color);
    cursor: pointer;
    margin-left: 10px;
}

.notes-toggle:hover {
    text-decoration: underline;
}

.text-center {
    text-align: center;
}

.p-4 {
    padding: 1rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mt-4 {
    margin-top: 1rem;
}

.modal-lg {
    max-width: 800px;
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
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .visas-table {
        display: block;
        overflow-x: auto;
    }
}

/* Document Category Cards */
.document-section {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.document-category-card {
    background-color: white;
    border-radius: 5px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    height: 100%;
    box-shadow: 0 1px 5px rgba(0, 0, 0, 0.03);
}

.document-category-header {
    background-color: var(--light-color);
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.document-category-name {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.document-category-body {
    padding: 15px;
}

.document-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.document-item {
    margin-bottom: 12px;
    padding-left: 24px;
    position: relative;
}

.document-item:last-child {
    margin-bottom: 0;
}

.document-item:before {
    content: '\f15b';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 0;
    color: var(--secondary-color);
}

/* Responsive adjustments for document cards */
@media (max-width: 768px) {
    .document-section {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Visa Details: <?php echo htmlspecialchars($visa['visa_type']); ?></h1>
            <p>Country: <?php echo htmlspecialchars($visa['country_name']); ?> (<?php echo htmlspecialchars($visa['country_code']); ?>)</p>
        </div>
        <div class="action-buttons">
            <button type="button" class="btn primary-btn" id="editVisaBtn">
                <i class="fas fa-edit"></i> Edit Visa
            </button>
            <a href="visa.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Visas
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="visa-details-container">
        <div class="country-card">
            <div class="country-header">
                <div class="country-info">
                    <h3>Visa Information</h3>
                    <?php if ($visa['is_active']): ?>
                        <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                        <?php if (!empty($visa['inactive_reason'])): ?>
                            <span class="inactive-reason">Reason: <?php echo htmlspecialchars($visa['inactive_reason']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="visas-table-container">
                <div class="detail-rows">
                    <div class="detail-row">
                        <div class="detail-label">Visa Type:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($visa['visa_type']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Description:</div>
                        <div class="detail-value"><?php echo !empty($visa['description']) ? nl2br(htmlspecialchars($visa['description'])) : '-'; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Validity Period:</div>
                        <div class="detail-value"><?php echo !empty($visa['validity_period']) ? $visa['validity_period'] . ' days' : '-'; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Fee:</div>
                        <div class="detail-value"><?php echo !empty($visa['fee']) ? '$' . number_format($visa['fee'], 2) : '-'; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Requirements:</div>
                        <div class="detail-value"><?php echo !empty($visa['requirements']) ? nl2br(htmlspecialchars($visa['requirements'])) : '-'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="country-card">
            <div class="country-header">
                <div class="country-info">
                    <h3>Required Documents</h3>
                </div>
                <div class="country-actions">
                    <button type="button" class="btn-action edit-btn" onclick="manageRequiredDocuments(<?php echo $visa['visa_id']; ?>, '<?php echo htmlspecialchars(addslashes($visa['visa_type'])); ?>')">
                        <i class="fas fa-edit"></i> Manage Documents
                    </button>
                </div>
            </div>
            
            <?php if (empty($required_docs)): ?>
                <div class="no-visas-message">
                    <i class="fas fa-info-circle"></i> No required documents specified for this visa.
                    <button type="button" class="btn-link" onclick="manageRequiredDocuments(<?php echo $visa['visa_id']; ?>, '<?php echo htmlspecialchars(addslashes($visa['visa_type'])); ?>')">
                        Add required documents
                    </button>
                </div>
            <?php else: ?>
                <div class="visas-table-container document-section">
                    <?php foreach ($required_docs as $category => $documents): ?>
                        <div class="document-category-card">
                            <div class="document-category-header">
                                <h4 class="document-category-name"><?php echo htmlspecialchars($category); ?></h4>
                            </div>
                            <div class="document-category-body">
                                <ul class="document-list">
                                    <?php foreach ($documents as $document): ?>
                                        <li class="document-item">
                                            <div class="document-name">
                                                <?php echo htmlspecialchars($document['document_name']); ?>
                                                <?php if ($document['is_mandatory']): ?>
                                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Required</span>
                                                <?php else: ?>
                                                    <span class="status-badge active"><i class="fas fa-circle"></i> Optional</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($document['notes'])): ?>
                                                <div class="document-notes">
                                                    <em>Note: <?php echo htmlspecialchars($document['notes']); ?></em>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="country-card">
            <div class="country-header">
                <div class="country-info">
                    <h3>Visa Services</h3>
                </div>
                <div class="country-actions">
                    <a href="services.php?visa_id=<?php echo $visa['visa_id']; ?>" class="btn-action add-visa-btn">
                        <i class="fas fa-cog"></i> Manage Services
                    </a>
                </div>
            </div>
            
            <?php if (empty($services)): ?>
                <div class="no-visas-message">
                    <i class="fas fa-info-circle"></i> No services configured for this visa.
                    <a href="services.php?visa_id=<?php echo $visa['visa_id']; ?>" class="btn-link">
                        Configure visa services
                    </a>
                </div>
            <?php else: ?>
                <div class="visas-table-container">
                    <table class="visas-table">
                        <thead>
                            <tr>
                                <th>Service Type</th>
                                <th>Base Price</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <div class="visa-name"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    </td>
                                    <td>$<?php echo number_format($service['base_price'], 2); ?></td>
                                    <td><?php echo !empty($service['description']) ? htmlspecialchars($service['description']) : '-'; ?></td>
                                    <td class="actions-cell">
                                        <a href="service_details.php?id=<?php echo $service['visa_service_id']; ?>" class="btn-action btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Visa Modal -->
<div class="modal" id="editVisaModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa_details.php?id=<?php echo $visa_id; ?>" method="POST" id="editVisaForm">
                    <div class="form-group">
                        <label for="visa_type">Visa Type*</label>
                        <input type="text" name="visa_type" id="visa_type" class="form-control" value="<?php echo htmlspecialchars($visa['visa_type']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($visa['description']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_period">Validity Period (days)</label>
                            <input type="number" name="validity_period" id="validity_period" class="form-control" min="1" value="<?php echo $visa['validity_period']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="fee">Fee ($)</label>
                            <input type="number" name="fee" id="fee" class="form-control" min="0" step="0.01" value="<?php echo $visa['fee']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" rows="4"><?php echo htmlspecialchars($visa['requirements']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="visa_is_active" value="1" <?php echo $visa['is_active'] ? 'checked' : ''; ?>>
                            <label for="visa_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_visa" class="btn submit-btn">Update Visa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Manage Required Documents Modal -->
<div class="modal" id="manageDocumentsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="documentsModalTitle">Manage Required Documents</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="documentsModalLoading" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading documents...</p>
                </div>
                <div id="documentsModalContent" style="display: none;">
                    <p class="mb-3">Select documents required for <strong id="visaTypeDisplay"></strong>:</p>
                    
                    <form id="requiredDocumentsForm">
                        <input type="hidden" id="modal_visa_id" name="visa_id">
                        
                        <div class="document-categories">
                            <!-- Document categories and types will be loaded here via AJAX -->
                        </div>
                        
                        <div class="form-buttons mt-4">
                            <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn submit-btn" id="saveRequiredDocsBtn">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functionality
// Open Edit Visa modal
document.getElementById('editVisaBtn').addEventListener('click', function() {
    document.getElementById('editVisaModal').style.display = 'block';
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

// Function to manage required documents for a visa
function manageRequiredDocuments(visaId, visaType) {
    // Set visa ID and title
    document.getElementById('modal_visa_id').value = visaId;
    document.getElementById('visaTypeDisplay').textContent = visaType;
    
    // Show loading, hide content
    document.getElementById('documentsModalLoading').style.display = 'block';
    document.getElementById('documentsModalContent').style.display = 'none';
    
    // Show modal
    document.getElementById('manageDocumentsModal').style.display = 'block';
    
    // Load document categories and types
    loadDocumentTypes(visaId);
}

// Function to load document types and categories
function loadDocumentTypes(visaId) {
    // First, get all document types grouped by category
    fetch('ajax/get_document_types.php')
        .then(response => response.json())
        .then(types => {
            // Then get currently required documents for this visa
            fetch('get_required_documents.php?visa_id=' + visaId)
                .then(response => response.json())
                .then(requiredDocs => {
                    // Build the document selection UI
                    buildDocumentSelectionUI(types, requiredDocs);
                    
                    // Hide loading, show content
                    document.getElementById('documentsModalLoading').style.display = 'none';
                    document.getElementById('documentsModalContent').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching required documents:', error);
                    alert('Error loading required documents. Please try again.');
                });
        })
        .catch(error => {
            console.error('Error fetching document types:', error);
            alert('Error loading document types. Please try again.');
        });
}

// Function to build document selection UI
function buildDocumentSelectionUI(documentsByCategory, requiredDocs) {
    const container = document.querySelector('.document-categories');
    container.innerHTML = '';
    
    // Create a map of required documents for easy lookup
    const requiredDocsMap = {};
    requiredDocs.forEach(doc => {
        requiredDocsMap[doc.document_name] = {
            is_mandatory: doc.is_mandatory,
            notes: doc.notes || ''
        };
    });
    
    // Build UI for each category
    for (const category in documentsByCategory) {
        const documents = documentsByCategory[category];
        
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'document-category';
        
        const categoryTitle = document.createElement('div');
        categoryTitle.className = 'category-title';
        categoryTitle.textContent = category;
        categoryDiv.appendChild(categoryTitle);
        
        const documentList = document.createElement('div');
        documentList.className = 'document-list';
        
        documents.forEach(doc => {
            const isRequired = requiredDocsMap.hasOwnProperty(doc.name);
            const isMandatory = isRequired && requiredDocsMap[doc.name].is_mandatory === '1';
            const notes = isRequired ? requiredDocsMap[doc.name].notes : '';
            
            const documentItem = document.createElement('div');
            documentItem.className = 'document-item';
            
            // Document selection checkbox
            const checkboxLabel = document.createElement('label');
            checkboxLabel.className = 'document-label';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'document-checkbox';
            checkbox.name = 'documents[]';
            checkbox.value = doc.id;
            checkbox.checked = isRequired;
            checkbox.dataset.documentName = doc.name;
            
            checkboxLabel.appendChild(checkbox);
            checkboxLabel.appendChild(document.createTextNode(' ' + doc.name));
            
            // Add a notes toggle
            const notesToggle = document.createElement('span');
            notesToggle.className = 'notes-toggle';
            notesToggle.textContent = 'Add Notes';
            notesToggle.onclick = function() {
                const notesField = this.parentElement.nextElementSibling;
                notesField.style.display = notesField.style.display === 'none' ? 'block' : 'none';
                this.textContent = notesField.style.display === 'none' ? 'Add Notes' : 'Hide Notes';
            };
            
            // Mandatory checkbox
            const mandatoryLabel = document.createElement('label');
            mandatoryLabel.className = 'mandatory-label';
            
            const mandatoryCheckbox = document.createElement('input');
            mandatoryCheckbox.type = 'checkbox';
            mandatoryCheckbox.className = 'mandatory-checkbox';
            mandatoryCheckbox.name = 'mandatory_' + doc.id;
            mandatoryCheckbox.checked = isMandatory;
            mandatoryCheckbox.disabled = !isRequired;
            
            checkbox.onchange = function() {
                mandatoryCheckbox.disabled = !this.checked;
                if (!this.checked) {
                    mandatoryCheckbox.checked = false;
                }
            };
            
            mandatoryLabel.appendChild(document.createTextNode('Mandatory '));
            mandatoryLabel.appendChild(mandatoryCheckbox);
            
            documentItem.appendChild(checkboxLabel);
            documentItem.appendChild(notesToggle);
            documentItem.appendChild(mandatoryLabel);
            
            // Notes textarea (initially hidden)
            const notesContainer = document.createElement('div');
            notesContainer.className = 'notes-field';
            notesContainer.style.display = notes ? 'block' : 'none';
            
            const notesTextarea = document.createElement('textarea');
            notesTextarea.className = 'form-control';
            notesTextarea.name = 'notes_' + doc.id;
            notesTextarea.placeholder = 'Add notes about this document';
            notesTextarea.rows = 2;
            notesTextarea.value = notes;
            
            notesContainer.appendChild(notesTextarea);
            
            documentList.appendChild(documentItem);
            documentList.appendChild(notesContainer);
            
            if (notes) {
                notesToggle.textContent = 'Hide Notes';
            }
        });
        
        categoryDiv.appendChild(documentList);
        container.appendChild(categoryDiv);
    }
    
    // Add save button event listener
    document.getElementById('saveRequiredDocsBtn').onclick = saveRequiredDocuments;
}

// Function to save required documents
function saveRequiredDocuments() {
    const form = document.getElementById('requiredDocumentsForm');
    const visaId = document.getElementById('modal_visa_id').value;
    
    // Get all selected documents
    const documentCheckboxes = form.querySelectorAll('.document-checkbox:checked');
    const documents = [];
    
    documentCheckboxes.forEach(checkbox => {
        const docId = checkbox.value;
        const mandatoryCheckbox = form.querySelector(`input[name="mandatory_${docId}"]`);
        const notesTextarea = form.querySelector(`textarea[name="notes_${docId}"]`);
        
        documents.push({
            document_id: docId,
            is_mandatory: mandatoryCheckbox.checked ? 1 : 0,
            notes: notesTextarea ? notesTextarea.value : ''
        });
    });
    
    // Send data to server
    fetch('ajax/save_required_documents.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            visa_id: visaId,
            documents: documents
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Required documents saved successfully!');
            window.location.reload(); // Reload the page to show updated document list
        } else {
            alert('Error: ' + (data.message || 'Could not save required documents'));
        }
    })
    .catch(error => {
        console.error('Error saving required documents:', error);
        alert('Error saving required documents. Please try again.');
    });
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>