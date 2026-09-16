<?php
// api/export_quotations.php
// ส่งออกข้อมูลใบเสนอราคาทั้งหมดของระบบนี้ ให้เว็บ/เซิร์ฟเวอร์ภายนอกเรียกดึงไปใช้งาน
// เรียกจากฝั่งเซิร์ฟเวอร์ของเว็บปลายทางเท่านั้น ยืนยันตัวตนด้วย secret key คงที่ (ไม่ใช้ session ล็อกอินของพนักงาน)
// ดูวิธีเรียกใช้ที่ API-EXPORT-QUOTATIONS.md
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "../config.php";
header('Content-Type: application/json; charset=utf-8');

define('EXPORT_QUOTES_KEY', '82263da08c2d0c6b5d70ee113691f710a4de802617d06521');

$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '');
if (!is_string($provided_key) || $provided_key === '' || !hash_equals(EXPORT_QUOTES_KEY, $provided_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

$sql = "SELECT q.*,
               c.cust_name, c.cust_contact_name, c.cust_phone, c.cust_email, c.cust_tax_id, c.cust_address, c.sales_name,
               f.function_name,
               p.project_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN functions f ON q.function_id = f.id
        LEFT JOIN event_projects p ON q.project_id = p.id
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'query failed']);
    exit;
}

$quotes = [];
$ids = [];
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $ids[] = $id;
    $quotes[$id] = $row;
}

// ดึงรายการอาหาร/บริการของทุกใบในครั้งเดียว แล้วจับกลุ่มด้วย quote_id
$items_by_quote = [];
if (!empty($ids)) {
    $items_sql = "SELECT * FROM quotation_items WHERE quote_id IN (" . implode(',', $ids) . ") ORDER BY id";
    $items_res = $conn->query($items_sql);
    while ($item = $items_res->fetch_assoc()) {
        $items_by_quote[(int)$item['quote_id']][] = [
            'id' => (int)$item['id'],
            'item_name' => $item['item_name'],
            'quantity' => (float)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'total_price' => (float)$item['total_price'],
            'item_type' => $item['item_type'],
        ];
    }
}

$output = [];
foreach ($quotes as $id => $q) {
    $output[] = [
        'id' => $id,
        'quote_no' => $q['quote_no'],
        'status' => $q['status'],
        'workflow_status' => $q['workflow_status'] ?? null,
        'event_name' => $q['event_name'] ?: ($q['function_name'] ?? null),
        'event_date' => $q['event_date'],
        'expiry_date' => $q['expiry_date'],
        'project_id' => $q['project_id'] ? (int)$q['project_id'] : null,
        'project_name' => $q['project_name'],
        'customer' => [
            'id' => $q['customer_id'] ? (int)$q['customer_id'] : null,
            'name' => $q['cust_name'],
            'contact_name' => $q['cust_contact_name'],
            'phone' => $q['cust_phone'],
            'email' => $q['cust_email'],
            'tax_id' => $q['cust_tax_id'],
            'address' => $q['cust_address'],
            'sales_name' => $q['sales_name'],
        ],
        'subtotal' => (float)$q['subtotal'],
        'discount' => (float)$q['discount'],
        'service_charge' => (float)$q['service_charge'],
        'vat' => (float)$q['vat'],
        'vat_type' => $q['vat_type'],
        'grand_total' => (float)$q['grand_total'],
        'is_selected' => (bool)$q['is_selected'],
        'remarks' => $q['remarks'],
        'lead_source' => $q['lead_source'],
        'result' => $q['result'],
        'created_by' => $q['created_by'] !== null ? (int)$q['created_by'] : null,
        'approved_by' => $q['approved_by'] !== null ? (int)$q['approved_by'] : null,
        'approved_at' => $q['approved_at'],
        'created_at' => $q['created_at'],
        'updated_at' => $q['updated_at'],
        'items' => $items_by_quote[$id] ?? [],
    ];
}

echo json_encode(['status' => 'success', 'count' => count($output), 'data' => $output], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
