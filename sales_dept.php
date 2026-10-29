<?php
include "config.php";
include_once __DIR__ . "/includes/report_data.php";

$role = strtolower($_SESSION['role'] ?? '');
$allowed = ['admin', 'staff', 'gm', 'sale', 'manager'];

/* ===== ตัวกรอง: ช่วงตามวันที่เสนอราคา ===== */
$mode = in_array($_GET['mode'] ?? '', ['month', 'year', 'all']) ? $_GET['mode'] : 'year';
$ym = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$yr = isset($_GET['year']) ? max(2000, min(2100, intval($_GET['year']))) : (int) date('Y');
$company = intval($_GET['company_id'] ?? 0);
$staff = intval($_GET['staff_id'] ?? 0);
$grp = in_array($_GET['g'] ?? '', ['open', 'won', 'lost']) ? $_GET['g'] : '';

if ($mode === 'month') {
    $from = "$ym-01";
    $to = date('Y-m-d', strtotime("$from +1 month"));
    $label = 'เดือน' . RPT_TH_MONTHS[(int) substr($ym, 5, 2)] . ' ' . (substr($ym, 0, 4) + 543);
    $prev_q = ['month' => date('Y-m', strtotime("$from -1 month"))];
    $next_q = ['month' => substr($to, 0, 7)];
} elseif ($mode === 'year') {
    $from = "$yr-01-01";
    $to = ($yr + 1) . '-01-01';
    $label = 'ปี ' . ($yr + 543);
    $prev_q = ['year' => $yr - 1];
    $next_q = ['year' => $yr + 1];
} else {
    $from = $to = null;
    $label = 'ทั้งหมด';
}

$rows_all = rpt_sales_rows($conn, $from, $to, $company, $staff);
$rows = $grp ? array_values(array_filter($rows_all, fn($r) => $r['group'] === $grp)) : $rows_all;
foreach ($rows as &$r) { $r['pay_status'] = rpt_pay_status($r); $r['forecast'] = $r['total'] * $r['prob'] / 100; }
unset($r);

$staff_name = '';
if ($staff) $staff_name = (string) ($conn->query("SELECT name FROM users WHERE id = $staff")->fetch_row()[0] ?? '');
$company_name = '';
if ($company) $company_name = (string) ($conn->query("SELECT company_name FROM companies WHERE id = $company")->fetch_row()[0] ?? '');
$scope_txt = implode(' · ', array_filter([$label, $company_name, $staff_name ? 'เซลล์ ' . $staff_name : '',
    ['open' => 'เฉพาะที่ยังไม่ปิด', 'won' => 'เฉพาะยืนยันงาน', 'lost' => 'เฉพาะแพ้การขาย/ยกเลิก'][$grp] ?? '']));

/* =========================================================
   ส่งออก Excel ตามแบบฟอร์ม (3 ชีต: วิธีใช้งาน / ติดตามใบเสนอราคา / Dashboard)
   ========================================================= */
