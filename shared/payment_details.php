<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user has appropriate role
$is_student = checkRole('student');
$is_finance = checkRole('finance');

if (!$is_student && !$is_finance) {
    header('Location: ../auth/login.php');
    exit();
}

$payment_id = $_GET['id'] ?? null;
if (!$payment_id) {
    header('Location: ' . ($is_student ? '../student/payments.php' : '../finance/payment_verification.php'));
    exit();
}

$database = new Database();
$db = $database->connect();

// Get payment details with related information
$payment_query = "SELECT sp.*, 
                         s.first_name as student_first_name, s.last_name as student_last_name, 
                         s.student_id, s.middle_name,
                         gl.grade_name,
                         pt.term_name, pt.term_type, pt.number_of_installments,
                         pt.down_payment_amount, pt.monthly_fee_amount, pt.full_payment_amount,
                         u_verified.username as verified_by_username,
                         u_created.username as created_by_username
                  FROM student_payments sp
                  JOIN students s ON sp.student_id = s.id
                  LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                  JOIN payment_terms pt ON sp.payment_term_id = pt.id
                  LEFT JOIN users u_verified ON sp.verified_by = u_verified.id
                  LEFT JOIN users u_created ON s.created_by = u_created.id
                  WHERE sp.id = :payment_id";

// Add security check for students - they can only view their own payments
if ($is_student) {
    $student_info_query = "SELECT id FROM students WHERE user_id = :user_id";
    $student_stmt = $db->prepare($student_info_query);
    $student_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $student_stmt->execute();
    $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student_info) {
        header('Location: ../student/payments.php');
        exit();
    }
    
    $payment_query .= " AND s.user_id = :user_id";
}

$payment_stmt = $db->prepare($payment_query);
$payment_stmt->bindParam(':payment_id', $payment_id);
if ($is_student) {
    $payment_stmt->bindParam(':user_id', $_SESSION['user_id']);
}
$payment_stmt->execute();
$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: ' . ($is_student ? '../student/payments.php' : '../finance/payment_verification.php'));
    exit();
}

// Get payment uploads/attachments if any
$uploads_query = "SELECT * FROM payment_uploads WHERE payment_id = :payment_id ORDER BY uploaded_at";
$uploads_stmt = $db->prepare($uploads_query);
$uploads_stmt->bindParam(':payment_id', $payment_id);
$uploads_stmt->execute();
$uploads = $uploads_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Payment Details - GTBA Portal';
$base_url = '../';

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Payment Details</h1>
    <p class="welcome-subtitle">Complete payment information and verification status</p>
</div>

