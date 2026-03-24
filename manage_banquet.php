<?php
include "config.php";
include "header.php";
?>
<?php
$user_role = strtolower($_SESSION['role'] ?? '');

$can_manage = in_array($user_role, ['admin', 'staff', 'gm']);

// 1. ตรวจสอบ Role และ User
$user_role = strtolower($_SESSION['role'] ?? 'staff');
$current_user = $_SESSION['user_name'] ?? '';
$can_manage = in_array($user_role, ['admin', 'gm', 'manager']); // กำหนดสิทธิ์จัดการ

// 2. เตรียม WHERE Clause
$where_clause = "";
if ($user_role === 'staff') {
    $safe_user = mysqli_real_escape_string($conn, $current_user);
    $where_clause = " WHERE f.created_by = '$safe_user' ";
}

// 3. SQL Query
$sql = "SELECT f.*, c.company_name, c.logo_path,
        (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date 
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id
        $where_clause
        ORDER BY f.id DESC";

$q = mysqli_query($conn, $sql);
$functions_data = [];

// 4. วนลูปเก็บข้อมูลลง Array และจัดการ Format ให้พร้อมใช้
if ($q && mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        // จัดการเรื่องวันที่
        $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
        $row['formatted_date'] = date('d M Y', strtotime($display_date));

        // จัดการสถานะ (Status Mapping)
        // 1. กำหนด Mapping ของสถานะ
        // 1. กำหนด Mapping ของสถานะ (เหมือนเดิม)
        // จัดการสถานะ (Status Mapping) ตามโครงสร้าง statusConfig
        $status_map = [
            'Confirmed' => [
                'text' => 'อนุมัติแล้ว',
                'class' => 'bg-success-subtle text-success',
                'icon' => 'bi-check-circle'
            ],
            'In Progress' => [
                'text' => 'ดำเนินการ',
                'class' => 'bg-info-subtle text-info',
                'icon' => 'bi-play-circle'
            ],
            'Completed' => [
                'text' => 'จบงานแล้ว',
                'class' => 'bg-primary-subtle text-primary',
                'icon' => 'bi-flag'
            ],
            'Cancelled' => [
                'text' => 'ยกเลิก',
                'class' => 'bg-danger-subtle text-danger',
                'icon' => 'bi-x-circle'
            ],
            // เผื่อกรณีสถานะอื่นๆ ที่ยังไม่มีใน List ให้เป็นสีเทาไว้ก่อน
            'Pending' => [
                'text' => 'รออนุมัติ',
                'class' => 'bg-warning-subtle text-warning',
                'icon' => 'bi-clock-history'
            ]
        ];

        // ดึงค่าสถานะจากคอลัมน์ status มาแมป (ถ้าไม่มีใน map ให้ดึง Pending เป็นค่าเริ่มต้น)
        $current_status = $row['status'] ?? 'Pending';
        $row['status_info'] = $status_map[$current_status] ?? $status_map['Pending'];

        // จัดการไฟล์แนบ
        $row['attachments'] = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($row['file_attachment' . $i])) {
                $row['attachments'][] = [
                    'path' => $row['file_attachment' . $i],
                    'label' => 'ไฟล์แนบ ' . $i
                ];
            }
        }

        $functions_data[] = $row;
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
                // เช็คสิทธิ์ก่อน ถ้าไม่ใช่ viewer ถึงจะยอมให้ปุ่มนี้ปรากฏในโครงสร้าง HTML
                if ($user_role !== 'viewer'):
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

                $user_role = strtolower($_SESSION['role'] ?? '');

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
        <div class="table-responsive">
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
                    <?php foreach ($functions_data as $row): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-checkbox form-check-input" value="<?= $row['id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-2 hotel-logo-container">
                                        <?php $logo = !empty($row['logo_path']) ? $row['logo_path'] : 'default-logo.png'; ?>
                                        <img src="<?= htmlspecialchars($logo); ?>" alt="Logo">
                                    </div>
                                    <div class="fw-bold text-dark small">
                                        <?= htmlspecialchars($row['company_name'] ?: '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['function_name']); ?></div>
                                <div class="text-muted small">
                                    <i class="bi bi-calendar-event me-1"></i> <?= $row['formatted_date']; ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark small"><?= htmlspecialchars($row['booking_name']); ?></div>
                                <div class="text-muted x-small"><?= htmlspecialchars($row['phone']); ?></div>
                            </td>
                            <td><span
                                    class="text-primary fw-bold"><?= number_format($row['total_amount'] ?: 0, 2); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal">
                                    <i class="bi bi-hash me-1 text-gold"></i>
                                    <?= htmlspecialchars($row['function_code']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $row['status_info']['class']; ?> rounded-pill px-3">
                                    <i class="bi <?= $row['status_info']['icon']; ?> me-1"></i>
                                    <?= $row['status_info']['text']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-muted small"><?= htmlspecialchars($row['created_by'] ?: '-'); ?></div>
                                <div class="text-muted x-small">แก้ไขเมื่อ <?= htmlspecialchars($row['modify']); ?></div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (empty($row['attachments'])): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <?php foreach ($row['attachments'] as $file): ?>
                                            <a href="<?= htmlspecialchars($file['path']); ?>" target="_blank" class="text-danger"
                                                title="<?= $file['label']; ?>" style="font-size: 1.2rem; line-height: 1;">
                                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center sticky-col">
                                <div class="d-flex justify-content-center gap-1">

                                    <?php
                                    // เช็คสิทธิ์ก่อนว่าไม่ใช่ viewer ถึงจะโชว์ปุ่มจัดการสถานะ
                                    if ($user_role !== 'viewer'):
                                        ?>
                                        <?php if ($row['approve'] == 0 && in_array($user_role, ['admin', 'gm'])): ?>
                                            <button type="button" class="btn btn-sm btn-success btn-approve-row"
                                                data-id="<?= $row['id']; ?>" title="อนุมัติงาน">
                                                <i class="bi bi-check-lg"></i> อนุมัติ
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($row['approve'] == 1 && $row['status'] == 'Confirmed'): ?>
                                            <button type="button" class="btn btn-sm btn-info text-white btn-status-change"
                                                data-id="<?= $row['id']; ?>" data-status="In Progress" title="เริ่มดำเนินการ">
                                                <i class="bi bi-play-fill"></i> ดำเนินการ
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($row['status'] == 'In Progress'): ?>
                                            <button type="button" class="btn btn-sm btn-primary btn-status-change"
                                                data-id="<?= $row['id']; ?>" data-status="Completed" title="จบงานเรียบร้อย">
                                                <i class="bi bi-flag-fill"></i> จบงาน
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!in_array($row['status'], ['Completed', 'Cancelled'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-status-change"
                                                data-id="<?= $row['id']; ?>" data-status="Cancelled" title="ยกเลิกงานนี้">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>

                                    <?php endif; // จบการเช็ค viewer สำหรับกลุ่มปุ่มสถานะ ?>

                                    <div class="vr mx-1"></div>

                                    <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-primary"
                                        title="พิมพ์/ดูรายละเอียด">
                                        <i class="bi bi-printer"></i>
                                    </a>

                                    <a href="finance.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-warning"
                                        title="จัดการบัญชี/ROI">
                                        <i class="bi bi-cash-coin"></i>
                                    </a>

                                    <?php
                                    // ส่วน แก้ไข และ ลบ (Viewer ห้ามเห็นแน่นอน)
                                    if ($user_role !== 'viewer' && $can_manage && $row['status'] != 'Completed'):
                                        ?>
                                        <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-dark"
                                            title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row"
                                            data-id="<?= $row['id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

<script src="assets/delete_handler.js"></script>
<?php include "style/banquet_table.php"; ?>
<?php include "footer.php"; ?>