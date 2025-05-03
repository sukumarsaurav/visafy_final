<?php
// Set page title
$page_title = "Dashboard";
require_once 'includes/header.php';

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
?>

<div class="content">
    <div class="welcome-section">
        <h1>Welcome to Member Dashboard, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h1>
        <p>Here's an overview of your tasks and activities.</p>
    </div>
    
    <!-- Status Cards -->
    <div class="stats-container">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-hourglass-start"></i>
            </div>
            <div class="stat-details">
                <h3>Pending Tasks</h3>
                <p class="stat-number"><?php echo $pending_tasks_count; ?></p>
            </div>
        </div>
        
        <div class="stat-card in-progress">
            <div class="stat-icon">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-details">
                <h3>In Progress</h3>
                <p class="stat-number"><?php echo $in_progress_tasks_count; ?></p>
            </div>
        </div>
        
        <div class="stat-card completed">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3>Completed</h3>
                <p class="stat-number"><?php echo $completed_tasks_count; ?></p>
            </div>
        </div>
        
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-details">
                <h3>Total Tasks</h3>
                <p class="stat-number"><?php echo $pending_tasks_count + $in_progress_tasks_count + $completed_tasks_count; ?></p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-sections">
        <!-- Left Column -->
        <div class="dashboard-column">
            <!-- Due Today Tasks -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-calendar-day"></i> Due Today</h2>
                    <a href="tasks.php?filter=today" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($due_today_tasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>You don't have any tasks due today.</p>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($due_today_tasks as $task): ?>
                                <div class="task-item priority-<?php echo strtolower($task['priority']); ?>">
                                    <div class="task-details">
                                        <h4 class="task-name"><?php echo htmlspecialchars($task['name']); ?></h4>
                                        <div class="task-meta">
                                            <span class="task-priority"><?php echo ucfirst($task['priority']); ?></span>
                                            <span class="task-status"><?php echo str_replace('_', ' ', ucfirst($task['status'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="task-actions">
                                        <a href="tasks.php?task_id=<?php echo $task['id']; ?>" class="btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Latest Tasks -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Latest Tasks</h2>
                    <a href="tasks.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($latest_tasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-list"></i>
                            <p>You don't have any assigned tasks yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($latest_tasks as $task): ?>
                                <div class="task-item priority-<?php echo strtolower($task['priority']); ?>">
                                    <div class="task-details">
                                        <h4 class="task-name"><?php echo htmlspecialchars($task['name']); ?></h4>
                                        <div class="task-meta">
                                            <span class="task-due">
                                                <?php if ($task['due_date']): ?>
                                                    Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                                <?php else: ?>
                                                    No due date
                                                <?php endif; ?>
                                            </span>
                                            <span class="task-status"><?php echo str_replace('_', ' ', ucfirst($task['status'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="task-actions">
                                        <a href="tasks.php?task_id=<?php echo $task['id']; ?>" class="btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="dashboard-column">
            <!-- Quick Actions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <button id="markTasksStartedBtn" class="action-btn">
                            <i class="fas fa-play"></i> Mark Tasks as Started
                        </button>
                        <button id="viewCalendarBtn" class="action-btn" onclick="window.location.href='calendar.php'">
                            <i class="fas fa-calendar-alt"></i> View Calendar
                        </button>
                        <button id="contactClientBtn" class="action-btn" onclick="window.location.href='messages.php'">
                            <i class="fas fa-comments"></i> Contact Clients
                        </button>
                        <button id="viewDocumentsBtn" class="action-btn" onclick="window.location.href='documents.php'">
                            <i class="fas fa-file-alt"></i> Manage Documents
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Tasks Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Upcoming Tasks</h2>
                    <a href="tasks.php?filter=upcoming" class="view-all">View All</a>
                </div>
                <div class="card-body chart-container">
                    <canvas id="upcomingTasksChart"></canvas>
                </div>
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

.welcome-section {
    margin-bottom: 20px;
}

.welcome-section h1 {
    color: var(--primary-color);
    margin: 0;
    font-size: 1.8rem;
}

.welcome-section p {
    color: var(--secondary-color);
    margin: 5px 0 0;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-right: 15px;
}

.stat-details h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--secondary-color);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 5px 0 0;
}

.stat-card.pending .stat-icon {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.stat-card.pending .stat-number {
    color: var(--warning-color);
}

.stat-card.in-progress .stat-icon {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.stat-card.in-progress .stat-number {
    color: var(--info-color);
}

.stat-card.completed .stat-icon {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.stat-card.completed .stat-number {
    color: var(--success-color);
}

.stat-card.total .stat-icon {
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
}

.stat-card.total .stat-number {
    color: var(--primary-color);
}

.dashboard-sections {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.dashboard-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.dashboard-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.card-header {
    background-color: var(--light-color);
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.view-all {
    font-size: 0.9rem;
    color: var(--primary-color);
    text-decoration: none;
}

.view-all:hover {
    text-decoration: underline;
}

.card-body {
    padding: 20px;
}

.chart-container {
    height: 300px;
}

.task-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.task-item {
    border-left: 4px solid var(--border-color);
    padding: 12px 15px;
    background-color: var(--light-color);
    border-radius: 0 4px 4px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.task-item.priority-high {
    border-left-color: var(--danger-color);
}

.task-item.priority-normal {
    border-left-color: var(--warning-color);
}

.task-item.priority-low {
    border-left-color: var(--info-color);
}

.task-details {
    flex: 1;
}

.task-name {
    margin: 0 0 5px;
    font-size: 1rem;
    color: var(--dark-color);
}

.task-meta {
    display: flex;
    gap: 10px;
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.task-priority, .task-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 500;
}

.task-priority {
    background-color: rgba(90, 92, 105, 0.1);
    color: var(--dark-color);
}

.task-status {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.task-due {
    color: var(--secondary-color);
}

.task-actions {
    display: flex;
    gap: 5px;
}

.btn-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border-radius: 4px;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background-color: var(--primary-color);
    color: white;
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

@media (max-width: 992px) {
    .dashboard-sections {
        grid-template-columns: 1fr;
    }
    
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-container {
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
        markTasksStartedBtn.addEventListener('click', function() {
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
