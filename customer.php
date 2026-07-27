<?php
include "config.php";
$user_role = strtolower($_SESSION['role'] ?? 'viewer');
// ==========================================
// 🛡️ API SECTION (ต้องอยู่บนสุดเพื่อ AJAX)
// ==========================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- 1. Logic การบันทึก/แก้ไข (AJAX) ---
    if ($action == 'save') {
        $id = intval($_POST['id'] ?? 0);
        $cust_name = $conn->real_escape_string($_POST['cust_name']);
        $cust_tax_id = $conn->real_escape_string($_POST['cust_tax_id']);
        $cust_address = $conn->real_escape_string($_POST['cust_address']);
        $cust_contact_name = $conn->real_escape_string($_POST['cust_contact_name']);
        $cust_phone = $conn->real_escape_string($_POST['cust_phone']);
        $cust_email = $conn->real_escape_string($_POST['cust_email']);
        $sales_name = $conn->real_escape_string($_POST['sales_name']);

        if ($id > 0) {
            $sql = "UPDATE customers SET 
                    cust_name='$cust_name', cust_tax_id='$cust_tax_id', 
                    cust_address='$cust_address', cust_contact_name='$cust_contact_name', 
                    cust_phone='$cust_phone', cust_email='$cust_email',
                    sales_name='$sales_name'
                    WHERE id=$id";
            $msg = "updated";
        } else {
            $sql = "INSERT INTO customers (cust_name, cust_tax_id, cust_address, cust_contact_name, cust_phone, cust_email, sales_name) 
                    VALUES ('$cust_name', '$cust_tax_id', '$cust_address', '$cust_contact_name', '$cust_phone', '$cust_email', '$sales_name')";
            $msg = "inserted";
        }

        if ($conn->query($sql)) {
            ob_clean(); 
            $new_id = ($id > 0 ? $id : $conn->insert_id);

            echo json_encode([
                "status" => "success",
                "message" => $msg,
                "data" => [
                    "id" => $new_id,
                    "cust_name" => $cust_name,
                    "cust_tax_id" => $cust_tax_id,
                    "cust_address" => $cust_address,
                    "cust_contact_name" => $cust_contact_name,
                    "cust_phone" => $cust_phone,
                    "cust_email" => $cust_email,
                    "sales_name" => $sales_name
                ]
            ]);
            exit; 
        }
    }

    // --- 2. Logic การลบ (AJAX) ---
    if ($action == 'delete') {

        if ($user_role !== 'admin') {
            ob_clean();
            echo "คุณไม่มีสิทธิ์ลบข้อมูล";
            exit;
        }

        $id = intval($_POST['id']);

        if ($conn->query("DELETE FROM customers WHERE id=$id")) {
            ob_clean();
            echo "success";
        } else {
            ob_clean();
            echo "error: " . $conn->error;
        }
        exit;
    }
}

