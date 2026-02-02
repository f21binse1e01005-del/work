<?php
// This should be in includes/footer.php
// The header.php should include everything up to </header> or start of main content
?>

    </main> <!-- Close main content if not already closed -->

    <!-- Main Footer -->
    <footer class="footer mt-auto">
        <div class="container-fluid">
            <!-- Footer Top Section -->
            <div class="footer-top py-5 bg-dark text-white">
                <div class="container">
                    <div class="row">
                        <!-- Institute Info -->
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="footer-about">
                                <h3 class="h4 mb-3">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    Skills Way Vocational Institute
                                </h3>
                                <p class="text-light mb-3">
                                    Empowering students with practical skills for the modern workforce through quality vocational education and training.
                                </p>
                                <div class="contact-info">
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-phone me-2"></i>
                                        0307-0237356, 0331-3307365
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-envelope me-2"></i>
                                        askillswaykpr@gmail.com
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="col-lg-2 col-md-6 mb-4">
                            <h5 class="h6 mb-3">Quick Links</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <a href="index.php" class="text-light text-decoration-none">
                                        <i class="fas fa-home me-1"></i> Home
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="courses.php" class="text-light text-decoration-none">
                                        <i class="fas fa-book me-1"></i> Courses
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="about.php" class="text-light text-decoration-none">
                                        <i class="fas fa-info-circle me-1"></i> About Us
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="contact.php" class="text-light text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i> Contact
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="faq.php" class="text-light text-decoration-none">
                                        <i class="fas fa-question-circle me-1"></i> FAQ
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Student Resources -->
                        <div class="col-lg-2 col-md-6 mb-4">
                            <h5 class="h6 mb-3">Student Resources</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <a href="dashboard.php" class="text-light text-decoration-none">
                                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="materials.php" class="text-light text-decoration-none">
                                        <i class="fas fa-book-open me-1"></i> Course Materials
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="assignments.php" class="text-light text-decoration-none">
                                        <i class="fas fa-tasks me-1"></i> Assignments
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="grades.php" class="text-light text-decoration-none">
                                        <i class="fas fa-chart-bar me-1"></i> Grades
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="messages.php" class="text-light text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i> Messages
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Social Media & Newsletter -->
                        <div class="col-lg-4 col-md-6 mb-4">
                            <h5 class="h6 mb-3">Connect With Us</h5>
                            <div class="social-links mb-4">
                                <a href="https://facebook.com" class="btn btn-outline-light btn-sm me-2 mb-2" target="_blank">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://twitter.com" class="btn btn-outline-light btn-sm me-2 mb-2" target="_blank">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="https://instagram.com" class="btn btn-outline-light btn-sm me-2 mb-2" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="https://linkedin.com" class="btn btn-outline-light btn-sm me-2 mb-2" target="_blank">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="https://youtube.com" class="btn btn-outline-light btn-sm mb-2" target="_blank">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            </div>
                            
                            <!-- Newsletter Subscription -->
                            <div class="newsletter">
                                <h6 class="mb-2">Subscribe to Newsletter</h6>
                                <p class="small text-light mb-3">Get updates on new courses and events</p>
                                <form id="newsletterForm" class="d-flex">
                                    <input type="email" class="form-control form-control-sm me-2" 
                                           placeholder="Your email" required>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom Section -->
            <div class="footer-bottom py-3 bg-black">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <p class="mb-0 text-light small">
                                &copy; <?php echo date('Y'); ?> Skills Way Vocational Institute. All rights reserved.
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <ul class="list-inline mb-0">
                                <li class="list-inline-item">
                                    <a href="privacy.php" class="text-light text-decoration-none small">Privacy Policy</a>
                                </li>
                                <li class="list-inline-item mx-2">|</li>
                                <li class="list-inline-item">
                                    <a href="terms.php" class="text-light text-decoration-none small">Terms of Service</a>
                                </li>
                                <li class="list-inline-item mx-2">|</li>
                                <li class="list-inline-item">
                                    <a href="sitemap.php" class="text-light text-decoration-none small">Sitemap</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <button type="button" class="btn btn-primary btn-floating btn-lg" id="backToTop">
            <i class="fas fa-arrow-up"></i>
        </button>
    </footer>

    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <!-- Toast notifications will be appended here -->
    </div>

    <!-- Modal for Confirmations -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmationModalConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner-container">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-white" id="loadingText">Loading...</p>
        </div>
    </div>

    <!-- Essential Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/main.js"></script>
    
    <?php
    // Page-specific scripts
    if (isset($pageTitle)) {
        $scriptFile = '../assets/js/' . strtolower(str_replace(' ', '_', $pageTitle)) . '.js';
        if (file_exists($scriptFile)) {
            echo '<script src="' . $scriptFile . '"></script>';
        }
    }
    ?>
    
    <!-- Session-specific scripts -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <script src="../assets/js/user-session.js"></script>
    <?php endif; ?>
    
    <!-- Analytics Script (example) -->
    <script>
        // Google Analytics (example)
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
        
        ga('create', 'UA-XXXXX-Y', 'auto');
        ga('send', 'pageview');
    </script>

    <!-- Custom JavaScript Functions -->
    <script>
    $(document).ready(function() {
        // Back to Top Button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('#backToTop').fadeIn();
            } else {
                $('#backToTop').fadeOut();
            }
        });

        $('#backToTop').click(function() {
            $('html, body').animate({scrollTop: 0}, 500);
            return false;
        });

        // Newsletter Form Submission
        $('#newsletterForm').submit(function(e) {
            e.preventDefault();
            const email = $(this).find('input[type="email"]').val();
            
            if (validateEmail(email)) {
                showToast('Subscribed to newsletter successfully!', 'success');
                $(this).trigger('reset');
                
                // In a real application, you would send this to your server
                $.ajax({
                    url: 'subscribe_newsletter.php',
                    method: 'POST',
                    data: { email: email },
                    success: function(response) {
                        // Handle success
                    }
                });
            } else {
                showToast('Please enter a valid email address.', 'error');
            }
        });

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Initialize popovers
        $('[data-bs-toggle="popover"]').popover();

        // Auto-hide alerts after 5 seconds
        $('.alert').not('.alert-permanent').delay(5000).fadeOut(400);

        // Form validation enhancement
        $('form').on('submit', function() {
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');
        });

        // Session timeout warning
        let sessionTimeout = <?php echo isset($_SESSION['user_id']) ? '1800000' : '0'; ?>; // 30 minutes
        if (sessionTimeout > 0) {
            setTimeout(function() {
                showSessionWarning();
            }, sessionTimeout - 60000); // Warn 1 minute before timeout
        }
    });

    // Utility Functions
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const toast = $(`
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">${getToastTitle(type)}</strong>
                    <small class="text-white">Just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `);

        $('.toast-container').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();

        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }

    function getToastTitle(type) {
        const titles = {
            'success': 'Success',
            'error': 'Error',
            'warning': 'Warning',
            'info': 'Information'
        };
        return titles[type] || 'Notification';
    }

    function showLoading(message = 'Loading...') {
        $('#loadingText').text(message);
        $('#loadingOverlay').fadeIn();
    }

    function hideLoading() {
        $('#loadingOverlay').fadeOut();
    }

    function showConfirmation(message, confirmCallback) {
        $('#confirmationModalBody').text(message);
        $('#confirmationModalConfirm').off('click').on('click', function() {
            confirmCallback();
            $('#confirmationModal').modal('hide');
        });
        $('#confirmationModal').modal('show');
    }

    function showSessionWarning() {
        const modal = $(`
            <div class="modal fade" id="sessionWarningModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">Session Expiring Soon</h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session will expire in 1 minute due to inactivity.</p>
                            <p>Would you like to extend your session?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="logoutBtn">Logout</button>
                            <button type="button" class="btn btn-primary" id="extendSessionBtn">Extend Session</button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);
        const bsModal = new bootstrap.Modal(modal[0]);
        bsModal.show();

        $('#extendSessionBtn').click(function() {
            $.ajax({
                url: 'extend_session.php',
                method: 'POST',
                success: function() {
                    bsModal.hide();
                    modal.remove();
                    showToast('Session extended successfully!', 'success');
                }
            });
        });

        $('#logoutBtn').click(function() {
            window.location.href = 'logout.php';
        });

        // Auto logout after 60 seconds if no action
        setTimeout(function() {
            if ($('#sessionWarningModal').is(':visible')) {
                window.location.href = 'logout.php?timeout=1';
            }
        }, 60000);
    }

    // AJAX error handling
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (jqxhr.status === 401) {
            // Unauthorized - redirect to login
            window.location.href = 'login.php?expired=1';
        } else if (jqxhr.status === 403) {
            // Forbidden
            showToast('You do not have permission to perform this action.', 'error');
        } else if (jqxhr.status === 500) {
            // Server error
            showToast('Server error. Please try again later.', 'error');
        } else if (jqxhr.status === 0) {
            // Network error
            showToast('Network error. Please check your connection.', 'error');
        }
    });

    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl + S to save forms
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            $('form:visible').first().submit();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            $('.modal').modal('hide');
        }
        
        // Ctrl + / to focus search
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            $('input[type="search"], #searchInput').first().focus();
        }
    });

    // Copy to clipboard functionality
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard!', 'success');
        }, function() {
            showToast('Failed to copy to clipboard.', 'error');
        });
    }

    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // File size formatting
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Debounce function for search inputs
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Throttle function for scroll events
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Check if element is in viewport
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Lazy load images
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = [].slice.call(document.querySelectorAll('img.lazy'));
        
        if ('IntersectionObserver' in window) {
            let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        let lazyImage = entry.target;
                        lazyImage.src = lazyImage.dataset.src;
                        lazyImage.classList.remove('lazy');
                        lazyImageObserver.unobserve(lazyImage);
                    }
                });
            });

            lazyImages.forEach(function(lazyImage) {
                lazyImageObserver.observe(lazyImage);
            });
        }
    });
    </script>

    <!-- Optional: Add this for older browser support -->
    <!--[if lt IE 9]>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
</body>
</html>