-- Clear all schedules for 2025-2026 school year

-- Show current schedule counts before deletion
SELECT 'Before deletion:' as status;
SELECT COUNT(*) as total_schedules FROM class_schedules WHERE school_year_id = 2;

-- Delete all class schedules for 2025-2026
DELETE FROM class_schedules WHERE school_year_id = 2;

-- Show counts after deletion
SELECT 'After deletion:' as status;
SELECT COUNT(*) as total_schedules FROM class_schedules WHERE school_year_id = 2;

SELECT 'All schedules for 2025-2026 school year have been cleared.' as message;
