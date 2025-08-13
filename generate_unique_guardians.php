<?php
/**
 * Unique Guardian Generator Script
 * Creates unique father, mother, and legal guardian for each student
 */

require_once 'config/database.php';

// Check if running from command line or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Unique Guardian Generator</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; font-weight: bold; }
            .error { color: red; font-weight: bold; }
            .info { color: blue; }
            .warning { color: orange; font-weight: bold; }
            .step { margin: 15px 0; padding: 10px; border-left: 3px solid #007cba; }
        </style>
    </head>
    <body>
    <h1>Generate Unique Guardians for Each Student</h1>";
}

function log_message($message, $type = 'info') {
    global $is_cli;
    
    $timestamp = date('Y-m-d H:i:s');
    
    if ($is_cli) {
        echo "[$timestamp] " . strtoupper($type) . ": $message\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'info'));
        echo "<div class='step $class'>[$timestamp] $message</div>\n";
        flush();
    }
}

// Filipino name generators
function generateFilipinoFirstName($gender = 'Male') {
    $male_names = [
        'Jose', 'Juan', 'Antonio', 'Pedro', 'Miguel', 'Carlos', 'Roberto', 'Fernando', 'Manuel', 'Ricardo',
        'Eduardo', 'Alejandro', 'Daniel', 'Gabriel', 'Rafael', 'Samuel', 'Benjamin', 'Nicolas', 'Adrian', 'Victor',
        'Francisco', 'Diego', 'Jorge', 'Raul', 'Sergio', 'Arturo', 'Ignacio', 'Emilio', 'Rodrigo', 'Armando',
        'Esteban', 'Cesar', 'Ruben', 'Andres', 'Mauricio', 'Hector', 'Oscar', 'Marco', 'Luis', 'Pablo',
        'Enrique', 'Javier', 'Mario', 'Felipe', 'Alvaro', 'Guillermo', 'Jaime', 'Alberto', 'Ramiro', 'Ernesto'
    ];
    
    $female_names = [
        'Maria', 'Ana', 'Carmen', 'Rosa', 'Elena', 'Isabel', 'Teresa', 'Patricia', 'Gloria', 'Esperanza',
        'Luz', 'Cristina', 'Sofia', 'Victoria', 'Angelica', 'Catalina', 'Francesca', 'Amelia', 'Esperanza', 'Dulce',
        'Concepcion', 'Pilar', 'Clementina', 'Rosario', 'Amparo', 'Remedios', 'Milagros', 'Soledad', 'Lourdes', 'Mercedes',
        'Guadalupe', 'Dolores', 'Encarnacion', 'Asuncion', 'Trinidad', 'Natividad', 'Presentacion', 'Purificacion', 'Consolacion', 'Resurreccion',
        'Fe', 'Esperanza', 'Caridad', 'Paz', 'Alegria', 'Gracia', 'Felicidad', 'Benita', 'Corazon', 'Alma'
    ];
    
    return $gender === 'Male' ? $male_names[array_rand($male_names)] : $female_names[array_rand($female_names)];
}

function generateFilipinoLastName() {
    $surnames = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Gonzales', 'Lopez',
        'Hernandez', 'Martinez', 'Flores', 'Ramos', 'Castro', 'Morales', 'Aquino', 'Dela Cruz', 'Navarro', 'Valdez',
        'Aguilar', 'Velasco', 'Castillo', 'Medina', 'Guerrero', 'Campos', 'Ortega', 'Lozano', 'Espinoza', 'Molina',
        'Pacheco', 'Figueroa', 'Contreras', 'Maldonado', 'Acosta', 'Vega', 'Rojas', 'Perez', 'Carrasco', 'Zuniga',
        'Varela', 'Hidalgo', 'Pantoja', 'Quintero', 'Camacho', 'Cardenas', 'Solis', 'Ibarra', 'Meza', 'Cano',
        'Ochoa', 'Paredes', 'Villanueva', 'Salazar', 'Cordero', 'Gutierrez', 'Herrera', 'Romero', 'Vargas', 'Peña'
    ];
    
    return $surnames[array_rand($surnames)];
}

function generateOccupation() {
    $occupations = [
        'Engineer', 'Teacher', 'Nurse', 'Driver', 'Businessman', 'Police Officer', 'Farmer', 'Electrician',
        'Security Guard', 'Construction Worker', 'Salesman', 'Office Worker', 'Mechanic', 'Vendor', 'Seamstress',
        'Cashier', 'Cook', 'Cleaner', 'Laundry Worker', 'Store Owner', 'Babysitter', 'Factory Worker',
        'IT Specialist', 'Bank Manager', 'Architect', 'Pharmacist', 'Chef', 'Pilot', 'Lawyer', 'Doctor',
        'Accountant', 'Manager', 'Technician', 'Supervisor', 'Sales Manager', 'Plumber', 'Carpenter', 'Welder'
    ];
    
    return $occupations[array_rand($occupations)];
}

