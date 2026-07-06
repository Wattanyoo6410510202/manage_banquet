<?php
include_once 'config.php'; 

$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลหัวข้องาน
$sql_func = "SELECT function_name FROM functions WHERE id = $id";
$res_func = $conn->query($sql_func);
$event = $res_func->fetch_assoc();

if (!$event) { echo "ไม่พบข้อมูลรายการอาหาร"; return; }

function cleanItemName($name) {
    return trim(preg_replace('/^[0-9\.\-\s]+/', '', $name));
}

// --- ตะกร้าที่ 1: รายการอาหารหลัก (ดึงจาก function_menus -> ล้วงราคาจาก function_menu_details) ---
$main_list = [];
$sum_main = 0;
$sql_m = "SELECT menu_detail, menu_qty, menu_price FROM function_menus WHERE function_id = $id";
$res_m = $conn->query($sql_m);

while ($row = $res_m->fetch_assoc()) {
    $direct_price = (float)($row['menu_price'] ?? 0);
    $qty = (float)$row['menu_qty'];
    $lines = preg_split('/\r\n|\r|\n/', $row['menu_detail']);
    if ($direct_price > 0) {
        $name = cleanItemName($lines[0] ?? '');
        $total = $direct_price * $qty;
        $main_list[] = ['name' => $name ?: 'ค่าอาหาร', 'qty' => $qty, 'price' => $direct_price, 'total' => $total];
        $sum_main += $total;
    } else {
        foreach ($lines as $l) {
            $name = cleanItemName($l);
            if (empty($name)) continue;
            $name_esc = $conn->real_escape_string($name);
            $q_price = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
            $price = 0;
            if ($p = $q_price->fetch_assoc()) {
                $price = (float)$p['price_per_pax'];
            }
            $total = $price * $qty;
            $main_list[] = ['name' => $name, 'qty' => $qty, 'price' => $price, 'total' => $total];
            $sum_main += $total;
        }
    }
}

// --- ตะกร้าที่ 2: รายการเบรก/จัดเตรียม (ดึงจาก function_kitchens -> ล้วงราคาจาก function_breaks) ---
$break_list = [];
$sum_break = 0;
$sql_k = "SELECT k_item, k_qty, k_price FROM function_kitchens WHERE function_id = $id";
$res_k = $conn->query($sql_k);

while ($row = $res_k->fetch_assoc()) {
    $price = (float)($row['k_price'] ?? 0);
    
    if ($price > 0) {
        $total = $price * (float)$row['k_qty'];
        $break_list[] = ['name' => $row['k_item'], 'qty' => (float)$row['k_qty'], 'price' => $price, 'total' => $total];
        $sum_break += $total;
    } else {
        foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
            $name = cleanItemName($l);
            if (!empty($name)) {
                $item_price = 0;
                $name_esc = $conn->real_escape_string($name);
                // ล้วงราคาจากตารางเบรก
                $q_price = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_price->fetch_assoc()) {
                    $item_price = (float)$p['break_price'];
                }
                $total = $item_price * (float)$row['k_qty'];
                $break_list[] = ['name' => $name, 'qty' => (float)$row['k_qty'], 'price' => $item_price, 'total' => $total];
                $sum_break += $total;
            }
        }
    }
}
?>

<div style="display: flex; gap: 15px; flex-wrap: wrap; font-family: 'Sarabun', sans-serif;">
    <div style="flex: 1; min-width: 350px;">
        <h6 style="color: #0d6efd; font-weight: bold;">[ รายการอาหารหลัก ]</h6>
        <table border="1" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th style="padding: 8px;">รายการ</th>
                    <th width="50">จำนวน</th>
                    <th width="70">ราคา</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($main_list as $r): ?>
                <tr>
                    <td style="padding: 8px;"><?= $r['name'] ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="right"><?= number_format($r['price'], 2) ?></td>
                    <td align="right"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #f0f7ff; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right" style="padding: 8px;">รวมต้นทุนอาหารหลัก</td>
                    <td align="right" style="color: #0d6efd;"><?= number_format($sum_main, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="flex: 1; min-width: 350px;">
        <h6 style="color: #fd7e14; font-weight: bold;">[ รายการจัดเตรียมเบรก ]</h6>
        <table border="1" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th style="padding: 8px;">รายการเบรก</th>
                    <th width="50">จำนวน</th>
                    <th width="70">ราคา</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($break_list as $r): ?>
                <tr>
                    <td style="padding: 8px;"><?= $r['name'] ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="right"><?= number_format($r['price'], 2) ?></td>
                    <td align="right" style="color: #d94100;"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #fff8f5; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right" style="padding: 8px;">รวมต้นทุนเบรก</td>
                    <td align="right" style="color: #d94100;"><?= number_format($sum_break, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>