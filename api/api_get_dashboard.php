<?php
include "../config.php";
header('Content-Type: application/json');

$company_id = isset($_GET['company_id']) ? $_GET['company_id'] : 'all';

// --- 1. ดึงสถิติ (Stats) ตามเงื่อนไขโรงแรม ---
$where_clause = "";
if ($company_id !== 'all' && $company_id !== '') {
    $safe_id = $conn->real_escape_string($company_id);
    $where_clause = " WHERE company_id = '$safe_id'";
}

$stats_sql = "SELECT 
    COUNT(id) as total_events,
    SUM(CASE WHEN approve = 0 THEN 1 ELSE 0 END) as pending_count,
    IFNULL(SUM(deposit), 0) as total_revenue 
    FROM functions $where_clause";
$stats = $conn->query($stats_sql)->fetch_assoc();

// --- 2. ดึงข้อมูลห้องประชุม (Rooms) ---
$room_sql = "SELECT 
            r.room_name, r.floor, f.function_name, 
            DATE_FORMAT(f.start_time, '%H:%i') as start_t, 
            DATE_FORMAT(f.end_time, '%H:%i') as end_t,
            c.company_name
        FROM meeting_rooms r
        LEFT JOIN companies c ON r.company_id = c.id
        LEFT JOIN functions f ON r.id = f.room_id 
            AND f.approve = 1 
            AND (NOW() BETWEEN f.start_time AND f.end_time)
        WHERE r.status = 'active'";

if ($company_id !== 'all' && $company_id !== '') {
    $room_sql .= " AND r.company_id = '$safe_id'";
}
$room_sql .= " ORDER BY c.company_name ASC, r.floor ASC, r.room_name ASC";

$rooms_res = $conn->query($room_sql);
$rooms = [];
while ($row = $rooms_res->fetch_assoc()) {
    $rooms[] = $row;
}

// ส่งออกเป็น JSON ทั้งชุด (ทั้ง Stats และ Rooms)
echo json_encode([
    "stats" => $stats,
    "rooms" => $rooms
]);