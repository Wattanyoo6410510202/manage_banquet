<?php
/**
 * ล้วงราคาทุนต่อหัวของเมนู 1 บรรทัด ตามชื่อเมนู
 *
 * ใช้ร่วมกันทั้งหน้าจอบัญชี รายงานพิมพ์ Excel และฝั่งใบเสนอราคา
 * เพื่อให้ตัวเลขทุนรายเมนูที่โชว์ในทุกที่มาจากกติกาเดียวกัน
 *
 * ลำดับการหา: function_menu_details (cost_per_pax ก่อน ไม่มีค่อยใช้ price_per_pax)
 *             แล้วค่อยไป function_breaks (break_cost ก่อน ไม่มีค่อยใช้ break_price)
 */

/** ตัดคำนำหน้าอย่าง "เบรก :" ออก ให้เหลือชื่อเมนูล้วนๆ ไว้ใช้จับคู่ */
function menuCostLookupKey($name)
{
    return trim(preg_replace('/^(เบรก|เบรค|break)\s*[:：\-]?\s*/iu', '', (string) $name));
}

/** ราคาทุนของชื่อเมนูตรงๆ (0 = จับคู่ไม่ได้) */
function menuLineCost($conn, $name)
{
    $key = menuCostLookupKey($name);
    if ($key === '') {
        return 0.0;
    }

    $esc = $conn->real_escape_string($key);

    $q = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details
                       WHERE menu_items LIKE '%$esc%' LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) {
        $cost = (float) ($r['cost_per_pax'] ?? 0);
        return $cost > 0 ? $cost : (float) $r['price_per_pax'];
    }

    $q = $conn->query("SELECT break_price, break_cost FROM function_breaks
                       WHERE break_menu LIKE '%$esc%' LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) {
        $cost = (float) ($r['break_cost'] ?? 0);
        return $cost > 0 ? $cost : (float) $r['break_price'];
    }

    return 0.0;
}

/**
 * เหมือน menuLineCost() แต่ถ้าทั้งบรรทัดจับคู่ไม่ได้ จะแตกตาม + แล้วรวมทุนของแต่ละเมนูย่อย
 *
 * ลองทั้งบรรทัดก่อนเสมอ เพราะชื่อเมนูบางอันมี + อยู่ในตัวเอง เช่น "น้ำพริกกุ้งสด+ผักสด"
 */
function menuLineCostSplit($conn, $line)
{
    $whole = menuLineCost($conn, $line);
    if ($whole > 0) {
        return $whole;
    }

    if (strpos($line, '+') === false) {
        return 0.0;
    }

    $sum = 0.0;
    foreach (explode('+', $line) as $part) {
        $sum += menuLineCost($conn, trim($part));
    }
    return $sum;
}

/**
 * แปลงรายชื่อเมนูย่อยเป็นแถวลูกพร้อมราคาทุนรายเมนู
 *
 * @param array $names ชื่อเมนูแต่ละบรรทัด
 * @return array [['name' => ..., 'cost' => float], ...]
 */
function buildSubItems($conn, $names)
{
    $out = [];
    foreach ($names as $n) {
        $out[] = ['name' => $n, 'cost' => menuLineCostSplit($conn, $n)];
    }
    return $out;
}
