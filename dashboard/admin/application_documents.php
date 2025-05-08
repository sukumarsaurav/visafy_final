<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Manage Application Documents";
$page_specific_css = "assets/css/application_documents.css";
require_once 'includes/header.php';

// Check if application ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: applications.php");
    exit;
}

$application_id = (int)$_GET['id'];

// Get application details with all related information
$query = "SELECT a.*, ast.name as status_name, ast.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as applicant_name, 
          u.email as applicant_email, u.id as applicant_id, u.profile_picture, u.status as applicant_status,
          v.visa_type, c.country_name, v.visa_id, c.country_id,
          CONCAT(cr.first_name, ' ', cr.last_name) as created_by_name
          FROM applications a
          JOIN application_statuses ast ON a.status_id = ast.id
          JOIN users u ON a.user_id = u.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN users cr ON a.created_by = cr.id
          WHERE a.id = ? AND a.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Application not found, redirect to applications list
    $stmt->close();
    header("Location: applications.php");
    exit;
}

$application = $result->fetch_assoc();
$stmt->close();

// Get all document categories
$query = "SELECT * FROM document_categories ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories_result = $stmt->get_result();
$document_categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $document_categories[$row['id']] = $row;
    }
}
$stmt->close();

// Get all document types
$query = "SELECT dt.*, dc.name as category_name 
          FROM document_types dt
          JOIN document_categories dc ON dt.category_id = dc.id
          WHERE dt.is_active = 1
          ORDER BY dc.name, dt.name";
$stmt = $conn->prepare($query);
$stmt->execute();
$types_result = $stmt->get_result();
$document_types = [];
$document_types_by_category = [];

if ($types_result && $types_result->num_rows > 0) {
    while ($row = $types_result->fetch_assoc()) {
        $document_types[$row['id']] = $row;
        
        if (!isset($document_types_by_category[$row['category_id']])) {
            $document_types_by_category[$row['category_id']] = [];
        }
        $document_types_by_category[$row['category_id']][] = $row;
    }
}
$stmt->close();

// Get required documents for this visa type
$query = "SELECT vrd.*, dt.name as document_name, dt.description as document_description, 
          dt.category_id, dc.name as category_name
          FROM visa_required_documents vrd
          JOIN document_types dt ON vrd.document_type_id = dt.id
          JOIN document_categories dc ON dt.category_id = dc.id
          WHERE vrd.visa_id = ?
          ORDER BY dc.name, dt.name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application['visa_id']);
$stmt->execute();
$required_docs_result = $stmt->get_result();
$required_documents = [];
$required_by_category = [];

if ($required_docs_result && $required_docs_result->num_rows > 0) {
    while ($row = $required_docs_result->fetch_assoc()) {
        $required_documents[$row['document_type_id']] = $row;
        
        if (!isset($required_by_category[$row['category_id']])) {
            $required_by_category[$row['category_id']] = [
                'name' => $row['category_name'],
                'documents' => []
            ];
        }
        $required_by_category[$row['category_id']]['documents'][] = $row;
    }
}
$stmt->close();

// Get current application documents
$query = "SELECT ad.*, dt.name as document_type_name, dt.description as document_type_description,
          dt.category_id, dc.name as category_name,
          CONCAT(s.first_name, ' ', s.last_name) as submitted_by_name,
          CONCAT(r.first_name, ' ', r.last_name) as reviewed_by_name
          FROM application_documents ad
          JOIN document_types dt ON ad.document_type_id = dt.id
          JOIN document_categories dc ON dt.category_id = dc.id
          LEFT JOIN users s ON ad.submitted_by = s.id
          LEFT JOIN users r ON ad.reviewed_by = r.id
          WHERE ad.application_id = ?
          ORDER BY dc.name, dt.name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];
