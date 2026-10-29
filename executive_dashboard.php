<?php
include "config.php";
include_once __DIR__ . "/includes/report_data.php";

$role = strtolower($_SESSION['role'] ?? '');
$allowed = ['admin', 'staff', 'gm', 'sale', 'procurement', 'manager'];

/* =========================================================
   ช่วงเวลา: รายวัน / รายเดือน / รายปี
   ========================================================= */
$today = date('Y-m-d');
$mode = in_array($_GET['mode'] ?? '', ['day', 'month', 'year']) ? $_GET['mode'] : 'month';
$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : $today;
$ym = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$yr = isset($_GET['year']) ? max(2000, min(2100, intval($_GET['year']))) : (int) date('Y');
$company = intval($_GET['company_id'] ?? 0);
$staff = $role === 'staff' ? 0 : intval($_GET['staff_id'] ?? 0);

if ($mode === 'day') {
    $from = $date;
    $to = date('Y-m-d', strtotime("$date +1 day"));
    $prev_from = date('Y-m-d', strtotime("$date -1 day"));
    $t = strtotime($date);
    $label = 'วัน' . ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][(int) date('w', $t)] . 'ที่ ' . (int) date('j', $t) . ' ' . RPT_TH_MONTHS[(int) date('n', $t)] . ' ' . (date('Y', $t) + 543);
    $prev_q = ['date' => $prev_from];
    $next_q = ['date' => $to];
    $prev_word = 'เมื่อวาน';
    // กราฟแนวโน้มของรายวัน = ทุกวันในเดือนนั้น (ไฮไลต์วันที่เลือก)
    $trend_from = substr($date, 0, 7) . '-01';
    $trend_to = date('Y-m-d', strtotime("$trend_from +1 month"));
} elseif ($mode === 'year') {
    $from = "$yr-01-01";
    $to = ($yr + 1) . "-01-01";
    $prev_from = ($yr - 1) . "-01-01";
    $label = 'ปี ' . ($yr + 543);
    $prev_q = ['year' => $yr - 1];
    $next_q = ['year' => $yr + 1];
    $prev_word = 'ปีก่อน';
    $trend_from = $from;
    $trend_to = $to;
} else {
    $from = "$ym-01";
    $to = date('Y-m-d', strtotime("$from +1 month"));
    $prev_from = date('Y-m-d', strtotime("$from -1 month"));
    $label = RPT_TH_MONTHS[(int) substr($ym, 5, 2)] . ' ' . (substr($ym, 0, 4) + 543);
    $prev_q = ['month' => substr($prev_from, 0, 7)];
    $next_q = ['month' => substr($to, 0, 7)];
    $prev_word = 'เดือนก่อน';
    $trend_from = $from;
    $trend_to = $to;
}

$ev = rpt_events($conn, $from, $to, $company, $staff);
$all = array_merge($ev['confirmed'], $ev['pending'], $ev['cancelled']);
usort($all, fn($a, $b) => strcmp($a['start'], $b['start']));

$status_th = function ($e) {
    if ($e['approve'] === 2 || $e['status'] === 'Cancelled') return ['ยกเลิก', 'crit'];
    if ($e['approve'] === 0) return ['รออนุมัติ', 'warn'];
    return [['In Progress' => 'กำลังจัด', 'Completed' => 'จัดเสร็จแล้ว'][$e['status']] ?? 'ยืนยันแล้ว', 'ok'];
};

/* ===== ส่งออก Excel (CSV) — ต้องทำก่อน include header.php ===== */
if (($_GET['export'] ?? '') === 'csv') {
    if (!isset($_SESSION['user']) || !in_array($role, $allowed)) { http_response_code(403); exit('forbidden'); }
    $fn = 'banquet_' . $mode . '_' . ($mode === 'day' ? $date : ($mode === 'year' ? $yr : $ym)) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยได้
    fputcsv($out, ['วันที่', 'เวลา', 'รหัสงาน', 'ชื่องาน', 'ลูกค้า', 'ประเภทงาน', 'โรงแรม', 'ห้อง', 'เซลล์', 'จำนวนคน', 'มูลค่างาน', 'มัดจำ', 'สถานะ']);
    foreach ($all as $e) {
        fputcsv($out, [$e['date'], $e['time'], $e['code'], $e['name'], $e['customer'], $e['type'], $e['company'], $e['room'],
            $e['staff'], $e['pax'], $e['total'], $e['deposit'], $status_th($e)[0]]);
    }
    fclose($out);
    exit;
}

include "header.php";
if (!in_array($role, $allowed)) {
    echo "<script>window.location.href='login.php?error=access_denied';</script>";
    exit;
}

/* =========================================================
   ตัวเลขสรุปของช่วงที่เลือก
   ========================================================= */
$sum = rpt_sum($ev['confirmed']);
$cancel = rpt_sum($ev['cancelled']);
$prev_ev = rpt_events($conn, $prev_from, $from, $company, $staff)['confirmed'];
$prev = rpt_sum($prev_ev);
$delta = $prev['revenue'] > 0 ? round(($sum['revenue'] - $prev['revenue']) / $prev['revenue'] * 100) : null;
$deps = rpt_deposits($conn, $from, $to, $company, $staff);
$dep_total = array_sum(array_column($deps, 'amount'));
$deals = rpt_deals($conn, $company, $staff);
$conv = rpt_conversion($deals, $from, $to);
$target = $mode === 'day' ? 0 : rpt_target($conn, (int) substr($from, 0, 4), $mode === 'month' ? (int) substr($from, 5, 2) : 0, $staff);

