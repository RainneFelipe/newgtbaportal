-- Fixed Sample Data Insert Script for GTBA Portal
-- This script properly handles foreign key constraints

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Get necessary IDs
SET @active_school_year_id = (SELECT id FROM school_years WHERE is_active = 1 LIMIT 1);
SET @current_school_year_id = (SELECT id FROM school_years WHERE is_current = 1 LIMIT 1);
SET @student_role_id = (SELECT id FROM roles WHERE name = 'student');
SET @enrollment_school_year_id = COALESCE(@active_school_year_id, @current_school_year_id, 2);

-- Create guardian records first
INSERT INTO student_guardians (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) VALUES
-- Fathers
('Father', 'Juan', 'Cruz', 'Santos', '1985-03-15', 'Engineer', 'Catholic', '09171234567', 'juan.cruz@email.com'),
('Father', 'Jose', 'Reyes', 'Garcia', '1983-07-22', 'Teacher', 'Catholic', '09182345678', 'jose.reyes@email.com'),
('Father', 'Antonio', 'Santos', 'Lopez', '1987-11-08', 'Businessman', 'Catholic', '09193456789', 'antonio.santos@email.com'),
('Father', 'Pedro', 'Gonzales', 'Martinez', '1984-05-30', 'Driver', 'Catholic', '09204567890', 'pedro.gonzales@email.com'),
('Father', 'Miguel', 'Hernandez', 'Rivera', '1986-09-12', 'Mechanic', 'Catholic', '09215678901', 'miguel.hernandez@email.com'),
('Father', 'Carlos', 'Lopez', 'Torres', '1982-01-25', 'Police Officer', 'Catholic', '09226789012', 'carlos.lopez@email.com'),
('Father', 'Roberto', 'Martinez', 'Flores', '1988-04-18', 'Farmer', 'Catholic', '09237890123', 'roberto.martinez@email.com'),
('Father', 'Fernando', 'Torres', 'Ramos', '1985-12-03', 'Electrician', 'Catholic', '09248901234', 'fernando.torres@email.com'),
('Father', 'Manuel', 'Flores', 'Morales', '1983-08-27', 'Security Guard', 'Catholic', '09259012345', 'manuel.flores@email.com'),
('Father', 'Ricardo', 'Ramos', 'Castro', '1987-06-14', 'Construction Worker', 'Catholic', '09260123456', 'ricardo.ramos@email.com'),
('Father', 'Eduardo', 'Morales', 'Mendoza', '1984-10-09', 'Salesman', 'Catholic', '09271234567', 'eduardo.morales@email.com'),
('Father', 'Alejandro', 'Castro', 'Jimenez', '1986-02-21', 'Office Worker', 'Catholic', '09282345678', 'alejandro.castro@email.com'),

-- Mothers  
('Mother', 'Maria', 'Cruz', 'dela Cruz', '1987-05-20', 'Housewife', 'Catholic', '09171234568', 'maria.cruz@email.com'),
('Mother', 'Ana', 'Reyes', 'Santos', '1985-09-15', 'Nurse', 'Catholic', '09182345679', 'ana.reyes@email.com'),
('Mother', 'Carmen', 'Santos', 'Garcia', '1989-12-10', 'Teacher', 'Catholic', '09193456790', 'carmen.santos@email.com'),
('Mother', 'Rosa', 'Gonzales', 'Lopez', '1986-03-28', 'Vendor', 'Catholic', '09204567891', 'rosa.gonzales@email.com'),
('Mother', 'Elena', 'Hernandez', 'Martinez', '1988-07-05', 'Seamstress', 'Catholic', '09215678902', 'elena.hernandez@email.com'),
('Mother', 'Isabel', 'Lopez', 'Rivera', '1984-11-17', 'Cashier', 'Catholic', '09226789013', 'isabel.lopez@email.com'),
('Mother', 'Teresa', 'Martinez', 'Torres', '1990-01-12', 'Cook', 'Catholic', '09237890124', 'teresa.martinez@email.com'),
('Mother', 'Patricia', 'Torres', 'Flores', '1987-04-25', 'Cleaner', 'Catholic', '09248901235', 'patricia.torres@email.com'),
('Mother', 'Gloria', 'Flores', 'Ramos', '1985-08-08', 'Laundry Worker', 'Catholic', '09259012346', 'gloria.flores@email.com'),
('Mother', 'Esperanza', 'Ramos', 'Morales', '1989-06-22', 'Store Owner', 'Catholic', '09260123457', 'esperanza.ramos@email.com'),
('Mother', 'Luz', 'Morales', 'Castro', '1986-10-30', 'Babysitter', 'Catholic', '09271234568', 'luz.morales@email.com'),
('Mother', 'Cristina', 'Castro', 'Mendoza', '1988-12-05', 'Factory Worker', 'Catholic', '09282345679', 'cristina.castro@email.com');

