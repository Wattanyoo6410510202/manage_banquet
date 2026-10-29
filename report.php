<?php
// รายงานรายวัน/รายเดือน/รายปี ย้ายไปรวมอยู่ในหน้า Dashboard แล้ว — คงไฟล์นี้ไว้ให้ลิงก์เดิมยังใช้ได้
header('Location: executive_dashboard.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
