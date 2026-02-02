<?php
/**
 * Add CAD Course and Sample Data
 */

require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "<h2>Adding CAD Course and Sample Data...</h2>";
    
    // Check if CAD course exists
    $checkCourse = $db->prepare("SELECT course_id FROM courses WHERE course_code = 'CAD001'");
    $checkCourse->execute();
    $cadCourse = $checkCourse->fetch();
    
    if (!$cadCourse) {
        // Insert CAD course
        $insertCourse = $db->prepare("INSERT INTO courses (course_name, course_code, description, duration_months, fee_amount, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $insertCourse->execute([
            '2D & 3D CAD Design and Modeling',
            'CAD001',
            'Complete CAD design course covering AutoCAD, SolidWorks, and 3D modeling techniques for engineering and architectural design. Learn professional drafting, 3D modeling, rendering, and industry-standard practices.',
            8,
            35000.00,
            true
        ]);
        
        $courseId = $db->lastInsertId();
        echo "✓ CAD Course added successfully (ID: $courseId)<br>";
        
        // Add sample batches for CAD course
        $batches = [
            ['CAD-2024-01', '2024-02-01', '2024-09-30', 25, 'upcoming'],
            ['CAD-2024-02', '2024-04-01', '2024-11-30', 25, 'upcoming'],
            ['CAD-2024-03', '2024-06-01', '2025-01-31', 25, 'upcoming']
        ];
        
        $insertBatch = $db->prepare("INSERT INTO batches (course_id, batch_name, start_date, end_date, max_students, status) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($batches as $batch) {
            $insertBatch->execute([
                $courseId,
                $batch[0],
                $batch[1],
                $batch[2],
                $batch[3],
                $batch[4]
            ]);
        }
        echo "✓ Sample batches added for CAD course<br>";
        
    } else {
        echo "✓ CAD Course already exists (ID: " . $cadCourse['course_id'] . ")<br>";
    }
    
    // Update existing courses with better descriptions if needed
    $updateDescriptions = [
        'WEB001' => 'Complete web development course with HTML, CSS, JavaScript, PHP, MySQL, Bootstrap, and jQuery. Build responsive websites and web applications with modern frameworks.',
        'PY001' => 'Learn Python programming from basics to advanced including data structures, OOP, web development with Django/Flask, data analysis, and automation scripting.',
        'DM001' => 'Complete digital marketing course including SEO, SEM, Social Media Marketing, Content Marketing, Email Marketing, and Google Analytics.',
        'GD001' => 'Professional graphic design using Adobe Creative Suite (Photoshop, Illustrator, InDesign). Learn logo design, branding, print design, and digital graphics.',
        'DE001' => 'Professional data entry and MS Office skills including Excel, Word, PowerPoint, database management, and typing speed improvement.'
    ];
    
    $updateDesc = $db->prepare("UPDATE courses SET description = ? WHERE course_code = ?");
    
    foreach ($updateDescriptions as $code => $desc) {
        $updateDesc->execute([$desc, $code]);
    }
    echo "✓ Course descriptions updated<br>";
    
    // Add pic 8 reference to a course if needed
    $updateCADImage = $db->prepare("UPDATE courses SET course_image = ? WHERE course_code = 'CAD001'");
    $updateCADImage->execute(['pics/8.png']);
    echo "✓ CAD course image updated to use pic 8<br>";
    
    echo "<h3 style='color: green;'>✓ Database update completed successfully!</h3>";
    echo "<p><a href='courses.php'>View Courses</a> | <a href='cad-design.php'>View CAD Course</a> | <a href='3d-viewer.php'>Try 3D Viewer</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Database update failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>