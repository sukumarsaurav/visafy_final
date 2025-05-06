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

// Get profile image - use the same approach as in book-service.php
$profile_img = '/assets/images/default-profile.svg';
if (!empty($team_member['profile_picture'])) {
    // Check both possible profile picture locations
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

<div class="consultant-profile">
    <div class="profile-header">
        <div class="profile-photo-container">
            <div class="profile-photo">
                <img src="<?php echo $profile_img; ?>" alt="<?php echo htmlspecialchars($team_member['first_name'] . ' ' . $team_member['last_name']); ?>">
            </div>
        </div>
    </div>

    <div class="profile-content">
        <div class="profile-info">
            <h1 class="consultant-name"><?php echo htmlspecialchars($team_member['first_name'] . ' ' . $team_member['last_name']); ?></h1>
            <p class="consultant-title">Immigration Specialist</p>
            <div class="rating">
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
                <span class="review-count">(<?php echo $total_reviews; ?> reviews)</span>
            </div>
        </div>

        <div class="profile-tabs">
            <div class="tab-navigation">
                <div class="tab active" data-tab="about">About</div>
                <div class="tab" data-tab="services">Services</div>
                <div class="tab" data-tab="reviews">Reviews</div>
                <div class="tab" data-tab="faq">FAQ</div>
            </div>

            <div class="tab-content active" id="about-tab">
                <div class="contact-information">
                    <h2>Contact Information</h2>
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Toronto, Canada</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($team_member['email']); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>+1 (555) 123-4567</span>
                        </div>
                    </div>
                    
                    <div class="countries-section">
                        <h3>Countries:</h3>
                        <div class="countries">
                            <span class="country">USA</span>
                            <span class="country">Canada</span>
                        </div>
                    </div>
                    
                    <div class="languages-section">
                        <h3>Languages:</h3>
                        <div class="languages">
                            <span class="language">English</span>
                            <span class="language">Spanish</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="services-tab">
                <h2>Services Offered</h2>
                <?php if (empty($services)): ?>
                    <p class="no-content">No services available at the moment.</p>
                <?php else: ?>
                    <div class="services-list">
                        <?php foreach ($services as $service): ?>
                            <div class="service-item">
                                <div class="service-info">
                                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($service['visa_type']); ?> Visa (<?php echo htmlspecialchars($service['country_name']); ?>)</p>
                                </div>
                                <div class="service-price">
                                    <span>$<?php echo number_format($service['base_price'], 2); ?></span>
                                    <?php if ($is_logged_in): ?>
                                        <a href="dashboard/admin/create_booking.php?consultant=<?php echo $team_member['id']; ?>&service=<?php echo $service['visa_service_id']; ?>" class="btn-primary">Book</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="reviews-tab">
                <h2>Client Reviews</h2>
                <?php if (empty($reviews)): ?>
                    <p class="no-content">No reviews available yet.</p>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="review-author"><?php echo htmlspecialchars($review['client_name']); ?></div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                                </div>
                                <div class="review-content">
                                    <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="faq-tab">
                <h2>Frequently Asked Questions</h2>
                <div class="faq-list">
                    <div class="faq-item">
                        <h3 class="faq-question">What is the consultation process like?</h3>
                        <div class="faq-answer">
                            <p>The initial consultation is a 60-minute session where we discuss your immigration goals, assess your eligibility for various programs, and develop a personalized immigration strategy. You can book a consultation online, and we can meet in person or via video call.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <h3 class="faq-question">How long does the immigration process typically take?</h3>
                        <div class="faq-answer">
                            <p>The timeline varies depending on the type of application and the country. Work visa applications can take 2-6 months, while family sponsorship and permanent residency applications may take 6-18 months. During our consultation, I can provide a more accurate timeline based on your specific situation.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <h3 class="faq-question">What documents will I need for my application?</h3>
                        <div class="faq-answer">
                            <p>Document requirements vary by application type, but typically include identification documents, educational credentials, work experience proof, and financial documents. During our consultation, I will provide a detailed checklist specific to your case.</p>
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
    --accent-color: #eaaa34;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --border-color: #e3e6f0;
    --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    --border-radius: 10px;
}

