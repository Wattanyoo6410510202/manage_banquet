<?php
include "config.php";
include "header.php";

// 1. รับ ID ของใบเสนอราคาที่ต้องการแก้ไข
$quote_id = intval($_GET['id'] ?? 0);

if ($quote_id > 0) {
    // ดึงข้อมูลหลักจาก quotations
    $sql = "SELECT * FROM quotations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quote_id);
    $stmt->execute();
    $quote = $stmt->get_result()->fetch_assoc();

    if (!$quote) {
        echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm'>ไม่พบข้อมูลใบเสนอราคาในระบบ</div></div>";
        exit;
    }

    // ดึงรายการสินค้าเดิม
    $items_sql = "SELECT * FROM quotation_items WHERE quote_id = ? ORDER BY id ASC";
    $stmt_items = $conn->prepare($items_sql);
    $stmt_items->bind_param("i", $quote_id);
    $stmt_items->execute();
    $items_res = $stmt_items->get_result();
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm'>ไม่ระบุ ID ของใบเสนอราคา</div></div>";
    exit;
}

// ดึงรายชื่อลูกค้าและบริษัทสำหรับ Dropdown
$customers_res = $conn->query("SELECT id, cust_name FROM customers ORDER BY cust_name ASC");
$company_res = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
?>

