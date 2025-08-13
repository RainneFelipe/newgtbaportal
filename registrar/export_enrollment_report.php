<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['export']) || $_GET['export'] !== 'csv') {
    header('Location: enrollment_reports.php');
    exit();
}

$database = new Database();
$conn = $database->connect();

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'overview';
$school_year_filter = $_GET['school_year_filter'] ?? '';
$grade_filter = $_GET['grade_filter'] ?? '';
$section_filter = $_GET['section_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

try {
    // Get current school year if no filter selected
    if (empty($school_year_filter)) {
        $current_year_stmt = $conn->prepare("SELECT id FROM school_years WHERE is_active = 1 LIMIT 1");
        $current_year_stmt->execute();
        $current_year = $current_year_stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_year) {
            $school_year_filter = $current_year['id'];
        }
    }

    // Get selected school year info for filename
    $year_label = 'All_Years';
    if (!empty($school_year_filter)) {
        $year_stmt = $conn->prepare("SELECT year_label FROM school_years WHERE id = ?");
        $year_stmt->execute([$school_year_filter]);
        $year_data = $year_stmt->fetch(PDO::FETCH_ASSOC);
        if ($year_data) {
            $year_label = str_replace(' ', '_', $year_data['year_label']);
        }
    }

    // Build WHERE conditions
    $where_conditions = ["s.is_active = 1"];
    $params = [];

    if (!empty($school_year_filter)) {
        $where_conditions[] = "s.current_school_year_id = ?";
        $params[] = $school_year_filter;
    }

    if (!empty($grade_filter)) {
        $where_conditions[] = "s.current_grade_level_id = ?";
        $params[] = $grade_filter;
    }

    if (!empty($section_filter)) {
        $where_conditions[] = "s.current_section_id = ?";
        $params[] = $section_filter;
    }

    if (!empty($status_filter)) {
        $where_conditions[] = "s.enrollment_status = ?";
        $params[] = $status_filter;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Set headers for CSV download
    $filename = "Enrollment_Report_" . ucfirst($report_type) . "_" . $year_label . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Generate CSV based on report type
    switch ($report_type) {
        case 'overview':
            // CSV headers for overview
            fputcsv($output, [
                'Report Type', 'School Year', 'Total Students', 'Enrolled Students', 
                'Dropped Students', 'Transferred Students', 'Graduated Students',
                'New Students', 'Transfer Students', 'Continuing Students',
                'Male Students', 'Female Students', 'Generated Date'
            ]);

            // Get overview data
            $overview_sql = "SELECT 
                            COUNT(*) as total_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Enrolled' THEN 1 END) as enrolled_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Dropped' THEN 1 END) as dropped_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Transferred' THEN 1 END) as transferred_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Graduated' THEN 1 END) as graduated_students,
                            COUNT(CASE WHEN s.student_type = 'New' THEN 1 END) as new_students,
                            COUNT(CASE WHEN s.student_type = 'Transfer' THEN 1 END) as transfer_students,
                            COUNT(CASE WHEN s.student_type = 'Continuing' THEN 1 END) as continuing_students,
                            COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                            COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                            FROM students s $where_clause";
            
            $overview_stmt = $conn->prepare($overview_sql);
            $overview_stmt->execute($params);
            $overview_data = $overview_stmt->fetch(PDO::FETCH_ASSOC);

            fputcsv($output, [
                'Overview Summary',
                str_replace('_', ' ', $year_label),
                $overview_data['total_students'],
                $overview_data['enrolled_students'],
                $overview_data['dropped_students'],
                $overview_data['transferred_students'],
                $overview_data['graduated_students'],
                $overview_data['new_students'],
                $overview_data['transfer_students'],
                $overview_data['continuing_students'],
                $overview_data['male_students'],
                $overview_data['female_students'],
                date('Y-m-d H:i:s')
            ]);
            break;

        case 'by_grade':
            // CSV headers for grade report
            fputcsv($output, [
                'Grade Level', 'Level Type', 'Total Students', 'Enrolled Students',
                'Male Students', 'Female Students', 'Enrollment Rate (%)'
            ]);

            $grade_sql = "SELECT 
                         gl.grade_name,
                         gl.level_type,
                         COUNT(s.id) as total_students,
                         COUNT(CASE WHEN s.enrollment_status = 'Enrolled' THEN 1 END) as enrolled_students,
                         COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                         COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                         FROM grade_levels gl
                         LEFT JOIN students s ON gl.id = s.current_grade_level_id AND s.is_active = 1";
            
            $grade_params = [];
            if (!empty($school_year_filter)) {
                $grade_sql .= " AND s.current_school_year_id = ?";
                $grade_params[] = $school_year_filter;
            }
            if (!empty($status_filter)) {
                $grade_sql .= " AND s.enrollment_status = ?";
                $grade_params[] = $status_filter;
            }
            
            $grade_sql .= " WHERE gl.is_active = 1 
                           GROUP BY gl.id, gl.grade_name, gl.level_type, gl.grade_order 
                           ORDER BY gl.grade_order";
            
            $grade_stmt = $conn->prepare($grade_sql);
            $grade_stmt->execute($grade_params);
            $grade_data = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($grade_data as $grade) {
                $enrollment_rate = $grade['total_students'] > 0 ? 
                    round(($grade['enrolled_students'] / $grade['total_students'] * 100), 2) : 0;
                
                fputcsv($output, [
                    $grade['grade_name'],
                    $grade['level_type'],
                    $grade['total_students'],
                    $grade['enrolled_students'],
                    $grade['male_students'],
                    $grade['female_students'],
                    $enrollment_rate
                ]);
            }
            break;

        case 'by_section':
            // CSV headers for section report
            fputcsv($output, [
                'Section Name', 'Grade Level', 'Current Enrollment',
                'Male Students', 'Female Students'
            ]);

            $section_sql = "SELECT 
                           sec.section_name,
                           gl.grade_name,
                           COUNT(s.id) as current_enrollment,
                           COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                           COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                           FROM sections sec
                           LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                           LEFT JOIN students s ON sec.id = s.current_section_id AND s.enrollment_status = 'Enrolled' AND s.is_active = 1";
            
            $section_params = [];
            if (!empty($school_year_filter)) {
                $section_sql .= " AND s.current_school_year_id = ?";
                $section_params[] = $school_year_filter;
            }
            if (!empty($grade_filter)) {
                $section_sql .= " AND sec.grade_level_id = ?";
                $section_params[] = $grade_filter;
            }
            
            $section_sql .= " WHERE sec.is_active = 1
                             GROUP BY sec.id, sec.section_name, gl.grade_name
                             ORDER BY gl.grade_order, sec.section_name";
            
            $section_stmt = $conn->prepare($section_sql);
            $section_stmt->execute($section_params);
            $section_data = $section_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($section_data as $section) {
                fputcsv($output, [
                    $section['section_name'],
                    $section['grade_name'],
                    $section['current_enrollment'],
                    $section['male_students'],
                    $section['female_students']
                ]);
            }
            break;

        case 'detailed':
            // CSV headers for detailed report
            fputcsv($output, [
                'Student ID', 'LRN', 'Last Name', 'First Name', 'Middle Name',
                'Gender', 'Date of Birth', 'Age', 'Student Type', 'Enrollment Status',
                'Grade Level', 'Section', 'School Year', 'Registration Date'
            ]);

            $detailed_sql = "SELECT 
                            s.student_id,
                            s.lrn,
                            s.last_name,
                            s.first_name,
                            s.middle_name,
                            s.gender,
                            s.date_of_birth,
                            s.student_type,
                            s.enrollment_status,
                            gl.grade_name,
                            sec.section_name,
                            sy.year_label,
                            s.created_at as registration_date
                            FROM students s
                            LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                            LEFT JOIN sections sec ON s.current_section_id = sec.id
                            LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
                            $where_clause
                            ORDER BY gl.grade_order, sec.section_name, s.last_name, s.first_name";
            
            $detailed_stmt = $conn->prepare($detailed_sql);
            $detailed_stmt->execute($params);
            $detailed_data = $detailed_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detailed_data as $student) {
                $age = date_diff(date_create($student['date_of_birth']), date_create('today'))->y;
                
                fputcsv($output, [
                    $student['student_id'],
                    $student['lrn'],
                    $student['last_name'],
                    $student['first_name'],
                    $student['middle_name'],
                    $student['gender'],
                    $student['date_of_birth'],
                    $age,
                    $student['student_type'],
                    $student['enrollment_status'],
                    $student['grade_name'] ?: 'Not assigned',
                    $student['section_name'] ?: 'Not assigned',
                    $student['year_label'] ?: 'Not assigned',
                    date('Y-m-d', strtotime($student['registration_date']))
                ]);
            }
            break;

        default:
            fputcsv($output, ['Error', 'Invalid report type specified']);
            break;
    }

    fclose($output);
    exit();

} catch (Exception $e) {
    error_log("Export enrollment report error: " . $e->getMessage());
    
    // If headers not sent yet, send error response
    if (!headers_sent()) {
        header('Content-Type: text/plain');
        echo "Error generating export: " . $e->getMessage();
    }
    exit();
}
?>
