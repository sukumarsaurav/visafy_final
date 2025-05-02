<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Leads Management";
$page_specific_css = "assets/css/leads.css";
require_once 'includes/header.php';

// Get all leads (applicants) - Using prepared statement
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.email_verified, u.status, 
          u.created_at, u.profile_picture, u.auth_provider
          FROM users u
          WHERE u.user_type = 'applicant' AND u.deleted_at IS NULL
          ORDER BY u.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$leads = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
}
$stmt->close();

// Handle lead status toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $lead_id = $_POST['lead_id'];
    $new_status = $_POST['new_status'];
    
    // Update status
    $update_query = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('si', $new_status, $lead_id);
    
    if ($stmt->execute()) {
        $status_message = ($new_status === 'active') ? "Lead activated successfully" : "Lead deactivated successfully";
        $stmt->close();
        header("Location: leads.php?success=1&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating lead status: " . $conn->error;
        $stmt->close();
    }
}

// Handle lead deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lead'])) {
    $lead_id = $_POST['lead_id'];
    
    // Soft delete the lead
    $delete_query = "UPDATE users SET deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $lead_id);
    
    if ($stmt->execute()) {
        $success_message = "Lead deleted successfully";
        $stmt->close();
        header("Location: leads.php?success=2");
        exit;
    } else {
        $error_message = "Error deleting lead: " . $conn->error;
        $stmt->close();
    }
}

// Handle converting lead to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_client'])) {
    // This would involve additional steps like creating client records
    // For now, we'll just update a status or flag to indicate this lead is now a client
    $lead_id = $_POST['lead_id'];
    
    // Here you would implement the conversion logic
    // For example, you might update a field in the users table or create a record in a clients table
    
    $success_message = "Lead converted to client successfully";
    header("Location: leads.php?success=3");
    exit;
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = isset($_GET['message']) ? $_GET['message'] : "Status updated successfully";
            break;
        case 2:
            $success_message = "Lead deleted successfully";
            break;
        case 3:
            $success_message = "Lead converted to client successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Leads Management</h1>
            <p>Manage and track potential clients who have registered as applicants.</p>
        </div>
        <div>
            <a href="clients.php" class="btn primary-btn">
                <i class="fas fa-user-check"></i> View Clients
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Leads Filters -->
    <div class="leads-filters">
        <div class="filter-group">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-control">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="date-filter">Registration Date:</label>
            <select id="date-filter" class="filter-control">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="verification-filter">Verification:</label>
            <select id="verification-filter" class="filter-control">
                <option value="all">All</option>
                <option value="verified">Verified</option>
                <option value="unverified">Unverified</option>
            </select>
        </div>
        <div class="search-group">
            <input type="text" id="search-leads" class="search-control" placeholder="Search by name or email...">
            <button id="search-btn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <!-- Leads Table Section -->
    <div class="leads-table-container">
        <?php if (empty($leads)): ?>
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <p>No leads found. New applicant registrations will appear here.</p>
            </div>
        <?php else: ?>
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registration Date</th>
                        <th>Verification</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr class="lead-row" 
                            data-status="<?php echo $lead['status']; ?>" 
                            data-verified="<?php echo $lead['email_verified'] ? 'verified' : 'unverified'; ?>"
                            data-date="<?php echo $lead['created_at']; ?>">
                            <td class="name-cell">
                                <div class="lead-avatar">
                                    <?php if (!empty($lead['profile_picture']) && file_exists('../../uploads/profiles/' . $lead['profile_picture'])): ?>
                                        <img src="../../uploads/profiles/<?php echo $lead['profile_picture']; ?>" alt="Profile picture">
                                    <?php else: ?>
                                        <div class="initials">
                                            <?php echo substr($lead['first_name'], 0, 1) . substr($lead['last_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php echo htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']); ?>
                                <?php if ($lead['auth_provider'] === 'google'): ?>
                                    <span class="provider-badge google"><i class="fab fa-google"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lead['email']); ?>
                            </td>
                            <td class="date-cell">
                                <?php 
                                    $created_date = new DateTime($lead['created_at']);
                                    echo $created_date->format('M d, Y'); 
                                ?>
                                <span class="time-ago">
                                    <?php 
                                        $now = new DateTime();
                                        $interval = $created_date->diff($now);
                                        if ($interval->y > 0) {
                                            echo $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->m > 0) {
                                            echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->d > 0) {
                                            echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->h > 0) {
                                            echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                        } else {
                                            echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                                        }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($lead['email_verified']): ?>
                                    <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="status-badge unverified"><i class="fas fa-times-circle"></i> Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lead['status'] === 'active'): ?>
                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="lead_details.php?id=<?php echo $lead['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <a href="send_message.php?id=<?php echo $lead['id']; ?>" class="btn-action btn-message" title="Send Message">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                
                                <?php if ($lead['status'] === 'active'): ?>
                                    <button type="button" class="btn-action btn-deactivate" 
                                            title="Deactivate" onclick="toggleLeadStatus(<?php echo $lead['id']; ?>, 'suspended')">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-action btn-activate" 
                                            title="Activate" onclick="toggleLeadStatus(<?php echo $lead['id']; ?>, 'active')">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn-action btn-convert" 
                                        title="Convert to Client" onclick="convertToClient(<?php echo $lead['id']; ?>)">
                                    <i class="fas fa-user-graduate"></i>
                                </button>
                                
                                <button type="button" class="btn-action btn-delete" 
                                        title="Delete" onclick="confirmDeleteLead(<?php echo $lead['id']; ?>)">
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

