<?php
include "config.php";

$current_user = trim($_SESSION['user_name'] ?? '');
$user_role = strtolower(trim($_SESSION['role'] ?? 'viewer'));

// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    $action = $_POST['action'];
    $allowed_tables = ['master_menu_types', 'master_break_types', 'master_menu_categories'];
    $table = $_POST['table_name'] ?? '';

    if (!in_array($table, $allowed_tables)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid table']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $name = $conn->real_escape_string($_POST['type_name'] ?? '');

    if ($action == 'save') {
        $category_id = 0;
        $sort_order = 0;

        if ($table === 'master_menu_types') {
            $category_id = intval($_POST['category_id'] ?? 0);
            if ($id > 0) {
                $sql = "UPDATE $table SET type_name='$name', category_id=" . ($category_id > 0 ? $category_id : 'NULL') . " WHERE id=$id";
            } else {
                $sql = "INSERT INTO $table (type_name, category_id) VALUES ('$name', " . ($category_id > 0 ? $category_id : 'NULL') . ")";
            }
        } elseif ($table === 'master_menu_categories') {
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $set_price = ($_POST['set_price'] ?? '') !== '' ? floatval($_POST['set_price']) : 'NULL';
            if ($id > 0) {
                $sql = "UPDATE $table SET category_name='$name', sort_order=$sort_order, set_price=$set_price WHERE id=$id";
            } else {
                $sql = "INSERT INTO $table (category_name, sort_order, set_price) VALUES ('$name', $sort_order, $set_price)";
            }
        } elseif ($table === 'master_break_types') {
            $break_price = ($_POST['break_price'] ?? '') !== '' ? floatval($_POST['break_price']) : 'NULL';
            if ($id > 0) {
                $sql = "UPDATE $table SET type_name='$name', break_price=$break_price WHERE id=$id";
            } else {
                $sql = "INSERT INTO $table (type_name, break_price) VALUES ('$name', $break_price)";
            }
        } else {
            if ($id > 0) {
                $sql = "UPDATE $table SET type_name='$name' WHERE id=$id";
            } else {
                $sql = "INSERT INTO $table (type_name) VALUES ('$name')";
            }
        }

        if (!$conn->query($sql)) {
            echo json_encode(['status' => 'error', 'message' => $conn->error, 'sql' => $sql]);
            exit;
        }

        if ($id > 0) {
            $cat_name = '';
            if ($table === 'master_menu_types' && $category_id > 0) {
                $cr = $conn->query("SELECT category_name FROM master_menu_categories WHERE id=$category_id");
                $cat_name = $cr ? ($cr->fetch_assoc()['category_name'] ?? '') : '';
            }
            $resp_set_price = (isset($set_price) && $set_price !== 'NULL') ? $set_price : null;
            $resp_break_price = (isset($break_price) && $break_price !== 'NULL') ? $break_price : null;
            echo json_encode(['status' => 'updated', 'id' => $id, 'name' => $name, 'table' => $table, 'category_name' => $cat_name, 'set_price' => $resp_set_price, 'break_price' => $resp_break_price]);
        } else {
            $new_id = $conn->insert_id;
            $cat_name = '';
            if ($table === 'master_menu_types' && $category_id > 0) {
                $cr = $conn->query("SELECT category_name FROM master_menu_categories WHERE id=$category_id");
                $cat_name = $cr ? ($cr->fetch_assoc()['category_name'] ?? '') : '';
            }
            $resp_set_price = (isset($set_price) && $set_price !== 'NULL') ? $set_price : null;
            $resp_break_price = (isset($break_price) && $break_price !== 'NULL') ? $break_price : null;
            echo json_encode(['status' => 'inserted', 'id' => $new_id, 'name' => $name, 'table' => $table, 'category_name' => $cat_name, 'set_price' => $resp_set_price, 'break_price' => $resp_break_price]);
        }
        exit;
    }

    if ($action == 'delete') {
        if (ob_get_length()) ob_clean();
        if ($conn->query("DELETE FROM $table WHERE id=$id")) {
            echo "success";
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }
}

// ดึงข้อมูล
$categories = $conn->query("SELECT * FROM master_menu_categories ORDER BY sort_order ASC, id ASC");
$menu_types = $conn->query("SELECT mmt.*, mmc.category_name FROM master_menu_types mmt LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id ORDER BY mmc.sort_order ASC, mmt.id ASC");
$break_types = $conn->query("SELECT * FROM master_break_types ORDER BY id ASC");

require_once "header.php";
?>

<div class="container-fluid p-0">
    <div class="row">
        <!-- ═══ ประเภทใหญ่ (Categories) ═══ -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <i class="bi bi-tags-fill me-2"></i>ประเภทใหญ่ (กลุ่มอาหาร)
                </div>
                <div class="card-body">
                    <form onsubmit="saveData(event, 'category')" class="mb-4">
                        <input type="hidden" id="category_id" value="0">
                        <div class="input-group shadow-sm mb-2">
                            <input type="text" id="category_name" class="form-control" placeholder="ชื่อประเภทใหญ่..." required>
                            <button class="btn btn-dark px-4" type="submit">บันทึก</button>
                            <button class="btn btn-light border" type="button" onclick="resetSettingForm('category')">ล้าง</button>
                        </div>
                        <input type="number" id="category_sort" class="form-control form-control-sm" placeholder="ลำดับ (sort_order)" value="0" style="max-width:180px;">
                        <input type="number" id="category_set_price" class="form-control form-control-sm mt-2" placeholder="ราคาเซต (บาท)" value="" style="max-width:180px;" step="0.01" min="0">
                    </form>

                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover align-middle border-start border-end">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="15%" class="ps-3">ID</th>
                                    <th>ชื่อกลุ่ม</th>
                                    <th width="15%" class="text-center">ลำดับ</th>
                                    <th width="15%" class="text-center">ราคาเซต</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="master_menu_categories_body">
                                <?php while($row = $categories->fetch_assoc()): ?>
                                <tr id="master_menu_categories-<?= $row['id'] ?>">
                                    <td class="ps-3 text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="name-text fw-semibold"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                    <td class="text-center"><small class="text-muted"><?= $row['sort_order'] ?></small></td>
                                    <td class="text-center"><small class="set-price-text"><?= ($row['set_price'] !== null) ? number_format($row['set_price'], 2) : '-' ?></small></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary" onclick="editSetting('category', <?= $row['id'] ?>, '<?= addslashes($row['category_name']) ?>', <?= $row['sort_order'] ?>, 0, <?= ($row['set_price'] !== null) ? $row['set_price'] : "''" ?>)"><i class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm text-danger" onclick="deleteSetting('master_menu_categories', <?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
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

        <!-- ═══ ประเภทย่อย (Menu Types) ═══ -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <i class="bi bi-egg-fried me-2"></i>ประเภทอาหาร / แพ็กเกจ
                </div>
                <div class="card-body">
                    <form onsubmit="saveData(event, 'menu')" class="mb-4">
                        <input type="hidden" id="menu_id" value="0">
                        <div class="mb-2">
                            <select id="menu_category" class="form-select form-select-sm">
                                <option value="0">-- ไม่ระบุกลุ่ม --</option>
                                <?php
                                $categories->data_seek(0);
                                while($cat = $categories->fetch_assoc()):
                                ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
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
                                    <th>กลุ่ม</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="master_menu_types_body">
                                <?php while($row = $menu_types->fetch_assoc()): ?>
                                <tr id="master_menu_types-<?= $row['id'] ?>">
                                    <td class="ps-3 text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="name-text fw-semibold"><?= htmlspecialchars($row['type_name']) ?></span></td>
                                    <td><span class="badge bg-light text-dark cat-text"><?= htmlspecialchars($row['category_name'] ?? '-') ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary" onclick="editSetting('menu', <?= $row['id'] ?>, '<?= addslashes($row['type_name']) ?>', 0, <?= $row['category_id'] ?? 0 ?>)"><i class="bi bi-pencil-square"></i></button>
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

        <!-- ═══ ประเภท Coffee Break ═══ -->
        <div class="col-md-4 mb-4">
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
                        <input type="number" id="break_price" class="form-control form-control-sm mt-2" placeholder="ราคาเบรก (บาท)" value="" style="max-width:180px;" step="0.01" min="0">
                    </form>

                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover align-middle border-start border-end">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="15%" class="ps-3">ID</th>
                                    <th>ชื่อประเภท</th>
                                    <th width="15%" class="text-center">ราคา</th>
                                    <th width="20%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="master_break_types_body">
                                <?php while($row = $break_types->fetch_assoc()): ?>
                                <tr id="master_break_types-<?= $row['id'] ?>">
                                    <td class="ps-3 text-muted small">#<?= $row['id'] ?></td>
                                    <td><span class="name-text fw-semibold"><?= htmlspecialchars($row['type_name']) ?></span></td>
                                    <td class="text-center"><small class="break-price-text"><?= ($row['break_price'] !== null) ? number_format($row['break_price'], 2) : '-' ?></small></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary" onclick="editSetting('break', <?= $row['id'] ?>, '<?= addslashes($row['type_name']) ?>', 0, 0, <?= ($row['break_price'] !== null) ? $row['break_price'] : "''" ?>)"><i class="bi bi-pencil-square"></i></button>
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
const categoryMap = {};
<?php
$categories->data_seek(0);
while($cat = $categories->fetch_assoc()) {
    echo "categoryMap[{$cat['id']}] = '" . addslashes($cat['category_name']) . "';";
}
?>

function saveData(event, type) {
    event.preventDefault();
    const id = document.getElementById(type + '_id').value;
    const name = document.getElementById(type + '_name').value;
    const tableMap = { 'menu': 'master_menu_types', 'break': 'master_break_types', 'category': 'master_menu_categories' };
    const tableName = tableMap[type];

    let fd = new FormData();
    fd.append('action', 'save');
    fd.append('table_name', tableName);
    fd.append('id', id);
    fd.append('type_name', name);

    if (type === 'menu') {
        const catId = document.getElementById('menu_category').value;
        fd.append('category_id', catId);
    }
    if (type === 'category') {
        fd.append('sort_order', document.getElementById('category_sort').value || 0);
        fd.append('set_price', document.getElementById('category_set_price').value);
    }
    if (type === 'break') {
        fd.append('break_price', document.getElementById('break_price').value);
    }

    fetch('setting_master.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'updated') {
            const row = document.getElementById(res.table + '-' + res.id);
            row.querySelector('.name-text').innerText = res.name;
            if (res.table === 'master_menu_types' && row.querySelector('.cat-text')) {
                row.querySelector('.cat-text').innerText = res.category_name || '-';
            }
            if (res.table === 'master_menu_categories' && row.querySelector('.set-price-text')) {
                row.querySelector('.set-price-text').innerText = (res.set_price !== null && res.set_price !== '') ? Number(res.set_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '-';
            }
            if (res.table === 'master_break_types' && row.querySelector('.break-price-text')) {
                row.querySelector('.break-price-text').innerText = (res.break_price !== null && res.break_price !== '') ? Number(res.break_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '-';
            }
            row.style.backgroundColor = '#e0f2fe';
            setTimeout(() => row.style.backgroundColor = 'transparent', 1000);
        } else if (res.status === 'inserted') {
            const tbody = document.getElementById(res.table + '_body');
            let extraCol = '';
            let extraClass = '';
            if (res.table === 'master_menu_types') {
                extraCol = `<td><span class="badge bg-light text-dark cat-text">${res.category_name || '-'}</span></td>`;
            } else if (res.table === 'master_menu_categories') {
                const sp = (res.set_price !== null && res.set_price !== '') ? Number(res.set_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '-';
                extraCol = `<td class="text-center"><small class="text-muted">${document.getElementById('category_sort').value || 0}</small></td>
                            <td class="text-center"><small class="set-price-text">${sp}</small></td>`;
            } else if (res.table === 'master_break_types') {
                const bp = (res.break_price !== null && res.break_price !== '') ? Number(res.break_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '-';
                extraCol = `<td class="text-center"><small class="break-price-text">${bp}</small></td>`;
            }
            let setPriceArg = "''";
            if (res.table === 'master_menu_categories') setPriceArg = (res.set_price !== null && res.set_price !== '') ? res.set_price : "''";
            let breakPriceArg = "''";
            if (res.table === 'master_break_types') breakPriceArg = (res.break_price !== null && res.break_price !== '') ? res.break_price : "''";
            const priceArg = (res.table === 'master_break_types') ? breakPriceArg : setPriceArg;
            const newRow = `
                <tr id="${res.table}-${res.id}" style="background-color: #dcfce7;">
                    <td class="ps-3 text-muted small">#${res.id}</td>
                    <td><span class="name-text fw-semibold">${res.name}</span></td>
                    ${extraCol}
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm text-primary" onclick="editSetting('${type}', ${res.id}, '${res.name.replace(/'/g,"\\'")}', 0, 0, ${priceArg})"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm text-danger" onclick="deleteSetting('${res.table}', ${res.id})"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', newRow);
            const addedRow = document.getElementById(res.table + '-' + res.id);
            setTimeout(() => addedRow.style.backgroundColor = 'transparent', 1000);
            if (res.table === 'master_menu_categories') {
                categoryMap[res.id] = res.name;
                const catSelect = document.getElementById('menu_category');
                const opt = document.createElement('option');
                opt.value = res.id;
                opt.text = res.name;
                catSelect.add(opt);
            }
        }
        resetSettingForm(type);
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่อีกครั้ง');
    });
}

function editSetting(type, id, name, sortOrder, catId, setPrice) {
    document.getElementById(type + '_id').value = id;
    document.getElementById(type + '_name').value = name;
    document.getElementById(type + '_name').focus();
    document.getElementById(type + '_name').style.border = '2px solid #0ea5e9';
    if (type === 'category' && sortOrder !== undefined) {
        document.getElementById('category_sort').value = sortOrder || 0;
    }
    if (type === 'category') {
        document.getElementById('category_set_price').value = (setPrice !== undefined && setPrice !== null && setPrice !== '') ? setPrice : '';
    }
    if (type === 'break') {
        document.getElementById('break_price').value = (setPrice !== undefined && setPrice !== null && setPrice !== '') ? setPrice : '';
    }
    if (type === 'menu' && catId !== undefined) {
        document.getElementById('menu_category').value = catId || 0;
    }
}

function resetSettingForm(type) {
    document.getElementById(type + '_id').value = 0;
    document.getElementById(type + '_name').value = '';
    document.getElementById(type + '_name').style.border = '1px solid #dee2e6';
    if (type === 'menu') {
        document.getElementById('menu_category').value = 0;
    }
    if (type === 'category') {
        document.getElementById('category_sort').value = 0;
        document.getElementById('category_set_price').value = '';
    }
    if (type === 'break') {
        document.getElementById('break_price').value = '';
    }
}

function deleteSetting(tableName, id) {
    if (confirm('เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) {
        let fd = new FormData();
        fd.append('action', 'delete');
        fd.append('table_name', tableName);
        fd.append('id', id);

        fetch('setting_master.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                const row = document.getElementById(tableName + '-' + id);
                if (row) {
                    row.style.transition = '0.4s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 400);
                }
                if (tableName === 'master_menu_categories') {
                    delete categoryMap[id];
                    const catSelect = document.getElementById('menu_category');
                    for (let i = catSelect.options.length - 1; i >= 0; i--) {
                        if (catSelect.options[i].value == id) catSelect.remove(i);
                    }
                }
            }
        });
    }
}
</script>

<?php include "footer.php"; ?>
