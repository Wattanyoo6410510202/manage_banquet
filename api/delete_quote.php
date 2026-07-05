<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $user_role = strtolower($_SESSION['role'] ?? '');
    $user_id = intval($_SESSION['user_id'] ?? 0);

    $conn->begin_transaction();

    try {
        // เช็คสิทธิ์: Admin ลบได้ทุกอย่าง, นอกนั้นลบได้เฉพาะของตัวเอง
        if ($user_role !== 'admin') {
            $stmt_check = $conn->prepare("SELECT id FROM quotations WHERE id = ? AND created_by != ?");
            $stmt_check->bind_param("ii", $id, $user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("คุณไม่มีสิทธิ์ลบใบเสนอราคานี้");
            }
        }

        // 1. ลบรายการย่อยก่อน (quotation_items)
        $sql_items = "DELETE FROM quotation_items WHERE quote_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $id);
        $stmt_items->execute();

        // 2. ลบใบเสนอราคาหลัก (quotations)
        $sql_main = "DELETE FROM quotations WHERE id = ?";
        $stmt_main = $conn->prepare($sql_main);
        $stmt_main->bind_param("i", $id);
        $stmt_main->execute();

        $conn->commit();
        echo "success";

    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
} else {
    echo "invalid_request";
}
