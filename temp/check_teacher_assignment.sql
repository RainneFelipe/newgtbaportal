-- Check which teacher is assigned to Grade 2 - Orchid
SELECT st.*, t.first_name, t.last_name, t.user_id, s.section_name, gl.grade_name 
FROM section_teachers st 
JOIN teachers t ON st.teacher_id = t.user_id 
JOIN sections s ON st.section_id = s.id 
JOIN grade_levels gl ON s.grade_level_id = gl.id 
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';

-- Check the existing schedules for Grade 2 - Orchid
SELECT cs.*, s.section_name, gl.grade_name, sub.subject_name
FROM class_schedules cs
JOIN sections s ON cs.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
LEFT JOIN subjects sub ON cs.subject_id = sub.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';

-- Get John Doe's user_id
SELECT id, username, first_name, last_name FROM teachers WHERE first_name = 'John' AND last_name = 'Doe';
