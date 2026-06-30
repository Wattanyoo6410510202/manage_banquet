<?php
include "../config.php";

$id = intval($_GET['id'] ?? 0);
$compare_id = intval($_GET['compare_id'] ?? 0);

if (!$id || !$compare_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing IDs']);
    exit;
}

function getFunctionData($conn, $id) {
    $sql = "SELECT f.*, r.room_name, ft.type_name 
            FROM functions f 
            LEFT JOIN meeting_rooms r ON f.room_id = r.id
            LEFT JOIN function_types ft ON f.function_type_id = ft.id
            WHERE f.id = $id";
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : null;
}

function getSchedules($conn, $id) {
    $res = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

function getKitchens($conn, $id) {
    $res = $conn->query("SELECT * FROM function_kitchens WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

function getMenus($conn, $id) {
    $res = $conn->query("SELECT * FROM function_menus WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

$a = getFunctionData($conn, $id);
$b = getFunctionData($conn, $compare_id);

if (!$a || !$b) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Draft not found']);
    exit;
}

$compare_fields = [
    'function_name' => 'ชื่องาน',
    'type_name' => 'ประเภทงาน',
    'room_name' => 'ห้อง',
    'start_time' => 'เวลาเริ่ม',
    'end_time' => 'เวลาสิ้นสุด',
    'total_amount' => 'มูลค่างาน',
    'deposit' => 'มัดจำ',
    'booking_name' => 'ผู้จอง',
    'phone' => 'เบอร์โทร',
    'organization' => 'ที่อยู่',
    'draft_name' => 'ชื่อ Draft',
    'booking_room' => 'Booking No.',
    'pax' => 'จำนวน (PAX)',
    'banquet_style' => 'การจัดงานเลี้ยง',
    'equipment' => 'งานช่างและภาพเสียง',
    'remark' => 'หมายเหตุ',
    'backdrop_detail' => 'ฉากหลัง',
    'hk_florist_detail' => 'ทำความสะอาด/ดอกไม้',
    'lead_source' => 'ที่มา Lead',
    'result' => 'ผลการดำเนินงาน',
    'inspection_date' => 'Inspection',
    'follow_up_date' => 'Follow Up',
];

$differences = [];

foreach ($compare_fields as $field => $label) {
    $val_a = $a[$field] ?? '';
    $val_b = $b[$field] ?? '';
    if ((string)$val_a !== (string)$val_b) {
        $differences[] = [
            'label' => $label,
            'field' => $field,
            'old_value' => $val_b,
            'new_value' => $val_a,
        ];
    }
}

$sched_a = getSchedules($conn, $id);
$sched_b = getSchedules($conn, $compare_id);
if (json_encode($sched_a) !== json_encode($sched_b)) {
    $differences[] = [
        'label' => 'ตารางกำหนดการ',
        'field' => 'schedules',
        'old_value' => count($sched_b) . ' รายการ',
        'new_value' => count($sched_a) . ' รายการ',
    ];
}

$kit_a = getKitchens($conn, $id);
$kit_b = getKitchens($conn, $compare_id);
if (json_encode($kit_a) !== json_encode($kit_b)) {
    $differences[] = [
        'label' => 'รายการครัว',
        'field' => 'kitchens',
        'old_value' => count($kit_b) . ' รายการ',
        'new_value' => count($kit_a) . ' รายการ',
    ];
}

$menu_a = getMenus($conn, $id);
$menu_b = getMenus($conn, $compare_id);
if (json_encode($menu_a) !== json_encode($menu_b)) {
    $differences[] = [
        'label' => 'รายการอาหาร',
        'field' => 'menus',
        'old_value' => count($menu_b) . ' รายการ',
        'new_value' => count($menu_a) . ' รายการ',
    ];
}

echo json_encode([
    'status' => 'success',
    'draft_a' => ['id' => $a['id'], 'name' => $a['draft_name'], 'version' => $a['version_no']],
    'draft_b' => ['id' => $b['id'], 'name' => $b['draft_name'], 'version' => $b['version_no']],
    'differences' => $differences,
    'total_changes' => count($differences),
]);
