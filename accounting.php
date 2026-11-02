<?php
include "config.php";
include "header.php";

// หน้าบัญชี: ดูใบเสนอราคาทั้งหมดแยกตามสถานะอนุมัติ (อ่านอย่างเดียว ไม่มีปุ่มแก้ไข/อนุมัติ)
access_control(['admin', 'staff', 'gm', 'manager', 'procurement', 'account', 'accounting']);

$role = strtolower($_SESSION['role'] ?? '');
$can_finance = in_array($role, ['admin', 'staff', 'account', 'accounting']); // ต้องตรงกับสิทธิ์ใน finance.php โหมดใบเสนอราคา

// ---------- ตัวกรอง ----------
$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'approved', 'all'], true)) $tab = 'pending';

$search    = trim($_GET['q'] ?? '');
$date_from = $_GET['from'] ?? '';
$date_to   = $_GET['to'] ?? '';
$date_by   = ($_GET['date_by'] ?? 'event') === 'created' ? 'created' : 'event';
$show_cancelled = ($_GET['cancelled'] ?? '0') === '1';
$is_date = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

$where = [];
if (!$show_cancelled) $where[] = "q.status <> 'Cancelled'";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where[] = "(q.quote_no LIKE '%$s%' OR c.cust_name LIKE '%$s%' OR c.cust_contact_name LIKE '%$s%' OR q.event_name LIKE '%$s%' OR p.project_name LIKE '%$s%' OR c.sales_name LIKE '%$s%')";
}
$date_col = $date_by === 'created' ? 'DATE(q.created_at)' : 'q.event_date';
if ($is_date($date_from)) $where[] = "$date_col >= '$date_from'";
if ($is_date($date_to))   $where[] = "$date_col <= '$date_to'";
$base_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- สรุปยอด (นับจากตัวกรองเดียวกัน ไม่ขึ้นกับแท็บ) ----------
$sum_sql = "SELECT
                SUM(q.status <> 'Approved') AS cnt_pending,
                SUM(q.status = 'Approved')  AS cnt_approved,
                COALESCE(SUM(CASE WHEN q.status <> 'Approved' THEN q.grand_total END), 0) AS amt_pending,
                COALESCE(SUM(CASE WHEN q.status = 'Approved'  THEN q.grand_total END), 0) AS amt_approved
            FROM quotations q
            LEFT JOIN customers c ON q.customer_id = c.id
            LEFT JOIN event_projects p ON q.project_id = p.id
            $base_where";
$sum = $conn->query($sum_sql)->fetch_assoc() ?: [];
$cnt_pending  = (int)($sum['cnt_pending'] ?? 0);
$cnt_approved = (int)($sum['cnt_approved'] ?? 0);
$amt_pending  = (float)($sum['amt_pending'] ?? 0);
$amt_approved = (float)($sum['amt_approved'] ?? 0);

// ---------- รายการตามแท็บ + แบ่งหน้า ----------
$tab_where = $where;
if ($tab === 'pending')  $tab_where[] = "q.status <> 'Approved'";
if ($tab === 'approved') $tab_where[] = "q.status = 'Approved'";
$tab_where_sql = $tab_where ? 'WHERE ' . implode(' AND ', $tab_where) : '';

$per_page = 50;
$total_rows = $tab === 'pending' ? $cnt_pending : ($tab === 'approved' ? $cnt_approved : $cnt_pending + $cnt_approved);
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $total_pages);
$offset = ($page - 1) * $per_page;

// มัดจำที่รับแล้ว: รายการ deposit ที่ผูกกับใบเสนอราคาโดยตรง หรือกับ EO ที่แปลงมาจากใบนี้
$list_sql = "SELECT q.id, q.quote_no, q.event_name, q.event_date, q.expiry_date, q.created_at,
                    q.subtotal, q.service_charge, q.discount, q.vat, q.grand_total, q.vat_type,
                    q.status, q.workflow_status, q.approved_at, q.is_selected,
                    c.cust_name, c.cust_contact_name, c.sales_name, p.project_name,
                    ua.name AS approver_name,
                    (SELECT COALESCE(SUM(ff.amount), 0) FROM function_finance ff
                      WHERE ff.type = 'deposit'
                        AND (ff.quotation_id = q.id
                             OR ff.function_id IN (SELECT fn.id FROM functions fn WHERE fn.quotation_id = q.id))
                    ) AS deposit_total
             FROM quotations q
             LEFT JOIN customers c ON q.customer_id = c.id
             LEFT JOIN event_projects p ON q.project_id = p.id
             LEFT JOIN users ua ON q.approved_by = ua.id
             $tab_where_sql
             ORDER BY " . ($tab === 'approved' ? 'COALESCE(q.approved_at, q.updated_at) DESC,' : '') . " q.id DESC
             LIMIT $per_page OFFSET $offset";
