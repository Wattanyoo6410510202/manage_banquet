<?php
include "config.php";
include_once __DIR__ . '/includes/quote_cost.php';
include_once __DIR__ . '/includes/finance_stage.php';
include_once __DIR__ . '/includes/menu_cost_lookup.php';
$id = intval($_GET['id'] ?? 0);
$quote_id = intval($_GET['quote_id'] ?? 0);

// โหมดใบเสนอราคา: งานที่ยังไม่ถูกแปลงเป็น EO ยังไม่มีแถวใน functions
// จึงผูกรายการบัญชีไว้กับ quotations ไปก่อน แล้วค่อยโอนเข้า EO ตอนแปลง
$is_quote = ($quote_id > 0 && $id === 0);
$eo_of_quote = null;
$quote_locked = false;

if ($is_quote) {
    // 1. ดึงข้อมูลใบเสนอราคา + ข้อมูลลูกค้า/บริษัท
    $sql = "SELECT q.*, c.company_name, cust.cust_name, cust.cust_tax_id, cust.cust_phone, cust.cust_email,
                   u.name as creator_name
            FROM quotations q
            LEFT JOIN companies c ON q.company_id = c.id
            LEFT JOIN customers cust ON q.customer_id = cust.id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.id = $quote_id";
    $res = $conn->query($sql);
    $data = $res->fetch_assoc();

    if (!$data) {
        die("ไม่พบใบเสนอราคานี้");
    }

    // ใบเสนอราคานี้ถูกแปลงเป็น EO ไปแล้วหรือยัง (ถ้าแปลงแล้วให้ไปคีย์ต่อที่ EO)
    $res_eo = $conn->query("SELECT id, function_name FROM functions
                            WHERE quotation_id = $quote_id AND status != 'Cancelled'
                            ORDER BY id ASC LIMIT 1");
    $eo_of_quote = $res_eo ? $res_eo->fetch_assoc() : null;

    // อนุมัติใบเสนอราคาแล้ว = ล็อกรายการบัญชี แก้ไข/ลบ/เพิ่มไม่ได้
    // (ใบที่แปลงเป็น EO แล้วก็ล็อกด้วย รายการใหม่ต้องไปคีย์ที่ EO)
    $quote_locked = (($data['status'] ?? '') === 'Approved') || !empty($eo_of_quote);

    // แปลงชื่อฟิลด์ให้ตรงกับฝั่ง EO เพื่อให้ส่วนที่เหลือของหน้าใช้โค้ดร่วมกันได้
    $data['created_by_id']      = intval($data['created_by'] ?? 0);
    $data['created_by']         = $data['creator_name'] ?? '';
    $data['function_name']      = $data['event_name'] ?? '';
    $data['function_code']      = $data['quote_no'] ?? '';
    $data['type_prefix']        = '';
    $data['booking_name']       = $data['cust_name'] ?? '';
    $data['phone']              = $data['cust_phone'] ?? '';
    $data['function_type_name'] = '';
    $data['room_name']          = '';
    $data['pax']                = 0;
    $data['total_amount']       = $data['grand_total'] ?? 0;
    $data['start_time']         = !empty($data['event_date']) ? $data['event_date'] . ' 00:00:00' : null;
    // ใบเสนอราคาไม่มีสถานะอนุมัติงาน รายการที่คีย์จึงนับเป็นก่อนอนุมัติทั้งหมด
    $data['approve']            = 0;
} else {
    // 1. ดึงข้อมูลงานหลัก + ข้อมูลลูกค้า/บริษัท
    $sql = "SELECT f.*, c.company_name, cust.cust_name, cust.cust_tax_id, cust.cust_phone, cust.cust_email,
                   ft.type_name as function_type_name, r.room_name
            FROM functions f
            LEFT JOIN companies c ON f.company_id = c.id
            LEFT JOIN customers cust ON f.customer_id = cust.id
            LEFT JOIN function_types ft ON f.function_type_id = ft.id
            LEFT JOIN meeting_rooms r ON f.room_id = r.id
            WHERE f.id = $id";
    $res = $conn->query($sql);
    $data = $res->fetch_assoc();

    if (!$data) {
        die("ไม่พบข้อมูลงานนี้");
    }
}

// URL ของหน้านี้ (ใช้ต่อกับ AJAX refresh และปุ่มต่างๆ)
$page_key = $is_quote ? "quote_id=$quote_id" : "id=$id";
// ใบเสนอราคาเก็บแค่วันจัดงาน ไม่มีเวลาเริ่มงาน
$event_date_txt = !empty($data['start_time']) ? date('d/m/Y', strtotime($data['start_time'])) : '';
$event_time_txt = (!$is_quote && !empty($data['start_time'])) ? date('H:i', strtotime($data['start_time'])) : '';

// 1.1 ดึงลายเซ็นผู้สร้างงาน
$creator_id = intval($data['created_by_id'] ?? 0);
$creator_sig = '';
$creator_name = $data['created_by'] ?? '';
if ($creator_id > 0) {
    $stmt_sig = $conn->prepare("SELECT path FROM signatures WHERE users_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_sig->bind_param("i", $creator_id);
    $stmt_sig->execute();
    $res_sig = $stmt_sig->get_result();
    if ($row_sig = $res_sig->fetch_assoc()) {
        $creator_sig = $row_sig['path'];
        if (strpos($creator_sig, 'uploads/') === false) {
            $creator_sig = 'uploads/signatures/' . $creator_sig;
        }
    }
}

// 2. ดึงรายการบัญชี
$sql_fin = $is_quote
    ? "SELECT * FROM function_finance WHERE quotation_id = $quote_id ORDER BY transaction_date ASC, id ASC"
    : "SELECT * FROM function_finance WHERE function_id = $id ORDER BY transaction_date ASC, id ASC";
$res_fin = $conn->query($sql_fin);
$finances = [];
$total_income = 0;
$total_deposit = 0;
$extra_cost = 0;

// แยกยอดก่อน/หลังอนุมัติ
$post_income = 0;
$post_cost = 0;

// ยอดที่คีย์ตั้งแต่ขั้นใบเสนอราคา (นับรวมอยู่ในฝั่งก่อนอนุมัติ แยกมาโชว์ให้เห็น)
$quote_income = 0;
$quote_cost = 0;

while ($f = $res_fin->fetch_assoc()) {
    if (financeStage($f) === 'quote') {
        if ($f['type'] == 'income' || $f['type'] == 'deposit') {
            $quote_income += $f['amount'];
        } else {
            $quote_cost += $f['amount'];
        }
    }
    if ($f['is_post_approval']) {
        if ($f['type'] == 'income' || $f['type'] == 'deposit') {
            $post_income += $f['amount'];
        } else {
            $post_cost += $f['amount'];
        }
    }
    if ($f['type'] == 'income' || $f['type'] == 'deposit') {
        $total_income += $f['amount'];
    }
    if ($f['type'] == 'deposit') {
        $total_deposit += $f['amount'];
    }
    if ($f['type'] == 'cost') {
        $extra_cost += $f['amount'];
    }
    $finances[] = $f;
}

// 2.5 ดึงข้อมูลต้นทุนอาหาร (Auto)
if ($is_quote) {
    // ขั้นใบเสนอราคา: รายการมาจาก quotation_items ราคาทุนล้วงตามชื่อเมนู
    $quote_cost_detail = getQuoteCostDetailed($conn, $quote_id);
    $kitchen_total = $quote_cost_detail['sum_main_cost'] + $quote_cost_detail['sum_break_cost'];
} else {
    $kitchen_data = getKitchenCost($conn, $id);
    $kitchen_total = $kitchen_data['total'];
}

// ป้องกัน Error กรณีคอลัมน์ชื่อไม่ตรง หรือไม่มีข้อมูล
$main_price = (float) ($data['total_amount'] ?? 0);

// 🎯 คำนวณรายรับทั้งหมด (ราคาขายหลัก + รายรับเสริมที่คีย์เพิ่ม)
$grand_total_income = $main_price + $total_income;

// 🎯 ต้นทุนรวม (จากครัวอัตโนมัติ + รายจ่ายที่คีย์เพิ่มเอง)
$total_cost = $extra_cost + $kitchen_total;

function thaiNumber($number) {
    $number = round($number, 2);
    $int_part = intval($number);
    $dec_part = round(($number - $int_part) * 100);
    $thai = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $unit = ['','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน'];
    $result = '';
    $num_str = (string)$int_part;
    $len = strlen($num_str);
    for ($i = 0; $i < $len; $i++) {
        $digit = intval($num_str[$i]);
        $pos = $len - $i - 1;
        if ($digit == 0) continue;
        if ($pos == 1 && $digit == 1) { $result .= 'สิบ'; }
        elseif ($pos == 1 && $digit == 2) { $result .= 'ยี่สิบ'; }
        else { $result .= $thai[$digit] . $unit[$pos]; }
    }
    if (empty($result)) $result = 'ศูนย์';
    $result .= 'บาท';
    if ($dec_part > 0) {
        $dec_str = (string)$dec_part;
        if (strlen($dec_str) == 1) $dec_str = '0' . $dec_str;
        for ($i = 0; $i < strlen($dec_str); $i++) {
            $d = intval($dec_str[$i]);
            if ($d == 0) continue;
            $p = strlen($dec_str) - $i - 1;
            if ($p == 1 && $d == 1) $result .= 'สิบ';
            elseif ($p == 1 && $d == 2) $result .= 'ยี่สิบ';
            else $result .= $thai[$d] . ($p > 0 ? $unit[$p] : '');
        }
        $result .= 'สตางค์';
    } else {
        $result .= 'ถ้วน';
    }
    return $result;
}

// 🎯 ค่าบริหาร 3% ของรายรับทั้งหมด
$management_fee = $grand_total_income * 0.03;

// 🎯 กำไรสุทธิ (รายรับทั้งหมด - ต้นทุนทั้งหมด - ค่าบริหาร)
$profit = $grand_total_income - $total_cost - $management_fee;

// ROI (%) หลังอนุมัติ (รวมทุกรายการ)
$roi = ($total_cost > 0) ? ($profit / $total_cost) * 100 : 0;

// 🎯 คำนวณก่อนอนุมัติ (แยกยอดหลังอนุมัติออก)
$pre_cost = $extra_cost - $post_cost;
$pre_income = $total_income - $post_income;
$pre_grand_income = $main_price + $pre_income;
$pre_management_fee = $pre_grand_income * 0.03;
$pre_profit = $pre_grand_income - ($pre_cost + $kitchen_total) - $pre_management_fee;
$pre_total_cost = $pre_cost + $kitchen_total;
$pre_roi = ($pre_total_cost > 0) ? ($pre_profit / $pre_total_cost) * 100 : 0;

// 🎯 คำนวณหลังอนุมัติ (增量 from post-approval)
$post_profit = $post_income - $post_cost;
$post_roi = ($post_cost > 0) ? ($post_profit / $post_cost) * 100 : 0;

// 3. ส่วนสำหรับ AJAX Refresh (จะแสดงผลเฉพาะส่วนนี้เมื่อเรียกผ่าน fetch)
if (isset($_GET['ajax'])) {
    ?>
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ราคาขายงาน</small>
                <h4 class="text-primary mb-0"><?= number_format($data['total_amount'], 2) ?></h4>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">เงินมัดจำรวม</small>
                <h4 class="text-info mb-0"><?= number_format($total_deposit, 2) ?></h4>
                <?php if ($quote_income > 0): ?>
                    <small class="text-info">(จากใบเสนอราคา <?= number_format($quote_income, 2) ?>)</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ต้นทุนรวม</small>
                <h4 class="text-danger mb-0"><?= number_format($total_cost, 2) ?></h4>
                <?php if ($post_cost > 0): ?>
                    <small class="text-warning">(หลังอนุมัติ <?= number_format($post_cost, 2) ?>)</small>
                <?php endif; ?>
                <?php if ($quote_cost > 0): ?>
                    <small class="text-info">(จากใบเสนอราคา <?= number_format($quote_cost, 2) ?>)</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">กำไรสุทธิ</small>
                <h4 class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0"><?= number_format($profit, 2) ?></h4>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ROI ก่อนอนุมัติ</small>
                <h4 class="text-primary mb-0"><?= number_format($pre_roi, 2) ?>%</h4>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted">ROI หลังอนุมัติ</small>
                <h4 class="<?= $roi >= 0 ? 'text-success' : 'text-danger' ?> mb-0"><?= number_format($roi, 2) ?>%</h4>
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
                        <th class="text-end">เงินมัดจำ</th>
                        <th class="text-end">รายจ่าย</th>
                        <th>ผู้บันทึก</th>
                        <th>สิทธิ์</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($finances)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($finances as $f): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y', strtotime($f['transaction_date'])) ?></td>
                                <td><?= htmlspecialchars($f['detail']) ?></td>
                                <td class="text-end text-success">
                                    <?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?>
                                </td>
                                <td class="text-end text-info">
                                    <?= $f['type'] == 'deposit' ? number_format($f['amount'], 2) : '-' ?>
                                </td>
                                <td class="text-end text-danger"><?= $f['type'] == 'cost' ? number_format($f['amount'], 2) : '-' ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($f['created_by_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($f['created_by_role'] ?? '-') ?></span>
                                    <span class="badge <?= financeStageBadgeClass($f) ?>"><?= financeStageLabel($f) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($f['type'] == 'deposit'): ?>
                                        <button type="button" class="btn btn-link text-info p-0 me-1 btn-print-deposit"
                                            data-id="<?= $f['id'] ?>"
                                            data-date="<?= date('d/m/Y', strtotime($f['transaction_date'])) ?>"
                                            data-detail="<?= htmlspecialchars($f['detail']) ?>"
                                            data-amount="<?= number_format($f['amount'], 2) ?>"
                                            data-amount-thai="<?= thaiNumber($f['amount']) ?>"
                                            data-payment="<?= htmlspecialchars($f['payment_method'] ?? '-') ?>"
                                            data-funcname="<?= htmlspecialchars($data['function_name']) ?>"
                                            data-func-code="<?= htmlspecialchars(($data['type_prefix'] ?? '') . ($data['function_code'] ?? '')) ?>"
                                            data-booking-name="<?= htmlspecialchars($data['booking_name'] ?? '') ?>"
                                            data-company="<?= htmlspecialchars($data['company_name'] ?? '') ?>"
                                            data-tax-id="<?= htmlspecialchars($data['cust_tax_id'] ?? '') ?>"
                                            data-phone="<?= htmlspecialchars($data['phone'] ?? $data['cust_phone'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($data['cust_email'] ?? '') ?>"
                                            data-func-type="<?= htmlspecialchars($data['function_type_name'] ?? '') ?>"
                                            data-event-date="<?= $event_date_txt ?>"
                                            data-event-time="<?= $event_time_txt ?>"
                                            data-room="<?= htmlspecialchars($data['room_name'] ?? '') ?>"
                                            data-pax="<?= $data['pax'] ?? 0 ?>"
                                            data-createdby="<?= htmlspecialchars($creator_name) ?>"
                                            data-sig-path="<?= htmlspecialchars($creator_sig) ?>"
                                            title="พิมพ์ใบเงินมัดจำ">
                                            <i class="bi bi-printer"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($quote_locked): ?>
                                        <i class="bi bi-lock text-muted" title="ใบเสนอราคาอนุมัติแล้ว แก้ไขไม่ได้"></i>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-link text-danger p-0 btn-delete-finance"
                                            data-id="<?= $f['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
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

        // ทุน/หน่วย: ใช้ menu_cost ที่กรอกไว้ก่อน ไม่มีค่อยรวมทุนรายเมนูที่จับคู่ชื่อได้
        // จับคู่ไม่ได้สักเมนูค่อยตกไปที่ราคาขาย (ต้องตรงกับ calculate_costs.php)
        $unit_cost = $cost_direct;
        if ($unit_cost <= 0) {
            foreach (preg_split('/\r\n|\r|\n/', $m['menu_detail']) as $line) {
                $n = trim(preg_replace('/^[0-9\.\-\s]+/u', '', $line));
                if ($n !== '') $unit_cost += menuLineCostSplit($conn, $n);
            }
        }

        if ($unit_cost > 0) {
            $total_cost += ($unit_cost * $qty);
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

        // ทุน/หน่วย: ใช้ k_cost ที่กรอกไว้ก่อน ไม่มีค่อยรวมทุนรายเมนูที่จับคู่ชื่อได้
        $k_unit_cost = $k_cost;
        if ($k_unit_cost <= 0) {
            foreach (preg_split('/\r\n|\r|\n/', $k['k_item']) as $line) {
                $n = trim(preg_replace('/^[0-9\.\-\s]+/u', '', $line));
                if ($n !== '') $k_unit_cost += menuLineCostSplit($conn, $n);
            }
        }

        if ($k_unit_cost > 0) {
            $total_cost += ($k_unit_cost * $k_qty);
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
                <i class="bi bi-cash-coin text-primary"></i>
                <?= $is_quote ? 'บัญชีใบเสนอราคา' : 'บัญชีงาน' ?>: <?= htmlspecialchars($data['function_name']) ?>
                <?php if ($is_quote): ?>
                    <span class="badge bg-secondary align-middle"><?= htmlspecialchars($data['quote_no'] ?? '') ?></span>
                <?php endif; ?>
            </h4>
            <small class="text-muted">จัดการรายรับ-รายจ่าย และสรุปผลกำไรสุทธิ</small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" onclick="exportExcel()" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
            <button type="button" onclick="window.open('print_finance_report.php?<?= $page_key ?>', '_blank', 'width=1100,height=900');" class="btn btn-dark btn-sm">
                <i class="bi bi-printer"></i> พิมพ์รายงาน
            </button>
        </div>
    </div>

    <?php if ($is_quote): ?>
        <?php if ($eo_of_quote): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center py-2">
                <div class="small">
                    <i class="bi bi-arrow-right-circle"></i>
                    ใบเสนอราคานี้แปลงเป็น EO แล้ว รายการที่คีย์ไว้ถูกโอนไปที่งานเรียบร้อย — รายการใหม่ให้คีย์ที่หน้าบัญชีของ EO
                </div>
                <a href="finance.php?id=<?= $eo_of_quote['id'] ?>" class="btn btn-sm btn-warning fw-bold">
                    ไปที่บัญชี EO
                </a>
            </div>
        <?php elseif ($quote_locked): ?>
            <div class="alert alert-secondary small py-2">
                <i class="bi bi-lock-fill"></i>
                ใบเสนอราคานี้<b>อนุมัติแล้ว</b> รายการบัญชีถูกล็อก เพิ่ม/ลบไม่ได้
                หากต้องแก้ไขต้องยกเลิกการอนุมัติใบเสนอราคาก่อน
            </div>
        <?php else: ?>
            <div class="alert alert-info small py-2">
                <i class="bi bi-info-circle"></i>
                ขั้นใบเสนอราคา — ต้นทุนเมนูดึงจากรายการในใบเสนอราคา แล้วล้วงราคาทุนตามชื่อเมนู
                (เมนูที่จับคู่ชื่อไม่ได้จะถือว่าทุนเท่ากับราคาขาย)
                เมื่อแปลงใบนี้เป็น EO รายการบัญชีทั้งหมดจะถูกโอนไปให้อัตโนมัติ
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div id="summaryWrapper">
        <div class="row g-3 mb-4">
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ราคาขายงาน</small>
                    <h4 class="text-primary mb-0"><?= number_format($data['total_amount'], 2) ?></h4>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">เงินมัดจำรวม</small>
                    <h4 class="text-info mb-0"><?= number_format($total_deposit, 2) ?></h4>
                    <?php if ($quote_income > 0): ?>
                        <small class="text-info">(จากใบเสนอราคา <?= number_format($quote_income, 2) ?>)</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ต้นทุนรวม</small>
                    <h4 class="text-danger mb-0"><?= number_format($total_cost, 2) ?></h4>
                    <?php if ($post_cost > 0): ?>
                        <small class="text-warning">(หลังอนุมัติ <?= number_format($post_cost, 2) ?>)</small>
                    <?php endif; ?>
                    <?php if ($quote_cost > 0): ?>
                        <small class="text-info">(จากใบเสนอราคา <?= number_format($quote_cost, 2) ?>)</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">กำไรสุทธิ</small>
                    <h4 class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                        <?= number_format($profit, 2) ?>
                    </h4>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ROI ก่อนอนุมัติ</small>
                    <h4 class="text-primary mb-0"><?= number_format($pre_roi, 2) ?>%</h4>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center p-3">
                    <small class="text-muted">ROI หลังอนุมัติ</small>
                    <h4 class="<?= $roi >= 0 ? 'text-success' : 'text-danger' ?> mb-0"><?= number_format($roi, 2) ?>%</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <?php if ($quote_locked): ?>
                <!-- อนุมัติ/แปลงเป็น EO แล้ว ไม่ให้คีย์ค้างไว้ที่ใบเสนอราคาอีก -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center text-muted py-4">
                        <i class="bi bi-lock fs-3 d-block mb-2"></i>
                        <?php if ($eo_of_quote): ?>
                            <div class="small">ใบเสนอราคานี้แปลงเป็น EO แล้ว<br>บันทึกรายการใหม่ได้ที่หน้าบัญชีของ EO</div>
                        <?php else: ?>
                            <div class="small">ใบเสนอราคานี้อนุมัติแล้ว<br>เพิ่มหรือลบรายการบัญชีไม่ได้</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3 text-warning"><i class="bi bi-plus-circle"></i> บันทึกรายการ</h5>
                    
                    <!-- แสดงข้อมูลผู้บันทึก -->
                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-person-fill"></i> ผู้บันทึก: <b><?= htmlspecialchars($_SESSION['user_name'] ?? 'ไม่ระบุ') ?></b> 
                        <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['role'] ?? 'viewer') ?></span>
                    </div>

                    <form id="financeForm">
                        <input type="hidden" name="function_id" value="<?= $is_quote ? '' : $id ?>">
                        <input type="hidden" name="quotation_id" value="<?= $is_quote ? $quote_id : '' ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภท</label>
                            <select name="type" class="form-select" required>
                                <option value="income">รายรับ (ยอดรับจริง)</option>
                                <option value="deposit">เงินมัดจำ (Deposit)</option>
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
            <?php endif; ?>
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
                                <th class="text-end">เงินมัดจำ</th>
                                <th class="text-end">รายจ่าย</th>
                                <th>ช่องทาง</th>
                                <th>สิทธิ์</th>
                                <th class="text-center d-print-none">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($finances)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">ยังไม่มีรายการบันทึก</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($finances as $f): ?>
                                    <tr>
                                        <td class="small"><?= date('d/m/Y', strtotime($f['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($f['detail']) ?></td>
                                        <td class="text-end text-success">
                                            <?= $f['type'] == 'income' ? number_format($f['amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end text-info">
                                            <?= $f['type'] == 'deposit' ? number_format($f['amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end text-danger"><?= $f['type'] == 'cost' ? number_format($f['amount'], 2) : '-' ?>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($f['payment_method'] ?? '-') ?></span></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($f['created_by_role'] ?? '-') ?></span>
                                            <span class="badge <?= financeStageBadgeClass($f) ?>"><?= financeStageLabel($f) ?></span>
                                        </td>
                                        <td class="text-center d-print-none">
                                            <?php if (strtolower($_SESSION['role'] ?? 'viewer') !== 'viewer'): ?>
                                                <?php if ($f['type'] == 'deposit'): ?>
                                                    <button type="button" class="btn btn-link text-info p-0 me-1 btn-print-deposit"
                                                        data-id="<?= $f['id'] ?>"
                                                        data-date="<?= date('d/m/Y', strtotime($f['transaction_date'])) ?>"
                                                        data-detail="<?= htmlspecialchars($f['detail']) ?>"
                                                        data-amount="<?= number_format($f['amount'], 2) ?>"
                                                        data-amount-thai="<?= thaiNumber($f['amount']) ?>"
                                                        data-payment="<?= htmlspecialchars($f['payment_method'] ?? '-') ?>"
                                                        data-funcname="<?= htmlspecialchars($data['function_name']) ?>"
                                                        data-func-code="<?= htmlspecialchars(($data['type_prefix'] ?? '') . ($data['function_code'] ?? '')) ?>"
                                                        data-booking-name="<?= htmlspecialchars($data['booking_name'] ?? '') ?>"
                                                        data-company="<?= htmlspecialchars($data['company_name'] ?? '') ?>"
                                                        data-tax-id="<?= htmlspecialchars($data['cust_tax_id'] ?? '') ?>"
                                                        data-phone="<?= htmlspecialchars($data['phone'] ?? $data['cust_phone'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($data['cust_email'] ?? '') ?>"
                                                        data-func-type="<?= htmlspecialchars($data['function_type_name'] ?? '') ?>"
                                                        data-event-date="<?= $event_date_txt ?>"
                                                        data-event-time="<?= $event_time_txt ?>"
                                                        data-room="<?= htmlspecialchars($data['room_name'] ?? '') ?>"
                                                        data-pax="<?= $data['pax'] ?? 0 ?>"
                                                        data-createdby="<?= htmlspecialchars($creator_name) ?>"
                                                        data-sig-path="<?= htmlspecialchars($creator_sig) ?>"
                                                        title="พิมพ์ใบเงินมัดจำ">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($quote_locked): ?>
                                                    <i class="bi bi-lock text-muted" title="ใบเสนอราคาอนุมัติแล้ว แก้ไขไม่ได้"></i>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-link text-danger p-0 btn-delete-finance"
                                                        data-id="<?= $f['id'] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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
                <div class="d-none d-print-block">
                    <table class="table table-bordered mb-0" style="font-size:11px;">
                        <tr><td class="label-cell" colspan="2" style="background:#e8e8e8;font-weight:bold;">สรุปผลการเงิน</td></tr>
                        <tr><td class="label-cell" style="width:50%">ราคาขายงาน</td><td class="amount text-primary"><?= number_format($main_price, 2) ?></td></tr>
                        <tr><td class="label-cell">เงินมัดจำรวม</td><td class="amount text-info"><?= number_format($total_deposit, 2) ?></td></tr>
                        <tr><td class="label-cell">รายรับเพิ่มเติม</td><td class="amount text-success"><?= number_format($total_income, 2) ?></td></tr>
                        <tr><td class="label-cell" style="background:#DAEEF3;"><strong>รวมรายรับทั้งหมด</strong></td><td class="amount" style="background:#DAEEF3;"><strong><?= number_format($grand_total_income, 2) ?></strong></td></tr>
                        <tr><td class="label-cell">ต้นทุนอาหารหลัก (ครัว)</td><td class="amount text-danger"><?= number_format($kitchen_total, 2) ?></td></tr>
                        <tr><td class="label-cell">ค่าใช้จ่ายอื่นๆ</td><td class="amount text-danger"><?= number_format($extra_cost, 2) ?></td></tr>
                        <tr><td class="label-cell" style="background:#DAEEF3;"><strong>ต้นทุนรวมทั้งสิ้น</strong></td><td class="amount text-danger" style="background:#DAEEF3;"><strong><?= number_format($total_cost, 2) ?></strong></td></tr>
                        <tr><td class="label-cell">ค่าบริหาร 3%</td><td class="amount text-danger"><?= number_format($management_fee, 2) ?></td></tr>
                        <tr><td class="label-cell" style="background:#d4edda;font-size:13px;"><strong>กำไรสุทธิ</strong></td><td class="amount" style="background:#d4edda;font-size:13px;color:<?= $profit >= 0 ? '#006100' : '#c00000' ?>;"><strong><?= number_format($profit, 2) ?></strong></td></tr>
                        <tr><td class="label-cell">ROI ก่อนอนุมัติ</td><td class="amount"><strong><?= number_format($pre_roi, 2) ?>%</strong></td></tr>
                        <tr><td class="label-cell">ROI หลังอนุมัติ</td><td class="amount"><strong><?= number_format($roi, 2) ?>%</strong></td></tr>
                    </table>
                    <div style="margin-top:15px;font-size:10px;color:#888;text-align:center;border-top:1px solid #ddd;padding-top:5px;">
                        รายงานนี้สร้างจากระบบจัดการงานเลี้ยง | <?= date('d/m/Y H:i:s') ?>
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
            window.location.href = 'api/export_finance_excel.php?<?= $page_key ?>';
        }
    </script>
    <script>
        // ฟังก์ชันโหลดข้อมูลใหม่แบบ AJAX
        function refreshFinanceData() {
            fetch(`finance.php?<?= $page_key ?>&ajax=1`)
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

            // พิมพ์ใบเงินมัดจำ
            if (e.target.closest('.btn-print-deposit')) {
                const btn = e.target.closest('.btn-print-deposit');
                const d = btn.dataset;
                const payCash = d.payment === 'Cash' ? '☑' : '☐';
                const payTransfer = d.payment === 'Bank Transfer' ? '☑' : '☐';
                const payCredit = d.payment === 'Credit Card' ? '☑' : '☐';
                const payCheck = d.payment === 'Check' ? '☑' : '☐';

                const printWin = window.open('', '_blank', 'width=900,height=800');
                printWin.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <title>ใบเงินมัดจำ - ${d.funcCode || ''}</title>
                        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
                        <style>
                            * { margin: 0; padding: 0; box-sizing: border-box; }
                            body { font-family: 'Sarabun', 'Tahoma', sans-serif; padding: 20px 30px; color: #000; font-size: 13px; }
                            .no-print { text-align: right; margin-bottom: 10px; }
                            .no-print button { padding: 6px 18px; font-size: 14px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #fff; margin-left: 5px; }
                            .no-print button.btn-print { background: #1a73e8; color: #fff; border-color: #1a73e8; }

                            .header { text-align: center !important; margin-bottom: 8px; border-bottom: 2px solid #000; padding-bottom: 8px; width: 100%; }
                            .header h2 { font-size: 20px; letter-spacing: 1px; text-align: center !important; }
                            .header p { font-size: 11px; color: #555; text-align: center !important; }

                            .doc-info { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 12px; }
                            .doc-info span { display: inline-block; }
                            .underline { border-bottom: 1px solid #000; min-width: 200px; display: inline-block; padding-bottom: 1px; }

                            .section-title { font-weight: 700; font-size: 12px; background: #f0f0f0; padding: 4px 8px; margin: 10px 0 6px; border-left: 3px solid #333; }

                            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; font-size: 12px; margin-bottom: 10px; }
                            .info-grid .field { display: flex; gap: 5px; }
                            .info-grid .field-label { font-weight: 600; white-space: nowrap; }
                            .info-grid .field-value { border-bottom: 1px dotted #999; flex: 1; min-height: 16px; }

                            .event-type { display: flex; gap: 15px; flex-wrap: wrap; font-size: 12px; margin: 6px 0; }
                            .event-type label { display: flex; align-items: center; gap: 3px; }

                            .event-detail { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; font-size: 12px; margin: 6px 0; }

                            .deposit-section { margin: 10px 0; }
                            .deposit-row { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; font-size: 12px; }
                            .deposit-row .field { margin-bottom: 6px; }
                            .amount-line { font-size: 14px; font-weight: 700; }
                            .amount-thai { font-size: 11px; color: #333; margin-top: 2px; }

                            .conditions { font-size: 11px; line-height: 1.7; margin: 10px 0; }
                            .conditions ol { padding-left: 18px; }
                            .conditions li { margin-bottom: 1px; }

                            .note { font-size: 11px; font-style: italic; border: 1px dashed #999; padding: 6px 10px; margin: 8px 0; background: #fafafa; }

                            .signature-section { display: flex; justify-content: space-between; margin-top: 30px; }
                            .sign-box { width: 45%; text-align: center; }
                            .sign-box .sig-area { height: 55px; display: flex; align-items: flex-end; justify-content: center; }
                            .sign-box .sig-area img { max-height: 50px; }
                            .sign-box .line { border-top: 1px solid #000; padding-top: 4px; font-size: 11px; }
                            .sign-box .name { font-weight: 600; font-size: 12px; }

                            .pay-option { display: flex; gap: 12px; font-size: 12px; margin: 4px 0; }
                            .pay-option label { display: flex; align-items: center; gap: 3px; }

                            @media print {
                                body { padding: 10px 15px; font-size: 12px; }
                                .no-print { display: none !important; }
                                @page { size: A4; margin: 5mm; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="no-print">
                            <button onclick="window.print();" class="btn-print">🖨️ พิมพ์</button>
                            <button onclick="window.close();">ปิด</button>
                        </div>

                        <div class="header">
                            <h2>ใบเงินมัดจำการจอง</h2>
                            <p>DEPOSIT RECEIPT</p>
                        </div>

                        <div class="doc-info">
                            <div><span>เลขที่ : </span><span class="underline">${d.funcCode || '_____________________'}</span></div>
                            <div><span>วันที่ : </span><span class="underline">${d.date || '_____________________'}</span></div>
                        </div>

                        <div class="section-title">ข้อมูลลูกค้า</div>
                        <div class="info-grid">
                            <div class="field"><span class="field-label">ชื่อผู้จอง</span><span class="field-value">${d.bookingName || ''}</span></div>
                            <div class="field"><span class="field-label">บริษัท</span><span class="field-value">${d.company || ''}</span></div>
                            <div class="field"><span class="field-label">เลขประจำตัวผู้เสียภาษี</span><span class="field-value">${d.taxId || ''}</span></div>
                            <div class="field"><span class="field-label">โทรศัพท์</span><span class="field-value">${d.phone || ''}</span></div>
                            <div class="field"><span class="field-label">E-mail</span><span class="field-value">${d.email || ''}</span></div>
                        </div>

                        <div class="section-title">รายละเอียดการจองประเภทงาน</div>
                        <div class="event-type">
                            <label>☐ ประชุม</label>
                            <label>☐ สัมมนา</label>
                            <label>☐ งานแต่งงาน</label>
                            <label>☐ งานเลี้ยง</label>
                            <label>☐ อื่น ๆ <span class="underline" style="min-width:100px;">${d.funcType || ''}</span></label>
                        </div>
                        <div class="event-detail">
                            <div class="field"><span class="field-label">วันที่จัดงาน</span> <span class="underline" style="min-width:100px;">${d.eventDate || ''}</span></div>
                            <div class="field"><span class="field-label">เวลา</span> <span class="underline" style="min-width:60px;">${d.eventTime || ''}</span></div>
                            <div class="field"><span class="field-label">ห้องประชุม</span> <span class="underline" style="min-width:80px;">${d.room || ''}</span></div>
                            <div class="field"><span class="field-label">จำนวนแขก</span> <span class="underline" style="min-width:40px;">${d.pax || ''}</span> คน</div>
                        </div>

                        <div class="section-title">รายละเอียดเงินมัดจำการจอง</div>
                        <div class="deposit-section">
                            <div class="deposit-row">
                                <div>
                                    <div class="amount-line">จำนวนเงิน <span class="underline" style="min-width:150px; display:inline-block;">${d.amount || '0.00'}</span> บาท</div>
                                    <div class="amount-thai">( ${d.amountThai || ''} )</div>
                                </div>
                                <div>
                                    <div style="font-weight:600; margin-bottom:4px;">ชำระโดย</div>
                                    <div class="pay-option">
                                        <label>${payCash} เงินสด</label>
                                        <label>${payTransfer} โอนเงิน</label>
                                        <label>${payCredit} บัตรเครดิต</label>
                                        <label>${payCheck} เช็ค</label>
                                    </div>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:6px; font-size:12px;">
                                <div class="field"><span class="field-label">เลขอ้างอิง</span> <span class="underline" style="min-width:120px;">${d.detail || ''}</span></div>
                                <div class="field"><span class="field-label">วันที่รับเงิน</span> <span class="underline" style="min-width:120px;">${d.date || ''}</span></div>
                            </div>
                        </div>

                        <div class="conditions">
                            <strong>เงื่อนไขการรับเงินมัดจำโดยลูกค้าได้รับทราบและยอมรับว่า</strong>
                            <ol>
                                <li>เงินจำนวนนี้เป็น เงินมัดจำเพื่อยืนยันการจอง</li>
                                <li>โรงแรมยังไม่รับรู้เป็นรายได้ เนื่องจากยังอยู่ภายใต้เงื่อนไขของสัญญา</li>
                                <li>โรงแรมจะออกใบกำกับภาษี/ใบเสร็จรับเงินเมื่อมีการให้บริการจริง</li>
                                <li>การคืนเงินมัดจำเป็นไปตามเงื่อนไขที่ระบุในใบเสนอราคา/สัญญาจัดงาน</li>
                                <li>หากมีการยกเลิกหลังพ้นกำหนด โรงแรมมีสิทธิริบเงินมัดจำทั้งหมดหรือบางส่วนตามสัญญา</li>
                                <li>หากงานมีการเลื่อนวันจัด โรงแรมสามารถนำเงินมัดจำไปใช้กับวันใหม่ได้ตามที่ตกลงร่วมกัน</li>
                            </ol>
                        </div>

                        <div class="note">
                            <strong>หมายเหตุ</strong><br>
                            เงินมัดจำฉบับนี้ ไม่ใช่ใบกำกับภาษี และ ไม่ถือเป็นการรับรู้รายได้ของโรงแรม
                        </div>

                        <div class="signature-section">
                            <div class="sign-box">
                                <div class="sig-area">
                                    ${d.sigPath ? '<img src="' + d.sigPath + '">' : ''}
                                </div>
                                <div class="line">
                                    <div class="name">ผู้รับเงิน ${d.createdby || '..................................'}</div>
                                    <div>วันที่ .................................</div>
                                </div>
                            </div>
                            <div class="sign-box">
                                <div class="sig-area"></div>
                                <div class="line">
                                    <div class="name">ลงชื่อ ..................................ผู้วางมัดจำ</div>
                                    <div>วันที่ ..................................</div>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                printWin.document.close();
            }
        });
    </script>

    <?php include "footer.php"; ?>