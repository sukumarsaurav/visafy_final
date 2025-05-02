<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Client Management";
$page_specific_css = "assets/css/clients.css";
require_once 'includes/header.php';

// Get all clients (users who have bookings)
$query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.email_verified,
          u.created_at, COUNT(b.id) as booking_count, MAX(b.booking_datetime) as last_booking_date,
          (SELECT bs.name FROM bookings b2 
           JOIN booking_statuses bs ON b2.status_id = bs.id 
           WHERE b2.user_id = u.id 
           ORDER BY b2.booking_datetime DESC LIMIT 1) as latest_booking_status
          FROM users u
          JOIN bookings b ON u.id = b.user_id
          WHERE u.deleted_at IS NULL AND u.user_type = 'applicant'
          GROUP BY u.id
          ORDER BY last_booking_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$clients = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
}
$stmt->close();

// Handle client account deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_client'])) {
    $user_id = $_POST['user_id'];
    
    // Soft delete - update status to suspended
    $update_query = "UPDATE users SET status = 'suspended' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Client account deactivated successfully";
        $stmt->close();
        header("Location: clients.php?success=1");
        exit;
    } else {
        $error_message = "Error deactivating client account: " . $conn->error;
        $stmt->close();
    }
}

// Handle client account reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_client'])) {
    $user_id = $_POST['user_id'];
    
    // Update status to active
    $update_query = "UPDATE users SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Client account reactivated successfully";
        $stmt->close();
        header("Location: clients.php?success=2");
        exit;
    } else {
        $error_message = "Error reactivating client account: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Client account deactivated successfully";
            break;
        case 2:
            $success_message = "Client account reactivated successfully";
            break;
    }
}

// Handle search functionality
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    $search_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.email_verified,
                    u.created_at, COUNT(b.id) as booking_count, MAX(b.booking_datetime) as last_booking_date,
                    (SELECT bs.name FROM bookings b2 
                     JOIN booking_statuses bs ON b2.status_id = bs.id 
                     WHERE b2.user_id = u.id 
                     ORDER BY b2.booking_datetime DESC LIMIT 1) as latest_booking_status
                    FROM users u
                    JOIN bookings b ON u.id = b.user_id
                    WHERE u.deleted_at IS NULL 
                    AND u.user_type = 'applicant'
                    AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
                    GROUP BY u.id
                    ORDER BY last_booking_date DESC";
    
    $search_param = "%" . $search_term . "%";
    $search_stmt = $conn->prepare($search_query);
    $search_stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    // Replace the original clients array with search results
    $clients = [];
    if ($search_result && $search_result->num_rows > 0) {
        while ($row = $search_result->fetch_assoc()) {
            $clients[] = $row;
        }
    }
    $search_stmt->close();
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Client Management</h1>
            <p>Manage clients who have booked consultations through the system.</p>
        </div>
        <div class="search-container">
            <form action="clients.php" method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search clients..." value="<?php echo htmlspecialchars($search_term); ?>" class="search-input">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="clients.php" class="clear-search-btn">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Clients Table Section -->
    <div class="clients-table-container">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <?php if (!empty($search_term)): ?>
                    <p>No clients found matching "<?php echo htmlspecialchars($search_term); ?>".</p>
                <?php else: ?>
                    <p>No clients found in the system yet.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Total Bookings</th>
                        <th>Last Booking</th>
                        <th>Latest Booking Status</th>
                        <th>Account Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td class="name-cell">
                                <div class="client-avatar">
                                    <?php if (!empty($client['profile_picture']) && file_exists('../../uploads/profiles/' . $client['profile_picture'])): ?>
                                        <img src="../../uploads/profiles/<?php echo $client['profile_picture']; ?>" alt="Profile picture">
                                    <?php else: ?>
                                        <div class="initials">
                                            <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($client['email']); ?>
                                <?php if (!$client['email_verified']): ?>
                                    <span class="pending-badge" title="Email not verified"><i class="fas fa-exclamation-triangle"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="booking-count"><?php echo $client['booking_count']; ?></td>
                            <td><?php echo !empty($client['last_booking_date']) ? date('M d, Y', strtotime($client['last_booking_date'])) : '-'; ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($client['latest_booking_status']) {
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        break;
                                    case 'confirmed':
                                        $status_class = 'status-confirmed';
                                        break;
                                    case 'completed':
                                        $status_class = 'status-completed';
                                        break;
                                    case 'cancelled_by_user':
                                    case 'cancelled_by_admin':
                                    case 'cancelled_by_consultant':
                                        $status_class = 'status-cancelled';
                                        break;
                                    case 'rescheduled':
                                        $status_class = 'status-rescheduled';
                                        break;
                                    case 'no_show':
                                        $status_class = 'status-no-show';
                                        break;
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php 
                                    echo ucfirst(str_replace('_', ' ', $client['latest_booking_status'])); 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($client['status'] === 'active'): ?>
                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="view_client.php?id=<?php echo $client['id']; ?>" class="btn-action btn-view" title="View Client Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($client['status'] === 'active'): ?>
                                    <button type="button" class="btn-action btn-deactivate" 
                                            title="Deactivate Account" onclick="confirmAction('deactivate', <?php echo $client['id']; ?>)">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-action btn-activate" 
                                            title="Activate Account" onclick="confirmAction('activate', <?php echo $client['id']; ?>)">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <a href="client_bookings.php?client_id=<?php echo $client['id']; ?>" class="btn-action btn-bookings" title="View Client Bookings">
                                    <i class="fas fa-calendar-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="deactivateForm" action="clients.php" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="deactivate_user_id">
    <input type="hidden" name="deactivate_client" value="1">
</form>

<form id="activateForm" action="clients.php" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="activate_user_id">
    <input type="hidden" name="reactivate_client" value="1">
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

.search-container {
    position: relative;
}

.search-form {
    display: flex;
    align-items: center;
}

.search-input {
    width: 250px;
    padding: 8px 40px 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.search-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--secondary-color);
    cursor: pointer;
}

