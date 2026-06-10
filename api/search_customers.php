<?php
include "../config.php";

$search = $_GET['q'] ?? '';
$search = $conn->real_escape_string($search);

$sql = "SELECT id, cust_name as text, cust_name, cust_phone, cust_address FROM customers 
        WHERE cust_name LIKE '%$search%' 
        ORDER BY cust_name ASC 
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['results' => $data]);
?>