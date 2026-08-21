<?php
session_start();
include "../config.php";

if (!isset($_SESSION['user_name'])) {
    die("Unauthorized");
}

$user_role = strtolower($_SESSION['role'] ?? 'staff');
$current_user = $_SESSION['user_name'] ?? '';
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$type = $_GET['type'] ?? 'mt'; // mt, hk, bk

$can_manage = in_array($user_role, ['admin', 'gm', 'manager', 'procurement']);

$where_clause = "";
// ปรับปรุง: ให้ Staff เห็นงานได้ทุกงานเหมือน Admin/GM ตามคำขอ
if (in_array($user_role, ['admin', 'gm', 'staff', 'manager', 'procurement'])) {
    $where_clause = ""; 
} else {
    $where_clause = ""; 
}

// จำกัดช่วงวันที่จัดงาน (-1 เดือน ~ +6 เดือน) เพื่อไม่ให้สแกนทั้งตารางทุกครั้ง
// งานที่ยังไม่มี start_time (ร่างที่ยังไม่ล็อกวัน) ยังแสดงตลอด
$_date_bound = "(f.start_time IS NULL OR (f.start_time >= '" . date('Y-m-d', strtotime('-1 month')) . "' AND f.start_time < '" . date('Y-m-d', strtotime('+7 months')) . "'))";
$where_clause .= ($where_clause === "" ? " WHERE " : " AND ") . "$_date_bound ";

$sql = "SELECT f.*, c.company_name, c.logo_path, p.project_name as main_project_name,
        (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date,
        (SELECT GROUP_CONCAT(CONCAT(id, ':', quote_no, ':', is_selected) SEPARATOR '|') FROM quotations WHERE project_id = f.project_id) as all_quotes
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id
        LEFT JOIN event_projects p ON f.project_id = p.id
        $where_clause
        ORDER BY f.modify DESC, f.id DESC";

$q = mysqli_query($conn, $sql);
$projects_data = [];

if ($q && mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
        $row['formatted_date'] = date('d M Y', strtotime($display_date));

        $status_map = [
            'Confirmed' => ['text' => 'อนุมัติแล้ว', 'class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle'],
            'In Progress' => ['text' => 'ดำเนินการ', 'class' => 'bg-info-subtle text-info', 'icon' => 'bi-play-circle'],
            'Completed' => ['text' => 'จบงานแล้ว', 'class' => 'bg-primary-subtle text-primary', 'icon' => 'bi-flag'],
            'Cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle'],
            'Pending' => ['text' => 'รออนุมัติ', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'bi-clock-history']
        ];

        $current_status = $row['status'] ?? 'Pending';
        $row['status_info'] = $status_map[$current_status] ?? $status_map['Pending'];

        $row['attachments'] = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($row['file_attachment' . $i])) {
                $row['attachments'][] = ['path' => $row['file_attachment' . $i]];
            }
        }

        $pid = $row['project_id'] ?: 'single_' . $row['id'];
        if (!isset($projects_data[$pid])) {
            $projects_data[$pid] = [
                'project_name' => $row['main_project_name'] ?: $row['function_name'],
                'company_name' => $row['company_name'],
                'logo_path' => $row['logo_path'],
                'booking_name' => $row['booking_name'],
                'phone' => $row['phone'],
                'drafts' => []
            ];
        }
        $projects_data[$pid]['drafts'][] = $row;
    }
}

