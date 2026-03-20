<?php
include "config.php";

// --- 1. ส่วนจัดการข้อมูล (API Logic) ---
if (isset($_POST['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $id             = intval($_POST['id'] ?? 0);
    $break_time     = $conn->real_escape_string($_POST['break_time'] ?? '');
    $break_type_id  = intval($_POST['break_type_id'] ?? 0);
    $break_menu     = $conn->real_escape_string($_POST['break_menu'] ?? '');
    $break_pax      = intval($_POST['break_pax'] ?? 0);
    $break_remark   = $conn->real_escape_string($_POST['break_remark'] ?? '');
    
    // 🎯 ส่วนที่เพิ่มใหม่: รับราคาและคำนวณยอดรวม
    $break_price    = floatval($_POST['break_price'] ?? 0);
    $break_total    = $break_pax * $break_price; 

    if ($_POST['action'] == 'save') {
        if ($id > 0) {
            $sql = "UPDATE function_breaks SET 
                    break_time='$break_time', 
                    break_type_id=$break_type_id, 
                    break_menu='$break_menu', 
                    break_pax=$break_pax, 
                    break_price=$break_price,
                    break_total=$break_total,
                    break_remark='$break_remark' 
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO function_breaks (break_time, break_type_id, break_menu, break_pax, break_price, break_total, break_remark) 
                    VALUES ('$break_time', $break_type_id, '$break_menu', $break_pax, $break_price, $break_total, '$break_remark')";
        }

        if ($conn->query($sql)) {
            $last_id = ($id > 0) ? $id : $conn->insert_id;
            $t_res = $conn->query("SELECT type_name FROM master_break_types WHERE id=$break_type_id");
            $t_row = $t_res->fetch_assoc();

            echo json_encode([
                "status" => "success",
                "data" => [
                    "id" => $last_id,
                    "break_time" => $break_time,
                    "break_type_id" => $break_type_id,
                    "type_name" => $t_row['type_name'] ?? 'ไม่ระบุ',
                    "break_menu" => $break_menu,
                    "break_pax" => $break_pax,
                    "break_price" => number_format($break_price, 2),
                    "break_total" => number_format($break_total, 2),
                    "break_remark" => $break_remark
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit; 
    }

    if ($_POST['action'] == 'delete') {
        if ($conn->query("DELETE FROM function_breaks WHERE id=$id")) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error"]);
        }
        exit; 
    }
}

// --- 2. ส่วนแสดงผล HTML ---
require_once "header.php";
$breaks = $conn->query("SELECT b.*, t.type_name FROM function_breaks b LEFT JOIN master_break_types t ON b.break_type_id = t.id ORDER BY b.id DESC");
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid p-0">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div id="formHeader" class="card-header bg-warning text-dark fw-bold">
                    <i class="bi bi-cup-hot me-2"></i>จัดการ Coffee Break
                </div>
                <div class="card-body">
                    <form id="breakForm">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="b_id" value="0">

                        <div class="mb-2">
                            <label class="small fw-bold">เวลาที่จัดเสิร์ฟ</label>
                            <input type="text" name="break_time" id="b_time" class="form-control form-control-sm"
                                placeholder="เช่น 10:30 น." required>
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold">ประเภทเบรก</label>
                            <select name="break_type_id" id="b_type_id" class="form-select form-select-sm" required>
                                <option value="">-- เลือก --</option>
                                <?php
            $types = $conn->query("SELECT * FROM master_break_types ORDER BY id ASC");
            while ($t = $types->fetch_assoc())
                echo "<option value='{$t['id']}'>{$t['type_name']}</option>";
            ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold">รายการเมนูของว่าง</label>
                            <textarea name="break_menu" id="b_menu" class="form-control form-control-sm" rows="4"
                                placeholder="ระบุรายละเอียดเมนู..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">จำนวน (Pax)</label>
                                <input type="number" name="break_pax" id="b_pax" class="form-control form-control-sm"
                                    min="1" value="1">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">ราคาต่อหัว (฿)</label>
                                <input type="number" name="break_price" id="b_price"
                                    class="form-control form-control-sm" step="0.01" min="0" value="0.00">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold text-primary">ยอดรวมค่าเบรก (฿)</label>
                            <input type="text" id="b_total_display"
                                class="form-control form-control-sm bg-light text-primary fw-bold" readonly
                                value="0.00">
                        </div>

                        <div class="mb-2">
                            <label class="small fw-bold">หมายเหตุ</label>
                            <input type="text" name="break_remark" id="b_remark" class="form-control form-control-sm"
                                placeholder="เช่น ไม่รับถั่ว, มังสวิรัติ">
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" id="btnSubmit" class="btn btn-warning fw-bold shadow-sm">
                                <i class="bi bi-save me-2"></i>บันทึกข้อมูลเบรก
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
                <div
                    class="card-header bg-white fw-bold border-bottom d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cup-hot me-2"></i></i>รายการ Coffee Break
                    </h5>

                    <div class="d-flex align-items-center gap-1">
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
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="breakTable" class="table table-hover align-middle" style="width:100%">
                            <thead class="table-dark small">
                                <tr>
                                    <th>เวลา / ประเภท</th>
                                    <th>เมนูของว่าง</th>
                                    <th width="10%" class="text-center">จำนวน</th>
                                    <th width="12%" class="text-end">ราคา/หัว</th>
                                    <th width="12%" class="text-end">ยอดรวม</th>
                                    <th width="10%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="breakTableBody" class="small">
                                <?php while ($row = $breaks->fetch_assoc()): ?>
                                <tr id="row-<?= $row['id'] ?>">
                                    <td>
                                        <div class="fw-bold text-primary b-time">
                                            <?= htmlspecialchars($row['break_time']) ?></div>
                                        <div class="badge bg-light text-dark border fw-normal b-type-name">
                                            <?= htmlspecialchars($row['type_name'] ?? 'ไม่ระบุ') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-1 b-menu-text"><?= nl2br(htmlspecialchars($row['break_menu'])) ?>
                                        </div>
                                        <small class="text-danger b-remark-text">
                                            <?= $row['break_remark'] ? '* ' . htmlspecialchars($row['break_remark']) : '' ?>
                                        </small>
                                    </td>
                                    <td class="text-center fw-bold b-pax-text">
                                        <?= number_format($row['break_pax']) ?>
                                    </td>
                                    <td class="text-end b-price-text">
                                        <?= number_format($row['break_price'], 2) ?>
                                    </td>
                                    <td class="text-end fw-bold text-primary b-total-text">
                                        <?= number_format($row['break_total'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                           <button class="btn btn-sm btn-outline-primary border-0"
    onclick='editBreak(<?= json_encode([
        "id" => $row['id'],
        "break_time" => $row['break_time'],
        "break_type_id" => $row['break_type_id'],
        "break_menu" => $row['break_menu'],
        "break_pax" => (int)$row['break_pax'],
        "break_price" => (float)$row['break_price'], // บังคับเป็นเลขทศนิยมที่นี่
        "break_remark" => $row['break_remark']
    ]) ?>)'>
    <i class="bi bi-pencil-square"></i>
</button>
                                            <button class="btn btn-sm btn-outline-danger border-0"
                                                onclick="deleteBreak(<?= $row['id'] ?>)">
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
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
let table;

$(document).ready(function() {
    // 1. ตั้งค่า DataTable แบบภาษาไทย
    table = $('#breakTable').DataTable({
        "order": [
            [0, "desc"]
        ],
        "pageLength": 10,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        },
        "columnDefs": [{
            "orderable": false,
            "targets": 3
        }],
        // แก้ไขตรงนี้ครับจาร
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
            "<'d-none'B>", // ซ่อนปุ่ม Text ทื่อๆ ไว้ แต่ยังให้ระบบ Buttons ทำงาน
        "buttons": [{
                extend: 'excel',
                className: 'd-none',
                exportOptions: {
                    columns: [0, 1, 2]
                },
                title: 'รายการเบรก'
            },
            {
                extend: 'print',
                className: 'd-none',
                exportOptions: {
                    columns: [0, 1, 2]
                },
                title: 'รายการเบรก' // ✅ ใส่คอมม่าหน้า title แล้วครับ
            },
            {
                extend: 'copy',
                className: 'd-none',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                }
            }
        ],
    });
    // เชื่อมปุ่ม Custom (ย้ายมาไว้ใน ready เพื่อความชัวร์)
    $('#customExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });
    $('#customPrint').on('click', function() {
        table.button('.buttons-print').trigger();
    });
    $('#customCopy').on('click', function() {
        table.button('.buttons-copy').trigger();
    });
});

// 2. ฟังก์ชันบันทึกข้อมูล (AJAX + DataTable API)
document.getElementById('breakForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    const fd = new FormData(this);
    const b_id = document.getElementById('b_id').value;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('main_kitchen.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const d = res.data;
                const remarkHtml = d.break_remark ?
                    `<br><small class="text-danger">* ${d.break_remark}</small>` : '';

                // เตรียมข้อมูลให้ครบ 6 คอลัมน์ (Index 0 - 5)
const col0 = `<div class="fw-bold text-primary b-time">${d.break_time}</div>
              <div class="badge bg-light text-dark border fw-normal b-type-name">${d.type_name}</div>`;
const col1 = `<div class="mb-1 b-menu-text">${d.break_menu.replace(/\n/g, '<br>')}</div>
              ${d.break_remark ? `<small class="text-danger">* ${d.break_remark}</small>` : ''}`;
const col2 = `<div class="text-center fw-bold b-pax-text">${Number(d.break_pax).toLocaleString()}</div>`;
const col3 = `<div class="text-end b-price-text">${d.break_price}</div>`; // เพิ่มคอลัมน์ราคา
const col4 = `<div class="text-end fw-bold text-primary b-total-text">${d.break_total}</div>`; // เพิ่มคอลัมน์ยอดรวม
const col5 = `<div class="text-center">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary border-0" onclick='editBreak(${JSON.stringify(d)})'>
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteBreak(${d.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
              </div>`;

if (b_id > 0) {
    // อัปเดต Array ให้ครบ 6 คอลัมน์
    table.row($(`#row-${d.id}`)).data([col0, col1, col2, col3, col4, col5]).draw(false);
       } else {
                    // กรณีเพิ่มใหม่: เพิ่ม Row เข้าไปใน DataTable
                    const newRow = table.row.add([col1, col2, col3, col4]).draw(false).node();
                    $(newRow).attr('id', 'row-' + d.id); // ใส่ ID ให้ Row ใหม่
                    $(newRow).addClass('table-success'); // ไฮไลท์แถวใหม่
                    setTimeout(() => $(newRow).removeClass('table-success'), 2000);
                }
                resetForm();
            }
        })
        .catch(err => console.error(err))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = 'บันทึกข้อมูลเบรก';
        });
});

