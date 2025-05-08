<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Check if ID is provided in URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: team.php");
    exit;
}

$team_member_id = intval($_GET['id']);
$page_title = "Team Member Details";
$page_specific_css = "assets/css/team.css";
require_once 'includes/header.php';

// Get team member details - Using prepared statement for security
$query = "SELECT tm.id, tm.role, tm.custom_role_name, tm.permissions, tm.created_at, tm.phone,
          u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, 
          u.email_verified, u.created_at as joined_date
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          WHERE tm.id = ? AND tm.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if team member exists
if ($result->num_rows === 0) {
    header("Location: team.php?error=member_not_found");
    exit;
}

$member = $result->fetch_assoc();
$stmt->close();

// Get assigned tasks for this team member
$tasks_query = "SELECT t.id, t.name, t.description, t.priority, t.status, t.due_date, 
                ta.status as assignment_status, ta.started_at, ta.completed_at
                FROM tasks t
                JOIN task_assignments ta ON t.id = ta.task_id
                WHERE ta.team_member_id = ? AND ta.deleted_at IS NULL
                ORDER BY t.due_date ASC, t.priority DESC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param('i', $member['user_id']);
$stmt->execute();
$tasks_result = $stmt->get_result();
$tasks = [];

if ($tasks_result && $tasks_result->num_rows > 0) {
    while ($row = $tasks_result->fetch_assoc()) {
        $tasks[] = $row;
    }
}
$stmt->close();

