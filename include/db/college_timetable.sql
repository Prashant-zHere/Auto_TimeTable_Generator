-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 22, 2026 at 09:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `college_timetable`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `semester` int(11) NOT NULL,
  `section` varchar(10) NOT NULL,
  `total_students` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `semester`, `section`, `total_students`) VALUES
(7, 'BCA sem 5', 5, 'A', 80),
(8, 'BBA sem 3', 3, 'A', 80),
(9, 'BCA sem 3', 3, 'A', 80),
(10, 'BBA sem 1', 1, 'A', 80);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `leave_date` date DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modify_requests`
--

CREATE TABLE `modify_requests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `timetable_id` int(11) DEFAULT NULL,
  `requested_change` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `admin_comment` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id` varchar(20) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `roll_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `class_id`, `semester`, `roll_number`) VALUES
(6, 16, '1001', 7, 5, '1');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `semester` int(11) NOT NULL,
  `periods_per_week` int(11) DEFAULT 4,
  `subject_type` enum('Theory','Practical','Lab','Project','Elective') DEFAULT 'Theory',
  `credits` int(11) DEFAULT 4,
  `is_lab` tinyint(1) DEFAULT 0,
  `academic_year` varchar(20) DEFAULT '2024-25'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `class_id`, `teacher_id`, `semester`, `periods_per_week`, `subject_type`, `credits`, `is_lab`, `academic_year`) VALUES
(22, '501', 'Maths', 7, NULL, 5, 4, 'Theory', 4, 0, '2025-26'),
(23, '502', 'DSA', 7, NULL, 5, 4, 'Lab', 4, 1, '2025-26'),
(24, '503', 'Software testing', 7, NULL, 5, 4, 'Theory', 4, 0, '2025-26'),
(25, '504', 'Project', 7, 4, 5, 4, 'Project', 4, 0, '2025-26'),
(26, '505', 'Python', 7, 4, 5, 4, 'Practical', 4, 0, '2025-26'),
(27, '506', 'Soft skills', 7, 6, 5, 1, 'Elective', 4, 0, '2025-26'),
(28, '101', 'Soft skills', 10, 7, 1, 3, 'Elective', 4, 0, '2025-26'),
(29, '102', 'Business Maths', 10, 5, 1, 4, 'Theory', 4, 0, '2025-26'),
(30, '103', 'Accounts', 10, 8, 1, 4, 'Theory', 4, 0, '2025-26'),
(31, '104', 'OCM', 10, 9, 1, 4, 'Theory', 4, 0, '2025-26'),
(32, '105', 'Information technology', 10, 6, 1, 4, 'Theory', 4, 0, '2025-26'),
(33, '106', 'Secretial Practice', 10, 7, 1, 4, 'Theory', 4, 0, '2025-26');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience` int(11) DEFAULT 0,
  `max_periods_per_day` int(11) DEFAULT 6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `employee_id`, `department`, `qualification`, `experience`, `max_periods_per_day`) VALUES
(4, 8, '101', 'Information Technology', 'M.Tech', 4, 4),
(5, 9, '102', 'Electronics', 'M.Tech', 0, 6),
(6, 10, '103', 'Computer Science', 'M.Tech', 2, 6),
(7, 11, '104', 'Computer Science', 'M.Tech', 2, 6),
(8, 12, '105', 'Computer Science', 'M.Tech', 2, 6),
(9, 15, '107', 'Mathematics', 'MCA', 5, 6);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT '2024-25'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subjects`
--

