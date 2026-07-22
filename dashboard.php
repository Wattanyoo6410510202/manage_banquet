<?php
include "config.php";
include "header.php";

$role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'staff', 'gm', 'sale', 'procurement'];
if (!in_array($role, $allowed_roles)) {
    echo "<script>window.location.href='access_denied.php';</script>";
    exit;
}

$selected_company = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$selected_staff = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
if ($role === 'staff' && $selected_staff === 0) {
    $selected_staff = intval($_SESSION['user_id'] ?? 0);
}
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'monthly';

if ($view_mode === 'yearly') $selected_month = 0;
if ($view_mode === 'monthly' && $selected_month === 0) $selected_month = intval(date('m'));

$company_filter = $selected_company > 0 ? "AND f.company_id = $selected_company" : "";
$staff_filter = $selected_staff > 0 ? "AND f.created_by_id = $selected_staff" : "";
$staff_filter_user = $selected_staff > 0 ? "AND u.id = $selected_staff" : "";
$staff_filter_plain = $selected_staff > 0 ? "AND created_by_id = $selected_staff" : "";
$is_yearly = ($view_mode === 'yearly');
$date_filter = $is_yearly
    ? "AND YEAR(f.created_at) = $selected_year"
    : "AND MONTH(f.created_at) = $selected_month AND YEAR(f.created_at) = $selected_year";

$date_filter_plain = $is_yearly
    ? "AND YEAR(created_at) = $selected_year"
    : "AND MONTH(created_at) = $selected_month AND YEAR(created_at) = $selected_year";

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$staff_list = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM') ORDER BY name ASC");

$total_revenue = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter")->fetch_assoc()['total'];
$total_events = $conn->query("SELECT COUNT(*) as cnt FROM functions f WHERE f.status != 'Cancelled' $company_filter $staff_filter $date_filter")->fetch_assoc()['cnt'];
$total_deposit = $conn->query("SELECT COALESCE(SUM(f.deposit), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') $company_filter $staff_filter $date_filter")->fetch_assoc()['total'];
$pending_approval = $conn->query("SELECT COUNT(*) as cnt FROM functions f WHERE f.approve = 0 AND f.status != 'Cancelled' $company_filter $staff_filter $date_filter")->fetch_assoc()['cnt'];

$staff_sales = $conn->query("SELECT
    u.id, u.name,
    COUNT(f.id) as total_events,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled') THEN f.total_amount ELSE 0 END), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') THEN f.deposit ELSE 0 END), 0) as total_deposit,
    COALESCE(SUM(CASE WHEN f.approve = 0 AND f.status != 'Cancelled' THEN f.total_amount ELSE 0 END), 0) as pending_revenue,
    COALESCE((SELECT SUM(st.target_amount) FROM sales_targets st WHERE st.user_id = u.id AND st.target_year = $selected_year" . ($is_yearly ? "" : " AND st.target_month = $selected_month") . "), 0) as target_amount
    FROM users u
    LEFT JOIN functions f ON f.created_by_id = u.id $company_filter $date_filter
    WHERE u.role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM') $staff_filter_user
    GROUP BY u.id, u.name
    HAVING total_events > 0 OR target_amount > 0
    ORDER BY total_revenue DESC");

$staff_chart_labels = [];
$staff_chart_revenue = [];
$staff_chart_target = [];
$staff_data = [];
while ($s = $staff_sales->fetch_assoc()) {
    $staff_chart_labels[] = $s['name'];
    $staff_chart_revenue[] = (float)$s['total_revenue'];
    $staff_chart_target[] = (float)$s['target_amount'];
    $staff_data[] = $s;
}

$monthly_revenue = [];
if ($is_yearly) {
    for ($m = 1; $m <= 12; $m++) {
        $row = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter AND MONTH(f.created_at) = $m AND YEAR(f.created_at) = $selected_year")->fetch_assoc();
        $monthly_revenue[] = [
            'label' => date('F', mktime(0, 0, 0, $m, 1)),
            'total' => (float)$row['total']
        ];
    }
} else {
    for ($i = 5; $i >= 0; $i--) {
        $m = date('m', strtotime("-$i months"));
        $y = date('Y', strtotime("-$i months"));
        $row = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter AND MONTH(f.created_at) = $m AND YEAR(f.created_at) = $y")->fetch_assoc();
        $monthly_revenue[] = [
            'label' => date('M', strtotime("-$i months")),
            'total' => (float)$row['total']
        ];
    }
}

