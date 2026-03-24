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

$current_user = trim($_SESSION['user_name'] ?? '');
$user_role = strtolower(trim($_SESSION['role'] ?? 'viewer'));

// 🛡️ ด่านที่ 1: เช็คสิทธิ์ (ตอนนี้ระบบรู้จัก $data และ $current_user แล้ว)
if ($user_role !== 'admin' && $user_role !== 'gm' && trim($data['created_by']) !== $current_user) {
    // ใช้ JavaScript Redirect เพื่อเลี่ยงปัญหา Headers already sent
    echo "<script>window.location.href='access_denied.php';</script>";
    exit();
}

// 3. ถ้าผ่านด่านค่อยโหลด Layout และทำส่วนที่เหลือ
require_once "header.php";
access_control('all_staff');



// 🛡️ ด่านที่ 2: เช็คสถานะอนุมัติ (Admin แก้ได้เสมอ)
if (isset($data['approve']) && $data['approve'] != 0 && $user_role !== 'admin') {
    echo "<script>
        alert('งานนี้ถูกอนุมัติแล้ว ห้ามแก้ไขครับ!');
        window.location.href='manage_banquet.php';
    </script>";
    exit;
}
// --- 🛡️ จบส่วนควบคุมสิทธิ์ ---

// 2. ดึงข้อมูลบริษัทสำหรับ Dropdown (ใช้แบบเดิมที่จารบอกว่าภาพขึ้น)
$query_companies = "SELECT id AS company_id, company_name, logo_path AS company_logo FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies);

