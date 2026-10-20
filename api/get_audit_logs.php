<?php
/**
 * ดึง Audit Log สำหรับแท็บ ตั้งค่า > Audit Log (DataTables server-side)
 * รับ: draw, start, length, search[value] + ตัวกรอง date_from, date_to, action, entity_type, user_id
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$draw   = intval($_GET['draw'] ?? 0);
$start  = max(0, intval($_GET['start'] ?? 0));
$length = intval($_GET['length'] ?? 25);
if ($length <= 0 || $length > 500) $length = 25;

$where  = [];
$types  = '';
$params = [];

if (!empty($_GET['date_from'])) {
    $where[] = 'created_at >= ?';
    $types .= 's';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where[] = 'created_at <= ?';
    $types .= 's';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
foreach (['action', 'entity_type'] as $col) {
    if (!empty($_GET[$col])) {
        $where[] = "$col = ?";
        $types .= 's';
        $params[] = $_GET[$col];
    }
}
if (!empty($_GET['user_id'])) {
    $where[] = 'user_id = ?';
    $types .= 'i';
    $params[] = intval($_GET['user_id']);
}
$search = trim($_GET['search']['value'] ?? '');
if ($search !== '') {
    $where[] = '(user_name LIKE ? OR detail LIKE ? OR entity_id LIKE ? OR ip_address LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $total = $conn->query("SELECT COUNT(*) AS c FROM audit_logs")->fetch_assoc()['c'];
    $filtered = db_query($conn, "SELECT COUNT(*) AS c FROM audit_logs $where_sql", $types, ...$params)->fetch_assoc()['c'];

    $rows = [];
    $res = db_query(
        $conn,
        "SELECT id, created_at, user_name, role, action, entity_type, entity_id, detail, ip_address
         FROM audit_logs $where_sql ORDER BY id DESC LIMIT $start, $length",
        $types,
        ...$params
    );
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => intval($total),
        'recordsFiltered' => intval($filtered),
        'data'            => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
        'error' => 'ยังไม่มีตาราง audit_logs — กดปุ่ม "อัพเดทฐานข้อมูล" ก่อน (' . $e->getMessage() . ')',
    ], JSON_UNESCAPED_UNICODE);
}
