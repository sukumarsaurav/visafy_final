<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Task Details";
$page_specific_css = "assets/css/tasks.css";
require_once 'includes/header.php';

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to tasks list if no valid ID provided
    header("Location: tasks.php");
    exit;
}

$task_id = intval($_GET['id']);

// Get task details with prepared statement
$query = "SELECT t.*, 
          u.first_name as admin_first_name, u.last_name as admin_last_name, u.email as admin_email
          FROM tasks t 
          JOIN users u ON t.admin_id = u.id
          WHERE t.id = ? AND t.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Task not found or deleted
    header("Location: tasks.php?error=Task not found");
    exit;
}

$task = $result->fetch_assoc();
$stmt->close();

// Get task assignees
$assignees_query = "SELECT ta.id as assignment_id, ta.status as assignment_status, 
                    ta.started_at, ta.completed_at,
                    u.id as user_id, u.first_name, u.last_name, u.email, u.profile_picture,
                    tm.role, tm.custom_role_name, tm.id as team_member_id
                    FROM task_assignments ta
                    JOIN users u ON ta.team_member_id = u.id
                    JOIN team_members tm ON u.id = tm.user_id
                    WHERE ta.task_id = ? AND ta.deleted_at IS NULL
                    ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($assignees_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$assignees_result = $stmt->get_result();
$assignees = [];

while ($assignee = $assignees_result->fetch_assoc()) {
    $assignees[] = $assignee;
}
$stmt->close();

// Get task comments
$comments_query = "SELECT tc.*, u.first_name, u.last_name, u.user_type, u.profile_picture 
                  FROM task_comments tc 
                  JOIN users u ON tc.user_id = u.id 
                  WHERE tc.task_id = ? AND tc.deleted_at IS NULL 
                  ORDER BY tc.created_at ASC";
$stmt = $conn->prepare($comments_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$comments_result = $stmt->get_result();
$comments = [];

while ($comment = $comments_result->fetch_assoc()) {
    $comments[] = $comment;
}
$stmt->close();

// Get task attachments
$attachments_query = "SELECT ta.*, u.first_name, u.last_name 
                     FROM task_attachments ta 
                     JOIN users u ON ta.user_id = u.id 
                     WHERE ta.task_id = ? 
                     ORDER BY ta.created_at DESC";
$stmt = $conn->prepare($attachments_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$attachments_result = $stmt->get_result();
$attachments = [];

while ($attachment = $attachments_result->fetch_assoc()) {
    $attachments[] = $attachment;
}
$stmt->close();

// Get task activity logs
$activity_query = "SELECT tal.*, u.first_name, u.last_name, u.user_type
                  FROM task_activity_logs tal 
                  JOIN users u ON tal.user_id = u.id 
                  WHERE tal.task_id = ? 
                  ORDER BY tal.created_at DESC 
                  LIMIT 20";
$stmt = $conn->prepare($activity_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$activity_result = $stmt->get_result();
$activity_logs = [];

while ($log = $activity_result->fetch_assoc()) {
    $activity_logs[] = $log;
}
$stmt->close();

// Get all team members for assignment
$team_query = "SELECT tm.id, tm.role, tm.custom_role_name,
              u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture
              FROM team_members tm
              JOIN users u ON tm.user_id = u.id
              WHERE tm.deleted_at IS NULL AND u.status = 'active'
              ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($team_query);
$stmt->execute();
$team_result = $stmt->get_result();
$team_members = [];

while ($row = $team_result->fetch_assoc()) {
    $team_members[] = $row;
}
$stmt->close();

// Get all registered clients for assignment
$clients_query = "SELECT u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture
                 FROM users u
                 WHERE u.user_type = 'client' AND u.status = 'active'
                 ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($clients_query);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = [];

while ($row = $clients_result->fetch_assoc()) {
    $clients[] = $row;
}
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle task update
    if (isset($_POST['update_task'])) {
        $name = trim($_POST['task_name']);
        $description = trim($_POST['description']);
        $priority = $_POST['priority'];
        $status = $_POST['status'];
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        
        $errors = [];
        
        // Basic validation
        if (empty($name)) {
            $errors[] = "Task name is required";
        }
        
        if (empty($errors)) {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update task record
                $update_query = "UPDATE tasks SET name = ?, description = ?, priority = ?, status = ?, due_date = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('ssssi', $name, $description, $priority, $status, $due_date, $task_id);
                $stmt->execute();
                $stmt->close();
                
                // Create activity log
                $log_query = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                             VALUES (?, ?, 'updated', 'Task details updated')";
                $stmt = $conn->prepare($log_query);
                $stmt->bind_param('ii', $task_id, $_SESSION['id']);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Task updated successfully";
                header("Location: task_detail.php?id={$task_id}&success=1");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error updating task: " . $e->getMessage();
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle assign team member
    elseif (isset($_POST['assign_member'])) {
        $team_member_id = $_POST['team_member_id'];
        
        // Check if already assigned
        $check_query = "SELECT id FROM task_assignments WHERE task_id = ? AND team_member_id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('ii', $task_id, $team_member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Team member is already assigned to this task";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert assignment
                $assign_query = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
                $stmt = $conn->prepare($assign_query);
                $stmt->bind_param('ii', $task_id, $team_member_id);
                $stmt->execute();
                $stmt->close();
                
                // Create activity log
                $log_query = "INSERT INTO task_activity_logs (task_id, user_id, team_member_id, activity_type, description) 
                             VALUES (?, ?, ?, 'assigned', 'Team member assigned to task')";
                $stmt = $conn->prepare($log_query);
                $stmt->bind_param('iii', $task_id, $_SESSION['id'], $team_member_id);
                $stmt->execute();
                $stmt->close();
                
                // Create notification
                $notif_query = "INSERT INTO notifications (user_id, related_user_id, notification_type, title, content, related_to_type, related_to_id, is_actionable, action_url) 
                               VALUES (?, ?, 'task_assigned', 'New Task Assignment', ?, 'task', ?, 1, ?)";
                $notif_content = "You have been assigned to task: " . $task['name'];
                $action_url = "/dashboard/member/tasks.php?task_id=" . $task_id;
                
                $stmt = $conn->prepare($notif_query);
                $stmt->bind_param('iisss', $team_member_id, $_SESSION['id'], $notif_content, $task_id, $action_url);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Team member assigned successfully";
                header("Location: task_detail.php?id={$task_id}&success=2");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error assigning team member: " . $e->getMessage();
            }
        }
    }
    
    // Handle assign client (this is for the self-assignment feature)
    elseif (isset($_POST['assign_client'])) {
        $client_id = $_POST['client_id'];
        
        // Check if already assigned
        $check_query = "SELECT id FROM task_assignments WHERE task_id = ? AND team_member_id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('ii', $task_id, $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Client is already assigned to this task";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert assignment
                $assign_query = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
                $stmt = $conn->prepare($assign_query);
                $stmt->bind_param('ii', $task_id, $client_id);
                $stmt->execute();
                $stmt->close();
                
                // Create activity log
                $log_query = "INSERT INTO task_activity_logs (task_id, user_id, team_member_id, activity_type, description) 
                             VALUES (?, ?, ?, 'assigned', 'Client assigned to task')";
                $stmt = $conn->prepare($log_query);
                $stmt->bind_param('iii', $task_id, $_SESSION['id'], $client_id);
                $stmt->execute();
                $stmt->close();
                
                // Create notification
                $notif_query = "INSERT INTO notifications (user_id, related_user_id, notification_type, title, content, related_to_type, related_to_id, is_actionable, action_url) 
                               VALUES (?, ?, 'task_assigned', 'New Task Assignment', ?, 'task', ?, 1, ?)";
                $notif_content = "You have been assigned to task: " . $task['name'];
                $action_url = "/dashboard/client/tasks.php?task_id=" . $task_id;
                
                $stmt = $conn->prepare($notif_query);
                $stmt->bind_param('iisss', $client_id, $_SESSION['id'], $notif_content, $task_id, $action_url);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Client assigned successfully";
                header("Location: task_detail.php?id={$task_id}&success=3");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error assigning client: " . $e->getMessage();
            }
        }
    }
    
    // Handle self-assignment (admin assigning themselves)
    elseif (isset($_POST['self_assign'])) {
        // Check if admin is already assigned
        $admin_id = $_SESSION['id'];
        
        $check_query = "SELECT id FROM task_assignments WHERE task_id = ? AND team_member_id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('ii', $task_id, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "You are already assigned to this task";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert assignment
                $assign_query = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
                $stmt = $conn->prepare($assign_query);
                $stmt->bind_param('ii', $task_id, $admin_id);
                $stmt->execute();
                $stmt->close();
                
                // Create activity log
                $log_query = "INSERT INTO task_activity_logs (task_id, user_id, team_member_id, activity_type, description) 
                             VALUES (?, ?, ?, 'assigned', 'Admin self-assigned to task')";
                $stmt = $conn->prepare($log_query);
                $stmt->bind_param('iii', $task_id, $admin_id, $admin_id);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "You have been assigned to this task";
                header("Location: task_detail.php?id={$task_id}&success=4");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error self-assigning: " . $e->getMessage();
            }
        }
    }
    
    // Handle unassign member
    elseif (isset($_POST['unassign_member'])) {
        $assignment_id = $_POST['assignment_id'];
        $member_id = $_POST['member_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Mark assignment as deleted
            $unassign_query = "UPDATE task_assignments SET deleted_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($unassign_query);
            $stmt->bind_param('i', $assignment_id);
            $stmt->execute();
            $stmt->close();
            
            // Create activity log
            $log_query = "INSERT INTO task_activity_logs (task_id, user_id, team_member_id, activity_type, description) 
                         VALUES (?, ?, ?, 'unassigned', 'Member unassigned from task')";
            $stmt = $conn->prepare($log_query);
            $stmt->bind_param('iii', $task_id, $_SESSION['id'], $member_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Member unassigned successfully";
            header("Location: task_detail.php?id={$task_id}&success=5");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error unassigning member: " . $e->getMessage();
        }
    }
    
    // Handle add comment
    elseif (isset($_POST['add_comment'])) {
        $comment_text = trim($_POST['comment']);
        
        if (empty($comment_text)) {
            $error_message = "Comment cannot be empty";
        } else {
            // Insert comment
            $comment_query = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($comment_query);
            $stmt->bind_param('iis', $task_id, $_SESSION['id'], $comment_text);
            
            if ($stmt->execute()) {
                // Create activity log
                $log_query = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                             VALUES (?, ?, 'commented', 'New comment added')";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param('ii', $task_id, $_SESSION['id']);
                $log_stmt->execute();
                $log_stmt->close();
                
                $success_message = "Comment added successfully";
                header("Location: task_detail.php?id={$task_id}&success=6");
                exit;
            } else {
                $error_message = "Error adding comment: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // Handle file upload
    elseif (isset($_POST['upload_attachment'])) {
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $file = $_FILES['attachment'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file extensions
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
            
            // Maximum file size (5MB)
            $max_size = 5 * 1024 * 1024;
            
            if (in_array($file_ext, $allowed_extensions)) {
                if ($file_size <= $max_size) {
                    // Generate unique filename
                    $new_file_name = uniqid('task_' . $task_id . '_') . '.' . $file_ext;
                    
                    // Set upload path
                    $upload_dir = '../../uploads/task_attachments/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $upload_path = $upload_dir . $new_file_name;
                    $db_path = 'uploads/task_attachments/' . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Begin transaction
                        $conn->begin_transaction();
                        
                        try {
                            // Insert attachment record
                            $attach_query = "INSERT INTO task_attachments 
                                           (task_id, user_id, file_name, file_path, file_type, file_size) 
                                           VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($attach_query);
                            $stmt->bind_param('iisssi', $task_id, $_SESSION['id'], $file_name, $db_path, $file_ext, $file_size);
                            $stmt->execute();
                            $stmt->close();
                            
                            // Create activity log
                            $log_query = "INSERT INTO task_activity_logs 
                                         (task_id, user_id, activity_type, description) 
                                         VALUES (?, ?, 'attachment_added', ?)";
                            $description = "Attachment added: " . $file_name;
                            
                            $stmt = $conn->prepare($log_query);
                            $stmt->bind_param('iis', $task_id, $_SESSION['id'], $description);
                            $stmt->execute();
                            $stmt->close();
                            
                            // Commit transaction
                            $conn->commit();
                            
                            $success_message = "File uploaded successfully";
                            header("Location: task_detail.php?id={$task_id}&success=7");
                            exit;
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $error_message = "Error saving attachment: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "Error uploading file";
                    }
                } else {
                    $error_message = "File is too large (maximum 5MB)";
                }
            } else {
                $error_message = "Invalid file type";
            }
        } else {
            $error_message = "Please select a file to upload";
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Task updated successfully";
            break;
        case 2:
            $success_message = "Team member assigned successfully";
            break;
        case 3:
            $success_message = "Client assigned successfully";
            break;
        case 4:
            $success_message = "You have been assigned to this task";
            break;
        case 5:
            $success_message = "Member unassigned successfully";
            break;
        case 6:
            $success_message = "Comment added successfully";
            break;
        case 7:
            $success_message = "File uploaded successfully";
            break;
    }
}
?>

<div class="content">
    <div class="back-link">
        <a href="tasks.php">
            <i class="fas fa-arrow-left"></i> Back to Task List
        </a>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="task-detail-container">
        <div class="task-detail-main">
            <div class="task-detail-header">
                <div class="task-detail-title">
                    <h1><?php echo htmlspecialchars($task['name']); ?></h1>
                    <div class="task-badges">
                        <span class="badge badge-priority-<?php echo strtolower($task['priority']); ?>">
                            <?php echo ucfirst($task['priority']); ?> Priority
                        </span>
                        <span class="badge badge-status-<?php echo strtolower($task['status']); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="task-detail-actions">
                    <button type="button" class="btn btn-primary" onclick="openEditTaskModal()">
                        <i class="fas fa-edit"></i> Edit Task
                    </button>
                    <button type="button" class="btn btn-info" onclick="openAssignModal()">
                        <i class="fas fa-user-plus"></i> Assign
                    </button>
                    <form method="post" class="self-assign-form" style="display: inline;">
                        <button type="submit" name="self_assign" class="btn btn-outline-primary" title="Assign yourself to this task">
                            <i class="fas fa-hand-pointer"></i> Self-Assign
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="task-detail-info">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Due Date:</span>
                    <span class="info-value">
                        <?php if (!empty($task['due_date'])): ?>
                            <?php
                            $due_date = new DateTime($task['due_date']);
                            $today = new DateTime();
                            $interval = $today->diff($due_date);
                            $is_overdue = $due_date < $today && $task['status'] !== 'completed';
                            
                            echo date('F j, Y', strtotime($task['due_date']));
                            
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
                    <span class="info-label"><i class="fas fa-user"></i> Created by:</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars($task['admin_first_name'] . ' ' . $task['admin_last_name']); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-clock"></i> Created:</span>
                    <span class="info-value">
                        <?php echo date('F j, Y, g:i a', strtotime($task['created_at'])); ?>
                    </span>
                </div>
                
                <?php if (!empty($task['completed_at'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-check-circle"></i> Completed:</span>
                    <span class="info-value">
                        <?php echo date('F j, Y, g:i a', strtotime($task['completed_at'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="task-detail-description">
                <h3>Description</h3>
                <div class="description-content">
                    <?php if (!empty($task['description'])): ?>
                        <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                    <?php else: ?>
                        <p class="no-content">No description provided.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="task-assignees">
                <h3>Assigned Members</h3>
                <?php if (empty($assignees)): ?>
                    <p class="no-content">No members assigned to this task yet.</p>
                <?php else: ?>
                    <div class="assignees-list">
                        <?php foreach ($assignees as $assignee): ?>
                            <div class="assignee-card">
                                <div class="assignee-info">
                                    <div class="assignee-avatar">
                                        <?php if (!empty($assignee['profile_picture'])): ?>
                                            <img src="../../uploads/profiles/<?php echo $assignee['profile_picture']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <div class="initials">
                                                <?php echo substr($assignee['first_name'], 0, 1) . substr($assignee['last_name'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="assignee-details">
                                        <div class="assignee-name">
                                            <?php echo htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']); ?>
                                        </div>
                                        <div class="assignee-role">
                                            <?php 
                                            if (isset($assignee['role'])) {
                                                echo $assignee['role'] === 'Custom' ? $assignee['custom_role_name'] : $assignee['role'];
                                            } else {
                                                echo 'Client';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="assignee-status">
                                    <span class="status-badge status-<?php echo strtolower($assignee['assignment_status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $assignee['assignment_status'])); ?>
                                    </span>
                                </div>
                                <div class="assignee-actions">
                                    <form method="post" class="unassign-form" onsubmit="return confirm('Are you sure you want to unassign this member?');">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignee['assignment_id']; ?>">
                                        <input type="hidden" name="member_id" value="<?php echo $assignee['user_id']; ?>">
                                        <button type="submit" name="unassign_member" class="btn-action btn-unassign" title="Unassign">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="task-detail-comments">
                <h3>Comments</h3>
                
                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <p class="no-content">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
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
                        <input type="hidden" name="add_comment" value="1">
                        <textarea name="comment" rows="3" placeholder="Write your comment here..." required></textarea>
                        <button type="submit" class="btn btn-primary">Submit Comment</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="task-detail-sidebar">
            <div class="sidebar-section">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline-primary btn-block" onclick="openUploadModal()">
                        <i class="fas fa-paperclip"></i> Add Attachment
                    </button>
                </div>
            </div>
            
            <?php if (!empty($attachments)): ?>
            <div class="sidebar-section">
                <h3>Attachments</h3>
                <div class="attachment-list">
                    <?php foreach ($attachments as $attachment): ?>
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
                                        Uploaded by <?php echo htmlspecialchars($attachment['first_name'] . ' ' . $attachment['last_name']); ?>
                                    </span>
                                    <span class="attachment-date">
                                        on <?php echo date('M j, Y', strtotime($attachment['created_at'])); ?>
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
                    <?php if (empty($activity_logs)): ?>
                        <p class="no-content">No activity recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($activity_logs as $activity): ?>
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
                                    } elseif ($activity['activity_type'] === 'assigned') {
                                        $icon_class = 'fa-user-plus';
                                    } elseif ($activity['activity_type'] === 'unassigned') {
                                        $icon_class = 'fa-user-minus';
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
</div>

<!-- Edit Task Modal -->
<div class="modal" id="editTaskModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Task</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" id="editTaskForm">
                    <input type="hidden" name="update_task" value="1">
                    
                    <div class="form-group">
                        <label for="task_name">Task Name*</label>
                        <input type="text" name="task_name" id="task_name" class="form-control" value="<?php echo htmlspecialchars($task['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="priority">Priority*</label>
                            <select name="priority" id="priority" class="form-control" required>
                                <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="normal" <?php echo $task['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status*</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control" value="<?php echo !empty($task['due_date']) ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>">
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assign Member Modal -->
<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Task</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="assignTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#teamTab">Team Member</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#clientTab">Client</a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="teamTab">
                        <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" id="assignTeamForm">
                            <input type="hidden" name="assign_member" value="1">
                            
                            <div class="form-group">
                                <label for="team_member_id">Select Team Member*</label>
                                <select name="team_member_id" id="team_member_id" class="form-control" required>
                                    <option value="">-- Select Team Member --</option>
                                    <?php foreach ($team_members as $member): ?>
                                        <option value="<?php echo $member['user_id']; ?>">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                                            (<?php echo $member['role'] === 'Custom' ? $member['custom_role_name'] : $member['role']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn submit-btn">Assign Team Member</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="tab-pane fade" id="clientTab">
                        <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" id="assignClientForm">
                            <input type="hidden" name="assign_client" value="1">
                            
                            <div class="form-group">
                                <label for="client_id">Select Client*</label>
                                <select name="client_id" id="client_id" class="form-control" required>
                                    <option value="">-- Select Client --</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['user_id']; ?>">
                                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?> 
                                            (<?php echo $client['email']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn submit-btn">Assign Client</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Attachment Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Attachment</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="upload_attachment" value="1">
                    
                    <div class="form-group">
                        <label for="attachment">Select File*</label>
                        <input type="file" name="attachment" id="attachment" class="form-control-file" required>
                        <div class="upload-help">
                            <small>Allowed file types: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt, zip, rar</small>
                            <small>Maximum file size: 5MB</small>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn">Upload</button>
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
    
    --high-priority-color: #e74a3b;
    --normal-priority-color: #f6c23e;
    --low-priority-color: #36b9cc;
    
    --pending-status-color: #858796;
    --in-progress-status-color: #36b9cc;
    --completed-status-color: #1cc88a;
    --cancelled-status-color: #e74a3b;
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

/* Back Link */
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

/* Task Detail Styling */
.task-detail-container {
    display: grid;
    grid-template-columns: 3fr 1fr;
    gap: 20px;
}

.task-detail-main {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
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

.task-badges {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
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

.badge-status-cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--cancelled-status-color);
}

.task-detail-actions {
    display: flex;
    gap: 10px;
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
.task-assignees,
.task-detail-comments {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.task-detail-description h3, 
.task-assignees h3,
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

/* Assignees Styling */
.assignees-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.assignee-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background-color: var(--light-color);
    border-radius: 8px;
    gap: 10px;
}

.assignee-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.assignee-avatar {
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

.assignee-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.assignee-details {
    display: flex;
    flex-direction: column;
}

.assignee-name {
    font-weight: 500;
    color: var(--dark-color);
}

.assignee-role {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.assignee-status {
    margin-left: auto;
}

.assignee-actions {
    margin-left: 10px;
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
    color: white;
    transition: background-color 0.2s;
}

.btn-unassign {
    background-color: var(--danger-color);
}

.btn-unassign:hover {
    background-color: #d44235;
}

/* Comments Styling */
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

.initials {
    font-size: 1.2rem;
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

/* Sidebar Styling */
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

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-block {
    display: block;
    width: 100%;
}

.btn-outline-primary {
    color: var(--primary-color);
    background-color: transparent;
    border: 1px solid var(--primary-color);
}

.btn-outline-primary:hover {
    background-color: rgba(4, 33, 103, 0.05);
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

/* Modal Styling */
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
    margin: 60px auto;
    max-width: 500px;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
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
    font-size: 1.3rem;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.form-control-file {
    padding: 8px 0;
}

.upload-help {
    margin-top: 5px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: var(--secondary-color);
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
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

.submit-btn:hover {
    background-color: #031c56;
}

/* Status Badge Styling */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--pending-status-color);
}

.status-in_progress {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--in-progress-status-color);
}

.status-completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--completed-status-color);
}

.status-cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--cancelled-status-color);
}

/* Nav Tabs for Assign Modal */
.nav-tabs {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0 0 15px;
    border-bottom: 1px solid var(--border-color);
}

.nav-item {
    margin-bottom: -1px;
}

.nav-link {
    display: block;
    padding: 8px 15px;
    text-decoration: none;
    color: var(--secondary-color);
    font-weight: 500;
    border: 1px solid transparent;
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
}

.nav-link:hover {
    border-color: var(--border-color) var(--border-color) transparent;
    background-color: var(--light-color);
}

.nav-link.active {
    color: var(--primary-color);
    background-color: white;
    border-color: var(--border-color) var(--border-color) white;
}

.tab-content {
    padding-top: 15px;
}

.tab-pane {
    display: none;
}

.tab-pane.show.active {
    display: block;
}

/* Button Styling */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-info {
    background-color: var(--info-color);
    color: white;
}

.btn-info:hover {
    background-color: #2ea7b9;
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #19b37b;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .task-detail-container {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .assignees-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
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

// Open the edit task modal
function openEditTaskModal() {
    openModal('editTaskModal');
}

// Open the assign modal
function openAssignModal() {
    openModal('assignModal');
    // Reset active tab
    document.querySelector('#assignTabs .nav-link.active').classList.remove('active');
    document.querySelector('#assignTabs .nav-link').classList.add('active');
    document.querySelector('.tab-pane.active').classList.remove('active', 'show');
    document.querySelector('#teamTab').classList.add('active', 'show');
}

// Open the upload modal
function openUploadModal() {
    openModal('uploadModal');
}

// Tab functionality for assign modal
document.querySelectorAll('#assignTabs .nav-link').forEach(function(tab) {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        document.querySelectorAll('#assignTabs .nav-link').forEach(function(t) {
            t.classList.remove('active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Get target tab content
        const target = this.getAttribute('href').substring(1);
        
        // Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(function(pane) {
            pane.classList.remove('active', 'show');
        });
        
        // Show target tab pane
        document.getElementById(target).classList.add('active', 'show');
    });
});

// Auto resize textarea for comments
document.addEventListener('DOMContentLoaded', function() {
    const commentTextarea = document.querySelector('.comment-form textarea');
    if (commentTextarea) {
        commentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
