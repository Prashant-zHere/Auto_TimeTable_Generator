-- Create Database
CREATE DATABASE IF NOT EXISTS college_timetable;
USE college_timetable;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teachers Table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    employee_id VARCHAR(20) UNIQUE,
    department VARCHAR(100),
    qualification VARCHAR(100),
    experience INT,
    max_periods_per_day INT DEFAULT 6,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Students Table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    student_id VARCHAR(20) UNIQUE,
    class_id INT,
    semester INT,
    roll_number VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Classes Table
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(50) UNIQUE,
    semester INT,
    section VARCHAR(10),
    total_students INT
);

-- Subjects Table
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(20) UNIQUE,
    subject_name VARCHAR(100),
    class_id INT,
    semester INT,
    periods_per_week INT DEFAULT 4,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Teacher Subjects (Allocation)
CREATE TABLE teacher_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    subject_id INT,
    class_id INT,
    academic_year VARCHAR(20),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_allocation (teacher_id, subject_id, class_id)
);

-- Time Slots Table
CREATE TABLE time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slot_number INT UNIQUE,
    start_time TIME,
    end_time TIME,
    day_type ENUM('weekday', 'saturday') DEFAULT 'weekday'
);

-- Timetable Table
CREATE TABLE timetable (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    day_of_week INT COMMENT '1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
    slot_id INT,
    subject_id INT,
    teacher_id INT,
    is_locked BOOLEAN DEFAULT FALSE,
    academic_year VARCHAR(20),
    semester INT,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY unique_slot (class_id, day_of_week, slot_id)
);

-- Leave Requests Table
CREATE TABLE leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    leave_date DATE,
    slot_id INT,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Timetable Modify Requests
CREATE TABLE modify_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    timetable_id INT,
    requested_change TEXT,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (timetable_id) REFERENCES timetable(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

INSERT INTO users (username, password, email, full_name, role) VALUES
('admin', 'admin', 'admin@college.edu', 'Abhay Sharma', 'admin');

INSERT INTO time_slots (slot_number, start_time, end_time) VALUES
(1, '09:00', '10:00'),
(2, '10:00', '11:00'),
(3, '11:00', '12:00'),
(4, '12:00', '13:00'),
(5, '14:00', '15:00'),
(6, '15:00', '16:00'),
(7, '16:00', '17:00');

INSERT INTO classes (class_name, semester, section) VALUES
('BCA Sem 3', 3, 'A'),
('BCA Sem 5', 5, 'A'),
('MCA Sem 1', 1, 'A');