$documents_by_category = [];
$uploaded_document_types = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
        $uploaded_document_types[$row['document_type_id']] = $row;
        
        if (!isset($documents_by_category[$row['category_id']])) {
            $documents_by_category[$row['category_id']] = [
                'name' => $row['category_name'],
                'documents' => []
            ];
        }
        $documents_by_category[$row['category_id']]['documents'][] = $row;
    }
}
$stmt->close();

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_type_id = $_POST['document_type_id'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Check if file was uploaded without errors
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['document_file']['tmp_name']);
        finfo_close($file_info);
        
        if (in_array($mime_type, $allowed_types)) {
            // Generate unique filename
            $file_extension = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
            $new_filename = 'doc_' . uniqid() . '.' . $file_extension;
            $upload_dir = '../uploads/documents/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            $file_path = 'uploads/documents/' . $new_filename;
            
            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Check if document already exists for this application and document type
                    $check_query = "SELECT id FROM application_documents 
                                   WHERE application_id = ? AND document_type_id = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param('ii', $application_id, $document_type_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $existing_doc = $check_result->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($existing_doc) {
                        // Update existing document
                        $update_query = "UPDATE application_documents 
                                        SET file_path = ?, status = 'submitted', 
                                        submitted_by = ?, submitted_at = NOW(),
                                        reviewed_by = NULL, reviewed_at = NULL,
                                        rejection_reason = NULL
                                        WHERE id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param('sii', $file_path, $user_id, $existing_doc['id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        $doc_id = $existing_doc['id'];
                        $action_type = 'updated';
                    } else {
                        // Insert new document
                        $insert_query = "INSERT INTO application_documents 
                                        (application_id, document_type_id, file_path, status, 
                                        submitted_by, submitted_at) 
                                        VALUES (?, ?, ?, 'submitted', ?, NOW())";
                        $stmt = $conn->prepare($insert_query);
                        $status = 'submitted';
                        $stmt->bind_param('iisi', $application_id, $document_type_id, $file_path, $user_id);
                        $stmt->execute();
                        $doc_id = $stmt->insert_id;
                        $stmt->close();
                        
                        $action_type = 'added';
                    }
                    
                    // Log the activity
                    $document_name = $document_types[$document_type_id]['name'];
                    $description = "Document " . $action_type . ": " . $document_name;
                    $activity_query = "INSERT INTO application_activity_logs 
                                      (application_id, user_id, activity_type, description, ip_address) 
                                      VALUES (?, ?, 'document_" . ($action_type === 'added' ? 'added' : 'updated') . "', ?, ?)";
                    $stmt = $conn->prepare($activity_query);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Commit the transaction
                    $conn->commit();
                    
                    $success_message = "Document successfully " . $action_type . ".";
                    
                    // Redirect to refresh the page content
                    header("Location: application_documents.php?id=" . $application_id . "&success=1");
                    exit;
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $error_message = "Error saving document: " . $e->getMessage();
                    
                    // Delete uploaded file if it exists
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $error_message = "Failed to move the uploaded file.";
            }
        } else {
            $error_message = "Invalid file type. Allowed types: PDF, JPEG, PNG, GIF, DOC, DOCX.";
        }
    } else {
        $error_message = "Error uploading file. Please try again.";
    }
}

