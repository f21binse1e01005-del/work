<?php
/**
 * Advanced Courses Management System
 * File: admin/courses.php
 * Features: CRUD, Image Management, Bulk Operations, Search, Export, Analytics
 */

session_start();
require_once '../config/database.php';

// Check admin access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';
$userId = $_SESSION['user_id'] ?? 0;

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=courses_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Name', 'Category', 'Fee', 'Duration', 'Students', 'Enrollments', 'Status', 'Created']);
    
    $stmt = $db->query("
        SELECT c.course_id, c.course_code, c.course_name, cc.category_name, 
               c.fee_amount, c.duration_months, c.max_students,
               (SELECT COUNT(*) FROM enrollment_applications ea WHERE ea.course_id = c.course_id) as enrollments,
               CASE WHEN c.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
               DATE(c.created_at) as created
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.category_id
        ORDER BY c.created_at DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle image upload with improved validation
    $courseImage = null;
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/images/courses/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['course_image']['name']));
        $targetPath = $uploadDir . $fileName;
        
        // Validate image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['course_image']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            // Check file size (max 5MB)
            if ($_FILES['course_image']['size'] > 5 * 1024 * 1024) {
                $message = 'Image file is too large. Maximum size is 5MB.';
                $messageType = 'warning';
            } else {
                // Resize image if needed
                list($width, $height) = getimagesize($_FILES['course_image']['tmp_name']);
                $maxWidth = 1200;
                $maxHeight = 800;
                
                if (move_uploaded_file($_FILES['course_image']['tmp_name'], $targetPath)) {
                    // Optimize image
                    optimizeImage($targetPath, $maxWidth, $maxHeight);
                    $courseImage = 'uploads/images/courses/' . $fileName;
                }
            }
        }
    }
    
    // Handle different actions
    switch ($action) {
        case 'add':
            handleAddCourse($db, $userId, $courseImage);
            break;
            
        case 'edit':
            handleEditCourse($db, $userId, $courseImage);
            break;
            
        case 'toggle':
            handleToggleCourse($db, $_POST['course_id'] ?? 0);
            break;
            
        case 'delete':
            handleDeleteCourse($db, $_POST['course_id'] ?? 0);
            break;
            
        case 'bulk':
            handleBulkActions($db, $_POST['bulk_action'] ?? '', $_POST['selected_courses'] ?? []);
            break;
            
        case 'duplicate':
            handleDuplicateCourse($db, $_POST['course_id'] ?? 0, $userId);
            break;
    }
}

