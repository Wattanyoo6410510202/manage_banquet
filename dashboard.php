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

$company_filter = $selected_company > 0 ? "AND f.company_id = $selected_company" : "";
$date_filter = "AND MONTH(f.created_at) = $selected_month AND YEAR(f.created_at) = $selected_year";

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");

$total_revenue = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter $date_filter")->fetch_assoc()['total'];
$total_events = $conn->query("SELECT COUNT(*) as cnt FROM functions f WHERE f.status != 'Cancelled' $company_filter $date_filter")->fetch_assoc()['cnt'];
$total_deposit = $conn->query("SELECT COALESCE(SUM(f.deposit), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') $company_filter $date_filter")->fetch_assoc()['total'];
$pending_approval = $conn->query("SELECT COUNT(*) as cnt FROM functions f WHERE f.approve = 0 AND f.status != 'Cancelled' $company_filter $date_filter")->fetch_assoc()['cnt'];

$staff_sales = $conn->query("SELECT
    u.id, u.name,
    COUNT(f.id) as total_events,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled') THEN f.total_amount ELSE 0 END), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') THEN f.deposit ELSE 0 END), 0) as total_deposit,
    COALESCE(SUM(CASE WHEN f.approve = 0 AND f.status != 'Cancelled' THEN f.total_amount ELSE 0 END), 0) as pending_revenue,
    COALESCE((SELECT st.target_amount FROM sales_targets st WHERE st.user_id = u.id AND st.target_month = $selected_month AND st.target_year = $selected_year LIMIT 1), 0) as target_amount
    FROM users u
    LEFT JOIN functions f ON f.created_by_id = u.id $company_filter $date_filter
    WHERE u.role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM')
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
for ($i = 5; $i >= 0; $i--) {
    $m = date('m', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $row = $conn->query("SELECT COALESCE(SUM(f.total_amount), 0) as total FROM functions f WHERE f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter AND MONTH(f.created_at) = $m AND YEAR(f.created_at) = $y")->fetch_assoc();
    $monthly_revenue[] = [
        'label' => date('M', strtotime("-$i months")),
        'total' => (float)$row['total']
    ];
}

$upcoming = $conn->query("SELECT f.id, f.function_name, f.start_time, f.end_time, c.company_name, r.room_name, u.name as staff_name
    FROM functions f
    LEFT JOIN companies c ON f.company_id = c.id
    LEFT JOIN meeting_rooms r ON f.room_id = r.id
    LEFT JOIN users u ON f.created_by_id = u.id
    WHERE f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time >= NOW()
    $company_filter
    ORDER BY f.start_time ASC LIMIT 10");

$top_staff = $conn->query("SELECT u.name, COALESCE(SUM(f.total_amount), 0) as revenue
    FROM users u
    LEFT JOIN functions f ON f.created_by_id = u.id AND f.approve = 1 AND f.status NOT IN ('Cancelled') $company_filter AND MONTH(f.created_at) = $selected_month AND YEAR(f.created_at) = $selected_year
    WHERE u.role IN ('Staff', 'Admin', 'Sale', 'Manager', 'GM')
    GROUP BY u.id, u.name
    HAVING revenue > 0
    ORDER BY revenue DESC LIMIT 5");
$top_staff_name = []; $top_staff_val = [];
while ($t = $top_staff->fetch_assoc()) {
    $top_staff_name[] = $t['name'];
    $top_staff_val[] = (float)$t['revenue'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid p-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-gold me-2"></i>แดชบอร์ดขาย</h4>
            <small class="text-muted">ภาพรวมยอดขายพนักงาน ประจำเดือน <?= date('F', mktime(0, 0, 0, $selected_month, 1)) ?> <?= $selected_year + 543 ?></small>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap" id="companyFilter">
            <label class="small text-muted fw-bold">เดือน</label>
            <select name="month" class="form-select form-select-sm" style="width:130px">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
                <?php endfor; ?>
            </select>
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
            <?php if ($selected_company > 0 || $selected_month != date('m') || $selected_year != date('Y')): ?>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success-subtle rounded-3 p-3">
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">รายได้รวม</div>
                            <div class="fw-bold fs-4">฿<?= number_format($total_revenue, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle rounded-3 p-3">
                            <i class="bi bi-calendar-check text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">งานทั้งหมด</div>
                            <div class="fw-bold fs-4"><?= $total_events ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info-subtle rounded-3 p-3">
                            <i class="bi bi-wallet text-info fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">เงินมัดจำ</div>
                            <div class="fw-bold fs-4">฿<?= number_format($total_deposit, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border shadow-sm h-100 dashboard-stat-card">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning-subtle rounded-3 p-3">
                            <i class="bi bi-clock text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">รออนุมัติ</div>
                            <div class="fw-bold fs-4"><?= $pending_approval ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people text-gold me-2"></i>ยอดขายพนักงาน <?= date('F', mktime(0, 0, 0, $selected_month, 1)) ?> <?= $selected_year + 543 ?></h6>
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
                                    <tr><td colspan="7" class="text-center text-muted py-4 small">ไม่มีข้อมูลยอดขายในเดือนนี้</td></tr>
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
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-gold me-2"></i>แนวโน้มรายได้ 6 เดือน</h6>
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

    <div class="row g-3">
        <div class="col-12">
            <div class="card border shadow-sm">
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
</script>

<?php include "footer.php"; ?>
