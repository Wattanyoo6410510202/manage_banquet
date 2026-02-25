<?php
ob_start();
session_start();
include "config.php";

// ตั้งค่าให้ไฟล์นี้ส่งค่ากลับเป็น JSON
header('Content-Type: application/json');

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $function_id = intval($_GET['id']);

    $conn->begin_transaction();

    try {
        // --- ส่วนลบข้อมูลเดิมของจาร (ห้ามเปลี่ยน) ---
        $sql1 = "DELETE FROM function_schedules WHERE function_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $function_id);
        $stmt1->execute();

        $sql2 = "DELETE FROM function_kitchens WHERE function_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $function_id);
        $stmt2->execute();

        $sql3 = "DELETE FROM function_menus WHERE function_id = ?";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("i", $function_id);
        $stmt3->execute();

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
        // --- จบส่วนลบข้อมูลเดิม ---

        $conn->commit();
        // ส่งสถานะบอก JavaScript ว่าผ่าน
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'invalid']);
}
ob_end_flush();
?>