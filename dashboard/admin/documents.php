<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: login.php");
    exit;
}

// Assign user_id from session['id'] to be consistent with header.php
$_SESSION['user_id'] = $_SESSION['id'];

$page_title = "Document Management";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';

// Get all document categories
$query = "SELECT * FROM document_categories ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$stmt->close();

// Get all document types
$query = "SELECT dt.*, dc.name as category_name 
          FROM document_types dt 
          JOIN document_categories dc ON dt.category_id = dc.id 
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->execute();
$document_types_result = $stmt->get_result();
$document_types = [];

if ($document_types_result && $document_types_result->num_rows > 0) {
    while ($row = $document_types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}
$stmt->close();

// Get all document templates
$query = "SELECT dt.*, dty.name as document_type_name, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
          FROM document_templates dt 
          JOIN document_types dty ON dt.document_type_id = dty.id
          JOIN users u ON dt.created_by = u.id
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->execute();
$templates_result = $stmt->get_result();
$templates = [];

if ($templates_result && $templates_result->num_rows > 0) {
    while ($row = $templates_result->fetch_assoc()) {
        $templates[] = $row;
    }
}
$stmt->close();

// Get users (clients) for the generated documents dropdown
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email FROM users WHERE user_type = 'applicant' ORDER BY first_name, last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = [];

if ($clients_result && $clients_result->num_rows > 0) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}
$stmt->close();

