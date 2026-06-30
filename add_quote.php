<?php
include "config.php";
include "header.php";

// 1. ดึงรายชื่อ Project ทั้งหมด
$projects_sql = "SELECT id, project_name FROM event_projects ORDER BY project_name ASC";
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
?>

<div class="container-fluid p-0">
    <form action="api/save_quote.php" method="POST" id="mainQuoteForm">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-file-earmark-plus"></i> ออกใบเสนอราคาใหม่
                </h4>

                <div>
                    <button type="submit" form="mainQuoteForm" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <input type="hidden" name="function_id" value="<?= $function_id ?>">

                <div class="col-md-3">
                    <label class="form-label fw-bold">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quote_no" class="form-control bg-light" value="QT-<?= date('Ymd-Hi') ?>"
                        readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-dark">อ้างอิงโครงการ (Project)</label>
                    <select name="project_id" class="form-select select2">
                        <option value="">--- ไม่ระบุโครงการ ---</option>
                        <?php
                        if ($projects_res->num_rows > 0) {
                            $projects_res->data_seek(0);
                            while ($p = $projects_res->fetch_assoc()):
                                $selected = ($p['id'] == $project_id) ? "selected" : "";
                                echo "<option value='{$p['id']}' $selected>{$p['project_name']}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">เลือกลูกค้า *</label>
                    <select name="customer_id" id="customer_select" class="form-select select2-customer-search" required>
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

                    <!-- Customer info display -->
                    <div id="customer_info" class="mt-2 p-2 rounded-3 bg-light border <?= $selected_customer_id ? '' : 'd-none' ?>" style="font-size: 0.8rem;">
                        <div class="fw-bold text-dark" id="info_name"><?= htmlspecialchars($selected_customer_id ? $c_name : '') ?></div>
                        <div class="text-muted small" id="info_phone"><?= htmlspecialchars($c_phone ?? '') ?></div>
                        <div class="text-muted small" id="info_address"><?= htmlspecialchars($c_address ?? '') ?></div>
                    </div>
                </div>
                <div class="col-md-3 mt-3">
                    <label class="form-label fw-bold text-primary">วันที่จัดงาน</label>
                    <input type="date" name="event_date" class="form-control" value="<?= $event_date ?>">
                </div>
                <div class="col-md-3 mt-3">
                    <label class="form-label fw-bold text-danger">วันที่สิ้นสุด (Expiry)</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= $expiry_date ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label fw-bold">ชื่อโครงการ/งาน</label>
                    <input type="text" name="event_name" class="form-control" value="<?= $event_name ?>"
                        placeholder="ระบุชื่องาน">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-building me-1"></i> เลือกบริษัท/ธุรกิจ</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- เลือกบริษัท --</option>
                        <?php
                        // ดึงรายชื่อบริษัทมาแสดง
                        $company_id = $row['company_id'] ?? '';
                        $company_sql = "SELECT id, company_name FROM companies  ORDER BY company_name ASC";
                        $company_res = $conn->query($company_sql);
                        while ($comp = $company_res->fetch_assoc()):
                            ?>
                            <option value="<?= $comp['id'] ?>" <?= ($company_id == $comp['id']) ? 'selected' : '' ?>>
                                <?= $comp['company_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <hr>

            <div class="table-responsive">
                <table class="table table-bordered table-items" id="itemTable">
                    <thead class="table-light text-center">
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
                                <textarea name="item_name[]" class="form-control"
                                    placeholder="ระบุรายการ เช่น ค่าอาหาร..." rows="2"
                                    style="resize: vertical; min-width: 200px;" required></textarea>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control text-center qty" value="1"
                                    min="1"></td>
                            <td><input type="number" name="unit_price[]" class="form-control text-end price"
                                    value="0.00" step="0.01"></td>
                            <td><input type="number" name="total_price[]" class="form-control text-end row-total"
                                    value="0.00" readonly></td>
                            <td></td>
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
                            <label class="form-label fw-bold text-dark">
                                <i class="bi bi-info-circle me-1"></i> หมายเหตุเพิ่มเติม (Remarks)
                            </label>
                            <textarea name="remarks" class="form-control" rows="3"
                                placeholder="ระบุเงื่อนไขเพิ่มเติมที่ต้องการให้แสดงในใบเสนอราคา..."><?= $remarks ?? '' ?></textarea>
                        </div>
                        <div>
                            <label class="form-label fw-bold text-danger">
                                <i class="bi bi-x-circle me-1"></i> สาเหตุที่ปิดงานไม่ได้ (Lost Reason)
                            </label>
                            <textarea name="lost_reason" class="form-control" rows="3"
                                placeholder="ระบุสาเหตุที่ลูกค้าไม่ตกลง หรือต้องยกเลิกใบเสนอราคานี้..."></textarea>
                            <div class="form-text text-muted">
                                * สำหรับบันทึกภายใน (ไม่แสดงในเอกสาร PDF)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5 col-lg-4">
                    <div
                        class="form-check form-switch d-flex justify-content-between align-items-center bg-white p-3 rounded border mb-3 shadow-sm">
                        <label class="form-check-label fw-bold mb-0" for="includeVat">คำนวณภาษี (VAT 7%)</label>
                        <input class="form-check-input ms-0" type="checkbox" id="includeVat" name="include_vat"
                            value="1" checked>
                    </div>

                    <div class="card p-3 border shadow-sm bg-white">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">รวมเป็นเงิน (Subtotal):</span>
                            <input type="number" id="subtotal" name="subtotal"
                                class="text-end border-0 bg-transparent fw-bold w-50" value="0.00" readonly>
                        </div>

                        <div class="d-flex justify-content-between mb-2 text-muted" id="vat-row">
                            <span>VAT (7%):</span>
                            <input type="number" id="vat" name="vat" class="text-end border-0 bg-transparent w-50"
                                value="0.00" readonly>
                        </div>

                        <hr class="my-3">

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
    $(document).ready(function () {
        // 2. เพิ่มแถวรายการใหม่
        $('#addRow').click(function () {
            let rowCount = $('#itemTable tbody tr').length + 1;
            let newRow = `<tr>
                <td class="text-center">${rowCount}</td>
                <td><textarea name="item_name[]" class="form-control" rows="2" style="resize: vertical; min-width: 200px;" required></textarea></td>
                <td><input type="number" name="quantity[]" class="form-control text-center qty" value="1" min="1"></td>
                <td><input type="number" name="unit_price[]" class="form-control text-end price" value="0.00" step="0.01"></td>
                <td><input type="number" name="total_price[]" class="form-control text-end row-total" value="0.00" readonly></td>
                <td class="text-center"><i class="bi bi-trash text-danger removeRow" style="cursor:pointer"></i></td>
            </tr>`;
            $('#itemTable tbody').append(newRow);
        });

        // 3. ลบแถวรายการ
        $(document).on('click', '.removeRow', function () {
            $(this).closest('tr').remove();
            calculateAll(); // คำนวณใหม่ทันทีหลังลบ
            updateRowNumbers(); // รันเลขลำดับใหม่
        });

        // 4. คำนวณยอดเงินรายบรรทัด เมื่อมีการเปลี่ยนจำนวนหรือราคา
        $(document).on('input', '.qty, .price', function () {
            let row = $(this).closest('tr');
            let qty = parseFloat(row.find('.qty').val()) || 0;
            let price = parseFloat(row.find('.price').val()) || 0;
            row.find('.row-total').val((qty * price).toFixed(2));
            calculateAll();
        });

        // 5. คลิกเปิด-ปิด VAT
        $('#includeVat').change(function () {
            calculateAll();
            // เอฟเฟกต์จางลงเมื่อไม่เลือก VAT (ต้องมี id="vat-row" ใน HTML)
            if ($(this).is(':checked')) {
                $('#vat-row').removeClass('opacity-50');
            } else {
                $('#vat-row').addClass('opacity-50');
            }
        });

        // 6. ฟังก์ชันหลักในการคำนวณยอดรวมทั้งหมด
        function calculateAll() {
            let subtotal = 0;

            // รวมยอดจากทุกแถว
            $('.row-total').each(function () {
                subtotal += parseFloat($(this).val()) || 0;
            });

            // Service Charge (ถ้าจะใช้ในอนาคต ให้แก้เลข 0 ตรงนี้)
            let service = subtotal * 0;

            // คำนวณ VAT 7% เฉพาะเมื่อ Checkbox ถูกเลือก
            let vat = 0;
            if ($('#includeVat').is(':checked')) {
                vat = (subtotal + service) * 0.07;
            }

            // คำนวณยอดสุทธิ
            let grand = subtotal + service + vat;

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
</script>

<style>
    /* Force Select2 dropdown to be visible and correctly styled */
    .select2-container { z-index: 999999 !important; }
    .select2-selection--single { height: 38px !important; line-height: 38px !important; border: 1px solid #ced4da !important; }
    .select2-selection__rendered { line-height: 38px !important; }
    .select2-selection__arrow { height: 36px !important; }
</style>

<?php include "footer.php"; ?>