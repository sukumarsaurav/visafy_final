<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Settings";
$page_specific_css = "assets/css/settings.css";
require_once 'includes/header.php';

// Get user settings
try {
    $query = "SELECT * FROM user_settings WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $settings = $result->fetch_assoc();
    } else {
        // Create default settings if none exist
        $query = "INSERT INTO user_settings (user_id, email_notifications, sms_notifications, language, timezone, theme) 
                 VALUES (?, 1, 0, 'en', 'UTC', 'light')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        $settings = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'language' => 'en',
            'timezone' => 'UTC',
            'theme' => 'light'
        ];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching user settings: " . $e->getMessage());
    $settings = [
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'language' => 'en',
        'timezone' => 'UTC',
        'theme' => 'light'
    ];
}

// Handle settings update
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $language = $_POST['language'];
        $timezone = $_POST['timezone'];
        $theme = $_POST['theme'];
        
        $query = "UPDATE user_settings SET 
                 email_notifications = ?, 
                 sms_notifications = ?, 
                 language = ?, 
                 timezone = ?, 
                 theme = ? 
                 WHERE user_id = ?";
                 
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iisssi', 
            $email_notifications, 
            $sms_notifications, 
            $language, 
            $timezone, 
            $theme, 
            $user_id
        );
        
        if ($stmt->execute()) {
            $message = "Settings updated successfully!";
            $message_class = "success";
            
            // Update settings in variable
            $settings['email_notifications'] = $email_notifications;
            $settings['sms_notifications'] = $sms_notifications;
            $settings['language'] = $language;
            $settings['timezone'] = $timezone;
            $settings['theme'] = $theme;
            
            // Update theme in session
            $_SESSION['theme'] = $theme;
        } else {
            $message = "Error updating settings. Please try again.";
            $message_class = "error";
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error updating settings: " . $e->getMessage());
        $message = "An error occurred. Please try again later.";
        $message_class = "error";
    }
}

// Get available languages
$languages = [
    'en' => 'English',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German',
    'zh' => 'Chinese',
    'ja' => 'Japanese',
    'ar' => 'Arabic',
    'ru' => 'Russian',
    'hi' => 'Hindi'
];

// Get available timezones
$timezones = [
    'UTC' => 'UTC (Coordinated Universal Time)',
    'America/New_York' => 'Eastern Time (US & Canada)',
    'America/Chicago' => 'Central Time (US & Canada)',
    'America/Denver' => 'Mountain Time (US & Canada)',
    'America/Los_Angeles' => 'Pacific Time (US & Canada)',
    'Europe/London' => 'London, Edinburgh',
    'Europe/Paris' => 'Paris, Berlin, Rome, Madrid',
    'Asia/Tokyo' => 'Tokyo, Seoul',
    'Asia/Shanghai' => 'Beijing, Hong Kong, Singapore',
    'Australia/Sydney' => 'Sydney, Melbourne',
    'Asia/Kolkata' => 'Mumbai, New Delhi',
    'Asia/Dubai' => 'Dubai, Abu Dhabi'
];
?>