/* ใส่ช่วงเวลาของวัน + ขนาดงาน ให้แต่ละงาน (ใช้กรองตอนกดกราฟ) */
$size_of = fn($p) => $p < 30 ? 'ไม่ถึง 30 คน' : ($p < 100 ? '30–99 คน' : ($p < 200 ? '100–199 คน' : '200 คนขึ้นไป'));
$enrich = function (&$rows) use ($size_of) {
    foreach ($rows as &$e) {
        $hh = (int) substr($e['start'], 11, 2);
        $e['period'] = $hh < 12 ? 'เช้า' : ($hh < 17 ? 'บ่าย' : 'เย็น');
        $e['size'] = $size_of($e['pax']);
    }
};
foreach (['confirmed', 'pending', 'cancelled'] as $k) $enrich($ev[$k]);

/* =========================================================
   แนวโน้ม: รายปี = 12 เดือน, รายเดือน/รายวัน = ทุกวันในเดือน
   ========================================================= */
$trend_ev = ($trend_from === $from && $trend_to === $to) ? $ev['confirmed'] : rpt_events($conn, $trend_from, $trend_to, $company, $staff)['confirmed'];
$by_month_axis = $mode === 'year';
$buckets = [];
if ($by_month_axis) {
    for ($m = 1; $m <= 12; $m++) $buckets[sprintf('%d-%02d', $yr, $m)] = [];
} else {
    for ($d = $trend_from; $d < $trend_to; $d = date('Y-m-d', strtotime("$d +1 day"))) $buckets[$d] = [];
}
$bkey = fn($ymd) => $by_month_axis ? substr($ymd, 0, 7) : substr($ymd, 0, 10);
foreach ($trend_ev as $e) $buckets[$bkey($e['date'])][] = $e;
$trend_cancel = [];
$trend_dep = [];
foreach ($ev['cancelled'] as $e) $trend_cancel[$bkey($e['date'])][] = $e;
foreach ($deps as $d) $trend_dep[$bkey($d['date'])] = ($trend_dep[$bkey($d['date'])] ?? 0) + $d['amount'];

$base = ['mode' => $mode, 'company_id' => $company, 'staff_id' => $staff];
$url = fn($extra = []) => 'executive_dashboard.php?' . http_build_query(array_filter(array_merge($base, $extra)));
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$baht = fn($n) => '฿' . number_format((float) $n);

$trend = ['labels' => [], 'values' => [], 'counts' => [], 'keys' => [], 'titles' => [], 'links' => [], 'current' => -1];
$data = [];
$i = 0;
foreach ($buckets as $k => $rows) {
    $t = strtotime(strlen($k) === 7 ? "$k-01" : $k);
    if ($by_month_axis) {
        $trend['labels'][] = RPT_TH_MONTHS_SHORT[(int) date('n', $t)];
        $trend['titles'][] = RPT_TH_MONTHS[(int) date('n', $t)] . ' ' . (date('Y', $t) + 543);
        $trend['links'][] = $url(['mode' => 'month', 'month' => $k]);
    } else {
        $trend['labels'][] = (int) date('j', $t);
        $trend['titles'][] = rpt_th_date($k);
        $trend['links'][] = $url(['mode' => 'day', 'date' => $k]);
        if ($mode === 'day' && $k === $date) $trend['current'] = $i;
        if ($mode === 'month' && $k === $today) $trend['current'] = $i;
    }
    if ($mode === 'year' && $k === date('Y-m')) $trend['current'] = $i;
    $trend['values'][] = array_sum(array_column($rows, 'total'));
    $trend['counts'][] = count($rows);
    $trend['keys'][] = "b_$k";
    $data["b_$k"] = $rows;
    $i++;
}

/* สะสมเทียบช่วงก่อน (รายเดือน: วันที่ 1..31, รายปี: ม.ค...ธ.ค.) */
$cum = null;
if ($mode !== 'day') {
    $n = count($buckets);
    $cur_cum = [];
    $prev_cum = array_fill(0, $n, 0.0);
    $run = 0;
    $last_idx = $n - 1;
    foreach (array_values($trend['values']) as $j => $v) { $run += $v; $cur_cum[] = $run; }
    // ช่วงปัจจุบันที่ยังไม่จบ ตัดเส้นที่วันนี้ ไม่ลากแบนไปถึงสิ้นเดือน
    if ($from <= $today && $today < $to) {
        $last_idx = $mode === 'year' ? (int) date('n') - 1 : (int) date('j') - 1;
        for ($j = $last_idx + 1; $j < $n; $j++) $cur_cum[$j] = null;
    }
    foreach ($prev_ev as $e) {
        $j = $mode === 'year' ? (int) substr($e['date'], 5, 2) - 1 : (int) substr($e['date'], 8, 2) - 1;
        if ($j < $n) $prev_cum[$j] += $e['total'];
    }
    $run = 0;
    foreach ($prev_cum as $j => $v) { $run += $v; $prev_cum[$j] = $run; }
    $cum = ['labels' => $trend['labels'], 'cur' => $cur_cum, 'prev' => $prev_cum,
        'target' => $target > 0 ? array_map(fn($j) => round($target * ($j + 1) / $n), range(0, $n - 1)) : null];
}

