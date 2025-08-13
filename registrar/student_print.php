<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();

$student_id = $_GET['id'] ?? 0;

if (!$student_id || !is_numeric($student_id)) {
    echo '<p style="text-align: center; color: red; padding: 2rem;">Invalid student ID provided.</p>';
    exit;
}

// Get comprehensive student details with all related information
$sql = "SELECT s.*, 
               gl.grade_name,
               sec.section_name,
               sy.year_label,
               u.email, u.username,
               creator.username as created_by_username,
               father.first_name as father_first_name,
               father.last_name as father_last_name,
               father.middle_name as father_middle_name,
               father.occupation as father_occupation,
               father.contact_number as father_contact,
               father.email_address as father_email,
               father.date_of_birth as father_birth_date,
               father.religion as father_religion,
               mother.first_name as mother_first_name,
               mother.last_name as mother_last_name,
               mother.middle_name as mother_middle_name,
               mother.occupation as mother_occupation,
               mother.contact_number as mother_contact,
               mother.email_address as mother_email,
               mother.date_of_birth as mother_birth_date,
               mother.religion as mother_religion,
               guardian.first_name as guardian_first_name,
               guardian.last_name as guardian_last_name,
               guardian.middle_name as guardian_middle_name,
               guardian.occupation as guardian_occupation,
               guardian.contact_number as guardian_contact,
               guardian.email_address as guardian_email,
               guardian.date_of_birth as guardian_birth_date,
               guardian.religion as guardian_religion
        FROM students s 
        LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
        LEFT JOIN sections sec ON s.current_section_id = sec.id 
        LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN users creator ON s.created_by = creator.id
        LEFT JOIN student_guardians father ON s.father_id = father.id
        LEFT JOIN student_guardians mother ON s.mother_id = mother.id
        LEFT JOIN student_guardians guardian ON s.legal_guardian_id = guardian.id
        WHERE s.id = ? AND s.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<p style="text-align: center; color: red; padding: 2rem;">Student record not found.</p>';
    exit;
}

