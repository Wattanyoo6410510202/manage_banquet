<?php
include "config.php";
$user_role = strtolower($_SESSION['role'] ?? 'viewer');
$can_manage = in_array($user_role, ['admin', 'staff', 'gm', 'sale']);

// API SECTION
if (isset($_POST['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save') {
        if (!$can_manage) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        $room_id = intval($_POST['room_id'] ?? 0);
        $company_id = intval($_POST['company_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $customer_id_sql = $customer_id > 0 ? $customer_id : 'NULL';
        $function_type_id = intval($_POST['function_type_id'] ?? 1);
        $event_name = $conn->real_escape_string($_POST['event_name'] ?? '');
        $booking_name = $conn->real_escape_string($_POST['booking_name'] ?? '');
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $organization = $conn->real_escape_string($_POST['organization'] ?? '');
        $pax = intval($_POST['pax'] ?? 0);
        $start_time = $conn->real_escape_string($_POST['start_time'] ?? '');
        $end_time = $conn->real_escape_string($_POST['end_time'] ?? '');
        $remark = $conn->real_escape_string($_POST['remark'] ?? '');

        if (!$room_id || !$start_time || !$end_time || !$event_name) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน']);
            exit;
        }

        // check conflict (exclude self when editing)
        $exclude_sql = $id > 0 ? " AND id != $id" : '';
        $check = $conn->query("SELECT id FROM room_bookings WHERE room_id = $room_id AND status = 'active' $exclude_sql AND (start_time <= '$end_time' AND end_time >= '$start_time')");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ห้องนี้ถูกจองในช่วงเวลาที่เลือกแล้ว']);
            exit;
        }

        $created_by = $conn->real_escape_string($_SESSION['user'] ?? $_SESSION['user_name'] ?? 'Unknown');
        $created_by_id = intval($_SESSION['user_id'] ?? 0);

        if ($id > 0) {
            $sql = "UPDATE room_bookings SET room_id=$room_id, company_id=$company_id, customer_id=$customer_id_sql, function_type_id=$function_type_id,
                event_name='$event_name', booking_name='$booking_name', phone='$phone', organization='$organization',
                pax=$pax, start_time='$start_time', end_time='$end_time', remark='$remark', updated_at=NOW() WHERE id=$id";
            $conn->query($sql);
            echo json_encode(['status' => 'updated', 'id' => $id]);
        } else {
            $ft_res = $conn->query("SELECT prefix FROM function_types WHERE id = $function_type_id");
            $prefix = 'RB';
            if ($ft_row = $ft_res->fetch_assoc()) $prefix = strtoupper($ft_row['prefix']);
            $date_str = date('dm', strtotime($_POST['start_time'] ?? 'now'));
            $seq_res = $conn->query("SELECT COUNT(*) AS cnt FROM room_bookings WHERE booking_code LIKE '{$prefix}{$date_str}%'");
            $seq = intval($seq_res->fetch_assoc()['cnt'] ?? 0) + 1;
            $booking_code = $prefix . $date_str . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO room_bookings (room_id, company_id, customer_id, function_type_id, booking_code, event_name,
                booking_name, phone, organization, pax, start_time, end_time, remark, created_by, created_by_id)
                VALUES ($room_id, $company_id, $customer_id_sql, $function_type_id, '$booking_code', '$event_name',
                '$booking_name', '$phone', '$organization', $pax, '$start_time', '$end_time', '$remark', '$created_by', $created_by_id)";
            $conn->query($sql);
            echo json_encode(['status' => 'inserted', 'id' => $conn->insert_id, 'booking_code' => $booking_code]);
        }
        exit;
    }

    if ($action === 'delete') {
        if (!in_array($user_role, ['admin', 'gm'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบข้อมูล']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE room_bookings SET status='cancelled' WHERE id=$id");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการ']);
        }
        exit;
    }
}

// Query data
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$function_types = $conn->query("SELECT id, type_name, prefix FROM function_types ORDER BY id ASC");
$ft_list = [];
while ($ft = $function_types->fetch_assoc()) $ft_list[] = $ft;

$rooms_data = $conn->query("SELECT mr.id AS rid, mr.room_name, mr.company_id, c.company_name
    FROM meeting_rooms mr LEFT JOIN companies c ON mr.company_id = c.id ORDER BY c.company_name, mr.room_name");

$bookings = $conn->query("SELECT rb.*, mr.room_name, c.company_name, ft.type_name
    FROM room_bookings rb
    LEFT JOIN meeting_rooms mr ON rb.room_id = mr.id
    LEFT JOIN companies c ON rb.company_id = c.id
    LEFT JOIN function_types ft ON rb.function_type_id = ft.id
    WHERE rb.status = 'active'
    ORDER BY rb.start_time DESC");

require_once "header.php";
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-0">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-dark text-white py-3" id="formHeader">
                    <i class="bi bi-calendar-plus me-1"></i> จองห้องประชุม
                </div>
                <div class="card-body">
                    <form id="bookingForm">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="booking_id" value="0">

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">โรงแรม <span class="text-danger">*</span></label>
                            <select name="company_id" id="f_company" class="form-select form-select-sm" required>
                                <option value="">-- เลือก --</option>
                                <?php while ($c = $companies->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">ห้องประชุม <span class="text-danger">*</span></label>
                            <select name="room_id" id="f_room" class="form-select form-select-sm" required>
                                <option value="">-- เลือกโรงแรมก่อน --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">ประเภทงาน <span class="text-danger">*</span></label>
                            <select name="function_type_id" class="form-select form-select-sm" required>
                                <?php foreach ($ft_list as $ft): ?>
                                    <option value="<?= $ft['id'] ?>"><?= htmlspecialchars($ft['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">ชื่องาน <span class="text-danger">*</span></label>
                            <input type="text" name="event_name" class="form-control form-control-sm" required placeholder="เช่น ประชุมบอร์ด">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold mb-1">ชื่อผู้จอง</label>
                                <input type="text" name="booking_name" class="form-control form-control-sm" placeholder="ชื่อ-นามสกุล">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold mb-1">เบอร์โทร</label>
                                <input type="text" name="phone" class="form-control form-control-sm" placeholder="0xx-xxx-xxxx">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">หน่วยงาน / องค์กร</label>
                            <input type="text" name="organization" class="form-control form-control-sm" placeholder="ชื่อบริษัท">
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">จำนวนคน (PAX)</label>
                            <input type="number" name="pax" class="form-control form-control-sm" value="0" min="0">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold mb-1">เริ่ม <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_time" id="f_start" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold mb-1">สิ้นสุด <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="end_time" id="f_end" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-1">หมายเหตุ</label>
                            <input type="text" name="remark" class="form-control form-control-sm" placeholder="หมายเหตุ...">
                        </div>

                        <div class="d-grid gap-2">
                            <?php if ($can_manage): ?>
                                <button type="submit" id="btn-submit" class="btn btn-dark fw-bold">
                                    <i class="bi bi-save me-1"></i>บันทึกการจอง
                                </button>
                                <button type="button" class="btn btn-outline-secondary border-0" onclick="resetForm()">ยกเลิก</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary disabled" style="cursor:not-allowed">
                                    <i class="bi bi-lock-fill me-2"></i>โหมดอ่านอย่างเดียว
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar3 me-2 text-gold"></i>รายการจองห้องประชุม</h5>
                    <div class="btn-group">
                        <button type="button" id="customExcel" class="btn btn-link btn-sm text-success text-decoration-none p-1" title="Excel">
                            <i class="bi bi-file-earmark-excel fs-5"></i>
                        </button>
                        <button type="button" id="customPrint" class="btn btn-link btn-sm text-secondary text-decoration-none p-1" title="Print">
                            <i class="bi bi-printer fs-5"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="bookingTable" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-dark">
                                <tr class="small text-uppercase">
                                    <th>เลขที่</th>
                                    <th>ชื่องาน</th>
                                    <th>ห้อง / โรงแรม</th>
                                    <th>วันที่</th>
                                    <th>เวลา</th>
                                    <th>ผู้จอง</th>
                                    <th>PAX</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php while ($b = $bookings->fetch_assoc()):
                                    $is_morning = (new DateTime($b['start_time']))->format('H') < 12;
                                ?>
                                <tr id="row-<?= $b['id'] ?>">
                                    <td><span class="badge bg-dark rounded-pill"><?= htmlspecialchars($b['booking_code']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($b['event_name']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($b['room_name'] ?? '-') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($b['company_name'] ?? '') ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($b['start_time'])) ?></td>
                                    <td>
                                        <?= date('H:i', strtotime($b['start_time'])) ?> - <?= date('H:i', strtotime($b['end_time'])) ?>
                                        <span class="badge <?= $is_morning ? 'bg-warning text-dark' : 'bg-info' ?> ms-1" style="font-size:0.65rem">
                                            <?= $is_morning ? 'เช้า' : 'บ่าย' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($b['booking_name'] ?: '-') ?></td>
                                    <td class="text-center"><?= $b['pax'] ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm text-primary border-0" onclick='editBooking(<?= json_encode($b) ?>)'><i class="bi bi-pencil-square"></i></button>
                                            <?php if (in_array($user_role, ['admin', 'gm'])): ?>
                                            <button class="btn btn-sm text-danger border-0" onclick="deleteBooking(<?= $b['id'] ?>)"><i class="bi bi-trash"></i></button>
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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
const allRooms = <?= json_encode(iterator_to_array($rooms_data)) ?>;
const functionTypes = <?= json_encode($ft_list) ?>;
let bookingTable;

$(document).ready(function() {
    bookingTable = $('#bookingTable').DataTable({
        order: [[3, 'desc']],
        pageLength: 15,
        columnDefs: [
            { targets: [6], className: 'text-center' },
            { targets: [7], orderable: false }
        ]
    });

    $('#f_company').on('change', function() {
        const cid = this.value;
        const sel = $('#f_room');
        sel.html('<option value="">-- เลือกห้อง --</option>');
        if (!cid) { sel.html('<option value="">-- เลือกโรงแรมก่อน --</option>'); return; }
        allRooms.filter(r => r.company_id == cid).forEach(r => {
            sel.append(`<option value="${r.rid}">${r.room_name}</option>`);
        });
    });

    $('#bookingForm').on('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('room_calendar.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'inserted' || res.status === 'updated') {
                    Swal.fire({ icon: 'success', title: res.status === 'inserted' ? 'จองสำเร็จ!' : 'แก้ไขสำเร็จ!',
                        html: res.booking_code ? `เลขที่: <b>${res.booking_code}</b>` : 'บันทึกข้อมูลแล้ว',
                        timer: 1500, showConfirmButton: false }).then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            });
    });
});

function editBooking(data) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    $('#booking_id').val(data.id);
    $('#f_company').val(data.company_id).trigger('change');
    setTimeout(() => {
        $('#f_room').val(data.room_id);
    }, 100);
    $('select[name="function_type_id"]').val(data.function_type_id);
    $('input[name="event_name"]').val(data.event_name);
    $('input[name="booking_name"]').val(data.booking_name);
    $('input[name="phone"]').val(data.phone);
    $('input[name="organization"]').val(data.organization);
    $('input[name="pax"]').val(data.pax);
    $('input[name="start_time"]').val(data.start_time.replace(' ', 'T').substring(0, 16));
    $('input[name="end_time"]').val(data.end_time.replace(' ', 'T').substring(0, 16));
    $('input[name="remark"]').val(data.remark);
    $('#formHeader').html('<i class="bi bi-pencil-square me-1"></i> แก้ไขการจอง');
    $('#btn-submit').html('<i class="bi bi-save me-1"></i>อัปเดต').removeClass('btn-dark').addClass('btn-primary');
}

function resetForm() {
    $('#bookingForm')[0].reset();
    $('#booking_id').val(0);
    $('#f_room').html('<option value="">-- เลือกโรงแรมก่อน --</option>');
    $('#formHeader').html('<i class="bi bi-calendar-plus me-1"></i> จองห้องประชุม');
    $('#btn-submit').html('<i class="bi bi-save me-1"></i>บันทึกการจอง').removeClass('btn-primary').addClass('btn-dark');
}

function deleteBooking(id) {
    Swal.fire({ title: 'ยืนยันยกเลิกการจอง?', icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', confirmButtonText: 'ยกเลิก', cancelButtonText: 'กลับ'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('room_calendar.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        bookingTable.row($('#row-' + id)).remove().draw(false);
                        Swal.fire('สำเร็จ', 'ยกเลิกการจองแล้ว', 'success');
                    } else {
                        Swal.fire('ผิดพลาด', res.message, 'error');
                    }
                });
        }
    });
}
</script>
<?php include "footer.php"; ?>
