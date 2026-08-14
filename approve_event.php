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

    if ($approve_val === 1) {
        // บันทึกคนอนุมัติ + วันที่อนุมัติ (เก็บค่าเดิมไว้ถ้าเคยอนุมัติแล้ว)
        $sql = "UPDATE functions SET
                    approve = ?,
                    status = ?,
                    approve_by = COALESCE(approve_by, ?),
                    approve_date = COALESCE(approve_date, NOW()),
                    modify = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param("isii", $approve_val, $status, $user_id, $id);
    } else {
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
    }

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        exit();
    }

    $stmt->close();

    // LINE แจ้งเตือนหลัง GM อนุมัติงาน (Flex Message)
    $lineSent = 0;
    $lineFailed = 0;
    if ($status === 'Confirmed') {
        include_once __DIR__ . "/line_helper.php";
        $detail = $conn->query("SELECT function_name, booking_name, phone, pax, deposit, total_amount, function_code, start_time, end_time, booking_room, banquet_style, equipment, remark, backdrop_detail, hk_florist_detail FROM functions WHERE id = $id")->fetch_assoc();
        if ($detail) {
            $approveName = $_SESSION['user_name'] ?? 'GM';
            $origin = $_SERVER['HTTP_ORIGIN'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $calendarUrl = 'https://nas909ssf.myqnapcloud.com:8081/manage_banquet/public_calendar.php';

            function getFlexChecklist($conn, $table) {
                $items = [];
                $q = $conn->query("SELECT task_detail FROM $table ORDER BY id ASC");
                while ($row = $q->fetch_assoc()) { $items[] = $row['task_detail']; }
                return $items;
            }

            // ===== จัดเลี้ยง (banquet_staff) =====
            $bkSections = [];
            if (trim($detail['banquet_style'] ?? '')) {
                $bkSections[] = ['label' => '🎨 รูปแบบการจัดงาน (SET-UP)', 'text' => $detail['banquet_style']];
            }
            $bkFlex = buildApprovedFlex($detail, $approveName, $origin, 'banquet_staff', $bkSections, getFlexChecklist($conn, 'master_checklist_bk'), $calendarUrl);
            $r = sendLineFlexToRole($conn, 'banquet_staff', $bkFlex, '✅ งานได้รับการอนุมัติ - จัดเลี้ยง');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            // ===== ช่าง (technician) =====
            $mtSections = [];
            if (trim($detail['equipment'] ?? '')) {
                $mtSections[] = ['label' => '🔧 งานช่างและภาพเสียง', 'text' => $detail['equipment']];
            }
            if (trim($detail['remark'] ?? '')) {
                $mtSections[] = ['label' => '📝 หมายเหตุ', 'text' => $detail['remark']];
            }
            $mtFlex = buildApprovedFlex($detail, $approveName, $origin, 'technician', $mtSections, getFlexChecklist($conn, 'master_checklist_mt'), $calendarUrl);
            $r = sendLineFlexToRole($conn, 'technician', $mtFlex, '✅ งานได้รับการอนุมัติ - วิศวกรรม');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            // ===== แม่บ้าน (housekeeping) =====
            $hkSections = [];
            if (trim($detail['backdrop_detail'] ?? '')) {
                $hkSections[] = ['label' => '🖼️ ฉากหลังและป้าย', 'text' => $detail['backdrop_detail']];
            }
            if (trim($detail['hk_florist_detail'] ?? '')) {
                $hkSections[] = ['label' => '💐 พนักงานทำความสะอาด/จัดดอกไม้', 'text' => $detail['hk_florist_detail']];
            }
            $hkFlex = buildApprovedFlex($detail, $approveName, $origin, 'housekeeping', $hkSections, getFlexChecklist($conn, 'master_checklist_hk'), $calendarUrl);
            $r = sendLineFlexToRole($conn, 'housekeeping', $hkFlex, '✅ งานได้รับการอนุมัติ - ทำความสะอาด');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            // ===== admin / procurement =====
            $adminFlex = buildApprovedFlex($detail, $approveName, $origin, 'admin', [], [], $calendarUrl);
            $r = sendLineFlexToRole($conn, 'admin', $adminFlex, '✅ งานได้รับการอนุมัติ');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            $procFlex = buildApprovedFlex($detail, $approveName, $origin, 'procurement', [], [], $calendarUrl);
            $r = sendLineFlexToRole($conn, 'procurement', $procFlex, '✅ งานได้รับการอนุมัติ');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            $staffFlex = buildApprovedFlex($detail, $approveName, $origin, 'staff', [], [], $calendarUrl);
            $r = sendLineFlexToRole($conn, 'staff', $staffFlex, '✅ งานได้รับการอนุมัติ');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];

            // GM คนอื่นที่ไม่ได้เป็นคนกดอนุมัติ ก็ต้องรู้ว่างานนี้เปิดแล้ว
            $gmFlex = buildApprovedFlex($detail, $approveName, $origin, 'admin', [], [], $calendarUrl);
            $r = sendLineFlexToRole($conn, 'gm', $gmFlex, '✅ งานได้รับการอนุมัติ');
            $lineSent += $r['sent']; $lineFailed += $r['failed'];
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

        // LINE แจ้งทุกแผนกว่างานถูกยกเลิก จะได้ไม่เตรียมของต่อ
        // ส่งเฉพาะงานที่เคยอนุมัติแล้ว งานที่ยังไม่อนุมัติไม่มีใครเตรียมอยู่แล้ว
        try {
            include_once __DIR__ . "/line_helper.php";
            $c = $conn->query("SELECT function_name, booking_name, phone, booking_room, function_code,
                                      start_time, end_time, cancel_reason, approve
                               FROM functions WHERE id = $id")->fetch_assoc();

            if ($c && $old_status === 'Confirmed') {
                $origin = lineOriginUrl();
                $flex = buildNotifyFlex([
                    'title'    => '❌ งานถูกยกเลิก',
                    'subtitle' => 'Event Cancelled',
                    'color'    => '#D32F2F',
                    'rows'     => [
                        ['📌', $c['function_name'], '#111111'],
                        ['👤', $c['booking_name']],
                        ['🏠', $c['booking_room']],
                        ['📅 เริ่ม', !empty($c['start_time']) ? date('d/m/Y H:i', strtotime($c['start_time'])) : '-'],
                        ['📅 สิ้นสุด', !empty($c['end_time']) ? date('d/m/Y H:i', strtotime($c['end_time'])) : '-'],
                        ['🆔', $c['function_code'], '#111111'],
                        ['📝 เหตุผล', $c['cancel_reason'] ?: '-', '#D32F2F'],
                        ['🙍 ยกเลิกโดย', $_SESSION['user_name'] ?? '-'],
                    ],
                    'note'     => 'หยุดเตรียมงานนี้ได้เลย และยกเลิกของที่สั่งไว้',
                    'buttons'  => [['เปิดดูรายละเอียด', $origin . '/manage_banquet/view.php?id=' . $id]],
                ]);
                $r = sendLineFlexToRoles(
                    $conn,
                    ['banquet_staff', 'technician', 'housekeeping', 'admin', 'procurement', 'staff', 'gm'],
                    $flex,
                    '❌ งานถูกยกเลิก: ' . $c['function_name']
                );
                $lineSent += $r['sent']; $lineFailed += $r['failed'];
            }
        } catch (Throwable $e) {
            error_log('[LINE] cancel notify failed: ' . $e->getMessage());
        }

        $_SESSION['flash_msg'] = "cancelled";
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย', 'line_sent' => $lineSent, 'line_failed' => $lineFailed]);
    exit();
}

$conn->close();
