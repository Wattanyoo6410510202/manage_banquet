<?php
include "config.php";

// 1. รับ ID และดึงข้อมูลขึ้นมาก่อน (ต้องทำก่อนเช็คสิทธิ์)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query = "SELECT * FROM functions WHERE id = $id";
$res = $conn->query($query);
$data = $res->fetch_assoc();

if (!$data) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>ไม่พบข้อมูลรายการนี้ครับจาร!</div></div>";
    exit;
}

// --- 🛡️ ส่วนควบคุมสิทธิ์ใหม่ (Manual Control) ---
$current_user = trim($_SESSION['user_name'] ?? '');
$user_role    = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$created_by   = trim($data['created_by'] ?? ''); // ดึงค่าคนสร้างมา trim ป้องกันช่องว่าง

// 🛡️ ด่านที่ 1: ใครเข้าหน้านี้ได้บ้าง? (Admin, GM, Staff, และเจ้าของงาน)
$is_admin = ($user_role === 'admin');
$is_gm    = ($user_role === 'gm');
$is_staff = ($user_role === 'staff');
$is_procurement = ($user_role === 'procurement');
$is_owner = ($created_by === $current_user);

// ตรรกะ: ถ้า "ไม่ใช่ Admin" และ "ไม่ใช่ GM" และ "ไม่ใช่ Staff" และ "ไม่ใช่เจ้าของงาน" และ "ไม่ใช่ Procurement" => ดีดออกทันที
if (!$is_admin && !$is_gm && !$is_staff && !$is_owner && !$is_procurement) {
    echo "<script>window.location.href='access_denied.php';</script>";
    exit();
}

// โหลด Header หลังจากเช็คสิทธิ์ผ่านแล้ว
require_once "header.php";
// เอา access_control ออกตามที่จารย์บอก เพื่อไม่ให้มันไปบล็อก GM ซ้ำซ้อน

// 🛡️ ด่านที่ 2: เช็คสถานะอนุมัติ (ล็อกเฉพาะสิทธิ์เล็กที่ไม่ได้ระบุให้ข้ามได้)
// ในที่นี้ให้ Admin, GM และ Staff (Sales) แก้ไขได้เสมอ แม้จะ Approve แล้ว ตามความต้องการล่าสุด
$can_bypass_approve = ($is_admin || $is_gm || $is_staff);

if (isset($data['approve']) && $data['approve'] != 0 && !$can_bypass_approve) {
    echo "<script>
        alert('จารย์! งานนี้ถูกอนุมัติแล้ว Staff ทั่วไปห้ามแก้ไขครับ!');
        window.location.href='manage_banquet.php';
    </script>";
    exit;
}

// --- 🛡️ จบส่วนควบคุมสิทธิ์ ---
// --- 🛡️ จบส่วนควบคุมสิทธิ์ ---

// 2. ดึงข้อมูลบริษัทสำหรับ Dropdown (ใช้แบบเดิมที่จารบอกว่าภาพขึ้น)
$query_companies = "SELECT id AS company_id, company_name, logo_path AS company_logo FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies);

// 3. ดึงข้อมูลจากตารางลูกทั้งหมด
$schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id ORDER BY id ASC");

// --- [NEW] Draft Management Logic ---
$project_id = intval($data['project_id']);
$all_drafts = [];
if ($project_id > 0) {
    $res_drafts = $conn->query("SELECT id, version_no, is_approved, draft_name, status FROM functions WHERE project_id = $project_id ORDER BY version_no ASC");
    while($d = $res_drafts->fetch_assoc()) {
        $all_drafts[] = $d;
    }
}
// ------------------------------------

$kitchens = $conn->query("SELECT * FROM function_kitchens WHERE function_id = $id ORDER BY id ASC");
$menus = $conn->query("SELECT * FROM function_menus WHERE function_id = $id ORDER BY id ASC");

$res_customers = $conn->query("SELECT * FROM customers ORDER BY cust_name ASC");
// 4. ดึงข้อมูล Master สำหรับ Dropdown (เหมือนหน้า Add)
// ดึงประเภท Break สำหรับตารางครัว
$query_breaks = "SELECT id, type_name FROM master_break_types ORDER BY id ASC";
$res_breaks = $conn->query($query_breaks);

// ดึงประเภทเซตเมนู สำหรับตารางรายละเอียดเมนู
$query_menu_types = "SELECT id, type_name FROM master_menu_types ORDER BY id ASC";
$res_menu_sets = $conn->query($query_menu_types);

$query_categories = "SELECT id, category_name FROM master_menu_categories ORDER BY sort_order ASC, id ASC";
$res_categories = $conn->query($query_categories);

