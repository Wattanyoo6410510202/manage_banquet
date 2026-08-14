<?php
/**
 * ปรับปรุงราคาทุน/หัว ของ function_menu_details จากฐานข้อมูลครัว (manage_kitchen)
 *
 * จับคู่ด้วย "ชื่อเมนูที่ตรงกันเท่านั้น" (menus.menu_name = function_menu_details.menu_items)
 * ชื่อที่หาไม่เจอจะถูกส่งกลับไปแจ้งผู้ใช้ ไม่มีการเดา/จับคู่แบบใกล้เคียง
 *
 * สูตรต้นทุนรวมต่อ 1 จาน ยึดตาม manage_kitchen/includes/cost_helper.php:
 *   ต้นทุนอาหาร (ingredients_json: price * qty)
 * + ค่าแรง       (menus.labor_cost_estimate)
 * + ต้นทุนแฝง    (overhead_costs + menu_overhead_overrides, percent คิดจากต้นทุนอาหารอย่างเดียว)
 * = ต้นทุนรวม
 * ถ้าฝั่งครัวแก้สูตร ต้องตามมาแก้ที่นี่ด้วย
 */
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json; charset=utf-8');

/** ตอบ JSON แล้วจบ — ทุกทางออกของไฟล์นี้ต้องผ่านตรงนี้ ห้ามปล่อยให้ตายเป็น 500 ตัวเปล่า */
function jsonOut($payload)
{
    if (ob_get_length()) ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// PHP 8.1+ ตั้ง mysqli ให้โยน exception เป็นค่าเริ่มต้น ถ้าไม่ดักไว้จะกลายเป็น fatal 500
// ที่ฝั่ง browser จะเห็นเป็น "Unexpected end of JSON input" เพราะ body ว่าง
set_exception_handler(function ($e) {
    http_response_code(200);
    jsonOut([
        'status'  => 'error',
        'message' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage(),
        'detail'  => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
});

// ดัก fatal error ที่ exception handler จับไม่ได้ (เช่น memory/timeout)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) ob_clean();
        http_response_code(200);
        echo json_encode([
            'status'  => 'error',
            'message' => 'เกิดข้อผิดพลาดร้ายแรงฝั่งเซิร์ฟเวอร์: ' . $err['message'],
            'detail'  => basename($err['file']) . ':' . $err['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ── ตั้งค่าการเชื่อมต่อฐานข้อมูลครัว ──
// เครื่องที่ MySQL ไม่ได้อยู่ port มาตรฐาน (NAS บางรุ่น) ให้แก้ KITCHEN_DB_PORT ด้วย
if (!defined('KITCHEN_DB_HOST')) define('KITCHEN_DB_HOST', '127.0.0.1');
if (!defined('KITCHEN_DB_USER')) define('KITCHEN_DB_USER', 'root');
if (!defined('KITCHEN_DB_PASS')) define('KITCHEN_DB_PASS', '12345gta');
if (!defined('KITCHEN_DB_NAME')) define('KITCHEN_DB_NAME', 'manage_kitchen');
if (!defined('KITCHEN_DB_PORT')) define('KITCHEN_DB_PORT', 3307);

$user_role = strtolower($_SESSION['role'] ?? 'viewer');
if ($user_role === 'viewer') {
    jsonOut([
        'status'  => 'error',
        'message' => 'ขออภัย! สิทธิ์ Viewer ไม่สามารถปรับปรุงราคาทุนได้'
    ]);
}

// ── เชื่อมต่อฐานข้อมูลครัว ──
// ปิดโหมดโยน exception เฉพาะตอนต่อ เพื่อให้อ่าน connect_error มาบอกผู้ใช้ได้ตรงๆ
mysqli_report(MYSQLI_REPORT_OFF);
$kconn = @new mysqli(KITCHEN_DB_HOST, KITCHEN_DB_USER, KITCHEN_DB_PASS, KITCHEN_DB_NAME, (int) KITCHEN_DB_PORT);
if ($kconn->connect_errno) {
    jsonOut([
        'status'  => 'error',
        'message' => 'เชื่อมต่อฐานข้อมูลครัวไม่ได้: ' . $kconn->connect_error,
        'detail'  => KITCHEN_DB_USER . '@' . KITCHEN_DB_HOST . ':' . KITCHEN_DB_PORT . '/' . KITCHEN_DB_NAME,
    ]);
}
$kconn->set_charset('utf8mb4');

// ── ตรวจว่าตารางที่ต้องใช้มีครบ ก่อนจะไปพังตอน query ──
foreach (['menus', 'overhead_costs', 'menu_overhead_overrides'] as $need) {
    $chk = $kconn->query("SHOW TABLES LIKE '$need'");
    if (!$chk || $chk->num_rows === 0) {
        jsonOut([
            'status'  => 'error',
            'message' => 'ฐานข้อมูล ' . KITCHEN_DB_NAME . ' ไม่มีตาราง `' . $need . '` — ตรวจว่า import ครบหรือชี้ถูกฐานข้อมูลแล้วหรือยัง',
        ]);
    }
}

// ฝั่งจัดเลี้ยงต้องมีคอลัมน์ราคาทุนให้เขียนลง
$chk = $conn->query("SHOW COLUMNS FROM function_menu_details LIKE 'cost_per_pax'");
if (!$chk || $chk->num_rows === 0) {
    jsonOut([
        'status'  => 'error',
        'message' => 'ตาราง function_menu_details ยังไม่มีคอลัมน์ cost_per_pax บนเซิร์ฟเวอร์นี้',
    ]);
}

// ── ต้นทุนแฝงกลาง (ใช้กับทุกเมนู) ──
$overhead_global = [];
$res = $kconn->query("SELECT id, name, cost_mode, value
                      FROM overhead_costs
                      WHERE is_active = 1
                      ORDER BY sort_order ASC, id ASC");
while ($res && $row = $res->fetch_assoc()) {
    $overhead_global[] = $row;
}

// ── ค่า override รายเมนู [menu_id => [overhead_id => value]] ──
$overrides = [];
$res = $kconn->query("SELECT menu_id, overhead_id, value FROM menu_overhead_overrides");
while ($res && $row = $res->fetch_assoc()) {
    $overrides[intval($row['menu_id'])][intval($row['overhead_id'])] = floatval($row['value']);
}

/** รวมต้นทุนวัตถุดิบจาก ingredients_json */
function sumFoodCost($ingredients_json)
{
    $items = json_decode((string) $ingredients_json, true);
    if (!is_array($items)) return 0.0;

    $total = 0.0;
    foreach ($items as $item) {
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $qty   = isset($item['qty']) ? floatval($item['qty']) : 0;
        $total += $price * $qty;
    }
    return $total;
}

/** ต้นทุนแฝงของเมนูหนึ่ง (ค่ากลาง + override) — percent คิดจากต้นทุนอาหารเท่านั้น */
function calcOverhead($food_cost, $global_list, $menu_overrides)
{
    $total = 0.0;
    foreach ($global_list as $oh) {
        $id    = intval($oh['id']);
        $value = array_key_exists($id, $menu_overrides) ? $menu_overrides[$id] : floatval($oh['value']);
        $total += ($oh['cost_mode'] === 'percent') ? ($food_cost * $value / 100) : $value;
    }
    return $total;
}

/** ชื่อเมนูสำหรับเทียบ: ตัดหัวท้าย + ยุบช่องว่างซ้อนให้เหลือช่องเดียว */
function normalizeMenuName($name)
{
    return trim(preg_replace('/\s+/u', ' ', (string) $name));
}

// ── สร้างตารางราคาทุนจากฝั่งครัว [ชื่อเมนู => ต้นทุนรวม] ──
// ชื่อซ้ำกันหลายรายการ ให้ยึดรายการที่เพิ่มล่าสุด (id มากสุด) และเก็บไว้แจ้งผู้ใช้
$kitchen_cost = [];
$dup_names    = [];
$res = $kconn->query("SELECT id, menu_name, ingredients_json, labor_cost_estimate
                      FROM menus
                      WHERE is_active = 1
                      ORDER BY id ASC");
while ($res && $row = $res->fetch_assoc()) {
    $name = normalizeMenuName($row['menu_name']);
    if ($name === '') continue;

    if (isset($kitchen_cost[$name])) {
        $dup_names[$name] = true;
    }

    $food     = sumFoodCost($row['ingredients_json']);
    $labor    = floatval($row['labor_cost_estimate']);
    $overhead = calcOverhead($food, $overhead_global, $overrides[intval($row['id'])] ?? []);

    // id เรียงจากน้อยไปมาก ตัวหลังทับตัวหน้า = ได้รายการล่าสุด
    $kitchen_cost[$name] = round($food + $labor + $overhead, 2);
}
$kconn->close();

if (empty($kitchen_cost)) {
    jsonOut([
        'status'  => 'error',
        'message' => 'ไม่พบรายการเมนู (is_active = 1) ในฐานข้อมูล ' . KITCHEN_DB_NAME
    ]);
}

// ── ไล่อัปเดตราคาทุนฝั่งจัดเลี้ยง ──
$updated_rows = [];
$unchanged    = 0;
$not_found    = [];

$res = $conn->query("SELECT id, menu_items, cost_per_pax FROM function_menu_details ORDER BY id ASC");
$stmt = $conn->prepare("UPDATE function_menu_details SET cost_per_pax = ? WHERE id = ?");

while ($res && $row = $res->fetch_assoc()) {
    $name = normalizeMenuName($row['menu_items']);
    if ($name === '') continue;

    // ชื่อตรงกันเท่านั้น ไม่เจอก็ข้ามไปแจ้งทีหลัง
    if (!isset($kitchen_cost[$name])) {
        $not_found[] = $name;
        continue;
    }

    $new_cost = $kitchen_cost[$name];
    $old_cost = round(floatval($row['cost_per_pax'] ?? 0), 2);

    if (abs($new_cost - $old_cost) < 0.005) {
        $unchanged++;
        continue;
    }

    $row_id = intval($row['id']);
    $stmt->bind_param("di", $new_cost, $row_id);
    $stmt->execute();

    $updated_rows[] = [
        'id'   => $row_id,
        'name' => $name,
        'old'  => $old_cost,
        'new'  => $new_cost,
    ];
}
$stmt->close();

jsonOut([
    'status'        => 'success',
    'updated'       => count($updated_rows),
    'unchanged'     => $unchanged,
    'not_found'     => array_values(array_unique($not_found)),
    'duplicates'    => array_keys($dup_names),
    'kitchen_menus' => count($kitchen_cost),
    'rows'          => $updated_rows,
]);
