<?php
include "config.php";
$id = intval($_GET['id'] ?? 0);

// 1. ดึงข้อมูลงานหลัก
$sql = "SELECT * FROM functions WHERE id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();

if (!$data) {
    die("ไม่พบข้อมูลงานนี้");
}

// 2. ดึงรายการบัญชี
$sql_fin = "SELECT * FROM function_finance WHERE function_id = $id ORDER BY transaction_date ASC, id ASC";
// 2. ดึงรายการบัญชี (ค่าใช้จ่ายอื่นๆ ที่ไม่ใช่ค่าอาหารที่ดึงออโต้)
$res_fin = $conn->query($sql_fin);
$finances = [];
$total_income = 0;
$extra_cost = 0; // เปลี่ยนชื่อตัวแปรให้ชัดเจนว่าเป็นค่าใช้จ่ายอื่นๆ

while ($f = $res_fin->fetch_assoc()) {
    if ($f['type'] == 'income') {
        $total_income += $f['amount'];
    } else {
        $extra_cost += $f['amount']; // เก็บยอดที่คีย์เองแยกไว้
    }
    $finances[] = $f;
}

// 2.5 ดึงข้อมูลต้นทุนจากครัว (Auto)
$kitchen_data = getKitchenCost($conn, $id);
$kitchen_total = $kitchen_data['total'];

// ป้องกัน Error กรณีคอลัมน์ชื่อไม่ตรง หรือไม่มีข้อมูล
$main_price = (float) ($data['total_amount'] ?? 0);

// 🎯 คำนวณรายรับทั้งหมด (ราคาขายหลัก + รายรับเสริมที่คีย์เพิ่ม)
$grand_total_income = $main_price + $total_income;

// 🎯 ต้นทุนรวม (จากครัวอัตโนมัติ + รายจ่ายที่คีย์เพิ่มเอง)
$total_cost = $extra_cost + $kitchen_total;

// 🎯 กำไรสุทธิ (รายรับทั้งหมด - ต้นทุนทั้งหมด)
$profit = $grand_total_income - $total_cost;

// ROI (%)
$roi = ($total_cost > 0) ? ($profit / $total_cost) * 100 : 0;

// 3. ส่วนสำหรับ AJAX Refresh (จะแสดงผลเฉพาะส่วนนี้เมื่อเรียกผ่าน fetch)
if (isset($_GET['ajax'])) {
    ?>
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ราคาขายงาน</small>
                <h4 class="text-primary mb-0"><?= number_format($data['total_amount'], 2) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ต้นทุนรวม</small>
                <h4 class="text-danger mb-0"><?= number_format($total_cost, 2) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">กำไรสุทธิ</small>
                <h4 class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0"><?= number_format($profit, 2) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ROI (%)</small>
                <h4 class="mb-0"><?= number_format($roi, 2) ?>%</h4>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="financeTableContent">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>รายการ</th>
                        <th class="text-end">รายรับ</th>
                        <th class="text-end">รายจ่าย</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($finances)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($finances as $f): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y', strtotime($f['transaction_date'])) ?></td>
                                <td><?= htmlspecialchars($f['detail']) ?></td>
                                <td class="text-end text-success">
                                    <?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?>
                                </td>
                                <td class="text-end text-danger"><?= $f['type'] == 'cost' ? number_format($f['amount'], 2) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 btn-delete-finance"
                                        data-id="<?= $f['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    exit; // จบการทำงานสำหรับ AJAX request
}
function getKitchenCost($conn, $function_id)
{
    $total_cost = 0;

    // --- ส่วนที่ 1: คำนวณจากเมนูหลัก (function_menus) ---
    $sql_m = "SELECT menu_qty, menu_price, menu_detail FROM function_menus WHERE function_id = $function_id";
    $res_m = $conn->query($sql_m);

    while ($m = $res_m->fetch_assoc()) {
        $qty = (float) $m['menu_qty'];
        $price_direct = (float) $m['menu_price'];

        if ($price_direct > 0) {
            // ถ้าระบุราคาเหมา/ราคาต่อเซตไว้แล้ว ใช้เจ้านี้คูณเลย
            $total_cost += ($price_direct * $qty);
        } else {
            // ถ้าราคาเป็น 0 ให้แตกชื่อเมนูไปส่องหาในตาราง Details
            $lines = explode("\n", str_replace("\r", "", $m['menu_detail']));
            foreach ($lines as $line) {
                $name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $line));
                if (empty($name))
                    continue;

                $name_esc = $conn->real_escape_string($name);
                $q_p = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                if ($p = $q_p->fetch_assoc()) {
                    $total_cost += ((float) $p['price_per_pax'] * $qty);
                }
            }
        }
    }

    // --- ส่วนที่ 2: คำนวณจากครัว/เบรก (function_kitchens) ---
    $sql_k = "SELECT k_item, k_qty FROM function_kitchens WHERE function_id = $function_id";
    $res_k = $conn->query($sql_k);

    while ($k = $res_k->fetch_assoc()) {
        $k_qty = (float) $k['k_qty'];
        $k_lines = explode("\n", str_replace("\r", "", $k['k_item']));

        foreach ($k_lines as $line) {
            $k_name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $line));
            if (empty($k_name))
                continue;

            $k_name_esc = $conn->real_escape_string($k_name);
            $unit_price = 0;

            // 🔍 หาในตารางเบรกก่อน
            $q_b = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$k_name_esc%' LIMIT 1");
            if ($b = $q_b->fetch_assoc()) {
                $unit_price = (float) $b['break_price'];
            }
            // 🔍 ถ้าไม่เจอ หาในตาราง Details เผื่อเป็นกับข้าวสั่งเพิ่ม
            else {
                $q_d = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$k_name_esc%' LIMIT 1");
                if ($d = $q_d->fetch_assoc()) {
                    $unit_price = (float) $d['price_per_pax'];
                }
            }
            $total_cost += ($unit_price * $k_qty);
        }
    }

    return ['total' => $total_cost];
}