<!-- Hidden forms for actions -->
<form id="statusToggleForm" action="leads.php" method="POST" style="display: none;">
    <input type="hidden" name="lead_id" id="status_lead_id">
    <input type="hidden" name="new_status" id="new_lead_status">
    <input type="hidden" name="toggle_status" value="1">
</form>

<form id="deleteLeadForm" action="leads.php" method="POST" style="display: none;">
    <input type="hidden" name="lead_id" id="delete_lead_id">
    <input type="hidden" name="delete_lead" value="1">
</form>

<form id="convertClientForm" action="leads.php" method="POST" style="display: none;">
    <input type="hidden" name="lead_id" id="convert_lead_id">
    <input type="hidden" name="convert_to_client" value="1">
</form>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
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
    text-decoration: none;
}

.primary-btn:hover {
    background-color: #031c56;
    text-decoration: none;
    color: white;
}

.leads-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 120px;
}

.search-group {
    display: flex;
    margin-left: auto;
}

.search-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px 0 0 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 200px;
}

.search-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
}

.leads-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.leads-table {
    width: 100%;
    border-collapse: collapse;
}

.leads-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.leads-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.leads-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.leads-table tbody tr:last-child td {
    border-bottom: none;
}

.name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.lead-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lead-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.initials {
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.date-cell {
    display: flex;
    flex-direction: column;
}

.time-ago {
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-top: 2px;
}

.provider-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    font-size: 10px;
    margin-left: 5px;
}

.provider-badge.google {
    background-color: white;
    color: #DB4437;
    border: 1px solid #DB4437;
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

.status-badge.verified {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.unverified {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge i {
    font-size: 10px;
}

.actions-cell {
    width: 10%;
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

.btn-activate {
    background-color: var(--success-color);
}

.btn-activate:hover {
    background-color: #18b07b;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
}

.btn-message {
    background-color: #4e73df;
}

.btn-message:hover {
    background-color: #4262c3;
}

.btn-convert {
    background-color: #36b9cc;
}

.btn-convert:hover {
    background-color: #2c9faf;
}

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .leads-filters {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .search-group {
        width: 100%;
        margin-left: 0;
    }
    
    .search-control {
        flex: 1;
    }
    
    .leads-table {
        display: block;
        overflow-x: auto;
    }
    
    .actions-cell {
        width: auto;
    }
}
</style>

<script>
// Function to filter leads
function filterLeads() {
    const statusFilter = document.getElementById('status-filter').value;
    const dateFilter = document.getElementById('date-filter').value;
    const verificationFilter = document.getElementById('verification-filter').value;
    const searchQuery = document.getElementById('search-leads').value.toLowerCase();
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Get start of this week (Sunday)
    const thisWeekStart = new Date(today);
    thisWeekStart.setDate(today.getDate() - today.getDay());
    
    // Get start of this month
    const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    
    // Get start of last month
    const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
    
    document.querySelectorAll('.lead-row').forEach(function(row) {
        const nameText = row.querySelector('.name-cell').textContent.toLowerCase();
        const email = row.closest('tr').querySelector('td:nth-child(2)').textContent.toLowerCase();
        const status = row.getAttribute('data-status');
        const verified = row.getAttribute('data-verified');
        const dateStr = row.getAttribute('data-date');
        const date = new Date(dateStr);
        
        let showRow = true;
        
        // Apply status filter
        if (statusFilter !== 'all' && status !== statusFilter) {
            showRow = false;
        }
        
        // Apply verification filter
        if (verificationFilter !== 'all' && verified !== verificationFilter) {
            showRow = false;
        }
        
        // Apply date filter
        if (dateFilter !== 'all') {
            switch (dateFilter) {
                case 'today':
                    if (date.toDateString() !== today.toDateString()) {
                        showRow = false;
                    }
                    break;
                case 'this_week':
                    if (date < thisWeekStart) {
                        showRow = false;
                    }
                    break;
                case 'this_month':
                    if (date < thisMonthStart) {
                        showRow = false;
                    }
                    break;
                case 'last_month':
                    if (date < lastMonthStart || date > lastMonthEnd) {
                        showRow = false;
                    }
                    break;
            }
        }
        
        // Apply search filter
        if (searchQuery && !nameText.includes(searchQuery) && !email.includes(searchQuery)) {
            showRow = false;
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

// Add event listeners for filters
document.getElementById('status-filter').addEventListener('change', filterLeads);
document.getElementById('date-filter').addEventListener('change', filterLeads);
document.getElementById('verification-filter').addEventListener('change', filterLeads);
document.getElementById('search-btn').addEventListener('click', filterLeads);
document.getElementById('search-leads').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterLeads();
    }
});

// Function to toggle lead status
function toggleLeadStatus(leadId, newStatus) {
    document.getElementById('status_lead_id').value = leadId;
    document.getElementById('new_lead_status').value = newStatus;
    document.getElementById('statusToggleForm').submit();
}

// Function to confirm lead deletion
function confirmDeleteLead(leadId) {
    if (confirm('Are you sure you want to delete this lead? This action cannot be undone.')) {
        document.getElementById('delete_lead_id').value = leadId;
        document.getElementById('deleteLeadForm').submit();
    }
}

// Function to convert lead to client
function convertToClient(leadId) {
    if (confirm('Are you sure you want to convert this lead to a client?')) {
        document.getElementById('convert_lead_id').value = leadId;
        document.getElementById('convertClientForm').submit();
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
