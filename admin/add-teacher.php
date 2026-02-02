<?php
/**
 * Add Teacher - Professional Admin Interface
 * File: admin/add-teacher.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $education_level = trim($_POST['education_level'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);
    
    // Validation
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (empty($cnic)) $errors[] = 'CNIC is required';
    if (empty($password) || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (empty($education_level)) $errors[] = 'Education level is required';
    if (empty($specialization)) $errors[] = 'Specialization is required';
    
    // Check for existing username/email
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists';
        }
    }
    
    // Insert teacher if no errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert into users table
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, phone, cnic, user_type, account_status, monthly_salary, joining_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'teacher', 'active', ?, NOW())
            ");
            // Use Salted SHA256 to match the Authentication class in config/auth.php
            $password_hash = hash('sha256', $password . 'skills_way_salt');
            $stmt->execute([$username, $email, $password_hash, $full_name, $phone, $cnic, $salary]);
            $user_id = $db->lastInsertId();
            
            // Insert into user_profiles table
            $stmt = $db->prepare("
                INSERT INTO user_profiles (user_id, education_level, address, specialization, years_experience) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $education_level, $address, $specialization, $experience_years]);
            
            // Note: 'teachers' is a view, so we do not insert into it. All data is now in users and user_profiles.
            
            $db->commit();
            
            $message = 'Teacher added successfully!';
            $messageType = 'success';
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-plus me-2"></i>Add New Teacher</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="teachers.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Teachers
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Teacher Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Teacher Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="addTeacherForm" novalidate>
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-user me-1"></i>Personal Details</h6>
                                
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please provide a valid full name.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="cnic" class="form-label">CNIC <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="cnic" name="cnic" 
                                           value="<?php echo htmlspecialchars($_POST['cnic'] ?? ''); ?>" 
                                           placeholder="12345-1234567-1" required>
                                    <div class="invalid-feedback">Please provide a valid CNIC.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please provide a valid phone number.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Account Information -->
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-key me-1"></i>Account Details</h6>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please provide a unique username.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Password must be at least 6 characters.</div>
                                    <div class="form-text">Minimum 6 characters required.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Passwords must match.</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row">
                            <!-- Professional Information -->
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-graduation-cap me-1"></i>Professional Details</h6>
                                
                                <div class="mb-3">
                                    <label for="education_level" class="form-label">Education Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="education_level" name="education_level" required>
                                        <option value="">Select Education Level</option>
                                        <option value="Bachelor's Degree" <?php echo ($_POST['education_level'] ?? '') === "Bachelor's Degree" ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                        <option value="Master's Degree" <?php echo ($_POST['education_level'] ?? '') === "Master's Degree" ? 'selected' : ''; ?>>Master's Degree</option>
                                        <option value="PhD" <?php echo ($_POST['education_level'] ?? '') === "PhD" ? 'selected' : ''; ?>>PhD</option>
                                        <option value="Diploma" <?php echo ($_POST['education_level'] ?? '') === "Diploma" ? 'selected' : ''; ?>>Diploma</option>
                                        <option value="Certificate" <?php echo ($_POST['education_level'] ?? '') === "Certificate" ? 'selected' : ''; ?>>Certificate</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an education level.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="specialization" class="form-label">Specialization <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="specialization" name="specialization" 
                                           value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" 
                                           placeholder="e.g., Computer Science, Electrical Engineering" required>
                                    <div class="invalid-feedback">Please provide specialization.</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-briefcase me-1"></i>Employment Details</h6>
                                
                                <div class="mb-3">
                                    <label for="experience_years" class="form-label">Years of Experience</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                           value="<?php echo htmlspecialchars($_POST['experience_years'] ?? '0'); ?>" min="0" max="50">
                                </div>

                                <div class="mb-3">
                                    <label for="salary" class="form-label">Monthly Salary (PKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₨</span>
                                        <input type="number" class="form-control" id="salary" name="salary" 
                                               value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>" min="0" step="1000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i>Reset Form
                            </button>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="previewData()">
                                    <i class="fas fa-eye me-1"></i>Preview
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-save me-1"></i>Add Teacher
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="teachers.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>View All Teachers
                        </a>
                        <a href="courses.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-book me-1"></i>Manage Courses
                        </a>
                        <a href="batches.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-calendar me-1"></i>View Batches
                        </a>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-muted mb-2">Tips:</h6>
                    <ul class="list-unstyled small text-muted">
                        <li><i class="fas fa-check text-success me-1"></i>Use strong passwords</li>
                        <li><i class="fas fa-check text-success me-1"></i>Verify email addresses</li>
                        <li><i class="fas fa-check text-success me-1"></i>Complete all required fields</li>
                        <li><i class="fas fa-check text-success me-1"></i>Double-check contact information</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Teacher Information Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="submitFormFromPreview()">
                    <i class="fas fa-save me-1"></i>Confirm & Add Teacher
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: box-shadow 0.15s ease-in-out;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
}

.btn-primary {
    background-color: #4361ee;
    border-color: #4361ee;
}

.btn-primary:hover {
    background-color: #3a0ca3;
    border-color: #3a0ca3;
}

.text-danger {
    color: #dc3545 !important;
}

.invalid-feedback {
    display: block;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

.preview-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.preview-section h6 {
    color: #4361ee;
    border-bottom: 2px solid #4361ee;
    padding-bottom: 5px;
    margin-bottom: 10px;
}
</style>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Form validation
    $('#addTeacherForm').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // Password toggle
    $('#togglePassword').click(function() {
        const password = $('#password');
        const icon = $(this).find('i');
        
        if (password.attr('type') === 'password') {
            password.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            password.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Real-time password confirmation
    $('#confirm_password').on('input', function() {
        const password = $('#password').val();
        const confirmPassword = $(this).val();
        
        if (password !== confirmPassword) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid').addClass('is-valid');
        }
    });

    // Username availability check (simulated)
    $('#username').on('blur', function() {
        const username = $(this).val();
        if (username.length >= 3) {
            // Simulate AJAX check
            setTimeout(() => {
                $(this).removeClass('is-invalid').addClass('is-valid');
            }, 500);
        }
    });

    // Auto-generate username from full name
    $('#full_name').on('input', function() {
        const fullName = $(this).val();
        const username = fullName.toLowerCase()
            .replace(/[^a-z0-9]/g, '')
            .substring(0, 15);
        
        if (username && !$('#username').val()) {
            $('#username').val(username);
        }
    });
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All data will be lost.')) {
        document.getElementById('addTeacherForm').reset();
        $('.was-validated').removeClass('was-validated');
        $('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
    }
}

// Preview function
function previewData() {
    const formData = new FormData(document.getElementById('addTeacherForm'));
    let previewHTML = '';
    
    // Personal Information
    previewHTML += '<div class="preview-section">';
    previewHTML += '<h6><i class="fas fa-user me-2"></i>Personal Information</h6>';
    previewHTML += `<p><strong>Full Name:</strong> ${formData.get('full_name') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Email:</strong> ${formData.get('email') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>CNIC:</strong> ${formData.get('cnic') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Phone:</strong> ${formData.get('phone') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Address:</strong> ${formData.get('address') || 'Not provided'}</p>`;
    previewHTML += '</div>';
    
    // Account Information
    previewHTML += '<div class="preview-section">';
    previewHTML += '<h6><i class="fas fa-key me-2"></i>Account Information</h6>';
    previewHTML += `<p><strong>Username:</strong> ${formData.get('username') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Password:</strong> ${formData.get('password') ? '••••••••' : 'Not provided'}</p>`;
    previewHTML += '</div>';
    
    // Professional Information
    previewHTML += '<div class="preview-section">';
    previewHTML += '<h6><i class="fas fa-graduation-cap me-2"></i>Professional Information</h6>';
    previewHTML += `<p><strong>Education Level:</strong> ${formData.get('education_level') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Specialization:</strong> ${formData.get('specialization') || 'Not provided'}</p>`;
    previewHTML += `<p><strong>Experience:</strong> ${formData.get('experience_years') || '0'} years</p>`;
    previewHTML += `<p><strong>Salary:</strong> ₨${formData.get('salary') || '0'}</p>`;
    previewHTML += '</div>';
    
    document.getElementById('previewContent').innerHTML = previewHTML;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}
