<?php
ob_start();
session_start();
// ถอยออกจากโฟลเดอร์ api ไปหา config
include "../config.php"; 

header('Content-Type: application/json');

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    
    $ids = $_POST['ids'];
    $conn->begin_transaction();

    try {
        foreach ($ids as $id) {
            $function_id = intval($id);

            // 1. ลบตารางลูก
            $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

            // 2. เช็คและลบไฟล์รูปภาพ
            $stmt_img = $conn->prepare("SELECT backdrop_img FROM functions WHERE id = ?");
            $stmt_img->bind_param("i", $function_id);
            $stmt_img->execute();
            $result = $stmt_img->get_result();
            if ($row = $result->fetch_assoc()) {
                // ถ้ามีรูปใน Folder ให้ลบทิ้งด้วย
                if (!empty($row['backdrop_img']) && file_exists("../" . $row['backdrop_img'])) {
                    unlink("../" . $row['backdrop_img']);
                }
            }

            // 3. ลบตารางหลัก
            $stmt_main = $conn->prepare("DELETE FROM functions WHERE id = ?");
            $stmt_main->bind_param("i", $function_id);
            $stmt_main->execute();
        }

        $conn->commit();
        
        // เซ็ต SESSION เพื่อให้หน้าหลักโชว์ Alert สไลด์จากขวา
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