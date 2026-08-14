<?php
define('LINE_CHANNEL_ACCESS_TOKEN', 'xdGd0ps46rYZ/NUKHD/tZii6z+pZE+jKXYetbUuBXsNFFVhq62G3//avzMtQJ/KivEXLWpkzbKQ2LvCmR8FJCHw5ofGjWPChlTW/0roBpcafWMt1z3Pxy6XiCfloToyn5NUDgVfsJUdkDvsgDjFuDAdB04t89/1O/w1cDnyilFU=');

function sendLineNotify($userId, $message) {
    if (empty($userId)) {
        error_log('[LINE] Send failed: empty userId');
        return false;
    }

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'to' => $userId,
            'messages' => [['type' => 'text', 'text' => $message]]
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('[LINE] cURL error for userId ' . $userId . ': ' . $curlError);
        return false;
    }

    if ($httpCode !== 200) {
        error_log('[LINE] API error for userId ' . $userId . ': HTTP ' . $httpCode . ' - ' . $result);
        return false;
    }

    return true;
}

function sendLineNotifyToUser($conn, $username, $message) {
    $stmt = $conn->prepare("SELECT line_user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || empty($user['line_user_id'])) {
        error_log('[LINE] No LINE User ID found for username: ' . $username);
        return false;
    }
    return sendLineNotify($user['line_user_id'], $message);
}

function sendLineNotifyToRole($conn, $role, $message) {
    $stmt = $conn->prepare("SELECT line_user_id FROM users WHERE LOWER(role) = LOWER(?) AND line_user_id IS NOT NULL AND line_user_id != ''");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        if (sendLineNotify($u['line_user_id'], $message)) {
            $sent++;
        } else {
            $failed++;
        }
    }
    if ($failed > 0) {
        error_log('[LINE] Role "' . $role . '": sent ' . $sent . ', failed ' . $failed);
    }
    return ['sent' => $sent, 'failed' => $failed];
}

function sendLineFlex($userId, $flexJson, $altText = 'แจ้งเตือนจากระบบ') {
    if (empty($userId)) {
        error_log('[LINE] Flex send failed: empty userId');
        return false;
    }

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'to' => $userId,
            'messages' => [[
                'type' => 'flex',
                'altText' => $altText,
                'contents' => $flexJson
            ]]
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('[LINE] Flex cURL error for userId ' . $userId . ': ' . $curlError);
        return false;
    }
    if ($httpCode !== 200) {
        error_log('[LINE] Flex API error for userId ' . $userId . ': HTTP ' . $httpCode . ' - ' . $result);
        return false;
    }
    return true;
}

function sendLineFlexToRole($conn, $role, $flexJson, $altText = 'แจ้งเตือนจากระบบ') {
    $stmt = $conn->prepare("SELECT line_user_id FROM users WHERE LOWER(role) = LOWER(?) AND line_user_id IS NOT NULL AND line_user_id != ''");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        if (sendLineFlex($u['line_user_id'], $flexJson, $altText)) {
            $sent++;
        } else {
            $failed++;
        }
    }
    if ($failed > 0) {
        error_log('[LINE] Flex Role "' . $role . '": sent ' . $sent . ', failed ' . $failed);
    }
    return ['sent' => $sent, 'failed' => $failed];
}

/**
 * ส่ง Flex ให้หลาย role พร้อมกัน โดยรวม LINE ID ที่ซ้ำกันให้เหลือครั้งเดียว
 *
 * จำเป็นเพราะหลาย role อาจผูกกับ LINE ID เดียวกัน (คนเดียวสวมหลายหมวก)
 * ถ้าวนส่งทีละ role คนนั้นจะได้การ์ดเดิมซ้ำหลายใบ
 */
function sendLineFlexToRoles($conn, array $roles, $flexJson, $altText = 'แจ้งเตือนจากระบบ')
{
    $roles = array_values(array_filter(array_map('strtolower', $roles)));
    if (empty($roles)) {
        return ['sent' => 0, 'failed' => 0];
    }

    $ph  = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT DISTINCT line_user_id FROM users
            WHERE LOWER(role) IN ($ph) AND line_user_id IS NOT NULL AND line_user_id != ''";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($roles)), ...$roles);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        if (sendLineFlex($u['line_user_id'], $flexJson, $altText)) $sent++;
        else $failed++;
    }
    if ($failed > 0) {
        error_log('[LINE] Flex Roles "' . implode(',', $roles) . '": sent ' . $sent . ', failed ' . $failed);
    }
    return ['sent' => $sent, 'failed' => $failed];
}

/** ส่ง Flex ให้ผู้ใช้คนเดียวจาก users.id */
function sendLineFlexToUserId($conn, $userRowId, $flexJson, $altText = 'แจ้งเตือนจากระบบ')
{
    $userRowId = intval($userRowId);
    if ($userRowId <= 0) {
        return ['sent' => 0, 'failed' => 0];
    }

    $stmt = $conn->prepare("SELECT line_user_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userRowId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || empty($u['line_user_id'])) {
        error_log('[LINE] No LINE User ID for users.id ' . $userRowId);
        return ['sent' => 0, 'failed' => 1];
    }
    return sendLineFlex($u['line_user_id'], $flexJson, $altText)
        ? ['sent' => 1, 'failed' => 0]
        : ['sent' => 0, 'failed' => 1];
}

