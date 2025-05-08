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

// Check if application ID is provided in URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: applications.php");
    exit;
}

$application_id = intval($_GET['id']);
$user_id = $_SESSION['id'];

$page_title = "Application Details";
$page_specific_css = "assets/css/view_application.css";
require_once 'includes/header.php';

// Get application details
$query = "SELECT 
            a.id, 
            a.reference_number, 
            a.visa_id, 
            a.status_id, 
            a.submitted_at, 
            a.expected_completion_date,
            a.priority,
            a.notes,
            a.created_at,
            a.updated_at,
            ast.name as status_name,
            ast.color as status_color,
            v.visa_type,
            c.country_name,
            CONCAT(tm_u.first_name, ' ', tm_u.last_name) as case_manager_name,
            tm.role as case_manager_role,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as total_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'pending') as pending_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'approved') as approved_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'rejected') as rejected_documents,
            (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'submitted') as submitted_documents
          FROM applications a
          JOIN application_statuses ast ON a.status_id = ast.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          LEFT JOIN application_assignments aa ON a.id = aa.application_id AND aa.status = 'active'
          LEFT JOIN team_members tm ON aa.team_member_id = tm.id
          LEFT JOIN users tm_u ON tm.user_id = tm_u.id
          WHERE a.id = ? AND a.user_id = ? AND a.deleted_at IS NULL";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Application not found or doesn't belong to user
    header("Location: applications.php");
    exit;
}

$application = $result->fetch_assoc();
$stmt->close();

// Get application documents
$query = "SELECT ad.id, ad.document_type_id, ad.file_path, ad.status, 
          ad.rejection_reason, ad.submitted_at, ad.reviewed_at, ad.created_at, ad.updated_at, 
          dt.name as document_type_name, dt.description as document_type_description, dt.category_id, 
          dc.name as category_name,
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

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
        
        if (!isset($documents_by_category[$row['category_id']])) {
            $documents_by_category[$row['category_id']] = [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'documents' => []
            ];
        }
        $documents_by_category[$row['category_id']]['documents'][] = $row;
    }
}
$stmt->close();

// Get application required documents
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
$uploaded_document_types = [];

if ($required_docs_result && $required_docs_result->num_rows > 0) {
    while ($row = $required_docs_result->fetch_assoc()) {
        $required_documents[] = $row;
        
        if (!isset($required_by_category[$row['category_id']])) {
            $required_by_category[$row['category_id']] = [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'documents' => []
            ];
        }
        $required_by_category[$row['category_id']]['documents'][] = $row;
    }
}
$stmt->close();

// Map uploaded documents
foreach ($documents as $doc) {
    $uploaded_document_types[$doc['document_type_id']] = $doc;
}

// Get application comments (non-internal only for applicants)
$query = "SELECT ac.*, CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.profile_picture, u.user_type
          FROM application_comments ac
          JOIN users u ON ac.user_id = u.id
          WHERE ac.application_id = ? AND ac.is_internal = 0 AND ac.deleted_at IS NULL
          ORDER BY ac.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$comments_result = $stmt->get_result();
$comments = [];

if ($comments_result && $comments_result->num_rows > 0) {
    while ($row = $comments_result->fetch_assoc()) {
        $comments[] = $row;
    }
}
$stmt->close();

// Get status history
$query = "SELECT ash.*, ast.name as status_name, ast.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
          FROM application_status_history ash
          JOIN application_statuses ast ON ash.status_id = ast.id
          JOIN users u ON ash.changed_by = u.id
          WHERE ash.application_id = ?
          ORDER BY ash.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$history_result = $stmt->get_result();
$status_history = [];

if ($history_result && $history_result->num_rows > 0) {
    while ($row = $history_result->fetch_assoc()) {
        $status_history[] = $row;
    }
}
$stmt->close();

// Get activity logs
$query = "SELECT aal.*, CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.profile_picture, u.user_type
          FROM application_activity_logs aal
          JOIN users u ON aal.user_id = u.id
          WHERE aal.application_id = ?
          ORDER BY aal.created_at DESC";
          
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$activity_result = $stmt->get_result();
$activity_logs = [];

