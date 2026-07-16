<?php
include "../config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { die("Invalid ID"); }

// ── ดึงข้อมูลงาน + ลูกค้า + บริษัท ──
$sql = "SELECT f.*, c.company_name, cust.cust_name, cust.cust_tax_id, cust.cust_phone, cust.cust_email,
               ft.type_name as function_type_name, r.room_name
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id
        LEFT JOIN customers cust ON f.customer_id = cust.id
        LEFT JOIN function_types ft ON f.function_type_id = ft.id
        LEFT JOIN meeting_rooms r ON f.room_id = r.id
        WHERE f.id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
if (!$data) { die("Not found"); }

// ── ดึงข้อมูลการเงิน ──
$finances = [];
$total_income = 0; $total_deposit = 0; $extra_cost = 0;
$post_income = 0; $post_cost = 0;
$pre_income = 0; $pre_cost_items = 0;

$sql_fin = "SELECT * FROM function_finance WHERE function_id = $id ORDER BY transaction_date ASC, id ASC";
$res_fin = $conn->query($sql_fin);
while ($f = $res_fin->fetch_assoc()) {
    if ($f['is_post_approval']) {
        if ($f['type'] == 'income' || $f['type'] == 'deposit') $post_income += $f['amount'];
        else $post_cost += $f['amount'];
    } else {
        if ($f['type'] == 'income' || $f['type'] == 'deposit') $pre_income += $f['amount'];
        if ($f['type'] == 'cost') $pre_cost_items += $f['amount'];
    }
    if ($f['type'] == 'income' || $f['type'] == 'deposit') $total_income += $f['amount'];
    if ($f['type'] == 'deposit') $total_deposit += $f['amount'];
    if ($f['type'] == 'cost') $extra_cost += $f['amount'];
    $finances[] = $f;
}

// ── ต้นทุนครัว Detail ──
function getKitchenCostDetailed($conn, $function_id) {
    $main_list = []; $break_list = [];
    $sum_main = 0; $sum_main_cost = 0; $sum_break = 0; $sum_break_cost = 0;

    $sql_m = "SELECT menu_detail, menu_qty, menu_price, menu_cost FROM function_menus WHERE function_id = $function_id";
    $res_m = $conn->query($sql_m);
    while ($row = $res_m->fetch_assoc()) {
        $direct_price = (float)($row['menu_price'] ?? 0);
        $direct_cost = (float)($row['menu_cost'] ?? 0);
        $qty = (float)$row['menu_qty'];
        $lines = preg_split('/\r\n|\r|\n/', $row['menu_detail']);
        if ($direct_price > 0) {
            $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $lines[0] ?? ''));
            $total = $direct_price * $qty;
            $cost = $direct_cost > 0 ? $direct_cost * $qty : $total;
            $main_list[] = ['name' => $name ?: 'ค่าอาหาร', 'qty' => $qty, 'price' => $direct_price, 'cost' => $direct_cost > 0 ? $direct_cost : $direct_price, 'total' => $total, 'cost_total' => $cost];
            $sum_main += $total; $sum_main_cost += $cost;
        } else {
            foreach ($lines as $l) {
                $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                if (empty($name)) continue;
                $name_esc = $conn->real_escape_string($name);
                $q_p = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                $price = 0; $cost = 0;
                if ($p = $q_p->fetch_assoc()) { $price = (float)$p['price_per_pax']; $cost = (float)($p['cost_per_pax'] ?? 0); }
                $total = $price * $qty; $cost_total = $cost > 0 ? $cost * $qty : $total;
                $main_list[] = ['name' => $name, 'qty' => $qty, 'price' => $price, 'cost' => $cost > 0 ? $cost : $price, 'total' => $total, 'cost_total' => $cost_total];
                $sum_main += $total; $sum_main_cost += $cost_total;
            }
        }
    }

    $sql_k = "SELECT k_item, k_qty, k_price, k_cost FROM function_kitchens WHERE function_id = $function_id";
    $res_k = $conn->query($sql_k);
    while ($row = $res_k->fetch_assoc()) {
        $price = (float)($row['k_price'] ?? 0); $cost = (float)($row['k_cost'] ?? 0); $qty = (float)$row['k_qty'];
        if ($price > 0) {
            $total = $price * $qty; $cost_total = $cost > 0 ? $cost * $qty : $total;
            $break_list[] = ['name' => $row['k_item'], 'qty' => $qty, 'price' => $price, 'cost' => $cost > 0 ? $cost : $price, 'total' => $total, 'cost_total' => $cost_total];
            $sum_break += $total; $sum_break_cost += $cost_total;
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
                $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                if (empty($name)) continue;
                $name_esc = $conn->real_escape_string($name);
                $item_price = 0; $item_cost = 0;
                $q_b = $conn->query("SELECT break_price, break_cost FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_b->fetch_assoc()) { $item_price = (float)$p['break_price']; $item_cost = (float)($p['break_cost'] ?? 0); }
                else {
                    $q_d = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                    if ($d = $q_d->fetch_assoc()) { $item_price = (float)$d['price_per_pax']; $item_cost = (float)($d['cost_per_pax'] ?? 0); }
                }
                $total = $item_price * $qty; $cost_total = $item_cost > 0 ? $item_cost * $qty : $total;
                $break_list[] = ['name' => $name, 'qty' => $qty, 'price' => $item_price, 'cost' => $item_cost > 0 ? $item_cost : $item_price, 'total' => $total, 'cost_total' => $cost_total];
                $sum_break += $total; $sum_break_cost += $cost_total;
            }
        }
    }
    return compact('main_list', 'break_list', 'sum_main', 'sum_main_cost', 'sum_break', 'sum_break_cost');
}

