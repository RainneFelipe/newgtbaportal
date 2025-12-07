-- Update existing Grade 2 - Orchid English schedules to assign John Doe as teacher
-- First, get John Doe's user_id and the section_id for Grade 2 - Orchid

UPDATE class_schedules cs
JOIN sections s ON cs.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
JOIN subjects sub ON cs.subject_id = sub.id
SET cs.teacher_id = (
    SELECT user_id FROM teachers WHERE first_name = 'John' AND last_name = 'Doe' LIMIT 1
)
WHERE s.section_name = 'Orchid' 
  AND gl.grade_name = 'Grade 2'
  AND sub.subject_name = 'English'
  AND cs.teacher_id IS NULL;

-- Verify the update
SELECT cs.id, cs.teacher_id, s.section_name, gl.grade_name, sub.subject_name, cs.day_of_week, cs.start_time, cs.end_time
FROM class_schedules cs
JOIN sections s ON cs.section_id = s.id
JOIN grade_levels gl ON s.grade_level_id = gl.id
LEFT JOIN subjects sub ON cs.subject_id = sub.id
WHERE s.section_name = 'Orchid' AND gl.grade_name = 'Grade 2';