// 4. ส่วนหน้าจอปกติ (เรียก Header)
include "header.php";
?>
<style>
    @media print {

        /* 1. ซ่อนทุกอย่างที่ไม่ใช่โซนตารางสรุป */
        body * {
            visibility: hidden;
        }

        #financeTableContainer,
        #financeTableContainer * {
            visibility: visible;
        }

        /* 2. จัดตำแหน่งโซนที่จะปริ้นให้ชิดขอบบนสุด */
        #financeTableContainer {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* 3. ซ่อนปุ่มลบ (ถังขยะ) และปุ่มกดย้อนกลับ ไม่ให้ติดไปในกระดาษ */
        .btn-delete-finance,
        .no-print,
        .btn,
        i.bi-trash {
            display: none !important;
        }

        /* 4. ปรับตารางให้มีเส้นขอบชัดเจนในกระดาษ */
        .table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        .table th,
        .table td {
            border: 1px solid #dee2e6 !important;
            padding: 8px !important;
            color: #000 !important;
        }

        /* 5. ปรับส่วนสรุปด้านล่างให้ดูสะอาดตา */
        .card {
            border: none !important;
        }

        .border-bottom {
            border-bottom: 1px solid #000 !important;
        }

        .text-primary,
        .text-danger,
        .text-success {
            color: #000 !important;
        }

        /* ปริ้นขาวดำจะได้ชัด */
    }
