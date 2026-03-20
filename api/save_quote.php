<?php
include "../config.php";
session_start();

// ตั้งค่าให้แสดง Error เพื่อการ Debug (ถ้าเอาขึ้นจริงค่อยปิดครับจารย์)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าและจัดการประเภทข้อมูล
    // แก้ปัญหา function_id: ถ้าว่างให้ส่งเป็น null
    $function_id = (!empty($_POST['function_id']) && $_POST['function_id'] != "") ? $_POST['function_id'] : null;
    
    $customer_id = $_POST['customer_id'];
    $quote_no    = $_POST['quote_no'];
    $event_name  = $_POST['event_name'];
    $event_date  = $_POST['event_date'];
    $expiry_date = $_POST['expiry_date'];
    
    // แปลงตัวเลขป้องกัน String error
    $subtotal       = floatval($_POST['subtotal']);
    $service_charge = floatval($_POST['service_charge'] ?? 0);
    $vat            = floatval($_POST['vat']);
    $grand_total    = floatval($_POST['grand_total']);
    
    // ข้อมูลรายการสินค้า (Array)
    $item_names   = $_POST['item_name'] ?? [];
    $quantities   = $_POST['quantity'] ?? [];
    $unit_prices  = $_POST['unit_price'] ?? [];
    $total_prices = $_POST['total_price'] ?? [];

    // 2. เริ่มต้น Transaction (กันข้อมูลค้างถ้า insert พลาด)
    $conn->begin_transaction();

    try {
        // 3. บันทึกลงตาราง quotations (ตารางหลัก)
        $sql_quote = "INSERT INTO quotations (
            function_id, customer_id, quote_no, event_name, event_date, 
            expiry_date, subtotal, service_charge, vat, grand_total, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')";

        $stmt = $conn->prepare($sql_quote);
        
        // ใช้ iissssdddd (i=int, s=string, d=double/float)
        $stmt->bind_param(
            "iissssdddd", 
            $function_id, $customer_id, $quote_no, $event_name, $event_date, 
            $expiry_date, $subtotal, $service_charge, $vat, $grand_total
        );
        $stmt->execute();
        
        // รับ ID ที่เพิ่ง Insert เพื่อเอาไปใส่ตารางลูก
        $last_quote_id = $conn->insert_id;

        // 4. บันทึกลงตาราง quotation_items (ตารางรายการย่อย)
        // 🎯 แก้เป็น quote_id ตามโครงสร้างตารางของจารย์แล้วครับ
        $sql_item = "INSERT INTO quotation_items (quote_id, item_name, quantity, unit_price, total_price, item_type) 
                     VALUES (?, ?, ?, ?, ?, 'Food')"; 
        
        $stmt_item = $conn->prepare($sql_item);

        foreach ($item_names as $index => $name) {
            if (trim($name) !== "") { 
                $qty   = intval($quantities[$index]);
                $price = floatval($unit_prices[$index]);
                $total = floatval($total_prices[$index]);
                
                // ผูกข้อมูล: i = quote_id, s = name, i = qty, d = price, d = total
                $stmt_item->bind_param("isidd", $last_quote_id, $name, $qty, $price, $total);
                $stmt_item->execute();
            }
        }

        // 5. ยืนยันการบันทึก
        $conn->commit();

        echo "<script>
                alert('บันทึกใบเสนอราคาเลขที่ $quote_no เรียบร้อยแล้ว');
                window.location.href = '../quotation_list.php';
              </script>";

    } catch (Exception $e) {
        // หากพลาดให้ Rollback ข้อมูลทั้งหมด
        $conn->rollback();
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    header("Location: ../add_quote.php");
    exit();
}