$kitchen = getKitchenCostDetailed($conn, $id);
$kitchen_total = $kitchen['sum_main_cost'] + $kitchen['sum_break_cost'];
$main_price = (float) ($data['total_amount'] ?? 0);
$grand_total_income = $main_price + $total_income;
$total_cost = $extra_cost + $kitchen_total;
$management_fee = $grand_total_income * 0.03;
$profit = $grand_total_income - $total_cost - $management_fee;
$roi = ($total_cost > 0) ? ($profit / $total_cost) * 100 : 0;

$pre_total_cost = $pre_cost_items + $kitchen_total;
$pre_grand_income = $main_price + $pre_income;
$pre_management_fee = $pre_grand_income * 0.03;
$pre_profit = $pre_grand_income - $pre_total_cost - $pre_management_fee;
$pre_roi = ($pre_total_cost > 0) ? ($pre_profit / $pre_total_cost) * 100 : 0;

$post_profit = $post_income - $post_cost;
$post_roi = ($post_cost > 0) ? ($post_profit / $post_cost) * 100 : 0;

// ── Output Excel (HTML format) ──
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Finance_' . preg_replace('/[^a-zA-Z0-9ก-๙]/', '_', $data['function_name']) . '_' . date('Ymd') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$fmtDate = function($val) {
    if (!$val || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y', strtotime($val));
};
$fmtTime = function($val) {
    if (!$val || $val === '0000-00-00 00:00:00') return '-';
    return date('H:i', strtotime($val));
};
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]>
<xml>
    <x:ExcelWorkbook>
        <x:ExcelWorksheets>
            <x:ExcelWorksheet>
                <x:Name>Finance Report</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
            </x:ExcelWorksheet>
        </x:ExcelWorksheets>
    </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    table { border-collapse: collapse; width: 100%; font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 12px; }
    th, td { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
    th { background: #4472C4; color: #fff; font-weight: bold; text-align: center; }
    .hdr-title { font-size: 18px; font-weight: bold; text-align: center; border: none; padding: 4px; }
    .hdr-sub { font-size: 11px; text-align: center; border: none; padding: 2px; }
    .label-cell { font-weight: bold; background: #f0f0f0; }
    .amount { text-align: right; }
    .text-red { color: #c00000; }
    .text-green { color: #006100; }
    .text-blue { color: #1f4e79; }
    .bg-yellow { background: #FFF2CC; }
    .bg-green { background: #d4edda; }
    .total-row { background: #DAEEF3; font-weight: bold; font-size: 13px; }
    .section-sep { height: 8px; border: none; }
    .info-label { font-weight: bold; background: #f5f5f5; }
</style>
</head>
<body>

<table>
    <!-- ===== HEADER ===== -->
    <tr><td colspan="7" class="hdr-title">รายงานสรุปบัญชีงาน</td></tr>
    <tr><td colspan="7" class="hdr-sub"><?= htmlspecialchars($data['function_name']) ?> | <?= date('d/m/Y H:i') ?></td></tr>
    <tr><td colspan="7" class="section-sep"></td></tr>

    <tr><td colspan="7" class="section-sep"></td></tr>

    <!-- ===== รายละเอียดต้นทุนครัว ===== -->
    <?php if (!empty($kitchen['main_list']) || !empty($kitchen['break_list'])): ?>
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <td colspan="7" style="background:#e8e8e8;font-weight:bold;font-size:13px;">รายละเอียดต้นทุนอาหาร (Kitchen Cost Breakdown)</td>
    </tr>
    <?php if (!empty($kitchen['main_list'])): ?>
    <tr>
        <th colspan="7" style="text-align:left;background:#d6e4f0;">🍽️ รายการอาหารหลัก</th>
    </tr>
    <tr>
        <th style="text-align:left;width:30%;">รายการ</th>
        <th style="width:8%;">จำนวน</th>
        <th class="amount" style="width:12%;">ราคาขาย/หน่วย</th>
        <th class="amount" style="width:12%;">ราคาทุน/หน่วย</th>
        <th class="amount" style="width:12%;">ราคารวม</th>
        <th class="amount" style="width:12%;">ต้นทุนรวม</th>
        <th style="width:14%;">กำไร</th>
    </tr>
    <?php foreach ($kitchen['main_list'] as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td align="center"><?= number_format($r['qty']) ?></td>
        <td class="amount"><?= number_format($r['price'], 2) ?></td>
        <td class="amount text-red"><?= number_format($r['cost'], 2) ?></td>
        <td class="amount"><strong><?= number_format($r['total'], 2) ?></strong></td>
        <td class="amount text-red"><?= number_format($r['cost_total'], 2) ?></td>
        <td class="amount" style="color:<?= ($r['total'] - $r['cost_total']) >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($r['total'] - $r['cost_total'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#f0f7ff;font-weight:bold;">
        <td colspan="4" style="text-align:right;">รวมอาหารหลัก</td>
        <td class="amount text-blue"><?= number_format($kitchen['sum_main'], 2) ?></td>
        <td class="amount text-red"><?= number_format($kitchen['sum_main_cost'], 2) ?></td>
        <td class="amount" style="color:<?= ($kitchen['sum_main'] - $kitchen['sum_main_cost']) >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($kitchen['sum_main'] - $kitchen['sum_main_cost'], 2) ?></td>
    </tr>
    <?php endif; ?>

    <?php if (!empty($kitchen['break_list'])): ?>
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <th colspan="7" style="text-align:left;background:#fce4d6;">☕ รายการจัดเตรียม/เบรก</th>
    </tr>
    <tr>
        <th style="text-align:left;">รายการ</th>
        <th>จำนวน</th>
        <th class="amount">ราคาขาย/หน่วย</th>
        <th class="amount">ราคาทุน/หน่วย</th>
        <th class="amount">ราคารวม</th>
        <th class="amount">ต้นทุนรวม</th>
        <th>กำไร</th>
    </tr>
    <?php foreach ($kitchen['break_list'] as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td align="center"><?= number_format($r['qty']) ?></td>
        <td class="amount"><?= number_format($r['price'], 2) ?></td>
        <td class="amount text-red"><?= number_format($r['cost'], 2) ?></td>
        <td class="amount"><strong><?= number_format($r['total'], 2) ?></strong></td>
        <td class="amount text-red"><?= number_format($r['cost_total'], 2) ?></td>
        <td class="amount" style="color:<?= ($r['total'] - $r['cost_total']) >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($r['total'] - $r['cost_total'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#fff8f5;font-weight:bold;">
        <td colspan="4" style="text-align:right;">รวมเบรก/จัดเตรียม</td>
        <td class="amount text-red"><?= number_format($kitchen['sum_break'], 2) ?></td>
        <td class="amount text-red"><?= number_format($kitchen['sum_break_cost'], 2) ?></td>
        <td class="amount" style="color:<?= ($kitchen['sum_break'] - $kitchen['sum_break_cost']) >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($kitchen['sum_break'] - $kitchen['sum_break_cost'], 2) ?></td>
    </tr>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ===== รายรับ ===== -->
    <?php
    $incomes = array_filter($finances, fn($f) => $f['type'] == 'income' || $f['type'] == 'deposit');
    $costs = array_filter($finances, fn($f) => $f['type'] == 'cost');
    ?>
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <td colspan="7" style="background:#d4edda;font-weight:bold;font-size:13px;">รายรับ (Income)</td>
    </tr>
    <tr>
        <th style="width:12%;">วันที่</th>
        <th style="width:28%;text-align:left;">รายการ</th>
        <th style="width:12%;">ประเภท</th>
        <th class="amount" style="width:14%;">เงินมัดจำ</th>
        <th class="amount" style="width:14%;">รายรับ</th>
        <th style="width:10%;">ช่องทาง</th>
        <th style="width:10%;">ผู้บันทึก</th>
    </tr>
    <?php if (empty($incomes)): ?>
    <tr><td colspan="7" style="text-align:center;">ไม่มีรายการ</td></tr>
    <?php else: ?>
        <?php foreach ($incomes as $f): ?>
        <tr>
            <td><?= $fmtDate($f['transaction_date']) ?></td>
            <td><?= htmlspecialchars($f['detail']) ?></td>
            <td style="text-align:center;">
                <?= $f['type'] == 'deposit' ? 'มัดจำ' : 'รายรับ' ?>
                <?= $f['is_post_approval'] ? ' (หลังอนุมัติ)' : '' ?>
            </td>
            <td class="amount"><?= $f['type'] == 'deposit' ? number_format($f['amount'], 2) : '-' ?></td>
            <td class="amount text-green"><?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?></td>
            <td><?= htmlspecialchars($f['payment_method'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    <tr style="background:#d4edda;font-weight:bold;">
        <td colspan="3" style="text-align:right;">รวม</td>
        <td class="amount" style="font-size:13px;"><?= number_format($total_deposit, 2) ?></td>
        <td class="amount text-green" style="font-size:13px;"><?= number_format($total_income - $total_deposit, 2) ?></td>
        <td colspan="2" style="text-align:right;">รวม: <?= number_format($total_income, 2) ?></td>
    </tr>
    <?php endif; ?>

    <!-- ===== ค่าใช้จ่าย ===== -->
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <td colspan="7" style="background:#f8d7da;font-weight:bold;font-size:13px;">ค่าใช้จ่าย (Expenses)</td>
    </tr>
    <tr>
        <th style="width:12%;">วันที่</th>
        <th style="width:35%;text-align:left;">รายการ</th>
        <th class="amount" style="width:15%;">จำนวนเงิน</th>
        <th style="width:10%;">ช่องทาง</th>
        <th style="width:13%;">ผู้บันทึก</th>
        <th style="width:15%;">หมายเหตุ</th>
        <th style="width:0%;"></th>
    </tr>
    <?php if (empty($costs)): ?>
    <tr><td colspan="7" style="text-align:center;">ไม่มีรายการ</td></tr>
    <?php else: ?>
        <?php foreach ($costs as $f): ?>
        <tr>
            <td><?= $fmtDate($f['transaction_date']) ?></td>
            <td><?= htmlspecialchars($f['detail']) ?></td>
            <td class="amount text-red"><?= number_format($f['amount'], 2) ?></td>
            <td><?= htmlspecialchars($f['payment_method'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
            <td><?= $f['is_post_approval'] ? 'หลังอนุมัติ' : '' ?></td>
            <td></td>
        </tr>
        <?php endforeach; ?>
    <tr style="background:#f8d7da;font-weight:bold;">
        <td colspan="2" style="text-align:right;">รวมค่าใช้จ่าย</td>
        <td class="amount text-red" style="font-size:13px;"><?= number_format($extra_cost, 2) ?></td>
        <td colspan="4"></td>
    </tr>
    <?php endif; ?>

    <!-- ===== สรุปผลการเงิน ===== -->
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <td colspan="7" style="background:#e8e8e8;font-weight:bold;font-size:13px;">สรุปผลการเงิน (Financial Summary)</td>
    </tr>
    <tr>
        <th colspan="2" style="text-align:left;width:35%;">รายการ</th>
        <th class="amount" style="width:18%;">จำนวนเงิน</th>
        <th colspan="2" style="text-align:left;width:20%;">รายการ</th>
        <th class="amount" style="width:14%;">จำนวนเงิน</th>
        <th style="width:13%;">หมายเหตุ</th>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">ราคาขายงาน</td>
        <td class="amount text-blue"><?= number_format($main_price, 2) ?></td>
        <td colspan="2" class="label-cell">ต้นทุนอาหารหลัก (ครัว)</td>
        <td class="amount text-red"><?= number_format($kitchen_total, 2) ?></td>
        <td>แยก: อาหาร <?= number_format($kitchen['sum_main_cost'], 2) ?></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายรับเพิ่มเติม</td>
        <td class="amount text-green"><?= number_format($total_income, 2) ?></td>
        <td colspan="2" class="label-cell">ค่าใช้จ่ายอื่นๆ</td>
        <td class="amount text-red"><?= number_format($extra_cost, 2) ?></td>
        <td><?= $post_cost > 0 ? '(หลังอนุมัติ ' . number_format($post_cost, 2) . ')' : '' ?></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">เงินมัดจำรวม</td>
        <td class="amount text-blue"><?= number_format($total_deposit, 2) ?></td>
        <td colspan="2" class="label-cell">ต้นทุนรวมทั้งสิ้น</td>
        <td class="amount fw-bold text-red" style="font-size:13px;"><?= number_format($total_cost, 2) ?></td>
        <td></td>
    </tr>
    <tr class="bg-yellow">
        <td colspan="2" class="label-cell" style="font-size:13px;">รวมรายรับทั้งหมด</td>
        <td class="amount fw-bold text-blue" style="font-size:13px;"><?= number_format($grand_total_income, 2) ?></td>
        <td colspan="2" class="label-cell">ค่าบริหาร 3%</td>
        <td class="amount text-red"><?= number_format($management_fee, 2) ?></td>
        <td></td>
    </tr>
    <tr class="bg-green">
        <td colspan="2" class="label-cell" style="font-size:14px;">กำไรสุทธิ</td>
        <td class="amount fw-bold" style="font-size:14px;color:<?= $profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($profit, 2) ?></td>
        <td colspan="2" class="label-cell" style="font-size:14px;">ROI (%)</td>
        <td class="amount fw-bold" style="font-size:14px;"><?= number_format($roi, 2) ?>%</td>
        <td><?= $profit >= 0 ? 'มีกำไร' : 'ขาดทุน' ?></td>
    </tr>
    <!-- ROI ก่อน/หลังอนุมัติ -->
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <th colspan="7" style="text-align:left;background:#e8e8e8;">การเปรียบเทียบ ROI ก่อน/หลังอนุมัติ</th>
    </tr>
    <tr>
        <th colspan="2" style="text-align:left;">รายการ</th>
        <th class="amount">ก่อนอนุมัติ</th>
        <th class="amount">หลังอนุมัติ</th>
        <th colspan="2" class="amount">รวมทั้งสิ้น</th>
        <th></th>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายรับ (ไม่รวมราคาขาย)</td>
        <td class="amount"><?= number_format($pre_income, 2) ?></td>
        <td class="amount text-green"><?= number_format($post_income, 2) ?></td>
        <td colspan="2" class="amount fw-bold"><?= number_format($total_income, 2) ?></td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายจ่าย</td>
        <td class="amount"><?= number_format($pre_cost_items, 2) ?></td>
        <td class="amount text-red"><?= number_format($post_cost, 2) ?></td>
        <td colspan="2" class="amount fw-bold"><?= number_format($extra_cost, 2) ?></td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">กำไร</td>
        <td class="amount" style="color:<?= $pre_profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($pre_profit, 2) ?></td>
        <td class="amount" style="color:<?= $post_profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($post_profit, 2) ?></td>
        <td colspan="2" class="amount fw-bold" style="color:<?= $profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($profit, 2) ?></td>
        <td></td>
    </tr>
    <tr class="bg-yellow">
        <td colspan="2" class="label-cell" style="font-size:13px;">ROI</td>
        <td class="amount fw-bold" style="font-size:13px;"><?= number_format($pre_roi, 2) ?>%</td>
        <td class="amount fw-bold" style="font-size:13px;color:<?= $post_roi >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($post_roi, 2) ?>%</td>
        <td colspan="2" class="amount fw-bold" style="font-size:14px;"><?= number_format($roi, 2) ?>%</td>
        <td></td>
    </tr>

    <!-- ===== ลงท้าย ===== -->
    <tr><td colspan="7" class="section-sep"></td></tr>
    <tr>
        <td colspan="7" style="text-align:center;border:none;font-size:10px;color:#888;">
            รายงานนี้สร้างจากระบบจัดการงานเลี้ยง | <?= date('d/m/Y H:i:s') ?>
        </td>
    </tr>
</table>

</body>
</html>