// Handle document status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_document'])) {
    $document_id = $_POST['document_id'];
    $new_status = $_POST['document_status'];
    $rejection_reason = ($new_status === 'rejected') ? trim($_POST['rejection_reason']) : null;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update document status
        $update_query = "UPDATE application_documents 
                        SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() 
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssii', $new_status, $rejection_reason, $user_id, $document_id);
        $stmt->execute();
        $stmt->close();
        
        // Get document info for activity log
        $doc_query = "SELECT dt.name FROM application_documents ad
                     JOIN document_types dt ON ad.document_type_id = dt.id
                     WHERE ad.id = ?";
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param('i', $document_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        $doc_info = $doc_result->fetch_assoc();
        $doc_stmt->close();
        
        // Log the activity
        $description = "Document status updated to " . ucfirst($new_status) . ": " . $doc_info['name'];
        $activity_query = "INSERT INTO application_activity_logs 
                          (application_id, user_id, activity_type, description, ip_address) 
                          VALUES (?, ?, 'document_updated', ?, ?)";
        $stmt = $conn->prepare($activity_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Create notification for applicant
        $notification_type = 'document_' . $new_status;
        $notification_title = "Document " . ucfirst($new_status);
        $notification_content = "Your document '" . $doc_info['name'] . "' has been " . $new_status . ".";
        
        $notif_query = "INSERT INTO notifications 
                       (user_id, related_user_id, notification_type, title, content, 
                       related_to_type, related_to_id, is_actionable, action_url) 
                       VALUES (?, ?, ?, ?, ?, 'document', ?, ?, ?)";
        $stmt = $conn->prepare($notif_query);
        $is_actionable = $new_status === 'rejected' ? 1 : 0;
        $action_url = $new_status === 'rejected' ? "dashboard.php?tab=documents&rejected=1" : null;
        $stmt->bind_param('iisssisi', $application['applicant_id'], $user_id, $notification_type, 
                         $notification_title, $notification_content, $document_id, $is_actionable, $action_url);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Document status updated successfully";
        
        // Redirect to refresh the page content
        header("Location: application_documents.php?id=" . $application_id . "&status_updated=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating document status: " . $e->getMessage();
    }
}

// Handle document deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $document_id = $_POST['document_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get document file path and info before deleting
        $doc_query = "SELECT ad.file_path, dt.name FROM application_documents ad
                     JOIN document_types dt ON ad.document_type_id = dt.id
                     WHERE ad.id = ?";
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param('i', $document_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        $doc_info = $doc_result->fetch_assoc();
        $doc_stmt->close();
        
        if ($doc_info) {
            // Delete document record
            $delete_query = "DELETE FROM application_documents WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param('i', $document_id);
            $stmt->execute();
            $stmt->close();
            
            // Log the activity
            $description = "Document deleted: " . $doc_info['name'];
            $activity_query = "INSERT INTO application_activity_logs 
                              (application_id, user_id, activity_type, description, ip_address) 
                              VALUES (?, ?, 'document_updated', ?, ?)";
            $stmt = $conn->prepare($activity_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Delete the file from server
            $file_path = '../' . $doc_info['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Commit the transaction
            $conn->commit();
            
            $success_message = "Document successfully deleted.";
            
            // Redirect to refresh the page content
            header("Location: application_documents.php?id=" . $application_id . "&deleted=1");
            exit;
        } else {
            throw new Exception("Document not found.");
        }
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error deleting document: " . $e->getMessage();
    }
}

// Handle request for additional documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_documents'])) {
    $document_types = isset($_POST['requested_documents']) ? $_POST['requested_documents'] : [];
    $message = isset($_POST['request_message']) ? trim($_POST['request_message']) : '';
    
    if (!empty($document_types)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get document type names
            $doc_types_list = implode(',', array_map('intval', $document_types));
            $doc_names_query = "SELECT name FROM document_types WHERE id IN ($doc_types_list)";
            $doc_names_result = $conn->query($doc_names_query);
            $doc_names = [];
            
            while ($row = $doc_names_result->fetch_assoc()) {
                $doc_names[] = $row['name'];
            }
            
            // Update application status to 'additional_documents_requested'
            $status_id_query = "SELECT id FROM application_statuses WHERE name = 'additional_documents_requested'";
            $status_result = $conn->query($status_id_query);
            $status_row = $status_result->fetch_assoc();
            $status_id = $status_row['id'];
            
            $update_query = "UPDATE applications SET status_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ii', $status_id, $application_id);
            $stmt->execute();
            $stmt->close();
            
            // Add to status history
            $history_query = "INSERT INTO application_status_history (application_id, status_id, changed_by, notes) 
                             VALUES (?, ?, ?, ?)";
            $history_notes = "Additional documents requested: " . implode(", ", $doc_names);
            if (!empty($message)) {
                $history_notes .= "\n\nMessage: " . $message;
            }
            $stmt = $conn->prepare($history_query);
            $stmt->bind_param('iiis', $application_id, $status_id, $user_id, $history_notes);
            $stmt->execute();
            $stmt->close();
            
            // Log the activity
            $description = "Additional documents requested: " . implode(", ", $doc_names);
            $activity_query = "INSERT INTO application_activity_logs 
                              (application_id, user_id, activity_type, description, ip_address) 
                              VALUES (?, ?, 'status_changed', ?, ?)";
            $stmt = $conn->prepare($activity_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Create notification for applicant
            $notification_title = "Additional Documents Requested";
            $notification_content = "Please upload the following documents: " . implode(", ", $doc_names);
            if (!empty($message)) {
                $notification_content .= "\n\n" . $message;
            }
            
            $notif_query = "INSERT INTO notifications 
                           (user_id, related_user_id, notification_type, title, content, 
                           related_to_type, related_to_id, is_actionable, action_url) 
                           VALUES (?, ?, 'document_requested', ?, ?, 'application', ?, 1, ?)";
            $action_url = "dashboard.php?tab=documents&upload=1";
            $stmt = $conn->prepare($notif_query);
            $stmt->bind_param('iisssi', $application['applicant_id'], $user_id, 
                             $notification_title, $notification_content, $application_id, $action_url);
            $stmt->execute();
            $stmt->close();
            
            // Add a comment with the document request
            if (!empty($message)) {
                $comment_query = "INSERT INTO application_comments (application_id, user_id, comment, is_internal) 
                                VALUES (?, ?, ?, 0)";
                $comment_text = "Additional documents requested: " . implode(", ", $doc_names) . "\n\n" . $message;
                $stmt = $conn->prepare($comment_query);
                $stmt->bind_param('iis', $application_id, $user_id, $comment_text);
                $stmt->execute();
                $stmt->close();
            }
            
            // Commit the transaction
            $conn->commit();
            
            $success_message = "Documents requested successfully. Application status updated.";
            
            // Redirect to refresh the page content
            header("Location: application_documents.php?id=" . $application_id . "&requested=1");
            exit;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error requesting documents: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select at least one document to request.";
    }
}

// Check for success messages from redirects
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Document successfully uploaded.";
} elseif (isset($_GET['status_updated']) && $_GET['status_updated'] == '1') {
    $success_message = "Document status updated successfully.";
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success_message = "Document successfully deleted.";
} elseif (isset($_GET['requested']) && $_GET['requested'] == '1') {
    $success_message = "Documents requested successfully. Application status updated.";
}

// Calculate document completion percentage
$total_required = 0;
$total_approved = 0;

foreach ($required_documents as $doc) {
    if ($doc['is_mandatory']) {
        $total_required++;
        
        // Check if document is approved
        if (isset($uploaded_document_types[$doc['document_type_id']]) && 
            $uploaded_document_types[$doc['document_type_id']]['status'] == 'approved') {
            $total_approved++;
        }
    }
}

$document_completion = $total_required > 0 ? round(($total_approved / $total_required) * 100) : 0;
?>

<div class="content">
    <!-- Header with back button and application title -->
    <div class="header-container">
        <div class="left-section">
            <a href="view_application.php?id=<?php echo $application_id; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Application
            </a>
        </div>
        <div class="center-section">
            <h1>Manage Documents</h1>
            <div class="application-subtitle">
                <span class="application-ref"><?php echo htmlspecialchars($application['reference_number']); ?></span>
                <span class="application-type"><?php echo htmlspecialchars($application['visa_type'] . ' - ' . $application['country_name']); ?></span>
                <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                    <i class="fas fa-circle"></i> <?php echo ucwords(str_replace('_', ' ', $application['status_name'])); ?>
                </span>
            </div>
        </div>
        <div class="right-section">
            <button type="button" class="btn-action btn-request" onclick="openRequestModal()">
                <i class="fas fa-plus-circle"></i> Request Documents
            </button>
            <button type="button" class="btn-action btn-upload" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Upload Document
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Document Progress Card -->
    <div class="card progress-card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Document Completion Progress</h3>
        </div>
        <div class="card-body">
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $document_completion; ?>%"></div>
                </div>
                <div class="progress-stats">
                    <span class="progress-percentage"><?php echo $document_completion; ?>%</span>
                    <span class="progress-count"><?php echo $total_approved; ?> of <?php echo $total_required; ?> required documents</span>
                </div>
            </div>
            
            <div class="document-status-summary">
                <div class="status-item">
                    <div class="status-icon approved">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="status-text">
                        <span class="status-count"><?php 
                            $approved_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['status'] == 'approved') $approved_count++;
                            }
                            echo $approved_count;
                        ?></span>
                        <span class="status-label">Approved</span>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon rejected">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="status-text">
                        <span class="status-count"><?php 
                            $rejected_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['status'] == 'rejected') $rejected_count++;
                            }
                            echo $rejected_count;
                        ?></span>
                        <span class="status-label">Rejected</span>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-text">
                        <span class="status-count"><?php 
                            $pending_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['status'] == 'pending') $pending_count++;
                            }
                            echo $pending_count;
                        ?></span>
                        <span class="status-label">Pending</span>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon submitted">
                        <i class="fas fa-file-import"></i>
                    </div>
                    <div class="status-text">
                        <span class="status-count"><?php 
                            $submitted_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['status'] == 'submitted') $submitted_count++;
                            }
                            echo $submitted_count;
                        ?></span>
                        <span class="status-label">Submitted</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs for Required vs All Documents -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="required-docs">Required Documents</button>
            <button class="tab-btn" data-tab="all-docs">All Documents</button>
        </div>
        
        <!-- Required Documents Tab -->
        <div id="required-docs" class="tab-content active">
            <?php if (empty($required_documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Required Documents</h3>
                    <p>There are no specific documents required for this visa type.</p>
                </div>
            <?php else: ?>
                <?php foreach ($required_by_category as $category_id => $category): ?>
                    <div class="document-category">
                        <h3><i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?></h3>
                        <div class="documents-list">
                            <?php foreach ($category['documents'] as $doc): ?>
                                <div class="document-item <?php echo isset($uploaded_document_types[$doc['document_type_id']]) ? 'has-document status-' . $uploaded_document_types[$doc['document_type_id']]['status'] : 'missing'; ?>">
                                    <div class="document-info">
                                        <div class="document-name">
                                            <?php echo htmlspecialchars($doc['document_name']); ?>
                                            <?php if ($doc['is_mandatory']): ?>
                                                <span class="mandatory-badge">Required</span>
                                            <?php else: ?>
                                                <span class="optional-badge">Optional</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($doc['document_description'])): ?>
                                            <div class="document-description">
                                                <?php echo htmlspecialchars($doc['document_description']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($uploaded_document_types[$doc['document_type_id']])): 
                                            $uploaded_doc = $uploaded_document_types[$doc['document_type_id']];
                                        ?>
                                            <div class="document-status">
                                                <span class="status-indicator status-<?php echo $uploaded_doc['status']; ?>">
                                                    <?php echo ucfirst($uploaded_doc['status']); ?>
                                                </span>
                                                <span class="document-date">
                                                    Uploaded: <?php echo date('M d, Y', strtotime($uploaded_doc['submitted_at'])); ?>
                                                    by <?php echo htmlspecialchars($uploaded_doc['submitted_by_name']); ?>
                                                </span>
                                                <?php if ($uploaded_doc['status'] == 'approved' || $uploaded_doc['status'] == 'rejected'): ?>
                                                    <span class="document-date">
                                                        Reviewed: <?php echo date('M d, Y', strtotime($uploaded_doc['reviewed_at'])); ?>
                                                        by <?php echo htmlspecialchars($uploaded_doc['reviewed_by_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($uploaded_doc['status'] == 'rejected' && !empty($uploaded_doc['rejection_reason'])): ?>
                                                    <div class="rejection-reason">
                                                        <strong>Rejection reason:</strong> <?php echo htmlspecialchars($uploaded_doc['rejection_reason']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="document-status">
                                                <span class="status-indicator status-missing">Not Uploaded</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-actions">
                                        <?php if (isset($uploaded_document_types[$doc['document_type_id']])): 
                                            $uploaded_doc = $uploaded_document_types[$doc['document_type_id']];
                                        ?>
                                            <a href="../../<?php echo $uploaded_doc['file_path']; ?>" target="_blank" class="btn-view" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../../<?php echo $uploaded_doc['file_path']; ?>" download class="btn-download" title="Download Document">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn-review" title="Review Document" 
                                                onclick="openDocumentReviewModal(<?php echo $uploaded_doc['id']; ?>, '<?php echo $doc['document_name']; ?>', '<?php echo $uploaded_doc['status']; ?>')">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                            <button type="button" class="btn-delete" title="Delete Document" 
                                                onclick="openDeleteModal(<?php echo $uploaded_doc['id']; ?>, '<?php echo $doc['document_name']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-upload-single" title="Upload Document" 
                                                onclick="openUploadModalForType(<?php echo $doc['document_type_id']; ?>, '<?php echo $doc['document_name']; ?>')">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- All Documents Tab -->
        <div id="all-docs" class="tab-content">
            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Documents Uploaded</h3>
                    <p>No documents have been uploaded for this application yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($documents_by_category as $category_id => $category): ?>
                    <div class="document-category">
                        <h3><i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?></h3>
                        <div class="documents-list">
                            <?php foreach ($category['documents'] as $doc): ?>
                                <div class="document-item status-<?php echo $doc['status']; ?>">
                                    <div class="document-icon">
                                        <?php
                                        $icon_class = 'fa-file';
                                        $mime_type = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                        if (in_array($mime_type, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            $icon_class = 'fa-file-image';
                                        } elseif (in_array($mime_type, ['pdf'])) {
                                            $icon_class = 'fa-file-pdf';
                                        } elseif (in_array($mime_type, ['doc', 'docx'])) {
                                            $icon_class = 'fa-file-word';
                                        } elseif (in_array($mime_type, ['xls', 'xlsx'])) {
                                            $icon_class = 'fa-file-excel';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <div class="document-info">
                                        <div class="document-name">
                                            <?php echo htmlspecialchars($doc['document_type_name']); ?>
                                            <span class="document-status status-<?php echo $doc['status']; ?>">
                                                <?php echo ucfirst($doc['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($doc['document_type_description'])): ?>
                                            <div class="document-description">
                                                <?php echo htmlspecialchars($doc['document_type_description']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="document-meta">
                                            <span>Uploaded by <?php echo htmlspecialchars($doc['submitted_by_name']); ?></span>
                                            <span><?php echo date('M d, Y', strtotime($doc['submitted_at'])); ?></span>
                                        </div>
                                        
                                        <?php if ($doc['status'] == 'approved' || $doc['status'] == 'rejected'): ?>
                                            <div class="document-meta">
                                                <span>Reviewed by <?php echo htmlspecialchars($doc['reviewed_by_name']); ?></span>
                                                <span><?php echo date('M d, Y', strtotime($doc['reviewed_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($doc['status'] == 'rejected' && !empty($doc['rejection_reason'])): ?>
                                            <div class="rejection-reason">
                                                <strong>Rejection reason:</strong> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-actions">
                                        <a href="../../<?php echo $doc['file_path']; ?>" target="_blank" class="btn-view" title="View Document">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../<?php echo $doc['file_path']; ?>" download class="btn-download" title="Download Document">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn-review" title="Review Document" 
                                            onclick="openDocumentReviewModal(<?php echo $doc['id']; ?>, '<?php echo $doc['document_type_name']; ?>', '<?php echo $doc['status']; ?>')">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button type="button" class="btn-delete" title="Delete Document" 
                                            onclick="openDeleteModal(<?php echo $doc['id']; ?>, '<?php echo $doc['document_type_name']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Upload Document</h3>
            <span class="close" onclick="closeUploadModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="application_documents.php?id=<?php echo $application_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="document_type_id">Document Type:</label>
                    <select name="document_type_id" id="document_type_id" class="form-control" required>
                        <option value="">Select Document Type</option>
                        <?php foreach ($document_categories as $category): ?>
                            <optgroup label="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php foreach ($document_types_by_category[$category['id']] ?? [] as $doc_type): ?>
                                    <option value="<?php echo $doc_type['id']; ?>">
                                        <?php echo htmlspecialchars($doc_type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="document_file">File (PDF, JPEG, PNG, DOC, DOCX):</label>
                    <input type="file" name="document_file" id="document_file" class="form-control" required>
                    <small class="file-help">Maximum file size: 10MB</small>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (Optional):</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Review Modal -->
<div id="documentReviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt"></i> Review Document: <span id="document-name"></span></h3>
            <span class="close" onclick="closeDocumentReviewModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="application_documents.php?id=<?php echo $application_id; ?>" method="POST">
                <input type="hidden" name="document_id" id="document_id">
                
                <div class="form-group">
                    <label for="document_status">Update Status:</label>
                    <select name="document_status" id="document_status" class="form-control" required onchange="toggleRejectionReason()">
                        <option value="pending">Pending</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group" id="rejection-reason-group" style="display: none;">
                    <label for="rejection_reason">Rejection Reason:</label>
                    <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeDocumentReviewModal()">Cancel</button>
                    <button type="submit" name="update_document" class="btn btn-primary">Update Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Document Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-trash"></i> Delete Document</h3>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the document: <strong id="delete-document-name"></strong>?</p>
            <p class="warning">This action cannot be undone.</p>
            
            <form action="application_documents.php?id=<?php echo $application_id; ?>" method="POST">
                <input type="hidden" name="document_id" id="delete_document_id">
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_document" class="btn btn-danger">Delete Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Documents Modal -->
<div id="requestModal" class="modal">
    <div class="modal-content request-modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Request Additional Documents</h3>
            <span class="close" onclick="closeRequestModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="application_documents.php?id=<?php echo $application_id; ?>" method="POST">
                <div class="form-group">
                    <label>Select Documents to Request:</label>
                    <div class="document-selection">
                        <?php foreach ($document_categories as $category): ?>
                            <div class="document-category-selection">
                                <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                                <div class="checkbox-group">
                                    <?php foreach ($document_types_by_category[$category['id']] ?? [] as $doc_type): 
                                        // Skip if already uploaded
                                        if (isset($uploaded_document_types[$doc_type['id']])) {
                                            continue;
                                        }
                                    ?>
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="requested_documents[]" id="doc_<?php echo $doc_type['id']; ?>" 
                                                value="<?php echo $doc_type['id']; ?>">
                                            <label for="doc_<?php echo $doc_type['id']; ?>"><?php echo htmlspecialchars($doc_type['name']); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="request_message">Message to Applicant:</label>
                    <textarea name="request_message" id="request_message" class="form-control" rows="5" placeholder="Explain why these documents are needed..."></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeRequestModal()">Cancel</button>
                    <button type="submit" name="request_documents" class="btn btn-primary">Send Request</button>
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
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
}

/* Header Styles */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.left-section {
    min-width: 120px;
}

.center-section {
    flex: 1;
    text-align: center;
}

.right-section {
    display: flex;
    gap: 10px;
}

.center-section h1 {
    margin: 0;
    font-size: 1.8rem;
    color: var(--primary-color);
}

.application-subtitle {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 5px;
    flex-wrap: wrap;
}

.application-ref {
    font-weight: 600;
    color: var(--dark-color);
}

.application-type {
    font-weight: 500;
    color: var(--dark-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
    gap: 5px;
}

.status-badge i {
    font-size: 8px;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    background-color: var(--light-color);
    color: var(--dark-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    gap: 8px;
}

.btn-back:hover {
    background-color: #e9ecef;
    text-decoration: none;
    color: var(--dark-color);
}

.btn-action {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    border: none;
    font-size: 0.9rem;
    cursor: pointer;
    gap: 8px;
    text-decoration: none;
}

.btn-upload {
    background-color: var(--primary-color);
    color: white;
}

.btn-upload:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.btn-request {
    background-color: var(--info-color);
    color: white;
}

.btn-request:hover {
    background-color: #2c9faf;
    color: white;
    text-decoration: none;
}

/* Alerts */
.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert i {
    font-size: 1.2rem;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

/* Card Styles */
.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: visible;
    display: flex;
    flex-direction: column;
    height: auto;
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

/* Progress Bar */
.progress-container {
    margin-bottom: 20px;
}

.progress-bar {
    height: 10px;
    background-color: var(--border-color);
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background-color: var(--success-color);
    border-radius: 5px;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.progress-percentage {
    font-weight: 600;
    color: var(--dark-color);
}

.document-status-summary {
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.status-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.status-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-bottom: 8px;
}

.status-icon.approved {
    background-color: var(--success-color);
}

.status-icon.rejected {
    background-color: var(--danger-color);
}

.status-icon.pending {
    background-color: var(--warning-color);
}

.status-icon.submitted {
    background-color: var(--info-color);
}

.status-count {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
    line-height: 1;
}

.status-label {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

/* Tab Styles */
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
    padding: 15px 20px;
    background: none;
    border: none;
    font-weight: 600;
    font-size: 1rem;
    color: var(--dark-color);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    background-color: rgba(4, 33, 103, 0.03);
}

.tab-content {
    display: none;
    padding: 20px;
    max-height: 800px;
    overflow-y: auto;
}

.tab-content.active {
    display: block;
}

/* Document Categories and Lists */
.document-category {
    margin-bottom: 30px;
}

.document-category h3 {
    font-size: 1.1rem;
    color: var(--dark-color);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.documents-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background-color: white;
    gap: 15px;
    transition: all 0.2s;
}

.document-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.document-item.has-document {
    border-left: 5px solid var(--secondary-color);
}

.document-item.status-approved {
    border-left: 5px solid var(--success-color);
    background-color: rgba(28, 200, 138, 0.02);
}

.document-item.status-rejected {
    border-left: 5px solid var(--danger-color);
    background-color: rgba(231, 74, 59, 0.02);
}

.document-item.status-pending {
    border-left: 5px solid var(--warning-color);
    background-color: rgba(246, 194, 62, 0.02);
}

.document-item.status-submitted {
    border-left: 5px solid var(--info-color);
    background-color: rgba(54, 185, 204, 0.02);
}

.document-item.missing {
    border-left: 5px solid #ddd;
    background-color: rgba(0, 0, 0, 0.02);
}

.document-icon {
    width: 40px;
    height: 40px;
    background-color: #f0f4ff;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.document-info {
    flex: 1;
}

.document-name {
    font-weight: 600;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    color: var(--dark-color);
}

.document-description {
    margin-bottom: 10px;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.document-meta {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.document-date {
    font-size: 0.85rem;
    color: var(--secondary-color);
    display: block;
    margin-bottom: 3px;
}

.document-status {
    margin-top: 8px;
}

.document-actions {
    display: flex;
    gap: 5px;
}

.btn-view, .btn-download, .btn-review, .btn-delete, .btn-upload-single {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    color: white;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-download {
    background-color: var(--info-color);
}

.btn-download:hover {
    background-color: #2c9faf;
}

.btn-review {
    background-color: var(--warning-color);
}

.btn-review:hover {
    background-color: #e0b137;
}

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
    background-color: #c23a2d;
}

.btn-upload-single {
    background-color: var(--primary-color);
}

.btn-upload-single:hover {
    background-color: #031c56;
}

/* Status Indicators */
.status-indicator {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
    margin-bottom: 5px;
}

.status-indicator.status-approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-indicator.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-indicator.status-pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-indicator.status-submitted {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.status-indicator.status-missing {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.rejection-reason {
    font-size: 0.85rem;
    color: var(--danger-color);
    background-color: rgba(231, 74, 59, 0.05);
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 8px;
}

.mandatory-badge, .optional-badge {
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 10px;
}

.mandatory-badge {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.optional-badge {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.4;
}

.empty-state h3 {
    margin: 0 0 10px;
    font-size: 1.4rem;
    color: var(--dark-color);
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto;
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    width: 500px;
    max-width: 90%;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    animation: modalFadeIn 0.3s;
}

.request-modal-content {
    width: 700px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.close {
    color: var(--secondary-color);
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--dark-color);
}

.modal-body {
    padding: 20px;
}

/* Form Styling */
.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.file-help {
    display: block;
    margin-top: 5px;
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn {
    padding: 10px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.btn-cancel {
    background-color: var(--light-color);
    color: var(--dark-color);
    border: 1px solid var(--border-color);
}

.btn-cancel:hover {
    background-color: #e9ecef;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
    border: none;
}

.btn-danger:hover {
    background-color: #c23a2d;
}

.warning {
    color: var(--danger-color);
    font-weight: 500;
}

/* Document Selection in Request Modal */
.document-selection {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.document-category-selection {
    margin-bottom: 20px;
}

.document-category-selection h4 {
    font-size: 1rem;
    margin: 0 0 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-item input[type="checkbox"] {
    margin: 0;
}

.checkbox-item label {
    margin: 0;
    font-weight: normal;
    font-size: 0.9rem;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .checkbox-group {
        grid-template-columns: 1fr;
    }
    
    .document-status-summary {
        flex-wrap: wrap;
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .center-section {
        text-align: left;
        width: 100%;
    }
    
    .application-subtitle {
        justify-content: flex-start;
    }
    
    .right-section {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .document-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .document-actions {
        width: 100%;
        justify-content: flex-end;
        margin-top: 10px;
    }
}
</style>

<script>
// Modal Functions
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

function openUploadModalForType(documentTypeId, documentName) {
    document.getElementById('document_type_id').value = documentTypeId;
    document.getElementById('uploadModal').style.display = 'block';
}

function openDocumentReviewModal(documentId, documentName, currentStatus) {
    document.getElementById('document-name').innerText = documentName;
    document.getElementById('document_id').value = documentId;
    document.getElementById('document_status').value = currentStatus;
    
    // Toggle rejection reason field based on current selection
    toggleRejectionReason();
    
    document.getElementById('documentReviewModal').style.display = 'block';
}

function closeDocumentReviewModal() {
    document.getElementById('documentReviewModal').style.display = 'none';
}

function openDeleteModal(documentId, documentName) {
    document.getElementById('delete-document-name').innerText = documentName;
    document.getElementById('delete_document_id').value = documentId;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function openRequestModal() {
    document.getElementById('requestModal').style.display = 'block';
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
}

function toggleRejectionReason() {
    var status = document.getElementById('document_status').value;
    var rejectionGroup = document.getElementById('rejection-reason-group');
    
    if (status === 'rejected') {
        rejectionGroup.style.display = 'block';
    } else {
        rejectionGroup.style.display = 'none';
    }
}

// Close modals when clicking outside of them
window.onclick = function(event) {
    var uploadModal = document.getElementById('uploadModal');
    var reviewModal = document.getElementById('documentReviewModal');
    var deleteModal = document.getElementById('deleteModal');
    var requestModal = document.getElementById('requestModal');
    
    if (event.target === uploadModal) {
        uploadModal.style.display = 'none';
    } else if (event.target === reviewModal) {
        reviewModal.style.display = 'none';
    } else if (event.target === deleteModal) {
        deleteModal.style.display = 'none';
    } else if (event.target === requestModal) {
        requestModal.style.display = 'none';
    }
}

// Tab switching
document.addEventListener('DOMContentLoaded', function() {
    var tabButtons = document.querySelectorAll('.tab-btn');
    var tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var tabId = this.getAttribute('data-tab');
            
            // Deactivate all tabs
            tabButtons.forEach(function(btn) {
                btn.classList.remove('active');
            });
            
            tabContents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Activate clicked tab
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