function editBreak(data) {
    console.log(data); // <--- ลองกดแล้วดูใน Console (F12) ว่ามีคำว่า break_price ไหม
    
    $('#b_id').val(data.id);
    $('#b_time').val(data.break_time);
    $('#b_type_id').val(data.break_type_id);
    $('#b_menu').val(data.break_menu);
    $('#b_pax').val(data.break_pax);
    $('#b_remark').val(data.break_remark);
    
    // ดึงค่ามาพักไว้ก่อน พร้อมเช็คว่าเป็นตัวเลขไหม
    let price = parseFloat(data.break_price) || 0;
    let pax = parseInt(data.break_pax) || 0;

    // ใส่ค่าใน Input ราคาต่อหัว
    $('#b_price').val(price.toFixed(2)); 
    
    // คำนวณยอดรวม
    let total = pax * price;
    $('#b_total_display').val(total.toLocaleString(undefined, {
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2
    }));
   
    window.scrollTo(0, 0);
}

function resetForm() {
    document.getElementById('breakForm').reset();
    document.getElementById('b_id').value = 0;
    document.getElementById('formHeader').className = 'card-header bg-warning text-dark fw-bold';
    document.getElementById('btnSubmit').className = 'btn btn-warning btn-sm fw-bold shadow-sm';
}

function deleteBreak(id) {
    if (confirm('เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) {
        let fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        fetch('main_kitchen.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // ลบ Row ออกจาก DataTable ทันที (เนียนมาก)
                    table.row($(`#row-${id}`)).remove().draw(false);
                }
            });
    }
}
$(document).ready(function() {
    // ฟังก์ชันคำนวณราคาสดๆ
    $('#b_pax, #b_price').on('input', function() {
        let pax = parseFloat($('#b_pax').val()) || 0;
        let price = parseFloat($('#b_price').val()) || 0;
        let total = pax * price;

        // แสดงผลยอดรวมแบบใส่คอมมาและทศนิยม 2 ตำแหน่ง
        $('#b_total_display').val(total.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    });
});

// อย่าลืมเพิ่มการล้างค่าในฟังก์ชัน resetForm() ของจารย์ด้วยนะครับ
function resetForm() {
    $('#breakForm')[0].reset();
    $('#b_id').val(0);
    $('#b_total_display').val('0.00');
    $('#formHeader').removeClass('bg-info').addClass('bg-warning');
    $('#btnSubmit').html('<i class="bi bi-save me-2"></i>บันทึกข้อมูลเบรก');
}
</script>
<?php include "footer.php"; ?>