if (($_GET['export'] ?? '') === 'xlsx') {
    if (!isset($_SESSION['user']) || !in_array($role, $allowed)) { http_response_code(403); exit('forbidden'); }
    include_once __DIR__ . "/includes/xlsx_writer.php";
    $x = new XlsxWriter();

    // ---- ชีต 1: วิธีใช้งานและหลักเกณฑ์ ----
    $guide = [
        ['วัตถุประสงค์', 'ใช้ติดตามใบเสนอราคาทุกฉบับตั้งแต่ส่งให้ลูกค้าจนยืนยัน/แพ้การขาย/ยกเลิก และเชื่อมโยงจำนวนคน ยอดขาย วันจัดงาน และการรับชำระ', 'พนักงานขาย / Sales Coordinator', 'บันทึกทุกใบเสนอราคา ห้ามติดตามเฉพาะงานที่ยืนยันแล้ว'],
        ['ที่มาของข้อมูล', 'ไฟล์นี้ส่งออกจากระบบ Banquet Management อัตโนมัติ หนึ่งแถวต่อหนึ่งดีล (ใบเสนอราคาที่แก้ไขหลายฉบับ แสดงเฉพาะฉบับที่เลือก/ฉบับล่าสุด)', 'ระบบ', 'แก้ไขข้อมูลในระบบแล้วส่งออกใหม่ ตัวเลขจะตรงกันเสมอ'],
        ['สถานะใบเสนอราคา', 'จัดทำใบเสนอราคา → ส่งใบเสนอราคาแล้ว → รอติดตาม/เจรจา/รออนุมัติ → ยืนยันงาน หรือ แพ้การขาย/ยกเลิก — แปลงจากสถานะ Workflow ของใบเสนอราคาและสถานะงาน (EO) ในระบบ', 'พนักงานขาย', 'เปลี่ยนสถานะ Workflow ในหน้าใบเสนอราคาทุกครั้งที่ติดตามลูกค้า'],
        ['วันติดตามล่าสุด', 'วันที่เปลี่ยนสถานะ Workflow ครั้งล่าสุดในระบบ', 'พนักงานขาย', 'อัปเดตสถานะทุกครั้งที่ติดต่อลูกค้าจริง'],
        ['วันติดตามครั้งถัดไป', 'ช่อง "วันติดตาม" ในใบเสนอราคา', 'พนักงานขาย', 'ช่องสีแดงหมายถึงเลยกำหนดติดตามและงานยังไม่ปิด'],
        ['ยอดสุทธิ', 'คำนวณอัตโนมัติ = ยอดเสนอราคา - ส่วนลด', 'ฝ่ายขาย/ผู้จัดการฝ่ายขาย', 'ยอดต้องตรงกับใบเสนอราคาฉบับล่าสุด'],
        ['โอกาสปิดการขาย', 'ระบบประเมินตามสถานะ: จัดทำ 10% · ส่งแล้ว 30% · รอติดตาม 40% · เจรจา 50% · รออนุมัติจากลูกค้า 70% · ยืนยันงาน 100% · แพ้/ยกเลิก 0% (แก้ตัวเลขในไฟล์ได้ สูตรจะคำนวณใหม่)', 'ผู้จัดการฝ่ายขาย', 'เป็นการประเมินเพื่อพยากรณ์ ไม่ใช่ยอดขายที่ยืนยันแล้ว'],
        ['มูลค่าคาดการณ์', 'คำนวณอัตโนมัติ = ยอดสุทธิ × โอกาสปิดการขาย (%) ÷ 100', 'ผู้จัดการฝ่ายขาย', ''],
        ['ยอดมัดจำ', 'ยอดมัดจำที่ระบุในงาน (EO) อัตรามัดจำ = ยอดมัดจำ ÷ ยอดสุทธิ', 'ฝ่ายขาย/บัญชี', 'กำหนดอัตรามัดจำและวันครบกำหนดตามใบเสนอราคา/สัญญา/นโยบายโรงแรม'],
        ['ยอดรับชำระ / คงเหลือ', 'ยอดรับชำระ = มัดจำ + รายรับที่บันทึกในหน้าการเงิน · ยอดคงเหลือ = ยอดสุทธิ - ยอดรับชำระแล้ว', 'บัญชี/แคชเชียร์', 'บันทึกยอดรับชำระจากหลักฐานจริงเท่านั้น และกระทบยอดกับใบเสร็จ/ธนาคาร'],
        ['การยืนยันงาน', 'ถือว่า "ยืนยันงาน" เมื่อลูกค้าเซ็นยืนยัน หรือเปิดงาน (EO) ในระบบแล้ว', 'ฝ่ายขาย + บัญชี + ฝ่ายจัดเลี้ยง', 'ส่งต่อรายละเอียดให้ปฏิบัติการจัดงาน'],
    ];
    $s1 = [[['v' => 'คู่มือการใช้งาน – ตารางติดตามใบเสนอราคาโรงแรม', 's' => 'title']],
        array_map(fn($v) => ['v' => $v, 's' => 'header'], ['หัวข้อ', 'แนวทางปฏิบัติ', 'ผู้รับผิดชอบ', 'จุดควบคุม'])];
    foreach ($guide as $g) $s1[] = [['v' => $g[0], 's' => 'label'], ['v' => $g[1], 's' => 'text'], ['v' => $g[2], 's' => 'text'], ['v' => $g[3], 's' => 'text']];
    $x->addSheet('วิธีใช้งานและหลักเกณฑ์', $s1, ['cols' => [25, 78, 27, 48], 'merges' => ['A1:D1'], 'heights' => [1 => 28], 'landscape' => true]);

    // ---- ชีต 2: ติดตามใบเสนอราคา (คอลัมน์ A–AE ตามแบบฟอร์ม) ----
    $heads = ['ลำดับ', 'เลขที่ใบเสนอราคา', 'วันที่เสนอราคา', 'พนักงานขายผู้รับผิดชอบ', 'ชื่อลูกค้า/บริษัท', 'ผู้ประสานงาน', 'โทรศัพท์/LINE',
        'ประเภทงาน', 'รายละเอียดงาน/แพ็กเกจ', 'วันที่จัดงาน', 'เวลาเริ่มงาน', 'สถานที่/ห้อง', 'จำนวนผู้เข้าร่วม (คน)', 'ยอดเสนอราคา (บาท)',
        'ส่วนลด (บาท)', 'ยอดสุทธิ (บาท)', 'สถานะใบเสนอราคา', 'โอกาสปิดการขาย (%)', 'มูลค่าคาดการณ์ (บาท)', 'วันที่ติดตามล่าสุด',
        'ผลการติดตามล่าสุด', 'วันติดตามครั้งถัดไป', 'เงื่อนไขการชำระเงิน', 'อัตรามัดจำ (%)', 'ยอดมัดจำที่ต้องชำระ (บาท)', 'ยอดรับชำระแล้ว (บาท)',
        'ยอดคงเหลือ (บาท)', 'กำหนดชำระส่วนที่เหลือ', 'สถานะการชำระเงิน', 'ผู้ประสานงานภายใน', 'หมายเหตุ/สิ่งที่ต้องดำเนินการ'];
    $s2 = [[['v' => 'ตารางควบคุมใบเสนอราคาและติดตามงานขายโรงแรม · ' . $scope_txt . ' · ข้อมูล ณ ' . rpt_th_date(date('Y-m-d')), 's' => 'title']],
        array_map(fn($v) => ['v' => $v, 's' => 'header'], $heads)];
    $dt = fn($d, $late = false) => $d ? ['v' => $d, 'date' => true, 's' => $late ? 'date_late' : 'date'] : ['v' => '-', 's' => 'date'];
    foreach ($rows as $i => $r) {
        $n = $i + 3;
        $st_style = $r['group'] === 'won' ? 'won' : ($r['group'] === 'lost' ? 'lost' : 'text');
        $s2[] = [
            ['v' => $i + 1, 's' => 'int'],
            ['v' => $r['quote_no'], 's' => 'text'],
            $dt($r['created']),
            ['v' => $r['staff'], 's' => 'text'],
            ['v' => $r['customer'], 's' => 'text'],
            ['v' => $r['contact'] ?: '-', 's' => 'text'],
            ['v' => $r['phone'] ?: '-', 's' => 'text'],
            ['v' => $r['type'] ?: '-', 's' => 'text'],
            ['v' => $r['name'], 's' => 'text'],
            $dt($r['date']),
            ['v' => $r['time'] ?: '-', 's' => 'text'],
            ['v' => $r['room'] ?: '-', 's' => 'text'],
            ['v' => $r['pax'] ?: null, 's' => 'int'],
            ['v' => $r['gross'], 's' => 'num'],
            ['v' => $r['discount'] ?: null, 's' => 'num'],
            ['v' => $r['total'], 'f' => "IF(N$n=\"\",\"\",N$n-IF(O$n=\"\",0,O$n))", 's' => 'calc'],
            ['v' => $r['status'], 's' => $st_style],
            ['v' => $r['prob'], 's' => 'pct'],
            ['v' => $r['forecast'], 'f' => "IF(OR(P$n=\"\",R$n=\"\"),\"\",P$n*R$n/100)", 's' => 'calc'],
            $dt($r['last_follow']),
            ['v' => $r['result'] ?: '-', 's' => 'text'],
            $dt($r['next_follow'], $r['overdue']),
            ['v' => '', 's' => 'text'],
            ['v' => $r['dep_rate'], 's' => 'pct'],
            ['v' => $r['dep_rate'] !== null ? $r['total'] * $r['dep_rate'] / 100 : '', 'f' => "IF(OR(P$n=\"\",X$n=\"\"),\"\",P$n*X$n/100)", 's' => 'calc'],
            ['v' => $r['paid'] ?: null, 's' => 'num'],
            ['v' => $r['balance'], 'f' => "IF(P$n=\"\",\"\",P$n-IF(Z$n=\"\",0,Z$n))", 's' => 'calc'],
            ['v' => '', 's' => 'text'],
            ['v' => $r['pay_status'], 'f' => "IF(B$n=\"\",\"\",IF(Q$n=\"ยืนยันงาน\",IF(AA$n<=0,\"ชำระครบ\",IF(AND(Y$n<>\"\",Z$n>=Y$n),\"รับมัดจำแล้ว/คงเหลือ\",\"รอชำระ\")),IF(Z$n>0,IF(AA$n<=0,\"ชำระครบ\",\"รับชำระบางส่วน\"),\"ยังไม่ชำระ\")))", 's' => 'calc_text'],
            ['v' => $r['staff'], 's' => 'text'],
            ['v' => $r['note'] ?: '-', 's' => 'text'],
        ];
    }
    $last = max(3, count($rows) + 2);
    $x->addSheet('ติดตามใบเสนอราคา', $s2, [
        'cols' => [8, 22, 14, 22, 26, 22, 18, 22, 30, 14, 13, 20, 16, 18, 16, 18, 22, 17, 20, 16, 32, 16, 24, 15, 22, 20, 18, 18, 24, 22, 36],
        'merges' => ['A1:AE1'], 'freeze' => 'C3', 'filter' => "A2:AE$last", 'heights' => [1 => 28, 2 => 36],
    ]);

    // ---- ชีต 3: Dashboard (สูตรอ้างอิงชีตติดตามใบเสนอราคา) ----
    $T = "'ติดตามใบเสนอราคา'!";
    $rg = fn($c) => "$T{$c}3:{$c}$last";
    $cnt = fn($st) => count(array_filter($rows, fn($r) => $r['status'] === $st));
    $won = array_filter($rows, fn($r) => $r['group'] === 'won');
    $open = array_filter($rows, fn($r) => $r['group'] === 'open');
    $metrics = [
        ['จำนวนใบเสนอราคาทั้งหมด', count($rows), 'COUNTA(' . $rg('B') . ')', 'จำนวนดีลที่มีเลขที่ใบเสนอราคา', 'ใช้ดูปริมาณงานขาย', 'int'],
        ['ยอดเสนอราคาสุทธิรวม', array_sum(array_column($rows, 'total')), 'SUM(' . $rg('P') . ')', 'ยอดสุทธิรวมทุกสถานะ', 'รวมงานที่ยังไม่ยืนยันด้วย', 'num'],
        ['มูลค่าคาดการณ์รวม', array_sum(array_column($rows, 'forecast')), 'SUM(' . $rg('S') . ')', 'ยอดสุทธิถ่วงน้ำหนักด้วยโอกาสปิดการขาย', 'ใช้คาดการณ์ ไม่ใช่ยอดขายจริง', 'num'],
        ['จำนวนงานยืนยันแล้ว', count($won), 'COUNTIF(' . $rg('Q') . ',"ยืนยันงาน")', 'งานที่ลูกค้ายืนยันหรือเปิด EO แล้ว', 'ควรตรวจหลักฐานการยืนยัน', 'int'],
        ['ยอดงานยืนยันแล้ว', array_sum(array_column($won, 'total')), 'SUMIF(' . $rg('Q') . ',"ยืนยันงาน",' . $rg('P') . ')', 'ยอดสุทธิของงานยืนยันแล้ว', 'ใช้ส่งต่อฝ่ายปฏิบัติการ/บัญชี', 'num'],
        ['อัตราปิดการขาย (%)', count($rows) ? round(count($won) / count($rows) * 100) : 0, 'IFERROR(ROUND(B6/B3*100,0),0)', 'งานยืนยัน ÷ ใบเสนอราคาทั้งหมด', 'ดูประสิทธิภาพการขาย', 'pct'],
        ['จำนวนงานรอติดตาม', count($open), 'COUNTIF(' . $rg('Q') . ',"จัดทำใบเสนอราคา")+COUNTIF(' . $rg('Q') . ',"รอติดตาม")+COUNTIF(' . $rg('Q') . ',"ส่งใบเสนอราคาแล้ว")+COUNTIF(' . $rg('Q') . ',"อยู่ระหว่างเจรจา/ปรับราคา")+COUNTIF(' . $rg('Q') . ',"รออนุมัติจากลูกค้า")', 'งานที่ยังไม่ปิดการขาย', 'กรองดูวันติดตามครั้งถัดไป', 'int'],
        ['ยอดรับชำระรวม', array_sum(array_column($rows, 'paid')), 'SUM(' . $rg('Z') . ')', 'ยอดรับชำระที่ฝ่ายขาย/บัญชีบันทึก', 'ต้องกระทบยอดหลักฐานรับเงิน', 'num'],
        ['ยอดคงเหลือรวม (เฉพาะงานยืนยัน)', array_sum(array_column($won, 'balance')), 'SUMIF(' . $rg('Q') . ',"ยืนยันงาน",' . $rg('AA') . ')', 'ยอดสุทธิหักยอดรับชำระแล้ว ของงานที่ยืนยัน', 'ติดตามลูกหนี้และยอดก่อนวันงาน', 'num'],
        ['จำนวนงานติดตามเกินกำหนด', count(array_filter($rows, fn($r) => $r['overdue'])), 'COUNTIFS(' . $rg('V') . ',"<"&TODAY(),' . $rg('V') . ',"<>-",' . $rg('Q') . ',"<>ยืนยันงาน",' . $rg('Q') . ',"<>แพ้การขาย",' . $rg('Q') . ',"<>ยกเลิก")', 'วันติดตามครั้งถัดไปเลยกำหนด', 'ตรวจทุกเช้า', 'int'],
    ];
    $s3 = [[['v' => 'DASHBOARD – สรุปใบเสนอราคาและงานขายโรงแรม · ' . $scope_txt, 's' => 'title']],
        array_map(fn($v) => ['v' => $v, 's' => 'header'], ['ตัวชี้วัด', 'ผลรวม/จำนวน', 'ความหมาย', 'วิธีใช้'])];
    foreach ($metrics as $m) $s3[] = [['v' => $m[0], 's' => 'label'], ['v' => $m[1], 'f' => $m[2], 's' => $m[5] === 'num' ? 'total_num' : ($m[5] === 'pct' ? 'pct' : 'int')], ['v' => $m[3], 's' => 'text'], ['v' => $m[4], 's' => 'text']];
    // สรุปรายพนักงานขาย
    $s3[] = [];
    $s3[] = [['v' => 'สรุปรายพนักงานขาย', 's' => 'title']];
    $s3[] = array_map(fn($v) => ['v' => $v, 's' => 'header'], ['พนักงานขาย', 'จำนวนใบเสนอราคา', 'ยอดเสนอสุทธิ', 'งานยืนยัน', 'ยอดยืนยัน', 'อัตราปิด (%)', 'มูลค่าคาดการณ์']);
    $people = array_values(array_unique(array_column($rows, 'staff')));
    foreach ($people as $p) {
        $n = count($s3) + 1;
        $pr = array_filter($rows, fn($r) => $r['staff'] === $p);
        $pw = array_filter($pr, fn($r) => $r['group'] === 'won');
        $s3[] = [
            ['v' => $p, 's' => 'label'],
            ['v' => count($pr), 'f' => 'COUNTIF(' . $rg('D') . ",A$n)", 's' => 'int'],
            ['v' => array_sum(array_column($pr, 'total')), 'f' => 'SUMIF(' . $rg('D') . ",A$n," . $rg('P') . ')', 's' => 'num'],
            ['v' => count($pw), 'f' => 'COUNTIFS(' . $rg('D') . ",A$n," . $rg('Q') . ',"ยืนยันงาน")', 's' => 'int'],
            ['v' => array_sum(array_column($pw, 'total')), 'f' => 'SUMIFS(' . $rg('P') . ',' . $rg('D') . ",A$n," . $rg('Q') . ',"ยืนยันงาน")', 's' => 'num'],
            ['v' => count($pr) ? round(count($pw) / count($pr) * 100) : 0, 'f' => "IFERROR(ROUND(D$n/B$n*100,0),0)", 's' => 'pct'],
            ['v' => array_sum(array_column($pr, 'forecast')), 'f' => 'SUMIF(' . $rg('D') . ",A$n," . $rg('S') . ')', 's' => 'num'],
        ];
    }
    $x->addSheet('Dashboard', $s3, ['cols' => [34, 20, 45, 35, 18, 14, 18], 'merges' => ['A1:D1'], 'heights' => [1 => 28]]);

    $x->download('รายงานสรุปการติดตามใบเสนอราคา ' . str_replace(['/', '\\'], '-', $scope_txt) . '.xlsx');
    exit;
}

