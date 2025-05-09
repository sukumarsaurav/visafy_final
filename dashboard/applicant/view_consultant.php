<?php
// Create a new file: dashboard/applicant/view_consultant.php

// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: ../../login.php");
    exit;
}

$page_title = "Consultant Profile";
$page_specific_css = "assets/css/consultant-profile.css";
require_once 'includes/header.php';

// Get consultant ID from URL
$team_member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_member_id <= 0) {
    // Invalid ID, redirect to bookings page
    header("Location: bookings.php");
    exit;
}

// Get consultant details
$query = "SELECT tm.id as team_member_id, u.id as user_id, 
          u.first_name, u.last_name, u.profile_picture, u.email, tm.phone,
          cp.bio, cp.specialty_areas, cp.years_of_experience, cp.license_type,
          cp.license_number, cp.license_expiry, cp.education,
          ROUND(AVG(IFNULL(cr.rating, 0)), 1) as average_rating,
          COUNT(cr.id) as review_count
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          LEFT JOIN consultant_profiles cp ON tm.id = cp.team_member_id
          LEFT JOIN consultant_reviews cr ON tm.id = cr.team_member_id AND cr.status = 'approved'
          WHERE tm.id = ? AND tm.role = 'Immigration Assistant'
          AND u.status = 'active'
          AND tm.deleted_at IS NULL
          AND u.deleted_at IS NULL";
          
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Consultant not found or not active
    $error_message = "Consultant not found.";
} else {
    $consultant = $result->fetch_assoc();
    
    // Parse specialty areas
    if (!empty($consultant['specialty_areas'])) {
        $consultant['specialty_areas_array'] = json_decode($consultant['specialty_areas'], true) ?? [];
    } else {
        $consultant['specialty_areas_array'] = [];
    }
    
    // Get consultant languages
    $languages_query = "SELECT cl.language, cl.proficiency_level 
                       FROM consultant_languages cl
                       JOIN consultant_profiles cp ON cl.consultant_profile_id = cp.id
                       WHERE cp.team_member_id = ?";
    $lang_stmt = $conn->prepare($languages_query);
    $lang_stmt->bind_param('i', $team_member_id);
    $lang_stmt->execute();
    $languages_result = $lang_stmt->get_result();
    
    $consultant['languages'] = [];
    while ($lang = $languages_result->fetch_assoc()) {
        $consultant['languages'][] = $lang;
    }
    $lang_stmt->close();
    
    // Get consultant reviews
    $reviews_query = "SELECT cr.*, 
                     CONCAT(u.first_name, ' ', SUBSTRING(u.last_name, 1, 1), '.') as reviewer_name,
                     cr.review_date,
                     NULL as application_title
                     FROM consultant_reviews cr
                     LEFT JOIN users u ON cr.user_id = u.id
                     WHERE cr.team_member_id = ? 
                     AND cr.status = 'approved'
                     ORDER BY cr.review_date DESC
                     LIMIT 5";
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews_stmt->bind_param('i', $team_member_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    
    $consultant['reviews'] = [];
    while ($review = $reviews_result->fetch_assoc()) {
        $consultant['reviews'][] = $review;
    }
    $reviews_stmt->close();
}
$stmt->close();
?>

<div class="content">
    <div class="back-link">
        <a href="bookings.php"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php else: ?>
        <div class="consultant-profile">
            <div class="profile-header">
                <div class="profile-photo-container">
                    <div class="profile-photo">
                        <?php if (!empty($consultant['profile_picture'])): ?>
                            <img src="../../uploads/profiles/<?php echo htmlspecialchars($consultant['profile_picture']); ?>" 
                                 alt="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>">
                        <?php else: ?>
                            <div class="profile-initials">
                                <?php echo substr($consultant['first_name'], 0, 1) . substr($consultant['last_name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <div class="profile-info">
                    <h1 class="consultant-name"><?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?></h1>
                    <p class="consultant-title">Immigration Specialist</p>
                    <div class="rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($consultant['average_rating'])): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $consultant['average_rating']): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="review-count"><?php echo $consultant['average_rating']; ?> (<?php echo $consultant['review_count']; ?> reviews)</span>
                    </div>
                    <button class="book-button" onclick="location.href='bookings.php?consultant_id=<?php echo $consultant['team_member_id']; ?>'">
                        <i class="fas fa-calendar-check"></i> Book Consultation
                    </button>
                </div>
                
                <div class="profile-tabs">
                    <div class="tab-navigation">
                        <button class="tab-btn active" data-tab="about">About</button>
                        <button class="tab-btn" data-tab="experience">Experience & Specialties</button>
                        <button class="tab-btn" data-tab="reviews">Reviews</button>
                    </div>
                    
                    <div id="about" class="tab-content active">
                        <?php if (!empty($consultant['bio'])): ?>
                            <div class="bio-section">
                                <h2>About Me</h2>
                                <p><?php echo nl2br(htmlspecialchars($consultant['bio'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="details-section">
                            <h2>Details</h2>
                            <div class="details-grid">
                                <?php if (!empty($consultant['years_of_experience'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon"><i class="fas fa-briefcase"></i></div>
                                        <div class="detail-content">
                                            <h3>Experience</h3>
                                            <p><?php echo $consultant['years_of_experience']; ?> years</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($consultant['license_type'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon"><i class="fas fa-certificate"></i></div>
                                        <div class="detail-content">
                                            <h3>License Type</h3>
                                            <p><?php echo htmlspecialchars($consultant['license_type']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($consultant['languages'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon"><i class="fas fa-language"></i></div>
                                        <div class="detail-content">
                                            <h3>Languages</h3>
                                            <div class="languages-list">
                                                <?php foreach($consultant['languages'] as $language): ?>
                                                    <div class="language-tag">
                                                        <?php echo htmlspecialchars($language['language']); ?>
                                                        <span class="proficiency"><?php echo ucfirst($language['proficiency_level']); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="experience" class="tab-content">
                        <?php if (!empty($consultant['education'])): ?>
                            <div class="education-section">
                                <h2>Education</h2>
                                <div class="education-content">
                                    <?php echo nl2br(htmlspecialchars($consultant['education'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($consultant['specialty_areas_array'])): ?>
                            <div class="specialties-section">
                                <h2>Specialties</h2>
                                <div class="specialties-grid">
                                    <?php foreach($consultant['specialty_areas_array'] as $specialty): ?>
                                        <div class="specialty-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo htmlspecialchars($specialty); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($consultant['license_number']) && !empty($consultant['license_expiry'])): ?>
                            <div class="credentials-section">
                                <h2>Credentials</h2>
                                <div class="credentials-grid">
                                    <div class="credential-item">
                                        <h3>License Number</h3>
                                        <p><?php echo htmlspecialchars($consultant['license_number']); ?></p>
                                    </div>
                                    <div class="credential-item">
                                        <h3>License Expiry</h3>
                                        <p><?php echo date('F d, Y', strtotime($consultant['license_expiry'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="reviews" class="tab-content">
                        <div class="reviews-header">
                            <h2>Client Reviews</h2>
                            <div class="overall-rating">
                                <div class="rating-number"><?php echo $consultant['average_rating']; ?></div>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= floor($consultant['average_rating'])): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif ($i - 0.5 <= $consultant['average_rating']): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="review-count"><?php echo $consultant['review_count']; ?> reviews</div>
                            </div>
                        </div>
                        
                        <?php if (empty($consultant['reviews'])): ?>
                            <div class="no-reviews">
                                <p>No reviews available for this consultant yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="reviews-list">
                                <?php foreach($consultant['reviews'] as $review): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                                <div class="review-date"><?php echo date('F d, Y', strtotime($review['review_date'])); ?></div>
                                            </div>
                                            <div class="review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'active' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($review['application_title'])): ?>
                                            <div class="review-service">
                                                <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($review['application_title']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($review['review_text'])): ?>
                                            <div class="review-content">
                                                <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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

.content {
    padding: 20px;
}

.back-link {
    margin-bottom: 20px;
}

.back-link a {
    color: var(--primary-color);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.back-link a:hover {
    text-decoration: underline;
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

.profile-initials {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: var(--primary-color);
    color: white;
    font-size: 60px;
    font-weight: 600;
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
    margin-bottom: 20px;
}

.stars {
    color: #ffc107;
}

.review-count {
    color: var(--secondary-color);
}

.book-button {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.book-button:hover {
    background-color: #031c56;
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

.tab-btn {
    background: none;
    border: none;
    padding: 15px 30px;
    font-size: 1rem;
    color: var(--secondary-color);
    cursor: pointer;
    position: relative;
    transition: color 0.3s;
}

.tab-btn.active {
    color: var(--primary-color);
    font-weight: 600;
}

.tab-btn.active:after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background-color: var(--primary-color);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.bio-section, 
.details-section, 
.education-section, 
.specialties-section, 
.credentials-section {
    margin-bottom: 40px;
}

.bio-section h2, 
.details-section h2, 
.education-section h2, 
.specialties-section h2, 
.credentials-section h2,
.reviews-header h2 {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 20px;
}

.bio-section p {
    line-height: 1.7;
    color: var(--dark-color);
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.detail-icon {
    width: 40px;
    height: 40px;
    background-color: rgba(4, 33, 103, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 16px;
}

.detail-content h3 {
    font-size: 1rem;
    color: var(--dark-color);
    margin: 0 0 5px;
}

.detail-content p {
    margin: 0;
    color: var(--secondary-color);
}

.languages-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.language-tag {
    font-size: 0.9rem;
    color: var(--dark-color);
    background-color: rgba(4, 33, 103, 0.05);
    padding: 3px 10px;
    border-radius: 15px;
    display: inline-flex;
    align-items: center;
}

.language-tag .proficiency {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-left: 5px;
}

.education-content {
    line-height: 1.7;
    color: var(--dark-color);
}

.specialties-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.specialty-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background-color: rgba(4, 33, 103, 0.05);
    border-radius: 8px;
}

.specialty-item i {
    color: var(--primary-color);
}

.credentials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.credential-item h3 {
    font-size: 1rem;
    color: var(--dark-color);
    margin: 0 0 5px;
}

.credential-item p {
    margin: 0;
    color: var(--secondary-color);
}

.reviews-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.overall-rating {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
}

.rating-stars {
    color: #ffc107;
    font-size: 1.2rem;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-card {
    background-color: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
}

.review-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.reviewer-name {
    font-weight: 600;
    color: var(--dark-color);
}

.review-date {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.review-rating {
    color: #ccc;
}

.review-rating .active {
    color: #ffc107;
}

.review-service {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.review-content {
    line-height: 1.6;
    color: var(--dark-color);
}

.no-reviews {
    text-align: center;
    padding: 40px 0;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .profile-content {
        padding: 120px 20px 20px;
    }
    
    .tab-navigation {
        overflow-x: auto;
        justify-content: flex-start;
    }
    
    .details-grid,
    .specialties-grid,
    .credentials-grid {
        grid-template-columns: 1fr;
    }
    
    .reviews-header {
        flex-direction: column;
        align-items: flex-start;
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
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
