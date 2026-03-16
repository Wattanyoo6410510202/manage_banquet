<?php
include "config.php";
require_once "header.php"; 

// ==========================================
// 🛡️ API SECTION (CRUD Logic)
// ==========================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
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
        } else {
            $sql = "INSERT INTO customers (cust_name, cust_tax_id, cust_address, cust_contact_name, cust_phone, cust_email) 
                    VALUES ('$cust_name', '$cust_tax_id', '$cust_address', '$cust_contact_name', '$cust_phone', '$cust_email')";
        }
        
        if ($conn->query($sql)) {
            echo "<script>alert('บันทึกข้อมูลลูกค้าสำเร็จ!'); window.location.href='setting_customer.php';</script>";
        }
    }

    if ($action == 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM customers WHERE id=$id")) {
            echo "success";
        }
        exit;
    }
}

$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");
?>

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>
    
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-gold fw-bold">
                        <i class="bi bi-person-plus-fill me-2"></i>ข้อมูลลูกค้า / บริษัท
                    </div>
                    <div class="card-body">
                        <form id="custForm" method="POST">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" id="cust_id" value="0">
                            
                            <div class="mb-2">
                                <label class="small fw-bold">ชื่อบริษัท/ลูกค้า</label>
                                <input type="text" name="cust_name" id="cust_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">เลขผู้เสียภาษี</label>
                                <input type="text" name="cust_tax_id" id="cust_tax_id" class="form-control form-control-sm">
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">ที่อยู่</label>
                                <textarea name="cust_address" id="cust_address" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="small fw-bold">ผู้ประสานงาน</label>
                                    <input type="text" name="cust_contact_name" id="cust_contact_name" class="form-control form-control-sm">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="small fw-bold">เบอร์โทร</label>
                                    <input type="text" name="cust_phone" id="cust_phone" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold">อีเมล</label>
                                <input type="email" name="cust_email" id="cust_email" class="form-control form-control-sm">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">บันทึกลูกค้า</button>
                                <button type="button" class="btn btn-light btn-sm border" onclick="resetForm()">ยกเลิก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">รายชื่อลูกค้าทั้งหมด</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>ชื่อบริษัท/ลูกค้า</th>
                                        <th>ผู้ประสานงาน</th>
                                        <th>เบอร์โทร</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_contact_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_phone']) ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary border-0" onclick='editCust(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteCust(<?= $row['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
</div>

<script>
function editCust(data) {
    document.getElementById('cust_id').value = data.id;
    document.getElementById('cust_name').value = data.cust_name;
    document.getElementById('cust_tax_id').value = data.cust_tax_id;
    document.getElementById('cust_address').value = data.cust_address;
    document.getElementById('cust_contact_name').value = data.cust_contact_name;
    document.getElementById('cust_phone').value = data.cust_phone;
    document.getElementById('cust_email').value = data.cust_email;
    document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลลูกค้า';
}

function resetForm() {
    document.getElementById('custForm').reset();
    document.getElementById('cust_id').value = 0;
    document.querySelector('.card-header.bg-dark').innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>ข้อมูลลูกค้า / บริษัท';
}

function deleteCust(id) {
    if (confirm('ลบลูกค้ารายนี้ใช่ไหม?')) {
        let fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('setting_customer.php', { method: 'POST', body: fd })
        .then(res => res.text()).then(data => { if(data === 'success') window.location.reload(); });
    }
}
</script>

<?php include "footer.php"; ?>