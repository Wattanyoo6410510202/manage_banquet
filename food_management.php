<?php
include "config.php";

// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
// ต้องอยู่ก่อนการส่ง Output ใดๆ เพื่อให้ Redirect ทำงานได้
// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    // ล้าง output buffer เพื่อไม่ให้มีช่องว่างหลุดออกไป
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json'); // บอก Browser ว่าจะส่ง JSON นะ

    if ($_POST['action'] == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($conn->query("DELETE FROM function_menu_details WHERE id=$id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
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

        if ($id > 0) {
            $sql = "UPDATE function_menu_details SET 
                    menu_type_id=$menu_type_id, 
                    menu_items='$menu_items', 
                    beverage_detail='$beverage_detail', 
                    guarantee_pax=$guarantee_pax, 
                    price_per_pax=$price_per_pax 
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO function_menu_details (menu_type_id, menu_items, beverage_detail, guarantee_pax, price_per_pax) 
                    VALUES ($menu_type_id, '$menu_items', '$beverage_detail', $guarantee_pax, $price_per_pax)";
        }

        if ($conn->query($sql)) {
            // ดึงชื่อประเภทอาหารกลับมาด้วยเพื่อไปโชว์ในตารางทันที
            $t_res = $conn->query("SELECT type_name FROM master_menu_types WHERE id=$menu_type_id");
            $t_row = $t_res->fetch_assoc();

            echo json_encode([
                'status' => 'success',
                'id' => ($id > 0 ? $id : $conn->insert_id),
                'type_name' => $t_row['type_name'] ?? 'ไม่ระบุ',
                'is_update' => ($id > 0)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }
}

// --- 2. ดึงข้อมูล ---
$types_query = $conn->query("SELECT * FROM master_menu_types ORDER BY id ASC");
$types_list = [];
while ($t = $types_query->fetch_assoc()) {
    $types_list[] = $t;
}

$menus = $conn->query("SELECT m.*, t.type_name 
                       FROM function_menu_details m 
                       LEFT JOIN master_menu_types t ON m.menu_type_id = t.id 
                       ORDER BY m.id DESC");

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
                            <select name="menu_type_id" id="m_type_id" class="form-select" required>
                                <option value="">-- เลือกประเภทอาหาร --</option>
                                <?php foreach ($types_list as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="small fw-bold mb-1">การันตี (Pax)</label>
                                <input type="number" name="guarantee_pax" id="m_pax" class="form-control"
                                    placeholder="100">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="small fw-bold mb-1">ราคา/หัว (บาท)</label>
                                <input type="number" step="0.01" name="price_per_pax" id="m_price" class="form-control"
                                    placeholder="450.00">
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
                            <button type="submit" id="btnSubmit" class="btn btn-success fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูลเมนู
                            </button>
                            <button type="button" class="btn btn-light border btn-sm"
                                onclick="resetMenuForm()">ล้างข้อมูล</button>
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
                        <button type="button" id="customExcel"
                            class="btn btn-link btn-sm text-success text-decoration-none p-1" title="Excel">
                            <i class="bi bi-file-earmark-excel fs-5"></i>
                            <span class="d-none d-md-inline small ms-1">Excel</span>
                        </button>
                        <button type="button" id="customPrint"
                            class="btn btn-link btn-sm text-secondary text-decoration-none p-1" title="Print">
                            <i class="bi bi-printer fs-5"></i>
                            <span class="d-none d-md-inline small ms-1">พิมพ์</span>
                        </button>
                    </div>

                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="menuTable" class="table table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr class="small text-uppercase">
                                    <th>ประเภทอาหาร</th>
                                    <th class="text-center">จำนวน</th>
                                    <th class="text-center">ราคา/หัว</th>
                                    <th>รายการเมนู/เครื่องดื่ม</th>
                                    <th class="text-">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php while ($row = $menus->fetch_assoc()): ?>
                                    <tr id="row-<?= $row['id'] ?>">
                                        <td class="col-type fw-bold text-success">
                                            <?= htmlspecialchars($row['type_name'] ?? 'ไม่ระบุ') ?>
                                        </td>
                                        <td class="col-pax text-center"><?= number_format($row['guarantee_pax']) ?></td>
                                        <td class="col-price text-center fw-bold text-primary">
                                            <?= number_format($row['price_per_pax'], 2) ?>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <span class="badge bg-secondary">Food</span>
                                                <small
                                                    class="text-muted menu-text"><?= nl2br(htmlspecialchars($row['menu_items'])) ?></small>
                                            </div>
                                            <div>
                                                <span class="badge bg-info text-dark">Beverage</span>
                                                <small
                                                    class="text-muted bev-text"><?= nl2br(htmlspecialchars($row['beverage_detail'])) ?></small>
                                            </div>
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

    $(document).ready(function () {
        // ตั้งค่า DataTable
        menuTable = $('#menuTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 10,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
            },
            "columnDefs": [
                { "orderable": false, "targets": [3, 4] }
            ],
            "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
                "<'d-none'B>",
            "buttons": [
                { extend: 'excel', title: 'รายการเมนูอาหาร', exportOptions: { columns: [0, 1, 2, 3] } },
                { extend: 'print', title: 'รายการเมนูอาหาร', exportOptions: { columns: [0, 1, 2, 3] } }
            ]
        });

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
                            r.find('.col-type').text(res.type_name);
                            r.find('.col-pax').text(Number($('#m_pax').val()).toLocaleString());
                            r.find('.col-price').text(Number($('#m_price').val()).toLocaleString(undefined, { minimumFractionDigits: 2 }));
                            r.find('.menu-text').html($('#m_items').val().replace(/\n/g, '<br>'));
                            r.find('.bev-text').html($('#m_bev').val().replace(/\n/g, '<br>'));
                        } else {
                            // --- กรณีเพิ่มใหม่: สร้างแถวใหม่เข้า DataTables ทันที ---
                            let newRow = menuTable.row.add([
                                res.type_name,
                                Number($('#m_pax').val()).toLocaleString(),
                                Number($('#m_price').val()).toLocaleString(undefined, { minimumFractionDigits: 2 }),
                                `<div class="mb-1"><span class="badge bg-secondary">Food</span> <small class="text-muted menu-text">${$('#m_items').val().replace(/\n/g, '<br>')}</small></div>
                         <div><span class="badge bg-info text-dark">Beverage</span> <small class="text-muted bev-text">${$('#m_bev').val().replace(/\n/g, '<br>')}</small></div>`,
                                `<div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick='editMenu(${JSON.stringify({ id: res.id, menu_type_id: $('#m_type_id').val(), guarantee_pax: $('#m_pax').val(), price_per_pax: $('#m_price').val(), menu_items: $('#m_items').val(), beverage_detail: $('#m_bev').val() })})'><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMenu(${res.id})"><i class="bi bi-trash"></i></button>
                         </div>`
                            ]).draw(false).node();

                            $(newRow).attr('id', 'row-' + res.id); // ใส่ ID ให้ <tr> ใหม่
                            $(newRow).find('td:eq(0)').addClass('fw-bold text-success col-type');
                            $(newRow).find('td:eq(1)').addClass('text-center col-pax');
                            $(newRow).find('td:eq(2)').addClass('text-center fw-bold text-primary col-price');
                            $(newRow).find('td:eq(4)').addClass('text-center');
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
    });

    function editMenu(data) {
        $('#m_id').val(data.id);
        $('#m_type_id').val(data.menu_type_id);
        $('#m_pax').val(data.guarantee_pax);
        $('#m_price').val(data.price_per_pax);
        $('#m_items').val(data.menu_items);
        $('#m_bev').val(data.beverage_detail);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetMenuForm() {
        $('#menuForm')[0].reset();
        $('#m_id').val(0);
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
</script>

<?php include "footer.php"; ?>