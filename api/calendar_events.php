<?php
/* =========================================================
   api/calendar_events.php — JSON feed สำหรับ FullCalendar
   รับ ?start= &end= (ISO 8601) แล้วดึง "เฉพาะงานในกรอบที่มองเห็น"
   แทนการดึงทุกงานตั้งแต่เริ่มใช้งานมายัดลงหน้าปฏิทิน
   ========================================================= */
date_default_timezone_set('Asia/Bangkok');
include __DIR__ . "/../config.php";

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

/* ขยายขอบเขตออก 14 วันจับ event ที่ยืดยาวหลายวัน (เช่น QT ที่มี expiry_date) */
$pad = 14 * 86400;
$range_start = date('Y-m-d H:i:s', (strtotime($_GET['start'] ?? '-1 month') ?: time()) - $pad);
$range_end   = date('Y-m-d H:i:s', (strtotime($_GET['end']   ?? '+2 month') ?: time()) + $pad);

$events = [];

/* Auto-freeze ถูกถอดออกแล้ว — ไม่เขียน workflow_status='Freeze' ลง DB อีก
   (เดิม UPDATE ทุกครั้งที่โหลดปฏิทิน) แต่ยังเตือนบนปฏิทินแบบคำนวณสด
   ที่ foreach ของ quotations ด้านล่าง: ใบไหน event_date ชนกับใบที่ approved
   จะแสดง ❌ สีแดง "กรุณาเปลี่ยนวันหรือกด Freeze" โดยไม่แตะฐานข้อมูล */

function cal_color($st) {
    if ($st === 'pending') return '#ffc107';
    if ($st === 'confirmed' || $st === 'approved') return '#0dcaf0';
    if ($st === 'in progress') return '#0d6efd';
    if ($st === 'completed') return '#198754';
    if ($st === 'cancelled') return '#dc3545';
    return '#6c757d';
}

/* ---------- 1. งานจากตาราง functions (General Mode) ---------- */
$sql_f = "SELECT f.*, r.room_name, c.cust_name, c.cust_phone, u.name as creator_name
          FROM functions f
          LEFT JOIN meeting_rooms r ON f.room_id = r.id
          LEFT JOIN customers c ON f.customer_id = c.id
          LEFT JOIN users u ON f.created_by_id = u.id
          WHERE f.status != 'Cancelled'
            AND f.start_time >= '" . $conn->real_escape_string($range_start) . "'
            AND f.start_time <  '" . $conn->real_escape_string($range_end) . "'
          ORDER BY f.id ASC";
