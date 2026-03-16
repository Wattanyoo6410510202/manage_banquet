<?php
include "config.php";
// ย้าย header ไว้หลัง API check เพื่อป้องกัน output record หลุดไปตอนลบด้วย AJAX
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    if ($conn->query("DELETE FROM function_types WHERE id=$id")) {
        echo "success";
    } else {
        echo "error";
    }
    exit; // จบการทำงานทันที ไม่ให้รันโค้ดส่วนอื่นต่อ
}

require_once "header.php"; 

// ==========================================
// 🛡️ API SECTION (Save Logic)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $type_name = $conn->real_escape_string($_POST['type_name']);

    if ($id > 0) {
        $sql = "UPDATE function_types SET type_name='$type_name' WHERE id=$id";
    } else {
        $sql = "INSERT INTO function_types (type_name) VALUES ('$type_name')";
    }
    
    if ($conn->query($sql)) {
        echo "<script>alert('บันทึกประเภทงานสำเร็จ!'); window.location.href='setting_type.php';</script>";
        exit;
    }
}

// ดึงข้อมูลประเภทงานทั้งหมดมาแสดง
$types = $conn->query("SELECT * FROM function_types ORDER BY id DESC");
?>

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-gold fw-bold">
                        <i class="bi bi-tag-fill me-2"></i>จัดการประเภทงาน
                    </div>
                    <div class="card-body">
                        <form id="typeForm" method="POST">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" id="type_id" value="0">
                            
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary">ชื่อประเภทงาน</label>
                                <input type="text" name="type_name" id="type_name" class="form-control" required placeholder="เช่น Wedding, Seminar">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                                </button>
                                <button type="button" class="btn btn-light border" onclick="resetForm()">ยกเลิก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-list-ul me-2 text-primary"></i>ประเภทงานทั้งหมดในระบบ
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">ID</th>
                                        <th>ชื่อประเภทงาน</th>
                                        <th>วันที่สร้าง</th>
                                        <th width="120" class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($types->num_rows > 0): ?>
                                        <?php while($row = $types->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark border">#<?= $row['id'] ?></span></td>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($row['type_name']) ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick='editType(<?= json_encode($row) ?>)'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteType(<?= $row['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center p-4 text-muted">ยังไม่มีข้อมูลประเภทงาน</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันสำหรับส่งค่าจากตารางเข้าฟอร์มเพื่อแก้ไข
function editType(data) {
    document.getElementById('type_id').value = data.id;
    document.getElementById('type_name').value = data.type_name;
    
    // เปลี่ยนสไตล์หัวข้อฟอร์มให้ชัดเจน
    const header = document.querySelector('.card-header.bg-dark');
    header.classList.replace('bg-dark', 'bg-primary');
    header.innerHTML = '<i class="bi bi-pencil-square me-2"></i>กำลังแก้ไข: ' + data.type_name;
}

// ฟังก์ชันรีเซ็ตฟอร์ม
function resetForm() {
    document.getElementById('typeForm').reset();
    document.getElementById('type_id').value = 0;
    const header = document.querySelector('.card-header');
    header.className = 'card-header bg-dark text-gold fw-bold';
    header.innerHTML = '<i class="bi bi-tag-fill me-2"></i>จัดการประเภทงาน';
}

// ฟังก์ชันลบข้อมูลผ่าน AJAX
function deleteType(id) {
    if (confirm('จารแน่ใจนะว่าจะลบประเภทงานนี้? (ถ้ามีงานที่ใช้ประเภทนี้อยู่อาจจะส่งผลกระทบ)')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('setting_type.php', {
            method: 'POST',
            body: formData
        }).then(res => res.text()).then(data => {
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