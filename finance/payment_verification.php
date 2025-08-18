<?php
require_once '../includes/auth_check.php';

// Check if user has finance role
if (!checkRole('finance')) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Payment Verification - GTBA Portal';
$base_url = '../';

$database = new Database();
$db = $database->connect();

$success_message = '';
$error_message = '';

// Handle payment verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_payment'])) {
        try {
            $payment_id = $_POST['payment_id'];
            $action = $_POST['action'];
            $verification_notes = $_POST['verification_notes'] ?? '';

            $update_query = "UPDATE student_payments 
                            SET verification_status = :status, 
                                verified_by = :verified_by,
                                verification_date = NOW(),
                                verification_notes = :verification_notes
                            WHERE id = :payment_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $action);
            $update_stmt->bindParam(':verified_by', $_SESSION['user_id']);
            $update_stmt->bindParam(':verification_notes', $verification_notes);
            $update_stmt->bindParam(':payment_id', $payment_id);
            
            if ($update_stmt->execute()) {
                $action_text = $action === 'verified' ? 'approved' : 'rejected';
                $success_message = "Payment successfully {$action_text}!";
            } else {
                $error_message = "Failed to update payment status.";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_student = $_GET['search_student'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "sp.verification_status = :status";
    $params[':status'] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(sp.submitted_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(sp.submitted_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($search_student) {
    $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search)";
    $params[':search'] = '%' . $search_student . '%';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get payment submissions
$payments_query = "SELECT sp.*, 
                          s.first_name as student_first_name, s.last_name as student_last_name, s.student_id,
                          gl.grade_name,
                          pt.term_name, pt.term_type,
                          u.username as verified_by_name
                   FROM student_payments sp
                   JOIN students s ON sp.student_id = s.id
                   LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                   JOIN payment_terms pt ON sp.payment_term_id = pt.id
                   LEFT JOIN users u ON sp.verified_by = u.id
                   {$where_clause}
                   ORDER BY sp.submitted_at DESC";

$payments_stmt = $db->prepare($payments_query);
foreach ($params as $key => $value) {
    $payments_stmt->bindValue($key, $value);
}
$payments_stmt->execute();
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
                    COUNT(*) as total_submissions,
                    COUNT(CASE WHEN verification_status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN verification_status = 'verified' THEN 1 END) as verified_count,
                    COUNT(CASE WHEN verification_status = 'rejected' THEN 1 END) as rejected_count,
                    SUM(CASE WHEN verification_status = 'verified' THEN amount_paid ELSE 0 END) as total_verified_amount
                FROM student_payments sp";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Verification</h1>
    <p class="welcome-subtitle">Review and verify student payment submissions</p>
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

<!-- Statistics Dashboard -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--dark-blue); font-size: 2rem; margin: 0 0 0.5rem 0;"><?php echo $stats['total_submissions']; ?></h3>
        <p style="color: var(--gray); margin: 0;">Total Submissions</p>
    </div>
    
    <div style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--warning); font-size: 2rem; margin: 0 0 0.5rem 0;"><?php echo $stats['pending_count']; ?></h3>
        <p style="color: var(--gray); margin: 0;">Pending Review</p>
    </div>
    
    <div style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--success); font-size: 2rem; margin: 0 0 0.5rem 0;"><?php echo $stats['verified_count']; ?></h3>
        <p style="color: var(--gray); margin: 0;">Verified</p>
    </div>
    
    <div style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--danger); font-size: 2rem; margin: 0 0 0.5rem 0;"><?php echo $stats['rejected_count']; ?></h3>
        <p style="color: var(--gray); margin: 0;">Rejected</p>
    </div>
    
    <div style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--primary-blue); font-size: 1.5rem; margin: 0 0 0.5rem 0;">₱<?php echo number_format($stats['total_verified_amount'], 2); ?></h3>
        <p style="color: var(--gray); margin: 0;">Verified Amount</p>
    </div>
</div>

