<?php
include "config.php";

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

        if ($conn->query($sql)) {
            ob_clean();
            // ถ้าเป็น Insert ส่ง ID ล่าสุดกลับไปด้วย เผื่อต้องใช้
            echo json_encode(["status" => "success", "message" => $msg, "id" => ($id > 0 ? $id : $conn->insert_id)]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit;
    }

    // --- 2. Logic การลบ (AJAX) ---
    if ($action == 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM customers WHERE id=$id")) {
            ob_clean();
            echo "success";
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
                <div class="card-header bg-dark text-white fw-bold">
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
                            <button type="submit" class="btn btn-dark btn-sm px-3 py-1">
                                <i class="bi bi-save me-2 text-amber"></i>บันทึกลูกค้า
                            </button>
                            <button type="button" class="btn btn-light btn-sm border"
                                onclick="resetForm()">ยกเลิก</button>
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
                                                <button class="btn btn-sm btn-outline-primary" title="แก้ไข"
                                                    onclick='editCust(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="ลบ"
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
    let form = document.getElementById('custForm');
    let fd = new FormData(form);

    fetch('customer.php', { // <--- เช็กชื่อไฟล์นี้อีกรอบ!
        method: 'POST',
        body: fd
    })
    .then(res => {
        // ลองเช็กว่าที่ตอบกลับมาใช่ JSON ไหม
        return res.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Server Responded with non-JSON:", text);
                throw new Error("Server response was not JSON");
            }
        });
    })
    .then(res => {
        if (res.status === 'success') {
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        console.error("Fetch Error:", err);
        alert("เกิดข้อผิดพลาดในการส่งข้อมูล เช็ก Console (F12)");
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
                { extend: 'excel', exportOptions: { columns: [0, 1, 2, 3] } },
                { extend: 'print', exportOptions: { columns: [0, 1, 2, 3] } },
                { extend: 'copy', exportOptions: { columns: [0, 1, 2, 3] } }
            ],
            "language": {
                "sSearch": "ค้นหา:",
                "sLengthMenu": "แสดง _MENU_ แถว",
                "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                "paginate": { "next": "ถัดไป", "previous": "ก่อนหน้า" }
            }
        });

        $('#customExcel').on('click', function () { table.button('.buttons-excel').trigger(); });
        $('#customPrint').on('click', function () { table.button('.buttons-print').trigger(); });
        $('#customCopy').on('click', function () { table.button('.buttons-copy').trigger(); });
    });
</script>

<?php include "footer.php"; ?>