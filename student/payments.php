<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Payment Submission - GTBA Portal';
$base_url = '../';

$database = new Database();
$db = $database->connect();

$success_message = '';
$error_message = '';

// Get student information
$student_query = "SELECT st.*, gl.grade_name, sy.year_label 
                  FROM students st 
                  LEFT JOIN grade_levels gl ON st.current_grade_level_id = gl.id
                  LEFT JOIN school_years sy ON st.current_school_year_id = sy.id
                  WHERE st.user_id = :user_id";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(':user_id', $_SESSION['user_id']);
$student_stmt->execute();
$student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_info) {
    $error_message = "Student information not found.";
} else {
    // Get student's payment preference
    $preference_query = "SELECT payment_term_id FROM student_payment_preferences WHERE student_id = :student_id LIMIT 1";
    $preference_stmt = $db->prepare($preference_query);
    $preference_stmt->bindParam(':student_id', $student_info['id']);
    $preference_stmt->execute();
    $preferred_term_id = $preference_stmt->fetchColumn();
    
    // Get applicable payment terms
    $terms_query = "SELECT pt.* 
                    FROM payment_terms pt 
                    WHERE (pt.grade_level_id = :grade_level_id OR pt.grade_level_id IS NULL) 
                    AND pt.school_year_id = :school_year_id 
                    AND pt.is_active = 1
                    ORDER BY pt.is_default DESC, pt.term_name";
    $terms_stmt = $db->prepare($terms_query);
    $terms_stmt->bindParam(':grade_level_id', $student_info['current_grade_level_id']);
    $terms_stmt->bindParam(':school_year_id', $student_info['current_school_year_id']);
    $terms_stmt->execute();
    $payment_terms = $terms_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing payments for this student
    $payments_query = "SELECT sp.*, pt.term_name, pt.term_type, pt.number_of_installments
                       FROM student_payments sp
                       JOIN payment_terms pt ON sp.payment_term_id = pt.id
                       WHERE sp.student_id = :student_id
                       ORDER BY sp.submitted_at DESC";
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->bindParam(':student_id', $student_info['id']);
    $payments_stmt->execute();
    $existing_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate remaining balance for preferred payment term if it's an installment
    $balance_info = null;
    if ($preferred_term_id) {
        // Get the preferred payment term details
        $preferred_term_query = "SELECT * FROM payment_terms WHERE id = :term_id";
        $preferred_term_stmt = $db->prepare($preferred_term_query);
        $preferred_term_stmt->bindParam(':term_id', $preferred_term_id);
        $preferred_term_stmt->execute();
        $preferred_term = $preferred_term_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($preferred_term && $preferred_term['term_type'] === 'installment') {
            // Calculate total amount owed
            $total_amount = $preferred_term['down_payment_amount'] + ($preferred_term['monthly_fee_amount'] * $preferred_term['number_of_installments']);
            
            // Get all verified payments for this payment term
            $paid_query = "SELECT COALESCE(SUM(amount_paid), 0) as total_paid
                           FROM student_payments
                           WHERE student_id = :student_id
                           AND payment_term_id = :payment_term_id
                           AND verification_status = 'verified'";
            $paid_stmt = $db->prepare($paid_query);
            $paid_stmt->bindParam(':student_id', $student_info['id']);
            $paid_stmt->bindParam(':payment_term_id', $preferred_term_id);
            $paid_stmt->execute();
            $total_paid = $paid_stmt->fetchColumn();
            
            // Get payment breakdown
            $payments_breakdown_query = "SELECT payment_type, installment_number, amount_paid, verification_status, submitted_at
                                         FROM student_payments
                                         WHERE student_id = :student_id
                                         AND payment_term_id = :payment_term_id
                                         ORDER BY 
                                           CASE payment_type 
                                             WHEN 'down_payment' THEN 1 
                                             WHEN 'monthly_installment' THEN 2 
                                           END,
                                           installment_number";
            $breakdown_stmt = $db->prepare($payments_breakdown_query);
            $breakdown_stmt->bindParam(':student_id', $student_info['id']);
            $breakdown_stmt->bindParam(':payment_term_id', $preferred_term_id);
            $breakdown_stmt->execute();
            $payments_breakdown = $breakdown_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $balance_info = [
                'term_name' => $preferred_term['term_name'],
                'total_amount' => $total_amount,
                'total_paid' => $total_paid,
                'remaining_balance' => $total_amount - $total_paid,
                'payments_breakdown' => $payments_breakdown,
                'down_payment_amount' => $preferred_term['down_payment_amount'],
                'monthly_fee_amount' => $preferred_term['monthly_fee_amount'],
                'number_of_installments' => $preferred_term['number_of_installments']
            ];
        }
    }
}

