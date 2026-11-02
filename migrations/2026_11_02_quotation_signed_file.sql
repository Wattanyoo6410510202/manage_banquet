-- ไฟล์ใบเสนอราคาที่ลูกค้าเซ็นแล้ว (อัปโหลดได้หลังอนุมัติ) — อัปโหลดแล้วสถานะ workflow เป็น "ลูกค้าเซ็นยืนยัน"
ALTER TABLE `quotations`
  ADD COLUMN IF NOT EXISTS `signed_file` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `signed_uploaded_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `signed_uploaded_by` int(11) DEFAULT NULL;
