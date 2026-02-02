<?php
$pageTitle = 'Classmates';
session_start(); // Ensure session is started
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
$action = $_GET['action'] ?? 'view';

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $response = [];
    
    try {
        if ($action == 'get_classmates') {
            $batch_id = $_GET['batch_id'] ?? null;
            if (!$batch_id) {
                throw new Exception('Batch ID is required');
            }
            
            // Get class info
            $stmt = $db->prepare("
                SELECT b.batch_name, c.course_name, c.course_code,
                       CONCAT(t.full_name, ' (', t.email, ')') as teacher_name,
                       b.start_date, b.end_date
                FROM batches b 
                JOIN courses c ON b.course_id = c.course_id
                LEFT JOIN users t ON b.teacher_id = t.user_id
                WHERE b.batch_id = ?
            ");
            $stmt->execute([$batch_id]);
            $class_info = $stmt->fetch();
            
            // Get classmates with additional info
            $stmt = $db->prepare("
                SELECT 
                    u.user_id, u.full_name, u.email, u.phone, u.profile_image,
                    up.date_of_birth, up.gender, up.education_level,
                    ea.application_date,
                    COUNT(DISTINCT m.message_id) as message_count
                FROM users u 
                JOIN enrollment_applications ea ON u.user_id = ea.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                LEFT JOIN messages m ON (u.user_id = m.sender_id AND m.receiver_id = ?) 
                    OR (u.user_id = m.receiver_id AND m.sender_id = ?)
                WHERE ea.batch_id = ? 
                    AND ea.application_status = 'approved' 
                    AND u.user_id != ?
                    AND u.account_status = 'active'
                GROUP BY u.user_id
                ORDER BY u.full_name
            ");
            $stmt->execute([$user_id, $user_id, $batch_id, $user_id]);
            $classmates = $stmt->fetchAll();
            
            // Get classmates count
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN up.gender = 'male' THEN 1 END) as males,
                    COUNT(CASE WHEN up.gender = 'female' THEN 1 END) as females
                FROM enrollment_applications ea
                JOIN users u ON ea.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                WHERE ea.batch_id = ? 
                    AND ea.application_status = 'approved'
                    AND u.account_status = 'active'
            ");
            $stmt->execute([$batch_id]);
            $stats = $stmt->fetch();
            
            $response = [
                'success' => true,
                'class_info' => $class_info,
                'classmates' => $classmates,
                'stats' => $stats
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}

// Get enrolled classes with additional info
$stmt = $db->prepare("
    SELECT 
        b.batch_id, b.batch_name, b.start_date, b.end_date, b.status,
        c.course_name, c.course_code, c.course_image,
        CONCAT(t.full_name, ' (', t.email, ')') as teacher_name,
        (SELECT COUNT(*) FROM enrollment_applications ea2 
         WHERE ea2.batch_id = b.batch_id AND ea2.application_status = 'approved') as total_students
    FROM enrollment_applications ea 
    JOIN batches b ON ea.batch_id = b.batch_id 
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN users t ON b.teacher_id = t.user_id
    WHERE ea.user_id = ? 
        AND ea.application_status = 'approved'
        AND b.status IN ('ongoing', 'upcoming')
    ORDER BY b.start_date DESC
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll();

// Get current batch info if selected
$current_class = null;
$classmates = [];
$class_stats = [];

if ($batch_id) {
    $stmt = $db->prepare("
        SELECT b.batch_name, c.course_name, c.course_code,
               CONCAT(t.full_name, ' (', t.email, ')') as teacher_name,
               b.start_date, b.end_date, b.classroom, b.schedule_details
        FROM batches b 
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN users t ON b.teacher_id = t.user_id
        WHERE b.batch_id = ?
    ");
    $stmt->execute([$batch_id]);
    $current_class = $stmt->fetch();
}
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h2"><i class="fas fa-users me-2"></i>Classmates</h1>
        <?php if ($batch_id && $current_class): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-primary" id="exportBtn">
                    <i class="fas fa-download me-1"></i>Export List
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="chatGroupBtn">
                    <i class="fas fa-comments me-1"></i>Group Chat
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>My Classes</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No enrolled classes found</p>
                        <a href="courses.php" class="btn btn-sm btn-primary mt-2">Browse Courses</a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($classes as $class): ?>
                            <a href="?batch_id=<?php echo $class['batch_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $batch_id == $class['batch_id'] ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($class['batch_name']); ?></h6>
                                    <small class="<?php echo $batch_id == $class['batch_id'] ? 'text-light' : 'text-muted'; ?>">
                                        <?php echo $class['total_students']; ?> students
                                    </small>
                                </div>
                                <p class="mb-1 small"><?php echo htmlspecialchars($class['course_name']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M Y', strtotime($class['start_date'])); ?> -
                                        <?php echo date('M Y', strtotime($class['end_date'])); ?>
                                    </small>
                                    <span class="badge bg-<?php echo $class['status'] == 'ongoing' ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst($class['status']); ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($batch_id && $current_class): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Class Info</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6><?php echo htmlspecialchars($current_class['course_name']); ?></h6>
                    <p class="text-muted small mb-1"><?php echo htmlspecialchars($current_class['batch_name']); ?></p>
                </div>
                
                <div class="row g-2">
                    <div class="col-6">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Course Code</small>
                            <strong><?php echo htmlspecialchars($current_class['course_code']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Duration</small>
                            <strong>
                                <?php echo date('M d, Y', strtotime($current_class['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($current_class['end_date'])); ?>
                            </strong>
                        </div>
                    </div>
                    <?php if ($current_class['teacher_name']): ?>
                    <div class="col-12">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Instructor</small>
                            <strong><?php echo htmlspecialchars($current_class['teacher_name']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($current_class['classroom']): ?>
                    <div class="col-12">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Classroom</small>
                            <strong><?php echo htmlspecialchars($current_class['classroom']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($current_class['schedule_details']): ?>
                    <div class="col-12">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Schedule</small>
                            <strong><?php echo htmlspecialchars($current_class['schedule_details']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-8">
        <?php if (!$batch_id): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Select a Class</h5>
                    <p class="text-muted mb-4">Choose a class from the list to view your classmates</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-primary" onclick="location.href='courses.php'">
                            <i class="fas fa-search me-1"></i>Browse Courses
                        </button>
                        <button class="btn btn-outline-primary" onclick="location.href='my_enrollments.php'">
                            <i class="fas fa-list me-1"></i>View Enrollments
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" id="className">
                            <i class="fas fa-users me-2"></i>
                            <?php echo htmlspecialchars($current_class['batch_name']); ?> - Classmates
                        </h5>
                        <div class="d-flex gap-2">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <input type="text" id="searchClassmates" class="form-control" placeholder="Search classmates...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleView" title="Toggle View">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBtn" title="Refresh">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Stats Row -->
                    <div class="row mb-4" id="classStats">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center py-3">
                                    <h4 class="mb-1" id="totalStudents">0</h4>
                                    <small>Total Classmates</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center py-3">
                                    <h4 class="mb-1" id="maleStudents">0</h4>
                                    <small>Male</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center py-3">
                                    <h4 class="mb-1" id="femaleStudents">0</h4>
                                    <small>Female</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center py-3">
                                    <h4 class="mb-1" id="onlineStudents">0</h4>
                                    <small>Online</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="loadingState" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading classmates...</p>
                    </div>
                    
                    <!-- Error State -->
                    <div id="errorState" class="text-center py-4" style="display: none;">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5 class="text-danger">Error Loading Data</h5>
                        <p id="errorMessage" class="text-muted"></p>
                        <button class="btn btn-primary" id="retryBtn">
                            <i class="fas fa-redo me-1"></i>Retry
                        </button>
                    </div>
                    
                    <!-- Empty State -->
                    <div id="emptyState" class="text-center py-5" style="display: none;">
                        <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Classmates Found</h5>
                        <p class="text-muted">You're the only student in this class or no classmates found.</p>
                    </div>
                    
                    <!-- Classmates Grid/List -->
                    <div id="classmatesContainer" class="row" style="display: none;">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" id="paginationContainer" style="display: none;">
                        <small class="text-muted" id="pageInfo">Showing 0 of 0 classmates</small>
                        <nav aria-label="Classmates pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination">
                                <!-- Pagination will be generated dynamically -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Classmate Details -->
<div class="modal fade" id="classmateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Classmate Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="classmateDetails">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendMessageBtn">
                    <i class="fas fa-envelope me-1"></i>Send Message
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    let currentBatchId = <?php echo $batch_id ?: 'null'; ?>;
    let classmatesData = [];
    let filteredData = [];
    let currentView = 'grid'; // 'grid' or 'list'
    let currentPage = 1;
    const itemsPerPage = 12;
    let selectedClassmate = null;
    
    // Initialize page
    if (currentBatchId) {
        loadClassmates();
        setupEventListeners();
    }
    
    function loadClassmates() {
        $('#loadingState').show();
        $('#classmatesContainer').hide();
        $('#emptyState').hide();
        $('#errorState').hide();
        $('#paginationContainer').hide();
        
        $.ajax({
            url: '?ajax=1&action=get_classmates&batch_id=' + currentBatchId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#loadingState').hide();
                
                if (response.success) {
                    classmatesData = response.classmates;
                    filteredData = classmatesData;
                    
                    // Update stats
                    if (response.stats) {
                        $('#totalStudents').text(response.stats.total);
                        $('#maleStudents').text(response.stats.males || 0);
                        $('#femaleStudents').text(response.stats.females || 0);
                        // Online count would come from a different source
                        $('#onlineStudents').text('N/A');
                    }
                    
                    // Update class info if needed
                    if (response.class_info) {
                        $('#className').html(
                            '<i class="fas fa-users me-2"></i>' + 
                            response.class_info.batch_name + ' - Classmates'
                        );
                    }
                    
                    if (classmatesData.length === 0) {
                        $('#emptyState').show();
                    } else {
                        renderClassmates();
                        setupPagination();
                        $('#classmatesContainer').show();
                        $('#paginationContainer').show();
                    }
                } else {
                    showError(response.error || 'Failed to load classmates');
                }
            },
            error: function() {
                $('#loadingState').hide();
                showError('Network error. Please check your connection.');
            }
        });
    }
    
    function renderClassmates() {
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filteredData.slice(start, end);
        
        const container = $('#classmatesContainer');
        container.empty();
        
        if (currentView === 'grid') {
            pageData.forEach(classmate => {
                const card = createClassmateCard(classmate);
                container.append(card);
            });
        } else {
            pageData.forEach(classmate => {
                const listItem = createClassmateListItem(classmate);
                container.append(listItem);
            });
        }
        
        updatePageInfo();
    }
    
    function createClassmateCard(classmate) {
        const profileImg = classmate.profile_image 
            ? `<img src="../uploads/profiles/${classmate.profile_image}" class="rounded-circle me-3" width="50" height="50" alt="${classmate.full_name}">`
            : `<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                  <i class="fas fa-user text-white"></i>
               </div>`;
        
        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            ${profileImg}
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(classmate.full_name)}</h6>
                                <p class="text-muted small mb-0">${escapeHtml(classmate.email)}</p>
                                ${classmate.phone ? `<small class="text-muted">${escapeHtml(classmate.phone)}</small>` : ''}
                            </div>
                            <button class="btn btn-sm btn-outline-primary view-profile-btn" 
                                    data-userid="${classmate.user_id}">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <div class="row g-2">
                            ${classmate.education_level ? `
                            <div class="col-12">
                                <small class="text-muted d-block">Education</small>
                                <small>${escapeHtml(classmate.education_level)}</small>
                            </div>` : ''}
                            
                            <div class="col-6">
                                <small class="text-muted d-block">Joined</small>
                                <small>${formatDate(classmate.application_date)}</small>
                            </div>
                            
                            <div class="col-6">
                                <small class="text-muted d-block">Messages</small>
                                <small>${classmate.message_count || 0}</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <button class="btn btn-sm btn-outline-primary send-msg-btn" 
                                    data-userid="${classmate.user_id}">
                                <i class="fas fa-envelope me-1"></i>Message
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" data-action="view-profile" data-userid="${classmate.user_id}">
                                            <i class="fas fa-user me-2"></i>View Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" data-action="send-message" data-userid="${classmate.user_id}">
                                            <i class="fas fa-envelope me-2"></i>Send Message
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#" data-action="view-activity" data-userid="${classmate.user_id}">
                                            <i class="fas fa-chart-line me-2"></i>View Activity
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function createClassmateListItem(classmate) {
        const profileImg = classmate.profile_image 
            ? `<img src="../uploads/profiles/${classmate.profile_image}" class="rounded-circle me-3" width="40" height="40" alt="${classmate.full_name}">`
            : `<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                  <i class="fas fa-user text-white fa-sm"></i>
               </div>`;
        
        return `
            <div class="col-12 mb-2">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center">
                            ${profileImg}
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">${escapeHtml(classmate.full_name)}</h6>
                                    <small class="text-muted">Joined: ${formatDate(classmate.application_date)}</small>
                                </div>
                                <p class="text-muted small mb-0">${escapeHtml(classmate.email)}</p>
                                ${classmate.education_level ? `<small class="text-muted">${escapeHtml(classmate.education_level)}</small>` : ''}
                            </div>
                            <div class="btn-group ms-3">
                                <button class="btn btn-sm btn-outline-primary view-profile-btn" 
                                        data-userid="${classmate.user_id}">
                                    <i class="fas fa-user me-1"></i>Profile
                                </button>
                                <button class="btn btn-sm btn-outline-success send-msg-btn" 
                                        data-userid="${classmate.user_id}">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function setupPagination() {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        const pagination = $('#pagination');
        pagination.empty();
        
        if (totalPages <= 1) {
            return;
        }
        
        // Previous button
        pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">«</a>
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
                <a class="page-link" href="#" data-page="${currentPage + 1}">»</a>
            </li>
        `);
    }
    
    function updatePageInfo() {
        const total = filteredData.length;
        const start = (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(start + itemsPerPage - 1, total);
        
        $('#pageInfo').text(`Showing ${start}-${end} of ${total} classmates`);
    }
    
    function setupEventListeners() {
        // Search functionality
        $('#searchClassmates').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            if (searchTerm.trim() === '') {
                filteredData = classmatesData;
            } else {
                filteredData = classmatesData.filter(classmate => 
                    classmate.full_name.toLowerCase().includes(searchTerm) ||
                    classmate.email.toLowerCase().includes(searchTerm) ||
                    (classmate.education_level && classmate.education_level.toLowerCase().includes(searchTerm))
                );
            }
            
            currentPage = 1;
            renderClassmates();
            setupPagination();
        });
        
        $('#clearSearch').click(function() {
            $('#searchClassmates').val('');
            filteredData = classmatesData;
            currentPage = 1;
            renderClassmates();
            setupPagination();
        });
        
        // Toggle view
        $('#toggleView').click(function() {
            currentView = currentView === 'grid' ? 'list' : 'grid';
            $(this).find('i').toggleClass('fa-th fa-list');
            $(this).attr('title', currentView === 'grid' ? 'List View' : 'Grid View');
            renderClassmates();
        });
        
        // Refresh
        $('#refreshBtn').click(function() {
            $(this).find('i').addClass('fa-spin');
            loadClassmates();
            setTimeout(() => {
                $('#refreshBtn').find('i').removeClass('fa-spin');
            }, 1000);
        });
        
        // Pagination click
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) {
                currentPage = page;
                renderClassmates();
                setupPagination();
                $('html, body').animate({
                    scrollTop: $('#classmatesContainer').offset().top - 20
                }, 500);
            }
        });
        
        // View profile
        $(document).on('click', '.view-profile-btn, [data-action="view-profile"]', function(e) {
            e.preventDefault();
            const userId = $(this).data('userid');
            viewClassmateProfile(userId);
        });
        
        // Send message
        $(document).on('click', '.send-msg-btn, [data-action="send-message"]', function(e) {
            e.preventDefault();
            const userId = $(this).data('userid');
            const classmate = classmatesData.find(c => c.user_id == userId);
            if (classmate) {
                window.location.href = `messages.php?compose=1&to=${userId}&name=${encodeURIComponent(classmate.full_name)}`;
            }
        });
        
        // Export list
        $('#exportBtn').click(function() {
            exportClassmatesList();
        });
        
        // Group chat
        $('#chatGroupBtn').click(function() {
            createGroupChat();
        });
        
        // Retry button
        $('#retryBtn').click(function() {
            loadClassmates();
        });
        
        // Send message from modal
        $('#sendMessageBtn').click(function() {
            if (selectedClassmate) {
                window.location.href = `messages.php?compose=1&to=${selectedClassmate.user_id}&name=${encodeURIComponent(selectedClassmate.full_name)}`;
            }
        });
    }
    
    function viewClassmateProfile(userId) {
        const classmate = classmatesData.find(c => c.user_id == userId);
        if (!classmate) return;
        
        selectedClassmate = classmate;
        
        const modalContent = `
            <div class="row">
                <div class="col-md-4 text-center">
                    ${classmate.profile_image 
                        ? `<img src="../uploads/profiles/${classmate.profile_image}" class="img-fluid rounded-circle mb-3" alt="${classmate.full_name}" style="max-width: 200px;">`
                        : `<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 200px; height: 200px;">
                              <i class="fas fa-user text-white fa-5x"></i>
                           </div>`
                    }
                    <h5>${escapeHtml(classmate.full_name)}</h5>
                    <p class="text-muted">Classmate</p>
                </div>
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Email</label>
                            <p class="mb-2">${escapeHtml(classmate.email)}</p>
                        </div>
                        ${classmate.phone ? `
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Phone</label>
                            <p class="mb-2">${escapeHtml(classmate.phone)}</p>
                        </div>` : ''}
                        
                        ${classmate.gender ? `
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Gender</label>
                            <p class="mb-2">${escapeHtml(classmate.gender)}</p>
                        </div>` : ''}
                        
                        ${classmate.date_of_birth ? `
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Date of Birth</label>
                            <p class="mb-2">${formatDate(classmate.date_of_birth)}</p>
                        </div>` : ''}
                        
                        ${classmate.education_level ? `
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Education Level</label>
                            <p class="mb-2">${escapeHtml(classmate.education_level)}</p>
                        </div>` : ''}
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Joined Class</label>
                            <p class="mb-2">${formatDate(classmate.application_date)}</p>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Message History</label>
                            <p class="mb-2">${classmate.message_count || 0} messages exchanged</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#classmateDetails').html(modalContent);
        $('#classmateModal').modal('show');
    }
    
    function exportClassmatesList() {
        const csvContent = "data:text/csv;charset=utf-8," + 
            "Name,Email,Phone,Gender,Education Level,Joined Date\n" +
            classmatesData.map(classmate => 
                `"${classmate.full_name}","${classmate.email}","${classmate.phone || ''}","${classmate.gender || ''}","${classmate.education_level || ''}","${classmate.application_date}"`
            ).join("\n");
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `classmates_${currentBatchId}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('Classmates list exported successfully!', 'success');
    }
    
    function createGroupChat() {
        if (classmatesData.length === 0) {
            showToast('No classmates available for group chat', 'warning');
            return;
        }
        
        const classmateIds = classmatesData.map(c => c.user_id);
        const className = $('#className').text().replace(' - Classmates', '');
        
        // In a real application, this would create a group chat
        showToast(`Group chat for ${className} would be created here`, 'info');
    }
    
    function showError(message) {
        $('#errorMessage').text(message);
        $('#errorState').show();
    }
    
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050;">
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
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
});
</script>

<style>
.classmate-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.classmate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.view-profile-btn:hover, .send-msg-btn:hover {
    transform: scale(1.05);
}

.toast {
    min-width: 300px;
}

#classStats .card {
    transition: transform 0.3s;
}

#classStats .card:hover {
    transform: translateY(-3px);
}

.dropdown-menu {
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

.profile-image-placeholder {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .card-header .input-group {
        width: 100% !important;
        margin-top: 10px;
    }
    
    .card-header .d-flex {
        flex-direction: column;
    }
}
</style>