// Determine next payment details based on payment history and preference
$next_payment = null;
if ($preferred_term_id && !empty($payment_terms)) {
    // Find the preferred payment term
    $selected_term = null;
    foreach ($payment_terms as $term) {
        if ($term['id'] == $preferred_term_id) {
            $selected_term = $term;
            break;
        }
    }
    
    if ($selected_term) {
        // Check existing verified payments for this term
        $history_query = "SELECT payment_type, installment_number, verification_status
                         FROM student_payments
                         WHERE student_id = :student_id
                           AND payment_term_id = :payment_term_id
                         ORDER BY 
                           CASE payment_type
                             WHEN 'down_payment' THEN 1
                             WHEN 'monthly_installment' THEN 2
                             WHEN 'full_payment' THEN 3
                             ELSE 4
                           END,
                           installment_number ASC";
        
        $history_stmt = $db->prepare($history_query);
        $history_stmt->bindParam(':student_id', $student_info['id']);
        $history_stmt->bindParam(':payment_term_id', $preferred_term_id);
        $history_stmt->execute();
        $payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determine next payment based on preference and history
        $has_verified_down_payment = false;
        $highest_verified_installment = 0;
        $has_pending_payment = false;
        
        foreach ($payment_history as $payment) {
            if ($payment['payment_type'] === 'down_payment' && $payment['verification_status'] === 'verified') {
                $has_verified_down_payment = true;
            }
            if ($payment['payment_type'] === 'monthly_installment' && $payment['verification_status'] === 'verified') {
                $highest_verified_installment = max($highest_verified_installment, $payment['installment_number']);
            }
            if ($payment['verification_status'] === 'pending') {
                $has_pending_payment = true;
            }
        }
        
        // Only auto-fill if no pending payments
        if (!$has_pending_payment) {
            // Check if student chose installment plan
            if ($selected_term['term_type'] === 'installment') {
                // Installment plan logic
                if (!$has_verified_down_payment) {
                    // Next payment is down payment
                    $next_payment = [
                        'type' => 'down_payment',
                        'type_label' => 'Down Payment',
                        'installment_number' => null,
                        'amount' => $selected_term['down_payment_amount']
                    ];
                } else {
                    // Down payment verified, determine next installment
                    $next_installment = $highest_verified_installment + 1;
                    
                    if ($next_installment <= $selected_term['number_of_installments']) {
                        $next_payment = [
                            'type' => 'monthly_installment',
                            'type_label' => 'Monthly Installment ' . $next_installment,
                            'installment_number' => $next_installment,
                            'amount' => $selected_term['monthly_fee_amount']
                        ];
                    }
                }
            } else if ($selected_term['term_type'] === 'full_payment') {
                // Full payment - check if not already paid
                $has_full_payment = false;
                foreach ($payment_history as $payment) {
                    if ($payment['payment_type'] === 'full_payment' && $payment['verification_status'] === 'verified') {
                        $has_full_payment = true;
                        break;
                    }
                }
                
                if (!$has_full_payment) {
                    $next_payment = [
                        'type' => 'full_payment',
                        'type_label' => 'Full Payment',
                        'installment_number' => null,
                        'amount' => $selected_term['full_tuition_amount']
                    ];
                }
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    try {
        $payment_term_id = $_POST['payment_term_id'];
        $payment_type = $_POST['payment_type'];
        $installment_number = null;
        
        // Auto-increment installment number for monthly installments
        if ($payment_type === 'monthly_installment') {
            // Get the highest installment number already paid for this payment term
            $installment_query = "SELECT COALESCE(MAX(installment_number), 0) as last_installment
                                 FROM student_payments
                                 WHERE student_id = :student_id
                                 AND payment_term_id = :payment_term_id
                                 AND payment_type = 'monthly_installment'
                                 AND verification_status != 'rejected'";
            $installment_stmt = $db->prepare($installment_query);
            $installment_stmt->bindParam(':student_id', $student_info['id']);
            $installment_stmt->bindParam(':payment_term_id', $payment_term_id);
            $installment_stmt->execute();
            $last_installment = $installment_stmt->fetchColumn();
            
            // Set to next installment number
            $installment_number = $last_installment + 1;
        }
        
        $amount_paid = $_POST['amount_paid'];
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $reference_number = $_POST['reference_number'] ?? '';
        $proof_notes = $_POST['proof_notes'] ?? '';

        // Handle file upload
        $proof_image = null;
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Check file size (max 5MB)
                if ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('File size too large. Maximum size is 5MB.');
                }
                
                $new_filename = 'payment_' . $student_info['id'] . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $upload_path)) {
                    $proof_image = 'uploads/payment_proofs/' . $new_filename;
                } else {
                    throw new Exception('Failed to upload proof image.');
                }
            } else {
                throw new Exception('Invalid file type. Please upload JPG, PNG, or PDF files only.');
            }
        }

        // Insert payment submission
        $insert_query = "INSERT INTO student_payments 
                        (student_id, payment_term_id, payment_type, installment_number, amount_paid, 
                         payment_date, payment_method, reference_number, proof_image, proof_notes, verification_status)
                        VALUES 
                        (:student_id, :payment_term_id, :payment_type, :installment_number, :amount_paid,
                         :payment_date, :payment_method, :reference_number, :proof_image, :proof_notes, 'pending')";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':student_id', $student_info['id']);
        $insert_stmt->bindParam(':payment_term_id', $payment_term_id);
        $insert_stmt->bindParam(':payment_type', $payment_type);
        $insert_stmt->bindParam(':installment_number', $installment_number);
        $insert_stmt->bindParam(':amount_paid', $amount_paid);
        $insert_stmt->bindParam(':payment_date', $payment_date);
        $insert_stmt->bindParam(':payment_method', $payment_method);
        $insert_stmt->bindParam(':reference_number', $reference_number);
        $insert_stmt->bindParam(':proof_image', $proof_image);
        $insert_stmt->bindParam(':proof_notes', $proof_notes);
        
        if ($insert_stmt->execute()) {
            $payment_id = $db->lastInsertId();
            
            // Handle additional files
            if (isset($_FILES['additional_files']) && is_array($_FILES['additional_files']['name'])) {
                $upload_count = 0;
                for ($i = 0; $i < count($_FILES['additional_files']['name']); $i++) {
                    if ($_FILES['additional_files']['error'][$i] === UPLOAD_ERR_OK && $upload_count < 3) {
                        $file_extension = strtolower(pathinfo($_FILES['additional_files']['name'][$i], PATHINFO_EXTENSION));
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            // Check file size (max 5MB)
                            if ($_FILES['additional_files']['size'][$i] > 5 * 1024 * 1024) {
                                continue; // Skip oversized files
                            }
                            
                            $new_filename = 'additional_' . $student_info['id'] . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['additional_files']['tmp_name'][$i], $upload_path)) {
                                // Insert into payment_uploads table
                                $upload_insert = "INSERT INTO payment_uploads 
                                                 (payment_id, original_filename, stored_filename, file_path, file_size, mime_type)
                                                 VALUES 
                                                 (:payment_id, :original_filename, :stored_filename, :file_path, :file_size, :mime_type)";
                                $upload_stmt = $db->prepare($upload_insert);
                                
                                // Assign values to variables for bindParam
                                $original_filename = $_FILES['additional_files']['name'][$i];
                                $file_path = 'uploads/payment_proofs/' . $new_filename;
                                $file_size = $_FILES['additional_files']['size'][$i];
                                $mime_type = $_FILES['additional_files']['type'][$i];
                                
                                $upload_stmt->bindParam(':payment_id', $payment_id);
                                $upload_stmt->bindParam(':original_filename', $original_filename);
                                $upload_stmt->bindParam(':stored_filename', $new_filename);
                                $upload_stmt->bindParam(':file_path', $file_path);
                                $upload_stmt->bindParam(':file_size', $file_size);
                                $upload_stmt->bindParam(':mime_type', $mime_type);
                                $upload_stmt->execute();
                                
                                $upload_count++;
                            }
                        }
                    }
                }
            }
            
            $success_message = "Payment submission successful! Your payment is pending verification by the finance office.";
            
            // Refresh existing payments
            $payments_stmt->execute();
            $existing_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Failed to submit payment. Please try again.";
        }

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Proof of Payment Submission </h1>
    <p class="welcome-subtitle">Submit your payment proof for verification</p>
