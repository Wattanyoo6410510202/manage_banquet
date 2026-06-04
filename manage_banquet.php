<?php
include "config.php";
include "header.php";

// ฟังก์ชันจัดรูปแบบเบอร์โทรศัพท์
function formatPhoneNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 4);
    }
    return $phone;
}
?>
<?php
$user_role = strtolower($_SESSION['role'] ?? '');

$can_manage = in_array($user_role, ['admin', 'staff', 'gm']);

// 1. ตรวจสอบ Role และ User
$user_role = strtolower($_SESSION['role'] ?? 'staff');
$current_user = $_SESSION['user_name'] ?? '';
$can_manage = in_array($user_role, ['admin', 'gm', 'manager', 'procurement', 'staff']); // กำหนดสิทธิ์จัดการ

// 2. เตรียม WHERE Clause
$where_clause = "";
// ปรับปรุง: ให้ Staff เห็นงานได้ทุกงานเหมือน Admin/GM ตามคำขอ
if (in_array($user_role, ['admin', 'gm', 'staff', 'manager', 'procurement'])) {
    $where_clause = ""; // เห็นทั้งหมด
} else {
    // สำหรับ Role อื่นๆ (ถ้ามี) ให้เห็นเฉพาะที่เกี่ยวข้องหรือว่างเปล่า
    $where_clause = ""; 
}

// 3. SQL Query
$sql = "SELECT f.*, c.company_name, c.logo_path, p.project_name as main_project_name,
        (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date 
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id
        LEFT JOIN event_projects p ON f.project_id = p.id
        $where_clause
        ORDER BY f.modify DESC, f.id DESC";

$q = mysqli_query($conn, $sql);
$projects_data = [];
$functions_data = [];

// 4. วนลูปเก็บข้อมูลลง Array และจัดการกลุ่ม Draft
if ($q && mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        // จัดการเรื่องวันที่
        $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
        $row['formatted_date'] = date('d M Y', strtotime($display_date));

        // จัดการสถานะ (Status Mapping)
        $status_map = [
            'Confirmed' => ['text' => 'อนุมัติแล้ว', 'class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle'],
            'In Progress' => ['text' => 'ดำเนินการ', 'class' => 'bg-info-subtle text-info', 'icon' => 'bi-play-circle'],
            'Completed' => ['text' => 'จบงานแล้ว', 'class' => 'bg-primary-subtle text-primary', 'icon' => 'bi-flag'],
            'Cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle'],
            'Pending' => ['text' => 'รออนุมัติ', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'bi-clock-history']
        ];

        $current_status = $row['status'] ?? 'Pending';
        $row['status_info'] = $status_map[$current_status] ?? $status_map['Pending'];

        // จัดการไฟล์แนบ
        $row['attachments'] = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($row['file_attachment' . $i])) {
                $row['attachments'][] = ['path' => $row['file_attachment' . $i]];
            }
        }

        // เก็บข้อมูลดิบไว้สำหรับ mobile card
        $functions_data[] = $row;

        // จัดกลุ่มตาม Project
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
?>
<div id="alert-container">
    <?php include "assets/alert.php"; ?>
</div>


<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.bootstrap5.min.css">

