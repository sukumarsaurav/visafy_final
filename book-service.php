<?php
// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Set page title
$page_title = "Book Immigration Assistant | Visafy";
include 'includes/header.php';

// Fetch team members with role "Immigration Assistant"
$stmt = $conn->prepare("SELECT tm.*, u.first_name, u.last_name, u.email, u.profile_picture 
                        FROM team_members tm 
                        JOIN users u ON tm.user_id = u.id 
                        WHERE tm.role = 'Immigration Assistant' 
                        AND u.status = 'active' 
                        AND tm.deleted_at IS NULL 
                        AND u.deleted_at IS NULL
                        ORDER BY u.first_name, u.last_name");
$stmt->execute();
$result = $stmt->get_result();
$team_members = [];

while ($row = $result->fetch_assoc()) {
    $team_members[] = $row;
}
$stmt->close();
?>

<div class="book-service-container">
    <div class="header-container">
        <div>
            <h1>Book an Immigration Assistant</h1>
            <p>Choose from our expert team of immigration professionals to assist with your visa application process</p>
        </div>
    </div>

    <?php if (empty($team_members)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <p>No immigration assistants are currently available. Please check back later.</p>
        </div>
    <?php else: ?>
        <div class="team-members-grid">
            <?php foreach ($team_members as $member): ?>
                <div class="team-member-card">
                    <div class="team-member-image">
                        <?php
                        $profile_img = '/assets/images/default-profile.svg';
                        if (!empty($member['profile_picture'])) {
                            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/' . $member['profile_picture'])) {
                                $profile_img = '/uploads/profiles/' . $member['profile_picture'];
                            } else if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/' . $member['profile_picture'])) {
                                $profile_img = '/uploads/profile/' . $member['profile_picture'];
                            }
                        }
                        ?>
                        <img src="<?php echo $profile_img; ?>" alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>">
                    </div>
                    <div class="team-member-info">
                        <h3><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
                        <span class="team-member-role">Immigration Assistant</span>
                        <p class="team-member-experience">
                            <i class="fas fa-briefcase"></i> 
                            Experience: <?php echo rand(2, 10); ?> years
                        </p>
                        <p class="team-member-contact">
                            <i class="fas fa-envelope"></i> 
                            <?php echo htmlspecialchars($member['email']); ?>
                        </p>
                        <?php if (!empty($member['phone'])): ?>
                        <p class="team-member-contact">
                            <i class="fas fa-phone"></i> 
                            <?php echo htmlspecialchars($member['phone']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="team-member-actions">
                        <a href="dashboard/admin/create_booking.php?consultant=<?php echo $member['id']; ?>" class="btn book-btn">Book Consultation</a>
                        <a href="#" class="btn view-profile-btn" data-id="<?php echo $member['id']; ?>">View Profile</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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

.book-service-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.header-container {
    text-align: center;
    margin-bottom: 40px;
}

.header-container h1 {
    color: var(--primary-color);
    font-size: 2.2rem;
    margin-bottom: 10px;
}

.header-container p {
    color: var(--secondary-color);
    font-size: 1.1rem;
    max-width: 700px;
    margin: 0 auto;
}

.team-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.team-member-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.team-member-image {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.team-member-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.team-member-card:hover .team-member-image img {
    transform: scale(1.05);
}

.team-member-info {
    padding: 20px;
}

.team-member-info h3 {
    margin: 0 0 5px 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.team-member-role {
    display: inline-block;
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
    font-size: 0.85rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.team-member-experience, 
.team-member-contact {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
    color: var(--dark-color);
    font-size: 0.95rem;
}

.team-member-experience i,
.team-member-contact i {
    color: var(--primary-color);
    width: 16px;
}

.team-member-actions {
    padding: 0 20px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 5px;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.book-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.book-btn:hover {
    background-color: #031c56;
    color: white;
}

.view-profile-btn {
    background-color: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.view-profile-btn:hover {
    background-color: rgba(4, 33, 103, 0.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.empty-state i {
    font-size: 48px;
    color: var(--secondary-color);
    margin-bottom: 20px;
    opacity: 0.6;
}

.empty-state p {
    color: var(--secondary-color);
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .team-members-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .header-container h1 {
        font-size: 1.8rem;
    }
    
    .header-container p {
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .team-members-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View profile functionality (could be expanded in the future)
    const viewProfileButtons = document.querySelectorAll('.view-profile-btn');
    
    viewProfileButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const memberId = this.getAttribute('data-id');
            
            // This could be expanded to show a modal with more details
            // For now, just navigate to a hypothetical profile page
            window.location.href = 'team-member-profile.php?id=' + memberId;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
