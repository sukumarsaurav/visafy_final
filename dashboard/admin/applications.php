<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Set execution time to avoid PHP timing out
ini_set('max_execution_time', 300); // 5 minutes

// Increase MySQL timeout settings
if (isset($conn)) {
    $conn->query("SET SESSION wait_timeout=600");
    $conn->query("SET SESSION interactive_timeout=600");
}

$page_title = "Visa Applications";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Get all applications with related information
$query = "SELECT av.*, 
         (SELECT COUNT(DISTINCT ad.id) FROM application_documents ad WHERE ad.application_id = av.id) as document_count,
         (SELECT COUNT(DISTINCT ac.id) FROM application_comments ac WHERE ac.application_id = av.id) as comment_count
         FROM applications_view av 
         ORDER BY 
         CASE 
             WHEN av.priority = 'urgent' THEN 1
             WHEN av.priority = 'high' THEN 2
             WHEN av.priority = 'normal' THEN 3
             WHEN av.priority = 'low' THEN 4
             ELSE 5
         END, 
         av.created_at DESC";

$applications = [];
try {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparing query");
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query");
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Error getting result");
    }
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching applications: " . $e->getMessage();
}

// Get all visa types for filter
$visa_query = "SELECT visa_id, visa_type, country_name FROM visas v JOIN countries c ON v.country_id = c.country_id WHERE v.is_active = 1";
$visas = [];

try {
    $visa_stmt = $conn->prepare($visa_query);
    if ($visa_stmt) {
        $visa_stmt->execute();
        $visa_result = $visa_stmt->get_result();
        
        if ($visa_result && $visa_result->num_rows > 0) {
            while ($row = $visa_result->fetch_assoc()) {
                $visas[] = $row;
            }
        }
        $visa_stmt->close();
    }
} catch (Exception $e) {
    // Silently handle error
}

// Get all application statuses for filter
$status_query = "SELECT id, name, color FROM application_statuses";
$statuses = [];

try {
    $status_stmt = $conn->prepare($status_query);
    if ($status_stmt) {
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        
        if ($status_result && $status_result->num_rows > 0) {
            while ($row = $status_result->fetch_assoc()) {
                $statuses[] = $row;
            }
        }
        $status_stmt->close();
    }
} catch (Exception $e) {
    // Silently handle error
}

// Get all team members for assignment
$team_query = "SELECT tm.id, tm.role, u.first_name, u.last_name 
               FROM team_members tm 
               JOIN users u ON tm.user_id = u.id 
               WHERE u.status = 'active' AND tm.deleted_at IS NULL";
$team_members = [];

try {
    $team_stmt = $conn->prepare($team_query);
    if ($team_stmt) {
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        
        if ($team_result && $team_result->num_rows > 0) {
            while ($row = $team_result->fetch_assoc()) {
                $team_members[] = $row;
            }
        }
        $team_stmt->close();
    }
} catch (Exception $e) {
    // Silently handle error
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
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
        $status_name = "";
        foreach ($statuses as $status) {
            if ($status['id'] == $new_status_id) {
                $status_name = $status['name'];
                break;
            }
        }
        
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
        header("Location: applications.php?success=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating application status: " . $e->getMessage();
    }
}

// Handle application assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_application'])) {
    $application_id = $_POST['application_id'];
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
        $team_name = "";
        foreach ($team_members as $member) {
            if ($member['id'] == $team_member_id) {
                $team_name = $member['first_name'] . ' ' . $member['last_name'];
                break;
            }
        }
        
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
        header("Location: applications.php?success=2");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error assigning application: " . $e->getMessage();
    }
}

