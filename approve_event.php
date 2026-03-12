<?php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

include "config.php";

header('Content-Type: application/json');

// 1. ตรวจสอบว่าพนักงานล็อกอินอยู่หรือไม่ (ถ้าไม่มีค่าใน session จะอนุมัติไม่ได้)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาล็อกอินใหม่']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    
    // 🚀 ดึงเลข ID พนักงานจาก Session ที่เก็บไว้ตอน Login
    $user_id_from_session = $_SESSION['user_id']; 
    
    if ($id && is_numeric($id)) {
        // 🚀 อัปเดต: สถานะเป็น 1, วันที่ปัจจุบัน, และ ID พนักงานจาก Session
        $sql = "UPDATE functions SET 
                approve = 1, 
                approve_date = NOW(), 
                approve_by = ? 
                WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // "ii" คือพารามิเตอร์ที่เป็น Integer ทั้งคู่
            // ตัวแรกคือ $user_id_from_session (จาก Login)
            // ตัวที่สองคือ $id (ID ของใบงาน)
            $stmt->bind_param("ii", $user_id_from_session, $id);
            
            if ($stmt->execute()) {
                // บันทึกสำเร็จ: ตั้งค่า Flash Message เพื่อไปโชว์แจ้งเตือนหน้าเว็บ
                $_SESSION['flash_msg'] = "approved"; 
                echo json_encode(['status' => 'success']); 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Execute Error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Prepare Error: ' . $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID เอกสารไม่ถูกต้อง']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
$conn->close();
?>