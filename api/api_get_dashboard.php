<?php
include "../config.php";
header('Content-Type: application/json');

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $company_id = isset($_GET['company_id']) ? $_GET['company_id'] : 'all';

    // --- 1. สถิติพื้นฐาน ---
    $where_clause = "";
    if ($company_id !== 'all' && $company_id !== '') {
        $safe_id = $conn->real_escape_string($company_id);
        $where_clause = " WHERE company_id = '$safe_id'";
    }

    $stats_sql = "SELECT 
        COUNT(f.id) as total_events,
        SUM(CASE WHEN f.approve = 0 THEN 1 ELSE 0 END) as pending_count,
        IFNULL(SUM(f.deposit), 0) as total_revenue,
        (
            SELECT AVG( ( (f2.total_amount + IFNULL(inc.total_inc, 0)) - (IFNULL(cst.total_cst, 0) + 0) ) / NULLIF(IFNULL(cst.total_cst, 0) + 0, 0) * 100 )
            FROM functions f2
            LEFT JOIN (SELECT function_id, SUM(amount) as total_inc FROM function_finance WHERE type='income' GROUP BY function_id) inc ON f2.id = inc.function_id
            LEFT JOIN (SELECT function_id, SUM(amount) as total_cst FROM function_finance WHERE type='cost' GROUP BY function_id) cst ON f2.id = cst.function_id
            WHERE f2.approve = 1
        ) as avg_roi
        FROM functions f $where_clause";
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
                 AVG(DATEDIFF(approve_date, created_at)) as avg_days 
                 FROM functions 
                 WHERE approve_date IS NOT NULL 
                 GROUP BY month ORDER BY month ASC LIMIT 6";
    $lead_data = [];
    $l_res = $conn->query($lead_sql);
    while($r = $l_res->fetch_assoc()) { $lead_data[] = $r; }

    // --- ใหม่: Sales Performance (เปรียบเทียบเป้าหมาย) ---
    $current_month = date('n');
    $current_year = date('Y');
    $sales_sql = "SELECT u.name, 
                  (SELECT IFNULL(target_amount, 0) FROM sales_targets WHERE user_id = u.id AND target_month = $current_month AND target_year = $current_year LIMIT 1) as target,
                  (
                    IFNULL((SELECT SUM(total_amount) FROM functions WHERE created_by_id = u.id AND MONTH(event_date) = $current_month AND YEAR(event_date) = $current_year), 0)
                    +
                    IFNULL((SELECT SUM(grand_total) FROM quotations WHERE created_by = u.id AND status = 'Approved' AND MONTH(event_date) = $current_month AND YEAR(event_date) = $current_year), 0)
                  ) as actual
                  FROM users u
                  WHERE u.role = 'Staff'
                  ORDER BY actual DESC";
    $sales_performance = [];
    $s_res = $conn->query($sales_sql);
    while($r = $s_res->fetch_assoc()) { $sales_performance[] = $r; }

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
        "sales" => $sales_performance,
        "rooms" => $rooms
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>