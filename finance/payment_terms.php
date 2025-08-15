<?php
require_once '../includes/auth_check.php';

// Check if user is a finance officer
if (!checkRole('finance')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Payment Terms Management - GTBA Portal';
$base_url = '../';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->connect();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_term':
                    $term_name = $_POST['term_name'];
                    $term_type = $_POST['term_type'];
                    $school_year_id = $_POST['school_year_id'];
                    $grade_level_id = $_POST['grade_level_id'] ?: null;
                    $description = $_POST['description'] ?: null;
                    $is_default = isset($_POST['is_default']) ? 1 : 0;
                    
                    // Handle default term constraint - remove default from other terms
                    if ($is_default) {
                        $update_query = "UPDATE payment_terms 
                                       SET is_default = 0 
                                       WHERE school_year_id = :school_year_id 
                                       AND (grade_level_id = :grade_level_id OR (grade_level_id IS NULL AND :grade_level_id IS NULL))";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':school_year_id', $school_year_id);
                        $update_stmt->bindParam(':grade_level_id', $grade_level_id);
                        $update_stmt->execute();
                    }
                    
                    if ($term_type === 'full_payment') {
                        $full_payment_due_date = $_POST['full_payment_due_date'] ?: null;
                        $full_payment_discount_percentage = $_POST['full_payment_discount_percentage'] ?: 0;
                        
                        $query = "INSERT INTO payment_terms (term_name, term_type, school_year_id, grade_level_id, 
                                 full_payment_due_date, full_payment_discount_percentage, description, is_default, created_by)
                                 VALUES (:term_name, :term_type, :school_year_id, :grade_level_id, 
                                 :full_payment_due_date, :full_payment_discount_percentage, :description, :is_default, :created_by)";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':full_payment_due_date', $full_payment_due_date);
                        $stmt->bindParam(':full_payment_discount_percentage', $full_payment_discount_percentage);
                    } else {
                        $down_payment_amount = $_POST['down_payment_amount'] ?: null;
                        $down_payment_due_date = $_POST['down_payment_due_date'] ?: null;
                        $monthly_fee_amount = $_POST['monthly_fee_amount'] ?: null;
                        $installment_start_month = $_POST['installment_start_month'] ?: null;
                        $installment_start_year = $_POST['installment_start_year'] ?: null;
                        $installment_end_month = $_POST['installment_end_month'] ?: null;
                        $installment_end_year = $_POST['installment_end_year'] ?: null;
                        $number_of_installments = $_POST['number_of_installments'] ?: null;
                        
                        $query = "INSERT INTO payment_terms (term_name, term_type, school_year_id, grade_level_id,
                                 down_payment_amount, down_payment_due_date, monthly_fee_amount,
                                 installment_start_month, installment_start_year, installment_end_month, installment_end_year,
                                 number_of_installments, description, is_default, created_by)
                                 VALUES (:term_name, :term_type, :school_year_id, :grade_level_id,
                                 :down_payment_amount, :down_payment_due_date, :monthly_fee_amount,
                                 :installment_start_month, :installment_start_year, :installment_end_month, :installment_end_year,
                                 :number_of_installments, :description, :is_default, :created_by)";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':down_payment_amount', $down_payment_amount);
                        $stmt->bindParam(':down_payment_due_date', $down_payment_due_date);
                        $stmt->bindParam(':monthly_fee_amount', $monthly_fee_amount);
                        $stmt->bindParam(':installment_start_month', $installment_start_month);
                        $stmt->bindParam(':installment_start_year', $installment_start_year);
                        $stmt->bindParam(':installment_end_month', $installment_end_month);
                        $stmt->bindParam(':installment_end_year', $installment_end_year);
                        $stmt->bindParam(':number_of_installments', $number_of_installments);
                    }
                    
                    $stmt->bindParam(':term_name', $term_name);
                    $stmt->bindParam(':term_type', $term_type);
                    $stmt->bindParam(':school_year_id', $school_year_id);
                    $stmt->bindParam(':grade_level_id', $grade_level_id);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':is_default', $is_default);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment term added successfully!";
                    } else {
                        $error_message = "Failed to add payment term.";
                    }
                    break;
                    
                case 'update_term':
                    $id = $_POST['term_id'];
                    $term_name = $_POST['term_name'];
                    $term_type = $_POST['term_type'];
                    $school_year_id = $_POST['school_year_id'];
                    $grade_level_id = $_POST['grade_level_id'] ?: null;
                    $description = $_POST['description'] ?: null;
                    $is_default = isset($_POST['is_default']) ? 1 : 0;
                    
                    // Start transaction for constraint safety
                    $db->beginTransaction();
                    
                    try {
                        // Handle default term constraint
                        if ($is_default) {
                            $update_query = "UPDATE payment_terms 
                                           SET is_default = 0 
                                           WHERE school_year_id = :school_year_id 
                                           AND (grade_level_id = :grade_level_id OR (grade_level_id IS NULL AND :grade_level_id IS NULL))
                                           AND id != :id";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':school_year_id', $school_year_id);
                            $update_stmt->bindParam(':grade_level_id', $grade_level_id);
                            $update_stmt->bindParam(':id', $id);
                            $update_stmt->execute();
                        }
                        
                        if ($term_type === 'full_payment') {
                            $full_payment_due_date = $_POST['full_payment_due_date'] ?: null;
                            $full_payment_discount_percentage = $_POST['full_payment_discount_percentage'] ?: 0;
                            
                            $query = "UPDATE payment_terms 
                                     SET term_name = :term_name, term_type = :term_type, school_year_id = :school_year_id, 
                                         grade_level_id = :grade_level_id, full_payment_due_date = :full_payment_due_date,
                                         full_payment_discount_percentage = :full_payment_discount_percentage,
                                         down_payment_amount = NULL, down_payment_due_date = NULL, monthly_fee_amount = NULL,
                                         installment_start_month = NULL, installment_start_year = NULL, 
                                         installment_end_month = NULL, installment_end_year = NULL, number_of_installments = NULL,
                                         description = :description, is_default = :is_default, updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id";
                            
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':full_payment_due_date', $full_payment_due_date);
                            $stmt->bindParam(':full_payment_discount_percentage', $full_payment_discount_percentage);
                        } else {
                            $down_payment_amount = $_POST['down_payment_amount'] ?: null;
                            $down_payment_due_date = $_POST['down_payment_due_date'] ?: null;
                            $monthly_fee_amount = $_POST['monthly_fee_amount'] ?: null;
                            $installment_start_month = $_POST['installment_start_month'] ?: null;
                            $installment_start_year = $_POST['installment_start_year'] ?: null;
                            $installment_end_month = $_POST['installment_end_month'] ?: null;
                            $installment_end_year = $_POST['installment_end_year'] ?: null;
                            $number_of_installments = $_POST['number_of_installments'] ?: null;
                            
                            $query = "UPDATE payment_terms 
                                     SET term_name = :term_name, term_type = :term_type, school_year_id = :school_year_id, 
                                         grade_level_id = :grade_level_id, down_payment_amount = :down_payment_amount,
                                         down_payment_due_date = :down_payment_due_date, monthly_fee_amount = :monthly_fee_amount,
                                         installment_start_month = :installment_start_month, installment_start_year = :installment_start_year,
                                         installment_end_month = :installment_end_month, installment_end_year = :installment_end_year,
                                         number_of_installments = :number_of_installments, 
                                         full_payment_due_date = NULL, full_payment_discount_percentage = 0,
                                         description = :description, is_default = :is_default, updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id";
                            
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':down_payment_amount', $down_payment_amount);
                            $stmt->bindParam(':down_payment_due_date', $down_payment_due_date);
                            $stmt->bindParam(':monthly_fee_amount', $monthly_fee_amount);
                            $stmt->bindParam(':installment_start_month', $installment_start_month);
                            $stmt->bindParam(':installment_start_year', $installment_start_year);
                            $stmt->bindParam(':installment_end_month', $installment_end_month);
                            $stmt->bindParam(':installment_end_year', $installment_end_year);
                            $stmt->bindParam(':number_of_installments', $number_of_installments);
                        }
                        
                        $stmt->bindParam(':term_name', $term_name);
                        $stmt->bindParam(':term_type', $term_type);
                        $stmt->bindParam(':school_year_id', $school_year_id);
                        $stmt->bindParam(':grade_level_id', $grade_level_id);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':is_default', $is_default);
                        $stmt->bindParam(':id', $id);
                        
                        if ($stmt->execute()) {
                            $db->commit();
                            $success_message = "Payment term updated successfully!";
                        } else {
                            $db->rollback();
                            $error_message = "Failed to update payment term.";
                        }
                        
                    } catch (PDOException $e) {
                        $db->rollback();
                        if (strpos($e->getMessage(), 'unique_default_per_grade_year') !== false) {
                            $error_message = "Cannot update: This would create duplicate default payment terms for the same grade level and school year. Only one payment term can be set as default per grade level per school year.";
                        } else {
                            $error_message = "Database error: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'toggle_status':
                    $id = $_POST['term_id'];
                    $is_active = $_POST['is_active'] ? 1 : 0;
                    
                    $query = "UPDATE payment_terms 
                              SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP
                              WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':is_active', $is_active);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment term status updated successfully!";
                    } else {
                        $error_message = "Failed to update payment term status.";
                    }
                    break;
                    
                case 'delete_term':
                    $id = $_POST['term_id'];
                    
                    $query = "DELETE FROM payment_terms WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment term deleted successfully!";
                    } else {
                        $error_message = "Failed to delete payment term.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
        error_log("Payment terms error: " . $e->getMessage());
    }
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get all payment terms with related data
    $query = "SELECT pt.*, 
                     sy.year_label as school_year_name,
                     gl.grade_name,
                     u.username as created_by_username
              FROM payment_terms pt
              JOIN school_years sy ON pt.school_year_id = sy.id
              LEFT JOIN grade_levels gl ON pt.grade_level_id = gl.id
              JOIN users u ON pt.created_by = u.id
              ORDER BY pt.school_year_id DESC, pt.is_default DESC, pt.term_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $payment_terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active school years
    $query = "SELECT * FROM school_years WHERE is_active = 1 ORDER BY year_label DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active grade levels
    $query = "SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $query = "SELECT 
                COUNT(*) as total_terms,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_terms,
                SUM(CASE WHEN term_type = 'full_payment' THEN 1 ELSE 0 END) as full_payment_terms,
                SUM(CASE WHEN term_type = 'installment' THEN 1 ELSE 0 END) as installment_terms
              FROM payment_terms";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load payment terms data.";
    error_log("Payment terms error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Terms Management</h1>
    <p class="welcome-subtitle">Configure payment terms and installment plans for tuition collection</p>
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

<!-- Payment Terms Statistics -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-pie"></i>
        Payment Terms Overview
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: var(--dark-blue); font-size: 2rem;"><?php echo $stats['total_terms'] ?? 0; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Total Terms</p>
        </div>
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: var(--primary-blue); font-size: 2rem;"><?php echo $stats['active_terms'] ?? 0; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Active Terms</p>
        </div>
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: #4CAF50; font-size: 2rem;"><?php echo $stats['full_payment_terms'] ?? 0; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Full Payment</p>
        </div>
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: #FF9800; font-size: 2rem;"><?php echo $stats['installment_terms'] ?? 0; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Installment Plans</p>
        </div>
    </div>
</div>

<!-- Add New Payment Term -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-plus-circle"></i>
        Add New Payment Term
    </h3>
    
    <button onclick="showAddModal()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.3s ease;">
        <i class="fas fa-plus"></i> Add Payment Term
    </button>
</div>

<!-- Current Payment Terms -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-calendar-alt"></i>
        Current Payment Terms
    </h3>
    
    <?php if (!empty($payment_terms)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
            <?php foreach ($payment_terms as $term): ?>
                <div style="border: 2px solid <?php echo $term['is_active'] ? ($term['is_default'] ? 'var(--primary-blue)' : 'var(--border-gray)') : '#ccc'; ?>; border-radius: 10px; padding: 1.5rem; position: relative; <?php echo !$term['is_active'] ? 'opacity: 0.6;' : ''; ?>">
                    
                    <!-- Status Badge -->
                    <div style="position: absolute; top: 1rem; right: 1rem; display: flex; gap: 0.5rem; flex-direction: column; align-items: flex-end;">
                        <?php if ($term['is_default']): ?>
                            <span style="background: var(--primary-blue); color: white; padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                                <i class="fas fa-star"></i> Default
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($term['is_active']): ?>
                            <span style="background: #4CAF50; color: white; padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                        <?php else: ?>
                            <span style="background: #f44336; color: white; padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                                <i class="fas fa-times-circle"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h4 style="color: var(--dark-blue); margin-bottom: 1rem; margin-right: 8rem;">
                        <?php if ($term['term_type'] === 'full_payment'): ?>
                            💰
                        <?php else: ?>
                            📅
                        <?php endif; ?>
                        <?php echo htmlspecialchars($term['term_name']); ?>
                    </h4>
                    
                    <div style="margin-bottom: 1rem;">
                        <p style="margin: 0.25rem 0; color: var(--black);"><strong>Type:</strong> 
                            <?php echo $term['term_type'] === 'full_payment' ? 'Full Payment' : 'Installment Plan'; ?>
                        </p>
                        <p style="margin: 0.25rem 0; color: var(--black);"><strong>School Year:</strong> <?php echo htmlspecialchars($term['school_year_name']); ?></p>
                        <p style="margin: 0.25rem 0; color: var(--black);"><strong>Grade Level:</strong> 
                            <?php echo $term['grade_name'] ? htmlspecialchars($term['grade_name']) : 'All Grades'; ?>
                        </p>
                    </div>
                    
                    <?php if ($term['term_type'] === 'full_payment'): ?>
                        <div style="background: #e8f5e8; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <h5 style="margin: 0 0 0.5rem 0; color: #2e7d32;">Full Payment Details</h5>
                            <?php if ($term['full_payment_due_date']): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($term['full_payment_due_date'])); ?></p>
                            <?php endif; ?>
                            <?php if ($term['full_payment_discount_percentage'] > 0): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Discount:</strong> <?php echo $term['full_payment_discount_percentage']; ?>%</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="background: #fff3e0; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <h5 style="margin: 0 0 0.5rem 0; color: #ef6c00;">Installment Plan Details</h5>
                            <?php if ($term['down_payment_amount']): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Down Payment:</strong> ₱<?php echo number_format($term['down_payment_amount'], 2); ?>
                                    <?php if ($term['down_payment_due_date']): ?>
                                        (Due: <?php echo date('M d, Y', strtotime($term['down_payment_due_date'])); ?>)
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($term['monthly_fee_amount']): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Monthly Fee:</strong> ₱<?php echo number_format($term['monthly_fee_amount'], 2); ?></p>
                            <?php endif; ?>
                            <?php if ($term['number_of_installments']): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Number of Installments:</strong> <?php echo $term['number_of_installments']; ?></p>
                            <?php endif; ?>
                            <?php if ($term['installment_start_month'] && $term['installment_start_year']): ?>
                                <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>Period:</strong> 
                                    <?php echo date('M Y', mktime(0, 0, 0, $term['installment_start_month'], 1, $term['installment_start_year'])); ?>
                                    <?php if ($term['installment_end_month'] && $term['installment_end_year']): ?>
                                        - <?php echo date('M Y', mktime(0, 0, 0, $term['installment_end_month'], 1, $term['installment_end_year'])); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($term['description']): ?>
                        <div style="background: var(--light-blue); padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <p style="margin: 0; color: var(--black); font-size: 0.9rem;">
                                <strong>Description:</strong><br>
                                <?php echo nl2br(htmlspecialchars($term['description'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 1rem;">
                        Created by <?php echo htmlspecialchars($term['created_by_username']); ?> 
                        on <?php echo date('M d, Y', strtotime($term['created_at'])); ?>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button onclick="editTerm(<?php echo htmlspecialchars(json_encode($term)); ?>)" 
                                style="background: var(--primary-blue); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="term_id" value="<?php echo $term['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $term['is_active'] ? '0' : '1'; ?>">
                            <button type="submit" style="background: <?php echo $term['is_active'] ? '#f44336' : '#4CAF50'; ?>; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;"
                                    onclick="return confirm('Are you sure you want to <?php echo $term['is_active'] ? 'deactivate' : 'activate'; ?> this payment term?')">
                                <i class="fas fa-<?php echo $term['is_active'] ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                <?php echo $term['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_term">
                            <input type="hidden" name="term_id" value="<?php echo $term['id']; ?>">
                            <button type="submit" style="background: #d32f2f; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;"
                                    onclick="return confirm('Are you sure you want to delete this payment term? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No payment terms found. Add the first payment term to get started.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="termModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto;">
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; margin: 2rem;">
        <h3 id="modalTitle" style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus-circle"></i>
            Add Payment Term
        </h3>
        
        <form method="POST" id="termForm">
            <input type="hidden" name="action" id="formAction" value="add_term">
            <input type="hidden" name="term_id" id="termId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Term Name:</label>
                    <input type="text" name="term_name" id="termName" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                           placeholder="e.g., Full Payment Early Bird">
                </div>
                
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Payment Type:</label>
                    <select name="term_type" id="termType" required onchange="toggleTermFields()"
                            style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                        <option value="">Select Type</option>
                        <option value="full_payment">Full Payment</option>
                        <option value="installment">Installment Plan</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">School Year:</label>
                    <select name="school_year_id" id="schoolYearId" required 
                            style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                        <option value="">Select School Year</option>
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['year_label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Grade Level:</label>
                    <select name="grade_level_id" id="gradeLevelId" 
                            style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                        <option value="">All Grade Levels</option>
                        <?php foreach ($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Full Payment Fields -->
            <div id="fullPaymentFields" style="display: none;">
                <h4 style="color: var(--dark-blue); margin: 1.5rem 0 1rem 0;">Full Payment Configuration</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Due Date:</label>
                        <input type="date" name="full_payment_due_date" id="fullPaymentDueDate" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Discount Percentage:</label>
                        <input type="number" name="full_payment_discount_percentage" id="fullPaymentDiscountPercentage" min="0" max="100" step="0.01" value="0"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="0.00">
                    </div>
                </div>
            </div>
            
            <!-- Installment Fields -->
            <div id="installmentFields" style="display: none;">
                <h4 style="color: var(--dark-blue); margin: 1.5rem 0 1rem 0;">Installment Plan Configuration</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Down Payment Amount:</label>
                        <input type="number" name="down_payment_amount" id="downPaymentAmount" min="0" step="0.01"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="5000.00">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Down Payment Due Date:</label>
                        <input type="date" name="down_payment_due_date" id="downPaymentDueDate" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Monthly Fee Amount:</label>
                        <input type="number" name="monthly_fee_amount" id="monthlyFeeAmount" min="0" step="0.01"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="15000.00">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Number of Installments:</label>
                        <input type="number" name="number_of_installments" id="numberOfInstallments" min="1" max="12"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="10">
                    </div>
                </div>
                
                <h5 style="color: var(--dark-blue); margin: 1rem 0 0.5rem 0;">Installment Period</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Start Month:</label>
                        <select name="installment_start_month" id="installmentStartMonth"
                                style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                            <option value="">Select Month</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Start Year:</label>
                        <input type="number" name="installment_start_year" id="installmentStartYear" min="2024" max="2030"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="2025">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">End Month:</label>
                        <select name="installment_end_month" id="installmentEndMonth"
                                style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                            <option value="">Select Month</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">End Year:</label>
                        <input type="number" name="installment_end_year" id="installmentEndYear" min="2024" max="2030"
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="2026">
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Description:</label>
                <textarea name="description" id="description" rows="3" 
                          style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black); resize: vertical;"
                          placeholder="Provide details about this payment term..."></textarea>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_default" id="isDefault" style="width: 18px; height: 18px;">
                    <span style="color: var(--black); font-weight: 500;">Set as Default Payment Term</span>
                    <small style="color: var(--gray); margin-left: 0.5rem;">(Only one default per school year and grade level)</small>
                </label>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeTermModal()" style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-save"></i> <span id="submitText">Add Term</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Finance Dashboard</a>
</div>

<script>
const monthNames = ["", "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];

function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Payment Term';
    document.getElementById('formAction').value = 'add_term';
    document.getElementById('submitText').textContent = 'Add Term';
    document.getElementById('termForm').reset();
    document.getElementById('termId').value = '';
    toggleTermFields();
    document.getElementById('termModal').style.display = 'flex';
}

function editTerm(term) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Payment Term';
    document.getElementById('formAction').value = 'update_term';
    document.getElementById('submitText').textContent = 'Update Term';
    
    document.getElementById('termId').value = term.id;
    document.getElementById('termName').value = term.term_name;
    document.getElementById('termType').value = term.term_type;
    document.getElementById('schoolYearId').value = term.school_year_id;
    document.getElementById('gradeLevelId').value = term.grade_level_id || '';
    document.getElementById('description').value = term.description || '';
    document.getElementById('isDefault').checked = term.is_default == 1;
    
    if (term.term_type === 'full_payment') {
        document.getElementById('fullPaymentDueDate').value = term.full_payment_due_date || '';
        document.getElementById('fullPaymentDiscountPercentage').value = term.full_payment_discount_percentage || '0';
    } else {
        document.getElementById('downPaymentAmount').value = term.down_payment_amount || '';
        document.getElementById('downPaymentDueDate').value = term.down_payment_due_date || '';
        document.getElementById('monthlyFeeAmount').value = term.monthly_fee_amount || '';
        document.getElementById('numberOfInstallments').value = term.number_of_installments || '';
        document.getElementById('installmentStartMonth').value = term.installment_start_month || '';
        document.getElementById('installmentStartYear').value = term.installment_start_year || '';
        document.getElementById('installmentEndMonth').value = term.installment_end_month || '';
        document.getElementById('installmentEndYear').value = term.installment_end_year || '';
    }
    
    toggleTermFields();
    document.getElementById('termModal').style.display = 'flex';
}

function closeTermModal() {
    document.getElementById('termModal').style.display = 'none';
}

function toggleTermFields() {
    const termType = document.getElementById('termType').value;
    const fullPaymentFields = document.getElementById('fullPaymentFields');
    const installmentFields = document.getElementById('installmentFields');
    
    if (termType === 'full_payment') {
        fullPaymentFields.style.display = 'block';
        installmentFields.style.display = 'none';
    } else if (termType === 'installment') {
        fullPaymentFields.style.display = 'none';
        installmentFields.style.display = 'block';
    } else {
        fullPaymentFields.style.display = 'none';
        installmentFields.style.display = 'none';
    }
}

// Close modal when clicking outside
document.getElementById('termModal').onclick = function(e) {
    if (e.target === this) {
        closeTermModal();
    }
}

// Set current year as default
document.addEventListener('DOMContentLoaded', function() {
    const currentYear = new Date().getFullYear();
    document.getElementById('installmentStartYear').value = currentYear;
    document.getElementById('installmentEndYear').value = currentYear + 1;
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
