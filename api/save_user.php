<?php
session_start();
include "../config.php"; // ถอยออกจากโฟลเดอร์ api ไปหาไฟล์ตั้งค่า

// --- 1. กรณีลบข้อมูล (Delete) ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "ไม่สามารถลบข้อมูลได้: " . $conn->error;
    }
    header("Location: ../setting.php"); // ถอยกลับไปหน้าหลัก
    exit();
}

// --- 2. กรณีเพิ่มหรือแก้ไขข้อมูล (Insert/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'];
    $name = $_POST['name']; 
    $role = $_POST['role'];
    $password = $_POST['password'];

    if ($id) {
        // --- กรณี: แก้ไข (Update) ---
        if (!empty($password)) {
            // ถ้ามีการกรอกรหัสผ่านใหม่เข้ามา ให้ Hash และอัปเดต
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $name, $hashed_password, $role, $id);
        } else {
            // ถ้าไม่กรอกรหัสผ่าน ให้อัปเดตแค่ข้อมูลส่วนอื่น
            $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $name, $role, $id);
        }
        $msg = "อัปเดตข้อมูลผู้ใช้เรียบร้อย";
    } else {
        // --- กรณี: เพิ่มใหม่ (Insert) ---
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $name, $hashed_password, $role);
        $msg = "เพิ่มผู้ใช้งานใหม่สำเร็จ";
    }

    if ($stmt->execute()) {
        $_SESSION['flash_msg'] = "success";
    } else {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
    
    header("Location: ../setting.php");
    exit();
}
?>