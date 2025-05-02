<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Task Management";
$page_specific_css = "assets/css/tasks.css";
require_once 'includes/header.php';

// Get all tasks - Using prepared statement
$query = "SELECT t.id, t.name, t.description, t.priority, t.status as task_status, 
          t.due_date, t.completed_at, t.created_at,
          u.id as admin_id, u.first_name as admin_first_name, u.last_name as admin_last_name
          FROM tasks t
          JOIN users u ON t.admin_id = u.id
          WHERE t.deleted_at IS NULL
          ORDER BY t.due_date ASC, t.priority DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Add task to array
        $tasks[$row['id']] = $row;
        
        // Initialize empty array for assignees
        $tasks[$row['id']]['assignees'] = [];
    }
}
$stmt->close();

// Get assignees for each task
if (!empty($tasks)) {
    $task_ids = array_keys($tasks);
    $task_ids_string = implode(',', $task_ids);
    
    $assignee_query = "SELECT ta.task_id, ta.status as assignee_status, ta.started_at, ta.completed_at,
                      u.id as user_id, u.first_name, u.last_name, u.email, u.profile_picture,
                      tm.role, tm.custom_role_name
                      FROM task_assignments ta
                      JOIN users u ON ta.team_member_id = u.id
                      JOIN team_members tm ON u.id = tm.user_id
                      WHERE ta.task_id IN ($task_ids_string)
                      ORDER BY u.first_name, u.last_name";
    
    $assignee_stmt = $conn->prepare($assignee_query);
    $assignee_stmt->execute();
    $assignee_result = $assignee_stmt->get_result();
    
    if ($assignee_result && $assignee_result->num_rows > 0) {
        while ($row = $assignee_result->fetch_assoc()) {
            $tasks[$row['task_id']]['assignees'][] = $row;
        }
    }
    $assignee_stmt->close();
}

// Get all team members for assignment
$team_query = "SELECT tm.id, tm.role, tm.custom_role_name,
               u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture
               FROM team_members tm
               JOIN users u ON tm.user_id = u.id
               WHERE tm.deleted_at IS NULL AND u.status = 'active'
               ORDER BY u.first_name, u.last_name";
$team_stmt = $conn->prepare($team_query);
$team_stmt->execute();
$team_result = $team_stmt->get_result();
$team_members = [];

if ($team_result && $team_result->num_rows > 0) {
    while ($row = $team_result->fetch_assoc()) {
        $team_members[] = $row;
    }
}
$team_stmt->close();

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $name = isset($_POST['task_name']) ? trim($_POST['task_name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';
    $due_date = isset($_POST['due_date']) && !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $assignees = isset($_POST['assignees']) ? $_POST['assignees'] : [];
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Task name is required";
    }
    
    // Check if user is logged in - using $_SESSION["id"] instead of $_SESSION['user_id']
    if (!isset($_SESSION["id"]) || empty($_SESSION["id"])) {
        $errors[] = "You need to be logged in to create a task";
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get admin ID from session - using $_SESSION["id"] instead of $_SESSION['user_id']
            $admin_id = $_SESSION["id"];
            
            // Insert task record
            $task_insert = "INSERT INTO tasks (name, description, priority, admin_id, due_date) 
                          VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($task_insert);
            $stmt->bind_param('sssis', $name, $description, $priority, $admin_id, $due_date);
            $stmt->execute();
            
            $task_id = $conn->insert_id;
            $stmt->close();
            
            // Insert task assignments
            if (!empty($assignees)) {
                $assignment_insert = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
                $stmt = $conn->prepare($assignment_insert);
                
                foreach ($assignees as $assignee_id) {
                    $stmt->bind_param('ii', $task_id, $assignee_id);
                    $stmt->execute();
                    
                    // Create activity log for assignment - use $_SESSION["id"] here too
                    $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, team_member_id, activity_type, description) 
                                 VALUES (?, ?, ?, 'assigned', 'Task assigned to team member')";
                    $log_stmt = $conn->prepare($log_insert);
                    $log_stmt->bind_param('iii', $task_id, $_SESSION["id"], $assignee_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    // Create notification for assignee - use $_SESSION["id"] here too
                    $notif_insert = "INSERT INTO notifications (user_id, related_user_id, notification_type, title, content, related_to_type, related_to_id, is_actionable, action_url) 
                                   VALUES (?, ?, 'task_assigned', 'New Task Assignment', ?, 'task', ?, 1, ?)";
                    $notif_content = "You have been assigned to task: " . $name;
                    $action_url = "/dashboard/admin/task_detail.php?id=" . $task_id;
                    
                    $notif_stmt = $conn->prepare($notif_insert);
                    $notif_stmt->bind_param('iisss', $assignee_id, $_SESSION["id"], $notif_content, $task_id, $action_url);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
                $stmt->close();
            }
            
            // Create activity log for task creation - use $_SESSION["id"] here too
            $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'created', 'Task created')";
            $log_stmt = $conn->prepare($log_insert);
            $log_stmt->bind_param('ii', $task_id, $_SESSION["id"]);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Task created successfully";
            header("Location: tasks.php?success=1");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error creating task: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['new_status'];
    
    // Update task status
    $update_query = "UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?";
    $completed_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ssi', $new_status, $completed_at, $task_id);
    
    if ($stmt->execute()) {
        // Create activity log
        $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'status_changed', ?)";
        $description = "Task status changed to " . $new_status;
        
        $log_stmt = $conn->prepare($log_insert);
        $log_stmt->bind_param('iis', $task_id, $_SESSION["id"], $description);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success_message = "Task status updated successfully";
        $stmt->close();
        header("Location: tasks.php?success=2");
        exit;
    } else {
        $error_message = "Error updating task status: " . $conn->error;
        $stmt->close();
    }
}

