<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Clients";
$page_specific_css = "../assets/css/clients.css";
require_once 'includes/header.php';

// Get the team member's ID
$team_member_id = $user_id;

// Get clients from applications assigned to this team member
$applications_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.email_verified,
                      u.created_at, 'application' AS source, a.reference_number
                      FROM users u
                      JOIN applications a ON u.id = a.user_id
                      JOIN application_assignments aa ON a.id = aa.application_id
                      WHERE aa.team_member_id = ? 
                      AND aa.status = 'active'
                      AND u.deleted_at IS NULL 
                      AND u.user_type = 'applicant'
                      AND a.deleted_at IS NULL";
                      
$app_stmt = $conn->prepare($applications_query);
$app_stmt->bind_param("i", $team_member_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();
$clients_from_applications = [];

if ($app_result && $app_result->num_rows > 0) {
    while ($row = $app_result->fetch_assoc()) {
        $clients_from_applications[$row['id']] = $row;
    }
}
$app_stmt->close();

// Get clients from bookings assigned to this team member
$bookings_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.email_verified,
                  u.created_at, COUNT(b.id) as booking_count, MAX(b.booking_datetime) as last_booking_date,
                  (SELECT bs.name FROM bookings b2 
                   JOIN booking_statuses bs ON b2.status_id = bs.id 
                   WHERE b2.user_id = u.id 
                   ORDER BY b2.booking_datetime DESC LIMIT 1) as latest_booking_status,
                  'booking' AS source
                  FROM users u
                  JOIN bookings b ON u.id = b.user_id
                  WHERE b.team_member_id = ?
                  AND u.deleted_at IS NULL 
                  AND u.user_type = 'applicant'
                  AND b.deleted_at IS NULL
                  GROUP BY u.id
                  ORDER BY last_booking_date DESC";
                  
$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->bind_param("i", $team_member_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$clients_from_bookings = [];

if ($bookings_result && $bookings_result->num_rows > 0) {
    while ($row = $bookings_result->fetch_assoc()) {
        $clients_from_bookings[$row['id']] = $row;
    }
}
$bookings_stmt->close();

// Merge clients from both sources
$clients = [];
foreach ($clients_from_applications as $client_id => $client) {
    $clients[$client_id] = $client;
}

foreach ($clients_from_bookings as $client_id => $client) {
    if (isset($clients[$client_id])) {
        // Client exists in both sources, merge booking data
        $clients[$client_id]['booking_count'] = $client['booking_count'];
        $clients[$client_id]['last_booking_date'] = $client['last_booking_date'];
        $clients[$client_id]['latest_booking_status'] = $client['latest_booking_status'];
        $clients[$client_id]['source'] = 'both';
    } else {
        $clients[$client_id] = $client;
    }
}

// Convert associative array to indexed array for easier processing
$clients = array_values($clients);

// Handle search functionality
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    // Filter clients array based on search term
    $filtered_clients = [];
    foreach ($clients as $client) {
        $fullname = $client['first_name'] . ' ' . $client['last_name'];
        if (stripos($client['first_name'], $search_term) !== false ||
            stripos($client['last_name'], $search_term) !== false ||
            stripos($fullname, $search_term) !== false ||
            stripos($client['email'], $search_term) !== false) {
            $filtered_clients[] = $client;
        }
    }
    $clients = $filtered_clients;
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Clients</h1>
            <p>View and manage clients assigned to you through applications and bookings.</p>
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
    
    <!-- Clients Table Section -->
    <div class="clients-table-container">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <?php if (!empty($search_term)): ?>
                    <p>No clients found matching "<?php echo htmlspecialchars($search_term); ?>".</p>
                <?php else: ?>
                    <p>You don't have any assigned clients yet.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Relationship</th>
                        <?php if (count($clients_from_bookings) > 0): ?>
                        <th>Bookings</th>
                        <th>Last Booking</th>
                        <?php endif; ?>
                        <th>Status</th>
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
                            <td>
                                <?php if (isset($client['source'])): ?>
                                    <?php if ($client['source'] == 'application'): ?>
                                        <span class="relationship-badge application">
                                            <i class="fas fa-folder-open"></i> Application
                                        </span>
                                    <?php elseif ($client['source'] == 'booking'): ?>
                                        <span class="relationship-badge booking">
                                            <i class="fas fa-calendar-alt"></i> Booking
                                        </span>
                                    <?php else: ?>
                                        <span class="relationship-badge both">
                                            <i class="fas fa-user-check"></i> Both
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php if (count($clients_from_bookings) > 0): ?>
                                <?php if (isset($client['booking_count'])): ?>
                                <td class="booking-count"><?php echo $client['booking_count']; ?></td>
                                <td><?php echo !empty($client['last_booking_date']) ? date('M d, Y', strtotime($client['last_booking_date'])) : '-'; ?></td>
                                <?php else: ?>
                                <td>-</td>
                                <td>-</td>
                                <?php endif; ?>
                            <?php endif; ?>
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
                                
                                <?php if (isset($client['booking_count']) && $client['booking_count'] > 0): ?>
                                <a href="client_bookings.php?client_id=<?php echo $client['id']; ?>" class="btn-action btn-bookings" title="View Client Bookings">
                                    <i class="fas fa-calendar-alt"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($client['source'] == 'application' || $client['source'] == 'both'): ?>
                                <a href="client_applications.php?client_id=<?php echo $client['id']; ?>" class="btn-action btn-applications" title="View Client Applications">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="send_message.php?client_id=<?php echo $client['id']; ?>" class="btn-action btn-message" title="Send Message">
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
    --application-color: #4e73df;
    --booking-color: #36b9cc;
    --both-color: #1cc88a;
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

.status-badge, .relationship-badge {
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

.relationship-badge.application {
    background-color: rgba(78, 115, 223, 0.1);
    color: var(--application-color);
}

.relationship-badge.booking {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--booking-color);
}

.relationship-badge.both {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--both-color);
}

.status-badge i, .relationship-badge i {
    font-size: 10px;
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
    background-color: var(--booking-color);
}

.btn-bookings:hover {
    background-color: #2ca8b9;
}

.btn-applications {
    background-color: var(--application-color);
}

.btn-applications:hover {
    background-color: #3a5fca;
}

.btn-message {
    background-color: var(--warning-color);
}

.btn-message:hover {
    background-color: #e5b03a;
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

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
