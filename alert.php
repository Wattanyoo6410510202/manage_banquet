<div id="alert-container">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เช็คว่ามีค่าส่งมาทาง SESSION หรือทาง GET (URL) หรือไม่
$msg_type = $_SESSION['flash_msg'] ?? $_GET['msg_type'] ?? null;

if ($msg_type) {
    $alert_msg = "";
    $alert_class = "";
    $alert_icon = "";

    switch ($msg_type) {
        case 'success':
            $alert_msg = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $alert_class = "alert-success text-success";
            $alert_icon = "bi-check-circle-fill";
            break;
        case 'updated':
            $alert_msg = "แก้ไขข้อมูลสำเร็จ";
            $alert_class = "alert-info text-info";
            $alert_icon = "bi-info-circle-fill";
            break;
        case 'deleted':
        case 'delete_success': 
            $alert_msg = "ลบรายการเรียบร้อยแล้ว";
            $alert_class = "alert-danger text-danger";
            $alert_icon = "bi-trash-fill";
            break;
        case 'approved':
            $alert_msg = "อนุมัติรายการจัดเลี้ยงสำเร็จ";
            $alert_class = "alert-success text-success";
            $alert_icon = "bi-shield-check";
            break;
        case 'error':
        case 'delete_error':
            $alert_msg = "เกิดข้อผิดพลาด หรือไม่สามารถลบข้อมูลได้";
            $alert_class = "alert-warning text-warning";
            $alert_icon = "bi-exclamation-triangle-fill";
            break;
        case 'invalid_id':
            $alert_msg = "ไม่พบรหัสรายการที่ต้องการลบ";
            $alert_class = "alert-secondary text-secondary";
            $alert_icon = "bi-question-circle";
            break;
    }

    if ($alert_msg != "") {
        echo '
        <div class="alert ' . $alert_class . ' alert-dismissible fade show border-0 shadow-sm mb-4 bg-white" role="alert" style="border-left: 5px solid currentColor !important;">
            <div class="d-flex align-items-center">
                <i class="bi ' . $alert_icon . ' me-2 fs-5"></i>
                <div>' . $alert_msg . '</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    // ล้างค่า SESSION ออก
    unset($_SESSION['flash_msg']);
}
?>

<script>
    // ฟังก์ชันสั่งปิดตัวเอง (ทำงานทั้งตอน Refresh และ Ajax)
    (function() {
        setTimeout(function() {
            let alertElement = document.querySelector('#alert-container .alert');
            if (alertElement) {
                // ใช้ Bootstrap class ในการ Fade
                alertElement.classList.remove('show');
                setTimeout(() => alertElement.remove(), 500);
            }
        }, 2500);
    })();
</script>
</div>