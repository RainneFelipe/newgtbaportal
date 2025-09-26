# GTBA School Portal - AI Coding Instructions

## Architecture Overview

This is a **role-based PHP school management system** using a pseudo-MVC pattern with session-based authentication. The system serves 6 distinct user roles (`student`, `teacher`, `admin`, `finance`, `registrar`, `principal`) with dedicated modules and permissions.

### Core Components
- **Authentication Layer**: Session-based with `includes/auth_check.php` middleware
- **Database Layer**: PDO-based with prepared statements (`config/database.php`, `classes/User.php`)
- **Role-based Access**: JSON permissions stored in `roles` table, checked via `checkRole()` and `hasPermission()`
- **UI Framework**: Custom CSS framework with professional blue theme and responsive sidebar navigation

## Essential Development Patterns

### File Structure Convention
```
{role}/
├── dashboard.php          # Role landing page
├── {feature}.php          # Feature-specific pages
shared/                    # Cross-role features
includes/
├── auth_check.php         # MUST be first require in protected pages
├── header.php             # Dynamic sidebar based on $_SESSION['role']
├── footer.php
classes/User.php           # Core user operations and audit logging
```

### Authentication Flow
Every protected page MUST follow this pattern:
```php
<?php
require_once '../includes/auth_check.php';

// Role-specific access control
if (!checkRole('role_name')) {  // or checkRole(['role1', 'role2'])
    header('Location: ../index.php');
    exit();
}
```

### Database Connection Pattern
```php
require_once '../config/database.php';
$database = new Database();
$db = $database->connect();

// Always use prepared statements
$stmt = $db->prepare("SELECT * FROM table WHERE column = ?");
$stmt->execute([$value]);
```

### Security Requirements
- **XSS Protection**: Always use `htmlspecialchars()` for output: `<?php echo htmlspecialchars($data); ?>`
- **SQL Injection**: Only PDO prepared statements - NO string concatenation in queries
- **File Uploads**: Store in `uploads/{category}/` with sanitized names and MIME validation
- **Session Management**: `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['permissions']`

## Role-Specific Conventions

### Navigation & Layout
Each page sets:
```php
$page_title = "Page Title - GTBA Portal";
$base_url = "../";  // Relative path to root for assets/includes
```

### Role Access Patterns
- **Students**: View-only (grades, schedule, tuition, payments)
- **Teachers**: Manage assigned sections and grade input
- **Admin**: Full user management and system oversight
- **Finance**: Payment verification and tuition management
- **Registrar**: Student registration and academic records
- **Principal**: School management (sections, schedules, curriculum)

### Database Table Relationships
Key relationships to understand:
- `users` → `students` (1:1 via `user_id`)
- `students` → `student_guardians` (3:1 - father/mother/legal guardian)
- `sections` → `section_teachers` (M:N with `is_primary` flag)
- `school_years.is_active = 1` (current year filter)
- All created records reference `created_by` → `users.id`

## CSS & UI Patterns

### Theme System
```css
:root {
    --primary-blue: #2E86AB;
    --light-blue: #E8F4F8;
    --accent-blue: #87CEEB;
    --dark-blue: #1B4F72;
}
```

### Common UI Components
- `.message.success/error/info/warning` for flash messages
- `.dashboard-card` for feature tiles
- `.data-table` for tabular data
- `.form-grid` for responsive forms
- `.role-badge` with dynamic classes (`.role-{role_name}`)

## Development Workflows

### Adding New Features
1. Create in appropriate `{role}/` directory
2. Follow auth check pattern with role validation
3. Use `$base_url` for all asset/include paths
4. Implement audit logging via `User->logAudit()` for data changes
5. Add navigation items to `includes/header.php` role-specific sections

### Form Processing Pattern
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        // Process form data with prepared statements
        // Log audit trail
        $db->commit();
        $_SESSION['success'] = 'Success message';
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = 'Error message';
        error_log("Error: " . $e->getMessage());
    }
    header('Location: current_page.php');
    exit();
}
```

### File Upload Pattern
Store uploads in `uploads/{category}/` with format: `{prefix}_{id}_{timestamp}_{hash}.{ext}`

## Integration Points

### Session Data Available
- `$_SESSION['user_id']` - Primary key for current user
- `$_SESSION['username']` - Login username
- `$_SESSION['role']` - Role name (student, teacher, etc.)
- `$_SESSION['role_display']` - Human-readable role name
- `$_SESSION['permissions']` - JSON decoded permissions array

### Student-Specific Context
Students have additional context in `students` table:
- `current_grade_level_id` - Links to grade levels
- `current_section_id` - Current class assignment
- `current_school_year_id` - Academic year enrollment

### Common Queries
- Active school year: `WHERE is_active = 1`
- Student info: Join `users` → `students` → `grade_levels` → `sections`
- Role-based data: Always filter by user's role and permissions

## Critical Business Logic

### Grade Management
- Only final grades stored (no quarterly breakdown)
- Teachers input grades for their assigned sections only
- Grade calculations include GWA (General Weighted Average)

### Payment System
- Display-only payment methods (no processing)
- Payment proof uploads with verification workflow
- Complex installment tracking with down payments and monthly fees
- Payment reminders based on student preferences

### Academic Calendar
- School years have `start_date`, `end_date`, `is_active`
- Sections belong to specific school years and grade levels
- Student promotions handled through registrar module

Always verify role permissions before displaying data or allowing actions. The system maintains comprehensive audit logs for all administrative actions.