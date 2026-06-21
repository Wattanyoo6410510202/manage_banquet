<?php
include "../config.php";

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$start_time = isset($_GET['start']) ? mysqli_real_escape_string($conn, $_GET['start']) : '';
$end_time = isset($_GET['end']) ? mysqli_real_escape_string($conn, $_GET['end']) : '';
$exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;

if (empty($start_time) || empty($end_time)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing time']);
    exit;
}

$sql = "SELECT f.id, f.function_name, f.start_time, f.end_time, c.cust_name
        FROM functions f
        LEFT JOIN customers c ON f.customer_id = c.id
        WHERE f.room_id = $room_id
        AND f.status != 'Cancelled'
        AND f.id != $exclude_id
        AND ((f.start_time <= '$end_time' AND f.end_time >= '$start_time'))";

$res = $conn->query($sql);
$conflicts = [];
while ($row = $res->fetch_assoc()) {
    $conflicts[] = $row;
}

if (count($conflicts) > 0) {
    echo json_encode(['status' => 'conflict', 'events' => $conflicts]);
} else {
    echo json_encode(['status' => 'available']);
}