<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <h3 style="color: var(--dark-blue); margin: 0 0 0.5rem 0;">Payment #<?php echo $payment_id; ?></h3>
            <p style="color: var(--gray); margin: 0;">Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($payment['submitted_at'])); ?></p>
        </div>
        <div>
            <?php
            $status_colors = [
                'pending' => 'var(--warning)',
                'verified' => 'var(--success)', 
                'rejected' => 'var(--danger)'
            ];
            $status_color = $status_colors[$payment['verification_status']];
            ?>
            <span style="background: <?php echo $status_color; ?>; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; text-transform: uppercase; font-size: 0.9rem;">
                <?php echo $payment['verification_status']; ?>
            </span>
        </div>
    </div>

    <!-- Student Information -->
    <div style="background: var(--light-blue); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <h4 style="color: var(--dark-blue); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-user"></i> Student Information
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <p style="margin: 0.5rem 0;"><strong>Name:</strong> 
                <?php 
                echo htmlspecialchars($payment['student_first_name'] . ' ' . 
                    ($payment['middle_name'] ? $payment['middle_name'] . ' ' : '') . 
                    $payment['student_last_name']); 
                ?>
            </p>
            <p style="margin: 0.5rem 0;"><strong>Student ID:</strong> <?php echo htmlspecialchars($payment['student_id']); ?></p>
            <p style="margin: 0.5rem 0;"><strong>Grade Level:</strong> <?php echo htmlspecialchars($payment['grade_name']); ?></p>
        </div>
    </div>

    <!-- Payment Term Information -->
    <div style="background: var(--light-gray); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <h4 style="color: var(--dark-blue); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-calendar-alt"></i> Payment Term Details
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <p style="margin: 0.5rem 0;"><strong>Term:</strong> <?php echo htmlspecialchars($payment['term_name']); ?></p>
            <p style="margin: 0.5rem 0;"><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['term_type']))); ?></p>
            <?php if ($payment['term_type'] === 'installment'): ?>
                <p style="margin: 0.5rem 0;"><strong>Total Installments:</strong> <?php echo $payment['number_of_installments']; ?></p>
                <p style="margin: 0.5rem 0;"><strong>Down Payment:</strong> ₱<?php echo number_format($payment['down_payment_amount'], 2); ?></p>
                <p style="margin: 0.5rem 0;"><strong>Monthly Fee:</strong> ₱<?php echo number_format($payment['monthly_fee_amount'], 2); ?></p>
            <?php else: ?>
                <p style="margin: 0.5rem 0;"><strong>Full Amount:</strong> ₱<?php echo number_format($payment['full_payment_amount'], 2); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Details -->
    <div style="background: #f8f9fa; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <h4 style="color: var(--dark-blue); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-money-bill-wave"></i> Payment Information
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <p style="margin: 0.5rem 0;"><strong>Payment Type:</strong> 
                <?php 
                $type_display = ucfirst(str_replace('_', ' ', $payment['payment_type']));
                if ($payment['installment_number']) {
                    $type_display .= " #{$payment['installment_number']}";
                }
                echo $type_display;
                ?>
            </p>
            <p style="margin: 0.5rem 0;"><strong>Amount Paid:</strong> 
                <span style="color: var(--success); font-weight: 700; font-size: 1.2rem;">₱<?php echo number_format($payment['amount_paid'], 2); ?></span>
            </p>
            <p style="margin: 0.5rem 0;"><strong>Payment Date:</strong> <?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></p>
            <p style="margin: 0.5rem 0;"><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></p>
            <?php if ($payment['reference_number']): ?>
                <p style="margin: 0.5rem 0;"><strong>Reference Number:</strong> <?php echo htmlspecialchars($payment['reference_number']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($payment['proof_notes']): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-gray);">
                <p style="margin: 0 0 0.5rem 0;"><strong>Additional Notes:</strong></p>
                <p style="margin: 0; background: white; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-blue);">
                    <?php echo nl2br(htmlspecialchars($payment['proof_notes'])); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Proof Images -->
    <?php if ($payment['proof_image'] || !empty($uploads)): ?>
        <div style="background: var(--white); border: 1px solid var(--border-gray); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
            <h4 style="color: var(--dark-blue); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-images"></i> Payment Proof
                <span style="background: var(--light-blue); color: var(--dark-blue); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; margin-left: auto;">
                    <?php echo ($payment['proof_image'] ? 1 : 0) + count($uploads); ?> file(s)
                </span>
            </h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <?php if ($payment['proof_image']): ?>
                    <div class="proof-image-container" onclick="openImageModal('<?php echo htmlspecialchars($base_url . $payment['proof_image']); ?>', 'Primary Payment Proof')">
                        <div style="position: relative; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s ease;">
                            <?php
                            $file_extension = strtolower(pathinfo($payment['proof_image'], PATHINFO_EXTENSION));
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])):
                            ?>
                                <img src="<?php echo htmlspecialchars($base_url . $payment['proof_image']); ?>" 
                                     alt="Payment Proof" 
                                     style="width: 100%; height: 200px; object-fit: cover; display: block;"
                                     loading="lazy">
                                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 1rem 0.75rem 0.75rem; font-size: 0.9rem;">
                                    <div style="font-weight: 600;">Primary Payment Proof</div>
                                    <div style="opacity: 0.9; font-size: 0.8rem;">Click to view full size</div>
                                </div>
                            <?php else: ?>
                                <div style="background: var(--light-gray); height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--gray);">
                                    <i class="fas fa-file-pdf" style="font-size: 3rem; margin-bottom: 0.5rem;"></i>
                                    <div style="font-weight: 600;">PDF Document</div>
                                    <div style="font-size: 0.9rem;">Click to download</div>
                                </div>
                            <?php endif; ?>
                            <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                <i class="fas fa-expand-alt"></i>
                            </div>
                            <div style="position: absolute; top: 10px; left: 10px; background: var(--primary-blue); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">
                                PRIMARY
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($uploads as $index => $upload): ?>
                    <div class="proof-image-container" onclick="openImageModal('<?php echo htmlspecialchars($base_url . $upload['file_path']); ?>', '<?php echo htmlspecialchars($upload['original_filename']); ?>')">
                        <div style="position: relative; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s ease;">
                            <?php
                            $file_extension = strtolower(pathinfo($upload['file_path'], PATHINFO_EXTENSION));
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])):
                            ?>
                                <img src="<?php echo htmlspecialchars($base_url . $upload['file_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($upload['original_filename']); ?>" 
                                     style="width: 100%; height: 200px; object-fit: cover; display: block;"
                                     loading="lazy">
                            <?php else: ?>
                                <div style="background: var(--light-gray); height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--gray);">
                                    <i class="fas fa-file-pdf" style="font-size: 3rem; margin-bottom: 0.5rem;"></i>
                                    <div style="font-weight: 600;">PDF Document</div>
                                    <div style="font-size: 0.9rem;">Click to download</div>
                                </div>
                            <?php endif; ?>
                            <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 1rem 0.75rem 0.75rem; font-size: 0.9rem;">
                                <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($upload['original_filename']); ?>
                                </div>
                                <div style="opacity: 0.9; font-size: 0.8rem;">
                                    <?php echo number_format($upload['file_size'] / 1024, 1); ?> KB • 
                                    Uploaded <?php echo date('M d, Y', strtotime($upload['uploaded_at'])); ?>
                                </div>
                            </div>
                            <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                <i class="fas fa-expand-alt"></i>
                            </div>
                            <div style="position: absolute; top: 10px; left: 10px; background: var(--success); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">
                                <?php echo $index + 1; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-gray); display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php if ($payment['proof_image']): ?>
                    <a href="<?php echo htmlspecialchars($base_url . $payment['proof_image']); ?>" download 
                       style="background: var(--primary-blue); color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-download"></i> Download Primary Proof
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($uploads)): ?>
                    <button onclick="downloadAllFiles()" 
                            style="background: var(--success); color: white; padding: 0.5rem 1rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-download"></i> Download All Files
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Verification Information -->
    <?php if ($payment['verification_status'] !== 'pending'): ?>
        <div style="background: <?php echo $payment['verification_status'] === 'verified' ? '#e8f5e8' : '#ffebee'; ?>; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
            <h4 style="color: var(--dark-blue); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-<?php echo $payment['verification_status'] === 'verified' ? 'check-circle' : 'times-circle'; ?>"></i> 
                Verification Details
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <p style="margin: 0.5rem 0;"><strong>Status:</strong> 
                    <span style="color: <?php echo $status_color; ?>; font-weight: 600; text-transform: uppercase;">
                        <?php echo $payment['verification_status']; ?>
                    </span>
                </p>
                <?php if ($payment['verified_by_username']): ?>
                    <p style="margin: 0.5rem 0;"><strong>Verified By:</strong> <?php echo htmlspecialchars($payment['verified_by_username']); ?></p>
                <?php endif; ?>
                <?php if ($payment['verification_date']): ?>
                    <p style="margin: 0.5rem 0;"><strong>Verified On:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($payment['verification_date'])); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($payment['verification_notes']): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1);">
                    <p style="margin: 0 0 0.5rem 0;"><strong>Verification Notes:</strong></p>
                    <p style="margin: 0; background: white; padding: 1rem; border-radius: 8px; border-left: 4px solid <?php echo $status_color; ?>;">
                        <?php echo nl2br(htmlspecialchars($payment['verification_notes'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div style="text-align: center; padding-top: 1rem; border-top: 1px solid var(--border-gray);">
        <?php if ($is_student): ?>
            <a href="../student/payments.php" style="background: var(--primary-blue); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 1rem;">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
        <?php else: ?>
            <a href="../finance/payment_verification.php" style="background: var(--primary-blue); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 1rem;">
                <i class="fas fa-arrow-left"></i> Back to Verification
            </a>
        <?php endif; ?>
        
        <?php if ($payment['proof_image']): ?>
            <a href="<?php echo htmlspecialchars($base_url . $payment['proof_image']); ?>" download 
               style="background: var(--success); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-download"></i> Download Proof
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.9);">
    <div style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 2001;" onclick="closeImageModal()">&times;</div>
    <div style="position: absolute; top: 20px; left: 30px; color: white; font-size: 18px; font-weight: 600; z-index: 2001;" id="imageModalTitle"></div>
    
    <div style="display: flex; align-items: center; justify-content: center; height: 100%; padding: 60px 30px 30px;">
        <img id="modalImage" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
    </div>
    
    <div style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); color: white; text-align: center;">
        <p style="margin: 0; opacity: 0.8;">Use mouse wheel to zoom • Click and drag to pan</p>
    </div>
</div>

<style>
.proof-image-container:hover > div {
    transform: scale(1.05);
}

.proof-image-container:hover {
    z-index: 10;
}

#modalImage {
    cursor: grab;
    transition: transform 0.3s ease;
}

