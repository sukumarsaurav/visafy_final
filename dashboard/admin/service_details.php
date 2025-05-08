<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Service Details";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Check if service ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: services.php");
    exit;
}

$service_id = intval($_GET['id']);

// Get service details with related information
$query = "SELECT vs.*, v.visa_type, v.description as visa_description, v.validity_period, v.fee as visa_fee, 
          v.country_id, c.country_name, c.country_code, st.service_name, st.description as service_type_description 
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

// Get consultation modes for this service
$consultation_modes = [];
$modes_query = "SELECT scm.*, cm.mode_name, cm.description as mode_description, cm.is_custom
               FROM service_consultation_modes scm
               JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
               WHERE scm.visa_service_id = ? AND scm.is_available = 1
               ORDER BY cm.mode_name";
$stmt = $conn->prepare($modes_query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$modes_result = $stmt->get_result();

if ($modes_result && $modes_result->num_rows > 0) {
    while ($mode = $modes_result->fetch_assoc()) {
        $consultation_modes[] = $mode;
    }
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Service Details</h1>
            <p><?php echo htmlspecialchars($service['service_name']); ?> - <?php echo htmlspecialchars($service['visa_type']); ?> for <?php echo htmlspecialchars($service['country_name']); ?></p>
        </div>
        <div class="action-buttons">
            <a href="services.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Services
            </a>
            <a href="edit_service.php?id=<?php echo $service_id; ?>" class="btn primary-btn">
                <i class="fas fa-edit"></i> Edit Service
            </a>
        </div>
    </div>
    
    <div class="service-details-container">
        <div class="service-card">
            <div class="card-header">
                <h2>Service Information</h2>
                <div class="status-badge <?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                    <i class="fas fa-circle"></i> <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
            
            <div class="card-body">
                <div class="detail-section">
                    <h3>Basic Details</h3>
                    <div class="detail-row">
                        <div class="detail-label">Country:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($service['country_name']); ?> (<?php echo htmlspecialchars($service['country_code']); ?>)</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Visa Type:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($service['visa_type']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Service Type:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($service['service_name']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Base Price:</div>
                        <div class="detail-value">$<?php echo number_format($service['base_price'], 2); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Created On:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($service['created_at'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($service['updated_at'])); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($service['description'])): ?>
                <div class="detail-section">
                    <h3>Service Description</h3>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($service['service_type_description'])): ?>
                <div class="detail-section">
                    <h3>Service Type Description</h3>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($service['service_type_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($service['visa_description'])): ?>
                <div class="detail-section">
                    <h3>Visa Description</h3>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($service['visa_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($service['visa_fee']): ?>
                <div class="detail-section">
                    <h3>Visa Information</h3>
                    <?php if ($service['visa_fee']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Visa Fee:</div>
                        <div class="detail-value">$<?php echo number_format($service['visa_fee'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($service['validity_period']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Validity Period:</div>
                        <div class="detail-value"><?php echo $service['validity_period']; ?> days</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="consultation-modes-card">
            <div class="card-header">
                <h2>Consultation Modes</h2>
                <div class="mode-count"><?php echo count($consultation_modes); ?> Mode(s)</div>
            </div>
            
            <div class="card-body">
                <?php if (empty($consultation_modes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No consultation modes available for this service.</p>
                    </div>
                <?php else: ?>
                    <div class="consultation-modes-list">
                        <?php foreach ($consultation_modes as $mode): ?>
                            <div class="consultation-mode-item">
                                <div class="mode-header">
                                    <h3><?php echo htmlspecialchars($mode['mode_name']); ?></h3>
                                    <?php if ($mode['is_custom']): ?>
                                        <span class="custom-badge">Custom</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mode-details">
                                    <?php if ($mode['additional_fee'] > 0): ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Additional Fee:</div>
                                            <div class="detail-value">$<?php echo number_format($mode['additional_fee'], 2); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($mode['duration_minutes']): ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Duration:</div>
                                            <div class="detail-value"><?php echo $mode['duration_minutes']; ?> minutes</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($mode['mode_description'])): ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Description:</div>
                                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($mode['mode_description'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-row">
                                        <div class="detail-label">Total Price:</div>
                                        <div class="detail-value total-price">
                                            $<?php echo number_format($service['base_price'] + $mode['additional_fee'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.service-details-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.service-card, .consultation-modes-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.service-card > .card-body, .consultation-modes-card > .card-body {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: #f8f9fc;
    position: sticky;
    top: 0;
    z-index: 1;
}

.card-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.mode-count {
    background-color: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.card-body {
    padding: 20px;
}

.detail-section {
    margin-bottom: 25px;
}

.detail-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 1.1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
}

.detail-label {
    font-weight: 600;
    color: var(--dark-color);
    width: 140px;
    flex-shrink: 0;
}

.detail-value {
    color: var(--secondary-color);
    flex: 1;
}

.description-box {
    background-color: #f9f9f9;
    border-radius: 4px;
    padding: 15px;
    color: var(--secondary-color);
    line-height: 1.5;
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.consultation-modes-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.consultation-mode-item {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
}

.mode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.mode-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.custom-badge {
    background-color: #e0eaff;
    color: #4285f4;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.mode-details {
    padding-top: 10px;
}

.total-price {
    font-weight: 600;
    color: var(--primary-color) !important;
    font-size: 1.1em;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.secondary-btn:hover {
    background-color: #f8f9fc;
}

@media (max-width: 992px) {
    .service-details-container {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
