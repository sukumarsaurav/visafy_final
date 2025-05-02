<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Document Management";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';

// Current tab management
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'templates';
$valid_tabs = ['templates', 'categories', 'types', 'documents'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'templates';
}

// Add Document Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_desc = trim($_POST['category_description']);
    
    // Validate input
    if (empty($category_name)) {
        $error_message = "Category name is required.";
    } else {
        // Check if category already exists
        $check_query = "SELECT id FROM document_categories WHERE name = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('s', $category_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Category name already exists.";
        } else {
            // Insert new category
            $insert_query = "INSERT INTO document_categories (name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('ss', $category_name, $category_desc);
            
            if ($stmt->execute()) {
                $success_message = "Document category added successfully.";
                // Redirect to avoid form resubmission
                header("Location: documents.php?tab=categories&success=1");
                exit;
            } else {
                $error_message = "Error adding category: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Add Document Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $type_name = trim($_POST['type_name']);
    $category_id = (int)$_POST['category_id'];
    $type_desc = trim($_POST['type_description']);
    
    // Validate input
    if (empty($type_name)) {
        $error_message = "Document type name is required.";
    } elseif ($category_id <= 0) {
        $error_message = "Please select a valid category.";
    } else {
        // Check if type already exists
        $check_query = "SELECT id FROM document_types WHERE name = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('s', $type_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Document type name already exists.";
        } else {
            // Insert new type
            $insert_query = "INSERT INTO document_types (category_id, name, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('iss', $category_id, $type_name, $type_desc);
            
            if ($stmt->execute()) {
                $success_message = "Document type added successfully.";
                // Redirect to avoid form resubmission
                header("Location: documents.php?tab=types&success=2");
                exit;
            } else {
                $error_message = "Error adding document type: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Handle Document Template Creation or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $template_name = trim($_POST['template_name']);
    $document_type_id = (int)$_POST['document_type_id'];
    $template_content = $_POST['template_content'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate input
    if (empty($template_name)) {
        $error_message = "Template name is required.";
    } elseif ($document_type_id <= 0) {
        $error_message = "Please select a valid document type.";
    } elseif (empty($template_content)) {
        $error_message = "Template content cannot be empty.";
    } else {
        // Check if template exists (for update) or if name is unique (for new)
        if ($template_id > 0) {
            // Update existing template
            $update_query = "UPDATE document_templates SET name = ?, document_type_id = ?, 
                           content = ?, is_active = ?, updated_at = NOW() 
                           WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('sisii', $template_name, $document_type_id, $template_content, $is_active, $template_id);
            
            if ($stmt->execute()) {
                $success_message = "Document template updated successfully.";
                // Redirect to avoid form resubmission
                header("Location: documents.php?tab=templates&success=3");
                exit;
            } else {
                $error_message = "Error updating template: " . $conn->error;
            }
        } else {
            // Check for existing template name
            $check_query = "SELECT id FROM document_templates WHERE name = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('s', $template_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Template name already exists.";
            } else {
                // Insert new template
                $insert_query = "INSERT INTO document_templates (name, document_type_id, content, is_active, created_by) 
                               VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $user_id = $_SESSION['id']; // Assuming user ID is stored in session
                $stmt->bind_param('sisis', $template_name, $document_type_id, $template_content, $is_active, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Document template created successfully.";
                    // Redirect to avoid form resubmission
                    header("Location: documents.php?tab=templates&success=4");
                    exit;
                } else {
                    $error_message = "Error creating template: " . $conn->error;
                }
            }
        }
        $stmt->close();
    }
}

// Handle Template Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $template_id = (int)$_POST['template_id'];
    
    $delete_query = "DELETE FROM document_templates WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $template_id);
    
    if ($stmt->execute()) {
        $success_message = "Document template deleted successfully.";
        // Redirect to avoid form resubmission
        header("Location: documents.php?tab=templates&success=5");
        exit;
    } else {
        $error_message = "Error deleting template: " . $conn->error;
    }
    $stmt->close();
}

// Handle Generate Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_document'])) {
    $template_id = (int)$_POST['template_id'];
    $client_id = (int)$_POST['client_id'];
    $document_name = trim($_POST['document_name']);
    
    // Validate input
    if (empty($document_name)) {
        $error_message = "Document name is required.";
    } elseif ($template_id <= 0) {
        $error_message = "Please select a valid template.";
    } elseif ($client_id <= 0) {
        $error_message = "Please select a valid client.";
    } else {
        // Get template content
        $template_query = "SELECT t.content, t.document_type_id, dt.name as type_name 
                          FROM document_templates t 
                          JOIN document_types dt ON t.document_type_id = dt.id 
                          WHERE t.id = ?";
        $stmt = $conn->prepare($template_query);
        $stmt->bind_param('i', $template_id);
        $stmt->execute();
        $template_result = $stmt->get_result();
        
        if ($template_result->num_rows === 0) {
            $error_message = "Template not found.";
        } else {
            $template_data = $template_result->fetch_assoc();
            $template_content = $template_data['content'];
            $document_type_id = $template_data['document_type_id'];
            
            // Get client data for placeholders
            $client_query = "SELECT * FROM users WHERE id = ? AND user_type = 'applicant'";
            $stmt = $conn->prepare($client_query);
            $stmt->bind_param('i', $client_id);
            $stmt->execute();
            $client_result = $stmt->get_result();
            
            if ($client_result->num_rows === 0) {
                $error_message = "Client not found.";
            } else {
                $client_data = $client_result->fetch_assoc();
                
                // Replace placeholders in template with client data
                $processed_content = $template_content;
                $processed_content = str_replace('{client_name}', $client_data['first_name'] . ' ' . $client_data['last_name'], $processed_content);
                $processed_content = str_replace('{client_email}', $client_data['email'], $processed_content);
                $processed_content = str_replace('{current_date}', date('F d, Y'), $processed_content);
                
                // Generate unique filename
                $filename = strtolower(str_replace(' ', '_', $document_name)) . '_' . time() . '.html';
                $filepath = '../../uploads/documents/' . $filename;
                
                // Save the HTML file
                if (file_put_contents($filepath, $processed_content)) {
                    // Insert into generated documents table
                    $insert_query = "INSERT INTO generated_documents (name, document_type_id, template_id, 
                                   client_id, file_path, created_by, generated_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($insert_query);
                    $user_id = $_SESSION['id']; // Assuming user ID is stored in session
                    $stmt->bind_param('siiisi', $document_name, $document_type_id, $template_id, $client_id, $filename, $user_id);
                    
                    if ($stmt->execute()) {
                        $document_id = $conn->insert_id;
                        $success_message = "Document generated successfully.";
                        // Redirect to preview page
                        header("Location: view_document.php?id={$document_id}&success=1");
                        exit;
                    } else {
                        $error_message = "Error saving document record: " . $conn->error;
                    }
                } else {
                    $error_message = "Error saving document file.";
                }
            }
        }
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Document category added successfully.";
            break;
        case 2:
            $success_message = "Document type added successfully.";
            break;
        case 3:
            $success_message = "Document template updated successfully.";
            break;
        case 4:
            $success_message = "Document template created successfully.";
            break;
        case 5:
            $success_message = "Document template deleted successfully.";
            break;
    }
}

// Fetch all document categories
$categories_query = "SELECT * FROM document_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch all document types
$types_query = "SELECT dt.*, dc.name as category_name 
                FROM document_types dt 
                JOIN document_categories dc ON dt.category_id = dc.id 
                ORDER BY dc.name, dt.name";
$types_result = $conn->query($types_query);
$types = [];
if ($types_result && $types_result->num_rows > 0) {
    while ($row = $types_result->fetch_assoc()) {
        $types[] = $row;
    }
}

// Fetch all document templates
$templates_query = "SELECT dt.*, u.first_name, u.last_name, doc.name as document_type_name, 
                    cat.name as category_name
                    FROM document_templates dt
                    JOIN document_types doc ON dt.document_type_id = doc.id
                    JOIN document_categories cat ON doc.category_id = cat.id
                    JOIN users u ON dt.created_by = u.id
                    ORDER BY dt.created_at DESC";
$templates_result = $conn->query($templates_query);
$templates = [];
if ($templates_result && $templates_result->num_rows > 0) {
    while ($row = $templates_result->fetch_assoc()) {
        $templates[] = $row;
    }
}

// Fetch generated documents
$documents_query = "SELECT gd.*, dt.name as document_type, 
                    CONCAT(client.first_name, ' ', client.last_name) as client_name,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                    FROM generated_documents gd
                    JOIN document_types dt ON gd.document_type_id = dt.id
                    JOIN users client ON gd.client_id = client.id
                    JOIN users creator ON gd.created_by = creator.id
                    ORDER BY gd.generated_date DESC";
$documents_result = $conn->query($documents_query);
$documents = [];
if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}

