<?php
header('Content-Type: application/json; charset=utf-8');
include "../config.php";

$type_id = intval($_GET['type_id'] ?? 0);
if ($type_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, break_menu, break_price, break_cost 
        FROM function_breaks 
        WHERE break_type_id = ? 
        ORDER BY id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $type_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => intval($row['id']),
        'break_menu' => $row['break_menu'],
        'break_price' => floatval($row['break_price']),
        'break_cost' => floatval($row['break_cost']),
    ];
}

echo json_encode($items, JSON_UNESCAPED_UNICODE);
?>
