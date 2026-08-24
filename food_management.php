<?php
include "config.php";
$user_role = strtolower($_SESSION['role'] ?? 'viewer');
// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
// ต้องอยู่ก่อนการส่ง Output ใดๆ เพื่อให้ Redirect ทำงานได้
// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    // ล้าง output buffer เพื่อไม่ให้มีช่องว่างหลุดออกไป
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json'); // บอก Browser ว่าจะส่ง JSON นะ

    if ($_POST['action'] == 'delete') {

        // 🚫 1. ด่านแรก: เช็คสิทธิ์ Viewer ห้ามลบรายละเอียดเมนู
        if ($user_role === 'viewer') {
            echo json_encode([
                'status' => 'error',
                'message' => 'ขออภัย! สิทธิ์ Viewer ไม่สามารถลบรายการอาหารได้'
            ]);
            exit;
        }

        // 🛡️ 2. Clean ID ให้ชัวร์ว่าเป็นตัวเลข (ป้องกัน SQL Injection)
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            // 🚀 3. ถ้าสิทธิ์ผ่านและ ID ถูกต้อง ถึงจะยอมให้ลบ
            if ($conn->query("DELETE FROM function_menu_details WHERE id=$id")) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ลบไม่สำเร็จ: ' . $conn->error
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID รายการที่ต้องการลบ']);
        }
        exit;
    }

    if ($_POST['action'] == 'delete_multi') {

        // 🚫 1. ด่านแรก: เช็คสิทธิ์ Viewer ห้ามลบรายละเอียดเมนู
        if ($user_role === 'viewer') {
            echo json_encode([
                'status' => 'error',
                'message' => 'ขออภัย! สิทธิ์ Viewer ไม่สามารถลบรายการอาหารได้'
            ]);
            exit;
        }

        // 🛡️ 2. Clean ID ทุกตัวให้ชัวร์ว่าเป็นตัวเลข (ป้องกัน SQL Injection)
        $ids = $_POST['ids'] ?? [];
        $ids = array_filter(array_map('intval', is_array($ids) ? $ids : []), fn($v) => $v > 0);

        if (!empty($ids)) {
            $id_list = implode(',', $ids);
            if ($conn->query("DELETE FROM function_menu_details WHERE id IN ($id_list)")) {
                echo json_encode(['status' => 'success', 'deleted' => array_values($ids)]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ลบไม่สำเร็จ: ' . $conn->error
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID รายการที่ต้องการลบ']);
        }
        exit;
    }

    if ($_POST['action'] == 'save') {
        $id = intval($_POST['id'] ?? 0);
        $menu_type_id = intval($_POST['menu_type_id'] ?? 0);
        $menu_items = $conn->real_escape_string($_POST['menu_items'] ?? '');
        $beverage_detail = $conn->real_escape_string($_POST['beverage_detail'] ?? '');
        $guarantee_pax = intval($_POST['guarantee_pax'] ?? 0);
        $price_per_pax = floatval($_POST['price_per_pax'] ?? 0);
        $cost_per_pax = floatval($_POST['cost_per_pax'] ?? 0);

        if ($id > 0) {
            $sql = "UPDATE function_menu_details SET 
                    menu_type_id=$menu_type_id, 
                    menu_items='$menu_items', 
                    beverage_detail='$beverage_detail', 
                    guarantee_pax=$guarantee_pax, 
                    price_per_pax=$price_per_pax,
                    cost_per_pax=$cost_per_pax 
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO function_menu_details (menu_type_id, menu_items, beverage_detail, guarantee_pax, price_per_pax, cost_per_pax) 
                    VALUES ($menu_type_id, '$menu_items', '$beverage_detail', $guarantee_pax, $price_per_pax, $cost_per_pax)";
        }

        if ($conn->query($sql)) {
            $t_res = $conn->query("SELECT t.type_name, c.category_name FROM master_menu_types t LEFT JOIN master_menu_categories c ON t.category_id = c.id WHERE t.id=$menu_type_id");
            $t_row = $t_res->fetch_assoc();

            echo json_encode([
                'status' => 'success',
                'id' => ($id > 0 ? $id : $conn->insert_id),
                'type_name' => $t_row['type_name'] ?? 'ไม่ระบุ',
                'category_name' => $t_row['category_name'] ?? '-',
                'is_update' => ($id > 0)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }
}

// --- 2. ดึงข้อมูล ---
$categories = $conn->query("SELECT * FROM master_menu_categories ORDER BY sort_order ASC");
$cat_list = [];
while ($c = $categories->fetch_assoc()) { $cat_list[] = $c; }

$types_query = $conn->query("SELECT * FROM master_menu_types ORDER BY id ASC");
$types_list = [];
while ($t = $types_query->fetch_assoc()) {
    $types_list[] = $t;
}

$menu_types_with_cat = $conn->query("SELECT mmt.id, mmt.type_name, mmt.category_id, mmc.category_name 
    FROM master_menu_types mmt 
    LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id 
    ORDER BY mmc.sort_order ASC, mmt.id ASC");
$menu_types_array = [];
while ($mt = $menu_types_with_cat->fetch_assoc()) {
    $menu_types_array[] = $mt;
}

$menus = $conn->query("SELECT m.*, t.type_name, mc.category_name 
                       FROM function_menu_details m 
                       LEFT JOIN master_menu_types t ON m.menu_type_id = t.id 
                       LEFT JOIN master_menu_categories mc ON t.category_id = mc.id
                       ORDER BY mc.sort_order ASC, t.id ASC, m.id DESC");

require_once "header.php";
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-success text-white fw-bold py-3">
                    <i class="bi bi-journal-plus me-2"></i>บันทึกเมนูอาหารมาตรฐาน
                </div>
                <div class="card-body">
                    <form id="menuForm" method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="m_id" value="0">

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">ประเภทอาหาร / ชื่อแพ็กเกจ</label>
                            <input type="hidden" name="menu_type_id" id="m_type_id" class="menu-type-id" value="">
                            <button type="button" class="btn-menu-type-picker" onclick="openMenuTypeModal(document.getElementById('m_type_id'))">
                                <i class="bi bi-grid-3x3-gap me-1"></i>เลือกประเภทเมนู
                            </button>
                            <span class="menu-type-label text-muted">ยังไม่ได้เลือก</span>
                        </div>

                        <div class="row">
                            <div class="col-4 mb-3">
                                <label class="small fw-bold mb-1">การันตี (Pax)</label>
                                <input type="number" name="guarantee_pax" id="m_pax" class="form-control" value="1"
                                    placeholder="100">
                            </div>
                            <div class="col-4 mb-3">
                                <label class="small fw-bold mb-1">ราคาขาย/หัว (บาท)</label>
                                <input type="number" step="0.01" name="price_per_pax" id="m_price" class="form-control"
                                    placeholder="450.00">
                            </div>
                            <div class="col-4 mb-3">
                                <label class="small fw-bold mb-1">ราคาทุน/หัว (บาท)</label>
                                <input type="number" step="0.01" name="cost_per_pax" id="m_cost" class="form-control"
                                    placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">รายการอาหาร (ระบุเป็นข้อๆ)</label>
                            <textarea name="menu_items" id="m_items" class="form-control" rows="6"
                                placeholder="1. ต้มยำกุ้ง..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">รายละเอียดเครื่องดื่ม</label>
                            <textarea name="beverage_detail" id="m_bev" class="form-control" rows="3"
                                placeholder="น้ำดื่ม, น้ำสมุนไพร..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <?php if ($user_role !== 'viewer'): ?>
                                <button type="submit" id="btnSubmit" class="btn btn-success fw-bold shadow-sm">
                                    <i class="bi bi-save me-1"></i> บันทึกข้อมูลเมนู
                                </button>
                                <button type="button" class="btn btn-light border btn-sm"
                                    onclick="resetMenuForm()">ล้างข้อมูล</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary px-3 py-1 fw-bold disabled"
                                    style="cursor: not-allowed;">
                                    <i class="bi bi-lock-fill me-2"></i>โหมดอ่านอย่างเดียว (Viewer)
                                </button>
                                <div class="text-center">
                                    <small class="text-danger" style="font-size: 0.7rem;">*
                                        คุณไม่มีสิทธิ์บันทึกหรือแก้ไขข้อมูล</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cup-hot me-2"></i></i>รายการ เมนู
                    </h5>

                    <div class="btn-group">
                        <?php if ($user_role !== 'viewer'): ?>
                            <button type="button" id="btnDeleteSelected"
                                class="btn btn-link btn-sm text-danger text-decoration-none p-1" title="ลบรายการที่เลือก" disabled>
                                <i class="bi bi-trash3 fs-5"></i>
                                <span class="d-none d-md-inline small ms-1">ลบที่เลือก (<span id="selectedCount">0</span>)</span>
                            </button>
                        <?php endif; ?>
                        <button type="button" id="customExcel"
                            class="btn btn-link btn-sm text-success text-decoration-none p-1" title="Excel">
                            <i class="bi bi-file-earmark-excel fs-5"></i>
                            <span class="d-none d-md-inline small ms-1">Excel</span>
                        </button>
                        <button type="button" id="customUpdateCost"
                            class="btn btn-link btn-sm text-warning text-decoration-none p-1" title="ปรับปรุงราคาทุน">
                            <i class="bi bi-cash-stack fs-5"></i>
                            <span class="d-none d-md-inline small ms-1">ปรับปรุงราคาทุน</span>
                        </button>
                        <button type="button" id="customPrint"
                            class="btn btn-link btn-sm text-secondary text-decoration-none p-1" title="Print">
                            <i class="bi bi-printer fs-5"></i>
                            <span class="d-none d-md-inline small ms-1">พิมพ์</span>
                        </button>
                    </div>

                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <select id="categoryFilter" class="form-select form-select-sm" onchange="filterByCategory(this.value)">
                            <option value="">-- ทุกกลุ่มอาหาร --</option>
                            <?php foreach ($cat_list as $c): ?>
                                <option value="<?= htmlspecialchars($c['category_name']) ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table id="menuTable" class="table table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr class="small text-uppercase">
                                    <?php if ($user_role !== 'viewer'): ?>
                                        <th class="text-center"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                    <?php endif; ?>
                                    <th>รายการเมนู</th>
                                    <th class="text-center">จำนวน</th>
                                    <th class="text-center">ราคาขาย/หัว</th>
                                    <th class="text-center">ราคาทุน/หัว</th>
                                    <th>กลุ่มอาหาร</th>
                                    <th>ประเภทอาหาร</th>
                                    <th class="text-">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php while ($row = $menus->fetch_assoc()): ?>
                                    <tr id="row-<?= $row['id'] ?>">
                                        <?php if ($user_role !== 'viewer'): ?>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input row-check" value="<?= $row['id'] ?>">
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div>
                                                <small
                                                    class=" menu-text"><?= nl2br(htmlspecialchars($row['menu_items'])) ?></small>
                                            </div>
                                        </td>
                                        <td class="col-pax text-center"><?= number_format($row['guarantee_pax']) ?></td>
                                        <td class="col-price text-center fw-bold text-primary">
                                            <?= number_format($row['price_per_pax'], 2) ?>
                                        </td>
                                        <td class="col-cost text-center fw-bold text-danger">
                                            <?= number_format($row['cost_per_pax'] ?? 0, 2) ?>
                                        </td>
                                        <td class="col-cat"><small class="text-muted"><?= htmlspecialchars($row['category_name'] ?? '-') ?></small></td>
                                        <td class="col-type ">
                                            <?= htmlspecialchars($row['type_name'] ?? 'ไม่ระบุ') ?>
                                        </td>

                                        <td>
                                            <button class="btn btn-sm text-primary border-0"
                                                onclick='editMenu(<?= json_encode($row) ?>)'><i
                                                    class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm text-danger border-0"
                                                onclick="deleteMenu(<?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
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

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
    let menuTable;
    const hasCheckbox = <?= $user_role !== 'viewer' ? 'true' : 'false' ?>;
    const colOffset = hasCheckbox ? 1 : 0;

    function updateSelectedCount() {
        if (!hasCheckbox) return;
        let n = $('.row-check:checked').length;
        $('#selectedCount').text(n);
        $('#btnDeleteSelected').prop('disabled', n === 0);
    }

    $(document).ready(function () {
        // ตั้งค่า DataTable
        menuTable = $('#menuTable').DataTable({
            "order": [[colOffset, "asc"]],
            "pageLength": 100,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
            },
            "columnDefs": [
                ...(hasCheckbox ? [{ "orderable": false, "className": "text-center", "targets": [0] }] : []),
                { "orderable": false, "targets": [6 + colOffset] },
                { "className": "text-center", "targets": [1 + colOffset, 2 + colOffset, 3 + colOffset] },
                {
                    "targets": colOffset,
                    "render": function (data, type) {
                        if (type === 'export' || type === 'print') {
                            return $(data).text().trim();
                        }
                        return data;
                    }
                }
            ],
            "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
                "<'d-none'B>",
            "buttons": [
                { extend: 'excel', title: 'รายการเมนูอาหาร', exportOptions: { columns: [0, 1, 2, 3, 4, 5].map(c => c + colOffset), modifier: { search: 'applied' } } },
                { extend: 'print', title: 'รายการเมนูอาหาร', exportOptions: { columns: [0, 1, 2, 3, 4, 5].map(c => c + colOffset), modifier: { search: 'applied' } } }
            ]
        });

        // --- Checkbox: เลือกทั้งหมด / เลือกทีละแถว ---
        if (hasCheckbox) {
            $(document).on('change', '#checkAll', function () {
                let checked = $(this).is(':checked');
                // เลือกเฉพาะแถวที่แสดงผลอยู่ (หลังกรอง/ค้นหา)
                menuTable.rows({ search: 'applied' }).nodes().to$().find('.row-check').prop('checked', checked);
                updateSelectedCount();
            });

            $(document).on('change', '.row-check', function () {
                if (!$(this).is(':checked')) {
                    $('#checkAll').prop('checked', false);
                }
                updateSelectedCount();
            });

            $('#btnDeleteSelected').on('click', function () {
                let ids = $('.row-check:checked').map(function () { return $(this).val(); }).get();
                if (ids.length === 0) return;
                if (!confirm('ต้องการลบ ' + ids.length + ' รายการที่เลือก? เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) return;

                let fd = new FormData();
                fd.append('action', 'delete_multi');
                ids.forEach(id => fd.append('ids[]', id));

                fetch('food_management.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success') {
                            (res.deleted || ids).forEach(id => {
                                menuTable.row($('#row-' + id)).remove();
                            });
                            menuTable.draw(false);
                            $('#checkAll').prop('checked', false);
                            updateSelectedCount();
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่สามารถลบข้อมูลได้'));
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('การเชื่อมต่อล้มเหลว');
                    });
            });
        }

        // --- ส่วนที่เพิ่ม/แก้ไข: AJAX Submit สำหรับ บันทึก & แก้ไข ---
        $('#menuForm').on('submit', function (e) {
            e.preventDefault();
            let formData = new FormData(this);
            let isUpdate = $('#m_id').val() > 0;

            fetch('food_management.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        if (isUpdate) {
                            // --- กรณีแก้ไข: อัปเดตข้อมูลในแถวเดิม ---
                            let r = $('#row-' + res.id);
                            r.find('.col-cat').html(`<small class="text-muted">${res.category_name || '-'}</small>`);
                            r.find('.col-type').text(res.type_name);
                            r.find('.col-pax').text(Number($('#m_pax').val()).toLocaleString());
                            r.find('.col-price').text(Number($('#m_price').val()).toLocaleString(undefined, { minimumFractionDigits: 2 }));
                            r.find('.col-cost').text(Number($('#m_cost').val()).toLocaleString(undefined, { minimumFractionDigits: 2 }));
                            r.find('.menu-text').html($('#m_items').val().replace(/\n/g, '<br>'));
                        } else {
                            // --- กรณีเพิ่มใหม่: สร้างแถวใหม่เข้า DataTables ทันที ---
                            let newRowData = [];
                            if (hasCheckbox) {
                                newRowData.push(`<input type="checkbox" class="form-check-input row-check" value="${res.id}">`);
                            }
                            let newRow = menuTable.row.add(newRowData.concat([
                                // 0. รายการเมนู
                                `<small class="text-muted menu-text">${$('#m_items').val().replace(/\n/g, '<br>')}</small>`,

                                // 1. จำนวน Pax
                                Number($('#m_pax').val()).toLocaleString(),

                                // 2. ราคาขาย/หัว
                                Number($('#m_price').val()).toLocaleString(undefined, { minimumFractionDigits: 2 }),

                                // 3. ราคาทุน/หัว
                                Number($('#m_cost').val() || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }),

                                // 4. กลุ่มอาหาร
                                `<small class="text-muted">${res.category_name || '-'}</small>`,

                                // 5. ประเภทอาหาร
                                res.type_name,

                                // 6. ปุ่มจัดการ
                                `<div class="d-flex justify-content-start align-items-center gap-3">
    <button type="button" class="btn btn-link text-primary p-1 border-0 btn-sm" 
        onclick='editMenu(${JSON.stringify({
                                    id: res.id,
                                    menu_type_id: $('#m_type_id').val(),
                                    guarantee_pax: $('#m_pax').val(),
                                    price_per_pax: $('#m_price').val(),
                                    cost_per_pax: $('#m_cost').val() || 0,
                                    menu_items: $('#m_items').val(),
                                    beverage_detail: $('#m_bev').val()
                                })})'>
        <i class="bi bi-pencil-square"></i>
    </button>
    <button type="button" class="btn btn-link text-danger p-1 border-0" 
        onclick="deleteMenu(${res.id})">
        <i class="bi bi-trash"></i>
    </button>
</div>`
                            ])).draw(false).node();

                            $(newRow).attr('id', 'row-' + res.id);
                            $(newRow).find('td:eq(' + (0 + colOffset) + ')').addClass('menu-items');
                            $(newRow).find('td:eq(' + (1 + colOffset) + ')').addClass('text-center col-pax');
                            $(newRow).find('td:eq(' + (2 + colOffset) + ')').addClass('text-center fw-bold text-primary col-price');
                            $(newRow).find('td:eq(' + (3 + colOffset) + ')').addClass('text-center fw-bold text-danger col-cost');
                            $(newRow).find('td:eq(' + (4 + colOffset) + ')').addClass('col-cat');
                            $(newRow).find('td:eq(' + (6 + colOffset) + ')').addClass('text-center');
                            if (hasCheckbox) {
                                $(newRow).find('td:eq(0)').addClass('text-center');
                            }
                        }

                        resetMenuForm();
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                });
        });

        // ปุ่ม Export
        $('#customExcel').on('click', function () { menuTable.button('.buttons-excel').trigger(); });
        $('#customPrint').on('click', function () { menuTable.button('.buttons-print').trigger(); });

        // ปุ่มปรับปรุงราคาทุน — ดึงต้นทุนรวมจากฐานข้อมูลครัว (manage_kitchen) ตามชื่อเมนูที่ตรงกัน
        $('#customUpdateCost').on('click', function () {
            Swal.fire({
                title: 'ปรับปรุงราคาทุน?',
                html: 'ระบบจะดึง <b>ต้นทุนรวม</b> จากฐานข้อมูลครัว มาทับ "ราคาทุน/หัว"<br>' +
                    '<span class="text-muted small">เฉพาะเมนูที่ชื่อตรงกันเท่านั้น ชื่อที่ไม่เจอจะแจ้งให้ทราบ</span>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#fd7e14',
                confirmButtonText: 'ปรับปรุงเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'กำลังปรับปรุง...',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch('api/sync_kitchen_cost.php', { method: 'POST' })
                    .then(function (res) {
                        // เซิร์ฟเวอร์ตายก่อนตอบ JSON (500 body ว่าง) ต้องอ่านเป็น text ก่อนจะได้เห็นสาเหตุ
                        return res.text().then(function (body) {
                            try {
                                return JSON.parse(body);
                            } catch (e) {
                                return {
                                    status: 'error',
                                    message: 'เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ (HTTP ' + res.status + ')',
                                    detail: body.replace(/<[^>]*>/g, '').trim().slice(0, 500) || '(ไม่มีข้อความตอบกลับ)'
                                };
                            }
                        });
                    })
                    .then(function (res) {
                        if (res.status !== 'success') {
                            Swal.fire({
                                icon: 'error',
                                title: 'ผิดพลาด',
                                html: $('<div>').text(res.message || 'ปรับปรุงราคาทุนไม่สำเร็จ').html() +
                                    (res.detail ? '<hr class="my-2"><div class="small text-muted text-start" style="word-break:break-all;">' +
                                        $('<div>').text(res.detail).html() + '</div>' : '')
                            });
                            return;
                        }

                        let html = '<div class="text-start small">' +
                            '<div>อัปเดตแล้ว <b class="text-success">' + res.updated + '</b> รายการ</div>' +
                            '<div>ราคาเท่าเดิม <b>' + res.unchanged + '</b> รายการ</div>' +
                            '<div>ไม่พบชื่อในครัว <b class="text-danger">' + res.not_found.length + '</b> รายการ</div>';

                        if (res.not_found.length) {
                            html += '<hr class="my-2"><div class="fw-bold mb-1">รายการที่หาไม่เจอ</div>' +
                                '<ul class="mb-0 ps-3" style="max-height:180px;overflow:auto;">' +
                                res.not_found.map(function (n) { return '<li>' + $('<div>').text(n).html() + '</li>'; }).join('') +
                                '</ul>';
                        }

                        if (res.duplicates.length) {
                            html += '<hr class="my-2"><div class="fw-bold mb-1 text-warning">ชื่อซ้ำในครัว (ใช้รายการล่าสุด)</div>' +
                                '<ul class="mb-0 ps-3" style="max-height:120px;overflow:auto;">' +
                                res.duplicates.map(function (n) { return '<li>' + $('<div>').text(n).html() + '</li>'; }).join('') +
                                '</ul>';
                        }

                        html += '</div>';

                        Swal.fire({
                            title: 'ปรับปรุงเสร็จแล้ว',
                            html: html,
                            icon: res.updated > 0 ? 'success' : 'info',
                            confirmButtonText: 'ปิด'
                        }).then(function () {
                            if (res.updated > 0) location.reload();
                        });
                    })
                    .catch(function (err) {
                        console.error('Error:', err);
                        Swal.fire('ผิดพลาด', 'การเชื่อมต่อล้มเหลว', 'error');
                    });
            });
        });
    });

    function editMenu(data) {
        $('#m_id').val(data.id);
        $('#m_type_id').val(data.menu_type_id);
        // Update modal label
        const typeName = <?= json_encode($menu_types_array, JSON_UNESCAPED_UNICODE) ?>;
        const found = typeName.find(t => t.id == data.menu_type_id);
        if (found) {
            $('.menu-type-label').text(found.type_name).removeClass('text-muted').addClass('fw-semibold text-dark');
        } else {
            $('.menu-type-label').text('ยังไม่ได้เลือก').removeClass('fw-semibold text-dark').addClass('text-muted');
        }
        $('#m_pax').val(data.guarantee_pax);
        $('#m_price').val(data.price_per_pax);
        $('#m_cost').val(data.cost_per_pax || 0);
        $('#m_items').val(data.menu_items);
        $('#m_bev').val(data.beverage_detail);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetMenuForm() {
        $('#menuForm')[0].reset();
        $('#m_id').val(0);
        $('#m_type_id').val('');
        $('.menu-type-label').text('ยังไม่ได้เลือก').removeClass('fw-semibold text-dark').addClass('text-muted');
        $('#btnSubmit').text('บันทึกข้อมูล').removeClass('btn-primary').addClass('btn-success');
    }

    function deleteMenu(id) {
        // 1. เพิ่ม Confirm Alert ก่อนทำรายการ
        if (confirm('เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) {

            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch('food_management.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        // 2. ถ้าลบใน DB สำเร็จ ให้ลบแถวออกจาก DataTables ทันที
                        menuTable.row($('#row-' + id)).remove().draw(false);
                        updateSelectedCount();

                        // (Optional) อยากให้แจ้งเตือนว่าลบเสร็จแล้วก็ใส่เพิ่มตรงนี้ได้
                        // alert('ลบข้อมูลเรียบร้อยแล้ว');
                    } else {
                        alert('เกิดข้อผิดพลาด: ไม่สามารถลบข้อมูลได้');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('การเชื่อมต่อล้มเหลว');
                });
        }
    }

    function filterByCategory(val) {
        menuTable.column(4 + colOffset).search(val).draw();
    }
</script>

<?php include "includes/menu_type_modal.php"; ?>
<?php include "footer.php"; ?>