$yearly_revenue = [];
for ($y = date('Y') - 4; $y <= date('Y'); $y++) {
    $row = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter AND YEAR(f.created_at) = $y")->fetch_assoc();
    $yearly_revenue[] = [
        'label' => ($y + 543),
        'year' => $y,
        'total' => (float)$row['total']
    ];
}

$upcoming = $conn->query("SELECT f.id, f.function_name, f.start_time, f.end_time, c.company_name, r.room_name, u.name as staff_name
    FROM functions f
    LEFT JOIN companies c ON f.company_id = c.id
    LEFT JOIN meeting_rooms r ON f.room_id = r.id
    LEFT JOIN users u ON f.created_by_id = u.id
    WHERE f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time >= NOW()
    $company_filter $staff_filter
    ORDER BY f.start_time ASC LIMIT 10");

$top_staff = $conn->query("SELECT u.name, COALESCE(SUM(f.total_amount), 0) as revenue
    FROM users u
    LEFT JOIN functions f ON f.created_by_id = u.id AND f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter " . ($is_yearly ? "AND YEAR(f.created_at) = $selected_year" : "AND MONTH(f.created_at) = $selected_month AND YEAR(f.created_at) = $selected_year") . "
    WHERE u.role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM')
    GROUP BY u.id, u.name
    HAVING revenue > 0
    ORDER BY revenue DESC LIMIT 5");
$top_staff_name = []; $top_staff_val = [];
while ($t = $top_staff->fetch_assoc()) {
    $top_staff_name[] = $t['name'];
    $top_staff_val[] = (float)$t['revenue'];
}

/* ---- New Dashboard Queries ---- */

$avg_deal_size = $total_events > 0 ? round($total_revenue / $total_events) : 0;

$team_target = $conn->query("SELECT COALESCE(SUM(st.target_amount), 0) as total FROM sales_targets st WHERE st.target_year = $selected_year" . ($is_yearly ? "" : " AND st.target_month = $selected_month") . ($selected_staff > 0 ? " AND st.user_id = $selected_staff" : ""))->fetch_assoc()['total'];
$team_target_pct = $team_target > 0 ? round(($total_revenue / $team_target) * 100, 1) : 0;

$company_filter_short = $selected_company > 0 ? "AND company_id = $selected_company" : "";

$rev_by_type = $conn->query("SELECT ft.type_name, COALESCE(SUM(f.total_amount), 0) as total
    FROM functions f
    JOIN function_types ft ON f.function_type_id = ft.id
    WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter
    GROUP BY ft.id, ft.type_name
    ORDER BY total DESC");
$type_names = []; $type_totals = [];
while ($rt = $rev_by_type->fetch_assoc()) {
    $type_names[] = $rt['type_name'];
    $type_totals[] = (float)$rt['total'];
}

$rev_by_company = $conn->query("SELECT c.company_name, COALESCE(SUM(f.total_amount), 0) as total
    FROM functions f
    JOIN companies c ON f.company_id = c.id
    WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $staff_filter $date_filter
    GROUP BY c.id, c.company_name
    ORDER BY total DESC");
$comp_names = []; $comp_totals = [];
while ($rc = $rev_by_company->fetch_assoc()) {
    $comp_names[] = $rc['company_name'];
    $comp_totals[] = (float)$rc['total'];
}

$lead_sources = $conn->query("SELECT COALESCE(NULLIF(lead_source, ''), 'ไม่ได้ระบุ') as source, COUNT(*) as cnt
    FROM functions f WHERE f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter
    GROUP BY source ORDER BY cnt DESC");
$source_labels = []; $source_counts = [];
while ($ls = $lead_sources->fetch_assoc()) {
    $source_labels[] = $ls['source'];
    $source_counts[] = (int)$ls['cnt'];
}

$period_count = $conn->query("SELECT
    CASE
        WHEN TIME(f.start_time) < '12:00:00' THEN 'เช้า'
        WHEN TIME(f.start_time) < '17:00:00' THEN 'บ่าย'
        ELSE 'เย็น'
    END as period,
    COUNT(*) as cnt
    FROM functions f WHERE f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter
    GROUP BY period ORDER BY cnt DESC");
$period_labels = []; $period_counts = [];
while ($pc = $period_count->fetch_assoc()) {
    $period_labels[] = $pc['period'];
    $period_counts[] = (int)$pc['cnt'];
}

$pending_list = $conn->query("SELECT f.id, f.function_name, f.start_time, f.total_amount, c.company_name, u.name as staff_name
    FROM functions f
    LEFT JOIN companies c ON f.company_id = c.id
    LEFT JOIN users u ON f.created_by_id = u.id
    WHERE f.approve = 0 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter
    ORDER BY f.start_time ASC LIMIT 5");

$expiring_quotes = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.expiry_date, q.grand_total, c.company_name
    FROM quotations q
    LEFT JOIN companies c ON q.company_id = c.id
    WHERE q.status = 'Sent' AND q.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY q.expiry_date ASC LIMIT 5");

$repeat_data = $conn->query("SELECT
    COUNT(DISTINCT customer_id) as total_cust,
    SUM(CASE WHEN booking_count > 1 THEN 1 ELSE 0 END) as repeat_cust
    FROM (
        SELECT customer_id, COUNT(*) as booking_count
        FROM functions
        WHERE status NOT IN ('Cancelled') AND customer_id IS NOT NULL $company_filter_short $staff_filter_plain
        GROUP BY customer_id
    ) sub")->fetch_assoc();
$total_customers = (int)($repeat_data['total_cust'] ?? 0);
$repeat_customers = (int)($repeat_data['repeat_cust'] ?? 0);
$repeat_pct = $total_customers > 0 ? round(($repeat_customers / $total_customers) * 100, 1) : 0;

$quotes_total = $conn->query("SELECT COUNT(*) as cnt FROM quotations q WHERE q.status = 'Sent' $company_filter_short" . ($selected_staff > 0 ? " AND q.created_by = $selected_staff" : ""))->fetch_assoc()['cnt'];
$conversion_data = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN f.result = 'success' OR f.result LIKE '%สำเร็จ%' THEN 1 ELSE 0 END), 0) as won,
    COALESCE(SUM(CASE WHEN f.result = 'lost' OR f.result LIKE '%แพ้%' OR f.result LIKE '%เสีย%' THEN 1 ELSE 0 END), 0) as lost
    FROM functions f WHERE f.status NOT IN ('Cancelled') $company_filter $staff_filter $date_filter")->fetch_assoc();
$won = (int)($conversion_data['won'] ?? 0);
$lost = (int)($conversion_data['lost'] ?? 0);
$total_decided = $won + $lost;
$conversion_pct = $total_decided > 0 ? round(($won / $total_decided) * 100, 1) : 0;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid p-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-gold me-2"></i>แดชบอร์ดขาย</h4>
            <small class="text-muted">ภาพรวมยอดขายพนักงาน<?= $is_yearly ? ' ประจำปี' : ' ประจำเดือน' ?> <?= $is_yearly ? $selected_year + 543 : date('F', mktime(0, 0, 0, $selected_month, 1)) . ' ' . ($selected_year + 543) ?></small>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap" id="companyFilter">
            <label class="small text-muted fw-bold">ดูเป็น</label>
            <select name="view" class="form-select form-select-sm" style="width:100px" onchange="this.form.submit()">
                <option value="monthly" <?= $view_mode === 'monthly' ? 'selected' : '' ?>>รายเดือน</option>
                <option value="yearly" <?= $view_mode === 'yearly' ? 'selected' : '' ?>>รายปี</option>
            </select>
            <label class="small text-muted fw-bold">เดือน</label>
            <select name="month" class="form-select form-select-sm" style="width:130px">
                <option value="0" <?= $selected_month == 0 ? 'selected' : '' ?>>ทุกเดือน</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
                <?php endfor; ?>
            </select>
            <label class="small text-muted fw-bold">พนักงาน</label>
            <select name="staff_id" class="form-select form-select-sm" style="width:130px" <?= $role === 'staff' ? 'disabled' : '' ?>>
                <option value="0">ทั้งหมด</option>
                <?php
                $staff_list->data_seek(0);
                while ($s = $staff_list->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>" <?= $selected_staff == $s['id'] ? 'selected' : '' ?>><?= $s['name'] ?></option>
                <?php endwhile; ?>
            </select>
            <?php if ($role === 'staff'): ?><input type="hidden" name="staff_id" value="<?= $selected_staff ?>"><?php endif; ?>
            <label class="small text-muted fw-bold">โรงแรม</label>
            <select name="company_id" class="form-select form-select-sm" style="width:180px">
                <option value="0">ทั้งหมด</option>
                <?php
                $companies->data_seek(0);
                while ($c = $companies->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $selected_company == $c['id'] ? 'selected' : '' ?>><?= $c['company_name'] ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-filter"></i></button>
            <?php if ($selected_company > 0 || $selected_staff > 0 || $selected_month != intval(date('m')) || $selected_year != intval(date('Y')) || $view_mode != 'monthly'): ?>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="d-flex gap-2 mb-3 flex-wrap">
        <a href="add_event.php" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg"></i> เพิ่มงาน</a>
        <a href="add_quote.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-text"></i> เสนอราคา</a>
        <a href="customer.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-person-plus"></i> เพิ่มลูกค้า</a>
        <a href="sales_dept.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-bullseye"></i> ตั้งเป้าหมาย</a>
        <a href="manage_banquet.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-list-check"></i> ดูงานทั้งหมด</a>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-success-subtle rounded-3 p-2">
                            <i class="bi bi-cash-stack text-success"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">รายได้รวม</div>
                            <div class="fw-bold" style="font-size:14px">฿<?= number_format($total_revenue, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary-subtle rounded-3 p-2">
                            <i class="bi bi-calendar-check text-primary"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">งานทั้งหมด</div>
                            <div class="fw-bold" style="font-size:14px"><?= $total_events ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-info-subtle rounded-3 p-2">
                            <i class="bi bi-wallet text-info"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">เงินมัดจำ</div>
                            <div class="fw-bold" style="font-size:14px">฿<?= number_format($total_deposit, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-warning-subtle rounded-3 p-2">
                            <i class="bi bi-clock text-warning"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">รออนุมัติ</div>
                            <div class="fw-bold" style="font-size:14px"><?= $pending_approval ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-secondary-subtle rounded-3 p-2">
                            <i class="bi bi-graph-up-arrow text-secondary"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">เฉลี่ยต่องาน</div>
                            <div class="fw-bold" style="font-size:14px">฿<?= number_format($avg_deal_size, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-danger-subtle rounded-3 p-2">
                            <i class="bi bi-arrow-repeat text-danger"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">ลูกค้าซ้ำ</div>
                            <div class="fw-bold" style="font-size:14px"><?= $repeat_pct ?>%</div>
                            <div style="font-size:9px" class="text-muted"><?= $repeat_customers ?>/<?= $total_customers ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-success-subtle rounded-3 p-2">
                            <i class="bi bi-check-circle text-success"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">Conversion</div>
                            <div class="fw-bold" style="font-size:14px"><?= $conversion_pct ?>%</div>
                            <div style="font-size:9px" class="text-muted"><?= $won ?>/<?= $total_decided ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-2 px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary-subtle rounded-3 p-2">
                            <i class="bi bi-send text-primary"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="text-muted" style="font-size:10px">เสนอราคา (รอตอบ)</div>
                            <div class="fw-bold" style="font-size:14px"><?= $quotes_total ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($team_target > 0): ?>
    <div class="card border shadow-sm mb-4">
        <div class="card-body py-3 px-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <span class="fw-bold small"><i class="bi bi-bullseye text-gold me-1"></i>เป้าหมายรวมทีม</span>
                    <span class="text-muted small ms-2">฿<?= number_format($total_revenue, 0) ?> / ฿<?= number_format($team_target, 0) ?></span>
                </div>
                <span class="fw-bold <?= $team_target_pct >= 100 ? 'text-success' : ($team_target_pct >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $team_target_pct ?>%</span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar <?= $team_target_pct >= 100 ? 'bg-success' : ($team_target_pct >= 50 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= min($team_target_pct, 100) ?>%"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people text-gold me-2"></i>ยอดขายพนักงาน<?= $is_yearly ? ' ปี' : '' ?> <?= $is_yearly ? $selected_year + 543 : date('F', mktime(0, 0, 0, $selected_month, 1)) . ' ' . ($selected_year + 543) ?></h6>
                    <small class="text-muted">เรียงตามยอดขายสูงสุด</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>พนักงาน</th>
                                    <th class="text-center">จำนวนงาน</th>
                                    <th class="text-end">ยอดขาย</th>
                                    <th class="text-end">เงินมัดจำ</th>
                                    <th class="text-end">รออนุมัติ</th>
                                    <th class="text-end">เป้าหมาย</th>
                                    <th class="text-end">% ของเป้าหมาย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($staff_data) > 0): ?>
                                    <?php foreach ($staff_data as $s): ?>
                                    <?php
                                        $pct = $s['target_amount'] > 0 ? round(($s['total_revenue'] / $s['target_amount']) * 100, 1) : 0;
                                        $bar_color = $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <tr>
                                        <td class="fw-medium"><?= $s['name'] ?></td>
                                        <td class="text-center"><?= $s['total_events'] ?></td>
                                        <td class="text-end fw-bold text-primary">฿<?= number_format($s['total_revenue'], 0) ?></td>
                                        <td class="text-end">฿<?= number_format($s['total_deposit'], 0) ?></td>
                                        <td class="text-end text-muted">฿<?= number_format($s['pending_revenue'], 0) ?></td>
                                        <td class="text-end">฿<?= number_format($s['target_amount'], 0) ?></td>
                                        <td class="text-end" style="min-width:120px">
                                            <div class="d-flex align-items-center gap-2 justify-content-end">
                                                <span class="fw-bold <?= $pct >= 100 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $pct ?>%</span>
                                                <div class="progress" style="width:60px;height:6px">
                                                    <div class="progress-bar <?= $bar_color ?>" style="width:<?= min($pct, 100) ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4 small">ไม่มีข้อมูลยอดขาย<?= $is_yearly ? 'ในปีนี้' : 'ในเดือนนี้' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-gold me-2"></i>แนวโน้มรายได้<?= $is_yearly ? 'รายเดือน ' . ($selected_year + 543) : ' 6 เดือน' ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-trophy text-gold me-2"></i>อันดับพนักงานขายยอดเยี่ยม</h6>
                </div>
                <div class="card-body">
                    <?php if (count($top_staff_name) > 0): ?>
                    <canvas id="topStaffChart" height="180"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-5 small">ไม่มีข้อมูลยอดขาย</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_yearly): ?>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up text-gold me-2"></i>รายได้รวมย้อนหลัง 5 ปี</h6>
                </div>
                <div class="card-body">
                    <canvas id="yearlyChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-2 mb-4">
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-pie-chart text-gold me-1"></i>รายได้ตามประเภท</h6>
                </div>
                <div class="card-body py-1 px-2">
                    <?php if (count($type_names) > 0): ?>
                    <canvas id="typeChart" height="70"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-building text-gold me-1"></i>รายได้ตามโรงแรม</h6>
                </div>
                <div class="card-body py-1 px-2">
                    <?php if (count($comp_names) > 0): ?>
                    <canvas id="companyChart" height="70"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-diagram-3 text-gold me-1"></i>แหล่งที่มาลูกค้า</h6>
                </div>
                <div class="card-body py-1 px-2">
                    <?php if (count($source_labels) > 0): ?>
                    <canvas id="sourceChart" height="70"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-clock text-gold me-1"></i>เช้า/บ่าย/เย็น</h6>
                </div>
                <div class="card-body py-1 px-2">
                    <?php if (count($period_labels) > 0): ?>
                    <canvas id="periodChart" height="70"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-exclamation-triangle text-gold me-1"></i>รออนุมัติ</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($pending_list->num_rows > 0): ?>
                    <div class="list-group list-group-flush small" style="font-size:11px">
                        <?php while ($pl = $pending_list->fetch_assoc()): ?>
                        <a href="view.php?id=<?= $pl['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-1">
                            <span class="text-truncate me-1"><?= $pl['function_name'] ?></span>
                            <span class="badge bg-warning-subtle text-warning rounded-pill flex-shrink-0">฿<?= number_format($pl['total_amount'], 0) ?></span>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีงานรออนุมัติ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-1 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:11px"><i class="bi bi-file-text text-gold me-1"></i>เสนอราคาหมดอายุ</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($expiring_quotes->num_rows > 0): ?>
                    <div class="list-group list-group-flush small" style="font-size:11px">
                        <?php while ($eq = $expiring_quotes->fetch_assoc()): ?>
                        <a href="quotation_view.php?id=<?= $eq['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-1">
                            <span class="text-truncate me-1"><?= $eq['event_name'] ?: $eq['quote_no'] ?></span>
                            <small class="text-danger flex-shrink-0 fw-bold">฿<?= number_format($eq['grand_total'], 0) ?></small>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีใบเสนอราคา</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-12">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-calendar-week text-gold me-2"></i>งานที่กำลังจะมาถึง</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>ชื่องาน</th>
                                    <th>พนักงานขาย</th>
                                    <th>โรงแรม</th>
                                    <th>ห้อง</th>
                                    <th>วันที่</th>
                                    <th>เวลา</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($upcoming->num_rows > 0): ?>
                                    <?php while ($u = $upcoming->fetch_assoc()): ?>
                                    <tr>
                                        <td><a href="view.php?id=<?= $u['id'] ?>" class="text-decoration-none fw-medium"><?= $u['function_name'] ?></a></td>
                                        <td class="small"><span class="badge bg-dark-subtle text-dark"><?= $u['staff_name'] ?? '-' ?></span></td>
                                        <td class="small"><?= $u['company_name'] ?></td>
                                        <td class="small"><?= $u['room_name'] ?></td>
                                        <td class="small"><?= date('d M Y', strtotime($u['start_time'])) ?></td>
                                        <td class="small"><?= date('H:i', strtotime($u['start_time'])) ?> - <?= date('H:i', strtotime($u['end_time'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3 small">ไม่มีงานที่กำลังจะมาถึง</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
<?php if (count($monthly_revenue) > 0): ?>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($monthly_revenue as $m): ?>'<?= $m['label'] ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'รายได้ (บาท)',
            data: [<?php foreach ($monthly_revenue as $m): ?><?= $m['total'] ?>,<?php endforeach; ?>],
            backgroundColor: 'rgba(184, 148, 65, 0.7)',
            borderColor: '#b89441',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: function(v) { return '฿' + v.toLocaleString(); } }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($top_staff_name) > 0): ?>
new Chart(document.getElementById('topStaffChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($top_staff_name as $n): ?>'<?= $n ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'ยอดขาย (บาท)',
            data: [<?php foreach ($top_staff_val as $v): ?><?= $v ?>,<?php endforeach; ?>],
            backgroundColor: [
                'rgba(184, 148, 65, 0.8)',
                'rgba(108, 117, 125, 0.7)',
                'rgba(205, 92, 92, 0.6)',
                'rgba(92, 184, 184, 0.6)',
                'rgba(92, 120, 184, 0.6)'
            ],
            borderColor: '#b89441',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { callback: function(v) { return '฿' + v.toLocaleString(); } }
            }
        }
    }
});
<?php endif; ?>

<?php if ($is_yearly && count($yearly_revenue) > 0): ?>
new Chart(document.getElementById('yearlyChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($yearly_revenue as $yr): ?>'<?= $yr['label'] ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'รายได้รวม (บาท)',
            data: [<?php foreach ($yearly_revenue as $yr): ?><?= $yr['total'] ?>,<?php endforeach; ?>],
            backgroundColor: 'rgba(40, 167, 69, 0.7)',
            borderColor: '#28a745',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: function(v) { return '฿' + v.toLocaleString(); } }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($type_names) > 0): ?>
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($type_names as $tn): ?>'<?= $tn ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($type_totals as $tt): ?><?= $tt ?>,<?php endforeach; ?>],
            backgroundColor: [
                'rgba(184, 148, 65, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '75%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } }
        }
    }
});
<?php endif; ?>

<?php if (count($comp_names) > 0): ?>
new Chart(document.getElementById('companyChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($comp_names as $cn): ?>'<?= $cn ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'รายได้ (บาท)',
            data: [<?php foreach ($comp_totals as $ct): ?><?= $ct ?>,<?php endforeach; ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: '#36a2eb',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { callback: function(v) { return '฿' + v.toLocaleString(); } }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($source_labels) > 0): ?>
new Chart(document.getElementById('sourceChart'), {
    type: 'pie',
    data: {
        labels: [<?php foreach ($source_labels as $sl): ?>'<?= $sl ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($source_counts as $sc): ?><?= $sc ?>,<?php endforeach; ?>],
            backgroundColor: [
                'rgba(184, 148, 65, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(201, 203, 207, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } }
        }
    }
});
<?php endif; ?>

<?php if (count($period_labels) > 0): ?>
new Chart(document.getElementById('periodChart'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($period_labels as $pl): ?>'<?= $pl ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($period_counts as $pc): ?><?= $pc ?>,<?php endforeach; ?>],
            backgroundColor: [
                'rgba(255, 205, 86, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '75%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } }
        }
    }
});
<?php endif; ?>
</script>

<?php include "footer.php"; ?>
