<?php
include "config.php";
include "header.php";

$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลหลัก + ข้อมูลลูกค้า + ข้อมูลบริษัทเรา + ลายเซ็น

// 1. ดึงข้อมูลหลัก + ข้อมูลลูกค้า + บริษัท + ลายเซ็น + ชื่อพนักงาน
$sql = "SELECT q.*, 
               c.cust_name, c.cust_address, c.cust_phone, 
               f.function_name, 
               comp.company_name, comp.address as comp_address, comp.phone as comp_phone, comp.email as comp_email, comp.logo_path,
               u_create.name as created_by_name,
               u_appr.name as approved_by_name,
               s_create.path as creator_sig_path, -- ลายเซ็นคนทำ
               s_appr.path as approver_sig_path    -- ลายเซ็นผู้อนุมัติ
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN functions f ON q.function_id = f.id
        LEFT JOIN companies comp ON q.company_id = comp.id
        LEFT JOIN users u_create ON q.created_by = u_create.id 
        LEFT JOIN users u_appr ON q.approved_by = u_appr.id
        -- JOIN ตารางลายเซ็นโดยเทียบจาก users_id
        LEFT JOIN signatures s_create ON q.created_by = s_create.users_id
        LEFT JOIN signatures s_appr ON q.approved_by = s_appr.users_id
        WHERE q.id = $id";
$result = $conn->query($sql);
$quote = $result->fetch_assoc();
if (!$quote) {
    echo "<div class='alert alert-danger'>ไม่พบข้อมูลใบเสนอราคา</div>";
    exit;
}

