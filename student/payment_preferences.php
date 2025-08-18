<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$page_title = 'Payment Preferences - GTBA Portal';
$base_url = '../';

$success_message = '';
$error_message = '';

// Get student information
$student_query = "SELECT s.id, s.current_grade_level_id, gl.grade_name 
                 FROM students s
                 LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                 WHERE s.user_id = ?";
$student_stmt = $db->prepare($student_query);
$student_stmt->execute([$_SESSION['user_id']]);
$student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_info || !$student_info['current_grade_level_id']) {
    $error_message = "Student grade information not found. Please contact the registrar.";
} else {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
        try {
            $selected_terms = $_POST['payment_terms'] ?? [];
            
            // Clear existing preferences
            $clear_query = "DELETE FROM student_payment_preferences WHERE student_id = ?";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->execute([$student_info['id']]);
            
            // Insert new preferences
            if (!empty($selected_terms)) {
                $insert_query = "INSERT INTO student_payment_preferences (student_id, payment_term_id) VALUES (?, ?)";
                $insert_stmt = $db->prepare($insert_query);
                
                foreach ($selected_terms as $term_id) {
                    $insert_stmt->execute([$student_info['id'], $term_id]);
                }
                
                $success_message = "Payment preferences saved successfully!";
            }
        } catch (Exception $e) {
            $error_message = "Error saving preferences: " . $e->getMessage();
        }
    }
    
    // Get available payment terms for student's grade
    $terms_query = "SELECT pt.*, sy.year_label 
                   FROM payment_terms pt
                   JOIN school_years sy ON pt.school_year_id = sy.id
                   WHERE sy.is_active = 1 
                   AND pt.is_active = 1 
                   AND pt.grade_level_id = ?
                   ORDER BY pt.term_name";
    $terms_stmt = $db->prepare($terms_query);
    $terms_stmt->execute([$student_info['current_grade_level_id']]);
    $available_terms = $terms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current preferences
    $prefs_query = "SELECT payment_term_id FROM student_payment_preferences WHERE student_id = ?";
    $prefs_stmt = $db->prepare($prefs_query);
    $prefs_stmt->execute([$student_info['id']]);
    $current_preferences = $prefs_stmt->fetchAll(PDO::FETCH_COLUMN);
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Preferences</h1>
    <p class="welcome-subtitle">Select your preferred payment terms to receive targeted reminders</p>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;">
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger" style="margin-bottom: 2rem;">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<?php if ($student_info && !empty($available_terms)): ?>
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
    <div style="background: var(--light-blue); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <h4 style="color: var(--dark-blue); margin: 0 0 0.5rem 0;">Student Information</h4>
        <p style="margin: 0; color: var(--gray);">Grade Level: <strong><?php echo htmlspecialchars($student_info['grade_name']); ?></strong></p>
    </div>
    
    <form method="POST">
        <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">📅 Available Payment Terms</h3>
        <p style="color: var(--gray); margin-bottom: 2rem;">
            Select the payment terms you want to enroll in. You will receive reminders only for the terms you choose.
        </p>
        
        <div style="display: grid; gap: 1rem;">
            <?php foreach ($available_terms as $term): ?>
                <div class="payment-term-option" style="border: 2px solid var(--border-gray); border-radius: 12px; padding: 1.5rem; transition: all 0.3s ease;">
                    <label style="display: flex; align-items: flex-start; gap: 1rem; cursor: pointer;">
                        <input type="checkbox" name="payment_terms[]" value="<?php echo $term['id']; ?>"
                               <?php echo in_array($term['id'], $current_preferences) ? 'checked' : ''; ?>
                               style="margin-top: 0.2rem; transform: scale(1.2);">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--dark-blue);">
                                <?php echo htmlspecialchars($term['term_name']); ?>
                                <span style="font-size: 0.9rem; color: var(--gray); font-weight: normal;">
                                    (<?php echo htmlspecialchars($term['year_label']); ?>)
                                </span>
                            </h4>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <?php if ($term['term_type'] === 'full_payment'): ?>
                                    <div>
                                        <strong>Payment Type:</strong> Full Payment<br>
                                        <strong>Amount:</strong> ₱<?php echo number_format($term['full_payment_amount'], 2); ?>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <strong>Payment Type:</strong> Installment Plan<br>
                                        <strong>Down Payment:</strong> ₱<?php echo number_format($term['down_payment_amount'], 2); ?>
                                    </div>
                                    <div>
                                        <strong>Monthly Fee:</strong> ₱<?php echo number_format($term['monthly_fee_amount'], 2); ?><br>
                                        <strong>Installments:</strong> <?php echo $term['number_of_installments']; ?> payments
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($term['description']): ?>
                                <p style="color: var(--gray); margin: 0; font-size: 0.9rem;">
                                    <?php echo nl2br(htmlspecialchars($term['description'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-gray);">
            <button type="submit" name="save_preferences" 
                    style="background: var(--primary-blue); color: white; padding: 0.75rem 2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">
                Save Preferences
            </button>
            <a href="dashboard.php" 
               style="background: var(--gray); color: white; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin-left: 1rem; display: inline-block;">
                Cancel
            </a>
        </div>
    </form>
</div>
<?php else: ?>
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
    <p style="color: var(--gray); margin: 0;">No payment terms available for your current grade level.</p>
    <a href="dashboard.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
</div>
<?php endif; ?>

<style>
.payment-term-option:hover {
    border-color: var(--primary-blue);
    background: var(--light-blue);
}

.payment-term-option input[type="checkbox"]:checked + div {
    color: var(--dark-blue);
}

.payment-term-option:has(input[type="checkbox"]:checked) {
    border-color: var(--primary-blue);
    background: var(--light-blue);
    box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.alert-danger {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
