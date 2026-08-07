-- =============================================
-- Backfill ผู้อนุมัติ + วันที่อนุมัติ ของงานที่อนุมัติไปแล้ว
-- (ก่อนหน้านี้ approve_event.php ไม่ได้บันทึก approve_by / approve_date)
-- วันที่: 2026-08-07
-- =============================================

-- ดึงจาก log การเปลี่ยนสถานะครั้งแรกที่เป็น Confirmed/Approved ของแต่ละงาน
UPDATE `functions` f
JOIN (
    SELECT l.function_id,
           MIN(l.id) AS first_log_id
    FROM `function_status_log` l
    WHERE l.new_status IN ('Confirmed', 'Approved')
    GROUP BY l.function_id
) fl ON fl.function_id = f.id
JOIN `function_status_log` l0 ON l0.id = fl.first_log_id
SET f.approve_by   = COALESCE(f.approve_by, l0.changed_by),
    f.approve_date = COALESCE(f.approve_date, l0.changed_at)
WHERE f.approve = 1
  AND (f.approve_by IS NULL OR f.approve_date IS NULL);

-- ตรวจผลลัพธ์: งานที่อนุมัติแล้วแต่ยังไม่มีคนอนุมัติ (ไม่มี log ให้ backfill)
SELECT id, function_name, status, approve, approve_by, approve_date
FROM `functions`
WHERE approve = 1 AND (approve_by IS NULL OR approve_date IS NULL);
