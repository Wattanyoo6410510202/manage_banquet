-- =========================================================
-- optimize_indexes.sql — เพิ่ม index ให้ตารางใหญ่ (รันบน NAS)
-- เลือกฐานข้อมูล managebanquet_simple ก่อนรัน
-- หมายเหตุ: ถ้าข้อมูลเยอะมาก ALTER อาจกินเวลา 1-2 นาที รอจนจบอย่าปิดหน้าเว็บ
-- =========================================================

-- ตาราง functions: ใช้โดย executive_dashboard / calendar / finance
ALTER TABLE `functions`
  ADD INDEX `idx_f_start` (`start_time`),
  ADD INDEX `idx_f_company` (`company_id`),
  ADD INDEX `idx_f_type` (`function_type_id`),
  ADD INDEX `idx_f_staff` (`created_by_id`);

-- ตาราง quotations: นับ "ใบเสนอราคาออกเดือนนี้" ตาม created_at
ALTER TABLE `quotations`
  ADD INDEX `idx_q_created` (`created_at`);

-- ใช้โดย calendar.php: join หา conflict ตามห้อง + auto-freeze ตาม event_date
ALTER TABLE `functions`
  ADD INDEX `idx_f_room` (`room_id`);
ALTER TABLE `quotations`
  ADD INDEX `idx_q_event_date` (`event_date`);
