<?php
// Set page title
$page_title = "Dashboard";
$page_specific_css = "../assets/css/dashboard.css"; // Add specific CSS for dashboard
require_once 'includes/header.php';

// Get team member role
$team_role_query = "SELECT tm.role, tm.id as team_member_id FROM team_members tm WHERE tm.user_id = ?";
$stmt = $conn->prepare($team_role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$team_role_result = $stmt->get_result();
$team_data = $team_role_result->fetch_assoc();
$team_role = $team_data['role'] ?? null;
$team_member_id = $team_data['team_member_id'] ?? null;
$stmt->close();

// Get unread message count
$unread_messages_query = "SELECT SUM(unread_count) as total_unread FROM (
    SELECT 
        (SELECT COUNT(m.id) 
        FROM messages m 
        LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
        WHERE m.conversation_id = c.id AND m.user_id != ? AND mrs.id IS NULL) as unread_count
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ? AND cp.left_at IS NULL
) as message_counts";
$stmt = $conn->prepare($unread_messages_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_messages_count = $unread_result->fetch_assoc()['total_unread'] ?? 0;
$stmt->close();

// Get pending document reviews count for team member
$pending_review_query = "SELECT COUNT(ad.id) as pending_review
                         FROM application_documents ad
                         JOIN applications a ON ad.application_id = a.id
                         JOIN application_assignments aa ON a.id = aa.application_id
                         WHERE aa.team_member_id = ?
                         AND aa.status = 'active'
                         AND ad.status = 'submitted'
                         AND a.deleted_at IS NULL";
$stmt = $conn->prepare($pending_review_query);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_review_count = $pending_result->fetch_assoc()['pending_review'] ?? 0;
$stmt->close();

// Get active applications count
$applications_query = "SELECT
                        COUNT(a.id) as total_applications,
                        SUM(CASE WHEN ast.name IN ('under_review', 'processing', 'additional_documents_requested') THEN 1 ELSE 0 END) as active_applications,
                        SUM(CASE WHEN a.priority = 'urgent' THEN 1 ELSE 0 END) as urgent_applications
                        FROM applications a
                        JOIN application_assignments aa ON a.id = aa.application_id
                        JOIN application_statuses ast ON a.status_id = ast.id
                        WHERE aa.team_member_id = ?
                        AND aa.status = 'active'
                        AND a.deleted_at IS NULL";
$stmt = $conn->prepare($applications_query);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$app_result = $stmt->get_result();
$applications = $app_result->fetch_assoc();
$total_applications = $applications['total_applications'] ?? 0;
$active_applications = $applications['active_applications'] ?? 0;
$urgent_applications = $applications['urgent_applications'] ?? 0;
$stmt->close();

// If user is a consultant (Immigration Assistant), get booking stats
$consultant_booking_stats = null;
$next_booking = null;
$upcoming_consultant_bookings = [];

if ($team_role == 'Immigration Assistant' && $team_member_id) {
    // Get total upcoming bookings
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings 
                           WHERE team_member_id = ? 
                           AND booking_datetime >= NOW() 
                           AND deleted_at IS NULL");
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_upcoming_bookings = $result->fetch_assoc()['total'];
    $stmt->close();

    // Get today's bookings
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $stmt = $conn->prepare("SELECT COUNT(*) as today FROM bookings 
                           WHERE team_member_id = ? 
                           AND booking_datetime BETWEEN ? AND ? 
                           AND deleted_at IS NULL");
    $stmt->bind_param("iss", $team_member_id, $today_start, $today_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $today_bookings = $result->fetch_assoc()['today'];
    $stmt->close();

    // Get this week's bookings
    $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
    $stmt = $conn->prepare("SELECT COUNT(*) as week FROM bookings 
                           WHERE team_member_id = ? 
                           AND booking_datetime BETWEEN ? AND ? 
                           AND deleted_at IS NULL");
    $stmt->bind_param("iss", $team_member_id, $week_start, $week_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $week_bookings = $result->fetch_assoc()['week'];
    $stmt->close();

    // Get completed bookings
    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM bookings b
                           JOIN booking_statuses bs ON b.status_id = bs.id
                           WHERE b.team_member_id = ? 
                           AND bs.name = 'completed'
                           AND b.deleted_at IS NULL");
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed_bookings = $result->fetch_assoc()['completed'];
    $stmt->close();

    $consultant_booking_stats = [
        'today' => $today_bookings,
        'week' => $week_bookings,
        'upcoming' => $total_upcoming_bookings,
        'completed' => $completed_bookings
    ];

    // Get next booking details
    $stmt = $conn->prepare("SELECT b.*, bs.name as status_name, bs.color as status_color,
                          CONCAT(u.first_name, ' ', u.last_name) as client_name,
                          st.service_name, cm.mode_name
                          FROM bookings b
                          JOIN booking_statuses bs ON b.status_id = bs.id
                          JOIN users u ON b.user_id = u.id
                          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
                          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                          JOIN service_types st ON vs.service_type_id = st.service_type_id
                          WHERE b.team_member_id = ?
                          AND b.booking_datetime >= NOW() 
                          AND bs.name IN ('pending', 'confirmed')
                          AND b.deleted_at IS NULL
                          ORDER BY b.booking_datetime
                          LIMIT 1");
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $next_booking_result = $stmt->get_result();
    $next_booking = $next_booking_result->num_rows > 0 ? $next_booking_result->fetch_assoc() : null;
    $stmt->close();

    // Get upcoming bookings for this consultant
    $stmt = $conn->prepare("SELECT b.id, b.reference_number, b.booking_datetime, bs.name as status_name, bs.color as status_color,
                          CONCAT(u.first_name, ' ', u.last_name) as client_name,
                          st.service_name, cm.mode_name
                          FROM bookings b
                          JOIN booking_statuses bs ON b.status_id = bs.id
                          JOIN users u ON b.user_id = u.id
                          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
                          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                          JOIN service_types st ON vs.service_type_id = st.service_type_id
                          WHERE b.team_member_id = ?
                          AND b.booking_datetime >= NOW() 
                          AND bs.name IN ('pending', 'confirmed')
                          AND b.deleted_at IS NULL
                          ORDER BY b.booking_datetime
                          LIMIT 5");
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $bookings_result = $stmt->get_result();
    while ($booking = $bookings_result->fetch_assoc()) {
        $upcoming_consultant_bookings[] = $booking;
    }
    $stmt->close();
}

// Get pending tasks count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM task_assignments 
                        WHERE team_member_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_tasks_result = $stmt->get_result();
$pending_tasks_count = $pending_tasks_result->fetch_assoc()['count'];
$stmt->close();

// Get in progress tasks count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM task_assignments 
                        WHERE team_member_id = ? AND status = 'in_progress'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$in_progress_tasks_result = $stmt->get_result();
$in_progress_tasks_count = $in_progress_tasks_result->fetch_assoc()['count'];
$stmt->close();

// Get completed tasks count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM task_assignments 
                        WHERE team_member_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_tasks_result = $stmt->get_result();
$completed_tasks_count = $completed_tasks_result->fetch_assoc()['count'];
$stmt->close();

// Get due today tasks
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT t.id, t.name, t.priority, t.due_date, ta.status 
                        FROM tasks t
                        JOIN task_assignments ta ON t.id = ta.task_id
                        WHERE ta.team_member_id = ? 
                        AND DATE(t.due_date) = ? 
                        AND ta.status NOT IN ('completed', 'cancelled')
                        ORDER BY t.priority DESC, t.due_date ASC
                        LIMIT 5");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$due_today_result = $stmt->get_result();
$due_today_tasks = [];
while ($row = $due_today_result->fetch_assoc()) {
    $due_today_tasks[] = $row;
}
$stmt->close();

// Get latest tasks
$stmt = $conn->prepare("SELECT t.id, t.name, t.priority, t.due_date, ta.status, ta.created_at 
                        FROM tasks t
                        JOIN task_assignments ta ON t.id = ta.task_id
                        WHERE ta.team_member_id = ? 
                        AND ta.status NOT IN ('completed', 'cancelled')
                        ORDER BY ta.created_at DESC
                        LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$latest_tasks_result = $stmt->get_result();
$latest_tasks = [];
while ($row = $latest_tasks_result->fetch_assoc()) {
    $latest_tasks[] = $row;
}
$stmt->close();

// Get upcoming tasks count by date (for calendar chart)
$next_30_days = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $next_30_days[$date] = ['date' => $date, 'count' => 0];
}

$stmt = $conn->prepare("SELECT DATE(t.due_date) as due_date, COUNT(*) as count
                        FROM tasks t
                        JOIN task_assignments ta ON t.id = ta.task_id
                        WHERE ta.team_member_id = ? 
                        AND ta.status NOT IN ('completed', 'cancelled')
                        AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(t.due_date)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_result = $stmt->get_result();
while ($row = $upcoming_result->fetch_assoc()) {
    if (isset($next_30_days[$row['due_date']])) {
        $next_30_days[$row['due_date']]['count'] = (int)$row['count'];
    }
}
$stmt->close();

// Format data for chart
$chart_labels = [];
$chart_data = [];
foreach ($next_30_days as $day) {
    $chart_labels[] = date('d M', strtotime($day['date']));
    $chart_data[] = $day['count'];
}

// Get pending tasks for the modal
$stmt = $conn->prepare("SELECT t.id, t.name, t.priority 
                        FROM tasks t
                        JOIN task_assignments ta ON t.id = ta.task_id
                        WHERE ta.team_member_id = ? 
                        AND ta.status = 'pending'
                        ORDER BY t.priority DESC, t.due_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_tasks_result = $stmt->get_result();
$pending_tasks = [];
while ($row = $pending_tasks_result->fetch_assoc()) {
    $pending_tasks[] = $row;
}
$stmt->close();
?>

<div class="content">
    <div class="dashboard-welcome">
        <div class="welcome-message">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h1>
            <p class="date-today"><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="quick-actions">
            <a href="tasks.php" class="action-btn primary">
                <i class="fas fa-tasks"></i> My Tasks
            </a>
            <a href="applications.php" class="action-btn secondary">
                <i class="fas fa-passport"></i> Applications
            </a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $pending_tasks_count + $in_progress_tasks_count; ?></h3>
                <p>Active Tasks</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $active_applications; ?></h3>
                <p>Active Applications</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $urgent_applications; ?></h3>
                <p>Urgent Applications</p>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $pending_review_count; ?></h3>
                <p>Documents To Review</p>
            </div>
        </div>
    </div>
    
    <?php if ($team_role == 'Immigration Assistant' && $consultant_booking_stats): ?>
    <!-- Consultant Bookings Stats -->
    <div class="stats-container">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $consultant_booking_stats['today']; ?></h3>
                <p>Today's Consultations</p>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $consultant_booking_stats['week']; ?></h3>
                <p>This Week</p>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $consultant_booking_stats['upcoming']; ?></h3>
                <p>Upcoming Bookings</p>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $consultant_booking_stats['completed']; ?></h3>
                <p>Completed Consultations</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Sections -->
    <div class="dashboard-sections">
        <!-- Due Today Tasks -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Due Today</h2>
                <a href="tasks.php?filter=today" class="view-all">View All</a>
            </div>
            <div class="section-content">
                <?php if (empty($due_today_tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>You don't have any tasks due today</p>
                    </div>
                <?php else: ?>
                    <div class="task-list">
                        <?php foreach ($due_today_tasks as $task): ?>
                            <div class="task-card priority-<?php echo strtolower($task['priority']); ?>">
                                <div class="task-header">
                                    <div class="task-title">
                                        <i class="fas fa-tasks"></i>
                                        <span><?php echo htmlspecialchars($task['name']); ?></span>
                                    </div>
                                    <div class="priority-badge <?php echo strtolower($task['priority']); ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </div>
                                </div>
                                <div class="task-details">
                                    <p class="due-date"><i class="fas fa-clock"></i> Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></p>
                                    <p class="status"><i class="fas fa-info-circle"></i> Status: <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?></p>
                                </div>
                                <a href="tasks.php?task_id=<?php echo $task['id']; ?>" class="btn-view">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($team_role == 'Immigration Assistant' && !empty($upcoming_consultant_bookings)): ?>
        <!-- Upcoming Bookings -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Upcoming Consultations</h2>
                <a href="bookings.php" class="view-all">View All</a>
            </div>
            <div class="section-content">
                <div class="booking-list">
                    <?php foreach ($upcoming_consultant_bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-date">
                                <div class="date-badge">
                                    <span class="month"><?php echo date('M', strtotime($booking['booking_datetime'])); ?></span>
                                    <span class="day"><?php echo date('d', strtotime($booking['booking_datetime'])); ?></span>
                                </div>
                                <div class="time"><?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?></div>
                            </div>
                            <div class="booking-details">
                                <h3><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                                <p class="consultation-mode">
                                    <i class="fas fa-video"></i> 
                                    <?php echo htmlspecialchars($booking['mode_name']); ?>
                                </p>
                                <p class="client-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($booking['client_name']); ?>
                                </p>
                                <div class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>10; color: <?php echo $booking['status_color']; ?>;">
                                    <?php echo ucfirst($booking['status_name']); ?>
                                </div>
                            </div>
                            <div class="booking-actions">
                                <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn-view">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Latest Tasks -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Latest Tasks</h2>
                <a href="tasks.php" class="view-all">View All</a>
            </div>
            <div class="section-content">
                <?php if (empty($latest_tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list"></i>
                        <p>You don't have any assigned tasks yet</p>
                    </div>
                <?php else: ?>
                    <div class="task-list">
                        <?php foreach ($latest_tasks as $task): ?>
                            <div class="task-card priority-<?php echo strtolower($task['priority']); ?>">
                                <div class="task-header">
                                    <div class="task-title">
                                        <i class="fas fa-tasks"></i>
                                        <span><?php echo htmlspecialchars($task['name']); ?></span>
                                    </div>
                                    <div class="priority-badge <?php echo strtolower($task['priority']); ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </div>
                                </div>
                                <div class="task-details">
                                    <?php if ($task['due_date']): ?>
                                        <p class="due-date"><i class="fas fa-clock"></i> Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></p>
                                    <?php else: ?>
                                        <p class="due-date"><i class="fas fa-clock"></i> No due date</p>
                                    <?php endif; ?>
                                    <p class="status"><i class="fas fa-info-circle"></i> Status: <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?></p>
                                </div>
                                <a href="tasks.php?task_id=<?php echo $task['id']; ?>" class="btn-view">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Links Section -->
    <div class="quick-links-section">
        <h2>Quick Links</h2>
        <div class="quick-links">
            <a href="tasks.php" class="quick-link-card" id="markTasksStartedBtn">
                <i class="fas fa-play"></i>
                <span>Start Tasks</span>
                <?php if ($pending_tasks_count > 0): ?>
                    <div class="badge"><?php echo $pending_tasks_count; ?></div>
                <?php endif; ?>
            </a>
            <a href="documents.php" class="quick-link-card">
                <i class="fas fa-file-alt"></i>
                <span>Documents</span>
                <?php if ($pending_review_count > 0): ?>
                    <div class="badge"><?php echo $pending_review_count; ?></div>
                <?php endif; ?>
            </a>
            <a href="messages.php" class="quick-link-card">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
                <?php if ($unread_messages_count > 0): ?>
                    <div class="badge"><?php echo $unread_messages_count; ?></div>
                <?php endif; ?>
            </a>
            <a href="calendar.php" class="quick-link-card">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </a>
            <?php if ($team_role == 'Immigration Assistant'): ?>
            <a href="availability.php" class="quick-link-card">
                <i class="fas fa-clock"></i>
                <span>Availability</span>
            </a>
            <?php else: ?>
            <a href="clients.php" class="quick-link-card">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upcoming Tasks Chart Section -->
    <div class="dashboard-section chart-section">
        <div class="section-header">
            <h2>Upcoming Tasks Timeline</h2>
            <a href="tasks.php?filter=upcoming" class="view-all">View All</a>
        </div>
        <div class="section-content">
            <div class="chart-container">
                <canvas id="upcomingTasksChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Mark Tasks Started Modal -->
<div class="modal" id="markTasksStartedModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mark Tasks as Started</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="tasks.php" method="POST" id="markTasksStartedForm">
                    <input type="hidden" name="action" value="mark_started">
                    
                    <div class="form-group">
                        <label>Select tasks to mark as started:</label>
                        <div class="task-selection-list">
                            <?php if (empty($pending_tasks)): ?>
                                <p>You don't have any pending tasks.</p>
                            <?php else: ?>
                                <?php foreach ($pending_tasks as $task): ?>
                                    <div class="task-selection-item">
                                        <input type="checkbox" name="task_ids[]" value="<?php echo $task['id']; ?>" id="task_<?php echo $task['id']; ?>" class="task-checkbox">
                                        <label for="task_<?php echo $task['id']; ?>" class="task-label">
                                            <?php echo htmlspecialchars($task['name']); ?>
                                            <span class="task-priority"><?php echo ucfirst($task['priority']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit" class="btn submit-btn">Mark as Started</button>
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
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    
    --urgent-color: #e74a3b;
    --high-color: #f6c23e;
    --normal-color: #4e73df;
    --low-color: #36b9cc
}

}

/* Welcome Section */
.dashboard-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.welcome-message h1 {
    font-size: 1.8rem;
    color: var(--primary-color);
    margin: 0;
}

.date-today {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 1rem;
}

.quick-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 10px 16px;
    border-radius: 4px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: all 0.2s;
}

.action-btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.action-btn.primary:hover {
    background-color: #031c56;
}

.action-btn.secondary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.action-btn.secondary:hover {
    background-color: var(--light-color);
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    border-left: 4px solid var(--primary-color);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-card.primary {
    border-left-color: var(--primary-color);
}

.stat-card.success {
    border-left-color: var(--success-color);
}

.stat-card.warning {
    border-left-color: var(--warning-color);
}

.stat-card.danger {
    border-left-color: var(--danger-color);
}

.stat-card.info {
    border-left-color: var(--info-color);
}

.stat-icon {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background-color: var(--light-color);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stat-card.primary .stat-icon {
    color: var(--primary-color);
}

.stat-card.success .stat-icon {
    color: var(--success-color);
}

.stat-card.warning .stat-icon {
    color: var(--warning-color);
}

.stat-card.danger .stat-icon {
    color: var(--danger-color);
}

.stat-card.info .stat-icon {
    color: var(--info-color);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-color);
}

.stat-details p {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

/* Dashboard Sections */
.dashboard-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.section-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
    font-weight: 600;
}

.view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
}

.view-all:hover {
    text-decoration: underline;
}

.section-content {
    padding: 20px;
    min-height: 320px;
}

/* Empty States */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.6;
}

.empty-state p {
    margin: 0 0 15px;
}

/* Task Cards */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.task-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.task-card.priority-high {
    border-left: 4px solid var(--urgent-color);
}

.task-card.priority-normal {
    border-left: 4px solid var(--high-color);
}

.task-card.priority-low {
    border-left: 4px solid var(--low-color);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.task-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--dark-color);
}

.priority-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.priority-badge.high {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--urgent-color);
}

.priority-badge.normal {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--high-color);
}

.priority-badge.low {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--low-color);
}

.task-details {
    margin-bottom: 15px;
}

.task-details p {
    margin: 5px 0;
    color: var(--secondary-color);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-view {
    display: inline-block;
    padding: 8px 12px;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-view:hover {
    background-color: #031c56;
}

/* Booking Cards */
.booking-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.booking-card {
    display: flex;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.booking-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.booking-date {
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    text-align: center;
    min-width: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 10px;
}

.date-badge .month {
    font-size: 14px;
    text-transform: uppercase;
}

.date-badge .day {
    font-size: 24px;
    font-weight: 700;
}

.time {
    font-size: 12px;
}

.booking-details {
    padding: 15px;
    flex: 1;
}

.booking-details h3 {
    margin: 0 0 8px;
    font-size: 16px;
    color: var(--dark-color);
}

.booking-details p {
    margin: 5px 0;
    color: var(--secondary-color);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    margin-top: 8px;
}

.booking-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 15px;
    border-left: 1px solid var(--border-color);
    justify-content: center;
}

/* Quick Links Section */
.quick-links-section {
    margin-bottom: 25px;
}

.quick-links-section h2 {
    margin: 0 0 15px;
    font-size: 1.1rem;
    color: var(--primary-color);
    font-weight: 600;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-link-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: var(--dark-color);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 120px;
    position: relative;
}

.quick-link-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    color: var(--primary-color);
}

.quick-link-card i {
    font-size: 24px;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: var(--danger-color);
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

/* Chart Section */
.chart-section {
    margin-bottom: 25px;
}

.chart-container {
    height: 300px;
    position: relative;
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
    width: 90%;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
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

.task-selection-list {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 10px;
}

.task-selection-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.task-selection-item:last-child {
    border-bottom: none;
}

.task-checkbox {
    margin-right: 10px;
}

.task-label {
    display: flex;
    justify-content: space-between;
    width: 100%;
    font-size: 0.9rem;
    margin: 0;
    cursor: pointer;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.cancel-btn {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.btn {
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s;
}

.btn:hover {
    opacity: 0.9;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .dashboard-sections {
        grid-template-columns: 1fr;
    }
    
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-links {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .dashboard-welcome {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .booking-card {
        flex-direction: column;
    }
    
    .booking-date {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
    }
    
    .date-badge {
        margin-bottom: 0;
        flex-direction: row;
        gap: 5px;
    }
    
    .booking-actions {
        flex-direction: row;
        border-left: none;
        border-top: 1px solid var(--border-color);
    }
    
    .quick-links {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .quick-links {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    const upcomingChart = document.getElementById('upcomingTasksChart');
    
    if (upcomingChart) {
        new Chart(upcomingChart, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Upcoming Tasks',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(4, 33, 103, 0.7)',
                    borderColor: 'rgba(4, 33, 103, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Modal functionality
    const markTasksStartedBtn = document.getElementById('markTasksStartedBtn');
    const markTasksStartedModal = document.getElementById('markTasksStartedModal');
    const closeModal = document.querySelector('.close');
    const cancelBtn = document.querySelector('.cancel-btn');
    
    // Open modal
    if (markTasksStartedBtn) {
        markTasksStartedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markTasksStartedModal.style.display = 'block';
        });
    }
    
    // Close modal with close button
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            markTasksStartedModal.style.display = 'none';
        });
    }
    
    // Close modal with cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            markTasksStartedModal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === markTasksStartedModal) {
            markTasksStartedModal.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>