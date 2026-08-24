<?php
/**
 * นำเข้าต้นทุนอาหาร (per เมนู) จากไฟล์ Excel ที่แปลงเป็น JSON ฝั่ง client แล้ว (SheetJS)
 * รับ: { rows: [ { category_id, type_name, menu_name, cost }, ... ] }
 * จับคู่ด้วย category_id + type_name (สร้าง master_menu_types ให้อัตโนมัติถ้ายังไม่มี)
 * แล้วจับคู่ menu_items แบบตรงชื่อเป๊ะภายใน menu_type_id นั้น — เจอแล้วราคาต่างค่อยอัปเดต, ไม่เจอค่อยเพิ่มแถวใหม่
 */
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json; charset=utf-8');

function jsonOut($payload)
{
    if (ob_get_length()) ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function ($e) {
    http_response_code(200);
    jsonOut(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage()]);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) ob_clean();
        http_response_code(200);
        echo json_encode([
            'status'  => 'error',
            'message' => 'เกิดข้อผิดพลาดร้ายแรงฝั่งเซิร์ฟเวอร์: ' . $err['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

// เฉพาะ Admin เท่านั้น (หน้าตั้งค่าที่เรียก endpoint นี้ก็ล็อก admin_only อยู่แล้ว)
$user_role = strtolower($_SESSION['role'] ?? 'viewer');
if ($user_role !== 'admin') {
    jsonOut(['status' => 'error', 'message' => 'ขออภัย! เฉพาะผู้ดูแลระบบเท่านั้นที่นำเข้าต้นทุนได้']);
}

$input = json_decode(file_get_contents('php://input'), true);
$rows = $input['rows'] ?? [];
if (!is_array($rows) || empty($rows)) {
    jsonOut(['status' => 'error', 'message' => 'ไม่มีข้อมูลนำเข้า']);
}

$inserted = 0;
$updated = 0;
$unchanged = 0;
$types_created = 0;
$created_type_names = [];
$errors = [];
$type_cache = []; // "categoryId|typeName" => menu_type_id

foreach ($rows as $row) {
    $category_id = intval($row['category_id'] ?? 0);
    $type_name   = trim((string) ($row['type_name'] ?? ''));
    $menu_name   = trim((string) ($row['menu_name'] ?? ''));
    $cost        = round(floatval($row['cost'] ?? 0), 2);

    if ($category_id <= 0 || $type_name === '' || $menu_name === '') {
        $errors[] = 'ข้อมูลไม่ครบ: ' . ($menu_name !== '' ? $menu_name : '(ไม่ระบุชื่อเมนู)');
        continue;
    }

    $cat_exists = db_fetch_one($conn, "SELECT id FROM master_menu_categories WHERE id = ?", "i", $category_id);
    if (!$cat_exists) {
        $errors[] = "ไม่พบกลุ่มอาหาร id={$category_id} สำหรับเมนู {$menu_name}";
        continue;
    }

    $cache_key = $category_id . '|' . $type_name;
    if (!isset($type_cache[$cache_key])) {
        $existing_type = db_fetch_one(
            $conn,
            "SELECT id FROM master_menu_types WHERE category_id = ? AND type_name = ? LIMIT 1",
            "is",
            $category_id,
            $type_name
        );
        if ($existing_type) {
            $type_cache[$cache_key] = intval($existing_type['id']);
        } else {
            $new_type_id = db_insert(
                $conn,
                "INSERT INTO master_menu_types (category_id, type_name) VALUES (?, ?)",
                "is",
                $category_id,
                $type_name
            );
            $type_cache[$cache_key] = intval($new_type_id);
            $types_created++;
            $created_type_names[] = $type_name;
        }
    }
    $menu_type_id = $type_cache[$cache_key];

    $existing_item = db_fetch_one(
        $conn,
        "SELECT id, cost_per_pax FROM function_menu_details WHERE menu_type_id = ? AND menu_items = ? LIMIT 1",
        "is",
        $menu_type_id,
        $menu_name
    );

    if ($existing_item) {
        $old_cost = round(floatval($existing_item['cost_per_pax'] ?? 0), 2);
        if (abs($old_cost - $cost) >= 0.005) {
            db_execute(
                $conn,
                "UPDATE function_menu_details SET cost_per_pax = ? WHERE id = ?",
                "di",
                $cost,
                intval($existing_item['id'])
            );
            $updated++;
        } else {
            $unchanged++;
        }
    } else {
        db_insert(
            $conn,
            "INSERT INTO function_menu_details (menu_type_id, menu_items, beverage_detail, guarantee_pax, price_per_pax, cost_per_pax)
             VALUES (?, ?, '', 1, 0, ?)",
            "isd",
            $menu_type_id,
            $menu_name,
            $cost
        );
        $inserted++;
    }
}

jsonOut([
    'status'              => 'success',
    'inserted'            => $inserted,
    'updated'             => $updated,
    'unchanged'           => $unchanged,
    'types_created'       => $types_created,
    'created_type_names'  => array_values(array_unique($created_type_names)),
    'errors'              => $errors,
]);
