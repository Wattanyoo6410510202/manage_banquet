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

$sql = "SELECT q.*, f.function_name, c.cust_name, c.cust_contact_name, c.sales_name, p.project_name 
        FROM quotations q
        LEFT JOIN functions f ON q.function_id = f.id
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN event_projects p ON q.project_id = p.id
        $filter_clause
        ORDER BY COALESCE(q.project_id, q.id) DESC, q.id DESC";

// สถานะ Workflow (Pipeline/Follow-up)
$workflow_statuses = [
    'Draft'                        => ['class' => 'bg-secondary-subtle text-secondary',  'icon' => 'bi-pencil-square'],
    'ส่งใบเสนอราคาแล้ว'            => ['class' => 'bg-info-subtle text-info',            'icon' => 'bi-send'],
    'Follow Up ครั้งที่ 1'         => ['class' => 'bg-primary-subtle text-primary',      'icon' => 'bi-telephone'],
    'Follow Up ครั้งที่ 2'         => ['class' => 'bg-primary-subtle text-primary',      'icon' => 'bi-telephone'],
    'Follow Up ครั้งที่ 3'         => ['class' => 'bg-primary-subtle text-primary',      'icon' => 'bi-telephone'],
    'ลูกค้าต่อรองราคา'             => ['class' => 'bg-warning-subtle text-warning',      'icon' => 'bi-cash-coin'],
    'รออนุมัติส่วนลด'              => ['class' => 'bg-warning-subtle text-warning',      'icon' => 'bi-hourglass-split'],
    'ส่งใบเสนอราคาใหม่ (Revision)' => ['class' => 'bg-info-subtle text-info',            'icon' => 'bi-arrow-repeat'],
    'ลูกค้าเซ็นยืนยัน'             => ['class' => 'bg-success-subtle text-success',      'icon' => 'bi-check-circle'],
    'รับเงินมัดจำแล้ว'             => ['class' => 'bg-success-subtle text-success',      'icon' => 'bi-wallet2'],
    'เปิด Function (BEO)'          => ['class' => 'bg-primary-subtle text-primary',      'icon' => 'bi-calendar-check'],
    'Lost Sale'                    => ['class' => 'bg-danger-subtle text-danger',        'icon' => 'bi-x-circle'],
    'Cancelled'                    => ['class' => 'bg-danger-subtle text-danger',        'icon' => 'bi-trash'],
];

$result = $conn->query($sql);
$quotes = [];
while ($row = $result->fetch_assoc()) {
    $quotes[] = $row;
}

function groupByProject($list) {
    $by_project = [];
    $ungrouped = [];
    foreach ($list as $q) {
        if ($q['project_id']) {
            $pid = $q['project_id'];
            if (!isset($by_project[$pid])) {
                $by_project[$pid] = [
                    'project_name' => $q['project_name'] ?: 'ไม่ระบุโครงการ',
                    'project_id' => $pid,
                    'quotes' => []
                ];
            }
            $by_project[$pid]['quotes'][] = $q;
        } else {
            $ungrouped[] = $q;
        }
    }
    return ['by_project' => $by_project, 'ungrouped' => $ungrouped];
}

$pending_quotes = array_values(array_filter($quotes, fn($q) => $q['status'] !== 'Approved'));
$approved_quotes = array_values(array_filter($quotes, fn($q) => $q['status'] === 'Approved'));

$pending_grouped = groupByProject($pending_quotes);
$approved_grouped = groupByProject($approved_quotes);

