-- Get John Doe's user_id
SELECT user_id, first_name, last_name FROM teachers WHERE first_name = 'John' AND last_name = 'Doe';

-- Get Grade 2 - Orchid section_id
SELECT s.id, s.section_name, gl.grade_name 
FROM sections s 
JOIN grade_levels gl ON s.grade_level_id = gl.id 
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';

-- Insert John Doe as teacher for Grade 2 - Orchid section
INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by)
SELECT 
    s.id,
    (SELECT user_id FROM teachers WHERE first_name = 'John' AND last_name = 'Doe' LIMIT 1),
    1,
    (SELECT id FROM users WHERE role = 'principal' LIMIT 1)
FROM sections s
JOIN grade_levels gl ON s.grade_level_id = gl.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2'
AND NOT EXISTS (
    SELECT 1 FROM section_teachers st 
    WHERE st.section_id = s.id 
    AND st.teacher_id = (SELECT user_id FROM teachers WHERE first_name = 'John' AND last_name = 'Doe' LIMIT 1)
);

-- Verify the insertion
SELECT st.*, t.first_name, t.last_name, s.section_name, gl.grade_name
FROM section_teachers st
JOIN teachers t ON st.teacher_id = t.user_id
JOIN sections s ON st.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';
