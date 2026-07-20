-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 20, 2026 at 01:11 PM
-- Server version: 10.5.8-MariaDB-log
-- PHP Version: 8.2.27

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_name`, `contact_name`, `phone`, `email`, `address`, `logo_path`, `created_at`) VALUES
(3, 'S hadyai hotel', 'Front', '074261702', 'gsa@shadyaihotel.com', '220 ถ. ประชาธิปัตย์ ตำบล หาดใหญ่ อำเภอหาดใหญ่ สงขลา 90110', 'img/logo_1772157252_images (1).jfif', '2026-02-25 06:48:12'),
(6, 'MANONTA Budget Hotel', 'โรงแรมนานอนตะ บัตเจ็ต', '093 647 6060', 'gsa@shadyaihotel.com', 'โรงแรมนานอนตะ บัตเจ็ต', 'img/logo_1772157234_ดาวน์โหลด.png', '2026-02-26 03:59:08'),
(9, 'Nijuni - ร้านอาหารญี่ปุ่นหาดใหญ่', 'คุณโตน', '093 574 8174', '', 'เลขที่ 220 ถ.ประชาธิปัตย์, Hat Yai, Thailand, Songkhla, Hat Yai, Thailand, 90110', 'img/logo_1779075227_571224078_807570942031345_7671692842623057547_n.jpg', '2026-05-18 03:33:47');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `cust_name` varchar(255) NOT NULL,
  `cust_tax_id` varchar(20) DEFAULT NULL,
  `cust_address` text DEFAULT NULL,
  `cust_contact_name` varchar(100) DEFAULT NULL,
  `cust_phone` varchar(20) DEFAULT NULL,
  `cust_email` varchar(100) DEFAULT NULL,
  `sales_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อเซลที่ดูแล',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `cust_name`, `cust_tax_id`, `cust_address`, `cust_contact_name`, `cust_phone`, `cust_email`, `sales_name`, `created_at`) VALUES
