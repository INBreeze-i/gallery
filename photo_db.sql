-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql:3306
-- Generation Time: Jul 24, 2025 at 09:38 AM
-- Server version: 8.0.42
-- PHP Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `photo_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `category_id`, `title`, `description`, `date`, `created_at`) VALUES
(1, 1, 'การแข่งขันฟุตบอล', 'การแข่งขันฟุตบอลประจำปี', '2024-01-15', '2025-07-24 08:27:36'),
(2, 2, 'นิทรรศการศิลปะ', 'แสดงผลงานศิลปะของนักเรียน', '2024-01-20', '2025-07-24 08:27:36'),
(3, 3, 'โครงการวิทยาศาสตร์', 'การนำเสนอโครงการวิทยาศาสตร์', '2024-01-25', '2025-07-24 08:27:36'),
(4, 4, 'คอนเสิร์ตดนตรี', 'การแสดงดนตรีประจำเทอม', '2024-01-30', '2025-07-24 08:27:36'),
(5, 5, 'สัมมนาการเรียนรู้', 'สัมมนาเทคนิคการเรียนรู้ใหม่', '2024-02-05', '2025-07-24 08:27:36'),
(6, 6, 'งานอาสาสมัคร', 'กิจกรรมช่วยเหลือชุมชน', '2024-02-10', '2025-07-24 08:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `activity_images`
--

CREATE TABLE `activity_images` (
  `id` int NOT NULL,
  `activity_id` int DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_order` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_images`
--

INSERT INTO `activity_images` (`id`, `activity_id`, `image_path`, `image_order`, `created_at`) VALUES
(1, 1, 'uploads/sport1.jpg', 1, '2025-07-24 08:27:36'),
(2, 1, 'uploads/sport2.jpg', 2, '2025-07-24 08:27:36'),
(3, 1, 'uploads/sport3.jpg', 3, '2025-07-24 08:27:36'),
(4, 1, 'uploads/sport4.jpg', 4, '2025-07-24 08:27:36'),
(5, 2, 'uploads/art1.jpg', 1, '2025-07-24 08:27:36'),
(6, 2, 'uploads/art2.jpg', 2, '2025-07-24 08:27:36'),
(7, 2, 'uploads/art3.jpg', 3, '2025-07-24 08:27:36'),
(8, 2, 'uploads/art4.jpg', 4, '2025-07-24 08:27:36'),
(9, 3, 'uploads/science1.jpg', 1, '2025-07-24 08:27:36'),
(10, 3, 'uploads/science2.jpg', 2, '2025-07-24 08:27:36'),
(11, 3, 'uploads/science3.jpg', 3, '2025-07-24 08:27:36'),
(12, 3, 'uploads/science4.jpg', 4, '2025-07-24 08:27:36'),
(13, 4, 'uploads/music1.jpg', 1, '2025-07-24 08:27:36'),
(14, 4, 'uploads/music2.jpg', 2, '2025-07-24 08:27:36'),
(15, 4, 'uploads/music3.jpg', 3, '2025-07-24 08:27:36'),
(16, 4, 'uploads/music4.jpg', 4, '2025-07-24 08:27:36'),
(17, 5, 'uploads/learn1.jpg', 1, '2025-07-24 08:27:36'),
(18, 5, 'uploads/learn2.jpg', 2, '2025-07-24 08:27:36'),
(19, 5, 'uploads/learn3.jpg', 3, '2025-07-24 08:27:36'),
(20, 5, 'uploads/learn4.jpg', 4, '2025-07-24 08:27:36'),
(21, 6, 'uploads/social1.jpg', 1, '2025-07-24 08:27:36'),
(22, 6, 'uploads/social2.jpg', 2, '2025-07-24 08:27:36'),
(23, 6, 'uploads/social3.jpg', 3, '2025-07-24 08:27:36'),
(24, 6, 'uploads/social4.jpg', 4, '2025-07-24 08:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','moderator','editor') DEFAULT 'editor',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `status`, `last_login`, `login_attempts`, `locked_until`, `remember_token`, `remember_expires`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gallery.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active', '2025-07-24 08:37:37', 0, NULL, NULL, NULL, '2025-07-24 08:27:36', '2025-07-24 08:37:37'),
(2, 'editor', 'editor@gallery.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Editor', 'editor', 'active', NULL, 0, NULL, NULL, NULL, '2025-07-24 08:27:36', '2025-07-24 08:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `albums`
--

CREATE TABLE `albums` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `view_count` int DEFAULT '0',
  `date_created` date NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `albums`
--

INSERT INTO `albums` (`id`, `category_id`, `title`, `description`, `cover_image`, `status`, `view_count`, `date_created`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 2, 'วิ่งๆ', 'วิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆวิ่งๆ', NULL, 'active', 3, '2025-07-23', 1, '2025-07-24 09:32:33', '2025-07-24 09:33:08');

-- --------------------------------------------------------

--
-- Table structure for table `album_categories`
--

CREATE TABLE `album_categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT 'fas fa-folder',
  `color` varchar(20) DEFAULT 'bg-blue-600',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `album_categories`
