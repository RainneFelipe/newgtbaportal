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

$page_title = "Edit Student Record";
$base_url = "../";

$student_id = $_GET['id'] ?? 0;
$message = '';
$messageType = '';

if (!$student_id || !is_numeric($student_id)) {
    header('Location: student_records.php');
    exit();
}

// Get student details with guardian information first
$sql = "SELECT s.*, u.username, u.email,
               gl.grade_name, sec.section_name, sy.year_label,
               -- Father information with proper null handling
               f.id as father_guardian_id, f.first_name as father_first_name, f.last_name as father_last_name, 
               f.middle_name as father_middle_name, f.date_of_birth as father_dob, f.occupation as father_occupation,
               f.religion as father_religion, f.contact_number as father_contact, f.email_address as father_email,
               -- Mother information with proper null handling
               m.id as mother_guardian_id, m.first_name as mother_first_name, m.last_name as mother_last_name,
               m.middle_name as mother_middle_name, m.date_of_birth as mother_dob, m.occupation as mother_occupation,
               m.religion as mother_religion, m.contact_number as mother_contact, m.email_address as mother_email,
               -- Legal guardian information with proper null handling
               lg.id as legal_guardian_guardian_id, lg.first_name as lg_first_name, lg.last_name as lg_last_name,
               lg.middle_name as lg_middle_name, lg.date_of_birth as lg_dob, lg.occupation as lg_occupation,
               lg.religion as lg_religion, lg.contact_number as lg_contact, lg.email_address as lg_email
        FROM students s 
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
        LEFT JOIN sections sec ON s.current_section_id = sec.id 
        LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
        LEFT JOIN student_guardians f ON s.father_id = f.id AND f.guardian_type = 'Father'
        LEFT JOIN student_guardians m ON s.mother_id = m.id AND m.guardian_type = 'Mother'
        LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
        WHERE s.id = ? AND s.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: student_records.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Function to create or update guardian
        function createOrUpdateGuardian($conn, $guardianData, $guardianType, $existingId = null) {
            if (empty($guardianData['first_name']) || empty($guardianData['last_name'])) {
                return null; // Skip if essential data missing
            }
            
            if ($existingId) {
                // Update existing guardian
                $stmt = $conn->prepare("UPDATE student_guardians SET 
                    first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, 
                    occupation = ?, religion = ?, contact_number = ?, email_address = ?,
                    updated_at = NOW()
                    WHERE id = ? AND guardian_type = ?");
                $stmt->execute([
                    $guardianData['first_name'],
                    $guardianData['last_name'],
                    $guardianData['middle_name'] ?: null,
                    $guardianData['date_of_birth'] ?: null,
                    $guardianData['occupation'] ?: null,
                    $guardianData['religion'] ?: null,
                    $guardianData['contact_number'] ?: null,
                    $guardianData['email_address'] ?: null,
                    $existingId,
                    $guardianType
                ]);
                return $existingId;
            } else {
                // Create new guardian
                $stmt = $conn->prepare("INSERT INTO student_guardians 
                    (guardian_type, first_name, last_name, middle_name, date_of_birth, 
                     occupation, religion, contact_number, email_address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $guardianType,
                    $guardianData['first_name'],
                    $guardianData['last_name'],
                    $guardianData['middle_name'] ?: null,
                    $guardianData['date_of_birth'] ?: null,
                    $guardianData['occupation'] ?: null,
                    $guardianData['religion'] ?: null,
                    $guardianData['contact_number'] ?: null,
                    $guardianData['email_address'] ?: null
                ]);
                return $conn->lastInsertId();
            }
        }
        
        // Process Guardian Information
        $father_id = $student['father_id']; // Keep existing if not updated
        $mother_id = $student['mother_id'];
        $legal_guardian_id = $student['legal_guardian_id'];
        
        // Father
        if (!empty($_POST['father_first_name']) || !empty($_POST['father_last_name'])) {
            $fatherData = [
                'first_name' => $_POST['father_first_name'] ?? '',
                'last_name' => $_POST['father_last_name'] ?? '',
                'middle_name' => $_POST['father_middle_name'] ?? '',
                'date_of_birth' => $_POST['father_dob'] ?? '',
                'occupation' => $_POST['father_occupation'] ?? '',
                'religion' => $_POST['father_religion'] ?? '',
                'contact_number' => $_POST['father_contact'] ?? '',
                'email_address' => $_POST['father_email'] ?? ''
            ];
            $father_id = createOrUpdateGuardian($conn, $fatherData, 'Father', $student['father_guardian_id']);
        }
        
        // Mother
        if (!empty($_POST['mother_first_name']) || !empty($_POST['mother_last_name'])) {
            $motherData = [
                'first_name' => $_POST['mother_first_name'] ?? '',
                'last_name' => $_POST['mother_last_name'] ?? '',
                'middle_name' => $_POST['mother_middle_name'] ?? '',
                'date_of_birth' => $_POST['mother_dob'] ?? '',
                'occupation' => $_POST['mother_occupation'] ?? '',
                'religion' => $_POST['mother_religion'] ?? '',
                'contact_number' => $_POST['mother_contact'] ?? '',
                'email_address' => $_POST['mother_email'] ?? ''
            ];
            $mother_id = createOrUpdateGuardian($conn, $motherData, 'Mother', $student['mother_guardian_id']);
        }
        
        // Legal Guardian
        if (!empty($_POST['lg_first_name']) || !empty($_POST['lg_last_name'])) {
            $lgData = [
                'first_name' => $_POST['lg_first_name'] ?? '',
                'last_name' => $_POST['lg_last_name'] ?? '',
                'middle_name' => $_POST['lg_middle_name'] ?? '',
                'date_of_birth' => $_POST['lg_dob'] ?? '',
                'occupation' => $_POST['lg_occupation'] ?? '',
                'religion' => $_POST['lg_religion'] ?? '',
                'contact_number' => $_POST['lg_contact'] ?? '',
                'email_address' => $_POST['lg_email'] ?? ''
            ];
            $legal_guardian_id = createOrUpdateGuardian($conn, $lgData, 'Legal Guardian', $student['legal_guardian_guardian_id']);
        }
        
        // Update user account information
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = (SELECT user_id FROM students WHERE id = ?)");
        $stmt->execute([
            $_POST['username'],
            $_POST['email'],
            $student_id
        ]);
        
        // Update student information including guardian IDs
        $stmt = $conn->prepare("UPDATE students SET 
            student_id = ?, lrn = ?, student_type = ?, enrollment_status = ?,
            first_name = ?, last_name = ?, middle_name = ?, suffix = ?,
            gender = ?, date_of_birth = ?, place_of_birth = ?, religion = ?,
            present_address = ?, permanent_address = ?,
            emergency_contact_name = ?, emergency_contact_number = ?, emergency_contact_relationship = ?,
            current_grade_level_id = ?, current_section_id = ?, current_school_year_id = ?,
            medical_conditions = ?, allergies = ?, special_needs = ?,
            father_id = ?, mother_id = ?, legal_guardian_id = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['student_id'],
            $_POST['lrn'],
            $_POST['student_type'],
            $_POST['enrollment_status'],
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
            $_POST['emergency_contact_name'] ?: null,
            $_POST['emergency_contact_number'] ?: null,
            $_POST['emergency_contact_relationship'] ?: null,
            $_POST['current_grade_level_id'] ?: null,
            $_POST['current_section_id'] ?: null,
            $_POST['current_school_year_id'] ?: null,
            $_POST['medical_conditions'] ?: null,
            $_POST['allergies'] ?: null,
            $_POST['special_needs'] ?: null,
            $father_id,
            $mother_id,
            $legal_guardian_id,
            $student_id
        ]);
        
        $conn->commit();
        
        // Log the action
        $user->logAudit($_SESSION['user_id'], 'Student Updated: ' . $_POST['first_name'] . ' ' . $_POST['last_name'], 'students', $student_id);
        
        $message = 'Student record updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Error updating student record: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get grade levels for dropdown