</div>

<?php if ($student_info): ?>
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
        <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Student Information</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></p>
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id']); ?></p>
            <p><strong>Grade Level:</strong> <?php echo htmlspecialchars($student_info['grade_name']); ?></p>
            <p><strong>School Year:</strong> <?php echo htmlspecialchars($student_info['year_label']); ?></p>
        </div>
    </div>
<?php endif; ?>

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

<?php if ($next_payment && $balance_info): ?>
    <!-- Payment Progress Indicator -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; color: white;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h4 style="color: white; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-clipboard-check"></i> Your Payment Progress
            </h4>
            <span style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;">
                Next: <?php echo htmlspecialchars($next_payment['type_label']); ?>
            </span>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); border-radius: 10px; padding: 1rem;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php 
                // Show down payment status
                $down_payment_verified = false;
                foreach ($balance_info['payments_breakdown'] as $payment) {
                    if ($payment['payment_type'] === 'down_payment' && $payment['verification_status'] === 'verified') {
                        $down_payment_verified = true;
                        break;
                    }
                }
                ?>
                <div style="flex: 0 0 auto; padding: 0.75rem 1rem; background: <?php echo $down_payment_verified ? '#4CAF50' : 'rgba(255,255,255,0.2)'; ?>; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas <?php echo $down_payment_verified ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                    <span style="font-size: 0.9rem;">Down Payment</span>
                </div>
                
                <?php 
                // Show installment statuses
                if ($balance_info['number_of_installments'] > 0):
                    for ($i = 1; $i <= $balance_info['number_of_installments']; $i++):
                        $installment_verified = false;
                        foreach ($balance_info['payments_breakdown'] as $payment) {
                            if ($payment['payment_type'] === 'monthly_installment' && 
                                $payment['installment_number'] == $i && 
                                $payment['verification_status'] === 'verified') {
                                $installment_verified = true;
                                break;
                            }
                        }
                ?>
                    <div style="flex: 0 0 auto; padding: 0.75rem 1rem; background: <?php echo $installment_verified ? '#4CAF50' : 'rgba(255,255,255,0.2)'; ?>; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas <?php echo $installment_verified ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                        <span style="font-size: 0.9rem;">Installment <?php echo $i; ?></span>
                    </div>
                <?php 
                    endfor;
                endif;
                ?>
            </div>
            
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Remaining Balance</div>
                    <div style="font-size: 1.4rem; font-weight: 700;">₱<?php echo number_format($balance_info['remaining_balance'], 2); ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.85rem; opacity: 0.9;">Next Payment Amount</div>
                    <div style="font-size: 1.4rem; font-weight: 700; color: #FFD700;">₱<?php echo number_format($next_payment['amount'], 2); ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
