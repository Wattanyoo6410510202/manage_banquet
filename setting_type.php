<?php
include "config.php";

$current_user = trim($_SESSION['user_name'] ?? '');
$user_role = strtolower(trim($_SESSION['role'] ?? 'viewer'));

// 🛡️ ด่านที่ 1: เช็คสิทธิ์ (ตอนนี้ระบบรู้จัก $data และ $current_user แล้ว)
if ($user_role !== 'admin' && $user_role !== 'gm' && $user_role !== 'staff' && trim($data['created_by']) !== $current_user) {
    // ใช้ JavaScript Redirect เพื่อเลี่ยงปัญหา Headers already sent
    echo "<script>window.location.href='access_denied.php';</script>";
    exit();
}
// ==========================================
// 🛡️ API SECTION (Logic) - ต้องอยู่ก่อนการแสดงผล
// ==========================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'save') {
        $id = intval($_POST['id'] ?? 0);
        $type_name = $conn->real_escape_string($_POST['type_name']);

        if ($id > 0) {
            $sql = "UPDATE function_types SET type_name='$type_name' WHERE id=$id";
            $conn->query($sql);
            echo json_encode(['status' => 'updated', 'id' => $id, 'name' => $type_name]);
        } else {
            $sql = "INSERT INTO function_types (type_name) VALUES ('$type_name')";
            $conn->query($sql);
            $new_id = $conn->insert_id;
            echo json_encode([
                'status' => 'inserted', 
                'id' => $new_id, 
                'name' => $type_name, 
                'date' => date('d/m/Y H:i')
            ]);
        }
        exit;
    }

    if ($action == 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM function_types WHERE id=$id")) {
            echo "success";
        } else {
            echo "error";
        }
        exit;
    }
}

// ดึงข้อมูลประเภทงานทั้งหมด
$types = $conn->query("SELECT * FROM function_types ORDER BY id DESC");
require_once "header.php"; 
?>

<div class="container-fluid p-0">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px;">
                <div id="formHeader" class="card-header bg-dark text-white py-3">
                    จัดการประเภทงาน
                </div>
                <div class="card-body">
                    <form id="typeForm" onsubmit="saveType(event)">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="type_id" value="0">
                        
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">ชื่อประเภทงาน (Event Type)</label>
                            <input type="text" name="type_name" id="type_name" class="form-control form-control-lg" required placeholder="เช่น Wedding, Seminar">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" id="btnSubmit" class="btn btn-dark fw-bold">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                            </button>
                            <button type="button" class="btn btn-light btn-sm border-0" onclick="resetForm()">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>ประเภทงานทั้งหมด</span>
                    <span id="typeCount" class="badge bg-secondary rounded-pill"><?= $types->num_rows ?> รายการ</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted text-center">
                                    <th width="100">ID</th>
                                    <th class="text-start">ชื่อประเภทงาน</th>
                                    <th>วันที่สร้าง</th>
                                    <th width="120">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="typeTableBody">
                                <?php if($types->num_rows > 0): ?>
                                    <?php while($row = $types->fetch_assoc()): ?>
                                    <tr id="type-row-<?= $row['id'] ?>" class="text-center">
                                        <td><span class="text-muted small">#<?= $row['id'] ?></span></td>
                                        <td class="text-start fw-bold text-dark type-name-cell"><?= htmlspecialchars($row['type_name']) ?></td>
                                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary border-0" 
                                                    onclick='editType(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0" 
                                                    onclick="deleteType(<?= $row['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr id="no-data-row"><td colspan="4" class="text-center p-5 text-muted">ยังไม่มีข้อมูลประเภทงาน</td></tr>
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
// ฟังก์ชันบันทึกข้อมูล (Save/Update) แบบ AJAX
function saveType(e) {
    e.preventDefault();
    const form = document.getElementById('typeForm');
    const fd = new FormData(form);
    const typeId = document.getElementById('type_id').value;

    fetch('setting_type.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'updated') {
            // อัปเดตข้อมูลในตารางทันที
            const row = document.getElementById('type-row-' + res.id);
            row.querySelector('.type-name-cell').innerText = res.name;
            
            // เอฟเฟกต์สีฟ้าแจ้งเตือนการแก้ไข
            row.style.backgroundColor = '#e0f2fe';
            setTimeout(() => row.style.backgroundColor = 'transparent', 1000);
            
        } else if (res.status === 'inserted') {
            // ลบแถว "ไม่มีข้อมูล" ถ้ามี
            const noData = document.getElementById('no-data-row');
            if (noData) noData.remove();

            // เพิ่มแถวใหม่ลงในตาราง (บนสุด)
            const newRow = `
                <tr id="type-row-${res.id}" class="text-center" style="background-color: #f0fdf4; transition: 0.5s;">
                    <td><span class="text-muted small">#${res.id}</span></td>
                    <td class="text-start fw-bold text-dark type-name-cell">${res.name}</td>
                    <td class="small text-muted">${res.date}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary border-0" onclick='editType({"id":"${res.id}","type_name":"${res.name}"})'>
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="deleteType(${res.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
            document.getElementById('typeTableBody').insertAdjacentHTML('afterbegin', newRow);
            
            // เอฟเฟกต์สีเขียวจางหาย
            setTimeout(() => {
                document.getElementById('type-row-' + res.id).style.backgroundColor = 'transparent';
            }, 1000);
            
            // อัปเดตตัวเลขจำนวนรายการ
            updateCount(1);
        }
        resetForm();
    })
    .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
}

function editType(data) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.getElementById('type_id').value = data.id;
    document.getElementById('type_name').value = data.type_name;
    
    const header = document.getElementById('formHeader');
    header.classList.replace('bg-dark', 'bg-primary');
    header.innerHTML = '<i class="bi bi-pencil-square me-2"></i>แก้ไขประเภทงาน: ' + data.type_name;
    document.getElementById('btnSubmit').innerHTML = '<i class="bi bi-check-circle me-1"></i> อัปเดตข้อมูล';
}

function resetForm() {
    document.getElementById('typeForm').reset();
    document.getElementById('type_id').value = 0;
    const header = document.getElementById('formHeader');
    header.className = 'card-header bg-dark text-white py-3';
    header.innerHTML = '<i class="bi bi-tag-fill me-2 text-warning"></i>จัดการประเภทงาน';
    document.getElementById('btnSubmit').innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูล';
}

function deleteType(id) {
    if (confirm('ลบรายการนี้ใช่ไหมจาร?')) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        fetch('setting_type.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                const row = document.getElementById('type-row-' + id);
                setTimeout(() => {
                    row.remove();
                    updateCount(-1);
                }, 300);
            }
        });
    }
}

function updateCount(n) {
    const badge = document.getElementById('typeCount');
    let current = parseInt(badge.innerText);
    badge.innerText = (current + n) + ' รายการ';
}
</script>

<?php include "footer.php"; ?>