<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    exit('Unauthorized access');
}

$database = new Database();
$conn = $database->connect();

$student_id = $_GET['id'] ?? 0;

if (!$student_id || !is_numeric($student_id)) {
    echo '<p style="text-align: center; color: var(--danger); padding: 2rem;">Invalid student ID provided.</p>';
    exit;
}

// Get student details with all related information
$sql = "SELECT s.*, 
               gl.grade_name,
               sec.section_name,
               sy.year_label,
               u.email, u.username,
               father.first_name as father_first_name,
               father.last_name as father_last_name,
               father.middle_name as father_middle_name,
               father.date_of_birth as father_dob,
               father.occupation as father_occupation,
               father.religion as father_religion,
               father.contact_number as father_contact,
               father.email_address as father_email,
               mother.first_name as mother_first_name,
               mother.last_name as mother_last_name,
               mother.middle_name as mother_middle_name,
               mother.date_of_birth as mother_dob,
               mother.occupation as mother_occupation,
               mother.religion as mother_religion,
               mother.contact_number as mother_contact,
               mother.email_address as mother_email,
               guardian.first_name as guardian_first_name,
               guardian.last_name as guardian_last_name,
               guardian.middle_name as guardian_middle_name,
               guardian.date_of_birth as guardian_dob,
               guardian.occupation as guardian_occupation,
               guardian.religion as guardian_religion,
               guardian.contact_number as guardian_contact,
               guardian.email_address as guardian_email
        FROM students s 
        LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
        LEFT JOIN sections sec ON s.current_section_id = sec.id 
        LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
        LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
        LEFT JOIN student_guardians guardian ON s.legal_guardian_id = guardian.id AND guardian.guardian_type = 'Legal Guardian'
        WHERE s.id = ? AND s.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '
    <div style="text-align: center; padding: 3rem; color: var(--gray);">
        <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem; color: var(--border-gray);"></i>
        <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Student Not Found</h3>
        <p>The requested student record could not be found or may have been removed.</p>
    </div>';
    exit;
}

$full_name = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'] . ($student['suffix'] ? ' ' . $student['suffix'] : ''));
?>

