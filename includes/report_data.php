<?php
/**
 * ชั้นข้อมูลกลางของ Dashboard และหน้ารายงาน — นิยามตัวเลขทุกตัวไว้ที่นี่ที่เดียว
 * เพื่อให้สองหน้าแสดงตัวเลขตรงกันเสมอ
 *
 * นิยามที่ใช้ (ตรวจจากข้อมูลจริงในตาราง functions):
 *   approve = 1  งานยืนยันแล้ว (Confirmed / In Progress / Completed)  → นับเป็นรายได้
 *   approve = 2  งานยกเลิก (approve_event.php ตั้งค่า 2 ตอนยกเลิก ไม่ใช่ 1)
 *   approve = 0  งานรออนุมัติ — หนึ่งโปรเจกต์อาจมีหลาย draft จึงนับโปรเจกต์ละ 1
 *                และไม่นับ draft ของโปรเจกต์ที่มีฉบับอนุมัติไปแล้ว
 *   วันที่ของงาน = start_time (วันจัดงาน)
 *   มัดจำที่รับ = function_finance.type='deposit' ทุกขั้น (ก่อน/หลังอนุมัติ/จากใบเสนอราคา)
 *                ตามวันที่รับเงิน (transaction_date)
 *   quotations.status='Approved' แปลว่าผู้จัดการอนุมัติเอกสาร ไม่ได้แปลว่าลูกค้าซื้อ
 *                → "ปิดการขายได้" ดูจากการที่ใบเสนอราคากลายเป็นงานที่ยืนยันแล้ว
 */

date_default_timezone_set('Asia/Bangkok');

const RPT_TH_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const RPT_TH_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const RPT_TH_DAYS = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

/** วันที่แบบไทย เช่น 29 ต.ค. 2569 */
function rpt_th_date($ymd, $withYear = true)
{
    if (!$ymd) return '-';
    $t = strtotime($ymd);
    return (int) date('j', $t) . ' ' . RPT_TH_MONTHS_SHORT[(int) date('n', $t)]
        . ($withYear ? ' ' . (date('Y', $t) + 543) : '');
}

/** เงื่อนไขกรองโรงแรม/เซลล์ (ค่าผ่าน intval แล้ว) */
function rpt_scope($company, $staff, $a = 'f')
{
    $s = '';
    if ($company > 0) $s .= " AND $a.company_id = " . (int) $company;
    if ($staff > 0) $s .= " AND $a.created_by_id = " . (int) $staff;
    return $s;
}

/**
 * งานทั้งหมดที่วันจัดงานอยู่ในช่วง [from, to) แยกเป็น confirmed / cancelled / pending
 */
function rpt_events($conn, $from, $to, $company = 0, $staff = 0)
{
    $sql = "SELECT f.id, f.project_id, f.function_code, f.function_name, f.start_time, f.end_time,
                   f.approve, f.status, COALESCE(f.pax,0) AS pax, COALESCE(f.total_amount,0) AS total,
                   COALESCE(f.deposit,0) AS deposit_field, f.room_id, f.created_by_id,
                   COALESCE(ft.type_name, 'ไม่ระบุประเภท') AS type_name,
                   COALESCE(c.company_name, '-') AS company_name,
                   COALESCE(mr.room_name, NULLIF(f.booking_room,''), NULLIF(f.room_name,''), '-') AS room_name,
                   COALESCE(u.name, NULLIF(f.created_by,''), '-') AS staff_name,
                   COALESCE(NULLIF(cu.cust_name,''), NULLIF(f.organization,''), NULLIF(f.booking_name,''), '-') AS customer_name,
                   COALESCE(fd.paid, 0) AS deposit_paid,
                   (f.approve = 0 AND f.project_id IS NOT NULL AND EXISTS(
                        SELECT 1 FROM functions x WHERE x.project_id = f.project_id AND x.approve = 1)) AS superseded
            FROM functions f
            LEFT JOIN function_types ft ON ft.id = f.function_type_id
            LEFT JOIN companies c ON c.id = f.company_id
            LEFT JOIN meeting_rooms mr ON mr.id = f.room_id
            LEFT JOIN users u ON u.id = f.created_by_id
            LEFT JOIN customers cu ON cu.id = f.customer_id
            LEFT JOIN (SELECT function_id, SUM(amount) AS paid FROM function_finance
                       WHERE type = 'deposit' GROUP BY function_id) fd ON fd.function_id = f.id
            WHERE f.start_time >= '$from' AND f.start_time < '$to'" . rpt_scope($company, $staff) . "
            ORDER BY f.start_time ASC, f.id ASC";
    $out = ['confirmed' => [], 'cancelled' => [], 'pending' => []];
    $pending_by_project = [];
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) {
        $r = rpt_shape_event($r);
        if ((int) $r['approve'] === 1) {
            $out['confirmed'][] = $r;
        } elseif ((int) $r['approve'] === 2 || $r['status'] === 'Cancelled') {
            $out['cancelled'][] = $r;
        } elseif (!$r['superseded']) {
            // เก็บ draft ล่าสุดของแต่ละโปรเจกต์
            $key = $r['project_id'] ? 'p' . $r['project_id'] : 'f' . $r['id'];
            if (!isset($pending_by_project[$key]) || $r['id'] > $pending_by_project[$key]['id']) {
                $pending_by_project[$key] = $r;
            }
        }
    }
    $out['pending'] = array_values($pending_by_project);
    usort($out['pending'], fn($a, $b) => strcmp($a['start'], $b['start']));
    return $out;
}

