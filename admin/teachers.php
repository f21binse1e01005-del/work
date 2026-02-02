<?php
/**
 * Teachers Management System - Professional Enhanced Version
 * File: admin/teachers.php
 * Enhanced with better security, performance, and UX
 */

session_start();
require_once '../config/database.php';
require_once 'includes/security.php';
require_once 'includes/helpers.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login-test.php');
    exit;
}

// Check permission
if (!hasPermission('manage_teachers')) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}

$db = (new Database())->getConnection();

// Initialize variables
$response = ['success' => false, 'message' => '', 'data' => []];
$filters = [
    'search' => cleanInput($_GET['search'] ?? ''),
    'status' => cleanInput($_GET['status'] ?? ''),
    'qualification' => cleanInput($_GET['qualification'] ?? ''),
    'experience' => cleanInput($_GET['experience'] ?? ''),
    'sort_by' => cleanInput($_GET['sort_by'] ?? 'name'),
    'sort_order' => cleanInput($_GET['sort_order'] ?? 'asc')
];
$page = validatePageNumber($_GET['page'] ?? 1);
$limit = getSetting('records_per_page', 12);
$offset = ($page - 1) * $limit;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    switch ($action) {
        case 'bulk_action':
            handleBulkAction($db, $_POST);
            break;
        case 'quick_edit':
            handleQuickEdit($db, $_POST);
            break;
        case 'get_stats':
            echo json_encode(getTeacherStatistics($db));
            exit;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// Handle regular POST actions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Security token invalid'];
    } else {
        switch ($action) {
            case 'delete':
                softDeleteTeacher($db, $teacher_id);
                break;
            case 'activate':
                activateTeacher($db, $teacher_id);
                break;
            case 'export':
                exportTeachers($db, $filters);
                break;
        }
    }
}

// Build filter conditions
$whereConditions = ["u.user_type = 'teacher'", "u.deleted_at IS NULL"];
$params = [];
$paramTypes = '';

if ($filters['search']) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.cnic LIKE ? OR up.qualifications LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $paramTypes .= 'sssss';
}

if ($filters['status'] && in_array($filters['status'], ['active', 'inactive', 'suspended', 'pending_verification'])) {
    $whereConditions[] = "u.account_status = ?";
    $params[] = $filters['status'];
    $paramTypes .= 's';
}

if ($filters['qualification']) {
    $whereConditions[] = "up.education_level = ?";
    $params[] = $filters['qualification'];
    $paramTypes .= 's';
}

