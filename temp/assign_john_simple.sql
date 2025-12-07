-- Assign John Doe (user_id 11) to Grade 2 - Orchid section
INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by)
SELECT 
    s.id,
    11,
    1,
    1
FROM sections s
JOIN grade_levels gl ON s.grade_level_id = gl.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2'
AND NOT EXISTS (
    SELECT 1 FROM section_teachers st 
    WHERE st.section_id = s.id AND st.teacher_id = 11
);

-- Verify
SELECT st.id, st.section_id, st.teacher_id, st.is_primary, t.first_name, t.last_name, s.section_name, gl.grade_name
FROM section_teachers st
JOIN teachers t ON st.teacher_id = t.user_id
JOIN sections s ON st.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';
