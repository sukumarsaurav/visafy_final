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

$page_title = "My Documents";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';

// Get all document categories
$query = "SELECT * FROM document_categories ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$stmt->close();

// Get all document types
$query = "SELECT dt.*, dc.name as category_name 
          FROM document_types dt 
          JOIN document_categories dc ON dt.category_id = dc.id 
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->execute();
$document_types_result = $stmt->get_result();
$document_types = [];

if ($document_types_result && $document_types_result->num_rows > 0) {
    while ($row = $document_types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}
$stmt->close();

// Get user's applications
$user_id = $_SESSION['id'];
$query = "SELECT a.id, a.reference_number, v.visa_type, c.country_name 
          FROM applications a
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          WHERE a.user_id = ? AND a.deleted_at IS NULL
          ORDER BY a.updated_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$applications_result = $stmt->get_result();
$applications = [];

if ($applications_result && $applications_result->num_rows > 0) {
    while ($row = $applications_result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$stmt->close();

// Get user's documents from application_documents
$query = "SELECT ad.id, ad.application_id, ad.document_type_id, ad.file_path, ad.status, 
          ad.rejection_reason, ad.submitted_at, ad.created_at, ad.updated_at, 
          dt.name as document_type_name, dt.category_id, 
          dc.name as category_name,
          a.reference_number, v.visa_type, c.country_name
          FROM application_documents ad
          JOIN applications a ON ad.application_id = a.id
          JOIN document_types dt ON ad.document_type_id = dt.id
          JOIN document_categories dc ON dt.category_id = dc.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          WHERE a.user_id = ?
          ORDER BY ad.updated_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];
$documents_by_category = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
        // Also organize by category for display
        if (!isset($documents_by_category[$row['category_id']])) {
            $documents_by_category[$row['category_id']] = [];
        }
        $documents_by_category[$row['category_id']][] = $row;
    }
}
$stmt->close();

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $application_id = $_POST['application_id'];
    $document_type_id = $_POST['document_type_id'];
    
    // Validate inputs
    $errors = [];
    if (empty($application_id)) {
        $errors[] = "Please select an application";
    }
    if (empty($document_type_id)) {
        $errors[] = "Please select a document type";
    }
    
    // Validate file upload
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] != UPLOAD_ERR_OK) {
        $errors[] = "Please select a file to upload";
    } else {
        $file = $_FILES['document_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        // Get file extension
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        // Validate file type
        if (!in_array(strtolower($file_ext), $allowed)) {
            $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowed);
        } 
        // Validate file size (max 10MB)
        else if ($file_size > 10 * 1024 * 1024) {
            $errors[] = "File is too large. Maximum size is 10MB";
        }
    }
    
    // Verify application belongs to user
    if (empty($errors)) {
        $check_query = "SELECT id FROM applications WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ii', $application_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $errors[] = "Invalid application selected";
        }
        $check_stmt->close();
    }
    
    // Check if document type already exists for this application
    if (empty($errors)) {
        $check_query = "SELECT id FROM application_documents WHERE application_id = ? AND document_type_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ii', $application_id, $document_type_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Document already exists, get its ID for updating
            $doc_row = $check_result->fetch_assoc();
            $existing_doc_id = $doc_row['id'];
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Generate unique filename
        $new_file_name = uniqid('doc_') . '.' . $file_ext;
        $upload_dir = '../../uploads/documents/';
        
        // Ensure directory exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $upload_path = $upload_dir . $new_file_name;
        $db_file_path = 'uploads/documents/' . $new_file_name; // Store relative path in DB
        
        // Upload file
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                if (isset($existing_doc_id)) {
                    // Update existing document record
                    $query = "UPDATE application_documents 
                              SET file_path = ?, status = 'submitted', submitted_by = ?, 
                                  submitted_at = NOW(), updated_at = NOW() 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('sii', $db_file_path, $user_id, $existing_doc_id);
                } else {
                    // Insert new document record
                    $query = "INSERT INTO application_documents 
                              (application_id, document_type_id, file_path, status, submitted_by, submitted_at) 
                              VALUES (?, ?, ?, 'submitted', ?, NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('iisi', $application_id, $document_type_id, $db_file_path, $user_id);
                }
                
                $stmt->execute();
                $stmt->close();
                
                // Log the activity
                $action = isset($existing_doc_id) ? 'document_updated' : 'document_added';
                $description = isset($existing_doc_id) ? 
                               "Document updated for application #{$application_id}" : 
                               "Document added to application #{$application_id}";
                
                $log_query = "INSERT INTO application_activity_logs 
                              (application_id, user_id, activity_type, description, ip_address) 
                              VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($log_query);
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt->bind_param('iisss', $application_id, $user_id, $action, $description, $ip);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Document uploaded successfully";
                header("Location: documents.php?success=1");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error saving document: " . $e->getMessage();
            }
        } else {
            $error_message = "Error uploading file";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Document uploaded successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Documents</h1>
            <p>Upload and manage your application documents</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="uploadDocumentBtn">
                <i class="fas fa-upload"></i> Upload Document
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filter options -->
    <div class="filter-container">
        <div class="filter-item">
            <label for="category-filter">Category:</label>
            <select id="category-filter" class="filter-select">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-select">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="document-search">Search:</label>
            <input type="text" id="document-search" placeholder="Search documents..." class="search-input">
        </div>
    </div>
    
    <!-- Documents by Category -->
    <div class="documents-container">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>You haven't uploaded any documents yet. Click the "Upload Document" button to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php if (isset($documents_by_category[$category['id']])): ?>
                    <div class="document-category" data-category="<?php echo $category['id']; ?>">
                        <div class="category-header">
                            <h2><i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?></h2>
                            <span class="doc-count"><?php echo count($documents_by_category[$category['id']]); ?> document<?php echo count($documents_by_category[$category['id']]) > 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="document-list">
                            <?php foreach ($documents_by_category[$category['id']] as $document): ?>
                                <div class="document-card" data-status="<?php echo $document['status']; ?>">
                                    <div class="doc-icon">
                                        <?php
                                        $file_ext = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                        $icon_class = 'fa-file';
                                        
                                        switch (strtolower($file_ext)) {
                                            case 'pdf':
                                                $icon_class = 'fa-file-pdf';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                                $icon_class = 'fa-file-image';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $icon_class = 'fa-file-word';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <div class="doc-details">
                                        <h3><?php echo htmlspecialchars($document['document_type_name']); ?></h3>
                                        <p class="app-info">
                                            <?php echo htmlspecialchars($document['visa_type']); ?> - 
                                            #<?php echo htmlspecialchars($document['reference_number']); ?>
                                        </p>
                                        <p class="doc-date">
                                            Uploaded: <?php echo date('M j, Y', strtotime($document['created_at'])); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="doc-status">
                                        <?php
                                        $status_class = 'status-' . $document['status'];
                                        $status_text = ucfirst($document['status']);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="doc-actions">
                                        <a href="../../<?php echo $document['file_path']; ?>" class="btn-icon" title="View Document" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($document['status'] !== 'approved'): ?>
                                            <button type="button" class="btn-icon" title="Replace Document" 
                                                    onclick="prepareUpload(<?php echo $document['application_id']; ?>, <?php echo $document['document_type_id']; ?>)">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal" id="uploadDocumentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Document</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" enctype="multipart/form-data" id="uploadDocumentForm">
                    <div class="form-group">
                        <label for="application_id">Application*</label>
                        <select name="application_id" id="application_id" class="form-control" required>
                            <option value="">Select Application</option>
                            <?php foreach ($applications as $application): ?>
                                <option value="<?php echo $application['id']; ?>"><?php echo htmlspecialchars($application['visa_type'] . ' - ' . $application['country_name'] . ' (#' . $application['reference_number'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <?php if ($type['is_active']): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?> (<?php echo htmlspecialchars($type['category_name']); ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="document_file">Upload File*</label>
                        <div class="custom-file-upload">
                            <input type="file" name="document_file" id="document_file" required>
                            <div class="file-upload-info">
                                <p class="upload-instruction">Click to browse or drag and drop file here</p>
                                <p class="file-requirements">Allowed formats: PDF, DOC, DOCX, JPG, PNG (Max size: 10MB)</p>
                            </div>
                        </div>
                        <div class="selected-file-info" style="display: none;">
                            <span id="selected-file-name"></span>
                            <button type="button" id="remove-file-btn" class="remove-file-btn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_document" class="btn submit-btn">Upload Document</button>
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

.filter-container {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    background-color: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    flex-wrap: wrap;
}

.filter-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-select {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-width: 150px;
}

.search-input {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-width: 200px;
}

.documents-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.document-category {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
}

.category-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.doc-count {
    background-color: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.document-list {
    padding: 15px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.document-card {
    background-color: var(--light-color);
    border-radius: 5px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    transition: transform 0.2s;
}

.document-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.doc-icon {
    font-size: 24px;
    color: var(--primary-color);
}

.doc-details h3 {
    margin: 0 0 5px;
    font-size: 16px;
    color: var(--dark-color);
}

.app-info, .doc-date {
    margin: 0;
    font-size: 13px;
    color: var(--secondary-color);
}

.doc-status {
    margin-top: auto;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.status-submitted {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.status-approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.doc-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.btn-icon:hover {
    background-color: #031c56;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
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

.custom-file-upload {
    position: relative;
    border: 2px dashed var(--border-color);
    border-radius: 5px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s;
}

.custom-file-upload:hover {
    border-color: var(--primary-color);
}

.custom-file-upload input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}

.file-upload-info {
    color: var(--secondary-color);
}

.upload-instruction {
    margin: 0 0 5px;
    font-weight: 500;
}

.file-requirements {
    margin: 0;
    font-size: 12px;
}

.selected-file-info {
    margin-top: 10px;
    padding: 8px 12px;
    background-color: var(--light-color);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.remove-file-btn {
    background: none;
    border: none;
    color: var(--danger-color);
    cursor: pointer;
    font-size: 16px;
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
    
    .filter-container {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .document-list {
        grid-template-columns: 1fr;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}
</style>

<script>
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

// Open upload document modal
document.getElementById('uploadDocumentBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('uploadDocumentForm').reset();
    document.querySelector('.selected-file-info').style.display = 'none';
    document.querySelector('.file-upload-info').style.display = 'block';
    
    // Enable selection fields
    document.getElementById('application_id').disabled = false;
    document.getElementById('document_type_id').disabled = false;
    
    // Change modal title back to default
    document.querySelector('.modal-title').textContent = 'Upload Document';
    
    openModal('uploadDocumentModal');
});

// Function to prepare upload form for a specific document replacement
function prepareUpload(applicationId, documentTypeId) {
    // Reset form
    document.getElementById('uploadDocumentForm').reset();
    document.querySelector('.selected-file-info').style.display = 'none';
    document.querySelector('.file-upload-info').style.display = 'block';
    
    // Set values for replacing existing document
    document.getElementById('application_id').value = applicationId;
    document.getElementById('document_type_id').value = documentTypeId;
    
    // Disable selection fields since we're replacing a specific document
    document.getElementById('application_id').disabled = true;
    document.getElementById('document_type_id').disabled = true;
    
    // Change modal title
    document.querySelector('.modal-title').textContent = 'Replace Document';
    
    // Open modal
    openModal('uploadDocumentModal');
}

// Handle file selection
document.getElementById('document_file').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileInfo = document.querySelector('.selected-file-info');
    const uploadInfo = document.querySelector('.file-upload-info');
    
    if (fileName) {
        document.getElementById('selected-file-name').textContent = fileName;
        fileInfo.style.display = 'flex';
        uploadInfo.style.display = 'none';
    } else {
        fileInfo.style.display = 'none';
        uploadInfo.style.display = 'block';
    }
});

// Handle file removal
document.getElementById('remove-file-btn').addEventListener('click', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('document_file');
    fileInput.value = '';
    document.querySelector('.selected-file-info').style.display = 'none';
    document.querySelector('.file-upload-info').style.display = 'block';
});

// Before form submission, re-enable disabled fields to ensure values are submitted
document.getElementById('uploadDocumentForm').addEventListener('submit', function() {
    document.getElementById('application_id').disabled = false;
    document.getElementById('document_type_id').disabled = false;
});

// Category filter functionality
document.getElementById('category-filter').addEventListener('change', function() {
    const categoryId = this.value;
    const categories = document.querySelectorAll('.document-category');
    
    if (categoryId === 'all') {
        categories.forEach(category => {
            category.style.display = 'block';
        });
    } else {
        categories.forEach(category => {
            if (category.getAttribute('data-category') === categoryId) {
                category.style.display = 'block';
            } else {
                category.style.display = 'none';
            }
        });
    }
});

// Status filter functionality
document.getElementById('status-filter').addEventListener('change', function() {
    const status = this.value;
    const documents = document.querySelectorAll('.document-card');
    
    if (status === 'all') {
        documents.forEach(doc => {
            doc.style.display = 'flex';
        });
    } else {
        documents.forEach(doc => {
            if (doc.getAttribute('data-status') === status) {
                doc.style.display = 'flex';
            } else {
                doc.style.display = 'none';
            }
        });
    }
    
    // Hide empty categories
    document.querySelectorAll('.document-category').forEach(category => {
        const visibleDocs = category.querySelectorAll('.document-card[style*="flex"]');
        if (visibleDocs.length === 0) {
            category.style.display = 'none';
        } else {
            category.style.display = 'block';
        }
    });
});

// Search functionality
document.getElementById('document-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const documents = document.querySelectorAll('.document-card');
    const categories = document.querySelectorAll('.document-category');
    
    // Set of categories that contain matching documents
    const matchingCategories = new Set();
    
    documents.forEach(doc => {
        const text = doc.textContent.toLowerCase();
        const categoryId = doc.closest('.document-category').getAttribute('data-category');
        
        if (text.includes(searchTerm)) {
            doc.style.display = 'flex';
            matchingCategories.add(categoryId);
        } else {
            doc.style.display = 'none';
        }
    });
    
    // Show only categories that have matching documents
    categories.forEach(category => {
        const categoryId = category.getAttribute('data-category');
        if (searchTerm === '' || matchingCategories.has(categoryId)) {
            category.style.display = 'block';
        } else {
            category.style.display = 'none';
        }
    });
});

// File drop functionality
const dropArea = document.querySelector('.custom-file-upload');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    dropArea.style.borderColor = '#042167';
    dropArea.style.backgroundColor = 'rgba(4, 33, 103, 0.03)';
}

function unhighlight() {
    dropArea.style.borderColor = '#e3e6f0';
    dropArea.style.backgroundColor = '';
}

dropArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const fileInput = document.getElementById('document_file');
    
    fileInput.files = files;
    
    // Trigger change event
    const event = new Event('change', { bubbles: true });
    fileInput.dispatchEvent(event);
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>