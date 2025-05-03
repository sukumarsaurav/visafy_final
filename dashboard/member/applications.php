<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Applications";
$page_specific_css = "../assets/css/applications.css";
require_once 'includes/header.php';

// Get the team member's ID
$team_member_id = $user_id;

// Default filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query to get applications assigned to the team member
$query = "SELECT a.id, a.reference_number, a.submitted_at, a.expected_completion_date, a.priority,
          a.notes, a.created_at, a.updated_at, 
          ast.id AS status_id, ast.name AS status_name, ast.color AS status_color, 
          u.id AS applicant_id, CONCAT(u.first_name, ' ', u.last_name) AS applicant_name, 
          u.email AS applicant_email, u.status AS applicant_status,
          v.visa_type, c.country_name, 
          COUNT(DISTINCT ad.id) AS total_documents,
          SUM(CASE WHEN ad.status = 'approved' THEN 1 ELSE 0 END) AS approved_documents,
          SUM(CASE WHEN ad.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_documents,
          SUM(CASE WHEN ad.status = 'pending' THEN 1 ELSE 0 END) AS pending_documents,
          SUM(CASE WHEN ad.status = 'submitted' THEN 1 ELSE 0 END) AS submitted_documents
          FROM applications a
          JOIN application_assignments aa ON a.id = aa.application_id
          JOIN application_statuses ast ON a.status_id = ast.id
          JOIN users u ON a.user_id = u.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          LEFT JOIN application_documents ad ON a.id = ad.application_id
          WHERE aa.team_member_id = ? 
          AND aa.status = 'active'
          AND a.deleted_at IS NULL";

// Add status filter
if ($status_filter != 'all') {
    $query .= " AND ast.name = ?";
}

// Add priority filter
if ($priority_filter != 'all') {
    $query .= " AND a.priority = ?";
}

// Add search filter
if (!empty($search_term)) {
    $query .= " AND (a.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
}

// Group by application
$query .= " GROUP BY a.id, a.reference_number, a.submitted_at, a.expected_completion_date, a.priority,
           a.notes, a.created_at, a.updated_at, ast.id, ast.name, ast.color,
           u.id, u.first_name, u.last_name, u.email, u.status, v.visa_type, c.country_name";

// Order by priority and status
$query .= " ORDER BY 
           CASE a.priority 
               WHEN 'urgent' THEN 1 
               WHEN 'high' THEN 2 
               WHEN 'normal' THEN 3 
               WHEN 'low' THEN 4 
           END,
           CASE ast.name
               WHEN 'additional_documents_requested' THEN 1
               WHEN 'under_review' THEN 2
               WHEN 'submitted' THEN 3
               WHEN 'processing' THEN 4
               WHEN 'on_hold' THEN 5
               WHEN 'approved' THEN 6
               WHEN 'completed' THEN 7
               WHEN 'rejected' THEN 8
               WHEN 'cancelled' THEN 9
               ELSE 10
           END,
           a.updated_at DESC";

// Prepare and execute the query with appropriate bindings
$stmt = $conn->prepare($query);

// Bind parameters based on filters applied
if ($status_filter != 'all' && $priority_filter != 'all' && !empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt->bind_param("isssss", $team_member_id, $status_filter, $priority_filter, $search_param, $search_param, $search_param);
} elseif ($status_filter != 'all' && $priority_filter != 'all') {
    $stmt->bind_param("iss", $team_member_id, $status_filter, $priority_filter);
} elseif ($status_filter != 'all' && !empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt->bind_param("issss", $team_member_id, $status_filter, $search_param, $search_param, $search_param);
} elseif ($priority_filter != 'all' && !empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt->bind_param("issss", $team_member_id, $priority_filter, $search_param, $search_param, $search_param);
} elseif ($status_filter != 'all') {
    $stmt->bind_param("is", $team_member_id, $status_filter);
} elseif ($priority_filter != 'all') {
    $stmt->bind_param("is", $team_member_id, $priority_filter);
} elseif (!empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt->bind_param("isss", $team_member_id, $search_param, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $team_member_id);
}

$stmt->execute();
$result = $stmt->get_result();
$applications = [];

while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

// Get application status counts for filters
$status_counts = [];
$status_query = "SELECT ast.name, COUNT(a.id) as count
                FROM applications a
                JOIN application_assignments aa ON a.id = aa.application_id
                JOIN application_statuses ast ON a.status_id = ast.id
                WHERE aa.team_member_id = ?
                AND aa.status = 'active'
                AND a.deleted_at IS NULL
                GROUP BY ast.name
                ORDER BY count DESC";

$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$status_result = $stmt->get_result();

while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['name']] = $row['count'];
}
$stmt->close();

