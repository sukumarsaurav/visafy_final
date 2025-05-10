<?php
$page_title = "Our Services | Visafy Immigration Consultancy";
include('includes/header.php');
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-content">
                <h1 class="hero-title">Immigration Services<br>Tailored for You</h1>
                <p class="hero-subtitle">Choose from our comprehensive range of immigration services designed to meet your unique needs</p>
                <div class="hero-buttons">
                    <a href="assessment-tools.php" class="btn btn-primary">Check Eligibility</a>
                    <a href="contact.php" class="btn btn-secondary">Get Consultation</a>
                </div>
            </div>
            <div class="hero-image-container">
                <div class="floating-image-hero">
                    <img src="assets/images/services-hero.png" alt="Immigration Services">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Services Section -->
<section class="section services">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Our Core Services</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Comprehensive immigration solutions for every stage of your journey</p>
        
        <div class="services-grid">
            <!-- Immigration Consultation -->
            <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                <div class="service-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Immigration Consultation</h3>
                <p>One-on-one sessions with licensed immigration consultants to evaluate your options and create a personalized immigration strategy.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Eligibility Assessment</li>
                    <li><i class="fas fa-check"></i> Program Selection</li>
                    <li><i class="fas fa-check"></i> Documentation Review</li>
                </ul>
                <a href="consultation.php" class="btn btn-primary">Book Consultation</a>
            </div>

            <!-- Visa Application -->
            <div class="service-card" data-aos="fade-up" data-aos-delay="300">
                <div class="service-icon">
                    <i class="fas fa-passport"></i>
                </div>
                <h3>Visa Application</h3>
                <p>Complete assistance with visa applications, including document preparation, submission, and follow-up with immigration authorities.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Application Preparation</li>
                    <li><i class="fas fa-check"></i> Document Review</li>
                    <li><i class="fas fa-check"></i> Submission Support</li>
                </ul>
                <a href="visa-services.php" class="btn btn-primary">Learn More</a>
            </div>

            <!-- Express Entry -->
            <div class="service-card" data-aos="fade-up" data-aos-delay="400">
                <div class="service-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h3>Express Entry</h3>
                <p>Specialized assistance for Canada's Express Entry system, including profile creation and invitation to apply support.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Profile Creation</li>
                    <li><i class="fas fa-check"></i> CRS Score Optimization</li>
                    <li><i class="fas fa-check"></i> ITA Support</li>
                </ul>
                <a href="express-entry.php" class="btn btn-primary">Explore Program</a>
            </div>

            <!-- Study Permits -->
            <div class="service-card" data-aos="fade-up" data-aos-delay="500">
                <div class="service-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Study Permits</h3>
                <p>Comprehensive support for international students seeking to study in Canada, from school selection to permit application.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> School Selection</li>
                    <li><i class="fas fa-check"></i> Application Support</li>
                    <li><i class="fas fa-check"></i> SDS Program</li>
                </ul>
                <a href="study-permits.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </div>
</section>

<!-- Service Process Section -->
<section class="section process">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Our Service Process</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">A streamlined approach to your immigration journey</p>

        <div class="process-grid">
            <div class="process-step" data-aos="fade-up" data-aos-delay="200">
                <div class="step-number">1</div>
                <h3>Initial Assessment</h3>
                <p>Complete our online assessment to determine your eligibility for various immigration programs.</p>
            </div>

            <div class="process-step" data-aos="fade-up" data-aos-delay="300">
                <div class="step-number">2</div>
                <h3>Consultation</h3>
                <p>Meet with our licensed consultants to discuss your options and create a personalized plan.</p>
            </div>

            <div class="process-step" data-aos="fade-up" data-aos-delay="400">
                <div class="step-number">3</div>
                <h3>Documentation</h3>
                <p>Gather and prepare all necessary documents with our expert guidance and support.</p>
            </div>

            <div class="process-step" data-aos="fade-up" data-aos-delay="500">
                <div class="step-number">4</div>
                <h3>Application</h3>
                <p>Submit your application with our assistance and track its progress through our platform.</p>
            </div>
        </div>
    </div>
