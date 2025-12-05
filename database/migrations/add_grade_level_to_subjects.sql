-- Migration: Add grade_level_id to subjects table
-- Date: 2025-12-05
-- Description: Add grade level association to subjects and populate existing subjects

-- Add the grade_level_id column
ALTER TABLE `subjects` 
ADD COLUMN `grade_level_id` INT NULL AFTER `subject_name`,
ADD KEY `fk_subjects_grade_level` (`grade_level_id`),
ADD CONSTRAINT `fk_subjects_grade_level` 
    FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Update existing subjects based on their subject codes
-- Grade 1 subjects (G1-)
UPDATE subjects SET grade_level_id = 3 WHERE subject_code LIKE 'G1-%';

-- Grade 2 subjects (G2-)
UPDATE subjects SET grade_level_id = 4 WHERE subject_code LIKE 'G2-%';

-- Grade 3 subjects (G3-)
UPDATE subjects SET grade_level_id = 5 WHERE subject_code LIKE 'G3-%';

-- Grade 4 subjects (G4-)
UPDATE subjects SET grade_level_id = 6 WHERE subject_code LIKE 'G4-%';

-- Grade 5 subjects (G5-)
UPDATE subjects SET grade_level_id = 7 WHERE subject_code LIKE 'G5-%';

-- Grade 6 subjects (G6-)
UPDATE subjects SET grade_level_id = 8 WHERE subject_code LIKE 'G6-%';

-- Grade 7 subjects (G7-)
UPDATE subjects SET grade_level_id = 9 WHERE subject_code LIKE 'G7-%';

-- Grade 8 subjects (G8-)
UPDATE subjects SET grade_level_id = 10 WHERE subject_code LIKE 'G8-%';

-- Grade 9 subjects (G9-)
UPDATE subjects SET grade_level_id = 11 WHERE subject_code LIKE 'G9-%';

-- Grade 10 subjects (G10-)
UPDATE subjects SET grade_level_id = 12 WHERE subject_code LIKE 'G10-%';

-- Kindergarten subjects (K-)
UPDATE subjects SET grade_level_id = 2 WHERE subject_code LIKE 'K-%';

-- Nursery subjects (NURS-)
UPDATE subjects SET grade_level_id = 1 WHERE subject_code LIKE 'NURS-%';

-- Verify the updates
SELECT 
    gl.grade_name,
    COUNT(s.id) as subject_count,
    GROUP_CONCAT(s.subject_code ORDER BY s.subject_code SEPARATOR ', ') as subjects
FROM grade_levels gl
LEFT JOIN subjects s ON gl.id = s.grade_level_id
GROUP BY gl.id, gl.grade_name
ORDER BY gl.grade_order;