/* ===== กราฟแยกตามมิติ ===== */
function chart_group($rows, $field, $max = 6, $metric = 'revenue')
{
    $g = rpt_group($rows, $field);
    if ($metric === 'count') usort($g, fn($a, $b) => $b['count'] <=> $a['count']);
    $labels = $values = $fv = [];
    foreach ($g as $i => $r) {
        if ($i < $max) {
            $labels[] = $r['label'];
            $values[] = $metric === 'count' ? $r['count'] : $r['revenue'];
            $fv[] = $r['label'];
        }
    }
    if (count($g) > $max) {
        $rest = array_slice($g, $max);
        $labels[] = 'อื่นๆ';
        $values[] = array_sum(array_column($rest, $metric === 'count' ? 'count' : 'revenue'));
        $fv[] = '__other__';
    }
    return ['labels' => $labels, 'values' => $values, 'fv' => $fv, 'field' => $field];
}
$c = $ev['confirmed'];
$charts = [
    'type' => chart_group($c, 'type'),
    'company' => chart_group($c, 'company'),
    'period' => chart_group($c, 'period'),
    'staff' => chart_group($c, 'staff', 8),
    'room' => chart_group($c, 'room', 8, 'count'),
];
// ขนาดงาน: เรียงตามขนาด ไม่ใช่ตามยอด
$sizes = ['ไม่ถึง 30 คน', '30–99 คน', '100–199 คน', '200 คนขึ้นไป'];
$size_g = array_fill_keys($sizes, 0);
foreach ($c as $e) $size_g[$e['size']]++;
$charts['size'] = ['labels' => $sizes, 'values' => array_values($size_g), 'fv' => $sizes, 'field' => 'size'];
// กรวยการขาย: ดีลที่เริ่มเสนอราคาในช่วงนี้
$deal_states = ['open' => 'รอลูกค้าตัดสินใจ', 'eo' => 'เปิดงาน รออนุมัติ', 'won' => 'ได้งาน', 'lost' => 'ไม่ได้งาน'];
$funnel = ['labels' => [], 'values' => [], 'money' => [], 'fv' => [], 'field' => 'state'];
foreach ($deal_states as $k => $lb) {
    $rows = array_filter($conv['deals'], fn($d) => $d['state'] === $k);
    $funnel['labels'][] = $lb;
    $funnel['values'][] = count($rows);
    $funnel['money'][] = array_sum(array_column($rows, 'total'));
    $funnel['fv'][] = $k;
}

/* ===== เป้ารายเซลล์ ===== */
$staff_targets = $mode === 'day' ? [] : rpt_target_by_user($conn, (int) substr($from, 0, 4), $mode === 'month' ? (int) substr($from, 5, 2) : 0);
$staff_ids = [];
foreach ($c as $e) if ($e['staff_id']) $staff_ids[$e['staff']] = $e['staff_id'];

/* =========================================================
   เรื่องที่ต้องจัดการ + งาน 14 วันข้างหน้า (ไม่ขึ้นกับช่วงที่เลือก)
   ========================================================= */
$future = rpt_events($conn, $today, '2100-01-01', $company, $staff);
$h14 = date('Y-m-d', strtotime('+14 days'));
$upcoming = array_values(array_filter(array_merge($future['confirmed'], $future['pending']), fn($e) => $e['date'] < $h14));
usort($upcoming, fn($a, $b) => strcmp($a['start'], $b['start']));
$agenda = [];
foreach ($upcoming as $e) $agenda[$e['date']][] = $e;
$d7 = date('Y-m-d H:i:s', strtotime('+7 days'));
$a_nodep = array_values(array_filter($future['confirmed'], fn($e) => $e['start'] <= $d7 && $e['deposit'] <= 0));
$conflict_ids = array_flip(rpt_room_conflicts($conn, $company, $staff));
$a_conflict = array_values(array_filter($future['confirmed'], fn($e) => isset($conflict_ids[$e['id']])));
$open_deals = array_values(array_filter($deals, fn($d) => $d['state'] === 'open' && $d['date'] && $d['date'] >= $today));
usort($open_deals, fn($a, $b) => strcmp($a['date'], $b['date']));
$a_deals_soon = array_values(array_filter($open_deals, fn($d) => $d['date'] < $h14));
$todo = [
    ['a_pending', 'bi-hourglass-split', 'งานรออนุมัติ', 'เปิด EO แล้ว ยังไม่มีผู้อนุมัติ', count($future['pending']), 'warn', 'event'],
    ['a_nodep', 'bi-cash-coin', 'ใกล้วันงาน ยังไม่ได้มัดจำ', 'งานยืนยันแล้วที่จะจัดภายใน 7 วัน', count($a_nodep), 'crit', 'event'],
    ['a_conflict', 'bi-door-closed', 'ห้องถูกจองเวลาชนกัน', 'ห้องเดียวกัน เวลาทับกัน', count($a_conflict), 'crit', 'event'],
    ['a_deals_soon', 'bi-telephone-outbound', 'ใบเสนอราคาที่ควรรีบตาม', 'วันงานอีกไม่ถึง 14 วัน ลูกค้ายังไม่ยืนยัน', count($a_deals_soon), 'warn', 'deal'],
];

/* ===== ตารางห้องของวัน (เฉพาะรายวัน) ===== */
$timeline = [];
if ($mode === 'day') {
    foreach (array_merge($ev['confirmed'], $ev['pending']) as $e) $timeline[$e['room']][] = $e;
    ksort($timeline);
}

