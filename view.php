<?php
include "config.php";

// 1. รับค่า ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. ดึงข้อมูลจาก Session (ทำให้เป็นตัวเล็กทั้งหมดเพื่อเทียบง่ายๆ)
$current_user_name = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
$user_role = strtolower(trim($_SESSION['role'] ?? 'staff'));

// 3. ดึงข้อมูลจากฐานข้อมูล
// 3. ดึงข้อมูลจากฐานข้อมูล (ปรับใหม่ให้ JOIN ครบทุกอย่าง)
$sql = "SELECT f.*, 
               c.company_name, c.logo_path,
               ft.type_name as function_type_name, ft.prefix as type_prefix,
               r.room_name as master_room_name
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id 
        LEFT JOIN function_types ft ON f.function_type_id = ft.id 
        LEFT JOIN meeting_rooms r ON f.room_id = r.id
        WHERE f.id = $id";

$res = $conn->query($sql);
$data = $res->fetch_assoc();

if (!$data) {
    die("ไม่พบข้อมูลรายการนี้ในระบบ!");
}

// ดึงชื่อคนสร้างจาก DB มาตัดช่องว่าง
$created_by_db = trim($data['created_by']);


if ($user_role !== 'admin' && $user_role !== 'gm' && $user_role !== 'viewer' && $user_role !== 'technician' && $user_role !== 'housekeeping' && $user_role !== 'procurement') {
    if ($created_by_db !== $current_user_name) {
        header("Location: access_denied.php");
        exit();
    }
}

// ✅ ย้าย Header ออกมาข้างนอก ให้ทุกคนโหลดได้เหมือนกัน
require_once "header.php";

// --- ผ่านด่านแล้ว ทำงานต่อด้านล่าง ---

// 1. ดึง ID คนอนุมัติจากข้อมูลที่มีอยู่
$approver_id = $data['approve_by'] ?? null;
$approver_name = '(..........................................)'; // ค่าเริ่มต้นถ้ายังไม่มีใครอนุมัติ

if ($approver_id && is_numeric($approver_id)) {
    // 2. ไปดึงชื่อจากตาราง users
    $sql_user = "SELECT name FROM users WHERE id = ? LIMIT 1";
    if ($stmt_user = $conn->prepare($sql_user)) {
        $stmt_user->bind_param("i", $approver_id);
        $stmt_user->execute();
        $res_user = $stmt_user->get_result();
        if ($user_row = $res_user->fetch_assoc()) {
            $approver_name = $user_row['name']; // ได้ชื่อมาแล้ว!
        }
        $stmt_user->close();
    }
}


// --- 🚀 สำหรับผู้จัดทำ (Event Organizer) ---
$creator_sig = "";
// เปลี่ยนมาดึงจาก created_by_id ที่จารเพิ่งเพิ่มลงในตาราง functions
$creator_id = intval($data['created_by_id'] ?? 0);

if ($creator_id > 0) {
    // จารครับ Query นี้จะแม่นยำที่สุด เพราะเชื่อมด้วย Primary Key (ID)
    $sql_c = "SELECT path FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1";

    if ($stmt_c = $conn->prepare($sql_c)) {
        $stmt_c->bind_param("i", $creator_id);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();

        if ($row_c = $res_c->fetch_assoc()) {
            $creator_sig = $row_c['path']; // ได้ path รูปมาแล้ว
        }
        $stmt_c->close();
    }
}
// --- 🚀 สำหรับผู้อนุมัติ (Authorized By) ---
// 1. ดึง ID ผู้อนุมัติจากคอลัมน์ approve_by ในฐานข้อมูล
$approver_id = intval($data['approve_by'] ?? 0);
$approver_sig = "";

// --- 🔎 เช็คว่าผู้ใช้ที่ login อยู่มีลายเซ็นหรือยัง ---
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_has_sig = false;
if ($current_user_id > 0) {
    $sql_check_sig = "SELECT id FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1";
    if ($stmt_check = $conn->prepare($sql_check_sig)) {
        $stmt_check->bind_param("i", $current_user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $current_user_has_sig = true;
        }
        $stmt_check->close();
    }
}

