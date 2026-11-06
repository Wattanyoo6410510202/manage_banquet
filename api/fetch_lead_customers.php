<?php
// api/fetch_lead_customers.php
// พร็อกซีฝั่งเซิร์ฟเวอร์ดึงลูกค้าจากระบบ Lead Management (อ่านอย่างเดียว)
// ไม่ระบุ company_id = รายชื่อบริษัทและสิทธิ์ของ key, ระบุ company_id = รายชื่อลูกค้าของบริษัทนั้น
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "../config.php";
include_once "../includes/lead_api.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

$companyId = intval($_GET['company_id'] ?? 0);
$path = '/companies';
if ($companyId > 0) {
    $query = http_build_query(['page' => max(1, intval($_GET['page'] ?? 1)), 'per_page' => 20]);
    $path = "/companies/{$companyId}/customers?{$query}";
}

$response = leadApiGet($path);
if (isset($response['error'])) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => $response['error']]);
    exit;
}

$data = $response['data'];
if ($companyId > 0 && $data) {
    $data = markExistingCustomers($conn, $data);
}

echo json_encode(['status' => 'success', 'data' => $data, 'meta' => $response['meta']]);
exit;
