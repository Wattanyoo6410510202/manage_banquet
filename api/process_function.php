<?php
include "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['save'])) {
    $current_role = strtolower($_SESSION['role'] ?? '');
    if ($current_role === 'viewer') {
        echo "<script>
                alert('ขออภัย! คุณมีสิทธิ์เข้าชมอย่างเดียว (Viewer) ไม่สามารถบันทึกข้อมูลได้');
                window.location.href = 'access_denied.php'; 
              </script>";
        exit;
    }

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

    $total_amount = floatval($_POST['total_amount'] ?? 0);

    $banquet_style = $_POST['banquet_style'] ?? '';
    $equipment = $_POST['equipment'] ?? '';
    $remark = $_POST['remark'] ?? '';
    $main_kitchen_remark = $_POST['main_kitchen_remark'] ?? '';
    $backdrop_detail = $_POST['backdrop_detail'] ?? '';
    $hk_florist_detail = $_POST['hk_florist_detail'] ?? '';

    $created_by_name = $_SESSION['user_name'] ?? 'Unknown';
    $created_by_id = $_SESSION['user_id'] ?? 0;

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

    // --- 2.1 จัดการไฟล์แนบเพิ่มเติม 3 ไฟล์ (สร้างโฟลเดอร์ถ้ามีการอัปโหลด) ---
    $attach_paths = [1 => null, 2 => null, 3 => null];
    $has_upload = false;

    // เช็คก่อนว่าใน 3 ช่องนี้ มีอันไหนอัปโหลดมาบ้าง
    for ($i = 1; $i <= 3; $i++) {
        if (isset($_FILES["file_attachment$i"]) && $_FILES["file_attachment$i"]['error'] == 0) {
            $has_upload = true;
            break;
        }
    }

    if ($has_upload) {
        // สร้างชื่อโฟลเดอร์ตามวันที่และเวลาเพื่อให้ไม่ซ้ำกัน (หรือตามชื่อลูกค้าก็ได้ครับจาร)
        $sub_folder = "uploads/attach_" . date('Ymd_His');
        if (!is_dir($sub_folder)) {
            mkdir($sub_folder, 0777, true); // สร้างโฟลเดอร์หลักและซับโฟลเดอร์ถ้ายังไม่มี
        }

        for ($i = 1; $i <= 3; $i++) {
            $file_field = "file_attachment" . $i;
            if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] == 0) {
                $ext = pathinfo($_FILES[$file_field]['name'], PATHINFO_EXTENSION);
                // ตั้งชื่อไฟล์ให้ดูง่ายขึ้น
                $new_name = "file_" . $i . "_" . uniqid() . "." . $ext;
                $target_attach = $sub_folder . "/" . $new_name;

                if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $target_attach)) {
                    $attach_paths[$i] = $target_attach;
                }
            }
        }
    }

    $conn->begin_transaction();

    try {
        // --- 3. แก้ไข SQL INSERT (เพิ่ม customer_id, function_type_id) ---
        // --- 3. แก้ไข SQL INSERT (ตรวจสอบจำนวน Column และ ? ให้เท่ากันคือ 21 ตัว) ---
        // --- 3. แก้ไข SQL INSERT (ตรวจสอบลำดับให้ตรงกับ bind_param) ---
        $sql_main = "INSERT INTO functions (
            company_id, customer_id, function_type_id, room_id, function_name, 
            booking_name, organization, phone, booking_room, deposit, 
            total_amount, -- เพิ่มตรงนี้
            banquet_style, equipment, remark, main_kitchen_remark, 
            backdrop_detail, hk_florist_detail, backdrop_img, created_by, created_by_id, pax, 
            start_time, end_time, file_attachment1, file_attachment2, file_attachment3
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql_main);

        // นับใหม่: i(4) s(5) d(1) s(7) i(1) i(1) s(2) s(3) 
// รวมทั้งหมดต้องมี 25 ตัว: iiiisssssdssssssss i i sssss
        $types = "iiiisssssddssssssssiisssss"; // <--- อันนี้มี 25 ตัวแล้วครับ

        $stmt->bind_param(
            $types,
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
            $total_amount,       // 11 (d) **เพิ่มใหม่ตรงนี้**
            $banquet_style,      // 12 (s)
            $equipment,          // 13 (s)
            $remark,             // 14 (s)
            $main_kitchen_remark,// 15 (s)
            $backdrop_detail,    // 16 (s)
            $hk_florist_detail,  // 17 (s)
            $backdrop_img_path,  // 18 (s)
            $created_by_name,    // 19 (s)
            $created_by_id,      // 20 (i)
            $pax,                // 21 (i)
            $start_date,         // 22 (s)
            $end_date,           // 23 (s)
            $attach_paths[1],    // 24 (s)
            $attach_paths[2],    // 25 (s)
            $attach_paths[3]     // 26 (s)
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

        // หยุดการทำงานและแสดง Error ทั้งหมดออกมา
        echo "<h1 style='color:red;'>เกิดข้อผิดพลาดในการบันทึก!</h1>";
        echo "<p><b>ข้อความจากระบบ:</b> " . $e->getMessage() . "</p>";
        echo "<hr>";
        echo "<pre>";
        print_r($_POST); // ดูว่าหน้าบ้านส่งค่าอะไรมาบ้าง
        echo "</pre>";
        exit(); // หยุดการ Redirect เพื่อให้อ่าน Error ทัน
    }
}
?>