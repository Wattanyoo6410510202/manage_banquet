<?php
include_once __DIR__ . "/../config.php";

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
    $quotation_id = !empty($_POST['quotation_id']) ? intval($_POST['quotation_id']) : null; // เพิ่มใหม่
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null; // เพิ่มใหม่
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
    $lead_source = $_POST['lead_source'] ?? '';
    $result = $_POST['result'] ?? '';
    $inspection_date = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : null;
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

    $created_by_name = $_SESSION['user_name'] ?? 'Unknown';
    $created_by_id = $_SESSION['user_id'] ?? 0;

    $raw_start = $_POST['start_time'] ?? '';
    $raw_end   = $_POST['end_time'] ?? '';
    $start_date = !empty($raw_start) ? date('Y-m-d H:i:s', strtotime($raw_start)) : null;
    $end_date   = !empty($raw_end)   ? date('Y-m-d H:i:s', strtotime($raw_end))   : null;

    // --- ตรวจสอบการชนกันของวันเวลา ---
    if ($room_id && $start_date && $end_date) {
        $check_sql = "SELECT id, function_name FROM functions 
                      WHERE room_id = ? 
                      AND status != 'Cancelled'
                      AND approve = 1
                      AND (start_time <= ? AND end_time >= ?)";
        
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("iss", $room_id, $end_date, $start_date);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            echo "<script>
                    alert('ขออภัย! ห้องนี้มีรายการที่ได้รับการอนุมัติแล้วในช่วงเวลาที่เลือก กรุณาเลือกเวลาหรือห้องอื่นครับ');
                    window.history.back();
                  </script>";
            exit;
        }
    }

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
        // --- [NEW] 2.5 จัดการ Project ---
        // สร้าง Project ใหม่ทุกครั้ง (ไม่ reuse project_id เดิม)
        $sql_project = "INSERT INTO event_projects (project_name, customer_id, company_id, status, created_by) VALUES (?, ?, ?, 'Pending', ?)";
        $stmt_project = $conn->prepare($sql_project);
        $stmt_project->bind_param("siis", $function_name, $customer_id, $company_id, $created_by_name);
        $stmt_project->execute();
        $project_id = $conn->insert_id;

        // อัปเดต project_id ของใบเสนอราคาให้ตรงกับ function ที่สร้าง
        if ($quotation_id) {
            $stmt_upd_quote = $conn->prepare("UPDATE quotations SET project_id = ? WHERE id = ?");
            $stmt_upd_quote->bind_param("ii", $project_id, $quotation_id);
            $stmt_upd_quote->execute();
        }

        // --- 3. แก้ไข SQL INSERT ---
        $sql_main = "INSERT INTO functions (
            project_id, quotation_id, version_no, is_approved, draft_name,
            company_id, customer_id, function_type_id, room_id, function_name, 
            booking_name, organization, phone, booking_room, deposit, 
            total_amount,
            banquet_style, equipment, remark, main_kitchen_remark, 
            backdrop_detail, hk_florist_detail, backdrop_img, created_by, created_by_id, pax, 
            start_time, end_time,
            lead_source, result, inspection_date, follow_up_date,
            file_attachment1, file_attachment2, file_attachment3
        ) VALUES (?, ?, 1, 0, 'Draft V1', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql_main);

        $types = "iiiiiisssssddssssssssiisssssssss"; // 32 chars: 6i+5s+2d+8s+2i+9s

        $stmt->bind_param(
            $types,
            $project_id,         // 1 (i)
            $quotation_id,       // 2 (i)
            $company_id,         // 3 (i)
            $customer_id,        // 4 (i)
            $function_type_id,   // 5 (i)
            $room_id,            // 6 (i)
            $function_name,      // 7 (s)
            $booking_name,       // 8 (s)
            $organization,       // 9 (s)
            $phone,              // 10 (s)
            $booking_room,       // 11 (s)
            $deposit,            // 12 (d)
            $total_amount,       // 13 (d)
            $banquet_style,      // 14 (s)
            $equipment,          // 15 (s)
            $remark,             // 16 (s)
            $main_kitchen_remark,// 17 (s)
            $backdrop_detail,    // 18 (s)
            $hk_florist_detail,  // 19 (s)
            $backdrop_img_path,  // 20 (s)
            $created_by_name,    // 21 (s)
            $created_by_id,      // 22 (i)
            $pax,                // 23 (i)
            $start_date,         // 24 (s)
            $end_date,           // 25 (s)
            $lead_source,        // 26 (s)
            $result,             // 27 (s)
            $inspection_date,    // 28 (s)
            $follow_up_date,     // 29 (s)
            $attach_paths[1],    // 30 (s)
            $attach_paths[2],    // 31 (s)
            $attach_paths[3]     // 32 (s)
        );

        if (!$stmt->execute()) {
            throw new Exception("บันทึกตารางหลักล้มเหลว: " . $stmt->error);
        }

        $last_id = $conn->insert_id;

        // สร้างเลขรันงาน
        $final_code = str_pad($last_id, 5, '0', STR_PAD_LEFT) . "/" . date('dm');
        $stmt_upd_code = $conn->prepare("UPDATE functions SET function_code = ? WHERE id = ?");
        $stmt_upd_code->bind_param("si", $final_code, $last_id);
        $stmt_upd_code->execute();

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
            $stmt_k = $conn->prepare("INSERT INTO function_kitchens (function_id, k_date, k_type_id, k_item, k_qty, k_price, k_remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['k_item'] as $k => $item) {
                if (trim($item) != "") {
                    $k_date = !empty($_POST['k_date'][$k]) ? $_POST['k_date'][$k] : date('Y-m-d');
                    $k_type = intval($_POST['k_type_id'][$k] ?? 0);
                    $k_qty = intval($_POST['k_qty'][$k] ?? 0);
                    $k_price = floatval($_POST['k_price'][$k] ?? 0);
                    if ($k_price == 0 && trim($item) != "") {
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
                        if ($total_unit > 0) $k_price = $total_unit;
                    }
                    $k_rem = $_POST['k_remark'][$k] ?? '';
                    $stmt_k->bind_param("isisids", $last_id, $k_date, $k_type, $item, $k_qty, $k_price, $k_rem);
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
                    if ($m_price == 0 && trim($detail) != "") {
                        $total_unit = 0;
                        $m_lines = preg_split('/\r\n|\r|\n/', $detail);
                        foreach ($m_lines as $ml) {
                            $m_name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $ml));
                            if (empty($m_name)) continue;
                            $m_esc = $conn->real_escape_string($m_name);
                            $qm = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$m_esc%' LIMIT 1");
                            if ($p = $qm->fetch_assoc()) $total_unit += (float)$p['price_per_pax'];
                        }
                        if ($total_unit > 0) $m_price = $total_unit;
                    }

                    $stmt_m->bind_param("isissd", $last_id, $m_date, $m_set, $detail, $m_qty, $m_price);
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