$q_f = mysqli_query($conn, $sql_f);
if (!$q_f) {
    $q_f = mysqli_query($conn, "SELECT * FROM functions
        WHERE start_time >= '" . $conn->real_escape_string($range_start) . "'
          AND start_time <  '" . $conn->real_escape_string($range_end) . "'
        ORDER BY id ASC");
}
if ($q_f) {
    while ($row = mysqli_fetch_assoc($q_f)) {
        $color = cal_color(strtolower(trim($row['status'] ?? '')));
        $events[] = [
            'id' => 'gen_' . $row['id'],
            'ref_id' => (string) $row['id'],
            'title' => (string) ($row['function_name'] ?? ''),
            'start' => (string) ($row['start_time'] ?? ''),
            'end' => (string) ($row['end_time'] ?? ''),
            'color' => $color,
            'mode' => 'general',
            'extendedProps' => [
                'mainTitle' => (string) ($row['function_name'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Pending'),
                'room' => (string) ($row['room_name'] ?? $row['room_id'] ?? ''),
                'customer' => (string) ($row['cust_name'] ?? ''),
                'phone' => (string) ($row['cust_phone'] ?? ''),
                'pax' => (string) ($row['pax'] ?? '0'),
                'deposit' => number_format($row['deposit'] ?? 0, 2),
                'total' => number_format($row['total_amount'] ?? 0, 2),
                'remark' => (string) ($row['remark'] ?? ''),
                'created_by_name' => (string) ($row['creator_name'] ?? $row['created_by'] ?? ''),
                'approved' => intval($row['approve'] ?? 0),
                'doc_no' => (string) ($row['function_code'] ?? $row['id'] ?? ''),
            ]
        ];
    }
}

/* ---------- 2. งานจากตาราง schedules (Schedule Mode) ---------- */
$sql_s = "SELECT s.*, f.function_name, f.status, r.room_name, c.cust_name, c.cust_phone, f.pax, f.deposit, f.total_amount,
                 f.lead_source, f.result, f.inspection_date, f.follow_up_date, f.created_at, f.approve_date, u.name as creator_name, f.created_by
          FROM function_schedules s
          JOIN functions f ON s.function_id = f.id
          LEFT JOIN meeting_rooms r ON f.room_id = r.id
          LEFT JOIN customers c ON f.customer_id = c.id
          LEFT JOIN users u ON f.created_by_id = u.id
          WHERE f.status != 'Cancelled'
            AND s.schedule_date >= '" . $conn->real_escape_string($range_start) . "'
            AND s.schedule_date <  '" . $conn->real_escape_string($range_end) . "'";
$q_s = mysqli_query($conn, $sql_s);
if (!$q_s) {
    $q_s = mysqli_query($conn, "SELECT s.*, f.function_name, f.status, f.created_by
          FROM function_schedules s
          JOIN functions f ON s.function_id = f.id
          WHERE s.schedule_date >= '" . $conn->real_escape_string($range_start) . "'
            AND s.schedule_date <  '" . $conn->real_escape_string($range_end) . "'");
}
if ($q_s) {
    while ($row = mysqli_fetch_assoc($q_s)) {
        $color = cal_color(strtolower(trim($row['status'] ?? '')));
        $sched_title = "[" . ($row['schedule_hour'] ?? '') . "] " . ($row['schedule_function'] ?? '');
        $events[] = [
            'id' => 'sched_' . $row['id'],
            'ref_id' => (string) $row['function_id'],
            'title' => $sched_title,
            'start' => (string) ($row['schedule_date'] ?? ''),
            'color' => $color,
            'mode' => 'schedule',
            'extendedProps' => [
                'mainTitle' => (string) ($row['function_name'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Pending'),
                'room' => (string) ($row['room_name'] ?? ''),
                'customer' => (string) ($row['cust_name'] ?? ''),
                'total' => number_format($row['total_amount'] ?? 0, 2),
                'remark' => (string) ($row['schedule_function'] ?? ''),
                'created_by_name' => (string) ($row['creator_name'] ?? $row['created_by'] ?? ''),
                'doc_no' => (string) ($row['id'] ?? ''),
            ]
        ];
    }
}

/* ---------- 3. ใบเสนอราคา (Quotations) — เฉพาะที่ event_date อยู่ในกรอบ ---------- */
$sql_q = "SELECT q.*, c.cust_name, c.cust_phone, u.name as creator_name,
                 ft.type_name as func_type_name, mr.room_name as func_room_name,
                 f.pax as func_pax, f.deposit as func_deposit, f.total_amount as func_total_amount,
                 (SELECT COUNT(*) FROM functions WHERE quotation_id = q.id AND status != 'Cancelled') as beo_count
          FROM quotations q
          LEFT JOIN customers c ON q.customer_id = c.id
          LEFT JOIN users u ON q.created_by = u.id
          LEFT JOIN functions f ON f.id = (
              SELECT f2.id FROM functions f2
              WHERE f2.quotation_id = q.id AND f2.status != 'Cancelled'
              ORDER BY f2.id DESC LIMIT 1
          )
          LEFT JOIN function_types ft ON f.function_type_id = ft.id
          LEFT JOIN meeting_rooms mr ON f.room_id = mr.id
          WHERE q.status NOT IN ('Cancelled')
            AND q.event_date >= '" . $conn->real_escape_string($range_start) . "'
            AND q.event_date <  '" . $conn->real_escape_string($range_end) . "'
          ORDER BY q.id DESC";
$q_q = mysqli_query($conn, $sql_q);
if (!$q_q) {
    $q_q = mysqli_query($conn, "SELECT * FROM quotations
        WHERE status NOT IN ('Cancelled')
          AND event_date >= '" . $conn->real_escape_string($range_start) . "'
          AND event_date <  '" . $conn->real_escape_string($range_end) . "'
        ORDER BY id DESC");
}

$qt_rows = [];
$date_approved_map = [];
if ($q_q) {
    while ($row = mysqli_fetch_assoc($q_q)) {
        $qt_rows[] = $row;
        $d = $row['event_date'] ?? '';
        if ($d && strtolower(trim($row['status'] ?? '')) === 'approved') {
            $date_approved_map[$d] = true;
        }
    }
}

foreach ($qt_rows as $row) {
    $st = strtolower(trim($row['status'] ?? ''));
    if ($st === 'draft' || $st === 'pending') {
        $color = '#6c757d';
        $status_text = 'QT (Draft)';
    } else {
        $color = '#fd7e14';
        $status_text = 'QT (อนุมัติ)';
    }

    /* freeze ถ้า DB บอก Freeze อยู่แล้ว หรือวันงานซ้อนกับใบที่อนุมัติ (เห็นผลทันทีไม่ต้องรีเฟรช)
       แต่ใบที่ approved เองห้ามถูก freeze — ไม่งั้นจะชนวันของตัวเองแล้วขึ้นแดงเหมือนยกเลิก */
    $is_freeze = ($st !== 'approved') && (($row['workflow_status'] ?? '') === 'Freeze');
    $d = $row['event_date'] ?? '';
    if (!$is_freeze && $st !== 'approved' && $d && isset($date_approved_map[$d])) {
        $is_freeze = true;
    }
    if ($is_freeze) {
        $color = '#dc3545';
        $status_text = 'กรุณาเปลี่ยนวันหรือกด Freeze';
    }

    $qt_title = "[" . ($row['quote_no'] ?? '') . "] " . ($row['event_name'] ?? '');
    if ($is_freeze) {
        $qt_title = "❌ " . $qt_title;
    }

    $wf_status = $is_freeze ? 'Freeze' : (string) ($row['workflow_status'] ?? '');
    $ev_date = $row['event_date'] ?? '';
    $ex_date = !empty($row['expiry_date']) ? $row['expiry_date'] : '';
    $end_date = '';
    if ($ex_date && $ex_date !== $ev_date) {
        $end_date = date('Y-m-d', strtotime($ex_date . ' +1 day'));
    }

    $ev = [
        'id' => 'qt_' . $row['id'],
        'ref_id' => (string) $row['id'],
        'title' => $qt_title,
        'start' => $ev_date,
        'color' => $color,
        'mode' => 'general',
        'extendedProps' => [
            'mainTitle' => (string) ($row['event_name'] ?? ''),
            'status' => $status_text,
            'customer' => (string) ($row['cust_name'] ?? ''),
            'phone' => (string) ($row['cust_phone'] ?? ''),
            'total' => number_format($row['grand_total'] ?? 0, 2),
            'created_by_name' => (string) ($row['creator_name'] ?? ''),
            'lead_source' => (string) ($row['lead_source'] ?? ''),
            'result' => (string) ($row['result'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'raw_status' => (string) ($row['status'] ?? ''),
            'inspection_date' => (string) ($row['inspection_date'] ?? ''),
            'follow_up_date' => (string) ($row['follow_up_date'] ?? ''),
            'confirmed_date' => (string) ($row['approved_at'] ?? ''),
            'doc_no' => (string) ($row['quote_no'] ?? ''),
            'approved' => 0,
            'is_qt' => true,
            'quote_no' => (string) ($row['quote_no'] ?? ''),
            'event_date' => (string) $ev_date,
            'func_type_name' => (string) ($row['func_type_name'] ?? ''),
            'room' => (string) ($row['func_room_name'] ?? ''),
            'pax' => (string) ($row['func_pax'] ?? $row['pax'] ?? '0'),
            'func_room_name' => (string) ($row['func_room_name'] ?? ''),
            'func_pax' => (string) ($row['func_pax'] ?? $row['pax'] ?? '0'),
            'func_deposit' => number_format($row['func_deposit'] ?? 0, 2),
            'func_total_amount' => number_format($row['func_total_amount'] ?? 0, 2),
            'workflow_status' => $wf_status,
            'customer_signed' => (string) ($row['customer_signature'] ?? ''),
            'has_beo' => intval($row['beo_count'] ?? 0) > 0 ? '✓' : '-',
        ]
    ];
    if ($end_date) {
        $ev['end'] = $end_date;
    }
    $events[] = $ev;
}

/* ---------- 4. จองห้องประชุม (Room Bookings) ---------- */
$sql_rb = "SELECT rb.*, mr.room_name, c.company_name, ft.type_name
    FROM room_bookings rb
    LEFT JOIN meeting_rooms mr ON rb.room_id = mr.id
    LEFT JOIN companies c ON rb.company_id = c.id
    LEFT JOIN function_types ft ON rb.function_type_id = ft.id
    WHERE rb.status = 'active'
      AND rb.start_time >= '" . $conn->real_escape_string($range_start) . "'
      AND rb.start_time <  '" . $conn->real_escape_string($range_end) . "'
    ORDER BY rb.id ASC";
$q_rb = @mysqli_query($conn, $sql_rb);

if ($q_rb) {
    while ($row = mysqli_fetch_assoc($q_rb)) {
        $booking_name = $row['booking_name'] ?: ($row['created_by'] ?? '');
        $room_label = $row['room_name'] ?? '';
        $created_by = $row['created_by'] ?? '';
        $rb_title = "📌 " . $created_by . " จอง " . ($row['event_name'] ?? '') . " — " . $room_label;

        $events[] = [
            'id' => 'rb_' . $row['id'],
            'ref_id' => (string) $row['id'],
            'title' => $rb_title,
            'start' => (string) ($row['start_time'] ?? ''),
            'end' => (string) ($row['end_time'] ?? ''),
            'color' => '#6f42c1',
            'mode' => 'general',
            'extendedProps' => [
                'mainTitle' => (string) ($row['event_name'] ?? ''),
                'status' => 'จองห้อง',
                'room' => (string) $room_label,
                'customer' => (string) $booking_name,
                'phone' => (string) ($row['phone'] ?? ''),
                'pax' => (string) ($row['pax'] ?? '0'),
                'remark' => (string) ($row['remark'] ?? ''),
                'created_by_name' => (string) ($row['created_by'] ?? ''),
                'booking_code' => (string) ($row['booking_code'] ?? ''),
                'organization' => (string) ($row['organization'] ?? ''),
                'doc_no' => (string) ($row['booking_code'] ?? ''),
            ]
        ];
    }
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
