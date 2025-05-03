/**
 * Main JavaScript for Canadian Immigration Consultancy Website
 */

document.addEventListener('DOMContentLoaded', function() {
    // AOS is initialized in utils.js
    
    // Mobile Navigation Toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');

    if (mobileMenuToggle && navMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            mobileMenuToggle.classList.toggle('active');
        });
    }

    // Mobile Dropdown Toggle
    const hasDropdownItems = document.querySelectorAll('.nav-item.has-dropdown');
    
    if (hasDropdownItems.length > 0) {
        hasDropdownItems.forEach(item => {
            // Create toggle for mobile
            const link = item.querySelector('a');
            const dropdownToggle = document.createElement('span');
            dropdownToggle.classList.add('dropdown-toggle');
            dropdownToggle.innerHTML = '<i class="fas fa-chevron-down"></i>';
            
            if (link && window.innerWidth <= 768) {
                link.appendChild(dropdownToggle);
                
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.nav-item').querySelector('.dropdown-menu');
                    if (dropdown) {
                        dropdown.classList.toggle('active');
                        this.classList.toggle('active');
                    }
                });
            }
        });
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navMenu && navMenu.classList.contains('active') && 
            !navMenu.contains(event.target) && 
            !mobileMenuToggle.contains(event.target)) {
            navMenu.classList.remove('active');
            mobileMenuToggle.classList.remove('active');
        }
    });

    // Scroll to section when clicking on navigation links
    const navLinks = document.querySelectorAll('.nav-item a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            
            if (targetId && targetId.startsWith('#') && targetId.length > 1) {
                e.preventDefault();
                
                const targetSection = document.querySelector(targetId);
                
                if (targetSection) {
                    window.scrollTo({
                        top: targetSection.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
                
                // Close mobile menu when a link is clicked
                if (navMenu.classList.contains('active')) {
                    navMenu.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                }
            }
        });
    });

    // Sticky Header
    const header = document.querySelector('.header');
    const hero = document.querySelector('.hero');
    
    if (header && hero) {
        const headerHeight = header.offsetHeight;
        const heroBottom = hero.offsetTop + hero.offsetHeight;
        
        window.addEventListener('scroll', function() {
            if (window.scrollY > heroBottom - headerHeight) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // Year for copyright in footer
    const yearElement = document.getElementById('current-year');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }

    // Testimonials/Success Stories Slider
    const storiesSlider = document.querySelector('.stories-slider');
    if (storiesSlider && typeof Swiper !== 'undefined') {
        new Swiper('.stories-slider', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                },
                768: {
                    slidesPerView: 2,
                },
                1024: {
                    slidesPerView: 3,
                },
            }
        });
    }

    // Form Validation
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            const formInputs = contactForm.querySelectorAll('input, textarea');
            
            formInputs.forEach(input => {
                if (input.hasAttribute('required') && !input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                } else if (input.type === 'email' && input.value.trim()) {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(input.value.trim())) {
                        isValid = false;
                        input.classList.add('error');
                    } else {
                        input.classList.remove('error');
                    }
                } else {
                    input.classList.remove('error');
                }
            });
            
            if (isValid) {
                // Form is valid, submit to server or display success message
                // Here we would typically use AJAX to submit the form data
                alert('Thank you for your message! We will get back to you soon.');
                contactForm.reset();
            } else {
                alert('Please fill in all required fields correctly.');
            }
        });
    }

    // Visa Eligibility Calculator
    const eligibilityForm = document.getElementById('eligibility-form');
    if (eligibilityForm) {
        eligibilityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Sample calculation logic - in a real application, this would be more complex
            const age = parseInt(document.getElementById('age').value);
            const education = document.getElementById('education').value;
            const experience = parseInt(document.getElementById('experience').value);
            const language = document.getElementById('language').value;
            
            let points = 0;
            
            // Age points (simplified)
            if (age >= 18 && age <= 35) {
                points += 12;
            } else if (age > 35 && age <= 45) {
                points += 6;
            }
            
            // Education points (simplified)
            if (education === 'bachelors') {
                points += 15;
            } else if (education === 'masters') {
                points += 25;
            } else if (education === 'phd') {
                points += 30;
            }
            
            // Experience points (simplified)
            points += experience * 3;
            
            // Language points (simplified)
            if (language === 'fluent') {
                points += 20;
            } else if (language === 'intermediate') {
                points += 10;
            } else if (language === 'basic') {
                points += 5;
            }
            
            // Display result
            const resultElement = document.getElementById('eligibility-result');
            if (resultElement) {
                resultElement.innerHTML = `<div class="result-score">Your score: <strong>${points}</strong> points</div>`;
                
                if (points >= 67) {
                    resultElement.innerHTML += `<div class="result-message success">Based on this basic assessment, you may be eligible for Express Entry. Please contact us for a detailed evaluation.</div>`;
                } else {
                    resultElement.innerHTML += `<div class="result-message warning">Your score is below the typical Express Entry threshold. Please contact us to discuss alternative immigration pathways that may be suitable for you.</div>`;
                }
                
                resultElement.style.display = 'block';
            }
        });
    }

    // CRS Score Calculator
    const crsForm = document.getElementById('crs-form');
    if (crsForm) {
        crsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simplified CRS calculation logic
            // In a real application, this would be much more comprehensive
            
            const age = parseInt(document.getElementById('crs-age').value);
            const education = document.getElementById('crs-education').value;
            const firstLanguage = document.getElementById('crs-first-language').value;
            const workExperience = parseInt(document.getElementById('crs-work-experience').value);
            const canadianExperience = document.getElementById('crs-canadian-experience').checked;
            
            let score = 0;
            
            // Age points (simplified)
            if (age >= 20 && age <= 29) {
                score += 100;
            } else if (age >= 30 && age <= 39) {
                score += 75;
            } else if (age >= 40 && age <= 44) {
                score += 50;
            }
            
            // Education points (simplified)
            if (education === 'bachelors') {
                score += 120;
            } else if (education === 'masters') {
                score += 135;
            } else if (education === 'phd') {
                score += 150;
            }
            
            // Language points (simplified)
            if (firstLanguage === 'clb9') {
                score += 150;
            } else if (firstLanguage === 'clb8') {
                score += 120;
            } else if (firstLanguage === 'clb7') {
                score += 100;
            }
            
            // Work experience points (simplified)
            score += workExperience * 25;
            
            // Canadian experience bonus
            if (canadianExperience) {
                score += 50;
            }
            
            // Display result
            const resultElement = document.getElementById('crs-result');
            if (resultElement) {
                resultElement.innerHTML = `<div class="result-score">Your CRS Score: <strong>${score}</strong> points</div>`;
                
                if (score >= 470) {
                    resultElement.innerHTML += `<div class="result-message success">Your score is above recent Express Entry draw cutoffs. You have a good chance of receiving an invitation to apply.</div>`;
                } else if (score >= 420) {
                    resultElement.innerHTML += `<div class="result-message warning">Your score is within range of some Express Entry draws. There may be program-specific draws where you could receive an invitation.</div>`;
                } else {
                    resultElement.innerHTML += `<div class="result-message error">Your score is below typical Express Entry draw cutoffs. Consider ways to improve your score or explore alternative immigration pathways.</div>`;
                }
                
                resultElement.style.display = 'block';
            }
        });
    }

    // Study Permit Checker
    const studyPermitForm = document.getElementById('study-permit-form');
    if (studyPermitForm) {
        studyPermitForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Gather form data
            const hasAcceptance = document.getElementById('has-acceptance').checked;
            const financialSupport = document.getElementById('financial-support').checked;
            const country = document.getElementById('study-country').value;
            const duration = document.getElementById('study-duration').value;
            
            // Check eligibility
            let isEligible = true;
            let requirementsMissing = [];
            
            if (!hasAcceptance) {
                isEligible = false;
                requirementsMissing.push("an acceptance letter from a designated learning institution in Canada");
            }
            
            if (!financialSupport) {
                isEligible = false;
                requirementsMissing.push("proof of financial support");
            }
            
            // Display result
            const resultElement = document.getElementById('study-result');
            if (resultElement) {
                if (isEligible) {
                    resultElement.innerHTML = `
                        <div class="result-message success">
                            <h4>You may be eligible for a Study Permit!</h4>
                            <p>Based on your answers, you meet the basic requirements for a Canadian study permit. Here's what you'll need:</p>
                            <ul>
                                <li>Acceptance letter from a designated learning institution</li>
                                <li>Proof of financial support</li>
                                <li>${country === 'visa-required' ? 'A valid visa or electronic travel authorization (eTA)' : 'An electronic travel authorization (eTA)'}</li>
                                <li>Medical examination and police certificate (in some cases)</li>
                            </ul>
                            <p>For a comprehensive assessment, we recommend <a href="contact.php" style="color: var(--color-burgundy); font-weight: 600;">booking a consultation</a> with one of our immigration experts.</p>
                        </div>
                    `;
                } else {
                    resultElement.innerHTML = `
                        <div class="result-message warning">
                            <h4>Additional requirements needed</h4>
                            <p>You're missing some key requirements for a Canadian study permit. You need ${requirementsMissing.join(" and ")}.</p>
                            <p>Don't worry! Our experts can guide you through the process of obtaining these requirements. <a href="contact.php" style="color: var(--color-burgundy); font-weight: 600;">Contact us</a> for personalized assistance.</p>
                        </div>
                    `;
                }
                
                // Additional information based on duration and country
                if (duration === 'less-6' && isEligible) {
                    resultElement.innerHTML += `
                        <div style="margin-top: 15px;">
                            <p><strong>Note:</strong> Since your intended study duration is less than 6 months, you may not require a study permit, but we recommend getting one if you might extend your studies later.</p>
                        </div>
                    `;
                }
                
                if (country === 'visa-required' && isEligible) {
                    resultElement.innerHTML += `
                        <div style="margin-top: 15px;">
                            <p><strong>Important:</strong> As you're from a visa-required country, you'll need to apply for a study permit before traveling to Canada. Processing times may vary.</p>
                        </div>
                    `;
                }
                
                resultElement.style.display = 'block';
            }
        });
    }

    // Mobile menu toggle
    const drawerOverlay = document.querySelector('.drawer-overlay');
    const sideDrawer = document.querySelector('.side-drawer');
    const drawerClose = document.querySelector('.drawer-close');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            drawerOverlay.classList.add('open');
            sideDrawer.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    }

    if (drawerClose) {
        drawerClose.addEventListener('click', function() {
            drawerOverlay.classList.remove('open');
            sideDrawer.classList.remove('open');
            document.body.style.overflow = '';
        });
    }

    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', function() {
            drawerOverlay.classList.remove('open');
            sideDrawer.classList.remove('open');
            document.body.style.overflow = '';
        });
    }

    // Drawer dropdown toggles
    const drawerDropdowns = document.querySelectorAll('.drawer-dropdown');
    drawerDropdowns.forEach(function(dropdown) {
        const dropdownToggle = dropdown.querySelector('span');
        const submenu = dropdown.querySelector('.drawer-submenu');

        dropdownToggle.addEventListener('click', function() {
            // Close all other open submenus
            document.querySelectorAll('.drawer-submenu.open').forEach(function(menu) {
                if (menu !== submenu) {
                    menu.classList.remove('open');
                    menu.style.maxHeight = null;
                    menu.previousElementSibling.parentElement.classList.remove('active');
                }
            });

            // Toggle current submenu
            dropdown.classList.toggle('active');
            submenu.classList.toggle('open');
            
            if (submenu.classList.contains('open')) {
                submenu.style.maxHeight = submenu.scrollHeight + "px";
            } else {
                submenu.style.maxHeight = null;
            }
        });
    });

    // Header scroll effect
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
}); 