--

INSERT INTO `album_categories` (`id`, `name`, `description`, `icon`, `color`, `created_at`) VALUES
(1, 'กิจกรรมผลิตบัณฑิต', 'กิจกรรมผลิตบัณฑิต', 'fas fa-futbol', 'bg-blue-600', '2025-07-24 08:27:36'),
(2, 'กิจกรรมการบริการวิชาการ', 'กิจกรรมการบริการวิชาการ', 'fas fa-palette', 'bg-purple-600', '2025-07-24 08:27:36'),
(3, 'กิจกรรมด้านผลิตและพัฒนาครู', 'กิจกรรมด้านผลิตและพัฒนาครู', 'fas fa-flask', 'bg-green-600', '2025-07-24 08:27:36'),
(4, 'กิจกรรมการวิจัย', 'กิจกรรมการวิจัย', 'fas fa-music', 'bg-pink-600', '2025-07-24 08:27:36'),
(5, 'กิจกรรมด้านศิลปะและวัฒนธรรม', 'กิจกรรมด้านศิลปะและวัฒนธรรม', 'fas fa-graduation-cap', 'bg-indigo-600', '2025-07-24 08:27:36'),
(6, 'กิจกรรมด้านการบริหารจัดการ', 'กิจกรรมด้านการบริหารจัดการ', 'fas fa-users', 'bg-yellow-600', '2025-07-24 08:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `album_images`
--

CREATE TABLE `album_images` (
  `id` int NOT NULL,
  `album_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(200) DEFAULT '',
  `image_description` text,
  `image_order` int DEFAULT '1',
  `is_cover` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `album_images`
--

INSERT INTO `album_images` (`id`, `album_id`, `image_path`, `image_title`, `image_description`, `image_order`, `is_cover`, `created_at`) VALUES
(14, 8, 'uploads/albums/album_8/img_20250724093233_ba5d72bd.webp', '', '', 1, 0, '2025-07-24 09:32:33'),
(15, 8, 'uploads/albums/album_8/img_20250724093233_9b1afbf1.webp', '', '', 2, 0, '2025-07-24 09:32:33'),
(16, 8, 'uploads/albums/album_8/img_20250724093233_cf6cb1c0.webp', '', '', 3, 0, '2025-07-24 09:32:33'),
(17, 8, 'uploads/albums/album_8/img_20250724093233_fa34470f.webp', '', '', 4, 0, '2025-07-24 09:32:34'),
(18, 8, 'uploads/albums/album_8/img_20250724093234_36840c70.webp', '', '', 5, 0, '2025-07-24 09:32:34'),
(19, 8, 'uploads/albums/album_8/img_20250724093234_9e31f113.webp', '', '', 6, 0, '2025-07-24 09:32:34'),
(20, 8, 'uploads/albums/album_8/img_20250724093234_dbef46a6.webp', '', '', 7, 0, '2025-07-24 09:32:34'),
(21, 8, 'uploads/albums/album_8/img_20250724093234_8e7c7a4e.webp', '', '', 8, 0, '2025-07-24 09:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'กิจกรรมผลิตบัณฑิต', 'กิจกรรมผลิตบัณฑิต', '2025-07-24 08:27:36'),
(2, 'กิจกรรมการบริการวิชาการ', 'กิจกรรมการบริการวิชาการ', '2025-07-24 08:27:36'),
(3, 'กิจกรรมด้านผลิตและพัฒนาครู', 'กิจกรรมด้านผลิตและพัฒนาครู', '2025-07-24 08:27:36'),
(4, 'กิจกรรมการวิจัย', 'กิจกรรมการวิจัย', '2025-07-24 08:27:36'),
(5, 'กิจกรรมด้านศิลปะและวัฒนธรรม', 'กิจกรรมด้านศิลปะและวัฒนธรรม', '2025-07-24 08:27:36'),
(6, 'กิจกรรมด้านการบริหารจัดการ', 'กิจกรรมด้านการบริหารจัดการ', '2025-07-24 08:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `status` enum('success','failed') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `username`, `ip_address`, `user_agent`, `status`, `failure_reason`, `login_time`) VALUES
(1, NULL, 'INBreeze-i', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'failed', 'Username not found', '2025-07-24 08:37:34'),
(2, 1, 'admin', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'success', NULL, '2025-07-24 08:37:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `activity_images`
--
ALTER TABLE `activity_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `album_categories`
--
ALTER TABLE `album_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `album_images`
--
ALTER TABLE `album_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `album_id` (`album_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_images`
--
ALTER TABLE `activity_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `albums`
--
ALTER TABLE `albums`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `album_categories`
--
ALTER TABLE `album_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `album_images`
--
ALTER TABLE `album_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `activity_images`
--
ALTER TABLE `activity_images`
  ADD CONSTRAINT `activity_images_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `albums`
--
ALTER TABLE `albums`
  ADD CONSTRAINT `albums_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `album_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `albums_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `album_images`
--
ALTER TABLE `album_images`
  ADD CONSTRAINT `album_images_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