// 2. ถ้ามี ID ผู้อนุมัติ (ค่ามากกว่า 0) ให้ไปค้นหาลายเซ็น
if ($approver_id > 0) {
    // จารครับ ผมใช้ users_id เพื่อดึงลายเซ็นล่าสุดของคนๆ นั้นออกมา
    $sql_a = "SELECT path FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1";

    if ($stmt_a = $conn->prepare($sql_a)) {
        $stmt_a->bind_param("i", $approver_id); // ใช้ $approver_id ให้ตรงกับที่ดึงมาข้างบน
        $stmt_a->execute();
        $res_a = $stmt_a->get_result();

        if ($row_a = $res_a->fetch_assoc()) {
            $approver_sig = $row_a['path']; // ได้ path รูปมาแล้ว
        }
        $stmt_a->close();
    }
}
// ฟังก์ชันตรวจสอบ Path เพื่อความปลอดภัย
function displaySignature($path)
{
    if (empty($path))
        return "";
    // ถ้าใน DB เก็บแค่ชื่อไฟล์ เช่น "sig1.png" ให้เติม path
    // แต่ถ้าเก็บเต็ม "uploads/signatures/sig1.png" อยู่แล้วก็ใช้ได้เลย
    return (strpos($path, 'uploads/') !== false) ? $path : "uploads/signatures/" . $path;
}
// ฟังก์ชันแปลงวันที่เป็นแบบไทย เช่น 1 เม.ย. 2567
function thai_date($dateStr, $withTime = false)
{
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if (!$ts) return $dateStr;
    $thaiMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $d = (int)date('j', $ts);
    $m = $thaiMonths[(int)date('n', $ts)];
    $y = (int)date('Y', $ts) + 543;
    $out = $d . ' ' . $m . ' ' . $y;
    if ($withTime) $out .= ' เวลา ' . date('H:i', $ts);
    return $out;
}
// ลองเปลี่ยน k_type_id เป็นชื่อคอลัมน์จริงๆ ใน DB ของจาร
$sql_kitchens = "SELECT fk.*, mbt.type_name as k_type_name 
                 FROM function_kitchens fk 
                 LEFT JOIN master_break_types mbt ON fk.k_type_id = mbt.id 
                 WHERE fk.function_id = $id";

$kitchens = $conn->query($sql_kitchens);

$sql_menus = "SELECT fm.*, mms.type_name as set_name, mmc.category_name
              FROM function_menus fm
              LEFT JOIN master_menu_types mms ON fm.menu_set_id = mms.id 
              LEFT JOIN master_menu_categories mmc ON mms.category_id = mmc.id
              WHERE fm.function_id = $id";