(32, 'สำนักงานสาธารณสุขและสิ่งแวดล้อม เทศบาลนครหาดใหญ่', '', '', 'ผอ.หมวย', '0944309646', '', 'ซูกัส', '2026-07-07 05:01:33'),
(12, 'คุณ สมชาย', '', '', '', '', '', '', '2026-06-21 10:37:34'),
(13, 'นิยะดา', '', '', '', '', '', 'ซูกัส', '2026-07-02 08:07:27'),
(21, 'คณะแพทย์ มอ.', '', '', 'หมอวรา', '0867833060', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-04 12:16:11'),
(15, 'BNI', '', '', 'คุณปอย', '', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-04 07:05:48'),
(16, 'OEXN', '', '', 'คุณเจน', '0652946289', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-04 07:26:29'),
(17, 'SSK2026', '', '', 'คุณเต้ย', '080139639', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-04 07:31:49'),
(18, 'คณะแพทย์ มอ.', '', '', 'คุณอรพรรณ', '0859252226', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-04 07:34:20'),
(19, 'บมจ.ซีพี ออลล์ (สำนักงานใหญ่)', ' 1075420000011', '313 อาคาร ซี.พี.ทาวเวอร์ ชั้น 24 ถนนสีลม แขวงสีลม เขตบางรัก กรุงเทพฯ 10500', 'อ.นุ', '0934134000', '', 'ซูกัส', '2026-07-04 07:56:32'),
(20, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' โรงเรียนหาดใหญ่วิทยาลัย', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 09:08:16'),
(22, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 12:57:49'),
(23, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 13:00:14'),
(24, 'โรงเรียนหาดใหญ่วิทยาลัย', '', 'โรงเรียนหาดใหญ่วิทยาลัย', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 13:03:46'),
(25, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' 468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 13:22:46'),
(26, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' 468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 14:32:54'),
(27, 'โรงเรียนหาดใหญ่วิทยาลัย', '0994000580746', '468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูแพน', '0611757287', '', 'ซูกัส', '2026-07-05 03:18:41'),
(28, 'งานแต่งคุณต๋อม', '', '', 'คุณต๋อม', '0954400645', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-06 05:51:55'),
(29, 'Amriches Group', '', '', 'คุณณัฐพล', '', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-06 09:10:16'),
(30, 'บริษัทไอยรา   แพลนเน็ต', '', '', 'ครูขวัญ', '', '', 'โบว์', '2026-07-06 09:21:34'),
(31, 'ธนาคารกรุงไทย', '', '', 'คุณเอ๋', '0894066688', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-07 03:50:26'),
(33, 'บริษัท ซัมซุงประกัน ชีวิต', '', '', 'คุณเมย์', '0949629289', '', 'ซูกัส', '2026-07-07 10:13:34'),
(34, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูขวัญ', '0887841791', '', 'โบว์', '2026-07-08 07:50:45'),
(35, 'คุณสุวรรณหงษ์ สังวาลย์', '', '', 'คุณต๋อม', '0954400645', '', 'น.ส. นิยะดา ชาปาน ', '2026-07-13 09:27:02'),
(36, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '468 ถ. เพชรเกษม, ต.หาดใหญ่ อำเภอหาดใหญ่, สงขลา 90110', 'ครูเจี๊ยบ', '0968837449', '', 'โบว์', '2026-07-14 07:15:22'),
(37, 'คุณธราธร เพชรสกุล', '', '', 'คุณธราธร', '0801397639', '', 'ซูกัส', '2026-07-15 05:04:38'),
(38, 'ไอยรา', '', '', '', '', '', '', '2026-07-18 04:47:23'),
(39, 'ไอยรา', '', '', '', '', '', '', '2026-07-18 04:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `event_projects`
--

CREATE TABLE `event_projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Cancelled') COLLATE utf8_unicode_ci DEFAULT 'Pending',
  `created_by` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `event_projects`
--

INSERT INTO `event_projects` (`id`, `project_name`, `customer_id`, `company_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'งานเลี้ยงปีใหม่บริษัท (Company New Year)', 5, 3, 'Pending', NULL, '2026-05-26 02:07:03', '2026-05-27 02:18:29'),
(2, 'งานประชุม/สัมนา', 5, 9, 'Approved', NULL, '2026-05-26 02:09:49', '2026-05-27 02:18:29'),
(5, 'งานประชุมสำนักงานเขต', 5, 3, 'Approved', 'sale', '2026-06-09 02:39:38', '2026-06-09 02:43:12'),
(6, 'งานแต่ง', 5, 3, 'Pending', 'GM', '2026-06-09 02:44:33', '2026-06-09 02:44:33'),
(7, 'งานบวช', 11, 3, 'Approved', 'GM', '2026-06-09 02:48:13', '2026-06-29 05:36:12'),
(8, 'งานเลี้ยงบริษัท BNI', 11, 3, 'Approved', 'คุฯผู้จัดการ ', '2026-06-21 10:39:06', '2026-06-21 11:12:39'),
(9, 'งานเลี้ยงบริษัท BNI 2', 12, 3, 'Approved', 'คุฯผู้จัดการ ', '2026-06-21 10:39:12', '2026-06-21 11:14:38'),
(10, 'งานเลี้ยงบริษัท BNI', 12, 3, 'Pending', 'คุณ เซลล์ ทดสอบ', '2026-06-22 02:17:03', '2026-06-22 02:17:03'),
(11, 'งานเลี้ยงบริษัท BNI', 12, 3, 'Approved', 'คุณ เซลล์ ทดสอบ', '2026-06-22 08:21:17', '2026-06-22 08:25:00'),
(12, 'งานสัมมนาและประชุม', 14, 3, 'Approved', 'นาย วทัญญู ต้นจาน', '2026-07-02 08:25:12', '2026-07-05 04:59:02'),
(13, 'งานสัมมนาและประชุม1', 19, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-05 04:50:25', '2026-07-05 04:57:54'),
(14, 'งานสัมมนาและประชุม', 15, 3, 'Approved', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-05 06:38:43', '2026-07-05 06:39:02'),
(15, 'งานสัมมนาและประชุม', 15, 3, 'Approved', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-05 06:44:56', '2026-07-05 06:45:05'),
(16, 'คอนเสิร์ตโซติจูด', 17, 3, 'Approved', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-05 13:11:55', '2026-07-05 13:12:11'),
(17, 'งานเลี้ยงสังสรรค์กีฬาสี', 32, 3, 'Pending', '21', '2026-07-07 08:39:18', '2026-07-07 08:39:18'),
(18, 'งานเลี้ยงสังสรรค์', 33, 3, 'Pending', '21', '2026-07-07 10:18:04', '2026-07-07 10:18:04'),
(19, 'งานเลี้ยงสังสรรค์', 33, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-07 10:33:02', '2026-07-07 10:33:02'),
(20, 'งานเลี้ยงสังสรรค์', 33, 3, 'Approved', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-07 10:33:08', '2026-07-08 00:20:25'),
(21, 'งานแต่งงาน', 28, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-08 00:33:07', '2026-07-08 00:33:07'),
(22, 'งานแต่งงาน', 28, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-08 00:33:14', '2026-07-08 00:33:14'),
(23, 'งานสัมมนาและประชุม', 15, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-09 06:27:49', '2026-07-09 06:27:49'),
(24, 'งานสัมมนาและประชุม', 15, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-09 06:27:54', '2026-07-09 06:27:54'),
(25, 'งานสัมมนาและประชุม', 15, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-09 06:27:59', '2026-07-09 06:27:59'),
(26, 'งานสัมมนาและประชุม', 15, 3, 'Pending', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-11 08:15:24', '2026-07-11 08:15:24'),
(27, 'งานสัมมนาและประชุม', 15, 3, 'Approved', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-11 08:15:27', '2026-07-14 08:38:17'),
(28, 'งานสัมมนาและประชุม', 15, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:23:58', '2026-07-13 03:23:58'),
(29, 'งานสัมมนาและประชุม', 29, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:25:27', '2026-07-13 03:25:27'),
(30, 'กิจกรรมสอนเสริมวันเสาร์ และกิจกรรมเพิ่มพูนศักยภาพ  ของนักเรียนระดับชั้นมัธยมศึกษาปีที่ 5 โครงการ SMA', 27, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:31:09', '2026-07-13 03:31:09'),
(31, 'งานสัมมนาและประชุม', 27, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:33:04', '2026-07-13 03:33:04'),
(32, 'งานสัมมนาและประชุม', 27, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:33:07', '2026-07-13 03:33:07'),
(33, 'งานสัมมนาและประชุม', 27, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:33:22', '2026-07-13 03:33:22'),
(34, 'งานเลี้ยงสังสรรค์', 33, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:36:14', '2026-07-13 03:36:14'),
(35, 'ทดสอบแจ้งเตือน', 12, 9, 'Pending', '17', '2026-07-13 03:49:24', '2026-07-13 03:49:24'),
(36, 'ทดสอบแจ้งเตือน', 12, 9, 'Approved', 'นาย วทัญญู ต้นจาน', '2026-07-13 03:49:56', '2026-07-13 03:50:47'),
(37, 'สัมมนาคณะคุณหมอ', 21, 3, 'Pending', 'น.ส. นิยะดา ชาปาน ', '2026-07-15 03:51:25', '2026-07-15 03:51:25');

-- --------------------------------------------------------

--
-- Table structure for table `functions`
--

CREATE TABLE `functions` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `version_no` int(11) DEFAULT 1,
  `is_approved` tinyint(1) DEFAULT 0,
  `draft_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT 'Draft V1',
  `function_code` varchar(20) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `function_type_id` int(11) DEFAULT NULL,
  `function_name` varchar(255) NOT NULL,
  `event_date` date DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `booking_name` varchar(255) DEFAULT NULL,
  `organization` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `booking_room` varchar(100) DEFAULT NULL,
  `pax` int(11) DEFAULT 0,
  `deposit` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `main_kitchen_remark` text DEFAULT NULL,
  `banquet_style` text DEFAULT NULL,
  `equipment` text DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `lead_source` varchar(100) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `backdrop_detail` text DEFAULT NULL,
  `backdrop_img` varchar(255) DEFAULT NULL,
  `hk_florist_detail` text DEFAULT NULL,
  `file_attachment1` varchar(255) DEFAULT NULL,
  `file_attachment2` varchar(255) DEFAULT NULL,
  `file_attachment3` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approve` tinyint(1) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `status_updated_at` datetime DEFAULT NULL,
  `created_by` varchar(255) NOT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `approve_by` int(11) DEFAULT NULL,
  `modify` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `functions`
--

INSERT INTO `functions` (`id`, `project_id`, `quotation_id`, `version_no`, `is_approved`, `draft_name`, `function_code`, `company_id`, `customer_id`, `function_type_id`, `function_name`, `event_date`, `start_time`, `end_time`, `booking_name`, `organization`, `phone`, `room_name`, `room_id`, `booking_room`, `pax`, `deposit`, `total_amount`, `main_kitchen_remark`, `banquet_style`, `equipment`, `remark`, `cancel_reason`, `lead_source`, `result`, `inspection_date`, `follow_up_date`, `backdrop_detail`, `backdrop_img`, `hk_florist_detail`, `file_attachment1`, `file_attachment2`, `file_attachment3`, `created_at`, `approve`, `status`, `status_updated_at`, `created_by`, `created_by_id`, `approve_date`, `approve_by`, `modify`) VALUES
(158, 26, 19, 1, 0, 'Draft V1', '00158/1107', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-09 08:00:00', '2026-07-09 17:00:00', 'BNI', '', '', NULL, NULL, '', 0, 0.00, 16650.00, '', '', '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-11 08:15:24', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-11 08:15:24'),
(159, 27, 19, 1, 1, 'Draft V1', '00159/1107', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-09 08:00:00', '2026-07-09 17:00:00', 'BNI', '', '', NULL, NULL, '', 0, 0.00, 16650.00, '', '', '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-11 08:15:27', 1, 'Confirmed', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-14 08:38:17'),
(168, 37, 28, 1, 0, 'Draft V1', '00168/1507', 3, 21, 1, 'สัมมนาคณะคุณหมอ', NULL, '2026-09-10 08:00:00', '2026-09-10 17:00:00', 'คณะแพทย์ มอ.', '', '0867833060', NULL, NULL, '', 0, 0.00, 73000.00, '', '', '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-15 03:51:25', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน ', 21, NULL, NULL, '2026-07-15 03:51:25');

-- --------------------------------------------------------

--
-- Table structure for table `function_breaks`
--

CREATE TABLE `function_breaks` (
  `id` int(11) NOT NULL,
  `function_id` int(11) NOT NULL,
  `break_time` varchar(50) DEFAULT NULL,
  `break_type_id` int(11) DEFAULT NULL,
  `break_type` varchar(100) DEFAULT NULL,
  `break_menu` text DEFAULT NULL,
  `break_pax` int(11) DEFAULT NULL,
  `break_price` decimal(10,2) DEFAULT 0.00,
  `break_cost` decimal(10,2) DEFAULT 0.00,
  `break_total` decimal(10,2) DEFAULT 0.00,
  `break_remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_breaks`
--

INSERT INTO `function_breaks` (`id`, `function_id`, `break_time`, `break_type_id`, `break_type`, `break_menu`, `break_pax`, `break_price`, `break_cost`, `break_total`, `break_remark`) VALUES
(1, 101, '10:30:00', 1, 'Morning Break', 'กาแฟดำ, ชาเขียวร้อน, พัฟไก่ และผลไม้ตามฤดูกาล', 1, 85.00, 0.00, 85.00, 'ขอแก้วกระดาษรักษ์โลก'),
(2, 101, '14:30:00', 2, 'Afternoon Break', 'น้ำส้มคั้นสด, แซนวิชแฮมชีส และคุกกี้เนยสด', 1, 75.00, 0.00, 75.00, 'เสิร์ฟพร้อมทิชชู่แผ่นหนา'),
(3, 102, '09:45:00', 1, 'Morning Break', 'โกโก้เย็น, ปาท่องโก๋ยัดไส้ ', 1, 60.00, 0.00, 60.00, 'เน้นเสิร์ฟเร็วภายใน 15 นาที'),
(4, 103, '15:00:00', 3, 'Special Break', 'ชานมไข่มุก (หวานน้อย), ไดฟุกุสตรอว์เบอร์รี่', 1, 120.00, 0.00, 120.00, 'วีไอพี 5 ท่าน ขอจานเซรามิก');

-- --------------------------------------------------------

--
-- Table structure for table `function_finance`
--

CREATE TABLE `function_finance` (
  `id` int(11) NOT NULL,
  `function_id` int(11) NOT NULL,
  `type` enum('income','cost','deposit') NOT NULL,
  `detail` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `created_by_role` varchar(50) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_post_approval` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_finance`
--

INSERT INTO `function_finance` (`id`, `function_id`, `type`, `detail`, `amount`, `payment_method`, `transaction_date`, `created_by_role`, `created_by_name`, `created_at`, `is_post_approval`) VALUES
(1, 117, 'cost', 'ค่าป้าย', 2996.00, NULL, '2026-03-26', NULL, NULL, '2026-03-26 06:14:07', 0),
(2, 133, 'cost', 'ค่าkfc', 10000.00, 'Cash', '2026-06-29', 'Admin', 'นาย วทัญญู ต้นจาน', '2026-06-29 05:54:40', 1),
(3, 133, 'income', 'ค่าkfc', 12000.00, 'Cash', '2026-06-29', 'Admin', 'นาย วทัญญู ต้นจาน', '2026-06-29 05:55:31', 1),
(4, 135, 'cost', 'ค่าเบรกเพิ่ม', 2000.00, 'Cash', '2026-07-02', 'Admin', 'นาย วทัญญู ต้นจาน', '2026-07-02 08:30:32', 1),
(5, 150, 'income', 'มัดจำ', 10000.00, 'Cash', '2026-07-06', 'Staff', 'น.ส. นิยะดา ชาปาน (ซูกัส)', '2026-07-06 09:22:54', 1);

-- --------------------------------------------------------

--
-- Table structure for table `function_kitchens`
--

CREATE TABLE `function_kitchens` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `k_type_id` int(11) DEFAULT NULL,
  `k_item` text DEFAULT NULL,
  `k_qty` int(11) DEFAULT NULL,
  `k_price` decimal(10,2) DEFAULT 0.00,
  `k_cost` decimal(10,2) DEFAULT 0.00,
  `k_remark` text DEFAULT NULL,
  `k_date` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_kitchens`
--

INSERT INTO `function_kitchens` (`id`, `function_id`, `k_type_id`, `k_item`, `k_qty`, `k_price`, `k_cost`, `k_remark`, `k_date`) VALUES
(187, 168, 1, 'เค้กบาม่อนหน้าฝอยทอง + ชา กาแฟ โอวัลติน ', 50, 90.00, 0.00, '', '2026-10-09'),
(188, 168, 2, 'ขนมเทียนแก้ว + ชา กาแฟ โอวัลติน ', 50, 90.00, 0.00, '', '2026-10-09');

-- --------------------------------------------------------

--
-- Table structure for table `function_menus`
--

CREATE TABLE `function_menus` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `menu_time` varchar(50) DEFAULT NULL,
  `menu_name` varchar(255) DEFAULT NULL,
  `menu_set_id` int(11) DEFAULT NULL,
  `menu_detail` text DEFAULT NULL,
  `menu_qty` varchar(50) DEFAULT NULL,
  `menu_price` decimal(10,2) DEFAULT NULL,
  `menu_cost` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_menus`
--

INSERT INTO `function_menus` (`id`, `function_id`, `menu_time`, `menu_name`, `menu_set_id`, `menu_detail`, `menu_qty`, `menu_price`, `menu_cost`) VALUES
(214, 158, '2026-07-09', NULL, 0, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', '25', 270.00, NULL),
(215, 158, '2026-07-09', NULL, 0, 'เบรค : ข้าวเหนียวคอนโด', '20', 100.00, NULL),
(216, 158, '2026-07-09', NULL, 0, 'เบรค : เค้กส้มหน้านิ่ม', '25', 100.00, NULL),
(217, 158, '2026-07-09', NULL, 0, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', '20', 270.00, NULL),
(218, 159, '2026-07-09', NULL, 0, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', '25', 270.00, NULL),
(219, 159, '2026-07-09', NULL, 0, 'เบรค : ข้าวเหนียวคอนโด', '20', 100.00, NULL),
(220, 159, '2026-07-09', NULL, 0, 'เบรค : เค้กส้มหน้านิ่ม', '25', 100.00, NULL),
(221, 159, '2026-07-09', NULL, 0, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', '20', 270.00, NULL),
(238, 168, '2026-09-10', NULL, 6, 'ข้าวสวย +ต้มยำทะเล+ไก่ทอดกระเทียม+ปลาทับทิมผัดพริก+ผลไม้', '12', 350.00, 0.00),
(239, 168, '2026-09-10', NULL, 6, 'ข้าวสวย+แกงส้มชะอมไ่ข่+ปลากะพงผัดพริกไทยดำ+บรอกโครี่ผัดกุ้ง+ผลไม้', '13', 350.00, 0.00),
(240, 168, '2026-09-10', NULL, 6, 'ข้าวสวย+พะแนงไก่+ปลาทับทิมทอดราดพริก+แกงจืดเต้าหู้สาหร่าย+ผลไม้', '13', 350.00, 0.00),
(241, 168, '2026-09-10', NULL, 6, 'ข้าวสวย+ต้มข่าไก่+กุ้งผัดน้ำพริกเผา+เครื่องแกงทะเล+ผลไม้', '12', 350.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `function_menu_details`
--

CREATE TABLE `function_menu_details` (
  `id` int(11) NOT NULL,
  `function_id` int(11) DEFAULT NULL,
  `menu_type_id` int(11) DEFAULT NULL,
  `menu_items` text DEFAULT NULL,
  `beverage_detail` text DEFAULT NULL,
  `guarantee_pax` int(11) DEFAULT NULL,
  `price_per_pax` decimal(10,2) DEFAULT NULL,
  `cost_per_pax` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_menu_details`
--

INSERT INTO `function_menu_details` (`id`, `function_id`, `menu_type_id`, `menu_items`, `beverage_detail`, `guarantee_pax`, `price_per_pax`, `cost_per_pax`) VALUES
(1, 0, 1, 'บุฟเฟต์อาหารไทย: แกงเขียวหวานลูกชิ้นปลากราย, ปลากะพงทอดน้ำปลา, ผัดผักรวมมิตรมงคล, ข้าวหอมมะลิใหม่', 'น้ำดื่มสะอาด, น้ำสมุนไพร (เก๊กฮวย/อัญชัน)', 1, 450.00, NULL),
(2, 0, 2, 'เซตเมนูอาหารจีน: เป็ดปักกิ่ง, กระเพาะปลาน้ำแดง, ปลากะพงนึ่งซีอิ๊ว, ข้าวผัดปู, โอนีแปะก๊วย', 'ชาจีนร้อน/เย็น, น้ำอัดลมแบบ Refill', 1, 850.00, NULL),
(3, 0, 3, 'ค็อกเทลปาร์ตี้: มินิเบอร์เกอร์เนื้อ, กุ้งพันหมี่, ลาบหมูทอดคำหวาน, พาสต้าซอสครีมเห็ดทรัฟเฟิล', 'Sparkling Juice, Punch, น้ำดื่ม', 1, 650.00, NULL),
(4, 0, 1, 'บุฟเฟต์นานาชาติ: สลัดบาร์สด, สเต็กหมูซอสพริกไทยดำ, สปาเก็ตตี้คาโบนาร่า, ซูชิหน้าต่างๆ', 'น้ำผลไม้รวม, กาแฟสด/ชา หลังอาหาร', 1, 550.00, NULL),
(5, 0, 4, 'อาหารกล่องพรีเมียม (Set Box): ข้าวหน้าปลาแซลมอนย่างเกลือ, ไข่หวาน, กิมจิ และสลัดผัก', 'น้ำแร่บรรจุขวด, ชาเขียวพร้อมดื่ม', 1, 250.00, NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_schedules`
--

INSERT INTO `function_schedules` (`id`, `function_id`, `schedule_date`, `schedule_hour`, `schedule_function`, `schedule_guarantee`) VALUES
(325, 168, '2026-10-09', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `function_status_log`
--

CREATE TABLE `function_status_log` (
  `id` int(11) NOT NULL,
  `function_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_status_log`
--

INSERT INTO `function_status_log` (`id`, `function_id`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(1, 133, 'Pending', 'Confirmed', 1, '2026-06-29 12:36:28'),
(2, 133, 'Confirmed', 'In Progress', 1, '2026-06-29 12:52:45'),
(3, 135, 'Pending', 'Confirmed', 1, '2026-07-02 15:27:21'),
(4, 135, 'Confirmed', 'Cancelled', 21, '2026-07-04 14:02:51'),
(5, 136, 'Pending', 'Confirmed', 17, '2026-07-04 15:13:41'),
(6, 148, 'Pending', 'Confirmed', 17, '2026-07-05 13:39:02'),
(7, 149, 'Pending', 'Confirmed', 17, '2026-07-05 13:45:05'),
(8, 150, 'Pending', 'Confirmed', 17, '2026-07-05 20:12:11'),
(9, 150, 'Confirmed', 'Cancelled', 21, '2026-07-07 11:44:55'),
(10, 150, 'Cancelled', 'Cancelled', 21, '2026-07-07 11:45:10'),
(11, 152, 'Pending', 'Confirmed', 17, '2026-07-08 07:20:24'),
(12, 151, 'Pending', 'Cancelled', 17, '2026-07-08 07:20:44'),
(13, 152, 'Confirmed', 'Cancelled', 17, '2026-07-08 07:21:22'),
(14, 167, 'Pending', 'Confirmed', 17, '2026-07-13 10:50:46'),
(15, 159, 'Pending', 'Confirmed', 17, '2026-07-14 15:38:10');

-- --------------------------------------------------------

--
-- Table structure for table `function_types`
--

CREATE TABLE `function_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `prefix` varchar(10) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_types`
--

INSERT INTO `function_types` (`id`, `type_name`, `prefix`, `created_at`) VALUES
(1, 'งานประชุม/สัมมนา', 'MT', '2026-03-16 09:13:48'),
(2, 'งานเลี้ยงฉลองมงคลสมรส', 'WED', '2026-03-16 09:13:48'),
(3, 'งานเลี้ยงวันเกิด', 'BD', '2026-03-16 09:13:48'),
(4, 'งานเลี้ยงสังสรรค์พนักงาน', 'BK', '2026-03-16 09:13:48'),
(5, 'งานแถลงข่าว', 'NEW', '2026-03-16 09:13:48'),
(6, 'งานนิทรรศการ/อีเวนต์', 'EV', '2026-03-16 09:13:48'),
(7, 'งานจัดเลี้ยงนอกสถานที่', 'ST', '2026-03-16 09:13:48'),
(8, 'งานทำบุญเลี้ยงพระ', 'MONK', '2026-03-16 09:13:48'),
(10, 'งานเลี้ยงสังสรรค์', 'BK', '2026-07-07 05:06:28');

-- --------------------------------------------------------

--
-- Table structure for table `master_break_types`
--

CREATE TABLE `master_break_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL COMMENT 'ชื่อประเภทเบรก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_break_types`
--

INSERT INTO `master_break_types` (`id`, `type_name`) VALUES
(1, 'Morning Break (เบรกเช้า)'),
(2, 'Afternoon Break (เบรกบ่าย)'),
(3, 'Special Break (เบรกพิเศษ/VIP)'),
(4, 'Snack Box (ชุดอาหารว่างกล่อง)'),
(5, 'Healthy Break (เบรกเพื่อสุขภาพ)'),
(6, 'Welcome Drink (เครื่องดื่มต้อนรับ)');

-- --------------------------------------------------------

--
-- Table structure for table `master_checklist_bk`
--

CREATE TABLE `master_checklist_bk` (
  `id` int(11) NOT NULL,
  `task_detail` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_checklist_bk`
--

INSERT INTO `master_checklist_bk` (`id`, `task_detail`, `sort_order`) VALUES
(1, 'จัดโต๊ะ 4 โต๊ะ', 0),
(2, 'จัดโต๊ะ 6 โต๊ะ', 0),
(3, 'จัดโต๊ะ 8 โต๊ะ', 0);

-- --------------------------------------------------------

--
-- Table structure for table `master_checklist_hk`
--

CREATE TABLE `master_checklist_hk` (
  `id` int(11) NOT NULL,
  `task_detail` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_checklist_hk`
--

INSERT INTO `master_checklist_hk` (`id`, `task_detail`, `sort_order`) VALUES
(1, 'ดอกไม้', 0),
(2, 'ดิกไม้ใหญ่', 0),
(3, 'กระถางดอกไม้+ธูปเทียน', 0);

-- --------------------------------------------------------

--
-- Table structure for table `master_checklist_mt`
--

CREATE TABLE `master_checklist_mt` (
  `id` int(11) NOT NULL,
  `task_detail` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_checklist_mt`
--

INSERT INTO `master_checklist_mt` (`id`, `task_detail`, `sort_order`) VALUES
(1, 'ไมค์ลอย', 0),
(2, 'เครื่องเสียง', 0),
(3, 'เวทีไฟ', 0),
(4, 'พลุ', 0),
(5, 'สปอตไลท์', 0);

-- --------------------------------------------------------

--
-- Table structure for table `master_menu_categories`
--

CREATE TABLE `master_menu_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_menu_categories`
--

INSERT INTO `master_menu_categories` (`id`, `category_name`, `sort_order`, `created_at`) VALUES
(1, 'อาหารไทย', 1, '2026-07-17 06:05:53'),
(2, 'อาหารจีน', 2, '2026-07-17 06:05:53'),
(3, 'อาหารนานาชาติ', 3, '2026-07-17 06:05:53'),
(4, 'ของว่าง / ค็อกเทล', 4, '2026-07-17 06:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `master_menu_types`
--

CREATE TABLE `master_menu_types` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `type_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_menu_types`
--

INSERT INTO `master_menu_types` (`id`, `category_id`, `type_name`) VALUES
(1, 1, 'บุฟเฟต์ไทย'),
(2, 3, 'บุฟเฟต์นานาชาติ'),
(3, 2, 'โต๊ะจีน'),
(4, 4, 'ค็อกเทล'),
(5, 1, 'เซตเมนู'),
(6, 1, 'ข้าวกล่อง'),
(7, 4, 'เบรก');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_rooms`
--

CREATE TABLE `meeting_rooms` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `room_name` varchar(255) NOT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `length_m` decimal(10,2) DEFAULT NULL,
  `width_m` decimal(10,2) DEFAULT NULL,
  `height_m` decimal(10,2) DEFAULT NULL,
  `total_sqm` decimal(10,2) DEFAULT NULL,
  `cap_theatre` int(11) DEFAULT 0,
  `cap_classroom` int(11) DEFAULT 0,
  `cap_boardroom` int(11) DEFAULT 0,
  `cap_u_shape` int(11) DEFAULT 0,
  `cap_banquet` int(11) DEFAULT 0,
  `cap_cocktail` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `meeting_rooms`
--

INSERT INTO `meeting_rooms` (`id`, `company_id`, `room_name`, `floor`, `length_m`, `width_m`, `height_m`, `total_sqm`, `cap_theatre`, `cap_classroom`, `cap_boardroom`, `cap_u_shape`, `cap_banquet`, `cap_cocktail`, `status`, `created_at`) VALUES
(3, 9, 'ห้องข้างบนชั้น 2', '', 0.00, 0.00, 0.00, 0.00, 0, 30, 0, 0, 0, 0, 'active', '2026-05-26 02:08:47'),
(2, 3, 'ห้องจันทรา', '2nd Floor', 12.00, 8.00, 3.50, 96.00, 80, 45, 25, 30, 0, 50, 'active', '2026-03-26 05:42:49'),
(4, 3, 'ห้องตะวัน', '2', 0.00, 0.00, 3.50, 0.00, 270, 80, 0, 0, 0, 0, 'active', '2026-07-04 08:04:44'),
(5, 3, 'ห้องตะวัน-จันทรา', '2', 0.00, 0.00, 0.00, 0.00, 500, 300, 0, 0, 0, 0, 'active', '2026-07-04 08:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `function_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `quote_no` varchar(50) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `service_charge` decimal(10,2) DEFAULT 0.00,
  `vat` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `vat_type` varchar(20) DEFAULT 'exclude',
  `status` enum('Draft','Sent','Approved','Cancelled') DEFAULT 'Draft',
  `is_selected` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `lead_source` varchar(100) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `lost_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `company_id`, `function_id`, `project_id`, `customer_id`, `quote_no`, `event_name`, `event_date`, `expiry_date`, `subtotal`, `service_charge`, `vat`, `grand_total`, `vat_type`, `status`, `is_selected`, `remarks`, `lead_source`, `result`, `inspection_date`, `follow_up_date`, `lost_reason`, `created_by`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(19, 3, NULL, 27, 15, 'QT-20260704-1518', 'งานสัมมนาและประชุม', '2026-07-09', '2026-07-09', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:20:56', '2026-07-04 07:20:42', '2026-07-11 08:15:27'),
(20, 3, NULL, 14, 15, 'QT-20260704-1521', 'งานสัมมนาและประชุม', '2026-07-16', '2026-07-16', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 1, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:23:20', '2026-07-04 07:23:10', '2026-07-05 06:38:43'),
(21, 3, NULL, NULL, 16, 'QT-20260704-1527', 'งานสัมมนาและประชุม', '2026-10-15', '2026-10-10', 11214.95, 0.00, 785.05, 12000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-04 07:29:48', '2026-07-14 08:37:59'),
(24, 3, NULL, NULL, 18, 'QT-20260704-1534', 'งานเกษียร', '2026-09-19', '2026-09-19', 56074.77, 0.00, 3925.23, 60000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-04 07:35:28', '2026-07-15 04:39:54'),
(25, 3, NULL, 13, 19, 'QT-20260704-1556', 'งานสัมมนาและประชุม', '2026-09-16', '2026-09-16', 22607.48, 0.00, 1582.52, 24190.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:59:44', '2026-07-04 07:59:33', '2026-07-05 04:50:25'),
(26, 3, NULL, 15, 15, 'QT-20260704-1638', 'งานสัมมนาและประชุม', '2026-07-23', '2026-07-23', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 1, '', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-04 08:39:28', '2026-07-11 08:12:04'),
(27, 3, NULL, 12, 20, 'QT-20260704-1709', 'งานประชุม-สัมนา', '2026-07-28', '2026-07-29', 69214.95, 0.00, 4845.05, 74060.00, 'include', 'Approved', 0, '*** 2 บิล****\r\n-บิลแรกลูกค้าจะชำระ ในวันที่ 29/7/69 \r\n-บิลที่สองจะชำระไม่เกิน 30 วันเพราะต้องรอทางโรงเรียนดำเนิการ  (ยึดการเบิกบิลที่เคยดำเนินการมากับทางบริษัท)', 'โทรเข้า', 'ปิดงานสำเร็จ', NULL, NULL, '', 22, 17, NULL, '2026-07-04 09:12:06', '2026-07-13 08:57:03'),
(28, 3, NULL, 37, 21, 'QT-20260704-2016', 'สัมมนาคณะคุณหมอ', '2026-09-10', '2026-09-10', 68224.30, 0.00, 4775.70, 73000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 19:19:41', '2026-07-04 12:19:29', '2026-07-15 03:51:25'),
(29, 3, NULL, 12, 23, 'QT-20260704-2103', 'งานประชุม-สัมนา', '2026-07-30', '2026-07-31', 58448.60, 0.00, 4091.40, 62540.00, 'include', 'Approved', 0, '', 'โทรเข้า', 'ปิดงานสำเร็จ', NULL, NULL, '', 22, 17, NULL, '2026-07-04 13:09:47', '2026-07-18 08:25:32'),
(31, 3, NULL, 12, 20, 'QT-20260704-2234', 'งานประชุม-สัมนา', '2026-08-24', '2026-08-28', 179252.34, 0.00, 12547.66, 191800.00, 'include', 'Approved', 0, '***ลูกค้าจ่าย 40 % ของวันสิ้นสุดงาน ส่วนที่เหลือขอเครดิตไม่เกิน 30 วัน\r\n- กรณีเปลี่ยนแปลงจำนวนคน หรืออาหารและเครื่องดื่ม กรุณาแจ้งทางโรงแรม ล่วงหน้า 5-7 วัน หากเกินก าหนดโรงแรมฯ จะคิดค่าดำเนินการตามความเหมาะสม \r\n \r\nกรณีเกิดความเสียหายต่างๆต่ออุปกรณ์ ห้องพัก หรือสถานที่ของโรงแรมฯอันเนื่องมาจากผู ้พัก, ผู ้ร่วมงาน, คณะท างาน หรือเจ้าหน้าที่ของผู ้จัดงาน\r\n              \r\n              - \r\nทางโรงแรมฯขอคิดค่าเสียหายทั ้งหมดจากผู ้จัดงานตามความเหมาะสม\r\nกรณีมีความประสงค์จะเพิ ่มอาหารจากจ านวนการันตี โรงแรมฯขอสงวนสิทธิ ์ในการเปลี่ยนแปลงรายการอาหารโดยไม่ต้องแจ้งให้ทราบล่วงหน้า \r\nและขออภัยกรณีอาหารที่เพิ ่มออกมาล่าช้า', 'โทรเข้า', 'รอการตัดสินใจ', NULL, NULL, '', 22, 17, NULL, '2026-07-04 14:35:50', '2026-07-15 03:39:04'),
(32, 3, NULL, 33, 27, 'QT-20260705-1118', 'งานสัมมนาและประชุม', '2026-09-12', '2026-09-12', 23364.49, 0.00, 1635.51, 25000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-05 10:20:19', '2026-07-05 03:20:10', '2026-07-13 03:33:22'),
(33, 3, NULL, 30, 27, 'QT-20260705-1329', 'กิจกรรมสอนเสริมวันเสาร์ และกิจกรรมเพิ่มพูนศักยภาพ  ของนักเรียนระดับชั้นมัธยมศึกษาปีที่ 5 โครงการ SMA', '2026-07-03', '2026-07-05', 107476.64, 0.00, 7523.36, 115000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-05 12:45:45', '2026-07-05 05:45:29', '2026-07-13 03:31:09'),
(37, 3, NULL, 29, 29, 'QT-20260706-1710', 'งานสัมมนาและประชุม', '2026-07-11', '2026-07-12', 49065.42, 0.00, 3434.58, 52500.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-06 16:11:36', '2026-07-06 09:11:24', '2026-07-13 03:25:27'),
(39, 3, NULL, 12, 30, 'QT-20260706-1728', 'งานประชุม-สัมนา', '2026-07-26', '2026-07-26', 31401.87, 0.00, 2198.13, 33600.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 22, 17, NULL, '2026-07-06 09:31:02', '2026-07-18 04:59:25'),
(42, 3, NULL, 2, 31, 'QT-20260707-1110/2', 'สำนักงานภาคสงขลา (ความปลอดภัยอาชีวนามัย) รุ่น 5', '2026-08-07', '2026-08-07', 33644.86, 0.00, 2355.14, 36000.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 14 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:23:14', '2026-07-07 04:23:04', '2026-07-07 04:23:14'),
(43, 3, NULL, 2, 31, 'QT-20260707-1110/3', 'สำนักงานภาคสงขลา (ความปลอดภัยอาชีวนามัย) รุ่น 5', '2026-08-19', '2026-08-19', 33644.86, 0.00, 2355.14, 36000.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 26 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:26:18', '2026-07-07 04:26:10', '2026-07-07 04:26:18'),
(44, 3, NULL, 2, 31, 'QT-20260707-1110/4', 'สำนักงานภาคสงขลา (ความปลอดภัยอาชีวนามัย) รุ่น 5', '2026-08-21', '2026-08-21', 33644.86, 0.00, 2355.14, 36000.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 27 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:27:52', '2026-07-07 04:27:46', '2026-07-07 04:27:52'),
(45, 3, NULL, 2, 31, 'QT-20260707-1110/5', 'สำนักงานภาคสงขลา (ดับเพลิง+อาชีวนามัย) รุ่น 3', '2026-08-03', '2026-08-04', 66168.22, 0.00, 4631.78, 70800.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 10 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:30:07', '2026-07-07 04:29:59', '2026-07-07 04:30:07'),
(46, 3, NULL, 2, 31, 'QT-20260707-1110/6', 'สำนักงานภาคสงขลา (ดับเพลิง+อาชีวนามัย) รุ่น 3', '2026-08-10', '2026-08-11', 66168.22, 0.00, 4631.78, 70800.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 17 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:31:18', '2026-07-07 04:31:11', '2026-07-07 04:31:18'),
(47, 3, NULL, 2, 31, 'QT-20260707-1110/7', 'สำนักงานภาคสงขลา (ดับเพลิง+อาชีวนามัย) รุ่น 3', '2026-08-17', '2026-08-18', 66168.22, 0.00, 4631.78, 70800.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 24 ส.ค. 69', '', '', NULL, NULL, '', 21, 17, '2026-07-07 11:32:35', '2026-07-07 04:32:09', '2026-07-07 04:32:35'),
(48, 3, NULL, 2, 31, 'QT-20260707-1110/8', 'สำนักงานภาคสงขลา (อาชีวนามัย) รุ่น 5', '2026-09-03', '2026-09-04', 66168.22, 0.00, 4631.78, 70800.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 10 ก.ย. 69', '', '', NULL, NULL, '', 17, 17, '2026-07-07 11:34:33', '2026-07-07 04:34:21', '2026-07-07 04:34:33'),
(49, 3, NULL, 2, 31, 'QT-20260707-1110/8-5026', 'สำนักงานภาคสงขลา (ดับเพลิงเบื้องต้น) รุ่น 3', '2026-07-20', '2026-07-20', 33644.86, 0.00, 2355.14, 36000.00, 'include', 'Approved', 0, '- จัดโต๊ะ Class Room 60 ท่าน\r\n- ข้อความโฟมตกแต่งเวที, \r\n- WI-FI Internet, พร้อมจอ Projecter, เครื่องเสียงพร้อมไมโครโฟน\r\n- กระดาษA4+ดินสอ และน้ำดื่ม\r\n\r\n\r\n*วางบิล 7 วัน ชำระไม่เกินวันที่ 28 ก.ย. 69', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-07 04:50:26', '2026-07-15 04:45:33'),
(51, 3, NULL, 17, 32, 'QT-20260707-1636', 'งานเลี้ยงสังสรรค์กีฬาสี', '2026-10-02', '2026-10-02', 56074.77, 0.00, 3925.23, 60000.00, 'include', 'Approved', 0, '- ป้ายโฟม 200\r\n- Back Drop 1000 บาท\r\n- คาราโอเกะ', '', '', NULL, NULL, '', 21, 17, '2026-07-07 15:39:58', '2026-07-07 08:39:18', '2026-07-07 08:39:58'),
(52, 3, NULL, 34, 33, 'QT-20260707-1813', 'งานเลี้ยงสังสรรค์', '2026-07-10', '2026-07-10', 21028.04, 0.00, 1471.96, 22500.00, 'include', 'Approved', 1, '- จัดโต๊ะกลม 5 โต๊ะ\r\n- คาราโอเกะ\r\n- ป้ายโซม\r\n\r\n*ราคารวมนำเข้าแอลกอฮอล์ ', '', '', NULL, NULL, '', 21, 17, '2026-07-07 17:18:21', '2026-07-07 10:18:04', '2026-07-13 03:36:14'),
(53, 3, NULL, 23, 15, 'QT-20260713-0856/1', 'BNI', '2026-08-06', '2026-08-06', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-13 09:00:23', '2026-07-13 01:59:01', '2026-07-13 02:00:23'),
(54, 3, NULL, 27, 15, 'QT-20260704-1518/2', 'งานสัมมนาและประชุม', '2026-08-13', '2026-08-13', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-13 09:00:16', '2026-07-13 01:59:33', '2026-07-13 02:00:16'),
(55, 3, NULL, 28, 15, 'QT-20260704-1518/3', 'งานสัมมนาและประชุม', '2026-08-20', '2026-08-20', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-13 09:00:07', '2026-07-13 01:59:51', '2026-07-13 03:23:58'),
(57, 3, NULL, 22, 35, 'QT-20260706-1416/2', 'งานแต่งงานคุณต๋อม-คุณน๊อต', '2026-08-14', '2026-08-14', 169345.79, 0.00, 11854.21, 181200.00, 'include', 'Approved', 0, '- เริ่ม เวลา 07.00 - 16.00 น.\r\n- เบรกเช้า ขนม 2 อย่าง เกิน 60 ท่าน เพิ่มท่านละ 100 บาท\r\n- เรทเช็คเอ้า 14.00 น. หลัง 14.00 น. คิดเป็น 1 วัน \r\n- ซักซ้อมก่อนวันงานช่วงเย็น 13/08/69 ใช้เวลา 1 ชม.\r\n**เงื่อนไขการชำระเงิน\r\n1. งวดที่ 1 เป็นจำนวน 10,000 บาท (ชำระเรียบร้อยแล้ววันที่ 21/03/69)\r\n2. งวดที่ 2 (ก่อนวันจัดงาน) เป็นจำนวน 40,000 บาท (ชำระเรียบร้อยแล้ววันที่ 15/07/69)\r\n3. งวดที่ 3 (วันจัดงาน) ชำระส่วนที่เหลือชำระก่อนหรือภายในวันจัดงาน\r\n4. หากมีค่าใช้จ่ายเพิ่มเติมจากการเปลี่ยนแปลงรายการอาหาร เครื่องดื่ม จำนวนแขก หรือบริการอื่น ๆ บริษัทจะออกใบแจ้งหนี้เพิ่มเติม และผู้ว่าจ้างตกลงชำระภายในวันจัดงานหรือภายในระยะเวลาที่กำหนด', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-13 09:27:48', '2026-07-15 11:30:36'),
(58, 3, NULL, 31, 36, 'QT-20260714-1438/1', 'งานประชุม-สัมนา', '2026-08-29', '2026-08-30', 66654.21, 0.00, 4665.79, 71320.00, 'include', 'Approved', 0, 'ขอเครดิตไม่เกิน 30 วัน ', '', '', NULL, NULL, '', 22, 17, NULL, '2026-07-14 07:51:52', '2026-07-16 03:38:38'),
(59, 3, NULL, 14, 31, 'QT-20260704-1521/2', 'งานสัมมนาและประชุม', '2026-07-21', '2026-07-21', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-15 11:48:31', '2026-07-15 04:46:57', '2026-07-15 04:48:31'),
(60, 3, NULL, 15, 31, 'QT-20260704-1638/2', 'งานสัมมนาและประชุม', '2026-07-22', '2026-07-22', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-15 11:48:26', '2026-07-15 04:47:47', '2026-07-15 04:48:26'),
(61, 3, NULL, 16, 37, 'QT-20260715-1204/1', 'คอนเสิร์ตโซติจูด', '2026-09-02', '2026-09-02', 28037.38, 0.00, 1962.62, 30000.00, 'include', 'Approved', 0, '- Set Up 12.00 น. ไม่เปิดแอร์ เปิดแอร์ 15.00 น.\r\n- พรหมเปื้อน สกปรก มีค่าปรับครึ่งฟอลล์ 25,000 บาท เต็มฟอลล์ 35,000 บาท\r\n- น้ำดื่ม น้ำอัดลม ทางโรงแรมจำหน่าย\r\n- ไม่อนุญาตให้สูบบุหรี่ทุกประเภทภายในอาคาร\r\n\r\n**เงื่อนไขการชำระเงิน**\r\nมัดจำ 30% ภายในวันที่ 31 ก.ค. 69 จำนวน 9,000 บาท\r\nส่วนที่เหลือชำระหลังเสร็จงาน', '', '', NULL, NULL, '', 21, 17, NULL, '2026-07-15 05:07:43', '2026-07-15 05:09:57');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `item_name` text NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `item_type` enum('Food','Beverage','Room','Equipment','Other') DEFAULT 'Food'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quote_id`, `item_name`, `quantity`, `unit_price`, `total_price`, `item_type`) VALUES
(330, 21, 'ห้องตะวัน จำนวน 50 ท่าน 13.00-17.00 น.\r\nเบรก 2 ชิ้น \r\nท่านที่ 51 บวกเพิ่ม  100', 1, 12000.00, 12000.00, 'Food'),
(212, 27, 'เบรกบ่าย จำนวน  114 ท่าน (ขนม 1 อย่าง) จำนวน  2 วัน', 228, 50.00, 11400.00, 'Food'),
(213, 27, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท\r\nไม่รวมอาหารเช้า, อาหารเช้า บริการแบบ Room Services ราคา 200 บาท\r\n', 1, 1100.00, 1100.00, 'Food'),
(141, 51, 'โต๊ะจีน โต๊ะละ 10 ท่าน อาหาร 4 อย่าง+ข้าวสวย+ของหวาน+เครื่องดื่ม', 20, 3000.00, 60000.00, 'Food'),
(335, 24, ' Thai Set (7 อย่าง) +  Soft Drink 18.00-22.00 น.', 15, 4000.00, 60000.00, 'Food'),
(38, 25, 'คอฟฟี่ เบรกเช้า (ขนม 1 อย่าง) กลุ่ม A', 148, 80.00, 11840.00, 'Food'),
(39, 25, 'คอฟฟี่ เบรกบ่าย (ขนม 1 อย่าง) กลุ่ม B', 135, 80.00, 10800.00, 'Food'),
(40, 25, 'คอฟฟี่ เบรกเช้า (ขนม 1 อย่าง) กลุ่ม A', 5, 80.00, 400.00, 'Food'),
(41, 25, 'คอฟฟี่ เบรกบ่าย (ขนม 1 อย่าง) กลุ่ม B', 5, 80.00, 400.00, 'Food'),
(42, 25, 'อาหารกลางวันวิทยากร', 5, 150.00, 750.00, 'Food'),
(156, 26, 'ข้าวกล่อง', 45, 270.00, 12150.00, 'Food'),
(29, 20, 'เบรก : สาคูไส้ไก่', 20, 100.00, 2000.00, 'Food'),
(30, 20, 'เบรก : เค้กใบเตยหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(31, 20, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', 20, 270.00, 5400.00, 'Food'),
(32, 20, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', 25, 270.00, 6750.00, 'Food'),
(155, 26, 'เบรกเช้า', 45, 100.00, 4500.00, 'Food'),
(28, 19, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', 25, 270.00, 6750.00, 'Food'),
(25, 19, 'เบรค : ข้าวเหนียวคอนโด', 20, 100.00, 2000.00, 'Food'),
(26, 19, 'เบรค : เค้กส้มหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(27, 19, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', 20, 270.00, 5400.00, 'Food'),
(210, 27, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน จำนวน 114 ท่าน  จำนวน 2 วัน\r\n', 228, 220.00, 50160.00, 'Food'),
(211, 27, 'เบรกเช้า จำนวน  114 ท่าน (ขนม 1 อย่าง) จำนวน  2 วัน', 228, 50.00, 11400.00, 'Food'),
(57, 28, 'เบรกเช้า ขนม 1 อย่าง+ ชา กาแฟ', 50, 90.00, 4500.00, 'Food'),
(58, 28, 'เบรกบ่าย ขนม 1 อย่าง+ ชา กาแฟ', 50, 90.00, 4500.00, 'Food'),
(59, 28, 'อาหารเที่ยงบุฟเฟต์ 7 อย่าง + น้ำดื่ม', 50, 400.00, 20000.00, 'Food'),
(60, 28, 'ห้องพัก Standard Room จำนวน 2 คืน', 20, 2200.00, 44000.00, 'Food'),
(375, 29, 'เบรกบ่าย จำนวน 96 ท่าน (ขนม 1 อย่าง) 2 วัน', 192, 50.00, 9600.00, 'Food'),
(376, 29, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท\r\nไม่รวมอาหารเช้า, อาหารเช้า บริการแบบ Room Services ราคา 200 บาท', 1, 1100.00, 1100.00, 'Food'),
(373, 29, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน จำนวน 96  ท่าน 2 วัน', 192, 220.00, 42240.00, 'Food'),
(374, 29, 'เบรกเช้า จำนวน96 ท่าน (ขนม 1 อย่าง) 2 วัน\r\n', 192, 50.00, 9600.00, 'Food'),
(369, 39, 'เบรคบ่าย', 200, 80.00, 16000.00, 'Food'),
(370, 39, 'ค่าห้องประชุมเหมาจ่าย ครึ่งวันบ่าย', 1, 9000.00, 9000.00, 'Food'),
(345, 60, 'ข้าวกล่อง', 45, 270.00, 12150.00, 'Food'),
(347, 61, 'เหมาห้องตะวัน 18.00-22.00 น.', 1, 30000.00, 30000.00, 'Food'),
(358, 57, 'ห้องพัก Standart Single Room', 2, 1100.00, 2200.00, 'Food'),
(372, 39, 'ค่าห้องพักเจ้าหน้าที่ (ใช้ห้องManonta เนื่องจากที่โรงแรมเอสเต็ม) Standard \r\n\r\n เตียงคู่ 2 คืน 700*4*2คืน  ( 25 out 27 )', 8, 700.00, 5600.00, 'Food'),
(74, 32, 'ห้องประชุมเต็มวัน (ลงจองไว้ก่อน) รายละเอียดจะตามมา', 1, 25000.00, 25000.00, 'Food'),
(75, 33, 'เบรกเช้า ขนม 1 อย่าง + ชา กาแฟ (105*3)', 315, 50.00, 15750.00, 'Food'),
(76, 33, 'เบรกบ่าย ขนม 1 อย่าง + ชา กาแฟ (105*3)', 315, 50.00, 15750.00, 'Food'),
(77, 33, 'อาหารกลางวันบุฟเฟต์ 3 อย่าง (105*3)', 315, 220.00, 69300.00, 'Food'),
(78, 33, 'อาหารไทยเซ็ท', 3, 3000.00, 9000.00, 'Food'),
(79, 33, 'ห้องพักสำหรับวิทยากร จำนวน 1 ห้อง + 3 ABF  เช็คอิน 2/7/69 - เช็คเอ้ำท์ 5/7/69 (3คืน)', 4, 1300.00, 5200.00, 'Food'),
(362, 58, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท จำนวน 1 ท่าน *2 วัน\r\nไม่รวมอาหารเช้า(อาหารเช้าท่านละ 150 บาท)', 2, 1100.00, 2200.00, 'Food'),
(341, 59, 'เบรก : เค้กใบเตยหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(342, 59, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', 20, 270.00, 5400.00, 'Food'),
(91, 37, 'ประชุม', 1, 52500.00, 52500.00, 'Food'),
(340, 59, 'เบรก : สาคูไส้ไก่', 20, 100.00, 2000.00, 'Food'),
(336, 49, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล 60', 60, 90.00, 5400.00, 'Food'),
(337, 49, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล 60', 60, 90.00, 5400.00, 'Food'),
(338, 49, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม 60', 60, 400.00, 24000.00, 'Food'),
(339, 49, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(101, 42, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(102, 42, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(103, 42, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม ', 60, 400.00, 24000.00, 'Food'),
(104, 42, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Twin Room ( มี 21 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(105, 43, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(106, 43, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(107, 43, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม ', 60, 400.00, 24000.00, 'Food'),
(108, 43, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(109, 44, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(110, 44, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล ', 60, 90.00, 5400.00, 'Food'),
(111, 44, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม ', 60, 400.00, 24000.00, 'Food'),
(112, 44, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(113, 45, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(114, 45, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(115, 45, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม 60*2', 120, 400.00, 48000.00, 'Food'),
(116, 45, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(117, 46, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(118, 46, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(119, 46, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม 60*2', 120, 400.00, 48000.00, 'Food'),
(120, 46, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(128, 47, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(127, 47, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม 60*2', 120, 400.00, 48000.00, 'Food'),
(125, 47, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(126, 47, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(129, 48, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(130, 48, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล 60*2', 120, 90.00, 10800.00, 'Food'),
(131, 48, 'อาหารกลางวัน บุฟเฟ่ต์ 7 อย่าง + น้ำดื่ม 60*2', 120, 400.00, 48000.00, 'Food'),
(132, 48, 'ห้องพัก Standard Twin Room ( มี 15 ห้อง )\r\nห้องพัก Standard Single Room ( มี 22 ห้อง ) \r\n*ราคาต่อคืน', 1, 1200.00, 1200.00, 'Food'),
(142, 52, 'บุฟเฟ่ต์อาหารไทย 7 อย่าง + Soft Drink ', 50, 450.00, 22500.00, 'Food'),
(371, 39, 'ค่าห้องพัก วิทยากร จูเนีย ( ไม่รวมอาหารเช้า ) 25 out 27 ', 2, 1500.00, 3000.00, 'Food'),
(344, 60, 'เบรกเช้า', 45, 100.00, 4500.00, 'Food'),
(357, 57, 'Set Chinese 30 + Soft Drink', 30, 4700.00, 141000.00, 'Food'),
(343, 59, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', 25, 270.00, 6750.00, 'Food'),
(360, 58, 'เบรกบ่าย ขนม 1อย่าง+ชา กาแฟ โอวันติล วันที่ 29-30 สิงหาคม จำนวน 108 ท่าน * 2วัน', 216, 50.00, 10800.00, 'Food'),
(172, 53, 'BNI', 1, 16650.00, 16650.00, 'Food'),
(173, 54, 'เบรค : ข้าวเหนียวคอนโด', 20, 100.00, 2000.00, 'Food'),
(174, 54, 'เบรค : เค้กส้มหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(175, 54, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', 20, 270.00, 5400.00, 'Food'),
(176, 54, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', 25, 270.00, 6750.00, 'Food'),
(177, 55, 'เบรค : ข้าวเหนียวคอนโด', 20, 100.00, 2000.00, 'Food'),
(178, 55, 'เบรค : เค้กส้มหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(179, 55, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', 20, 270.00, 5400.00, 'Food'),
(180, 55, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', 25, 270.00, 6750.00, 'Food'),
(356, 57, 'Packet Wedding S Hadyai Hotel สำหรับหมั้นเช้า - ฉลองมงคลสมรสเที่ยง\r\n- ชุดพิธีหลังน้ำพระพุทธมนต์\r\n- อาหารตักบาตร 1 ชุด\r\n- อาหารไหว้พระพุทธ 1 ชุด\r\n- อาหารไหว้ตายาย 1 ชุด\r\n- อาหารถวายเพล 1 ชุด\r\n- เบรกเช้า ขนม 2 อย่าง ชา กาแฟ โอวัลติล 60 ท่าน\r\n- ที่จอดรถบ่าว - สาว 1 คัน\r\n- เครื่องแสง, สี, บับเบิ้ล, ไฟฟอลโล่\r\n- Projecter Presentation\r\n- คาราโอเกะ\r\n- ป้ายชื่อตั้งโต๊ะ\r\n- ฟรี Excutive Room 1 ห้อง\r\n- ฟรี Standart Twin Room ห้อง\r\n- ฟรีค่านำเข้าแอลกอฮอล์', 1, 38000.00, 38000.00, 'Food'),
(361, 58, 'บุฟเฟ่อาหาร กลางวัน 3 อย่าง พร้อมขนมหวาน วันที่ 29-30 สิงหาคม จำนวน 108 ท่าน * 2วัน', 216, 220.00, 47520.00, 'Food'),
(359, 58, 'เบรกเช้า ขนม 1อย่าง+ชา กาแฟ โอวันติล วันที่ 29-30 สิงหาคม จำนวน 108 ท่าน * 2วัน', 216, 50.00, 10800.00, 'Food'),
(331, 31, 'เบรกเช้า ขนม 1 อย่าง ชา กาแฟ โอวันติล  วันที่ 24-28  สิงหาคม จำนวน 113 ท่าน * 5วัน', 565, 50.00, 28250.00, 'Food'),
(332, 31, 'เบรกบ่าย ขนม 1 อย่าง ชา กาแฟ โอวันติล   วันที่ 24-28  สิงหาคม  จำนวน 113 ท่าน * 5วัน', 565, 50.00, 28250.00, 'Food'),
(333, 31, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน วันที่ 24-28  สิงหาคม  จำนวน 113 ท่าน * 5วัน', 565, 220.00, 124300.00, 'Food'),
(334, 31, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท จำนวน 2 ท่าน * 5 วัน\r\nไม่รวมอาหารเช้า (  อาหารเช้า ท่านละ 150 )', 10, 1100.00, 11000.00, 'Food');

-- --------------------------------------------------------

--
-- Table structure for table `sales_logs`
--

CREATE TABLE `sales_logs` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_targets`
--

CREATE TABLE `sales_targets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `target_month` tinyint(4) NOT NULL,
  `target_year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signatures`
--

CREATE TABLE `signatures` (
  `id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `users_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `signatures`
--

INSERT INTO `signatures` (`id`, `path`, `created_at`, `users_id`) VALUES
(4, 'uploads/signatures/sig_7_1772180774.png', '2026-02-27 08:26:14', 7),
(5, 'uploads/signatures/sig_1_1774507038.png', '2026-03-26 06:37:19', 1),
(6, 'uploads/signatures/sig_6_1772181394.png', '2026-02-27 08:36:34', 6),
(7, 'uploads/signatures/sig_8_1772181939.png', '2026-02-27 08:45:39', 8),
(8, 'uploads/signatures/sig_17_1783936770.png', '2026-07-13 09:59:30', 17),
(9, 'uploads/signatures/sig_19_1782038062.png', '2026-06-21 10:34:22', 19),
(10, 'uploads/signatures/sig_21_1783150561.png', '2026-07-04 07:36:01', 21);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `line_user_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `line_user_id`, `name`) VALUES
(1, 'admin', '81dc9bdb52d04dc20036dbd8313ed055', 'Admin', 'U073f536d4c4db4b8fe3345bf82107c50', 'นาย วทัญญู ต้นจาน'),
(13, 'lek', '$2y$10$BhTMkOjrOiWypab4qnBAM.Z.mbkxvGS.OrY4q8/dpzuaj03mILqpS', 'Procurement', NULL, 'พี่เล็ก'),
(14, 'eng', '$2y$10$FjuNX84FxXyHxvMFxRjzUeuUoKTDvLqQmbsfcxdAxh/VI0BuESz7i', 'Technician', 'U073f536d4c4db4b8fe3345bf82107c50', 'ช่าง shotel'),
(15, 'hk', '$2y$10$6/UTDZJn7fD087XXJslQteuCQm/3gA3BBP18vIfptaRTFz2TwnAx.', 'Housekeeping', NULL, 'แม่บ้าน'),
(16, 'bk', '$2y$10$4Z1k.rjFp3YTB32XddZT4OUF12uiBtts2kgWQD4l7z/Jrr0bosUzy', 'Banquet_Staff', 'U073f536d4c4db4b8fe3345bf82107c50', 'บังสิท'),
(17, 'gm', '$2y$10$8epNgUQEL0pk4PQpUB5udOXK0PB7sfM.XhqsOrsoi72Ysj/4u51r6', 'GM', 'U073f536d4c4db4b8fe3345bf82107c50', 'คุณผู้จัดการ '),
(21, 'sugus', '$2y$10$84Epghn/x3GpfuC6YJPSWOdb0Z.j4yrnzcGm.jOvmx/3Gzx5kgSN.', 'Staff', 'U77a622428a507b5c7c553ecc1eab053a', 'น.ส. นิยะดา ชาปาน '),
(22, 'bow', '$2y$10$/GBCBHA4mE7pnRoLTwJ0F.8VjLrdOfDZe6NLX2bhzt7QKSIDGpMoe', 'Staff', '', 'น.ส.ประวีณา เพ็ชรสมุทร'),
(23, 'pew', '$2y$10$sEzRb1ugpPTxnz5ZUtzl4.i2P/dXcebSQ2l7Tml30ygshaSEbZ97O', 'Procurement', NULL, 'วนิดา '),
(24, 'jele', '$2y$10$Dc71SHhGlAsyhhiaZ8rgIurGbY5lxPlQvfXRFAAp4KN5stZWH/tfu', 'Staff', '', 'พี่เจเล่'),
(25, 'poy', '$2y$10$uOog0dWMJabdqmnC.2Xptu0oFrcbM3Nyr0JtpLlEb3St1qNhI9TkO', 'GM', '', 'พี่ปอย'),
(26, 'PEWW', '$2y$10$NfeV01buL.opcG/A3M654uqLBacHZXd.RLuubXj3blUsnRE1IgTuG', 'Procurement', '', 'พีป่ิว'),
(27, 'pai', '$2y$10$7MsUAdpYeU9YWEMW5zPszOh8CpQdJqzipwZ9dxqpn2UrWO482k5Xm', 'GM', 'U073f536d4c4db4b8fe3345bf82107c50', 'พี่ปาย');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_projects`
--
ALTER TABLE `event_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `functions`
--
ALTER TABLE `functions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quotation_id` (`quotation_id`);

--
-- Indexes for table `function_breaks`
--
ALTER TABLE `function_breaks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_function_break` (`function_id`);

--
-- Indexes for table `function_finance`
--
ALTER TABLE `function_finance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `function_id` (`function_id`);

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
-- Indexes for table `function_menu_details`
--
ALTER TABLE `function_menu_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_function_menu` (`function_id`);

--
-- Indexes for table `function_schedules`
--
ALTER TABLE `function_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `function_id` (`function_id`);

--
-- Indexes for table `function_status_log`
--
ALTER TABLE `function_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_func` (`function_id`),
  ADD KEY `idx_time` (`changed_at`);

--
-- Indexes for table `function_types`
--
ALTER TABLE `function_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_break_types`
--
ALTER TABLE `master_break_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_checklist_bk`
--
ALTER TABLE `master_checklist_bk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_checklist_hk`
--
ALTER TABLE `master_checklist_hk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_checklist_mt`
--
ALTER TABLE `master_checklist_mt`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_menu_categories`
--
ALTER TABLE `master_menu_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_menu_types`
--
ALTER TABLE `master_menu_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_menu_type_category` (`category_id`);

--
-- Indexes for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quote_no` (`quote_no`),
  ADD KEY `function_id` (`function_id`),
  ADD KEY `idx_quote_project` (`project_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quote_id` (`quote_id`);

--
-- Indexes for table `sales_logs`
--
ALTER TABLE `sales_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales_targets`
--
ALTER TABLE `sales_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month_year` (`user_id`,`target_month`,`target_year`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `event_projects`
--
ALTER TABLE `event_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `functions`
--
ALTER TABLE `functions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `function_breaks`
--
ALTER TABLE `function_breaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `function_finance`
--
ALTER TABLE `function_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `function_menus`
--
ALTER TABLE `function_menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT for table `function_menu_details`
--
ALTER TABLE `function_menu_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `function_schedules`
--
ALTER TABLE `function_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=326;

--
-- AUTO_INCREMENT for table `function_status_log`
--
ALTER TABLE `function_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `function_types`
--
ALTER TABLE `function_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `master_break_types`
--
ALTER TABLE `master_break_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `master_checklist_bk`
--
ALTER TABLE `master_checklist_bk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_checklist_hk`
--
ALTER TABLE `master_checklist_hk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_checklist_mt`
--
ALTER TABLE `master_checklist_mt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `master_menu_categories`
--
ALTER TABLE `master_menu_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `master_menu_types`
--
ALTER TABLE `master_menu_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=377;

--
-- AUTO_INCREMENT for table `sales_logs`
--
ALTER TABLE `sales_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_targets`
--
ALTER TABLE `sales_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `signatures`
--
ALTER TABLE `signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

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

--
-- Constraints for table `master_menu_types`
--
ALTER TABLE `master_menu_types`
  ADD CONSTRAINT `fk_menu_type_category` FOREIGN KEY (`category_id`) REFERENCES `master_menu_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`function_id`) REFERENCES `functions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_targets`
--
ALTER TABLE `sales_targets`
  ADD CONSTRAINT `fk_target_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