<div class="container-fluid p-0">
    <form action="api/update_quote.php" method="POST" id="mainQuoteForm">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-pencil-square"></i> แก้ไขใบเสนอราคา (<?= htmlspecialchars($quote['quote_no']) ?>)
                </h4>
                <div>
                    <button type="submit" class="btn btn-success px-4 shadow-sm fw-bold">
                        <i class="bi bi-check-circle me-1"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">

                <div class="col-md-3">
                    <label class="form-label fw-bold">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quote_no" class="form-control bg-light"
                        value="<?= htmlspecialchars($quote['quote_no']) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">เลือกลูกค้า *</label>
                    <select name="customer_id" class="form-select select2">
                        <option value="">--- เลือกรายชื่อลูกค้า ---</option>
                        <?php
                        if ($customers_res) {
                            $customers_res->data_seek(0);
                            while ($c = $customers_res->fetch_assoc()):
                                $selected = ($c['id'] == $quote['customer_id']) ? "selected" : "";
                                echo "<option value='{$c['id']}' $selected>{$c['cust_name']}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-primary">วันที่จัดงาน</label>
                    <input type="date" name="event_date" class="form-control" value="<?= $quote['event_date'] ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">วันที่สิ้นสุด (Expiry)</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= $quote['expiry_date'] ?>">
                </div>

                <div class="col-md-9">
                    <label class="form-label fw-bold">ชื่อโครงการ/งาน</label>
                    <input type="text" name="event_name" class="form-control"
                        value="<?= htmlspecialchars($quote['event_name']) ?>" placeholder="ระบุชื่องาน">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-building me-1"></i> บริษัท/ธุรกิจ</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- เลือกบริษัท --</option>
                        <?php
                        if ($company_res) {
                            $company_res->data_seek(0);
                            while ($comp = $company_res->fetch_assoc()):
                                $selected = ($comp['id'] == $quote['company_id']) ? "selected" : "";
                                echo "<option value='{$comp['id']}' $selected>{$comp['company_name']}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered align-middle" id="itemTable">
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
                        <?php
                        $i = 1;
                        if ($items_res && $items_res->num_rows > 0):
                            while ($item = $items_res->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $i++ ?></td>
                                    <td>
                                        <textarea name="item_name[]" class="form-control" rows="2" style="resize:none;"
                                            required><?= htmlspecialchars($item['item_name']) ?></textarea>
                                    </td>
                                    <td><input type="number" name="quantity[]" class="form-control text-center qty"
                                            value="<?= $item['quantity'] ?>" min="1"></td>
                                    <td><input type="number" name="unit_price[]" class="form-control text-end price"
                                            value="<?= number_format($item['unit_price'], 2, '.', '') ?>" step="0.01"></td>
                                    <td><input type="number" name="total_price[]" class="form-control text-end row-total"
                                            value="<?= number_format($item['total_price'], 2, '.', '') ?>" readonly></td>
                                    <td class="text-center">
                                        <i class="bi bi-trash text-danger removeRow"
                                            style="cursor:pointer; font-size: 1.2rem;"></i>
                                    </td>
                                </tr>
                                <?php
                            endwhile;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addRow">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มแถวรายการ
            </button>

            <div class="row">
                <div class="col-md-7">
                    <div class="card border-0 bg-light p-3 h-100">
                        <label class="form-label fw-bold"><i class="bi bi-info-circle me-1"></i> หมายเหตุเพิ่มเติม
                            (Remarks)</label>
                        <textarea name="remarks" class="form-control" rows="6"
                            placeholder="ระบุเงื่อนไขเพิ่มเติม..."><?= htmlspecialchars($quote['remarks']) ?></textarea>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="vatToggle" <?= (isset($quote['vat']) && $quote['vat'] > 0) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="vatToggle">คำนวณภาษีมูลค่าเพิ่ม (VAT 7%)</label>
                    </div>

                    <input type="hidden" id="includeVat" name="include_vat"
                        value="<?= ($quote['vat'] > 0) ? '1' : '0' ?>">

                    <div class="card p-3 shadow-sm bg-white border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold">รวมเงิน (Subtotal):</span>
                            <input type="number" id="subtotal" name="subtotal"
                                class="text-end border-0 bg-transparent fw-bold w-50"
                                value="<?= number_format($quote['subtotal'] ?? 0, 2, '.', '') ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 text-muted">
                            <span>ภาษี (VAT 7%):</span>
                            <input type="number" id="vat" name="vat" class="text-end border-0 bg-transparent w-50"
                                value="<?= number_format($quote['vat'] ?? 0, 2, '.', '') ?>" readonly>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center fw-bold text-primary">
                            <span class="fs-6">ยอดสุทธิ (Grand Total):</span>
                            <input type="number" id="grand_total" name="grand_total"
                                class="text-end border-0 bg-transparent fw-bold text-primary fs-5 w-50"
                                value="<?= number_format($quote['grand_total'] ?? 0, 2, '.', '') ?>" readonly>
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
        // 1. คำนวณยอดเริ่มต้นทันทีเมื่อโหลดหน้า (เพื่อให้สอดคล้องกับสถานะ Toggle จาก DB)
        calculateAll();

        // เริ่มต้นระบบ Select2 สำหรับค้นหาลูกค้า
        $('.select2').select2({
            theme: "classic",
            width: '100%'
        });

        // เมื่อมีการคลิก Toggle VAT (เปิด-ปิด)
        $('#vatToggle').change(function() {
            if($(this).is(':checked')) {
                $('#includeVat').val('1'); // ส่งค่า 1 ไปที่ API
            } else {
                $('#includeVat').val('0'); // ส่งค่า 0 ไปที่ API
            }
            calculateAll(); // สั่งคำนวณยอดใหม่ทันที
        });

        // เพิ่มแถวรายการใหม่
        $('#addRow').click(function () {
            let rowCount = $('#itemTable tbody tr').length + 1;
            let newRow = `<tr>
                <td class="text-center fw-bold">${rowCount}</td>
                <td><textarea name="item_name[]" class="form-control" rows="2" style="resize:none;" required></textarea></td>
                <td><input type="number" name="quantity[]" class="form-control text-center qty" value="1" min="1"></td>
                <td><input type="number" name="unit_price[]" class="form-control text-end price" value="0.00" step="0.01"></td>
                <td><input type="number" name="total_price[]" class="form-control text-end row-total" value="0.00" readonly></td>
                <td class="text-center">
                    <i class="bi bi-trash text-danger removeRow" style="cursor:pointer; font-size: 1.2rem;"></i>
                </td>
            </tr>`;
            $('#itemTable tbody').append(newRow);
        });

        // ลบแถวรายการ
        $(document).on('click', '.removeRow', function () {
            if ($('#itemTable tbody tr').length > 1) {
                $(this).closest('tr').remove();
                calculateAll();
                updateRowNumbers();
            } else {
                alert("ต้องมีอย่างน้อย 1 รายการครับ");
            }
        });

        // คำนวณรายบรรทัดเมื่อมีการเปลี่ยนเลข (จำนวน หรือ ราคา)
        $(document).on('input', '.qty, .price', function () {
            let row = $(this).closest('tr');
            let qty = parseFloat(row.find('.qty').val()) || 0;
            let price = parseFloat(row.find('.price').val()) || 0;
            let total = qty * price;
            row.find('.row-total').val(total.toFixed(2));
            calculateAll();
        });

        // ฟังก์ชันคำนวณยอดรวมทั้งหมด (Subtotal, VAT, Grand Total)
        function calculateAll() {
            let subtotal = 0;
            
            // วนลูปหาผลรวมของทุกแถว
            $('.row-total').each(function () {
                subtotal += parseFloat($(this).val()) || 0;
            });

            let vat = 0;
            // ตรวจสอบสถานะ Toggle ว่าให้คำนวณ VAT หรือไม่
            if ($('#vatToggle').is(':checked')) {
                vat = subtotal * 0.07;
            }

            let grand = subtotal + vat;

            // แสดงผลลงในช่อง Input ต่างๆ
            $('#subtotal').val(subtotal.toFixed(2));
            $('#vat').val(vat.toFixed(2));
            $('#grand_total').val(grand.toFixed(2));
        }

        // ฟังก์ชันเรียงเลขลำดับแถวใหม่ (ใช้ตอนลบแถว)
        function updateRowNumbers() {
            $('#itemTable tbody tr').each(function (index) {
                $(this).find('td:first').text(index + 1);
            });
        }
    });
</script>