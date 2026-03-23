<?php
include "../config.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // ดึง User ID จาก Session (สมมติว่าคุณเก็บไว้ใน $_SESSION['user_id'])
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    try {
        // อัปเดตสถานะเป็น 'Approved', บันทึกผู้อนุมัติ และเวลาที่อนุมัติ
        $sql = "UPDATE quotations SET 
                status = 'Approved', 
                approved_by = ?, 
                approved_at = NOW(),
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$admin_id, $id])) {
            // อัปเดตสำเร็จ ส่งกลับไปหน้าเดิมพร้อม Parameter แจ้งเตือน
            header("Location: quotation_list.php?status=success");
        } else {
            header("Location: quotation_list.php?status=error");
        }
    } catch (PDOException $e) {
        // กรณีเกิด Error ใน SQL
        header("Location: quotation_list.php?status=db_error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}