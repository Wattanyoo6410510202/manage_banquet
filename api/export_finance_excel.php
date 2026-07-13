<?php
include "../config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { die("Invalid ID"); }

// ── ดึงข้อมูลงานหลัก ──
$sql = "SELECT * FROM functions WHERE id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
if (!$data) { die("Not found"); }

// ── ดึงข้อมูลการเงิน ──
$finances = [];
$total_income = 0; $extra_cost = 0;
$post_income = 0; $post_cost = 0;

$sql_fin = "SELECT * FROM function_finance WHERE function_id = $id ORDER BY transaction_date ASC, id ASC";
$res_fin = $conn->query($sql_fin);
while ($f = $res_fin->fetch_assoc()) {
    if ($f['is_post_approval']) {
        if ($f['type'] == 'income') $post_income += $f['amount'];
        else $post_cost += $f['amount'];
    }
    if ($f['type'] == 'income') $total_income += $f['amount'];
    else $extra_cost += $f['amount'];
    $finances[] = $f;
}

// ── ต้นทุนจากครัว ──
function getKitchenCost($conn, $function_id) {
    $total_cost = 0;
    $total_cost_price = 0;
    $sql_m = "SELECT menu_qty, menu_price, menu_cost, menu_detail FROM function_menus WHERE function_id = $function_id";
    $res_m = $conn->query($sql_m);
    while ($m = $res_m->fetch_assoc()) {
        $qty = (float) $m['menu_qty'];
        $price_direct = (float) $m['menu_price'];
        $cost_direct = (float) ($m['menu_cost'] ?? 0);
        if ($price_direct > 0) {
            $total_cost += ($price_direct * $qty);
        } else {
            $lines = explode("\n", str_replace("\r", "", $m['menu_detail']));
            foreach ($lines as $line) {
                $name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $line));
                if (empty($name)) continue;
                $name_esc = $conn->real_escape_string($name);
                $q_p = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_p->fetch_assoc()) {
                    $total_cost += ((float) $p['price_per_pax'] * $qty);
                }
            }
        }
        if ($cost_direct > 0) {
            $total_cost_price += ($cost_direct * $qty);
        } elseif ($price_direct > 0) {
            $total_cost_price += ($price_direct * $qty);
        }
    }
    $sql_k = "SELECT k_item, k_qty, k_price, k_cost FROM function_kitchens WHERE function_id = $function_id";
    $res_k = $conn->query($sql_k);
    while ($k = $res_k->fetch_assoc()) {
        $k_qty = (float) $k['k_qty'];
        $k_price = (float) ($k['k_price'] ?? 0);
        $k_cost = (float) ($k['k_cost'] ?? 0);
        if ($k_price > 0) {
            $total_cost += ($k_price * $k_qty);
        } else {
            foreach (explode("\n", str_replace("\r", "", $k['k_item'])) as $line) {
                $k_name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $line));
                if (empty($k_name)) continue;
                $k_name_esc = $conn->real_escape_string($k_name);
                $unit_price = 0;
                $q_b = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$k_name_esc%' LIMIT 1");
                if ($b = $q_b->fetch_assoc()) { $unit_price = (float) $b['break_price']; }
                else {
                    $q_d = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$k_name_esc%' LIMIT 1");
                    if ($d = $q_d->fetch_assoc()) { $unit_price = (float) $d['price_per_pax']; }
                }
                $total_cost += ($unit_price * $k_qty);
            }
        }
        if ($k_cost > 0) {
            $total_cost_price += ($k_cost * $k_qty);
        } elseif ($k_price > 0) {
            $total_cost_price += ($k_price * $k_qty);
        }
    }
    return ['total' => $total_cost, 'total_cost_price' => $total_cost_price];
}
$kitchen_result = getKitchenCost($conn, $id);
$kitchen_total = is_array($kitchen_result) ? $kitchen_result['total'] : $kitchen_result;
$kitchen_cost_price = is_array($kitchen_result) ? ($kitchen_result['total_cost_price'] ?? $kitchen_result['total']) : $kitchen_result;
$main_price = (float) ($data['total_amount'] ?? 0);
$grand_total_income = $main_price + $total_income;
$total_cost = $extra_cost + $kitchen_total;
$management_fee = $grand_total_income * 0.03;
$profit = $grand_total_income - $total_cost - $management_fee;
$roi = ($total_cost > 0) ? ($profit / $total_cost) * 100 : 0;