// ═══════════════════════════════════════════════════════
// Flex Message Builders
// ═══════════════════════════════════════════════════════

/**
 * การ์ดแจ้งเตือนแบบทั่วไป ใช้ร่วมกันทุกเหตุการณ์ที่ไม่ต้องแยกเนื้อหารายแผนก
 *
 * $opts = [
 *   'title'    => หัวการ์ด
 *   'subtitle' => บรรทัดรองภาษาอังกฤษ
 *   'color'    => สีแถบหัว
 *   'rows'     => [[label, value] หรือ [label, value, สีค่า], ...]
 *   'note'     => ข้อความเน้นใต้ตาราง (ไม่ใส่ก็ได้)
 *   'buttons'  => [[label, uri], ...]
 * ]
 */
function buildNotifyFlex(array $opts)
{
    $color = $opts['color'] ?? '#607D8B';

    $rows = [];
    foreach ($opts['rows'] ?? [] as $r) {
        $rows[] = flexLabelValue($r[0], $r[1], $r[2] ?? '#555555');
    }

    if (!empty($opts['note'])) {
        $rows[] = flexSeparator();
        $rows[] = [
            'type' => 'text', 'text' => $opts['note'], 'size' => 'xs',
            'color' => $color, 'wrap' => true, 'margin' => 'md', 'weight' => 'bold',
        ];
    }

    $buttons = [];
    foreach ($opts['buttons'] ?? [] as $i => $b) {
        $buttons[] = [
            'type' => 'button', 'height' => 'sm', 'color' => $color,
            'style' => $i === 0 ? 'primary' : 'link',
            'action' => ['type' => 'uri', 'label' => $b[0], 'uri' => $b[1]],
        ];
    }

    return [
        'type' => 'bubble', 'size' => 'mega',
        'header' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs',
            'contents' => [
                ['type' => 'text', 'text' => $opts['title'] ?? 'แจ้งเตือน', 'weight' => 'bold', 'size' => 'lg', 'color' => '#FFFFFF', 'wrap' => true],
                ['type' => 'text', 'text' => $opts['subtitle'] ?? '', 'size' => 'xs', 'color' => '#FFFFFFBB'],
            ],
            'backgroundColor' => $color, 'paddingAll' => '16px',
        ],
        'body' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '16px',
            'contents' => $rows,
        ],
        'footer' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs',
            'contents' => $buttons, 'paddingAll' => '12px',
        ],
    ];
}

/** URL ฐานของเว็บ ใช้ประกอบลิงก์ในปุ่มการ์ด */
function lineOriginUrl()
{
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        return $_SERVER['HTTP_ORIGIN'];
    }
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function flexLabelValue($label, $value, $color = '#555555') {
    return [
        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
        'contents' => [
            ['type' => 'text', 'text' => $label, 'size' => 'sm', 'color' => '#888888', 'flex' => 0, 'weight' => 'bold'],
            ['type' => 'text', 'text' => ($value === null || $value === '' || $value === false) ? '-' : (string)$value, 'size' => 'sm', 'color' => $color, 'wrap' => true, 'align' => 'end', 'flex' => 4],
        ]
    ];
}

function flexSeparator() {
    return ['type' => 'separator', 'margin' => 'md', 'color' => '#E0E0E0'];
}

