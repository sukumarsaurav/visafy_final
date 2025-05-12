<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Email Management | Admin Dashboard";
$page_specific_css = "assets/css/email-management.css";
// We'll add JavaScript directly in the page instead of using this
// $page_specific_js = "assets/js/email-management.js";
include('includes/header.php');

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle sending bulk email
if (isset($_POST['send_bulk_email'])) {
    $template_id = (int)$_POST['bulk_template_id'];
    $recipient_type = $_POST['recipient_type'];
    $subject = trim($_POST['bulk_subject']);
    $content = $_POST['bulk_content'];
    
    if (empty($subject) || empty($content)) {
        $error_message = "Subject and content are required";
    } else {
        // Get recipients based on type
        $recipients = [];
        
        switch ($recipient_type) {
            case 'all_clients':
                $stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM users WHERE user_type = 'applicant' AND status = 'active'");
                break;
            case 'all_members':
                $stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM users WHERE user_type = 'member' AND status = 'active'");
                break;
            case 'pending_applications':
                $stmt = $conn->prepare("SELECT DISTINCT u.id, u.email, u.first_name, u.last_name 
                    FROM users u 
                    JOIN applications a ON u.id = a.user_id 
                    JOIN application_statuses s ON a.status_id = s.id 
                    WHERE s.name IN ('draft', 'submitted', 'under_review', 'processing') 
                    AND u.status = 'active'");
                break;
            case 'upcoming_bookings':
                $stmt = $conn->prepare("SELECT DISTINCT u.id, u.email, u.first_name, u.last_name 
                    FROM users u 
                    JOIN bookings b ON u.id = b.user_id 
                    JOIN booking_statuses bs ON b.status_id = bs.id 
                    WHERE bs.name = 'confirmed' 
                    AND b.booking_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) 
                    AND u.status = 'active'");
                break;
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $recipients[] = [
                'id' => $row['id'],
                'email' => $row['email'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name']
            ];
        }
        
        // Insert into email_queue for each recipient
        if (!empty($recipients)) {
            $insert_stmt = $conn->prepare("INSERT INTO email_queue 
                (recipient_id, recipient_email, subject, content, status, scheduled_time, created_by) 
                VALUES (?, ?, ?, ?, 'pending', NOW(), ?)");
                
            $success_count = 0;
            foreach ($recipients as $recipient) {
                // Replace placeholders in content
                $personalized_content = str_replace(
                    ['{first_name}', '{last_name}', '{full_name}', '{email}'], 
                    [$recipient['first_name'], $recipient['last_name'], $recipient['first_name'] . ' ' . $recipient['last_name'], $recipient['email']], 
                    $content
                );
                
                $personalized_subject = str_replace(
                    ['{first_name}', '{last_name}', '{full_name}', '{email}'], 
                    [$recipient['first_name'], $recipient['last_name'], $recipient['first_name'] . ' ' . $recipient['last_name'], $recipient['email']], 
                    $subject
                );
                
                $insert_stmt->bind_param("isssi", $recipient['id'], $recipient['email'], $personalized_subject, $personalized_content, $_SESSION['id']);
                
                if ($insert_stmt->execute()) {
                    $success_count++;
                }
            }
            
            if ($success_count > 0) {
                $success_message = "$success_count emails have been queued for sending";
            } else {
                $error_message = "No emails were queued";
            }
        } else {
            $error_message = "No recipients found matching the selected criteria";
        }
    }
}

// Handle sending individual email
if (isset($_POST['send_email'])) {
    $recipient_email = trim($_POST['recipient_email']);
    $subject = trim($_POST['subject']);
    $content = $_POST['content'];
    $cc_emails = isset($_POST['cc_emails']) ? trim($_POST['cc_emails']) : '';
    $bcc_emails = isset($_POST['bcc_emails']) ? trim($_POST['bcc_emails']) : '';
    $scheduled_time = isset($_POST['scheduled_time']) ? trim($_POST['scheduled_time']) : '';
    
    if (empty($recipient_email) || empty($subject) || empty($content)) {
        $error_message = "Recipient email, subject, and content are required";
    } else if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid recipient email address";
    } else {
        // Validate CC emails if provided
        $cc_valid = true;
        $cc_list = [];
        if (!empty($cc_emails)) {
            $cc_array = explode(',', $cc_emails);
            foreach ($cc_array as $cc) {
                $cc = trim($cc);
                if (!empty($cc) && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $cc_valid = false;
                    break;
                }
                if (!empty($cc)) {
                    $cc_list[] = $cc;
                }
            }
        }
        
        // Validate BCC emails if provided
        $bcc_valid = true;
        $bcc_list = [];
        if (!empty($bcc_emails)) {
            $bcc_array = explode(',', $bcc_emails);
            foreach ($bcc_array as $bcc) {
                $bcc = trim($bcc);
                if (!empty($bcc) && !filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                    $bcc_valid = false;
                    break;
                }
                if (!empty($bcc)) {
                    $bcc_list[] = $bcc;
                }
            }
        }
        
        if (!$cc_valid) {
            $error_message = "One or more CC email addresses are invalid";
        } else if (!$bcc_valid) {
            $error_message = "One or more BCC email addresses are invalid";
        } else {
            // Get recipient ID if exists in our system
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $recipient_email);
            $stmt->execute();
            $result = $stmt->get_result();
            $recipient_id = ($result->num_rows > 0) ? $result->fetch_assoc()['id'] : null;
            
            // Determine scheduled time
            $final_scheduled_time = 'NOW()';
            if (!empty($scheduled_time)) {
                $scheduled_datetime = new DateTime($scheduled_time);
                $final_scheduled_time = "'" . $scheduled_datetime->format('Y-m-d H:i:s') . "'";
            }
            
            // Check if cc_emails and bcc_emails columns exist in the email_queue table
            $check_columns = $conn->query("SHOW COLUMNS FROM email_queue LIKE 'cc_emails'");
            $cc_column_exists = $check_columns->num_rows > 0;
            
            // If CC/BCC columns exist in the database, use them
            if ($cc_column_exists) {
                // Store CC and BCC as JSON
                $cc_json = !empty($cc_list) ? json_encode($cc_list) : null;
                $bcc_json = !empty($bcc_list) ? json_encode($bcc_list) : null;
                
                // Insert into email_queue with CC and BCC columns
                $stmt = $conn->prepare("INSERT INTO email_queue 
                    (recipient_id, recipient_email, subject, content, cc_emails, bcc_emails, status, scheduled_time, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', " . $final_scheduled_time . ", ?)");
                $stmt->bind_param("isssssi", $recipient_id, $recipient_email, $subject, $content, $cc_json, $bcc_json, $_SESSION['id']);
            } else {
                // If CC/BCC columns don't exist, add the CC/BCC information to the content
                if (!empty($cc_list) || !empty($bcc_list)) {
                    $header_info = "<div style='background-color: #f9f9f9; padding: 10px; margin-bottom: 15px; border: 1px solid #e3e3e3; border-radius: 4px;'>";
                    
                    if (!empty($cc_list)) {
                        $header_info .= "<div><strong>CC:</strong> " . htmlspecialchars(implode(", ", $cc_list)) . "</div>";
                    }
                    
                    if (!empty($bcc_list)) {
                        $header_info .= "<div><strong>BCC:</strong> " . htmlspecialchars(implode(", ", $bcc_list)) . "</div>";
                    }
                    
                    $header_info .= "</div>";
                    
                    // Add the header information to the content
                    $content = $header_info . $content;
                }
                
                // Insert into email_queue without CC and BCC columns
                $stmt = $conn->prepare("INSERT INTO email_queue 
                    (recipient_id, recipient_email, subject, content, status, scheduled_time, created_by) 
                    VALUES (?, ?, ?, ?, 'pending', " . $final_scheduled_time . ", ?)");
                $stmt->bind_param("isssi", $recipient_id, $recipient_email, $subject, $content, $_SESSION['id']);
            }
            
            if ($stmt->execute()) {
                $success_message = "Email has been queued for sending" . (!empty($scheduled_time) ? " and scheduled for $scheduled_time" : "");
                
                // Clear form fields after successful submission
                $_POST = array();
            } else {
                $error_message = "Error queueing email: " . $conn->error;
            }
        }
    }
}

// Get all email templates
$templates = [];
$stmt = $conn->prepare("SELECT id, name, subject, content, template_type, created_at FROM email_templates ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

// Get all received emails (last 20)
$received_emails = [];
$stmt = $conn->prepare("
    SELECT e.id, e.sender_email, e.subject, e.content, e.received_at, 
           CONCAT(u.first_name, ' ', u.last_name) AS sender_name
    FROM received_emails e
    LEFT JOIN users u ON e.sender_id = u.id
    ORDER BY e.received_at DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $received_emails[] = $row;
}

// Get all sent/queued emails (last 20)
$sent_emails = [];
$stmt = $conn->prepare("
    SELECT e.id, e.recipient_email, e.subject, e.status, e.scheduled_time, e.sent_at, 
           CONCAT(u.first_name, ' ', u.last_name) AS recipient_name
    FROM email_queue e
    LEFT JOIN users u ON e.recipient_id = u.id
    ORDER BY 
        CASE 
            WHEN e.status = 'pending' THEN 1
            WHEN e.status = 'processing' THEN 2
            WHEN e.status = 'sent' THEN 3
            ELSE 4
        END,
        COALESCE(e.sent_at, e.scheduled_time) DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sent_emails[] = $row;
}
?>

<div class="content">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="header-container">
        <div>
            <h1>Email Management</h1>
            <p>Create and manage email templates, send emails, and view email history</p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="email-tabs">
                <div class="email-tab active" data-tab="templates">Email Templates</div>
                <div class="email-tab" data-tab="bulk">Bulk Email</div>
                <div class="email-tab" data-tab="compose">Compose Email</div>
                <div class="email-tab" data-tab="sent">Sent/Queued</div>
                <div class="email-tab" data-tab="received">Received</div>
                <div class="email-tab" data-tab="automation">Email Automation</div>
            </div>
        </div>
        
        <div class="card-body">
            <div class="tab-content">
                <!-- Email Templates Tab -->
                <div class="tab-pane active" id="templates-tab">
                    <div class="tab-header-container">
                        <h3>Email Templates</h3>
                        <a href="template_builder.php" class="btn primary-btn">
                            <i class="fas fa-plus"></i> Create New Template
                        </a>
                    </div>
                    
                    <div class="templates-grid">
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card">
                                <!-- Three dots menu -->
                                <div class="template-actions-menu">
                                    <span class="template-menu-trigger"><i class="fas fa-ellipsis-v"></i></span>
                                    <div class="template-menu-dropdown">
                                        <a href="template_builder.php?id=<?php echo $template['id']; ?>" class="menu-item">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="menu-item delete-item" 
                                                data-id="<?php echo $template['id']; ?>" 
                                                onclick="return deleteTemplate(<?php echo $template['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Template preview (thumbnail) -->
                                <div class="template-thumbnail">
                                    <div class="template-thumbnail-wrapper">
                                        <div class="template-thumbnail-content">
                                            <?php 
                                            // Create scaled-down preview
                                            $content = $template['content'];
                                            // Strip script tags for security
                                            $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
                                            // Output the thumbnail preview
                                            echo $content; 
                                            ?>
                                        </div>
                                    </div>
                                    <button type="button" class="btn preview-btn" data-id="<?php echo $template['id']; ?>" onclick="showTemplatePreview(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars(addslashes($template['name'])); ?>')">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                                
                                <!-- Template name in footer -->
                                <div class="template-footer">
                                    <h5 title="<?php echo htmlspecialchars($template['name']); ?>"><?php echo htmlspecialchars($template['name']); ?></h5>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($templates)): ?>
                            <div class="empty-state">
                                <p>No templates found</p>
                                <a href="template_builder.php" class="btn primary-btn">
                                    <i class="fas fa-plus"></i> Create Your First Template
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bulk Email Tab -->
                <div class="tab-pane" id="bulk-tab">
                    <h3>Send Bulk Email</h3>
                    <form method="post" action="" class="form-container">
                        <div class="form-group">
                            <label for="recipient_type">Select Recipients*</label>
                            <select class="form-control" id="recipient_type" name="recipient_type" required>
                                <option value="">-- Select Recipient Group --</option>
                                <option value="all_clients">All Clients</option>
                                <option value="all_members">All Team Members</option>
                                <option value="pending_applications">Clients with Pending Applications</option>
                                <option value="upcoming_bookings">Clients with Upcoming Bookings</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_template_id">Use Template (Optional)</label>
                            <select class="form-control" id="bulk_template_id" name="bulk_template_id">
                                <option value="">-- Select Template --</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_subject">Subject*</label>
                            <input type="text" class="form-control" id="bulk_subject" name="bulk_subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_content">Content*</label>
                            <textarea class="form-control rich-editor" id="bulk_content" name="bulk_content" rows="10" required></textarea>
                            <small class="form-text text-muted">
                                Available placeholders: {first_name}, {last_name}, {full_name}, {email}
                            </small>
                        </div>
                        
                        <button type="submit" name="send_bulk_email" class="btn primary-btn">Send Bulk Email</button>
                    </form>
                </div>
                
                <!-- Compose Email Tab -->
                <div class="tab-pane" id="compose-tab">
                    <h3>Compose Email</h3>
                    <form method="post" action="" class="form-container">
                        <div class="email-compose-header">
                            <div class="form-group">
                                <label for="recipient_email">To*</label>
                                <input type="email" class="form-control" id="recipient_email" name="recipient_email" placeholder="recipient@example.com" value="<?php echo isset($_POST['recipient_email']) ? htmlspecialchars($_POST['recipient_email']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="cc_emails">CC</label>
                                <input type="text" class="form-control" id="cc_emails" name="cc_emails" placeholder="cc1@example.com, cc2@example.com" value="<?php echo isset($_POST['cc_emails']) ? htmlspecialchars($_POST['cc_emails']) : ''; ?>">
                                <small class="form-text text-muted">Separate multiple emails with commas</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="bcc_emails">BCC</label>
                                <input type="text" class="form-control" id="bcc_emails" name="bcc_emails" placeholder="bcc1@example.com, bcc2@example.com" value="<?php echo isset($_POST['bcc_emails']) ? htmlspecialchars($_POST['bcc_emails']) : ''; ?>">
                                <small class="form-text text-muted">Separate multiple emails with commas</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="template_id">Use Template (Optional)</label>
                            <select class="form-control" id="template_id" name="template_id">
                                <option value="">-- Select Template --</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>" <?php echo (isset($_POST['template_id']) && $_POST['template_id'] == $template['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject*</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">Message*</label>
                            <textarea class="form-control rich-editor" id="content" name="content" rows="12" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="scheduled_time">Schedule (Optional)</label>
                            <div class="schedule-input">
                                <input type="datetime-local" class="form-control" id="scheduled_time" name="scheduled_time" value="<?php echo isset($_POST['scheduled_time']) ? htmlspecialchars($_POST['scheduled_time']) : ''; ?>">
                                <button type="button" class="btn secondary-btn" id="clear_schedule">Clear</button>
                            </div>
                            <small class="form-text text-muted">Leave empty to send immediately</small>
                        </div>
                        
                        <div class="email-action-buttons">
                            <button type="submit" name="send_email" class="btn primary-btn"><i class="fas fa-paper-plane"></i> Send Email</button>
                            <button type="button" id="save_draft" class="btn secondary-btn"><i class="fas fa-save"></i> Save Draft</button>
                            <button type="button" id="preview_email" class="btn secondary-btn"><i class="fas fa-eye"></i> Preview</button>
                        </div>
                    </form>
                </div>
                
                <!-- Sent/Queued Tab -->
                <div class="tab-pane" id="sent-tab">
                    <h3>Sent & Queued Emails</h3>
                    <div class="table-responsive">
                        <table class="data-table" id="sentTable">
                            <thead>
                                <tr>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Scheduled/Sent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sent_emails as $email): ?>
                                    <tr>
                                        <td>
                                            <div class="recipient-info">
                                                <?php if (!empty($email['recipient_name'])): ?>
                                                    <span class="recipient-name"><?php echo htmlspecialchars($email['recipient_name']); ?></span>
                                                    <span class="recipient-email"><?php echo htmlspecialchars($email['recipient_email']); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($email['recipient_email']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($email['subject']); ?></td>
                                        <td>
                                            <?php if ($email['status'] == 'pending'): ?>
                                                <span class="status-badge pending">Pending</span>
                                            <?php elseif ($email['status'] == 'processing'): ?>
                                                <span class="status-badge processing">Processing</span>
                                            <?php elseif ($email['status'] == 'sent'): ?>
                                                <span class="status-badge sent">Sent</span>
                                            <?php elseif ($email['status'] == 'failed'): ?>
                                                <span class="status-badge failed">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="date">
                                                <?php if (!empty($email['sent_at'])): ?>
                                                    <?php echo date('M d, Y H:i', strtotime($email['sent_at'])); ?>
                                                <?php else: ?>
                                                    <?php echo date('M d, Y H:i', strtotime($email['scheduled_time'])); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="btn-action view-email" data-id="<?php echo $email['id']; ?>" data-type="sent" data-recipient="<?php echo htmlspecialchars($email['recipient_email']); ?>" data-subject="<?php echo htmlspecialchars($email['subject']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($email['status'] == 'pending'): ?>
                                                <button class="btn-action btn-danger cancel-email" data-id="<?php echo $email['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sent_emails)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No sent or queued emails found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Received Tab -->
                <div class="tab-pane" id="received-tab">
                    <h3>Received Emails</h3>
                    <div class="table-responsive">
                        <table class="data-table" id="receivedTable">
                            <thead>
                                <tr>
                                    <th>Sender</th>
                                    <th>Subject</th>
                                    <th>Received</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($received_emails as $email): ?>
                                    <tr>
                                        <td>
                                            <div class="sender-info">
                                                <?php if (!empty($email['sender_name'])): ?>
                                                    <span class="sender-name"><?php echo htmlspecialchars($email['sender_name']); ?></span>
                                                    <span class="sender-email"><?php echo htmlspecialchars($email['sender_email']); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($email['sender_email']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($email['subject']); ?></td>
                                        <td><span class="date"><?php echo date('M d, Y H:i', strtotime($email['received_at'])); ?></span></td>
                                        <td class="actions-cell">
                                            <button class="btn-action view-email" data-id="<?php echo $email['id']; ?>" data-type="received" data-sender="<?php echo htmlspecialchars($email['sender_email']); ?>" data-subject="<?php echo htmlspecialchars($email['subject']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action reply-email" 
                                                    data-email="<?php echo htmlspecialchars($email['sender_email']); ?>"
                                                    data-subject="Re: <?php echo htmlspecialchars($email['subject']); ?>">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($received_emails)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No received emails found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Email Automation Tab -->
                <div class="tab-pane" id="automation-tab">
                    <h3>Email Automation Settings</h3>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Booking Email Notifications</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" class="form-container">
                                <div class="form-group">
                                    <label>Booking Confirmation Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="booking_confirmation" name="automation[booking_confirmation]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="booking_confirmation" class="switch-label">Send booking confirmation emails</label>
                                    </div>
                                    <select class="form-control" name="templates[booking_confirmation]">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'booking_confirmation'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Booking Reminder Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="booking_reminder" name="automation[booking_reminder]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="booking_reminder" class="switch-label">Send booking reminder emails</label>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">Send</span>
                                        <input type="number" class="form-control reminder-hours" name="reminder_hours" value="24" min="1" max="72">
                                        <span class="input-group-text">hours before appointment</span>
                                    </div>
                                    <select class="form-control" name="templates[booking_reminder]">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'booking_reminder'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Booking Cancellation Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="booking_cancellation" name="automation[booking_cancellation]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="booking_cancellation" class="switch-label">Send booking cancellation emails</label>
                                    </div>
                                    <select class="form-control" name="templates[booking_cancellation]">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'booking_cancellation'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Application Email Notifications</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" class="form-container">
                                <div class="form-group">
                                    <label>Application Status Change Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="application_status" name="automation[application_status]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="application_status" class="switch-label">Send application status update emails</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Document Request Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="document_request" name="automation[document_request]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="document_request" class="switch-label">Send document request emails</label>
                                    </div>
                                    <select class="form-control" name="templates[document_request]">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'document_request'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Document Approval/Rejection Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="document_review" name="automation[document_review]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="document_review" class="switch-label">Send document review result emails</label>
                                    </div>
                                    <select class="form-control" name="templates[document_approval]">
                                        <option value="">-- Select Template (Approval) --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'document_approval'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Document Rejection Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="document_rejection" name="automation[document_rejection]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="document_rejection" class="switch-label">Send document rejection result emails</label>
                                    </div>
                                    <select class="form-control" name="templates[document_rejection]">
                                        <option value="">-- Select Template (Rejection) --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template['template_type'] == 'document_rejection'): ?>
                                                <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>System Email Notifications</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" class="form-container">
                                <div class="form-group">
                                    <label>Welcome Email</label>
                                    <div class="switch-container mb-2">
                                        <label class="switch">
                                            <input type="checkbox" id="welcome_email" name="automation[welcome_email]" checked>
                                            <span class="slider round"></span>
                                        </label>
                                        <label for="welcome_email" class="switch-label">Send welcome email</label>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add the JavaScript directly at the end of the file -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Inline email management JS loaded');
        
        // Tab functionality
        const tabs = document.querySelectorAll('.email-tab');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        console.log('Tabs found:', tabs.length);
        console.log('Tab panes found:', tabPanes.length);
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                console.log('Tab clicked:', this.getAttribute('data-tab'));
                
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Hide all tab panes
                tabPanes.forEach(pane => pane.classList.remove('active'));
                
                // Show the selected tab pane
                const tabName = this.getAttribute('data-tab');
                const targetPane = document.getElementById(tabName + '-tab');
                
                if (targetPane) {
                    targetPane.classList.add('active');
                    console.log('Activated tab pane:', tabName + '-tab');
                } else {
                    console.error('Tab pane not found:', tabName + '-tab');
                }
            });
        });
        
        // Delete template functionality
        const deleteButtons = document.querySelectorAll('.delete-item');
        
        console.log('Delete buttons found:', deleteButtons.length);
        
        deleteButtons.forEach(button => {
            button.setAttribute('type', 'button'); // Ensure it's a button type
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const templateId = this.getAttribute('data-id');
                console.log('Delete button clicked for template ID:', templateId);
                
                if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                    console.log('Delete confirmed');
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('delete_template', 'true');
                    formData.append('template_id', templateId);
                    
                    // Send AJAX request
                    fetch('ajax_handlers/delete_template.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Delete response:', data);
                        
                        if (data.success) {
                            // Simple solution - reload the page
                            window.location.reload();
                        } else {
                            alert(data.message || 'An error occurred while deleting the template');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request. Please try again.');
                    });
                }
            });
        });
        
        // Template selection in email forms
        const templateSelects = document.querySelectorAll('#template_id, #bulk_template_id');
        
        console.log('Template selects found:', templateSelects.length);
        
        templateSelects.forEach(select => {
            select.addEventListener('change', function() {
                const templateId = this.value;
                if (!templateId) return;
                
                console.log('Template selected:', templateId);
                
                // Determine which form we're in
                const isCompose = this.id === 'template_id';
                const subjectField = isCompose ? 'subject' : 'bulk_subject';
                const contentField = isCompose ? 'content' : 'bulk_content';
                
                // Fetch template details
                fetch(`ajax_handlers/get_template.php?id=${templateId}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Template data:', data);
                        
                        if (data.success) {
                            // Fill in subject and content fields
                            document.getElementById(subjectField).value = data.template.subject;
                            
                            // If using a rich text editor
                            const contentElement = document.getElementById(contentField);
                            if (contentElement.classList.contains('rich-editor') && window.CKEDITOR) {
                                CKEDITOR.instances[contentField].setData(data.template.content);
                            } else {
                                contentElement.value = data.template.content;
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        });
        
        // Clear schedule button
        const clearScheduleBtn = document.getElementById('clear_schedule');
        if (clearScheduleBtn) {
            clearScheduleBtn.addEventListener('click', function() {
                const scheduledTimeInput = document.getElementById('scheduled_time');
                if (scheduledTimeInput) {
                    scheduledTimeInput.value = '';
                }
            });
        }
        
        // Preview email button
        const previewEmailBtn = document.getElementById('preview_email');
        if (previewEmailBtn) {
            previewEmailBtn.addEventListener('click', function() {
                const contentElement = document.getElementById('content');
                const subjectElement = document.getElementById('subject');
                
                if (!contentElement || !subjectElement || !contentElement.value.trim() || !subjectElement.value.trim()) {
                    alert('Please fill in the subject and content fields to preview the email.');
                    return;
                }
                
                // Create modal if it doesn't exist
                let modal = document.getElementById('emailPreviewModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'emailPreviewModal';
                    modal.className = 'modal';
                    
                    modal.innerHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Email Preview</h3>
                                <button type="button" class="close-modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="email-preview-header">
                                    <div><strong>Subject:</strong> <span id="preview-subject"></span></div>
                                    <div><strong>To:</strong> <span id="preview-to"></span></div>
                                    <div id="preview-cc-container" style="display:none"><strong>CC:</strong> <span id="preview-cc"></span></div>
                                    <div id="preview-bcc-container" style="display:none"><strong>BCC:</strong> <span id="preview-bcc"></span></div>
                                </div>
                                <hr>
                                <div id="preview-content"></div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    
                    // Add close functionality
                    modal.querySelector('.close-modal').addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                    
                    // Close when clicking outside the modal content
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                }
                
                // Get values from form
                const subject = document.getElementById('subject').value;
                const content = document.getElementById('content').value;
                const to = document.getElementById('recipient_email').value;
                const cc = document.getElementById('cc_emails').value;
                const bcc = document.getElementById('bcc_emails').value;
                
                // Update modal content
                modal.querySelector('#preview-subject').textContent = subject;
                modal.querySelector('#preview-to').textContent = to;
                
                // Handle CC
                if (cc.trim()) {
                    modal.querySelector('#preview-cc').textContent = cc;
                    modal.querySelector('#preview-cc-container').style.display = 'block';
                } else {
                    modal.querySelector('#preview-cc-container').style.display = 'none';
                }
                
                // Handle BCC
                if (bcc.trim()) {
                    modal.querySelector('#preview-bcc').textContent = bcc;
                    modal.querySelector('#preview-bcc-container').style.display = 'block';
                } else {
                    modal.querySelector('#preview-bcc-container').style.display = 'none';
                }
                
                // Set email content
                modal.querySelector('#preview-content').innerHTML = content;
                
                // Show the modal
                modal.style.display = 'block';
            });
        }
        
        // Save draft button (simplified functionality)
        const saveDraftBtn = document.getElementById('save_draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() {
                alert('Draft saving is not implemented yet. This feature will be available in a future update.');
            });
        }
        
        // Email form submission
        const emailForm = document.querySelector('#compose-tab form');
        if (emailForm) {
            emailForm.addEventListener('submit', function(e) {
                const toField = document.getElementById('recipient_email');
                const subjectField = document.getElementById('subject');
                const contentField = document.getElementById('content');
                
                if (!toField.value.trim() || !subjectField.value.trim() || !contentField.value.trim()) {
                    alert('Please fill all required fields (To, Subject, and Message)');
                    e.preventDefault();
                    return;
                }
                
                // Show sending overlay
                const overlay = document.createElement('div');
                overlay.className = 'sending-overlay';
                overlay.innerHTML = `
                    <div class="sending-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div class="sending-message">Sending email...</div>
                `;
                
                document.body.appendChild(overlay);
                
                // Form will submit normally after overlay is shown
            });
        }
        
        // Reply to email functionality
        const replyButtons = document.querySelectorAll('.reply-email');
        replyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const email = this.getAttribute('data-email');
                const subject = this.getAttribute('data-subject');
                
                // Switch to compose tab
                const composeTab = document.querySelector('[data-tab="compose"]');
                if (composeTab) {
                    composeTab.click();
                }
                
                // Fill the form fields
                const toField = document.getElementById('recipient_email');
                const subjectField = document.getElementById('subject');
                const contentField = document.getElementById('content');
                
                if (toField) toField.value = email;
                if (subjectField) subjectField.value = subject;
                if (contentField) {
                    // Add reply quote
                    contentField.value = '\n\n------ Original Message ------\n';
                    if (contentField.classList.contains('rich-editor') && window.CKEDITOR && CKEDITOR.instances['content']) {
                        CKEDITOR.instances['content'].setData('\n\n------ Original Message ------\n');
                    }
                }
                
                // Focus the content field
                if (contentField) {
                    if (contentField.classList.contains('rich-editor') && window.CKEDITOR && CKEDITOR.instances['content']) {
                        CKEDITOR.instances['content'].focus();
                    } else {
                        contentField.focus();
                    }
                }
            });
        });
        
        // View email functionality
        const viewButtons = document.querySelectorAll('.view-email');
        console.log('View email buttons found:', viewButtons.length);
        
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const emailId = this.getAttribute('data-id');
                const emailType = this.getAttribute('data-type');
                const subject = this.getAttribute('data-subject');
                
                console.log('View email clicked:', emailId, emailType);
                
                // Show loading state
                document.body.style.cursor = 'wait';
                
                // Fetch the email content
                fetch(`ajax_handlers/get_email.php?id=${emailId}&type=${emailType}`)
                    .then(response => response.json())
                    .then(data => {
                        document.body.style.cursor = 'default';
                        
                        if (data.success) {
                            showEmailModal(data.email, emailType);
                        } else {
                            alert('Error loading email: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.body.style.cursor = 'default';
                        
                        // If AJAX fails, create a basic modal with the information we have
                        const email = {
                            subject: subject,
                            content: '<p>Could not load the full email content. Please try again later.</p>',
                            recipient_email: this.getAttribute('data-recipient') || 'N/A',
                            sender_email: this.getAttribute('data-sender') || 'N/A',
                            received_at: null,
                            sent_at: null,
                            scheduled_time: null
                        };
                        
                        showEmailModal(email, emailType);
                    });
            });
        });
        
        // Handle dropdown menu interactions
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.template-menu-dropdown');
            if (!e.target.closest('.template-actions-menu')) {
                dropdowns.forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
        });

        // Toggle dropdowns when clicking the trigger
        const menuTriggers = document.querySelectorAll('.template-menu-trigger');
        menuTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.template-menu-dropdown').forEach(d => {
                    if (d !== dropdown) {
                        d.style.display = 'none';
                    }
                });
                
                // Toggle this dropdown
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });
        });
    });
    
    // Global delete function that can be called directly from the onclick attribute
    function deleteTemplate(templateId) {
        console.log('Manual delete function called for template ID:', templateId);
        
        if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
            // Create form data
            const formData = new FormData();
            formData.append('delete_template', 'true');
            formData.append('template_id', templateId);
            
            // Show a processing indicator
            document.body.style.cursor = 'wait';
            
            // Send AJAX request
            fetch('ajax_handlers/delete_template.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.style.cursor = 'default';
                
                if (data.success) {
                    // Reload the page to show updated templates
                    window.location.reload();
                } else {
                    alert(data.message || 'An error occurred while deleting the template');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.body.style.cursor = 'default';
                alert('An error occurred while processing your request. Please try again.');
            });
        }
        
        // Return false to prevent the default behavior
        return false;
    }

    // Add the showTemplatePreview function to the JavaScript section
    function showTemplatePreview(templateId, templateName) {
        console.log('Showing preview for template ID:', templateId);
        
        // Show loading state
        document.body.style.cursor = 'wait';
        
        // Fetch the template content
        fetch(`ajax_handlers/get_template.php?id=${templateId}`)
            .then(response => response.json())
            .then(data => {
                document.body.style.cursor = 'default';
                
                if (data.success) {
                    // Create modal if it doesn't exist
                    let modal = document.getElementById('templatePreviewModal');
                    if (!modal) {
                        modal = document.createElement('div');
                        modal.id = 'templatePreviewModal';
                        modal.className = 'modal';
                        
                        modal.innerHTML = `
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title"></h3>
                                    <button type="button" class="close-modal">&times;</button>
                                </div>
                                <div class="modal-body"></div>
                            </div>
                        `;
                        
                        document.body.appendChild(modal);
                        
                        // Add close functionality
                        modal.querySelector('.close-modal').addEventListener('click', function() {
                            modal.style.display = 'none';
                        });
                        
                        // Close when clicking outside the modal content
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.style.display = 'none';
                            }
                        });
                    }
                    
                    // Update modal content
                    modal.querySelector('.modal-title').textContent = templateName;
                    modal.querySelector('.modal-body').innerHTML = data.template.content;
                    
                    // Show the modal
                    modal.style.display = 'block';
                } else {
                    alert('Error loading template preview');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.body.style.cursor = 'default';
                alert('An error occurred while loading the template preview');
            });
    }

    // Close the modal when ESC key is pressed
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('templatePreviewModal');
            if (modal && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        }
    });

    // Function to show email modal
    function showEmailModal(email, emailType) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('emailViewModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'emailViewModal';
            modal.className = 'modal';
            
            modal.innerHTML = `
                <div class="modal-content email-view-modal">
                    <div class="modal-header">
                        <h3 class="modal-title"></h3>
                        <button type="button" class="close-modal">&times;</button>
                    </div>
                    <div class="email-metadata">
                        <div class="email-metadata-row">
                            <div class="email-metadata-label">From:</div>
                            <div class="email-metadata-value email-from"></div>
                        </div>
                        <div class="email-metadata-row">
                            <div class="email-metadata-label">To:</div>
                            <div class="email-metadata-value email-to"></div>
                        </div>
                        <div class="email-metadata-row cc-row" style="display: none;">
                            <div class="email-metadata-label">CC:</div>
                            <div class="email-metadata-value email-cc"></div>
                        </div>
                        <div class="email-metadata-row bcc-row" style="display: none;">
                            <div class="email-metadata-label">BCC:</div>
                            <div class="email-metadata-value email-bcc"></div>
                        </div>
                        <div class="email-metadata-row">
                            <div class="email-metadata-label">Date:</div>
                            <div class="email-metadata-value email-date"></div>
                        </div>
                        <div class="email-metadata-row status-row" style="display: none;">
                            <div class="email-metadata-label">Status:</div>
                            <div class="email-metadata-value email-status"></div>
                        </div>
                    </div>
                    <div class="modal-body email-content"></div>
                    <div class="modal-footer">
                        <button class="btn secondary-btn close-email-btn">Close</button>
                        <button class="btn primary-btn reply-btn" style="display: none;">Reply</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Add close functionality
            const closeButtons = modal.querySelectorAll('.close-modal, .close-email-btn');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            });
            
            // Close when clicking outside the modal content
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Reply button functionality
            const replyBtn = modal.querySelector('.reply-btn');
            if (replyBtn) {
                replyBtn.addEventListener('click', function() {
                    // Get the email info from the modal
                    const toEmail = emailType === 'sent' ? 
                        modal.querySelector('.email-to').textContent : 
                        modal.querySelector('.email-from').textContent;
                        
                    const subject = "Re: " + modal.querySelector('.modal-title').textContent;
                    
                    // Switch to compose tab and fill the form
                    const composeTab = document.querySelector('[data-tab="compose"]');
                    if (composeTab) {
                        composeTab.click();
                        
                        const toField = document.getElementById('recipient_email');
                        const subjectField = document.getElementById('subject');
                        
                        if (toField) toField.value = toEmail;
                        if (subjectField) subjectField.value = subject;
                        
                        // Close the modal
                        modal.style.display = 'none';
                    }
                });
            }
        }
        
        // Update modal content based on email type
        if (emailType === 'sent') {
            // For sent emails
            modal.querySelector('.modal-title').textContent = email.subject || 'No Subject';
            modal.querySelector('.email-from').textContent = 'You';
            modal.querySelector('.email-to').textContent = email.recipient_email || 'N/A';
            
            // Handle CC if available
            if (email.cc_emails) {
                try {
                    const ccEmails = JSON.parse(email.cc_emails);
                    if (ccEmails && ccEmails.length > 0) {
                        modal.querySelector('.cc-row').style.display = 'flex';
                        modal.querySelector('.email-cc').textContent = ccEmails.join(', ');
                    } else {
                        modal.querySelector('.cc-row').style.display = 'none';
                    }
                } catch (e) {
                    modal.querySelector('.cc-row').style.display = 'none';
                }
            } else {
                modal.querySelector('.cc-row').style.display = 'none';
            }
            
            // Handle BCC if available
            if (email.bcc_emails) {
                try {
                    const bccEmails = JSON.parse(email.bcc_emails);
                    if (bccEmails && bccEmails.length > 0) {
                        modal.querySelector('.bcc-row').style.display = 'flex';
                        modal.querySelector('.email-bcc').textContent = bccEmails.join(', ');
                    } else {
                        modal.querySelector('.bcc-row').style.display = 'none';
                    }
                } catch (e) {
                    modal.querySelector('.bcc-row').style.display = 'none';
                }
            } else {
                modal.querySelector('.bcc-row').style.display = 'none';
            }
            
            // Date display logic
            let dateText = '';
            if (email.sent_at) {
                dateText = 'Sent: ' + formatDate(email.sent_at);
            } else if (email.scheduled_time) {
                dateText = 'Scheduled: ' + formatDate(email.scheduled_time);
            } else {
                dateText = 'Queued: ' + formatDate(email.created_at);
            }
            modal.querySelector('.email-date').textContent = dateText;
            
            // Show status for sent emails
            modal.querySelector('.status-row').style.display = 'flex';
            let statusHtml = '';
            switch (email.status) {
                case 'pending':
                    statusHtml = '<span class="status-badge pending">Pending</span>';
                    break;
                case 'processing':
                    statusHtml = '<span class="status-badge processing">Processing</span>';
                    break;
                case 'sent':
                    statusHtml = '<span class="status-badge sent">Sent</span>';
                    break;
                case 'failed':
                    statusHtml = '<span class="status-badge failed">Failed</span>';
                    break;
                default:
                    statusHtml = email.status || 'Unknown';
            }
            modal.querySelector('.email-status').innerHTML = statusHtml;
            
            // Hide reply button for sent emails
            modal.querySelector('.reply-btn').style.display = 'none';
        } else {
            // For received emails
            modal.querySelector('.modal-title').textContent = email.subject || 'No Subject';
            modal.querySelector('.email-from').textContent = email.sender_email || 'N/A';
            modal.querySelector('.email-to').textContent = 'You';
            
            // Hide CC and BCC for received emails
            modal.querySelector('.cc-row').style.display = 'none';
            modal.querySelector('.bcc-row').style.display = 'none';
            
            // Date for received emails
            modal.querySelector('.email-date').textContent = 
                email.received_at ? 'Received: ' + formatDate(email.received_at) : 'N/A';
            
            // Hide status for received emails
            modal.querySelector('.status-row').style.display = 'none';
            
            // Show reply button for received emails
            modal.querySelector('.reply-btn').style.display = 'inline-flex';
        }
        
        // Set email content
        modal.querySelector('.email-content').innerHTML = email.content || '<p>No content</p>';
        
        // Show the modal
        modal.style.display = 'block';
    }

    // Helper function to format dates
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString; // Return original if invalid
        
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
</script>

<!-- Also update the CSS directly -->
<style>
    /* Fix for template action buttons */
    .template-actions .btn-action {
        position: relative;
        z-index: 10;
        flex: 1;
        padding: 10px;
        border: none;
        background-color: white;
        color: var(--primary-color);
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
        text-decoration: none;
        width: auto;
        height: auto;
        border-radius: 0;
        margin-right: 0;
    }
    
    .template-actions .btn-danger {
        color: var(--danger-color);
        background-color: white;
    }
    
    .template-actions .btn-danger:hover {
        background-color: #fff5f5;
    }
    
    /* Template thumbnail styles */
    .template-thumbnail {
        position: relative;
        flex-grow: 1;
        overflow: hidden;
        background-color: white;
        display: flex;
        flex-direction: column;
        height: calc(100% - 40px); /* Account for footer */
    }
    
    .template-thumbnail-wrapper {
        position: relative;
        flex-grow: 1;
        overflow: hidden;
    }
    
    .template-thumbnail-content {
        transform: scale(0.5);
        transform-origin: top center;
        width: 200%;
        height: 200%;
        pointer-events: none;
        padding: 15px;
        overflow: hidden;
    }
    
    .template-thumbnail:after {
        content: '';
        position: absolute;
        bottom: 40px;
        left: 0;
        right: 0;
        height: 80px;
        background: linear-gradient(transparent, white);
        pointer-events: none;
    }
    
    /* Preview button styling */
    .preview-btn {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        padding: 8px;
        font-size: 13px;
        color: var(--primary-color);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        border-top: 1px solid #eee;
        transition: background-color 0.2s;
        z-index: 5;
        opacity: 0;
        transform: translateY(100%);
        transition: opacity 0.3s, transform 0.3s;
    }
    
    .template-card:hover .preview-btn {
        opacity: 1;
        transform: translateY(0);
    }
    
    .preview-btn:hover {
        background: var(--primary-color);
        color: white;
    }
    
    /* Template footer with just the name */
    .template-footer {
        height: 40px;
        padding: 0 12px;
        display: flex;
        align-items: center;
        background-color: #f9f9f9;
        border-top: 1px solid #eee;
    }
    
    .template-footer h5 {
        margin: 0;
        font-size: 14px;
        font-weight: 500;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        width: 100%;
    }
    
    /* Templates grid spacing */
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }
    
    @media (max-width: 768px) {
        .templates-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
        }
    }
    
    /* Add this for empty state styling */
    .empty-state {
        text-align: center;
        padding: 30px;
        background-color: #f9f9f9;
        border-radius: 8px;
        grid-column: 1 / -1;
    }
    
    .empty-state p {
        margin-bottom: 15px;
        color: #6c757d;
    }
</style>

<?php include('includes/footer.php'); ?>