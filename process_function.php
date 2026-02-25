<?php
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

    // --- 2. จัดการเรื่องการอัปโหลดรูปภาพ Backdrop ---
    $backdrop_img_path = "";
    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $ext = pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        $filename = "backdrop_" . time() . "." . $ext;
        $target = "uploads/" . $filename; // อย่าลืมสร้างโฟลเดอร์ uploads
        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], $target)) {
            $backdrop_img_path = $target;
        }
    }

    // --- 3. บันทึกข้อมูลลงตารางหลัก (functions) ---
    $sql_main = "INSERT INTO functions (
        function_code, company_id, function_name, booking_name, organization, 
        phone, room_name, booking_room, deposit, banquet_style, 
        equipment, remark, main_kitchen_remark, backdrop_detail, 
        hk_florist_detail, backdrop_img
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_main);
    $stmt->bind_param(
        "sissssssdsssssss",
        $function_code,
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
        $backdrop_img_path
    );

    if ($stmt->execute()) {
        $last_id = $conn->insert_id; // ดึง ID ล่าสุดที่เพิ่งบันทึก

        // --- 4. บันทึกข้อมูลตาราง Schedule (Array Loop) ---
        if (!empty($_POST['schedule_date'])) {
            foreach ($_POST['schedule_date'] as $key => $val) {
                if (!empty($val)) {
                    // แก้ไขชื่อ Column ตรงนี้ให้ตรงกับ Database (ตามไฟล์ SQL)
                    $sql_sub = "INSERT INTO function_schedules (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee) VALUES (?, ?, ?, ?, ?)";
                    $stmt_sub = $conn->prepare($sql_sub);

                    // ตรวจสอบให้แน่ใจว่าค่าที่ดึงมาจาก $_POST ตรงกับชื่อใน HTML form
                    $s_hour = $_POST['schedule_hour'][$key];
                    $s_func = $_POST['schedule_function'][$key];
                    $s_guar = $_POST['schedule_guarantee'][$key];

                    $stmt_sub->bind_param("issss", $last_id, $val, $s_hour, $s_func, $s_guar);
                    $stmt_sub->execute();
                }
            }
        }

        // --- 5. บันทึกข้อมูลตาราง Kitchen (Array Loop) ---
        if (!empty($_POST['k_type'])) {
            foreach ($_POST['k_type'] as $key => $val) {
                if (!empty($val)) {
                    $sql_k = "INSERT INTO function_kitchens (function_id, k_type, k_item, k_qty, k_remark) VALUES (?, ?, ?, ?, ?)";
                    $stmt_k = $conn->prepare($sql_k);
                    $stmt_k->bind_param("issis", $last_id, $val, $_POST['k_item'][$key], $_POST['k_qty'][$key], $_POST['k_remark'][$key]);
                    $stmt_k->execute();
                }
            }
        }

        // --- 6. บันทึกข้อมูลตาราง Menu F&B (Array Loop) ---
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

        echo "<script>alert('บันทึกข้อมูลเรียบร้อยแล้ว!'); window.location='manage_banquet.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>