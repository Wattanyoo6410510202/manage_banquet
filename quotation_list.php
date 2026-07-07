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
        ORDER BY COALESCE(q.project_id, q.id) DESC, q.id DESC";

$result = $conn->query($sql);
$quotes = [];
while ($row = $result->fetch_assoc()) {
    $quotes[] = $row;
}

// Group by project_id
$quotes_by_project = [];
$ungrouped = [];
foreach ($quotes as $q) {
    if ($q['project_id']) {
        $pid = $q['project_id'];
        if (!isset($quotes_by_project[$pid])) {
            $quotes_by_project[$pid] = [
                'project_name' => $q['project_name'] ?: 'ไม่ระบุโครงการ',
                'project_id' => $pid,
                'quotes' => []
            ];
        }
        $quotes_by_project[$pid]['quotes'][] = $q;
    } else {
        $ungrouped[] = $q;
    }
}

$status_map = [
    'Draft' => ['class' => 'bg-secondary-subtle text-secondary', 'text' => 'ฉบับร่าง'],
    'Sent' => ['class' => 'bg-info-subtle text-info', 'text' => 'ส่งแล้ว'],
    'Approved' => ['class' => 'bg-success-subtle text-success', 'text' => 'อนุมัติแล้ว'],
    'Cancelled' => ['class' => 'bg-danger-subtle text-danger', 'text' => 'ยกเลิก']
];
?>

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
            <table id="quoteTable" class="table table-hover align-middle mb-0" style="width:100%">
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
                    <!-- Ungrouped quotes (no project_id) first -->
                    <?php if (!empty($ungrouped)): ?>
                        <tr class="project-header-row has-sub" data-pid="ungrouped">
                            <td class="text-center" colspan="8">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-plus-square text-muted toggle-quotes" style="cursor: pointer; font-size: 1.1rem;"></i>
                                    <i class="bi bi-file-earmark-text text-muted"></i>
                                    <span class="fw-bold text-muted">ใบเสนอราคาที่ไม่มีโครงการ</span>
                                    <span class="badge bg-secondary text-white rounded-pill" style="font-size: 0.65rem;"><?= count($ungrouped) ?> ใบ</span>
                                </div>
                            </td>
                        </tr>
                        <?php foreach ($ungrouped as $q):
                            $st = $status_map[$q['status']] ?? $status_map['Draft'];
                        ?>
                            <tr class="quote-sub-row bg-light" data-parent-pid="ungrouped" style="display:none">
                                <td class="text-center fw-bold text-primary" style="font-size: 0.85rem;">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-arrow-return-right text-muted" style="font-size: 0.8rem;"></i>
                                        <?= $q['quote_no'] ?>
                                    </div>
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
                                    <div class="fw-bold text-dark">ชื่อลูกค้า : <?= htmlspecialchars($q['cust_name']) ?></div>
                                    <div class="text-gold small fw-bold"><?= htmlspecialchars($q['event_name'] ?: $q['function_name']) ?></div>
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
                    <?php endif; ?>

                    <?php foreach ($quotes_by_project as $pid => $project):
                        $quotes_list = $project['quotes'];
                        $has_multiple = count($quotes_list) > 1;
                        $first_q = $quotes_list[0];
                    ?>
                        <!-- Project Header Row -->
                        <tr class="project-header-row has-sub" data-pid="p_<?= $pid ?>">
                            <td class="text-center" colspan="8">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-plus-square text-gold toggle-quotes" style="cursor: pointer; font-size: 1.1rem;"></i>
                                    
                                    <span class="fw-bold text-dark">ชื่อโครงการ: <?= htmlspecialchars($project['project_name']) ?></span>
                                    <span class="badge bg-gold text-white rounded-pill" style="font-size: 0.65rem;"><?= count($quotes_list) ?> ใบ</span>
                                    <span class="text-muted small ms-2"><?= htmlspecialchars($first_q['cust_name']) ?></span>
                                    <a href="add_quote.php?project_id=<?= $pid ?>" class="btn btn-sm btn-outline-dark ms-auto" title="เพิ่มใบเสนอราคาในโครงการนี้">
                                        <i class="bi bi-plus-circle"></i> เพิ่ม
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Quotation Rows (hidden by default) -->
                        <?php foreach ($quotes_list as $q):
                            $st = $status_map[$q['status']] ?? $status_map['Draft'];
                        ?>
                            <tr class="quote-sub-row bg-light" data-parent-pid="p_<?= $pid ?>" style="display:none">
                                <td class="text-center fw-bold text-primary" style="font-size: 0.85rem;">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-arrow-return-right text-muted" style="font-size: 0.8rem;"></i>
                                        <?= $q['quote_no'] ?>
                                    </div>
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
                                    <div class="fw-bold text-dark">ชื่อลูกค้า : <?= htmlspecialchars($q['cust_name']) ?></div>
                                    <div class="text-gold small fw-bold"><?= htmlspecialchars($q['project_name'] ?: ($q['event_name'] ?: $q['function_name'])) ?></div>
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="d-md-none p-2">
            <!-- Ungrouped mobile first -->
            <?php if (!empty($ungrouped)): ?>
                <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="fw-bold text-muted"><i class="bi bi-file-earmark-text me-1"></i>ใบเสนอราคาที่ไม่มีโครงการ (<?= count($ungrouped) ?>)</div>
                            <i class="bi bi-chevron-down text-muted toggle-ungrouped-mobile" style="cursor: pointer; font-size: 1.2rem;"></i>
                        </div>
                        <div id="mobile-ungrouped" style="display:none">
                            <?php foreach ($ungrouped as $q):
                                $st = $status_map[$q['status']] ?? $status_map['Draft'];
                            ?>
                                <div class="card mb-2 border-0 bg-light">
                                    <div class="card-body p-2 small">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="fw-bold text-primary"><?= $q['quote_no'] ?></span>
                                                <div class="text-muted small"><?= date('d/m/Y', strtotime($q['created_at'])) ?></div>
                                            </div>
                                            <span class="badge border <?= $st['class'] ?> px-2 py-1"><?= $st['text'] ?></span>
                                        </div>
                                        <div class="fw-bold text-dark mb-1">ชื่อลูกค้า : <?= $q['cust_name'] ?></div>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-calendar-event me-1"></i><?= $q['event_name'] ?? $q['function_name'] ?>
                                        </small>
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <div class="fw-bold text-dark">฿<?= number_format($q['grand_total'], 2) ?></div>
                                            <div class="d-flex gap-1">
                                                <?php if ($q['is_selected']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-deselect-quote" data-id="<?= $q['id'] ?>"><i class="bi bi-x-lg"></i></button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-select-quote" data-id="<?= $q['id'] ?>"><i class="bi bi-check-lg"></i></button>
                                                <?php endif; ?>
                                                <a href="quotation_view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($quotes_by_project as $pid => $project):
                $quotes_list = $project['quotes'];
                $first_q = $quotes_list[0];
            ?>
                <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #b89441 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold text-dark">
                                    ชื่อโครงการ: <?= htmlspecialchars($project['project_name']) ?>
                                </div>
                                <span class="badge bg-gold text-white rounded-pill" style="font-size: 0.65rem;"><?= count($quotes_list) ?> ใบ</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="add_quote.php?project_id=<?= $pid ?>" class="btn btn-sm btn-outline-dark" title="เพิ่มใบเสนอราคาในโครงการนี้">
                                    <i class="bi bi-plus-circle"></i>
                                </a>
                                <i class="bi bi-chevron-down text-gold toggle-mobile-quotes" data-pid="p_<?= $pid ?>" style="cursor: pointer; font-size: 1.2rem;"></i>
                            </div>
                        </div>

                        <div id="mobile-quotes-p_<?= $pid ?>" style="display:none">
                            <?php foreach ($quotes_list as $q):
                                $st = $status_map[$q['status']] ?? $status_map['Draft'];
                            ?>
                                <div class="quote-mobile-item">
                                    <?php if ($q !== $quotes_list[0]): ?>
                                        <hr class="my-2">
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="fw-bold text-primary"><?= $q['quote_no'] ?></span>
                                            <?php if ($q['is_selected']): ?>
                                                <span class="badge bg-primary" style="font-size: 0.6rem;">SELECTED</span>
                                            <?php endif; ?>
                                            <div class="text-muted small"><?= date('d/m/Y', strtotime($q['created_at'])) ?></div>
                                        </div>
                                        <span class="badge border <?= $st['class'] ?> px-2 py-1">
                                            <?= $st['text'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="fw-bold text-dark mb-1">ชื่อลูกค้า : <?= htmlspecialchars($q['cust_name']) ?></div>
                                    <small class="text-muted d-block mb-1">
                                        <i class="bi bi-calendar-event me-1"></i><?= $q['event_name'] ?? $q['function_name'] ?>
                                    </small>
                                    <small class="text-muted d-block mb-3">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <?= !empty($q['event_date']) ? date('d/m/Y', strtotime($q['event_date'])) : '?' ?>
                                        -
                                        <?= !empty($q['expiry_date']) ? date('d/m/Y', strtotime($q['expiry_date'])) : '?' ?>
                                    </small>

                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <div class="fw-bold text-dark">
                                            ฿<?= number_format($q['grand_total'], 2) ?>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <?php if ($q['is_selected']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-deselect-quote" data-id="<?= $q['id'] ?>">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-select-quote" data-id="<?= $q['id'] ?>">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($q['status'] !== 'Approved' && $can_approve): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success btn-approve-quote" data-id="<?= $q['id'] ?>">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($q['status'] === 'Approved'): ?>
                                                <a href="add_event.php?quote_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-calendar-plus"></i>
                                                </a>
                                            <?php endif; ?>

                                            <a href="quotation_view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            
                                            <?php if ($q['status'] !== 'Approved' || $role === 'admin'): ?>
                                                <a href="edit_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($role === 'admin' || intval($q['created_by']) === $current_user_id): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-quote" data-id="<?= $q['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function () {
        // Toggle project sub-rows (desktop)
        $(document).on('click', '.toggle-quotes', function () {
            const tr = $(this).closest('tr');
            const pid = tr.data('pid');
            const subRows = $(`.quote-sub-row[data-parent-pid="${pid}"]`);
            
            if (subRows.is(':visible')) {
                subRows.fadeOut(200);
                $(this).removeClass('bi-dash-square').addClass('bi-plus-square');
            } else {
                subRows.fadeIn(200);
                $(this).removeClass('bi-plus-square').addClass('bi-dash-square');
            }
        });

        // Toggle mobile sub-quotes
        $(document).on('click', '.toggle-mobile-quotes', function () {
            const pid = $(this).data('pid');
            const container = $(`#mobile-quotes-${pid}`);
            const icon = $(this);
            if (container.is(':visible')) {
                container.slideUp(200);
                icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
            } else {
                container.slideDown(200);
                icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
            }
        });

        // Toggle ungrouped mobile
        $(document).on('click', '.toggle-ungrouped-mobile', function () {
            const container = $('#mobile-ungrouped');
            const icon = $(this);
            if (container.is(':visible')) {
                container.slideUp(200);
                icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
            } else {
                container.slideDown(200);
                icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
            }
        });

        // Delete quote
        $(document).on('click', '.btn-delete-quote', function (e) {
            e.preventDefault();
            const quoteId = $(this).attr('data-id');
            const btn = $(this);

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
                                btn.closest('tr, .quote-mobile-item, .card').remove();
                                Swal.fire('สำเร็จ!', 'ลบข้อมูลเรียบร้อยแล้ว', 'success');
                            } else {
                                Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาด: ' + response, 'error');
                            }
                        }
                    });
                }
            });
        });

        // Approve quote
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

        // Deselect quote
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

        // Select quote
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
    });
</script>
<?php include "footer.php"; ?>
