<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Client Bookings";
$page_specific_css = "assets/css/client_bookings.css";
require_once 'includes/header.php';

// Check if client ID is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    // Redirect to clients list if no client_id provided
    header("Location: clients.php");
    exit;
}

$client_id = (int)$_GET['client_id'];

// Get client details
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, 
           u.email_verified, u.created_at
          FROM users u
          WHERE u.id = ? AND u.deleted_at IS NULL AND u.user_type = 'applicant'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Client not found, redirect to clients list
    $stmt->close();
    header("Location: clients.php");
    exit;
}

$client = $result->fetch_assoc();
$stmt->close();

// Get booking statistics
$query = "SELECT COUNT(*) as total_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'completed') THEN 1 ELSE 0 END) as completed_bookings,
          SUM(CASE WHEN b.status_id IN (SELECT id FROM booking_statuses WHERE name LIKE 'cancelled%') THEN 1 ELSE 0 END) as cancelled_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'pending') THEN 1 ELSE 0 END) as pending_bookings,
          SUM(CASE WHEN b.status_id = (SELECT id FROM booking_statuses WHERE name = 'confirmed') THEN 1 ELSE 0 END) as upcoming_bookings
          FROM bookings b
          WHERE b.user_id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$booking_stats = $stats_result->fetch_assoc();
$stmt->close();

// Get all booking statuses
$query = "SELECT * FROM booking_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$booking_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $booking_statuses[$row['id']] = $row;
    }
}
$stmt->close();

// Apply filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build booking query with filters
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name,
          cm.mode_name as consultation_mode,
          CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name,
          vs.base_price, scm.additional_fee,
          (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price,
          bp.payment_status
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users team_u ON tm.user_id = team_u.id
          LEFT JOIN booking_payments bp ON b.id = bp.booking_id
          WHERE b.deleted_at IS NULL AND b.user_id = ?";

// Apply additional filters
$params = [$client_id];
$param_types = "i";

if (!empty($status_filter)) {
    $query .= " AND bs.name = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.booking_datetime) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.booking_datetime) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if (!empty($search_term)) {
    $search_term = "%$search_term%";
    $query .= " AND (b.reference_number LIKE ? OR v.visa_type LIKE ? OR c.country_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

$query .= " ORDER BY b.booking_datetime DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Bookings for <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h1>
            <p>Manage and view all consultation bookings for this client</p>
        </div>
        <div class="action-buttons">
            <a href="view_client.php?id=<?php echo $client_id; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Client Profile
            </a>
            <a href="create_booking.php?client_id=<?php echo $client_id; ?>" class="btn primary-btn">
                <i class="fas fa-plus"></i> Create New Booking
            </a>
        </div>
    </div>
    
    <!-- Booking Statistics Section -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo intval($booking_stats['total_bookings'] ?? 0); ?></h3>
                <p>Total Bookings</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon upcoming">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo intval($booking_stats['upcoming_bookings'] ?? 0); ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon completed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo intval($booking_stats['completed_bookings'] ?? 0); ?></h3>
                <p>Completed</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon cancelled">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo intval($booking_stats['cancelled_bookings'] ?? 0); ?></h3>
                <p>Cancelled</p>
            </div>
        </div>
    </div>
    
    <!-- Filters Section -->
    <div class="filters-container">
        <form action="client_bookings.php" method="GET" class="filters-form">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            
            <div class="filter-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach ($booking_statuses as $status): ?>
                        <option value="<?php echo $status['name']; ?>" <?php echo ($status_filter === $status['name']) ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="filter-group search-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" 
                        placeholder="Ref #, Visa Type, Country" value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn filter-btn">Apply Filters</button>
                <a href="client_bookings.php?client_id=<?php echo $client_id; ?>" class="btn reset-btn">Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Bookings Table Section -->
    <div class="bookings-table-container">
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-alt"></i>
                <p>No bookings found for this client. Adjust your filters or create a new booking.</p>
            </div>
        <?php else: ?>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Date & Time</th>
                        <th>Service</th>
                        <th>Consultation</th>
                        <th>Consultant</th>
                        <th>Status</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($booking['booking_datetime'])); ?><br>
                                <span class="time"><?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?></span>
                                <span class="duration">(<?php echo $booking['duration_minutes']; ?> min)</span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($booking['visa_type']); ?></strong><br>
                                <span><?php echo htmlspecialchars($booking['service_name']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                            <td>
                                <?php if (!empty($booking['consultant_name'])): ?>
                                    <?php echo htmlspecialchars($booking['consultant_name']); ?>
                                <?php else: ?>
                                    <span class="not-assigned">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($booking['total_price'], 2); ?></td>
                            <td class="actions-cell">
                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if (in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                                    <a href="edit_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-edit" title="Edit Booking">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
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
    --info-color: #36b9cc;
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

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-back, .btn-edit, .primary-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-back {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.btn-edit {
    background-color: var(--warning-color);
    color: white;
}

/* Stats section */
.stat-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stat-icon.total {
    background-color: var(--primary-color);
}

.stat-icon.upcoming {
    background-color: var(--info-color);
}

.stat-icon.completed {
    background-color: var(--success-color);
}

.stat-icon.cancelled {
    background-color: var(--danger-color);
}

.stat-info h3 {
    margin: 0;
    font-size: 24px;
    color: var(--dark-color);
}

.stat-info p {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 14px;
}

/* Filters Styling */
.filters-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    margin-bottom: 20px;
}

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.search-group {
    flex: 2;
    min-width: 250px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

.filter-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.reset-btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

/* Bookings Table */
.bookings-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
}

.bookings-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.bookings-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.bookings-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.bookings-table tbody tr:last-child td {
    border-bottom: none;
}

.time, .duration {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.duration {
    margin-left: 5px;
}

.not-assigned {
    font-style: italic;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.actions-cell {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
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

@media (max-width: 992px) {
    .stat-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .filters-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .bookings-table {
        display: block;
        overflow-x: auto;
    }
}

@media (max-width: 576px) {
    .stat-cards {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-back, .primary-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
