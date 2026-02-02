<?php
$pageTitle = 'My Profile';
$pageIcon = 'fas fa-user-circle';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        try {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $date_of_birth = trim($_POST['date_of_birth'] ?? '');
            
            // Validate inputs
            if (empty($full_name) || empty($email)) {
                throw new Exception('Name and email are required.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if email is already used by another user
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                throw new Exception('Email already in use by another account.');
            }
            
            // Update users table
            $stmt = $db->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $user_id]);
            
            // Update or insert user_profiles
            $stmt = $db->prepare("
                INSERT INTO user_profiles (user_id, address, gender, date_of_birth)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    address = VALUES(address),
                    gender = VALUES(gender),
                    date_of_birth = VALUES(date_of_birth)
            ");
            $stmt->execute([$user_id, $address, $gender, $date_of_birth]);
            
            $_SESSION['success_message'] = 'Profile updated successfully!';
            header('Location: profile.php');
            exit;
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    // Handle avatar upload
    elseif ($action === 'upload_avatar') {
        try {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please select an image to upload.');
            }
            
            $file = $_FILES['avatar'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF allowed.');
            }
            
            if ($file['size'] > 2 * 1024 * 1024) { // 2MB
                throw new Exception('File too large. Maximum size is 2MB.');
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Get old profile image
                $stmt = $db->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $old_image = $stmt->fetchColumn();
                
                // Delete old image if exists
                if ($old_image && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                $stmt->execute([$filename, $user_id]);
                
                $_SESSION['success_message'] = 'Profile picture updated successfully!';
                header('Location: profile.php');
                exit;
            } else {
                throw new Exception('Failed to upload file.');
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Fetch user data
$stmt = $db->prepare("
    SELECT u.*, up.address, up.gender, up.date_of_birth, up.education_level
    FROM users u
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get enrollment statistics
$stmt = $db->prepare("
    SELECT COUNT(*) as total_courses
    FROM enrollment_applications ea
    WHERE ea.user_id = ? AND ea.application_status = 'approved'
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Profile Header -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user_data['profile_image'])): ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($user_data['profile_image']); ?>" 
                             class="rounded-circle" 
                             width="150" 
                             height="150"
                             style="object-fit: cover;"
                             alt="Profile Picture"
                             onerror="this.src='../assets/images/default-avatar.png'">
                        <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 150px; height: 150px; font-size: 3rem;">
                            <?php echo strtoupper(substr($user_data['full_name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="mb-1"><?php echo htmlspecialchars($user_data['full_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo ucfirst($user_data['user_type']); ?></p>
                    
                    <!-- Upload Avatar Button -->
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadAvatarModal">
                        <i class="fas fa-camera me-1"></i> Change Picture
                    </button>
                    
                    <hr class="my-3">
                    
                    <!-- Quick Stats -->
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="mb-0"><?php echo $stats['total_courses']; ?></h5>
                            <small class="text-muted">Courses</small>
                        </div>
                        <div class="col-6">
                            <h5 class="mb-0"><?php echo date('Y') - date('Y', strtotime($user_data['created_at'])); ?>+</h5>
                            <small class="text-muted">Years</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info Card -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Contact Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted d-block">Email</small>
                        <strong><?php echo htmlspecialchars($user_data['email']); ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">Phone</small>
                        <strong><?php echo htmlspecialchars($user_data['phone'] ?: 'Not provided'); ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">Member Since</small>
                        <strong><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?: ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?: ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" 
                                      placeholder="Enter your full address"><?php echo htmlspecialchars($user_data['address'] ?: ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Avatar Modal -->
<div class="modal fade" id="uploadAvatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Upload Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_avatar">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Choose Image</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*" required>
                        <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                    </div>
                    <div id="imagePreview" class="text-center"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Image preview
document.querySelector('input[name="avatar"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').innerHTML = 
                '<img src="' + e.target.result + '" class="img-thumbnail" style="max-width: 300px;">';
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
