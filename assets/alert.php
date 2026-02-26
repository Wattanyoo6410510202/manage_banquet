<div id="alert-container"
    style="position: fixed; top: 20px; right: 20px; z-index: 9999; width: 100%; max-width: 350px;">
    <style>
        /* สร้าง Animation สำหรับสไลด์มาจากขวา */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* สร้าง Animation สำหรับสไลด์กลับไปทางขวา (ตอนปิด) */
        .slide-out-right {
            transform: translateX(100%) !important;
            opacity: 0 !important;
            transition: all 0.5s ease-in-out !important;
        }

        .custom-alert {
            animation: slideInRight 0.5s ease-out forwards;
            border: none !important;
            border-left: 6px solid currentColor !important;
            box-shadow: -5px 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>

    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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
                $alert_msg = "อนุมัติรายการสำเร็จ";
                $alert_class = "alert-success text-success";
                $alert_icon = "bi-shield-check";
                break;
            case 'error':
            case 'delete_error':
                $alert_msg = "เกิดข้อผิดพลาด!";
                $alert_class = "alert-warning text-warning";
                $alert_icon = "bi-exclamation-triangle-fill";
                break;
       
            case 'update_success':
                $alert_msg = "อัปเดตข้อมูลสำเร็จ";
                $alert_class = "alert-success text-success";
                $alert_icon = "bi-check-circle-fill";
                break;
        }

        if ($alert_msg != "") {
            echo '
            <div class="alert ' . $alert_class . ' custom-alert alert-dismissible fade show bg-white mb-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi ' . $alert_icon . ' me-3 fs-4"></i>
                    <div class="fw-bold">' . $alert_msg . '</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        unset($_SESSION['flash_msg']);
    }
    ?>

    <script>
        (function () {
            setTimeout(function () {
                let alertElement = document.querySelector('#alert-container .alert');
                if (alertElement) {
                    // ใส่ Class สำหรับสไลด์ออก
                    alertElement.classList.add('slide-out-right');
                    // ลบ Element ทิ้งหลังจากสไลด์ออกเสร็จ
                    setTimeout(() => alertElement.remove(), 500);
                }
            }, 3000); // แสดงค้างไว้ 3 วินาที
        })();
    </script>
</div>