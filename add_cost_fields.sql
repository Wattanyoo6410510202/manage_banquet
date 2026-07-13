-- =============================================
-- เพิ่ม field ราคาทุน (cost) ในตารางเมนูและเบรก
-- วันที่: 2026-07-12
-- =============================================

-- 1. ตาราง function_menus (เมนูอาหารหลัก)
ALTER TABLE `function_menus`
  ADD COLUMN `menu_cost` decimal(10,2) DEFAULT NULL AFTER `menu_price`;

-- 2. ตาราง function_menu_details (แคตตาล็อกเมนูมาตรฐาน)
ALTER TABLE `function_menu_details`
  ADD COLUMN `cost_per_pax` decimal(10,2) DEFAULT NULL AFTER `price_per_pax`;

-- 3. ตาราง function_breaks (เบรก)
ALTER TABLE `function_breaks`
  ADD COLUMN `break_cost` decimal(10,2) DEFAULT 0.00 AFTER `break_price`;

-- 4. ตาราง function_kitchens (ครัว/เบรก)
ALTER TABLE `function_kitchens`
  ADD COLUMN `k_cost` decimal(10,2) DEFAULT 0.00 AFTER `k_price`;
