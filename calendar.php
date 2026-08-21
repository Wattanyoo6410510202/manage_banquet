<?php include "config.php";
include "header.php";

$current_user_name = $_SESSION['user_name'] ?? '';
$user_role = strtolower($_SESSION['role'] ?? '');
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$rooms = $conn->query("SELECT id, room_name, company_id FROM meeting_rooms WHERE status = 'active' ORDER BY room_name ASC");
$rooms_json = [];
while($r = $rooms->fetch_assoc()) { $rooms_json[] = $r; }
$function_types = $conn->query("SELECT id, type_name, prefix FROM function_types ORDER BY id ASC");
$ft_list = [];
while ($ft = $function_types->fetch_assoc()) $ft_list[] = $ft;
$break_types = $conn->query("SELECT id, type_name FROM master_break_types ORDER BY id ASC");
$break_type_list = [];
while ($bt = $break_types->fetch_assoc()) $break_type_list[] = $bt;
$menu_types = $conn->query("SELECT id, type_name FROM master_menu_types ORDER BY id ASC");
$menu_type_list = [];
while ($mt = $menu_types->fetch_assoc()) $menu_type_list[] = $mt;
$can_manage = in_array($user_role, ['admin', 'staff', 'gm', 'sale']);
$users = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff','Admin','Sale','Manager','GM') ORDER BY name ASC");
$user_list = [];
while ($u = $users->fetch_assoc()) $user_list[] = $u;
?>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://code.jquery.com/jquery-3.7.0.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="container-fluid p-0">

    <!-- Room Conflict Card -->
    <?php
    /* จำกัดช่วงเวลา อดีต 1 เดือน ~ อนาคต 6 เดือน — เดิมเทียบทุกแถวกับทุกแถวทั้งประวัติศาสตร์ (O(n²)) ข้อมูลเยอะจะค้าง */
    $conflict_sql = "SELECT
        f1.id AS id1, f1.function_name AS name1, f1.start_time AS start1, f1.end_time AS end1,
        f2.id AS id2, f2.function_name AS name2, f2.start_time AS start2, f2.end_time AS end2,
        f1.room_id, mr.room_name,
        COALESCE(f1.approve, 0) AS approve1, COALESCE(f2.approve, 0) AS approve2,
        f1.status AS status1, f2.status AS status2
    FROM functions f1
    INNER JOIN functions f2 ON f1.id < f2.id
        AND f1.room_id IS NOT NULL AND f2.room_id IS NOT NULL
        AND f1.start_time IS NOT NULL AND f2.start_time IS NOT NULL
        AND f1.end_time IS NOT NULL AND f2.end_time IS NOT NULL
        AND f1.room_id = f2.room_id
        AND f1.start_time < f2.end_time
        AND f1.end_time > f2.start_time
    INNER JOIN meeting_rooms mr ON f1.room_id = mr.id
    WHERE (f1.approve = 1 OR f2.approve = 1)
        AND f1.status NOT IN ('Cancelled', 'Completed')
        AND f2.status NOT IN ('Cancelled', 'Completed')
        AND f1.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        AND f1.start_time < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
    ORDER BY f1.start_time ASC";
    $conflict_q = mysqli_query($conn, $conflict_sql);
    $conflicts = [];
    if ($conflict_q) {
        while ($cr = mysqli_fetch_assoc($conflict_q)) {
            $conflicts[] = $cr;
        }
    } elseif ($conn->error) {
        error_log("Calendar conflict SQL error: " . $conn->error);
    }
    ?>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <?php if (!empty($conflicts)): ?>
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning bg-opacity-10 border-warning d-flex align-items-center gap-2 py-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    <span class="fw-bold text-warning-emphasis">พบการทับซ้อนของห้อง (<?= count($conflicts) ?> รายการ)</span>
                    <button class="btn btn-sm btn-outline-warning ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#conflictCollapse" aria-expanded="true">
                        <i class="bi bi-chevron-up"></i>
                    </button>
                </div>
                <div class="collapse show" id="conflictCollapse">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
                                <thead class="table-warning">
                                    <tr>
                                        <th>ห้อง</th>
                                        <th>งานที่ 1</th>
                                        <th>เวลาเริ่ม</th>
                                        <th>เวลาสิ้นสุด</th>
                                        <th>สถานะ</th>
                                        <th>งานที่ 2</th>
                                        <th>เวลาเริ่ม</th>
                                        <th>เวลาสิ้นสุด</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conflicts as $c): 
                                        $s1_class = ($c['approve1'] == 1) ? 'bg-success-subtle' : '';
                                        $s2_class = ($c['approve2'] == 1) ? 'bg-success-subtle' : '';
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($c['room_name']) ?></td>
                                        <td class="<?= $s1_class ?>">
                                            <a href="edit.php?id=<?= $c['id1'] ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($c['name1']) ?></a>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($c['start1'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($c['end1'])) ?></td>
                                        <td><span class="badge <?= $c['approve1'] == 1 ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($c['status1']) ?></span></td>
                                        <td class="<?= $s2_class ?>">
                                            <a href="edit.php?id=<?= $c['id2'] ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($c['name2']) ?></a>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($c['start2'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($c['end2'])) ?></td>
                                        <td><span class="badge <?= $c['approve2'] == 1 ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($c['status2']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-success shadow-sm">
                <div class="card-header bg-success bg-opacity-10 border-success d-flex align-items-center gap-2 py-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span class="fw-bold text-success-emphasis">ไม่มีการทับซ้อนของห้อง</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top row: Calendar -->
    <div class="row g-3">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-2">
                            <label class="cal-filter-label">โรงแรม</label>
                            <select id="companyFilter" class="form-select form-select-sm" onchange="filterRooms()">
                                <option value="all">ทุกโรงแรม</option>
                                <?php while ($c = $companies->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="cal-filter-label">ห้องประชุม</label>
                            <select id="roomFilter" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="all">ทุกห้องประชุม</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="cal-filter-label">แหล่งข้อมูล</label>
                            <select id="dataSource" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="all" selected>ทั้งหมด (All)</option>
                                <option value="eo">Function Order (EO)</option>
                                <option value="quotation">ใบเสนอราคาทั้งหมด</option>
                                <option value="qt_approved">ใบเสนอราคาที่อนุมัติแล้ว</option>
                                <option value="room">จองห้องประชุม (Room)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="cal-filter-label">สถานะอนุมัติ</label>
                            <select id="approveFilter" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="approved">เฉพาะที่อนุมัติแล้ว</option>
                                <option value="all" selected>ทั้งหมด (รวมรออนุมัติ)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="cal-filter-label">โหมดเวลา</label>
                            <select id="timeMode" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="general" selected>เวลาจองหลัก</option>
                                <option value="schedule">กำหนดการ</option>
                            </select>
                        </div>
                        <?php if ($can_manage): ?>
                        <div class="col-md-2">
                            <button class="btn btn-dark btn-sm w-100" onclick="openRoomBookingModal()">
                                <i class="bi bi-door-open me-1"></i> จองห้องประชุม
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="cal-legend">
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#ffc107"></i>รออนุมัติ</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#0dcaf0"></i>อนุมัติแล้ว</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#0d6efd"></i>ดำเนินการ</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#198754"></i>จบงานแล้ว</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#fd7e14"></i>QT อนุมัติ</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#6c757d"></i>QT ฉบับร่าง</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#dc3545"></i>ยกเลิก / ต้องแก้ไข</span>
                        <span class="cal-legend-item"><i class="cal-dot" style="background:#6f42c1"></i>จองห้องประชุม</span>
                    </div>
                    <div class="cal-legend cal-legend-type">
                        <span class="cal-legend-label">ไอคอน/ความเข้มพื้นหลัง = ประเภท:</span>
                        <span class="cal-legend-item"><i class="cal-swatch cal-chip-eo"></i><i class="bi bi-journal-bookmark-fill me-1"></i>งานจัดเลี้ยง (EO)</span>
                        <span class="cal-legend-item"><i class="cal-swatch cal-chip-qt"></i><i class="bi bi-file-earmark-text-fill me-1"></i>ใบเสนอราคา (ตัวเอียง)</span>
                        <span class="cal-legend-item"><i class="cal-swatch cal-chip-rb"></i><i class="bi bi-door-closed-fill me-1"></i>จองห้องประชุม</span>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom row: Day Timetable full width -->
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        ตารางเวลา: <span id="selectedDateText" class="text-primary"><?php $m=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']; echo date('j').' '.$m[(int)date('n')].' '.(date('Y')+543); ?></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select id="timetableType" class="form-select form-select-sm" style="width:auto;" onchange="refreshTimetable()">
                            <option value="eo">เฉพาะ EO</option>
                            <option value="qt" selected>เฉพาะใบเสนอราคา</option>
                            <option value="all">ทั้งหมด</option>
                        </select>
                        <select id="timetableSalesperson" class="form-select form-select-sm" style="width:auto;" onchange="refreshTimetable()">
                            <option value="all">เซลล์ทั้งหมด</option>
                            <?php foreach ($user_list as $u): ?>
                                <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="month" id="timetableMonthPicker" class="form-control form-control-sm" style="width:auto;" onchange="onTimetableMonthChange()">
                        <input type="date" id="timetableDatePicker" class="form-control form-control-sm" style="width:auto;">
                        <span id="eventCountBadge" class="badge bg-primary d-none">0 งาน</span>
                        <button class="btn btn-success btn-sm" onclick="exportExcel()"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="dayTimetable">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="7%">วันที่จัดงาน</th>
                                    <th width="7%">เลขที่</th>
                                    <th>กิจกรรม</th>
                                    <th width="9%">ลูกค้า</th>
                                    <th width="8%">เบอร์โทร</th>
                                    <th width="6%">ที่มา Lead</th>
                                    <th width="6%">ผู้รับผิดชอบ</th>
                                    <th width="7%">งบประมาณ</th>
                                    <th width="6%">ผลงาน</th>
                                    <th width="7%">สถานะ</th>
                                    <th width="6%">วันที่เสนอราคา</th>
                                    <th width="6%">วันที่ Inspection</th>
                                    <th width="6%">วันที่ Follow Up</th>
                                    <th width="6%">วันที่ Confirmed</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Detail Modal -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-gold py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i> รายละเอียดกิจกรรม</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailBody">
                <div class="text-center text-muted py-5">
                    <i class="bi bi-calendar2-week d-block mb-3" style="font-size:3rem;"></i>
                    <p>กำลังโหลดข้อมูล...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Room Booking Modal -->
<div class="modal fade" id="roomBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:1rem;">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-door-open me-2"></i>จองห้องประชุม</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="roomBookingForm">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="0">
                    <input type="hidden" name="customer_id" id="rb_customer_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">โรงแรม <span class="text-danger">*</span></label>
                            <select name="company_id" id="rb_company" class="form-select form-select-sm" required>
                                <option value="">-- เลือก --</option>
                                <?php
                                $companies->data_seek(0);
                                while ($c = $companies->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ห้องประชุม <span class="text-danger">*</span></label>
                            <select name="room_id" id="rb_room" class="form-select form-select-sm" required>
                                <option value="">-- เลือกโรงแรมก่อน --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ประเภทงาน <span class="text-danger">*</span></label>
                            <select name="function_type_id" class="form-select form-select-sm" required>
                                <?php foreach ($ft_list as $ft): ?>
                                    <option value="<?= $ft['id'] ?>"><?= htmlspecialchars($ft['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">จำนวนคน (PAX)</label>
                            <input type="number" name="pax" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-secondary mb-1">ชื่องาน <span class="text-danger">*</span></label>
                            <input type="text" name="event_name" class="form-control form-control-sm" required placeholder="เช่น ประชุมบอร์ด">
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-secondary mb-1">ลูกค้า (เลือกจากระบบ ถ้ามี)</label>
                            <select id="rb_customer" class="form-select form-select-sm" style="width:100%;">
                                <option value="">-- ค้นหาลูกค้า หรือพิมพ์เองด้านล่าง --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ชื่อผู้จอง</label>
                            <input type="text" name="booking_name" id="rb_booking_name" class="form-control form-control-sm" placeholder="ชื่อ-นามสกุล">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">เบอร์โทร</label>
                            <input type="text" name="phone" id="rb_phone" class="form-control form-control-sm" placeholder="0xx-xxx-xxxx">
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-secondary mb-1">หน่วยงาน / องค์กร</label>
                            <input type="text" name="organization" id="rb_organization" class="form-control form-control-sm" placeholder="ชื่อบริษัท">
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12 small fw-bold text-muted">รายละเอียดสำหรับ Sales (ไม่บังคับ)</div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">เบรก</label>
                            <select name="break_type_id" class="form-select form-select-sm">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach ($break_type_list as $bt): ?>
                                    <option value="<?= $bt['id'] ?>"><?= htmlspecialchars($bt['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ประเภทอาหาร</label>
                            <select name="menu_type_id" class="form-select form-select-sm">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach ($menu_type_list as $mt): ?>
                                    <option value="<?= $mt['id'] ?>"><?= htmlspecialchars($mt['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ห้องพัก</label>
                            <input type="text" name="room_stay" class="form-control form-control-sm" placeholder="เช่น Standard Twin x2 คืน">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">ราคาขาย (บาท)</label>
                            <input type="number" name="selling_price" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">เริ่ม <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_time" id="rb_start" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-secondary mb-1">สิ้นสุด <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="end_time" id="rb_end" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-secondary mb-1">หมายเหตุ</label>
                            <input type="text" name="remark" class="form-control form-control-sm" placeholder="หมายเหตุ...">
                        </div>
                    </div>
                    <div id="rbConflictAlert" class="alert alert-danger mt-3 mb-0 d-none" style="font-size:0.8rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <span id="rbConflictMsg"></span>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" id="btnSubmitRb" class="btn btn-dark btn-sm px-4">
                            <i class="bi bi-check-lg me-1"></i> บันทึกการจอง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let calendar;
let lastTimetableDate = null;
const currentUserName = '<?php echo addslashes($current_user_name); ?>';
const currentUserRole = '<?php echo addslashes($user_role); ?>';

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var eventDetailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        aspectRatio: 1.5,
        locale: 'th',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'วันนี้',
            month: 'เดือน',
            week: 'สัปดาห์',
            day: 'วัน',
            list: 'รายการ'
        },
        dayMaxEvents: 3,
        moreLinkText: function(n) { return '+ ' + n + ' งาน'; },
        navLinks: true,
        navLinkDayClick: function(date) {
            lastTimetableDate = date;
            document.getElementById('timetableMonthPicker').value = '';
            updateDayTimetable(date);
        },
        dateClick: function(info) {
            lastTimetableDate = info.date;
            document.getElementById('timetableMonthPicker').value = '';
            updateDayTimetable(info.date);
        },
        datesSet: function(info) {
            lastTimetableDate = null;
            document.getElementById('timetableDatePicker').value = '';
            updateStats();
            updateDayTimetable(null, info.start, info.end);
        },
        eventContent: function(arg) {
            const hasTime = arg.event.startStr && arg.event.startStr.includes('T');
            const timeStr = hasTime ? arg.event.startStr.split('T')[1].substring(0, 5) : '';
            // ใช้สีเดียวกับพื้นหลังของ event (มาจาก 'color' ที่ backend กำหนดไว้แล้ว) แทนการคำนวณซ้ำ
            // เพื่อไม่ให้สีขอบเพี้ยนไปจากสีแถบ event จริง
            const color = arg.event.backgroundColor || arg.event.extendedProps.color || '#0dcaf0';
            const title = (arg.event.title || '').replace(/</g, '&lt;');

            // แยก "ประเภท" ออกจาก "สถานะ" ด้วยคนละมิติ: สถานะ = สีขอบ (ตาม legend เดิม),
            // ประเภท = ความเข้ม/หนาของพื้นหลัง — EO จางสุด, ใบเสนอราคากลาง, จองห้องเข้มสุด (เพราะล็อกห้องจริง)
            let typeClass = 'cal-chip-eo';
            let typeIcon = 'bi-journal-bookmark-fill';
            if (arg.event.id.startsWith('rb_')) { typeClass = 'cal-chip-rb'; typeIcon = 'bi-door-closed-fill'; }
            else if (arg.event.id.startsWith('qt_')) { typeClass = 'cal-chip-qt'; typeIcon = 'bi-file-earmark-text-fill'; }

            // สำคัญ: ต้องมี overflow:hidden ที่ตัว wrapper และ min-width:0 ที่ลูกใน flex
            // ไม่งั้นข้อความยาวจะ "ล้น" ทะลุไปทับช่องวันถัดไปแทนที่จะถูกตัดด้วย ellipsis
            return {
                html: `<div class="cal-chip ${typeClass}" style="border-left-color:${color};">
                    <i class="bi ${typeIcon} cal-chip-icon" style="color:${color};"></i>
                    ${timeStr ? `<span class="cal-chip-time" style="color:${color};">${timeStr}</span>` : ''}
                    <span class="cal-chip-title">${title}</span>
                </div>`
            };
        },
        events: {
            url: 'api/calendar_events.php',
            method: 'GET',
            extraParams: function () { return { _: Date.now() }; },
            failure: function () { alert('\u0e42\u0e2b\u0e25\u0e14\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e07\u0e32\u0e19\u0e43\u0e19\u0e1b\u0e0f\u0e34\u0e17\u0e34\u0e19\u0e44\u0e21\u0e48\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08'); }
        },

        eventClick: function (info) {
            const props = info.event.extendedProps;
            const eventId = info.event.extendedProps.ref_id;
            const isQt = info.event.id.startsWith('qt_');
            const isRb = info.event.id.startsWith('rb_');
            const st = (props.status || '').toLowerCase();

            let badgeClass = 'bg-secondary';
            if(isRb) {
                badgeClass = 'bg-purple text-white';
            } else if(isQt) {
                if(st.includes('draft')) badgeClass = 'bg-secondary';
                else if(st.includes('freeze')) badgeClass = 'bg-danger';
                else badgeClass = 'bg-dark text-light';
            } else {
                if(st === 'pending') badgeClass = 'bg-warning text-dark';
                else if(st === 'confirmed' || st === 'approved') badgeClass = 'bg-info text-dark';
                else if(st === 'in progress') badgeClass = 'bg-primary';
                else if(st === 'completed') badgeClass = 'bg-success';
                else if(st === 'cancelled') badgeClass = 'bg-danger';
            }

            if(isRb) {
                const evStart = info.event.start ? new Date(info.event.start).toLocaleString('th-TH', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
                const evEnd = info.event.end ? new Date(info.event.end).toLocaleString('th-TH', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
                document.getElementById('modalDetailBody').innerHTML = `
                <div class="animate__animated animate__fadeIn">
                    <div class="text-center mb-3 pb-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">RB #${props.booking_code || eventId}</small>
                            <span class="badge bg-purple">${props.status}</span>
                        </div>
                        <h5 class="fw-bold mt-2 mb-0" style="color:#1a1a1a;">${props.mainTitle}</h5>
                    </div>
                    <div class="mb-3">
                        <div class="row g-2">
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-person me-1"></i>ผู้จอง</small><span class="fw-bold small">${props.customer || '-'}</span></div></div>
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>เบอร์โทร</small><span class="fw-bold small">${props.phone || '-'}</span></div></div>
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-building me-1"></i>หน่วยงาน</small><span class="fw-bold small">${props.organization || '-'}</span></div></div>
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>ห้อง</small><span class="fw-bold small">${props.room || '-'}</span></div></div>
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-people me-1"></i>จำนวนคน</small><span class="fw-bold small">${props.pax || '0'} คน</span></div></div>
                            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-clock me-1"></i>เวลา</small><span class="fw-bold small">${evStart}<br>${evEnd}</span></div></div>
                            ${props.remark ? `<div class="col-12"><div class="p-2 rounded bg-light"><small class="text-muted d-block"><i class="bi bi-sticky me-1"></i>หมายเหตุ</small><span class="small">${props.remark}</span></div></div>` : ''}
                        </div>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="room_calendar.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-door-open me-2"></i> ดูหน้าจองห้องประชุม</a>
                        <?php if (in_array($user_role, ['admin', 'gm'])): ?>
                        <button class="btn btn-outline-danger btn-sm" onclick="cancelRoomBooking(${eventId})"><i class="bi bi-x-circle me-2"></i>ยกเลิกการจอง</button>
                        <?php endif; ?>
                    </div>
                </div>`;
                eventDetailModal.show();
                return;
            }

            const viewUrl = isQt ? 'quotation_view.php?id=' : 'view.php?id=';
            window.open(viewUrl + eventId, '_blank', 'width=1100,height=900');
            return;

            document.getElementById('modalDetailBody').innerHTML = `
            <div class="animate__animated animate__fadeIn">
                <div class="text-center mb-3 pb-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">${isQt ? 'QT' : 'EO'} #${eventId}</small>
                        <span class="badge ${badgeClass}">${props.status}</span>
                    </div>
                    <h5 class="fw-bold mt-2 mb-0" style="color:#1a1a1a;">${props.mainTitle}</h5>
                </div>

                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-person me-1"></i>ลูกค้า</small>
                                <span class="fw-bold small">${props.customer || 'ไม่ได้ระบุ'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>เบอร์โทร</small>
                                <span class="fw-bold small">${props.phone || '-'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>สถานที่</small>
                                <span class="fw-bold small">${props.room || '-'}</span>
                            </div>
                        </div>
                        ${props.func_type_name ? `
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-tag me-1"></i>ประเภทงาน</small>
                                <span class="fw-bold small">${props.func_type_name}</span>
                            </div>
                        </div>` : ''}
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-people me-1"></i>จำนวนคน</small>
                                <span class="fw-bold small">${props.pax || '0'} คน</span>
                            </div>
                        </div>
                        ${!isQt ? `
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-cash-stack me-1"></i>มัดจำ</small>
                                <span class="fw-bold small">฿${props.deposit}</span>
                            </div>
                        </div>` : ''}
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-wallet2 me-1"></i>มูลค่ารวม</small>
                                <span class="fw-bold small">฿${props.total}</span>
                            </div>
                        </div>
                        ${props.remark ? `
                        <div class="col-12">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-sticky me-1"></i>หมายเหตุ</small>
                                <span class="small">${props.remark}</span>
                            </div>
                        </div>` : ''}
                    </div>
                </div>

                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-person-badge me-1"></i>ผู้รับผิดชอบ</small>
                                <span class="fw-bold small">${props.created_by_name || '-'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-tag me-1"></i>ที่มา Lead</small>
                                <span class="fw-bold small">${props.lead_source || '-'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-calendar-plus me-1"></i>วันที่เสนอราคา</small>
                                <span class="fw-bold small">${fmtDateTime(props.created_at)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-check2-square me-1"></i>วันที่ Confirmed</small>
                                <span class="fw-bold small">${fmtDate(props.confirmed_date)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-binoculars me-1"></i>วันที่ Inspection</small>
                                <span class="fw-bold small">${fmtDate(props.inspection_date)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-clock-history me-1"></i>วันที่ Follow Up</small>
                                <span class="fw-bold small">${fmtDate(props.follow_up_date)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <small class="text-muted d-block"><i class="bi bi-graph-up me-1"></i>ผลการดำเนินงาน</small>
                                <span class="fw-bold small">${props.result || '-'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-grid gap-2">
                    <a href="${viewUrl}${eventId}" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-2"></i> พิมพ์เอกสาร (PDF)
                    </a>
                </div>
            </div>
            `;
            eventDetailModal.show();
        }
    });
    // กำหนดค่าเริ่มต้น month picker เป็นเดือนปัจจุบัน
    const now = new Date();
    const defaultMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    const monthPicker = document.getElementById('timetableMonthPicker');
    if (monthPicker) monthPicker.value = defaultMonth;

    calendar.render();
    updateCalendarEvents();
    updateStats();
});

function updateStats() {
    if (!calendar) return;
    const allEvents = calendar.getEvents().filter(ev => ev.display !== 'none' && !(ev.id.startsWith('qt_') && ev.extendedProps.status.toLowerCase().includes('draft')));
    const now = new Date();
    const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const monthStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');

    const todayEvents = allEvents.filter(ev => ev.startStr.startsWith(todayStr));
    const monthEvents = allEvents.filter(ev => ev.startStr.startsWith(monthStr));

    const elToday = document.getElementById('statToday');
    const elMonth = document.getElementById('statMonth');
    const elPax = document.getElementById('statPax');
    const elRevenue = document.getElementById('statRevenue');
    if (elToday) elToday.textContent = todayEvents.length + ' งาน';
    if (elMonth) elMonth.textContent = monthEvents.length + ' งาน';
    if (elPax) elPax.textContent = monthEvents.reduce((sum, ev) => {
        const p = parseInt(ev.extendedProps.pax) || 0;
        return sum + p;
    }, 0) + ' คน';
    const totalRev = monthEvents.reduce((sum, ev) => {
        const t = parseFloat((ev.extendedProps.total || '0').replace(/,/g, '')) || 0;
        return sum + t;
    }, 0);
    if (elRevenue) elRevenue.textContent = '฿' + totalRev.toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function belongsToUser(ev) {
    if (currentUserRole === 'admin') return true;
    const creator = ev.extendedProps.created_by_name || '';
    return creator === currentUserName;
}

function refreshTimetable() {
    if (lastTimetableDate) {
        updateDayTimetable(lastTimetableDate);
    } else if (calendar && calendar.view) {
        const vs = calendar.view.currentStart;
        const ve = calendar.view.currentEnd;
        updateDayTimetable(null, vs, ve);
    }
}

function onTimetableMonthChange() {
    const monthPicker = document.getElementById('timetableMonthPicker');
    const datePicker = document.getElementById('timetableDatePicker');
    if (monthPicker && monthPicker.value) {
        datePicker.value = '';
        lastTimetableDate = null;
    }
    refreshTimetable();
}

function updateDayTimetable(date, viewStart, viewEnd) {
    const dateText = document.getElementById('selectedDateText');
    const timetableBody = document.querySelector('#dayTimetable tbody');
    const eventCountBadge = document.getElementById('eventCountBadge');
    const datePicker = document.getElementById('timetableDatePicker');
    const typeFilter = document.getElementById('timetableType')?.value || 'eo';
    const salesFilter = document.getElementById('timetableSalesperson')?.value || 'all';
    const isQtView = typeFilter === 'qt';
    const tblColspan = isQtView ? 18 : 14;
    setTimetableHeader(typeFilter);
    timetableBody.innerHTML = '';

    // ถ้ามี month picker → ใช้เดือนนั้นแทน calendar view
    const monthPicker = document.getElementById('timetableMonthPicker');
    if (!date && monthPicker && monthPicker.value) {
        const parts = monthPicker.value.split('-');
        const y = parseInt(parts[0]);
        const m = parseInt(parts[1]) - 1;
        viewStart = new Date(y, m, 1);
        viewEnd = new Date(y, m + 1, 1);
    }

    const passesTimetableFilters = (ev) => {
        if (typeFilter === 'eo' && ev.id.startsWith('qt_')) return false;
        if (typeFilter === 'qt' && !ev.id.startsWith('qt_')) return false;
        if (salesFilter !== 'all') {
            const creator = ev.extendedProps.created_by_name || '';
            if (creator !== salesFilter) return false;
        }
        return true;
    };

    if (date !== null && date !== undefined) {
        if (typeof date === 'string' && date.includes('-')) {
            const d = new Date(date + 'T00:00:00');
            const dateStr = date;
            dateText.innerText = d.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
            if (datePicker) datePicker.value = dateStr;

            const events = calendar.getEvents().filter(ev => {
                const evStart = ev.startStr.split('T')[0];
                const isVisible = ev.display !== 'none';
                return evStart === dateStr && isVisible && belongsToUser(ev) && passesTimetableFilters(ev);
            });

            if (events.length === 0) {
                timetableBody.innerHTML = `<tr><td colspan="${tblColspan}" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในวันที่เลือก</td></tr>`;
                eventCountBadge.classList.add('d-none');
                (document.getElementById('statDayEvents') || {}).textContent = '0 งาน';
                return;
            }

            eventCountBadge.classList.remove('d-none');
            eventCountBadge.textContent = events.length + ' งาน';
            (document.getElementById('statDayEvents') || {}).textContent = events.length + ' งาน';
            renderTimetableRows(events, tblColspan, isQtView);
            return;
        }

        // แสดงเฉพาะวันเดียว
        const d = new Date(date);
        const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        dateText.innerText = d.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
        if (datePicker) datePicker.value = dateStr;

        const events = calendar.getEvents().filter(ev => {
            const evStart = ev.startStr.split('T')[0];
            const isVisible = ev.display !== 'none';
            return evStart === dateStr && isVisible && belongsToUser(ev) && passesTimetableFilters(ev);
        });

        if (events.length === 0) {
            timetableBody.innerHTML = `<tr><td colspan="${tblColspan}" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในวันที่เลือก</td></tr>`;
            eventCountBadge.classList.add('d-none');
            (document.getElementById('statDayEvents') || {}).textContent = '0 งาน';
            return;
        }

        eventCountBadge.classList.remove('d-none');
        eventCountBadge.textContent = events.length + ' งาน';
        (document.getElementById('statDayEvents') || {}).textContent = events.length + ' งาน';
        renderTimetableRows(events, tblColspan, isQtView);
        return;
    }

    // แสดงทั้งหมดในช่วงวันที่กำหนด
    if (!viewStart || !viewEnd) return;
    const startStr = viewStart.toISOString().split('T')[0];
    const endStr = viewEnd.toISOString().split('T')[0];

    const clampedStart = startStr;
    const clampedEnd = endStr;
    if (clampedStart >= clampedEnd) {
        timetableBody.innerHTML = `<tr><td colspan="${tblColspan}" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในช่วงนี้</td></tr>`;
        eventCountBadge.classList.add('d-none');
        (document.getElementById('statDayEvents') || {}).textContent = '0 งาน';
        return;
    }
    const allEvents = calendar.getEvents().filter(ev => {
        const evStart = ev.startStr.split('T')[0];
        const isVisible = ev.display !== 'none';
        return evStart >= clampedStart && evStart < clampedEnd && isVisible && belongsToUser(ev) && passesTimetableFilters(ev);
    }).sort((a, b) => (a.startStr + a.id).localeCompare(b.startStr + b.id));

    const totalCount = allEvents.length;
    const startLabel = new Date(clampedStart + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    const endLabel = new Date(clampedEnd + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    dateText.innerText = startLabel + ' — ' + endLabel;

    if (totalCount === 0) {
        timetableBody.innerHTML = `<tr><td colspan="${tblColspan}" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในช่วงนี้</td></tr>`;
        eventCountBadge.classList.add('d-none');
        (document.getElementById('statDayEvents') || {}).textContent = '0 งาน';
        return;
    }

    eventCountBadge.classList.remove('d-none');
    eventCountBadge.textContent = totalCount + ' งาน';
    { const el = document.getElementById('statDayEvents'); if (el) el.innerHTML = totalCount + ' งาน <small class="text-muted fw-normal">(' + startLabel + ' - ' + endLabel + ')</small>'; }

    // จัดกลุ่มตามวันที่ แล้วตามห้อง
    const groupedByDate = {};
    allEvents.forEach(ev => {
        const d = ev.startStr.split('T')[0];
        if (!groupedByDate[d]) groupedByDate[d] = [];
        groupedByDate[d].push(ev);
    });

    Object.keys(groupedByDate).sort().forEach(dateKey => {
        const dateEvents = groupedByDate[dateKey];
        const d = new Date(dateKey + 'T00:00:00');
        const dateLabel = d.toLocaleDateString('th-TH', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

        dateEvents.forEach(ev => isQtView ? renderQtRow(ev) : renderRow(ev));
    });

    if (isQtView) {
        let totalAmt = 0, confirmedAmt = 0, pendingAmt = 0, totalDep = 0;
        allEvents.forEach(ev => {
            const p = ev.extendedProps;
            const t = parseFloat((p.total || '0').replace(/,/g, '')) || 0;
            const d = parseFloat((p.func_deposit || '0').replace(/,/g, '')) || 0;
            totalAmt += t;
            totalDep += d;
            const st = (p.raw_status || '').toLowerCase();
            if (st === 'approved') {
                confirmedAmt += t;
            } else {
                pendingAmt += t;
            }
        });
        const pctConfirmed = totalAmt > 0 ? ((confirmedAmt / totalAmt) * 100).toFixed(1) : 0;
        const pctPending = totalAmt > 0 ? ((pendingAmt / totalAmt) * 100).toFixed(1) : 0;
        const outstanding = Math.max(0, confirmedAmt - totalDep);
        const fmt = (n) => n.toLocaleString('en-US', {minimumFractionDigits: 2});
        timetableBody.innerHTML += `
            <tr class="table-light fw-bold" style="border-top:2px solid #dee2e6;">
                <td colspan="17" class="text-end py-2 small">ยอดรวม</td>
                <td class="py-2 small">฿${fmt(totalAmt)}</td>
            </tr>
            <tr class="table-success">
                <td colspan="17" class="text-end py-2 small">ยอดยืนยันชำระ</td>
                <td class="py-2 small">฿${fmt(confirmedAmt)} (${pctConfirmed}%)</td>
            </tr>
            <tr class="table-warning">
                <td colspan="17" class="text-end py-2 small">ยอดรอยืนยัน</td>
                <td class="py-2 small">฿${fmt(pendingAmt)} (${pctPending}%)</td>
            </tr>
            <tr>
                <td colspan="17" class="text-end py-2 small">ยอดมัดจำ</td>
                <td class="py-2 small">฿${fmt(totalDep)}</td>
            </tr>
            <tr class="table-danger">
                <td colspan="17" class="text-end py-2 small">ยอดคงค้าง</td>
                <td class="py-2 small fw-bold">฿${fmt(outstanding)}</td>
            </tr>`;
    }
}

const fmtDate = (val) => {
    if (!val || val === '0000-00-00' || val === '0000-00-00 00:00:00') return '-';
    const d = new Date(val + 'T00:00:00');
    return isNaN(d) ? '-' : d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
};
const fmtDateTime = (val) => {
    if (!val || val === '0000-00-00 00:00:00' || val === '0000-00-00') return '-';
    const d = new Date(val.replace(' ', 'T'));
    return isNaN(d) ? '-' : d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
};

function renderRow(ev) {
    const timetableBody = document.querySelector('#dayTimetable tbody');
    const props = ev.extendedProps;
    const d = ev.startStr.split('T')[0];
    const dateLabel = d ? new Date(d + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

    const st = props.status.toLowerCase();
    let badgeClass = 'bg-secondary';
    if(st === 'pending') badgeClass = 'bg-warning text-dark';
    else if(st === 'confirmed' || st === 'approved') badgeClass = 'bg-info text-dark';
    else if(st === 'in progress') badgeClass = 'bg-primary';
    else if(st === 'completed') badgeClass = 'bg-success';
    else if(st === 'cancelled') badgeClass = 'bg-danger';

    const eid = ev.id;
    timetableBody.innerHTML += `
        <tr data-event-id="${eid}">
            <td class="small">${dateLabel}</td>
            <td class="small fw-bold text-primary">${props.doc_no || '-'}</td>
            <td><div class="fw-bold small" onclick='calendar.trigger("eventClick", {event: calendar.getEventById("${eid}")})' style="cursor:pointer;">${props.mainTitle || '-'}</div></td>
            <td class="small">${props.customer || '-'}</td>
            <td class="small">${props.phone || '-'}</td>
            <td class="small editable" data-field="lead_source" data-eid="${eid}">${props.lead_source || '-'}</td>
            <td class="small">${props.created_by_name || '-'}</td>
            <td class="fw-bold small">฿${props.total}</td>
            <td class="small editable" data-field="result" data-eid="${eid}">${props.result || '-'}</td>
            <td><span class="badge ${badgeClass}" style="font-size:0.7rem;">${props.status}</span></td>
            <td class="small">${fmtDateTime(props.created_at)}</td>
            <td class="small editable" data-field="inspection_date" data-eid="${eid}" data-raw="${(props.inspection_date && props.inspection_date !== '0000-00-00' && props.inspection_date !== '0000-00-00 00:00:00') ? props.inspection_date : ''}">${fmtDate(props.inspection_date)}</td>
            <td class="small editable" data-field="follow_up_date" data-eid="${eid}" data-raw="${(props.follow_up_date && props.follow_up_date !== '0000-00-00' && props.follow_up_date !== '0000-00-00 00:00:00') ? props.follow_up_date : ''}">${fmtDate(props.follow_up_date)}</td>
            <td class="small editable" data-field="confirmed_date" data-eid="${eid}" data-raw="${(props.confirmed_date && props.confirmed_date !== '0000-00-00' && props.confirmed_date !== '0000-00-00 00:00:00') ? props.confirmed_date : ''}">${fmtDate(props.confirmed_date)}</td>
        </tr>
    `;
}

function setTimetableHeader(type) {
    const thead = document.querySelector('#dayTimetable thead tr');
    if (type === 'qt') {
        thead.innerHTML = `
            <th width="9%">เลขที่ Quotation</th>
            <th width="9%">วันที่เสนอราคา</th>
            <th width="7%">Sales</th>
            <th>ชื่อลูกค้า/บริษัท</th>
            <th width="8%">ประเภทงาน</th>
            <th width="8%">วันที่จัดงาน</th>
            <th width="8%">ห้องจัดเลี้ยง</th>
            <th width="6%">จำนวนแขก</th>
            <th width="8%">มูลค่าเสนอ (บาท)</th>
            <th width="9%">สถานะใบเสนอราคา</th>
            <th width="7%">วันที่ Follow Up</th>
            <th width="7%">ผลการติดตาม</th>
            <th width="5%">เซ็นรับ</th>
            <th width="7%">วันที่รับใบเซ็น</th>
            <th width="6%">มัดจำ</th>
            <th width="5%">BEO</th>
            <th width="7%">สถานะ</th>
            <th width="7%">ยอดคงเหลือ</th>
        `;
    } else {
        thead.innerHTML = `
            <th width="7%">วันที่จัดงาน</th>
            <th width="7%">เลขที่</th>
            <th>กิจกรรม</th>
            <th width="9%">ลูกค้า</th>
            <th width="8%">เบอร์โทร</th>
            <th width="6%">ที่มา Lead</th>
            <th width="6%">ผู้รับผิดชอบ</th>
            <th width="7%">งบประมาณ</th>
            <th width="6%">ผลงาน</th>
            <th width="7%">สถานะ</th>
            <th width="6%">วันที่เสนอราคา</th>
            <th width="6%">วันที่ Inspection</th>
            <th width="6%">วันที่ Follow Up</th>
            <th width="6%">วันที่ Confirmed</th>
        `;
    }
}

function renderQtRow(ev) {
    const timetableBody = document.querySelector('#dayTimetable tbody');
    const props = ev.extendedProps;
    const eid = ev.id;
    const wf = props.workflow_status || '';
    const hasSigned = props.customer_signed ? '✓' : '-';
    const deposit = parseFloat((props.func_deposit || '0').replace(/,/g, '')) || 0;
    const total = parseFloat((props.total || '0').replace(/,/g, '')) || 0;
    const balance = Math.max(0, total - deposit);

    const st = props.status.toLowerCase();
    let badgeClass = 'bg-secondary';
    if (st === 'pending') badgeClass = 'bg-warning text-dark';
    else if (st === 'confirmed' || st === 'approved') badgeClass = 'bg-info text-dark';
    else if (st === 'in progress') badgeClass = 'bg-primary';
    else if (st === 'completed') badgeClass = 'bg-success';
    else if (st === 'cancelled') badgeClass = 'bg-danger';

    timetableBody.innerHTML += `
        <tr data-event-id="${eid}">
            <td class="small fw-bold text-primary">${props.quote_no || '-'}</td>
            <td class="small">${fmtDateTime(props.created_at)}</td>
            <td class="small">${props.created_by_name || '-'}</td>
            <td class="small">${props.customer || '-'}</td>
            <td class="small">${props.func_type_name || '-'}</td>
            <td class="small">${props.event_date || '-'}</td>
            <td class="small">${props.func_room_name || '-'}</td>
            <td class="small">${props.func_pax || '0'}</td>
            <td class="fw-bold small">฿${props.total}</td>
            <td class="small">${wf || 'Draft'}</td>
            <td class="small">${fmtDate(props.follow_up_date)}</td>
            <td class="small">${props.result || '-'}</td>
            <td class="small text-center">${hasSigned}</td>
            <td class="small">${fmtDate(props.confirmed_date)}</td>
            <td class="small">฿${props.func_deposit}</td>
            <td class="small text-center">${props.has_beo || '-'}</td>
            <td><span class="badge ${badgeClass}" style="font-size:0.7rem;">${props.status}</span></td>
            <td class="fw-bold small">฿${balance.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
        </tr>
    `;
}

function renderTimetableRows(events, tblColspan, isQtView) {
    events.sort((a, b) => (a.startStr + a.id).localeCompare(b.startStr + b.id));

    if (isQtView) {
        const tblBody = document.querySelector('#dayTimetable tbody');
        events.forEach(ev => renderQtRow(ev));
        return;
    }

    events.forEach(ev => renderRow(ev));
}

const allRooms = <?php echo json_encode($rooms_json); ?>;

function filterRooms() {
    const companyId = document.getElementById('companyFilter').value;
    const roomSelect = document.getElementById('roomFilter');

    roomSelect.innerHTML = '<option value="all">ทุกห้องประชุม</option>';
    allRooms.forEach(room => {
        if (companyId === 'all' || room.company_id == companyId) {
            let opt = document.createElement('option');
            opt.value = room.id;
            opt.text = room.room_name;
            roomSelect.add(opt);
        }
    });
    updateCalendarEvents();
}

function updateCalendarEvents() {
    if(!calendar) return;
    const companyId = document.getElementById('companyFilter').value;
    const roomId = document.getElementById('roomFilter').value;
    const source = document.getElementById('dataSource').value;
    const mode = document.getElementById('timeMode').value;
    const approveFilter = document.getElementById('approveFilter').value;

    calendar.getEvents().forEach(event => {
        const isQt = event.id.startsWith('qt_');
        const isRb = event.id.startsWith('rb_');
        let matchesSource = (source === 'all' || (source === 'eo' && !isQt && !isRb) || (source === 'quotation' && isQt) || (source === 'qt_approved' && isQt && !event.extendedProps.status.toLowerCase().includes('draft') && !event.extendedProps.status.toLowerCase().includes('freeze')) || (source === 'room' && isRb));
        let matchesMode = (event.extendedProps.mode === mode);
        let matchesRoom = (roomId === 'all' || event.extendedProps.room_id == roomId);

        let matchesApprove = true;
        if (approveFilter === 'approved') {
            if (isRb) matchesApprove = true;
            else if (isQt) matchesApprove = !event.extendedProps.status.toLowerCase().includes('draft');
            else matchesApprove = event.extendedProps.approved == 1;
        }

        if (matchesSource && matchesMode && matchesRoom && matchesApprove) {
            event.setProp('display', 'auto');
        } else {
            event.setProp('display', 'none');
        }
    });
    updateStats();
    if (lastTimetableDate) {
        updateDayTimetable(lastTimetableDate);
    } else if (calendar.view) {
        const vs = calendar.view.currentStart;
        const ve = calendar.view.currentEnd;
        updateDayTimetable(null, vs, ve);
    }
}

// ── Date picker for timetable ──
document.getElementById('timetableDatePicker').addEventListener('change', function() {
    if (this.value) {
        lastTimetableDate = this.value;
        document.getElementById('timetableMonthPicker').value = '';
        updateDayTimetable(this.value);
    } else {
        lastTimetableDate = null;
        refreshTimetable();
    }
});

// ── Inline editing for timetable ──
$(document).on('click', '#dayTimetable tbody td.editable', function() {
    const $td = $(this);
    if ($td.find('input, select').length) return;

    const field = $td.data('field');
    const eid = $td.data('eid');
    const current = $td.text().trim();

    if (field === 'inspection_date' || field === 'follow_up_date' || field === 'confirmed_date') {
        const raw = $td.data('raw') || '';
        $td.html(`<input type="date" class="form-control form-control-sm" value="${raw}" style="min-width:120px;">`);
        const $input = $td.find('input');
        $input.focus();
        $input.on('blur', function() { saveInline($td, eid, field, $input.val()); });
        $input.on('keydown', function(e) {
            if (e.key === 'Enter') { $input.blur(); }
            else if (e.key === 'Escape') { $td.html(current); }
        });
    } else if (field === 'lead_source') {
        const opts = ['','โทรเข้า','FB / Social','แนะนำ','Walk-in','อื่นๆ'];
        let html = '<select class="form-select form-select-sm" style="min-width:110px;">';
        opts.forEach(o => {
            const sel = o === current ? 'selected' : '';
            html += `<option value="${o}" ${sel}>${o || '—'}</option>`;
        });
        html += '</select>';
        $td.html(html);
        const $sel = $td.find('select');
        $sel.focus();
        $sel.on('change', function() { saveInline($td, eid, field, $sel.val()); });
        $sel.on('keydown', function(e) {
            if (e.key === 'Escape') { $td.html(current); }
        });
    } else if (field === 'result') {
        const opts = ['','ปิดงานสำเร็จ','ปิดงานไม่สำเร็จ','รอการตัดสินใจ','ติดต่อไม่ได้'];
        let html = '<select class="form-select form-select-sm" style="min-width:130px;">';
        opts.forEach(o => {
            const sel = o === current ? 'selected' : '';
            html += `<option value="${o}" ${sel}>${o || '—'}</option>`;
        });
        html += '</select>';
        $td.html(html);
        const $sel = $td.find('select');
        $sel.focus();
        $sel.on('change', function() { saveInline($td, eid, field, $sel.val()); });
        $sel.on('keydown', function(e) {
            if (e.key === 'Escape') { $td.html(current); }
        });
    }
});

function saveInline($td, eid, field, value) {
    const isQt = eid.startsWith('qt_');
    const refId = eid.replace(/^(qt_|sched_)/, '');
    const table = isQt ? 'quotations' : 'functions';

    if (field === 'confirmed_date' && !isQt) {
        // EO: map to approve_date
        field = 'approve_date';
    } else if (field === 'confirmed_date' && isQt) {
        field = 'approved_at';
    }
    // inspection_date, follow_up_date, lead_source, result are same column names in both tables

    $.ajax({
        url: 'api/inline_update.php',
        method: 'POST',
        data: { table: table, id: refId, field: field, value: value },
        success: function(res) {
            try { res = JSON.parse(res); } catch(e) {}
            if (res && res.success) {
                // Update the event's extendedProps
                const ev = calendar.getEventById(eid);
                if (ev) {
                    let displayVal = value || '-';
                    if (field === 'inspection_date' || field === 'follow_up_date' || field === 'approve_date' || field === 'approved_at') {
                        displayVal = fmtDate(value);
                    }
                    $td.html(displayVal);
                    // Update props
                    const propMap = {lead_source:'lead_source', result:'result', inspection_date:'inspection_date',
                                     follow_up_date:'follow_up_date', approve_date:'confirmed_date', approved_at:'confirmed_date'};
                    const prop = propMap[field] || field;
                    ev.setExtendedProp(prop, value);
                }
                if (field === 'approve_date' || field === 'approved_at') {
                    $td.html(fmtDate(value));
                }
            } else {
                const msg = res && res.message ? res.message : 'Save failed';
                $td.addClass('text-danger');
                setTimeout(() => $td.removeClass('text-danger'), 2000);
            }
        },
        error: function() {
            $td.addClass('text-danger');
            setTimeout(() => $td.removeClass('text-danger'), 2000);
        }
    });
}
</script>

<script>
const rbRooms = <?= json_encode($rooms_json) ?>;

function initRbCustomerSelect() {
    var $sel = $('#rb_customer');
    if (!$sel.length) return;
    if (!$.fn.select2) { setTimeout(initRbCustomerSelect, 200); return; }
    $sel.select2({
        width: '100%',
        placeholder: '-- ค้นหาลูกค้า หรือพิมพ์เองด้านล่าง --',
        allowClear: true,
        minimumInputLength: 0,
        dropdownParent: $('#roomBookingModal'),
        ajax: {
            url: 'api/search_customers.php?t=' + new Date().getTime(),
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) {
                return { results: (data.results || []).map(function(item) {
                    return { id: item.id, text: item.text, cust_name: item.cust_name, cust_phone: item.cust_phone };
                })};
            }
        }
    }).on('select2:select', function(e) {
        var data = e.params.data;
        document.getElementById('rb_customer_id').value = data.id || '';
        document.getElementById('rb_booking_name').value = data.cust_name || data.text || '';
        document.getElementById('rb_phone').value = data.cust_phone || '';
        document.getElementById('rb_organization').value = data.cust_name || data.text || '';
    }).on('select2:clear', function() {
        document.getElementById('rb_customer_id').value = '';
        document.getElementById('rb_booking_name').value = '';
        document.getElementById('rb_phone').value = '';
        document.getElementById('rb_organization').value = '';
    });
}
initRbCustomerSelect();

function openRoomBookingModal() {
    document.getElementById('roomBookingForm').reset();
    document.getElementById('rbConflictAlert').classList.add('d-none');
    document.getElementById('btnSubmitRb').disabled = false;
    document.getElementById('btnSubmitRb').innerHTML = '<i class="bi bi-check-lg me-1"></i> บันทึกการจอง';
    document.getElementById('rb_room').innerHTML = '<option value="">-- เลือกโรงแรมก่อน --</option>';
    if ($.fn.select2) { $('#rb_customer').val(null).trigger('change'); }
    new bootstrap.Modal(document.getElementById('roomBookingModal')).show();
}

document.getElementById('rb_company').addEventListener('change', function() {
    const cid = this.value;
    const sel = document.getElementById('rb_room');
    sel.innerHTML = '<option value="">-- เลือกห้อง --</option>';
    if (!cid) { sel.innerHTML = '<option value="">-- เลือกโรงแรมก่อน --</option>'; return; }
    rbRooms.filter(r => r.company_id == cid).forEach(r => {
        sel.innerHTML += `<option value="${r.id}">${r.room_name}</option>`;
    });
});

document.getElementById('rb_room').addEventListener('change', checkRbConflict);
['rb_start', 'rb_end'].forEach(id => {
    document.getElementById(id).addEventListener('change', checkRbConflict);
});

function checkRbConflict() {
    const roomId = document.getElementById('rb_room').value;
    const start = document.getElementById('rb_start').value;
    const end = document.getElementById('rb_end').value;
    if (!roomId || !start || !end) return;

    fetch('api/quick_book_room.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=check_conflict&room_id=${roomId}&start_time=${start.replace('T', ' ')}:00&end_time=${end.replace('T', ' ')}:00`
    })
    .then(res => res.json())
    .then(data => {
        const alertDiv = document.getElementById('rbConflictAlert');
        const msgSpan = document.getElementById('rbConflictMsg');
        if (data.status === 'conflict') {
            const names = data.events.map(e => `${e.event_name} (${e.booking_name || 'ไม่ระบุ'}) ${e.start_time} - ${e.end_time}`).join('<br>');
            msgSpan.innerHTML = 'ห้องนี้มีการจองแล้ว:<br>' + names;
            alertDiv.classList.remove('d-none');
            document.getElementById('btnSubmitRb').disabled = true;
        } else {
            alertDiv.classList.add('d-none');
            document.getElementById('btnSubmitRb').disabled = false;
        }
    });
}

document.getElementById('roomBookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitRb');
    if (btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> กำลังบันทึก...';

    const fd = new FormData(this);
    fd.set('start_time', document.getElementById('rb_start').value.replace('T', ' ') + ':00');
    fd.set('end_time', document.getElementById('rb_end').value.replace('T', ' ') + ':00');

    fetch('room_calendar.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'inserted') {
                bootstrap.Modal.getInstance(document.getElementById('roomBookingModal')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'จองสำเร็จ!',
                    html: `เลขที่: <b>${data.booking_code}</b><br>กำลังโหลดข้อมูลใหม่...`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถบันทึกได้', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> บันทึกการจอง';
            }
        })
        .catch(err => {
            Swal.fire('เกิดข้อผิดพลาด', err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> บันทึกการจอง';
        });
});

function cancelRoomBooking(bookingId) {
    Swal.fire({
        title: 'ยืนยันยกเลิกการจอง?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'ยกเลิกการจอง',
        cancelButtonText: 'กลับ'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', bookingId);
            fetch('room_calendar.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('eventDetailModal')).hide();
                        const ev = calendar.getEventById('rb_' + bookingId);
                        if (ev) ev.remove();
                        Swal.fire('สำเร็จ', 'ยกเลิกการจองแล้ว', 'success');
                    } else {
                        Swal.fire('ผิดพลาด', data.message || 'ไม่สามารถยกเลิกได้', 'error');
                    }
                });
        }
    });
}
function exportExcel() {
    const tbl = document.getElementById('dayTimetable');
    if (!tbl) return;
    const typeFilter = document.getElementById('timetableType')?.value || 'eo';
    const label = typeFilter === 'qt' ? 'Quotation' : (typeFilter === 'eo' ? 'EO' : 'All');
    const dateText = document.getElementById('selectedDateText')?.innerText || '';
    let html = '<html><head><meta charset="utf-8"><title>Export</title></head><body>';
    html += '<h3>ตารางเวลา: ' + dateText + ' (' + label + ')</h3>';
    html += '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:12px;">';
    html += '<thead><tr style="background:#212529;color:#fff;">';
    const headerTr = tbl.querySelector('thead tr');
    if (headerTr) {
        headerTr.querySelectorAll('th').forEach(th => {
            const label = th.querySelector('div')?.textContent?.trim() || th.innerText.replace(/กรอง/g,'').trim() || '';
            html += '<th style="background:#212529;color:#fff;padding:6px 8px;">' + label + '</th>';
        });
    }
    html += '</tr></thead>';
    html += '</thead><tbody>';
    const rows = tbl.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.classList.contains('table-info') || row.classList.contains('table-secondary')) return;
        html += '<tr>' + row.innerHTML + '</tr>';
    });
    html += '</tbody></table>';
    html += '</body></html>';
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'timetable_' + new Date().toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<style>
    /* ===== ตัวกรอง: label เล็กเหนือ select ===== */
    .cal-filter-label {
        font-size: .68rem; text-transform: uppercase; letter-spacing: .5px;
        color: #8a9099; font-weight: 700; margin-bottom: 3px; display: block;
    }

    /* ===== คำอธิบายสี (legend) ===== */
    .cal-legend {
        display: flex; flex-wrap: wrap; gap: 4px 14px;
        padding: 8px 2px 10px; margin-top: 4px;
        border-top: 1px dashed #eef0f2; font-size: .72rem; color: #6c757d;
    }
    .cal-legend-item { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
    .cal-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .cal-legend-type { padding-top: 6px; padding-bottom: 8px; border-top: 1px dashed #eef0f2; margin-top: 0; }
    .cal-legend-label { font-weight: 700; color: #5b6470; }
    .cal-swatch {
        display: inline-block; width: 20px; height: 12px; border-radius: 3px; flex-shrink: 0;
        border-left: 3px solid #8a9099;
    }

    /* ===== ตัวปฏิทิน (FullCalendar) ===== */
    #calendar { font-size: 0.85rem; background: white; border-radius: 10px; padding: 10px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; color: #333; }
    .fc .fc-daygrid-day-frame { min-height: 92px; }
    .fc .fc-daygrid-day-top { padding: 3px 4px 0; }
    .fc .fc-daygrid-day-number { font-size: 0.85rem; font-weight: 600; padding: 2px 4px; }
    .fc .fc-col-header-cell-cushion { font-weight: 600; padding: 6px 4px; color: #5b6470; }
    .fc .fc-day-today { background: rgba(184, 148, 65, 0.07) !important; }
    .fc .fc-day-today .fc-daygrid-day-number {
        background: var(--hotel-gold, #b89441); color: #fff; border-radius: 50%;
        width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; padding: 0;
    }
    .fc .fc-day-sat .fc-daygrid-day-number, .fc .fc-day-sun .fc-daygrid-day-number { color: #c0392b; }
    .fc-daygrid-day.fc-day-sat, .fc-daygrid-day.fc-day-sun { background: rgba(0,0,0,0.012); }

    /* harness = กล่องที่ครองพื้นที่ของ event แต่ละอัน ต้องกันการล้นตรงนี้เป็นด่านแรก
       ห้ามใส่ max-width ตรงนี้ — event หลายวัน (เช่น 20-22 ส.ค.) ใช้ left/right ลบเพื่อยืดแท่งข้ามหลายวัน
       max-width:100% จะไปหักความกว้างนั้นทิ้ง ทำให้แท่งงานหลายวันถูกตัดเหลือแค่ 1 ช่องวัน */
    .fc-daygrid-event-harness { overflow: hidden; }
    .fc-event {
        cursor: pointer; border: none !important; overflow: hidden;
        border-radius: 4px; padding: 0; margin-bottom: 2px !important; background: transparent !important;
    }
    .fc-daygrid-event { padding: 0; }
    .fc-timegrid-event { padding: 2px 4px !important; }

    /* กล่อง event แบบใหม่: แถบสีซ้าย(=สถานะ) + พื้นหลัง(=ประเภท) อ่านง่ายกว่าจุดเล็กๆ เดิม และตัดคำด้วย ellipsis เสมอ */
    .cal-chip {
        display: flex; align-items: center; gap: 5px; width: 100%; min-width: 0; box-sizing: border-box;
        padding: 2px 6px 2px 7px; border-left: 3px solid #0dcaf0; border-radius: 3px;
        line-height: 1.5; overflow: hidden; white-space: nowrap;
    }
    .cal-chip-icon { flex-shrink: 0; font-size: .62rem; opacity: .8; }
    .cal-chip-time { flex-shrink: 0; font-weight: 700; font-size: .68rem; }
    .cal-chip-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; font-size: .72rem; color: #2b2f36; }

    /* 3 ประเภท แยกด้วย "ความเข้ม" ของพื้นหลัง ไม่ใช่สี (สีเก็บไว้บอกสถานะอย่างเดียว ไม่ให้ชนกัน) */
    .cal-chip-eo { background: rgba(17, 19, 24, 0.045); }                    /* งานจัดเลี้ยง (EO) — จางสุด งานทั่วไป */
    .cal-chip-eo:hover { background: rgba(17, 19, 24, 0.09); }
    .cal-chip-qt { background: rgba(17, 19, 24, 0.11); font-style: italic; } /* ใบเสนอราคา — กลาง เอียงเล็กน้อยบอกว่ายังไม่ใช่งานจริง */
    .cal-chip-qt:hover { background: rgba(17, 19, 24, 0.17); }
    .cal-chip-qt .cal-chip-title { font-style: italic; }
    .cal-chip-rb { background: rgba(111, 66, 193, 0.16); border-left-width: 5px; font-weight: 600; } /* จองห้อง — เข้มสุด+ขอบหนา เพราะล็อกห้องจริง */
    .cal-chip-rb:hover { background: rgba(111, 66, 193, 0.26); }

    /* "+N งาน" ให้ดูเป็นปุ่มเล็กๆ กดได้ชัดเจน แทนตัวหนังสือเปลือย */
    .fc .fc-daygrid-more-link {
        font-size: .68rem; font-weight: 700; color: #8a6c22; background: #f7f1e3;
        border-radius: 3px; padding: 1px 6px; margin-top: 1px; display: inline-block;
    }
    .fc .fc-daygrid-more-link:hover { background: #efe3c4; }
    .fc .fc-more-popover { border-radius: 10px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
    .fc .fc-popover-header { background: #16181d; color: #fff; padding: 8px 10px; }

    .text-gold { color: #d4af37; }
    .bg-purple { background-color: #6f42c1 !important; }
    .bg-dark { background-color: #1a1a1a !important; }
    .list-group-item { border-bottom: 1px dashed #eee; padding-top: 12px; padding-bottom: 12px; }
    .badge { font-weight: 500; padding: 0.5em 0.8em; }
    #dayTimetable tbody tr:not(.table-secondary):hover { background: rgba(13, 110, 253, 0.04); }
    #dayTimetable tbody tr.table-secondary { background: rgba(0,0,0,0.03) !important; }
    #dayTimetable tbody tr.table-secondary:hover { background: rgba(0,0,0,0.06) !important; }
    .card { border-radius: 10px; }

    @media (max-width: 768px) {
        #statsRow .card-body { padding: 0.5rem; }
        #statsRow .card-body i { font-size: 1rem !important; }
        #statsRow .card-body div div { font-size: 0.75rem; }
        .fc .fc-daygrid-day-frame { min-height: 68px; }
        .cal-legend { gap: 4px 10px; font-size: .68rem; }
    }
</style>

<?php include "footer.php"; ?>