function flexChecklist($tasks) {
    $items = [];
    foreach ($tasks as $i => $t) {
        $items[] = [
            'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'xs',
            'contents' => [
                ['type' => 'text', 'text' => '☐ ' . ($i + 1) . '.', 'size' => 'xs', 'color' => '#1DB446', 'flex' => 0],
                ['type' => 'text', 'text' => $t, 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
            ]
        ];
    }
    return $items;
}

function buildApprovedFlex($detail, $approveName, $originUrl, $roleKey = 'admin', $sections = [], $checklist = [], $calendarUrl = '') {
    $colorMap = [
        'banquet_staff' => '#FF6B35', 'technician' => '#2196F3',
        'housekeeping' => '#9C27B0', 'admin' => '#607D8B', 'procurement' => '#795548', 'staff' => '#E67E22',
    ];
    $nameMap = [
        'banquet_staff' => '🍽️ จัดเลี้ยง', 'technician' => '⚙️ วิศวกรรม',
        'housekeeping' => '🧹 ทำความสะอาด', 'admin' => '👤 ผู้ดูแลระบบ', 'procurement' => '📦 จัดซื้อ', 'staff' => '👥 พนักงาน',
    ];
    $pageMap = [
        'banquet_staff' => 'banquet_bk.php', 'technician' => 'banquet_mt.php', 'housekeeping' => 'banquet_hk.php',
    ];

    $bodyContents = [
        flexLabelValue('📌', $detail['function_name'], '#111111'),
        flexLabelValue('👤', $detail['booking_name']),
        flexLabelValue('📞', $detail['phone']),
    ];
    if (!in_array($roleKey, ['banquet_staff', 'technician', 'housekeeping'])) {
        $bodyContents[] = flexLabelValue('💰', number_format($detail['total_amount'], 2) . ' บาท', '#111111');
    }
    $bodyContents = array_merge($bodyContents, [
        flexLabelValue('🏠', $detail['booking_room']),
        flexLabelValue('📅 เริ่ม', date('d/m/Y H:i', strtotime($detail['start_time']))),
        flexLabelValue('📅 สิ้นสุด', date('d/m/Y H:i', strtotime($detail['end_time']))),
        flexSeparator(),
        flexLabelValue('👨‍💼 อนุมัติ', $approveName, '#1DB446'),
        flexLabelValue('🆔', $detail['function_code'], '#111111'),
    ]);

    $body = [
        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '16px',
        'contents' => $bodyContents,
    ];

    // Role-specific section
    if (!empty($sections) || !empty($checklist)) {
        $roleContents = [
            flexSeparator(),
            ['type' => 'text', 'text' => $nameMap[$roleKey] ?? $roleKey, 'weight' => 'bold', 'size' => 'md', 'color' => $colorMap[$roleKey] ?? '#333', 'margin' => 'md'],
        ];

        foreach ($sections as $sec) {
            $lines = [];
            $lines[] = ['type' => 'text', 'text' => $sec['label'], 'weight' => 'bold', 'size' => 'sm', 'color' => '#555555', 'margin' => 'md'];
            foreach (explode("\n", $sec['text']) as $line) {
                if (trim($line) !== '') {
                    $lines[] = ['type' => 'text', 'text' => $line, 'size' => 'xs', 'color' => '#666666', 'wrap' => true, 'margin' => 'xs'];
                }
            }
            $roleContents[] = ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'contents' => $lines];
        }

        if (!empty($checklist)) {
            $chk = [['type' => 'text', 'text' => '✅ สิ่งที่ต้องทำ', 'weight' => 'bold', 'size' => 'sm', 'color' => '#1DB446', 'margin' => 'md']];
            foreach (flexChecklist($checklist) as $ci) { $ci['margin'] = 'xs'; $chk[] = $ci; }
            $roleContents[] = ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'contents' => $chk];
        }

        $body['contents'][] = ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'contents' => $roleContents];
    }

    $targetPage = $pageMap[$roleKey] ?? 'manage_banquet.php';

    return [
        'type' => 'bubble', 'size' => 'mega',
        'header' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs',
            'contents' => [
                ['type' => 'text', 'text' => '✅ งานได้รับการอนุมัติ', 'weight' => 'bold', 'size' => 'lg', 'color' => '#FFFFFF'],
                ['type' => 'text', 'text' => 'Approved Event', 'size' => 'xs', 'color' => '#FFFFFFBB'],
            ],
            'backgroundColor' => '#1DB446', 'paddingAll' => '16px',
        ],
        'body' => $body,
        'footer' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'contents' => array_merge(
                [[
                    'type' => 'button', 'style' => 'primary', 'color' => '#1DB446', 'height' => 'sm',
                    'action' => ['type' => 'uri', 'label' => 'เปิดดูงาน', 'uri' => $originUrl . '/manage_banquet/' . $targetPage],
                ]],
                $calendarUrl ? [[
                    'type' => 'button', 'style' => 'link', 'color' => '#1DB446', 'height' => 'sm',
                    'action' => ['type' => 'uri', 'label' => '📅 ดูปฏิทิน', 'uri' => $calendarUrl],
                ]] : []
            ), 'paddingAll' => '12px',
        ],
    ];
}

function buildDraftFlex($functionName, $draftName, $originUrl) {
    return [
        'type' => 'bubble', 'size' => 'mega',
        'header' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs',
            'contents' => [
                ['type' => 'text', 'text' => '📝 มี Draft ใหม่', 'weight' => 'bold', 'size' => 'lg', 'color' => '#FFFFFF'],
                ['type' => 'text', 'text' => 'New Draft Version', 'size' => 'xs', 'color' => '#FFFFFFBB'],
            ],
            'backgroundColor' => '#6C5CE7', 'paddingAll' => '16px',
        ],
        'body' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md', 'paddingAll' => '16px',
            'contents' => [
                flexLabelValue('📌 ชื่องาน', $functionName, '#111111'),
                flexLabelValue('📋 Draft', $draftName, '#6C5CE7'),
                flexLabelValue('📅 วันที่', date('d/m/Y H:i')),
            ],
        ],
        'footer' => [
            'type' => 'box', 'layout' => 'vertical', 'contents' => [[
                'type' => 'button', 'style' => 'primary', 'color' => '#6C5CE7', 'height' => 'sm',
                'action' => ['type' => 'uri', 'label' => 'เปิดดูงาน', 'uri' => $originUrl . '/manage_banquet/manage_banquet.php'],
            ]], 'paddingAll' => '12px',
        ],
    ];
}
