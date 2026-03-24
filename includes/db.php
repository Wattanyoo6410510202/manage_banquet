<?php
$conn = mysqli_connect("127.0.0.1","root","12345gta","managebanquet_simple",3307);

if (!$conn) {
    die("Database Error");
}

mysqli_set_charset($conn,"utf8mb4");

/* ปิด strict mode เฉพาะเว็บนี้ */
mysqli_query($conn,"SET SESSION sql_mode=''");

/* เริ่ม session ถ้ายังไม่มี */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>