<?php
$page_title = "Admin Dashboard";
$page_specific_css = "assets/css/dashboard.css";
require_once 'includes/header.php';

// Get booking stats
$booking_stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN bs.name = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN bs.name = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN bs.name = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN bs.name LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM bookings b
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.deleted_at IS NULL";
$stmt = $conn->prepare($booking_stats_query);
$stmt->execute();
$booking_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get client stats
$client_stats_query = "SELECT 
    COUNT(*) as total_clients
    FROM users
    WHERE user_type = 'applicant' AND deleted_at IS NULL";
$stmt = $conn->prepare($client_stats_query);
$stmt->execute();
$client_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get team member stats
$team_stats_query = "SELECT 
    COUNT(*) as total_team_members
    FROM team_members
    WHERE deleted_at IS NULL";
$stmt = $conn->prepare($team_stats_query);
$stmt->execute();
$team_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get visa services stats
$services_stats_query = "SELECT 
    COUNT(*) as total_services
    FROM visa_services
    WHERE is_active = 1";
$stmt = $conn->prepare($services_stats_query);
$stmt->execute();
$services_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$my_tasks_query = "SELECT t.id, t.name, t.priority, t.status, t.due_date,
                  ta.status as assignment_status
                  FROM tasks t 
                  JOIN task_assignments ta ON t.id = ta.task_id
                  WHERE ta.team_member_id = ? AND t.deleted_at IS NULL AND ta.deleted_at IS NULL
                  AND ta.status IN ('pending', 'in_progress')
                  ORDER BY t.due_date ASC, t.priority DESC
                  LIMIT 5";
