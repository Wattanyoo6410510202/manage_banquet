<?php
/**
 * ต้นทุนรายเมนูฝั่ง "ใบเสนอราคา"
 *
 * ฝั่ง EO ดึงรายการจาก function_menus / function_kitchens ซึ่งใบเสนอราคายังไม่มี
 * ที่นี่จึงอ่านจาก quotation_items แทน แล้วล้วงราคาทุนจาก function_menu_details /
 * function_breaks ตามชื่อเมนู เพื่อให้ได้โครงสร้างผลลัพธ์หน้าตาเดียวกับ
 * getKitchenCostDetailed() ฝั่ง EO — หน้าจอ/รายงาน/Excel จะได้ใช้โค้ดแสดงผลร่วมกันได้
 *
 * quotation_items ไม่มีคอลัมน์ราคาทุน กติกาจึงเป็น:
 *   ทุน/หน่วย = ผลรวม cost_per_pax ของเมนูทุกบรรทัดที่จับคู่ชื่อได้
 *   ถ้าจับคู่ไม่ได้เลย = ถือว่าทุนเท่ากับราคาขาย (กติกาเดียวกับฝั่ง EO ที่ไม่มีข้อมูลทุน)
 */

require_once __DIR__ . '/menu_cost_lookup.php';

/** ตัดเลขลำดับ/ขีดนำหน้าออกจากชื่อรายการ */
function quoteCleanItemName($name)
{
    return trim(preg_replace('/^[0-9\.\-\s]+/u', '', (string) $name));
}

/** รายการนี้นับเป็นเบรก/จัดเตรียมไหม */
function isQuoteBreakItem($item_type, $name)
{
    if (!empty($item_type) && $item_type !== 'Food') {
        return true;
    }
    return (bool) preg_match('/^(เบรก|เบรค|break)/iu', trim((string) $name));
}

// การล้วงราคาทุนตามชื่อเมนูย้ายไปอยู่ที่ menu_cost_lookup.php แล้ว
// เพื่อให้ฝั่ง EO กับฝั่งใบเสนอราคาใช้กติกาเดียวกัน (menuLineCost / menuLineCostSplit)

/**
 * แตกรายการในใบเสนอราคาเป็นตะกร้าอาหารหลัก/เบรก พร้อมยอดรวม
 * คืนค่าโครงสร้างเดียวกับ getKitchenCostDetailed() ฝั่ง EO
 */
function getQuoteCostDetailed($conn, $quote_id)
{
    $main_list = [];
    $break_list = [];
    $sum_main = 0;
    $sum_main_cost = 0;
    $sum_break = 0;
    $sum_break_cost = 0;

    $quote_id = intval($quote_id);
    $res = $conn->query("SELECT item_name, quantity, unit_price, item_type
                         FROM quotation_items
                         WHERE quote_id = $quote_id
                         ORDER BY id ASC");

    while ($res && $row = $res->fetch_assoc()) {
        $qty   = (float) $row['quantity'];
        $price = (float) $row['unit_price'];

        // แตกชื่อรายการทีละบรรทัด บรรทัดแรกเป็นหัว ที่เหลือเป็นลูก
        $names = [];
        foreach (preg_split('/\r\n|\r|\n/', $row['item_name']) as $line) {
            $n = quoteCleanItemName($line);
            if ($n !== '') $names[] = $n;
        }
        if (empty($names)) continue;
        $name = array_shift($names);

        // ทุน/หน่วย = รวมทุกบรรทัดที่จับคู่ชื่อได้ ไม่เจอเลยก็ถือว่าทุน = ราคาขาย
        $unit_cost = 0;
        foreach (array_merge([$name], $names) as $n) {
            $unit_cost += menuLineCostSplit($conn, $n);
        }
        $cost_unit  = $unit_cost > 0 ? $unit_cost : $price;
        $total      = $price * $qty;
        $cost_total = $cost_unit * $qty;

        $entry = [
            'name'       => $name,
            'sub'        => buildSubItems($conn, $names),
            'qty'        => $qty,
            'price'      => $price,
            'cost'       => $cost_unit,
            'total'      => $total,
            'cost_total' => $cost_total,
        ];

        if (isQuoteBreakItem($row['item_type'] ?? '', $name)) {
            $break_list[] = $entry;
            $sum_break += $total;
            $sum_break_cost += $cost_total;
        } else {
            $main_list[] = $entry;
            $sum_main += $total;
            $sum_main_cost += $cost_total;
        }
    }

    return compact('main_list', 'break_list', 'sum_main', 'sum_main_cost', 'sum_break', 'sum_break_cost');
}
