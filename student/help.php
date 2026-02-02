<?php
$pageTitle = 'Help & Support';
$pageIcon = 'fas fa-question-circle';
require_once 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <!-- Quick Help Cards -->
        <div class="col-lg-8 mb-4">
            <!-- Search Help -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="mb-3">How can we help you?</h4>
                    <div class="help-search-box">
                        <i class="fas fa-search help-search-icon"></i>
                        <input type="text" class="form-control help-search-input" 
                               id="helpSearch" placeholder="Search for help articles...">
                    </div>
                </div>
            </div>
            
            <!-- FAQs -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <!-- FAQ 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I reset my password?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can change your password by going to <a href="change-password.php">Settings → Change Password</a>. 
                                    You'll need to enter your current password and then choose a new one. If you've forgotten your current 
                                    password, please contact the support team.
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How do I submit an assignment?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to the <a href="assignments.php">Assignments</a> page, click on the assignment you want to submit, 
                                    then click the "Submit Assignment" button. You can upload files or enter text depending on the assignment type.
                                    Make sure to submit before the deadline!
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How can I check my grades?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Your grades are available on the <a href="progress.php">Progress</a> page. You can view your grades 
                                    for individual assignments, quizzes, and your overall course grade. Grades are updated as soon as your 
                                    instructor posts them.
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 4 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I access course materials?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    All course materials including lecture notes, videos, and resources are available on the 
                                    <a href="materials.php">Course Materials</a> page. Materials are organized by course and topic. 
                                    You can download them for offline access.
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 5 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    Can I update my profile information?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! Go to your <a href="profile.php">Profile</a> page to update your personal information, 
                                    contact details, and profile picture. Make sure to save your changes after updating.
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 6 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    How do I contact my instructor?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can send messages to your instructors through the <a href="messages.php">Messages</a> page. 
                                    Click "New Message", select your instructor from the list, and send your message. You'll receive a 
                                    notification when they reply.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Support Sidebar -->
        <div class="col-lg-4">
            <!-- Contact Support -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-headset me-2"></i>Contact Support</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">Can't find what you're looking for? Get in touch with our support team.</p>
                    
                    <div class="d-grid gap-2">
                        <a href="mailto:askillswaykpr@gmail.com" class="btn btn-outline-primary contact-btn">
                            <i class="fas fa-envelope me-2"></i>Email Support
                        </a>
                        <a href="tel:03070237356" class="btn btn-outline-success contact-btn">
                            <i class="fas fa-phone me-2"></i>Call Us
                        </a>
                    </div>
                    
                    <hr>
                    
                    <div class="small text-muted">
                        <p class="mb-1"><strong>Office Hours:</strong></p>
                        <p class="mb-1">Monday - Friday: 9:00 AM - 5:00 PM</p>
                        <p class="mb-0">Saturday: 10:00 AM - 2:00 PM</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i>My Profile
                    </a>
                    <a href="assignments.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tasks me-2"></i>Assignments
                    </a>
                    <a href="materials.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-book me-2"></i>Course Materials
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-server me-2"></i>System Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Portal</span>
                        <span class="system-status-badge operational">Operational</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Database</span>
                        <span class="system-status-badge operational">Operational</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Messaging</span>
                        <span class="system-status-badge operational">Operational</span>
                    </div>
                    
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        All systems operational
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Help Categories -->
    <div class="row mt-4">
        <div class="col-12">
            <h4 class="mb-3">Browse by Category</h4>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card help-card shadow-sm">
                <div class="card-body text-center">
                    <div class="help-card-icon bg-primary text-white mx-auto">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h5>Getting Started</h5>
                    <p class="text-muted small">Learn the basics of using the student portal</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card help-card shadow-sm">
                <div class="card-body text-center">
                    <div class="help-card-icon bg-success text-white mx-auto">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h5>Courses & Classes</h5>
                    <p class="text-muted small">Managing your courses and accessing materials</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card help-card shadow-sm">
                <div class="card-body text-center">
                    <div class="help-card-icon bg-warning text-white mx-auto">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h5>Account & Settings</h5>
                    <p class="text-muted small">Manage your profile and preferences</p>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
// Help search functionality
document.getElementById('helpSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const accordionItems = document.querySelectorAll('.accordion-item');
    
    accordionItems.forEach(item => {
        const button = item.querySelector('.accordion-button');
        const body = item.querySelector('.accordion-body');
        const text = (button.textContent + body.textContent).toLowerCase();
        
        if (searchTerm === '' || text.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
