<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Application Details";
$page_specific_css = "assets/css/view_application.css";
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
          CONCAT(cr.first_name, ' ', cr.last_name) as created_by_name,
          tm.id as team_member_id, tm.role as team_member_role,
          CONCAT(team_u.first_name, ' ', team_u.last_name) as team_member_name,
          team_u.id as team_user_id
          FROM applications a
          JOIN application_statuses ast ON a.status_id = ast.id
          JOIN users u ON a.user_id = u.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN users cr ON a.created_by = cr.id
          LEFT JOIN application_assignments aa ON a.id = aa.application_id AND aa.status = 'active'
          LEFT JOIN team_members tm ON aa.team_member_id = tm.id
          LEFT JOIN users team_u ON tm.user_id = team_u.id
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

// Get all application statuses
$query = "SELECT * FROM application_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$application_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $application_statuses[$row['id']] = $row;
    }
}
$stmt->close();

// Get all team members
$query = "SELECT tm.id, tm.role, tm.custom_role_name, 
          u.id as user_id, u.first_name, u.last_name, u.email 
          FROM team_members tm 
          JOIN users u ON tm.user_id = u.id 
          WHERE tm.deleted_at IS NULL AND u.status = 'active'
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[$row['id']] = $row;
    }
}
$stmt->close();

// Get application documents
$query = "SELECT ad.*, dt.name as document_type_name, dt.description as document_type_description,
          CONCAT(s.first_name, ' ', s.last_name) as submitted_by_name,
          CONCAT(r.first_name, ' ', r.last_name) as reviewed_by_name
          FROM application_documents ad
          JOIN document_types dt ON ad.document_type_id = dt.id
          LEFT JOIN users s ON ad.submitted_by = s.id
          LEFT JOIN users r ON ad.reviewed_by = r.id
          WHERE ad.application_id = ?
          ORDER BY ad.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Get application comments
$query = "SELECT ac.*, CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.profile_picture, u.user_type
          FROM application_comments ac
          JOIN users u ON ac.user_id = u.id
          WHERE ac.application_id = ? AND ac.deleted_at IS NULL
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

// Get required documents for this visa type
$query = "SELECT vrd.*, dt.name as document_name, dt.description as document_description
          FROM visa_required_documents vrd
          JOIN document_types dt ON vrd.document_type_id = dt.id
          WHERE vrd.visa_id = ?
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application['visa_id']);
$stmt->execute();
$required_docs_result = $stmt->get_result();
$required_documents = [];

if ($required_docs_result && $required_docs_result->num_rows > 0) {
    while ($row = $required_docs_result->fetch_assoc()) {
        $required_documents[$row['document_type_id']] = $row;
    }
}
$stmt->close();

// Calculate document completion percentage
$total_required = 0;
$total_approved = 0;

foreach ($required_documents as $doc) {
    if ($doc['is_mandatory']) {
        $total_required++;
        
        // Check if document is approved
        foreach ($documents as $uploaded_doc) {
            if ($uploaded_doc['document_type_id'] == $doc['document_type_id'] && $uploaded_doc['status'] == 'approved') {
                $total_approved++;
                break;
            }
        }
    }
}

$document_completion = $total_required > 0 ? round(($total_approved / $total_required) * 100) : 0;

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status_id = $_POST['new_status_id'];
    $notes = isset($_POST['status_notes']) ? trim($_POST['status_notes']) : '';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update application status
        $update_query = "UPDATE applications SET status_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $new_status_id, $application_id);
        $stmt->execute();
        $stmt->close();
        
        // Add to status history
        $history_query = "INSERT INTO application_status_history (application_id, status_id, changed_by, notes) 
                         VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param('iiis', $application_id, $new_status_id, $user_id, $notes);
        $stmt->execute();
        $stmt->close();
        
        // Log the activity
        $status_name = $application_statuses[$new_status_id]['name'];
        $description = "Application status updated to " . ucwords(str_replace('_', ' ', $status_name));
        $activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                          VALUES (?, ?, 'status_changed', ?, ?)";
        $stmt = $conn->prepare($activity_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Application status updated successfully";
        
        // Refresh the page to show updated information
        header("Location: view_application.php?id=" . $application_id . "&status_updated=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating application status: " . $e->getMessage();
    }
}

