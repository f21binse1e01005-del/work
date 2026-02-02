<?php
$pageTitle = 'Course Materials';
session_start();
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is logged in
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$batch_id = $_GET['batch_id'] ?? null;
$lesson_id = $_GET['lesson_id'] ?? null;
$module_id = $_GET['module_id'] ?? null;
$action = $_GET['action'] ?? 'view';
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? 'all';
$sort = $_GET['sort'] ?? 'date_desc';

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $response = [];
    
    try {
        switch ($action) {
            case 'get_materials':
                $response = getMaterialsAjax($db, $user_id, $batch_id, $lesson_id, $search, $type_filter, $sort);
                break;
            case 'record_download':
                $response = recordDownload($db, $_GET['material_id'], $user_id);
                break;
            case 'get_material_details':
                $response = getMaterialDetails($db, $_GET['material_id'], $user_id);
                break;
            case 'mark_completed':
                $response = markMaterialCompleted($db, $_GET['material_id'], $user_id);
                break;
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Get enrolled classes with material counts
$stmt = $db->prepare("
    SELECT 
        b.batch_id, 
        b.batch_name, 
        c.course_name,
        c.course_code,
        c.course_image,
        (SELECT COUNT(*) FROM learning_materials lm
         JOIN lessons l ON lm.lesson_id = l.lesson_id
         JOIN modules m ON l.module_id = m.module_id
         JOIN batches b2 ON m.course_id = b2.course_id
         WHERE b2.batch_id = b.batch_id) as material_count
    FROM enrollment_applications ea 
    JOIN batches b ON ea.batch_id = b.batch_id 
    JOIN courses c ON b.course_id = c.course_id 
    WHERE ea.user_id = ? 
    AND ea.application_status = 'approved'
    AND b.status IN ('ongoing', 'upcoming')
    ORDER BY b.start_date DESC
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll();

// Get materials if batch selected
$materials = [];
$course_modules = [];
$course_lessons = [];
$batch_info = null;

if ($batch_id) {
    // Get batch information
    $stmt = $db->prepare("
        SELECT b.*, c.course_name, c.course_code, c.description as course_description,
               CONCAT(u.full_name, ' (', u.email, ')') as teacher_name
        FROM batches b
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN users u ON b.teacher_id = u.user_id
        WHERE b.batch_id = ?
    ");
    $stmt->execute([$batch_id]);
    $batch_info = $stmt->fetch();
    
    // Get course modules for navigation
    $stmt = $db->prepare("
        SELECT m.module_id, m.module_title, m.module_description, m.module_order
        FROM modules m
        WHERE m.course_id = (SELECT course_id FROM batches WHERE batch_id = ?)
        AND m.is_published = TRUE
        ORDER BY m.module_order
    ");
    $stmt->execute([$batch_id]);
    $course_modules = $stmt->fetchAll();
    
    // Get lessons if module selected
    if ($module_id) {
        $stmt = $db->prepare("
            SELECT l.lesson_id, l.lesson_title, l.lesson_type, l.lesson_order
            FROM lessons l
            WHERE l.module_id = ?
            AND l.is_published = TRUE
            ORDER BY l.lesson_order
        ");
        $stmt->execute([$module_id]);
        $course_lessons = $stmt->fetchAll();
    }
    
    // Get materials with filters
    $materials = getMaterials($db, $user_id, $batch_id, $lesson_id, $search, $type_filter, $sort);
}

// Get user's material progress
$material_progress = [];
if ($batch_id) {
    $stmt = $db->prepare("
        SELECT material_id, completed_date
        FROM material_progress
        WHERE user_id = ? 
        AND material_id IN (
            SELECT lm.material_id FROM learning_materials lm
            JOIN lessons l ON lm.lesson_id = l.lesson_id
            JOIN modules m ON l.module_id = m.module_id
            JOIN batches b ON m.course_id = b.course_id
            WHERE b.batch_id = ?
        )
    ");
    $stmt->execute([$user_id, $batch_id]);
    while ($row = $stmt->fetch()) {
        $material_progress[$row['material_id']] = $row['completed_date'];
    }
}
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h2"><i class="fas fa-book me-2"></i>Course Materials</h1>
        <?php if ($batch_id): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-primary" id="toggleViewBtn">
                    <i class="fas fa-th"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statsModal">
                    <i class="fas fa-chart-bar"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$batch_id): ?>
<!-- Class Selection View -->
<div class="row">
    <?php if (empty($classes)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Enrolled Classes</h5>
                    <p class="text-muted mb-4">You are not enrolled in any active classes</p>
                    <a href="courses.php" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Browse Courses
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">My Classes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($classes as $class): ?>
                            <a href="?batch_id=<?php echo $class['batch_id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex align-items-center">
                                    <?php if ($class['course_image']): ?>
                                        <img src="../uploads/courses/<?php echo htmlspecialchars($class['course_image']); ?>" 
                                             class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-primary d-flex align-items-center justify-content-center me-3" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-book text-white fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($class['batch_name']); ?></h6>
                                            <span class="badge bg-primary">
                                                <?php echo $class['material_count']; ?> materials
                                            </span>
                                        </div>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($class['course_name']); ?> (<?php echo htmlspecialchars($class['course_code']); ?>)</p>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted ms-3"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Classes:</span>
                        <strong><?php echo count($classes); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Materials:</span>
                        <strong><?php echo array_sum(array_column($classes, 'material_count')); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Active Classes:</span>
                        <strong><?php echo count($classes); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Completed:</span>
                        <strong>0</strong>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="location.href='my_courses.php'">
                            <i class="fas fa-graduation-cap me-2"></i>My Courses
                        </button>
                        <button class="btn btn-outline-success" onclick="location.href='assignments.php'">
                            <i class="fas fa-tasks me-2"></i>Assignments
                        </button>
                        <button class="btn btn-outline-info" onclick="location.href='schedule.php'">
                            <i class="fas fa-calendar me-2"></i>Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Materials View -->
<div class="row">
    <!-- Sidebar -->
    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Course Navigation</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="?batch_id=<?php echo $batch_id; ?>" 
                       class="list-group-item list-group-item-action <?php echo !$module_id ? 'active' : ''; ?>">
                        <i class="fas fa-th me-2"></i>All Materials
                    </a>
                    
                    <?php foreach ($course_modules as $module): ?>
                        <div class="list-group-item">
                            <div class="fw-bold mb-1">
                                <i class="fas fa-folder me-2"></i>
                                <?php echo htmlspecialchars($module['module_title']); ?>
                            </div>
                            
                            <div class="ps-4">
                                <a href="?batch_id=<?php echo $batch_id; ?>&module_id=<?php echo $module['module_id']; ?>" 
                                   class="d-block py-1 text-decoration-none <?php echo $module_id == $module['module_id'] && !$lesson_id ? 'text-primary fw-bold' : 'text-muted'; ?>">
                                    <i class="fas fa-layer-group me-1"></i>Module Materials
                                </a>
                                
                                <?php if ($module_id == $module['module_id'] && !empty($course_lessons)): ?>
                                    <?php foreach ($course_lessons as $lesson): ?>
                                        <a href="?batch_id=<?php echo $batch_id; ?>&lesson_id=<?php echo $lesson['lesson_id']; ?>" 
                                           class="d-block py-1 text-decoration-none <?php echo $lesson_id == $lesson['lesson_id'] ? 'text-primary fw-bold' : 'text-muted'; ?>">
                                            <i class="fas fa-file-alt me-1"></i>
                                            <?php echo htmlspecialchars($lesson['lesson_title']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form id="filterForm">
                    <div class="mb-3">
                        <label class="form-label">Material Type</label>
                        <select class="form-select" name="type" id="typeFilter">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="document" <?php echo $type_filter === 'document' ? 'selected' : ''; ?>>Documents</option>
                            <option value="presentation" <?php echo $type_filter === 'presentation' ? 'selected' : ''; ?>>Presentations</option>
                            <option value="template" <?php echo $type_filter === 'template' ? 'selected' : ''; ?>>Templates</option>
                            <option value="sample_file" <?php echo $type_filter === 'sample_file' ? 'selected' : ''; ?>>Sample Files</option>
                            <option value="reference" <?php echo $type_filter === 'reference' ? 'selected' : ''; ?>>References</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort" id="sortFilter">
                            <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="size_desc" <?php echo $sort === 'size_desc' ? 'selected' : ''; ?>>Largest First</option>
                            <option value="size_asc" <?php echo $sort === 'size_asc' ? 'selected' : ''; ?>>Smallest First</option>
                            <option value="downloads_desc" <?php echo $sort === 'downloads_desc' ? 'selected' : ''; ?>>Most Downloaded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showCompleted" checked>
                            <label class="form-check-label" for="showCompleted">
                                Show Completed
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showUncompleted" checked>
                            <label class="form-check-label" for="showUncompleted">
                                Show Uncompleted
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" id="applyFilters">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="col-lg-9">
        <!-- Batch Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($batch_info['batch_name']); ?></h4>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($batch_info['course_name']); ?></p>
                        <div class="d-flex gap-3">
                            <?php if ($batch_info['teacher_name']): ?>
                                <small><i class="fas fa-chalkboard-teacher me-1"></i><?php echo htmlspecialchars($batch_info['teacher_name']); ?></small>
                            <?php endif; ?>
                            <small><i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($batch_info['start_date'])); ?> - <?php echo date('M j, Y', strtotime($batch_info['end_date'])); ?></small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-<?php echo $batch_info['status'] === 'ongoing' ? 'success' : 'info'; ?> fs-6">
                            <?php echo ucfirst($batch_info['status']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Actions -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Search materials by name, description, or type..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="button" id="searchBtn">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-primary" id="downloadAllBtn">
                                <i class="fas fa-download me-1"></i>Download All
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-upload me-1"></i>Upload
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Materials Grid/List -->
        <div id="materialsContainer">
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading materials...</p>
            </div>
            
            <!-- Error State -->
            <div id="errorState" class="text-center py-5" style="display: none;">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h5 class="text-danger">Error Loading Materials</h5>
                <p id="errorMessage" class="text-muted"></p>
                <button class="btn btn-primary" id="retryBtn">
                    <i class="fas fa-redo me-1"></i>Retry
                </button>
            </div>
            
            <!-- Empty State -->
            <div id="emptyState" class="text-center py-5" style="display: none;">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No Materials Found</h5>
                <p class="text-muted">No course materials available for this class.</p>
                <?php if ($module_id || $lesson_id): ?>
                    <button class="btn btn-primary" onclick="location.href='?batch_id=<?php echo $batch_id; ?>'">
                        <i class="fas fa-th me-1"></i>View All Materials
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Materials Grid -->
            <div id="materialsGrid" class="row" style="display: none;">
                <!-- Dynamic content will be loaded here -->
            </div>
            
            <!-- Materials List -->
            <div id="materialsList" class="list-group" style="display: none;">
                <!-- Dynamic content will be loaded here -->
            </div>
            
            <!-- Pagination -->
            <nav id="paginationContainer" class="mt-4" style="display: none;">
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Dynamic pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Material Type</label>
                        <select class="form-select" name="material_type" required>
                            <option value="">Select type...</option>
                            <option value="document">Document</option>
                            <option value="presentation">Presentation</option>
                            <option value="template">Template</option>
                            <option value="sample_file">Sample File</option>
                            <option value="reference">Reference</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,.jpg,.jpeg,.png" required>
                        <div class="form-text">Maximum file size: 50MB</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Related Lesson (Optional)</label>
                        <select class="form-select" name="lesson_id">
                            <option value="">Select lesson...</option>
                            <?php foreach ($course_modules as $module): ?>
                                <optgroup label="<?php echo htmlspecialchars($module['module_title']); ?>">
                                    <?php 
                                    $stmt = $db->prepare("SELECT lesson_id, lesson_title FROM lessons WHERE module_id = ? AND is_published = TRUE ORDER BY lesson_order");
                                    $stmt->execute([$module['module_id']]);
                                    $lessons = $stmt->fetchAll();
                                    ?>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <option value="<?php echo $lesson['lesson_id']; ?>">
                                            <?php echo htmlspecialchars($lesson['lesson_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    <i class="fas fa-upload me-1"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stats Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Materials Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="statsContent">
                    <!-- Dynamic stats will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewTitle">Material Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Dynamic preview will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    let currentView = 'grid'; // 'grid' or 'list'
    let currentPage = 1;
    const itemsPerPage = 12;
    let totalMaterials = 0;
    let materialsData = [];
    let filteredData = [];
    
    // Initialize if batch is selected
    <?php if ($batch_id): ?>
        loadMaterials();
        setupEventListeners();
    <?php endif; ?>
    
    function loadMaterials() {
        showLoading();
        
        const params = new URLSearchParams({
            batch_id: <?php echo $batch_id; ?>,
            <?php if ($lesson_id): ?>lesson_id: <?php echo $lesson_id; ?>,<?php endif; ?>
            <?php if ($module_id): ?>module_id: <?php echo $module_id; ?>,<?php endif; ?>
            search: $('#searchInput').val(),
            type: $('#typeFilter').val(),
            sort: $('#sortFilter').val(),
            ajax: 1,
            action: 'get_materials'
        });
        
        $.ajax({
            url: 'materials.php?' + params.toString(),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    materialsData = response.materials || [];
                    filteredData = materialsData;
                    totalMaterials = filteredData.length;
                    
                    if (totalMaterials === 0) {
                        showEmptyState();
                    } else {
                        renderMaterials();
                        setupPagination();
                        showContentView();
                    }
                } else {
                    showError(response.error || 'Failed to load materials');
                }
            },
            error: function() {
                hideLoading();
                showError('Network error. Please check your connection.');
            }
        });
    }
    
    function renderMaterials() {
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filteredData.slice(start, end);
        
        if (currentView === 'grid') {
            renderGridView(pageData);
        } else {
            renderListView(pageData);
        }
        
        updatePaginationInfo();
    }
    
    function renderGridView(materials) {
        const container = $('#materialsGrid');
        container.empty();
        
        materials.forEach(material => {
            const card = createMaterialCard(material);
            container.append(card);
        });
        
        $('#materialsGrid').show();
        $('#materialsList').hide();
    }
    
    function renderListView(materials) {
        const container = $('#materialsList');
        container.empty();
        
        materials.forEach(material => {
            const listItem = createMaterialListItem(material);
            container.append(listItem);
        });
        
        $('#materialsList').show();
        $('#materialsGrid').hide();
    }
    
    function createMaterialCard(material) {
        const icon = getMaterialIcon(material.material_type);
        const isCompleted = material.completed_date !== null;
        const progressClass = isCompleted ? 'completed' : 'uncompleted';
        
        return `
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card h-100 material-card ${progressClass}" data-material-id="${material.material_id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="material-icon ${getIconColorClass(material.material_type)}">
                                <i class="${icon} fa-2x"></i>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item preview-btn" href="#" data-material-id="${material.material_id}">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item download-btn" href="#" data-material-id="${material.material_id}">
                                            <i class="fas fa-download me-2"></i>Download
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item mark-completed-btn" href="#" data-material-id="${material.material_id}">
                                            <i class="fas fa-check-circle me-2"></i>
                                            ${isCompleted ? 'Mark as Uncompleted' : 'Mark as Completed'}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <h6 class="card-title mb-2" title="${escapeHtml(material.file_name)}">
                            ${truncateText(escapeHtml(material.file_name), 40)}
                        </h6>
                        
                        <p class="card-text text-muted small mb-2">
                            ${material.lesson_title ? escapeHtml(material.lesson_title) : 'General Material'}
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-${getTypeBadgeClass(material.material_type)}">
                                ${escapeHtml(material.material_type)}
                            </span>
                            <small class="text-muted">
                                ${formatFileSize(material.file_size)}
                            </small>
                        </div>
                        
                        <div class="progress mb-2" style="height: 4px;">
                            <div class="progress-bar ${isCompleted ? 'bg-success' : 'bg-info'}" 
                                 style="width: ${isCompleted ? '100' : '0'}%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-download me-1"></i>
                                ${material.download_count || 0}
                            </small>
                            <small class="text-muted">
                                ${formatDate(material.upload_date)}
                            </small>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-top-0 pt-0">
                        <div class="d-grid">
                            <button class="btn btn-sm btn-primary download-btn" data-material-id="${material.material_id}">
                                <i class="fas fa-download me-1"></i>Download
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function createMaterialListItem(material) {
        const icon = getMaterialIcon(material.material_type);
        const isCompleted = material.completed_date !== null;
        
        return `
            <div class="list-group-item list-group-item-action" data-material-id="${material.material_id}">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="${icon} fa-2x ${getIconColorClass(material.material_type)}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-1">${escapeHtml(material.file_name)}</h6>
                            <span class="badge bg-${getTypeBadgeClass(material.material_type)}">
                                ${escapeHtml(material.material_type)}
                            </span>
                        </div>
                        <p class="mb-1 text-muted small">
                            ${material.lesson_title ? 'Lesson: ' + escapeHtml(material.lesson_title) : 'General Material'}
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-download me-1"></i>
                                ${material.download_count || 0} downloads • 
                                ${formatFileSize(material.file_size)} • 
                                Uploaded: ${formatDate(material.upload_date)}
                            </small>
                            <div>
                                <span class="badge bg-${isCompleted ? 'success' : 'secondary'} me-2">
                                    ${isCompleted ? 'Completed' : 'Not Started'}
                                </span>
                                <button class="btn btn-sm btn-outline-primary preview-btn me-1" 
                                        data-material-id="${material.material_id}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary download-btn" 
                                        data-material-id="${material.material_id}">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function setupPagination() {
        const totalPages = Math.ceil(totalMaterials / itemsPerPage);
        const pagination = $('#pagination');
        pagination.empty();
        
        if (totalPages <= 1) {
            $('#paginationContainer').hide();
            return;
        }
        
        $('#paginationContainer').show();
        
        // Previous button
        pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `);
        
        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        
        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        // Next button
        pagination.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `);
    }
    
    function updatePaginationInfo() {
        const start = (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(start + itemsPerPage - 1, totalMaterials);
        
        // Update page info if needed
    }
    
    function setupEventListeners() {
        // Search
        $('#searchBtn').click(function() {
            currentPage = 1;
            loadMaterials();
        });
        
        $('#searchInput').on('keypress', function(e) {
            if (e.which === 13) {
                currentPage = 1;
                loadMaterials();
            }
        });
        
        $('#clearSearch').click(function() {
            $('#searchInput').val('');
            currentPage = 1;
            loadMaterials();
        });
        
        // Filters
        $('#applyFilters').click(function() {
            currentPage = 1;
            loadMaterials();
        });
        
        // Toggle view
        $('#toggleViewBtn').click(function() {
            currentView = currentView === 'grid' ? 'list' : 'grid';
            $(this).find('i').toggleClass('fa-th fa-list');
            renderMaterials();
        });
        
        // Refresh
        $('#refreshBtn').click(function() {
            $(this).find('i').addClass('fa-spin');
            loadMaterials();
            setTimeout(() => {
                $('#refreshBtn').find('i').removeClass('fa-spin');
            }, 1000);
        });
        
        // Download all
        $('#downloadAllBtn').click(function() {
            downloadAllMaterials();
        });
        
        // Upload
        $('#uploadBtn').click(function() {
            uploadMaterial();
        });
        
        // Pagination
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) {
                currentPage = page;
                renderMaterials();
                setupPagination();
                $('html, body').animate({
                    scrollTop: $('#materialsContainer').offset().top - 20
                }, 500);
            }
        });
        
        // Material actions
        $(document).on('click', '.download-btn', function(e) {
            e.preventDefault();
            const materialId = $(this).data('material-id');
            downloadMaterial(materialId);
        });
        
        $(document).on('click', '.preview-btn', function(e) {
            e.preventDefault();
            const materialId = $(this).data('material-id');
            previewMaterial(materialId);
        });
        
        $(document).on('click', '.mark-completed-btn', function(e) {
            e.preventDefault();
            const materialId = $(this).data('material-id');
            toggleMaterialCompletion(materialId);
        });
        
        // Retry button
        $('#retryBtn').click(function() {
            loadMaterials();
        });
    }
    
    function downloadMaterial(materialId) {
        const material = materialsData.find(m => m.material_id == materialId);
        if (!material) return;
        
        // Record download
        $.ajax({
            url: `materials.php?ajax=1&action=record_download&material_id=${materialId}`,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    // Update download count locally
                    const materialCard = $(`.material-card[data-material-id="${materialId}"]`);
                    const downloadCount = materialCard.find('.fa-download').parent();
                    const currentCount = parseInt(downloadCount.text().trim()) || 0;
                    downloadCount.text(currentCount + 1);
                    
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = material.file_path;
                    link.download = material.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast('Download started successfully!', 'success');
                }
            }
        });
    }
    
    function downloadAllMaterials() {
        if (materialsData.length === 0) {
            showToast('No materials to download', 'warning');
            return;
        }
        
        if (!confirm(`Download all ${materialsData.length} materials? This may take a while.`)) {
            return;
        }
        
        showToast('Preparing download...', 'info');
        
        // In a real application, you would create a zip file server-side
        // For now, we'll trigger individual downloads
        materialsData.forEach((material, index) => {
            setTimeout(() => {
                const link = document.createElement('a');
                link.href = material.file_path;
                link.download = material.file_name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, index * 100); // Stagger downloads
        });
    }
    
    function previewMaterial(materialId) {
        $.ajax({
            url: `materials.php?ajax=1&action=get_material_details&material_id=${materialId}`,
            type: 'GET',
            success: function(response) {
                if (response.success && response.material) {
                    const material = response.material;
                    const previewContent = getPreviewContent(material);
                    
                    $('#previewTitle').text(`Preview: ${material.file_name}`);
                    $('#previewContent').html(previewContent);
                    $('#previewModal').modal('show');
                }
            }
        });
    }
    
    function toggleMaterialCompletion(materialId) {
        $.ajax({
            url: `materials.php?ajax=1&action=mark_completed&material_id=${materialId}`,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    // Update UI
                    const materialCard = $(`.material-card[data-material-id="${materialId}"]`);
                    const btn = materialCard.find('.mark-completed-btn');
                    
                    if (response.completed) {
                        materialCard.removeClass('uncompleted').addClass('completed');
                        btn.html('<i class="fas fa-check-circle me-2"></i>Mark as Uncompleted');
                        materialCard.find('.progress-bar')
                            .removeClass('bg-info')
                            .addClass('bg-success')
                            .css('width', '100%');
                    } else {
                        materialCard.removeClass('completed').addClass('uncompleted');
                        btn.html('<i class="fas fa-check-circle me-2"></i>Mark as Completed');
                        materialCard.find('.progress-bar')
                            .removeClass('bg-success')
                            .addClass('bg-info')
                            .css('width', '0%');
                    }
                    
                    showToast(response.message, 'success');
                }
            }
        });
    }
    
    function uploadMaterial() {
        const formData = new FormData($('#uploadForm')[0]);
        
        $('#uploadBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Uploading...');
        
        $.ajax({
            url: 'upload_material.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i>Upload');
                
                if (response.success) {
                    $('#uploadModal').modal('hide');
                    showToast('Material uploaded successfully!', 'success');
                    loadMaterials();
                } else {
                    showToast(response.error || 'Upload failed', 'danger');
                }
            },
            error: function() {
                $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i>Upload');
                showToast('Network error. Please try again.', 'danger');
            }
        });
    }
    
    // Helper functions
    function getMaterialIcon(type) {
        const icons = {
            'document': 'fas fa-file-pdf',
            'presentation': 'fas fa-file-powerpoint',
            'template': 'fas fa-file-alt',
            'sample_file': 'fas fa-file-code',
            'reference': 'fas fa-book'
        };
        return icons[type] || 'fas fa-file';
    }
    
    function getIconColorClass(type) {
        const colors = {
            'document': 'text-danger',
            'presentation': 'text-warning',
            'template': 'text-primary',
            'sample_file': 'text-info',
            'reference': 'text-success'
        };
        return colors[type] || 'text-secondary';
    }
    
    function getTypeBadgeClass(type) {
        const classes = {
            'document': 'danger',
            'presentation': 'warning',
            'template': 'primary',
            'sample_file': 'info',
            'reference': 'success'
        };
        return classes[type] || 'secondary';
    }
    
    function getPreviewContent(material) {
        const extension = material.file_name.split('.').pop().toLowerCase();
        const previewable = ['pdf', 'jpg', 'jpeg', 'png', 'txt'];
        
        if (previewable.includes(extension)) {
            if (extension === 'pdf') {
                return `
                    <div class="text-center">
                        <iframe src="${material.file_path}" width="100%" height="600" style="border: none;"></iframe>
                    </div>
                `;
            } else if (['jpg', 'jpeg', 'png'].includes(extension)) {
                return `
                    <div class="text-center">
                        <img src="${material.file_path}" class="img-fluid" alt="${material.file_name}">
                    </div>
                `;
            } else if (extension === 'txt') {
                return `
                    <div class="border rounded p-3 bg-light">
                        <pre class="mb-0">${escapeHtml(material.content || 'Content not available')}</pre>
                    </div>
                `;
            }
        }
        
        return `
            <div class="text-center py-5">
                <i class="${getMaterialIcon(material.material_type)} fa-5x ${getIconColorClass(material.material_type)} mb-3"></i>
                <h5>Preview Not Available</h5>
                <p class="text-muted">This file type cannot be previewed in the browser.</p>
                <a href="${material.file_path}" class="btn btn-primary" download>
                    <i class="fas fa-download me-1"></i>Download File
                </a>
            </div>
        `;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showLoading() {
        $('#loadingState').show();
        $('#errorState').hide();
        $('#emptyState').hide();
        $('#materialsGrid').hide();
        $('#materialsList').hide();
        $('#paginationContainer').hide();
    }
    
    function hideLoading() {
        $('#loadingState').hide();
    }
    
    function showError(message) {
        $('#errorMessage').text(message);
        $('#errorState').show();
    }
    
    function showEmptyState() {
        $('#emptyState').show();
        $('#materialsGrid').hide();
        $('#materialsList').hide();
        $('#paginationContainer').hide();
    }
    
    function showContentView() {
        $('#errorState').hide();
        $('#emptyState').hide();
    }
    
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" 
                 style="position: fixed; bottom: 20px; right: 20px; z-index: 1050;">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
});
</script>