if ($type === 'mt' || $type === 'hk' || $type === 'bk') {
    // แผนกช่าง, จัดเลี้ยง, แม่บ้าน (7 คอลัมน์)
    foreach ($projects_data as $pid => $project) {
        foreach ($project['drafts'] as $row) {
            echo '<tr>';
            echo '<td><input type="checkbox" class="row-checkbox form-check-input" value="' . $row['id'] . '"></td>';
            
            echo '<td><div class="d-flex align-items-center"><div class="me-2 hotel-logo-container">';
            $logo = !empty($row['logo_path']) ? $row['logo_path'] : 'assets/img/default-company.png';
            echo '<img src="' . htmlspecialchars($logo) . '" style="width:30px; height:30px; object-fit:contain;"></div></div></td>';
            
            echo '<td class="ps-4"><div class="fw-bold text-dark text-wrap function-name-link" style="max-width: 400px; cursor: pointer; text-decoration: underline;">' . htmlspecialchars($row['function_name']) . '</div>';
            if (!empty($row['quote_no'])) {
                echo '<div class="text-info small"><i class="bi bi-file-earmark-text me-1"></i> Quote: ' . htmlspecialchars($row['quote_no']) . '</div>';
            }
            echo '<div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> ' . $row['formatted_date'] . '</div></td>';
            
            echo '<td><div class="text-dark small text-wrap" style="max-width: 250px;">';
            if ($type === 'mt') {
                echo nl2br(htmlspecialchars($row['equipment'] ?: '-'));
            } elseif ($type === 'bk') {
                echo nl2br(htmlspecialchars($row['banquet_style'] ?: '-'));
            } elseif ($type === 'hk') {
                echo '<strong>ฉากหลัง:</strong> ' . htmlspecialchars($row['backdrop_detail'] ?: '-') . '<br>';
                echo '<strong>ดอกไม้/ความสะอาด:</strong> ' . htmlspecialchars($row['hk_florist_detail'] ?: '-');
            }
            echo '</div></td>';
            
            $cancelTip = (!empty($row['cancel_reason']) && $row['status'] === 'Cancelled') ? ' title="' . htmlspecialchars($row['cancel_reason']) . '" style="cursor:help;"' : '';
            echo '<td><span class="badge ' . $row['status_info']['class'] . ' rounded-pill px-3"' . $cancelTip . '><i class="bi ' . $row['status_info']['icon'] . ' me-1"></i>' . $row['status_info']['text'] . '</span></td>';
            
            echo '<td><div class="d-flex flex-wrap gap-2">';
            if (empty($row['attachments'])) {
                echo '<span class="text-muted small">-</span>';
            } else {
                foreach ($row['attachments'] as $file) {
                    echo '<a href="' . htmlspecialchars($file['path']) . '" target="_blank" class="text-danger" style="font-size: 1.2rem; line-height: 1;"><i class="bi bi-file-earmark-pdf-fill"></i></a>';
                }
            }
            echo '</div></td>';
            
            echo '<td class="text-center sticky-col"><div class="d-flex justify-content-center gap-1">';
            echo '<a href="view.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary" title="พิมพ์/ดูรายละเอียด"><i class="bi bi-printer"></i></a>';
            echo '<a href="finance.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-warning" title="จัดการบัญชี/ROI"><i class="bi bi-cash-coin"></i></a>';
            echo '</div></td></tr>';
        }
    }
} else {
    // แบบ Project/Draft (สำหรับ manage_banquet.php - 10 คอลัมน์)
    foreach ($projects_data as $pid => $project) {
        $drafts = $project['drafts'];
        $master = null;
        $is_project_approved = false;
        foreach ($drafts as $d) {
            if ($d['is_approved'] == 1) { 
                $master = $d; 
                $is_project_approved = true;
                break; 
            }
        }
        if (!$master) $master = $drafts[0];
        $has_drafts = count($drafts) > 1;

        // --- Main Row ---
        echo '<tr class="project-header-row ' . ($has_drafts ? 'has-sub' : '') . '" data-pid="' . $pid . '">';
        echo '<td class="ps-4">';
        if($has_drafts) echo '<i class="bi bi-plus-square text-gold toggle-drafts me-2" style="cursor: pointer;"></i>';
        echo '<input type="checkbox" class="row-checkbox form-check-input" value="' . $master['id'] . '"></td>';
        
        echo '<td><div class="hotel-logo-container">';
        $logo = !empty($project['logo_path']) ? $project['logo_path'] : 'assets/img/default-company.png';
        echo '<img src="' . htmlspecialchars($logo) . '" style="width:30px; height:30px; object-fit:contain;"></div></td>';
        
        echo '<td><div class="fw-bold text-dark project-title-link">' . htmlspecialchars($project['project_name']);
        if($has_drafts) echo ' <span class="badge bg-gold text-white rounded-pill ms-1" style="font-size: 0.65rem;">' . count($drafts) . ' Versions</span>';
        echo '</div>';
        if (!empty($master['quote_no'])) {
            echo '<div class="text-info small"><i class="bi bi-file-earmark-text me-1"></i> Quote: ' . htmlspecialchars($master['quote_no']) . '</div>';
        }
        echo '<div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> ' . $master['formatted_date'] . '</div></td>';
        
        echo '<td><div class="text-dark fw-medium small mb-1">' . htmlspecialchars($project['booking_name']) . '</div>';
        echo '<div class="text-muted small"><i class="bi bi-telephone me-1"></i>' . htmlspecialchars($project['phone']) . '</div></td>';
        
        echo '<td><div class="text-primary fw-bold">฿' . number_format($master['total_amount'] ?: 0, 2) . '</div>';
        echo '<small class="text-muted" style="font-size: 0.7rem;">Draft: ' . htmlspecialchars($master['draft_name']) . '</small></td>';
        
        echo '<td><span class="badge bg-light text-dark border fw-normal small">#' . htmlspecialchars($master['function_code']) . '</span></td>';
        
        echo '<td><span class="badge ' . $master['status_info']['class'] . ' rounded-pill px-3"><i class="bi ' . $master['status_info']['icon'] . ' me-1"></i>' . $master['status_info']['text'] . '</span></td>';
        
        echo '<td><div class="text-dark small fw-medium">' . htmlspecialchars($master['created_by'] ?: '-') . '</div><small class="text-muted" style="font-size: 0.7rem;">Master Version</small></td>';
        
        echo '<td><div class="d-flex flex-wrap gap-1">';
        foreach ($master['attachments'] as $file) {
            echo '<a href="' . htmlspecialchars($file['path']) . '" target="_blank" class="text-danger fs-5"><i class="bi bi-file-earmark-pdf-fill"></i></a>';
        }
        echo '</div></td>';
        
        echo '<td class="text-center sticky-col"><div class="d-flex justify-content-center gap-1">';
        
        if ($master['approve'] == 0 && in_array($user_role, ['admin', 'gm'])) {
            echo '<button type="button" class="btn btn-sm btn-success btn-approve-draft" data-id="' . $master['id'] . '"><i class="bi bi-check-lg"></i> อนุมัติ</button>';
        }

        echo '<a href="view.php?id=' . $master['id'] . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>';
        
        if (in_array($user_role, ['admin', 'gm', 'staff', 'manager', 'procurement'])) {
            echo '<a href="finance.php?id=' . $master['id'] . '" class="btn btn-sm btn-outline-warning" title="จัดการบัญชี/ROI"><i class="bi bi-cash-coin"></i></a>';
        }

        echo '<a href="edit.php?id=' . $master['id'] . '" class="btn btn-sm btn-outline-dark" title="แก้ไขงานหลัก"><i class="bi bi-pencil-square"></i></a>';
        echo '</div></td></tr>';

        // --- Draft Sub-rows ---
        foreach ($drafts as $row) {
            echo '<tr class="draft-sub-row bg-light" data-parent-pid="' . $pid . '" style="display: none; border-left: 4px solid var(--hotel-gold-solid);">';
            echo '<td class="ps-5 text-center"><i class="bi bi-arrow-return-right text-muted"></i></td>';
            echo '<td></td>';
            echo '<td><div class="fw-medium text-secondary small function-name-link">' . htmlspecialchars($row['draft_name']);
            if($row['is_approved']) echo ' <span class="badge bg-success-subtle text-success ms-1" style="font-size: 0.6rem;">Master</span>';
            echo '</div>';
            
            // แสดงรายการใบเสนอราคาที่เกี่ยวข้อง
            if (!empty($row['all_quotes'])) {
                echo '<div class="mt-1 d-flex flex-wrap gap-1">';
                $quotes_list = explode('|', $row['all_quotes']);
                foreach ($quotes_list as $q_str) {
                    list($q_id, $q_no, $q_sel) = explode(':', $q_str);
                    $q_class = ($q_sel == 1) ? 'bg-primary text-white' : 'bg-light text-muted border';
                    $q_icon = ($q_sel == 1) ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-file-earmark-text me-1"></i>';
                    echo '<a href="quotation_view.php?id=' . $q_id . '" target="_blank" class="badge ' . $q_class . ' text-decoration-none" style="font-size: 0.6rem;">' . $q_icon . htmlspecialchars($q_no) . '</a>';
                }
                echo '</div>';
            }

            echo '<small class="text-muted d-block mt-1">' . htmlspecialchars($row['function_name']) . '</small></td>';
            echo '<td><small class="text-muted">' . htmlspecialchars($row['booking_name']) . '</small></td>';
            echo '<td><div class="small fw-bold">฿' . number_format($row['total_amount'] ?: 0, 2) . '</div></td>';
            echo '<td><span class="badge bg-white text-dark border small fw-normal">#' . htmlspecialchars($row['function_code']) . '</span></td>';
            echo '<td><span class="badge ' . $row['status_info']['class'] . ' opacity-75 rounded-pill px-2 py-1" style="font-size: 0.7rem;">' . $row['status_info']['text'] . '</span></td>';
            echo '<td><small class="text-muted" style="font-size: 0.7rem;">' . date('d/m/y H:i', strtotime($row['modify'])) . '</small></td>';
            echo '<td></td>';
            echo '<td class="text-center"><div class="btn-group">';
            if (!$is_project_approved && in_array($user_role, ['admin', 'gm'])) {
                echo '<button type="button" class="btn btn-xs btn-success btn-approve-draft py-0 px-2" data-id="' . $row['id'] . '"><i class="bi bi-check-lg small"></i> อนุมัติ</button>';
            }
            echo '<a href="edit.php?id=' . $row['id'] . '" class="btn btn-xs btn-outline-secondary py-0 px-2"><i class="bi bi-pencil small"></i></a>';
            if ($user_role === 'admin' || intval($row['created_by_id']) === $current_user_id) {
                echo '<button type="button" class="btn btn-xs btn-outline-danger btn-delete-row py-0 px-2" data-id="' . $row['id'] . '"><i class="bi bi-trash small"></i></button>';
            }
            echo '</div></td></tr>';
        }
    }
}
?>
