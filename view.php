<?php
include "config.php";

// 1. รับค่า ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. ดึงข้อมูลจาก Session (ทำให้เป็นตัวเล็กทั้งหมดเพื่อเทียบง่ายๆ)
$current_user_name = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
$user_role = strtolower(trim($_SESSION['role'] ?? 'staff'));

// 3. ดึงข้อมูลจากฐานข้อมูล
$sql = "SELECT f.*, c.company_name, c.logo_path 
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id 
        WHERE f.id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();

if (!$data) {
    die("ไม่พบข้อมูลรายการนี้ในระบบ!");
}

// ดึงชื่อคนสร้างจาก DB มาตัดช่องว่าง
$created_by_db = trim($data['created_by']);

// --- 🚀 ส่วนการเช็คสิทธิ์ (Gatekeeper) ---

/**
 * 💡 Logic: 
 * ถ้าไม่ใช่ Admin และไม่ใช่ GM (กลุ่มคนมีสิทธิ์สูง)
 * ค่อยมาเช็คว่าใช่เจ้าของงานไหม ถ้าไม่ใช่เจ้าของงานจริง...ดีดออก!
 */
if ($user_role !== 'admin' && $user_role !== 'gm' && $user_role !== 'viewer') {
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
$approver_name = 'ยังไม่อนุมัติ'; // ค่าเริ่มต้นถ้ายังไม่มีใครอนุมัติ

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
// ค้นหาลายเซ็นจากชื่อ (created_by) ที่เก็บในตาราง functions
$creator_sig = "";
$created_by_name = trim($data['created_by'] ?? '');

if (!empty($created_by_name)) {
    // JOIN ตาราง users (u) เพื่อเอา ID ไปหาใน signatures (s)
    $sql_c = "SELECT s.path 
              FROM users u 
              JOIN signatures s ON u.id = s.users_id 
              WHERE u.name = ? 
              ORDER BY s.id DESC LIMIT 1";

    if ($stmt_c = $conn->prepare($sql_c)) {
        $stmt_c->bind_param("s", $created_by_name);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($row_c = $res_c->fetch_assoc()) {
            $creator_sig = $row_c['path'];
        }
        $stmt_c->close();
    }
}

// --- 🚀 สำหรับผู้อนุมัติ (Authorized By) ---
// ค้นหาลายเซ็นจาก approve_by (ซึ่งเป็น int ID อยู่แล้ว)
$approver_id = $data['approve_by'] ?? 0;
$approver_sig = "";

if ($approver_id > 0) {
    $sql_a = "SELECT path FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1";
    if ($stmt_a = $conn->prepare($sql_a)) {
        $stmt_a->bind_param("i", $approver_id);
        $stmt_a->execute();
        $res_a = $stmt_a->get_result();
        if ($row_a = $res_a->fetch_assoc()) {
            $approver_sig = $row_a['path'];
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
</style>


<div class="no-print"
    style="position: fixed; top: 100px; left: calc(50% + 105mm); transform: translateX(180px); z-index: 9999;">
    <div class="bg-white p-2 rounded-pill  border border-gold-soft d-flex flex-column align-items-center gap-1">

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
        </button>


        <div class="hr-custom w-75 border-top opacity-25"></div>

        <button onclick="downloadPDF(this)"
            class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
            title="ดาวน์โหลด PDF">
            <i class="bi bi-file-pdf-fill text-danger fs-5"></i>
            <span style="font-size: 10px;" class="fw-bold">PDF</span>
        </button>

        <div class="hr-custom w-75 border-top opacity-25"></div>

        <button onclick="exportToWord()"
            class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
            title="ส่งออก Word">
            <i class="bi bi-file-earmark-word-fill text-primary fs-5"></i>
            <span style="font-size: 10px;" class="fw-bold">Word</span>
        </button>




        <div class="hr-custom w-75 border-top opacity-25"></div>

        <button onclick="exportToDoc()"
            class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
            title="ส่งออกเอกสาร">
            <i class="bi bi-file-earmark-richtext-fill text-warning fs-5"></i>
            <span style="font-size: 10px;" class="fw-bold">DOC</span>
        </button>

    </div>
</div>

<div id="printableArea">
    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1">
        <div class="d-flex align-items-center">
            <img src="<?php echo !empty($data['logo_path']) ? $data['logo_path'] : 'assets/img/default-company.png'; ?>"
                style="max-height: 50px; max-width: 100px;" class="me-3">
            <div>
                <h5 class="mb-0 fw-bold text-dark">FUNCTION MEETING</h5>
                <p class="mb-0 text-muted" style="font-size: 9px;"><?php echo $data['company_name']; ?></p>
            </div>
        </div>
        <div class="text-end">
            <div class="p-1 border rounded bg-light text-center" style="min-width: 130px;">
                <small class="text-muted d-block" style="font-size: 8px;">DOCUMENT NO.</small>
                <span class="fw-bold " style="font-size: 14px;"><?php echo $data['function_code']; ?></span>
            </div>
        </div>
    </div>

    <div class="section-title">1. ข้อมูลการจองทั่วไป (GENERAL INFORMATION)</div>
    <div class="row g-2 mb-2">
        <div class="col-7">
            <div class="row g-2">
                <div class="col-12"><strong>ชื่องาน:</strong> <span
                        class="data-value"><?php echo $data['function_name']; ?></span></div>
                <div class="col-6"><strong>ผู้จอง:</strong> <span
                        class="data-value"><?php echo $data['booking_name']; ?></span></div>
                <div class="col-6"><strong>เบอร์โทร:</strong> <span
                        class="data-value"><?php echo $data['phone']; ?></span></div>
                <div class="col-12"><strong>หน่วยงาน/ที่อยู่:</strong> <span
                        class="data-value"><?php echo $data['organization']; ?></span></div>
            </div>
        </div>
        <div class="col-5 border-start ps-3">
            <div class="row g-2">
                <div class="col-12"><strong>สถานที่ประชุม:</strong> <span
                        class="data-value"><?php echo $data['room_name']; ?></span></div>
                <div class="col-12"><strong>Booking Room:</strong> <span
                        class="data-value"><?php echo $data['booking_room'] ?? '-'; ?></span></div>
                <div class="col-12 text-primary"><strong>เงินมัดจำ (Deposit):</strong> <span
                        class="data-value"><?php echo $data['deposit'] ?? '0.00'; ?></span></div>
            </div>
        </div>
    </div>

    <div class="section-title">2. ตารางกำหนดการ (SCHEDULE)</div>
    <table class="table table-sm table-bordered table-tight mb-2">
        <thead class="table-light text-center">
            <tr>
                <th width="15%">Date</th>
                <th width="15%">Hour</th>
                <th>Function Detail</th>
                <th width="12%">QTY</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id");
            while ($row = $schedules->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?php echo $row['schedule_date']; ?></td>
                    <td class="text-center"><?php echo $row['schedule_hour']; ?></td>
                    <td><?php echo nl2br($row['schedule_function']); ?></td>
                    <td class="text-center fw-bold"><?php echo number_format($row['schedule_guarantee']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="row g-3">
        <div class="col-7">
            <div class="section-title">3. รายการอาหารและเครื่องครัว (MAIN KITCHEN)</div>
            <table class="table table-sm table-bordered table-tight mb-1">
                <thead class="table-light text-center">
                    <tr>
                        <th width="18%">Date</th>
                        <th width="20%">Type</th>
                        <th>Menu Item</th>
                        <th width="12%">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $kitchens = $conn->query("SELECT * FROM function_kitchens WHERE function_id = $id");
                    while ($row = $kitchens->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?php echo $row['k_date']; ?></td>
                            <td><?php echo $row['k_type']; ?></td>
                            <td><?php echo $row['k_item']; ?></td>
                            <td class="text-center"><?php echo $row['k_qty']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="p-2 border rounded bg-light" style="font-size: 8.5px;">
                <strong>Kitchen Remark:</strong> <?php echo nl2br($data['main_kitchen_remark'] ?? '-'); ?>
            </div>
        </div>
        <div class="col-5">
            <div class="section-title">4. รูปแบบการจัดงาน (SET-UP)</div>
            <div class="box-detail mb-2" style="min-height: 80px;">
                <?php echo nl2br($data['banquet_style'] ?? 'ตามมาตรฐาน'); ?>
            </div>

            <div class="section-title">5. ระบบวิศวกรรม (TECHNICAL)</div>
            <div class="box-detail" style="min-height: 60px;"><?php echo nl2br($data['equipment'] ?? '-'); ?></div>
        </div>
    </div>

    <div class="section-title">6. รายละเอียดเมนูอาหารและเครื่องดื่ม (FOOD & BEVERAGE DETAILS)</div>
    <table class="table table-sm table-bordered table-tight mb-2">
        <thead class="table-light text-center">
            <tr>
                <th width="10%">Time</th>
                <th width="15%">Type Menu</th>
                <th width="10%">Set</th>
                <th>Menu Details</th>
                <th width="10%">Qty</th>
                <th width="12%">Price/Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $menus = $conn->query("SELECT * FROM function_menus WHERE function_id = $id");
            while ($row = $menus->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?php echo $row['menu_time']; ?></td>
                    <td class="fw-bold"><?php echo $row['menu_name']; ?></td>
                    <td class="text-center"><?php echo $row['menu_set']; ?></td>
                    <td><?php echo nl2br($row['menu_detail']); ?></td>
                    <td class="text-center fw-bold"><?php echo $row['menu_qty']; ?></td>
                    <td class="text-end"><?php echo number_format($row['menu_price'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="row g-3">
        <div class="col-6">
            <div class="section-title">7. ป้ายชื่อและฉาก (BACKDROP & SIGNAGE)</div>
            <div class="box-detail mb-1"><?php echo nl2br($data['backdrop_detail'] ?? '-'); ?></div>
            <?php if (!empty($data['backdrop_img'])): ?>
                <div class="text-center border p-1 rounded bg-white mt-1">
                    <img src="<?php echo $data['backdrop_img']; ?>" style="max-height: 80px; max-width: 100%;">
                </div>
            <?php endif; ?>
        </div>
        <div class="col-6">
            <div class="section-title">8. แม่บ้านและดอกไม้ (FLORIST & HK)</div>
            <div class="box-detail" style="min-height: 60px;">
                <?php echo nl2br($data['hk_florist_detail'] ?? '-'); ?>
            </div>
            <div class="mt-2 p-1 border-start border-warning bg-light" style="font-size: 9px;">
                <strong>Additional Remark:</strong> <?php echo $data['remark'] ?? '-'; ?>
            </div>
        </div>
    </div>

    <div class="row mt-5 text-center" style="font-size: 10px;">
        <div class="col-4 text-center">
            <div class="sig-space">
                <?php if (!empty($creator_sig)): ?>
                    <img src="<?php echo displaySignature($creator_sig); ?>" class="sig-img">
                <?php endif; ?>
            </div>
            <div class="mx-auto border-top w-75 pt-1">
                <div class="fw-bold"><?php echo $data['created_by'] ?? '-'; ?></div>
                ผู้จัดทำ (Event Organizer)
            </div>
            <small class="text-muted">วันที่: <?php echo $data['created_at'] ?? '-'; ?></small>
        </div>

        <div class="col-4 text-center">
            <div class="sig-space">
                <?php if ($data['approve'] == 1 && !empty($approver_sig)): ?>
                    <img src="<?php echo displaySignature($approver_sig); ?>" class="sig-img">
                <?php endif; ?>
            </div>
            <div class="mx-auto border-top w-75 pt-1">
                <div class="fw-bold"><?php echo $approver_name; ?></div>
                ผู้อนุมัติ (Authorized By)
            </div>
            <small class="text-muted">
                วันที่: <?php echo ($data['approve'] == 1) ? $data['approve_date'] : '-'; ?>
            </small>
        </div>

        <div class="col-4 text-center">
            <div class="sig-space"></div>
            <div class="mx-auto border-top w-75 pt-1">
                <div class="fw-bold"><?php echo $data['booking_name'] ?? '-'; ?></div>
                ลูกค้า (Customer)
            </div>
            <small class="text-muted">วันที่: ______/______/______</small>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันสำหรับ Export เป็น Word
    function exportToWord() {
        var header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' " +
            "xmlns:w='urn:schemas-microsoft-com:office:word' " +
            "xmlns='http://www.w3.org/TR/REC-html40'>" +
            "<head><meta charset='utf-8'><title>Export HTML to Word</title>" +
            "<style>" +
            "body { font-family: 'Sarabun', sans-serif; }" +
            "table { border-collapse: collapse; width: 100%; }" +
            "th, td { border: 1px solid black; padding: 5px; font-size: 12pt; }" +
            ".section-title { background-color: #f8f9fa; font-weight: bold; border-left: 5px solid #D4AF37; padding: 5px; margin-top: 10px; }" +
            ".text-end { text-align: right; }" +
            ".fw-bold { font-weight: bold; }" +
            ".row { display: table; width: 100%; }" +
            ".col-6 { display: table-cell; width: 50%; }" +
            "</style></head><body>";

        var footer = "</body></html>";

        // ดึงเนื้อหาจาก printableArea
        var sourceHTML = header + document.getElementById("printableArea").innerHTML + footer;

        // สร้าง Blob สำหรับดาวน์โหลดไฟล์
        var source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
        var fileDownload = document.createElement("a");
        document.body.appendChild(fileDownload);
        fileDownload.href = source;
        fileDownload.download = 'FS-<?php echo $data['function_code']; ?>.doc';
        fileDownload.click();
        document.body.removeChild(fileDownload);
    }

    // ฟังก์ชัน PDF เดิมของจาร
    function downloadPDF(btn) {
        const element = document.getElementById('printableArea');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        btn.disabled = true;

        const opt = {
            // [top, left, bottom, right] - ปรับเป็น 2mm คือชิดมากแล้วครับ
            margin: [2, 2, 2, 2],
            filename: 'FS-<?php echo $data['function_code']; ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 3, // เพิ่ม scale เป็น 3 เพื่อความคมชัดเวลาขอบชิด
                useCORS: true,
                logging: false
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            // เพิ่มส่วนนี้เพื่อรองรับจุดตัดกระดาษ
            pagebreak: { mode: ['css', 'legacy'] }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalContent;
            btn.disabled = false;
        });
    }

</script>

<?php include "footer.php"; ?>