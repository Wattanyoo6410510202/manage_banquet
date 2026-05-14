<?php
session_start();
include "../config.php";

if (!isset($_SESSION['user_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $function_id = intval($_POST['function_id']);
    
    // Debug: log inputs if needed, or simply ensure they are accessed
    $update_parts = [];
    if (isset($_POST['banquet_style'])) $update_parts[] = "banquet_style = '" . mysqli_real_escape_string($conn, $_POST['banquet_style']) . "'";
    if (isset($_POST['equipment'])) $update_parts[] = "equipment = '" . mysqli_real_escape_string($conn, $_POST['equipment']) . "'";
    if (isset($_POST['backdrop_detail'])) $update_parts[] = "backdrop_detail = '" . mysqli_real_escape_string($conn, $_POST['backdrop_detail']) . "'";
    if (isset($_POST['hk_florist_detail'])) $update_parts[] = "hk_florist_detail = '" . mysqli_real_escape_string($conn, $_POST['hk_florist_detail']) . "'";
    
    // เพิ่มการตรวจสอบ Checklist ที่ส่งมาจาก form (กรณีมี name attribute)
    // แต่จริงๆ ข้อมูลถูกรวมใน textarea แล้ว ถ้ามันไม่มา แสดงว่าอาจมีปัญหาที่ Form data
    
    $success = true;
    if (!empty($update_parts)) {
        $sql = "UPDATE functions SET " . implode(', ', $update_parts) . " WHERE id = $function_id";
        if (!mysqli_query($conn, $sql)) $success = false;
    }

    // Handle file upload if present
    if ($success && isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/backdrops/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = time() . '_' . basename($_FILES['backdrop_img']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], $target_path)) {
            $db_path = 'uploads/backdrops/' . $file_name;
            $update_img_sql = "UPDATE functions SET backdrop_img = '$db_path' WHERE id = $function_id";
            mysqli_query($conn, $update_img_sql);
        }
    }
    
    echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $success ? 'บันทึกเรียบร้อย' : mysqli_error($conn)]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
