<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Get the current user data
$user_id = $_SESSION["id"];
$query = "SELECT u.*, oauth.provider, oauth.provider_user_id 
          FROM users u 
          LEFT JOIN oauth_tokens oauth ON u.id = oauth.user_id AND oauth.provider = 'google'
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Initialize variables for form handling
$success_message = '';
$error_message = '';
$validation_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Profile Update
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        // Validate inputs
        if (empty($first_name)) {
            $validation_errors[] = "First name is required";
        }
        if (empty($last_name)) {
            $validation_errors[] = "Last name is required";
        }
        if (empty($email)) {
            $validation_errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Invalid email format";
        }
        
        // Check if email already exists (if changing email)
        if ($email !== $user_data['email']) {
            $email_check = "SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL";
            $stmt = $conn->prepare($email_check);
            $stmt->bind_param('si', $email, $user_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $validation_errors[] = "Email already in use by another account";
            }
            $stmt->close();
        }
        
        // Handle profile picture upload
        $profile_picture = $user_data['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                $validation_errors[] = "Only JPG, PNG or GIF files are allowed";
            } elseif ($_FILES['profile_picture']['size'] > $max_size) {
                $validation_errors[] = "File size should be less than 2MB";
            } else {
                $upload_dir = '../../uploads/profiles/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    $profile_picture = $filename;
                } else {
                    $validation_errors[] = "Failed to upload profile picture";
                }
            }
        }
        
        // Update profile if no validation errors
        if (empty($validation_errors)) {
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ssssi', $first_name, $last_name, $email, $profile_picture, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully";
                
                // Update session variables
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["email"] = $email;
                
                // Refresh user data
                $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
                $user_data = $result->fetch_assoc();
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Update just the profile picture (Ajax request)
    if (isset($_POST['update_profile_picture']) && isset($_FILES['profile_picture'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $validation_errors = [];
        
        if ($_FILES['profile_picture']['error'] != 0) {
            $validation_errors[] = "Error uploading file. Please try again.";
        } elseif (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $validation_errors[] = "Only JPG, PNG or GIF files are allowed";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $validation_errors[] = "File size should be less than 2MB";
        } else {
            $upload_dir = '../../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                // Update database
                $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('si', $filename, $user_id);
                
                if ($stmt->execute()) {
                    // Success - redirect to refresh the page
                    header('Location: profile.php?success=profile_picture_updated');
                    exit;
                } else {
                    $validation_errors[] = "Error updating profile picture in database";
                }
                $stmt->close();
            } else {
                $validation_errors[] = "Failed to upload profile picture";
            }
        }
        
        if (!empty($validation_errors)) {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Password Update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Skip password validation for OAuth users
        if ($user_data['auth_provider'] === 'google') {
            $validation_errors[] = "Password cannot be changed for Google-linked accounts";
        } else {
            // Validate inputs
            if (empty($current_password)) {
                $validation_errors[] = "Current password is required";
            }
            if (empty($new_password)) {
                $validation_errors[] = "New password is required";
            } elseif (strlen($new_password) < 8) {
                $validation_errors[] = "New password must be at least 8 characters long";
            }
            if ($new_password !== $confirm_password) {
                $validation_errors[] = "New passwords do not match";
            }
            
            // Verify current password
            if (empty($validation_errors)) {
                if (!password_verify($current_password, $user_data['password'])) {
                    $validation_errors[] = "Current password is incorrect";
                }
            }
            
            // Update password if no validation errors
            if (empty($validation_errors)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('si', $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password updated successfully";
                } else {
                    $error_message = "Error updating password: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = implode("<br>", $validation_errors);
            }
        }
    }
    
    // Account link with Google
    if (isset($_POST['link_google'])) {
        // This would redirect to OAuth consent screen
        // For demo purposes, just showing what would happen
        $success_message = "Google account linking functionality would be implemented here";
    }
    
    // Account unlink from Google
    if (isset($_POST['unlink_google'])) {
        // Only unlink if there's a password set
        if (empty($user_data['password']) || $user_data['password'] === '') {
            $error_message = "You must set a password first before unlinking your Google account";
        } else {
            $delete_query = "DELETE FROM oauth_tokens WHERE user_id = ? AND provider = 'google'";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param('i', $user_id);
            
            if ($stmt->execute()) {
                $update_query = "UPDATE users SET auth_provider = 'local' WHERE id = ?";
                $stmt_update = $conn->prepare($update_query);
                $stmt_update->bind_param('i', $user_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                $success_message = "Google account unlinked successfully";
                
                // Refresh user data
                $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
                $user_data = $result->fetch_assoc();
            } else {
                $error_message = "Error unlinking Google account: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'profile_picture_updated') {
    $success_message = "Profile picture updated successfully";
}

// Get profile picture URL
$profile_img = '../../assets/images/default-profile.jpg';
if (!empty($user_data['profile_picture'])) {
    $profile_path = '../../uploads/profiles/' . $user_data['profile_picture'];
    if (file_exists($profile_path)) {
        $profile_img = $profile_path;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Profile</h1>
            <p>Manage your personal information and account settings</p>
        </div>
    </div>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-image-container">
                <img src="<?php echo $profile_img; ?>" alt="Profile Picture" class="profile-image" id="profile-image-preview">
                <div class="change-photo-overlay">
                    <label for="profile-picture-upload" class="change-photo-btn">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h2>
                <p class="role">Administrator</p>
                <div class="account-status">
                    <span class="status-badge <?php echo $user_data['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($user_data['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="personal-info"><i class="fas fa-user"></i> Personal Info</button>
                <button class="tab-btn" data-tab="security"><i class="fas fa-lock"></i> Security</button>
                <button class="tab-btn" data-tab="connected-accounts"><i class="fas fa-link"></i> Connected Accounts</button>
            </div>
        </div>
        
        <div class="profile-content">
            <!-- Personal Info Tab -->
            <div class="tab-content active" id="personal-info">
                <div class="section-header">
                    <h3>Personal Information</h3>
                    <p>Update your personal details</p>
                </div>
                
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <div class="file-upload-container">
                            <input type="file" id="profile_picture" name="profile_picture" class="form-control file-upload" accept="image/jpeg, image/png, image/gif">
                            <div class="file-upload-text">
                                <i class="fas fa-upload"></i> Choose a file...
                            </div>
                        </div>
                        <small class="form-text">Maximum size: 2MB. Allowed types: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
                    </div>
                </form>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="security">
                <div class="section-header">
                    <h3>Security Settings</h3>
                    <p>Manage your account password</p>
                </div>
                
                <?php if ($user_data['auth_provider'] === 'google'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You're signed in with Google. Set a password below to be able to log in directly.
                    </div>
                <?php endif; ?>
                
                <form action="profile.php" method="POST">
                    <?php if ($user_data['auth_provider'] !== 'google' || !empty($user_data['password'])): ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small class="form-text">Minimum 8 characters, include numbers and special characters for better security</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_password" class="btn primary-btn">Update Password</button>
                    </div>
                </form>
            </div>
            
            <!-- Connected Accounts Tab -->
            <div class="tab-content" id="connected-accounts">
                <div class="section-header">
                    <h3>Connected Accounts</h3>
                    <p>Manage connections to other services</p>
                </div>
                
                <div class="connected-account-item">
                    <div class="account-info">
                        <div class="account-logo google">
                            <i class="fab fa-google"></i>
                        </div>
                        <div class="account-details">
                            <h4>Google</h4>
                            <p>
                                <?php if ($user_data['auth_provider'] === 'google'): ?>
                                    Connected to <?php echo htmlspecialchars($user_data['email']); ?>
                                <?php else: ?>
                                    Not connected
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="account-actions">
                        <form action="profile.php" method="POST">
                            <?php if ($user_data['auth_provider'] === 'google'): ?>
                                <button type="submit" name="unlink_google" class="btn outline-btn">Disconnect</button>
                            <?php else: ?>
                                <button type="submit" name="link_google" class="btn outline-btn">Connect</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for profile picture upload -->
<form id="profile-picture-form" action="profile.php" method="POST" enctype="multipart/form-data" style="display: none;">
    <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/jpeg, image/png, image/gif">
    <input type="hidden" name="update_profile_picture" value="1">
</form>

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

.alert-info {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    border: 1px solid rgba(78, 115, 223, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.profile-container {
    display: flex;
    gap: 30px;
    background: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.profile-sidebar {
    width: 280px;
    border-right: 1px solid var(--border-color);
    padding: 30px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.profile-image-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin-bottom: 20px;
}

.profile-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--light-color);
}

.change-photo-overlay {
    position: absolute;
    bottom: 0;
    right: 0;
    background: var(--primary-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.change-photo-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.hidden-file-input {
    display: none;
}

.profile-info {
    text-align: center;
    margin-bottom: 30px;
}

.profile-info h2 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--dark-color);
}

.profile-info .role {
    margin: 5px 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
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

.status-badge i {
    font-size: 8px;
}

.profile-tabs {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 0 15px;
}

.tab-btn {
    text-align: left;
    padding: 12px 15px;
    background: none;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.2s;
}

.tab-btn:hover {
    background-color: var(--light-color);
}

.tab-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.profile-content {
    flex: 1;
    padding: 30px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.section-header {
    margin-bottom: 25px;
}

.section-header h3 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-header p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
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

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--secondary-color);
}

.file-upload-container {
    position: relative;
    overflow: hidden;
}

.file-upload {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    cursor: pointer;
    z-index: 2;
}

.file-upload-text {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-align: center;
    color: var(--secondary-color);
    position: relative;
    z-index: 1;
}

.form-buttons {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

.outline-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.outline-btn:hover {
    background-color: var(--light-color);
}

.connected-account-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    margin-bottom: 15px;
}

.account-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.account-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.account-logo.google {
    background-color: #DB4437;
}

.account-details h4 {
    margin: 0;
    font-size: 1rem;
}

.account-details p {
    margin: 3px 0 0;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .profile-container {
        flex-direction: column;
    }
    
    .profile-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 20px;
    }
    
    .profile-tabs {
        flex-direction: row;
        overflow-x: auto;
        padding: 10px 0;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Show corresponding content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Profile picture change via the camera icon
    const profilePicForm = document.getElementById('profile-picture-form');
    const profilePicUpload = document.getElementById('profile-picture-upload');
    const profileImagePreview = document.getElementById('profile-image-preview');
    const changePhotoBtn = document.querySelector('.change-photo-btn');
    
    // Open file picker when the camera icon is clicked
    changePhotoBtn.addEventListener('click', function() {
        profilePicUpload.click();
    });
    
    // Preview and submit when file is selected
    profilePicUpload.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            // First show a preview
            const reader = new FileReader();
            
            reader.onload = function(e) {
                profileImagePreview.src = e.target.result;
                
                // Submit the form to update the profile picture
                profilePicForm.submit();
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Handle the file upload in the main form
    const fileUpload = document.getElementById('profile_picture');
    const fileText = document.querySelector('.file-upload-text');
    
    fileUpload.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileText.innerHTML = '<i class="fas fa-file"></i> ' + this.files[0].name;
        } else {
            fileText.innerHTML = '<i class="fas fa-upload"></i> Choose a file...';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
