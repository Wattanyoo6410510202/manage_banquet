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
    $project_id  = (!empty($_POST['project_id'])) ? intval($_POST['project_id']) : null;
    $customer_id = intval($_POST['customer_id']);
    $quote_no    = $_POST['quote_no'];
    $event_name  = $_POST['event_name'];
    $event_date  = $_POST['event_date'];
    $expiry_date = $_POST['expiry_date'];
    $subtotal    = floatval($_POST['subtotal']);
    $discount    = floatval($_POST['discount'] ?? 0);
    $vat         = floatval($_POST['vat']);
    $grand_total = floatval($_POST['grand_total']);
    $vat_type    = $_POST['vat_type'] ?? 'exclude';
    $service_charge = floatval($_POST['service_charge'] ?? 0);
    $remarks     = $_POST['remarks'] ?? '';
    $lost_reason = $_POST['lost_reason'] ?? '';
    $lead_source = $_POST['lead_source'] ?? '';
    $result = $_POST['result'] ?? '';
    $inspection_date = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : null;
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $approved_at = !empty($_POST['approved_at']) ? $_POST['approved_at'] : null;
    $created_by  = $_SESSION['user_id'] ?? null;

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

        // 3. ถ้ายังไม่มี project_id ให้สร้าง project ใหม่จาก event_name
        if (!$project_id && !empty($event_name)) {
            $sql_new_project = "INSERT INTO event_projects (project_name, customer_id, company_id, status, created_by) VALUES (?, ?, ?, 'Pending', ?)";
            $stmt_new_project = $conn->prepare($sql_new_project);
            $stmt_new_project->bind_param("siss", $event_name, $customer_id, $company_id, $created_by);
            $stmt_new_project->execute();
            $project_id = $conn->insert_id;
        }

        // 4. เตรียมคำสั่ง INSERT (เพิ่ม company_id, remarks, created_by, project_id, lost_reason, vat_type)
        $sql_quote = "INSERT INTO quotations (
            company_id, function_id, project_id, customer_id, quote_no, event_name, 
            event_date, expiry_date, subtotal, service_charge, discount, vat, 
            grand_total, vat_type, status, remarks, lost_reason,
            lead_source, result, inspection_date, follow_up_date, approved_at, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql_quote);
        
        $stmt->bind_param(
            "iiiissssdddddssssssssi",
            $company_id,
            $function_id,
            $project_id,
            $customer_id,
            $quote_no,
            $event_name,
            $event_date,
            $expiry_date,
            $subtotal,
            $service_charge,
            $discount,
            $vat,
            $grand_total,
            $vat_type,
            $remarks,
            $lost_reason,
            $lead_source,
            $result,
            $inspection_date,
            $follow_up_date,
            $approved_at,
            $created_by
        );
        $stmt->execute();

        $last_quote_id = $conn->insert_id;

        // 5. บันทึกรายการย่อย (Quotation Items)
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

        // LINE แจ้ง GM/Admin ว่ามีใบเสนอราคาใหม่รออนุมัติ
        // ครอบ try ไว้เพราะแจ้งเตือนล้มเหลวต้องไม่ทำให้ใบเสนอราคาที่ commit แล้วพัง
        try {
            include_once __DIR__ . "/../line_helper.php";

            $cust_name = '';
            if ($customer_id) {
                $q_c = $conn->prepare("SELECT cust_name FROM customers WHERE id = ?");
                $q_c->bind_param("i", $customer_id);
                $q_c->execute();
                $c_row = $q_c->get_result()->fetch_assoc();
                $cust_name = $c_row['cust_name'] ?? '';
            }

            $origin = lineOriginUrl();
            $flex = buildNotifyFlex([
                'title'    => '📄 ใบเสนอราคาใหม่รออนุมัติ',
                'subtitle' => 'New Quotation - Pending Approval',
                'color'    => '#1A73E8',
                'rows'     => [
                    ['📌', $event_name, '#111111'],
                    ['🧾', $quote_no, '#111111'],
                    ['👤', $cust_name],
                    ['📅 วันจัดงาน', $event_date ? date('d/m/Y', strtotime($event_date)) : '-'],
                    ['⏳ ยืนราคาถึง', $expiry_date ? date('d/m/Y', strtotime($expiry_date)) : '-'],
                    ['💰 ยอดรวม', number_format($grand_total, 2) . ' บาท', '#111111'],
                    ['💸 ส่วนลด', number_format($discount, 2) . ' บาท'],
                ],
                'note'     => 'รอ GM อนุมัติก่อนส่งให้ลูกค้า',
                'buttons'  => [
                    ['ดูใบเสนอราคา', $origin . '/manage_banquet/quotation_view.php?id=' . $last_quote_id],
                    ['📋 รายการทั้งหมด', $origin . '/manage_banquet/quotation_list.php'],
                ],
            ]);
            sendLineFlexToRoles($conn, ['gm', 'admin'], $flex, '📄 ใบเสนอราคาใหม่รออนุมัติ: ' . $event_name);
        } catch (Throwable $e) {
            error_log('[LINE] new quotation notify failed: ' . $e->getMessage());
        }

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