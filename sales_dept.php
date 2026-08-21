<?php
include "header.php";
include "config.php";

// เช็คสิทธิ์ (Admin หรือ Staff ที่เกี่ยวข้อง)
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'staff', 'gm', 'sale'])) {
    echo "<script>window.location.href='login.php?error=access_denied';</script>";
    exit;
}

// --- ข้อมูลเป้าหมายการขาย ---
$current_month = date('n');
$current_year = date('Y');

// ดึงรายการพนักงานเพื่อใช้ใน Select
$staffs = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff', 'Banquet_Staff', 'Admin', 'Sale') ORDER BY name ASC");

// รายการเป้าหมายทั้งหมด (ดึงเก็บเป็น array เพื่อเอาไปนับใน hero ด้วย)
$targets_res = $conn->query("SELECT st.*, u.name FROM sales_targets st JOIN users u ON st.user_id = u.id ORDER BY st.target_year DESC, st.target_month DESC");
$targets = [];
while ($t = $targets_res->fetch_assoc()) $targets[] = $t;

// ผลงานพนักงานขายเดือนปัจจุบัน (ดึงเก็บเป็น array เพื่อคำนวณสรุปยอดรวมของทั้งแผนกด้วย)
$staff_perf_res = $conn->query("SELECT
    u.id, u.name, u.role,
    COUNT(f.id) as total_events,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled') THEN f.total_amount ELSE 0 END), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN f.approve = 1 AND f.status NOT IN ('Cancelled','Completed') THEN f.deposit ELSE 0 END), 0) as total_deposit,
    COALESCE((SELECT st.target_amount FROM sales_targets st WHERE st.user_id = u.id AND st.target_month = $current_month AND st.target_year = $current_year LIMIT 1), 0) as target_amount
    FROM users u
    LEFT JOIN functions f ON f.created_by_id = u.id AND MONTH(f.created_at) = $current_month AND YEAR(f.created_at) = $current_year
    WHERE u.role IN ('Staff', 'Admin', 'Sale')
    GROUP BY u.id, u.name, u.role
    HAVING total_events > 0 OR target_amount > 0
    ORDER BY total_revenue DESC");
$staff_perf = [];
while ($s = $staff_perf_res->fetch_assoc()) $staff_perf[] = $s;

$sum_revenue = array_sum(array_column($staff_perf, 'total_revenue'));
$sum_deposit = array_sum(array_column($staff_perf, 'total_deposit'));
$sum_target  = array_sum(array_column($staff_perf, 'target_amount'));
$sum_events  = array_sum(array_column($staff_perf, 'total_events'));

// "สำเร็จตามเป้า" ต้องเทียบเฉพาะยอดขายของคนที่ตั้งเป้าไว้จริง ไม่งั้นเอายอดรวมทุกคน
// (รวมคนที่ไม่มีเป้า) มาหารเป้าของคนเดียวที่ตั้งไว้ จะได้ % ที่ไม่มีความหมาย
$staff_with_target = array_filter($staff_perf, fn($s) => $s['target_amount'] > 0);
$sum_revenue_with_target = array_sum(array_column($staff_with_target, 'total_revenue'));
$overall_pct = $sum_target > 0 ? round(($sum_revenue_with_target / $sum_target) * 100, 1) : 0;

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<style>
.sd-page{--gold:#b89441;--gold-tint:#f7f1e3;--ink:#111318;--ink2:#5b6470;--muted:#8a9099;--line:#e8eaee}
.sd-page{font-family:'Sarabun','Inter',sans-serif;color:var(--ink)}
.sd-hero{border-radius:16px;padding:15px 20px;color:var(--ink);position:relative;overflow:hidden;
    background:#fff;border:1px solid var(--line);border-left:4px solid var(--gold)}
.sd-hero::after{content:'';position:absolute;inset:0;pointer-events:none;
    background:radial-gradient(520px 200px at 92% -40%,rgba(184,148,65,.10),transparent 70%)}
.sd-hero>*{position:relative;z-index:1}
.sd-hero .hero-sub{font-size:.78rem;color:var(--ink2)}
.sd-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 14px;height:100%}
.sd-stat .v{font-size:1.45rem;font-weight:700;line-height:1.15;font-variant-numeric:tabular-nums}
.sd-stat .l{font-size:.7rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.sd-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
.sd-card-head{padding:14px 18px;border-bottom:1px solid var(--line);font-weight:700;font-size:.92rem}
.sd-card-body{padding:18px}
.sd-form label{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:4px;display:block}
.sd-form .form-control,.sd-form .form-select{border-radius:9px;font-size:.85rem}
.btn-gold{background:var(--gold);border:1px solid var(--gold);color:#fff;font-weight:600;border-radius:9px}
.btn-gold:hover{background:#a5833a;border-color:#a5833a;color:#fff}
#salesTargetTable{font-size:.84rem}
#salesTargetTable thead th{background:#fafbfc;color:var(--muted);font-weight:700;font-size:.68rem;text-transform:uppercase;
    letter-spacing:.4px;border-bottom:1px solid var(--line)!important;white-space:nowrap}
#salesTargetTable tbody td{vertical-align:middle;border-bottom:1px solid #f0f2f5}
.sd-perf-card{background:#fff;border:1px solid var(--line);border-radius:14px;height:100%;cursor:pointer;transition:box-shadow .15s ease,border-color .15s ease}
.sd-perf-card:hover{box-shadow:0 8px 18px rgba(16,24,40,.08);border-color:#d3d7dd}
.sd-perf-card.rank-1{border-color:var(--gold);background:linear-gradient(180deg,var(--gold-tint),#fff 60%)}
.sd-rank{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0}
.sd-empty{padding:52px 20px;text-align:center;color:var(--muted)}
</style>

<div class="sd-page">

    <!-- ===== HERO ===== -->
    <div class="sd-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-gold"></i>แผนกขาย (Sales Department)</h5>
                <div class="hero-sub">บันทึกภาระงานและจัดการเป้าหมายการขาย · พบ <?= number_format(count($targets)) ?> เป้าหมายที่ตั้งไว้</div>
            </div>
        </div>
    </div>

    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <!-- ===== STAT: สรุปเดือนปัจจุบัน ===== -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="sd-stat"><div class="l">ยอดขายเดือนนี้</div><div class="v" style="color:#0ca30c">฿<?= number_format($sum_revenue, 0) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="sd-stat"><div class="l">เป้าหมายรวม</div><div class="v"><?= $sum_target > 0 ? '฿' . number_format($sum_target, 0) : '-' ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="sd-stat"><div class="l">สำเร็จตามเป้า</div>
                <div class="v" style="color:<?= $overall_pct >= 100 ? '#0ca30c' : ($overall_pct >= 50 ? '#c78a1e' : '#8a9099') ?>">
                    <?= $sum_target > 0 ? number_format($overall_pct, 1) . '%' : '-' ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="sd-stat"><div class="l">งานทั้งหมดเดือนนี้</div><div class="v" style="color:#2a78d6"><?= number_format($sum_events) ?></div>
                <div style="font-size:.7rem;color:#8a9099">เงินมัดจำรวม ฿<?= number_format($sum_deposit, 0) ?></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- ส่วนตั้งค่าเป้าหมาย -->
        <div class="col-xl-4">
            <div class="sd-card h-100">
                <div class="sd-card-head"><i class="bi bi-plus-circle me-2 text-gold"></i>ตั้งเป้าหมายการขายรายเดือน</div>
                <div class="sd-card-body sd-form">
                    <form action="/manage_banquet/api/save_sales_target.php" method="POST">
                        <input type="hidden" name="redirect" value="<?= $h($_SERVER['REQUEST_URI']) ?>">
                        <div class="mb-3">
                            <label>เลือกพนักงาน (Sales)</label>
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">-- เลือกพนักงาน --</option>
                                <?php while ($s = $staffs->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= $h($s['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>เป้าหมายรายได้ (฿)</label>
                            <input type="number" name="target_amount" class="form-control form-control-sm" placeholder="เช่น 500000" required>
                        </div>
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label>เดือน</label>
                                <select name="target_month" class="form-select form-select-sm">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m == $current_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>ปี</label>
                                <select name="target_year" class="form-select form-select-sm">
                                    <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?= $y ?>"><?= $y + 543 ?> (<?= $y ?>)</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold w-100"><i class="bi bi-save me-1"></i>บันทึกเป้าหมาย</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- รายการเป้าหมาย -->
        <div class="col-xl-8">
            <div class="sd-card h-100">
                <div class="sd-card-head d-flex justify-content-between align-items-center">
                    <span>รายการเป้าหมายทั้งหมด</span>
                    <span class="badge bg-light text-muted border" style="font-weight:600"><?= number_format(count($targets)) ?> รายการ</span>
                </div>
                <div class="sd-card-body pt-3">
                    <?php if (empty($targets)): ?>
                        <div class="sd-empty">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            ยังไม่มีการตั้งเป้าหมาย
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="salesTargetTable" class="table table-hover align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>พนักงาน</th>
                                        <th>เดือน/ปี</th>
                                        <th>เป้าหมาย</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($targets as $t): ?>
                                    <tr>
                                        <td class="fw-medium"><?= $h($t['name']) ?></td>
                                        <td><?= date('F', mktime(0, 0, 0, $t['target_month'], 1)) ?> <?= $t['target_year'] + 543 ?></td>
                                        <td class="fw-bold text-primary">฿<?= number_format($t['target_amount'], 2) ?></td>
                                        <td class="text-end">
                                            <a href="/manage_banquet/api/save_sales_target.php?delete_id=<?= $t['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                               class="btn btn-sm btn-outline-danger border-0"
                                               onclick="return confirm('ลบเป้าหมายนี้?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Performance Cards: Staff ===== -->
    <div class="sd-card">
        <div class="sd-card-head"><i class="bi bi-people me-2 text-gold"></i>ผลงานพนักงานขาย <?= date('F') ?> <?= date('Y') + 543 ?></div>
        <div class="sd-card-body">
            <?php if (empty($staff_perf)): ?>
                <div class="sd-empty">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    ยังไม่มีผลงานหรือเป้าหมายของพนักงานขายในเดือนนี้
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($staff_perf as $i => $s):
                        $has_target = $s['target_amount'] > 0;
                        $pct = $has_target ? round(($s['total_revenue'] / $s['target_amount']) * 100, 1) : null;
                        $bar_color  = !$has_target ? 'bg-secondary' : ($pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger'));
                        $text_color = !$has_target ? 'text-muted'  : ($pct >= 100 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger'));
                        $badge_bg   = !$has_target ? 'secondary'  : ($pct >= 100 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'));
                        $rank = $i + 1;
                        $medal = ['🥇', '🥈', '🥉'][$i] ?? null;
                    ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="sd-perf-card <?= $rank === 1 && $s['total_revenue'] > 0 ? 'rank-1' : '' ?>"
                             onclick="window.open('sales_staff_report.php?id=<?= $s['id'] ?>&month=<?= $current_month ?>&year=<?= $current_year ?>', '_blank')"
                             title="คลิกเพื่อดูรายงานสรุปผลงาน (พิมพ์ได้)">
                            <div class="p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($medal && $s['total_revenue'] > 0): ?>
                                            <span class="sd-rank" style="background:var(--gold-tint)"><?= $medal ?></span>
                                        <?php else: ?>
                                            <span class="sd-rank bg-light text-muted">#<?= $rank ?></span>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold mb-0"><?= $h($s['name']) ?></h6>
                                            <small class="text-muted"><?= number_format($s['total_events']) ?> งาน</small>
                                        </div>
                                    </div>
                                    <div class="bg-<?= $badge_bg ?>-subtle rounded-circle p-3" title="พิมพ์รายงาน">
                                        <i class="bi bi-printer text-<?= $badge_bg ?> fs-4"></i>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">ยอดขาย</small>
                                        <span class="fw-bold text-primary fs-5">฿<?= number_format($s['total_revenue'], 0) ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">เงินมัดจำ</small>
                                        <span class="fw-bold fs-5">฿<?= number_format($s['total_deposit'], 0) ?></span>
                                    </div>
                                </div>
                                <div class="mb-1 d-flex justify-content-between">
                                    <small class="text-muted"><?= $has_target ? 'เป้าหมาย: ฿' . number_format($s['target_amount'], 0) : 'ยังไม่ตั้งเป้าหมาย' ?></small>
                                    <?php if ($has_target): ?>
                                        <small class="fw-bold <?= $text_color ?>"><?= number_format($pct, 1) ?>%</small>
                                    <?php endif; ?>
                                </div>
                                <div class="progress" style="height:8px">
                                    <div class="progress-bar <?= $bar_color ?>" style="width:<?= $has_target ? min($pct, 100) : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
