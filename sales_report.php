<?php
// รายงานการขายย้ายไปรวมอยู่ในหน้า "งานขาย" (sales_dept.php) แล้ว — คงไฟล์นี้ไว้ให้ลิงก์เดิมยังใช้ได้
header('Location: sales_dept.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
