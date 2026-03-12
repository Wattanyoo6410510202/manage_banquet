<?php
include "config.php";

// เริ่ม session เพื่อดึงค่าชื่อจริง (name) ของผู้ที่ล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่ามีการกดปุ่ม save หรือไม่
if (isset($_POST['save'])) {

    // --- 1. รับค่าข้อมูลทั่วไป ---
    $function_code = $_POST['function_code'];
    $company_id = $_POST['company_id'];
    $function_name = $_POST['function_name'];
    $booking_name = $_POST['booking_name'];
    $organization = $_POST['organization'];
    $phone = $_POST['phone'];
    $room_name = $_POST['room_name'];
    $booking_room = $_POST['booking_room'];
    $deposit = $_POST['deposit'];
    $banquet_style = $_POST['banquet_style'];
    $equipment = $_POST['equipment'];
    $remark = $_POST['remark'];
    $main_kitchen_remark = $_POST['main_kitchen_remark'];
    $backdrop_detail = $_POST['backdrop_detail'];
    $hk_florist_detail = $_POST['hk_florist_detail'];

    // ดึงค่า "ชื่อจริง" (name) จาก Session ที่เราเก็บไว้ตอน Login
    // (นางสาว ดวงพร โชคชัย)
    $created_by_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';

    // --- 2. จัดการเรื่องการอัปโหลดรูปภาพ Backdrop ---
    $backdrop_img_path = "";

    // Step 2.1: เช็คว่ามีการอัปโหลดไฟล์จริงจากเครื่องไหม
    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $ext = pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        $filename = "backdrop_" . time() . "." . $ext;
        $target = "uploads/" . $filename;

        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], $target)) {
            $backdrop_img_path = $target; // ได้ค่าเป็น uploads/xxx.png
        }
    }
    // Step 2.2: ถ้าไม่มีไฟล์อัปโหลด ให้เช็คว่ามี URL จาก AI ส่งมาไหม (จากช่อง hidden ที่เราเพิ่ม)
    else if (!empty($_POST['backdrop_img_path_ai'])) {
        $backdrop_img_path = $_POST['backdrop_img_path_ai']; // ได้ค่าเป็น https://picsum.photos/...
    }

    // --- 3. บันทึกข้อมูลลงตารางหลัก (functions) ---
    $sql_main = "INSERT INTO functions (
    company_id, function_name, booking_name, organization, 
    phone, room_name, booking_room, deposit, banquet_style, 
    equipment, remark, main_kitchen_remark, backdrop_detail, 
    hk_florist_detail, backdrop_img, created_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_main);

    // bind_param: ตัด s ตัวแรกออก (เพราะไม่มี function_code แล้ว) เหลือ 16 ตัวแปร
    $stmt->bind_param(
        "issssssdssssssss",
        $company_id,
        $function_name,
        $booking_name,
        $organization,
        $phone,
        $room_name,
        $booking_room,
        $deposit,
        $banquet_style,
        $equipment,
        $remark,
        $main_kitchen_remark,
        $backdrop_detail,
        $hk_florist_detail,
        $backdrop_img_path,
        $created_by_name
    );

    // --- เริ่มต้นการบันทึกข้อมูล (แก้ไขจากจุดที่จารส่งมา) ---
    if ($stmt->execute()) {
        $last_id = $conn->insert_id;
    // --- 🚀 เพิ่มตรงนี้: สร้างเลข 00064/2702 แล้ว UPDATE กลับทันที ---
    $final_code = str_pad($last_id, 5, '0', STR_PAD_LEFT) . "/" . date('dm');
    $conn->query("UPDATE functions SET function_code = '$final_code' WHERE id = $last_id");
    // -------------------------------------------------------

    $conn->begin_transaction();
        $conn->begin_transaction();

        try {
            // --- 4. บันทึกข้อมูลตาราง Schedule (Array Loop) ---
            if (!empty($_POST['schedule_date'])) {
                foreach ($_POST['schedule_date'] as $key => $val) {
                    if (!empty($val)) {
                        $sql_sub = "INSERT INTO function_schedules (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee) VALUES (?, ?, ?, ?, ?)";
                        $stmt_sub = $conn->prepare($sql_sub);
                        $stmt_sub->bind_param("issss", $last_id, $val, $_POST['schedule_hour'][$key], $_POST['schedule_function'][$key], $_POST['schedule_guarantee'][$key]);
                        $stmt_sub->execute();
                    }
                }
            }

            // --- 5. บันทึกข้อมูลตาราง Kitchen ---
            // --- 5. บันทึกข้อมูลตาราง Kitchen (เพิ่ม k_date) ---
            if (!empty($_POST['k_type'])) {
                foreach ($_POST['k_type'] as $key => $val) {
                    if (!empty($val)) {
                        // เพิ่ม k_date เข้าไปใน SQL
                        $sql_k = "INSERT INTO function_kitchens (function_id, k_date, k_type, k_item, k_qty, k_remark) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt_k = $conn->prepare($sql_k);

                        // รับค่าจาก Form
                        $k_date = $_POST['k_date'][$key];
                        $k_item = $_POST['k_item'][$key];
                        $k_qty = $_POST['k_qty'][$key];
                        $k_remark = $_POST['k_remark'][$key];

                        // bind_param: i (id), s (date), s (type), s (item), i (qty), s (remark)
                        $stmt_k->bind_param("isssis", $last_id, $k_date, $val, $k_item, $k_qty, $k_remark);
                        $stmt_k->execute();
                    }
                }
            }

            // --- 6. บันทึกข้อมูลตาราง Menu ---
            if (!empty($_POST['menu_name'])) {
                foreach ($_POST['menu_name'] as $key => $val) {
                    if (!empty($val)) {
                        $sql_m = "INSERT INTO function_menus (function_id, menu_time, menu_name, menu_set, menu_detail, menu_qty, menu_price) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt_m = $conn->prepare($sql_m);
                        $stmt_m->bind_param("issssss", $last_id, $_POST['menu_time'][$key], $val, $_POST['menu_set'][$key], $_POST['menu_detail'][$key], $_POST['menu_qty'][$key], $_POST['menu_price'][$key]);
                        $stmt_m->execute();
                    }
                }
            }

            // ถ้าทุกอย่างโอเค ยืนยันการบันทึกทั้งหมด
            $conn->commit();

            // ส่งค่าแจ้งเตือนสำเร็จ
            $_SESSION['flash_msg'] = "success";
            header("Location: manage_banquet.php");
            exit();

        } catch (Exception $e) {
            // ถ้ามีอะไรพลาด ยกเลิกที่บันทึกไปทั้งหมด (Rollback)
            $conn->rollback();
            $_SESSION['flash_msg'] = "error";
            header("Location: manage_banquet.php");
            exit();
        }

    } else {
        // กรณี Error ตั้งแต่ Insert function หลัก
        $_SESSION['flash_msg'] = "error";
        header("Location: manage_banquet.php");
        exit();
    }
}
?>