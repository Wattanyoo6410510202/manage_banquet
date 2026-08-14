-- =============================================
-- เปิดให้บันทึกบัญชี (function_finance) ได้ตั้งแต่ขั้นใบเสนอราคา
-- เดิมผูกกับ functions.id อย่างเดียว งานที่ยังไม่แปลงเป็น EO เลยลงรายการไม่ได้
-- วันที่: 2026-08-14
-- =============================================

-- function_id ต้องว่างได้ เพราะใบเสนอราคายังไม่มีแถวใน functions
ALTER TABLE `function_finance`
    MODIFY `function_id` INT(11) NULL DEFAULT NULL;

-- ผูกรายการกับใบเสนอราคา (รายการที่คีย์ตอนยังเป็นใบเสนอราคา)
ALTER TABLE `function_finance`
    ADD COLUMN `quotation_id` INT(11) NULL DEFAULT NULL AFTER `function_id`;

ALTER TABLE `function_finance`
    ADD INDEX `idx_ff_quotation` (`quotation_id`);

-- ตรวจผลลัพธ์
SHOW COLUMNS FROM `function_finance`;

-- รายการที่ยังค้างอยู่ที่ใบเสนอราคา (ยังไม่ถูกดึงเข้า EO)
SELECT ff.id, ff.quotation_id, q.quote_no, ff.type, ff.detail, ff.amount
FROM `function_finance` ff
JOIN `quotations` q ON q.id = ff.quotation_id
WHERE ff.function_id IS NULL;
