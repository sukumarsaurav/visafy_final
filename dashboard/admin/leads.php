<?php
$page_title = "Lead Management";
$page_specific_css = "assets/css/leads.css";
$page_specific_js = "assets/js/leads.js";
require_once 'includes/header.php';
// require_once 'includes/admin_check.php';

// Fetch all users with 'applicant' role
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, u.status, u.email_verified,
           COUNT(DISTINCT va.id) as application_count
    FROM users u
    LEFT JOIN visa_applications va ON u.id = va.user_id AND va.deleted_at IS NULL
    WHERE u.user_type = 'applicant' AND u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$applicants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="leads-card">
                    <div class="leads-header">
                        <h2 class="leads-title">Lead Management</h2>
                        <div class="leads-actions">
                            <input type="text" class="leads-search" id="lead-search" placeholder="Search leads...">
                            <div class="leads-filter">
                                <select id="status-filter" class="leads-select">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="leads-filter">
                                <select id="verification-filter" class="leads-select">
                                    <option value="all">All Verification</option>
                                    <option value="1">Verified</option>
                                    <option value="0">Unverified</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="leads-body">
                        <table class="leads-table" id="leads-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Verified</th>
                                    <th>Applications</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applicants as $applicant): ?>
                                <tr class="lead-row" data-status="<?php echo $applicant['status']; ?>" data-verified="<?php echo $applicant['email_verified']; ?>">
                                    <td class="lead-name">
                                        <div class="lead-profile">
                                            <div class="lead-avatar">
                                                <?php echo strtoupper(substr($applicant['first_name'], 0, 1) . substr($applicant['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="lead-info">
                                                <div class="lead-fullname"><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></div>
                                                <div class="lead-id">ID: <?php echo $applicant['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="lead-email"><?php echo htmlspecialchars($applicant['email']); ?></td>
                                    <td class="lead-status">
                                        <span class="status-badge status-<?php echo $applicant['status']; ?>">
                                            <?php echo ucfirst($applicant['status']); ?>
                                        </span>
                                    </td>
                                    <td class="lead-verified">
                                        <span class="verified-badge verified-<?php echo $applicant['email_verified'] ? 'yes' : 'no'; ?>">
                                            <?php echo $applicant['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                    </td>
                                    <td class="lead-apps">
                                        <span class="apps-count">
                                            <?php echo $applicant['application_count']; ?>
                                        </span>
                                    </td>
                                    <td class="lead-date">
                                        <?php echo date('M d, Y', strtotime($applicant['created_at'])); ?>
                                    </td>
                                    <td class="lead-actions">
                                        <button class="action-btn view-btn" data-id="<?php echo $applicant['id']; ?>" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn message-btn" data-id="<?php echo $applicant['id']; ?>" title="Message">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        <button class="action-btn edit-btn" data-id="<?php echo $applicant['id']; ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <div class="action-dropdown">
                                            <button class="action-btn dropdown-btn">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($applicant['status'] === 'active'): ?>
                                                <a href="#" class="dropdown-item suspend-user" data-id="<?php echo $applicant['id']; ?>">
                                                    <i class="fas fa-ban"></i> Suspend
                                                </a>
                                                <?php else: ?>
                                                <a href="#" class="dropdown-item activate-user" data-id="<?php echo $applicant['id']; ?>">
                                                    <i class="fas fa-check"></i> Activate
                                                </a>
                                                <?php endif; ?>
                                                <a href="#" class="dropdown-item resend-verification" data-id="<?php echo $applicant['id']; ?>">
                                                    <i class="fas fa-envelope"></i> Resend Verification
                                                </a>
                                                <a href="#" class="dropdown-item delete-user" data-id="<?php echo $applicant['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="leads-footer">
                        <div class="leads-pagination">
                            <span class="pagination-info">Showing <span id="showing-records">1-<?php echo min(count($applicants), 10); ?></span> of <span id="total-records"><?php echo count($applicants); ?></span> records</span>
                            <div class="pagination-controls">
                                <button class="pagination-btn" id="prev-page" disabled>
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="pagination-pages" id="pagination-pages">
                                    <span class="pagination-page active">1</span>
                                </div>
                                <button class="pagination-btn" id="next-page">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lead Details Modal -->
<div class="lead-modal" id="lead-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Lead Details</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="lead-profile-details">
                <div class="profile-header">
                    <div class="profile-avatar" id="modal-avatar"></div>
                    <div class="profile-info">
                        <h4 id="modal-name"></h4>
                        <p id="modal-email"></p>
                    </div>
                    <div class="profile-status">
                        <span class="status-badge" id="modal-status"></span>
                    </div>
                </div>
                <div class="profile-sections">
                    <div class="profile-section">
                        <h5 class="section-title">Applications</h5>
                        <div class="application-list" id="applications-list">
                            <!-- Application list will be loaded dynamically -->
                        </div>
                    </div>
                    <div class="profile-section">
                        <h5 class="section-title">Activity</h5>
                        <div class="activity-timeline" id="activity-timeline">
                            <!-- Activity timeline will be loaded dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn close-btn">Close</button>
            <button class="modal-btn message-btn">Send Message</button>
        </div>
    </div>
</div>

<!-- Create leads.js file for later implementation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic filtering functionality
    const leadSearch = document.getElementById('lead-search');
    const statusFilter = document.getElementById('status-filter');
    const verificationFilter = document.getElementById('verification-filter');
    const leadRows = document.querySelectorAll('.lead-row');
    
    function filterLeads() {
        const searchTerm = leadSearch.value.toLowerCase();
        const statusValue = statusFilter.value;
        const verifiedValue = verificationFilter.value;
        
        leadRows.forEach(row => {
            const name = row.querySelector('.lead-fullname').textContent.toLowerCase();
            const email = row.querySelector('.lead-email').textContent.toLowerCase();
            const status = row.dataset.status;
            const verified = row.dataset.verified;
            
            const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
            const matchesStatus = statusValue === 'all' || status === statusValue;
            const matchesVerified = verifiedValue === 'all' || verified === verifiedValue;
            
            if (matchesSearch && matchesStatus && matchesVerified) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    leadSearch.addEventListener('input', filterLeads);
    statusFilter.addEventListener('change', filterLeads);
    verificationFilter.addEventListener('change', filterLeads);
    
    // Dropdown functionality
    const dropdownBtns = document.querySelectorAll('.dropdown-btn');
    
    dropdownBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.nextElementSibling;
            dropdown.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking elsewhere
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    });
    
    // Modal functionality
    const viewBtns = document.querySelectorAll('.view-btn');
    const modal = document.getElementById('lead-details-modal');
    const closeModal = document.querySelector('.modal-close');
    const closeBtn = document.querySelector('.close-btn');
    
    function openModal(leadId) {
        // You would normally fetch lead details via AJAX here
        // For now, we'll just display the modal
        modal.classList.add('show');
    }
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            openModal(this.dataset.id);
        });
    });
    
    function closeModalFunc() {
        modal.classList.remove('show');
    }
    
    closeModal.addEventListener('click', closeModalFunc);
    closeBtn.addEventListener('click', closeModalFunc);
});
</script>

<?php require_once 'includes/footer.php'; ?>
