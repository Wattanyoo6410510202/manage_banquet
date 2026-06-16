<?php
// api/search_customers.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // ซ่อน error ไม่ให้กวน JSON

include "../config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$search = $_GET['q'] ?? '';
$search = $conn->real_escape_string($search);

$data = [];
$status = 'success';
$error = '';

if ($conn->connect_error) {
    $status = 'error';
    $error = 'Database Connection Failed: ' . $conn->connect_error;
} else {
    // ค้นหาตามชื่อลูกค้า หรือเบอร์โทร
    $sql = "SELECT id, cust_name as text, cust_name, cust_phone, cust_address 
            FROM customers 
            WHERE cust_name LIKE '%$search%' OR cust_phone LIKE '%$search%'
            ORDER BY cust_name ASC 
            LIMIT 20";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        $status = 'error';
        $error = 'Query Failed: ' . $conn->error;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'results' => $data,
    'error' => $error
]);
exit;
?>