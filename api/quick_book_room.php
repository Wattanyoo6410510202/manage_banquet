<?php
ob_start();
include "../config.php";
ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'check_conflict') {
    $room_id = intval($_POST['room_id'] ?? 0);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time'] ?? '');
    $exclude_id = intval($_POST['exclude_id'] ?? 0);

    if (!$room_id || !$start_time || !$end_time) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    $sql = "SELECT id, event_name, start_time, end_time, booking_name
        FROM room_bookings
        WHERE room_id = $room_id
        AND status = 'active'
        AND id != $exclude_id
        AND ((start_time <= '$end_time' AND end_time >= '$start_time'))";

    $res = $conn->query($sql);
    $conflicts = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $conflicts[] = $row;
        }
    }

    if (count($conflicts) > 0) {
        echo json_encode(['status' => 'conflict', 'events' => $conflicts]);
    } else {
        echo json_encode(['status' => 'available']);
    }
    exit;
}

if ($action === 'save') {
    try {
        $current_role = strtolower($_SESSION['role'] ?? '');
        if (!in_array($current_role, ['admin', 'staff', 'gm', 'sale'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล']);
            exit;
        }

        $room_id = intval($_POST['room_id'] ?? 0);
        $company_id = intval($_POST['company_id'] ?? 0);
        $function_type_id = intval($_POST['function_type_id'] ?? 1);
        $event_name = mysqli_real_escape_string($conn, $_POST['event_name'] ?? '');
        $booking_name = mysqli_real_escape_string($conn, $_POST['booking_name'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $organization = mysqli_real_escape_string($conn, $_POST['organization'] ?? '');
        $pax = intval($_POST['pax'] ?? 0);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time'] ?? '');
        $remark = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');

        if (!$room_id || !$start_time || !$end_time || !$event_name) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน']);
            exit;
        }

        $check_sql = "SELECT id FROM room_bookings
            WHERE room_id = $room_id
            AND status = 'active'
            AND (start_time <= '$end_time' AND end_time >= '$start_time')";
        $check_res = $conn->query($check_sql);
        if ($check_res && $check_res->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ห้องนี้ถูกจองในช่วงเวลาที่เลือกแล้ว']);
            exit;
        }

        $created_by = mysqli_real_escape_string($conn, $_SESSION['user'] ?? $_SESSION['user_name'] ?? 'Unknown');
        $created_by_id = intval($_SESSION['user_id'] ?? 0);

        $ft_result = $conn->query("SELECT prefix FROM function_types WHERE id = $function_type_id");
        $prefix = 'RB';
        if ($ft_row = $ft_result->fetch_assoc()) {
            $prefix = strtoupper(mysqli_real_escape_string($conn, $ft_row['prefix']));
        }
        $date_str = date('dm', strtotime($_POST['start_time'] ?? 'now'));
        $seq_query = "SELECT COUNT(*) AS cnt FROM room_bookings WHERE booking_code LIKE '{$prefix}{$date_str}%'";
        $seq_result = $conn->query($seq_query);
        $seq_row = $seq_result->fetch_assoc();
        $seq = intval($seq_row['cnt'] ?? 0) + 1;
        $booking_code = $prefix . $date_str . str_pad($seq, 3, '0', STR_PAD_LEFT);

        $sql_insert = "INSERT INTO room_bookings
            (room_id, company_id, function_type_id, booking_code, event_name,
            booking_name, phone, organization, pax, start_time, end_time,
            remark, created_by, created_by_id)
            VALUES ($room_id, $company_id, $function_type_id, '$booking_code', '$event_name',
            '$booking_name', '$phone', '$organization', $pax, '$start_time', '$end_time',
            '$remark', '$created_by', $created_by_id)";

        if ($conn->query($sql_insert)) {
            $last_id = $conn->insert_id;
            echo json_encode([
                'status' => 'success',
                'booking_id' => $last_id,
                'booking_code' => $booking_code,
                'message' => 'บันทึกการจองห้องสำเร็จ'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
        }
    } catch (Throwable $e) {
        error_log("[quick_book_room] ERROR: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    try {
        $current_role = strtolower($_SESSION['role'] ?? '');
        if (!in_array($current_role, ['admin', 'gm'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบข้อมูล']);
            exit;
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        if ($booking_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการ']);
            exit;
        }

        $conn->query("UPDATE room_bookings SET status = 'cancelled' WHERE id = $booking_id");

        echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการจองสำเร็จ']);
    } catch (Throwable $e) {
        error_log("[quick_book_room delete] ERROR: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
?>
