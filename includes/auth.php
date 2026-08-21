<?php
// includes/auth.php
function check_access($allowed_roles = []) {
    // 1. ถ้าไม่ได้ Login
    if (!isset($_SESSION['user_id'])) {
        echo "<script>window.location.href='login.php?error=pls_login';</script>";
        exit();
    }

    // 2. ปรับตัวแปรให้เป็นตัวเล็กทั้งหมด ป้องกัน Admin กับ admin ไม่ตรงกัน
    $current_role = strtolower($_SESSION['role'] ?? '');
    $allowed = array_map('strtolower', (array)$allowed_roles);

    // --- 🚀 MASTER KEY: ถ้าเป็น admin ให้ผ่านฉลุยทุกด่าน ไม่ต้องเช็คต่อ ---
    if ($current_role === 'admin') {
        return true; 
    }

    // 3. ตรวจสอบสิทธิ์ (ถ้าหน้านั้นจำกัดสิทธิ์ไว้)
    if (!empty($allowed)) {
        if (!in_array($current_role, $allowed)) {
            // ใช้ JavaScript ดีดออก เพื่อแก้ปัญหา Cannot modify header information
            echo "<script>window.location.href='login.php?error=access_denied';</script>";
            exit();
        }
    }
}
?>