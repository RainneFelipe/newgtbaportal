-- Create rooms table and populate with room numbers
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    room_name VARCHAR(100),
    floor INT,
    capacity INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert rooms 101-105 (1st floor)
INSERT INTO rooms (room_number, room_name, floor, capacity) VALUES
('101', 'Room 101', 1, 30),
('102', 'Room 102', 1, 30),
('103', 'Room 103', 1, 30),
('104', 'Room 104', 1, 30),
('105', 'Room 105', 1, 30);

-- Insert rooms 201-205 (2nd floor)
INSERT INTO rooms (room_number, room_name, floor, capacity) VALUES
('201', 'Room 201', 2, 30),
('202', 'Room 202', 2, 30),
('203', 'Room 203', 2, 30),
('204', 'Room 204', 2, 30),
('205', 'Room 205', 2, 30);

-- Insert rooms 301-305 (3rd floor)
INSERT INTO rooms (room_number, room_name, floor, capacity) VALUES
('301', 'Room 301', 3, 30),
('302', 'Room 302', 3, 30),
('303', 'Room 303', 3, 30),
('304', 'Room 304', 3, 30),
('305', 'Room 305', 3, 30);

-- Insert rooms 401-405 (4th floor)
INSERT INTO rooms (room_number, room_name, floor, capacity) VALUES
('401', 'Room 401', 4, 30),
('402', 'Room 402', 4, 30),
('403', 'Room 403', 4, 30),
('404', 'Room 404', 4, 30),
('405', 'Room 405', 4, 30);

-- Insert rooms 501-505 (5th floor)
INSERT INTO rooms (room_number, room_name, floor, capacity) VALUES
('501', 'Room 501', 5, 30),
('502', 'Room 502', 5, 30),
('503', 'Room 503', 5, 30),
('504', 'Room 504', 5, 30),
('505', 'Room 505', 5, 30);

-- Verify the insert
SELECT room_number, room_name, floor FROM rooms ORDER BY floor, room_number;
