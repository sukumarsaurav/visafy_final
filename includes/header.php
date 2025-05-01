<?php
// Set default page title if not set
$page_title = isset($page_title) ? $page_title : "Visayfy | Canadian Immigration Consultancy";

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
    <!-- Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

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
            
            <div class="drawer-item drawer-dropdown">
                <span>Services <i class="fas fa-chevron-down"></i></span>
                <div class="drawer-submenu">
                    <a href="/services/study-permit.php" class="drawer-submenu-item">Study Permit</a>
                    <a href="/services/work-permit.php" class="drawer-submenu-item">Work Permit</a>
                    <a href="/services/express-entry.php" class="drawer-submenu-item">Express Entry</a>
                    <a href="/services/family-sponsorship.php" class="drawer-submenu-item">Family Sponsorship</a>
                    <a href="/services/business-immigration.php" class="drawer-submenu-item">Business Immigration</a>
                </div>
            </div>
            
            <div class="drawer-item drawer-dropdown">
                <span>Resources <i class="fas fa-chevron-down"></i></span>
                <div class="drawer-submenu">
                    <a href="/resources/blog.php" class="drawer-submenu-item">Blog</a>
                    <a href="/resources/faq.php" class="drawer-submenu-item">FAQ</a>
                    <a href="/resources/documents.php" class="drawer-submenu-item">Document Checklist</a>
                    <a href="/resources/calculators.php" class="drawer-submenu-item">Calculators</a>
                </div>
            </div>
            
            <a href="/eligibility-test.php" class="drawer-item">Eligibility Check</a>
            <a href="/become-member.php" class="drawer-item">Become Partner</a>
            <a href="/contact.php" class="drawer-item">Contact</a>
            
            <div class="drawer-cta">
                <?php if(isset($_SESSION['user_id'])): ?>
                <div class="drawer-profile">
                    <span class="drawer-username"><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'; ?></span>
                    <a href="/dashboard.php" class="drawer-profile-link">Dashboard</a>
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
                        <li class="nav-item dropdown">
                            <a href="/services.php">Services <i class="fas fa-chevron-down"></i></a>
                            <div class="dropdown-menu">
                                <a href="/services/study-permit.php" class="dropdown-item">Study Permit</a>
                                <a href="/services/work-permit.php" class="dropdown-item">Work Permit</a>
                                <a href="/services/express-entry.php" class="dropdown-item">Express Entry</a>
                                <a href="/services/family-sponsorship.php" class="dropdown-item">Family Sponsorship</a>
                                <a href="/services/business-immigration.php" class="dropdown-item">Business Immigration</a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a href="/resources.php">Resources <i class="fas fa-chevron-down"></i></a>
                            <div class="dropdown-menu">
                                <a href="/resources/blog.php" class="dropdown-item">Blog</a>
                                <a href="/resources/faq.php" class="dropdown-item">FAQ</a>
                                <a href="/resources/documents.php" class="dropdown-item">Document Checklist</a>
                                <a href="/resources/calculators.php" class="dropdown-item">Calculators</a>
                            </div>
                        </li>
                        <li class="nav-item"><a href="/eligibility-test.php">Eligibility Check</a></li>
                        <li class="nav-item"><a href="/become-member.php">Become Partner</a></li>
                        <li class="nav-item"><a href="/contact.php">Contact</a></li>
                    </ul>
                </nav>
                
                <!-- Inside the header-actions div -->
                <div class="header-actions">
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- User is logged in - show profile dropdown -->
                    <div class="action-buttons">
                        <div class="user-profile-dropdown">
                            <button class="profile-toggle">
                                <span class="username"><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'; ?></span>
                                <?php if(isset($_SESSION['user_profile_image']) && !empty($_SESSION['user_profile_image'])): ?>
                                    <img src="/assets/images/profiles/<?php echo $_SESSION['user_profile_image']; ?>" alt="Profile" class="profile-image">
                                <?php else: ?>
                                    <i class="fas fa-user-circle profile-placeholder"></i>
                                <?php endif; ?>
                            </button>
                            <div class="profile-dropdown-menu">
                                <a href="/dashboard.php">Dashboard</a>
                                <a href="/profile.php">Profile</a>
                                <a href="/notifications.php">Notifications</a>
                                <a href="/logout.php">Logout</a>
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
</body>
</html>