<div class="row mb-4 align-items-center">
    <div class="col">
        <h4 class="fw-bold text-dark mb-0">
            <i class="bi bi-journal-text me-2 text-gold"></i> Banquet Event List
        </h4>
        <p class="text-muted small mb-0">จัดการรายการจัดเลี้ยงทั้งหมดในระบบ</p>
    </div>
    <div class="col-auto">
        <div class="d-flex align-items-center justify-content-between bg-white border rounded p-1 px-2 ">

            <div class="d-flex align-items-center gap-1">
                <div class="btn-group border-end pe-2 me-1">
                    <button type="button" id="btnExportExcel"
                        class="btn btn-link btn-sm text-success text-decoration-none p-1">
                        <i class="bi bi-file-earmark-excel fs-5"></i>
                        <span class="d-none d-md-inline small ms-1">Excel</span>
                    </button>
                    <button type="button" id="btnExportPrint"
                        class="btn btn-link btn-sm text-secondary text-decoration-none p-1">
                        <i class="bi bi-printer fs-5"></i>
                        <span class="d-none d-md-inline small ms-1">พิมพ์</span>
                    </button>
                </div>

                <?php
                if (strtolower($user_role) === 'admin'):
                    ?>
                    <button id="deleteSelected" class="btn btn-danger btn-sm py-1 px-2 shadow-sm"
                        style="display:none; font-size: 0.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                        <span class="ms-1">ลบ (<span id="selectCount">0</span>)</span>
                    </button>
                    <?php
                else:
                    ?>
                    <button class="btn btn-secondary btn-sm py-1 px-2 shadow-sm disabled"
                        style="font-size: 0.75rem; cursor: not-allowed;">
                        <i class="bi bi-eye-fill"></i>
                        <span class="ms-1">โหมดดูข้อมูลเท่านั้น</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="calendar.php" class="btn btn-outline-hotel btn-sm border-0 py-1">
                    <i class="bi bi-calendar3"></i>
                    <span class="d-none d-sm-inline ms-1 small">ปฏิทิน</span>
                </a>
                <?php
                $allowed_roles = ['admin', 'staff', 'gm', 'viewer'];
                if (in_array($user_role, $allowed_roles)):
                    ?>
                    <a href="add_event.php" class="btn btn-dark btn-sm px-3 py-1 rounded-pill shadow-sm"
                        style="font-size: 0.8rem;">
                        <i class="bi bi-plus-lg"></i>
                        <span class="ms-1">เพิ่มงานใหม่</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <!-- ตารางปกติ (สำหรับจอ Desktop) -->
        <div class="table-responsive d-none d-md-block">
            <table id="banquetTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>โรงแรม</th>
                        <th class="ps-4">ชื่องาน (Function)</th>
                        <th>ผู้จอง</th>
                        <th>มูลค่า</th>
                        <th>เลขที่</th>
                        <th>สถานะ</th>
                        <th>Sales</th>
                        <th>ไฟล์</th>
                        <th class="text-center bg-light">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects_data as $pid => $project): 
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
                    ?>
                        <!-- Main Project Row -->
                        <tr class="project-header-row <?= $has_drafts ? 'has-sub' : '' ?>" data-pid="<?= $pid ?>">
                            <td class="ps-4">
                                <?php if($has_drafts): ?>
                                    <i class="bi bi-plus-square text-gold toggle-drafts me-2" style="cursor: pointer;"></i>
                                <?php endif; ?>
                                <input type="checkbox" class="row-checkbox form-check-input" value="<?= $master['id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-2 hotel-logo-container">
                                        <?php $logo = !empty($project['logo_path']) ? $project['logo_path'] : 'assets/img/default-company.png'; ?>
                                        <img src="<?= htmlspecialchars($logo); ?>" alt="Logo" style="width:30px; height:30px; object-fit:contain;">
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark text-wrap project-title-link" style="max-width: 400px; cursor: pointer;" data-id="<?= $master['id'] ?>">
                                    <?= htmlspecialchars($project['project_name']); ?>
                                    <?php if($has_drafts): ?>
                                        <span class="badge bg-gold text-white rounded-pill ms-1" style="font-size: 0.65rem;"><?= count($drafts) ?> Versions</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> <?= $master['formatted_date']; ?></div>
                            </td>
                            <td>
                                <div class="text-dark fw-medium small mb-1"><?= htmlspecialchars($project['booking_name']); ?></div>
                                <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars(formatPhoneNumber($project['phone'])); ?></div>
                            </td>
                            <td>
                                <div class="text-primary fw-bold">฿<?= number_format($master['total_amount'] ?: 0, 2); ?></div>
                                <small class="text-muted" style="font-size: 0.7rem;">Draft: <?= htmlspecialchars($master['draft_name']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal small">#<?= htmlspecialchars($master['function_code']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $master['status_info']['class']; ?> rounded-pill px-3">
                                    <i class="bi <?= $master['status_info']['icon']; ?> me-1"></i><?= $master['status_info']['text']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium"><?= htmlspecialchars($master['created_by'] ?: '-'); ?></div>
                                <small class="text-muted" style="font-size: 0.7rem;">Master Version</small>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($master['attachments'] as $file): ?>
                                        <a href="<?= htmlspecialchars($file['path']); ?>" target="_blank" class="text-danger fs-5"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-center sticky-col">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($master['approve'] == 0 && in_array($user_role, ['admin', 'gm'])): ?>
                                        <button type="button" class="btn btn-sm btn-success btn-approve-draft" data-id="<?= $master['id']; ?>">
                                            <i class="bi bi-check-lg"></i> อนุมัติ
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="view.php?id=<?= $master['id']; ?>" class="btn btn-sm btn-outline-primary" title="ดูรายละเอียด"><i class="bi bi-eye"></i></a>
                                    
                                    <?php if (in_array($user_role, ['admin', 'gm', 'staff', 'manager', 'procurement'])): ?>
                                        <a href="finance.php?id=<?= $master['id']; ?>" class="btn btn-sm btn-outline-warning" title="จัดการบัญชี/ROI">
                                            <i class="bi bi-cash-coin"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="edit.php?id=<?= $master['id']; ?>" class="btn btn-sm btn-outline-dark" title="แก้ไขงานหลัก"><i class="bi bi-pencil-square"></i></a>
                                </div>
                            </td>
                        </tr>

                        <!-- Draft Rows (Sub-items) -->
                        <?php foreach ($drafts as $row): ?>
                            <tr class="draft-sub-row bg-light" data-parent-pid="<?= $pid ?>" style="display: none; border-left: 4px solid var(--hotel-gold-solid);">
                                <td class="ps-5 text-center"><i class="bi bi-arrow-return-right text-muted"></i></td>
                                <td></td>
                                <td>
                                    <div class="fw-medium text-secondary small function-name-link" style="cursor: pointer;">
                                        <?= htmlspecialchars($row['draft_name']); ?>
                                        <?php if($row['is_approved']): ?>
                                            <span class="badge bg-success-subtle text-success ms-1" style="font-size: 0.6rem;">Master</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($row['function_name']); ?></small>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($row['booking_name']); ?></small></td>
                                <td><div class="small fw-bold">฿<?= number_format($row['total_amount'] ?: 0, 2); ?></div></td>
                                <td><span class="badge bg-white text-dark border small fw-normal">#<?= htmlspecialchars($row['function_code']); ?></span></td>
                                <td>
                                    <span class="badge <?= $row['status_info']['class']; ?> opacity-75 rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                        <?= $row['status_info']['text']; ?>
                                    </span>
                                </td>
                                <td><small class="text-muted" style="font-size: 0.7rem;"><?= date('d/m/y H:i', strtotime($row['modify'])); ?></small></td>
                                <td></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if (!$is_project_approved && in_array($user_role, ['admin', 'gm'])): ?>
                                            <button type="button" class="btn btn-xs btn-success btn-approve-draft py-0 px-2" data-id="<?= $row['id']; ?>" title="เลือกดราฟนี้เป็นหลัก">
                                                <i class="bi bi-check-lg small"></i> อนุมัติ
                                            </button>
                                        <?php endif; ?>
                                        <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-xs btn-outline-secondary py-0 px-2" title="แก้ไขดราฟ"><i class="bi bi-pencil small"></i></a>
                                        <button type="button" class="btn btn-xs btn-outline-danger btn-delete-row py-0 px-2" data-id="<?= $row['id']; ?>"><i class="bi bi-trash small"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- รายการแบบ Card (สำหรับมือถือ) -->
        <div class="d-md-none p-2">
            <?php foreach ($projects_data as $pid => $project): 
                $drafts = $project['drafts'];
                $master = null;
                foreach ($drafts as $d) {
                    if ($d['is_approved'] == 1) { 
                        $master = $d; 
                        break; 
                    }
                }
                if (!$master) $master = $drafts[0];
                $has_drafts = count($drafts) > 1;
            ?>
                <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #b89441 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <div class="hotel-logo-container me-2">
                                    <?php $logo = !empty($project['logo_path']) ? $project['logo_path'] : 'assets/img/default-company.png'; ?>
                                    <img src="<?= htmlspecialchars($logo); ?>" alt="Logo" style="width:35px; height:35px; object-fit:contain;">
                                </div>
                                <div class="function-name-link" style="cursor: pointer;" data-id="<?= $master['id'] ?>">
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($project['project_name']); ?></h6>
                                    <?php if($has_drafts): ?>
                                        <span class="badge bg-gold text-white rounded-pill" style="font-size: 0.65rem;"><?= count($drafts) ?> Versions</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge <?= $master['status_info']['class']; ?> rounded-pill px-2">
                                <?= $master['status_info']['text']; ?>
                            </span>
                        </div>

                        <div class="mb-2">
                            <div class="small text-muted mb-1">
                                <i class="bi bi-calendar-event me-1 text-gold"></i> <?= $master['formatted_date']; ?>
                                <span class="mx-2 text-light">|</span>
                                <i class="bi bi-hash me-1 text-gold"></i> <?= htmlspecialchars($master['function_code']); ?>
                            </div>
                            <div class="small fw-bold text-dark">
                                <i class="bi bi-person me-1 text-gold"></i> <?= htmlspecialchars($project['booking_name']); ?>
                            </div>
                            <div class="small">
                                <a href="tel:<?= $project['phone']; ?>" class="text-muted text-decoration-none">
                                    <i class="bi bi-telephone me-1 text-gold"></i> <?= formatPhoneNumber($project['phone']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                            <span class="small text-muted">มูลค่ารวม:</span>
                            <span class="text-primary fw-bold fs-5">฿<?= number_format($master['total_amount'] ?: 0, 2); ?></span>
                        </div>

                        <div class="row g-2">
                            <div class="col-4">
                                <a href="view.php?id=<?= $master['id']; ?>" class="btn btn-outline-primary btn-sm w-100 py-2">
                                    <i class="bi bi-eye d-block fs-5 mb-1"></i> ดูข้อมูล
                                </a>
                            </div>
                            <div class="col-4">
                                <?php if (in_array($user_role, ['admin', 'gm', 'staff', 'manager', 'procurement'])): ?>
                                    <a href="finance.php?id=<?= $master['id']; ?>" class="btn btn-outline-warning btn-sm w-100 py-2">
                                        <i class="bi bi-cash-coin d-block fs-5 mb-1"></i> บัญชี
                                    </a>
                                <?php else: ?>
                                     <button class="btn btn-outline-secondary btn-sm w-100 py-2 disabled">
                                        <i class="bi bi-lock d-block fs-5 mb-1"></i> บัญชี
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-4">
                                <a href="edit.php?id=<?= $master['id']; ?>" class="btn btn-outline-dark btn-sm w-100 py-2">
                                    <i class="bi bi-pencil-square d-block fs-5 mb-1"></i> แก้ไข
                                </a>
                            </div>
                        </div>

                        <?php if ($master['approve'] == 0 && in_array($user_role, ['admin', 'gm'])): ?>
                            <button type="button" class="btn btn-success btn-sm w-100 mt-2 py-2 btn-approve-draft" data-id="<?= $master['id']; ?>">
                                <i class="bi bi-check-lg me-1"></i> อนุมัติรายการนี้
                            </button>
                        <?php endif; ?>

                        <?php if($has_drafts): ?>
                            <div class="mt-3 pt-2 border-top">
                                <button class="btn btn-link btn-sm text-gold text-decoration-none w-100 p-0 toggle-mobile-drafts" data-pid="<?= $pid ?>">
                                    <i class="bi bi-chevron-down me-1"></i> ดูเวอร์ชันอื่น ๆ (<?= count($drafts)-1 ?>)
                                </button>
                                <div class="mobile-drafts-container mt-2" id="mobile-drafts-<?= $pid ?>" style="display: none;">
                                    <?php foreach($drafts as $row): if($row['id'] == $master['id']) continue; ?>
                                        <div class="card mb-2 border-0 bg-light">
                                            <div class="card-body p-2 small">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['draft_name']) ?></span>
                                                    <span class="badge <?= $row['status_info']['class']; ?> px-2" style="font-size: 0.6rem;"><?= $row['status_info']['text']; ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted">#<?= $row['function_code'] ?></span>
                                                    <span class="text-primary fw-bold">฿<?= number_format($row['total_amount'], 2) ?></span>
                                                </div>
                                                <div class="text-end mt-2">
                                                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-xs btn-outline-primary py-0 px-2">ดู</a>
                                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-xs btn-outline-dark py-0 px-2">แก้ไข</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .modal-content { border-radius: 1rem; border: none; overflow: hidden; }
    .modal-header { background: #212529; color: #fff; border-bottom: none; padding: 1.25rem; }
    .card-header { background: #f8f9fa; border-bottom: 1px solid #eee; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05rem; }
    .form-control { border: 1px solid #dee2e6; border-radius: 0.5rem; }
    .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15); }
    .btn-success { background: #198754; border-radius: 0.5rem; transition: transform 0.2s; }
    .btn-success:hover { transform: translateY(-2px); }
    .hr-line { border-top: 2px solid #e9ecef; margin: 1.5rem 0; }
</style>

<div class="modal fade" id="eventDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">จัดการรายการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalBody"></div>
        </div>
    </div>
</div>

<script>
    function showEventDetail(title, functionId) {
        document.getElementById('modalTitle').innerText = 'รายละเอียด: ' + title;
        const userRole = '<?php echo htmlspecialchars(strtolower($_SESSION['role'] ?? 'viewer')); ?>';
        loadEventInfoModal(functionId, userRole);
    }

    function loadEventInfoModal(functionId, userRole) {
        fetch(`api/get_modal_data.php?id=${functionId}`)
            .then(res => res.json())
            .then(response => {
                if (response.status !== 'success') {
                    alert('ไม่สามารถโหลดข้อมูลได้');
                    return;
                }
                const data = response.data;
                const mtList = response.mt_list || [];
                const hkList = response.hk_list || [];
                const bkList = response.bk_list || [];
                
                const renderChecklist = (list, targetTextareaName) => list.map(item => `
                    <div class="form-check">
                        <input class="form-check-input checklist-item" type="checkbox" value="${item.task_detail}" data-target="${targetTextareaName}" id="chk_${targetTextareaName}_${item.id}">
                        <label class="form-check-label small" for="chk_${targetTextareaName}_${item.id}">${item.task_detail}</label>
                    </div>
                `).join('');
                
                // กำหนดขนาด Column ตาม Role
                const mtColClass = (userRole === 'technician' || userRole === 'banquet_staff') ? 'col-12' : 'col-md-6';
                const hkColClass = (userRole === 'housekeeping') ? 'col-12' : 'col-md-6';

                document.getElementById('modalBody').innerHTML = `
                    <form id="modalInfoForm" enctype="multipart/form-data">
                        <input type="hidden" name="function_id" value="${functionId}">
                        <div class="row mb-4">
                            ${(userRole === 'technician' || userRole === 'admin' || userRole === 'gm' || userRole === 'banquet_staff') ? `
                            <div class="${mtColClass}">
                                <div class="card h-100 shadow-sm border-0 bg-light mb-3">
                                    <div class="card-header bg-transparent fw-bold">4. ด้านเทคนิคและงานช่าง</div>
                                    <div class="card-body">
                                        <label class="small fw-bold">การจัดงานเลี้ยง:</label>
                                        <textarea name="banquet_style" id="bk_textarea" class="form-control mb-2" placeholder="การจัดงานเลี้ยง">${data.banquet_style || ''}</textarea>
                                        <div class="p-2 border bg-white rounded mb-3" id="bk_checklist_container">${renderChecklist(bkList, 'bk_textarea')}</div>
                                        
                                        <label class="small fw-bold">งานช่างและภาพเสียง:</label>
                                        <textarea name="equipment" id="mt_textarea" class="form-control mb-3" placeholder="งานช่างและภาพเสียง">${data.equipment || ''}</textarea>
                                        <div class="p-2 border bg-white rounded" id="mt_checklist_container">${renderChecklist(mtList, 'mt_textarea')}</div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            ${(userRole === 'housekeeping' || userRole === 'admin' || userRole === 'gm') ? `
                            <div class="${hkColClass}">
                                <div class="card h-100 shadow-sm border-0 bg-light mb-3">
                                    <div class="card-header bg-transparent fw-bold">6. การตกแต่งและการดูแลทำความสะอาด</div>
                                    <div class="card-body">
                                        <textarea name="backdrop_detail" class="form-control mb-2" placeholder="รายละเอียดฉากหลัง">${data.backdrop_detail || ''}</textarea>
                                        <textarea name="hk_florist_detail" id="hk_textarea" class="form-control mb-3" placeholder="พนักงานทำความสะอาดและจัดดอกไม้">${data.hk_florist_detail || ''}</textarea>
                                        <div class="p-2 border bg-white rounded" id="hk_checklist_container">${renderChecklist(hkList, 'hk_textarea')}</div>
                                        <label class="small fw-bold mt-3">รูปภาพฉากหลัง:</label>
                                        <input type="file" name="backdrop_img" class="form-control form-control-sm" accept="image/*">
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        <div class="text-end mb-4">
                            <button type="submit" class="btn btn-success px-4">บันทึกข้อมูลทั่วไป</button>
                        </div>
                    </form>
                    <hr>
                    <div class="card border-0 shadow-sm mb-5">
                        <div class="card-header bg-dark text-white p-3 border-0">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>บันทึกรายการบัญชีใหม่</h6>
                        </div>
                        <div class="card-body p-3 bg-light">
                            <form id="modalFinanceForm">
                                <input type="hidden" name="function_id" value="${functionId}">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-bold mb-1">ประเภท</label>
                                        <select name="type" class="form-select border-0 shadow-sm" required>
                                            <option value="income">รายรับ</option>
                                            <option value="cost">รายจ่าย</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-bold mb-1">รายละเอียด</label>
                                        <textarea name="detail" class="form-control border-0 shadow-sm" rows="1" placeholder="รายละเอียด" required></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted fw-bold mb-1">จำนวนเงิน</label>
                                        <input type="number" step="0.01" name="amount" class="form-control border-0 shadow-sm" placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted fw-bold mb-1">วันที่</label>
                                        <input type="date" name="transaction_date" class="form-control border-0 shadow-sm" value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100 shadow-sm">
                                            <i class="bi bi-check-lg me-1"></i>บันทึก
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="financeContainer"></div>
                `;
                
                // ติดตั้ง Event Listener หลังจาก HTML ถูกแทรก
                document.querySelectorAll('.checklist-item').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const targetId = this.getAttribute('data-target');
                        const textarea = document.getElementById(targetId);
                        let lines = textarea.value.split(/\r?\n/).filter(line => line.trim() !== '');
                        
                        if (this.checked) {
                            if (!lines.includes(this.value)) lines.push(this.value);
                        } else {
                            lines = lines.filter(line => line !== this.value);
                        }
                        textarea.value = lines.join('\n');
                    });
                });

                // ตรวจสอบสถานะ Checkbox เมื่อโหลด Modal
                ['mt_textarea', 'hk_textarea'].forEach(textareaId => {
                    const textarea = document.getElementById(textareaId);
                    if (!textarea) return;
                    
                    const lines = textarea.value.split(/\r?\n/);
                    document.querySelectorAll(`.checklist-item[data-target="${textareaId}"]`).forEach(checkbox => {
                        if (lines.includes(checkbox.value)) {
                            checkbox.checked = true;
                        }
                    });
                });
                
                document.getElementById('modalInfoForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('update', '1');
                    
                    fetch('api/update_modal_function.php', {
                        method: 'POST',
                        body: formData
                    }).then(res => res.json()).then(data => {
                        if(data.status === 'success') {
                            alert('บันทึกข้อมูลเรียบร้อย');
                        } else {
                            alert(data.message || 'เกิดข้อผิดพลาด');
                        }
                    });
                });
                
                document.getElementById('modalFinanceForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    fetch('api/finance_handler.php?action=save', {
                        method: 'POST',
                        body: new FormData(this)
                    }).then(res => res.json()).then(data => {
                        if(data.status === 'success') {
                            loadFinanceContent(functionId, userRole);
                        } else {
                            alert(data.message);
                        }
                    });
                });

                loadFinanceContent(functionId, userRole);
                var myModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                myModal.show();
            });
    }

    function loadFinanceContent(functionId, userRole) {
        fetch(`finance.php?id=${functionId}&ajax=1`)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const rows = doc.querySelectorAll('tbody tr');
                let filteredRows = '';
                rows.forEach(row => {
                    const roleBadge = row.querySelector('.badge:last-child');
                    if (roleBadge && roleBadge.innerText.toLowerCase() === userRole) {
                        filteredRows += row.outerHTML;
                    }
                });

                document.getElementById('financeContainer').innerHTML = `
                    <h6 class="fw-bold mb-3">รายการการเงิน</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr>${doc.querySelector('thead').innerHTML}</tr></thead>
                            <tbody class="bg-white">${filteredRows || '<tr><td colspan="7" class="text-center py-4 text-muted">ยังไม่มีรายการ</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.function-name-link, .project-title-link').forEach(el => {
            el.addEventListener('click', () => {
                let functionId = el.getAttribute('data-id');
                if (!functionId) {
                    functionId = el.closest('tr')?.querySelector('.row-checkbox')?.value;
                }
                const title = el.innerText.trim();
                if (functionId) {
                    showEventDetail(title, functionId);
                }
            });
        });
    });
</script>

<script>
    $(document).on('click', '.toggle-drafts', function() {
        const tr = $(this).closest('tr');
        const pid = tr.data('pid');
        const subRows = $(`.draft-sub-row[data-parent-pid="${pid}"]`);
        
        if (subRows.is(':visible')) {
            subRows.fadeOut(200);
            $(this).removeClass('bi-dash-square').addClass('bi-plus-square');
        } else {
            subRows.fadeIn(200);
            $(this).removeClass('bi-plus-square').addClass('bi-dash-square');
        }
    });

    $(document).on('click', '.toggle-mobile-drafts', function() {
        const pid = $(this).data('pid');
        const container = $(`#mobile-drafts-${pid}`);
        if (container.is(':visible')) {
            container.slideUp(200);
            $(this).html('<i class="bi bi-chevron-down me-1"></i> ดูเวอร์ชันอื่น ๆ');
        } else {
            container.slideDown(200);
            $(this).html('<i class="bi bi-chevron-up me-1"></i> ปิดเวอร์ชันอื่น ๆ');
        }
    });

    $(document).on('click', '.btn-approve-draft', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'ยืนยันการอนุมัติ Draft นี้?',
            text: "Draft นี้จะกลายเป็นรายการหลักของโครงการ และสถานะโครงการจะถูกเปลี่ยนเป็น Approved",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b89441',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยันการอนุมัติ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'approve_event.php',
                    type: 'POST',
                    data: { id: id, status: 'Confirmed' },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('สำเร็จ!', 'อนุมัติ Draft เรียบร้อยแล้ว', 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('ผิดพลาด!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });
</script>

<script>
    const userRole = '<?php echo htmlspecialchars(strtolower($_SESSION['role'] ?? 'viewer')); ?>';
</script>
<script src="assets/delete_handler.js"></script>
<?php include "style/banquet_table.php"; ?>
<?php include "footer.php"; ?>