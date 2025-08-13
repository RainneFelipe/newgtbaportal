<?php
require_once '../includes/auth_check.php';

// Check if user is a finance officer
if (!checkRole('finance')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Payment Methods - GTBA Portal';
$base_url = '../';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->connect();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_method':
                    $method_name = $_POST['method_name'];
                    $method_type = $_POST['method_type'];
                    $account_name = $_POST['account_name'] ?: null;
                    $account_number = $_POST['account_number'] ?: null;
                    $bank_name = $_POST['bank_name'] ?: null;
                    $display_name = $_POST['display_name'];
                    $instructions = $_POST['instructions'] ?: null;
                    $display_order = $_POST['display_order'];
                    
                    $query = "INSERT INTO payment_methods (method_name, method_type, account_name, account_number, bank_name, display_name, instructions, display_order)
                              VALUES (:method_name, :method_type, :account_name, :account_number, :bank_name, :display_name, :instructions, :display_order)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':method_name', $method_name);
                    $stmt->bindParam(':method_type', $method_type);
                    $stmt->bindParam(':account_name', $account_name);
                    $stmt->bindParam(':account_number', $account_number);
                    $stmt->bindParam(':bank_name', $bank_name);
                    $stmt->bindParam(':display_name', $display_name);
                    $stmt->bindParam(':instructions', $instructions);
                    $stmt->bindParam(':display_order', $display_order);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment method added successfully!";
                    } else {
                        $error_message = "Failed to add payment method.";
                    }
                    break;
                    
                case 'update_method':
                    $id = $_POST['payment_id'];
                    $method_name = $_POST['method_name'];
                    $method_type = $_POST['method_type'];
                    $account_name = $_POST['account_name'] ?: null;
                    $account_number = $_POST['account_number'] ?: null;
                    $bank_name = $_POST['bank_name'] ?: null;
                    $display_name = $_POST['display_name'];
                    $instructions = $_POST['instructions'] ?: null;
                    $display_order = $_POST['display_order'];
                    
                    $query = "UPDATE payment_methods 
                              SET method_name = :method_name,
                                  method_type = :method_type,
                                  account_name = :account_name,
                                  account_number = :account_number,
                                  bank_name = :bank_name,
                                  display_name = :display_name,
                                  instructions = :instructions,
                                  display_order = :display_order,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':method_name', $method_name);
                    $stmt->bindParam(':method_type', $method_type);
                    $stmt->bindParam(':account_name', $account_name);
                    $stmt->bindParam(':account_number', $account_number);
                    $stmt->bindParam(':bank_name', $bank_name);
                    $stmt->bindParam(':display_name', $display_name);
                    $stmt->bindParam(':instructions', $instructions);
                    $stmt->bindParam(':display_order', $display_order);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment method updated successfully!";
                    } else {
                        $error_message = "Failed to update payment method.";
                    }
                    break;
                    
                case 'toggle_status':
                    $id = $_POST['payment_id'];
                    $is_active = $_POST['is_active'] ? 1 : 0;
                    
                    $query = "UPDATE payment_methods 
                              SET is_active = :is_active,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':is_active', $is_active);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Payment method status updated successfully!";
                    } else {
                        $error_message = "Failed to update payment method status.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
        error_log("Payment methods error: " . $e->getMessage());
    }
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get all payment methods
    $query = "SELECT * FROM payment_methods ORDER BY display_order ASC, method_type ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment method statistics
    $query = "SELECT method_type, COUNT(*) as count, 
                     SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
              FROM payment_methods 
              GROUP BY method_type 
              ORDER BY method_type";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $method_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load payment methods data.";
    error_log("Payment methods error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Methods Management</h1>
    <p class="welcome-subtitle">Manage available payment methods for tuition and fee collection</p>
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

<!-- Payment Method Statistics -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-pie"></i>
        Payment Method Overview
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <?php 
        $total_methods = array_sum(array_column($method_stats, 'count'));
        $total_active = array_sum(array_column($method_stats, 'active_count'));
        ?>
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: var(--dark-blue); font-size: 2rem;"><?php echo $total_methods; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Total Methods</p>
        </div>
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; color: var(--primary-blue); font-size: 2rem;"><?php echo $total_active; ?></h4>
            <p style="margin: 0.5rem 0 0 0; color: var(--black);">Active Methods</p>
        </div>
    </div>
    
    <?php if (!empty($method_stats)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Method Type</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Total Methods</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Active Methods</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($method_stats as $stat): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php if ($stat['method_type'] === 'Bank'): ?>
                                    🏦 Bank Transfer
                                <?php elseif ($stat['method_type'] === 'E-Wallet'): ?>
                                    📱 E-Wallet
                                <?php else: ?>
                                    💵 Cash Payment
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <?php echo $stat['count']; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <span style="background: var(--primary-blue); color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                    <?php echo $stat['active_count']; ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php if ($stat['active_count'] > 0): ?>
                                    <span style="color: #4CAF50; font-weight: 600;">
                                        <i class="fas fa-check-circle"></i> Available
                                    </span>
                                <?php else: ?>
                                    <span style="color: #f44336; font-weight: 600;">
                                        <i class="fas fa-times-circle"></i> None Active
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add New Payment Method -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-plus-circle"></i>
        Add New Payment Method
    </h3>
    
    <button onclick="showAddModal()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.3s ease;">
        <i class="fas fa-plus"></i> Add Payment Method
    </button>
</div>

<!-- Current Payment Methods -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-credit-card"></i>
        Current Payment Methods
    </h3>
    
    <?php if (!empty($payment_methods)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($payment_methods as $method): ?>
                <div style="border: 2px solid <?php echo $method['is_active'] ? 'var(--primary-blue)' : 'var(--border-gray)'; ?>; border-radius: 10px; padding: 1.5rem; position: relative; <?php echo !$method['is_active'] ? 'opacity: 0.6;' : ''; ?>">
                    <!-- Status Badge -->
                    <div style="position: absolute; top: 1rem; right: 1rem;">
                        <?php if ($method['is_active']): ?>
                            <span style="background: #4CAF50; color: white; padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                        <?php else: ?>
                            <span style="background: #f44336; color: white; padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                                <i class="fas fa-times-circle"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h4 style="color: var(--dark-blue); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; margin-right: 5rem;">
                        <?php if ($method['method_type'] === 'Bank'): ?>
                            🏦
                        <?php elseif ($method['method_type'] === 'E-Wallet'): ?>
                            📱
                        <?php else: ?>
                            💵
                        <?php endif; ?>
                        <?php echo htmlspecialchars($method['display_name']); ?>
                    </h4>
                    
                    <div style="margin-bottom: 1rem;">
                        <p style="margin: 0.25rem 0; color: var(--black);"><strong>Type:</strong> <?php echo htmlspecialchars($method['method_type']); ?></p>
                        
                        <?php if ($method['account_name']): ?>
                            <p style="margin: 0.25rem 0; color: var(--black);"><strong>Account Name:</strong> <?php echo htmlspecialchars($method['account_name']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($method['account_number']): ?>
                            <p style="margin: 0.25rem 0; color: var(--black);">
                                <strong>Account Number:</strong> 
                                <span style="font-family: monospace; background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: 5px;">
                                    <?php echo htmlspecialchars($method['account_number']); ?>
                                </span>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($method['bank_name']): ?>
                            <p style="margin: 0.25rem 0; color: var(--black);"><strong>Bank:</strong> <?php echo htmlspecialchars($method['bank_name']); ?></p>
                        <?php endif; ?>
                        
                        <p style="margin: 0.25rem 0; color: var(--black);"><strong>Display Order:</strong> <?php echo $method['display_order']; ?></p>
                    </div>
                    
                    <?php if ($method['instructions']): ?>
                        <div style="background: var(--light-blue); padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <p style="margin: 0; color: var(--black); font-size: 0.9rem;">
                                <strong>Instructions:</strong><br>
                                <?php echo nl2br(htmlspecialchars($method['instructions'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button onclick="editMethod(<?php echo htmlspecialchars(json_encode($method)); ?>)" 
                                style="background: var(--primary-blue); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="payment_id" value="<?php echo $method['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $method['is_active'] ? '0' : '1'; ?>">
                            <button type="submit" style="background: <?php echo $method['is_active'] ? '#f44336' : '#4CAF50'; ?>; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-size: 0.9rem;"
                                    onclick="return confirm('Are you sure you want to <?php echo $method['is_active'] ? 'deactivate' : 'activate'; ?> this payment method?')">
                                <i class="fas fa-<?php echo $method['is_active'] ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                <?php echo $method['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No payment methods found. Add the first payment method to get started.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="methodModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3 id="modalTitle" style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus-circle"></i>
            Add Payment Method
        </h3>
        
        <form method="POST" id="methodForm">
            <input type="hidden" name="action" id="formAction" value="add_method">
            <input type="hidden" name="payment_id" id="paymentId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Method Name (Internal):</label>
                    <input type="text" name="method_name" id="methodName" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                           placeholder="e.g., gcash_primary">
                </div>
                
                <div>
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Method Type:</label>
                    <select name="method_type" id="methodType" required onchange="toggleFields()"
                            style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
                        <option value="">Select Type</option>
                        <option value="Bank">Bank Transfer</option>
                        <option value="E-Wallet">E-Wallet</option>
                        <option value="Cash">Cash Payment</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Display Name:</label>
                <input type="text" name="display_name" id="displayName" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                       placeholder="e.g., GCash Payment">
            </div>
            
            <div id="accountFields" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Account Name:</label>
                        <input type="text" name="account_name" id="accountName" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="Account holder name">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Account Number:</label>
                        <input type="text" name="account_number" id="accountNumber" 
                               style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                               placeholder="Account/phone number">
                    </div>
                </div>
                
                <div id="bankField" style="display: none; margin-bottom: 1rem;">
                    <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Bank Name:</label>
                    <input type="text" name="bank_name" id="bankName" 
                           style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);"
                           placeholder="Bank name">
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Display Order:</label>
                <input type="number" name="display_order" id="displayOrder" min="1" value="1" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black);">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; color: var(--black); font-weight: 500; margin-bottom: 0.5rem;">Instructions for Students:</label>
                <textarea name="instructions" id="instructions" rows="4" 
                          style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black); resize: vertical;"
                          placeholder="Provide instructions on how to use this payment method..."></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeMethodModal()" style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-save"></i> <span id="submitText">Add Method</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Finance Dashboard</a>
</div>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Payment Method';
    document.getElementById('formAction').value = 'add_method';
    document.getElementById('submitText').textContent = 'Add Method';
    document.getElementById('methodForm').reset();
    document.getElementById('paymentId').value = '';
    toggleFields();
    document.getElementById('methodModal').style.display = 'flex';
}

function editMethod(method) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Payment Method';
    document.getElementById('formAction').value = 'update_method';
    document.getElementById('submitText').textContent = 'Update Method';
    
    document.getElementById('paymentId').value = method.id;
    document.getElementById('methodName').value = method.method_name;
    document.getElementById('methodType').value = method.method_type;
    document.getElementById('displayName').value = method.display_name;
    document.getElementById('accountName').value = method.account_name || '';
    document.getElementById('accountNumber').value = method.account_number || '';
    document.getElementById('bankName').value = method.bank_name || '';
    document.getElementById('displayOrder').value = method.display_order;
    document.getElementById('instructions').value = method.instructions || '';
    
    toggleFields();
    document.getElementById('methodModal').style.display = 'flex';
}

function closeMethodModal() {
    document.getElementById('methodModal').style.display = 'none';
}

function toggleFields() {
    const methodType = document.getElementById('methodType').value;
    const accountFields = document.getElementById('accountFields');
    const bankField = document.getElementById('bankField');
    
    if (methodType === 'Cash') {
        accountFields.style.display = 'none';
        bankField.style.display = 'none';
    } else {
        accountFields.style.display = 'block';
        if (methodType === 'Bank') {
            bankField.style.display = 'block';
        } else {
            bankField.style.display = 'none';
        }
    }
}

// Close modal when clicking outside
document.getElementById('methodModal').onclick = function(e) {
    if (e.target === this) {
        closeMethodModal();
    }
}
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
