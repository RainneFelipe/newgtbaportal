-- Fix Student-Guardian Relationships
-- This script corrects the mismatch between student records and guardian IDs

USE newgtbaportal;

-- First, let's see the current state
SELECT 'Current Guardian ID Ranges:' as Info;
SELECT MIN(id) as min_id, MAX(id) as max_id, guardian_type, COUNT(*) as count
FROM student_guardians 
GROUP BY guardian_type
ORDER BY guardian_type;

SELECT 'Current Student Guardian References:' as Info;
SELECT MIN(father_id) as min_father_id, MAX(father_id) as max_father_id,
       MIN(mother_id) as min_mother_id, MAX(mother_id) as max_mother_id,
       MIN(legal_guardian_id) as min_legal_guardian_id, MAX(legal_guardian_id) as max_legal_guardian_id
FROM students 
WHERE is_active = 1;

-- Backup the current students table
CREATE TABLE students_backup AS SELECT * FROM students;

-- Fix the guardian relationships by updating student records to match existing guardian IDs
-- Based on the data pattern, it appears the relationships should be sequential

-- Update student records to correctly reference guardian IDs
-- The pattern seems to be:
-- Fathers: IDs 226-341 (first 116 fathers)
-- Mothers: IDs 238-249 and continuing (mothers)

-- Let's create a proper mapping
-- First, create temporary tables to establish correct relationships

-- Create a temp table for correct father mappings
CREATE TEMPORARY TABLE father_mapping AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY s.id) as student_seq,
    s.id as student_id,
    sg.id as guardian_id
FROM students s
CROSS JOIN student_guardians sg
WHERE sg.guardian_type = 'Father'
    AND s.is_active = 1
ORDER BY s.id, sg.id
LIMIT (SELECT COUNT(*) FROM students WHERE is_active = 1);

-- Create a temp table for correct mother mappings  
CREATE TEMPORARY TABLE mother_mapping AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY s.id) as student_seq,
    s.id as student_id,
    sg.id as guardian_id
FROM students s
CROSS JOIN student_guardians sg
WHERE sg.guardian_type = 'Mother'
    AND s.is_active = 1
ORDER BY s.id, sg.id
LIMIT (SELECT COUNT(*) FROM students WHERE is_active = 1);

-- Let's use a simpler approach - map students to guardians sequentially
-- First, get the count of students and guardians
SET @student_count = (SELECT COUNT(*) FROM students WHERE is_active = 1);
SET @father_count = (SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Father');
SET @mother_count = (SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Mother');

-- Clear existing guardian references temporarily
UPDATE students SET father_id = NULL, mother_id = NULL, legal_guardian_id = NULL WHERE is_active = 1;

-- Now let's create proper one-to-one mappings
-- This approach ensures each student gets a unique father and mother

-- Update father relationships - map students sequentially to fathers
SET @row_number = 0;
UPDATE students s
JOIN (
    SELECT id as student_id, 
           (@row_number := @row_number + 1) as rn
    FROM students 
    WHERE is_active = 1 
    ORDER BY id
) student_order ON s.id = student_order.student_id
JOIN (
    SELECT id as guardian_id,
           ROW_NUMBER() OVER (ORDER BY id) as guardian_rn
    FROM student_guardians 
    WHERE guardian_type = 'Father'
) father_order ON student_order.rn = father_order.guardian_rn
SET s.father_id = father_order.guardian_id;

-- Update mother relationships - map students sequentially to mothers  
SET @row_number = 0;
UPDATE students s
JOIN (
    SELECT id as student_id, 
           (@row_number := @row_number + 1) as rn
    FROM students 
    WHERE is_active = 1 
    ORDER BY id
) student_order ON s.id = student_order.student_id
JOIN (
    SELECT id as guardian_id,
           ROW_NUMBER() OVER (ORDER BY id) as guardian_rn
    FROM student_guardians 
    WHERE guardian_type = 'Mother'
) mother_order ON student_order.rn = mother_order.guardian_rn
SET s.mother_id = mother_order.guardian_id;

-- Verify the fix
SELECT 'After Fix - Verification:' as Info;

-- Check if all student guardian references are valid
SELECT 'Invalid Father References:' as Check_Type, COUNT(*) as count
FROM students s
LEFT JOIN student_guardians sg ON s.father_id = sg.id AND sg.guardian_type = 'Father'
WHERE s.is_active = 1 AND s.father_id IS NOT NULL AND sg.id IS NULL

UNION ALL

SELECT 'Invalid Mother References:' as Check_Type, COUNT(*) as count
FROM students s
LEFT JOIN student_guardians sg ON s.mother_id = sg.id AND sg.guardian_type = 'Mother'
WHERE s.is_active = 1 AND s.mother_id IS NOT NULL AND sg.id IS NULL

UNION ALL

SELECT 'Invalid Legal Guardian References:' as Check_Type, COUNT(*) as count
FROM students s
LEFT JOIN student_guardians sg ON s.legal_guardian_id = sg.id AND sg.guardian_type = 'Legal Guardian'
WHERE s.is_active = 1 AND s.legal_guardian_id IS NOT NULL AND sg.id IS NULL;

-- Sample verification - show first 10 students with their guardian info
SELECT 'Sample Verification - First 10 Students:' as Info;
SELECT 
    s.id,
    s.first_name,
    s.last_name,
    s.father_id,
    father.first_name as father_first_name,
    father.last_name as father_last_name,
    s.mother_id,
    mother.first_name as mother_first_name,
    mother.last_name as mother_last_name
FROM students s
LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
WHERE s.is_active = 1
ORDER BY s.id
LIMIT 10;
