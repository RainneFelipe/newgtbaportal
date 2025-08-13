<?php
require_once '../includes/auth_check.php';

// Check if user is a finance officer
if (!checkRole('finance')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Tuition Management - GTBA Portal';
$base_url = '../';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->connect();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_tuition':
                    $id = $_POST['tuition_id'];
                    $gtba_tuition_fee = $_POST['gtba_tuition_fee'];
                    $gtba_other_fees = $_POST['gtba_other_fees'];
                    $gtba_miscellaneous_fees = $_POST['gtba_miscellaneous_fees'];
                    
                    $query = "UPDATE tuition_fees 
                              SET gtba_tuition_fee = :tuition_fee,
                                  gtba_other_fees = :other_fees,
                                  gtba_miscellaneous_fees = :misc_fees,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':tuition_fee', $gtba_tuition_fee);
                    $stmt->bindParam(':other_fees', $gtba_other_fees);
                    $stmt->bindParam(':misc_fees', $gtba_miscellaneous_fees);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Tuition fees updated successfully!";
                    } else {
                        $error_message = "Failed to update tuition fees.";
                    }
                    break;
                
                case 'create_tuition_structure':
                    $school_year_id = $_POST['school_year_id'];
                    
                    // Check if tuition structure already exists for this year
                    $check_query = "SELECT COUNT(*) as count FROM tuition_fees WHERE school_year_id = :school_year_id AND is_active = 1";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':school_year_id', $school_year_id);
                    $check_stmt->execute();
                    $existing_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($existing_count > 0) {
                        $error_message = "Tuition fee structure already exists for this school year.";
                    } else {
                        // Get all active grade levels
                        $grade_query = "SELECT id FROM grade_levels WHERE is_active = 1 ORDER BY grade_order";
                        $grade_stmt = $db->prepare($grade_query);
                        $grade_stmt->execute();
                        $grade_levels = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Insert tuition fees for each grade level with default values of 0
                        $insert_query = "INSERT INTO tuition_fees (grade_level_id, school_year_id, gtba_tuition_fee, gtba_other_fees, gtba_miscellaneous_fees, created_by) 
                                        VALUES (:grade_level_id, :school_year_id, 0.00, 0.00, 0.00, :created_by)";
                        $insert_stmt = $db->prepare($insert_query);
                        
                        $created_count = 0;
                        foreach ($grade_levels as $grade) {
                            $insert_stmt->bindParam(':grade_level_id', $grade['id']);
                            $insert_stmt->bindParam(':school_year_id', $school_year_id);
                            $insert_stmt->bindParam(':created_by', $_SESSION['user_id']);
                            
                            if ($insert_stmt->execute()) {
                                $created_count++;
                            }
                        }
                        
                        if ($created_count > 0) {
                            $success_message = "Tuition fee structure created successfully for {$created_count} grade levels!";
                        } else {
                            $error_message = "Failed to create tuition fee structure.";
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
        error_log("Tuition management error: " . $e->getMessage());
    }
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all school years for dropdown
    $query = "SELECT * FROM school_years ORDER BY start_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected school year (default to current)
    $selected_year_id = $_GET['year_id'] ?? $current_year['id'] ?? null;
    
    // Get tuition fees for selected school year
    $query = "SELECT tf.*, gl.grade_name, gl.grade_order, sy.year_label
              FROM tuition_fees tf
              JOIN grade_levels gl ON tf.grade_level_id = gl.id
              JOIN school_years sy ON tf.school_year_id = sy.id
              WHERE tf.school_year_id = :school_year_id
              AND tf.is_active = 1
              ORDER BY gl.grade_order";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $selected_year_id);
    $stmt->execute();
    $tuition_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrollment statistics for the selected year
    $query = "SELECT gl.grade_name, 
                     COUNT(DISTINCT se.student_id) as enrolled_count,
                     tf.gtba_total_amount,
                     (COUNT(DISTINCT se.student_id) * tf.gtba_total_amount) as total_revenue
              FROM grade_levels gl
              LEFT JOIN student_enrollments se ON gl.id = se.grade_level_id 
                                               AND se.school_year_id = :school_year_id
                                               AND se.enrollment_status = 'Enrolled'
              LEFT JOIN tuition_fees tf ON gl.id = tf.grade_level_id 
                                         AND tf.school_year_id = :school_year_id
                                         AND tf.is_active = 1
              WHERE gl.is_active = 1
              GROUP BY gl.id, gl.grade_name, gl.grade_order, tf.gtba_total_amount
              ORDER BY gl.grade_order";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $selected_year_id);
    $stmt->execute();
    $enrollment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load tuition management data.";
    error_log("Tuition management error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Tuition Fee Management</h1>
    <p class="welcome-subtitle">Manage tuition fees and financial settings for all grade levels</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- School Year Selection -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-calendar-alt"></i>
        School Year Selection
    </h3>
    
    <form method="GET" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <label style="color: var(--black); font-weight: 500;">Select School Year:</label>
        <select name="year_id" onchange="this.form.submit()" style="padding: 0.5rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black); font-size: 1rem;">
            <?php foreach ($school_years as $year): ?>
                <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $selected_year_id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($year['year_label']); ?>
                    <?php if ($year['is_active']): ?>
                        (Current)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Tuition Fee Structure -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-money-bill-wave"></i>
        Tuition Fee Structure
        <?php if (!empty($school_years)): ?>
            <span style="color: var(--gray); font-size: 1rem; font-weight: normal;">
                (<?php 
                $selected_year = array_filter($school_years, function($year) use ($selected_year_id) {
                    return $year['id'] == $selected_year_id;
                });
                echo htmlspecialchars(current($selected_year)['year_label'] ?? '');
                ?>)
            </span>
        <?php endif; ?>
    </h3>
    
    <?php if (!empty($tuition_fees)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Tuition Fee</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Other Fees</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Misc. Fees</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total Amount</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tuition_fees as $fee): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);" id="row-<?php echo $fee['id']; ?>">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($fee['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);" id="tuition-<?php echo $fee['id']; ?>">
                                ₱<?php echo number_format($fee['gtba_tuition_fee'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);" id="other-<?php echo $fee['id']; ?>">
                                ₱<?php echo number_format($fee['gtba_other_fees'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);" id="misc-<?php echo $fee['id']; ?>">
                                ₱<?php echo number_format($fee['gtba_miscellaneous_fees'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600; background: var(--light-blue);" id="total-<?php echo $fee['id']; ?>">
                                ₱<?php echo number_format($fee['gtba_total_amount'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <button onclick="editTuition(<?php echo $fee['id']; ?>, '<?php echo $fee['grade_name']; ?>', <?php echo $fee['gtba_tuition_fee']; ?>, <?php echo $fee['gtba_other_fees']; ?>, <?php echo $fee['gtba_miscellaneous_fees']; ?>)" 
                                        style="background: var(--primary-blue); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; transition: background 0.3s;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem; margin-bottom: 2rem;">No tuition fee structure found for the selected school year.</p>
            
            <?php if ($selected_year_id): ?>
                <form method="POST" style="display: inline-block;" onsubmit="return confirmCreateTuitionStructure(this)">
                    <input type="hidden" name="action" value="create_tuition_structure">
                    <input type="hidden" name="school_year_id" value="<?php echo $selected_year_id; ?>">
                    <button type="submit" 
                            style="background: var(--primary-blue); color: white; border: none; padding: 1rem 2rem; border-radius: 10px; cursor: pointer; font-size: 1rem; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,123,255,0.3);"
                            onmouseover="this.style.background='var(--dark-blue)'; this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.background='var(--primary-blue)'; this.style.transform='translateY(0)'">
                        <i class="fas fa-plus-circle"></i> Create Tuition Fee Structure
                    </button>
                </form>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--gray);">
                    This will create tuition fee entries for all grade levels with default fees of ₱0.00<br>
                    You can then edit each grade level's fees individually.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Enrollment Statistics -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-bar"></i>
        Enrollment & Revenue Statistics
    </h3>
    
    <?php if (!empty($enrollment_stats)): ?>
        <?php 
        $total_enrolled = array_sum(array_column($enrollment_stats, 'enrolled_count'));
        $total_revenue = array_sum(array_column($enrollment_stats, 'total_revenue'));
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
                <h4 style="margin: 0; color: var(--dark-blue); font-size: 2rem;"><?php echo number_format($total_enrolled); ?></h4>
                <p style="margin: 0.5rem 0 0 0; color: var(--black);">Total Enrolled Students</p>
            </div>
            <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
                <h4 style="margin: 0; color: var(--dark-blue); font-size: 1.5rem;">₱<?php echo number_format($total_revenue, 2); ?></h4>
                <p style="margin: 0.5rem 0 0 0; color: var(--black);">Total Expected Revenue</p>
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Enrolled Students</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Fee per Student</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollment_stats as $stat): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($stat['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <span style="background: var(--light-blue); padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                    <?php echo number_format($stat['enrolled_count']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);">
                                ₱<?php echo number_format($stat['gtba_total_amount'] ?? 0, 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600;">
                                ₱<?php echo number_format($stat['total_revenue'] ?? 0, 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No enrollment data available for the selected school year.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Tuition Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-edit"></i>
            Edit Tuition Fees
        </h3>
        
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_tuition">
            <input type="hidden" name="tuition_id" id="editTuitionId">
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Grade Level:</label>
                <input type="text" id="editGradeName" readonly style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--light-gray); color: var(--black);">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">GTBA Tuition Fee:</label>
                <input type="number" name="gtba_tuition_fee" id="editTuitionFee" step="0.01" min="0" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                       onchange="updateTotal()">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">GTBA Other Fees:</label>
                <input type="number" name="gtba_other_fees" id="editOtherFees" step="0.01" min="0" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                       onchange="updateTotal()">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">GTBA Miscellaneous Fees:</label>
                <input type="number" name="gtba_miscellaneous_fees" id="editMiscFees" step="0.01" min="0" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                       onchange="updateTotal()">
            </div>
            
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--light-blue); border-radius: 8px;">
                <label style="display: block; color: var(--dark-blue); font-weight: 600; margin-bottom: 0.5rem;">Total Amount:</label>
                <span id="calculatedTotal" style="font-size: 1.5rem; color: var(--dark-blue); font-weight: bold;">₱0.00</span>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-save"></i> Update Fees
                </button>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Finance Dashboard</a>
</div>

<script>
function editTuition(id, gradeName, tuitionFee, otherFees, miscFees) {
    document.getElementById('editTuitionId').value = id;
    document.getElementById('editGradeName').value = gradeName;
    document.getElementById('editTuitionFee').value = tuitionFee;
    document.getElementById('editOtherFees').value = otherFees;
    document.getElementById('editMiscFees').value = miscFees;
    updateTotal();
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function updateTotal() {
    const tuitionFee = parseFloat(document.getElementById('editTuitionFee').value) || 0;
    const otherFees = parseFloat(document.getElementById('editOtherFees').value) || 0;
    const miscFees = parseFloat(document.getElementById('editMiscFees').value) || 0;
    const total = tuitionFee + otherFees + miscFees;
    
    document.getElementById('calculatedTotal').textContent = '₱' + total.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function confirmCreateTuitionStructure(form) {
    const selectedYear = form.querySelector('input[name="school_year_id"]').value;
    const yearSelect = document.querySelector('select[name="year_id"]');
    const yearText = yearSelect.options[yearSelect.selectedIndex].text;
    
    if (confirm(`Are you sure you want to create a tuition fee structure for ${yearText}?\n\nThis will create entries for all grade levels with default fees of ₱0.00.`)) {
        return true;
    }
    return false;
}

// Close modal when clicking outside
document.getElementById('editModal').onclick = function(e) {
    if (e.target === this) {
        closeModal();
    }
}
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
