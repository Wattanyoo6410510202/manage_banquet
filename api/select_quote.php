<?php
include "../config.php";

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$id = intval($_GET['id']);
$deselect = isset($_GET['deselect']) && $_GET['deselect'] == 1;

try {
    // 1. ดึงข้อมูล project_id ของใบเสนอราคานี้
    $res = $conn->query("SELECT project_id FROM quotations WHERE id = $id");
    $row = $res->fetch_assoc();
    $project_id = $row['project_id'];

    $conn->begin_transaction();

    if ($deselect) {
        // ยกเลิกการเลือกใบนี้
        $conn->query("UPDATE quotations SET is_selected = 0 WHERE id = $id");
    } else {
        // 2. ถ้ามี project_id ให้ยกเลิกการเลือกใบอื่นใน project เดียวกันก่อน
        if ($project_id) {
            $conn->query("UPDATE quotations SET is_selected = 0 WHERE project_id = $project_id");
        }
        // 3. เลือกใบนี้เป็นใบหลัก
        $conn->query("UPDATE quotations SET is_selected = 1 WHERE id = $id");
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
