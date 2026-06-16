<?php
header('Content-Type: application/json');
include "../config.php";

$type = $_GET['type'] ?? '';
$company_id = $_GET['company_id'] ?? 'all';

if (empty($type)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing type']);
    exit;
}

$where = " WHERE 1=1 ";
if ($company_id !== 'all') {
    $where .= " AND f.company_id = " . intval($company_id);
}

$data = [];

switch ($type) {
    case 'total_events':
        $sql = "SELECT f.id, f.function_name, f.event_date, f.total_amount, f.status, c.company_name 
                FROM functions f 
                LEFT JOIN companies c ON f.company_id = c.id 
                $where 
                ORDER BY f.event_date DESC LIMIT 50";
        break;
    case 'pending':
        $sql = "SELECT f.id, f.function_name, f.event_date, f.total_amount, f.status, c.company_name 
                FROM functions f 
                LEFT JOIN companies c ON f.company_id = c.id 
                $where AND f.approve = 0 
                ORDER BY f.event_date DESC";
        break;
    case 'revenue':
        $sql = "SELECT f.id, f.function_name, f.event_date, f.deposit, f.total_amount, c.company_name 
                FROM functions f 
                LEFT JOIN companies c ON f.company_id = c.id 
                $where AND f.deposit > 0 
                ORDER BY f.event_date DESC LIMIT 50";
        break;
    case 'roi':
        // ดึงเฉพาะงานที่อนุมัติแล้วและมี ROI (คำนวณเบื้องต้น)
        $sql = "SELECT f.id, f.function_name, f.event_date, f.total_amount, c.company_name,
                IFNULL(inc.total_inc, 0) as extra_income,
                IFNULL(cst.total_cst, 0) as total_cost
                FROM functions f
                LEFT JOIN companies c ON f.company_id = c.id
                LEFT JOIN (SELECT function_id, SUM(amount) as total_inc FROM function_finance WHERE type='income' GROUP BY function_id) inc ON f.id = inc.function_id
                LEFT JOIN (SELECT function_id, SUM(amount) as total_cst FROM function_finance WHERE type='cost' GROUP BY function_id) cst ON f.id = cst.function_id
                $where AND f.approve = 1
                ORDER BY f.event_date DESC LIMIT 50";
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
        exit;
}

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($type === 'roi') {
            $total_income = floatval($row['total_amount']) + floatval($row['extra_income']);
            $cost = floatval($row['total_cost']);
            $row['roi'] = ($cost > 0) ? (($total_income - $cost) / $cost) * 100 : 0;
        }
        $data[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>