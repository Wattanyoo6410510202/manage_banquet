<?php
/* =========================================================
   api/public_calendar_events.php — JSON feed สำหรับปฏิทินสาธารณะ
   รับ ?start= &end= ดึงเฉพาะงานในกรอบที่มองเห็น (ไม่ต้องล็อกอิน)
   ========================================================= */
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json; charset=UTF-8');

/* ขยายขอบเขตออก 14 วันจับ event ที่ยืดยาวหลายวัน */
$pad = 14 * 86400;
$range_start = date('Y-m-d H:i:s', (strtotime($_GET['start'] ?? '-1 month') ?: time()) - $pad);
$range_end   = date('Y-m-d H:i:s', (strtotime($_GET['end']   ?? '+2 month') ?: time()) + $pad);

$es = $conn->real_escape_string($range_start);
$ee = $conn->real_escape_string($range_end);

$events = [];

/* ---------- 1. งานจากตาราง functions ---------- */
$q = $conn->query("SELECT f.*, r.room_name, c.cust_name, c.cust_phone, comp.company_name, ft.type_name
    FROM functions f LEFT JOIN meeting_rooms r ON f.room_id=r.id LEFT JOIN customers c ON f.customer_id=c.id
    LEFT JOIN companies comp ON f.company_id=comp.id LEFT JOIN function_types ft ON f.function_type_id=ft.id
    WHERE f.status!='Cancelled'
      AND f.start_time >= '$es' AND f.start_time < '$ee'
    ORDER BY f.id ASC");
if ($q) while ($r = $q->fetch_assoc()) {
    $s = strtolower(trim($r['status'] ?? ''));
    $cl = $s === 'pending' ? '#ffc107' : ($s === 'confirmed' || $s === 'approved' ? '#0dcaf0' : ($s === 'in progress' ? '#0d6efd' : ($s === 'completed' ? '#198754' : '#6c757d')));
    $pe = '';
    if (!empty($r['start_time'])) { $h = (int)date('H', strtotime($r['start_time'])); $pe = $h < 12 ? 'เช้า' : ($h < 17 ? 'บ่าย' : 'เย็น'); }
    $events[] = ['id' => 'gen_' . $r['id'], 'title' => (string)($r['function_name'] ?? ''), 'start' => (string)($r['start_time'] ?? ''), 'end' => (string)($r['end_time'] ?? ''), 'color' => $cl, 'extendedProps' => [
        'mainTitle' => (string)($r['function_name'] ?? ''), 'status' => (string)($r['status'] ?? 'Pending'), 'room' => (string)($r['room_name'] ?? ''),
        'customer' => (string)($r['cust_name'] ?? ''), 'phone' => (string)($r['cust_phone'] ?? ''), 'pax' => (string)($r['pax'] ?? '0'),
        'deposit' => $r['deposit'] ?? 0, 'total' => $r['total_amount'] ?? 0, 'period' => $pe,
        'function_type' => (string)($r['type_name'] ?? $r['function_type'] ?? ''), 'company' => (string)($r['company_name'] ?? ''),
        'room_id' => (string)($r['room_id'] ?? ''),
    ]];
}

/* ---------- 2. ใบเสนอราคา ---------- */
$q2 = $conn->query("SELECT q.*, c.cust_name, c.cust_phone FROM quotations q LEFT JOIN customers c ON q.customer_id=c.id
    WHERE q.status NOT IN ('Cancelled')
      AND q.event_date >= '$es' AND q.event_date < '$ee'
    ORDER BY q.id DESC");
if ($q2) while ($r = $q2->fetch_assoc()) {
    $s = strtolower(trim($r['status'] ?? ''));
    $cl = $s === 'draft' || $s === 'pending' ? '#6c757d' : '#fd7e14';
    $st = $s === 'draft' || $s === 'pending' ? 'QT (Draft)' : 'QT (อนุมัติ)';
    $evd = $r['event_date'] ?? ''; $exd = !empty($r['expiry_date']) ? $r['expiry_date'] : ''; $ed = '';
    if ($exd && $exd !== $evd) $ed = date('Y-m-d', strtotime($exd . ' +1 day'));
    $ev = ['id' => 'qt_' . $r['id'], 'title' => '[' . ($r['quote_no'] ?? '') . '] ' . ($r['event_name'] ?? ''), 'start' => $evd, 'color' => $cl,
        'extendedProps' => ['mainTitle' => (string)($r['event_name'] ?? ''), 'status' => $st, 'customer' => (string)($r['cust_name'] ?? ''),
        'phone' => (string)($r['cust_phone'] ?? ''), 'total' => $r['grand_total'] ?? 0, 'period' => '', 'room' => '', 'pax' => '0',
        'company' => '', 'function_type' => '', 'deposit' => 0]];
    if ($ed) $ev['end'] = $ed;
    $events[] = $ev;
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