</style>
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 ">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-cash-coin text-primary"></i> บัญชีงาน: <?= htmlspecialchars($data['function_name']) ?>
            </h4>
            <small class="text-muted">จัดการรายรับ-รายจ่าย และสรุปผลกำไรสุทธิ</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" onclick="window.print();" class="btn btn-dark btn-sm">
                <i class="bi bi-printer"></i> พิมพ์รายงาน
            </button>
        </div>
    </div>

    <div id="summaryWrapper">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ราคาขายงาน</small>
                    <h4 class="text-primary mb-0"><?= number_format($data['total_amount'], 2) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ต้นทุนรวม</small>
                    <h4 class="text-danger mb-0"><?= number_format($total_cost, 2) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">กำไรสุทธิ</small>
                    <h4 class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                        <?= number_format($profit, 2) ?>
                    </h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ROI (%)</small>
                    <h4 class="mb-0"><?= number_format($roi, 2) ?>%</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3 text-warning"><i class="bi bi-plus-circle"></i> บันทึกรายการ</h5>
                    <form id="financeForm">
                        <input type="hidden" name="function_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภท</label>
                            <select name="type" class="form-select" required>
                                <option value="income">รายรับ (เงินมัดจำ/ยอดรับจริง)</option>
                                <option value="cost">รายจ่าย (ต้นทุนงาน)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">รายละเอียด</label>
                            <input type="text" name="detail" class="form-control"
                                placeholder="เช่น ค่าอาหาร, มัดจำงวดที่ 1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">จำนวนเงิน</label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">วันที่รายการ</label>
                            <input type="date" name="transaction_date" class="form-control"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <?php
                            // ดึงสิทธิ์มาเช็ค (แนะนำให้ใส่ไว้บรรทัดบนสุดของไฟล์ครั้งเดียวพอครับ)
                            $user_role = strtolower($_SESSION['role'] ?? 'viewer');

                            // ✅ ถ้า "ไม่ใช่" viewer (แปลว่าเป็น admin, staff หรือสิทธิ์อื่นๆ) ให้โชว์ปุ่มบันทึก
                            if ($user_role !== 'viewer'):
                                ?>
                                <button type="submit" class="btn btn-warning w-100 fw-bold">
                                    <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary w-100 fw-bold disabled"
                                    style="cursor: not-allowed;">
                                    <i class="bi bi-lock-fill me-1"></i> โหมดอ่านอย่างเดียว
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8" id="financeTableContainer">
            <div class="d-none d-print-block mb-3 border-bottom pb-2">
                <h5 class="mb-1 fw-bold text-dark">
                    รายงานสรุปบัญชี ID: #<?= $data['id'] ?>
                </h5>
                <div class="d-flex justify-content-between">
                    <span>งาน: <?= htmlspecialchars($data['function_name']) ?></span>
                    <span>วันที่พิมพ์: <?= date('d/m/Y') ?></span>
                </div>
            </div>
            <div class="mb-4 ">
                <?php include 'calculate_costs.php'; ?>
            </div>
            <div class="card border-0 shadow-sm">

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่</th>
                                <th>รายการ</th>
                                <th class="text-end">รายรับ</th>
                                <th class="text-end">รายจ่าย</th>
                                <th class="text-center d-print-none">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($finances)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($finances as $f): ?>
                                    <tr>
                                        <td class="small"><?= date('d/m/Y', strtotime($f['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($f['detail']) ?></td>
                                        <td class="text-end text-success">
                                            <?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end text-danger">
                                            <?= $f['type'] == 'cost' ? number_format($f['amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-center d-print-none">
                                            <?php if (strtolower($_SESSION['role'] ?? 'viewer') !== 'viewer'): ?>
                                                <button type="button" class="btn btn-link text-danger p-0 btn-delete-finance"
                                                    data-id="<?= $f['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <i class="bi bi-lock text-muted" title="อ่านอย่างเดียว"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row g-2 mt-2" id="summaryPrintZone">


                <div class="d-none d-print-block d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">ผลตอบแทน (ROI):</span>
                        <span class="fw-bold text-dark"><?= number_format($roi, 2) ?>%</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-1">
                        <span class="text-muted small">รวมรายรับทั้งหมด:</span>
                        <span class="fw-bold text-primary"><?= number_format($main_price + $total_income, 2) ?>
                            บาท</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-1">
                        <span class="text-muted small">ต้นทุนรวมทั้งงาน:</span>
                        <span class="fw-bold text-danger"><?= number_format($total_cost, 2) ?> บาท</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-1">
                        <span class="text-muted small">กำไรสุทธิ:</span>
                        <span class="fw-bold text-success"><?= number_format($profit, 2) ?> บาท</span>
                    </div>

                </div>
            </div>
        </div>

    </div>
    <script>
        function exportToWord(elementId) {
            // 1. ดึงเนื้อหา HTML จาก id ที่ระบุ
            var content = document.getElementById(elementId).innerHTML;

            // 2. จัดรูปแบบสำหรับ Word (ใส่ Style พื้นฐานเพื่อให้ตารางมีเส้นตอนเปิดใน Word)
            var style = `
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid black; padding: 5px; text-align: left; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .fw-bold { font-weight: bold; }
            .text-primary { color: #0d6efd; }
            .text-danger { color: #dc3545; }
            .text-success { color: #198754; }
        </style>
    `;

            var header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' " +
                "xmlns:w='urn:schemas-microsoft-com:office:word' " +
                "xmlns='http://www.w3.org/TR/REC-html40'>" +
                "<head><meta charset='utf-8'>" + style + "</head><body>";
            var footer = "</body></html>";

            var sourceHTML = header + content + footer;

            // 3. สร้าง Blob object (สำคัญมาก: ช่วยให้เบราว์เซอร์มองว่าเป็นไฟล์จริงๆ)
            var blob = new Blob(['\ufeff', sourceHTML], {
                type: 'application/msword'
            });

            // 4. สร้าง Link สำหรับดาวน์โหลด
            var url = URL.createObjectURL(blob);
            var link = document.createElement("a");
            link.href = url;

            // ตั้งชื่อไฟล์ (เอาชื่อชื่องานมาตั้งเป็นชื่อไฟล์)
            link.download = 'สรุปบัญชี_<?= addslashes($data['function_name']) ?>.doc';

            document.body.appendChild(link);
            link.click();

            // 5. ลบ Link ทิ้งหลังดาวน์โหลดเสร็จ
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ฟังก์ชันโหลดข้อมูลใหม่แบบ AJAX
        function refreshFinanceData() {
            fetch(`finance.php?id=<?= $id ?>&ajax=1`)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // อัปเดตตาราง
                    const tableContent = doc.querySelector('#financeTableContent').innerHTML;
                    document.querySelector('#financeTableContainer .card').innerHTML = tableContent;

                    // อัปเดต Card ยอดเงินด้านบน
                    const summaryContent = doc.querySelector('#summaryCards').innerHTML;
                    document.querySelector('#summaryWrapper').innerHTML = `<div class="row g-3 mb-4">${summaryContent}</div>`;
                });
        }

        // การบันทึกข้อมูล
        document.getElementById('financeForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/finance_handler.php?action=save', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.reset();
                        this.querySelector('[name="transaction_date"]').value = '<?= date('Y-m-d') ?>';
                        refreshFinanceData();
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                });
        });

        // การลบข้อมูล
        document.addEventListener('click', function (e) {
            if (e.target.closest('.btn-delete-finance')) {
                const btn = e.target.closest('.btn-delete-finance');
                const id = btn.dataset.id;

                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "ข้อมูลนี้จะหายไปจากบัญชีของงานนี้",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'ลบรายการ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('id', id);
                        fetch('api/finance_handler.php?action=delete', {
                            method: 'POST',
                            body: fd
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    refreshFinanceData();
                                }
                            });
                    }
                });
            }
        });
    </script>

    <?php include "footer.php"; ?>