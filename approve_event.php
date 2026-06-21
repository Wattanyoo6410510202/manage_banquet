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
        $p_res = $conn->query("SELECT project_id FROM functions WHERE id = $id");
        if ($p_row = $p_res->fetch_assoc()) {
            $project_id = $p_row['project_id'];
            $conn->query("UPDATE functions SET is_approved = 0 WHERE project_id = $project_id");
            $conn->query("UPDATE functions SET is_approved = 1 WHERE id = $id");
            $conn->query("UPDATE event_projects SET status = 'Approved' WHERE id = $project_id");
        }
        $_SESSION['flash_msg'] = "approved";
    } elseif ($status === 'In Progress') {
        $_SESSION['flash_msg'] = "in_progress";
    } elseif ($status === 'Completed') {
        $_SESSION['flash_msg'] = "completed";
    } elseif ($status === 'Cancelled') {
        $conn->query("UPDATE functions SET cancel_reason = " . ($cancel_reason ? "'" . $conn->real_escape_string($cancel_reason) . "'" : "NULL") . " WHERE id = $id");
        $_SESSION['flash_msg'] = "cancelled";
    }

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะเรียบร้อย']);
    exit();
}

$conn->close();
