-- Fix Student ID Format to YEAR + Sequential Number
-- This script updates any non-conforming student IDs and sets up proper format

-- Step 1: Find the student with incorrect format
SELECT id, student_id, first_name, last_name, created_at 
FROM students 
WHERE student_id = '123123';

-- Step 2: Update the non-conforming student ID to follow the format
-- Assuming this student was the 121st student registered in 2025
UPDATE students 
SET student_id = '2025121'
WHERE student_id = '123123';

-- Step 3: Also update the corresponding username in users table
UPDATE users 
SET username = '2025121'
WHERE username = '123123';

-- Step 4: Verify all students now follow the correct format
SELECT 
    COUNT(*) as total_students,
    COUNT(CASE WHEN student_id REGEXP '^[0-9]{4}[0-9]{3}$' THEN 1 END) as correct_format,
    COUNT(CASE WHEN student_id NOT REGEXP '^[0-9]{4}[0-9]{3}$' THEN 1 END) as incorrect_format
FROM students;

-- Step 5: Show all student IDs to verify
SELECT student_id, first_name, last_name, created_at 
FROM students 
ORDER BY student_id;