// Handle application assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_application'])) {
    $team_member_id = $_POST['team_member_id'];
    $notes = isset($_POST['assignment_notes']) ? trim($_POST['assignment_notes']) : '';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, mark any existing assignments as reassigned
        $update_existing = "UPDATE application_assignments 
                           SET status = 'reassigned' 
                           WHERE application_id = ? AND status = 'active'";
        $stmt = $conn->prepare($update_existing);
        $stmt->bind_param('i', $application_id);
        $stmt->execute();
        $stmt->close();
        
        // Now create the new assignment
        $assign_query = "INSERT INTO application_assignments (application_id, team_member_id, assigned_by, notes) 
                        VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($assign_query);
        $stmt->bind_param('iiis', $application_id, $team_member_id, $user_id, $notes);
        $stmt->execute();
        $stmt->close();
        
        // Log the activity
        $team_name = $team_members[$team_member_id]['first_name'] . ' ' . $team_members[$team_member_id]['last_name'];
        $description = "Application assigned to " . $team_name;
        $activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                          VALUES (?, ?, 'assigned', ?, ?)";
        $stmt = $conn->prepare($activity_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Application assigned successfully";
        
        // Refresh the page to show updated information
        header("Location: view_application.php?id=" . $application_id . "&assigned=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error assigning application: " . $e->getMessage();
    }
}

// Handle adding comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment_text']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (!empty($comment)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert comment
            $comment_query = "INSERT INTO application_comments (application_id, user_id, comment, is_internal) 
                            VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($comment_query);
            $stmt->bind_param('iisi', $application_id, $user_id, $comment, $is_internal);
            $stmt->execute();
            $stmt->close();
            
            // Log the activity
            $description = "Comment added" . ($is_internal ? " (internal)" : "");
            $activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                              VALUES (?, ?, 'comment_added', ?, ?)";
            $stmt = $conn->prepare($activity_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
            $stmt->execute();
            $stmt->close();
            
            // Commit the transaction
            $conn->commit();
            
            $success_message = "Comment added successfully";
            
            // Refresh the page to show updated information
            header("Location: view_application.php?id=" . $application_id . "&comment_added=1");
            exit;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error adding comment: " . $e->getMessage();
        }
    } else {
        $error_message = "Comment cannot be empty";
    }
}

// Handle update document status
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
        
        // Log the activity
        $description = "Document status updated to " . ucfirst($new_status);
        $activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                          VALUES (?, ?, 'document_updated', ?, ?)";
        $stmt = $conn->prepare($activity_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Document status updated successfully";
        
        // Refresh the page to show updated information
        header("Location: view_application.php?id=" . $application_id . "&document_updated=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating document status: " . $e->getMessage();
    }
}

// Check for success messages
if (isset($_GET['status_updated']) && $_GET['status_updated'] == '1') {
    $success_message = "Application status updated successfully";
} elseif (isset($_GET['assigned']) && $_GET['assigned'] == '1') {
    $success_message = "Application assigned successfully";
} elseif (isset($_GET['comment_added']) && $_GET['comment_added'] == '1') {
    $success_message = "Comment added successfully";
} elseif (isset($_GET['document_updated']) && $_GET['document_updated'] == '1') {
    $success_message = "Document status updated successfully";
}
?>

