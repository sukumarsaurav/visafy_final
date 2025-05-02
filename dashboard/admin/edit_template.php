<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Redirect to login if no user_id is set
    header("Location: login.php");
    exit;
}

$page_title = "Edit Document Template";
$page_specific_css = "assets/css/templates.css";
require_once 'includes/header.php';

// Get template id from query string
$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($template_id <= 0) {
    echo '<div class="alert alert-danger">Invalid template ID.</div>';
    require_once 'includes/footer.php';
    exit;
}

// Get all document types for dropdown
$types_query = "SELECT dt.id, dt.name, dt.category_id, dc.name as category_name 
                FROM document_types dt 
                JOIN document_categories dc ON dt.category_id = dc.id 
                WHERE dt.is_active = 1
                ORDER BY dc.name, dt.name";
$types_stmt = $conn->prepare($types_query);
$types_stmt->execute();
$types_result = $types_stmt->get_result();
$document_types = [];

if ($types_result && $types_result->num_rows > 0) {
    while ($row = $types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}
$types_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $template_name = trim($_POST['template_name']);
    $document_type_id = $_POST['document_type_id'];
    $content = trim($_POST['content']);
    $is_active = isset($_POST['template_is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($template_name)) {
        $errors[] = "Template name is required";
    }
    if (empty($document_type_id)) {
        $errors[] = "Document type is required";
    }
    if (empty($content)) {
        $errors[] = "Template content is required";
    }
    
    if (empty($errors)) {
        // Check if template name already exists (except for this template)
        $check_query = "SELECT id FROM document_templates WHERE name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $template_name, $template_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Template name already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Update template
        $update_query = "UPDATE document_templates SET name = ?, document_type_id = ?, content = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sssii', $template_name, $document_type_id, $content, $is_active, $template_id);
        
        if ($stmt->execute()) {
            $success_message = "Document template updated successfully";
            // Refresh template data
            $stmt->close();
        } else {
            $error_message = "Error updating document template: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get template details
$query = "SELECT dt.*, dty.name as document_type_name, dty.category_id, 
          dc.name as category_name, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
          FROM document_templates dt 
          JOIN document_types dty ON dt.document_type_id = dty.id
          JOIN document_categories dc ON dty.category_id = dc.id
          JOIN users u ON dt.created_by = u.id
          WHERE dt.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $template_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Template not found.</div>';
    require_once 'includes/footer.php';
    exit;
}

$template = $result->fetch_assoc();
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Document Template</h1>
            <p>Editing template: <?php echo htmlspecialchars($template['name']); ?></p>
        </div>
        <div>
            <a href="documents.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Documents
            </a>
            <a href="view_template.php?id=<?php echo $template_id; ?>" class="btn secondary-btn">
                <i class="fas fa-eye"></i> View Template
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="edit-form-container">
        <form action="edit_template.php?id=<?php echo $template_id; ?>" method="POST" id="editTemplateForm">
            <div class="form-section">
                <h3>Template Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="template_name">Template Name*</label>
                        <input type="text" name="template_name" id="template_name" class="form-control" 
                               value="<?php echo htmlspecialchars($template['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        <?php echo $template['document_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['category_name'] . ' - ' . $type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="template_is_active" id="template_is_active" 
                           <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                    <label for="template_is_active">Active</label>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Template Content</h3>
                <div class="form-group">
                    <p class="content-note">
                        <i class="fas fa-info-circle"></i> 
                        You can use placeholders like <code>{applicant_name}</code>, <code>{application_date}</code>, etc. that will be replaced with actual values when generating a document.
                    </p>
                    <div class="editor-preview-container">
                        <div class="editor-column">
                            <textarea name="content" id="content" class="form-control code-editor" rows="20" required><?php echo htmlspecialchars($template['content']); ?></textarea>
                        </div>
                        <div class="preview-column">
                            <div class="preview-header">
                                <h4>Live Preview</h4>
                            </div>
                            <div class="content-preview" id="preview-pane">
                                <?php echo $template['content']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-buttons">
                <a href="documents.php" class="btn cancel-btn">Cancel</a>
                <button type="submit" name="update_template" class="btn submit-btn">Update Template</button>
            </div>
        </form>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
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

.secondary-btn {
    background-color: var(--light-color);
    color: var(--dark-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-left: 10px;
    text-decoration: none;
}

.secondary-btn:hover {
    background-color: #e9ecef;
    color: var(--dark-color);
    text-decoration: none;
}

.edit-form-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 1.2rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
}

.form-row {
    display: flex;
    gap: 20px;
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

.code-editor {
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    line-height: 1.5;
    tab-size: 4;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
}

.checkbox-group label {
    margin-bottom: 0;
}

.content-note {
    margin-top: 0;
    margin-bottom: 10px;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.content-note code {
    background-color: var(--light-color);
    padding: 2px 5px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.cancel-btn:hover {
    background-color: var(--light-color);
    text-decoration: none;
    color: var(--dark-color);
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}

.editor-preview-container {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.editor-column, .preview-column {
    flex: 1;
    min-width: 0; /* Prevents flex items from overflowing */
}

.editor-column .code-editor {
    height: 600px;
    resize: vertical;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.preview-header h4 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1rem;
}

.content-preview {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    height: 600px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    font-family: 'Nunito', sans-serif;
    line-height: 1.6;
}

.content-preview h1, 
.content-preview h2, 
.content-preview h3, 
.content-preview h4, 
.content-preview h5, 
.content-preview h6 {
    color: var(--primary-color);
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.content-preview h1 { font-size: 1.8rem; }
.content-preview h2 { font-size: 1.5rem; }
.content-preview h3 { font-size: 1.3rem; }

.content-preview p {
    margin-bottom: 1rem;
}

.content-preview ul, 
.content-preview ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.content-preview li {
    margin-bottom: 0.5rem;
}

@media (max-width: 992px) {
    .editor-preview-container {
        flex-direction: column;
    }
    
    .editor-column .code-editor,
    .content-preview {
        height: 400px;
    }
}
</style>

<script>
// Live preview functionality
document.addEventListener('DOMContentLoaded', function() {
    const contentEditor = document.getElementById('content');
    const previewPane = document.getElementById('preview-pane');
    
    // Update preview when typing in the editor
    contentEditor.addEventListener('input', function() {
        previewPane.innerHTML = this.value;
    });
    
    // Initial preview update
    previewPane.innerHTML = contentEditor.value;
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 