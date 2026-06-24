<?php
session_start();
include "../config.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$user_id = intval($_SESSION['user_id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

// Get the most recent status log entry for this function
$log_res = $conn->query("SELECT old_status FROM function_status_log WHERE function_id = $id ORDER BY id DESC LIMIT 1");
$log_row = $log_res->fetch_assoc();

if (!$log_row) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบประวัติสถานะสำหรับรายการนี้']);
    exit;
}

$old_status = $log_row['old_status'];

// Determine the approve value for the rollback
$approve_val = 0;
if (in_array($old_status, ['Confirmed', 'Approved', 'In Progress', 'Completed'])) {
    $approve_val = 1;
} elseif ($old_status === 'Cancelled') {
    $approve_val = 2;
}

// Log the rollback operation too (so we can rollback again if needed)
$log_sql = "INSERT INTO function_status_log (function_id, old_status, new_status, changed_by, changed_at) 
            VALUES (?, ?, ?, ?, NOW())";
$log_stmt = $conn->prepare($log_sql);
$current_status = '';
$get_current = $conn->query("SELECT status FROM functions WHERE id = $id");
if ($get_current_row = $get_current->fetch_assoc()) {
    $current_status = $get_current_row['status'];
}
$log_stmt->bind_param("issi", $id, $current_status, $old_status, $user_id);
$log_stmt->execute();
$log_stmt->close();

// Update the function status
$sql = "UPDATE functions SET status = ?, approve = ?, modify = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $old_status, $approve_val, $id);

if ($stmt->execute()) {
    // If rolling back from Confirmed/Approved, reset is_approved
    if ($current_status === 'Confirmed' || $current_status === 'Approved') {
        $stmt_upd = $conn->prepare("UPDATE functions SET is_approved = 0 WHERE id = ?");
        $stmt_upd->bind_param("i", $id);
        $stmt_upd->execute();
    }
    // If rolling back to Confirmed/Approved, restore is_approved
    if ($old_status === 'Confirmed' || $old_status === 'Approved') {
        $stmt_upd = $conn->prepare("UPDATE functions SET is_approved = 1 WHERE id = ?");
        $stmt_upd->bind_param("i", $id);
        $stmt_upd->execute();
    }
    // Clear cancel_reason when rolling back from Cancelled
    if ($current_status === 'Cancelled') {
        $stmt_upd = $conn->prepare("UPDATE functions SET cancel_reason = NULL WHERE id = ?");
        $stmt_upd->bind_param("i", $id);
        $stmt_upd->execute();
    }
    echo json_encode(['success' => true, 'old_status' => $old_status]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
