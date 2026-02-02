<?php
$pageTitle = 'Quizzes';
require_once 'includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Initialize variables
$quiz_id = isset($_GET['take']) ? intval($_GET['take']) : 0;
$quiz_data = null;
$classes = [];
$quizzes = [];

// Handle quiz submission
if (isset($_POST['action']) && $_POST['action'] === 'submit' && $quiz_id > 0) {
    $score = 0;
    $total_questions = 0;
    
    // Get quiz details and correct answers
    $stmt = $db->prepare("
        SELECT qq.question_id, qo.is_correct 
        FROM quiz_questions qq 
        LEFT JOIN question_options qo ON qq.question_id = qo.question_id 
        WHERE qq.quiz_id = ? AND qo.is_correct = 1
    ");
    $stmt->execute([$quiz_id]);
    $correct_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Calculate score
    if (isset($_POST['answers']) && is_array($_POST['answers'])) {
        foreach ($_POST['answers'] as $question_id => $selected_option) {
            $total_questions++;
            if (isset($correct_answers[$question_id]) && $correct_answers[$question_id] == $selected_option) {
                $score++;
            }
        }
    }
    
    // Record attempt
    $stmt = $db->prepare("
        INSERT INTO student_quiz_attempts 
        (student_id, quiz_id, score, total_questions, percentage, attempt_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $percentage = $total_questions > 0 ? round(($score/$total_questions)*100, 2) : 0;
    $stmt->execute([$user_id, $quiz_id, $score, $total_questions, $percentage]);
    
    header("Location: quizzes.php?result=" . $percentage . "&quiz_id=" . $quiz_id);
    exit;
}

// If we have a quiz ID to take, fetch quiz details
if ($quiz_id > 0) {
    // First check if student has access to this quiz
    $stmt = $db->prepare("
        SELECT q.*, 
               c.course_name,
               b.batch_name,
               l.lesson_title,
               m.module_name
        FROM quizzes q
        INNER JOIN lessons l ON q.lesson_id = l.lesson_id
        INNER JOIN modules m ON l.module_id = m.module_id
        INNER JOIN batches b ON m.course_id = b.course_id
        INNER JOIN courses c ON b.course_id = c.course_id
        INNER JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        WHERE q.quiz_id = ? 
        AND ea.user_id = ?
        AND ea.application_status = 'approved'
        AND q.is_published = 1
        AND (q.publish_date IS NULL OR q.publish_date <= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$quiz_id, $user_id]);
    $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If quiz not found or no access, redirect
    if (!$quiz_data) {
        header("Location: quizzes.php?error=no_access");
        exit;
    }
    
    // Check if student has already exceeded max attempts
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM student_quiz_attempts 
        WHERE student_id = ? AND quiz_id = ?
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $attempt_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attempt_data && $attempt_data['attempt_count'] >= $quiz_data['max_attempts']) {
        header("Location: quizzes.php?error=max_attempts");
        exit;
    }
}

// Get enrolled classes (batches)
$stmt = $db->prepare("
    SELECT DISTINCT b.batch_id, b.batch_name, b.batch_code, c.course_name,
           b.start_date, b.end_date, b.status, b.teacher_id
    FROM enrollment_applications ea
    INNER JOIN batches b ON ea.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ea.user_id = ? 
    AND ea.application_status = 'approved'
    AND b.status IN ('ongoing', 'upcoming')
    ORDER BY b.start_date DESC
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available quizzes for enrolled classes
if (!empty($classes)) {
    $batch_ids = array_column($classes, 'batch_id');
    $placeholders = str_repeat('?,', count($batch_ids) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT DISTINCT q.*, 
               c.course_name,
               b.batch_name,
               b.batch_code,
               l.lesson_title,
               m.module_name,
               COALESCE(sqa.attempt_count, 0) as attempt_count,
               sqa.max_score,
               sqa.last_attempt_date
        FROM quizzes q
        INNER JOIN lessons l ON q.lesson_id = l.lesson_id
        INNER JOIN modules m ON l.module_id = m.module_id
        INNER JOIN courses c ON m.course_id = c.course_id
        INNER JOIN batches b ON c.course_id = b.course_id
        LEFT JOIN (
            SELECT quiz_id, student_id,
                   COUNT(*) as attempt_count,
                   MAX(percentage) as max_score,
                   MAX(attempt_date) as last_attempt_date
            FROM student_quiz_attempts
            WHERE student_id = ?
            GROUP BY quiz_id, student_id
        ) sqa ON q.quiz_id = sqa.quiz_id
        WHERE b.batch_id IN ($placeholders)
        AND q.is_published = 1
        AND (q.publish_date IS NULL OR q.publish_date <= CURDATE())
        AND (q.due_date IS NULL OR q.due_date >= CURDATE())
        ORDER BY q.publish_date DESC, q.quiz_title ASC
    ");
    
    $params = array_merge([$user_id], $batch_ids);
    $stmt->execute($params);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Display result message if redirected after submission
if (isset($_GET['result'])) {
    $result_message = htmlspecialchars($_GET['result']);
    $quiz_id_result = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-clipboard-check text-primary me-2"></i>Quizzes</h1>
                    <p class="text-muted mb-0">Test your knowledge with interactive quizzes</p>
                </div>
                <?php if (!empty($quizzes)): ?>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <i class="fas fa-list-check me-1"></i> <?php echo count($quizzes); ?> Available
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                switch ($_GET['error']) {
                    case 'no_access': echo 'You do not have access to this quiz.'; break;
                    case 'max_attempts': echo 'You have exceeded the maximum attempts for this quiz.'; break;
                    default: echo 'An error occurred.'; break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['result'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="alert-heading mb-1">Quiz Submitted Successfully!</h5>
                        <p class="mb-0">Your score: <strong><?php echo $result_message; ?>%</strong></p>
                        <?php if ($quiz_id_result): ?>
                        <a href="quiz_results.php?quiz_id=<?php echo $quiz_id_result; ?>" class="btn btn-sm btn-outline-success mt-2">
                            <i class="fas fa-chart-line me-1"></i> View Detailed Results
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($quiz_id && $quiz_data): ?>
            <!-- Quiz Taking Interface -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fas fa-pencil-alt me-2"></i><?php echo htmlspecialchars($quiz_data['quiz_title']); ?>
                            </h5>
                            <small class="text-white-50">
                                <?php echo htmlspecialchars($quiz_data['course_name']); ?> - <?php echo htmlspecialchars($quiz_data['batch_name']); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <?php if ($quiz_data['time_limit_minutes']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-clock me-1"></i> <?php echo $quiz_data['time_limit_minutes']; ?> mins
                            </span>
                            <?php endif; ?>
                            <?php if ($quiz_data['total_points']): ?>
                            <span class="badge bg-info">
                                <i class="fas fa-star me-1"></i> <?php echo $quiz_data['total_points']; ?> points
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($quiz_data['quiz_description']): ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo nl2br(htmlspecialchars($quiz_data['quiz_description'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quiz Instructions -->
                    <div class="bg-light p-3 rounded mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><small><i class="fas fa-check-circle text-success me-2"></i>Select one answer per question</small></p>
                                <p class="mb-1"><small><i class="fas fa-exclamation-triangle text-warning me-2"></i>All questions are required</small></p>
                            </div>
                            <div class="col-md-6">
                                <?php if ($quiz_data['max_attempts'] > 1): ?>
                                <p class="mb-1"><small><i class="fas fa-redo text-primary me-2"></i>Attempts remaining: <?php echo max(0, $quiz_data['max_attempts'] - ($attempt_data['attempt_count'] ?? 0)); ?></small></p>
                                <?php endif; ?>
                                <?php if ($quiz_data['due_date']): ?>
                                <p class="mb-1"><small><i class="fas fa-calendar-times text-danger me-2"></i>Due: <?php echo date('M d, Y', strtotime($quiz_data['due_date'])); ?></small></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quiz Questions Form -->
                    <form id="quizForm" method="POST" onsubmit="return confirmSubmit()">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                        
                        <?php
                        // Fetch quiz questions
                        $stmt = $db->prepare("
                            SELECT qq.*, qo.option_id, qo.option_text, qo.is_correct
                            FROM quiz_questions qq
                            LEFT JOIN question_options qo ON qq.question_id = qo.question_id
                            WHERE qq.quiz_id = ?
                            ORDER BY qq.question_order ASC
                        ");
                        $stmt->execute([$quiz_id]);
                        $questions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Organize questions and options
                        $questions = [];
                        foreach ($questions_data as $row) {
                            $questions[$row['question_id']]['question_text'] = $row['question_text'];
                            $questions[$row['question_id']]['question_type'] = $row['question_type'];
                            $questions[$row['question_id']]['points'] = $row['points'];
                            $questions[$row['question_id']]['options'][$row['option_id']] = [
                                'text' => $row['option_text'],
                                'correct' => $row['is_correct']
                            ];
                        }
                        
                        if (empty($questions)):
                        ?>
                        <div class="alert alert-warning text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                            <h5>No Questions Available</h5>
                            <p class="text-muted">This quiz doesn't have any questions yet.</p>
                        </div>
                        <?php else: ?>
                            <?php $question_num = 1; ?>
                            <?php foreach ($questions as $question_id => $question): ?>
                            <div class="card mb-4 border question-card" id="question-<?php echo $question_num; ?>">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <span class="badge bg-primary rounded-circle me-2"><?php echo $question_num; ?></span>
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php if ($question['points']): ?>
                                            <span class="badge bg-secondary"><?php echo $question['points']; ?> points</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (isset($question['options']) && is_array($question['options'])): ?>
                                        <?php foreach ($question['options'] as $option_id => $option): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="answers[<?php echo $question_id; ?>]" 
                                                   value="<?php echo $option_id; ?>" 
                                                   id="option_<?php echo $question_id; ?>_<?php echo $option_id; ?>"
                                                   required>
                                            <label class="form-check-label" for="option_<?php echo $question_id; ?>_<?php echo $option_id; ?>">
                                                <?php echo htmlspecialchars($option['text']); ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted"><i>No options available for this question.</i></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $question_num++; endforeach; ?>
                            
                            <!-- Quiz Controls -->
                            <div class="card border-0 bg-transparent">
                                <div class="card-body p-0">
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='quizzes.php'">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Quizzes
                                        </button>
                                        <div>
                                            <button type="button" class="btn btn-outline-warning me-2" onclick="if(confirm('Are you sure you want to reset all answers?')) { resetQuiz(); }">
                                                <i class="fas fa-undo me-1"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-success px-4">
                                                <i class="fas fa-paper-plane me-1"></i> Submit Quiz
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Main Quizzes Listing -->
            <div class="row">
                <div class="col-lg-12">
                    <?php if (empty($classes)): ?>
                    <!-- No Enrollments State -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-user-graduate fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Active Enrollments</h4>
                                <p class="text-muted mb-4">You need to be enrolled in a course batch to access quizzes.</p>
                                <a href="courses.php" class="btn btn-primary px-4">
                                    <i class="fas fa-book me-1"></i> Browse Courses
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif (empty($quizzes)): ?>
                    <!-- No Quizzes Available -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-clipboard-question fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Quizzes Available</h4>
                                <p class="text-muted mb-4">There are no quizzes available for your enrolled batches at the moment.</p>
                                <div class="text-muted small">
                                    <p class="mb-1"><i class="fas fa-info-circle me-2"></i>Quizzes will appear here once published by your instructors.</p>
                                    <p class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Check back later for new quizzes.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Quizzes Grid -->
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm border-0 quiz-card" data-quiz-id="<?php echo $quiz['quiz_id']; ?>">
                                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-primary bg-opacity-10 text-primary mb-2">
                                                <?php echo htmlspecialchars($quiz['batch_code']); ?>
                                            </span>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($quiz['quiz_title']); ?></h5>
                                        </div>
                                        <?php if ($quiz['attempt_count'] > 0): ?>
                                        <span class="badge bg-success rounded-pill">
                                            <i class="fas fa-check me-1"></i> Attempted
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($quiz['course_name']); ?>
                                    </p>
                                </div>
                                
                                <div class="card-body pt-2">
                                    <?php if ($quiz['quiz_description']): ?>
                                    <p class="card-text text-muted small mb-3">
                                        <?php echo substr(htmlspecialchars($quiz['quiz_description']), 0, 100); ?>
                                        <?php if (strlen($quiz['quiz_description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="quiz-meta mb-3">
                                        <div class="row g-2">
                                            <?php if ($quiz['time_limit_minutes']): ?>
                                            <div class="col-6">
                                                <small class="text-muted"><i class="fas fa-clock me-1"></i> <?php echo $quiz['time_limit_minutes']; ?> min</small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($quiz['total_points']): ?>
                                            <div class="col-6">
                                                <small class="text-muted"><i class="fas fa-star me-1"></i> <?php echo $quiz['total_points']; ?> pts</small>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-6">
                                                <small class="text-muted"><i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($quiz['module_name']); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted"><i class="fas fa-graduation-cap me-1"></i> <?php echo htmlspecialchars($quiz['lesson_title']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($quiz['attempt_count'] > 0): ?>
                                    <div class="attempt-info bg-light p-2 rounded mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Best Score</small>
                                                <div class="fw-bold text-success"><?php echo $quiz['max_score']; ?>%</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Attempts</small>
                                                <div class="fw-bold"><?php echo $quiz['attempt_count']; ?>/<?php echo $quiz['max_attempts']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-white border-top-0 pt-0">
                                    <div class="d-grid">
                                        <a href="quizzes.php?take=<?php echo $quiz['quiz_id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <?php if ($quiz['attempt_count'] > 0): ?>
                                            <i class="fas fa-redo me-1"></i> Retake Quiz
                                            <?php else: ?>
                                            <i class="fas fa-play me-1"></i> Start Quiz
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Confirm quiz submission
function confirmSubmit() {
    // Check if all questions are answered
    const unanswered = document.querySelectorAll('.question-card').length - 
                     document.querySelectorAll('input[type="radio"]:checked').length;
    
    if (unanswered > 0) {
        return confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`);
    }
    
    return confirm("Are you sure you want to submit your quiz? You cannot change answers after submission.");
}

// Reset quiz form
function resetQuiz() {
    document.querySelectorAll('input[type="radio"]').forEach(input => {
        input.checked = false;
    });
}

// Add timer functionality for timed quizzes
<?php if ($quiz_id && $quiz_data && $quiz_data['time_limit_minutes']): ?>
let timeLeft = <?php echo $quiz_data['time_limit_minutes'] * 60; ?>;
const timerElement = document.createElement('div');
timerElement.className = 'alert alert-warning alert-dismissible fade show';
timerElement.innerHTML = `
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-clock me-2"></i>
            <strong>Time Remaining:</strong>
            <span id="timerDisplay" class="fw-bold"></span>
        </div>
        <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
    </div>
`;

document.querySelector('.card-body').insertBefore(timerElement, document.querySelector('.card-body').firstChild);

function updateTimer() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    document.getElementById('timerDisplay').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        alert('Time is up! Submitting your quiz...');
        document.getElementById('quizForm').submit();
    }
    
    if (timeLeft <= 300) { // 5 minutes warning
        timerElement.className = 'alert alert-danger alert-dismissible fade show';
    }
    
    timeLeft--;
}

updateTimer();
const timerInterval = setInterval(updateTimer, 1000);
<?php endif; ?>

// Add question navigation
document.addEventListener('DOMContentLoaded', function() {
    const questionCards = document.querySelectorAll('.question-card');
    
    questionCards.forEach((card, index) => {
        // Add question number indicator
        const questionNum = index + 1;
        const navButton = document.createElement('button');
        navButton.className = 'btn btn-sm btn-outline-secondary me-1 mb-1';
        navButton.textContent = questionNum;
        navButton.onclick = () => {
            card.scrollIntoView({ behavior: 'smooth' });
        };
        
        // Check if this question is answered
        const questionId = card.id.split('-')[1];
        const isAnswered = card.querySelector('input[type="radio"]:checked');
        if (isAnswered) {
            navButton.className = 'btn btn-sm btn-success me-1 mb-1';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>