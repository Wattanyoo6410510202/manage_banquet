<?php
// เริ่มต้น Session เพื่อเช็คสถานะ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เช็คว่ามี session ของ user หรือยัง
// ถ้ามีแล้ว (Login ค้างไว้) ให้เด้งไปหน้า Dashboard เลย
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit;
} else {
    // ถ้ายังไม่ได้ Login หรือ Session หลุด ให้เด้งไปหน้า Login
    header("Location: login.php");
    exit;
}
?>