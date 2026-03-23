<?php
include "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. รับค่าจากฟอร์ม (รวมส่วนที่เพิ่มใหม่)
    $company_id  = (!empty($_POST['company_id'])) ? intval($_POST['company_id']) : null;
    $function_id = (!empty($_POST['function_id'])) ? intval($_POST['function_id']) : null;
    $customer_id = intval($_POST['customer_id']);
    $quote_no    = $_POST['quote_no'];
    $event_name  = $_POST['event_name'];
    $event_date  = $_POST['event_date'];
    $expiry_date = $_POST['expiry_date'];
    $subtotal    = floatval($_POST['subtotal']);
    $vat         = floatval($_POST['vat']);
    $grand_total = floatval($_POST['grand_total']);
    $service_charge = floatval($_POST['service_charge'] ?? 0);
    $remarks     = $_POST['remarks'] ?? ''; // รับค่าหมายเหตุ
    $created_by  = $_SESSION['user_id'] ?? null; // เก็บ ID ผู้สร้าง (ถ้ามี session)

    $conn->begin_transaction();

    try {
        // 2. ตรวจสอบเลขที่ใบเสนอราคาซ้ำ
        $check_sql = "SELECT id FROM quotations WHERE quote_no = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $quote_no);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $quote_no .= "-" . date('is'); // ถ้าซ้ำเติม นาที+วินาที
        }

        // 3. เตรียมคำสั่ง INSERT (เพิ่ม company_id, remarks, created_by)
        $sql_quote = "INSERT INTO quotations (
            company_id, function_id, customer_id, quote_no, event_name, 
            event_date, expiry_date, subtotal, service_charge, vat, 
            grand_total, status, remarks, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?)";

        $stmt = $conn->prepare($sql_quote);
        
        // "iiissssddddsi" -> i=int, s=string, d=decimal
        $stmt->bind_param(
            "iiissssddddsi",
            $company_id,
            $function_id,
            $customer_id,
            $quote_no,
            $event_name,
            $event_date,
            $expiry_date,
            $subtotal,
            $service_charge,
            $vat,
            $grand_total,
            $remarks,
            $created_by
        );
        $stmt->execute();

        $last_quote_id = $conn->insert_id;

        // 4. บันทึกรายการย่อย (Quotation Items)
        $item_names   = $_POST['item_name'] ?? [];
        $quantities   = $_POST['quantity'] ?? [];
        $unit_prices  = $_POST['unit_price'] ?? [];
        $total_prices = $_POST['total_price'] ?? [];

        $sql_item = "INSERT INTO quotation_items (quote_id, item_name, quantity, unit_price, total_price, item_type) 
                     VALUES (?, ?, ?, ?, ?, 'Food')";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($item_names as $index => $name) {
            if (trim($name) !== "") {
                $qty   = intval($quantities[$index]);
                $price = floatval($unit_prices[$index]);
                $total = floatval($total_prices[$index]);
                $stmt_item->bind_param("isidd", $last_quote_id, $name, $qty, $price, $total);
                $stmt_item->execute();
            }
        }

        $conn->commit();

        $_SESSION['flash_msg'] = "success";
        header("Location: ../quotation_list.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        // กรณี Error เลขที่ซ้ำ (Duplicate Entry)
        if ($conn->errno == 1062) {
            echo "<script>alert('เลขที่ใบเสนอราคานี้ซ้ำในระบบ'); window.history.back();</script>";
        } else {
            die("Error: " . $e->getMessage());
        }
    }
}