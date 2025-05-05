<?php
include_once 'includes/header.php';

// Check if assessment is in progress
$assessment_id = $_SESSION['assessment_id'] ?? null;
$current_question = null;
$options = [];

// Start new assessment if none exists
if (empty($assessment_id)) {
    // Create new assessment
    $user_id = $_SESSION['id'];
    $stmt = $conn->prepare("INSERT INTO user_assessments (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $assessment_id = $stmt->insert_id;
    $_SESSION['assessment_id'] = $assessment_id;
    $stmt->close();
    
    // Get first question (lowest ID)
    $stmt = $conn->prepare("SELECT * FROM decision_tree_questions WHERE is_active = 1 ORDER BY id LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $current_question = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    // Get assessment data
    $stmt = $conn->prepare("SELECT * FROM user_assessments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $assessment_id, $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $assessment = $result->fetch_assoc();
        
        // If assessment is complete, show result
        if ($assessment['is_complete']) {
            $completed = true;
            $result_text = $assessment['result_text'];
            $result_eligible = $assessment['result_eligible'];
        } else {
            // Get last answered question to determine next question
            $stmt = $conn->prepare("SELECT a.*, o.next_question_id, o.is_endpoint, o.endpoint_result, o.endpoint_eligible  
                                  FROM user_assessment_answers a
                                  JOIN decision_tree_options o ON a.option_id = o.id
                                  WHERE a.assessment_id = ?
                                  ORDER BY a.answer_time DESC
                                  LIMIT 1");
            $stmt->bind_param("i", $assessment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $last_answer = $result->fetch_assoc();
                
                if ($last_answer['is_endpoint']) {
                    // This was an endpoint - complete the assessment
                    $stmt = $conn->prepare("UPDATE user_assessments 
                                          SET is_complete = 1, 
                                              end_time = NOW(), 
                                              result_text = ?, 
                                              result_eligible = ?
                                          WHERE id = ?");
                    $stmt->bind_param("sii", $last_answer['endpoint_result'], $last_answer['endpoint_eligible'], $assessment_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $completed = true;
                    $result_text = $last_answer['endpoint_result'];
                    $result_eligible = $last_answer['endpoint_eligible'];
                } else {
                    // Get next question
                    $next_question_id = $last_answer['next_question_id'];
                    
                    $stmt = $conn->prepare("SELECT * FROM decision_tree_questions WHERE id = ? AND is_active = 1");
                    $stmt->bind_param("i", $next_question_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $current_question = $result->fetch_assoc();
                    } else {
                        // Something went wrong - question not found or inactive
                        $error = "The next question could not be found or is inactive. Please start a new assessment.";
                    }
                    $stmt->close();
                }
            } else {
                // No answers yet, get first question
                $stmt = $conn->prepare("SELECT * FROM decision_tree_questions WHERE is_active = 1 ORDER BY id LIMIT 1");
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $current_question = $result->fetch_assoc();
                }
                $stmt->close();
            }
        }
    } else {
        // Invalid assessment ID
        unset($_SESSION['assessment_id']);
        header("Location: eligibility-check.php");
        exit();
    }
}

// Get options for current question
if ($current_question) {
    $stmt = $conn->prepare("SELECT * FROM decision_tree_options WHERE question_id = ? ORDER BY id");
    $stmt->bind_param("i", $current_question['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }
    $stmt->close();
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4 mb-0">Visa Eligibility Checker</h2>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <p class="mt-3">
                            <a href="reset_assessment.php" class="btn btn-outline-danger">Start New Assessment</a>
                        </p>
                    </div>
                    
                    <?php elseif (isset($completed) && $completed): ?>
                    <!-- Assessment Complete - Show Result -->
                    <div class="assessment-result">
                        <h3 class="text-center mb-4">Assessment Complete</h3>
                        
                        <div class="result-box <?php echo $result_eligible ? 'result-eligible' : 'result-ineligible'; ?>">
                            <div class="result-icon">
                                <?php if ($result_eligible): ?>
                                <i class="fas fa-check-circle"></i>
                                <?php else: ?>
                                <i class="fas fa-times-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="result-text">
                                <h4><?php echo $result_eligible ? 'Eligible' : 'Not Eligible'; ?></h4>
                                <p><?php echo nl2br(htmlspecialchars($result_text)); ?></p>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="reset_assessment.php" class="btn btn-primary">Start New Assessment</a>
                            <?php if ($result_eligible): ?>
                            <a href="apply.php" class="btn btn-success ml-2">Apply Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php elseif ($current_question): ?>
                    <!-- Show Current Question -->
                    <form action="process_answer.php" method="post" id="question-form">
                        <input type="hidden" name="assessment_id" value="<?php echo $assessment_id; ?>">
                        <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                        
                        <div class="question-counter">Question <?php echo isset($_SESSION['question_number']) ? $_SESSION['question_number'] : 1; ?></div>
                        
                        <h3 class="question-text"><?php echo htmlspecialchars($current_question['question_text']); ?></h3>
                        
                        <?php if (!empty($current_question['description'])): ?>
                        <div class="question-description">
                            <?php echo nl2br(htmlspecialchars($current_question['description'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="options-list mt-4">
                            <?php foreach ($options as $option): ?>
                            <div class="option-item">
                                <label class="option-label">
                                    <input type="radio" name="option_id" value="<?php echo $option['id']; ?>" required>
                                    <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-navigation mt-4">
                            <button type="submit" class="btn btn-primary">Continue</button>
                            <a href="reset_assessment.php" class="btn btn-outline-secondary ml-2">Start Over</a>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No active questions found. Please contact the administrator.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.question-counter {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 10px;
}
.question-text {
    font-size: 1.4rem;
    font-weight: 600;
    margin-bottom: 15px;
}
.question-description {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-size: 0.95rem;
}
.options-list {
    margin-bottom: 20px;
}
.option-item {
    margin-bottom: 12px;
}
.option-label {
    display: flex;
    align-items: flex-start;
    padding: 12px 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
}
.option-label:hover {
    background-color: #f8f9fa;
}
.option-label input[type="radio"] {
    margin-top: 3px;
    margin-right: 12px;
}
.option-text {
    flex: 1;
}
.result-box {
    display: flex;
    align-items: center;
    padding: 25px;
    border-radius: 8px;
    margin: 20px 0;
}
.result-eligible {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}
.result-ineligible {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}
.result-icon {
    font-size: 3rem;
    margin-right: 20px;
}
.result-eligible .result-icon {
    color: #28a745;
}
.result-ineligible .result-icon {
    color: #dc3545;
}
.result-text h4 {
    margin-bottom: 10px;
    font-weight: 600;
}
</style>

<?php include_once 'includes/footer.php'; ?>
