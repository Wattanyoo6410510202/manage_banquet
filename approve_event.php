<?php
session_start();
// ปิดการแสดง error ที่เป็น html เพื่อไม่ให้ JSON พัง
ini_set('display_errors', 0); 
error_reporting(E_ALL);

include "config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    
    if ($id && is_numeric($id)) {
        $sql = "UPDATE functions SET approve = 1 WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                
                // --- 🚀 จุดสำคัญ: ตั้งค่า Session สำหรับแจ้งเตือน ---
                $_SESSION['flash_msg'] = "success"; 
                // ถ้าใน alert.php ของคุณมีการใช้ตัวแปรข้อความอื่น เช่น $_SESSION['msg_text'] ให้ใส่เพิ่มที่นี่ครับ
                
                echo json_encode(['status' => 'success', 'message' => 'อนุมัติเรียบร้อยแล้ว']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Execute Error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Prepare Error: ' . $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
$conn->close();