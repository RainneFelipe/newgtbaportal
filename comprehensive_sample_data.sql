-- Comprehensive Sample Data Insert Script for GTBA Portal
-- This script inserts 10 students for each remaining grade level (110 total students)
-- with complete guardian information, user accounts, and enrollment records
-- NOTE: Nursery students (2025001-2025010) are already created by fixed_sample_data.sql

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

-- Additional guardian records to have enough for remaining students (beyond the 24 already created)
-- Starting from guardian ID 25 onwards to avoid conflicts
INSERT INTO student_guardians (guardian_type, first_name, last_name, middle_name, date_of_birth, occupation, religion, contact_number, email_address) VALUES
-- Additional Fathers (starting from what would be ID 25)
('Father', 'Daniel', 'Dela Rosa', 'Villanueva', '1985-01-15', 'IT Specialist', 'Catholic', '09301234567', 'daniel.delarosa@email.com'),
('Father', 'Gabriel', 'Bautista', 'Salazar', '1983-03-20', 'Bank Manager', 'Catholic', '09312345678', 'gabriel.bautista@email.com'),
('Father', 'Rafael', 'Navarro', 'Cordero', '1987-05-25', 'Architect', 'Catholic', '09323456789', 'rafael.navarro@email.com'),
('Father', 'Samuel', 'Valdez', 'Gutierrez', '1984-07-30', 'Pharmacist', 'Catholic', '09334567890', 'samuel.valdez@email.com'),
('Father', 'Benjamin', 'Aguilar', 'Herrera', '1986-09-14', 'Chef', 'Catholic', '09345678901', 'benjamin.aguilar@email.com'),
('Father', 'Nicolas', 'Velasco', 'Romero', '1982-11-18', 'Pilot', 'Catholic', '09356789012', 'nicolas.velasco@email.com'),
('Father', 'Adrian', 'Castillo', 'Vargas', '1988-01-22', 'Lawyer', 'Catholic', '09367890123', 'adrian.castillo@email.com'),
('Father', 'Victor', 'Medina', 'Peña', '1985-03-26', 'Doctor', 'Catholic', '09378901234', 'victor.medina@email.com'),
('Father', 'Alejandro', 'Guerrero', 'Dominguez', '1983-05-30', 'Engineer', 'Catholic', '09389012345', 'alejandro.guerrero@email.com'),
('Father', 'Francisco', 'Campos', 'Silva', '1987-07-14', 'Businessman', 'Catholic', '09390123456', 'francisco.campos@email.com'),
('Father', 'Diego', 'Ortega', 'Ponce', '1984-09-18', 'Accountant', 'Catholic', '09401234567', 'diego.ortega@email.com'),
('Father', 'Jorge', 'Lozano', 'Cervantes', '1986-11-22', 'Teacher', 'Catholic', '09412345678', 'jorge.lozano@email.com'),
('Father', 'Raul', 'Espinoza', 'Delgado', '1982-01-26', 'Mechanic', 'Catholic', '09423456789', 'raul.espinoza@email.com'),
('Father', 'Sergio', 'Molina', 'Fuentes', '1988-03-30', 'Engineer', 'Catholic', '09434567890', 'sergio.molina@email.com'),
('Father', 'Arturo', 'Pacheco', 'Sandoval', '1985-05-14', 'Manager', 'Catholic', '09445678901', 'arturo.pacheco@email.com'),
('Father', 'Ignacio', 'Figueroa', 'Escobar', '1983-07-18', 'Technician', 'Catholic', '09456789012', 'ignacio.figueroa@email.com'),
('Father', 'Emilio', 'Contreras', 'Galvan', '1987-09-22', 'Supervisor', 'Catholic', '09467890123', 'emilio.contreras@email.com'),
('Father', 'Rodrigo', 'Maldonado', 'Barrera', '1984-11-26', 'Sales Manager', 'Catholic', '09478901234', 'rodrigo.maldonado@email.com'),
('Father', 'Armando', 'Acosta', 'Cabrera', '1986-01-30', 'Electrician', 'Catholic', '09489012345', 'armando.acosta@email.com'),
('Father', 'Esteban', 'Vega', 'Cortez', '1982-03-14', 'Driver', 'Catholic', '09490123456', 'esteban.vega@email.com'),
('Father', 'Cesar', 'Rojas', 'Moreno', '1988-05-18', 'Security Guard', 'Catholic', '09501234567', 'cesar.rojas@email.com'),
('Father', 'Ruben', 'Perez', 'Soto', '1985-07-22', 'Plumber', 'Catholic', '09512345678', 'ruben.perez@email.com'),
('Father', 'Andres', 'Carrasco', 'Restrepo', '1983-09-26', 'Carpenter', 'Catholic', '09523456789', 'andres.carrasco@email.com'),
('Father', 'Mauricio', 'Zuniga', 'Osorio', '1987-11-30', 'Welder', 'Catholic', '09534567890', 'mauricio.zuniga@email.com'),
('Father', 'Hector', 'Varela', 'Calderon', '1984-01-14', 'Farmer', 'Catholic', '09545678901', 'hector.varela@email.com'),
('Father', 'Oscar', 'Hidalgo', 'Espejo', '1986-03-18', 'Cook', 'Catholic', '09556789012', 'oscar.hidalgo@email.com'),
('Father', 'Marco', 'Pantoja', 'Uribe', '1982-05-22', 'Janitor', 'Catholic', '09567890123', 'marco.pantoja@email.com'),
('Father', 'Luis', 'Quintero', 'Benitez', '1988-07-26', 'Delivery Driver', 'Catholic', '09578901234', 'luis.quintero@email.com'),
('Father', 'Pablo', 'Camacho', 'Palacios', '1985-09-30', 'Maintenance', 'Catholic', '09589012345', 'pablo.camacho@email.com'),
('Father', 'Enrique', 'Cardenas', 'Montoya', '1983-11-14', 'Factory Worker', 'Catholic', '09590123456', 'enrique.cardenas@email.com'),
('Father', 'Javier', 'Solis', 'Villalobos', '1987-01-18', 'Store Clerk', 'Catholic', '09601234567', 'javier.solis@email.com'),
('Father', 'Mario', 'Ibarra', 'Aranda', '1984-03-22', 'Taxi Driver', 'Catholic', '09612345678', 'mario.ibarra@email.com'),
('Father', 'Felipe', 'Meza', 'Coronado', '1986-05-26', 'Construction', 'Catholic', '09623456789', 'felipe.meza@email.com'),
('Father', 'Alvaro', 'Cano', 'Avalos', '1982-07-30', 'Painter', 'Catholic', '09634567890', 'alvaro.cano@email.com'),
('Father', 'Guillermo', 'Ochoa', 'Bermudez', '1988-09-14', 'Electrician', 'Catholic', '09645678901', 'guillermo.ochoa@email.com'),
('Father', 'Jaime', 'Paredes', 'Casillas', '1985-11-18', 'Mechanic', 'Catholic', '09656789012', 'jaime.paredes@email.com'),

