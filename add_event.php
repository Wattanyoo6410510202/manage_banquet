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

// ดึงข้อมูลประเภทใหญ่ (Menu Categories)
$query_categories = "SELECT id, category_name FROM master_menu_categories ORDER BY sort_order ASC, id ASC";
$res_categories = $conn->query($query_categories);

// ดึงข้อมูลประเภทเมนูพร้อม category
$menu_types_with_cat = $conn->query("SELECT mmt.id, mmt.type_name, mmt.category_id, mmc.category_name 
    FROM master_menu_types mmt 
    LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id 
    ORDER BY mmc.sort_order ASC, mmt.id ASC");
$menu_types_array = [];
while ($mt = $menu_types_with_cat->fetch_assoc()) {
    $menu_types_array[] = $mt;
}

// 5. ดึงข้อมูลลูกค้า
$query_customers = "SELECT id, cust_name, cust_phone, cust_address FROM customers ORDER BY cust_name ASC";
$res_customers = $conn->query($query_customers);

// 6. ดึงข้อมูลประเภท Break (จากตารางที่จารให้มา)
$query_breaks = "SELECT id, type_name FROM master_break_types ORDER BY id ASC";
$res_breaks = $conn->query($query_breaks);

// --- [เพิ่มใหม่] ดึงข้อมูลจากใบเสนอราคา (ถ้ามี) ---
$quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : 0;
$quote_data = null;
$quote_items = [];

if ($quote_id > 0) {
    $sql_quote = "SELECT q.*, c.cust_name, c.cust_phone, c.cust_address 
                  FROM quotations q 
                  LEFT JOIN customers c ON q.customer_id = c.id 
                   WHERE q.id = $quote_id AND q.status != 'Cancelled'";
    $res_quote = $conn->query($sql_quote);
    if ($res_quote && $res_quote->num_rows > 0) {
        $quote_data = $res_quote->fetch_assoc();
        // ถ้ามีข้อมูลใบเสนอราคา ให้เอาค่าจากใบเสนอราคามาเป็นค่าเริ่มต้น
        $target_company_id = $quote_data['company_id'];
        
        $sql_items = "SELECT * FROM quotation_items WHERE quote_id = $quote_id";
        $res_items = $conn->query($sql_items);
        while ($item = $res_items->fetch_assoc()) {
            $quote_items[] = $item;
        }
    }
}
// ------------------------------------------

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

<?php
function renderMenuTypeOptions($menu_types_array, $selected_id = '') {
    $html = '<option value="" disabled selected>-- เลือกเซตเมนู --</option>';
    $current_cat_id = null;
    $hasoptgroup = false;
    foreach ($menu_types_array as $m) {
        $cat_id = $m['category_id'] ?? 0;
        $cat_name = $m['category_name'] ?? '';
        if ($cat_id != $current_cat_id) {
            if ($hasoptgroup) $html .= '</optgroup>';
            if ($cat_id > 0 && $cat_name) {
                $html .= '<optgroup label="' . htmlspecialchars($cat_name) . '">';
                $hasoptgroup = true;
            } else {
                $hasoptgroup = false;
            }
            $current_cat_id = $cat_id;
        }
        $sel = ($selected_id && $m['id'] == $selected_id) ? 'selected' : '';
        $html .= '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['type_name']) . '</option>';
    }
    if ($hasoptgroup) $html .= '</optgroup>';
    return $html;
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
    const menuTypeOptions = <?= json_encode($menu_types_array) ?>;
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

                <!-- Row: Company + Customer + Basic Info -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-building me-1 text-primary"></i> เลือกโรงแรม
                            </label>
                            <select name="company_id" class="form-select border-0 bg-light mb-2"
                                id="company_select"
                                onchange="updateCompanyLogo(this); renderRooms(this.value);" required
                                style="border-radius: 10px; height: 38px; font-size: 0.85rem;">
                                <option value="">-- เลือกโรงแรม --</option>
                                <?php
                                $res_companies->data_seek(0);
                                while ($row = $res_companies->fetch_assoc()): 
                                    $selected = ($row['id'] == $target_company_id) ? 'selected' : '';
                                ?>
                                    <option value="<?= $row['id']; ?>" <?= $selected ?>
                                        data-logo="<?= !empty($row['logo_path']) ? $row['logo_path'] : 'assets/img/default-company.png'; ?>">
                                        <?= htmlspecialchars($row['company_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <script>
                                window.addEventListener('DOMContentLoaded', (event) => {
                                    const companySelect = document.getElementById('company_select');
                                    if(companySelect && companySelect.value) {
                                        renderRooms(companySelect.value);
                                    }
                                });
                            </script>
                            <div class="company-logo-preview border rounded-3 bg-light d-flex align-items-center justify-content-center mx-auto"
                                style="width: 70px; height: 70px; overflow: hidden;">
                                <img id="companyLogo" src="<?= $current_logo; ?>" class="img-fluid p-2"
                                    alt="Company Logo">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-person-lines-fill me-1 text-primary"></i> ข้อมูลลูกค้า
                            </label>
                            <input type="hidden" name="customer_id" id="customer_id_hidden" value="<?= $quote_data['customer_id'] ?? '' ?>">
                            <input type="hidden" name="quotation_id" value="<?= $quote_id ?>">
                            <input type="hidden" name="project_id" value="<?= $quote_data['project_id'] ?? '' ?>">
                            <div class="mb-2">
                                <select id="customer_selector" name="customer_id"
                                    class="form-select border-0 bg-light select2-ajax-customer"
                                    style="border-radius: 10px; height: 38px; font-size: 0.85rem;">
                                    <?php if ($quote_data['customer_id']): ?>
                                        <option value="<?= $quote_data['customer_id'] ?>" selected>
                                            <?= htmlspecialchars($quote_data['cust_name']) ?>
                                        </option>
                                    <?php else: ?>
                                        <option value="">-- ค้นหาลูกค้า --</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="text" id="booking_name" name="booking_name"
                                        class="form-control border-0 bg-light rounded-3" placeholder="ชื่อ-นามสกุล" required
                                        value="<?= htmlspecialchars($quote_data['cust_name'] ?? '') ?>"
                                        style="height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-6">
                                    <input type="text" id="customer_phone" name="phone"
                                        class="form-control border-0 bg-light rounded-3" placeholder="เบอร์โทร"
                                        value="<?= htmlspecialchars($quote_data['cust_phone'] ?? '') ?>"
                                        style="height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-12 mt-1">
                                    <textarea id="customer_address" name="organization"
                                        class="form-control border-0 bg-light rounded-3" placeholder="ที่อยู่ลูกค้า..."
                                        rows="2" style="font-size: 0.8rem; resize: none;"><?= htmlspecialchars($quote_data['cust_address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="p-3 rounded-4 bg-white border h-100">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-info-circle me-1 text-primary"></i> รายละเอียดการจอง
                            </label>
                            <div class="row g-1">
                                <div class="col-7">
                                    <input name="function_name" class="form-control border-0 bg-light"
                                        placeholder="ชื่องาน" required
                                        value="<?= htmlspecialchars($quote_data['event_name'] ?? '') ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-5">
                                    <select name="function_type_id" class="form-select border-0 bg-light" required
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                        <option value="" disabled selected>ประเภทงาน</option>
                                        <?php 
                                        $res_types->data_seek(0);
                                        while ($t = $res_types->fetch_assoc()): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-6 mt-1">
                                    <label class="small text-muted mb-0" style="font-size: 0.65rem;">เริ่มงาน</label>
                                    <input type="datetime-local" name="start_time"
                                        class="form-control border-0 bg-light" required
                                        value="<?= isset($quote_data['event_date']) ? $quote_data['event_date'].'T08:00' : '' ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                                <div class="col-6 mt-1">
                                    <label class="small text-muted mb-0" style="font-size: 0.65rem;">สิ้นสุด</label>
                                    <input type="datetime-local" name="end_time"
                                        class="form-control border-0 bg-light" required
                                        value="<?= isset($quote_data['event_date']) ? $quote_data['event_date'].'T17:00' : '' ?>"
                                        style="border-radius: 10px; height: 36px; font-size: 0.8rem;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Room Selection -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="p-3 rounded-4 bg-white border">
                            <label class="small fw-bold text-secondary mb-2 d-block">
                                <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i> เลือกห้องประชุม (Select Venue)
                            </label>
                            <div class="row g-2" id="roomContainer">
                                <div class="col-12 text-center py-4 text-muted small">
                                    โปรดเลือกบริษัทก่อนเพื่อแสดงรายชื่อห้องประชุม
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Financial + Tracking -->
                <div class="row g-2 mb-4">
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 h-100">
                            <label class="small fw-bold text-primary mb-0" style="font-size: 0.65rem;">Booking No.</label>
                            <input name="booking_room" value="<?php echo $quote_data['booking_room'] ?? ''; ?>"
                                                                        class="form-control border-0 bg-transparent fw-bold text-primary p-0 fs-6"
                                placeholder="BK-XXXX" style="height: 32px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="p-2 rounded-3 bg-info bg-opacity-10 h-100">
                            <label class="small fw-bold text-info mb-0" style="font-size: 0.65rem;">จำนวน (PAX)</label>
                            <div class="input-group">
                                <input type="number" name="pax" value="<?php echo $quote_data['pax'] ?? ''; ?>"
                                    class="form-control border-0 bg-transparent fw-bold text-info p-0 fs-6"
                                    placeholder="0" style="height: 32px;">
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
                                    value="<?php echo $quote_data['deposit'] ?? ''; ?>"
                                    class="form-control border-0 bg-transparent fw-bold text-success p-0 fs-6"
                                    placeholder="0.00" style="height: 32px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 rounded-3 bg-secondary bg-opacity-10 h-100">
                            <label class="small fw-bold text-secondary mb-0" style="font-size: 0.65rem;">มูลค่างานทั้งหมด</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-transparent text-secondary fw-bold p-0">฿</span>
                                <input type="number" step="0.01" name="total_amount"
                                    class="form-control border-0 bg-transparent fw-bold text-secondary p-0 fs-6"
                                    placeholder="0.00" style="height: 32px;"
                                    value="<?= isset($quote_data['grand_total']) ? number_format($quote_data['grand_total'], 2, '.', '') : '0.00' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="p-2 rounded-3 bg-secondary bg-opacity-10 h-100">
                            <label class="small fw-bold text-secondary mb-0" style="font-size: 0.65rem;"><i class="bi bi-paperclip"></i> ไฟล์แนบ</label>
                            <div class="d-flex gap-1">
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <input type="file" name="file_attachment<?= $i ?>"
                                        class="form-control form-control-sm border-0 bg-transparent p-0"
                                        style="font-size: 0.6rem;">
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="row mb-5">
                        <div class="col-12 mb-4">
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

                            <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. รายการเบรก
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle" id="kitchenTable">
                                    <thead class="small text-center text-secondary">
                                        <tr>
                                            <th width="13%">วันที่</th>
                                            <th width="18%">ประเภทเบรก</th>
                                            <th>รายการรายละเอียด</th>
                                            <th width="10%">จำนวน (PAX)</th>
                                            <th width="13%">ราคาขาย/หน่วย</th>
                                            <th width="10%">ราคาทุน/หน่วย</th>
                                            <th width="12%">ยอดรวม</th>
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
                                                    class="form-control form-control-sm border-0 bg-light text-center kitchen-qty"
                                                    placeholder="0" oninput="updateKitchenRowTotal(this)">
                                            </td>
                                            <td>
                                                <input type="number" name="k_price[]"
                                                    class="form-control form-control-sm border-0 bg-light text-end kitchen-price"
                                                    placeholder="0.00" step="0.01" oninput="updateKitchenRowTotal(this)">
                                            </td>
                                            <td>
                                                <input type="number" name="k_cost[]"
                                                    class="form-control form-control-sm border-0 bg-light text-end kitchen-cost"
                                                    placeholder="0.00" step="0.01">
                                            </td>
                                            <td class="text-end fw-bold kitchen-row-total">0.00</td>
                                            <td>
                                                <button type="button" class="btn text-danger btn-sm border-0"
                                                    onclick="removeRow(this)">
                                                    <i class="bi bi-dash-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="7" class="text-end fw-bold">รวมทั้งหมด (Grand Total)</th>
                                            <th class="text-end fw-bold kitchen-grand-total">0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                                <button type="button" class="btn btn-hotel-outline btn-sm mt-1"
                                    onclick="addKitchenRow()">
                                    <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการครัว
                                </button>
                            </div>

                            <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2" rows="3"
                                placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                        </div>
                    </div>

                    <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 4.
                        รายละเอียดเมนูอาหารและเครื่องดื่ม
                    </h5>
                    <div class="table-responsive mb-5">
                        <table class="table table-sm table-hover align-middle border" id="menuTable">
                            <thead class="text-center text-secondary bg-light">
                                <tr>
                                    <th width="10%">เวลา</th>
                                    <th width="14%">ประเภทเมนู</th>
                                    <th>รายละเอียด</th>
                                    <th width="10%">จำนวน</th>
                                    <th width="12%">ราคาขาย/หน่วย</th>
                                    <th width="10%">ราคาทุน/หน่วย</th>
                                    <th width="12%">ยอดรวม</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quote_items)): ?>
                                    <?php foreach ($quote_items as $item): 
                                        $menu_qty = (float)($item['quantity'] ?? 0);
                                        $menu_price = (float)($item['unit_price'] ?? 0);
                                        $menu_total = $menu_qty * $menu_price;
                                    ?>
                                        <tr>
                                            <td><input type="date" name="menu_time[]"
                                                    class="form-control form-control-sm border-0" 
                                                    value="<?= $quote_data['event_date'] ?>"></td>
                                            <td>
                                                <input type="hidden" name="menu_set_id[]" class="menu-type-id" value="<?= $item['menu_set_id'] ?? '' ?>">
                                                <button type="button" class="btn-menu-type-picker" onclick="openTemplateModalForRow(this, 'template-menu', 'onMenuTypeSelect')"><i class="bi bi-grid-3x3-gap me-1"></i>เลือก</button>
                                                <span class="menu-type-label <?= ($item['menu_set_id'] ?? '') ? 'fw-semibold text-dark' : 'text-muted' ?>"><?= htmlspecialchars($item['menu_set_id'] ? ($menu_types_array[array_search($item['menu_set_id'], array_column($menu_types_array, 'id'))]['type_name'] ?? '') : 'ยังไม่ได้เลือก') ?></span>
                                            </td>
                                            <td>
                                                <textarea name="menu_detail[]"
                                                    class="form-control form-control-sm border-0 menu-detail-input"
                                                    rows="1"><?= htmlspecialchars($item['item_name']) ?></textarea>
                                            </td>
                                            <td><input type="text" name="menu_qty[]"
                                                    class="form-control form-control-sm border-0 menu-qty"
                                                    value="<?= $item['quantity'] ?>"
                                                    oninput="updateMenuRowTotal(this)">
                                            </td>
                                            <td><input type="text" name="menu_price[]"
                                                    class="form-control form-control-sm border-0 menu-price"
                                                    value="<?= number_format($item['unit_price'], 2, '.', '') ?>"
                                                    oninput="updateMenuRowTotal(this)"></td>
                                            <td><input type="number" name="menu_cost[]"
                                                    class="form-control form-control-sm border-0 menu-cost"
                                                    placeholder="0.00" step="0.01"></td>
                                            <td class="text-end fw-bold menu-row-total"><?php echo number_format($menu_total, 2); ?></td>
                                            <td class="text-center"><button type="button"
                                                    class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i
                                                        class="bi bi-dash-circle"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><input type="date" name="menu_time[]"
                                                class="form-control form-control-sm border-0" placeholder="10:30"></td>
                                        <td>
                                            <input type="hidden" name="menu_set_id[]" class="menu-type-id" value="">
                                            <button type="button" class="btn-menu-type-picker" onclick="openTemplateModalForRow(this, 'template-menu', 'onMenuTypeSelect')"><i class="bi bi-grid-3x3-gap me-1"></i>เลือก</button>
                                            <span class="menu-type-label text-muted">ยังไม่ได้เลือก</span>
                                        </td>
                                        <td>
                                            <textarea name="menu_detail[]"
                                                class="form-control form-control-sm border-0 menu-detail-input"
                                                rows="1"></textarea>
                                        </td>
                                        <td><input type="text" name="menu_qty[]"
                                                class="form-control form-control-sm border-0 menu-qty"
                                                oninput="updateMenuRowTotal(this)">
                                        </td>
                                        <td><input type="text" name="menu_price[]"
                                                class="form-control form-control-sm border-0 menu-price"
                                                placeholder="0.00" oninput="updateMenuRowTotal(this)"></td>
                                        <td><input type="number" name="menu_cost[]"
                                                class="form-control form-control-sm border-0 menu-cost"
                                                placeholder="0.00" step="0.01"></td>
                                        <td class="text-end fw-bold menu-row-total">0.00</td>
                                        <td class="text-center"><button type="button"
                                                class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i
                                                    class="bi bi-dash-circle"></i></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light bg-light">
                                <tr>
                                    <th colspan="7" class="text-end fw-bold">รวมทั้งหมด (Grand Total)</th>
                                    <th class="text-end fw-bold menu-grand-total">0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addMenuRow()"><i
                                class="bi bi-plus-lg me-1"></i> เพิ่มรายการอาหาร</button>
                    </div>

                    <div class="row mb-5">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <div class="bg-sidebar p-4 rounded-4 h-100">
                                <h5 class="section-title mb-4"><i class="bi bi-building"></i> 5. รูปแบบการจัดงาน (SET-UP)</h5>
                                <div class="mb-0">
                                    <label class="fw-bold small text-muted">การจัดงานเลี้ยง:</label>
                                    <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                        rows="6"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="bg-sidebar p-4 rounded-4 h-100">
                                <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 6. ระบบวิศวกรรม (TECHNICAL)</h5>
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
                    </div>

                    <h5 class="section-title"><i class="bi bi-palette-fill"></i> 7. การตกแต่งและการดูแลทำความสะอาด
                    </h5>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="p-4 border rounded-4 bg-white h-100">
                                <label class="fw-bold small text-muted mb-3">รายละเอียดฉากหลังและป้าย:</label>
                                <textarea name="backdrop_detail" class="form-control form-control-sm mb-3"
                                    rows="3"></textarea>
                                <div class="p-3 border-dashed text-center bg-light rounded-3">
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
                        <div class="col-lg-6">
                            <div class="p-4 border rounded-4 bg-white h-100">
                                <label class="fw-bold small text-muted mb-3">พนักงานทำความสะอาดและพนักงานจัดดอกไม้:</label>
                                <textarea name="hk_florist_detail" class="form-control form-control-sm"
                                    rows="6"></textarea>
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
            if (typeof updateKitchenGrandTotal === 'function') updateKitchenGrandTotal();
            if (typeof updateMenuGrandTotal === 'function') updateMenuGrandTotal();
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
            <input type="hidden" name="menu_set_id[]" class="menu-type-id" value="">
            <button type="button" class="btn-menu-type-picker" onclick="openTemplateModalForRow(this, 'template-menu', 'onMenuTypeSelect')"><i class="bi bi-grid-3x3-gap me-1"></i>เลือก</button>
            <span class="menu-type-label text-muted">ยังไม่ได้เลือก</span>
        </td>
        <td>
            <textarea name="menu_detail[]"
                class="form-control form-control-sm border-0 menu-detail-input"
                rows="1"></textarea>
        </td>
        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0 menu-qty" oninput="updateMenuRowTotal(this)"></td>
        <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0 menu-price"
                placeholder="0.00" oninput="updateMenuRowTotal(this)"></td>
        <td><input type="number" name="menu_cost[]" class="form-control form-control-sm border-0 menu-cost"
                placeholder="0.00" step="0.01"></td>
        <td class="text-end fw-bold menu-row-total">0.00</td>
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
        <td><select name="k_type_id[]" class="form-select form-select-sm border-0 bg-light" onchange="fetchBreakMenu(this)">${breakOptions}</select></td>
        <td><textarea name="k_item[]" class="form-control form-control-sm border-0 bg-light break-menu-input" rows="3" onfocus="initFirstLine(this)"></textarea></td>
        <td><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center kitchen-qty" placeholder="0" oninput="updateKitchenRowTotal(this)"></td>
        <td><input type="number" name="k_price[]" class="form-control form-control-sm border-0 bg-light text-end kitchen-price" placeholder="0.00" step="0.01" oninput="updateKitchenRowTotal(this)"></td>
        <td><input type="number" name="k_cost[]" class="form-control form-control-sm border-0 bg-light text-end kitchen-cost" placeholder="0.00" step="0.01"></td>
        <td class="text-end fw-bold kitchen-row-total">0.00</td>
        <td> <button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)">
                                                <i class="bi bi-dash-circle"></i>
                                            </button></td>`;
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
            <div class="col-lg-3 col-md-4">

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

    // --- Select2 AJAX for customer search ---
    function initCustomerSelect() {
        var $sel = $('#customer_selector');
        if (!$sel.length) return;
        if (!$.fn.select2) { setTimeout(initCustomerSelect, 200); return; }
        $sel.select2({
            width: '100%',
            placeholder: '-- ค้นหาลูกค้า --',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: 'api/search_customers.php?t=' + new Date().getTime(),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text,
                            cust_name: item.cust_name,
                            cust_phone: item.cust_phone,
                            cust_address: item.cust_address
                        };
                    })};
                }
            },
            templateSelection: function(data) {
                return data.text || data.cust_name || '-- ค้นหาลูกค้า --';
            }
        }).on('select2:select', function(e) {
            var data = e.params.data;
            document.getElementById('customer_id_hidden').value = data.id;
            document.getElementById('booking_name').value = data.cust_name || data.text || '';
            document.getElementById('customer_phone').value = data.cust_phone || '';
            document.getElementById('customer_address').value = data.cust_address || '';
        }).on('select2:clear', function() {
            document.getElementById('customer_id_hidden').value = '';
            document.getElementById('booking_name').value = '';
            document.getElementById('customer_phone').value = '';
            document.getElementById('customer_address').value = '';
        });
    }
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

    initCustomerSelect();
</script>
<?php include "includes/menu_type_modal.php"; ?>
<?php include "footer.php"; ?>