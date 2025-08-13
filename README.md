# GTBA School Portal

A comprehensive PHP-based student portal system for Golden Treasure Baptist Academy featuring role-based access control and modern responsive design.

## Features

### Student Features
- ✅ **Login System** - Secure authentication with role-based access
- ✅ **Dashboard** - Overview of student information and quick access to features
- ✅ **View Grades** - Final grades display with GWA calculation
- ✅ **Class Schedule** - Weekly schedule with teacher and room information
- ✅ **Tuition & Fees** - Fee breakdown and payment methods display
- ✅ **Section Information** - View section details and classmates
- 🔄 **Announcements** - School announcements (coming soon)
- 🔄 **Profile Management** - Update personal information (coming soon)

### Design Features
- 🎨 **Light Blue & White Theme** - Clean, professional appearance
- 📱 **Responsive Design** - Works on desktop, tablet, and mobile
- ♿ **Accessible** - High contrast black text for readability
- ⚡ **Fast Loading** - Optimized CSS and minimal JavaScript

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Authentication**: Session-based with password hashing
- **Security**: PDO prepared statements, input validation

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or XAMPP/Laragon for development

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   # Place in your web server directory
   # For Laragon: C:\laragon\www\NewGTBAPortal
   # For XAMPP: C:\xampp\htdocs\NewGTBAPortal
   ```

2. **Create Database**
   ```sql
   CREATE DATABASE newgtbaportal;
   ```

3. **Import Database Schema**
   ```bash
   # Run the schema setup script
   mysql -u root -p newgtbaportal < 000_complete_schema_setup.sql
   
   # Then run the seed data script
   mysql -u root -p newgtbaportal < 000_complete_seed_setup.sql
   ```

4. **Configure Database Connection**
   - Edit `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'newgtbaportal');
     define('DB_USER', 'root');        // Your MySQL username
     define('DB_PASS', '');            // Your MySQL password
     ```

5. **Set File Permissions** (Linux/Mac only)
   ```bash
   chmod 755 NewGTBAPortal
   chmod 644 NewGTBAPortal/*.php
   ```

6. **Access the Portal**
   - Open your browser and go to: `http://localhost/NewGTBAPortal`
   - For Laragon: `http://newgtbaportal.test` (if using virtual hosts)

## Default Login Credentials

The system comes with pre-configured admin accounts:

| Role | Username | Password | Purpose |
|------|----------|----------|---------|
| Admin | admin | password | System administration |
| Finance | finance | password | Financial management |
| Registrar | registrar | password | Student registration |
| Principal | principal | password | School management |

**⚠️ Important**: Change these default passwords immediately in production!

## Database Structure

The system includes comprehensive tables for:

- **User Management**: Users, roles, permissions
- **Academic Structure**: School years, grade levels, sections, subjects
- **Student Information**: Complete student profiles with guardian info
- **Academic Tracking**: Enrollments, grades, schedules
- **Finance**: Tuition fees and payment methods
- **Communication**: Announcements system
- **Audit**: Complete audit trail

## Project Structure

```
NewGTBAPortal/
├── assets/
│   └── css/
│       └── style.css          # Main stylesheet
├── auth/
│   ├── login_process.php      # Login handling
│   └── logout.php             # Logout handling
├── classes/
│   └── User.php               # User management class
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── auth_check.php         # Authentication middleware
│   ├── header.php             # Common header
│   └── footer.php             # Common footer
├── student/
│   ├── dashboard.php          # Student dashboard
│   ├── grades.php             # Grades view
│   ├── schedule.php           # Class schedule
│   ├── tuition.php            # Tuition & fees
│   └── section.php            # Section information
├── index.php                  # Login page
├── dashboard.php              # Main dashboard
├── 000_complete_schema_setup.sql
├── 000_complete_seed_setup.sql
└── DATABASE_SCHEMA.md
```

## Security Features

- ✅ **Password Hashing** - bcrypt password hashing
- ✅ **SQL Injection Protection** - PDO prepared statements
- ✅ **Session Management** - Secure session handling
- ✅ **Role-Based Access** - Comprehensive permission system
- ✅ **Audit Logging** - Complete user action tracking
- ✅ **Input Validation** - Server-side validation
- ✅ **XSS Protection** - HTML escaping

## Customization

### Changing Colors
Edit `assets/css/style.css` and modify the CSS variables:
```css
:root {
    --primary-blue: #87CEEB;      /* Main blue color */
    --light-blue: #E0F6FF;       /* Light background */
    --dark-blue: #4682B4;        /* Dark accent */
    /* Add your custom colors */
}
```

### Adding New Features
1. Create new PHP files in appropriate directories
2. Use the authentication middleware: `require_once '../includes/auth_check.php'`
3. Check user roles: `checkRole('student')` or `hasPermission('permission_name')`
4. Follow the existing code structure and styling

## Browser Support

- ✅ Chrome 60+
- ✅ Firefox 55+
- ✅ Safari 12+
- ✅ Edge 79+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Development Status

**Current Version**: 1.0.0 (Student Portal Core)

### Completed ✅
- Authentication system
- Student dashboard
- Grades viewing
- Schedule display
- Tuition information
- Section information
- Responsive design

### Coming Soon 🔄
- Teacher portal
- Admin panel
- Finance module
- Registrar tools
- Principal dashboard
- Announcements system
- Profile management
- Payment tracking

## Support

For technical support or questions:
- Review the `DATABASE_SCHEMA.md` file for database structure
- Check PHP error logs for debugging
- Ensure database connection is properly configured
- Verify file permissions are set correctly

## License

This project is developed for Golden Treasure Baptist Academy. All rights reserved.

---

**Golden Treasure Baptist Academy**  
Student Portal System v1.0.0