.consultant-profile {
    margin: 0 auto;
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow);
}

.profile-header {
    background-color: var(--light-color);
    height: 200px;
    position: relative;
    background-image: linear-gradient(rgba(4, 33, 103, 0.7), rgba(4, 33, 103, 0.7)), url('/assets/images/header-bg.jpg');
    background-size: cover;
    background-position: center;
}

.profile-photo-container {
    position: absolute;
    bottom: -100px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 100;
    width: 200px;
    height: 200px;
}

.profile-photo {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    border: 5px solid white;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.profile-content {
    padding: 120px 40px 40px;
}

.profile-info {
    text-align: center;
    margin-bottom: 40px;
}

.consultant-name {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.consultant-title {
    font-size: 1.1rem;
    color: var(--secondary-color);
    margin-bottom: 15px;
}

.rating {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.stars {
    color: #ffc107;
}

.review-count {
    color: var(--secondary-color);
}

.profile-tabs {
    max-width: 900px;
    margin: 0 auto;
}

.tab-navigation {
    display: flex;
    justify-content: center;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.tab {
    padding: 15px 30px;
    cursor: pointer;
    font-weight: 500;
    color: var(--secondary-color);
    position: relative;
}

.tab.active {
    color: var(--primary-color);
}

.tab.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
}

.tab-content {
    display: none;
    padding: 20px 0;
}

.tab-content.active {
    display: block;
}

.tab-content h2 {
    color: var(--primary-color);
    font-size: 1.8rem;
    margin-bottom: 20px;
    text-align: center;
}

.contact-information {
    max-width: 600px;
    margin: 0 auto;
    background-color: var(--light-color);
    padding: 30px;
    border-radius: var(--border-radius);
}

.contact-details {
    margin-bottom: 30px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.contact-item i {
    width: 20px;
    color: var(--primary-color);
}

.countries-section, .languages-section {
    margin-top: 25px;
}

.countries-section h3, .languages-section h3 {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.countries, .languages {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.country, .language {
    background-color: white;
    padding: 5px 15px;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.services-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.service-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.service-info h3 {
    margin: 0 0 5px;
    color: var(--primary-color);
}

.service-info p {
    margin: 0;
    color: var(--secondary-color);
}

.service-price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}

.service-price span {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
}

.btn-primary {
    display: inline-block;
    padding: 8px 15px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 5px;
    text-decoration: none;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background-color: #031c56;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-item {
    padding: 20px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.review-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.review-author {
    font-weight: 500;
}

.review-rating {
    color: #ffc107;
}

.review-date {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.review-content p {
    margin: 0;
    color: var(--dark-color);
}

.faq-list {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border-color);
}

.faq-item:last-child {
    border-bottom: none;
}

.faq-question {
    font-size: 1.2rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.faq-answer p {
    color: var(--dark-color);
    line-height: 1.6;
}

.no-content {
    text-align: center;
    color: var(--secondary-color);
    font-style: italic;
    padding: 30px;
}

@media (max-width: 768px) {
    .profile-header {
        height: 250px;
    }
    
    .profile-photo {
        width: 160px;
        height: 160px;
    }
    
    .profile-photo-container {
        bottom: -80px;
    }
    
    .profile-content {
        padding: 100px 20px 30px;
    }
    
    .consultant-name {
        font-size: 2rem;
    }
    
    .tab-navigation {
        flex-wrap: wrap;
    }
    
    .tab {
        padding: 10px 15px;
    }
    
    .services-list {
        grid-template-columns: 1fr;
    }
    
    .contact-information {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .review-header {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Show the selected tab content
            const tabName = this.getAttribute('data-tab');
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
    
    // FAQ toggle functionality
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            
            if (answer.style.maxHeight) {
                answer.style.maxHeight = null;
                this.classList.remove('active');
            } else {
                answer.style.maxHeight = answer.scrollHeight + "px";
                this.classList.add('active');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
