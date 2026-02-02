<?php
/**
 * Student Registration by Admin
 * File: admin/register.php
 */

// Handle student registration BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $cnic = $_POST['cnic'];
    $phone = $_POST['phone'];
    $password = hash('sha256', $_POST['password'] . 'skills_way_salt');
    
    // Profile fields
    $date_of_birth = $_POST['date_of_birth'] ?: null;
    $gender = $_POST['gender'] ?: null;
    $address = $_POST['address'];
    $education_level = $_POST['education_level'];
    $parent_name = $_POST['parent_name'];
    $parent_phone = $_POST['parent_phone'];
    $emergency_contact = $_POST['emergency_contact'];
    
    try {
        // Insert user
        $userSql = "INSERT INTO users (username, email, full_name, cnic, phone, password_hash, user_type, account_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'student', 'active')";
        $stmt = $db->prepare($userSql);
        $stmt->execute([$username, $email, $full_name, $cnic, $phone, $password]);
        
        $user_id = $db->lastInsertId();
        
        // Insert profile
        $profileSql = "INSERT INTO user_profiles (user_id, date_of_birth, gender, address, education_level, parent_name, parent_phone, emergency_contact) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($profileSql);
        $stmt->execute([$user_id, $date_of_birth, $gender, $address, $education_level, $parent_name, $parent_phone, $emergency_contact]);
        
        header('Location: students.php?added=1');
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-plus me-2"></i>Add New Student</h1>
        <a href="students.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Students
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CNIC</label>
                                <input type="text" name="cnic" class="form-control" placeholder="12345-1234567-1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Education Level</label>
                                <input type="text" name="education_level" class="form-control" placeholder="e.g., Matric, Intermediate">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Parent/Guardian Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Parent/Guardian Name</label>
                            <input type="text" name="parent_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent Phone</label>
                            <input type="text" name="parent_phone" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <a href="students.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once 'includes/footer.php'; ?>