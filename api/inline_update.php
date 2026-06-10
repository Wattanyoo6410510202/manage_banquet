<?php
include "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$table = $_POST['table'] ?? '';
$id = intval($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$table || !$id || !$field) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Whitelist allowed tables and fields
$allowed = [
    'functions' => ['lead_source', 'result', 'inspection_date', 'follow_up_date', 'approve_date'],
    'quotations' => ['lead_source', 'result', 'inspection_date', 'follow_up_date', 'approved_at']
];

if (!isset($allowed[$table]) || !in_array($field, $allowed[$table])) {
    echo json_encode(['success' => false, 'message' => 'Invalid table or field']);
    exit;
}

// Sanitize value
if (in_array($field, ['inspection_date', 'follow_up_date', 'approve_date', 'approved_at'])) {
    $value = !empty($value) ? $value : null;
} else {
    $value = trim($value);
}

$sql = "UPDATE $table SET $field = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $value, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
