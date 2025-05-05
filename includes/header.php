<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default page title if not set
$page_title = isset($page_title) ? $page_title : "Visayfy | Canadian Immigration Consultancy";

// Check if user is logged in
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Prepare profile image if user is logged in
$profile_img = '/assets/images/default-profile.svg';

if ($is_logged_in) {
    // Check for profile image
    $profile_image = !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '';

    if (!empty($profile_image)) {
        // Check if file exists in either location
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/' . $profile_image)) {
            $profile_img = '/uploads/profiles/' . $profile_image;
        } else if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/' . $profile_image)) {
            $profile_img = '/uploads/profile/' . $profile_image;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Visafy Immigration Consultancy'; ?></title>
    <meta name="description" content="Expert Canadian immigration consultancy services for study permits, work permits, express entry, and more.">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base; ?>/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    
    <!-- Swiper CSS for Sliders -->
    <link rel="stylesheet" href="https://unpkg.com/swiper@8/swiper-bundle.min.css">
    
    <!-- AOS Animation CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Move JS libraries to the end of head to ensure they load before other scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/header.css">

    <!-- Load utils.js before other scripts -->
    <script src="/assets/js/utils.js"></script>

    <!-- Your custom scripts should come after utils.js -->
    <script src="/assets/js/main.js" defer></script>
    <script src="/assets/js/resources.js" defer></script>
    <script src="/assets/js/notifications.js" defer></script>
</head>
<body>
    <!-- Removed top navbar as requested -->

    <!-- Drawer Overlay -->
    <div class="drawer-overlay"></div>
    
    <!-- Side Drawer -->
    <div class="side-drawer">
        <div class="drawer-header">
            <a href="/" class="drawer-logo">
                <img src="/assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="mobile-logo">
            </a>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <nav class="drawer-nav">
           
            
            <a href="/about-us.php" class="drawer-item">About Us</a>
            <a href="/services.php" class="drawer-item">Services</a>
            <a href="/eligibility-test.php" class="drawer-item">Eligibility Check</a>
            <a href="/become-member.php" class="drawer-item">Become Partner</a>
            
            
            <div class="drawer-cta">
                <?php if($is_logged_in): ?>
                <div class="drawer-profile">
                    <span class="drawer-username"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                    <?php if($_SESSION["user_type"] == 'admin'): ?>
                    <a href="/dashboard/admin/index.php" class="drawer-profile-link">Dashboard</a>
                    <?php elseif($_SESSION["user_type"] == 'member'): ?>
                    <a href="/dashboard/member/index.php" class="drawer-profile-link">Dashboard</a>
                    <?php elseif($_SESSION["user_type"] == 'applicant'): ?>
                    <a href="/dashboard/applicant/index.php" class="drawer-profile-link">Dashboard</a>
                    <?php else: ?>
                    <a href="/dashboard.php" class="drawer-profile-link">Dashboard</a>
                    <?php endif; ?>
                    <a href="/profile.php" class="drawer-profile-link">Profile</a>
                    <a href="/notifications.php" class="drawer-profile-link">Notifications</a>
                    <a href="/logout.php" class="drawer-profile-link">Logout</a>
                </div>
                <?php else: ?>
                <a href="/book-service.php" class="btn btn-primary">Book Service</a>
                <div class="drawer-auth">
                    <a href="/login.php" class="drawer-auth-link">Login</a>
                    <a href="/register.php" class="drawer-auth-link">Register</a>
                </div>
                <?php endif; ?>
            </div>
        </nav>
    </div>

    <!-- Header Section -->
    <header class="header">
        <div class="container header-container">
            <!-- Logo -->
            <div class="logo">
                <a href="/">
                    <img src="/assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="desktop-logo">
                </a>
            </div>
            
            <!-- Right Side Navigation and Button -->
            <div class="header-right">
                <nav class="main-nav">
                    <ul class="nav-menu">
                        <li class="nav-item"><a href="/about-us.php">About Us</a></li>
                        <li class="nav-item"><a href="/services.php">Services</a></li>  
                        <li class="nav-item"><a href="/eligibility-test.php">Eligibility Check</a></li>
                        <li class="nav-item"><a href="/become-member.php">Become Partner</a></li>
                    </ul>
                </nav>
                
                <!-- Inside the header-actions div -->
                <div class="header-actions">
                    <?php if($is_logged_in): ?>
                    <!-- User is logged in - show profile dropdown -->
                    <div class="action-buttons">
                        <div class="user-profile-dropdown">
                            <button class="profile-toggle">
                                <span class="username"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                                <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-image" style="width: 32px; height: 32px; border-radius: 50%;">
                            </button>
                            <div class="profile-dropdown-menu">
                                <?php if($_SESSION["user_type"] == 'admin'): ?>
                                <a href="/dashboard/admin/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'member'): ?>
                                <a href="/dashboard/member/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'applicant'): ?>
                                <a href="/dashboard/applicant/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php else: ?>
                                <a href="/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php endif; ?>
                                <a href="/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="/notifications.php" class="dropdown-item">
                                    <i class="fas fa-bell"></i> Notifications
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- User is not logged in - show login/register button and book service -->
                    <div class="action-buttons">
                        <div class="consultation-btn">
                                <a href="/book-service.php" class="btn btn-primary">Book Service</a>
                        </div>
                        <div class="auth-button">
                            <a href="/login.php" class="btn btn-secondary">Login/Register</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <button class="mobile-menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