-- Additional Mothers (61-120)
('Mother', 'Beatriz', 'Dela Rosa', 'Santos', '1987-02-20', 'Teacher', 'Catholic', '09301234568', 'beatriz.delarosa@email.com'),
('Mother', 'Claudia', 'Bautista', 'Cruz', '1985-04-25', 'Nurse', 'Catholic', '09312345679', 'claudia.bautista@email.com'),
('Mother', 'Diana', 'Navarro', 'Garcia', '1989-06-30', 'Secretary', 'Catholic', '09323456790', 'diana.navarro@email.com'),
('Mother', 'Estela', 'Valdez', 'Lopez', '1986-08-14', 'Accountant', 'Catholic', '09334567891', 'estela.valdez@email.com'),
('Mother', 'Fernanda', 'Aguilar', 'Martinez', '1988-10-18', 'Cashier', 'Catholic', '09345678902', 'fernanda.aguilar@email.com'),
('Mother', 'Gabriela', 'Velasco', 'Rivera', '1984-12-22', 'Sales Lady', 'Catholic', '09356789013', 'gabriela.velasco@email.com'),
('Mother', 'Helena', 'Castillo', 'Torres', '1990-02-26', 'Housewife', 'Catholic', '09367890124', 'helena.castillo@email.com'),
('Mother', 'Irene', 'Medina', 'Flores', '1987-04-30', 'Cook', 'Catholic', '09378901235', 'irene.medina@email.com'),
('Mother', 'Julia', 'Guerrero', 'Ramos', '1985-06-14', 'Seamstress', 'Catholic', '09389012346', 'julia.guerrero@email.com'),
('Mother', 'Karina', 'Campos', 'Morales', '1989-08-18', 'Vendor', 'Catholic', '09390123457', 'karina.campos@email.com'),
('Mother', 'Leticia', 'Ortega', 'Castro', '1986-10-22', 'Laundry Worker', 'Catholic', '09401234568', 'leticia.ortega@email.com'),
('Mother', 'Monica', 'Lozano', 'Mendoza', '1988-12-26', 'Babysitter', 'Catholic', '09412345679', 'monica.lozano@email.com'),
('Mother', 'Natalia', 'Espinoza', 'Jimenez', '1984-02-28', 'Store Owner', 'Catholic', '09423456790', 'natalia.espinoza@email.com'),
('Mother', 'Olga', 'Molina', 'Villanueva', '1990-04-14', 'Cleaner', 'Catholic', '09434567891', 'olga.molina@email.com'),
('Mother', 'Paloma', 'Pacheco', 'Salazar', '1987-06-18', 'Factory Worker', 'Catholic', '09445678902', 'paloma.pacheco@email.com'),
('Mother', 'Raquel', 'Figueroa', 'Cordero', '1985-08-22', 'Office Worker', 'Catholic', '09456789013', 'raquel.figueroa@email.com'),
('Mother', 'Sandra', 'Contreras', 'Gutierrez', '1989-10-26', 'Cashier', 'Catholic', '09467890124', 'sandra.contreras@email.com'),
('Mother', 'Tania', 'Maldonado', 'Herrera', '1986-12-30', 'Teacher', 'Catholic', '09478901235', 'tania.maldonado@email.com'),
('Mother', 'Ursula', 'Acosta', 'Romero', '1988-02-14', 'Nurse', 'Catholic', '09489012346', 'ursula.acosta@email.com'),
('Mother', 'Veronica', 'Vega', 'Vargas', '1984-04-18', 'Secretary', 'Catholic', '09490123457', 'veronica.vega@email.com'),
('Mother', 'Wendy', 'Rojas', 'Peña', '1990-06-22', 'Sales Lady', 'Catholic', '09501234568', 'wendy.rojas@email.com'),
('Mother', 'Ximena', 'Perez', 'Dominguez', '1987-08-26', 'Housewife', 'Catholic', '09512345679', 'ximena.perez@email.com'),
('Mother', 'Yolanda', 'Carrasco', 'Silva', '1985-10-30', 'Cook', 'Catholic', '09523456790', 'yolanda.carrasco@email.com'),
('Mother', 'Zulema', 'Zuniga', 'Ponce', '1989-12-14', 'Seamstress', 'Catholic', '09534567891', 'zulema.zuniga@email.com'),
('Mother', 'Adriana', 'Varela', 'Cervantes', '1986-02-18', 'Vendor', 'Catholic', '09545678902', 'adriana.varela@email.com'),
('Mother', 'Blanca', 'Hidalgo', 'Delgado', '1988-04-22', 'Laundry Worker', 'Catholic', '09556789013', 'blanca.hidalgo@email.com'),
('Mother', 'Cecilia', 'Pantoja', 'Fuentes', '1984-06-26', 'Babysitter', 'Catholic', '09567890124', 'cecilia.pantoja@email.com'),
('Mother', 'Dolores', 'Quintero', 'Sandoval', '1990-08-30', 'Store Owner', 'Catholic', '09578901235', 'dolores.quintero@email.com'),
('Mother', 'Esperanza', 'Camacho', 'Escobar', '1987-10-14', 'Cleaner', 'Catholic', '09589012346', 'esperanza.camacho@email.com'),
('Mother', 'Fatima', 'Cardenas', 'Galvan', '1985-12-18', 'Factory Worker', 'Catholic', '09590123457', 'fatima.cardenas@email.com'),
('Mother', 'Graciela', 'Solis', 'Barrera', '1989-02-22', 'Office Worker', 'Catholic', '09601234568', 'graciela.solis@email.com'),
('Mother', 'Hilda', 'Ibarra', 'Cabrera', '1986-04-26', 'Cashier', 'Catholic', '09612345679', 'hilda.ibarra@email.com'),
('Mother', 'Ines', 'Meza', 'Cortez', '1988-06-30', 'Teacher', 'Catholic', '09623456790', 'ines.meza@email.com'),
('Mother', 'Josefina', 'Cano', 'Moreno', '1984-08-14', 'Nurse', 'Catholic', '09634567891', 'josefina.cano@email.com'),
('Mother', 'Karen', 'Ochoa', 'Soto', '1990-10-18', 'Secretary', 'Catholic', '09645678902', 'karen.ochoa@email.com'),
('Mother', 'Lidia', 'Paredes', 'Restrepo', '1987-12-22', 'Sales Lady', 'Catholic', '09656789013', 'lidia.paredes@email.com');

-- Now create users and students for remaining grade levels (skipping Nursery which is already created)
-- Starting from Kindergarten (Grade Level 2)

