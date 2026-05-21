<?php
include "../config.php";
header('Content-Type: application/json');

$company_id = isset($_GET['company_id']) ? $_GET['company_id'] : 'all';

// --- 1. สถิติพื้นฐาน ---
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

// --- ใหม่: รายได้ 6 เดือนล่าสุด ---
$revenue_sql = "SELECT DATE_FORMAT(start_time, '%Y-%m') as month, SUM(total_amount) as revenue 
                FROM functions 
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                GROUP BY month ORDER BY month ASC";
$revenue_data = [];
$r_res = $conn->query($revenue_sql);
while($r = $r_res->fetch_assoc()) { $revenue_data[] = $r; }

// --- ใหม่: สัดส่วนประเภทงาน ---
$type_sql = "SELECT t.type_name, COUNT(f.id) as count 
             FROM functions f 
             LEFT JOIN function_types t ON f.function_type_id = t.id 
             GROUP BY t.type_name";
$type_data = [];
$t_res = $conn->query($type_sql);
while($r = $t_res->fetch_assoc()) { $type_data[] = $r; }

// --- ใหม่: งานแบ่งตามโรงแรม (Occupancy) ---
$occ_sql = "SELECT c.company_name, COUNT(f.id) as count 
            FROM functions f 
            LEFT JOIN companies c ON f.company_id = c.id 
            GROUP BY c.company_name";
$occ_data = [];
$o_res = $conn->query($occ_sql);
while($r = $o_res->fetch_assoc()) { $occ_data[] = $r; }

// --- ใหม่: ระยะเวลาอนุมัติเฉลี่ย (Lead Time) 6 เดือน ---
$lead_sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
             AVG(DATEDIFF(approved_at, created_at)) as avg_days 
             FROM functions 
             WHERE approved_at IS NOT NULL 
             GROUP BY month ORDER BY month ASC LIMIT 6";
$lead_data = [];
$l_res = $conn->query($lead_sql);
while($r = $l_res->fetch_assoc()) { $lead_data[] = $r; }

// --- 2. ข้อมูลห้องประชุม ---
$room_sql = "SELECT r.room_name, r.floor, f.function_name, DATE_FORMAT(f.start_time, '%H:%i') as start_t, DATE_FORMAT(f.end_time, '%H:%i') as end_t, c.company_name
        FROM meeting_rooms r
        LEFT JOIN companies c ON r.company_id = c.id
        LEFT JOIN functions f ON r.id = f.room_id AND f.approve = 1 AND (NOW() BETWEEN f.start_time AND f.end_time)
        WHERE r.status = 'active'";
if ($company_id !== 'all' && $company_id !== '') $room_sql .= " AND r.company_id = '$safe_id'";
$room_sql .= " ORDER BY c.company_name ASC, r.floor ASC, r.room_name ASC";

$rooms = [];
$room_res = $conn->query($room_sql);
while ($row = $room_res->fetch_assoc()) { $rooms[] = $row; }

echo json_encode([
    "stats" => $stats,
    "revenue" => $revenue_data,
    "types" => $type_data,
    "occupancy" => $occ_data,
    "lead_time" => $lead_data,
    "rooms" => $rooms
]);