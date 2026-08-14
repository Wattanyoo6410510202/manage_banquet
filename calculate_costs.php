<?php
include_once 'config.php';
include_once __DIR__ . '/includes/quote_cost.php';
include_once __DIR__ . '/includes/menu_cost_lookup.php';

$id = intval($_GET['id'] ?? 0);
$cc_quote_id = intval($_GET['quote_id'] ?? 0);
$cc_is_quote = ($cc_quote_id > 0 && $id === 0);

if ($cc_is_quote) {
    // ขั้นใบเสนอราคา: รายการมาจาก quotation_items ราคาทุนล้วงตามชื่อเมนู
    $q_event = $conn->query("SELECT event_name FROM quotations WHERE id = $cc_quote_id");
    if (!$q_event || !$q_event->fetch_assoc()) { echo "ไม่พบใบเสนอราคานี้"; return; }

    $cc = getQuoteCostDetailed($conn, $cc_quote_id);
    $main_list      = $cc['main_list'];
    $break_list     = $cc['break_list'];
    $sum_main       = $cc['sum_main'];
    $sum_main_cost  = $cc['sum_main_cost'];
    $sum_break      = $cc['sum_break'];
    $sum_break_cost = $cc['sum_break_cost'];
} else {

// 1. ดึงข้อมูลหัวข้องาน
$sql_func = "SELECT function_name FROM functions WHERE id = $id";
$res_func = $conn->query($sql_func);
$event = $res_func->fetch_assoc();

if (!$event) { echo "ไม่พบข้อมูลรายการอาหาร"; return; }

function cleanItemName($name) {
    return trim(preg_replace('/^[0-9\.\-\s]+/', '', $name));
}

// ชื่อเซตเมนู: เอาแค่ชื่อหมวด (เช่น "Thai-set 3,000")
function menuSetName($row) {
    $cat = trim($row['category_name'] ?? '');
    return $cat !== '' ? $cat : trim($row['type_name'] ?? '');
}

// --- ตะกร้าที่ 1: รายการอาหารหลัก (ดึงจาก function_menus -> ล้วงราคาจาก function_menu_details) ---
$main_list = [];
$sum_main = 0;
$sum_main_cost = 0;
$sql_m = "SELECT fm.menu_detail, fm.menu_qty, fm.menu_price, fm.menu_cost,
                 mt.type_name, mc.category_name
          FROM function_menus fm
          LEFT JOIN master_menu_types mt ON fm.menu_set_id = mt.id
          LEFT JOIN master_menu_categories mc ON mt.category_id = mc.id
          WHERE fm.function_id = $id";
$res_m = $conn->query($sql_m);

while ($row = $res_m->fetch_assoc()) {
    $direct_price = (float)($row['menu_price'] ?? 0);
    $direct_cost = (float)($row['menu_cost'] ?? 0);
    $qty = (float)$row['menu_qty'];
    $lines = preg_split('/\r\n|\r|\n/', $row['menu_detail']);
    if ($direct_price > 0) {
        // ราคาเหมา: แยกชื่อเมนูทุกบรรทัดออกมาแสดง แต่คิดเงินครั้งเดียวที่แถวหลัก
        $names = [];
        foreach ($lines as $l) {
            $n = cleanItemName($l);
            if (!empty($n)) $names[] = $n;
        }
        // ทุน/หน่วย: ใช้ menu_cost ที่กรอกไว้ก่อน ไม่มีค่อยรวมทุนรายเมนูที่จับคู่ชื่อได้
        // จับคู่ไม่ได้สักเมนูค่อยตกไปที่ราคาขาย (กติกาเดียวกับฝั่งใบเสนอราคา)
        $unit_cost = $direct_cost;
        if ($unit_cost <= 0) {
            foreach ($names as $n) { $unit_cost += menuLineCostSplit($conn, $n); }
        }
        if ($unit_cost <= 0) $unit_cost = $direct_price;

        // หัวแถวใช้ชื่อเซต ถ้าไม่มีเซตค่อยหยิบเมนูบรรทัดแรกมาเป็นหัว
        $set_name = menuSetName($row);
        $name = $set_name !== '' ? $set_name : array_shift($names);
        $total = $direct_price * $qty;
        $cost = $unit_cost * $qty;
        $main_list[] = ['name' => $name ?: 'ค่าอาหาร', 'sub' => buildSubItems($conn, $names), 'qty' => $qty, 'price' => $direct_price, 'cost' => $unit_cost, 'total' => $total, 'cost_total' => $cost];
        $sum_main += $total;
        $sum_main_cost += $cost;
    } else {
        foreach ($lines as $l) {
            $name = cleanItemName($l);
            if (empty($name)) continue;
            $name_esc = $conn->real_escape_string($name);
            $q_price = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
            $price = 0;
            $cost = 0;
            if ($p = $q_price->fetch_assoc()) {
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

// --- ตะกร้าที่ 2: รายการเบรก/จัดเตรียม (ดึงจาก function_kitchens -> ล้วงราคาจาก function_breaks) ---
$break_list = [];
$sum_break = 0;
$sum_break_cost = 0;
$sql_k = "SELECT fk.k_item, fk.k_qty, fk.k_price, fk.k_cost, bt.type_name
          FROM function_kitchens fk
          LEFT JOIN master_break_types bt ON fk.k_type_id = bt.id
          WHERE fk.function_id = $id";
$res_k = $conn->query($sql_k);

while ($row = $res_k->fetch_assoc()) {
    $price = (float)($row['k_price'] ?? 0);
    $cost = (float)($row['k_cost'] ?? 0);
    
    if ($price > 0) {
        // ราคาเหมา: แยกชื่อเบรกทุกบรรทัดออกมาแสดง แต่คิดเงินครั้งเดียวที่แถวหลัก
        $k_names = [];
        foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
            $n = cleanItemName($l);
            if (!empty($n)) $k_names[] = $n;
        }
        // ทุน/หน่วย: ใช้ k_cost ที่กรอกไว้ก่อน ไม่มีค่อยรวมทุนรายเมนูที่จับคู่ชื่อได้
        $k_unit_cost = $cost;
        if ($k_unit_cost <= 0) {
            foreach ($k_names as $n) { $k_unit_cost += menuLineCostSplit($conn, $n); }
        }
        if ($k_unit_cost <= 0) $k_unit_cost = $price;

        // หัวแถวใช้ชื่อประเภทเบรก ถ้าไม่มีค่อยหยิบเมนูบรรทัดแรกมาเป็นหัว
        $k_set = trim($row['type_name'] ?? '');
        $k_name = $k_set !== '' ? $k_set : array_shift($k_names);
        $total = $price * (float)$row['k_qty'];
        $cost_total = $k_unit_cost * (float)$row['k_qty'];
        $break_list[] = ['name' => $k_name ?: 'ค่าเบรก', 'sub' => buildSubItems($conn, $k_names), 'qty' => (float)$row['k_qty'], 'price' => $price, 'cost' => $k_unit_cost, 'total' => $total, 'cost_total' => $cost_total];
        $sum_break += $total;
        $sum_break_cost += $cost_total;
    } else {
        foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
            $name = cleanItemName($l);
            if (!empty($name)) {
                $item_price = 0;
                $item_cost = 0;
                $name_esc = $conn->real_escape_string($name);
                $q_price = $conn->query("SELECT break_price, break_cost FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_price->fetch_assoc()) {
                    $item_price = (float)$p['break_price'];
                    $item_cost = (float)($p['break_cost'] ?? 0);
                }
                $total = $item_price * (float)$row['k_qty'];
                $cost_total = $item_cost > 0 ? $item_cost * (float)$row['k_qty'] : $total;
                $break_list[] = ['name' => $name, 'qty' => (float)$row['k_qty'], 'price' => $item_price, 'cost' => $item_cost > 0 ? $item_cost : $item_price, 'total' => $total, 'cost_total' => $cost_total];
                $sum_break += $total;
                $sum_break_cost += $cost_total;
            }
        }
    }
}

} // จบโหมด EO
?>

<div style="display: flex; gap: 15px; flex-wrap: wrap; font-family: 'Sarabun', sans-serif;">
    <div style="flex: 1; min-width: 350px;">
        <h6 style="color: #0d6efd; font-weight: bold;">[ รายการอาหารหลัก ]</h6>
        <table border="1" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th style="padding: 8px;">รายการ</th>
                    <th width="50">จำนวน</th>
                    <th width="70">ราคาขาย</th>
                    <th width="70">ราคาทุน</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($main_list as $r): ?>
                <tr>
                    <td style="padding: 8px;"><?= htmlspecialchars($r['name']) ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="right"><?= number_format($r['price'], 2) ?></td>
                    <td align="right" style="color: #d94100;"><?= number_format($r['cost'], 2) ?></td>
                    <td align="right"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                    <?php foreach ($r['sub'] ?? [] as $s): ?>
                    <tr>
                        <td style="padding: 4px 8px 4px 26px; color: #666;">↳ <?= htmlspecialchars($s['name']) ?></td>
                        <td align="center" style="color:#aaa;">-</td>
                        <td align="right" style="color:#aaa;">-</td>
                        <td align="right" style="color:<?= $s['cost'] > 0 ? '#d94100' : '#aaa' ?>;">
                            <?= $s['cost'] > 0 ? number_format($s['cost'], 2) : '-' ?>
                        </td>
                        <td align="right" style="color:#aaa;">-</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #f0f7ff; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right" style="padding: 8px;">รวมต้นทุนอาหารหลัก (ราคาขาย)</td>
                    <td align="right" style="color: #d94100;"><?= number_format($sum_main_cost, 2) ?></td>
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
                    <th width="70">ราคาขาย</th>
                    <th width="70">ราคาทุน</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($break_list as $r): ?>
                <tr>
                    <td style="padding: 8px;"><?= htmlspecialchars($r['name']) ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="right"><?= number_format($r['price'], 2) ?></td>
                    <td align="right" style="color: #d94100;"><?= number_format($r['cost'], 2) ?></td>
                    <td align="right" style="color: #d94100;"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                    <?php foreach ($r['sub'] ?? [] as $s): ?>
                    <tr>
                        <td style="padding: 4px 8px 4px 26px; color: #666;">↳ <?= htmlspecialchars($s['name']) ?></td>
                        <td align="center" style="color:#aaa;">-</td>
                        <td align="right" style="color:#aaa;">-</td>
                        <td align="right" style="color:<?= $s['cost'] > 0 ? '#d94100' : '#aaa' ?>;">
                            <?= $s['cost'] > 0 ? number_format($s['cost'], 2) : '-' ?>
                        </td>
                        <td align="right" style="color:#aaa;">-</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #fff8f5; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right" style="padding: 8px;">รวมต้นทุนเบรก (ราคาขาย)</td>
                    <td align="right" style="color: #d94100;"><?= number_format($sum_break_cost, 2) ?></td>
                    <td align="right" style="color: #d94100;"><?= number_format($sum_break, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>