if ($filters['experience'] && is_numeric($filters['experience'])) {
    $whereConditions[] = "up.years_experience >= ?";
    $params[] = $filters['experience'];
    $paramTypes .= 'i';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get sorting
$sortOptions = [
    'name' => 'u.full_name',
    'date' => 'u.created_at',
    'status' => 'u.account_status',
    'experience' => 'up.years_experience'
];
$sortField = $sortOptions[$filters['sort_by']] ?? 'u.full_name';
$sortOrder = strtoupper($filters['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
$orderClause = "ORDER BY $sortField $sortOrder, u.user_id DESC";

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id $whereClause";
$countStmt = executeQuery($db, $countQuery, $params, $paramTypes);
$totalTeachers = $countStmt->fetchColumn();
$totalPages = ceil($totalTeachers / $limit);

// Get teachers with pagination
$query = "
    SELECT 
        u.*,
        up.*,
        u.user_id as id,
        DATE_FORMAT(u.created_at, '%Y-%m-%d') as join_date,
        TIMESTAMPDIFF(YEAR, up.date_of_birth, CURDATE()) as age,
        (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id AND status = 'ongoing') as active_batches,
        (SELECT COUNT(DISTINCT course_id) FROM batches WHERE teacher_id = u.user_id) as courses_taught,
        (SELECT COUNT(*) FROM enrollments e 
         LEFT JOIN enrollment_applications ea ON e.application_id = ea.application_id
         LEFT JOIN batches b ON ea.batch_id = b.batch_id 
         WHERE b.teacher_id = u.user_id AND e.enrollment_status = 'active') as total_students,
        (SELECT AVG(rating) FROM teacher_feedback WHERE teacher_id = u.user_id) as avg_rating,
        (SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = u.user_id) as feedback_count,
        (SELECT GROUP_CONCAT(course_code SEPARATOR ', ') 
         FROM batches b 
         JOIN courses c ON b.course_id = c.course_id 
         WHERE b.teacher_id = u.user_id AND b.status = 'ongoing' 
         LIMIT 3) as current_courses
    FROM users u 
    LEFT JOIN user_profiles up ON u.user_id = up.user_id 
    $whereClause
    $orderClause
    LIMIT ? OFFSET ?
";

array_push($params, $limit, $offset);
$paramTypes .= 'ii';

$stmt = executeQuery($db, $query, $params, $paramTypes);
$teachers = $stmt->fetchAll();

// Get statistics
$stats = getTeacherStatistics($db);

// Get available qualifications for filter
$qualificationsStmt = $db->query("SELECT DISTINCT education_level FROM user_profiles WHERE education_level IS NOT NULL AND education_level != ''");
$qualifications = $qualificationsStmt->fetchAll(PDO::FETCH_COLUMN);



require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" id="teachers-management">
    <!-- Page Header -->
    <div class="page-header py-3 mb-4 border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="icon-wrapper bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="fas fa-chalkboard-teacher fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h1 class="h3 mb-1">Teachers Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Teachers</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="export-teachers.php?format=excel&type=all"><i class="fas fa-file-excel me-2 text-success"></i> Export All to Excel</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportWithFilters('pdf')"><i class="fas fa-file-pdf me-2 text-danger"></i> Export Filtered to PDF</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportSelectedTeachers('csv')"><i class="fas fa-file-csv me-2 text-info"></i> Export Selected to CSV</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export-teachers.php?format=json&type=all"><i class="fas fa-code me-2"></i> Export to JSON</a></li>
                            <li><a class="dropdown-item" href="export-teachers.php?format=xml&type=all"><i class="fas fa-file-code me-2"></i> Export to XML</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="fas fa-print me-2"></i> Print List</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-columns me-1"></i> Columns
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="columnToggle">
                            <li class="dropdown-item">
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column="contact" checked>
                                    <label class="form-check-label">Contact Info</label>
                                </div>
                            </li>
                            <li class="dropdown-item">
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column="qualifications" checked>
                                    <label class="form-check-label">Qualifications</label>
                                </div>
                            </li>
                            <li class="dropdown-item">
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column="stats" checked>
                                    <label class="form-check-label">Statistics</label>
                                </div>
                            </li>
                            <li class="dropdown-item">
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column="rating" checked>
                                    <label class="form-check-label">Rating</label>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <a href="add-teacher.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Add Teacher
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Total Teachers</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-success"><?php echo number_format($stats['active']); ?> Active</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-success bg-opacity-10 text-success me-3">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Active Batches</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['active_batches']); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-info"><?php echo number_format($stats['total_batches']); ?> Total</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Total Students</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Across all teachers</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Avg. Rating</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="star-rating small">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($stats['avg_rating'] ?? 0) ? ' text-warning' : ' text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Pending</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['pending']); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="?status=pending_verification" class="btn btn-sm btn-warning">Review</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-secondary bg-opacity-10 text-secondary me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">This Month</h6>
                            <h3 class="mb-0">+<?php echo number_format($stats['new_this_month']); ?></h3>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">New teachers</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="card filter-card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filters & Search
                <button class="btn btn-sm btn-link float-end" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                    Advanced <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" id="filterForm" class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search teachers..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>" id="searchInput">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <select name="status" class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="pending_verification" <?php echo $filters['status'] === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                    </select>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <select name="qualification" class="form-select" id="qualificationFilter">
                        <option value="">All Qualifications</option>
                        <?php foreach ($qualifications as $qual): ?>
                            <option value="<?php echo htmlspecialchars($qual); ?>" <?php echo $filters['qualification'] === $qual ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($qual); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
                
                <!-- Advanced Filters (Collapsed) -->
                <div class="collapse col-12" id="advancedFilters">
                    <div class="row mt-3 pt-3 border-top">
                        <div class="col-md-4">
                            <label class="form-label">Experience (Years)</label>
                            <select name="experience" class="form-select">
                                <option value="">Any Experience</option>
                                <option value="1" <?php echo $filters['experience'] == '1' ? 'selected' : ''; ?>>1+ Years</option>
                                <option value="3" <?php echo $filters['experience'] == '3' ? 'selected' : ''; ?>>3+ Years</option>
                                <option value="5" <?php echo $filters['experience'] == '5' ? 'selected' : ''; ?>>5+ Years</option>
                                <option value="10" <?php echo $filters['experience'] == '10' ? 'selected' : ''; ?>>10+ Years</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Sort By</label>
                            <select name="sort_by" class="form-select">
                                <option value="name" <?php echo $filters['sort_by'] === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="date" <?php echo $filters['sort_by'] === 'date' ? 'selected' : ''; ?>>Join Date</option>
                                <option value="status" <?php echo $filters['sort_by'] === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="experience" <?php echo $filters['sort_by'] === 'experience' ? 'selected' : ''; ?>>Experience</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Sort Order</label>
                            <select name="sort_order" class="form-select">
                                <option value="asc" <?php echo $filters['sort_order'] === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="desc" <?php echo $filters['sort_order'] === 'desc' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Active Filters -->
            <?php if ($filters['search'] || $filters['status'] || $filters['qualification']): ?>
            <div class="mt-3">
                <div class="d-flex flex-wrap gap-2">
                    <small class="text-muted">Active filters:</small>
                    <?php if ($filters['search']): ?>
                        <span class="badge bg-info">
                            Search: "<?php echo htmlspecialchars($filters['search']); ?>"
                            <button type="button" class="btn-close btn-close-white ms-1" style="font-size: 0.6rem;" onclick="removeFilter('search')"></button>
                        </span>
                    <?php endif; ?>
                    <?php if ($filters['status']): ?>
                        <span class="badge bg-primary">
                            Status: <?php echo ucfirst(str_replace('_', ' ', $filters['status'])); ?>
                            <button type="button" class="btn-close btn-close-white ms-1" style="font-size: 0.6rem;" onclick="removeFilter('status')"></button>
                        </span>
                    <?php endif; ?>
                    <?php if ($filters['qualification']): ?>
                        <span class="badge bg-success">
                            Qualification: <?php echo htmlspecialchars($filters['qualification']); ?>
                            <button type="button" class="btn-close btn-close-white ms-1" style="font-size: 0.6rem;" onclick="removeFilter('qualification')"></button>
                        </span>
                    <?php endif; ?>
                    <a href="teachers.php" class="badge bg-secondary text-decoration-none">
                        Clear All <i class="fas fa-times ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="card mb-4 d-none" id="bulkActionsBar">
        <div class="card-body py-2">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="selectAllTeachers">
                        <label class="form-check-label" for="selectAllTeachers">
                            Select All
                        </label>
                    </div>
                    <span class="me-3" id="selectedCountText">0 teachers selected</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" id="bulkAction" style="width: auto;">
                        <option value="">Choose action...</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        <option value="assign_course">Assign to Course</option>
                        <option value="send_email">Send Email</option>
                        <option value="export_selected">Export Selected</option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="applyBulkAction()" id="applyBulkActionBtn" disabled>
                        Apply
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers Grid/Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Teachers List</h5>
                <small class="text-muted">
                    Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $totalTeachers); ?> of <?php echo number_format($totalTeachers); ?> teachers
                </small>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active" id="viewGridBtn" onclick="toggleView('grid')">
                    <i class="fas fa-th-large"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="viewTableBtn" onclick="toggleView('table')">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
        
        <!-- Grid View -->
        <div class="card-body" id="gridView">
            <?php if (empty($teachers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chalkboard-teacher fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No teachers found</h4>
                    <p class="text-muted">
                        <?php echo $filters['search'] ? 'Try adjusting your search or filters' : 'Add your first teacher to get started'; ?>
                    </p>
                    <?php if (!$filters['search']): ?>
                        <a href="add-teacher.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Add Teacher
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    <?php foreach ($teachers as $teacher): 
                        $statusColor = getStatusColor($teacher['account_status']);
                        $rating = $teacher['avg_rating'] ?? 0;
                    ?>
                    <div class="col">
                        <div class="card teacher-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-lg me-3">
                                            <?php if (!empty($teacher['profile_picture'])): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                                     class="rounded-circle" alt="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                                                    <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($teacher['full_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($teacher['username']); ?></small>
                                        </div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input teacher-select" type="checkbox" value="<?php echo $teacher['id']; ?>">
                                    </div>
                                </div>
                                
                                <div class="teacher-info mb-3">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Email</small>
                                            <a href="mailto:<?php echo htmlspecialchars($teacher['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($teacher['email']); ?>
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Phone</small>
                                            <?php echo $teacher['phone'] ? formatPhone($teacher['phone']) : 'N/A'; ?>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Experience</small>
                                            <span class="fw-bold"><?php echo $teacher['years_experience'] ?? 0; ?> yrs</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Qualification</small>
                                            <span><?php echo htmlspecialchars($teacher['education_level'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Stats -->
                                <div class="teacher-stats d-flex justify-content-around border-top border-bottom py-2 mb-3">
                                    <div class="text-center">
                                        <div class="fw-bold text-primary"><?php echo $teacher['active_batches']; ?></div>
                                        <small class="text-muted">Batches</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-success"><?php echo $teacher['courses_taught']; ?></div>
                                        <small class="text-muted">Courses</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-info"><?php echo $teacher['total_students']; ?></div>
                                        <small class="text-muted">Students</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-warning"><?php echo number_format($rating, 1); ?></div>
                                        <small class="text-muted">Rating</small>
                                    </div>
                                </div>
                                
                                <!-- Status & Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $teacher['account_status'])); ?>
                                    </span>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" onclick="viewTeacher(<?php echo $teacher['id']; ?>)" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="edit-teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="teacher-schedule.php?id=<?php echo $teacher['id']; ?>">
                                                        <i class="fas fa-calendar-alt me-2"></i>View Schedule
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="teacher-feedback.php?id=<?php echo $teacher['id']; ?>">
                                                        <i class="fas fa-star me-2"></i>View Feedback
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if ($teacher['account_status'] === 'active'): ?>
                                                    <li>
                                                        <button class="dropdown-item text-danger" onclick="changeStatus(<?php echo $teacher['id']; ?>, 'deactivate')">
                                                            <i class="fas fa-ban me-2"></i>Deactivate
                                                        </button>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <button class="dropdown-item text-success" onclick="changeStatus(<?php echo $teacher['id']; ?>, 'activate')">
                                                            <i class="fas fa-check me-2"></i>Activate
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Table View (Hidden by default) -->
        <div class="table-responsive d-none" id="tableView">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllTable">
                            </div>
                        </th>
                        <th>Teacher</th>
                        <th class="contact-column">Contact</th>
                        <th class="qualifications-column">Qualifications</th>
                        <th class="stats-column">Statistics</th>
                        <th class="rating-column">Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): 
                        $statusColor = getStatusColor($teacher['account_status']);
                        $rating = $teacher['avg_rating'] ?? 0;
                    ?>
                    <tr data-teacher-id="<?php echo $teacher['id']; ?>">
                        <td>
                            <div class="form-check">
                                <input class="form-check-input teacher-select" type="checkbox" value="<?php echo $teacher['id']; ?>">
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($teacher['profile_picture'])): ?>
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($teacher['profile_picture']); ?>" 
                                         class="rounded-circle me-2" width="32" height="32" alt="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                                <?php else: ?>
                                    <div class="avatar-placeholder bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                         style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($teacher['full_name']); ?></div>
                                    <small class="text-muted">Joined <?php echo date('M Y', strtotime($teacher['join_date'])); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="contact-column">
                            <div>
                                <small class="d-block">
                                    <i class="fas fa-envelope text-muted me-1"></i>
                                    <?php echo htmlspecialchars($teacher['email']); ?>
                                </small>
                                <small class="d-block">
                                    <i class="fas fa-phone text-muted me-1"></i>
                                    <?php echo $teacher['phone'] ? formatPhone($teacher['phone']) : 'N/A'; ?>
                                </small>
                            </div>
                        </td>
                        <td class="qualifications-column">
                            <div>
                                <small class="d-block"><?php echo htmlspecialchars($teacher['education_level'] ?? 'N/A'); ?></small>
                                <small class="text-muted">
                                    <?php echo $teacher['years_experience'] ?? 0; ?> yrs experience
                                </small>
                            </div>
                        </td>
                        <td class="stats-column">
                            <div class="d-flex gap-3">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo $teacher['active_batches']; ?></div>
                                    <small class="text-muted">Batches</small>
                                </div>
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo $teacher['total_students']; ?></div>
                                    <small class="text-muted">Students</small>
                                </div>
                            </div>
                        </td>
                        <td class="rating-column">
                            <div class="star-rating" title="<?php echo number_format($rating, 1); ?> stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= floor($rating) ? ' text-warning' : ($i == ceil($rating) && fmod($rating, 1) >= 0.5 ? ' fa-star-half-alt text-warning' : ' text-muted'); ?>"></i>
                                <?php endfor; ?>
                                <small class="text-muted ms-1">(<?php echo $teacher['feedback_count'] ?? 0; ?>)</small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $teacher['account_status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-info" onclick="viewTeacher(<?php echo $teacher['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="edit-teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($teacher['account_status'] === 'active'): ?>
                                    <button class="btn btn-outline-warning" onclick="changeStatus(<?php echo $teacher['id']; ?>, 'deactivate')" title="Deactivate">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-success" onclick="changeStatus(<?php echo $teacher['id']; ?>, 'activate')" title="Activate">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <span class="me-3">Rows per page:</span>
                        <select class="form-select form-select-sm w-auto" onchange="changePageSize(this.value)">
                            <option value="12" <?php echo $limit == 12 ? 'selected' : ''; ?>>12</option>
                            <option value="24" <?php echo $limit == 24 ? 'selected' : ''; ?>>24</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-end mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo buildQueryString(['page' => 1]); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo buildQueryString(['page' => $i]); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo buildQueryString(['page' => $totalPages]); ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modals -->
<?php include 'includes/modals/teacher-quick-edit.php'; ?>
<?php include 'includes/modals/bulk-email.php'; ?>
<?php include 'includes/modals/assign-course.php'; ?>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Teacher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="quickViewContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmationBody">
                Are you sure you want to perform this action?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
    <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<style>
/* Enhanced Styles */
.teacher-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e9ecef;
}

.teacher-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.card-stats {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.card-stats:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.icon-wrapper {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.star-rating {
    color: #ffc107;
}

.filter-card {
    border: 1px solid #dee2e6;
}

.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.page-link {
    color: #495057;
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Print Styles */
@media print {
    .btn, .dropdown, .pagination, .card-header .btn-group, 
    #bulkActionsBar, .filter-card, .page-header .btn-toolbar {
        display: none !important;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .table {
        font-size: 11px;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-stats .icon-circle {
        display: none;
    }
    
    .teacher-stats {
        flex-wrap: wrap;
    }
    
    .teacher-stats > div {
        flex: 1 0 45%;
        margin-bottom: 0.5rem;
    }
}
</style>

<script>
// Global variables
const csrfToken = '<?php echo generateCSRFToken(); ?>';
let selectedTeachers = new Set();
let currentView = 'grid';

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeTeachersPage();
    loadStats(); // Load real-time stats
    
    // Auto-save view preference
    const savedView = localStorage.getItem('teachers_view') || 'grid';
    if (savedView) {
        toggleView(savedView);
    }
    
    // Search with debounce
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 2 || this.value.length === 0) {
                document.getElementById('filterForm').submit();
            }
        }, 500);
    });
});

