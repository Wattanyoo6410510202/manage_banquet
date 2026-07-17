<?php
header('Content-Type: application/json; charset=utf-8');
include "../config.php";

$type_id = intval($_GET['type_id'] ?? 0);
if ($type_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, menu_items, price_per_pax, cost_per_pax 
        FROM function_menu_details 
        WHERE menu_type_id = ? 
        ORDER BY id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $type_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => intval($row['id']),
        'menu_items' => $row['menu_items'],
        'price_per_pax' => floatval($row['price_per_pax']),
        'cost_per_pax' => floatval($row['cost_per_pax']),
    ];
}

echo json_encode($items, JSON_UNESCAPED_UNICODE);
?>