$res = $conn->query($list_sql);
$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

$status_map = [
    'Draft'     => ['bg-secondary-subtle text-secondary', 'ฉบับร่าง'],
    'Sent'      => ['bg-info-subtle text-info', 'ส่งแล้ว'],
    'Approved'  => ['bg-success-subtle text-success', 'อนุมัติแล้ว'],
    'Cancelled' => ['bg-danger-subtle text-danger', 'ยกเลิก'],
];
$fmt_date = fn($d) => !empty($d) ? date('d/m/Y', strtotime($d)) : '-';
$qs = function (array $override) {
    $q = array_merge($_GET, $override);
    return '?' . htmlspecialchars(http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null)));
};
?>

<style>
    .ac-hero{border-radius:16px;padding:15px 20px;background:#fff;border:1px solid #e8eaee;border-left:4px solid #b89441}
    .ac-tab{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;border:2px solid #e8eaee;
        background:#fff;text-decoration:none;color:#111318;flex:1 1 0;transition:transform .15s,box-shadow .15s,border-color .15s}
    .ac-tab:hover{border-color:var(--c);transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.08)}
    .ac-tab i{font-size:var(--fs-xl);color:var(--c)}
    .ac-tab .lbl{font-size:var(--fs-xs);font-weight:700;color:#5b6470}
    .ac-tab .cnt{font-size:var(--fs-xl);font-weight:800;line-height:1.2}
    .ac-tab .amt{font-size:var(--fs-xs);color:#5b6470}
    .ac-tab.active{border-color:var(--c);background:var(--c)}
    .ac-tab.active i,.ac-tab.active .lbl,.ac-tab.active .cnt,.ac-tab.active .amt{color:var(--t)}
    @media (max-width:575px){.ac-tabs{flex-direction:column}}
</style>

<div class="container-fluid p-0">
    <div class="ac-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-calculator me-2" style="color:#b89441"></i>บัญชี — ใบเสนอราคา</h5>
                <div class="text-muted small">กรองใบเสนอราคาที่อนุมัติแล้ว / ยังไม่อนุมัติ สำหรับฝ่ายบัญชี</div>
            </div>
        </div>

        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1">ค้นหา</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>"
                    placeholder="เลขที่, ลูกค้า, ผู้ติดต่อ, ชื่องาน, โครงการ, เซลล์...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">กรองวันที่ตาม</label>
                <select name="date_by" class="form-select form-select-sm">
                    <option value="event" <?= $date_by === 'event' ? 'selected' : '' ?>>วันจัดงาน</option>
                    <option value="created" <?= $date_by === 'created' ? 'selected' : '' ?>>วันที่ออกใบ</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">ตั้งแต่</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">ถึง</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-dark btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>กรอง</button>
                <a href="accounting.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary btn-sm" title="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="col-12">
                <div class="form-check form-check-inline small mb-0">
                    <input class="form-check-input" type="checkbox" name="cancelled" value="1" id="chkCancelled"
                        <?= $show_cancelled ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label" for="chkCancelled">รวมใบที่ยกเลิก</label>
                </div>
            </div>
        </form>
    </div>

    <div class="d-flex gap-3 mb-3 ac-tabs">
        <a href="<?= $qs(['tab' => 'pending', 'page' => null]) ?>" class="ac-tab <?= $tab === 'pending' ? 'active' : '' ?>" style="--c:#ffc107;--t:#664d03">
            <i class="bi bi-hourglass-split"></i>
            <div><div class="lbl">ยังไม่อนุมัติ</div><div class="cnt"><?= number_format($cnt_pending) ?> ใบ</div><div class="amt">฿<?= number_format($amt_pending, 2) ?></div></div>
        </a>
        <a href="<?= $qs(['tab' => 'approved', 'page' => null]) ?>" class="ac-tab <?= $tab === 'approved' ? 'active' : '' ?>" style="--c:#198754;--t:#fff">
            <i class="bi bi-check-circle-fill"></i>
            <div><div class="lbl">อนุมัติแล้ว</div><div class="cnt"><?= number_format($cnt_approved) ?> ใบ</div><div class="amt">฿<?= number_format($amt_approved, 2) ?></div></div>
        </a>
        <a href="<?= $qs(['tab' => 'all', 'page' => null]) ?>" class="ac-tab <?= $tab === 'all' ? 'active' : '' ?>" style="--c:#0d6efd;--t:#fff">
            <i class="bi bi-collection"></i>
            <div><div class="lbl">ทั้งหมด</div><div class="cnt"><?= number_format($cnt_pending + $cnt_approved) ?> ใบ</div><div class="amt">฿<?= number_format($amt_pending + $amt_approved, 2) ?></div></div>
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive p-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>วันที่ออก</th>
                        <th>วันจัดงาน</th>
                        <th>ลูกค้า / งาน</th>
                        <th class="text-end">ก่อน VAT</th>
                        <th class="text-end">VAT</th>
                        <th class="text-end">ยอดสุทธิ</th>
                        <th class="text-end">มัดจำรับแล้ว</th>
                        <th class="text-center">สถานะ</th>
                        <th>อนุมัติโดย</th>
                        <th class="text-center">ดู</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-1"></i>ไม่พบใบเสนอราคา</td></tr>
                    <?php endif; ?>
                    <?php
                    $page_total = 0; $page_deposit = 0;
                    foreach ($rows as $r):
                        [$st_class, $st_text] = $status_map[$r['status']] ?? $status_map['Draft'];
                        $page_total += (float)$r['grand_total'];
                        $page_deposit += (float)$r['deposit_total'];
                        $before_vat = (float)$r['grand_total'] - (float)$r['vat'];
                        ?>
                        <tr>
                            <td class="fw-bold text-primary text-nowrap">
                                <?= htmlspecialchars($r['quote_no'] ?? '') ?>
                                <?php if ($r['is_selected']): ?><span class="badge bg-primary ms-1" style="font-size:var(--fs-xs)">SELECTED</span><?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $fmt_date($r['created_at']) ?></td>
                            <td class="text-nowrap"><?= $fmt_date($r['event_date']) ?><?= (!empty($r['expiry_date']) && $r['expiry_date'] !== $r['event_date']) ? ' - ' . $fmt_date($r['expiry_date']) : '' ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($r['cust_name'] ?? '-') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($r['event_name'] ?: '-') ?><?= $r['project_name'] ? ' · ' . htmlspecialchars($r['project_name']) : '' ?></div>
                                <?php if (!empty($r['sales_name'])): ?>
                                    <div class="small" style="color:#b89441"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($r['sales_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap"><?= number_format($before_vat, 2) ?></td>
                            <td class="text-end text-nowrap"><?= number_format((float)$r['vat'], 2) ?></td>
                            <td class="text-end fw-bold text-nowrap"><?= number_format((float)$r['grand_total'], 2) ?></td>
                            <td class="text-end text-nowrap <?= $r['deposit_total'] > 0 ? 'text-success fw-semibold' : 'text-muted' ?>"><?= number_format((float)$r['deposit_total'], 2) ?></td>
                            <td class="text-center">
                                <span class="badge border <?= $st_class ?> px-2 py-1"><?= $st_text ?></span>
                                <?php if (!empty($r['workflow_status']) && $r['workflow_status'] !== 'Draft'): ?>
                                    <div class="text-muted mt-1" style="font-size:var(--fs-xs)"><?= htmlspecialchars($r['workflow_status']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($r['status'] === 'Approved'): ?>
                                    <?= htmlspecialchars($r['approver_name'] ?: '-') ?>
                                    <?php if (!empty($r['approved_at'])): ?><div class="text-muted"><?= date('d/m/Y H:i', strtotime($r['approved_at'])) ?></div><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="quotation_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" title="ดู/พิมพ์ใบเสนอราคา"><i class="bi bi-printer"></i></a>
                                <?php if ($can_finance): ?>
                                    <a href="finance.php?quote_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-warning" title="บัญชี/เงินมัดจำ"><i class="bi bi-cash-coin"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="6" class="text-end">รวมหน้านี้ (<?= count($rows) ?> ใบ)</td>
                            <td class="text-end"><?= number_format($page_total, 2) ?></td>
                            <td class="text-end text-success"><?= number_format($page_deposit, 2) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="px-3 pb-3">
                <ul class="pagination pagination-sm mb-0 flex-wrap">
                    <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $qs(['page' => $p]) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item disabled"><span class="page-link">จาก <?= $total_pages ?> หน้า</span></li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>
