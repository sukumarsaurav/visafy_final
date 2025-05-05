<?php
$page_title = "Become a Member | Visafy Immigration Consultancy";
include('includes/functions.php');
include('includes/header.php');

// Handle member registration form submission
$registration_success = false;
$registration_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_member'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
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
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Generate verification token
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create user with pending status
            $user_insert = "INSERT INTO users (first_name, last_name, email, password, user_type, email_verified, email_verification_token, email_verification_expires, status) 
                          VALUES (?, ?, ?, ?, 'member', 0, ?, ?, 'suspended')";
            $stmt = $conn->prepare($user_insert);
            $stmt->bind_param('ssssss', $first_name, $last_name, $email, $hashed_password, $token, $expires);
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Create team member record with Immigration Assistant role
            $role = "Immigration Assistant";
            $member_insert = "INSERT INTO team_members (user_id, phone, role, custom_role_name) VALUES (?, ?, ?, NULL)";
            $stmt = $conn->prepare($member_insert);
            $stmt->bind_param('iss', $user_id, $phone, $role);
            $stmt->execute();
            $stmt->close();
            
            // Send verification email
            $verify_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Verify your Visafy membership application";
            
            $message = "
            <html>
            <head>
                <title>Membership Verification</title>
            </head>
            <body>
                <p>Hello {$first_name} {$last_name},</p>
                <p>Thank you for applying to become a member of Visafy.</p>
                <p>Please click the link below to verify your email and activate your account:</p>
                <p><a href='{$verify_link}'>{$verify_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>Your application will be reviewed by our admin team after verification.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($email, $subject, $message, $headers);
            
            // Commit transaction
            $conn->commit();
            
            $registration_success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $registration_error = "Error registering: " . $e->getMessage();
        }
    } else {
        $registration_error = implode("<br>", $errors);
    }
}
?>

<section class="partner-hero">
    <div class="container">
        <div class="partner-hero-content">
            <h1>Join the Visafy Partner Network</h1>
            <p>Expand your services, increase your revenue, and help more clients achieve their immigration goals</p>
            <button class="btn btn-primary register-btn" id="registerBtn">Become a Partner Today</button>
        </div>
    </div>
</section>

<section class="benefits-section">
    <div class="container">
        <div class="section-header">
            <h2>Why Become a Visafy Partner?</h2>
            <p>Our partnership program is designed to help immigration professionals succeed</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="100">
                <div class="benefit-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3>Increased Revenue</h3>
                <p>Expand your service offerings and increase your earning potential with our comprehensive immigration services.</p>
            </div>
            
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="200">
                <div class="benefit-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Professional Tools</h3>
                <p>Access our suite of specialized tools designed for immigration professionals, including case management software.</p>
            </div>
            
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="300">
                <div class="benefit-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Training & Support</h3>
                <p>Receive ongoing training and expert support from our team of immigration specialists.</p>
            </div>
            
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="400">
                <div class="benefit-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>Client Referrals</h3>
                <p>Get access to our client referral network and grow your business with qualified leads.</p>
            </div>
            
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="500">
                <div class="benefit-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3>Credibility</h3>
                <p>Gain instant credibility as part of the Visafy network, a trusted name in Canadian immigration.</p>
            </div>
            
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="600">
                <div class="benefit-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Community</h3>
                <p>Join a community of like-minded professionals and benefit from shared knowledge and expertise.</p>
            </div>
        </div>
    </div>
</section>

