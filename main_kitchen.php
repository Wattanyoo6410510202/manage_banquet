<?php
include "config.php";
require_once "header.php";

// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);
    $break_time = $conn->real_escape_string($_POST['break_time'] ?? '');
    $break_type_id = intval($_POST['break_type_id'] ?? 0); 
    $break_menu = $conn->real_escape_string($_POST['break_menu'] ?? '');
    $break_pax = intval($_POST['break_pax'] ?? 0);
    $break_remark = $conn->real_escape_string($_POST['break_remark'] ?? '');

    // บันทึก หรือ แก้ไข
    if ($_POST['action'] == 'save') {
        if ($id > 0) {
            $sql = "UPDATE function_breaks SET 
                    break_time='$break_time', 
                    break_type_id=$break_type_id, 
                    break_menu='$break_menu', 
                    break_pax=$break_pax, 
                    break_remark='$break_remark' 
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO function_breaks (break_time, break_type_id, break_menu, break_pax, break_remark) 
                    VALUES ('$break_time', $break_type_id, '$break_menu', $break_pax, '$break_remark')";
        }

        if ($conn->query($sql)) {
            echo "<script>window.location.href='main_kitchen.php';</script>";
            exit;
        }
    }

    // ลบข้อมูล (AJAX)
    if ($_POST['action'] == 'delete') {
        if ($conn->query("DELETE FROM function_breaks WHERE id=$id")) {
            echo "success";
        }
        exit;
    }
}

// ดึงข้อมูลเบรก + JOIN กับตาราง Master เพื่อเอาชื่อประเภทมาแสดง
$breaks = $conn->query("SELECT b.*, t.type_name 
                       FROM function_breaks b 
                       LEFT JOIN master_break_types t ON b.break_type_id = t.id 
                       ORDER BY b.id DESC");
?>

<div class="container-fluid p-4">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="bi bi-cup-hot me-2"></i>จัดการ Coffee Break
                </div>
                <div class="card-body">
                    <form id="breakForm" method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="b_id" value="0">

                        <div class="mb-2">
                            <label class="small fw-bold">เวลาที่จัดเสิร์ฟ</label>
                            <input type="text" name="break_time" id="b_time" class="form-control form-control-sm" placeholder="เช่น 10:30 หรือ ช่วงเช้า" required>
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold">ประเภทเบรก</label>
                            <select name="break_type_id" id="b_type_id" class="form-select form-select-sm" required>
                                <option value="">-- เลือกประเภทเบรก --</option>
                                <?php
                                $types = $conn->query("SELECT * FROM master_break_types ORDER BY id ASC");
                                while ($t = $types->fetch_assoc()):
                                ?>
                                    <option value="<?= $t['id'] ?>"><?= $t['type_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold">รายการเมนูของว่าง</label>
                            <textarea name="break_menu" id="b_menu" class="form-control form-control-sm" rows="5" placeholder="ระบุรายการอาหารและเครื่องดื่ม..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">จำนวน (Pax)</label>
                                <input type="number" name="break_pax" id="b_pax" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">หมายเหตุ</label>
                                <input type="text" name="break_remark" id="b_remark" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm">บันทึกข้อมูลเบรก</button>
                            <button type="button" class="btn btn-light btn-sm border" onclick="resetForm()">ล้างฟอร์ม</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold border-bottom">
                    <i class="bi bi-table me-2"></i>รายการ Coffee Break ที่บันทึกไว้
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-dark small">
                                <tr>
                                    <th width="20%">เวลา / ประเภท</th>
                                    <th>เมนูของว่าง</th>
                                    <th width="15%" class="text-center">จำนวน (Pax)</th>
                                    <th width="15%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if ($breaks->num_rows > 0): ?>
                                    <?php while ($row = $breaks->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($row['break_time']) ?></div>
                                                <div class="badge bg-light text-dark border fw-normal">
                                                    <?= htmlspecialchars($row['type_name'] ?? 'ไม่ระบุประเภท') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="mb-1"><?= nl2br(htmlspecialchars($row['break_menu'])) ?></div>
                                                <?php if ($row['break_remark']): ?>
                                                    <small class="text-danger">* <?= htmlspecialchars($row['break_remark']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-bold"><?= number_format($row['break_pax']) ?></td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary border-0" onclick='editBreak(<?= json_encode($row) ?>)'>
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteBreak(<?= $row['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center p-4 text-muted">ยังไม่มีข้อมูลรายการเบรก</td></tr>
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
    // ฟังก์ชันดึงข้อมูลมาแก้ที่ฟอร์ม
    function editBreak(data) {
        document.getElementById('b_id').value = data.id;
        document.getElementById('b_time').value = data.break_time;
        document.getElementById('b_type_id').value = data.break_type_id; 
        document.getElementById('b_menu').value = data.break_menu;
        document.getElementById('b_pax').value = data.break_pax;
        document.getElementById('b_remark').value = data.break_remark;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ฟังก์ชันล้างฟอร์ม
    function resetForm() {
        document.getElementById('breakForm').reset();
        document.getElementById('b_id').value = 0;
    }

    // ฟังก์ชันลบข้อมูล
    function deleteBreak(id) {
        if (confirm('ยืนยันการลบรายการเบรกนี้ไหมจาร?')) {
            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('main_kitchen.php', { method: 'POST', body: fd })
                .then(res => res.text()).then(data => {
                    if (data.trim() === 'success') {
                        window.location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาดในการลบข้อมูล');
                    }
                });
        }
    }
</script>

<?php include "footer.php"; ?>