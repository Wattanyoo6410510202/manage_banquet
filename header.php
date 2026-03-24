<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. เช็ค Login (ใช้ JS แทน header เพื่อกัน Error)
if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// 2. ฟังก์ชันเช็คสิทธิ์แบบกลุ่ม "all_staff"
function access_control($check)
{
    $role = strtolower($_SESSION['role'] ?? '');

    // นิยามกลุ่มสิทธิ์ไว้ที่นี่ที่เดียว
    $groups = [
        'all_staff' => ['admin', 'staff', 'manager'],
        'admin_only' => ['admin']
    ];

    // ตรวจสอบว่าเป็นชื่อกลุ่มหรือ Array
    if (!is_array($check) && isset($groups[$check])) {
        $allowed = $groups[$check];
    } else {
        $allowed = is_array($check) ? $check : [$check];
    }

    if (!in_array($role, array_map('strtolower', $allowed))) {
        // ใช้ JS ดีดออก หายห่วงเรื่อง Headers already sent
        echo "<script>window.location.href='access_denied.php';</script>";
        exit;
    }
}

// 3. ฟังก์ชัน Active Menu
function is_active($pages)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    if (is_array($pages)) {
        return in_array($current_page, $pages) ? 'active' : '';
    }
    return ($current_page == $pages) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Sale System </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Sarabun:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ล็อค Navbar บน */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1050;
            /* เพิ่มให้สูงกว่า Sidebar */
            height: 56px;
            background-color: #1a1a1a !important;
        }

        /* ล็อค Sidebar ข้าง */
        #sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            width: 260px;
            height: calc(100vh - 56px);
            overflow-y: auto;
            z-index: 1040;
            transition: all 0.3s ease;
            background-color: #1a1a1a;
            border-right: 1px solid rgba(184, 148, 65, 0.2);
        }

        /* เนื้อหาหลัก */
        #content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: calc(100vh - 56px);
            transition: all 0.3s ease;
            padding: 20px;
        }

        /* คลาสพิเศษสำหรับชื่อ User ในมือถือ */
        .user-name {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* สำหรับมือถือ (Tablet & Phone) */
        @media (max-width: 991px) {
            #sidebar {
                margin-left: -260px;
            }

            #sidebar.active {
                margin-left: 0;
            }

            #content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .user-name {
                max-width: 70px;
                /* ในมือถือจำกัดให้สั้นลง */
                font-size: 0.85rem;
            }

            .navbar-brand {
                font-size: 0.9rem;
                /* ย่อขนาดโลโก้ในมือถือ */
            }
        }

        /* สำหรับหน้าจอเล็กมาก (iPhone SE) */
        @media (max-width: 375px) {
            .user-name {
                display: none;
                /* ซ่อนชื่อไปเลย เหลือแค่ไอคอน */
            }

            .navbar-brand span:last-child {
                display: none;
                /* ซ่อนคำว่า Management */
            }
        }

        .text-gold {
            color: #b89441 !important;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm border-bottom border-secondary">
        <div class="container-fluid px-2 px-md-3">

            <div class="d-flex align-items-center">
                <button type="button" id="sidebarCollapse" class="btn btn-link text-gold d-lg-none me-1 p-1">
                    <i class="bi bi-list fs-4"></i>
                </button>

                <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                    <i class="bi bi-building me-2 text-gold"></i>
                    <div class="d-flex flex-column flex-sm-row">
                        <span class="text-white">Banquet</span>
                        <span class="text-gold ms-sm-1">Management</span>
                    </div>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <div class="d-flex align-items-center text-white-50">
                    <i class="bi bi-person-circle text-gold fs-5 me-1 me-sm-2"></i>
                    <span class="fw-bold text-white user-name">
                        <?php echo $_SESSION['user']; ?>
                    </span>
                </div>

                <div class="vr bg-secondary d-none d-sm-block" style="height: 20px; opacity: 0.5;"></div>

                <a href="logout.php"
                    class="btn btn-outline-danger btn-sm border-0 px-2 py-1 d-flex align-items-center shadow-none">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    <span class="d-none d-sm-inline small">Logout</span>
                </a>
            </div>

        </div>
    </nav>

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header py-4 px-3">
                <small class="text-uppercase text-gold fw-bold letter-spacing-1" style="font-size: 0.7rem;">Main
                    Navigation</small>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php" class="<?php echo is_active('dashboard.php'); ?>">
                        <i class="bi bi-speedometer2"></i> แดชบอร์ด
                    </a>
                </li>
                <li>
                    <a href="calendar.php" class="<?php echo is_active('calendar.php'); ?>">
                        <i class="bi bi-speedometer2"></i> ปฏิทิน
                    </a>
                </li>

                <li>
                    <a href="manage_banquet.php"
                        class="<?php echo is_active(['manage_banquet.php', 'view.php', 'edit.php', 'add_event.php', 'finance.php']); ?>">
                        <i class="bi bi-calendar-check"></i> จัดเลี้ยง (Banquet)
                    </a>
                </li>

                <li>
                    <a href="quotation_list.php"
                        class="<?php echo is_active(['quotation_list.php', 'add_quote.php', 'quotation_view.php']); ?>">
                        <i class="bi bi-file-earmark-text"></i> ใบเสนอราคา
                    </a>
                </li>
                <li>
                    <a href="customer.php" class="<?php echo is_active('customer.php'); ?>">
                        <i class="bi bi-person"></i> ลูกค้า
                    </a>
                </li>
                <li class="mt-4 sidebar-header px-3">
                    <small class="text-uppercase text-white-50 fw-bold" style="font-size: 0.7rem;">เพิ่ม/แก้ไข</small>
                </li>
                <li>
                    <a href="main_kitchen.php" class="<?php echo is_active('main_kitchen.php'); ?>">
                        <i class="bi bi-egg"></i> การจัดการเบรก
                    </a>
                </li>
                <li>
                    <a href="setting_room.php" class="<?php echo is_active('setting_room.php'); ?>">
                        <i class="bi bi-door-open"></i> เพิ่มห้องประชุม
                    </a>
                </li>
                <li>
                    <a href="food_management.php" class="<?php echo is_active('food_management.php'); ?>">
                        <i class="bi bi-menu-app"></i> การจัดการเมนูอาหาร
                    </a>
                </li>
                <?php
                // เช็คว่าสิทธิ์ปัจจุบัน อยู่ในกลุ่มที่อนุญาต (Admin หรือ Staff) หรือไม่
                if (in_array(strtolower($_SESSION['role']), ['admin', 'staff', 'gm'])):
                    ?>
                    <li>
                        <a href="setting_master.php" class="<?php echo is_active('setting_master.php'); ?>">
                            <i class="bi bi-plus-circle"></i> เพิ่มประเภทเมนูและเบรก
                        </a>
                    </li>

                    <li>
                        <a href="setting_type.php" class="<?php echo is_active('setting_type.php'); ?>">
                            <i class="bi bi-plus-circle "></i> เพิ่มประเภทการจัดเลี้ยง
                        </a>
                    </li>

                <?php endif; ?>



                <?php if (strtolower($_SESSION['role']) === 'admin'): ?>
                    <li class="mt-4 sidebar-header px-3">
                        <small class="text-uppercase text-white-50 fw-bold" style="font-size: 0.7rem;">Settings</small>
                    </li>
                    <li>
                        <a href="setting.php" class="<?php echo is_active('setting.php'); ?>">
                            <i class="bi bi-gear-fill "></i> การตั้งค่า
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </nav>

        <div id="content">
            <div class="container-fluid pt-1 px-1">