#modalImage:active {
    cursor: grabbing;
}

#modalImage.zoomed {
    cursor: move;
}

@media (max-width: 768px) {
    #imageModal div[style*="top: 20px; right: 30px"] {
        top: 10px;
        right: 15px;
        font-size: 30px;
    }
    
    #imageModal div[style*="top: 20px; left: 30px"] {
        top: 15px;
        left: 15px;
        font-size: 16px;
    }
    
    #imageModal div[style*="display: flex"] {
        padding: 50px 15px 20px;
    }
}
</style>

<script>
let currentZoom = 1;
let currentX = 0;
let currentY = 0;
let isDragging = false;
let lastX = 0;
let lastY = 0;

function openImageModal(imageSrc, title) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('imageModalTitle');
    
    modalImage.src = imageSrc;
    modalTitle.textContent = title || 'Payment Proof';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset zoom and position
    currentZoom = 1;
    currentX = 0;
    currentY = 0;
    updateImageTransform();
    
    // Add event listeners for zoom and pan
    modalImage.addEventListener('wheel', handleZoom);
    modalImage.addEventListener('mousedown', startDrag);
    modalImage.addEventListener('mousemove', drag);
    modalImage.addEventListener('mouseup', endDrag);
    modalImage.addEventListener('mouseleave', endDrag);
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Remove event listeners
    modalImage.removeEventListener('wheel', handleZoom);
    modalImage.removeEventListener('mousedown', startDrag);
    modalImage.removeEventListener('mousemove', drag);
    modalImage.removeEventListener('mouseup', endDrag);
    modalImage.removeEventListener('mouseleave', endDrag);
}