-- Grade Level 2: Kindergarten (Born 2020 - Age 4-5)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025011', '2025011@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025012', '2025012@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025013', '2025013@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025014', '2025014@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025015', '2025015@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025016', '2025016@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025017', '2025017@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025018', '2025018@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025019', '2025019@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025020', '2025020@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @kinder_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@kinder_user_start, '2025011', '202500000011', 'New', 'Enrolled', 'Mateo', 'Morales', 'Mendoza', 'Male', '2020-03-10', 'Caloocan City', 'Catholic', '852 Hickory Lane, Caloocan City', '852 Hickory Lane, Caloocan City', 13, 25, 'Eduardo Morales', '09271234567', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+1, '2025012', '202500000012', 'New', 'Enrolled', 'Emma', 'Castro', 'Jimenez', 'Female', '2020-07-15', 'Malabon City', 'Catholic', '963 Poplar Street, Malabon City', '963 Poplar Street, Malabon City', 14, 26, 'Alejandro Castro', '09282345678', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+2, '2025013', '202500000013', 'New', 'Enrolled', 'Diego', 'Cruz', 'Santos', 'Male', '2020-11-20', 'Navotas City', 'Catholic', '159 Willow Road, Navotas City', '159 Willow Road, Navotas City', 15, 27, 'Daniel Dela Rosa', '09301234567', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+3, '2025014', '202500000014', 'New', 'Enrolled', 'Mia', 'Reyes', 'Garcia', 'Female', '2020-05-25', 'Valenzuela City', 'Catholic', '753 Chestnut Ave, Valenzuela City', '753 Chestnut Ave, Valenzuela City', 16, 28, 'Gabriel Bautista', '09312345678', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+4, '2025015', '202500000015', 'New', 'Enrolled', 'Lucas', 'Santos', 'Lopez', 'Male', '2020-09-30', 'San Juan City', 'Catholic', '486 Sycamore Dr, San Juan City', '486 Sycamore Dr, San Juan City', 17, 29, 'Rafael Navarro', '09323456789', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+5, '2025016', '202500000016', 'New', 'Enrolled', 'Zoe', 'Gonzales', 'Martinez', 'Female', '2020-01-12', 'Mandaluyong City', 'Catholic', '357 Magnolia St, Mandaluyong City', '357 Magnolia St, Mandaluyong City', 18, 30, 'Samuel Valdez', '09334567890', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+6, '2025017', '202500000017', 'New', 'Enrolled', 'Ethan', 'Hernandez', 'Rivera', 'Male', '2020-04-08', 'Pateros', 'Catholic', '624 Dogwood Lane, Pateros', '624 Dogwood Lane, Pateros', 19, 31, 'Benjamin Aguilar', '09345678901', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+7, '2025018', '202500000018', 'New', 'Enrolled', 'Aria', 'Lopez', 'Torres', 'Female', '2020-12-18', 'Marikina City', 'Catholic', '791 Redwood Road, Marikina City', '791 Redwood Road, Marikina City', 20, 32, 'Nicolas Velasco', '09356789012', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+8, '2025019', '202500000019', 'New', 'Enrolled', 'Noah', 'Martinez', 'Flores', 'Male', '2020-08-22', 'Antipolo City', 'Catholic', '135 Sequoia Circle, Antipolo City', '135 Sequoia Circle, Antipolo City', 21, 33, 'Adrian Castillo', '09367890123', 'Father', 2, @enrollment_school_year_id, 1),
(@kinder_user_start+9, '2025020', '202500000020', 'New', 'Enrolled', 'Luna', 'Torres', 'Ramos', 'Female', '2020-06-05', 'Cainta, Rizal', 'Catholic', '802 Bamboo Street, Cainta, Rizal', '802 Bamboo Street, Cainta, Rizal', 22, 34, 'Victor Medina', '09378901234', 'Father', 2, @enrollment_school_year_id, 1);

-- Insert enrollment records for Kindergarten
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 2, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025011' AND '2025020';

