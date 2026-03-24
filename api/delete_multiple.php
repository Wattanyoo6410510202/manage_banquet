<?php
ob_start();
session_start();
include "../config.php";

header('Content-Type: application/json');

// 1. ดึงข้อมูลจาก Session มาเช็คสิทธิ์
$current_user = $_SESSION['user_name'] ?? '';
$user_role = strtolower($_SESSION['role'] ?? 'viewer'); // ใช้ตัวเล็กเช็คแม่นยำกว่า

if (isset($_POST['ids']) && is_array($_POST['ids'])) {

    // --- แก้ไขจุดนี้: ส่ง JSON แทนการ Echo Script ---
    if ($user_role === 'viewer') {
        echo json_encode([
            'status' => 'error',
            'message' => 'ขออภัย! คุณมีสิทธิ์เข้าชมอย่างเดียว (Viewer) ไม่สามารถลบข้อมูลได้'
        ]);
        exit;
    }

    $ids = array_map('intval', $_POST['ids']);
    $ids_string = implode(',', $ids);

    $conn->begin_transaction();

    try {
        // 🚀 2. เช็คสิทธิ์เข้มงวด (ดักทั้ง Viewer และ Staff)

        // ด่านที่ 1: ถ้าเป็น Viewer "ห้ามลบทุกกรณี"
        if ($user_role === 'viewer') {
            throw new Exception("ขออภัย! คุณมีสิทธิ์เข้าชมอย่างเดียว ไม่สามารถลบข้อมูลได้");
        }

        // ด่านที่ 2: ถ้าเป็น Staff เช็คสิทธิ์ความเป็นเจ้าของและสถานะ Approve
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

        $res_files = $conn->query("SELECT backdrop_img, file_attachment1, file_attachment2, file_attachment3 FROM functions WHERE id IN ($ids_string)");

        while ($row = $res_files->fetch_assoc()) {
            $cols = ['backdrop_img', 'file_attachment1', 'file_attachment2', 'file_attachment3'];

            foreach ($cols as $col) {
                if (!empty($row[$col])) {
                    $file_relative_path = "../" . $row[$col];

                    // หาโฟลเดอร์ที่เก็บไฟล์นั้น (เช่นจาก ../uploads/attach_2026/f1.pdf -> ../uploads/attach_2026)
                    $dir_path = dirname($file_relative_path);

                    if (is_dir($dir_path)) {
                        // 1. ลบไฟล์ทุกไฟล์ที่อยู่ในโฟลเดอร์นั้นก่อน (PHP ลบโฟลเดอร์ที่มีไฟล์ไม่ได้)
                        $inner_files = glob($dir_path . '/*');
                        foreach ($inner_files as $f) {
                            if (is_file($f))
                                unlink($f);
                        }

                        // 2. พอข้างในว่างแล้ว สั่งลบโฟลเดอร์ทิ้งเลยจาร
                        @rmdir($dir_path);
                    }
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