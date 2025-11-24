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
            $selected_term = $_POST['payment_term'] ?? null;
            
            // Clear existing preferences
            $clear_query = "DELETE FROM student_payment_preferences WHERE student_id = ?";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->execute([$student_info['id']]);
            
            // Insert new preference (only one)
            if ($selected_term) {
                $insert_query = "INSERT INTO student_payment_preferences (student_id, payment_term_id) VALUES (?, ?)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([$student_info['id'], $selected_term]);
                
                $success_message = "Payment preference saved successfully!";
            } else {
                $success_message = "Payment preference cleared successfully!";
            }
        } catch (Exception $e) {
            $error_message = "Error saving preference: " . $e->getMessage();
        }
    }
    
    // Get tuition fee breakdown for student's grade
    $tuition_query = "SELECT tf.*, sy.year_label
                     FROM tuition_fees tf
                     JOIN school_years sy ON tf.school_year_id = sy.id
                     WHERE sy.is_active = 1
                     AND tf.is_active = 1
                     AND tf.grade_level_id = ?
                     LIMIT 1";
    $tuition_stmt = $db->prepare($tuition_query);
    $tuition_stmt->execute([$student_info['current_grade_level_id']]);
    $tuition_breakdown = $tuition_stmt->fetch(PDO::FETCH_ASSOC);
    
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
    
    // Get current preference (single value)
    $prefs_query = "SELECT payment_term_id FROM student_payment_preferences WHERE student_id = ? LIMIT 1";
    $prefs_stmt = $db->prepare($prefs_query);
    $prefs_stmt->execute([$student_info['id']]);
    $current_preference = $prefs_stmt->fetchColumn();
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Preferences</h1>
    <p class="welcome-subtitle">Select your preferred payment term for tuition payment reminders</p>
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
<!-- Student Information Section -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
    <div style="background: var(--light-blue); border-radius: 10px; padding: 1.5rem;">
        <h4 style="color: var(--dark-blue); margin: 0 0 0.5rem 0;">Student Information</h4>
        <p style="margin: 0; color: var(--gray);">Grade Level: <strong><?php echo htmlspecialchars($student_info['grade_name']); ?></strong></p>
    </div>
</div>

