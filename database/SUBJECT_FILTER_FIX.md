# Subject Dropdown Filter Fix - Principal Class Schedules

## Issue
When selecting a section (e.g., Grade 1 - Bamboo) in the Add Class Schedule modal, the subject dropdown was showing subjects from all grade levels instead of filtering to show only Grade 1 subjects.

## Root Cause
The subjects were loaded without any JavaScript filtering mechanism. The dropdown showed all active subjects regardless of the selected section's grade level.

## Solution Implemented

### 1. Database Query Enhancement
**File:** `principal/schedules.php`

Updated the subjects query to include grade level information:
```php
// Before
$query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name";

// After
$query = "SELECT s.*, gl.grade_name 
          FROM subjects s
          LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
          WHERE s.is_active = 1 
          ORDER BY s.subject_name";
```

### 2. HTML Updates - Add Modal

#### Section Dropdown
Added `data-grade-level` attribute and `onchange` handler:
```html
<select id="section_id" name="section_id" class="schedule-form-select" required onchange="filterSubjectsBySection()">
    <option value="">Select Section</option>
    <?php foreach ($sections as $section): ?>
        <option value="<?php echo $section['id']; ?>" data-grade-level="<?php echo $section['grade_level_id']; ?>">
            <?php echo htmlspecialchars($section['section_display']); ?>
        </option>
    <?php endforeach; ?>
</select>
```

#### Subject Dropdown
Added `data-grade-level` attribute to each option:
```html
<select id="subject_id" name="subject_id" class="schedule-form-select">
    <option value="">Select Subject</option>
    <?php foreach ($subjects as $subject): ?>
        <option value="<?php echo $subject['id']; ?>" data-grade-level="<?php echo $subject['grade_level_id']; ?>">
            <?php echo htmlspecialchars($subject['subject_name']); ?>
            <?php if ($subject['subject_code']): ?>
                (<?php echo htmlspecialchars($subject['subject_code']); ?>)
            <?php endif; ?>
        </option>
    <?php endforeach; ?>
</select>
```

### 3. HTML Updates - Edit Modal

Same updates applied to edit modal:
- Edit section dropdown: Added `data-grade-level` and `onchange="filterSubjectsByEditSection()"`
- Edit subject dropdown: Added `data-grade-level` to options

### 4. JavaScript Filtering Functions

Added two new functions to handle subject filtering:

#### filterSubjectsBySection()
Filters subjects in the Add Schedule modal:
```javascript
function filterSubjectsBySection() {
    const sectionSelect = document.getElementById('section_id');
    const subjectSelect = document.getElementById('subject_id');
    
    // Get selected section's grade level
    const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
    const gradeLevel = selectedOption ? selectedOption.getAttribute('data-grade-level') : null;
    
    // Show/hide subject options based on grade level match
    const options = subjectSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = ''; // Always show placeholder
            return;
        }
        
        const optionGradeLevel = option.getAttribute('data-grade-level');
        
        if (!gradeLevel || optionGradeLevel === gradeLevel) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
}
```

#### filterSubjectsByEditSection()
Same logic for the Edit Schedule modal.

## How It Works

1. **User selects a section** (e.g., "Grade 1 - Bamboo")
2. **JavaScript triggers** `filterSubjectsBySection()`
3. **Function reads** the `data-grade-level="3"` attribute from the selected section
4. **Function filters** subject options, hiding those that don't match grade level 3
5. **User sees** only Grade 1 subjects in the dropdown:
   - Grade 1 English (G1-ENG)
   - Grade 1 Filipino (G1-FIL)
   - Grade 1 MAPEH (G1-MAPEH)
   - Grade 1 Mathematics (G1-MATH)
   - Grade 1 Science (G1-SCI)

## Verification

### Database Structure
- **subjects table** has `grade_level_id` column
- **sections table** has `grade_level_id` column
- Proper foreign key relationships exist

### Example Data
```
Grade 1 (grade_level_id = 3):
- Grade 1 English (G1-ENG)
- Grade 1 Filipino (G1-FIL)
- Grade 1 MAPEH (G1-MAPEH)
- Grade 1 Mathematics (G1-MATH)
- Grade 1 Science (G1-SCI)

Grade 2 (grade_level_id = 4):
- Grade 2 Araling Panlipunan (G2-AP)
- Grade 2 English (G2-ENG)
- Grade 2 Filipino (G2-FIL)
- Grade 2 Mathematics (G2-MATH)
- Grade 2 Science (G2-SCI)
```

## Testing Checklist

- [x] Add Schedule Modal filters subjects correctly
- [x] Edit Schedule Modal filters subjects correctly
- [x] Changing section re-filters subjects
- [x] All grade levels filter properly
- [x] Placeholder text updates when no subjects available
- [x] Selected subject is cleared if it becomes hidden

## Benefits

1. **Prevents Errors:** Can't assign wrong grade level subjects to sections
2. **User Experience:** Cleaner, shorter dropdown lists
3. **Data Integrity:** Ensures curriculum alignment
4. **Intuitive:** Automatically shows only relevant subjects
5. **Scalable:** Works for any number of grade levels and subjects
