<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../line_helper.php';

header('Content-Type: application/json; charset=utf-8');

// เฉพาะ Admin เท่านั้น (หน้า setting.php ก็จำกัด admin)
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่ระบุผู้ใช้'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT name, username, role, line_user_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if (!$u) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'ไม่พบผู้ใช้นี้ในระบบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($u['line_user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'ผู้ใช้นี้ยังไม่ได้ผูก LINE User ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// การ์ดทดสอบ — ใช้ builder เดียวกับการแจ้งเตือนจริง เพื่อทดสอบให้เหมือนของจริง
$flex = buildNotifyFlex([
    'title' => '🔔 ทดสอบการแจ้งเตือน',
    'subtitle' => 'LINE Notification Test',
    'color' => '#1DB446',
    'rows' => [
        ['👤 ชื่อ', $u['name'] ?: $u['username']],
        ['🆔 Username', $u['username']],
        ['🏷️ Role', $u['role']],
        ['🕐 ส่งเมื่อ', date('d/m/Y H:i:s')],
    ],
    'note' => 'ถ้าคุณเห็นข้อความนี้ แสดงว่า LINE User ID ของคุณผูกถูกต้อง ระบบจะส่งแจ้งเตือนงานมาที่แชทนี้',
]);

$sent = sendLineFlex($u['line_user_id'], $flex, '🔔 ข้อความทดสอบจากระบบจัดเลี้ยง');

if ($sent) {
    echo json_encode([
        'ok' => true,
        'message' => 'ส่งข้อความทดสอบถึง ' . ($u['name'] ?: $u['username']) . ' สำเร็จ กรุณาตรวจสอบแชท LINE',
    ], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[LINE] Test send failed for users.id ' . $user_id . ' (line_user_id: ' . $u['line_user_id'] . ')');
    echo json_encode([
        'ok' => false,
        'message' => 'ส่งไม่สำเร็จ — LINE User ID อาจไม่ถูกต้อง หรือผู้ใช้ยังไม่ได้เพิ่มเพื่อนกับ Bot',
    ], JSON_UNESCAPED_UNICODE);
}
