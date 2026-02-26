<?php
ob_start();
session_start();
include "../config.php";

if (isset($_POST['update'])) {
    $function_id = intval($_POST['function_id']);

    // --- 1. รับค่าข้อมูลทั่วไป ---
    $function_code       = $_POST['function_code'];
    $company_id         = $_POST['company_id'];
    $function_name       = $_POST['function_name'];
    $booking_name        = $_POST['booking_name'];
    $organization        = $_POST['organization'];
    $phone               = $_POST['phone'];
    $room_name           = $_POST['room_name'];
    $booking_room        = $_POST['booking_room'];
    $deposit             = $_POST['deposit'];
    $banquet_style       = $_POST['banquet_style'];
    $equipment           = $_POST['equipment'];
    $remark              = $_POST['remark'];
    $main_kitchen_remark = $_POST['main_kitchen_remark'];
    $backdrop_detail     = $_POST['backdrop_detail'];
    $hk_florist_detail   = $_POST['hk_florist_detail'];

    // --- 2. จัดการรูปภาพ (ลบของเก่าถ้ามีการอัปโหลดใหม่) ---
    $backdrop_img_path = $_POST['old_backdrop_img']; // ค่าเริ่มต้นใช้รูปเดิม

    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $ext = pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        $filename = "backdrop_" . time() . "." . $ext;
        $target = "uploads/" . $filename;

        // สร้างโฟลเดอร์ถ้าไม่มี
        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }

        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], "../" . $target)) {
            // ลบไฟล์รูปเก่าออกจาก Server เพื่อประหยัดพื้นที่
            if (!empty($_POST['old_backdrop_img']) && file_exists("../" . $_POST['old_backdrop_img'])) {
                unlink("../" . $_POST['old_backdrop_img']);
            }
            $backdrop_img_path = $target; 
        }
    }

    // --- 3. อัปเดตตารางหลัก (functions) ---
    $sql_update = "UPDATE functions SET 
        function_code=?, company_id=?, function_name=?, booking_name=?, organization=?, 
        phone=?, room_name=?, booking_room=?, deposit=?, banquet_style=?, 
        equipment=?, remark=?, main_kitchen_remark=?, backdrop_detail=?, 
        hk_florist_detail=?, backdrop_img=? 
        WHERE id=?";

    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sissssssdsssssssi", 
        $function_code, $company_id, $function_name, $booking_name, $organization, 
        $phone, $room_name, $booking_room, $deposit, $banquet_style, 
        $equipment, $remark, $main_kitchen_remark, $backdrop_detail, 
        $hk_florist_detail, $backdrop_img_path, $function_id
    );

    if ($stmt->execute()) {
        // ใช้ Transaction สำหรับข้อมูลตารางลูก
        $conn->begin_transaction();

        try {
            // --- 4. ล้างข้อมูลเก่าในตารางลูก (Clean & Insert ใหม่) ---
            $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

            // --- 5. Re-Insert ตาราง Schedule ---
            if (!empty($_POST['schedule_date'])) {
                $stmt_s = $conn->prepare("INSERT INTO function_schedules (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['schedule_date'] as $key => $val) {
                    if (!empty($val)) {
                        $stmt_s->bind_param("issss", $function_id, $val, $_POST['schedule_hour'][$key], $_POST['schedule_function'][$key], $_POST['schedule_guarantee'][$key]);
                        $stmt_s->execute();
                    }
                }
            }

            // --- 6. Re-Insert ตาราง Kitchen ---
            if (!empty($_POST['k_type'])) {
                $stmt_k = $conn->prepare("INSERT INTO function_kitchens (function_id, k_date, k_type, k_item, k_qty, k_remark) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($_POST['k_type'] as $key => $val) {
                    if (!empty($val)) {
                        $stmt_k->bind_param("isssis", $function_id, $_POST['k_date'][$key], $val, $_POST['k_item'][$key], $_POST['k_qty'][$key], $_POST['k_remark'][$key]);
                        $stmt_k->execute();
                    }
                }
            }

            // --- 7. Re-Insert ตาราง Menu ---
            if (!empty($_POST['menu_name'])) {
                $stmt_m = $conn->prepare("INSERT INTO function_menus (function_id, menu_time, menu_name, menu_set, menu_detail, menu_qty, menu_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($_POST['menu_name'] as $key => $val) {
                    if (!empty($val)) {
                        $stmt_m->bind_param("issssss", $function_id, $_POST['menu_time'][$key], $val, $_POST['menu_set'][$key], $_POST['menu_detail'][$key], $_POST['menu_qty'][$key], $_POST['menu_price'][$key]);
                        $stmt_m->execute();
                    }
                }
            }

            $conn->commit();
            $_SESSION['flash_msg'] = "update_success";
            header("Location: ../manage_banquet.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_msg'] = "error";
            header("Location: ../manage_banquet.php");
            exit();
        }
    } else {
        $_SESSION['flash_msg'] = "error";
        header("Location: ../manage_banquet.php");
        exit();
    }
}
ob_end_flush();
?>