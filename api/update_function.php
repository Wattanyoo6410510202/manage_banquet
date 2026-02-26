<?php
ob_start();
session_start();
include "../config.php";

if (isset($_POST['update'])) {
    $function_id = intval($_POST['function_id']);

    // --- 🚀 ด่านตรวจความปลอดภัย (Security Check) ---
    $current_user_name = $_SESSION['user_name'] ?? ''; 
    $user_role = $_SESSION['role'] ?? 'viewer'; // default เป็น viewer เพื่อความปลอดภัย

    // 1. ถ้าเป็น viewer ห้ามเข้าถึงการอัปเดตเด็ดขาด
    if ($user_role === 'viewer') {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['msg_text'] = "สิทธิ์ Viewer ดูได้อย่างเดียวครับจาร!";
        header("Location: ../manage_banquet.php");
        exit();
    }

    // 2. ดึงข้อมูลปัจจุบันมาเช็ค "เจ้าของ" และ "สถานะการอนุมัติ"
    $check_sql = "SELECT created_by, approve FROM functions WHERE id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $function_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $current_data = $res_check->fetch_assoc();

    if (!$current_data) {
        die("ไม่พบข้อมูลรายการนี้!");
    }

    // 3. เช็คว่าเป็นเจ้าของงานหรือไม่ (ยกเว้น admin แก้ได้หมด)
    if ($user_role !== 'admin' && trim($current_data['created_by']) !== trim($current_user_name)) {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['msg_text'] = "จารไม่มีสิทธิ์แก้งานของคนอื่นนะ!";
        header("Location: ../manage_banquet.php");
        exit();
    }

    // 4. เช็คสถานะการอนุมัติ (ถ้า Approve แล้ว ห้ามทุกคนแก้ แม้แต่เจ้าของ)
    if ($current_data['approve'] != 0) {
        $_SESSION['flash_msg'] = "error";
        $_SESSION['msg_text'] = "รายการนี้อนุมัติแล้ว ล็อกข้อมูลห้ามแก้ครับ!";
        header("Location: ../manage_banquet.php");
        exit();
    }

    // --- ผ่านทุกด่านแล้ว เริ่มกระบวนการรับค่าข้อมูล (Logic เดิมของจาร) ---
    
    $function_code       = $_POST['function_code'];
    $company_id          = $_POST['company_id'];
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

    // --- จัดการรูปภาพ (ลบของเก่าถ้ามีการอัปโหลดใหม่) ---
    $backdrop_img_path = $_POST['old_backdrop_img']; 

    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $ext = pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        $filename = "backdrop_" . time() . "." . $ext;
        $target = "uploads/" . $filename;

        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }

        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], "../" . $target)) {
            if (!empty($_POST['old_backdrop_img']) && file_exists("../" . $_POST['old_backdrop_img'])) {
                unlink("../" . $_POST['old_backdrop_img']);
            }
            $backdrop_img_path = $target; 
        }
    }

    // --- เริ่มอัปเดตตารางหลัก ---
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
        $conn->begin_transaction();
        try {
            // ล้างข้อมูลตารางลูกและ Insert ใหม่ (Logic เดิมของจาร)
            $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

            // --- ส่วน Re-Insert ข้อมูลตารางลูก (จารยกโค้ดเดิมมาใส่ตรงนี้ได้เลย) ---
            // ... (โค้ด Insert Schedule, Kitchen, Menu) ...

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
    }
}
ob_end_flush();
?>