// 2. ดึงรายการย่อย
$sql_items = "SELECT * FROM quotation_items WHERE quote_id = $id ORDER BY id ASC";
$items = $conn->query($sql_items);
?>

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
<div class="container my-5">
    <div id="printableArea">
        <div class="content-body">
            <div class="section-group">
                <div class="row align-items-start mb-4">
                    <div class="col-7">
                        <div class="mb-4">
                            <h1 class="fw-bold text-primary mb-0" style="letter-spacing: 2px;">QUOTATION</h1>
                            <p class="text-muted small text-uppercase">ใบเสนอราคา</p>
                        </div>

                        <div class="customer-info-section">
                            <div class="section-title mb-2">ข้อมูลลูกค้า / Customer Info</div>
                            <div class="ps-3">
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($quote['cust_name']) ?></h5>
                                <div class="small text-muted mb-2" style="line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($quote['cust_address'])) ?>
                                </div>
                                <div class="small">
                                    <span class="text-muted">โทร / Tel:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($quote['cust_phone']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-5">
                        <div class="d-flex align-items-start justify-content-end gap-3 mb-4">
                            <div class="text-end">
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($quote['company_name']) ?></h5>
                                <p class="small text-muted mb-0" style="font-size: 10px; line-height: 1.4;">
                                    <?= nl2br(htmlspecialchars($quote['comp_address'])) ?>
                                </p>
                                <p class="small text-muted mb-0" style="font-size: 10px;">
                                    โทร: <?= htmlspecialchars($quote['comp_phone']) ?>
                                    <?php if (!empty($quote['comp_email'])): ?>
                                        | <?= htmlspecialchars($quote['comp_email']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if (!empty($quote['logo_path'])): ?>
                                <div class="flex-shrink-0">
                                    <img src="<?= htmlspecialchars($quote['logo_path']) ?>" alt="Logo"
                                        style="max-height: 60px; width: auto; object-fit: contain; filter: grayscale(10%) ;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered ms-auto mb-0"
                                style="width: 100%; max-width: 240px; ">
                                <tbody>
                                    <tr>
                                        <th class="bg-light text-muted fw-normal" style="width: 40%;">เลขที่ / No.</th>
                                        <td class="fw-bold text-end"><?= $quote['quote_no'] ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-muted fw-normal">วันที่ / Date</th>
                                        <td class="text-end"><?= date('d/m/Y', strtotime($quote['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-muted fw-normal">ชื่องาน / Event</th>
                                        <td class="text-end text-truncate" style="max-width: 120px;">
                                            <?= htmlspecialchars($quote['event_name']) ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-group">
                <div class="section-title">รายละเอียดรายการ / Description</div>
                <table class="table table-bordered table-tight">
                    <thead>
                        <tr class="text-center bg-light">
                            <th width="5%">#</th>
                            <th>รายการ / Description</th>
                            <th width="12%">จำนวน</th>
                            <th width="15%">ราคา/หน่วย</th>
                            <th width="18%">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($item = $items->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></div>
                                </td>
                                <td class="text-center"><?= number_format($item['quantity']) ?></td>
                                <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-end fw-bold"><?= number_format($item['total_price'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="section-group row ">
                <div class="col-7">
                    <div class="section-title">หมายเหตุ / Remark</div>
                    <div class="text-black" style="white-space: pre-line;">
                        <?= !empty($quote['remarks']) ? htmlspecialchars($quote['remarks']) : '-' ?>
                    </div>
                </div>
                <div class="col-5">
                    <table class="table table-sm table-borderless table-tight">
                        <tr>
                            <td class="text-end">รวมเป็นเงิน / Subtotal:</td>
                            <td class="text-end border-bottom" width="40%"><?= number_format($quote['subtotal'], 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-end">ค่าบริการ / Service Charge:</td>
                            <td class="text-end border-bottom"><?= number_format($quote['service_charge'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end">ภาษีมูลค่าเพิ่ม / VAT (7%):</td>
                            <td class="text-end border-bottom"><?= number_format($quote['vat'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-primary">ยอดรวมสุทธิ / Grand Total:</td>
                            <td class="text-end fw-bold h6 text-primary border-bottom"
                                style="border-bottom: 2px double !important;">
                                <?= number_format($quote['grand_total'], 2) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="signature-wrapper text-center mt-5">
            <div class="row">
                <div class="col-4">
                    <div
                        style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($quote['creator_sig_path'])): ?>
                            <img src="<?= $quote['creator_sig_path'] ?>" style="max-height: 55px; width: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="small mb-0 fw-bold">ผู้จัดทำ / Prepared By</p>
                    <p class="small text-muted mb-0">(
                        <?= htmlspecialchars($quote['created_by_name'] ?? '................................') ?> )
                    </p>
                    <p class="small text-muted">วันที่: <?= date('d/m/Y', strtotime($quote['created_at'])) ?></p>
                </div>
                <div class="col-4">
                    <div
                        style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($quote['approver_sig_path'])): ?>
                            <img src="<?= $quote['approver_sig_path'] ?>" style="max-height: 55px; width: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="small mb-0 fw-bold">ผู้อนุมัติ / Authorized Signature</p>
                    <p class="small text-muted mb-0">(
                        <?= htmlspecialchars($quote['approved_by_name'] ?? '................................') ?> )
                    </p>
                    <p class="small text-muted">วันที่:
                        <?= !empty($quote['approved_at']) ? date('d/m/Y', strtotime($quote['approved_at'])) : '....../....../......' ?>
                    </p>
                </div>
                <div class="col-4">
                    <div
                        style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($quote['customer_signature'])): ?>
                            <img src="<?= $quote['customer_signature'] ?>" style="max-height: 55px; width: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="small mb-0 fw-bold">ลูกค้า / Customer </p>
                    <p class="small text-muted mb-0">(
                        <?= htmlspecialchars($quote['cust_name'] ?? '................................') ?> )
                    </p>
                    <p class="small text-muted">วันที่: ....../....../......</p>
                </div>


            </div>
        </div>
    </div>
</div>

<style>
    /* --- ส่วนการแสดงผลบนหน้าจอ --- */
    :root {
        --print-fs: 14px;
        --print-pad: 3px 5px;
        /* ลด padding ตารางลงเล็กน้อย */
        --print-lh: 1.3;
        /* กระชับระยะบรรทัด */
    }

    body {
        background: #f4f4f4;
    }

    #printableArea {
        font-family: 'Sarabun', sans-serif;
        width: 210mm;
        min-height: 297mm;
        padding: 10mm 15mm;
        /* ลด padding บน-ล่าง จาก 15 เป็น 10 */
        margin: 20px auto;
        background: white;
        color: black;
        box-sizing: border-box;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        font-size: var(--print-fs);
        line-height: var(--print-lh);
        display: flex;
        flex-direction: column;
    }

    .section-title {
        border-left: 4px solid #D4AF37;
        padding-left: 8px;
        font-weight: 700;
        background: #f8f9fa;
        margin: 8px 0 5px 0 !important;
        /* บีบ margin ให้เล็กลง */
        text-transform: uppercase;
        font-size: calc(var(--print-fs) + 1px);
    }

    .table-tight th,
    .table-tight td {
        padding: var(--print-pad) !important;
        vertical-align: middle;
        border-color: #333 !important;
        /* ทำให้เส้นตารางเข้มขึ้นเพื่อความชัด */
    }

    .data-value {
        border-bottom: 1px solid #ccc;
        padding: 0 5px;
    }

    /* ส่วนลายเซ็นแบบกระชับพิเศษ */
    .signature-wrapper {
        margin-top: auto !important;
        padding-top: 10px;
        padding-bottom: 0;
    }

    /* ปรับช่องขีดเส้นใต้ให้เตี้ยลงเพื่อประหยัดพื้นที่ */
    .signature-wrapper div[style*="height: 50px"],
    .signature-wrapper div[style*="height: 40px"] {
        height: 30px !important;
        margin-bottom: 3px !important;
    }

    .signature-wrapper p {
        margin-bottom: 0 !important;
        font-size: 14px;
        /* ลดฟอนต์คำบรรยายลายเซ็นเล็กน้อยเพื่อให้ดูชัดเจนไม่รก */
    }

    .content-body {
        flex-grow: 1;
    }

    /* --- ส่วนการตั้งค่าสำหรับการพิมพ์ --- */
    @media print {
        @page {
            size: A4;
            margin: 0 !important;
        }

        .no-print,
        header,
        footer,
        nav,
        .btn {
            display: none !important;
        }

        body {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            visibility: hidden;
            -webkit-print-color-adjust: exact;
        }

        #printableArea {
            visibility: visible !important;
            position: absolute !important;
            top: -2mm !important;
            /* ขยับขึ้นไปชิดขอบบนสุด */
            left: 0 !important;
            right: 0 !important;
            width: 210mm !important;
            height: 297mm !important;
            margin: 0 !important;
            padding: 8mm 12mm !important;
            /* ลดขอบนอก เพื่อให้ตัวหนังสือดูใหญ่เต็มตา */
            box-shadow: none !important;
            transform: none !important;
            /* ยกเลิกการ scale เพื่อให้ตัวอักษรชัดเจนที่สุด */
        }

        #printableArea * {
            visibility: visible !important;
        }

        .section-group {
            page-break-inside: avoid;
            margin-bottom: 5px !important;
            /* ลดระยะห่างระหว่างกลุ่มเนื้อหา */
        }

        .section-title {
            background-color: #f8f9fa !important;
            print-color-adjust: exact;
        }

        /* ทำให้ตัวหนาชัดเจนขึ้นตอนพิมพ์ */
        .fw-bold {
            font-weight: 700 !important;
        }
    }
</style>

<?php include "footer.php"; ?>