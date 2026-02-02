    </main>
    
    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <!-- About Section -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="text-primary mb-3">Skills Way Institute</h5>
                    <p class="mb-3">Leading vocational training institute providing industry-relevant skills and certifications to empower students for successful careers.</p>
                    <div class="social-links">
                        <a href="#" class="text-white me-3" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white me-3" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="text-primary mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="text-white-50 text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="courses.php" class="text-white-50 text-decoration-none">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-white-50 text-decoration-none">Contact</a></li>
                        <li class="mb-2"><a href="enrollment-form.php" class="text-white-50 text-decoration-none">Admission</a></li>
                    </ul>
                </div>
                
                <!-- Courses -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="text-primary mb-3">Popular Courses</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="courses.php?category=web" class="text-white-50 text-decoration-none">Web Development</a></li>
                        <li class="mb-2"><a href="courses.php?category=graphics" class="text-white-50 text-decoration-none">Graphic Design</a></li>
                        <li class="mb-2"><a href="courses.php?category=autocad" class="text-white-50 text-decoration-none">AutoCAD</a></li>
                        <li class="mb-2"><a href="courses.php?category=digital" class="text-white-50 text-decoration-none">Digital Marketing</a></li>
                        <li class="mb-2"><a href="courses.php?category=python" class="text-white-50 text-decoration-none">Python Programming</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="text-primary mb-3">Contact Info</h5>
                    <div class="contact-info">
                        <p class="mb-2">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            <small>Main Campus, City Center<br>Lahore, Pakistan</small>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            <small>+92 300 1234567</small>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            <small>info@skillsway.edu.pk</small>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock me-2 text-primary"></i>
                            <small>Mon - Fri: 8:00 AM - 6:00 PM</small>
                        </p>
                    </div>
                </div>
            </div>
            
            <hr class="my-4 border-secondary">
            
            <!-- Bottom Footer -->
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-white-50">
                        &copy; <?php echo date('Y'); ?> Skills Way Vocational Institute. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="privacy.php" class="text-white-50 text-decoration-none me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-white-50 text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-primary position-fixed bottom-0 end-0 m-4 rounded-circle" 
            style="display: none; z-index: 1000; width: 50px; height: 50px;" 
            aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" 
         style="background: rgba(255,255,255,0.9); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Please wait...</p>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/main.js"></script>
    
    <!-- Additional Scripts for specific pages -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Back to Top Functionality -->
    <script>
        $(document).ready(function() {
            // Back to top button
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    $('#backToTop').fadeIn();
                } else {
                    $('#backToTop').fadeOut();
                }
            });
            
            $('#backToTop').click(function() {
                $('html, body').animate({scrollTop: 0}, 600);
                return false;
            });
            
            // Add padding to body for fixed navbar
            $('body').css('padding-top', $('.navbar').outerHeight() + 'px');
            
            // Adjust padding on window resize
            $(window).resize(function() {
                $('body').css('padding-top', $('.navbar').outerHeight() + 'px');
            });
        });
    </script>
    
    <!-- Google Analytics (replace with your tracking ID) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_TRACKING_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_TRACKING_ID');
    </script>
    
</body>
</html>