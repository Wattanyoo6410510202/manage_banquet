<?php
header('Content-Type: application/json');
include "../config.php";

// รับ Action จากทั้ง GET หรือ POST เพื่อความยืดหยุ่น
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- กรณีบันทึกข้อมูล (Save) ---
    if ($action === 'save') {
        $function_id = intval($_POST['function_id'] ?? 0);
        $type = $_POST['type'] ?? 'cost';
        $detail = mysqli_real_escape_string($conn, $_POST['detail'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $t_date = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');

        if ($function_id === 0 || $amount <= 0 || empty($detail)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วนหรือจำนวนเงินไม่ถูกต้อง']);
            exit;
        }

        $sql = "INSERT INTO function_finance (function_id, type, detail, amount, transaction_date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issds", $function_id, $type, $detail, $amount, $t_date);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $conn->error]);
        }
    }

    // --- กรณีลบข้อมูล (Delete) ---
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id > 0) {
            // เช็คก่อนว่ามีข้อมูลไหม (เผื่อโดนลบไปแล้ว)
            $check = $conn->query("SELECT id FROM function_finance WHERE id = $id");
            if ($check->num_rows > 0) {
                if ($conn->query("DELETE FROM function_finance WHERE id = $id")) {
                    echo json_encode(['status' => 'success', 'message' => 'ลบรายการเรียบร้อย']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบข้อมูลได้']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการที่ต้องการลบ']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
        }
    }
    
    // กรณีส่ง action มาไม่ตรง
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Request Method Not Allowed']);
}
exit;