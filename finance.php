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
$res_fin = $conn->query($sql_fin);
$finances = [];
$total_income = 0;
$extra_cost = 0;

// แยกยอดก่อน/หลังอนุมัติ
$post_income = 0;
$post_cost = 0;

while ($f = $res_fin->fetch_assoc()) {
    if ($f['is_post_approval']) {
        if ($f['type'] == 'income') {
            $post_income += $f['amount'];
        } else {
            $post_cost += $f['amount'];
        }
    }
    if ($f['type'] == 'income') {
        $total_income += $f['amount'];
    } else {
        $extra_cost += $f['amount'];
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

// 🎯 ค่าบริหาร 3% ของรายรับทั้งหมด
$management_fee = $grand_total_income * 0.03;

// 🎯 กำไรสุทธิ (รายรับทั้งหมด - ต้นทุนทั้งหมด - ค่าบริหาร)
$profit = $grand_total_income - $total_cost - $management_fee;

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
                <?php if ($post_cost > 0): ?>
                    <small class="text-warning">(หลังอนุมัติ <?= number_format($post_cost, 2) ?>)</small>
                <?php endif; ?>
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
        <?php if ($post_income > 0 || $post_cost > 0): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-3 bg-light">
                <small class="text-muted fw-bold"><i class="bi bi-clock-history me-1"></i> รายการหลังอนุมัติ</small>
                <div class="d-flex gap-4 mt-1">
                    <span>รายรับหลังอนุมัติ: <strong class="text-success"><?= number_format($post_income, 2) ?></strong></span>
                    <span>รายจ่ายหลังอนุมัติ: <strong class="text-danger"><?= number_format($post_cost, 2) ?></strong></span>
                    <?php $post_profit = $post_income - $post_cost; ?>
                    <span>ผลต่างหลังอนุมัติ: <strong class="<?= $post_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($post_profit, 2) ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                        <th>ผู้บันทึก</th>
                        <th>สิทธิ์</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($finances)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
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
                                <td class="small text-muted"><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($f['created_by_role'] ?? '-') ?></span>
                                    <?php if ($f['is_post_approval']): ?>
                                        <span class="badge bg-warning text-dark">หลังอนุมัติ</span>
                                    <?php endif; ?>
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
    $total_cost_price = 0;

    // --- ส่วนที่ 1: คำนวณจากเมนูหลัก (function_menus) ---
    $sql_m = "SELECT menu_qty, menu_price, menu_cost, menu_detail FROM function_menus WHERE function_id = $function_id";
    $res_m = $conn->query($sql_m);

    while ($m = $res_m->fetch_assoc()) {
        $qty = (float) $m['menu_qty'];
        $price_direct = (float) $m['menu_price'];
        $cost_direct = (float) ($m['menu_cost'] ?? 0);

        if ($cost_direct > 0) {
            $total_cost += ($cost_direct * $qty);
        } elseif ($price_direct > 0) {
            $total_cost += ($price_direct * $qty);
        } else {
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

        if ($cost_direct > 0) {
            $total_cost_price += ($cost_direct * $qty);
        } elseif ($price_direct > 0) {
            $total_cost_price += ($price_direct * $qty);
        }
    }

    // --- ส่วนที่ 2: คำนวณจากครัว/เบรก (function_kitchens) ---
    $sql_k = "SELECT k_item, k_qty, k_price, k_cost FROM function_kitchens WHERE function_id = $function_id";
    $res_k = $conn->query($sql_k);

    while ($k = $res_k->fetch_assoc()) {
        $k_qty = (float) $k['k_qty'];
        $k_price = (float) ($k['k_price'] ?? 0);
        $k_cost = (float) ($k['k_cost'] ?? 0);

        if ($k_cost > 0) {
            $total_cost += ($k_cost * $k_qty);
        } elseif ($k_price > 0) {
            $total_cost += ($k_price * $k_qty);
        } else {
            $k_lines = explode("\n", str_replace("\r", "", $k['k_item']));
            foreach ($k_lines as $line) {
                $k_name = trim(preg_replace('/^(\d+\.|\-)\s*/', '', $line));
                if (empty($k_name))
                    continue;

                $k_name_esc = $conn->real_escape_string($k_name);
                $unit_price = 0;
                $q_b = $conn->query("SELECT break_price FROM function_breaks WHERE break_menu LIKE '%$k_name_esc%' LIMIT 1");
                if ($b = $q_b->fetch_assoc()) {
                    $unit_price = (float) $b['break_price'];
                } else {
                    $q_d = $conn->query("SELECT price_per_pax FROM function_menu_details WHERE menu_items LIKE '%$k_name_esc%' LIMIT 1");
                    if ($d = $q_d->fetch_assoc()) {
                        $unit_price = (float) $d['price_per_pax'];
                    }
                }
                $total_cost += ($unit_price * $k_qty);
            }
        }

        if ($k_cost > 0) {
            $total_cost_price += ($k_cost * $k_qty);
        } elseif ($k_price > 0) {
            $total_cost_price += ($k_price * $k_qty);
        }
    }

    return ['total' => $total_cost, 'total_cost_price' => $total_cost_price];
}


// 4. ส่วนหน้าจอปกติ (เรียก Header)
include "header.php";
?>
<style>
    /* ── Excel-like Print Style ── */
    @media print {
        body * { visibility: hidden; }
        #financeTableContainer, #financeTableContainer * { visibility: visible; }
        #financeTableContainer {
            position: absolute; left: 0; top: 0; width: 100%;
            margin: 0 !important; padding: 10px !important;
        }
        .btn, .btn-delete-finance, .no-print, i.bi-trash,
        #financeForm, .col-md-4:first-child, .d-print-none { display: none !important; }

        .table { border-collapse: collapse !important; width: 100% !important; font-size: 11px !important; font-family: Consolas, 'Courier New', monospace !important; }
        .table th, .table td {
            border: 1px solid #000 !important;
            padding: 6px 6px !important;
            color: #000 !important;
            background: #fff !important;
        }
        .table th { background: #e0e0e0 !important; font-weight: bold !important; text-align: center !important; }
        .table td.text-end { text-align: right !important; }
        .text-success, .text-danger, .text-primary, .text-warning { color: #000 !important; }
        .badge { background: transparent !important; color: #000 !important; border: 1px solid #000 !important; }
        .card { border: 1px solid #000 !important; box-shadow: none !important; }
        .bg-light { background: #f5f5f5 !important; }
        .fw-bold { font-weight: bold !important; }
        h4, h5, h6 { margin: 4px 0; }

        /* ต้นทุนรวม card - ซ่อนย่อยเฉพาะตอน print */
        #summaryWrapper .row.g-3.mb-4 { page-break-after: avoid; }
    }

    .excel-table {
        font-family: Consolas, 'Courier New', monospace;
        font-size: 13px;
        border-collapse: collapse;
        width: 100%;
    }
    .excel-table th, .excel-table td {
        border: 1px solid #999;
        padding: 4px 6px;
    }
    .excel-table th {
        background: #4472C4;
        color: #fff;
        font-weight: bold;
        text-align: center;
    }
    .excel-table .row-even { background: #f2f2f2; }
    .excel-table .row-total { background: #DAEEF3; font-weight: bold; }
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
            <button type="button" onclick="exportExcel()" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
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
                    <?php if ($post_cost > 0): ?>
                        <small class="text-warning">(หลังอนุมัติ <?= number_format($post_cost, 2) ?>)</small>
                    <?php endif; ?>
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
        <?php if ($post_income > 0 || $post_cost > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm p-3 bg-light">
                    <small class="text-muted fw-bold"><i class="bi bi-clock-history me-1"></i> รายการหลังอนุมัติ</small>
                    <div class="d-flex gap-4 mt-1">
                        <span>รายรับหลังอนุมัติ: <strong class="text-success"><?= number_format($post_income, 2) ?></strong></span>
                        <span>รายจ่ายหลังอนุมัติ: <strong class="text-danger"><?= number_format($post_cost, 2) ?></strong></span>
                        <?php $post_profit = $post_income - $post_cost; ?>
                        <span>ผลต่างหลังอนุมัติ: <strong class="<?= $post_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($post_profit, 2) ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3 text-warning"><i class="bi bi-plus-circle"></i> บันทึกรายการ</h5>
                    
                    <!-- แสดงข้อมูลผู้บันทึก -->
                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-person-fill"></i> ผู้บันทึก: <b><?= htmlspecialchars($_SESSION['user_name'] ?? 'ไม่ระบุ') ?></b> 
                        <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['role'] ?? 'viewer') ?></span>
                    </div>

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
                            <label class="form-label small fw-bold">ช่องทางการชำระเงิน</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">เงินสด</option>
                                <option value="Bank Transfer">โอนเงินผ่านธนาคาร</option>
                                <option value="Credit Card">บัตรเครดิต</option>
                                <option value="Other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">วันที่รายการ</label>
                            <input type="date" name="transaction_date" class="form-control"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-warning w-100 fw-bold">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                            </button>
                            <?php if ($data['approve'] == 1): ?>
                                <div class="alert alert-warning text-center small py-2 mt-2">
                                    <i class="bi bi-exclamation-triangle"></i> งานนี้ผ่านการอนุมัติแล้ว รายการที่บันทึกจะถูกทำเครื่องหมายเป็น "หลังอนุมัติ"
                                </div>
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
                                <th>ช่องทาง</th>
                                <th>สิทธิ์</th>
                                <th class="text-center d-print-none">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($finances)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
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
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($f['payment_method'] ?? '-') ?></span></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($f['created_by_role'] ?? '-') ?></span>
                                            <?php if ($f['is_post_approval']): ?>
                                                <span class="badge bg-warning text-dark">หลังอนุมัติ</span>
                                            <?php endif; ?>
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
                        <span class="text-muted small">ค่าบริหาร 3%:</span>
                        <span class="fw-bold text-dark"><?= number_format($management_fee, 2) ?> บาท</span>
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
        // Export to Excel
        function exportExcel() {
            const id = <?= $id ?>;
            window.location.href = 'api/export_finance_excel.php?id=' + id;
        }
    </script>
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