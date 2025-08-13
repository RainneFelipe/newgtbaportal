# School Portal Database Schema

## Overview
This database schema supports a comprehensive school portal system with role-based access for students, teachers, administrators, finance personnel, registrars, and principals.

## Core Features Supported

### Student Features
- View tuition fees and payment methods (Bank/GCash details)
- View class schedules
- View final grades and grade history
- View assigned class section

### Teacher Features
- Input final grades for students
- View assigned sections

### Admin Features
- Manage all user accounts
- View audit logs
- Create accounts for any role
- School year management

### Finance Features
- Manage tuition fees for each grade level
- Manage payment methods display

### Registrar Features
- Create student accounts
- Input complete student information

### Principal Features
- Section management (create sections, assign teachers/students)
- Manage class schedules
- Manage curriculum and subjects
- Upload announcements

## Database Tables

### User Management
1. **users** - Core user authentication and roles
2. **student_guardians** - Father, mother, and legal guardian information
3. **students** - Complete student information including LRN, addresses, guardians
4. **teachers** - Teacher information and assignments
5. **audit_logs** - System audit trail

### Academic Management
1. **school_years** - School year management (e.g., '2024-2025')
2. **grade_levels** - All grade levels from Nursery to Grade 10
3. **sections** - Class sections within grade levels (no advisers, no max capacity)
4. **section_teachers** - Many-to-many relationship between sections and teachers
5. **subjects** - Subjects available for each grade
6. **curriculum** - Subject assignments to grades/sections
7. **class_schedules** - Class scheduling system (references users.id for teachers)
8. **student_enrollments** - Student enrollment tracking
9. **student_grades** - Final grades (references users.id for teachers)
10. **announcements** - School announcements system
11. **announcement_attachments** - File attachments for announcements

### Finance Management
1. **tuition_fees** - Fee structure for each grade level
2. **payment_methods** - Available payment methods (Bank, GCash, etc.) for display only

## Grade Levels Supported
- Nursery
- Kindergarten
- Elementary: Grades 1-6
- Junior High School: Grades 7-10

## Student Information Fields
- **Basic Info**: LRN (Learners Reference Number), names, gender, birth details
- **Student Type**: Continuing/Old student or Transfer/New student
- **Addresses**: Present and permanent addresses
- **Guardian Info**: Complete father, mother, and legal guardian details
  - Full names, birthdays, occupations, religions
  - Contact numbers and email addresses
- **Academic**: Current grade level, section, school year
- **Emergency**: Emergency contact information

## Key Business Rules
1. Teachers only input final grades (no quarterly breakdown)
2. Each grade level has different tuition fees and subjects
3. Students can view payment methods and details (bank and GCash information)
4. Audit logging for all administrative actions
5. Role-based access control for all features
6. School year management with start/end dates
7. **NEW:** Multiple teachers can be assigned to a single section
8. **NEW:** One teacher per section can be marked as "primary" teacher
9. **NEW:** No maximum capacity limits on sections
10. **NEW:** Consistent teacher references across all tables (uses users.id)

## Payment Methods Display
- Multiple payment methods supported (Bank transfer, GCash, Maya, Cash)
- Display bank account details and e-wallet information for student reference
- No payment processing or tracking within the portal

## File Locations
- **Migrations**: `/database/migrations/`
  - `users/` - User and student-related tables
  - `academic/` - Academic and curriculum tables
  - `finance/` - Payment and fee tables
- **Seeds**: `/database/seeds/`
  - Default grade levels, school years, payment methods
  - Sample data for testing