<!-- Two Column Layout: Payment Terms + Fee Breakdown -->
<div class="payment-preferences-grid" style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start;">
    <!-- Left Column: Payment Terms -->
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <form method="POST">
            <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">📅 Available Payment Terms</h3>
            <p style="color: var(--gray); margin-bottom: 2rem;">
                Select the payment term you want to enroll in. You will receive reminders based on your chosen term.
            </p>
        
        <div style="display: grid; gap: 1rem;">
            <?php foreach ($available_terms as $term): ?>
                <div class="payment-term-option" style="border: 2px solid var(--border-gray); border-radius: 12px; padding: 1.5rem; transition: all 0.3s ease;">
                    <label style="display: flex; align-items: flex-start; gap: 1rem; cursor: pointer;">
                        <input type="radio" name="payment_term" value="<?php echo $term['id']; ?>"
                               <?php echo ($term['id'] == $current_preference) ? 'checked' : ''; ?>
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
                                        <?php if ($term['full_payment_discount_percentage'] > 0): ?>
                                            <br>
                                            <span style="background: #4CAF50; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.85rem; display: inline-block; margin-top: 0.3rem;">
                                                <i class="fas fa-tag"></i> <?php echo $term['full_payment_discount_percentage']; ?>% Discount
                                            </span>
                                            <?php 
                                            $original_amount = $term['full_payment_amount'];
                                            $discount_amount = $original_amount * ($term['full_payment_discount_percentage'] / 100);
                                            $discounted_amount = $original_amount - $discount_amount;
                                            ?>
                                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--dark-blue);">
                                                <strong>Discounted Amount:</strong> ₱<?php echo number_format($discounted_amount, 2); ?><br>
                                                <span style="color: #4CAF50; font-weight: 600;">You Save: ₱<?php echo number_format($discount_amount, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($term['full_payment_due_date']): ?>
                                            <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                                                <i class="fas fa-calendar-alt"></i> <strong>Due:</strong> <?php echo date('M d, Y', strtotime($term['full_payment_due_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <strong>Payment Type:</strong> Installment Plan<br>
                                        <strong>Down Payment:</strong> ₱<?php echo number_format($term['down_payment_amount'], 2); ?>
                                        <?php if ($term['down_payment_due_date']): ?>
                                            <br><span style="font-size: 0.85rem; color: var(--gray);">
                                                Due: <?php echo date('M d, Y', strtotime($term['down_payment_due_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong>Monthly Fee:</strong> ₱<?php echo number_format($term['monthly_fee_amount'], 2); ?><br>
                                        <strong>Installments:</strong> <?php echo $term['number_of_installments']; ?> payments
                                        <?php 
                                        $total_installment = $term['down_payment_amount'] + ($term['monthly_fee_amount'] * $term['number_of_installments']);
                                        ?>
                                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--dark-blue);">
                                            <strong>Total:</strong> ₱<?php echo number_format($total_installment, 2); ?>
                                        </div>
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

<!-- Right Column: Fee Breakdown (Sticky) -->
<?php if ($tuition_breakdown): ?>
<div class="fee-breakdown-sticky" style="position: sticky; top: 2rem;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 1.5rem; color: white; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
        <h3 style="color: white; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
            <i class="fas fa-receipt"></i>
            Tuition Fee Breakdown
        </h3>
        <p style="font-size: 0.85rem; opacity: 0.9; margin: 0 0 1rem 0;">
            <?php echo htmlspecialchars($tuition_breakdown['year_label']); ?>
        </p>
        
        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 1.25rem; border: 1px solid rgba(255, 255, 255, 0.2);">
            <div style="display: grid; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
                    <span style="font-size: 0.9rem; opacity: 0.95;">
                        <i class="fas fa-book" style="margin-right: 0.3rem; width: 18px;"></i>
                        Tuition Fee
                    </span>
                    <span style="font-size: 0.95rem; font-weight: 600;">
                        ₱<?php echo number_format($tuition_breakdown['gtba_tuition_fee'], 2); ?>
                    </span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
                    <span style="font-size: 0.9rem; opacity: 0.95;">
                        <i class="fas fa-clipboard-list" style="margin-right: 0.3rem; width: 18px;"></i>
                        Other Fees
                    </span>
                    <span style="font-size: 0.95rem; font-weight: 600;">
                        ₱<?php echo number_format($tuition_breakdown['gtba_other_fees'], 2); ?>
                    </span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
                    <span style="font-size: 0.9rem; opacity: 0.95;">
                        <i class="fas fa-file-invoice" style="margin-right: 0.3rem; width: 18px;"></i>
                        Misc. Fees
                    </span>
                    <span style="font-size: 0.95rem; font-weight: 600;">
                        ₱<?php echo number_format($tuition_breakdown['gtba_miscellaneous_fees'], 2); ?>
                    </span>
                </div>
            </div>
            
            <div style="background: rgba(255, 255, 255, 0.25); border-radius: 8px; padding: 1rem; margin-top: 1rem; border: 2px solid rgba(255, 255, 255, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 1rem; font-weight: 700;">
                        <i class="fas fa-calculator" style="margin-right: 0.3rem;"></i>
                        Total
                    </span>
                    <span style="font-size: 1.3rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        ₱<?php echo number_format($tuition_breakdown['gtba_total_amount'], 2); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
<!-- End Two Column Layout -->
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

.payment-term-option input[type="radio"]:checked + div {
    color: var(--dark-blue);
}

.payment-term-option:has(input[type="radio"]:checked) {
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

/* Responsive layout for payment preferences */
@media (max-width: 1200px) {
    .payment-preferences-grid {
        grid-template-columns: 1fr !important;
    }
    
    .fee-breakdown-sticky {
        position: relative !important;
        top: 0 !important;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
