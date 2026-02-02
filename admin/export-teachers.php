<?php
/**
 * Teachers Export Module
 * Export teachers data in multiple formats (Excel, PDF, CSV)
 * File: export-teachers.php
 */

// Enable error reporting for debugging
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

require_once '../config/database.php';
require_once 'includes/security.php';
require_once 'includes/helpers.php';

// Check permissions
if (!hasPermission('export_data')) {
    $_SESSION['error'] = 'You do not have permission to export data.';
    header('Location: teachers.php');
    exit;
}

// Get export parameters
$format = cleanInput($_GET['format'] ?? $_POST['format'] ?? 'excel');
$exportType = cleanInput($_GET['type'] ?? $_POST['type'] ?? 'all'); // all, filtered, selected
$selectedIds = $_GET['selected_ids'] ?? $_POST['selected_ids'] ?? '';
$filters = [
    'search' => cleanInput($_GET['search'] ?? ''),
    'status' => cleanInput($_GET['status'] ?? ''),
    'qualification' => cleanInput($_GET['qualification'] ?? ''),
    'experience' => cleanInput($_GET['experience'] ?? ''),
    'sort_by' => cleanInput($_GET['sort_by'] ?? 'name'),
    'sort_order' => cleanInput($_GET['sort_order'] ?? 'asc')
];

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid security token.';
    header('Location: teachers.php');
    exit;
}

// Apply rate limiting for exports
applyRateLimit('teacher_exports_' . $_SESSION['user_id'], 3600, 5);

