<?php
include "config.php";

// --- กรณีลบข้อมูล (Delete) ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: setting.php?status=deleted");
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}

// --- กรณีเพิ่มหรือแก้ไขข้อมูล (Insert/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'];
    $name = $_POST['name']; // รับค่าชื่อ-นามสกุลเพิ่มมา
    $role = $_POST['role'];
    $password = $_POST['password'];

    if ($id) {
        // กรณี: แก้ไข (Update)
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $name, $hashed_password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $name, $role, $id);
        }
    } else {
        // กรณี: เพิ่มใหม่ (Insert)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $name, $hashed_password, $role);
    }

    if ($stmt->execute()) {
        header("Location: setting.php?status=success");
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}
?>