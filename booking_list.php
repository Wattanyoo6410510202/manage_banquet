<?php
/* =========================================================
   booking_list.php — รวมรายการจองห้องประชุม (room_bookings)
   ค้นหา / กรอง / เรียงลำดับ / ส่งออก CSV / ยกเลิกการจอง
   ========================================================= */

/* php.ini ของ XAMPP ตั้ง timezone เป็น Europe/Berlin ซึ่งช้ากว่าไทย 5-6 ชม.
   ต้องบังคับให้ตรงกับ NOW()/CURDATE() ของ MySQL ไม่งั้นช่วงเที่ยงคืน-ตี 6
   คำว่า "วันนี้" ของ PHP จะกลายเป็นเมื่อวาน */
date_default_timezone_set('Asia/Bangkok');

include "config.php";

/* ---------- สิทธิ์การเข้าถึง ----------
   ต้องเช็คตรงนี้ก่อน include header.php เพราะสาขา CSV/JSON ห้ามมี HTML นำหน้า */
if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'staff', 'gm', 'sale', 'procurement', 'manager'])) {
    echo "<script>window.location.href='access_denied.php';</script>";
    exit;
}
$can_cancel = in_array($role, ['admin', 'gm']); // ให้ตรงกับสิทธิ์ลบใน room_calendar.php