try {
    $db = (new Database())->getConnection();
    
    // Build query based on export type
    $teachers = getTeachersForExport($db, $exportType, $selectedIds, $filters);
    
    if (empty($teachers)) {
        $_SESSION['warning'] = 'No teachers found to export.';
        header('Location: teachers.php');
        exit;
    }
    
    // Generate export based on format
    switch (strtolower($format)) {
        case 'excel':
            exportToExcel($teachers, $exportType);
            break;
            
        case 'pdf':
            exportToPDF($teachers, $exportType);
            break;
            
        case 'csv':
            exportToCSV($teachers, $exportType);
            break;
            
        case 'json':
            exportToJSON($teachers, $exportType);
            break;
            
        case 'xml':
            exportToXML($teachers, $exportType);
            break;
            
        default:
            throw new Exception('Unsupported export format: ' . $format);
    }
    
    // Log export activity
    logExportActivity($db, $format, $exportType, count($teachers), $_SESSION['user_id']);
    
} catch (Exception $e) {
    logSecurityEvent('export_error', [
        'format' => $format,
        'type' => $exportType,
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
    
    $_SESSION['error'] = 'Export failed: ' . $e->getMessage();
    header('Location: teachers.php');
    exit;
}

/**
 * Get teachers data for export
 */
function getTeachersForExport($db, $exportType, $selectedIds, $filters) {
    $whereConditions = ["u.user_type = 'teacher'", "u.deleted_at IS NULL"];
    $params = [];
    $paramTypes = '';
    
    // Apply filters
    if ($filters['search']) {
        $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.cnic LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $paramTypes .= 'ssss';
    }
    
    if ($filters['status'] && in_array($filters['status'], ['active', 'inactive', 'suspended', 'pending_verification'])) {
        $whereConditions[] = "u.account_status = ?";
        $params[] = $filters['status'];
        $paramTypes .= 's';
    }
    
    // Handle selected IDs
    if ($exportType === 'selected' && !empty($selectedIds)) {
        if (is_array($selectedIds)) {
            $idList = $selectedIds;
        } else {
            $idList = explode(',', $selectedIds);
        }
        
        $idList = array_map('intval', array_filter($idList, 'is_numeric'));
        
        if (!empty($idList)) {
            $placeholders = implode(',', array_fill(0, count($idList), '?'));
            $whereConditions[] = "u.user_id IN ($placeholders)";
            $params = array_merge($params, $idList);
            $paramTypes .= str_repeat('i', count($idList));
        }
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
    
    // Query to get teacher data with statistics
    $query = "
        SELECT 
            u.user_id,
            u.teacher_code,
            u.full_name,
            u.username,
            u.email,
            u.phone,
            u.cnic,
            u.account_status,
            DATE_FORMAT(u.created_at, '%Y-%m-%d') as join_date,
            DATE_FORMAT(u.joining_date, '%Y-%m-%d') as employment_start_date,
            u.is_full_time,
            u.hourly_rate,
            u.monthly_salary,
            
            up.gender,
            DATE_FORMAT(up.date_of_birth, '%Y-%m-%d') as date_of_birth,
            TIMESTAMPDIFF(YEAR, up.date_of_birth, CURDATE()) as age,
            up.education_level,
            up.qualifications,
            up.specialization,
            up.years_experience,
            up.address,
            up.bio,
            
            -- Statistics
            (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id AND status = 'ongoing') as active_batches,
            (SELECT COUNT(DISTINCT course_id) FROM batches WHERE teacher_id = u.user_id) as courses_taught,
            (SELECT COUNT(DISTINCT e.user_id) FROM enrollments e 
             JOIN batches b ON e.batch_id = b.batch_id 
             WHERE b.teacher_id = u.user_id AND e.enrollment_status = 'active') as active_students,
            (SELECT COUNT(*) FROM enrollments e 
             JOIN batches b ON e.batch_id = b.batch_id 
             WHERE b.teacher_id = u.user_id) as total_students,
            
            -- Feedback
            (SELECT AVG(rating) FROM teacher_feedback WHERE teacher_id = u.user_id) as avg_rating,
            (SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = u.user_id) as feedback_count,
            
            -- Current courses
            (SELECT GROUP_CONCAT(CONCAT(c.course_code, ' - ', c.course_name) SEPARATOR '; ') 
             FROM batches b 
             JOIN courses c ON b.course_id = c.course_id 
             WHERE b.teacher_id = u.user_id AND b.status = 'ongoing' 
             LIMIT 5) as current_courses,
            
            -- Performance metrics
            DATEDIFF(CURDATE(), u.joining_date) as days_with_institute,
            CASE 
                WHEN u.account_status = 'active' THEN 'Active'
                WHEN u.account_status = 'inactive' THEN 'Inactive'
                WHEN u.account_status = 'suspended' THEN 'Suspended'
                WHEN u.account_status = 'pending_verification' THEN 'Pending Verification'
                ELSE 'Unknown'
            END as status_display
            
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        {$whereClause}
        ORDER BY {$sortField} {$sortOrder}
    ";
    
    $stmt = $db->prepare($query);
    
    if (!empty($params)) {
        if (strlen($paramTypes) === count($params)) {
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindValue($i + 1, $params[$i], getPDOType($paramTypes[$i]));
            }
            $stmt->execute();
        } else {
            $stmt->execute($params);
        }
    } else {
        $stmt->execute();
    }
    
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for export
    foreach ($teachers as &$teacher) {
        $teacher = formatTeacherForExport($teacher);
    }
    
    return $teachers;
}

/**
 * Format teacher data for export
 */
function formatTeacherForExport($teacher) {
    // Format phone number
    if (!empty($teacher['phone'])) {
        $teacher['phone'] = formatPhone($teacher['phone']);
    }
    
    // Format CNIC
    if (!empty($teacher['cnic'])) {
        $teacher['cnic'] = formatCNIC($teacher['cnic']);
    }
    
    // Format salary/rates
    if (!empty($teacher['hourly_rate'])) {
        $teacher['hourly_rate'] = 'Rs. ' . number_format($teacher['hourly_rate'], 2);
    }
    
    if (!empty($teacher['monthly_salary'])) {
        $teacher['monthly_salary'] = 'Rs. ' . number_format($teacher['monthly_salary'], 2);
    }
    
    // Format employment type
    if (isset($teacher['is_full_time'])) {
        $teacher['employment_type'] = $teacher['is_full_time'] ? 'Full Time' : 'Part Time';
    }
    
    // Format rating
    if (!empty($teacher['avg_rating'])) {
        $teacher['avg_rating'] = number_format($teacher['avg_rating'], 1) . ' / 5.0';
    } else {
        $teacher['avg_rating'] = 'No ratings';
    }
    
    // Calculate performance score
    $teacher['performance_score'] = calculatePerformanceScore($teacher);
    
    // Remove sensitive/internal fields
    unset($teacher['is_full_time']);
    unset($teacher['user_id']);
    
    return $teacher;
}

/**
 * Calculate performance score for export
 */
function calculatePerformanceScore($teacher) {
    $score = 0;
    
    // Rating component (40%)
    $rating = floatval($teacher['avg_rating'] ?? 0);
    $score += min(40, $rating * 8);
    
    // Active batches component (30%)
    $activeBatches = intval($teacher['active_batches'] ?? 0);
    $score += min(30, $activeBatches * 10);
    
    // Student count component (20%)
    $activeStudents = intval($teacher['active_students'] ?? 0);
    $score += min(20, $activeStudents / 10);
    
    // Experience component (10%)
    $experience = intval($teacher['years_experience'] ?? 0);
    $score += min(10, $experience * 0.5);
    
    $totalScore = min(100, $score);
    
    // Add grade
    $grade = match(true) {
        $totalScore >= 90 => 'A+ (Excellent)',
        $totalScore >= 80 => 'A (Very Good)',
        $totalScore >= 70 => 'B+ (Good)',
        $totalScore >= 60 => 'B (Satisfactory)',
        $totalScore >= 50 => 'C (Needs Improvement)',
        default => 'D (Poor)'
    };
    
    return number_format($totalScore, 1) . '% - ' . $grade;
}

/**
 * Export to Excel using PHPExcel/PhpSpreadsheet
 */
function exportToExcel($teachers, $exportType) {
    // Check if PhpSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Try to include it
        $vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
        } else {
            // Fallback to simple CSV if PhpSpreadsheet not available
            exportToCSV($teachers, $exportType);
            return;
        }
    }
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator('Skills Way Vocational Institute')
            ->setLastModifiedBy('Admin')
            ->setTitle('Teachers Export')
            ->setSubject('Teachers Data')
            ->setDescription('Export of teachers information')
            ->setKeywords('teachers export skills way')
            ->setCategory('Teachers Data');
        
        // Define headers
        $headers = [
            'Teacher Code',
            'Full Name',
            'Username',
            'Email',
            'Phone',
            'CNIC',
            'Status',
            'Join Date',
            'Employment Start Date',
            'Employment Type',
            'Hourly Rate',
            'Monthly Salary',
            'Gender',
            'Date of Birth',
            'Age',
            'Education Level',
            'Qualifications',
            'Specialization',
            'Years Experience',
            'Address',
            'Active Batches',
            'Courses Taught',
            'Active Students',
            'Total Students',
            'Average Rating',
            'Feedback Count',
            'Current Courses',
            'Days with Institute',
            'Performance Score',
            'Bio'
        ];
        
        // Write headers with styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ];
        
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->applyFromArray($headerStyle);
        }
        
        // Auto size columns for headers
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        
        // Write data
        $row = 2;
        foreach ($teachers as $teacher) {
            $col = 1;
            
            // Map teacher data to headers
            $data = [
                $teacher['teacher_code'] ?? '',
                $teacher['full_name'] ?? '',
                $teacher['username'] ?? '',
                $teacher['email'] ?? '',
                $teacher['phone'] ?? '',
                $teacher['cnic'] ?? '',
                $teacher['status_display'] ?? '',
                $teacher['join_date'] ?? '',
                $teacher['employment_start_date'] ?? '',
                $teacher['employment_type'] ?? '',
                $teacher['hourly_rate'] ?? '',
                $teacher['monthly_salary'] ?? '',
                $teacher['gender'] ?? '',
                $teacher['date_of_birth'] ?? '',
                $teacher['age'] ?? '',
                $teacher['education_level'] ?? '',
                $teacher['qualifications'] ?? '',
                $teacher['specialization'] ?? '',
                $teacher['years_experience'] ?? '',
                $teacher['address'] ?? '',
                $teacher['active_batches'] ?? 0,
                $teacher['courses_taught'] ?? 0,
                $teacher['active_students'] ?? 0,
                $teacher['total_students'] ?? 0,
                $teacher['avg_rating'] ?? '',
                $teacher['feedback_count'] ?? 0,
                $teacher['current_courses'] ?? '',
                $teacher['days_with_institute'] ?? 0,
                $teacher['performance_score'] ?? '',
                $teacher['bio'] ?? ''
            ];
            
            foreach ($data as $value) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $sheet->setCellValue($cell, $value);
                
                // Apply formatting based on column type
                if (in_array($col, [11, 12])) { // Salary columns
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                } elseif (in_array($col, [20, 21, 22, 23, 26, 28])) { // Numeric columns
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0');
                }
                
                $col++;
            }
            
            // Apply alternating row colors
            $rowStyle = [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $row % 2 == 0 ? 'F8F9FA' : 'FFFFFF']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'DDDDDD']
                    ]
                ]
            ];
            
            $sheet->getStyle("A{$row}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)
                  ->applyFromArray($rowStyle);
            
            $row++;
        }
        
        // Add summary statistics
        $summaryRow = $row + 2;
        $sheet->setCellValue("A{$summaryRow}", 'Summary Statistics');
        $sheet->getStyle("A{$summaryRow}")->getFont()->setBold(true);
        
        $summaryData = [
            ['Total Teachers', count($teachers)],
            ['Active Teachers', count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active'))],
            ['Average Experience (Years)', number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1)],
            ['Total Active Batches', array_sum(array_column($teachers, 'active_batches'))],
            ['Total Active Students', array_sum(array_column($teachers, 'active_students'))],
            ['Average Rating', calculateOverallAverageRating($teachers)]
        ];
        
        foreach ($summaryData as $index => $summary) {
            $sheet->setCellValue("A" . ($summaryRow + $index + 1), $summary[0]);
            $sheet->setCellValue("B" . ($summaryRow + $index + 1), $summary[1]);
            $sheet->getStyle("A" . ($summaryRow + $index + 1))->getFont()->setBold(true);
        }
        
        // Add export metadata
        $metaRow = $summaryRow + count($summaryData) + 2;
        $sheet->setCellValue("A{$metaRow}", 'Export Information:');
        $sheet->getStyle("A{$metaRow}")->getFont()->setBold(true);
        
        $metadata = [
            ['Export Date', date('Y-m-d H:i:s')],
            ['Exported By', $_SESSION['full_name'] ?? 'Admin'],
            ['Export Type', ucfirst($exportType)],
            ['Total Records', count($teachers)],
            ['Generated By', 'Skills Way Vocational Institute - Teachers Management System']
        ];
        
        foreach ($metadata as $index => $meta) {
            $sheet->setCellValue("A" . ($metaRow + $index + 1), $meta[0]);
            $sheet->setCellValue("B" . ($metaRow + $index + 1), $meta[1]);
        }
        
        // Add filters information if any
        $filterRow = $metaRow + count($metadata) + 2;
        $filtersApplied = array_filter([
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'qualification' => $_GET['qualification'] ?? ''
        ]);
        
        if (!empty($filtersApplied)) {
            $sheet->setCellValue("A{$filterRow}", 'Filters Applied:');
            $sheet->getStyle("A{$filterRow}")->getFont()->setBold(true);
            
            $filterIndex = 0;
            foreach ($filtersApplied as $key => $value) {
                if (!empty($value)) {
                    $sheet->setCellValue("A" . ($filterRow + $filterIndex + 1), ucfirst($key) . ':');
                    $sheet->setCellValue("B" . ($filterRow + $filterIndex + 1), $value);
                    $filterIndex++;
                }
            }
        }
        
        // Freeze header row
        $sheet->freezePane('A2');
        
        // Set active sheet index
        $spreadsheet->setActiveSheetIndex(0);
        
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        // Create writer and output
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        
        // Save to output
        $writer->save('php://output');
        
    } catch (Exception $e) {
        throw new Exception('Excel export failed: ' . $e->getMessage());
    }
}

