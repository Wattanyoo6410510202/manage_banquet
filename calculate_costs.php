<?php
// แนะนำให้ใช้ include_once เพื่อกัน error ถ้าหน้าหลักมี config อยู่แล้ว
include_once 'config.php'; 

$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลงาน
$sql_func = "SELECT function_name FROM functions WHERE id = $id";
$res_func = $conn->query($sql_func);
$event = $res_func->fetch_assoc();

if (!$event) { echo "ไม่พบข้อมูลรายการอาหาร"; return; }

function cleanItemName($name) {
    return trim(preg_replace('/^[0-9\.\-\s]+/', '', $name));
}

// 2. ประมวลผลข้อมูล
$all_raw_items = [];
$sql_menu = "SELECT menu_detail, menu_qty FROM function_menus WHERE function_id = $id";
$res_menu = $conn->query($sql_menu);
while ($row = $res_menu->fetch_assoc()) {
    foreach (preg_split('/\r\n|\r|\n/', $row['menu_detail']) as $l) {
        $name = cleanItemName($l);
        if (!empty($name)) $all_raw_items[] = ['name' => $name, 'qty' => (float)$row['menu_qty'], 'type_id' => 0];
    }
}

$sql_kitchen = "SELECT k_item, k_qty, k_type_id FROM function_kitchens WHERE function_id = $id";
$res_kitchen = $conn->query($sql_kitchen);
while ($row = $res_kitchen->fetch_assoc()) {
    foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
        $name = cleanItemName($l);
        if (!empty($name)) $all_raw_items[] = ['name' => $name, 'qty' => (float)$row['k_qty'], 'type_id' => $row['k_type_id']];
    }
}

$main_list = [];
$break_list = [];
$sum_main = 0;   
$sum_break = 0;  

foreach ($all_raw_items as $item) {
    $name_esc = $conn->real_escape_string($item['name']);
    $price = 0;
    $q_break = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
    
    if ($q_break && $b = $q_break->fetch_assoc()) {
        $price = (float)$b['break_price'];
        $is_break = true;
    } else {
        $q_main = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
        if ($q_main && $m = $q_main->fetch_assoc()) $price = (float)$m['price_per_pax'];
        $is_break = ($item['type_id'] == 1 || $item['type_id'] == 3 || preg_match('/(เบรค|กาแฟ|ขนม|ของว่าง|น้ำ)/u', $item['name']));
    }
    
    $sub = $price * $item['qty'];
    $data = ['name' => $item['name'], 'qty' => $item['qty'], 'price' => $price, 'total' => $sub];
    
    if ($is_break) { 
        $break_list[] = $data; 
        $sum_break += $sub; 
    } else { 
        $main_list[] = $data; 
        $sum_main += $sub; 
    }
}
?>

<div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
    <div style="flex: 1; min-width: 320px;">
        <h6 style="margin-bottom: 8px; color: #0d6efd; font-weight: bold;">[ รายการอาหารหลัก ]</h6>
        <table border="1" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th>รายการ</th>
                    <th width="40">จำนวน</th>
                    <th width="70">ราคา</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($main_list as $r): ?>
                <tr>
                    <td><?= $r['name'] ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="center" style="color: #666;"><?= number_format($r['price'], 2) ?></td>
                    <td align="center"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($main_list)): ?>
                    <tr><td colspan="4" align="center">ไม่มีรายการ</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot style="background: #fdfdfe; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right">รวมต้นทุนอาหารหลัก</td>
                    <td align="right" style="color: #0d6efd; font-size: 14px;"><?= number_format($sum_main, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="flex: 1; min-width: 320px;">
        <h6 style="margin-bottom: 8px; color: #fd7e14; font-weight: bold;">[ รายการเบรค/ของว่าง ]</h6>
        <table border="1" cellpadding="5" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="background: #f8f9fa;">
                <tr>
                    <th>รายการ</th>
                    <th width="40">จำนวน</th>
                    <th width="70">ราคา</th>
                    <th width="80">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($break_list as $r): ?>
                <tr>
                    <td><?= $r['name'] ?></td>
                    <td align="center"><?= number_format($r['qty']) ?></td>
                    <td align="center" style="color: #666;"><?= number_format($r['price'], 2) ?></td>
                    <td align="center" style="color: #d94100;"><strong><?= number_format($r['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($break_list)): ?>
                    <tr><td colspan="4" align="center">ไม่มีรายการ</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot style="background: #fdfdfe; font-weight: bold;">
                <tr>
                    <td colspan="3" align="right">รวมต้นทุนเบรค</td>
                    <td align="right" style="color: #d94100; font-size: 14px;"><?= number_format($sum_break, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>