$menu_types_with_cat = $conn->query("SELECT mmt.id, mmt.type_name, mmt.category_id, mmc.category_name 
    FROM master_menu_types mmt 
    LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id 
    ORDER BY mmc.sort_order ASC, mmt.id ASC");
$menu_types_array = [];
while ($mt = $menu_types_with_cat->fetch_assoc()) {
    $menu_types_array[] = $mt;
}

// 1. ดึง ID บริษัทของงานนี้ออกมาก่อน (จารย์มีตัวแปร $data อยู่แล้ว)
$current_company_id = $data['company_id']; 

// 2. แก้ Query ให้ดึงเฉพาะห้องของบริษัทนี้
$res_rooms = $conn->query("SELECT * FROM meeting_rooms 
                           WHERE company_id = '$current_company_id' 
                           AND status = 'active' 
                           ORDER BY floor ASC, room_name ASC");
$res_types = $conn->query("SELECT * FROM function_types ORDER BY id ASC");

// ดึงข้อมูลห้องทั้งหมดเตรียมไว้ให้ JS
// ดึงข้อมูลห้องพร้อมเช็กสถานะการจอง (เฉพาะรายการที่ Approve และยังไม่หมดเวลา)
// ดึงข้อมูลห้องพร้อม "รายการวันที่จอง" (เฉพาะงานที่ Approve แล้ว)
// ดึงข้อมูลห้องพร้อม "รายการวันที่จองทั้งหมด" (เฉพาะที่ Approve และเป็นอนาคต)
$all_rooms_res = $conn->query("
    SELECT 
        r.*, 
        (SELECT GROUP_CONCAT(DATE_FORMAT(f.start_time, '%d/%m %H:%i')
                ORDER BY f.start_time ASC SEPARATOR ', ')
         FROM functions f 
         WHERE f.room_id = r.id 
         AND f.status != 'Cancelled'
         AND f.status != 'Completed'
         AND f.start_time IS NOT NULL
        ) as booking_dates
    FROM meeting_rooms r
    WHERE r.status = 'active' 
    ORDER BY r.floor ASC, r.room_name ASC
");

$all_rooms_data = [];
while($row = $all_rooms_res->fetch_assoc()) {
    // เก็บเข้า array เพื่อส่งให้ json_encode ใน JS
    $all_rooms_data[] = $row;
}

$current_status = $data['status'] ?? 'Pending';
$status_log_res = $conn->query("SELECT old_status FROM function_status_log WHERE function_id = $id ORDER BY id DESC LIMIT 1");
$has_rollback = ($status_log_res && $status_log_res->num_rows > 0);
$prev_status = '';
if ($has_rollback) {
    $prev_status = $status_log_res->fetch_assoc()['old_status'];
}

$status_badge_map = [
    'Pending' => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock'],
    'Confirmed' => ['class' => 'bg-info text-dark', 'icon' => 'bi-check-circle'],
    'Approved' => ['class' => 'bg-info text-dark', 'icon' => 'bi-check-circle'],
    'In Progress' => ['class' => 'bg-primary text-white', 'icon' => 'bi-play-circle'],
    'Completed' => ['class' => 'bg-success text-white', 'icon' => 'bi-flag'],
    'Cancelled' => ['class' => 'bg-danger text-white', 'icon' => 'bi-x-circle'],
];
$status_info = $status_badge_map[$current_status] ?? ['class' => 'bg-secondary text-white', 'icon' => 'bi-question-circle'];
$status_text = $current_status === 'Confirmed' ? 'อนุมัติแล้ว' : ($current_status === 'In Progress' ? 'ดำเนินการ' : ($current_status === 'Completed' ? 'จบงานแล้ว' : ($current_status === 'Cancelled' ? 'ยกเลิก' : ($current_status === 'Pending' ? 'รออนุมัติ' : $current_status))));
?>
<style>
.room-card.selected {
    border: 2px solid #198754 !important;
    background-color: #f8fffb !important;
}

.room-card.selected .check-icon {
    display: block !important;
}

.bg-light {
    background-color: #f8f9fa !important;
}
</style>

<div class="container-fluid p-0">
    <form action="api/update_function.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="function_id" value="<?php echo $id; ?>">

        <div class="card border-0">
            <div class="card-header main-header p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-gold"></i> EDIT FUNCTION MEETING
                    </h4>
                    <div style="width: 100%; max-width: 500px;">
                        <div class="d-flex align-items-center gap-2 justify-content-end">
                            <a href="manage_banquet.php" class="btn btn-outline-secondary btn-sm px-3">
                                <i class="bi bi-arrow-left"></i> กลับรายการ
                            </a>
                            <button name="update" type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0">
                                <i class="bi bi-cloud-upload-fill me-2"></i> อัปเดตข้อมูล
                            </button>
                            <div class="input-group input-group-sm" style="width: 150px;">
                                <span
                                    class="input-group-text bg-dark border-secondary text-gold small fw-bold">NO.</span>
                                <input type="text" name="function_code"
                                    class="form-control border-secondary bg-light fw-bold text-center"
                                    value="<?php echo htmlspecialchars($data['function_code']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- --- [NEW] Draft Management Bar --- -->
            <?php if ($project_id > 0): ?>
            <div class="bg-light border-bottom px-4 py-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <span class="small fw-bold text-secondary"><i class="bi bi-layers-half me-1"></i> รายการ Draft:</span>
                    <div class="d-flex gap-2">
                        <?php foreach ($all_drafts as $draft): ?>
                            <?php 
                                $is_current = ($draft['id'] == $id);
                                $btn_class = $is_current ? 'btn-dark' : 'btn-outline-dark';
                                $approved_icon = ($draft['is_approved'] == 1) ? '<i class="bi bi-patch-check-fill text-info ms-1"></i>' : '';
                            ?>
                            <a href="edit.php?id=<?= $draft['id'] ?>" class="btn btn-xs <?= $btn_class ?> px-2 py-1 rounded-pill small" style="font-size: 11px;">
                                <?= htmlspecialchars($draft['draft_name']) ?> <?= $approved_icon ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php
                    $compare_id = 0;
                    foreach ($all_drafts as $d) {
                        if ($d['is_approved'] == 1 && $d['id'] != $id) {
                            $compare_id = $d['id'];
                            break;
                        }
                    }
                    if (!$compare_id) {
                        $prev = null;
                        foreach ($all_drafts as $d) {
                            if ($d['id'] == $id) break;
                            $prev = $d;
                        }
                        if ($prev) $compare_id = $prev['id'];
                    }
                    ?>
                    <?php if ($compare_id): ?>
                    <a href="print_changes.php?id=<?= $id ?>&compare_id=<?= $compare_id ?>" target="_blank" class="btn btn-outline-info btn-xs px-3 py-1 rounded-pill small" style="font-size: 11px;">
                        <i class="bi bi-file-earmark-diff me-1"></i> พิมพ์รายการที่เปลี่ยนแปลง
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-success btn-xs px-3 py-1 rounded-pill small" style="font-size: 11px;" onclick="duplicateDraft(<?= $id ?>)">
                        <i class="bi bi-plus-circle me-1"></i> คัดลอกเป็น Draft ใหม่
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <!-- --------------------------------- -->

            <!-- Status Bar -->
            <div class="bg-white border-bottom px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="small fw-bold text-secondary"><i class="bi bi-activity me-1"></i> สถานะปัจจุบัน:</span>
                    <span class="badge <?= $status_info['class'] ?> rounded-pill px-3 py-2">
                        <i class="bi <?= $status_info['icon'] ?> me-1"></i> <?= $status_text ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($has_rollback && $current_status !== $prev_status): ?>
                    <button type="button" id="rollbackStatusBtn" class="btn btn-outline-warning btn-sm px-3" data-id="<?= $id ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> ย้อนกลับสถานะ
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <!-- ----------------- -->

            <div class="card-body p-4 p-lg-5">
                <h5 class="section-title mb-4"><i class="bi bi-person-lines-fill"></i> 1. ข้อมูลการจองทั่วไป</h5>
                <!-- Row: Company + Customer + Event Details (3-column) -->
                <div class="row g-3 mb-4">
                    <!-- Company -->
                    <div class="col-lg-3">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-building me-1 text-primary"></i> เลือกโรงแรม
                            </label>
                            <select name="company_id" class="form-select border-0 bg-light mb-2"
                                id="company_select"
                                onchange="updateCompanyLogo(this); filterRooms(this.value);" required
                                style="border-radius: 10px; height: 38px; font-size: 0.85rem;">
                                <option value="">-- เลือกโรงแรม --</option>
                                <?php
                                $current_logo = 'assets/img/default-company.png';
                                if ($res_companies && $res_companies->num_rows > 0):
                                    $res_companies->data_seek(0);
                                    while ($row = $res_companies->fetch_assoc()):
                                        $selected = ($row['company_id'] == $data['company_id']) ? 'selected' : '';
                                        $logo_path = !empty($row['company_logo']) ? $row['company_logo'] : 'assets/img/default-company.png';
                                        if ($selected) $current_logo = $logo_path;
                                ?>
                                <option value="<?= $row['company_id']; ?>" data-logo="<?= $logo_path; ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($row['company_name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <div class="company-logo-preview border rounded-3 bg-light d-flex align-items-center justify-content-center mx-auto"
                                style="width: 70px; height: 70px; overflow: hidden;">
                                <img id="companyLogo" src="<?= $current_logo; ?>" class="img-fluid p-2" alt="Company Logo">
                            </div>
                        </div>
                    </div>

                    <!-- Customer -->
                    <div class="col-lg-4">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-person-lines-fill me-1 text-primary"></i> ข้อมูลลูกค้า
                            </label>
                            <input type="hidden" name="customer_id" id="customer_id_hidden" value="<?= $data['customer_id'] ?>">
                            <div class="mb-2">
                                <select id="customer_selector"
                                    class="form-select border-0 bg-light select2-ajax-customer"
                                    style="border-radius: 10px; height: 38px; font-size: 0.85rem;">
                                    <?php if ($data['customer_id']): 
                                        $c_stmt = $conn->prepare("SELECT cust_name FROM customers WHERE id = ?");
                                        $c_stmt->bind_param("i", $data['customer_id']);
                                        $c_stmt->execute();
                                        $c_name = $c_stmt->get_result()->fetch_assoc()['cust_name'] ?? '-- ค้นหา/เลือกลูกค้าเดิม --';
                                    ?>
                                        <option value="<?= $data['customer_id'] ?>" selected><?= htmlspecialchars($c_name) ?></option>
                                    <?php else: ?>
                                        <option value="">-- พิมพ์เพื่อค้นหาลูกค้า --</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="text" id="booking_name" name="booking_name"
                                        class="form-control border-0 bg-light rounded-3" placeholder="ชื่อ-นามสกุล" required
                                        value="<?= htmlspecialchars($data['booking_name']) ?>"
                                        style="height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-6">
                                    <input type="text" id="customer_phone" name="phone"
                                        class="form-control border-0 bg-light rounded-3" placeholder="เบอร์โทร"
                                        value="<?= htmlspecialchars($data['phone']) ?>"
                                        style="height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-12 mt-1">
                                    <textarea id="customer_address" name="organization"
                                        class="form-control border-0 bg-light rounded-3" placeholder="ที่อยู่ลูกค้า..."
                                        rows="2" style="font-size: 0.8rem; resize: none;"><?= htmlspecialchars($data['organization']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Event Details -->
                    <div class="col-lg-5">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-info-circle me-1 text-primary"></i> รายละเอียดการจอง
                            </label>
                            <div class="row g-1">
                                <div class="col-7">
                                    <input name="function_name" class="form-control border-0 bg-light"
                                        placeholder="ชื่องาน" required
                                        value="<?= htmlspecialchars($data['function_name']) ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-5">
                                    <select name="function_type_id" class="form-select border-0 bg-light" required
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                        <option value="" disabled>-- เลือกประเภท --</option>
                                        <?php 
                                        $res_types->data_seek(0);
                                        while ($t = $res_types->fetch_assoc()): 
                                            $selected_type = ($t['id'] == $data['function_type_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $t['id'] ?>" <?= $selected_type ?>>
                                                <?= htmlspecialchars($t['type_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-6 mt-1">
                                    <label class="small text-muted mb-0" style="font-size: 0.65rem;">เริ่มงาน</label>
                                    <input type="datetime-local" name="start_time"
                                        class="form-control border-0 bg-light" required
                                        value="<?= (!empty($data['start_time']) && $data['start_time'] != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($data['start_time'])) : '' ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-6 mt-1">
                                    <label class="small text-muted mb-0" style="font-size: 0.65rem;">สิ้นสุด</label>
                                    <input type="datetime-local" name="end_time"
                                        class="form-control border-0 bg-light" required
                                        value="<?= (!empty($data['end_time']) && $data['end_time'] != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($data['end_time'])) : '' ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Room Selection (full width) -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="p-3 rounded-4 bg-white border">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i> เลือกห้องประชุม (Select Venue)
                            </label>
                            <div class="row g-2" id="roomContainer">
                                <?php if ($res_rooms && $res_rooms->num_rows > 0):
                                    $res_rooms->data_seek(0);
                                    while ($r = $res_rooms->fetch_assoc()):
                                        $is_premium = ($r['floor'] > 5);
                                        $is_selected = ($r['id'] == $data['room_id']);
                                ?>
                                    <div class="col-md-4">
                                        <div class="room-card p-3 rounded-4 border h-100 <?= $is_selected ? 'selected' : 'bg-white' ?>"
                                            onclick="selectRoom(this, '<?= $r['id'] ?>')">
                                            <input type="radio" name="room_id" value="<?= $r['id'] ?>" class="d-none"
                                                <?= $is_selected ? 'checked' : '' ?>>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge <?= $is_premium ? 'bg-warning' : 'bg-primary' ?> bg-opacity-10 <?= $is_premium ? 'text-warning' : 'text-primary' ?> rounded-pill">
                                                    Floor <?= $r['floor'] ?>
                                                </span>
                                                <i class="bi bi-check-circle-fill check-icon text-primary <?= $is_selected ? '' : 'd-none' ?>"></i>
                                            </div>
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($r['room_name']) ?></h6>
                                            <p class="text-muted small mb-0">Max: 100 ท่าน</p>
                                        </div>
                                    </div>
                                <?php endwhile; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Financial + Tracking (compact) + File Attachments -->
                <div class="row g-2 mb-4">
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 h-100">
                            <label class="small fw-bold text-primary mb-0" style="font-size: 0.65rem;">Draft Name</label>
                            <input name="draft_name"
                                class="form-control border-0 bg-transparent fw-bold text-primary p-0 fs-6"
                                placeholder="Draft V1"
                                value="<?= htmlspecialchars($data['draft_name'] ?? 'Draft V1') ?>"
                                style="height: 32px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-secondary bg-opacity-10 h-100">
                            <label class="small fw-bold text-secondary mb-0" style="font-size: 0.65rem;">Booking No.</label>
                            <input name="booking_room"
                                class="form-control border-0 bg-transparent fw-bold text-secondary p-0 fs-6"
                                placeholder="BK-XXXX"
                                value="<?= htmlspecialchars($data['booking_room']) ?>"
                                style="height: 32px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-info bg-opacity-10 h-100">
                            <label class="small fw-bold text-info mb-0" style="font-size: 0.65rem;">จำนวน (PAX)</label>
                            <div class="input-group">
                                <input type="number" name="pax"
                                    class="form-control border-0 bg-transparent fw-bold text-info p-0 fs-6"
                                    placeholder="0" value="<?= $data['pax'] ?>" style="height: 32px;">
                                <span class="input-group-text border-0 bg-transparent text-info p-0 small">คน</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 h-100">
                            <label class="small fw-bold text-success mb-0" style="font-size: 0.65rem;">มัดจำ (Deposit)</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-transparent text-success fw-bold p-0">฿</span>
                                <input type="number" step="0.01" name="deposit"
                                    class="form-control border-0 bg-transparent fw-bold text-success p-0 fs-6"
                                    placeholder="0.00" value="<?= $data['deposit'] ?>" style="height: 32px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-secondary bg-opacity-10 h-100">
                            <label class="small fw-bold text-secondary mb-0" style="font-size: 0.65rem;">มูลค่างานทั้งหมด</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-transparent text-secondary fw-bold p-0">฿</span>
                                <input type="number" step="0.01" name="total_amount"
                                    class="form-control border-0 bg-transparent fw-bold text-secondary p-0 fs-6"
                                    placeholder="0.00"
                                    value="<?= isset($data['total_amount']) ? number_format($data['total_amount'], 2, '.', '') : '0.00' ?>"
                                    style="height: 32px;">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-secondary bg-opacity-10 h-100">
                            <label class="small fw-bold text-secondary mb-0" style="font-size: 0.65rem;"><i class="bi bi-paperclip"></i> ไฟล์แนบ</label>
                            <div class="row g-1">
                                <?php 
                                $file_colors = ['secondary', 'warning', 'danger']; 
                                for($i=1; $i<=3; $i++): 
                                    $color = $file_colors[$i-1];
                                    $file_path = $data['file_attachment'.$i];
                                    $file_name = $file_path ? basename($file_path) : '';
                                ?>
                                    <div class="col-md-4">
                                        <div class="p-1 rounded-2 bg-white border border-<?=$color?> border-opacity-25 h-100">
                                            <label class="small fw-bold text-<?=$color?> mb-1 d-block" style="font-size: 0.6rem;">
                                                <i class="bi bi-paperclip"></i> แนบ <?=$i?>
                                            </label>
                                            <?php if($file_path): ?>
                                                <div id="file_display_<?=$i?>"
                                                    class="d-flex align-items-center justify-content-between bg-white p-1 rounded-2 mb-1"
                                                    style="font-size: 9px;">
                                                    <div class="text-truncate me-1" style="max-width: 70px;">
                                                        <a href="<?=$file_path?>" target="_blank"
                                                            class="text-decoration-none text-dark">
                                                            <i class="bi bi-file-earmark-check text-<?=$color?>"></i>
                                                            <?=$file_name?>
                                                        </a>
                                                    </div>
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm py-0 px-1 flex-shrink-0"
                                                        style="font-size: 8px; border-radius: 4px; line-height: 1.2;"
                                                        onclick="if(confirm('ลบไฟล์เดิม?')){ 
                                                            document.getElementById('file_display_<?=$i?>').style.setProperty('display', 'none', 'important'); 
                                                            document.getElementById('delete_flag_<?=$i?>').value = '1'; 
                                                        }">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="file_attachment<?=$i?>"
                                                class="form-control form-control-sm border-0 bg-white bg-opacity-50 text-<?=$color?>"
                                                style="font-size: 10px; height: 28px;">
                                            <input type="hidden" name="old_file_<?=$i?>" value="<?=$file_path?>">
                                            <input type="hidden" name="delete_file_<?=$i?>" id="delete_flag_<?=$i?>" value="0">
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-12 mb-4">
                        <h5 class="section-title mb-4"><i class="bi bi-calendar3"></i> 2. ตารางกำหนดการ (Schedule)</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle" id="scheduleTable">
                                <thead class="small text-center text-secondary">
                                    <tr>
                                        <th style="width: 20%;">วันที่</th>
                                        <th style="width: 20%;">เวลา</th>
                                        <th>รายละเอียด</th>
                                        <th style="width: 15%;">จำนวน</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($schedules->num_rows > 0):
                                        while ($s = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="date" name="schedule_date[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                value="<?php echo $s['schedule_date']; ?>"></td>
                                        <td><input type="text" name="schedule_hour[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                value="<?php echo $s['schedule_hour']; ?>"></td>
                                        <td><textarea name="schedule_function[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                rows="2"><?php echo $s['schedule_function']; ?></textarea></td>
                                        <td><input type="number" name="schedule_guarantee[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                value="<?php echo $s['schedule_guarantee']; ?>"></td>
                                        <td><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addScheduleRow()"><i
                                    class="bi bi-plus-lg"></i> เพิ่มกำหนดการ</button>
                        </div>

                        <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. รายการเบรก</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle" id="kitchenTable" style="table-layout: fixed; width: 100%;">
    <thead>
        <tr>
            <th style="width: 130px; font-size: 11px;" class="text-center text-secondary">วันที่</th>
            <th style="width: 140px; font-size: 11px;" class="text-center text-secondary">ประเภทเบรก</th>
            <th style="font-size: 11px;" class="text-center text-secondary">รายการรายละเอียด</th>
            <th style="width: 70px; font-size: 11px;" class="text-center text-secondary">จำนวน (PAX)</th>
            <th style="width: 90px; font-size: 11px;" class="text-center text-secondary">ราคาขาย/หน่วย</th>
            <th style="width: 90px; font-size: 11px;" class="text-center text-secondary">ราคาทุน/หน่วย</th>
            <th style="width: 100px; font-size: 11px;" class="text-center text-secondary">ยอดรวม</th>
            <th style="width: 45px;"></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($kitchens->num_rows > 0):
            while ($k = $kitchens->fetch_assoc()):
                $k_qty = (float)($k['k_qty'] ?? 0);
                $k_price = (float)($k['k_price'] ?? 0);
                $k_total = $k_qty * $k_price;
            ?>
        <tr>
            <td style="width: 130px;">
                <input type="date" name="k_date[]"
                    class="form-control form-control-sm border-0 bg-light"
                    value="<?php echo $k['k_date']; ?>">
            </td>

            <td style="width: 140px;">
                <select name="k_type_id[]"
                    class="form-select form-select-sm border-0 bg-light">
                    <option value="">-- เลือกประเภท --</option>
                    <?php
                    if ($res_breaks && $res_breaks->num_rows > 0):
                        $res_breaks->data_seek(0);
                        while ($b = $res_breaks->fetch_assoc()):
                            $selected = (isset($k['k_type_id']) && $k['k_type_id'] == $b['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= $b['id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($b['type_name']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </td>

            <td>
                <textarea name="k_item[]" 
                    class="form-control form-control-sm border-0 bg-light w-100" 
                    style="field-sizing: content; min-height: 2.2rem; resize: none; overflow:hidden;"
                    oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px';"
                ><?php echo $k['k_item']; ?></textarea>
            </td>

            <td style="width: 70px;">
                <input type="number" name="k_qty[]"
                    class="form-control form-control-sm border-0 bg-light text-center kitchen-qty"
                    value="<?php echo $k['k_qty']; ?>"
                    oninput="updateKitchenRowTotal(this)">
            </td>

            <td style="width: 90px;">
                <input type="number" name="k_price[]"
                    class="form-control form-control-sm border-0 bg-light text-end kitchen-price"
                    placeholder="0.00" step="0.01"
                    value="<?php echo number_format($k['k_price'] ?? 0, 2, '.', ''); ?>"
                    oninput="updateKitchenRowTotal(this)">
            </td>

            <td style="width: 90px;">
                <input type="number" name="k_cost[]"
                    class="form-control form-control-sm border-0 bg-light text-end kitchen-cost"
                    placeholder="0.00" step="0.01"
                    value="<?php echo number_format($k['k_cost'] ?? 0, 2, '.', ''); ?>">
            </td>

            <td style="width: 100px;" class="text-end fw-bold kitchen-row-total"><?php echo number_format($k_total, 2); ?></td>

            <td style="width: 45px;" class="text-center">
                <button type="button" class="btn text-danger btn-sm border-0"
                    onclick="removeRow(this)"><i class="bi bi-dash-circle fs-5"></i></button>
            </td>
        </tr>
        <?php endwhile; endif; ?>
    </tbody>
    <tfoot class="table-light">
        <?php
        $kitchens->data_seek(0);
        $kitchen_grand = 0;
        while ($k = $kitchens->fetch_assoc()):
            $kitchen_grand += (float)($k['k_qty'] ?? 0) * (float)($k['k_price'] ?? 0);
        endwhile;
        ?>
        <tr>
            <th colspan="7" class="text-end fw-bold">รวมทั้งหมด (Grand Total)</th>
            <th class="text-end fw-bold kitchen-grand-total"><?php echo number_format($kitchen_grand, 2); ?></th>
        </tr>
    </tfoot>
</table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addKitchenRow()"><i
                                    class="bi bi-plus-lg"></i> เพิ่มรายการครัว</button>
                        </div>
                        <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2"
                            rows="3"><?php echo htmlspecialchars($data['main_kitchen_remark'] ?? ''); ?></textarea>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 4. รายละเอียดเมนูอาหารและเครื่องดื่ม
                </h5>
                <div class="table-responsive mb-5">
                    <table class="table table-sm table-hover align-middle" id="menuTable" style="table-layout: fixed; width: 100%;">
    <thead>
        <tr>
            <th style="width: 130px; font-size: 11px;" class="text-center text-secondary">เวลา</th>
            <th style="width: 150px; font-size: 11px;" class="text-center text-secondary">ประเภทเมนู</th>
            <th style="font-size: 11px;" class="text-center text-secondary">รายละเอียด</th>
            <th style="width: 70px; font-size: 11px;" class="text-center text-secondary">จำนวน</th>
            <th style="width: 90px; font-size: 11px;" class="text-center text-secondary">ราคาขาย/หน่วย</th>
            <th style="width: 90px; font-size: 11px;" class="text-center text-secondary">ราคาทุน/หน่วย</th>
            <th style="width: 100px; font-size: 11px;" class="text-center text-secondary">ยอดรวม</th>
            <th style="width: 45px;"></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($menus->num_rows > 0):
            while ($m = $menus->fetch_assoc()):
                $menu_qty = (float)($m['menu_qty'] ?? 0);
                $menu_price = (float)($m['menu_price'] ?? 0);
                $menu_total = $menu_qty * $menu_price;
            ?>
        <tr>
            <td style="width: 130px;">
                <input type="date" name="menu_time[]" class="form-control form-control-sm border-0 bg-light"
                    value="<?php echo $m['menu_time']; ?>">
            </td>

            <td style="width: 150px;">
                <?php
                $current_menu_val = $m['menu_set_id'] ?? '';
                $current_menu_name = '';
                foreach ($menu_types_array as $mt) { if ($mt['id'] == $current_menu_val) { $current_menu_name = $mt['type_name']; break; } }
                ?>
                <input type="hidden" name="menu_set_id[]" class="menu-type-id" value="<?= $current_menu_val ?>">
                <button type="button" class="btn-menu-type-picker" onclick="openTemplateModalForRow(this, 'template-menu', 'onMenuTypeSelect')"><i class="bi bi-grid-3x3-gap me-1"></i>เลือก</button>
                <span class="menu-type-label <?= $current_menu_val ? 'fw-semibold text-dark' : 'text-muted' ?>"><?= htmlspecialchars($current_menu_name ?: 'ยังไม่ได้เลือก') ?></span>
            </td>

            <td>
                <textarea name="menu_detail[]" class="form-control form-control-sm border-0 bg-light w-100"
                    rows="1"
                    style="field-sizing: content; min-height: 2.2rem; resize: none; overflow:hidden;"
                    oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px';"
                ><?php echo htmlspecialchars($m['menu_detail'] ?? ''); ?></textarea>
            </td>

            <td style="width: 70px;">
                <input type="number" name="menu_qty[]" class="form-control form-control-sm border-0 bg-light text-center menu-qty" 
                    placeholder="จำนวน"
                    value="<?php echo $m['menu_qty']; ?>"
                    oninput="updateMenuRowTotal(this)">
            </td>

            <td style="width: 90px;">
                <input type="number" step="0.01" name="menu_price[]" class="form-control form-control-sm border-0 bg-light text-end menu-price" 
                    placeholder="ราคา"
                    value="<?php echo $m['menu_price']; ?>"
                    oninput="updateMenuRowTotal(this)">
            </td>

            <td style="width: 90px;">
                <input type="number" step="0.01" name="menu_cost[]" class="form-control form-control-sm border-0 bg-light text-end menu-cost"
                    placeholder="ทุน"
                    value="<?php echo number_format($m['menu_cost'] ?? 0, 2, '.', ''); ?>">
            </td>

            <td style="width: 100px;" class="text-end fw-bold menu-row-total"><?php echo number_format($menu_total, 2); ?></td>

            <td style="width: 45px;" class="text-center">
                <button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)">
                    <i class="bi bi-dash-circle fs-5"></i>
                </button>
            </td>
        </tr>
        <?php endwhile; endif; ?>
    </tbody>
    <tfoot class="table-light">
        <?php
        $menus->data_seek(0);
        $menu_grand = 0;
        while ($m = $menus->fetch_assoc()):
            $menu_grand += (float)($m['menu_qty'] ?? 0) * (float)($m['menu_price'] ?? 0);
        endwhile;
        ?>
        <tr>
            <th colspan="7" class="text-end fw-bold">รวมทั้งหมด (Grand Total)</th>
            <th class="text-end fw-bold menu-grand-total"><?php echo number_format($menu_grand, 2); ?></th>
        </tr>
    </tfoot>
</table>
                    <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addMenuRow()"><i
                            class="bi bi-plus-lg"></i> เพิ่มรายการอาหาร</button>
                </div>

                <div class="row mb-5">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <div class="bg-sidebar p-4 rounded-4 h-100">
                            <h5 class="section-title mb-4"><i class="bi bi-building"></i> 5. รูปแบบการจัดงาน (SET-UP)</h5>
                            <div class="mb-0">
                                <label class="fw-bold small text-muted">การจัดงานเลี้ยง:</label>
                                <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                    rows="6"><?php echo htmlspecialchars($data['banquet_style']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-sidebar p-4 rounded-4 h-100">
                            <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 6. ระบบวิศวกรรม (TECHNICAL)</h5>
                            <div class="mb-4">
                                <label class="fw-bold small text-muted">งานช่างและภาพเสียง:</label>
                                <textarea name="equipment" class="form-control form-control-sm bg-white"
                                    rows="5"><?php echo htmlspecialchars($data['equipment']); ?></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="fw-bold small text-muted">หมายเหตุเพิ่มเติม:</label>
                                <textarea name="remark" class="form-control form-control-sm bg-white"
                                    rows="2"><?php echo htmlspecialchars($data['remark']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-palette-fill"></i> 7. การตกแต่งและการดูแลทำความสะอาด</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white h-100">
                            <label class="fw-bold small text-muted mb-3">รายละเอียดฉากหลังและป้าย:</label>
                            <textarea name="backdrop_detail" class="form-control form-control-sm mb-4"
                                rows="3"><?php echo htmlspecialchars($data['backdrop_detail'] ?? ''); ?></textarea>

                            <div class="p-3 border-dashed text-center bg-light">
                                <label class="small d-block mb-2">รูปภาพปัจจุบัน:</label>
                                <div id="imagePreviewContainer"
                                    class="<?php echo !empty($data['backdrop_img']) ? '' : 'd-none'; ?>">
                                    <img id="imagePreview" src="<?php echo htmlspecialchars($data['backdrop_img'] ?: '#'); ?>"
                                        class="img-thumbnail mb-2" style="max-height: 150px;">
                                </div>
                                <input type="file" name="backdrop_img" class="form-control form-control-sm"
                                    accept="image/*" onchange="previewImage(this)">
                                <input type="hidden" name="old_backdrop_img"
                                    value="<?php echo htmlspecialchars($data['backdrop_img']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white  h-100">
                            <label class="fw-bold small text-muted mb-3">พนักงานทำความสะอาดและพนักงานจัดดอกไม้:</label>
                            <textarea name="hk_florist_detail" class="form-control form-control-sm"
                                rows="8"><?php echo htmlspecialchars($data['hk_florist_detail']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const container = document.getElementById('imagePreviewContainer');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            container.classList.remove('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function updateCompanyLogo(select) {
    const logoImg = document.getElementById('companyLogo');
    const logoPath = select.options[select.selectedIndex].getAttribute('data-logo');
    logoImg.src = logoPath || 'assets/img/default-company.png';
}

function addScheduleRow() {
    const table = document.querySelector("#scheduleTable tbody");
    const row = table.insertRow();
    row.innerHTML =
        `<td><input type="date" name="schedule_date[]" class="form-control form-control-sm border-0 bg-light"></td><td><input type="text" name="schedule_hour[]" class="form-control form-control-sm border-0 bg-light"></td><td><textarea name="schedule_function[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td><td><input type="number" name="schedule_guarantee[]" class="form-control form-control-sm border-0 bg-light"></td><td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>`;
}

function addKitchenRow() {
    const table = document.querySelector("#kitchenTable tbody");
    const row = table.insertRow();
    row.className = "align-top";
    row.innerHTML = `
        <td width="12%"><input type="date" name="k_date[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td width="18%">
            <select name="k_type_id[]" class="form-select form-select-sm border-0 bg-light" 
                    onchange="fetchBreakMenu(this)"> <option value="">-- เลือกประเภท --</option>
                <?php
                if ($res_breaks) {
                    $res_breaks->data_seek(0);
                    while ($b = $res_breaks->fetch_assoc()) {
                        echo '<option value="' . $b['id'] . '">' . htmlspecialchars($b['type_name']) . '</option>';
                    }
                }
                ?>
            </select>
        </td>
        <td>
            <textarea name="k_item[]" 
                      class="form-control form-control-sm border-0 bg-light break-menu-input"  /* 🛠️ เพิ่มคลาสนี้ */
                      rows="2" placeholder="รายการ..."></textarea>
        </td>
        <td width="10%"><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center kitchen-qty" placeholder="0" oninput="updateKitchenRowTotal(this)"></td>
        <td width="12%"><input type="number" name="k_price[]" class="form-control form-control-sm border-0 bg-light text-end kitchen-price" placeholder="0.00" step="0.01" oninput="updateKitchenRowTotal(this)"></td>
        <td width="12%"><input type="number" name="k_cost[]" class="form-control form-control-sm border-0 bg-light text-end kitchen-cost" placeholder="0.00" step="0.01"></td>
        <td class="text-end fw-bold kitchen-row-total">0.00</td>
        <td width="5%"><button type="button" class="btn text-danger btn-sm border-0"
                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
}

const menuTypeOptions = <?= json_encode($menu_types_array) ?>;

function addMenuRow() {
    const table = document.querySelector("#menuTable tbody");
    const row = table.insertRow();
    row.className = "align-top";
    row.innerHTML = `
        <td style="width: 130px;"><input type="date" name="menu_time[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td style="width: 150px;">
            <input type="hidden" name="menu_set_id[]" class="menu-type-id" value="">
            <button type="button" class="btn-menu-type-picker" onclick="openTemplateModalForRow(this, 'template-menu', 'onMenuTypeSelect')"><i class="bi bi-grid-3x3-gap me-1"></i>เลือก</button>
            <span class="menu-type-label text-muted">ยังไม่ได้เลือก</span>
        </td>
        <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0 bg-light w-100" rows="1" style="field-sizing: content; min-height: 2.2rem; resize: none; overflow:hidden;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px';"></textarea></td>
        <td style="width: 70px;"><input type="number" name="menu_qty[]" class="form-control form-control-sm border-0 bg-light text-center menu-qty" placeholder="จำนวน" oninput="updateMenuRowTotal(this)"></td>
        <td style="width: 90px;"><input type="number" step="0.01" name="menu_price[]" class="form-control form-control-sm border-0 bg-light text-end menu-price" placeholder="ราคา" oninput="updateMenuRowTotal(this)"></td>
        <td style="width: 90px;"><input type="number" step="0.01" name="menu_cost[]" class="form-control form-control-sm border-0 bg-light text-end menu-cost" placeholder="ทุน"></td>
        <td style="width: 100px;" class="text-end fw-bold menu-row-total">0.00</td>
        <td style="width: 45px;" class="text-center"><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle fs-5"></i></button></td>
    `;
}

function removeRow(btn) {
    btn.closest("tr").remove();
    if (typeof updateKitchenGrandTotal === 'function') updateKitchenGrandTotal();
    if (typeof updateMenuGrandTotal === 'function') updateMenuGrandTotal();
}

function updateKitchenRowTotal(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.kitchen-qty').value) || 0;
    const price = parseFloat(row.querySelector('.kitchen-price').value) || 0;
    const total = qty * price;
    const totalCell = row.querySelector('.kitchen-row-total');
    if (totalCell) totalCell.textContent = total.toFixed(2);
    updateKitchenGrandTotal();
}

function updateKitchenGrandTotal() {
    const table = document.getElementById('kitchenTable');
    let grandTotal = 0;
    table.querySelectorAll('.kitchen-row-total').forEach(function(el) {
        grandTotal += parseFloat(el.textContent) || 0;
    });
    const footer = table.querySelector('.kitchen-grand-total');
    if (footer) footer.textContent = grandTotal.toFixed(2);
}

function updateMenuRowTotal(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.menu-qty').value) || 0;
    const price = parseFloat(row.querySelector('.menu-price').value) || 0;
    const total = qty * price;
    const totalCell = row.querySelector('.menu-row-total');
    if (totalCell) totalCell.textContent = total.toFixed(2);
    updateMenuGrandTotal();
}

function updateMenuGrandTotal() {
    const table = document.getElementById('menuTable');
    let grandTotal = 0;
    table.querySelectorAll('.menu-row-total').forEach(function(el) {
        grandTotal += parseFloat(el.textContent) || 0;
    });
    const footer = table.querySelector('.menu-grand-total');
    if (footer) footer.textContent = grandTotal.toFixed(2);
}

function selectRoom(element, roomId) {
    // 1. ล้างทุกอย่างออกจากทุก Card (เอาให้เกลี้ยง!)
    document.querySelectorAll('.room-card').forEach(card => {
        // ลบ Class ที่เป็นตัวกำหนดสีออกให้หมด
        card.classList.remove('selected', 'bg-primary', 'bg-opacity-10', 'border-primary');
        // คืนค่าพื้นหลังเป็นสีขาว
        card.classList.add('bg-white');

        // ซ่อนไอคอนเช็คถูกของอันอื่นด้วย
        const icon = card.querySelector('.check-icon');
        if (icon) icon.classList.add('d-none');
    });

    // 2. ใส่สีฟ้าให้อันที่เพิ่งคลิก
    element.classList.add('selected');
    element.classList.remove('bg-white'); // ต้องเอาสีขาวออกด้วย สีฟ้าถึงจะชัด

    // แสดงไอคอนเช็คถูกของอันนี้
    const currentIcon = element.querySelector('.check-icon');
    if (currentIcon) currentIcon.classList.remove('d-none');

    // 3. ติ๊ก Radio ตัวจริงที่ซ่อนอยู่
    const radio = element.querySelector('input[type="radio"]');
    radio.checked = true;

    console.log("จารเลือกห้อง ID:", roomId);
}

async function fetchBreakMenu(selectEl) {
    const row = selectEl.closest('tr');
    const textarea = row.querySelector('.break-menu-input');
    const typeId = selectEl.value;

    if (!typeId) return;
    textarea.placeholder = "กำลังดึงข้อมูล...";

    try {
        const response = await fetch(`api/get_menu_ajax.php?type_id=${typeId}`);
        const data = await response.text();
        textarea.value = data;
        // แถม: ปรับความสูง textarea ตามเนื้อหาอัตโนมัติ
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    } catch (error) {
        console.error("Error:", error);
        textarea.value = "1. ";
    }
}

// ฟังก์ชันดึงรายละเอียดเมนูหลัก
async function fetchMenuDetail(selectEl) {
    const row = selectEl.closest('tr');
    const textarea = row.querySelector('.menu-detail-input'); // มั่นใจว่าคลาสตรงกัน
    const setId = selectEl.value;

    if (!setId) return;
    textarea.placeholder = "กำลังดึงรายละเอียดเมนู...";

    try {
        // วิ่งไปหาไฟล์ AJAX สำหรับดึงรายละเอียดเมนูหลัก
        const response = await fetch(`api/get_menu_detail_ajax.php?set_id=${setId}`);
        const data = await response.text();
        textarea.value = data;

        // แถม: ปรับความสูง textarea ตามเนื้อหาอัตโนมัติ
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    } catch (error) {
        console.error("Fetch Menu Error:", error);
    }
}

// [NEW] ฟังก์ชันสำหรับ Duplicate Draft
function duplicateDraft(id) {
    if(!confirm('คุณต้องการคัดลอกข้อมูลรายการนี้เป็น Draft ใหม่ใช่หรือไม่?')) return;
    
    fetch('api/duplicate_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if(data.status === 'success') {
            let lineText = '';
            if (data.line_sent > 0) {
                lineText = '\n\n LINE แจ้งเตือนสำเร็จ ' + data.line_sent + ' คน';
            }
            if (data.line_failed > 0) {
                lineText += '\n LINE แจ้งเตือนไม่สำเร็จ ' + data.line_failed + ' คน';
            }
            Swal.fire({
                icon: 'success',
                title: 'คัดลอกสำเร็จ!',
                text: 'กำลังพาคุณไปยัง Draft ใหม่...' + lineText,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'edit.php?id=' + data.new_id;
            });
        } else {
            console.error('API Error:', data);
            alert('เกิดข้อผิดพลาดจาก Server: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ (Network Error). ลองเช็ค Console (F12)');
    });
}

/**
 * SECTION: CUSTOMER FETCHING
 */
function fillCustomerInfo(select) {
    const selectedOption = select.options[select.selectedIndex];
    const customerId = select.value; 
    const customerName = selectedOption.getAttribute('data-name');
    const customerPhone = selectedOption.getAttribute('data-phone');
    const customerAddress = selectedOption.getAttribute('data-address');

    document.getElementById('customer_id_hidden').value = customerId;
    document.getElementById('booking_name').value = customerName || '';
    document.getElementById('customer_phone').value = customerPhone || '';
    document.getElementById('customer_address').value = customerAddress || '';
}
</script>
<script>
function confirmRemoveFile(index) {
    if (confirm('ยืนยันที่จะนำไฟล์เดิมออกเพื่อเปลี่ยนไฟล์ใหม่หรือไม่?')) {
        // หา Element ที่โชว์ไฟล์เดิม
        const displayDiv = document.getElementById('file_display_' + index);
        const flagInput = document.getElementById('delete_flag_' + index);

        if (displayDiv && flagInput) {
            displayDiv.style.display = 'none'; // ซ่อนทันที
            flagInput.value = '1'; // เปลี่ยนค่าเป็น 1 เพื่อบอก PHP ให้ลบ
            console.log('File ' + index + ' marked for deletion');
        } else {
            alert('Error: ไม่พบ Element สำหรับลบไฟล์');
        }
    }
}
</script>
<script>
// แปลงข้อมูล PHP Array เป็น JS Object
const allRooms = <?php echo json_encode($all_rooms_data); ?>;
const selectedRoomId = "<?php echo intval($data['room_id']); ?>"; // ห้องที่เคยจองไว้เดิม

function filterRooms(companyId) {
    const container = document.getElementById('roomContainer');
    container.innerHTML = ''; // ล้างค่าเก่า

    if (!companyId) {
        container.innerHTML = '<div class="col-12 text-center py-4 text-muted">-- กรุณาเลือกโรงแรม --</div>';
        return;
    }

    // กรองเอาเฉพาะห้องที่มี company_id ตรงกับที่เลือก
    const filtered = allRooms.filter(room => room.company_id == companyId);

    if (filtered.length === 0) {
        container.innerHTML = '<div class="col-12 text-center py-4 text-muted">-- ไม่พบห้องประชุมในโรงแรมนี้ --</div>';
        return;
    }

    // วนลูปสร้าง HTML ของ Card ห้องประชุม
   filtered.forEach(room => {
    const isSelected = (room.id == selectedRoomId);
    
    // ดึงรายการวันที่จอง (ถ้ามีค่าจะเป็น "25/03, 28/03" ถ้าไม่มีจะเป็น null)
    const bookingList = room.booking_dates;

    const cardHtml = `
        <div class="col-md-4 mb-3">
            <div class="room-card p-3 rounded-4 border h-100 position-relative 
                ${isSelected ? 'selected border-primary bg-light shadow-sm' : 'bg-white'}"
                onclick="selectRoom(this, '${room.id}')" 
                style="cursor: pointer; transition: all 0.2s;">
                
                <input type="radio" name="room_id" value="${room.id}" 
                       class="d-none room-radio" ${isSelected ? 'checked' : ''}>

                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge ${room.floor > 5 ? 'bg-warning text-dark' : 'bg-primary text-white'} rounded-pill">
                        <i class="bi bi-layers me-1"></i> ชั้น: ${room.floor}
                    </span>
                    <i class="bi bi-check-circle-fill check-icon text-success ${isSelected ? '' : 'd-none'}"></i>
                </div>

                <h6 class="fw-bold mb-1">${room.room_name}</h6>
                
                <p class="text-muted small mb-1">
                    <i class="bi bi-aspect-ratio me-1"></i> ${parseFloat(room.total_sqm).toFixed(2)} ตร.ม.
                </p>

                <div class="mt-2">
                    ${bookingList ? `
                        <div class="d-flex flex-column gap-1">
                            <span class="text-danger fw-bold" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar-x-fill me-1"></i> วันที่มีใช้งานแล้ว:
                            </span>
                            <div class="p-2 rounded bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger fw-bold" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                ${bookingList}
                            </div>
                        </div>
                    ` : `
                        <div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25 text-success text-center small">
                            <i class="bi bi-calendar-check me-1"></i> ว่าง / พร้อมใช้งาน
                        </div>
                    `}
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', cardHtml);
});
}

// ฟังก์ชันตรวจสอบห้องว่างแบบ Real-time
function checkRoomAvailability() {
    const roomEl = document.querySelector('input[name="room_id"]:checked');
    if (!roomEl) return;
    const roomId = roomEl.value;
    const start = document.querySelector('input[name="start_time"]').value;
    const end = document.querySelector('input[name="end_time"]').value;
    const functionId = document.querySelector('input[name="function_id"]').value;
    const card = roomEl.closest('.room-card');
    if (!card || !start || !end) return;

    // หา div แสดงสถานะ (หรือสร้างใหม่)
    let statusDiv = card.querySelector('.room-status');
    if (!statusDiv) {
        statusDiv = document.createElement('div');
        statusDiv.className = 'room-status mt-2';
        card.appendChild(statusDiv);
    }
    statusDiv.innerHTML = '<div class="small text-muted text-center"><i class="bi bi-hourglass-split"></i> กำลังตรวจสอบ...</div>';

    fetch(`api/validate_room_booking.php?room_id=${roomId}&start=${start}&end=${end}&exclude_id=${functionId}`)
        .then(r => r.json())
        .then(result => {
            if (result.status === 'conflict') {
                const ev = result.events[0];
                statusDiv.innerHTML = `
                    <div class="p-2 rounded bg-danger bg-opacity-10 border border-danger text-danger" style="font-size: 0.8rem;">
                        <div class="fw-bold"><i class="bi bi-x-circle-fill me-1"></i> ไม่ว่างช่วงนี้</div>
                        <div class="small mt-1">${ev.function_name}</div>
                        <div class="small opacity-75">${ev.start_time} - ${ev.end_time}</div>
                    </div>
                `;
                card.classList.add('border-danger');
            } else {
                statusDiv.innerHTML = `
                    <div class="p-2 rounded bg-success bg-opacity-10 border border-success text-success text-center small">
                        <i class="bi bi-check-circle-fill me-1"></i> ว่าง / พร้อมใช้งาน
                    </div>
                `;
                card.classList.remove('border-danger');
            }
        })
        .catch(() => {
            statusDiv.innerHTML = '';
        });
}

// ฟังก์ชันคลิกเลือกห้อง
function selectRoom(card, roomId) {
    document.querySelectorAll('.room-card').forEach(c => {
        c.classList.remove('selected', 'border-primary', 'bg-light');
        c.classList.add('bg-white');
        c.querySelector('.check-icon').classList.add('d-none');
        c.querySelector('.room-radio').checked = false;
    });

    card.classList.add('selected', 'border-primary', 'bg-light');
    card.classList.remove('bg-white');
    card.querySelector('.check-icon').classList.remove('d-none');
    card.querySelector('.room-radio').checked = true;

    checkRoomAvailability();
}

// สั่งให้ทำงานทันทีตอนโหลดหน้า (เพื่อให้โชว์ห้องของโรงแรมเดิม)
document.addEventListener('DOMContentLoaded', function() {
    const currentComp = document.querySelector('select[name="company_id"]').value;
    if (currentComp) filterRooms(currentComp);

    // เช็คสถานะห้องอัตโนมัติเมื่อเปลี่ยนเวลาหรือห้อง
    document.querySelectorAll('input[name="start_time"], input[name="end_time"]').forEach(el => {
        el.addEventListener('change', checkRoomAvailability);
        el.addEventListener('input', checkRoomAvailability);
    });

    // ถ้ามีห้องถูกเลือกไว้แล้วและมีเวลา ให้ตรวจสอบสถานะทันที
    setTimeout(checkRoomAvailability, 500);

    // ป้องกันการ submit ถ้าห้องไม่ว่าง
    document.querySelector('form').addEventListener('submit', function(e) {
        const selCard = document.querySelector('input[name="room_id"]:checked')?.closest('.room-card');
        if (selCard && selCard.classList.contains('border-danger')) {
            e.preventDefault();
            alert('ห้องนี้มีงานในช่วงเวลาที่เลือก กรุณาเปลี่ยนเวลาหรือเลือกห้องอื่น');
        }
    });

    // --- Select2 AJAX Customer Search ---
    $('.select2-ajax-customer').select2({
        width: '100%',
        placeholder: '--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/search_customers.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data.results || [] };
            }
        },
        templateResult: function (row) {
            if (row.loading) return row.text;
            return $(`<div><strong>${row.text}</strong><br><small class="text-muted">${row.cust_phone || ''} ${row.cust_address ? '| ' + row.cust_address : ''}</small></div>`);
        },
        templateSelection: function (row) {
            return row.text || '--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---';
        }
    }).on('select2:select', function (e) {
        const data = e.params.data;
        document.getElementById('customer_id_hidden').value = data.id;
        document.getElementById('booking_name').value = data.cust_name || '';
        document.getElementById('customer_phone').value = data.cust_phone || '';
        document.getElementById('customer_address').value = data.cust_address || '';
    }).on('select2:clear', function () {
        document.getElementById('customer_id_hidden').value = '';
        document.getElementById('booking_name').value = '';
        document.getElementById('customer_phone').value = '';
        document.getElementById('customer_address').value = '';
    });
});
</script>

<!-- Rollback Status JS -->
<script>
$(document).on('click', '#rollbackStatusBtn', function() {
    const id = $(this).data('id');
    const btn = $(this);

    Swal.fire({
        title: 'ย้อนกลับสถานะ?',
        text: 'คุณต้องการย้อนกลับสถานะไปสถานะก่อนหน้านี้ใช่หรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ย้อนกลับ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/rollback_status.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'ย้อนกลับสถานะสำเร็จ!',
                            text: 'สถานะถูกเปลี่ยนเป็น "' + res.old_status + '"',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('ผิดพลาด!', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถติดต่อ Server ได้', 'error');
                }
            });
        }
    });
});
</script>

<script>
    let _mtmActiveRow = null;

    function openTemplateModalForRow(btn, mode, cb) {
        _mtmActiveRow = btn.closest('tr');
        openTemplateModal(mode, cb);
    }

    function onMenuTypeSelect(result) {
        if (!_mtmActiveRow) return;
        const row = _mtmActiveRow;
        const hidden = row.querySelector('.menu-type-id');
        const label = row.querySelector('.menu-type-label');
        const detail = row.querySelector('.menu-detail-input');
        const price = row.querySelector('.menu-price');
        const cost = row.querySelector('.menu-cost');

        if (hidden) hidden.value = result.type_id || result.id;
        if (label) {
            label.textContent = result.type_name || result.name;
            label.classList.remove('text-muted');
            label.classList.add('fw-semibold', 'text-dark');
        }
        if (detail && result.description) {
            detail.value = result.description;
            detail.style.height = 'auto';
            detail.style.height = detail.scrollHeight + 'px';
        }
        if (price && result.price !== undefined) price.value = parseFloat(result.price).toFixed(2);
        if (cost && result.cost !== undefined) cost.value = parseFloat(result.cost).toFixed(2);

        _mtmActiveRow = null;
    }
</script>


<?php include "includes/menu_type_modal.php"; ?>
<?php include "footer.php"; ?>