// Initialize page functionality
function initializeTeachersPage() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
    
    // Initialize checkboxes
    document.querySelectorAll('.teacher-select').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });
    
    document.getElementById('selectAllTeachers').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.teacher-select');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateSelection();
    });
    
    document.getElementById('selectAllTable').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.teacher-select');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateSelection();
    });
    
    // Column toggles
    document.querySelectorAll('.column-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const column = this.dataset.column;
            const elements = document.querySelectorAll(`.${column}-column`);
            elements.forEach(el => {
                el.style.display = this.checked ? '' : 'none';
            });
            localStorage.setItem(`column_${column}`, this.checked);
        });
        
        // Restore column preferences
        const saved = localStorage.getItem(`column_${toggle.dataset.column}`);
        if (saved !== null) {
            toggle.checked = saved === 'true';
            toggle.dispatchEvent(new Event('change'));
        }
    });
}

// Toggle between grid and table view
function toggleView(view) {
    currentView = view;
    localStorage.setItem('teachers_view', view);
    
    const gridView = document.getElementById('gridView');
    const tableView = document.getElementById('tableView');
    const gridBtn = document.getElementById('viewGridBtn');
    const tableBtn = document.getElementById('viewTableBtn');
    
    if (view === 'grid') {
        gridView.classList.remove('d-none');
        tableView.classList.add('d-none');
        gridBtn.classList.add('active');
        tableBtn.classList.remove('active');
    } else {
        gridView.classList.add('d-none');
        tableView.classList.remove('d-none');
        gridBtn.classList.remove('active');
        tableBtn.classList.add('active');
    }
}

