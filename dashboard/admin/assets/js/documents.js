// Global variables
let selectedCategoryId = null;
let selectedCategoryName = null;

// Document ready
$(document).ready(function() {
    // Initialize Bootstrap components
    initializeBootstrapComponents();
    
    // Set up event listeners
    setupEventListeners();
});

// Initialize Bootstrap components
function initializeBootstrapComponents() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Set up event listeners
function setupEventListeners() {
    // Category item click
    $('#category-list').on('click', '.category-item', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        // Highlight selected category
        $('.category-item').removeClass('active');
        $(this).addClass('active');
        
        // Update selected category info
        selectedCategoryId = categoryId;
        selectedCategoryName = categoryName;
        
        // Update UI
        $('#selected-category').text(': ' + categoryName);
        $('#add-document-btn').show();
        
        // Load document types
        loadDocumentTypes(categoryId);
    });
    
    // Add category button click
    $('#add-category-btn').click(function() {
        // Reset form
        $('#category-form').trigger('reset');
        $('#category-id').val('');
        $('#categoryModalLabel').text('Add New Category');
        
        // Show modal
        $('#category-modal').modal('show');
    });
    
    // Edit category button click
    $('#category-list').on('click', '.edit-category-btn', function(e) {
        e.stopPropagation(); // Prevent triggering category item click
        
        const categoryId = $(this).data('id');
        
        // Fetch category data
        $.ajax({
            url: 'ajax/get_category.php',
            type: 'GET',
            data: { category_id: categoryId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fill form
                    $('#category-id').val(response.category.id);
                    $('#category-name').val(response.category.name);
                    $('#category-description').val(response.category.description);
                    
                    // Update modal title
                    $('#categoryModalLabel').text('Edit Category');
                    
                    // Show modal
                    $('#category-modal').modal('show');
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
    
    // Add document button click
    $('#add-document-btn').click(function() {
        // Reset form
        $('#document-form').trigger('reset');
        $('#document-id').val('');
        $('#document-category-id').val(selectedCategoryId);
        $('#documentModalLabel').text('Add New Document Type');
        
        // Show modal
        $('#document-modal').modal('show');
    });
    
    // Edit document button click
    $('#document-list').on('click', '.edit-document-btn', function() {
        const documentId = $(this).data('id');
        
        // Fetch document data
        $.ajax({
            url: 'ajax/get_document_type.php',
            type: 'GET',
            data: { document_id: documentId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fill form
                    $('#document-id').val(response.document.id);
                    $('#document-category-id').val(response.document.category_id);
                    $('#document-name').val(response.document.name);
                    $('#document-description').val(response.document.description);
                    $('#document-active').prop('checked', response.document.is_active == 1);
                    
                    // Update modal title
                    $('#documentModalLabel').text('Edit Document Type');
                    
                    // Show modal
                    $('#document-modal').modal('show');
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
    
    // Delete document button click
    $('#document-list').on('click', '.delete-document-btn', function() {
        const documentId = $(this).data('id');
        const documentName = $(this).data('name');
        
        if (confirm('Are you sure you want to delete the document type "' + documentName + '"?')) {
            // Delete document
            $.ajax({
                url: 'ajax/delete_document_type.php',
                type: 'POST',
                data: { document_id: documentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload document types
                        loadDocumentTypes(selectedCategoryId);
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        }
    });
    
    // Category form submit
    $('#category-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const isEdit = $('#category-id').val() !== '';
        
        $.ajax({
            url: 'ajax/save_category.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide modal
                    $('#category-modal').modal('hide');
                    
                    // Show success message
                    alert(isEdit ? 'Category updated successfully!' : 'Category added successfully!');
                    
                    // Reload page to update category list
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
    
    // Document form submit
    $('#document-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const isEdit = $('#document-id').val() !== '';
        
        $.ajax({
            url: 'ajax/save_document_type.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide modal
                    $('#document-modal').modal('hide');
                    
                    // Show success message
                    alert(isEdit ? 'Document type updated successfully!' : 'Document type added successfully!');
                    
                    // Reload document types
                    loadDocumentTypes(selectedCategoryId);
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
}

// Load document types for a category
function loadDocumentTypes(categoryId) {
    // Show loading indicator
    $('#document-list-container').hide();
    $('#document-list-placeholder').text('Loading...').show();
    
    $.ajax({
        url: 'ajax/get_document_types.php',
        type: 'GET',
        data: { category_id: categoryId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update document list
                renderDocumentTypes(response.documents);
                
                // Hide placeholder, show document list
                $('#document-list-placeholder').hide();
                $('#document-list-container').show();
            } else {
                // Show error message
                $('#document-list-placeholder').html('<i class="fas fa-exclamation-circle fa-3x mb-3 text-danger"></i><p>Error: ' + response.error + '</p>').show();
                $('#document-list-container').hide();
            }
        },
        error: function(xhr, status, error) {
            // Show error message
            $('#document-list-placeholder').html('<i class="fas fa-exclamation-circle fa-3x mb-3 text-danger"></i><p>Error: ' + error + '</p>').show();
            $('#document-list-container').hide();
        }
    });
}

// Render document types
function renderDocumentTypes(documents) {
    const documentList = $('#document-list');
    documentList.empty();
    
    if (documents.length === 0) {
        // Show empty message
        documentList.html('<tr><td colspan="4" class="text-center">No document types found in this category</td></tr>');
        return;
    }
    
    // Add each document
    documents.forEach(function(document) {
        const statusClass = document.is_active == 1 ? 'active' : 'inactive';
        const statusText = document.is_active == 1 ? 'Active' : 'Inactive';
        
        const row = `
            <tr>
                <td>${document.name}</td>
                <td class="description-cell">${document.description || '-'}</td>
                <td><span class="document-status ${statusClass}"></span> ${statusText}</td>
                <td class="document-actions">
                    <button class="btn btn-sm btn-outline-primary edit-document-btn" data-id="${document.id}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-document-btn" data-id="${document.id}" data-name="${document.name}">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </td>
            </tr>
        `;
        
        documentList.append(row);
    });
}
