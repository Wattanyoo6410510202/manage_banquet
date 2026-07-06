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
$by_id = intval($_GET['id'] ?? 0);

$data = [];
$status = 'success';
$error = '';

if ($conn->connect_error) {
    $status = 'error';
    $error = 'Database Connection Failed: ' . $conn->connect_error;
} else {
    if ($by_id) {
        $sql = "SELECT id, cust_name as text, cust_name, cust_phone, cust_address 
                FROM customers WHERE id = $by_id LIMIT 1";
    } else {
        $sql = "SELECT id, cust_name as text, cust_name, cust_phone, cust_address 
                FROM customers 
                WHERE cust_name LIKE '%$search%' OR cust_phone LIKE '%$search%'
                ORDER BY cust_name ASC 
                LIMIT 20";
    }

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