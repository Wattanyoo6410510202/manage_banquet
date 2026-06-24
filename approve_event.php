<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include "config.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'Confirmed';
    $cancel_reason = $_POST['cancel_reason'] ?? '';
    $user_id = intval($_SESSION['user_id']);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
        exit();
    }

    $approve_val = ($status === 'Cancelled') ? 2 : 1;

    // --- Log old status before update ---
    $stmt_old = $conn->prepare("SELECT status FROM functions WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_res = $stmt_old->get_result();
    $old_row = $old_res->fetch_assoc();
    $old_status = $old_row['status'] ?? '';

    $log_sql = "INSERT INTO function_status_log (function_id, old_status, new_status, changed_by, changed_at) 
                VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("issi", $id, $old_status, $status, $user_id);
    $log_stmt->execute();
    $log_stmt->close();
    // --- End log ---

    $sql = "UPDATE functions SET 
                approve = ?,
                status = ?,
                modify = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("isi", $approve_val, $status, $id);

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        exit();
    }

    $stmt->close();

    if ($status === 'Confirmed') {
        $stmt_pr = $conn->prepare("SELECT project_id FROM functions WHERE id = ?");
        $stmt_pr->bind_param("i", $id);
        $stmt_pr->execute();
        $p_res = $stmt_pr->get_result();
        if ($p_row = $p_res->fetch_assoc()) {
            $project_id = $p_row['project_id'];
            $stmt_upd = $conn->prepare("UPDATE functions SET is_approved = 0 WHERE project_id = ?");
            $stmt_upd->bind_param("i", $project_id);
            $stmt_upd->execute();
            $stmt_upd = $conn->prepare("UPDATE functions SET is_approved = 1 WHERE id = ?");
            $stmt_upd->bind_param("i", $id);
            $stmt_upd->execute();
            $stmt_upd = $conn->prepare("UPDATE event_projects SET status = 'Approved' WHERE id = ?");
            $stmt_upd->bind_param("i", $project_id);
            $stmt_upd->execute();
        }
        $_SESSION['flash_msg'] = "approved";
    } elseif ($status === 'In Progress') {
        $_SESSION['flash_msg'] = "in_progress";
    } elseif ($status === 'Completed') {
        $_SESSION['flash_msg'] = "completed";
    } elseif ($status === 'Cancelled') {
        $stmt_cancel = $conn->prepare("UPDATE functions SET cancel_reason = ? WHERE id = ?");
        $cancel_val = $cancel_reason ?: null;
        $stmt_cancel->bind_param("si", $cancel_val, $id);
        $stmt_cancel->execute();
        $_SESSION['flash_msg'] = "cancelled";
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย']);
    exit();
}

$conn->close();
