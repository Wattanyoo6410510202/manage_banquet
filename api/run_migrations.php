<?php
/**
 * อัพเดทฐานข้อมูล: รันไฟล์ .sql ใน migrations/ ที่ยังไม่เคยรัน (เรียงตามชื่อไฟล์)
 * ไฟล์ที่รันแล้วจะจดไว้ในตาราง schema_migrations — กดซ้ำได้ ไม่รันซ้ำ
 * ใช้ตอน git pull บน NAS แล้วมีการเปลี่ยนโครงสร้างตาราง
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

// เฉพาะ Admin เท่านั้น (หน้า setting.php ก็จำกัด admin)
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'ต้องเรียกด้วย POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$applied = [];
$res = $conn->query("SELECT filename FROM schema_migrations");
while ($row = $res->fetch_assoc()) {
    $applied[$row['filename']] = true;
}

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

$ran = [];
foreach ($files as $path) {
    $name = basename($path);
    if (isset($applied[$name])) continue;

    // ไฟล์หนึ่งมีได้หลายคำสั่ง ต้องเก็บผลให้ครบทุกคำสั่งก่อนรันไฟล์ถัดไป
    if (!$conn->multi_query(file_get_contents($path))) {
        echo json_encode(['ok' => false, 'ran' => $ran, 'message' => "รัน $name ไม่สำเร็จ: " . $conn->error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    do {
        if ($r = $conn->store_result()) $r->free();
    } while ($conn->more_results() && $conn->next_result());
    if ($conn->errno) {
        echo json_encode(['ok' => false, 'ran' => $ran, 'message' => "รัน $name ไม่สำเร็จ: " . $conn->error], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $ran[] = $name;
}

if ($ran) audit_log($conn, 'migrate', 'database', null, 'รัน migration: ' . implode(', ', $ran));

echo json_encode([
    'ok' => true,
    'ran' => $ran,
    'message' => $ran ? 'อัพเดทแล้ว ' . count($ran) . ' รายการ: ' . implode(', ', $ran) : 'ฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว',
], JSON_UNESCAPED_UNICODE);
