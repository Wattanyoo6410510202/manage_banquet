<?php
session_start();
include "config.php"; 

// กรณีลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) $_SESSION['success'] = "ลบข้อมูลเรียบร้อยแล้ว";
    header("Location: setting.php");
    exit;
}

// กรณีบันทึก/แก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $company_name = $_POST['company_name'];
    $contact_name = $_POST['contact_name'];
    $phone        = $_POST['phone'];
    $email        = $_POST['email'];
    $address      = $_POST['address'];
    
    // จัดการรูปภาพ
    $logo_path = $_POST['old_logo'] != '' ? $_POST['old_logo'] : 'img/default-logo.png';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $target_dir = "img/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $new_filename = "logo_" . time() . "_" . basename($_FILES["logo"]["name"]);
        $target_file = $target_dir . $new_filename;
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            $logo_path = $target_file;
        }
    }

    if ($id) {
        // อัปเดตข้อมูล (UPDATE)
        $sql = "UPDATE companies SET company_name=?, contact_name=?, phone=?, email=?, address=?, logo_path=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $company_name, $contact_name, $phone, $email, $address, $logo_path, $id);
    } else {
        // เพิ่มข้อมูลใหม่ (INSERT)
        $sql = "INSERT INTO companies (company_name, contact_name, phone, email, address, logo_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $company_name, $contact_name, $phone, $email, $address, $logo_path);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: setting.php");
    exit;
}