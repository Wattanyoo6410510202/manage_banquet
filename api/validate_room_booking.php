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

$sql = "SELECT COUNT(*) as conflict_count FROM functions 
        WHERE room_id = $room_id 
        AND approve = 1 
        AND id != $exclude_id 
        AND (('$start_time' BETWEEN start_time AND end_time) 
             OR ('$end_time' BETWEEN start_time AND end_time)
             OR (start_time BETWEEN '$start_time' AND '$end_time'))";

$res = $conn->query($sql);
$row = $res->fetch_assoc();

if ($row['conflict_count'] > 0) {
    echo json_encode(['status' => 'conflict']);
} else {
    echo json_encode(['status' => 'available']);
}
