<?php
session_start();
include "../config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$workflow_status = $_POST['workflow_status'] ?? '';

$allowed = [
    'Draft',
    'ส่งใบเสนอราคาแล้ว',
    'Follow Up ครั้งที่ 1',
    'Follow Up ครั้งที่ 2',
    'Follow Up ครั้งที่ 3',
    'ลูกค้าต่อรองราคา',
    'รออนุมัติส่วนลด',
    'ส่งใบเสนอราคาใหม่ (Revision)',
    'ลูกค้าเซ็นยืนยัน',
    'รับเงินมัดจำแล้ว',
    'เปิด Function (BEO)',
    'Lost Sale',
    'Cancelled',
];

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสใบเสนอราคา']);
    exit;
}

$role = strtolower($_SESSION['role'] ?? 'staff');
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$is_admin_or_gm = in_array($role, ['admin', 'gm']);

if (!$is_admin_or_gm) {
    $check = $conn->prepare("SELECT created_by FROM quotations WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    if (!$row || intval($row['created_by']) !== $current_user_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขสถานะใบเสนอนี้']);
        exit;
    }
}

if (!in_array($workflow_status, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'สถานะไม่ถูกต้อง: ' . $workflow_status]);
    exit;
}

$stmt = $conn->prepare("UPDATE quotations SET workflow_status = ? WHERE id = ?");
$stmt->bind_param("si", $workflow_status, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'workflow_status' => $workflow_status]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'อัปเดตไม่สำเร็จ: ' . $conn->error]);
}
