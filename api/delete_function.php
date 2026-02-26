<?php
ob_start();
session_start();
include "../config.php";

// 1. เอา header JSON ออก (เพราะเราจะเด้งหน้าจอแทน)
// header('Content-Type: application/json'); 

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $function_id = intval($_GET['id']);
    $conn->begin_transaction();

    try {
        // --- ส่วนลบข้อมูลของจารเหมือนเดิม ---
        $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
        $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
        $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

        $sql_img = "SELECT backdrop_img FROM functions WHERE id = ?";
        $stmt_img = $conn->prepare($sql_img);
        $stmt_img->bind_param("i", $function_id);
        $stmt_img->execute();
        $result = $stmt_img->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['backdrop_img']) && file_exists($row['backdrop_img'])) {
                unlink($row['backdrop_img']);
            }
        }

        $sql_main = "DELETE FROM functions WHERE id = ?";
        $stmt_main = $conn->prepare($sql_main);
        $stmt_main->bind_param("i", $function_id);
        $stmt_main->execute();

        $conn->commit();

        // --- 2. จุดเปลี่ยน: ตั้งค่า Session แบบเดียวกับตอนบันทึก ---
        $_SESSION['flash_msg'] = "delete_success"; 
        header("Location: ../manage_banquet.php"); // ใส่ชื่อหน้าหลักของจาร
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_msg'] = "error";
        header("Location: ../manage_banquet.php");
        exit();
    }
} else {
    header("Location: ../manage_banquet.php");
    exit();
}
ob_end_flush();
?>