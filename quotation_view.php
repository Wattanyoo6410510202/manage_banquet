<?php
include "config.php";
include "header.php";

$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลหลัก + ข้อมูลลูกค้า + ข้อมูลบริษัทเรา + ลายเซ็น

// 1. ดึงข้อมูลหลัก + ข้อมูลลูกค้า + บริษัท + ลายเซ็น + ชื่อพนักงาน
$sql = "SELECT q.*, 
                c.cust_name, c.cust_address, c.cust_phone, c.cust_email, c.cust_contact_name, c.sales_name,
               f.function_name, 
               comp.company_name, comp.address as comp_address, comp.phone as comp_phone, comp.email as comp_email, comp.logo_path, comp.stamp_path,
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

        <div class="hr-custom w-75 border-top opacity-25"></div>

        <button onclick="sendPDFToCustomer(this)"
            class="btn btn-link btn-sm text-dark text-decoration-none border-0 p-2 d-flex flex-column align-items-center custom-btn-pill"
            title="ส่ง PDF ให้ลูกค้า">
            <i class="bi bi-send-fill text-success fs-5"></i>
            <span style="font-size: 10px;" class="fw-bold">ส่ง PDF</span>
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
                            <h2 class="fw-bold text-primary mb-0" style="letter-spacing: 2px; font-size: 22px;">QUOTATION</h2>
                            <p class="text-muted small text-uppercase" style="font-size: 11px;">ใบเสนอราคา</p>
                        </div>

                        <div class="customer-info-section">
                            <div class="section-title mb-2">ข้อมูลลูกค้า / Customer Info</div>
                            <div class="ps-3">
                                <div class="text-muted mb-2" style="font-size: 12px; line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($quote['cust_address'])) ?>
                                </div>
                                <div style="font-size: 12px;">
                                    <span class="text-muted">ชื่อ / Name:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($quote['cust_name']) ?></span>
                                    <span class="text-muted ms-2">โทร / Tel:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($quote['cust_phone']) ?></span>
                                </div>
                                <div style="font-size: 12px; margin-top: 4px;">
                                    <span class="text-muted">วันที่จัดงาน / Event Date:</span>
                                    <span class="fw-bold"><?= !empty($quote['event_date']) ? date('d/m/Y', strtotime($quote['event_date'])) : '-' ?> - <?= !empty($quote['expiry_date']) ? date('d/m/Y', strtotime($quote['expiry_date'])) : '-' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-5">
                        <div class="d-flex align-items-start justify-content-end gap-3 mb-4">
                            <div class="text-end">
                                <h5 class="fw-bold text-dark mb-1" style="font-size: 15px;"><?= htmlspecialchars($quote['company_name']) ?></h5>
                                <p class="text-muted mb-0" style="font-size: 12px; line-height: 1.4;">
                                    <?= nl2br(htmlspecialchars($quote['comp_address'])) ?>
                                </p>
                                <p class="text-muted mb-0" style="font-size: 12px;">
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

                        <div>
                            <table class="table table-sm table-bordered ms-auto mb-0"
                                style="width: 100%; max-width: 240px; font-size: 12px;">
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
                                        <td class="text-end" style="max-width: 200px;">
                                            <textarea class="form-control border-0 bg-transparent fw-bold p-0 event-name-ta" rows="1" readonly style="resize: none; width: 100%; overflow: hidden; text-align: right; font-size: 12px;"><?= htmlspecialchars($quote['event_name']) ?></textarea>
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
                <table class="table table-bordered table-tight" style="font-size: 11px;">
                    <thead>
                        <tr class="text-center bg-light">
                            <th width="5%" style="font-size: 12px;">#</th>
                            <th style="font-size: 12px;">รายการ / Description</th>
                            <th width="12%" style="font-size: 12px;">จำนวน</th>
                            <th width="15%" style="font-size: 12px;">ราคา/หน่วย</th>
                            <th width="18%" style="font-size: 12px;">จำนวนเงิน</th>
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
                                    <textarea class="form-control border-0 bg-transparent p-0 item-desc" rows="1" readonly style="resize: none; min-width: 100%; overflow: hidden; font-size: 11px;"><?= htmlspecialchars($item['item_name']) ?></textarea>
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
                    <div class="section-title">หมายเหตุ / Remarks</div>
                    <div class="text-black" style="white-space: pre-line; font-size: 12px;">
                        <?= !empty($quote['remarks']) ? htmlspecialchars($quote['remarks']) : '-' ?>
                    </div>
                </div>
                <div class="col-5">
                    <table class="table table-sm table-borderless table-tight" style="font-size: 12px;">
                        <tr>
                            <td class="text-end">รวมเป็นเงิน / Subtotal (Ex.vat):</td>
                            <td class="text-end border-bottom" width="40%"><?= number_format($quote['subtotal'], 2) ?>
                            </td>
                        </tr>
                        <?php if (floatval($quote['discount'] ?? 0) > 0): ?>
                        <tr>
                            <td class="text-end text-danger">ส่วนลดท้ายบิล / Special Discount:</td>
                            <td class="text-end border-bottom text-danger">- <?= number_format($quote['discount'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end">รวมหลังหักส่วนลด / After Discount:</td>
                            <td class="text-end border-bottom"><?= number_format(($quote['subtotal'] ?? 0) - ($quote['discount'] ?? 0), 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-end">ค่าบริการ / Service Charge:</td>
                            <td class="text-end border-bottom"><?= number_format($quote['service_charge'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end">
                                ภาษีมูลค่าเพิ่ม / VAT (7%) 
                                <?php 
                                if (isset($quote['vat_type'])) {
                                    if ($quote['vat_type'] === 'include') {
                                        echo '(รวมใน)';
                                    } elseif ($quote['vat_type'] === 'exclude') {
                                        echo '(แยกนอก)';
                                    }
                                }
                                ?>
                                (คำนวณจากยอดหลังหักส่วนลด):
                            </td>
                            <td class="text-end border-bottom"><?= number_format($quote['vat'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-primary" style="font-size: 13px;">ยอดรวมสุทธิ / Grand Total:</td>
                            <td class="text-end fw-bold text-primary border-bottom"
                                style="border-bottom: 2px double !important; font-size: 15px;">
                                <?= number_format($quote['grand_total'], 2) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- เงื่อนไข -->
        <div class="conditions-section" style="font-size: 10.5px; line-height: 1.6;">
            <div class="d-flex align-items-center mb-2">
                <span class="section-title mb-0">เงื่อนไขการยืนยันการจัดงานและการชำระเงิน</span>
                <small class="text-muted ms-2 no-print" style="font-size: 9px;"><i class="bi bi-pencil"></i> คลิกเพื่อแก้ไข</small>
            </div>
            <ol style="padding-left: 18px; margin-bottom: 6px;" contenteditable="true" class="editable-block">
                <li>ผู้ว่าจ้างตกลงยืนยันการจัดงานเป็นลายลักษณ์อักษร หรือผ่านเอกสารที่สามารถตรวจสอบได้ ไม่น้อยกว่า 15 (สิบห้า) วัน ก่อนวันจัดงาน</li>
                <li>ผู้ว่าจ้างตกลงชำระค่าบริการทั้งหมด ภายใน 15 (สิบห้า) วัน นับแต่วันที่โรงแรมออกใบแจ้งหนี้ (Tax Invoice) เว้นแต่คู่สัญญาจะตกลงเป็นหนังสือไว้เป็นอย่างอื่น</li>
                <li>การลงนามในใบเสนอราคา หรือการตอบรับใบเสนอราคาทางอิเล็กทรอนิกส์ ถือเป็นการยอมรับรายละเอียด ราคา ขอบเขตการให้บริการ และเงื่อนไขทั้งหมดที่ระบุไว้ในใบเสนอราคา โดยมีผลผูกพันตามกฎหมาย</li>
                <li>หากผู้ว่าจ้างผิดนัดชำระเงิน โรงแรมมีสิทธิเรียกดอกเบี้ยผิดนัดในอัตราที่กฎหมายกำหนด นับแต่วันถัดจากวันครบกำหนดชำระ จนกว่าจะชำระเงินครบถ้วน</li>
                <li>โรงแรมสงวนสิทธิ์ในการระงับการให้บริการ หรือปฏิเสธการรับจองในอนาคต หากผู้ว่าจ้างผิดนัดชำระหนี้โดยไม่มีเหตุอันสมควร</li>
            </ol>

            <div style="border: 1px solid #333; padding: 8px 12px; margin-bottom: 10px; background: #f9f9f9;">
                <strong>กรุณาโอนชำระเงินในนาม :</strong> บจก.ดับเบิ้ล เอส กรุ๊ป<br>
                <strong>ธนาคาร :</strong> กสิกรไทย &nbsp;&nbsp; <strong>เลขที่บัญชี :</strong> 019-3-40644-0<br>
                <span style="font-size: 10px;">(ภายหลังโอนชำระ กรุณาส่งเอกสารสลิปการโอน มาถึงฝ่ายขาย หลังโอนเสร็จสิ้น)</span>
            </div>

            <div class="d-flex align-items-center mb-2">
                <span class="section-title mb-0">เงื่อนไขการเปลี่ยนแปลงรายละเอียดการจัดงาน</span>
                <small class="text-muted ms-2 no-print" style="font-size: 9px;"><i class="bi bi-pencil"></i> คลิกเพื่อแก้ไข</small>
            </div>
            <ol style="padding-left: 18px; margin-bottom: 6px;" contenteditable="true" class="editable-block">
                <li>กรณีผู้ว่าจ้างประสงค์จะเปลี่ยนแปลงจำนวนผู้เข้าร่วมประชุม รายการอาหาร เครื่องดื่ม หรือรายละเอียดอื่นใด ผู้ว่าจ้างต้องแจ้งให้โรงแรมทราบเป็นลายลักษณ์อักษร ไม่น้อยกว่า 3 (สาม) วัน ก่อนวันจัดงาน</li>
                <li>หากแจ้งเปลี่ยนแปลงภายหลังระยะเวลาที่กำหนด โรงแรมขอสงวนสิทธิ์เรียกเก็บค่าใช้จ่ายเพิ่มเติม หรือคิดค่าบริการตามจำนวนที่ได้เตรียมการไว้แล้ว ทั้งนี้ตามความเสียหายที่เกิดขึ้นจริง</li>
                <li>โรงแรมจะดำเนินการเปลี่ยนแปลงตามความพร้อมของวัตถุดิบ บุคลากร และการให้บริการ โดยไม่กระทบต่อคุณภาพมาตรฐานของงาน</li>
            </ol>

            <div class="d-flex align-items-center mb-2">
                <span class="section-title mb-0">เงื่อนไขการเพิ่มจำนวนผู้เข้าร่วม</span>
                <small class="text-muted ms-2 no-print" style="font-size: 9px;"><i class="bi bi-pencil"></i> คลิกเพื่อแก้ไข</small>
            </div>
            <ol style="padding-left: 18px; margin-bottom: 6px;" contenteditable="true" class="editable-block">
                <li>กรณีผู้ว่าจ้างมีความประสงค์เพิ่มจำนวนผู้เข้าร่วมประชุม หรือเพิ่มจำนวนอาหารและเครื่องดื่มภายหลังการยืนยันยอด โรงแรมจะดำเนินการตามศักยภาพในการให้บริการและความพร้อมของวัตถุดิบ</li>
                <li>โรงแรมขอสงวนสิทธิ์ในการเปลี่ยนแปลงรายการอาหาร เครื่องดื่ม หรือวัสดุอุปกรณ์เป็นรายการที่มีคุณภาพเทียบเท่า โดยไม่ต้องแจ้งให้ทราบล่วงหน้า</li>
                <li>ผู้ว่าจ้างรับทราบว่า การสั่งเพิ่มอาหารในวันจัดงานอาจใช้ระยะเวลาในการจัดเตรียมเพิ่มเติม และโรงแรมจะดำเนินการโดยเร็วที่สุดตามมาตรฐานการให้บริการ</li>
            </ol>

            <div class="d-flex align-items-center mb-2">
                <span class="section-title mb-0">ความรับผิดชอบต่อความเสียหาย</span>
                <small class="text-muted ms-2 no-print" style="font-size: 9px;"><i class="bi bi-pencil"></i> คลิกเพื่อแก้ไข</small>
            </div>
            <ol style="padding-left: 18px; margin-bottom: 6px;" contenteditable="true" class="editable-block">
                <li>ผู้ว่าจ้างตกลงรับผิดชอบต่อความเสียหาย ความสูญหาย หรือการชำรุดของอาคาร ห้องประชุม อุปกรณ์ เครื่องใช้ เครื่องตกแต่ง ทรัพย์สิน หรือสิ่งอำนวยความสะดวกของโรงแรม อันเกิดจากการกระทำของผู้ว่าจ้าง ผู้เข้าร่วมประชุม วิทยากร ผู้รับจ้างช่วง คณะทำงาน หรือบุคคลที่ผู้ว่าจ้างเชิญเข้าร่วมงาน</li>
                <li>ผู้ว่าจ้างตกลงชำระค่าเสียหายตามมูลค่าความเสียหายที่เกิดขึ้นจริง รวมถึงค่าใช้จ่ายในการซ่อมแซม เปลี่ยนทดแทน หรือฟื้นฟูทรัพย์สินดังกล่าว ตามที่โรงแรมประเมินโดยสุจริตและสมเหตุสมผล</li>
                <li>โรงแรมมีสิทธิเรียกเก็บค่าเสียหายดังกล่าวเพิ่มเติมจากค่าบริการตามใบเสนอราคาโรงแรมมีสิทธิเรียกเก็บค่าเสียหายดังกล่าวเพิ่มเติมจากค่าบริการตามใบเสนอราคา</li>
            </ol>

            <div class="d-flex align-items-center mb-2">
                <span class="section-title mb-0">ข้อกำหนดทั่วไป</span>
                <small class="text-muted ms-2 no-print" style="font-size: 9px;"><i class="bi bi-pencil"></i> คลิกเพื่อแก้ไข</small>
            </div>
            <ol style="padding-left: 18px; margin-bottom: 6px;" contenteditable="true" class="editable-block">
                <li>ราคาในใบเสนอราคานี้มีอายุ 30 วัน นับจากวันที่ออกเอกสาร เว้นแต่จะระบุไว้เป็นอย่างอื่น</li>
                <li>การให้บริการเป็นไปตามข้อกำหนดของโรงแรม และกฎหมายที่เกี่ยวข้อง</li>
                <li>หากมีเหตุสุดวิสัย (Force Majeure) เช่น ภัยธรรมชาติ การระบาดของโรค การจลาจล คำสั่งของหน่วยงานรัฐ หรือเหตุการณ์ที่อยู่นอกเหนือการควบคุมของคู่สัญญา ซึ่งทำให้ไม่สามารถจัดงานได้ คู่สัญญาจะร่วมกันเจรจาเพื่อกำหนดแนวทางที่เหมาะสม โดยไม่มีฝ่ายใดต้องรับผิดในความเสียหายที่เกิดจากเหตุสุดวิสัยดังกล่าว</li>
            </ol>

            <ol style="padding-left: 18px; margin-bottom: 6px;" start="4">
                <li>เงื่อนไขการยกเลิกงาน (Cancellation Policy) เช่น ยกเลิกภายใน 7 วัน คิดค่าบริการ 50% และภายใน 3 วัน คิด 100%</li>
                <li>การรับมอบบริการ (Acceptance of Service) เมื่องานเสร็จสิ้นและผู้จัดงานใช้บริการ ถือว่าผู้ว่าจ้างรับมอบงาน เว้นแต่จะแจ้งข้อบกพร่องเป็นลายลักษณ์อักษรภายใน 24 ชั่วโมง</li>
                <li>การระงับการให้บริการ หากผู้ว่าจ้างไม่ปฏิบัติตามเงื่อนไขสำคัญ โรงแรมมีสิทธิระงับการให้บริการโดยไม่ต้องรับผิดในความเสียหายที่เกิดขึ้น</li>
                <li>ข้อกำหนดเรื่องกฎหมายและเขตอำนาจศาล ระบุให้ข้อพิพาทอยู่ภายใต้กฎหมายไทย และให้ศาลที่โรงแรมตั้งอยู่เป็นศาลที่มีเขตอำนาจ เพื่อความชัดเจนในการดำเนินคดีหากเกิดข้อพิพาทในอนาคต</li>
            </ol>

            <div class="signature-wrapper text-center mt-5">
                <div class="row">
                    <div class="col-4">
                        <div
                            style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 55px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 2;">
                            <?php if (!empty($quote['creator_sig_path'])): ?>
                                <img src="<?= $quote['creator_sig_path'] ?>" style="max-height: 50px; width: auto;">
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 fw-bold" style="font-size: 12px;">ผู้จัดทำ / Prepared By</p>
                        <p class="text-muted mb-0" style="font-size: 12px;">(
                            <?= htmlspecialchars($quote['created_by_name'] ?? '................................') ?> )
                        </p>
                        <p class="text-muted" style="font-size: 12px;">วันที่: <?= date('d/m/Y', strtotime($quote['created_at'])) ?></p>
                    </div>
                    <div class="col-4" style="position: relative;">
                        <?php if (!empty($quote['stamp_path']) && file_exists($quote['stamp_path'])): ?>
                            <img src="<?= htmlspecialchars($quote['stamp_path']) ?>" 
                                style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 100px; height: 100px; object-fit: contain; opacity: 0.7; z-index: 1; pointer-events: none; mix-blend-mode: multiply;">
                        <?php endif; ?>
                        <div
                            style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 55px; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($quote['approver_sig_path'])): ?>
                                <img src="<?= $quote['approver_sig_path'] ?>" style="max-height: 50px; width: auto;">
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 fw-bold" style="font-size: 12px;">ผู้อนุมัติ / Authorized Signature</p>
                        <p class="text-muted mb-0" style="font-size: 12px;">(
                            <?= htmlspecialchars($quote['approved_by_name'] ?? '................................') ?> )
                        </p>
                        <p class="text-muted" style="font-size: 12px;">วันที่:
                            <?= !empty($quote['approved_at']) ? date('d/m/Y', strtotime($quote['approved_at'])) : '....../....../......' ?>
                        </p>
                    </div>
                    <div class="col-4">
                        <div
                            style="border-bottom: 1px solid #000; margin: 0 10px 10px 10px; height: 55px; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($quote['customer_signature'])): ?>
                                <img src="<?= $quote['customer_signature'] ?>" style="max-height: 50px; width: auto;">
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 fw-bold" style="font-size: 12px;">ลูกค้า / Customer </p>
                        <p class="text-muted mb-0" style="font-size: 12px;">(
                            <?= htmlspecialchars($quote['cust_name'] ?? '................................') ?> )
                        </p>
                        <p class="text-muted" style="font-size: 12px;">วันที่: ....../....../......</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    /* --- ส่วนการแสดงผลบนหน้าจอ --- */
    :root {
        --print-fs: 13px;
        --print-pad: 2px 6px;
        --print-lh: 1.5;
    }

    body {
        background: #f4f4f4;
    }

    #printableArea {
        font-family: 'Sarabun', sans-serif;
        width: 210mm;
        min-height: 297mm;
        padding: 10mm 15mm;
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
        margin: 6px 0 4px 0 !important;
        text-transform: uppercase;
        font-size: 13px;
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
            left: 0 !important;
            right: 0 !important;
            width: 210mm !important;
            min-height: 297mm !important;
            height: auto !important;
            margin: 0 !important;
            padding: 8mm 12mm !important;
            box-shadow: none !important;
            transform: none !important;
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

        .conditions-section {
            page-break-before: always !important;
            padding-top: 8mm;
        }

        .editable-block {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }
    }
</style>

<style>
    .editable-block {
        outline: none;
        min-height: 20px;
        border: 1px dashed transparent;
        border-radius: 4px;
        padding: 4px 6px;
        transition: border-color 0.2s;
    }
    .editable-block:hover {
        border-color: #0d6efd;
        background-color: #f0f7ff;
    }
    .editable-block:focus {
        border-color: #0d6efd;
        background-color: #fff;
        box-shadow: 0 0 0 2px rgba(13,110,253,0.15);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-desc, .event-name-ta').forEach(function(ta) {
        ta.style.height = 'auto';
        ta.style.height = ta.scrollHeight + 'px';
    });
});

function downloadPDF(btn) {
    const element = document.getElementById('printableArea');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    const opt = {
        margin: [2, 2, 2, 2],
        filename: 'Q-<?php echo htmlspecialchars($quote['quote_no'] ?? 'quotation'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 3,
            useCORS: true,
            logging: false
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'] }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
    });
}

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
    var sourceHTML = header + document.getElementById("printableArea").innerHTML + footer;
    var source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
    var fileDownload = document.createElement("a");
    document.body.appendChild(fileDownload);
    fileDownload.href = source;
    fileDownload.download = 'Q-<?php echo htmlspecialchars($quote['quote_no'] ?? 'quotation'); ?>.doc';
    fileDownload.click();
    document.body.removeChild(fileDownload);
}

