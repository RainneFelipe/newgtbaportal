<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

try {
    $database = new Database();
    $pdo = $database->connect();

    // Get all inactive school years
    $stmt = $pdo->query("
        SELECT id, year_label 
        FROM school_years 
        WHERE is_current = 0 
        ORDER BY start_date DESC
    ");
    $inactive_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get selected year if any
    $selected_year = isset($_GET['year']) ? $_GET['year'] : null;

    // Get archived classroom information for selected year
    $archived_classes = [];
    if ($selected_year) {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.class_name,
                gl.grade_name,
                t.first_name as teacher_fname,
                t.last_name as teacher_lname,
                (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id) as student_count
            FROM classes c
            JOIN grade_levels gl ON c.grade_level_id = gl.id
            LEFT JOIN teachers t ON c.adviser_id = t.id
            WHERE c.school_year_id = ?
            ORDER BY gl.grade_name, c.class_name
        ");
        $stmt->execute([$selected_year]);
        $archived_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1>📚 Archived Class Records</h1>
            <p class="text-muted">View classroom information from previous academic years</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($inactive_years)): ?>
        <div class="alert alert-info">
            <h4>No Archived Records</h4>
            <p>There are no inactive school years with archived class records.</p>
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Select School Year</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <option value="">Choose a school year...</option>
                            <?php foreach ($inactive_years as $year): ?>
                                <option value="<?= $year['id'] ?>" 
                                    <?= ($selected_year == $year['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['year_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_year && !empty($archived_classes)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Archived Classes</h5>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        🖨️ Print Class Records
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Grade Level</th>
                                    <th>Class Name</th>
                                    <th>Class Adviser</th>
                                    <th>Number of Students</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archived_classes as $class): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($class['grade_name']) ?></td>
                                        <td><?= htmlspecialchars($class['class_name']) ?></td>
                                        <td>
                                            <?php if ($class['teacher_fname'] && $class['teacher_lname']): ?>
                                                <?= htmlspecialchars($class['teacher_fname'] . ' ' . $class['teacher_lname']) ?>
                                            <?php else: ?>
                                                <em>No adviser assigned</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $class['student_count'] ?> students
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($selected_year): ?>
            <div class="alert alert-info">
                No class records found for the selected school year.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    @media print {
        .no-print, .header, .footer, .card-header, select, button {
            display: none !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .table {
            width: 100% !important;
            margin: 0 !important;
            font-size: 12px !important;
        }
        @page {
            margin: 2cm;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>