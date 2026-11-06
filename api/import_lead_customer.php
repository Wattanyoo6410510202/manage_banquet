<?php
// api/import_lead_customer.php
// รับลูกค้าจาก Lead Management: ถ้ามีในตาราง customers แล้วคืนรายเดิม ถ้ายังไม่มีเพิ่มให้
// ดึงข้อมูลจาก API ฝั่งเซิร์ฟเวอร์เสมอ ไม่รับค่าลูกค้าจากเบราว์เซอร์
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "../config.php";
include_once "../includes/lead_api.php";
header('Content-Type: application/json');

function respondError(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user'])) {
    respondError(401, 'unauthorized');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError(405, 'method not allowed');
}
if (strtolower($_SESSION['role'] ?? 'viewer') === 'viewer') {
    respondError(403, 'คุณไม่มีสิทธิ์เพิ่มข้อมูลลูกค้า');
}

$companyId = intval($_POST['company_id'] ?? 0);
$leadCustomerId = intval($_POST['lead_customer_id'] ?? 0);
if ($companyId <= 0 || $leadCustomerId <= 0) {
    respondError(422, 'ข้อมูลไม่ครบ');
}

$response = leadApiGet("/companies/{$companyId}/customers/{$leadCustomerId}");
if (isset($response['error'])) {
    respondError(502, $response['error']);
}

$lead = markExistingCustomers($conn, [$response['data']])[0];
if ($lead['in_customers'] === null) {
    respondError(403, 'API key นี้ไม่มีสิทธิ์ customers.contact จึงเพิ่มลูกค้าไม่ได้');
}

$created = false;
$customerId = $lead['matched_customer_id'];

if (!$lead['in_customers']) {
    $name = trim((string) ($lead['organization_name'] ?: $lead['display_name']));
    if ($name === '') {
        respondError(422, 'ลูกค้ารายนี้ไม่มีชื่อใน Lead Management');
    }
    // API ไม่ส่งเลขผู้เสียภาษีและที่อยู่ ต้องไปกรอกเพิ่มที่หน้าลูกค้า
    $contact = (string) ($lead['display_name'] ?? '');
    $phone = (string) ($lead['phone'] ?? '');
    $email = (string) ($lead['email'] ?? '');
    try {
        $stmt = $conn->prepare("INSERT INTO customers (cust_name, cust_tax_id, cust_address, cust_contact_name, cust_phone, cust_email) VALUES (?, '', '', ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $contact, $phone, $email);
        $stmt->execute();
        $customerId = $stmt->insert_id;
        $created = true;
    } catch (Throwable $e) {
        error_log('import_lead_customer: ' . $e->getMessage());
        respondError(500, 'เพิ่มลูกค้าไม่สำเร็จ');
    }
}

$stmt = $conn->prepare("SELECT id, cust_name, cust_phone, cust_address FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

echo json_encode(['status' => 'success', 'created' => $created, 'matched_by' => $lead['matched_by'], 'customer' => $customer]);
exit;