<!-- Filters -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">🔍 Filters</h3>
    
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Status</label>
            <select name="status" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Date From</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Date To</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Search Student</label>
            <input type="text" name="search_student" placeholder="Name or Student ID" value="<?php echo htmlspecialchars($search_student); ?>"
                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px;">
        </div>
        
        <div>
            <button type="submit" style="background: var(--primary-blue); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; width: 100%;">
                Filter
            </button>
        </div>
        
        <div>
            <a href="payment_verification.php" style="display: inline-block; background: var(--gray); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; text-align: center; width: 100%; box-sizing: border-box;">
                Clear
            </a>
        </div>
    </form>
</div>

<!-- Payment Submissions -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">💰 Payment Submissions</h3>
    
    <?php if (!empty($payments)): ?>
        <div style="overflow-x: auto;" class="payment-table-container">
            <table style="width: 100%; border-collapse: collapse;" class="payment-table">
                <thead>
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left;">Student</th>
                        <th style="padding: 1rem; text-align: left;">Payment Details</th>
                        <th style="padding: 1rem; text-align: center;">Amount</th>
                        <th style="padding: 1rem; text-align: center;">Date Submitted</th>
                        <th style="padding: 1rem; text-align: center;">Status</th>
                        <th style="padding: 1rem; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);" 
                            <?php echo $payment['verification_status'] === 'pending' ? 'style="background: rgba(255, 193, 7, 0.1);"' : ''; ?>>
                            <td style="padding: 1rem;">
                                <div>
                                    <strong><?php echo htmlspecialchars($payment['student_first_name'] . ' ' . $payment['student_last_name']); ?></strong><br>
                                    <small style="color: var(--gray);">ID: <?php echo htmlspecialchars($payment['student_id']); ?></small><br>
                                    <small style="color: var(--gray);">Grade: <?php echo htmlspecialchars($payment['grade_name']); ?></small>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div>
                                    <strong><?php echo htmlspecialchars($payment['term_name']); ?></strong><br>
                                    <small>
                                        <?php 
                                        $type_display = ucfirst(str_replace('_', ' ', $payment['payment_type']));
                                        if ($payment['installment_number']) {
                                            $type_display .= " #{$payment['installment_number']}";
                                        }
                                        echo $type_display;
                                        ?>
                                    </small><br>
                                    <small style="color: var(--gray);">Method: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></small>
                                    <?php if ($payment['reference_number']): ?>
                                        <br><small style="color: var(--gray);">Ref: <?php echo htmlspecialchars($payment['reference_number']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: center; font-weight: 600; font-size: 1.1rem;">
                                ₱<?php echo number_format($payment['amount_paid'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php echo date('M d, Y', strtotime($payment['submitted_at'])); ?><br>
                                <small style="color: var(--gray);"><?php echo date('h:i A', strtotime($payment['submitted_at'])); ?></small>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php
                                $status_colors = [
                                    'pending' => 'var(--warning)',
                                    'verified' => 'var(--success)',
                                    'rejected' => 'var(--danger)'
                                ];
                                $status_color = $status_colors[$payment['verification_status']];
                                ?>
                                <span style="color: <?php echo $status_color; ?>; font-weight: 600; text-transform: uppercase; font-size: 0.9rem;">
                                    <?php echo $payment['verification_status']; ?>
                                </span>
                                <?php if ($payment['verified_by']): ?>
                                    <br><small style="color: var(--gray);">
                                        by <?php echo htmlspecialchars($payment['verified_by_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;" class="payment-actions">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
                                    <a href="../shared/payment_details.php?id=<?php echo $payment['id']; ?>" 
                                       class="view-details-btn"
                                       style="background: var(--primary-blue); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9rem; display: inline-block; width: 110px; text-align: center;">
                                        Details
                                    </a>
                                    <?php if ($payment['verification_status'] === 'pending'): ?>
                                        <button onclick="showVerificationModal(<?php echo $payment['id']; ?>, 'verified')" 
                                                style="background: var(--success); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; width: 110px;">
                                            Approve
                                        </button>
                                        <button onclick="showVerificationModal(<?php echo $payment['id']; ?>, 'rejected')" 
                                                style="background: var(--danger); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; width: 110px;">
                                            Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            No payment submissions found matching your criteria.
        </div>
    <?php endif; ?>
</div>

<!-- Verification Modal -->
<div id="verificationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px;">
        <h3 id="modalTitle" style="color: var(--dark-blue); margin-bottom: 1rem;"></h3>
        
        <form method="POST">
            <input type="hidden" name="payment_id" id="modalPaymentId">
            <input type="hidden" name="action" id="modalAction">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Verification Notes</label>
                <textarea name="verification_notes" id="verificationNotes" rows="4" placeholder="Add notes about this verification decision..."
                          style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" 
                        style="background: var(--gray); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" name="verify_payment" id="modalSubmitBtn"
                        style="padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; color: white;">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 800px; max-height: 90%; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--dark-blue); margin: 0;">Payment Details</h3>
            <button onclick="closeDetailsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray);">✕</button>
        </div>
        
        <div id="paymentDetailsContent">
            <p style="text-align: center; color: var(--gray); padding: 2rem;">
                Payment details are now available in the dedicated detail view.<br>
                Click "View Details" to see the complete payment information.
            </p>
        </div>
    </div>
</div>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>

<script>
function showVerificationModal(paymentId, action) {
    const modal = document.getElementById('verificationModal');
    const title = document.getElementById('modalTitle');
    const paymentIdInput = document.getElementById('modalPaymentId');
    const actionInput = document.getElementById('modalAction');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    paymentIdInput.value = paymentId;
    actionInput.value = action;
    
    if (action === 'verified') {
        title.textContent = 'Approve Payment';
        submitBtn.style.background = 'var(--success)';
        submitBtn.textContent = 'Approve';
    } else {
        title.textContent = 'Reject Payment';
        submitBtn.style.background = 'var(--danger)';
        submitBtn.textContent = 'Reject';
    }
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('verificationModal').style.display = 'none';
    document.getElementById('verificationNotes').value = '';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const verificationModal = document.getElementById('verificationModal');
    const detailsModal = document.getElementById('detailsModal');
    
    if (event.target === verificationModal) {
        closeModal();
    }
    if (event.target === detailsModal) {
        closeDetailsModal();
    }
});
</script>

<style>
/* Action buttons responsive layout */
@media (max-width: 1200px) {
    .payment-actions {
        min-width: 130px;
    }
    
    .payment-actions a,
    .payment-actions button {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.8rem !important;
        width: 100px !important;
    }
}

@media (max-width: 992px) {
    .payment-actions {
        min-width: 110px;
    }
    
    .payment-actions a,
    .payment-actions button {
        font-size: 0.8rem !important;
        padding: 0.4rem 0.6rem !important;
        width: 90px !important;
    }
}

@media (max-width: 768px) {
    .payment-actions {
        min-width: 100px;
    }
    
    .payment-actions a,
    .payment-actions button {
        width: 85px !important;
        font-size: 0.75rem !important;
        padding: 0.4rem 0.5rem !important;
    }
}

@media (max-width: 576px) {
    .payment-actions {
        min-width: 90px;
    }
    
    .payment-actions a,
    .payment-actions button {
        width: 80px !important;
        font-size: 0.7rem !important;
        padding: 0.3rem 0.4rem !important;
    }
}

/* Hover effects for buttons */
.payment-actions a:hover,
.payment-actions button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
}

.payment-actions button:active {
    transform: translateY(0);
}

/* Better button styling */
.payment-actions a,
.payment-actions button {
    font-weight: 600;
    letter-spacing: 0.3px;
    transition: all 0.2s ease;
    border: none;
    outline: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.payment-actions a:focus,
.payment-actions button:focus {
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.3);
}

/* Ensure buttons maintain consistent height */
.payment-actions > div {
    align-items: stretch;
}

.payment-actions a,
.payment-actions button {
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Table responsive improvements */
@media (max-width: 768px) {
    .payment-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .payment-table {
        min-width: 900px;
    }
    
    /* Adjust table column widths for better action button display */
    .payment-table th:last-child,
    .payment-table td:last-child {
        min-width: 120px;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