<section class="pricing-section" id="pricing">
    <div class="container">
        <div class="section-header">
            <h2>Partnership Plans</h2>
            <p>Choose the plan that's right for your business</p>
        </div>
        
        <div class="pricing-grid">
            <div class="pricing-card starter" data-aos="fade-up" data-aos-delay="100">
                <div class="pricing-header">
                    <h3>Starter</h3>
                    <div class="price">
                        <span class="amount">$99</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="trial">
                        <span>15-day free trial</span>
                    </div>
                </div>
                <div class="pricing-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Basic case management tools</li>
                        <li><i class="fas fa-check"></i> 10 client referrals per month</li>
                        <li><i class="fas fa-check"></i> Email support</li>
                        <li><i class="fas fa-check"></i> Basic document templates</li>
                        <li><i class="fas fa-check"></i> Monthly newsletter</li>
                        <li><i class="fas fa-times"></i> Priority support</li>
                        <li><i class="fas fa-times"></i> Advanced tools</li>
                    </ul>
                </div>
                <div class="pricing-cta">
                    <button class="btn btn-secondary register-btn">Start Free Trial</button>
                </div>
            </div>
            
            <div class="pricing-card professional" data-aos="fade-up" data-aos-delay="200">
                <div class="pricing-badge">Most Popular</div>
                <div class="pricing-header">
                    <h3>Professional</h3>
                    <div class="price">
                        <span class="amount">$199</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="trial">
                        <span>15-day free trial</span>
                    </div>
                </div>
                <div class="pricing-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Advanced case management tools</li>
                        <li><i class="fas fa-check"></i> 30 client referrals per month</li>
                        <li><i class="fas fa-check"></i> Priority email support</li>
                        <li><i class="fas fa-check"></i> Full document template library</li>
                        <li><i class="fas fa-check"></i> Weekly industry updates</li>
                        <li><i class="fas fa-check"></i> Phone support</li>
                        <li><i class="fas fa-check"></i> CRM integration</li>
                    </ul>
                </div>
                <div class="pricing-cta">
                    <button class="btn btn-primary register-btn">Start Free Trial</button>
                </div>
            </div>
            
            <div class="pricing-card premium" data-aos="fade-up" data-aos-delay="300">
                <div class="pricing-header">
                    <h3>Premium</h3>
                    <div class="price">
                        <span class="amount">$349</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="trial">
                        <span>15-day free trial</span>
                    </div>
                </div>
                <div class="pricing-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Full suite of tools & resources</li>
                        <li><i class="fas fa-check"></i> Unlimited client referrals</li>
                        <li><i class="fas fa-check"></i> 24/7 priority support</li>
                        <li><i class="fas fa-check"></i> Custom document templates</li>
                        <li><i class="fas fa-check"></i> Real-time industry alerts</li>
                        <li><i class="fas fa-check"></i> Dedicated account manager</li>
                        <li><i class="fas fa-check"></i> Advanced reporting & analytics</li>
                    </ul>
                </div>
                <div class="pricing-cta">
                    <button class="btn btn-secondary register-btn">Start Free Trial</button>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="testimonials-section">
    <div class="container">
        <div class="section-header">
            <h2>What Our Partners Say</h2>
            <p>Success stories from our partner network</p>
        </div>
        
        <div class="testimonials-grid">
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-content">
                    <p>"Joining Visafy as a partner has transformed my business. The professional tools and client referrals have helped me grow my client base by 40% in just six months."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-image">
                        <img src="assets/images/testimonial-1.jpg" alt="Sarah Johnson">
                    </div>
                    <div class="author-info">
                        <h4>Sarah Johnson</h4>
                        <p>Independent Immigration Consultant</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-content">
                    <p>"The case management tools and document templates have saved me countless hours, allowing me to focus on providing better service to my clients instead of administrative tasks."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-image">
                        <img src="assets/images/testimonial-2.jpg" alt="Michael Chang">
                    </div>
                    <div class="author-info">
                        <h4>Michael Chang</h4>
                        <p>Immigration Attorney</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-content">
                    <p>"The training and support provided by Visafy has helped me stay on top of changing immigration regulations and offer more comprehensive services to my clients."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-image">
                        <img src="assets/images/testimonial-3.jpg" alt="Elena Rodriguez">
                    </div>
                    <div class="author-info">
                        <h4>Elena Rodriguez</h4>
                        <p>Career & Immigration Advisor</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="faq-section">
    <div class="container">
        <div class="section-header">
            <h2>Frequently Asked Questions</h2>
            <p>Everything you need to know about becoming a Visafy partner</p>
        </div>
        
        <div class="faq-accordion">
            <div class="faq-item" data-aos="fade-up">
                <div class="faq-question">
                    <h3>What qualifications do I need to become a partner?</h3>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="faq-answer">
                    <p>We welcome partners with various backgrounds in immigration services. While specific credentials are not required, experience in immigration consulting, legal services, or related fields is beneficial. All applications are reviewed by our admin team to ensure partners meet our quality standards.</p>
                </div>
            </div>
            
            <div class="faq-item" data-aos="fade-up">
                <div class="faq-question">
                    <h3>How does the 15-day free trial work?</h3>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="faq-answer">
                    <p>Our 15-day free trial gives you full access to all features of your selected plan with no commitment. You won't be charged until the trial period ends, and you can cancel anytime during the trial period with no obligations. Your credit card information is required to start the trial, but you won't be billed until the trial period ends.</p>
                </div>
            </div>
            
            <div class="faq-item" data-aos="fade-up">
                <div class="faq-question">
                    <h3>Can I upgrade or downgrade my plan later?</h3>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="faq-answer">
                    <p>Yes, you can change your plan at any time. If you upgrade, you'll be charged the prorated difference for the remainder of your billing cycle. If you downgrade, the changes will take effect at the start of your next billing cycle.</p>
                </div>
            </div>
            
            <div class="faq-item" data-aos="fade-up">
                <div class="faq-question">
                    <h3>How are client referrals distributed?</h3>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="faq-answer">
                    <p>Client referrals are distributed based on several factors including your expertise, location, and client needs. Our goal is to match clients with the most suitable partners to ensure high-quality service. The number of referrals you receive depends on your subscription plan.</p>
                </div>
            </div>
            
            <div class="faq-item" data-aos="fade-up">
                <div class="faq-question">
                    <h3>What support will I receive as a partner?</h3>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="faq-answer">
                    <p>All partners receive onboarding support, access to training materials, and regular updates on immigration policies. Additional support varies by plan, with higher-tier plans offering priority support, dedicated account managers, and personalized training sessions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Ready to Take Your Immigration Business to the Next Level?</h2>
            <p>Join our growing network of immigration professionals and start your 15-day free trial today.</p>
            <button class="btn btn-light register-btn">Become a Partner Now</button>
        </div>
    </div>
