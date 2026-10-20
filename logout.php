<?php
include "config.php";
include_once "includes/audit.php";
if (isset($_SESSION['user_id'])) audit_log($conn, 'logout', 'user', $_SESSION['user_id'], 'ออกจากระบบ');
session_destroy();
header("Location: login.php");
exit;
?>