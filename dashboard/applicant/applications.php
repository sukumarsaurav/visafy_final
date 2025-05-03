<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Applications";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Get all applications for the current user
try {
    $query = "SELECT a.id, a.reference_number, v.visa_type, c.country_name, 
               s.name AS status, s.color AS status_color, a.created_at, a.updated_at,
               (SELECT COUNT(*) FROM application_documents ad WHERE ad.application_id = a.id) AS document_count,
               (SELECT COUNT(*) FROM application_documents ad WHERE ad.application_id = a.id AND ad.status = 'approved') AS approved_docs,
               (SELECT COUNT(*) FROM application_documents ad WHERE ad.application_id = a.id AND ad.status = 'pending') AS pending_docs
               FROM applications a
               JOIN visas v ON a.visa_id = v.visa_id
               JOIN countries c ON v.country_id = c.country_id
               JOIN application_statuses s ON a.status_id = s.id
               WHERE a.user_id = ? AND a.deleted_at IS NULL
               ORDER BY a.updated_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    $error_message = "Error loading applications. Please try again later.";
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Applications</h1>
            <p>Track and manage your visa applications</p>
        </div>
        <div>
            <a href="applications.php?action=new" class="btn primary-btn">
                <i class="fas fa-plus"></i> New Application
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- Applications List -->
    <div class="applications-container">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>You don't have any visa applications yet.</p>
                <a href="applications.php?action=new" class="btn-link">Start your first application</a>
            </div>
        <?php else: ?>
            <div class="applications-grid">
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="app-header">
                            <div class="ref-number">#<?php echo htmlspecialchars($app['reference_number']); ?></div>
                            <div class="status-badge" style="background-color: <?php echo $app['status_color']; ?>20; color: <?php echo $app['status_color']; ?>;">
                                <i class="fas fa-circle"></i> <?php echo htmlspecialchars($app['status']); ?>
                            </div>
                        </div>
                        <div class="app-details">
                            <h3><?php echo htmlspecialchars($app['visa_type']); ?></h3>
                            <p class="country"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($app['country_name']); ?></p>
                            <p class="date"><i class="fas fa-calendar-alt"></i> Submitted: <?php echo date('M j, Y', strtotime($app['created_at'])); ?></p>
                            
                            <div class="doc-progress">
                                <div class="progress-label">
                                    <span>Documents</span>
                                    <span><?php echo $app['approved_docs']; ?>/<?php echo $app['document_count']; ?> approved</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo ($app['document_count'] > 0) ? ($app['approved_docs'] / $app['document_count'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <?php if ($app['pending_docs'] > 0): ?>
                                <div class="pending-docs">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $app['pending_docs']; ?> document<?php echo $app['pending_docs'] > 1 ? 's' : ''; ?> pending
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="app-actions">
                            <a href="application_details.php?id=<?php echo $app['id']; ?>" class="btn btn-primary">
                                View Details
                            </a>
                            <a href="application_documents.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary">
                                Manage Documents
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: white;
    border-radius: 5px;
    padding: 50px 20px;
    text-align: center;
    color: var(--secondary-color);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0 0 15px;
}

.btn-link {
    color: var(--primary-color);
    text-decoration: none;
}

.btn-link:hover {
    text-decoration: underline;
}

.applications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.application-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.application-card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.app-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.ref-number {
    font-weight: 600;
    color: var(--primary-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge i {
    font-size: 8px;
}

.app-details {
    padding: 15px;
}

.app-details h3 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.app-details p {
    margin: 5px 0;
    color: var(--secondary-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.doc-progress {
    margin: 15px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 5px;
}

.progress-bar {
    height: 8px;
    background-color: var(--border-color);
    border-radius: 4px;
    overflow: hidden;
}

.progress {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 4px;
}

.pending-docs {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--warning-color);
    font-size: 13px;
    margin-top: 10px;
}

.app-actions {
    padding: 15px;
    display: flex;
    gap: 10px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    text-align: center;
    transition: all 0.2s;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    flex: 1;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-secondary {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    flex: 1;
}

.btn-secondary:hover {
    background-color: #f0f2fa;
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .applications-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 