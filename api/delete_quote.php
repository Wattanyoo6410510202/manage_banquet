<?php
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // เริ่ม Transaction (เพราะต้องลบ 2 ตาราง: ตารางหลัก และ ตารางลูก)
    $conn->begin_transaction();

    try {
        // 1. ลบรายการย่อยก่อน (quotation_items) เพื่อป้องกัน Error Foreign Key
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