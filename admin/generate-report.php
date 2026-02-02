<?php
/**
 * Report Generation
 * File: admin/generate-report.php
 */

require_once '../config/database.php';

$db = (new Database())->getConnection();
$type = $_GET['type'] ?? '';

if ($type === 'certificate') {
    $student_id = $_GET['student_id'] ?? 0;
    
    // Get student and course completion data
    $query = "
        SELECT u.full_name, u.cnic, c.course_name, c.course_code, 
               b.batch_name, b.start_date, b.end_date
        FROM enrollment_applications ea
        JOIN users u ON ea.user_id = u.user_id
        JOIN courses c ON ea.course_id = c.course_id
        JOIN batches b ON ea.batch_id = b.batch_id
        WHERE ea.user_id = ? AND ea.application_status = 'approved' 
        AND b.status = 'completed'
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die('No completed course found for this student.');
    }
    
    // Generate certificate HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Certificate of Completion</title>
        <style>
            body { font-family: 'Times New Roman', serif; margin: 0; padding: 20px; }
            .certificate { 
                width: 800px; 
                margin: 0 auto; 
                border: 10px solid #4361ee; 
                padding: 50px; 
                text-align: center;
                background: linear-gradient(45deg, #f8f9fa, #ffffff);
            }
            .header { font-size: 36px; font-weight: bold; color: #4361ee; margin-bottom: 20px; }
            .title { font-size: 28px; margin: 30px 0; color: #333; }
            .student-name { font-size: 32px; font-weight: bold; color: #4361ee; margin: 20px 0; text-decoration: underline; }
            .course-name { font-size: 24px; font-weight: bold; margin: 20px 0; }
            .details { font-size: 16px; margin: 10px 0; }
            .signature { margin-top: 50px; display: flex; justify-content: space-between; }
            .sig-line { border-top: 2px solid #333; width: 200px; padding-top: 10px; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">SKILLS WAY VOCATIONAL INSTITUTE</div>
            <div style="font-size: 18px; margin-bottom: 30px;">Certificate of Completion</div>
            
            <div class="title">This is to certify that</div>
            
            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
            
            <div class="title">has successfully completed the course</div>
            
            <div class="course-name"><?php echo htmlspecialchars($student['course_name']); ?></div>
            <div class="details">Course Code: <?php echo htmlspecialchars($student['course_code']); ?></div>
            <div class="details">Batch: <?php echo htmlspecialchars($student['batch_name']); ?></div>
            
            <div class="details" style="margin-top: 30px;">
                Duration: <?php echo date('F d, Y', strtotime($student['start_date'])); ?> 
                to <?php echo date('F d, Y', strtotime($student['end_date'])); ?>
            </div>
            
            <div class="details">CNIC: <?php echo htmlspecialchars($student['cnic']); ?></div>
            <div class="details">Date of Issue: <?php echo date('F d, Y'); ?></div>
            
            <div class="signature">
                <div class="sig-line">
                    <div>Director</div>
                    <div>Skills Way Vocational Institute</div>
                </div>
                <div style="text-align: center;">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0MCIgZmlsbD0iIzQzNjFlZSIvPgogIDx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+U1c8L3RleHQ+Cjwvc3ZnPg==" alt="Seal">
                    <div style="margin-top: 10px;">Official Seal</div>
                </div>
                <div class="sig-line">
                    <div>Academic Coordinator</div>
                    <div>Skills Way Vocational Institute</div>
                </div>
            </div>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Default response for other report types
echo "Report type not supported yet.";
?>