// Calculate total applications
$total_applications = array_sum($status_counts);

// Get application priority counts for filters
$priority_counts = [];
$priority_query = "SELECT a.priority, COUNT(a.id) as count
                  FROM applications a
                  JOIN application_assignments aa ON a.id = aa.application_id
                  WHERE aa.team_member_id = ?
                  AND aa.status = 'active'
                  AND a.deleted_at IS NULL
                  GROUP BY a.priority
                  ORDER BY 
                  CASE a.priority 
                      WHEN 'urgent' THEN 1 
                      WHEN 'high' THEN 2 
                      WHEN 'normal' THEN 3 
                      WHEN 'low' THEN 4 
                  END";

$stmt = $conn->prepare($priority_query);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$priority_result = $stmt->get_result();

while ($row = $priority_result->fetch_assoc()) {
    $priority_counts[$row['priority']] = $row['count'];
}
$stmt->close();

// Get application details if an application is selected
$selected_application = null;
$application_documents = [];
$application_comments = [];
$application_history = [];

if (isset($_GET['application_id']) && is_numeric($_GET['application_id'])) {
    $application_id = $_GET['application_id'];
    
    // Get application details
    $app_query = "SELECT a.*, ast.name AS status_name, ast.color AS status_color,
                 CONCAT(u.first_name, ' ', u.last_name) AS applicant_name,
                 u.email AS applicant_email, u.status AS applicant_status, u.profile_picture,
                 v.visa_type, c.country_name,
                 CONCAT(adm.first_name, ' ', adm.last_name) AS created_by_name
                 FROM applications a
                 JOIN application_assignments aa ON a.id = aa.application_id
                 JOIN application_statuses ast ON a.status_id = ast.id
                 JOIN users u ON a.user_id = u.id
                 JOIN visas v ON a.visa_id = v.visa_id
                 JOIN countries c ON v.country_id = c.country_id
                 JOIN users adm ON a.created_by = adm.id
                 WHERE a.id = ? AND aa.team_member_id = ? AND aa.status = 'active'";
    
    $app_stmt = $conn->prepare($app_query);
    $app_stmt->bind_param("ii", $application_id, $team_member_id);
    $app_stmt->execute();
    $app_result = $app_stmt->get_result();
    
    if ($app_result->num_rows > 0) {
        $selected_application = $app_result->fetch_assoc();
        
        // Get application documents
        $doc_query = "SELECT ad.*, dt.name AS document_type_name, dt.description AS document_type_description,
                     CONCAT(s.first_name, ' ', s.last_name) AS submitted_by_name,
                     CONCAT(r.first_name, ' ', r.last_name) AS reviewed_by_name
                     FROM application_documents ad
                     JOIN document_types dt ON ad.document_type_id = dt.id
                     LEFT JOIN users s ON ad.submitted_by = s.id
                     LEFT JOIN users r ON ad.reviewed_by = r.id
                     WHERE ad.application_id = ?
                     ORDER BY dt.name";
        
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param("i", $application_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        
        while ($row = $doc_result->fetch_assoc()) {
            $application_documents[] = $row;
        }
        $doc_stmt->close();
        
        // Get application comments
        $comment_query = "SELECT ac.*, CONCAT(u.first_name, ' ', u.last_name) AS commented_by_name,
                         u.profile_picture, u.user_type
                         FROM application_comments ac
                         JOIN users u ON ac.user_id = u.id
                         WHERE ac.application_id = ? AND ac.deleted_at IS NULL
                         ORDER BY ac.created_at DESC";
        
        $comment_stmt = $conn->prepare($comment_query);
        $comment_stmt->bind_param("i", $application_id);
        $comment_stmt->execute();
        $comment_result = $comment_stmt->get_result();
        
        while ($row = $comment_result->fetch_assoc()) {
            $application_comments[] = $row;
        }
        $comment_stmt->close();
        
        // Get application status history
        $history_query = "SELECT ash.*, ast.name AS status_name, ast.color AS status_color,
                         CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
                         FROM application_status_history ash
                         JOIN application_statuses ast ON ash.status_id = ast.id
                         JOIN users u ON ash.changed_by = u.id
                         WHERE ash.application_id = ?
                         ORDER BY ash.created_at DESC";
        
        $history_stmt = $conn->prepare($history_query);
        $history_stmt->bind_param("i", $application_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        
        while ($row = $history_result->fetch_assoc()) {
            $application_history[] = $row;
        }
        $history_stmt->close();
    }
    $app_stmt->close();
}

// Handle adding a comment to an application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $application_id = $_POST['application_id'];
    $comment = trim($_POST['comment']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (!empty($comment)) {
        try {
            $conn->begin_transaction();
            
            // Insert the comment
            $stmt = $conn->prepare("INSERT INTO application_comments (application_id, user_id, comment, is_internal, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $application_id, $user_id, $comment, $is_internal);
            $stmt->execute();
            $stmt->close();
            
            // Add activity log
            $activity_description = "Comment added" . ($is_internal ? " (internal)" : "");
            $stmt = $conn->prepare("INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address, created_at) 
                                   VALUES (?, ?, 'comment_added', ?, ?, NOW())");
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iiss", $application_id, $user_id, $activity_description, $ip_address);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            // Redirect to prevent form resubmission
            header("Location: applications.php?application_id=" . $application_id . "&comment_added=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error adding comment: " . $e->getMessage();
        }
    }
}

// Handle document status update (for team members who can review documents)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document') {
    $document_id = $_POST['document_id'];
    $application_id = $_POST['application_id'];
    $status = $_POST['document_status'];
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;
    
    try {
        $conn->begin_transaction();
        
        // Update document status
        $stmt = $conn->prepare("UPDATE application_documents 
                               SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() 
                               WHERE id = ?");
        $stmt->bind_param("ssii", $status, $rejection_reason, $user_id, $document_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $activity_description = "Document status updated to " . ucfirst($status);
        $stmt = $conn->prepare("INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address, created_at) 
                               VALUES (?, ?, 'document_updated', ?, ?, NOW())");
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("iiss", $application_id, $user_id, $activity_description, $ip_address);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Redirect to prevent form resubmission
        header("Location: applications.php?application_id=" . $application_id . "&document_updated=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating document: " . $e->getMessage();
    }
}

// Flash messages for actions
$flash_message = null;
if (isset($_GET['comment_added']) && $_GET['comment_added'] == 1) {
    $flash_message = ['type' => 'success', 'message' => 'Comment added successfully.'];
} elseif (isset($_GET['document_updated']) && $_GET['document_updated'] == 1) {
    $flash_message = ['type' => 'success', 'message' => 'Document status updated successfully.'];
}
?>

<div class="content">
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_message['type']; ?>">
            <?php echo $flash_message['message']; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($selected_application): ?>
        <!-- Application Detail View -->
        <div class="back-link">
            <a href="applications.php<?php echo !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'application_id') !== false ? substr($_SERVER['QUERY_STRING'], strpos($_SERVER['QUERY_STRING'], 'application_id') + 15) : ''; ?>">
                <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
        </div>

        <div class="application-detail-container">
            <div class="application-header">
                <div class="application-title">
                    <h1>
                        <span class="reference-number"><?php echo htmlspecialchars($selected_application['reference_number']); ?></span>
                        <?php echo htmlspecialchars($selected_application['visa_type'] . ' - ' . $selected_application['country_name']); ?>
                    </h1>
                    <div class="status-badges">
                        <span class="status-badge" style="background-color: <?php echo $selected_application['status_color']; ?>20; color: <?php echo $selected_application['status_color']; ?>;">
                            <?php echo ucfirst(str_replace('_', ' ', $selected_application['status_name'])); ?>
                        </span>
                        <span class="priority-badge priority-<?php echo strtolower($selected_application['priority']); ?>">
                            <?php echo ucfirst($selected_application['priority']); ?> Priority
                        </span>
                    </div>
                </div>
                <!-- Application actions can be added here if needed -->
            </div>

            <div class="application-meta">
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-user"></i> Applicant:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($selected_application['applicant_name']); ?> (<?php echo htmlspecialchars($selected_application['applicant_email']); ?>)</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-calendar-alt"></i> Created:</span>
                    <span class="meta-value"><?php echo date('F j, Y', strtotime($selected_application['created_at'])); ?> by <?php echo htmlspecialchars($selected_application['created_by_name']); ?></span>
                </div>
                <?php if ($selected_application['submitted_at']): ?>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-paper-plane"></i> Submitted:</span>
                    <span class="meta-value"><?php echo date('F j, Y', strtotime($selected_application['submitted_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($selected_application['expected_completion_date']): ?>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-calendar-check"></i> Expected Completion:</span>
                    <span class="meta-value"><?php echo date('F j, Y', strtotime($selected_application['expected_completion_date'])); ?></span>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-clock"></i> Last Updated:</span>
                    <span class="meta-value"><?php echo date('F j, Y g:i A', strtotime($selected_application['updated_at'])); ?></span>
                </div>
            </div>

            <div class="application-tabs">
                <ul class="nav-tabs">
                    <li class="tab-item active" data-tab="documents">
                        <i class="fas fa-file-alt"></i> Documents 
                        <span class="tab-badge"><?php echo count($application_documents); ?></span>
                    </li>
                    <li class="tab-item" data-tab="comments">
                        <i class="fas fa-comments"></i> Comments 
                        <span class="tab-badge"><?php echo count($application_comments); ?></span>
                    </li>
                    <li class="tab-item" data-tab="history">
                        <i class="fas fa-history"></i> Status History 
                        <span class="tab-badge"><?php echo count($application_history); ?></span>
                    </li>
                    <?php if (!empty($selected_application['notes'])): ?>
                    <li class="tab-item" data-tab="notes">
                        <i class="fas fa-sticky-note"></i> Notes
                    </li>
                    <?php endif; ?>
                </ul>

                <div id="documents" class="tab-content active">
                    <h3>Documents</h3>
                    <?php if (empty($application_documents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No documents have been added to this application.</p>
                        </div>
                    <?php else: ?>
                        <div class="documents-list">
                            <?php foreach ($application_documents as $document): ?>
                                <div class="document-card status-<?php echo $document['status']; ?>">
                                    <div class="document-header">
                                        <h4><?php echo htmlspecialchars($document['document_type_name']); ?></h4>
                                        <span class="document-status status-<?php echo $document['status']; ?>">
                                            <?php echo ucfirst($document['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($document['document_type_description'])): ?>
                                        <div class="document-description">
                                            <?php echo htmlspecialchars($document['document_type_description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($document['file_path'])): ?>
                                        <div class="document-file">
                                            <a href="../../<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-view-document">
                                                <i class="fas fa-eye"></i> View Document
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($document['status'] === 'rejected' && !empty($document['rejection_reason'])): ?>
                                        <div class="rejection-reason">
                                            <strong>Reason for rejection:</strong>
                                            <p><?php echo htmlspecialchars($document['rejection_reason']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="document-meta">
                                        <?php if (!empty($document['submitted_by'])): ?>
                                            <div class="meta-item">
                                                <span class="meta-label">Submitted by:</span>
                                                <span class="meta-value"><?php echo htmlspecialchars($document['submitted_by_name']); ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <span class="meta-label">Submitted on:</span>
                                                <span class="meta-value"><?php echo date('M j, Y', strtotime($document['submitted_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($document['reviewed_by'])): ?>
                                            <div class="meta-item">
                                                <span class="meta-label">Reviewed by:</span>
                                                <span class="meta-value"><?php echo htmlspecialchars($document['reviewed_by_name']); ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <span class="meta-label">Reviewed on:</span>
                                                <span class="meta-value"><?php echo date('M j, Y', strtotime($document['reviewed_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($document['status'] === 'submitted'): ?>
                                        <div class="document-actions">
                                            <button class="btn btn-approve" data-toggle="modal" data-target="#documentModal" data-document-id="<?php echo $document['id']; ?>" data-action="approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-reject" data-toggle="modal" data-target="#documentModal" data-document-id="<?php echo $document['id']; ?>" data-action="reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="comments" class="tab-content">
                    <h3>Comments</h3>
                    <div class="add-comment-form">
                        <form method="post" action="applications.php">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="application_id" value="<?php echo $selected_application['id']; ?>">
                            <div class="form-group">
                                <textarea name="comment" placeholder="Add a comment..." required></textarea>
                            </div>
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" name="is_internal" value="1" checked>
                                    <span>Internal comment (only visible to team members)</span>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Comment</button>
                        </form>
                    </div>
                    
                    <?php if (empty($application_comments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>No comments have been added to this application.</p>
                        </div>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($application_comments as $comment): ?>
                                <div class="comment-card <?php echo $comment['is_internal'] ? 'internal-comment' : ''; ?>">
                                    <div class="comment-header">
                                        <div class="comment-author">
                                            <div class="comment-avatar">
                                                <?php if (!empty($comment['profile_picture']) && file_exists('../../uploads/profiles/' . $comment['profile_picture'])): ?>
                                                    <img src="../../uploads/profiles/<?php echo $comment['profile_picture']; ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php 
                                                        $name_parts = explode(' ', $comment['commented_by_name']);
                                                        echo substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="author-info">
                                                <span class="author-name"><?php echo htmlspecialchars($comment['commented_by_name']); ?></span>
                                                <span class="author-role">
                                                    <?php 
                                                    if ($comment['user_type'] === 'admin') {
                                                        echo 'Administrator';
                                                    } elseif ($comment['user_type'] === 'member') {
                                                        echo 'Team Member';
                                                    } else {
                                                        echo 'Applicant';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="comment-meta">
                                            <?php if ($comment['is_internal']): ?>
                                                <span class="internal-badge"><i class="fas fa-eye-slash"></i> Internal</span>
                                            <?php endif; ?>
                                            <span class="comment-date"><?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="history" class="tab-content">
                    <h3>Status History</h3>
                    <?php if (empty($application_history)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No status history is available for this application.</p>
                        </div>
                    <?php else: ?>
                        <div class="status-timeline">
                            <?php foreach ($application_history as $key => $status): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot" style="background-color: <?php echo $status['status_color']; ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-date">
                                            <?php echo date('F j, Y g:i A', strtotime($status['created_at'])); ?>
                                        </div>
                                        <div class="timeline-title">
                                            Status changed to <span class="status-badge" style="background-color: <?php echo $status['status_color']; ?>20; color: <?php echo $status['status_color']; ?>;">
                                                <?php echo ucfirst(str_replace('_', ' ', $status['status_name'])); ?>
                                            </span>
                                            by <?php echo htmlspecialchars($status['changed_by_name']); ?>
                                        </div>
                                        <?php if (!empty($status['notes'])): ?>
                                            <div class="timeline-notes">
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

        <!-- Document Status Update Modal -->
        <div id="documentModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="modal-title">Update Document Status</h2>
                <form id="documentForm" method="post" action="applications.php">
                    <input type="hidden" name="action" value="update_document">
                    <input type="hidden" name="document_id" id="document_id" value="">
                    <input type="hidden" name="application_id" value="<?php echo $selected_application['id']; ?>">
                    <input type="hidden" name="document_status" id="document_status" value="">
                    
                    <div id="rejection-reason-container" style="display: none;">
                        <div class="form-group">
                            <label for="rejection_reason">Reason for rejection:</label>
                            <textarea name="rejection_reason" id="rejection_reason" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
                        <button type="submit" class="btn btn-confirm" id="confirmBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List View -->
        <div class="applications-header">
            <div class="page-title">
                <h1>My Applications</h1>
                <p>Manage applications assigned to you</p>
            </div>
            
            <div class="search-filter-container">
                <form method="get" action="applications.php" class="search-filter-form">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses (<?php echo $total_applications; ?>)</option>
                            <?php foreach ($status_counts as $status => $count): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?> (<?php echo $count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="priority">Priority:</label>
                        <select name="priority" id="priority" onchange="this.form.submit()">
                            <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                            <?php 
                            $priorities = ['urgent', 'high', 'normal', 'low'];
                            foreach ($priorities as $priority): 
                                $count = isset($priority_counts[$priority]) ? $priority_counts[$priority] : 0;
                            ?>
                                <option value="<?php echo $priority; ?>" <?php echo $priority_filter === $priority ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($priority); ?> (<?php echo $count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Search applications..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="btn btn-search">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search_term)): ?>
                            <a href="applications.php" class="btn btn-clear">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No applications found</h3>
                <p>
                    <?php 
                    if (!empty($search_term)) {
                        echo "No applications match your search criteria.";
                    } elseif ($status_filter !== 'all') {
                        echo "No applications with status: " . ucfirst(str_replace('_', ' ', $status_filter));
                    } elseif ($priority_filter !== 'all') {
                        echo "No applications with priority: " . ucfirst($priority_filter);
                    } else {
                        echo "You don't have any applications assigned to you yet.";
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="applications-grid">
                <?php foreach ($applications as $application): ?>
                    <div class="application-card priority-<?php echo strtolower($application['priority']); ?>">
                        <div class="card-priority-indicator"></div>
                        <div class="card-content">
                            <div class="card-header">
                                <div class="app-ref-number">
                                    <i class="fas fa-hashtag"></i>
                                    <?php echo htmlspecialchars($application['reference_number']); ?>
                                </div>
                                <div class="status-badge" style="background-color: <?php echo $application['status_color']; ?>20; color: <?php echo $application['status_color']; ?>;">
                                    <?php echo ucfirst(str_replace('_', ' ', $application['status_name'])); ?>
                                </div>
                            </div>
                            
                            <h3 class="app-title">
                                <?php echo htmlspecialchars($application['visa_type']); ?> - <?php echo htmlspecialchars($application['country_name']); ?>
                            </h3>
                            
                            <div class="applicant-info">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($application['applicant_name']); ?>
                                <?php if ($application['applicant_status'] !== 'active'): ?>
                                    <span class="applicant-inactive"><i class="fas fa-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="document-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $application['approved_documents']; ?>/<?php echo $application['total_documents']; ?></span>
                                    <span class="stat-label">Documents Approved</span>
                                </div>
                                <?php if ($application['submitted_documents'] > 0): ?>
                                    <div class="stat-item stat-pending">
                                        <span class="stat-value"><?php echo $application['submitted_documents']; ?></span>
                                        <span class="stat-label">Pending Review</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-dates">
                                <?php if (!empty($application['expected_completion_date'])): ?>
                                    <div class="date-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Expected Completion: <?php echo date('M j, Y', strtotime($application['expected_completion_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="date-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Last Updated: <?php echo date('M j, Y', strtotime($application['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="priority-badge priority-<?php echo strtolower($application['priority']); ?>">
                                    <?php echo ucfirst($application['priority']); ?> Priority
                                </div>
                                <a href="applications.php?application_id=<?php echo $application['id']; ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
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
    
    --urgent-color: #e74a3b;
    --high-color: #f6c23e;
    --normal-color: #4e73df;
    --low-color: #36b9cc;
    
    --pending-color: #858796;
    --submitted-color: #f6c23e;
    --approved-color: #1cc88a;
    --rejected-color: #e74a3b;
}

.content {
    padding: 20px;
    font-family: 'Nunito', sans-serif;
}

/* Alert Styling */
.alert {
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    position: relative;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.12);
    border-left: 4px solid var(--success-color);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.12);
    border-left: 4px solid var(--danger-color);
    color: var(--danger-color);
}

/* Applications Header */
.applications-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 20px;
}

.page-title h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.9rem;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.page-title p {
    margin: 6px 0 0;
    color: var(--secondary-color);
    font-size: 0.95rem;
}

.search-filter-container {
    width: 100%;
    max-width: 650px;
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-group, .search-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--secondary-color);
}

.filter-group select, .search-group input {
    padding: 9px 14px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.filter-group select:focus, .search-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(4, 33, 103, 0.1);
}

.search-group {
    flex: 1;
    position: relative;
    min-width: 200px;
}

.search-group input {
    width: 100%;
    padding-right: 40px;
}

.btn-search, .btn-clear {
    background: none;
    border: none;
    color: var(--secondary-color);
    cursor: pointer;
    position: absolute;
    transition: color 0.2s ease;
}

.btn-search {
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
}

.btn-clear {
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
}

.btn-search:hover, .btn-clear:hover {
    color: var(--primary-color);
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 30px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.empty-state i {
    font-size: 3.5rem;
    color: var(--secondary-color);
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 0 0 12px;
    color: var(--dark-color);
    font-size: 1.3rem;
    font-weight: 600;
}

.empty-state p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 1rem;
    max-width: 400px;
}

/* Applications Grid */
.applications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.application-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.07);
    overflow: hidden;
    display: flex;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    z-index: 1;
}

.application-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.card-priority-indicator {
    width: 6px;
    background-color: var(--normal-color);
    transition: width 0.2s ease;
}

.application-card:hover .card-priority-indicator {
    width: 10px;
}

.application-card.priority-urgent .card-priority-indicator {
    background-color: var(--urgent-color);
}

.application-card.priority-high .card-priority-indicator {
    background-color: var(--high-color);
}

.application-card.priority-normal .card-priority-indicator {
    background-color: var(--normal-color);
}

.application-card.priority-low .card-priority-indicator {
    background-color: var(--low-color);
}

.card-content {
    flex: 1;
    padding: 18px;
    display: flex;
    flex-direction: column;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.app-ref-number {
    font-size: 0.8rem;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.app-title {
    margin: 0 0 12px;
    font-size: 1.2rem;
    color: var(--primary-color);
    font-weight: 700;
    line-height: 1.4;
}

.applicant-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    color: var(--dark-color);
    margin-bottom: 16px;
    font-weight: 500;
}

.applicant-inactive {
    color: var(--danger-color);
    margin-left: 5px;
}

.document-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 18px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background-color: rgba(78, 115, 223, 0.08);
    padding: 8px 14px;
    border-radius: 6px;
    flex: 1;
    transition: transform 0.15s ease;
}

.stat-item:hover {
    transform: scale(1.05);
}

.stat-pending {
    background-color: rgba(246, 194, 62, 0.08);
    color: var(--warning-color);
}

.stat-value {
    font-weight: 700;
    font-size: 1.1rem;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--secondary-color);
    margin-top: 2px;
}

.card-dates {
    margin-bottom: 18px;
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.date-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.date-item i {
    color: var(--primary-color);
    opacity: 0.8;
}

.card-footer {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid var(--light-color);
}

.priority-badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.priority-badge.priority-urgent {
    background-color: rgba(231, 74, 59, 0.08);
    color: var(--urgent-color);
}

.priority-badge.priority-high {
    background-color: rgba(246, 194, 62, 0.08);
    color: var(--high-color);
}

.priority-badge.priority-normal {
    background-color: rgba(78, 115, 223, 0.08);
    color: var(--normal-color);
}

.priority-badge.priority-low {
    background-color: rgba(54, 185, 204, 0.08);
    color: var(--low-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 9px 16px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}

.btn-view {
    background-color: var(--primary-color);
    color: white;
}

.btn-view:hover {
    background-color: #021a50;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Application Detail View */
.back-link {
    margin-bottom: 24px;
}

.back-link a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: color 0.2s ease;
}

.back-link a:hover {
    color: #021a50;
}

.application-detail-container {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.application-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background-color: rgba(4, 33, 103, 0.02);
}

.application-title h1 {
    margin: 0 0 12px;
    font-size: 1.6rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    font-weight: 700;
    line-height: 1.4;
}

.reference-number {
    font-size: 0.9rem;
    background-color: var(--light-color);
    padding: 4px 10px;
    border-radius: 5px;
    color: var(--secondary-color);
    font-weight: 600;
}

.status-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.application-meta {
    padding: 20px 24px;
    background-color: var(--light-color);
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.meta-label {
    font-size: 0.85rem;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: 600;
}

.meta-value {
    font-size: 0.98rem;
    color: var(--dark-color);
}

/* Tabs */
.application-tabs {
    padding: 0 24px 24px;
}

.nav-tabs {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    border-bottom: 1px solid var(--border-color);
    overflow-x: auto;
    scrollbar-width: thin;
}

.nav-tabs::-webkit-scrollbar {
    height: 4px;
}

.nav-tabs::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 4px;
}

.tab-item {
    padding: 16px 20px;
    cursor: pointer;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    color: var(--secondary-color);
    font-weight: 600;
    position: relative;
}

.tab-item:hover {
    color: var(--primary-color);
}

.tab-item.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    background-color: var(--light-color);
    color: var(--secondary-color);
    font-size: 0.7rem;
    margin-left: 6px;
    font-weight: 700;
}

.tab-item.active .tab-badge {
    background-color: var(--primary-color);
    color: white;
}

.tab-content {
    display: none;
    padding: 24px 0;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

.tab-content h3 {
    margin: 0 0 20px;
    font-size: 1.2rem;
    color: var(--primary-color);
    font-weight: 700;
}

/* Documents Tab */
.documents-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.document-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s;
    background-color: white;
    position: relative;
    overflow: hidden;
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
}

.document-card.status-approved {
    border-left: 5px solid var(--success-color);
}

.document-card.status-rejected {
    border-left: 5px solid var(--danger-color);
}

.document-card.status-pending {
    border-left: 5px solid var(--secondary-color);
}

.document-card.status-submitted {
    border-left: 5px solid var(--warning-color);
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.document-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-color);
    font-weight: 700;
}

.document-status {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

.document-status.status-approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.document-status.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.document-status.status-pending {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.document-status.status-submitted {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.document-description {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-bottom: 15px;
    line-height: 1.5;
}

.document-file {
    margin-bottom: 15px;
}

.btn-view-document {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    font-size: 0.85rem;
    padding: 7px 12px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-view-document:hover {
    background-color: rgba(4, 33, 103, 0.08);
    transform: translateY(-2px);
}

.rejection-reason {
    background-color: rgba(231, 74, 59, 0.08);
    padding: 12px 15px;
    border-radius: 6px;
    font-size: 0.9rem;
    margin-bottom: 15px;
    border-left: 3px solid var(--danger-color);
}

.rejection-reason strong {
    color: var(--danger-color);
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.rejection-reason p {
    margin: 5px 0 0;
    color: var(--dark-color);
    line-height: 1.5;
}

.document-meta {
    font-size: 0.85rem;
    color: var(--secondary-color);
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
    margin-bottom: 15px;
}

.document-meta .meta-label {
    font-size: 0.75rem;
    font-weight: 600;
}

.document-actions {
    display: flex;
    gap: 10px;
    margin-top: 5px;
}

.btn-approve {
    background-color: var(--success-color);
    color: white;
}

.btn-approve:hover {
    background-color: #19b37b;
}

.btn-reject {
    background-color: var(--danger-color);
    color: white;
}

.btn-reject:hover {
    background-color: #d44235;
}

/* Comments Tab */
.add-comment-form {
    margin-bottom: 24px;
    background-color: var(--light-color);
    padding: 20px;
    border-radius: 8px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    resize: vertical;
    font-family: inherit;
    min-height: 100px;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-size: 0.95rem;
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(4, 33, 103, 0.1);
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary-color);
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    font-weight: 600;
    padding: 10px 18px;
}

.btn-primary:hover {
    background-color: #021a50;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.comment-card {
    background-color: var(--light-color);
    padding: 18px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.comment-card.internal-comment {
    background-color: rgba(78, 115, 223, 0.06);
    border-left: 4px solid var(--primary-color);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 12px;
}

.comment-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
}

.author-info {
    display: flex;
    flex-direction: column;
}

.author-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1rem;
}

.author-role {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.comment-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.internal-badge {
    font-size: 0.75rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 4px;
    background-color: rgba(4, 33, 103, 0.08);
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.comment-date {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.comment-content {
    color: var(--dark-color);
    line-height: 1.6;
    font-size: 0.95rem;
    padding: 0 0 0 56px;
}

/* History Tab */
.status-timeline {
    position: relative;
    margin-left: 20px;
    padding-left: 20px;
}

.status-timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 10px;
    width: 2px;
    background-color: var(--border-color);
    transform: translateX(-50%);
}

.timeline-item {
    position: relative;
    padding-bottom: 24px;
    padding-left: 30px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item:hover .timeline-dot {
    transform: translateX(-50%) scale(1.1);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.8), 0 0 0 5px var(--border-color);
}

.timeline-dot {
    position: absolute;
    left: 0;
    top: 8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    transform: translateX(-50%);
    border: 3px solid white;
    box-shadow: 0 0 0 1px var(--border-color);
    z-index: 1;
    transition: all 0.2s ease;
}

.timeline-content {
    background-color: var(--light-color);
    padding: 18px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s;
}

.timeline-content:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.timeline-date {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-bottom: 8px;
    font-weight: 500;
}

.timeline-title {
    font-size: 0.98rem;
    color: var(--dark-color);
    margin-bottom: 10px;
    font-weight: 500;
    line-height: 1.5;
}

.timeline-notes {
    font-size: 0.9rem;
    color: var(--secondary-color);
    padding: 12px;
    background-color: rgba(255, 255, 255, 0.6);
    border-radius: 6px;
    border-left: 3px solid var(--border-color);
    line-height: 1.5;
}

/* Notes Tab */
.notes-content {
    background-color: var(--light-color);
    padding: 20px;
    border-radius: 8px;
    line-height: 1.7;
    color: var(--dark-color);
    font-size: 0.95rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal.show {
    opacity: 1;
}

.modal-content {
    background-color: white;
    padding: 25px;
    border-radius: 10px;
    max-width: 550px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.95);
    transition: transform 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal.show .modal-content {
    transform: scale(1);
}

.close {
    float: right;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    color: var(--secondary-color);
    transition: color 0.2s ease;
}

.modal h2 {
    margin: 0 0 20px;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn-cancel {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-confirm {
    background-color: var(--primary-color);
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .applications-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-filter-form {
        flex-direction: column;
    }
    
    .filter-group, .search-group {
        width: 100%;
    }
    
    .applications-grid {
        grid-template-columns: 1fr;
    }
    
    .documents-list {
        grid-template-columns: 1fr;
    }
    
    .application-meta {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 90%;
    }
}
</style>

<script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabItems = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabItems.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs
            tabItems.forEach(item => item.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current tab
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Document modal functionality
    const modal = document.getElementById('documentModal');
    const documentButtons = document.querySelectorAll('.btn-approve, .btn-reject');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.getElementById('cancelBtn');
    const rejectionContainer = document.getElementById('rejection-reason-container');
    
    documentButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const documentId = this.getAttribute('data-document-id');
            const action = this.getAttribute('data-action');
            
            document.getElementById('document_id').value = documentId;
            document.getElementById('document_status').value = action === 'approve' ? 'approved' : 'rejected';
            
            if (action === 'approve') {
                document.getElementById('modal-title').textContent = 'Approve Document';
                rejectionContainer.style.display = 'none';
            } else {
                document.getElementById('modal-title').textContent = 'Reject Document';
                rejectionContainer.style.display = 'block';
            }
            
            modal.style.display = 'flex';
        });
    });
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();

require_once 'includes/footer.php';
?>