<div class="content">
    <!-- Header with back button and application title -->
    <div class="header-container">
        <div class="left-section">
            <a href="applications.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
        </div>
        <div class="center-section">
            <h1>Application #<?php echo htmlspecialchars($application['reference_number']); ?></h1>
            <div class="application-subtitle">
                <span class="application-type"><?php echo htmlspecialchars($application['visa_type'] . ' - ' . $application['country_name']); ?></span>
                <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                    <i class="fas fa-circle"></i> <?php echo ucwords(str_replace('_', ' ', $application['status_name'])); ?>
                </span>
                <span class="priority-badge priority-<?php echo $application['priority']; ?>">
                    <?php echo ucfirst($application['priority']); ?> Priority
                </span>
            </div>
        </div>
        <div class="right-section">
            <button type="button" class="btn-action btn-update-status" onclick="openStatusModal()">
                Update Status
            </button>
            <?php if (empty($application['team_member_id'])): ?>
                <button type="button" class="btn-action btn-assign" onclick="openAssignModal()">
                    Assign Case Manager
                </button>
            <?php else: ?>
                <button type="button" class="btn-action btn-reassign" onclick="openAssignModal()">
                    Reassign
                </button>
            <?php endif; ?>
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
    
    <!-- Main content grid -->
    <div class="grid-container">
        <!-- Left column: Application details and client info -->
        <div class="left-column">
            <!-- Application Details Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-folder-open"></i> Application Details</h3>
                </div>
                <div class="card-body">
                    <div class="detail-group">
                        <div class="detail-row">
                            <div class="detail-label">Reference Number:</div>
                            <div class="detail-value highlight"><?php echo htmlspecialchars($application['reference_number']); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Visa Type:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['visa_type']); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Country:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['country_name']); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Current Status:</div>
                            <div class="detail-value">
                                <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>;">
                                    <?php echo ucwords(str_replace('_', ' ', $application['status_name'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Priority:</div>
                            <div class="detail-value">
                                <span class="priority-badge priority-<?php echo $application['priority']; ?>">
                                    <?php echo ucfirst($application['priority']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-divider"></div>
                    
                    <div class="detail-group">
                        <div class="detail-row">
                            <div class="detail-label">Created By:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['created_by_name']); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Created Date:</div>
                            <div class="detail-value"><?php echo date('M d, Y h:i A', strtotime($application['created_at'])); ?></div>
                        </div>
                        
                        <?php if (!empty($application['submitted_at'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Submitted Date:</div>
                                <div class="detail-value"><?php echo date('M d, Y h:i A', strtotime($application['submitted_at'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['expected_completion_date'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Expected Completion:</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($application['expected_completion_date'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <div class="detail-label">Last Updated:</div>
                            <div class="detail-value"><?php echo date('M d, Y h:i A', strtotime($application['updated_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($application['notes'])): ?>
                        <div class="detail-divider"></div>
                        
                        <div class="detail-group">
                            <div class="detail-row">
                                <div class="detail-label">Notes:</div>
                                <div class="detail-value notes"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Applicant Information Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user"></i> Applicant Information</h3>
                    <a href="view_client.php?id=<?php echo $application['applicant_id']; ?>" class="btn-view-profile">
                        <i class="fas fa-external-link-alt"></i> View Profile
                    </a>
                </div>
                <div class="card-body">
                    <div class="applicant-info">
                        <div class="applicant-avatar">
                            <?php if (!empty($application['profile_picture'])): ?>
                                <img src="../../<?php echo htmlspecialchars($application['profile_picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="default-avatar">
                                    <?php echo strtoupper(substr($application['applicant_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="applicant-details">
                            <h4><?php echo htmlspecialchars($application['applicant_name']); ?></h4>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($application['applicant_email']); ?></p>
                            <p>
                                <i class="fas fa-circle status-indicator status-<?php echo $application['applicant_status']; ?>"></i>
                                <?php echo ucfirst($application['applicant_status']); ?>
                            </p>
                            
                            <div class="applicant-actions">
                                <a href="client_bookings.php?client_id=<?php echo $application['applicant_id']; ?>" class="btn-view-bookings">
                                    <i class="fas fa-calendar-alt"></i> View Bookings
                                </a>
                                <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>" class="btn-email">
                                    <i class="fas fa-envelope"></i> Email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Case Manager Card (if assigned) -->
            <?php if (!empty($application['team_member_id'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-tie"></i> Case Manager</h3>
                    </div>
                    <div class="card-body">
                        <div class="case-manager-info">
                            <div class="team-member-avatar">
                                <div class="default-avatar">
                                    <?php echo strtoupper(substr($application['team_member_name'], 0, 1)); ?>
                                </div>
                            </div>
                            
                            <div class="team-member-details">
                                <h4><?php echo htmlspecialchars($application['team_member_name']); ?></h4>
                                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($application['team_member_role']); ?></p>
                                
                                <div class="team-member-actions">
                                    <button type="button" class="btn-action btn-reassign" onclick="openAssignModal()">
                                        <i class="fas fa-exchange-alt"></i> Reassign
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right column: Documents, Comments, Activity -->
        <div class="right-column">
            <!-- Document Progress Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tasks"></i> Document Progress</h3>
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
                    </div>
                </div>
            </div>
            
            <!-- Documents Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Documents</h3>
                    <a href="application_documents.php?id=<?php echo $application_id; ?>" class="btn-secondary">
                        <i class="fas fa-cog"></i> Manage Documents
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No documents have been uploaded yet</p>
                        </div>
                    <?php else: ?>
                        <div class="documents-list">
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-item">
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
                                        <div class="document-meta">
                                            <span>Uploaded by <?php echo htmlspecialchars($doc['submitted_by_name']); ?></span>
                                            <span><?php echo date('M d, Y', strtotime($doc['submitted_at'])); ?></span>
                                        </div>
                                        
                                        <?php if ($doc['status'] == 'rejected' && !empty($doc['rejection_reason'])): ?>
                                            <div class="rejection-reason">
                                                <strong>Rejection reason:</strong> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-actions">
                                        <a href="../../<?php echo $doc['file_path']; ?>" target="_blank" class="btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../<?php echo $doc['file_path']; ?>" download class="btn-download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn-review" onclick="openDocumentReviewModal(<?php echo $doc['id']; ?>, '<?php echo $doc['document_type_name']; ?>', '<?php echo $doc['status']; ?>')">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Comment Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-comment-alt"></i> Add Comment</h3>
                </div>
                <div class="card-body">
                    <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                        <div class="form-group">
                            <textarea name="comment_text" placeholder="Write your comment here..." class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_internal" name="is_internal" value="1">
                            <label class="form-check-label" for="is_internal">Internal comment (only visible to team members)</label>
                        </div>
                        <div class="form-buttons">
                            <button type="submit" name="add_comment" class="btn submit-btn">Add Comment</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Comments Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-comments"></i> Comments</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($comments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>No comments yet</p>
                        </div>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item <?php echo $comment['is_internal'] ? 'internal-comment' : ''; ?>">
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
                                                <?php if ($comment['is_internal']): ?>
                                                    <span class="internal-badge">Internal</span>
                                                <?php endif; ?>
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
            
            <!-- Activity Log Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Activity Log</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($activity_logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No activity recorded yet</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activity_logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker 
                                        <?php
                                        switch ($log['activity_type']) {
                                            case 'created': echo 'created'; break;
                                            case 'status_changed': echo 'status-changed'; break;
                                            case 'assigned': echo 'assigned'; break;
                                            case 'document_added': case 'document_updated': echo 'document'; break;
                                            case 'comment_added': echo 'comment'; break;
                                            default: echo ''; break;
                                        }
                                        ?>">
                                        <i class="fas 
                                        <?php
                                        switch ($log['activity_type']) {
                                            case 'created': echo 'fa-plus'; break;
                                            case 'status_changed': echo 'fa-exchange-alt'; break;
                                            case 'assigned': echo 'fa-user-check'; break;
                                            case 'document_added': echo 'fa-file-upload'; break;
                                            case 'document_updated': echo 'fa-file-alt'; break;
                                            case 'comment_added': echo 'fa-comment-dots'; break;
                                            default: echo 'fa-circle'; break;
                                        }
                                        ?>"></i>
                                    </div>
                                    
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <div class="timeline-user">
                                                <?php echo htmlspecialchars($log['user_name']); ?>
                                            </div>
                                            <div class="timeline-date">
                                                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="timeline-body">
                                            <?php echo htmlspecialchars($log['description']); ?>
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
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> Update Application Status</h3>
            <span class="close" onclick="closeStatusModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                <div class="form-group">
                    <label for="new_status_id">Select New Status:</label>
                    <select name="new_status_id" id="new_status_id" class="form-control" required>
                        <?php foreach ($application_statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>" <?php echo ($status['id'] == $application['status_id']) ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $status['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status_notes">Notes (Optional):</label>
                    <textarea name="status_notes" id="status_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Case Manager Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> <?php echo empty($application['team_member_id']) ? 'Assign Case Manager' : 'Reassign Case Manager'; ?></h3>
            <span class="close" onclick="closeAssignModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                <div class="form-group">
                    <label for="team_member_id">Select Case Manager:</label>
                    <select name="team_member_id" id="team_member_id" class="form-control" required>
                        <?php foreach ($team_members as $tm): ?>
                            <option value="<?php echo $tm['id']; ?>" <?php echo ($tm['id'] == $application['team_member_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tm['first_name'] . ' ' . $tm['last_name'] . ' (' . $tm['role'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="assignment_notes">Notes (Optional):</label>
                    <textarea name="assignment_notes" id="assignment_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" name="assign_application" class="btn btn-primary">
                        <?php echo empty($application['team_member_id']) ? 'Assign Case Manager' : 'Reassign Case Manager'; ?>
                    </button>
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
            <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                <input type="hidden" name="document_id" id="document_id">
                
                <div class="form-group">
                    <label for="document_status">Update Status:</label>
                    <select name="document_status" id="document_status" class="form-control" required onchange="toggleRejectionReason()">
                        <option value="pending">Pending</option>
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
    width: 120px;
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

.priority-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

.priority-urgent {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.priority-high {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.priority-normal {
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
}

.priority-low {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
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

.btn-update-status {
    background-color: var(--warning-color);
    color: white;
}

.btn-update-status:hover {
    background-color: #e0b137;
    color: white;
    text-decoration: none;
}

.btn-assign {
    background-color: var(--primary-color);
    color: white;
}

.btn-assign:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.btn-reassign {
    background-color: var(--info-color);
    color: white;
}

.btn-reassign:hover {
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

/* Grid Layout */
.grid-container {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 20px;
}

.left-column, .right-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Card Styles */
.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
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
}

.detail-group {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
}

.detail-label {
    width: 35%;
    font-weight: 600;
    color: var(--dark-color);
}

.detail-value {
    width: 65%;
    color: var(--secondary-color);
}

.detail-value.highlight {
    font-weight: 600;
    color: var(--primary-color);
}

.detail-value.notes {
    white-space: pre-line;
}

.detail-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 15px 0;
}

/* Applicant Info */
.applicant-info {
    display: flex;
    gap: 15px;
}

.applicant-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.applicant-avatar img {
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
    background-color: var(--primary-color);
    color: white;
    font-weight: 600;
    font-size: 2rem;
}

.applicant-details {
    flex: 1;
}

.applicant-details h4 {
    margin: 0 0 5px;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.applicant-details p {
    margin: 0 0 10px;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    font-size: 8px;
}

.status-active {
    color: var(--success-color);
}

.status-suspended {
    color: var(--danger-color);
}

.applicant-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn-view-profile {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
    text-decoration: none;
}

.btn-view-profile:hover {
    background-color: #e9ecef;
    text-decoration: none;
    color: var(--primary-color);
}

.btn-view-bookings, .btn-email {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    font-size: 0.85rem;
    border-radius: 3px;
    text-decoration: none;
}

.btn-view-bookings {
    background-color: var(--primary-color);
    color: white;
}

.btn-view-bookings:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.btn-email {
    background-color: var(--info-color);
    color: white;
}

.btn-email:hover {
    background-color: #2c9faf;
    color: white;
    text-decoration: none;
}

/* Case Manager Info */
.case-manager-info {
    display: flex;
    gap: 15px;
}

.team-member-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
}

.team-member-details {
    flex: 1;
}

.team-member-details h4 {
    margin: 0 0 5px;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.team-member-details p {
    margin: 0 0 10px;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.team-member-actions {
    margin-top: 10px;
}

/* Document Progress */
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

/* Documents List */
.documents-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    gap: 15px;
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
}

.document-status {
    font-size: 0.8rem;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 10px;
}

.status-pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}
.status-submitted {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.document-meta {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.rejection-reason {
    font-size: 0.85rem;
    color: var(--danger-color);
    background-color: rgba(231, 74, 59, 0.05);
    padding: 5px 10px;
    border-radius: 4px;
    margin-top: 5px;
}

.document-actions {
    display: flex;
    gap: 5px;
}

.btn-view, .btn-download, .btn-review {
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

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
    text-decoration: none;
}

.btn-secondary:hover {
    background-color: #e9ecef;
    text-decoration: none;
    color: var(--primary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.4;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
}

/* Comments Styling */
.form-group {
    margin-bottom: 15px;
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

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.form-check-input {
    margin-right: 8px;
}

.form-check-label {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
}

.submit-btn {
    padding: 8px 16px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
}

.submit-btn:hover {
    background-color: #031c56;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.comment-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.comment-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.comment-item.internal-comment {
    background-color: rgba(246, 194, 62, 0.05);
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid var(--warning-color);
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.comment-avatar .default-avatar {
    font-size: 1.2rem;
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
    gap: 10px;
}

.author-name {
    font-weight: 600;
    color: var(--dark-color);
}

.author-role {
    font-size: 0.8rem;
    color: var(--secondary-color);
    padding: 2px 8px;
    background-color: var(--light-color);
    border-radius: 12px;
}

.internal-badge {
    font-size: 0.8rem;
    color: var(--warning-color);
    background-color: rgba(246, 194, 62, 0.1);
    padding: 2px 8px;
    border-radius: 12px;
}

.comment-date {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.comment-text {
    color: var(--dark-color);
    line-height: 1.5;
}

/* Timeline Styling */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 15px;
    width: 2px;
    background-color: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--light-color);
    border: 2px solid var(--secondary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--secondary-color);
    z-index: 1;
}

.timeline-marker.created {
    border-color: var(--success-color);
    color: var(--success-color);
}

.timeline-marker.status-changed {
    border-color: var(--warning-color);
    color: var(--warning-color);
}

.timeline-marker.assigned {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.timeline-marker.document {
    border-color: var(--info-color);
    color: var(--info-color);
}

.timeline-marker.comment {
    border-color: var(--dark-color);
    color: var(--dark-color);
}

.timeline-content {
    background-color: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 12px 15px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.timeline-user {
    font-weight: 600;
    color: var(--dark-color);
}

.timeline-date {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.timeline-body {
    color: var(--dark-color);
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
    border-radius: 5px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    animation: modalFadeIn 0.3s;
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

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    font-size: 0.9rem;
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

/* Responsive Styles */
@media (max-width: 992px) {
    .grid-container {
        grid-template-columns: 1fr;
    }
    
    .right-section {
        flex-wrap: wrap;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
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
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label, .detail-value {
        width: 100%;
    }
    
    .detail-label {
        margin-bottom: 5px;
    }
    
    .document-status-summary {
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .status-item {
        width: 100px;
    }
}
</style>

<script>
// Modal Functions
function openStatusModal() {
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function openAssignModal() {
    document.getElementById('assignModal').style.display = 'block';
}

function closeAssignModal() {
    document.getElementById('assignModal').style.display = 'none';
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
    var statusModal = document.getElementById('statusModal');
    var assignModal = document.getElementById('assignModal');
    var documentModal = document.getElementById('documentReviewModal');
    
    if (event.target === statusModal) {
        statusModal.style.display = 'none';
    } else if (event.target === assignModal) {
        assignModal.style.display = 'none';
    } else if (event.target === documentModal) {
        documentModal.style.display = 'none';
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