/** แปลงแถวให้พร้อมใช้ทั้งใน PHP และ JSON ที่ส่งให้หน้าเว็บ */
function rpt_shape_event($r)
{
    $st = strtotime($r['start_time']);
    $en = $r['end_time'] ? strtotime($r['end_time']) : null;
    $paid = (float) $r['deposit_paid'];
    $field = (float) $r['deposit_field'];
    return [
        'id' => (int) $r['id'],
        'project_id' => $r['project_id'] ? (int) $r['project_id'] : null,
        'code' => $r['function_code'],
        'name' => trim($r['function_name']),
        'start' => $r['start_time'],
        'date' => date('Y-m-d', $st),
        'time' => date('H:i', $st) . ($en ? '–' . date('H:i', $en) : ''),
        'approve' => (int) $r['approve'],
        'status' => $r['status'],
        'pax' => (int) $r['pax'],
        'total' => (float) $r['total'],
        // มัดจำ: ใช้ยอดที่บันทึกรับเงินจริงก่อน ถ้าไม่มีใช้ช่อง "มัดจำ" ในฟอร์มงาน
        'deposit' => $paid > 0 ? $paid : $field,
        'room_id' => $r['room_id'] ? (int) $r['room_id'] : null,
        'staff_id' => $r['created_by_id'] ? (int) $r['created_by_id'] : null,
        'type' => $r['type_name'],
        'company' => $r['company_name'],
        'room' => $r['room_name'],
        'staff' => $r['staff_name'],
        'customer' => $r['customer_name'],
        'superseded' => (bool) $r['superseded'],
    ];
}

/** สรุป จำนวน / คน / มูลค่า ของชุดงาน */
function rpt_sum(array $rows)
{
    return [
        'count' => count($rows),
        'pax' => array_sum(array_column($rows, 'pax')),
        'revenue' => array_sum(array_column($rows, 'total')),
    ];
}

