<?php include "config.php";
include "header.php"; ?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="container-fluid py-3">
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
                // ดึงข้อมูลโดยแยก main_id ออกมาให้ชัดเจน
                $sql = "SELECT f.id AS main_id, f.function_name, f.room_name, f.booking_name, f.remark,
                               s.schedule_date, s.schedule_hour, s.schedule_function, s.schedule_guarantee 
                        FROM functions f
                        JOIN function_schedules s ON f.id = s.function_id";
                
                $q = mysqli_query($conn, $sql);
                while ($row = mysqli_fetch_assoc($q)) {
                    $display_title = "[" . $row['schedule_hour'] . "] " . $row['schedule_function'];
                    
                    // สุ่มสีโดยอ้างอิงจาก main_id (งานเดียวกัน ID เดียวกัน สีจะเหมือนกัน)
                    // เพิ่มสตริงเข้าไปเล็กน้อยเพื่อให้การสุ่มกระจายตัวดีขึ้น
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
                            room: '<?php echo addslashes($row['room_name']); ?>',
                            booking: '<?php echo addslashes($row['booking_name']); ?>',
                            guarantee: '<?php echo $row['schedule_guarantee']; ?>',
                            remark: '<?php echo addslashes($row['remark']); ?>'
                        }
                    },
                <?php } ?>
            ],
            eventClick: function (info) {
                const props = info.event.extendedProps;
                const eventId = info.event.id;

                detailPanel.innerHTML = `
                <div class="animate__animated animate__fadeIn">
                    <div class="mb-3">
                        <small class="text-muted d-block">ชื่องานหลัก:</small>
                        <h4 class="text-primary fw-bold mb-0">${props.mainTitle}</h4>
                        <span class="badge bg-light text-dark border mt-1">ID งาน: ${eventId}</span>
                    </div>
                    
                    <ul class="list-group list-group-flush border-top">
                        <li class="list-group-item px-0"><strong>กิจกรรมย่อย:</strong><br>${info.event.title}</li>
                        <li class="list-group-item px-0"><strong>เวลา:</strong> ${props.time} น.</li>
                        <li class="list-group-item px-0"><strong>ห้องประชุม:</strong> ${props.room}</li>
                        <li class="list-group-item px-0"><strong>ผู้จอง:</strong> ${props.booking}</li>
                        <li class="list-group-item px-0 text-danger"><strong>จำนวนการันตี:</strong> ${props.guarantee} ท่าน</li>
                        <li class="list-group-item px-0 small text-muted"><strong>หมายเหตุ:</strong><br>${props.remark || '-'}</li>
                    </ul>
                    
                    <hr>
                    <a href="edit.php?id=${eventId}" class="btn btn-warning w-100 fw-bold shadow-sm mb-2">
                        <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูลงานนี้
                    </a>
                    
                    <a href="view.php?id=${eventId}" target="_blank" class="btn btn-outline-secondary w-100 btn-sm">
                        <i class="bi bi-printer me-2"></i> พิมพ์เอกสาร (PDF)
                    </a>
                </div>
                `;
            }
        });
        calendar.render();
    });
</script>

<style>
    #calendar { font-size: 0.85rem; background: white; border-radius: 10px; }
    .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; color: #333; }
    .fc-button { padding: 3px 7px !important; font-size: 0.8rem !important; }
    
    /* สไตล์สำหรับ Event */
    .fc-event {
        cursor: pointer;
        border: none !important;
        padding: 1px 3px;
        transition: transform 0.1s;
    }
    .fc-event:hover {
        transform: scale(1.03);
        z-index: 10;
    }

    .text-gold { color: #d4af37; }
    .bg-dark { background-color: #1a1a1a !important; }
    .list-group-item { border-bottom: 1px dashed #eee; font-size: 0.9rem; }
    
    @media (min-width: 992px) {
        .sticky-top { top: 20px !important; }
    }
</style>

<?php include "footer.php"; ?>