// ── Output Excel (HTML format) ──
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="ROI_' . $data['function_name'] . '_' . date('Ymd') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// ฟังก์ชันแปลงวันที่
$fmtDate = function($val) {
    if (!$val || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y', strtotime($val));
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
                <x:Name>ROI</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
            </x:ExcelWorksheet>
        </x:ExcelWorksheets>
    </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    table { border-collapse: collapse; width: 100%; font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 12px; }
    th, td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
    th { background: #4472C4; color: #fff; font-weight: bold; text-align: center; }
    .hdr-title { font-size: 18px; font-weight: bold; text-align: center; border: none; padding: 4px; }
    .hdr-sub { font-size: 11px; text-align: center; border: none; padding: 2px; }
    .label-cell { font-weight: bold; background: #f0f0f0; }
    .amount { text-align: right; }
    .text-red { color: #c00000; }
    .text-green { color: #006100; }
    .text-blue { color: #1f4e79; }
    .bg-yellow { background: #FFF2CC; }
</style>
</head>
<body>

<table>
    <!-- Header -->
    <tr><td colspan="6" class="hdr-title">รายงานสรุปบัญชี (ROI)</td></tr>
    <tr><td colspan="6" class="hdr-sub">งาน: <?= htmlspecialchars($data['function_name']) ?></td></tr>
    <tr><td colspan="6" class="hdr-sub">วันที่: <?= $fmtDate($data['start_time']) ?></td></tr>
    <tr><td colspan="6" style="border:none; height: 8px;"></td></tr>

    <!-- สรุปยอด -->
    <tr>
        <th colspan="2">รายการ</th>
        <th class="amount">จำนวนเงิน</th>
        <th colspan="3">หมายเหตุ</th>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">ราคาขายงาน</td>
        <td class="amount text-blue"><?= number_format($main_price, 2) ?></td>
        <td colspan="3"><?= htmlspecialchars($data['function_code'] ?? '-') ?></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายรับเพิ่มเติม</td>
        <td class="amount text-green"><?= number_format($total_income, 2) ?></td>
        <td colspan="3"><?= $post_income > 0 ? '(หลังอนุมัติ ' . number_format($post_income, 2) . ')' : '' ?></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รวมรายรับทั้งหมด</td>
        <td class="amount fw-bold"><?= number_format($grand_total_income, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <tr><td colspan="6" style="height: 4px;"></td></tr>
    <tr>
        <td colspan="2" class="label-cell">ต้นทุนอาหารหลัก</td>
        <td class="amount text-red"><?= number_format($kitchen_total, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">ค่าใช้จ่ายอื่นๆ</td>
        <td class="amount text-red"><?= number_format($extra_cost, 2) ?></td>
        <td colspan="3"><?= $post_cost > 0 ? '(หลังอนุมัติ ' . number_format($post_cost, 2) . ')' : '' ?></td>
    </tr>
    <tr class="bg-yellow">
        <td colspan="2" class="label-cell">ต้นทุนรวมทั้งสิ้น</td>
        <td class="amount fw-bold text-red"><?= number_format($total_cost, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <tr><td colspan="6" style="height: 4px;"></td></tr>
    <tr>
        <td colspan="2" class="label-cell">ค่าบริหาร 3%</td>
        <td class="amount text-red"><?= number_format($management_fee, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <tr><td colspan="6" style="height: 4px;"></td></tr>
    <tr>
        <td colspan="2" class="label-cell" style="background: #DAEEF3;">กำไรสุทธิ</td>
        <td class="amount fw-bold" style="background: #DAEEF3; color: <?= $profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($profit, 2) ?></td>
        <td colspan="3" style="background: #DAEEF3;"></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">ROI (%)</td>
        <td class="amount fw-bold"><?= number_format($roi, 2) ?>%</td>
        <td colspan="3"></td>
    </tr>

    <!-- Post-approval summary -->
    <?php if ($post_income > 0 || $post_cost > 0): ?>
    <tr><td colspan="6" style="height: 8px;"></td></tr>
    <tr>
        <th colspan="2">รายการหลังอนุมัติ</th>
        <th class="amount">จำนวนเงิน</th>
        <th colspan="3"></th>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายรับหลังอนุมัติ</td>
        <td class="amount text-green"><?= number_format($post_income, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td colspan="2" class="label-cell">รายจ่ายหลังอนุมัติ</td>
        <td class="amount text-red"><?= number_format($post_cost, 2) ?></td>
        <td colspan="3"></td>
    </tr>
    <?php $post_profit = $post_income - $post_cost; ?>
    <tr>
        <td colspan="3" class="label-cell">ผลต่างหลังอนุมัติ</td>
        <td class="amount fw-bold" style="color: <?= $post_profit >= 0 ? '#006100' : '#c00000' ?>;"><?= number_format($post_profit, 2) ?></td>
        <td colspan="2"></td>
    </tr>
    <?php endif; ?>

    <!-- รายการเดินบัญชี -->
    <tr><td colspan="6" style="height: 8px;"></td></tr>
    <tr><td colspan="6" class="hdr-sub" style="text-align: left; font-weight: bold;">รายการเดินบัญชี:</td></tr>
    <tr>
        <th>วันที่</th>
        <th>รายการ</th>
        <th class="amount">รายรับ</th>
        <th class="amount">รายจ่าย</th>
        <th>ช่องทาง</th>
        <th>บันทึกโดย</th>
    </tr>
    <?php if (empty($finances)): ?>
    <tr><td colspan="6" style="text-align:center;">ไม่มีรายการ</td></tr>
    <?php else: ?>
        <?php foreach ($finances as $f): ?>
        <tr>
            <td><?= $fmtDate($f['transaction_date']) ?></td>
            <td><?= htmlspecialchars($f['detail']) ?></td>
            <td class="amount"><?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?></td>
            <td class="amount"><?= $f['type'] == 'cost' ? number_format($f['amount'], 2) : '-' ?></td>
            <td><?= htmlspecialchars($f['payment_method'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['created_by_name'] ?? '-') ?><?= $f['is_post_approval'] ? ' (หลังอนุมัติ)' : '' ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

</body>
</html>
