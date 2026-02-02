<?php
/**
 * Student Edit Page
 * File: admin/student-edit.php
 */

// Handle student update BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $student_id = $_POST['student_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $account_status = $_POST['account_status'];
    
    // Profile fields
    $date_of_birth = $_POST['date_of_birth'] ?: null;
    $gender = $_POST['gender'] ?: null;
    $address = $_POST['address'];
    $education_level = $_POST['education_level'];
    $previous_experience = $_POST['previous_experience'];
    $emergency_contact = $_POST['emergency_contact'];
    $parent_name = $_POST['parent_name'];
    $parent_phone = $_POST['parent_phone'];
    
    // Update users table
    $userSql = "UPDATE users SET full_name = ?, email = ?, phone = ?, account_status = ? WHERE user_id = ?";
    $stmt = $db->prepare($userSql);
    $stmt->execute([$full_name, $email, $phone, $account_status, $student_id]);
    
    // Update or insert profile
    $profileSql = "INSERT INTO user_profiles (user_id, date_of_birth, gender, address, education_level, previous_experience, emergency_contact, parent_name, parent_phone) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                   ON DUPLICATE KEY UPDATE 
                   date_of_birth = VALUES(date_of_birth), gender = VALUES(gender), address = VALUES(address), 
                   education_level = VALUES(education_level), previous_experience = VALUES(previous_experience), 
                   emergency_contact = VALUES(emergency_contact), parent_name = VALUES(parent_name), parent_phone = VALUES(parent_phone)";
    $stmt = $db->prepare($profileSql);
    $stmt->execute([$student_id, $date_of_birth, $gender, $address, $education_level, $previous_experience, $emergency_contact, $parent_name, $parent_phone]);
    
    header('Location: student-detail.php?id=' . $student_id . '&updated=1');
    exit;
}

require_once 'includes/header.php';

$student_id = $_GET['id'] ?? 0;

// Get student details
$studentQuery = "
    SELECT u.*, p.date_of_birth, p.gender, p.address, p.education_level, 
           p.previous_experience, p.emergency_contact, p.parent_name, p.parent_phone
    FROM users u
    LEFT JOIN user_profiles p ON u.user_id = p.user_id
    WHERE u.user_id = ? AND u.user_type = 'student'
";
$stmt = $db->prepare($studentQuery);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit;
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Student</h1>
        <a href="student-detail.php?id=<?php echo $student['user_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Details
        </a>
    </div>

    <form method="POST">
        <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Status</label>
                                <select name="account_status" class="form-select" required>
                                    <option value="active" <?php echo $student['account_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $student['account_status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $student['account_status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="pending_verification" <?php echo $student['account_status'] == 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?php echo $student['date_of_birth']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $student['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $student['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $student['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student['address']); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Education Level</label>
                                <input type="text" name="education_level" class="form-control" value="<?php echo htmlspecialchars($student['education_level']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($student['emergency_contact']); ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Previous Experience</label>
                                <textarea name="previous_experience" class="form-control" rows="3"><?php echo htmlspecialchars($student['previous_experience']); ?></textarea>
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
                            <input type="text" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($student['parent_name']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent Phone</label>
                            <input type="text" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_phone']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Account Info</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
                        <p><strong>CNIC:</strong> <?php echo htmlspecialchars($student['cnic']); ?></p>
                        <p><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($student['registration_date'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <a href="student-detail.php?id=<?php echo $student['user_id']; ?>" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once 'includes/footer.php'; ?>