<div class="content">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_class; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="settings-container">
        <div class="settings-header">
            <h1>Account Settings</h1>
            <p>Manage your preferences and account settings</p>
        </div>
        
        <div class="settings-content">
            <form method="post" class="settings-form">
                <!-- Notification Settings -->
                <div class="settings-section">
                    <h2>Notification Preferences</h2>
                    <p class="section-desc">Control how and when you receive notifications</p>
                    
                    <div class="settings-options">
                        <div class="toggle-option">
                            <div class="toggle-info">
                                <h3>Email Notifications</h3>
                                <p>Receive status updates, reminders, and important notices via email</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-option">
                            <div class="toggle-info">
                                <h3>SMS Notifications</h3>
                                <p>Receive text message alerts for time-sensitive updates and reminders</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_notifications" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Regional Settings -->
                <div class="settings-section">
                    <h2>Regional Settings</h2>
                    <p class="section-desc">Customize language and time display preferences</p>
                    
                    <div class="settings-options">
                        <div class="select-option">
                            <div class="option-info">
                                <h3>Language</h3>
                                <p>Select your preferred language for the dashboard</p>
                            </div>
                            <div class="option-control">
                                <select name="language">
                                    <?php foreach ($languages as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($settings['language'] == $code) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="select-option">
                            <div class="option-info">
                                <h3>Time Zone</h3>
                                <p>Set your local time zone for accurate scheduling and reminders</p>
                            </div>
                            <div class="option-control">
                                <select name="timezone">
                                    <?php foreach ($timezones as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($settings['timezone'] == $code) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Appearance Settings -->
                <div class="settings-section">
                    <h2>Appearance</h2>
                    <p class="section-desc">Customize the look and feel of your dashboard</p>
                    
                    <div class="settings-options">
                        <div class="theme-options">
                            <h3>Theme</h3>
                            <div class="theme-selector">
                                <label class="theme-card <?php echo ($settings['theme'] == 'light') ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme" value="light" <?php echo ($settings['theme'] == 'light') ? 'checked' : ''; ?>>
                                    <div class="theme-preview light-theme">
                                        <div class="theme-preview-header"></div>
                                        <div class="theme-preview-sidebar"></div>
                                        <div class="theme-preview-content"></div>
                                    </div>
                                    <span>Light</span>
                                </label>
                                
                                <label class="theme-card <?php echo ($settings['theme'] == 'dark') ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme" value="dark" <?php echo ($settings['theme'] == 'dark') ? 'checked' : ''; ?>>
                                    <div class="theme-preview dark-theme">
                                        <div class="theme-preview-header"></div>
                                        <div class="theme-preview-sidebar"></div>
                                        <div class="theme-preview-content"></div>
                                    </div>
                                    <span>Dark</span>
                                </label>
                                
                                <label class="theme-card <?php echo ($settings['theme'] == 'blue') ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme" value="blue" <?php echo ($settings['theme'] == 'blue') ? 'checked' : ''; ?>>
                                    <div class="theme-preview blue-theme">
                                        <div class="theme-preview-header"></div>
                                        <div class="theme-preview-sidebar"></div>
                                        <div class="theme-preview-content"></div>
                                    </div>
                                    <span>Blue</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security and Privacy -->
                <div class="settings-section">
                    <h2>Security and Privacy</h2>
                    <p class="section-desc">Manage your account security settings</p>
                    
                    <div class="settings-options">
                        <div class="link-option">
                            <div class="option-info">
                                <h3>Change Password</h3>
                                <p>Update your account password</p>
                            </div>
                            <div class="option-control">
                                <a href="profile.php?tab=security" class="btn secondary-btn">Change Password</a>
                            </div>
                        </div>
                        
                        <div class="link-option">
                            <div class="option-info">
                                <h3>Privacy Settings</h3>
                                <p>Manage how your information is used</p>
                            </div>
                            <div class="option-control">
                                <a href="privacy.php" class="btn secondary-btn">View Privacy Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_settings" class="btn primary-btn">Save Changes</button>
                </div>
            </form>
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

.settings-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.settings-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.settings-header h1 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.settings-header p {
    margin: 0;
    color: var(--secondary-color);
}

.settings-content {
    padding: 20px;
}

.settings-section {
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.settings-section:last-child {
    margin-bottom: 20px;
    border-bottom: none;
    padding-bottom: 0;
}

.settings-section h2 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-desc {
    margin: 0 0 20px;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.settings-options {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.toggle-option, .select-option, .link-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toggle-info, .option-info {
    max-width: 70%;
}

.toggle-info h3, .option-info h3 {
    margin: 0 0 5px;
    font-size: 1rem;
    color: var(--dark-color);
}

.toggle-info p, .option-info p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary-color);
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.option-control select {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    width: 250px;
    font-size: 14px;
}

.theme-options {
    width: 100%;
}

.theme-options h3 {
    margin: 0 0 15px;
    font-size: 1rem;
    color: var(--dark-color);
}

.theme-selector {
    display: flex;
    gap: 20px;
}

.theme-card {
    cursor: pointer;
    border: 2px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
    transition: all 0.3s;
    padding-bottom: 10px;
    width: 120px;
    text-align: center;
}

.theme-card.selected {
    border-color: var(--primary-color);
}

.theme-card input {
    display: none;
}

.theme-preview {
    height: 80px;
    position: relative;
    margin-bottom: 10px;
}

.theme-preview-header {
    height: 15px;
    width: 100%;
}

.theme-preview-sidebar {
    position: absolute;
    left: 0;
    top: 15px;
    bottom: 0;
    width: 25%;
}

.theme-preview-content {
    position: absolute;
    left: 25%;
    top: 15px;
    bottom: 0;
    right: 0;
}

.light-theme .theme-preview-header {
    background-color: #f8f9fc;
}

.light-theme .theme-preview-sidebar {
    background-color: #4e73df;
}

.light-theme .theme-preview-content {
    background-color: #fff;
}

.dark-theme .theme-preview-header {
    background-color: #222;
}

.dark-theme .theme-preview-sidebar {
    background-color: #2c3e50;
}

.dark-theme .theme-preview-content {
    background-color: #333;
}

.blue-theme .theme-preview-header {
    background-color: #1a237e;
}

.blue-theme .theme-preview-sidebar {
    background-color: #283593;
}

.blue-theme .theme-preview-content {
    background-color: #f0f8ff;
}

.theme-card span {
    display: block;
    margin-top: 5px;
    font-size: 0.9rem;
    color: var(--dark-color);
}

.btn {
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-size: 14px;
    border: none;
    cursor: pointer;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover {
    background-color: #031c56;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.secondary-btn:hover {
    background-color: #f8f9fc;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .toggle-option, .select-option, .link-option {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .toggle-info, .option-info {
        max-width: 100%;
    }
    
    .option-control select {
        width: 100%;
    }
    
    .theme-selector {
        flex-wrap: wrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme selection handling
    const themeCards = document.querySelectorAll('.theme-card');
    const themeInputs = document.querySelectorAll('.theme-card input');
    
    themeInputs.forEach(input => {
        input.addEventListener('change', function() {
            themeCards.forEach(card => card.classList.remove('selected'));
            if (this.checked) {
                this.closest('.theme-card').classList.add('selected');
            }
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 