$stmt = $conn->prepare($my_tasks_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$my_tasks_result = $stmt->get_result();
$my_tasks = [];

if ($my_tasks_result && $my_tasks_result->num_rows > 0) {
    while ($row = $my_tasks_result->fetch_assoc()) {
        $my_tasks[] = $row;
    }
}
$stmt->close();

// Get tasks assigned to team members (excluding current user)
$team_tasks_query = "SELECT t.id, t.name, t.priority, t.status, t.due_date,
                   ta.status as assignment_status,
                   u.first_name, u.last_name
                   FROM tasks t 
                   JOIN task_assignments ta ON t.id = ta.task_id
                   JOIN users u ON ta.team_member_id = u.id
                   WHERE t.deleted_at IS NULL AND ta.deleted_at IS NULL
                   AND ta.team_member_id != ?
                   AND ta.status IN ('pending', 'in_progress')
                   ORDER BY t.due_date ASC, t.priority DESC
                   LIMIT 5";
$stmt = $conn->prepare($team_tasks_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$team_tasks_result = $stmt->get_result();
$team_tasks = [];

if ($team_tasks_result && $team_tasks_result->num_rows > 0) {
    while ($row = $team_tasks_result->fetch_assoc()) {
        $team_tasks[] = $row;
    }
}
$stmt->close();

// Get upcoming bookings (next 7 days)
$upcoming_bookings_query = "SELECT 
    b.id, b.reference_number, b.booking_datetime, bs.name as status_name, bs.color as status_color,
    CONCAT(u.first_name, ' ', u.last_name) as client_name,
    v.visa_type, st.service_name, cm.mode_name as consultation_mode,
    CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name
    FROM bookings b
    JOIN booking_statuses bs ON b.status_id = bs.id
    JOIN users u ON b.user_id = u.id
    JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
    JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
    JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
    JOIN visas v ON vs.visa_id = v.visa_id
    JOIN service_types st ON vs.service_type_id = st.service_type_id
    LEFT JOIN team_members tm ON b.team_member_id = tm.id
    LEFT JOIN users team_u ON tm.user_id = team_u.id
    WHERE b.deleted_at IS NULL
    AND bs.name IN ('pending', 'confirmed')
    AND b.booking_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY b.booking_datetime ASC
    LIMIT 5";
$stmt = $conn->prepare($upcoming_bookings_query);
$stmt->execute();
$upcoming_bookings_result = $stmt->get_result();
$upcoming_bookings = [];

if ($upcoming_bookings_result && $upcoming_bookings_result->num_rows > 0) {
    while ($row = $upcoming_bookings_result->fetch_assoc()) {
        $upcoming_bookings[] = $row;
    }
}
$stmt->close();

// Get recent client activity
$recent_activity_query = "SELECT 
    bal.created_at, bal.activity_type, bal.description,
    b.reference_number, b.id as booking_id,
    CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM booking_activity_logs bal
    JOIN bookings b ON bal.booking_id = b.id
    JOIN users u ON bal.user_id = u.id
    WHERE b.deleted_at IS NULL
    ORDER BY bal.created_at DESC
    LIMIT 8";
$stmt = $conn->prepare($recent_activity_query);
$stmt->execute();
$recent_activity_result = $stmt->get_result();
$recent_activities = [];

if ($recent_activity_result && $recent_activity_result->num_rows > 0) {
    while ($row = $recent_activity_result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
$stmt->close();

// Get bookings by month (for chart)
$monthly_bookings_query = "SELECT 
    DATE_FORMAT(booking_datetime, '%Y-%m') as month,
    COUNT(*) as booking_count
    FROM bookings
    WHERE deleted_at IS NULL
    AND booking_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(booking_datetime, '%Y-%m')
    ORDER BY month ASC";
$stmt = $conn->prepare($monthly_bookings_query);
$stmt->execute();
$monthly_bookings_result = $stmt->get_result();
$monthly_bookings = [];
$monthly_labels = [];
$monthly_data = [];

if ($monthly_bookings_result && $monthly_bookings_result->num_rows > 0) {
    while ($row = $monthly_bookings_result->fetch_assoc()) {
        // Format the month for display
        $date = new DateTime($row['month'] . '-01');
        $monthly_labels[] = $date->format('M Y');
        $monthly_data[] = $row['booking_count'];
    }
}
$stmt->close();

// Get booking status distribution (for chart)
$status_distribution_query = "SELECT 
    bs.name as status_name, bs.color,
    COUNT(*) as status_count
    FROM bookings b
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.deleted_at IS NULL
    GROUP BY bs.name, bs.color
    ORDER BY status_count DESC";
$stmt = $conn->prepare($status_distribution_query);
$stmt->execute();
$status_distribution_result = $stmt->get_result();
$status_labels = [];
$status_data = [];
$status_colors = [];

if ($status_distribution_result && $status_distribution_result->num_rows > 0) {
    while ($row = $status_distribution_result->fetch_assoc()) {
        $status_labels[] = ucfirst(str_replace('_', ' ', $row['status_name']));
        $status_data[] = $row['status_count'];
        $status_colors[] = $row['color'];
    }
}
$stmt->close();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <p>Overview of system metrics and activities</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon booking-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3>Total Bookings</h3>
                <div class="stat-number"><?php echo number_format($booking_stats['total_bookings']); ?></div>
                <div class="stat-detail">
                    <span class="pending"><?php echo number_format($booking_stats['pending_bookings']); ?>
                        Pending</span>
                    <span class="confirmed"><?php echo number_format($booking_stats['confirmed_bookings']); ?>
                        Confirmed</span>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon client-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Clients</h3>
                <div class="stat-number"><?php echo number_format($client_stats['total_clients']); ?></div>
                <div class="stat-detail">
                    <a href="clients.php" class="stat-link">View All Clients</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon team-icon">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-info">
                <h3>Team Members</h3>
                <div class="stat-number"><?php echo number_format($team_stats['total_team_members']); ?></div>
                <div class="stat-detail">
                    <a href="team.php" class="stat-link">Manage Team</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon service-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stat-info">
                <h3>Visa Services</h3>
                <div class="stat-number"><?php echo number_format($services_stats['total_services']); ?></div>
                <div class="stat-detail">
                    <a href="visa_services.php" class="stat-link">Manage Services</a>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Upcoming Bookings Section -->
        <div class="dashboard-section upcoming-bookings">
            <div class="section-header">
                <h2>Upcoming Bookings</h2>
                <a href="bookings.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($upcoming_bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No upcoming bookings for the next 7 days</p>
            </div>
            <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Client</th>
                        <th>Date & Time</th>
                        <th>Service</th>
                        <th>Consultant</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_bookings as $booking): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                        <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                        <td>
                            <?php 
                                        $date = new DateTime($booking['booking_datetime']);
                                        echo $date->format('M d, Y');
                                    ?>
                            <div class="time"><?php echo $date->format('h:i A'); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($booking['visa_type']); ?></div>
                            <div class="service-type"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($booking['consultant_name'])): ?>
                            <?php echo htmlspecialchars($booking['consultant_name']); ?>
                            <?php else: ?>
                            <span class="not-assigned">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge"
                                style="background-color: <?php echo $booking['status_color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view"
                                title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Activity Section -->
        <div class="dashboard-section recent-activity">
            <div class="section-header">
                <h2>Recent Activity</h2>
            </div>

            <?php if (empty($recent_activities)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No recent activities</p>
            </div>
            <?php else: ?>
            <div class="activity-feed">
                <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <?php switch ($activity['activity_type']) {
                                    case 'created':
                                        echo '<i class="fas fa-plus-circle"></i>';
                                        break;
                                    case 'updated':
                                        echo '<i class="fas fa-edit"></i>';
                                        break;
                                    case 'status_changed':
                                        echo '<i class="fas fa-exchange-alt"></i>';
                                        break;
                                    case 'assigned':
                                        echo '<i class="fas fa-user-plus"></i>';
                                        break;
                                    case 'cancelled':
                                        echo '<i class="fas fa-times-circle"></i>';
                                        break;
                                    case 'rescheduled':
                                        echo '<i class="fas fa-calendar-alt"></i>';
                                        break;
                                    case 'completed':
                                        echo '<i class="fas fa-check-circle"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-info-circle"></i>';
                                } ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-info">
                            <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                            <span
                                class="activity-action"><?php echo str_replace('_', ' ', $activity['activity_type']); ?></span>
                            <a href="view_booking.php?id=<?php echo $activity['booking_id']; ?>"
                                class="activity-reference">
                                Booking #<?php echo htmlspecialchars($activity['reference_number']); ?>
                            </a>
                        </div>
                        <div class="activity-description">
                            <?php echo htmlspecialchars($activity['description']); ?>
                        </div>
                        <div class="activity-time">
                            <?php 
                                        $activity_time = new DateTime($activity['created_at']);
                                        $now = new DateTime();
                                        $diff = $activity_time->diff($now);
                                        
                                        if ($diff->days > 0) {
                                            echo $diff->days . ' days ago';
                                        } elseif ($diff->h > 0) {
                                            echo $diff->h . ' hours ago';
                                        } elseif ($diff->i > 0) {
                                            echo $diff->i . ' minutes ago';
                                        } else {
                                            echo 'Just now';
                                        }
                                    ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="dashboard-grid">
        <!-- My Tasks Section -->
        <div class="dashboard-section my-tasks">
            <div class="section-header">
                <h2>My Tasks</h2>
                <a href="tasks.php?filter=my_tasks" class="btn-link">View All</a>
            </div>

            <?php if (empty($my_tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>No tasks assigned to you</p>
            </div>
            <?php else: ?>
            <div class="task-cards">
                <?php foreach ($my_tasks as $task): ?>
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-priority <?php echo strtolower($task['priority']); ?>">
                            <?php echo ucfirst($task['priority']); ?></div>
                        <div class="task-status <?php echo str_replace('_', '-', $task['assignment_status']); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $task['assignment_status'])); ?>
                        </div>
                    </div>
                    <div class="task-name">
                        <a
                            href="task_detail.php?id=<?php echo $task['id']; ?>"><?php echo htmlspecialchars($task['name']); ?></a>
                    </div>
                    <?php if (!empty($task['due_date'])): ?>
                    <div class="task-due-date">
                        <i class="far fa-calendar-alt"></i>
                        <?php 
                                        $due_date = new DateTime($task['due_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($due_date);
                                        $overdue = $due_date < $today;
                                        
                                        echo $due_date->format('M d, Y');
                                        
                                        if ($overdue) {
                                            echo ' <span class="overdue">(' . $interval->days . ' days overdue)</span>';
                                        } elseif ($interval->days == 0) {
                                            echo ' <span class="due-today">(Today)</span>';
                                        } elseif ($interval->days == 1) {
                                            echo ' <span class="due-soon">(Tomorrow)</span>';
                                        } elseif ($interval->days <= 3) {
                                            echo ' <span class="due-soon">(' . $interval->days . ' days left)</span>';
                                        }
                                    ?>
                    </div>
                    <?php endif; ?>
                    <div class="task-actions">
                        <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="btn-task-action">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($task['assignment_status'] == 'pending'): ?>
                        <a href="tasks.php?action=start&id=<?php echo $task['id']; ?>" class="btn-task-action start">
                            <i class="fas fa-play"></i> Start
                        </a>
                        <?php elseif ($task['assignment_status'] == 'in_progress'): ?>
                        <a href="tasks.php?action=complete&id=<?php echo $task['id']; ?>"
                            class="btn-task-action complete">
                            <i class="fas fa-check"></i> Complete
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Team Tasks Section -->
        <div class="dashboard-section team-tasks">
            <div class="section-header">
                <h2>Team Tasks</h2>
                <a href="tasks.php?filter=team_tasks" class="btn-link">View All</a>
            </div>

            <?php if (empty($team_tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <p>No tasks assigned to team members</p>
            </div>
            <?php else: ?>
            <div class="task-list">
                <?php foreach ($team_tasks as $task): ?>
                <div class="team-task-item">
                    <div class="team-task-info">
                        <div class="task-priority-badge <?php echo strtolower($task['priority']); ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </div>
                        <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="task-name">
                            <?php echo htmlspecialchars($task['name']); ?>
                        </a>
                        <div class="task-assignee">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                        </div>
                    </div>
                    <div class="team-task-status">
                        <div class="status-badge <?php echo str_replace('_', '-', $task['assignment_status']); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $task['assignment_status'])); ?>
                        </div>
                        <?php if (!empty($task['due_date'])): ?>
                        <div class="due-date">
                            <?php 
                                $due_date = new DateTime($task['due_date']);
                                echo $due_date->format('M d, Y'); 
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Charts Section -->
<div class="dashboard-charts">
    <div class="chart-container">
        <div class="chart-header">
            <h3>Bookings Over Time</h3>
        </div>
        <div class="chart-body">
            <canvas id="bookingsChart"></canvas>
        </div>
    </div>

    <div class="chart-container">
        <div class="chart-header">
            <h3>Booking Status Distribution</h3>
        </div>
        <div class="chart-body">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="quick-actions">
    <div class="section-header">
        <h2>Quick Actions</h2>
    </div>
    <div class="actions-grid">
        <a href="create_booking.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="action-title">Create Booking</div>
            <div class="action-description">Schedule a new consultation</div>
        </a>

        <a href="tasks.php?tab=create" class="action-card">
            <div class="action-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="action-title">Create Task</div>
            <div class="action-description">Assign new tasks to team</div>
        </a>

        <a href="messaging.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="action-title">Messages</div>
            <div class="action-description">View and send client messages</div>
        </a>

        <a href="reports.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-title">Reports</div>
            <div class="action-description">Generate business reports</div>
        </a>
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
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    max-height: 200px;
    overflow-y: auto;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.booking-icon {
    background-color: var(--primary-color);
}

.client-icon {
    background-color: var(--info-color);
}

.team-icon {
    background-color: var(--success-color);
}

.service-icon {
    background-color: var(--warning-color);
}

.stat-info {
    flex: 1;
}

.stat-info h3 {
    margin: 0 0 5px 0;
    color: var(--secondary-color);
    font-size: 0.85rem;
    font-weight: 600;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.stat-detail {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.8rem;
}

.pending {
    color: var(--warning-color);
}

.confirmed {
    color: var(--success-color);
}

.stat-link {
    color: var(--primary-color);
    text-decoration: none;
}

.stat-link:hover {
    text-decoration: underline;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.btn-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.btn-link:hover {
    text-decoration: underline;
}

/* Tables */
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    text-align: left;
    padding: 10px;
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.85rem;
}

.dashboard-table td {
    padding: 10px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.time,
.service-type {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.not-assigned {
    font-style: italic;
    color: var(--secondary-color);
    font-size: 0.85rem;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

/* Activity Feed */
.activity-feed {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 500px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-info {
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.activity-user {
    font-weight: 600;
    color: var(--dark-color);
}

.activity-action {
    color: var(--secondary-color);
}

.activity-reference {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.activity-description {
    font-size: 0.85rem;
    color: var(--dark-color);
    margin-bottom: 5px;
    line-height: 1.4;
}

.activity-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Charts */
.dashboard-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.chart-header {
    margin-bottom: 15px;
}

.chart-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.chart-body {
    height: 300px;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 30px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.action-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    text-decoration: none;
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    max-height: 250px;
    overflow-y: auto;
}

.action-card:hover {
    transform: translateY(-5px);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 24px;
    margin-bottom: 15px;
}

.action-title {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 1rem;
}

.action-description {
    color: var(--secondary-color);
    font-size: 0.85rem;
}

/* Add scrollbar styling for action cards */
.action-card::-webkit-scrollbar {
    width: 6px;
}

.action-card::-webkit-scrollbar-track {
    background: white;
    border-radius: 8px;
}

.action-card::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.action-card::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

/* Task Cards Styling */
.task-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.task-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    background-color: var(--light-color);
    transition: transform 0.2s, box-shadow 0.2s;
    max-height: 300px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* Add scrollbar styling */
.task-card::-webkit-scrollbar {
    width: 6px;
}

.task-card::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.task-card::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.task-card::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.task-priority {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
}

.task-priority.high {
    background-color: rgba(231, 74, 59, 0.15);
    color: var(--danger-color);
}

.task-priority.medium {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-priority.low {
    background-color: rgba(28, 200, 138, 0.15);
    color: var(--success-color);
}

.task-status {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
}

.task-status.pending {
    background-color: rgba(54, 185, 204, 0.15);
    color: var(--info-color);
}

.task-status.in-progress {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-name {
    margin-bottom: 10px;
    font-weight: 600;
}

.task-name a {
    color: var(--dark-color);
    text-decoration: none;
}

.task-name a:hover {
    color: var(--primary-color);
}

.task-due-date {
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-bottom: 15px;
}

.task-due-date i {
    margin-right: 5px;
}

.overdue {
    color: var(--danger-color);
    font-weight: 500;
}

.due-today {
    color: var(--warning-color);
    font-weight: 500;
}

.due-soon {
    color: var(--info-color);
    font-weight: 500;
}

.task-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
}

.btn-task-action {
    font-size: 0.8rem;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: white;
    background-color: var(--primary-color);
}

.btn-task-action:hover {
    opacity: 0.9;
}

.btn-task-action.start {
    background-color: var(--warning-color);
}

.btn-task-action.complete {
    background-color: var(--success-color);
}

/* Team Tasks List Styling */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

/* Add scrollbar styling for task list */
.task-list::-webkit-scrollbar {
    width: 6px;
}

.task-list::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.task-list::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.task-list::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.team-task-item {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    border-radius: 8px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
    max-height: 150px;
    overflow-y: auto;
}

/* Add scrollbar styling for team task items */
.team-task-item::-webkit-scrollbar {
    width: 6px;
}

.team-task-item::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.team-task-item::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.team-task-item::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.team-task-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.task-priority-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 8px;
    display: inline-block;
    margin-bottom: 5px;
}

.task-priority-badge.high {
    background-color: rgba(231, 74, 59, 0.15);
    color: var(--danger-color);
}

.task-priority-badge.medium {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-priority-badge.low {
    background-color: rgba(28, 200, 138, 0.15);
    color: var(--success-color);
}

.task-assignee {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.task-assignee i {
    margin-right: 5px;
}

.team-task-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.due-date {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-charts {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }

    .actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Bookings Chart
const bookingsCtx = document.getElementById('bookingsChart').getContext('2d');
const bookingsChart = new Chart(bookingsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
            label: 'Bookings',
            data: <?php echo json_encode($monthly_data); ?>,
            backgroundColor: 'rgba(4, 33, 103, 0.1)',
            borderColor: '#042167',
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($status_data); ?>,
            backgroundColor: <?php echo json_encode($status_colors); ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        },
        cutout: '60%'
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>