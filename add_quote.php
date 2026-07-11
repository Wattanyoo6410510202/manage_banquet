<?php
include "config.php";
include "header.php";

// 1. ดึงรายชื่อ Project ทั้งหมด (พร้อมตรวจสอบแหล่งที่มา)
$projects_sql = "SELECT ep.id, ep.project_name,
                        (SELECT COUNT(*) FROM functions f WHERE f.project_id = ep.id) as has_eo,
                        (SELECT COUNT(*) FROM quotations q WHERE q.project_id = ep.id) as has_quotation
                 FROM event_projects ep 
                 ORDER BY ep.project_name ASC";
$projects_res = $conn->query($projects_sql);

// 2. ตั้งค่าตัวแปรเริ่มต้น
$function_id = $_GET['function_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$selected_customer_id = "";
$event_name = "";
$event_date = date('Y-m-d');
$expiry_date = date('Y-m-d', strtotime('+30 days')); // Default วันหมดอายุล่วงหน้า 30 วัน

if ($function_id) {
    $sql = "SELECT * FROM functions WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $function_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $event_name = $row['function_name'];
        $selected_customer_id = $row['customer_id'];
        $event_date = $row['event_date'];
        $project_id = $row['project_id']; // ดึง project_id มาด้วย
    }
}

// 3. ดึงเทมเพลตเมนูและเบรก
$menu_templates = $conn->query("
    SELECT fmd.id, mt.type_name, fmd.menu_items, fmd.price_per_pax
    FROM function_menu_details fmd
    JOIN master_menu_types mt ON fmd.menu_type_id = mt.id
    ORDER BY mt.type_name, fmd.id
");

$break_templates = $conn->query("
    SELECT fb.id, bt.type_name, fb.break_menu, fb.break_price
    FROM function_breaks fb
    JOIN master_break_types bt ON fb.break_type_id = bt.id
    ORDER BY bt.type_name, fb.id
");

?>

<div class="container-fluid p-0">
    <form action="api/save_quote.php" method="POST" id="mainQuoteForm">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-file-earmark-plus"></i> ออกใบเสนอราคาใหม่
                </h4>
                <button type="submit" form="mainQuoteForm" class="btn btn-primary px-4 shadow-sm">
                    <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                </button>
            </div>

            <input type="hidden" name="function_id" value="<?= $function_id ?>">

            <!-- Row 1: Company, Quote No, Project -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1"><i class="bi bi-building me-1"></i> บริษัท/ธุรกิจ</label>
                    <select name="company_id" class="form-select form-select-sm" required>
                        <option value="">-- เลือกบริษัท --</option>
                        <?php
                        $company_id = $row['company_id'] ?? '';
                        $company_sql = "SELECT id, company_name FROM companies ORDER BY company_name ASC";
                        $company_res = $conn->query($company_sql);
                        while ($comp = $company_res->fetch_assoc()):
                            ?>
                            <option value="<?= $comp['id'] ?>" <?= ($company_id == $comp['id']) ? 'selected' : '' ?>>
                                <?= $comp['company_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quote_no" class="form-control form-control-sm bg-light" value="QT-<?= date('Ymd-Hi') ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small mb-1">อ้างอิงโครงการที่มีอยู่เดิม</label>
                    <select name="project_id" class="form-select form-select-sm select2">
                        <option value="">--- ไม่ระบุโครงการ ---</option>
                        <?php
                        if ($projects_res->num_rows > 0) {
                            $projects_res->data_seek(0);
                            while ($p = $projects_res->fetch_assoc()):
                                $selected = ($p['id'] == $project_id) ? "selected" : "";
                                $sources = [];
                                if ($p['has_eo'] > 0) $sources[] = 'EO';
                                if ($p['has_quotation'] > 0) $sources[] = 'QT';
                                $src_text = !empty($sources) ? ' [' . implode('+', $sources) . ']' : '';
                                echo "<option value='{$p['id']}' data-sources='" . implode(',', $sources) . "' $selected>{$p['project_name']}{$src_text}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Customer (full width) -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label fw-bold small mb-1 text-danger">เลือกลูกค้า *</label>
                    <select name="customer_id" id="customer_select" class="form-select form-select-sm select2-customer-search" required>
                        <?php if ($selected_customer_id): 
                            $c_stmt = $conn->prepare("SELECT cust_name, cust_phone, cust_address FROM customers WHERE id = ?");
                            $c_stmt->bind_param("i", $selected_customer_id);
                            $c_stmt->execute();
                            $c_row = $c_stmt->get_result()->fetch_assoc();
                            $c_name = $c_row['cust_name'] ?? '--- เลือกรายชื่อลูกค้า ---';
                            $c_phone = $c_row['cust_phone'] ?? '';
                            $c_address = $c_row['cust_address'] ?? '';
                        ?>
                            <option value="<?= $selected_customer_id ?>" selected><?= htmlspecialchars($c_name) ?></option>
                        <?php else: 
                            $c_phone = '';
                            $c_address = '';
                        ?>
                            <option value="">--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---</option>
                        <?php endif; ?>
                    </select>
                    <div id="customer_info" class="mt-1 p-2 rounded-3 bg-light border <?= $selected_customer_id ? '' : 'd-none' ?>" style="font-size: 0.8rem;">
                        <div class="fw-bold text-dark" id="info_name"><?= htmlspecialchars($selected_customer_id ? $c_name : '') ?></div>
                        <div class="text-muted small" id="info_phone"><?= htmlspecialchars($c_phone ?? '') ?></div>
                        <div class="text-muted small" id="info_address"><?= htmlspecialchars($c_address ?? '') ?></div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Event name (full width) -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label fw-bold small mb-1">ชื่อโครงการ/งาน</label>
                    <input type="text" name="event_name" class="form-control form-control-sm" value="<?= $event_name ?>" placeholder="ระบุชื่องาน">
                </div>
            </div>

            <!-- Row 4: Event date, Expiry date -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold small mb-1">วันที่จัดงาน</label>
                    <input type="date" name="event_date" class="form-control form-control-sm" value="<?= $event_date ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small mb-1">วันที่สิ้นสุด</label>
                    <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= $expiry_date ?>">
                </div>
            </div>

            <hr>

            <!-- ====== เพิ่มรายการจากเทมเพลต ====== -->
            <div class="card border-0 bg-light-subtle mb-4">
                <div class="card-header bg-transparent border-0 py-2" data-bs-toggle="collapse" data-bs-target="#templatePanel" style="cursor:pointer">
                    <i class="bi bi-box-seam me-1"></i> เพิ่มรายการจากเทมเพลต
                    <i class="bi bi-chevron-down small float-end"></i>
                </div>
                <div class="collapse" id="templatePanel">
                    <div class="card-body pt-0">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-5">
                                <label class="form-label fw-bold small mb-1">🍽️ เมนูอาหาร</label>
                                <select id="menuTemplate" class="form-select form-select-sm">
                                    <option value="">-- เลือกเมนู --</option>
                                    <?php if ($menu_templates && $menu_templates->num_rows):
                                        $menu_templates->data_seek(0);
                                        while ($m = $menu_templates->fetch_assoc()): ?>
                                        <option value="<?= $m['id'] ?>"
                                            data-name="<?= htmlspecialchars($m['menu_items'], ENT_QUOTES) ?>"
                                            data-price="<?= $m['price_per_pax'] ?>">
                                            [<?= htmlspecialchars($m['type_name']) ?>] <?= mb_substr(htmlspecialchars($m['menu_items']), 0, 80) ?>...
                                            (<?= number_format($m['price_per_pax'], 2) ?> บาท/หน่วย)
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small mb-1">จำนวน</label>
                                <input type="number" id="menuQty" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small mb-1">ราคา/หน่วย</label>
                                <input type="text" id="menuPrice" class="form-control form-control-sm bg-white" readonly>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-sm w-100" id="addMenuTemplate">
                                    <i class="bi bi-plus-lg"></i> เพิ่ม
                                </button>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label fw-bold small mb-1">☕ เบรก</label>
                                <select id="breakTemplate" class="form-select form-select-sm">
                                    <option value="">-- เลือกเบรก --</option>
                                    <?php if ($break_templates && $break_templates->num_rows):
                                        $break_templates->data_seek(0);
                                        while ($b = $break_templates->fetch_assoc()): ?>
                                        <option value="<?= $b['id'] ?>"
                                            data-name="<?= htmlspecialchars($b['break_menu'], ENT_QUOTES) ?>"
                                            data-price="<?= $b['break_price'] ?>">
                                            [<?= htmlspecialchars($b['type_name']) ?>] <?= htmlspecialchars($b['break_menu']) ?>
                                            (<?= number_format($b['break_price'], 2) ?> บาท/หน่วย)
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small mb-1">จำนวน</label>
                                <input type="number" id="breakQty" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small mb-1">ราคา/หน่วย</label>
                                <input type="text" id="breakPrice" class="form-control form-control-sm bg-white" readonly>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-sm w-100" id="addBreakTemplate">
                                    <i class="bi bi-plus-lg"></i> เพิ่ม
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ====== END เทมเพลต ====== -->

            <div class="table-responsive">
                <table class="table table-bordered table-items" id="itemTable">
                    <thead class="table-light text-center" style="font-size:0.85rem;">
                        <tr>
                            <th width="5%">#</th>
                            <th>รายละเอียดรายการ</th>
                            <th width="12%">จำนวน</th>
                            <th width="15%">ราคา/หน่วย</th>
                            <th width="15%">ยอดรวม</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">1</td>
                            <td>
                                <textarea name="item_name[]" class="form-control form-control-sm"
                                    placeholder="ระบุรายการ เช่น ค่าอาหาร..." rows="2"
                                    style="resize: vertical; min-width: 200px;" required></textarea>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="1" min="1"></td>
                            <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="0.00" step="0.01"></td>
                            <td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="0.00" readonly></td>
                            <td class="text-center"><i class="bi bi-trash text-danger removeRow" style="cursor:pointer"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addRow">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มแถวรายการ
            </button>

            <div class="row mt-4">
                <div class="col-md-7 col-lg-8">
                    <div class="card border-0 bg-light-subtle p-3 h-100">
                        <div class="mb-3">
                            <label class="form-label fw-bold small mb-1">
                                <i class="bi bi-info-circle me-1"></i> หมายเหตุเพิ่มเติม (Remarks)
                            </label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="3"
                                placeholder="ระบุเงื่อนไขเพิ่มเติมที่ต้องการให้แสดงในใบเสนอราคา..."><?= $remarks ?? '' ?></textarea>
                        </div>
                        <div>
                            <label class="form-label fw-bold small mb-1 text-danger">
                                <i class="bi bi-x-circle me-1"></i> สาเหตุที่ปิดงานไม่ได้ (Lost Reason)
                            </label>
                            <textarea name="lost_reason" class="form-control form-control-sm" rows="3"
                                placeholder="ระบุสาเหตุที่ลูกค้าไม่ตกลง หรือต้องยกเลิกใบเสนอราคานี้..."></textarea>
                            <div class="form-text text-muted small">
                                * สำหรับบันทึกภายใน (ไม่แสดงในเอกสาร PDF)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5 col-lg-4">
                    <div class="card p-3 border shadow-sm bg-white mb-3">
                        <label class="form-label fw-bold small mb-1"><i class="bi bi-percent"></i> การคิดภาษี</label>
                        <select name="vat_type" id="vatType" class="form-select form-select-sm">
                            <option value="exclude" selected>แยกนอก (Exclude VAT)</option>
                            <option value="include">รวมใน (Include VAT)</option>
                            <option value="no">ไม่มี VAT</option>
                        </select>
                    </div>

                    <div class="card p-3 border shadow-sm bg-white">
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">รวมเป็นเงิน (Subtotal):</span>
                            <input type="number" id="subtotal" name="subtotal"
                                class="text-end border-0 bg-transparent fw-bold w-50" value="0.00" readonly>
                        </div>

                        <div class="d-flex justify-content-between mb-2 small text-muted" id="vat-row">
                            <span>VAT (7%):</span>
                            <input type="number" id="vat" name="vat" class="text-end border-0 bg-transparent w-50"
                                value="0.00" readonly>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center fw-bold text-primary">
                            <span class="fs-6">ยอดรวมสุทธิ:</span>
                            <input type="number" id="grand_total" name="grand_total"
                                class="text-end border-0 bg-transparent fw-bold text-primary fs-5 w-50" value="0.00"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function escapeHtml(text) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    $(document).ready(function () {
        // 2. เพิ่มแถวรายการใหม่
        $('#addRow').click(function () {
            let rowCount = $('#itemTable tbody tr').length + 1;
            let newRow = `<tr>
                <td class="text-center">${rowCount}</td>
                <td><textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical; min-width: 200px;" required></textarea></td>
                <td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="1" min="1"></td>
                <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="0.00" step="0.01"></td>
                <td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="0.00" readonly></td>
                <td class="text-center"><i class="bi bi-trash text-danger removeRow" style="cursor:pointer"></i></td>
            </tr>`;
            $('#itemTable tbody').append(newRow);
        });

        // 3. ลบแถวรายการ
        $(document).on('click', '.removeRow', function () {
            if ($('#itemTable tbody tr').length > 1) {
                $(this).closest('tr').remove();
                calculateAll();
                updateRowNumbers();
            } else {
                alert("ต้องมีอย่างน้อย 1 รายการครับ");
            }
        });

        // 4. คำนวณยอดเงินรายบรรทัด เมื่อมีการเปลี่ยนจำนวนหรือราคา
        $(document).on('input', '.qty, .price', function () {
            let row = $(this).closest('tr');
            let qty = parseFloat(row.find('.qty').val()) || 0;
            let price = parseFloat(row.find('.price').val()) || 0;
            row.find('.row-total').val((qty * price).toFixed(2));
            calculateAll();
        });

        // 5. คลิกเปลี่ยนประเภท VAT
        $('#vatType').change(function () {
            calculateAll();
            // เอฟเฟกต์จางลงเมื่อไม่เลือก VAT (ต้องมี id="vat-row" ใน HTML)
            if ($(this).val() === 'no') {
                $('#vat-row').addClass('opacity-50');
            } else {
                $('#vat-row').removeClass('opacity-50');
            }
        });

        // 6. ฟังก์ชันหลักในการคำนวณยอดรวมทั้งหมด
        window.calculateAll = function calculateAll() {
            let sumItems = 0;

            // รวมยอดจากทุกแถว
            $('.row-total').each(function () {
                sumItems += parseFloat($(this).val()) || 0;
            });

            // Service Charge (ถ้าจะใช้ในอนาคต ให้แก้เลข 0 ตรงนี้)
            let service = sumItems * 0;

            let vatType = $('#vatType').val();
            let subtotal = 0;
            let vat = 0;
            let grand = 0;

            if (vatType === 'exclude') {
                // แยกนอก (Exclude VAT)
                subtotal = sumItems;
                vat = (subtotal + service) * 0.07;
                grand = subtotal + service + vat;
            } else if (vatType === 'include') {
                // รวมใน (Include VAT)
                grand = sumItems + service;
                subtotal = grand / 1.07;
                vat = grand - subtotal;
            } else {
                // ไม่มี VAT
                subtotal = sumItems;
                vat = 0;
                grand = subtotal + service;
            }

            // แสดงผลลัพธ์
            $('#subtotal').val(subtotal.toFixed(2));
            $('#service_charge').val(service.toFixed(2));
            $('#vat').val(vat.toFixed(2));
            $('#grand_total').val(grand.toFixed(2));
        }

        // 7. ฟังก์ชันอัปเดตเลขลำดับ # ให้เรียงใหม่เสมอ
        function updateRowNumbers() {
            $('#itemTable tbody tr').each(function (index) {
                $(this).find('td:first').text(index + 1);
            });
        }

    });


    // 8. เมื่อเลือกเทมเพลตเมนู -> แสดงราคา
    $('#menuTemplate').change(function () {
        var price = $(this).find(':selected').data('price') || 0;
        $('#menuPrice').val(parseFloat(price).toFixed(2));
    });

    // 9. เมื่อเลือกเทมเพลตเบรก -> แสดงราคา
    $('#breakTemplate').change(function () {
        var price = $(this).find(':selected').data('price') || 0;
        $('#breakPrice').val(parseFloat(price).toFixed(2));
    });

    // 10. เพิ่มรายการจากเทมเพลตเมนู
    $('#addMenuTemplate').click(function () {
        var $sel = $('#menuTemplate');
        var name = $sel.find(':selected').data('name') || '';
        var price = parseFloat($sel.find(':selected').data('price')) || 0;
        var qty = parseInt($('#menuQty').val()) || 1;
        if (!name) { alert('กรุณาเลือกเมนูก่อน'); return; }
        addTemplateRow(name, qty, price);
    });

    // 11. เพิ่มรายการจากเทมเพลตเบรก
    $('#addBreakTemplate').click(function () {
        var $sel = $('#breakTemplate');
        var name = $sel.find(':selected').data('name') || '';
        var price = parseFloat($sel.find(':selected').data('price')) || 0;
        var qty = parseInt($('#breakQty').val()) || 1;
        if (!name) { alert('กรุณาเลือกเบรกก่อน'); return; }
        addTemplateRow(name, qty, price);
    });

    // 12. ฟังก์ชันเพิ่มแถวจากเทมเพลต
    function addTemplateRow(name, qty, price) {
        var total = (qty * price).toFixed(2);
        var rowCount = $('#itemTable tbody tr').length + 1;
        var row = '<tr>'
            + '<td class="text-center">' + rowCount + '</td>'
            + '<td><textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical; min-width: 200px;" required>' + escapeHtml(name) + '</textarea></td>'
            + '<td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="' + qty + '" min="1"></td>'
            + '<td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="' + price.toFixed(2) + '" step="0.01"></td>'
            + '<td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="' + total + '" readonly></td>'
            + '<td class="text-center"><i class="bi bi-trash text-danger removeRow" style="cursor:pointer"></i></td>'
            + '</tr>';
        $('#itemTable tbody').append(row);
        calculateAll();
    }

    // 8. Initialize Select2 for customer search with AJAX
    function initCustomerSelect() {
        var $sel = $('#customer_select');
        if (!$sel.length) return;
        if (!$.fn.select2) { setTimeout(initCustomerSelect, 200); return; }
        $sel.select2({
            width: '100%',
            placeholder: '--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---',
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
                },
                error: function(err) {
                    console.error("AJAX Error:", err);
                }
            },
            templateSelection: function(data) {
                return data.text || data.cust_name || '--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---';
            }
        }).on('select2:select', function(e) {
            var data = e.params.data;
            $('#customer_info').removeClass('d-none');
            $('#info_name').text(data.cust_name || data.text || '');
            $('#info_phone').text(data.cust_phone || '');
            $('#info_address').text(data.cust_address || '');
        }).on('select2:clear', function() {
            $('#customer_info').addClass('d-none');
            $('#info_name').text('');
            $('#info_phone').text('');
            $('#info_address').text('');
        });
        console.log("Select2 Initialized on #customer_select");
    }
    initCustomerSelect();

    // 9. Initialize Select2 for project dropdown with source badges
    function initProjectSelect() {
        var $sel = $('select[name="project_id"]');
        if (!$sel.length) return;
        if (!$.fn.select2) { setTimeout(initProjectSelect, 200); return; }
        $sel.select2({
            width: '100%',
            placeholder: '--- ไม่ระบุโครงการ ---',
            allowClear: true,
            templateResult: function(data) {
                if (!data.id) return data.text;
                var name = data.text.replace(/\s*\[.*?\]/g, '');
                var $el = $('<span>' + name + '</span>');
                var sources = $(data.element).data('sources') || '';
                if (sources) {
                    var parts = sources.split(',');
                    parts.forEach(function(s) {
                        if (s === 'EO') $el.append(' <span class="badge bg-info-subtle text-info" style="font-size:0.65rem;">EO</span>');
                        else if (s === 'QT') $el.append(' <span class="badge bg-warning-subtle text-warning" style="font-size:0.65rem;">ใบเสนอราคา</span>');
                    });
                }
                return $el;
            },
            templateSelection: function(data) {
                if (!data.id) return data.text;
                var name = data.text.replace(/\s*\[.*?\]/g, '');
                var $el = $('<span>' + name + '</span>');
                var sources = $(data.element).data('sources') || '';
                if (sources) {
                    var parts = sources.split(',');
                    parts.forEach(function(s) {
                        if (s === 'EO') $el.append(' <span class="badge bg-info-subtle text-info" style="font-size:0.65rem;">EO</span>');
                        else if (s === 'QT') $el.append(' <span class="badge bg-warning-subtle text-warning" style="font-size:0.65rem;">QT</span>');
                    });
                }
                return $el;
            }
        });

        // เมื่อเลือก Project -> ดึงข้อมูลเก่ามา Auto-fill
        function loadProjectData(pid) {
            if (!pid) return;
            console.log('Loading project data for pid:', pid);
            $.getJSON('api/get_project_quotations.php?project_id=' + pid + '&t=' + new Date().getTime())
            .done(function (res) {
                console.log('API response:', res);
                if (res.status !== 'success') return;
                var q = res.quote;

                if (q) {
                    var baseNo = q.quote_no.replace(/\/\d+$/, '');
                    $('input[name="quote_no"]').val(baseNo + '/' + res.version);
                } else {
                    $('input[name="quote_no"]').val('QT-' + (new Date().toISOString().slice(0,10).replace(/-/g,'')) + '-' +
                        ('0'+new Date().getHours()).slice(-2) + ('0'+new Date().getMinutes()).slice(-2) + '/' + res.version);
                }

                if (q) {
                    if (q.customer_id) {
                        var $cust = $('#customer_select');
                        $.getJSON('api/search_customers.php?q=&id=' + q.customer_id, function (cr) {
                            if (cr.results && cr.results.length > 0) {
                                var c = cr.results[0];
                                var opt = new Option(c.text, c.id, true, true);
                                $cust.append(opt).trigger('change');
                                $('#customer_info').removeClass('d-none');
                                $('#info_name').text(c.cust_name || c.text);
                                $('#info_phone').text(c.cust_phone || '');
                                $('#info_address').text(c.cust_address || '');
                            }
                        });
                    }

                    if (q.event_name) $('input[name="event_name"]').val(q.event_name);

                    if (q.event_date) {
                        $('input[name="event_date"]').val(q.event_date);
                    }

                    if (q.expiry_date) {
                        $('input[name="expiry_date"]').val(q.expiry_date);
                    }

                    if (q.company_id) $('select[name="company_id"]').val(q.company_id);
                    if (q.vat_type) $('#vatType').val(q.vat_type);
                    if (q.remarks) $('textarea[name="remarks"]').val(q.remarks);

                    if (res.items && res.items.length > 0) {
                        $('#itemTable tbody').empty();
                        res.items.forEach(function (item, idx) {
                            var rowNum = idx + 1;
                            var row = '<tr>'
                                + '<td class="text-center">' + rowNum + '</td>'
                                    + '<td><textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical; min-width: 200px;" required>' + escapeHtml(item.item_name || '') + '</textarea></td>'
                                    + '<td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="' + (item.quantity || 1) + '" min="1"></td>'
                                    + '<td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="' + (parseFloat(item.unit_price) || 0).toFixed(2) + '" step="0.01"></td>'
                                    + '<td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="' + (parseFloat(item.total_price) || 0).toFixed(2) + '" readonly></td>'
                                + '<td class="text-center"><i class="bi bi-trash text-danger removeRow" style="cursor:pointer"></i></td>'
                                + '</tr>';
                            $('#itemTable tbody').append(row);
                        });
                        calculateAll();
                    }
                }
            }).always(function () {
                $sel.prop('disabled', false);
            });
        }

        $sel.on('select2:select', function (e) {
            loadProjectData(e.params.data.id);
        });
        $sel.on('select2:clear', function () {
            $('input[name="event_name"]').val('');
            $('textarea[name="remarks"]').val('');
            $('input[name="quote_no"]').val('<?= "QT-" . date('Ymd-Hi') ?>');
        });

        // Auto-load if project_id already selected via URL
        var currentVal = $sel.val();
        if (currentVal) {
            loadProjectData(currentVal);
        }
    }
    initProjectSelect();
</script>

<style>
    .select2-container { z-index: 999999 !important; }
    .select2-container--default .select2-selection--single { height: 31px !important; line-height: 31px !important; border: 1px solid #ced4da !important; font-size: 0.875rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px !important; padding-left: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px !important; }
    .select2-container .badge { vertical-align: middle; margin-left: 2px; }
    .select2-results__option .badge { font-size: 0.65rem !important; }
    .select2-selection__rendered .badge { font-size: 0.6rem !important; }

    .table-items th, .table-items td { white-space: nowrap; }
    .table-items textarea { min-width: 180px; }
</style>

<?php include "footer.php"; ?>