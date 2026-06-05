<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $target_amount = $_POST['target_amount'];
    $target_month = $_POST['target_month'];
    $target_year = $_POST['target_year'];

    // ตรวจสอบว่ามีข้อมูลเดิมอยู่แล้วหรือไม่ (Update if exists, else Insert)
    $stmt = $conn->prepare("INSERT INTO sales_targets (user_id, target_amount, target_month, target_year) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount)");
    $stmt->bind_param("idii", $user_id, $target_amount, $target_month, $target_year);

    if ($stmt->execute()) {
        $_SESSION['flash_msg'] = "success";
        $_SESSION['success'] = "บันทึกเป้าหมายการขายเรียบร้อยแล้ว";
    } else {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }

    $redirect = $_POST['redirect'] ?? '../setting.php?active_tab=sales';
    header("Location: $redirect");
    exit();
}

// กรณีลบเป้าหมาย
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $redirect = $_GET['redirect'] ?? '../setting.php?active_tab=sales';
    $stmt = $conn->prepare("DELETE FROM sales_targets WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "ลบเป้าหมายเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "ไม่สามารถลบข้อมูลได้";
    }
    header("Location: $redirect");
    exit();
}
?>