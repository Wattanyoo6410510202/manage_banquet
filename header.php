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

// 4. เมนูด้านข้าง: [หัวข้อกลุ่ม => [[ไฟล์, ไอคอน, ชื่อเมนู, Role ที่เห็น, หน้าที่นับเป็นเมนูนี้]]]
//    กลุ่มที่ผู้ใช้ไม่เห็นเมนูใดเลยจะถูกซ่อนทั้งกลุ่ม
$nav_role = strtolower($_SESSION['role'] ?? '');
$nav_groups = [
    'เมนูหลัก' => [
        ['executive_dashboard.php', 'bi-grid-1x2', 'Executive Dashboard', ['admin', 'staff', 'gm', 'sale', 'procurement']],
        ['calendar.php', 'bi-calendar3', 'ปฏิทิน', ['admin', 'staff', 'gm', 'sale', 'procurement']],
        ['manage_banquet.php', 'bi-calendar-event', 'จัดเลี้ยง (Banquet)', ['admin', 'staff', 'gm', 'sale', 'manager', 'procurement'], ['manage_banquet.php', 'view.php', 'edit.php', 'add_event.php', 'finance.php']],
        ['booking_list.php', 'bi-journal-bookmark', 'รายการจองห้อง / ใบเสนอราคา', ['admin', 'staff', 'gm', 'sale', 'manager', 'procurement'], ['booking_list.php', 'room_calendar.php']],
        ['quotation_list.php', 'bi-file-earmark-text', 'ใบเสนอราคา', ['admin', 'staff', 'gm', 'sale', 'procurement'], ['quotation_list.php', 'add_quote.php', 'edit_quotation.php', 'quotation_view.php']],
        ['customer.php', 'bi-people', 'ลูกค้า', ['admin', 'staff', 'gm', 'sale', 'procurement']],
    ],
    'บันทึกภาระงานแผนก' => [
        ['sales_dept.php', 'bi-graph-up-arrow', 'งานขาย', ['admin', 'gm', 'sale']],
        ['banquet_mt.php', 'bi-tools', 'งานช่าง', ['admin', 'technician']],
        ['banquet_bk.php', 'bi-calendar-check', 'งานจัดเลี้ยง', ['admin', 'banquet_staff']],
        ['banquet_hk.php', 'bi-house-door', 'งานแม่บ้าน', ['admin', 'housekeeping']],
    ],
    'ตั้งค่ารายการตรวจสอบ' => [
        ['checklist_mt.php', 'bi-ui-checks', 'Checklist ช่าง', ['admin', 'technician']],
        ['checklist_hk.php', 'bi-ui-checks', 'Checklist แม่บ้าน', ['admin', 'housekeeping']],
        ['checklist_bk.php', 'bi-ui-checks', 'Checklist จัดเลี้ยง', ['admin', 'banquet_staff']],
    ],
    'เพิ่ม/แก้ไข' => [
        ['main_kitchen.php', 'bi-cup-hot', 'การจัดการเบรก', ['admin', 'gm', 'sale', 'procurement']],
        ['food_management.php', 'bi-egg-fried', 'การจัดการเมนูอาหาร', ['admin', 'gm', 'sale', 'procurement']],
        ['setting_room.php', 'bi-door-open', 'เพิ่มห้องประชุม', ['admin', 'gm', 'sale', 'procurement']],
        ['setting_master.php', 'bi-tags', 'เพิ่มประเภทเมนูและเบรก', ['admin', 'gm', 'sale', 'procurement']],
        ['setting_type.php', 'bi-bookmark-star', 'เพิ่มประเภทการจัดเลี้ยง', ['admin', 'gm', 'sale', 'procurement']],
    ],
    'Settings' => [
        ['setting.php', 'bi-gear', 'การตั้งค่า', ['admin']],
    ],
];
$nav_user = $_SESSION['user_name'] ?? $_SESSION['user'] ?? '';
$nav_initial = mb_substr(trim((string)($_SESSION['user'] ?? 'U')), 0, 1);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Banquet Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- IBM Plex Sans Thai = ฟอนต์ของระบบ, Sarabun = ฟอนต์ของเอกสารพิมพ์ (ใบเสนอราคา/EO) -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- jQuery (Must be before other scripts) -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Driver.js for Tutorial -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/theme.css') ?: time(); ?>">
</head>

<body>

    <header class="app-topbar">
        <button type="button" id="sidebarCollapse" class="icon-btn" title="ยุบ/ขยายเมนู" aria-label="ยุบ/ขยายเมนู">
            <i class="bi bi-list"></i>
        </button>
        <a class="app-brand" href="executive_dashboard.php">
            <span class="mark"><i class="bi bi-building"></i></span>
            <span>Banquet <span class="sub">Management</span></span>
        </a>

        <div class="spacer"></div>

        <div class="top-actions">
            <button type="button" id="startTutorial" class="btn-tutorial" title="โหมดสอนใช้งาน">
                <i class="bi bi-question-circle"></i><span>โหมดสอนใช้งาน</span>
            </button>
            <a href="https://line.me/R/ti/p/@080cyphf" target="_blank" rel="noopener" class="btn-line" title="เพิ่มเพื่อน LINE เพื่อรับแจ้งเตือน">
                <i class="bi bi-line"></i><span>@080cyphf</span>
            </a>
            <div class="dropdown">
                <button type="button" class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?php echo htmlspecialchars($nav_initial); ?></span>
                    <span class="user-meta d-flex flex-column align-items-start lh-sm">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
                        <span class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
                    </span>
                    <i class="bi bi-chevron-down text-muted fs-xs"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <div class="px-3 py-2">
                            <div class="fw-bold"><?php echo htmlspecialchars($nav_user); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="wrapper">
        <nav id="sidebar" aria-label="เมนูหลัก">
            <?php foreach ($nav_groups as $group_label => $items): ?>
                <?php $visible = array_filter($items, fn($it) => in_array($nav_role, $it[3])); ?>
                <?php if (!$visible) continue; ?>
                <div class="nav-group"><?php echo $group_label; ?></div>
                <ul>
                    <?php foreach ($visible as $it): ?>
                    <li>
                        <a href="<?php echo $it[0]; ?>" class="<?php echo is_active($it[4] ?? $it[0]); ?>" title="<?php echo htmlspecialchars($it[2]); ?>">
                            <i class="bi <?php echo $it[1]; ?>"></i><span><?php echo $it[2]; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div id="content">
            <div class="container-fluid">
