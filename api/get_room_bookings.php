<?php
ob_start();
include "../config.php";
ob_end_clean();
header('Content-Type: application/json');

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$company_id = intval($_GET['company_id'] ?? 0);

$start_date = !empty($start) ? date('Y-m-d', strtotime($start)) : date('Y-m-01');
$end_date = !empty($end) ? date('Y-m-d', strtotime($end)) : date('Y-m-t');

$rooms_sql = "SELECT r.id, r.room_name, r.company_id, c.company_name, r.cap_theatre, r.cap_banquet, r.total_sqm
    FROM meeting_rooms r
    LEFT JOIN companies c ON r.company_id = c.id
    WHERE r.status = 'active'";
if ($company_id > 0) {
    $rooms_sql .= " AND r.company_id = $company_id";
}
$rooms_sql .= " ORDER BY r.room_name ASC";

$rooms_res = $conn->query($rooms_sql);
$resources = [];
while ($row = $rooms_res->fetch_assoc()) {
    $resources[] = [
        'id' => 'room_' . $row['id'],
        'title' => $row['room_name'],
        'company' => $row['company_name'] ?? '',
        'capacity' => $row['cap_theatre'],
        'sqm' => $row['total_sqm'],
        'company_id' => $row['company_id']
    ];
}

$bookings_sql = "SELECT rb.*, ft.type_name AS function_type_name, ft.prefix
    FROM room_bookings rb
    LEFT JOIN function_types ft ON rb.function_type_id = ft.id
    WHERE rb.status = 'active'
    AND rb.start_time >= '$start_date 00:00:00'
    AND rb.end_time <= '$end_date 23:59:59'";
if ($company_id > 0) {
    $bookings_sql .= " AND rb.company_id = $company_id";
}
$bookings_sql .= " ORDER BY rb.start_time ASC";

$bookings_res = $conn->query($bookings_sql);
$events = [];
while ($row = $bookings_res->fetch_assoc()) {
    $bg_color = '#0d6efd';
    $border_color = '#0a58ca';
    $text_color = '#ffffff';

    $time_label = date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time']));
    $prefix_code = $row['prefix'] ? strtoupper($row['prefix']) : 'RB';

    $events[] = [
        'id' => $row['id'],
        'resourceId' => 'room_' . $row['room_id'],
        'title' => $row['event_name'] ?: 'ไม่ระบุชื่องาน',
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'backgroundColor' => $bg_color,
        'borderColor' => $border_color,
        'textColor' => $text_color,
        'extendedProps' => [
            'booking_id' => $row['id'],
            'booking_code' => $row['booking_code'],
            'booking_name' => $row['booking_name'] ?? '',
            'phone' => $row['phone'] ?? '',
            'organization' => $row['organization'] ?? '',
            'pax' => $row['pax'],
            'status' => $row['status'],
            'remark' => $row['remark'] ?? '',
            'time_label' => $time_label,
            'function_type' => $row['function_type_name'] ?? '',
            'prefix' => $prefix_code,
            'created_by' => $row['created_by'] ?? '',
            'created_at' => $row['created_at']
        ]
    ];
}

echo json_encode([
    'resources' => $resources,
    'events' => $events
]);
?>
