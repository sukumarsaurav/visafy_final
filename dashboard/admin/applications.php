<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Applications Management";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Get all applicants (users with type 'applicant')
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email 
          FROM users 
          WHERE user_type = 'applicant' AND deleted_at IS NULL
          ORDER BY first_name, last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$applicants_result = $stmt->get_result();
$applicants = [];

if ($applicants_result && $applicants_result->num_rows > 0) {
    while ($row = $applicants_result->fetch_assoc()) {
        $applicants[] = $row;
    }
}
$stmt->close();

// Get all countries
$query = "SELECT * FROM countries WHERE is_active = 1 ORDER BY country_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$countries_result = $stmt->get_result();
$countries = [];

if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[] = $row;
    }
}
$stmt->close();

// Get all team members
$query = "SELECT tm.id, tm.role, tm.custom_role_name, 
          u.id as user_id, u.first_name, u.last_name, u.email, u.status
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          WHERE tm.deleted_at IS NULL AND u.status = 'active'
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[] = $row;
    }
}
$stmt->close();

// Get all application statuses
$query = "SELECT * FROM application_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$application_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $application_statuses[] = $row;
    }
}
$stmt->close();

// Handle new application creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_application'])) {
    $user_id = $_POST['user_id'];
    $visa_id = $_POST['visa_id'];
    $team_member_id = !empty($_POST['team_member_id']) ? $_POST['team_member_id'] : NULL;
    $priority = $_POST['priority'];
    $notes = trim($_POST['notes']);
    $expected_completion_date = !empty($_POST['expected_completion_date']) ? $_POST['expected_completion_date'] : NULL;
    
    // Get draft status id
    $draft_status_query = "SELECT id FROM application_statuses WHERE name = 'draft' LIMIT 1";
    $stmt = $conn->prepare($draft_status_query);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_id = $result->fetch_assoc()['id'];
    $stmt->close();
    
    // Validate inputs
    $errors = [];
    if (empty($user_id)) {
        $errors[] = "Applicant is required";
    }
    if (empty($visa_id)) {
        $errors[] = "Visa type is required";
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert new application
            $insert_query = "INSERT INTO applications (user_id, visa_id, status_id, team_member_id, notes, 
                          expected_completion_date, priority, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $created_by = $_SESSION['user_id']; // Assuming admin's user ID is stored in session
            $stmt->bind_param('iiissisi', $user_id, $visa_id, $status_id, $team_member_id, $notes, 
                            $expected_completion_date, $priority, $created_by);
            
            if ($stmt->execute()) {
                $application_id = $conn->insert_id;
                
                // Insert document requirements
                if (isset($_POST['documents']) && is_array($_POST['documents'])) {
                    foreach ($_POST['documents'] as $document_id) {
                        $doc_query = "INSERT INTO application_documents (application_id, document_type_id, status) 
                                     VALUES (?, ?, 'pending')";
                        $doc_stmt = $conn->prepare($doc_query);
                        $doc_stmt->bind_param('ii', $application_id, $document_id);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
                
                // Log the application creation activity
                $activity_query = "INSERT INTO application_activity_logs 
                                (application_id, user_id, activity_type, description, ip_address)
                                VALUES (?, ?, 'created', 'Application created', ?)";
                $activity_stmt = $conn->prepare($activity_query);
                $ip = $_SERVER['REMOTE_ADDR'];
                $activity_stmt->bind_param('iis', $application_id, $created_by, $ip);
                $activity_stmt->execute();
                $activity_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Application created successfully";
                header("Location: applications.php?success=1");
                exit;
            } else {
                throw new Exception("Error creating application: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
        
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get all applications with related data
$query = "SELECT * FROM applications_view ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$applications_result = $stmt->get_result();
$applications = [];

if ($applications_result && $applications_result->num_rows > 0) {
    while ($row = $applications_result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$stmt->close();

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Application created successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Applications Management</h1>
            <p>Create and manage visa applications for clients</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="createApplicationBtn">
                <i class="fas fa-plus"></i> Create Application
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Applications Table Section -->
    <div class="applications-container">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No applications yet. Create an application to get started!</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Visa Type</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Case Manager</th>
                        <th>Documents</th>
                        <th>Priority</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($application['reference_number']); ?></td>
                            <td><?php echo htmlspecialchars($application['applicant_name']); ?></td>
                            <td><?php echo htmlspecialchars($application['visa_type']); ?></td>
                            <td><?php echo htmlspecialchars($application['country_name']); ?></td>
                            <td>
                                <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>10; color: <?php echo $application['status_color']; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst(htmlspecialchars($application['status_name'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($application['case_manager_name'])): ?>
                                    <?php echo htmlspecialchars($application['case_manager_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo $application['total_documents'] > 0 ? ($application['approved_documents'] / $application['total_documents'] * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo $application['approved_documents']; ?>/<?php echo $application['total_documents']; ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="priority-badge priority-<?php echo strtolower($application['priority']); ?>">
                                    <?php echo ucfirst($application['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                            <td class="actions-cell">
                                <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_application.php?id=<?php echo $application['id']; ?>" class="btn-action btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Create Application Modal with Multi-step Form -->
<div class="modal" id="createApplicationModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create Application</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="applications.php" method="POST" id="createApplicationForm">
                    <!-- Step indicators -->
                    <div class="step-indicator">
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-title">Applicant</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-title">Visa</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-title">Documents</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-title">Assignment</div>
                        </div>
                        <div class="step" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-title">Review</div>
                        </div>
                    </div>

                    <!-- Step 1: Select Applicant -->
                    <div class="form-step active" id="step1">
                        <h4>Select Applicant</h4>
                        <div class="form-group">
                            <label for="user_id">Applicant*</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Select Applicant</option>
                                <?php foreach ($applicants as $applicant): ?>
                                    <option value="<?php echo $applicant['id']; ?>"><?php echo htmlspecialchars($applicant['full_name']); ?> (<?php echo htmlspecialchars($applicant['email']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Only applicants with a verified account are shown.</small>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn submit-btn next-step" data-next="2">Next</button>
                        </div>
                    </div>

                    <!-- Step 2: Select Country and Visa -->
                    <div class="form-step" id="step2">
                        <h4>Select Visa Type</h4>
                        <div class="form-group">
                            <label for="country_id">Country*</label>
                            <select name="country_id" id="country_id" class="form-control" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['country_id']; ?>"><?php echo htmlspecialchars($country['country_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visa_id">Visa Type*</label>
                            <select name="visa_id" id="visa_id" class="form-control" required disabled>
                                <option value="">Select Visa Type</option>
                            </select>
                        </div>
                        <div id="visa_info" class="info-box" style="display: none;"></div>
                        <div class="form-buttons">
                            <button type="button" class="btn cancel-btn prev-step" data-prev="1">Previous</button>
                            <button type="button" class="btn submit-btn next-step" data-next="3">Next</button>
                        </div>
                    </div>

                    <!-- Step 3: Required Documents -->
                    <div class="form-step" id="step3">
                        <h4>Required Documents</h4>
                        <div id="document_requirements" class="document-container">
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>Please select a visa type first to see required documents.</p>
                            </div>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn cancel-btn prev-step" data-prev="2">Previous</button>
                            <button type="button" class="btn submit-btn next-step" data-next="4">Next</button>
                        </div>
                    </div>

                    <!-- Step 4: Assignment Details -->
                    <div class="form-step" id="step4">
                        <h4>Assignment Details</h4>
                        <div class="form-group">
                            <label for="team_member_id">Assign Case Manager</label>
                            <select name="team_member_id" id="team_member_id" class="form-control">
                                <option value="">Select Case Manager (Optional)</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                                        (<?php echo $member['role'] === 'Custom' ? htmlspecialchars($member['custom_role_name']) : htmlspecialchars($member['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="expected_completion_date">Expected Completion Date</label>
                            <input type="date" name="expected_completion_date" id="expected_completion_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control"></textarea>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn cancel-btn prev-step" data-prev="3">Previous</button>
                            <button type="button" class="btn submit-btn next-step" data-next="5">Next</button>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="form-step" id="step5">
                        <h4>Review Application</h4>
                        <div class="review-container">
                            <div class="review-section">
                                <h5>Applicant</h5>
                                <p id="review_applicant">Please select an applicant</p>
                            </div>
                            <div class="review-section">
                                <h5>Visa Details</h5>
                                <p id="review_visa">Please select a visa type</p>
                                <p id="review_country"></p>
                            </div>
                            <div class="review-section">
                                <h5>Required Documents</h5>
                                <ul id="review_documents">
                                    <li class="text-muted">No documents selected</li>
                                </ul>
                            </div>
                            <div class="review-section">
                                <h5>Assignment Details</h5>
                                <p id="review_case_manager">Not assigned</p>
                                <p>Priority: <span id="review_priority">Normal</span></p>
                                <p id="review_completion_date"></p>
                            </div>
                            <div class="review-section">
                                <h5>Notes</h5>
                                <p id="review_notes" class="text-muted">No notes added</p>
                            </div>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn cancel-btn prev-step" data-prev="4">Previous</button>
                            <button type="submit" name="create_application" class="btn submit-btn">Create Application</button>
                        </div>
                    </div>
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

.applications-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
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

.status-badge i {
    font-size: 8px;
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.priority-low {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.priority-normal {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.priority-high {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.priority-urgent {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.progress {
    height: 100%;
    background-color: var(--primary-color);
}

.progress-text {
    font-size: 12px;
    color: var(--secondary-color);
    min-width: 36px;
    text-align: right;
}

.text-muted {
    color: var(--secondary-color);
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
    margin: 40px auto;
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

.form-group {
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

/* Step Indicator Styles */
.step-indicator {
    display: flex;
    margin-bottom: 20px;
    background-color: var(--light-color);
    border-radius: 4px;
    overflow: hidden;
}

.step {
    flex: 1;
    padding: 12px 8px;
    text-align: center;
    position: relative;
    color: var(--secondary-color);
}

.step::after {
    content: '';
    position: absolute;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 15px 0 15px 10px;
    border-color: transparent transparent transparent var(--light-color);
    z-index: 1;
}

.step:last-child::after {
    display: none;
}

.step.active {
    background-color: var(--primary-color);
    color: white;
}

.step.active::after {
    border-color: transparent transparent transparent var(--primary-color);
}

.step.completed {
    background-color: var(--info-color);
    color: white;
}

.step.completed::after {
    border-color: transparent transparent transparent var(--info-color);
}

.step-number {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 5px;
    font-size: 13px;
    font-weight: bold;
}

.step-title {
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Form Step Styles */
.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

/* Document container styles */
.document-container {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.document-item {
    display: flex;
    align-items: flex-start;
    padding: 10px;
    border-bottom: 1px solid var(--border-color);
}

.document-item:last-child {
    border-bottom: none;
}

.document-checkbox {
    margin-right: 10px;
    margin-top: 3px;
}

.document-details {
    flex: 1;
}

.document-name {
    font-weight: 500;
    margin-bottom: 3px;
}

.document-description {
    font-size: 13px;
    color: var(--secondary-color);
}

.mandatory-badge {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 5px;
}

/* Info box styles */
.info-box {
    background-color: rgba(54, 185, 204, 0.1);
    border: 1px solid rgba(54, 185, 204, 0.2);
    color: var(--info-color);
    padding: 12px 15px;
    border-radius: 4px;
    margin-top: 10px;
    font-size: 14px;
}

/* Review container styles */
.review-container {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 0;
    overflow: hidden;
}

.review-section {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.review-section:last-child {
    border-bottom: none;
}

.review-section h5 {
    margin: 0 0 10px;
    color: var(--primary-color);
    font-size: 16px;
}

.review-section p {
    margin: 0 0 5px;
}

.review-section ul {
    margin: 0;
    padding-left: 20px;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .step-indicator {
        overflow-x: auto;
    }
    
    .step {
        padding: 10px 5px;
        min-width: 80px;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    
    .modal-dialog {
        margin: 20px 10px;
        max-width: none;
        width: auto;
    }
}
</style>

<script>
// Modal functionality
document.getElementById('createApplicationBtn').addEventListener('click', function() {
    document.getElementById('createApplicationModal').style.display = 'block';
});

// Close modal when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        document.getElementById('createApplicationModal').style.display = 'none';
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    const modal = document.getElementById('createApplicationModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Multi-step form navigation
document.querySelectorAll('.next-step').forEach(function(button) {
    button.addEventListener('click', function() {
        const currentStep = this.closest('.form-step');
        const nextStepNum = this.getAttribute('data-next');
        
        // Validate current step
        if (validateStep(currentStep.id)) {
            // Update step indicator
            document.querySelector(`.step[data-step="${nextStepNum}"]`).classList.add('active');
            
            // Hide current step and show next step
            currentStep.classList.remove('active');
            document.getElementById(`step${nextStepNum}`).classList.add('active');
            
            // Add completed class to previous step indicator
            const currentStepNum = currentStep.id.replace('step', '');
            document.querySelector(`.step[data-step="${currentStepNum}"]`).classList.add('completed');
            
            // Update review information if going to review step
            if (nextStepNum === '5') {
                updateReviewInfo();
            }
        }
    });
});

document.querySelectorAll('.prev-step').forEach(function(button) {
    button.addEventListener('click', function() {
        const currentStep = this.closest('.form-step');
        const prevStepNum = this.getAttribute('data-prev');
        
        // Update step indicator (remove active from current)
        const currentStepNum = currentStep.id.replace('step', '');
        document.querySelector(`.step[data-step="${currentStepNum}"]`).classList.remove('active');
        
        // Hide current step and show previous step
        currentStep.classList.remove('active');
        document.getElementById(`step${prevStepNum}`).classList.add('active');
        
        // Make previous step active again
        document.querySelector(`.step[data-step="${prevStepNum}"]`).classList.add('active');
        document.querySelector(`.step[data-step="${prevStepNum}"]`).classList.remove('completed');
    });
});

// Function to validate each step
function validateStep(stepId) {
    let isValid = true;
    
    switch(stepId) {
        case 'step1':
            const userId = document.getElementById('user_id').value;
            if (!userId) {
                alert('Please select an applicant.');
                isValid = false;
            }
            break;
        case 'step2':
            const countryId = document.getElementById('country_id').value;
            const visaId = document.getElementById('visa_id').value;
            
            if (!countryId) {
                alert('Please select a country.');
                isValid = false;
            } else if (!visaId) {
                alert('Please select a visa type.');
                isValid = false;
            }
            break;
        case 'step3':
            // Check if at least one document is selected or if no documents are required
            const documentCheckboxes = document.querySelectorAll('input[name="documents[]"]:checked');
            const mandatoryDocuments = document.querySelectorAll('input[name="documents[]"][data-mandatory="1"]:not(:checked)');
            
            if (mandatoryDocuments.length > 0) {
                alert('Please select all mandatory documents.');
                isValid = false;
            }
            break;
        case 'step4':
            // No mandatory fields in step 4
            break;
    }
    
    return isValid;
}

// Load visa types based on country selection
document.getElementById('country_id').addEventListener('change', function() {
    const countryId = this.value;
    const visaSelect = document.getElementById('visa_id');
    
    if (countryId) {
        // Enable the visa select
        visaSelect.disabled = false;
        
        // Use AJAX to fetch visa types for the selected country
        fetch('ajax/get_visa_types.php?country_id=' + countryId)
            .then(response => response.json())
            .then(data => {
                visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
                
                if (data.length > 0) {
                    data.forEach(function(visa) {
                        const option = document.createElement('option');
                        option.value = visa.visa_id;
                        option.textContent = visa.visa_type;
                        visaSelect.appendChild(option);
                    });
                } else {
                    visaSelect.innerHTML = '<option value="">No visa types found</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching visa types:', error);
                visaSelect.innerHTML = '<option value="">Error loading visa types</option>';
            });
    } else {
        // Reset and disable the visa select
        visaSelect.innerHTML = '<option value="">Select Visa Type</option>';
        visaSelect.disabled = true;
        
        // Hide visa info
        document.getElementById('visa_info').style.display = 'none';
        
        // Reset documents section
        document.getElementById('document_requirements').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Please select a visa type first to see required documents.</p>
            </div>`;
    }
});

// Load document requirements when visa type is selected
document.getElementById('visa_id').addEventListener('change', function() {
    const visaId = this.value;
    const documentContainer = document.getElementById('document_requirements');
    
    if (visaId) {
        // Use AJAX to fetch visa information
        fetch('ajax/get_visa_info.php?visa_id=' + visaId)
            .then(response => response.json())
            .then(data => {
                // Show visa info
                const visaInfoBox = document.getElementById('visa_info');
                if (data.visa) {
                    visaInfoBox.innerHTML = `
                        <p><strong>${data.visa.visa_type}</strong> - Validity: ${data.visa.validity_period} days</p>
                        <p>${data.visa.description || 'No description available.'}</p>
                    `;
                    visaInfoBox.style.display = 'block';
                } else {
                    visaInfoBox.style.display = 'none';
                }
                
                // Load document requirements
                return fetch('ajax/get_document_requirements.php?visa_id=' + visaId);
            })
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    
                    data.forEach(function(doc) {
                        html += `
                            <div class="document-item">
                                <input type="checkbox" name="documents[]" id="doc_${doc.id}" 
                                       value="${doc.id}" class="document-checkbox" 
                                       ${doc.is_mandatory ? 'checked data-mandatory="1" required' : ''}>
                                <div class="document-details">
                                    <label for="doc_${doc.id}" class="document-name">
                                        ${doc.name} 
                                        ${doc.is_mandatory ? '<span class="mandatory-badge">Required</span>' : ''}
                                    </label>
                                    <div class="document-description">
                                        ${doc.description || 'No additional details available.'}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    documentContainer.innerHTML = html;
                } else {
                    documentContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No document requirements found for this visa type.</p>
                        </div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                documentContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error loading document requirements. Please try again.</p>
                    </div>`;
            });
    } else {
        // Reset document container
        documentContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Please select a visa type first to see required documents.</p>
            </div>`;
        
        // Hide visa info
        document.getElementById('visa_info').style.display = 'none';
    }
});

// Update review information
function updateReviewInfo() {
    // Applicant
    const userSelect = document.getElementById('user_id');
    const userText = userSelect.options[userSelect.selectedIndex].text;
    document.getElementById('review_applicant').textContent = userSelect.value ? userText : 'Please select an applicant';
    
    // Visa & Country
    const visaSelect = document.getElementById('visa_id');
    const visaText = visaSelect.options[visaSelect.selectedIndex].text;
    document.getElementById('review_visa').textContent = visaSelect.value ? visaText : 'Please select a visa type';
    
    const countrySelect = document.getElementById('country_id');
    const countryText = countrySelect.options[countrySelect.selectedIndex].text;
    document.getElementById('review_country').textContent = countrySelect.value ? `Country: ${countryText}` : '';
    
    // Documents
    const documentList = document.getElementById('review_documents');
    const selectedDocs = document.querySelectorAll('input[name="documents[]"]:checked');
    
    if (selectedDocs.length > 0) {
        let docListHtml = '';
        selectedDocs.forEach(function(checkbox) {
            const docName = checkbox.nextElementSibling.querySelector('.document-name').textContent.trim().split(' ')[0]; // Just get the name without the "Required" badge
            docListHtml += `<li>${docName}</li>`;
        });
        documentList.innerHTML = docListHtml;
    } else {
        documentList.innerHTML = '<li class="text-muted">No documents selected</li>';
    }
    
    // Assignment details
    const teamMemberSelect = document.getElementById('team_member_id');
    if (teamMemberSelect.value) {
        const teamMemberText = teamMemberSelect.options[teamMemberSelect.selectedIndex].text;
        document.getElementById('review_case_manager').textContent = `Case Manager: ${teamMemberText}`;
    } else {
        document.getElementById('review_case_manager').textContent = 'Case Manager: Not assigned';
    }
    
    const prioritySelect = document.getElementById('priority');
    const priorityText = prioritySelect.options[prioritySelect.selectedIndex].text;
    document.getElementById('review_priority').textContent = priorityText;
    
    const completionDate = document.getElementById('expected_completion_date').value;
    if (completionDate) {
        const formattedDate = new Date(completionDate).toLocaleDateString('en-US', {
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        });
        document.getElementById('review_completion_date').textContent = `Expected completion: ${formattedDate}`;
    } else {
        document.getElementById('review_completion_date').textContent = '';
    }
    
    // Notes
    const notes = document.getElementById('notes').value;
    if (notes.trim()) {
        document.getElementById('review_notes').textContent = notes;
        document.getElementById('review_notes').classList.remove('text-muted');
    } else {
        document.getElementById('review_notes').textContent = 'No notes added';
        document.getElementById('review_notes').classList.add('text-muted');
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>