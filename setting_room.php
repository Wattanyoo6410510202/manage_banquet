<?php
include "config.php";

// ==========================================
// 🛡️ API SECTION (Logic) - จัดการแบบ JSON
// ==========================================
if (isset($_POST['action'])) {
    if (ob_get_length())
        ob_clean(); // ล้าง buffer กันช่องว่างหลุด
    header('Content-Type: application/json');

    $action = $_POST['action'];

    if ($action == 'save') {
        $id = intval($_POST['id'] ?? 0);
        // 🏢 รับค่า company_id เพิ่มเติม
        $company_id = intval($_POST['company_id'] ?? 0);

        $room_name = $conn->real_escape_string($_POST['room_name']);
        $floor = $conn->real_escape_string($_POST['floor']);
        $length_m = floatval($_POST['length_m']);
        $width_m = floatval($_POST['width_m']);
        $height_m = floatval($_POST['height_m']);
        $total_sqm = $length_m * $width_m;

        $cap_theatre = intval($_POST['cap_theatre']);
        $cap_classroom = intval($_POST['cap_classroom']);
        $cap_banquet = intval($_POST['cap_banquet']);
        $cap_cocktail = intval($_POST['cap_cocktail']);

        if ($id > 0) {
            // อัปเดตโดยเพิ่ม company_id
            $sql = "UPDATE meeting_rooms SET 
                company_id='$company_id',
                room_name='$room_name', floor='$floor', length_m='$length_m', 
                width_m='$width_m', height_m='$height_m', total_sqm='$total_sqm',
                cap_theatre='$cap_theatre', cap_classroom='$cap_classroom',
                cap_banquet='$cap_banquet', cap_cocktail='$cap_cocktail'
                WHERE id=$id";
            $conn->query($sql);
            echo json_encode(['status' => 'updated', 'id' => $id, 'sqm' => number_format($total_sqm, 2)]);
        } else {
            // เพิ่มใหม่โดยใส่ company_id ลงไปด้วย
            $sql = "INSERT INTO meeting_rooms (company_id, room_name, floor, length_m, width_m, height_m, total_sqm, cap_theatre, cap_classroom, cap_banquet, cap_cocktail) 
                VALUES ('$company_id', '$room_name', '$floor', '$length_m', '$width_m', '$height_m', '$total_sqm', 
                '$cap_theatre', '$cap_classroom', '$cap_banquet', '$cap_cocktail')";
            $conn->query($sql);
            echo json_encode(['status' => 'inserted', 'id' => $conn->insert_id, 'sqm' => number_format($total_sqm, 2)]);
        }
        exit;
    }

    if ($action == 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM meeting_rooms WHERE id=$id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
}
// ดึงรายชื่อบริษัทมาทำ Dropdown
$companies_list = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$rooms = $conn->query("SELECT * FROM meeting_rooms ORDER BY id DESC");
require_once "header.php";
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-dark text-white py-3">
                    จัดการข้อมูลห้องประชุม
                </div>
                <div class="card-body">
                    <form id="roomForm">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="room_id" value="0">
                        <div class="mb-3">
                            <label class="small fw-bold mb-1">สังกัดบริษัท (Company)</label>
                            <select name="company_id" id="company_id" class="form-select" required>
                                <option value="">-- เลือกบริษัท --</option>
                                <?php while ($comp = $companies_list->fetch_assoc()): ?>
                                    <option value="<?= $comp['id'] ?>">
                                        <?= htmlspecialchars($comp['company_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold mb-1">ชื่อห้อง (Function Room Name)</label>
                            <input type="text" name="room_name" id="room_name" class="form-control" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold mb-1">ชั้น (Floor)</label>
                                <input type="text" name="floor" id="floor" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold mb-1">ความสูง (Ceiling m.)</label>
                                <input type="number" step="0.01" name="height_m" id="height_m" class="form-control">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="small fw-bold mb-1">กว้าง (m)</label>
                                <input type="number" step="0.01" name="width_m" id="width_m"
                                    class="form-control calc-sqm">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold mb-1">ยาว (m)</label>
                                <input type="number" step="0.01" name="length_m" id="length_m"
                                    class="form-control calc-sqm">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold mb-1 text-primary">รวม (sqm)</label>
                                <input type="text" id="display_sqm" class="form-control bg-light text-primary fw-bold"
                                    readonly value="0.00">
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <label class="fw-bold small text-primary mb-2 d-block">Capacity (Persons)</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="small ">Theatre</span>
                                    <input type="number" name="cap_theatre" id="cap_theatre"
                                        class="form-control form-control-sm" value="0">
                                </div>
                                <div class="col-6">
                                    <span class="small ">Classroom</span>
                                    <input type="number" name="cap_classroom" id="cap_classroom"
                                        class="form-control form-control-sm" value="0">
                                </div>
                                <div class="col-6">
                                    <span class="small ">Banquet</span>
                                    <input type="number" name="cap_banquet" id="cap_banquet"
                                        class="form-control form-control-sm" value="0">
                                </div>
                                <div class="col-6">
                                    <span class="small ">Cocktail</span>
                                    <input type="number" name="cap_cocktail" id="cap_cocktail"
                                        class="form-control form-control-sm" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" id="btn-submit" class="btn btn-success fw-bold shadow-sm"><i
                                    class="bi bi-save me-1"></i>บันทึกข้อมูลห้อง</button>
                            <button type="button" class="btn btn-outline-secondary border-0"
                                onclick="resetForm()">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        ห้องประชุม
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
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="roomTable" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-dark">
                                <tr class="small text-uppercase">
                                    <th class="text-start ps-4">ชื้อห้อง / ขั้น</th>
                                    <th>บริษัท</th>
                                    <th>รวม (sqm)</th>
                                    <th>เวที</th>
                                    <th>จัดเลี่ยง</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php while ($row = $rooms->fetch_assoc()): ?>
                                    <tr id="row-<?= $row['id'] ?>" class="text-center">
                                        <td class="text-start ps-4">
                                            <div class="fw-bold text-dark col-room-name">
                                                <?= htmlspecialchars($row['room_name']) ?>
                                            </div>
                                            <div class="small text-muted col-room-info">ชั้น:
                                                <?= htmlspecialchars($row['floor']) ?> | สูง: <?= $row['height_m'] ?>m
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $c_id = $row['company_id'];
                                            $comp = $conn->query("SELECT company_name FROM companies WHERE id = '$c_id'")->fetch_assoc();
                                            echo htmlspecialchars($comp['company_name'] ?? 'ไม่ระบุ');
                                            ?>
                                        </td>
                                        <td><span
                                                class="badge bg-light text-dark border col-sqm"><?= number_format($row['total_sqm'], 2) ?></span>
                                        </td>
                                        <td class="col-theatre"><?= number_format($row['cap_theatre']) ?></td>
                                        <td class="col-banquet"><?= number_format($row['cap_banquet']) ?></td>
                                        <td>
                                            <button class="btn btn-sm text-primary border-0"
                                                onclick='editRoom(<?= json_encode($row) ?>)'><i
                                                    class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm text-danger border-0"
                                                onclick="deleteRoom(<?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
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
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    let roomTable;

    $(document).ready(function () {
        // 1. Initial DataTables
        roomTable = $('#roomTable').DataTable({
            "order": [],
            "pageLength": 10,
            // กำหนดคลาสให้คอลัมน์ล่วงหน้า จะได้ไม่ต้องสั่ง addClass ทีหลัง
            "columnDefs": [
                { "targets": [0], "className": "text-start ps-4" },
                { "targets": [1, 2, 3, 4], "className": "text-center" }
            ]
        });

        // 2. คำนวณ SQM อัตโนมัติ
        $('.calc-sqm').on('input', function () {
            const w = parseFloat($('#width_m').val()) || 0;
            const l = parseFloat($('#length_m').val()) || 0;
            $('#display_sqm').val((w * l).toFixed(2));
        });

        // 3. บันทึกข้อมูลแบบ AJAX (No Reload)
        $('#roomForm').on('submit', function (e) {
            e.preventDefault();
            const form = this;
            const fd = new FormData(form);

            fetch('setting_room.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'updated' || res.status === 'inserted') {

                        // เตรียมข้อมูล HTML สำหรับใส่ในตาราง
                        const roomNameHtml = `
                        <div class="fw-bold text-dark col-room-name ">${fd.get('room_name')}</div>
                        <div class="small text-muted col-room-info">ชั้น: ${fd.get('floor')} | สูง: ${fd.get('height_m')}m</div>
                    `;
                        const sqmHtml = `<span class="badge bg-light text-dark border col-sqm">${res.sqm}</span>`;

                        // ปุ่มแก้ไข (เพิ่ม company_id ลงใน JSON data)
                        const actionButtons = `
                        <button class="btn btn-sm text-primary border-0" onclick='editRoom({
                            "id":"${res.id}",
                            "company_id":"${fd.get('company_id')}",
                            "room_name":"${fd.get('room_name')}",
                            "floor":"${fd.get('floor')}",
                            "length_m":"${fd.get('length_m')}",
                            "width_m":"${fd.get('width_m')}",
                            "height_m":"${fd.get('height_m')}",
                            "cap_theatre":"${fd.get('cap_theatre')}",
                            "cap_classroom":"${fd.get('cap_classroom')}",
                            "cap_banquet":"${fd.get('cap_banquet')}",
                            "cap_cocktail":"${fd.get('cap_cocktail')}"
                        })'><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-sm text-danger border-0" onclick="deleteRoom(${res.id})"><i class="bi bi-trash"></i></button>
                    `;

                        const companyName = $('#company_id option:selected').text();

                        if (res.status === 'updated') {
                            const rowSelector = $('#row-' + res.id);
                            roomTable.row(rowSelector).data([
                                roomNameHtml,                                     // 0: ชื่อห้อง / ชั้น
                                companyName,                                      // 1: บริษัท (ดึงจาก dropdown)
                                sqmHtml,                                          // 2: รวม (SQM)
                                Number(fd.get('cap_theatre')).toLocaleString(),   // 3: เวที
                                Number(fd.get('cap_banquet')).toLocaleString(),   // 4: จัดเลี้ยง
                                actionButtons                                     // 5: จัดการ
                            ]).draw(false);

                            $(rowSelector).css('background-color', '#e3f2fd');
                            setTimeout(() => $(rowSelector).css('background-color', ''), 1000);

                        } else {
                            // กรณี Insert ก็ต้องมี 6 คอลัมน์เหมือนกัน
                            const newRow = roomTable.row.add([
                                roomNameHtml,                                     // 0
                                companyName,                                      // 1
                                sqmHtml,                                          // 2
                                Number(fd.get('cap_theatre')).toLocaleString(),   // 3
                                Number(fd.get('cap_banquet')).toLocaleString(),   // 4
                                actionButtons                                     // 5
                            ]).draw(false).node();

                            $(newRow).attr('id', 'row-' + res.id);
                            $(newRow).css('background-color', '#f1f8e9');
                            setTimeout(() => $(newRow).css('background-color', ''), 1000);
                        }

                        resetForm();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่สามารถบันทึกได้'));
                    }
                })
                .catch(err => console.error('Error:', err));
        });
    });

    // ฟังก์ชันดึงข้อมูลมาแก้ (เลื่อนขึ้นบน)
    function editRoom(data) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // 🏢 กำหนดค่าบริษัทใน Dropdown
        $('#company_id').val(data.company_id);

        // กำหนดค่าอื่นๆ ลงฟอร์ม
        $('#room_id').val(data.id);
        $('#room_name').val(data.room_name);
        $('#floor').val(data.floor);
        $('#length_m').val(data.length_m);
        $('#width_m').val(data.width_m);
        $('#height_m').val(data.height_m);
        $('#cap_theatre').val(data.cap_theatre);
        $('#cap_classroom').val(data.cap_classroom);
        $('#cap_banquet').val(data.cap_banquet);
        $('#cap_cocktail').val(data.cap_cocktail);

        // คำนวณ SQM โชว์ใหม่
        const sqm = (parseFloat(data.width_m) * parseFloat(data.length_m)).toFixed(2);
        $('#display_sqm').val(sqm);

    }

    // ฟังก์ชันล้างฟอร์ม
    function resetForm() {
        // ล้างข้อมูลทุกอย่างในฟอร์ม (รวมถึง Dropdown บริษัท)
        $('#roomForm')[0].reset();

        // ล้างค่า Hidden ID และหน้าจอแสดงผล SQM
        $('#room_id').val(0);
        $('#display_sqm').val("0.00");

        // 🔄 เปลี่ยนปุ่มกลับเป็นโหมด "บันทึกใหม่"
        $('#btn-submit')
            .text('บันทึกข้อมูลห้อง')
            .removeClass('btn-warning')
            .addClass('btn-primary');
    }

    // ฟังก์ชันลบแบบ AJAX
    function deleteRoom(id) {
        if (confirm('เมื่อดำเนินการ จะไม่สามารถย้อนกลับได้')) {
            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch('setting_room.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        roomTable.row($('#row-' + id)).remove().draw(false);
                    } else {
                        alert('เกิดข้อผิดพลาดในการลบ');
                    }
                });
        }
    }
</script>
<?php include "footer.php"; ?>