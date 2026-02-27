-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 27, 2026 at 10:08 AM
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
-- Database: `managebanquet_simple`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT 'default-logo.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_name`, `contact_name`, `phone`, `email`, `address`, `logo_path`, `created_at`) VALUES
(3, 'SHotel Hatyai', 'Front', '074261702', 'gsa@shadyaihotel.com', '220 ถ. ประชาธิปัตย์ ตำบล หาดใหญ่ อำเภอหาดใหญ่ สงขลา 90110', 'img/logo_1772157252_images (1).jfif', '2026-02-25 06:48:12'),
(6, 'MANONTA Budget Hotel', 'โรงแรมนานอนตะ บัตเจ็ต', '093 647 6060', 'gsa@shadyaihotel.com', 'โรงแรมนานอนตะ บัตเจ็ต', 'img/logo_1772157234_ดาวน์โหลด.png', '2026-02-26 03:59:08'),
(7, 'Luxury Grand Hotel', 'creeda 725', '0888990743', 'creeda725@gmail.com', 'asd', 'img/logo_1772095866_images.jfif', '2026-02-26 08:48:30');

-- --------------------------------------------------------

--
-- Table structure for table `functions`
--

CREATE TABLE `functions` (
  `id` int(11) NOT NULL,
  `function_code` varchar(20) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `function_name` varchar(255) NOT NULL,
  `booking_name` varchar(255) DEFAULT NULL,
  `organization` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `booking_room` varchar(100) DEFAULT NULL,
  `deposit` decimal(10,2) DEFAULT NULL,
  `main_kitchen_remark` text DEFAULT NULL,
  `banquet_style` text DEFAULT NULL,
  `equipment` text DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `backdrop_detail` text DEFAULT NULL,
  `backdrop_img` varchar(255) DEFAULT NULL,
  `hk_florist_detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approve` tinyint(1) NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `approve_date` datetime DEFAULT NULL,
  `approve_by` int(11) DEFAULT NULL,
  `modify` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `functions`
--

INSERT INTO `functions` (`id`, `function_code`, `company_id`, `function_name`, `booking_name`, `organization`, `phone`, `room_name`, `booking_room`, `deposit`, `main_kitchen_remark`, `banquet_style`, `equipment`, `remark`, `backdrop_detail`, `backdrop_img`, `hk_florist_detail`, `created_at`, `approve`, `created_by`, `approve_date`, `approve_by`, `modify`) VALUES
(107, '00107/2702', 6, 'พิธีสวดพระอภิธรรม (Funeral Service)', 'คุณเจนนิเฟอร์ คิม', 'ธนาคารแห่งประเทศไทย', '0820554160', 'ห้องจามจุรี', '', 9000.00, '', 'จัดเก้าอี้แบบแถวตอนเรียงหน้ากระดาน\r\nปูผ้าขาวเทาบริเวณอาสนะสงฆ์', 'ไมค์สายสำหรับพระสงฆ์ 4 ตัว\r\nลำโพงกระจายเสียงรอบพื้นที่', 'เน้นความสงบเรียบร้อย การแต่งกายโทนสุภาพ', 'Backdrop อักษรโฟม: พิธีสวดพระอภิธรรม (Funeral Service)\r\nธีมสี: ครีม-ทอง', '', 'จัดดอกไม้หน้าศพโทนขาว-เขียว\r\nน้ำดื่มใส่แก้วพร้อมหลอดบริการแขกตลอดงาน', '2026-02-27 08:28:46', 1, 'นางสาว ดวงพร โชคชัย', '2026-02-27 15:30:09', 1, '2026-02-27 08:30:09'),
(108, '00108/2702', 3, 'งานเลี้ยงปีใหม่บริษัท (Company New Year)', 'คุณอัครพล สุขสวัสดิ์', 'ธนาคารแห่งประเทศไทย', '0883872316', 'ห้องราชพฤกษ์ ชั้น 2', '', 5000.00, '', 'Round Table พร้อมเวทีการแสดงขนาดใหญ่\r\nมีจุดลงคะแนน Lucky Draw', 'ระบบไฟเทค แสง สี เสียง\r\nไมค์สำหรับพิธีกร 2 คู่', 'เน้นความสนุกสนานและการจับรางวัล', 'Backdrop อักษรโฟม: งานเลี้ยงปีใหม่บริษัท (Company New Year)\r\nธีมสี: ครีม-ทอง', '', 'สายรุ้งและลูกโป่งธีมคริสต์มาส/ปีใหม่\r\nต้นคริสต์มาสประดับไฟที่ทางเข้า', '2026-02-27 08:30:19', 1, 'นางสาว ดวงพร โชคชัย', '2026-02-27 15:30:22', 1, '2026-02-27 08:30:22'),
(109, '00109/2702', 6, 'งานแฟนมีตติ้ง (Fan Meeting)', 'รศ.นภา พรรณนา', 'มหาวิทยาลัยเกษตรศาสตร์', '0825696198', 'ห้องราชพฤกษ์ ชั้น 2', '', 20000.00, '', 'Flat Floor พร้อมโซฟานั่งคุยบนเวที\r\nพื้นที่ด้านหน้าเวทีสำหรับกิจกรรมเกม', 'จอ Projector 2 ข้างเวที\r\nระบบถ่ายทอดสดผ่าน OBS\r\nไฟ Follow ส่องศิลปิน', 'แขกเน้นถ่ายรูปและวีดีโอ ต้องการปลั๊กไฟสำรอง', 'Backdrop อักษรโฟม: งานแฟนมีตติ้ง (Fan Meeting)\r\nธีมสี: ครีม-ทอง', '', 'ซุ้มแสดง Standee ศิลปิน\r\nจุดรับฝากของขวัญและจดหมาย', '2026-02-27 08:32:48', 1, 'นาย ทดสอบ สเร่อ', '2026-02-27 15:37:41', 8, '2026-02-27 08:37:41'),
(110, '00110/2702', 3, 'สัมมนาพนักงาน ', 'คุณเจนนิเฟอร์ คิม', 'ธนาคารแห่งประเทศไทย', '0845842370', 'ห้องราชพฤกษ์ ชั้น 2', '', 24000.00, '', 'U-Shape Style เพื่อการพูดคุยที่ทั่วถึง\r\nมีกระดาน Flipchart 4 มุม', 'ลำโพงบลูทูธสำหรับกิจกรรม\r\nสายเชื่อมต่อ Mac/Windows ครบชุด', 'เน้นกิจกรรมกลุ่ม ไม่เน้นพิธีการ', 'Backdrop อักษรโฟม: สัมมนาพนักงาน (Internal Workshop)\r\nธีมสี: ฟ้า-ขาว', '', 'เตรียม Post-it และปากกาเคมี\r\nน้ำดื่มและลูกอมประจำกลุ่ม', '2026-02-27 08:32:55', 1, 'นาย ทดสอบ สเร่อ', '2026-02-27 15:37:43', 8, '2026-02-27 08:37:43'),
(111, '00111/2702', 6, 'งานเลี้ยงปีใหม่บริษัท (Company New Year)', 'รศ.นภา พรรณนา', 'ธนาคารแห่งประเทศไทย', '0822029154', 'ห้องจามจุรี', '', 33000.00, '', 'Round Table พร้อมเวทีการแสดงขนาดใหญ่\r\nมีจุดลงคะแนน Lucky Draw', 'ระบบไฟเทค แสง สี เสียง\r\nไมค์สำหรับพิธีกร 2 คู่', 'เน้นความสนุกสนานและการจับรางวัล', 'Backdrop อักษรโฟม: งานเลี้ยงปีใหม่บริษัท (Company New Year)\r\nธีมสี: ชมพู-พาสเทล', 'uploads/backdrop_1772182973.png', 'สายรุ้งและลูกโป่งธีมคริสต์มาส/ปีใหม่\r\nต้นคริสต์มาสประดับไฟที่ทางเข้า', '2026-02-27 09:02:13', 0, 'นางสาว ดวงพร โชคชัย', NULL, NULL, '2026-02-27 09:02:53');

-- --------------------------------------------------------

--
-- Table structure for table `function_kitchens`
--

CREATE TABLE `function_kitchens` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `k_type` varchar(100) DEFAULT NULL,
  `k_item` text DEFAULT NULL,
  `k_qty` int(11) DEFAULT NULL,
  `k_remark` text DEFAULT NULL,
  `k_date` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `function_kitchens`
--

INSERT INTO `function_kitchens` (`id`, `function_id`, `k_type`, `k_item`, `k_qty`, `k_remark`, `k_date`) VALUES
(104, 107, 'Snack Box', 'เบเกอรี่ 2 ชิ้น/น้ำผลไม้', 100, NULL, '2026-03-29'),
(105, 108, 'Grand Buffet', 'บุฟเฟต์นานาชาติ / เบียร์ถัง', 250, NULL, '2026-03-18'),
(106, 109, 'LunchBox', 'ข้าวหน้าหมูทอด/ชานมไข่มุก', 200, NULL, '2026-03-08'),
(110, 110, 'Coffee Break', 'ปังปิ้ง/โกโก้ร้อน', 30, NULL, '2026-03-20'),
(118, 111, 'Grand Buffet', 'บุฟเฟต์นานาชาติ / เบียร์ถัง', 250, NULL, '2026-03-08');

-- --------------------------------------------------------

--
-- Table structure for table `function_menus`
--

CREATE TABLE `function_menus` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `menu_time` varchar(50) DEFAULT NULL,
  `menu_name` varchar(255) DEFAULT NULL,
  `menu_set` varchar(100) DEFAULT NULL,
  `menu_detail` text DEFAULT NULL,
  `menu_qty` varchar(50) DEFAULT NULL,
  `menu_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `function_menus`
--

INSERT INTO `function_menus` (`id`, `function_id`, `menu_time`, `menu_name`, `menu_set`, `menu_detail`, `menu_qty`, `menu_price`) VALUES
(85, 107, '2026-03-29', 'Funeral Box', 'Simple', 'Snack Box Set A', '100', 65.00),
(86, 108, '2026-03-18', 'New Year Grand', 'Max', 'International Buffet Set D', '250', 1800.00),
(87, 109, '2026-03-08', 'Fanclub Set', 'Cute', 'Bento Box + Drink', '200', 350.00),
(91, 110, '2026-03-20', 'Simple Workshop', 'Eco', 'Lunch Box Only', '30', 150.00),
(99, 111, '2026-03-08', 'New Year Grand', 'Max', 'International Buffet Set D', '250', 1800.00);

-- --------------------------------------------------------

--
-- Table structure for table `function_schedules`
--

CREATE TABLE `function_schedules` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `schedule_date` date DEFAULT NULL,
  `schedule_hour` varchar(50) DEFAULT NULL,
  `schedule_function` text DEFAULT NULL,
  `schedule_guarantee` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `function_schedules`
--

INSERT INTO `function_schedules` (`id`, `function_id`, `schedule_date`, `schedule_hour`, `schedule_function`, `schedule_guarantee`) VALUES
(174, 107, '2026-03-29', '18:00', 'ต้อนรับแขก', '98'),
(175, 107, '2026-03-29', '19:00', 'เริ่มพิธีสวด', '63'),
(176, 108, '2026-03-18', '19:00', 'Dinner Start', '53'),
(177, 108, '2026-03-18', '21:00', 'Lucky Draw Phase 1', '61'),
(178, 109, '2026-03-08', '10:00', 'Hi-Touch Activity', '48'),
(179, 109, '2026-03-08', '13:00', 'Main Stage Show', '88'),
(185, 110, '2026-03-20', '09:00', 'Ice Breaking', '58'),
(201, 111, '2026-03-08', '19:00', 'Dinner Start', '67'),
(202, 111, '2026-03-08', '21:00', 'Lucky Draw Phase 1', '42'),
(203, 111, '2026-03-20', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `signatures`
--

CREATE TABLE `signatures` (
  `id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `users_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `signatures`
--

INSERT INTO `signatures` (`id`, `path`, `created_at`, `users_id`) VALUES
(4, 'uploads/signatures/sig_7_1772180774.png', '2026-02-27 08:26:14', 7),
(5, 'uploads/signatures/sig_1_1772181502.png', '2026-02-27 08:38:22', 1),
(6, 'uploads/signatures/sig_6_1772181394.png', '2026-02-27 08:36:34', 6),
(7, 'uploads/signatures/sig_8_1772181939.png', '2026-02-27 08:45:39', 8);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`) VALUES
(1, 'admin', '81dc9bdb52d04dc20036dbd8313ed055', 'Admin', 'นางสาว ดวงพร โชคชัย'),
(6, 'sale', '$2y$10$6VysOZiByZolc3iMfsI7C.lQ1L5evr1wV8tKrY0mCavGqdO07xf6u', 'Staff', 'นาย ทดสอบ สเร่อ'),
(7, 'sale1', '$2y$10$/LakriEPhPDYQqReZuEX0eVxDXg5wdjbWtYwoVp8hWiux8JQbbrMm', 'Staff', 'sale1'),
(8, 'GM', '$2y$10$rE8o3aektMXaVlDnitLMn.VLqAu6NMwjJJocsFem0bc2ki9UyOmeS', 'GM', 'นางสาว mfse asasd'),
(9, 'V', '$2y$10$Bxl0fwblLQXPnY2LAcIuLukWzFsMDOr.llFrlpYRu2tZvxm9RVjyC', 'Viewer', 'นางสาว ดวงพร โชคชัย');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `functions`
--
ALTER TABLE `functions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `function_id` (`function_id`);

--
-- Indexes for table `function_menus`
--
ALTER TABLE `function_menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `function_id` (`function_id`);

--
-- Indexes for table `function_schedules`
--
ALTER TABLE `function_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `function_id` (`function_id`);

--
-- Indexes for table `signatures`
--
ALTER TABLE `signatures`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `functions`
--
ALTER TABLE `functions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `function_menus`
--
ALTER TABLE `function_menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `function_schedules`
--
ALTER TABLE `function_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `signatures`
--
ALTER TABLE `signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  ADD CONSTRAINT `function_kitchens_ibfk_1` FOREIGN KEY (`function_id`) REFERENCES `functions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `function_menus`
--
ALTER TABLE `function_menus`
  ADD CONSTRAINT `function_menus_ibfk_1` FOREIGN KEY (`function_id`) REFERENCES `functions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `function_schedules`
--
ALTER TABLE `function_schedules`
  ADD CONSTRAINT `function_schedules_ibfk_1` FOREIGN KEY (`function_id`) REFERENCES `functions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
