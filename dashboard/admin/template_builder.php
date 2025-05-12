<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Email Template Builder | Admin Dashboard";
$page_specific_css = "assets/css/template_builder.css";
include('includes/header.php');

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template_name = '';
$template_subject = '';
$template_content = '';
$template_type = '';
$success_message = '';
$error_message = '';

// Fetch template if editing
if ($template_id > 0) {
    $stmt = $conn->prepare("SELECT id, name, subject, content, template_type FROM email_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $template = $result->fetch_assoc();
        $template_name = $template['name'];
        $template_subject = $template['subject'];
        $template_content = $template['content'];
        $template_type = $template['template_type'];
    } else {
        $error_message = "Template not found";
    }
}

// Handle form submission
if (isset($_POST['save_template'])) {
    $template_name = trim($_POST['template_name']);
    $template_subject = trim($_POST['template_subject']);
    $template_content = $_POST['template_content'];
    $template_type = $_POST['template_type'];
    
    if (empty($template_name) || empty($template_subject) || empty($template_content)) {
        $error_message = "All fields are required";
    } else {
        // Ensure we have a valid user ID for created_by field
        $user_id = isset($_SESSION["id"]) ? $_SESSION["id"] : 0;
        
        // Check if the user exists in the database
        $check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check_user->bind_param("i", $user_id);
        $check_user->execute();
        $user_result = $check_user->get_result();
        
        if ($user_result->num_rows == 0) {
            // If current user doesn't exist, use admin user with ID 1 as fallback
            $user_id = 1;
            
            // Verify admin user exists
            $check_admin = $conn->prepare("SELECT id FROM users WHERE id = 1");
            $check_admin->execute();
            if ($check_admin->get_result()->num_rows == 0) {
                $error_message = "Error: No valid user found to associate with this template. Please contact system administrator.";
                // You might want to create a user record here if needed
            }
        }
        
        if (empty($error_message)) {
            if ($template_id > 0) {
                // Update existing template
                $stmt = $conn->prepare("UPDATE email_templates SET name = ?, subject = ?, content = ?, template_type = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssi", $template_name, $template_subject, $template_content, $template_type, $template_id);
            } else {
                // Create new template
                $stmt = $conn->prepare("INSERT INTO email_templates (name, subject, content, template_type, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("ssssi", $template_name, $template_subject, $template_content, $template_type, $user_id);
            }
            
            if ($stmt->execute()) {
                if ($template_id == 0) {
                    $template_id = $conn->insert_id;
                }
                $success_message = "Template " . ($template_id > 0 ? "updated" : "created") . " successfully";
                
                // Redirect to avoid form resubmission
                header("Location: email-management.php");
                exit();
            } else {
                $error_message = "Error " . ($template_id > 0 ? "updating" : "creating") . " template: " . $conn->error;
            }
        }
    }
}

// Get template types for dropdown
$template_types = [
    'general' => 'General',
    'welcome' => 'Welcome Email',
    'booking_confirmation' => 'Booking Confirmation',
    'booking_reminder' => 'Booking Reminder',
    'booking_cancellation' => 'Booking Cancellation',
    'document_request' => 'Document Request',
    'document_approval' => 'Document Approval',
    'document_rejection' => 'Document Rejection',
    'application_status' => 'Application Status Update'
];
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
            <h1><?php echo $template_id > 0 ? 'Edit' : 'Create New'; ?> Email Template</h1>
            <p>Use our template builder to create professional email templates</p>
        </div>
        <div>
            <a href="email-management.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Email Management
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <!-- Updated Tab style with only two tabs -->
            <div class="tabs-container">
                <div class="tabs" id="templateTabs">
                    <button class="tab-btn active" data-tab="components">Component Builder</button>
                    <button class="tab-btn" data-tab="ai">AI & HTML Builder</button>
                </div>
            </div>
            
            <form id="templateForm" method="post" action="">
                <div class="template-form-top">
                    <div class="form-group">
                        <label for="template_name">Template Name*</label>
                        <input type="text" class="form-control" id="template_name" name="template_name" 
                               value="<?php echo htmlspecialchars($template_name); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_subject">Email Subject*</label>
                        <input type="text" class="form-control" id="template_subject" name="template_subject" 
                               value="<?php echo htmlspecialchars($template_subject); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_type">Template Type*</label>
                        <select class="form-control" id="template_type" name="template_type" required>
                            <option value="">-- Select Type --</option>
                            <?php foreach ($template_types as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $template_type === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="tab-content">
                    <!-- Component Builder Tab -->
                    <div class="tab-pane active" id="components-tab">
                        <div class="component-builder">
                            <div class="component-sidebar">
                                <h4>Components</h4>
                                <div class="component-list">
                                    <div class="component-item" draggable="true" data-component="container">
                                        <i class="fas fa-square-full"></i> Container
                                    </div>
                                    <div class="component-item" draggable="true" data-component="row">
                                        <i class="fas fa-columns"></i> Row
                                    </div>
                                    <div class="component-item" draggable="true" data-component="text">
                                        <i class="fas fa-font"></i> Text
                                    </div>
                                    <div class="component-item" draggable="true" data-component="image">
                                        <i class="fas fa-image"></i> Image
                                    </div>
                                    <div class="component-item" draggable="true" data-component="button">
                                        <i class="fas fa-square"></i> Button
                                    </div>
                                    <div class="component-item" draggable="true" data-component="divider">
                                        <i class="fas fa-minus"></i> Divider
                                    </div>
                                    <div class="component-item" draggable="true" data-component="spacer">
                                        <i class="fas fa-arrows-alt-v"></i> Spacer
                                    </div>
                                    <div class="component-item" draggable="true" data-component="grid">
                                        <i class="fas fa-th"></i> Grid Layout
                                    </div>
                                    <div class="component-item" draggable="true" data-component="imagePlaceholder">
                                        <i class="fas fa-image"></i> Image Upload
                                    </div>
                                </div>
                                
                                <h4>Templates</h4>
                                <div class="template-list">
                                    <div class="template-item" data-template="simple">
                                        Simple
                                    </div>
                                    <div class="template-item" data-template="announcement">
                                        Announcement
                                    </div>
                                    <div class="template-item" data-template="newsletter">
                                        Newsletter
                                    </div>
                                    <div class="template-item" data-template="confirmation">
                                        Confirmation
                                    </div>
                                </div>
                                
                                <h4>Placeholders</h4>
                                <div class="placeholder-list">
                                    <button type="button" class="placeholder-tag" data-placeholder="{first_name}">First Name</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{last_name}">Last Name</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{full_name}">Full Name</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{email}">Email</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{booking_date}">Booking Date</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{booking_time}">Booking Time</button>
                                    <button type="button" class="placeholder-tag" data-placeholder="{application_status}">App. Status</button>
                                </div>
                            </div>
                            
                            <div class="canvas-wrapper">
                                <div class="canvas-container">
                                    <div class="canvas-header">
                                        <div class="canvas-actions">
                                            <button type="button" class="btn-action" id="undoBtn" title="Undo">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button type="button" class="btn-action" id="redoBtn" title="Redo">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                            <button type="button" class="btn-action" id="previewBtn" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="templateCanvas" class="template-canvas">
                                        <!-- Template content will be built here -->
                                    </div>
                                </div>
                                
                                <div class="canvas-preview">
                                    <h4>Preview</h4>
                                    <div id="componentPreview" class="component-preview-area">
                                        <!-- Component preview will appear here -->
                                    </div>
                                </div>
                            </div>
                            
                            <div class="property-panel">
                                <h4>Properties</h4>
                                <div id="componentProperties">
                                    <p class="property-placeholder">Select a component to edit its properties</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI & HTML Builder Tab (Combined) -->
                    <div class="tab-pane" id="ai-tab">
                        <div class="ai-prompt-wrapper">
                            <label for="ai-prompt">Describe the email template you want to create:</label>
                            <div class="ai-prompt-container">
                                <input type="text" id="ai-prompt" class="form-control" 
                                       placeholder="E.g., Create a welcome email template with blue header, company logo, and a greeting message with the recipient's name...">
                                <button type="button" id="generateTemplateBtn" class="btn primary-btn">
                                    <i class="fas fa-magic"></i> Generate
                                </button>
                            </div>
                            <div class="ai-suggestions">
                                <p>Suggestions:</p>
                                <button type="button" class="ai-suggestion-tag">Welcome email with company introduction</button>
                                <button type="button" class="ai-suggestion-tag">Appointment confirmation with details</button>
                                <button type="button" class="ai-suggestion-tag">Document request with instructions</button>
                                <button type="button" class="ai-suggestion-tag">Application approval notification</button>
                            </div>
                        </div>
                        
                        <div class="ai-generating d-none">
                            <div class="ai-generating-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <p>AI is generating your template...</p>
                        </div>
                        
                        <!-- New code/preview layout for AI tab -->
                        <div class="code-container">
                            <div class="code-editor-container">
                                <div class="editor-header">
                                    <h4>HTML Code</h4>
                                    <div class="editor-actions">
                                        <button type="button" class="btn-action" id="aiFormatCodeBtn" title="Format Code">
                                            <i class="fas fa-code"></i> Format
                                        </button>
                                    </div>
                                </div>
                                <textarea class="form-control code-editor" id="ai_template_content" name="template_content" rows="20"><?php echo htmlspecialchars($template_content); ?></textarea>
                            </div>
                            
                            <div class="code-preview-container">
                                <div class="preview-header">
                                    <h4>Preview</h4>
                                    <button type="button" class="btn-action" id="aiRefreshPreviewBtn">
                                        <i class="fas fa-sync"></i> Refresh
                                    </button>
                                </div>
                                <div id="aiCodePreview" class="email-preview">
                                    <!-- AI generated preview will appear here -->
                                    <?php if (!empty($template_content)): ?>
                                        <?php echo $template_content; ?>
                                    <?php else: ?>
                                        <p class="preview-placeholder">HTML preview will appear here</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <a href="email-management.php" class="btn cancel-btn">Cancel</a>
                    <button type="submit" name="save_template" class="btn primary-btn">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/template_builder.js"></script>
<?php include('includes/footer.php'); ?>