// Fetch all clients for document generation
$clients_query = "SELECT id, first_name, last_name, email FROM users 
                 WHERE user_type = 'applicant' AND status = 'active' AND deleted_at IS NULL
                 ORDER BY first_name, last_name";
$clients_result = $conn->query($clients_query);
$clients = [];
if ($clients_result && $clients_result->num_rows > 0) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Document Management</h1>
            <p>Create and manage document templates, categories, and types.</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=templates" class="tab <?php echo $current_tab === 'templates' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Document Templates
            </a>
            <a href="?tab=categories" class="tab <?php echo $current_tab === 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-folder"></i> Categories
            </a>
            <a href="?tab=types" class="tab <?php echo $current_tab === 'types' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Document Types
            </a>
            <a href="?tab=documents" class="tab <?php echo $current_tab === 'documents' ? 'active' : ''; ?>">
                <i class="fas fa-file-pdf"></i> Generated Documents
            </a>
        </div>
        
        <!-- Action Button -->
        <div class="tab-actions">
            <?php if ($current_tab === 'templates'): ?>
                <button class="btn primary-btn" id="createTemplateBtn">
                    <i class="fas fa-plus"></i> Create Template
                </button>
            <?php elseif ($current_tab === 'categories'): ?>
                <button class="btn primary-btn" id="addCategoryBtn">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            <?php elseif ($current_tab === 'types'): ?>
                <button class="btn primary-btn" id="addTypeBtn">
                    <i class="fas fa-plus"></i> Add Document Type
                </button>
            <?php elseif ($current_tab === 'documents'): ?>
                <button class="btn primary-btn" id="generateDocumentBtn">
                    <i class="fas fa-file-export"></i> Generate Document
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Templates Tab -->
        <?php if ($current_tab === 'templates'): ?>
            <div class="card tab-templates" data-tab="templates">
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No document templates created yet.</p>
                            <button class="btn primary-btn" id="emptyCreateTemplateBtn">
                                <i class="fas fa-plus"></i> Create First Template
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Document Type</th>
                                    <th>Category</th>
                                    <th>Created By</th>
                                    <th>Created Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($template['name']); ?></td>
                                        <td><?php echo htmlspecialchars($template['document_type_name']); ?></td>
                                        <td><?php echo htmlspecialchars($template['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($template['first_name'] . ' ' . $template['last_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $template['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="btn-action btn-edit edit-template-btn" 
                                                    data-id="<?php echo $template['id']; ?>" 
                                                    title="Edit Template">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-view preview-template-btn" 
                                                    data-id="<?php echo $template['id']; ?>" 
                                                    title="Preview Template">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-generate generate-doc-btn" 
                                                    data-id="<?php echo $template['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($template['name']); ?>"
                                                    title="Generate Document">
                                                <i class="fas fa-file-export"></i>
                                            </button>
                                            <button class="btn-action btn-delete delete-template-btn" 
                                                    data-id="<?php echo $template['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($template['name']); ?>"
                                                    title="Delete Template">
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
        <?php endif; ?>
        
        <!-- Categories Tab -->
        <?php if ($current_tab === 'categories'): ?>
            <div class="card tab-categories" data-tab="categories">
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder"></i>
                            <p>No document categories created yet.</p>
                            <button class="btn primary-btn" id="emptyAddCategoryBtn">
                                <i class="fas fa-plus"></i> Add First Category
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                        <td class="actions-cell">
                                            <button class="btn-action btn-edit edit-category-btn" 
                                                    data-id="<?php echo $category['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                    data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                                                    title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Document Types Tab -->
        <?php if ($current_tab === 'types'): ?>
            <div class="card tab-types" data-tab="types">
                <div class="card-body">
                    <?php if (empty($types)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <p>No document types created yet.</p>
                            <button class="btn primary-btn" id="emptyAddTypeBtn">
                                <i class="fas fa-plus"></i> Add First Document Type
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['name']); ?></td>
                                        <td><?php echo htmlspecialchars($type['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($type['description'] ?? ''); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $type['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="btn-action btn-edit edit-type-btn" 
                                                    data-id="<?php echo $type['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                    data-category="<?php echo $type['category_id']; ?>"
                                                    data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                                    data-active="<?php echo $type['is_active']; ?>"
                                                    title="Edit Document Type">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Generated Documents Tab -->
        <?php if ($current_tab === 'documents'): ?>
            <div class="card tab-documents" data-tab="documents">
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-pdf"></i>
                            <p>No documents generated yet.</p>
                            <button class="btn primary-btn" id="emptyGenerateDocBtn">
                                <i class="fas fa-file-export"></i> Generate First Document
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Document Name</th>
                                    <th>Document Type</th>
                                    <th>Client</th>
                                    <th>Created By</th>
                                    <th>Generated Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $document): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($document['name']); ?></td>
                                        <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                                        <td><?php echo htmlspecialchars($document['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($document['created_by_name']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($document['generated_date'])); ?></td>
                                        <td class="actions-cell">
                                            <a href="view_document.php?id=<?php echo $document['id']; ?>" class="btn-action btn-view" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download_document.php?id=<?php echo $document['id']; ?>" class="btn-action btn-download" title="Download Document">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="email_document.php?id=<?php echo $document['id']; ?>" class="btn-action btn-email" title="Email Document">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal" id="addCategoryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Category</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php?tab=categories" method="POST">
                    <div class="form-group">
                        <label for="category_name">Category Name*</label>
                        <input type="text" id="category_name" name="category_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="category_description">Description</label>
                        <textarea id="category_description" name="category_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" class="btn submit-btn">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Document Type Modal -->
<div class="modal" id="addTypeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Type</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php?tab=types" method="POST">
                    <div class="form-group">
                        <label for="type_name">Type Name*</label>
                        <input type="text" id="type_name" name="type_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Category*</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type_description">Description</label>
                        <textarea id="type_description" name="type_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_type" class="btn submit-btn">Save Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Template Modal -->
<div class="modal" id="templateModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="templateModalTitle">Create Document Template</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php?tab=templates" method="POST" id="templateForm">
                    <input type="hidden" id="template_id" name="template_id" value="">
                    
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="template_name">Template Name*</label>
                            <input type="text" id="template_name" name="template_name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="document_type_id">Document Type*</label>
                            <select id="document_type_id" name="document_type_id" class="form-control" required>
                                <option value="">Select document type</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name'] . ' (' . $type['category_name'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_content">Template Content*</label>
                        <p class="text-muted small">Use the following placeholders to insert client data: {client_name}, {client_email}, {current_date}</p>
                        <textarea id="template_content" name="template_content" class="form-control rich-editor" rows="15" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="is_active" name="is_active" class="form-check-input" checked>
                            <label for="is_active" class="form-check-label">Active</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_template" class="btn submit-btn">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Generate Document Modal -->
<div class="modal" id="generateDocumentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Generate Document</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php?tab=documents" method="POST">
                    <div class="form-group">
                        <label for="template_id_gen">Template*</label>
                        <select id="template_id_gen" name="template_id" class="form-control" required>
                            <option value="">Select template</option>
                            <?php foreach ($templates as $template): ?>
                                <?php if ($template['is_active']): ?>
                                <option value="<?php echo $template['id']; ?>">
                                    <?php echo htmlspecialchars($template['name']); ?> (<?php echo htmlspecialchars($template['document_type_name']); ?>)
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_id">Client*</label>
                        <select id="client_id" name="client_id" class="form-control" required>
                            <option value="">Select client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_name">Document Name*</label>
                        <input type="text" id="document_name" name="document_name" class="form-control" required>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_document" class="btn submit-btn">Generate Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Template Confirmation Form -->
<form id="deleteTemplateForm" action="documents.php?tab=templates" method="POST" style="display: none;">
    <input type="hidden" name="template_id" id="delete_template_id">
    <input type="hidden" name="delete_template" value="1">
</form>

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

/* Tab Content Visibility */
.tab-content > div {
    display: none;
}

.tab-templates[data-tab="templates"],
.tab-categories[data-tab="categories"],
.tab-types[data-tab="types"],
.tab-documents[data-tab="documents"] {
    display: block;
}

/* If JavaScript is disabled, this ensures PHP rendered content is visible */
<?php if ($current_tab === 'templates'): ?>
.tab-templates { display: block !important; }
<?php elseif ($current_tab === 'categories'): ?>
.tab-categories { display: block !important; }
<?php elseif ($current_tab === 'types'): ?>
.tab-types { display: block !important; }
<?php elseif ($current_tab === 'documents'): ?>
.tab-documents { display: block !important; }
<?php endif; ?>

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

.tabs-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tabs {
    display: flex;
    overflow-x: auto;
    border-bottom: 1px solid var(--border-color);
}

.tab {
    padding: 10px 15px;
    color: var(--secondary-color);
    text-decoration: none;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 5px;
}

.tab:hover {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

.tab i {
    font-size: 14px;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.card-body {
    padding: 20px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.data-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
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

.actions-cell {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
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

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
    background-color: #d44235;
}

.btn-generate {
    background-color: #4e73df;
}

.btn-generate:hover {
    background-color: #4262c3;
}

.btn-download {
    background-color: #36b9cc;
}

.btn-download:hover {
    background-color: #2ca8b9;
}

.btn-email {
    background-color: #1cc88a;
}

.btn-email:hover {
    background-color: #18b07b;
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

.empty-state button {
    margin-top: 15px;
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
    margin: 50px auto;
    max-width: 500px;
}

.modal-dialog.modal-lg {
    max-width: 800px;
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

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-check-input {
    width: 16px;
    height: 16px;
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

.text-muted {
    color: var(--secondary-color);
}

.small {
    font-size: 12px;
}

@media (max-width: 768px) {
    .tabs-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize rich text editor for template content when template modal is opened
    let editor;
    
    // Functions to handle modal visibility
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        
        // Initialize editor when template modal is opened
        if (modalId === 'templateModal' && document.getElementById('template_content')) {
            // Check if CKEditor is available
            if (typeof ClassicEditor !== 'undefined') {
                // Only initialize if not already initialized
                if (!editor) {
                    ClassicEditor
                        .create(document.getElementById('template_content'), {
                            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable'],
                            placeholder: 'Enter template content here...'
                        })
                        .then(newEditor => {
                            editor = newEditor;
                        })
                        .catch(error => {
                            console.error(error);
                        });
                }
            }
        }
    }
    
    function closeModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.style.display = 'none';
        });
    }
    
    // Modal functionality for add category
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const emptyAddCategoryBtn = document.getElementById('emptyAddCategoryBtn');
    
    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', function() {
            openModal('addCategoryModal');
        });
    }
    
    if (emptyAddCategoryBtn) {
        emptyAddCategoryBtn.addEventListener('click', function() {
            openModal('addCategoryModal');
        });
    }
    
    // Modal functionality for add document type
    const addTypeBtn = document.getElementById('addTypeBtn');
    const emptyAddTypeBtn = document.getElementById('emptyAddTypeBtn');
    
    if (addTypeBtn) {
        addTypeBtn.addEventListener('click', function() {
            openModal('addTypeModal');
        });
    }
    
    if (emptyAddTypeBtn) {
        emptyAddTypeBtn.addEventListener('click', function() {
            openModal('addTypeModal');
        });
    }
    
    // Modal functionality for create/edit template
    const createTemplateBtn = document.getElementById('createTemplateBtn');
    const emptyCreateTemplateBtn = document.getElementById('emptyCreateTemplateBtn');
    const templateForm = document.getElementById('templateForm');
    const templateModalTitle = document.getElementById('templateModalTitle');
    
    if (createTemplateBtn) {
        createTemplateBtn.addEventListener('click', function() {
            // Reset form for creating new template
            templateForm.reset();
            document.getElementById('template_id').value = '';
            templateModalTitle.textContent = 'Create Document Template';
            
            // If editor exists, set empty content
            if (editor) {
                editor.setData('');
            }
            
            openModal('templateModal');
        });
    }
    
    if (emptyCreateTemplateBtn) {
        emptyCreateTemplateBtn.addEventListener('click', function() {
            // Reset form for creating new template
            templateForm.reset();
            document.getElementById('template_id').value = '';
            templateModalTitle.textContent = 'Create Document Template';
            
            // If editor exists, set empty content
            if (editor) {
                editor.setData('');
            }
            
            openModal('templateModal');
        });
    }
    
    // Edit template buttons
    const editTemplateBtns = document.querySelectorAll('.edit-template-btn');
    editTemplateBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const templateId = this.getAttribute('data-id');
            
            // Fetch template data using AJAX
            fetch('ajax/get_template.php?id=' + templateId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fill form with template data
                        document.getElementById('template_id').value = data.template.id;
                        document.getElementById('template_name').value = data.template.name;
                        document.getElementById('document_type_id').value = data.template.document_type_id;
                        
                        // Open modal before setting content
                        templateModalTitle.textContent = 'Edit Document Template';
                        openModal('templateModal');
                        
                        // Set template content with slight delay to ensure editor is ready
                        setTimeout(() => {
                            if (editor) {
                                editor.setData(data.template.content);
                            } else {
                                document.getElementById('template_content').value = data.template.content;
                            }
                            
                            document.getElementById('is_active').checked = data.template.is_active == 1;
                        }, 100);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching template data');
                });
        });
    });
    
    // Delete template buttons
    const deleteTemplateBtns = document.querySelectorAll('.delete-template-btn');
    deleteTemplateBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const templateId = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-name');
            
            if (confirm('Are you sure you want to delete the template "' + templateName + '"? This action cannot be undone.')) {
                document.getElementById('delete_template_id').value = templateId;
                document.getElementById('deleteTemplateForm').submit();
            }
        });
    });
    
    // Generate document from template
    const generateDocBtns = document.querySelectorAll('.generate-doc-btn');
    const generateDocumentBtn = document.getElementById('generateDocumentBtn');
    const emptyGenerateDocBtn = document.getElementById('emptyGenerateDocBtn');
    
    generateDocBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const templateId = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-name');
            
            document.getElementById('template_id_gen').value = templateId;
            document.getElementById('document_name').value = templateName;
            openModal('generateDocumentModal');
        });
    });
    
    if (generateDocumentBtn) {
        generateDocumentBtn.addEventListener('click', function() {
            openModal('generateDocumentModal');
        });
    }
    
    if (emptyGenerateDocBtn) {
        emptyGenerateDocBtn.addEventListener('click', function() {
            openModal('generateDocumentModal');
        });
    }
    
    // Close modals when clicking on close button or outside of modal
    const closeButtons = document.querySelectorAll('.close, .cancel-btn');
    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            closeModals();
            
            // Destroy editor instance when modal is closed
            if (editor) {
                // Don't actually destroy - just hide the container
                document.querySelector('.ck-editor__main').style.display = 'none';
            }
        });
    });
    
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            if (event.target === modal) {
                closeModals();
                
                // Destroy editor instance when modal is closed
                if (editor) {
                    // Don't actually destroy - just hide the container
                    document.querySelector('.ck-editor__main').style.display = 'none';
                }
            }
        });
    });
    
    // Make sure form submits the editor content
    if (templateForm) {
        templateForm.addEventListener('submit', function() {
            if (editor) {
                const editorData = editor.getData();
                document.getElementById('template_content').value = editorData;
            }
        });
    }
    
    // Fix for tab content not displaying - ensure tab content is visible
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            const targetTab = this.getAttribute('href').split('=')[1];
            console.log('Clicked tab:', targetTab);
            
            // Add proper tab display logic by handling client-side tab switching
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Hide all tab content first
            document.querySelectorAll('.tab-content > div').forEach(content => {
                content.style.display = 'none';
            });
            
            // Show the selected tab content
            const selectedTabContent = document.querySelector(`.tab-${targetTab}, [data-tab="${targetTab}"]`);
            if (selectedTabContent) {
                selectedTabContent.style.display = 'block';
            }
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
