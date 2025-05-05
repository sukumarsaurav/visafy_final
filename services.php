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
                    <a href="assessment-tools.php" class="btn-primary">Check Eligibility</a>
                    <a href="contact.php" class="btn-secondary">Get Consultation</a>
                </div>
            </div>
            <div class="hero-image-container">
                <div class="floating-image">
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
                <a href="consultation.php" class="btn-primary">Book Consultation</a>
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
                <a href="visa-services.php" class="btn-primary">Learn More</a>
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
                <a href="express-entry.php" class="btn-primary">Explore Program</a>
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
                <a href="study-permits.php" class="btn-primary">Get Started</a>
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
/* Service Cards Styling */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.service-card {
    background: #fff;
    padding: 40px 30px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    text-align: center;
    transition: transform 0.3s ease;
}

.service-card:hover {
    transform: translateY(-10px);
}

.service-icon {
    font-size: 3rem;
    color: #eaaa34;
    margin-bottom: 20px;
}

.service-card h3 {
    color: #042167;
    margin-bottom: 15px;
    font-size: 1.5rem;
}

.service-card p {
    color: #666;
    margin-bottom: 25px;
    line-height: 1.6;
}

.service-features {
    list-style: none;
    padding: 0;
    margin: 0 0 25px;
    text-align: left;
}

.service-features li {
    margin-bottom: 10px;
    color: #042167;
    display: flex;
    align-items: center;
    gap: 10px;
}

.service-features i {
    color: #eaaa34;
    font-size: 0.9rem;
}

/* Process Section Styling */
.process {
    background-color: #f8f9fa;
    padding: 80px 0;
}

.process-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.process-step {
    text-align: center;
    padding: 30px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    position: relative;
}

.step-number {
    width: 40px;
    height: 40px;
    background: #eaaa34;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
    margin: 0 auto 20px;
}

.process-step h3 {
    color: #042167;
    margin-bottom: 15px;
    font-size: 1.3rem;
}

.process-step p {
    color: #666;
    margin: 0;
    line-height: 1.6;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .services-grid,
    .process-grid {
        grid-template-columns: 1fr;
    }

    .service-card,
    .process-step {
        padding: 30px 20px;
    }

    .section {
        padding: 60px 0;
    }
}
</style>

<!-- Add the existing scripts from index.php -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true
    });
});
</script>

<?php include('includes/footer.php'); ?>
