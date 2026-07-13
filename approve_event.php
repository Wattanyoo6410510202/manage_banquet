<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include "config.php";
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'Confirmed';
    $cancel_reason = $_POST['cancel_reason'] ?? '';
    $user_id = intval($_SESSION['user_id']);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
        exit();
    }

    $approve_val = ($status === 'Cancelled') ? 2 : 1;

    // --- Log (optional — ถ้า table ไม่มีก็ข้าม) ---
    $old_status = '';
    $stmt_old = @$conn->prepare("SELECT status FROM functions WHERE id = ?");
    if ($stmt_old) {
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_res = $stmt_old->get_result();
        if ($old_row = $old_res->fetch_assoc()) {
            $old_status = $old_row['status'] ?? '';
        }
        $stmt_old->close();
    }

    $log_stmt = @$conn->prepare("INSERT INTO function_status_log (function_id, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
    if ($log_stmt) {
        $log_stmt->bind_param("issi", $id, $old_status, $status, $user_id);
        $log_stmt->execute();
        $log_stmt->close();
    }
    // --- End log ---

    $sql = "UPDATE functions SET 
                approve = ?,
                status = ?,
                modify = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("isi", $approve_val, $status, $id);

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        exit();
    }

    $stmt->close();

    // LINE แจ้งเตือนหลัง GM อนุมัติงาน
    $lineSent = 0;
    $lineFailed = 0;
    if ($status === 'Confirmed') {
        include_once __DIR__ . "/line_helper.php";
        $detail = $conn->query("SELECT function_name, booking_name, phone, pax, deposit, total_amount, function_code, start_time, end_time, booking_room, banquet_style, equipment, remark, backdrop_detail, hk_florist_detail FROM functions WHERE id = $id")->fetch_assoc();
        if ($detail) {
            $approve_name = $_SESSION['user_name'] ?? 'GM';
            $baseInfo = "📌 ชื่องาน: {$detail['function_name']}\n"
                . "👤 ผู้จอง: {$detail['booking_name']}\n"
                . "📞 โทร: {$detail['phone']}\n"
                . "💰 ยอดขาย: " . number_format($detail['total_amount'], 2) . " บาท\n"
                . "🏠 ห้อง: {$detail['booking_room']}\n"
                . "📅 วันที่เริ่ม: " . date('d/m/Y H:i', strtotime($detail['start_time'])) . "\n"
                . "📅 วันที่สิ้นสุด: " . date('d/m/Y H:i', strtotime($detail['end_time'])) . "\n"
                . "👨‍💼 อนุมัติโดย: {$approve_name}\n"
                . "━━━━━━━━━━━━━━━━\n"
                . "🆔 รหัสงาน: {$detail['function_code']}\n"
                . "🔗 " . $_SERVER['HTTP_ORIGIN'] . "/manage_banquet/manage_banquet.php";

            // ดึง checklist ตาม role
            function getChecklist($conn, $table) {
                $items = [];
                $q = $conn->query("SELECT task_detail FROM $table ORDER BY id ASC");
                while ($row = $q->fetch_assoc()) {
                    $items[] = $row['task_detail'];
                }
                return $items;
            }

            function buildChecklist($tasks) {
                $text = "";
                if (!empty($tasks)) {
                    foreach ($tasks as $i => $t) {
                        $text .= ($i + 1) . ". " . $t . "\n";
                    }
                }
                return $text;
            }

            // Section info สำหรับแต่ละ role
            $banquetSetup = trim($detail['banquet_style'] ?? '');
            $techInfo = trim($detail['equipment'] ?? '');
            $techRemark = trim($detail['remark'] ?? '');
            $hkBackdrop = trim($detail['backdrop_detail'] ?? '');
            $hkFlorist = trim($detail['hk_florist_detail'] ?? '');

            // ===== จัดเลี้ยง (banquet_staff) =====
            $bkChecklist = buildChecklist(getChecklist($conn, 'master_checklist_bk'));
            $bkSection = "";
            if ($banquetSetup) $bkSection .= "🎨 รูปแบบการจัดงาน (SET-UP):\n{$banquetSetup}\n\n";
            if ($bkChecklist) $bkSection .= "✅ สิ่งที่ต้องทำ:\n{$bkChecklist}";
            $roleMessages['banquet_staff'] = "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}"
                . ($bkSection ? "\n\n🍽️ จัดเลี้ยง:\n{$bkSection}" : "");

            // ===== ช่าง (technician) =====
            $mtChecklist = buildChecklist(getChecklist($conn, 'master_checklist_mt'));
            $mtSection = "";
            if ($techInfo) $mtSection .= "🔧 งานช่างและภาพเสียง:\n{$techInfo}\n\n";
            if ($techRemark) $mtSection .= "📝 หมายเหตุ:\n{$techRemark}\n\n";
            if ($mtChecklist) $mtSection .= "✅ สิ่งที่ต้องทำ:\n{$mtChecklist}";
            $roleMessages['technician'] = "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}"
                . ($mtSection ? "\n\n⚙️ วิศวกรรม (TECHNICAL):\n{$mtSection}" : "");

            // ===== แม่บ้าน (housekeeping) =====
            $hkChecklist = buildChecklist(getChecklist($conn, 'master_checklist_hk'));
            $hkSection = "";
            if ($hkBackdrop) $hkSection .= "🖼️ ฉากหลังและป้าย:\n{$hkBackdrop}\n\n";
            if ($hkFlorist) $hkSection .= "💐 พนักงานทำความสะอาด/จัดดอกไม้:\n{$hkFlorist}\n\n";
            if ($hkChecklist) $hkSection .= "✅ สิ่งที่ต้องทำ:\n{$hkChecklist}";
            $roleMessages['housekeeping'] = "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}"
                . ($hkSection ? "\n\n🧹 การตกแต่งและทำความสะอาด:\n{$hkSection}" : "");

            // ===== admin / procurement =====
            $roleMessages['admin'] = "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}";
            $roleMessages['procurement'] = "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}";

            $allRoles = ['admin', 'housekeeping', 'technician', 'banquet_staff', 'procurement'];
            foreach ($allRoles as $role) {
                $msg = $roleMessages[$role] ?? "✅ งานได้รับการอนุมัติ / Approved\n{$baseInfo}";
                $r = sendLineNotifyToRole($conn, $role, $msg);
                $lineSent += $r['sent'];
                $lineFailed += $r['failed'];
            }
        }

        // อัปเดต is_approved ของ project
        $stmt_pr = @$conn->prepare("SELECT project_id FROM functions WHERE id = ?");
        if ($stmt_pr) {
            $stmt_pr->bind_param("i", $id);
            $stmt_pr->execute();
            $p_res = $stmt_pr->get_result();
            if ($p_row = $p_res->fetch_assoc()) {
                $project_id = $p_row['project_id'] ?? 0;
                if ($project_id) {
                    $upd1 = @$conn->prepare("UPDATE functions SET is_approved = 0 WHERE project_id = ?");
                    if ($upd1) { $upd1->bind_param("i", $project_id); $upd1->execute(); $upd1->close(); }
                    $upd2 = @$conn->prepare("UPDATE functions SET is_approved = 1 WHERE id = ?");
                    if ($upd2) { $upd2->bind_param("i", $id); $upd2->execute(); $upd2->close(); }
                    $upd3 = @$conn->prepare("UPDATE event_projects SET status = 'Approved' WHERE id = ?");
                    if ($upd3) { $upd3->bind_param("i", $project_id); $upd3->execute(); $upd3->close(); }
                }
            }
            $stmt_pr->close();
        }
        $_SESSION['flash_msg'] = "approved";
    } elseif ($status === 'In Progress') {
        $_SESSION['flash_msg'] = "in_progress";
    } elseif ($status === 'Completed') {
        $_SESSION['flash_msg'] = "completed";
    } elseif ($status === 'Cancelled') {
        if (!empty($cancel_reason)) {
            $stmt_cancel = @$conn->prepare("UPDATE functions SET cancel_reason = ? WHERE id = ?");
            if ($stmt_cancel) {
                $stmt_cancel->bind_param("si", $cancel_reason, $id);
                $stmt_cancel->execute();
                $stmt_cancel->close();
            }
        }
        $_SESSION['flash_msg'] = "cancelled";
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย', 'line_sent' => $lineSent, 'line_failed' => $lineFailed]);
    exit();
}

$conn->close();
