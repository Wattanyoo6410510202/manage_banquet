-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 08:43 AM
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
(3, 'Luxury Grand Hotel', 'creeda 725', 'กฟหกฟห', 'creeda725@gmail.com', 'dvsdfsdfsd', 'img/logo_1772002092_IMG_2442.jpg', '2026-02-25 06:48:12');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `function_name` varchar(255) DEFAULT NULL,
  `booking_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `participants` int(11) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `functions`
--

INSERT INTO `functions` (`id`, `function_code`, `company_id`, `function_name`, `booking_name`, `organization`, `phone`, `room_name`, `booking_room`, `deposit`, `main_kitchen_remark`, `banquet_style`, `equipment`, `remark`, `backdrop_detail`, `backdrop_img`, `hk_florist_detail`, `created_at`) VALUES
(1, '863563', 3, 'หฟหก', 'หกฟห', 'ฟหก', 'ฟหกฟหก', 'ฟหกฟ', 'หกฟ', 0.00, '', '4534', '534534', '53453', '', '', '', '2026-02-25 07:42:32');

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
  `k_remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'admin', '81dc9bdb52d04dc20036dbd8313ed055', '', ''),
(5, '6410510212', '$2y$10$o3v2/Smdthzzl06xw/U8sOC67LbVUvYdXpG8taNGXK7B0czrai3H2', 'Admin', 'นางสาว ดวงพร โชคชัย');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `functions`
--
ALTER TABLE `functions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `function_kitchens`
--
ALTER TABLE `function_kitchens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `function_menus`
--
ALTER TABLE `function_menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `function_schedules`
--
ALTER TABLE `function_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
