<?php
ob_start();
session_start();
include "config.php";

// ตั้งค่าให้ไฟล์นี้ส่งค่ากลับเป็น JSON
header('Content-Type: application/json');

// เปลี่ยนจาก GET เป็น POST และเช็คว่าเป็น Array หรือไม่
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    
    $ids = $_POST['ids'];
    $conn->begin_transaction();

    try {
        foreach ($ids as $id) {
            $function_id = intval($id);

            // 1. ลบตารางลูก: schedules
            $sql1 = "DELETE FROM function_schedules WHERE function_id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("i", $function_id);
            $stmt1->execute();

            // 2. ลบตารางลูก: kitchens
            $sql2 = "DELETE FROM function_kitchens WHERE function_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("i", $function_id);
            $stmt2->execute();

            // 3. ลบตารางลูก: menus
            $sql3 = "DELETE FROM function_menus WHERE function_id = ?";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param("i", $function_id);
            $stmt3->execute();

            // 4. ลูปเช็คและลบไฟล์รูปภาพ
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

            // 5. ลบตารางหลัก
            $sql_main = "DELETE FROM functions WHERE id = ?";
            $stmt_main = $conn->prepare($sql_main);
            $stmt_main->bind_param("i", $function_id);
            $stmt_main->execute();
        }

        $conn->commit();
        // เซ็ต session เพื่อให้หน้า index โชว์ alert หลัง reload
        $_SESSION['flash_msg'] = 'delete_success';
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'invalid', 'message' => 'ไม่มีข้อมูลที่จะลบ']);
}
ob_end_flush();
?>