$data += [
    'confirmed' => $ev['confirmed'], 'cancelled' => $ev['cancelled'], 'pending' => $ev['pending'],
    'deposits' => $deps, 'deals' => $conv['deals'], 'open_deals' => $open_deals,
    'a_pending' => $future['pending'], 'a_nodep' => $a_nodep, 'a_conflict' => $a_conflict, 'a_deals_soon' => $a_deals_soon,
];
foreach ($trend_cancel as $k => $rows) $data["bc_$k"] = $rows;

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
$staff_list = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff','Admin','Sale','Manager','GM') ORDER BY name");
$cur_q = $mode === 'day' ? ['date' => $date] : ($mode === 'year' ? ['year' => $yr] : ['month' => $ym]);
$filter_note = [];
if ($company) { $companies->data_seek(0); while ($r = $companies->fetch_assoc()) if ($r['id'] == $company) $filter_note[] = $r['company_name']; }
if ($staff) { $staff_list->data_seek(0); while ($r = $staff_list->fetch_assoc()) if ($r['id'] == $staff) $filter_note[] = 'เซลล์ ' . $r['name']; }
$word = ['day' => 'วันนี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้'][$mode];
?>
<link rel="stylesheet" href="assets/css/insights.css?v=<?= @filemtime(__DIR__ . '/assets/css/insights.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="ins py-2">

    <!-- ===== หัวหน้า ===== -->
    <div class="ins-head">
        <div>
            <h1 id="dashTitle">Dashboard &amp; รายงาน</h1>
            <div class="sub"><?= $h($label) ?><?= $filter_note ? ' · ' . $h(implode(' · ', $filter_note)) : '' ?> · นับเฉพาะงานที่ยืนยันแล้ว ตามวันจัดงาน</div>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="<?= $h($url(array_merge($cur_q, ['export' => 'csv']))) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
        </div>
    </div>

    <!-- ===== ตัวกรอง ===== -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print" id="dashFilter">
        <nav class="ins-seg" aria-label="ช่วงเวลา">
            <a href="<?= $h($url(['mode' => 'day', 'date' => $mode === 'day' ? $date : ($mode === 'month' && $ym !== date('Y-m') ? "$ym-01" : $today)])) ?>" class="<?= $mode === 'day' ? 'active' : '' ?>">รายวัน</a>
            <a href="<?= $h($url(['mode' => 'month', 'month' => substr($from, 0, 7)])) ?>" class="<?= $mode === 'month' ? 'active' : '' ?>">รายเดือน</a>
            <a href="<?= $h($url(['mode' => 'year', 'year' => substr($from, 0, 4)])) ?>" class="<?= $mode === 'year' ? 'active' : '' ?>">รายปี</a>
        </nav>
        <form method="get" class="ins-filter">
            <input type="hidden" name="mode" value="<?= $mode ?>">
            <a href="<?= $h($url($prev_q)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="ก่อนหน้า"><i class="bi bi-chevron-left"></i></a>
            <?php if ($mode === 'day'): ?>
                <input type="date" name="date" value="<?= $date ?>" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="วันที่">
            <?php elseif ($mode === 'month'): ?>
                <input type="month" name="month" value="<?= $ym ?>" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="เดือน">
            <?php else: ?>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="ปี">
                    <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $yr ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>
            <a href="<?= $h($url($next_q)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="ถัดไป"><i class="bi bi-chevron-right"></i></a>
            <a href="<?= $h($url([$mode === 'day' ? 'date' : ($mode === 'year' ? 'year' : 'month') => $mode === 'day' ? $today : ($mode === 'year' ? date('Y') : date('Y-m'))])) ?>" class="btn btn-sm btn-outline-secondary"><?= $word ?></a>
            <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="โรงแรม">
                <option value="0">ทุกโรงแรม</option>
                <?php $companies->data_seek(0); while ($r = $companies->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>" <?= $company == $r['id'] ? 'selected' : '' ?>><?= $h($r['company_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <?php if ($role !== 'staff'): ?>
                <select name="staff_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="เซลล์">
                    <option value="0">ทุกเซลล์</option>
                    <?php $staff_list->data_seek(0); while ($r = $staff_list->fetch_assoc()): ?>
                        <option value="<?= $r['id'] ?>" <?= $staff == $r['id'] ? 'selected' : '' ?>><?= $h($r['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- ===== ตัวเลขหลัก ===== -->
    <div class="sumstrip mb-3" id="dashKpi">
        <button type="button" data-drill="confirmed" data-title="งานที่ยืนยันแล้ว · <?= $h($label) ?>">
            <div class="s-l">รายได้</div>
            <div class="s-v"><?= $baht($sum['revenue']) ?></div>
            <div class="s-s">
                <?php if ($delta !== null): ?><span class="delta <?= $delta >= 0 ? 'up' : 'down' ?>"><?= $delta >= 0 ? '▲' : '▼' ?><?= abs($delta) ?>%</span> จาก<?= $prev_word ?>
                <?php else: ?><?= $prev_word ?>ไม่มีรายได้<?php endif; ?>
                <?= $target > 0 ? '<br>' . round($sum['revenue'] / $target * 100) . '% ของเป้า ' . $baht($target) : '' ?>
            </div>
        </button>
        <button type="button" data-drill="confirmed" data-title="งานที่ยืนยันแล้ว · <?= $h($label) ?>">
            <div class="s-l">งานที่ยืนยัน</div>
            <div class="s-v"><?= number_format($sum['count']) ?></div>
            <div class="s-s"><?= number_format($sum['pax']) ?> คน<?= $sum['count'] ? ' · เฉลี่ย ' . $baht($sum['revenue'] / $sum['count']) : '' ?></div>
        </button>
        <button type="button" data-drill="deposits" data-kind="deposit" data-title="มัดจำที่รับ · <?= $h($label) ?>">
            <div class="s-l">มัดจำที่รับ</div>
            <div class="s-v"><?= $baht($dep_total) ?></div>
            <div class="s-s"><?= count($deps) ?> รายการ · ตามวันที่รับเงิน</div>
        </button>
        <button type="button" data-drill="pending" data-title="งานรออนุมัติ · <?= $h($label) ?>">
            <div class="s-l">รออนุมัติ</div>
            <div class="s-v"><?= count($ev['pending']) ?></div>
            <div class="s-s"><?= $baht(array_sum(array_column($ev['pending'], 'total'))) ?></div>
        </button>
        <button type="button" data-drill="cancelled" data-title="งานที่ยกเลิก · <?= $h($label) ?>">
            <div class="s-l">ยกเลิก</div>
            <div class="s-v" style="<?= $cancel['count'] ? 'color:var(--danger)' : '' ?>"><?= $cancel['count'] ?></div>
            <div class="s-s">มูลค่าที่หายไป <?= $baht($cancel['revenue']) ?></div>
        </button>
        <button type="button" data-drill="deals" data-kind="deal" data-title="ใบเสนอราคาที่เริ่มเสนอ · <?= $h($label) ?>">
            <div class="s-l">ปิดการขาย</div>
            <div class="s-v"><?= $conv['quoted'] ? $conv['rate'] . '%' : '–' ?></div>
            <div class="s-s">ได้งาน <?= $conv['won'] ?> จาก <?= $conv['quoted'] ?> ใบเสนอราคา</div>
        </button>
    </div>

    <!-- ===== แนวโน้ม + ต้องจัดการ ===== -->
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="ins-card" id="dashTrend">
                <div class="ins-card-hd">
                    <h2><?= $mode === 'year' ? 'รายได้แต่ละเดือน' : 'รายได้แต่ละวัน' ?></h2>
                    <span class="hint"><?= $mode === 'day' ? RPT_TH_MONTHS[(int) substr($date, 5, 2)] . ' ' . (substr($date, 0, 4) + 543) . ' · ' : '' ?>กดแท่งเพื่อดูรายการงาน</span>
                </div>
                <div class="ins-card-bd"><div class="chart-wrap" style="height:260px"><canvas id="cTrend" aria-label="กราฟรายได้"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="ins-card" id="dashTodo">
                <div class="ins-card-hd"><h2>ต้องจัดการ</h2><span class="hint">ไม่ขึ้นกับช่วงที่เลือก</span></div>
                <div class="ins-card-bd pt-0">
                    <ul class="todo">
                        <?php foreach ($todo as [$key, $icon, $lb, $desc, $n, $lvl, $kind]): ?>
                            <li class="<?= $n > 0 ? "is-$lvl" : 'is-ok' ?>">
                                <button type="button" data-drill="<?= $key ?>" data-kind="<?= $kind ?>" data-title="<?= $h($lb) ?>">
                                    <span class="ic"><i class="bi <?= $n > 0 ? $icon : 'bi-check-lg' ?>"></i></span>
                                    <span class="tx"><b><?= $h($lb) ?></b><small><?= $n > 0 ? $h($desc) : 'เรียบร้อย ไม่มีรายการค้าง' ?></small></span>
                                    <span class="n"><?= $n ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== สะสม / ตารางห้อง + กรวยการขาย ===== -->
    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <?php if ($mode === 'day'): ?>
                <div class="ins-card">
                    <div class="ins-card-hd"><h2>ตารางการใช้ห้อง</h2><span class="hint">06:00–24:00 · กดแถบเพื่อเปิดงาน</span></div>
                    <div class="ins-card-bd pt-0">
                        <?php if (!$timeline): ?>
                            <div class="ins-empty">ไม่มีงานในวันนี้</div>
                        <?php else: ?>
                            <div class="tl">
                                <div class="tl-row tl-axis"><div class="tl-room"></div><div class="tl-track">
                                    <?php for ($hr = 6; $hr <= 24; $hr += 3): ?><span style="left:<?= round(($hr - 6) / 18 * 100, 2) ?>%"><?= sprintf('%02d:00', $hr) ?></span><?php endfor; ?>
                                </div></div>
                                <?php foreach ($timeline as $room => $evs): ?>
                                    <div class="tl-row">
                                        <div class="tl-room" title="<?= $h($room) ?>"><?= $h($room) ?></div>
                                        <div class="tl-track">
                                            <?php foreach ($evs as $e):
                                                [$s1, $s2] = array_pad(explode('–', $e['time']), 2, '');
                                                $a = max(0, ((int) substr($s1, 0, 2) + (int) substr($s1, 3, 2) / 60) - 6);
                                                $b = $s2 ? min(18, ((int) substr($s2, 0, 2) + (int) substr($s2, 3, 2) / 60) - 6) : $a + 1;
                                                if ($b <= $a) $b = 18; ?>
                                                <a href="view.php?id=<?= $e['id'] ?>" class="tl-bar <?= $e['approve'] === 0 ? 'is-pending' : '' ?>"
                                                    style="left:<?= round($a / 18 * 100, 2) ?>%;width:<?= max(3, round(($b - $a) / 18 * 100, 2)) ?>%"
                                                    data-tip="<?= $h('<b>' . $h($e['name']) . '</b><br>' . $e['time'] . ' · ' . number_format($e['pax']) . ' คน<br>' . $baht($e['total']) . ($e['approve'] === 0 ? ' · รออนุมัติ' : '')) ?>">
                                                    <?= $h($e['name']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="ins-card">
                    <div class="ins-card-hd">
                        <h2>ยอดสะสมเทียบ<?= $prev_word ?></h2>
                        <span class="hint">เส้นทองคือช่วงที่เลือก เส้นเทาคือ<?= $prev_word ?><?= $target > 0 ? ' · เส้นประคือเป้า' : '' ?></span>
                    </div>
                    <div class="ins-card-bd"><div class="chart-wrap" style="height:240px"><canvas id="cCum" aria-label="กราฟยอดสะสม"></canvas></div></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-xl-5">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>ผลการเสนอราคา</h2><span class="hint">ใบที่เริ่มเสนอใน<?= $word === 'วันนี้' ? 'วันที่เลือก' : 'ช่วงนี้' ?> · ปิดได้ <?= $conv['rate'] ?>%</span></div>
                <div class="ins-card-bd">
                    <?php if (!$conv['quoted']): ?>
                        <div class="ins-empty">ไม่มีใบเสนอราคาที่เริ่มเสนอในช่วงนี้</div>
                    <?php else: ?>
                        <div class="chart-wrap" style="height:200px"><canvas id="cFunnel" aria-label="กราฟผลการเสนอราคา"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== สัดส่วน ===== -->
    <?php $donuts = [['type', 'cType', 'ประเภทงาน'], ['company', 'cCompany', 'โรงแรม'], ['period', 'cPeriod', 'ช่วงเวลาจัดงาน']]; ?>
    <div class="row g-3 mb-3">
        <?php foreach ($donuts as [$k, $id, $title]): ?>
            <div class="col-md-6 col-xl-4">
                <div class="ins-card">
                    <div class="ins-card-hd"><h2>รายได้ตาม<?= $title ?></h2></div>
                    <div class="ins-card-bd">
                        <?php if (!$charts[$k]['labels']): ?>
                            <div class="ins-empty">ไม่มีข้อมูล</div>
                        <?php else: ?>
                            <div class="donut-row">
                                <div class="chart-wrap" style="height:160px;width:160px;flex:0 0 160px"><canvas id="<?= $id ?>" aria-label="กราฟ<?= $title ?>"></canvas></div>
                                <ul class="lgd" id="<?= $id ?>Lgd"></ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== เซลล์ / ห้อง / ขนาดงาน ===== -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>ผลงานเซลล์</h2><span class="hint">รายได้<?= $staff_targets ? ' · % ของเป้าในวงเล็บ' : '' ?></span></div>
                <div class="ins-card-bd"><?php if (!$charts['staff']['labels']): ?><div class="ins-empty">ไม่มีข้อมูล</div><?php else: ?>
                    <div class="chart-wrap" style="height:<?= max(150, count($charts['staff']['labels']) * 34 + 20) ?>px"><canvas id="cStaff" aria-label="กราฟผลงานเซลล์"></canvas></div><?php endif; ?></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>การใช้ห้อง</h2><span class="hint">จำนวนงานต่อห้อง</span></div>
                <div class="ins-card-bd"><?php if (!$charts['room']['labels']): ?><div class="ins-empty">ไม่มีข้อมูล</div><?php else: ?>
                    <div class="chart-wrap" style="height:<?= max(150, count($charts['room']['labels']) * 34 + 20) ?>px"><canvas id="cRoom" aria-label="กราฟการใช้ห้อง"></canvas></div><?php endif; ?></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12">
            <div class="ins-card">
                <div class="ins-card-hd"><h2>ขนาดงาน</h2><span class="hint">จำนวนงานแยกตามจำนวนคน</span></div>
                <div class="ins-card-bd"><?php if (!$c): ?><div class="ins-empty">ไม่มีข้อมูล</div><?php else: ?>
                    <div class="chart-wrap" style="height:170px"><canvas id="cSize" aria-label="กราฟขนาดงาน"></canvas></div><?php endif; ?></div>
            </div>
        </div>
    </div>

    <!-- ===== งาน 14 วัน + ใบเสนอราคาค้าง ===== -->
    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="ins-card" id="dashAgenda">
                <div class="ins-card-hd"><h2>งาน 14 วันข้างหน้า</h2><span class="hint"><?= count($upcoming) ?> งาน</span><a href="calendar.php" class="act">เปิดปฏิทิน</a></div>
                <div class="ins-card-bd pt-0">
                    <?php if (!$agenda): ?>
                        <div class="ins-empty">ยังไม่มีงานใน 14 วันข้างหน้า</div>
                    <?php else: ?>
                        <ul class="agenda">
                            <?php foreach ($agenda as $d => $evs): $t = strtotime($d); ?>
                                <li>
                                    <div class="day <?= $d === $today ? 'is-today' : '' ?>">
                                        <small><?= $d === $today ? 'วันนี้' : RPT_TH_DAYS[(int) date('w', $t)] ?></small>
                                        <b><?= date('j', $t) ?></b>
                                        <small><?= RPT_TH_MONTHS_SHORT[(int) date('n', $t)] ?></small>
                                    </div>
                                    <div>
                                        <?php foreach ($evs as $e): ?>
                                            <a class="ev" href="view.php?id=<?= $e['id'] ?>">
                                                <span class="ev-time"><?= $h($e['time']) ?></span>
                                                <span class="ev-main">
                                                    <span class="ev-name"><?= $h($e['name']) ?></span>
                                                    <span class="ev-meta">
                                                        <?php if ($e['approve'] === 0): ?><span class="pill warn">รออนุมัติ</span><?php endif; ?>
                                                        <?php if ($e['approve'] === 1 && $e['deposit'] <= 0): ?><span class="pill">ยังไม่มีมัดจำ</span><?php endif; ?>
                                                        <?= $h($e['room']) ?> · <?= number_format($e['pax']) ?> คน · <?= $h($e['staff']) ?>
                                                    </span>
                                                </span>
                                                <span class="ev-amt"><?= $baht($e['total']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="ins-card">
                <div class="ins-card-hd">
                    <h2>ใบเสนอราคารอลูกค้าตัดสินใจ</h2>
                    <span class="hint"><?= count($open_deals) ?> ดีล · <?= $baht(array_sum(array_column($open_deals, 'total'))) ?></span>
                    <a href="sales_dept.php" class="act">รายงานการขาย</a>
                </div>
                <div class="ins-card-bd pt-0">
                    <?php if (!$open_deals): ?>
                        <div class="ins-empty">ไม่มีใบเสนอราคาค้าง</div>
                    <?php else: ?>
                        <table class="ins-tbl">
                            <thead><tr><th>วันจัดงาน</th><th>งาน / ลูกค้า</th><th class="r">มูลค่า</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($open_deals, 0, 7) as $d): $days = (int) round((strtotime($d['date']) - strtotime($today)) / 86400); ?>
                                    <tr>
                                        <td class="text-nowrap"><?= rpt_th_date($d['date'], false) ?><span class="sm" style="<?= $days < 14 ? 'color:var(--danger)' : '' ?>">อีก <?= $days ?> วัน</span></td>
                                        <td><a href="quotation_view.php?id=<?= $d['id'] ?>"><?= $h($d['name']) ?></a><span class="sm"><?= $h($d['customer']) ?> · <?= $h($d['staff']) ?></span></td>
                                        <td class="r fw-semibold"><?= $baht($d['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ตารางรายงาน ===== -->
    <?php if ($mode === 'day'): ?>
        <div class="ins-card mb-3">
            <div class="ins-card-hd"><h2>รายการงานของวัน</h2><span class="hint"><?= count($all) ?> งาน (รวมรออนุมัติและยกเลิก)</span></div>
            <div class="ins-card-bd pt-0 table-responsive">
                <?php if (!$all): ?>
                    <div class="ins-empty">ไม่มีงานในวันนี้</div>
                <?php else: ?>
                    <table class="ins-tbl">
                        <thead><tr><th>เวลา</th><th>งาน</th><th>ห้อง</th><th>เซลล์</th><th class="c">คน</th><th class="r">มัดจำ</th><th class="r">มูลค่า</th><th>สถานะ</th></tr></thead>
                        <tbody>
                            <?php foreach ($all as $e): [$st, $sc] = $status_th($e); ?>
                                <tr class="<?= $sc === 'crit' ? 'is-muted' : '' ?>">
                                    <td class="text-nowrap num"><?= $h($e['time']) ?></td>
                                    <td><a href="view.php?id=<?= $e['id'] ?>"><?= $h($e['name']) ?></a><span class="sm"><?= $h($e['customer']) ?> · <?= $h($e['type']) ?></span></td>
                                    <td><?= $h($e['room']) ?></td>
                                    <td><?= $h($e['staff']) ?></td>
                                    <td class="c"><?= number_format($e['pax']) ?></td>
                                    <td class="r"><?= $e['deposit'] > 0 ? $baht($e['deposit']) : '–' ?></td>
                                    <td class="r fw-semibold"><?= $baht($e['total']) ?></td>
                                    <td><span class="pill <?= $sc ?>"><?= $st ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="4">รวมงานที่ยืนยัน</td><td class="c"><?= number_format($sum['pax']) ?></td><td></td><td class="r"><?= $baht($sum['revenue']) ?></td><td></td></tr></tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="ins-card mb-3">
            <div class="ins-card-hd"><h2>รายงาน<?= $mode === 'month' ? 'แยกรายวัน' : 'แยกรายเดือน' ?></h2><span class="hint">กดแถวเพื่อดูรายการงาน</span></div>
            <div class="ins-card-bd pt-0 table-responsive">
                <table class="ins-tbl">
                    <thead><tr>
                        <th><?= $mode === 'month' ? 'วันที่' : 'เดือน' ?></th><th class="c">งาน</th><th class="c">คน</th><th class="r">รายได้</th>
                        <th class="r">มัดจำที่รับ</th><th class="c">ยกเลิก</th>
                    </tr></thead>
                    <tbody>
                        <?php $ix = 0; foreach ($buckets as $k => $rows):
                            $rev = $trend['values'][$ix];
                            $n = $trend['counts'][$ix];
                            $nc = count($trend_cancel[$k] ?? []);
                            $dp = $trend_dep[$k] ?? 0;
                            $wk = $mode === 'month' && in_array((int) date('w', strtotime($k)), [0, 6]);
                            $plabel = $mode === 'month' ? RPT_TH_DAYS[(int) date('w', strtotime($k))] . ' ' . $trend['labels'][$ix] : RPT_TH_MONTHS[$ix + 1]; ?>
                            <tr class="is-click <?= !$n && !$nc && !$dp ? 'is-muted' : '' ?> <?= $wk ? 'weekend' : '' ?>" tabindex="0"
                                data-drill="b_<?= $k ?>" data-title="<?= $h($trend['titles'][$ix]) ?>" data-link="<?= $h($trend['links'][$ix]) ?>"
                                data-link-text="<?= $mode === 'month' ? 'ดูรายวัน' : 'ดูรายเดือน' ?>">
                                <td class="text-nowrap"><?= $plabel ?></td>
                                <td class="c"><?= $n ?: '–' ?></td>
                                <td class="c"><?= ($p = array_sum(array_column($rows, 'pax'))) ? number_format($p) : '–' ?></td>
                                <td class="r fw-semibold"><?= $rev ? $baht($rev) : '–' ?></td>
                                <td class="r"><?= $dp ? $baht($dp) : '–' ?></td>
                                <td class="c" style="<?= $nc ? 'color:var(--danger)' : '' ?>"><?= $nc ?: '–' ?></td>
                            </tr>
                        <?php $ix++; endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>รวม</td><td class="c"><?= $sum['count'] ?></td><td class="c"><?= number_format($sum['pax']) ?></td><td class="r"><?= $baht($sum['revenue']) ?></td><td class="r"><?= $baht($dep_total) ?></td><td class="c"><?= $cancel['count'] ?></td></tr></tfoot>
                </table>
            </div>
        </div>

        <?php if ($c): ?>
            <details class="ins-card mb-3">
                <summary class="ins-card-hd" style="cursor:pointer">
                    <h2>รายการงานที่ยืนยันทั้งหมด</h2><span class="hint"><?= $sum['count'] ?> งาน · กดเพื่อแสดง/ซ่อน</span>
                </summary>
                <div class="ins-card-bd pt-0 table-responsive">
                    <table class="ins-tbl">
                        <thead><tr><th>วันที่</th><th>งาน</th><th>ห้อง</th><th>เซลล์</th><th class="c">คน</th><th class="r">มัดจำ</th><th class="r">มูลค่า</th></tr></thead>
                        <tbody>
                            <?php foreach ($c as $e): ?>
                                <tr>
                                    <td class="text-nowrap"><?= rpt_th_date($e['date']) ?><span class="sm"><?= $h($e['time']) ?></span></td>
                                    <td><a href="view.php?id=<?= $e['id'] ?>"><?= $h($e['name']) ?></a><span class="sm"><?= $h($e['customer']) ?> · <?= $h($e['type']) ?></span></td>
                                    <td><?= $h($e['room']) ?></td>
                                    <td><?= $h($e['staff']) ?></td>
                                    <td class="c"><?= number_format($e['pax']) ?></td>
                                    <td class="r"><?= $e['deposit'] > 0 ? $baht($e['deposit']) : '–' ?></td>
                                    <td class="r fw-semibold"><?= $baht($e['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <p class="text-muted small mb-0">
        รายได้ = มูลค่างานที่ยืนยันแล้ว นับตามวันจัดงาน · มัดจำ = เงินที่บันทึกรับในหน้าการเงิน นับตามวันที่รับเงิน ·
        ปิดการขาย = ใบเสนอราคาที่เริ่มเสนอในช่วงนี้ แล้วกลายเป็นงานที่ยืนยัน (ใบที่แก้ไขหลายครั้งนับเป็น 1)
    </p>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="insDrawer" aria-labelledby="insDrawerTitle">
    <div class="offcanvas-header">
        <div><h5 class="offcanvas-title" id="insDrawerTitle"></h5><div class="d-sum"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="ปิด"></button>
    </div>
    <div class="offcanvas-body"></div>
    <div class="d-foot d-none"></div>
</div>

<?php
$staff_pct = [];
foreach ($charts['staff']['labels'] as $lb) {
    $tg = $staff_targets[$staff_ids[$lb] ?? 0] ?? 0;
    $rev = 0;
    foreach ($c as $e) if ($e['staff'] === $lb) $rev += $e['total'];
    $staff_pct[] = $tg > 0 ? round($rev / $tg * 100) : null;
}
?>
<script>
window.INS_DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.INS_CHARTS = <?= json_encode([
    'label' => $label, 'trend' => $trend, 'cum' => $cum, 'funnel' => $funnel, 'staffPct' => $staff_pct,
    'type' => $charts['type'], 'company' => $charts['company'], 'period' => $charts['period'],
    'staff' => $charts['staff'], 'room' => $charts['room'], 'size' => $charts['size'],
    'prevWord' => $prev_word,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="assets/js/insights.js?v=<?= @filemtime(__DIR__ . '/assets/js/insights.js') ?>"></script>
<script src="assets/js/dashboard_charts.js?v=<?= @filemtime(__DIR__ . '/assets/js/dashboard_charts.js') ?>"></script>

<?php include "footer.php"; ?>