INSERT INTO `teacher_subjects` (`id`, `teacher_id`, `subject_id`, `class_id`, `academic_year`) VALUES
(11, 9, 22, 7, '2025-26'),
(12, 8, 23, 7, '2025-26'),
(13, 7, 24, 7, '2025-26'),
(14, 6, 25, 7, '2025-26'),
(15, 5, 26, 7, '2025-26'),
(16, 6, 27, 7, '2025-26'),
(17, 7, 28, 10, '2025-26'),
(18, 5, 29, 10, '2025-26'),
(19, 8, 30, 10, '2025-26'),
(20, 9, 31, 10, '2025-26'),
(21, 6, 32, 10, '2025-26'),
(22, 7, 33, 10, '2025-26');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `day_of_week` int(11) DEFAULT NULL COMMENT '1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
  `slot_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `academic_year` varchar(20) DEFAULT '2024-25',
  `semester` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `class_id`, `day_of_week`, `slot_id`, `subject_id`, `teacher_id`, `is_locked`, `academic_year`, `semester`) VALUES
(151, 7, 2, 53, 22, 9, 0, '2025-26', 5),
(152, 7, 5, 53, 22, 9, 0, '2025-26', 5),
(153, 7, 5, 54, 22, 9, 0, '2025-26', 5),
(154, 7, 4, 57, 22, 9, 0, '2025-26', 5),
(155, 7, 4, 56, 23, 8, 0, '2025-26', 5),
(156, 7, 6, 57, 23, 8, 0, '2025-26', 5),
(157, 7, 6, 53, 23, 8, 0, '2025-26', 5),
(158, 7, 4, 53, 23, 8, 0, '2025-26', 5),
(159, 7, 3, 57, 24, 7, 0, '2025-26', 5),
(160, 7, 5, 57, 24, 7, 0, '2025-26', 5),
(161, 7, 3, 53, 24, 7, 0, '2025-26', 5),
(162, 7, 4, 54, 24, 7, 0, '2025-26', 5),
(163, 7, 3, 54, 25, 6, 0, '2025-26', 5),
(164, 7, 2, 56, 25, 6, 0, '2025-26', 5),
(165, 7, 2, 57, 25, 6, 0, '2025-26', 5),
(166, 7, 6, 54, 25, 6, 0, '2025-26', 5),
(167, 7, 2, 54, 26, 5, 0, '2025-26', 5),
(168, 7, 1, 54, 26, 5, 0, '2025-26', 5),
(169, 7, 3, 56, 26, 5, 0, '2025-26', 5),
(170, 7, 1, 56, 26, 5, 0, '2025-26', 5),
(171, 7, 1, 57, 27, 6, 0, '2025-26', 5),
(172, 7, 6, 56, NULL, NULL, 0, '2025-26', 5),
(173, 7, 1, 53, NULL, NULL, 0, '2025-26', 5),
(174, 7, 5, 56, NULL, NULL, 0, '2025-26', 5),
(175, 7, 1, 55, NULL, NULL, 0, '2025-26', 5),
(176, 7, 2, 55, NULL, NULL, 0, '2025-26', 5),
(177, 7, 3, 55, NULL, NULL, 0, '2025-26', 5),
(178, 7, 4, 55, NULL, NULL, 0, '2025-26', 5),
(179, 7, 5, 55, NULL, NULL, 0, '2025-26', 5),
(180, 7, 6, 55, NULL, NULL, 0, '2025-26', 5);

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `slot_number` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `day_type` enum('weekday','saturday') DEFAULT 'weekday',
  `is_break` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `class_id`, `slot_number`, `start_time`, `end_time`, `day_type`, `is_break`) VALUES
(53, 7, 1, '09:00:00', '10:00:00', 'weekday', 0),
(54, 7, 2, '10:00:00', '11:00:00', 'weekday', 0),
(55, 7, 3, '11:00:00', '11:30:00', 'weekday', 1),
(56, 7, 4, '11:30:00', '12:30:00', 'weekday', 0),
(57, 7, 5, '12:30:00', '13:30:00', 'weekday', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', 'admin', 'admin@gmail.com', 'Daksh Patel', 'admin', '2026-03-21 07:02:02'),
(8, 'priya', '1', 'priya@gmail.com', 'Priya', 'teacher', '2026-03-21 07:04:21'),
(9, 'soham', '1', 'soham@gmail.com', 'Soham', 'teacher', '2026-03-21 07:05:59'),
(10, 'raashi', '1', 'raashi@gmail.com', 'Raashi', 'teacher', '2026-03-21 07:07:41'),
(11, 'rajesh', '1', 'rajesh@gmail.com', 'Rajesh Singh', 'teacher', '2026-03-21 07:12:35'),
(12, 'mehta', '1', 'mehta@gmail.com', 'Priya Mehta', 'teacher', '2026-03-21 07:13:15'),
(15, 'T-sunita', '1', 'sunita@gmail.com', 'Sunita Desai', 'teacher', '2026-03-22 07:51:31'),
(16, 'ST-rahul', '1', 'rahul@gmail.com', 'Rahul kumar', 'student', '2026-03-22 07:53:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `class_name_unique` (`class_name`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `modify_requests`
--
ALTER TABLE `modify_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `timetable_id` (`timetable_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `student_id_unique` (`student_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code_unique` (`subject_code`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `employee_id_unique` (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_allocation` (`teacher_id`,`subject_id`,`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`class_id`,`day_of_week`,`slot_id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_slot` (`class_id`,`slot_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username_unique` (`username`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `modify_requests`
--
ALTER TABLE `modify_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `time_slots` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `modify_requests`
--
ALTER TABLE `modify_requests`
  ADD CONSTRAINT `modify_requests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modify_requests_ibfk_2` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modify_requests_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD CONSTRAINT `teacher_subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_subjects_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD CONSTRAINT `time_slots_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
