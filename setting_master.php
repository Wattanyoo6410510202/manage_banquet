<?php
include "config.php";
require_once "header.php";

// --- ส่วนจัดการข้อมูล (Logic) ---
if (isset($_POST['action'])) {
    $table = $_POST['table_name']; // รับชื่อตารางที่จะจัดการ
    $id = intval($_POST['id'] ?? 0);
    $name = $conn->real_escape_string($_POST['type_name'] ?? '');

    if ($_POST['action'] == 'save') {
        if ($id > 0) {
            $sql = "UPDATE $table SET type_name='$name' WHERE id=$id";
        } else {
            $sql = "INSERT INTO $table (type_name) VALUES ('$name')";
        }
        $conn->query($sql);
    }

    if ($_POST['action'] == 'delete') {
        $conn->query("DELETE FROM $table WHERE id=$id");
        echo "success";
        exit;
    }
    
    echo "<script>window.location.href='setting_master.php';</script>";
    exit;
}

// ดึงข้อมูลทั้ง 2 ตาราง
$menu_types = $conn->query("SELECT * FROM master_menu_types ORDER BY id ASC");
$break_types = $conn->query("SELECT * FROM master_break_types ORDER BY id ASC");
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h4 class="fw-bold"><i class="bi bi-gear-fill me-2"></i>ตั้งค่าข้อมูล Master</h4>
            <hr>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-egg-fried me-2"></i>ประเภทอาหาร / แพ็กเกจ
                </div>
                <div class="card-body">
                    <form method="POST" class="input-group mb-3">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="table_name" value="master_menu_types">
                        <input type="hidden" name="id" id="menu_id" value="0">
                        <input type="text" name="type_name" id="menu_name" class="form-control" placeholder="เพิ่มประเภทอาหารใหม่..." required>
                        <button class="btn btn-primary" type="submit">บันทึก</button>
                        <button class="btn btn-outline-secondary" type="button" onclick="resetSettingForm('menu')">ล้าง</button>
                    </form>

                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover border">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">ID</th>
                                    <th>ชื่อประเภท</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $menu_types->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['type_name']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary border-0" onclick="editSetting('menu', <?= $row['id'] ?>, '<?= $row['type_name'] ?>')"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteSetting('master_menu_types', <?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
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
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-cup-hot me-2"></i>ประเภท Coffee Break
                </div>
                <div class="card-body">
                    <form method="POST" class="input-group mb-3">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="table_name" value="master_break_types">
                        <input type="hidden" name="id" id="break_id" value="0">
                        <input type="text" name="type_name" id="break_name" class="form-control" placeholder="เพิ่มประเภทเบรกใหม่..." required>
                        <button class="btn btn-warning" type="submit">บันทึก</button>
                        <button class="btn btn-outline-secondary" type="button" onclick="resetSettingForm('break')">ล้าง</button>
                    </form>

                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover border">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">ID</th>
                                    <th>ชื่อประเภท</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $break_types->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['type_name']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary border-0" onclick="editSetting('break', <?= $row['id'] ?>, '<?= $row['type_name'] ?>')"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteSetting('master_break_types', <?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
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
    // ฟังก์ชันช่วยเติมข้อมูลเวลาจะแก้ไข
    function editSetting(type, id, name) {
        document.getElementById(type + '_id').value = id;
        document.getElementById(type + '_name').value = name;
        document.getElementById(type + '_name').focus();
    }

    // ล้างฟอร์ม
    function resetSettingForm(type) {
        document.getElementById(type + '_id').value = 0;
        document.getElementById(type + '_name').value = '';
    }

    // ลบข้อมูลแบบ AJAX
    function deleteSetting(tableName, id) {
        if (confirm('ยืนยันการลบข้อมูลนี้ใช่ไหมจาร? รายการอาหารที่ผูกกับ ID นี้อาจจะไม่แสดงชื่อนะ!')) {
            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('table_name', tableName);
            fd.append('id', id);

            fetch('setting_master.php', { method: 'POST', body: fd })
            .then(res => res.text())
            .then(data => {
                if(data.trim() === 'success') window.location.reload();
            });
        }
    }
</script>

<?php include "footer.php"; ?>