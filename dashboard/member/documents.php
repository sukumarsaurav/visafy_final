<?php
$page_title = "Documents";

require_once 'includes/header.php';

// Check for success/error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get document view mode from query string (list/grid)
$view_mode = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'grid';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch document categories for filter
$categories_query = "SELECT id, name FROM document_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Base query to fetch generated documents for the member
$query = "SELECT 
            gd.id, 
            gd.name, 
            gd.file_path, 
            gd.generated_date,
            dt.name AS document_type,
            dc.name AS category_name,
            dc.id AS category_id,
            CONCAT(u.first_name, ' ', u.last_name) AS client_name,
            u.email AS client_email,
            CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
            gd.email_sent,
            gd.email_sent_date
          FROM generated_documents gd
          JOIN document_types dt ON gd.document_type_id = dt.id
          JOIN document_categories dc ON dt.category_id = dc.id
          JOIN users u ON gd.client_id = u.id
          JOIN users c ON gd.created_by = c.id
          WHERE gd.created_by = ?";

// Add category filter if set
if ($category_filter > 0) {
    $query .= " AND dc.id = ?";
}

// Add search term if set
if (!empty($search_term)) {
    $query .= " AND (gd.name LIKE ? OR dt.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
}

$query .= " ORDER BY gd.generated_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if ($category_filter > 0 && !empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param("iissss", $user_id, $category_filter, $search_param, $search_param, $search_param, $search_param, $search_param);
} elseif ($category_filter > 0) {
    $stmt->bind_param("ii", $user_id, $category_filter);
} elseif (!empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param("issss", $user_id, $search_param, $search_param, $search_param, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$documents = [];

while ($document = $result->fetch_assoc()) {
    $documents[] = $document;
}
$stmt->close();

// Get document counts by category for the filter badges
$category_counts = [];
$count_query = "SELECT 
                  dc.id, 
                  dc.name, 
                  COUNT(gd.id) AS doc_count
                FROM document_categories dc
                LEFT JOIN document_types dt ON dc.id = dt.category_id
                LEFT JOIN generated_documents gd ON dt.id = gd.document_type_id AND gd.created_by = ?
                GROUP BY dc.id
                ORDER BY dc.name";

$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();

while ($count = $count_result->fetch_assoc()) {
    $category_counts[$count['id']] = $count['doc_count'];
}
$count_stmt->close();

// Get total document count
$total_documents = array_sum($category_counts);

// Fetch document templates for creating new documents
$templates_query = "SELECT 
                      dt.id,
                      dt.name,
                      dt.content,
                      dt.document_type_id,
                      dtp.name AS document_type_name,
                      dc.name AS category_name
                    FROM document_templates dt
                    JOIN document_types dtp ON dt.document_type_id = dtp.id
                    JOIN document_categories dc ON dtp.category_id = dc.id
                    WHERE dt.is_active = 1 AND dt.created_by = ?
                    ORDER BY dc.name, dtp.name, dt.name";

$templates_stmt = $conn->prepare($templates_query);
$templates_stmt->bind_param("i", $user_id);
$templates_stmt->execute();
$templates_result = $templates_stmt->get_result();
$templates = [];

while ($template = $templates_result->fetch_assoc()) {
    $templates[] = $template;
}
$templates_stmt->close();

// Fetch clients for document generation
$clients_query = "SELECT 
                    id, 
                    CONCAT(first_name, ' ', last_name) AS full_name,
                    email
                  FROM users
                  WHERE user_type = 'applicant' AND status = 'active' AND deleted_at IS NULL
                  ORDER BY first_name, last_name";

$clients_result = $conn->query($clients_query);
$clients = [];

while ($client = $clients_result->fetch_assoc()) {
    $clients[] = $client;
}
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
    
    <div class="document-dashboard">
        <div class="document-header">
            <div class="document-title">
                <h1>My Documents</h1>
                <p>Manage the documents you've created for clients</p>
            </div>
            
            <div class="document-actions">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDocumentModal">
                    <i class="fas fa-plus"></i> Create Document
                </button>
                
                <div class="view-toggle">
                    <a href="?view=grid<?php echo $category_filter ? '&category=' . $category_filter : ''; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="view-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                    </a>
                    <a href="?view=list<?php echo $category_filter ? '&category=' . $category_filter : ''; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="document-filters-bar">
            <div class="document-categories">
                <a href="?view=<?php echo $view_mode; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="category-filter <?php echo $category_filter === 0 ? 'active' : ''; ?>">
                    All Documents <span class="count"><?php echo $total_documents; ?></span>
                </a>
                
                <?php foreach ($categories as $category): ?>
                    <?php if (isset($category_counts[$category['id']]) && $category_counts[$category['id']] > 0): ?>
                        <a href="?category=<?php echo $category['id']; ?>&view=<?php echo $view_mode; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="category-filter <?php echo $category_filter === (int)$category['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($category['name']); ?> 
                            <span class="count"><?php echo $category_counts[$category['id']]; ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="document-search">
                <form action="" method="GET">
                    <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                    <?php if ($category_filter): ?>
                        <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                    <?php endif; ?>
                    
                    <div class="search-input">
                        <input type="text" name="search" placeholder="Search documents..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="far fa-file-alt"></i>
                <h3>No documents found</h3>
                <p>
                    <?php if (!empty($search_term)): ?>
                        No documents match your search criteria. Try different keywords or clear the search.
                    <?php elseif ($category_filter): ?>
                        No documents found in this category. Choose another category or create a new document.
                    <?php else: ?>
                        You haven't created any documents yet. Click the "Create Document" button to get started.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_term) || $category_filter): ?>
                    <a href="documents.php" class="btn btn-outline">Clear Filters</a>
                <?php else: ?>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createDocumentModal">
                        <i class="fas fa-plus"></i> Create Document
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($view_mode === 'grid'): ?>
                <div class="documents-grid">
                    <?php foreach ($documents as $document): ?>
                        <div class="document-card">
                            <div class="document-icon">
                                <?php
                                $file_extension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                $icon_class = 'far fa-file';
                                
                                switch (strtolower($file_extension)) {
                                    case 'pdf':
                                        $icon_class = 'far fa-file-pdf';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                        $icon_class = 'far fa-file-word';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                        $icon_class = 'far fa-file-excel';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $icon_class = 'far fa-file-powerpoint';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $icon_class = 'far fa-file-image';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                        $icon_class = 'far fa-file-archive';
                                        break;
                                    case 'txt':
                                        $icon_class = 'far fa-file-alt';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            
                            <div class="document-info">
                                <h3 title="<?php echo htmlspecialchars($document['name']); ?>">
                                    <?php echo htmlspecialchars(strlen($document['name']) > 28 ? substr($document['name'], 0, 25) . '...' : $document['name']); ?>
                                </h3>
                                
                                <div class="document-meta">
                                    <span class="document-type" title="<?php echo htmlspecialchars($document['document_type']); ?>">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($document['document_type']); ?>
                                    </span>
                                    <span class="document-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($document['generated_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="client-info" title="<?php echo htmlspecialchars($document['client_name'] . ' (' . $document['client_email'] . ')'); ?>">
                                    <i class="far fa-user"></i> <?php echo htmlspecialchars(strlen($document['client_name']) > 20 ? substr($document['client_name'], 0, 17) . '...' : $document['client_name']); ?>
                                </div>
                                
                                <div class="document-status">
                                    <?php if ($document['email_sent']): ?>
                                        <span class="status-sent">
                                            <i class="fas fa-envelope"></i> Sent <?php echo date('M d', strtotime($document['email_sent_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-unsent">
                                            <i class="far fa-envelope"></i> Not sent
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="document-actions">
                                <a href="../../<?php echo $document['file_path']; ?>" class="action-btn view" title="View Document" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if (!$document['email_sent']): ?>
                                    <a href="send_document.php?id=<?php echo $document['id']; ?>" class="action-btn send" title="Send to Client">
                                        <i class="fas fa-paper-plane"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="download_document.php?id=<?php echo $document['id']; ?>" class="action-btn download" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="documents-table-container">
                    <table class="documents-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Client</th>
                                <th>Date Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td class="document-name-cell">
                                        <?php
                                        $file_extension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                        $icon_class = 'far fa-file';
                                        
                                        switch (strtolower($file_extension)) {
                                            case 'pdf':
                                                $icon_class = 'far fa-file-pdf';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $icon_class = 'far fa-file-word';
                                                break;
                                            case 'xls':
                                            case 'xlsx':
                                                $icon_class = 'far fa-file-excel';
                                                break;
                                            case 'ppt':
                                            case 'pptx':
                                                $icon_class = 'far fa-file-powerpoint';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif':
                                                $icon_class = 'far fa-file-image';
                                                break;
                                            case 'zip':
                                            case 'rar':
                                                $icon_class = 'far fa-file-archive';
                                                break;
                                            case 'txt':
                                                $icon_class = 'far fa-file-alt';
                                                break;
                                        }
                                        ?>
                                        <i class="<?php echo $icon_class; ?>"></i>
                                        <span title="<?php echo htmlspecialchars($document['name']); ?>">
                                            <?php echo htmlspecialchars(strlen($document['name']) > 40 ? substr($document['name'], 0, 37) . '...' : $document['name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="document-category"><?php echo htmlspecialchars($document['category_name']); ?></span>
                                        <?php echo htmlspecialchars($document['document_type']); ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($document['client_email']); ?>">
                                        <?php echo htmlspecialchars($document['client_name']); ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($document['generated_date'])); ?></td>
                                    <td>
                                        <?php if ($document['email_sent']): ?>
                                            <span class="status-sent">
                                                <i class="fas fa-envelope"></i> Sent <?php echo date('M d', strtotime($document['email_sent_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-unsent">
                                                <i class="far fa-envelope"></i> Not sent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="../../<?php echo $document['file_path']; ?>" class="action-btn view" title="View Document" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (!$document['email_sent']): ?>
                                            <a href="send_document.php?id=<?php echo $document['id']; ?>" class="action-btn send" title="Send to Client">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="download_document.php?id=<?php echo $document['id']; ?>" class="action-btn download" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Create Document Modal -->
<div class="modal fade" id="createDocumentModal" tabindex="-1" aria-labelledby="createDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createDocumentModalLabel">Create New Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="create_document.php" method="post" id="createDocumentForm">
                    <div class="mb-3">
                        <label for="template_id" class="form-label">Document Template</label>
                        <select class="form-select" id="template_id" name="template_id" required>
                            <option value="">Select a template</option>
                            <?php
                            $current_category = '';
                            foreach ($templates as $template):
                                if ($current_category != $template['category_name']):
                                    if ($current_category != ''):
                                        echo '</optgroup>';
                                    endif;
                                    $current_category = $template['category_name'];
                                    echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                endif;
                                ?>
                                <option value="<?php echo $template['id']; ?>">
                                    <?php echo htmlspecialchars($template['name'] . ' (' . $template['document_type_name'] . ')'); ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_category != ''):
                                echo '</optgroup>';
                            endif;
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_name" class="form-label">Document Name</label>
                        <input type="text" class="form-control" id="document_name" name="document_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="">Select a client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" data-email="<?php echo htmlspecialchars($client['email']); ?>">
                                    <?php echo htmlspecialchars($client['full_name']); ?> (<?php echo htmlspecialchars($client['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_email" name="send_email">
                            <label class="form-check-label" for="send_email">
                                Send document to client after creation
                            </label>
                        </div>
                    </div>
                    
                    <div id="emailFields" style="display: none;">
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_message" class="form-label">Email Message</label>
                            <textarea class="form-control" id="email_message" name="email_message" rows="4"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="createDocumentForm" class="btn btn-primary">Create Document</button>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #4e73df;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --gray-color: #858796;
    
    --pdf-color: #e74c3c;
    --word-color: #4285f4;
    --excel-color: #0f9d58;
    --powerpoint-color: #db4437;
    --image-color: #4285f4;
    --archive-color: #f1c40f;
    --text-color: #95a5a6;
}

.content {
    padding: 1.5rem;
    font-family: 'Nunito', sans-serif;
}

.document-dashboard {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.document-title h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-color);
}

.document-title p {
    margin: 0.25rem 0 0;
    color: var(--gray-color);
    font-size: 0.875rem;
}

.document-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031a54;
}

.btn-secondary {
    background-color: var(--gray-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #717384;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid var(--gray-color);
    color: var(--gray-color);
}

.btn-outline:hover {
    background-color: var(--gray-color);
    color: white;
}

.view-toggle {
    display: flex;
    background-color: var(--light-color);
    border-radius: 0.25rem;
    overflow: hidden;
}

.view-btn {
    padding: 0.5rem 0.75rem;
    color: var(--gray-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.document-filters-bar {
    display: flex;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    background-color: var(--light-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    flex-wrap: wrap;
}

.document-categories {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.category-filter {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-color);
    background-color: white;
    border-radius: 1rem;
    text-decoration: none;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.category-filter.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.category-filter:hover:not(.active) {
    background-color: #f0f2fa;
}

.count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    font-size: 0.7rem;
    margin-left: 0.5rem;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 50%;
}

.category-filter.active .count {
    background-color: rgba(255, 255, 255, 0.2);
}

.document-search {
    margin-top: 0.5rem;
}

.search-input {
    position: relative;
    width: 16rem;
}

.search-input input {
    width: 100%;
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: var(--dark-color);
}

.search-input button {
    position: absolute;
    left: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-color);
    cursor: pointer;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
    text-align: center;
}

.empty-state i {
    font-size: 3.5rem;
    color: #d1d3e2;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0 0 0.5rem;
}

.empty-state p {
    max-width: 32rem;
    color: var(--gray-color);
    margin-bottom: 1.5rem;
}

/* Grid View Styles */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 1.5rem;
    padding: 1.5rem;
}

.document-card {
    display: flex;
    flex-direction: column;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.15);
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
    position: relative;
}

.document-card:hover {
    transform: translateY(-0.25rem);
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
}

.document-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem 1rem;
    font-size: 2.5rem;
}

.document-icon i.fa-file-pdf {
    color: var(--pdf-color);
}

.document-icon i.fa-file-word {
    color: var(--word-color);
}

.document-icon i.fa-file-excel {
    color: var(--excel-color);
}

.document-icon i.fa-file-powerpoint {
    color: var(--powerpoint-color);
}

.document-icon i.fa-file-image {
    color: var(--image-color);
}

.document-icon i.fa-file-archive {
    color: var(--archive-color);
}

.document-icon i.fa-file-alt, 
.document-icon i.fa-file {
    color: var(--text-color);
}

.document-info {
    padding: 0 1.25rem 1.25rem;
    flex: 1;
}

.document-info h3 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.75rem;
    color: var(--dark-color);
    line-height: 1.4;
}

.document-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 0.75rem;
}

.document-type, .document-date {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--gray-color);
}

.client-info {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--dark-color);
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}

.document-status {
    font-size: 0.75rem;
}

.status-sent {
    color: var(--success-color);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.status-unsent {
    color: var(--warning-color);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.document-actions {
    display: flex;
    background-color: var(--light-color);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem;
    color: var(--dark-color);
    text-decoration: none;
    font-size: 0.875rem;
    transition: background-color 0.2s;
}

.action-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.action-btn.view {
    color: var(--primary-color);
}

.action-btn.send {
    color: var(--info-color);
}

.action-btn.download {
    color: var(--success-color);
}

.action-btn.delete {
    color: var(--danger-color);
}

/* List View Styles */
.documents-table-container {
    padding: 1.5rem;
    overflow-x: auto;
}

.documents-table {
    width: 100%;
    border-collapse: collapse;
}

.documents-table th {
    background-color: var(--light-color);
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--dark-color);
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.documents-table td {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    color: var(--dark-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    vertical-align: middle;
}

.documents-table tr:last-child td {
    border-bottom: none;
}

.documents-table tr:hover td {
    background-color: rgba(0, 0, 0, 0.02);
}

.document-name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.document-name-cell i {
    font-size: 1.25rem;
}

.document-name-cell i.fa-file-pdf {
    color: var(--pdf-color);
}

.document-name-cell i.fa-file-word {
    color: var(--word-color);
}

.document-name-cell i.fa-file-excel {
    color: var(--excel-color);
}

.document-name-cell i.fa-file-powerpoint {
    color: var(--powerpoint-color);
}

.document-name-cell i.fa-file-image {
    color: var(--image-color);
}

.document-name-cell i.fa-file-archive {
    color: var(--archive-color);
}

.document-name-cell i.fa-file-alt, 
.document-name-cell i.fa-file {
    color: var(--text-color);
}

.document-category {
    display: inline-block;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    background-color: var(--light-color);
    border-radius: 0.25rem;
    margin-right: 0.5rem;
    color: var(--gray-color);
}

.actions-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.actions-cell .action-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 0.25rem;
    background-color: var(--light-color);
}

/* Modal Styles */
.modal-dialog {
    max-width: 40rem;
}

.modal-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.modal-title {
    font-weight: 700;
    color: var(--dark-color);
}

.modal-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.form-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--dark-color);
    margin-bottom: 0.375rem;
}

.form-select, .form-control {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    color: var(--dark-color);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-check-input {
    margin-top: 0;
}

.form-check-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--dark-color);
}

/* Alert Styles */
.alert {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 0.25rem;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border-left: 0.25rem solid var(--success-color);
    color: #0e6251;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border-left: 0.25rem solid var(--danger-color);
    color: #a93226;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .document-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .document-actions {
        align-self: stretch;
        justify-content: space-between;
    }
    
    .document-filters-bar {
        flex-direction: column;
        gap: 1rem;
    }
    
    .document-search {
        align-self: stretch;
        margin-top: 0;
    }
    
    .search-input {
        width: 100%;
    }
    
    .documents-grid {
        grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    }
}

@media (max-width: 768px) {
    .documents-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle email fields when checkbox is checked
    const sendEmailCheckbox = document.getElementById('send_email');
    const emailFields = document.getElementById('emailFields');
    
    if (sendEmailCheckbox && emailFields) {
        sendEmailCheckbox.addEventListener('change', function() {
            emailFields.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Auto-fill email subject when client is selected
    const clientSelect = document.getElementById('client_id');
    const emailSubject = document.getElementById('email_subject');
    const documentName = document.getElementById('document_name');
    
    if (clientSelect && emailSubject && documentName) {
        clientSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value && documentName.value) {
                emailSubject.value = 'Document: ' + documentName.value;
            }
        });
        
        documentName.addEventListener('input', function() {
            if (clientSelect.value && this.value) {
                emailSubject.value = 'Document: ' + this.value;
            }
        });
    }
    
    // Default email message
    const emailMessage = document.getElementById('email_message');
    if (emailMessage) {
        emailMessage.value = 'Dear Client,\n\nPlease find attached the requested document. If you have any questions, feel free to contact us.\n\nBest regards,\nThe Visafy Team';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
