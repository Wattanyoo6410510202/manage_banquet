<?php
/* =========================================================
   booking_list.php — รวมรายการจองห้องประชุม (room_bookings)
   ค้นหา / กรอง / เรียงลำดับ / ส่งออก CSV / ยกเลิกการจอง
   ========================================================= */

/* php.ini ของ XAMPP ตั้ง timezone เป็น Europe/Berlin ซึ่งช้ากว่าไทย 5-6 ชม.
   ต้องบังคับให้ตรงกับ NOW()/CURDATE() ของ MySQL ไม่งั้นช่วงเที่ยงคืน-ตี 6
   คำว่า "วันนี้" ของ PHP จะกลายเป็นเมื่อวาน */
date_default_timezone_set('Asia/Bangkok');

include "config.php";

/* ---------- สิทธิ์การเข้าถึง ----------
   ต้องเช็คตรงนี้ก่อน include header.php เพราะสาขา CSV/JSON ห้ามมี HTML นำหน้า */
if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'staff', 'gm', 'sale', 'procurement', 'manager'])) {
    echo "<script>window.location.href='login.php?error=access_denied';</script>";
    exit;
}
$can_cancel = in_array($role, ['admin', 'gm']); // ให้ตรงกับสิทธิ์ลบใน room_calendar.php

