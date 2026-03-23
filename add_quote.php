<?php
include "config.php";
include "header.php";

// 1. ดึงรายชื่อลูกค้าทั้งหมดมาเตรียมไว้ใส่ Dropdown
$customers_sql = "SELECT id, cust_name FROM customers ORDER BY cust_name ASC";
$customers_res = $conn->query($customers_sql);

// 2. ตั้งค่าตัวแปรเริ่มต้น
$function_id = $_GET['function_id'] ?? null;
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
                    <label class="form-label fw-bold text-danger">เลือกลูกค้า *</label>
                    <select name="customer_id" id="customer_select" class="form-select select2">
                        <option value="">--- เลือกรายชื่อลูกค้า ---</option>
                        <?php
                        if ($customers_res->num_rows > 0) {
                            $customers_res->data_seek(0);
                            while ($c = $customers_res->fetch_assoc()):
                                $selected = ($c['id'] == $selected_customer_id) ? "selected" : "";
                                echo "<option value='{$c['id']}' $selected>{$c['cust_name']}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
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
                                    style="resize: none; min-width: 200px; resize-y;" required></textarea>
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
                        <label class="form-label fw-bold text-dark">
                            <i class="bi bi-info-circle me-1"></i> หมายเหตุเพิ่มเติม (Remarks)
                        </label>
                        <textarea name="remarks" class="form-control" rows="6"
                            placeholder="ระบุเงื่อนไขเพิ่มเติมที่ต้องการให้แสดงในใบเสนอราคา..."><?= $remarks ?? '' ?></textarea>
                        <div class="form-text text-muted mt-2">
                            * ข้อมูลนี้จะปรากฏที่ส่วนล่างของเอกสารใบเสนอราคา
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

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        // 1. ระบบค้นหาใน Dropdown (Select2)
        $('.select2').select2({
            theme: "classic",
            width: '100%'
        });

        // 2. เพิ่มแถวรายการใหม่
        $('#addRow').click(function () {
            let rowCount = $('#itemTable tbody tr').length + 1;
            let newRow = `<tr>
                <td class="text-center">${rowCount}</td>
                <td><input type="text" name="item_name[]" class="form-control" required></td>
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
</script>