if ($activity_result && $activity_result->num_rows > 0) {
    while ($row = $activity_result->fetch_assoc()) {
        $activity_logs[] = $row;
    }
}
$stmt->close();

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment_text = trim($_POST['comment']);
    
    if (!empty($comment_text)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert comment
            $query = "INSERT INTO application_comments (application_id, user_id, comment, is_internal) 
                      VALUES (?, ?, ?, 0)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iis', $application_id, $user_id, $comment_text);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            $query = "INSERT INTO application_activity_logs 
                     (application_id, user_id, activity_type, description, ip_address) 
                     VALUES (?, ?, 'comment_added', 'Comment added by applicant', ?)";
            $stmt = $conn->prepare($query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iis', $application_id, $user_id, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Comment added successfully";
            header("Location: view_application.php?id=" . $application_id . "&success=comment_added");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error adding comment: " . $e->getMessage();
        }
    } else {
        $error_message = "Comment cannot be empty";
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'comment_added':
            $success_message = "Comment added successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1><?php echo htmlspecialchars($application['visa_type']); ?> - <?php echo htmlspecialchars($application['country_name']); ?></h1>
            <p>Application #<?php echo htmlspecialchars($application['reference_number']); ?></p>
        </div>
        <div class="status-container">
            <div class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                <i class="fas fa-circle"></i> <?php echo ucfirst($application['status_name']); ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Application Overview -->
    <div class="section">
        <div class="section-header">
            <h2>Application Overview</h2>
        </div>
        <div class="section-body">
            <div class="overview-grid">
                <div class="overview-item">
                    <div class="item-label">Status</div>
                    <div class="item-value">
                        <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                            <?php echo ucfirst($application['status_name']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Priority</div>
                    <div class="item-value">
                        <span class="priority-badge priority-<?php echo $application['priority']; ?>">
                            <?php echo ucfirst($application['priority']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Submitted</div>
                    <div class="item-value">
                        <?php echo $application['submitted_at'] ? date('M d, Y', strtotime($application['submitted_at'])) : 'Not submitted'; ?>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Expected Completion</div>
                    <div class="item-value">
                        <?php echo $application['expected_completion_date'] ? date('M d, Y', strtotime($application['expected_completion_date'])) : 'Not specified'; ?>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Created</div>
                    <div class="item-value">
                        <?php echo date('M d, Y', strtotime($application['created_at'])); ?>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Last Updated</div>
                    <div class="item-value">
                        <?php echo date('M d, Y', strtotime($application['updated_at'])); ?>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Case Manager</div>
                    <div class="item-value">
                        <?php echo !empty($application['case_manager_name']) ? htmlspecialchars($application['case_manager_name']) . ' (' . htmlspecialchars($application['case_manager_role']) . ')' : 'Not assigned'; ?>
                    </div>
                </div>
                
                <div class="overview-item">
                    <div class="item-label">Documents Status</div>
                    <div class="item-value">
                        <div class="doc-stats">
                            <span class="doc-stat approved"><?php echo $application['approved_documents']; ?> approved</span>
                            <span class="doc-stat pending"><?php echo $application['pending_documents'] + $application['submitted_documents']; ?> pending</span>
                            <span class="doc-stat rejected"><?php echo $application['rejected_documents']; ?> rejected</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($application['notes'])): ?>
            <div class="application-notes">
                <h3>Notes</h3>
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($application['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="upload_documents.php?id=<?php echo $application_id; ?>" class="btn primary-btn">
                    <i class="fas fa-upload"></i> Upload Documents
                </a>
                <a href="applications.php" class="btn secondary-btn">
                    <i class="fas fa-arrow-left"></i> Back to Applications
                </a>
            </div>
        </div>
    </div>
    
    <!-- Documents Section -->
    <div class="section">
        <div class="section-header">
            <h2>Required Documents</h2>
            <a href="upload_documents.php?id=<?php echo $application_id; ?>" class="btn-sm primary-btn">
                <i class="fas fa-upload"></i> Upload Documents
            </a>
        </div>
        <div class="section-body">
            <?php if (empty($required_documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No required documents have been specified for this application.</p>
                </div>
            <?php else: ?>
                <div class="documents-container">
                    <?php foreach ($required_by_category as $category): ?>
                        <div class="document-category">
                            <div class="category-header">
                                <h3><i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?></h3>
                            </div>
                            <div class="document-list">
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
                                                <div class="document-upload-info">
                                                    <div class="upload-detail">
                                                        <span class="label">Submitted:</span>
                                                        <span class="value"><?php echo date('M d, Y', strtotime($uploaded_doc['submitted_at'])); ?></span>
                                                    </div>
                                                    <div class="upload-detail">
                                                        <span class="label">Status:</span>
                                                        <span class="value status-text status-<?php echo $uploaded_doc['status']; ?>">
                                                            <?php echo ucfirst($uploaded_doc['status']); ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($uploaded_doc['status'] === 'rejected' && !empty($uploaded_doc['rejection_reason'])): ?>
                                                        <div class="upload-detail rejection-reason">
                                                            <span class="label">Rejection Reason:</span>
                                                            <span class="value"><?php echo htmlspecialchars($uploaded_doc['rejection_reason']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="document-missing">
                                                    <i class="fas fa-exclamation-circle"></i> Document not uploaded yet
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="document-actions">
                                            <?php if (isset($uploaded_document_types[$doc['document_type_id']])): 
                                                $uploaded_doc = $uploaded_document_types[$doc['document_type_id']];
                                            ?>
                                                <a href="../../<?php echo $uploaded_doc['file_path']; ?>" class="btn-icon" title="View Document" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($uploaded_doc['status'] !== 'approved'): ?>
                                                    <a href="upload_documents.php?id=<?php echo $application_id; ?>&document_id=<?php echo $uploaded_doc['document_type_id']; ?>" class="btn-icon" title="Replace Document">
                                                        <i class="fas fa-upload"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="upload_documents.php?id=<?php echo $application_id; ?>&document_id=<?php echo $doc['document_type_id']; ?>" class="btn-icon btn-upload" title="Upload Document">
                                                    <i class="fas fa-upload"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Comments Section -->
    <div class="section">
        <div class="section-header">
            <h2>Comments</h2>
        </div>
        <div class="section-body">
            <div class="add-comment-form">
                <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                    <textarea name="comment" placeholder="Write a comment..." required></textarea>
                    <div class="comment-actions">
                        <button type="submit" name="add_comment" class="btn primary-btn">
                            <i class="fas fa-paper-plane"></i> Add Comment
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="comments-container">
                <?php if (empty($comments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No comments yet. Be the first to add a comment.</p>
                    </div>
                <?php else: ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-avatar">
                                    <?php if (!empty($comment['profile_picture'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($comment['profile_picture']); ?>" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <div class="comment-author">
                                            <span class="author-name"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                                            <span class="author-role"><?php echo ucfirst($comment['user_type']); ?></span>
                                        </div>
                                        <div class="comment-date">
                                            <?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Activity Log Section -->
    <div class="section">
        <div class="section-header">
            <h2>Activity Log</h2>
        </div>
        <div class="section-body">
            <?php if (empty($activity_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No activity has been recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($activity_logs as $log): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?php
                                switch ($log['activity_type']) {
                                    case 'created':
                                        echo '<i class="fas fa-plus-circle"></i>';
                                        break;
                                    case 'updated':
                                        echo '<i class="fas fa-edit"></i>';
                                        break;
                                    case 'status_changed':
                                        echo '<i class="fas fa-sync-alt"></i>';
                                        break;
                                    case 'document_added':
                                    case 'document_updated':
                                        echo '<i class="fas fa-file-upload"></i>';
                                        break;
                                    case 'comment_added':
                                        echo '<i class="fas fa-comment"></i>';
                                        break;
                                    case 'assigned':
                                        echo '<i class="fas fa-user-check"></i>';
                                        break;
                                    case 'completed':
                                        echo '<i class="fas fa-check-circle"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-info-circle"></i>';
                                }
                                ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title"><?php echo htmlspecialchars($log['description']); ?></span>
                                    <span class="timeline-date">
                                        <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="timeline-user">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['user_name']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Status History Section -->
    <div class="section">
        <div class="section-header">
            <h2>Status History</h2>
        </div>
        <div class="section-body">
            <?php if (empty($status_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No status changes have been recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="status-history-list">
                    <?php foreach ($status_history as $status): ?>
                        <div class="status-history-item">
                            <div class="status-history-badge" style="background-color: <?php echo $status['status_color']; ?>10; color: <?php echo $status['status_color']; ?>;">
                                <?php echo ucfirst($status['status_name']); ?>
                            </div>
                            <div class="status-history-details">
                                <div class="status-history-date">
                                    <?php echo date('M d, Y h:i A', strtotime($status['created_at'])); ?>
                                </div>
                                <div class="status-history-user">
                                    Changed by: <?php echo htmlspecialchars($status['changed_by_name']); ?>
                                </div>
                                <?php if (!empty($status['notes'])): ?>
                                    <div class="status-history-notes">
                                        <?php echo nl2br(htmlspecialchars($status['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
}

.status-badge i {
    font-size: 8px;
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

/* Section Styles */
.section {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}

.section-body {
    padding: 20px;
}

/* Overview Grid */
.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.overview-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.item-label {
    font-size: 12px;
    color: var(--secondary-color);
    font-weight: 500;
}

.priority-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
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

.doc-stats {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.doc-stat {
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 10px;
}

.doc-stat.approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.doc-stat.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.doc-stat.rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.application-notes {
    margin-top: 20px;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 5px;
}

.application-notes h3 {
    margin: 0 0 10px;
    font-size: 16px;
    color: var(--dark-color);
}

.notes-content {
    color: var(--dark-color);
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    border: none;
    gap: 8px;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover {
    background-color: #031c56;
    color: white;
}

.secondary-btn {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.secondary-btn:hover {
    background-color: #e9ecef;
    color: var(--dark-color);
}

/* Documents Section */
.documents-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.document-category {
    border: 1px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
}

.category-header {
    background-color: var(--light-color);
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.category-header h3 {
    margin: 0;
    color: var(--dark-color);
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.document-list {
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.document-item {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    border-radius: 5px;
    background-color: var(--light-color);
    border-left: 3px solid transparent;
}

.document-item.missing {
    border-left-color: var(--secondary-color);
}

.document-item.has-document.status-pending,
.document-item.has-document.status-submitted {
    border-left-color: var(--warning-color);
}

.document-item.has-document.status-approved {
    border-left-color: var(--success-color);
}

.document-item.has-document.status-rejected {
    border-left-color: var(--danger-color);
}

.document-info {
    flex: 1;
}

.document-name {
    font-weight: 500;
    color: var(--dark-color);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mandatory-badge {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}

.optional-badge {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}

.document-description {
    color: var(--secondary-color);
    font-size: 13px;
    margin-bottom: 10px;
}

.document-upload-info {
    margin-top: 8px;
    font-size: 13px;
}

.upload-detail {
    display: flex;
    gap: 5px;
    margin-bottom: 3px;
}

.upload-detail .label {
    color: var(--secondary-color);
    min-width: 80px;
}

.status-text {
    font-weight: 500;
}

.status-pending {
    color: var(--secondary-color);
}

.status-submitted {
    color: #4e73df;
}

.status-approved {
    color: var(--success-color);
}

.status-rejected {
    color: var(--danger-color);
}

.rejection-reason {
    margin-top: 5px;
    padding: 8px;
    background-color: rgba(231, 74, 59, 0.05);
    border-radius: 4px;
}

.document-missing {
    color: var(--secondary-color);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
}

.document-actions {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
}

.btn-icon:hover {
    background-color: #031c56;
    color: white;
}

.btn-upload {
    background-color: var(--warning-color);
}

.btn-upload:hover {
    background-color: #e5a922;
}

/* Empty States */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 40px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
}

/* Comment Section */
.add-comment-form {
    margin-bottom: 20px;
}

.add-comment-form textarea {
    width: 100%;
    height: 100px;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    resize: vertical;
    margin-bottom: 10px;
}

.comment-actions {
    display: flex;
    justify-content: flex-end;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.comment-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 5px;
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 500;
}

.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.comment-content {
    flex: 1;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.author-name {
    font-weight: 500;
    color: var(--dark-color);
}

.author-role {
    color: var(--secondary-color);
    font-size: 12px;
    background-color: rgba(133, 135, 150, 0.1);
    padding: 2px 8px;
    border-radius: 10px;
}

.comment-date {
    color: var(--secondary-color);
    font-size: 12px;
}

.comment-text {
    color: var(--dark-color);
    line-height: 1.5;
}

/* Timeline Section */
.timeline {
    position: relative;
    margin-left: 20px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 15px;
    width: 2px;
    background-color: var(--border-color);
    transform: translateX(-50%);
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.timeline-content {
    background-color: var(--light-color);
    padding: 12px 15px;
    border-radius: 5px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.timeline-title {
    font-weight: 500;
    color: var(--dark-color);
}

.timeline-date {
    color: var(--secondary-color);
    font-size: 12px;
}

.timeline-user {
    color: var(--secondary-color);
    font-size: 13px;
}

/* Status History Section */
.status-history-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.status-history-item {
    display: flex;
    gap: 15px;
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 5px;
}

.status-history-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    height: fit-content;
}

.status-history-details {
    flex: 1;
}

.status-history-date {
    color: var(--dark-color);
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 5px;
}

.status-history-user {
    color: var(--secondary-color);
    font-size: 13px;
    margin-bottom: 5px;
}

.status-history-notes {
    background-color: white;
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 8px;
    color: var(--dark-color);
    font-size: 13px;
    border: 1px solid var(--border-color);
}

/* Responsive Styles */
@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .document-item {
        flex-direction: column;
        gap: 15px;
    }
    
    .document-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .status-history-item {
        flex-direction: column;
        gap: 10px;
    }
    
    .status-history-badge {
        align-self: flex-start;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
