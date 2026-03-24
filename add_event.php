<?php
include "config.php";
include "api/process_function.php";
include "header.php";
access_control(['Admin', 'GM', 'Staff', 'Viewer']);


// 1. ดึงข้อมูลบริษัท/โรงแรม
$query_companies = "SELECT id, company_name, logo_path FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies);

// 2. ดึงข้อมูลประเภทงาน (Function Types)
$query_types = "SELECT id, type_name FROM function_types ORDER BY id ASC";
$res_types = $conn->query($query_types);

// 1. รับค่า ID บริษัทที่เลือก (เช่น จาก URL หรือตัวแปรที่จารย์มี)
$target_company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

// 2. ปรับ SQL ให้ดึงเฉพาะห้องของบริษัทนั้น
// ถ้า $target_company_id เป็น 0 อาจจะให้ดึงทั้งหมด หรือไม่ดึงเลยก็ได้ครับ
$sql_rooms = "SELECT * FROM meeting_rooms WHERE company_id = '$target_company_id' AND status = 'active' ORDER BY room_name ASC";
$res_rooms = $conn->query($sql_rooms);

// 4. ดึงประเภทเมนูอาหาร (Master Menu Types)
$query_menu_types = "SELECT id, type_name FROM master_menu_types ORDER BY id ASC";
$res_menu_sets = $conn->query($query_menu_types);

// 5. ดึงข้อมูลลูกค้า
$query_customers = "SELECT id, cust_name, cust_phone, cust_address FROM customers ORDER BY cust_name ASC";
$res_customers = $conn->query($query_customers);

// 6. ดึงข้อมูลประเภท Break (จากตารางที่จารให้มา)
$query_breaks = "SELECT id, type_name FROM master_break_types ORDER BY id ASC";
$res_breaks = $conn->query($query_breaks);

// ต่อท้ายส่วนที่รับค่า $target_company_id
$current_logo = 'assets/img/default-company.png'; // ค่าเริ่มต้น
if ($target_company_id > 0) {
    // ดึงโลโก้ออกมา
    $res_companies->data_seek(0);
    while ($row = $res_companies->fetch_assoc()) {
        if ($row['id'] == $target_company_id) {
            $current_logo = !empty($row['logo_path']) ? $row['logo_path'] : 'assets/img/default-company.png';
            break;
        }
    }
}
?>
<?php
// ดึงข้อมูลห้องประชุมทั้งหมด พร้อมเช็กสถานะการจอง (Join ทีเดียวจบ)
$all_rooms_query = "SELECT r.*, 
    (SELECT end_time FROM functions 
     WHERE room_id = r.id AND approve = 1 AND end_time >= NOW() 
     ORDER BY end_time DESC LIMIT 1) as active_booking_end
    FROM meeting_rooms r WHERE r.status = 'active'";
$all_rooms_res = $conn->query($all_rooms_query);

