-- ==========================================================
-- SQL Script สำหรับติดตั้งระบบ Draft & Version Control
-- ==========================================================

-- 1. สร้างตาราง event_projects (เพื่อเก็บข้อมูลหัวข้องานหลัก)
CREATE TABLE IF NOT EXISTS `event_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Cancelled') COLLATE utf8_unicode_ci DEFAULT 'Pending',
  `created_by` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 2. เพิ่ม Column สำหรับจัดการ Draft ในตาราง functions (เดิม)
ALTER TABLE `functions`
ADD COLUMN `project_id` int(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `version_no` int(11) DEFAULT 1 AFTER `project_id`,
ADD COLUMN `is_approved` tinyint(1) DEFAULT 0 AFTER `version_no`,
ADD COLUMN `draft_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT 'Draft V1' AFTER `is_approved`;

-- 3. การย้ายข้อมูลเดิม (Data Migration) - รันเฉพาะครั้งแรกที่ติดตั้ง
-- สร้าง Project ใหม่จากงานที่มีอยู่เดิมในตาราง functions
INSERT INTO `event_projects` (project_name, customer_id, company_id, status, created_at)
SELECT function_name, customer_id, company_id, 
       CASE WHEN approve = 1 THEN 'Approved' ELSE 'Pending' END,
       created_at
FROM `functions`;

-- เชื่อมโยงตาราง functions กลับไปหา project_id ที่เพิ่งสร้าง
UPDATE `functions` f
JOIN `event_projects` p ON f.function_name = p.project_name AND f.created_at = p.created_at
SET f.project_id = p.id,
    f.is_approved = f.approve;

-- 4. (ทางเลือก) เพิ่ม Foreign Key เพื่อความปลอดภัยของข้อมูล
-- ALTER TABLE `functions` ADD CONSTRAINT `fk_project` FOREIGN KEY (`project_id`) REFERENCES `event_projects`(`id`) ON DELETE CASCADE;
