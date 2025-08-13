<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Tuition & Fees - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get student's current grade level and school year
    $query = "SELECT s.*, gl.grade_name, sy.year_label, sy.id as school_year_id
              FROM students s
              LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
              WHERE s.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student_info && $student_info['current_grade_level_id']) {
        // Get tuition fees for student's grade level
        $query = "SELECT * FROM tuition_fees 
                  WHERE grade_level_id = :grade_level_id 
                  AND school_year_id = :school_year_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':grade_level_id', $student_info['current_grade_level_id']);
        $stmt->bindParam(':school_year_id', $student_info['school_year_id']);
        $stmt->execute();
        
        $tuition_fees = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get payment methods
    $query = "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load tuition information.";
    error_log("Student tuition error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Tuition & Fees</h1>
    <p class="welcome-subtitle">View your tuition fees and payment information</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php elseif (!$student_info || !$student_info['current_grade_level_id']): ?>
    <div class="alert alert-warning">
        Your grade level information is not yet complete. Please contact the registrar's office.
    </div>
<?php else: ?>
    <!-- Student Information -->
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Student Information</h3>
        <div style="background: var(--light-blue); padding: 1rem; border-radius: 10px;">
            <p style="margin: 0.25rem 0; color: var(--black);"><strong>Name:</strong> <?php echo htmlspecialchars($student_info['first_name'] . ' ' . ($student_info['middle_name'] ? $student_info['middle_name'] . ' ' : '') . $student_info['last_name']); ?></p>
            <p style="margin: 0.25rem 0; color: var(--black);"><strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id']); ?></p>
            <p style="margin: 0.25rem 0; color: var(--black);"><strong>Grade Level:</strong> <?php echo htmlspecialchars($student_info['grade_name']); ?></p>
            <p style="margin: 0.25rem 0; color: var(--black);"><strong>School Year:</strong> <?php echo htmlspecialchars($student_info['year_label']); ?></p>
        </div>
    </div>

    <?php if (isset($tuition_fees) && $tuition_fees): ?>
        <!-- Tuition Fees Breakdown -->
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Tuition Fees Breakdown</h3>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                            <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Fee Type</th>
                            <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black);">GTBA Tuition Fee</td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 500;">
                                ₱<?php echo number_format($tuition_fees['gtba_tuition_fee'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black);">GTBA Other Fees</td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 500;">
                                ₱<?php echo number_format($tuition_fees['gtba_other_fees'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black);">GTBA Miscellaneous Fees</td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 500;">
                                ₱<?php echo number_format($tuition_fees['gtba_miscellaneous_fees'], 2); ?>
                            </td>
                        </tr>
                        <tr style="background: var(--light-blue); border-top: 2px solid var(--primary-blue);">
                            <td style="padding: 1rem; color: var(--dark-blue); font-weight: 600; font-size: 1.1rem;">
                                <strong>TOTAL AMOUNT</strong>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600; font-size: 1.2rem;">
                                <strong>₱<?php echo number_format($tuition_fees['gtba_total_amount'], 2); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Tuition fees for your grade level have not been set yet. Please contact the finance office.
        </div>
    <?php endif; ?>

    <!-- Payment Methods -->
    <?php if (!empty($payment_methods)): ?>
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Available Payment Methods</h3>
            <p style="color: var(--gray); margin-bottom: 2rem;">Choose from the following payment options to settle your tuition fees:</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($payment_methods as $method): ?>
                    <div style="border: 2px solid var(--border-gray); border-radius: 10px; padding: 1.5rem; transition: all 0.3s ease;">
                        <h4 style="color: var(--dark-blue); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <?php if ($method['method_type'] === 'Bank'): ?>
                                🏦
                            <?php elseif ($method['method_type'] === 'E-Wallet'): ?>
                                📱
                            <?php else: ?>
                                💵
                            <?php endif; ?>
                            <?php echo htmlspecialchars($method['display_name']); ?>
                        </h4>
                        
                        <?php if ($method['account_name']): ?>
                            <p style="margin: 0.5rem 0; color: var(--black);">
                                <strong>Account Name:</strong> <?php echo htmlspecialchars($method['account_name']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($method['account_number']): ?>
                            <p style="margin: 0.5rem 0; color: var(--black);">
                                <strong>Account Number:</strong> 
                                <span style="font-family: monospace; background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: 5px;">
                                    <?php echo htmlspecialchars($method['account_number']); ?>
                                </span>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($method['bank_name']): ?>
                            <p style="margin: 0.5rem 0; color: var(--black);">
                                <strong>Bank:</strong> <?php echo htmlspecialchars($method['bank_name']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($method['instructions']): ?>
                            <div style="background: var(--light-blue); padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                                <p style="margin: 0; color: var(--black); font-size: 0.9rem;">
                                    <strong>Instructions:</strong><br>
                                    <?php echo htmlspecialchars($method['instructions']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; margin-top: 2rem;">
                <h4 style="color: var(--dark-blue); margin-bottom: 0.5rem;">Important Reminders:</h4>
                <ul style="color: var(--black); margin: 0.5rem 0; padding-left: 1.5rem;">
                    <li>Always keep your payment receipts as proof of payment</li>
                    <li>Submit payment confirmation to the finance office</li>
                    <li>Payment deadlines will be announced separately</li>
                    <li>For questions about payments, contact the finance office during office hours</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
