<?php
include_once 'includes/header.php';

// Get assessment results with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Number of results per page
$offset = ($page - 1) * $limit;

// Filter by user if provided
$user_filter = "";
$user_params = [];
$user_types = [];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_filter = "AND ua.user_id = ?";
    $user_params[] = $_GET['user_id'];
    $user_types[] = "i";
}

// Filter by eligibility if provided
$eligibility_filter = "";
if (isset($_GET['eligible']) && $_GET['eligible'] !== '') {
    $eligibility_filter = "AND ua.result_eligible = ?";
    $user_params[] = $_GET['eligible'];
    $user_types[] = "i";
}

// Filter by completion status if provided
$completion_filter = "";
if (isset($_GET['complete']) && $_GET['complete'] !== '') {
    $completion_filter = "AND ua.is_complete = ?";
    $user_params[] = $_GET['complete'];
    $user_types[] = "i";
}

// Filter by date range if provided
$date_filter = "";
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $date_filter .= "AND DATE(ua.start_time) >= ?";
    $user_params[] = $_GET['start_date'];
    $user_types[] = "s";
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $date_filter .= "AND DATE(ua.start_time) <= ?";
    $user_params[] = $_GET['end_date'];
    $user_types[] = "s";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM user_assessments ua 
                WHERE 1=1 $user_filter $eligibility_filter $completion_filter $date_filter";
                
$stmt = $conn->prepare($count_query);

if (!empty($user_params)) {
    $stmt->bind_param(implode('', $user_types), ...$user_params);
}

$stmt->execute();
$total_results = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$stmt->close();

// Get assessment results
$query = "SELECT ua.*, 
          u.first_name, u.last_name, u.email,
          TIMESTAMPDIFF(MINUTE, ua.start_time, IFNULL(ua.end_time, NOW())) as duration_minutes
          FROM user_assessments ua
          LEFT JOIN users u ON ua.user_id = u.id
          WHERE 1=1 $user_filter $eligibility_filter $completion_filter $date_filter
          ORDER BY ua.start_time DESC
          LIMIT ? OFFSET ?";

$all_params = $user_params;
$all_params[] = $limit;
$all_params[] = $offset;

$all_types = $user_types;
$all_types[] = "i";
$all_types[] = "i";

$stmt = $conn->prepare($query);
$stmt->bind_param(implode('', $all_types), ...$all_params);
$stmt->execute();
$results = $stmt->get_result();
$assessments = [];

while ($row = $results->fetch_assoc()) {
    $assessments[] = $row;
}
$stmt->close();

// Get statistics
$stats_query = "SELECT 
               COUNT(*) as total_assessments,
               SUM(is_complete) as completed_assessments,
               SUM(IF(is_complete = 1 AND result_eligible = 1, 1, 0)) as eligible_results,
               SUM(IF(is_complete = 1 AND result_eligible = 0, 1, 0)) as ineligible_results,
               AVG(TIMESTAMPDIFF(MINUTE, start_time, IF(end_time IS NULL, NOW(), end_time))) as avg_duration
               FROM user_assessments";
               
