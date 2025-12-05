DELIMITER $$

CREATE TRIGGER auto_promote_student_on_grade_save
AFTER INSERT ON student_grades
FOR EACH ROW
auto_promote_label: BEGIN
    DECLARE student_grade_level_id INT;
    DECLARE next_grade_level_id INT;
    DECLARE student_enrollment_status VARCHAR(50);
    DECLARE student_prevent_promotion TINYINT;
    DECLARE total_subjects INT;
    DECLARE passing_subjects INT;
    DECLARE is_last_grade TINYINT;
    
    -- Get student info
    SELECT current_grade_level_id, enrollment_status, prevent_auto_promotion
    INTO student_grade_level_id, student_enrollment_status, student_prevent_promotion
    FROM students 
    WHERE id = NEW.student_id;
    
    -- Check if auto-promotion is prevented
    IF student_prevent_promotion = 1 THEN
        LEAVE auto_promote_label;
    END IF;
    
    -- Only proceed if student is enrolled
    IF student_enrollment_status = 'Enrolled' THEN
        -- Count total subjects and passing subjects for this student
        SELECT COUNT(*), SUM(CASE WHEN final_grade >= 75 THEN 1 ELSE 0 END)
        INTO total_subjects, passing_subjects
        FROM student_grades
        WHERE student_id = NEW.student_id 
        AND school_year_id = NEW.school_year_id;
        
        -- Check if all subjects are passing
        IF total_subjects > 0 AND total_subjects = passing_subjects THEN
            -- Get next grade level
            SELECT gl2.id, gl2.grade_order
            INTO next_grade_level_id, is_last_grade
            FROM grade_levels gl1
            LEFT JOIN grade_levels gl2 ON gl2.grade_order = gl1.grade_order + 1 AND gl2.is_active = 1
            WHERE gl1.id = student_grade_level_id
            LIMIT 1;
            
            IF next_grade_level_id IS NOT NULL THEN
                -- Promote to next grade
                UPDATE students 
                SET current_grade_level_id = next_grade_level_id,
                    current_section_id = NULL,
                    enrollment_status = 'Pending Payment',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            ELSE
                -- Mark as graduated
                UPDATE students 
                SET enrollment_status = 'Graduated',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            END IF;
        END IF;
    END IF;
END$$

CREATE TRIGGER auto_promote_student_on_grade_update
AFTER UPDATE ON student_grades
FOR EACH ROW
auto_promote_update_label: BEGIN
    DECLARE student_grade_level_id INT;
    DECLARE next_grade_level_id INT;
    DECLARE student_enrollment_status VARCHAR(50);
    DECLARE student_prevent_promotion TINYINT;
    DECLARE total_subjects INT;
    DECLARE passing_subjects INT;
    DECLARE is_last_grade TINYINT;
    
    -- Get student info
    SELECT current_grade_level_id, enrollment_status, prevent_auto_promotion
    INTO student_grade_level_id, student_enrollment_status, student_prevent_promotion
    FROM students 
    WHERE id = NEW.student_id;
    
    -- Check if auto-promotion is prevented
    IF student_prevent_promotion = 1 THEN
        LEAVE auto_promote_update_label;
    END IF;
    
    -- Only proceed if student is enrolled
    IF student_enrollment_status = 'Enrolled' THEN
        -- Count total subjects and passing subjects for this student
        SELECT COUNT(*), SUM(CASE WHEN final_grade >= 75 THEN 1 ELSE 0 END)
        INTO total_subjects, passing_subjects
        FROM student_grades
        WHERE student_id = NEW.student_id 
        AND school_year_id = NEW.school_year_id;
        
        -- Check if all subjects are passing
        IF total_subjects > 0 AND total_subjects = passing_subjects THEN
            -- Get next grade level
            SELECT gl2.id, gl2.grade_order
            INTO next_grade_level_id, is_last_grade
            FROM grade_levels gl1
            LEFT JOIN grade_levels gl2 ON gl2.grade_order = gl1.grade_order + 1 AND gl2.is_active = 1
            WHERE gl1.id = student_grade_level_id
            LIMIT 1;
            
            IF next_grade_level_id IS NOT NULL THEN
                -- Promote to next grade
                UPDATE students 
                SET current_grade_level_id = next_grade_level_id,
                    current_section_id = NULL,
                    enrollment_status = 'Pending Payment',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            ELSE
                -- Mark as graduated
                UPDATE students 
                SET enrollment_status = 'Graduated',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;
