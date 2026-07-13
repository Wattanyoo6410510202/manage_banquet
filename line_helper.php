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
    $user = db_fetch_one($conn, "SELECT line_user_id FROM users WHERE username = ?", "s", $username);
    if (!$user || empty($user['line_user_id'])) {
        error_log('[LINE] No LINE User ID found for username: ' . $username);
        return false;
    }
    return sendLineNotify($user['line_user_id'], $message);
}

function sendLineNotifyToRole($conn, $role, $message) {
    $users = db_fetch_all($conn, "SELECT line_user_id FROM users WHERE LOWER(role) = LOWER(?) AND line_user_id IS NOT NULL AND line_user_id != ''", "s", $role);
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