/**
 * Export to PDF using TCPDF
 */
function exportToPDF($teachers, $exportType) {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        // Try to include it
        $vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
        } else {
            // Fallback to HTML if TCPDF not available
            exportToHTML($teachers, $exportType);
            return;
        }
    }
    
    try {
        // Create new PDF document
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Skills Way Vocational Institute');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Teachers Export');
        $pdf->SetSubject('Teachers Data');
        $pdf->SetKeywords('Teachers, Export, Skills Way');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Skills Way Vocational Institute - Teachers Export', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Exported by: ' . ($_SESSION['full_name'] ?? 'Admin'), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Export Type: ' . ucfirst($exportType) . ' (' . count($teachers) . ' teachers)', 0, 1, 'C');
        
        // Add a line break
        $pdf->Ln(10);
        
        // Create summary table
        $summaryHtml = '
        <style>
            .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .summary-table th { background-color: #2C3E50; color: white; padding: 8px; text-align: left; font-weight: bold; }
            .summary-table td { padding: 8px; border-bottom: 1px solid #ddd; }
            .summary-table tr:nth-child(even) { background-color: #f9f9f9; }
        </style>
        
        <table class="summary-table">
            <tr>
                <th>Statistic</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Total Teachers</td>
                <td>' . count($teachers) . '</td>
            </tr>
            <tr>
                <td>Active Teachers</td>
                <td>' . count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active')) . '</td>
            </tr>
            <tr>
                <td>Average Experience</td>
                <td>' . number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1) . ' years</td>
            </tr>
            <tr>
                <td>Total Active Batches</td>
                <td>' . array_sum(array_column($teachers, 'active_batches')) . '</td>
            </tr>
            <tr>
                <td>Total Active Students</td>
                <td>' . array_sum(array_column($teachers, 'active_students')) . '</td>
            </tr>
            <tr>
                <td>Average Rating</td>
                <td>' . calculateOverallAverageRating($teachers) . '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($summaryHtml, true, false, true, false, '');
        
        // Add page break before detailed table
        $pdf->AddPage();
        
        // Create detailed table header
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Detailed Teacher Information', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Define column widths for landscape
        $colWidths = [25, 40, 25, 35, 25, 35, 20, 20, 20, 25];
        $headers = [
            'Code',
            'Name',
            'Email',
            'Phone',
            'Status',
            'Join Date',
            'Exp (Yrs)',
            'Batches',
            'Students',
            'Rating'
        ];
        
        // Draw table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(44, 62, 80); // Dark blue
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Reset text color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        
        // Draw table rows
        $fill = false;
        $rowCount = 0;
        
        foreach ($teachers as $teacher) {
            // Check if we need a new page
            if ($rowCount > 0 && $rowCount % 25 == 0) {
                $pdf->AddPage();
                
                // Redraw header
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(44, 62, 80);
                $pdf->SetTextColor(255, 255, 255);
                
                foreach ($headers as $i => $header) {
                    $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
                }
                $pdf->Ln();
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', '', 8);
            }
            
            // Set fill color for alternating rows
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
            
            // Draw cells
            $pdf->Cell($colWidths[0], 6, $teacher['teacher_code'] ?? '', 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[1], 6, substr($teacher['full_name'] ?? '', 0, 20), 'LR', 0, 'L', $fill);
            $pdf->Cell($colWidths[2], 6, substr($teacher['email'] ?? '', 0, 20), 'LR', 0, 'L', $fill);
            $pdf->Cell($colWidths[3], 6, $teacher['phone'] ?? '', 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[4], 6, $teacher['status_display'] ?? '', 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[5], 6, $teacher['join_date'] ?? '', 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[6], 6, $teacher['years_experience'] ?? 0, 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[7], 6, $teacher['active_batches'] ?? 0, 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[8], 6, $teacher['active_students'] ?? 0, 'LR', 0, 'C', $fill);
            $pdf->Cell($colWidths[9], 6, $teacher['avg_rating'] ?? '', 'LR', 0, 'C', $fill);
            $pdf->Ln();
            
            $fill = !$fill;
            $rowCount++;
        }
        
        // Close table
        $pdf->Cell(array_sum($colWidths), 0, '', 'T');
        
        // Add export information on last page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        
        $exportInfo = '
        <h3>Export Information</h3>
        <p><strong>Export Date:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Exported By:</strong> ' . ($_SESSION['full_name'] ?? 'Admin') . '</p>
        <p><strong>Export Type:</strong> ' . ucfirst($exportType) . '</p>
        <p><strong>Total Records:</strong> ' . count($teachers) . '</p>
        <p><strong>Generated By:</strong> Skills Way Vocational Institute - Teachers Management System</p>';
        
        $pdf->writeHTML($exportInfo, true, false, true, false, '');
        
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Output PDF
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        throw new Exception('PDF export failed: ' . $e->getMessage());
    }
}

