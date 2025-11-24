<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Submit LRN - GTBA Portal';
$base_url = '../';

$database = new Database();
$db = $database->connect();

// Get student information
$student_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

try {
    $stmt = $db->prepare("SELECT s.*, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found");
    }
} catch (Exception $e) {
    $error_message = "Error loading student information: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_lrn'])) {
    try {
        $lrn = trim($_POST['lrn']);
        
        // Validate LRN format (12 digits)
        if (!preg_match('/^[0-9]{12}$/', $lrn)) {
            throw new Exception("LRN must be exactly 12 digits");
        }
        
        // Update student LRN (duplicates allowed since multiple students may not have LRN yet)
        $update_stmt = $db->prepare("UPDATE students SET lrn = ?, updated_at = NOW() WHERE user_id = ?");
        $update_stmt->execute([$lrn, $student_id]);
        
        $message = 'LRN submitted successfully!';
        $messageType = 'success';
        
        // Refresh student data
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">📝 Submit LRN</h1>
    <p class="welcome-subtitle">Enter your Learner Reference Number (LRN)</p>
</div>

<?php if ($message): ?>
    <div style="padding: 1rem; background: <?= $messageType === 'success' ? 'var(--success-light, #d4edda)' : 'var(--danger-light, #f8d7da)' ?>; border: 1px solid <?= $messageType === 'success' ? 'var(--success, #28a745)' : 'var(--danger, #dc3545)' ?>; border-radius: 8px; margin-bottom: 2rem;">
        <p style="color: <?= $messageType === 'success' ? 'var(--success-dark, #155724)' : 'var(--danger-dark, #721c24)' ?>; margin: 0;">
            <strong><?= $messageType === 'success' ? '✅ Success!' : '⚠️ Error:' ?></strong> <?= htmlspecialchars($message) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div style="padding: 1rem; background: var(--danger-light, #f8d7da); border: 1px solid var(--danger, #dc3545); border-radius: 8px; margin-bottom: 2rem;">
        <p style="color: var(--danger-dark, #721c24); margin: 0;">
            <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_message) ?>
        </p>
    </div>
<?php else: ?>
    <div style="background: var(--white, white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); max-width: 600px; margin: 0 auto;">
        
        <?php if (!empty($student['lrn'])): ?>
            <!-- LRN Already Submitted -->
            <div style="text-align: center; padding: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                <h3 style="color: var(--dark-blue, #1B4F72); margin-bottom: 1rem;">LRN Already Submitted</h3>
                <p style="color: var(--gray, #6c757d); margin-bottom: 1.5rem;">
                    Your Learner Reference Number has been recorded in our system.
                </p>
                
                <div style="background: var(--light-blue, #E8F4F8); padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <p style="color: var(--black, #000); margin: 0 0 0.5rem 0; font-size: 0.9rem; font-weight: 600;">
                        Your LRN:
                    </p>
                    <p style="color: var(--primary-blue, #2E86AB); margin: 0; font-size: 2rem; font-weight: 700; letter-spacing: 2px; font-family: monospace;">
                        <?= htmlspecialchars($student['lrn']) ?>
                    </p>
                </div>
                
                <div style="background: var(--light-gray, #f8f9fa); padding: 1rem; border-radius: 8px; font-size: 0.9rem; color: var(--gray, #6c757d);">
                    <p style="margin: 0;">
                        <strong>Note:</strong> If you need to update your LRN, please contact the registrar's office.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- LRN Submission Form -->
            <div style="margin-bottom: 2rem;">
                <h3 style="color: var(--dark-blue, #1B4F72); margin-bottom: 0.5rem;">What is LRN?</h3>
                <p style="color: var(--gray, #6c757d); margin: 0; line-height: 1.6;">
                    The Learner Reference Number (LRN) is a 12-digit number assigned by the Department of Education (DepEd) 
                    to each student in the Philippines. It serves as your unique identification throughout your basic education.
                </p>
            </div>
            
            <form method="POST" style="margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <label for="lrn" style="display: block; margin-bottom: 0.5rem; color: var(--black, #000); font-weight: 600;">
                        Enter Your LRN <span style="color: var(--danger, #dc3545);">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="lrn" 
                        name="lrn" 
                        required 
                        maxlength="12" 
                        pattern="[0-9]{12}"
                        placeholder="123456789012"
                        style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray, #dee2e6); border-radius: 8px; font-size: 1.1rem; font-family: monospace; letter-spacing: 1px; box-sizing: border-box;"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    >
                    <small style="display: block; margin-top: 0.5rem; color: var(--gray, #6c757d);">
                        Must be exactly 12 digits (numbers only)
                    </small>
                </div>
                
                <div style="background: var(--light-blue, #E8F4F8); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: var(--black, #000); font-size: 0.9rem;">
                        <strong>📌 Where to find your LRN:</strong>
                    </p>
                    <ul style="margin: 0.5rem 0 0 1.5rem; color: var(--gray, #6c757d); font-size: 0.9rem;">
                        <li>Form 137 (Permanent Record)</li>
                        <li>Certificate of Good Moral Character</li>
                        <li>Previous school documents</li>
                        <li>DepEd LRN Registry</li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button 
                        type="submit" 
                        name="submit_lrn"
                        style="flex: 1; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, var(--primary-blue, #2E86AB) 0%, var(--dark-blue, #1B4F72) 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; min-width: 150px;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(46, 134, 171, 0.3)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'"
                    >
                        <i class="fas fa-paper-plane"></i> Submit LRN
                    </button>
                    <a 
                        href="dashboard.php"
                        style="flex: 1; padding: 0.75rem 1.5rem; background: var(--light-gray, #f8f9fa); color: var(--black, #000); border: 2px solid var(--border-gray, #dee2e6); border-radius: 8px; font-weight: 600; text-decoration: none; text-align: center; font-size: 1rem; min-width: 150px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center;"
                    >
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
            
            <div style="background: var(--warning-light, #fff3cd); border: 1px solid var(--warning, #ffc107); padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
                <p style="margin: 0; color: var(--warning-dark, #856404);">
                    <strong>⚠️ Important:</strong> Make sure to enter your LRN correctly. 
                    If you don't have an LRN yet or need assistance, please contact the registrar's office.
                </p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Auto-format LRN input (add spaces for readability while typing)
document.getElementById('lrn')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    if (value.length > 12) {
        value = value.substring(0, 12);
    }
    e.target.value = value;
});

// Form validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const lrnInput = document.getElementById('lrn');
    const lrn = lrnInput.value.replace(/\s/g, '');
    
    if (lrn.length !== 12) {
        e.preventDefault();
        alert('LRN must be exactly 12 digits');
        lrnInput.focus();
        return false;
    }
    
    if (!/^[0-9]{12}$/.test(lrn)) {
        e.preventDefault();
        alert('LRN must contain only numbers');
        lrnInput.focus();
        return false;
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