// Handle task deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $task_id = $_POST['task_id'];
    
    // Soft delete the task
    $delete_query = "UPDATE tasks SET deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $task_id);
    
    if ($stmt->execute()) {
        $success_message = "Task deleted successfully";
        $stmt->close();
        header("Location: tasks.php?success=3");
        exit;
    } else {
        $error_message = "Error deleting task: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Task created successfully";
            break;
        case 2:
            $success_message = "Task status updated successfully";
            break;
        case 3:
            $success_message = "Task deleted successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Task Management</h1>
            <p>Create, assign, and track tasks for your team members.</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="createTaskBtn">
                <i class="fas fa-plus"></i> Create Task
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Task Filters -->
    <div class="task-filters">
        <div class="filter-group">
            <label for="priority-filter">Priority:</label>
            <select id="priority-filter" class="filter-control">
                <option value="all">All Priorities</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-control">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="due-date-filter">Due Date:</label>
            <select id="due-date-filter" class="filter-control">
                <option value="all">All Dates</option>
                <option value="today">Today</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="this_week">This Week</option>
                <option value="next_week">Next Week</option>
                <option value="overdue">Overdue</option>
            </select>
        </div>
    </div>
    
    <!-- Tasks Table Section -->
    <div class="tasks-table-container">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>No tasks yet. Create a task to get started!</p>
            </div>
        <?php else: ?>
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assignees</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr class="task-row" 
                            data-priority="<?php echo $task['priority']; ?>" 
                            data-status="<?php echo $task['task_status']; ?>"
                            data-due-date="<?php echo $task['due_date']; ?>">
                            <td class="task-name-cell">
                                <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="task-name">
                                    <?php echo htmlspecialchars($task['name']); ?>
                                </a>
                                <div class="task-description">
                                    <?php echo !empty($task['description']) ? substr(htmlspecialchars($task['description']), 0, 80) . (strlen($task['description']) > 80 ? '...' : '') : ''; ?>
                                </div>
                            </td>
                            <td class="assignees-cell">
                                <?php if (empty($task['assignees'])): ?>
                                    <span class="no-assignees">Not assigned</span>
                                <?php else: ?>
                                    <div class="assignee-avatars">
                                        <?php foreach (array_slice($task['assignees'], 0, 3) as $index => $assignee): ?>
                                            <div class="assignee-avatar" title="<?php echo htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']); ?>">
                                                <?php if (!empty($assignee['profile_picture']) && file_exists('../../uploads/profiles/' . $assignee['profile_picture'])): ?>
                                                    <img src="../../uploads/profiles/<?php echo $assignee['profile_picture']; ?>" alt="Profile picture">
                                                <?php else: ?>
                                                    <div class="initials">
                                                        <?php echo substr($assignee['first_name'], 0, 1) . substr($assignee['last_name'], 0, 1); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($task['assignees']) > 3): ?>
                                            <div class="assignee-avatar more-assignees">
                                                <span>+<?php echo count($task['assignees']) - 3; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $priority_class = 'priority-badge ' . $task['priority']; ?>
                                <span class="<?php echo $priority_class; ?>">
                                    <?php echo ucfirst($task['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <?php $status_class = 'status-badge ' . $task['task_status']; ?>
                                <span class="<?php echo $status_class; ?>">
                                    <?php 
                                        $status_display = str_replace('_', ' ', $task['task_status']);
                                        echo ucwords($status_display);
                                    ?>
                                </span>
                            </td>
                            <td class="due-date-cell">
                                <?php if (!empty($task['due_date'])): ?>
                                    <?php 
                                        $due_date = new DateTime($task['due_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($due_date);
                                        $is_overdue = $due_date < $today && $task['task_status'] !== 'completed';
                                        $date_class = $is_overdue ? 'overdue' : '';
                                    ?>
                                    <span class="due-date <?php echo $date_class; ?>">
                                        <?php echo $due_date->format('M d, Y'); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="overdue-tag">Overdue</span>
                                        <?php elseif ($interval->days == 0): ?>
                                            <span class="today-tag">Today</span>
                                        <?php elseif ($interval->days == 1 && $due_date > $today): ?>
                                            <span class="tomorrow-tag">Tomorrow</span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-due-date">No due date</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn-action btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($task['task_status'] === 'pending'): ?>
                                    <button type="button" class="btn-action btn-start" 
                                            title="Start" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php elseif ($task['task_status'] === 'in_progress'): ?>
                                    <button type="button" class="btn-action btn-complete" 
                                            title="Complete" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'completed')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php elseif ($task['task_status'] === 'completed'): ?>
                                    <button type="button" class="btn-action btn-reopen" 
                                            title="Reopen" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn-action btn-delete" 
                                        title="Delete" onclick="confirmDeleteTask(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal" id="createTaskModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Task</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="tasks.php" method="POST" id="createTaskForm">
                    <div class="form-group">
                        <label for="task_name">Task Name*</label>
                        <input type="text" name="task_name" id="task_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="priority">Priority*</label>
                            <select name="priority" id="priority" class="form-control" required>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" name="due_date" id="due_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="assignees">Assign To</label>
                        <select name="assignees[]" id="assignees" class="form-control" multiple>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['user_id']; ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . 
                                        ($member['role'] === 'Custom' ? $member['custom_role_name'] : $member['role']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple team members</small>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_task" class="btn submit-btn">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="statusUpdateForm" action="tasks.php" method="POST" style="display: none;">
    <input type="hidden" name="task_id" id="status_task_id">
    <input type="hidden" name="new_status" id="new_task_status">
    <input type="hidden" name="update_task_status" value="1">
</form>

<form id="deleteTaskForm" action="tasks.php" method="POST" style="display: none;">
    <input type="hidden" name="task_id" id="delete_task_id">
    <input type="hidden" name="delete_task" value="1">
</form>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
}

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

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.task-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 120px;
}

.tasks-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.tasks-table {
    width: 100%;
    border-collapse: collapse;
}

.tasks-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.tasks-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.tasks-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.tasks-table tbody tr:last-child td {
    border-bottom: none;
}

.task-name-cell {
    width: 30%;
}

.task-name {
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    display: block;
    margin-bottom: 5px;
}

.task-description {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.assignees-cell {
    width: 15%;
}

.assignee-avatars {
    display: flex;
    flex-wrap: wrap;
}

.assignee-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: -8px;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    position: relative;
    overflow: hidden;
}

.assignee-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.assignee-avatar.more-assignees {
    background-color: var(--secondary-color);
    color: white;
    font-size: 12px;
    font-weight: 600;
}

.no-assignees {
    color: var(--secondary-color);
    font-size: 0.85rem;
    font-style: italic;
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.priority-badge.high {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.priority-badge.normal {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.priority-badge.low {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.in_progress {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.status-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.cancelled {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.due-date-cell {
    width: 15%;
}

.due-date {
    display: block;
    color: var(--dark-color);
}

.due-date.overdue {
    color: var(--danger-color);
}

.overdue-tag, .today-tag, .tomorrow-tag {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 5px;
}

.overdue-tag {
    background-color: var(--danger-color);
    color: white;
}

.today-tag {
    background-color: var(--warning-color);
    color: white;
}

.tomorrow-tag {
    background-color: var(--primary-color);
    color: white;
}

.no-due-date {
    color: var(--secondary-color);
    font-style: italic;
    font-size: 0.85rem;
}

.actions-cell {
    width: 10%;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 12px;
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

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
}

.btn-start {
    background-color: #4e73df;
}

.btn-start:hover {
    background-color: #4262c3;
}

.btn-complete {
    background-color: var(--success-color);
}

.btn-complete:hover {
    background-color: #18b07b;
}

.btn-reopen {
    background-color: var(--warning-color);
}

.btn-reopen:hover {
    background-color: #e0b137;
}

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
    background-color: #d44235;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
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
    max-width: 600px;
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

textarea.form-control {
    resize: vertical;
    min-height: 80px;
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .task-filters {
        flex-direction: column;
        gap: 10px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .tasks-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
// Modal functionality
// Open modal when Create Task button is clicked
document.getElementById('createTaskBtn').addEventListener('click', function() {
    document.getElementById('createTaskModal').style.display = 'block';
});

// Close modal when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        document.getElementById('createTaskModal').style.display = 'none';
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    const modal = document.getElementById('createTaskModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Task filtering functionality
function filterTasks() {
    const priorityFilter = document.getElementById('priority-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    const dueDateFilter = document.getElementById('due-date-filter').value;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    document.querySelectorAll('.task-row').forEach(function(row) {
        const priority = row.getAttribute('data-priority');
        const status = row.getAttribute('data-status');
        const dueDate = row.getAttribute('data-due-date');
        
        let showRow = true;
        
        // Priority filter
        if (priorityFilter !== 'all' && priorityFilter !== priority) {
            showRow = false;
        }
        
        // Status filter
        if (statusFilter !== 'all' && statusFilter !== status) {
            showRow = false;
        }
        
        // Due date filter
        if (dueDateFilter !== 'all' && dueDate) {
            const taskDueDate = new Date(dueDate);
            taskDueDate.setHours(0, 0, 0, 0);
            
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const nextWeekStart = new Date(today);
            nextWeekStart.setDate(nextWeekStart.getDate() - nextWeekStart.getDay() + 7);
            
            const nextWeekEnd = new Date(nextWeekStart);
            nextWeekEnd.setDate(nextWeekEnd.getDate() + 6);
            
            const thisWeekStart = new Date(today);
            thisWeekStart.setDate(thisWeekStart.getDate() - thisWeekStart.getDay());
            
            const thisWeekEnd = new Date(thisWeekStart);
            thisWeekEnd.setDate(thisWeekEnd.getDate() + 6);
            
            switch (dueDateFilter) {
                case 'today':
                    if (taskDueDate.getTime() !== today.getTime()) {
                        showRow = false;
                    }
                    break;
                case 'tomorrow':
                    if (taskDueDate.getTime() !== tomorrow.getTime()) {
                        showRow = false;
                    }
                    break;
                case 'this_week':
                    if (taskDueDate < thisWeekStart || taskDueDate > thisWeekEnd) {
                        showRow = false;
                    }
                    break;
                case 'next_week':
                    if (taskDueDate < nextWeekStart || taskDueDate > nextWeekEnd) {
                        showRow = false;
                    }
                    break;
                case 'overdue':
                    if (taskDueDate >= today || status === 'completed') {
                        showRow = false;
                    }
                    break;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

// Add event listeners for filters
document.getElementById('priority-filter').addEventListener('change', filterTasks);
document.getElementById('status-filter').addEventListener('change', filterTasks);
document.getElementById('due-date-filter').addEventListener('change', filterTasks);

// Function to update task status
function updateTaskStatus(taskId, newStatus) {
    document.getElementById('status_task_id').value = taskId;
    document.getElementById('new_task_status').value = newStatus;
    document.getElementById('statusUpdateForm').submit();
}

// Function to confirm task deletion
function confirmDeleteTask(taskId) {
    if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        document.getElementById('delete_task_id').value = taskId;
        document.getElementById('deleteTaskForm').submit();
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