// Handle Add Course
function handleAddCourse($db, $userId, $courseImage) {
    global $message, $messageType;
    
    $courseCode = $_POST['course_code'] ?? '';
    if (empty($courseCode)) {
        $courseCode = generateCourseCode($_POST['course_name'] ?? '');
    }
    
    // Check for duplicate course code
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
    $checkStmt->execute([$courseCode]);
    
    if ($checkStmt->fetchColumn() > 0) {
        $message = 'Course code already exists. Please use a different code.';
        $messageType = 'warning';
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO courses 
        (course_code, course_name, category_id, description, duration_months, 
        fee_amount, max_students, course_image, is_free, requirements, 
        learning_outcomes, is_active, created_by, syllabus, prerequisites) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $maxStudents = $_POST['max_students'] ?? 20;
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 1;
    
    try {
        $stmt->execute([
            $courseCode,
            $_POST['course_name'] ?? '', 
            $_POST['category_id'] ?? 1, 
            $_POST['description'] ?? '', 
            $_POST['duration_months'] ?? 3, 
            $_POST['fee_amount'] ?? 0, 
            $maxStudents,
            $courseImage,
            $isFree,
            $_POST['requirements'] ?? '',
            $_POST['learning_outcomes'] ?? '',
            $isActive,
            $userId,
            $_POST['syllabus'] ?? '',
            $_POST['prerequisites'] ?? ''
        ]);
        
        $courseId = $db->lastInsertId();
        $message = 'Course added successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity($db, $userId, 'course_add', [
            'course_id' => $courseId,
            'course_name' => $_POST['course_name'],
            'course_code' => $courseCode
        ]);
        
    } catch (PDOException $e) {
        $message = 'Error adding course: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle Edit Course
function handleEditCourse($db, $userId, $courseImage) {
    global $message, $messageType;
    
    $courseId = $_POST['course_id'] ?? 0;
    if (!$courseId) {
        $message = 'Invalid course ID';
        $messageType = 'danger';
        return;
    }
    
    // Get existing image if no new image uploaded
    if (!$courseImage) {
        $checkStmt = $db->prepare("SELECT course_image FROM courses WHERE course_id = ?");
        $checkStmt->execute([$courseId]);
        $existing = $checkStmt->fetch();
        $courseImage = $existing['course_image'] ?? null;
    }
    
    $stmt = $db->prepare("UPDATE courses SET 
        course_name = ?, 
        category_id = ?, 
        description = ?, 
        duration_months = ?, 
        fee_amount = ?,
        max_students = ?,
        course_image = ?,
        is_free = ?,
        requirements = ?,
        learning_outcomes = ?,
        syllabus = ?,
        prerequisites = ?,
        is_active = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE course_id = ?");
    
    $maxStudents = $_POST['max_students'] ?? 20;
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 1;
    
    try {
        $stmt->execute([
            $_POST['course_name'] ?? '', 
            $_POST['category_id'] ?? 1, 
            $_POST['description'] ?? '', 
            $_POST['duration_months'] ?? 3, 
            $_POST['fee_amount'] ?? 0,
            $maxStudents,
            $courseImage,
            $isFree,
            $_POST['requirements'] ?? '',
            $_POST['learning_outcomes'] ?? '',
            $_POST['syllabus'] ?? '',
            $_POST['prerequisites'] ?? '',
            $isActive,
            $courseId
        ]);
        
        $message = 'Course updated successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity($db, $userId, 'course_edit', [
            'course_id' => $courseId,
            'course_name' => $_POST['course_name']
        ]);
        
        // Redirect to clear the edit parameter
        header('Location: courses.php');
        exit;
        
    } catch (PDOException $e) {
        $message = 'Error updating course: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle Toggle Course Status
function handleToggleCourse($db, $courseId) {
    global $message, $messageType;
    
    if (!$courseId) return;
    
    $stmt = $db->prepare("UPDATE courses SET is_active = NOT is_active WHERE course_id = ?");
    if ($stmt->execute([$courseId])) {
        // Get new status
        $statusStmt = $db->prepare("SELECT is_active FROM courses WHERE course_id = ?");
        $statusStmt->execute([$courseId]);
        $newStatus = $statusStmt->fetchColumn();
        
        $message = 'Course ' . ($newStatus ? 'activated' : 'deactivated') . ' successfully';
        $messageType = 'success';
    }
}

// Handle Delete Course
function handleDeleteCourse($db, $courseId) {
    global $message, $messageType, $userId;
    
    if (!$courseId) return;
    
    // Check if course has enrollments
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE course_id = ?");
    $checkStmt->execute([$courseId]);
    $enrollmentCount = $checkStmt->fetchColumn();
    
    if ($enrollmentCount > 0) {
        $message = 'Cannot delete course with existing enrollments. Deactivate instead.';
        $messageType = 'warning';
        return;
    }
    
    // Get course info for logging
    $infoStmt = $db->prepare("SELECT course_name, course_code, course_image FROM courses WHERE course_id = ?");
    $infoStmt->execute([$courseId]);
    $courseInfo = $infoStmt->fetch();
    
    $stmt = $db->prepare("DELETE FROM courses WHERE course_id = ?");
    if ($stmt->execute([$courseId])) {
        // Delete associated image file
        if ($courseInfo && !empty($courseInfo['course_image'])) {
            $imagePath = '../' . $courseInfo['course_image'];
            if (file_exists($imagePath) && !strpos($courseInfo['course_image'], 'default-course.jpg')) {
                unlink($imagePath);
            }
        }
        
        $message = 'Course deleted successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity($db, $userId, 'course_delete', [
            'course_id' => $courseId,
            'course_name' => $courseInfo['course_name'] ?? '',
            'course_code' => $courseInfo['course_code'] ?? ''
        ]);
    }
}

