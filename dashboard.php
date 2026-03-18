<?php
include "header.php";
include "config.php";

// --- 1. สถิติจริงจากตาราง functions ---
// รวม deposit เป็นรายได้เบื้องต้น และนับงานทั้งหมด
$stats_res = $conn->query("SELECT 
    COUNT(id) as total_events,
    SUM(CASE WHEN approve = 0 THEN 1 ELSE 0 END) as pending_count,
    SUM(deposit) as total_revenue 
    FROM functions");
$stats = $stats_res->fetch_assoc();

// --- 2. รายชื่อบริษัท/โรงแรม ---
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Banquet Operations Dashboard</h4>
            <small class="text-muted">ระบบบริหารจัดการงานจัดเลี้ยงและห้องประชุม</small>
        </div>
        <div class="col-md-3">
            <select class="form-select border-primary shadow-sm" id="companyFilter"
                onchange="loadRoomStatus(this.value)">
                <option value="all">-- แสดงทั้งหมด --</option> <?php while ($c = $companies->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                        <i class="bi bi-calendar-check fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">TOTAL FUNCTIONS</h6>
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
                        <h6 class="text-muted mb-0 small fw-bold">PENDING APPROVE</h6>
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
                        <h6 class="text-muted mb-0 small fw-bold">TOTAL DEPOSIT</h6>
                        <h3 class="fw-bold mb-0 text-success" id="stat_revenue">
                            ฿<?= number_format($stats['total_revenue'], 2) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-door-open me-2 text-primary"></i>Room Availability &
                        Current Status</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small">
                            <tr>
                                <th class="ps-4">ROOM / FLOOR</th>
                                <th>EVENT NAME</th>
                                <th>PAX</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="roomDisplayBody">
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">กรุณาเลือกโรงแรมจากเมนูด้านบน...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 bg-dark p-4 mb-4 rounded-4">
                <h6 class="text-white fw-bold mb-4 border-bottom border-secondary pb-2">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="create_function.php" class="btn btn-primary py-2 rounded-3 text-start shadow-sm">
                        <i class="bi bi-plus-circle me-2"></i> New Booking
                    </a>
                    <button onclick="window.print()"
                        class="btn btn-outline-light py-2 rounded-3 text-start border-secondary">
                        <i class="bi bi-printer me-2"></i> Print Daily Schedule
                    </button>
                </div>
            </div>

            <div class="card shadow-sm border-0 p-4 rounded-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Operational Alerts</h6>
                <div class="alert alert-warning border-0 small py-2 shadow-sm rounded-3 mb-2">
                    <i class="bi bi-info-circle me-2"></i> มี <?= $stats['pending_count'] ?> รายการรออนุมัติ
                </div>
                <div class="alert alert-info border-0 small py-2 shadow-sm rounded-3">
                    <i class="bi bi-egg-fried me-2"></i> เช็ก Remark ใน Kitchen Remark เสมอ
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function loadRoomStatus(companyId) {
    const tbody = document.getElementById('roomDisplayBody');
    // อ้างอิง ID ของ Card ต่างๆ
    const txtTotal = document.getElementById('stat_total');
    const txtPending = document.getElementById('stat_pending');
    const txtRevenue = document.getElementById('stat_revenue');

    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    fetch(`api/api_get_dashboard.php?company_id=${companyId}`)
    .then(res => res.json())
    .then(data => {
        // --- 1. อัปเดตตัวเลขสถิติใน Card ---
        txtTotal.innerText = Number(data.stats.total_events).toLocaleString();
        txtPending.innerText = Number(data.stats.pending_count).toLocaleString();
        txtRevenue.innerText = '฿' + Number(data.stats.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2});

        // --- 2. อัปเดตตารางห้องประชุม ---
        let html = '';
        if(data.rooms.length === 0) {
            html = '<tr><td colspan="4" class="text-center py-5">ไม่พบข้อมูลห้องประชุม</td></tr>';
        } else {
            data.rooms.forEach(item => {
                let isBusy = item.function_name ? true : false;
                let statusBadge = isBusy 
                    ? `<span class="badge rounded-pill bg-danger bg-opacity-10 text-danger px-3 border border-danger border-opacity-10">Occupied</span>`
                    : `<span class="badge rounded-pill bg-success bg-opacity-10 text-success px-3 border border-success border-opacity-10">Available</span>`;
                
                html += `
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold text-dark mb-0">${item.room_name}</div>
                        <small class="text-muted">${item.company_name} | ชั้น ${item.floor}</small>
                    </td>
                    <td><span class="${isBusy ? 'text-primary fw-bold' : 'text-muted'}">${item.function_name || '- ว่าง -'}</span></td>
                    <td><small class="text-muted">${isBusy ? item.start_t + ' - ' + item.end_t : '-'}</small></td>
                    <td>${statusBadge}</td>
                </tr>`;
            });
        }
        tbody.innerHTML = html;
    });
}

// โหลดครั้งแรกให้แสดงทั้งหมด
document.addEventListener('DOMContentLoaded', () => loadRoomStatus('all'));
</script>

<?php include "footer.php"; ?>