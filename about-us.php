<?php
$page_title = "About Us | Visafy - Canadian Immigration Consultancy";
include('includes/header.php');
?>

<!-- Hero Section -->
<section class="about-hero" style="padding: 80px 0; background-color: #f8f9fa;">
    <div class="container">
        <div class="about-hero-grid">
            <div class="about-hero-content">
                <h1 class="about-title">Transforming Immigration Services Through Technology</h1>
                <p class="about-subtitle">We're building the future of immigration consulting by connecting applicants with licensed professionals through our innovative digital platform.</p>
            </div>
            <div class="about-hero-image">
                <img src="assets/images/about-hero.jpg" alt="Visafy Team" style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            </div>
        </div>
    </div>
</section>

<!-- Vision & Mission Section -->
<section class="vision-mission" style="padding: 80px 0; background-color: #fff;">
    <div class="container">
        <div class="vision-mission-grid">
            <div class="vision-box">
                <div class="icon-wrapper">
                    <i class="fas fa-eye"></i>
                </div>
                <h2>Our Vision</h2>
                <p>To be the leading digital platform that makes immigration services accessible, transparent, and efficient for everyone seeking to build their future in Canada.</p>
            </div>
            <div class="mission-box">
                <div class="icon-wrapper">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h2>Our Mission</h2>
                <p>To revolutionize the immigration industry by connecting applicants with licensed professionals through technology, while ensuring compliance, transparency, and success in every application.</p>
            </div>
        </div>
    </div>
</section>

<!-- Problem & Solution Section -->
<section class="problem-solution" style="padding: 80px 0; background-color: #f8f9fa;">
    <div class="container">
        <h2 class="section-title">The Problems We Solve</h2>
        <div class="problem-solution-grid">
            <div class="problem-card">
                <div class="card-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Traditional Challenges</h3>
                <ul>
                    <li>Lack of transparency in application process</li>
                    <li>Difficulty in finding legitimate consultants</li>
                    <li>Inefficient document management</li>
                    <li>Poor communication channels</li>
                    <li>Inconsistent service quality</li>
                </ul>
            </div>
            <div class="solution-card">
                <div class="card-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>Our Solutions</h3>
                <ul>
                    <li>Real-time application tracking system</li>
                    <li>Verified ICCRC-licensed consultants</li>
                    <li>Secure digital document management</li>
                    <li>Integrated communication platform</li>
                    <li>Standardized service delivery</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Platform Benefits Section -->
<section class="platform-benefits" style="padding: 80px 0; background-color: #fff;">
    <div class="container">
        <h2 class="section-title">How We Help</h2>
        <div class="benefits-grid">
            <!-- For Applicants -->
            <div class="benefit-group">
                <h3 class="benefit-title">For Applicants</h3>
                <div class="benefits-list">
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="benefit-content">
                            <h4>Transparent Process</h4>
                            <p>Track your application progress in real-time with detailed status updates</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-shield-alt"></i>
                        <div class="benefit-content">
                            <h4>Verified Experts</h4>
                            <p>Connect with licensed immigration consultants with proven track records</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-file-alt"></i>
                        <div class="benefit-content">
                            <h4>Document Security</h4>
                            <p>Store and manage your documents in our secure digital vault</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- For Consultants -->
            <div class="benefit-group">
                <h3 class="benefit-title">For Consultants</h3>
                <div class="benefits-list">
                    <div class="benefit-item">
                        <i class="fas fa-tasks"></i>
                        <div class="benefit-content">
                            <h4>Practice Management</h4>
                            <p>Streamline your practice with our comprehensive management tools</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-users"></i>
                        <div class="benefit-content">
                            <h4>Client Acquisition</h4>
                            <p>Connect with verified applicants seeking professional services</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-chart-line"></i>
                        <div class="benefit-content">
                            <h4>Business Growth</h4>
                            <p>Scale your practice with automated workflows and digital tools</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="our-values" style="padding: 80px 0; background-color: #f8f9fa;">
    <div class="container">
        <h2 class="section-title">Our Core Values</h2>
        <div class="values-grid">
            <div class="value-card">
                <i class="fas fa-lock"></i>
                <h3>Trust & Security</h3>
                <p>We prioritize the security of your information and maintain the highest standards of data protection.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-handshake"></i>
                <h3>Transparency</h3>
                <p>We believe in complete transparency in our processes, pricing, and communication.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-star"></i>
                <h3>Excellence</h3>
                <p>We strive for excellence in every aspect of our service delivery and platform functionality.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-heart"></i>
                <h3>Client Success</h3>
                <p>Your success is our success. We're committed to helping you achieve your immigration goals.</p>
            </div>
        </div>
    </div>
</section>

<style>
/* About Hero Styles */
.about-hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    align-items: center;
}

.about-title {
    font-size: 2.8rem;
    color: #042167;
    margin-bottom: 20px;
    line-height: 1.2;
}

.about-subtitle {
    font-size: 1.2rem;
    color: #666;
    line-height: 1.6;
}

/* Vision & Mission Styles */
.vision-mission-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.vision-box, .mission-box {
    padding: 40px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    text-align: center;
}

.icon-wrapper {
    font-size: 2.5rem;
    color: #eaaa34;
    margin-bottom: 20px;
}

/* Problem & Solution Styles */
.problem-solution-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 40px;
}

.problem-card, .solution-card {
    padding: 40px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.card-icon {
    font-size: 2rem;
    color: #eaaa34;
    margin-bottom: 20px;
}

.section-title {
    font-size: 2.5rem;
    color: #042167;
    text-align: center;
    margin-bottom: 40px;
}

/* Benefits Styles */
.benefits-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
}

.benefit-group {
    background: #fff;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.benefit-title {
    color: #042167;
    font-size: 1.8rem;
    margin-bottom: 30px;
    text-align: center;
}

.benefit-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 30px;
}

.benefit-item i {
    color: #eaaa34;
    font-size: 1.5rem;
    margin-right: 20px;
    margin-top: 5px;
}

.benefit-content h4 {
    color: #042167;
    margin-bottom: 10px;
}

.benefit-content p {
    color: #666;
    line-height: 1.6;
}

/* Values Styles */
.values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.value-card {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    text-align: center;
}

.value-card i {
    font-size: 2rem;
    color: #eaaa34;
    margin-bottom: 20px;
}

.value-card h3 {
    color: #042167;
    margin-bottom: 15px;
}

.value-card p {
    color: #666;
    line-height: 1.6;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .about-hero-grid,
    .vision-mission-grid,
    .problem-solution-grid,
    .benefits-grid {
        grid-template-columns: 1fr;
    }

    .about-title {
        font-size: 2.2rem;
    }

    .section-title {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .about-hero,
    .vision-mission,
    .problem-solution,
    .platform-benefits,
    .our-values {
        padding: 60px 0;
    }

    .value-card {
        padding: 20px;
    }
}
</style>

<?php include('includes/footer.php'); ?>