<div class="student-details">
    <div class="detail-header">
        <div class="student-avatar-large">
            <i class="fas fa-user"></i>
        </div>
        <div class="student-title">
            <h2><?php echo htmlspecialchars($full_name); ?></h2>
            <div class="student-meta">
                <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                    <?php echo htmlspecialchars($student['enrollment_status']); ?>
                </span>
                <span class="type-badge type-<?php echo strtolower($student['student_type']); ?>">
                    <?php echo htmlspecialchars($student['student_type']); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="detail-sections">
        <!-- Basic Information -->
        <div class="detail-section">
            <h3><i class="fas fa-id-card"></i> Basic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Student ID</label>
                    <span><?php echo htmlspecialchars($student['student_id']); ?></span>
                </div>
                <div class="detail-item">
                    <label>LRN (Learner Reference Number)</label>
                    <span><?php echo htmlspecialchars($student['lrn']); ?></span>
                </div>
                <div class="detail-item">
                    <label>First Name</label>
                    <span><?php echo htmlspecialchars($student['first_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Middle Name</label>
                    <span><?php echo htmlspecialchars($student['middle_name'] ?: 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Last Name</label>
                    <span><?php echo htmlspecialchars($student['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Suffix</label>
                    <span><?php echo htmlspecialchars($student['suffix'] ?: 'None'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Gender</label>
                    <span><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth</label>
                    <span><?php echo date('F j, Y', strtotime($student['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <label>Place of Birth</label>
                    <span><?php echo htmlspecialchars($student['place_of_birth']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Religion</label>
                    <span><?php echo htmlspecialchars($student['religion'] ?: 'Not specified'); ?></span>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="detail-section">
            <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Current Grade</label>
                    <span><?php echo htmlspecialchars($student['grade_name'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Section</label>
                    <span><?php echo htmlspecialchars($student['section_name'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="detail-item">
                    <label>School Year</label>
                    <span><?php echo htmlspecialchars($student['year_label'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Student Type</label>
                    <span><?php echo htmlspecialchars($student['student_type']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Enrollment Status</label>
                    <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                        <?php echo htmlspecialchars($student['enrollment_status']); ?>
                    </span>
                </div>
                
                <?php if ($student['enrollment_status'] === 'Transferred'): ?>
                    <div class="detail-item">
                        <label>Transferred To</label>
                        <span><?php echo htmlspecialchars($student['transferred_to_school'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Transfer Date</label>
                        <span><?php echo $student['transfer_date'] ? date('F d, Y', strtotime($student['transfer_date'])) : 'Not specified'; ?></span>
                    </div>
                    <?php if ($student['transfer_reason']): ?>
                    <div class="detail-item full-width">
                        <label>Transfer Reason</label>
                        <span><?php echo htmlspecialchars($student['transfer_reason']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="detail-item">
                    <label>Account Status</label>
                    <span class="status-badge status-<?php echo $student['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="detail-section">
            <h3><i class="fas fa-map-marker-alt"></i> Address & Contact</h3>
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <label>Present Address</label>
                    <span><?php echo htmlspecialchars($student['present_address']); ?></span>
                </div>
                <div class="detail-item full-width">
                    <label>Permanent Address</label>
                    <span><?php echo htmlspecialchars($student['permanent_address']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($student['email'] ?: 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Username</label>
                    <span><?php echo htmlspecialchars($student['username']); ?></span>
                </div>
            </div>
        </div>

        <!-- Guardian Information -->
        <?php if ($student['father_first_name'] || $student['mother_first_name'] || $student['guardian_first_name']): ?>
        <div class="detail-section">
            <h3><i class="fas fa-users"></i> Guardian Information</h3>
            
            <?php if ($student['father_first_name']): ?>
            <div class="guardian-info">
                <h4><i class="fas fa-male"></i> Father</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Full Name</label>
                        <span><?php echo htmlspecialchars(trim($student['father_first_name'] . ' ' . ($student['father_middle_name'] ? $student['father_middle_name'] . ' ' : '') . $student['father_last_name'])); ?></span>
                    </div>
                    <?php if ($student['father_dob']): ?>
                    <div class="detail-item">
                        <label>Date of Birth</label>
                        <span><?php echo date('F j, Y', strtotime($student['father_dob'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Occupation</label>
                        <span><?php echo htmlspecialchars($student['father_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <?php if ($student['father_religion']): ?>
                    <div class="detail-item">
                        <label>Religion</label>
                        <span><?php echo htmlspecialchars($student['father_religion']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Contact Number</label>
                        <span><?php echo htmlspecialchars($student['father_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($student['father_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($student['mother_first_name']): ?>
            <div class="guardian-info">
                <h4><i class="fas fa-female"></i> Mother</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Full Name</label>
                        <span><?php echo htmlspecialchars(trim($student['mother_first_name'] . ' ' . ($student['mother_middle_name'] ? $student['mother_middle_name'] . ' ' : '') . $student['mother_last_name'])); ?></span>
                    </div>
                    <?php if ($student['mother_dob']): ?>
                    <div class="detail-item">
                        <label>Date of Birth</label>
                        <span><?php echo date('F j, Y', strtotime($student['mother_dob'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Occupation</label>
                        <span><?php echo htmlspecialchars($student['mother_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <?php if ($student['mother_religion']): ?>
                    <div class="detail-item">
                        <label>Religion</label>
                        <span><?php echo htmlspecialchars($student['mother_religion']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Contact Number</label>
                        <span><?php echo htmlspecialchars($student['mother_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($student['mother_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($student['guardian_first_name']): ?>
            <div class="guardian-info">
                <h4><i class="fas fa-user-shield"></i> Legal Guardian</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Full Name</label>
                        <span><?php echo htmlspecialchars(trim($student['guardian_first_name'] . ' ' . ($student['guardian_middle_name'] ? $student['guardian_middle_name'] . ' ' : '') . $student['guardian_last_name'])); ?></span>
                    </div>
                    <?php if ($student['guardian_dob']): ?>
                    <div class="detail-item">
                        <label>Date of Birth</label>
                        <span><?php echo date('F j, Y', strtotime($student['guardian_dob'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Occupation</label>
                        <span><?php echo htmlspecialchars($student['guardian_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <?php if ($student['guardian_religion']): ?>
                    <div class="detail-item">
                        <label>Religion</label>
                        <span><?php echo htmlspecialchars($student['guardian_religion']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <label>Contact Number</label>
                        <span><?php echo htmlspecialchars($student['guardian_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($student['guardian_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Emergency Contact -->
        <?php if ($student['emergency_contact_name']): ?>
        <div class="detail-section">
            <h3><i class="fas fa-phone"></i> Emergency Contact</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Contact Name</label>
                    <span><?php echo htmlspecialchars($student['emergency_contact_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Contact Number</label>
                    <span><?php echo htmlspecialchars($student['emergency_contact_number'] ?: 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Relationship</label>
                    <span><?php echo htmlspecialchars($student['emergency_contact_relationship'] ?: 'Not specified'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Medical Information -->
        <?php if ($student['medical_conditions'] || $student['allergies'] || $student['special_needs']): ?>
        <div class="detail-section">
            <h3><i class="fas fa-medkit"></i> Medical Information</h3>
            <div class="detail-grid">
                <?php if ($student['medical_conditions']): ?>
                <div class="detail-item full-width">
                    <label>Medical Conditions</label>
                    <span><?php echo htmlspecialchars($student['medical_conditions']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($student['allergies']): ?>
                <div class="detail-item full-width">
                    <label>Allergies</label>
                    <span><?php echo htmlspecialchars($student['allergies']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($student['special_needs']): ?>
                <div class="detail-item full-width">
                    <label>Special Needs</label>
                    <span><?php echo htmlspecialchars($student['special_needs']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registration Information -->
        <div class="detail-section">
            <h3><i class="fas fa-calendar"></i> Registration Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Register Date</label>
                    <span><?php echo date('F j, Y g:i A', strtotime($student['created_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <label>Last Updated</label>
                    <span><?php echo date('F j, Y g:i A', strtotime($student['updated_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-actions">
        <button class="btn btn-primary" onclick="editStudent(<?php echo $student['id']; ?>)">
            <i class="fas fa-edit"></i> Edit Student
        </button>
        <button class="btn btn-secondary" onclick="printRecord(<?php echo $student['id']; ?>)">
            <i class="fas fa-print"></i> Print Record
        </button>
    </div>
</div>

<style>
.student-details {
    max-height: 80vh;
    overflow-y: auto;
    padding-bottom: 2rem;
}

.student-details::-webkit-scrollbar {
    width: 8px;
}

.student-details::-webkit-scrollbar-track {
    background: var(--light-gray);
    border-radius: 4px;
}

.student-details::-webkit-scrollbar-thumb {
    background: var(--primary-blue);
    border-radius: 4px;
}

.student-details::-webkit-scrollbar-thumb:hover {
    background: var(--dark-blue);
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-gray);
}

.student-avatar-large {
    width: 80px;
    height: 80px;
    background: var(--primary-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    flex-shrink: 0;
}

.student-title h2 {
    margin: 0 0 0.5rem 0;
    color: var(--dark-blue);
}

.student-meta {
    display: flex;
    gap: 0.5rem;
}

.detail-sections {
    space: 1.5rem;
}

.detail-section {
    margin-bottom: 1.5rem;
    background: var(--light-gray);
    border-radius: 12px;
    padding: 1.5rem;
}

.detail-section h3 {
    margin: 0 0 1rem 0;
    color: var(--dark-blue);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-gray);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item label {
    font-weight: 600;
    color: var(--gray);
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-item span {
    color: var(--black);
    font-size: 0.95rem;
    line-height: 1.4;
}

.guardian-info {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--white);
    border-radius: 8px;
    border-left: 4px solid var(--primary-blue);
}

.guardian-info:last-child {
    margin-bottom: 0;
}

.guardian-info h4 {
    margin: 0 0 1rem 0;
    color: var(--dark-blue);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-gray);
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Status badges styling */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-enrolled, .status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-dropped, .status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.status-graduated {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-transferred {
    background-color: #fff3cd;
    color: #856404;
}

.type-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-continuing {
    background-color: #e2e3e5;
    color: #383d41;
}

.type-transfer, .type-new {
    background-color: #ffeaa7;
    color: #6c5ce7;
}

@media (max-width: 768px) {
    .detail-header {
        flex-direction: column;
        text-align: center;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>
