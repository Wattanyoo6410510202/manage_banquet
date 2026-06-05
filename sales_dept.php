<?php
include "header.php";
include "config.php";

// เช็คสิทธิ์ (Admin หรือ Staff ที่เกี่ยวข้อง)
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'staff', 'gm', 'sale'])) {
    echo "<script>window.location.href='access_denied.php';</script>";
    exit;
}

// --- ข้อมูลเป้าหมายการขาย ---
$current_month = date('n');
$current_year = date('Y');

// ดึงรายการพนักงานเพื่อใช้ใน Select
$staffs = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff', 'Banquet_Staff', 'Admin', 'Sale') ORDER BY name ASC");
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">แผนกขาย (Sales Department)</h4>
            <small class="text-muted">บันทึกภาระงานและจัดการเป้าหมายการขาย</small>
        </div>
    </div>

    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <div class="row g-4">
        <!-- ส่วนตั้งค่าเป้าหมาย -->
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-gold"><i class="bi bi-plus-circle me-2"></i>ตั้งเป้าหมายการขายรายเดือน</h6>
                    <form action="api/save_sales_target.php" method="POST">
                        <input type="hidden" name="redirect" value="sales_dept.php">
                        <div class="mb-3">
                            <label class="small fw-bold">เลือกพนักงาน (Sales)</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- เลือกพนักงาน --</option>
                                <?php while($s = $staffs->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">เป้าหมายรายได้ (฿)</label>
                            <input type="number" name="target_amount" class="form-control" placeholder="เช่น 500000" required>
                        </div>
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="small fw-bold">เดือน</label>
                                <select name="target_month" class="form-select">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m == $current_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">ปี</label>
                                <select name="target_year" class="form-select">
                                    <?php for($y=date('Y'); $y<=date('Y')+1; $y++): ?>
                                        <option value="<?= $y ?>"><?= $y + 543 ?> (<?= $y ?>)</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 rounded-3">บันทึกเป้าหมาย</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- รายการเป้าหมาย -->
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4">รายการเป้าหมายทั้งหมด</h6>
                    <div class="table-responsive">
                        <table id="salesTargetTable" class="table table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>พนักงาน</th>
                                    <th>เดือน/ปี</th>
                                    <th>เป้าหมาย</th>
                                    <th class="text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $targets = $conn->query("SELECT st.*, u.name FROM sales_targets st JOIN users u ON st.user_id = u.id ORDER BY st.target_year DESC, st.target_month DESC");
                                while ($t = $targets->fetch_assoc()):
                                    ?>
                                <tr>
                                    <td><?= $t['name'] ?></td>
                                    <td><?= date('F', mktime(0, 0, 0, $t['target_month'], 1)) ?> <?= $t['target_year'] + 543 ?></td>
                                    <td class="fw-bold text-primary">฿<?= number_format($t['target_amount'], 2) ?></td>
                                    <td class="text-end">
                                        <a href="api/save_sales_target.php?delete_id=<?= $t['id'] ?>&redirect=sales_dept.php" 
                                           class="btn btn-sm btn-outline-danger border-0" 
                                           onclick="return confirm('ลบเป้าหมายนี้?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#salesTargetTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
        },
        pageLength: 10
    });
});
</script>

<?php include "footer.php"; ?>