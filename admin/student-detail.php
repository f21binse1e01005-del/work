<?php
/**
 * Student Detail Page
 * File: admin/student-detail.php
 */

require_once 'includes/header.php';

$student_id = $_GET['id'] ?? 0;

// Get student details with profile
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

// Get student's enrollment applications
$enrollmentsQuery = "
    SELECT ea.*, c.course_name, c.course_code, b.batch_name, b.batch_code
    FROM enrollment_applications ea
    JOIN courses c ON ea.course_id = c.course_id
    JOIN batches b ON ea.batch_id = b.batch_id
    WHERE ea.user_id = ?
    ORDER BY ea.application_date DESC
";
$stmt = $db->prepare($enrollmentsQuery);
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user me-2"></i>Student Details</h1>
        <div>
            <a href="student-edit.php?id=<?php echo $student['user_id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Edit Student
            </a>
            <a href="generate-report.php?type=certificate&student_id=<?php echo $student['user_id']; ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-certificate me-1"></i>Generate Certificate
            </a>
            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Students
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($student['username']); ?></p>
                    <span class="badge <?php echo $student['account_status'] == 'active' ? 'bg-success' : 'bg-warning'; ?>">
                        <?php echo ucfirst($student['account_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Full Name</label>
                            <p><?php echo htmlspecialchars($student['full_name']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Email</label>
                            <p><?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Phone</label>
                            <p><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">CNIC</label>
                            <p><?php echo htmlspecialchars($student['cnic']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Date of Birth</label>
                            <p><?php echo $student['date_of_birth'] ? date('F d, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Gender</label>
                            <p><?php echo ucfirst($student['gender'] ?: 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Address</label>
                            <p><?php echo htmlspecialchars($student['address'] ?: 'Not provided'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Education Level</label>
                            <p><?php echo htmlspecialchars($student['education_level'] ?: 'Not provided'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Registration Date</label>
                            <p><?php echo date('F d, Y', strtotime($student['registration_date'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Emergency Contact</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Parent/Guardian Name</label>
                            <p><?php echo htmlspecialchars($student['parent_name'] ?: 'Not provided'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Parent Phone</label>
                            <p><?php echo htmlspecialchars($student['parent_phone'] ?: 'Not provided'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Emergency Contact</label>
                            <p><?php echo htmlspecialchars($student['emergency_contact'] ?: 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Academic Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Previous Experience</label>
                        <p><?php echo htmlspecialchars($student['previous_experience'] ?: 'None specified'); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Last Login</label>
                        <p><?php echo $student['last_login'] ? date('F d, Y g:i A', strtotime($student['last_login'])) : 'Never logged in'; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Enrollment History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollments)): ?>
                        <p class="text-muted">No enrollment applications found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Batch</th>
                                        <th>Application Date</th>
                                        <th>Status</th>
                                        <th>Payment Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($enrollment['course_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_code']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($enrollment['batch_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['batch_code']); ?></small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($enrollment['application_date'])); ?></td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'submitted' => 'bg-info',
                                                    'under_review' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'waitlisted' => 'bg-secondary'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $statusColors[$enrollment['application_status']]; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $enrollment['application_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $paymentColors = [
                                                    'pending' => 'bg-warning',
                                                    'partial' => 'bg-info',
                                                    'paid' => 'bg-success',
                                                    'waived' => 'bg-secondary'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $paymentColors[$enrollment['payment_status']]; ?>">
                                                    <?php echo ucfirst($enrollment['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>