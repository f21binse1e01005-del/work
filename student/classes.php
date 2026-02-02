<?php
$pageTitle = 'Classes';
require_once 'includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize database connection
$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : null;

try {
    // Get enrolled classes with additional information
    $stmt = $db->prepare("SELECT 
            b.batch_id, 
            b.batch_name, 
            c.course_name,
            c.course_id,
            b.start_date,
            b.end_date,
            (SELECT COUNT(*) FROM enrollment_applications ea2 
             WHERE ea2.batch_id = b.batch_id 
             AND ea2.application_status = 'approved') as total_students
        FROM enrollment_applications ea 
        JOIN batches b ON ea.batch_id = b.batch_id 
        JOIN courses c ON b.course_id = c.course_id 
        WHERE ea.user_id = ? 
        AND ea.application_status = 'approved'
        ORDER BY b.start_date DESC");
    
    $stmt->execute([$user_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get class details, announcements, materials, and schedule if batch selected
    $class_info = null;
    $announcements = [];
    $materials = [];
    $schedule = [];
    $instructor = null;
    
    if ($batch_id) {
        // Validate that user is enrolled in this batch
        $enrolled = false;
        foreach ($classes as $class) {
            if ($class['batch_id'] == $batch_id) {
                $enrolled = true;
                break;
            }
        }
        
        if (!$enrolled) {
            throw new Exception("You are not enrolled in this class");
        }
        
        // Get detailed class information
        $stmt = $db->prepare("SELECT 
                b.batch_id, 
                b.batch_name, 
                b.start_date,
                b.end_date,
                b.meeting_link,
                b.meeting_time,
                c.course_id,
                c.course_name, 
                c.description,
                c.learning_outcomes,
                u.full_name as instructor_name,
                u.email as instructor_email
            FROM batches b 
            JOIN courses c ON b.course_id = c.course_id 
            LEFT JOIN users u ON b.instructor_id = u.user_id 
            WHERE b.batch_id = ?");
        
        $stmt->execute([$batch_id]);
        $class_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($class_info) {
            // Get announcements
            $stmt = $db->prepare("SELECT 
                    announcement_id,
                    title, 
                    content, 
                    created_at,
                    attachment,
                    attachment_name
                FROM announcements 
                WHERE batch_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10");
            
            $stmt->execute([$batch_id]);
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get course materials
            $stmt = $db->prepare("SELECT 
                    material_id,
                    title,
                    description,
                    file_name,
                    file_type,
                    file_size,
                    uploaded_at,
                    upload_type
                FROM course_materials 
                WHERE batch_id = ? 
                ORDER BY uploaded_at DESC");
            
            $stmt->execute([$batch_id]);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get schedule/topics
            $stmt = $db->prepare("SELECT 
                    schedule_id,
                    topic,
                    description,
                    scheduled_date,
                    materials_required,
                    is_completed
                FROM batch_schedule 
                WHERE batch_id = ? 
                ORDER BY scheduled_date ASC");
            
            $stmt->execute([$batch_id]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get instructor info if available
            if ($class_info['instructor_name']) {
                $instructor = [
                    'name' => $class_info['instructor_name'],
                    'email' => $class_info['instructor_email']
                ];
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Classes Page Error: " . $error);
}

// Calculate class progress for the selected batch
$class_progress = 0;
if ($class_info && $batch_id) {
    $start_date = strtotime($class_info['start_date']);
    $end_date = strtotime($class_info['end_date']);
    $current_date = time();
    
    if ($end_date > $start_date) {
        $total_days = ($end_date - $start_date) / (60 * 60 * 24);
        $elapsed_days = ($current_date - $start_date) / (60 * 60 * 24);
        $class_progress = min(100, max(0, ($elapsed_days / $total_days) * 100));
        $class_progress = round($class_progress, 1);
    }
}
?>

<div class="container-fluid px-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h2"><i class="fas fa-chalkboard-teacher me-2"></i>My Classes</h1>
            <?php if ($batch_id && $class_info): ?>
                <a href="classmates.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-users me-1"></i> View Classmates
                </a>
            <?php endif; ?>
        </div>
        <p class="text-muted mb-0">Access your class materials, announcements, and schedule</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Class Selection Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Select Your Class</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Choose from your enrolled classes:</label>
                    <select class="form-select form-select-lg" id="classSelector" onchange="changeClass(this.value)">
                        <option value="">-- Select a Class --</option>
                        <?php foreach ($classes as $class): 
                            $isActive = (strtotime($class['start_date']) <= time() && strtotime($class['end_date']) >= time());
                        ?>
                            <option value="<?php echo $class['batch_id']; ?>" 
                                <?php echo $batch_id == $class['batch_id'] ? 'selected' : ''; ?>
                                data-start="<?php echo $class['start_date']; ?>"
                                data-end="<?php echo $class['end_date']; ?>"
                                data-students="<?php echo $class['total_students']; ?>">
                                <?php echo htmlspecialchars($class['batch_name']); ?> 
                                - <?php echo htmlspecialchars($class['course_name']); ?>
                                <?php if (!$isActive): ?> (<?php echo time() < strtotime($class['start_date']) ? 'Upcoming' : 'Completed'; ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center h-100">
                        <div class="text-center w-100">
                            <h3 class="text-primary mb-0"><?php echo count($classes); ?></h3>
                            <p class="text-muted mb-0">Total Classes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($batch_id && $class_info): ?>
        <!-- Class Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2"><?php echo htmlspecialchars($class_info['batch_name']); ?></h3>
                        <p class="text-muted mb-1">
                            <i class="fas fa-book me-1"></i>
                            <?php echo htmlspecialchars($class_info['course_name']); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar-start me-1"></i>
                                <?php echo date('M j, Y', strtotime($class_info['start_date'])); ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar-end me-1"></i>
                                <?php echo date('M j, Y', strtotime($class_info['end_date'])); ?>
                            </span>
                            <?php if ($class_info['meeting_time']): ?>
                                <span class="badge bg-info">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('h:i A', strtotime($class_info['meeting_time'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h5 class="text-muted mb-2">Class Progress</h5>
                            <div class="position-relative d-inline-block">
                                <div class="progress-circle" data-percent="<?php echo $class_progress; ?>" 
                                     data-size="100" data-thickness="8">
                                    <span class="percent"><?php echo $class_progress; ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <ul class="nav nav-tabs nav-justified" id="classTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="announcements-tab" data-bs-toggle="tab" 
                                data-bs-target="#announcements" type="button" role="tab">
                            <i class="fas fa-bullhorn me-2"></i>Announcements
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="materials-tab" data-bs-toggle="tab" 
                                data-bs-target="#materials" type="button" role="tab">
                            <i class="fas fa-folder-open me-2"></i>Materials
                            <?php if (!empty($materials)): ?>
                                <span class="badge bg-primary ms-1"><?php echo count($materials); ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" 
                                data-bs-target="#schedule" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i>Schedule
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="info-tab" data-bs-toggle="tab" 
                                data-bs-target="#info" type="button" role="tab">
                            <i class="fas fa-info-circle me-2"></i>Class Info
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="classTabsContent">
                    
                    <!-- Announcements Tab -->
                    <div class="tab-pane fade show active" id="announcements" role="tabpanel">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No announcements yet</h5>
                                <p class="text-muted">Your instructor will post announcements here</p>
                            </div>
                        <?php else: ?>
                            <div class="announcements-list">
                                <?php foreach ($announcements as $announcement): 
                                    $time_ago = time_elapsed_string($announcement['created_at']);
                                ?>
                                    <div class="card mb-3 border-start border-primary border-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0">
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                </h5>
                                                <small class="text-muted" data-bs-toggle="tooltip" 
                                                       title="<?php echo date('F j, Y \a\t h:i A', strtotime($announcement['created_at'])); ?>">
                                                    <i class="far fa-clock me-1"></i><?php echo $time_ago; ?>
                                                </small>
                                            </div>
                                            <div class="card-text mb-3">
                                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                            </div>
                                            <?php if ($announcement['attachment']): ?>
                                                <div class="mt-3">
                                                    <a href="../uploads/announcements/<?php echo htmlspecialchars($announcement['attachment']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" download>
                                                        <i class="fas fa-paperclip me-1"></i>
                                                        <?php echo htmlspecialchars($announcement['attachment_name'] ?: 'Download Attachment'); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Materials Tab -->
                    <div class="tab-pane fade" id="materials" role="tabpanel">
                        <?php if (empty($materials)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No materials uploaded yet</h5>
                                <p class="text-muted">Your instructor will upload course materials here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Description</th>
                                            <th>Type</th>
                                            <th>Uploaded</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): 
                                            $file_icon = get_file_icon($material['file_type']);
                                            $file_size = format_file_size($material['file_size']);
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-<?php echo $file_icon; ?> text-primary me-2 fa-lg"></i>
                                                        <strong><?php echo htmlspecialchars($material['title']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($material['upload_type'] ?: 'Material'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($material['uploaded_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="../uploads/materials/<?php echo htmlspecialchars($material['file_name']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" download>
                                                        <i class="fas fa-download me-1"></i> Download
                                                    </a>
                                                    <span class="ms-2 text-muted small"><?php echo $file_size; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Schedule Tab -->
                    <div class="tab-pane fade" id="schedule" role="tabpanel">
                        <?php if (empty($schedule)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No schedule available</h5>
                                <p class="text-muted">Your instructor will post the class schedule here</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($schedule as $item): 
                                    $is_past = strtotime($item['scheduled_date']) < time();
                                    $is_today = date('Y-m-d', strtotime($item['scheduled_date'])) == date('Y-m-d');
                                ?>
                                    <div class="timeline-item <?php echo $is_past ? 'completed' : ''; ?> <?php echo $is_today ? 'current' : ''; ?>">
                                        <div class="timeline-marker">
                                            <?php if ($item['is_completed']): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php elseif ($is_today): ?>
                                                <i class="fas fa-circle text-primary"></i>
                                            <?php else: ?>
                                                <i class="far fa-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['topic']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('D, M j, Y', strtotime($item['scheduled_date'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($item['description']): ?>
                                                <p class="mb-2 text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($item['materials_required']): ?>
                                                <small class="text-info">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Materials Required: <?php echo htmlspecialchars($item['materials_required']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info Tab -->
                    <div class="tab-pane fade" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <h5 class="mb-3">Course Description</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($class_info['description'] ?: 'No description available')); ?>
                                    </div>
                                </div>
                                
                                <?php if ($class_info['learning_outcomes']): ?>
                                    <h5 class="mt-4 mb-3">Learning Outcomes</h5>
                                    <div class="card">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($class_info['learning_outcomes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header bg-white">
                                        <h6 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Instructor</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <?php if ($instructor): ?>
                                            <div class="avatar-circle mb-3 mx-auto">
                                                <span class="initials">
                                                    <?php echo get_initials($instructor['name']); ?>
                                                </span>
                                            </div>
                                            <h6><?php echo htmlspecialchars($instructor['name']); ?></h6>
                                            <p class="text-muted mb-3">
                                                <i class="fas fa-envelope me-1"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($instructor['email']); ?>">
                                                    <?php echo htmlspecialchars($instructor['email']); ?>
                                                </a>
                                            </p>
                                        <?php else: ?>
                                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Instructor information not available</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($class_info['meeting_link']): ?>
                                    <div class="card mt-3">
                                        <div class="card-header bg-white">
                                            <h6 class="mb-0"><i class="fas fa-video me-2"></i>Virtual Class</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-2">
                                                <i class="fas fa-link me-2"></i>
                                                <a href="<?php echo htmlspecialchars($class_info['meeting_link']); ?>" 
                                                   target="_blank" class="text-decoration-none">
                                                    Join Virtual Class
                                                </a>
                                            </p>
                                            <?php if ($class_info['meeting_time']): ?>
                                                <p class="mb-0">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <?php echo date('h:i A', strtotime($class_info['meeting_time'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No Class Selected -->
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Select a Class</h4>
                <p class="text-muted mb-4">Choose a class from the dropdown above to view class details, materials, announcements, and schedule.</p>
                <?php if (empty($classes)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You are not enrolled in any classes yet. <a href="available-courses.php">Browse available courses</a> to get started.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

<style>
    .progress-circle {
        position: relative;
        display: inline-block;
        width: 100px;
        height: 100px;
    }
    
    .progress-circle svg {
        transform: rotate(-90deg);
    }
    
    .progress-circle .percent {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }
    
    .timeline-item.completed .timeline-marker i {
        color: #28a745;
    }
    
    .timeline-item.current .timeline-marker i {
        color: #007bff;
        animation: pulse 2s infinite;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 0;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .timeline-content {
        padding-left: 20px;
    }
    
    .avatar-circle {
        width: 80px;
        height: 80px;
        background: #007bff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-circle .initials {
        color: white;
        font-size: 24px;
        font-weight: bold;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 12px 0;
    }
    
    .nav-tabs .nav-link.active {
        color: #007bff;
        border-bottom: 3px solid #007bff;
        background: none;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Create circular progress indicators
    document.querySelectorAll('.progress-circle').forEach(function(circle) {
        const percent = parseFloat(circle.getAttribute('data-percent'));
        const size = parseInt(circle.getAttribute('data-size'));
        const thickness = parseInt(circle.getAttribute('data-thickness'));
        
        const radius = (size - thickness) / 2;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (percent / 100) * circumference;
        
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("width", size);
        svg.setAttribute("height", size);
        
        const bgCircle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        bgCircle.setAttribute("cx", size / 2);
        bgCircle.setAttribute("cy", size / 2);
        bgCircle.setAttribute("r", radius);
        bgCircle.setAttribute("fill", "none");
        bgCircle.setAttribute("stroke", "#e9ecef");
        bgCircle.setAttribute("stroke-width", thickness);
        
        const progressCircle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        progressCircle.setAttribute("cx", size / 2);
        progressCircle.setAttribute("cy", size / 2);
        progressCircle.setAttribute("r", radius);
        progressCircle.setAttribute("fill", "none");
        progressCircle.setAttribute("stroke", percent >= 75 ? "#28a745" : (percent >= 50 ? "#007bff" : "#ffc107"));
        progressCircle.setAttribute("stroke-width", thickness);
        progressCircle.setAttribute("stroke-linecap", "round");
        progressCircle.setAttribute("stroke-dasharray", circumference);
        progressCircle.setAttribute("stroke-dashoffset", offset);
        progressCircle.setAttribute("transform", `rotate(-90 ${size/2} ${size/2})`);
        
        svg.appendChild(bgCircle);
        svg.appendChild(progressCircle);
        circle.insertBefore(svg, circle.firstChild);
    });
});

function changeClass(batchId) {
    if (batchId) {
        window.location.href = 'classes.php?batch_id=' + batchId;
    }
}

// Helper function to handle file downloads with progress
function downloadFile(url, fileName) {
    fetch(url)
        .then(response => response.blob())
        .then(blob => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        })
        .catch(error => {
            console.error('Download failed:', error);
            alert('Failed to download file. Please try again.');
        });
}

// Remember selected tab on page refresh
if (window.history.replaceState) {
    var activeTab = localStorage.getItem('activeClassTab');
    if (activeTab) {
        var tab = document.querySelector('button[data-bs-target="' + activeTab + '"]');
        if (tab) {
            var tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }
}

// Save active tab
document.querySelectorAll('#classTabs button').forEach(function(tab) {
    tab.addEventListener('shown.bs.tab', function(event) {
        localStorage.setItem('activeClassTab', event.target.getAttribute('data-bs-target'));
    });
});
</script>

<?php
// Helper functions
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function get_file_icon($file_type) {
    $icons = [
        'pdf' => 'file-pdf',
        'doc' => 'file-word',
        'docx' => 'file-word',
        'xls' => 'file-excel',
        'xlsx' => 'file-excel',
        'ppt' => 'file-powerpoint',
        'pptx' => 'file-powerpoint',
        'zip' => 'file-archive',
        'rar' => 'file-archive',
        'txt' => 'file-alt',
        'jpg' => 'file-image',
        'jpeg' => 'file-image',
        'png' => 'file-image',
        'gif' => 'file-image',
        'mp4' => 'file-video',
        'mp3' => 'file-audio',
    ];
    
    $extension = strtolower(pathinfo($file_type, PATHINFO_EXTENSION));
    return $icons[$extension] ?? 'file';
}

function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function get_initials($name) {
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        $initials .= strtoupper(substr($n, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials;
}
?>