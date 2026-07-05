<?php
include_once "config.php";
include_once "line_helper.php";

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['events'])) {
    http_response_code(400);
    exit;
}

$channelToken = LINE_CHANNEL_ACCESS_TOKEN;

foreach ($data['events'] as $event) {
    $replyToken = $event['replyToken'] ?? '';
    $userId = $event['source']['userId'] ?? '';
    $eventType = $event['type'] ?? '';

    if ($eventType === 'follow') {
        $msg = "🙏 ขอบคุณที่เพิ่มเพื่อน!\n\n"
            . "LINE User ID ของคุณคือ:\n{$userId}\n\n"
            . "กรุณาแจ้ง LINE ID นี้ให้ Admin เพื่อรับการแจ้งเตือนจากระบบ";
    } elseif ($eventType === 'message' && $event['message']['type'] === 'text') {
        $text = trim($event['message']['text']);
        if (strtolower($text) === 'myid' || strtolower($text) === 'id') {
            $msg = "LINE User ID ของคุณคือ:\n{$userId}";
        } else {
            $msg = "สวัสดีค่ะ 🙏\n\nLINE User ID ของคุณคือ:\n{$userId}\n\n"
                . "แจ้ง ID นี้ให้ Admin เพื่อรับการแจ้งเตือนจากระบบนะคะ";
        }
    } else {
        continue;
    }

    // Reply กลับไป
    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channelToken
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'replyToken' => $replyToken,
            'messages' => [['type' => 'text', 'text' => $msg]]
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
