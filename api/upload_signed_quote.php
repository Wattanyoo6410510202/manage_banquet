<?php
// อัปโหลดใบเสนอราคาที่ลูกค้าเซ็นแล้ว (เฉพาะใบที่อนุมัติแล้ว) → เปลี่ยน workflow_status เป็น "ลูกค้าเซ็นยืนยัน"
session_start();
include "../config.php";
include_once "../includes/audit.php";
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
if (empty($_SESSION['user_id'])) fail('กรุณาเข้าสู่ระบบ', 401);

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) fail('ไม่พบรหัสใบเสนอราคา');

$stmt = $conn->prepare("SELECT id, quote_no, status, created_by, signed_file FROM quotations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
if (!$quote) fail('ไม่พบใบเสนอราคา', 404);

$role = strtolower($_SESSION['role'] ?? 'staff');
$current_user_id = intval($_SESSION['user_id']);
if (!in_array($role, ['admin', 'gm']) && intval($quote['created_by']) !== $current_user_id) {
    fail('ไม่มีสิทธิ์อัปโหลดไฟล์ของใบเสนอนี้', 403);
}
if ($quote['status'] !== 'Approved') fail('ใบเสนอราคาต้องได้รับการอนุมัติก่อน จึงจะอัปโหลดใบที่ลูกค้าเซ็นได้');

$file = $_FILES['signed_file'] ?? null;
if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) fail('กรุณาเลือกไฟล์');
if ($file['error'] !== UPLOAD_ERR_OK) fail('อัปโหลดไม่สำเร็จ (error ' . $file['error'] . ') — ไฟล์อาจใหญ่เกินไป');
if ($file['size'] > 10 * 1024 * 1024) fail('ไฟล์ต้องไม่เกิน 10MB');

// ตรวจชนิดไฟล์จากเนื้อไฟล์จริง ไม่เชื่อนามสกุลที่ส่งมา
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowed[$mime])) fail('รองรับเฉพาะไฟล์ PDF, JPG, PNG');

$dir = __DIR__ . '/../uploads/signed_quotes/';
if (!is_dir($dir) && !mkdir($dir, 0775, true)) fail('สร้างโฟลเดอร์เก็บไฟล์ไม่ได้', 500);
// กันเปิดไฟล์ตรงๆ ผ่าน URL (uploads/ ไม่อยู่ใน git จึงสร้างตรงนี้) — ให้เปิดผ่าน api/view_signed_quote.php เท่านั้น
if (!is_file($dir . '.htaccess')) @file_put_contents($dir . '.htaccess', "Require all denied\n");

$fileName = 'signed_' . $id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . $fileName)) fail('บันทึกไฟล์ไม่สำเร็จ', 500);

$workflow_status = 'ลูกค้าเซ็นยืนยัน';
$upd = $conn->prepare("UPDATE quotations
                       SET signed_file = ?, signed_uploaded_at = NOW(), signed_uploaded_by = ?, workflow_status = ?
                       WHERE id = ?");
$upd->bind_param("sisi", $fileName, $current_user_id, $workflow_status, $id);
if (!$upd->execute()) {
    @unlink($dir . $fileName);
    fail('อัปเดตไม่สำเร็จ: ' . $conn->error, 500);
}

// อัปโหลดทับ → ลบไฟล์เก่าทิ้ง
if (!empty($quote['signed_file']) && $quote['signed_file'] !== $fileName) {
    @unlink($dir . basename($quote['signed_file']));
}

audit_log($conn, 'upload', 'quotation', $id, 'อัปโหลดใบเสนอราคาที่ลูกค้าเซ็นแล้ว (' . $quote['quote_no'] . ') → สถานะ: ' . $workflow_status);

echo json_encode(['status' => 'success', 'workflow_status' => $workflow_status], JSON_UNESCAPED_UNICODE);
