<?php
ob_start();
session_start();
include "../config.php";

// บอก Browser ว่าเราจะตอบกลับเป็น JSON
header('Content-Type: application/json');

// 1. ตรวจสอบ Login และสิทธิ์
$current_user = $_SESSION['user_name'] ?? '';
$user_role    = $_SESSION['role'] ?? 'staff';

// รับค่าจาก AJAX (ids เป็น array)
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

if (!empty($ids) && is_array($ids)) {
    // ป้องกัน SQL Injection
    $clean_ids = array_map('intval', $ids);
    $ids_string = implode(',', $clean_ids);

    $conn->begin_transaction();
    try {
        // 🚀 2. เช็คสิทธิ์ก่อนลบ: ถ้าเป็น Staff ต้องลบได้เฉพาะงานที่ตัวเองสร้าง และยังไม่ Approve
        if ($user_role === 'staff') {
            $check_sql = "SELECT id FROM functions WHERE id IN ($ids_string) AND (created_by != ? OR approve != 0)";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("s", $current_user);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            
            if ($res_check->num_rows > 0) {
                throw new Exception("จาร! มีบางรายการที่จารไม่มีสิทธิ์ลบ หรือถูกอนุมัติไปแล้วนะ");
            }
        }

        // 3. ดึงชื่อรูปภาพมาลบออกจาก Folder
        $res_img = $conn->query("SELECT backdrop_img FROM functions WHERE id IN ($ids_string)");
        while ($row = $res_img->fetch_assoc()) {
            if (!empty($row['backdrop_img'])) {
                $file_path = "../" . $row['backdrop_img'];
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                }
            }
        }

        // 4. ลบตารางลูก (ใช้ WHERE IN ทีเดียวจบ)
        $conn->query("DELETE FROM function_schedules WHERE function_id IN ($ids_string)");
        $conn->query("DELETE FROM function_kitchens WHERE function_id IN ($ids_string)");
        $conn->query("DELETE FROM function_menus WHERE function_id IN ($ids_string)");

        // 5. ลบตารางหลัก
        $conn->query("DELETE FROM functions WHERE id IN ($ids_string)");

        $conn->commit();

        // ✅ เก็บ Session สำหรับ Flash Message (หน้าจอก็จะเด้งแถบเขียวเนียนๆ)
        $_SESSION['flash_msg'] = "success";
        $_SESSION['msg_text'] = "ลบข้อมูลเรียบร้อยแล้วครับจาร";

        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มี ID ส่งมาครับจาร']);
}
ob_end_flush();
?>