// Handle member update - Change role, status, etc.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $role = $_POST['role'];
    $custom_role_name = isset($_POST['custom_role_name']) ? trim($_POST['custom_role_name']) : null;
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // Validate inputs
    $errors = [];
    if ($role === 'Custom' && empty($custom_role_name)) {
        $errors[] = "Custom role name is required";
    }
    
    if (empty($errors)) {
        // Update team member details
        $update_query = "UPDATE team_members SET role = ?, custom_role_name = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sssi', $role, $custom_role_name, $phone, $team_member_id);
        
        if ($stmt->execute()) {
            $success_message = "Team member details updated successfully";
            $stmt->close();
            
            // Refresh member data
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $team_member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Error updating team member: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle status change (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $status = $_POST['status'];
    $user_id = $_POST['user_id'];
    
    // Update user status
    $update_query = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('si', $status, $user_id);
    
    if ($stmt->execute()) {
        $status_text = ($status === 'active') ? 'activated' : 'deactivated';
        $success_message = "Team member {$status_text} successfully";
        $stmt->close();
        
        // Refresh member data
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $team_member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error_message = "Error updating team member status: " . $conn->error;
        $stmt->close();
    }
}

// Handle resend invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
    $user_id = $_POST['user_id'];
    
    // Get user details
    $user_query = "SELECT first_name, last_name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // Create new token
        $token = bin2hex(random_bytes(16)); // Generate a 32-character token
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Update token in database
        $update_query = "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssi', $token, $expires, $user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Send invitation email
            $invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Invitation to join the team at Visafy";
            
            $message = "
            <html>
            <head>
                <title>Team Invitation</title>
            </head>
            <body>
                <p>Hello {$user['first_name']} {$user['last_name']},</p>
                <p>You have been invited to join the team at Visafy.</p>
                <p>Please click the link below to activate your account and set your password:</p>
                <p><a href='{$invite_link}'>{$invite_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>If you did not expect this invitation, please ignore this email.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($user['email'], $subject, $message, $headers);
            
            $success_message = "Invitation resent to {$user['email']}";
        } else {
            $error_message = "Error resending invitation: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = "User not found";
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Team Member Details</h1>
            <p>View and manage team member information</p>
        </div>
        <div class="header-actions">
            <a href="team.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Team
            </a>
            <button type="button" class="btn primary-btn" id="editMemberBtn">
                <i class="fas fa-edit"></i> Edit Details
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="member-profile-container">
        <div class="member-profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($member['profile_picture']) && file_exists('../../uploads/profiles/' . $member['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo $member['profile_picture']; ?>" alt="Profile picture">
                    <?php else: ?>
                        <div class="profile-initials">
                            <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-name-container">
                    <h2><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h2>
                    <div class="member-role">
                        <?php echo $member['role'] === 'Custom' ? htmlspecialchars($member['custom_role_name']) : htmlspecialchars($member['role']); ?>
                    </div>
                    <div class="member-status">
                        <?php if ($member['status'] === 'active'): ?>
                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                        <?php else: ?>
                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                        <?php endif; ?>
                        
                        <?php if (!$member['email_verified']): ?>
                            <span class="status-badge pending"><i class="fas fa-clock"></i> Pending Activation</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-actions">
                    <?php if ($member['status'] === 'active'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" name="change_status" class="btn-action btn-deactivate" 
                                    title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this team member?')">
                                <i class="fas fa-user-slash"></i> Deactivate
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" name="change_status" class="btn-action btn-activate" 
                                    title="Activate">
                                <i class="fas fa-user-check"></i> Activate
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (!$member['email_verified']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <button type="submit" name="resend_invite" class="btn-action btn-resend" 
                                    title="Resend Invitation" onclick="return confirm('Resend invitation email to this team member?')">
                                <i class="fas fa-paper-plane"></i> Resend Invitation
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-section">
                    <h3>Contact Information</h3>
                    <div class="detail-row">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($member['email']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value"><?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : '-'; ?></div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Account Details</h3>
                    <div class="detail-row">
                        <div class="detail-label">User ID</div>
                        <div class="detail-value"><?php echo $member['user_id']; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <?php if ($member['status'] === 'active'): ?>
                                <span class="status-text active">Active</span>
                            <?php else: ?>
                                <span class="status-text inactive">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email Verification</div>
                        <div class="detail-value">
                            <?php if ($member['email_verified']): ?>
                                <span class="status-text verified">Verified</span>
                            <?php else: ?>
                                <span class="status-text pending">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Member Since</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($member['joined_date'])); ?></div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Role & Permissions</h3>
                    <div class="detail-row">
                        <div class="detail-label">Role</div>
                        <div class="detail-value">
                            <?php echo $member['role'] === 'Custom' ? htmlspecialchars($member['custom_role_name']) : htmlspecialchars($member['role']); ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Permissions</div>
                        <div class="detail-value permissions-list">
                            <?php 
                            if (!empty($member['permissions'])) {
                                $permissions = json_decode($member['permissions'], true);
                                if (is_array($permissions) && !empty($permissions)) {
                                    echo '<ul>';
                                    foreach ($permissions as $permission) {
                                        echo '<li>' . htmlspecialchars($permission) . '</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo 'Default role permissions';
                                }
                            } else {
                                echo 'Default role permissions';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tasks-section">
            <h3>Assigned Tasks</h3>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <p>No tasks assigned to this team member yet.</p>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card">
                            <div class="task-header">
                                <div class="task-name">
                                    <a href="task_detail.php?id=<?php echo $task['id']; ?>">
                                        <?php echo htmlspecialchars($task['name']); ?>
                                    </a>
                                </div>
                                <div class="task-badges">
                                    <span class="priority-badge <?php echo strtolower($task['priority']); ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                    <span class="status-badge <?php echo str_replace('_', '-', $task['assignment_status']); ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($task['assignment_status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="task-description">
                                <?php echo !empty($task['description']) ? nl2br(htmlspecialchars(substr($task['description'], 0, 100))) . (strlen($task['description']) > 100 ? '...' : '') : 'No description'; ?>
                            </div>
                            <div class="task-footer">
                                <?php if (!empty($task['due_date'])): ?>
                                    <div class="task-due-date">
                                        <i class="fas fa-calendar-alt"></i> 
                                        Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($task['assignment_status'] === 'completed' && !empty($task['completed_at'])): ?>
                                    <div class="task-completed">
                                        <i class="fas fa-check-circle"></i>
                                        Completed: <?php echo date('M j, Y', strtotime($task['completed_at'])); ?>
                                    </div>
                                <?php elseif ($task['assignment_status'] === 'in_progress' && !empty($task['started_at'])): ?>
                                    <div class="task-started">
                                        <i class="fas fa-hourglass-half"></i>
                                        Started: <?php echo date('M j, Y', strtotime($task['started_at'])); ?>
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

<!-- Edit Member Modal -->
<div class="modal" id="editMemberModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Team Member</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_team_member.php?id=<?php echo $team_member_id; ?>" method="POST" id="editMemberForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_first_name">First Name</label>
                            <input type="text" id="modal_first_name" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" disabled>
                            <small class="form-text">First name cannot be changed</small>
                        </div>
                        <div class="form-group">
                            <label for="modal_last_name">Last Name</label>
                            <input type="text" id="modal_last_name" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" disabled>
                            <small class="form-text">Last name cannot be changed</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_email">Email Address</label>
                        <input type="email" id="modal_email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" disabled>
                        <small class="form-text">Email cannot be changed</small>
                    </div>
                    <div class="form-group">
                        <label for="modal_phone">Phone Number</label>
                        <input type="tel" name="phone" id="modal_phone" class="form-control" value="<?php echo htmlspecialchars($member['phone']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_role">Role*</label>
                            <select name="role" id="modal_role" class="form-control" required>
                                <option value="Case Manager" <?php echo $member['role'] === 'Case Manager' ? 'selected' : ''; ?>>Case Manager</option>
                                <option value="Document Creator" <?php echo $member['role'] === 'Document Creator' ? 'selected' : ''; ?>>Document Creator</option>
                                <option value="Career Consultant" <?php echo $member['role'] === 'Career Consultant' ? 'selected' : ''; ?>>Career Consultant</option>
                                <option value="Business Plan Creator" <?php echo $member['role'] === 'Business Plan Creator' ? 'selected' : ''; ?>>Business Plan Creator</option>
                                <option value="Immigration Assistant" <?php echo $member['role'] === 'Immigration Assistant' ? 'selected' : ''; ?>>Immigration Assistant</option>
                                <option value="Social Media Manager" <?php echo $member['role'] === 'Social Media Manager' ? 'selected' : ''; ?>>Social Media Manager</option>
                                <option value="Leads & CRM Manager" <?php echo $member['role'] === 'Leads & CRM Manager' ? 'selected' : ''; ?>>Leads & CRM Manager</option>
                                <option value="Custom" <?php echo $member['role'] === 'Custom' ? 'selected' : ''; ?>>Custom Role</option>
                            </select>
                        </div>
                        <div class="form-group" id="modal_custom_role_group" style="<?php echo $member['role'] === 'Custom' ? 'display: block;' : 'display: none;'; ?>">
                            <label for="modal_custom_role_name">Custom Role Name*</label>
                            <input type="text" name="custom_role_name" id="modal_custom_role_name" class="form-control" value="<?php echo htmlspecialchars($member['custom_role_name']); ?>">
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_member" class="btn submit-btn">Save Changes</button>
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

.header-actions {
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
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
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
    background-color: var(--light-color);
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

.member-profile-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.member-profile-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.profile-header {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    position: relative;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 20px;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-initials {
    color: white;
    font-weight: 600;
    font-size: 28px;
}

.profile-name-container {
    flex: 1;
}

.profile-name-container h2 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.member-role {
    color: var(--dark-color);
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.member-status {
    display: flex;
    gap: 10px;
    align-items: center;
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

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge i {
    font-size: 8px;
}

.profile-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
}

.btn-activate {
    background-color: var(--success-color);
}

.btn-activate:hover {
    background-color: #18b07b;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
}

.btn-resend {
    background-color: #4e73df;
}

.btn-resend:hover {
    background-color: #4262c3;
}

.profile-details {
    padding: 20px;
}

.detail-section {
    margin-bottom: 25px;
}

.detail-section h3 {
    color: var(--primary-color);
    font-size: 1.1rem;
    margin: 0 0 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
}

.detail-label {
    width: 160px;
    font-weight: 500;
    color: var(--dark-color);
}

.detail-value {
    flex: 1;
    color: var(--secondary-color);
}

.status-text {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 13px;
}

.status-text.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-text.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-text.verified {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-text.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.permissions-list ul {
    margin: 0;
    padding-left: 18px;
}

.permissions-list li {
    margin-bottom: 4px;
}

/* Tasks Section */
.tasks-section {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.tasks-section h3 {
    color: var(--primary-color);
    font-size: 1.2rem;
    margin: 0 0 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.task-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.task-card {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    transition: box-shadow 0.2s;
}

.task-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.task-name {
    font-weight: 500;
    font-size: 1.05rem;
}

.task-name a {
    color: var(--primary-color);
    text-decoration: none;
}

.task-name a:hover {
    text-decoration: underline;
}

.task-badges {
    display: flex;
    gap: 8px;
}

.priority-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.priority-badge.high {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.priority-badge.normal {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.priority-badge.low {
    background-color: rgba(28, 132, 200, 0.1);
    color: #2184c7;
}

.task-description {
    color: var(--secondary-color);
    font-size: 0.95rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.task-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.task-due-date, .task-completed, .task-started {
    display: flex;
    align-items: center;
    gap: 5px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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

.form-text {
    font-size: 12px;
    color: var(--secondary-color);
    margin-top: 4px;
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

.form-control:disabled {
    background-color: #f8f8f8;
    cursor: not-allowed;
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

@media (max-width: 992px) {
    .member-profile-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar {
        margin: 0 auto 15px;
    }
    
    .profile-actions {
        margin-top: 15px;
        width: 100%;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 5px;
    }
}
</style>

<script>
// Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const editMemberBtn = document.getElementById('editMemberBtn');
    const editMemberModal = document.getElementById('editMemberModal');
    const modalRoleSelect = document.getElementById('modal_role');
    const modalCustomRoleGroup = document.getElementById('modal_custom_role_group');
    const closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
    
    // Open edit modal
    editMemberBtn.addEventListener('click', function() {
        editMemberModal.style.display = 'block';
    });
    
    // Close modal when close button is clicked
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            editMemberModal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === editMemberModal) {
            editMemberModal.style.display = 'none';
        }
    });
    
    // Toggle custom role field visibility
    modalRoleSelect.addEventListener('change', function() {
        if (this.value === 'Custom') {
            modalCustomRoleGroup.style.display = 'block';
            document.getElementById('modal_custom_role_name').setAttribute('required', 'required');
        } else {
            modalCustomRoleGroup.style.display = 'none';
            document.getElementById('modal_custom_role_name').removeAttribute('required');
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