.clear-search-btn {
    margin-left: 8px;
    color: var(--danger-color);
    cursor: pointer;
    text-decoration: none;
}

.clients-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.clients-table {
    width: 100%;
    border-collapse: collapse;
}

.clients-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.clients-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.clients-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.clients-table tbody tr:last-child td {
    border-bottom: none;
}

.name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.client-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.client-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.initials {
    color: white;
    font-weight: 600;
    font-size: 14px;
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

.status-badge.status-pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.status-confirmed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.status-completed {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.status-badge.status-cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge.status-rescheduled {
    background-color: rgba(153, 50, 204, 0.1);
    color: #9932CC;
}

.status-badge.status-no-show {
    background-color: rgba(128, 128, 128, 0.1);
    color: #808080;
}

.status-badge i {
    font-size: 8px;
}

.booking-count {
    font-weight: 600;
    text-align: center;
}

.pending-badge {
    display: inline-flex;
    margin-left: 5px;
    color: #f6c23e;
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

.btn-bookings {
    background-color: #36b9cc;
}

.btn-bookings:hover {
    background-color: #2ca8b9;
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
    
    .clients-table {
        display: block;
        overflow-x: auto;
    }
    
    .actions-cell {
        flex-direction: row;
    }
    
    .search-input {
        width: 100%;
    }
}
</style>

<script>
// Function to handle action confirmations (deactivate, activate)
function confirmAction(action, userId) {
    switch(action) {
        case 'deactivate':
            if (confirm('Are you sure you want to deactivate this client account?')) {
                document.getElementById('deactivate_user_id').value = userId;
                document.getElementById('deactivateForm').submit();
            }
            break;
        case 'activate':
            document.getElementById('activate_user_id').value = userId;
            document.getElementById('activateForm').submit();
            break;
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
