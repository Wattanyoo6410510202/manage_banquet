<?php
session_start();
include "../config.php";

if (!isset($_SESSION['user_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$function_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($function_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$sql = "SELECT banquet_style, equipment, backdrop_detail, hk_florist_detail FROM functions WHERE id = $function_id";
$res = $conn->query($sql);

$mt_checklists = $conn->query("SELECT * FROM master_checklist_mt ORDER BY id ASC");
$hk_checklists = $conn->query("SELECT * FROM master_checklist_hk ORDER BY id ASC");

if ($res && $data = $res->fetch_assoc()) {
    $mt_items = [];
    while($row = $mt_checklists->fetch_assoc()) $mt_items[] = $row;
    
    $hk_items = [];
    while($row = $hk_checklists->fetch_assoc()) $hk_items[] = $row;

    echo json_encode([
        'status' => 'success', 
        'data' => $data,
        'mt_list' => $mt_items,
        'hk_list' => $hk_items
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data not found']);
}