$grade_stmt = $conn->prepare("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order");
$grade_stmt->execute();
$grade_levels = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sections for dropdown
$section_stmt = $conn->prepare("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");
$section_stmt->execute();
$sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school years for dropdown
$school_year_stmt = $conn->prepare("SELECT * FROM school_years ORDER BY is_active DESC, year_label DESC");
$school_year_stmt->execute();
$school_years = $school_year_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="main-wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-edit"></i> Edit Student Record</h1>
                <p>Update student information and academic details</p>
            </div>
            <div class="header-actions">
                <a href="student_records.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Records
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="edit-form">
            <!-- Account Information -->
            <div class="form-section">
                <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username" class="required">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($student['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="required">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Student Information -->
            <div class="form-section">
                <h3><i class="fas fa-id-card"></i> Student Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id" class="required">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="lrn" class="required">LRN</label>
                        <input type="text" id="lrn" name="lrn" value="<?php echo htmlspecialchars($student['lrn'] ?? ''); ?>" required maxlength="12">
                    </div>
                    <div class="form-group">
                        <label for="student_type" class="required">Student Type</label>
                        <select id="student_type" name="student_type" required>
                            <option value="New" <?php echo $student['student_type'] === 'New' ? 'selected' : ''; ?>>New</option>
                            <option value="Transfer" <?php echo $student['student_type'] === 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                            <option value="Continuing" <?php echo $student['student_type'] === 'Continuing' ? 'selected' : ''; ?>>Continuing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="enrollment_status" class="required">Enrollment Status</label>
                        <select id="enrollment_status" name="enrollment_status" required>
                            <option value="Enrolled" <?php echo $student['enrollment_status'] === 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Dropped" <?php echo $student['enrollment_status'] === 'Dropped' ? 'selected' : ''; ?>>Dropped</option>
                            <option value="Graduated" <?php echo $student['enrollment_status'] === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                            <option value="Transferred" <?php echo $student['enrollment_status'] === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" class="required">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="required">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" value="<?php echo htmlspecialchars($student['suffix'] ?? ''); ?>" placeholder="Jr., Sr., III, etc.">
                    </div>
                    <div class="form-group">
                        <label for="gender" class="required">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="Male" <?php echo $student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth" class="required">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="place_of_birth" class="required">Place of Birth</label>
                        <input type="text" id="place_of_birth" name="place_of_birth" value="<?php echo htmlspecialchars($student['place_of_birth'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="religion">Religion</label>
                        <input type="text" id="religion" name="religion" value="<?php echo htmlspecialchars($student['religion'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Father Information -->
            <div class="form-section">
                <h3><i class="fas fa-male"></i> Father Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="father_first_name">First Name</label>
                        <input type="text" id="father_first_name" name="father_first_name" value="<?php echo htmlspecialchars($student['father_first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_last_name">Last Name</label>
                        <input type="text" id="father_last_name" name="father_last_name" value="<?php echo htmlspecialchars($student['father_last_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_middle_name">Middle Name</label>
                        <input type="text" id="father_middle_name" name="father_middle_name" value="<?php echo htmlspecialchars($student['father_middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_dob">Date of Birth</label>
                        <input type="date" id="father_dob" name="father_dob" value="<?php echo $student['father_dob'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_occupation">Occupation</label>
                        <input type="text" id="father_occupation" name="father_occupation" value="<?php echo htmlspecialchars($student['father_occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_religion">Religion</label>
                        <input type="text" id="father_religion" name="father_religion" value="<?php echo htmlspecialchars($student['father_religion'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_contact">Contact Number</label>
                        <input type="text" id="father_contact" name="father_contact" value="<?php echo htmlspecialchars($student['father_contact'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_email">Email Address</label>
                        <input type="email" id="father_email" name="father_email" value="<?php echo htmlspecialchars($student['father_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Mother Information -->
            <div class="form-section">
                <h3><i class="fas fa-female"></i> Mother Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="mother_first_name">First Name</label>
                        <input type="text" id="mother_first_name" name="mother_first_name" value="<?php echo htmlspecialchars($student['mother_first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_last_name">Last Name</label>
                        <input type="text" id="mother_last_name" name="mother_last_name" value="<?php echo htmlspecialchars($student['mother_last_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_middle_name">Middle Name</label>
                        <input type="text" id="mother_middle_name" name="mother_middle_name" value="<?php echo htmlspecialchars($student['mother_middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_dob">Date of Birth</label>
                        <input type="date" id="mother_dob" name="mother_dob" value="<?php echo $student['mother_dob'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_occupation">Occupation</label>
                        <input type="text" id="mother_occupation" name="mother_occupation" value="<?php echo htmlspecialchars($student['mother_occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_religion">Religion</label>
                        <input type="text" id="mother_religion" name="mother_religion" value="<?php echo htmlspecialchars($student['mother_religion'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_contact">Contact Number</label>
                        <input type="text" id="mother_contact" name="mother_contact" value="<?php echo htmlspecialchars($student['mother_contact'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_email">Email Address</label>
                        <input type="email" id="mother_email" name="mother_email" value="<?php echo htmlspecialchars($student['mother_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Legal Guardian Information -->
            <div class="form-section">
                <h3><i class="fas fa-user-shield"></i> Legal Guardian Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="lg_first_name">First Name</label>
                        <input type="text" id="lg_first_name" name="lg_first_name" value="<?php echo htmlspecialchars($student['lg_first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_last_name">Last Name</label>
                        <input type="text" id="lg_last_name" name="lg_last_name" value="<?php echo htmlspecialchars($student['lg_last_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_middle_name">Middle Name</label>
                        <input type="text" id="lg_middle_name" name="lg_middle_name" value="<?php echo htmlspecialchars($student['lg_middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_dob">Date of Birth</label>
                        <input type="date" id="lg_dob" name="lg_dob" value="<?php echo $student['lg_dob'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_occupation">Occupation</label>
                        <input type="text" id="lg_occupation" name="lg_occupation" value="<?php echo htmlspecialchars($student['lg_occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_religion">Religion</label>
                        <input type="text" id="lg_religion" name="lg_religion" value="<?php echo htmlspecialchars($student['lg_religion'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_contact">Contact Number</label>
                        <input type="text" id="lg_contact" name="lg_contact" value="<?php echo htmlspecialchars($student['lg_contact'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lg_email">Email Address</label>
                        <input type="email" id="lg_email" name="lg_email" value="<?php echo htmlspecialchars($student['lg_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="form-section">
                <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="present_address" class="required">Present Address</label>
                        <textarea id="present_address" name="present_address" rows="3" required><?php echo htmlspecialchars($student['present_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="permanent_address" class="required">Permanent Address</label>
                        <textarea id="permanent_address" name="permanent_address" rows="3" required><?php echo htmlspecialchars($student['permanent_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="form-section">
                <h3><i class="fas fa-phone"></i> Emergency Contact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_number">Emergency Contact Number</label>
                        <input type="text" id="emergency_contact_number" name="emergency_contact_number" value="<?php echo htmlspecialchars($student['emergency_contact_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_relationship">Relationship</label>
                        <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($student['emergency_contact_relationship'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Academic Information -->
            <div class="form-section">
                <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="current_grade_level_id">Current Grade Level</label>
                        <select id="current_grade_level_id" name="current_grade_level_id">
                            <option value="">Select Grade Level</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $student['current_grade_level_id'] == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="current_section_id">Current Section</label>
                        <select id="current_section_id" name="current_section_id">
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo $student['current_section_id'] == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="current_school_year_id">Current School Year</label>
                        <select id="current_school_year_id" name="current_school_year_id">
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo $student['current_school_year_id'] == $year['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['year_label']); ?>
                                    <?php echo $year['is_active'] ? ' (Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Medical Information -->
            <div class="form-section">
                <h3><i class="fas fa-medkit"></i> Medical Information</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="medical_conditions">Medical Conditions</label>
                        <textarea id="medical_conditions" name="medical_conditions" rows="3" placeholder="List any medical conditions..."><?php echo htmlspecialchars($student['medical_conditions'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="3" placeholder="List any allergies..."><?php echo htmlspecialchars($student['allergies'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="special_needs">Special Needs</label>
                        <textarea id="special_needs" name="special_needs" rows="3" placeholder="List any special needs or accommodations..."><?php echo htmlspecialchars($student['special_needs'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Student Record
                </button>
                <a href="student_records.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.edit-form {
    max-width: 1200px;
    margin: 0 auto;
}

.form-section {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-left: 5px solid var(--primary-blue);
}

.form-section h3 {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
}

.form-section h3 i {
    color: var(--primary-blue);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group label.required::after {
    content: ' *';
    color: var(--danger);
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.form-group textarea {
    resize: vertical;
    font-family: inherit;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding: 2rem;
    background: var(--light-gray);
    border-radius: 15px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: var(--white);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-blue), #0F3A5F);
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--gray);
    color: var(--white);
}

.btn-secondary:hover {
    background: var(--dark-gray);
    transform: translateY(-2px);
}

.content-header {
    background: linear-gradient(135deg, var(--light-blue), var(--white));
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.header-left h1 {
    color: var(--dark-blue);
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-left p {
    color: var(--gray);
    font-size: 1.1rem;
    margin: 0;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .content-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .header-left h1 {
        font-size: 1.5rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
