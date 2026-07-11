<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include "config.php";
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

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

    // --- Log (optional — ถ้า table ไม่มีก็ข้าม) ---
    $old_status = '';
    $stmt_old = @$conn->prepare("SELECT status FROM functions WHERE id = ?");
    if ($stmt_old) {
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_res = $stmt_old->get_result();
        if ($old_row = $old_res->fetch_assoc()) {
            $old_status = $old_row['status'] ?? '';
        }
        $stmt_old->close();
    }

    $log_stmt = @$conn->prepare("INSERT INTO function_status_log (function_id, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
    if ($log_stmt) {
        $log_stmt->bind_param("issi", $id, $old_status, $status, $user_id);
        $log_stmt->execute();
        $log_stmt->close();
    }
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
        $stmt_pr = @$conn->prepare("SELECT project_id FROM functions WHERE id = ?");
        if ($stmt_pr) {
            $stmt_pr->bind_param("i", $id);
            $stmt_pr->execute();
            $p_res = $stmt_pr->get_result();
            if ($p_row = $p_res->fetch_assoc()) {
                $project_id = $p_row['project_id'] ?? 0;
                if ($project_id) {
                    $upd1 = @$conn->prepare("UPDATE functions SET is_approved = 0 WHERE project_id = ?");
                    if ($upd1) { $upd1->bind_param("i", $project_id); $upd1->execute(); $upd1->close(); }
                    $upd2 = @$conn->prepare("UPDATE functions SET is_approved = 1 WHERE id = ?");
                    if ($upd2) { $upd2->bind_param("i", $id); $upd2->execute(); $upd2->close(); }
                    $upd3 = @$conn->prepare("UPDATE event_projects SET status = 'Approved' WHERE id = ?");
                    if ($upd3) { $upd3->bind_param("i", $project_id); $upd3->execute(); $upd3->close(); }
                }
            }
            $stmt_pr->close();
        }
        $_SESSION['flash_msg'] = "approved";
    } elseif ($status === 'In Progress') {
        $_SESSION['flash_msg'] = "in_progress";
    } elseif ($status === 'Completed') {
        $_SESSION['flash_msg'] = "completed";
    } elseif ($status === 'Cancelled') {
        if (!empty($cancel_reason)) {
            $stmt_cancel = @$conn->prepare("UPDATE functions SET cancel_reason = ? WHERE id = ?");
            if ($stmt_cancel) {
                $stmt_cancel->bind_param("si", $cancel_reason, $id);
                $stmt_cancel->execute();
                $stmt_cancel->close();
            }
        }
        $_SESSION['flash_msg'] = "cancelled";
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย']);
    exit();
}

$conn->close();
