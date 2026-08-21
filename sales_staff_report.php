<?php
include "config.php";
include_once __DIR__ . '/includes/menu_cost_lookup.php';
include_once __DIR__ . '/includes/kitchen_cost.php';

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'staff', 'gm', 'sale'])) {
    die("ไม่มีสิทธิ์เข้าถึงหน้านี้");
}

$staff_id = intval($_GET['id'] ?? 0);
$month = intval($_GET['month'] ?? date('n'));
$year  = intval($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int) date('n');
if ($year < 2000) $year = (int) date('Y');
if ($staff_id <= 0) { die("ไม่พบพนักงาน"); }

$stmt = $conn->prepare("SELECT id, name, role, username FROM users WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
if (!$staff) { die("ไม่พบพนักงาน"); }

// เป้าหมายของเดือนนี้ (ถ้ามี)
$stmt = $conn->prepare("SELECT target_amount FROM sales_targets WHERE user_id = ? AND target_month = ? AND target_year = ?");
$stmt->bind_param("iii", $staff_id, $month, $year);
$stmt->execute();
$target_row = $stmt->get_result()->fetch_assoc();
$target_amount = (float) ($target_row['target_amount'] ?? 0);

// ===== 1. รายการจองห้องประชุม (จุดเริ่มต้นของ funnel) =====
$stmt = $conn->prepare("SELECT rb.*, mr.room_name, c.company_name
                         FROM room_bookings rb
                         LEFT JOIN meeting_rooms mr ON rb.room_id = mr.id
                         LEFT JOIN companies c ON rb.company_id = c.id
                         WHERE rb.created_by_id = ? AND MONTH(rb.created_at) = ? AND YEAR(rb.created_at) = ?
                         ORDER BY rb.created_at ASC");
$stmt->bind_param("iii", $staff_id, $month, $year);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== 2. ใบเสนอราคา =====
$stmt = $conn->prepare("SELECT q.*, cust.cust_name
                         FROM quotations q
                         LEFT JOIN customers cust ON q.customer_id = cust.id
                         WHERE q.created_by = ? AND MONTH(q.created_at) = ? AND YEAR(q.created_at) = ?
                         ORDER BY q.created_at ASC");
$stmt->bind_param("iii", $staff_id, $month, $year);
$stmt->execute();
$quotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$quote_total_value = array_sum(array_column($quotes, 'grand_total'));
$quote_approved_count = count(array_filter($quotes, fn($q) => $q['status'] === 'Approved'));

// ===== 3. งาน EO ที่เปิดจริง + ต้นทุน/กำไร/ROI ต่องาน (ROI ที่ sales คุมได้ = ราคาขาย vs ต้นทุนจริงของงานนั้น) =====
$stmt = $conn->prepare("SELECT f.id, f.function_code, f.function_name, f.booking_name, f.pax, f.total_amount, f.deposit,
                                f.approve, f.status, f.start_time, c.company_name
                         FROM functions f
                         LEFT JOIN companies c ON f.company_id = c.id
                         WHERE f.created_by_id = ? AND MONTH(f.created_at) = ? AND YEAR(f.created_at) = ?
                         ORDER BY f.created_at ASC");
$stmt->bind_param("iii", $staff_id, $month, $year);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_events  = count($jobs);
$total_revenue = 0;
$total_deposit = 0;
$total_cost_all = 0;
$total_profit_all = 0;
$roi_job_count = 0;

foreach ($jobs as $i => $j) {
    $is_active = $j['approve'] == 1 && $j['status'] !== 'Cancelled';

    // ต้นทุนครัว (auto) ของงานนี้
    $kitchen = getKitchenCostDetailed($conn, $j['id']);
    $kitchen_cost = $kitchen['sum_main_cost'] + $kitchen['sum_break_cost'];

    // รายรับ/ต้นทุนเพิ่มเติมที่บันทึกในบัญชี (function_finance)
    $fin_res = $conn->query("SELECT type, amount FROM function_finance WHERE function_id = " . intval($j['id']));
    $extra_income = 0;
    $extra_cost = 0;
    while ($fr = $fin_res->fetch_assoc()) {
        if ($fr['type'] === 'income' || $fr['type'] === 'deposit') $extra_income += (float) $fr['amount'];
        if ($fr['type'] === 'cost') $extra_cost += (float) $fr['amount'];
    }

    $main_price   = (float) $j['total_amount'];
    $job_income   = $main_price + $extra_income;
    $job_cost     = $kitchen_cost + $extra_cost;
    $mgmt_fee     = $job_income * 0.03;
    $job_profit   = $job_income - $job_cost - $mgmt_fee;
    $job_roi      = $job_cost > 0 ? ($job_profit / $job_cost) * 100 : null;

    $jobs[$i]['job_cost']   = $job_cost;
    $jobs[$i]['job_profit'] = $job_profit;
    $jobs[$i]['job_roi']    = $job_roi;

    if ($is_active) {
        $total_revenue += $main_price;
        // ROI รวมนับเฉพาะงานที่มีข้อมูลต้นทุนบันทึกไว้จริง (job_cost > 0)
        // ไม่งั้นงานเก่าที่ไม่เคยกรอกเมนู/เบรกเลยจะเอารายรับมาหารต้นทุน ~0 ได้ ROI หลักแสน% ซึ่งไม่มีความหมาย
        if ($job_cost > 0) {
            $roi_job_count++;
            $total_cost_all   += $job_cost;
            $total_profit_all += $job_profit;
        }
    }
    if ($j['approve'] == 1 && !in_array($j['status'], ['Cancelled', 'Completed'])) {
        $total_deposit += (float) $j['deposit'];
    }
}
$active_job_count = count(array_filter($jobs, fn($j) => $j['approve'] == 1 && $j['status'] !== 'Cancelled'));
$overall_roi = $total_cost_all > 0 ? round(($total_profit_all / $total_cost_all) * 100, 1) : null;
$achieve_pct = $target_amount > 0 ? round(($total_revenue / $target_amount) * 100, 1) : null;

$status_label = [
    'Pending'     => 'รอดำเนินการ',
    'Confirmed'   => 'อนุมัติแล้ว',
    'In Progress' => 'กำลังดำเนินการ',
    'Completed'   => 'เสร็จสิ้น',
    'Cancelled'   => 'ยกเลิก',
    'active'      => 'ใช้งานอยู่',
    'cancelled'   => 'ยกเลิก',
    'Draft'       => 'ฉบับร่าง',
    'Sent'        => 'ส่งแล้ว',
    'Approved'    => 'อนุมัติแล้ว',
];
$status_color = [
    'Pending'     => '#fff3cd',
    'Confirmed'   => '#d4edda',
    'In Progress' => '#cfe2ff',
    'Completed'   => '#e2e3e5',
    'Cancelled'   => '#f8d7da',
    'active'      => '#d4edda',
    'cancelled'   => '#f8d7da',
    'Draft'       => '#e2e3e5',
    'Sent'        => '#cfe2ff',
    'Approved'    => '#d4edda',
];

$thai_months = ['', 'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$fmtDate = fn($v) => (!$v || $v === '0000-00-00 00:00:00' || $v === '0000-00-00') ? '-' : date('d/m/Y', strtotime($v));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>รายงานผลงาน - <?= $h($staff['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Sarabun', 'Tahoma', sans-serif; padding: 15px 25px; color: #000; font-size: 12.5px; background: #fff; }
    .no-print { text-align: right; margin-bottom: 10px; }
    .no-print button { padding: 6px 18px; font-size: 14px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #fff; margin-left: 5px; }
    .no-print button.btn-print { background: #1a73e8; color: #fff; border-color: #1a73e8; }

    .header { text-align: center; margin-bottom: 12px; border-bottom: 3px double #000; padding-bottom: 10px; }
    .header h2 { font-size: 20px; letter-spacing: 1px; }
    .header p { font-size: 11px; color: #555; }

    .info-bar { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; color: #333; }

    .section-title { font-weight: 700; font-size: 13px; background: #e8e8e8; padding: 4px 10px; margin: 14px 0 6px; border-left: 4px solid #333; display: flex; justify-content: space-between; }

    .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 6px; }
    .summary-box { border: 1px solid #ccc; padding: 8px 10px; border-radius: 4px; }
    .summary-box h6 { font-size: 10px; color: #555; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .3px; }
    .summary-box .big-num { font-size: 16px; font-weight: 700; font-family: 'Consolas', monospace; }

    .progress-track { background: #eee; border-radius: 6px; height: 10px; margin-top: 8px; overflow: hidden; }
    .progress-fill { height: 100%; }

    table.data-table { width: 100%; border-collapse: collapse; font-size: 11.5px; margin-top: 4px; }
    table.data-table th, table.data-table td { border: 1px solid #999; padding: 4px 7px; }
    table.data-table th { background: #4472C4; color: #fff; font-weight: 600; text-align: center; }
    table.data-table td.amount { text-align: right; font-family: 'Consolas', monospace; }
    table.data-table td.total-row { background: #DAEEF3; font-weight: 700; }
    table.data-table tr.alt { background: #f9f9f9; }

    .text-red { color: #c00000; }
    .text-green { color: #006100; }
    .text-blue { color: #1f4e79; }
    .count-tag { font-weight: 400; font-size: 11px; color: #555; }

    .signature-section { display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid; }
    .sign-box { width: 40%; text-align: center; }
    .sign-box .sig-area { height: 50px; }
    .sign-box .line { border-top: 1px solid #000; padding-top: 4px; font-size: 11px; }
    .sign-box .name { font-weight: 600; font-size: 12px; }

    .footer-note { font-size: 10px; color: #888; text-align: center; margin-top: 16px; border-top: 1px solid #ddd; padding-top: 6px; }

    @media print {
        .no-print { display: none !important; }
        body { padding: 6px 10px; font-size: 10.5px; }
        @page { size: A4 landscape; margin: 8mm; }
        .section-title { margin-top: 8px; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print();" class="btn-print">🖨️ พิมพ์</button>
    <button onclick="window.close();">ปิด</button>
</div>

<div class="header">
    <h2>รายงานสรุปผลงานพนักงานขาย (ครบวงจร: จอง → ใบเสนอราคา → EO → ROI)</h2>
    <p><?= $h($staff['name']) ?> · <?= $thai_months[$month] ?> <?= $year + 543 ?> | พิมพ์เมื่อ <?= date('d/m/Y H:i') ?></p>
</div>

<div class="info-bar">
    <div><strong>พนักงาน:</strong> <?= $h($staff['name']) ?> (<?= $h($staff['role']) ?>)</div>
    <div><strong>รอบรายงาน:</strong> <?= $thai_months[$month] ?> <?= $year + 543 ?></div>
</div>

<div class="section-title">สรุปภาพรวม (Overview)</div>
<div class="summary-grid">
    <div class="summary-box">
        <h6>การจองห้อง</h6>
        <div class="big-num"><?= number_format(count($bookings)) ?></div>
    </div>
    <div class="summary-box">
        <h6>ใบเสนอราคา</h6>
        <div class="big-num"><?= number_format(count($quotes)) ?> <span class="count-tag">(อนุมัติ <?= $quote_approved_count ?>)</span></div>
    </div>
    <div class="summary-box">
        <h6>งานที่เปิด EO</h6>
        <div class="big-num"><?= number_format($total_events) ?></div>
    </div>
    <div class="summary-box">
        <h6>ยอดขาย (Revenue)</h6>
        <div class="big-num text-green">฿<?= number_format($total_revenue, 0) ?></div>
    </div>
    <div class="summary-box">
        <h6>ROI รวม (ที่คุมได้)</h6>
        <div class="big-num" style="color:<?= $overall_roi === null ? '#555' : ($overall_roi >= 0 ? '#006100' : '#c00000') ?>">
            <?= $overall_roi === null ? '-' : number_format($overall_roi, 1) . '%' ?>
        </div>
        <div style="font-size:9.5px;color:#999;margin-top:2px;">จาก <?= $roi_job_count ?>/<?= $active_job_count ?> งานที่มีข้อมูลต้นทุน</div>
    </div>
</div>
<?php if ($target_amount > 0): ?>
<div class="summary-box" style="margin-top:8px;">
    <h6>ความสำเร็จเทียบเป้าหมาย (เป้า ฿<?= number_format($target_amount, 0) ?>)</h6>
    <div class="big-num" style="color:<?= $achieve_pct >= 100 ? '#006100' : ($achieve_pct >= 50 ? '#a66a00' : '#c00000') ?>"><?= number_format($achieve_pct, 1) ?>%</div>
    <div class="progress-track">
        <div class="progress-fill" style="width:<?= min($achieve_pct, 100) ?>%;background:<?= $achieve_pct >= 100 ? '#198754' : ($achieve_pct >= 50 ? '#ffc107' : '#dc3545') ?>;"></div>
    </div>
</div>
<?php endif; ?>

<!-- 1. การจองห้อง -->
<div class="section-title">1. รายการจองห้องประชุม (Room Bookings) — จุดเริ่มต้นของงาน</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:10%">รหัสจอง</th>
            <th style="width:22%">ชื่องาน</th>
            <th style="width:16%">หน่วยงาน/ผู้จอง</th>
            <th style="width:14%">ห้อง/โรงแรม</th>
            <th style="width:10%">วันที่จัดงาน</th>
            <th style="width:8%">PAX</th>
            <th style="width:10%">ราคาขาย</th>
            <th style="width:10%">สถานะ</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($bookings)): ?>
        <tr><td colspan="8" style="text-align:center;padding:10px;color:#999;">ไม่มีการจองห้องในเดือนนี้</td></tr>
        <?php else: ?>
            <?php foreach ($bookings as $i => $b): ?>
            <tr class="<?= $i % 2 == 1 ? 'alt' : '' ?>">
                <td><?= $h($b['booking_code']) ?></td>
                <td><?= $h($b['event_name']) ?></td>
                <td><?= $h($b['organization'] ?: $b['booking_name'] ?: '-') ?></td>
                <td><?= $h($b['room_name'] ?: '-') ?><br><span style="color:#777;font-size:10px;"><?= $h($b['company_name'] ?: '') ?></span></td>
                <td style="white-space:nowrap;"><?= $fmtDate($b['start_time']) ?></td>
                <td style="text-align:center;"><?= number_format($b['pax']) ?></td>
                <td class="amount"><?= $b['selling_price'] !== null ? number_format($b['selling_price'], 2) : '-' ?></td>
                <td style="text-align:center;">
                    <span style="background:<?= $status_color[$b['status']] ?? '#e2e3e5' ?>;padding:2px 8px;border-radius:3px;font-size:10px;">
                        <?= $h($status_label[$b['status']] ?? $b['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- 2. ใบเสนอราคา -->
<div class="section-title">2. ใบเสนอราคา (Quotations)</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:12%">เลขที่</th>
            <th style="width:24%">ชื่องาน</th>
            <th style="width:18%">ลูกค้า</th>
            <th style="width:10%">วันที่จัดงาน</th>
            <th style="width:12%">ยอดรวม</th>
            <th style="width:12%">สถานะเอกสาร</th>
            <th style="width:12%">Workflow</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($quotes)): ?>
        <tr><td colspan="7" style="text-align:center;padding:10px;color:#999;">ไม่มีใบเสนอราคาในเดือนนี้</td></tr>
        <?php else: ?>
            <?php foreach ($quotes as $i => $q): ?>
            <tr class="<?= $i % 2 == 1 ? 'alt' : '' ?>">
                <td><?= $h($q['quote_no']) ?></td>
                <td><?= $h($q['event_name']) ?></td>
                <td><?= $h($q['cust_name'] ?: '-') ?></td>
                <td style="white-space:nowrap;"><?= $fmtDate($q['event_date']) ?></td>
                <td class="amount"><?= number_format($q['grand_total'], 2) ?></td>
                <td style="text-align:center;">
                    <span style="background:<?= $status_color[$q['status']] ?? '#e2e3e5' ?>;padding:2px 8px;border-radius:3px;font-size:10px;">
                        <?= $h($status_label[$q['status']] ?? $q['status']) ?>
                    </span>
                </td>
                <td style="font-size:10.5px;"><?= $h($q['workflow_status'] ?: '-') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($quotes)): ?>
    <tfoot>
        <tr>
            <td colspan="4" class="total-row" style="text-align:right;">รวมมูลค่าใบเสนอราคาทั้งหมด</td>
            <td class="amount total-row"><?= number_format($quote_total_value, 2) ?></td>
            <td colspan="2" class="total-row"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<!-- 3. งาน EO + ต้นทุน/กำไร/ROI ต่องาน -->
<div class="section-title">3. งานที่เปิด EO พร้อมต้นทุน/กำไร/ROI ต่องาน (Jobs &amp; Job-level ROI)</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:8%">รหัสงาน</th>
            <th style="width:16%">ชื่องาน</th>
            <th style="width:12%">ลูกค้า/โรงแรม</th>
            <th style="width:8%">วันที่จัดงาน</th>
            <th style="width:9%">ราคาขาย</th>
            <th style="width:9%">เงินมัดจำ</th>
            <th style="width:9%">ต้นทุนรวม</th>
            <th style="width:9%">กำไร</th>
            <th style="width:7%">ROI</th>
            <th style="width:8%">สถานะ</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($jobs)): ?>
        <tr><td colspan="10" style="text-align:center;padding:10px;color:#999;">ไม่มีงานที่เปิด EO ในเดือนนี้</td></tr>
        <?php else: ?>
            <?php foreach ($jobs as $i => $j): ?>
            <tr class="<?= $i % 2 == 1 ? 'alt' : '' ?>">
                <td><?= $h($j['function_code'] ?: '-') ?></td>
                <td><?= $h($j['function_name']) ?></td>
                <td><?= $h($j['booking_name'] ?: '-') ?><br><span style="color:#777;font-size:10px;"><?= $h($j['company_name'] ?: '') ?></span></td>
                <td style="white-space:nowrap;"><?= $fmtDate($j['start_time']) ?></td>
                <td class="amount"><?= number_format($j['total_amount'], 2) ?></td>
                <td class="amount"><?= number_format($j['deposit'], 2) ?></td>
                <td class="amount text-red"><?= number_format($j['job_cost'], 2) ?></td>
                <td class="amount" style="color:<?= $j['job_profit'] >= 0 ? '#006100' : '#c00000' ?>"><?= number_format($j['job_profit'], 2) ?></td>
                <td class="amount" style="font-weight:700;color:<?= $j['job_roi'] === null ? '#999' : ($j['job_roi'] >= 0 ? '#006100' : '#c00000') ?>">
                    <?= $j['job_roi'] === null ? '-' : number_format($j['job_roi'], 1) . '%' ?>
                </td>
                <td style="text-align:center;">
                    <span style="background:<?= $status_color[$j['status']] ?? '#e2e3e5' ?>;padding:2px 6px;border-radius:3px;font-size:10px;">
                        <?= $h($status_label[$j['status']] ?? $j['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($jobs)): ?>
    <tfoot>
        <tr>
            <td colspan="4" class="total-row" style="text-align:right;">รวม (เฉพาะงานที่อนุมัติและยังไม่ถูกยกเลิก)</td>
            <td class="amount total-row text-green"><?= number_format($total_revenue, 2) ?></td>
            <td class="amount total-row text-blue"><?= number_format($total_deposit, 2) ?></td>
            <td class="amount total-row text-red"><?= number_format($total_cost_all, 2) ?></td>
            <td class="amount total-row" style="color:<?= $total_profit_all >= 0 ? '#006100' : '#c00000' ?>"><?= number_format($total_profit_all, 2) ?></td>
            <td class="amount total-row" style="color:<?= $overall_roi === null ? '#555' : ($overall_roi >= 0 ? '#006100' : '#c00000') ?>">
                <?= $overall_roi === null ? '-' : number_format($overall_roi, 1) . '%' ?>
            </td>
            <td class="total-row"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
<div style="font-size:10px;color:#777;margin-top:4px;">
    * ต้นทุนรวม = ต้นทุนอาหาร/เบรกอัตโนมัติจากรายการที่บันทึกในงาน + ค่าใช้จ่ายอื่นที่บันทึกในบัญชี + ค่าบริหาร 3% ของรายรับ ·
    ROI ต่องาน = กำไร ÷ ต้นทุนรวม × 100 (คำนวณเฉพาะงานที่มีต้นทุนมากกว่า 0 — งานที่ยังไม่ได้กรอกเมนู/เบรกจะไม่มี ROI ให้)<br>
    * ROI รวมด้านบนคำนวณจากงานที่มีข้อมูลต้นทุนบันทึกไว้จริงเท่านั้น (<?= $roi_job_count ?> จาก <?= $active_job_count ?> งานที่อนุมัติแล้ว)
    — งานที่เหลือยังไม่ได้กรอกรายการเมนู/เบรก จึงไม่ถูกนับใน ROI รวม เพื่อไม่ให้ตัวเลขบิดเบือน
</div>

<div class="signature-section">
    <div class="sign-box">
        <div class="sig-area"></div>
        <div class="line">
            <div class="name">พนักงานขาย</div>
            <div><?= $h($staff['name']) ?></div>
        </div>
    </div>
    <div class="sign-box">
        <div class="sig-area"></div>
        <div class="line">
            <div class="name">ผู้ตรวจสอบ / อนุมัติ</div>
            <div>วันที่ .................................</div>
        </div>
    </div>
</div>

<div class="footer-note">
    รายงานนี้สร้างจากระบบจัดการงานเลี้ยง | <?= date('d/m/Y H:i:s') ?>
</div>

</body>
</html>
