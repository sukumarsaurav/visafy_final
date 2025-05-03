<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Notifications";
$page_specific_css = "assets/css/notifications.css";
require_once 'includes/header.php';

// Get notifications
try {
    $query = "SELECT n.*, nt.name as type_name, nt.icon 
              FROM notifications n
              JOIN notification_types nt ON n.notification_type_id = nt.id
              WHERE n.user_id = ? 
              ORDER BY n.created_at DESC 
              LIMIT 50";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $notifications = [];
}

// Handle mark as read
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 'all') {
    try {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        
        if ($stmt->execute()) {
            // Update notifications in our array
            foreach ($notifications as &$notification) {
                $notification['is_read'] = 1;
            }
            
            // Redirect to avoid resubmission on refresh
            header("Location: notifications.php?marked=all");
            exit;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
    }
}

// Handle mark single notification as read
if (isset($_GET['mark_notification']) && is_numeric($_GET['mark_notification'])) {
    try {
        $notification_id = intval($_GET['mark_notification']);
        
        // Verify this notification belongs to the user
        $check_query = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ii', $notification_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $notification_id);
            
            if ($stmt->execute()) {
                // Update notification in our array
                foreach ($notifications as &$notification) {
                    if ($notification['id'] == $notification_id) {
                        $notification['is_read'] = 1;
                        break;
                    }
                }
                
                // Redirect to avoid resubmission on refresh
                header("Location: notifications.php?marked=single");
                exit;
            }
            $stmt->close();
        }
        $check_stmt->close();
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
    }
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unread_count++;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Notifications</h1>
            <p>Stay updated with your application process</p>
        </div>
        <?php if ($unread_count > 0): ?>
            <div>
                <a href="notifications.php?mark_read=all" class="btn secondary-btn">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="notification-container">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>You don't have any notifications yet.</p>
                <p>We'll notify you when there are updates to your applications or bookings.</p>
            </div>
        <?php else: ?>
            <div class="notification-filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="unread">Unread</button>
                <button class="filter-btn" data-filter="application">Application</button>
                <button class="filter-btn" data-filter="document">Document</button>
                <button class="filter-btn" data-filter="booking">Booking</button>
                <button class="filter-btn" data-filter="system">System</button>
            </div>
            
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                         data-type="<?php echo strtolower($notification['type_name']); ?>">
                        <div class="notification-icon <?php echo strtolower($notification['type_name']); ?>">
                            <i class="<?php echo $notification['icon']; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="far fa-clock"></i> 
                                    <?php echo time_elapsed_string($notification['created_at']); ?>
                                </span>
                                <?php if ($notification['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-link">
                                        View Details <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($notification['is_read'] == 0): ?>
                            <div class="notification-actions">
                                <a href="notifications.php?mark_notification=<?php echo $notification['id']; ?>" class="mark-read-btn" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.content {
    padding: 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.btn {
    padding: 10px 15px;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--border-color);
}

.secondary-btn:hover {
    background-color: #f8f9fc;
}

.notification-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 30px;
    text-align: center;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0 0 10px;
}

.notification-filters {
    display: flex;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    overflow-x: auto;
    gap: 10px;
}

.filter-btn {
    background: none;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    cursor: pointer;
    color: var(--secondary-color);
    white-space: nowrap;
}

.filter-btn:hover {
    background-color: #f8f9fc;
}

.filter-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.notification-list {
    max-height: 600px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.3s;
    position: relative;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background-color: #f8f9fc;
}

.notification-item.unread {
    background-color: rgba(78, 115, 223, 0.05);
}

.notification-item.unread:hover {
    background-color: rgba(78, 115, 223, 0.1);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.notification-icon.application {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.notification-icon.document {
    background-color: rgba(246, 194, 62, 0.1);
    color: #f6c23e;
}

.notification-icon.booking {
    background-color: rgba(28, 200, 138, 0.1);
    color: #1cc88a;
}

.notification-icon.system {
    background-color: rgba(54, 185, 204, 0.1);
    color: #36b9cc;
}

.notification-content {
    flex: 1;
}

.notification-content h3 {
    margin: 0 0 5px;
    color: var(--dark-color);
    font-size: 1rem;
}

.notification-content p {
    margin: 0 0 10px;
    color: var(--secondary-color);
    font-size: 0.9rem;
    line-height: 1.5;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
}

.notification-time {
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 5px;
}

.notification-link {
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
}

.notification-link:hover {
    text-decoration: underline;
}

.notification-actions {
    display: flex;
    align-items: center;
    margin-left: 15px;
}

.mark-read-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #f8f9fc;
    color: var(--primary-color);
    text-decoration: none;
    border: 1px solid var(--border-color);
}

.mark-read-btn:hover {
    background-color: var(--primary-color);
    color: white;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .notification-item {
        flex-direction: column;
    }
    
    .notification-icon {
        margin-bottom: 10px;
    }
    
    .notification-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .notification-actions {
        position: absolute;
        top: 15px;
        right: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');
    const notificationItems = document.querySelectorAll('.notification-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Apply filter
            const filter = this.getAttribute('data-filter');
            
            notificationItems.forEach(item => {
                if (filter === 'all') {
                    item.style.display = 'flex';
                } else if (filter === 'unread') {
                    item.style.display = item.classList.contains('unread') ? 'flex' : 'none';
                } else {
                    item.style.display = item.getAttribute('data-type') === filter ? 'flex' : 'none';
                }
            });
        });
    });
});
</script>

<?php
// Helper function to format dates as "time ago"
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 