-- NURSERY STUDENTS (Grade Level ID: 1)
-- Create users first, then students

-- User 1
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025001', '2025001@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_1 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_1, '2025001', '202500000001', 'New', 'Enrolled', 'Angelo', 'Cruz', 'Santos', 'Male', '2021-03-15', 'Manila', 'Catholic', '123 Main Street, Manila', '123 Main Street, Manila', 1, 13, 'Juan Cruz', '09171234567', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_1 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_1, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 2
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025002', '2025002@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_2 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_2, '2025002', '202500000002', 'New', 'Enrolled', 'Sofia', 'Reyes', 'Garcia', 'Female', '2021-07-22', 'Quezon City', 'Catholic', '456 Oak Avenue, Quezon City', '456 Oak Avenue, Quezon City', 2, 14, 'Jose Reyes', '09182345678', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_2 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_2, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 3
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025003', '2025003@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_3 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_3, '2025003', '202500000003', 'New', 'Enrolled', 'Gabriel', 'Santos', 'Lopez', 'Male', '2021-11-08', 'Pasig City', 'Catholic', '789 Pine Street, Pasig City', '789 Pine Street, Pasig City', 3, 15, 'Antonio Santos', '09193456789', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_3 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_3, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 4
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025004', '2025004@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_4 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_4, '2025004', '202500000004', 'New', 'Enrolled', 'Isabella', 'Gonzales', 'Martinez', 'Female', '2021-05-30', 'Makati City', 'Catholic', '321 Elm Road, Makati City', '321 Elm Road, Makati City', 4, 16, 'Pedro Gonzales', '09204567890', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_4 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_4, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 5
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025005', '2025005@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_5 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_5, '2025005', '202500000005', 'New', 'Enrolled', 'Miguel', 'Hernandez', 'Rivera', 'Male', '2021-09-12', 'Taguig City', 'Catholic', '654 Birch Lane, Taguig City', '654 Birch Lane, Taguig City', 5, 17, 'Miguel Hernandez', '09215678901', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_5 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_5, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 6
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025006', '2025006@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_6 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_6, '2025006', '202500000006', 'New', 'Enrolled', 'Sophia', 'Lopez', 'Torres', 'Female', '2021-01-25', 'Paranaque City', 'Catholic', '987 Cedar Drive, Paranaque City', '987 Cedar Drive, Paranaque City', 6, 18, 'Carlos Lopez', '09226789012', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_6 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_6, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 7
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025007', '2025007@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_7 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_7, '2025007', '202500000007', 'New', 'Enrolled', 'Lorenzo', 'Martinez', 'Flores', 'Male', '2021-04-18', 'Las Pinas City', 'Catholic', '147 Maple Court, Las Pinas City', '147 Maple Court, Las Pinas City', 7, 19, 'Roberto Martinez', '09237890123', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_7 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_7, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 8
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025008', '2025008@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_8 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_8, '2025008', '202500000008', 'New', 'Enrolled', 'Camila', 'Torres', 'Ramos', 'Female', '2021-12-03', 'Muntinlupa City', 'Catholic', '258 Walnut Street, Muntinlupa City', '258 Walnut Street, Muntinlupa City', 8, 20, 'Fernando Torres', '09248901234', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_8 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_8, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 9
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025009', '2025009@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_9 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_9, '2025009', '202500000009', 'New', 'Enrolled', 'Sebastian', 'Flores', 'Morales', 'Male', '2021-08-27', 'Marikina City', 'Catholic', '369 Aspen Avenue, Marikina City', '369 Aspen Avenue, Marikina City', 9, 21, 'Manuel Flores', '09259012345', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_9 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_9, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- User 10
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025010', '2025010@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());
SET @user_id_10 = LAST_INSERT_ID();

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@user_id_10, '2025010', '202500000010', 'New', 'Enrolled', 'Valentina', 'Ramos', 'Castro', 'Female', '2021-06-14', 'Pasay City', 'Catholic', '741 Spruce Road, Pasay City', '741 Spruce Road, Pasay City', 10, 22, 'Ricardo Ramos', '09260123456', 'Father', 1, @enrollment_school_year_id, 1);
SET @student_id_10 = LAST_INSERT_ID();

INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by) VALUES
(@student_id_10, @enrollment_school_year_id, 1, '2025-08-15', 'Enrolled', 1);

-- Update section enrollment counts
UPDATE sections s 
SET current_enrollment = (
    SELECT COUNT(*) 
    FROM students st 
    WHERE st.current_section_id = s.id 
    AND st.enrollment_status = 'Enrolled'
)
WHERE s.school_year_id = @enrollment_school_year_id;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

SELECT 'Fixed sample data insertion completed!' AS message,
       'Created 10 students for Nursery grade level' AS detail1,
       'Each student has complete guardian information and enrollment records' AS detail2,
       'All students have user accounts with default password: password' AS detail3;
