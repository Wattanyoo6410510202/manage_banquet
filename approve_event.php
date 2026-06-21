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
    $cancel_reason = $_POST['cancel_reason'] ?? '';
    $user_id = $_SESSION['user_id']; 

    if ($id && is_numeric($id)) {
        
        // --- 🚀 Logic Approve Value ---
        $approve_val = ($status === 'Cancelled') ? 2 : 1;

        $sql = "UPDATE functions SET 
                    approve = ?, 
                    status = ?, 
                    approve_date = IF(approve_date IS NULL AND ? = 1, NOW(), approve_date),
                    approve_by = IF(approve_by IS NULL AND ? = 1, ?, approve_by),
                    cancel_reason = IF(? = 2, ?, cancel_reason),
                    modify = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isiiiisi", $approve_val, $status, $approve_val, $approve_val, $user_id, $approve_val, $cancel_reason, $id);
            
            if ($stmt->execute()) {
                // --- [NEW] Draft & Project System Management ---
                if ($status === 'Confirmed') {
                    // 1. ดึง project_id ของ draft นี้
                    $p_res = $conn->query("SELECT project_id FROM functions WHERE id = $id");
                    if ($p_row = $p_res->fetch_assoc()) {
                        $project_id = $p_row['project_id'];
                        
                        // 2. เคลียร์ Draft อื่นให้หมด (Master Only)
                        $conn->query("UPDATE functions SET is_approved = 0 WHERE project_id = $project_id");
                        
                        // 3. ตั้ง Draft นี้เป็น Master
                        $conn->query("UPDATE functions SET is_approved = 1 WHERE id = $id");
                        
                        // 4. อัปเดตสถานะ Project หลัก
                        $conn->query("UPDATE event_projects SET status = 'Approved' WHERE id = $project_id");
                    }
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