<?php
// api/fetch_external_quotations.php
// พร็อกซีฝั่งเซิร์ฟเวอร์ดึงใบเสนอราคาจากเว็บจัดเลี้ยงอีกระบบ (Supabase RPC)
// ต้องเรียกจากฝั่งเซิร์ฟเวอร์เท่านั้น ห้ามฝัง p_key ไว้ฝั่ง browser (ดู API-EXPORT.md)
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "../config.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

define('EXT_QUOTE_ENDPOINT', 'https://govmturozgtfvllvnvap.supabase.co/rest/v1/rpc/export_quotations');
define('EXT_QUOTE_APIKEY', 'sb_publishable_N9psu9QmYz3hcIcPiasFdw_sMzcFukU');
define('EXT_QUOTE_PKEY', 'NNP3FB1CApxXNDuEDmkXG5NGwnhUe0zW');

$ch = curl_init(EXT_QUOTE_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . EXT_QUOTE_APIKEY,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['p_key' => EXT_QUOTE_PKEY]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result === false) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'เชื่อมต่อระบบภายนอกไม่สำเร็จ: ' . $curlError]);
    exit;
}

$data = json_decode($result, true);

if ($httpCode !== 200 || !is_array($data)) {
    http_response_code(502);
    $msg = is_array($data) ? ($data['message'] ?? json_encode($data)) : $result;
    echo json_encode(['status' => 'error', 'message' => 'ระบบภายนอกตอบกลับผิดพลาด: ' . $msg]);
    exit;
}

echo json_encode(['status' => 'success', 'data' => $data]);
exit;