<style>
.material-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #dee2e6;
}

.material-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.material-card.completed {
    border-left: 4px solid #198754;
}

.material-card.uncompleted {
    border-left: 4px solid #0dcaf0;
}

.material-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.text-danger-bg {
    background-color: rgba(220,53,69,0.1);
}

.text-warning-bg {
    background-color: rgba(255,193,7,0.1);
}

.text-primary-bg {
    background-color: rgba(13,110,253,0.1);
}

.text-info-bg {
    background-color: rgba(13,202,240,0.1);
}

.text-success-bg {
    background-color: rgba(25,135,84,0.1);
}

.list-group-item {
    transition: all 0.2s;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.progress {
    background-color: #e9ecef;
}

.toast {
    min-width: 300px;
}

@media (max-width: 768px) {
    .material-card .dropdown {
        position: static;
    }
    
    .material-card .dropdown-menu {
        position: absolute;
        right: 10px;
        left: auto;
    }
}
</style>

<?php
// ======================
// AJAX FUNCTION DEFINITIONS
// ======================

function getMaterialsAjax($db, $user_id, $batch_id, $lesson_id, $search, $type_filter, $sort) {
    $conditions = ["ea.user_id = ?", "ea.application_status = 'approved'", "b.batch_id = ?"];
    $params = [$user_id, $batch_id];
    
    // Build query based on filters
    $query = "
        SELECT 
            lm.material_id,
            lm.material_type,
            lm.file_name,
            lm.file_path,
            lm.file_size,
            lm.mime_type,
            lm.download_count,
            lm.upload_date,
            l.lesson_id,
            l.lesson_title,
            m.module_id,
            m.module_title,
            up.full_name as uploaded_by_name,
            mp.completed_date
        FROM learning_materials lm
        JOIN lessons l ON lm.lesson_id = l.lesson_id
        JOIN modules m ON l.module_id = m.module_id
        JOIN batches b ON m.course_id = b.course_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        LEFT JOIN users up ON lm.uploaded_by = up.user_id
        LEFT JOIN material_progress mp ON lm.material_id = mp.material_id AND mp.user_id = ?
        WHERE " . implode(" AND ", $conditions);
    
    array_unshift($params, $user_id); // Add user_id for progress join
    
    if ($lesson_id) {
        $query .= " AND lm.lesson_id = ?";
        $params[] = $lesson_id;
    } elseif ($module_id) {
        $query .= " AND l.module_id = ?";
        $params[] = $module_id;
    }
    
    if ($type_filter !== 'all') {
        $query .= " AND lm.material_type = ?";
        $params[] = $type_filter;
    }
    
    if ($search) {
        $query .= " AND (lm.file_name LIKE ? OR lm.material_type LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Add sorting
    $orderBy = "ORDER BY ";
    switch ($sort) {
        case 'date_asc': $orderBy .= "lm.upload_date ASC"; break;
        case 'name_asc': $orderBy .= "lm.file_name ASC"; break;
        case 'name_desc': $orderBy .= "lm.file_name DESC"; break;
        case 'size_asc': $orderBy .= "lm.file_size ASC"; break;
        case 'size_desc': $orderBy .= "lm.file_size DESC"; break;
        case 'downloads_desc': $orderBy .= "lm.download_count DESC"; break;
        default: $orderBy .= "lm.upload_date DESC"; break;
    }
    
    $query .= " " . $orderBy;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
    
    return ['success' => true, 'materials' => $materials];
}

function recordDownload($db, $material_id, $user_id) {
    // Verify user has access to this material
    $stmt = $db->prepare("
        SELECT 1 FROM learning_materials lm
        JOIN lessons l ON lm.lesson_id = l.lesson_id
        JOIN modules m ON l.module_id = m.module_id
        JOIN batches b ON m.course_id = b.course_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        WHERE lm.material_id = ? AND ea.user_id = ?
    ");
    $stmt->execute([$material_id, $user_id]);
    
    if ($stmt->fetch()) {
        // Increment download count
        $stmt = $db->prepare("UPDATE learning_materials SET download_count = download_count + 1 WHERE material_id = ?");
        $stmt->execute([$material_id]);
        
        // Log download activity
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address)
            VALUES (?, 'material_download', ?, ?)
        ");
        $activity_details = json_encode(['material_id' => $material_id]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$user_id, $activity_details, $ip_address]);
        
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => 'Access denied'];
}

function getMaterialDetails($db, $material_id, $user_id) {
    $stmt = $db->prepare("
        SELECT 
            lm.*,
            l.lesson_title,
            m.module_title,
            up.full_name as uploaded_by_name,
            u.email as uploaded_by_email,
            b.batch_name,
            c.course_name
        FROM learning_materials lm
        JOIN lessons l ON lm.lesson_id = l.lesson_id
        JOIN modules m ON l.module_id = m.module_id
        JOIN batches b ON m.course_id = b.course_id
        JOIN courses c ON b.course_id = c.course_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        LEFT JOIN users up ON lm.uploaded_by = up.user_id
        LEFT JOIN users u ON lm.uploaded_by = u.user_id
        WHERE lm.material_id = ? AND ea.user_id = ?
    ");
    $stmt->execute([$material_id, $user_id]);
    $material = $stmt->fetch();
    
    if ($material) {
        // Try to read file content for preview if it's a text file
        if (in_array(pathinfo($material['file_name'], PATHINFO_EXTENSION), ['txt', 'md', 'csv'])) {
            if (file_exists($material['file_path'])) {
                $material['content'] = file_get_contents($material['file_path']);
            }
        }
        return ['success' => true, 'material' => $material];
    }
    
    return ['success' => false, 'error' => 'Material not found'];
}

function markMaterialCompleted($db, $material_id, $user_id) {
    // Check if already marked as completed
    $stmt = $db->prepare("SELECT completed_date FROM material_progress WHERE material_id = ? AND user_id = ?");
    $stmt->execute([$material_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Remove completion
        $stmt = $db->prepare("DELETE FROM material_progress WHERE material_id = ? AND user_id = ?");
        $stmt->execute([$material_id, $user_id]);
        return [
            'success' => true,
            'completed' => false,
            'message' => 'Material marked as uncompleted'
        ];
    } else {
        // Add completion
        $stmt = $db->prepare("INSERT INTO material_progress (material_id, user_id, completed_date) VALUES (?, ?, NOW())");
        $stmt->execute([$material_id, $user_id]);
        return [
            'success' => true,
            'completed' => true,
            'message' => 'Material marked as completed'
        ];
    }
}

// Helper function for main page
function getMaterials($db, $user_id, $batch_id, $lesson_id, $search, $type_filter, $sort) {
    $conditions = ["ea.user_id = ?", "ea.application_status = 'approved'", "b.batch_id = ?"];
    $params = [$user_id, $batch_id];
    
    $query = "
        SELECT 
            lm.material_id,
            lm.material_type,
            lm.file_name,
            lm.file_path,
            lm.file_size,
            lm.mime_type,
            lm.download_count,
            lm.upload_date,
            l.lesson_title,
            m.module_title,
            up.full_name as uploaded_by_name
        FROM learning_materials lm
        JOIN lessons l ON lm.lesson_id = l.lesson_id
        JOIN modules m ON l.module_id = m.module_id
        JOIN batches b ON m.course_id = b.course_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        LEFT JOIN users up ON lm.uploaded_by = up.user_id
        WHERE " . implode(" AND ", $conditions);
    
    if ($lesson_id) {
        $query .= " AND lm.lesson_id = ?";
        $params[] = $lesson_id;
    }
    
    if ($type_filter !== 'all') {
        $query .= " AND lm.material_type = ?";
        $params[] = $type_filter;
    }
    
    if ($search) {
        $query .= " AND (lm.file_name LIKE ? OR lm.material_type LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Add sorting
    $orderBy = "ORDER BY ";
    switch ($sort) {
        case 'date_asc': $orderBy .= "lm.upload_date ASC"; break;
        case 'name_asc': $orderBy .= "lm.file_name ASC"; break;
        case 'name_desc': $orderBy .= "lm.file_name DESC"; break;
        case 'size_asc': $orderBy .= "lm.file_size ASC"; break;
        case 'size_desc': $orderBy .= "lm.file_size DESC"; break;
        case 'downloads_desc': $orderBy .= "lm.download_count DESC"; break;
        default: $orderBy .= "lm.upload_date DESC"; break;
    }
    
    $query .= " " . $orderBy;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
?>