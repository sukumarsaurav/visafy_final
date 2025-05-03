<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Get user data including team member details
try {
    $query = "SELECT u.*, tm.role, tm.custom_role_name, tm.phone as tm_phone, c.country_name 
              FROM users u
              JOIN team_members tm ON u.id = tm.user_id
              LEFT JOIN countries c ON u.country_id = c.country_id 
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    // Use team member phone if user phone is empty
    if (empty($user_data['phone']) && !empty($user_data['tm_phone'])) {
        $user_data['phone'] = $user_data['tm_phone'];
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user_data = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'zipcode' => '',
        'country_id' => '',
        'country_name' => '',
        'profile_picture' => '',
        'role' => '',
        'custom_role_name' => '',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Get role display name
$role_display = ($user_data['role'] === 'Custom' && !empty($user_data['custom_role_name'])) 
                ? $user_data['custom_role_name'] 
                : $user_data['role'];

// Get countries for dropdown
try {
    $query = "SELECT country_id as id, country_name as name FROM countries WHERE is_active = TRUE ORDER BY country_name ASC";
    $result = $conn->query($query);
    $countries = [];
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching countries: " . $e->getMessage());
    $countries = [];
}

// Handle profile update
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // Validate inputs
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $zipcode = trim($_POST['zipcode']);
        $country_id = trim($_POST['country_id']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Update user table
        $query = "UPDATE users SET 
                 first_name = ?, 
                 last_name = ?, 
                 phone = ?, 
                 address = ?, 
                 city = ?, 
                 state = ?, 
                 zipcode = ?, 
                 country_id = ? 
                 WHERE id = ?";
                 
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sssssssii', 
            $first_name, 
            $last_name, 
            $phone, 
            $address, 
            $city,
            $state, 
            $zipcode, 
            $country_id, 
            $_SESSION['id']
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Update team_members table phone
        $query = "UPDATE team_members SET phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $phone, $_SESSION['id']);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $message = "Profile updated successfully!";
        $message_class = "success";
        
        // Update the session variables
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        
        // Refresh user data
        $query = "SELECT u.*, tm.role, tm.custom_role_name, tm.phone as tm_phone, c.country_name 
                FROM users u
                JOIN team_members tm ON u.id = tm.user_id
                LEFT JOIN countries c ON u.country_id = c.country_id 
                WHERE u.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        // Use team member phone if user phone is empty
        if (empty($user_data['phone']) && !empty($user_data['tm_phone'])) {
            $user_data['phone'] = $user_data['tm_phone'];
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error updating profile: " . $e->getMessage());
        $message = "An error occurred. Please try again later.";
        $message_class = "error";
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (password_verify($current_password, $user['password'])) {
            // Check if new passwords match
            if ($new_password === $confirm_password) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('si', $hashed_password, $_SESSION['id']);
                
                if ($stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_class = "success";
                } else {
                    $message = "Error updating password. Please try again.";
                    $message_class = "error";
                }
                $stmt->close();
            } else {
                $message = "New passwords do not match.";
                $message_class = "error";
            }
        } else {
            $message = "Current password is incorrect.";
            $message_class = "error";
        }
    } catch (Exception $e) {
        error_log("Error updating password: " . $e->getMessage());
        $message = "An error occurred. Please try again later.";
        $message_class = "error";
    }
}

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    try {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed)) {
                // Generate unique filename
                $new_filename = 'user_' . $_SESSION['id'] . '_' . time() . '.' . $file_ext;
                $upload_path = '../../uploads/profiles/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!file_exists('../../uploads/profiles/')) {
                    mkdir('../../uploads/profiles/', 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Update database
                    $query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('si', $new_filename, $_SESSION['id']);
                    
                    if ($stmt->execute()) {
                        $message = "Profile picture updated successfully!";
                        $message_class = "success";
                        $_SESSION['profile_picture'] = $new_filename;
                        $user_data['profile_picture'] = $new_filename;
                    } else {
                        $message = "Error updating profile picture in database.";
                        $message_class = "error";
                    }
                    $stmt->close();
                } else {
                    $message = "Error uploading file. Please try again.";
                    $message_class = "error";
                }
            } else {
                $message = "Invalid file format. Only JPG, JPEG, PNG and GIF files are allowed.";
                $message_class = "error";
            }
        } else {
            $message = "Please select a file to upload.";
            $message_class = "error";
        }
    } catch (Exception $e) {
        error_log("Error updating profile picture: " . $e->getMessage());
        $message = "An error occurred. Please try again later.";
        $message_class = "error";
    }
}
?>

