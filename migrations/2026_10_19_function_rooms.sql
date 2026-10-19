-- รายการห้องพัก/ห้องประชุมของ EO (ดึงมาจากรายการประเภท Room/Meeting ในใบเสนอราคา หรือคีย์เองในหน้า EO)
CREATE TABLE IF NOT EXISTS `function_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `function_id` int(11) DEFAULT NULL,
  `room_type` varchar(20) NOT NULL DEFAULT 'Room',
  `room_date` date DEFAULT NULL,
  `room_detail` text DEFAULT NULL,
  `room_qty` int(11) DEFAULT NULL,
  `room_price` decimal(10,2) DEFAULT NULL,
  `room_cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `function_id` (`function_id`),
  CONSTRAINT `function_rooms_ibfk_1` FOREIGN KEY (`function_id`) REFERENCES `functions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ประเภทรายการ "ห้องประชุม" ในใบเสนอราคา
ALTER TABLE `quotation_items`
  MODIFY `item_type` enum('Food','Beverage','Room','Meeting','Equipment','Other','Extra','Break') DEFAULT 'Food';