$full_name = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'] . ($student['suffix'] ? ' ' . $student['suffix'] : ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record - <?php echo htmlspecialchars($full_name); ?></title>
    <style>
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            background: white;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .school-name {
            font-size: 20pt;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }

        .school-address {
            font-size: 11pt;
            color: #666;
            margin-bottom: 10px;
        }

        .document-title {
            font-size: 16pt;
            font-weight: bold;
            color: #000;
            text-decoration: underline;
        }

        .student-photo {
            float: right;
            width: 100px;
            height: 120px;
            border: 2px solid #2c5aa0;
            background: #f8f9fa;
            margin-left: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10pt;
            color: #666;
        }

        .section {
            margin-bottom: 25px;
            clear: both;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #2c5aa0;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
            vertical-align: top;
        }

        .info-value {
            display: inline-block;
            border-bottom: 1px solid #ccc;
            min-width: 200px;
            padding-bottom: 2px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .address-field {
            margin-bottom: 10px;
        }

        .address-field .info-value {
            min-width: 400px;
            word-wrap: break-word;
        }

        .guardian-section {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .guardian-title {
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 10px;
            font-size: 12pt;
        }

        .footer {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 50px;
        }

        .signature-label {
            font-size: 10pt;
            font-weight: bold;
        }

        .print-info {
            text-align: right;
            font-size: 9pt;
            color: #666;
            margin-top: 20px;
        }

        @media screen {
            .no-print {
                position: fixed;
                top: 10px;
                right: 10px;
                z-index: 1000;
            }

            .print-btn {
                background: #2c5aa0;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin-right: 10px;
            }

            .print-btn:hover {
                background: #1e3d6f;
            }

            .close-btn {
                background: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }

            .close-btn:hover {
                background: #545b62;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">🖨️ Print</button>
        <button onclick="window.close()" class="close-btn">✕ Close</button>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="school-logo">
            <img src="../assets/images/school-logo.png" alt="GTBA Logo" style="width: 80px; height: 80px; object-fit: contain;">
        </div>
            <div class="school-name">GOLDEN TREASURE BAPTIST ACADEMY</div>
            <div class="school-address">2909 E. Rodriguez St, Malibay, Pasay City | 854-2913 </div>
            <div class="document-title">STUDENT RECORD</div>
        </div>

        

        <!-- Basic Information -->
        <div class="section">
            <div class="section-title">STUDENT INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Student ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_id']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">LRN:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['lrn']); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">First Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['first_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Middle Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['middle_name'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Suffix:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['suffix'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($student['date_of_birth'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Place of Birth:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['place_of_birth']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Religion:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['religion'] ?: 'Not specified'); ?></span>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="section">
            <div class="section-title">ACADEMIC INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Student Type:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_type']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Enrollment Status:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['enrollment_status']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Grade:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['grade_name'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Section:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['section_name'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">School Year:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_label'] ?: 'Not assigned'); ?></span>
                </div>
            </div>
        </div>

        <!-- Address Information -->
        <div class="section">
            <div class="section-title">ADDRESS INFORMATION</div>
            <div class="address-field">
                <span class="info-label">Present Address:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['present_address']); ?></span>
            </div>
            <div class="address-field">
                <span class="info-label">Permanent Address:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['permanent_address']); ?></span>
            </div>
        </div>

        <!-- Emergency Contact -->
        <?php if ($student['emergency_contact_name']): ?>
        <div class="section">
            <div class="section-title">EMERGENCY CONTACT</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Contact Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['emergency_contact_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['emergency_contact_number'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">Relationship:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['emergency_contact_relationship'] ?: 'Not specified'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guardian Information -->
        <div class="section">
            <div class="section-title">GUARDIAN INFORMATION</div>
            
            <?php if ($student['father_first_name']): ?>
            <div class="guardian-section">
                <div class="guardian-title">FATHER'S INFORMATION</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars(trim($student['father_first_name'] . ' ' . ($student['father_middle_name'] ? $student['father_middle_name'] . ' ' : '') . $student['father_last_name'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?php echo $student['father_birth_date'] ? date('F j, Y', strtotime($student['father_birth_date'])) : 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['father_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Religion:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['father_religion'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['father_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['father_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($student['mother_first_name']): ?>
            <div class="guardian-section">
                <div class="guardian-title">MOTHER'S INFORMATION</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars(trim($student['mother_first_name'] . ' ' . ($student['mother_middle_name'] ? $student['mother_middle_name'] . ' ' : '') . $student['mother_last_name'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?php echo $student['mother_birth_date'] ? date('F j, Y', strtotime($student['mother_birth_date'])) : 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['mother_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Religion:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['mother_religion'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['mother_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['mother_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($student['guardian_first_name']): ?>
            <div class="guardian-section">
                <div class="guardian-title">LEGAL GUARDIAN'S INFORMATION</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars(trim($student['guardian_first_name'] . ' ' . ($student['guardian_middle_name'] ? $student['guardian_middle_name'] . ' ' : '') . $student['guardian_last_name'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?php echo $student['guardian_birth_date'] ? date('F j, Y', strtotime($student['guardian_birth_date'])) : 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['guardian_occupation'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Religion:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['guardian_religion'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['guardian_contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['guardian_email'] ?: 'Not provided'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Medical Information -->
        <?php if ($student['medical_conditions'] || $student['allergies'] || $student['special_needs']): ?>
        <div class="section">
            <div class="section-title">MEDICAL INFORMATION</div>
            <?php if ($student['medical_conditions']): ?>
            <div class="address-field">
                <span class="info-label">Medical Conditions:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['medical_conditions']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($student['allergies']): ?>
            <div class="address-field">
                <span class="info-label">Allergies:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['allergies']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($student['special_needs']): ?>
            <div class="address-field">
                <span class="info-label">Special Needs:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['special_needs']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="section">
            <div class="section-title">RECORD INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Account Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['email'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['username']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created By:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['created_by_username'] ?: 'System'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Registration Date:</span>
                    <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($student['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($student['updated_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Status:</span>
                    <span class="info-value"><?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?></span>
                </div>
            </div>
        </div>

       

            <div class="print-info">
                <p>Printed on: <?php echo date('F j, Y g:i A'); ?></p>
                <p>Printed by: <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role_display']); ?>)</p>
                <p>Document ID: GTBA-<?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };

        // Close window after printing
        window.onafterprint = function() {
            // Uncomment the line below if you want to auto-close after printing
            // window.close();
        };
    </script>
</body>
</html>
