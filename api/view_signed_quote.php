<?php
// เปิดดูไฟล์ใบเสนอราคาที่ลูกค้าเซ็นแล้ว — ต้องล็อกอินก่อน (ไฟล์จริงถูกบล็อกไม่ให้เปิดตรงด้วย .htaccess)
session_start();
include "../config.php";

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('กรุณาเข้าสู่ระบบ');
}

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT quote_no, signed_file FROM quotations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$path = $row && $row['signed_file'] ? __DIR__ . '/../uploads/signed_quotes/' . basename($row['signed_file']) : '';
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="signed_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $row['quote_no']) . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
