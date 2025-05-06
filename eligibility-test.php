<?php
$page_title = "Visa Eligibility Test | Visafy";
include('includes/header.php');

// Include database connection
require_once 'config/db_connect.php';

// Get the first question to start the test
try {
    $stmt = $conn->prepare("
        SELECT q.*, c.name as category_name 
        FROM decision_tree_questions q 
        LEFT JOIN decision_tree_categories c ON q.category_id = c.id 
        WHERE q.is_active = 1 
        ORDER BY q.id ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $first_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error loading initial question: " . $e->getMessage();
}
?>

<!-- Hero Section -->
<section class="hero" style="background-color: #f8f9fc;">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">
                 Free Visa Eligibility Check
            </h1>
            <p class="hero-subtitle">Answer a few simple questions to check your visa eligibility and get instant results</p>
        </div>
    </div>
</section>

<!-- Eligibility Test Section -->
<section class="section eligibility-test">
    <div class="container">
        <div class="test-container">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Eligibility Assessment</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif ($first_question): ?>
                        <div id="question-section">
                            <div class="question-container">
                                <h3 id="question-text"><?php echo htmlspecialchars($first_question['question_text']); ?></h3>
                                <?php if (isset($first_question['description']) && $first_question['description']): ?>
                                    <p class="question-description"><?php echo htmlspecialchars($first_question['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div id="options-container" class="options-container">
                                <div class="loader">
                                    <i class="fas fa-circle-notch fa-spin"></i> Loading options...
                                </div>
                            </div>
                        </div>

                        <div id="result-section" class="result-section" style="display: none;">
                            <div class="result-container">
                                <div id="result-icon"></div>
                                <h3 id="result-title"></h3>
                                <p id="result-message"></p>
                                <div class="cta-container">
                                    <p class="cta-text">Want to proceed with your visa application?</p>
                                    <div class="cta-buttons">
                                        <a href="register.php" class="btn btn-primary">
                                            <i class="fas fa-user-plus"></i> Create Free Account
                                        </a>
                                        <a href="consultation.php" class="btn btn-secondary">
                                            <i class="fas fa-comments"></i> Book Consultation
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="result-actions">
                                <button id="restart-test" class="btn btn-outline">
                                    <i class="fas fa-redo"></i> Take Test Again
                                </button>
                                <a href="services.php" class="btn btn-link">
                                    <i class="fas fa-arrow-right"></i> Explore Our Services
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Our eligibility test is currently being updated. Please check back later or contact us directly.</p>
                            <div class="empty-state-actions">
                                <a href="contact.php" class="btn btn-primary">Contact Us</a>
                                <a href="index.php" class="btn btn-secondary">Back to Home</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Service Cards Styling */
.hero {
    padding: 80px 0;
    background-color:rgb(255, 255, 255);
    color: var(--color-light);
    overflow: hidden;
    position: relative;
}

.hero-grid {
    display: grid;
    grid-template-columns: 4fr 3fr;
    align-items: center;
    gap: 50px;
}

.hero-content {
    text-align: left;
    max-width: 700px;
}

.hero-title {
    font-size: 3.5rem;
    color: #042167;
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.2;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.hero-subtitle {
    font-size: 1.2rem;
    margin-bottom: 30px;
    line-height: 1.6;
    opacity: 0.9;
    color: #042167;
}

.hero-buttons {
    display: flex;
    gap: 20px;
}

.hero-image-container {
    position: relative;
    height: 500px;
}

.floating-image {
    position: relative;
    animation: float 6s ease-in-out infinite;
}

.floating-image img {
    max-width: 100%;
    height: auto;
}
/* Test Section Styles */
.eligibility-test {
    padding: 40px 0 80px;
}

.test-container {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.card-header {
    padding: 20px 30px;
    border-bottom: 1px solid #e3e6f0;
    background-color: #fff;
    border-radius: 10px 10px 0 0;
}

.card-header h5 {
    margin: 0;
    color: #042167;
    font-size: 1.2rem;
    font-weight: 600;
}

.card-body {
    padding: 30px;
}

.question-container {
    text-align: center;
    margin-bottom: 30px;
}

.question-container h3 {
    color: #042167;
    font-size: 1.4rem;
    margin-bottom: 15px;
}

.question-description {
    color: #666;
    margin-bottom: 20px;
}

.options-container {
    display: grid;
    gap: 15px;
}

.option-button {
    padding: 15px 20px;
    background: #fff;
    border: 2px solid #e3e6f0;
    border-radius: 8px;
    text-align: left;
    font-size: 1rem;
    color: #042167;
    cursor: pointer;
    transition: all 0.3s ease;
}

.option-button:hover {
    border-color: #eaaa34;
    background-color: rgba(234, 170, 52, 0.05);
}

.result-section {
    text-align: center;
}

.result-container {
    margin-bottom: 30px;
}

.result-container i {
    font-size: 48px;
    margin-bottom: 20px;
}

.result-container.eligible i {
    color: #1cc88a;
}

.result-container.not-eligible i {
    color: #e74a3b;
}

.cta-container {
    margin-top: 30px;
    padding: 20px;
    background-color: #f8f9fc;
    border-radius: 8px;
}

.cta-text {
    font-size: 1.1rem;
    color: #042167;
    margin-bottom: 20px;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.btn {
    padding: 12px 25px;
    border-radius: 5px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background-color: #eaaa34;
    color: #fff;
    border: none;
}

.btn-primary:hover {
    background-color: #d99b2b;
}

.btn-secondary {
    background-color: #042167;
    color: #fff;
    border: none;
}

.btn-secondary:hover {
    background-color: #031c56;
}

.btn-outline {
    background-color: transparent;
    border: 2px solid #042167;
    color: #042167;
}

.btn-outline:hover {
    background-color: #042167;
    color: #fff;
}

.btn-link {
    background: none;
    border: none;
    color: #042167;
    text-decoration: none;
}

.btn-link:hover {
    text-decoration: underline;
}

.loader {
    text-align: center;
    padding: 20px;
    color: #666;
}

.loader i {
    margin-right: 8px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 48px;
    color: #666;
    margin-bottom: 20px;
}

.empty-state-actions {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .cta-buttons {
        flex-direction: column;
    }
    
    .empty-state-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionSection = document.getElementById('question-section');
    const resultSection = document.getElementById('result-section');
    const questionText = document.getElementById('question-text');
    const optionsContainer = document.getElementById('options-container');
    const resultIcon = document.getElementById('result-icon');
    const resultTitle = document.getElementById('result-title');
    const resultMessage = document.getElementById('result-message');
    const restartButton = document.getElementById('restart-test');
    
    let currentQuestionId = <?php echo $first_question ? $first_question['id'] : 'null'; ?>;
    let assessmentId = null;
    
    // Debug logging
    console.log('Initial question ID:', currentQuestionId);
    
    // Function to load question options
    async function loadOptions(questionId) {
        try {
            console.log('Loading options for question:', questionId);
            optionsContainer.innerHTML = '<div class="loader"><i class="fas fa-circle-notch fa-spin"></i> Loading options...</div>';
            
            const response = await fetch(`ajax/get_options.php?question_id=${questionId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const options = await response.json();
            console.log('Loaded options:', options);
            
            if (Array.isArray(options) && options.length > 0) {
                optionsContainer.innerHTML = options.map(option => `
                    <button class="option-button" data-id="${option.id}" 
                            data-is-endpoint="${option.is_endpoint}" 
                            data-next-question="${option.next_question_id || ''}" 
                            data-endpoint-eligible="${option.endpoint_eligible || false}"
                            data-endpoint-result="${option.endpoint_result || ''}">
                        ${option.option_text}
                    </button>
                `).join('');
                
                // Add click handlers to options
                document.querySelectorAll('.option-button').forEach(button => {
                    button.addEventListener('click', handleOptionClick);
                });
            } else {
                throw new Error('No options returned from server');
            }
            
        } catch (error) {
            console.error('Error loading options:', error);
            optionsContainer.innerHTML = `
                <div class="alert alert-danger">
                    Error loading options. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to handle option selection
    async function handleOptionClick(event) {
        try {
            const button = event.currentTarget;
            const optionId = button.dataset.id;
            const isEndpoint = button.dataset.isEndpoint === 'true';
            const nextQuestionId = button.dataset.nextQuestion;
            const endpointEligible = button.dataset.endpointEligible === 'true';
            const endpointResult = button.dataset.endpointResult;
            
            console.log('Option clicked:', {
                optionId,
                isEndpoint,
                nextQuestionId,
                endpointEligible,
                endpointResult
            });
            
            // Start assessment if not already started
            if (!assessmentId) {
                const response = await fetch('ajax/start_assessment.php', {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    assessmentId = data.assessment_id;
                    console.log('Assessment started:', assessmentId);
                }
            }
            
            // Save the answer
            const saveResponse = await fetch('ajax/save_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    assessment_id: assessmentId,
                    question_id: currentQuestionId,
                    option_id: optionId
                })
            });
            
            const saveResult = await saveResponse.json();
            console.log('Save answer result:', saveResult);
            
            if (isEndpoint) {
                showResult(endpointEligible, endpointResult);
            } else if (nextQuestionId) {
                await loadQuestion(nextQuestionId);
            }
            
        } catch (error) {
            console.error('Error processing answer:', error);
            optionsContainer.innerHTML = `
                <div class="alert alert-danger">
                    Error processing your answer. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to load question
    async function loadQuestion(questionId) {
        try {
            console.log('Loading question:', questionId);
            const response = await fetch(`ajax/get_question.php?id=${questionId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const question = await response.json();
            console.log('Loaded question:', question);
            
            if (question.success) {
                currentQuestionId = question.id;
                questionText.textContent = question.question_text;
                
                const descriptionElement = document.querySelector('.question-description');
                if (descriptionElement) {
                    if (question.description) {
                        descriptionElement.textContent = question.description;
                        descriptionElement.style.display = 'block';
                    } else {
                        descriptionElement.style.display = 'none';
                    }
                }
                
                await loadOptions(questionId);
            } else {
                throw new Error(question.message || 'Failed to load question');
            }
            
        } catch (error) {
            console.error('Error loading question:', error);
            questionSection.innerHTML = `
                <div class="alert alert-danger">
                    Error loading question. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to show result
    function showResult(isEligible, message) {
        questionSection.style.display = 'none';
        resultSection.style.display = 'block';
        
        resultIcon.innerHTML = isEligible ? 
            '<i class="fas fa-check-circle"></i>' : 
            '<i class="fas fa-times-circle"></i>';
        
        resultTitle.textContent = isEligible ? 
            'You may be eligible!' : 
            'You may not be eligible';
        
        resultMessage.textContent = message;
        
        resultSection.querySelector('.result-container').className = 
            'result-container ' + (isEligible ? 'eligible' : 'not-eligible');
    }
    
    // Handle restart button
    restartButton.addEventListener('click', function() {
        location.reload();
    });
    
    // Load initial options if we have a question
    if (currentQuestionId) {
        console.log('Loading initial options...');
        loadOptions(currentQuestionId);
    }
});
</script>

<?php include('includes/footer.php'); ?>
