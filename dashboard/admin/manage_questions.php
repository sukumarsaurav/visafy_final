<?php
include_once 'includes/header.php';

// Check if a question is being edited
$edit_mode = false;
$question_id = 0;
$question_data = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $question_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM decision_tree_questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_mode = true;
        $question_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all categories for dropdown
$categories = [];
$stmt = $conn->prepare("SELECT * FROM decision_tree_categories ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

// Get all questions for linking options to next questions
$all_questions = [];
$stmt = $conn->prepare("SELECT id, question_text FROM decision_tree_questions ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_questions[] = $row;
}
$stmt->close();

// Get options if editing a question
$options = [];
if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM decision_tree_options WHERE question_id = ? ORDER BY id");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }
    $stmt->close();
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>
                <i class="fas fa-sitemap"></i> 
                Decision Tree Questions
            </h1>
            <p>Manage questions and options for the eligibility checker</p>
        </div>
        <div>
            <button id="addQuestionBtn" class="btn primary-btn">
                <i class="fas fa-plus"></i> Add New Question
            </button>
            <a href="eligibility-checker.php" class="btn cancel-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Questions List -->
    <div class="questions-list-container">
        <div class="card">
            <div class="card-header">
                <h5>All Questions</h5>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <?php
                    $stmt = $conn->prepare("SELECT q.*, c.name as category_name, 
                                          (SELECT COUNT(*) FROM decision_tree_options WHERE question_id = q.id) as options_count 
                                          FROM decision_tree_questions q
                                          LEFT JOIN decision_tree_categories c ON q.category_id = c.id
                                          ORDER BY q.id");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $has_questions = ($result->num_rows > 0);
                    ?>
                    
                    <?php if (!$has_questions): ?>
                        <div class="empty-state">
                            <i class="fas fa-question-circle"></i>
                            <p>No questions yet. Create your first question to get started!</p>
                        </div>
                    <?php else: ?>
                        <table class="questions-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Question</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Options</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr data-id="<?php echo $row['id']; ?>">
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['question_text']); ?></td>
                                    <td><?php echo $row['category_name'] ? htmlspecialchars($row['category_name']) : 'Uncategorized'; ?></td>
                                    <td>
                                        <?php if ($row['is_active']): ?>
                                            <span class="status-badge completed">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge cancelled">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['options_count']; ?></td>
                                    <td class="actions-cell">
                                        <button class="btn-action btn-view view-question" title="View Details" data-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-edit edit-question" title="Edit Question" data-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-option add-option" title="Add Option" data-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                        <button class="btn-action btn-delete delete-question" title="Delete Question" data-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($edit_mode && !empty($options)): ?>
    <!-- Options List for the current question -->
    <div class="options-list-container">
        <div class="card">
            <div class="card-header">
                <h5>Options for Question #<?php echo $question_id; ?></h5>
                <div class="question-text"><?php echo htmlspecialchars($question_data['question_text']); ?></div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="options-table">
                        <thead>
                            <tr>
                                <th width="40%">Option Text</th>
                                <th>Next Question</th>
                                <th>Is Endpoint</th>
                                <th width="15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($options as $option): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($option['option_text']); ?></td>
                                <td>
                                    <?php 
                                    if ($option['is_endpoint']) {
                                        echo '<span class="status-badge pending">Endpoint</span>';
                                    } else {
                                        foreach ($all_questions as $q) {
                                            if ($q['id'] == $option['next_question_id']) {
                                                echo htmlspecialchars($q['question_text']);
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($option['is_endpoint']): ?>
                                        <span class="status-badge completed">Yes</span>
                                    <?php else: ?>
                                        <span class="status-badge cancelled">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn-action btn-edit edit-option" title="Edit" data-id="<?php echo $option['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action btn-delete delete-option" title="Delete" data-id="<?php echo $option['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button class="btn primary-btn add-option" data-id="<?php echo $question_id; ?>">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Question Modal -->
<div class="modal" id="questionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="questionModalTitle">Add New Question</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="questionForm" action="ajax/save_question.php" method="post">
                    <input type="hidden" name="question_id" id="edit_question_id" value="">
                    
                    <div class="form-group">
                        <label for="question_text">Question Text*</label>
                        <input type="text" class="form-control" id="question_text" name="question_text" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        <small class="form-text text-muted">Additional information to help users understand the question.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category (Optional)</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="saveQuestionBtn">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Option Modal -->
<div class="modal" id="optionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="optionModalTitle">Add Option</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="optionForm" action="ajax/save_option.php" method="post">
                    <input type="hidden" name="question_id" id="option_question_id" value="">
                    <input type="hidden" name="option_id" id="option_id" value="">
                    
                    <div class="form-group">
                        <label for="option_text">Option Text*</label>
                        <input type="text" class="form-control" id="option_text" name="option_text" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_endpoint" name="is_endpoint" value="1">
                            <label class="form-check-label" for="is_endpoint">
                                This is an endpoint (final answer)
                            </label>
                        </div>
                    </div>
                    
                    <div id="next-question-section">
                        <div class="form-group">
                            <label for="next_question_id">Next Question*</label>
                            <select class="form-control" id="next_question_id" name="next_question_id">
                                <option value="">-- Select Next Question --</option>
                                <?php foreach ($all_questions as $q): ?>
                                <option value="<?php echo $q['id']; ?>" class="next-question-option">
                                    <?php echo htmlspecialchars($q['question_text']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="endpoint-section" style="display: none;">
                        <div class="form-group">
                            <label for="endpoint_result">Result Message*</label>
                            <textarea class="form-control" id="endpoint_result" name="endpoint_result" rows="3"></textarea>
                            <small class="form-text text-muted">The message to show when this option is selected (the final result).</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="endpoint_eligible" name="endpoint_eligible" value="1">
                                <label class="form-check-label" for="endpoint_eligible">
                                    This endpoint indicates eligibility
                                </label>
                            </div>
                            <small class="form-text text-muted">If checked, this endpoint indicates the user is eligible for the visa/program.</small>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="saveOptionBtn">Save Option</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Question Details Modal -->
<div class="modal" id="viewQuestionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Question Details</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="question-details-content">
                    <div class="loader">Loading...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn primary-btn" id="viewQuestionOptions">View Options</button>
                <button type="button" class="btn cancel-btn" data-dismiss="modal">Close</button>
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
    --info-color: #36b9cc;
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

.header-container .btn {
    margin-left: 10px;
}

.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    height: 100%;
    margin-bottom: 20px;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.question-text {
    font-style: italic;
    color: var(--secondary-color);
    margin-left: 10px;
}

.card-body {
    padding: 20px;
}

.card-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    background-color: var(--light-color);
    text-align: right;
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

.table-container {
    overflow-x: auto;
}

.questions-table, .options-table {
    width: 100%;
    border-collapse: collapse;
}

.questions-table th, .options-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.questions-table td, .options-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.questions-table tbody tr:hover, .options-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.actions-cell {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
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

.submit-btn {
    background-color: var(--primary-color);
    color: white;
}

.submit-btn:hover {
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

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
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

.btn-option {
    background-color: var(--info-color);
}

.btn-option:hover {
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

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.cancelled {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
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

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.form-check-input {
    margin-right: 8px;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--secondary-color);
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
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

.question-details-item {
    margin-bottom: 15px;
}

.question-details-item h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: var(--secondary-color);
}

.question-details-item p {
    margin: 0;
    font-size: 16px;
    color: var(--dark-color);
}

.loader {
    text-align: center;
    padding: 20px;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .header-container .btn {
        margin-left: 0;
        margin-top: 5px;
        width: 100%;
    }
    
    .form-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .actions-cell {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-action {
        width: 28px;
        height: 28px;
    }
}

/* Add these styles to your existing CSS */
.question-details-row {
    background-color: #f8f9fc;
}

.expanded-details {
    padding: 20px;
    border-top: 2px solid var(--primary-color);
}

.details-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
}

.details-section {
    background: white;
    padding: 15px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.details-section h4 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.details-content p {
    margin: 8px 0;
    font-size: 14px;
}

.details-content p strong {
    color: var(--dark-color);
}

.options-section {
    background: white;
    padding: 15px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.options-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.options-header h4 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.options-table-container {
    max-height: 400px;
    overflow-y: auto;
}

.expanded-details .options-table {
    margin: 0;
    border: 1px solid var(--border-color);
}

.expanded-details .options-table th,
.expanded-details .options-table td {
    padding: 8px 12px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---------------
    // Modal handling
    // ---------------
    const questionModal = document.getElementById('questionModal');
    const optionModal = document.getElementById('optionModal');
    const viewQuestionModal = document.getElementById('viewQuestionModal');
    
    // Open modals
    document.getElementById('addQuestionBtn').addEventListener('click', function() {
        // Reset form
        document.getElementById('questionForm').reset();
        document.getElementById('edit_question_id').value = '';
        document.getElementById('questionModalTitle').textContent = 'Add New Question';
        document.getElementById('saveQuestionBtn').textContent = 'Save Question';
        
        // Show modal
        questionModal.style.display = 'block';
    });
    
    // Close modals when X is clicked
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
        element.addEventListener('click', function() {
            questionModal.style.display = 'none';
            optionModal.style.display = 'none';
            viewQuestionModal.style.display = 'none';
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === questionModal) {
            questionModal.style.display = 'none';
        }
        if (event.target === optionModal) {
            optionModal.style.display = 'none';
        }
        if (event.target === viewQuestionModal) {
            viewQuestionModal.style.display = 'none';
        }
    });
    
    // ---------------
    // Toggle endpoint vs next question sections
    // ---------------
    const isEndpointCheckbox = document.getElementById('is_endpoint');
    const nextQuestionSection = document.getElementById('next-question-section');
    const endpointSection = document.getElementById('endpoint-section');
    
    if (isEndpointCheckbox) {
        isEndpointCheckbox.addEventListener('change', function() {
            if (this.checked) {
                nextQuestionSection.style.display = 'none';
                endpointSection.style.display = 'block';
            } else {
                nextQuestionSection.style.display = 'block';
                endpointSection.style.display = 'none';
            }
        });
    }
    
    // ---------------
    // Question form submission
    // ---------------
    const questionForm = document.getElementById('questionForm');
    if (questionForm) {
        questionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/save_question.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to manage_questions.php to show the updated list
                    window.location.href = 'manage_questions.php?edit=' + data.question_id + '&success=1';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving question:', error);
                alert('An error occurred while saving the question');
            });
        });
    }
    
    // ---------------
    // Option form submission
    // ---------------
    const optionForm = document.getElementById('optionForm');
    if (optionForm) {
        optionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/save_option.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the modal
                    optionModal.style.display = 'none';
                    
                    // Reload page to show the updated option
                    window.location.href = 'manage_questions.php?edit=' + formData.get('question_id') + '&success=2';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving option:', error);
                alert('An error occurred while saving the option');
            });
        });
    }
    
    // ---------------
    // Edit question button click
    // ---------------
    const editQuestionButtons = document.querySelectorAll('.edit-question');
    editQuestionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const questionId = this.getAttribute('data-id');
            console.log('Editing question ID:', questionId);
            
            // Fetch question data
            fetch(`ajax/get_question.php?id=${questionId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Populate form
                        document.getElementById('edit_question_id').value = data.id;
                        document.getElementById('question_text').value = data.question_text;
                        document.getElementById('description').value = data.description || '';
                        document.getElementById('is_active').checked = data.is_active == 1;
                        
                        if (data.category_id) {
                            document.getElementById('category_id').value = data.category_id;
                        }
                        
                        // Update modal title and button
                        document.getElementById('questionModalTitle').textContent = 'Edit Question';
                        document.getElementById('saveQuestionBtn').textContent = 'Update Question';
                        
                        // Show modal
                        questionModal.style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching question data:', error);
                    alert('An error occurred while fetching question data. Check the console for details.');
                });
        });
    });
    
    // ---------------
    // Add option button click
    // ---------------
    const addOptionButtons = document.querySelectorAll('.add-option');
    addOptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const questionId = this.getAttribute('data-id');
            
            // Reset form
            document.getElementById('optionForm').reset();
            document.getElementById('option_id').value = '';
            document.getElementById('option_question_id').value = questionId;
            
            // Reset display
            nextQuestionSection.style.display = 'block';
            endpointSection.style.display = 'none';
            
            // Filter next question options to prevent circular references
            const nextQuestionOptions = document.querySelectorAll('.next-question-option');
            nextQuestionOptions.forEach(option => {
                if (option.value === questionId) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
            
            // Update modal title and button
            document.getElementById('optionModalTitle').textContent = 'Add Option';
            document.getElementById('saveOptionBtn').textContent = 'Save Option';
            
            // Show modal
            optionModal.style.display = 'block';
        });
    });
    
    // ---------------
    // Edit option button click
    // ---------------
    const editOptionButtons = document.querySelectorAll('.edit-option');
    editOptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const optionId = this.getAttribute('data-id');
            
            // Fetch option data
            fetch(`ajax/get_option.php?id=${optionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.id) {
                        // Populate form
                        document.getElementById('option_id').value = data.id;
                        document.getElementById('option_question_id').value = data.question_id;
                        document.getElementById('option_text').value = data.option_text;
                        document.getElementById('is_endpoint').checked = data.is_endpoint == 1;
                        
                        if (data.is_endpoint == 1) {
                            nextQuestionSection.style.display = 'none';
                            endpointSection.style.display = 'block';
                            document.getElementById('endpoint_result').value = data.endpoint_result || '';
                            document.getElementById('endpoint_eligible').checked = data.endpoint_eligible == 1;
                        } else {
                            nextQuestionSection.style.display = 'block';
                            endpointSection.style.display = 'none';
                            
                            if (data.next_question_id) {
                                document.getElementById('next_question_id').value = data.next_question_id;
                            }
                        }
                        
                        // Filter next question options to prevent circular references
                        const nextQuestionOptions = document.querySelectorAll('.next-question-option');
                        nextQuestionOptions.forEach(option => {
                            if (option.value === data.question_id) {
                                option.disabled = true;
                            } else {
                                option.disabled = false;
                            }
                        });
                        
                        // Update modal title and button
                        document.getElementById('optionModalTitle').textContent = 'Edit Option';
                        document.getElementById('saveOptionBtn').textContent = 'Update Option';
                        
                        // Show modal
                        optionModal.style.display = 'block';
                    } else {
                        alert('Error fetching option data');
                    }
                })
                .catch(error => {
                    console.error('Error fetching option data:', error);
                    alert('An error occurred while fetching option data');
                });
        });
    });
    
    // ---------------
    // View question details
    // ---------------
    const viewQuestionButtons = document.querySelectorAll('.view-question');
    viewQuestionButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const questionId = this.getAttribute('data-id');
            const questionRow = this.closest('tr');
            
            // Check if details are already shown
            const existingDetails = document.getElementById(`question-details-${questionId}`);
            if (existingDetails) {
                existingDetails.remove();
                return;
            }
            
            // Remove any other open details
            document.querySelectorAll('.question-details-row').forEach(row => row.remove());
            
            // Show loading indicator
            const detailsRow = document.createElement('tr');
            detailsRow.className = 'question-details-row';
            detailsRow.id = `question-details-${questionId}`;
            const detailsCell = document.createElement('td');
            detailsCell.colSpan = 6; // Adjust based on your table columns
            detailsCell.innerHTML = '<div class="loader">Loading details...</div>';
            detailsRow.appendChild(detailsCell);
            questionRow.parentNode.insertBefore(detailsRow, questionRow.nextSibling);
            
            try {
                // Fetch both question and options data
                const [questionResponse, optionsResponse] = await Promise.all([
                    fetch(`ajax/get_question.php?id=${questionId}`).then(async response => {
                        if (!response.ok) {
                            const text = await response.text();
                            console.error('Question Response:', text);
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    }),
                    fetch(`ajax/get_options.php?question_id=${questionId}`).then(async response => {
                        if (!response.ok) {
                            const text = await response.text();
                            console.error('Options Response:', text);
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                ]);
                
                const questionData = questionResponse;
                const optionsData = optionsResponse;
                
                if (questionData.success) {
                    // Create the expanded details content
                    let detailsHtml = `
                        <div class="expanded-details">
                            <div class="details-grid">
                                <div class="details-section">
                                    <h4>Question Details</h4>
                                    <div class="details-content">
                                        <p><strong>Description:</strong> ${questionData.description || 'No description provided'}</p>
                                        <p><strong>Category:</strong> ${questionData.category_name || 'Uncategorized'}</p>
                                        <p><strong>Created By:</strong> ${questionData.created_by_name || 'Unknown'}</p>
                                        <p><strong>Created On:</strong> ${formatDate(questionData.created_at)}</p>
                                    </div>
                                </div>
                                
                                <div class="options-section">
                                    <div class="options-header">
                                        <h4>Options</h4>
                                        <button class="btn primary-btn add-option" data-id="${questionId}">
                                            <i class="fas fa-plus"></i> Add Option
                                        </button>
                                    </div>
                                    
                                    <div class="options-table-container">
                                        <table class="options-table">
                                            <thead>
                                                <tr>
                                                    <th>Option Text</th>
                                                    <th>Next Question</th>
                                                    <th>Is Endpoint</th>
                                                    <th>Eligible</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                
                    if (optionsData && optionsData.length > 0) {
                        optionsData.forEach(option => {
                            detailsHtml += `
                                <tr>
                                    <td>${option.option_text}</td>
                                    <td>${option.is_endpoint ? 
                                        '<span class="status-badge pending">Endpoint</span>' : 
                                        (option.next_question_text || 'N/A')}</td>
                                    <td>
                                        ${option.is_endpoint ? 
                                            '<span class="status-badge completed">Yes</span>' : 
                                            '<span class="status-badge cancelled">No</span>'}
                                    </td>
                                    <td>
                                        ${option.is_endpoint ? 
                                            (option.endpoint_eligible ? 
                                                '<span class="status-badge completed">Yes</span>' : 
                                                '<span class="status-badge cancelled">No</span>') : 
                                            'N/A'}
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn-action btn-edit edit-option" title="Edit" data-id="${option.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-delete delete-option" title="Delete" data-id="${option.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                        });
                    } else {
                        detailsHtml += `
                            <tr>
                                <td colspan="5" class="text-center">No options found for this question.</td>
                            </tr>`;
                    }
                    
                    detailsHtml += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    
                    detailsCell.innerHTML = detailsHtml;
                    
                    // Add event listeners for the new buttons
                    detailsCell.querySelectorAll('.edit-option').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const optionId = this.getAttribute('data-id');
                            // Your existing edit option logic
                            optionModal.style.display = 'block';
                        });
                    });
                    
                    detailsCell.querySelectorAll('.delete-option').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const optionId = this.getAttribute('data-id');
                            // Your existing delete option logic
                        });
                    });
                    
                    detailsCell.querySelectorAll('.add-option').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const qId = this.getAttribute('data-id');
                            // Your existing add option logic
                            optionModal.style.display = 'block';
                        });
                    });
                    
                } else {
                    detailsCell.innerHTML = `<div class="alert alert-danger">Error: ${questionData.message || 'Unknown error occurred'}</div>`;
                }
                
            } catch (error) {
                console.error('Error fetching details:', error);
                detailsCell.innerHTML = `
                    <div class="alert alert-danger">
                        <p>An error occurred while fetching details.</p>
                        <p><small>${error.message}</small></p>
                    </div>`;
            }
        });
    });

    // Add this after the existing event listeners in your script
    document.getElementById('viewQuestionOptions').addEventListener('click', function() {
        const questionId = this.getAttribute('data-id');
        if (questionId) {
            // Close the view modal
            document.getElementById('viewQuestionModal').style.display = 'none';
            
            // Redirect to the manage questions page with the edit parameter
            window.location.href = `manage_questions.php?edit=${questionId}`;
        }
    });
});

// Add this helper function at the top of your script
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleString();
    } catch (e) {
        console.error('Error formatting date:', e);
        return dateString || 'N/A';
    }
}
</script>