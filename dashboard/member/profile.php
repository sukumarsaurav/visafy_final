<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Get user data including team member details
try {
    $query = "SELECT u.*, tm.role, tm.custom_role_name, tm.phone as tm_phone, c.country_name, 
              tm.id as team_member_id
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
    
    // Check if user is a consultant (Immigration Assistant)
    $is_consultant = ($user_data['role'] === 'Immigration Assistant');
    
    // Get consultant profile data if applicable
    if ($is_consultant) {
        $query = "SELECT * FROM consultant_profiles WHERE team_member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_data['team_member_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $consultant_data = $result->fetch_assoc();
        } else {
            // Initialize empty consultant data
            $consultant_data = [
                'license_number' => '',
                'license_expiry' => '',
                'license_type' => '',
                'years_of_experience' => '',
                'bio' => '',
                'education' => '',
                'specialty_areas' => ''
            ];
        }
        $stmt->close();
        
        // Get consultant languages
        $query = "SELECT language, proficiency_level FROM consultant_languages 
                  WHERE consultant_profile_id = (SELECT id FROM consultant_profiles WHERE team_member_id = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_data['team_member_id']);
        $stmt->execute();
        $lang_result = $stmt->get_result();
        
        $languages = [];
        while ($lang = $lang_result->fetch_assoc()) {
            $languages[] = $lang;
        }
        $stmt->close();
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
    $is_consultant = false;
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
        
        // If user is consultant, update consultant profile
        if ($is_consultant) {
            $license_number = trim($_POST['license_number']);
            $license_type = trim($_POST['license_type']);
            $license_expiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;
            $years_experience = !empty($_POST['years_experience']) ? intval($_POST['years_experience']) : null;
            $bio = trim($_POST['bio']);
            $education = trim($_POST['education']);
            $specialty_areas = isset($_POST['specialty_areas']) ? json_encode($_POST['specialty_areas']) : null;
            
            // Check if profile exists and update or insert
            $check_query = "SELECT id FROM consultant_profiles WHERE team_member_id = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('i', $user_data['team_member_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing profile
                $profile_id = $result->fetch_assoc()['id'];
                $query = "UPDATE consultant_profiles SET 
                          license_number = ?, 
                          license_type = ?, 
                          license_expiry = ?, 
                          years_of_experience = ?, 
                          bio = ?, 
                          education = ?, 
                          specialty_areas = ? 
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('sssisssi', 
                    $license_number, 
                    $license_type, 
                    $license_expiry, 
                    $years_experience, 
                    $bio, 
                    $education, 
                    $specialty_areas,
                    $profile_id
                );
            } else {
                // Insert new profile
                $query = "INSERT INTO consultant_profiles 
                          (team_member_id, license_number, license_type, license_expiry, 
                           years_of_experience, bio, education, specialty_areas) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('isssssss', 
                    $user_data['team_member_id'], 
                    $license_number, 
                    $license_type, 
                    $license_expiry, 
                    $years_experience, 
                    $bio, 
                    $education, 
                    $specialty_areas
                );
            }
            $stmt->execute();
            $stmt->close();
            
            // Handle languages
            if (isset($_POST['languages']) && is_array($_POST['languages'])) {
                // Get consultant profile ID
                $query = "SELECT id FROM consultant_profiles WHERE team_member_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $user_data['team_member_id']);
                $stmt->execute();
                $profile_id = $stmt->get_result()->fetch_assoc()['id'];
                $stmt->close();
                
                // Delete existing languages
                $query = "DELETE FROM consultant_languages WHERE consultant_profile_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $profile_id);
                $stmt->execute();
                $stmt->close();
                
                // Add new languages
                foreach ($_POST['languages'] as $idx => $language) {
                    if (!empty($language) && isset($_POST['proficiency'][$idx])) {
                        $query = "INSERT INTO consultant_languages 
                                 (consultant_profile_id, language, proficiency_level) 
                                 VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('iss', 
                            $profile_id, 
                            $language, 
                            $_POST['proficiency'][$idx]
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $message = "Profile updated successfully!";
        $message_class = "success";
        
        // Update the session variables
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        
        // Refresh user data
        $query = "SELECT u.*, tm.role, tm.custom_role_name, tm.phone as tm_phone, c.country_name, 
                  tm.id as team_member_id
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
        
        // Refresh consultant data if applicable
        if ($is_consultant) {
            $query = "SELECT * FROM consultant_profiles WHERE team_member_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_data['team_member_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $consultant_data = $result->fetch_assoc();
            }
            $stmt->close();
            
            // Refresh languages
            $query = "SELECT language, proficiency_level FROM consultant_languages 
                      WHERE consultant_profile_id = (SELECT id FROM consultant_profiles WHERE team_member_id = ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_data['team_member_id']);
            $stmt->execute();
            $lang_result = $stmt->get_result();
            
            $languages = [];
            while ($lang = $lang_result->fetch_assoc()) {
                $languages[] = $lang;
            }
            $stmt->close();
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
                    <?php if ($is_consultant): ?>
                    <button class="tab-btn" data-tab="professional">Professional Details</button>
                    <?php endif; ?>
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
                        
                        <?php if ($is_consultant): ?>
                        <!-- Hidden consultant fields for this form submission -->
                        <input type="hidden" name="license_number" value="<?php echo htmlspecialchars($consultant_data['license_number'] ?? ''); ?>">
                        <input type="hidden" name="license_type" value="<?php echo htmlspecialchars($consultant_data['license_type'] ?? ''); ?>">
                        <input type="hidden" name="license_expiry" value="<?php echo htmlspecialchars($consultant_data['license_expiry'] ?? ''); ?>">
                        <input type="hidden" name="years_experience" value="<?php echo htmlspecialchars($consultant_data['years_of_experience'] ?? ''); ?>">
                        <input type="hidden" name="bio" value="<?php echo htmlspecialchars($consultant_data['bio'] ?? ''); ?>">
                        <input type="hidden" name="education" value="<?php echo htmlspecialchars($consultant_data['education'] ?? ''); ?>">
                        <?php if (isset($consultant_data['specialty_areas']) && !empty($consultant_data['specialty_areas'])): 
                            $areas = json_decode($consultant_data['specialty_areas'], true);
                            if (is_array($areas)):
                                foreach ($areas as $area): ?>
                                <input type="hidden" name="specialty_areas[]" value="<?php echo htmlspecialchars($area); ?>">
                                <?php endforeach; 
                            endif;
                        endif; ?>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <?php if ($is_consultant): ?>
                <!-- Professional Details Tab for Immigration Consultants -->
                <div id="professional" class="tab-content">
                    <h3>Professional Information</h3>
                    <form method="post" class="profile-form">
                        <!-- Preserve personal information in hidden fields -->
                        <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>">
                        <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>">
                        <input type="hidden" name="address" value="<?php echo htmlspecialchars($user_data['address']); ?>">
                        <input type="hidden" name="city" value="<?php echo htmlspecialchars($user_data['city']); ?>">
                        <input type="hidden" name="state" value="<?php echo htmlspecialchars($user_data['state']); ?>">
                        <input type="hidden" name="zipcode" value="<?php echo htmlspecialchars($user_data['zipcode']); ?>">
                        <input type="hidden" name="country_id" value="<?php echo htmlspecialchars($user_data['country_id']); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($consultant_data['license_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="license_type">License Type</label>
                                <input type="text" id="license_type" name="license_type" value="<?php echo htmlspecialchars($consultant_data['license_type'] ?? ''); ?>" placeholder="e.g., ICCRC, CICC">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license_expiry">License Expiry Date</label>
                                <input type="date" id="license_expiry" name="license_expiry" value="<?php echo htmlspecialchars($consultant_data['license_expiry'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="years_experience">Years of Experience</label>
                                <input type="number" id="years_experience" name="years_experience" value="<?php echo htmlspecialchars($consultant_data['years_of_experience'] ?? ''); ?>" min="0" max="50">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="bio">Professional Bio</label>
                            <textarea id="bio" name="bio" rows="4" class="form-control"><?php echo htmlspecialchars($consultant_data['bio'] ?? ''); ?></textarea>
                            <small>Brief description of your experience and expertise in immigration services.</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="education">Education</label>
                            <textarea id="education" name="education" rows="3" class="form-control"><?php echo htmlspecialchars($consultant_data['education'] ?? ''); ?></textarea>
                            <small>e.g., Bachelor in Law, Immigration Consultant Diploma</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Specialty Areas</label>
                            <div class="specialty-areas-container">
                                <div class="checkbox-group">
                                    <?php 
                                    $specialty_areas = [];
                                    if (isset($consultant_data['specialty_areas']) && !empty($consultant_data['specialty_areas'])) {
                                        $specialty_areas = json_decode($consultant_data['specialty_areas'], true) ?? [];
                                    }
                                    
                                    $available_specialties = [
                                        "Study Permits", "Work Permits", "Express Entry", "Business Immigration", 
                                        "Family Sponsorship", "Refugee Claims", "Provincial Nominee Programs",
                                        "Citizenship Applications", "Permanent Residency", "Temporary Residency"
                                    ];
                                    
                                    foreach ($available_specialties as $specialty): 
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialty_areas[]" value="<?php echo htmlspecialchars($specialty); ?>" 
                                            <?php echo (is_array($specialty_areas) && in_array($specialty, $specialty_areas)) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($specialty); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Languages</label>
                            <div id="languages-container">
                                <?php if (!empty($languages)): 
                                    foreach ($languages as $index => $lang): ?>
                                <div class="language-row">
                                    <div class="form-row">
                                        <div class="form-group language-input">
                                            <input type="text" name="languages[]" class="form-control" value="<?php echo htmlspecialchars($lang['language']); ?>" placeholder="Language">
                                        </div>
                                        <div class="form-group proficiency-select">
                                            <select name="proficiency[]" class="form-control">
                                                <option value="basic" <?php echo ($lang['proficiency_level'] == 'basic') ? 'selected' : ''; ?>>Basic</option>
                                                <option value="intermediate" <?php echo ($lang['proficiency_level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                                <option value="fluent" <?php echo ($lang['proficiency_level'] == 'fluent') ? 'selected' : ''; ?>>Fluent</option>
                                                <option value="native" <?php echo ($lang['proficiency_level'] == 'native') ? 'selected' : ''; ?>>Native</option>
                                            </select>
                                        </div>
                                        <div class="form-group language-actions">
                                            <?php if ($index === 0): ?>
                                            <button type="button" class="btn btn-sm add-language-btn"><i class="fas fa-plus"></i></button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm remove-language-btn"><i class="fas fa-times"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach;
                                else: ?>
                                <div class="language-row">
                                    <div class="form-row">
                                        <div class="form-group language-input">
                                            <input type="text" name="languages[]" class="form-control" placeholder="Language">
                                        </div>
                                        <div class="form-group proficiency-select">
                                            <select name="proficiency[]" class="form-control">
                                                <option value="basic">Basic</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="fluent" selected>Fluent</option>
                                                <option value="native">Native</option>
                                            </select>
                                        </div>
                                        <div class="form-group language-actions">
                                            <button type="button" class="btn btn-sm add-language-btn"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn primary-btn">Save Professional Profile</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
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

.form-group input, .form-group select, .form-group textarea {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
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

/* Specialty areas container */
.specialty-areas-container {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    background-color: #f9f9f9;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

/* Languages section */
.language-row {
    margin-bottom: 10px;
}

.form-row .language-input {
    flex: 2;
}

.form-row .proficiency-select {
    flex: 1;
}

.form-row .language-actions {
    flex: 0 0 40px;
    display: flex;
    align-items: center;
}

.add-language-btn,
.remove-language-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
}

.add-language-btn {
    background-color: var(--success-color, #1cc88a);
    color: white;
}

.remove-language-btn {
    background-color: var(--danger-color, #e74a3b);
    color: white;
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
    
    .checkbox-group {
        grid-template-columns: 1fr;
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
    
    // Add/remove language fields
    const languagesContainer = document.getElementById('languages-container');
    
    if (languagesContainer) {
        // Add language field
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-language-btn') || e.target.parentElement.classList.contains('add-language-btn')) {
                const btn = e.target.closest('.add-language-btn');
                const languageRow = btn.closest('.language-row');
                
                // Create new language row
                const newRow = document.createElement('div');
                newRow.className = 'language-row';
                newRow.innerHTML = `
                    <div class="form-row">
                        <div class="form-group language-input">
                            <input type="text" name="languages[]" class="form-control" placeholder="Language">
                        </div>
                        <div class="form-group proficiency-select">
                            <select name="proficiency[]" class="form-control">
                                <option value="basic">Basic</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="fluent" selected>Fluent</option>
                                <option value="native">Native</option>
                            </select>
                        </div>
                        <div class="form-group language-actions">
                            <button type="button" class="btn btn-sm remove-language-btn"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                `;
                
                // Insert after current row
                languageRow.parentNode.insertBefore(newRow, languageRow.nextSibling);
            }
            
            // Remove language field
            if (e.target.classList.contains('remove-language-btn') || e.target.parentElement.classList.contains('remove-language-btn')) {
                const btn = e.target.closest('.remove-language-btn');
                const languageRow = btn.closest('.language-row');
                languageRow.remove();
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

