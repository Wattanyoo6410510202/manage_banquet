-- =============================================
-- Migration: เพิ่ม "ประเภทใหญ่" (master_menu_categories)
-- สำหรับระบบจัดเลี้ยง manage_banquet_simple
-- วันที่: 2026-07-17
-- =============================================

-- 1. สร้างตาราง master_menu_categories
CREATE TABLE IF NOT EXISTS master_menu_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(150) NOT NULL,
    sort_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. เพิ่มคอลัมน์ category_id ใน master_menu_types (ถ้ายังไม่มี)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'managebanquet_simple'
    AND TABLE_NAME = 'master_menu_types'
    AND COLUMN_NAME = 'category_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE master_menu_types ADD COLUMN category_id INT(11) DEFAULT NULL AFTER id',
    'SELECT "category_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. เพิ่ม Foreign Key (ถ้ายังไม่มี)
SET @fk_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'managebanquet_simple'
    AND TABLE_NAME = 'master_menu_types'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql2 = IF(@fk_exists = 0,
    'ALTER TABLE master_menu_types ADD CONSTRAINT fk_menu_type_category FOREIGN KEY (category_id) REFERENCES master_menu_categories(id) ON DELETE SET NULL',
    'SELECT "FK already exists"'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 4. Seed Data: ประเภทใหญ่
INSERT IGNORE INTO master_menu_categories (id, category_name, sort_order) VALUES
(1, 'อาหารไทย', 1),
(2, 'อาหารจีน', 2),
(3, 'อาหารนานาชาติ', 3),
(4, 'ของว่าง / ค็อกเทล', 4);

-- 5. ผูก master_menu_types กับ master_menu_categories
UPDATE master_menu_types SET category_id = 1 WHERE type_name LIKE '%บุฟเฟต์ไทย%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 1 WHERE type_name LIKE '%ข้าวกล่อง%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 1 WHERE type_name LIKE '%เซตเมนู%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 2 WHERE type_name LIKE '%จีน%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 3 WHERE type_name LIKE '%นานาชาติ%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 4 WHERE type_name LIKE '%ค็อกเทล%' AND category_id IS NULL;
UPDATE master_menu_types SET category_id = 4 WHERE type_name LIKE '%เบรก%' AND category_id IS NULL;

-- 6. ตรวจสอบผลลัพธ์
SELECT '=== master_menu_categories ===' AS info;
SELECT * FROM master_menu_categories ORDER BY sort_order;
SELECT '=== master_menu_types with categories ===' AS info;
SELECT mmt.id, mmt.type_name, COALESCE(mmc.category_name, '(ไม่มีกลุ่ม)') AS category_name
FROM master_menu_types mmt
LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id
ORDER BY mmc.sort_order, mmt.id;
