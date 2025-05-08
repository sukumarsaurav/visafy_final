<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: ../../login.php");
    exit;
}

$page_title = "My Applications";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Get user's applications
$user_id = $_SESSION['id'];
$query = "SELECT 
            a.id, 
            a.reference_number, 
            a.visa_id, 
            a.status_id, 
            a.submitted_at, 
            a.expected_completion_date,
            a.priority,
            a.created_at,
            a.updated_at,
            ast.name as status_name,
            ast.color as status_color,
            v.visa_type,
            c.country_name,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as total_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'pending') as pending_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'approved') as approved_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'rejected') as rejected_documents
          FROM applications a
          JOIN application_statuses ast ON a.status_id = ast.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          WHERE a.user_id = ? AND a.deleted_at IS NULL
          ORDER BY a.updated_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$applications = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$stmt->close();

// Get application statistics
$total_applications = count($applications);
$pending_documents = 0;
$active_applications = 0;
$completed_applications = 0;

foreach ($applications as $app) {
    $pending_documents += $app['pending_documents'];
    
    if (in_array($app['status_name'], ['completed', 'approved', 'rejected', 'cancelled'])) {
        $completed_applications++;
    } else {
        $active_applications++;
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $application_id = $_POST['application_id'];
    $document_type_id = $_POST['document_type_id'];
    
    // Verify application belongs to user
    $query = "SELECT id FROM applications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $application_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        $error_message = "Invalid application selected";
    } else {
        // Check if file was uploaded properly
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === 0) {
            $file_tmp = $_FILES['document_file']['tmp_name'];
            $file_name = $_FILES['document_file']['name'];
            $file_size = $_FILES['document_file']['size'];
            $file_type = $_FILES['document_file']['type'];
            
            // Validate file size and type
            $max_size = 10 * 1024 * 1024; // 10MB
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            
            if ($file_size > $max_size) {
                $error_message = "File size exceeds the maximum limit of 10MB";
            } elseif (!in_array($file_type, $allowed_types)) {
                $error_message = "File type not allowed. Please upload PDF, JPG or PNG files only";
            } else {
                // Create directory if it doesn't exist
                $upload_dir = "../../uploads/applications/{$application_id}/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_file_name = uniqid() . '_' . $file_name;
                $file_path = $upload_dir . $new_file_name;
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Move uploaded file
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Check if document already exists
                        $query = "SELECT id FROM application_documents 
                                  WHERE application_id = ? AND document_type_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('ii', $application_id, $document_type_id);
                        $stmt->execute();
                        $exists_result = $stmt->get_result();
                        
                        if ($exists_result->num_rows > 0) {
                            // Update existing document
                            $document_id = $exists_result->fetch_assoc()['id'];
                            $query = "UPDATE application_documents 
                                      SET file_path = ?, status = 'submitted', submitted_by = ?, 
                                      submitted_at = NOW(), rejection_reason = NULL
                                      WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('sii', $new_file_name, $user_id, $document_id);
                            $stmt->execute();
                        } else {
                            // Insert new document
                            $query = "INSERT INTO application_documents 
                                      (application_id, document_type_id, file_path, status, submitted_by, submitted_at) 
                                      VALUES (?, ?, ?, 'submitted', ?, NOW())";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('iisi', $application_id, $document_type_id, $new_file_name, $user_id);
                            $stmt->execute();
                        }
                        
                        // Log activity
                        $query = "INSERT INTO application_activity_logs 
                                  (application_id, user_id, activity_type, description, ip_address) 
                                  VALUES (?, ?, 'document_added', 'Document uploaded by applicant', ?)";
                        $stmt = $conn->prepare($query);
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $stmt->bind_param('iis', $application_id, $user_id, $ip);
                        $stmt->execute();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $success_message = "Document uploaded successfully.";
                        header("Location: applications.php?success=1");
                        exit;
                    } else {
                        throw new Exception("Failed to move uploaded file.");
                    }
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error_message = "Error uploading document: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Please select a file to upload";
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Document uploaded successfully.";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Applications</h1>
            <p>Track and manage your visa applications</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $total_applications; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $active_applications; ?></div>
                <div class="stat-label">Active Applications</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $pending_documents; ?></div>
                <div class="stat-label">Pending Documents</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $completed_applications; ?></div>
                <div class="stat-label">Completed Applications</div>
            </div>
        </div>
    </div>
    
    <!-- Applications List -->
    <div class="section">
        <div class="section-header">
            <h2>My Applications</h2>
        </div>
        <div class="applications-list">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>You don't have any applications yet.</p>
                    <p>An application will be created for you once you book a consultation or your case manager initiates one.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="application-ref">
                                #<?php echo htmlspecialchars($application['reference_number']); ?>
                            </div>
                            <div class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                                <i class="fas fa-circle"></i> <?php echo ucfirst($application['status_name']); ?>
                            </div>
                        </div>
                        
                        <div class="application-details">
                            <h3><?php echo htmlspecialchars($application['visa_type'] . ' - ' . $application['country_name']); ?></h3>
                            
                            <div class="details-row">
                                <div class="detail">
                                    <span class="detail-label">Submitted:</span>
                                    <span class="detail-value">
                                        <?php echo $application['submitted_at'] ? date('M d, Y', strtotime($application['submitted_at'])) : 'Not submitted'; ?>
                                    </span>
                                </div>
                                
                                <div class="detail">
                                    <span class="detail-label">Expected Completion:</span>
                                    <span class="detail-value">
                                        <?php echo $application['expected_completion_date'] ? date('M d, Y', strtotime($application['expected_completion_date'])) : 'Not specified'; ?>
                                    </span>
                                </div>
                                
                                <div class="detail">
                                    <span class="detail-label">Priority:</span>
                                    <span class="detail-value priority-badge priority-<?php echo $application['priority']; ?>">
                                        <?php echo ucfirst($application['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="document-progress">
                                <div class="progress-label">
                                    <span>Documents</span>
                                    <span class="progress-stats">
                                        <?php echo $application['approved_documents']; ?> approved / 
                                        <?php echo $application['pending_documents']; ?> pending / 
                                        <?php echo $application['rejected_documents']; ?> rejected
                                    </span>
                                </div>
                                
                                <?php 
                                $total_docs = $application['total_documents'] > 0 ? $application['total_documents'] : 1;
                                $approved_percent = ($application['approved_documents'] / $total_docs) * 100;
                                $pending_percent = ($application['pending_documents'] / $total_docs) * 100;
                                $rejected_percent = ($application['rejected_documents'] / $total_docs) * 100;
                                ?>
                                
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo $approved_percent; ?>%"></div>
                                    <div class="progress-bar bg-warning" style="width: <?php echo $pending_percent; ?>%"></div>
                                    <div class="progress-bar bg-danger" style="width: <?php echo $rejected_percent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="application-actions">
                            <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <a href="upload_documents.php?id=<?php echo $application['id']; ?>" class="btn-action btn-primary">
                                <i class="fas fa-upload"></i> Upload Documents
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

/* Stats */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-right: 15px;
    color: white;
}

.bg-primary {
    background-color: var(--primary-color);
}

.bg-warning {
    background-color: var(--warning-color);
}

.bg-danger {
    background-color: var(--danger-color);
}

.bg-success {
    background-color: var(--success-color);
}

.stat-details {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark-color);
    line-height: 1;
}

.stat-label {
    color: var(--secondary-color);
    font-size: 14px;
    margin-top: 5px;
}

/* Section */
.section {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.applications-list {
    padding: 20px;
}

/* Empty State */
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

.empty-state p {
    margin: 0 0 10px;
}

/* Application Card */
.application-card {
    border: 1px solid var(--border-color);
    border-radius: 5px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s;
}

.application-card:hover {
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
}

.application-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.application-ref {
    font-weight: 600;
    color: var(--dark-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge i {
    font-size: 8px;
}

.application-details {
    padding: 15px;
}

.application-details h3 {
    margin: 0 0 15px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.details-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 15px;
}

.detail {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 120px;
}

.detail-label {
    font-size: 12px;
    color: var(--secondary-color);
    margin-bottom: 4px;
}

.detail-value {
    color: var(--dark-color);
    font-weight: 500;
}

.priority-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    text-transform: capitalize;
}

.priority-low {
    background-color: #e3f2fd;
    color: #1976d2;
}

.priority-normal {
    background-color: #f1f8e9;
    color: #689f38;
}

.priority-high {
    background-color: #fff8e1;
    color: #ffa000;
}

.priority-urgent {
    background-color: #ffebee;
    color: #d32f2f;
}

.document-progress {
    margin-top: 15px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 14px;
}

.progress-stats {
    color: var(--secondary-color);
    font-size: 12px;
}

.progress {
    height: 8px;
    background-color: #f1f1f1;
    border-radius: 4px;
    overflow: hidden;
    display: flex;
}

.progress-bar {
    height: 100%;
    transition: width 0.3s;
}

.bg-success {
    background-color: var(--success-color);
}

.bg-warning {
    background-color: var(--warning-color);
}

.bg-danger {
    background-color: var(--danger-color);
}

.application-actions {
    display: flex;
    padding: 15px;
    border-top: 1px solid var(--border-color);
    gap: 10px;
    justify-content: flex-end;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
    color: white;
}

.btn-view {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-view:hover {
    background-color: var(--light-color);
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(auto-fit, minmax(100%, 1fr));
    }
    
    .details-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .application-actions {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
