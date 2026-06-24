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
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');
        $t_date = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');
        
        // รับค่าจาก session เพื่อบันทึกผู้สร้าง
        $created_by_role = $_SESSION['role'] ?? 'unknown';
        $created_by_name = $_SESSION['user_name'] ?? 'unknown';

        if ($function_id === 0 || $amount <= 0 || empty($detail)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วนหรือจำนวนเงินไม่ถูกต้อง']);
            exit;
        }

        // Check if function is already approved (for post-approval flag)
        $is_post = 0;
        $stmt_cf = $conn->prepare("SELECT approve FROM functions WHERE id = ?");
        $stmt_cf->bind_param("i", $function_id);
        $stmt_cf->execute();
        $res_cf = $stmt_cf->get_result();
        if ($cf = $res_cf->fetch_assoc()) {
            if ($cf['approve'] == 1) $is_post = 1;
        }

        $sql = "INSERT INTO function_finance (function_id, type, detail, amount, payment_method, transaction_date, created_by_role, created_by_name, is_post_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdssssi", $function_id, $type, $detail, $amount, $payment_method, $t_date, $created_by_role, $created_by_name, $is_post);
        
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
            $stmt_chk = $conn->prepare("SELECT id FROM function_finance WHERE id = ?");
            $stmt_chk->bind_param("i", $id);
            $stmt_chk->execute();
            $res_chk = $stmt_chk->get_result();
            if ($res_chk->num_rows > 0) {
                $stmt_del = $conn->prepare("DELETE FROM function_finance WHERE id = ?");
                $stmt_del->bind_param("i", $id);
                if ($stmt_del->execute()) {
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