</section>

<style>
/* General Styles */
:root {
    --primary-color: #eaaa34;
    --primary-light: rgba(234, 170, 52, 0.1);
    --primary-medium: rgba(234, 170, 52, 0.2);
    --dark-blue: #042167;
    --text-color: #333;
    --text-light: #666;
    --background-light: #f8f9fa;
    --white: #fff;
    --border-color: #e5e7eb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --border-radius: 0.5rem;
    --border-radius-lg: 1rem;
    --transition: all 0.3s ease;
}

/* Hero Section */
.hero {
    padding: 6rem 0;
    background-color: var(--white);
    color: var(--text-color);
    overflow: hidden;
    position: relative;
}

.hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 3rem;
}

.hero-content {
    text-align: left;
}

.hero-title {
    font-size: 3.5rem;
    color: var(--dark-blue);
    font-weight: 700;
    margin-bottom: 1.25rem;
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.25rem;
    margin-bottom: 2rem;
    color: var(--text-light);
}

.hero-buttons {
    display: flex;
    gap: 1rem;
}

.hero-image-container {
    position: relative;
    height: 500px;
}

.floating-image-hero {
    max-width: 100%;
    height: auto;
}

@keyframes float {
    0% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
    100% {
        transform: translateY(0);
    }
}

/* Button Styles */
.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-align: center;
    border-radius: var(--border-radius);
    transition: var(--transition);
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
    border: 2px solid var(--primary-color);
}

.btn-primary:hover {
    background-color: transparent;
    color: var(--primary-color);
}

.btn-secondary {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn-secondary:hover {
    background-color: var(--primary-color);
    color: var(--white);
}

/* Services Section */
.services {
    background-color: var(--background-light);
    padding: 6rem 0;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark-blue);
    text-align: center;
    margin-bottom: 0.5rem;
}

.section-subtitle {
    font-size: 1.1rem;
    color: var(--text-light);
    text-align: center;
    margin-bottom: 3rem;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.service-card {
    background: var(--white);
    padding: 2.5rem 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.service-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-lg);
}

.service-icon {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 1.25rem;
    height: 4rem;
    width: 4rem;
    background-color: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.service-card h3 {
    color: var(--dark-blue);
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
}

.service-card p {
    color: var(--text-light);
    margin-bottom: 1.5rem;
}

.service-features {
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem;
    text-align: left;
}

.service-features li {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
    gap: 0.5rem;
    color: var(--text-color);
}

.service-features i {
    color: var(--primary-color);
    font-size: 0.875rem;
}

/* Process Section */
.process {
    background-color: var(--white);
    padding: 6rem 0;
}

.process-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.process-step {
    background-color: var(--white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    text-align: center;
    transition: transform 0.3s ease;
}

.process-step:hover {
    transform: translateY(-10px);
}

.step-number {
    width: 3rem;
    height: 3rem;
    background-color: var(--primary-color);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: bold;
    margin: 0 auto 1.5rem;
}

.process-step h3 {
    color: var(--dark-blue);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.process-step p {
    color: var(--text-light);
    margin: 0;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .hero-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .hero-buttons {
        justify-content: center;
    }
    
    .section-title {
        font-size: 2.2rem;
    }
}

@media (max-width: 768px) {
    .section, 
    .hero, 
    .process {
        padding: 4rem 0;
    }

    .hero-title {
        font-size: 2.5rem;
    }

    .hero-subtitle {
        font-size: 1.1rem;
    }

    .hero-image-container {
        height: 300px;
    }
    
    .services-grid,
    .process-grid {
        gap: 1.5rem;
    }

    .section-title {
        font-size: 2rem;
    }

    .section-subtitle {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 2rem;
    }

    .hero-subtitle {
        font-size: 1rem;
    }

    .hero-buttons {
        flex-direction: column;
        gap: 1rem;
    }

    .hero-buttons .btn {
        width: 100%;
    }

    .service-card,
    .process-step {
        padding: 1.5rem 1rem;
    }
    
    .service-features li {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    }
});
</script>

<?php include('includes/footer.php'); ?>
