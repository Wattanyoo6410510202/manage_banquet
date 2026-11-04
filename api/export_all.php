<?php
// api/export_all.php
// ส่งออกข้อมูลทั้งหมดของระบบ (ทุกตารางในฐานข้อมูล) ให้เว็บ/เซิร์ฟเวอร์ภายนอกดึงไปใช้งาน
// เรียกจากฝั่งเซิร์ฟเวอร์ของเว็บปลายทางเท่านั้น ยืนยันตัวตนด้วย secret key คงที่ (ไม่ใช้ session ล็อกอินของพนักงาน)
// ดูวิธีเรียกใช้ที่ API-EXPORT-ALL.md
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120);
ini_set('memory_limit', '512M');

include "../config.php";
header('Content-Type: application/json; charset=utf-8');

define('EXPORT_ALL_KEY', '63b15a505dcd53139466cd99dcd57b7812a5e473b6987ef7');

// ตารางที่ไม่ส่งออก (ตอนนี้ส่งออกทุกตาราง)
const EXPORT_SKIP_TABLES = [];
// คอลัมน์ที่ห้ามหลุดออกไปเด็ดขาด
const EXPORT_HIDDEN_COLUMNS = [
    'users' => ['password'],
];

$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '');
if (!is_string($provided_key) || $provided_key === '' || !hash_equals(EXPORT_ALL_KEY, $provided_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

// คืนค่าตัวเลขเป็น int/float จริงใน JSON แทน string
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

// หาตารางทั้งหมดจากฐานข้อมูลจริง (ตารางใหม่ที่เพิ่มภายหลังจะถูกส่งออกอัตโนมัติ)
$all_tables = [];
$res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($row = $res->fetch_row()) {
    if (!in_array($row[0], EXPORT_SKIP_TABLES, true)) {
        $all_tables[] = $row[0];
    }
}

// ?tables=quotations,customers  → ดึงเฉพาะบางตาราง
// ?list=1                       → ดูรายชื่อตาราง + จำนวนแถวอย่างเดียว
$requested = $_REQUEST['tables'] ?? '';
$tables = $all_tables;
if (is_string($requested) && trim($requested) !== '') {
    $wanted = array_filter(array_map('trim', explode(',', $requested)));
    $unknown = array_values(array_diff($wanted, $all_tables));
    if ($unknown) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'unknown table', 'unknown' => $unknown, 'available' => $all_tables], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tables = array_values(array_intersect($all_tables, $wanted));
}

$list_only = !empty($_REQUEST['list']);

$data = [];
$counts = [];
foreach ($tables as $table) {
    // ชื่อตารางมาจาก SHOW TABLES เท่านั้น จึงปลอดภัยที่จะใส่ใน query
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    if ($list_only) {
        $counts[$table] = (int)$conn->query("SELECT COUNT(*) FROM $quoted")->fetch_row()[0];
        continue;
    }
    $hidden = EXPORT_HIDDEN_COLUMNS[$table] ?? [];
    $rows = [];
    $r = $conn->query("SELECT * FROM $quoted");
    if (!$r) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'query failed', 'table' => $table]);
        exit;
    }
    while ($row = $r->fetch_assoc()) {
        foreach ($hidden as $col) {
            unset($row[$col]);
        }
        $rows[] = $row;
    }
    $data[$table] = $rows;
    $counts[$table] = count($rows);
}

$out = [
    'status' => 'success',
    'exported_at' => date('c'),
    'tables' => $counts,
];
if (!$list_only) {
    $out['data'] = $data;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
exit;