// Handle Bulk Actions
function handleBulkActions($db, $action, $selectedIds) {
    global $message, $messageType;
    
    if (!is_array($selectedIds) || empty($selectedIds)) {
        $message = 'No courses selected';
        $messageType = 'warning';
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    
    switch ($action) {
        case 'activate':
            $stmt = $db->prepare("UPDATE courses SET is_active = 1 WHERE course_id IN ($placeholders)");
            $message = count($selectedIds) . ' courses activated';
            break;
            
        case 'deactivate':
            $stmt = $db->prepare("UPDATE courses SET is_active = 0 WHERE course_id IN ($placeholders)");
            $message = count($selectedIds) . ' courses deactivated';
            break;
            
        case 'delete':
            // Check for enrollments
            $checkQuery = "SELECT COUNT(*) as total FROM enrollment_applications WHERE course_id IN ($placeholders)";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute($selectedIds);
            $enrollmentCount = $checkStmt->fetchColumn();
            
            if ($enrollmentCount > 0) {
                $message = "Cannot delete courses with existing enrollments";
                $messageType = 'warning';
                return;
            }
            
            $stmt = $db->prepare("DELETE FROM courses WHERE course_id IN ($placeholders)");
            $message = count($selectedIds) . ' courses deleted';
            break;
            
        default:
            return;
    }
    
    if ($stmt->execute($selectedIds)) {
        $messageType = 'success';
    }
}

// Handle Duplicate Course
function handleDuplicateCourse($db, $courseId, $userId) {
    global $message, $messageType;
    
    if (!$courseId) return;
    
    // Get original course
    $stmt = $db->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $original = $stmt->fetch();
    
    if (!$original) {
        $message = 'Course not found';
        $messageType = 'danger';
        return;
    }
    
    // Generate new course code
    $newCode = $original['course_code'] . '-COPY-' . date('ym');
    
    $insertStmt = $db->prepare("INSERT INTO courses 
        (course_code, course_name, category_id, description, duration_months, 
        fee_amount, max_students, course_image, is_free, requirements, 
        learning_outcomes, syllabus, prerequisites, is_active, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    try {
        $insertStmt->execute([
            $newCode,
            $original['course_name'] . ' (Copy)',
            $original['category_id'],
            $original['description'],
            $original['duration_months'],
            $original['fee_amount'],
            $original['max_students'],
            $original['course_image'],
            $original['is_free'],
            $original['requirements'],
            $original['learning_outcomes'],
            $original['syllabus'] ?? '',
            $original['prerequisites'] ?? '',
            0, // Inactive by default
            $userId
        ]);
        
        $message = 'Course duplicated successfully (set as inactive)';
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = 'Error duplicating course: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Helper Functions
function generateCourseCode($courseName) {
    $code = strtoupper(substr(preg_replace('/[^a-z]/i', '', $courseName), 0, 3));
    $code .= date('ym') . rand(100, 999);
    return $code;
}

function logActivity($db, $userId, $activityType, $details) {
    $stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $activityType, json_encode($details)]);
}

function optimizeImage($path, $maxWidth, $maxHeight) {
    // Image optimization logic
    list($width, $height, $type) = getimagesize($path);
    
    if ($width <= $maxWidth && $height <= $maxHeight) {
        return; // No need to resize
    }
    
    $ratio = min($maxWidth/$width, $maxHeight/$height);
    $newWidth = floor($width * $ratio);
    $newHeight = floor($height * $ratio);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($path);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($path);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($path);
            break;
        default:
            return;
    }
    
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $path, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($newImage, $path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($newImage, $path, 85);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($newImage);
}

// Get search parameters
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'desc';
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE 1=1";
$params = [];
$paramTypes = "";

if ($search) {
    $whereClause .= " AND (c.course_name LIKE ? OR c.course_code LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryFilter) {
    $whereClause .= " AND c.category_id = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter !== '') {
    $whereClause .= " AND c.is_active = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== '') {
    if ($typeFilter === 'free') {
        $whereClause .= " AND c.is_free = 1";
    } elseif ($typeFilter === 'paid') {
        $whereClause .= " AND c.is_free = 0";
    }
}

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM courses c $whereClause");
if ($params) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalCourses = $countStmt->fetchColumn();
$totalPages = ceil($totalCourses / $limit);

// Validate sort column
$allowedSorts = ['course_name', 'course_code', 'fee_amount', 'duration_months', 'created_at', 'is_active'];
$sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
$order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

// Get courses with filters
$query = "SELECT c.*, cc.category_name,
    (SELECT COUNT(*) FROM enrollment_applications ea WHERE ea.course_id = c.course_id) as total_applications,
    (SELECT COUNT(DISTINCT ea.user_id) FROM enrollment_applications ea WHERE ea.course_id = c.course_id AND ea.application_status = 'approved') as enrolled_students,
    (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.course_id AND b.status = 'ongoing') as active_batches,
    (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.course_id) as total_batches
    FROM courses c 
    LEFT JOIN course_categories cc ON c.category_id = cc.category_id
    $whereClause
    ORDER BY c.$sort $order
    LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
if ($params) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$courses = $stmt->fetchAll();

// Get categories for dropdown
$categories = $db->query("SELECT * FROM course_categories WHERE is_active = 1 ORDER BY display_order, category_name")->fetchAll();

// Get course for editing
$editCourse = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCourse = $stmt->fetch();
}

// Get statistics for dashboard
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_courses,
        SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_courses,
        AVG(fee_amount) as avg_fee,
        SUM(max_students) as total_capacity
    FROM courses
");

$defaults = [
    'total_courses' => 0,
    'active_courses' => 0,
    'free_courses' => 0,
    'avg_fee' => 0,
    'total_capacity' => 0
];

$stats = $defaults;
if ($statsStmt) {
    $result = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = array_merge($defaults, $result);
    }
}

$categoryStats = $db->query("
    SELECT cc.category_name, COUNT(c.course_id) as course_count
    FROM course_categories cc
    LEFT JOIN courses c ON cc.category_id = c.category_id AND c.is_active = 1
    WHERE cc.is_active = 1
    GROUP BY cc.category_id, cc.category_name
    ORDER BY course_count DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-book me-2"></i>Courses Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                    <i class="fas fa-plus me-1"></i>Add Course
                </button>
                <a href="?export=csv" class="btn btn-outline-success">
                    <i class="fas fa-file-export me-1"></i>Export CSV
                </a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="fix-course-images.php"><i class="fas fa-images me-2"></i>Fix Course Images</a></li>
                    <li><a class="dropdown-item" href="upload-course-image.php"><i class="fas fa-upload me-2"></i>Upload Images</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import me-2"></i>Import Courses</a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Dashboard -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Courses</h6>
                            <h4 class="card-title"><?php echo $stats['total_courses']; ?></h4>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-book text-primary fa-2x"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?php echo $stats['active_courses']; ?> active</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-success shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Average Fee</h6>
                            <h4 class="card-title">Rs. <?php echo number_format($stats['avg_fee'] ?? 0, 0); ?></h4>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-money-bill-wave text-success fa-2x"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?php echo $stats['free_courses']; ?> free courses</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-info shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total Capacity</h6>
                            <h4 class="card-title"><?php echo number_format($stats['total_capacity'] ?? 0); ?></h4>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-users text-info fa-2x"></i>
                        </div>
                    </div>
                    <small class="text-muted">Maximum students across all courses</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-warning shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Categories</h6>
                            <h4 class="card-title"><?php echo count($categoryStats); ?></h4>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-tags text-warning fa-2x"></i>
                        </div>
                    </div>
                    <small class="text-muted">Active categories</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Courses by Category</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($categoryStats as $cat): 
                            $percentage = $stats['total_courses'] > 0 ? round(($cat['course_count'] / $stats['total_courses']) * 100) : 0;
                            $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                            $color = $colors[array_rand($colors)];
                        ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-<?php echo $color; ?> bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-folder text-<?php echo $color; ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?php echo $cat['category_name']; ?></div>
                                    <div class="progress" style="height: 5px; width: 100px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" 
                                             style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $cat['course_count']; ?> courses (<?php echo $percentage; ?>%)</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" placeholder="Search courses..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo $categoryFilter == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="free" <?php echo $typeFilter === 'free' ? 'selected' : ''; ?>>Free</option>
                        <option value="paid" <?php echo $typeFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="sort">
                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Newest</option>
                        <option value="course_name" <?php echo $sort === 'course_name' ? 'selected' : ''; ?>>Name</option>
                        <option value="fee_amount" <?php echo $sort === 'fee_amount' ? 'selected' : ''; ?>>Fee</option>
                        <option value="duration_months" <?php echo $sort === 'duration_months' ? 'selected' : ''; ?>>Duration</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select class="form-select" name="order">
                        <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Desc</option>
                        <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Asc</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                        <a href="courses.php" class="btn btn-outline-secondary">Clear Filters</a>
                        <div class="ms-auto">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-filter me-1"></i>
                                <?php echo $totalCourses; ?> courses found
                            </span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm" class="mb-3">
        <input type="hidden" name="action" value="bulk">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" name="bulk_action" id="bulkAction" style="width: auto;">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" onclick="submitBulkForm()">
                    <i class="fas fa-check me-1"></i>Apply
                </button>
                <div class="form-check ms-3">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label small" for="selectAll">Select All</label>
                </div>
            </div>
            <div class="text-muted">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?> • 
                Showing <?php echo min($limit, count($courses)); ?> of <?php echo $totalCourses; ?> courses
            </div>
        </div>

        <!-- Courses Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAllCheckbox">
                        </th>
                        <th>
                            <a href="?<?php echo buildSortUrl('course_name'); ?>" class="text-decoration-none text-dark">
                                Course
                                <?php if ($sort === 'course_name'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Category</th>
                        <th>
                            <a href="?<?php echo buildSortUrl('duration_months'); ?>" class="text-decoration-none text-dark">
                                Duration
                                <?php if ($sort === 'duration_months'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo buildSortUrl('fee_amount'); ?>" class="text-decoration-none text-dark">
                                Fee
                                <?php if ($sort === 'fee_amount'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Enrollments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-book fa-3x mb-3"></i>
                                    <h5>No courses found</h5>
                                    <p>Try changing your search criteria or add a new course</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): 
                            $imagePath = !empty($course['course_image']) ? '../' . $course['course_image'] : '../uploads/images/courses/default-course.jpg';
                            $imageExists = file_exists($imagePath);
                        ?>
                        <tr class="<?php echo !$course['is_active'] ? 'table-secondary' : ''; ?>">
                            <td>
                                <input type="checkbox" class="course-checkbox" name="selected_courses[]" value="<?php echo $course['course_id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="position-relative me-3">
                                        <img src="<?php echo $imageExists ? $imagePath : 'https://via.placeholder.com/60x45/2c3e50/ffffff?text=' . urlencode(substr($course['course_name'], 0, 2)); ?>" 
                                             alt="<?php echo htmlspecialchars($course['course_name']); ?>"
                                             class="rounded" width="60" height="45"
                                             style="object-fit: cover;"
                                             onerror="this.src='https://via.placeholder.com/60x45/2c3e50/ffffff?text=<?php echo urlencode(substr($course['course_name'], 0, 2)); ?>'">
                                        <?php if ($course['is_free']): ?>
                                            <span class="badge bg-success position-absolute top-0 start-100 translate-middle" style="font-size: 0.6rem;">
                                                FREE
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($course['course_code']); ?></small>
                                        <small class="text-muted">
                                            <i class="fas fa-user-graduate me-1"></i>
                                            <?php echo $course['enrolled_students']; ?> enrolled
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($course['category_name'] ?? 'No Category'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-info me-2"><?php echo $course['duration_months']; ?>m</span>
                                    <small><?php echo $course['total_batches']; ?> batches</small>
                                </div>
                            </td>
                            <td>
                                <?php if ($course['is_free']): ?>
                                    <span class="badge bg-success">Free</span>
                                <?php else: ?>
                                    <div class="fw-bold text-success">Rs. <?php echo number_format($course['fee_amount']); ?></div>
                                    <small class="text-muted">Max <?php echo $course['max_students']; ?> students</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress" style="height: 6px; width: 80px;">
                                    <?php 
                                    $enrollmentRate = $course['max_students'] > 0 ? min(100, ($course['enrolled_students'] / $course['max_students']) * 100) : 0;
                                    $progressColor = $enrollmentRate > 80 ? 'danger' : ($enrollmentRate > 50 ? 'warning' : 'success');
                                    ?>
                                    <div class="progress-bar bg-<?php echo $progressColor; ?>" 
                                         style="width: <?php echo $enrollmentRate; ?>%"
                                         title="<?php echo $enrollmentRate; ?>% filled">
                                    </div>
                                </div>
                                <small>
                                    <?php echo $course['enrolled_students']; ?> / <?php echo $course['max_students']; ?>
                                    (<?php echo round($enrollmentRate); ?>%)
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $course['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="?edit=<?php echo $course['course_id']; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Edit Course"
                                       data-bs-toggle="tooltip">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                data-bs-toggle="dropdown"
                                                title="More Actions">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="batches.php?course_id=<?php echo $course['course_id']; ?>">
                                                    <i class="fas fa-calendar-alt me-2"></i>Manage Batches
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="../course-detail.php?id=<?php echo $course['course_id']; ?>" 
                                                   target="_blank">
                                                    <i class="fas fa-eye me-2"></i>View on Site
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="duplicate">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fas fa-copy me-2"></i>Duplicate Course
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" 
                                                        onclick="toggleCourseStatus(<?php echo $course['course_id']; ?>, <?php echo $course['is_active'] ? '0' : '1'; ?>)">
                                                    <i class="fas fa-<?php echo $course['is_active'] ? 'pause' : 'play'; ?> me-2"></i>
                                                    <?php echo $course['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" 
                                                        onclick="deleteCourse(<?php echo $course['course_id']; ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo buildPageUrl($page - 1); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo buildPageUrl($i); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo buildPageUrl($page + 1); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </form>
</main>

<!-- Course Modal -->
<div class="modal fade" id="courseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="courseForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-book me-2"></i>
                        <?php echo $editCourse ? 'Edit Course' : 'Add New Course'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editCourse ? 'edit' : 'add'; ?>">
                    <?php if ($editCourse): ?>
                        <input type="hidden" name="course_id" value="<?php echo $editCourse['course_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Course Name <span class="text-danger">*</span></label>
                            <input type="text" name="course_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editCourse['course_name'] ?? ''); ?>"
                                   maxlength="200">
                            <div class="form-text">Maximum 200 characters</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" name="course_code" class="form-control" required
                                   value="<?php echo htmlspecialchars($editCourse['course_code'] ?? ''); ?>"
                                   <?php echo $editCourse ? 'readonly' : ''; ?>
                                   pattern="[A-Z0-9-]+" title="Only uppercase letters, numbers and hyphens"
                                   maxlength="20">
                            <div class="form-text">Auto-generated if left empty</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>"
                                            <?php echo (isset($editCourse) && $editCourse['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Students</label>
                            <input type="number" name="max_students" class="form-control" min="1" max="100"
                                   value="<?php echo $editCourse['max_students'] ?? '20'; ?>">
                            <div class="form-text">Maximum capacity per batch</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_months" class="form-control" required min="1" max="24"
                                   value="<?php echo $editCourse['duration_months'] ?? '3'; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Course Image</label>
                            <div class="input-group">
                                <input type="file" name="course_image" class="form-control" accept="image/*" 
                                       id="imageInput" onchange="previewImage(this)">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearImage()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="form-text">Recommended: 800x600px, JPG/PNG, max 5MB</div>
                            
                            <?php if ($editCourse && !empty($editCourse['course_image'])): 
                                $imagePath = '../' . $editCourse['course_image'];
                                $imageExists = file_exists($imagePath);
                            ?>
                                <div class="mt-2" id="currentImageContainer">
                                    <div class="position-relative d-inline-block">
                                        <img src="<?php echo $imageExists ? $imagePath : 'https://via.placeholder.com/200x150/2c3e50/ffffff?text=No+Image'; ?>" 
                                             alt="Current image" class="img-thumbnail" 
                                             style="width: 200px; height: 150px; object-fit: cover;"
                                             id="currentImagePreview">
                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" 
                                                onclick="removeCurrentImage()" 
                                                style="transform: translate(50%, -50%);">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Current image</small>
                                </div>
                            <?php endif; ?>
                            
                            <div id="imagePreviewContainer" class="mt-2" style="display: none;">
                                <div class="position-relative d-inline-block">
                                    <img id="imagePreview" class="img-thumbnail" 
                                         style="width: 200px; height: 150px; object-fit: cover;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" 
                                            onclick="clearImagePreview()" 
                                            style="transform: translate(50%, -50%);">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">New image preview</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fee Amount (Rs.)</label>
                                    <input type="number" name="fee_amount" class="form-control" min="0" step="100"
                                           value="<?php echo $editCourse['fee_amount'] ?? '0'; ?>"
                                           id="feeAmount">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Course Type</label>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="is_free" id="isFree" 
                                            value="1" <?php echo isset($editCourse) && $editCourse['is_free'] ? 'checked' : ''; ?>
                                            onchange="toggleFreeCourse(this)">
                                        <label class="form-check-label" for="isFree">
                                            Mark as Free Course
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-select">
                                        <option value="1" <?php echo isset($editCourse) && $editCourse['is_active'] ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo isset($editCourse) && !$editCourse['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prerequisites</label>
                                    <input type="text" name="prerequisites" class="form-control"
                                           value="<?php echo htmlspecialchars($editCourse['prerequisites'] ?? ''); ?>"
                                           placeholder="e.g., Basic computer knowledge">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" required
                                  maxlength="1000"><?php echo htmlspecialchars($editCourse['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            <span id="descriptionCounter">0</span>/1000 characters
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Requirements</label>
                            <textarea name="requirements" class="form-control" rows="3"
                                      placeholder="What students need before starting this course"><?php echo htmlspecialchars($editCourse['requirements'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Learning Outcomes</label>
                            <textarea name="learning_outcomes" class="form-control" rows="3"
                                      placeholder="What students will learn"><?php echo htmlspecialchars($editCourse['learning_outcomes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Syllabus (Course Outline)</label>
                        <textarea name="syllabus" class="form-control" rows="5"
                                  placeholder="Detailed course outline, topics covered, etc."><?php echo htmlspecialchars($editCourse['syllabus'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        <?php echo $editCourse ? 'Update Course' : 'Add Course'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Import Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Download the template CSV file, fill in your courses, and upload it here.
                </div>
                <div class="mb-3">
                    <label class="form-label">Download Template</label>
                    <a href="template/courses_template.csv" class="btn btn-outline-primary w-100" download>
                        <i class="fas fa-download me-2"></i>Download CSV Template
                    </a>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload CSV File</label>
                    <input type="file" class="form-control" accept=".csv">
                    <div class="form-text">File must be in CSV format</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Import Courses</button>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($editCourse): ?>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('courseModal')).show();
});
<?php endif; ?>

// Helper function to build sort URLs
function buildSortUrl(sortField) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || 'created_at';
    const currentOrder = urlParams.get('order') || 'desc';
    
    let newOrder = 'desc';
    if (sortField === currentSort) {
        newOrder = currentOrder === 'desc' ? 'asc' : 'desc';
    }
    
    urlParams.set('sort', sortField);
    urlParams.set('order', newOrder);
    
    return urlParams.toString();
}

function buildPageUrl(page) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page);
    return urlParams.toString();
}

// Bulk actions
document.getElementById('selectAll').addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.course-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
});

document.getElementById('selectAllCheckbox').addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.course-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
    document.getElementById('selectAll').checked = e.target.checked;
});

function submitBulkForm() {
    const action = document.getElementById('bulkAction').value;
    const selected = document.querySelectorAll('.course-checkbox:checked');
    
    if (!action) {
        alert('Please select a bulk action');
        return;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one course');
        return;
    }
    
    const actionText = action === 'delete' ? 'delete' : action === 'deactivate' ? 'deactivate' : 'activate';
    
    if (!confirm(`Are you sure you want to ${actionText} ${selected.length} course(s)?`)) {
        return;
    }
    
    document.getElementById('bulkForm').submit();
}

// Individual actions
function toggleCourseStatus(courseId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this course?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'course_id';
        idInput.value = courseId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteCourse(courseId) {
    if (confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'course_id';
        idInput.value = courseId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-generate course code
document.querySelector('input[name="course_name"]')?.addEventListener('blur', function() {
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const courseName = this.value.trim();
    
    if (courseName && !courseCodeInput.readOnly && !courseCodeInput.value) {
        const letters = courseName.replace(/[^a-z]/gi, '').substring(0, 3).toUpperCase();
        const date = new Date();
        const year = date.getFullYear().toString().substring(2);
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const random = Math.floor(Math.random() * 900 + 100);
        
        courseCodeInput.value = letters + year + month + random;
    }
});

// Toggle free course
function toggleFreeCourse(checkbox) {
    const feeAmount = document.getElementById('feeAmount');
    if (checkbox.checked) {
        feeAmount.value = '0';
        feeAmount.readOnly = true;
        feeAmount.classList.add('bg-light', 'text-muted');
    } else {
        feeAmount.readOnly = false;
        feeAmount.classList.remove('bg-light', 'text-muted');
        if (!feeAmount.value) feeAmount.value = '0';
    }
}

// Initialize free course toggle
document.addEventListener('DOMContentLoaded', function() {
    const freeCheckbox = document.getElementById('isFree');
    const feeAmount = document.getElementById('feeAmount');
    if (freeCheckbox && freeCheckbox.checked) {
        feeAmount.readOnly = true;
        feeAmount.classList.add('bg-light', 'text-muted');
    }
});

// Image preview
function previewImage(input) {
    const file = input.files[0];
    if (file) {
        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Please upload an image (JPG, PNG, GIF, WebP).');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const preview = document.getElementById('imagePreview');
            const currentImageContainer = document.getElementById('currentImageContainer');
            
            if (currentImageContainer) {
                currentImageContainer.style.display = 'none';
            }
            
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

function clearImage() {
    const input = document.getElementById('imageInput');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const currentImageContainer = document.getElementById('currentImageContainer');
    
    input.value = '';
    
    if (previewContainer) {
        previewContainer.style.display = 'none';
    }
    
    if (currentImageContainer) {
        currentImageContainer.style.display = 'block';
    }
}

function clearImagePreview() {
    const previewContainer = document.getElementById('imagePreviewContainer');
    const input = document.getElementById('imageInput');
    const currentImageContainer = document.getElementById('currentImageContainer');
    
    if (previewContainer) {
        previewContainer.style.display = 'none';
    }
    
    input.value = '';
    
    if (currentImageContainer) {
        currentImageContainer.style.display = 'block';
    }
}

function removeCurrentImage() {
    const currentImageContainer = document.getElementById('currentImageContainer');
    const preview = document.getElementById('currentImagePreview');
    
    if (preview) {
        preview.src = 'https://via.placeholder.com/200x150/2c3e50/ffffff?text=No+Image';
    }
    
    if (currentImageContainer) {
        currentImageContainer.querySelector('button').style.display = 'none';
    }
}

// Character counter for description
const descriptionTextarea = document.querySelector('textarea[name="description"]');
const descriptionCounter = document.getElementById('descriptionCounter');

if (descriptionTextarea && descriptionCounter) {
    descriptionTextarea.addEventListener('input', function() {
        const length = this.value.length;
        descriptionCounter.textContent = length;
        
        if (length > 1000) {
            descriptionCounter.classList.add('text-danger');
        } else {
            descriptionCounter.classList.remove('text-danger');
        }
    });
    
    // Initialize counter
    if (descriptionTextarea.value) {
        descriptionCounter.textContent = descriptionTextarea.value.length;
    }
}

// Form validation
document.getElementById('courseForm')?.addEventListener('submit', function(e) {
    const courseName = this.querySelector('input[name="course_name"]').value.trim();
    const description = this.querySelector('textarea[name="description"]').value.trim();
    
    if (courseName.length < 3) {
        alert('Course name must be at least 3 characters long.');
        e.preventDefault();
        return;
    }
    
    if (description.length < 10) {
        alert('Description must be at least 10 characters long.');
        e.preventDefault();
        return;
    }
    
    // Check for duplicate course code (for new courses)
    if (!<?php echo $editCourse ? 'true' : 'false'; ?>) {
        const courseCode = this.querySelector('input[name="course_code"]').value;
        // You could add AJAX validation here
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-save draft (optional feature)
let saveDraftTimeout;
const formElements = document.querySelectorAll('#courseForm input, #courseForm textarea, #courseForm select');
formElements.forEach(element => {
    element.addEventListener('input', function() {
        clearTimeout(saveDraftTimeout);
        saveDraftTimeout = setTimeout(() => {
            // Save form data to localStorage as draft
            const formData = new FormData(document.getElementById('courseForm'));
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            localStorage.setItem('courseDraft', JSON.stringify(data));
            console.log('Draft saved');
        }, 2000);
    });
});

// Load draft (optional)
window.addEventListener('load', function() {
    const draft = localStorage.getItem('courseDraft');
    if (draft && !<?php echo $editCourse ? 'true' : 'false'; ?>) {
        if (confirm('You have an unsaved draft. Load it?')) {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element) {
                    element.value = data[key];
                }
            });
        }
    }
});
</script>

<?php 
// Helper function for building URLs
function buildSortUrl($field) {
    $params = $_GET;
    $currentSort = $params['sort'] ?? 'created_at';
    $currentOrder = $params['order'] ?? 'desc';
    
    $newOrder = 'desc';
    if ($field === $currentSort) {
        $newOrder = $currentOrder === 'desc' ? 'asc' : 'desc';
    }
    
    $params['sort'] = $field;
    $params['order'] = $newOrder;
    
    return http_build_query($params);
}

function buildPageUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return http_build_query($params);
}
?>

<?php require_once 'includes/footer.php'; ?>