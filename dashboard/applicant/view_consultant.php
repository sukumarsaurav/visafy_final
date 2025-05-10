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
    <div class="consultant-profile">
        <div class="profile-header">
            <div class="back-link-header">
                <a href="bookings.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
            <div class="profile-photo-container">
                <div class="profile-photo">
                    <?php if (!empty($consultant['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($consultant['profile_picture']); ?>" 
                             alt="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>">
                    <?php else: ?>
                        <div class="consultant-initials">
                            <?php echo substr($consultant['first_name'], 0, 1) . substr($consultant['last_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-header-info">
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
                    <div class="profile-actions">
                        <button class="book-button" id="bookConsultationBtn">
                            <i class="fas fa-calendar-check"></i> Book Consultation
                        </button>
                        <a href="messages.php?team_member_id=<?php echo $consultant['user_id']; ?>" class="send-message-btn">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="profile-content">
            <div class="profile-tabs">
                <div class="tab-navigation left-align">
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
</div>

<!-- Create Booking Modal -->
<div class="modal" id="createBookingModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Book an Appointment</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="bookings.php" method="POST" id="createBookingForm">
                    <div class="form-group">
                        <label for="visa_service_id">Service*</label>
                        <select name="visa_service_id" id="visa_service_id" class="form-control" required>
                            <option value="">Select Service</option>
                            <?php 
                            // Get visa services
                            $query = "SELECT vs.visa_service_id, v.visa_type, c.country_name, st.service_name 
                                      FROM visa_services vs
                                      JOIN visas v ON vs.visa_id = v.visa_id
                                      JOIN countries c ON v.country_id = c.country_id
                                      JOIN service_types st ON vs.service_type_id = st.service_type_id
                                      WHERE vs.is_active = 1
                                      ORDER BY c.country_name, v.visa_type, st.service_name";
                            $stmt = $conn->prepare($query);
                            $stmt->execute();
                            $services_result = $stmt->get_result();
                            
                            while ($service = $services_result->fetch_assoc()): ?>
                                <option value="<?php echo $service['visa_service_id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name'] . ' (' . $service['visa_type'] . ' - ' . $service['country_name'] . ')'); ?>
                                </option>
                            <?php endwhile;
                            $stmt->close();
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_mode_id">Consultation Mode*</label>
                        <select name="consultation_mode_id" id="consultation_mode_id" class="form-control" required disabled>
                            <option value="">Select Service First</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_date">Date*</label>
                        <input type="date" name="booking_date" id="booking_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                               required disabled>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_time">Time*</label>
                        <select name="booking_time" id="booking_time" class="form-control" required disabled>
                            <option value="">Select Date First</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_notes">Additional Notes</label>
                        <textarea name="client_notes" id="client_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <input type="hidden" name="selected_consultant_id" id="selected_consultant_id" value="<?php echo $consultant['team_member_id']; ?>">
                    
                    <div class="booking-summary" id="booking-summary" style="display: none;">
                        <h4>Booking Summary</h4>
                        <p><strong>Service:</strong> <span id="summary-service"></span></p>
                        <p><strong>Mode:</strong> <span id="summary-mode"></span></p>
                        <p><strong>Date & Time:</strong> <span id="summary-datetime"></span></p>
                        <p><strong>Consultant:</strong> <?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?></p>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_booking" class="btn submit-btn">Book Appointment</button>
                    </div>
                </form>
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

.content {
    padding:0px;
}

.consultant-profile {
    margin: 0 auto;
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow);
}

.profile-header {
    background: linear-gradient(90deg, #ff9800 0%, #e91e63 100%);
    height: 260px;
    position: relative;
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: flex-end;
    padding-bottom: 30px;
    gap: 30px;
}

.back-link-header {
    position: absolute;
    top: 20px;
    right: 30px;
    z-index: 10;
}

.profile-photo-container {
    position: relative;
    width: 180px;
    height: 180px;
    margin-left: 40px;
    margin-bottom: -40px;
    z-index: 100;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    border: 5px solid white;
    overflow: hidden;
}

.profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.consultant-initials {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--primary-color);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
}

.profile-content {
    padding: 20px;
}

.profile-header-info {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
    width: 100%;
    margin-left: 40px;
    gap: 40px;
}

.profile-header-info .profile-info {
    text-align: left;
    margin-bottom: 0;
}

.profile-header-actions {
    display: flex;
    flex-direction: row;
    gap: 15px;
    align-items: center;
    margin-bottom: 20px;
}

.send-message-btn {
    background-color: #4e73df;
    color: #fff;
    border: none;
    border-radius: 30px;
    padding: 10px 20px;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.3s;
}

.send-message-btn:hover {
    background-color: #375ad3;
}

.tab-navigation {
    display: flex;
    justify-content: flex-start;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 30px;
    gap: 0;
}

.tab-navigation.left-align {
    justify-content: flex-start;
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

.profile-header,
.profile-header .consultant-name,
.profile-header .consultant-title,
.profile-header .rating,
.profile-header .stars,
.profile-header .review-count,
.profile-header .profile-header-actions,
.profile-header .profile-header-actions .book-button,
.profile-header .profile-header-actions .send-message-btn,
.profile-header .back-link-header .back-btn {
    color: #fff !important;
}

.back-link-header .back-btn {
    background: rgba(0,0,0,0.18);
    color: #fff;
    border: none;
    border-radius: 20px;
    padding: 8px 18px;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
}
.back-link-header .back-btn:hover {
    background: rgba(0,0,0,0.32);
    color: #fff;
    text-decoration: none;
}

.profile-header-actions .book-button {
    background-color: #fff;
    color: #e91e63;
    border: none;
    border-radius: 30px;
    padding: 10px 22px;
    font-size: 1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: background 0.2s, color 0.2s;
}
.profile-header-actions .book-button:hover {
    background-color: #ffe0b2;
    color: #ff9800;
}

.profile-header-actions .send-message-btn {
    background-color: #4e73df;
    color: #fff;
    border: none;
    border-radius: 30px;
    padding: 10px 22px;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: background 0.2s;
}
.profile-header-actions .send-message-btn:hover {
    background-color: #375ad3;
    color: #fff;
}

.profile-info {
    text-align: left;
    margin-bottom: 0;
    color: #fff;
}

.profile-actions {
    display: flex;
    flex-direction: row;
    gap: 15px;
    margin-top: 20px;
}

.book-button {
    background-color: #fff;
    color: #e91e63 !important; /* Force text color */
    border: none;
    border-radius: 30px;
    padding: 10px 22px;
    font-size: 1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: background 0.2s, color 0.2s;
}

.book-button:hover {
    background-color: #ffe0b2;
    color: #ff9800 !important;
}

.send-message-btn {
    background-color: #4e73df;
    color: #fff !important; /* Force text color */
    border: none;
    border-radius: 30px;
    padding: 10px 22px;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: background 0.2s;
}

.send-message-btn:hover {
    background-color: #375ad3;
    color: #fff !important;
}

/* Modal styling from bookings.php */
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
    border-radius: 5px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
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

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
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

.form-control:disabled {
    background-color: #f0f0f0;
    cursor: not-allowed;
}

.booking-summary {
    margin-top: 20px;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 5px;
    border: 1px solid var(--border-color);
}

.booking-summary h4 {
    margin: 0 0 10px;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.booking-summary p {
    margin: 5px 0;
    font-size: 14px;
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

/* Responsive fixes */
@media (max-width: 768px) {
    .profile-actions {
        flex-direction: column;
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
    
    // Modal functionality
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modals when close button is clicked
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
        element.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal').forEach(function(modal) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Open booking modal when "Book Consultation" button is clicked
    document.getElementById('bookConsultationBtn').addEventListener('click', function() {
        // Reset form
        document.getElementById('createBookingForm').reset();
        
        // Reset and disable form fields in sequence
        document.getElementById('consultation_mode_id').innerHTML = '<option value="">Select Service First</option>';
        document.getElementById('consultation_mode_id').disabled = true;
        
        document.getElementById('booking_date').disabled = true;
        
        document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
        document.getElementById('booking_time').disabled = true;
        
        // Hide booking summary
        document.getElementById('booking-summary').style.display = 'none';
        
        // Set consultant information in the form
        const noteText = "I would like to book with <?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>";
        document.getElementById('client_notes').value = noteText;
        
        // Show the modal
        openModal('createBookingModal');
    });
    
    // Add service selection handler (similar to bookings.php)
    document.getElementById('visa_service_id').addEventListener('change', function() {
        const serviceId = this.value;
        const consultationModeSelect = document.getElementById('consultation_mode_id');
        
        // Reset date and time fields
        document.getElementById('booking_date').disabled = true;
        document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
        document.getElementById('booking_time').disabled = true;
        
        if (serviceId) {
            // Enable and populate consultation modes
            fetchConsultationModes(serviceId, consultationModeSelect);
        } else {
            // Reset and disable consultation mode selection
            consultationModeSelect.innerHTML = '<option value="">Select Service First</option>';
            consultationModeSelect.disabled = true;
        }
    });
    
    // Add consultation mode handler
    document.getElementById('consultation_mode_id').addEventListener('change', function() {
        const consultationModeId = this.value;
        const dateInput = document.getElementById('booking_date');
        
        // Reset time field
        document.getElementById('booking_time').innerHTML = '<option value="">Select Date First</option>';
        document.getElementById('booking_time').disabled = true;
        
        if (consultationModeId) {
            // Enable date selection
            dateInput.disabled = false;
        } else {
            // Disable date selection
            dateInput.disabled = true;
        }
    });
    
    // Add date selection handler
    document.getElementById('booking_date').addEventListener('change', function() {
        const selectedDate = this.value;
        const serviceId = document.getElementById('visa_service_id').value;
        const consultationModeId = document.getElementById('consultation_mode_id').value;
        const timeSelect = document.getElementById('booking_time');
        
        if (selectedDate && serviceId && consultationModeId) {
            // Fetch available time slots
            fetchAvailableTimeSlots(serviceId, consultationModeId, selectedDate, timeSelect);
        } else {
            // Reset time selection
            timeSelect.innerHTML = '<option value="">Select Date First</option>';
            timeSelect.disabled = true;
        }
    });
    
    // Add time selection handler
    document.getElementById('booking_time').addEventListener('change', function() {
        const selectedTime = this.value;
        const selectedDate = document.getElementById('booking_date').value;
        const serviceSelect = document.getElementById('visa_service_id');
        const modeSelect = document.getElementById('consultation_mode_id');
        
        if (selectedTime) {
            // Show booking summary
            const summaryService = serviceSelect.options[serviceSelect.selectedIndex].text;
            const summaryMode = modeSelect.options[modeSelect.selectedIndex].text;
            const summaryDatetime = formatDate(selectedDate) + ' at ' + formatTime(selectedTime);
            
            document.getElementById('summary-service').textContent = summaryService;
            document.getElementById('summary-mode').textContent = summaryMode;
            document.getElementById('summary-datetime').textContent = summaryDatetime;
            
            document.getElementById('booking-summary').style.display = 'block';
        } else {
            document.getElementById('booking-summary').style.display = 'none';
        }
    });
    
    // Helper functions for date/time formatting
    function formatDate(dateString) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }

    function formatTime(timeString) {
        // If it's already in 12-hour format, return as is
        if (timeString.includes('AM') || timeString.includes('PM')) {
            return timeString;
        }
        
        // Otherwise convert from 24-hour format
        const [hours, minutes] = timeString.split(':');
        let hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12; // Convert 0 to 12
        return `${hour}:${minutes} ${ampm}`;
    }
    
    // Functions to fetch data from the server - using same API endpoints as bookings.php
    function fetchConsultationModes(serviceId, selectElement) {
        // Show loading state
        selectElement.innerHTML = '<option value="">Loading...</option>';
        selectElement.disabled = true;
        
        // Make AJAX request to get consultation modes
        fetch(`ajax/get_consultation_modes.php?service_id=${serviceId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate select options
                    selectElement.innerHTML = '<option value="">Select Consultation Mode</option>';
                    
                    if (data.modes && data.modes.length > 0) {
                        data.modes.forEach(mode => {
                            const option = document.createElement('option');
                            option.value = mode.consultation_mode_id;
                            option.textContent = `${mode.mode_name} ($${parseFloat(mode.total_price).toFixed(2)})`;
                            selectElement.appendChild(option);
                        });
                        
                        selectElement.disabled = false;
                    } else {
                        selectElement.innerHTML = '<option value="">No consultation modes available</option>';
                    }
                } else {
                    // Show error
                    console.error('Error loading consultation modes:', data.message);
                    selectElement.innerHTML = '<option value="">Error loading consultation modes</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching consultation modes:', error);
                selectElement.innerHTML = '<option value="">Error: Could not load consultation modes</option>';
            });
    }

    function fetchAvailableTimeSlots(serviceId, consultationModeId, date, selectElement) {
        // Show loading state
        selectElement.innerHTML = '<option value="">Loading available times...</option>';
        selectElement.disabled = true;
        
        // Make AJAX request to get available slots
        fetch(`ajax/get_available_slots.php?service_id=${serviceId}&consultation_mode_id=${consultationModeId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.slots && data.slots.length > 0) {
                        // Populate select options
                        selectElement.innerHTML = '<option value="">Select Time</option>';
                        
                        data.slots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot;
                            option.textContent = formatTime(slot);
                            selectElement.appendChild(option);
                        });
                        
                        selectElement.disabled = false;
                    } else {
                        // No slots available
                        selectElement.innerHTML = '<option value="">No times available on this date</option>';
                    }
                } else {
                    // Show error
                    console.error('Error fetching time slots:', data.message);
                    selectElement.innerHTML = '<option value="">Error loading time slots</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching time slots:', error);
                selectElement.innerHTML = '<option value="">Error: Could not load time slots</option>';
            });
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
