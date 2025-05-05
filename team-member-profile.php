<?php
// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Check if team member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: book-service.php");
    exit();
}

$team_member_id = intval($_GET['id']);

// Fetch team member details
$stmt = $conn->prepare("SELECT tm.*, u.first_name, u.last_name, u.email, u.profile_picture 
                      FROM team_members tm 
                      JOIN users u ON tm.user_id = u.id 
                      WHERE tm.id = ? 
                      AND tm.role = 'Immigration Assistant'
                      AND u.status = 'active' 
                      AND tm.deleted_at IS NULL 
                      AND u.deleted_at IS NULL");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

// If team member not found, redirect to service page
if ($result->num_rows === 0) {
    header("Location: book-service.php");
    exit();
}

$team_member = $result->fetch_assoc();
$stmt->close();

// Check if user is logged in
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Get services offered by this team member
$stmt = $conn->prepare("SELECT DISTINCT vs.visa_service_id, vs.base_price, st.service_name, v.visa_type, c.country_name
                      FROM team_members tm 
                      JOIN visa_services vs 
                      JOIN service_types st ON vs.service_type_id = st.service_type_id
                      JOIN visas v ON vs.visa_id = v.visa_id
                      JOIN countries c ON v.country_id = c.country_id
                      WHERE tm.id = ? AND vs.is_active = 1 AND v.is_active = 1
                      ORDER BY st.service_name");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];

while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}
$stmt->close();

// Get ratings and reviews for this team member
$stmt = $conn->prepare("SELECT bf.rating, bf.feedback, bf.is_anonymous, bf.created_at,
                        CASE WHEN bf.is_anonymous = 1 THEN 'Anonymous' ELSE CONCAT(u.first_name, ' ', u.last_name) END as client_name
                        FROM booking_feedback bf
                        JOIN bookings b ON bf.booking_id = b.id
                        JOIN users u ON bf.user_id = u.id
                        WHERE b.team_member_id = ? AND bf.is_public = 1
                        ORDER BY bf.created_at DESC
                        LIMIT 10");
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = [];

while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

// Calculate average rating
$avg_rating = 0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $rating_sum = 0;
    foreach ($reviews as $review) {
        $rating_sum += $review['rating'];
    }
    $avg_rating = round($rating_sum / $total_reviews, 1);
}

// Get profile image
$profile_img = '/assets/images/default-profile.svg';
if (!empty($team_member['profile_picture'])) {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/' . $team_member['profile_picture'])) {
        $profile_img = '/uploads/profiles/' . $team_member['profile_picture'];
    } else if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/' . $team_member['profile_picture'])) {
        $profile_img = '/uploads/profile/' . $team_member['profile_picture'];
    }
}

// Set page title
$page_title = htmlspecialchars($team_member['first_name'] . ' ' . $team_member['last_name']) . " | Immigration Assistant";
include 'includes/header.php';
?>

