<?php include "config.php";
include "header.php"; ?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
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
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        var detailPanel = document.getElementById('detailPanel');

        var calendar = new FullCalendar.Calendar(calendarEl, {
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
                // JOIN meeting_rooms เพื่อเอาชื่อห้อง และดึง status มาให้เป๊ะๆ
                $sql = "SELECT f.id AS main_id, f.function_name, f.booking_name, f.remark, f.status,
                               r.room_name AS true_room_name,
                               s.schedule_date, s.schedule_hour, s.schedule_function, s.schedule_guarantee 
                        FROM functions f
                        LEFT JOIN meeting_rooms r ON f.room_id = r.id
                        JOIN function_schedules s ON f.id = s.function_id";
                
                $q = mysqli_query($conn, $sql);
                while ($row = mysqli_fetch_assoc($q)) {
                    $display_title = "[" . $row['schedule_hour'] . "] " . $row['schedule_function'];
                    $event_color = "#" . substr(md5($row['main_id'] . "banquet_color"), 0, 6);
                ?>
                    {
                        id: '<?php echo $row['main_id']; ?>',
                        title: '<?php echo addslashes($display_title); ?>',
                        start: '<?php echo $row['schedule_date']; ?>',
                        color: '<?php echo $event_color; ?>',
                        extendedProps: {
                            mainTitle: '<?php echo addslashes($row['function_name']); ?>',
                            time: '<?php echo $row['schedule_hour']; ?>',
                            room: '<?php echo addslashes($row['true_room_name'] ?? 'ไม่ระบุห้อง'); ?>',
                            booking: '<?php echo addslashes($row['booking_name']); ?>',
                            guarantee: '<?php echo $row['schedule_guarantee']; ?>',
                            remark: '<?php echo addslashes($row['remark']); ?>',
                            status: '<?php echo trim($row['status']); ?>' 
                        }
                    },
                <?php } ?>
            ],
            eventClick: function (info) {
                const props = info.event.extendedProps;
                const eventId = info.event.id;
                
                // แปลง Status เป็นตัวเล็กเพื่อเช็คเงื่อนไขให้แม่นยำขึ้น
                const st = props.status.toLowerCase();
                let statusBadge = '';
                
                if(st === 'approved' || st === 'confirmed' || st === 'active') {
                    statusBadge = '<span class="badge bg-success">อนุมัติแล้ว</span>';
                } else if(st === 'pending') {
                    statusBadge = '<span class="badge bg-warning text-dark">รออนุมัติ</span>';
                } else if(st === 'cancelled') {
                    statusBadge = '<span class="badge bg-danger">ยกเลิก</span>';
                } else {
                    statusBadge = `<span class="badge bg-secondary">${props.status}</span>`;
                }

                detailPanel.innerHTML = `
                <div class="animate__animated animate__fadeIn">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">ID งาน: #${eventId}</small>
                            ${statusBadge}
                        </div>
                        <h4 class="fw-bold mb-0" style="color: #1a1a1a;">${props.mainTitle}</h4>
                    </div>
                    
                    <ul class="list-group list-group-flush border-top">
                        <li class="list-group-item px-0"><strong><i class="bi bi-layers me-2"></i>กิจกรรม:</strong><br>${info.event.title}</li>
                        <li class="list-group-item px-0"><strong><i class="bi bi-clock me-2"></i>เวลา:</strong> ${props.time} น.</li>
                        <li class="list-group-item px-0 text-primary"><strong><i class="bi bi-geo-alt me-2"></i>ห้องประชุม:</strong> ${props.room}</li>
                        <li class="list-group-item px-0"><strong><i class="bi bi-person me-2"></i>ผู้จอง:</strong> ${props.booking}</li>
                        <li class="list-group-item px-0 text-danger"><strong><i class="bi bi-people me-2"></i>จำนวน:</strong> ${props.guarantee} ท่าน</li>
                        <li class="list-group-item px-0 small text-muted"><strong><i class="bi bi-sticky me-2"></i>หมายเหตุ:</strong><br>${props.remark || '-'}</li>
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
    });
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