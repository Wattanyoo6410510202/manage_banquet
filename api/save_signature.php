<?php
session_start();
header('Content-Type: application/json');
include "../config.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']);
    exit();
}

$session_user_id = $_SESSION['user_id']; 
$input = json_decode(file_get_contents('php://input'), true);
$function_id = isset($input['id']) ? intval($input['id']) : 0; 
$dataURL = isset($input['image']) ? $input['image'] : '';

if (empty($dataURL)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลลายเซ็น']);
    exit();
}

$uploadDir = '../uploads/signatures/';
if (!file_exists($uploadDir)) { mkdir($uploadDir, 0777, true); }

try {
    // 1. ค้นหาข้อมูลเดิมก่อนว่าเคยมีลายเซ็นของ user_id นี้หรือยัง
    $checkSql = "SELECT id, path FROM signatures WHERE users_id = ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("i", $session_user_id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $oldRecord = $result->fetch_assoc();

    // 2. ถ้ามีของเก่า ให้ลบไฟล์จริงออกจาก Folder
    if ($oldRecord) {
        $oldFilePath = "../" . $oldRecord['path']; // เติม ../ เพื่อถอยออกจากโฟลเดอร์ api
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            unlink($oldFilePath); // สั่งลบไฟล์ภาพเก่าทันที
        }
    }

    // 3. เตรียมไฟล์ภาพใหม่
    $image_parts = explode(";base64,", $dataURL);
    $image_base64 = base64_decode($image_parts[1]);
    
    // ตั้งชื่อไฟล์ใหม่ (ใส่ timestamp ป้องกัน browser จำแคชรูปเก่า)
    $fileName = 'sig_' . $session_user_id . '_' . time() . '.png';
    $fileFullPath = $uploadDir . $fileName;
    $dbPath = 'uploads/signatures/' . $fileName;

    if (file_put_contents($fileFullPath, $image_base64)) {
        
        if ($oldRecord) {
            // 4. กรณีมี Record เดิม: ให้ UPDATE path ใหม่
            $sql = "UPDATE signatures SET path = ?, created_at = NOW() WHERE users_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $dbPath, $session_user_id);
        } else {
            // 5. กรณีเป็นครั้งแรก: ให้ INSERT ใหม่
            $sql = "INSERT INTO signatures (path, users_id, created_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $dbPath, $session_user_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'อัปเดตลายเซ็นเรียบร้อย']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->error]);
        }
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}