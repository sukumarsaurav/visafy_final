<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Help & Support";
$page_specific_css = "assets/css/help.css";
require_once 'includes/header.php';
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Help & Support</h1>
            <p>Find answers to common questions</p>
        </div>
        <div>
            <a href="contact_support.php" class="btn primary-btn">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
    </div>
    
    <div class="help-container">
        <!-- Search Section -->
        <div class="search-section">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="help-search" placeholder="Search for help topics...">
            </div>
        </div>
        
        <!-- FAQ Sections -->
        <div class="faq-container">
            <!-- Applications Section -->
            <div id="applications" class="faq-section">
                <h2>Visa Applications</h2>
                
                <div class="accordion">
                    <div class="accordion-item">
                        <div class="accordion-header">
                            How do I start a new visa application?
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <p>To start a new visa application:</p>
                            <ol>
                                <li>Go to the "Applications" page from your dashboard</li>
                                <li>Click on the "New Application" button</li>
                                <li>Select the visa type you wish to apply for</li>
                                <li>Fill out the required information</li>
                                <li>Submit your application</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <div class="accordion-header">
                            How long does the visa application process take?
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <p>Processing times vary based on the type of visa and country. Generally:</p>
                            <ul>
                                <li>Tourist visas: 1-4 weeks</li>
                                <li>Student visas: 2-8 weeks</li>
                                <li>Work visas: 4-12 weeks</li>
                                <li>Immigration visas: 3-6 months</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents Section -->
            <div id="documents" class="faq-section">
                <h2>Documents</h2>
                
                <div class="accordion">
                    <div class="accordion-item">
                        <div class="accordion-header">
                            What file formats are accepted for document uploads?
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <p>We accept the following file formats for document uploads:</p>
                            <ul>
                                <li>PDF (.pdf)</li>
                                <li>JPEG/JPG images (.jpg, .jpeg)</li>
                                <li>PNG images (.png)</li>
                            </ul>
                            <p>Maximum file size: 10MB per document.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Appointments Section -->
            <div id="appointments" class="faq-section">
                <h2>Appointments</h2>
                
                <div class="accordion">
                    <div class="accordion-item">
                        <div class="accordion-header">
                            How do I schedule a consultation?
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <p>To schedule a consultation with a visa advisor:</p>
                            <ol>
                                <li>Go to the "Bookings" page from your dashboard</li>
                                <li>Click on the "Book Appointment" button</li>
                                <li>Select the type of consultation you need</li>
                                <li>Choose an available date and time slot</li>
                                <li>Confirm your booking</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Section -->
            <div id="account" class="faq-section">
                <h2>Account</h2>
                
                <div class="accordion">
                    <div class="accordion-item">
                        <div class="accordion-header">
                            How do I change my password?
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <p>To change your password:</p>
                            <ol>
                                <li>Go to the "Profile" page</li>
                                <li>Select the "Security" tab</li>
                                <li>Enter your current password</li>
                                <li>Enter and confirm your new password</li>
                                <li>Click "Update Password"</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Support -->
        <div class="support-section">
            <div class="support-content">
                <h2>Still Need Help?</h2>
                <p>Our support team is available to assist you.</p>
                <div class="support-options">
                    <a href="mailto:support@visafy.com" class="support-option">
                        <i class="fas fa-envelope"></i>
                        <h3>Email Support</h3>
                        <p>support@visafy.com</p>
                    </a>
                    <a href="tel:+18001234567" class="support-option">
                        <i class="fas fa-phone-alt"></i>
                        <h3>Phone Support</h3>
                        <p>+1 (800) 123-4567</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

.btn {
    padding: 10px 15px;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

.help-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.search-section {
    background-color: var(--primary-color);
    padding: 30px 20px;
    border-radius: 5px;
    text-align: center;
}

.search-wrapper {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary-color);
}

.search-wrapper input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: none;
    border-radius: 30px;
    font-size: 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.search-wrapper input:focus {
    outline: none;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
}

.faq-section {
    background-color: white;
    border-radius: 5px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    scroll-margin-top: 20px;
}

.faq-section h2 {
    margin: 0 0 20px;
    color: var(--primary-color);
    font-size: 1.5rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.accordion {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.accordion-item {
    border: 1px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
}

.accordion-header {
    padding: 15px 20px;
    background-color: #f8f9fc;
    font-weight: 500;
    color: var(--dark-color);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.accordion-header i {
    transition: transform 0.3s;
}

.accordion-header.active i {
    transform: rotate(180deg);
}

.accordion-content {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out, padding 0.3s;
}

.accordion-header.active + .accordion-content {
    padding: 15px 20px;
    max-height: 500px;
}

.accordion-content p, .accordion-content ul, .accordion-content ol {
    margin: 0 0 15px;
    color: var(--secondary-color);
    line-height: 1.6;
}

.accordion-content ul, .accordion-content ol {
    padding-left: 20px;
}

.accordion-content li {
    margin-bottom: 5px;
}

.support-section {
    background-color: #f8f9fc;
    border-radius: 5px;
    padding: 30px 20px;
    text-align: center;
}

.support-content {
    max-width: 800px;
    margin: 0 auto;
}

.support-content h2 {
    margin: 0 0 10px;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.support-content p {
    margin: 0 0 20px;
    color: var(--secondary-color);
}

.support-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.support-option {
    background-color: white;
    border-radius: 5px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: inherit;
    transition: transform 0.3s, box-shadow 0.3s;
}

.support-option:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.support-option i {
    font-size: 24px;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.support-option h3 {
    margin: 0 0 5px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.support-option p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .support-options {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('help-search');
    const accordionItems = document.querySelectorAll('.accordion-item');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        accordionItems.forEach(item => {
            const header = item.querySelector('.accordion-header').textContent.toLowerCase();
            const content = item.querySelector('.accordion-content').textContent.toLowerCase();
            
            if (header.includes(searchTerm) || content.includes(searchTerm)) {
                item.style.display = 'block';
                // Expand the item
                item.querySelector('.accordion-header').classList.add('active');
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Accordion functionality
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?> 