function exportToDoc() {
    exportToWord();
}

function sendPDFToCustomer(btn) {
    const element = document.getElementById('printableArea');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    const opt = {
        margin: [2, 2, 2, 2],
        filename: 'Q-<?php echo htmlspecialchars($quote['quote_no'] ?? 'quotation'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 3,
            useCORS: true,
            logging: false
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'] }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        const customerEmail = '<?php echo htmlspecialchars($quote['cust_email'] ?? ''); ?>';
        const quoteNo = '<?php echo htmlspecialchars($quote['quote_no'] ?? 'quotation'); ?>';

        if (customerEmail) {
            if (confirm('ดาวน์โหลด PDF เรียบร้อย\n\nต้องการเปิดอีเมลเพื่อส่งให้ลูกค้า (' + customerEmail + ') หรือไม่?')) {
                const subject = encodeURIComponent('ใบเสนอราคา ' + quoteNo);
                const body = encodeURIComponent('เรียน คุณลูกค้า\n\nตามเอกสารแนบเป็นใบเสนอราคาเลขที่ ' + quoteNo + '\n\nขอแสดงความนับถือ');
                window.open('mailto:' + customerEmail + '?subject=' + subject + '&body=' + body, '_blank');
            }
        } else {
            alert('ดาวน์โหลด PDF เรียบร้อย กรุณาส่งไฟล์ให้ลูกค้าผ่านช่องทางที่สะดวก (LINE, Email, Messenger ฯลฯ)');
        }

        btn.innerHTML = originalContent;
        btn.disabled = false;
    });
}
</script>
<?php include "footer.php"; ?>