<div class="profile-container">
    <div class="header-container">
        <div>
            <h1>Immigration Assistant Profile</h1>
            <p>Learn more about our team member and the services they offer</p>
        </div>
        <div class="action-buttons">
            <a href="book-service.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Team Members
            </a>
        </div>
    </div>

    <div class="profile-content">
        <div class="profile-main">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-image">
                        <img src="<?php echo $profile_img; ?>" alt="<?php echo htmlspecialchars($team_member['first_name'] . ' ' . $team_member['last_name']); ?>">
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($team_member['first_name'] . ' ' . $team_member['last_name']); ?></h2>
                        <span class="profile-role">Immigration Assistant</span>
                        
                        <div class="profile-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= floor($avg_rating)): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                        </div>
                        
                        <div class="profile-contact">
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($team_member['email']); ?></span>
                            </div>
                            
                            <?php if (!empty($team_member['phone'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($team_member['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="contact-item">
                                <i class="fas fa-briefcase"></i>
                                <span>Experience: <?php echo rand(2, 10); ?> years</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-body">
                    <div class="profile-section">
                        <h3>About</h3>
                        <p>
                            <?php echo htmlspecialchars($team_member['first_name']); ?> is a dedicated Immigration Assistant with extensive experience helping clients navigate the complexities of the Canadian immigration system. Specializing in various visa types, <?php echo htmlspecialchars($team_member['first_name']); ?> provides personalized consultation services to ensure a smooth application process.
                        </p>
                    </div>
                    
                    <div class="profile-section">
                        <h3>Expertise</h3>
                        <div class="expertise-tags">
                            <span>Immigration Consultation</span>
                            <span>Visa Applications</span>
                            <span>Document Verification</span>
                            <span>Status Inquiries</span>
                            <span>Application Review</span>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h3>Languages</h3>
                        <div class="language-list">
                            <div class="language-item">
                                <span>English</span>
                                <div class="proficiency">
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                </div>
                            </div>
                            <div class="language-item">
                                <span>French</span>
                                <div class="proficiency">
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar full"></div>
                                    <div class="proficiency-bar empty"></div>
                                    <div class="proficiency-bar empty"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-footer">
                    <div class="book-action">
                        <?php if ($is_logged_in): ?>
                            <a href="dashboard/admin/create_booking.php?consultant=<?php echo $team_member['id']; ?>" class="btn primary-btn">
                                <i class="fas fa-calendar-check"></i> Book a Consultation
                            </a>
                        <?php else: ?>
                            <div class="login-required">
                                <p>Please login or register to book a consultation</p>
                                <div class="login-buttons">
                                    <a href="login.php?redirect=team-member-profile.php?id=<?php echo $team_member['id']; ?>" class="btn secondary-btn">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </a>
                                    <a href="register.php?redirect=team-member-profile.php?id=<?php echo $team_member['id']; ?>" class="btn primary-btn">
                                        <i class="fas fa-user-plus"></i> Register
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="services-card">
                <h3>Services Offered</h3>
                
                <?php if (empty($services)): ?>
                    <div class="empty-state">
                        <p>No services available at the moment</p>
                    </div>
                <?php else: ?>
                    <div class="services-list">
                        <?php foreach ($services as $service): ?>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($service['visa_type']); ?> Visa (<?php echo htmlspecialchars($service['country_name']); ?>)</p>
                                </div>
                                <div class="service-price">
                                    <span>$<?php echo number_format($service['base_price'], 2); ?></span>
                                    <?php if ($is_logged_in): ?>
                                        <a href="dashboard/admin/create_booking.php?consultant=<?php echo $team_member['id']; ?>&service=<?php echo $service['visa_service_id']; ?>" class="btn small-btn">Book</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="reviews-card">
                <h3>Client Reviews</h3>
                
                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <p>No reviews available yet</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="review-author">
                                        <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                                        <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-body">
                                    <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-sidebar">
            <div class="availability-card">
                <h3>Availability</h3>
                <div class="availability-days">
                    <div class="day-item">
                        <span>Monday</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="day-item">
                        <span>Tuesday</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="day-item">
                        <span>Wednesday</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="day-item">
                        <span>Thursday</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="day-item">
                        <span>Friday</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="day-item unavailable">
                        <span>Saturday</span>
                        <span class="time">Unavailable</span>
                    </div>
                    <div class="day-item unavailable">
                        <span>Sunday</span>
                        <span class="time">Unavailable</span>
                    </div>
                </div>
                
                <div class="availability-note">
                    <i class="fas fa-info-circle"></i>
                    <p>Schedule a consultation by booking an appointment.</p>
                </div>
                
                <?php if ($is_logged_in): ?>
                    <a href="dashboard/admin/create_booking.php?consultant=<?php echo $team_member['id']; ?>" class="btn primary-btn full-width">
                        Check Available Slots
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="faq-card">
                <h3>Frequently Asked Questions</h3>
                <div class="faq-list">
                    <div class="faq-item">
                        <h4>How do I book a consultation?</h4>
                        <p>You can book a consultation by logging in to your account and selecting an available time slot.</p>
                    </div>
                    <div class="faq-item">
                        <h4>What should I prepare for my consultation?</h4>
                        <p>Please have your identification documents, any existing visa applications, and specific questions ready for your consultation.</p>
                    </div>
                    <div class="faq-item">
                        <h4>Can I reschedule my appointment?</h4>
                        <p>Yes, you can reschedule your appointment up to 24 hours before the scheduled time through your dashboard.</p>
                    </div>
                    <div class="faq-item">
                        <h4>What payment methods are accepted?</h4>
                        <p>We accept credit cards, PayPal, and bank transfers for consultation payments.</p>
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

.profile-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header-container h1 {
    color: var(--primary-color);
    font-size: 1.8rem;
    margin: 0;
}

.header-container p {
    color: var(--secondary-color);
    margin: 5px 0 0;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
    color: white;
}

.secondary-btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
}

.secondary-btn:hover {
    background-color: #76788a;
    color: white;
}

.small-btn {
    padding: 5px 10px;
    font-size: 12px;
}

.full-width {
    width: 100%;
    justify-content: center;
}

.profile-content {
    display: flex;
    gap: 30px;
}

.profile-main {
    flex: 3;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-sidebar {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-card, .services-card, .reviews-card, .availability-card, .faq-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.profile-header {
    display: flex;
    padding: 30px;
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
}

.profile-image {
    width: 150px;
    height: 150px;
    border-radius: 8px;
    overflow: hidden;
    margin-right: 30px;
    flex-shrink: 0;
}

.profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-info {
    display: flex;
    flex-direction: column;
}

.profile-info h2 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.profile-role {
    display: inline-block;
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
    font-size: 0.85rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.profile-rating {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.stars {
    display: flex;
    gap: 2px;
    margin-right: 10px;
}

.stars i {
    color: #ffc107;
}

.rating-text {
    color: var(--dark-color);
    font-size: 0.9rem;
}

.profile-contact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-item i {
    color: var(--primary-color);
    width: 16px;
}

.profile-body {
    padding: 30px;
}

.profile-section {
    margin-bottom: 25px;
}

.profile-section:last-child {
    margin-bottom: 0;
}

.profile-section h3 {
    color: var(--primary-color);
    font-size: 1.2rem;
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.expertise-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.expertise-tags span {
    background-color: var(--light-color);
    color: var(--dark-color);
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.9rem;
}

.language-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.language-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.proficiency {
    display: flex;
    gap: 3px;
}

.proficiency-bar {
    width: 20px;
    height: 8px;
    border-radius: 4px;
}

.proficiency-bar.full {
    background-color: var(--primary-color);
}

.proficiency-bar.empty {
    background-color: var(--border-color);
}

.profile-footer {
    padding: 20px 30px;
    border-top: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.book-action {
    display: flex;
    justify-content: center;
}

.login-required {
    text-align: center;
}

.login-required p {
    color: var(--secondary-color);
    margin-bottom: 15px;
}

.login-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.services-card, .reviews-card {
    padding: 20px;
}

.services-card h3, .reviews-card h3, .availability-card h3, .faq-card h3 {
    color: var(--primary-color);
    font-size: 1.2rem;
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.services-list, .reviews-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.service-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    transition: all 0.2s;
}

.service-item:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.service-info h4 {
    margin: 0 0 5px;
    color: var(--dark-color);
    font-size: 1rem;
}

.service-info p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.service-price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.service-price span {
    font-weight: 600;
    color: var(--primary-color);
}

.review-item {
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
}

.review-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.review-author {
    display: flex;
    flex-direction: column;
}

.review-date {
    color: var(--secondary-color);
    font-size: 0.8rem;
}

.review-rating i {
    color: #e0e0e0;
}

.review-rating i.filled {
    color: #ffc107;
}

.review-body p {
    margin: 0;
    color: var(--dark-color);
    font-size: 0.95rem;
}

.availability-card, .faq-card {
    padding: 20px;
}

.availability-days {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.day-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.day-item:last-child {
    border-bottom: none;
}

.day-item.unavailable .time {
    color: var(--danger-color);
}

.availability-note {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 0;
    padding: 10px;
    background-color: rgba(4, 33, 103, 0.05);
    border-radius: 5px;
}

.availability-note i {
    color: var(--primary-color);
}

.availability-note p {
    margin: 0;
    font-size: 0.9rem;
}

.faq-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.faq-item h4 {
    margin: 0 0 8px;
    color: var(--primary-color);
    font-size: 1rem;
}

.faq-item p {
    margin: 0;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 20px;
    color: var(--secondary-color);
}

@media (max-width: 992px) {
    .profile-content {
        flex-direction: column;
    }
    
    .profile-sidebar {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .profile-image {
        margin-right: 0;
        margin-bottom: 20px;
    }
    
    .profile-contact {
        align-items: center;
    }
}

@media (max-width: 576px) {
    .profile-sidebar {
        grid-template-columns: 1fr;
    }
    
    .login-buttons {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