$menus = $conn->query($sql_menus);
?>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<link rel="stylesheet" href="style/banquet_print.css">
<style>
    .sig-space {
        height: 60px;
        /* ความสูงพื้นที่ลายเซ็น */
        display: flex;
        align-items: flex-end;
        /* ให้รูปชิดขอบล่าง (บนเส้นบรรทัด) */
        justify-content: center;
        margin-bottom: 2px;
    }

    .sig-img {
        max-height: 55px;
        /* คุมความสูงไม่ให้ล้น */
        width: auto;
        object-fit: contain;
    }

    /* เส้นแบ่งระหว่างหัวข้อ (toggle จากปุ่มปรับแต่ง) */
    #printableArea.section-divider .section-group:not(.no-frame) {
        border: 2px solid #333;
        border-radius: 0;
        margin-top: 10px;
        padding: 10px;
    }

    /* หัวข้อตัวหนา */
    #printableArea.bold-titles .section-title {
        font-weight: 800 !important;
    }

    /* ซ่อนราคาทุน (คอลัมน์ที่ 6 ของตารางเบรก/เมนู) */
    #printableArea.hide-cost th:nth-child(6),
    #printableArea.hide-cost td:nth-child(6) {
        display: none !important;
    }

    /* หัวข้อสั้น: ซ่อนคำภาษาอังกฤษในวงเล็บ */
    #printableArea.short-titles .section-en {
        display: none !important;
    }

    /* ซ่อนโลโก้ */
    #printableArea.hide-logo .doc-logo {
        display: none !important;
    }

    /* ซ่อนหมายเหตุครัว */
    #printableArea.hide-kitchen .kitchen-remark {
        display: none !important;
    }

    /* ฟองคำพูดด้านซ้ายของปุ่ม */
    .custom-btn-pill {
        position: relative;
    }

    .speech-bubble {
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 10px;
        background: #1a1a1a;
        color: #fff;
        padding: 6px 12px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        z-index: 9999;
        pointer-events: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .speech-bubble::after {
        content: '';
        position: absolute;
        right: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 6px solid transparent;
        border-right-color: #1a1a1a;
    }

    .speech-bubble.warning {
        background: #b89441;
    }

    .speech-bubble.warning::after {
        border-right-color: #b89441;
    }
</style>


<div class="no-print"
    style="position: fixed; top: 100px; left: calc(50% + 105mm); transform: translateX(180px); z-index: 9999;">
    <div class="position-relative">
        <div class="bg-white p-2 rounded-pill  border border-gold-soft d-flex flex-column align-items-center gap-1">

            <button onclick="toggleCustomizePanel()"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="ปรับแต่งเอกสารก่อนพิมพ์">
                <i class="bi bi-sliders text-secondary fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">ปรับแต่ง</span>
            </button>

            <div class="hr-custom w-75 border-top opacity-25"></div>

            <button onclick="window.print()"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="พิมพ์">
                <i class="bi bi-printer-fill text-secondary fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">พิมพ์</span>
            </button>

            <div class="hr-custom w-75 border-top opacity-25"></div>

            <button onclick="window.location.href='signature_page.php?id=<?php echo $id; ?>'"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="จัดการลายเซ็น">
                <i class="bi bi-pen-fill text-info fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">ลายเซ็น</span>
                <?php if (!$current_user_has_sig): ?>
                <span class="speech-bubble warning">คุณยังไม่มีลายเซ็นนะ เพิ่มตรงนี้สิ</span>
                <?php endif; ?>
            </button>


            <div class="hr-custom w-75 border-top opacity-25"></div>

            <button onclick="downloadPDF(this)"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="ดาวน์โหลด PDF">
                <i class="bi bi-file-pdf-fill text-danger fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">PDF</span>
                <span class="speech-bubble">อยู่ระหว่างพัฒนา</span>
            </button>

            <div class="hr-custom w-75 border-top opacity-25"></div>

            <button onclick="exportToWord()"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="ส่งออก Word">
                <i class="bi bi-file-earmark-word-fill text-primary fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">Word</span>
                <span class="speech-bubble">อยู่ระหว่างพัฒนา</span>
            </button>




            <div class="hr-custom w-75 border-top opacity-25"></div>

            <button onclick="exportToDoc()"
                class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
                title="ส่งออกเอกสาร">
                <i class="bi bi-file-earmark-richtext-fill text-warning fs-5"></i>
                <span style="font-size: 10px;" class="fw-bold">DOC</span>
            </button>

        </div>

        <!-- Panel ปรับแต่งเอกสาร -->
        <div id="customizePanel"
            class="no-print bg-white shadow-lg rounded-3 border"
            style="display: none; position: absolute; top: 0; right: calc(100% + 12px); width: 250px; z-index: 9999; padding: 14px;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-sliders me-1 text-gold"></i>ปรับแต่งเอกสาร</h6>
                <button type="button" class="btn-close" onclick="toggleCustomizePanel()"></button>
            </div>
            <hr class="my-2">

            <label class="form-label small fw-bold text-secondary mb-1 d-flex justify-content-between align-items-center">
                <span>ขนาดตัวอักษร</span>
                <span id="fontSizeLabel" class="badge bg-dark" style="font-size: 10px;">ปกติ (10px)</span>
            </label>
            <input type="range" class="form-range" id="fontSizeSlider" min="7" max="14" step="0.5" value="10">
            <div class="d-flex justify-content-between text-muted mb-2" style="font-size: 10px;">
                <span>เล็ก</span><span>ปกติ</span><span>ใหญ่</span>
            </div>

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="dividerToggle">
                <label class="form-check-label small" for="dividerToggle">เส้นแบ่งระหว่างหัวข้อ</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="boldTitleToggle">
                <label class="form-check-label small" for="boldTitleToggle">หัวข้อตัวหนา</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="hideCostToggle">
                <label class="form-check-label small" for="hideCostToggle">ซ่อนราคาทุน</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="shortTitleToggle">
                <label class="form-check-label small" for="shortTitleToggle">หัวข้อสั้น (ซ่อนภาษาอังกฤษ)</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="hideLogoToggle">
                <label class="form-check-label small" for="hideLogoToggle">ซ่อนโลโก้</label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="hideKitchenToggle">
                <label class="form-check-label small" for="hideKitchenToggle">ซ่อนหมายเหตุครัว</label>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetPrintSettings()">
                <i class="bi bi-arrow-counterclockwise me-1"></i> รีเซ็ตค่าเริ่มต้น
            </button>
        </div>
    </div>
</div>

<div id="printableArea">
    <div class="section-group no-frame">
        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1">
            <div class="d-flex align-items-center">
                <img src="<?php echo !empty($data['logo_path']) ? $data['logo_path'] : 'assets/img/default-company.png'; ?>"
                    style="max-height: 50px; max-width: 100px;" class="me-3 doc-logo">
                <div>
                    <?php if ($data['approve'] == 1): ?>
                        <h5 class="mb-0 fw-bold text-dark">EVENT ORDER</h5>
                    <?php else: ?>
                        <h5 class="mb-0 fw-bold text-dark">QUOTATION</h5>
                    <?php endif; ?>
                    <p class="mb-0 text-muted" style="font-size: 9px;"><?php echo htmlspecialchars($data['company_name']); ?></p>
                </div>
            </div>
            <div class="text-end">
                <div class="p-1 border rounded bg-light text-center" style="min-width: 130px;">
                    <small class="text-muted d-block" style="font-size: 8px;">DOCUMENT NO.</small>
                    <span class="fw-bold " style="font-size: 14px;"><?php echo htmlspecialchars($data['type_prefix']) . htmlspecialchars($data['function_code']); ?></span>
                </div>
            </div>
        </div>
        <div class="section-group">
            <div class="section-title">1. ข้อมูลการจองทั่วไป <span class="section-en">(GENERAL INFORMATION)</span></div>
            <div class="row g-2 mb-2">
                <div class="col-7">
                    <div class="row g-2">
                        <div class="col-12"><strong>ชื่องาน:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['function_name']); ?> 
                                <span class="badge bg-secondary rounded-pill fw-normal ms-1" style="font-size: 8px;">
                                    <?= htmlspecialchars($data['draft_name'] ?? 'Draft V1') ?>
                                </span>
                            </span></div>
                        <div class="col-12"><strong>ประเภทงาน:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['function_type_name'] ?? '-'); ?></span></div>

                        <div class="col-6"><strong>ผู้จอง:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['booking_name']); ?></span></div>
                        <div class="col-6"><strong>เบอร์โทร:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['phone']); ?></span></div>

                        <div class="col-12"><strong>หน่วยงาน/ที่อยู่:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['organization']); ?></span></div>
                    </div>
                </div>
                <div class="col-5 border-start ps-3">
                    <div class="row g-2">
                        <div class="col-12"><strong>สถานที่ประชุม:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['master_room_name'] ?? $data['room_name']); ?></span>
                        </div>

                        <div class="col-12"><strong>จำนวนผู้เข้าร่วม (PAX):</strong> <span
                                class="data-value fw-bold text-danger"><?php echo number_format($data['pax'] ?? 0); ?>
                                ท่าน</span></div>

                        <div class="col-12"><strong>Booking Room:</strong> <span
                                class="data-value"><?php echo htmlspecialchars($data['booking_room'] ?? '-'); ?></span></div>
                        <div class="col-12 text-primary"><strong>เงินมัดจำ (Deposit):</strong> <span
                                class="data-value"><?php echo number_format($data['deposit'] ?? 0, 2); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="section-group">
        <div class="section-title">2. ตารางกำหนดการ <span class="section-en">(SCHEDULE)</span></div>
        <table class="table table-sm table-bordered table-tight mb-0">
            <thead class="table-light text-center">
                <tr>
                    <th width="15%">วันที่</th>
                    <th width="15%">เวลา</th>
                    <th>รายละเอียด</th>
                    <th width="12%">จำนวน</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id");
                while ($row = $schedules->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><?php echo thai_date($row['schedule_date']); ?></td>
                        <td class="text-center"><?php echo $row['schedule_hour']; ?></td>
                        <td><?php echo nl2br($row['schedule_function']); ?></td>
                        <td class="text-center fw-bold"><?php echo number_format($row['schedule_guarantee']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="section-group mb-0">
        <div class="section-title">3. รายการเบรก</div>
        <table class="table table-sm table-bordered table-tight mb-0">
            <thead class="table-light text-center">
                <tr>
                    <th width="15%">วันที่</th>
                    <th width="15%">ประเภท</th>
                    <th>รายการอาหาร</th>
                    <th width="10%">จำนวน</th>
                    <th width="12%">ราคาขาย/หน่วย</th>
                    <th width="12%">ราคาทุน/หน่วย</th>
                    <th width="12%">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ลบบรรทัด $kitchens = $conn->query(...) ออกไปเลยครับ เพราะเราทำไว้ข้างบนแล้ว
                while ($row = $kitchens->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><?php echo thai_date($row['k_date']); ?></td>

                        <td><?php echo $row['k_type_name'] ?? 'ไม่ระบุ'; ?></td>

                        <td><?php echo nl2br($row['k_item']); ?></td>
                        <td class="text-center"><?php echo number_format($row['k_qty']); ?></td>
                        <td class="text-end"><?php echo number_format($row['k_price'] ?? 0, 2); ?></td>
                        <td class="text-end text-danger"><?php echo number_format($row['k_cost'] ?? 0, 2); ?></td>
                        <td class="text-end fw-bold">
                            <?php 
                            $qty = (float)($row['k_qty'] ?? 0);
                            $price = (float)($row['k_price'] ?? 0);
                            echo number_format($qty * $price, 2);
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="p-2 border rounded bg-light kitchen-remark" style="font-size: 8.5px; mb-0">
            <strong>หมายเหตุครัว:</strong> <?php echo nl2br(htmlspecialchars($data['main_kitchen_remark'] ?? '-')); ?>
        </div>
    </div>

    <div class="row g-2">
        <div class="col-6">
            <div class="section-group">
                <div class="section-title">4. รูปแบบการจัดงาน <span class="section-en">(SET-UP)</span></div>
                <div class="box-detail">
                    <?php echo nl2br(htmlspecialchars($data['banquet_style'] ?? 'ตามมาตรฐาน')); ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="section-group">
                <div class="section-title">5. ระบบวิศวกรรม <span class="section-en">(TECHNICAL)</span></div>
                <div class="box-detail"><?php echo nl2br(htmlspecialchars($data['equipment'] ?? '-')); ?></div>
            </div>
        </div>
    </div>
    <div class="section-group">
        <div class="section-title">6. รายละเอียดเมนูอาหารและเครื่องดื่ม <span class="section-en">(FOOD &amp; BEVERAGE DETAILS)</span></div>
        <table class="table table-sm table-bordered table-tight mb-0">
            <thead class="table-light text-center">
                <tr>
                    <th width="10%">เวลา</th>
                    <th width="15%">ประเภทเมนู</th>
                    <th>รายละเอียดเมนู</th>
                    <th width="10%">จำนวน</th>
                    <th width="12%">ราคาขาย/หน่วย</th>
                    <th width="12%">ราคาทุน/หน่วย</th>
                    <th width="12%">ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $menu_grand_total = 0;
                while ($row = $menus->fetch_assoc()):
                    $menu_qty = (float)($row['menu_qty'] ?? 0);
                    $menu_price = (float)($row['menu_price'] ?? 0);
                    $menu_total = $menu_qty * $menu_price;
                    $menu_grand_total += $menu_total;
                ?>
                    <tr>
                        <td class="text-center"><?php echo thai_date($row['menu_time']); ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['category_name'] ?? ($row['set_name'] ?? '-')) ?></td>
                        <td><?php echo nl2br($row['menu_detail']); ?></td>
                        <td class="text-center fw-bold"><?php echo number_format($row['menu_qty']); ?></td>
                        <td class="text-end"><?php echo number_format($row['menu_price'], 2); ?></td>
                        <td class="text-end text-danger"><?php echo number_format($row['menu_cost'] ?? 0, 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($menu_total, 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="6" class="text-end fw-bold">รวมทั้งหมด (Grand Total)</th>
                    <th class="text-end fw-bold"><?php echo number_format($menu_grand_total, 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="row g-2">
        <div class="col-6">
            <div class="section-group">
                <div class="section-title">7. ป้ายชื่อและฉาก <span class="section-en">(BACKDROP &amp; SIGNAGE)</span></div>
                <div class="box-detail mb-1"><?php echo nl2br(htmlspecialchars($data['backdrop_detail'] ?? '-')); ?></div>
                <?php if (!empty($data['backdrop_img'])): ?>
                    <div class="text-center border p-1 rounded bg-white mt-1">
                        <img src="<?php echo htmlspecialchars($data['backdrop_img']); ?>" style="max-height: 80px; max-width: 100%;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6">
            <div class="section-group">
                <div class="section-title">8. แม่บ้านและดอกไม้ <span class="section-en">(FLORIST &amp; HK)</span></div>
                <div class="box-detail" style="min-height: 60px;">
                    <?php echo nl2br(htmlspecialchars($data['hk_florist_detail'] ?? '-')); ?>
                </div>
                <div class="mt-2 p-1 border-start border-warning bg-light" style="font-size: 9px;">
                    <strong>หมายเหตุอื่นๆ:</strong> <?php echo htmlspecialchars($data['remark'] ?? '-'); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="signature-wrapper">
        <div class="row mt-5 text-center" style="font-size: 10px;">
            <div class="col-4 text-center">
                <div class="sig-space">
                    <?php if (!empty($creator_sig)): ?>
                        <img src="<?php echo $creator_sig; ?>" class="sig-img" style="max-height: 50px;">

                    <?php else: ?>
                        <p style="color:red; font-size:8px;"></p>
                    <?php endif; ?>
                </div>
                <div class="mx-auto border-top w-75 pt-1">
                    <div class="fw-bold"><?php echo htmlspecialchars($data['created_by'] ?? '-'); ?></div>
                    ผู้จัดทำ (Event Organizer)
                </div>
                <small class="text-muted">วันที่: <?php echo thai_date($data['created_at'] ?? '', true); ?></small>
            </div>

            <div class="col-4 text-center">
                <div class="sig-space">
                    <?php if (!empty($approver_sig)): ?>
                        <img src="<?php echo $approver_sig; ?>" class="sig-img" style="max-height: 50px;">

                    <?php else: ?>
                        <p style="color:red; font-size:8px;"></p>
                    <?php endif; ?>
                </div>
                <div class="mx-auto border-top w-75 pt-1">
                    <div class="fw-bold"><?php echo htmlspecialchars($approver_name); ?></div>
                    ผู้อนุมัติ (Authorized By)
                </div>
                <small class="text-muted">
                    วันที่:
                    <?php
                    $approve_date_raw = $data['approve_date'] ?? '';
                    if ($data['approve'] == 1 && !empty($approve_date_raw) && $approve_date_raw !== '0000-00-00 00:00:00') {
                        echo thai_date($approve_date_raw, true);
                    } else {
                        echo '______/______/______';
                    }
                    ?>
                </small>
            </div>

            <div class="col-4 text-center">
                <div class="sig-space"></div>
                <div class="mx-auto border-top w-75 pt-1">
                    <div class="fw-bold"><?php echo htmlspecialchars($data['booking_name'] ?? '-'); ?></div>
                    ลูกค้า (Customer)
                </div>
                <small class="text-muted">วันที่: ______/______/______</small>
            </div>
        </div>
    </div>

    <?php if (!empty($data['cancel_reason'])): ?>
    <div class="section-group mt-3" style="border: 1px solid #fecaca; background: #fef2f2; border-radius: 6px; padding: 10px;">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-x-circle-fill text-danger"></i>
            <strong class="text-danger" style="font-size: 10px;">เหตุผลการยกเลิก</strong>
        </div>
        <p class="mb-0 text-danger" style="font-size: 9px;"><?= nl2br(htmlspecialchars($data['cancel_reason'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
    // ฟังก์ชันสำหรับ Export เป็น Word (อยู่ระหว่างพัฒนา)
    function exportToWord() {
        return;
    }

    // ฟังก์ชัน PDF (อยู่ระหว่างพัฒนา)
    function downloadPDF(btn) {
        return;
    }

</script>
<script>
    // --- ปุ่มปรับแต่งเอกสาร ---
    function toggleCustomizePanel() {
        const p = document.getElementById('customizePanel');
        if (p) p.style.display = (p.style.display === 'block') ? 'none' : 'block';
    }

    function printFontSizeLabel(v) {
        return v == 10 ? 'ปกติ (10px)' : (v + 'px');
    }

    function applyPrintSettings() {
        const root = document.documentElement;
        const area = document.getElementById('printableArea');
        if (!area) return;

        const fs = localStorage.getItem('printFontSize');
        if (fs) {
            root.style.setProperty('--print-fs', fs + 'px');
            const s = document.getElementById('fontSizeSlider');
            const l = document.getElementById('fontSizeLabel');
            if (s) s.value = fs;
            if (l) l.textContent = printFontSizeLabel(fs);
        }

        const t = document.getElementById('dividerToggle');
        if (localStorage.getItem('printDivider') === '1') { area.classList.add('section-divider'); if (t) t.checked = true; }
        const b = document.getElementById('boldTitleToggle');
        if (localStorage.getItem('printBold') === '1') { area.classList.add('bold-titles'); if (b) b.checked = true; }
        const h = document.getElementById('hideCostToggle');
        if (localStorage.getItem('printHideCost') === '1') { area.classList.add('hide-cost'); if (h) h.checked = true; }
        const st = document.getElementById('shortTitleToggle');
        if (localStorage.getItem('printShortTitle') === '1') { area.classList.add('short-titles'); if (st) st.checked = true; }
        const lg = document.getElementById('hideLogoToggle');
        if (localStorage.getItem('printHideLogo') === '1') { area.classList.add('hide-logo'); if (lg) lg.checked = true; }
        const kt = document.getElementById('hideKitchenToggle');
        if (localStorage.getItem('printHideKitchen') === '1') { area.classList.add('hide-kitchen'); if (kt) kt.checked = true; }
    }

    function resetPrintSettings() {
        ['printFontSize', 'printDivider', 'printBold', 'printHideCost', 'printShortTitle', 'printHideLogo', 'printHideKitchen'].forEach(k => localStorage.removeItem(k));
        const area = document.getElementById('printableArea');
        if (area) area.classList.remove('section-divider', 'bold-titles', 'hide-cost', 'short-titles', 'hide-logo', 'hide-kitchen');
        document.documentElement.style.setProperty('--print-fs', '10px');
        const s = document.getElementById('fontSizeSlider');
        const l = document.getElementById('fontSizeLabel');
        if (s) s.value = 10;
        if (l) l.textContent = printFontSizeLabel(10);
        const t = document.getElementById('dividerToggle');
        const b = document.getElementById('boldTitleToggle');
        const h = document.getElementById('hideCostToggle');
        const st = document.getElementById('shortTitleToggle');
        const lg = document.getElementById('hideLogoToggle');
        const kt = document.getElementById('hideKitchenToggle');
        if (t) t.checked = false;
        if (b) b.checked = false;
        if (h) h.checked = false;
        if (st) st.checked = false;
        if (lg) lg.checked = false;
        if (kt) kt.checked = false;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const fsSlider = document.getElementById('fontSizeSlider');
        const fsLabel = document.getElementById('fontSizeLabel');
        if (fsSlider && fsLabel) {
            fsSlider.addEventListener('input', function () {
                const v = this.value;
                document.documentElement.style.setProperty('--print-fs', v + 'px');
                fsLabel.textContent = printFontSizeLabel(v);
                localStorage.setItem('printFontSize', v);
            });
        }
        const dt = document.getElementById('dividerToggle');
        if (dt) dt.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('section-divider', this.checked);
            localStorage.setItem('printDivider', this.checked ? '1' : '0');
        });
        const bt = document.getElementById('boldTitleToggle');
        if (bt) bt.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('bold-titles', this.checked);
            localStorage.setItem('printBold', this.checked ? '1' : '0');
        });
        const ht = document.getElementById('hideCostToggle');
        if (ht) ht.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('hide-cost', this.checked);
            localStorage.setItem('printHideCost', this.checked ? '1' : '0');
        });
        const st = document.getElementById('shortTitleToggle');
        if (st) st.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('short-titles', this.checked);
            localStorage.setItem('printShortTitle', this.checked ? '1' : '0');
        });
        const lg = document.getElementById('hideLogoToggle');
        if (lg) lg.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('hide-logo', this.checked);
            localStorage.setItem('printHideLogo', this.checked ? '1' : '0');
        });
        const kt = document.getElementById('hideKitchenToggle');
        if (kt) kt.addEventListener('change', function () {
            document.getElementById('printableArea').classList.toggle('hide-kitchen', this.checked);
            localStorage.setItem('printHideKitchen', this.checked ? '1' : '0');
        });
        applyPrintSettings();
    });

    window.onbeforeprint = function () {
        // ถ้าผู้ใช้ตั้งขนาดตัวอักษรเอง ให้ใช้ค่าที่ตั้งแทนการบีบอัตโนมัติ
        if (localStorage.getItem('printFontSize')) return;

        // 1. นับจำนวนแถว (tr) ทั้งหมดใน printableArea
        const rows = document.querySelectorAll('#printableArea tr').length;
        const root = document.documentElement;

        // 2. ลอจิกการปรับขนาด (จารปรับตัวเลข 30, 45 ได้ตามความยาวงานจาร)
        if (rows > 45) {
            // เนื้อหาเยอะมาก บีบสุดใจ
            root.style.setProperty('--print-fs', '8px');
            root.style.setProperty('--print-pad', '1px 3px');
            root.style.setProperty('--print-lh', '1.1');
        }
        else if (rows > 30) {
            // เนื้อหาเริ่มล้น บีบปานกลาง
            root.style.setProperty('--print-fs', '9px');
            root.style.setProperty('--print-pad', '2px 4px');
            root.style.setProperty('--print-lh', '1.2');
        }
        else {
            // เนื้อหาน้อย โชว์สวยๆ ตัวโตๆ
            root.style.setProperty('--print-fs', '10px');
            root.style.setProperty('--print-pad', '3px 5px');
            root.style.setProperty('--print-lh', '1.3');
        }
    };

    // เมื่อพิมพ์เสร็จ คืนค่าหน้าจอให้กลับมาเป็น Font 10px ปกติ
    window.onafterprint = function () {
        // ถ้าผู้ใช้ตั้งขนาดตัวอักษรเอง ให้คงค่าเดิมไว้
        if (localStorage.getItem('printFontSize')) return;

        const root = document.documentElement;
        root.style.setProperty('--print-fs', '10px');
        root.style.setProperty('--print-pad', '3px 5px');
        root.style.setProperty('--print-lh', '1.3');
    };
</script>
<?php include "footer.php"; ?>