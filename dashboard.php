<?php
include "header.php";
include "config.php";

$stats_res = $conn->query("SELECT 
    COUNT(f.id) as total_events,
    SUM(CASE WHEN f.approve = 0 THEN 1 ELSE 0 END) as pending_count,
    IFNULL(SUM(f.deposit), 0) as total_revenue,
    (
        SELECT AVG( ( (f2.total_amount + IFNULL(inc.total_inc, 0)) - (IFNULL(cst.total_cst, 0) + 0) ) / NULLIF(IFNULL(cst.total_cst, 0) + 0, 0) * 100 )
        FROM functions f2
        LEFT JOIN (SELECT function_id, SUM(amount) as total_inc FROM function_finance WHERE type='income' GROUP BY function_id) inc ON f2.id = inc.function_id
        LEFT JOIN (SELECT function_id, SUM(amount) as total_cst FROM function_finance WHERE type='cost' GROUP BY function_id) cst ON f2.id = cst.function_id
        WHERE f2.approve = 1
    ) as avg_roi
    FROM functions f");
$stats = $stats_res->fetch_assoc();

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root {
    --dash-bg: #f7f5f2;
    --dash-card: #ffffff;
    --dash-text: #1c1917;
    --dash-muted: #a8a29e;
    --dash-border: #e7e5e4;
    --dash-rose: #e11d48;
    --dash-sage: #65a30d;
    --dash-navy: #1e40af;
    --dash-amber: #d97706;
    --dash-teal: #0d9488;
}

body {
    font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif;
    background: var(--dash-bg);
}

.dash-header {
    padding: 1.5rem 0 0.5rem;
    border-bottom: 2px solid var(--dash-border);
    margin-bottom: 2rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1rem;
}
.dash-header h1 {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--dash-text);
    margin: 0;
    line-height: 1.2;
}
.dash-header h1 small {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--dash-muted);
    letter-spacing: 0.04em;
    margin-top: 0.15rem;
}

.dash-filter select {
    border: 1px solid var(--dash-border);
    border-radius: 12px;
    padding: 0.55rem 1rem;
    font-size: 0.8rem;
    font-weight: 500;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23a8a29e' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 1rem center;
    appearance: none;
    -webkit-appearance: none;
    min-width: 200px;
    color: var(--dash-text);
    transition: border 0.2s;
    cursor: pointer;
}
.dash-filter select:focus {
    outline: none;
    border-color: var(--dash-navy);
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.08);
}

