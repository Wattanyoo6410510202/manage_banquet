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

<div class="container p-0">
    <form action="api/save_quote.php" method="POST">
        <div class="card p-4 border-0 shadow-sm">
            <h4 class="fw-bold mb-4 text-primary">
                <i class="bi bi-file-earmark-plus"></i> ออกใบเสนอราคาใหม่
            </h4>
            
            <div class="row g-3 mb-4">
                <input type="hidden" name="function_id" value="<?= $function_id ?>">
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quote_no" class="form-control bg-light" value="QT-<?= date('Ymd-Hi') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">เลือกลูกค้า *</label>
                    <select name="customer_id" id="customer_select" class="form-select select2" required>
                        <option value="">--- เลือกรายชื่อลูกค้า ---</option>
                        <?php 
                        if($customers_res->num_rows > 0) {
                            $customers_res->data_seek(0);
                            while($c = $customers_res->fetch_assoc()): 
                                $selected = ($c['id'] == $selected_customer_id) ? "selected" : "";
                                echo "<option value='{$c['id']}' $selected>{$c['cust_name']}</option>";
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-10">
                    <label class="form-label fw-bold">ชื่อโครงการ/งาน</label>
                    <input type="text" name="event_name" class="form-control" value="<?= $event_name ?>" placeholder="ระบุชื่องาน" required>
                </div>

                <div class="col-md-3 mt-3">
                    <label class="form-label fw-bold text-primary">วันที่จัดงาน</label>
                    <input type="date" name="event_date" class="form-control" value="<?= $event_date ?>" required>
                </div>

                <div class="col-md-3 mt-3">
                    <label class="form-label fw-bold text-danger">วันที่สิ้นสุด (Expiry)</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= $expiry_date ?>" required>
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
                            <td><input type="text" name="item_name[]" class="form-control" placeholder="ระบุรายการ เช่น ค่าอาหาร..." required></td>
                            <td><input type="number" name="quantity[]" class="form-control text-center qty" value="1" min="1"></td>
                            <td><input type="number" name="unit_price[]" class="form-control text-end price" value="0.00" step="0.01"></td>
                            <td><input type="number" name="total_price[]" class="form-control text-end row-total" value="0.00" readonly></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addRow">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มแถวรายการ
            </button>

            <div class="row justify-content-end">
                <div class="col-md-5 col-lg-4 total-section">
                    <div class="d-flex justify-content-between mb-2">
                        <span>รวมเป็นเงิน (Subtotal):</span>
                        <input type="number" id="subtotal" name="subtotal" class="text-end border-0 bg-transparent w-50" value="0.00" readonly>
                    </div>
                    <!-- <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>Service Charge (10%):</span>
                        <input type="number" id="service_charge" name="service_charge" class="text-end border-0 bg-transparent w-50" value="0.00" readonly>
                    </div> -->
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>VAT (7%):</span>
                        <input type="number" id="vat" name="vat" class="text-end border-0 bg-transparent w-50" value="0.00" readonly>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold text-primary fs-5">
                        <span>ยอดรวมสุทธิ:</span>
                        <input type="number" id="grand_total" name="grand_total" class="text-end border-0 bg-transparent fw-bold text-primary w-50" value="0.00" readonly>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm rounded-pill">
                    <i class="bi bi-save me-2"></i> บันทึกใบเสนอราคา
                </button>
                <a href="quotation_list.php" class="btn btn-light btn-lg px-5 rounded-pill ms-2">ยกเลิก</a>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // 1. ระบบค้นหาใน Dropdown
    $('.select2').select2({
        theme: "classic",
        width: '100%'
    });

    // 2. เพิ่มแถวรายการ
    $('#addRow').click(function() {
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

    // 3. ลบแถว
    $(document).on('click', '.removeRow', function() {
        $(this).closest('tr').remove();
        calculateAll();
        updateRowNumbers();
    });

    // 4. คำนวณรายบรรทัด
    $(document).on('input', '.qty, .price', function() {
        let row = $(this).closest('tr');
        let qty = parseFloat(row.find('.qty').val()) || 0;
        let price = parseFloat(row.find('.price').val()) || 0;
        row.find('.row-total').val((qty * price).toFixed(2));
        calculateAll();
    });

    // 5. คำนวณสรุปยอดท้ายบิล
    function calculateAll() {
        let subtotal = 0;
        $('.row-total').each(function() { 
            subtotal += parseFloat($(this).val()) || 0; 
        });
        
        let service = subtotal * 0;
        let vat = (subtotal + service) * 0.07;
        let grand = subtotal + service + vat;

        $('#subtotal').val(subtotal.toFixed(2));
        $('#service_charge').val(service.toFixed(2));
        $('#vat').val(vat.toFixed(2));
        $('#grand_total').val(grand.toFixed(2));
    }

    // 6. อัปเดตเลขลำดับ # เมื่อมีการลบแถว
    function updateRowNumbers() {
        $('#itemTable tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
});
</script>
</body>
</html>