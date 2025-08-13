# Student Re-enrollment Process for School Year Transition

## Overview

When the admin changes the active school year from **2025-2026** to **2026-2027**, students need to be re-enrolled and promoted to the next grade level. This document explains the process for handling student promotion and re-enrollment.

## Current System Behavior

### Before Enhancement
The GTBA Portal system had **manual re-enrollment** where the registrar would need to:
1. Edit each student record individually through `student_edit.php`
2. Update the `current_grade_level_id`, `current_school_year_id`, and `student_type` fields
3. Create new enrollment records manually

### After Enhancement (New Feature)
A new **Student Promotion & Re-enrollment** module (`student_promotion.php`) provides:
- Bulk promotion capabilities
- Automated grade level progression
- Proper enrollment history tracking

## Re-enrollment Process for Grade 1 → Grade 2

### Step 1: Admin Sets New Active School Year
```sql
-- Admin changes active school year
UPDATE school_years SET is_active = 0 WHERE is_active = 1;
UPDATE school_years SET is_active = 1 WHERE id = [new_year_id];
```

### Step 2: Registrar Uses Promotion Module

1. **Access Promotion Module**
   - Navigate to `registrar/student_promotion.php`
   - Select **From School Year**: 2025-2026
   - Filter by **Current Grade Level**: Grade 1

2. **Review Students**
   - System displays all Grade 1 students from 2025-2026
   - Shows automatic progression: Grade 1 → Grade 2
   - Allows selection of specific students

3. **Execute Promotion**
   - Select students to promote
   - Choose **Promote to School Year**: 2026-2027
   - Click "Promote Selected"

### Step 3: System Updates

For each promoted student, the system performs:

```sql
-- Update student's current information
UPDATE students SET 
    current_grade_level_id = [grade_2_id],
    current_school_year_id = [2026_2027_id],
    current_section_id = NULL,
    student_type = 'Continuing',
    enrollment_status = 'Enrolled',
    updated_at = NOW()
WHERE id = [student_id];

-- Create new enrollment record
INSERT INTO student_enrollments 
(student_id, school_year_id, grade_level_id, section_id, 
 enrollment_date, enrollment_status, created_by)
VALUES ([student_id], [2026_2027_id], [grade_2_id], NULL, CURDATE(), 'Enrolled', [registrar_id]);
```

## Database Schema for Enrollment Tracking

### Students Table
```sql
CREATE TABLE students (
    id int PRIMARY KEY,
    current_grade_level_id int,     -- Updated to Grade 2
    current_section_id int,         -- Reset to NULL (assigned later)
    current_school_year_id int,     -- Updated to 2026-2027
    student_type enum('New','Transfer','Continuing'),  -- Set to 'Continuing'
    enrollment_status enum('Enrolled','Dropped','Graduated','Transferred')
);
```

### Student Enrollments Table (Historical Records)
```sql
CREATE TABLE student_enrollments (
    id int PRIMARY KEY,
    student_id int,
    school_year_id int,             -- Tracks year-by-year enrollment
    grade_level_id int,             -- Grade for that specific year
    section_id int,                 -- Section assignment
    enrollment_date date,
    enrollment_status enum('Enrolled','Dropped','Graduated','Transferred')
);
```

## Complete Re-enrollment Workflow

### 1. Pre-Promotion Checklist
- [ ] New school year (2026-2027) is created
- [ ] Grade 2 sections are created for 2026-2027
- [ ] Teacher assignments are prepared
- [ ] Curriculum is set up for Grade 2

### 2. Promotion Execution
```php
// Example: Promoting student ID 1 from Grade 1 to Grade 2
$student_id = 1;
$old_grade_id = 1; // Grade 1
$new_grade_id = 2; // Grade 2
$old_year_id = 1;  // 2025-2026
$new_year_id = 2;  // 2026-2027

// Update current student info
$update_query = "UPDATE students SET 
                current_grade_level_id = ?,
                current_school_year_id = ?,
                current_section_id = NULL,
                student_type = 'Continuing',
                enrollment_status = 'Enrolled'
                WHERE id = ?";
$stmt->execute([$new_grade_id, $new_year_id, $student_id]);

// Create enrollment record
$enrollment_query = "INSERT INTO student_enrollments 
                   (student_id, school_year_id, grade_level_id, section_id, 
                    enrollment_date, enrollment_status, created_by)
                   VALUES (?, ?, ?, NULL, CURDATE(), 'Enrolled', ?)";
$stmt->execute([$student_id, $new_year_id, $new_grade_id, $registrar_id]);
```

### 3. Post-Promotion Tasks
- [ ] Assign students to Grade 2 sections
- [ ] Update class schedules
- [ ] Set up tuition fees for new grade level
- [ ] Generate enrollment reports

## Manual Alternative (Individual Student)

If bulk promotion is not needed, registrars can still use the individual edit approach:

1. Go to `student_records.php`
2. Click "Edit" on the student
3. Update fields:
   - **Student Type**: Continuing
   - **Current Grade Level**: Grade 2
   - **Current School Year**: 2026-2027
   - **Current Section**: (Select appropriate Grade 2 section)

## Key Benefits of New Promotion System

### Automated Processing
- Reduces manual work from hours to minutes
- Eliminates human error in grade progression
- Maintains proper enrollment history

### Audit Trail
- Creates proper `student_enrollments` records
- Tracks promotion dates and responsible users
- Maintains historical enrollment data

### Bulk Operations
- Process entire grade levels at once
- Filter by specific criteria
- Select/deselect individual students as needed

## Reports and Tracking

After re-enrollment, use these reports to verify:

1. **Enrollment Reports** (`enrollment_reports.php`)
   - By Grade Level: Check Grade 2 enrollment numbers
   - By School Year: Verify 2026-2027 enrollments
   - Detailed Reports: Individual student verification

2. **Student Records** (`student_records.php`)
   - Filter by Grade 2 and 2026-2027
   - Verify student types show "Continuing"
   - Check enrollment status is "Enrolled"

## Troubleshooting Common Issues

### Issue: Student not appearing in promotion list
**Cause**: Student may be marked as 'Dropped' or 'Transferred'
**Solution**: Check and update enrollment_status to 'Enrolled' first

### Issue: Grade progression is incorrect
**Cause**: Grade levels may not be in proper order
**Solution**: Verify grade_levels table has correct grade_order values

### Issue: Section assignment errors
**Cause**: No Grade 2 sections created for new school year
**Solution**: Create sections first in `sections.php` before promotion

## Implementation Notes

The promotion system integrates with existing GTBA Portal features:
- Uses existing authentication and authorization
- Follows established database patterns
- Maintains audit logging consistency
- Compatible with existing student management workflows

This enhancement significantly improves the efficiency of year-end student promotion and ensures proper data integrity throughout the re-enrollment process.