.stat-tile {
    background: var(--dash-card);
    border: 1px solid var(--dash-border);
    border-radius: 16px;
    padding: 1.5rem 1.5rem 1.25rem;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.stat-tile:hover {
    border-color: transparent;
    box-shadow: 0 8px 30px rgba(0,0,0,0.07);
    transform: translateY(-2px);
}
.stat-tile .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 0.75rem;
}
.stat-tile .stat-num {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
}
.stat-tile .stat-label {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    margin-top: 0.15rem;
}
.stat-tile .stat-hint {
    font-size: 0.65rem;
    color: var(--dash-muted);
    margin-top: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.stat-tile-rose .stat-icon { background: #fef2f2; color: var(--dash-rose); }
.stat-tile-rose .stat-num { color: var(--dash-rose); }
.stat-tile-rose .stat-label { color: #7c2d12; }
.stat-tile-rose:hover { box-shadow: 0 8px 30px rgba(225, 29, 72, 0.1); }

.stat-tile-navy .stat-icon { background: #eff6ff; color: var(--dash-navy); }
.stat-tile-navy .stat-num { color: var(--dash-navy); }
.stat-tile-navy .stat-label { color: #1e3a5f; }
.stat-tile-navy:hover { box-shadow: 0 8px 30px rgba(30, 64, 175, 0.1); }

.stat-tile-sage .stat-icon { background: #f7fee7; color: var(--dash-sage); }
.stat-tile-sage .stat-num { color: var(--dash-sage); }
.stat-tile-sage .stat-label { color: #3f6212; }
.stat-tile-sage:hover { box-shadow: 0 8px 30px rgba(101, 163, 13, 0.1); }

.stat-tile-amber .stat-icon { background: #fffbeb; color: var(--dash-amber); }
.stat-tile-amber .stat-num { color: var(--dash-amber); }
.stat-tile-amber .stat-label { color: #78350f; }
.stat-tile-amber:hover { box-shadow: 0 8px 30px rgba(217, 119, 6, 0.1); }

.chart-box {
    background: var(--dash-card);
    border: 1px solid var(--dash-border);
    border-radius: 16px;
    transition: all 0.25s ease;
}
.chart-box:hover {
    box-shadow: 0 6px 24px rgba(0,0,0,0.05);
    border-color: transparent;
}
.chart-box .chart-head {
    padding: 1.25rem 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.chart-box .chart-head .ch-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.chart-box .chart-head h6 {
    font-size: 0.82rem;
    font-weight: 700;
    margin: 0;
    color: var(--dash-text);
}
.chart-box .chart-body {
    padding: 1rem 1.5rem 1.25rem;
}

.section-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--dash-border);
}
.section-head h5 {
    font-size: 0.9rem;
    font-weight: 700;
    margin: 0;
    color: var(--dash-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-head a {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--dash-muted);
    text-decoration: none;
    transition: color 0.2s;
}
.section-head a:hover { color: var(--dash-text); }

.sales-item {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    background: var(--dash-card);
    border: 1px solid var(--dash-border);
    transition: border 0.2s;
    height: 100%;
}
.sales-item:hover { border-color: transparent; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.sales-item .sales-name {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--dash-text);
}
.sales-item .sales-pct {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.6rem;
    border-radius: 50px;
}
.sales-item .bar-track {
    height: 6px;
    background: #f1f0ef;
    border-radius: 50px;
    margin: 0.5rem 0 0.4rem;
    overflow: hidden;
}
.sales-item .bar-track .bar-fill {
    height: 100%;
    border-radius: 50px;
    transition: width 0.6s ease;
}
.sales-item .sales-meta {
    font-size: 0.72rem;
    display: flex;
    justify-content: space-between;
}

.lb-list { padding: 0; }
.lb-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    transition: background 0.15s;
}
.lb-item:hover { background: #f1f0ef; }
.lb-item + .lb-item { border-top: 1px solid #f5f4f3; }
.lb-rank {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 800;
    flex-shrink: 0;
}
.lb-item .lb-name {
    flex: 1;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--dash-text);
}
.lb-item .lb-amount {
    font-size: 0.8rem;
    font-weight: 700;
}

.tbl-dash {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.tbl-dash thead th {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--dash-muted);
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--dash-border);
    background: transparent;
}
.tbl-dash tbody td {
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #f5f4f3;
    font-size: 0.82rem;
    vertical-align: middle;
}
.tbl-dash tbody tr:last-child td { border-bottom: none; }
.tbl-dash .room-name {
    font-weight: 600;
    color: var(--dash-text);
}
.tbl-dash .room-meta {
    font-size: 0.68rem;
    color: var(--dash-muted);
    margin-top: 0.1rem;
}
.tbl-dash .room-event {
    font-weight: 500;
}
.room-tag {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.2rem 0.75rem;
    border-radius: 50px;
    display: inline-block;
}
.room-tag-free {
    background: #f0fdf4;
    color: #15803d;
}
.room-tag-busy {
    background: #fef2f2;
    color: #b91c1c;
}

.dash-modal .modal-content {
    border: none;
    border-radius: 20px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.15);
}
.dash-modal .modal-header {
    border: none;
    padding: 1.5rem 1.5rem 0;
}
.dash-modal .modal-body {
    padding: 1rem 1.5rem 1.5rem;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
.slide-up { animation: slideUp 0.45s ease forwards; opacity: 0; }
.slide-up:nth-child(1) { animation-delay: 0.03s; }
.slide-up:nth-child(2) { animation-delay: 0.08s; }
.slide-up:nth-child(3) { animation-delay: 0.13s; }
.slide-up:nth-child(4) { animation-delay: 0.18s; }

@media (max-width: 768px) {
    .dash-header { flex-direction: column; align-items: stretch; }
    .dash-header h1 small { display: inline; margin-left: 0.5rem; }
    .dash-filter select { width: 100%; }
    .stat-tile .stat-num { font-size: 1.6rem; }
}
</style>

<script>
let charts = {};

function showDetails(type, title) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('modalTitle').innerText = title;
    const companyId = document.getElementById('companyFilter').value;
    const header = document.getElementById('detailsTableHeader');
    const body = document.getElementById('detailsTableBody');

    header.innerHTML = '';
    body.innerHTML = '<tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-secondary" role="status" style="width:1.5rem;height:1.5rem;"></div></td></tr>';
    modal.show();

    fetch(`api/get_dashboard_details.php?type=${type}&company_id=${companyId}`)
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            let h = '', b = '';
            if (type === 'revenue') {
                h = '<th>วันที่</th><th>ชื่องาน</th><th>โรงแรม</th><th class="text-end">มัดจำ</th><th class="text-end">ยอดรวม</th>';
                res.data.forEach(item => {
                    b += `<tr>
                        <td><span class="badge bg-light text-dark fw-normal px-3 py-1">${item.event_date}</span></td>
                        <td class="fw-semibold">${item.function_name}</td>
                        <td class="text-muted">${item.company_name}</td>
                        <td class="text-end fw-bold" style="color:#15803d">฿${parseFloat(item.deposit).toLocaleString()}</td>
                        <td class="text-end">฿${parseFloat(item.total_amount).toLocaleString()}</td>
                    </tr>`;
                });
            } else if (type === 'roi') {
                h = '<th>วันที่</th><th>ชื่องาน</th><th>โรงแรม</th><th class="text-end">ต้นทุน</th><th class="text-end">ROI</th>';
                res.data.forEach(item => {
                    let c = item.roi > 0 ? '#15803d' : '#b91c1c';
                    b += `<tr>
                        <td><span class="badge bg-light text-dark fw-normal px-3 py-1">${item.event_date}</span></td>
                        <td class="fw-semibold">${item.function_name}</td>
                        <td class="text-muted">${item.company_name}</td>
                        <td class="text-end">฿${parseFloat(item.total_cost).toLocaleString()}</td>
                        <td class="text-end fw-bold" style="color:${c}">${parseFloat(item.roi).toFixed(2)}%</td>
                    </tr>`;
                });
            } else {
                h = '<th>วันที่</th><th>ชื่องาน</th><th>โรงแรม</th><th class="text-end">ยอดรวม</th><th>สถานะ</th>';
                res.data.forEach(item => {
                    b += `<tr>
                        <td><span class="badge bg-light text-dark fw-normal px-3 py-1">${item.event_date}</span></td>
                        <td class="fw-semibold">${item.function_name}</td>
                        <td class="text-muted">${item.company_name}</td>
                        <td class="text-end">฿${parseFloat(item.total_amount).toLocaleString()}</td>
                        <td><span class="badge bg-light text-dark border fw-normal px-3 py-1">${item.status}</span></td>
                    </tr>`;
                });
            }
            header.innerHTML = h;
            body.innerHTML = b || '<tr><td colspan="5" class="text-center py-5 text-muted">ไม่มีข้อมูล</td></tr>';
        }
    });
}
</script>

<div class="container-fluid px-0">

    <!-- Header -->
    <div class="dash-header slide-up">
        <div>
            <h1>
                ภาพรวมการดำเนินงาน
                <small>ระบบบริหารจัดการงานจัดเลี้ยงและห้องประชุม</small>
            </h1>
        </div>
        <div class="dash-filter">
            <select id="companyFilter" onchange="loadRoomStatus(this.value)">
                <option value="all">🏨 ทุกโรงแรม</option>
                <?php while ($c = $companies->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3 slide-up">
            <div class="stat-tile stat-tile-rose" onclick="showDetails('total_events', 'งานทั้งหมด')">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-num" id="stat_total"><?= number_format($stats['total_events']) ?></div>
                <div class="stat-label">งานทั้งหมด</div>
                <div class="stat-hint"><i class="bi bi-arrow-right-circle"></i> ดูรายละเอียด</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 slide-up">
            <div class="stat-tile stat-tile-navy" onclick="showDetails('pending', 'รอการอนุมัติ')">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-num" id="stat_pending"><?= number_format($stats['pending_count']) ?></div>
                <div class="stat-label">รอการอนุมัติ</div>
                <div class="stat-hint"><i class="bi bi-arrow-right-circle"></i> ดูรายละเอียด</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 slide-up">
            <div class="stat-tile stat-tile-sage" onclick="showDetails('revenue', 'รายได้มัดจำรวม')">
                <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                <div class="stat-num" id="stat_revenue">฿<?= number_format($stats['total_revenue'], 2) ?></div>
                <div class="stat-label">รายได้มัดจำรวม</div>
                <div class="stat-hint"><i class="bi bi-arrow-right-circle"></i> ดูรายละเอียด</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 slide-up">
            <div class="stat-tile stat-tile-amber" onclick="showDetails('roi', 'ROI เฉลี่ย')">
                <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                <div class="stat-num" id="stat_roi"><?= number_format($stats['avg_roi'] ?? 0, 2) ?>%</div>
                <div class="stat-label">ROI เฉลี่ย</div>
                <div class="stat-hint"><i class="bi bi-arrow-right-circle"></i> ดูรายละเอียด</div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6 slide-up">
            <div class="chart-box">
                <div class="chart-head">
                    <div class="ch-icon" style="background:#fef2f2;color:#e11d48;"><i class="bi bi-bar-chart-fill"></i></div>
                    <h6>รายได้รายเดือน (6 เดือน)</h6>
                </div>
                <div class="chart-body">
                    <canvas id="revenueChart" style="max-height:200px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 slide-up">
            <div class="chart-box">
                <div class="chart-head">
                    <div class="ch-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-pie-chart-fill"></i></div>
                    <h6>สัดส่วนประเภทงาน</h6>
                </div>
                <div class="chart-body">
                    <canvas id="typeChart" style="max-height:200px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 slide-up">
            <div class="chart-box">
                <div class="chart-head">
                    <div class="ch-icon" style="background:#f7fee7;color:#65a30d;"><i class="bi bi-building"></i></div>
                    <h6>งานตามโรงแรม (Occupancy)</h6>
                </div>
                <div class="chart-body">
                    <canvas id="occChart" style="max-height:200px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 slide-up">
            <div class="chart-box">
                <div class="chart-head">
                    <div class="ch-icon" style="background:#eff6ff;color:#1e40af;"><i class="bi bi-clock-history"></i></div>
                    <h6>ระยะเวลาอนุมัติเฉลี่ย (วัน)</h6>
                </div>
                <div class="chart-body">
                    <canvas id="leadChart" style="max-height:200px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales -->
    <div class="slide-up">
        <div class="chart-box" style="margin-bottom:1.5rem;">
            <div class="section-head" style="padding:1.25rem 1.5rem 0;margin-bottom:0;border:none;padding-bottom:0;">
                <h5><i class="bi bi-graph-up-arrow" style="color:#1e40af;"></i> ผลการดำเนินงานทีมขาย (เดือนนี้)</h5>
                <a href="sales_dept.php"><i class="bi bi-gear"></i> ตั้งค่าเป้าหมาย</a>
            </div>
            <div style="padding:0.75rem 1.5rem 1.25rem;">
                <div class="row g-2" id="salesPerformanceBody">
                    <div class="col-12 text-center py-4 text-muted small">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>กำลังโหลด...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard + Rooms -->
    <div class="row g-3">
        <div class="col-lg-4 slide-up">
            <div class="chart-box h-100">
                <div class="chart-head" style="padding:1.25rem 1.25rem 0;">
                    <div class="ch-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-trophy-fill"></i></div>
                    <h6>อันดับยอดขายรายคน</h6>
                </div>
                <div style="padding:0.5rem 0.75rem 0.75rem;">
                    <div id="salesLeaderboardBody">
                        <div class="text-center py-4 text-muted small">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>กำลังโหลด...
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 slide-up">
            <div class="chart-box h-100">
                <div class="chart-head" style="padding:1.25rem 1.25rem 0;">
                    <div class="ch-icon" style="background:#eff6ff;color:#1e40af;"><i class="bi bi-door-open"></i></div>
                    <h6>สถานะห้องประชุม</h6>
                </div>
                <div style="padding:0;">
                    <div class="table-responsive">
                        <table class="tbl-dash">
                            <thead>
                                <tr>
                                    <th class="ps-4">ห้อง / ชั้น</th>
                                    <th>ชื่องาน</th>
                                    <th>เวลา</th>
                                    <th class="pe-4">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="roomDisplayBody">
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">เลือกโรงแรมจากเมนูด้านบน...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal -->
<div class="modal fade dash-modal" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">รายละเอียด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="tbl-dash w-100" id="detailsTable">
                        <thead>
                            <tr id="detailsTableHeader"></tr>
                        </thead>
                        <tbody id="detailsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function initCharts() {
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    font: { family: "'Plus Jakarta Sans','Sarabun',sans-serif", size: 10 },
                    boxWidth: 10,
                    padding: 10
                }
            }
        }
    };

    charts.revenue = new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: { labels: [], datasets: [{
            label: 'รายได้',
            data: [],
            borderColor: '#e11d48',
            backgroundColor: 'rgba(225,29,72,0.06)',
            borderWidth: 2.5,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#e11d48',
            pointBorderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5
        }] },
        options: {
            ...base,
            plugins: { ...base.plugins, legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 9 }, callback: v => '฿' + v.toLocaleString() }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { ticks: { font: { size: 9 } }, grid: { display: false } }
            }
        }
    });

    charts.types = new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: { labels: [], datasets: [{ data: [], backgroundColor: ['#e11d48','#d97706','#65a30d','#1e40af','#0d9488','#7c3aed'], borderWidth: 2, borderColor: '#fff' }] },
        options: {
            ...base,
            cutout: '72%',
            plugins: {
                ...base.plugins,
                legend: { position: 'right', labels: { ...base.plugins.legend.labels, padding: 6 } }
            }
        }
    });

    charts.occ = new Chart(document.getElementById('occChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{
            label: 'จำนวนงาน',
            data: [],
            backgroundColor: 'rgba(101,163,13,0.65)',
            borderColor: '#65a30d',
            borderWidth: 0,
            borderRadius: 4,
            barPercentage: 0.55
        }] },
        options: {
            ...base,
            plugins: { ...base.plugins, legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 9 }, stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { ticks: { font: { size: 8 } }, grid: { display: false } }
            }
        }
    });

    charts.lead = new Chart(document.getElementById('leadChart'), {
        type: 'line',
        data: { labels: [], datasets: [{
            label: 'วัน',
            data: [],
            borderColor: '#1e40af',
            backgroundColor: 'rgba(30,64,175,0.06)',
            borderWidth: 2.5,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#1e40af',
            pointBorderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5
        }] },
        options: {
            ...base,
            plugins: { ...base.plugins, legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 9 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { ticks: { font: { size: 9 } }, grid: { display: false } }
            }
        }
    });
}

