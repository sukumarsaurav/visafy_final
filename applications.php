<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Visa Applications";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Ensure user is logged in and has appropriate permissions
if (!isLoggedIn() || !hasPermission('view_applications')) {
    header("Location: login.php");
    exit;
}

// Get all application statuses for filter
$status_query = "SELECT id, name, color FROM application_statuses ORDER BY name";
$statuses = [];
try {
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $statuses[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching application statuses: " . $e->getMessage());
}

// Get all visa types for filter
$visa_query = "SELECT v.visa_id, v.visa_type, c.country_name 
               FROM visas v 
               JOIN countries c ON v.country_id = c.country_id 
               WHERE v.is_active = 1 
               ORDER BY c.country_name, v.visa_type";
$visas = [];
try {
    $stmt = $conn->prepare($visa_query);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $visas[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching visa types: " . $e->getMessage());
}

// Get all team members for assignment
$team_query = "SELECT tm.id, u.first_name, u.last_name, tm.role 
               FROM team_members tm 
               JOIN users u ON tm.user_id = u.id 
               WHERE tm.deleted_at IS NULL 
               ORDER BY u.first_name, u.last_name";
$team_members = [];
try {
    $stmt = $conn->prepare($team_query);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $team_members[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching team members: " . $e->getMessage());
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Visa Applications</h1>
            <p>View and manage all visa applications</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="createApplicationBtn">
                <i class="fas fa-plus"></i> New Application
            </button>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-container">
        <div class="filter-section">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-select">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status['id']; ?>"><?php echo ucwords(str_replace('_', ' ', $status['name'])); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-section">
            <label for="visa-filter">Visa Type:</label>
            <select id="visa-filter" class="filter-select">
                <option value="">All Visa Types</option>
                <?php foreach ($visas as $visa): ?>
                    <option value="<?php echo $visa['visa_id']; ?>"><?php echo $visa['visa_type'] . ' (' . $visa['country_name'] . ')'; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-section">
            <label for="priority-filter">Priority:</label>
            <select id="priority-filter" class="filter-select">
                <option value="">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
            </select>
        </div>

        <div class="filter-section">
            <label for="search-input">Search:</label>
            <input type="text" id="search-input" placeholder="Reference, Name, Email..." class="search-input">
        </div>
    </div>

    <!-- Applications Table Container -->
    <div class="applications-table-container">
        <div id="loading-indicator" class="loading-indicator" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
        <div id="applications-content">
            <!-- Table will be loaded here via AJAX -->
        </div>
        <div id="pagination-controls" class="pagination-controls">
            <!-- Pagination will be loaded here via AJAX -->
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" name="application_id" id="status_application_id">
                    
                    <div class="form-group">
                        <label for="new_status_id">New Status*</label>
                        <select name="new_status_id" id="new_status_id" class="form-control" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>"><?php echo ucwords(str_replace('_', ' ', $status['name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_notes">Notes</label>
                        <textarea name="notes" id="status_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assign Case Manager Modal -->
<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Case Manager</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <input type="hidden" name="application_id" id="assign_application_id">
                    
                    <div class="form-group">
                        <label for="team_member_id">Team Member*</label>
                        <select name="team_member_id" id="team_member_id" class="form-control" required>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_notes">Notes</label>
                        <textarea name="notes" id="assignment_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn">Assign Case Manager</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Your existing CSS styles here */
.loading-indicator {
    text-align: center;
    padding: 20px;
    font-size: 1.2em;
    color: var(--secondary-color);
}

.loading-indicator i {
    margin-right: 10px;
}

.pagination-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
    padding: 10px;
}

.pagination-controls button {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    background-color: white;
    border-radius: 4px;
    cursor: pointer;
}

.pagination-controls button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls button.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--secondary-color);
    font-size: 0.9em;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let currentPage = 1;
    let currentFilters = {
        status_id: '',
        visa_id: '',
        priority: '',
        search: '',
        page: 1,
        limit: 10
    };

    // Function to load applications
    function loadApplications(filters = {}) {
        const loadingIndicator = document.getElementById('loading-indicator');
        const applicationsContent = document.getElementById('applications-content');
        
        // Show loading indicator
        loadingIndicator.style.display = 'block';
        
        // Build query string from filters
        const queryString = Object.entries(filters)
            .filter(([_, value]) => value !== '')
            .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
            .join('&');
        
        // Fetch applications
        fetch(`ajax/filter_applications.php?${queryString}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update applications table
                    applicationsContent.innerHTML = generateTableHTML(data.data);
                    
                    // Update pagination
                    updatePagination(data.pagination);
                } else {
                    throw new Error(data.error || 'Error loading applications');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                applicationsContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
            });
    }

    // Function to generate table HTML
    function generateTableHTML(applications) {
        if (applications.length === 0) {
            return `
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No applications found matching your criteria.</p>
                </div>
            `;
        }

        return `
            <table class="applications-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Visa Type</th>
                        <th>Status</th>
                        <th>Case Manager</th>
                        <th>Documents</th>
                        <th>Priority</th>
                        <th>Created</th>
                        <th class="actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${applications.map(app => generateTableRow(app)).join('')}
                </tbody>
            </table>
        `;
    }

    // Function to generate table row HTML
    function generateTableRow(app) {
        return `
            <tr>
                <td>
                    <a href="view_application.php?id=${app.id}" class="reference-link">
                        ${app.reference_number}
                    </a>
                </td>
                <td>
                    <div class="applicant-info">
                        <span class="applicant-name">${app.applicant_name}</span>
                        <span class="applicant-email">${app.applicant_email}</span>
                    </div>
                </td>
                <td>${app.visa_type} (${app.country_name})</td>
                <td>
                    <span class="status-badge" style="background-color: ${app.status_color}20; color: ${app.status_color};">
                        <i class="fas fa-circle"></i> ${app.status_name.replace(/_/g, ' ')}
                    </span>
                </td>
                <td>
                    ${app.case_manager_name ? `
                        <div class="case-manager">
                            <span class="manager-name">${app.case_manager_name}</span>
                            <span class="manager-role">${app.case_manager_role}</span>
                        </div>
                    ` : '<span class="not-assigned">Not Assigned</span>'}
                </td>
                <td>
                    <div class="documents-info">
                        <div class="document-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${app.total_documents > 0 ? (app.approved_documents / app.total_documents * 100) : 0}%"></div>
                            </div>
                            <span class="document-count">${app.approved_documents}/${app.total_documents}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="priority-badge priority-${app.priority}">
                        ${app.priority.charAt(0).toUpperCase() + app.priority.slice(1)}
                    </span>
                </td>
                <td>
                    <span class="date">${new Date(app.created_at).toLocaleDateString()}</span>
                </td>
                <td class="actions-cell">
                    <a href="view_application.php?id=${app.id}" class="btn-action btn-view" title="View Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <button type="button" class="btn-action btn-status" 
                            title="Update Status" onclick="openStatusModal(${app.id}, ${app.status_id})">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    
                    <button type="button" class="btn-action btn-assign" 
                            title="Assign Case Manager" onclick="openAssignModal(${app.id}, '${app.team_member_id || ''}')">
                        <i class="fas fa-user-plus"></i>
                    </button>
                    
                    <a href="application_documents.php?id=${app.id}" class="btn-action btn-document" title="Manage Documents">
                        <i class="fas fa-file-alt"></i>
                    </a>
                </td>
            </tr>
        `;
    }

    // Function to update pagination controls
    function updatePagination(pagination) {
        const paginationControls = document.getElementById('pagination-controls');
        const totalPages = pagination.total_pages;
        const currentPage = pagination.page;
        
        let html = '<div class="pagination-info">';
        html += `Showing ${(currentPage - 1) * pagination.limit + 1} to ${Math.min(currentPage * pagination.limit, pagination.total)} of ${pagination.total} entries`;
        html += '</div>';
        
        html += '<div class="pagination-buttons">';
        
        // Previous button
        html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">Previous</button>`;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span>...</span>';
            }
        }
        
        // Next button
        html += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Next</button>`;
        
        html += '</div>';
        
        paginationControls.innerHTML = html;
    }

    // Function to change page
    window.changePage = function(page) {
        currentFilters.page = page;
        loadApplications(currentFilters);
    };

    // Function to open status update modal
    window.openStatusModal = function(applicationId, currentStatus) {
        document.getElementById('status_application_id').value = applicationId;
        document.getElementById('new_status_id').value = currentStatus;
        document.getElementById('updateStatusModal').style.display = 'block';
    };

    // Function to open assignment modal
    window.openAssignModal = function(applicationId, currentManager) {
        document.getElementById('assign_application_id').value = applicationId;
        if (currentManager) {
            document.getElementById('team_member_id').value = currentManager;
        }
        document.getElementById('assignModal').style.display = 'block';
    };

    // Handle status update form submission
    document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('ajax/update_application_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('updateStatusModal').style.display = 'none';
                loadApplications(currentFilters);
            } else {
                throw new Error(data.error || 'Error updating status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message);
        });
    });

    // Handle assignment form submission
    document.getElementById('assignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('ajax/assign_application.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('assignModal').style.display = 'none';
                loadApplications(currentFilters);
            } else {
                throw new Error(data.error || 'Error assigning application');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message);
        });
    });

    // Add event listeners to filters
    document.getElementById('status-filter').addEventListener('change', function() {
        currentFilters.status_id = this.value;
        currentFilters.page = 1;
        loadApplications(currentFilters);
    });

    document.getElementById('visa-filter').addEventListener('change', function() {
        currentFilters.visa_id = this.value;
        currentFilters.page = 1;
        loadApplications(currentFilters);
    });

    document.getElementById('priority-filter').addEventListener('change', function() {
        currentFilters.priority = this.value;
        currentFilters.page = 1;
        loadApplications(currentFilters);
    });

    // Add debounce to search input
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = this.value;
            currentFilters.page = 1;
            loadApplications(currentFilters);
        }, 300);
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // Close modals when clicking close button
    document.querySelectorAll('.close').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });

    // Initial load
    loadApplications(currentFilters);
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();

require_once 'includes/footer.php';
?> 