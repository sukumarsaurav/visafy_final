<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Get user data - Using prepared statement
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.email_verified, 
          u.status, u.created_at, u.profile_picture, u.auth_provider, u.user_type
          FROM users u
          WHERE u.id = ? AND u.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION["id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // Redirect if user not found
    header("Location: index.php");
    exit();
}
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // Validate inputs
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists for another user
    if ($email !== $user['email']) {
        $check_query = "SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $email, $_SESSION["id"]);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $check_stmt->close();
    }
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture']; // Default to current
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $errors[] = "Image size must be less than 2MB";
        } else {
            // Generate unique filename
            $filename = uniqid() . '_' . $_FILES['profile_picture']['name'];
            $upload_path = '../../uploads/profiles/' . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../../uploads/profiles/')) {
                mkdir('../../uploads/profiles/', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists('../../uploads/profiles/' . $user['profile_picture'])) {
                    unlink('../../uploads/profiles/' . $user['profile_picture']);
                }
                $profile_picture = $filename;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }
    
    if (empty($errors)) {
        // Update user record
        $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ? 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssssi', $first_name, $last_name, $email, $profile_picture, $_SESSION["id"]);
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION["first_name"] = $first_name;
            $_SESSION["last_name"] = $last_name;
            
            $success_message = "Profile updated successfully";
            
            // Refresh user data
            $user['first_name'] = $first_name;
            $user['last_name'] = $last_name;
            $user['email'] = $email;
            $user['profile_picture'] = $profile_picture;
            
            $stmt->close();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        // Get current password from database
        $password_query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($password_query);
        $stmt->bind_param('i', $_SESSION["id"]);
        $stmt->execute();
        $password_result = $stmt->get_result();
        $stored_password = $password_result->fetch_assoc()['password'];
        $stmt->close();
        
        // Verify current password
        if (password_verify($current_password, $stored_password)) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('si', $hashed_password, $_SESSION["id"]);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully";
                $stmt->close();
            } else {
                $error_message = "Error changing password: " . $conn->error;
                $stmt->close();
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Profile</h1>
            <p>View and update your profile information.</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-image-container">
                <?php if (!empty($user['profile_picture']) && file_exists('../../uploads/profiles/' . $user['profile_picture'])): ?>
                    <img src="../../uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile picture" class="profile-image">
                <?php else: ?>
                    <div class="profile-initials">
                        <?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?>
                    </div>
                <?php endif; ?>
                <div class="profile-upload-overlay" id="uploadOverlay">
                    <i class="fas fa-camera"></i>
                    <span>Change Photo</span>
                </div>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <div class="profile-meta">
                    <div class="meta-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user-shield"></i>
                        <span><?php echo ucfirst($user['user_type']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <?php if ($user['email_verified']): ?>
                            <i class="fas fa-check-circle text-success"></i>
                            <span class="text-success">Email Verified</span>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger"></i>
                            <span class="text-danger">Email Not Verified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-actions">
                <button id="editProfileBtn" class="action-btn">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
                <button id="changePasswordBtn" class="action-btn secondary-btn">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </div>
        
        <div class="profile-content">
            <div class="section-tabs">
                <button class="tab-btn active" data-tab="profile-details">Profile Details</button>
                <button class="tab-btn" data-tab="account-settings">Account Settings</button>
                <button class="tab-btn" data-tab="activity-log">Activity Log</button>
            </div>
            
            <div class="tab-content">
                <!-- Profile Details Tab -->
                <div class="tab-pane active" id="profile-details">
                    <form id="profile-form" action="profile.php" method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name*</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name*</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address*</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                        </div>
                        <div class="form-group hidden">
                            <label for="profile_picture">Profile Picture</label>
                            <input type="file" name="profile_picture" id="profile_picture" class="form-control-file" accept="image/*">
                            <small class="form-text text-muted">Max file size: 2MB. Allowed formats: JPG, PNG, GIF</small>
                        </div>
                        <div class="form-buttons">
                            <button type="submit" name="update_profile" class="btn submit-btn">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Settings Tab -->
                <div class="tab-pane" id="account-settings">
                    <div class="settings-section">
                        <h3>Change Password</h3>
                        <form id="password-form" action="profile.php" method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password*</label>
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">New Password*</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password*</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" name="change_password" class="btn submit-btn">Change Password</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Notification Preferences</h3>
                        <form id="notifications-form" action="profile.php" method="POST">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="email_notifications" name="email_notifications" checked>
                                    <label for="email_notifications">Email Notifications</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="app_notifications" name="app_notifications" checked>
                                    <label for="app_notifications">In-App Notifications</label>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" name="update_notifications" class="btn submit-btn">Update Preferences</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Activity Log Tab -->
                <div class="tab-pane" id="activity-log">
                    <div class="activity-log-container">
                        <div class="activity-filters">
                            <div class="filter-group">
                                <label for="activity-filter">Filter by:</label>
                                <select id="activity-filter" class="filter-control">
                                    <option value="all">All Activities</option>
                                    <option value="login">Logins</option>
                                    <option value="profile">Profile Updates</option>
                                    <option value="password">Password Changes</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon login">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">Login</div>
                                    <div class="activity-description">You logged in from Chrome on Windows</div>
                                    <div class="activity-time">Today, 9:41 AM</div>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon profile">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">Profile Updated</div>
                                    <div class="activity-description">You updated your profile information</div>
                                    <div class="activity-time">Yesterday, 3:24 PM</div>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon password">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">Password Changed</div>
                                    <div class="activity-description">You changed your password</div>
                                    <div class="activity-time">Aug 15, 2023, 5:30 PM</div>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon login">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">Login</div>
                                    <div class="activity-description">You logged in from Safari on iPhone</div>
                                    <div class="activity-time">Aug 14, 2023, 10:15 AM</div>
                                </div>
                            </div>
                        </div>
                    </div>
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

.profile-container {
    display: flex;
    gap: 30px;
}

.profile-sidebar {
    flex: 0 0 300px;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.profile-image-container {
    position: relative;
    width: 140px;
    height: 140px;
    margin-bottom: 20px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-color);
    color: white;
    font-size: 48px;
    font-weight: 600;
}

.profile-upload-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 8px 0;
    text-align: center;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    opacity: 0;
    transition: opacity 0.2s;
}

.profile-upload-overlay i {
    font-size: 18px;
    margin-bottom: 2px;
}

.profile-image-container:hover .profile-upload-overlay {
    opacity: 1;
}

.profile-info {
    text-align: center;
    margin-bottom: 20px;
}

.profile-info h2 {
    color: var(--dark-color);
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.profile-meta {
    margin-top: 15px;
    text-align: left;
}

.meta-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    color: var(--secondary-color);
}

.meta-item i {
    width: 20px;
    margin-right: 10px;
    text-align: center;
}

.profile-actions {
    width: 100%;
    margin-top: auto;
}

.action-btn {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.action-btn i {
    width: 16px;
    text-align: center;
}

.action-btn:last-child {
    margin-bottom: 0;
}

.action-btn {
    background-color: var(--primary-color);
    color: white;
}

.action-btn:hover {
    background-color: #031c56;
}

.secondary-btn {
    background-color: var(--secondary-color);
    color: white;
}

.secondary-btn:hover {
    background-color: #717486;
}

.profile-content {
    flex: 1;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.section-tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    padding: 15px 20px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--secondary-color);
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s, border-color 0.2s;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-content {
    padding: 20px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block !important;
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

.form-group:last-child {
    margin-bottom: 0;
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

.form-control-file {
    width: 100%;
    padding: 10px 0;
}

.form-text {
    font-size: 12px;
    margin-top: 5px;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
}

.submit-btn:hover {
    background-color: #031c56;
}

.hidden {
    display: none;
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 10px;
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.settings-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.settings-section h3 {
    color: var(--dark-color);
    margin-bottom: 15px;
}

.activity-filters {
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 150px;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    padding: 15px;
    border-radius: 4px;
    background-color: var(--light-color);
    transition: transform 0.2s;
}

.activity-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    flex-shrink: 0;
}

.activity-icon.login {
    background-color: #4e73df;
}

.activity-icon.profile {
    background-color: var(--success-color);
}

.activity-icon.password {
    background-color: var(--warning-color);
}

.activity-details {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.activity-description {
    color: var(--secondary-color);
    font-size: 14px;
    margin-bottom: 5px;
}

.activity-time {
    color: var(--secondary-color);
    font-size: 12px;
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

.text-success {
    color: var(--success-color);
}

.text-danger {
    color: var(--danger-color);
}

@media (max-width: 992px) {
    .profile-container {
        flex-direction: column;
    }
    
    .profile-sidebar {
        flex: none;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .section-tabs {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        text-align: center;
        padding: 10px;
    }
}

</style>

<script>
// Upload profile picture
document.getElementById('uploadOverlay').addEventListener('click', function() {
    document.getElementById('profile_picture').click();
});

document.getElementById('profile_picture').addEventListener('change', function() {
    // Preview the image before upload
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var profileImage = document.querySelector('.profile-image');
            var profileInitials = document.querySelector('.profile-initials');
            
            if (profileImage) {
                profileImage.src = e.target.result;
            } else if (profileInitials) {
                // If there's no image yet, create one
                profileInitials.style.display = 'none';
                var img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'profile-image';
                document.querySelector('.profile-image-container').insertBefore(img, profileInitials);
            }
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Tab switching functionality
document.querySelectorAll('.tab-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        // Remove active class from all tab buttons and panes
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.tab-pane').forEach(function(pane) {
            pane.classList.remove('active');
            pane.style.display = 'none'; // Explicitly hide all panes
        });
        
        // Add active class to clicked button and corresponding pane
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        const activePane = document.getElementById(tabId);
        activePane.classList.add('active');
        activePane.style.display = 'block'; // Explicitly show active pane
    });
});

// Button shortcuts
document.getElementById('editProfileBtn').addEventListener('click', function() {
    // Activate the profile details tab
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        pane.classList.remove('active');
    });
    
    document.querySelector('[data-tab="profile-details"]').classList.add('active');
    document.getElementById('profile-details').classList.add('active');
    
    // Focus on first name field
    document.getElementById('first_name').focus();
});

document.getElementById('changePasswordBtn').addEventListener('click', function() {
    // Activate the account settings tab
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        pane.classList.remove('active');
    });
    
    document.querySelector('[data-tab="account-settings"]').classList.add('active');
    document.getElementById('account-settings').classList.add('active');
    
    // Focus on current password field
    document.getElementById('current_password').focus();
});

// Filter activities
document.getElementById('activity-filter').addEventListener('change', function() {
    const filterValue = this.value;
    const activityItems = document.querySelectorAll('.activity-item');
    
    if (filterValue === 'all') {
        activityItems.forEach(function(item) {
            item.style.display = 'flex';
        });
    } else {
        activityItems.forEach(function(item) {
            const activityType = item.querySelector('.activity-icon').classList.contains(filterValue);
            item.style.display = activityType ? 'flex' : 'none';
        });
    }
});

// Password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        if (this.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
});

// Ensure one tab is active by default
document.addEventListener('DOMContentLoaded', function() {
    // Make sure profile-details tab is active on page load
    document.getElementById('profile-details').style.display = 'block';
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
