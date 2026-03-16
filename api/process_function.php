<?php
include "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['save'])) {

    // --- 1. รับค่าข้อมูลทั่วไป (เพิ่ม 2 ฟิลด์ที่ขาดไป) ---
    $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null; // เพิ่มใหม่
    $function_type_id = !empty($_POST['function_type_id']) ? intval($_POST['function_type_id']) : null; // เพิ่มใหม่
    $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;

    $function_name = $_POST['function_name'] ?? '';
    $booking_name = $_POST['booking_name'] ?? '';
    $organization = $_POST['organization'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $booking_room = $_POST['booking_room'] ?? '';
    $pax = intval($_POST['pax'] ?? 0);
    $deposit = floatval($_POST['deposit'] ?? 0);

    $banquet_style = $_POST['banquet_style'] ?? '';
    $equipment = $_POST['equipment'] ?? '';
    $remark = $_POST['remark'] ?? '';
    $main_kitchen_remark = $_POST['main_kitchen_remark'] ?? '';
    $backdrop_detail = $_POST['backdrop_detail'] ?? '';
    $hk_florist_detail = $_POST['hk_florist_detail'] ?? '';

    $created_by_name = $_SESSION['user_name'] ?? 'Unknown';

    $start_date = $_POST['start_time'] ?? null;
    $end_date = $_POST['end_time'] ?? null;

    // --- 2. จัดการรูปภาพ ---
    $backdrop_img_path = $_POST['backdrop_img_path_ai'] ?? '';
    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $target = "uploads/backdrop_" . time() . "." . pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], $target)) {
            $backdrop_img_path = $target;
        }
    }

    $conn->begin_transaction();

    try {
        // --- 3. แก้ไข SQL INSERT (เพิ่ม customer_id, function_type_id) ---
        // --- 3. แก้ไข SQL INSERT (ตรวจสอบจำนวน Column และ ? ให้เท่ากันคือ 21 ตัว) ---
        $sql_main = "INSERT INTO functions (
    company_id, customer_id, function_type_id, room_id, function_name, 
    booking_name, organization, phone, booking_room, deposit, 
    banquet_style, equipment, remark, main_kitchen_remark, 
    backdrop_detail, hk_florist_detail, backdrop_img, created_by, pax, 
    start_time, end_time
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // มี ? ทั้งหมด 21 ตัว

        $stmt = $conn->prepare($sql_main);

        // ผูกตัวแปร (i=int, s=string, d=double) 
// ต้องระบุประเภทให้ครบ 21 ตัว: iiii sssss d sssssss i ss
        $stmt->bind_param(
            "iiiisssssdssssssssiss", // รวม 21 ตัวอักษร
            $company_id,         // 1 (i)
            $customer_id,        // 2 (i)
            $function_type_id,   // 3 (i)
            $room_id,            // 4 (i)
            $function_name,      // 5 (s)
            $booking_name,       // 6 (s)
            $organization,       // 7 (s)
            $phone,              // 8 (s)
            $booking_room,       // 9 (s)
            $deposit,            // 10 (d)
            $banquet_style,      // 11 (s)
            $equipment,          // 12 (s)
            $remark,             // 13 (s)
            $main_kitchen_remark,// 14 (s)
            $backdrop_detail,    // 15 (s)
            $hk_florist_detail,  // 16 (s)
            $backdrop_img_path,  // 17 (s)
            $created_by_name,    // 18 (s)
            $pax,                // 19 (i)
            $start_date,         // 20 (s)
            $end_date            // 21 (s)
        );

        if (!$stmt->execute()) {
            throw new Exception("บันทึกตารางหลักล้มเหลว: " . $stmt->error);
        }

        $last_id = $conn->insert_id;

        // สร้างเลขรันงาน
        $final_code = str_pad($last_id, 5, '0', STR_PAD_LEFT) . "/" . date('dm');
        $conn->query("UPDATE functions SET function_code = '$final_code' WHERE id = $last_id");

        // --- 4. บันทึกตารางย่อย (เหมือนเดิม) ---

        // Schedule
        if (!empty($_POST['schedule_date'])) {
            $stmt_s = $conn->prepare("INSERT INTO function_schedules (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['schedule_date'] as $k => $val) {
                if (trim($val) != "") {
                    $s_hour = $_POST['schedule_hour'][$k] ?? '';
                    $s_func = $_POST['schedule_function'][$k] ?? '';
                    $s_guar = $_POST['schedule_guarantee'][$k] ?? '';
                    $stmt_s->bind_param("issss", $last_id, $val, $s_hour, $s_func, $s_guar);
                    $stmt_s->execute();
                }
            }
        }

        // Kitchen
        if (!empty($_POST['k_item'])) {
            $stmt_k = $conn->prepare("INSERT INTO function_kitchens (function_id, k_date, k_type_id, k_item, k_qty, k_remark) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['k_item'] as $k => $item) {
                if (trim($item) != "") {
                    $k_date = !empty($_POST['k_date'][$k]) ? $_POST['k_date'][$k] : null;
                    $k_type = intval($_POST['k_type_id'][$k] ?? 0);
                    $k_qty = intval($_POST['k_qty'][$k] ?? 0);
                    $k_rem = $_POST['k_remark'][$k] ?? '';
                    $stmt_k->bind_param("isisis", $last_id, $k_date, $k_type, $item, $k_qty, $k_rem);
                    $stmt_k->execute();
                }
            }
        }

        // --- 5. บันทึกตารางเมนูอาหารและเครื่องดื่ม (ตารางที่ 5) ---
        if (!empty($_POST['menu_detail'])) {
            // แก้ไขชื่อตารางและฟิลด์ให้ตรงกับ Database ของคุณ
            $stmt_m = $conn->prepare("INSERT INTO function_menus (function_id, 	menu_time, menu_set_id, menu_detail, menu_qty, menu_price) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($_POST['menu_detail'] as $k => $detail) {
                if (trim($detail) != "") {
                    // รับค่าจาก input array ใน HTML
                    $m_date = !empty($_POST['menu_time'][$k]) ? $_POST['menu_time'][$k] : null; // ใน HTML คุณใช้ name="menu_time[]" เป็นตัวเก็บวันที่/เวลา
                    $m_set = intval($_POST['menu_set_id'][$k] ?? 0);
                    $m_qty = $_POST['menu_qty'][$k] ?? ''; // รับเป็น string หรือ int ตามโครงสร้างตาราง
                    $m_price = floatval($_POST['menu_price'][$k] ?? 0);

                    $stmt_m->bind_param("isisds", $last_id, $m_date, $m_set, $detail, $m_qty, $m_price);
                    $stmt_m->execute();
                }
            }
        }

        $conn->commit();
        $_SESSION['flash_msg'] = "success";
        header("Location: manage_banquet.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_msg'] = "error";
        header("Location: manage_banquet.php");
        exit();
    }
}
?>