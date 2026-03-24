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

        if ($id > 0) {
            $sql = "UPDATE customers SET 
                    cust_name='$cust_name', cust_tax_id='$cust_tax_id', 
                    cust_address='$cust_address', cust_contact_name='$cust_contact_name', 
                    cust_phone='$cust_phone', cust_email='$cust_email' 
                    WHERE id=$id";
            $msg = "updated";
        } else {
            $sql = "INSERT INTO customers (cust_name, cust_tax_id, cust_address, cust_contact_name, cust_phone, cust_email) 
                    VALUES ('$cust_name', '$cust_tax_id', '$cust_address', '$cust_contact_name', '$cust_phone', '$cust_email')";
            $msg = "inserted";
        }

        // --- แก้ไขตรงส่วนบันทึก (save) ---
        if ($conn->query($sql)) {
            ob_clean(); // ล้างค้างที่อาจหลุดมา
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
                    "cust_email" => $cust_email
                ]
            ]);
            exit; // 👈 ต้องมีบรรทัดนี้ ไม่งั้นมันจะไปโหลด header.php มาใส่ใน JSON
        }
    }

    // --- 2. Logic การลบ (AJAX) ---
    // --- 2. Logic การลบ (AJAX) ---
    if ($action == 'delete') {

        // 🚫 ด่านที่ 1: ดักสิทธิ์ Viewer ห้ามลบเด็ดขาด
        if ($user_role === 'viewer') {
            ob_clean(); // เคลียร์ Output ที่อาจจะค้างอยู่
            echo "คุณไม่มีสิทธิ์ลบข้อมูล (Viewer Mode)";
            exit;
        }

        $id = intval($_POST['id']);

        // 🚀 ด่านที่ 2: ถ้าไม่ใช่ Viewer ถึงจะยอมให้รัน Query นี้
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
        <div class="col-md-4">
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
                            <input type="text" name="cust_tax_id" id="cust_tax_id" class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">ที่อยู่</label>
                            <textarea name="cust_address" id="cust_address" class="form-control form-control-sm"
                                rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">ผู้ประสานงาน</label>
                                <input type="text" name="cust_contact_name" id="cust_contact_name"
                                    class="form-control form-control-sm">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">เบอร์โทร</label>
                                <input type="text" name="cust_phone" id="cust_phone"
                                    class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">อีเมล</label>
                            <input type="email" name="cust_email" id="cust_email" class="form-control form-control-sm">
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

        <div class="col-md-8">
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
                                        <td><?= htmlspecialchars($row['cust_phone']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_email']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary border-0" title="แก้ไข"
                                                    onclick='editCust(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger border-0" title="ลบ"
                                                    onclick="deleteCust(<?= $row['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
    function editCust(data) {
        document.getElementById('cust_id').value = data.id;
        document.getElementById('cust_name').value = data.cust_name;
        document.getElementById('cust_tax_id').value = data.cust_tax_id;
        document.getElementById('cust_address').value = data.cust_address;
        document.getElementById('cust_contact_name').value = data.cust_contact_name;
        document.getElementById('cust_phone').value = data.cust_phone;
        document.getElementById('cust_email').value = data.cust_email;
        document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-pencil-square me-2 text-amber"></i>แก้ไขข้อมูลลูกค้า';
    }

    function resetForm() {
        document.getElementById('custForm').reset();
        document.getElementById('cust_id').value = 0;
        document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-person-plus-fill me-2 text-amber"></i>ข้อมูลลูกค้า / บริษัท';
    }

    function saveCust(e) {
        e.preventDefault();
        const form = document.getElementById('custForm');
        const fd = new FormData(form);
        const btn = e.target.querySelector('button[type="submit"]');
        const isEdit = document.getElementById('cust_id').value > 0;

        // ล็อคปุ่มป้องกันการส่งซ้ำ
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
                    const d = res.data; // ข้อมูลก้อนใหม่จากฝั่ง PHP

                    if (isEdit) {
                        // ✅ กรณีแก้ไข: ค้นหาแถวเดิมด้วย ID แล้วอัปเดตข้อมูล
                        const rowId = document.getElementById('cust_id').value;
                        const row = $(`button[onclick*="deleteCust(${rowId})"]`).parents('tr');

                        table.row(row).data([
                            `<span class="fw-bold">${d.cust_name}</span>`,
                            d.cust_contact_name,
                            d.cust_phone,
                            d.cust_email,
                            row.find('td:last').html() // รักษา HTML ของกลุ่มปุ่มจัดการเดิมไว้
                        ]).draw(false);

                    } else {
                        // ✅ กรณีเพิ่มใหม่: ยัดข้อมูล และสั่งให้คอลัมน์ปุ่มอยู่ตรงกลาง
                        const table = $('#customerTable').DataTable();
                        const d = res.data;

                        const newRow = table.row.add([
                            `<span class="fw-bold">${d.cust_name}</span>`,
                            d.cust_contact_name,
                            d.cust_phone,
                            d.cust_email,
                            `<div class="btn-group">
            <button class="btn btn-sm btn-outline-primary border-0" title="แก้ไข"
                onclick='editCust(${JSON.stringify(d)})'>
                <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger border-0" title="ลบ"
                onclick="deleteCust(${d.id})">
                <i class="bi bi-trash"></i>
            </button>
        </div>`
                        ]).draw(false).node(); // ดึงก้อน Row (tr) ที่เพิ่งสร้างออกมา

                        // บังคับให้คอลัมน์ที่ 5 (index 4) ในแถวนี้มีคลาส text-center เพื่อให้อยู่ตรงกลาง
                        $(newRow).find('td').eq(4).addClass('text-center');
                    }

                    // ล้างฟอร์มให้พร้อมสำหรับรายการถัดไป
                    resetForm();

                } else {
                    // แจ้งเตือนเฉพาะกรณีเกิด Error จากระบบ (เช่น Data ซ้ำ หรือ SQL พัง)
                    alert('เกิดข้อผิดพลาด: ' + res.message);
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ (Check PHP or JSON format)');
            })
            .finally(() => {
                // คืนค่าปุ่มให้กลับมาใช้งานได้
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-2 text-amber"></i>บันทึกลูกค้า';
            });
    }

    // ฟังก์ชันแจ้งเตือนแบบเนียนๆ
    function showNotify(type, msg) {
        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 250px;">
            <i class="bi bi-info-circle me-2"></i> ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        $('body').append(alertHtml);
        setTimeout(() => { $('.alert').alert('close'); }, 3000);
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
                        // AJAX ลบแถวทันที
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
                    title: 'รายการลูกค้า' // ✅ ใส่คอมม่าหน้า title แล้วครับ
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