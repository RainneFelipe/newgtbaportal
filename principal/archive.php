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

    // Handle AJAX schedule request
    if (isset($_GET['ajax_schedule']) && isset($_GET['section_id']) && isset($_GET['school_year_id'])) {
        $sectionId = (int)$_GET['section_id'];
        $schoolYearId = (int)$_GET['school_year_id'];
        // Example query, adjust columns/table as needed
        $schedStmt = $db->prepare("
            SELECT 
                sc.day,
                sc.start_time,
                sc.end_time,
                subj.subject_code,
                subj.subject_name,
                t.first_name,
                t.last_name
            FROM schedules sc
            INNER JOIN subjects subj ON sc.subject_id = subj.id
            LEFT JOIN teachers t ON sc.teacher_id = t.id
            WHERE sc.section_id = ? AND sc.school_year_id = ?
            ORDER BY FIELD(sc.day, 'Monday','Tuesday','Wednesday','Thursday','Friday'), sc.start_time
        ");
        $schedStmt->execute([$sectionId, $schoolYearId]);
        $schedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($schedules) {
            echo '<table class="table table-bordered table-sm mb-0">';
            echo '<thead><tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                  </tr></thead><tbody>';
            foreach ($schedules as $sched) {
                echo '<tr>
                        <td>' . htmlspecialchars($sched['day']) . '</td>
                        <td>' . htmlspecialchars(substr($sched['start_time'],0,5)) . ' - ' . htmlspecialchars(substr($sched['end_time'],0,5)) . '</td>
                        <td>' . htmlspecialchars($sched['subject_code']) . ' - ' . htmlspecialchars($sched['subject_name']) . '</td>
                        <td>' . htmlspecialchars($sched['first_name'] . ' ' . $sched['last_name']) . '</td>
                      </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="text-center text-muted py-4">No schedule found for this class.</div>';
        }
        exit;
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container-xl py-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="fw-bold mb-0 text-primary">Academic Archives</h2>
                    <p class="text-muted mb-0">Historical view of class records from previous years</p>
                </div>
                <?php if (!empty($classes)): ?>
                <button type="button" onclick="window.print()" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-print me-2"></i> Print Report
                </button>
                <?php endif; ?>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($schoolYears)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label for="yearSelect" class="form-label fw-semibold">Select Academic Year</label>
                                <select id="yearSelect" name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php foreach ($schoolYears as $year): ?>
                                        <option value="<?= $year['id'] ?>" <?= ($selectedYear == $year['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['year_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-primary"><i class="fas fa-table me-2"></i>Class Records</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 15%;">Grade Level</th>
                                        <th style="width: 35%;">Section</th>
                                        <th style="width: 20%;">Room</th>
                                        <th style="width: 15%;">Class Adviser</th>
                                        <th style="width: 10%;" class="text-center">Students</th>
                                        <th style="width: 10%;" class="text-center">Actions</th>
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
                                                <td class="text-center">
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-sm btn-outline-info view-sched-btn"
                                                        data-section-id="<?= $class['id'] ?>"
                                                        data-schoolyear-id="<?= $selectedYear ?>"
                                                    >
                                                        <i class="fas fa-calendar-alt me-1"></i> View Schedule
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
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

<!-- Modal for schedule -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scheduleModalLabel">Class Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="scheduleModalBody">
        <div class="text-center text-muted py-4">Loading schedule...</div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.view-sched-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const sectionId = this.getAttribute('data-section-id');
        const schoolYearId = this.getAttribute('data-schoolyear-id');
        const modalBody = document.getElementById('scheduleModalBody');
        modalBody.innerHTML = '<div class="text-center text-muted py-4">Loading schedule...</div>';
        var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        scheduleModal.show();

        fetch(`archive.php?ajax_schedule=1&section_id=${sectionId}&school_year_id=${schoolYearId}`)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(() => {
                modalBody.innerHTML = '<div class="text-danger text-center py-4">Failed to load schedule.</div>';
            });
    });
});
</script>

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
    .btn, select, label, .card-header, .modal {
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