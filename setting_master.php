<?php
include "config.php";

// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $allowed_tables = ['master_menu_types', 'master_break_types'];
    $table = $_POST['table_name'];

    if (!in_array($table, $allowed_tables)) {
        exit; // ปิดเงียบๆ ถ้าตารางไม่ถูกต้อง
    }

    $id = intval($_POST['id'] ?? 0);
    $name = $conn->real_escape_string($_POST['type_name'] ?? '');

    // กรณีบันทึก (Save/Update) - เปลี่ยนเป็นส่งค่ากลับแบบ JSON
    if ($action == 'save') {
        if ($id > 0) {
            $sql = "UPDATE $table SET type_name='$name' WHERE id=$id";
            $conn->query($sql);
            echo json_encode(['status' => 'updated', 'id' => $id, 'name' => $name, 'table' => $table]);
        } else {
            $sql = "INSERT INTO $table (type_name) VALUES ('$name')";
            $conn->query($sql);
            $new_id = $conn->insert_id;
            echo json_encode(['status' => 'inserted', 'id' => $new_id, 'name' => $name, 'table' => $table]);
        }
        exit;
    }

    // กรณีลบข้อมูล (Delete)
    if ($action == 'delete') {
        if (ob_get_length()) ob_clean();
        if ($conn->query("DELETE FROM $table WHERE id=$id")) {
            echo "success";
        }
        exit;
    }
}

// ดึงข้อมูลแสดงผลตามปกติสำหรับโหลดหน้าครั้งแรก
$menu_types = $conn->query("SELECT * FROM master_menu_types ORDER BY id ASC");
$break_types = $conn->query("SELECT * FROM master_break_types ORDER BY id ASC");

require_once "header.php";
?>

<div class="container-fluid p-0">
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <i class="bi bi-egg-fried me-2"></i>ประเภทอาหาร / แพ็กเกจ
                </div>
                <div class="card-body">
                    <form onsubmit="saveData(event, 'menu')" class="mb-4">
                        <input type="hidden" id="menu_id" value="0">
                        <div class="input-group shadow-sm">
                            <input type="text" id="menu_name" class="form-control" placeholder="ชื่อประเภทอาหาร..." required>
                            <button class="btn btn-primary px-4" type="submit">บันทึก</button>
                            <button class="btn btn-light border" type="button" onclick="resetSettingForm('menu')">ล้าง</button>
                        </div>
                    </form>

                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover align-middle border-start border-end">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="15%" class="ps-3">ID</th>
                                    <th>ชื่อประเภท</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="master_menu_types_body">
                                <?php while($row = $menu_types->fetch_assoc()): ?>
                                <tr id="master_menu_types-<?= $row['id'] ?>">
                                    <td class="ps-3 text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="name-text fw-semibold"><?= htmlspecialchars($row['type_name']) ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary" onclick="editSetting('menu', <?= $row['id'] ?>, '<?= addslashes($row['type_name']) ?>')"><i class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm text-danger" onclick="deleteSetting('master_menu_types', <?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark py-3">
                    <i class="bi bi-cup-hot me-2"></i>ประเภท Coffee Break
                </div>
                <div class="card-body">
                    <form onsubmit="saveData(event, 'break')" class="mb-4">
                        <input type="hidden" id="break_id" value="0">
                        <div class="input-group shadow-sm">
                            <input type="text" id="break_name" class="form-control" placeholder="ชื่อประเภทเบรก..." required>
                            <button class="btn btn-warning fw-bold px-4" type="submit">บันทึก</button>
                            <button class="btn btn-light border" type="button" onclick="resetSettingForm('break')">ล้าง</button>
                        </div>
                    </form>

                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover align-middle border-start border-end">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="15%" class="ps-3">ID</th>
                                    <th>ชื่อประเภท</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="master_break_types_body">
                                <?php while($row = $break_types->fetch_assoc()): ?>
                                <tr id="master_break_types-<?= $row['id'] ?>">
                                    <td class="ps-3 text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="name-text fw-semibold"><?= htmlspecialchars($row['type_name']) ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary" onclick="editSetting('break', <?= $row['id'] ?>, '<?= addslashes($row['type_name']) ?>')"><i class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm text-danger" onclick="deleteSetting('master_break_types', <?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
                                        </div>
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

<script>
// ฟังก์ชันบันทึกข้อมูลแบบ AJAX (ใช้ทั้งเพิ่มและแก้ไข)
function saveData(event, type) {
    event.preventDefault();
    const id = document.getElementById(type + '_id').value;
    const name = document.getElementById(type + '_name').value;
    const tableName = (type === 'menu') ? 'master_menu_types' : 'master_break_types';

    let fd = new FormData();
    fd.append('action', 'save');
    fd.append('table_name', tableName);
    fd.append('id', id);
    fd.append('type_name', name);

    fetch('setting_master.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(res => {
        if(res.status === 'updated') {
            // แก้ไข: อัปเดตข้อความในแถวเดิม
            const row = document.getElementById(res.table + '-' + res.id);
            row.querySelector('.name-text').innerText = res.name;
            row.style.backgroundColor = '#e0f2fe';
            setTimeout(() => row.style.backgroundColor = 'transparent', 1000);
        } else if(res.status === 'inserted') {
            // เพิ่มใหม่: สร้างแถวใหม่ไปต่อท้ายตาราง
            const tbody = document.getElementById(res.table + '_body');
            const newRow = `
                <tr id="${res.table}-${res.id}" style="background-color: #dcfce7;">
                    <td class="ps-3 text-muted small">#${res.id}</td>
                    <td><span class="name-text fw-semibold">${res.name}</span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm text-primary" onclick="editSetting('${type}', ${res.id}, '${res.name}')"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm text-danger" onclick="deleteSetting('${res.table}', ${res.id})"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', newRow);
            const addedRow = document.getElementById(res.table + '-' + res.id);
            setTimeout(() => addedRow.style.backgroundColor = 'transparent', 1000);
        }
        resetSettingForm(type);
    });
}

// เติมข้อมูลลงฟอร์ม
function editSetting(type, id, name) {
    document.getElementById(type + '_id').value = id;
    document.getElementById(type + '_name').value = name;
    document.getElementById(type + '_name').focus();
    document.getElementById(type + '_name').style.border = '2px solid #0ea5e9';
}

// รีเซ็ตฟอร์ม
function resetSettingForm(type) {
    document.getElementById(type + '_id').value = 0;
    document.getElementById(type + '_name').value = '';
    document.getElementById(type + '_name').style.border = '1px solid #dee2e6';
}

// ลบข้อมูลแบบ Fade Out
function deleteSetting(tableName, id) {
    if (confirm('เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) {
        let fd = new FormData();
        fd.append('action', 'delete');
        fd.append('table_name', tableName);
        fd.append('id', id);

        fetch('setting_master.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(data => {
            if(data.trim() === 'success') {
                const row = document.getElementById(tableName + '-' + id);
                if(row) {
                    row.style.transition = '0.4s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 400);
                }
            }
        });
    }
}
</script>

<?php include "footer.php"; ?>