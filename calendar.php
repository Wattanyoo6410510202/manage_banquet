<?php include "config.php";
include "header.php"; ?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                        <div class="d-flex gap-3 small flex-wrap">
                            <span class="d-flex align-items-center"><i class="bi bi-circle-fill me-1" style="color: #ffc107;"></i> รออนุมัติ</span>
                            <span class="d-flex align-items-center"><i class="bi bi-circle-fill me-1" style="color: #0dcaf0;"></i> อนุมัติ</span>
                            <span class="d-flex align-items-center"><i class="bi bi-circle-fill me-1" style="color: #0d6efd;"></i> ดำเนินการ</span>
                            <span class="d-flex align-items-center"><i class="bi bi-circle-fill me-1" style="color: #198754;"></i> จบงาน</span>
                            <span class="d-flex align-items-center"><i class="bi bi-circle-fill me-1" style="color: #dc3545;"></i> ยกเลิก</span>
                        </div>
                        <select id="timeMode" class="form-select form-select-sm" style="width: auto;" onchange="updateCalendarView()">
                            <option value="general" selected>อิงตามเวลาจองหลัก (Start/End)</option>
                            <option value="schedule">อิงตามกำหนดการ (Schedule)</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 sticky-top" style="top: 20px; z-index: 100;">
                <div class="card-header bg-dark text-gold py-3">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> รายละเอียดกิจกรรม</h5>
                </div>
                <div id="detailPanel" class="card-body">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar2-week d-block mb-3" style="font-size: 3rem;"></i>
                        <p>คลิกเลือกงานจากปฏิทิน<br>เพื่อดูรายละเอียดที่นี่ครับ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let calendar; // ประกาศตัวแปร global
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
                right: 'dayGridMonth,listMonth'
            },
            dayMaxEvents: 3,
            events: [
                <?php
                // 1. งานจากตาราง functions (General Mode)
                $sql_f = "SELECT f.*, r.room_name, c.cust_name, c.cust_phone 
                          FROM functions f 
                          LEFT JOIN meeting_rooms r ON f.room_id = r.id
                          LEFT JOIN customers c ON f.customer_id = c.id";
                $q_f = mysqli_query($conn, $sql_f);
                while ($row = mysqli_fetch_assoc($q_f)) {
                    $st = strtolower(trim($row['status']));
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
                    title: '<?php echo addslashes($row['function_name']); ?>',
                    start: '<?php echo $row['start_time']; ?>',
                    end: '<?php echo $row['end_time']; ?>',
                    color: '<?php echo $color; ?>',
                    display: 'none',
                    mode: 'general',
                    extendedProps: { 
                        mainTitle: '<?php echo addslashes($row['function_name']); ?>', 
                        status: '<?php echo $row['status']; ?>', 
                        room: '<?php echo addslashes($row['room_name'] ?? ''); ?>',
                        customer: '<?php echo addslashes($row['cust_name'] ?? ''); ?>',
                        phone: '<?php echo addslashes($row['cust_phone'] ?? ''); ?>',
                        pax: '<?php echo $row['pax']; ?>',
                        deposit: '<?php echo number_format($row['deposit'], 2); ?>',
                        total: '<?php echo number_format($row['total_amount'], 2); ?>',
                        remark: '<?php echo addslashes($row['remark']); ?>'
                    }
                },
                <?php } 
                
                // 2. งานจากตาราง schedules (Schedule Mode)
                $sql_s = "SELECT s.*, f.function_name, f.status, r.room_name, c.cust_name, c.cust_phone, f.pax, f.deposit, f.total_amount 
                          FROM function_schedules s 
                          JOIN functions f ON s.function_id = f.id
                          LEFT JOIN meeting_rooms r ON f.room_id = r.id
                          LEFT JOIN customers c ON f.customer_id = c.id";
                $q_s = mysqli_query($conn, $sql_s);
                while ($row = mysqli_fetch_assoc($q_s)) {
                    $st = strtolower(trim($row['status']));
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
                    title: '<?php echo addslashes("[" . $row['schedule_hour'] . "] " . $row['schedule_function']); ?>',
                    start: '<?php echo $row['schedule_date']; ?>',
                    color: '<?php echo $color; ?>',
                    mode: 'schedule',
                    extendedProps: { 
                        mainTitle: '<?php echo addslashes($row['function_name']); ?>', 
                        status: '<?php echo $row['status']; ?>', 
                        room: '<?php echo addslashes($row['room_name'] ?? ''); ?>',
                        customer: '<?php echo addslashes($row['cust_name'] ?? ''); ?>',
                        phone: '<?php echo addslashes($row['cust_phone'] ?? ''); ?>',
                        pax: '<?php echo $row['pax']; ?>',
                        deposit: '<?php echo number_format($row['deposit'], 2); ?>',
                        total: '<?php echo number_format($row['total_amount'], 2); ?>',
                        remark: '<?php echo addslashes($row['schedule_function']); ?>'
                    }
                },
                <?php } ?>
            ],
            
            eventClick: function (info) {
                const props = info.event.extendedProps;
                const eventId = info.event.extendedProps.ref_id;
                const st = props.status.toLowerCase();
                
                let badgeClass = 'bg-secondary';
                if(st === 'pending') badgeClass = 'bg-warning text-dark';
                else if(st === 'confirmed' || st === 'approved') badgeClass = 'bg-info text-dark';
                else if(st === 'in progress') badgeClass = 'bg-primary';
                else if(st === 'completed') badgeClass = 'bg-success';
                else if(st === 'cancelled') badgeClass = 'bg-danger';

                detailPanel.innerHTML = `
                <div class="animate__animated animate__fadeIn">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">ID งาน: #${eventId}</small>
                            <span class="badge ${badgeClass}">${props.status}</span>
                        </div>
                        <h4 class="fw-bold mb-0" style="color: #1a1a1a;">${props.mainTitle}</h4>
                    </div>
                    
                    <ul class="list-group list-group-flush border-top">
                        <li class="list-group-item px-0"><strong><i class="bi bi-person me-2"></i>ลูกค้า:</strong> ${props.customer || 'ไม่ได้ระบุ'}</li>
                        <li class="list-group-item px-0"><strong><i class="bi bi-telephone me-2"></i>เบอร์โทร:</strong> ${props.phone || '-'}</li>
                        <li class="list-group-item px-0 text-primary"><strong><i class="bi bi-geo-alt me-2"></i>สถานที่:</strong> ${props.room}</li>
                        <li class="list-group-item px-0 text-danger"><strong><i class="bi bi-people me-2"></i>จำนวนคน:</strong> ${props.pax} ท่าน</li>
                        <li class="list-group-item px-0"><strong><i class="bi bi-cash-stack me-2"></i>มัดจำ:</strong> ฿${props.deposit}</li>
                        <li class="list-group-item px-0 fw-bold"><strong><i class="bi bi-wallet2 me-2"></i>มูลค่ารวม:</strong> ฿${props.total}</li>
                        <li class="list-group-item px-0 small text-muted"><strong><i class="bi bi-sticky me-2"></i>รายละเอียด:</strong><br>${props.remark || '-'}</li>
                    </ul>
                    
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=${eventId}" class="btn btn-dark fw-bold text-gold">
                            <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูลงาน
                        </a>
                        <a href="view.php?id=${eventId}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-printer me-2"></i> พิมพ์เอกสาร (PDF)
                        </a>
                    </div>
                </div>
                `;
            }
        });
        calendar.render();
        updateCalendarView();
    });

    // ฟังก์ชันสลับโหมด (อยู่นอก DOMContentLoaded เพื่อให้เรียกใช้ได้)
    window.updateCalendarView = function() {
        if(!calendar) return;
        const mode = document.getElementById('timeMode').value;
        calendar.getEvents().forEach(event => {
            if (event.extendedProps.mode === mode) {
                event.setProp('display', 'block');
            } else {
                event.setProp('display', 'none');
            }
        });
    }
</script>

<style>
    #calendar { font-size: 0.85rem; background: white; border-radius: 10px; padding: 10px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; color: #333; }
    .fc-event { cursor: pointer; border: none !important; padding: 2px 4px; border-radius: 4px; }
    .text-gold { color: #d4af37; }
    .bg-dark { background-color: #1a1a1a !important; }
    .list-group-item { border-bottom: 1px dashed #eee; padding-top: 12px; padding-bottom: 12px; }
    .badge { font-weight: 500; padding: 0.5em 0.8em; }
</style>

<?php include "footer.php"; ?>