// Handle document category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Category name is required";
    }
    
    if (empty($errors)) {
        // Check if category already exists
        $check_query = "SELECT id FROM document_categories WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Document category already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new category
        $insert_query = "INSERT INTO document_categories (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ss', $name, $description);
        
        if ($stmt->execute()) {
            $success_message = "Document category added successfully";
            $stmt->close();
            header("Location: documents.php?success=1");
            exit;
        } else {
            $error_message = "Error adding document category: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document type form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document_type'])) {
    $category_id = $_POST['category_id'];
    $name = trim($_POST['type_name']);
    $description = trim($_POST['type_description']);
    $is_active = isset($_POST['type_is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }
    if (empty($name)) {
        $errors[] = "Document type name is required";
    }
    
    if (empty($errors)) {
        // Check if document type already exists
        $check_query = "SELECT id FROM document_types WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Document type already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new document type
        $insert_query = "INSERT INTO document_types (category_id, name, description, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('issi', $category_id, $name, $description, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Document type added successfully";
            $stmt->close();
            header("Location: documents.php?success=2");
            exit;
        } else {
            $error_message = "Error adding document type: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document template form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $template_name = trim($_POST['template_name']);
    $document_type_id = $_POST['document_type_id'];
    $content = trim($_POST['content']);
    $is_active = isset($_POST['template_is_active']) ? 1 : 0;
    $created_by = $_SESSION['user_id']; // Assuming user_id is stored in session
    
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
        // Check if template name already exists
        $check_query = "SELECT id FROM document_templates WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $template_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Template name already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new template
        $insert_query = "INSERT INTO document_templates (name, document_type_id, content, is_active, created_by) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('sisii', $template_name, $document_type_id, $content, $is_active, $created_by);
        
        if ($stmt->execute()) {
            $success_message = "Document template added successfully";
            $stmt->close();
            header("Location: documents.php?success=3");
            exit;
        } else {
            $error_message = "Error adding document template: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle generated document form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_document'])) {
    $name = trim($_POST['document_name']);
    $document_type_id = $_POST['document_type_id'];
    $template_id = $_POST['template_id'];
    $client_id = $_POST['client_id'];
    $created_by = $_SESSION['user_id']; // Assuming user_id is stored in session
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Document name is required";
    }
    if (empty($document_type_id)) {
        $errors[] = "Document type is required";
    }
    if (empty($template_id)) {
        $errors[] = "Template is required";
    }
    if (empty($client_id)) {
        $errors[] = "Client is required";
    }
    
    if (empty($errors)) {
        // Generate filename
        $filename = 'doc_' . time() . '_' . $client_id . '.pdf';
        $file_path = 'uploads/documents/' . $filename;
        
        // In a real implementation, you would generate the actual document here
        // For now, we'll just insert the record
        
        // Insert new generated document
        $insert_query = "INSERT INTO generated_documents (name, document_type_id, template_id, client_id, file_path, created_by, generated_date) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('siiisi', $name, $document_type_id, $template_id, $client_id, $file_path, $created_by);
        
        if ($stmt->execute()) {
            $success_message = "Document generated successfully";
            $stmt->close();
            header("Location: documents.php?success=4");
            exit;
        } else {
            $error_message = "Error generating document: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    
    // Check if category is in use
    $check_query = "SELECT id FROM document_types WHERE category_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete category as it is currently in use by document types";
    } else {
        // Delete category
        $delete_query = "DELETE FROM document_categories WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $category_id);
        
        if ($stmt->execute()) {
            $success_message = "Document category deleted successfully";
            $stmt->close();
            header("Location: documents.php?success=5");
            exit;
        } else {
            $error_message = "Error deleting document category: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Handle document type deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document_type'])) {
    $document_type_id = $_POST['document_type_id'];
    
    // Check if document type is in use by templates
    $check_query = "SELECT id FROM document_templates WHERE document_type_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $document_type_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete document type as it is currently in use by templates";
    } else {
        // Delete document type
        $delete_query = "DELETE FROM document_types WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $document_type_id);
        
        if ($stmt->execute()) {
            $success_message = "Document type deleted successfully";
            $stmt->close();
            header("Location: documents.php?success=6");
            exit;
        } else {
            $error_message = "Error deleting document type: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Get generated documents
$query = "SELECT gd.*, dt.name as document_type_name, CONCAT(u.first_name, ' ', u.last_name) as client_name, 
          tmpl.name as template_name, CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
          FROM generated_documents gd
          JOIN document_types dt ON gd.document_type_id = dt.id
          JOIN document_templates tmpl ON gd.template_id = tmpl.id
          JOIN users u ON gd.client_id = u.id
          JOIN users creator ON gd.created_by = creator.id
          ORDER BY gd.generated_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$generated_docs_result = $stmt->get_result();
$generated_documents = [];

if ($generated_docs_result && $generated_docs_result->num_rows > 0) {
    while ($row = $generated_docs_result->fetch_assoc()) {
        $generated_documents[] = $row;
    }
}
$stmt->close();

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Document category added successfully";
            break;
        case 2:
            $success_message = "Document type added successfully";
            break;
        case 3:
            $success_message = "Document template added successfully";
            break;
        case 4:
            $success_message = "Document generated successfully";
            break;
        case 5:
            $success_message = "Document category deleted successfully";
            break;
        case 6:
            $success_message = "Document type deleted successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Document Management</h1>
            <p>Manage document categories, types, templates and generate documents</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="generated-documents">Generated Documents</button>
            <button class="tab-btn" data-tab="templates">Document Templates</button>
            <button class="tab-btn" data-tab="document-types">Document Types</button>
            <button class="tab-btn" data-tab="categories">Categories</button>
        </div>
        
        <!-- Generated Documents Tab -->
        <div class="tab-content active" id="generated-documents-tab">
            <div class="tab-header">
                <h2>Generated Documents</h2>
                <button type="button" class="btn primary-btn" id="generateDocumentBtn">
                    <i class="fas fa-plus"></i> Generate Document
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($generated_documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No documents generated yet. Generate a document to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Document Type</th>
                                <th>Template</th>
                                <th>Client</th>
                                <th>Generated Date</th>
                                <th>Email Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($generated_documents as $document): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($document['name']); ?></td>
                                    <td><?php echo htmlspecialchars($document['document_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($document['template_name']); ?></td>
                                    <td><?php echo htmlspecialchars($document['client_name']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($document['generated_date'])); ?></td>
                                    <td>
                                        <?php if ($document['email_sent']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Sent</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Not Sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="btn-action btn-view" title="View Document" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download_document.php?id=<?php echo $document['id']; ?>" class="btn-action btn-download" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (!$document['email_sent']): ?>
                                            <button type="button" class="btn-action btn-email" title="Send Email" 
                                                    onclick="sendDocumentEmail(<?php echo $document['id']; ?>)">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Templates Tab -->
        <div class="tab-content" id="templates-tab">
            <div class="tab-header">
                <h2>Document Templates</h2>
                <button type="button" class="btn primary-btn" id="addTemplateBtn">
                    <i class="fas fa-plus"></i> Add Template
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($templates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-code"></i>
                        <p>No document templates yet. Add a template to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Template Name</th>
                                <th>Document Type</th>
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
                                    <td><?php echo htmlspecialchars($template['created_by_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
                                    <td>
                                        <?php if ($template['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="edit_template.php?id=<?php echo $template['id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_template.php?id=<?php echo $template['id']; ?>" class="btn-action btn-view" title="View Template">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Types Tab -->
        <div class="tab-content" id="document-types-tab">
            <div class="tab-header">
                <h2>Document Types</h2>
                <button type="button" class="btn primary-btn" id="addDocumentTypeBtn">
                    <i class="fas fa-plus"></i> Add Document Type
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($document_types)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <p>No document types yet. Add a document type to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($document_types as $type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                    <td><?php echo htmlspecialchars($type['category_name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($type['description']) 
                                                ? htmlspecialchars(substr($type['description'], 0, 100)) . (strlen($type['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($type['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editDocumentType(<?php echo $type['id']; ?>, '<?php echo addslashes($type['name']); ?>', '<?php echo addslashes($type['description']); ?>', <?php echo $type['category_id']; ?>, <?php echo $type['is_active']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteDocumentType(<?php echo $type['id']; ?>, '<?php echo addslashes($type['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Categories Tab -->
        <div class="tab-content" id="categories-tab">
            <div class="tab-header">
                <h2>Document Categories</h2>
                <button type="button" class="btn primary-btn" id="addCategoryBtn">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder"></i>
                        <p>No document categories yet. Add a category to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($category['description']) 
                                                ? htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
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
                <form action="documents.php" method="POST" id="addCategoryForm">
                    <div class="form-group">
                        <label for="name">Category Name*</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="category_id" id="category_id" value="">
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
<div class="modal" id="addDocumentTypeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Type</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="addDocumentTypeForm">
                    <div class="form-group">
                        <label for="category_id">Category*</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type_name">Type Name*</label>
                        <input type="text" name="type_name" id="type_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="type_description">Description</label>
                        <textarea name="type_description" id="type_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="type_is_active" id="type_is_active" checked>
                        <label for="type_is_active">Active</label>
                    </div>
                    <input type="hidden" name="document_type_id" id="edit_document_type_id" value="">
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_document_type" class="btn submit-btn">Save Document Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal" id="addTemplateModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Template</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="addTemplateForm">
                    <div class="form-group">
                        <label for="template_name">Template Name*</label>
                        <input type="text" name="template_name" id="template_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <?php if ($type['is_active']): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="content">Template Content*</label>
                        <div class="ai-generator-controls">
                            <button type="button" id="ai-generate-btn" class="btn ai-btn" onclick="generateTemplateWithAI()">
                                <i class="fas fa-robot"></i> Generate with Visafy AI
                            </button>
                            <span id="ai-generate-status" class="ai-status"></span>
                        </div>
                        <textarea name="content" id="content" class="form-control" rows="15" required></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="template_is_active" id="template_is_active" checked>
                        <label for="template_is_active">Active</label>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_template" class="btn submit-btn">Save Template</button>
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
                <form action="documents.php" method="POST" id="generateDocumentForm">
                    <div class="form-group">
                        <label for="document_name">Document Name*</label>
                        <input type="text" name="document_name" id="document_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="gen_document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <?php if ($type['is_active']): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="template_id">Template*</label>
                        <select name="template_id" id="template_id" class="form-control" required disabled>
                            <option value="">Select Template</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_id">Client*</label>
                        <select name="client_id" id="client_id" class="form-control" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['full_name']); ?> (<?php echo htmlspecialchars($client['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Hidden forms for actions -->
<form id="deleteCategoryForm" action="documents.php" method="POST" style="display: none;">
    <input type="hidden" name="category_id" id="delete_category_id">
    <input type="hidden" name="delete_category" value="1">
</form>

<form id="deleteDocumentTypeForm" action="documents.php" method="POST" style="display: none;">
    <input type="hidden" name="document_type_id" id="delete_document_type_id">
    <input type="hidden" name="delete_document_type" value="1">
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
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.tabs-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--secondary-color);
    font-weight: 500;
    position: relative;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tab-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
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

.status-badge i {
    font-size: 8px;
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

.btn-download {
    background-color: var(--success-color);
}

.btn-download:hover {
    background-color: #19b67f;
}

.btn-email {
    background-color: var(--secondary-color);
}

.btn-email:hover {
    background-color: #707483;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
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
    margin: 80px auto;
    max-width: 500px;
}

.modal-dialog.modal-lg {
    max-width: 700px;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
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

/* AI Template Generator styles */
.ai-generator-controls {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.ai-btn {
    background-color: #4e73df;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.ai-btn:hover {
    background-color: #375ad3;
}

.ai-btn i {
    font-size: 14px;
}

.ai-status {
    margin-left: 10px;
    font-size: 14px;
    color: var(--secondary-color);
    display: none;
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
    
    .tabs {
        overflow-x: auto;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}
</style>

<script>
// Tab functionality
document.querySelectorAll('.tab-btn').forEach(function(tab) {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-btn').forEach(function(t) {
            t.classList.remove('active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all tab content
        document.querySelectorAll('.tab-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        // Show corresponding tab content
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId + '-tab').classList.add('active');
    });
});

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

// Open modals when buttons are clicked
document.getElementById('addCategoryBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addCategoryForm').reset();
    document.getElementById('category_id').value = '';
    document.querySelector('#addCategoryModal .modal-title').textContent = 'Add Document Category';
    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Save Category';
    openModal('addCategoryModal');
});

document.getElementById('addDocumentTypeBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addDocumentTypeForm').reset();
    document.getElementById('edit_document_type_id').value = '';
    document.querySelector('#addDocumentTypeModal .modal-title').textContent = 'Add Document Type';
    document.querySelector('#addDocumentTypeForm button[type="submit"]').textContent = 'Save Document Type';
    openModal('addDocumentTypeModal');
});

document.getElementById('addTemplateBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addTemplateForm').reset();
    openModal('addTemplateModal');
});

document.getElementById('generateDocumentBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('generateDocumentForm').reset();
    // Reset template dropdown
    const templateSelect = document.getElementById('template_id');
    templateSelect.innerHTML = '<option value="">Select Template</option>';
    templateSelect.disabled = true;
    openModal('generateDocumentModal');
});

// Function to edit category
function editCategory(id, name, description) {
    document.getElementById('category_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = description;
    
    document.querySelector('#addCategoryModal .modal-title').textContent = 'Edit Document Category';
    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Update Category';
    
    openModal('addCategoryModal');
}

// Function to edit document type
function editDocumentType(id, name, description, categoryId, isActive) {
    document.getElementById('edit_document_type_id').value = id;
    document.getElementById('type_name').value = name;
    document.getElementById('type_description').value = description;
    document.getElementById('category_id').value = categoryId;
    document.getElementById('type_is_active').checked = isActive === 1;
    
    document.querySelector('#addDocumentTypeModal .modal-title').textContent = 'Edit Document Type';
    document.querySelector('#addDocumentTypeForm button[type="submit"]').textContent = 'Update Document Type';
    
    openModal('addDocumentTypeModal');
}

// Function to view template
function viewTemplate(id) {
    // Redirect to template viewer page
    window.location.href = 'view_template.php?id=' + id;
}

// Function to send document email
function sendDocumentEmail(id) {
    if (confirm('Are you sure you want to send this document via email?')) {
        window.location.href = 'send_document_email.php?id=' + id;
    }
}

// Function to confirm category deletion
function confirmDeleteCategory(id, name) {
    if (confirm('Are you sure you want to delete the category "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_category_id').value = id;
        document.getElementById('deleteCategoryForm').submit();
    }
}

// Function to confirm document type deletion
function confirmDeleteDocumentType(id, name) {
    if (confirm('Are you sure you want to delete the document type "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_document_type_id').value = id;
        document.getElementById('deleteDocumentTypeForm').submit();
    }
}

// Load templates based on document type selection
document.getElementById('gen_document_type_id').addEventListener('change', function() {
    const documentTypeId = this.value;
    const templateSelect = document.getElementById('template_id');
    
    if (documentTypeId) {
        // Enable the template select
        templateSelect.disabled = false;
        
        // Use AJAX to fetch templates for the selected document type
        fetch('ajax/get_templates.php?document_type_id=' + documentTypeId)
            .then(response => response.json())
            .then(data => {
                templateSelect.innerHTML = '<option value="">Select Template</option>';
                
                if (data.length > 0) {
                    data.forEach(function(template) {
                        const option = document.createElement('option');
                        option.value = template.id;
                        option.textContent = template.name;
                        templateSelect.appendChild(option);
                    });
                } else {
                    templateSelect.innerHTML = '<option value="">No templates found</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching templates:', error);
                templateSelect.innerHTML = '<option value="">Error loading templates</option>';
            });
    } else {
        // Reset and disable the template select
        templateSelect.innerHTML = '<option value="">Select Template</option>';
        templateSelect.disabled = true;
    }
});

// AI Template Generator functionality
async function generateTemplateWithAI() {
    const templateTypeSelect = document.getElementById('document_type_id');
    const contentTextarea = document.getElementById('content');
    const generateBtn = document.getElementById('ai-generate-btn');
    const generateStatus = document.getElementById('ai-generate-status');
    
    if (!templateTypeSelect.value) {
        alert('Please select a document type first.');
        templateTypeSelect.focus();
        return;
    }
    
    // Get the selected document type text
    const selectedOption = templateTypeSelect.options[templateTypeSelect.selectedIndex];
    const documentTypeName = selectedOption.text;
    
    // Update UI to show loading state
    generateBtn.disabled = true;
    generateStatus.textContent = 'Generating template...';
    generateStatus.style.display = 'block';
    
    try {
        // Make API call to get AI-generated template
        const response = await fetch('ajax/ai_template_generator.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_type: documentTypeName,
                prompt: `Create a professional ${documentTypeName} template in HTML format. Include placeholders for variables like {client_name}, {client_email}, {current_date}, etc. where appropriate. The document should be well-structured with headers, paragraphs, and any relevant sections.`
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Insert the generated content into the textarea
            contentTextarea.value = data.content;
            generateStatus.textContent = 'Template generated successfully!';
            generateStatus.style.color = 'var(--success-color)';
        } else {
            generateStatus.textContent = 'Error: ' + data.error;
            generateStatus.style.color = 'var(--danger-color)';
        }
    } catch (error) {
        generateStatus.textContent = 'Error connecting to AI service. Please try again.';
        generateStatus.style.color = 'var(--danger-color)';
    } finally {
        generateBtn.disabled = false;
        // Hide status message after 5 seconds
        setTimeout(() => {
            generateStatus.style.display = 'none';
        }, 5000);
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