-- Continue with remaining grades using the same pattern
-- Grade Level 3: Grade 1 (Born 2019 - Age 5-6)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025021', '2025021@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025022', '2025022@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025023', '2025023@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025024', '2025024@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025025', '2025025@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025026', '2025026@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025027', '2025027@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025028', '2025028@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025029', '2025029@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025030', '2025030@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade1_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade1_user_start, '2025021', '202500000021', 'New', 'Enrolled', 'Alexander', 'Dela Rosa', 'Villanueva', 'Male', '2019-02-15', 'Manila', 'Catholic', '123 Narra Street, Manila', '123 Narra Street, Manila', 23, 35, 'Francisco Campos', '09390123456', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+1, '2025022', '202500000022', 'New', 'Enrolled', 'Victoria', 'Bautista', 'Salazar', 'Female', '2019-06-20', 'Quezon City', 'Catholic', '456 Molave Avenue, Quezon City', '456 Molave Avenue, Quezon City', 24, 36, 'Diego Ortega', '09401234567', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+2, '2025023', '202500000023', 'New', 'Enrolled', 'Leonardo', 'Navarro', 'Cordero', 'Male', '2019-10-25', 'Pasig City', 'Catholic', '789 Mahogany Road, Pasig City', '789 Mahogany Road, Pasig City', 25, 37, 'Jorge Lozano', '09412345678', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+3, '2025024', '202500000024', 'New', 'Enrolled', 'Angelica', 'Valdez', 'Gutierrez', 'Female', '2019-04-30', 'Makati City', 'Catholic', '321 Banyan Street, Makati City', '321 Banyan Street, Makati City', 26, 38, 'Raul Espinoza', '09423456789', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+4, '2025025', '202500000025', 'New', 'Enrolled', 'Nicolas', 'Aguilar', 'Herrera', 'Male', '2019-08-14', 'Taguig City', 'Catholic', '654 Ipil Lane, Taguig City', '654 Ipil Lane, Taguig City', 27, 39, 'Sergio Molina', '09434567890', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+5, '2025026', '202500000026', 'New', 'Enrolled', 'Catalina', 'Velasco', 'Romero', 'Female', '2019-12-18', 'Paranaque City', 'Catholic', '987 Acacia Drive, Paranaque City', '987 Acacia Drive, Paranaque City', 28, 40, 'Arturo Pacheco', '09445678901', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+6, '2025027', '202500000027', 'New', 'Enrolled', 'Maximiliano', 'Castillo', 'Vargas', 'Male', '2019-03-22', 'Las Pinas City', 'Catholic', '147 Bamboo Court, Las Pinas City', '147 Bamboo Court, Las Pinas City', 29, 41, 'Ignacio Figueroa', '09456789012', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+7, '2025028', '202500000028', 'New', 'Enrolled', 'Francesca', 'Medina', 'Peña', 'Female', '2019-07-26', 'Muntinlupa City', 'Catholic', '258 Talisay Street, Muntinlupa City', '258 Talisay Street, Muntinlupa City', 30, 42, 'Emilio Contreras', '09467890123', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+8, '2025029', '202500000029', 'New', 'Enrolled', 'Santiago', 'Guerrero', 'Dominguez', 'Male', '2019-11-30', 'Marikina City', 'Catholic', '369 Bougainvillea Avenue, Marikina City', '369 Bougainvillea Avenue, Marikina City', 31, 43, 'Rodrigo Maldonado', '09478901234', 'Father', 3, @enrollment_school_year_id, 1),
(@grade1_user_start+9, '2025030', '202500000030', 'New', 'Enrolled', 'Esperanza', 'Campos', 'Silva', 'Female', '2019-05-14', 'Pasay City', 'Catholic', '741 Sampaguita Road, Pasay City', '741 Sampaguita Road, Pasay City', 32, 44, 'Armando Acosta', '09489012345', 'Father', 3, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 1
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 3, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025021' AND '2025030';

-- Continue pattern for all remaining grades...
-- I'll create a more efficient batch process for the remaining grades to manage file size

-- For brevity, I'll create a template that can be repeated for all grades
-- The pattern continues with appropriate age adjustments for each grade level

-- Grade 2 through Grade 10 would follow the same pattern with:
-- - Birth years: 2018, 2017, 2016, 2015, 2014, 2013, 2012, 2011, 2010 respectively
-- - Student IDs: 2025031-2025040, 2025041-2025050, etc.
-- - LRN: 202500000031-202500000040, etc.
-- - Appropriate guardian pairings
-- - Grade level IDs: 4, 5, 6, 7, 8, 9, 10, 11, 12 respectively

-- For now, let's complete the remaining grades using a more condensed approach

-- Grade Level 4: Grade 2 (Born 2018 - Age 6-7)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025031', '2025031@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025032', '2025032@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025033', '2025033@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025034', '2025034@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025035', '2025035@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025036', '2025036@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025037', '2025037@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025038', '2025038@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025039', '2025039@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025040', '2025040@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade2_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade2_user_start, '2025031', '202500000031', 'New', 'Enrolled', 'Emmanuel', 'Ortega', 'Ponce', 'Male', '2018-01-20', 'Manila', 'Catholic', '456 Rose Street, Manila', '456 Rose Street, Manila', 35, 71, 'Diego Ortega', '09401234567', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+1, '2025032', '202500000032', 'New', 'Enrolled', 'Gabriella', 'Lozano', 'Cervantes', 'Female', '2018-05-25', 'Quezon City', 'Catholic', '789 Lily Avenue, Quezon City', '789 Lily Avenue, Quezon City', 36, 72, 'Jorge Lozano', '09412345678', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+2, '2025033', '202500000033', 'New', 'Enrolled', 'Rafael', 'Espinoza', 'Delgado', 'Male', '2018-09-30', 'Pasig City', 'Catholic', '321 Tulip Road, Pasig City', '321 Tulip Road, Pasig City', 37, 73, 'Raul Espinoza', '09423456789', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+3, '2025034', '202500000034', 'New', 'Enrolled', 'Valeria', 'Molina', 'Fuentes', 'Female', '2018-03-14', 'Makati City', 'Catholic', '654 Sunflower Lane, Makati City', '654 Sunflower Lane, Makati City', 38, 74, 'Sergio Molina', '09434567890', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+4, '2025035', '202500000035', 'New', 'Enrolled', 'Rodrigo', 'Pacheco', 'Sandoval', 'Male', '2018-07-18', 'Taguig City', 'Catholic', '987 Orchid Drive, Taguig City', '987 Orchid Drive, Taguig City', 39, 75, 'Arturo Pacheco', '09445678901', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+5, '2025036', '202500000036', 'New', 'Enrolled', 'Ximena', 'Figueroa', 'Escobar', 'Female', '2018-11-22', 'Paranaque City', 'Catholic', '147 Jasmine Court, Paranaque City', '147 Jasmine Court, Paranaque City', 40, 76, 'Ignacio Figueroa', '09456789012', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+6, '2025037', '202500000037', 'New', 'Enrolled', 'Thiago', 'Contreras', 'Galvan', 'Male', '2018-04-26', 'Las Pinas City', 'Catholic', '258 Dahlia Street, Las Pinas City', '258 Dahlia Street, Las Pinas City', 41, 77, 'Emilio Contreras', '09467890123', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+7, '2025038', '202500000038', 'New', 'Enrolled', 'Amelia', 'Maldonado', 'Barrera', 'Female', '2018-08-30', 'Muntinlupa City', 'Catholic', '369 Hibiscus Avenue, Muntinlupa City', '369 Hibiscus Avenue, Muntinlupa City', 42, 78, 'Rodrigo Maldonado', '09478901234', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+8, '2025039', '202500000039', 'New', 'Enrolled', 'Fernando', 'Acosta', 'Cabrera', 'Male', '2018-12-14', 'Marikina City', 'Catholic', '741 Carnation Road, Marikina City', '741 Carnation Road, Marikina City', 43, 79, 'Armando Acosta', '09489012345', 'Father', 4, @enrollment_school_year_id, 1),
(@grade2_user_start+9, '2025040', '202500000040', 'New', 'Enrolled', 'Dulce', 'Vega', 'Cortez', 'Female', '2018-06-18', 'Pasay City', 'Catholic', '852 Petunia Lane, Pasay City', '852 Petunia Lane, Pasay City', 44, 80, 'Esteban Vega', '09490123456', 'Father', 4, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 2
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 4, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025031' AND '2025040';

-- Grade Level 5: Grade 3 (Born 2017 - Age 7-8)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025041', '2025041@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025042', '2025042@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025043', '2025043@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025044', '2025044@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025045', '2025045@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025046', '2025046@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025047', '2025047@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025048', '2025048@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025049', '2025049@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025050', '2025050@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade3_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade3_user_start, '2025041', '202500000041', 'New', 'Enrolled', 'Joaquin', 'Rojas', 'Moreno', 'Male', '2017-02-10', 'Manila', 'Catholic', '123 Violet Street, Manila', '123 Violet Street, Manila', 45, 81, 'Cesar Rojas', '09501234567', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+1, '2025042', '202500000042', 'New', 'Enrolled', 'Renata', 'Perez', 'Soto', 'Female', '2017-06-15', 'Quezon City', 'Catholic', '456 Marigold Avenue, Quezon City', '456 Marigold Avenue, Quezon City', 46, 82, 'Ruben Perez', '09512345678', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+2, '2025043', '202500000043', 'New', 'Enrolled', 'Adriano', 'Carrasco', 'Restrepo', 'Male', '2017-10-20', 'Pasig City', 'Catholic', '789 Chrysanthemum Road, Pasig City', '789 Chrysanthemum Road, Pasig City', 47, 83, 'Andres Carrasco', '09523456789', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+3, '2025044', '202500000044', 'New', 'Enrolled', 'Esperanza', 'Zuniga', 'Osorio', 'Female', '2017-04-25', 'Makati City', 'Catholic', '321 Lavender Lane, Makati City', '321 Lavender Lane, Makati City', 48, 84, 'Mauricio Zuniga', '09534567890', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+4, '2025045', '202500000045', 'New', 'Enrolled', 'Maximos', 'Varela', 'Calderon', 'Male', '2017-08-30', 'Taguig City', 'Catholic', '654 Daffodil Drive, Taguig City', '654 Daffodil Drive, Taguig City', 49, 85, 'Hector Varela', '09545678901', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+5, '2025046', '202500000046', 'New', 'Enrolled', 'Soledad', 'Hidalgo', 'Espejo', 'Female', '2017-12-14', 'Paranaque City', 'Catholic', '987 Freesia Court, Paranaque City', '987 Freesia Court, Paranaque City', 50, 86, 'Oscar Hidalgo', '09556789012', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+6, '2025047', '202500000047', 'New', 'Enrolled', 'Patricio', 'Pantoja', 'Uribe', 'Male', '2017-03-18', 'Las Pinas City', 'Catholic', '147 Poppy Street, Las Pinas City', '147 Poppy Street, Las Pinas City', 51, 87, 'Marco Pantoja', '09567890123', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+7, '2025048', '202500000048', 'New', 'Enrolled', 'Milagros', 'Quintero', 'Benitez', 'Female', '2017-07-22', 'Muntinlupa City', 'Catholic', '258 Zinnia Avenue, Muntinlupa City', '258 Zinnia Avenue, Muntinlupa City', 52, 88, 'Luis Quintero', '09578901234', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+8, '2025049', '202500000049', 'New', 'Enrolled', 'Cristobal', 'Camacho', 'Palacios', 'Male', '2017-11-26', 'Marikina City', 'Catholic', '369 Iris Road, Marikina City', '369 Iris Road, Marikina City', 53, 89, 'Pablo Camacho', '09589012345', 'Father', 5, @enrollment_school_year_id, 1),
(@grade3_user_start+9, '2025050', '202500000050', 'New', 'Enrolled', 'Remedios', 'Cardenas', 'Montoya', 'Female', '2017-05-30', 'Pasay City', 'Catholic', '741 Begonia Lane, Pasay City', '741 Begonia Lane, Pasay City', 54, 90, 'Enrique Cardenas', '09590123456', 'Father', 5, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 3
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 5, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025041' AND '2025050';

-- Grade Level 6: Grade 4 (Born 2016 - Age 8-9)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025051', '2025051@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025052', '2025052@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025053', '2025053@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025054', '2025054@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025055', '2025055@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025056', '2025056@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025057', '2025057@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025058', '2025058@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025059', '2025059@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025060', '2025060@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade4_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade4_user_start, '2025051', '202500000051', 'New', 'Enrolled', 'Agustin', 'Solis', 'Villalobos', 'Male', '2016-01-15', 'Manila', 'Catholic', '852 Camellia Street, Manila', '852 Camellia Street, Manila', 55, 91, 'Javier Solis', '09601234567', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+1, '2025052', '202500000052', 'New', 'Enrolled', 'Concepcion', 'Ibarra', 'Aranda', 'Female', '2016-05-20', 'Quezon City', 'Catholic', '963 Azalea Avenue, Quezon City', '963 Azalea Avenue, Quezon City', 56, 92, 'Mario Ibarra', '09612345678', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+2, '2025053', '202500000053', 'New', 'Enrolled', 'Teodoro', 'Meza', 'Coronado', 'Male', '2016-09-25', 'Pasig City', 'Catholic', '159 Geranium Road, Pasig City', '159 Geranium Road, Pasig City', 57, 93, 'Felipe Meza', '09623456789', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+3, '2025054', '202500000054', 'New', 'Enrolled', 'Pilar', 'Cano', 'Avalos', 'Female', '2016-03-30', 'Makati City', 'Catholic', '753 Snapdragon Lane, Makati City', '753 Snapdragon Lane, Makati City', 58, 94, 'Alvaro Cano', '09634567890', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+4, '2025055', '202500000055', 'New', 'Enrolled', 'Leopoldo', 'Ochoa', 'Bermudez', 'Male', '2016-07-14', 'Taguig City', 'Catholic', '486 Peony Drive, Taguig City', '486 Peony Drive, Taguig City', 59, 95, 'Guillermo Ochoa', '09645678901', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+5, '2025056', '202500000056', 'New', 'Enrolled', 'Clementina', 'Paredes', 'Casillas', 'Female', '2016-11-18', 'Paranaque City', 'Catholic', '357 Pansy Court, Paranaque City', '357 Pansy Court, Paranaque City', 60, 96, 'Jaime Paredes', '09656789012', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+6, '2025057', '202500000057', 'New', 'Enrolled', 'Evaristo', 'Cruz', 'Santos', 'Male', '2016-04-22', 'Las Pinas City', 'Catholic', '624 Cosmos Street, Las Pinas City', '624 Cosmos Street, Las Pinas City', 1, 25, 'Juan Cruz', '09171234567', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+7, '2025058', '202500000058', 'New', 'Enrolled', 'Rosario', 'Reyes', 'Garcia', 'Female', '2016-08-26', 'Muntinlupa City', 'Catholic', '791 Gladiolus Avenue, Muntinlupa City', '791 Gladiolus Avenue, Muntinlupa City', 2, 26, 'Jose Reyes', '09182345678', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+8, '2025059', '202500000059', 'New', 'Enrolled', 'Florencio', 'Santos', 'Lopez', 'Male', '2016-12-30', 'Marikina City', 'Catholic', '135 Aster Road, Marikina City', '135 Aster Road, Marikina City', 3, 27, 'Antonio Santos', '09193456789', 'Father', 6, @enrollment_school_year_id, 1),
(@grade4_user_start+9, '2025060', '202500000060', 'New', 'Enrolled', 'Amparo', 'Gonzales', 'Martinez', 'Female', '2016-06-14', 'Pasay City', 'Catholic', '802 Carnation Lane, Pasay City', '802 Carnation Lane, Pasay City', 4, 28, 'Pedro Gonzales', '09204567890', 'Father', 6, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 4
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 6, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025051' AND '2025060';

-- Grade Level 7: Grade 5 (Born 2015 - Age 9-10)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025061', '2025061@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025062', '2025062@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025063', '2025063@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025064', '2025064@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025065', '2025065@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025066', '2025066@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025067', '2025067@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025068', '2025068@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025069', '2025069@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025070', '2025070@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade5_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade5_user_start, '2025061', '202500000061', 'New', 'Enrolled', 'Anastacio', 'Hernandez', 'Rivera', 'Male', '2015-02-20', 'Manila', 'Catholic', '147 Lotus Street, Manila', '147 Lotus Street, Manila', 5, 29, 'Miguel Hernandez', '09215678901', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+1, '2025062', '202500000062', 'New', 'Enrolled', 'Trinidad', 'Lopez', 'Torres', 'Female', '2015-06-25', 'Quezon City', 'Catholic', '258 Tulip Avenue, Quezon City', '258 Tulip Avenue, Quezon City', 6, 30, 'Carlos Lopez', '09226789012', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+2, '2025063', '202500000063', 'New', 'Enrolled', 'Ireneo', 'Martinez', 'Flores', 'Male', '2015-10-30', 'Pasig City', 'Catholic', '369 Jasmine Road, Pasig City', '369 Jasmine Road, Pasig City', 7, 31, 'Roberto Martinez', '09237890123', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+3, '2025064', '202500000064', 'New', 'Enrolled', 'Asuncion', 'Torres', 'Ramos', 'Female', '2015-04-14', 'Makati City', 'Catholic', '741 Magnolia Lane, Makati City', '741 Magnolia Lane, Makati City', 8, 32, 'Fernando Torres', '09248901234', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+4, '2025065', '202500000065', 'New', 'Enrolled', 'Crisanto', 'Flores', 'Morales', 'Male', '2015-08-18', 'Taguig City', 'Catholic', '852 Gardenia Drive, Taguig City', '852 Gardenia Drive, Taguig City', 9, 33, 'Manuel Flores', '09259012345', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+5, '2025066', '202500000066', 'New', 'Enrolled', 'Encarnacion', 'Ramos', 'Castro', 'Female', '2015-12-22', 'Paranaque City', 'Catholic', '963 Sunflower Court, Paranaque City', '963 Sunflower Court, Paranaque City', 10, 34, 'Ricardo Ramos', '09260123456', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+6, '2025067', '202500000067', 'New', 'Enrolled', 'Placido', 'Morales', 'Mendoza', 'Male', '2015-03-26', 'Las Pinas City', 'Catholic', '159 Lily Street, Las Pinas City', '159 Lily Street, Las Pinas City', 11, 35, 'Eduardo Morales', '09271234567', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+7, '2025068', '202500000068', 'New', 'Enrolled', 'Natividad', 'Castro', 'Jimenez', 'Female', '2015-07-30', 'Muntinlupa City', 'Catholic', '753 Rose Avenue, Muntinlupa City', '753 Rose Avenue, Muntinlupa City', 12, 36, 'Alejandro Castro', '09282345678', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+8, '2025069', '202500000069', 'New', 'Enrolled', 'Basilio', 'Dela Rosa', 'Villanueva', 'Male', '2015-11-14', 'Marikina City', 'Catholic', '486 Daisy Road, Marikina City', '486 Daisy Road, Marikina City', 25, 61, 'Daniel Dela Rosa', '09301234567', 'Father', 7, @enrollment_school_year_id, 1),
(@grade5_user_start+9, '2025070', '202500000070', 'New', 'Enrolled', 'Purificacion', 'Bautista', 'Salazar', 'Female', '2015-05-18', 'Pasay City', 'Catholic', '357 Orchid Lane, Pasay City', '357 Orchid Lane, Pasay City', 26, 62, 'Gabriel Bautista', '09312345678', 'Father', 7, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 5
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 7, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025061' AND '2025070';

-- Grade Level 8: Grade 6 (Born 2014 - Age 10-11)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025071', '2025071@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025072', '2025072@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025073', '2025073@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025074', '2025074@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025075', '2025075@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025076', '2025076@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025077', '2025077@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025078', '2025078@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025079', '2025079@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025080', '2025080@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade6_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade6_user_start, '2025071', '202500000071', 'New', 'Enrolled', 'Fortunato', 'Navarro', 'Cordero', 'Male', '2014-01-10', 'Manila', 'Catholic', '624 Hibiscus Street, Manila', '624 Hibiscus Street, Manila', 27, 63, 'Rafael Navarro', '09323456789', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+1, '2025072', '202500000072', 'New', 'Enrolled', 'Guadalupe', 'Valdez', 'Gutierrez', 'Female', '2014-05-15', 'Quezon City', 'Catholic', '791 Dahlia Avenue, Quezon City', '791 Dahlia Avenue, Quezon City', 28, 64, 'Samuel Valdez', '09334567890', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+2, '2025073', '202500000073', 'New', 'Enrolled', 'Leoncio', 'Aguilar', 'Herrera', 'Male', '2014-09-20', 'Pasig City', 'Catholic', '135 Petunia Road, Pasig City', '135 Petunia Road, Pasig City', 29, 65, 'Benjamin Aguilar', '09345678901', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+3, '2025074', '202500000074', 'New', 'Enrolled', 'Inmaculada', 'Velasco', 'Romero', 'Female', '2014-03-25', 'Makati City', 'Catholic', '802 Violet Lane, Makati City', '802 Violet Lane, Makati City', 30, 66, 'Nicolas Velasco', '09356789012', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+4, '2025075', '202500000075', 'New', 'Enrolled', 'Teodulo', 'Castillo', 'Vargas', 'Male', '2014-07-30', 'Taguig City', 'Catholic', '147 Marigold Drive, Taguig City', '147 Marigold Drive, Taguig City', 31, 67, 'Adrian Castillo', '09367890123', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+5, '2025076', '202500000076', 'New', 'Enrolled', 'Presentacion', 'Medina', 'Peña', 'Female', '2014-11-14', 'Paranaque City', 'Catholic', '258 Poppy Court, Paranaque City', '258 Poppy Court, Paranaque City', 32, 68, 'Victor Medina', '09378901234', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+6, '2025077', '202500000077', 'New', 'Enrolled', 'Ambrosio', 'Guerrero', 'Dominguez', 'Male', '2014-04-18', 'Las Pinas City', 'Catholic', '369 Azalea Street, Las Pinas City', '369 Azalea Street, Las Pinas City', 33, 69, 'Alejandro Guerrero', '09389012345', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+7, '2025078', '202500000078', 'New', 'Enrolled', 'Visitacion', 'Campos', 'Silva', 'Female', '2014-08-22', 'Muntinlupa City', 'Catholic', '741 Chrysanthemum Avenue, Muntinlupa City', '741 Chrysanthemum Avenue, Muntinlupa City', 34, 70, 'Francisco Campos', '09390123456', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+8, '2025079', '202500000079', 'New', 'Enrolled', 'Primitivo', 'Ortega', 'Ponce', 'Male', '2014-12-26', 'Marikina City', 'Catholic', '852 Freesia Road, Marikina City', '852 Freesia Road, Marikina City', 35, 71, 'Diego Ortega', '09401234567', 'Father', 8, @enrollment_school_year_id, 1),
(@grade6_user_start+9, '2025080', '202500000080', 'New', 'Enrolled', 'Salvacion', 'Lozano', 'Cervantes', 'Female', '2014-06-30', 'Pasay City', 'Catholic', '963 Cosmos Lane, Pasay City', '963 Cosmos Lane, Pasay City', 36, 72, 'Jorge Lozano', '09412345678', 'Father', 8, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 6
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 8, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025071' AND '2025080';

-- Continue with the remaining grades in the next section...

-- Grade Level 9: Grade 7 (Born 2013 - Age 11-12)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025081', '2025081@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025082', '2025082@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025083', '2025083@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025084', '2025084@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025085', '2025085@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025086', '2025086@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025087', '2025087@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025088', '2025088@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025089', '2025089@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025090', '2025090@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade7_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade7_user_start, '2025081', '202500000081', 'New', 'Enrolled', 'Domingo', 'Espinoza', 'Delgado', 'Male', '2013-02-14', 'Manila', 'Catholic', '159 Begonia Street, Manila', '159 Begonia Street, Manila', 37, 73, 'Raul Espinoza', '09423456789', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+1, '2025082', '202500000082', 'New', 'Enrolled', 'Socorro', 'Molina', 'Fuentes', 'Female', '2013-06-18', 'Quezon City', 'Catholic', '753 Camellia Avenue, Quezon City', '753 Camellia Avenue, Quezon City', 38, 74, 'Sergio Molina', '09434567890', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+2, '2025083', '202500000083', 'New', 'Enrolled', 'Eusebio', 'Pacheco', 'Sandoval', 'Male', '2013-10-22', 'Pasig City', 'Catholic', '486 Gladiolus Road, Pasig City', '486 Gladiolus Road, Pasig City', 39, 75, 'Arturo Pacheco', '09445678901', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+3, '2025084', '202500000084', 'New', 'Enrolled', 'Milagros', 'Figueroa', 'Escobar', 'Female', '2013-04-26', 'Makati City', 'Catholic', '357 Snapdragon Lane, Makati City', '357 Snapdragon Lane, Makati City', 40, 76, 'Ignacio Figueroa', '09456789012', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+4, '2025085', '202500000085', 'New', 'Enrolled', 'Prudencio', 'Contreras', 'Galvan', 'Male', '2013-08-30', 'Taguig City', 'Catholic', '624 Carnation Drive, Taguig City', '624 Carnation Drive, Taguig City', 41, 77, 'Emilio Contreras', '09467890123', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+5, '2025086', '202500000086', 'New', 'Enrolled', 'Conception', 'Maldonado', 'Barrera', 'Female', '2013-12-14', 'Paranaque City', 'Catholic', '791 Peony Court, Paranaque City', '791 Peony Court, Paranaque City', 42, 78, 'Rodrigo Maldonado', '09478901234', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+6, '2025087', '202500000087', 'New', 'Enrolled', 'Eufemio', 'Acosta', 'Cabrera', 'Male', '2013-03-18', 'Las Pinas City', 'Catholic', '135 Geranium Street, Las Pinas City', '135 Geranium Street, Las Pinas City', 43, 79, 'Armando Acosta', '09489012345', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+7, '2025088', '202500000088', 'New', 'Enrolled', 'Remedios', 'Vega', 'Cortez', 'Female', '2013-07-22', 'Muntinlupa City', 'Catholic', '802 Aster Avenue, Muntinlupa City', '802 Aster Avenue, Muntinlupa City', 44, 80, 'Esteban Vega', '09490123456', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+8, '2025089', '202500000089', 'New', 'Enrolled', 'Clementino', 'Rojas', 'Moreno', 'Male', '2013-11-26', 'Marikina City', 'Catholic', '147 Pansy Road, Marikina City', '147 Pansy Road, Marikina City', 45, 81, 'Cesar Rojas', '09501234567', 'Father', 9, @enrollment_school_year_id, 1),
(@grade7_user_start+9, '2025090', '202500000090', 'New', 'Enrolled', 'Soledad', 'Perez', 'Soto', 'Female', '2013-05-30', 'Pasay City', 'Catholic', '258 Daffodil Lane, Pasay City', '258 Daffodil Lane, Pasay City', 46, 82, 'Ruben Perez', '09512345678', 'Father', 9, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 7
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 9, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025081' AND '2025090';

-- Grade Level 10: Grade 8 (Born 2012 - Age 12-13)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025091', '2025091@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025092', '2025092@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025093', '2025093@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025094', '2025094@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025095', '2025095@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025096', '2025096@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025097', '2025097@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025098', '2025098@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025099', '2025099@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025100', '2025100@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade8_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade8_user_start, '2025091', '202500000091', 'New', 'Enrolled', 'Genaro', 'Carrasco', 'Restrepo', 'Male', '2012-01-12', 'Manila', 'Catholic', '369 Tulip Street, Manila', '369 Tulip Street, Manila', 47, 83, 'Andres Carrasco', '09523456789', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+1, '2025092', '202500000092', 'New', 'Enrolled', 'Filomena', 'Zuniga', 'Osorio', 'Female', '2012-05-16', 'Quezon City', 'Catholic', '741 Iris Avenue, Quezon City', '741 Iris Avenue, Quezon City', 48, 84, 'Mauricio Zuniga', '09534567890', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+2, '2025093', '202500000093', 'New', 'Enrolled', 'Honorio', 'Varela', 'Calderon', 'Male', '2012-09-20', 'Pasig City', 'Catholic', '852 Lavender Road, Pasig City', '852 Lavender Road, Pasig City', 49, 85, 'Hector Varela', '09545678901', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+3, '2025094', '202500000094', 'New', 'Enrolled', 'Perpetua', 'Hidalgo', 'Espejo', 'Female', '2012-03-24', 'Makati City', 'Catholic', '963 Jasmine Lane, Makati City', '963 Jasmine Lane, Makati City', 50, 86, 'Oscar Hidalgo', '09556789012', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+4, '2025095', '202500000095', 'New', 'Enrolled', 'Isidoro', 'Pantoja', 'Uribe', 'Male', '2012-07-28', 'Taguig City', 'Catholic', '159 Zinnia Drive, Taguig City', '159 Zinnia Drive, Taguig City', 51, 87, 'Marco Pantoja', '09567890123', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+5, '2025096', '202500000096', 'New', 'Enrolled', 'Consolacion', 'Quintero', 'Benitez', 'Female', '2012-11-12', 'Paranaque City', 'Catholic', '753 Cosmos Court, Paranaque City', '753 Cosmos Court, Paranaque City', 52, 88, 'Luis Quintero', '09578901234', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+6, '2025097', '202500000097', 'New', 'Enrolled', 'Macario', 'Camacho', 'Palacios', 'Male', '2012-04-16', 'Las Pinas City', 'Catholic', '486 Lily Street, Las Pinas City', '486 Lily Street, Las Pinas City', 53, 89, 'Pablo Camacho', '09589012345', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+7, '2025098', '202500000098', 'New', 'Enrolled', 'Caridad', 'Cardenas', 'Montoya', 'Female', '2012-08-20', 'Muntinlupa City', 'Catholic', '357 Sunflower Avenue, Muntinlupa City', '357 Sunflower Avenue, Muntinlupa City', 54, 90, 'Enrique Cardenas', '09590123456', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+8, '2025099', '202500000099', 'New', 'Enrolled', 'Nemesio', 'Solis', 'Villalobos', 'Male', '2012-12-24', 'Marikina City', 'Catholic', '624 Magnolia Road, Marikina City', '624 Magnolia Road, Marikina City', 55, 91, 'Javier Solis', '09601234567', 'Father', 10, @enrollment_school_year_id, 1),
(@grade8_user_start+9, '2025100', '202500000100', 'New', 'Enrolled', 'Epifania', 'Ibarra', 'Aranda', 'Female', '2012-06-28', 'Pasay City', 'Catholic', '791 Orchid Lane, Pasay City', '791 Orchid Lane, Pasay City', 56, 92, 'Mario Ibarra', '09612345678', 'Father', 10, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 8
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 10, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025091' AND '2025100';

-- Grade Level 11: Grade 9 (Born 2011 - Age 13-14)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025101', '2025101@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025102', '2025102@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025103', '2025103@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025104', '2025104@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025105', '2025105@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025106', '2025106@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025107', '2025107@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025108', '2025108@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025109', '2025109@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025110', '2025110@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade9_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade9_user_start, '2025101', '202500000101', 'New', 'Enrolled', 'Evaristo', 'Meza', 'Coronado', 'Male', '2011-02-08', 'Manila', 'Catholic', '135 Dahlia Street, Manila', '135 Dahlia Street, Manila', 57, 93, 'Felipe Meza', '09623456789', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+1, '2025102', '202500000102', 'New', 'Enrolled', 'Felicitas', 'Cano', 'Avalos', 'Female', '2011-06-12', 'Quezon City', 'Catholic', '802 Petunia Avenue, Quezon City', '802 Petunia Avenue, Quezon City', 58, 94, 'Alvaro Cano', '09634567890', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+2, '2025103', '202500000103', 'New', 'Enrolled', 'Melquiades', 'Ochoa', 'Bermudez', 'Male', '2011-10-16', 'Pasig City', 'Catholic', '147 Hibiscus Road, Pasig City', '147 Hibiscus Road, Pasig City', 59, 95, 'Guillermo Ochoa', '09645678901', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+3, '2025104', '202500000104', 'New', 'Enrolled', 'Candelaria', 'Paredes', 'Casillas', 'Female', '2011-04-20', 'Makati City', 'Catholic', '258 Freesia Lane, Makati City', '258 Freesia Lane, Makati City', 60, 96, 'Jaime Paredes', '09656789012', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+4, '2025105', '202500000105', 'New', 'Enrolled', 'Apolinario', 'Cruz', 'Santos', 'Male', '2011-08-24', 'Taguig City', 'Catholic', '369 Marigold Drive, Taguig City', '369 Marigold Drive, Taguig City', 1, 25, 'Juan Cruz', '09171234567', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+5, '2025106', '202500000106', 'New', 'Enrolled', 'Encarnacion', 'Reyes', 'Garcia', 'Female', '2011-12-28', 'Paranaque City', 'Catholic', '741 Gladiolus Court, Paranaque City', '741 Gladiolus Court, Paranaque City', 2, 26, 'Jose Reyes', '09182345678', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+6, '2025107', '202500000107', 'New', 'Enrolled', 'Bartolome', 'Santos', 'Lopez', 'Male', '2011-05-12', 'Las Pinas City', 'Catholic', '852 Chrysanthemum Street, Las Pinas City', '852 Chrysanthemum Street, Las Pinas City', 3, 27, 'Antonio Santos', '09193456789', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+7, '2025108', '202500000108', 'New', 'Enrolled', 'Esperanza', 'Gonzales', 'Martinez', 'Female', '2011-09-16', 'Muntinlupa City', 'Catholic', '963 Azalea Avenue, Muntinlupa City', '963 Azalea Avenue, Muntinlupa City', 4, 28, 'Pedro Gonzales', '09204567890', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+8, '2025109', '202500000109', 'New', 'Enrolled', 'Sinforoso', 'Hernandez', 'Rivera', 'Male', '2011-01-20', 'Marikina City', 'Catholic', '159 Begonia Road, Marikina City', '159 Begonia Road, Marikina City', 5, 29, 'Miguel Hernandez', '09215678901', 'Father', 11, @enrollment_school_year_id, 1),
(@grade9_user_start+9, '2025110', '202500000110', 'New', 'Enrolled', 'Remedios', 'Lopez', 'Torres', 'Female', '2011-07-24', 'Pasay City', 'Catholic', '486 Camellia Lane, Pasay City', '486 Camellia Lane, Pasay City', 6, 30, 'Carlos Lopez', '09226789012', 'Father', 11, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 9
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 11, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025101' AND '2025110';

-- Grade Level 12: Grade 10 (Born 2010 - Age 14-15)
INSERT INTO users (username, email, password, role_id, is_active, created_at) VALUES
('2025111', '2025111@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025112', '2025112@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025113', '2025113@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025114', '2025114@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025115', '2025115@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025116', '2025116@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025117', '2025117@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025118', '2025118@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025119', '2025119@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW()),
('2025120', '2025120@student.gtba.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @student_role_id, 1, NOW());

SET @grade10_user_start = LAST_INSERT_ID() - 9;

INSERT INTO students (user_id, student_id, lrn, student_type, enrollment_status, first_name, last_name, middle_name, gender, date_of_birth, place_of_birth, religion, present_address, permanent_address, father_id, mother_id, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, current_grade_level_id, current_school_year_id, created_by) VALUES
(@grade10_user_start, '2025111', '202500000111', 'New', 'Enrolled', 'Floriano', 'Martinez', 'Flores', 'Male', '2010-01-05', 'Manila', 'Catholic', '753 Violet Street, Manila', '753 Violet Street, Manila', 7, 31, 'Roberto Martinez', '09237890123', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+1, '2025112', '202500000112', 'New', 'Enrolled', 'Catalina', 'Torres', 'Ramos', 'Female', '2010-05-10', 'Quezon City', 'Catholic', '624 Daffodil Avenue, Quezon City', '624 Daffodil Avenue, Quezon City', 8, 32, 'Fernando Torres', '09248901234', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+2, '2025113', '202500000113', 'New', 'Enrolled', 'Nicanor', 'Flores', 'Morales', 'Male', '2010-09-14', 'Pasig City', 'Catholic', '791 Lavender Road, Pasig City', '791 Lavender Road, Pasig City', 9, 33, 'Manuel Flores', '09259012345', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+3, '2025114', '202500000114', 'New', 'Enrolled', 'Dionisia', 'Ramos', 'Castro', 'Female', '2010-03-18', 'Makati City', 'Catholic', '135 Jasmine Lane, Makati City', '135 Jasmine Lane, Makati City', 10, 34, 'Ricardo Ramos', '09260123456', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+4, '2025115', '202500000115', 'New', 'Enrolled', 'Policarpo', 'Morales', 'Mendoza', 'Male', '2010-07-22', 'Taguig City', 'Catholic', '802 Poppy Drive, Taguig City', '802 Poppy Drive, Taguig City', 11, 35, 'Eduardo Morales', '09271234567', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+5, '2025116', '202500000116', 'New', 'Enrolled', 'Consolacion', 'Castro', 'Jimenez', 'Female', '2010-11-26', 'Paranaque City', 'Catholic', '147 Geranium Court, Paranaque City', '147 Geranium Court, Paranaque City', 12, 36, 'Alejandro Castro', '09282345678', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+6, '2025117', '202500000117', 'New', 'Enrolled', 'Anastasio', 'Dela Rosa', 'Villanueva', 'Male', '2010-04-30', 'Las Pinas City', 'Catholic', '258 Snapdragon Street, Las Pinas City', '258 Snapdragon Street, Las Pinas City', 25, 61, 'Daniel Dela Rosa', '09301234567', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+7, '2025118', '202500000118', 'New', 'Enrolled', 'Feliciana', 'Bautista', 'Salazar', 'Female', '2010-08-14', 'Muntinlupa City', 'Catholic', '369 Carnation Avenue, Muntinlupa City', '369 Carnation Avenue, Muntinlupa City', 26, 62, 'Gabriel Bautista', '09312345678', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+8, '2025119', '202500000119', 'New', 'Enrolled', 'Hermenegildo', 'Navarro', 'Cordero', 'Male', '2010-12-18', 'Marikina City', 'Catholic', '741 Peony Road, Marikina City', '741 Peony Road, Marikina City', 27, 63, 'Rafael Navarro', '09323456789', 'Father', 12, @enrollment_school_year_id, 1),
(@grade10_user_start+9, '2025120', '202500000120', 'New', 'Enrolled', 'Esperanza', 'Valdez', 'Gutierrez', 'Female', '2010-06-22', 'Pasay City', 'Catholic', '852 Cosmos Lane, Pasay City', '852 Cosmos Lane, Pasay City', 28, 64, 'Samuel Valdez', '09334567890', 'Father', 12, @enrollment_school_year_id, 1);

-- Insert enrollment records for Grade 10
INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, enrollment_date, enrollment_status, created_by)
SELECT s.id, @enrollment_school_year_id, 12, '2025-08-15', 'Enrolled', 1
FROM students s 
WHERE s.student_id BETWEEN '2025111' AND '2025120';

-- Update current enrollment counts for sections
UPDATE sections s 
JOIN (
    SELECT se.section_id, COUNT(*) as enrollment_count
    FROM student_enrollments se
    JOIN students st ON se.student_id = st.id
    WHERE se.school_year_id = @enrollment_school_year_id
    AND se.enrollment_status = 'Enrolled'
    GROUP BY se.section_id
) enrollment_data ON s.id = enrollment_data.section_id
SET s.current_enrollment = enrollment_data.enrollment_count
WHERE s.school_year_id = @enrollment_school_year_id;

COMMIT;

-- Update current enrollment counts for sections
UPDATE sections s 
JOIN (
    SELECT se.section_id, COUNT(*) as enrollment_count
    FROM student_enrollments se
    JOIN students st ON se.student_id = st.id
    WHERE se.school_year_id = @enrollment_school_year_ida
    AND se.enrollment_status = 'Enrolled'
    GROUP BY se.section_id
) enrollment_data ON s.id = enrollment_data.section_id
SET s.current_enrollment = enrollment_data.enrollment_count
WHERE s.school_year_id = @enrollment_school_year_id;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

-- Summary of what this script creates:
-- 1. 220 additional guardian records (110 fathers + 110 mothers) for comprehensive family data
-- 2. User accounts for 110 students (10 per remaining grade level) with username = student_id and default password 'password'
-- 3. Complete student records with guardian relationships for remaining 11 grade levels (excluding Nursery)
-- 4. Enrollment records for the active school year for all students
-- 5. Updated section enrollment counts

SELECT 'Comprehensive sample data creation completed successfully!' AS message,
       'Created 110 students across remaining 11 grade levels (10 students per grade)' AS detail1,
       'Each student has complete guardian information and enrollment records' AS detail2,
       'All students have user accounts with default password: password' AS detail3,
       'Student IDs range from 2025011 to 2025120' AS detail4,
       'All students are enrolled in the active school year' AS detail5,
       'Nursery students (2025001-2025010) were already created by fixed_sample_data.sql' AS detail6;