<div class="content">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_class; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-container">
        <!-- Sidebar with profile picture -->
        <div class="profile-sidebar">
            <div class="profile-image-container">
                <?php if (!empty($user_data['profile_picture'])): ?>
                    <img src="../../uploads/profiles/<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Profile Picture" class="profile-image">
                <?php else: ?>
                    <div class="profile-initials">
                        <?php echo substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data" class="picture-form">
                    <label for="profile_picture" class="upload-btn">
                        <i class="fas fa-camera"></i> 
                    </label>
                    <input type="file" id="profile_picture" name="profile_picture" style="display: none;" onchange="this.form.submit()">
                    <input type="hidden" name="update_picture" value="1">
                </form>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h2>
                <p class="subtitle"><?php echo htmlspecialchars($user_data['email']); ?></p>
                <div class="role-badge"><?php echo htmlspecialchars($role_display); ?></div>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo !empty($user_data['city']) && !empty($user_data['country_name']) ? htmlspecialchars($user_data['city'] . ', ' . $user_data['country_name']) : 'Location not set'; ?></p>
                <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('F Y', strtotime($user_data['created_at'])); ?></p>
                <?php if (!empty($user_data['phone'])): ?>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user_data['phone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main profile content -->
        <div class="profile-main">
            <div class="tab-container">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="personal">Personal Information</button>
                    <button class="tab-btn" data-tab="security">Security</button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <h3>Personal Information</h3>
                    <form method="post" class="profile-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly>
                                <small>Email cannot be changed. Contact support if needed.</small>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="role">Team Role</label>
                            <input type="text" id="role" value="<?php echo htmlspecialchars($role_display); ?>" readonly>
                            <small>Your role is assigned by administrators and cannot be changed here.</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user_data['address']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="state">State/Province</label>
                                <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user_data['state']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="zipcode">ZIP/Postal Code</label>
                                <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($user_data['zipcode']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="country_id">Country</label>
                                <select id="country_id" name="country_id">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['id']; ?>" <?php echo ($user_data['country_id'] == $country['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <h3>Change Password</h3>
                    <form method="post" class="profile-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                                <small>Use a strong password with at least 8 characters including letters, numbers and special characters.</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_password" class="btn primary-btn">Update Password</button>
                        </div>
                    </form>
                    
                    <div class="password-tips">
                        <h4>Password Security Tips</h4>
                        <ul>
                            <li>Use a unique password that you don't use for other services</li>
                            <li>Include uppercase and lowercase letters, numbers, and special characters</li>
                            <li>Avoid using personal information that others might know</li>
                            <li>Change your password periodically for better security</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.content {
    padding: 20px;
}

.alert {
    padding: 12px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.profile-container {
    display: flex;
    gap: 20px;
}

.profile-sidebar {
    width: 300px;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    padding: 20px;
    text-align: center;
}

.profile-image-container {
    position: relative;
    margin-bottom: 20px;
}

.profile-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #f8f9fc;
}

.profile-initials {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background-color: #e3e6f0;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 50px;
    margin: 0 auto;
}

.upload-btn {
    position: absolute;
    bottom: 10px;
    right: 70px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.upload-btn:hover {
    background-color: #031c56;
}

.profile-info h2 {
    margin: 0 0 5px;
    color: var(--primary-color);
}

.subtitle {
    color: var(--secondary-color);
    margin: 0 0 15px;
}

.role-badge {
    display: inline-block;
    background-color: #4e73df;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    margin-bottom: 15px;
}

.profile-info p {
    margin: 8px 0;
    color: var(--secondary-color);
}

.profile-info i {
    margin-right: 5px;
    color: var(--primary-color);
}

.profile-main {
    flex: 1;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.tab-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    padding: 15px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    color: var(--secondary-color);
    border-bottom: 2px solid transparent;
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.tab-content h3 {
    margin-top: 0;
    color: var(--primary-color);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.profile-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    width: 100%;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-group input, .form-group select {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-group small {
    font-size: 12px;
    color: var(--secondary-color);
    margin-top: 5px;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.primary-btn:hover {
    background-color: #031c56;
}

.password-tips {
    margin-top: 30px;
    padding: 15px;
    background-color: #f8f9fc;
    border-radius: 4px;
    border-left: 4px solid var(--primary-color);
}

.password-tips h4 {
    margin-top: 0;
    color: var(--primary-color);
    font-size: 16px;
}

.password-tips ul {
    margin: 0;
    padding-left: 20px;
}

.password-tips li {
    margin-bottom: 8px;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .profile-container {
        flex-direction: column;
    }
    
    .profile-sidebar {
        width: 100%;
    }
    
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Update active tab button
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show active tab content
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Password strength validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (newPasswordInput && confirmPasswordInput) {
        // Check password match
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && newPasswordInput.value && this.value !== newPasswordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Check password strength
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            if (password.length < 8) {
                this.setCustomValidity('Password must be at least 8 characters long');
            } else if (!/[A-Z]/.test(password)) {
                this.setCustomValidity('Password must contain at least one uppercase letter');
            } else if (!/[a-z]/.test(password)) {
                this.setCustomValidity('Password must contain at least one lowercase letter');
            } else if (!/[0-9]/.test(password)) {
                this.setCustomValidity('Password must contain at least one number');
            } else if (!/[^A-Za-z0-9]/.test(password)) {
                this.setCustomValidity('Password must contain at least one special character');
            } else {
                this.setCustomValidity('');
            }
            
            // Recheck confirmation match
            if (confirmPasswordInput.value && confirmPasswordInput.value !== password) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
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