/** จัดกลุ่มงานตามฟิลด์ คืน [[label, count, pax, revenue], ...] เรียงรายได้มาก→น้อย */
function rpt_group(array $rows, $field)
{
    $g = [];
    foreach ($rows as $r) {
        $k = $r[$field] ?: '-';
        if (!isset($g[$k])) $g[$k] = ['label' => $k, 'count' => 0, 'pax' => 0, 'revenue' => 0.0];
        $g[$k]['count']++;
        $g[$k]['pax'] += $r['pax'];
        $g[$k]['revenue'] += $r['total'];
    }
    $g = array_values($g);
    usort($g, fn($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['count'] <=> $a['count']);
    return $g;
}

/** เงินมัดจำที่รับเข้ามาจริงในช่วง [from, to) ตามวันที่รับเงิน */
function rpt_deposits($conn, $from, $to, $company = 0, $staff = 0)
{
    $scope = '';
    if ($company > 0) $scope .= " AND COALESCE(f.company_id, q.company_id) = " . (int) $company;
    if ($staff > 0) $scope .= " AND COALESCE(f.created_by_id, q.created_by) = " . (int) $staff;
    $res = $conn->query("SELECT ff.id, ff.amount, ff.transaction_date, COALESCE(NULLIF(ff.payment_method,''),'ไม่ระบุ') AS method,
                                ff.detail, ff.function_id, ff.quotation_id,
                                COALESCE(f.function_name, q.event_name, '-') AS name,
                                COALESCE(c.company_name, '-') AS company_name, f.approve
                         FROM function_finance ff
                         LEFT JOIN functions f ON f.id = ff.function_id
                         LEFT JOIN quotations q ON q.id = ff.quotation_id
                         LEFT JOIN companies c ON c.id = COALESCE(f.company_id, q.company_id)
                         WHERE ff.type = 'deposit' AND ff.transaction_date >= '$from' AND ff.transaction_date < '$to' $scope
                         ORDER BY ff.transaction_date ASC, ff.id ASC");
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => $r['function_id'] ? (int) $r['function_id'] : null,
            'qid' => $r['quotation_id'] ? (int) $r['quotation_id'] : null,
            'date' => $r['transaction_date'],
            'name' => trim($r['name']),
            'company' => $r['company_name'],
            'detail' => $r['detail'],
            'method' => $r['method'],
            'amount' => (float) $r['amount'],
            'cancelled' => (int) $r['approve'] === 2,
        ];
    }
    return $rows;
}

/** เป้ายอดขายรวม (เดือนเดียว หรือทั้งปีถ้า $month = 0) */
function rpt_target($conn, $year, $month = 0, $staff = 0)
{
    $w = "target_year = " . (int) $year;
    if ($month > 0) $w .= " AND target_month = " . (int) $month;
    if ($staff > 0) $w .= " AND user_id = " . (int) $staff;
    return (float) $conn->query("SELECT COALESCE(SUM(target_amount),0) t FROM sales_targets WHERE $w")->fetch_assoc()['t'];
}

/** เป้าแยกรายคน [user_id => amount] */
function rpt_target_by_user($conn, $year, $month = 0)
{
    $w = "target_year = " . (int) $year . ($month > 0 ? " AND target_month = " . (int) $month : '');
    $out = [];
    $res = $conn->query("SELECT user_id, SUM(target_amount) t FROM sales_targets WHERE $w GROUP BY user_id");
    while ($r = $res->fetch_assoc()) $out[(int) $r['user_id']] = (float) $r['t'];
    return $out;
}

/** เป้ารายเดือนของทั้งปี [1..12 => amount] */
function rpt_target_by_month($conn, $year, $staff = 0)
{
    $out = array_fill(1, 12, 0.0);
    $w = "target_year = " . (int) $year . ($staff > 0 ? " AND user_id = " . (int) $staff : '');
    $res = $conn->query("SELECT target_month m, SUM(target_amount) t FROM sales_targets WHERE $w GROUP BY target_month");
    while ($r = $res->fetch_assoc()) $out[(int) $r['m']] = (float) $r['t'];
    return $out;
}

/**
 * ใบเสนอราคาจัดกลุ่มเป็น "ดีล" (โปรเจกต์ละ 1 ดีล — ใบแก้ไขหลายเวอร์ชันนับเป็นดีลเดียว)
 * state: won = มีงานยืนยันแล้ว, eo = เปิดงานแล้วรออนุมัติ, lost = งานถูกยกเลิก/ใบถูกยกเลิก, open = ยังไม่เปิดงาน
 */