// Handle application creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_application'])) {
    $user_id = $_POST['user_id'];
    $visa_id = $_POST['visa_id'];
    $priority = $_POST['priority'];
    $expected_completion_date = !empty($_POST['expected_completion_date']) ? $_POST['expected_completion_date'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $team_member_id = isset($_POST['team_member_id']) && !empty($_POST['team_member_id']) ? $_POST['team_member_id'] : null;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Create new application
        $initial_status_query = "SELECT id FROM application_statuses WHERE name = 'draft' LIMIT 1";
        $status_stmt = $conn->prepare($initial_status_query);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        $status_id = $status_result->fetch_assoc()['id'];
        $status_stmt->close();
        
        $insert_query = "INSERT INTO applications (user_id, visa_id, status_id, expected_completion_date, notes, priority, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('iiisssi', $user_id, $visa_id, $status_id, $expected_completion_date, $notes, $priority, $user_id);
        $stmt->execute();
        
        $application_id = $conn->insert_id;
        $stmt->close();
        
        // Add status to history
        $history_query = "INSERT INTO application_status_history (application_id, status_id, changed_by, notes) 
                         VALUES (?, ?, ?, 'Application created')";
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param('iii', $application_id, $status_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Assign to team member if selected
        if ($team_member_id) {
            $assign_query = "INSERT INTO application_assignments (application_id, team_member_id, assigned_by, notes) 
                            VALUES (?, ?, ?, 'Assigned during application creation')";
            $stmt = $conn->prepare($assign_query);
            $stmt->bind_param('iii', $application_id, $team_member_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Get team member name for activity log
            $team_name = "Team member";
            foreach ($team_members as $member) {
                if ($member['id'] == $team_member_id) {
                    $team_name = $member['first_name'] . ' ' . $member['last_name'];
                    break;
                }
            }
            
            // Log the assignment activity
            $assign_description = "Application assigned to " . $team_name;
            $assign_activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                                     VALUES (?, ?, 'assigned', ?, ?)";
            $stmt = $conn->prepare($assign_activity_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param('iiss', $application_id, $user_id, $assign_description, $ip);
            $stmt->execute();
            $stmt->close();
        }
        
        // Log the application creation activity
        $description = "Application created";
        $activity_query = "INSERT INTO application_activity_logs (application_id, user_id, activity_type, description, ip_address) 
                         VALUES (?, ?, 'created', ?, ?)";
        $stmt = $conn->prepare($activity_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $application_id, $user_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        $success_message = "Application created successfully";
        header("Location: applications.php?success=3");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error creating application: " . $e->getMessage();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Application status updated successfully";
            break;
        case 2:
            $success_message = "Application assigned successfully";
            break;
        case 3:
            $success_message = "Application created successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Visa Applications</h1>
            <p>View and manage all visa applications</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="createApplicationBtn">
                <i class="fas fa-plus"></i> New Application
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filter Controls -->
    <div class="filter-container">
        <div class="filter-section">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-select">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                <option value="<?php echo $status['id']; ?>"><?php echo ucwords(str_replace('_', ' ', $status['name'])); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-section">
            <label for="visa-filter">Visa Type:</label>
            <select id="visa-filter" class="filter-select">
                <option value="">All Visa Types</option>
                <?php foreach ($visas as $visa): ?>
                <option value="<?php echo $visa['visa_id']; ?>"><?php echo $visa['visa_type'] . ' (' . $visa['country_name'] . ')'; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-section">
            <label for="priority-filter">Priority:</label>
            <select id="priority-filter" class="filter-select">
                <option value="">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
            </select>
        </div>
        
        <div class="filter-section">
            <label for="search-input">Search:</label>
            <input type="text" id="search-input" placeholder="Reference, Name, Email..." class="search-input">
        </div>
    </div>
    
    <!-- Applications Table -->
    <div class="applications-table-container">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No applications found. Create a new application to get started.</p>
            </div>
        <?php else: ?>
            <table class="applications-table" id="applications-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Visa Type</th>
                        <th>Status</th>
                        <th>Case Manager</th>
                        <th>Documents</th>
                        <th>Priority</th>
                        <th>Created</th>
                        <th class="actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr data-status="<?php echo isset($app['status_id']) ? $app['status_id'] : '0'; ?>" data-visa="<?php echo isset($app['visa_id']) ? $app['visa_id'] : ''; ?>" data-priority="<?php echo isset($app['priority']) ? $app['priority'] : 'normal'; ?>">
                            <td>
                                <a href="view_application.php?id=<?php echo $app['id']; ?>" class="reference-link">
                                    <?php echo isset($app['reference_number']) ? htmlspecialchars($app['reference_number']) : 'No Reference'; ?>
                                </a>
                            </td>
                            <td>
                                <div class="applicant-info">
                                    <span class="applicant-name"><?php echo isset($app['applicant_name']) ? htmlspecialchars($app['applicant_name']) : 'Unknown'; ?></span>
                                    <span class="applicant-email"><?php echo isset($app['applicant_email']) ? htmlspecialchars($app['applicant_email']) : ''; ?></span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $visa_info = '';
                                if (isset($app['visa_type'])) {
                                    $visa_info = htmlspecialchars($app['visa_type']);
                                    if (isset($app['country_name'])) {
                                        $visa_info .= ' (' . htmlspecialchars($app['country_name']) . ')';
                                    }
                                } elseif (isset($app['visa_id'])) {
                                    // Fetch visa info if needed
                                    $visa_info = 'Visa ID: ' . $app['visa_id'];
                                } else {
                                    $visa_info = 'Unknown visa type';
                                }
                                echo $visa_info;
                                ?>
                            </td>
                            <td>
                                <span class="status-badge" style="background-color: <?php echo isset($app['status_color']) ? $app['status_color'] : '#808080'; ?>10; color: <?php echo isset($app['status_color']) ? $app['status_color'] : '#808080'; ?>;">
                                    <i class="fas fa-circle"></i> <?php echo ucwords(str_replace('_', ' ', isset($app['status_name']) ? $app['status_name'] : 'Unknown')); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($app['case_manager_name']) && !empty($app['case_manager_name'])): ?>
                                    <div class="case-manager">
                                        <span class="manager-name"><?php echo htmlspecialchars($app['case_manager_name']); ?></span>
                                        <span class="manager-role"><?php echo isset($app['case_manager_role']) ? htmlspecialchars($app['case_manager_role']) : ''; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="not-assigned">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="documents-info">
                                    <div class="document-progress">
                                        <div class="progress-bar">
                                            <?php 
                                            $total_docs = isset($app['total_documents']) ? $app['total_documents'] : 0;
                                            $approved_docs = isset($app['approved_documents']) ? $app['approved_documents'] : 0;
                                            $progress = $total_docs > 0 ? ($approved_docs / $total_docs) * 100 : 0;
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="document-count"><?php echo $approved_docs; ?>/<?php echo $total_docs; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="priority-badge priority-<?php echo isset($app['priority']) ? $app['priority'] : 'normal'; ?>">
                                    <?php echo ucfirst(isset($app['priority']) ? $app['priority'] : 'normal'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="date"><?php echo isset($app['created_at']) ? date('M d, Y', strtotime($app['created_at'])) : 'Unknown'; ?></span>
                            </td>
                            <td class="actions-cell">
                                <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <button type="button" class="btn-action btn-status" 
                                        title="Update Status" onclick="openStatusModal(<?php echo $app['id']; ?>, <?php echo isset($app['status_id']) ? $app['status_id'] : '0'; ?>)">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                
                                <button type="button" class="btn-action btn-assign" 
                                        title="Assign Case Manager" onclick="openAssignModal(<?php echo $app['id']; ?>, '<?php echo isset($app['team_member_id']) ? $app['team_member_id'] : ''; ?>')">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                
                                <a href="application_documents.php?id=<?php echo $app['id']; ?>" class="btn-action btn-document" title="Manage Documents">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                
                                <a href="application_comments.php?id=<?php echo $app['id']; ?>" class="btn-action btn-comment" title="Comments (<?php echo isset($app['comment_count']) ? $app['comment_count'] : '0'; ?>)">
                                    <i class="fas fa-comments"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="applications.php" method="POST" id="updateStatusForm">
                    <input type="hidden" name="application_id" id="status_application_id">
                    
                    <div class="form-group">
                        <label for="new_status_id">New Status*</label>
                        <select name="new_status_id" id="new_status_id" class="form-control" required>
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>"><?php echo ucwords(str_replace('_', ' ', $status['name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_notes">Notes</label>
                        <textarea name="status_notes" id="status_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assign Case Manager Modal -->
<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Case Manager</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="applications.php" method="POST" id="assignForm">
                    <input type="hidden" name="application_id" id="assign_application_id">
                    
                    <div class="form-group">
                        <label for="team_member_id">Team Member*</label>
                        <select name="team_member_id" id="team_member_id" class="form-control" required>
                            <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_notes">Notes</label>
                        <textarea name="assignment_notes" id="assignment_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_application" class="btn submit-btn">Assign Case Manager</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Create Application Modal -->
<div class="modal" id="createApplicationModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Application</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="applications.php" method="POST" id="createApplicationForm">
                    <!-- Step Progress Bar -->
                    <div class="steps-progress">
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Applicant</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Visa Selection</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Documents</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-label">Assignment</div>
                        </div>
                        <div class="step" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>
                    
                    <!-- Step 1: Applicant Selection -->
                    <div class="step-content active" id="step-1">
                        <h4 class="step-title">Select Applicant</h4>
                        <div class="form-group">
                            <label for="user_id">Choose Applicant*</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Select an applicant</option>
                                <?php
                                // Get all clients - Fixed the user_type to 'applicant' instead of 'client'
                                $clients_query = "SELECT id, first_name, last_name, email FROM users WHERE user_type = 'applicant' AND status = 'active' AND deleted_at IS NULL";
                                $clients_stmt = $conn->prepare($clients_query);
                                $clients_stmt->execute();
                                $clients_result = $clients_stmt->get_result();
                                
                                if ($clients_result && $clients_result->num_rows > 0) {
                                    while ($client = $clients_result->fetch_assoc()) {
                                        echo '<option value="' . $client['id'] . '">' . 
                                            htmlspecialchars($client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['email'] . ')') . 
                                            '</option>';
                                    }
                                }
                                $clients_stmt->close();
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority*</label>
                            <select name="priority" id="priority" class="form-control" required>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Step 2: Country and Visa Selection -->
                    <div class="step-content" id="step-2">
                        <h4 class="step-title">Select Visa Type</h4>
                        <div class="form-group">
                            <label for="country_id">Country*</label>
                            <select id="country_id" class="form-control" required>
                                <option value="">Select a country</option>
                                <?php
                                // Get all countries
                                $countries_query = "SELECT DISTINCT c.country_id, c.country_name 
                                                  FROM countries c 
                                                  JOIN visas v ON c.country_id = v.country_id 
                                                  WHERE v.is_active = 1 
                                                  ORDER BY c.country_name";
                                $countries_stmt = $conn->prepare($countries_query);
                                $countries_stmt->execute();
                                $countries_result = $countries_stmt->get_result();
                                
                                if ($countries_result && $countries_result->num_rows > 0) {
                                    while ($country = $countries_result->fetch_assoc()) {
                                        echo '<option value="' . $country['country_id'] . '">' . 
                                            htmlspecialchars($country['country_name']) . 
                                            '</option>';
                                    }
                                }
                                $countries_stmt->close();
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visa_id">Visa Type*</label>
                            <select name="visa_id" id="visa_id" class="form-control" required disabled>
                                <option value="">Select a country first</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="expected_completion_date">Expected Completion Date</label>
                            <input type="date" name="expected_completion_date" id="expected_completion_date" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Step 3: Documents -->
                    <div class="step-content" id="step-3">
                        <h4 class="step-title">Required Documents</h4>
                        <div id="required-documents-list">
                            <p class="text-muted">Please select a visa type to see required documents.</p>
                        </div>
                    </div>
                    
                    <!-- Step 4: Team Assignment -->
                    <div class="step-content" id="step-4">
                        <h4 class="step-title">Assign Case Manager</h4>
                        <div class="form-group">
                            <label for="team_member_id">Team Member*</label>
                            <select name="team_member_id" id="team_member_id" class="form-control">
                                <option value="">None (Assign Later)</option>
                                <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Step 5: Review and Submit -->
                    <div class="step-content" id="step-5">
                        <h4 class="step-title">Review Application</h4>
                        <div class="review-section">
                            <div id="review-summary">
                                <div class="review-item">
                                    <strong>Applicant:</strong> <span id="review-applicant">Not selected</span>
                                </div>
                                <div class="review-item">
                                    <strong>Priority:</strong> <span id="review-priority">Normal</span>
                                </div>
                                <div class="review-item">
                                    <strong>Country:</strong> <span id="review-country">Not selected</span>
                                </div>
                                <div class="review-item">
                                    <strong>Visa Type:</strong> <span id="review-visa">Not selected</span>
                                </div>
                                <div class="review-item">
                                    <strong>Expected Completion:</strong> <span id="review-date">Not set</span>
                                </div>
                                <div class="review-item">
                                    <strong>Case Manager:</strong> <span id="review-manager">None (Assign Later)</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="form-navigation">
                        <button type="button" class="btn secondary-btn" id="prevStepBtn" style="display:none;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn primary-btn" id="nextStepBtn">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" name="create_application" class="btn submit-btn" id="submitApplicationBtn" style="display:none;">
                            Create Application
                        </button>
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
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
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
    text-decoration: none;
}

.primary-btn:hover {
    background-color: #031c56;
    text-decoration: none;
    color: white;
}

.filter-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
    background-color: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.filter-section {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 200px;
}

.filter-section label {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark-color);
}

.filter-select, .search-input {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.filter-select:focus, .search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.applications-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.applications-table {
    width: 100%;
    border-collapse: collapse;
}

.applications-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.applications-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
    vertical-align: middle;
}

.applications-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.applications-table tbody tr:last-child td {
    border-bottom: none;
}

.reference-link {
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
}

.reference-link:hover {
    text-decoration: underline;
}

.applicant-info {
    display: flex;
    flex-direction: column;
}

.applicant-name {
    font-weight: 500;
}

.applicant-email {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge i {
    font-size: 8px;
}

.case-manager {
    display: flex;
    flex-direction: column;
}

.manager-name {
    font-weight: 500;
}

.manager-role {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.not-assigned {
    color: var(--secondary-color);
    font-style: italic;
}

.documents-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.document-progress {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background-color: var(--border-color);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: var(--success-color);
    border-radius: 4px;
}

.document-count {
    font-size: 0.8rem;
    color: var(--dark-color);
    white-space: nowrap;
}

.priority-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
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
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.priority-low {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.date {
    white-space: nowrap;
    font-size: 0.9rem;
}

.actions-cell {
    white-space: nowrap;
    width: 1%;
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
    margin-right: 4px;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-status {
    background-color: var(--warning-color);
}

.btn-status:hover {
    background-color: #e0b137;
}

.btn-assign {
    background-color: #4e73df;
}

.btn-assign:hover {
    background-color: #4262c3;
}

.btn-document {
    background-color: #36b9cc;
}

.btn-document:hover {
    background-color: #2c9faf;
}

.btn-comment {
    background-color: var(--success-color);
}

.btn-comment:hover {
    background-color: #18b07b;
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

/* Responsive Styles */
@media (max-width: 1200px) {
    .actions-header {
        width: 80px;
    }
}

@media (max-width: 992px) {
    .filter-container {
        flex-direction: column;
    }
    
    .filter-section {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .applications-table {
        display: block;
        overflow-x: auto;
    }
}

/* Add these styles to your existing <style> block */
.modal-lg {
    max-width: 700px;
}

.steps-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.steps-progress:after {
    content: "";
    position: absolute;
    height: 2px;
    background-color: var(--border-color);
    top: 20px;
    left: 40px;
    right: 40px;
    z-index: 1;
}

.step {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 20%;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--light-color);
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.step-label {
    font-size: 0.8rem;
    text-align: center;
    color: var(--secondary-color);
    max-width: 80px;
}

.step.active .step-number {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.step.active .step-label {
    color: var(--primary-color);
    font-weight: 600;
}

.step.completed .step-number {
    background-color: var(--success-color);
    border-color: var(--success-color);
    color: white;
}

.step-content {
    display: none;
    padding: 20px 0;
}

.step-content.active {
    display: block;
}

.step-title {
    margin: 0 0 20px;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.form-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.secondary-btn {
    background-color: var(--light-color);
    color: var(--dark-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.secondary-btn:hover {
    background-color: #e9ecef;
}

.review-section {
    background-color: var(--light-color);
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

.review-item {
    margin-bottom: 10px;
    display: flex;
}

.review-item strong {
    width: 40%;
    color: var(--dark-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Open create application modal when button is clicked
    const createApplicationBtn = document.getElementById('createApplicationBtn');
    if (createApplicationBtn) {
        createApplicationBtn.addEventListener('click', function() {
            document.getElementById('createApplicationModal').style.display = 'block';
        });
    }
    
    // Function to open status update modal
    window.openStatusModal = function(applicationId, currentStatus) {
        document.getElementById('status_application_id').value = applicationId;
        document.getElementById('new_status_id').value = currentStatus;
        document.getElementById('updateStatusModal').style.display = 'block';
    };
    
    // Function to open assignment modal
    window.openAssignModal = function(applicationId, currentManager) {
        document.getElementById('assign_application_id').value = applicationId;
        if (currentManager) {
            document.getElementById('team_member_id').value = currentManager;
        }
        document.getElementById('assignModal').style.display = 'block';
    };
    
    // Close modals when close button is clicked
    document.querySelectorAll('[data-dismiss="modal"]').forEach(element => {
        element.addEventListener('click', function() {
            document.getElementById('updateStatusModal').style.display = 'none';
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('createApplicationModal').style.display = 'none';
        });
    });
    
    // Close modals when clicking outside of them
    window.addEventListener('click', function(event) {
        const updateModal = document.getElementById('updateStatusModal');
        const assignModal = document.getElementById('assignModal');
        const createModal = document.getElementById('createApplicationModal');
        
        if (event.target === updateModal) {
            updateModal.style.display = 'none';
        }
        
        if (event.target === assignModal) {
            assignModal.style.display = 'none';
        }
        
        if (event.target === createModal) {
            createModal.style.display = 'none';
        }
    });
    
    // Filter functionality
    const statusFilter = document.getElementById('status-filter');
    const visaFilter = document.getElementById('visa-filter');
    const priorityFilter = document.getElementById('priority-filter');
    const searchInput = document.getElementById('search-input');
    const applicationRows = document.querySelectorAll('#applications-table tbody tr');
    
    // Function to filter table rows
    function filterApplications() {
        const statusValue = statusFilter.value;
        const visaValue = visaFilter.value;
        const priorityValue = priorityFilter.value;
        const searchValue = searchInput.value.toLowerCase();
        
        applicationRows.forEach(row => {
            const statusMatch = !statusValue || row.getAttribute('data-status') === statusValue;
            const visaMatch = !visaValue || row.getAttribute('data-visa') === visaValue;
            const priorityMatch = !priorityValue || row.getAttribute('data-priority') === priorityValue;
            
            // Search in reference, name and email
            const rowText = row.textContent.toLowerCase();
            const searchMatch = !searchValue || rowText.includes(searchValue);
            
            if (statusMatch && visaMatch && priorityMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Add event listeners to filters
    statusFilter.addEventListener('change', filterApplications);
    visaFilter.addEventListener('change', filterApplications);
    priorityFilter.addEventListener('change', filterApplications);
    searchInput.addEventListener('input', filterApplications);
    
    // Multi-step form functionality
    let currentStep = 1;
    const totalSteps = 5;
    
    const nextStepBtn = document.getElementById('nextStepBtn');
    const prevStepBtn = document.getElementById('prevStepBtn');
    const submitBtn = document.getElementById('submitApplicationBtn');
    
    if (nextStepBtn && prevStepBtn && submitBtn) {
        // Next button click
        nextStepBtn.addEventListener('click', function() {
            // Validate current step
            if (validateStep(currentStep)) {
                // Update step indicators
                document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
                
                // Move to next step
                document.getElementById(`step-${currentStep}`).classList.remove('active');
                currentStep++;
                document.getElementById(`step-${currentStep}`).classList.add('active');
                document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('active');
                
                // Update navigation buttons
                updateNavButtons();
                
                // If it's the last step, update review information
                if (currentStep === 5) {
                    updateReviewInfo();
                }
            }
        });
        
        // Previous button click
        prevStepBtn.addEventListener('click', function() {
            // Move to previous step
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
            currentStep--;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            
            // Update navigation buttons
            updateNavButtons();
        });
        
        // Handle country selection to populate visa types
        const countrySelect = document.getElementById('country_id');
        const visaSelect = document.getElementById('visa_id');
        
        if (countrySelect && visaSelect) {
            countrySelect.addEventListener('change', function() {
                const countryId = this.value;
                
                if (countryId) {
                    // Enable visa select
                    visaSelect.disabled = false;
                    visaSelect.innerHTML = '<option value="">Loading visa types...</option>';
                    
                    // Fetch visa types for selected country
                    fetch(`get_visas.php?country_id=${countryId}`)
                        .then(response => response.json())
                        .then(data => {
                            visaSelect.innerHTML = '<option value="">Select visa type</option>';
                            
                            data.forEach(visa => {
                                const option = document.createElement('option');
                                option.value = visa.visa_id;
                                option.textContent = visa.visa_type;
                                visaSelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error loading visa types:', error);
                            visaSelect.innerHTML = '<option value="">Error loading visa types</option>';
                        });
                } else {
                    // Disable visa select if no country selected
                    visaSelect.disabled = true;
                    visaSelect.innerHTML = '<option value="">Select a country first</option>';
                }
            });
            
            // Handle visa type selection to show required documents
            visaSelect.addEventListener('change', function() {
                const visaId = this.value;
                const documentsContainer = document.getElementById('required-documents-list');
                
                if (visaId && documentsContainer) {
                    documentsContainer.innerHTML = '<p>Loading required documents...</p>';
                    
                    // Fetch required documents for selected visa
                    fetch(`get_required_documents.php?visa_id=${visaId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                let html = '<div class="documents-checklist">';
                                data.forEach(doc => {
                                    const requiredBadge = doc.is_mandatory ? 
                                        '<span class="required-badge">Required</span>' : 
                                        '<span class="optional-badge">Optional</span>';
                                    
                                    html += `
                                    <div class="document-item">
                                        <div class="document-info">
                                            <div class="document-name">${doc.document_name}</div>
                                            <div class="document-meta">
                                                ${requiredBadge}
                                                <span class="document-type">${doc.document_type}</span>
                                            </div>
                                        </div>
                                    </div>`;
                                });
                                html += '</div>';
                                documentsContainer.innerHTML = html;
                            } else {
                                documentsContainer.innerHTML = '<p>No required documents for this visa type.</p>';
                            }
                        })
                        .catch(error => {
                            console.error('Error loading required documents:', error);
                            documentsContainer.innerHTML = '<p>Error loading required documents.</p>';
                        });
                } else {
                    documentsContainer.innerHTML = '<p class="text-muted">Please select a visa type to see required documents.</p>';
                }
            });
        }
    }
    
    // Function to validate steps
    function validateStep(step) {
        switch(step) {
            case 1:
                const userId = document.getElementById('user_id').value;
                if (!userId) {
                    alert('Please select an applicant.');
                    return false;
                }
                return true;
            
            case 2:
                const countryId = document.getElementById('country_id').value;
                const visaId = document.getElementById('visa_id').value;
                
                if (!countryId) {
                    alert('Please select a country.');
                    return false;
                }
                
                if (!visaId) {
                    alert('Please select a visa type.');
                    return false;
                }
                
                return true;
            
            case 3:
                // No validation required for documents view
                return true;
            
            case 4:
                // Team member assignment is optional
                return true;
            
            default:
                return true;
        }
    }
    
    // Function to update navigation buttons
    function updateNavButtons() {
        // Show/hide previous button
        if (currentStep > 1) {
            prevStepBtn.style.display = 'block';
        } else {
            prevStepBtn.style.display = 'none';
        }
        
        // Show/hide next and submit buttons
        if (currentStep === totalSteps) {
            nextStepBtn.style.display = 'none';
            submitBtn.style.display = 'block';
        } else {
            nextStepBtn.style.display = 'block';
            submitBtn.style.display = 'none';
        }
    }
    
    // Function to update review information
    function updateReviewInfo() {
        // Get form values
        const userSelect = document.getElementById('user_id');
        const prioritySelect = document.getElementById('priority');
        const countrySelect = document.getElementById('country_id');
        const visaSelect = document.getElementById('visa_id');
        const dateInput = document.getElementById('expected_completion_date');
        const teamSelect = document.getElementById('team_member_id');
        
        // Update review fields
        document.getElementById('review-applicant').textContent = userSelect.options[userSelect.selectedIndex]?.text || 'Not selected';
        document.getElementById('review-priority').textContent = prioritySelect.options[prioritySelect.selectedIndex]?.text || 'Normal';
        document.getElementById('review-country').textContent = countrySelect.options[countrySelect.selectedIndex]?.text || 'Not selected';
        document.getElementById('review-visa').textContent = visaSelect.options[visaSelect.selectedIndex]?.text || 'Not selected';
        document.getElementById('review-date').textContent = dateInput.value || 'Not set';
        document.getElementById('review-manager').textContent = teamSelect.options[teamSelect.selectedIndex]?.text || 'None (Assign Later)';
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