$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1><i class="fas fa-chart-bar"></i> Assessment Results</h1>
            <p>View and analyze user eligibility assessment results</p>
        </div>
        <div>
            <a href="eligibility-checker.php" class="btn cancel-btn">
                <i class="fas fa-arrow-left"></i> Back to Eligibility Checker
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stats-info">
                <h3>Total Assessments</h3>
                <span class="stats-value"><?php echo number_format($stats['total_assessments'] ?? 0); ?></span>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon completed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-info">
                <h3>Completed</h3>
                <span class="stats-value">
                    <?php 
                    $completed = $stats['completed_assessments'] ?? 0;
                    $total = $stats['total_assessments'] ?? 0;
                    echo number_format($completed);
                    if ($total > 0) {
                        echo ' <span class="stats-percentage">(' . 
                             round(($completed / $total) * 100) . 
                             '%)</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon eligible">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-info">
                <h3>Eligible Results</h3>
                <span class="stats-value">
                    <?php 
                    $eligible = $stats['eligible_results'] ?? 0;
                    $completed = $stats['completed_assessments'] ?? 0;
                    echo number_format($eligible);
                    if ($completed > 0) {
                        echo ' <span class="stats-percentage">(' . 
                             round(($eligible / $completed) * 100) . 
                             '%)</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon ineligible">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stats-info">
                <h3>Ineligible Results</h3>
                <span class="stats-value">
                    <?php 
                    $ineligible = $stats['ineligible_results'] ?? 0;
                    $completed = $stats['completed_assessments'] ?? 0;
                    echo number_format($ineligible);
                    if ($completed > 0) {
                        echo ' <span class="stats-percentage">(' . 
                             round(($ineligible / $completed) * 100) . 
                             '%)</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-info">
                <h3>Avg. Duration</h3>
                <span class="stats-value">
                    <?php 
                    $avg_mins = $stats['avg_duration'] !== null ? round($stats['avg_duration']) : 0;
                    if ($avg_mins < 1) {
                        echo 'Less than 1 min';
                    } elseif ($avg_mins < 60) {
                        echo $avg_mins . ' min' . ($avg_mins != 1 ? 's' : '');
                    } else {
                        $hours = floor($avg_mins / 60);
                        $mins = $avg_mins % 60;
                        echo $hours . ' hr' . ($hours != 1 ? 's' : '') . 
                             ($mins > 0 ? ' ' . $mins . ' min' . ($mins != 1 ? 's' : '') : '');
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-container">
        <form id="filterForm" method="GET" action="assessment_results.php" class="filter-form">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="user_id">User ID</label>
                    <input type="number" id="user_id" name="user_id" class="form-control" 
                           value="<?php echo isset($_GET['user_id']) ? htmlspecialchars($_GET['user_id']) : ''; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="complete">Status</label>
                    <select id="complete" name="complete" class="form-control">
                        <option value="">All</option>
                        <option value="1" <?php echo isset($_GET['complete']) && $_GET['complete'] === '1' ? 'selected' : ''; ?>>
                            Completed
                        </option>
                        <option value="0" <?php echo isset($_GET['complete']) && $_GET['complete'] === '0' ? 'selected' : ''; ?>>
                            In Progress
                        </option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="eligible">Result</label>
                    <select id="eligible" name="eligible" class="form-control">
                        <option value="">All</option>
                        <option value="1" <?php echo isset($_GET['eligible']) && $_GET['eligible'] === '1' ? 'selected' : ''; ?>>
                            Eligible
                        </option>
                        <option value="0" <?php echo isset($_GET['eligible']) && $_GET['eligible'] === '0' ? 'selected' : ''; ?>>
                            Not Eligible
                        </option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control"
                           value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control"
                           value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn primary-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="assessment_results.php" class="btn cancel-btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <div class="card">
        <div class="card-header">
            <h5>Assessment Results</h5>
            <span class="result-count"><?php echo number_format($total_results); ?> result<?php echo $total_results != 1 ? 's' : ''; ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($assessments)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No assessment results match your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="assessment-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Start Time</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $assessment): ?>
                                <tr>
                                    <td><?php echo $assessment['id']; ?></td>
                                    <td>
                                        <?php if ($assessment['user_id'] > 0 && !empty($assessment['email'])): ?>
                                            <div class="user-info">
                                                <div>
                                                    <?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?>
                                                </div>
                                                <div class="user-email">
                                                    <?php echo htmlspecialchars($assessment['email']); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="anonymous-user">Anonymous User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($assessment['start_time'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($assessment['is_complete']): ?>
                                            <span class="status-badge completed">Completed</span>
                                        <?php else: ?>
                                            <span class="status-badge pending">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($assessment['is_complete']): ?>
                                            <?php if ($assessment['result_eligible']): ?>
                                                <span class="status-badge eligible">Eligible</span>
                                            <?php else: ?>
                                                <span class="status-badge ineligible">Not Eligible</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge neutral">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $duration = $assessment['duration_minutes'];
                                        if ($duration < 1) {
                                            echo 'Less than 1 min';
                                        } elseif ($duration < 60) {
                                            echo $duration . ' min' . ($duration != 1 ? 's' : '');
                                        } else {
                                            $hours = floor($duration / 60);
                                            $mins = $duration % 60;
                                            echo $hours . ' hr' . ($hours != 1 ? 's' : '') . 
                                                 ($mins > 0 ? ' ' . $mins . ' min' . ($mins != 1 ? 's' : '') : '');
                                        }
                                        ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn-action btn-view view-details" title="View Details" data-id="<?php echo $assessment['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($assessment['user_id'] > 0): ?>
                                            <a href="user_profile.php?id=<?php echo $assessment['user_id']; ?>" class="btn-action btn-user" title="View User">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn-action btn-delete delete-assessment" title="Delete Assessment" data-id="<?php echo $assessment['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?><?php echo isset($_GET['complete']) ? '&complete=' . htmlspecialchars($_GET['complete']) : ''; ?><?php echo isset($_GET['eligible']) ? '&eligible=' . htmlspecialchars($_GET['eligible']) : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" class="pagination-item">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            if ($start_page > 1): ?>
                                <a href="?page=1<?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?><?php echo isset($_GET['complete']) ? '&complete=' . htmlspecialchars($_GET['complete']) : ''; ?><?php echo isset($_GET['eligible']) ? '&eligible=' . htmlspecialchars($_GET['eligible']) : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" class="pagination-item">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?><?php echo isset($_GET['complete']) ? '&complete=' . htmlspecialchars($_GET['complete']) : ''; ?><?php echo isset($_GET['eligible']) ? '&eligible=' . htmlspecialchars($_GET['eligible']) : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?><?php echo isset($_GET['complete']) ? '&complete=' . htmlspecialchars($_GET['complete']) : ''; ?><?php echo isset($_GET['eligible']) ? '&eligible=' . htmlspecialchars($_GET['eligible']) : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" class="pagination-item">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?><?php echo isset($_GET['complete']) ? '&complete=' . htmlspecialchars($_GET['complete']) : ''; ?><?php echo isset($_GET['eligible']) ? '&eligible=' . htmlspecialchars($_GET['eligible']) : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>" class="pagination-item">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assessment Details Modal -->
<div class="modal" id="assessmentDetailsModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assessment Details</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="loader">Loading assessment details...</div>
                <div id="assessmentDetails" style="display: none;">
                    <div class="assessment-info">
                        <div class="detail-row">
                            <div class="detail-label">Assessment ID:</div>
                            <div class="detail-value" id="detail-id"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">User:</div>
                            <div class="detail-value" id="detail-user"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Start Time:</div>
                            <div class="detail-value" id="detail-start-time"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">End Time:</div>
                            <div class="detail-value" id="detail-end-time"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Duration:</div>
                            <div class="detail-value" id="detail-duration"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value" id="detail-status"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Result:</div>
                            <div class="detail-value" id="detail-result"></div>
                        </div>
                    </div>
                    
                    <h4 class="answers-heading">Assessment Answers</h4>
                    <div class="answers-list" id="answers-container">
                        <!-- Answers will be populated here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal" id="confirmationModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this assessment? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST" action="ajax/delete_assessment.php">
                    <input type="hidden" name="assessment_id" id="delete_assessment_id" value="">
                    <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
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

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stats-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    display: flex;
    align-items: center;
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-right: 15px;
}

.stats-icon.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.stats-icon.eligible {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.stats-icon.ineligible {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.stats-info h3 {
    margin: 0 0 5px;
    font-size: 14px;
    color: var(--secondary-color);
}

.stats-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark-color);
}

.stats-percentage {
    font-size: 14px;
    font-weight: normal;
    color: var(--secondary-color);
}

/* Filter Form */
.filter-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 15px;
    margin-bottom: 20px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    margin-bottom: 5px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 500;
    color: var(--dark-color);
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

/* Card Styles */
.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.result-count {
    color: var(--secondary-color);
    font-size: 14px;
}

.card-body {
    padding: 20px;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.assessment-table {
    width: 100%;
    border-collapse: collapse;
}

.assessment-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.assessment-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.assessment-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.user-info {
    display: flex;
    flex-direction: column;
}

.user-info div:first-child {
    font-weight: 500;
}

.user-email {
    font-size: 12px;
    color: var(--secondary-color);
}

.anonymous-user {
    color: var(--secondary-color);
    font-style: italic;
}

.actions-cell {
    display: flex;
    gap: 5px;
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

.btn-user {
    background-color: var(--info-color);
}

.btn-user:hover {
    background-color: #2fa9bd;
}

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
    background-color: #d44235;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.eligible {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.status-badge.ineligible {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge.neutral {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Pagination */
.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px;
}

.pagination-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 4px;
    font-size: 14px;
    color: var(--primary-color);
    background-color: white;
    border: 1px solid var(--border-color);
    text-decoration: none;
    transition: background-color 0.2s;
}

.pagination-item:hover {
    background-color: var(--light-color);
}

.pagination-item.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    font-size: 14px;
    color: var(--secondary-color);
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
    max-width: 600px;
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

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Assessment Details */
.assessment-info {
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    padding: 15px;
    background-color: var(--light-color);
}

.detail-row {
    display: flex;
    margin-bottom: 8px;
}

.detail-label {
    width: 120px;
    font-weight: 500;
    color: var(--dark-color);
}

.detail-value {
    flex: 1;
    color: var(--secondary-color);
}

.answers-heading {
    margin: 20px 0 10px;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.answers-list {
    border: 1px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
}

.answer-item {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.answer-item:last-child {
    border-bottom: none;
}

.answer-question {
    font-weight: 500;
    margin-bottom: 5px;
    color: var(--dark-color);
}

.answer-option {
    color: var(--secondary-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s;
    border: none;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover {
    background-color: #031c56;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.cancel-btn:hover {
    background-color: var(--light-color);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #d44235;
}

.loader {
    text-align: center;
    padding: 20px;
    color: var(--secondary-color);
}

/* Responsive styles */
@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        flex-direction: column;
    }
    
    .pagination {
        gap: 3px;
    }
    
    .pagination-item {
        min-width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 3px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View assessment details
    const viewButtons = document.querySelectorAll('.view-details');
    const assessmentDetailsModal = document.getElementById('assessmentDetailsModal');
    const assessmentDetails = document.getElementById('assessmentDetails');
    const loader = document.querySelector('.loader');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assessmentId = this.getAttribute('data-id');
            
            // Show modal with loader
            assessmentDetailsModal.style.display = 'block';
            loader.style.display = 'block';
            assessmentDetails.style.display = 'none';
            
            // Fetch assessment details
            fetch(`ajax/get_assessment_details.php?id=${assessmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate details
                        document.getElementById('detail-id').textContent = data.assessment.id;
                        
                        if (data.assessment.user_id > 0 && data.assessment.email) {
                            document.getElementById('detail-user').textContent = 
                                `${data.assessment.first_name} ${data.assessment.last_name} (${data.assessment.email})`;
                        } else {
                            document.getElementById('detail-user').textContent = 'Anonymous User';
                        }
                        
                        document.getElementById('detail-start-time').textContent = formatDateTime(data.assessment.start_time);
                        document.getElementById('detail-end-time').textContent = data.assessment.end_time ? 
                            formatDateTime(data.assessment.end_time) : 'Not completed';
                        
                        const duration = data.assessment.duration_minutes;
                        document.getElementById('detail-duration').textContent = formatDuration(duration);
                        
                        document.getElementById('detail-status').textContent = 
                            data.assessment.is_complete ? 'Completed' : 'In Progress';
                        
                        if (data.assessment.is_complete) {
                            document.getElementById('detail-result').textContent = 
                                data.assessment.result_eligible ? 'Eligible' : 'Not Eligible';
                            
                            if (data.assessment.result_text) {
                                document.getElementById('detail-result').textContent += ` - ${data.assessment.result_text}`;
                            }
                        } else {
                            document.getElementById('detail-result').textContent = 'Pending';
                        }
                        
                        // Populate answers
                        const answersContainer = document.getElementById('answers-container');
                        answersContainer.innerHTML = '';
                        
                        if (data.answers && data.answers.length > 0) {
                            data.answers.forEach(answer => {
                                const answerItem = document.createElement('div');
                                answerItem.className = 'answer-item';
                                
                                const questionElement = document.createElement('div');
                                questionElement.className = 'answer-question';
                                questionElement.textContent = `Q: ${answer.question_text}`;
                                
                                const optionElement = document.createElement('div');
                                optionElement.className = 'answer-option';
                                optionElement.textContent = `A: ${answer.option_text}`;
                                
                                answerItem.appendChild(questionElement);
                                answerItem.appendChild(optionElement);
                                
                                answersContainer.appendChild(answerItem);
                            });
                        } else {
                            const noAnswersElement = document.createElement('div');
                            noAnswersElement.className = 'answer-item';
                            noAnswersElement.textContent = 'No answers recorded for this assessment.';
                            answersContainer.appendChild(noAnswersElement);
                        }
                        
                        // Hide loader and show details
                        loader.style.display = 'none';
                        assessmentDetails.style.display = 'block';
                    } else {
                        // Show error
                        loader.textContent = `Error: ${data.message || 'Failed to load assessment details'}`;
                    }
                })
                .catch(error => {
                    loader.textContent = 'Error loading assessment details. Please try again.';
                    console.error('Error fetching assessment details:', error);
                });
        });
    });
    
    // Delete assessment
    const deleteButtons = document.querySelectorAll('.delete-assessment');
    const confirmationModal = document.getElementById('confirmationModal');
    const deleteAssessmentIdInput = document.getElementById('delete_assessment_id');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assessmentId = this.getAttribute('data-id');
            deleteAssessmentIdInput.value = assessmentId;
            confirmationModal.style.display = 'block';
        });
    });
    
    // Close modals
    document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Helper functions
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        const date = new Date(dateTimeString);
        return date.toLocaleString();
    }
    
    function formatDuration(minutes) {
        if (minutes < 1) {
            return 'Less than 1 minute';
        } else if (minutes < 60) {
            return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
        } else {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours} hour${hours !== 1 ? 's' : ''} ${mins > 0 ? `${mins} minute${mins !== 1 ? 's' : ''}` : ''}`;
        }
    }
});
</script>