function rpt_deals($conn, $company = 0, $staff = 0)
{
    $scope = '';
    if ($company > 0) $scope .= " AND q.company_id = " . (int) $company;
    if ($staff > 0) $scope .= " AND q.created_by = " . (int) $staff;
    $res = $conn->query("SELECT q.id, q.project_id, q.quote_no, q.event_name, q.event_date, q.grand_total, q.status,
                                q.is_selected, q.created_at, COALESCE(c.company_name,'-') AS company_name,
                                COALESCE(u.name,'-') AS staff_name, COALESCE(NULLIF(cu.cust_name,''),'-') AS customer_name,
                                (SELECT MAX(CASE x.approve WHEN 1 THEN 3 WHEN 0 THEN 2 WHEN 2 THEN 1 END)
                                   FROM functions x
                                  WHERE (q.project_id IS NOT NULL AND x.project_id = q.project_id)
                                     OR x.quotation_id = q.id OR x.id = q.function_id) AS fstate
                         FROM quotations q
                         LEFT JOIN companies c ON c.id = q.company_id
                         LEFT JOIN users u ON u.id = q.created_by
                         LEFT JOIN customers cu ON cu.id = q.customer_id
                         WHERE 1=1 $scope
                         ORDER BY q.id ASC");
    $deals = [];
    while ($r = $res->fetch_assoc()) {
        $k = $r['project_id'] ? 'p' . $r['project_id'] : 'q' . $r['id'];
        $fs = (int) $r['fstate'];
        if (!isset($deals[$k])) {
            $deals[$k] = ['first_created' => $r['created_at'], 'fstate' => 0, 'versions' => 0, 'all_cancelled' => true, 'rep' => null];
        }
        $d = &$deals[$k];
        $d['versions']++;
        $d['fstate'] = max($d['fstate'], $fs);
        if ($r['status'] !== 'Cancelled') $d['all_cancelled'] = false;
        // ใบตัวแทนของดีล: ใบที่ถูกเลือก (is_selected) ก่อน ไม่งั้นใช้ใบล่าสุด
        if ($d['rep'] === null || $r['is_selected'] || !$d['rep']['is_selected']) $d['rep'] = $r;
        unset($d);
    }
    $out = [];
    foreach ($deals as $d) {
        $q = $d['rep'];
        $state = $d['fstate'] === 3 ? 'won' : ($d['fstate'] === 2 ? 'eo' : (($d['fstate'] === 1 || $d['all_cancelled']) ? 'lost' : 'open'));
        $out[] = [
            'id' => (int) $q['id'],
            'quote_no' => $q['quote_no'],
            'name' => trim($q['event_name'] ?: $q['quote_no']),
            'customer' => $q['customer_name'],
            'company' => $q['company_name'],
            'staff' => $q['staff_name'],
            'date' => $q['event_date'],
            'total' => (float) $q['grand_total'],
            'created' => substr($d['first_created'], 0, 10),
            'versions' => $d['versions'],
            'state' => $state,
        ];
    }
    return $out;
}

/** สรุปอัตราปิดการขายของดีลที่ "เริ่มเสนอราคา" ในช่วง [from, to) */
function rpt_conversion(array $deals, $from, $to)
{
    $in = array_values(array_filter($deals, fn($d) => $d['created'] >= $from && $d['created'] < $to));
    $won = array_values(array_filter($in, fn($d) => $d['state'] === 'won'));
    return [
        'deals' => $in,
        'quoted' => count($in),
        'quoted_value' => array_sum(array_column($in, 'total')),
        'won' => count($won),
        'won_value' => array_sum(array_column($won, 'total')),
        'rate' => count($in) > 0 ? round(count($won) / count($in) * 100) : 0,
    ];
}

/** ห้องเดียวกันถูกจองเวลาทับกัน (เฉพาะงานยืนยันแล้วที่ยังไม่ถึงวันจัด) */
function rpt_room_conflicts($conn, $company = 0, $staff = 0)
{
    $scope = rpt_scope($company, $staff, 'a');
    $res = $conn->query("SELECT a.id AS a_id, b.id AS b_id
                         FROM functions a
                         JOIN functions b ON b.room_id = a.room_id AND b.id > a.id AND b.approve = 1
                              AND b.start_time < COALESCE(a.end_time, DATE_ADD(a.start_time, INTERVAL 1 HOUR))
                              AND COALESCE(b.end_time, DATE_ADD(b.start_time, INTERVAL 1 HOUR)) > a.start_time
                         WHERE a.approve = 1 AND a.room_id IS NOT NULL AND a.room_id <> 0
                           AND a.start_time >= CURDATE() $scope");
    $ids = [];
    while ($r = $res->fetch_assoc()) {
        $ids[(int) $r['a_id']] = true;
        $ids[(int) $r['b_id']] = true;
    }
    return array_keys($ids);
}

/* =========================================================
   รายงานการขาย (ตามแบบฟอร์ม "ตารางควบคุมใบเสนอราคาและติดตามงานขายโรงแรม")
   ========================================================= */

/** สถานะตามแบบฟอร์ม → โอกาสปิดการขาย (%) ที่ระบบประเมินให้ (ระบบไม่มีช่องให้กรอกเอง) */
const RPT_SALE_PROB = [
    'จัดทำใบเสนอราคา' => 10, 'ส่งใบเสนอราคาแล้ว' => 30, 'รอติดตาม' => 40,
    'อยู่ระหว่างเจรจา/ปรับราคา' => 50, 'รออนุมัติจากลูกค้า' => 70,
    'ยืนยันงาน' => 100, 'แพ้การขาย' => 0, 'ยกเลิก' => 0,
];

/** แปลงสถานะในระบบ (สถานะงาน + workflow ของใบเสนอราคา) เป็นสถานะตามแบบฟอร์ม */
function rpt_sale_status($fstate, $workflow)
{
    if ($fstate === 3 || $fstate === 2) return 'ยืนยันงาน'; // เปิดงาน (EO) แล้ว
    if ($workflow === 'Lost Sale') return 'แพ้การขาย';
    if ($fstate === 1 || $workflow === 'Cancelled') return 'ยกเลิก';
    if (in_array($workflow, ['ลูกค้าเซ็นยืนยัน', 'รับเงินมัดจำแล้ว', 'เปิด Function (BEO)', 'Approved'], true)) return 'ยืนยันงาน';
    if ($workflow === 'ส่งใบเสนอราคาแล้ว') return 'ส่งใบเสนอราคาแล้ว';
    if (strpos((string) $workflow, 'Follow Up') === 0) return 'รอติดตาม';
    if (in_array($workflow, ['ลูกค้าต่อรองราคา', 'รออนุมัติส่วนลด', 'ส่งใบเสนอราคาใหม่ (Revision)'], true)) return 'อยู่ระหว่างเจรจา/ปรับราคา';
    if ($workflow === 'Freeze') return 'รออนุมัติจากลูกค้า';
    return 'จัดทำใบเสนอราคา';
}

/** กลุ่มสถานะใหญ่: open | won | lost */
function rpt_sale_group($status)
{
    return $status === 'ยืนยันงาน' ? 'won' : (in_array($status, ['แพ้การขาย', 'ยกเลิก'], true) ? 'lost' : 'open');
}

/**
 * หนึ่งแถวต่อหนึ่งดีล (ใบเสนอราคาฉบับที่เลือก/ฉบับล่าสุดของแต่ละโปรเจกต์)
 * กรองตามวันที่เริ่มเสนอราคา [from, to) — ส่ง null เพื่อเอาทั้งหมด
 */
function rpt_sales_rows($conn, $from = null, $to = null, $company = 0, $staff = 0)
{
    $scope = '';
    if ($company > 0) $scope .= " AND q.company_id = " . (int) $company;
    if ($staff > 0) $scope .= " AND q.created_by = " . (int) $staff;
    $res = $conn->query("SELECT q.*, COALESCE(c.company_name,'-') AS company_name, COALESCE(u.name,'ไม่ระบุ') AS staff_name,
                                cu.cust_name, cu.cust_contact_name, cu.cust_phone,
                                (SELECT MAX(a.created_at) FROM audit_logs a
                                  WHERE a.entity_type = 'quotation' AND a.entity_id = q.id AND a.action = 'status') AS last_status_at
                         FROM quotations q
                         LEFT JOIN companies c ON c.id = q.company_id
                         LEFT JOIN users u ON u.id = q.created_by
                         LEFT JOIN customers cu ON cu.id = q.customer_id
                         WHERE 1=1 $scope ORDER BY q.id ASC");
    $groups = [];
    while ($q = $res->fetch_assoc()) {
        $k = $q['project_id'] ? 'p' . $q['project_id'] : 'q' . $q['id'];
        $g = &$groups[$k];
        $g['ids'][] = (int) $q['id'];
        $g['first'] = $g['first'] ?? $q['created_at'];
        $g['last_status'] = max($g['last_status'] ?? '', (string) $q['last_status_at']);
        if (empty($g['rep']) || $q['is_selected'] || !$g['rep']['is_selected']) $g['rep'] = $q;
        unset($g);
    }
    if (!$groups) return [];

    // งาน (EO) ที่ผูกกับดีล: เลือกงานที่ยืนยันก่อน ไม่งั้นงานล่าสุด
    $fn_by = ['p' => [], 'q' => [], 'f' => []];
    $fr = $conn->query("SELECT f.id, f.project_id, f.quotation_id, f.approve, f.start_time, f.end_time, f.pax,
                               COALESCE(f.deposit,0) deposit, COALESCE(ft.type_name,'') type_name,
                               COALESCE(mr.room_name, NULLIF(f.booking_room,''), NULLIF(f.room_name,''), '') room_name
                        FROM functions f
                        LEFT JOIN function_types ft ON ft.id = f.function_type_id
                        LEFT JOIN meeting_rooms mr ON mr.id = f.room_id");
    while ($f = $fr->fetch_assoc()) {
        $f['rank'] = ['1' => 3, '0' => 2, '2' => 1][$f['approve']] ?? 0;
        if ($f['project_id']) $fn_by['p'][$f['project_id']][] = $f;
        if ($f['quotation_id']) $fn_by['q'][$f['quotation_id']][] = $f;
        $fn_by['f'][$f['id']] = $f;
    }
    // เงินที่รับแล้ว (มัดจำ + รายรับ) แยกตามงาน และตามใบเสนอราคา (ช่วงที่ยังไม่เปิดงาน)
    $paid_f = $paid_q = [];
    $pr = $conn->query("SELECT function_id, quotation_id, SUM(amount) s FROM function_finance
                        WHERE type IN ('deposit','income') GROUP BY function_id, quotation_id");
    while ($p = $pr->fetch_assoc()) {
        if ($p['function_id']) $paid_f[$p['function_id']] = ($paid_f[$p['function_id']] ?? 0) + $p['s'];
        elseif ($p['quotation_id']) $paid_q[$p['quotation_id']] = ($paid_q[$p['quotation_id']] ?? 0) + $p['s'];
    }
    // จำนวนคน/ห้อง จากรายการในใบเสนอราคา (ใช้เมื่อยังไม่มีงาน)
    $rep_ids = implode(',', array_map(fn($g) => (int) $g['rep']['id'], $groups));
    $items = [];
    $ir = $conn->query("SELECT quote_id,
                               MAX(CASE WHEN item_type IN ('Food','Break') THEN quantity END) pax,
                               SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN item_type = 'Meeting' THEN item_name END ORDER BY id SEPARATOR '||'), '||', 1) room
                        FROM quotation_items WHERE quote_id IN ($rep_ids) GROUP BY quote_id");
    while ($it = $ir->fetch_assoc()) $items[$it['quote_id']] = $it;

    $today = date('Y-m-d');
    $out = [];
    foreach ($groups as $g) {
        $q = $g['rep'];
        $created = substr($g['first'], 0, 10);
        if ($from && ($created < $from || $created >= $to)) continue;

        $cands = [];
        if ($q['project_id']) $cands = $fn_by['p'][$q['project_id']] ?? [];
        foreach ($g['ids'] as $qid) foreach ($fn_by['q'][$qid] ?? [] as $f) $cands[] = $f;
        if ($q['function_id'] && isset($fn_by['f'][$q['function_id']])) $cands[] = $fn_by['f'][$q['function_id']];
        $fn = null;
        foreach ($cands as $f) {
            if (!$fn || $f['rank'] > $fn['rank'] || ($f['rank'] === $fn['rank'] && $f['id'] > $fn['id'])) $fn = $f;
        }
        $fstate = $fn ? $fn['rank'] : 0;

        $status = rpt_sale_status($fstate, $q['workflow_status'] ?? 'Draft');
        $grp = rpt_sale_group($status);
        $net = (float) $q['grand_total'];
        $disc = (float) $q['discount'];
        $paid = $fn ? ($paid_f[$fn['id']] ?? 0) : 0;
        foreach ($g['ids'] as $qid) $paid += $paid_q[$qid] ?? 0;
        $dep_req = $fn ? (float) $fn['deposit'] : 0;
        $next = $q['follow_up_date'];
        $notes = [];
        if ($fstate === 2) $notes[] = 'เปิด EO แล้ว รออนุมัติ';
        if (!empty($q['lost_reason'])) $notes[] = 'เหตุผลที่ไม่ได้งาน: ' . trim($q['lost_reason']);
        if (!empty($q['lead_source'])) $notes[] = 'ที่มา: ' . trim($q['lead_source']);
        if (count($g['ids']) > 1) $notes[] = 'แก้ไข ' . count($g['ids']) . ' ฉบับ';
        if (!empty($q['inspection_date'])) $notes[] = 'ชมสถานที่ ' . rpt_th_date($q['inspection_date']);
        $last = $g['last_status'] ?: (($q['workflow_status'] ?? 'Draft') !== 'Draft' ? $q['updated_at'] : null);

        $out[] = [
            'id' => (int) $q['id'],
            'quote_no' => $q['quote_no'],
            'created' => $created,
            'staff' => $q['staff_name'],
            'customer' => trim($q['cust_name'] ?: '-'),
            'contact' => trim($q['cust_contact_name'] ?? ''),
            'phone' => trim($q['cust_phone'] ?? ''),
            'type' => $fn['type_name'] ?? '',
            'name' => trim($q['event_name'] ?: $q['quote_no']),
            'date' => $fn ? substr($fn['start_time'], 0, 10) : $q['event_date'],
            'time' => $fn ? date('H.i', strtotime($fn['start_time'])) . ($fn['end_time'] ? '-' . date('H.i', strtotime($fn['end_time'])) : '') : '',
            'room' => ($fn['room_name'] ?? '') ?: ($items[$q['id']]['room'] ?? ''),
            'pax' => (int) (($fn['pax'] ?? 0) ?: ($items[$q['id']]['pax'] ?? 0)),
            'gross' => $net + $disc,
            'discount' => $disc,
            'total' => $net,
            'status' => $status,
            'group' => $grp,
            'prob' => RPT_SALE_PROB[$status],
            'last_follow' => $last ? substr($last, 0, 10) : null,
            'result' => trim($q['result'] ?? '') ?: (($q['workflow_status'] ?? 'Draft') !== 'Draft' ? $q['workflow_status'] : ''),
            'next_follow' => $next,
            'overdue' => $grp === 'open' && $next && $next < $today,
            'dep_rate' => $dep_req > 0 && $net > 0 ? round($dep_req / $net * 100) : null,
            'dep_req' => $dep_req,
            'paid' => (float) $paid,
            'balance' => $net - $paid,
            'company' => $q['company_name'],
            'function_id' => $fn ? (int) $fn['id'] : null,
            'note' => implode(' · ', $notes),
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['created'], $a['created']) ?: $b['id'] <=> $a['id']);
    return $out;
}

/** สถานะการชำระเงิน — สูตรเดียวกับคอลัมน์ AC ในแบบฟอร์ม */
function rpt_pay_status($r)
{
    if ($r['status'] === 'ยืนยันงาน') {
        if ($r['balance'] <= 0) return 'ชำระครบ';
        return ($r['dep_req'] > 0 && $r['paid'] >= $r['dep_req']) ? 'รับมัดจำแล้ว/คงเหลือ' : 'รอชำระ';
    }
    if ($r['paid'] > 0) return $r['balance'] <= 0 ? 'ชำระครบ' : 'รับชำระบางส่วน';
    return 'ยังไม่ชำระ';
}