// Update selection count
function updateSelection() {
    selectedTeachers.clear();
    document.querySelectorAll('.teacher-select:checked').forEach(cb => {
        selectedTeachers.add(cb.value);
    });
    
    const count = selectedTeachers.size;
    document.getElementById('selectedCountText').textContent = `${count} teacher${count !== 1 ? 's' : ''} selected`;
    
    const bulkBar = document.getElementById('bulkActionsBar');
    const applyBtn = document.getElementById('applyBulkActionBtn');
    
    if (count > 0) {
        bulkBar.classList.remove('d-none');
        applyBtn.disabled = false;
    } else {
        bulkBar.classList.add('d-none');
        applyBtn.disabled = true;
    }
    
    // Update select all checkboxes
    const totalCheckboxes = document.querySelectorAll('.teacher-select').length;
    const checkedCount = document.querySelectorAll('.teacher-select:checked').length;
    
    document.getElementById('selectAllTeachers').checked = checkedCount > 0 && checkedCount === totalCheckboxes;
    document.getElementById('selectAllTeachers').indeterminate = checkedCount > 0 && checkedCount < totalCheckboxes;
    
    document.getElementById('selectAllTable').checked = checkedCount > 0 && checkedCount === totalCheckboxes;
    document.getElementById('selectAllTable').indeterminate = checkedCount > 0 && checkedCount < totalCheckboxes;
}

