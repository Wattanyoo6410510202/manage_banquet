<?php include "config.php"; include "header.php"; ?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div id='calendar'></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        events: [
            <?php
            $q = mysqli_query($conn, "SELECT * FROM events");
            while($row = mysqli_fetch_assoc($q)){
                echo "{ title: '{$row['function_name']}', start: '{$row['event_date']}' },";
            }
            ?>
        ]
    });
    calendar.render();
});
</script>

<?php include "footer.php"; ?>