include "header.php";
if (!in_array($role, $allowed)) {
    echo "<script>window.location.href='login.php?error=access_denied';</script>";
    exit;
}

/* ===== สรุป ===== */
$won = array_values(array_filter($rows, fn($r) => $r['group'] === 'won'));
$open = array_values(array_filter($rows, fn($r) => $r['group'] === 'open'));
$lost = array_values(array_filter($rows, fn($r) => $r['group'] === 'lost'));
$overdue = array_values(array_filter($rows, fn($r) => $r['overdue']));
$unpaid = array_values(array_filter($won, fn($r) => $r['balance'] > 0));
$rate = count($rows) ? round(count($won) / count($rows) * 100) : 0;
$data = ['all' => $rows, 'won' => $won, 'open' => $open, 'lost' => $lost, 'overdue' => $overdue, 'unpaid' => $unpaid];

$by_staff = [];
foreach ($rows as $r) {
    $s = &$by_staff[$r['staff']];
    $s['n'] = ($s['n'] ?? 0) + 1;
    $s['total'] = ($s['total'] ?? 0) + $r['total'];
    $s['won'] = ($s['won'] ?? 0) + ($r['group'] === 'won' ? 1 : 0);
    $s['won_total'] = ($s['won_total'] ?? 0) + ($r['group'] === 'won' ? $r['total'] : 0);
    $s['forecast'] = ($s['forecast'] ?? 0) + $r['forecast'];
    $s['overdue'] = ($s['overdue'] ?? 0) + ($r['overdue'] ? 1 : 0);
    unset($s);
}
uasort($by_staff, fn($a, $b) => $b['total'] <=> $a['total']);

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
$staff_list = $conn->query("SELECT DISTINCT u.id, u.name FROM users u JOIN quotations q ON q.created_by = u.id ORDER BY u.name");
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$baht = fn($n) => '฿' . number_format((float) $n);
$base = ['mode' => $mode, 'company_id' => $company, 'staff_id' => $staff, 'g' => $grp];
$cur_q = $mode === 'month' ? ['month' => $ym] : ($mode === 'year' ? ['year' => $yr] : []);
$url = fn($extra = []) => 'sales_dept.php?' . http_build_query(array_filter(array_merge($base, $cur_q, $extra), fn($v) => $v !== '' && $v !== 0 && $v !== null));
$st_cls = fn($r) => $r['group'] === 'won' ? 'ok' : ($r['group'] === 'lost' ? 'crit' : ($r['status'] === 'จัดทำใบเสนอราคา' ? '' : 'warn'));