// Check if there's a pending payment
$has_pending_payment = false;
if (isset($payment_history) && !empty($payment_history)) {
    foreach ($payment_history as $p) {
        if ($p['verification_status'] === 'pending') {
            $has_pending_payment = true;
            break;
        }
    }
}

// Display message if all payments are completed
if (!$next_payment && $balance_info && $balance_info['remaining_balance'] <= 0): ?>
    <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; color: white; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Payment Fully Completed!</h3>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.95;">
            All payments for this term have been verified. Thank you for your payment!
        </p>
    </div>
<?php elseif ($has_pending_payment): ?>
    <div style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; color: white; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">
            <i class="fas fa-clock"></i>
        </div>
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Payment Verification Pending</h3>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.95;">
            You have a payment awaiting verification by the Finance Office. Please wait for approval before submitting the next payment.
        </p>
    </div>
<?php endif; ?>

<?php if (!empty($payment_terms) && ($next_payment || !$has_pending_payment)): ?>
    <!-- Two Column Layout: Payment Form + Balance Sidebar -->
    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start; margin-bottom: 2rem;">
        <!-- Left Column: Payment Form -->
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">💳 Submit Payment Proof</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Term *</label>
                    <?php if ($preferred_term_id && $next_payment): ?>
                        <!-- Locked to preferred payment term -->
                        <?php 
                        $selected_term_name = '';
                        $selected_term_type = '';
                        foreach ($payment_terms as $term) {
                            if ($term['id'] == $preferred_term_id) {
                                $selected_term_name = $term['term_name'];
                                $selected_term_type = $term['term_type'];
                                break;
                            }
                        }
                        ?>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($selected_term_name); ?> (<?php echo ucfirst(str_replace('_', ' ', $selected_term_type)); ?>)" 
                               readonly
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; background-color: #f5f5f5; cursor: not-allowed;">
                        <input type="hidden" name="payment_term_id" value="<?php echo htmlspecialchars($preferred_term_id); ?>">
                        <small style="color: var(--primary-blue); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i> Locked to your selected payment preference
                        </small>
                    <?php else: ?>
                        <select name="payment_term_id" id="payment_term_id" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                            <option value="">Select Payment Term</option>
                            <?php foreach ($payment_terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>" 
                                        data-type="<?php echo $term['term_type']; ?>"
                                        data-down-payment="<?php echo $term['down_payment_amount'] ?? 0; ?>"
                                        data-monthly-fee="<?php echo $term['monthly_fee_amount'] ?? 0; ?>"
                                        data-installments="<?php echo $term['number_of_installments'] ?? 0; ?>"
                                        data-full-amount="<?php echo $term['full_payment_amount'] ?? 0; ?>"
                                        <?php 
                                        // Auto-select preferred payment term, otherwise default term
                                        if ($preferred_term_id && $term['id'] == $preferred_term_id) {
                                            echo 'selected';
                                        } elseif (!$preferred_term_id && $term['is_default']) {
                                            echo 'selected';
                                        }
                                        ?>>
                                    <?php echo htmlspecialchars($term['term_name']); ?> 
                                    (<?php echo ucfirst(str_replace('_', ' ', $term['term_type'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Type *</label>
                    <?php if ($next_payment): ?>
                        <!-- Auto-filled and locked based on payment history -->
                        <input type="text" 
                               value="<?php echo htmlspecialchars($next_payment['type_label']); ?>" 
                               readonly
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; background-color: #f5f5f5; cursor: not-allowed;">
                        <input type="hidden" name="payment_type" value="<?php echo htmlspecialchars($next_payment['type']); ?>">
                        <small style="color: var(--primary-blue); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i> Auto-determined based on your payment progress
                        </small>
                    <?php else: ?>
                        <select name="payment_type" id="payment_type" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                            <option value="">Select Payment Type</option>
                            <option value="full_payment">Full Payment</option>
                            <option value="down_payment">Down Payment</option>
                            <option value="monthly_installment">Monthly Installment</option>
                        </select>
                        <small id="installment_info" style="color: var(--gray); font-size: 0.9rem; display: none;"></small>
                        <?php if (isset($payment_history) && !empty($payment_history)): ?>
                            <?php 
                            $has_pending = false;
                            foreach ($payment_history as $p) {
                                if ($p['verification_status'] === 'pending') {
                                    $has_pending = true;
                                    break;
                                }
                            }
                            if ($has_pending): ?>
                                <small style="color: orange; font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                                    <i class="fas fa-exclamation-triangle"></i> You have a pending payment awaiting verification
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Amount Paid *</label>
                    <?php if ($next_payment): ?>
                        <!-- Auto-filled and locked based on payment schedule -->
                        <input type="text" 
                               value="₱<?php echo number_format($next_payment['amount'], 2); ?>" 
                               readonly
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; background-color: #f5f5f5; cursor: not-allowed; font-weight: 600; color: var(--dark-blue);">
                        <input type="hidden" name="amount_paid" value="<?php echo htmlspecialchars($next_payment['amount']); ?>">
                        <small style="color: var(--primary-blue); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i> Amount set by payment schedule
                        </small>
                    <?php else: ?>
                        <input type="number" name="amount_paid" id="amount_paid" step="0.01" min="0" required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                        <small id="amount_hint" style="color: var(--gray); font-size: 0.9rem;"></small>
                    <?php endif; ?>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Date *</label>
                    <input type="date" name="payment_date" required 
                           value="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                    <small style="color: var(--gray); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                        Date when payment was made
                    </small>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Method *</label>
                    <select name="payment_method" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                        <option value="">Select Method</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Reference Number</label>
                    <input type="text" name="reference_number" placeholder="Transaction reference number"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Proof (Image/PDF) *</label>
                <input type="file" name="proof_image" accept="image/*,.pdf" required
                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                <small style="color: var(--gray); font-size: 0.9rem;">Upload receipt, bank transfer confirmation, or payment screenshot (JPG, PNG, PDF only, max 5MB)</small>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Additional Documents (Optional)</label>
                <input type="file" name="additional_files[]" multiple accept="image/*,.pdf"
                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                <small style="color: var(--gray); font-size: 0.9rem;">Upload additional supporting documents if needed (max 3 files, 5MB each)</small>
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Additional Notes</label>
                <textarea name="proof_notes" rows="3" placeholder="Additional information about this payment..."
                          style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; resize: vertical;"></textarea>
            </div>

            <button type="submit" name="submit_payment" 
                    style="background: var(--primary-blue); color: white; padding: 1rem 2rem; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                Submit Payment
            </button>
        </form>
    </div>
    
    <!-- Right Column: Balance Sidebar (Sticky) -->
    <?php if ($balance_info): ?>
    <div style="position: sticky; top: 2rem;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 1.5rem; color: white; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
            <h4 style="color: white; margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
                <i class="fas fa-receipt"></i> Payment Balance
            </h4>
            <p style="font-size: 0.85rem; opacity: 0.9; margin: 0 0 1rem 0;">
                <?php echo htmlspecialchars($balance_info['term_name']); ?>
            </p>
            
            <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 1.25rem; border: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 1rem;">
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.3rem;">Total Amount</div>
                    <div style="font-size: 1.3rem; font-weight: 700;">
                        ₱<?php echo number_format($balance_info['total_amount'], 2); ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
                    <div style="font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.3rem;">Paid (Verified)</div>
                    <div style="font-size: 1.3rem; font-weight: 700; color: #4CAF50;">
                        ₱<?php echo number_format($balance_info['total_paid'], 2); ?>
                    </div>
                </div>
                
                <div style="background: rgba(255, 255, 255, 0.25); border-radius: 8px; padding: 1rem; border: 2px solid rgba(255, 255, 255, 0.4);">
                    <div style="font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.3rem;">Remaining Balance</div>
                    <div style="font-size: 1.6rem; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        ₱<?php echo number_format($balance_info['remaining_balance'], 2); ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Schedule -->
            <div style="background: rgba(255, 255, 255, 0.95); border-radius: 10px; padding: 1rem; color: var(--black); max-height: 300px; overflow-y: auto;">
                <h5 style="color: var(--dark-blue); margin: 0 0 0.75rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.3rem;">
                    <i class="fas fa-list-check"></i> Payment Breakdown
                </h5>
                
                <?php if (!empty($balance_info['payments_breakdown'])): ?>
                    <div style="font-size: 0.85rem;">
                        <?php foreach ($balance_info['payments_breakdown'] as $payment_item): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-gray);">
                                <div>
                                    <div style="font-weight: 600; color: var(--dark-blue);">
                                        <?php 
                                        $type_display = ucfirst(str_replace('_', ' ', $payment_item['payment_type']));
                                        if ($payment_item['installment_number']) {
                                            $type_display .= " #{$payment_item['installment_number']}";
                                        }
                                        echo $type_display;
                                        ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--gray);">
                                        <?php echo date('M d, Y', strtotime($payment_item['submitted_at'])); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600;">₱<?php echo number_format($payment_item['amount_paid'], 2); ?></div>
                                    <?php
                                    $badge_colors = [
                                        'pending' => 'background: #FFA726;',
                                        'verified' => 'background: #4CAF50;',
                                        'rejected' => 'background: #f44336;'
                                    ];
                                    ?>
                                    <span style="<?php echo $badge_colors[$payment_item['verification_status']]; ?> color: white; padding: 0.1rem 0.4rem; border-radius: 8px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; display: inline-block; margin-top: 0.2rem;">
                                        <?php echo $payment_item['verification_status']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--gray); text-align: center; padding: 1rem 0;">
                        No payments submitted yet
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.15); border-radius: 8px; font-size: 0.75rem; line-height: 1.4;">
                <i class="fas fa-info-circle"></i> Only <strong>verified</strong> payments reduce your balance.
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<!-- End Two Column Layout -->
<?php else: ?>
    <div class="alert alert-warning">
        No payment terms are currently available for your grade level. Please contact the finance office for assistance.
    </div>
<?php endif; ?>

<?php if (!empty($existing_payments)): ?>
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">📋 Payment History</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left;">Date Submitted</th>
                        <th style="padding: 1rem; text-align: left;">Payment Term</th>
                        <th style="padding: 1rem; text-align: left;">Type</th>
                        <th style="padding: 1rem; text-align: center;">Amount</th>
                        <th style="padding: 1rem; text-align: center;">Status</th>
                        <th style="padding: 1rem; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing_payments as $payment): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem;">
                                <?php echo date('M d, Y', strtotime($payment['submitted_at'])); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <?php echo htmlspecialchars($payment['term_name']); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <?php 
                                $type_display = ucfirst(str_replace('_', ' ', $payment['payment_type']));
                                if ($payment['installment_number']) {
                                    $type_display .= " #{$payment['installment_number']}";
                                }
                                echo $type_display;
                                ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; font-weight: 600;">
                                ₱<?php echo number_format($payment['amount_paid'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php
                                $status_color = [
                                    'pending' => 'var(--warning)',
                                    'verified' => 'var(--success)',
                                    'rejected' => 'var(--danger)'
                                ][$payment['verification_status']];
                                ?>
                                <span style="color: <?php echo $status_color; ?>; font-weight: 600; text-transform: uppercase; font-size: 0.9rem;">
                                    <?php echo $payment['verification_status']; ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../shared/payment_details.php?id=<?php echo $payment['id']; ?>" 
                                   style="background: var(--primary-blue); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9rem; display: inline-block;">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentTermSelect = document.getElementById('payment_term_id');
    const paymentTypeSelect = document.getElementById('payment_type');
    const installmentInfo = document.getElementById('installment_info');
    const amountPaidInput = document.getElementById('amount_paid');
    const amountHint = document.getElementById('amount_hint');

    // Only run interactive code if elements exist (not locked/readonly)
    if (!paymentTermSelect || !paymentTypeSelect || !amountPaidInput) {
        return; // Fields are locked, no need for interactive updates
    }

    function updatePaymentOptions() {
        const selectedTerm = paymentTermSelect.options[paymentTermSelect.selectedIndex];
        const termType = selectedTerm.dataset.type;
        
        // Clear payment type options
        paymentTypeSelect.innerHTML = '<option value="">Select Payment Type</option>';
        
        if (termType === 'full_payment') {
            paymentTypeSelect.innerHTML += '<option value="full_payment">Full Payment</option>';
            // Auto-select full payment if it's the only option
            if (paymentTypeSelect.options.length === 2) {
                paymentTypeSelect.value = 'full_payment';
                updateAmountHint();
            }
        } else if (termType === 'installment') {
            paymentTypeSelect.innerHTML += '<option value="down_payment">Down Payment</option>';
            paymentTypeSelect.innerHTML += '<option value="monthly_installment">Monthly Installment</option>';
        }
    }

    async function updateInstallmentInfo() {
        const paymentTermId = paymentTermSelect.value;
        
        if (!paymentTermId || !installmentInfo) {
            if (installmentInfo) installmentInfo.style.display = 'none';
            return;
        }
        
        try {
            // Fetch next installment number from the server
            const response = await fetch(`get_next_installment.php?payment_term_id=${paymentTermId}`);
            const data = await response.json();
            
            if (data.next_installment) {
                installmentInfo.textContent = `This will be recorded as Installment #${data.next_installment}`;
                installmentInfo.style.display = 'block';
                installmentInfo.style.color = 'var(--primary-blue)';
                installmentInfo.style.fontWeight = '600';
            }
        } catch (error) {
            console.error('Error fetching installment info:', error);
        }
    }

    function updateAmountHint() {
        const selectedTerm = paymentTermSelect.options[paymentTermSelect.selectedIndex];
        const paymentType = paymentTypeSelect.value;
        
        let hint = '';
        let suggestedAmount = 0;
        
        if (paymentType === 'full_payment') {
            suggestedAmount = parseFloat(selectedTerm.dataset.fullAmount) || 0;
            hint = `Suggested amount: ₱${suggestedAmount.toLocaleString()}`;
        } else if (paymentType === 'down_payment') {
            suggestedAmount = parseFloat(selectedTerm.dataset.downPayment) || 0;
            hint = `Suggested amount: ₱${suggestedAmount.toLocaleString()}`;
        } else if (paymentType === 'monthly_installment') {
            suggestedAmount = parseFloat(selectedTerm.dataset.monthlyFee) || 0;
            hint = `Suggested amount: ₱${suggestedAmount.toLocaleString()}`;
            updateInstallmentInfo();
        }
        
        if (amountHint) amountHint.textContent = hint;
        if (suggestedAmount > 0 && amountPaidInput) {
            amountPaidInput.value = suggestedAmount.toFixed(2);
        }
    }

    paymentTermSelect.addEventListener('change', function() {
        updatePaymentOptions();
        if (installmentInfo) installmentInfo.style.display = 'none';
        if (amountPaidInput) amountPaidInput.value = '';
    });

    paymentTypeSelect.addEventListener('change', function() {
        if (this.value === 'monthly_installment') {
            updateInstallmentInfo();
        } else {
            if (installmentInfo) installmentInfo.style.display = 'none';
        }
        updateAmountHint();
    });

    // Initialize on page load - if there's a pre-selected payment term, trigger the update
    if (paymentTermSelect.value) {
        updatePaymentOptions();
    }
});
</script>

<style>
    @media (max-width: 1200px) {
        /* Stack the two-column layout on smaller screens */
        .two-column-layout {
            grid-template-columns: 1fr !important;
        }
        
        /* Remove sticky positioning on mobile */
        .balance-sidebar {
            position: static !important;
        }
        
        /* Adjust sidebar for mobile */
        .balance-sidebar {
            margin-top: 2rem;
        }
    }
    
    @media (max-width: 768px) {
        /* Further adjustments for mobile phones */
        .balance-sidebar {
            padding: 1.5rem;
        }
        
        .balance-item {
            font-size: 0.95rem;
        }
        
        .balance-remaining h3 {
            font-size: 1.8rem;
        }
    }
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
