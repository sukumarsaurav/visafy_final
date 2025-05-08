<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Check if ID is provided in URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: team.php");
    exit;
}

$member_id = intval($_GET['id']);
$page_title = "Edit Team Member";
$page_specific_css = "assets/css/team.css";
require_once 'includes/header.php';

// Get team member details
$query = "SELECT tm.id, tm.role, tm.custom_role_name, tm.permissions, tm.phone,
          u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, 
          u.email_verified, u.created_at as joined_date
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          WHERE tm.id = ? AND tm.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $member_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if team member exists
if ($result->num_rows === 0) {
    header("Location: team.php?error=member_not_found");
    exit;
}

$member = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle member update
    if (isset($_POST['update_member'])) {
        $role = $_POST['role'];
        $custom_role_name = isset($_POST['custom_role_name']) ? trim($_POST['custom_role_name']) : null;
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : null;
        
        // Validate inputs
        $errors = [];
        if ($role === 'Custom' && empty($custom_role_name)) {
            $errors[] = "Custom role name is required";
        }
        
        if (empty($errors)) {
            // Convert permissions array to JSON if provided
            $permissions_json = null;
            if (!empty($permissions) && is_array($permissions)) {
                $permissions_json = json_encode($permissions);
            }
            
            // Update team member details
            $update_query = "UPDATE team_members SET role = ?, custom_role_name = ?, phone = ?, permissions = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ssssi', $role, $custom_role_name, $phone, $permissions_json, $member_id);
            
            if ($stmt->execute()) {
                $success_message = "Team member updated successfully";
                $stmt->close();
                
                // Refresh member data
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $member_id);
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
    
    // Handle status change
    if (isset($_POST['change_status'])) {
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
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Error updating team member status: " . $conn->error;
            $stmt->close();
        }
    }
    
    // Handle resending invitation
    if (isset($_POST['resend_invite'])) {
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
}

// Get available permissions
$available_permissions = [
    'manage_clients',
    'view_applications',
    'edit_applications',
    'create_documents',
    'view_documents',
    'edit_documents',
    'manage_tasks',
    'view_reports',
    'access_dashboard',
    'manage_team',
    'manage_settings'
];

// Parse existing permissions
$current_permissions = [];
if (!empty($member['permissions'])) {
    $current_permissions = json_decode($member['permissions'], true) ?: [];
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Team Member</h1>
            <p>Update team member information and permissions</p>
        </div>
        <div class="header-actions">
            <a href="team.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Team
            </a>
            <a href="view_team_member.php?id=<?php echo $member_id; ?>" class="btn secondary-btn">
                <i class="fas fa-user"></i> View Profile
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="edit-member-container">
        <div class="edit-member-card">
            <div class="card-header">
                <h2>
                    <?php if (!empty($member['profile_picture']) && file_exists('../../uploads/profiles/' . $member['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo $member['profile_picture']; ?>" alt="Profile picture" class="profile-small">
                    <?php else: ?>
                        <div class="profile-initials-small">
                            <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                </h2>
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
            
            <form action="edit_member.php?id=<?php echo $member_id; ?>" method="POST" class="edit-form">
                <div class="form-section">
                    <h3>Basic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" disabled>
                            <div class="form-text">First name cannot be changed</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" disabled>
                            <div class="form-text">Last name cannot be changed</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" disabled>
                        <div class="form-text">Email address cannot be changed</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($member['phone']); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Role & Permissions</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role*</label>
                            <select name="role" id="role" class="form-control" required>
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
                        
                        <div class="form-group" id="custom_role_group" style="<?php echo $member['role'] === 'Custom' ? 'display: block;' : 'display: none;'; ?>">
                            <label for="custom_role_name">Custom Role Name*</label>
                            <input type="text" name="custom_role_name" id="custom_role_name" class="form-control" value="<?php echo htmlspecialchars($member['custom_role_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group permissions-group">
                        <label>Permissions</label>
                        <div class="permissions-list">
                            <?php foreach($available_permissions as $permission): ?>
                                <div class="permission-item">
                                    <label class="permission-label">
                                        <input type="checkbox" name="permissions[]" value="<?php echo $permission; ?>" 
                                            <?php echo in_array($permission, $current_permissions) ? 'checked' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $permission)); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_member" class="btn primary-btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <div class="actions-sidebar">
            <div class="sidebar-card">
                <h3>Account Actions</h3>
                
                <div class="action-buttons">
                    <?php if ($member['status'] === 'active'): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" name="change_status" class="btn btn-danger btn-block" 
                                    onclick="return confirm('Are you sure you want to deactivate this team member?')">
                                <i class="fas fa-user-slash"></i> Deactivate Account
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" name="change_status" class="btn btn-success btn-block">
                                <i class="fas fa-user-check"></i> Activate Account
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (!$member['email_verified']): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                            <button type="submit" name="resend_invite" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane"></i> Resend Invitation
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="view_team_member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary btn-block">
                        <i class="fas fa-eye"></i> View Full Profile
                    </a>
                </div>
            </div>
            
            <div class="sidebar-card">
                <h3>Member Since</h3>
                <div class="member-info">
                    <p><?php echo date('F j, Y', strtotime($member['joined_date'])); ?></p>
                </div>
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

.edit-member-container {
    display: grid;
    grid-template-columns: 3fr 1fr;
    gap: 20px;
}

.edit-member-card, .sidebar-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
    display: flex;
    align-items: center;
}

.profile-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}

.profile-initials-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}

.member-status {
    display: flex;
    gap: 10px;
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

.edit-form {
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
    font-size: 1.2rem;
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

.form-control:disabled {
    background-color: #f8f8f8;
    cursor: not-allowed;
}

.form-text {
    font-size: 12px;
    color: var(--secondary-color);
    margin-top: 4px;
}

.permissions-group {
    margin-top: 15px;
}

.permissions-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    background-color: var(--light-color);
}

.permission-item {
    margin-bottom: 8px;
}

.permission-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.permission-label input {
    margin: 0;
}

.form-actions {
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
}

.sidebar-card {
    margin-bottom: 20px;
}

.sidebar-card h3 {
    padding: 15px 20px;
    margin: 0;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
    font-size: 1.1rem;
}

.member-info {
    padding: 15px 20px;
}

.member-info p {
    margin: 0;
    color: var(--secondary-color);
}

.action-buttons {
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-form {
    margin-bottom: 0;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s;
    border: none;
}

.btn-block {
    display: flex;
    width: 100%;
}

.primary-btn, .btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover, .btn-primary:hover {
    background-color: #031c56;
}

.secondary-btn, .btn-secondary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.secondary-btn:hover, .btn-secondary:hover {
    background-color: var(--light-color);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #d44235;
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #18b07b;
}

@media (max-width: 992px) {
    .edit-member-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .permissions-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle custom role field visibility
    const roleSelect = document.getElementById('role');
    const customRoleGroup = document.getElementById('custom_role_group');
    const customRoleNameInput = document.getElementById('custom_role_name');
    
    if (roleSelect && customRoleGroup && customRoleNameInput) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'Custom') {
                customRoleGroup.style.display = 'block';
                customRoleNameInput.setAttribute('required', 'required');
            } else {
                customRoleGroup.style.display = 'none';
                customRoleNameInput.removeAttribute('required');
            }
        });
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