/* ---------- API: ยกเลิกการจอง ---------- */
if (($_POST['action'] ?? '') === 'cancel') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    if (!$can_cancel) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ยกเลิกการจอง'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // ยกเลิกเฉพาะรายการที่ยัง active อยู่ กันกดซ้ำ
    $affected = db_execute($conn, "UPDATE room_bookings SET status='cancelled', updated_at=NOW() WHERE id=? AND status='active'", "i", $id);
    echo json_encode(
        $affected > 0
            ? ['status' => 'success']
            : ['status' => 'error', 'message' => 'รายการนี้ถูกยกเลิกไปแล้ว หรือไม่พบข้อมูล'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ---------- ตัวกรอง ---------- */
$q          = trim($_GET['q'] ?? '');
$f_room     = intval($_GET['room_id'] ?? 0);
$f_company  = intval($_GET['company_id'] ?? 0);
$f_type     = intval($_GET['type_id'] ?? 0);
$f_status   = $_GET['status'] ?? 'all';
$date_from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$date_to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
if (!in_array($f_status, ['all', 'active', 'cancelled'])) $f_status = 'all';

/* ---------- แหล่งข้อมูลที่จะแสดง (checklist) — จองห้อง / ใบเสนอราคา / EO ---------- */
$all_sources = ['booking', 'quote', 'eo'];
$sources = array_values(array_intersect($all_sources, array_map('strval', (array) ($_GET['src'] ?? $all_sources))));
if (empty($sources)) $sources = $all_sources;
$show_booking = in_array('booking', $sources, true);
$show_quote   = in_array('quote', $sources, true);
$show_eo      = in_array('eo', $sources, true);

/* ป้ายกำกับแหล่งข้อมูลของแต่ละแถว (ใช้ทั้งบนจอและตอนส่งออก) */
if (!function_exists('bl_src_meta')) {
    function bl_src_meta($src)
    {
        static $m = [
            'booking' => ['label' => 'จองห้อง',     'cls' => 'src-booking', 'icon' => 'bi-journal-bookmark'],
            'quote'   => ['label' => 'ใบเสนอราคา',  'cls' => 'src-quote',   'icon' => 'bi-file-earmark-text'],
            'eo'      => ['label' => 'EO',           'cls' => 'src-eo',      'icon' => 'bi-calendar-check'],
        ];
        return $m[$src] ?? ['label' => $src, 'cls' => '', 'icon' => 'bi-dot'];
    }
}

/* สถานะแบบรวม — แต่ละแหล่งมีชุดสถานะของตัวเอง แปลงให้ออกมาเป็นหน้าตาเดียวกัน (pill + ธง "ยกเลิก") */
if (!function_exists('bl_status_meta')) {
    function bl_status_meta($r)
    {
        $src = $r['_src'] ?? 'booking';
        $s = (string) ($r['status'] ?? '');
        if ($src === 'booking') {
            $cancelled = $s !== 'active';
            return ['cancelled' => $cancelled, 'cls' => $cancelled ? 'off' : 'on',
                    'icon' => $cancelled ? 'bi-x-circle-fill' : 'bi-check-circle-fill',
                    'label' => $cancelled ? 'ยกเลิกแล้ว' : 'ใช้งานอยู่'];
        }
        if ($src === 'quote') {
            if ($s === 'Cancelled') return ['cancelled' => true,  'cls' => 'off',  'icon' => 'bi-x-circle-fill',     'label' => 'ยกเลิก'];
            if ($s === 'Approved')  return ['cancelled' => false, 'cls' => 'on',   'icon' => 'bi-check-circle-fill', 'label' => 'อนุมัติแล้ว'];
            if ($s === 'Sent')      return ['cancelled' => false, 'cls' => 'done', 'icon' => 'bi-send',              'label' => 'ส่งแล้ว'];
            return ['cancelled' => false, 'cls' => 'done', 'icon' => 'bi-pencil-square', 'label' => 'ฉบับร่าง'];
        }
        // eo
        if (strcasecmp($s, 'Cancelled') === 0) return ['cancelled' => true, 'cls' => 'off', 'icon' => 'bi-x-circle-fill', 'label' => 'ยกเลิก'];
        $approved = !empty($r['is_approved']) || !empty($r['approve']) || in_array($s, ['Confirmed', 'Approved'], true);
        if ($approved) return ['cancelled' => false, 'cls' => 'on', 'icon' => 'bi-check-circle-fill', 'label' => 'ยืนยันแล้ว'];
        return ['cancelled' => false, 'cls' => 'done', 'icon' => 'bi-hourglass-split', 'label' => $s !== '' ? $s : 'ร่าง'];
    }
}

// เรียงลำดับ — ใช้ whitelist เท่านั้น ห้ามรับชื่อคอลัมน์จาก URL ตรงๆ
$sort_map = [
    'start_time'   => 'rb.start_time',
    'booking_code' => 'rb.booking_code',
    'event_name'   => 'rb.event_name',
    'room_name'    => 'mr.room_name',
    'pax'          => 'rb.pax',
    'status'       => 'rb.status',
    'created_at'   => 'rb.created_at',
    'type_name'    => 'ft.type_name',
    'organization' => 'rb.organization',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sort_map) ? $_GET['sort'] : 'start_time';
$dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

/* ---------- ประกอบเงื่อนไขแบบ prepared statement ---------- */
$where = ["1=1"];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = "(rb.booking_code LIKE ? OR rb.event_name LIKE ? OR rb.booking_name LIKE ?
                 OR rb.phone LIKE ? OR rb.organization LIKE ? OR rb.remark LIKE ?)";
    $like = '%' . $q . '%';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($f_room > 0)    { $where[] = "rb.room_id = ?";          $types .= 'i'; $params[] = $f_room; }
if ($f_company > 0) { $where[] = "rb.company_id = ?";       $types .= 'i'; $params[] = $f_company; }
if ($f_type > 0)    { $where[] = "rb.function_type_id = ?"; $types .= 'i'; $params[] = $f_type; }
if ($f_status !== 'all') { $where[] = "rb.status = ?";      $types .= 's'; $params[] = $f_status; }
if ($date_from !== '')   { $where[] = "rb.start_time >= ?"; $types .= 's'; $params[] = $date_from; }
if ($date_to !== '')     { $where[] = "rb.start_time < DATE_ADD(?, INTERVAL 1 DAY)"; $types .= 's'; $params[] = $date_to; }

$where_sql = implode(' AND ', $where);

$base_sql = "SELECT rb.*, mr.room_name, c.company_name, ft.type_name, cust.cust_name AS system_cust_name,
                    bt.type_name AS break_type_name, mt.type_name AS menu_type_name
             FROM room_bookings rb
             LEFT JOIN meeting_rooms mr ON rb.room_id = mr.id
             LEFT JOIN companies c      ON rb.company_id = c.id
             LEFT JOIN function_types ft ON rb.function_type_id = ft.id
             LEFT JOIN customers cust   ON rb.customer_id = cust.id
             LEFT JOIN master_break_types bt ON rb.break_type_id = bt.id
             LEFT JOIN master_menu_types mt ON rb.menu_type_id = mt.id
             WHERE $where_sql
             ORDER BY {$sort_map[$sort]} $dir, rb.id DESC";

$rows = $show_booking ? db_fetch_all($conn, $base_sql, $types, ...$params) : [];
foreach ($rows as $i => $r) $rows[$i]['_src'] = 'booking';

/* ---------- แหล่งเพิ่มเติม: ใบเสนอราคา (quotations) ----------
   ตัวกรองที่ใช้ร่วมได้: ค้นหา, โรงแรม, ช่วงวัน, สถานะ (ยกเลิก/ไม่ยกเลิก)
   ตัวกรอง "ห้องประชุม" และ "ประเภทงาน" ไม่มีในใบเสนอราคา → ถ้าเลือกไว้ให้ข้ามแหล่งนี้ */
if ($show_quote && !($f_room > 0) && !($f_type > 0)) {
    $qw = ["1=1"]; $qt = ''; $qp = [];
    if ($q !== '') {
        $qw[] = "(qt.quote_no LIKE ? OR qt.event_name LIKE ? OR cu.cust_name LIKE ? OR cu.cust_contact_name LIKE ? OR qt.remarks LIKE ?)";
        $qt .= 'sssss';
        $like = '%' . $q . '%';
        array_push($qp, $like, $like, $like, $like, $like);
    }
    if ($f_company > 0) { $qw[] = "qt.company_id = ?"; $qt .= 'i'; $qp[] = $f_company; }
    if ($date_from !== '') { $qw[] = "qt.event_date >= ?"; $qt .= 's'; $qp[] = $date_from; }
    if ($date_to !== '')   { $qw[] = "qt.event_date <= ?"; $qt .= 's'; $qp[] = $date_to; }
    if ($f_status === 'cancelled')  $qw[] = "qt.status = 'Cancelled'";
    elseif ($f_status === 'active') $qw[] = "qt.status <> 'Cancelled'";

    $qsql = "SELECT qt.id, qt.quote_no, qt.event_name, qt.event_date, qt.grand_total, qt.status,
                    qt.remarks, qt.company_id, qt.customer_id, qt.created_at,
                    cu.cust_name, cu.cust_contact_name, cu.cust_phone,
                    co.company_name, u.name AS creator_name
             FROM quotations qt
             LEFT JOIN customers cu ON qt.customer_id = cu.id
             LEFT JOIN companies co ON qt.company_id = co.id
             LEFT JOIN users u      ON qt.created_by = u.id
             WHERE " . implode(' AND ', $qw) . "
             ORDER BY qt.event_date DESC, qt.id DESC";
    foreach (db_fetch_all($conn, $qsql, $qt, ...$qp) as $q0) {
        $rows[] = [
            '_src'            => 'quote',
            'id'              => $q0['id'],
            'booking_code'    => $q0['quote_no'],
            'event_name'      => $q0['event_name'],
            'start_time'      => $q0['event_date'] ? $q0['event_date'] . ' 00:00:00' : null,
            'end_time'        => null,
            'organization'    => $q0['cust_name'],
            'booking_name'    => $q0['cust_contact_name'],
            'phone'           => $q0['cust_phone'],
            'customer_id'     => $q0['customer_id'],
            'system_cust_name' => $q0['cust_name'],
            'room_name'       => null,
            'company_name'    => $q0['company_name'],
            'company_id'      => $q0['company_id'],
            'type_name'       => null,
            'pax'             => 0,
            'break_type_name' => null,
            'menu_type_name'  => null,
            'room_stay'       => null,
            'created_at'      => $q0['created_at'],
            'created_by'      => $q0['creator_name'],
            'selling_price'   => $q0['grand_total'],
            'status'          => $q0['status'],
            'remark'          => $q0['remarks'],
        ];
    }
}

/* ---------- แหล่งเพิ่มเติม: EO / Function Order (functions) ---------- */
if ($show_eo) {
    $ew = ["1=1"]; $et = ''; $ep = [];
    if ($q !== '') {
        $ew[] = "(f.function_code LIKE ? OR f.function_name LIKE ? OR f.booking_name LIKE ? OR f.phone LIKE ? OR f.organization LIKE ? OR f.remark LIKE ?)";
        $et .= 'ssssss';
        $like = '%' . $q . '%';
        array_push($ep, $like, $like, $like, $like, $like, $like);
    }
    if ($f_room > 0)    { $ew[] = "f.room_id = ?";          $et .= 'i'; $ep[] = $f_room; }
    if ($f_company > 0) { $ew[] = "f.company_id = ?";       $et .= 'i'; $ep[] = $f_company; }
    if ($f_type > 0)    { $ew[] = "f.function_type_id = ?"; $et .= 'i'; $ep[] = $f_type; }
    $eo_date_expr = "COALESCE(f.start_time, f.event_date)";
    if ($date_from !== '') { $ew[] = "$eo_date_expr >= ?"; $et .= 's'; $ep[] = $date_from; }
    if ($date_to !== '')   { $ew[] = "$eo_date_expr < DATE_ADD(?, INTERVAL 1 DAY)"; $et .= 's'; $ep[] = $date_to; }
    if ($f_status === 'cancelled')  $ew[] = "f.status = 'Cancelled'";
    elseif ($f_status === 'active') $ew[] = "(f.status IS NULL OR f.status <> 'Cancelled')";

    $esql = "SELECT f.id, f.function_code, f.function_name, f.event_date, f.start_time, f.end_time,
                    f.booking_name, f.organization, f.phone, f.room_name, f.room_id, f.pax,
                    f.total_amount, f.remark, f.created_at, f.created_by, f.status, f.is_approved, f.approve,
                    f.company_id, f.customer_id,
                    co.company_name, ft.type_name, cu.cust_name
             FROM functions f
             LEFT JOIN companies co       ON f.company_id = co.id
             LEFT JOIN function_types ft  ON f.function_type_id = ft.id
             LEFT JOIN customers cu       ON f.customer_id = cu.id
             WHERE " . implode(' AND ', $ew) . "
             ORDER BY $eo_date_expr DESC, f.id DESC";
    foreach (db_fetch_all($conn, $esql, $et, ...$ep) as $e0) {
        $eo_start = $e0['start_time'] ?: ($e0['event_date'] ? $e0['event_date'] . ' 00:00:00' : null);
        $rows[] = [
            '_src'            => 'eo',
            'id'              => $e0['id'],
            'booking_code'    => $e0['function_code'],
            'event_name'      => $e0['function_name'],
            'start_time'      => $eo_start,
            'end_time'        => $e0['end_time'],
            'organization'    => $e0['organization'] ?: $e0['cust_name'],
            'booking_name'    => $e0['booking_name'],
            'phone'           => $e0['phone'],
            'customer_id'     => $e0['customer_id'],
            'system_cust_name' => $e0['cust_name'],
            'room_name'       => $e0['room_name'],
            'company_name'    => $e0['company_name'],
            'company_id'      => $e0['company_id'],
            'type_name'       => $e0['type_name'],
            'pax'             => $e0['pax'],
            'break_type_name' => null,
            'menu_type_name'  => null,
            'room_stay'       => null,
            'created_at'      => $e0['created_at'],
            'created_by'      => $e0['created_by'],
            'selling_price'   => $e0['total_amount'],
            'status'          => $e0['status'],
            'is_approved'     => $e0['is_approved'],
            'approve'         => $e0['approve'],
            'remark'          => $e0['remark'],
        ];
    }
}

/* ---------- เรียงลำดับรวมทุกแหล่งในฝั่ง PHP (query ของแต่ละแหล่งเรียงมาแล้วในระดับหนึ่ง
   แต่พอ merge กันต้องจัดใหม่ให้เป็นชุดเดียว ตาม sort/dir ที่ผู้ใช้เลือก) ---------- */
if (count($sources) > 1 || !$show_booking) {
    $sort_field_map = [
        'start_time' => 'start_time', 'booking_code' => 'booking_code', 'event_name' => 'event_name',
        'room_name' => 'room_name', 'pax' => 'pax', 'status' => 'status',
        'created_at' => 'created_at', 'type_name' => 'type_name', 'organization' => 'organization',
    ];
    $sk = $sort_field_map[$sort] ?? 'start_time';
    $dir_mul = ($dir === 'ASC') ? 1 : -1;
    usort($rows, function ($a, $b) use ($sk, $dir_mul) {
        $av = $a[$sk] ?? ''; $bv = $b[$sk] ?? '';
        if ($sk === 'pax') {
            $c = (int) $av <=> (int) $bv;
        } elseif ($sk === 'start_time' || $sk === 'created_at') {
            $c = (($av ? strtotime($av) : 0)) <=> (($bv ? strtotime($bv) : 0));
        } else {
            $c = strcmp((string) $av, (string) $bv);
        }
        if ($c === 0) $c = (($a['start_time'] ? strtotime($a['start_time']) : 0)) <=> (($b['start_time'] ? strtotime($b['start_time']) : 0));
        return $c * $dir_mul;
    });
}

/* ---------- มุมมองรายวัน (อารมณ์ปฏิทินแนวตั้ง) — ใช้ $rows ชุดเดียวกับตาราง แค่จัดกลุ่มใหม่ตามวันในเดือน
   ต้องคำนวณก่อนส่วน export เพราะปุ่ม "ส่งออก Excel" ต้องรู้ว่าตอนนี้อยู่มุมมองไหนด้วย ---------- */
$view = ($_GET['view'] ?? 'table') === 'agenda' ? 'agenda' : 'table';
$agenda_month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$agenda_ts = strtotime($agenda_month . '-01');
if (!$agenda_ts) { $agenda_month = date('Y-m'); $agenda_ts = strtotime($agenda_month . '-01'); }
$agenda_year = (int) date('Y', $agenda_ts);
$agenda_mon  = (int) date('n', $agenda_ts);
$days_in_month = (int) date('t', $agenda_ts);
$today_key = date('Y-m-d');

// การจองที่คร่อมหลายวัน (เช่น สัมมนา 3 วัน) ต้องขึ้นซ้ำทุกวันที่ครอบคลุม ไม่ใช่แค่วันเริ่ม
// ไม่งั้นปฏิทินจะโกหกว่าห้องว่างในวันที่ 2-3 ของงานทั้งที่จริงถูกจองอยู่
$agenda_by_day = array_fill(1, $days_in_month, []);
if ($view === 'agenda') {
    $month_start_ts = mktime(0, 0, 0, $agenda_mon, 1, $agenda_year);
    $month_end_ts   = mktime(0, 0, 0, $agenda_mon, $days_in_month, $agenda_year);
    foreach ($rows as $r) {
        $sts = !empty($r['start_time']) ? strtotime($r['start_time']) : false;
        if (!$sts) continue;
        $ets = !empty($r['end_time']) ? strtotime($r['end_time']) : $sts;
        if (!$ets || $ets < $sts) $ets = $sts;

        $seg_start = max($sts, $month_start_ts);
        $seg_end   = min($ets, $month_end_ts);
        if ($seg_start > $seg_end) continue; // ช่วงของรายการนี้ไม่ตกในเดือนที่กำลังดูเลย

        for ($dd = (int) date('j', $seg_start); $dd <= (int) date('j', $seg_end); $dd++) {
            $agenda_by_day[$dd][] = $r;
        }
    }
}
$agenda_prev = date('Y-m', strtotime($agenda_month . '-01 -1 month'));
$agenda_next = date('Y-m', strtotime($agenda_month . '-01 +1 month'));

/* ---------- ส่งออก Excel (ใช้ตัวกรองชุดเดียวกับที่เห็นบนหน้าจอ / มุมมองเดียวกับที่เห็นด้วย)
   หมายเหตุ: ไม่มี library ทำ .xlsx จริงในโปรเจกต์นี้ (ไม่มี composer/vendor) เลยใช้เทคนิคมาตรฐาน
   คือ output เป็นตาราง HTML ที่มีเส้นขอบ/สีพื้นหลังจริงๆ แล้วตั้งชื่อไฟล์ .xls — Excel เปิดเป็นตารางพร้อม
   จัดรูปแบบ (เส้นตาราง, สีหัวตาราง) ให้เองโดยไม่ต้องเพิ่ม dependency ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    if (ob_get_length()) ob_clean();
    $filename = 'booking_list_' . ($view === 'agenda' ? $agenda_month . '_รายวัน_' : '') . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $eh = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $csv_dow = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
    $csv_row = fn($r) => [
        bl_src_meta($r['_src'] ?? 'booking')['label'],
        $r['booking_code'], $r['event_name'], $r['type_name'], $r['company_name'], $r['room_name'],
        $r['start_time'], $r['end_time'], $r['pax'], $r['booking_name'], $r['phone'],
        $r['organization'], bl_status_meta($r)['label'],
        $r['break_type_name'], $r['menu_type_name'], $r['room_stay'],
        $r['selling_price'] !== null ? number_format($r['selling_price'], 2, '.', '') : '',
        $r['created_by'], $r['created_at'], $r['remark'],
    ];

    $th_style  = 'background:#16181d;color:#ffffff;font-weight:bold;padding:6px 10px;border:1px solid #444444;white-space:nowrap;';
    $td_style  = 'padding:5px 9px;border:1px solid #cccccc;';
    $td_empty  = 'padding:5px 9px;border:1px solid #cccccc;background:#f4f5f7;color:#888888;font-style:italic;';
    $td_dowcol = 'padding:5px 9px;border:1px solid #cccccc;background:#faf7ef;text-align:center;font-weight:bold;';

    echo "<html><head><meta charset=\"UTF-8\"></head><body>";
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" style=\"border-collapse:collapse;font-family:Tahoma,sans-serif;font-size:12px;\">";
    echo "<thead><tr>";
    $headers = $view === 'agenda'
        ? ['วัน', 'วันที่', 'แหล่งข้อมูล', 'รหัส', 'ชื่องาน', 'ประเภท', 'โรงแรม', 'ห้อง', 'เริ่ม', 'สิ้นสุด', 'จำนวนคน', 'ผู้จอง/ผู้ติดต่อ', 'เบอร์โทร', 'หน่วยงาน/ลูกค้า', 'สถานะ', 'เบรก', 'ประเภทอาหาร', 'ห้องพัก', 'ราคาขาย', 'ผู้บันทึก', 'บันทึกเมื่อ', 'หมายเหตุ']
        : ['แหล่งข้อมูล', 'รหัส', 'ชื่องาน', 'ประเภท', 'โรงแรม', 'ห้อง', 'เริ่ม', 'สิ้นสุด', 'จำนวนคน', 'ผู้จอง/ผู้ติดต่อ', 'เบอร์โทร', 'หน่วยงาน/ลูกค้า', 'สถานะ', 'เบรก', 'ประเภทอาหาร', 'ห้องพัก', 'ราคาขาย', 'ผู้บันทึก', 'บันทึกเมื่อ', 'หมายเหตุ'];
    foreach ($headers as $hd) echo "<th style=\"$th_style\">" . $eh($hd) . "</th>";
    echo "</tr></thead><tbody>";

    if ($view === 'agenda') {
        // มุมมองรายวัน: ขึ้นครบทุกวันของเดือนเหมือนที่เห็นบนจอ วันว่างก็ยังมีแถวไว้ (เขียนว่า "ไม่มีการจอง")
        for ($d = 1; $d <= $days_in_month; $d++) {
            $day_ts = mktime(0, 0, 0, $agenda_mon, $d, $agenda_year);
            $dow = $csv_dow[(int) date('w', $day_ts)];
            $date_str = date('Y-m-d', $day_ts);
            $day_items = $agenda_by_day[$d];
            if (empty($day_items)) {
                echo "<tr><td style=\"$td_dowcol\">" . $eh($dow) . "</td><td style=\"$td_dowcol\">" . $eh($date_str) . "</td>"
                    . "<td style=\"$td_empty\" colspan=\"20\">ไม่มีการจอง</td></tr>";
            } else {
                foreach ($day_items as $r) {
                    echo "<tr><td style=\"$td_dowcol\">" . $eh($dow) . "</td><td style=\"$td_dowcol\">" . $eh($date_str) . "</td>";
                    foreach ($csv_row($r) as $c) echo "<td style=\"$td_style\">" . $eh($c) . "</td>";
                    echo "</tr>";
                }
            }
        }
    } else {
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($csv_row($r) as $c) echo "<td style=\"$td_style\">" . $eh($c) . "</td>";
            echo "</tr>";
        }
    }
    echo "</tbody></table></body></html>";
    exit;
}

/* ---------- ข้อมูลสำหรับ dropdown ---------- */
$rooms_opt   = db_fetch_all($conn, "SELECT mr.id, mr.room_name, c.company_name FROM meeting_rooms mr LEFT JOIN companies c ON mr.company_id=c.id ORDER BY c.company_name, mr.room_name");
$comp_opt    = db_fetch_all($conn, "SELECT id, company_name FROM companies ORDER BY company_name");
$type_opt    = db_fetch_all($conn, "SELECT id, type_name FROM function_types ORDER BY id");

/* ---------- สรุปยอด (นับจากผลลัพธ์หลังกรองแล้ว) ---------- */
$sum_total     = count($rows);
$sum_active    = 0;
$sum_cancelled = 0;
$sum_pax       = 0;
$sum_upcoming  = 0;
$now_ts = time();
foreach ($rows as $r) {
    if (bl_status_meta($r)['cancelled']) {
        $sum_cancelled++;
    } else {
        $sum_active++;
        $sum_pax += (int)$r['pax'];
        if (!empty($r['start_time']) && strtotime($r['start_time']) >= $now_ts) $sum_upcoming++;
    }
}

/* ---------- helper ---------- */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

if (!function_exists('thai_dt')) {
    function thai_dt($dt, $with_time = true)
    {
        static $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        if (!$dt || $dt === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($dt);
        if (!$ts) return '-';
        $s = date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . (date('Y', $ts) + 543);
        return $with_time ? $s . ' ' . date('H:i', $ts) : $s;
    }
}

if (!function_exists('thai_dow')) {
    function thai_dow($dt)
    {
        static $days = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
        if (!$dt || $dt === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($dt);
        if (!$ts) return '-';
        return $days[(int)date('w', $ts)];
    }
}

if (!function_exists('thai_time_range')) {
    function thai_time_range($start, $end)
    {
        $ts1 = $start ? strtotime($start) : false;
        $ts2 = $end ? strtotime($end) : false;
        if (!$ts1) return '-';
        return $ts2 ? date('H:i', $ts1) . '-' . date('H:i', $ts2) : date('H:i', $ts1);
    }
}

// แถวเดียวกันทั้ง 2 มุมมอง (ตาราง และ รายวัน) — คอลัมน์ต้องตรงกับ <thead> เป๊ะๆ
// $display_day_ts: ใช้ตอนแสดงในมุมมองรายวัน กรณีงานคร่อมหลายวัน — คอลัมน์ "วัน/วันที่" ต้องโชว์วันที่ของแถวนั้นๆ
// ไม่ใช่วันเริ่มงานเดิม (ไม่งั้นแถวที่ซ้ำอยู่ใต้วันที่ 16 จะเขียนวันที่ 14 ทำให้งง)
if (!function_exists('render_booking_row')) {
    function render_booking_row($r, $h, $can_cancel, $display_day_ts = null, $hide_date = false)
    {
        $src = $r['_src'] ?? 'booking';
        $sm  = bl_status_meta($r);
        $src_meta = bl_src_meta($src);
        $is_cancelled = $sm['cancelled'];
        $start_ts = !empty($r['start_time']) ? strtotime($r['start_time']) : false;
        $is_continuation = $display_day_ts !== null && $start_ts && date('Y-m-d', $display_day_ts) !== date('Y-m-d', $start_ts);
        $date_key = $display_day_ts !== null ? date('Y-m-d', $display_day_ts) : ($r['start_time'] ?? '');
        ob_start();
        ?>
        <tr class="<?= $is_cancelled ? 'is-cancelled' : '' ?><?= $hide_date ? ' bk-samedate' : '' ?>" data-id="<?= $r['id'] ?>" data-src="<?= $src ?>">
            <td><?= $hide_date ? '' : thai_dow($date_key) ?></td>
            <td class="num"><?= $hide_date ? '' : ($date_key ? thai_dt($date_key, false) : '-') ?></td>
            <td class="text-truncate" style="max-width:220px" title="<?= $h($r['organization'] ?: '') ?>">
                <div><span class="src-tag <?= $src_meta['cls'] ?>"><i class="bi <?= $src_meta['icon'] ?>"></i><?= $h($src_meta['label']) ?></span></div>
                <div class="fw-medium"><?= $h($r['organization'] ?: '-') ?></div>
                <div style="font-size:.72rem;color:#8a9099">
                    <?= $h($r['booking_name'] ?: '-') ?>
                    <?php if (!empty($r['customer_id'])): ?>
                        <i class="bi bi-link-45deg text-gold" title="ผูกกับลูกค้าในระบบ: <?= $h($r['system_cust_name'] ?: '') ?>"></i>
                    <?php endif; ?>
                    <?php if ($is_continuation): ?>
                        <span class="tag" style="background:#eef1f6;color:#5b6470" title="งานนี้เริ่มตั้งแต่ <?= $h(thai_dt($r['start_time'])) ?>">ต่อเนื่องจากวันก่อน</span>
                    <?php endif; ?>
                </div>
            </td>
            <td><?= $h($r['phone'] ?: '-') ?></td>
            <td>
                <div><?= $h($r['room_name'] ?: '-') ?></div>
                <div style="font-size:.72rem;color:#8a9099"><?= $h($r['company_name'] ?: '-') ?></div>
            </td>
            <td class="num"><?= $src === 'quote' ? '-' : thai_time_range($r['start_time'] ?? null, $r['end_time'] ?? null) ?></td>
            <td><?php if ($src === 'booking'): ?><span class="tag"><?= $h($r['type_name'] ?: 'ไม่ระบุประเภท') ?></span><?php else: ?><span class="<?= $r['type_name'] ? 'tag' : 'text-muted' ?>"><?= $h($r['type_name'] ?: '-') ?></span><?php endif; ?></td>
            <td class="text-center num"><?= $r['pax'] > 0 ? number_format($r['pax']) : '-' ?></td>
            <td class="<?= $r['break_type_name'] ? '' : 'text-muted' ?>"><?= $h($r['break_type_name'] ?: '-') ?></td>
            <td class="<?= $r['menu_type_name'] ? '' : 'text-muted' ?>"><?= $h($r['menu_type_name'] ?: '-') ?></td>
            <td class="<?= $r['room_stay'] ? '' : 'text-muted' ?>"><?= $h($r['room_stay'] ?: '-') ?></td>
            <td class="num"><?= thai_dt($r['created_at']) ?></td>
            <td><?= $h($r['created_by'] ?: '-') ?></td>
            <td class="text-end <?= $r['selling_price'] !== null ? 'fw-medium' : 'text-muted' ?>"><?= $r['selling_price'] !== null ? number_format($r['selling_price'], 2) : '-' ?></td>
            <td>
                <span class="pill <?= $sm['cls'] ?>">
                    <i class="bi <?= $sm['icon'] ?>"></i>
                    <?= $h($sm['label']) ?>
                </span>
            </td>
            <td class="text-end">
                <?php if ($src === 'quote'): ?>
                    <a href="quotation_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary border-0" title="ดูใบเสนอราคา">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="edit_quotation.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary border-0" title="แก้ไขใบเสนอราคา">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                <?php elseif ($src === 'eo'): ?>
                    <a href="view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary border-0" title="ดู EO">
                        <i class="bi bi-eye"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 btn-detail"
                            data-id="<?= $r['id'] ?>" title="ดูรายละเอียด">
                        <i class="bi bi-eye"></i>
                    </button>
                    <?php
                        $quote_qs = http_build_query(array_filter([
                            'event_name'  => $r['event_name'] ?? '',
                            'event_date'  => $r['start_time'] ? date('Y-m-d', strtotime($r['start_time'])) : '',
                            'company_id'  => $r['company_id'] ?? '',
                            'customer_id' => $r['customer_id'] ?? '',
                        ]));
                    ?>
                    <a href="add_quote.php?<?= $quote_qs ?>" class="btn btn-sm btn-outline-primary border-0" title="ส่งไปใบเสนอราคา">
                        <i class="bi bi-file-earmark-plus"></i>
                    </a>
                    <?php if ($can_cancel && !$is_cancelled): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-cancel"
                                data-id="<?= $r['id'] ?>" data-name="<?= $h($r['event_name']) ?>" title="ยกเลิกการจอง">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
}

// แถว "ไม่มีการจอง" สำหรับวันว่างในมุมมองรายวัน — คอลัมน์เท่ากับแถวข้อมูลจริงเป๊ะๆ (แค่ขึ้น dash)
if (!function_exists('render_empty_day_row')) {
    function render_empty_day_row($day_ts, $is_today)
    {
        ob_start();
        ?>
        <tr class="bk-empty-day <?= $is_today ? 'is-today' : '' ?>">
            <td><?= thai_dow(date('Y-m-d', $day_ts)) ?></td>
            <td class="num"><?= thai_dt(date('Y-m-d', $day_ts), false) ?></td>
            <td colspan="14" class="text-muted fst-italic">ไม่มีการจอง</td>
        </tr>
        <?php
        return ob_get_clean();
    }
}

// ลิงก์เรียงลำดับ — คงตัวกรองเดิมไว้ สลับเฉพาะทิศทาง
if (!function_exists('sort_link')) {
function sort_link($key, $cur_sort, $cur_dir)
{
    $qs = $_GET;
    $qs['sort'] = $key;
    $qs['dir'] = ($cur_sort === $key && strtoupper($cur_dir) === 'ASC') ? 'desc' : 'asc';
    unset($qs['export']);
    return '?' . http_build_query($qs);
}
function sort_icon($key, $cur_sort, $cur_dir)
{
    if ($cur_sort !== $key) return '<i class="bi bi-arrow-down-up text-muted opacity-25"></i>';
    return strtoupper($cur_dir) === 'ASC'
        ? '<i class="bi bi-sort-up text-gold"></i>'
        : '<i class="bi bi-sort-down text-gold"></i>';
}
}

// query string สำหรับปุ่มส่งออก
$export_qs = $_GET;
$export_qs['export'] = 'csv';

require_once "header.php";
?>

<style>
.bk-page{--gold:#b89441;--gold-tint:#f7f1e3;--ink:#111318;--ink2:#5b6470;--muted:#8a9099;--line:#e8eaee}
.bk-page{font-family:'Sarabun','Inter',sans-serif;color:var(--ink)}
.bk-hero{border-radius:16px;padding:15px 20px;color:var(--ink);position:relative;overflow:hidden;
    background:#fff;border:1px solid var(--line);border-left:4px solid var(--gold)}
.bk-hero::after{content:'';position:absolute;inset:0;pointer-events:none;
    background:radial-gradient(520px 200px at 92% -40%,rgba(184,148,65,.10),transparent 70%)}
.bk-hero>*{position:relative;z-index:1}
.bk-hero .hero-sub{font-size:.78rem;color:var(--ink2)}
.bk-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 14px;height:100%}
.bk-stat .v{font-size:1.45rem;font-weight:700;line-height:1.15;font-variant-numeric:tabular-nums}
.bk-stat .l{font-size:.7rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.bk-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
.bk-filter{padding:14px 16px;border-bottom:1px solid var(--line);background:#fff}
.bk-filter label{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:3px;display:block}
.bk-filter .form-control,.bk-filter .form-select{font-size:.82rem;border-radius:9px}
.btn-gold{background:var(--gold);border:1px solid var(--gold);color:#fff;font-weight:600;font-size:.8rem;border-radius:9px}
.btn-gold:hover{background:#a5833a;border-color:#a5833a;color:#fff}
.bk-tbl{width:100%;margin:0;font-size:.82rem}
.bk-tbl thead th{background:#fafbfc;color:var(--muted);font-weight:700;font-size:.68rem;text-transform:uppercase;
    letter-spacing:.4px;border-bottom:1px solid var(--line)!important;white-space:nowrap;padding:10px 12px}
.bk-tbl thead th a{color:var(--muted);text-decoration:none}
.bk-tbl thead th a:hover{color:var(--ink)}
.bk-tbl tbody td{padding:10px 12px;border-top:1px solid #f0f2f5;vertical-align:middle}
.bk-tbl tbody tr:first-child td{border-top:0}
/* คอลัมน์ วัน/วันที่ ทำเป็น "ราง" ด้านซ้าย มีเส้นคั่นแนวตั้ง */
.bk-tbl tbody td:nth-child(2){border-right:1px solid #eef0f3}
/* หลายรายการในวันเดียวกัน — เอาเส้นคั่นแนวนอนระหว่างแถวออก ให้อ่านเป็นบล็อกวันเดียว */
.bk-tbl tbody tr.bk-samedate td{border-top:0}
.bk-tbl tbody tr:hover{background:#f8f9fb}
.bk-tbl tbody tr.is-cancelled{color:var(--muted)}
.bk-tbl tbody tr.is-cancelled .bk-name{text-decoration:line-through}
.bk-tbl .num{font-variant-numeric:tabular-nums}
.bk-code{font-family:'Inter',monospace;font-size:.74rem;font-weight:700;color:#8a6c22;background:var(--gold-tint);
    border-radius:6px;padding:2px 7px;white-space:nowrap}
.bk-name{font-weight:600}
.tag{display:inline-block;background:#f1f3f6;color:var(--ink2);border-radius:6px;padding:1px 7px;font-size:.7rem;font-weight:600}
.pill{display:inline-flex;align-items:center;gap:4px;border-radius:99px;padding:2px 9px;font-size:.7rem;font-weight:700;white-space:nowrap}
.pill.on{background:#e9f7e9;color:#0ca30c}
.pill.off{background:#fbeaea;color:#d03b3b}
.pill.done{background:#eef1f6;color:#5b6470}
.bk-empty{padding:52px 20px;text-align:center;color:var(--muted)}
.chipbar{display:flex;gap:6px;flex-wrap:wrap}
.chipbar a{border:1px solid var(--line);background:#fff;color:var(--ink2);border-radius:999px;
    padding:5px 14px;font-size:.78rem;font-weight:600;text-decoration:none}
.chipbar a:hover{border-color:#d3d7dd;color:var(--ink)}
.chipbar a.on{background:var(--gold-tint);border-color:var(--gold);color:#8a6c22}
@media(max-width:575px){.bk-stat .v{font-size:1.2rem}}

/* ===== checklist เลือกแหล่งข้อมูล (จองห้อง / ใบเสนอราคา / EO) ===== */
.bk-srcbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 12px;background:#fafbfc;border:1px solid var(--line);border-radius:10px}
.bk-srcbar-label{font-size:.7rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--muted)}
.bk-srcchk{display:inline-flex;align-items:center;gap:6px;cursor:pointer;user-select:none;
    border:1px solid var(--line);background:#fff;color:var(--ink2);border-radius:999px;padding:4px 12px;font-size:.78rem;font-weight:600}
.bk-srcchk input{margin:0;cursor:pointer}
.bk-srcchk.on{border-color:currentColor}
.bk-srcchk.src-booking.on{color:#8a6c22;background:var(--gold-tint)}
.bk-srcchk.src-quote.on{color:#1d6fb8;background:#eaf3fb}
.bk-srcchk.src-eo.on{color:#0ca30c;background:#e9f7e9}

/* ป้ายแหล่งข้อมูลในแต่ละแถวของตาราง */
.src-tag{display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:1px 7px;font-size:.66rem;font-weight:700;margin-bottom:2px;text-transform:uppercase;letter-spacing:.3px}
.src-tag.src-booking{background:var(--gold-tint);color:#8a6c22}
.src-tag.src-quote{background:#eaf3fb;color:#1d6fb8}
.src-tag.src-eo{background:#e9f7e9;color:#0ca30c}

/* ===== มุมมองรายวัน (อารมณ์ปฏิทินแนวตั้ง) ===== */
.bk-agenda-nav{border-bottom:1px solid var(--line);background:#fafbfc}
.bk-tbl tbody tr.bk-empty-day td{color:var(--muted);background:#fcfcfd}
.bk-tbl tbody tr.bk-empty-day.is-today{background:var(--gold-tint)}
.bk-tbl tbody tr.bk-empty-day.is-today td{color:#8a6c22}
</style>

<div class="bk-page">

    <!-- ===== HERO ===== -->
    <div class="bk-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-journal-bookmark-fill me-2 text-gold"></i>รายการจองห้อง / ใบเสนอราคา</h5>
                <div class="hero-sub">
                    รวมการจองห้อง ใบเสนอราคา และ EO — เลือกแหล่งข้อมูลที่ต้องการดูได้จากช่อง "แสดงรายการ"
                    <?php if ($sum_total > 0): ?>· พบ <?= number_format($sum_total) ?> รายการ<?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <div class="chipbar">
                    <?php $vqs = $_GET; unset($vqs['export']); $vqs['view'] = 'table'; ?>
                    <a href="?<?= $h(http_build_query($vqs)) ?>" class="<?= $view === 'table' ? 'on' : '' ?>"><i class="bi bi-table me-1"></i>ตาราง</a>
                    <?php $vqs['view'] = 'agenda'; ?>
                    <a href="?<?= $h(http_build_query($vqs)) ?>" class="<?= $view === 'agenda' ? 'on' : '' ?>"><i class="bi bi-calendar3 me-1"></i>รายวัน</a>
                </div>
                <a href="<?= $h('?' . http_build_query($export_qs)) ?>" class="btn btn-gold btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>ส่งออก Excel
                </a>
                <a href="room_calendar.php" class="btn btn-outline-secondary btn-sm" style="font-size:.8rem;border-radius:9px">
                    <i class="bi bi-calendar-plus me-1"></i>จองห้องใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- ===== STAT ===== -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ทั้งหมด</div><div class="v"><?= number_format($sum_total) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ใช้งานอยู่</div><div class="v" style="color:#0ca30c"><?= number_format($sum_active) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ยกเลิกแล้ว</div><div class="v" style="color:#d03b3b"><?= number_format($sum_cancelled) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ยังไม่ถึงวันจัด</div><div class="v" style="color:#2a78d6"><?= number_format($sum_upcoming) ?></div>
                <div style="font-size:.7rem;color:#8a9099">รวม <?= number_format($sum_pax) ?> คน</div></div>
        </div>
    </div>

    <div class="bk-card">
        <!-- ===== FILTER ===== -->
        <form method="GET" class="bk-filter" id="bkFilter">
            <input type="hidden" name="sort" value="<?= $h($sort) ?>">
            <input type="hidden" name="dir" value="<?= $h(strtolower($dir)) ?>">
            <input type="hidden" name="view" value="<?= $h($view) ?>">
            <input type="hidden" name="status" value="<?= $h($f_status) ?>">
            <?php if ($view === 'agenda'): ?><input type="hidden" name="month" value="<?= $h($agenda_month) ?>"><?php endif; ?>
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label>ค้นหา</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= $h($q) ?>" class="form-control"
                               placeholder="รหัสจอง / ชื่องาน / ผู้จอง / เบอร์โทร">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>ห้องประชุม</label>
                    <select name="room_id" class="form-select form-select-sm">
                        <option value="0">ทุกห้อง</option>
                        <?php foreach ($rooms_opt as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $f_room == $r['id'] ? 'selected' : '' ?>><?= $h($r['room_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>โรงแรม</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach ($comp_opt as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $f_company == $c['id'] ? 'selected' : '' ?>><?= $h($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>ประเภทงาน</label>
                    <select name="type_id" class="form-select form-select-sm">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach ($type_opt as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $f_type == $t['id'] ? 'selected' : '' ?>><?= $h($t['type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-12">
                    <label>ช่วงวันจัดงาน</label>
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" name="from" value="<?= $h($date_from) ?>" class="form-control form-control-sm">
                        <span class="text-muted small">ถึง</span>
                        <input type="date" name="to" value="<?= $h($date_to) ?>" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="bk-srcbar mt-3">
                <span class="bk-srcbar-label"><i class="bi bi-collection me-1"></i>แสดงรายการ</span>
                <?php
                $src_opts = [
                    'booking' => ['ค' => $show_booking, 'ic' => 'bi-journal-bookmark',   'lb' => 'จองห้อง'],
                    'quote'   => ['ค' => $show_quote,   'ic' => 'bi-file-earmark-text', 'lb' => 'ใบเสนอราคา'],
                    'eo'      => ['ค' => $show_eo,      'ic' => 'bi-calendar-check',    'lb' => 'EO'],
                ];
                foreach ($src_opts as $sk => $so): ?>
                    <label class="bk-srcchk src-<?= $sk ?> <?= $so['ค'] ? 'on' : '' ?>">
                        <input type="checkbox" name="src[]" value="<?= $sk ?>" <?= $so['ค'] ? 'checked' : '' ?>
                               onchange="document.getElementById('bkFilter').submit()">
                        <i class="bi <?= $so['ic'] ?>"></i><?= $so['lb'] ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="chipbar">
                    <?php
                    $status_tabs = ['all' => 'ทั้งหมด', 'active' => 'ใช้งานอยู่', 'cancelled' => 'ยกเลิกแล้ว'];
                    foreach ($status_tabs as $k => $lb):
                        $qs = $_GET; $qs['status'] = $k; unset($qs['export']);
                    ?>
                        <a href="?<?= $h(http_build_query($qs)) ?>" class="<?= $f_status === $k ? 'on' : '' ?>"><?= $lb ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-gold btn-sm px-3"><i class="bi bi-funnel me-1"></i>กรองข้อมูล</button>
                    <a href="booking_list.php" class="btn btn-outline-secondary btn-sm" style="font-size:.8rem;border-radius:9px">ล้างตัวกรอง</a>
                </div>
            </div>
        </form>

        <!-- ===== TABLE / รายวัน — ใช้ตารางแบบเดียวกันเป๊ะๆ ต่างกันแค่มุมมองรายวันขึ้นครบทุกวัน (แม้วันว่าง) ===== -->
        <?php if ($view === 'agenda'): ?>
            <?php
                $agenda_qs_base = $_GET; unset($agenda_qs_base['export']); $agenda_qs_base['view'] = 'agenda';
                $agenda_qs_prev = $agenda_qs_base; $agenda_qs_prev['month'] = $agenda_prev;
                $agenda_qs_next = $agenda_qs_base; $agenda_qs_next['month'] = $agenda_next;
                $thai_months_full = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 bk-agenda-nav">
                <a href="?<?= $h(http_build_query($agenda_qs_prev)) ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:9px"><i class="bi bi-chevron-left"></i></a>
                <div class="fw-bold"><?= $thai_months_full[$agenda_mon] ?> <?= $agenda_year + 543 ?></div>
                <a href="?<?= $h(http_build_query($agenda_qs_next)) ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:9px"><i class="bi bi-chevron-right"></i></a>
            </div>
        <?php elseif ($sum_total === 0): ?>
            <div class="bk-empty">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                ไม่พบรายการจองที่ตรงกับเงื่อนไข
                <div class="mt-2"><a href="booking_list.php" class="btn btn-sm btn-outline-secondary">ล้างตัวกรอง</a></div>
            </div>
        <?php endif; ?>

        <?php if ($view === 'agenda' || $sum_total > 0): ?>
            <div class="table-responsive">
                <table class="bk-tbl">
                    <thead>
                        <tr>
                            <th>วัน</th>
                            <th><a href="<?= $h(sort_link('start_time', $sort, $dir)) ?>">วันที่ <?= sort_icon('start_time', $sort, $dir) ?></a></th>
                            <th><a href="<?= $h(sort_link('organization', $sort, $dir)) ?>">หน่วยงาน / ผู้ติดต่อ <?= sort_icon('organization', $sort, $dir) ?></a></th>
                            <th>เบอร์โทร</th>
                            <th><a href="<?= $h(sort_link('room_name', $sort, $dir)) ?>">ห้องประชุม <?= sort_icon('room_name', $sort, $dir) ?></a></th>
                            <th>เวลา</th>
                            <th><a href="<?= $h(sort_link('type_name', $sort, $dir)) ?>">ประเภทงาน <?= sort_icon('type_name', $sort, $dir) ?></a></th>
                            <th class="text-center"><a href="<?= $h(sort_link('pax', $sort, $dir)) ?>">จำนวน <?= sort_icon('pax', $sort, $dir) ?></a></th>
                            <th>เบรก</th>
                            <th>ประเภทอาหาร</th>
                            <th>ห้องพัก</th>
                            <th><a href="<?= $h(sort_link('created_at', $sort, $dir)) ?>">วันที่จอง <?= sort_icon('created_at', $sort, $dir) ?></a></th>
                            <th>ผู้รับงาน</th>
                            <th class="text-end">ราคาขาย</th>
                            <th>สถานะ</th>
                            <th class="text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($view === 'agenda'): ?>
                            <?php for ($d = 1; $d <= $days_in_month; $d++):
                                $day_ts = mktime(0, 0, 0, $agenda_mon, $d, $agenda_year);
                                $day_items = $agenda_by_day[$d];
                                if (empty($day_items)) {
                                    echo render_empty_day_row($day_ts, date('Y-m-d', $day_ts) === $today_key);
                                } else {
                                    // วันเดียวกันหลายรายการ — โชว์ วัน/วันที่ แค่รายการแรกของวันนั้น ที่เหลือเว้นว่าง
                                    foreach ($day_items as $idx => $it) echo render_booking_row($it, $h, $can_cancel, $day_ts, $idx > 0);
                                }
                            endfor; ?>
                        <?php else: ?>
                            <?php
                            // ถ้าหลายรายการตกวันเดียวกัน (เช่น 12 ส.ค. 2569 มี 2 งาน) ให้โชว์คอลัมน์ วัน/วันที่
                            // แค่แถวแรกของวันนั้น แถวถัดไปเว้นว่างไว้ ให้อ่านเป็นบล็อกวันเดียวกัน
                            // จัดกลุ่มตามวันเฉพาะตอนเรียงด้วย "วันที่" เท่านั้น (เรียงคอลัมน์อื่นแถววันเดียวกันจะไม่ติดกัน)
                            $group_by_date = ($sort === 'start_time');
                            $prev_date_key = null;
                            foreach ($rows as $r):
                                $rdk = !empty($r['start_time']) ? date('Y-m-d', strtotime($r['start_time'])) : '';
                                $hide_date = $group_by_date && $rdk !== '' && ($rdk === $prev_date_key);
                                $prev_date_key = $rdk;
                                echo render_booking_row($r, $h, $can_cancel, null, $hide_date);
                            endforeach;
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-top:1px solid #e8eaee;font-size:.76rem;color:#5b6470">
                <?php if ($view === 'agenda'): ?>
                    <?php
                        // นับจำนวน "รายการจองที่ไม่ซ้ำ" ไม่ใช่จำนวนแถว เพราะงานคร่อมหลายวันจะถูกแสดงซ้ำในทุกวันที่ครอบคลุม
                        $agenda_unique_ids = [];
                        foreach ($agenda_by_day as $day_items) foreach ($day_items as $it) $agenda_unique_ids[($it['_src'] ?? 'booking') . '_' . $it['id']] = true;
                    ?>
                    <span>เดือนนี้มี <b><?= number_format(count($agenda_unique_ids)) ?></b> รายการ</span>
                <?php else: ?>
                    <span>แสดง <b><?= number_format($sum_total) ?></b> รายการ</span>
                    <span class="text-muted">คลิกหัวตารางเพื่อเรียงลำดับ</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL: รายละเอียด ===== -->
<div class="modal fade" id="bkDetail" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden">
            <div class="modal-header py-2" style="background:#16181d;color:#fff;border:0">
                <h6 class="modal-title fw-bold" style="font-size:.9rem">
                    <i class="bi bi-journal-text me-2" style="color:#b89441"></i><span id="bkDetailTitle">รายละเอียดการจอง</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bkDetailBody"></div>
        </div>
    </div>
</div>

<!-- ===== MODAL: ยืนยันยกเลิก ===== -->
<div class="modal fade" id="bkCancel" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:0;border-radius:16px">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill fs-1" style="color:#fab219"></i>
                <h6 class="fw-bold mt-3 mb-1">ยืนยันยกเลิกการจอง?</h6>
                <p class="text-muted mb-1" style="font-size:.82rem" id="bkCancelName"></p>
                <p class="text-muted mb-3" style="font-size:.76rem">ห้องจะถูกปล่อยว่างให้จองใหม่ได้ทันที</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill btn-sm" data-bs-dismiss="modal">ไม่ใช่ตอนนี้</button>
                    <button type="button" class="btn btn-danger flex-fill btn-sm" id="bkCancelConfirm">ยกเลิกการจอง</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BK_ROWS = <?= json_encode(array_column(array_filter($rows, fn($r) => ($r['_src'] ?? 'booking') === 'booking'), null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const BK_CAN_CANCEL = <?= $can_cancel ? 'true' : 'false' ?>;

const esc = s => String(s ?? '').replace(/[&<>"']/g, m =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

const THAI_M = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thaiDT(s) {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${THAI_M[d.getMonth()]} ${d.getFullYear() + 543} ${hh}:${mm}`;
}

/* ---------- รายละเอียด (ใช้ร่วมกันทั้งปุ่มในตาราง และการ์ดในมุมมองรายวัน) ---------- */
function showBkDetail(id) {
    const r = BK_ROWS[id];
    if (!r) return;
    document.getElementById('bkDetailTitle').textContent = r.booking_code || 'รายละเอียดการจอง';

    const isCancelled = r.status !== 'active';
    const field = (label, val, wide) => `
        <div class="${wide ? 'col-12' : 'col-sm-6'} mb-3">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#8a9099;font-weight:700">${label}</div>
            <div style="font-size:.88rem">${val && String(val).trim() !== '' ? esc(val) : '<span class="text-muted">-</span>'}</div>
        </div>`;

    document.getElementById('bkDetailBody').innerHTML = `
        <div class="d-flex align-items-center gap-2 mb-3 pb-3" style="border-bottom:1px solid #e8eaee">
            <span class="pill ${isCancelled ? 'off' : 'on'}">
                <i class="bi ${isCancelled ? 'bi-x-circle-fill' : 'bi-check-circle-fill'}"></i>
                ${isCancelled ? 'ยกเลิกแล้ว' : 'ใช้งานอยู่'}
            </span>
            <span class="fw-bold">${esc(r.event_name || '(ไม่ระบุชื่องาน)')}</span>
        </div>
        <div class="row">
            ${field('ห้องประชุม', r.room_name)}
            ${field('โรงแรม', r.company_name)}
            ${field('เริ่มงาน', thaiDT(r.start_time))}
            ${field('สิ้นสุด', thaiDT(r.end_time))}
            ${field('ประเภทงาน', r.type_name)}
            ${field('จำนวนคน', r.pax > 0 ? Number(r.pax).toLocaleString('th-TH') + ' คน' : '')}
            ${field('ผู้จอง', r.booking_name)}
            ${field('ลูกค้าในระบบ', r.customer_id ? (r.system_cust_name || '(ลูกค้า #' + r.customer_id + ')') : '')}
            ${field('เบอร์โทร', r.phone)}
            ${field('หน่วยงาน', r.organization, true)}
            ${field('หมายเหตุ', r.remark, true)}
            ${field('ผู้บันทึก', r.created_by)}
            ${field('บันทึกเมื่อ', thaiDT(r.created_at))}
        </div>`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('bkDetail')).show();
}

document.querySelectorAll('.btn-detail').forEach(el => {
    el.addEventListener('click', () => showBkDetail(el.dataset.id));
});

/* ---------- ยกเลิกการจอง ---------- */
let bkPendingId = null;
document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', () => {
        bkPendingId = btn.dataset.id;
        document.getElementById('bkCancelName').textContent = btn.dataset.name || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bkCancel')).show();
    });
});

document.getElementById('bkCancelConfirm')?.addEventListener('click', function () {
    if (!bkPendingId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('id', bkPendingId);

    fetch('booking_list.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message || 'ยกเลิกไม่สำเร็จ');
                btn.disabled = false;
                btn.textContent = 'ยกเลิกการจอง';
            }
        })
        .catch(() => {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            btn.disabled = false;
            btn.textContent = 'ยกเลิกการจอง';
        });
});
</script>

<?php include "footer.php"; ?>
