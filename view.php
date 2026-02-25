<?php
include "config.php";
include "header.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงข้อมูลหลัก
$sql = "SELECT f.*, c.company_name, c.logo_path 
        FROM functions f 
        LEFT JOIN companies c ON f.company_id = c.id 
        WHERE f.id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();

if (!$data) { die("ไม่พบข้อมูล!"); }
?>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
body {
    background: #eeeeee;
}

#printableArea {
    font-family: 'Sarabun', sans-serif;
    width: 210mm;
    padding: 10mm 12mm;
    margin: 20px auto;
    background: white;
    font-size: 10px;
    line-height: 1.3;
    color: black;
    box-sizing: border-box;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.text-gold {
    color: #D4AF37;
}

.section-title {
    border-left: 3px solid #D4AF37;
    padding-left: 8px;
    font-weight: 600;
    background: #f8f9fa;
    font-size: 10.5px;
    margin: 10px 0 5px 0 !important;
    text-transform: uppercase;
}

.table-tight th,
.table-tight td {
    padding: 3px 5px !important;
    font-size: 9.5px;
    vertical-align: middle;
}

.data-value {
    border-bottom: 0.5px solid #ccc;
    padding: 0 3px;
    font-weight: 600;
}

.box-detail {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 5px;
    background-color: #fcfcfc;
    min-height: 35px;
}
.html2pdf__page-break {
    display: block;
    height: 0;
    page-break-before: always;
    break-before: page;
}


@media print {
    body {
        background: white;
    }

    /* 1. ซ่อนปุ่มและส่วนที่ไม่เกี่ยวข้องทั้งหมด */
    .no-print,
    .btn-group,
    .container.text-center {
        display: none !important;
    }

    /* 2. ดันเนื้อหาขึ้นไปให้ชิดขอบบนมากขึ้น */
    #printableArea {
        position: absolute;
        left: 0;
        top: -5mm;
        /* ลดระยะห่างด้านบน (ติดลบเพื่อให้ขยับขึ้นไปอีก) */
        width: 100% !important;
        margin: 0 !important;
        padding: 5mm 10mm !important;
        /* ปรับ padding ให้พอดี */
        box-shadow: none !important;
        visibility: visible !important;
    }

    body * {
        visibility: hidden;
    }

    #printableArea * {
        visibility: visible;
    }
}
</style>

<div class="container text-center mt-3 no-print">
    <div class="btn-group btn-group-sm shadow-sm">
        <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer-fill me-1"></i> พิมพ์</button>
        <button onclick="downloadPDF(this)" class="btn btn-warning"><i class="bi bi-file-pdf-fill me-1"></i>
            PDF</button>
        <button onclick="exportToWord()" class="btn btn-primary"><i class="bi bi-file-earmark-word-fill me-1"></i>
            Word</button>
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
                <th width="12%">Guar. (Pax)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id");
            while($row = $schedules->fetch_assoc()): ?>
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
                    while($row = $kitchens->fetch_assoc()): ?>
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
                <?php echo nl2br($data['banquet_style'] ?? 'ตามมาตรฐาน'); ?></div>

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
            while($row = $menus->fetch_assoc()): ?>
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
            <?php if(!empty($data['backdrop_img'])): ?>
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
        <div class="col-4">
            <div class="mx-auto border-top w-75 pt-1 mt-4">ผู้จัดทำ (Event Organizer)</div>
            <small class="text-muted">วันที่: ____/____/____</small>
        </div>
        <div class="col-4">
            <div class="mx-auto border-top w-75 pt-1 mt-4">ผู้อนุมัติ (Authorized By)</div>
            <small class="text-muted">วันที่: ____/____/____</small>
        </div>
        <div class="col-4">
            <div class="mx-auto border-top w-75 pt-1 mt-4">ลูกค้า (Customer Signature)</div>
            <small class="text-muted">วันที่: ____/____/____</small>
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