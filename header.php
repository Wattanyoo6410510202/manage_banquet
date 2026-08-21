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
        echo "<script>window.location.href='login.php?error=access_denied';</script>";
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
    <!-- jQuery (Must be before other scripts) -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: time(); ?>">
    <!-- Driver.js for Tutorial -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
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

        /* ปรับแต่งปุ่ม Tutorial */
        .btn-tutorial {
            color: #b89441;
            border: 1px solid #b89441;
            transition: all 0.3s;
        }
        .btn-tutorial:hover {
            background-color: #b89441;
            color: #fff;
        }
        /* ซ่อนข้อความ Tutorial ในมือถือ */
        @media (max-width: 576px) {
            .btn-tutorial span {
                display: none;
            }
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
        /* สถานะพับใน Desktop (Mini-sidebar) */
        #sidebar.collapsed {
            width: 60px;
        }
        #sidebar.collapsed .sidebar-header,
        #sidebar.collapsed .sidebar-header small,
        #sidebar.collapsed .components li a span,
        #sidebar.collapsed .components li a::after,
        #sidebar.collapsed .components li a small {
            display: none !important;
        }
        #sidebar.collapsed .components li a {
            text-align: center;
            padding: 15px 0;
            font-size: 0;
        }
        #sidebar.collapsed .components li a i {
            margin-right: 0 !important;
            font-size: 1.2rem;
        }

        /* เนื้อหาหลัก */
        #content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: calc(100vh - 56px);
            transition: all 0.3s ease;
            padding: 20px;
        }
        #content.expanded {
            margin-left: 60px;
            width: calc(100% - 60px);
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
                <button type="button" id="sidebarCollapse" class="btn btn-link text-gold me-1 p-1" title="ยุบ/ขยายเมนู">
                    <i class="bi bi-list fs-4"></i>
                </button>

                <a class="navbar-brand fw-bold d-flex align-items-center" href="executive_dashboard.php">
                    <i class="bi bi-building me-2 text-gold"></i>
                    <div class="d-flex flex-column flex-sm-row">
                        <span class="text-white">Banquet</span>
                        <span class="text-gold ms-sm-1">Management</span>
                    </div>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <button type="button" id="startTutorial" class="btn btn-tutorial btn-sm d-flex align-items-center shadow-none">
                    <i class="bi bi-question-circle me-sm-1"></i>
                    <span class="small">โหมดสอนใช้งาน</span>
                </button>

                <a href="https://line.me/R/ti/p/@080cyphf" target="_blank"
                    class="btn btn-sm d-flex align-items-center gap-1 shadow-none"
                    style="background:#06C755; color:#fff; border-radius: 50px; font-size: 0.75rem; padding: 2px 10px;">
                    <i class="bi bi-line" style="font-size: 1rem;"></i>
                    <span class="d-none d-sm-inline">@080cyphf</span>
                </a>

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
                <?php $role = strtolower($_SESSION['role'] ?? ''); ?>
                <?php if (in_array($role, ['admin', 'staff', 'gm', 'sale', 'procurement'])): ?>
                <li>
                    <a href="executive_dashboard.php" class="<?php echo is_active('executive_dashboard.php'); ?>">
                        <i class="bi bi-clipboard-data"></i> Executive Dashboard
                    </a>
                </li>
                <li>
                    <a href="calendar.php" class="<?php echo is_active('calendar.php'); ?>">
                        <i class="bi bi-calendar"></i> ปฏิทิน
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'staff', 'gm', 'sale', 'manager', 'procurement'])): ?>
                <li>
                    <a href="manage_banquet.php"
                        class="<?php echo is_active(['manage_banquet.php', 'view.php', 'edit.php', 'add_event.php', 'finance.php']); ?>">
                        <i class="bi bi-calendar-event"></i> จัดเลี้ยง (Banquet)
                    </a>
                </li>
                <li>
                    <a href="booking_list.php"
                        class="<?php echo is_active(['booking_list.php', 'room_calendar.php']); ?>">
                        <i class="bi bi-journal-bookmark"></i> รายการจองห้อง
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'staff', 'gm', 'sale', 'procurement'])): ?>
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
                <?php endif; ?>
                <?php $role = strtolower($_SESSION['role'] ?? ''); ?>

                <?php if (in_array($role, ['admin', 'procurement', 'technician', 'housekeeping', 'banquet_staff', 'gm', 'sale'])): ?>
                <li class="mt-4 sidebar-header px-3">
                    <small class="text-uppercase text-white-50 fw-bold" style="font-size: 0.7rem;">บันทึกภาระงานแผนก</small>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'gm', 'sale'])): ?>
                <li>
                    <a href="sales_dept.php" class="<?php echo is_active('sales_dept.php'); ?>">
                        <i class="bi bi-graph-up-arrow"></i> งานขาย
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'technician'])): ?>
                <li>
                    <a href="banquet_mt.php" class="<?php echo is_active('banquet_mt.php'); ?>">
                        <i class="bi bi-tools"></i> งานช่าง
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'banquet_staff'])): ?>
                <li>
                    <a href="banquet_bk.php" class="<?php echo is_active('banquet_bk.php'); ?>">
                        <i class="bi bi-calendar-check"></i> งานจัดเลี้ยง
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'housekeeping'])): ?>
                <li>
                    <a href="banquet_hk.php" class="<?php echo is_active('banquet_hk.php'); ?>">
                        <i class="bi bi-house-door"></i> งานแม่บ้าน
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'procurement'])): ?>
                <li class="mt-4 sidebar-header px-3">
                    <small class="text-uppercase text-white-50 fw-bold" style="font-size: 0.7rem;">ตั้งค่ารายการตรวจสอบ</small>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'technician'])): ?>
                <li>
                    <a href="checklist_mt.php" class="<?php echo is_active('checklist_mt.php'); ?>">
                        <i class="bi bi-tools"></i> Checklist ช่าง
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'housekeeping'])): ?>
                <li>
                    <a href="checklist_hk.php" class="<?php echo is_active('checklist_hk.php'); ?>">
                        <i class="bi bi-house-door"></i> Checklist แม่บ้าน
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($role, ['admin', 'banquet_staff'])): ?>
                <li>
                    <a href="checklist_bk.php" class="<?php echo is_active('checklist_bk.php'); ?>">
                        <i class="bi bi-calendar-check"></i> Checklist จัดเลี้ยง
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'gm', 'sale', 'procurement'])): ?>
                <li class="mt-4 sidebar-header px-3">
                    <small class="text-uppercase text-white-50 fw-bold" style="font-size: 0.7rem;">เพิ่ม/แก้ไข</small>
                </li>
                <li>
                    <a href="main_kitchen.php" class="<?php echo is_active('main_kitchen.php'); ?>">
                        <i class="bi bi-egg"></i> การจัดการเบรก
                    </a>
                </li>
                
                <li>
                    <a href="food_management.php" class="<?php echo is_active('food_management.php'); ?>">
                        <i class="bi bi-menu-app"></i> การจัดการเมนูอาหาร
                    </a>
                </li>
                <li>
                    <a href="setting_room.php" class="<?php echo is_active('setting_room.php'); ?>">
                        <i class="bi bi-door-open"></i> เพิ่มห้องประชุม
                    </a>
                </li>
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

                <?php if ($role === 'admin'): ?>
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