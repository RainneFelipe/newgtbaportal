# Automatic Student Promotion System

## Overview
The system automatically promotes students to the next grade level when they meet ALL the following criteria:
1. ✅ Student has **"Enrolled"** status (meaning tuition is fully paid and verified)
2. ✅ All required subject grades have been entered by teachers
3. ✅ All grades are **passing** (75 or above)

## How It Works

### Triggers Created
Two database triggers monitor grade entries:
- `auto_promote_student_on_grade_save` (fires on INSERT)
- `auto_promote_student_on_grade_update` (fires on UPDATE)

### Automatic Actions

**When a teacher saves/updates a student grade:**

1. **Check Enrollment Status**
   - Only proceeds if student status = "Enrolled" (fully paid + verified)
   - If "Pending Payment" → No promotion (student must pay first)

2. **Count Required Subjects**
   - Gets all required subjects from curriculum for student's grade level
   - Example: Grade 1 might have 5 required subjects

3. **Check All Grades Entered**
   - Counts how many grades the student has
   - Compares to required subject count

4. **Check All Passing**
   - Counts grades ≥ 75 (passing grade)
   - Must equal total required subjects

5. **Promote Student**
   - If all conditions met:
     - **Has next grade?** → Promotes to next grade (e.g., Grade 1 → Grade 2)
     - **No next grade?** → Marks as "Graduated" (e.g., Grade 10 → Graduated)
   - Clears section assignment (student needs new section for new grade)

## Business Logic

### Passing Grade: 75
Students need a minimum of 75% in ALL required subjects to be promoted.

### Promotion Flow Examples

**Example 1: Grade 3 Student**
```
Student: John Doe
Current Grade: Grade 3
Payment Status: Enrolled ✓
Required Subjects: 5
Grades Entered: 5
All Grades ≥ 75: Yes ✓

Result: Promoted to Grade 4
```

**Example 2: Grade 10 Student (Last Grade)**
```
Student: Jane Smith
Current Grade: Grade 10
Payment Status: Enrolled ✓
Required Subjects: 7
Grades Entered: 7
All Grades ≥ 75: Yes ✓

Result: Status changed to "Graduated"
```

**Example 3: Student with Failing Grade**
```
Student: Bob Johnson
Current Grade: Grade 5
Payment Status: Enrolled ✓
Required Subjects: 6
Grades Entered: 6
Math Grade: 70 (FAILING)

Result: NOT promoted (has failing grade)
```

**Example 4: Unpaid Student**
```
Student: Alice Williams
Current Grade: Grade 2
Payment Status: Pending Payment ✗
Required Subjects: 5
Grades Entered: 5
All Grades ≥ 75: Yes

Result: NOT promoted (tuition not paid)
```

## Integration with Other Systems

### Payment System
- Payment trigger sets status to "Enrolled" when fully paid
- Promotion trigger only works on "Enrolled" students
- **Flow:** Payment Verified → Status = Enrolled → Grades Added → Auto Promotion

### Grading System
- Teachers enter/update grades in `student_grades` table
- Each grade save triggers promotion check
- Works for both initial grade entry and grade updates

### Graduation
- When student completes final grade level (e.g., Grade 10)
- No next grade available → Status automatically set to "Graduated"
- Graduated students won't be promoted further

## Manual Override
Registrars can still manually:
- Change student status via "Change Status" button
- Assign students to specific grade levels
- Handle special cases (transfers, retention, etc.)

## Technical Details

### Database Tables Involved
- `students` - Student records with current_grade_level_id and enrollment_status
- `student_grades` - Grade records (triggers fire here)
- `grade_levels` - Grade level definitions with grade_order
- `curriculum` - Required subjects per grade level
- `student_payments` - Payment records (separate trigger handles enrollment)

### Key Fields
- `students.enrollment_status` - Must be 'Enrolled'
- `students.current_grade_level_id` - Gets updated to next grade
- `students.current_section_id` - Cleared on promotion (set to NULL)
- `grade_levels.grade_order` - Determines next grade (order + 1)
- `student_grades.final_grade` - Must be ≥ 75

## Testing the System

To test automatic promotion:

1. **Set up a test student:**
   ```sql
   -- Ensure student is enrolled and has payment verified
   UPDATE students SET enrollment_status = 'Enrolled' WHERE id = <student_id>;
   ```

2. **Enter passing grades:**
   ```sql
   -- Teacher enters all required subject grades (≥ 75)
   INSERT INTO student_grades (student_id, subject_id, final_grade, ...) VALUES (...);
   ```

3. **Verify promotion:**
   ```sql
   -- Check if student was promoted
   SELECT 
       CONCAT(first_name, ' ', last_name) as name,
       current_grade_level_id,
       enrollment_status
   FROM students 
   WHERE id = <student_id>;
   ```

## Date Implemented
November 24, 2025

## Summary
✅ Automatic promotion on grade completion  
✅ Payment verification required  
✅ All grades must be passing (≥75)  
✅ Auto-graduation for final grade  
✅ Section cleared for new grade assignment  
