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

$mt_list = [];
$hk_list = [];
$bk_list = [];

if ($mt_q = $conn->query("SELECT * FROM master_checklist_mt ORDER BY id ASC")) {
    while ($row = $mt_q->fetch_assoc()) $mt_list[] = $row;
}
if ($hk_q = $conn->query("SELECT * FROM master_checklist_hk ORDER BY id ASC")) {
    while ($row = $hk_q->fetch_assoc()) $hk_list[] = $row;
}
if ($bk_q = $conn->query("SELECT * FROM master_checklist_bk ORDER BY id ASC")) {
    while ($row = $bk_q->fetch_assoc()) $bk_list[] = $row;
}

if ($res && $data = $res->fetch_assoc()) {
    echo json_encode([
        'status' => 'success', 
        'data' => $data,
        'mt_list' => $mt_list,
        'hk_list' => $hk_list,
        'bk_list' => $bk_list
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data not found']);
}
