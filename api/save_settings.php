<?php
session_start();
include "../config.php"; // ถอยออกจากโฟลเดอร์ api ไปหาไฟล์ตั้งค่า

// --- 1. กรณีลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ดึงชื่อไฟล์รูปมาลบทิ้งจาก Server ด้วย (เพื่อไม่ให้ขยะเต็ม)
    $stmt_img = $conn->prepare("SELECT logo_path FROM companies WHERE id = ?");
    $stmt_img->bind_param("i", $id);
    $stmt_img->execute();
    $res_img = $stmt_img->get_result()->fetch_assoc();
    if($res_img && $res_img['logo_path'] != 'img/default-logo.png') {
        @unlink("../" . $res_img['logo_path']);
    }

    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "ลบข้อมูลบริษัทเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "ไม่สามารถลบข้อมูลได้: " . $conn->error;
    }
    header("Location: ../setting.php"); // ต้องมี ../ เพื่อถอยกลับไปหน้าหลัก
    exit;
}

// --- 2. กรณีบันทึก/แก้ไข ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $company_name = $_POST['company_name'];
    $contact_name = $_POST['contact_name'];
    $phone        = $_POST['phone'];
    $email        = $_POST['email'];
    $address      = $_POST['address'];
    
    // จัดการเรื่อง Path รูปภาพ
    // $logo_path คือค่าที่จะเก็บลง Database (เริ่มจาก img/...)
    $logo_path = ($_POST['old_logo'] != '') ? $_POST['old_logo'] : 'img/default-logo.png';
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $target_dir = "../img/"; // Path สำหรับย้ายไฟล์จริง (Server path)
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $new_filename = "logo_" . time() . "_" . basename($_FILES["logo"]["name"]);
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            // ถ้าย้ายไฟล์สำเร็จ ให้ลบรูปเก่าทิ้ง (ถ้ามีและไม่ใช่รูป default)
            if ($_POST['old_logo'] != '' && $_POST['old_logo'] != 'img/default-logo.png') {
                @unlink("../" . $_POST['old_logo']);
            }
            // กำหนดค่าใหม่เพื่อเตรียมลง DB
            $logo_path = "img/" . $new_filename; 
        }
    }

    if ($id) {
        // อัปเดตข้อมูล (UPDATE)
        $sql = "UPDATE companies SET company_name=?, contact_name=?, phone=?, email=?, address=?, logo_path=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $company_name, $contact_name, $phone, $email, $address, $logo_path, $id);
        $msg_text = "อัปเดตข้อมูลบริษัทเรียบร้อย";
    } else {
        // เพิ่มข้อมูลใหม่ (INSERT)
        $sql = "INSERT INTO companies (company_name, contact_name, phone, email, address, logo_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $company_name, $contact_name, $phone, $email, $address, $logo_path);
        $msg_text = "ลงทะเบียนบริษัทใหม่สำเร็จ";
    }

    if ($stmt->execute()) {
         $_SESSION['flash_msg'] = "success";
         $_SESSION['success'] = $msg_text;
    } else {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }

    header("Location: ../setting.php");
    exit;
}