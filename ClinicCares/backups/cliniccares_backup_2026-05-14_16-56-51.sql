-- ClinicCares Database Backup
-- Generated: 2026-05-14 16:56:51
-- Server: localhost
-- Database: cliniccares

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table structure for `activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `activity_logs`
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('1', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:11:37'),
('2', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:11:51'),
('3', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:12:28'),
('4', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:12:43'),
('5', NULL, 'LOGIN_FAILED', 'Failed login attempt for: dr.reyes@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:13:59'),
('6', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:15:00'),
('7', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:15:25'),
('8', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:16:35'),
('9', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:21:22'),
('10', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:21:26'),
('11', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:21:31'),
('12', NULL, 'LOGIN_FAILED', 'Failed login attempt for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:21:42'),
('13', NULL, 'LOGIN_FAILED', 'Wrong password for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:24:12'),
('14', NULL, 'LOGIN_FAILED', 'Wrong password for: admin@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:24:16'),
('15', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:29:13'),
('16', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:30:19'),
('17', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 19:31:59'),
('18', '1', 'LOGIN', 'User logged in successfully', NULL, NULL, '2026-05-12 19:46:31'),
('19', NULL, 'LOGIN_FAILED', 'Failed login attempt for: dr.reyes@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 21:19:37'),
('20', NULL, 'LOGIN_FAILED', 'Failed login attempt for: dr.reyes@cliniccare.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 21:19:41'),
('21', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 21:23:52'),
('22', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 22:01:41'),
('23', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 22:01:45'),
('24', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:23:47'),
('25', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:26:01'),
('26', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:26:05'),
('27', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:39:16'),
('28', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:39:22'),
('29', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:03'),
('30', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:07'),
('31', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:12'),
('32', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:19'),
('33', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:55'),
('34', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:47:58'),
('35', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-12 23:51:26'),
('36', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 01:16:33'),
('37', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 01:16:42'),
('38', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 01:17:47'),
('39', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 01:18:27'),
('40', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 01:18:33'),
('41', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 07:46:42'),
('42', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 07:50:21'),
('43', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 07:50:24'),
('44', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 07:54:02'),
('45', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 07:54:10'),
('46', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:03:07'),
('47', '8', 'REGISTER', 'New patient registered', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:04:20'),
('48', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:05:11'),
('49', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:05:34'),
('50', '8', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:05:56'),
('51', '8', 'BOOK_APPOINTMENT', 'Appointment 7 booked with doctor 3 on 2026-05-13 at 08:30:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:11:23'),
('52', '8', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:11:33'),
('53', '8', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:11:44'),
('54', '8', 'BOOK_APPOINTMENT', 'Appointment 8 booked with doctor 1 on 2026-05-14 at 09:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:12:14'),
('55', '8', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:21:49'),
('56', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:21:51'),
('57', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:35:59'),
('58', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:36:22'),
('59', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:42:28'),
('60', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 08:42:32'),
('61', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 09:46:09'),
('62', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 09:49:05'),
('63', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:05:26'),
('64', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:08:15'),
('65', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:08:17'),
('66', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:09:07'),
('67', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:11:27'),
('68', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:11:33'),
('69', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:11:36'),
('70', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:19:21'),
('71', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:19:30'),
('72', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:56:39'),
('73', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 11:05:52'),
('74', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 11:19:25'),
('75', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 11:19:28'),
('76', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 11:54:05'),
('77', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 11:54:09'),
('78', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 12:02:40'),
('79', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 12:02:52'),
('80', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 12:09:51'),
('81', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 12:09:56'),
('82', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:27:14'),
('83', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:27:17'),
('84', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:38:50'),
('85', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:38:53'),
('86', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:40:03'),
('87', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:40:05'),
('88', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:48:06'),
('89', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:48:08'),
('90', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:54:53'),
('91', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 13:56:15'),
('92', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:06:32'),
('93', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:06:36'),
('94', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:10:53'),
('95', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:11:01'),
('96', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:11:11'),
('97', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:11:14'),
('98', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:17:25'),
('99', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:17:30'),
('100', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:35:24');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('101', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:35:28'),
('102', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:36:27'),
('103', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 14:36:32'),
('104', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:02:22'),
('105', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:02:25'),
('106', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:03:14'),
('107', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:03:18'),
('108', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:41:52'),
('109', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:41:56'),
('110', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:46:58'),
('111', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:47:02'),
('112', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:49:03'),
('113', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:49:07'),
('114', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:49:14'),
('115', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:49:21'),
('116', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:53:15'),
('117', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:55:40'),
('118', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 15:55:47'),
('119', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 18:16:24'),
('120', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 18:17:13'),
('121', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 18:17:15'),
('122', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 22:06:18'),
('123', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 22:10:59'),
('124', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 22:38:11'),
('125', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:06:17'),
('126', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:13:24'),
('127', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:13:26'),
('128', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:13:30'),
('129', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:13:32'),
('130', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:13:59'),
('131', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:14:03'),
('132', '5', '2FA_ENABLED', 'User enabled two-factor authentication', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:15:01'),
('133', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:15:10'),
('134', '5', 'LOGIN_2FA_PENDING', '2FA challenge started', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:15:12'),
('135', '5', 'LOGIN_2FA', 'User completed 2FA login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:15:22'),
('136', '5', '2FA_DISABLED', 'User disabled two-factor authentication', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:15:34'),
('137', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:21:14'),
('138', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:24:11'),
('139', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:35:39'),
('140', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:35:42'),
('141', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:35:50'),
('142', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 23:35:57'),
('143', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:01:36'),
('144', '5', '2FA_ENABLED', 'User enabled two-factor authentication', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:02:56'),
('145', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:03:02'),
('146', '5', 'LOGIN_2FA_PENDING', '2FA challenge started', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:03:06'),
('147', '5', 'LOGIN_2FA', 'User completed 2FA login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:03:21'),
('148', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:03:30'),
('149', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:03:33'),
('150', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:06:36'),
('151', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:06:38'),
('152', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:06:42'),
('153', '5', 'LOGIN_2FA_PENDING', '2FA challenge started', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:06:46'),
('154', '5', 'LOGIN_2FA', 'User completed 2FA login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:06:53'),
('155', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:07:24'),
('156', '5', 'LOGIN_2FA_PENDING', '2FA challenge started', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:07:27'),
('157', '5', 'LOGIN_2FA', 'User completed 2FA login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:07:32'),
('158', '5', '2FA_DISABLED', 'User disabled two-factor authentication', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:07:39'),
('159', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-14 00:07:49'),
('160', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 08:58:44'),
('161', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:01:24'),
('162', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:01:33'),
('163', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:02:22'),
('164', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:03:11'),
('165', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:03:37'),
('166', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:04:18'),
('167', '5', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:05:17'),
('168', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:05:20'),
('169', '1', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:06:18'),
('170', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:06:20'),
('171', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:06:54'),
('172', '2', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:06:59'),
('173', '2', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:07:16'),
('174', '5', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 09:07:19'),
('175', '1', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 22:56:42');

-- --------------------------------------------------------
-- Table structure for `appointments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `end_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
  `type` enum('consultation','follow_up','emergency','check_up') DEFAULT 'consultation',
  `notes` text DEFAULT NULL,
  `google_event_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `appointments`
INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `end_time`, `reason`, `status`, `type`, `notes`, `google_event_id`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '2026-05-13', '09:00:00', '09:30:00', 'Regular check-up', 'confirmed', 'check_up', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('2', '1', '2', '2026-05-15', '10:00:00', '10:30:00', 'Follow-up consultation', 'pending', 'follow_up', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('3', '2', '1', '2026-05-14', '11:00:00', '11:30:00', 'Fever and cough', 'confirmed', 'consultation', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('4', '3', '3', '2026-05-17', '14:00:00', '14:30:00', 'Heart palpitations', 'pending', 'consultation', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 20:36:41'),
('5', '1', '1', '2026-05-05', '09:00:00', '09:30:00', 'Blood pressure check', 'completed', 'check_up', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('6', '2', '2', '2026-04-28', '10:00:00', '10:30:00', 'Annual physical', 'completed', 'check_up', NULL, NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('7', '4', '3', '2026-05-13', '08:30:00', '09:00:00', '', 'pending', 'consultation', NULL, NULL, '2026-05-13 08:11:23', '2026-05-13 08:11:23'),
('8', '4', '1', '2026-05-14', '09:00:00', '09:30:00', '', 'pending', 'consultation', NULL, NULL, '2026-05-13 08:12:14', '2026-05-13 10:11:53');

-- --------------------------------------------------------
-- Table structure for `backup_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `triggered_by` enum('manual','scheduled','restore') NOT NULL DEFAULT 'manual',
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `billing`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `billing`;
CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid','cancelled','refunded') DEFAULT 'pending',
  `payment_method` enum('cash','gcash','paymaya','card','insurance','bank_transfer') DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `billing`
INSERT INTO `billing` (`id`, `invoice_number`, `patient_id`, `appointment_id`, `doctor_id`, `subtotal`, `discount`, `tax`, `total`, `amount_paid`, `balance`, `status`, `payment_method`, `payment_reference`, `payment_date`, `due_date`, `notes`, `created_at`, `updated_at`) VALUES
('1', 'INV-2024-0001', '1', '5', '1', '800.00', '0.00', '0.00', '800.00', '800.00', '0.00', 'paid', 'gcash', NULL, '2026-05-05 00:00:00', '2026-05-05', NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('2', 'INV-2024-0002', '2', '6', '2', '600.00', '0.00', '0.00', '600.00', '0.00', '600.00', 'pending', NULL, NULL, NULL, '2026-05-19', NULL, '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('3', 'INV-2026-45592', '4', '7', '3', '1200.00', '0.00', '0.00', '1200.00', '0.00', '1200.00', 'pending', NULL, NULL, NULL, '2026-05-20', NULL, '2026-05-13 08:11:23', '2026-05-13 08:11:23'),
('4', 'INV-2026-86781', '4', '8', '1', '800.00', '0.00', '0.00', '800.00', '0.00', '800.00', 'pending', NULL, NULL, NULL, '2026-05-20', NULL, '2026-05-13 08:12:14', '2026-05-13 08:12:14');

-- --------------------------------------------------------
-- Table structure for `billing_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `billing_items`;
CREATE TABLE `billing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_id` (`billing_id`),
  CONSTRAINT `billing_items_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `billing_items`
INSERT INTO `billing_items` (`id`, `billing_id`, `description`, `quantity`, `unit_price`, `total`) VALUES
('1', '1', 'Consultation Fee - Dr. Maria Reyes', '1', '800.00', '800.00'),
('2', '2', 'Consultation Fee - Dr. Jose Santos', '1', '600.00', '600.00'),
('3', '3', 'Consultation Fee', '1', '1200.00', '1200.00'),
('4', '4', 'Consultation Fee', '1', '800.00', '800.00');

-- --------------------------------------------------------
-- Table structure for `clinics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `clinics`;
CREATE TABLE `clinics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `type` enum('hospital','clinic','specialty','pharmacy','diagnostic') DEFAULT 'clinic',
  `address` varchar(300) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `hours` varchar(200) DEFAULT 'Mon-Fri 8:00 AM – 5:00 PM',
  `accepts_walkin` tinyint(1) DEFAULT 1,
  `telemedicine` tinyint(1) DEFAULT 0,
  `emergency` tinyint(1) DEFAULT 0,
  `rating` decimal(2,1) DEFAULT 4.0,
  `description` text DEFAULT NULL,
  `specializations` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `clinics`
INSERT INTO `clinics` (`id`, `name`, `type`, `address`, `city`, `lat`, `lng`, `phone`, `email`, `website`, `hours`, `accepts_walkin`, `telemedicine`, `emergency`, `rating`, `description`, `specializations`, `is_active`, `created_at`) VALUES
('1', 'Philippine General Hospital', 'hospital', 'Taft Ave, Ermita, Manila', 'Manila', '14.5653000', '120.9925000', '+63 2 8554 8400', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.3', 'The largest government hospital in the Philippines, providing tertiary-level care.', 'Emergency,Internal Medicine,Surgery,Pediatrics,OB-GYN', '1', '2026-05-13 13:31:54'),
('2', 'St. Luke\'s Medical Center – Global City', 'hospital', '32nd St corner 5th Ave, BGC, Taguig', 'Taguig', '14.5477000', '121.0483000', '+63 2 8789 7700', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.8', 'Premier private hospital with world-class facilities and specialist doctors.', 'Cardiology,Oncology,Neurology,Orthopedics,Internal Medicine', '1', '2026-05-13 13:31:54'),
('3', 'Makati Medical Center', 'hospital', '2 Amorsolo St, Legaspi Village, Makati', 'Makati', '14.5547000', '121.0244000', '+63 2 8888 8999', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.7', 'One of Metro Manila\'s top private hospitals offering comprehensive medical services.', 'Cardiology,Oncology,Orthopedics,Neurology,Internal Medicine', '1', '2026-05-13 13:31:54'),
('4', 'The Medical City', 'hospital', 'Ortigas Ave, Pasig', 'Pasig', '14.5876000', '121.0701000', '+63 2 8988 1000', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.6', 'A leading private hospital known for its cancer center and cardiac services.', 'Oncology,Cardiology,Pediatrics,Internal Medicine,Dermatology', '1', '2026-05-13 13:31:54'),
('5', 'Ospital ng Maynila Medical Center', 'hospital', 'Quirino Ave, Malate, Manila', 'Manila', '14.5685000', '120.9897000', '+63 2 8524 6061', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '3.9', 'City government hospital serving the people of Manila with affordable care.', 'Emergency,Surgery,Internal Medicine,Pediatrics', '1', '2026-05-13 13:31:54'),
('6', 'Asian Hospital & Medical Center', 'hospital', '2205 Civic Dr, Filinvest Corporate City, Muntinlupa', 'Muntinlupa', '14.4189000', '121.0392000', '+63 2 8771 9000', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.7', 'JCI-accredited hospital in the south offering top-tier medical services.', 'Cardiology,Neurology,Orthopedics,Oncology,Gastroenterology', '1', '2026-05-13 13:31:54'),
('7', 'Quirino Memorial Medical Center', 'hospital', 'Quirino Ave, Project 4, Quezon City', 'Quezon City', '14.6180000', '121.0640000', '+63 2 8913 4561', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '3.8', 'Government hospital in Quezon City providing accessible healthcare.', 'Emergency,Internal Medicine,Pediatrics,OB-GYN,Surgery', '1', '2026-05-13 13:31:54'),
('8', 'Hi-Precision Diagnostics', 'diagnostic', 'G/F WalterMart Makati, Makati Ave', 'Makati', '14.5580000', '121.0190000', '+63 2 8888 4747', NULL, NULL, 'Mon–Sat 7:00 AM – 7:00 PM, Sun 8:00 AM – 5:00 PM', '1', '0', '0', '4.5', 'Trusted diagnostic center with fast, accurate results across multiple branches.', 'Laboratory,X-Ray,Ultrasound,ECG,MRI', '1', '2026-05-13 13:31:54'),
('9', 'Lung Center of the Philippines', 'specialty', 'Quezon Ave, Quezon City', 'Quezon City', '14.6526000', '121.0337000', '+63 2 8924 6101', NULL, NULL, 'Mon–Fri 7:00 AM – 5:00 PM', '1', '0', '1', '4.4', 'National specialty center for lung and respiratory diseases.', 'Pulmonology,Thoracic Surgery,Internal Medicine,Allergy', '1', '2026-05-13 13:31:54'),
('10', 'National Kidney & Transplant Institute', 'specialty', 'East Ave, Diliman, Quezon City', 'Quezon City', '14.6501000', '121.0484000', '+63 2 8981 0300', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.6', 'The country\'s leading center for kidney diseases and transplant services.', 'Nephrology,Urology,Transplant Surgery,Internal Medicine', '1', '2026-05-13 13:31:54'),
('11', 'Capitol Medical Center', 'hospital', 'Scout Magbanua St, Quezon City', 'Quezon City', '14.6329000', '121.0146000', '+63 2 8372 7777', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.3', 'A well-established private hospital in the heart of Quezon City.', 'Internal Medicine,Surgery,Cardiology,OB-GYN,Pediatrics', '1', '2026-05-13 13:31:54'),
('12', 'HealthNow Digital Clinic', 'clinic', 'Net One Center, BGC, Taguig', 'Taguig', '14.5512000', '121.0476000', '+63 917 888 9000', NULL, NULL, 'Mon–Fri 9:00 AM – 6:00 PM', '1', '0', '0', '4.2', 'Modern digital-first clinic offering both in-person and telemedicine consultations.', 'General Practice,Telemedicine,Wellness', '1', '2026-05-13 13:31:54'),
('13', 'Family Doctors Clinic – Alabang', 'clinic', 'Festival Mall Medical Strip, Alabang, Muntinlupa', 'Muntinlupa', '14.4217000', '121.0373000', '+63 2 8850 1234', NULL, NULL, 'Mon–Sat 8:00 AM – 8:00 PM, Sun 10:00 AM – 6:00 PM', '1', '0', '0', '4.4', 'Friendly neighborhood clinic with complete family health services.', 'General Practice,Pediatrics,Internal Medicine,OB-GYN', '1', '2026-05-13 13:31:54'),
('14', 'Philippine Heart Center', 'specialty', 'East Ave, Quezon City', 'Quezon City', '14.6494000', '121.0493000', '+63 2 8925 2401', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '4.7', 'The national center for cardiovascular diseases and cardiac surgery.', 'Cardiology,Cardiac Surgery,Vascular Surgery,Echocardiography', '1', '2026-05-13 13:31:54'),
('15', 'Pasay City General Hospital', 'hospital', 'Padre Zamora St, Pasay', 'Pasay', '14.5379000', '120.9982000', '+63 2 8833 9999', NULL, NULL, 'Open 24 Hours', '1', '0', '1', '3.7', 'City government hospital serving Pasay and surrounding communities.', 'Emergency,Internal Medicine,Surgery,Pediatrics', '1', '2026-05-13 13:31:54');

-- --------------------------------------------------------
-- Table structure for `doctors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(150) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `clinic_name` varchar(200) DEFAULT NULL,
  `clinic_address` varchar(300) DEFAULT NULL,
  `clinic_city` varchar(100) DEFAULT NULL,
  `clinic_lat` decimal(10,7) DEFAULT NULL,
  `clinic_lng` decimal(10,7) DEFAULT NULL,
  `clinic_phone` varchar(30) DEFAULT NULL,
  `clinic_hours` varchar(200) DEFAULT 'Mon-Fri 8:00 AM – 5:00 PM',
  `accepts_walkin` tinyint(1) DEFAULT 1,
  `telemedicine` tinyint(1) DEFAULT 0,
  `consultation_fee` decimal(10,2) DEFAULT 500.00,
  `available_days` varchar(100) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday',
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '17:00:00',
  `slot_duration` int(11) DEFAULT 30,
  `google_calendar_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `doctors`
INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `license_number`, `department`, `bio`, `clinic_name`, `clinic_address`, `clinic_city`, `clinic_lat`, `clinic_lng`, `clinic_phone`, `clinic_hours`, `accepts_walkin`, `telemedicine`, `consultation_fee`, `available_days`, `start_time`, `end_time`, `slot_duration`, `google_calendar_id`, `created_at`) VALUES
('1', '2', 'Internal Medicine', 'LIC-2024-001', 'Internal Medicine', 'Board-certified internist with 10 years of experience.', 'ClinicCare – Makati Medical Center', 'San Sebastian Village,  Barangay 3,  Hidalgo', 'Makati City', '14.0788582', '121.1514089', '+63 2 8888 8999', 'Mon–Fri 8:00 AM – 6:00 PM, Sat 8:00 AM – 12:00 PM', '1', '1', '800.00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '08:00:00', '17:00:00', '30', NULL, '2026-05-12 18:51:21'),
('2', '3', 'Pediatrics', 'LIC-2024-002', 'Pediatrics', 'Dedicated pediatrician specializing in child health.', 'ClinicCare – Quezon City Pediatric Center', 'Elliptical Rd, Diliman, Quezon City', 'Quezon City', '14.6524000', '121.0374000', '+63 2 8929 7777', 'Mon–Sat 9:00 AM – 5:00 PM', '1', '0', '600.00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '08:00:00', '17:00:00', '30', NULL, '2026-05-12 18:51:21'),
('3', '4', 'Cardiology', 'LIC-2024-003', 'Cardiology', 'Expert cardiologist with advanced training in heart disease.', 'ClinicCare – BGC Heart & Vascular Institute', '5th Ave corner 39th St, Bonifacio Global City', 'Taguig City', '14.5490000', '121.0490000', '+63 2 8789 7700', 'Mon–Fri 7:00 AM – 7:00 PM', '0', '1', '1200.00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '08:00:00', '17:00:00', '30', NULL, '2026-05-12 18:51:21');

-- --------------------------------------------------------
-- Table structure for `medical_records`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `symptoms` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `vital_bp` varchar(20) DEFAULT NULL,
  `vital_temp` varchar(10) DEFAULT NULL,
  `vital_pulse` varchar(10) DEFAULT NULL,
  `vital_weight` varchar(10) DEFAULT NULL,
  `vital_height` varchar(10) DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `record_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `medical_records`
INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `appointment_id`, `diagnosis`, `symptoms`, `treatment`, `notes`, `vital_bp`, `vital_temp`, `vital_pulse`, `vital_weight`, `vital_height`, `follow_up_date`, `record_date`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '5', 'Hypertension Stage 1', 'Headache, dizziness, elevated BP', 'Lifestyle modification and medication', NULL, '140/90', '36.8', '82', '75kg', '170cm', NULL, '2026-05-05', '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('2', '2', '2', '6', 'Upper Respiratory Tract Infection', 'Cough, fever, runny nose', 'Antibiotics and rest', NULL, '110/70', '37.9', '88', '58kg', '160cm', NULL, '2026-04-28', '2026-05-12 18:51:21', '2026-05-12 18:51:21');

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('appointment','billing','prescription','system','reminder') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `notifications`
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `link`, `created_at`) VALUES
('1', '7', 'Appointment Updated', 'Your appointment status changed to: confirmed', 'appointment', '0', '/cliniccare/patient/appointments.php', '2026-05-12 20:36:40'),
('2', '7', 'Appointment Updated', 'Your appointment status changed to: pending', 'appointment', '0', '/cliniccare/patient/appointments.php', '2026-05-12 20:36:41'),
('3', '4', 'New Appointment', 'New appointment booked for May 13, 2026 at 08:30 AM', 'appointment', '0', '/cliniccares/doctor/appointments.php', '2026-05-13 08:11:23'),
('4', '8', 'Appointment Booked', 'Your appointment on May 13, 2026 at 08:30 AM is pending confirmation.', 'appointment', '0', '/cliniccares/patient/appointments.php', '2026-05-13 08:11:23'),
('5', '2', 'New Appointment', 'New appointment booked for May 14, 2026 at 09:00 AM', 'appointment', '0', '/cliniccares/doctor/appointments.php', '2026-05-13 08:12:14'),
('6', '8', 'Appointment Booked', 'Your appointment on May 14, 2026 at 09:00 AM is pending confirmation.', 'appointment', '0', '/cliniccares/patient/appointments.php', '2026-05-13 08:12:14'),
('7', '8', 'Appointment Update', 'Your appointment on May 14, 2026 has been cancelled.', 'appointment', '0', '/cliniccares/patient/appointments.php', '2026-05-13 10:11:49'),
('8', '8', 'Appointment Update', 'Your appointment on May 14, 2026 has been pending.', 'appointment', '0', '/cliniccares/patient/appointments.php', '2026-05-13 10:11:53');

-- --------------------------------------------------------
-- Table structure for `patients`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `insurance_provider` varchar(150) DEFAULT NULL,
  `insurance_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `patients`
INSERT INTO `patients` (`id`, `user_id`, `date_of_birth`, `gender`, `blood_type`, `address`, `city`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `insurance_provider`, `insurance_number`, `created_at`, `lat`, `lng`) VALUES
('1', '5', '1990-05-15', 'male', 'O+', '123 Rizal Street', 'Manila', 'Ana Dela Cruz', '09241234567', NULL, NULL, NULL, '2026-05-12 18:51:21', '14.5995000', '120.9842000'),
('2', '6', '1985-08-22', 'female', 'A+', '456 Bonifacio Ave', 'Quezon City', 'Roberto Santos', '09251234567', NULL, NULL, NULL, '2026-05-12 18:51:21', '14.6760000', '121.0437000'),
('3', '7', '1978-12-10', 'male', 'B+', '789 Luna Street', 'Makati', 'Luisa Reyes', '09261234567', NULL, NULL, NULL, '2026-05-12 18:51:21', '14.5547000', '121.0244000'),
('4', '8', '2000-01-07', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 08:04:18', NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `prescription_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prescription_items`;
CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `prescription_items`
INSERT INTO `prescription_items` (`id`, `prescription_id`, `medication_name`, `dosage`, `frequency`, `duration`, `instructions`, `quantity`) VALUES
('1', '1', 'Amlodipine', '5mg', 'Once daily', '30 days', 'Take in the morning with or without food', '30'),
('2', '1', 'Losartan', '50mg', 'Once daily', '30 days', 'Take at the same time each day', '30'),
('3', '2', 'Amoxicillin', '500mg', 'Three times daily', '7 days', 'Take with food to avoid stomach upset', '21'),
('4', '2', 'Paracetamol', '500mg', 'As needed', '7 days', 'Take for fever above 38°C', '14');

-- --------------------------------------------------------
-- Table structure for `prescriptions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medical_record_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_number` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `medical_record_id` (`medical_record_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `prescriptions`
INSERT INTO `prescriptions` (`id`, `patient_id`, `doctor_id`, `medical_record_id`, `appointment_id`, `prescription_number`, `issue_date`, `valid_until`, `notes`, `status`, `created_at`) VALUES
('1', '1', '1', '1', '5', 'RX-2024-0001', '2026-05-05', '2026-06-04', NULL, 'active', '2026-05-12 18:51:21'),
('2', '2', '2', '2', '6', 'RX-2024-0002', '2026-04-28', '2026-05-05', NULL, 'completed', '2026-05-12 18:51:21');

-- --------------------------------------------------------
-- Table structure for `schedule_overrides`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `schedule_overrides`;
CREATE TABLE `schedule_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `override_date` date NOT NULL,
  `is_available` tinyint(1) DEFAULT 0,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `schedule_overrides_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient') NOT NULL DEFAULT 'patient',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `totp_secret` varchar(100) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `users`
INSERT INTO `users` (`id`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `avatar`, `is_active`, `email_verified`, `verification_token`, `reset_token`, `reset_expires`, `totp_secret`, `two_factor_enabled`, `created_at`, `updated_at`) VALUES
('1', 'admin@cliniccare.com', '$2y$10$ua5Pt6P4oALxBmFhuDCRNu3IBLxgw3/EdZ8sFgWQEw5nnDACX3ZFa', 'admin', 'System', 'Administrator', '09171234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-12 19:28:13'),
('2', 'dr.reyes@cliniccare.com', '$2y$10$9BhFglKg8RZaaUiVsDhA1.Trp8ZH.FKFCPvjfwxO.NkfUneHCaxOa', 'doctor', 'Maria', 'Reyes', '09181234567', '/cliniccares/uploads/avatars/avatar_2_1778688389.jpg', '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-14 00:06:29'),
('3', 'dr.santos@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Jose', 'Santos', '09191234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('4', 'dr.garcia@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Ana', 'Garcia', '09201234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('5', 'juan.dela.cruz@email.com', '$2y$10$9BhFglKg8RZaaUiVsDhA1.Trp8ZH.FKFCPvjfwxO.NkfUneHCaxOa', 'patient', 'Juan', 'Dela Cruz', '09211234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-14 00:07:39'),
('6', 'maria.santos@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Maria', 'Santos', '09221234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('7', 'pedro.reyes@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Pedro', 'Reyes', '09231234567', NULL, '1', '1', NULL, NULL, NULL, NULL, '0', '2026-05-12 18:51:21', '2026-05-12 18:51:21'),
('8', 'test1@gmail.com', '$2y$12$AuEklL8Qbqq2ao7IkmJLh.O0lEv0moe9Pl1DU4umt.PQ56/NT0QHq', 'patient', 'Test', 'Subject', '', NULL, '1', '1', '4db09e36e1936864f23c106c525dfd8171465d286a9035c2e2fb143fc9c62b5b', NULL, NULL, NULL, '0', '2026-05-13 08:04:18', '2026-05-13 08:05:28');

SET FOREIGN_KEY_CHECKS=1;
