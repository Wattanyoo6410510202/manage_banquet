<?php include "config.php";
include "header.php";

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$rooms = $conn->query("SELECT id, room_name, company_id FROM meeting_rooms WHERE status = 'active' ORDER BY room_name ASC");
$rooms_json = [];
while($r = $rooms->fetch_assoc()) { $rooms_json[] = $r; }
?>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://code.jquery.com/jquery-3.7.0.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="container-fluid p-0">

    <!-- Stats Summary Row -->
    <div class="row g-2 mb-3">
        <div class="col-4 col-md">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-check text-primary fs-4"></i>
                    <div>
                        <div class="small text-muted">วันนี้</div>
                        <div class="fw-bold" id="statToday">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4 col-md">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-building text-info fs-4"></i>
                    <div>
                        <div class="small text-muted">ทั้งเดือน</div>
                        <div class="fw-bold" id="statMonth">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4 col-md">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-people text-warning fs-4"></i>
                    <div>
                        <div class="small text-muted">PAX รวม</div>
                        <div class="fw-bold" id="statPax">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 text-success fs-4"></i>
                    <div>
                        <div class="small text-muted">ยอดรวมเดือน</div>
                        <div class="fw-bold" id="statRevenue">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-secondary fs-4"></i>
                    <div>
                        <div class="small text-muted">วันที่เลือก</div>
                        <div class="fw-bold" id="statDayEvents">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Conflict Card -->
    <?php
    $conflict_sql = "SELECT 
        f1.id AS id1, f1.function_name AS name1, f1.start_time AS start1, f1.end_time AS end1,
        f2.id AS id2, f2.function_name AS name2, f2.start_time AS start2, f2.end_time AS end2,
        f1.room_id, mr.room_name,
        f1.approve AS approve1, f2.approve AS approve2,
        f1.status AS status1, f2.status AS status2
    FROM functions f1
    JOIN functions f2 ON f1.id < f2.id 
        AND f1.room_id = f2.room_id
        AND f1.start_time < f2.end_time
        AND f1.end_time > f2.start_time
    JOIN meeting_rooms mr ON f1.room_id = mr.id
    WHERE (f1.approve = 1 OR f2.approve = 1)
        AND f1.status NOT IN ('Cancelled')
        AND f2.status NOT IN ('Cancelled')
    ORDER BY f1.start_time ASC";
    $conflict_q = mysqli_query($conn, $conflict_sql);
    $conflicts = [];
    if ($conflict_q) {
        while ($cr = mysqli_fetch_assoc($conflict_q)) {
            $conflicts[] = $cr;
        }
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

    <!-- Top row: Calendar + Detail Panel -->
    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-3">
                            <select id="companyFilter" class="form-select form-select-sm" onchange="filterRooms()">
                                <option value="all">ทุกโรงแรม</option>
                                <?php while ($c = $companies->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="roomFilter" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="all">ทุกห้องประชุม</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="dataSource" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="all" selected>ทั้งหมด (All)</option>
                                <option value="eo">Function Order (EO)</option>
                                <option value="quotation">ใบเสนอราคา (Quotation)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="timeMode" class="form-select form-select-sm" onchange="updateCalendarEvents()">
                                <option value="general" selected>เวลาจองหลัก</option>
                                <option value="schedule">กำหนดการ</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id='calendar'></div>
                    <div class="d-flex flex-wrap gap-2 mt-2 px-1">
                        <span class="badge" style="background:#ffc107;">รออนุมัติ</span>
                        <span class="badge" style="background:#0dcaf0; color:#000;">อนุมัติแล้ว</span>
                        <span class="badge" style="background:#0d6efd;">ดำเนินการ</span>
                        <span class="badge" style="background:#198754;">จบงานแล้ว</span>
                        <span class="badge" style="background:#dc3545;">ยกเลิก</span>
                        <span class="badge" style="background:#6c757d;">อื่น ๆ</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm border-0 h-100 sticky-top" style="top:20px;z-index:100;">
                <div class="card-header bg-dark text-gold py-3">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> รายละเอียดกิจกรรม</h5>
                </div>
                <div id="detailPanel" class="card-body">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar2-week d-block mb-3" style="font-size:3rem;"></i>
                        <p>คลิกเลือกงานจากปฏิทิน<br>เพื่อดูรายละเอียดที่นี่ครับ</p>
                    </div>
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
                    <div class="d-flex align-items-center gap-2">
                        <input type="date" id="timetableDatePicker" class="form-control form-control-sm" style="width:auto;">
                        <span id="eventCountBadge" class="badge bg-primary d-none">0 งาน</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="dayTimetable">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th width="9%">วันที่จัดงาน</th>
                                    <th>กิจกรรม</th>
                                    <th width="10%">ลูกค้า</th>
                                    <th width="9%">เบอร์โทร</th>
                                    <th width="7%">ที่มา Lead</th>
                                    <th width="7%">ผู้รับผิดชอบ</th>
                                    <th width="8%">งบประมาณ</th>
                                    <th width="7%">ผลงาน</th>
                                    <th width="8%">สถานะ</th>
                                    <th width="7%">วันที่เสนอราคา</th>
                                    <th width="7%">วันที่ Inspection</th>
                                    <th width="7%">วันที่ Follow Up</th>
                                    <th width="7%">วันที่ Confirmed</th>
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

<script>
let calendar;
let lastTimetableDate = null;

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var detailPanel = document.getElementById('detailPanel');

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
            updateDayTimetable(date);
        },
        dateClick: function(info) {
            lastTimetableDate = info.date;
            updateDayTimetable(info.date);
        },
        datesSet: function(info) {
            lastTimetableDate = null;
            document.getElementById('timetableDatePicker').value = '';
            updateStats();
            updateDayTimetable(null, info.start, info.end);
        },
        eventContent: function(arg) {
            const props = arg.event.extendedProps;
            const hasTime = arg.event.startStr && arg.event.startStr.includes('T');
            const timeStr = hasTime ? arg.event.startStr.split('T')[1].substring(0, 5) : '';
            const st = (props.status || '').toLowerCase();
            let dotColor = '#0dcaf0';
            if(st === 'pending') dotColor = '#ffc107';
            else if(st === 'in progress' || st === 'อนุมัติแล้ว') dotColor = '#0d6efd';
            else if(st === 'completed' || st === 'จบงานแล้ว') dotColor = '#198754';
            else if(st === 'cancelled' || st === 'ยกเลิก') dotColor = '#dc3545';

            return {
                html: `<div style="display:flex;align-items:center;gap:3px;font-size:0.75rem;line-height:1.3;">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:${dotColor};flex-shrink:0;"></span>
                    ${timeStr ? `<span style="font-weight:600;flex-shrink:0;font-size:0.7rem;">${timeStr}</span>` : ''}
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${arg.event.title || ''}</span>
                </div>`
            };
        },
        events: [
            <?php
            // 1. งานจากตาราง functions (General Mode)
            // พยายามดึงแบบ Join ก่อน ถ้าล้มเหลว (เช่น ไม่มีคอลัมน์ใหม่) ให้ใช้ Fallback
            $sql_f = "SELECT f.*, r.room_name, c.cust_name, c.cust_phone, u.name as creator_name
                      FROM functions f 
                      LEFT JOIN meeting_rooms r ON f.room_id = r.id
                      LEFT JOIN customers c ON f.customer_id = c.id
                      LEFT JOIN users u ON f.created_by_id = u.id
                      ORDER BY f.id ASC"; 
            
            $q_f = mysqli_query($conn, $sql_f);
            if (!$q_f) {
                $sql_f = "SELECT * FROM functions ORDER BY id ASC";
                $q_f = mysqli_query($conn, $sql_f);
            }

            $func_groups = [];
            if ($q_f) {
                while ($row = mysqli_fetch_assoc($q_f)) {
                    $gid = (isset($row['project_id']) && $row['project_id']) ? $row['project_id'] : 'single_' . $row['id'];
                    if (!isset($func_groups[$gid])) {
                        $func_groups[$gid] = $row;
                    } elseif (isset($row['is_approved']) && $row['is_approved'] == 1) {
                        $func_groups[$gid] = $row;
                    }
                }
            }
            
            foreach ($func_groups as $row) {
                $st = strtolower(trim($row['status'] ?? ''));
                if($st === 'pending') $color = '#ffc107';
                elseif($st === 'confirmed' || $st === 'approved') $color = '#0dcaf0';
                elseif($st === 'in progress') $color = '#0d6efd';
                elseif($st === 'completed') $color = '#198754';
                elseif($st === 'cancelled') $color = '#dc3545';
                else $color = '#6c757d';
            ?>
            {
                id: 'gen_<?php echo $row['id']; ?>',
                ref_id: '<?php echo $row['id']; ?>',
                title: '<?php echo addslashes($row['function_name'] ?? ''); ?>',
                start: '<?php echo $row['start_time'] ?? ''; ?>',
                end: '<?php echo $row['end_time'] ?? ''; ?>',
                color: '<?php echo $color; ?>',
                mode: 'general',
                extendedProps: { 
                    mainTitle: '<?php echo addslashes($row['function_name'] ?? ''); ?>', 
                    status: '<?php echo $row['status'] ?? 'Pending'; ?>', 
                    room: '<?php echo addslashes($row['room_name'] ?? $row['room_id'] ?? ''); ?>',
                    customer: '<?php echo addslashes($row['cust_name'] ?? ''); ?>',
                    phone: '<?php echo addslashes($row['cust_phone'] ?? ''); ?>',
                    pax: '<?php echo $row['pax'] ?? 0; ?>',
                    deposit: '<?php echo number_format($row['deposit'] ?? 0, 2); ?>',
                    total: '<?php echo number_format($row['total_amount'] ?? 0, 2); ?>',
                    remark: '<?php echo addslashes($row['remark'] ?? ''); ?>',
                    created_by_name: '<?php echo addslashes($row['creator_name'] ?? $row['created_by'] ?? ''); ?>'
                }
            },
            <?php } 
            
            // 2. งานจากตาราง schedules (Schedule Mode)
            $sql_s = "SELECT s.*, f.function_name, f.status, r.room_name, c.cust_name, c.cust_phone, f.pax, f.deposit, f.total_amount,
                             f.lead_source, f.result, f.inspection_date, f.follow_up_date, f.created_at, f.approve_date, u.name as creator_name, f.created_by
                      FROM function_schedules s 
                      JOIN functions f ON s.function_id = f.id
                      LEFT JOIN meeting_rooms r ON f.room_id = r.id
                      LEFT JOIN customers c ON f.customer_id = c.id
                      LEFT JOIN users u ON f.created_by_id = u.id";
            $q_s = mysqli_query($conn, $sql_s);
            if (!$q_s) {
                $sql_s = "SELECT s.*, f.function_name, f.status, f.created_by 
                          FROM function_schedules s 
                          JOIN functions f ON s.function_id = f.id";
                $q_s = mysqli_query($conn, $sql_s);
            }

            if ($q_s) {
                while ($row = mysqli_fetch_assoc($q_s)) {
                    $st = strtolower(trim($row['status'] ?? ''));
                    if($st === 'pending') $color = '#ffc107';
                    elseif($st === 'confirmed' || $st === 'approved') $color = '#0dcaf0';
                    elseif($st === 'in progress') $color = '#0d6efd';
                    elseif($st === 'completed') $color = '#198754';
                    elseif($st === 'cancelled') $color = '#dc3545';
                    else $color = '#6c757d';
                ?>
                {
                    id: 'sched_<?php echo $row['id']; ?>',
                    ref_id: '<?php echo $row['function_id']; ?>',
                    title: '<?php echo addslashes("[" . ($row['schedule_hour'] ?? '') . "] " . ($row['schedule_function'] ?? '')); ?>',
                    start: '<?php echo $row['schedule_date'] ?? ''; ?>',
                    color: '<?php echo $color; ?>',
                    mode: 'schedule',
                    extendedProps: { 
                        mainTitle: '<?php echo addslashes($row['function_name'] ?? ''); ?>', 
                        status: '<?php echo $row['status'] ?? 'Pending'; ?>', 
                        room: '<?php echo addslashes($row['room_name'] ?? ''); ?>',
                        customer: '<?php echo addslashes($row['cust_name'] ?? ''); ?>',
                        total: '<?php echo number_format($row['total_amount'] ?? 0, 2); ?>',
                        remark: '<?php echo addslashes($row['schedule_function'] ?? ''); ?>',
                        created_by_name: '<?php echo addslashes($row['creator_name'] ?? $row['created_by'] ?? ''); ?>'
                    }
                },
                <?php } 
            } ?>

            <?php
            // 3. ใบเสนอราคา (Quotations)
            $sql_q = "SELECT q.*, c.cust_name, c.cust_phone, u.name as creator_name
                      FROM quotations q 
                      LEFT JOIN customers c ON q.customer_id = c.id
                      LEFT JOIN users u ON q.created_by = u.id
                      WHERE q.status NOT IN ('Cancelled')
                      ORDER BY q.id DESC";
            $q_q = mysqli_query($conn, $sql_q);
            if (!$q_q) {
                $sql_q = "SELECT * FROM quotations WHERE status NOT IN ('Cancelled') ORDER BY id DESC";
                $q_q = mysqli_query($conn, $sql_q);
            }

            if ($q_q) {
                while ($row = mysqli_fetch_assoc($q_q)) {
                    $st = strtolower(trim($row['status'] ?? ''));
                    if($st === 'draft' || $st === 'pending') {
                        $color = '#6c757d';
                        $status_text = 'QT (Draft)';
                    } else {
                        $color = '#fd7e14';
                        $status_text = 'QT (อนุมัติ)';
                    }
                ?>
                {
                    id: 'qt_<?php echo $row['id']; ?>',
                    ref_id: '<?php echo $row['id']; ?>',
                    title: '<?php echo addslashes("[" . ($row['quote_no'] ?? '') . "] " . ($row['event_name'] ?? '')); ?>',
                    start: '<?php echo $row['event_date'] ?? ''; ?>',
                    color: '<?php echo $color; ?>',
                    mode: 'general',
                    extendedProps: { 
                        mainTitle: '<?php echo addslashes($row['event_name'] ?? ''); ?>', 
                        status: '<?php echo $status_text; ?>', 
                        customer: '<?php echo addslashes($row['cust_name'] ?? ''); ?>',
                        total: '<?php echo number_format($row['grand_total'] ?? 0, 2); ?>',
                        created_by_name: '<?php echo addslashes($row['creator_name'] ?? ''); ?>'
                    }
                },
                <?php } 
            } ?>
        ],

        eventClick: function (info) {
            const props = info.event.extendedProps;
            const eventId = info.event.extendedProps.ref_id;
            const isQt = info.event.id.startsWith('qt_');
            const st = props.status.toLowerCase();

            let badgeClass = 'bg-secondary';
            if(isQt) {
                if(st.includes('draft')) badgeClass = 'bg-secondary';
                else badgeClass = 'bg-dark text-light';
            } else {
                if(st === 'pending') badgeClass = 'bg-warning text-dark';
                else if(st === 'confirmed' || st === 'approved') badgeClass = 'bg-info text-dark';
                else if(st === 'in progress') badgeClass = 'bg-primary';
                else if(st === 'completed') badgeClass = 'bg-success';
                else if(st === 'cancelled') badgeClass = 'bg-danger';
            }

            const editUrl = isQt ? 'edit_quotation.php?id=' : 'edit.php?id=';
            const viewUrl = isQt ? 'quotation_view.php?id=' : 'view.php?id=';

            detailPanel.innerHTML = `
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
                    <a href="${editUrl}${eventId}" class="btn btn-dark fw-bold text-gold">
                        <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูล${isQt ? 'ใบเสนอราคา' : 'งาน'}
                    </a>
                    <a href="${viewUrl}${eventId}" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-2"></i> พิมพ์เอกสาร (PDF)
                    </a>
                </div>
            </div>
            `;
        }
    });
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

    document.getElementById('statToday').textContent = todayEvents.length + ' งาน';
    document.getElementById('statMonth').textContent = monthEvents.length + ' งาน';
    document.getElementById('statPax').textContent = monthEvents.reduce((sum, ev) => {
        const p = parseInt(ev.extendedProps.pax) || 0;
        return sum + p;
    }, 0) + ' คน';
    const totalRev = monthEvents.reduce((sum, ev) => {
        const t = parseFloat((ev.extendedProps.total || '0').replace(/,/g, '')) || 0;
        return sum + t;
    }, 0);
    document.getElementById('statRevenue').textContent = '฿' + totalRev.toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function updateDayTimetable(date, viewStart, viewEnd) {
    const dateText = document.getElementById('selectedDateText');
    const timetableBody = document.querySelector('#dayTimetable tbody');
    const eventCountBadge = document.getElementById('eventCountBadge');
    const datePicker = document.getElementById('timetableDatePicker');
    timetableBody.innerHTML = '';

    if (date !== null && date !== undefined) {
        // แสดงเฉพาะวันเดียว
        const d = new Date(date);
        const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        dateText.innerText = d.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
        if (datePicker) datePicker.value = dateStr;

        const events = calendar.getEvents().filter(ev => {
            const evStart = ev.startStr.split('T')[0];
            const isVisible = ev.display !== 'none';
            const isDraft = ev.id.startsWith('qt_') && ev.extendedProps.status.toLowerCase().includes('draft');
            return evStart === dateStr && isVisible && !isDraft;
        });

        if (events.length === 0) {
            timetableBody.innerHTML = '<tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในวันที่เลือก</td></tr>';
            eventCountBadge.classList.add('d-none');
            document.getElementById('statDayEvents').textContent = '0 งาน';
            return;
        }

        eventCountBadge.classList.remove('d-none');
        eventCountBadge.textContent = events.length + ' งาน';
        document.getElementById('statDayEvents').textContent = events.length + ' งาน';
        renderTimetableRows(events);
        return;
    }

    // แสดงทั้งหมดในช่วงวันที่กำหนด
    if (!viewStart || !viewEnd) return;
    const startStr = viewStart.toISOString().split('T')[0];
    const endStr = viewEnd.toISOString().split('T')[0];

    const allEvents = calendar.getEvents().filter(ev => {
        const evStart = ev.startStr.split('T')[0];
        const isVisible = ev.display !== 'none';
        const isDraft = ev.id.startsWith('qt_') && ev.extendedProps.status.toLowerCase().includes('draft');
        return evStart >= startStr && evStart < endStr && isVisible && !isDraft;
    }).sort((a, b) => (a.startStr + a.id).localeCompare(b.startStr + b.id));

    const totalCount = allEvents.length;
    const startLabel = new Date(startStr + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    const endLabel = new Date(endStr + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    dateText.innerText = startLabel + ' — ' + endLabel;

    if (totalCount === 0) {
        timetableBody.innerHTML = '<tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-calendar-x d-block mb-2 fs-1"></i>ไม่มีกิจกรรมในช่วงนี้</td></tr>';
        eventCountBadge.classList.add('d-none');
        document.getElementById('statDayEvents').textContent = '0 งาน';
        return;
    }

    eventCountBadge.classList.remove('d-none');
    eventCountBadge.textContent = totalCount + ' งาน';
    document.getElementById('statDayEvents').innerHTML = totalCount + ' งาน <small class="text-muted fw-normal">(' + startLabel + ' - ' + endLabel + ')</small>';

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

        timetableBody.innerHTML += `
            <tr class="table-info" style="cursor:default;">
                <td colspan="13" class="py-1">
                    <span class="fw-bold small"><i class="bi bi-calendar me-1"></i>${dateLabel}</span>
                    <span class="badge bg-info text-dark ms-2">${dateEvents.length} งาน</span>
                </td>
            </tr>`;

        dateEvents.forEach(ev => renderRow(ev));
    });
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

function renderTimetableRows(events) {
    events.sort((a, b) => (a.startStr + a.id).localeCompare(b.startStr + b.id));

    const groupedByRoom = {};
    events.forEach(ev => {
        const room = ev.extendedProps.room || 'ไม่ได้ระบุห้อง';
        if (!groupedByRoom[room]) groupedByRoom[room] = [];
        groupedByRoom[room].push(ev);
    });

    Object.keys(groupedByRoom).sort().forEach(room => {
        const tblBody = document.querySelector('#dayTimetable tbody');
        tblBody.innerHTML += `
            <tr class="table-secondary" style="cursor:default;">
                <td colspan="13" class="py-1">
                    <span class="fw-bold small"><i class="bi bi-building me-1"></i>${room}</span>
                    <span class="badge bg-secondary ms-2">${groupedByRoom[room].length} งาน</span>
                </td>
            </tr>`;
        groupedByRoom[room].forEach(ev => renderRow(ev));
    });
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

    calendar.getEvents().forEach(event => {
        const isQt = event.id.startsWith('qt_');
        let matchesSource = (source === 'all' || (source === 'eo' && !isQt) || (source === 'quotation' && isQt));
        let matchesMode = (event.extendedProps.mode === mode);
        let matchesRoom = (roomId === 'all' || event.extendedProps.room_id == roomId);

        if (matchesSource && matchesMode && matchesRoom) {
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
        updateDayTimetable(this.value);
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

<style>
    #calendar { font-size: 0.85rem; background: white; border-radius: 10px; padding: 10px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; color: #333; }
    .fc-event { cursor: pointer; border: none !important; border-radius: 4px; padding: 1px 3px; font-size: 0.75rem; margin-bottom: 1px !important; }
    .fc-daygrid-event { padding: 1px 3px; }
    .fc-timegrid-event { padding: 2px 4px !important; }
    .fc .fc-day-today { background: rgba(13, 110, 253, 0.05) !important; }
    .fc .fc-daygrid-day-number { font-size: 0.85rem; font-weight: 500; padding: 4px 6px; }
    .fc .fc-col-header-cell-cushion { font-weight: 600; padding: 6px 4px; }
    .fc .fc-more-link { font-size: 0.7rem; }
    .text-gold { color: #d4af37; }
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
    }
</style>

<?php include "footer.php"; ?>
