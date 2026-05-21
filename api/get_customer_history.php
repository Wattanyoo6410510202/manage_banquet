<?php
include "../config.php";

$customer_id = intval($_GET['customer_id'] ?? 0);

if ($customer_id > 0) {
    $sql = "SELECT id, function_name, status, created_at FROM functions WHERE customer_id = $customer_id ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    $events = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $events]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID']);
}
?>