$rooms_data = [];
while ($row = $all_rooms_res->fetch_assoc()) {
    $rooms_data[] = $row;
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

<script>
    // ส่งข้อมูลจาก PHP ไปเป็นตัวแปร JavaScript JSON
    const allRooms = <?= json_encode($rooms_data); ?>;
</script>

<div class="container-fluid p-0">
    <form method="POST" enctype="multipart/form-data">
        <div class="card  border-0">
            <div class="card-header main-header p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-building-check me-2 text-gold"></i> FUNCTION MEETING
                    </h4>

                    <div style="width: 100%; max-width: 450px;">
                        <div class="d-flex align-items-center justify-content-end">

                            <?php
                            // แปลงเป็นตัวเล็กเพื่อกันพลาด และเช็คว่าไม่ใช่ viewer
                            $current_role = strtolower($_SESSION['role'] ?? '');

                            // ถ้า role ไม่ใช่ viewer ให้แสดงปุ่มบันทึก
                            if ($current_role !== 'viewer'):
                                ?>
                                <button name="save" type="submit"
                                    class="btn btn-success btn-sm px-3 flex-shrink-0 shadow-sm">
                                    <i class="bi bi-cloud-check-fill me-2"></i> บันทึกข้อมูลฟังชั่น
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm px-3 flex-shrink-0 opacity-50"
                                    disabled>
                                    <i class="bi bi-eye me-2"></i> โหมดอ่านอย่างเดียว
                                </button>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-lg-5">
                <h5 class="section-title mb-4"><i class="bi bi-person-lines-fill"></i> 1. ข้อมูลการจองทั่วไป (General
                    Information)</h5>
                <div class="row mb-5">
                    <div class="col-lg-3">
                        <div class="mb-4 text-center">
                            <label class="small fw-bold text-secondary mb-3 d-block text-start">
                                <i class="bi bi-building me-1 text-primary"></i> เลือกโรงแรม
                            </label>
                            <select name="company_id" class="form-select border-0 bg-light mb-3"
                                onchange="updateCompanyLogo(this); renderRooms(this.value);" required
                                style="border-radius: 10px; height: 42px;">
                                <option value="">-- เลือกโรงแรม --</option>
                                <?php
                                $res_companies->data_seek(0);
                                while ($row = $res_companies->fetch_assoc()): ?>
                                    <option value="<?= $row['id']; ?>"
                                        data-logo="<?= !empty($row['logo_path']) ? $row['logo_path'] : 'assets/img/default-company.png'; ?>">
                                        <?= htmlspecialchars($row['company_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="company-logo-preview border-0 rounded-3 bg-light d-flex align-items-center justify-content-center mx-auto mb-2"
                                style="width: 80px; height: 80px; overflow: hidden;">
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
                                <input type="hidden" name="customer_id" id="customer_id_hidden">
                                <select id="customer_selector" name="customer_id"
                                    class="form-select border-0  bg-opacity-10  fw-bold bg-light"
                                    onchange="fillCustomerInfo(this)"
                                    style="border-radius: 10px; height: 42px; font-size: 13px;">
                                    <option value="">-- ค้นหา/เลือกลูกค้าเดิม --</option>
                                    <?php if ($res_customers && $res_customers->num_rows > 0):
                                        while ($c = $res_customers->fetch_assoc()): ?>
                                            <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['cust_name']) ?>"
                                                data-phone="<?= htmlspecialchars($c['cust_phone']) ?>"
                                                data-address="<?= htmlspecialchars($c['cust_address']) ?>">
                                                <?= htmlspecialchars($c['cust_name']) ?>
                                            </option>
                                        <?php endwhile; endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label mb-1" style="font-size: 11px;">ชื่อลูกค้า/ผู้จอง</label>
                                <input type="text" id="booking_name" name="booking_name"
                                    class="form-control border-0 bg-light rounded-3" placeholder="ชื่อ-นามสกุล" required
                                    style="height: 40px; font-size: 13px;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label mb-1" style="font-size: 11px;">เบอร์โทรศัพท์</label>
                                <input type="text" id="customer_phone" name="phone"
                                    class="form-control border-0 bg-light rounded-3" placeholder="08x-xxx-xxxx"
                                    style="height: 40px; font-size: 13px;">
                            </div>

                            <div class="mb-0">
                                <label class="form-label mb-1" style="font-size: 11px;">หน่วยงาน/ที่อยู่</label>
                                <textarea id="customer_address" name="organization"
                                    class="form-control border-0 bg-light rounded-3" placeholder="ที่อยู่ลูกค้า..."
                                    rows="3" style="font-size: 13px; resize: none;"></textarea>
                            </div>
                        </div>
                    </div>



                    <div class="col-md-9">
                        <div class="col-md-12 mb-4">
                            <label class="form-label small fw-bold text-secondary mb-3">
                                <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i> เลือกห้องประชุม (Select
                                Venue)
                            </label>
                            <div class="row g-3" id="roomContainer">
                                <div class="col-12 text-center py-5 text-muted">
                                    โปรดเลือกบริษัทก่อนเพื่อแสดงรายชื่อห้องประชุม
                                </div>
                            </div>
                        </div>
                        <div class="row text-dark">
                            <h6 class="fw-bold  text-dark">
                                <i class="bi bi-info-circle me-2 text-primary"></i>รายละเอียดการจอง
                            </h6>

                            <div class="row text-dark">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label small fw-bold text-secondary">ชื่องาน (Event
                                        Title)</label>
                                    <input name="function_name" class="form-control border-0 bg-light"
                                        placeholder="พิมพ์ชื่อโครงการหรืองานจัดเลี้ยง..." required
                                        style="border-radius: 10px; height: 42px;">
                                </div>

                                <div class="col-md-4 ">
                                    <label class="form-label small fw-bold text-secondary">ประเภทงาน</label>
                                    <select name="function_type_id" class="form-select border-0 bg-light" required ...>
                                        <option value="" disabled selected>-- เลือกประเภท --</option>
                                        <?php while ($t = $res_types->fetch_assoc()): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 ">
                                        <label class="form-label small fw-bold text-secondary">วันเวลาที่เริ่มงาน (Start
                                            Date & Time)</label>
                                        <input type="datetime-local" name="start_time"
                                            class="form-control border-0 bg-light" required
                                            style="border-radius: 10px; height: 42px;">
                                    </div>
                                    <div class="col-md-6 ">
                                        <label class="form-label small fw-bold text-secondary">วันเวลาที่สิ้นสุดงาน (End
                                            Date & Time)</label>
                                        <input type="datetime-local" name="end_time"
                                            class="form-control border-0 bg-light" required
                                            style="border-radius: 10px; height: 42px;">
                                    </div>
                                </div>
                            </div>
                            <div class="row  mb-3">
                                <div class="row g-2 ">
                                    <div class="col-md-2">
                                        <div class="p-3 rounded-4 bg-primary bg-opacity-10 h-100">
                                            <label class="small fw-bold text-primary mb-1 d-block">Booking
                                                Number</label>
                                            <input name="booking_room" value="<?php echo $row['booking_room'] ?? ''; ?>"
                                                class="form-control border-0 bg-transparent fw-bold text-primary p-0 fs-5"
                                                placeholder="BK-XXXX">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="p-3 rounded-4 bg-info bg-opacity-10 h-100">
                                            <label class="small fw-bold text-info mb-1 d-block">จำนวน (PAX)</label>
                                            <div class="input-group">
                                                <input type="number" name="pax" value="<?php echo $row['pax'] ?? ''; ?>"
                                                    class="form-control border-0 bg-transparent fw-bold text-info p-0 fs-5"
                                                    placeholder="0">
                                                <span
                                                    class="input-group-text border-0 bg-transparent text-info fw-bold pe-0 small">Pers.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-success bg-opacity-10 h-100">
                                            <label class="small fw-bold text-success mb-1 d-block">มัดจำ
                                                (Deposit)</label>
                                            <div class="input-group">
                                                <span
                                                    class="input-group-text border-0 bg-transparent text-success fw-bold ps-0 fs-4">฿</span>
                                                <input type="number" step="0.01" name="deposit"
                                                    value="<?php echo $row['deposit'] ?? ''; ?>"
                                                    class="form-control border-0 bg-transparent fw-bold text-success p-0 fs-4"
                                                    placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>



                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-secondary bg-opacity-10 h-100">
                                            <label class="small fw-bold text-secondary mb-1 d-block">มูลค่างานทั้งหมด
                                                (Total Amount)</label>
                                            <div class="input-group">
                                                <span
                                                    class="input-group-text border-0 bg-transparent text-secondary fw-bold ps-0 fs-4">฿</span>
                                                <input type="number" step="0.01" name="total_amount"
                                                    class="form-control border-0 bg-transparent fw-bold text-secondary p-0 fs-4"
                                                    placeholder="0.00"
                                                    value="<?php echo isset($row['total_amount']) ? number_format($row['total_amount'], 2, '.', '') : '0.00'; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <?php
                                    $file_colors = ['secondary', 'warning', 'danger'];
                                    $file_labels = ['ไฟล์แนบ 1', 'ไฟล์แนบ 2', 'ไฟล์แนบ 3'];
                                    for ($i = 1; $i <= 3; $i++):
                                        $color = $file_colors[$i - 1];
                                        ?>
                                        <div class="col-md-4">
                                            <div class="p-3 rounded-4 bg-<?= $color ?> bg-opacity-10">
                                                <label class="small fw-bold text-<?= $color ?> mb-1 d-block">
                                                    <i class="bi bi-paperclip"></i>
                                                    <?= $file_labels[$i - 1] ?>
                                                </label>
                                                <input type="file" name="file_attachment<?= $i ?>"
                                                    class="form-control form-control-sm border-0 bg-transparent p-0">

                                                <input type="hidden" name="old_file_<?= $i ?>"
                                                    value="<?php echo $row['file_attachment' . $i] ?? ''; ?>">

                                                <?php if (!empty($row['file_attachment' . $i])): ?>
                                                    <div class="mt-1">
                                                        <a href="../<?= $row['file_attachment' . $i] ?>" target="_blank"
                                                            class="badge bg-<?= $color ?> text-decoration-none small">
                                                            <i class="bi bi-eye"></i> ดูไฟล์ปัจจุบัน
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <div class="col-md-7 border-end pe-lg-4">
                            <h5 class="section-title mb-4"><i class="bi bi-calendar3"></i> 2. ตารางกำหนดการ (Schedule)
                            </h5>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-hover align-middle" id="scheduleTable">
                                    <thead class="small text-center text-secondary">
                                        <tr>
                                            <th width="20%">วันที่</th>
                                            <th width="20%">เวลา</th>
                                            <th>รายละเอียด</th>
                                            <th width="15%">จำนวน</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="date" name="schedule_date[]"
                                                    class="form-control form-control-sm border-0 bg-light"></td>
                                            <td><input type="text" name="schedule_hour[]"
                                                    class="form-control form-control-sm border-0 bg-light"
                                                    placeholder="00:00 - 00:00"></td>
                                            <td><textarea name="schedule_function[]"
                                                    class="form-control form-control-sm border-0 bg-light"
                                                    rows="2"></textarea></td>
                                            <td><input type="number" name="schedule_guarantee[]"
                                                    class="form-control form-control-sm border-0 bg-light"></td>
                                            <td><button type="button" class="btn text-danger btn-sm border-0"
                                                    onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-hotel-outline btn-sm mt-1"
                                    onclick="addScheduleRow()"><i class="bi bi-plus-lg me-1"></i> เพิ่มกำหนดการ</button>
                            </div>

                            <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. Main Kitchen (ครัว)
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle" id="kitchenTable">
                                    <thead class="small text-center text-secondary">
                                        <tr>
                                            <th width="20%">วันที่</th>
                                            <th width="25%">ประเภทเมนู</th>
                                            <th>รายการรายละเอียด</th>
                                            <th width="15%">จำนวน (PAX)</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="date" name="k_date[]"
                                                    class="form-control form-control-sm border-0 bg-light">
                                            </td>
                                            <td>
                                                <select name="k_type_id[]"
                                                    class="form-select form-select-sm border-0 bg-light"
                                                    onchange="fetchBreakMenu(this)">
                                                    <option value="" disabled selected>-- เลือกประเภท Break --</option>
                                                    <?php if ($res_breaks && $res_breaks->num_rows > 0):
                                                        $res_breaks->data_seek(0);
                                                        while ($b = $res_breaks->fetch_assoc()): ?>
                                                            <option value="<?= $b['id'] ?>">
                                                                <?= htmlspecialchars($b['type_name']) ?>
                                                            </option>
                                                        <?php endwhile; endif; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea name="k_item[]"
                                                    class="form-control form-control-sm border-0 bg-light break-menu-input"
                                                    rows="3" placeholder="1. รายการอาหาร..."
                                                    onfocus="initFirstLine(this)"></textarea>
                                            </td>
                                            <td>
                                                <input type="number" name="k_qty[]"
                                                    class="form-control form-control-sm border-0 bg-light text-center"
                                                    placeholder="0">
                                            </td>
                                            <td>
                                                <button type="button" class="btn text-danger btn-sm border-0"
                                                    onclick="removeRow(this)">
                                                    <i class="bi bi-dash-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-hotel-outline btn-sm mt-1"
                                    onclick="addKitchenRow()">
                                    <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการครัว
                                </button>
                            </div>


                            <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2" rows="3"
                                placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                        </div>

                        <div class="col-md-5 bg-sidebar p-4 rounded-4">
                            <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 4.
                                ด้านเทคนิคและงานช่าง
                            </h5>
                            <div class="mb-4">
                                <label class="fw-bold small text-muted">การจัดงานเลี้ยง:</label>
                                <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                    rows="6"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="fw-bold small text-muted">งานช่างและภาพเสียง:</label>
                                <textarea name="equipment" class="form-control form-control-sm bg-white"
                                    rows="5"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="fw-bold small text-muted">หมายเหตุเพิ่มเติม:</label>
                                <textarea name="remark" class="form-control form-control-sm bg-white"
                                    rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 5.
                        รายละเอียดเมนูอาหารและเครื่องดื่ม
                    </h5>
                    <div class="table-responsive mb-5">
                        <table class="table table-sm table-hover align-middle border" id="menuTable">
                            <thead class="text-center text-secondary bg-light">
                                <tr>
                                    <th width="10%">เวลา</th>
                                    <th width="15%">ประเภทเมนู</th>
                                    <th>รายละเอียด</th>
                                    <th width="10%">จำนวน</th>
                                    <th width="12%">ราคา</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="date" name="menu_time[]"
                                            class="form-control form-control-sm border-0" placeholder="10:30"></td>
                                    <td>
                                        <select name="menu_set_id[]" class="form-select form-select-sm border-0"
                                            onchange="fetchMenuDetail(this)">
                                            <option value="" disabled selected>-- เลือกเซตเมนู --</option>
                                            <?php
                                            // สมมติจารมี $res_menu_sets ที่ดึงมาจากตาราง master_menus
                                            if ($res_menu_sets):
                                                $res_menu_sets->data_seek(0);
                                                while ($m = $res_menu_sets->fetch_assoc()): ?>
                                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['type_name']) ?>
                                                    </option>
                                                <?php endwhile; endif; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <textarea name="menu_detail[]"
                                            class="form-control form-control-sm border-0 menu-detail-input"
                                            rows="1"></textarea>
                                    </td>
                                    <td><input type="text" name="menu_qty[]"
                                            class="form-control form-control-sm border-0">
                                    </td>
                                    <td><input type="text" name="menu_price[]"
                                            class="form-control form-control-sm border-0" placeholder="0.00"></td>
                                    <td class="text-center"><button type="button"
                                            class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i
                                                class="bi bi-dash-circle"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addMenuRow()"><i
                                class="bi bi-plus-lg me-1"></i> เพิ่มรายการอาหาร</button>
                    </div>

                    <h5 class="section-title"><i class="bi bi-palette-fill"></i> 6. การตกแต่งและการดูแลทำความสะอาด
                    </h5>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 bg-white  h-100">
                                <label class="fw-bold small text-muted mb-3">รายละเอียดฉากหลังและป้าย:</label>
                                <textarea name="backdrop_detail" class="form-control form-control-sm mb-4"
                                    rows="3"></textarea>
                                <div class="p-3 border-dashed text-center bg-light">
                                    <input type="file" name="backdrop_img" id="backdropInput"
                                        class="form-control form-control-sm mb-2" accept="image/*"
                                        onchange="previewImage(this)">
                                    <input type="hidden" name="backdrop_img_path_ai" id="backdrop_img_path_ai">
                                    <div id="imagePreviewContainer" class="text-center d-none">
                                        <img id="imagePreview" src="#" class="img-thumbnail mt-2"
                                            style="max-height: 150px;">
                                        <button type="button"
                                            class="btn btn-sm btn-link text-danger d-block mx-auto mt-2"
                                            onclick="clearPreview()">ลบรูปภาพ</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 bg-white  h-100">
                                <label
                                    class="fw-bold small text-muted mb-3">พนักงานทำความสะอาดและพนักงานจัดดอกไม้:</label>
                                <textarea name="hk_florist_detail" class="form-control form-control-sm"
                                    rows="8"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
