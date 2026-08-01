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

// ดึงรายชื่อบริษัทสำหรับ Dropdown
$company_res = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");

// ดึงรายชื่อ Project
$projects_sql = "SELECT ep.id, ep.project_name,
                        (SELECT COUNT(*) FROM functions f WHERE f.project_id = ep.id) as has_eo,
                        (SELECT COUNT(*) FROM quotations q WHERE q.project_id = ep.id) as has_quotation
                 FROM event_projects ep
                 ORDER BY ep.project_name ASC";
$projects_res = $conn->query($projects_sql);

// ดึงเทมเพลตเมนูและเบรก
$menu_types_with_cat = $conn->query("SELECT mmt.id, mmt.type_name, mmt.category_id, mmc.category_name, mmc.set_price 
    FROM master_menu_types mmt 
    LEFT JOIN master_menu_categories mmc ON mmt.category_id = mmc.id 
    ORDER BY mmc.sort_order ASC, mmt.id ASC");
$menu_types_array = [];
while ($mt = $menu_types_with_cat->fetch_assoc()) {
    $menu_types_array[] = $mt;
}

$break_types_with_cat = $conn->query("SELECT id, type_name, break_price FROM master_break_types ORDER BY id ASC");
$break_types_array = [];
while ($bt = $break_types_with_cat->fetch_assoc()) {
    $break_types_array[] = $bt;
}
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

            <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">

            <!-- Row 1: Company, Quote No, Project -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1"><i class="bi bi-building me-1"></i> บริษัท/ธุรกิจ</label>
                    <select name="company_id" class="form-select form-select-sm" required>
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
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quote_no" class="form-control form-control-sm bg-light"
                        value="<?= htmlspecialchars($quote['quote_no']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small mb-1">อ้างอิงโครงการที่มีอยู่เดิม</label>
                    <select name="project_id" class="form-select form-select-sm select2">
                        <option value="">--- ไม่ระบุโครงการ ---</option>
                        <?php
                        if ($projects_res->num_rows > 0) {
                            $projects_res->data_seek(0);
                            while ($p = $projects_res->fetch_assoc()):
                                $selected = ($p['id'] == $quote['project_id']) ? "selected" : "";
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
                    <select name="customer_id" class="form-select form-select-sm select2-ajax-customer" required>
                        <?php if ($quote['customer_id']): 
                            $c_stmt = $conn->prepare("SELECT cust_name FROM customers WHERE id = ?");
                            $c_stmt->bind_param("i", $quote['customer_id']);
                            $c_stmt->execute();
                            $c_name = $c_stmt->get_result()->fetch_assoc()['cust_name'] ?? '--- เลือกรายชื่อลูกค้า ---';
                        ?>
                            <option value="<?= $quote['customer_id'] ?>" selected><?= htmlspecialchars($c_name) ?></option>
                        <?php else: ?>
                            <option value="">--- พิมพ์ชื่อลูกค้าเพื่อค้นหา ---</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Row 3: Event name (full width) -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label fw-bold small mb-1">ชื่อโครงการ/งาน</label>
                    <input type="text" name="event_name" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($quote['event_name']) ?>" placeholder="ระบุชื่องาน">
                </div>
            </div>

            <!-- Row 4: Event date, Expiry date -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold small mb-1">วันที่จัดงาน</label>
                    <input type="date" name="event_date" class="form-control form-control-sm" value="<?= $quote['event_date'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small mb-1">วันที่สิ้นสุด</label>
                    <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= $quote['expiry_date'] ?>">
                </div>
            </div>

            <!-- Row 5: Lead tracking fields -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1 text-warning"><i class="bi bi-tag me-1"></i> ที่มา Lead</label>
                    <select name="lead_source" class="form-select form-select-sm">
                        <option value="">-- เลือก --</option>
                        <option value="โทรเข้า" <?= ($quote['lead_source'] ?? '') === 'โทรเข้า' ? 'selected' : '' ?>>โทรเข้า</option>
                        <option value="FB / Social" <?= ($quote['lead_source'] ?? '') === 'FB / Social' ? 'selected' : '' ?>>FB / Social</option>
                        <option value="แนะนำ" <?= ($quote['lead_source'] ?? '') === 'แนะนำ' ? 'selected' : '' ?>>แนะนำ</option>
                        <option value="Walk-in" <?= ($quote['lead_source'] ?? '') === 'Walk-in' ? 'selected' : '' ?>>Walk-in</option>
                        <option value="อื่นๆ" <?= ($quote['lead_source'] ?? '') === 'อื่นๆ' ? 'selected' : '' ?>>อื่นๆ</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1 text-info"><i class="bi bi-graph-up me-1"></i> ผลการดำเนินงาน</label>
                    <select name="result" class="form-select form-select-sm">
                        <option value="">-- เลือก --</option>
                        <option value="ปิดงานสำเร็จ" <?= ($quote['result'] ?? '') === 'ปิดงานสำเร็จ' ? 'selected' : '' ?>>ปิดงานสำเร็จ</option>
                        <option value="ปิดงานไม่สำเร็จ" <?= ($quote['result'] ?? '') === 'ปิดงานไม่สำเร็จ' ? 'selected' : '' ?>>ปิดงานไม่สำเร็จ</option>
                        <option value="รอการตัดสินใจ" <?= ($quote['result'] ?? '') === 'รอการตัดสินใจ' ? 'selected' : '' ?>>รอการตัดสินใจ</option>
                        <option value="ติดต่อไม่ได้" <?= ($quote['result'] ?? '') === 'ติดต่อไม่ได้' ? 'selected' : '' ?>>ติดต่อไม่ได้</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1 text-success"><i class="bi bi-binoculars me-1"></i> Inspection</label>
                    <input type="date" name="inspection_date" class="form-control form-control-sm"
                        value="<?= $quote['inspection_date'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1 text-danger"><i class="bi bi-clock-history me-1"></i> Follow Up</label>
                    <input type="date" name="follow_up_date" class="form-control form-control-sm"
                        value="<?= $quote['follow_up_date'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1"><i class="bi bi-check2-square me-1"></i> Confirmed</label>
                    <input type="date" name="approved_at" class="form-control form-control-sm"
                        value="<?= preg_match('/^\d{4}-\d{2}-\d{2}$/', $quote['approved_at'] ?? '') ? $quote['approved_at'] : '' ?>">
                </div>
            </div>

            <hr>

            <!-- ====== เพิ่มรายการจากเทมเพลต ====== -->
            <div class="d-flex gap-2 mb-4">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openTemplateModal('template-menu','onMenuTemplateSelect')">
                    <i class="bi bi-grid-3x3-gap me-1"></i>เลือกเมนูอาหาร
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openTemplateModal('template-break','onBreakTemplateSelect')">
                    <i class="bi bi-grid-3x3-gap me-1"></i>เลือกเบรก
                </button>
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
                        <?php
                        $i = 1;
                        if ($items_res && $items_res->num_rows > 0):
                            while ($item = $items_res->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $i++ ?></td>
                                    <td>
                                        <textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical;"
                                            required><?= htmlspecialchars($item['item_name']) ?></textarea>
                                    </td>
                                    <td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty"
                                            value="<?= $item['quantity'] ?>" min="1"></td>
                                    <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price"
                                            value="<?= number_format($item['unit_price'], 2, '.', '') ?>" step="0.01"></td>
                                    <td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total"
                                            value="<?= number_format($item['total_price'], 2, '.', '') ?>" readonly></td>
                                    <td class="text-center">
                                        <i class="bi bi-trash text-danger removeRow" style="cursor:pointer; font-size: 1.2rem;"></i>
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

            <div class="row mt-4">
                <div class="col-md-7 col-lg-8">
                    <div class="card border-0 bg-light-subtle p-3 h-100">
                        <div class="mb-3">
                            <label class="form-label fw-bold small mb-1"><i class="bi bi-info-circle me-1"></i> หมายเหตุเพิ่มเติม (Remarks)</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="3"
                                placeholder="ระบุเงื่อนไขเพิ่มเติม..."><?= htmlspecialchars($quote['remarks']) ?></textarea>
                        </div>
                        <div>
                            <label class="form-label fw-bold small mb-1 text-danger"><i class="bi bi-x-circle me-1"></i> สาเหตุที่ปิดงานไม่ได้ (Lost Reason)</label>
                            <textarea name="lost_reason" class="form-control form-control-sm" rows="3"
                                placeholder="ระบุสาเหตุที่ลูกค้าไม่ตกลง..."><?= htmlspecialchars($quote['lost_reason'] ?? '') ?></textarea>
                            <div class="form-text text-muted small">* สำหรับบันทึกภายใน (ไม่แสดงในเอกสาร PDF)</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="card p-3 border shadow-sm bg-white mb-3">
                        <label class="form-label fw-bold small mb-1"><i class="bi bi-percent"></i> การคิดภาษี</label>
                        <?php $v_type = $quote['vat_type'] ?? (($quote['vat'] > 0) ? 'exclude' : 'no'); ?>
                        <select name="vat_type" id="vatType" class="form-select form-select-sm">
                            <option value="exclude" <?= ($v_type == 'exclude') ? 'selected' : '' ?>>แยกนอก (Exclude VAT)</option>
                            <option value="include" <?= ($v_type == 'include') ? 'selected' : '' ?>>รวมใน (Include VAT)</option>
                            <option value="no" <?= ($v_type == 'no') ? 'selected' : '' ?>>ไม่มี VAT</option>
                        </select>
                    </div>

                    <div class="card p-3 shadow-sm bg-white border">
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">รวมเป็นเงิน (Subtotal):</span>
                            <input type="number" id="subtotal" name="subtotal"
                                class="text-end border-0 bg-transparent fw-bold w-50"
                                value="<?= number_format($quote['subtotal'] ?? 0, 2, '.', '') ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between mb-2 small text-muted" id="vat-row">
                            <span>VAT (7%):</span>
                            <input type="number" id="vat" name="vat" class="text-end border-0 bg-transparent w-50"
                                value="<?= number_format($quote['vat'] ?? 0, 2, '.', '') ?>" readonly>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center fw-bold text-primary">
                            <span class="fs-6">ยอดรวมสุทธิ:</span>
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

<script>
    function escapeHtml(text) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    function autoGrowTextarea(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight) + 'px';
    }

    $(document).ready(function () {
        // 0. Auto-resize textarea ในคอลัมน์รายละเอียดรายการ
        $(document).on('input', '#itemTable textarea', function () {
            autoGrowTextarea(this);
        });
        $('#itemTable textarea').each(function () {
            autoGrowTextarea(this);
        });

        // 1. คำนวณยอดเริ่มต้นทันทีเมื่อโหลดหน้า (เพื่อให้สอดคล้องกับสถานะ Toggle จาก DB)
        calculateAll();

        // เมื่อมีการเปลี่ยนประเภท VAT
        $('#vatType').change(function() {
            calculateAll(); // สั่งคำนวณยอดใหม่ทันที
        });

        // เพิ่มแถวรายการใหม่
        $('#addRow').click(function () {
            let rowCount = $('#itemTable tbody tr').length + 1;
            let newRow = `<tr>
                <td class="text-center fw-bold">${rowCount}</td>
                <td><textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical;" required></textarea></td>
                <td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="1" min="1"></td>
                <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="0.00" step="0.01"></td>
                <td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="0.00" readonly></td>
                <td class="text-center">
                    <i class="bi bi-trash text-danger removeRow" style="cursor:pointer; font-size: 1.2rem;"></i>
                </td>
            </tr>`;
            $('#itemTable tbody').append(newRow);
            autoGrowTextarea($('#itemTable tbody tr:last textarea')[0]);
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
            let sumItems = 0;
            
            // วนลูปหาผลรวมของทุกแถว
            $('.row-total').each(function () {
                sumItems += parseFloat($(this).val()) || 0;
            });

            let vatType = $('#vatType').val();
            let subtotal = 0;
            let vat = 0;
            let grand = 0;

            if (vatType === 'exclude') {
                subtotal = sumItems;
                vat = subtotal * 0.07;
                grand = subtotal + vat;
            } else if (vatType === 'include') {
                grand = sumItems;
                subtotal = grand / 1.07;
                vat = grand - subtotal;
            } else {
                subtotal = sumItems;
                vat = 0;
                grand = subtotal;
            }

            // แสดงผลลงในช่อง Input ต่างๆ
            $('#subtotal').val(subtotal.toFixed(2));
            $('#vat').val(vat.toFixed(2));
            $('#grand_total').val(grand.toFixed(2));

            // เอฟเฟกต์จางลงเมื่อไม่มี VAT
            if (vatType === 'no') {
                $('#vat-row').addClass('opacity-50');
            } else {
                $('#vat-row').removeClass('opacity-50');
            }
        }

        // ฟังก์ชันเรียงเลขลำดับแถวใหม่ (ใช้ตอนลบแถว)
        function updateRowNumbers() {
            $('#itemTable tbody tr').each(function (index) {
                $(this).find('td:first').text(index + 1);
            });
        }

        // Modal callback: เลือกเมนูเสร็จ → เพิ่มแถวทันที (items = single, set = array)
        window.onMenuTemplateSelect = function(data) {
            function prefixed(typeName, name) {
                return typeName ? typeName + ':' + name : name;
            }
            function itemText(item) {
                return prefixed(item.type_name, item.name) + ' จำนวน ' + (item.qty || 1) + ' ราคา ' + (parseFloat(item.price) || 0) + ' บาท';
            }
            if (Array.isArray(data)) {
                var lines = [];
                if (data.category_name) lines.push(data.category_name);
                data.forEach(function(item) {
                    lines.push(item.name);
                });
                var notes = data.map(function(item) {
                    return item.note ? { name: item.name, note: item.note } : null;
                }).filter(Boolean);
                if (notes.length > 0) {
                    lines.push('');
                    lines.push('หมายเหตุ:');
                    notes.forEach(function(n) { lines.push('- ' + n.name + ': ' + n.note); });
                }
                var total = data.set_price !== undefined ? parseFloat(data.set_price) || 0 : data.reduce(function(s, item) { return s + (item.qty * item.price); }, 0);
                addTemplateRow(lines.join('\n'), data[0].qty || 1, total);
            } else {
                addTemplateRow(itemText(data), data.qty || 1, data.price);
            }
        };

        // Modal callback: เลือกเบรกเสร็จ → เพิ่มแถวทันที
        window.onBreakTemplateSelect = function(data) {
            if (Array.isArray(data) && data.set_price !== undefined) {
                var lines = [];
                data.forEach(function(item) {
                    lines.push((item.type_name || data.category_name || '') + ' :' + item.name);
                });
                var notes = data.map(function(item) {
                    return item.note ? { name: item.name, note: item.note } : null;
                }).filter(Boolean);
                if (notes.length > 0) {
                    lines.push('');
                    lines.push('หมายเหตุ:');
                    notes.forEach(function(n) { lines.push('- ' + n.name + ': ' + n.note); });
                }
                var setTotal = parseFloat(data.set_price) || 0;
                addTemplateRow(lines.join('\n'), data[0].qty || 1, setTotal);
            } else if (Array.isArray(data)) {
                data.forEach(item => addTemplateRow(item.name, item.qty || 1, item.price));
            } else {
                addTemplateRow(data.name, data.qty || 1, data.price);
            }
        };

        // ฟังก์ชันเพิ่มแถวจากเทมเพลต (ล็อกไม่ให้แก้ไข)
        function addTemplateRow(name, qty, price, explicitTotal) {
            var total = (explicitTotal !== undefined && explicitTotal !== null) ? Number(explicitTotal).toFixed(2) : (qty * price).toFixed(2);
            var rowCount = $('#itemTable tbody tr').length + 1;
            var row = '<tr class="template-row">'
                + '<td class="text-center fw-bold">' + rowCount + '</td>'
                + '<td><textarea name="item_name[]" class="form-control form-control-sm" rows="2" style="resize: vertical;" readonly>' + escapeHtml(name) + '</textarea></td>'
                + '<td><input type="number" name="quantity[]" class="form-control form-control-sm text-center qty" value="' + qty + '" min="1" readonly></td>'
                + '<td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end price" value="' + price.toFixed(2) + '" step="0.01" readonly></td>'
                + '<td><input type="number" name="total_price[]" class="form-control form-control-sm text-end row-total" value="' + total + '" readonly></td>'
                + '<td class="text-center"><i class="bi bi-lock-fill text-secondary me-1" title="รายการจากเทมเพลต - แก้ไขไม่ได้"></i><i class="bi bi-trash text-danger removeRow" style="cursor:pointer; font-size: 1.2rem;"></i></td>'
                + '</tr>';
            $('#itemTable tbody').append(row);
            autoGrowTextarea($('#itemTable tbody tr:last textarea')[0]);
            calculateAll();
        }
    });
</script>

<style>
    .select2-container--default .select2-selection--single { height: 31px !important; line-height: 31px !important; border: 1px solid #ced4da !important; font-size: 0.875rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px !important; padding-left: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px !important; }
    .select2-container .badge { vertical-align: middle; margin-left: 2px; }
    .select2-results__option .badge { font-size: 0.65rem !important; }
    .select2-selection__rendered .badge { font-size: 0.6rem !important; }
    .table-items th, .table-items td { white-space: nowrap; }
    .table-items textarea { min-width: 180px; }
</style>

<?php include "includes/menu_type_modal.php"; ?>
<?php include "footer.php"; ?>