<?php
include "config.php";
include "header.php";
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

                <button id="deleteSelected" class="btn btn-danger btn-sm py-1 px-2 shadow-sm"
                    style="display:none; font-size: 0.75rem;">
                    <i class="bi bi-trash3-fill"></i>
                    <span class="ms-1">ลบ (<span id="selectCount">0</span>)</span>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="calendar.php" class="btn btn-outline-hotel btn-sm border-0 py-1">
                    <i class="bi bi-calendar3"></i>
                    <span class="d-none d-sm-inline ms-1 small">ปฏิทิน</span>
                </a>
                <a href="add_event.php" class="btn btn-dark btn-sm px-3 py-1 rounded-pill shadow-sm"
                    style="font-size: 0.8rem;">
                    <i class="bi bi-plus-lg"></i>
                    <span class="ms-1">เพิ่มงานใหม่</span>
                </a>
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
                        <th>เงินมัดจำ</th>
                        <th>เลขที่</th>
                        <th>สถานะ</th>
                        <th>Sales</th>
                        <th class="text-center bg-light">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT f.*, c.company_name, c.logo_path,
                           (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date 
                           FROM functions f 
                           LEFT JOIN companies c ON f.company_id = c.id
                           ORDER BY f.id DESC";
                    $q = mysqli_query($conn, $sql);
                    if ($q && mysqli_num_rows($q) > 0) {
                        while ($row = mysqli_fetch_assoc($q)) {
                            $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
                            $date = date('d M Y', strtotime($display_date));
                            $deposit = !empty($row['deposit']) ? number_format($row['deposit'], 2) : '0.00';
                            $comp_name = !empty($row['company_name']) ? $row['company_name'] : '-';

                            $status_map = [
                                1 => ['text' => 'อนุมัติแล้ว', 'class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle'],
                                2 => ['text' => 'ยกเลิก', 'class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle'],
                                0 => ['text' => 'รออนุมัติ', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'bi-clock-history']
                            ];
                            $st = $status_map[$row['approve']] ?? $status_map[0];
                            ?>
                            <tr>
                                <td><input type="checkbox" class="row-checkbox form-check-input"
                                        value="<?php echo $row['id']; ?>"></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2 hotel-logo-container">
                                            <?php $logo = !empty($row['logo_path']) ? $row['logo_path'] : 'default-logo.png'; ?>
                                            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
                                        </div>
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($comp_name); ?></div>
                                    </div>
                                </td>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['function_name']); ?></div>
                                    <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> <?php echo $date; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark small"><?php echo htmlspecialchars($row['booking_name']); ?></div>
                                    <div class="text-muted x-small"><?php echo htmlspecialchars($row['phone']); ?></div>
                                </td>
                                <td><span class="text-primary fw-bold"><?php echo $deposit; ?></span></td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal">
                                        <i class="bi bi-hash me-1 text-gold"></i>
                                        <?php echo htmlspecialchars($row['function_code']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $st['class']; ?> rounded-pill px-3">
                                        <i class="bi <?php echo $st['icon']; ?> me-1"></i> <?php echo $st['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-muted small"><?php echo htmlspecialchars($row['created_by'] ?: '-'); ?>
                                    </div>
                                </td>
                                <td class="text-center  sticky-col">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($row['approve'] == 0): ?>
                                            <button class="btn btn-sm btn-outline-success "
                                                onclick="confirmApprove(<?php echo $row['id']; ?>)" title="อนุมัติงาน">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary "
                                            title="ดูรายละเอียด">
                                            <i class="bi bi-printer "></i>
                                        </a>

                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-dark "
                                            title="แก้ไขข้อมูล">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                            data-id="<?php echo $row['id']; ?>" title="ลบรายการ">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php }
                    } ?>
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

<?php include "style/banquet_table.php"; ?>
<?php include "footer.php"; ?>