// 3. ดึงข้อมูลจากตารางลูกทั้งหมด
$schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id ORDER BY id ASC");
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
$all_rooms_res = $conn->query("
    SELECT 
        r.*, 
        (SELECT f.end_time
         FROM functions f 
         WHERE f.room_id = r.id 
         AND f.approve = 1 
         AND f.end_time >= NOW() 
         ORDER BY f.end_time DESC LIMIT 1) as busy_until
    FROM meeting_rooms r
    WHERE r.status = 'active' 
    ORDER BY r.floor ASC, r.room_name ASC
");

$all_rooms_data = [];
while($row = $all_rooms_res->fetch_assoc()) {
    // เก็บเข้า array เพื่อส่งให้ json_encode ใน JS
    $all_rooms_data[] = $row;
}
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
                    <div style="width: 100%; max-width: 450px;">
                        <div class="d-flex align-items-center gap-2">
                            <button name="update" type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0">
                                <i class="bi bi-cloud-upload-fill me-2"></i> อัปเดตข้อมูล
                            </button>
                            <div class="input-group input-group-sm">
                                <span
                                    class="input-group-text bg-dark border-secondary text-gold small fw-bold">NO.</span>
                                <input type="text" name="function_code"
                                    class="form-control border-secondary bg-light fw-bold text-center"
                                    value="<?php echo $data['function_code']; ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-lg-5">
                <h5 class="section-title mb-4"><i class="bi bi-person-lines-fill"></i> 1. ข้อมูลการจองทั่วไป</h5>
                <div class="row mb-5">
                    <div class="col-lg-3">
                        <div class="mb-4 text-center">
                            <label class="small fw-bold text-secondary mb-3 d-block text-start">
                                <i class="bi bi-building me-1 text-primary"></i> เลือกโรงแรม
                            </label>
                            <select name="company_id" class="form-select border-0 bg-light mb-3"
                                onchange="updateCompanyLogo(this); filterRooms(this.value);" required
                                style="border-radius: 10px; height: 42px;">
                                <option value="">-- เลือกโรงแรม --</option>
                                <?php
                                $current_logo = 'assets/img/default-company.png'; // ค่า Default
                                if ($res_companies && $res_companies->num_rows > 0):
                                    $res_companies->data_seek(0);
                                    while ($row = $res_companies->fetch_assoc()):
                                        // เช็คข้อมูลเก่า: ถ้า id ตรงกับ $data['company_id'] ให้เลือกไว้
                                        $selected = ($row['company_id'] == $data['company_id']) ? 'selected' : '';
                                        $logo_path = !empty($row['company_logo']) ? $row['company_logo'] : 'assets/img/default-company.png';

                                        // ถ้าถูกเลือก ให้ดึงรูปมาเก็บในตัวแปรแสดงผลทันที
                                        if ($selected)
                                            $current_logo = $logo_path;
                                        ?>
                                <option value="<?= $row['company_id']; ?>" data-logo="<?= $logo_path; ?>"
                                    <?= $selected ?>>
                                    <?= htmlspecialchars($row['company_name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>

                            <div class="company-logo-preview border-0 rounded-3 bg-light d-flex align-items-center justify-content-center mx-auto mb-2"
                                style="width: 80px; height: 80px; overflow: hidden; border: 1px solid #eee !important;">
                                <img id="companyLogo" src="<?= $current_logo; ?>" class="img-fluid p-2"
                                    alt="Company Logo">
                            </div>
                        </div>

                        <hr class="opacity-10 mb-4">

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="small fw-bold text-secondary m-0">
                                    <i class="bi bi-person-lines-fill me-1 "></i> ข้อมูลลูกค้า
                                </label>
                            </div>

                            <div class="mb-3">
                                <input type="hidden" name="customer_id" id="customer_id_hidden"
                                    value="<?= $data['customer_id'] ?>">
                                <select id="customer_selector"
                                    class="form-select border-0  bg-opacity-10  fw-bold bg-light"
                                    onchange="fillCustomerInfo(this)"
                                    style="border-radius: 10px; height: 42px; font-size: 13px;">
                                    <option value="">-- ค้นหา/เลือกลูกค้าเดิม --</option>
                                    <?php
                                    if ($res_customers && $res_customers->num_rows > 0):
                                        $res_customers->data_seek(0);
                                        while ($c = $res_customers->fetch_assoc()):
                                            // 🛠️ แก้จุดนี้: ใช้ trim และเช็คว่า ID ตรงกันจริงๆ (ใช้ == เพื่อไม่ check type ที่เข้มงวดเกินไป)
                                            $is_selected_cust = (trim($c['id']) == trim($data['customer_id'])) ? 'selected' : '';
                                            ?>
                                    <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['cust_name']) ?>"
                                        data-phone="<?= htmlspecialchars($c['cust_phone']) ?>"
                                        data-address="<?= htmlspecialchars($c['cust_address']) ?>"
                                        <?= $is_selected_cust ?>> <?= htmlspecialchars($c['cust_name']) ?>
                                    </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label mb-1 text-muted"
                                    style="font-size: 11px;">ชื่อลูกค้า/ผู้จอง</label>
                                <input type="text" id="booking_name" name="booking_name"
                                    class="form-control border-0 bg-light rounded-3" placeholder="ชื่อ-นามสกุล" required
                                    style="height: 40px; font-size: 13px;"
                                    value="<?= htmlspecialchars($data['booking_name']) ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label mb-1 text-muted" style="font-size: 11px;">เบอร์โทรศัพท์</label>
                                <input type="text" id="customer_phone" name="phone"
                                    class="form-control border-0 bg-light rounded-3" placeholder="08x-xxx-xxxx"
                                    style="height: 40px; font-size: 13px;"
                                    value="<?= htmlspecialchars($data['phone']) ?>">
                            </div>

                            <div class="mb-0">
                                <label class="form-label mb-1 text-muted"
                                    style="font-size: 11px;">หน่วยงาน/ที่อยู่</label>
                                <textarea id="customer_address" name="organization"
                                    class="form-control border-0 bg-light rounded-3" placeholder="ที่อยู่ลูกค้า..."
                                    rows="3"
                                    style="font-size: 13px; resize: none;"><?= htmlspecialchars($data['organization']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="col-md-12 mb-4">
                            <label class="form-label small fw-bold text-secondary mb-3">
                                <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i> เลือกห้องประชุม (Select Venue)
                            </label>
                            <div class="row g-3 " id="roomContainer">
                                <?php if ($res_rooms && $res_rooms->num_rows > 0):
                $res_rooms->data_seek(0);
                while ($r = $res_rooms->fetch_assoc()):
                    $is_premium = ($r['floor'] > 5);
                    // 🛠️ เช็คว่าห้องนี้คือห้องที่เลือกไว้หรือไม่
                    $is_selected_room = ($r['id'] == $data['room_id']);
            ?>
                                <div class="col-md-4">
                                    <?php $is_selected = ($r['id'] == $data['room_id']); ?>

                                    <div class="room-card p-3 rounded-4 border h-100  <?= $is_selected ? 'selected' : 'bg-white' ?>"
                                        onclick="selectRoom(this, '<?= $r['id'] ?>')" style="">

                                        <input type="radio" name="room_id" value="<?= $r['id'] ?>" class="d-none"
                                            <?= $is_selected ? 'checked' : '' ?>>

                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span
                                                class="badge <?= $is_premium ? 'bg-warning' : 'bg-primary' ?> bg-opacity-10 <?= $is_premium ? 'text-warning' : 'text-primary' ?> rounded-pill">
                                                Floor <?= $r['floor'] ?>
                                            </span>
                                            <i
                                                class="bi bi-check-circle-fill check-icon text-primary <?= $is_selected ? '' : 'd-none' ?>"></i>
                                        </div>

                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($r['room_name']) ?></h6>
                                        <p class="text-muted small mb-0">Max: 100 ท่าน</p>
                                    </div>
                                </div>
                                <?php endwhile; endif; ?>
                            </div>
                        </div>

                        <div class="row">
                            <h6 class="fw-bold text-dark">
                                <i class="bi bi-info-circle me-2 text-primary"></i>รายละเอียดการจอง
                            </h6>

                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-secondary">ชื่องาน (Event Title)</label>
                                    <input name="function_name" class="form-control border-0 bg-light"
                                        placeholder="พิมพ์ชื่อโครงการหรืองานจัดเลี้ยง..." required
                                        style="border-radius: 10px; height: 42px;"
                                        value="<?= htmlspecialchars($data['function_name']) ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">ประเภทงาน</label>
                                    <select name="function_type_id" class="form-select border-0 bg-light" required
                                        style="border-radius: 10px; height: 42px;">
                                        <option value="" disabled>-- เลือกประเภท --</option>
                                        <?php 
                    $res_types->data_seek(0);
                    while ($t = $res_types->fetch_assoc()): 
                        $selected_type = ($t['id'] == $data['function_type_id']) ? 'selected' : '';
                    ?>
                                        <option value="<?= $t['id'] ?>" <?= $selected_type ?>>
                                            <?= htmlspecialchars($t['type_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="row ">
                                    <div class="col-md-6">
                                        <label
                                            class="form-label small fw-bold text-secondary">วันเวลาที่เริ่มงาน</label>
                                        <input type="datetime-local" name="start_time"
                                            class="form-control border-0 bg-light" required
                                            style="border-radius: 10px; height: 42px;"
                                            value="<?= (!empty($data['start_time']) && $data['start_time'] != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($data['start_time'])) : (isset($data['start_time']) ? 'ERROR_DATA_EMPTY' : 'ERROR_NO_FIELD') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label
                                            class="form-label small fw-bold text-secondary">วันเวลาที่สิ้นสุดงาน</label>
                                        <input type="datetime-local" name="end_time"
                                            class="form-control border-0 bg-light" required
                                            style="border-radius: 10px; height: 42px;"
                                            value="<?= (!empty($data['end_time']) && $data['end_time'] != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($data['end_time'])) : '' ?>">
                                    </div>
                                </div>
                            <div class="row g-2">
    <div class="col-md-2">
        <div class="p-3 rounded-4 bg-primary bg-opacity-10 h-100">
            <label class="small fw-bold text-primary mb-1 d-block">Booking Number</label>
            <input name="booking_room"
                class="form-control border-0 bg-transparent fw-bold text-primary p-0 fs-5"
                placeholder="BK-XXXX"
                value="<?= htmlspecialchars($data['booking_room']) ?>">
        </div>
    </div>

    <div class="col-md-4">
        <div class="p-3 rounded-4 bg-success bg-opacity-10 h-100">
            <label class="small fw-bold text-success mb-1 d-block">มัดจำ (Deposit)</label>
            <div class="input-group">
                <span class="input-group-text border-0 bg-transparent text-success fw-bold ps-0 fs-4">฿</span>
                <input type="number" step="0.01" name="deposit"
                    class="form-control border-0 bg-transparent fw-bold text-success p-0 fs-4"
                    placeholder="0.00" value="<?= $data['deposit'] ?>">
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="p-3 rounded-4 bg-info bg-opacity-10 h-100">
            <label class="small fw-bold text-info mb-1 d-block">จำนวน (PAX)</label>
            <div class="input-group">
                <input type="number" name="pax"
                    class="form-control border-0 bg-transparent fw-bold text-info p-0 fs-5"
                    placeholder="0" value="<?= $data['pax'] ?>">
                <span class="input-group-text border-0 bg-transparent text-info fw-bold pe-0 small">Pers.</span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="p-3 rounded-4 bg-secondary bg-opacity-10 h-100">
            <label class="small fw-bold text-secondary mb-1 d-block">มูลค่างานทั้งหมด (Total Amount)</label>
            <div class="input-group">
                <span class="input-group-text border-0 bg-transparent text-secondary fw-bold ps-0 fs-4">฿</span>
                <input type="number" step="0.01" name="total_amount"
                    class="form-control border-0 bg-transparent fw-bold text-secondary p-0 fs-4"
                    placeholder="0.00" 
                    value="<?= isset($data['total_amount']) ? number_format($data['total_amount'], 2, '.', '') : '0.00' ?>">
            </div>
        </div>
    </div>
</div>

                           
                                <div class="row g-3 mt-1">
                                    <?php 
    $file_colors = ['secondary', 'warning', 'danger']; 
    for($i=1; $i<=3; $i++): 
        $color = $file_colors[$i-1];
        $file_path = $data['file_attachment'.$i];
        $file_name = $file_path ? basename($file_path) : '';
    ?>
                                    <div class="col-md-4">
                                        <div
                                            class="p-3 rounded-4 bg-<?=$color?> bg-opacity-10 border border-<?=$color?> border-opacity-25">
                                            <label class="small fw-bold text-<?=$color?> mb-1 d-block">
                                                <i class="bi bi-paperclip"></i> ไฟล์แนบ <?=$i?>
                                            </label>

                                            <?php if($file_path): ?>
                                            <div id="file_display_<?=$i?>"
                                                class="d-flex align-items-center justify-content-between mb-2 bg-white p-2 rounded-3 ">
                                                <div class="text-truncate me-2" style="font-size: 11px;">
                                                    <a href="<?=$file_path?>" target="_blank"
                                                        class="text-decoration-none text-dark">
                                                        <i class="bi bi-file-earmark-check text-<?=$color?>"></i>
                                                        <?=$file_name?>
                                                    </a>
                                                </div>

                                                <div class="ms-1">
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm py-0 px-2"
                                                        style="font-size: 10px; border-radius: 6px;" onclick="if(confirm('ลบไฟล์เดิม?')){ 
                                    document.getElementById('file_display_<?=$i?>').style.setProperty('display', 'none', 'important'); 
                                    document.getElementById('delete_flag_<?=$i?>').value = '1'; 
                                }">
                                                        <i class="bi bi-trash3"></i> ลบ
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <input type="file" name="file_attachment<?=$i?>"
                                                class="form-control form-control-sm border-0 bg-white bg-opacity-50 text-<?=$color?>"
                                                style="font-size: 11px;">

                                            <input type="hidden" name="old_file_<?=$i?>" value="<?=$file_path?>">
                                            <input type="hidden" name="delete_file_<?=$i?>" id="delete_flag_<?=$i?>"
                                                value="0">

                                            <div class="mt-1" style="font-size: 9px; color: #666;">
                                                * อัปโหลดใหม่เพื่อเปลี่ยนไฟล์
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-7 border-end pe-lg-4">
                        <h5 class="section-title mb-4"><i class="bi bi-calendar3"></i> 2. ตารางกำหนดการ</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle" id="scheduleTable">
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
                            <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addScheduleRow()"><i
                                    class="bi bi-plus-lg"></i> เพิ่มกำหนดการ</button>
                        </div>

                        <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. Main Kitchen (ครัว)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle" id="kitchenTable" style="table-layout: fixed; width: 100%;">
    <tbody>
        <?php if ($kitchens->num_rows > 0):
            while ($k = $kitchens->fetch_assoc()): ?>
        <tr>
            <td style="width: 140px;">
                <input type="date" name="k_date[]"
                    class="form-control form-control-sm border-0 bg-light"
                    value="<?php echo $k['k_date']; ?>">
            </td>

            <td style="width: 160px;">
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

            <td style="width: 80px;">
                <input type="number" name="k_qty[]"
                    class="form-control form-control-sm border-0 bg-light text-center"
                    value="<?php echo $k['k_qty']; ?>">
            </td>

            <td style="width: 45px;" class="text-center">
                <button type="button" class="btn text-danger btn-sm border-0"
                    onclick="removeRow(this)"><i class="bi bi-dash-circle fs-5"></i></button>
            </td>
        </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>
                            <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addKitchenRow()"><i
                                    class="bi bi-plus-lg"></i> เพิ่มรายการครัว</button>
                        </div>
                        <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2"
                            rows="3"><?php echo $data['main_kitchen_remark'] ?? ''; ?></textarea>
                    </div>

                    <div class="col-md-5 bg-sidebar p-4 rounded-4">
                        <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 4. ด้านเทคนิคและงานช่าง
                        </h5>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">การจัดงานเลี้ยง:</label>
                            <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                rows="6"><?php echo $data['banquet_style']; ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">งานช่างและภาพเสียง:</label>
                            <textarea name="equipment" class="form-control form-control-sm bg-white"
                                rows="5"><?php echo $data['equipment']; ?></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="fw-bold small text-muted">หมายเหตุเพิ่มเติม:</label>
                            <textarea name="remark" class="form-control form-control-sm bg-white"
                                rows="2"><?php echo $data['remark']; ?></textarea>
                        </div>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 5. รายละเอียดเมนูอาหารและเครื่องดื่ม
                </h5>
                <div class="table-responsive mb-5">
                    <table class="table table-sm table-hover align-middle" id="menuTable" style="table-layout: fixed; width: 100%;">
    <tbody>
        <?php if ($menus->num_rows > 0):
            while ($m = $menus->fetch_assoc()): ?>
        <tr>
            <td style="width: 150px;">
                <input type="date" name="menu_time[]" class="form-control form-control-sm border-0 bg-light"
                    value="<?php echo $m['menu_time']; ?>">
            </td>

            <td style="width: 180px;">
                <select name="menu_set_id[]" class="form-select form-select-sm border-0 bg-light">
                    <option value="">-- เลือกเซตเมนู --</option>
                    <?php if ($res_menu_sets && $res_menu_sets->num_rows > 0):
                        $res_menu_sets->data_seek(0);
                        while ($ms = $res_menu_sets->fetch_assoc()):
                            $selected = (isset($m['menu_set_id']) && $m['menu_set_id'] == $ms['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= $ms['id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($ms['type_name']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </td>

            <td>
                <textarea name="menu_detail[]" class="form-control form-control-sm border-0 bg-light w-100"
                    rows="1"
                    style="field-sizing: content; min-height: 2.2rem; resize: none; overflow:hidden;"
                    oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px';"
                ><?php echo htmlspecialchars($m['menu_detail'] ?? ''); ?></textarea>
            </td>

            <td style="width: 90px;">
                <input type="number" name="menu_qty[]" class="form-control form-control-sm border-0 bg-light text-center" 
                    placeholder="จำนวน"
                    value="<?php echo $m['menu_qty']; ?>">
            </td>

            <td style="width: 110px;">
                <input type="number" step="0.01" name="menu_price[]" class="form-control form-control-sm border-0 bg-light text-end" 
                    placeholder="ราคา"
                    value="<?php echo $m['menu_price']; ?>">
            </td>

            <td style="width: 50px;" class="text-center">
                <button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)">
                    <i class="bi bi-dash-circle fs-5"></i>
                </button>
            </td>
        </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>
                    <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addMenuRow()"><i
                            class="bi bi-plus-lg"></i> เพิ่มรายการอาหาร</button>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-palette-fill"></i> 6. การตกแต่งและการดูแลทำความสะอาด</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white h-100">
                            <label class="fw-bold small text-muted mb-3">รายละเอียดฉากหลังและป้าย:</label>
                            <textarea name="backdrop_detail" class="form-control form-control-sm mb-4"
                                rows="3"><?php echo $data['backdrop_detail'] ?? ''; ?></textarea>

                            <div class="p-3 border-dashed text-center bg-light">
                                <label class="small d-block mb-2">รูปภาพปัจจุบัน:</label>
                                <div id="imagePreviewContainer"
                                    class="<?php echo !empty($data['backdrop_img']) ? '' : 'd-none'; ?>">
                                    <img id="imagePreview" src="<?php echo $data['backdrop_img'] ?: '#'; ?>"
                                        class="img-thumbnail mb-2" style="max-height: 150px;">
                                </div>
                                <input type="file" name="backdrop_img" class="form-control form-control-sm"
                                    accept="image/*" onchange="previewImage(this)">
                                <input type="hidden" name="old_backdrop_img"
                                    value="<?php echo $data['backdrop_img']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white  h-100">
                            <label class="fw-bold small text-muted mb-3">พนักงานทำความสะอาดและพนักงานจัดดอกไม้:</label>
                            <textarea name="hk_florist_detail" class="form-control form-control-sm"
                                rows="8"><?php echo $data['hk_florist_detail']; ?></textarea>
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
        <td width="10%"><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center" placeholder="0"></td>
        <td width="5%"><button type="button" class="btn text-danger btn-sm border-0"
                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
}

function addMenuRow() {
    const table = document.querySelector("#menuTable tbody");
    const row = table.insertRow();
    row.className = "align-top";
    row.innerHTML = `
        <td width="12%"><input type="date" name="menu_time[]" class="form-control form-control-sm border-0 "></td>
        <td width="18%">
            <select name="menu_set_id[]" class="form-select form-select-sm border-0 "
                    onchange="fetchMenuDetail(this)"> <option value="">-- เลือกเซตเมนู --</option>
                <?php
                if ($res_menu_sets) {
                    $res_menu_sets->data_seek(0);
                    while ($ms = $res_menu_sets->fetch_assoc()) {
                        echo '<option value="' . $ms['id'] . '">' . htmlspecialchars($ms['type_name']) . '</option>';
                    }
                }
                ?>
            </select>
        </td>
        <td>
            <textarea name="menu_detail[]" 
                      class="form-control form-control-sm border-0 bg-white  w-100 menu-detail-input" /* 🛠️ เพิ่มคลาสนี้ */
                      rows="3" placeholder="ระบุรายละเอียดอาหาร..." style="min-width: 100%; resize: vertical;"></textarea>
        </td>
        <td width="10%"><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0 " placeholder="0"></td>
        <td width="12%"><input type="text" name="menu_price[]" class="form-control form-control-sm border-0" placeholder="0.00"></td>
        <td width="5%" class="text-center">
           <button type="button" class="btn text-danger btn-sm border-0"
                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
        </td>
    `;
}

function removeRow(btn) {
    btn.closest("tr").remove();
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
const selectedRoomId = "<?php echo $data['room_id']; ?>"; // ห้องที่เคยจองไว้เดิม

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

        // เช็กสถานะ: สมมติว่าในวัตถุ room มี property 'busy_until' (เช่น "2024-05-20 18:00") 
        // ถ้าห้องนั้นถูกอนุมัติ (approve=1) และยังไม่หมดเวลา
        const isBusy = room.busy_until ? true : false;

        const cardHtml = `
            <div class="col-md-4 mb-3">
    <div class="room-card p-3 rounded-4 border h-100 position-relative 
        ${isSelected ? 'selected border-primary bg-light' : 'bg-white'}"
        onclick="selectRoom(this, '${room.id}')" 
        style="cursor: pointer; transition: all 0.2s; ${isBusy ? 'border-style: dashed;' : ''}">
        
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
            <i class="bi bi-aspect-ratio me-1"></i> พื้นที่: ${parseFloat(room.total_sqm).toFixed(2)} ตร.ม.
        </p>

        <p class="text-muted small mb-2">
            <i class="bi bi-people me-1"></i>
            B: <span class="text-dark fw-bold">${room.cap_banquet}</span> |
            T: <span class="text-dark fw-bold">${room.cap_theatre}</span>
        </p>

        <div class="mt-2">
            ${isBusy ? `
                <div class="d-flex flex-column gap-1">
                    <span class="badge bg-danger bg-opacity-10 text-danger w-100 py-2 border border-danger border-opacity-25">
                        <i class="bi bi-calendar-check me-1"></i> ใช้งานถึง: ${room.busy_until}
                    </span>
                </div>
            ` : `
                <span class="badge bg-success bg-opacity-10 text-success w-100 py-2 border border-success border-opacity-25">
                    <i class="bi bi-check-circle me-1"></i> ว่าง / พร้อมใช้งาน
                </span>
            `}
        </div>
    </div>
</div>
        `;
        container.insertAdjacentHTML('beforeend', cardHtml);
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
}

// สั่งให้ทำงานทันทีตอนโหลดหน้า (เพื่อให้โชว์ห้องของโรงแรมเดิม)
document.addEventListener('DOMContentLoaded', function() {
    const currentComp = document.querySelector('select[name="company_id"]').value;
    if (currentComp) filterRooms(currentComp);
});
</script>
<?php include "footer.php"; ?>