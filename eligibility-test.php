<?php
$page_title = "Eligibility Checker | Visafy Immigration Consultancy";
include('includes/header.php');
?>

<div class="main-container">
    <div class="content-wrapper">
        <div class="content-inner">
            <div class="eligibility-card">
                <div class="card-header">
                    <h3>Immigration Eligibility Checker</h3>
                </div>
                <div class="card-content">
                    <!-- Primary Category Selection -->
                    <div id="primary-category" class="eligibility-section">
                        <h4>Step 1: Select your primary immigration category</h4>
                        <div class="form-input">
                            <select id="primaryCategory" class="select-input">
                                <option value="">-- Select Category --</option>
                                <option value="study">Study</option>
                                <option value="work">Work</option>
                                <option value="permanent">Permanent Residence</option>
                                <option value="invest">Invest/Business</option>
                                <option value="visitor">Visitor</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Question Container -->
                    <div id="question-container" class="eligibility-section mt-4" style="display: none;">
                        <div id="question-header" class="mb-3"></div>
                        <div id="question-content"></div>
                        <div id="navigation-buttons" class="mt-4">
                            <button id="prev-button" class="btn-secondary" style="display: none;">Previous</button>
                            <button id="next-button" class="btn-primary" style="display: none;">Next</button>
                        </div>
                    </div>

                    <!-- Results Container -->
                    <div id="result-container" class="eligibility-section mt-4" style="display: none;">
                        <div class="alert" id="result-alert">
                            <h4 id="result-title"></h4>
                            <p id="result-message"></p>
                            <div id="result-links"></div>
                        </div>
                        <button id="restart-button" class="btn-primary mt-3">Start New Assessment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SDS Country List Modal -->
<div class="modal" id="sdsCountriesModal">
    <div class="modal-wrapper">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">SDS Eligible Countries</h5>
                <button type="button" class="close-btn" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>The Student Direct Stream (SDS) is available to legal residents of:</p>
                <ul class="country-list">
                    <li>Antigua and Barbuda</li>
                    <li>Brazil</li>
                    <li>China</li>
                    <li>Colombia</li>
                    <li>Costa Rica</li>
                    <li>India</li>
                    <li>Morocco</li>
                    <li>Pakistan</li>
                    <li>Peru</li>
                    <li>Philippines</li>
                    <li>Senegal</li>
                    <li>Saint Vincent and the Grenadines</li>
                    <li>Trinidad and Tobago</li>
                    <li>Vietnam</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.main-container {
    margin: 3rem auto;
    padding: 0 1rem;
}

.content-wrapper {
    max-width: 800px;
    margin: 0 auto;
}

.content-inner {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.eligibility-card {
    width: 100%;
}

.card-header {
    background-color: var(--color-primary);
    color: var(--color-light);
    padding: 1rem;
    border-radius: 8px 8px 0 0;
}

.card-header h3 {
    margin: 0;
}

.card-content {
    padding: 1.5rem;
}

.eligibility-section {
    margin-bottom: 1.5rem;
}

.form-input {
    margin-top: 1rem;
}

.select-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn-primary {
    background-color: var(--color-secondary);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: var(--color-primary);
}

.btn-secondary {
    background-color: var(--color-gray);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary:hover {
    background-color: var(--color-dark);
}

#navigation-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

.answer-option {
    margin: 0.5rem 0;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.answer-option:hover {
    background-color: #f8f9fa;
}

.answer-option.selected {
    background-color: var(--color-gold);
    border-color: var(--color-secondary);
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-eligible {
    background-color: #d4edda;
    border-color: var(--color-secondary);
    color: #155724;
}

.alert-not-eligible {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-wrapper {
    max-width: 500px;
    margin: 3rem auto;
}

.modal-content {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.modal-header {
    background-color: var(--color-primary);
    color: var(--color-light);
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    color: var(--color-light);
    font-size: 1.5rem;
    cursor: pointer;
}

.modal-body {
    padding: 1rem;
}

.modal-footer {
    padding: 1rem;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
}

.country-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.country-list li {
    padding: 0.25rem 0;
}

@media (max-width: 768px) {
    .main-container {
        margin: 1rem auto;
    }
    
    .modal-wrapper {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
}
</style>

<!-- Ensure jQuery and Bootstrap are loaded before eligibility.js -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

<!-- Add initialization script to ensure modal is hidden -->
<script>
    $(document).ready(function() {
        // Force hide any modal that might be visible
        $('.modal').modal('hide');
        // Remove any lingering backdrop
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    });
</script>

<!-- Include custom eligibility JavaScript -->
<script src="assets/js/eligibility.js"></script>

<?php
// Include footer
include_once("includes/footer.php");
?>