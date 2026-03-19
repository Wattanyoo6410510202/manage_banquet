<?php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

include "config.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? 'Confirmed'; 
    $user_id = $_SESSION['user_id']; 

    if ($id && is_numeric($id)) {
        
        // --- 🚀 Logic Approve Value ---
        $approve_val = ($status === 'Cancelled') ? 2 : 1;

        $sql = "UPDATE functions SET 
                    approve = ?, 
                    status = ?, 
                    status_updated_at = NOW(),
                    approve_date = IF(approve_date IS NULL AND ? = 1, NOW(), approve_date),
                    approve_by = IF(approve_by IS NULL AND ? = 1, ?, approve_by),
                    modify = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isiiii", $approve_val, $status, $approve_val, $approve_val, $user_id, $id);
            
            if ($stmt->execute()) {
                // --- ✅ ส่วนที่จารย์ต้องการ: ส่ง Session ตามสถานะที่กด ---
                if ($status === 'Confirmed') {
                    $_SESSION['flash_msg'] = "approved";
                } elseif ($status === 'In Progress') {
                    $_SESSION['flash_msg'] = "in_progress";
                } elseif ($status === 'Completed') {
                    $_SESSION['flash_msg'] = "completed";
                } elseif ($status === 'Cancelled') {
                    $_SESSION['flash_msg'] = "cancelled";
                }

                echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย']); 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database Error']);
            }
            $stmt->close();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
    }
}
$conn->close();