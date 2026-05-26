<?php
session_start();
include "../config.php";

if (!isset($_SESSION['user_name'])) {
    die("Unauthorized");
}

$user_role = strtolower($_SESSION['role'] ?? 'staff');
$current_user = $_SESSION['user_name'] ?? '';
$type = $_GET['type'] ?? 'mt'; // mt, hk, bk

$can_manage = in_array($user_role, ['admin', 'gm', 'manager', 'procurement']);

$where_clause = "";
if ($user_role === 'staff') {
    $safe_user = mysqli_real_escape_string($conn, $current_user);
    $where_clause = " WHERE f.created_by = '$safe_user' ";
}

$sql = "SELECT f.*, c.company_name, c.logo_path,
        (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date 
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id
        $where_clause
        ORDER BY f.modify DESC, f.id DESC";

$q = mysqli_query($conn, $sql);
$functions_data = [];

$status_map = [
    'Confirmed' => ['text' => 'อนุมัติแล้ว', 'class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle'],
    'In Progress' => ['text' => 'ดำเนินการ', 'class' => 'bg-info-subtle text-info', 'icon' => 'bi-play-circle'],
    'Completed' => ['text' => 'จบงานแล้ว', 'class' => 'bg-primary-subtle text-primary', 'icon' => 'bi-flag'],
    'Cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle'],
    'Pending' => ['text' => 'รออนุมัติ', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'bi-clock-history']
];

if ($q && mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
        $formatted_date = date('d M Y', strtotime($display_date));
        $current_status = $row['status'] ?? 'Pending';
        $status_info = $status_map[$current_status] ?? $status_map['Pending'];

        $attachments = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($row['file_attachment' . $i])) {
                $attachments[] = ['path' => $row['file_attachment' . $i]];
            }
        }

        echo '<tr>';
        echo '<td><input type="checkbox" class="row-checkbox form-check-input" value="' . $row['id'] . '"></td>';
        echo '<td>
                <div class="d-flex align-items-center">
                    <div class="me-2 hotel-logo-container">';
        $logo = !empty($row['logo_path']) ? $row['logo_path'] : 'default-logo.png';
        echo '<img src="' . htmlspecialchars($logo) . '" alt="Logo" style="width:30px; height:30px; object-fit:contain;">
                    </div>
                </div>
            </td>';
        echo '<td class="ps-4">
                <div class="fw-bold text-dark text-wrap function-name-link" style="max-width: 400px; cursor: pointer; text-decoration: underline;">
                    ' . htmlspecialchars($row['function_name']) . '
                </div>
                <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> ' . $formatted_date . '</div>
            </td>';
        
        echo '<td><div class="text-dark small text-wrap" style="max-width: 250px;">';
        if ($type === 'mt') {
            echo nl2br(htmlspecialchars($row['equipment'] ?: '-'));
        } elseif ($type === 'hk') {
            echo '<strong>ฉากหลัง:</strong> ' . htmlspecialchars($row['backdrop_detail'] ?: '-') . '<br>';
            echo '<strong>ดอกไม้/ความสะอาด:</strong> ' . htmlspecialchars($row['hk_florist_detail'] ?: '-');
        } else { // bk
            echo nl2br(htmlspecialchars($row['banquet_style'] ?: '-'));
        }
        echo '</div></td>';

        echo '<td>
                <span class="badge ' . $status_info['class'] . ' rounded-pill px-3">
                    <i class="bi ' . $status_info['icon'] . ' me-1"></i>' . $status_info['text'] . '
                </span>
            </td>';
        
        echo '<td><div class="d-flex flex-wrap gap-2">';
        if (empty($attachments)) {
            echo '<span class="text-muted small">-</span>';
        } else {
            foreach ($attachments as $file) {
                echo '<a href="' . htmlspecialchars($file['path']) . '" target="_blank" class="text-danger" style="font-size: 1.2rem; line-height: 1;"><i class="bi bi-file-earmark-pdf-fill"></i></a>';
            }
        }
        echo '</div></td>';

        echo '<td class="text-center sticky-col">
                <div class="d-flex justify-content-center gap-1">';
        
        if ($user_role !== 'viewer' && !in_array($user_role, ['technician', 'housekeeping', 'procurement'])) {
            if ($row['approve'] == 0 && in_array($user_role, ['admin', 'gm'])) {
                echo '<button type="button" class="btn btn-sm btn-success btn-approve-row" data-id="' . $row['id'] . '"><i class="bi bi-check-lg"></i> อนุมัติ</button>';
            }
            if ($row['approve'] == 1 && $row['status'] == 'Confirmed') {
                echo '<button type="button" class="btn btn-sm btn-info text-white btn-status-change" data-id="' . $row['id'] . '" data-status="In Progress"><i class="bi bi-play-fill"></i> ดำเนินการ</button>';
            }
            if ($row['status'] == 'In Progress') {
                echo '<button type="button" class="btn btn-sm btn-primary btn-status-change" data-id="' . $row['id'] . '" data-status="Completed"><i class="bi bi-flag-fill"></i> จบงาน</button>';
            }
            if (!in_array($row['status'], ['Completed', 'Cancelled'])) {
                echo '<button type="button" class="btn btn-sm btn-outline-danger btn-status-change" data-id="' . $row['id'] . '" data-status="Cancelled"><i class="bi bi-x-lg"></i> ยกเลิก</button>';
            }
        }
        
        echo '<div class="vr mx-1"></div>
              <a href="view.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary" title="พิมพ์/ดูรายละเอียด"><i class="bi bi-printer"></i></a>';
        
        if (!in_array($user_role, ['technician', 'housekeeping'])) {
            echo '<a href="finance.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-warning" title="จัดการบัญชี/ROI"><i class="bi bi-cash-coin"></i></a>';
            if ($user_role !== 'viewer' && $can_manage && $row['status'] != 'Completed' && ($row['approve'] == 0 || $user_role === 'procurement')) {
                echo '<a href="edit.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>';
                if ($user_role !== 'procurement') {
                    echo '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" data-id="' . $row['id'] . '"><i class="bi bi-trash"></i></button>';
                }
            }
        }
        
        echo '</div></td>';
        echo '</tr>';
    }
}
?>
