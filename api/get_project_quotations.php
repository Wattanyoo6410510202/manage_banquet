<?php
include "../config.php";

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) {
    echo json_encode(['status' => 'error', 'message' => 'no project_id']);
    exit;
}

// ดึงใบเสนอราคาล่าสุดของ Project นี้
$sql = "SELECT * FROM quotations WHERE project_id = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();

// นับจำนวนใบเสนอราคาใน Project นี้
$count_sql = "SELECT COUNT(*) as cnt FROM quotations WHERE project_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $project_id);
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_assoc();
$version = ($count_row['cnt'] ?? 0) + 1;

// ดึงรายการ items
$items = [];
if ($quote) {
    $item_sql = "SELECT * FROM quotation_items WHERE quote_id = ? ORDER BY id ASC";
    $item_stmt = $conn->prepare($item_sql);
    $item_stmt->bind_param("i", $quote['id']);
    $item_stmt->execute();
    $items_res = $item_stmt->get_result();
    while ($item = $items_res->fetch_assoc()) {
        $items[] = $item;
    }
}

echo json_encode([
    'status' => 'success',
    'version' => $version,
    'quote' => $quote,
    'items' => $items
]);
