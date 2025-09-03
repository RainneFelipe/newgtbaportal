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
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    try {
        $payment_term_id = $_POST['payment_term_id'];
        $payment_type = $_POST['payment_type'];
        $installment_number = $_POST['installment_number'] ?? null;
        
        // Ensure installment_number is properly NULL for non-installment payments
        if ($payment_type !== 'monthly_installment' || empty($installment_number)) {
            $installment_number = null;
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

<?php if (!empty($payment_terms)): ?>
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
        <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">💳 Submit Payment Proof</h3>
        
        <form method="POST" enctype="multipart/form-data" style="max-width: 800px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Term *</label>
                    <select name="payment_term_id" id="payment_term_id" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                        <option value="">Select Payment Term</option>
                        <?php foreach ($payment_terms as $term): ?>
                            <option value="<?php echo $term['id']; ?>" 
                                    data-type="<?php echo $term['term_type']; ?>"
                                    data-down-payment="<?php echo $term['down_payment_amount'] ?? 0; ?>"
                                    data-monthly-fee="<?php echo $term['monthly_fee_amount'] ?? 0; ?>"
                                    data-installments="<?php echo $term['number_of_installments'] ?? 0; ?>"
                                    data-full-amount="<?php echo $term['full_payment_amount'] ?? 0; ?>"
                                    <?php echo $term['is_default'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($term['term_name']); ?> 
                                (<?php echo ucfirst(str_replace('_', ' ', $term['term_type'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Type *</label>
                    <select name="payment_type" id="payment_type" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                        <option value="">Select Payment Type</option>
                        <option value="full_payment">Full Payment</option>
                        <option value="down_payment">Down Payment</option>
                        <option value="monthly_installment">Monthly Installment</option>
                    </select>
                </div>

                <div id="installment_number_div" style="display: none;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Installment Number</label>
                    <select name="installment_number" id="installment_number" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                        <option value="">Select Installment</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Amount Paid *</label>
                    <input type="number" name="amount_paid" id="amount_paid" step="0.01" min="0" required 
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                    <small id="amount_hint" style="color: var(--gray); font-size: 0.9rem;"></small>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Date *</label>
                    <input type="date" name="payment_date" required max="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
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
    const installmentNumberDiv = document.getElementById('installment_number_div');
    const installmentNumberSelect = document.getElementById('installment_number');
    const amountPaidInput = document.getElementById('amount_paid');
    const amountHint = document.getElementById('amount_hint');

    function updatePaymentOptions() {
        const selectedTerm = paymentTermSelect.options[paymentTermSelect.selectedIndex];
        const termType = selectedTerm.dataset.type;
        
        // Clear payment type options
        paymentTypeSelect.innerHTML = '<option value="">Select Payment Type</option>';
        
        if (termType === 'full_payment') {
            paymentTypeSelect.innerHTML += '<option value="full_payment">Full Payment</option>';
        } else if (termType === 'installment') {
            paymentTypeSelect.innerHTML += '<option value="down_payment">Down Payment</option>';
            paymentTypeSelect.innerHTML += '<option value="monthly_installment">Monthly Installment</option>';
        }
        
        updateAmountHint();
    }

    function updateInstallmentOptions() {
        const selectedTerm = paymentTermSelect.options[paymentTermSelect.selectedIndex];
        const numInstallments = parseInt(selectedTerm.dataset.installments) || 0;
        
        installmentNumberSelect.innerHTML = '<option value="">Select Installment</option>';
        
        for (let i = 1; i <= numInstallments; i++) {
            installmentNumberSelect.innerHTML += `<option value="${i}">Installment ${i}</option>`;
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
        }
        
        amountHint.textContent = hint;
        if (suggestedAmount > 0) {
            amountPaidInput.value = suggestedAmount.toFixed(2);
        }
    }

    paymentTermSelect.addEventListener('change', function() {
        updatePaymentOptions();
        installmentNumberDiv.style.display = 'none';
    });

    paymentTypeSelect.addEventListener('change', function() {
        if (this.value === 'monthly_installment') {
            installmentNumberDiv.style.display = 'block';
            updateInstallmentOptions();
        } else {
            installmentNumberDiv.style.display = 'none';
        }
        updateAmountHint();
    });

    // Initialize on page load
    if (paymentTermSelect.value) {
        updatePaymentOptions();
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
