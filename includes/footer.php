<footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>Visafy</h3>
                    <p>We specialize in providing expert immigration consultancy services for Canada, helping you achieve your dreams of studying, working, or settling in Canada.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="/">Home</a></li>
                        <li><a href="/about-us.php">About Us</a></li>
                        <li><a href="/services.php">Visa Services</a></li>
                        <li><a href="/assessment-tools.php">Assessment Tools</a></li>
                        <li><a href="/resources.php">Resources</a></li>
                        <li><a href="/contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Visa Services</h3>
                    <ul class="footer-links">
                        <li><a href="/visas.php?type=study">Study Permits</a></li>
                        <li><a href="/visas.php?type=work">Work Permits</a></li>
                        <li><a href="/visas.php?type=express-entry">Express Entry</a></li>
                        <li><a href="/visas.php?type=provincial-nominee">Provincial Nominee</a></li>
                        <li><a href="/visas.php?type=family">Family Sponsorship</a></li>
                        <li><a href="/visas.php?type=visitor">Visitor Visas</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Contact Information</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> 2233 Argentina Rd, Mississauga ON L5N 2X7, Canada</li>
                        <li><i class="fas fa-phone"></i> +1 (647) 226-7436</li>
                        <li><i class="fas fa-envelope"></i> info@visafy.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9am-5pm</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <span id="current-year"></span> Visafy Immigration Consultancy. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript Libraries -->
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/header.js"></script>
    <script src="/assets/js/resources.js"></script>
    
    <!-- Assessment Tool Drawer Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const assessmentToolBtn = document.getElementById('assessmentToolBtn');
            const assessmentDrawer = document.getElementById('assessmentDrawer');
            const assessmentDrawerOverlay = document.getElementById('assessmentDrawerOverlay');
            const closeAssessmentDrawer = document.getElementById('closeAssessmentDrawer');
            
            function openDrawer() {
                assessmentDrawer.classList.add('open');
                assessmentDrawerOverlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
            
            function closeDrawer() {
                assessmentDrawer.classList.remove('open');
                assessmentDrawerOverlay.classList.remove('open');
                document.body.style.overflow = '';
            }
            
            assessmentToolBtn.addEventListener('click', openDrawer);
            closeAssessmentDrawer.addEventListener('click', closeDrawer);
            assessmentDrawerOverlay.addEventListener('click', closeDrawer);
        });
    </script>
   
    <!-- JavaScript initialization -->
    <script>
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    </script>

    <!-- If you have footer-specific JS files -->
    <script src="<?php echo isset($base_path) ? $base_path : ''; ?>/assets/js/footer.js"></script>
</body>
</html> 