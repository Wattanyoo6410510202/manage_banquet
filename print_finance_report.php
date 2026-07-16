<?php
include "config.php";
$id = intval($_GET['id'] ?? 0);
if (!$id) { die("Invalid ID"); }

// ดึงข้อมูลงาน + ลูกค้า + บริษัท
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
if (!$data) { die("ไม่พบข้อมูลงานนี้"); }

// ดึงรายการบัญชี
$sql_fin = "SELECT * FROM function_finance WHERE function_id = $id ORDER BY transaction_date ASC, id ASC";
$res_fin = $conn->query($sql_fin);
$finances = [];
$total_income = 0;
$total_deposit = 0;
$extra_cost = 0;
$post_income = 0;
$post_cost = 0;
$pre_income = 0;
$pre_cost_items = 0;

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

// ต้นทุนครัว (Auto)
function getKitchenCostDetailed($conn, $function_id) {
    $main_list = [];
    $break_list = [];
    $sum_main = 0;
    $sum_main_cost = 0;
    $sum_break = 0;
    $sum_break_cost = 0;

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
            $sum_main += $total;
            $sum_main_cost += $cost;
        } else {
            foreach ($lines as $l) {
                $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                if (empty($name)) continue;
                $name_esc = $conn->real_escape_string($name);
                $q_p = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                $price = 0; $cost = 0;
                if ($p = $q_p->fetch_assoc()) {
                    $price = (float)$p['price_per_pax'];
                    $cost = (float)($p['cost_per_pax'] ?? 0);
                }
                $total = $price * $qty;
                $cost_total = $cost > 0 ? $cost * $qty : $total;
                $main_list[] = ['name' => $name, 'qty' => $qty, 'price' => $price, 'cost' => $cost > 0 ? $cost : $price, 'total' => $total, 'cost_total' => $cost_total];
                $sum_main += $total;
                $sum_main_cost += $cost_total;
            }
        }
    }

    $sql_k = "SELECT k_item, k_qty, k_price, k_cost FROM function_kitchens WHERE function_id = $function_id";
    $res_k = $conn->query($sql_k);
    while ($row = $res_k->fetch_assoc()) {
        $price = (float)($row['k_price'] ?? 0);
        $cost = (float)($row['k_cost'] ?? 0);
        $qty = (float)$row['k_qty'];
        if ($price > 0) {
            $total = $price * $qty;
            $cost_total = $cost > 0 ? $cost * $qty : $total;
            $break_list[] = ['name' => $row['k_item'], 'qty' => $qty, 'price' => $price, 'cost' => $cost > 0 ? $cost : $price, 'total' => $total, 'cost_total' => $cost_total];
            $sum_break += $total;
            $sum_break_cost += $cost_total;
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
                $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                if (empty($name)) continue;
                $name_esc = $conn->real_escape_string($name);
                $item_price = 0; $item_cost = 0;
                $q_b = $conn->query("SELECT break_price, break_cost FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_b->fetch_assoc()) {
                    $item_price = (float)$p['break_price'];
                    $item_cost = (float)($p['break_cost'] ?? 0);
                } else {
                    $q_d = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                    if ($d = $q_d->fetch_assoc()) {
                        $item_price = (float)$d['price_per_pax'];
                        $item_cost = (float)($d['cost_per_pax'] ?? 0);
                    }
                }
                $total = $item_price * $qty;
                $cost_total = $item_cost > 0 ? $item_cost * $qty : $total;
                $break_list[] = ['name' => $name, 'qty' => $qty, 'price' => $item_price, 'cost' => $item_cost > 0 ? $item_cost : $item_price, 'total' => $total, 'cost_total' => $cost_total];
                $sum_break += $total;
                $sum_break_cost += $cost_total;
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

// ก่อนอนุมัติ
$pre_total_cost = $pre_cost_items + $kitchen_total;
$pre_grand_income = $main_price + $pre_income;
$pre_management_fee = $pre_grand_income * 0.03;
$pre_profit = $pre_grand_income - $pre_total_cost - $pre_management_fee;
$pre_roi = ($pre_total_cost > 0) ? ($pre_profit / $pre_total_cost) * 100 : 0;

// หลังอนุมัติ
$post_profit = $post_income - $post_cost;
$post_roi = ($post_cost > 0) ? ($post_profit / $post_cost) * 100 : 0;

$fmtDate = function($val) {
    if (!$val || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y', strtotime($val));
};
$fmtTime = function($val) {
    if (!$val || $val === '0000-00-00 00:00:00') return '-';
    return date('H:i', strtotime($val));
};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>รายงานสรุปบัญชี - <?= htmlspecialchars($data['function_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Sarabun', 'Tahoma', sans-serif; padding: 15px 25px; color: #000; font-size: 13px; background: #fff; }
    .no-print { text-align: right; margin-bottom: 10px; }
    .no-print button { padding: 6px 18px; font-size: 14px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #fff; margin-left: 5px; }
    .no-print button.btn-print { background: #1a73e8; color: #fff; border-color: #1a73e8; }

    .header { text-align: center; margin-bottom: 12px; border-bottom: 3px double #000; padding-bottom: 10px; }
    .header h2 { font-size: 20px; letter-spacing: 1px; }
    .header p { font-size: 11px; color: #555; }

    .info-bar { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; color: #333; }

    .section-title { font-weight: 700; font-size: 13px; background: #e8e8e8; padding: 4px 10px; margin: 12px 0 6px; border-left: 4px solid #333; }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 3px 20px; font-size: 12px; margin-bottom: 8px; }
    .info-grid .field { display: flex; gap: 5px; }
    .info-grid .field-label { font-weight: 600; white-space: nowrap; color: #333; }
    .info-grid .field-value { border-bottom: 1px dotted #999; flex: 1; min-height: 16px; }

    table.data-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 4px; }
    table.data-table th, table.data-table td { border: 1px solid #999; padding: 5px 8px; }
    table.data-table th { background: #4472C4; color: #fff; font-weight: 600; text-align: center; }
    table.data-table td.amount { text-align: right; font-family: 'Consolas', monospace; }
    table.data-table td.label { background: #f0f0f0; font-weight: 600; }
    table.data-table td.total-row { background: #DAEEF3; font-weight: 700; }
    table.data-table tr.alt { background: #f9f9f9; }

    .text-red { color: #c00000; }
    .text-green { color: #006100; }
    .text-blue { color: #1f4e79; }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 6px; }
    .two-col h6 { font-size: 12px; margin-bottom: 4px; }
    .two-col table { font-size: 11px; }
    .two-col table th { font-size: 11px; padding: 4px 6px; }
    .two-col table td { font-size: 11px; padding: 4px 6px; }

    .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; }
    .summary-box { border: 1px solid #ccc; padding: 8px 10px; border-radius: 4px; }
    .summary-box h6 { font-size: 12px; color: #555; margin-bottom: 4px; }
    .summary-box .big-num { font-size: 22px; font-weight: 700; font-family: 'Consolas', monospace; }

    .post-approval-table { margin-top: 6px; }

    .signature-section { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
    .sign-box { width: 40%; text-align: center; }
    .sign-box .sig-area { height: 55px; display: flex; align-items: flex-end; justify-content: center; }
    .sign-box .sig-area img { max-height: 50px; }
    .sign-box .line { border-top: 1px solid #000; padding-top: 4px; font-size: 11px; }
    .sign-box .name { font-weight: 600; font-size: 12px; }

    .footer-note { font-size: 10px; color: #888; text-align: center; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 6px; }

    @media print {
        .no-print { display: none !important; }
        body { padding: 8px 12px; font-size: 11px; }
        @page { size: A4; margin: 5mm; }
        .section-title { margin-top: 10px; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print();" class="btn-print">🖨️ พิมพ์</button>
    <button onclick="window.close();">ปิด</button>
</div>

<div class="header">
    <h2>รายงานสรุปบัญชีงาน</h2>
    <p><?= htmlspecialchars($data['function_name']) ?> | <?= date('d/m/Y H:i') ?></p>
</div>

<!-- รายละเอียดต้นทุนครัว -->
<?php if (!empty($kitchen['main_list']) || !empty($kitchen['break_list'])): ?>
<div class="section-title">รายละเอียดต้นทุนอาหาร (Kitchen Cost Breakdown)</div>
<div class="two-col">
    <?php if (!empty($kitchen['main_list'])): ?>
    <div>
        <h6 style="color:#0d6efd;">🍽️ รายการอาหารหลัก</h6>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align:left;">รายการ</th>
                    <th>จำนวน</th>
                    <th>ราคาขาย/หน่วย</th>
                    <th>ราคาทุน/หน่วย</th>
                    <th>ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kitchen['main_list'] as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td style="text-align:center;"><?= number_format($r['qty']) ?></td>
                    <td class="amount"><?= number_format($r['price'], 2) ?></td>
                    <td class="amount text-red"><?= number_format($r['cost'], 2) ?></td>
                    <td class="amount"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f0f7ff;font-weight:bold;">
                    <td colspan="4" style="text-align:right;padding:5px 8px;">รวมอาหารหลัก</td>
                    <td class="amount text-blue"><?= number_format($kitchen['sum_main'], 2) ?></td>
                </tr>
                <tr style="background:#fff0f0;font-weight:bold;">
                    <td colspan="4" style="text-align:right;padding:5px 8px;">รวมต้นทุนอาหารหลัก</td>
                    <td class="amount text-red"><?= number_format($kitchen['sum_main_cost'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($kitchen['break_list'])): ?>
    <div>
        <h6 style="color:#fd7e14;">☕ รายการจัดเตรียม/เบรก</h6>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align:left;">รายการ</th>
                    <th>จำนวน</th>
                    <th>ราคาขาย/หน่วย</th>
                    <th>ราคาทุน/หน่วย</th>
                    <th>ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kitchen['break_list'] as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td style="text-align:center;"><?= number_format($r['qty']) ?></td>
                    <td class="amount"><?= number_format($r['price'], 2) ?></td>
                    <td class="amount text-red"><?= number_format($r['cost'], 2) ?></td>
                    <td class="amount"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#fff8f5;font-weight:bold;">
                    <td colspan="4" style="text-align:right;padding:5px 8px;">รวมเบรก/จัดเตรียม</td>
                    <td class="amount text-red"><?= number_format($kitchen['sum_break'], 2) ?></td>
                </tr>
                <tr style="background:#fff0f0;font-weight:bold;">
                    <td colspan="4" style="text-align:right;padding:5px 8px;">รวมต้นทุนเบรก</td>
                    <td class="amount text-red"><?= number_format($kitchen['sum_break_cost'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- รายรับทั้งหมด -->
<?php
$incomes = array_filter($finances, fn($f) => $f['type'] == 'income' || $f['type'] == 'deposit');
$costs = array_filter($finances, fn($f) => $f['type'] == 'cost');
?>
<div class="section-title">รายรับ (Income)</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:12%">วันที่</th>
            <th style="width:28%">รายการ</th>
            <th style="width:12%" class="text-center">ประเภท</th>
            <th style="width:14%" class="amount">เงินมัดจำ</th>
            <th style="width:14%" class="amount">รายรับ</th>
            <th style="width:10%">ช่องทาง</th>
            <th style="width:10%">บันทึกโดย</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($incomes)): ?>
        <tr><td colspan="7" style="text-align:center;padding:10px;color:#999;">ไม่มีรายการรายรับ</td></tr>
        <?php else: ?>
            <?php $ri = 0; foreach ($incomes as $f): ?>
            <tr class="<?= $ri % 2 == 1 ? 'alt' : '' ?>">
                <td style="white-space:nowrap;"><?= $fmtDate($f['transaction_date']) ?></td>
                <td><?= htmlspecialchars($f['detail']) ?></td>
                <td style="text-align:center;">
                    <?php if ($f['type'] == 'deposit'): ?>
                        <span style="background:#d1ecf1;padding:2px 8px;border-radius:3px;font-size:11px;">มัดจำ</span>
                    <?php else: ?>
                        <span style="background:#d4edda;padding:2px 8px;border-radius:3px;font-size:11px;">รายรับ</span>
                    <?php endif; ?>
                    <?php if ($f['is_post_approval']): ?>
                        <br><span style="background:#fff3cd;padding:1px 6px;border-radius:3px;font-size:10px;">หลังอนุมัติ</span>
                    <?php endif; ?>
                </td>
                <td class="amount" style="color:#1f4e79;"><?= $f['type'] == 'deposit' ? number_format($f['amount'], 2) : '-' ?></td>
                <td class="amount" style="color:#006100;font-weight:600;"><?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($f['payment_method'] ?? '-') ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
            </tr>
            <?php $ri++; endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($incomes)): ?>
    <tfoot>
        <tr style="background:#d4edda;font-weight:700;">
            <td colspan="3" style="text-align:right;padding:6px 8px;">รวมรายรับ</td>
            <td class="amount" style="font-size:13px;color:#1f4e79;"><?= number_format($total_deposit, 2) ?></td>
            <td class="amount" style="font-size:13px;color:#006100;"><?= number_format($total_income - $total_deposit, 2) ?></td>
            <td colspan="2" style="text-align:right;">รวม: <?= number_format($total_income, 2) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<!-- ค่าใช้จ่ายทั้งหมด -->
<div class="section-title">ค่าใช้จ่าย (Expenses)</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:12%">วันที่</th>
            <th style="width:35%">รายการ</th>
            <th style="width:15%" class="amount">จำนวนเงิน</th>
            <th style="width:10%">ช่องทาง</th>
            <th style="width:13%">ผู้บันทึก</th>
            <th style="width:15%">หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($costs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:10px;color:#999;">ไม่มีรายการค่าใช้จ่าย</td></tr>
        <?php else: ?>
            <?php $rc = 0; foreach ($costs as $f): ?>
            <tr class="<?= $rc % 2 == 1 ? 'alt' : '' ?>">
                <td style="white-space:nowrap;"><?= $fmtDate($f['transaction_date']) ?></td>
                <td><?= htmlspecialchars($f['detail']) ?></td>
                <td class="amount" style="color:#c00000;font-weight:600;"><?= number_format($f['amount'], 2) ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($f['payment_method'] ?? '-') ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
                <td style="font-size:11px;">
                    <?php if ($f['is_post_approval']): ?>
                        <span style="background:#fff3cd;padding:1px 6px;border-radius:3px;font-size:10px;">หลังอนุมัติ</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php $rc++; endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($costs)): ?>
    <tfoot>
        <tr style="background:#f8d7da;font-weight:700;">
            <td colspan="2" style="text-align:right;padding:6px 8px;">รวมค่าใช้จ่าย</td>
            <td class="amount" style="font-size:13px;color:#c00000;"><?= number_format($extra_cost, 2) ?></td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<!-- สรุปผลการเงิน -->
<div class="section-title">สรุปผลการเงิน (Financial Summary)</div>
<table class="data-table">
    <tr>
        <th colspan="4" style="text-align:left;">รายการ</th>
    </tr>
    <tr>
        <td class="label" style="width:35%">ราคาขายงาน</td>
        <td class="amount text-blue" style="width:25%"><?= number_format($main_price, 2) ?></td>
        <td class="label" style="width:15%">รหัสงาน</td>
        <td style="width:25%"><?= htmlspecialchars($data['function_code'] ?? '-') ?></td>
    </tr>
    <tr class="alt">
        <td class="label">รายรับเพิ่มเติม (มัดจำ/รายรับอื่น)</td>
        <td class="amount text-green"><?= number_format($total_income, 2) ?></td>
        <td class="label">แยกหลังอนุมัติ</td>
        <td class="amount"><?= $post_income > 0 ? '<span class="text-green">' . number_format($post_income, 2) . '</span>' : '-' ?></td>
    </tr>
    <tr>
        <td class="label">เงินมัดจำรวม</td>
        <td class="amount text-blue"><?= number_format($total_deposit, 2) ?></td>
        <td class="label">แยกก่อนอนุมัติ</td>
        <td class="amount"><?= number_format($pre_income, 2) ?></td>
    </tr>
    <tr class="alt">
        <td class="label total-row" style="font-size:14px;">รวมรายรับทั้งหมด</td>
        <td class="amount total-row" style="font-size:14px;"><?= number_format($grand_total_income, 2) ?></td>
        <td colspan="2"></td>
    </tr>
    <tr><td colspan="4" style="height:6px; border:none;"></td></tr>
    <tr>
        <td class="label">ต้นทุนอาหารหลัก (ครัว)</td>
        <td class="amount text-red"><?= number_format($kitchen_total, 2) ?></td>
        <td class="label">แยกอาหารหลัก</td>
        <td class="amount text-red"><?= number_format($kitchen['sum_main_cost'], 2) ?></td>
    </tr>
    <tr class="alt">
        <td class="label">ต้นทุนเบรก/จัดเตรียม</td>
        <td class="amount text-red"><?= number_format($kitchen['sum_break_cost'], 2) ?></td>
        <td class="label">แยกเบรก</td>
        <td class="amount text-red"><?= number_format($kitchen['sum_break_cost'], 2) ?></td>
    </tr>
    <tr>
        <td class="label">ค่าใช้จ่ายอื่นๆ (บันทึกเอง)</td>
        <td class="amount text-red"><?= number_format($extra_cost, 2) ?></td>
        <td class="label">แยกหลังอนุมัติ</td>
        <td class="amount"><?= $post_cost > 0 ? '<span class="text-red">' . number_format($post_cost, 2) . '</span>' : '-' ?></td>
    </tr>
    <tr class="alt">
        <td class="label total-row" style="font-size:14px;">ต้นทุนรวมทั้งสิ้น</td>
        <td class="amount total-row text-red" style="font-size:14px;"><?= number_format($total_cost, 2) ?></td>
        <td colspan="2"></td>
    </tr>
    <tr><td colspan="4" style="height:6px; border:none;"></td></tr>
    <tr>
        <td class="label">ค่าบริหาร 3%</td>
        <td class="amount text-red"><?= number_format($management_fee, 2) ?></td>
        <td class="label">(3% ของรายรับรวม <?= number_format($grand_total_income, 2) ?>)</td>
        <td></td>
    </tr>
    <tr class="alt">
        <td class="label total-row" style="font-size:16px;background:#d4edda;">กำไรสุทธิ</td>
        <td class="amount total-row" style="font-size:16px;background:#d4edda;color:<?= $profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($profit, 2) ?> บาท</td>
        <td colspan="2" style="background:#d4edda;"><?= $profit >= 0 ? '✅ มีกำไร' : '❌ ขาดทุน' ?></td>
    </tr>
    <tr><td colspan="4" style="height:6px; border:none;"></td></tr>
    <tr>
        <td class="label">ROI ก่อนอนุมัติ</td>
        <td class="amount" style="font-size:15px;font-weight:700;color:#1f4e79;"><?= number_format($pre_roi, 2) ?>%</td>
        <td class="label">กำไรก่อนอนุมัติ</td>
        <td class="amount" style="font-weight:700;color:<?= $pre_profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($pre_profit, 2) ?></td>
    </tr>
    <tr class="alt">
        <td class="label">ROI หลังอนุมัติ (รวมทุกรายการ)</td>
        <td class="amount" style="font-size:15px;font-weight:700;color:<?= $roi >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($roi, 2) ?>%</td>
        <td class="label">กำไรหลังอนุมัติ</td>
        <td class="amount" style="font-weight:700;color:<?= $post_profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($post_profit, 2) ?></td>
    </tr>
</table>

<!-- กราฟ ROI ก่อน/หลังอนุมัติ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:15px;">
    <div style="border:1px solid #ddd;border-radius:6px;padding:12px;background:#fafafa;">
        <h6 style="text-align:center;margin-bottom:8px;font-size:13px;color:#333;">ROI (%)</h6>
        <div style="max-width:220px;margin:0 auto;"><canvas id="roiChart"></canvas></div>
        <div style="text-align:center;margin-top:6px;font-size:11px;color:#555;">
            ก่อนอนุมัติ: <strong style="color:#1f4e79;"><?= number_format($pre_roi, 2) ?>%</strong>
            &nbsp;|&nbsp;
            หลังอนุมัติ: <strong style="color:<?= $roi >= 0 ? '#198754' : '#c00000' ?>;"><?= number_format($roi, 2) ?>%</strong>
        </div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:12px;background:#fafafa;">
        <h6 style="text-align:center;margin-bottom:8px;font-size:13px;color:#333;">กำไร (บาท)</h6>
        <div style="max-width:220px;margin:0 auto;"><canvas id="profitChart"></canvas></div>
        <div style="text-align:center;margin-top:6px;font-size:11px;color:#555;">
            ก่อนอนุมัติ: <strong style="color:<?= $pre_profit >= 0 ? '#198754' : '#c00000' ?>;"><?= number_format($pre_profit, 2) ?></strong>
            &nbsp;|&nbsp;
            หลังอนุมัติ: <strong style="color:<?= $post_profit >= 0 ? '#198754' : '#c00000' ?>;"><?= number_format($post_profit, 2) ?></strong>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('roiChart'), {
        type: 'doughnut',
        data: {
            labels: ['ก่อนอนุมัติ', 'หลังอนุมัติ'],
            datasets: [{
                data: [Math.abs(<?= $pre_roi ?>), Math.abs(<?= $roi ?>)],
                backgroundColor: ['rgba(31,78,121,0.75)', <?= $roi >= 0 ? "'rgba(25,135,84,0.75)'" : "'rgba(192,0,0,0.75)'" ?>],
                borderColor: ['#fff', '#fff'],
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            cutout: '50%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return ctx.label + ': ' + ctx.parsed.toFixed(2) + '%'; }
                    }
                }
            }
        }
    });

    new Chart(document.getElementById('profitChart'), {
        type: 'doughnut',
        data: {
            labels: ['ก่อนอนุมัติ', 'หลังอนุมัติ'],
            datasets: [{
                data: [Math.abs(<?= $pre_profit ?>), Math.abs(<?= $post_profit ?>)],
                backgroundColor: [<?= $pre_profit >= 0 ? "'rgba(25,135,84,0.75)'" : "'rgba(192,0,0,0.75)'" ?>, <?= $post_profit >= 0 ? "'rgba(25,135,84,0.75)'" : "'rgba(192,0,0,0.75)'" ?>],
                borderColor: ['#fff', '#fff'],
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            cutout: '50%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return ctx.label + ': ' + ctx.parsed.toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท'; }
                    }
                }
            }
        }
    });
</script>

<!-- ลงชื่อ -->
<div class="signature-section">
    <div class="sign-box">
        <div class="sig-area"></div>
        <div class="line">
            <div class="name">ผู้จัดทำรายงาน</div>
            <div>วันที่ <?= date('d/m/Y') ?></div>
        </div>
    </div>
    <div class="sign-box">
        <div class="sig-area"></div>
        <div class="line">
            <div class="name">ผู้อนุมัติ</div>
            <div>วันที่ .................................</div>
        </div>
    </div>
</div>

<div class="footer-note">
    รายงานนี้สร้างจากระบบจัดการงานเลี้ยง | <?= date('d/m/Y H:i:s') ?>
</div>

</body>
</html>
