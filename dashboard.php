<?php
include "header.php";
include "config.php";

// --- 1. สถิติจริงจากตาราง functions ---
$stats_res = $conn->query("SELECT 
    COUNT(id) as total_events,
    SUM(CASE WHEN approve = 0 THEN 1 ELSE 0 END) as pending_count,
    SUM(deposit) as total_revenue 
    FROM functions");
$stats = $stats_res->fetch_assoc();

// --- 2. รายชื่อบริษัท/โรงแรม ---
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">แดชบอร์ดภาพรวมการดำเนินงาน</h4>
            <small class="text-muted">ระบบบริหารจัดการงานจัดเลี้ยงและห้องประชุม</small>
        </div>
        <div class="col-md-3">
            <select class="form-select border-primary shadow-sm" id="companyFilter"
                onchange="loadRoomStatus(this.value)">
                <option value="all">-- ทุกโรงแรม --</option> 
                <?php while ($c = $companies->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                        <i class="bi bi-calendar-check fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">งานทั้งหมด</h6>
                        <h3 class="fw-bold mb-0" id="stat_total"><?= number_format($stats['total_events']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">รอการอนุมัติ</h6>
                        <h3 class="fw-bold mb-0 text-warning" id="stat_pending">
                            <?= number_format($stats['pending_count']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success bg-opacity-10 text-success p-3 rounded-3 me-3">
                        <i class="bi bi-currency-dollar fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">ยอดเงินมัดจำรวม</h6>
                        <h3 class="fw-bold mb-0 text-success" id="stat_revenue">
                            ฿<?= number_format($stats['total_revenue'], 2) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Performance Row -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>ผลการดำเนินงานของทีมขาย (ประจำเดือนนี้)</h6>
                    <a href="sales_dept.php" class="btn btn-sm btn-outline-primary border-0"><i class="bi bi-gear"></i> ตั้งค่าเป้าหมาย</a>
                </div>
                <div class="row g-4" id="salesPerformanceBody">
                    <div class="col-12 text-center py-3 text-muted">กำลังโหลดข้อมูลการขาย...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h6 class="fw-bold mb-4">รายได้รายเดือน (6 เดือน)</h6>
                <canvas id="revenueChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h6 class="fw-bold mb-4">สัดส่วนประเภทงาน</h6>
                <canvas id="typeChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h6 class="fw-bold mb-4">งานตามโรงแรม (Occupancy)</h6>
                <canvas id="occChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h6 class="fw-bold mb-4">ระยะเวลาอนุมัติเฉลี่ย (วัน)</h6>
                <canvas id="leadChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Sales Leaderboard and Room Status -->
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>อันดับยอดขายรายคน</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small">
                            <tr>
                                <th class="ps-3">พนักงาน</th>
                                <th class="text-end pe-3">ยอดขายรวม</th>
                            </tr>
                        </thead>
                        <tbody id="salesLeaderboardBody">
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted small">กำลังโหลดข้อมูลอันดับ...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-door-open me-2 text-primary"></i>สถานะห้องประชุม</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small">
                            <tr>
                                <th class="ps-4">ห้อง / ชั้น</th>
                                <th>ชื่องาน</th>
                                <th>เวลา</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="roomDisplayBody">
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">กรุณาเลือกโรงแรมจากเมนูด้านบน...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let charts = {};

function initCharts() {
    charts.revenue = new Chart(document.getElementById('revenueChart').getContext('2d'), { type: 'line', data: { labels: [], datasets: [{ label: 'รายได้ (บาท)', data: [], backgroundColor: '#0d6efd', borderColor: '#0d6efd', tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false } });
    charts.types = new Chart(document.getElementById('typeChart').getContext('2d'), { type: 'doughnut', data: { labels: [], datasets: [{ data: [], backgroundColor: ['#0d6efd', '#ffc107', '#dc3545', '#198754', '#6610f2'] }] }, options: { responsive: true, maintainAspectRatio: false } });
    charts.occ = new Chart(document.getElementById('occChart').getContext('2d'), { type: 'bar', data: { labels: [], datasets: [{ label: 'จำนวนงาน', data: [], backgroundColor: '#6c757d' }] }, options: { responsive: true, maintainAspectRatio: false } });
    charts.lead = new Chart(document.getElementById('leadChart').getContext('2d'), { type: 'line', data: { labels: [], datasets: [{ label: 'วัน', data: [], backgroundColor: '#ff9f43', borderColor: '#ff9f43' }] }, options: { responsive: true, maintainAspectRatio: false } });
}

function updateDashboardData(data) {
    if (!data) {
        console.error('No data received from API');
        return;
    }

    // Stats (with defensive checks)
    if (data.stats) {
        document.getElementById('stat_total').innerText = Number(data.stats.total_events || 0).toLocaleString();
        document.getElementById('stat_pending').innerText = Number(data.stats.pending_count || 0).toLocaleString();
        document.getElementById('stat_revenue').innerText = '฿' + Number(data.stats.total_revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    // Charts
    if (data.revenue) {
        charts.revenue.data.labels = data.revenue.map(r => r.month);
        charts.revenue.data.datasets[0].data = data.revenue.map(r => r.revenue);
        charts.revenue.update();
    }

    if (data.types) {
        charts.types.data.labels = data.types.map(t => t.type_name);
        charts.types.data.datasets[0].data = data.types.map(t => t.count);
        charts.types.update();
    }

    if (data.occupancy) {
        charts.occ.data.labels = data.occupancy.map(o => o.company_name);
        charts.occ.data.datasets[0].data = data.occupancy.map(o => o.count);
        charts.occ.update();
    }

    if (data.lead_time) {
        charts.lead.data.labels = data.lead_time.map(l => l.month);
        charts.lead.data.datasets[0].data = data.lead_time.map(l => parseFloat(l.avg_days || 0).toFixed(1));
        charts.lead.update();
    }

    // Sales Performance
    let salesHtml = '';
    if (!data.sales || data.sales.length === 0) {
        salesHtml = '<div class="col-12 text-center text-muted">ไม่พบข้อมูลทีมขาย</div>';
    } else {
        data.sales.forEach(s => {
            let target = parseFloat(s.target || 0);
            let actual = parseFloat(s.actual || 0);
            let percent = target > 0 ? (actual / target) * 100 : 0;
            let barColor = percent >= 100 ? 'bg-success' : (percent >= 50 ? 'bg-primary' : 'bg-warning');
            
            let targetDisplay = target > 0 ? `เป้า: ฿${target.toLocaleString()}` : '<span class="text-danger small">ยังไม่ได้ตั้งเป้า</span>';
            let percentDisplay = target > 0 ? `${percent.toFixed(1)}%` : '-';
            let progressBar = target > 0 ? `
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated ${barColor}" 
                         role="progressbar" style="width: ${Math.min(percent, 100)}%"></div>
                </div>` : '<div class="mb-2" style="height: 10px;"></div>';

            salesHtml += `
                <div class="col-md-6 col-xl-4">
                    <div class="p-3 border rounded-3 bg-light bg-opacity-50 h-100">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold small">${s.name}</span>
                            <span class="small text-muted">${percentDisplay}</span>
                        </div>
                        ${progressBar}
                        <div class="d-flex justify-content-between x-small">
                            <span class="fw-bold text-success">ยอดขาย: ฿${actual.toLocaleString()}</span>
                            <span class="text-muted">${targetDisplay}</span>
                        </div>
                    </div>
                </div>`;
        });
    }
    document.getElementById('salesPerformanceBody').innerHTML = salesHtml;

    // Sales Leaderboard
    let leaderboardHtml = '';
    let sortedSales = [...(data.sales || [])].sort((a, b) => parseFloat(b.actual || 0) - parseFloat(a.actual || 0));
    
    if (sortedSales.length === 0) {
        leaderboardHtml = '<tr><td colspan="2" class="text-center py-4 text-muted small">ไม่มีข้อมูลยอดขาย</td></tr>';
    } else {
        sortedSales.forEach((s, index) => {
            let trophy = '';
            if (index === 0) trophy = '<i class="bi bi-trophy-fill text-warning me-2"></i>';
            else if (index === 1) trophy = '<i class="bi bi-trophy-fill text-secondary me-2"></i>';
            else if (index === 2) trophy = '<i class="bi bi-trophy-fill text-bronze me-2" style="color: #cd7f32;"></i>';
            
            leaderboardHtml += `
                <tr>
                    <td class="ps-3">
                        <span class="small fw-bold">${trophy}${s.name}</span>
                    </td>
                    <td class="text-end pe-3">
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold">฿${parseFloat(s.actual || 0).toLocaleString()}</span>
                    </td>
                </tr>`;
        });
    }
    document.getElementById('salesLeaderboardBody').innerHTML = leaderboardHtml;

    // Room Status
    let roomsHtml = '';
    if(!data.rooms || data.rooms.length === 0) roomsHtml = '<tr><td colspan="4" class="text-center py-5">ไม่พบข้อมูลห้องประชุม</td></tr>';
    else {
        data.rooms.forEach(item => {
            let isBusy = item.function_name ? true : false;
            roomsHtml += `<tr>
                <td class="ps-4">
                    <div class="fw-bold text-dark mb-0">${item.room_name}</div>
                    <small class="text-muted">${item.company_name} | ชั้น ${item.floor}</small>
                </td>
                <td><span class="${isBusy ? 'text-primary fw-bold' : 'text-muted'}">${item.function_name || '- ว่าง -'}</span></td>
                <td><small class="text-muted">${isBusy ? item.start_t + ' - ' + item.end_t : '-'}</small></td>
                <td>${isBusy ? '<span class="badge bg-danger bg-opacity-10 text-danger px-3">ไม่ว่าง</span>' : '<span class="badge bg-success bg-opacity-10 text-success px-3">ว่าง</span>'}</td>
            </tr>`;
        });
    }
    document.getElementById('roomDisplayBody').innerHTML = roomsHtml;
}

function loadRoomStatus(companyId) {
    fetch(`api/api_get_dashboard.php?company_id=${companyId}`)
    .then(res => res.json())
    .then(data => {
        updateDashboardData(data);
    })
    .catch(err => {
        console.error('Dashboard Error:', err);
        document.getElementById('salesPerformanceBody').innerHTML = '<div class="col-12 text-center text-danger py-3">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
    });
}

document.addEventListener('DOMContentLoaded', () => { 
    initCharts(); 
    loadRoomStatus('all'); 
});
</script>

<?php include "footer.php"; ?>