<?php
$pageTitle = 'Available Courses';
$pageIcon = 'fas fa-book-open';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Handle Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    try {
        $course_id = $_POST['course_id'];
        $batch_id = !empty($_POST['batch_id']) ? $_POST['batch_id'] : null;
        
        // Check if already enrolled
        $stmt = $db->prepare("SELECT application_id FROM enrollment_applications WHERE user_id = ? AND course_id = ? AND application_status != 'rejected'");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) {
            throw new Exception("You have already applied or are enrolled in this course.");
        }
        
        // Create application
        $stmt = $db->prepare("INSERT INTO enrollment_applications (user_id, course_id, batch_id, application_status) VALUES (?, ?, ?, 'submitted')");
        $stmt->execute([$user_id, $course_id, $batch_id]);
        
        $_SESSION['success_message'] = 'Enrollment application submitted successfully!';
        header("Location: dashboard.php");
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch Categories
$stmt = $db->prepare("SELECT * FROM course_categories WHERE is_active = 1 ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build Query
$query = "
    SELECT c.*, cc.category_name,
           (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.course_id AND b.status = 'upcoming') as upcoming_batches_count
    FROM courses c
    LEFT JOIN course_categories cc ON c.category_id = cc.category_id
    WHERE c.is_active = 1
";

$params = [];

// Filter by Category
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
if ($category_filter !== 'all') {
    $query .= " AND c.category_id = ?";
    $params[] = $category_filter;
}

// Search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_query)) {
    $query .= " AND (c.course_name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY c.course_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch upcoming batches for each course
$batches = [];
if (!empty($courses)) {
    $course_ids = array_column($courses, 'course_id');
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    
    $stmt = $db->prepare("SELECT * FROM batches WHERE course_id IN ($placeholders) AND status = 'upcoming' ORDER BY start_date");
    $stmt->execute($course_ids);
    $all_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_batches as $batch) {
        $batches[$batch['course_id']][] = $batch;
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-gray-800 mb-0">Available Courses</h1>
            <p class="text-muted small mb-0">Browse and enroll in our professional courses</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body bg-white rounded">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Search courses..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        Filter
                    </button>
                </div>
                <?php if ($category_filter !== 'all' || !empty($search_query)): ?>
                <div class="col-md-2">
                    <a href="available-courses.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Course Grid -->
    <?php if (empty($courses)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="fas fa-search fa-3x text-muted opacity-50"></i>
            </div>
            <h4 class="text-muted">No courses found</h4>
            <p class="text-muted">Try adjusting your search or filters</p>
            <a href="available-courses.php" class="btn btn-outline-primary mt-2">View All Courses</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($courses as $course): ?>
            <div class="col-xl-4 col-md-6 mb-4 fade-in">
                <div class="card h-100 course-card border-0 shadow-sm hover-lift">
                    <div class="position-relative">
                        <!-- Course Image Placeholder -->
                        <div class="course-image-wrapper bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                            <i class="fas fa-graduation-cap fa-3x text-primary opacity-25"></i>
                        </div>
                        <div class="category-badge position-absolute top-0 end-0 m-3">
                            <span class="badge bg-primary bg-opacity-90 rounded-pill shadow-sm">
                                <?php echo htmlspecialchars($course['category_name']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2">
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($course['course_code']); ?>
                            </small>
                        </div>
                        <h5 class="card-title fw-bold mb-2">
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </h5>
                        <p class="card-text text-muted small mb-3 flex-grow-1">
                            <?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3 text-sm">
                            <span class="text-muted">
                                <i class="far fa-clock me-1"></i> <?php echo $course['duration_months']; ?> Months
                            </span>
                            <span class="fw-bold text-primary">
                                <?php echo number_format($course['fee_amount'], 0); ?> PKR
                            </span>
                        </div>
                        
                        <div class="mt-auto">
                            <?php if (isset($batches[$course['course_id']])): ?>
                                <button type="button" class="btn btn-outline-primary w-100" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#enrollModal<?php echo $course['course_id']; ?>">
                                    Enroll Now
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary w-100" disabled>
                                    No Upcoming Batches
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Modal -->
            <div class="modal fade" id="enrollModal<?php echo $course['course_id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-user-plus me-2"></i>Enroll in <?php echo htmlspecialchars($course['course_name']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body p-4">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                
                                <p class="text-muted mb-4">Please select a batch to start your learning journey.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Select Batch</label>
                                    <div class="list-group">
                                        <?php if (isset($batches[$course['course_id']])): ?>
                                            <?php foreach ($batches[$course['course_id']] as $batch): ?>
                                            <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center cursor-pointer">
                                                <div class="d-flex align-items-center">
                                                    <input class="form-check-input me-3" type="radio" name="batch_id" 
                                                           value="<?php echo $batch['batch_id']; ?>" required>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Starts: <?php echo date('M d, Y', strtotime($batch['start_date'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo $batch['max_students'] - $batch['current_students']; ?> seats left
                                                </span>
                                            </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info bg-opacity-10 py-2">
                                    <small><i class="fas fa-info-circle me-1"></i> Your application will be reviewed by the admin.</small>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary px-4">Submit Application</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Animation for cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.fade-in');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
