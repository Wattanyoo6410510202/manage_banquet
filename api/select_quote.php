<?php
include "../config.php";

header('Content-Type: application/json');

// ปุ่ม "เลือกใช้งาน" เปิดให้เฉพาะ admin กับ staff — ต้องเช็คที่ฝั่งเซิร์ฟเวอร์ด้วย
// เพราะการซ่อนปุ่มใน quotation_list.php กันได้แค่การคลิก ยิง URL ตรงยังผ่าน
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เลือกใบเสนอราคา'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$id = intval($_GET['id']);
$deselect = isset($_GET['deselect']) && $_GET['deselect'] == 1;

try {
    // 1. ดึงข้อมูล project_id ของใบเสนอราคานี้
    $stmt_sel = $conn->prepare("SELECT project_id FROM quotations WHERE id = ?");
    $stmt_sel->bind_param("i", $id);
    $stmt_sel->execute();
    $res = $stmt_sel->get_result();
    $row = $res->fetch_assoc();
    $project_id = $row['project_id'] ?? null;

    $conn->begin_transaction();

    if ($deselect) {
        // ยกเลิกการเลือกใบนี้
        $stmt_upd = $conn->prepare("UPDATE quotations SET is_selected = 0 WHERE id = ?");
        $stmt_upd->bind_param("i", $id);
        $stmt_upd->execute();
    } else {
        // 2. ถ้ามี project_id ให้ยกเลิกการเลือกใบอื่นใน project เดียวกันก่อน
        if ($project_id) {
            $stmt_upd = $conn->prepare("UPDATE quotations SET is_selected = 0 WHERE project_id = ?");
            $stmt_upd->bind_param("i", $project_id);
            $stmt_upd->execute();
        }
        // 3. เลือกใบนี้เป็นใบหลัก
        $stmt_upd = $conn->prepare("UPDATE quotations SET is_selected = 1 WHERE id = ?");
        $stmt_upd->bind_param("i", $id);
        $stmt_upd->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