/**
 * Export to CSV
 */
function exportToCSV($teachers, $exportType) {
    try {
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Define headers
        $headers = [
            'Teacher Code',
            'Full Name',
            'Username',
            'Email',
            'Phone',
            'CNIC',
            'Status',
            'Join Date',
            'Employment Start Date',
            'Employment Type',
            'Hourly Rate',
            'Monthly Salary',
            'Gender',
            'Date of Birth',
            'Age',
            'Education Level',
            'Qualifications',
            'Specialization',
            'Years Experience',
            'Address',
            'Active Batches',
            'Courses Taught',
            'Active Students',
            'Total Students',
            'Average Rating',
            'Feedback Count',
            'Current Courses',
            'Days with Institute',
            'Performance Score',
            'Bio'
        ];
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($teachers as $teacher) {
            $row = [
                $teacher['teacher_code'] ?? '',
                $teacher['full_name'] ?? '',
                $teacher['username'] ?? '',
                $teacher['email'] ?? '',
                $teacher['phone'] ?? '',
                $teacher['cnic'] ?? '',
                $teacher['status_display'] ?? '',
                $teacher['join_date'] ?? '',
                $teacher['employment_start_date'] ?? '',
                $teacher['employment_type'] ?? '',
                $teacher['hourly_rate'] ?? '',
                $teacher['monthly_salary'] ?? '',
                $teacher['gender'] ?? '',
                $teacher['date_of_birth'] ?? '',
                $teacher['age'] ?? '',
                $teacher['education_level'] ?? '',
                $teacher['qualifications'] ?? '',
                $teacher['specialization'] ?? '',
                $teacher['years_experience'] ?? '',
                $teacher['address'] ?? '',
                $teacher['active_batches'] ?? 0,
                $teacher['courses_taught'] ?? 0,
                $teacher['active_students'] ?? 0,
                $teacher['total_students'] ?? 0,
                $teacher['avg_rating'] ?? '',
                $teacher['feedback_count'] ?? 0,
                $teacher['current_courses'] ?? '',
                $teacher['days_with_institute'] ?? 0,
                $teacher['performance_score'] ?? '',
                $teacher['bio'] ?? ''
            ];
            
            fputcsv($output, $row);
        }
        
        // Add summary section
        fputcsv($output, []);
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Teachers', count($teachers)]);
        fputcsv($output, ['Active Teachers', count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active'))]);
        fputcsv($output, ['Average Experience (Years)', number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1)]);
        fputcsv($output, ['Total Active Batches', array_sum(array_column($teachers, 'active_batches'))]);
        fputcsv($output, ['Total Active Students', array_sum(array_column($teachers, 'active_students'))]);
        fputcsv($output, ['Average Rating', calculateOverallAverageRating($teachers)]);
        
        // Add export metadata
        fputcsv($output, []);
        fputcsv($output, ['Export Information']);
        fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
        fputcsv($output, ['Exported By', $_SESSION['full_name'] ?? 'Admin']);
        fputcsv($output, ['Export Type', ucfirst($exportType)]);
        fputcsv($output, ['Total Records', count($teachers)]);
        fputcsv($output, ['Generated By', 'Skills Way Vocational Institute - Teachers Management System']);
        
        fclose($output);
        
    } catch (Exception $e) {
        throw new Exception('CSV export failed: ' . $e->getMessage());
    }
}

/**
 * Export to JSON
 */
function exportToJSON($teachers, $exportType) {
    try {
        // Prepare data structure
        $exportData = [
            'metadata' => [
                'export_date' => date('Y-m-d H:i:s'),
                'exported_by' => $_SESSION['full_name'] ?? 'Admin',
                'export_type' => $exportType,
                'total_records' => count($teachers),
                'institute' => 'Skills Way Vocational Institute',
                'system' => 'Teachers Management System'
            ],
            'summary' => [
                'total_teachers' => count($teachers),
                'active_teachers' => count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active')),
                'average_experience' => number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1),
                'total_active_batches' => array_sum(array_column($teachers, 'active_batches')),
                'total_active_students' => array_sum(array_column($teachers, 'active_students')),
                'average_rating' => calculateOverallAverageRating($teachers)
            ],
            'teachers' => $teachers
        ];
        
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.json';
        
        // Set headers for download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Output JSON with pretty print for readability
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        throw new Exception('JSON export failed: ' . $e->getMessage());
    }
}

