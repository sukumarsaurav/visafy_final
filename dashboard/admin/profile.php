<?php
$page_title = "My Profile";
$page_specific_js = "assets/js/profile.js";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Load user data
$user_id = $_SESSION["id"];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, user_type, email_verified, profile_picture, 
                       created_at, updated_at, status, auth_provider FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Format profile picture URL
$profile_img = '../assets/images/default-profile.jpg'; // Default image
if (!empty($user['profile_picture'])) {
    $profile_path = '../../uploads/profiles/' . $user['profile_picture'];
    if (file_exists($profile_path)) {
        $profile_img = $profile_path;
    }
}

$success_message = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
$error_message = isset($_SESSION['error_msg']) ? $_SESSION['error_msg'] : '';

// Clear session messages
if (isset($_SESSION['success_msg'])) unset($_SESSION['success_msg']);
if (isset($_SESSION['error_msg'])) unset($_SESSION['error_msg']);
?>

<div class="profile-container">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-header">
        <div class="profile-image-container">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo $profile_img; ?>" alt="Profile Image" class="profile-image" id="profile-image">
            <?php else: ?>
                <div class="profile-image-placeholder">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="image-overlay" id="image-overlay">
                <i class="fas fa-camera"></i>
                <span>Change Photo</span>
            </div>
        </div>
        <div class="profile-info">
            <h1 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p class="profile-entity-type"><?php echo htmlspecialchars($user['user_type']); ?></p>
            <div class="verification-status <?php echo $user['status']; ?>">
                <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
            </div>
        </div>
    </div>
    
    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="general">Personal Information</button>
        <button class="tab-btn" data-tab="security">Security Settings</button>
        <button class="tab-btn" data-tab="notifications">Notification Preferences</button>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data" class="profile-form" id="profile-form">
        <div class="tab-content active" id="general-tab">
            <input type="file" id="photo-upload" name="profile_picture" accept="image/*" class="hidden-input">
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="first-name">First Name</label>
                    <input type="text" id="first-name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="last-name">Last Name</label>
                    <input type="text" id="last-name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                <?php if (!$user['email_verified']): ?>
                    <small>Your email is not verified. <a href="#" id="resend-verification">Resend verification email</a></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="joined">Joined Date</label>
                    <input type="text" id="joined" value="<?php echo date('F Y', strtotime($user['created_at'])); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="last-updated">Last Updated</label>
                    <input type="text" id="last-updated" value="<?php echo date('M d, Y', strtotime($user['updated_at'])); ?>" disabled>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" id="edit-profile-btn" class="edit-btn">
                    <i class="fas fa-edit icon-left"></i> Edit Profile
                </button>
                <button type="submit" id="save-profile-btn" class="save-btn hidden">
                    <i class="fas fa-save icon-left"></i> Save Changes
                </button>
                <button type="button" id="cancel-edit-btn" class="cancel-btn hidden">
                    Cancel
                </button>
            </div>
        </div>
        
        <div class="tab-content" id="security-tab">
            <div class="form-group">
                <label for="current-password">Current Password</label>
                <input type="password" id="current-password" name="current_password">
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new_password">
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm New Password</label>
                    <input type="password" id="confirm-password" name="confirm_password">
                </div>
            </div>
            
            <div class="password-strength" id="password-strength">
                <div class="strength-bar">
                    <div class="strength-indicator" id="strength-indicator"></div>
                </div>
                <div class="strength-text" id="strength-text">Password strength</div>
            </div>
            
            <div class="security-note">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <div class="content">
                    <h3>Password Security Tips</h3>
                    <ul>
                        <li>Use at least 8 characters, including uppercase, lowercase, numbers, and special characters</li>
                        <li>Don't reuse passwords from other websites</li>
                        <li>Update your password regularly</li>
                        <li>Never share your password with others</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="save-btn" formaction="update_password.php">
                    <i class="fas fa-lock icon-left"></i> Change Password
                </button>
            </div>
        </div>
        
        <div class="tab-content" id="notifications-tab">
            <?php
            // Fetch notification preferences
            $stmt = $conn->prepare("SELECT notification_type, email_enabled, push_enabled, in_app_enabled 
                               FROM notification_preferences WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = [];
            
            while ($row = $result->fetch_assoc()) {
                $notifications[$row['notification_type']] = $row;
            }
            
            // Define notification types and friendly names
            $notification_types = [
                'application_status_change' => 'Application Status Updates',
                'document_requested' => 'Document Requests',
                'booking_created' => 'New Bookings',
                'booking_rescheduled' => 'Booking Reschedules',
                'task_assigned' => 'Task Assignments',
                'message_received' => 'New Messages',
                'system_alert' => 'System Alerts'
            ];
            
            foreach ($notification_types as $type => $name):
                $prefs = isset($notifications[$type]) ? $notifications[$type] : 
                    ['email_enabled' => 1, 'push_enabled' => 1, 'in_app_enabled' => 1];
            ?>
            <div class="notification-preference">
                <div class="preference-name"><?php echo $name; ?></div>
                <div class="preference-options">
                    <div class="toggle-option">
                        <input class="toggle-checkbox" type="checkbox" id="email-<?php echo $type; ?>" 
                            name="notifications[<?php echo $type; ?>][email]" <?php echo $prefs['email_enabled'] ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="email-<?php echo $type; ?>">Email</label>
                    </div>
                    <div class="toggle-option">
                        <input class="toggle-checkbox" type="checkbox" id="push-<?php echo $type; ?>" 
                            name="notifications[<?php echo $type; ?>][push]" <?php echo $prefs['push_enabled'] ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="push-<?php echo $type; ?>">Push</label>
                    </div>
                    <div class="toggle-option">
                        <input class="toggle-checkbox" type="checkbox" id="inapp-<?php echo $type; ?>" 
                            name="notifications[<?php echo $type; ?>][inapp]" <?php echo $prefs['in_app_enabled'] ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="inapp-<?php echo $type; ?>">In-App</label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="form-actions">
                <button type="submit" class="save-btn" formaction="update_notifications.php">
                    <i class="fas fa-save icon-left"></i> Save Preferences
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Crop Image Modal -->
<div class="modal" id="crop-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Crop Profile Image</h5>
            <button type="button" class="close-button">&times;</button>
        </div>
        <div class="modal-body">
            <div class="img-container">
                <img id="crop-image" src="" alt="Image to crop">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-outline modal-cancel-btn">Cancel</button>
            <button type="button" class="button" id="crop-btn">Crop & Save</button>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
