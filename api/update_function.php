<?php
ob_start();
session_start();
include "../config.php";

if (isset($_POST['update'])) {
    $function_id = intval($_POST['function_id'] ?? 0);

    $company_id = intval($_POST['company_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $function_type_id = intval($_POST['function_type_id'] ?? 0);
    $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;

    $function_name = $_POST['function_name'];
    $draft_name = $_POST['draft_name'] ?? 'Draft'; // [NEW]
    $booking_name = $_POST['booking_name'];
    $organization = $_POST['organization'];
    $phone = $_POST['phone'];
    $booking_room = $_POST['booking_room'];
    $deposit = floatval($_POST['deposit']);
    $total_amount = floatval($_POST['total_amount'] ?? 0); // 👈 เพิ่มตัวนี้เข้าไปครับ
    $pax = intval($_POST['pax'] ?? 0);
    $start_time = !empty($_POST['start_time']) ? str_replace('T', ' ', $_POST['start_time']) : null;
    $end_time = !empty($_POST['end_time']) ? str_replace('T', ' ', $_POST['end_time']) : null;

    $banquet_style = $_POST['banquet_style'];
    $equipment = $_POST['equipment'];
    $remark = $_POST['remark'];
    $main_kitchen_remark = $_POST['main_kitchen_remark'];
    $backdrop_detail = $_POST['backdrop_detail'];
    $hk_florist_detail = $_POST['hk_florist_detail'];
    $lead_source = $_POST['lead_source'] ?? '';
    $result = $_POST['result'] ?? '';
    $inspection_date = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : null;
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

    // --- ตรวจสอบการชนกันของวันเวลา (ไม่นับตัวเอง) ---
    if ($room_id && $start_time && $end_time) {
        $check_sql = "SELECT id, function_name FROM functions 
                      WHERE room_id = ? 
                      AND status != 'Cancelled'
                      AND approve = 1
                      AND id != ?
                      AND (start_time <= ? AND end_time >= ?)";
        $stmt_check = $conn->prepare($check_sql);
        if ($stmt_check) {
            $stmt_check->bind_param("iiss", $room_id, $function_id, $end_time, $start_time);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            if ($res_check->num_rows > 0) {
                echo "<script>
                        alert('ขออภัย! ห้องนี้มีรายการอื่นในช่วงเวลาที่เลือก กรุณาเลือกเวลาหรือห้องอื่น');
                        window.history.back();
                      </script>";
                exit;
            }
        }
    }

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

    //// --- 2. เตรียมชื่อโฟลเดอร์ใหม่ (สร้างตามวันเวลาและรหัสสุ่ม) ---
    $new_folder_name = "attach_" . date("Ymd_His") . "_" . uniqid();
    $target_dir = "uploads/" . $new_folder_name;
    $folder_created = false; // ตัวแปรเช็คว่าสร้างโฟลเดอร์ไปหรือยัง

    $file_attachment_paths = [];

    for ($i = 1; $i <= 3; $i++) {
        $field_name = "file_attachment" . $i;
        $old_path = $_POST["old_file_$i"] ?? '';
        $delete_flag = $_POST["delete_file_$i"] ?? '0';

        $current_path = $old_path;

        // 1. กรณีสั่งลบไฟล์เดิม (กดปุ่มลบ หรือ จะอัปโหลดใหม่ทับ)
        if ($delete_flag == '1' || (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0)) {
            if (!empty($old_path) && file_exists("../" . $old_path)) {
                unlink("../" . $old_path);

                // 🔥 จาร! ถ้าลบไฟล์เสร็จแล้ว โฟลเดอร์เดิมมันว่าง ก็ลบทิ้งไปเลย (ความสะอาดขั้นสุด)
                $old_dir = dirname("../" . $old_path);
                if (is_dir($old_dir) && count(glob("$old_dir/*")) === 0) {
                    @rmdir($old_dir);
                }
            }
            $current_path = null;
        }

        // 2. กรณีอัปโหลดไฟล์ใหม่
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0) {
            // สร้างโฟลเดอร์ใหม่แค่ครั้งเดียวต่อการรัน Loop นี้
            if (!$folder_created) {
                if (!file_exists("../" . $target_dir)) {
                    mkdir("../" . $target_dir, 0777, true);
                }
                $folder_created = true;
            }

            $ext = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
            $new_filename = "file_" . $i . "_" . uniqid() . "." . $ext;
            $target_file = $target_dir . "/" . $new_filename;

            if (move_uploaded_file($_FILES[$field_name]['tmp_name'], "../" . $target_file)) {
                $current_path = $target_file;
            }
        }
        $file_attachment_paths[$i] = $current_path;
    }

    // --- 3. อัปเดตตารางหลัก ---
    $sql_update = "UPDATE functions SET 
    company_id=?, customer_id=?, function_type_id=?, room_id=?, 
    function_name=?, draft_name=?, booking_name=?, organization=?, phone=?, booking_room=?, 
    deposit=?, total_amount=?,
    banquet_style=?, equipment=?, remark=?, main_kitchen_remark=?, 
    backdrop_detail=?, hk_florist_detail=?, backdrop_img=?, pax=?, start_time=?, end_time=?,
    lead_source=?, result=?, inspection_date=?, follow_up_date=?,
    file_attachment1=?, file_attachment2=?, file_attachment3=? 
    WHERE id=?";

    $stmt = $conn->prepare($sql_update);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $types = "iiiissssssddsssssssisssssssssi"; // 30 chars

    $stmt->bind_param(
        $types,
        $company_id,           // 1
        $customer_id,          // 2
        $function_type_id,     // 3
        $room_id,              // 4
        $function_name,        // 5
        $draft_name,           // 6
        $booking_name,         // 7
        $organization,         // 8
        $phone,                // 9
        $booking_room,         // 10
        $deposit,              // 11
        $total_amount,         // 12
        $banquet_style,        // 13
        $equipment,            // 14
        $remark,               // 15
        $main_kitchen_remark,  // 16
        $backdrop_detail,      // 17
        $hk_florist_detail,    // 18
        $backdrop_img_path,    // 19
        $pax,                  // 20
        $start_time,           // 21
        $end_time,             // 22
        $lead_source,          // 23
        $result,               // 24
        $inspection_date,      // 25
        $follow_up_date,       // 26
        $file_attachment_paths[1], // 27
        $file_attachment_paths[2], // 28
        $file_attachment_paths[3], // 29
        $function_id           // 30
    );

    $conn->begin_transaction();
    try {
        if (!$stmt->execute()) {
            throw new Exception("Update main table failed: " . $stmt->error);
        }

        // --- 4. ล้างและลงข้อมูลตารางลูกใหม่ (Kitchen / Menu / Schedule) ---
        $conn->query("DELETE FROM function_schedules WHERE function_id = $function_id");
        $conn->query("DELETE FROM function_kitchens WHERE function_id = $function_id");
        $conn->query("DELETE FROM function_menus WHERE function_id = $function_id");

        // --- 7. Re-Insert Schedule ---
        if (!empty($_POST['schedule_function'])) {
            $stmt_s = $conn->prepare("INSERT INTO function_schedules 
        (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee) 
        VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_s) throw new Exception("Prepare schedule failed: " . $conn->error);

            foreach ($_POST['schedule_function'] as $key => $func) {
                $date = $_POST['schedule_date'][$key] ?? null;
                $hour = $_POST['schedule_hour'][$key] ?? '';
                $guarantee = intval($_POST['schedule_guarantee'][$key] ?? 0);

                if (trim($func) != "" || trim($hour) != "") {
                    $stmt_s->bind_param("isssi", $function_id, $date, $hour, $func, $guarantee);
                    if (!$stmt_s->execute()) throw new Exception("Insert schedule failed: " . $stmt_s->error);
                }
            }
        }

        // --- 5. Re-Insert Kitchen ---
        if (!empty($_POST['k_item'])) {
            $stmt_k = $conn->prepare("INSERT INTO function_kitchens (function_id, k_date, k_type_id, k_item, k_qty, k_price, k_remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_k) throw new Exception("Prepare kitchen failed: " . $conn->error);

            $k_dates = $_POST['k_date'] ?? [];
            $k_qtys = $_POST['k_qty'] ?? [];
            $k_prices = $_POST['k_price'] ?? [];
            $k_remarks = $_POST['k_remark'] ?? [];
            $k_type_ids = $_POST['k_type_id'] ?? [];
            foreach ($_POST['k_item'] as $key => $item) {
                if (trim($item) != "") {
                    $k_date_val = !empty($k_dates[$key]) ? $k_dates[$key] : date('Y-m-d');
                    $k_type_id_val = intval($k_type_ids[$key] ?? 0);
                    $k_qty_val = $k_qtys[$key] ?? 0;
                    $k_price_val = floatval($k_prices[$key] ?? 0);
                    if ($k_price_val == 0 && trim($item) != "") {
                        $total_unit = 0;
                        $k_lines = preg_split('/\r\n|\r|\n/', $item);
                        foreach ($k_lines as $kl) {
                            $k_name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $kl));
                            if (empty($k_name)) continue;
                            $k_esc = $conn->real_escape_string($k_name);
                            $qb = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$k_esc%' LIMIT 1");
                            if ($b = $qb->fetch_assoc()) {
                                $total_unit += (float)$b['break_price'];
                            } else {
                                $qd = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$k_esc%' LIMIT 1");
                                if ($d = $qd->fetch_assoc()) $total_unit += (float)$d['price_per_pax'];
                            }
                        }
                        if ($total_unit > 0) $k_price_val = $total_unit;
                    }
                    $k_remark_val = $k_remarks[$key] ?? '';
                    $stmt_k->bind_param("isisids", $function_id, $k_date_val, $k_type_id_val, $item, $k_qty_val, $k_price_val, $k_remark_val);
                    if (!$stmt_k->execute()) throw new Exception("Insert kitchen failed: " . $stmt_k->error);
                }
            }
        }

        // --- 6. Re-Insert Menu ---
        if (!empty($_POST['menu_detail'])) {
            $stmt_m = $conn->prepare("INSERT INTO function_menus (function_id, menu_time, menu_set_id, menu_detail, menu_qty, menu_price) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_m) throw new Exception("Prepare menu failed: " . $conn->error);

            $menu_times = $_POST['menu_time'] ?? [];
            $menu_qtys = $_POST['menu_qty'] ?? [];
            $menu_prices = $_POST['menu_price'] ?? [];
            $menu_set_ids = $_POST['menu_set_id'] ?? [];
            foreach ($_POST['menu_detail'] as $key => $detail) {
                if (trim($detail) != "") {
                    $menu_time_val = $menu_times[$key] ?? '';
                    $menu_set_id_val = intval($menu_set_ids[$key] ?? 0);
                    $menu_qty_val = $menu_qtys[$key] ?? 0;
                    $menu_price_val = $menu_prices[$key] ?? 0;
                    if ($menu_price_val == 0 && trim($detail) != "") {
                        $total_unit = 0;
                        $m_lines = preg_split('/\r\n|\r|\n/', $detail);
                        foreach ($m_lines as $ml) {
                            $m_name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $ml));
                            if (empty($m_name)) continue;
                            $m_esc = $conn->real_escape_string($m_name);
                            $qm = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$m_esc%' LIMIT 1");
                            if ($p = $qm->fetch_assoc()) $total_unit += (float)$p['price_per_pax'];
                        }
                        if ($total_unit > 0) $menu_price_val = $total_unit;
                    }
                    $stmt_m->bind_param("isisds", $function_id, $menu_time_val, $menu_set_id_val, $detail, $menu_qty_val, $menu_price_val);
                    if (!$stmt_m->execute()) throw new Exception("Insert menu failed: " . $stmt_m->error);
                }
            }
        }

        $conn->commit();
        $_SESSION['flash_msg'] = "update_success";
        header("Location: ../edit.php?id=" . $function_id);
        exit;
    } catch (\Throwable $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}
?>