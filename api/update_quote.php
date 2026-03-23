<?php
include "../config.php";

// ตรวจสอบการส่งค่าแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. รับค่าจากฟอร์ม (อ้างอิงตาม name ในหน้า edit_quotation.php)
    $quote_id = intval($_POST['quote_id']);
    $customer_id = intval($_POST['customer_id']);
    $company_id = intval($_POST['company_id']);
    $event_date = $_POST['event_date'];
    $expiry_date = $_POST['expiry_date'];
    $event_name = $_POST['event_name'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    // ข้อมูลตัวเลข (คูณค่าให้เป็น Float เพื่อความแม่นยำ)
    $subtotal = floatval($_POST['subtotal']);
    $vat = floatval($_POST['vat']); // ใช้ชื่อให้ตรงกับตาราง (vat)
    $grand_total = floatval($_POST['grand_total']);

    // เริ่ม Transaction
    $conn->begin_transaction();

    try {
        // 2. อัปเดตข้อมูลหลักในตาราง quotations 
        // (ปรับชื่อคอลัมน์ให้ตรงกับที่คุณส่งมา: subtotal, vat, grand_total, remarks)
        $sql_update = "UPDATE quotations SET 
                        customer_id = ?, 
                        company_id = ?, 
                        event_date = ?, 
                        expiry_date = ?, 
                        event_name = ?, 
                        subtotal = ?, 
                        vat = ?, 
                        grand_total = ?, 
                        remarks = ?,
                        updated_at = NOW() 
                      WHERE id = ?";

        $stmt = $conn->prepare($sql_update);

        // s = string, i = integer, d = double (decimal)
        $stmt->bind_param(
            "iisssdddsi",
            $customer_id,
            $company_id,
            $event_date,
            $expiry_date,
            $event_name,
            $subtotal,
            $vat,
            $grand_total,
            $remarks,
            $quote_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Update Quotation Failed: " . $stmt->error);
        }

        // 3. ลบรายการย่อยเดิมทิ้ง (quotation_items)
        $sql_delete = "DELETE FROM quotation_items WHERE quote_id = ?";
        $stmt_del = $conn->prepare($sql_delete);
        $stmt_del->bind_param("i", $quote_id);
        $stmt_del->execute();

        // 4. บันทึกรายการย่อยใหม่
        if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
            $sql_item = "INSERT INTO quotation_items (quote_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);

            foreach ($_POST['item_name'] as $key => $name) {
                if (trim($name) !== "") {
                    $qty = floatval($_POST['quantity'][$key]);
                    $price = floatval($_POST['unit_price'][$key]);
                    $total = floatval($_POST['total_price'][$key]);

                    $stmt_item->bind_param("isddd", $quote_id, $name, $qty, $price, $total);
                    $stmt_item->execute();
                }
            }
        }

        // ยืนยันการบันทึก
        $conn->commit();
        $_SESSION['flash_msg'] = "update_success";
        header("Location: ../quotation_list.php");

    } catch (Exception $e) {
        // ยกเลิกหากผิดพลาด
        $conn->rollback();
        echo "<script>
                alert('Error: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
    }

} else {
    die("Invalid request method.");
}
?>