/* =========================================================
   แท็บ "เป้าหมาย & ผลงาน" (เดิมของหน้างานขาย)
   ยอดขายนับตามวันจัดงานของงานที่ยืนยันแล้ว ให้ตรงกับหน้า Dashboard
   (เดิมนับตามวันที่สร้างงาน และนับงานที่ยกเลิก/รออนุมัติรวมเป็นจำนวนงาน)
   ========================================================= */
$tab = ($_GET['tab'] ?? '') === 'targets' ? 'targets' : 'track';
if ($tab === 'targets') {
    $t_ym = $mode === 'month' ? $ym : date('Y-m');
    $t_year = (int) substr($t_ym, 0, 4);
    $t_month = (int) substr($t_ym, 5, 2);
    $t_from = "$t_ym-01";
    $t_ev = rpt_events($conn, $t_from, date('Y-m-d', strtotime("$t_from +1 month")), $company)['confirmed'];
    $t_targets = rpt_target_by_user($conn, $t_year, $t_month);
    $perf = [];
    foreach ($t_ev as $e) {
        $k = $e['staff_id'] ?: 0;
        $p = &$perf[$k];
        $p['id'] = $k;
        $p['name'] = $e['staff'];
        $p['events'] = ($p['events'] ?? 0) + 1;
        $p['revenue'] = ($p['revenue'] ?? 0) + $e['total'];
        $p['deposit'] = ($p['deposit'] ?? 0) + $e['deposit'];
        unset($p);
    }
    // คนที่มีเป้าแต่ยังไม่มีงาน ก็ต้องแสดง
    $ur = $conn->query("SELECT id, name FROM users");
    $user_names = [];
    while ($u = $ur->fetch_assoc()) $user_names[(int) $u['id']] = $u['name'];
    foreach ($t_targets as $uid => $amt) {
        if (!isset($perf[$uid])) $perf[$uid] = ['id' => $uid, 'name' => $user_names[$uid] ?? '-', 'events' => 0, 'revenue' => 0, 'deposit' => 0];
    }
    foreach ($perf as $k => &$p) $p['target'] = $t_targets[$k] ?? 0;
    unset($p);
    usort($perf, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    $sum_rev = array_sum(array_column($perf, 'revenue'));
    $sum_target = array_sum($t_targets);
    $rev_with_target = array_sum(array_map(fn($p) => $p['target'] > 0 ? $p['revenue'] : 0, $perf));
    $overall_pct = $sum_target > 0 ? round($rev_with_target / $sum_target * 100) : null;

    $targets_res = $conn->query("SELECT st.*, u.name FROM sales_targets st JOIN users u ON st.user_id = u.id ORDER BY st.target_year DESC, st.target_month DESC, u.name");
    $targets = $targets_res->fetch_all(MYSQLI_ASSOC);
    $staffs = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM') ORDER BY name ASC");
}
$tab_url = fn($t) => 'sales_dept.php?' . http_build_query(array_filter(array_merge($base, $cur_q, ['tab' => $t === 'targets' ? 'targets' : '']), fn($v) => $v !== '' && $v !== 0 && $v !== null));
?>
<link rel="stylesheet" href="assets/css/insights.css?v=<?= @filemtime(__DIR__ . '/assets/css/insights.css') ?>">

<div class="ins py-2">
    <div class="ins-head">
        <div>
            <h1>งานขาย &amp; รายงานการขาย</h1>
            <div class="sub"><?= $tab === 'track'
                ? 'ติดตามใบเสนอราคา · ' . $h($scope_txt) . ' · นับตามวันที่เริ่มเสนอราคา ใบที่แก้ไขหลายครั้งนับเป็น 1 ดีล'
                : 'เป้าหมายและผลงานพนักงานขาย · ' . RPT_TH_MONTHS[$t_month] . ' ' . ($t_year + 543) . ' · ยอดขาย = งานที่ยืนยันแล้ว ตามวันจัดงาน' ?></div>
        </div>
        <?php if ($tab === 'track'): ?>
            <div class="d-flex gap-2 no-print">
                <a href="<?= $h($url(['export' => 'xlsx'])) ?>" class="btn btn-sm btn-gold"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
            </div>
        <?php endif; ?>
    </div>

    <ul class="nav nav-underline mb-3 no-print">
        <li class="nav-item"><a class="nav-link <?= $tab === 'track' ? 'active' : '' ?>" href="<?= $h($tab_url('track')) ?>"><i class="bi bi-clipboard-data me-1"></i>ติดตามใบเสนอราคา</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'targets' ? 'active' : '' ?>" href="<?= $h($tab_url('targets')) ?>"><i class="bi bi-bullseye me-1"></i>เป้าหมาย &amp; ผลงาน</a></li>
    </ul>

    <?php if ($tab === 'track'): ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
        <nav class="ins-seg" aria-label="ช่วงเวลา">
            <a href="<?= $h('sales_dept.php?' . http_build_query(array_filter(['mode' => 'month', 'month' => $mode === 'year' ? "$yr-" . date('m') : $ym, 'company_id' => $company, 'staff_id' => $staff, 'g' => $grp]))) ?>" class="<?= $mode === 'month' ? 'active' : '' ?>">รายเดือน</a>
            <a href="<?= $h('sales_dept.php?' . http_build_query(array_filter(['mode' => 'year', 'year' => $mode === 'month' ? substr($ym, 0, 4) : $yr, 'company_id' => $company, 'staff_id' => $staff, 'g' => $grp]))) ?>" class="<?= $mode === 'year' ? 'active' : '' ?>">รายปี</a>
            <a href="<?= $h('sales_dept.php?' . http_build_query(array_filter(['mode' => 'all', 'company_id' => $company, 'staff_id' => $staff, 'g' => $grp]))) ?>" class="<?= $mode === 'all' ? 'active' : '' ?>">ทั้งหมด</a>
        </nav>
        <form method="get" class="ins-filter">
            <input type="hidden" name="mode" value="<?= $mode ?>">
            <?php if ($mode !== 'all'): ?>
                <a href="<?= $h($url($prev_q)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="ก่อนหน้า"><i class="bi bi-chevron-left"></i></a>
                <?php if ($mode === 'month'): ?>
                    <input type="month" name="month" value="<?= $ym ?>" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="เดือน">
                <?php else: ?>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="ปี">
                        <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $yr ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>
                <a href="<?= $h($url($next_q)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="ถัดไป"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
            <select name="g" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="สถานะ">
                <option value="">ทุกสถานะ</option>
                <option value="open" <?= $grp === 'open' ? 'selected' : '' ?>>ยังไม่ปิด</option>
                <option value="won" <?= $grp === 'won' ? 'selected' : '' ?>>ยืนยันงาน</option>
                <option value="lost" <?= $grp === 'lost' ? 'selected' : '' ?>>แพ้การขาย / ยกเลิก</option>
            </select>
            <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="โรงแรม">
                <option value="0">ทุกโรงแรม</option>
                <?php while ($r = $companies->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= $company == $r['id'] ? 'selected' : '' ?>><?= $h($r['company_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="staff_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="พนักงานขาย">
                <option value="0">ทุกพนักงานขาย</option>
                <?php while ($r = $staff_list->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= $staff == $r['id'] ? 'selected' : '' ?>><?= $h($r['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <!-- ===== สรุป (ตรงกับชีต Dashboard ในไฟล์ Excel) ===== -->
    <div class="sumstrip mb-3">
        <button type="button" data-drill="all" data-kind="sale" data-title="ใบเสนอราคาทั้งหมด · <?= $h($label) ?>">
            <div class="s-l">ใบเสนอราคา</div><div class="s-v"><?= count($rows) ?></div>
            <div class="s-s">ยอดสุทธิรวม <?= $baht(array_sum(array_column($rows, 'total'))) ?></div>
        </button>
        <button type="button" data-drill="won" data-kind="sale" data-title="ยืนยันงาน · <?= $h($label) ?>">
            <div class="s-l">ยืนยันงาน</div><div class="s-v" style="color:var(--success)"><?= count($won) ?></div>
            <div class="s-s"><?= $baht(array_sum(array_column($won, 'total'))) ?> · ปิดได้ <?= $rate ?>%</div>
        </button>
        <button type="button" data-drill="open" data-kind="sale" data-title="รอติดตาม · <?= $h($label) ?>">
            <div class="s-l">รอติดตาม</div><div class="s-v" style="color:var(--warning)"><?= count($open) ?></div>
            <div class="s-s"><?= $baht(array_sum(array_column($open, 'total'))) ?></div>
        </button>
        <button type="button" data-drill="all" data-kind="sale" data-title="มูลค่าคาดการณ์ · <?= $h($label) ?>">
            <div class="s-l">มูลค่าคาดการณ์</div><div class="s-v"><?= $baht(array_sum(array_column($rows, 'forecast'))) ?></div>
            <div class="s-s">ยอดสุทธิ × โอกาสปิดการขาย</div>
        </button>
        <button type="button" data-drill="won" data-kind="sale" data-title="ยอดรับชำระ · <?= $h($label) ?>">
            <div class="s-l">รับชำระแล้ว</div><div class="s-v"><?= $baht(array_sum(array_column($rows, 'paid'))) ?></div>
            <div class="s-s">มัดจำ + รายรับที่บันทึก</div>
        </button>
        <button type="button" data-drill="unpaid" data-kind="sale" data-title="งานยืนยันที่ยังมียอดคงเหลือ · <?= $h($label) ?>">
            <div class="s-l">คงเหลือ (งานยืนยัน)</div><div class="s-v"><?= $baht(array_sum(array_column($won, 'balance'))) ?></div>
            <div class="s-s"><?= count($unpaid) ?> งาน</div>
        </button>
        <button type="button" data-drill="overdue" data-kind="sale" data-title="ติดตามเกินกำหนด">
            <div class="s-l">ติดตามเกินกำหนด</div><div class="s-v" style="<?= $overdue ? 'color:var(--danger)' : '' ?>"><?= count($overdue) ?></div>
            <div class="s-s">วันติดตามถัดไปเลยแล้ว</div>
        </button>
    </div>

    <!-- ===== สรุปรายพนักงานขาย ===== -->
    <div class="ins-card mb-3">
        <div class="ins-card-hd"><h2>สรุปรายพนักงานขาย</h2><span class="hint">กดแถวเพื่อดูใบเสนอราคาของคนนั้น</span></div>
        <div class="ins-card-bd pt-0 table-responsive">
            <?php if (!$by_staff): ?>
                <div class="ins-empty">ไม่มีใบเสนอราคาในช่วงนี้</div>
            <?php else: ?>
                <table class="ins-tbl">
                    <thead><tr><th>พนักงานขาย</th><th class="c">ใบเสนอราคา</th><th class="r">ยอดเสนอสุทธิ</th><th class="c">ยืนยันงาน</th><th class="r">ยอดยืนยัน</th><th class="r">อัตราปิด</th><th class="r">มูลค่าคาดการณ์</th><th class="c">เกินกำหนด</th></tr></thead>
                    <tbody>
                        <?php foreach ($by_staff as $name => $s): ?>
                            <tr class="is-click" tabindex="0" data-drill="all" data-kind="sale" data-field="staff" data-value="<?= $h($name) ?>" data-title="<?= $h($name) ?> · <?= $h($label) ?>">
                                <td class="fw-semibold"><?= $h($name) ?></td>
                                <td class="c"><?= $s['n'] ?></td>
                                <td class="r"><?= $baht($s['total']) ?></td>
                                <td class="c"><?= $s['won'] ?></td>
                                <td class="r fw-semibold"><?= $baht($s['won_total']) ?></td>
                                <td class="r"><?= round($s['won'] / $s['n'] * 100) ?>%</td>
                                <td class="r"><?= $baht($s['forecast']) ?></td>
                                <td class="c" style="<?= $s['overdue'] ? 'color:var(--danger);font-weight:700' : '' ?>"><?= $s['overdue'] ?: '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== ตารางติดตามใบเสนอราคา ===== -->
    <div class="ins-card mb-3">
        <div class="ins-card-hd"><h2>ตารางติดตามใบเสนอราคา</h2><span class="hint"><?= count($rows) ?> ดีล · ไฟล์ Excel มีครบทั้ง 31 คอลัมน์ตามแบบฟอร์ม</span></div>
        <div class="ins-card-bd pt-0 table-responsive">
            <?php if (!$rows): ?>
                <div class="ins-empty">ไม่มีใบเสนอราคาในช่วงนี้</div>
            <?php else: ?>
                <table class="ins-tbl">
                    <thead><tr>
                        <th>#</th><th>ใบเสนอราคา</th><th>ลูกค้า</th><th>งาน</th><th>วันจัดงาน</th>
                        <th class="r">ยอดสุทธิ</th><th>สถานะ</th><th class="c">โอกาส</th><th>ติดตาม</th><th class="r">รับชำระ / คงเหลือ</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td class="c text-muted"><?= $i + 1 ?></td>
                                <td class="text-nowrap"><a href="quotation_view.php?id=<?= $r['id'] ?>"><?= $h($r['quote_no']) ?></a>
                                    <span class="sm"><?= rpt_th_date($r['created']) ?> · <?= $h($r['staff']) ?></span></td>
                                <td><?= $h($r['customer']) ?><span class="sm"><?= $h(implode(' · ', array_filter([$r['contact'], $r['phone']]))) ?: '-' ?></span></td>
                                <td><?= $h($r['name']) ?><span class="sm"><?= $h(implode(' · ', array_filter([$r['type'], $r['room'], $r['pax'] ? number_format($r['pax']) . ' คน' : '']))) ?></span></td>
                                <td class="text-nowrap"><?= rpt_th_date($r['date']) ?><span class="sm"><?= $h($r['time']) ?></span></td>
                                <td class="r fw-semibold"><?= $baht($r['total']) ?><?php if ($r['discount'] > 0): ?><span class="sm">ลด <?= $baht($r['discount']) ?></span><?php endif; ?></td>
                                <td><span class="pill <?= $st_cls($r) ?>"><?= $h($r['status']) ?></span><span class="sm"><?= $h($r['result']) ?></span></td>
                                <td class="c"><?= $r['prob'] ?>%</td>
                                <td class="text-nowrap">
                                    <span class="sm">ล่าสุด <?= $r['last_follow'] ? rpt_th_date($r['last_follow'], false) : '-' ?></span>
                                    <span class="sm" style="<?= $r['overdue'] ? 'color:var(--danger);font-weight:700' : '' ?>">ถัดไป <?= $r['next_follow'] ? rpt_th_date($r['next_follow'], false) : '-' ?></span>
                                </td>
                                <td class="r"><?= $r['paid'] > 0 ? $baht($r['paid']) : '–' ?><span class="sm"><?= $h($r['pay_status']) ?><?= $r['group'] === 'won' && $r['balance'] > 0 ? ' · ' . $baht($r['balance']) : '' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr>
                        <td colspan="5">รวม</td><td class="r"><?= $baht(array_sum(array_column($rows, 'total'))) ?></td><td colspan="3"></td>
                        <td class="r"><?= $baht(array_sum(array_column($rows, 'paid'))) ?></td>
                    </tr></tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted small mb-0">
        สถานะแปลงจากสถานะ Workflow ของใบเสนอราคาและงาน (EO) · "ยืนยันงาน" = ลูกค้าเซ็นยืนยันหรือเปิด EO แล้ว ·
        โอกาสปิดการขายระบบประเมินตามสถานะ (จัดทำ 10% · ส่งแล้ว 30% · รอติดตาม 40% · เจรจา 50% · รออนุมัติจากลูกค้า 70% · ยืนยัน 100%) ·
        วันติดตามถัดไปมาจากช่อง "วันติดตาม" ในใบเสนอราคา
    </p>

    <?php else: /* ===================== แท็บ เป้าหมาย & ผลงาน ===================== */ ?>

    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <form method="get" class="ins-filter mb-3 no-print">
        <input type="hidden" name="tab" value="targets">
        <input type="hidden" name="mode" value="month">
        <a href="<?= $h('sales_dept.php?' . http_build_query(['tab' => 'targets', 'mode' => 'month', 'month' => date('Y-m', strtotime("$t_from -1 month")), 'company_id' => $company ?: null])) ?>" class="btn btn-sm btn-outline-secondary" aria-label="เดือนก่อน"><i class="bi bi-chevron-left"></i></a>
        <input type="month" name="month" value="<?= $t_ym ?>" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="เดือน">
        <a href="<?= $h('sales_dept.php?' . http_build_query(['tab' => 'targets', 'mode' => 'month', 'month' => date('Y-m', strtotime("$t_from +1 month")), 'company_id' => $company ?: null])) ?>" class="btn btn-sm btn-outline-secondary" aria-label="เดือนถัดไป"><i class="bi bi-chevron-right"></i></a>
    </form>

    <div class="sumstrip mb-3">
        <div><div class="s-l">ยอดขายเดือนนี้</div><div class="s-v"><?= $baht($sum_rev) ?></div><div class="s-s"><?= count($t_ev) ?> งานที่ยืนยัน</div></div>
        <div><div class="s-l">เป้าหมายรวม</div><div class="s-v"><?= $sum_target > 0 ? $baht($sum_target) : '–' ?></div><div class="s-s"><?= count($t_targets) ?> คนที่ตั้งเป้า</div></div>
        <div><div class="s-l">สำเร็จตามเป้า</div>
            <div class="s-v" style="color:<?= $overall_pct === null ? 'inherit' : ($overall_pct >= 100 ? 'var(--success)' : ($overall_pct >= 50 ? 'var(--warning)' : 'var(--danger)')) ?>"><?= $overall_pct === null ? '–' : $overall_pct . '%' ?></div>
            <div class="s-s">เทียบเฉพาะคนที่ตั้งเป้าไว้</div></div>
        <div><div class="s-l">มัดจำของงานเดือนนี้</div><div class="s-v"><?= $baht(array_sum(array_column($perf, 'deposit'))) ?></div><div class="s-s">ตามยอดมัดจำในงาน</div></div>
    </div>

    <div class="ins-card mb-3">
        <div class="ins-card-hd"><h2>ผลงานพนักงานขาย</h2><span class="hint">กดที่ชื่อเพื่อเปิดรายงานสรุปผลงาน (พิมพ์ได้)</span></div>
        <div class="ins-card-bd pt-0 table-responsive">
            <?php if (!$perf): ?>
                <div class="ins-empty">ยังไม่มีผลงานหรือเป้าหมายในเดือนนี้</div>
            <?php else: ?>
                <table class="ins-tbl">
                    <thead><tr><th style="width:40px">#</th><th>พนักงานขาย</th><th class="c">งาน</th><th class="r">ยอดขาย</th><th class="r">มัดจำ</th><th class="r">เป้าหมาย</th><th style="width:220px">% ของเป้า</th></tr></thead>
                    <tbody>
                        <?php foreach ($perf as $i => $p):
                            $pct = $p['target'] > 0 ? round($p['revenue'] / $p['target'] * 100) : null;
                            $col = $pct === null ? 'var(--line-strong)' : ($pct >= 100 ? 'var(--success)' : ($pct >= 50 ? 'var(--gold)' : 'var(--danger)')); ?>
                            <tr>
                                <td class="c text-muted"><?= $i + 1 ?></td>
                                <td><?php if ($p['id']): ?><a href="sales_staff_report.php?id=<?= $p['id'] ?>&month=<?= $t_month ?>&year=<?= $t_year ?>" target="_blank"><?= $h($p['name']) ?> <i class="bi bi-box-arrow-up-right small text-muted"></i></a><?php else: ?><?= $h($p['name']) ?><?php endif; ?></td>
                                <td class="c"><?= $p['events'] ?: '–' ?></td>
                                <td class="r fw-semibold"><?= $baht($p['revenue']) ?></td>
                                <td class="r"><?= $p['deposit'] > 0 ? $baht($p['deposit']) : '–' ?></td>
                                <td class="r"><?= $p['target'] > 0 ? $baht($p['target']) : '<span class="text-muted">ยังไม่ตั้ง</span>' ?></td>
                                <td>
                                    <?php if ($pct !== null): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="flex:1;height:8px;background:var(--sunken);border-radius:99px;overflow:hidden"><span style="display:block;height:100%;width:<?= min(100, $pct) ?>%;background:<?= $col ?>;border-radius:99px"></span></div>
                                            <b class="num" style="width:44px;text-align:right"><?= $pct ?>%</b>
                                        </div>
                                    <?php else: ?><span class="text-muted small">–</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>ตั้งเป้าหมายรายเดือน</h2></div>
                <div class="ins-card-bd">
                    <form action="/manage_banquet/api/save_sales_target.php" method="POST">
                        <input type="hidden" name="redirect" value="<?= $h($_SERVER['REQUEST_URI']) ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">พนักงานขาย</label>
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">-- เลือกพนักงาน --</option>
                                <?php while ($s = $staffs->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= $h($s['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">เป้าหมายรายได้ (บาท)</label>
                            <input type="number" name="target_amount" min="0" step="1000" class="form-control form-control-sm" placeholder="เช่น 500000" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">เดือน</label>
                                <select name="target_month" class="form-select form-select-sm">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m === $t_month ? 'selected' : '' ?>><?= RPT_TH_MONTHS[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">ปี</label>
                                <select name="target_year" class="form-select form-select-sm">
                                    <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 1; $y++): ?>
                                        <option value="<?= $y ?>" <?= $y === $t_year ? 'selected' : '' ?>><?= $y + 543 ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold btn-sm w-100"><i class="bi bi-save me-1"></i>บันทึกเป้าหมาย</button>
                        <div class="form-text">ตั้งซ้ำเดือนเดิมของคนเดิม = แก้ไขเป้าเดิม</div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>เป้าหมายที่ตั้งไว้</h2><span class="hint"><?= count($targets) ?> รายการ</span></div>
                <div class="ins-card-bd pt-0 table-responsive" style="max-height:420px">
                    <?php if (!$targets): ?>
                        <div class="ins-empty">ยังไม่มีการตั้งเป้าหมาย</div>
                    <?php else: ?>
                        <table class="ins-tbl">
                            <thead><tr><th>พนักงาน</th><th>เดือน / ปี</th><th class="r">เป้าหมาย</th><th class="r" style="width:60px"></th></tr></thead>
                            <tbody>
                                <?php foreach ($targets as $t): ?>
                                    <tr>
                                        <td><?= $h($t['name']) ?></td>
                                        <td><?= RPT_TH_MONTHS[(int) $t['target_month']] ?> <?= $t['target_year'] + 543 ?></td>
                                        <td class="r fw-semibold"><?= $baht($t['target_amount']) ?></td>
                                        <td class="r">
                                            <a href="/manage_banquet/api/save_sales_target.php?delete_id=<?= $t['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                               class="btn btn-sm btn-outline-danger border-0" data-confirm="ลบเป้าหมายนี้?" aria-label="ลบ"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="insDrawer" aria-labelledby="insDrawerTitle">
    <div class="offcanvas-header">
        <div><h5 class="offcanvas-title" id="insDrawerTitle"></h5><div class="d-sum"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="ปิด"></button>
    </div>
    <div class="offcanvas-body"></div>
    <div class="d-foot d-none"></div>
</div>

<script>window.INS_DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/insights.js?v=<?= @filemtime(__DIR__ . '/assets/js/insights.js') ?>"></script>

<?php include "footer.php"; ?>