$sections = [
    ['key' => 'pending', 'title' => 'รออนุมัติ', 'icon' => 'bi-hourglass-split', 'header_class' => 'bg-warning-subtle', 'badge_class' => 'bg-warning text-dark', 'data' => $pending_grouped, 'count' => count($pending_quotes)],
    ['key' => 'approved', 'title' => 'อนุมัติแล้ว', 'icon' => 'bi-check-circle-fill', 'header_class' => 'bg-success-subtle', 'badge_class' => 'bg-success', 'data' => $approved_grouped, 'count' => count($approved_quotes)],
];

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
                        <th>เซลล์ที่ดูแล</th>
                        <th class="text-end" width="10%">ยอดสุทธิ</th>
                        <th class="text-center" width="8%">สถานะ</th>
                        <th class="text-center" width="15%">สถานะใบเสนอราคา</th>
                        <th class="text-center" width="8%">เลือกใช้งาน</th>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sections as $sec):
                        $sec_ungrouped = $sec['data']['ungrouped'];
                        $sec_projected = $sec['data']['by_project'];
                    ?>
                        <!-- Section Header -->
                        <tr>
                            <td colspan="9" class="p-0">
                                <div class="d-flex align-items-center gap-2 px-3 py-2 <?= $sec['header_class'] ?>" style="font-weight: 700; font-size: 0.95rem; border-bottom: 2px solid rgba(0,0,0,0.08);">
                                    <i class="bi <?= $sec['icon'] ?>"></i>
                                    <span><?= $sec['title'] ?></span>
                                    <span class="badge <?= $sec['badge_class'] ?> rounded-pill" style="font-size: 0.7rem;"><?= $sec['count'] ?> ใบ</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Ungrouped quotes (no project_id) -->
                        <?php if (!empty($sec_ungrouped)): ?>
                            <tr class="project-header-row has-sub" data-pid="<?= $sec['key'] ?>_ungrouped">
                                <td class="text-center" colspan="9">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-plus-square text-muted toggle-quotes" style="cursor: pointer; font-size: 1.1rem;"></i>
                                        <i class="bi bi-file-earmark-text text-muted"></i>
                                        <span class="fw-bold text-muted">ใบเสนอราคาที่ไม่มีโครงการ</span>
                                        <span class="badge bg-secondary text-white rounded-pill" style="font-size: 0.65rem;"><?= count($sec_ungrouped) ?> ใบ</span>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($sec_ungrouped as $q):
                                $st = $status_map[$q['status']] ?? $status_map['Draft'];
                                $wf = $workflow_statuses[$q['workflow_status']] ?? $workflow_statuses['Draft'];
                            ?>
                                <tr class="quote-sub-row bg-light" data-parent-pid="<?= $sec['key'] ?>_ungrouped" style="display:none">
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
                                        <div class="text-muted small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($q['cust_contact_name'] ?: '-') ?></div>
                                        <?php if (!empty($q['sales_name'])): ?>
                                            <div class="small" style="color: #b89441;"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($q['sales_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-dark"><?= number_format($q['grand_total'], 2) ?></td>
                                    <td class="text-center">
                                        <span class="badge border <?= $st['class'] ?> px-3 py-2"><?= $st['text'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_admin_or_gm || intval($q['created_by']) === $current_user_id): ?>
                                        <select class="form-select form-select-sm wf-status-select <?= $wf['class'] ?>" 
                                                style="font-size: 0.72rem; padding: 3px 26px 3px 8px; border-radius: 20px; width: auto; display: inline-block; border: 1px solid rgba(0,0,0,0.1); cursor: pointer; background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 6px center;"
                                                data-id="<?= $q['id'] ?>" onchange="updateWorkflowStatus(this)">
                                            <?php foreach (array_keys($workflow_statuses) as $ws): ?>
                                                <option value="<?= $ws ?>" <?= ($q['workflow_status'] ?? 'Draft') === $ws ? 'selected' : '' ?>><?= $ws ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php else: ?>
                                            <span class="badge <?= $wf['class'] ?> px-2 py-1" style="font-size: 0.72rem;"><?= $q['workflow_status'] ?? 'Draft' ?></span>
                                        <?php endif; ?>
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
                                                <?php if ($role === 'admin' || intval($q['created_by']) === $current_user_id): ?>
                                                <a href="edit_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></a>
                                                <?php endif; ?>
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

                        <?php foreach ($sec_projected as $pid => $project):
                            $quotes_list = $project['quotes'];
                            $first_q = $quotes_list[0];
                        ?>
                            <!-- Project Header Row -->
                            <tr class="project-header-row has-sub" data-pid="<?= $sec['key'] ?>_p_<?= $pid ?>">
                                <td class="text-center" colspan="9">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-plus-square text-gold toggle-quotes" style="cursor: pointer; font-size: 1.1rem;"></i>
                                        <span class="fw-bold text-dark">ชื่อโครงการ: <?= htmlspecialchars($project['project_name']) ?></span>
                                        <span class="badge bg-gold text-white rounded-pill" style="font-size: 0.65rem;"><?= count($quotes_list) ?> ใบ</span>
                                        <span class="text-muted small ms-2"><?= htmlspecialchars($first_q['cust_name']) ?></span>
                                        <?php if (!empty($first_q['cust_contact_name'])): ?>
                                            <span class="text-muted small ms-2"><i class="bi bi-person me-1"></i><?= htmlspecialchars($first_q['cust_contact_name']) ?></span>
                                        <?php endif; ?>
                                        <a href="add_quote.php?project_id=<?= $pid ?>" class="btn btn-sm btn-outline-dark ms-auto" title="เพิ่มใบเสนอราคาในโครงการนี้">
                                            <i class="bi bi-plus-circle"></i> เพิ่ม
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($quotes_list as $q):
                                $st = $status_map[$q['status']] ?? $status_map['Draft'];
                                $wf = $workflow_statuses[$q['workflow_status']] ?? $workflow_statuses['Draft'];
                            ?>
                                <tr class="quote-sub-row bg-light" data-parent-pid="<?= $sec['key'] ?>_p_<?= $pid ?>" style="display:none">
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
                                        <?php if (!empty($q['sales_name'])): ?>
                                            <div class="small" style="color: #b89441;"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($q['sales_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-dark"><?= number_format($q['grand_total'], 2) ?></td>
                                    <td class="text-center">
                                        <span class="badge border <?= $st['class'] ?> px-3 py-2"><?= $st['text'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_admin_or_gm || intval($q['created_by']) === $current_user_id): ?>
                                        <select class="form-select form-select-sm wf-status-select <?= $wf['class'] ?>" 
                                                style="font-size: 0.72rem; padding: 3px 26px 3px 8px; border-radius: 20px; width: auto; display: inline-block; border: 1px solid rgba(0,0,0,0.1); cursor: pointer; background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 6px center;"
                                                data-id="<?= $q['id'] ?>" onchange="updateWorkflowStatus(this)">
                                            <?php foreach (array_keys($workflow_statuses) as $ws): ?>
                                                <option value="<?= $ws ?>" <?= ($q['workflow_status'] ?? 'Draft') === $ws ? 'selected' : '' ?>><?= $ws ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php else: ?>
                                            <span class="badge <?= $wf['class'] ?> px-2 py-1" style="font-size: 0.72rem;"><?= $q['workflow_status'] ?? 'Draft' ?></span>
                                        <?php endif; ?>
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
                                                <?php if ($role === 'admin' || intval($q['created_by']) === $current_user_id): ?>
                                                <a href="edit_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></a>
                                                <?php endif; ?>
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="d-md-none p-2">
            <?php foreach ($sections as $sec):
                $sec_ungrouped = $sec['data']['ungrouped'];
                $sec_projected = $sec['data']['by_project'];
            ?>
                <!-- Section Header Mobile -->
                <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
                    <div class="card-body p-3 <?= $sec['header_class'] ?>">
                        <div class="d-flex align-items-center gap-2 fw-bold" style="font-size: 0.95rem;">
                            <i class="bi <?= $sec['icon'] ?>"></i>
                            <span><?= $sec['title'] ?></span>
                            <span class="badge <?= $sec['badge_class'] ?> rounded-pill" style="font-size: 0.7rem;"><?= $sec['count'] ?> ใบ</span>
                        </div>
                    </div>
                </div>

                <!-- Ungrouped mobile -->
                <?php if (!empty($sec_ungrouped)): ?>
                    <div class="card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-bold text-muted"><i class="bi bi-file-earmark-text me-1"></i>ใบเสนอราคาที่ไม่มีโครงการ (<?= count($sec_ungrouped) ?>)</div>
                                <i class="bi bi-chevron-down text-muted toggle-ungrouped-mobile-<?= $sec['key'] ?>" style="cursor: pointer; font-size: 1.2rem;"></i>
                            </div>
                            <div id="mobile-ungrouped-<?= $sec['key'] ?>" style="display:none">
                                <?php foreach ($sec_ungrouped as $q):
                                    $st = $status_map[$q['status']] ?? $status_map['Draft'];
                                    $wf = $workflow_statuses[$q['workflow_status']] ?? $workflow_statuses['Draft'];
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
                                            <div class="mb-2">
                                                <?php if ($is_admin_or_gm || intval($q['created_by']) === $current_user_id): ?>
                                                <select class="form-select form-select-sm wf-status-select <?= $wf['class'] ?>"
                                                        style="font-size: 0.72rem; padding: 3px 26px 3px 8px; border-radius: 20px; width: auto; display: inline-block; border: 1px solid rgba(0,0,0,0.1); cursor: pointer; background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 6px center;"
                                                        data-id="<?= $q['id'] ?>" onchange="updateWorkflowStatus(this)">
                                                    <?php foreach (array_keys($workflow_statuses) as $ws): ?>
                                                        <option value="<?= $ws ?>" <?= ($q['workflow_status'] ?? 'Draft') === $ws ? 'selected' : '' ?>><?= $ws ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php else: ?>
                                                    <span class="badge <?= $wf['class'] ?> px-2 py-1" style="font-size: 0.72rem;"><?= $q['workflow_status'] ?? 'Draft' ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fw-bold text-dark mb-1">ชื่อลูกค้า : <?= $q['cust_name'] ?></div>
                                            <?php if (!empty($q['sales_name'])): ?>
                                                <div class="small mb-1" style="color: #b89441;"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($q['sales_name']) ?></div>
                                            <?php endif; ?>
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

                <?php foreach ($sec_projected as $pid => $project):
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
                                    <?php if (!empty($first_q['cust_contact_name'])): ?>
                                        <div class="text-muted small mt-1"><i class="bi bi-person me-1"></i><?= htmlspecialchars($first_q['cust_contact_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="add_quote.php?project_id=<?= $pid ?>" class="btn btn-sm btn-outline-dark" title="เพิ่มใบเสนอราคาในโครงการนี้">
                                        <i class="bi bi-plus-circle"></i>
                                    </a>
                                    <i class="bi bi-chevron-down text-gold toggle-mobile-quotes" data-pid="<?= $sec['key'] ?>_p_<?= $pid ?>" style="cursor: pointer; font-size: 1.2rem;"></i>
                                </div>
                            </div>

                            <div id="mobile-quotes-<?= $sec['key'] ?>_p_<?= $pid ?>" style="display:none">
                                <?php foreach ($quotes_list as $q):
                                    $st = $status_map[$q['status']] ?? $status_map['Draft'];
                                    $wf = $workflow_statuses[$q['workflow_status']] ?? $workflow_statuses['Draft'];
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
                                        <div class="mb-2">
                                            <?php if ($is_admin_or_gm || intval($q['created_by']) === $current_user_id): ?>
                                            <select class="form-select form-select-sm wf-status-select <?= $wf['class'] ?>"
                                                    style="font-size: 0.72rem; padding: 3px 26px 3px 8px; border-radius: 20px; width: auto; display: inline-block; border: 1px solid rgba(0,0,0,0.1); cursor: pointer; background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 6px center;"
                                                    data-id="<?= $q['id'] ?>" onchange="updateWorkflowStatus(this)">
                                                <?php foreach (array_keys($workflow_statuses) as $ws): ?>
                                                    <option value="<?= $ws ?>" <?= ($q['workflow_status'] ?? 'Draft') === $ws ? 'selected' : '' ?>><?= $ws ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php else: ?>
                                                <span class="badge <?= $wf['class'] ?> px-2 py-1" style="font-size: 0.72rem;"><?= $q['workflow_status'] ?? 'Draft' ?></span>
                                            <?php endif; ?>
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
                                                    <?php if ($role === 'admin' || intval($q['created_by']) === $current_user_id): ?>
                                                    <a href="edit_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <?php endif; ?>
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

        // Toggle ungrouped mobile (dynamic per section)
        $(document).on('click', '[class*="toggle-ungrouped-mobile-"]', function () {
            const key = this.className.match(/toggle-ungrouped-mobile-(\w+)/)?.[1];
            if (!key) return;
            const container = $(`#mobile-ungrouped-${key}`);
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

    const wfColors = {
        'Draft':                        { bg: '#6c757d', text: '#fff' },
        'ส่งใบเสนอราคาแล้ว':            { bg: '#0dcaf0', text: '#055160' },
        'Follow Up ครั้งที่ 1':         { bg: '#0d6efd', text: '#fff' },
        'Follow Up ครั้งที่ 2':         { bg: '#0d6efd', text: '#fff' },
        'Follow Up ครั้งที่ 3':         { bg: '#0d6efd', text: '#fff' },
        'ลูกค้าต่อรองราคา':             { bg: '#ffc107', text: '#664d03' },
        'รออนุมัติส่วนลด':              { bg: '#ffc107', text: '#664d03' },
        'ส่งใบเสนอราคาใหม่ (Revision)': { bg: '#0dcaf0', text: '#055160' },
        'ลูกค้าเซ็นยืนยัน':             { bg: '#198754', text: '#fff' },
        'รับเงินมัดจำแล้ว':             { bg: '#198754', text: '#fff' },
        'เปิด Function (BEO)':          { bg: '#0d6efd', text: '#fff' },
        'Lost Sale':                    { bg: '#dc3545', text: '#fff' },
        'Cancelled':                    { bg: '#dc3545', text: '#fff' },
    };

    function styleWfSelect(el) {
        const val = el.value;
        const c = wfColors[val] || wfColors['Draft'];
        el.style.backgroundColor = c.bg;
        el.style.color = c.text;
        el.style.fontWeight = '600';
    }

    document.querySelectorAll('.wf-status-select').forEach(el => styleWfSelect(el));

    function updateWorkflowStatus(el) {
        const id = el.dataset.id;
        const status = el.value;
        styleWfSelect(el);

        $.ajax({
            url: 'api/update_quote_status.php',
            type: 'POST',
            data: { id: id, workflow_status: status },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    showToast('อัปเดตสถานะเป็น: ' + status);
                } else {
                    Swal.fire('ผิดพลาด!', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('ผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
            }
        });
    }

    function showToast(msg) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `<div class="toast show align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div></div>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>
<?php include "footer.php"; ?>