// Clear selection
function clearSelection() {
    document.querySelectorAll('.teacher-select').forEach(cb => cb.checked = false);
    document.getElementById('selectAllTeachers').checked = false;
    document.getElementById('selectAllTable').checked = false;
    updateSelection();
}

// Change teacher status
function changeStatus(teacherId, action) {
    const actionText = action === 'activate' ? 'activate' : 'deactivate';
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    
    document.getElementById('confirmationTitle').textContent = `Confirm ${actionText}`;
    document.getElementById('confirmationBody').innerHTML = `
        Are you sure you want to ${actionText} this teacher?<br><br>
        <small class="text-muted">This action will change their access to the system.</small>
    `;
    
    document.getElementById('confirmActionBtn').onclick = function() {
        performStatusChange(teacherId, action);
        modal.hide();
    };
    
    modal.show();
}

// Perform status change via AJAX
function performStatusChange(teacherId, action) {
    showLoading();
    
    fetch('teacher-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: action,
            teacher_id: teacherId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            // Reload the row or page
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('Network error occurred', 'danger');
        console.error('Error:', error);
    });
}

// Apply bulk action
function applyBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        showToast('Please select an action', 'warning');
        return;
    }
    
    if (selectedTeachers.size === 0) {
        showToast('Please select at least one teacher', 'warning');
        return;
    }
    
    switch (action) {
        case 'activate':
        case 'deactivate':
            bulkChangeStatus(action);
            break;
        case 'send_email':
            openBulkEmailModal();
            break;
        case 'assign_course':
            openAssignCourseModal();
            break;
        case 'export_selected':
            exportSelectedTeachers();
            break;
    }
}

