<?php
// Start output buffering at the very beginning of the file
ob_start();

// Set page title
$page_title = "My Tasks";
$page_specific_css = "assets/css/tasks.css";
require_once 'includes/header.php';

// Check for session success message
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it after use
}

// Handle task status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_started') {
        if (isset($_POST['task_ids']) && is_array($_POST['task_ids'])) {
            try {
                $conn->begin_transaction();
                
                foreach ($_POST['task_ids'] as $task_id) {
                    // Update task assignment status to in_progress
                    $stmt = $conn->prepare("UPDATE task_assignments 
                                            SET status = 'in_progress', started_at = NOW(), updated_at = NOW() 
                                            WHERE task_id = ? AND team_member_id = ?");
                    $stmt->bind_param("ii", $task_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Add activity log
                    $stmt = $conn->prepare("INSERT INTO task_activity_logs (task_id, user_id, activity_type, description, created_at) 
                                            VALUES (?, ?, 'member_status_changed', 'Task marked as in progress', NOW())");
                    $stmt->bind_param("ii", $task_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $conn->commit();
                $success_message = "Tasks successfully marked as in progress!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error updating tasks: " . $e->getMessage();
                error_log($error_message);
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mark_completed') {
        if (isset($_POST['task_id'])) {
            try {
                $task_id = $_POST['task_id'];
                $conn->begin_transaction();
                
                // Update task assignment status to completed
                $stmt = $conn->prepare("UPDATE task_assignments 
                                        SET status = 'completed', completed_at = NOW(), updated_at = NOW() 
                                        WHERE task_id = ? AND team_member_id = ?");
                $stmt->bind_param("ii", $task_id, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Add activity log
                $stmt = $conn->prepare("INSERT INTO task_activity_logs (task_id, user_id, activity_type, description, created_at) 
                                        VALUES (?, ?, 'member_status_changed', 'Task marked as completed', NOW())");
                $stmt->bind_param("ii", $task_id, $user_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $success_message = "Task successfully marked as completed!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error updating task: " . $e->getMessage();
                error_log($error_message);
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
        if (isset($_POST['task_id']) && isset($_POST['comment']) && !empty($_POST['comment'])) {
            try {
                $task_id = $_POST['task_id'];
                $comment_text = trim($_POST['comment']);
                
                // Insert new comment
                $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment, created_at) 
                                        VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iis", $task_id, $user_id, $comment_text);
                $stmt->execute();
                $stmt->close();
                
                // Add activity log
                $stmt = $conn->prepare("INSERT INTO task_activity_logs (task_id, user_id, activity_type, description, created_at) 
                                        VALUES (?, ?, 'commented', 'New comment added', NOW())");
                $stmt->bind_param("ii", $task_id, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Use POST-REDIRECT-GET pattern to prevent form resubmission on refresh
                $redirect_url = "tasks.php?task_id=" . $task_id;
                // Add filter parameter if it exists
                if (isset($_GET['filter'])) {
                    $redirect_url .= "&filter=" . urlencode($_GET['filter']);
                }
                
                // Set success message in session to display after redirect
                $_SESSION['success_message'] = "Comment added successfully!";
                
                // Redirect to the same page
                header("Location: " . $redirect_url);
                exit();
            } catch (Exception $e) {
                $error_message = "Error adding comment: " . $e->getMessage();
                error_log($error_message);
            }
        }
    }
}

// Get task details if task_id provided
$selected_task = null;
$task_comments = [];
$task_attachments = [];
$task_activity = [];

if (isset($_GET['task_id'])) {
    $task_id = $_GET['task_id'];
    
    // Get task details
    $stmt = $conn->prepare("SELECT t.*, a.first_name as admin_first_name, a.last_name as admin_last_name, 
                           ta.status as assignment_status, ta.started_at, ta.completed_at 
                           FROM tasks t 
                           JOIN users a ON t.admin_id = a.id
                           JOIN task_assignments ta ON t.id = ta.task_id
                           WHERE t.id = ? AND ta.team_member_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $selected_task = $result->fetch_assoc();
        
        // Get task comments
        $stmt = $conn->prepare("SELECT tc.*, u.first_name, u.last_name, u.user_type, u.profile_picture 
                               FROM task_comments tc 
                               JOIN users u ON tc.user_id = u.id 
                               WHERE tc.task_id = ? AND tc.deleted_at IS NULL 
                               ORDER BY tc.created_at ASC");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $comments_result = $stmt->get_result();
        
        while ($comment = $comments_result->fetch_assoc()) {
            $task_comments[] = $comment;
        }
        
        // Get task attachments
        $stmt = $conn->prepare("SELECT ta.*, u.first_name, u.last_name 
                               FROM task_attachments ta 
                               JOIN users u ON ta.user_id = u.id 
                               WHERE ta.task_id = ? 
                               ORDER BY ta.created_at DESC");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $attachments_result = $stmt->get_result();
        
        while ($attachment = $attachments_result->fetch_assoc()) {
            $task_attachments[] = $attachment;
        }
        
        // Get task activity logs
        $stmt = $conn->prepare("SELECT tal.*, u.first_name, u.last_name 
                               FROM task_activity_logs tal 
                               JOIN users u ON tal.user_id = u.id 
                               WHERE tal.task_id = ? 
                               ORDER BY tal.created_at DESC 
                               LIMIT 15");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $activity_result = $stmt->get_result();
        
        while ($activity = $activity_result->fetch_assoc()) {
            $task_activity[] = $activity;
        }
    } else {
        // Task not found or not assigned to this user
        $error_message = "Task not found or you don't have permission to view it.";
    }
    $stmt->close();
}

// Get all tasks assigned to current user based on filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query for all tasks
$query = "SELECT t.id, t.name, t.description, t.priority, t.due_date, 
          ta.status as assignment_status, ta.started_at, ta.completed_at,
          u.first_name as admin_first_name, u.last_name as admin_last_name
          FROM tasks t
          JOIN task_assignments ta ON t.id = ta.task_id
          JOIN users u ON t.admin_id = u.id
          WHERE ta.team_member_id = ? ";

// Add filters
$params = [$user_id];
$param_types = 'i';

if ($filter === 'pending') {
    $query .= "AND ta.status = 'pending' ";
} elseif ($filter === 'in_progress') {
    $query .= "AND ta.status = 'in_progress' ";
} elseif ($filter === 'completed') {
    $query .= "AND ta.status = 'completed' ";
} elseif ($filter === 'today') {
    $query .= "AND DATE(t.due_date) = CURDATE() AND ta.status NOT IN ('completed', 'cancelled') ";
} elseif ($filter === 'upcoming') {
    $query .= "AND t.due_date > CURDATE() AND ta.status NOT IN ('completed', 'cancelled') ";
} elseif ($filter === 'overdue') {
    $query .= "AND t.due_date < CURDATE() AND ta.status NOT IN ('completed', 'cancelled') ";
} else {
    // 'all' filter - exclude cancelled tasks
    $query .= "AND ta.status != 'cancelled' ";
}

// Add search term if provided
if (!empty($search)) {
    $query .= "AND (t.name LIKE ? OR t.description LIKE ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $param_types .= 'ss';
}

// Sort by priority and due date
$query .= "ORDER BY 
          CASE ta.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
          END,
          CASE t.priority
            WHEN 'high' THEN 1
            WHEN 'normal' THEN 2
            WHEN 'low' THEN 3
          END,
          CASE 
            WHEN t.due_date IS NULL THEN 1
            ELSE 0
          END,
          t.due_date ASC";

$stmt = $conn->prepare($query);

// Bind parameters
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];

while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// Count tasks by status
$counts = [
    'all' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'today' => 0,
    'upcoming' => 0,
    'overdue' => 0
];

// Get counts for filters
$stmt = $conn->prepare("SELECT ta.status, 
                       COUNT(*) as total, 
                       SUM(CASE WHEN DATE(t.due_date) = CURDATE() AND ta.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as today,
                       SUM(CASE WHEN t.due_date > CURDATE() AND ta.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as upcoming,
                       SUM(CASE WHEN t.due_date < CURDATE() AND ta.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue
                       FROM task_assignments ta 
                       JOIN tasks t ON ta.task_id = t.id
                       WHERE ta.team_member_id = ? AND ta.status != 'cancelled'
                       GROUP BY ta.status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counts_result = $stmt->get_result();

while ($row = $counts_result->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        $counts['pending'] = $row['total'];
    } elseif ($row['status'] === 'in_progress') {
        $counts['in_progress'] = $row['total'];
    } elseif ($row['status'] === 'completed') {
        $counts['completed'] = $row['total'];
    }
    
    $counts['today'] += $row['today'];
    $counts['upcoming'] += $row['upcoming'];
    $counts['overdue'] += $row['overdue'];
}

// Total count (excluding cancelled)
$counts['all'] = $counts['pending'] + $counts['in_progress'] + $counts['completed'];
$stmt->close();
?>

<div class="content">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($selected_task): ?>
        <!-- Task Detail View -->
        <div class="back-link">
            <a href="tasks.php<?php echo !empty($filter) ? '?filter=' . $filter : ''; ?>">
                <i class="fas fa-arrow-left"></i> Back to Task List
            </a>
        </div>
        
        <div class="task-detail-container">
            <div class="task-detail-main">
                <div class="task-detail-header">
                    <div class="task-detail-title">
                        <h1><?php echo htmlspecialchars($selected_task['name']); ?></h1>
                        <div class="task-badges">
                            <span class="badge badge-priority-<?php echo strtolower($selected_task['priority']); ?>">
                                <?php echo ucfirst($selected_task['priority']); ?> Priority
                            </span>
                            <span class="badge badge-status-<?php echo strtolower($selected_task['assignment_status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $selected_task['assignment_status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="task-detail-actions">
                        <?php if ($selected_task['assignment_status'] === 'pending'): ?>
                            <form method="post" class="task-action-form">
                                <input type="hidden" name="action" value="mark_started">
                                <input type="hidden" name="task_ids[]" value="<?php echo $selected_task['id']; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Start Task
                                </button>
                            </form>
                        <?php elseif ($selected_task['assignment_status'] === 'in_progress'): ?>
                            <form method="post" class="task-action-form">
                                <input type="hidden" name="action" value="mark_completed">
                                <input type="hidden" name="task_id" value="<?php echo $selected_task['id']; ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Mark as Completed
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="task-detail-info">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Due Date:</span>
                        <span class="info-value">
                            <?php if (!empty($selected_task['due_date'])): ?>
                                <?php
                                $due_date = new DateTime($selected_task['due_date']);
                                $today = new DateTime();
                                $interval = $today->diff($due_date);
                                $is_overdue = $due_date < $today && $selected_task['assignment_status'] !== 'completed';
                                
                                echo date('F j, Y', strtotime($selected_task['due_date']));
                                
                                if ($is_overdue) {
                                    echo ' <span class="overdue-label">Overdue by ' . $interval->days . ' day(s)</span>';
                                } elseif ($due_date > $today) {
                                    echo ' <span class="days-left">(' . $interval->days . ' day(s) left)</span>';
                                }
                                ?>
                            <?php else: ?>
                                No due date
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Assigned by:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($selected_task['admin_first_name'] . ' ' . $selected_task['admin_last_name']); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($selected_task['started_at'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-play-circle"></i> Started:</span>
                        <span class="info-value">
                            <?php echo date('F j, Y, g:i a', strtotime($selected_task['started_at'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($selected_task['completed_at'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-check-circle"></i> Completed:</span>
                        <span class="info-value">
                            <?php echo date('F j, Y, g:i a', strtotime($selected_task['completed_at'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="task-detail-description">
                    <h3>Description</h3>
                    <div class="description-content">
                        <?php if (!empty($selected_task['description'])): ?>
                            <?php echo nl2br(htmlspecialchars($selected_task['description'])); ?>
                        <?php else: ?>
                            <p class="no-content">No description provided.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="task-detail-comments">
                    <h3>Comments</h3>
                    
                    <div class="comments-list">
                        <?php if (empty($task_comments)): ?>
                            <p class="no-content">No comments yet.</p>
                        <?php else: ?>
                            <?php foreach ($task_comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-avatar">
                                        <?php if (!empty($comment['profile_picture'])): ?>
                                            <img src="../../uploads/profiles/<?php echo $comment['profile_picture']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <div class="initials">
                                                <?php echo substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-content">
                                        <div class="comment-header">
                                            <div class="comment-author">
                                                <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                                <span class="user-role">
                                                    <?php echo $comment['user_type'] === 'admin' ? 'Administrator' : ($comment['user_type'] === 'member' ? 'Team Member' : 'Client'); ?>
                                                </span>
                                            </div>
                                            <div class="comment-time">
                                                <?php echo date('M j, Y g:i a', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="comment-text">
                                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="add-comment">
                        <h4>Add Comment</h4>
                        <form method="post" class="comment-form">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="task_id" value="<?php echo $selected_task['id']; ?>">
                            <textarea name="comment" rows="3" placeholder="Write your comment here..." required></textarea>
                            <button type="submit" class="btn btn-primary">Submit Comment</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-sidebar">
                <?php if (!empty($task_attachments)): ?>
                <div class="sidebar-section">
                    <h3>Attachments</h3>
                    <div class="attachment-list">
                        <?php foreach ($task_attachments as $attachment): ?>
                            <div class="attachment-item">
                                <div class="attachment-icon">
                                    <?php 
                                    $file_ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                    $icon_class = 'fa-file';
                                    
                                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $icon_class = 'fa-file-image';
                                    } elseif (in_array($file_ext, ['pdf'])) {
                                        $icon_class = 'fa-file-pdf';
                                    } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                        $icon_class = 'fa-file-word';
                                    } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                        $icon_class = 'fa-file-excel';
                                    } elseif (in_array($file_ext, ['zip', 'rar'])) {
                                        $icon_class = 'fa-file-archive';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="attachment-details">
                                    <div class="attachment-name">
                                        <a href="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($attachment['file_name']); ?>
                                        </a>
                                    </div>
                                    <div class="attachment-meta">
                                        <span class="attachment-size">
                                            <?php echo round($attachment['file_size'] / 1024, 2); ?> KB
                                        </span>
                                        <span class="attachment-date">
                                            Uploaded on <?php echo date('M j, Y', strtotime($attachment['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="sidebar-section">
                    <h3>Activity History</h3>
                    <div class="activity-list">
                        <?php if (empty($task_activity)): ?>
                            <p class="no-content">No activity recorded yet.</p>
                        <?php else: ?>
                            <?php foreach ($task_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php 
                                        $icon_class = 'fa-info-circle';
                                        
                                        if ($activity['activity_type'] === 'created') {
                                            $icon_class = 'fa-plus-circle';
                                        } elseif ($activity['activity_type'] === 'updated') {
                                            $icon_class = 'fa-edit';
                                        } elseif ($activity['activity_type'] === 'status_changed' || $activity['activity_type'] === 'member_status_changed') {
                                            $icon_class = 'fa-exchange-alt';
                                        } elseif ($activity['activity_type'] === 'commented') {
                                            $icon_class = 'fa-comment';
                                        } elseif ($activity['activity_type'] === 'attachment_added') {
                                            $icon_class = 'fa-paperclip';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo date('M j, Y g:i a', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Task List View -->
        <div class="task-list-header">
            <h1>My Tasks</h1>
            
            <div class="task-filters">
                <a href="tasks.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All <span class="filter-count"><?php echo $counts['all']; ?></span>
                </a>
                <a href="tasks.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="filter-count"><?php echo $counts['pending']; ?></span>
                </a>
                <a href="tasks.php?filter=in_progress" class="filter-btn <?php echo $filter === 'in_progress' ? 'active' : ''; ?>">
                    In Progress <span class="filter-count"><?php echo $counts['in_progress']; ?></span>
                </a>
                <a href="tasks.php?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                    Completed <span class="filter-count"><?php echo $counts['completed']; ?></span>
                </a>
                <a href="tasks.php?filter=today" class="filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>">
                    Due Today <span class="filter-count"><?php echo $counts['today']; ?></span>
                </a>
                <a href="tasks.php?filter=overdue" class="filter-btn <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                    Overdue <span class="filter-count"><?php echo $counts['overdue']; ?></span>
                </a>
            </div>
            
            <div class="task-search">
                <form method="get" class="search-form">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <?php endif; ?>
                    <div class="search-input-group">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tasks...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <h3>No tasks found</h3>
                <p>
                    <?php
                    if (!empty($search)) {
                        echo "No tasks match your search criteria.";
                    } elseif ($filter === 'pending') {
                        echo "You don't have any pending tasks.";
                    } elseif ($filter === 'in_progress') {
                        echo "You don't have any tasks in progress.";
                    } elseif ($filter === 'completed') {
                        echo "You haven't completed any tasks yet.";
                    } elseif ($filter === 'today') {
                        echo "You don't have any tasks due today.";
                    } elseif ($filter === 'overdue') {
                        echo "You don't have any overdue tasks.";
                    } else {
                        echo "You don't have any tasks assigned to you yet.";
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card priority-<?php echo strtolower($task['priority']); ?> status-<?php echo strtolower($task['assignment_status']); ?>">
                        <div class="task-priority-indicator"></div>
                        <div class="task-card-content">
                            <div class="task-card-header">
                                <h3 class="task-name"><?php echo htmlspecialchars($task['name']); ?></h3>
                                
                                <div class="task-badges">
                                    <span class="badge badge-priority-<?php echo strtolower($task['priority']); ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                    <span class="badge badge-status-<?php echo strtolower($task['assignment_status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['assignment_status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="task-card-details">
                                <?php if (!empty($task['description'])): ?>
                                    <div class="task-description">
                                        <?php echo substr(htmlspecialchars($task['description']), 0, 100) . (strlen($task['description']) > 100 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="task-meta">
                                    <div class="task-meta-item">
                                        <i class="far fa-user"></i> 
                                        <?php echo htmlspecialchars($task['admin_first_name'] . ' ' . $task['admin_last_name']); ?>
                                    </div>
                                    
                                    <?php if (!empty($task['due_date'])): ?>
                                    <div class="task-meta-item task-due-date <?php echo strtotime($task['due_date']) < time() && $task['assignment_status'] !== 'completed' ? 'overdue' : ''; ?>">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php 
                                        $due_date = new DateTime($task['due_date']);
                                        $today = new DateTime();
                                        
                                        if ($due_date->format('Y-m-d') === $today->format('Y-m-d')) {
                                            echo 'Today';
                                        } elseif ($due_date->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d')) {
                                            echo 'Tomorrow';
                                        } else {
                                            echo date('M j, Y', strtotime($task['due_date']));
                                        }
                                        
                                        if (strtotime($task['due_date']) < time() && $task['assignment_status'] !== 'completed') {
                                            echo ' (Overdue)';
                                        }
                                        ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="task-meta-item">
                                        <i class="far fa-calendar-alt"></i> No due date
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="task-card-actions">
                                <a href="tasks.php?task_id=<?php echo $task['id']; ?><?php echo !empty($filter) ? '&filter=' . $filter : ''; ?>" class="btn btn-sm btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                
                                <?php if ($task['assignment_status'] === 'pending'): ?>
                                    <form method="post" class="task-action-form">
                                        <input type="hidden" name="action" value="mark_started">
                                        <input type="hidden" name="task_ids[]" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-start">
                                            <i class="fas fa-play"></i> Start
                                        </button>
                                    </form>
                                <?php elseif ($task['assignment_status'] === 'in_progress'): ?>
                                    <form method="post" class="task-action-form">
                                        <input type="hidden" name="action" value="mark_completed">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-complete">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
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
    
    --high-priority-color: #e74a3b;
    --normal-priority-color: #f6c23e;
    --low-priority-color: #36b9cc;
    
    --pending-status-color: #858796;
    --in-progress-status-color: #36b9cc;
    --completed-status-color: #1cc88a;
}

.content {
    padding: 20px;
}

/* Alert Styling */
.alert {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid var(--success-color);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid var(--danger-color);
    color: var(--danger-color);
}

/* Task List View Styling */
.task-list-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.task-list-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.task-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 15px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    color: var(--secondary-color);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.filter-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.filter-btn:hover {
    background-color: rgba(4, 33, 103, 0.1);
}

.filter-count {
    display: inline-block;
    background-color: rgba(0, 0, 0, 0.1);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.8rem;
    margin-left: 5px;
}

.filter-btn.active .filter-count {
    background-color: rgba(255, 255, 255, 0.2);
}

.task-search {
    margin-top: 10px;
}

.search-form {
    width: 100%;
}

.search-input-group {
    display: flex;
    max-width: 400px;
}

.search-input-group input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px 0 0 4px;
    outline: none;
}

.search-input-group button {
    padding: 8px 15px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
}

/* Empty State Styling */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.empty-state i {
    font-size: 3rem;
    color: var(--secondary-color);
    opacity: 0.5;
    margin-bottom: 15px;
}

.empty-state h3 {
    color: var(--dark-color);
    margin: 0 0 10px;
    font-size: 1.4rem;
}

.empty-state p {
    color: var(--secondary-color);
    margin: 0;
}

/* Task List Styling */
.task-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.task-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    display: flex;
    transition: transform 0.2s ease;
}

.task-card:hover {
    transform: translateY(-5px);
}

.task-priority-indicator {
    width: 6px;
    background-color: var(--normal-priority-color);
}

.task-card.priority-high .task-priority-indicator {
    background-color: var(--high-priority-color);
}

.task-card.priority-normal .task-priority-indicator {
    background-color: var(--normal-priority-color);
}

.task-card.priority-low .task-priority-indicator {
    background-color: var(--low-priority-color);
}

.task-card.status-completed {
    opacity: 0.7;
}

.task-card-content {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
}

.task-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.task-name {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.task-badges {
    display: flex;
    gap: 5px;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
}

.badge-priority-high {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--high-priority-color);
}

.badge-priority-normal {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--normal-priority-color);
}

.badge-priority-low {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--low-priority-color);
}

.badge-status-pending {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--pending-status-color);
}

.badge-status-in_progress {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--in-progress-status-color);
}

.badge-status-completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--completed-status-color);
}

.task-card-details {
    flex: 1;
    margin-bottom: 15px;
}

.task-description {
    color: var(--secondary-color);
    font-size: 0.9rem;
    margin-bottom: 10px;
    line-height: 1.4;
}

.task-meta {
    display: flex;
    justify-content: space-between;
    color: var(--secondary-color);
    font-size: 0.85rem;
}

.task-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.task-meta-item.overdue {
    color: var(--danger-color);
}

.task-card-actions {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

.btn-view {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    flex: 1;
}

.btn-view:hover {
    background-color: rgba(4, 33, 103, 0.1);
}

.btn-start {
    background-color: var(--info-color);
    color: white;
    flex: 1;
}

.btn-start:hover {
    background-color: #2ea7b9;
}

.btn-complete {
    background-color: var(--success-color);
    color: white;
    flex: 1;
}

.btn-complete:hover {
    background-color: #19b37b;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c5a;
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #19b37b;
}

.task-action-form {
    flex: 1;
}

/* Task Detail View Styling */
.back-link {
    margin-bottom: 20px;
}

.back-link a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
}

.back-link a:hover {
    text-decoration: underline;
}

.task-detail-container {
    display: grid;
    grid-template-columns: 3fr 1fr;
    gap: 20px;
}

.task-detail-main {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.task-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.task-detail-title {
    flex: 1;
}

.task-detail-title h1 {
    margin: 0 0 10px;
    font-size: 1.5rem;
    color: var(--dark-color);
}

.task-detail-actions {
    margin-left: 20px;
}

.task-detail-info {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    padding: 20px;
    background-color: var(--light-color);
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-label {
    font-size: 0.8rem;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 5px;
}

.info-value {
    font-size: 0.95rem;
    color: var(--dark-color);
}

.overdue-label {
    color: var(--danger-color);
    font-size: 0.85rem;
}

.days-left {
    color: var(--info-color);
    font-size: 0.85rem;
}

.task-detail-description, 
.task-detail-comments {
    padding: 20px;
}

.task-detail-description h3, 
.task-detail-comments h3,
.sidebar-section h3 {
    margin: 0 0 15px;
    color: var(--primary-color);
    font-size: 1.1rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.description-content {
    color: var(--dark-color);
    line-height: 1.6;
}

.no-content {
    color: var(--secondary-color);
    font-style: italic;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.comment-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.comment-content {
    flex: 1;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.comment-author {
    font-weight: 500;
    color: var(--dark-color);
}

.user-role {
    font-weight: normal;
    color: var(--secondary-color);
    font-size: 0.8rem;
    margin-left: 5px;
}

.comment-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.comment-text {
    color: var(--dark-color);
    line-height: 1.5;
}

.add-comment h4 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 1rem;
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.comment-form textarea {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 10px;
    resize: vertical;
    font-family: inherit;
    outline: none;
}

.comment-form textarea:focus {
    border-color: var(--primary-color);
}

.comment-form button {
    align-self: flex-end;
}

.task-detail-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    padding: 15px;
}

.attachment-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.attachment-item {
    display: flex;
    gap: 10px;
    padding: 10px;
    background-color: var(--light-color);
    border-radius: 4px;
}

.attachment-icon {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.attachment-details {
    flex: 1;
    overflow: hidden;
}

.attachment-name {
    margin-bottom: 5px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attachment-name a {
    color: var(--primary-color);
    text-decoration: none;
}

.attachment-name a:hover {
    text-decoration: underline;
}

.attachment-meta {
    font-size: 0.8rem;
    color: var(--secondary-color);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 10px;
    font-size: 0.9rem;
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    color: var(--primary-color);
    font-size: 1rem;
    padding-top: 3px;
}

.activity-details {
    flex: 1;
}

.activity-text {
    margin-bottom: 2px;
    color: var(--dark-color);
    line-height: 1.4;
}

.activity-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .task-detail-container {
        grid-template-columns: 1fr;
    }
    
    .task-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Highlight current filter
    const currentFilter = '<?php echo $filter; ?>';
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    filterBtns.forEach(btn => {
        const filterType = btn.getAttribute('href').split('=')[1];
        if (filterType === currentFilter) {
            btn.classList.add('active');
        }
    });
    
    // Auto resize textarea for comments
    const commentTextarea = document.querySelector('.comment-form textarea');
    if (commentTextarea) {
        commentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