/**
 * Export to XML
 */
function exportToXML($teachers, $exportType) {
    try {
        // Create XML document
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Create root element
        $root = $xml->createElement('teachers_export');
        $xml->appendChild($root);
        
        // Add metadata
        $metadata = $xml->createElement('metadata');
        
        $exportDate = $xml->createElement('export_date', date('Y-m-d H:i:s'));
        $metadata->appendChild($exportDate);
        
        $exportedBy = $xml->createElement('exported_by', $_SESSION['full_name'] ?? 'Admin');
        $metadata->appendChild($exportedBy);
        
        $exportTypeElem = $xml->createElement('export_type', $exportType);
        $metadata->appendChild($exportTypeElem);
        
        $totalRecords = $xml->createElement('total_records', count($teachers));
        $metadata->appendChild($totalRecords);
        
        $institute = $xml->createElement('institute', 'Skills Way Vocational Institute');
        $metadata->appendChild($institute);
        
        $system = $xml->createElement('system', 'Teachers Management System');
        $metadata->appendChild($system);
        
        $root->appendChild($metadata);
        
        // Add summary
        $summary = $xml->createElement('summary');
        
        $summaryData = [
            'total_teachers' => count($teachers),
            'active_teachers' => count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active')),
            'average_experience' => number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1),
            'total_active_batches' => array_sum(array_column($teachers, 'active_batches')),
            'total_active_students' => array_sum(array_column($teachers, 'active_students')),
            'average_rating' => calculateOverallAverageRating($teachers)
        ];
        
        foreach ($summaryData as $key => $value) {
            $elem = $xml->createElement($key, $value);
            $summary->appendChild($elem);
        }
        
        $root->appendChild($summary);
        
        // Add teachers data
        $teachersElem = $xml->createElement('teachers');
        
        foreach ($teachers as $teacherData) {
            $teacher = $xml->createElement('teacher');
            
            foreach ($teacherData as $key => $value) {
                // Skip empty values
                if ($value === null || $value === '') {
                    continue;
                }
                
                // Create element with CDATA for text that might contain special characters
                $elem = $xml->createElement(str_replace(' ', '_', strtolower($key)));
                
                // Check if value contains XML special characters
                if (preg_match('/[<>&"\']/', $value)) {
                    $cdata = $xml->createCDATASection($value);
                    $elem->appendChild($cdata);
                } else {
                    $elem->appendChild($xml->createTextNode($value));
                }
                
                $teacher->appendChild($elem);
            }
            
            $teachersElem->appendChild($teacher);
        }
        
        $root->appendChild($teachersElem);
        
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.xml';
        
        // Set headers for download
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Output XML
        echo $xml->saveXML();
        
    } catch (Exception $e) {
        throw new Exception('XML export failed: ' . $e->getMessage());
    }
}

