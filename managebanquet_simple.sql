-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 05, 2026 at 12:59 PM
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
(3, 'SHotel Hatyai', 'Front', '074261702', 'gsa@shadyaihotel.com', '220 ถ. ประชาธิปัตย์ ตำบล หาดใหญ่ อำเภอหาดใหญ่ สงขลา 90110', 'img/logo_1772157252_images (1).jfif', '2026-02-25 06:48:12'),
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
(11, 'นาย ทวี ทรัพย์กลิ่น', '453453', 'ฟหกหกด', 'creeda 725', '5455', 'creeda725@gmail.com', NULL, '2026-06-09 02:47:27'),
(12, 'คุณ สมชาย', '', '', '', '', '', '', '2026-06-21 10:37:34'),
(13, 'นิยะดา', '', '', '', '', '', 'ซูกัส', '2026-07-02 08:07:27'),
(21, 'คณะแพทย์ มอ.', '', '', 'หมอวรา', '0867833060', '', 'ซูกัส', '2026-07-04 12:16:11'),
(15, 'BNI', '', '', 'คุณปอย', '', '', 'ซูกัส', '2026-07-04 07:05:48'),
(16, 'OEXN', '', '', 'คุณเจน', '0652946289', '', 'ซูกัส', '2026-07-04 07:26:29'),
(17, 'SSK2026', '', '', 'คุณเต้ย', '080139639', '', 'ซูกัส', '2026-07-04 07:31:49'),
(18, 'คณะแพทย์ มอ.', '', '', 'คุณอรพรรณ', '0859252226', '', 'ซูกัส', '2026-07-04 07:34:20'),
(19, 'บมจ.ซีพี ออลล์ (สำนักงานใหญ่)', ' 1075420000011', '313 อาคาร ซี.พี.ทาวเวอร์ ชั้น 24 ถนนสีลม แขวงสีลม เขตบางรัก กรุงเทพฯ 10500', 'อ.นุ', '0934134000', '', 'ซูกัส', '2026-07-04 07:56:32'),
(20, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' โรงเรียนหาดใหญ่วิทยาลัย', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 09:08:16'),
(22, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 12:57:49'),
(23, ' โรงเรียนหาดใหญ่วิทยาลัย', '', '', 'ครูเจี๊ยบ', '', '', 'โบว์', '2026-07-04 13:00:14'),
(24, 'โรงเรียนหาดใหญ่วิทยาลัย', '', 'โรงเรียนหาดใหญ่วิทยาลัย', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 13:03:46'),
(25, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' 468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 13:22:46'),
(26, ' โรงเรียนหาดใหญ่วิทยาลัย', '', ' 468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูขวัญ', '', '', 'โบว์', '2026-07-04 14:32:54'),
(27, 'โรงเรียนหาดใหญ่วิทยาลัย', '0994000580746', '468 ถ.เพชรเกษม ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110', 'ครูแพน', '0611757287', '', 'ซูกัส', '2026-07-05 03:18:41');

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
(13, 'งานสัมมนาและประชุม1', 19, 3, 'Pending', 'นาย วทัญญู ต้นจาน', '2026-07-05 04:50:25', '2026-07-05 04:57:54');

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

INSERT INTO `functions` (`id`, `project_id`, `quotation_id`, `version_no`, `is_approved`, `draft_name`, `function_code`, `company_id`, `customer_id`, `function_type_id`, `function_name`, `event_date`, `start_time`, `end_time`, `booking_name`, `organization`, `phone`, `room_name`, `room_id`, `booking_room`, `pax`, `deposit`, `total_amount`, `main_kitchen_remark`, `banquet_style`, `equipment`, `remark`, `lead_source`, `result`, `inspection_date`, `follow_up_date`, `backdrop_detail`, `backdrop_img`, `hk_florist_detail`, `file_attachment1`, `file_attachment2`, `file_attachment3`, `created_at`, `approve`, `status`, `status_updated_at`, `created_by`, `created_by_id`, `approve_date`, `approve_by`, `modify`) VALUES
(136, 12, 19, 1, 1, 'Draft V1', '00136/0407', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-09 06:00:00', '2026-07-09 12:00:00', 'BNI', 'Business Network International (BNI Ignite Songkhla)', '', NULL, 5, '03/07', 45, 0.00, 16650.00, '', 'จัดโต๊ะ + เก้าอี้ ผู้เข้าประชุมแบบ U-Shape จำนวน 45 ที่นั่ง\r\nจัดโต๊ะลงทะเบียน + เก้าอี้ หน้าห้องประชุม จำนวน 4 ที่นั่ง\r\nจัดโต๊ะ + เก้าอี้ สำหรับวิทยากร บนเวที จำนวน 1 ที่นั่ง\r\nจัดเตรียมแฟ้มรองเซนต์ โต๊ะลงทะเบียน 2 เล่ม\r\nจัดเตรียม กระดาษ ดินสอ น้ำดื่ม สำหรับผู้ประชุมให้เพียงพอ', 'Wifi ในห้องประชุม LCD + Projector / สายเสียง / สายภาพ /ไวนิลติดผนัง หน้าห้องตะวัน ไมค์ลอย 5ตัว เตรียม Podium + Mic (บนเวที) 1 ตัว ', '', '', '', NULL, NULL, 'BNI – The World’s Leading Business Networking\r\nand Referral Organization\r\n09 JULY 2026\r\nS Hadyai Hotel, Songkhla', 'uploads/backdrop_1783152798.png', 'จัดเตียมดอกไม้โต๊ะลงทะเบียน\r\nจัดเตรียมดอกไม้ติด Podium \r\nจัดเตรียมดอกไม้โต๊ะวิทยากร \r\nดูแลความสะอาดบริเวณห้องงานประชุม / ห้องน้ำ / ฟร้อน', '', '', '', '2026-07-04 08:13:18', 1, 'Confirmed', NULL, 'พี่ซูกัส', 21, NULL, NULL, '2026-07-05 02:43:34'),
(137, 12, 20, 1, 0, 'Draft V1', '00137/0407', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 06:00:00', '2026-07-16 12:00:00', 'BNI', 'Business Network International (BNI Ignite Songkhla)', '', NULL, NULL, '04//07', 45, 0.00, 16650.00, '', '-จัดโต๊ะ + เก้าอี้ ผู้เข้าประชุมแบบ U-Shape จำนวน 45 ที่นั่ง\r\n-จัดโต๊ะลงทะเบียน + เก้าอี้ หน้าห้องประชุม จำนวน 4 ที่นั่ง\r\n-จัดโต๊ะ + เก้าอี้ สำหรับวิทยากร บนเวที จำนวน 1 ที่นั่ง\r\n-จัดเตรียมแฟ้มรองเซนต์ โต๊ะลงทะเบียน 2 เล่ม\r\n-จัดเตรียม กระดาษ ดินสอ น้ำดื่ม สำหรับผู้ประชุมให้เพียงพอ\r\n-จัดโต๊ะกลม+เก้าอี้ จำนวน 5 โต๊ะ สำหรับนั่งทานข้าว', 'Wifi ในห้องประชุม LCD + Projector / สายเสียง / สายภาพ/ไวนิลติดผนัง หน้าห้องตะวัน ไมค์ลอย 5ตัว เตรียม Podium + Mic (บนเวที) 1 ตัว ', '', '', '', NULL, NULL, '\r\n\r\nBNI – The World’s Leading Business Networking\r\nand Referral Organization\r\n16 JULY 2026\r\nS Hadyai Hotel, Songkhla', 'uploads/backdrop_1783170063.png', '-จัดเตียมดอกไม้โต๊ะลงทะเบียน \r\n-จัดเตรียมดอกไม้ติด Podium \r\n-จัดเตรียมดอกไม้โต๊ะวิทยากร \r\n-ดูแลความสะอาดบริเวณห้องงานประชุม / ห้องน้ำ / ฟร้อน\r\n-ดูแลความเรียบร้อยที่จอดรถของโรงแรม', NULL, NULL, NULL, '2026-07-04 13:01:03', 0, 'Pending', NULL, 'พี่ซูกัส', 21, NULL, NULL, '2026-07-04 13:01:03'),
(138, 12, 20, 1, 0, 'Draft V1', '00138/0407', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 06:00:00', '2026-07-16 12:00:00', 'BNI', '', '', NULL, NULL, '05/07', 45, 0.00, 16650.00, '', '-จัดโต๊ะ + เก้าอี้ ผู้เข้าประชุมแบบ U-Shape จำนวน 45 ที่นั่ง\r\n-จัดโต๊ะลงทะเบียน + เก้าอี้ หน้าห้องประชุม จำนวน 4 ที่นั่ง\r\n-จัดโต๊ะ + เก้าอี้ สำหรับวิทยากร บนเวที จำนวน 1 ที่นั่ง\r\n-จัดเตรียมแฟ้มรองเซนต์ โต๊ะลงทะเบียน 2 เล่ม\r\n-จัดเตรียม กระดาษ ดินสอ น้ำดื่ม สำหรับผู้ประชุมให้เพียงพอ\r\n-จัดโต๊ะกลม+เก้าอี้ จำนวน 5 โต๊ะ สำหรับนั่งทานข้าว', 'Wifi ในห้องประชุม LCD + Projector / สายเสียง / สายภาพ /ไวนิลติดผนัง หน้าห้องตะวัน ไมค์ลอย 5ตัว เตรียม Podium + Mic (บนเวที) 1 ตัว ', '', '', '', NULL, NULL, 'BNI – The World’s Leading Business Networking\r\nand Referral Organization\r\n16 JULY 2026\r\nS Hadyai Hotel, Songkhla', 'uploads/backdrop_1783171244.png', '-จัดเตียมดอกไม้โต๊ะลงทะเบียน \r\n-จัดเตรียมดอกไม้ติด Podium \r\n-จัดเตรียมดอกไม้โต๊ะวิทยากร \r\n-ดูแลความสะอาดบริเวณห้องงานประชุม / ห้องน้ำ / ฟร้อน', NULL, NULL, NULL, '2026-07-04 13:20:44', 0, 'Pending', NULL, 'พี่ซูกัส', 21, NULL, NULL, '2026-07-04 13:20:44'),
(139, 12, 20, 1, 0, 'Draft V1', '00139/0507', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 06:00:00', '2026-07-16 12:00:00', 'BNI', '', '', NULL, 5, '05/07', 45, 0.00, 16650.00, '', '-จัดโต๊ะ + เก้าอี้ ผู้เข้าประชุมแบบ U-Shape จำนวน 47 ที่นั่ง\r\n-จัดโต๊ะลงทะเบียน + เก้าอี้ หน้าห้องประชุม จำนวน 4 ที่นั่ง\r\n-จัดโต๊ะ + เก้าอี้ สำหรับวิทยากร บนเวที จำนวน 1 ที่นั่ง\r\n-จัดเตรียมแฟ้มรองเซนต์ โต๊ะลงทะเบียน 2 เล่ม\r\n-จัดเตรียม กระดาษ ดินสอ น้ำดื่ม สำหรับผู้ประชุมให้เพียงพอ\r\n-จัดโต๊ะกลม+เก้าอี้ จำนวน 5 โต๊ะ สำหรับนั่งทานข้าว', 'Wifi ในห้องประชุม LCD + Projector / สายเสียง / สายภาพ / ไวนิลติดผนัง หน้าห้องตะวัน ไมค์ลอย 5ตัว เตรียม Podium + Mic (บนเวที) 1 ตัว ', '', '', '', NULL, NULL, 'BNI – The World’s Leading Business Networking\r\nand Referral Organization\r\n16 JULY 2026\r\nS Hadyai Hotel, Songkhla', 'uploads/backdrop_1783214976.png', '-จัดเตียมดอกไม้โต๊ะลงทะเบียน\r\n-จัดเตรียมดอกไม้ติด Podium \r\n-จัดเตรียมดอกไม้โต๊ะวิทยากร \r\n-ดูแลความสะอาดบริเวณห้องงานประชุม / ห้องน้ำ / ฟร้อน\r\n-ดูแลความเรียบร้อยที่จอดรถของโรงแรม ', NULL, NULL, NULL, '2026-07-05 01:29:36', 0, 'Pending', NULL, 'คุฯผู้จัดการ ', 17, NULL, NULL, '2026-07-05 01:29:36'),
(140, 12, 20, 1, 0, 'Draft V1', '00140/0507', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 06:00:00', '2026-07-16 12:00:00', 'BNI', '', '', NULL, 5, '05/07', 45, 0.00, 16650.00, '', '', '', '', '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-05 01:33:02', 0, 'Pending', NULL, 'คุฯผู้จัดการ ', 17, NULL, NULL, '2026-07-05 01:33:02'),
(142, 12, 20, 1, 0, 'Draft V1', '00142/0507', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 08:00:00', '2026-07-16 17:00:00', 'BNI', '', '', NULL, 5, '', 0, 0.00, 16650.00, '', '', '', '', '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-05 04:53:37', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-05 04:53:37'),
(143, 12, 20, 1, 0, 'Draft V1', '00143/0507', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 08:00:00', '2026-07-16 17:00:00', 'BNI', '', '', NULL, 4, '', 0, 0.00, 16650.00, '', '', '', '', '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-05 04:55:25', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-05 04:55:25'),
(145, 12, 20, 1, 0, 'Draft V1', '00145/0507', 3, 15, 1, 'งานสัมมนาและประชุม1', NULL, '2026-07-16 08:00:00', '2026-07-16 17:00:00', 'BNI', '', '', NULL, 5, '', 0, 0.00, 16650.00, '', '', '', '', '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-05 04:58:37', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-05 04:58:37'),
(146, 12, 20, 1, 0, 'Draft V1', '00146/0507', 3, 15, 1, 'งานสัมมนาและประชุม', NULL, '2026-07-16 08:00:00', '2026-07-16 17:00:00', 'BNI', '', '', NULL, 5, '', 0, 0.00, 16650.00, '', '', '', '', '', '', NULL, NULL, '', '', '', NULL, NULL, NULL, '2026-07-05 04:59:02', 0, 'Pending', NULL, 'น.ส. นิยะดา ชาปาน (ซูกัส)', 21, NULL, NULL, '2026-07-05 04:59:02');

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
  `break_total` decimal(10,2) DEFAULT 0.00,
  `break_remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_breaks`
--

INSERT INTO `function_breaks` (`id`, `function_id`, `break_time`, `break_type_id`, `break_type`, `break_menu`, `break_pax`, `break_price`, `break_total`, `break_remark`) VALUES
(1, 101, '10:30:00', 1, 'Morning Break', 'กาแฟดำ, ชาเขียวร้อน, พัฟไก่ และผลไม้ตามฤดูกาล', 1, 85.00, 85.00, 'ขอแก้วกระดาษรักษ์โลก'),
(2, 101, '14:30:00', 2, 'Afternoon Break', 'น้ำส้มคั้นสด, แซนวิชแฮมชีส และคุกกี้เนยสด', 1, 75.00, 75.00, 'เสิร์ฟพร้อมทิชชู่แผ่นหนา'),
(3, 102, '09:45:00', 1, 'Morning Break', 'โกโก้เย็น, ปาท่องโก๋ยัดไส้ ', 1, 60.00, 60.00, 'เน้นเสิร์ฟเร็วภายใน 15 นาที'),
(4, 103, '15:00:00', 3, 'Special Break', 'ชานมไข่มุก (หวานน้อย), ไดฟุกุสตรอว์เบอร์รี่', 1, 120.00, 120.00, 'วีไอพี 5 ท่าน ขอจานเซรามิก');

-- --------------------------------------------------------

--
-- Table structure for table `function_finance`
--

CREATE TABLE `function_finance` (
  `id` int(11) NOT NULL,
  `function_id` int(11) NOT NULL,
  `type` enum('income','cost') NOT NULL,
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
(4, 135, 'cost', 'ค่าเบรกเพิ่ม', 2000.00, 'Cash', '2026-07-02', 'Admin', 'นาย วทัญญู ต้นจาน', '2026-07-02 08:30:32', 1);

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
  `k_remark` text DEFAULT NULL,
  `k_date` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_kitchens`
--

INSERT INTO `function_kitchens` (`id`, `function_id`, `k_type_id`, `k_item`, `k_qty`, `k_price`, `k_remark`, `k_date`) VALUES
(155, 137, 1, 'สาคูไส้ไก่ + ชา กาแฟ', 20, 100.00, '', '2026-07-16'),
(156, 137, 1, 'เค้กใบเตยหน้านิ่ม + ชา กาแฟ', 25, 100.00, '', '2026-07-16'),
(161, 138, 1, 'สาคูไส้ไก่ + ชา กาแฟ', 20, 100.00, '', '2026-07-16'),
(162, 138, 1, 'เค้กใบเตยหน้านิ้ม + ชา กาแฟ', 25, 100.00, '', '2026-07-16'),
(163, 139, 1, 'สาคูไส้ไก่ + ชา กาแฟ', 20, 100.00, '', '2026-07-16'),
(164, 139, 1, 'เค้กใบเตยหน้านิ่ม + ชา กาแฟ', 25, 100.00, '', '2026-07-16'),
(165, 140, 1, 'สาคูไส้ไก่ + ชา กาแฟ', 20, 100.00, '', '2026-07-16'),
(166, 140, 1, 'เค้กใบเตยหน้านิ่ม + ชา กาแฟ', 25, 100.00, '', '2026-07-16'),
(167, 136, 1, 'ข้าวเหนียวคอนโด + ชา กาแฟ', 20, 100.00, '', '2026-07-09'),
(168, 136, 1, 'เค้กส้มหน้านิ่ม + ชา กาแฟ', 25, 100.00, '', '2026-07-09');

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
  `menu_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_menus`
--

INSERT INTO `function_menus` (`id`, `function_id`, `menu_time`, `menu_name`, `menu_set_id`, `menu_detail`, `menu_qty`, `menu_price`) VALUES
(149, 137, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(150, 137, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(155, 138, '2026-07-16', NULL, 6, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว+ผลไม้', '20', 270.00),
(156, 138, '2026-07-16', NULL, 6, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว+ผลไม้', '25', 270.00),
(157, 139, '2026-07-16', NULL, 6, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(158, 139, '2026-07-16', NULL, 6, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(159, 140, '2026-07-16', NULL, 0, 'เบรก : สาคูไส้ไก่', '20', 100.00),
(160, 140, '2026-07-16', NULL, 0, 'เบรก : เค้กใบเตยหน้านิ่ม', '25', 100.00),
(161, 140, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(162, 140, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(163, 136, '2026-07-09', NULL, 6, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว+ผลไม้', '20', 270.00),
(164, 136, '2026-07-09', NULL, 6, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย+ผลไม้', '25', 270.00),
(170, 142, '2026-07-16', NULL, 0, 'เบรก : สาคูไส้ไก่', '20', 100.00),
(171, 142, '2026-07-16', NULL, 0, 'เบรก : เค้กใบเตยหน้านิ่ม', '25', 100.00),
(172, 142, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(173, 142, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(174, 143, '2026-07-16', NULL, 0, 'เบรก : สาคูไส้ไก่', '20', 100.00),
(175, 143, '2026-07-16', NULL, 0, 'เบรก : เค้กใบเตยหน้านิ่ม', '25', 100.00),
(176, 143, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(177, 143, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(183, 145, '2026-07-16', NULL, 0, 'เบรก : สาคูไส้ไก่', '20', 100.00),
(184, 145, '2026-07-16', NULL, 0, 'เบรก : เค้กใบเตยหน้านิ่ม', '25', 100.00),
(185, 145, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(186, 145, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00),
(187, 146, '2026-07-16', NULL, 0, 'เบรก : สาคูไส้ไก่', '20', 100.00),
(188, 146, '2026-07-16', NULL, 0, 'เบรก : เค้กใบเตยหน้านิ่ม', '25', 100.00),
(189, 146, '2026-07-16', NULL, 0, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', '20', 270.00),
(190, 146, '2026-07-16', NULL, 0, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', '25', 270.00);

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
  `price_per_pax` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `function_menu_details`
--

INSERT INTO `function_menu_details` (`id`, `function_id`, `menu_type_id`, `menu_items`, `beverage_detail`, `guarantee_pax`, `price_per_pax`) VALUES
(1, 0, 1, 'บุฟเฟต์อาหารไทย: แกงเขียวหวานลูกชิ้นปลากราย, ปลากะพงทอดน้ำปลา, ผัดผักรวมมิตรมงคล, ข้าวหอมมะลิใหม่', 'น้ำดื่มสะอาด, น้ำสมุนไพร (เก๊กฮวย/อัญชัน)', 1, 450.00),
(2, 0, 2, 'เซตเมนูอาหารจีน: เป็ดปักกิ่ง, กระเพาะปลาน้ำแดง, ปลากะพงนึ่งซีอิ๊ว, ข้าวผัดปู, โอนีแปะก๊วย', 'ชาจีนร้อน/เย็น, น้ำอัดลมแบบ Refill', 1, 850.00),
(3, 0, 3, 'ค็อกเทลปาร์ตี้: มินิเบอร์เกอร์เนื้อ, กุ้งพันหมี่, ลาบหมูทอดคำหวาน, พาสต้าซอสครีมเห็ดทรัฟเฟิล', 'Sparkling Juice, Punch, น้ำดื่ม', 1, 650.00),
(4, 0, 1, 'บุฟเฟต์นานาชาติ: สลัดบาร์สด, สเต็กหมูซอสพริกไทยดำ, สปาเก็ตตี้คาโบนาร่า, ซูชิหน้าต่างๆ', 'น้ำผลไม้รวม, กาแฟสด/ชา หลังอาหาร', 1, 550.00),
(5, 0, 4, 'อาหารกล่องพรีเมียม (Set Box): ข้าวหน้าปลาแซลมอนย่างเกลือ, ไข่หวาน, กิมจิ และสลัดผัก', 'น้ำแร่บรรจุขวด, ชาเขียวพร้อมดื่ม', 1, 250.00);

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
(261, 137, '2026-07-16', '06.00-12.00', 'ประชุม/สัมมนา', '45'),
(262, 137, '2026-07-16', '06.00-07.00', 'เบรกเช้า', '45'),
(263, 137, '2026-07-06', '09.00-09.30', 'รับประทานอาหารเที่ยง', '45'),
(276, 138, '2026-07-16', '06.00-12.00', 'ประชุม/สัมมนา', '45'),
(277, 138, '2026-07-16', '06.00-07.00', 'เบรกเช้า', '45'),
(278, 138, '2026-07-16', '09.00-09.30', 'รับประทานอาหารกลางวัน', '45'),
(279, 139, '2026-07-16', '06.00-12.00', 'ประชุม/สัมมนา', '45'),
(280, 139, '2026-07-16', '06.00-07.00', 'เบรกเช้า', '45'),
(281, 139, '2026-07-16', '09.00-09.30', 'รับประทานอาหารกลางวัน', '45'),
(282, 140, '2026-07-16', '06.00-12.00', 'ประชุม/สัมมนา', '45'),
(283, 140, '2026-07-16', '06.00-07.00', 'เบรกเช้า', '45'),
(284, 140, '2026-07-16', '09.00-09.30', 'รับประทานอาหารกลางวัน', '45'),
(285, 136, '2026-07-09', '07.00-12.00', 'ประชุม/สัมมนา', '45'),
(286, 136, '2026-07-09', '06.00 - 07.00 ', 'เบรกเช้า', '45'),
(287, 136, '2026-07-09', '09.00 - 09.30', 'รับประทานอาหาร', '45');

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
(5, 136, 'Pending', 'Confirmed', 17, '2026-07-04 15:13:41');

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
(1, 'งานประชุม/สัมมนา', '', '2026-03-16 09:13:48'),
(2, 'งานเลี้ยงฉลองมงคลสมรส', '', '2026-03-16 09:13:48'),
(3, 'งานเลี้ยงวันเกิด', '', '2026-03-16 09:13:48'),
(4, 'งานเลี้ยงสังสรรค์พนักงาน', '', '2026-03-16 09:13:48'),
(5, 'งานแถลงข่าว', '', '2026-03-16 09:13:48'),
(6, 'งานนิทรรศการ/อีเวนต์', '', '2026-03-16 09:13:48'),
(7, 'งานจัดเลี้ยงนอกสถานที่', '', '2026-03-16 09:13:48'),
(8, 'งานทำบุญเลี้ยงพระ', '', '2026-03-16 09:13:48');

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
-- Table structure for table `master_menu_types`
--

CREATE TABLE `master_menu_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_menu_types`
--

INSERT INTO `master_menu_types` (`id`, `type_name`) VALUES
(1, 'บุฟเฟต์ไทย'),
(2, 'บุฟเฟต์นานาชาติ'),
(3, 'โต๊ะจีน'),
(4, 'ค็อกเทล'),
(5, 'เซตเมนู'),
(6, 'ข้าวกล่อง'),
(7, 'เบรก');

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
(19, 3, NULL, 12, 15, 'QT-20260704-1518', 'งานสัมมนาและประชุม', '2026-07-09', '2026-07-09', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:20:56', '2026-07-04 07:20:42', '2026-07-04 09:05:40'),
(20, 3, NULL, 12, 15, 'QT-20260704-1521', 'งานสัมมนาและประชุม', '2026-07-16', '2026-07-16', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 1, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:23:20', '2026-07-04 07:23:10', '2026-07-05 01:22:05'),
(21, 3, NULL, NULL, 16, 'QT-20260704-1527', 'งานสัมมนาและประชุม', '2026-08-15', '2026-08-15', 11214.95, 0.00, 785.05, 12000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:29:56', '2026-07-04 07:29:48', '2026-07-04 07:29:56'),
(24, 3, NULL, NULL, 18, 'QT-20260704-1534', 'งานเกษียร', '2026-09-19', '2026-09-19', 56074.77, 0.00, 3925.23, 60000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:35:34', '2026-07-04 07:35:28', '2026-07-05 02:10:54'),
(25, 3, NULL, 13, 19, 'QT-20260704-1556', 'งานสัมมนาและประชุม', '2026-09-16', '2026-09-16', 22607.48, 0.00, 1582.52, 24190.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 14:59:44', '2026-07-04 07:59:33', '2026-07-05 04:50:25'),
(26, 3, NULL, NULL, 15, 'QT-20260704-1638', 'งานสัมมนาและประชุม', '2026-07-23', '2026-07-23', 15560.75, 0.00, 1089.25, 16650.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 15:39:38', '2026-07-04 08:39:28', '2026-07-04 08:39:38'),
(27, 3, NULL, 12, 20, 'QT-20260704-1709', 'งานประชุม-สัมนา', '2026-07-28', '2026-07-29', 69214.95, 0.00, 4845.05, 74060.00, 'include', 'Approved', 0, '', 'โทรเข้า', 'ปิดงานสำเร็จ', NULL, NULL, '', 22, 17, '2026-07-04 19:12:42', '2026-07-04 09:12:06', '2026-07-04 12:12:42'),
(28, 3, NULL, NULL, 21, 'QT-20260704-2016', 'สัมมนาคณะคุณหมอ', '2026-09-10', '2026-09-10', 68224.30, 0.00, 4775.70, 73000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 19:19:41', '2026-07-04 12:19:29', '2026-07-04 12:19:41'),
(29, 3, NULL, 12, 23, 'QT-20260704-2103', 'งานประชุม-สัมนา', '2026-07-30', '2026-07-31', 65196.26, 0.00, 4563.74, 69760.00, 'include', 'Approved', 0, '', 'โทรเข้า', 'ปิดงานสำเร็จ', NULL, NULL, '', 22, 17, '2026-07-04 20:21:37', '2026-07-04 13:09:47', '2026-07-04 13:21:37'),
(30, 3, NULL, NULL, 17, 'QT-20260704-2125', 'คอนเสิร์ต', '2026-09-02', '2026-09-02', 23364.49, 0.00, 1635.51, 25000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-04 20:27:41', '2026-07-04 13:27:05', '2026-07-04 13:27:41'),
(31, 3, NULL, 12, 20, 'QT-20260704-2234', 'งานประชุม-สัมนา', '2026-08-24', '2026-08-28', 65196.26, 0.00, 4563.74, 69760.00, 'include', 'Approved', 0, '', 'โทรเข้า', 'รอการตัดสินใจ', NULL, NULL, '', 22, 17, '2026-07-05 08:21:47', '2026-07-04 14:35:50', '2026-07-05 01:21:47'),
(32, 3, NULL, NULL, 27, 'QT-20260705-1118', 'งานสัมมนาและประชุม', '2026-09-12', '2026-09-12', 23364.49, 0.00, 1635.51, 25000.00, 'include', 'Approved', 0, '', '', '', NULL, NULL, '', 21, 17, '2026-07-05 10:20:19', '2026-07-05 03:20:10', '2026-07-05 03:20:19');

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
(55, 27, 'เบรกบ่าย จำนวน  114 ท่าน (ขนม 1 อย่าง) จำนวน  2 วัน', 228, 50.00, 11400.00, 'Food'),
(53, 27, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน จำนวน 114 ท่าน  จำนวน 2 วัน\r\n', 228, 220.00, 50160.00, 'Food'),
(68, 30, 'เหมาห้องตะวัน 18.00-23.00 น. จำนวน 250 ท่าน\r\nรวมนำเข้าเครื่องดื่มทุกประเภท', 1, 25000.00, 25000.00, 'Food'),
(37, 24, ' Thai Set (7 อย่าง) +  เครื่องดื่ม 18.00-22.00 น.', 15, 4000.00, 60000.00, 'Food'),
(38, 25, 'คอฟฟี่ เบรกเช้า (ขนม 1 อย่าง) กลุ่ม A', 148, 80.00, 11840.00, 'Food'),
(39, 25, 'คอฟฟี่ เบรกบ่าย (ขนม 1 อย่าง) กลุ่ม B', 135, 80.00, 10800.00, 'Food'),
(40, 25, 'คอฟฟี่ เบรกเช้า (ขนม 1 อย่าง) กลุ่ม A', 5, 80.00, 400.00, 'Food'),
(41, 25, 'คอฟฟี่ เบรกบ่าย (ขนม 1 อย่าง) กลุ่ม B', 5, 80.00, 400.00, 'Food'),
(42, 25, 'อาหารกลางวันวิทยากร', 5, 150.00, 750.00, 'Food'),
(44, 26, 'ข้าวกล่อง', 45, 270.00, 12150.00, 'Food'),
(29, 20, 'เบรก : สาคูไส้ไก่', 20, 100.00, 2000.00, 'Food'),
(30, 20, 'เบรก : เค้กใบเตยหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(31, 20, 'ข้าวผัดกะเพราไก่ +ต้มยำไก่ +ไข่ดาว', 20, 270.00, 5400.00, 'Food'),
(32, 20, 'ข้าวเปรี้ยวหวานปลา + แกงจืดเต้าหู้สาหร่าย +ไข่เจียว', 25, 270.00, 6750.00, 'Food'),
(33, 21, 'ห้องตะวัน จำนวน 50 ท่าน 13.00-17.00 น.\r\nเบรก 2 ชิ้น \r\nท่านที่ 51 บวกเพิ่ม  100', 1, 12000.00, 12000.00, 'Food'),
(43, 26, 'เบรกเช้า', 45, 100.00, 4500.00, 'Food'),
(28, 19, 'ข้าวปลากะพงพริกไทยดำ+แกงเขียวหวานไก่+ ไข่ลูกเขย', 25, 270.00, 6750.00, 'Food'),
(25, 19, 'เบรค : ข้าวเหนียวคอนโด', 20, 100.00, 2000.00, 'Food'),
(26, 19, 'เบรค : เค้กส้มหน้านิ่ม', 25, 100.00, 2500.00, 'Food'),
(27, 19, 'ข้าวไก่ผัดขิง+แกงส้มปลากะพงยอดมะพร้าว+ไข่เจียว', 20, 270.00, 5400.00, 'Food'),
(54, 27, 'เบรกเช้า จำนวน  114 ท่าน (ขนม 1 อย่าง) จำนวน  2 วัน', 228, 50.00, 11400.00, 'Food'),
(56, 27, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท\r\nไม่รวมอาหารเช้า, อาหารเช้า บริการแบบ Room Services ราคา 200 บาท\r\n', 1, 1100.00, 1100.00, 'Food'),
(57, 28, 'เบรกเช้า ขนม 1 อย่าง+ ชา กาแฟ', 50, 90.00, 4500.00, 'Food'),
(58, 28, 'เบรกบ่าย ขนม 1 อย่าง+ ชา กาแฟ', 50, 90.00, 4500.00, 'Food'),
(59, 28, 'อาหารเที่ยงบุฟเฟต์ 7 อย่าง + น้ำดื่ม', 50, 400.00, 20000.00, 'Food'),
(60, 28, 'ห้องพัก Standard Room จำนวน 2 คืน', 20, 2200.00, 44000.00, 'Food'),
(66, 29, 'เบรกบ่าย จำนวน 109 ท่าน (ขนม 1 อย่าง) 2 วัน', 218, 50.00, 10900.00, 'Food'),
(65, 29, 'เบรกเช้า จำนวน 109 ท่าน (ขนม 1 อย่าง) 2 วัน\r\n', 218, 50.00, 10900.00, 'Food'),
(64, 29, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน จำนวน 109 ท่าน 2 วัน', 218, 220.00, 47960.00, 'Food'),
(67, 29, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท\r\nไม่รวมอาหารเช้า, อาหารเช้า บริการแบบ Room Services ราคา 200 บาท', 1, 0.00, 0.00, 'Food'),
(70, 31, 'บุฟเฟ่อาหารกลางวัน 3 อย่าง พร้อมขนมหวาน จำนวน 109 ท่าน จำนวน  2 วัน', 218, 220.00, 47960.00, 'Food'),
(71, 31, 'เบรกเช้า จำนวน 109 ท่าน (ขนม 1 อย่าง) จำนวน 2 วัน ', 218, 50.00, 10900.00, 'Food'),
(72, 31, 'เบรกบ่าย จำนวน 109 ท่าน (ขนม 1 อย่าง) จำนวน  2 วัน', 218, 50.00, 10900.00, 'Food'),
(73, 31, 'ราคาห้องพัก STD เตียงเดี่ยว เตียงคู ่ ราคา 1100 บาท\r\nไม่รวมอาหารเช้า, อาหารเช้า บริการแบบ Room Services ราคา 200 บาท', 1, 0.00, 0.00, 'Food'),
(74, 32, 'ห้องประชุมเต็มวัน (ลงจองไว้ก่อน) รายละเอียดจะตามมา', 1, 25000.00, 25000.00, 'Food');

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
(8, 'uploads/signatures/sig_17_1782038027.png', '2026-06-21 10:33:47', 17),
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
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`) VALUES
(1, 'admin', '81dc9bdb52d04dc20036dbd8313ed055', 'Admin', 'นาย วทัญญู ต้นจาน'),
(12, 'viewer', '$2y$10$67lzJDYH8Db/.Kb8wbY89eCBqkoZkRgVEZHf5ypih27deznJ4UrI6', 'Viewer', 'นาย ทดสอบ ระบบ'),
(13, 'lek', '$2y$10$BhTMkOjrOiWypab4qnBAM.Z.mbkxvGS.OrY4q8/dpzuaj03mILqpS', 'Procurement', 'พี่เล็ก'),
(14, 'pao', '$2y$10$FjuNX84FxXyHxvMFxRjzUeuUoKTDvLqQmbsfcxdAxh/VI0BuESz7i', 'Technician', 'ช่างเป่า'),
(15, 'hk', '$2y$10$6/UTDZJn7fD087XXJslQteuCQm/3gA3BBP18vIfptaRTFz2TwnAx.', 'Housekeeping', 'แม่บ้าน'),
(16, 'bk', '$2y$10$4Z1k.rjFp3YTB32XddZT4OUF12uiBtts2kgWQD4l7z/Jrr0bosUzy', 'Banquet_Staff', 'บังสิท'),
(17, 'gm', '$2y$10$8epNgUQEL0pk4PQpUB5udOXK0PB7sfM.XhqsOrsoi72Ysj/4u51r6', 'GM', 'คุฯผู้จัดการ '),
(19, 'sale', '$2y$10$gTcwoF/LtOrVSt6GZa34ZuEmsCSkiPPFWHnpuIfEMAiOsPy7mKbCS', 'Staff', 'คุณ เซลล์ ทดสอบ'),
(20, 'sale2', '$2y$10$zAMJqBMknVkT8mKlNku/BezM9WBCaKyjKr8pSxYQHXYFeUnFyUQF2', 'Staff', 'คุณ เซลล์ ทดสอบ2'),
(21, 'sugus', '$2y$10$84Epghn/x3GpfuC6YJPSWOdb0Z.j4yrnzcGm.jOvmx/3Gzx5kgSN.', 'Staff', 'น.ส. นิยะดา ชาปาน (ซูกัส)'),
(22, 'bow', '$2y$10$/GBCBHA4mE7pnRoLTwJ0F.8VjLrdOfDZe6NLX2bhzt7QKSIDGpMoe', 'Staff', 'น.ส.ประวีณา เพ็ชรสมุทร (โบว์)'),
(23, 'pew', '$2y$10$sEzRb1ugpPTxnz5ZUtzl4.i2P/dXcebSQ2l7Tml30ygshaSEbZ97O', 'Procurement', 'วนิดา ');

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
-- Indexes for table `master_menu_types`
--
ALTER TABLE `master_menu_types`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `event_projects`
--
ALTER TABLE `event_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `functions`
--
ALTER TABLE `functions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `function_breaks`
--
ALTER TABLE `function_breaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `function_finance`
--
ALTER TABLE `function_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `function_menus`
--
ALTER TABLE `function_menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `function_menu_details`
--
ALTER TABLE `function_menu_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `function_schedules`
--
ALTER TABLE `function_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;

--
-- AUTO_INCREMENT for table `function_status_log`
--
ALTER TABLE `function_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `function_types`
--
ALTER TABLE `function_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
