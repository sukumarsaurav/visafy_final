document.addEventListener('DOMContentLoaded', function() {
    console.log('Email management JS loaded');
    
    // Tab functionality
    const tabs = document.querySelectorAll('.email-tabs .email-tab');
    const tabPanes = document.querySelectorAll('.tab-content .tab-pane');
    
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
            const targetPaneId = tabName + '-tab';
            console.log('Looking for tab pane:', targetPaneId);
            
            const targetPane = document.getElementById(targetPaneId);
            if (targetPane) {
                targetPane.classList.add('active');
                console.log('Activated tab pane:', targetPaneId);
            } else {
                console.error('Tab pane not found:', targetPaneId);
            }
        });
    });
    
    // Delete template functionality
    const deleteButtons = document.querySelectorAll('.delete-template');
    
    console.log('Delete buttons found:', deleteButtons.length);
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Stop the event from bubbling up to parent elements
            e.stopPropagation();
            
            const templateId = this.getAttribute('data-id');
            console.log('Delete button clicked for template ID:', templateId);
            
            // Check if the delete button has already been clicked to prevent double execution
            if (this.dataset.processing === 'true') {
                console.log('Delete already in progress');
                return;
            }
            
            if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                console.log('Delete confirmed');
                
                // Mark this button as being processed
                this.dataset.processing = 'true';
                
                // Create form data
                const formData = new FormData();
                formData.append('delete_template', 'true');
                formData.append('template_id', templateId);
                
                // Create a reference to this button for use in the fetch callbacks
                const buttonElement = this;
                
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
                    
                    // Remove the processing flag
                    buttonElement.dataset.processing = 'false';
                    
                    if (data.success) {
                        // Remove template card from UI
                        const templateCard = buttonElement.closest('.template-card');
                        if (templateCard) {
                            templateCard.remove();
                            
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success';
                            alertDiv.textContent = 'Template deleted successfully';
                            
                            const content = document.querySelector('.content');
                            if (content) {
                                content.insertBefore(alertDiv, content.firstChild);
                                
                                // Auto-remove alert after 3 seconds
                                setTimeout(() => {
                                    alertDiv.remove();
                                }, 3000);
                            }
                            
                            // If no templates left, show empty state
                            const templatesGrid = document.querySelector('.templates-grid');
                            if (templatesGrid && templatesGrid.querySelectorAll('.template-card').length === 0) {
                                const emptyState = document.createElement('div');
                                emptyState.className = 'empty-state';
                                emptyState.innerHTML = `
                                    <p>No templates found</p>
                                    <a href="template_builder.php" class="btn primary-btn">
                                        <i class="fas fa-plus"></i> Create Your First Template
                                    </a>
                                `;
                                templatesGrid.appendChild(emptyState);
                            }
                        } else {
                            // If we can't find the template card, just reload the page
                            window.location.reload();
                        }
                    } else {
                        // Show error message
                        alert(data.message || 'An error occurred while deleting the template');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Remove the processing flag
                    buttonElement.dataset.processing = 'false';
                    
                    // Show error message
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
                        const subjectElement = document.getElementById(subjectField);
                        if (subjectElement) {
                            subjectElement.value = data.template.subject;
                        }
                        
                        // If using a rich text editor
                        const contentElement = document.getElementById(contentField);
                        if (contentElement) {
                            if (contentElement.classList.contains('rich-editor') && window.CKEDITOR && CKEDITOR.instances[contentField]) {
                                CKEDITOR.instances[contentField].setData(data.template.content);
                            } else {
                                contentElement.value = data.template.content;
                            }
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
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
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'alert alert-info';
        loadingDiv.textContent = 'Deleting template...';
        
        const content = document.querySelector('.content');
        if (content) {
            content.insertBefore(loadingDiv, content.firstChild);
        }
        
        // Send AJAX request
        fetch('ajax_handlers/delete_template.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Remove the loading indicator
            if (loadingDiv && loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            
            if (data.success) {
                // Reload the page to show updated templates
                window.location.reload();
            } else {
                alert(data.message || 'An error occurred while deleting the template');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Remove the loading indicator
            if (loadingDiv && loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            
            alert('An error occurred while processing your request. Please try again.');
        });
    }
}

// Show email details in a modal
function showEmailDetails(email) {
    console.log('Showing email details:', email);
    
    const modal = document.getElementById('email-details-modal');
    if (!modal) {
        console.error('Email details modal not found');
        return;
    }
    
    if (email.status === 'sent') {
        // For sent emails
        modal.querySelector('.modal-title').textContent = email.subject || 'No Subject';
        modal.querySelector('.email-from').textContent = email.sender_email || 'N/A';
        modal.querySelector('.email-to').textContent = 'You';
        
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

// Close modals with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
}); 