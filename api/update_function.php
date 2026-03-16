<?php
ob_start();
session_start();
include "../config.php";

if (isset($_POST['update'])) {
    $function_id = intval($_POST['function_id']);

    $company_id = intval($_POST['company_id']);
    $customer_id = intval($_POST['customer_id']);       // 👈 มาแล้วจาร!
    $function_type_id = intval($_POST['function_type_id']);  // 👈 มาแล้วจาร!
    $room_id = intval($_POST['room_id']);           // 👈 มาแล้วจาร!

    $function_name = $_POST['function_name'];
    $booking_name = $_POST['booking_name'];
    $organization = $_POST['organization'];
    $phone = $_POST['phone'];
    $booking_room = $_POST['booking_room'];
    $deposit = floatval($_POST['deposit']);
    $pax = intval($_POST['pax'] ?? 0);
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    $banquet_style = $_POST['banquet_style'];
    $equipment = $_POST['equipment'];
    $remark = $_POST['remark'];
    $main_kitchen_remark = $_POST['main_kitchen_remark'];
    $backdrop_detail = $_POST['backdrop_detail'];
    $hk_florist_detail = $_POST['hk_florist_detail'];

    // --- 2. จัดการรูปภาพ ---
    $backdrop_img_path = $_POST['old_backdrop_img'];
    if (isset($_FILES['backdrop_img']) && $_FILES['backdrop_img']['error'] == 0) {
        $ext = pathinfo($_FILES['backdrop_img']['name'], PATHINFO_EXTENSION);
        $target = "uploads/backdrop_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['backdrop_img']['tmp_name'], "../" . $target)) {
            if (!empty($_POST['old_backdrop_img']) && file_exists("../" . $_POST['old_backdrop_img'])) {
                unlink("../" . $_POST['old_backdrop_img']);
            }
            $backdrop_img_path = $target;
        }
    }

    // --- 3. อัปเดตตารางหลัก (เพิ่มคอลัมน์ให้ครบ) ---
    $sql_update = "UPDATE functions SET 
         company_id=?, customer_id=?, function_type_id=?, room_id=?, 
        function_name=?, booking_name=?, organization=?, phone=?, booking_room=?, 
        deposit=?, banquet_style=?, equipment=?, remark=?, main_kitchen_remark=?, 
        backdrop_detail=?, hk_florist_detail=?, backdrop_img=?, pax=?, start_time=?, end_time=?
        WHERE id=?";

    $stmt = $conn->prepare($sql_update);

    // Bind Param ทั้งหมด 22 ตัว (i=int, s=string, d=double)
    // i i i i i (5) | s s s s s (5) | d (1) | s s s s s s s (7) | i (1) | s s (2) | i (1 - WHERE)
    // รวม 22 ตัวอักษร: iiiii sssss d sssssss i ss i
    $stmt->bind_param(
        "iiiisssssdsssssssissi",
        $company_id,
        $customer_id,
        $function_type_id,
        $room_id,
        $function_name,
        $booking_name,
        $organization,
        $phone,
        $booking_room,
        $deposit,
        $banquet_style,
        $equipment,
        $remark,
        $main_kitchen_remark,
        $backdrop_detail,
        $hk_florist_detail,
        $backdrop_img_path,
        $pax,
        $start_time,
        $end_time,
        $function_id
    );

    if ($stmt->execute()) {
        $conn->begin_transaction();
        try {
            // --- 4. ล้างและลงข้อมูลตารางลูกใหม่ (Kitchen / Menu / Schedule) ---
            $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
            $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

            // --- 5. Re-Insert Kitchen (ใช้ k_type_id ตามที่จารแก้) ---
            if (!empty($_POST['k_item'])) {
                $stmt_k = $conn->prepare("INSERT INTO function_kitchens (function_id, k_date, k_type_id, k_item, k_qty, k_remark) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($_POST['k_item'] as $key => $item) {
                    if (trim($item) != "") {
                        $k_type_id = intval($_POST['k_type_id'][$key] ?? 0);
                        $stmt_k->bind_param("isisis", $function_id, $_POST['k_date'][$key], $k_type_id, $item, $_POST['k_qty'][$key], $_POST['k_remark'][$key]);
                        $stmt_k->execute();
                    }
                }
            }

            // --- 6. Re-Insert Menu (ใช้ menu_set_id และ menu_detail) ---
            if (!empty($_POST['menu_detail'])) {
                $stmt_m = $conn->prepare("INSERT INTO function_menus (function_id, menu_time, menu_set_id, menu_detail, menu_qty, menu_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($_POST['menu_detail'] as $key => $detail) {
                    if (trim($detail) != "") {
                        $m_set_id = intval($_POST['menu_set_id'][$key] ?? 0);
                        $stmt_m->bind_param("isisds", $function_id, $_POST['menu_time'][$key], $m_set_id, $detail, $_POST['menu_qty'][$key], $_POST['menu_price'][$key]);
                        $stmt_m->execute();
                    }
                }
            }

            $conn->commit();
            $_SESSION['flash_msg'] = "update_success";
            header("Location: ../manage_banquet.php");
        } catch (Exception $e) {
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    }
}
?>