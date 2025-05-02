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

$page_title = "View Document Template";
$page_specific_css = "assets/css/templates.css";
require_once 'includes/header.php';

// Get template id from query string
$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($template_id <= 0) {
    echo '<div class="alert alert-danger">Invalid template ID.</div>';
    require_once 'includes/footer.php';
    exit;
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
            <h1>View Document Template</h1>
            <p>Viewing template: <?php echo htmlspecialchars($template['name']); ?></p>
        </div>
        <div>
            <a href="documents.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Documents
            </a>
            <a href="edit_template.php?id=<?php echo $template_id; ?>" class="btn primary-btn">
                <i class="fas fa-edit"></i> Edit Template
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="template-details">
        <!-- Template Info Card - Full Width in First Row -->
        <div class="info-card full-width">
            <h3>Template Information</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($template['name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Document Type:</div>
                    <div class="info-value"><?php echo htmlspecialchars($template['document_type_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Category:</div>
                    <div class="info-value"><?php echo htmlspecialchars($template['category_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <?php if ($template['is_active']): ?>
                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                        <?php else: ?>
                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created By:</div>
                    <div class="info-value"><?php echo htmlspecialchars($template['created_by_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created Date:</div>
                    <div class="info-value"><?php echo date('M d, Y H:i', strtotime($template['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Code and Preview Row - Split View in Second Row -->
        <div class="template-content-row">
            <!-- HTML Code on Left -->
            <div class="template-code">
                <h3>Template Code</h3>
                <div class="content-preview code-view">
                    <?php echo nl2br(htmlspecialchars($template['content'])); ?>
                </div>
            </div>
            
            <!-- Rendered Preview on Right -->
            <div class="template-preview">
                <h3>Rendered Preview</h3>
                <div class="content-preview preview-view">
                    <?php echo $template['content']; ?>
                </div>
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

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
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

.primary-btn:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
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
    text-decoration: none;
}

.secondary-btn:hover {
    background-color: #e9ecef;
    color: var(--dark-color);
    text-decoration: none;
}

.template-details {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 20px;
}

.full-width {
    width: 100%;
}

.info-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.info-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 1.2rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 15px;
}

.info-row {
    display: flex;
    margin-bottom: 10px;
}

.info-label {
    width: 120px;
    font-weight: 600;
    color: var(--dark-color);
}

.info-value {
    flex: 1;
    color: var(--dark-color);
}

.template-content-row {
    display: flex;
    gap: 20px;
}

.template-code, .template-preview {
    flex: 1;
    min-width: 0; /* Prevents flex items from overflowing */
}

.template-code h3, .template-preview h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.content-preview {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    height: 600px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
}

.code-view {
    white-space: pre-wrap;
    font-family: 'Courier New', monospace;
}

.preview-view {
    font-family: 'Nunito', sans-serif;
    line-height: 1.6;
}

.preview-view h1, 
.preview-view h2, 
.preview-view h3, 
.preview-view h4, 
.preview-view h5, 
.preview-view h6 {
    color: var(--primary-color);
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.preview-view h1 { font-size: 1.8rem; }
.preview-view h2 { font-size: 1.5rem; }
.preview-view h3 { font-size: 1.3rem; }

.preview-view p {
    margin-bottom: 1rem;
}

.preview-view ul, 
.preview-view ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.preview-view li {
    margin-bottom: 0.5rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge i {
    font-size: 8px;
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

@media (max-width: 992px) {
    .template-content-row {
        flex-direction: column;
    }
    
    .content-preview {
        height: 400px;
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Toggle between raw template and rendered preview
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Hide all content previews
        document.querySelectorAll('.content-preview').forEach(preview => {
            preview.classList.remove('active');
        });
        
        // Show selected content preview
        const viewType = this.getAttribute('data-view');
        document.getElementById(viewType + '-view').classList.add('active');
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 