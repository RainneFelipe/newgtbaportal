UPDATE class_schedules 
SET teacher_id = 12
WHERE section_id = 9 
  AND subject_id IN (SELECT id FROM subjects WHERE subject_name LIKE '%Filipino%');

UPDATE class_schedules 
SET teacher_id = 11
WHERE section_id = 9 
  AND subject_id IN (SELECT id FROM subjects WHERE subject_name LIKE '%English%');