require_once "header.php";
$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold py-3">
                    <i class="bi bi-person-plus-fill me-2"></i>ข้อมูลลูกค้า / บริษัท
                </div>
                <div class="card-body">
                    <form id="custForm" onsubmit="saveCust(event)">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="cust_id" value="0">

                        <div class="mb-2">
                            <label class="small fw-bold">ชื่อบริษัท/ลูกค้า</label>
                            <input type="text" name="cust_name" id="cust_name" class="form-control form-control-sm"
                                required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">เลขผู้เสียภาษี</label>
                            <input type="text" name="cust_tax_id" id="cust_tax_id" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">ที่อยู่</label>
                            <textarea name="cust_address" id="cust_address" class="form-control form-control-sm"
                                rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">ผู้ประสานงาน</label>
                                <input type="text" name="cust_contact_name" id="cust_contact_name"
                                    class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">เบอร์โทร</label>
                                <input type="text" name="cust_phone" id="cust_phone"
                                    class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">อีเมล</label>
                            <input type="email" name="cust_email" id="cust_email" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">เซลที่ดูแล</label>
                            <input type="text" name="sales_name" id="sales_name" class="form-control form-control-sm" placeholder="ระบุชื่อเซล" required>
                        </div>

                        <div class="d-grid gap-2">
                            <?php if ($user_role !== 'viewer'): ?>
                                <button type="submit" class="btn btn-dark px-3 py-1 fw-bold">
                                    <i class="bi bi-save me-2 text-amber"></i>บันทึกลูกค้า
                                </button>
                                <button type="button" class="btn btn-light btn-sm border"
                                    onclick="resetForm()">ยกเลิก</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary px-3 py-1 fw-bold disabled"
                                    style="cursor: not-allowed;">
                                    <i class="bi bi-lock-fill me-2"></i>โหมดอ่านอย่างเดียว (Viewer)
                                </button>
                                <div class="text-center">
                                    <small class="text-danger" style="font-size: 0.7rem;">*
                                        คุณไม่มีสิทธิ์บันทึกหรือแก้ไขข้อมูลลูกค้า</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i
                            class="bi bi-people-fill me-2 text-amber"></i>รายชื่อลูกค้าทั้งหมด</h5>
                    <div class="d-flex align-items-center gap-1">
                        <div class="btn-group pe-2 me-1">
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
                            <button type="button" id="customCopy"
                                class="btn btn-link btn-sm text-primary text-decoration-none p-1" title="Copy">
                                <i class="bi bi-copy fs-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="customerTable" class="table table-striped table-hover align-middle nowrap"
                            style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th class="small">ชื่อบริษัท/ลูกค้า</th>
                                    <th class="small">ผู้ประสานงาน</th>
                                    <th class="small">เซลที่ดูแล</th>
                                    <th class="small">เบอร์โทร</th>
                                    <th class="small">อีเมล</th>
                                    <th class="small text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_contact_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['sales_name'] ?? '-') ?></span></td>
                                        <td><?= htmlspecialchars($row['cust_phone']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_email']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-info border-0" title="ประวัติงาน"
                                                    onclick="showCustomerHistory(<?= $row['id'] ?>, '<?= htmlspecialchars($row['cust_name']) ?>')">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary border-0" title="แก้ไข"
                                                    onclick='editCust(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php if ($user_role === 'admin'): ?>
                                                <button class="btn btn-sm btn-outline-danger border-0" title="ลบ"
                                                    onclick="deleteCust(<?= $row['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
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

<!-- Modal ประวัติลูกค้า -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">ประวัติงานเลี้ยง: <span id="historyCustName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ชื่องาน</th>
                            <th>วันที่สร้าง</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function showCustomerHistory(custId, custName) {
        document.getElementById('historyCustName').innerText = custName;
        const body = document.getElementById('historyBody');
        body.innerHTML = '<tr><td colspan="3" class="text-center">กำลังโหลด...</td></tr>';
        
        const myModal = new bootstrap.Modal(document.getElementById('historyModal'));
        myModal.show();

        fetch(`api/get_customer_history.php?customer_id=${custId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    if (res.data.length === 0) {
                        body.innerHTML = '<tr><td colspan="3" class="text-center">ไม่มีประวัติงาน</td></tr>';
                        return;
                    }
                    body.innerHTML = res.data.map(event => `
                        <tr>
                            <td>${event.function_name}</td>
                            <td>${event.created_at}</td>
                            <td><span class="badge bg-secondary">${event.status}</span></td>
                        </tr>
                    `).join('');
                } else {
                    body.innerHTML = '<tr><td colspan="3" class="text-center text-danger">เกิดข้อผิดพลาด</td></tr>';
                }
            });
    }

    function editCust(data) {
        document.getElementById('cust_id').value = data.id;
        document.getElementById('cust_name').value = data.cust_name;
        document.getElementById('cust_tax_id').value = data.cust_tax_id;
        document.getElementById('cust_address').value = data.cust_address;
        document.getElementById('cust_contact_name').value = data.cust_contact_name;
        document.getElementById('cust_phone').value = data.cust_phone;
        document.getElementById('cust_email').value = data.cust_email;
        document.getElementById('sales_name').value = data.sales_name || '';
        document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-pencil-square me-2 text-amber"></i>แก้ไขข้อมูลลูกค้า';
    }

    function resetForm() {
        document.getElementById('custForm').reset();
        document.getElementById('cust_id').value = 0;
        document.getElementById('sales_name').value = '';
        document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-person-plus-fill me-2 text-amber"></i>ข้อมูลลูกค้า / บริษัท';
    }

    function saveCust(e) {
        e.preventDefault();
        const form = document.getElementById('custForm');
        const fd = new FormData(form);
        const btn = e.target.querySelector('button[type="submit"]');
        const isEdit = document.getElementById('cust_id').value > 0;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';

        fetch('customer.php', {
            method: 'POST',
            body: fd
        })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const table = $('#customerTable').DataTable();
                    const d = res.data; 

                    if (isEdit) {
                        const rowId = document.getElementById('cust_id').value;
                        const row = $(`button[onclick*="deleteCust(${rowId})"]`).parents('tr');

                        table.row(row).data([
                            `<span class="fw-bold">${d.cust_name}</span>`,
                            d.cust_contact_name,
                            `<span class="badge bg-light text-dark border">${d.sales_name || '-'}</span>`,
                            d.cust_phone,
                            d.cust_email,
                            row.find('td:last').html()
                        ]).draw(false);

                    } else {
                        const table = $('#customerTable').DataTable();
                        const d = res.data;

                        const newRow = table.row.add([
                            `<span class="fw-bold">${d.cust_name}</span>`,
                            d.cust_contact_name,
                            `<span class="badge bg-light text-dark border">${d.sales_name || '-'}</span>`,
                            d.cust_phone,
                            d.cust_email,
                            `<div class="btn-group">
            <button class="btn btn-sm btn-outline-info border-0" title="ประวัติงาน"
                onclick="showCustomerHistory(${d.id}, '${d.cust_name}')">
                <i class="bi bi-clock-history"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary border-0" title="แก้ไข"
                onclick='editCust(${JSON.stringify(d)})'>
                <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger border-0" title="ลบ"
                onclick="deleteCust(${d.id})">
                <i class="bi bi-trash"></i>
            </button>
        </div>`
                        ]).draw(false).node();

                        $(newRow).find('td').eq(5).addClass('text-center');
                    }
                    resetForm();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.message);
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-2 text-amber"></i>บันทึกลูกค้า';
            });
    }

    function deleteCust(id) {
        if (confirm('ยืนยันการลบลูกค้ารายนี้?')) {
            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch('customer.php', { method: 'POST', body: fd })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        let table = $('#customerTable').DataTable();
                        table.row($(`button[onclick="deleteCust(${id})"]`).parents('tr')).remove().draw();
                    } else {
                        alert('ลบไม่สำเร็จ: ' + data);
                    }
                })
                .catch(err => console.error("Error:", err));
        }
    }

    $(document).ready(function () {
        var table = $('#customerTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "pageLength": 10,
            "dom": '<"d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            "buttons": [
                {
                    extend: 'excel',
                    className: 'd-none',
                    exportOptions: { columns: [0, 1, 2, 3] },
                    title: 'รายการลูกค้า'
                },
                {
                    extend: 'print',
                    className: 'd-none',
                    exportOptions: { columns: [0, 1, 2, 3] },
                    title: 'รายการลูกค้า' 
                },
                {
                    extend: 'copy',
                    className: 'd-none',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                }
            ],
        });

        $('#customExcel').on('click', function () { table.button('.buttons-excel').trigger(); });
        $('#customPrint').on('click', function () { table.button('.buttons-print').trigger(); });
        $('#customCopy').on('click', function () { table.button('.buttons-copy').trigger(); });
    });
</script>

<?php include "footer.php"; ?>