</section>

<!-- Registration Modal -->
<div class="modal" id="registerModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Apply to Become a Partner</h3>
                <button type="button" class="close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($registration_success): ?>
                    <div class="registration-success">
                        <i class="fas fa-check-circle"></i>
                        <h4>Application Submitted!</h4>
                        <p>Thank you for applying to become a Visafy partner. We've sent a verification email to your inbox. Please verify your email to complete the first step of your application.</p>
                        <p>Our admin team will review your application after verification.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($registration_error)): ?>
                        <div class="alert alert-danger"><?php echo $registration_error; ?></div>
                    <?php endif; ?>
                    <form action="become-member.php" method="POST" id="registerForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name*</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name*</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address*</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="password">Password*</label>
                            <input type="password" name="password" id="password" class="form-control" required minlength="8">
                            <div class="password-strength">
                                <div class="strength-bar" id="strength-bar"></div>
                            </div>
                            <div class="password-hint">Password must be at least 8 characters</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password*</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8">
                        </div>
                        <div class="form-group terms-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" required>
                                <span>I agree to the <a href="terms.php">Terms and Conditions</a> and <a href="privacy.php">Privacy Policy</a></span>
                            </label>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn cancel-btn" id="cancelBtn">Cancel</button>
                            <button type="submit" name="register_member" class="btn submit-btn">Submit Application</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --accent-color: #f39c12;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