/* ---------- API: ยกเลิกการจอง ---------- */
if (($_POST['action'] ?? '') === 'cancel') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    if (!$can_cancel) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ยกเลิกการจอง'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // ยกเลิกเฉพาะรายการที่ยัง active อยู่ กันกดซ้ำ
    $affected = db_execute($conn, "UPDATE room_bookings SET status='cancelled', updated_at=NOW() WHERE id=? AND status='active'", "i", $id);
    echo json_encode(
        $affected > 0
            ? ['status' => 'success']
            : ['status' => 'error', 'message' => 'รายการนี้ถูกยกเลิกไปแล้ว หรือไม่พบข้อมูล'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ---------- ตัวกรอง ---------- */
$q          = trim($_GET['q'] ?? '');
$f_room     = intval($_GET['room_id'] ?? 0);
$f_company  = intval($_GET['company_id'] ?? 0);
$f_type     = intval($_GET['type_id'] ?? 0);
$f_status   = $_GET['status'] ?? 'all';
$date_from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$date_to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
if (!in_array($f_status, ['all', 'active', 'cancelled'])) $f_status = 'all';

// เรียงลำดับ — ใช้ whitelist เท่านั้น ห้ามรับชื่อคอลัมน์จาก URL ตรงๆ
$sort_map = [
    'start_time'   => 'rb.start_time',
    'booking_code' => 'rb.booking_code',
    'event_name'   => 'rb.event_name',
    'room_name'    => 'mr.room_name',
    'pax'          => 'rb.pax',
    'status'       => 'rb.status',
    'created_at'   => 'rb.created_at',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sort_map) ? $_GET['sort'] : 'start_time';
$dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

/* ---------- ประกอบเงื่อนไขแบบ prepared statement ---------- */
$where = ["1=1"];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = "(rb.booking_code LIKE ? OR rb.event_name LIKE ? OR rb.booking_name LIKE ?
                 OR rb.phone LIKE ? OR rb.organization LIKE ? OR rb.remark LIKE ?)";
    $like = '%' . $q . '%';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($f_room > 0)    { $where[] = "rb.room_id = ?";          $types .= 'i'; $params[] = $f_room; }
if ($f_company > 0) { $where[] = "rb.company_id = ?";       $types .= 'i'; $params[] = $f_company; }
if ($f_type > 0)    { $where[] = "rb.function_type_id = ?"; $types .= 'i'; $params[] = $f_type; }
if ($f_status !== 'all') { $where[] = "rb.status = ?";      $types .= 's'; $params[] = $f_status; }
if ($date_from !== '')   { $where[] = "DATE(rb.start_time) >= ?"; $types .= 's'; $params[] = $date_from; }
if ($date_to !== '')     { $where[] = "DATE(rb.start_time) <= ?"; $types .= 's'; $params[] = $date_to; }

$where_sql = implode(' AND ', $where);

$base_sql = "SELECT rb.*, mr.room_name, c.company_name, ft.type_name
             FROM room_bookings rb
             LEFT JOIN meeting_rooms mr ON rb.room_id = mr.id
             LEFT JOIN companies c      ON rb.company_id = c.id
             LEFT JOIN function_types ft ON rb.function_type_id = ft.id
             WHERE $where_sql
             ORDER BY {$sort_map[$sort]} $dir, rb.id DESC";

$rows = db_fetch_all($conn, $base_sql, $types, ...$params);

/* ---------- ส่งออก CSV (ใช้ตัวกรองชุดเดียวกับที่เห็นบนหน้าจอ) ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    if (ob_get_length()) ob_clean();
    $filename = 'booking_list_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM — ให้ Excel อ่านภาษาไทยไม่เป็นต่างด้าว
    fputcsv($out, ['รหัสจอง', 'ชื่องาน', 'ประเภท', 'โรงแรม', 'ห้อง', 'เริ่ม', 'สิ้นสุด',
                   'จำนวนคน', 'ผู้จอง', 'เบอร์โทร', 'หน่วยงาน', 'สถานะ', 'ผู้บันทึก', 'บันทึกเมื่อ', 'หมายเหตุ']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['booking_code'], $r['event_name'], $r['type_name'], $r['company_name'], $r['room_name'],
            $r['start_time'], $r['end_time'], $r['pax'], $r['booking_name'], $r['phone'],
            $r['organization'], ($r['status'] === 'active' ? 'ใช้งาน' : 'ยกเลิก'),
            $r['created_by'], $r['created_at'], $r['remark'],
        ]);
    }
    fclose($out);
    exit;
}

/* ---------- ข้อมูลสำหรับ dropdown ---------- */
$rooms_opt   = db_fetch_all($conn, "SELECT mr.id, mr.room_name, c.company_name FROM meeting_rooms mr LEFT JOIN companies c ON mr.company_id=c.id ORDER BY c.company_name, mr.room_name");
$comp_opt    = db_fetch_all($conn, "SELECT id, company_name FROM companies ORDER BY company_name");
$type_opt    = db_fetch_all($conn, "SELECT id, type_name FROM function_types ORDER BY id");

/* ---------- สรุปยอด (นับจากผลลัพธ์หลังกรองแล้ว) ---------- */
$sum_total     = count($rows);
$sum_active    = 0;
$sum_cancelled = 0;
$sum_pax       = 0;
$sum_upcoming  = 0;
$now_ts = time();
foreach ($rows as $r) {
    if ($r['status'] === 'active') {
        $sum_active++;
        $sum_pax += (int)$r['pax'];
        if (strtotime($r['start_time']) >= $now_ts) $sum_upcoming++;
    } else {
        $sum_cancelled++;
    }
}

/* ---------- helper ---------- */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

if (!function_exists('thai_dt')) {
    function thai_dt($dt, $with_time = true)
    {
        static $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        if (!$dt || $dt === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($dt);
        if (!$ts) return '-';
        $s = date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . (date('Y', $ts) + 543);
        return $with_time ? $s . ' ' . date('H:i', $ts) : $s;
    }
}

// ลิงก์เรียงลำดับ — คงตัวกรองเดิมไว้ สลับเฉพาะทิศทาง
if (!function_exists('sort_link')) {
function sort_link($key, $cur_sort, $cur_dir)
{
    $qs = $_GET;
    $qs['sort'] = $key;
    $qs['dir'] = ($cur_sort === $key && strtoupper($cur_dir) === 'ASC') ? 'desc' : 'asc';
    unset($qs['export']);
    return '?' . http_build_query($qs);
}
function sort_icon($key, $cur_sort, $cur_dir)
{
    if ($cur_sort !== $key) return '<i class="bi bi-arrow-down-up text-muted opacity-25"></i>';
    return strtoupper($cur_dir) === 'ASC'
        ? '<i class="bi bi-sort-up text-gold"></i>'
        : '<i class="bi bi-sort-down text-gold"></i>';
}
}

// query string สำหรับปุ่มส่งออก
$export_qs = $_GET;
$export_qs['export'] = 'csv';

require_once "header.php";
?>

<style>
.bk-page{--gold:#b89441;--gold-tint:#f7f1e3;--ink:#111318;--ink2:#5b6470;--muted:#8a9099;--line:#e8eaee}
.bk-page{font-family:'Sarabun','Inter',sans-serif;color:var(--ink)}
.bk-hero{border-radius:16px;padding:15px 20px;color:var(--ink);position:relative;overflow:hidden;
    background:#fff;border:1px solid var(--line);border-left:4px solid var(--gold)}
.bk-hero::after{content:'';position:absolute;inset:0;pointer-events:none;
    background:radial-gradient(520px 200px at 92% -40%,rgba(184,148,65,.10),transparent 70%)}
.bk-hero>*{position:relative;z-index:1}
.bk-hero .hero-sub{font-size:.78rem;color:var(--ink2)}
.bk-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 14px;height:100%}
.bk-stat .v{font-size:1.45rem;font-weight:700;line-height:1.15;font-variant-numeric:tabular-nums}
.bk-stat .l{font-size:.7rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.bk-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
.bk-filter{padding:14px 16px;border-bottom:1px solid var(--line);background:#fff}
.bk-filter label{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:3px;display:block}
.bk-filter .form-control,.bk-filter .form-select{font-size:.82rem;border-radius:9px}
.btn-gold{background:var(--gold);border:1px solid var(--gold);color:#fff;font-weight:600;font-size:.8rem;border-radius:9px}
.btn-gold:hover{background:#a5833a;border-color:#a5833a;color:#fff}
.bk-tbl{width:100%;margin:0;font-size:.82rem}
.bk-tbl thead th{background:#fafbfc;color:var(--muted);font-weight:700;font-size:.68rem;text-transform:uppercase;
    letter-spacing:.4px;border-bottom:1px solid var(--line)!important;white-space:nowrap;padding:10px 12px}
.bk-tbl thead th a{color:var(--muted);text-decoration:none}
.bk-tbl thead th a:hover{color:var(--ink)}
.bk-tbl tbody td{padding:10px 12px;border-bottom:1px solid #f0f2f5;vertical-align:middle}
.bk-tbl tbody tr:last-child td{border-bottom:0}
.bk-tbl tbody tr:hover{background:#f8f9fb}
.bk-tbl tbody tr.is-cancelled{color:var(--muted)}
.bk-tbl tbody tr.is-cancelled .bk-name{text-decoration:line-through}
.bk-tbl .num{font-variant-numeric:tabular-nums}
.bk-code{font-family:'Inter',monospace;font-size:.74rem;font-weight:700;color:#8a6c22;background:var(--gold-tint);
    border-radius:6px;padding:2px 7px;white-space:nowrap}
.bk-name{font-weight:600}
.tag{display:inline-block;background:#f1f3f6;color:var(--ink2);border-radius:6px;padding:1px 7px;font-size:.7rem;font-weight:600}
.pill{display:inline-flex;align-items:center;gap:4px;border-radius:99px;padding:2px 9px;font-size:.7rem;font-weight:700}
.pill.on{background:#e9f7e9;color:#0ca30c}
.pill.off{background:#fbeaea;color:#d03b3b}
.pill.done{background:#eef1f6;color:#5b6470}
.bk-empty{padding:52px 20px;text-align:center;color:var(--muted)}
.chipbar{display:flex;gap:6px;flex-wrap:wrap}
.chipbar a{border:1px solid var(--line);background:#fff;color:var(--ink2);border-radius:999px;
    padding:5px 14px;font-size:.78rem;font-weight:600;text-decoration:none}
.chipbar a:hover{border-color:#d3d7dd;color:var(--ink)}
.chipbar a.on{background:var(--gold-tint);border-color:var(--gold);color:#8a6c22}
@media(max-width:575px){.bk-stat .v{font-size:1.2rem}}
</style>

<div class="bk-page">

    <!-- ===== HERO ===== -->
    <div class="bk-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-journal-bookmark-fill me-2 text-gold"></i>รวมรายการจองห้องประชุม</h5>
                <div class="hero-sub">
                    ทุกการจองที่บันทึกไว้ในระบบ ทั้งที่ใช้งานอยู่และที่ยกเลิกแล้ว
                    <?php if ($sum_total > 0): ?>· พบ <?= number_format($sum_total) ?> รายการ<?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= $h('?' . http_build_query($export_qs)) ?>" class="btn btn-gold btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>ส่งออก Excel
                </a>
                <a href="room_calendar.php" class="btn btn-outline-secondary btn-sm" style="font-size:.8rem;border-radius:9px">
                    <i class="bi bi-calendar-plus me-1"></i>จองห้องใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- ===== STAT ===== -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ทั้งหมด</div><div class="v"><?= number_format($sum_total) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ใช้งานอยู่</div><div class="v" style="color:#0ca30c"><?= number_format($sum_active) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ยกเลิกแล้ว</div><div class="v" style="color:#d03b3b"><?= number_format($sum_cancelled) ?></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="bk-stat"><div class="l">ยังไม่ถึงวันจัด</div><div class="v" style="color:#2a78d6"><?= number_format($sum_upcoming) ?></div>
                <div style="font-size:.7rem;color:#8a9099">รวม <?= number_format($sum_pax) ?> คน</div></div>
        </div>
    </div>

    <div class="bk-card">
        <!-- ===== FILTER ===== -->
        <form method="GET" class="bk-filter" id="bkFilter">
            <input type="hidden" name="sort" value="<?= $h($sort) ?>">
            <input type="hidden" name="dir" value="<?= $h(strtolower($dir)) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label>ค้นหา</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= $h($q) ?>" class="form-control"
                               placeholder="รหัสจอง / ชื่องาน / ผู้จอง / เบอร์โทร">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>ห้องประชุม</label>
                    <select name="room_id" class="form-select form-select-sm">
                        <option value="0">ทุกห้อง</option>
                        <?php foreach ($rooms_opt as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $f_room == $r['id'] ? 'selected' : '' ?>><?= $h($r['room_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>โรงแรม</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach ($comp_opt as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $f_company == $c['id'] ? 'selected' : '' ?>><?= $h($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label>ประเภทงาน</label>
                    <select name="type_id" class="form-select form-select-sm">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach ($type_opt as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $f_type == $t['id'] ? 'selected' : '' ?>><?= $h($t['type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-12">
                    <label>ช่วงวันจัดงาน</label>
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" name="from" value="<?= $h($date_from) ?>" class="form-control form-control-sm">
                        <span class="text-muted small">ถึง</span>
                        <input type="date" name="to" value="<?= $h($date_to) ?>" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="chipbar">
                    <?php
                    $status_tabs = ['all' => 'ทั้งหมด', 'active' => 'ใช้งานอยู่', 'cancelled' => 'ยกเลิกแล้ว'];
                    foreach ($status_tabs as $k => $lb):
                        $qs = $_GET; $qs['status'] = $k; unset($qs['export']);
                    ?>
                        <a href="?<?= $h(http_build_query($qs)) ?>" class="<?= $f_status === $k ? 'on' : '' ?>"><?= $lb ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-gold btn-sm px-3"><i class="bi bi-funnel me-1"></i>กรองข้อมูล</button>
                    <a href="booking_list.php" class="btn btn-outline-secondary btn-sm" style="font-size:.8rem;border-radius:9px">ล้างตัวกรอง</a>
                </div>
            </div>
        </form>

        <!-- ===== TABLE ===== -->
        <?php if ($sum_total === 0): ?>
            <div class="bk-empty">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                ไม่พบรายการจองที่ตรงกับเงื่อนไข
                <div class="mt-2"><a href="booking_list.php" class="btn btn-sm btn-outline-secondary">ล้างตัวกรอง</a></div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="bk-tbl">
                    <thead>
                        <tr>
                            <th><a href="<?= $h(sort_link('booking_code', $sort, $dir)) ?>">รหัสจอง <?= sort_icon('booking_code', $sort, $dir) ?></a></th>
                            <th><a href="<?= $h(sort_link('event_name', $sort, $dir)) ?>">ชื่องาน <?= sort_icon('event_name', $sort, $dir) ?></a></th>
                            <th><a href="<?= $h(sort_link('room_name', $sort, $dir)) ?>">ห้อง <?= sort_icon('room_name', $sort, $dir) ?></a></th>
                            <th><a href="<?= $h(sort_link('start_time', $sort, $dir)) ?>">วันเวลาจัดงาน <?= sort_icon('start_time', $sort, $dir) ?></a></th>
                            <th class="text-center"><a href="<?= $h(sort_link('pax', $sort, $dir)) ?>">คน <?= sort_icon('pax', $sort, $dir) ?></a></th>
                            <th>ผู้จอง</th>
                            <th><a href="<?= $h(sort_link('status', $sort, $dir)) ?>">สถานะ <?= sort_icon('status', $sort, $dir) ?></a></th>
                            <th class="text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $is_cancelled = $r['status'] !== 'active';
                            $is_past = strtotime($r['start_time']) < $now_ts;
                        ?>
                            <tr class="<?= $is_cancelled ? 'is-cancelled' : '' ?>" data-id="<?= $r['id'] ?>">
                                <td><span class="bk-code"><?= $h($r['booking_code'] ?: '-') ?></span></td>
                                <td>
                                    <div class="bk-name text-truncate" style="max-width:220px"><?= $h($r['event_name'] ?: '(ไม่ระบุชื่องาน)') ?></div>
                                    <span class="tag"><?= $h($r['type_name'] ?: 'ไม่ระบุประเภท') ?></span>
                                </td>
                                <td>
                                    <div><?= $h($r['room_name'] ?: '-') ?></div>
                                    <div style="font-size:.72rem;color:#8a9099"><?= $h($r['company_name'] ?: '-') ?></div>
                                </td>
                                <td class="num">
                                    <div><?= thai_dt($r['start_time']) ?></div>
                                    <div style="font-size:.72rem;color:#8a9099">ถึง <?= thai_dt($r['end_time']) ?></div>
                                </td>
                                <td class="text-center num"><?= $r['pax'] > 0 ? number_format($r['pax']) : '-' ?></td>
                                <td>
                                    <div><?= $h($r['booking_name'] ?: '-') ?></div>
                                    <?php if (!empty($r['phone'])): ?>
                                        <div style="font-size:.72rem;color:#8a9099"><i class="bi bi-telephone me-1"></i><?= $h($r['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_cancelled): ?>
                                        <span class="pill off"><i class="bi bi-x-circle-fill"></i>ยกเลิก</span>
                                    <?php elseif ($is_past): ?>
                                        <span class="pill done"><i class="bi bi-check2"></i>ผ่านไปแล้ว</span>
                                    <?php else: ?>
                                        <span class="pill on"><i class="bi bi-check-circle-fill"></i>ใช้งานอยู่</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 btn-detail"
                                            data-id="<?= $r['id'] ?>" title="ดูรายละเอียด">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($can_cancel && !$is_cancelled): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-cancel"
                                                data-id="<?= $r['id'] ?>" data-name="<?= $h($r['event_name']) ?>" title="ยกเลิกการจอง">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-top:1px solid #e8eaee;font-size:.76rem;color:#5b6470">
                <span>แสดง <b><?= number_format($sum_total) ?></b> รายการ</span>
                <span class="text-muted">คลิกหัวตารางเพื่อเรียงลำดับ</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL: รายละเอียด ===== -->
<div class="modal fade" id="bkDetail" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden">
            <div class="modal-header py-2" style="background:#16181d;color:#fff;border:0">
                <h6 class="modal-title fw-bold" style="font-size:.9rem">
                    <i class="bi bi-journal-text me-2" style="color:#b89441"></i><span id="bkDetailTitle">รายละเอียดการจอง</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bkDetailBody"></div>
        </div>
    </div>
</div>

<!-- ===== MODAL: ยืนยันยกเลิก ===== -->
<div class="modal fade" id="bkCancel" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:0;border-radius:16px">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill fs-1" style="color:#fab219"></i>
                <h6 class="fw-bold mt-3 mb-1">ยืนยันยกเลิกการจอง?</h6>
                <p class="text-muted mb-1" style="font-size:.82rem" id="bkCancelName"></p>
                <p class="text-muted mb-3" style="font-size:.76rem">ห้องจะถูกปล่อยว่างให้จองใหม่ได้ทันที</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill btn-sm" data-bs-dismiss="modal">ไม่ใช่ตอนนี้</button>
                    <button type="button" class="btn btn-danger flex-fill btn-sm" id="bkCancelConfirm">ยกเลิกการจอง</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BK_ROWS = <?= json_encode(array_column($rows, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const BK_CAN_CANCEL = <?= $can_cancel ? 'true' : 'false' ?>;

const esc = s => String(s ?? '').replace(/[&<>"']/g, m =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

const THAI_M = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thaiDT(s) {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${THAI_M[d.getMonth()]} ${d.getFullYear() + 543} ${hh}:${mm}`;
}

/* ---------- รายละเอียด ---------- */
document.querySelectorAll('.btn-detail').forEach(btn => {
    btn.addEventListener('click', () => {
        const r = BK_ROWS[btn.dataset.id];
        if (!r) return;
        document.getElementById('bkDetailTitle').textContent = r.booking_code || 'รายละเอียดการจอง';

        const isCancelled = r.status !== 'active';
        const field = (label, val, wide) => `
            <div class="${wide ? 'col-12' : 'col-sm-6'} mb-3">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#8a9099;font-weight:700">${label}</div>
                <div style="font-size:.88rem">${val && String(val).trim() !== '' ? esc(val) : '<span class="text-muted">-</span>'}</div>
            </div>`;

        document.getElementById('bkDetailBody').innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-3 pb-3" style="border-bottom:1px solid #e8eaee">
                <span class="pill ${isCancelled ? 'off' : 'on'}">
                    <i class="bi ${isCancelled ? 'bi-x-circle-fill' : 'bi-check-circle-fill'}"></i>
                    ${isCancelled ? 'ยกเลิกแล้ว' : 'ใช้งานอยู่'}
                </span>
                <span class="fw-bold">${esc(r.event_name || '(ไม่ระบุชื่องาน)')}</span>
            </div>
            <div class="row">
                ${field('ห้องประชุม', r.room_name)}
                ${field('โรงแรม', r.company_name)}
                ${field('เริ่มงาน', thaiDT(r.start_time))}
                ${field('สิ้นสุด', thaiDT(r.end_time))}
                ${field('ประเภทงาน', r.type_name)}
                ${field('จำนวนคน', r.pax > 0 ? Number(r.pax).toLocaleString('th-TH') + ' คน' : '')}
                ${field('ผู้จอง', r.booking_name)}
                ${field('เบอร์โทร', r.phone)}
                ${field('หน่วยงาน', r.organization, true)}
                ${field('หมายเหตุ', r.remark, true)}
                ${field('ผู้บันทึก', r.created_by)}
                ${field('บันทึกเมื่อ', thaiDT(r.created_at))}
            </div>`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bkDetail')).show();
    });
});

/* ---------- ยกเลิกการจอง ---------- */
let bkPendingId = null;
document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', () => {
        bkPendingId = btn.dataset.id;
        document.getElementById('bkCancelName').textContent = btn.dataset.name || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bkCancel')).show();
    });
});

document.getElementById('bkCancelConfirm')?.addEventListener('click', function () {
    if (!bkPendingId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('id', bkPendingId);

    fetch('booking_list.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message || 'ยกเลิกไม่สำเร็จ');
                btn.disabled = false;
                btn.textContent = 'ยกเลิกการจอง';
            }
        })
        .catch(() => {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            btn.disabled = false;
            btn.textContent = 'ยกเลิกการจอง';
        });
});
</script>

<?php include "footer.php"; ?>