/**
 * Export to HTML (fallback)
 */
function exportToHTML($teachers, $exportType) {
    try {
        // Generate filename
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.html';
        
        // Set headers for download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Start HTML output
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Export - Skills Way Vocational Institute</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #2C3E50; }
        .header h1 { color: #2C3E50; margin-bottom: 10px; }
        .header .subtitle { color: #7F8C8D; font-size: 14px; }
        .summary { background: #F8F9FA; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
        .summary h2 { color: #2C3E50; margin-bottom: 15px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stat-card { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #7F8C8D; font-size: 14px; margin-bottom: 5px; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #2C3E50; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #2C3E50; color: white; padding: 12px; text-align: left; font-weight: bold; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f5f5f5; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #7F8C8D; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-active { background: #D4EDDA; color: #155724; }
        .badge-inactive { background: #F8D7DA; color: #721C24; }
        .badge-pending { background: #FFF3CD; color: #856404; }
        .rating { color: #FFC107; font-weight: bold; }
        .print-button { display: inline-block; background: #2C3E50; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin: 20px 0; }
        .print-button:hover { background: #1A252F; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Skills Way Vocational Institute</h1>
            <h2>Teachers Export Report</h2>
            <div class="subtitle">
                Generated on <?php echo date('F j, Y, g:i a'); ?> | 
                Exported by: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?> | 
                Export Type: <?php echo ucfirst($exportType); ?>
            </div>
        </div>
        
        <div class="summary">
            <h2>Summary Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Teachers</h3>
                    <div class="value"><?php echo count($teachers); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Teachers</h3>
                    <div class="value"><?php echo count(array_filter($teachers, fn($t) => ($t['status_display'] ?? '') === 'Active')); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Experience</h3>
                    <div class="value"><?php echo number_format(array_sum(array_column($teachers, 'years_experience')) / count($teachers), 1); ?> years</div>
                </div>
                <div class="stat-card">
                    <h3>Total Active Batches</h3>
                    <div class="value"><?php echo array_sum(array_column($teachers, 'active_batches')); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Active Students</h3>
                    <div class="value"><?php echo array_sum(array_column($teachers, 'active_students')); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Rating</h3>
                    <div class="value"><?php echo calculateOverallAverageRating($teachers); ?></div>
                </div>
            </div>
        </div>
        
        <a href="javascript:window.print()" class="print-button">Print Report</a>
        
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Join Date</th>
                    <th>Experience</th>
                    <th>Batches</th>
                    <th>Students</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><?php echo htmlspecialchars($teacher['teacher_code'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($teacher['full_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($teacher['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($teacher['phone'] ?? ''); ?></td>
                    <td>
                        <?php 
                        $status = $teacher['status_display'] ?? '';
                        $badgeClass = match($status) {
                            'Active' => 'badge-active',
                            'Inactive' => 'badge-inactive',
                            'Pending Verification' => 'badge-pending',
                            default => ''
                        };
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($teacher['join_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($teacher['years_experience'] ?? 0); ?> yrs</td>
                    <td><?php echo htmlspecialchars($teacher['active_batches'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($teacher['active_students'] ?? 0); ?></td>
                    <td class="rating"><?php echo htmlspecialchars($teacher['avg_rating'] ?? 'No ratings'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated by Skills Way Vocational Institute - Teachers Management System</p>
            <p>Total Records: <?php echo count($teachers); ?> | Export ID: <?php echo uniqid('EXP_'); ?></p>
            <p>This document is confidential and intended for authorized personnel only.</p>
        </div>
    </div>
    
    <script>
        // Auto-print if parameter is set
        if (window.location.search.includes('autoprint')) {
            window.print();
        }
    </script>
</body>
</html>
        <?php
        
    } catch (Exception $e) {
        throw new Exception('HTML export failed: ' . $e->getMessage());
    }
}

/**
 * Calculate overall average rating
 */
function calculateOverallAverageRating($teachers) {
    $totalRating = 0;
    $count = 0;
    
    foreach ($teachers as $teacher) {
        $ratingStr = $teacher['avg_rating'] ?? '';
        if ($ratingStr && $ratingStr !== 'No ratings') {
            // Extract numeric rating from string (e.g., "4.5 / 5.0" -> 4.5)
            preg_match('/(\d+\.?\d*)/', $ratingStr, $matches);
            if (!empty($matches[1])) {
                $totalRating += floatval($matches[1]);
                $count++;
            }
        }
    }
    
    return $count > 0 ? number_format($totalRating / $count, 1) . ' / 5.0' : 'No ratings';
}

/**
 * Log export activity
 */
function logExportActivity($db, $format, $type, $count, $userId) {
    try {
        $stmt = $db->prepare("
            INSERT INTO export_logs 
            (user_id, export_type, format, record_count, export_date, ip_address, user_agent)
            VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            'teachers_' . $type,
            $format,
            $count,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        // Also log security event
        logSecurityEvent('export_completed', [
            'format' => $format,
            'type' => $type,
            'count' => $count,
            'user_id' => $userId,
            'export_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        // Silently fail logging - don't break export
        error_log('Export logging failed: ' . $e->getMessage());
    }
}

/**
 * Get PDO parameter type
 */
function getPDOType($type) {
    return match($type) {
        'i' => PDO::PARAM_INT,
        's' => PDO::PARAM_STR,
        'b' => PDO::PARAM_LOB,
        'n' => PDO::PARAM_NULL,
        default => PDO::PARAM_STR
    };
}

/**
 * Format phone number
 */
function formatPhone($phone) {
    if (empty($phone)) return '';
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 11) {
        // Format as 0300-1234567
        return substr($phone, 0, 4) . '-' . substr($phone, 4);
    } elseif (strlen($phone) === 10) {
        // Format as 300-1234567
        return substr($phone, 0, 3) . '-' . substr($phone, 3);
    }
    
    return $phone;
}

/**
 * Format CNIC
 */
function formatCNIC($cnic) {
    if (empty($cnic)) return '';
    
    $cnic = preg_replace('/[^0-9]/', '', $cnic);
    
    if (strlen($cnic) === 13) {
        // Format as 12345-1234567-1
        return substr($cnic, 0, 5) . '-' . substr($cnic, 5, 7) . '-' . substr($cnic, 12, 1);
    }
    
    return $cnic;
}