</div>

<script>
    /**
     * SECTION 1: MEDIA & UI LOGIC
     * จัดการรูปภาพ, โลโก้ และการเลือกห้อง
     */
    function previewImage(input) {
        const container = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                container.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearPreview() {
        document.getElementById('backdropInput').value = "";
        document.getElementById('imagePreviewContainer').classList.add('d-none');
    }

    function updateCompanyLogo(select) {
        const logoImg = document.getElementById('companyLogo');
        const selectedOption = select.options[select.selectedIndex];
        const logoPath = selectedOption.getAttribute('data-logo');
        logoImg.src = logoPath ? logoPath : 'assets/img/default-company.png';
    }

    function selectRoom(element, roomId) {
        document.querySelectorAll('.room-card').forEach(card => {
            card.classList.remove('selected');
        });
        element.classList.add('selected');
        const radio = element.querySelector('input[type="radio"]');
        radio.checked = true;
        console.log("Selected Room ID:", roomId);
    }

    /**
     * SECTION 2: CUSTOMER & DATA FETCHING
     * ดึงข้อมูลลูกค้า และดึงเมนูเบรกจาก Database (AJAX)
     */
    function fillCustomerInfo(select) {
        const selectedOption = select.options[select.selectedIndex];

        // ดึงค่าจาก data attributes
        const customerId = select.value; // ค่า ID จาก value ของ option
        const customerName = selectedOption.getAttribute('data-name');
        const customerPhone = selectedOption.getAttribute('data-phone');
        const customerAddress = selectedOption.getAttribute('data-address');

        // นำค่าไปใส่ในฟิลด์ต่างๆ
        document.getElementById('customer_id_hidden').value = customerId; // ใส่ ID ในฟิลด์ที่ซ่อนไว้
        document.getElementById('booking_name').value = customerName || '';
        document.getElementById('customer_phone').value = customerPhone || '';
        document.getElementById('customer_address').value = customerAddress || '';
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
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        } catch (error) {
            console.error("Error:", error);
            textarea.value = "1. ";
        }
    }

    /**
     * SECTION 3: DYNAMIC ROW MANAGEMENT
     * ฟังก์ชันเพิ่ม/ลบแถวของทุกตาราง
     */
    function removeRow(btn) {
        const tbody = btn.closest("tbody");
        const rowCount = tbody.querySelectorAll("tr").length;
        if (rowCount > 1) {
            btn.closest("tr").remove();
        } else {
            alert("ต้องมีอย่างน้อย 1 แถวครับจาร");
        }
    }

    function addScheduleRow() {
        const table = document.querySelector("#scheduleTable tbody");
        const row = table.insertRow();
        row.innerHTML = `
        <td><input type="date" name="schedule_date[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="schedule_hour[]" class="form-control form-control-sm border-0 bg-light" placeholder="00:00 - 00:00"></td>
        <td><textarea name="schedule_function[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td>
        <td><input type="number" name="schedule_guarantee[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>`;
    }

    function addMenuRow() {
        const table = document.querySelector("#menuTable tbody");
        const row = table.insertRow();
        row.className = "align-top";
        row.innerHTML = `
        <td width="150"><input type="date" name="menu_time[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td width="200">
            <select name="menu_set_id[]" class="form-select form-select-sm border-0" onchange="fetchMenuDetail(this)">
                <option value="" disabled selected>-- เลือกเซตเมนู --</option>
                <?php
                if ($res_menu_sets) {
                    $res_menu_sets->data_seek(0);
                    while ($m = $res_menu_sets->fetch_assoc()) {
                        echo '<option value="' . $m['id'] . '">' . addslashes(htmlspecialchars($m['type_name'])) . '</option>';
                    }
                }
                ?>
            </select>
        </td>
        <td>
            <textarea name="menu_detail[]"
                                        class="form-control form-control-sm border-0 menu-detail-input"
                                        rows="1"></textarea>
        </td>
        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0">
                                </td>
                                <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"
                                        placeholder="0.00"></td>
                                <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0"
                                        onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
    }

    function addKitchenRow() {
        const table = document.querySelector("#kitchenTable tbody");
        const newRow = table.insertRow();

        // ดึง Options จาก PHP มาเตรียมไว้
        const breakOptions = `
        <option value="" disabled selected>-- เลือกประเภท Break --</option>
        <?php if ($res_breaks && $res_breaks->num_rows > 0):
            $res_breaks->data_seek(0);
            while ($b = $res_breaks->fetch_assoc()): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['type_name']) ?></option>
        <?php endwhile; endif; ?>`;

        newRow.innerHTML = `
        <td><input type="date" name="k_date[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><select name="k_type[]" class="form-select form-select-sm border-0 bg-light" onchange="fetchBreakMenu(this)">${breakOptions}</select></td>
        <td><textarea name="k_item[]" class="form-control form-control-sm border-0 bg-light break-menu-input" rows="3" onfocus="initFirstLine(this)"></textarea></td>
        <td><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center"></td>
        <td> <button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)">
                                                <i class="bi bi-dash-circle"></i>
                                            </button></td>`;
    }

    /**
     * SECTION 4: TEXTAREA UX (Auto-numbering)
     */
    function initFirstLine(el) {
        if (el.value.trim() === "") {
            el.value = "1. ";
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.target && e.target.classList.contains('break-menu-input')) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const el = e.target;
                const content = el.value;
                const lines = content.split('\n');
                const nextNum = lines.length + 1;
                el.value = content + '\n' + nextNum + ". ";
                el.scrollTop = el.scrollHeight;
            }
        }
    });

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

    function renderRooms(companyId) {
        const container = document.getElementById('roomContainer');
        if (!companyId) {
            container.innerHTML = '<div class="col-12 text-center py-5 text-muted">โปรดเลือกบริษัทก่อน</div>';
            return;
        }

        // กรองเอาเฉพาะห้องของบริษัทที่เลือก
        const filteredRooms = allRooms.filter(room => room.company_id == companyId);

        if (filteredRooms.length === 0) {
            container.innerHTML = '<div class="col-12 text-center py-5 text-muted">ไม่พบข้อมูลห้องประชุมสำหรับบริษัทนี้</div>';
            return;
        }

        // สร้าง HTML
        let html = '';
        filteredRooms.forEach(r => {
            const bookingStatus = r.active_booking_end
                ? `<span class="badge bg-danger"><i class="bi bi-calendar-check me-1"></i> ใช้งานถึง: ${r.active_booking_end}</span>`
                : `<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> ว่าง / พร้อมใช้งาน</span>`;

            html += `
            <div class="col-md-4">
                <div class="room-card p-3 rounded-4 border bg-white h-100 position-relative"
                    style="cursor: pointer;" onclick="selectRoom(this, '${r.id}')">
                    <input type="radio" name="room_id" value="${r.id}" class="d-none room-radio">
                    <div class="check-icon position-absolute" style="top: 10px; right: 10px; display: none;">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    </div>
                    <h6 class="fw-bold mb-1">${r.room_name}</h6>
                    <div class="mb-2">
                        <p class="text-muted small mb-1">
                            <i class="bi bi-layers me-1"></i> ชั้น: ${r.floor || '-'} |
                            <i class="bi bi-aspect-ratio me-1"></i> พื้นที่: ${parseFloat(r.total_sqm).toFixed(2)} ตร.ม.
                        </p>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-people me-1"></i> Banquet: <b>${r.cap_banquet}</b> | Theatre: <b>${r.cap_theatre}</b>
                        </p>
                        <p class="mb-0">${bookingStatus}</p>
                    </div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }
</script>
<?php include "footer.php"; ?>