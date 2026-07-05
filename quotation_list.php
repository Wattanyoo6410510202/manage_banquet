<?php
include "config.php";
include "header.php";

$role = strtolower($_SESSION['role'] ?? 'staff');
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$is_admin_or_gm = in_array($role, ['admin', 'gm']);
$can_approve = in_array($role, ['admin', 'gm', 'manager']);

if (!isset($_GET['my'])) {
    $my_only = !$is_admin_or_gm;
} else {
    $my_only = $_GET['my'] === '1';
}
$user_id = intval($_SESSION['user_id'] ?? 0);
$filter_clause = $my_only ? "WHERE q.created_by = $user_id" : "";

$sql = "SELECT q.*, f.function_name, c.cust_name, p.project_name 
        FROM quotations q
        LEFT JOIN functions f ON q.function_id = f.id
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN event_projects p ON q.project_id = p.id
        $filter_clause
        ORDER BY q.id DESC";

$result = $conn->query($sql);
$quotes = [];
while ($row = $result->fetch_assoc()) {
    $quotes[] = $row;
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div id="alert-container">
    <?php include "assets/alert.php"; ?>
</div>
<div class="container-fluid p-0">
    <div class="row mb-4 align-items-center">
        <div class="col-md">
            <h4 class="fw-bold text-dark mb-0">
                <i class="bi bi-file-earmark-text me-2 text-gold"></i> รายการใบเสนอราคา<?= $my_only ? ' (ของฉัน)' : ' (ทั้งหมด)' ?>
            </h4>
            <p class="text-muted small mb-0">อนุมัติและใช้งาน</p>
        </div>
        <div class="col-md-auto d-flex align-items-center gap-2 mt-3 mt-md-0">
            <select class="form-select form-select-sm" style="width:auto" onchange="location.href='quotation_list.php?my='+this.value">
                <option value="0" <?= !$my_only ? 'selected' : '' ?>>ทั้งหมด</option>
                <option value="1" <?= $my_only ? 'selected' : '' ?>>เฉพาะของฉัน</option>
            </select>
            <a href="add_quote.php" class="btn btn-dark btn-create">
                <i class="bi bi-plus-circle-fill me-2"></i> สร้างใบเสนอราคาใหม่
            </a>
        </div>
    </div>

    <div class="card p-0 border-0 shadow-sm">
        <!-- Desktop Table -->
        <div class="table-responsive d-none d-md-block p-3">
            <table id="quoteDataTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center" width="10%">เลขที่ใบเสนอราคา</th>
                        <th width="8%">วันที่ออก</th>
                        <th width="12%">วันที่เริ่ม - วันสิ้นสุด</th>
                        <th>ชื่อลูกค้า / โครงการ</th>
                        <th class="text-end" width="10%">ยอดสุทธิ</th>
                        <th class="text-center" width="8%">สถานะ</th>
                        <th class="text-center" width="8%">เลือกใช้งาน</th>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $status_map = [
                        'Draft' => ['class' => 'bg-secondary-subtle text-secondary', 'text' => 'ฉบับร่าง'],
                        'Sent' => ['class' => 'bg-info-subtle text-info', 'text' => 'ส่งแล้ว'],
                        'Approved' => ['class' => 'bg-success-subtle text-success', 'text' => 'อนุมัติแล้ว'],
                        'Cancelled' => ['class' => 'bg-danger-subtle text-danger', 'text' => 'ยกเลิก']
                    ];
                    foreach ($quotes as $q): 
                        $st = $status_map[$q['status']] ?? $status_map['Draft'];
                    ?>
                        <tr style="<?= $q['is_selected'] ? 'background-color: #f0f7ff;' : '' ?>">
                            <td class="text-center fw-bold text-primary" style="font-size: 0.85rem;">
                                <?= $q['quote_no'] ?>
                                <?php if ($q['is_selected']): ?>
                                    <div class="badge bg-primary d-block mt-1" style="font-size: 0.6rem;">SELECTED</div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($q['created_at'])) ?></td>
                            <td style="font-size: 0.85rem;">
                                <?= !empty($q['event_date']) ? date('d/m/Y', strtotime($q['event_date'])) : '-' ?>
                                -
                                <?= !empty($q['expiry_date']) ? date('d/m/Y', strtotime($q['expiry_date'])) : '-' ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($q['cust_name']) ?></div>
                                <div class="text-gold small fw-bold"><i class="bi bi-folder-fill me-1"></i><?= htmlspecialchars($q['project_name'] ?: ($q['event_name'] ?: $q['function_name'])) ?></div>
                            </td>
                            <td class="text-end fw-bold text-dark"><?= number_format($q['grand_total'], 2) ?></td>
                            <td class="text-center">
                                <span class="badge border <?= $st['class'] ?> px-3 py-2"><?= $st['text'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($q['is_selected']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 btn-deselect-quote" data-id="<?= $q['id'] ?>">
                                        <i class="bi bi-x-lg"></i> ยกเลิก
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-select-quote" data-id="<?= $q['id'] ?>">
                                        เลือกใช้งาน
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($q['status'] !== 'Approved' || $role === 'admin'): ?>
                                        <?php if ($can_approve && $q['status'] !== 'Approved'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success btn-approve-quote" data-id="<?= $q['id'] ?>"><i class="bi bi-check-circle"></i></button>
                                        <?php endif; ?>
                                        <a href="edit_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></a>
                                    <?php endif; ?>
                                    <?php if ($q['status'] === 'Approved'): ?>
                                        <a href="add_event.php?quote_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-calendar-plus"></i></a>
                                    <?php endif; ?>
                                    <a href="quotation_view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></a>
                                    <?php if ($role === 'admin' || intval($q['created_by']) === $current_user_id): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-quote" data-id="<?= $q['id'] ?>"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="d-md-none p-2">
            <?php foreach ($quotes as $row): 
                $st = $status_map[$row['status']] ?? $status_map['Draft'];
            ?>
                <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px; background: #fff;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-primary"><?= $row['quote_no'] ?></span>
                                <div class="text-muted small"><?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
                            </div>
                            <span class="badge border <?= $st['class'] ?> px-2 py-1">
                                <?= $st['text'] ?>
                            </span>
                        </div>
                        
                        <div class="fw-bold text-dark mb-1"><?= $row['cust_name'] ?></div>
                        <small class="text-muted d-block mb-1">
                            <i class="bi bi-calendar-event me-1"></i><?= $row['event_name'] ?? $row['function_name'] ?>
                        </small>
                        <small class="text-muted d-block mb-3">
                            <i class="bi bi-calendar-range me-1"></i>
                            <?= !empty($row['event_date']) ? date('d/m/Y', strtotime($row['event_date'])) : '?' ?>
                            -
                            <?= !empty($row['expiry_date']) ? date('d/m/Y', strtotime($row['expiry_date'])) : '?' ?>
                        </small>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <div class="fw-bold text-dark">
                                ฿<?= number_format($row['grand_total'], 2) ?>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if ($row['is_selected']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-deselect-quote" data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-select-quote" data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($row['status'] !== 'Approved' && $can_approve): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-approve-quote" data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($row['status'] === 'Approved'): ?>
                                    <a href="add_event.php?quote_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-calendar-plus"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="quotation_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer"></i>
                                </a>
                                
                                <?php if ($row['status'] !== 'Approved' || $role === 'admin'): ?>
                                    <a href="edit_quotation.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($role === 'admin' || intval($row['created_by']) === $current_user_id): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-quote" data-id="<?= $row['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function () {
        $('#quoteDataTable').DataTable({
            "order": [[ 0, "desc" ]],
            "language": {
                "search": "ค้นหา:",
                "lengthMenu": "แสดง _MENU_ รายการ",
                "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                "paginate": {
                    "first": "หน้าแรก",
                    "last": "หน้าสุดท้าย",
                    "next": "ถัดไป",
                    "previous": "ก่อนหน้า"
                }
            }
        });

        $(document).on('click', '.btn-delete-quote', function (e) {
            e.preventDefault();
            const quoteId = $(this).attr('data-id');
            const row = $(this).closest('tr');

            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลนี้จะถูกลบอย่างถาวร!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api/delete_quote.php',
                        type: 'POST',
                        data: { id: quoteId },
                        success: function (response) {
                            if (response.trim() === 'success') {
                                $('#quoteDataTable').DataTable().row(row).remove().draw();
                                Swal.fire('สำเร็จ!', 'ลบข้อมูลเรียบร้อยแล้ว', 'success');
                            } else {
                                Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาด: ' + response, 'error');
                            }
                        }
                    });
                }
            });
        });
    });

    $(document).on('click', '.btn-approve-quote', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: "คุณต้องการอนุมัติใบเสนอราคานี้ใช่หรือไม่?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, อนุมัติเลย'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'api/approve_process.php?id=' + id;
            }
        });
    });

    $(document).on('click', '.btn-deselect-quote', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'ยกเลิกการเลือกใบเสนอราคานี้?',
            text: "ใบเสนอราคานี้จะไม่ถูกใช้งานเป็นหลักอีกต่อไป",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ยกเลิกเลย'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/select_quote.php',
                    type: 'GET',
                    data: { id: id, deselect: 1 },
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire('สำเร็จ!', 'ยกเลิกการเลือกเรียบร้อยแล้ว', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('ผิดพลาด!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-select-quote', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'เลือกใบเสนอราคานี้?',
            text: "ต้องการเลือกใบนี้เป็นใบที่ใช้งานหลักสำหรับโครงการนี้ใช่หรือไม่?",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, เลือกเลย'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/select_quote.php',
                    type: 'GET',
                    data: { id: id },
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire('สำเร็จ!', 'เลือกใบเสนอราคาเรียบร้อยแล้ว', 'success').then(() => {
                                location.reload();
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
<?php include "footer.php"; ?>