/* Hero Section */
.partner-hero {
    background: linear-gradient(rgba(4, 33, 103, 0.9), rgba(4, 33, 103, 0.8)), url('/assets/images/partner-hero-bg.jpg');
    background-size: cover;
    background-position: center;
    color: white;
    padding: 100px 0;
    text-align: center;
}

.partner-hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.partner-hero h1 {
    font-size: 2.5rem;
    margin-bottom: 20px;
}

.partner-hero p {
    font-size: 1.2rem;
    margin-bottom: 30px;
    opacity: 0.9;
}

/* Benefits Section */
.benefits-section {
    padding: 80px 0;
    background-color: var(--light-color);
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h2 {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.section-header p {
    font-size: 1.1rem;
    color: var(--secondary-color);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.benefit-card {
    background-color: white;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.benefit-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.benefit-icon {
    width: 60px;
    height: 60px;
    background-color: rgba(4, 33, 103, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.benefit-icon i {
    font-size: 24px;
    color: var(--primary-color);
}

.benefit-card h3 {
    font-size: 1.3rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.benefit-card p {
    color: var(--secondary-color);
    line-height: 1.6;
}

/* Pricing Section */
.pricing-section {
    padding: 80px 0;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.pricing-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.pricing-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.pricing-badge {
    position: absolute;
    top: 15px;
    right: -30px;
    background-color: var(--accent-color);
    color: white;
    padding: 5px 30px;
    font-size: 0.8rem;
    font-weight: 500;
    transform: rotate(45deg);
    width: 150px;
    text-align: center;
}

.pricing-header {
    padding: 30px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.pricing-header h3 {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.price {
    margin-bottom: 5px;
}

.price .amount {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
}

.price .period {
    font-size: 1rem;
    color: var(--secondary-color);
}

.trial {
    font-size: 0.9rem;
    color: var(--success-color);
    margin-bottom: 15px;
}

.pricing-features {
    padding: 30px;
}

.pricing-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.pricing-features li {
    padding: 10px 0;
    display: flex;
    align-items: center;
    color: var(--dark-color);
}

.pricing-features i {
    margin-right: 10px;
    font-size: 0.8rem;
}

.pricing-features .fa-check {
    color: var(--success-color);
}

.pricing-features .fa-times {
    color: var(--danger-color);
}

.pricing-cta {
    padding: 0 30px 30px;
    text-align: center;
}

.pricing-card.professional {
    box-shadow: 0 8px 25px rgba(4, 33, 103, 0.15);
    border: 2px solid var(--primary-color);
    transform: scale(1.05);
}

.pricing-card.professional:hover {
    transform: scale(1.05) translateY(-10px);
}

/* Testimonials Section */
.testimonials-section {
    padding: 80px 0;
    background-color: var(--light-color);
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.testimonial-card {
    background-color: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.testimonial-content {
    padding: 30px;
    position: relative;
}

.testimonial-content:before {
    content: "\201C";
    font-size: 80px;
    position: absolute;
    top: -10px;
    left: 20px;
    color: rgba(4, 33, 103, 0.1);
    font-family: Georgia, serif;
}

.testimonial-content p {
    position: relative;
    z-index: 1;
    color: var(--dark-color);
    line-height: 1.7;
}

.testimonial-author {
    padding: 20px 30px;
    border-top: 1px solid var(--border-color);
    display: flex;
    align-items: center;
}

.author-image {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 15px;
}

.author-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.author-info h4 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.author-info p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

/* FAQ Section */
.faq-section {
    padding: 80px 0;
}

.faq-accordion {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    margin-bottom: 15px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.faq-question {
    padding: 20px;
    background-color: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.faq-question h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
}

.faq-icon {
    transition: transform 0.3s ease;
}

.faq-item.active .faq-icon {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.faq-item.active .faq-answer {
    padding: 0 20px 20px;
    max-height: 500px;
}

.faq-answer p {
    color: var(--secondary-color);
    line-height: 1.6;
    margin: 0;
}

/* CTA Section */
.cta-section {
    padding: 80px 0;
    background: linear-gradient(rgba(4, 33, 103, 0.9), rgba(4, 33, 103, 0.8)), url('/assets/images/cta-bg.jpg');
    background-size: cover;
    background-position: center;
    color: white;
    text-align: center;
}

.cta-content {
    max-width: 800px;
    margin: 0 auto;
}

.cta-section h2 {
    font-size: 2rem;
    margin-bottom: 20px;
}

.cta-section p {
    font-size: 1.1rem;
    margin-bottom: 30px;
    opacity: 0.9;
}

.btn-light {
    background-color: white;
    color: var(--primary-color);
    border: none;
    padding: 12px 30px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.btn-light:hover {
    background-color: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
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
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
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

.password-strength {
    height: 5px;
    background-color: #eee;
    margin-top: 5px;
    border-radius: 3px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s ease;
    background-color: var(--danger-color);
}

.password-hint {
    font-size: 12px;
    color: var(--secondary-color);
    margin-top: 5px;
}

.terms-group {
    margin-top: 20px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input {
    margin-top: 3px;
}

.checkbox-label span {
    font-size: 14px;
    color: var(--dark-color);
}

.checkbox-label a {
    color: var(--primary-color);
    text-decoration: none;
}

.checkbox-label a:hover {
    text-decoration: underline;
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

.registration-success {
    text-align: center;
    padding: 30px 20px;
}

.registration-success i {
    font-size: 60px;
    color: var(--success-color);
    margin-bottom: 20px;
}

.registration-success h4 {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.registration-success p {
    color: var(--secondary-color);
    margin-bottom: 10px;
    line-height: 1.6;
}

/* Responsive Design */
@media (max-width: 992px) {
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .pricing-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .testimonials-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .pricing-card.professional {
        grid-row: 1;
        grid-column: 1 / span 2;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .partner-hero {
        padding: 70px 0;
    }
    
    .partner-hero h1 {
        font-size: 2rem;
    }
    
    .section-header h2 {
        font-size: 1.8rem;
    }
    
    .pricing-grid {
        grid-template-columns: 1fr;
    }
    
    .pricing-card.professional {
        grid-column: 1;
        transform: none;
    }
    
    .pricing-card.professional:hover {
        transform: translateY(-10px);
    }
    
    .testimonials-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .benefits-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-dialog {
        margin: 20px;
        width: auto;
    }
}
</style>

<script>
    // Modal functionality
    const registerBtn = document.querySelectorAll('.register-btn');
    const registerModal = document.getElementById('registerModal');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    
    registerBtn.forEach(btn => {
        btn.addEventListener('click', function() {
            registerModal.style.display = 'block';
        });
    });
    
    function closeModalFunc() {
        registerModal.style.display = 'none';
    }
    
    closeModal.addEventListener('click', closeModalFunc);
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModalFunc);
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === registerModal) {
            closeModalFunc();
        }
    });
    
    // FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        question.addEventListener('click', function() {
            // Close all other items
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle current item
            item.classList.toggle('active');
        });
    });
    
    // Password strength meter
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strength-bar');
    
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) {
                strength += 25;
            }
            
            if (password.match(/[A-Z]/)) {
                strength += 25;
            }
            
            if (password.match(/[0-9]/)) {
                strength += 25;
            }
            
            if (password.match(/[^A-Za-z0-9]/)) {
                strength += 25;
            }
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.style.backgroundColor = '#e74a3b';
            } else if (strength <= 50) {
                strengthBar.style.backgroundColor = '#f6c23e';
            } else if (strength <= 75) {
                strengthBar.style.backgroundColor = '#4e73df';
            } else {
                strengthBar.style.backgroundColor = '#1cc88a';
            }
        });
    }
    
    // Password confirmation validation
    const confirmPassword = document.getElementById('confirm_password');
    
    if (passwordInput && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Initialize AOS animations if exists
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    }
</script>

<?php include('includes/footer.php'); ?>