// Bulk status change
function bulkChangeStatus(action) {
    const actionText = action === 'activate' ? 'activate' : 'deactivate';
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    
    document.getElementById('confirmationTitle').textContent = `Confirm Bulk ${actionText}`;
    document.getElementById('confirmationBody').innerHTML = `
        Are you sure you want to ${actionText} ${selectedTeachers.size} selected teacher(s)?<br><br>
        <small class="text-muted">This action will affect all selected teachers.</small>
    `;
    
    document.getElementById('confirmActionBtn').onclick = function() {
        performBulkStatusChange(action);
        modal.hide();
    };
    
    modal.show();
}

// Perform bulk status change
function performBulkStatusChange(action) {
    showLoading();
    
    fetch('teacher-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'bulk_' + action,
            teacher_ids: Array.from(selectedTeachers)
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// View teacher quick details
function viewTeacher(teacherId) {
    const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
    const content = document.getElementById('quickViewContent');
    
    content.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
    
    fetch(`get-teacher-details.php?id=${teacherId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
            modal.show();
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load teacher details</div>';
        });
}



// Filter utilities
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterForm').submit();
}

function removeFilter(filterName) {
    const url = new URL(window.location);
    url.searchParams.delete(filterName);
    window.location.href = url.toString();
}

function changePageSize(size) {
    const url = new URL(window.location);
    url.searchParams.set('limit', size);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

// Load real-time stats
function loadStats() {
    fetch('get-teacher-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.updated) {
                // Update stats cards dynamically
                updateStatsDisplay(data);
            }
        })
        .catch(console.error);
}

// Update stats display
function updateStatsDisplay(data) {
    // This would update the stats cards with new data
    // Implementation depends on your specific stats structure
}

// Show toast notification
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('successToast');
    const toastBody = document.getElementById('toastMessage');
    
    toastBody.textContent = message;
    
    // Update toast color
    const toastHeader = toastEl.querySelector('.toast-header');
    toastHeader.className = `toast-header bg-${type} text-white`;
    
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}

// Show loading overlay
function showLoading() {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        overlay.style.cssText = 'background: rgba(255,255,255,0.8); z-index: 9999;';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                <div class="mt-2">Loading...</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.remove();
}

// Auto-refresh every 30 seconds (optional)
setInterval(() => {
    if (!document.hidden) {
        loadStats();
    }
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch (e.key) {
            case 'f':
                e.preventDefault();
                document.getElementById('searchInput').focus();
                break;
            case 'a':
                e.preventDefault();
                clearSelection();
                break;
            case 'g':
                e.preventDefault();
                toggleView('grid');
                break;
            case 't':
                e.preventDefault();
                toggleView('table');
                break;
        }
    }
});

// Helper function to build query string
function buildQueryString(params) {
    const urlParams = new URLSearchParams(window.location.search);
    Object.entries(params).forEach(([key, value]) => {
        urlParams.set(key, value);
    });
    return urlParams.toString();
}
    // Export selected teachers
    function exportSelectedTeachers(format = 'excel') {
        const selectedIds = getSelectedTeacherIds();
        
        if (selectedIds.length === 0) {
            showToast('Warning', 'Please select teachers to export', 'warning');
            return;
        }
        
        // Use POST for many selections to avoid URL length limits
        if (selectedIds.length > 20) {
            document.getElementById('exportFormat').value = format;
            document.getElementById('exportSelectedIds').value = selectedIds.join(',');
            document.getElementById('exportForm').submit();
        } else {
            const url = `export-teachers.php?format=${format}&type=selected&selected_ids=${selectedIds.join(',')}`;
            window.open(url, '_blank');
        }
    }
    
    // Export with filters
    function exportWithFilters(format) {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        const qualification = document.getElementById('qualificationFilter').value;
        
        const params = new URLSearchParams({
            format: format,
            type: 'filtered',
            search: search,
            status: status,
            qualification: qualification
        });
        
        window.open(`export-teachers.php?${params.toString()}`, '_blank');
    }

    function getSelectedTeacherIds() {
        const checkboxes = document.querySelectorAll('.teacher-select:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }
</script>

<!-- Hidden Export Form -->
<form id="exportForm" method="POST" action="export-teachers.php" target="_blank" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="format" id="exportFormat" value="excel">
    <input type="hidden" name="type" value="selected">
    <input type="hidden" name="selected_ids" id="exportSelectedIds">
</form>

<?php
// Helper functions (would be in includes/functions.php)
function getStatusColor($status) {
    return match($status) {
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'pending_verification' => 'warning',
        default => 'secondary'
    };
}

function getTeacherStatistics($db) {
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN account_status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN account_status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN account_status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN account_status = 'pending_verification' THEN 1 ELSE 0 END) as pending,
            (SELECT COUNT(*) FROM batches WHERE teacher_id IN (SELECT user_id FROM users WHERE user_type = 'teacher') AND status = 'ongoing') as active_batches,
            (SELECT COUNT(*) FROM batches WHERE teacher_id IN (SELECT user_id FROM users WHERE user_type = 'teacher')) as total_batches,
            (SELECT COUNT(*) FROM enrollments e 
             JOIN enrollment_applications ea ON e.application_id = ea.application_id
             JOIN batches b ON ea.batch_id = b.batch_id 
             WHERE b.teacher_id IN (SELECT user_id FROM users WHERE user_type = 'teacher') 
             AND e.enrollment_status = 'active') as total_students,
            (SELECT AVG(rating) FROM teacher_feedback) as avg_rating,
            (SELECT COUNT(*) FROM users 
             WHERE user_type = 'teacher' 
             AND MONTH(created_at) = MONTH(CURRENT_DATE) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE)) as new_this_month
        FROM users 
        WHERE user_type = 'teacher' AND deleted_at IS NULL
    ";
    
    $stmt = $db->query($query);
    
    $defaults = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'suspended' => 0,
        'pending' => 0,
        'active_batches' => 0,
        'total_batches' => 0,
        'total_students' => 0,
        'avg_rating' => 0,
        'new_this_month' => 0
    ];

    if ($stmt) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return array_merge($defaults, $result);
        }
    }
    
    return $defaults;
}

function executeQuery($db, $query, $params = [], $types = '') {
    $stmt = $db->prepare($query);
    
    if (!empty($params)) {
        if (strlen($types) === count($params)) {
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindValue($i + 1, $params[$i], getPDOType($types[$i]));
            }
            $stmt->execute();
        } else {
            $stmt->execute($params);
        }
    } else {
        $stmt->execute();
    }
    
    return $stmt;
}

function getPDOType($type) {
    return match($type) {
        'i' => PDO::PARAM_INT,
        's' => PDO::PARAM_STR,
        'b' => PDO::PARAM_LOB,
        'n' => PDO::PARAM_NULL,
        default => PDO::PARAM_STR
    };
}

function formatPhone($phone) {
    if (empty($phone)) return 'N/A';
    return preg_replace('/(\d{4})(\d{3})(\d{4})/', '$1-$2-$3', $phone);
}

require_once 'includes/footer.php';
?>