<?php
// api/find_or_create_customer.php
// ใช้ตอนกด "ทำใบเสนอราคา" จากใบเสนอราคาระบบภายนอก (quotation_list.php)
// ค้นหาลูกค้าเดิมจากเบอร์โทร/ชื่อก่อน ถ้าไม่พบให้เพิ่มลูกค้าใหม่ แล้วคืน customer_id
// เพื่อให้หน้า add_quote.php เลือกลูกค้าไว้ให้ตั้งแต่โหลดหน้า (ผ่าน ?customer_id=)
include "../config.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน'], JSON_UNESCAPED_UNICODE);
    exit;
}

$customer_name = trim($_POST['customer_name'] ?? '');
$company = trim($_POST['company'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($customer_name === '' && $company === '') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีชื่อลูกค้าหรือบริษัทสำหรับค้นหา/เพิ่มลูกค้า'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ชื่อลูกค้าหลักในระบบนี้ใช้ชื่อบริษัทเป็นหลักถ้ามี (สอดคล้องกับข้อมูลลูกค้าที่มีอยู่เดิมในระบบ)
$cust_name = $company !== '' ? $company : $customer_name;
$cust_contact_name = $company !== '' ? $customer_name : '';

// 1) ค้นหาลูกค้าเดิมจากเบอร์โทรก่อน (แม่นยำสุด)
$found = null;
if ($phone !== '') {
    $phone_esc = $conn->real_escape_string($phone);
    $res = $conn->query("SELECT id, cust_name, cust_phone, cust_address FROM customers WHERE cust_phone = '$phone_esc' LIMIT 1");
    if ($res && $res->num_rows > 0) $found = $res->fetch_assoc();
}

// 2) ถ้าไม่เจอ ลองค้นจากชื่อลูกค้า/บริษัทตรงกันเป๊ะ
if (!$found) {
    $name_esc = $conn->real_escape_string($cust_name);
    $res = $conn->query("SELECT id, cust_name, cust_phone, cust_address FROM customers WHERE cust_name = '$name_esc' LIMIT 1");
    if ($res && $res->num_rows > 0) $found = $res->fetch_assoc();
}

if ($found) {
    echo json_encode([
        'status' => 'success',
        'created' => false,
        'id' => (int)$found['id'],
        'cust_name' => $found['cust_name'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3) ไม่พบลูกค้าเดิม -> เพิ่มลูกค้าใหม่ให้อัตโนมัติ
$cust_name_esc = $conn->real_escape_string($cust_name);
$cust_contact_esc = $conn->real_escape_string($cust_contact_name);
$phone_esc = $conn->real_escape_string($phone);
$email_esc = $conn->real_escape_string($email);

$sql = "INSERT INTO customers (cust_name, cust_tax_id, cust_address, cust_contact_name, cust_phone, cust_email, sales_name)
        VALUES ('$cust_name_esc', '', '', '$cust_contact_esc', '$phone_esc', '$email_esc', '')";

if ($conn->query($sql)) {
    echo json_encode([
        'status' => 'success',
        'created' => true,
        'id' => $conn->insert_id,
        'cust_name' => $cust_name,
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เพิ่มลูกค้าใหม่ไม่สำเร็จ: ' . $conn->error], JSON_UNESCAPED_UNICODE);
}
exit;