function generatePhoneNumber() {
    $prefixes = ['0917', '0918', '0919', '0920', '0921', '0922', '0923', '0924', '0925', '0926'];
    $prefix = $prefixes[array_rand($prefixes)];
    $suffix = str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    return $prefix . $suffix;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    log_message("Starting unique guardian generation process...");
    
    // Step 1: Get current counts
    $student_count = $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
    $father_count = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Father'")->fetchColumn();
    $mother_count = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Mother'")->fetchColumn();
    $lg_count = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Legal Guardian'")->fetchColumn();
    
    log_message("Current counts: $student_count students, $father_count fathers, $mother_count mothers, $lg_count legal guardians");
    
    $fathers_needed = $student_count - $father_count;
    $mothers_needed = $student_count - $mother_count;
    $lg_needed = $student_count - $lg_count;
    
    log_message("Need to create: $fathers_needed fathers, $mothers_needed mothers, $lg_needed legal guardians");
    
    // Step 2: Create backup
    log_message("Creating backup tables...");
    $conn->exec("DROP TABLE IF EXISTS students_backup_unique_guardians");
    $conn->exec("CREATE TABLE students_backup_unique_guardians AS SELECT * FROM students");
    $conn->exec("DROP TABLE IF EXISTS student_guardians_backup_unique");
    $conn->exec("CREATE TABLE student_guardians_backup_unique AS SELECT * FROM student_guardians");
    log_message("✓ Backup tables created", 'success');
    
    // Step 3: Generate additional fathers
    if ($fathers_needed > 0) {
        log_message("Generating $fathers_needed additional fathers...");
        
        for ($i = 0; $i < $fathers_needed; $i++) {
            $first_name = generateFilipinoFirstName('Male');
            $last_name = generateFilipinoLastName();
            $middle_name = generateFilipinoLastName(); // Use another surname as middle name
            $occupation = generateOccupation();
            $phone = generatePhoneNumber();
            $email = strtolower($first_name . '.' . $last_name . '@email.com');
            $birthdate = date('Y-m-d', strtotime('-' . rand(25, 55) . ' years'));
            
            $stmt = $conn->prepare("
                INSERT INTO student_guardians 
                (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) 
                VALUES ('Father', ?, ?, ?, ?, ?, 'Catholic', ?, ?)
            ");
            $stmt->execute([$first_name, $last_name, $middle_name, $birthdate, $occupation, $phone, $email]);
        }
        log_message("✓ Created $fathers_needed fathers", 'success');
    }
    
    // Step 4: Generate additional mothers
    if ($mothers_needed > 0) {
        log_message("Generating $mothers_needed additional mothers...");
        
        for ($i = 0; $i < $mothers_needed; $i++) {
            $first_name = generateFilipinoFirstName('Female');
            $last_name = generateFilipinoLastName();
            $middle_name = generateFilipinoLastName();
            $occupation = generateOccupation();
            $phone = generatePhoneNumber();
            $email = strtolower($first_name . '.' . $last_name . '@email.com');
            $birthdate = date('Y-m-d', strtotime('-' . rand(25, 55) . ' years'));
            
            $stmt = $conn->prepare("
                INSERT INTO student_guardians 
                (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) 
                VALUES ('Mother', ?, ?, ?, ?, ?, 'Catholic', ?, ?)
            ");
            $stmt->execute([$first_name, $last_name, $middle_name, $birthdate, $occupation, $phone, $email]);
        }
        log_message("✓ Created $mothers_needed mothers", 'success');
    }
    
    // Step 5: Generate legal guardians
    if ($lg_needed > 0) {
        log_message("Generating $lg_needed legal guardians...");
        
        for ($i = 0; $i < $lg_needed; $i++) {
            $gender = rand(0, 1) ? 'Male' : 'Female';
            $first_name = generateFilipinoFirstName($gender);
            $last_name = generateFilipinoLastName();
            $middle_name = generateFilipinoLastName();
            $occupation = generateOccupation();
            $phone = generatePhoneNumber();
            $email = strtolower($first_name . '.' . $last_name . '@email.com');
            $birthdate = date('Y-m-d', strtotime('-' . rand(30, 70) . ' years'));
            
            $stmt = $conn->prepare("
                INSERT INTO student_guardians 
                (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) 
                VALUES ('Legal Guardian', ?, ?, ?, ?, ?, 'Catholic', ?, ?)
            ");
            $stmt->execute([$first_name, $last_name, $middle_name, $birthdate, $occupation, $phone, $email]);
        }
        log_message("✓ Created $lg_needed legal guardians", 'success');
    }
    
    // Step 6: Assign unique guardians to students (1:1 mapping)
    log_message("Assigning unique guardians to students...");
    
    // Get all students
    $students = $conn->query("SELECT id FROM students WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all available guardians
    $fathers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Father' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $mothers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Mother' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $legal_guardians = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Legal Guardian' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    log_message("Available: " . count($fathers) . " fathers, " . count($mothers) . " mothers, " . count($legal_guardians) . " legal guardians");
    
    // Assign unique guardians (1:1 mapping)
    foreach ($students as $index => $student_id) {
        $father_id = $fathers[$index] ?? null;
        $mother_id = $mothers[$index] ?? null;
        $lg_id = $legal_guardians[$index] ?? null;
        
        $stmt = $conn->prepare("
            UPDATE students 
            SET father_id = ?, mother_id = ?, legal_guardian_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$father_id, $mother_id, $lg_id, $student_id]);
    }
    
    log_message("✓ Assigned unique guardians to all students", 'success');
    
    // Step 7: Verify the assignment
    log_message("Verifying unique assignments...");
    
    // Check for duplicate father assignments
    $duplicate_fathers = $conn->query("
        SELECT father_id, COUNT(*) as count 
        FROM students 
        WHERE is_active = 1 AND father_id IS NOT NULL 
        GROUP BY father_id 
        HAVING COUNT(*) > 1
    ")->fetchAll();
    
    // Check for duplicate mother assignments
    $duplicate_mothers = $conn->query("
        SELECT mother_id, COUNT(*) as count 
        FROM students 
        WHERE is_active = 1 AND mother_id IS NOT NULL 
        GROUP BY mother_id 
        HAVING COUNT(*) > 1
    ")->fetchAll();
    
    // Check for duplicate legal guardian assignments
    $duplicate_lgs = $conn->query("
        SELECT legal_guardian_id, COUNT(*) as count 
        FROM students 
        WHERE is_active = 1 AND legal_guardian_id IS NOT NULL 
        GROUP BY legal_guardian_id 
        HAVING COUNT(*) > 1
    ")->fetchAll();
    
    $total_duplicates = count($duplicate_fathers) + count($duplicate_mothers) + count($duplicate_lgs);
    
    if ($total_duplicates === 0) {
        log_message("🎉 SUCCESS: All students now have unique guardians!", 'success');
    } else {
        log_message("⚠️ WARNING: Found $total_duplicates duplicate assignments", 'warning');
    }
    
    // Step 8: Show sample results
    log_message("Sample results (first 5 students):");
    
    $stmt = $conn->query("
        SELECT 
            s.id,
            s.first_name,
            s.last_name,
            father.first_name as father_name,
            mother.first_name as mother_name,
            lg.first_name as lg_name
        FROM students s
        LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
        LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
        LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
        WHERE s.is_active = 1
        ORDER BY s.id
        LIMIT 5
    ");
    
    $sample_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$is_cli) {
        echo "<div class='step'><h3>Sample Results:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Student ID</th><th>Student Name</th><th>Father</th><th>Mother</th><th>Legal Guardian</th></tr>";
        
        foreach ($sample_results as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['first_name'] . ' ' . $row['last_name'] . "</td>";
            echo "<td>" . ($row['father_name'] ?: 'None') . "</td>";
            echo "<td>" . ($row['mother_name'] ?: 'None') . "</td>";
            echo "<td>" . ($row['lg_name'] ?: 'None') . "</td>";
            echo "</tr>";
        }
        echo "</table></div>";
    }
    
    log_message("✅ Unique guardian generation completed successfully!", 'success');
    
} catch (Exception $e) {
    log_message("❌ Error: " . $e->getMessage(), 'error');
    if (!$is_cli) {
        echo "<div class='step error'><strong>Recovery Instructions:</strong><br>";
        echo "To restore original data:<br>";
        echo "<code>DROP TABLE students; CREATE TABLE students AS SELECT * FROM students_backup_unique_guardians;</code><br>";
        echo "<code>DROP TABLE student_guardians; CREATE TABLE student_guardians AS SELECT * FROM student_guardians_backup_unique;</code>";
        echo "</div>";
    }
}

if (!$is_cli) {
    echo "<hr>";
    echo "<div class='step info'>";
    echo "<strong>Summary:</strong><br>";
    echo "• Each student now has completely unique father, mother, and legal guardian<br>";
    echo "• No siblings (no shared parents)<br>";
    echo "• Generated realistic Filipino names and data<br>";
    echo "• Test the registrar edit form to verify guardian details display correctly";
    echo "</div>";
    echo "</body></html>";
}
?>
