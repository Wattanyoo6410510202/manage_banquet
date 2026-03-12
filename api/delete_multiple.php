<?php
ob_start();
session_start();
include "../config.php"; 

header('Content-Type: application/json');

// 1. ดึงข้อมูลจาก Session มาเช็คสิทธิ์
$current_user = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['role'] ?? 'staff';

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    
    $ids = array_map('intval', $_POST['ids']); // ทำความสะอาด ID ทั้งหมด
    $ids_string = implode(',', $ids);
    
    $conn->begin_transaction();

    try {
        // 🚀 2. เช็คสิทธิ์เข้มงวด: ถ้าเป็น Staff ต้องห้ามลบงานคนอื่น หรือลบงานที่ Approve แล้ว
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

        // 3. จัดการรูปภาพ (ดึง Path มาลบไฟล์ใน Folder)
        $res_img = $conn->query("SELECT backdrop_img FROM functions WHERE id IN ($ids_string)");
        while ($row = $res_img->fetch_assoc()) {
            if (!empty($row['backdrop_img'])) {
                $file_path = "../" . $row['backdrop_img'];
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                }
            }
        }

        // 4. ลบตารางลูกแบบทีเดียวจบ (ใช้ WHERE IN ประสิทธิภาพดีกว่าวนลูป)
        $conn->query("DELETE FROM function_schedules WHERE function_id IN ($ids_string)");
        $conn->query("DELETE FROM function_kitchens WHERE function_id IN ($ids_string)");
        $conn->query("DELETE FROM function_menus WHERE function_id IN ($ids_string)");

        // 5. ลบตารางหลัก
        $conn->query("DELETE FROM functions WHERE id IN ($ids_string)");

        $conn->commit();
        
        // เซ็ต SESSION เพื่อให้หน้าหลักโชว์ Alert สไลด์จากขวา (ตามที่จารเซ็ตไว้)
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