function updateDashboardData(data) {
    if (!data) return;

    if (data.stats) {
        document.getElementById('stat_total').innerText = Number(data.stats.total_events || 0).toLocaleString();
        document.getElementById('stat_pending').innerText = Number(data.stats.pending_count || 0).toLocaleString();
        document.getElementById('stat_revenue').innerText = '฿' + Number(data.stats.total_revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        if (data.stats.avg_roi) {
            document.getElementById('stat_roi').innerText = parseFloat(data.stats.avg_roi).toFixed(2) + '%';
        }
    }

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

    // Sales
    let sHtml = '';
    if (!data.sales || data.sales.length === 0) {
        sHtml = '<div class="col-12 text-center py-4 text-muted small">ไม่มีข้อมูลทีมขาย</div>';
    } else {
        data.sales.forEach(s => {
            let target = parseFloat(s.target || 0);
            let actual = parseFloat(s.actual || 0);
            let pct = target > 0 ? (actual / target) * 100 : 0;
            let barColor = pct >= 100 ? '#65a30d' : (pct >= 50 ? '#1e40af' : '#e11d48');
            let bgClass = pct >= 100 ? 'style="background:#f0fdf4;color:#15803d;"' :
                          pct >= 50 ? 'style="background:#eff6ff;color:#1e40af;"' :
                          'style="background:#fef2f2;color:#b91c1c;"';

            sHtml += `
                <div class="col-md-6 col-xl-4">
                    <div class="sales-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="sales-name">${s.name}</span>
                            <span class="sales-pct" ${bgClass}>${target > 0 ? pct.toFixed(1) + '%' : '-'}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width:${Math.min(pct, 100)}%;background:${barColor};"></div></div>
                        <div class="sales-meta">
                            <span style="color:#15803d;font-weight:600;">฿${actual.toLocaleString()}</span>
                            <span style="color:var(--dash-muted);">${target > 0 ? 'เป้า: ฿' + target.toLocaleString() : 'ยังไม่ได้ตั้งเป้า'}</span>
                        </div>
                    </div>
                </div>`;
        });
    }
    document.getElementById('salesPerformanceBody').innerHTML = sHtml;

    // Leaderboard
    let lbHtml = '';
    let sorted = [...(data.sales || [])].sort((a, b) => parseFloat(b.actual || 0) - parseFloat(a.actual || 0));
    if (sorted.length === 0) {
        lbHtml = '<div class="text-center py-4 text-muted small">ไม่มีข้อมูล</div>';
    } else {
        sorted.forEach((s, i) => {
            let colors = ['#d97706','#78716c','#a8a29e','#d6d3d1','#d6d3d1','#d6d3d1'];
            let bgColors = ['#fffbeb','#f5f5f4','#f5f5f4','#f5f5f4','#f5f5f4','#f5f5f4'];
            let rankColor = colors[i] || '#d6d3d1';
            let rankBg = bgColors[i] || '#f5f5f4';
            if (i < 3) rankBg = '#fff';

            lbHtml += `
                <div class="lb-item">
                    <div class="lb-rank" style="background:${rankBg};color:${rankColor};border:1px solid ${rankColor}20;">${i + 1}</div>
                    <div class="lb-name">${i === 0 ? '<i class="bi bi-crown-fill me-1" style="color:#d97706;"></i>' : ''}${s.name}</div>
                    <div class="lb-amount" style="color:#15803d;">฿${parseFloat(s.actual || 0).toLocaleString()}</div>
                </div>`;
        });
    }
    document.getElementById('salesLeaderboardBody').innerHTML = lbHtml;

    // Rooms
    let rHtml = '';
    if (!data.rooms || data.rooms.length === 0) {
        rHtml = '<tr><td colspan="4" class="text-center py-5 text-muted">ไม่มีข้อมูล</td></tr>';
    } else {
        data.rooms.forEach(item => {
            let busy = !!item.function_name;
            rHtml += `<tr>
                <td class="ps-4">
                    <div class="room-name">${item.room_name}</div>
                    <div class="room-meta">${item.company_name} · ชั้น ${item.floor}</div>
                </td>
                <td><span class="room-event" style="color:${busy ? 'var(--dash-text)' : '#a8a29e'}">${item.function_name || '— ว่าง —'}</span></td>
                <td>${busy ? '<span style="font-size:0.8rem;">' + item.start_t + ' – ' + item.end_t + '</span>' : '<span style="color:#a8a29e;">—</span>'}</td>
                <td class="pe-4">${busy ? '<span class="room-tag room-tag-busy">ไม่ว่าง</span>' : '<span class="room-tag room-tag-free">ว่าง</span>'}</td>
            </tr>`;
        });
    }
    document.getElementById('roomDisplayBody').innerHTML = rHtml;
}

function loadRoomStatus(companyId) {
    fetch(`api/api_get_dashboard.php?company_id=${companyId}`)
    .then(r => r.json())
    .then(d => updateDashboardData(d))
    .catch(() => {
        document.getElementById('salesPerformanceBody').innerHTML = '<div class="col-12 text-center text-danger py-3">โหลดข้อมูลล้มเหลว</div>';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    loadRoomStatus('all');
});
</script>

<?php include "footer.php"; ?>
