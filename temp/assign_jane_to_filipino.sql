-- Get Jane Smith's user_id
SELECT user_id, first_name, last_name FROM teachers WHERE first_name = 'Jane' AND last_name = 'Smith';

-- Update the existing Filipino schedules to assign Jane Smith
UPDATE class_schedules cs
JOIN sections s ON cs.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
JOIN subjects sub ON cs.subject_id = sub.id
SET cs.teacher_id = (
    SELECT user_id FROM teachers WHERE first_name = 'Jane' AND last_name = 'Smith' LIMIT 1
)
WHERE s.section_name = 'Orchid' 
  AND gl.grade_name = 'Grade 2'
  AND sub.subject_name = 'Filipino'
  AND cs.teacher_id IS NULL;

-- Assign Jane Smith to the section if not already assigned
INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by)
SELECT 
    s.id,
    (SELECT user_id FROM teachers WHERE first_name = 'Jane' AND last_name = 'Smith' LIMIT 1),
    0,
    1
FROM sections s
JOIN grade_levels gl ON s.grade_level_id = gl.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2'
AND NOT EXISTS (
    SELECT 1 FROM section_teachers st 
    WHERE st.section_id = s.id 
    AND st.teacher_id = (SELECT user_id FROM teachers WHERE first_name = 'Jane' AND last_name = 'Smith' LIMIT 1)
);

-- Verify the updates
SELECT cs.id, cs.teacher_id, t.first_name, t.last_name, s.section_name, gl.grade_name, sub.subject_name, cs.day_of_week
FROM class_schedules cs
JOIN sections s ON cs.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
LEFT JOIN subjects sub ON cs.subject_id = sub.id
LEFT JOIN teachers t ON cs.teacher_id = t.user_id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';
