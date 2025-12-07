-- Assign John Doe and Jane Smith to Grade 2 - Orchid section (section_id = 9)
INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by)
VALUES 
    (9, 11, 1, 1),  -- John Doe as primary teacher
    (9, 12, 0, 1)   -- Jane Smith as secondary teacher
ON DUPLICATE KEY UPDATE is_active = 1;

-- Update Filipino schedules with Jane Smith's teacher_id
UPDATE class_schedules 
SET teacher_id = 12
WHERE section_id = 9 
  AND subject_id = (SELECT id FROM subjects WHERE subject_name = 'Filipino' LIMIT 1)
  AND teacher_id IS NULL;

-- Update English schedules with John Doe's teacher_id
UPDATE class_schedules 
SET teacher_id = 11
WHERE section_id = 9 
  AND subject_id = (SELECT id FROM subjects WHERE subject_name = 'English' LIMIT 1)
  AND teacher_id IS NULL;

-- Verify section_teachers
SELECT st.*, t.first_name, t.last_name, s.section_name
FROM section_teachers st
JOIN teachers t ON st.teacher_id = t.user_id
JOIN sections s ON st.section_id = s.id
WHERE st.section_id = 9;

-- Verify class_schedules
SELECT cs.id, sub.subject_name, t.first_name, t.last_name, cs.day_of_week, cs.start_time
FROM class_schedules cs
LEFT JOIN subjects sub ON cs.subject_id = sub.id
LEFT JOIN teachers t ON cs.teacher_id = t.user_id
WHERE cs.section_id = 9
ORDER BY cs.day_of_week, cs.start_time;
