<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

$page_title = "Student Registration";
$base_url = "../";

// Handle form submission
$message = '';
$messageType = '';

if ($_POST && isset($_POST['register_student'])) {
    try {
        $conn->beginTransaction();
        
        // First create guardians
        $father_id = null;
        $mother_id = null;
        $guardian_id = null;
        
        // Create father record
        if (!empty($_POST['father_first_name'])) {
            $stmt = $conn->prepare("INSERT INTO student_guardians (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Father',
                $_POST['father_first_name'],
                $_POST['father_last_name'],
                $_POST['father_middle_name'] ?: null,
                $_POST['father_birth_date'] ?: null,
                $_POST['father_occupation'] ?: null,
                $_POST['father_religion'] ?: null,
                $_POST['father_contact'] ?: null,
                $_POST['father_email'] ?: null
            ]);
            $father_id = $conn->lastInsertId();
        }
        
        // Create mother record
        if (!empty($_POST['mother_first_name'])) {
            $stmt = $conn->prepare("INSERT INTO student_guardians (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Mother',
                $_POST['mother_first_name'],
                $_POST['mother_last_name'],
                $_POST['mother_middle_name'] ?: null,
                $_POST['mother_birth_date'] ?: null,
                $_POST['mother_occupation'] ?: null,
                $_POST['mother_religion'] ?: null,
                $_POST['mother_contact'] ?: null,
                $_POST['mother_email'] ?: null
            ]);
            $mother_id = $conn->lastInsertId();
        }
        
        // Create legal guardian if different
        if (!empty($_POST['guardian_first_name']) && $_POST['guardian_first_name'] !== $_POST['father_first_name'] && $_POST['guardian_first_name'] !== $_POST['mother_first_name']) {
            $stmt = $conn->prepare("INSERT INTO student_guardians (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Legal Guardian',
                $_POST['guardian_first_name'],
                $_POST['guardian_last_name'],
                $_POST['guardian_middle_name'] ?: null,
                $_POST['guardian_birth_date'] ?: null,
                $_POST['guardian_occupation'] ?: null,
                $_POST['guardian_religion'] ?: null,
                $_POST['guardian_contact'] ?: null,
                $_POST['guardian_email'] ?: null
            ]);
            $guardian_id = $conn->lastInsertId();
        }
        
        // Create user account for student
        $username = strtolower($_POST['student_id']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['student_email'] ?: $username . '@student.gtba.edu.ph';
        
        // Get student role ID
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'student'");
        $role_stmt->execute();
        $student_role = $role_stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $student_role['id']]);
        $user_id = $conn->lastInsertId();
        
        // Create student record
        $stmt = $conn->prepare("INSERT INTO students (user_id, student_id, lrn, student_type, first_name, last_name, middle_name, suffix, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, legal_guardian_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, medical_conditions, allergies, special_needs, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user_id,
            $_POST['student_id'],
            $_POST['lrn'],
            $_POST['student_type'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['middle_name'] ?: null,
            $_POST['suffix'] ?: null,
            $_POST['gender'],
            $_POST['date_of_birth'],
            $_POST['place_of_birth'],
            $_POST['religion'] ?: null,
            $_POST['present_address'],
            $_POST['permanent_address'],
            $father_id,
            $mother_id,
            $guardian_id,
            $_POST['emergency_contact_name'] ?: null,
            $_POST['emergency_contact_number'] ?: null,
            $_POST['emergency_contact_relationship'] ?: null,
            $_POST['grade_level_id'] ?: null,
            $_POST['school_year_id'] ?: null,
            $_POST['medical_conditions'] ?: null,
            $_POST['allergies'] ?: null,
            $_POST['special_needs'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $conn->commit();
        
        // Log the action (outside of transaction to avoid rollback issues)
        try {
            $user->logAudit($_SESSION['user_id'], 'Student Registration', 'students', $conn->lastInsertId(), 'Created new student account for ' . $_POST['first_name'] . ' ' . $_POST['last_name']);
        } catch (Exception $audit_e) {
            // Log audit error but don't fail the registration
            error_log("Audit log error: " . $audit_e->getMessage());
        }
        
        $message = 'Student registered successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $message = 'Error registering student: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get grade levels for dropdown
$grade_stmt = $conn->prepare("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order");
$grade_stmt->execute();
$grade_levels = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school years for dropdown
$school_year_stmt = $conn->prepare("SELECT * FROM school_years ORDER BY is_active DESC, year_label DESC");
$school_year_stmt->execute();
$school_years = $school_year_stmt->fetchAll(PDO::FETCH_ASSOC);

// Find the active school year
$active_school_year_id = null;
foreach ($school_years as $year) {
    if ($year['is_active']) {
        $active_school_year_id = $year['id'];
        break;
    }
}

include '../includes/header.php';
?>

<div class="main-wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <h1><i class="fas fa-user-plus"></i> Student Registration</h1>
            <p>Register new students and input complete information</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="registration-container">
            <form method="POST" class="registration-form">
                <!-- Student Basic Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-user"></i>
                        <h3>Student Information</h3>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_id" class="required">Student ID</label>
                            <input type="text" id="student_id" name="student_id" required maxlength="20">
                            <small>Unique school-generated ID</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="lrn" class="required">LRN (Learners Reference Number)</label>
                            <input type="text" id="lrn" name="lrn" required maxlength="12" pattern="[0-9]{12}">
                            <small>12-digit number from DepEd</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_type" class="required">Student Type</label>
                            <select id="student_type" name="student_type" required>
                                <option value="">Select Type</option>
                                <option value="New">New Student</option>
                                <option value="Transfer">Transfer Student</option>
                                <option value="Continuing">Continuing Student</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_level_id">Grade Level</label>
                            <select id="grade_level_id" name="grade_level_id">
                                <option value="">Select Grade Level</option>
                                <?php foreach ($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="school_year_id" class="required">School Year</label>
                            <select id="school_year_id" name="school_year_id" required>
                                <option value="">Select School Year</option>
                                <?php foreach ($school_years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $active_school_year_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['year_label']); ?>
                                        <?php echo ($year['is_active']) ? ' (Current)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Student will be enrolled in this school year</small>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name" class="required">First Name</label>
                            <input type="text" id="first_name" name="first_name" required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="required">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="suffix">Suffix</label>
                            <input type="text" id="suffix" name="suffix" maxlength="10" placeholder="Jr., Sr., III">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="gender" class="required">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth" class="required">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="place_of_birth" class="required">Place of Birth</label>
                            <input type="text" id="place_of_birth" name="place_of_birth" required maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="religion">Religion</label>
                            <input type="text" id="religion" name="religion" maxlength="50">
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Address Information</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="present_address" class="required">Present Address</label>
                        <textarea id="present_address" name="present_address" required rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="permanent_address" class="required">Permanent Address</label>
                        <textarea id="permanent_address" name="permanent_address" required rows="3"></textarea>
                        <div class="form-check">
                            <input type="checkbox" id="same_address" onchange="copyAddress()">
                            <label for="same_address">Same as present address</label>
                        </div>
                    </div>
                </div>

                <!-- Father Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-male"></i>
                        <h3>Father's Information</h3>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="father_first_name">First Name</label>
                            <input type="text" id="father_first_name" name="father_first_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_middle_name">Middle Name</label>
                            <input type="text" id="father_middle_name" name="father_middle_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_last_name">Last Name</label>
                            <input type="text" id="father_last_name" name="father_last_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_birth_date">Date of Birth</label>
                            <input type="date" id="father_birth_date" name="father_birth_date">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="father_occupation">Occupation</label>
                            <input type="text" id="father_occupation" name="father_occupation" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_religion">Religion</label>
                            <input type="text" id="father_religion" name="father_religion" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_contact">Contact Number</label>
                            <input type="tel" id="father_contact" name="father_contact" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="father_email">Email Address</label>
                            <input type="email" id="father_email" name="father_email" maxlength="100">
                        </div>
                    </div>
                </div>

                <!-- Mother Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-female"></i>
                        <h3>Mother's Information</h3>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="mother_first_name">First Name</label>
                            <input type="text" id="mother_first_name" name="mother_first_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_middle_name">Middle Name</label>
                            <input type="text" id="mother_middle_name" name="mother_middle_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_last_name">Last Name</label>
                            <input type="text" id="mother_last_name" name="mother_last_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_birth_date">Date of Birth</label>
                            <input type="date" id="mother_birth_date" name="mother_birth_date">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="mother_occupation">Occupation</label>
                            <input type="text" id="mother_occupation" name="mother_occupation" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_religion">Religion</label>
                            <input type="text" id="mother_religion" name="mother_religion" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_contact">Contact Number</label>
                            <input type="tel" id="mother_contact" name="mother_contact" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="mother_email">Email Address</label>
                            <input type="email" id="mother_email" name="mother_email" maxlength="100">
                        </div>
                    </div>
                </div>

                <!-- Legal Guardian Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-users"></i>
                        <h3>Legal Guardian Information</h3>
                        <small>Fill only if different from parents</small>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="guardian_first_name">First Name</label>
                            <input type="text" id="guardian_first_name" name="guardian_first_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_middle_name">Middle Name</label>
                            <input type="text" id="guardian_middle_name" name="guardian_middle_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_last_name">Last Name</label>
                            <input type="text" id="guardian_last_name" name="guardian_last_name" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_birth_date">Date of Birth</label>
                            <input type="date" id="guardian_birth_date" name="guardian_birth_date">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="guardian_occupation">Occupation</label>
                            <input type="text" id="guardian_occupation" name="guardian_occupation" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_religion">Religion</label>
                            <input type="text" id="guardian_religion" name="guardian_religion" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_contact">Contact Number</label>
                            <input type="tel" id="guardian_contact" name="guardian_contact" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_email">Email Address</label>
                            <input type="email" id="guardian_email" name="guardian_email" maxlength="100">
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-phone"></i>
                        <h3>Emergency Contact</h3>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emergency_contact_name">Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_number">Contact Number</label>
                            <input type="tel" id="emergency_contact_number" name="emergency_contact_number" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_relationship">Relationship</label>
                            <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" maxlength="50" placeholder="Uncle, Aunt, etc.">
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-medkit"></i>
                        <h3>Medical Information</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_conditions">Medical Conditions</label>
                        <textarea id="medical_conditions" name="medical_conditions" rows="3" placeholder="Any existing medical conditions"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="2" placeholder="Food, drug, or environmental allergies"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_needs">Special Needs</label>
                        <textarea id="special_needs" name="special_needs" rows="2" placeholder="Any special accommodations needed"></textarea>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-key"></i>
                        <h3>Account Information</h3>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_email">Email Address</label>
                            <input type="email" id="student_email" name="student_email" maxlength="100">
                            <small>Leave blank to auto-generate using Student ID</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" required minlength="6">
                            <small>Minimum 6 characters</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="register_student" class="btn btn-primary">
                        <i class="fas fa-save"></i> Register Student
                    </button>
                    <a href="student_records.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> View Records
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyAddress() {
    const checkbox = document.getElementById('same_address');
    const presentAddress = document.getElementById('present_address');
    const permanentAddress = document.getElementById('permanent_address');
    
    if (checkbox.checked) {
        permanentAddress.value = presentAddress.value;
    } else {
        permanentAddress.value = '';
    }
}

// Auto-generate email if Student ID changes
document.getElementById('student_id').addEventListener('input', function() {
    const studentId = this.value.toLowerCase();
    const emailField = document.getElementById('student_email');
    
    if (emailField.value === '' || emailField.value.endsWith('@student.gtba.edu.ph')) {
        emailField.value = studentId ? studentId + '@student.gtba.edu.ph' : '';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
