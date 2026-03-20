<?php
include "config.php";
include "header.php";

$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลหลัก
$sql = "SELECT q.*, c.cust_name, c.cust_address, c.cust_phone, f.function_name 
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN functions f ON q.function_id = f.id
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

<div class="container my-5">
    <div class="d-print-none mb-4 d-flex justify-content-between align-items-center">
        <a href="quotation_list.php" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับหน้ารายการ</a>
        <button onclick="window.print()" class="btn btn-primary px-4">
            <i class="bi bi-printer-fill me-2"></i> พิมพ์ใบเสนอราคา
        </button>
    </div>

    <div id="printableArea">

        <div class="section-group">
            <div class="row mb-4">
                <div class="col-6">
                    <h2 class="fw-bold text-primary mb-1">QUOTATION</h2>
                    <p class="text-muted small">ใบเสนอราคา</p>

                    <div class="mt-4">
                        <div class="section-title">ข้อมูลลูกค้า / Customer Info</div>
                        <div class="ps-2">
                            <div class="data-value fw-bold"><?= htmlspecialchars($quote['cust_name']) ?></div>
                            <div class="small text-muted"><?= nl2br(htmlspecialchars($quote['cust_address'])) ?></div>
                            <div class="small">โทร: <span
                                    class="data-value"><?= htmlspecialchars($quote['cust_phone']) ?></span></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 text-end">
                    <div class="mb-3">
                        <h4 class="fw-bold">BANQUET MANAGEMENT</h4>
                        <p class="small text-muted">123 ถนนเพชรเกษม หาดใหญ่ สงขลา 90110</p>
                    </div>

                    <table class="table table-sm table-bordered ms-auto table-tight" style="width: 250px;">
                        <tr>
                            <th class="bg-light small">เลขที่ / No.</th>
                            <td class="small fw-bold"><?= $quote['quote_no'] ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light small">วันที่ / Date</th>
                            <td class="small"><?= date('d/m/Y', strtotime($quote['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light small">ชื่องาน / Event</th>
                            <td class="small"><?= htmlspecialchars($quote['event_name']) ?></td>
                        </tr>
                    </table>
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
                                <small class="text-muted"><?= $item['item_type'] ?></small>
                            </td>
                            <td class="text-center"><?= number_format($item['quantity']) ?></td>
                            <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold"><?= number_format($item['total_price'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section-group row">
            <div class="col-7">
                <div class="section-title">หมายเหตุ / Remark</div>
                <div class="box-detail small">
                    ใบเสนอราคานี้มีผลถึงวันที่:
                    <strong><?= date('d/m/Y', strtotime($quote['expiry_date'])) ?></strong><br>
                    * ราคานี้รวมอุปกรณ์มาตรฐานและพนักงานบริการเรียบร้อยแล้ว
                </div>
            </div>
            <div class="col-5">
                <table class="table table-sm table-borderless table-tight">
                    <tr>
                        <td class="text-end">รวมเป็นเงิน / Subtotal:</td>
                        <td class="text-end border-bottom" width="40%"><?= number_format($quote['subtotal'], 2) ?></td>
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

        <div class="signature-wrapper text-center mt-5">
            <div class="row">
                <div class="col-4">
                    <div style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 50px;"></div>
                    <p class="small mb-0 fw-bold">ผู้จัดทำ / Prepared By</p>
                    <p class="small text-muted mb-0">(....................................................)</p>
                    <p class="small text-muted">วันที่ / Date: ....../....../......</p>
                </div>

                <div class="col-4">
                    <div style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 50px;"></div>
                    <p class="small mb-0 fw-bold">ยอมรับข้อเสนอ / Customer Accepted</p>
                    <p class="small text-muted mb-0">(....................................................)</p>
                    <p class="small text-muted">วันที่ / Date: ....../....../......</p>
                </div>

                <div class="col-4">
                    <div style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 50px;"></div>
                    <p class="small mb-0 fw-bold">ผู้อนุมัติ / Authorized Signature</p>
                    <p class="small text-muted mb-0">(....................................................)</p>
                    <p class="small text-muted">วันที่ / Date: ....../....../......</p>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    /* --- ส่วนการแสดงผลบนหน้าจอ --- */
    :root {
        --print-fs: 11px;
        --print-pad: 4px 6px;
        --print-lh: 1.4;
    }

    body {
        background: #f4f4f4;
    }

    #printableArea {
        font-family: 'Sarabun', sans-serif;
        width: 210mm;
        min-height: 297mm;
        padding: 15mm 15mm;
        margin: 20px auto;
        background: white;
        color: black;
        box-sizing: border-box;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        font-size: var(--print-fs);
        line-height: var(--print-lh);
    }

    .section-title {
        border-left: 4px solid #D4AF37;
        padding-left: 10px;
        font-weight: 700;
        background: #f8f9fa;
        margin: 15px 0 8px 0 !important;
        text-transform: uppercase;
        font-size: calc(var(--print-fs) + 1px);
    }

    .table-tight th,
    .table-tight td {
        padding: var(--print-pad) !important;
        vertical-align: middle;
    }

    .data-value {
        border-bottom: 1px solid #eee;
        padding: 0 5px;
    }

    .box-detail {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 8px;
        background-color: #fcfcfc;
        min-height: 50px;
    }

    /* --- ส่วนการตั้งค่าสำหรับการพิมพ์ (หักดิบ) --- */
    @media print {
        @page {
            size: A4;
            margin: 0 !important;
        }

        .d-print-none,
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
        }

        #printableArea {
            visibility: visible !important;
            position: absolute !important;
            top: 8mm !important;
            left: 10mm !important;
            right: 10mm !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }

        #printableArea * {
            visibility: visible !important;
        }

        .section-group {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 15px !important;
        }

        .signature-wrapper {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .table thead {
            display: table-header-group !important;
        }

        .section-title {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<?php include "footer.php"; ?>