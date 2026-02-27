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
        <title>Sale System - Banquet</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
                z-index: 1030;
                height: 56px;
            }

            /* ล็อค Sidebar ข้าง */
            #sidebar {
                position: fixed;
                top: 56px;
                /* ต่อจากความสูง Navbar */
                left: 0;
                height: calc(100vh - 56px);
                overflow-y: auto;
                z-index: 1000;
            }

            /* ดันเนื้อหา content ไม่ให้โดนเมนูทับ */
            .wrapper {
                margin-top: 50px;
                /* หลบ Navbar */
            }

            #content {
                margin-left: 260px;
                /* หลบ Sidebar (ตามความกว้างที่คุณตั้งไว้) */
                width: calc(100% - 260px);
                min-height: calc(100vh - 56px);
            }

            /* สำหรับมือถือ */
            @media (max-width: 991px) {
                #sidebar {
                    top: 0;
                    height: 100vh;
                    margin-left: -260px;
                }

                #sidebar.active {
                    margin-left: 0;
                }

                #content {
                    margin-left: 0;
                    width: 100%;
                }
            }
        </style>
    </head>

    <body>

        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm border-bottom border-secondary">
            <div class="container-fluid px-3">
                <button type="button" id="sidebarCollapse" class="btn btn-dark d-lg-none me-2">
                    <i class="bi bi-list text-gold"></i>
                </button>

                <a class="navbar-brand fw-bold ms-2" href="dashboard.php">
                    <i class="bi bi-building me-2 text-gold"></i><span class="text-white">SALE</span> <span
                        class="text-gold">SYSTEM</span>
                </a>

                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown">
                        <a href="#" class="text-white-50 text-decoration-none dropdown-toggle small"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1 text-gold"></i> <?php echo $_SESSION['user']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 bg-dark">
                            <li><a class="dropdown-item text-white" href="profile.php"><i
                                        class="bi bi-person me-2 text-gold"></i>โปรไฟล์</a></li>
                            <li>
                                <hr class="dropdown-divider bg-secondary">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i
                                        class="bi bi-box-arrow-right me-2 text-danger"></i>Logout</a></li>
                        </ul>
                    </div>
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
                        <a href="calendar.php" class="<?php echo is_active('calendar.php'); ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="manage_banquet.php"
                            class="<?php echo is_active(['manage_banquet.php', 'view.php']); ?>">
                            <i class="bi bi-calendar-check"></i> จัดเลี้ยง (Banquet)
                        </a>
                    </li>


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