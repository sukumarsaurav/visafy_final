<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Documents";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';

// Get all document categories
try {
    $query = "SELECT * FROM document_categories ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id']] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching document categories: " . $e->getMessage());
    $categories = [];
}

// Get all documents for the current user
try {
    $query = "SELECT ad.id, ad.file_path, ad.status, dt.name as document_name, dt.category_id, 
              a.reference_number, a.id as application_id, v.visa_type, ad.created_at, ad.updated_at
              FROM application_documents ad
              JOIN applications a ON ad.application_id = a.id
              JOIN document_types dt ON ad.document_type_id = dt.id
              JOIN visas v ON a.visa_id = v.visa_id
              WHERE a.user_id = ?
              ORDER BY ad.updated_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = [];
    
    while ($row = $result->fetch_assoc()) {
        $category_id = $row['category_id'];
        if (!isset($documents[$category_id])) {
            $documents[$category_id] = [];
        }
        $documents[$category_id][] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching documents: " . $e->getMessage());
    $error_message = "Error loading documents. Please try again later.";
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Documents</h1>
            <p>View and manage your application documents</p>
        </div>
        <div>
            <a href="upload_document.php" class="btn primary-btn">
                <i class="fas fa-upload"></i> Upload Document
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- Document filters -->
    <div class="filter-container">
        <div class="filter-item">
            <label for="category-filter">Category:</label>
            <select id="category-filter" class="filter-select">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-select">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        
        <div class="filter-item search-container">
            <input type="text" id="document-search" placeholder="Search documents..." class="search-input">
            <button id="search-btn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <!-- Document Categories -->
    <div class="documents-container">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>You don't have any documents yet.</p>
                <a href="upload_document.php" class="btn-link">Upload your first document</a>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category_id => $category): ?>
                <?php if (isset($documents[$category_id])): ?>
                    <div class="document-category" data-category="<?php echo $category_id; ?>">
                        <div class="category-header">
                            <h2><i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?></h2>
                            <span class="doc-count"><?php echo count($documents[$category_id]); ?> document<?php echo count($documents[$category_id]) > 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="document-list">
                            <?php foreach ($documents[$category_id] as $document): ?>
                                <div class="document-card" data-status="<?php echo $document['status']; ?>">
                                    <div class="doc-icon">
                                        <?php
                                        $file_ext = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                        $icon_class = 'fa-file';
                                        
                                        switch (strtolower($file_ext)) {
                                            case 'pdf':
                                                $icon_class = 'fa-file-pdf';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                                $icon_class = 'fa-file-image';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $icon_class = 'fa-file-word';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <div class="doc-details">
                                        <h3><?php echo htmlspecialchars($document['document_name']); ?></h3>
                                        <p class="app-info">
                                            <?php echo htmlspecialchars($document['visa_type']); ?> - 
                                            #<?php echo htmlspecialchars($document['reference_number']); ?>
                                        </p>
                                        <p class="doc-date">
                                            Uploaded: <?php echo date('M j, Y', strtotime($document['created_at'])); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="doc-status">
                                        <span class="status-badge status-<?php echo $document['status']; ?>">
                                            <?php echo ucfirst($document['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="doc-actions">
                                        <a href="view_document.php?id=<?php echo $document['id']; ?>" class="btn-icon" title="View Document">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($document['status'] !== 'approved'): ?>
                                            <a href="upload_document.php?replace=<?php echo $document['id']; ?>" class="btn-icon" title="Replace Document">
                                                <i class="fas fa-upload"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
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

.filter-container {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    background-color: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    flex-wrap: wrap;
}

.filter-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-select {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-width: 150px;
}

.search-container {
    margin-left: auto;
    display: flex;
}

.search-input {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px 0 0 4px;
    min-width: 200px;
}

.search-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
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

.document-category {
    margin-bottom: 20px;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.category-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.doc-count {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.document-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 15px;
}

.document-card {
    display: flex;
    align-items: center;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    padding: 15px;
    gap: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.doc-icon {
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 24px;
    color: var(--primary-color);
}

.doc-details {
    flex: 1;
    min-width: 0;
}

.doc-details h3 {
    margin: 0 0 5px;
    font-size: 1rem;
    color: var(--dark-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.doc-details p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.status-badge {
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.status-pending {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.status-submitted {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-approved {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.doc-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: var(--light-color);
    color: var(--primary-color);
    border-radius: 4px;
    text-decoration: none;
    transition: background-color 0.2s;
}

.btn-icon:hover {
    background-color: #e8ecfc;
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
    
    .filter-container {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-container {
        width: 100%;
        margin-left: 0;
    }
    
    .document-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('category-filter');
    const statusFilter = document.getElementById('status-filter');
    const searchInput = document.getElementById('document-search');
    const searchBtn = document.getElementById('search-btn');
    
    // Filter function
    function filterDocuments() {
        const categoryValue = categoryFilter.value;
        const statusValue = statusFilter.value;
        const searchValue = searchInput.value.toLowerCase();
        
        // Get all document categories
        const categories = document.querySelectorAll('.document-category');
        
        categories.forEach(category => {
            // Category filter
            if (categoryValue !== 'all' && category.dataset.category !== categoryValue) {
                category.style.display = 'none';
                return;
            } else {
                category.style.display = 'block';
            }
            
            // Get all documents in this category
            const documents = category.querySelectorAll('.document-card');
            let visibleCount = 0;
            
            documents.forEach(doc => {
                // Status filter
                if (statusValue !== 'all' && doc.dataset.status !== statusValue) {
                    doc.style.display = 'none';
                    return;
                }
                
                // Search filter
                if (searchValue) {
                    const docText = doc.textContent.toLowerCase();
                    if (!docText.includes(searchValue)) {
                        doc.style.display = 'none';
                        return;
                    }
                }
                
                // If passed all filters, show document
                doc.style.display = 'flex';
                visibleCount++;
            });
            
            // Hide category if no documents visible
            if (visibleCount === 0) {
                category.style.display = 'none';
            }
        });
    }
    
    // Add event listeners
    categoryFilter.addEventListener('change', filterDocuments);
    statusFilter.addEventListener('change', filterDocuments);
    searchBtn.addEventListener('click', filterDocuments);
    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            filterDocuments();
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 