function handleZoom(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? 0.9 : 1.1;
    currentZoom *= delta;
    currentZoom = Math.max(0.5, Math.min(5, currentZoom)); // Limit zoom between 0.5x and 5x
    updateImageTransform();
    
    const modalImage = document.getElementById('modalImage');
    if (currentZoom > 1) {
        modalImage.classList.add('zoomed');
    } else {
        modalImage.classList.remove('zoomed');
        currentX = 0;
        currentY = 0;
        updateImageTransform();
    }
}

function startDrag(e) {
    if (currentZoom <= 1) return;
    isDragging = true;
    lastX = e.clientX;
    lastY = e.clientY;
}

function drag(e) {
    if (!isDragging || currentZoom <= 1) return;
    
    const deltaX = e.clientX - lastX;
    const deltaY = e.clientY - lastY;
    
    currentX += deltaX;
    currentY += deltaY;
    
    lastX = e.clientX;
    lastY = e.clientY;
    
    updateImageTransform();
}

function endDrag() {
    isDragging = false;
}

function updateImageTransform() {
    const modalImage = document.getElementById('modalImage');
    modalImage.style.transform = `scale(${currentZoom}) translate(${currentX / currentZoom}px, ${currentY / currentZoom}px)`;
}

// Close modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

// Prevent context menu on modal image
document.getElementById('modalImage').addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

// Download all files function
function downloadAllFiles() {
    <?php if ($payment['proof_image']): ?>
        // Download primary proof
        const link1 = document.createElement('a');
        link1.href = '<?php echo htmlspecialchars($base_url . $payment['proof_image']); ?>';
        link1.download = 'Primary_Payment_Proof_<?php echo $payment_id; ?>';
        link1.click();
    <?php endif; ?>
    
    <?php foreach ($uploads as $index => $upload): ?>
        // Download additional file <?php echo $index + 1; ?>
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = '<?php echo htmlspecialchars($base_url . $upload['file_path']); ?>';
            link.download = '<?php echo htmlspecialchars($upload['original_filename']); ?>';
            link.click();
        }, <?php echo ($index + 1) * 500; ?>); // Stagger downloads by 500ms
    <?php endforeach; ?>
}
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
