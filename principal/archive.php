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
    $db = $database->connect();

    // Fetch all past school years
    $yearQuery = $db->query("
        SELECT 
            id,
            year_label
        FROM school_years
        WHERE is_current = 0
        ORDER BY start_date DESC
    ");
    $schoolYears = $yearQuery->fetchAll(PDO::FETCH_ASSOC);

    // Get selected year
    $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 
        (!empty($schoolYears) ? $schoolYears[0]['id'] : null);

    // Fetch archived classes if a year is selected
    $classes = [];
    if ($selectedYear) {
        $classQuery = $db->prepare("
            SELECT 
                s.id,
                s.section_name,
                s.room_number,
                gl.grade_name,
                COUNT(DISTINCT se.student_id) as student_count
            FROM sections s
            INNER JOIN grade_levels gl ON s.grade_level_id = gl.id
            LEFT JOIN student_enrollments se ON s.id = se.section_id 
                AND se.enrollment_status = 'Enrolled'
            WHERE s.school_year_id = ?
            GROUP BY s.id, s.section_name, s.room_number, gl.grade_name
            ORDER BY gl.grade_name ASC, s.section_name ASC
        ");
        $classQuery->execute([$selectedYear]);
        $classes = $classQuery->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col">
            <h1 class="mb-1">Academic Archives</h1>
            <p class="text-muted mb-4">Historical view of class records from previous years</p>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($schoolYears)): ?>
                <form method="get" class="mb-4">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="yearSelect" class="form-label">Select Academic Year</label>
                            <select id="yearSelect" name="year" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($schoolYears as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= ($selectedYear == $year['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['year_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!empty($classes)): ?>
                        <div class="col-md-8 text-end">
                            <button type="button" onclick="window.print()" class="btn btn-outline-primary">
                                <i class="fas fa-print me-2"></i> Print Report
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="card">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-primary"><i class="fas fa-table me-2"></i>Class Records</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 15%;">Grade Level</th>
                                        <th style="width: 35%;">Section</th>
                                        <th style="width: 20%;">Room</th>
                                        <th style="width: 15%;">Class Adviser</th>
                                        <th style="width: 10%;" class="text-center">Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($classes)): ?>
                                        <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($class['grade_name']) ?></td>
                                                <td><?= htmlspecialchars($class['section_name']) ?></td>
                                                <td><?= htmlspecialchars($class['room_number'] ?? 'N/A') ?></td>
                                                <td class="text-muted">No Adviser Assigned</td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?= $class['student_count'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No class records available for the selected academic year.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h6 class="alert-heading">No Archives Available</h6>
                    <p class="mb-0">There are no past academic years to display.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.table thead th {
    background-color: #f8fafc;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 2px solid #e3e6ea;
    color: #2c3e50;
}
.table td {
    font-size: 0.95rem;
    vertical-align: middle;
}
.badge {
    font-size: 0.95rem;
    padding: 0.5em 1em;
}
.card-header {
    border-bottom: 1px solid #e3e6ea;
}
.card {
    border-radius: 0.5rem;
    box-shadow: 0 2px 8px rgba(44,62,80,0.04);
}
@media print {
    .btn, select, label, .card-header {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table thead th {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #000 !important;
    }
    .badge {
        border: 1px solid #666;
        background-color: transparent !important;
        color: #000 !important;
    }
    @page {
        margin: 2cm;
    }
}
</style>

<?php include '../includes/footer.php'; ?>