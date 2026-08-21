<?php
/* php.ini ของ XAMPP ตั้ง date.timezone เป็น Europe/Berlin ซึ่งช้ากว่าไทย 5-6 ชม.
   ถ้าไม่ตั้งตรงนี้ date('Y-m-d') จะยังเป็น "เมื่อวาน" ในช่วงเที่ยงคืน-ตี 6 ตามเวลาไทย
   ทำให้การ์ด "รายได้วันนี้" และค่าเริ่มต้นของเดือน/ปี ดึงข้อมูลผิดช่วง
   (ฝั่ง MySQL ใช้ NOW()/CURDATE() ซึ่งเป็นเวลาไทยอยู่แล้ว จึงต้องบังคับให้ PHP ตรงกัน) */
date_default_timezone_set('Asia/Bangkok');

include "config.php";
include "header.php";

$role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'staff', 'gm', 'sale', 'procurement', 'manager'];
if (!in_array($role, $allowed_roles)) {
    echo "<script>window.location.href='login.php?error=access_denied';</script>";
    exit;
}

$today = date('Y-m-d');
$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : $today;
$selected_month = isset($_GET['month']) ? max(1, min(12, intval($_GET['month']))) : intval(date('m'));
$selected_year = isset($_GET['year']) ? max(date('Y')-2, min(date('Y')+1, intval($_GET['year']))) : intval(date('Y'));
$selected_company = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$selected_staff = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

$company_filter = $selected_company > 0 ? "AND f.company_id = $selected_company" : "";
$company_filter_plain = $selected_company > 0 ? "AND company_id = $selected_company" : "";
$staff_filter = $selected_staff > 0 ? "AND f.created_by_id = $selected_staff" : "";
$staff_filter_plain = $selected_staff > 0 ? "AND created_by_id = $selected_staff" : "";
$staff_filter_user = $selected_staff > 0 ? "AND u.id = $selected_staff" : "";

$month_start = "$selected_year-" . str_pad($selected_month, 2, '0', STR_PAD_LEFT) . "-01";
$month_end = date('Y-m-t', strtotime($month_start));

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$staff_list = $conn->query("SELECT id, name FROM users WHERE role IN ('Staff','Admin','Sale','Manager','GM') ORDER BY name ASC");

/* เขียนแบบ range (>= x AND < x+1day) แทน DATE(col)=x เพื่อให้ MySQL ใช้ index ได้ */
$mtd_filter = "AND f.start_time >= '$month_start' AND f.start_time < DATE_ADD('$month_end', INTERVAL 1 DAY)";

/* ใบเสนอราคา "ออกเดือนนี้" = นับตามวันที่ออกใบ (created_at) ให้ตรงกับคำว่า "ออก"
   และตรงฐานเดียวกับ q_today_total ที่ใช้ created_at อยู่แล้ว */
$mtd_filter_q = "AND q.created_at >= '$month_start' AND q.created_at < DATE_ADD('$month_end', INTERVAL 1 DAY)";

/* ===== 1. CORE STATS — รวมทุกตัวเลขของตาราง functions ให้เป็น QUERY เดียว =====
   เดิมยิงแยกทีละตัวเลข ~15 queries/การโหลด 1 ครั้ง ทำให้หน้าช้าเมื่อข้อมูลเยอะ */
$cond_today = "f.start_time >= '$selected_date' AND f.start_time < DATE_ADD('$selected_date', INTERVAL 1 DAY)";
$cond_mtd   = "f.start_time >= '$month_start' AND f.start_time < DATE_ADD('$month_end', INTERVAL 1 DAY)";
$cond_year  = "f.start_time >= '$selected_year-01-01' AND f.start_time < DATE_ADD('$selected_year-01-01', INTERVAL 1 YEAR)";
$ok         = "f.approve=1 AND f.status NOT IN ('Cancelled')";
$ok_active  = "f.approve=1 AND f.status NOT IN ('Cancelled','Completed')";
$core = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN $ok AND ($cond_today) THEN f.total_amount ELSE 0 END),0) AS today_rev,
    COUNT(CASE WHEN $ok AND ($cond_today) THEN 1 END) AS today_ev,
    COALESCE(SUM(CASE WHEN $ok AND ($cond_today) THEN f.pax ELSE 0 END),0) AS today_pax,
    COUNT(DISTINCT CASE WHEN $ok AND ($cond_today) AND f.room_id IS NOT NULL AND f.room_id != 0 THEN f.room_id END) AS today_rooms,
    COALESCE(SUM(CASE WHEN $ok AND ($cond_mtd) THEN f.total_amount ELSE 0 END),0) AS mtd_rev,
    COUNT(CASE WHEN $ok AND ($cond_mtd) THEN 1 END) AS mtd_ev,
    COALESCE(SUM(CASE WHEN $ok AND ($cond_mtd) THEN f.pax ELSE 0 END),0) AS mtd_pax,
    COUNT(DISTINCT CASE WHEN $ok AND ($cond_mtd) AND f.room_id IS NOT NULL AND f.room_id != 0 THEN f.room_id END) AS mtd_rooms,
    COALESCE(SUM(CASE WHEN $ok AND ($cond_year) THEN f.total_amount ELSE 0 END),0) AS year_rev,
    COUNT(CASE WHEN $ok AND ($cond_year) THEN 1 END) AS year_ev,
    COALESCE(SUM(CASE WHEN $ok_active THEN f.deposit ELSE 0 END),0) AS deposit_balance,
    COUNT(CASE WHEN $ok_active AND (f.deposit IS NULL OR f.deposit=0) THEN 1 END) AS deposit_pending,
    COUNT(CASE WHEN f.approve=1 AND f.status='Cancelled' AND ($cond_mtd) THEN 1 END) AS cancelled_mtd,
    COUNT(CASE WHEN $ok_active AND f.start_time >= NOW() AND f.start_time <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND (f.deposit IS NULL OR f.deposit=0) THEN 1 END) AS alert_no_deposit,
    COUNT(CASE WHEN f.approve=0 AND f.status NOT IN ('Cancelled') THEN 1 END) AS alert_pending
    FROM functions f WHERE 1=1 $company_filter $staff_filter")->fetch_assoc();
$today_total = $core['today_rev'];
$today_events = $core['today_ev'];
$today_pax = $core['today_pax'];
$today_rooms_used = $core['today_rooms'];
$mtd_total = $core['mtd_rev'];
$mtd_events = $core['mtd_ev'];
$mtd_pax = $core['mtd_pax'];
$mtd_rooms_used = $core['mtd_rooms'];
$year_total = $core['year_rev'];
$year_events = $core['year_ev'];
$year_avg_deal = $year_events > 0 ? round($year_total / $year_events) : 0;
$total_deposit_balance = $core['deposit_balance'];
$deposit_pending_events = $core['deposit_pending'];
$cancelled_count = $core['cancelled_mtd'];
$alert_no_deposit = $core['alert_no_deposit'];
$alert_pending_approval = $core['alert_pending'];

/* ===== 2. REVENUE BY TYPE ===== */
$type_names = []; $type_today = []; $type_mtd = [];
$rev_types = $conn->query("SELECT ft.type_name, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND f.start_time >= '$selected_date' AND f.start_time < DATE_ADD('$selected_date', INTERVAL 1 DAY) THEN f.total_amount ELSE 0 END),0) as today_rev, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND f.start_time >= '$month_start' AND f.start_time < DATE_ADD('$month_end', INTERVAL 1 DAY) THEN f.total_amount ELSE 0 END),0) as mtd_rev FROM function_types ft LEFT JOIN functions f ON f.function_type_id=ft.id $company_filter $staff_filter GROUP BY ft.id, ft.type_name ORDER BY mtd_rev DESC");
while ($rt = $rev_types->fetch_assoc()) {
    $type_names[] = $rt['type_name'];
    $type_today[] = (float)$rt['today_rev'];
    $type_mtd[] = (float)$rt['mtd_rev'];
}

/* ===== 3. REVENUE BY COMPANY ===== */
$comp_names = []; $comp_today = []; $comp_mtd = [];
$rev_comps = $conn->query("SELECT c.company_name, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND f.start_time >= '$selected_date' AND f.start_time < DATE_ADD('$selected_date', INTERVAL 1 DAY) THEN f.total_amount ELSE 0 END),0) as today_rev, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND f.start_time >= '$month_start' AND f.start_time < DATE_ADD('$month_end', INTERVAL 1 DAY) THEN f.total_amount ELSE 0 END),0) as mtd_rev FROM companies c LEFT JOIN functions f ON f.company_id=c.id $staff_filter GROUP BY c.id, c.company_name ORDER BY mtd_rev DESC");
while ($rc = $rev_comps->fetch_assoc()) {
    $comp_names[] = $rc['company_name'];
    $comp_today[] = (float)$rc['today_rev'];
    $comp_mtd[] = (float)$rc['mtd_rev'];
}

/* ===== 4. BANQUET & MEETING PERFORMANCE ===== */
$today_avg_revenue = $today_events > 0 ? round($today_total / $today_events) : 0;
$mtd_avg_revenue = $mtd_events > 0 ? round($mtd_total / $mtd_events) : 0;
/* today_rooms_used / mtd_rooms_used มาจาก $core ด้านบนแล้ว */

/* ===== 5. MEETING ROOM UTILIZATION ===== */
/* "จำนวนงานเดือนนี้" = งานที่อนุมัติแล้วและไม่ถูกยกเลิก (รวมงานที่จัดจบไปแล้วด้วย)
   เดิมตัด 'Completed' ออก ทำให้เดือนที่ผ่านมาแล้วกราฟกลายเป็น 0 ทั้งแถบ */
$room_stats = $conn->query("SELECT mr.room_name, COALESCE(SUM(CASE WHEN f.status NOT IN ('Cancelled') THEN 1 ELSE 0 END),0) as bookings FROM meeting_rooms mr LEFT JOIN functions f ON f.room_id=mr.id AND f.approve=1 $mtd_filter $company_filter $staff_filter WHERE mr.status='active' GROUP BY mr.id, mr.room_name ORDER BY bookings DESC");
$room_labels = []; $room_bookings = [];
while ($rs = $room_stats->fetch_assoc()) {
    $room_labels[] = $rs['room_name'];
    $room_bookings[] = (int)$rs['bookings'];
}

/* ===== 6. FUTURE BOOKING PIPELINE — query เดียวแบ่ง bucket 7/30/90/180 วัน ===== */
$_now = date('Y-m-d H:i:s');
$pipe = $conn->query("SELECT
    COUNT(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 7 DAY) THEN 1 END) AS c7,
    COALESCE(SUM(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 7 DAY) THEN f.total_amount ELSE 0 END),0) AS t7,
    COUNT(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 30 DAY) THEN 1 END) AS c30,
    COALESCE(SUM(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 30 DAY) THEN f.total_amount ELSE 0 END),0) AS t30,
    COUNT(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 90 DAY) THEN 1 END) AS c90,
    COALESCE(SUM(CASE WHEN f.start_time <= DATE_ADD('$_now', INTERVAL 90 DAY) THEN f.total_amount ELSE 0 END),0) AS t90,
    COUNT(*) AS c180,
    COALESCE(SUM(f.total_amount),0) AS t180
    FROM functions f WHERE $ok_active AND f.start_time >= '$_now' AND f.start_time <= DATE_ADD('$_now', INTERVAL 180 DAY) $company_filter $staff_filter")->fetch_assoc();
$pipe_7d = ['c' => $pipe['c7'], 't' => $pipe['t7']];
$pipe_30d = ['c' => $pipe['c30'], 't' => $pipe['t30']];
$pipe_90d = ['c' => $pipe['c90'], 't' => $pipe['t90']];
$pipe_180d = ['c' => $pipe['c180'], 't' => $pipe['t180']];

/* ===== 7. QUOTATION & CONVERSION — รวมทุกตัวเลขของ quotations เป็น QUERY เดียว =====
   (รวม funnel สถานะ Draft/Sent/Approved/Cancelled และ alert_overdue ที่เคยยิงแยก 14 queries) */
$_qstaff = $selected_staff > 0 ? " AND q.created_by=$selected_staff" : "";
$cond_mtd_q = "q.created_at >= '$month_start' AND q.created_at < DATE_ADD('$month_end', INTERVAL 1 DAY)";
$cond_today_q = "q.created_at >= '$selected_date' AND q.created_at < DATE_ADD('$selected_date', INTERVAL 1 DAY)";
$qc = $conn->query("SELECT
    COUNT(CASE WHEN ($cond_mtd_q) THEN 1 END) AS q_total,
    COUNT(CASE WHEN q.status='Approved' AND ($cond_mtd_q) THEN 1 END) AS q_approved,
    COUNT(CASE WHEN q.status='Cancelled' AND ($cond_mtd_q) THEN 1 END) AS q_cancelled,
    COUNT(CASE WHEN q.status='Sent' AND q.expiry_date >= CURDATE() THEN 1 END) AS q_pending,
    COUNT(CASE WHEN ($cond_today_q) THEN 1 END) AS q_today,
    SUM(CASE WHEN q.status='Draft' THEN 1 ELSE 0 END) AS pipe_prospect,
    COALESCE(SUM(CASE WHEN q.status='Draft' THEN q.grand_total ELSE 0 END),0) AS pipe_prospect_val,
    SUM(CASE WHEN q.status='Sent' THEN 1 ELSE 0 END) AS pipe_quotation,
    COALESCE(SUM(CASE WHEN q.status='Sent' THEN q.grand_total ELSE 0 END),0) AS pipe_quotation_val,
    SUM(CASE WHEN q.status='Approved' THEN 1 ELSE 0 END) AS pipe_confirmed,
    COALESCE(SUM(CASE WHEN q.status='Approved' THEN q.grand_total ELSE 0 END),0) AS pipe_confirmed_val,
    SUM(CASE WHEN q.status='Cancelled' THEN 1 ELSE 0 END) AS pipe_lost,
    COALESCE(SUM(CASE WHEN q.status='Cancelled' THEN q.grand_total ELSE 0 END),0) AS pipe_lost_val,
    COUNT(CASE WHEN q.status='Sent' AND q.expiry_date < CURDATE() THEN 1 END) AS alert_overdue
    FROM quotations q WHERE 1=1 $company_filter_plain $_qstaff")->fetch_assoc();
$q_total = $qc['q_total'];
$q_approved = $qc['q_approved'];
$q_cancelled = $qc['q_cancelled'];
$q_pending = $qc['q_pending'];
$conversion_rate = $q_total > 0 ? round(($q_approved / $q_total) * 100, 1) : 0;

$q_today_total = $qc['q_today'];
$pipe_prospect = (int)$qc['pipe_prospect'];
$pipe_quotation = (int)$qc['pipe_quotation'];
$pipe_confirmed = (int)$qc['pipe_confirmed'];
$pipe_lost = (int)$qc['pipe_lost'];
$pipe_prospect_val = (float)$qc['pipe_prospect_val'];
$pipe_quotation_val = (float)$qc['pipe_quotation_val'];
$pipe_confirmed_val = (float)$qc['pipe_confirmed_val'];
$pipe_lost_val = (float)$qc['pipe_lost_val'];
$alert_overdue = (int)$qc['alert_overdue'];

/* ===== 8. DEPOSIT DASHBOARD — รวม finance ทั้งหมดเป็น QUERY เดียว (มัดจำ + รายได้/ต้นทุนเพื่อคำนวณ GOP) ===== */
$fin = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN ff.type='deposit' AND ff.is_post_approval=1 AND ff.transaction_date >= '$selected_date' AND ff.transaction_date < DATE_ADD('$selected_date', INTERVAL 1 DAY) THEN ff.amount ELSE 0 END),0) AS dep_today,
    COALESCE(SUM(CASE WHEN ff.type='deposit' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' THEN ff.amount ELSE 0 END),0) AS dep_mtd,
    COALESCE(SUM(CASE WHEN ff.type='income' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' THEN ff.amount ELSE 0 END),0) AS inc_mtd,
    COALESCE(SUM(CASE WHEN ff.type='cost' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' THEN ff.amount ELSE 0 END),0) AS cost_mtd
    FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE 1=1 $company_filter $staff_filter")->fetch_assoc();
$deposit_received = $fin['dep_today'];
$deposit_received_mtd = $fin['dep_mtd'];
$total_income_mtd = $fin['inc_mtd'];
$total_cost_mtd = $fin['cost_mtd'];
/* total_deposit_balance / deposit_pending_events มาจาก $core ด้านบนแล้ว */

/* ===== 9. SALES PIPELINE (Funnel) — ตัวเลขมาจาก $qc ด้านบนแล้ว ===== */

/* ===== 10. TOP 10 EVENTS ===== */
$top_events = $conn->query("SELECT f.function_name, ft.type_name, DATE(f.start_time) as event_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter ORDER BY f.total_amount DESC LIMIT 10");

/* ===== 11. SALES BY SEGMENT (Function Type) ===== */
$seg_labels = []; $seg_values = [];
$seg_data = $conn->query("SELECT COALESCE(ft.type_name,'ไม่ระบุประเภท') as type_name, COALESCE(SUM(f.total_amount),0) as total FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter GROUP BY ft.id, type_name ORDER BY total DESC");
while ($sd = $seg_data->fetch_assoc()) {
    $seg_labels[] = $sd['type_name'];
    $seg_values[] = (float)$sd['total'];
}

/* ===== 12. LEAD SOURCE (OTA equivalent) ===== */
$src_labels = []; $src_values = [];
$src_data = $conn->query("SELECT COALESCE(NULLIF(f.lead_source,''),'ไม่ได้ระบุ') as src, COALESCE(SUM(f.total_amount),0) as total, COUNT(*) as cnt FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter GROUP BY src ORDER BY total DESC");
while ($sr = $src_data->fetch_assoc()) {
    $src_labels[] = $sr['src'];
    $src_values[] = (float)$sr['total'];
}

/* ===== 13. PAYMENT METHOD ===== */
$pay_labels = []; $pay_values = [];
$pay_data = $conn->query("SELECT COALESCE(ff.payment_method,'ไม่ระบุ') as pm, COALESCE(SUM(ff.amount),0) as total FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE ff.type='income' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter GROUP BY pm ORDER BY total DESC");
while ($pd = $pay_data->fetch_assoc()) {
    $pay_labels[] = $pd['pm'];
    $pay_values[] = (float)$pd['total'];
}

/* ===== 14. SALES BY PERSON ===== */
$staff_data = [];
$staff_q = $conn->query("SELECT u.id, u.name, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') THEN 1 ELSE 0 END),0) as total_events, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') THEN f.total_amount ELSE 0 END),0) as total_revenue, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled','Completed') THEN f.deposit ELSE 0 END),0) as total_deposit, COALESCE((SELECT SUM(st.target_amount) FROM sales_targets st WHERE st.user_id=u.id AND st.target_year=$selected_year AND st.target_month=$selected_month),0) as target_amount FROM users u LEFT JOIN functions f ON f.created_by_id=u.id $company_filter $mtd_filter WHERE u.role IN ('Staff','Admin','Sale','Manager','GM') $staff_filter_user GROUP BY u.id, u.name HAVING total_events > 0 OR target_amount > 0 ORDER BY total_revenue DESC");
while ($sq = $staff_q->fetch_assoc()) {
    $staff_data[] = $sq;
}

/* ===== 15. KPI vs TARGET ===== */
$team_target = $conn->query("SELECT COALESCE(SUM(st.target_amount),0) as t FROM sales_targets st WHERE st.target_year=$selected_year AND st.target_month=$selected_month" . ($selected_staff > 0 ? " AND st.user_id=$selected_staff" : ""))->fetch_assoc()['t'];
$team_target_pct = $team_target > 0 ? round(($mtd_total / $team_target) * 100, 1) : 0;

$avg_deal = $mtd_events > 0 ? round($mtd_total / $mtd_events) : 0;

/* ===== 16. CANCELLATION ===== */
/* ต้องกรอง approve=1 ให้ตรงกับ $mtd_events ที่เป็นตัวส่วน ไม่งั้นอัตรายกเลิกจะเพี้ยน
   (ตัวเศษนับงานที่ยังไม่อนุมัติด้วย แต่ตัวส่วนนับเฉพาะงานที่อนุมัติแล้ว)
   $cancelled_count มาจาก $core ด้านบนแล้ว */
$cancel_rate = ($mtd_events + $cancelled_count) > 0 ? round(($cancelled_count / ($mtd_events + $cancelled_count)) * 100, 1) : 0;

/* ===== 17. REPEAT CUSTOMER ===== */
$repeat_data = $conn->query("SELECT COUNT(DISTINCT customer_id) as total_cust, SUM(CASE WHEN booking_count > 1 THEN 1 ELSE 0 END) as repeat_cust FROM (SELECT customer_id, COUNT(*) as booking_count FROM functions WHERE approve=1 AND status NOT IN ('Cancelled') AND customer_id IS NOT NULL $company_filter_plain $staff_filter_plain GROUP BY customer_id) sub")->fetch_assoc();
$total_cust = (int)($repeat_data['total_cust'] ?? 0);
$repeat_cust = (int)($repeat_data['repeat_cust'] ?? 0);
$repeat_pct = $total_cust > 0 ? round(($repeat_cust / $total_cust) * 100, 1) : 0;

/* ===== 18. GOP FORECAST — income/cost มาจาก $fin ด้านบนแล้ว ===== */
$gop_forecast = $total_income_mtd - $total_cost_mtd;
$gop_margin = $total_income_mtd > 0 ? round(($gop_forecast / $total_income_mtd) * 100, 1) : 0;

/* ===== 19. EXECUTIVE ALERTS =====
   ห้ามตัด 'Confirmed' ออก — งานที่อนุมัติแล้วทุกใบมีสถานะ Confirmed ถ้าตัดออก alert จะเป็น 0 ตลอดกาล
   และงาน Confirmed ที่ยังไม่วางมัดจำคือกลุ่มที่ต้องเร่งตามที่สุด
   ตัวเลข alert_no_deposit / alert_overdue / alert_pending_approval มาจาก $core และ $qc แล้ว */
$alert_room_conflict = $conn->query("SELECT COUNT(*) as c FROM (SELECT f.room_id, DATE(f.start_time) as dt, COUNT(*) as cnt FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.room_id IS NOT NULL AND f.room_id != 0 AND f.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND f.start_time < DATE_ADD(CURDATE(), INTERVAL 6 MONTH) $company_filter $staff_filter GROUP BY f.room_id, DATE(f.start_time) HAVING cnt > 1) sub")->fetch_assoc()['c'];

/* ===== 20. UPCOMING TODAY ===== */
$today_events_list = $conn->query("SELECT f.id, f.function_name, f.start_time, f.end_time, c.company_name, r.room_name, u.name as staff_name, f.total_amount, f.pax, ft.type_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN meeting_rooms r ON f.room_id=r.id LEFT JOIN users u ON f.created_by_id=u.id LEFT JOIN function_types ft ON f.function_type_id=ft.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND $cond_today $company_filter $staff_filter ORDER BY f.start_time ASC");

/* ===== 21. MONTHLY TREND =====
   ยึดจากวันที่ 1 ของเดือนที่เลือกเสมอ — ถ้ายึดจากวันปัจจุบัน strtotime("-1 months")
   ในวันที่ 29-31 จะเด้งข้ามเดือน (เช่น 31 มี.ค. ลบ 1 เดือน = 3 มี.ค.) ทำให้กราฟซ้ำเดือนและหายเดือน */
$trend_anchor = strtotime($month_start);
$trend_from = date('Y-m-01', strtotime('-5 months', $trend_anchor));
$trend_to_excl = date('Y-m-01', strtotime('+1 month', $trend_anchor));
/* query เดียว GROUP BY เดือน — เดิม loop 6 queries ที่ใช้ MONTH()/YEAR() ไม่สามารถใช้ index ได้ */
$monthly_trend_map = [];
$tr = $conn->query("SELECT DATE_FORMAT(f.start_time,'%Y-%m') as ym, COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE $ok AND f.start_time >= '$trend_from' AND f.start_time < '$trend_to_excl' $company_filter $staff_filter GROUP BY ym");
while ($row = $tr->fetch_assoc()) { $monthly_trend_map[$row['ym']] = (float)$row['t']; }
$monthly_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months", $trend_anchor);
    $monthly_trend[] = ['label' => date('M y', $ts), 'total' => $monthly_trend_map[date('Y-m', $ts)] ?? 0.0];
}

/* ===== 22. PERIOD SPLIT (เช้า/บ่าย/เย็น) ===== */
$period_data = $conn->query("SELECT CASE WHEN TIME(f.start_time) < '12:00:00' THEN 'เช้า' WHEN TIME(f.start_time) < '17:00:00' THEN 'บ่าย' ELSE 'เย็น' END as period, COUNT(*) as cnt, COALESCE(SUM(f.total_amount),0) as total FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter GROUP BY period ORDER BY total DESC");
$period_labels_arr = []; $period_counts_arr = []; $period_rev_arr = [];
while ($pd2 = $period_data->fetch_assoc()) {
    $period_labels_arr[] = $pd2['period'];
    $period_counts_arr[] = (int)$pd2['cnt'];
    $period_rev_arr[] = (float)$pd2['total'];
}

/* ===== 23. QUOTATION EXPIRING ===== */
$expiring_quotes = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.expiry_date, q.grand_total, c.company_name FROM quotations q LEFT JOIN companies c ON q.company_id=c.id WHERE q.status='Sent' AND q.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) " . ($selected_company > 0 ? "AND q.company_id = $selected_company " : "") . ($selected_staff > 0 ? "AND q.created_by = $selected_staff " : "") . "ORDER BY q.expiry_date ASC LIMIT 5");

/* ===== 24. RECENT EVENTS LIST ===== */
$upcoming_all = $conn->query("SELECT f.id, f.function_name, f.start_time, f.end_time, c.company_name, r.room_name, u.name as staff_name, f.total_amount, f.pax, ft.type_name, f.status FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN meeting_rooms r ON f.room_id=r.id LEFT JOIN users u ON f.created_by_id=u.id LEFT JOIN function_types ft ON f.function_type_id=ft.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time >= NOW() $company_filter $staff_filter ORDER BY f.start_time ASC LIMIT 10");

/* ===== DRILL-DOWN DATA ===== */
$dd = [];

// 1) Events by Type
$dd_type = $conn->query("SELECT f.id, f.function_name, ft.type_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name, u.name as staff_name, f.pax FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter ORDER BY f.total_amount DESC");
$dd['type'] = [];
while ($r = $dd_type->fetch_assoc()) $dd['type'][] = $r;

// 2) Events by Company
$dd_comp = $conn->query("SELECT f.id, f.function_name, c.company_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, u.name as staff_name, f.pax FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter ORDER BY c.company_name, f.total_amount DESC");
$dd['company'] = [];
while ($r = $dd_comp->fetch_assoc()) $dd['company'][] = $r;

// 3) Events by Source
$dd_src = $conn->query("SELECT f.id, f.function_name, COALESCE(NULLIF(f.lead_source,''),'ไม่ได้ระบุ') as lead_source, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter ORDER BY lead_source, f.total_amount DESC");
$dd['source'] = [];
while ($r = $dd_src->fetch_assoc()) $dd['source'][] = $r;

// 4) Events by Period
$dd_period = $conn->query("SELECT f.id, f.function_name, CASE WHEN TIME(f.start_time) < '12:00:00' THEN 'เช้า' WHEN TIME(f.start_time) < '17:00:00' THEN 'บ่าย' ELSE 'เย็น' END as period, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name, CONCAT(TIME_FORMAT(f.start_time,'%H:%i'),'-',TIME_FORMAT(f.end_time,'%H:%i')) as ev_time FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter ORDER BY period, f.total_amount DESC");
$dd['period'] = [];
while ($r = $dd_period->fetch_assoc()) $dd['period'][] = $r;

// 5) Events by Room
$dd_room = $conn->query("SELECT f.id, f.function_name, mr.room_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name, f.pax FROM functions f LEFT JOIN meeting_rooms mr ON f.room_id=mr.id LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') AND f.room_id IS NOT NULL $mtd_filter $company_filter $staff_filter ORDER BY mr.room_name, f.total_amount DESC");
$dd['room'] = [];
while ($r = $dd_room->fetch_assoc()) $dd['room'][] = $r;

// 6) Pipeline (Quotations by status)
$dd_pipe = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.status, q.grand_total, DATE(q.event_date) as ev_date, c.company_name, u.name as staff_name, q.created_at FROM quotations q LEFT JOIN companies c ON q.company_id=c.id LEFT JOIN users u ON q.created_by=u.id WHERE 1=1 $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : "") . " ORDER BY FIELD(q.status,'Draft','Sent','Approved','Cancelled'), q.grand_total DESC");
$dd['pipeline'] = [];
while ($r = $dd_pipe->fetch_assoc()) $dd['pipeline'][] = $r;

// 7) Events by Month (for trend) — query เดียวทั้งช่วง 6 เดือน แล้วจัดกลุ่มใน PHP
$dd_month = [];
$_month_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months", $trend_anchor);
    $_month_labels[date('Y-m', $ts)] = date('M y', $ts);
}
$mr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name, DATE_FORMAT(f.start_time,'%Y-%m') as ym FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE $ok AND f.start_time >= '$trend_from' AND f.start_time < '$trend_to_excl' $company_filter $staff_filter ORDER BY f.start_time DESC");
$_grouped = [];
while ($r = $mr->fetch_assoc()) { $_grouped[$r['ym']][] = $r; }
foreach ($_month_labels as $ym => $ml) {
    foreach ($_grouped[$ym] ?? [] as $r) { $r['month_label'] = $ml; $dd_month[] = $r; }
}
$dd['month'] = $dd_month;

// 8) Deposit transactions
$dd_dep = $conn->query("SELECT ff.id, ff.detail, ff.amount, ff.payment_method, DATE(ff.transaction_date) as tx_date, f.function_name, c.company_name, ff.created_by_name FROM function_finance ff JOIN functions f ON ff.function_id=f.id LEFT JOIN companies c ON f.company_id=c.id WHERE ff.type='deposit' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter ORDER BY ff.transaction_date DESC");
$dd['deposit'] = [];
while ($r = $dd_dep->fetch_assoc()) $dd['deposit'][] = $r;

// 9) Payment method transactions
$dd_pay = $conn->query("SELECT ff.id, ff.detail, ff.amount, COALESCE(ff.payment_method,'ไม่ระบุ') as payment_method, DATE(ff.transaction_date) as tx_date, f.function_name, c.company_name FROM function_finance ff JOIN functions f ON ff.function_id=f.id LEFT JOIN companies c ON f.company_id=c.id WHERE ff.type='income' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter ORDER BY ff.payment_method, ff.transaction_date DESC");
$dd['payment'] = [];
while ($r = $dd_pay->fetch_assoc()) $dd['payment'][] = $r;

// 10) Future booking details — query เดียวถึง 180 วัน แล้วแบ่ง bucket ใน PHP
$_now_ts = strtotime($_now);
$dd_future = ['7' => [], '30' => [], '90' => [], '180' => []];
$fr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name, f.pax, UNIX_TIMESTAMP(f.start_time) as ts FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE $ok_active AND f.start_time >= '$_now' AND f.start_time <= DATE_ADD('$_now', INTERVAL 180 DAY) $company_filter $staff_filter ORDER BY f.start_time ASC");
while ($r = $fr->fetch_assoc()) {
    $ts = (int)$r['ts'];
    unset($r['ts']);
    foreach ([7, 30, 90, 180] as $days) {
        if ($ts <= $_now_ts + $days * 86400) { $dd_future[(string)$days][] = $r; }
    }
}
$dd['future'] = $dd_future;

// 11) Alerts detail
$dd['alert_no_deposit'] = [];
$adr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND (f.deposit IS NULL OR f.deposit=0) $company_filter $staff_filter ORDER BY f.start_time ASC");
while ($r = $adr->fetch_assoc()) $dd['alert_no_deposit'][] = $r;

$dd['alert_overdue'] = [];
$odr = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.expiry_date, q.grand_total, c.company_name, u.name as staff_name FROM quotations q LEFT JOIN companies c ON q.company_id=c.id LEFT JOIN users u ON q.created_by=u.id WHERE q.status='Sent' AND q.expiry_date < CURDATE() $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : "") . " ORDER BY q.expiry_date ASC");
while ($r = $odr->fetch_assoc()) $dd['alert_overdue'][] = $r;

$dd['alert_pending'] = [];
$apr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=0 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter ORDER BY f.created_at DESC");
while ($r = $apr->fetch_assoc()) $dd['alert_pending'][] = $r;

$dd['alert_room_conflict'] = [];
$rcr = $conn->query("SELECT f.id, f.function_name, mr.room_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name FROM functions f LEFT JOIN meeting_rooms mr ON f.room_id=mr.id LEFT JOIN companies c ON f.company_id=c.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.room_id IS NOT NULL AND f.room_id != 0 AND f.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND f.start_time < DATE_ADD(CURDATE(), INTERVAL 6 MONTH) AND (f.room_id, DATE(f.start_time)) IN (SELECT f2.room_id, DATE(f2.start_time) FROM functions f2 WHERE f2.approve=1 AND f2.status NOT IN ('Cancelled','Completed') AND f2.room_id IS NOT NULL AND f2.room_id != 0 AND f2.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND f2.start_time < DATE_ADD(CURDATE(), INTERVAL 6 MONTH) $company_filter $staff_filter GROUP BY f2.room_id, DATE(f2.start_time) HAVING COUNT(*)>1) $company_filter $staff_filter ORDER BY f.start_time ASC");
while ($r = $rcr->fetch_assoc()) $dd['alert_room_conflict'][] = $r;

/* =========================================================
   PRESENTATION LAYER — จัดชุดข้อมูลให้พร้อมวาดกราฟ
   ========================================================= */

/** ยุบรายการเล็ก ๆ เป็น "อื่นๆ" ให้เหลือไม่เกิน 6 ชิ้น (โดนัทอ่านง่าย) */
function viz_top(array $labels, array $values, int $max = 5): array
{
    $pairs = [];
    foreach ($labels as $i => $l) {
        $v = (float) ($values[$i] ?? 0);
        if ($v > 0) $pairs[] = [$l, $v];
    }
    usort($pairs, fn($a, $b) => $b[1] <=> $a[1]);
    if (count($pairs) > $max + 1) {
        $rest = array_slice($pairs, $max);
        $pairs = array_slice($pairs, 0, $max);
        $pairs[] = ['อื่นๆ', array_sum(array_column($rest, 1))];
    }
    return ['labels' => array_column($pairs, 0), 'values' => array_column($pairs, 1)];
}

// รายได้ตามประเภทงาน (ตัดประเภทที่ไม่มียอด + เรียงมาก→น้อย)
$type_rows = [];
foreach ($type_names as $i => $tn) {
    $mtd = (float) ($type_mtd[$i] ?? 0);
    $tod = (float) ($type_today[$i] ?? 0);
    if ($mtd <= 0 && $tod <= 0) continue;
    $type_rows[] = ['label' => $tn, 'today' => $tod, 'mtd' => $mtd];
}
usort($type_rows, fn($a, $b) => $b['mtd'] <=> $a['mtd']);
$type_sum = array_sum(array_column($type_rows, 'mtd'));

// รายได้ตามโรงแรม
$comp_rows = [];
foreach ($comp_names as $i => $cn) {
    if ((float) ($comp_mtd[$i] ?? 0) > 0) $comp_rows[] = ['label' => $cn, 'value' => (float) $comp_mtd[$i]];
}
usort($comp_rows, fn($a, $b) => $b['value'] <=> $a['value']);

// การใช้ห้องประชุม
$room_rows = [];
foreach ($room_labels as $i => $rl) {
    if ((int) ($room_bookings[$i] ?? 0) > 0) $room_rows[] = ['label' => $rl, 'value' => (int) $room_bookings[$i]];
}
usort($room_rows, fn($a, $b) => $b['value'] <=> $a['value']);

// สัดส่วนธุรกิจ (3 มุมมองในการ์ดเดียว)
$mix_source = viz_top($src_labels, $src_values);
$mix_period = viz_top($period_labels_arr, $period_rev_arr);
$mix_pay    = viz_top($pay_labels, $pay_values);

// กรวยการขาย
$funnel_rows = [
    ['key' => 'Draft',     'label' => 'ร่าง / Prospect', 'cnt' => $pipe_prospect,  'val' => $pipe_prospect_val,  'color' => '#86b6ef'],
    ['key' => 'Sent',      'label' => 'ส่งใบแล้ว',        'cnt' => $pipe_quotation, 'val' => $pipe_quotation_val, 'color' => '#3987e5'],
    ['key' => 'Approved',  'label' => 'ยืนยันแล้ว',       'cnt' => $pipe_confirmed, 'val' => $pipe_confirmed_val, 'color' => '#1c5cab'],
    ['key' => 'Cancelled', 'label' => 'ปิดไม่สำเร็จ',      'cnt' => $pipe_lost,      'val' => $pipe_lost_val,      'color' => '#d03b3b'],
];
$funnel_max = max(1, max(array_column($funnel_rows, 'cnt')));

// งานในคิวข้างหน้า
$pipe_rows = [
    ['days' => '7',   'label' => '7 วัน',   'cnt' => (int) $pipe_7d['c'],   'val' => (float) $pipe_7d['t']],
    ['days' => '30',  'label' => '30 วัน',  'cnt' => (int) $pipe_30d['c'],  'val' => (float) $pipe_30d['t']],
    ['days' => '90',  'label' => '90 วัน',  'cnt' => (int) $pipe_90d['c'],  'val' => (float) $pipe_90d['t']],
    ['days' => '180', 'label' => '180 วัน', 'cnt' => (int) $pipe_180d['c'], 'val' => (float) $pipe_180d['t']],
];
$pipe_max = max(1, max(array_column($pipe_rows, 'val')));

// การ์ดแจ้งเตือน
$alert_rows = [
    ['type' => 'alert_no_deposit',    'label' => 'งานยังไม่วางมัดจำ (7 วัน)', 'n' => $alert_no_deposit,      'icon' => 'bi-cash-coin',           'level' => 'warn'],
    ['type' => 'alert_room_conflict', 'label' => 'ห้องประชุมถูกจองซ้ำ',       'n' => $alert_room_conflict,   'icon' => 'bi-exclamation-octagon', 'level' => 'crit'],
    ['type' => 'alert_pending',       'label' => 'งานรออนุมัติ',              'n' => $alert_pending_approval,'icon' => 'bi-hourglass-split',     'level' => 'warn'],
    ['type' => 'alert_overdue',       'label' => 'ใบเสนอราคาเกินกำหนด',       'n' => $alert_overdue,         'icon' => 'bi-calendar-x',          'level' => 'crit'],
];
$alert_total = array_sum(array_column($alert_rows, 'n'));

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* =======================================================
   EXECUTIVE DASHBOARD — UI
   ======================================================= */
.exec-dash{
    --gold:#b89441; --gold-tint:#f7f1e3;
    --ink:#111318; --ink-2:#5b6470; --muted:#8a9099;
    --line:#e8eaee; --line-soft:#f0f2f5; --surface:#fff;
    --ok:#0ca30c; --warn:#fab219; --crit:#d03b3b;
    font-family:'Sarabun','Inter',sans-serif; color:var(--ink);
}
.exec-dash .text-dim{color:var(--ink-2)}
.exec-dash .num{font-variant-numeric:tabular-nums}

/* ---------- Hero + filter ---------- */
.exec-hero{
    position:relative; overflow:hidden; border-radius:18px; padding:16px 20px;
    background:var(--surface); border:1px solid var(--line); color:var(--ink);
    border-left:4px solid var(--gold);
}
.exec-hero::after{
    content:''; position:absolute; inset:0; pointer-events:none;
    background:radial-gradient(520px 200px at 92% -40%, rgba(184,148,65,.10), transparent 70%);
}
.exec-hero > *{position:relative; z-index:1}
.exec-hero .section-title{font-size:1.05rem;font-weight:700;margin:0;letter-spacing:.2px;color:var(--ink)}
.exec-hero .hero-sub{font-size:.78rem;color:var(--ink-2)}

/* ---------- Filter bar (พื้นขาว แยกออกมาจากแถบหัวสีเข้ม) ---------- */
.exec-filter{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:13px 16px}
.exec-filter .flt-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:700;margin-bottom:3px;display:block}
.exec-filter .form-control,.exec-filter .form-select{
    background:#fff;border:1px solid var(--line);color:var(--ink);
    font-size:.8rem;border-radius:9px;box-shadow:none;
}
.exec-filter .form-control:focus,.exec-filter .form-select:focus{
    border-color:var(--gold);box-shadow:0 0 0 .18rem rgba(184,148,65,.15);
}
.exec-filter .form-select:disabled{background:#f2f4f7;color:var(--muted)}
.btn-gold{background:var(--gold);border:1px solid var(--gold);color:#fff;font-weight:600;font-size:.78rem;border-radius:9px;padding:6px 16px}
.btn-gold:hover{background:#a5833a;border-color:#a5833a;color:#fff}

/* ---------- Stat tiles ---------- */
.tile{
    height:100%; background:var(--surface); border:1px solid var(--line); border-radius:14px;
    padding:14px 15px; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.tile:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(16,24,40,.09);border-color:#dcdfe5}
.tile.is-click{cursor:pointer}
.tile-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.tile-label{font-size:.7rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.tile-ico{width:30px;height:30px;flex:0 0 auto;border-radius:9px;display:grid;place-items:center;font-size:.9rem}
.tile-value{margin-top:7px;font-size:1.5rem;font-weight:700;line-height:1.15;letter-spacing:-.2px}
.tile-sub{margin-top:3px;font-size:.74rem;color:var(--ink-2)}
.tile .bar{height:6px;border-radius:99px;background:var(--line-soft);overflow:hidden;margin-top:9px}
.tile .bar > span{display:block;height:100%;border-radius:99px}

/* ---------- Alert chips ---------- */
.chip{
    display:flex;align-items:center;gap:10px;width:100%;text-align:left;
    background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:9px 12px;
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.chip:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(16,24,40,.08);border-color:#dcdfe5}
.chip-ico{width:32px;height:32px;flex:0 0 auto;border-radius:10px;display:grid;place-items:center;font-size:1rem}
.chip-lb{font-size:.76rem;font-weight:600;color:var(--ink-2);line-height:1.25}
.chip-n{margin-left:auto;font-size:1.15rem;font-weight:700}
.chip.ok .chip-ico{background:#e9f7e9;color:var(--ok)} .chip.ok .chip-n{color:var(--ok)}
.chip.warn .chip-ico{background:#fdf4de;color:#9a6a06} .chip.warn .chip-n{color:#9a6a06}
.chip.warn{border-color:#f4e3bb}
.chip.crit .chip-ico{background:#fbeaea;color:var(--crit)} .chip.crit .chip-n{color:var(--crit)}
.chip.crit{border-color:#f2cfcf}

/* ---------- Tabs ---------- */
.exec-tabs{border:0;gap:6px;flex-wrap:wrap}
.exec-tabs .nav-link{
    border:1px solid var(--line);background:var(--surface);color:var(--ink-2);
    border-radius:999px;padding:7px 16px;font-size:.8rem;font-weight:600;
}
.exec-tabs .nav-link:hover{border-color:#d3d7dd;color:var(--ink)}
.exec-tabs .nav-link.active{background:#16181d;border-color:#16181d;color:#fff}
.exec-tabs .nav-link .badge{font-size:.62rem;font-weight:700}

/* ---------- Panels ---------- */
.pnl{background:var(--surface);border:1px solid var(--line);border-radius:14px;height:100%;overflow:hidden}
.pnl-hd{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px 16px;border-bottom:1px solid var(--line)}
.pnl-hd h6{margin:0;font-size:.88rem;font-weight:700}
.pnl-hd .hint{font-size:.72rem;color:var(--muted);font-weight:400}
.pnl-bd{padding:16px}
.pnl-bd.tight{padding:8px 16px 14px}
.pnl-note{font-size:.72rem;color:var(--muted);padding:0 16px 12px;line-height:1.55}
.pnl-desc{display:flex;gap:7px;font-size:.72rem;color:var(--muted);line-height:1.6;padding:10px 16px 0}
.pnl-desc i{color:var(--gold);flex:0 0 auto;margin-top:2px}
.sec-desc{display:flex;gap:7px;font-size:.74rem;color:var(--ink-2);line-height:1.6;margin:0 2px 9px}
.sec-desc i{color:var(--gold);flex:0 0 auto;margin-top:3px}

/* ---------- Segmented toggle ---------- */
.seg{display:inline-flex;background:#f2f4f7;border-radius:999px;padding:3px;gap:2px}
.seg-btn{border:0;background:transparent;border-radius:999px;padding:4px 12px;font-size:.74rem;font-weight:600;color:var(--ink-2)}
.seg-btn.active{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(16,24,40,.14)}

/* ---------- Chart box ---------- */
.chart-box{position:relative;width:100%}
.chart-empty{display:grid;place-items:center;color:var(--muted);font-size:.8rem;min-height:160px}

/* ---------- Donut legend / table twin ---------- */
.lgd{list-style:none;margin:0;padding:0}
.lgd li{display:flex;align-items:center;gap:9px;padding:7px 0;border-bottom:1px dashed #eef0f3;font-size:.79rem;cursor:pointer}
.lgd li:last-child{border-bottom:0}
.lgd li:hover{background:#f8f9fb}
.lgd .sw{width:10px;height:10px;border-radius:3px;flex:0 0 auto}
.lgd .nm{flex:1;color:var(--ink-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lgd .vl{font-weight:700;font-variant-numeric:tabular-nums}
.lgd .pc{width:46px;text-align:right;color:var(--muted);font-variant-numeric:tabular-nums}

/* ---------- Funnel / runway ---------- */
.fn-row{display:grid;grid-template-columns:112px 1fr auto;align-items:center;gap:12px;padding:9px 6px;border-radius:9px;cursor:pointer}
.fn-row:hover{background:#f7f8fa}
.fn-lb{font-size:.78rem;font-weight:600;color:var(--ink-2);display:flex;align-items:center;gap:6px}
.fn-track{height:12px;border-radius:99px;background:var(--line-soft);overflow:hidden}
.fn-fill{display:block;height:100%;border-radius:99px;min-width:3px}
.fn-num{text-align:right;min-width:96px}
.fn-num b{font-size:.92rem;font-variant-numeric:tabular-nums}
.fn-num small{display:block;font-size:.7rem;color:var(--muted);font-variant-numeric:tabular-nums}

/* ---------- Mini stats ---------- */
.ministat{border:1px solid var(--line);border-radius:11px;padding:9px 8px;text-align:center;height:100%;background:var(--surface)}
.ministat .v{font-size:1.1rem;font-weight:700;line-height:1.2}
.ministat .l{font-size:.68rem;color:var(--muted);margin-top:2px}

/* ---------- Tables ---------- */
.tbl{width:100%;margin:0;font-size:.79rem}
.tbl thead th{
    background:#fafbfc;color:var(--muted);font-weight:700;font-size:.68rem;text-transform:uppercase;
    letter-spacing:.4px;border-bottom:1px solid var(--line)!important;white-space:nowrap;padding:9px 12px;
}
.tbl tbody td{padding:9px 12px;border-bottom:1px solid var(--line-soft);vertical-align:middle}
.tbl tbody tr:last-child td{border-bottom:0}
.tbl tbody tr:hover{background:#f8f9fb}
.tbl .num{font-variant-numeric:tabular-nums}
.tag{display:inline-block;background:#f1f3f6;color:var(--ink-2);border-radius:6px;padding:1px 7px;font-size:.7rem;font-weight:600}
.rank{width:22px;height:22px;border-radius:7px;display:inline-grid;place-items:center;background:var(--gold-tint);color:#8a6c22;font-size:.68rem;font-weight:700}
.who{width:26px;height:26px;border-radius:50%;display:inline-grid;place-items:center;background:#eef1f6;color:var(--ink-2);font-size:.68rem;font-weight:700;margin-right:7px}
.prog{height:6px;border-radius:99px;background:var(--line-soft);width:66px;overflow:hidden}
.prog > span{display:block;height:100%;border-radius:99px}

.exec-dash a.lk{color:var(--ink);text-decoration:none;font-weight:600}
.exec-dash a.lk:hover{color:var(--gold)}

@media (max-width:575px){
    .tile-value{font-size:1.3rem}
    .fn-row{grid-template-columns:92px 1fr auto}
}
@media print{
    .no-print{display:none!important}
    .exec-dash .tab-pane{display:block!important;opacity:1!important}
    .pnl,.tile,.chip{break-inside:avoid}
    .exec-hero{background:#fff!important;color:#000!important;box-shadow:none}
}
</style>

<div class="exec-dash">

    <!-- ===================== HERO ===================== -->
    <div class="exec-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <h5 class="section-title"><i class="bi bi-speedometer2 me-2" style="color:var(--gold)"></i>Executive Dashboard</h5>
                <div class="hero-sub mt-1">
                    ข้อมูลวันที่ <?= date('d M Y', strtotime($selected_date)) ?>
                    · เดือน <?= date('F', mktime(0, 0, 0, $selected_month, 1)) ?> <?= $selected_year + 543 ?>
                    <?php if ($selected_company > 0 || $selected_staff > 0): ?>
                        · <span style="color:#8a6c22;font-weight:600">กรองข้อมูลอยู่</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== FILTER ===================== -->
    <div class="exec-filter mb-3 no-print">
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2" id="execFilter">
                <div>
                    <label class="flt-label">วันที่</label>
                    <input type="date" name="date" value="<?= $selected_date ?>" class="form-control form-control-sm" style="width:145px">
                </div>
                <div>
                    <label class="flt-label">เดือน / ปี</label>
                    <div class="d-flex gap-1">
                        <select name="month" class="form-select form-select-sm" style="width:105px">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm" style="width:82px">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y + 543 ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="flt-label">โรงแรม</label>
                    <select name="company_id" class="form-select form-select-sm" style="width:150px">
                        <option value="0">ทั้งหมด</option>
                        <?php $companies->data_seek(0); while ($c = $companies->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $selected_company == $c['id'] ? 'selected' : '' ?>><?= $h($c['company_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="flt-label">เซลล์</label>
                    <select name="staff_id" class="form-select form-select-sm" style="width:130px" <?= $role === 'staff' ? 'disabled' : '' ?>>
                        <option value="0">ทั้งหมด</option>
                        <?php $staff_list->data_seek(0); while ($s = $staff_list->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $selected_staff == $s['id'] ? 'selected' : '' ?>><?= $h($s['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-gold btn-sm"><i class="bi bi-funnel me-1"></i>ดูข้อมูล</button>
        </form>
    </div>

    <!-- ===================== KPI TILES ===================== -->
    <div class="sec-desc"><i class="bi bi-info-circle"></i>
        <span>ตัวเลขสรุปทั้งหมดคิดตามตัวกรองด้านบน (วันที่ / เดือน / โรงแรม / เซลล์) และนับเฉพาะงานที่อนุมัติแล้วและไม่ถูกยกเลิก
            — การ์ดรายได้วันนี้ รายได้เดือนนี้ ปิดการขาย และเงินมัดจำ กดเข้าไปดูรายละเอียดรายการได้</span>
    </div>
    <div class="row g-2 g-lg-3 mb-3">
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile is-click" id="tileToday">
                <div class="tile-head">
                    <span class="tile-label">รายได้วันนี้</span>
                    <span class="tile-ico" style="background:var(--gold-tint);color:#8a6c22"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="tile-value">฿<?= number_format($today_total) ?></div>
                <div class="tile-sub"><?= $today_events ?> งาน · <?= number_format($today_pax) ?> คน</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile is-click" id="tileMtd">
                <div class="tile-head">
                    <span class="tile-label">รายได้เดือนนี้</span>
                    <span class="tile-ico" style="background:#e9f0fc;color:#2a78d6"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
                <div class="tile-value">฿<?= number_format($mtd_total) ?></div>
                <div class="tile-sub d-flex justify-content-between">
                    <span><?= $mtd_events ?> งาน · <?= number_format($mtd_pax) ?> คน</span>
                    <span class="fw-bold" style="color:<?= $team_target_pct >= 100 ? 'var(--ok)' : ($team_target_pct >= 50 ? '#9a6a06' : 'var(--crit)') ?>"><?= $team_target_pct ?>%</span>
                </div>
                <div class="bar"><span style="width:<?= min($team_target_pct, 100) ?>%;background:<?= $team_target_pct >= 100 ? 'var(--ok)' : ($team_target_pct >= 50 ? 'var(--warn)' : 'var(--crit)') ?>"></span></div>
                <div class="tile-sub">เป้า ฿<?= number_format($team_target) ?></div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile">
                <div class="tile-head">
                    <span class="tile-label">สะสมทั้งปี</span>
                    <span class="tile-ico" style="background:#eaf7f1;color:#0d8a60"><i class="bi bi-calendar3"></i></span>
                </div>
                <div class="tile-value">฿<?= number_format($year_total) ?></div>
                <div class="tile-sub"><?= number_format($year_events) ?> งาน · เฉลี่ย ฿<?= number_format($year_avg_deal) ?>/งาน</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile is-click" id="tileConv">
                <div class="tile-head">
                    <span class="tile-label">ปิดการขาย</span>
                    <span class="tile-ico" style="background:#f0edfb;color:#4a3aa7"><i class="bi bi-percent"></i></span>
                </div>
                <div class="tile-value"><?= $conversion_rate ?>%</div>
                <div class="tile-sub">ใบเสนอราคา <?= $q_total ?> ใบ · ยืนยัน <?= $q_approved ?></div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile is-click" id="tileDeposit">
                <div class="tile-head">
                    <span class="tile-label">มัดจำคงค้าง (ตามสัญญา)</span>
                    <span class="tile-ico" style="background:#fdf4de;color:#9a6a06"><i class="bi bi-wallet2"></i></span>
                </div>
                <div class="tile-value">฿<?= number_format($total_deposit_balance) ?></div>
                <div class="tile-sub">รับจริงเดือนนี้ ฿<?= number_format($deposit_received_mtd) ?></div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="tile">
                <div class="tile-head">
                    <span class="tile-label">กำไรขั้นต้น</span>
                    <span class="tile-ico" style="background:#eef1f6;color:#5b6470"><i class="bi bi-pie-chart"></i></span>
                </div>
                <div class="tile-value" style="color:<?= $gop_forecast >= 0 ? 'var(--ok)' : 'var(--crit)' ?>">฿<?= number_format($gop_forecast) ?></div>
                <div class="tile-sub">Margin <?= $gop_margin ?>% · ยกเลิก <?= $cancel_rate ?>%</div>
            </div>
        </div>
    </div>

    <!-- ===================== ALERT STRIP ===================== -->
    <div class="sec-desc"><i class="bi bi-bell"></i>
        <span>เรื่องที่ต้องรีบจัดการ — เขียวคือปกติ เหลืองคือควรติดตาม แดงคือต้องแก้ไขทันที กดที่การ์ดเพื่อดูว่าเป็นงานหรือใบเสนอราคาใดบ้าง</span>
    </div>
    <div class="row g-2 mb-3">
        <?php foreach ($alert_rows as $a):
            $lvl = $a['n'] > 0 ? $a['level'] : 'ok'; ?>
            <div class="col-xl-3 col-md-6">
                <button type="button" class="chip <?= $lvl ?> alert-clickable" data-alert-type="<?= $a['type'] ?>" data-alert-label="<?= $h($a['label']) ?>">
                    <span class="chip-ico"><i class="bi <?= $a['n'] > 0 ? $a['icon'] : 'bi-check-circle' ?>"></i></span>
                    <span class="chip-lb"><?= $h($a['label']) ?></span>
                    <span class="chip-n num"><?= $a['n'] ?></span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===================== TABS ===================== -->
    <ul class="nav exec-tabs mb-3 no-print" id="execTab" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button"><i class="bi bi-bar-chart-line me-1"></i>ภาพรวมรายได้</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sales" type="button"><i class="bi bi-funnel me-1"></i>งานขาย</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ops" type="button"><i class="bi bi-calendar-week me-1"></i>ปฏิบัติการ<?php if ($today_events_list->num_rows > 0): ?> <span class="badge rounded-pill text-bg-light"><?= $today_events_list->num_rows ?></span><?php endif; ?></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-finance" type="button"><i class="bi bi-wallet2 me-1"></i>การเงิน</button></li>
    </ul>

    <div class="tab-content">

        <!-- ============ TAB 1: ภาพรวมรายได้ ============ -->
        <div class="tab-pane fade show active" id="tab-overview">
            <div class="row g-3 mb-3">
                <div class="col-xl-7">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-activity me-1" style="color:var(--gold)"></i>แนวโน้มรายได้ 6 เดือน</h6>
                            <span class="hint ms-auto">คลิกจุดบนกราฟเพื่อดูงานของเดือนนั้น</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>รายได้รวมย้อนหลัง 6 เดือนจนถึงเดือนที่เลือกในตัวกรอง (นับตามวันจัดงาน) ใช้ดูว่ายอดกำลังขึ้นหรือลง
                                และเดือนไหนเป็นไฮซีซัน — ตัวเลขที่กำกับไว้คือเดือนที่ทำได้สูงสุดและเดือนล่าสุด</span>
                        </div>
                        <div class="pnl-bd">
                            <div class="chart-box" style="height:270px"><canvas id="trendChart"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-layers me-1" style="color:var(--gold)"></i>รายได้ตามประเภทงาน</h6>
                            <span class="hint ms-auto">เดือนนี้</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>เทียบรายได้ของงานแต่ละประเภท (สัมมนา ประชุม งานเลี้ยง ฯลฯ) ในเดือนที่เลือก เรียงจากมากไปน้อย
                                ตารางด้านล่างแยกให้เห็นยอดเฉพาะวันนี้และสัดส่วนของทั้งเดือน กดแท่งหรือแถวในตารางเพื่อดูรายชื่องาน</span>
                        </div>
                        <?php if (count($type_rows) > 0): ?>
                            <div class="pnl-bd tight">
                                <div class="chart-box" style="height:<?= max(150, min(count($type_rows), 8) * 30 + 30) ?>px"><canvas id="typeChart"></canvas></div>
                            </div>
                            <div class="table-responsive" style="max-height:210px;overflow:auto">
                                <table class="tbl">
                                    <thead>
                                        <tr><th>ประเภทงาน</th><th class="text-end">วันนี้</th><th class="text-end">เดือนนี้</th><th class="text-end">สัดส่วน</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($type_rows as $tr): ?>
                                            <tr class="drill-type" data-label="<?= $h($tr['label']) ?>" style="cursor:pointer">
                                                <td><?= $h($tr['label']) ?></td>
                                                <td class="text-end num text-dim"><?= $tr['today'] > 0 ? '฿' . number_format($tr['today']) : '–' ?></td>
                                                <td class="text-end num fw-bold">฿<?= number_format($tr['mtd']) ?></td>
                                                <td class="text-end num text-dim"><?= $type_sum > 0 ? round($tr['mtd'] / $type_sum * 100) : 0 ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="chart-empty">ไม่มีข้อมูลในเดือนนี้</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-6">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-buildings me-1" style="color:var(--gold)"></i>รายได้ตามโรงแรม</h6>
                            <span class="hint ms-auto">เดือนนี้ · คลิกแท่งเพื่อดูรายละเอียด</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>รายได้ทั้งเดือนแยกตามโรงแรม/บริษัทในเครือ ใช้เทียบผลงานระหว่างสาขาว่าที่ไหนทำยอดได้มากที่สุด</span>
                        </div>
                        <div class="pnl-bd">
                            <?php if (count($comp_rows) > 0): ?>
                                <div class="chart-box" style="height:<?= max(160, count($comp_rows) * 38 + 30) ?>px"><canvas id="companyChart"></canvas></div>
                            <?php else: ?>
                                <div class="chart-empty">ไม่มีข้อมูล</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-diagram-3 me-1" style="color:var(--gold)"></i>สัดส่วนธุรกิจ</h6>
                            <div class="seg ms-auto" id="mixSeg">
                                <button type="button" class="seg-btn active" data-mix="source">แหล่งลูกค้า</button>
                                <button type="button" class="seg-btn" data-mix="period">ช่วงเวลา</button>
                                <button type="button" class="seg-btn" data-mix="pay">ช่องทางชำระ</button>
                            </div>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>สัดส่วนรายได้ของเดือนนี้ สลับดูได้ 3 มุมมอง — <b>แหล่งลูกค้า</b> ลูกค้ามาจากช่องทางไหน (ใช้จัดงบการตลาด),
                                <b>ช่วงเวลา</b> เช้า/บ่าย/เย็นช่วงไหนทำเงิน (ใช้วางแผนรอบใช้ห้อง), <b>ช่องทางชำระ</b> ลูกค้าจ่ายด้วยวิธีใด
                                กดที่วงกลมหรือรายการด้านข้างเพื่อดูรายละเอียด</span>
                        </div>
                        <div class="pnl-bd">
                            <div class="row g-3 align-items-center">
                                <div class="col-sm-5">
                                    <div class="chart-box" style="height:190px"><canvas id="mixChart"></canvas></div>
                                </div>
                                <div class="col-sm-7">
                                    <ul class="lgd" id="mixLegend"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB 2: งานขาย ============ -->
        <div class="tab-pane fade" id="tab-sales">
            <div class="row g-3 mb-3">
                <div class="col-xl-5">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-funnel me-1" style="color:var(--gold)"></i>กรวยการขาย</h6>
                            <span class="hint ms-auto">ทุกช่วงเวลา · คลิกเพื่อดูใบเสนอราคา</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>ใบเสนอราคาทั้งหมดในระบบแยกตามขั้นของการขาย: ร่างยังไม่ส่ง → ส่งให้ลูกค้าแล้ว → ลูกค้ายืนยัน → ปิดไม่สำเร็จ
                                ความยาวแท่งคือจำนวนใบ ตัวเลขขวามือคือจำนวนใบและมูลค่ารวม ใช้ดูว่างานไปค้างอยู่ขั้นไหนมากที่สุด</span>
                        </div>
                        <div class="pnl-bd tight">
                            <?php foreach ($funnel_rows as $f): ?>
                                <div class="fn-row funnel-card" data-status="<?= $f['key'] ?>" data-label="<?= $h($f['label']) ?>">
                                    <div class="fn-lb">
                                        <?php if ($f['key'] === 'Cancelled'): ?><i class="bi bi-x-circle-fill" style="color:var(--crit)"></i><?php endif; ?>
                                        <?= $h($f['label']) ?>
                                    </div>
                                    <div class="fn-track"><span class="fn-fill" style="width:<?= round($f['cnt'] / $funnel_max * 100) ?>%;background:<?= $f['color'] ?>"></span></div>
                                    <div class="fn-num"><b><?= $f['cnt'] ?> ใบ</b><small>฿<?= number_format($f['val']) ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="pnl-note">
                            อัตราปิดการขายเดือนนี้ <b style="color:var(--ink)"><?= $conversion_rate ?>%</b>
                            (ยืนยัน <?= $q_approved ?> จาก <?= $q_total ?> ใบ) · เสียงาน <?= $q_cancelled ?> ใบ
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-file-earmark-text me-1" style="color:var(--gold)"></i>ใบเสนอราคา</h6><span class="hint ms-auto">เดือนนี้</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>สถิติใบเสนอราคาที่ <b>ออกในเดือนที่เลือก</b> (นับตามวันที่ออกใบ) — Conversion คืออัตราปิดการขาย
                                (ใบที่ลูกค้ายืนยัน ÷ ใบที่ออกทั้งหมด) · ช่อง <b>รอตอบ (ค้างสะสม)</b> เป็นยอดค้างปัจจุบันของทุกเดือนรวมกัน ไม่ใช่เฉพาะเดือนนี้
                                ส่วนด้านล่างคือใบที่จะหมดอายุใน 7 วัน ควรรีบตามลูกค้าให้ตัดสินใจก่อนใบหมดอายุ</span>
                        </div>
                        <div class="pnl-bd">
                            <div class="row g-2 mb-3">
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num"><?= $q_total ?></div><div class="l">ออกทั้งหมด</div></div></div>
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num"><?= $q_today_total ?></div><div class="l">ออกวันนี้</div></div></div>
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num" style="color:var(--ok)"><?= $q_approved ?></div><div class="l">ยืนยันแล้ว</div></div></div>
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num" style="color:#9a6a06"><?= $q_pending ?></div><div class="l">รอตอบ (ค้างสะสม)</div></div></div>
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num" style="color:var(--crit)"><?= $q_cancelled ?></div><div class="l">ปิดไม่สำเร็จ</div></div></div>
                                <div class="col-4 col-lg-2"><div class="ministat"><div class="v num" style="color:#2a78d6"><?= $conversion_rate ?>%</div><div class="l">Conversion</div></div></div>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-alarm" style="color:var(--crit)"></i>
                                <span class="fw-bold" style="font-size:.8rem">ใกล้หมดอายุใน 7 วัน</span>
                            </div>
                            <?php if ($expiring_quotes->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="tbl">
                                        <thead><tr><th>ใบเสนอราคา</th><th>ลูกค้า</th><th>หมดอายุ</th><th class="text-end">มูลค่า</th></tr></thead>
                                        <tbody>
                                            <?php while ($eq = $expiring_quotes->fetch_assoc()): ?>
                                                <tr>
                                                    <td><a class="lk" href="quotation_view.php?id=<?= $eq['id'] ?>"><?= $h($eq['event_name'] ?: $eq['quote_no']) ?></a></td>
                                                    <td class="text-dim"><?= $h($eq['company_name'] ?: '-') ?></td>
                                                    <td class="num" style="color:var(--crit)"><?= date('d M', strtotime($eq['expiry_date'])) ?></td>
                                                    <td class="text-end num fw-bold">฿<?= number_format($eq['grand_total']) ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-dim" style="font-size:.78rem"><i class="bi bi-check-circle me-1" style="color:var(--ok)"></i>ไม่มีใบเสนอราคาใกล้หมดอายุ</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-people me-1" style="color:var(--gold)"></i>ผลงานทีมขาย</h6>
                            <span class="hint ms-auto">เดือนนี้ · เทียบเป้าหมาย</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>ผลงานเซลล์รายคนในเดือนที่เลือก: จำนวนงานที่ปิดได้ รายได้รวม เงินมัดจำที่เก็บได้ และ % เทียบเป้าหมายเดือนนั้น
                                (เขียว = ถึงเป้า, เหลือง = เกินครึ่งทาง, แดง = ต่ำกว่าครึ่ง)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="tbl">
                                <thead><tr><th>พนักงาน</th><th class="text-center">งาน</th><th class="text-end">รายได้</th><th class="text-end">มัดจำ</th><th class="text-end">เป้าหมาย</th><th class="text-end" style="width:130px">% เป้า</th></tr></thead>
                                <tbody>
                                    <?php if (count($staff_data) > 0): ?>
                                        <?php foreach ($staff_data as $sd):
                                            $pct = $sd['target_amount'] > 0 ? round(($sd['total_revenue'] / $sd['target_amount']) * 100, 1) : 0;
                                            $pc = $pct >= 100 ? 'var(--ok)' : ($pct >= 50 ? '#9a6a06' : 'var(--crit)');
                                            $pb = $pct >= 100 ? 'var(--ok)' : ($pct >= 50 ? 'var(--warn)' : 'var(--crit)');
                                            ?>
                                            <tr>
                                                <td><span class="who"><?= $h(mb_substr($sd['name'], 0, 1, 'UTF-8')) ?></span><?= $h($sd['name']) ?></td>
                                                <td class="text-center num"><?= $sd['total_events'] ?></td>
                                                <td class="text-end num fw-bold">฿<?= number_format($sd['total_revenue']) ?></td>
                                                <td class="text-end num text-dim">฿<?= number_format($sd['total_deposit']) ?></td>
                                                <td class="text-end num text-dim">฿<?= number_format($sd['target_amount']) ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                                        <span class="fw-bold num" style="color:<?= $pc ?>"><?= $pct ?>%</span>
                                                        <div class="prog"><span style="width:<?= min($pct, 100) ?>%;background:<?= $pb ?>"></span></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-dim py-4">ไม่มีข้อมูล</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-trophy me-1" style="color:var(--gold)"></i>งานมูลค่าสูงสุด</h6><span class="hint ms-auto">10 อันดับแรก · ทุกช่วงเวลา</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>10 งานที่มีมูลค่าสูงที่สุด <b>นับทุกช่วงเวลา ไม่จำกัดเดือนที่เลือก</b> (แต่ยังกรองตามโรงแรม/เซลล์)
                                ใช้ดูว่างานใหญ่มาจากลูกค้าหรือประเภทงานแบบไหน เพื่อนำไปต่อยอดการขาย</span>
                        </div>
                        <div class="table-responsive">
                            <table class="tbl">
                                <thead><tr><th style="width:34px">#</th><th>ชื่องาน</th><th>วันที่</th><th class="text-end">มูลค่า</th></tr></thead>
                                <tbody>
                                    <?php $rk = 0; while ($te = $top_events->fetch_assoc()): $rk++; ?>
                                        <tr>
                                            <td><span class="rank"><?= $rk ?></span></td>
                                            <td>
                                                <div class="text-truncate" style="max-width:190px"><?= $h($te['function_name']) ?></div>
                                                <span class="tag"><?= $h($te['type_name'] ?: '-') ?></span>
                                            </td>
                                            <td class="num text-dim"><?= $te['event_date'] ? date('d M', strtotime($te['event_date'])) : '-' ?></td>
                                            <td class="text-end num fw-bold">฿<?= number_format($te['total_amount']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB 3: ปฏิบัติการ ============ -->
        <div class="tab-pane fade" id="tab-ops">
            <div class="row g-3 mb-3">
                <div class="col-xl-5">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-calendar-check me-1" style="color:var(--gold)"></i>งานในคิวข้างหน้า</h6>
                            <span class="hint ms-auto">คลิกเพื่อดูรายการ</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>งานที่อนุมัติแล้วและยังไม่ถึงวันจัด นับสะสมภายใน 7 / 30 / 90 / 180 วันข้างหน้า
                                (ช่วงยาวจะรวมช่วงสั้นไว้ด้วย) ความยาวแท่งคือมูลค่างาน ใช้คาดการณ์รายได้และเตรียมห้อง ทีมงาน วัตถุดิบล่วงหน้า</span>
                        </div>
                        <div class="pnl-bd tight">
                            <?php foreach ($pipe_rows as $p): ?>
                                <div class="fn-row pipe-card" data-days="<?= $p['days'] ?>" data-label="<?= $h($p['label']) ?>">
                                    <div class="fn-lb">ภายใน <?= $h($p['label']) ?></div>
                                    <div class="fn-track"><span class="fn-fill" style="width:<?= round($p['val'] / $pipe_max * 100) ?>%;background:#b89441"></span></div>
                                    <div class="fn-num"><b><?= $p['cnt'] ?> งาน</b><small>฿<?= number_format($p['val']) ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="pnl">
                        <div class="pnl-hd">
                            <h6><i class="bi bi-door-open me-1" style="color:var(--gold)"></i>การใช้ห้องประชุม</h6>
                            <span class="hint ms-auto">จำนวนงานเดือนนี้ · คลิกแท่งเพื่อดูรายละเอียด</span>
                        </div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>จำนวนงานที่จองใช้ห้องประชุมแต่ละห้องในเดือนที่เลือก (เฉพาะห้องที่เปิดใช้งาน)
                                ห้องที่แท่งสั้นคือห้องที่ยังว่างมาก ควรนำไปเสนอขายหรือทบทวนราคา</span>
                        </div>
                        <div class="pnl-bd">
                            <?php if (count($room_rows) > 0): ?>
                                <div class="chart-box" style="height:<?= max(160, count($room_rows) * 34 + 30) ?>px"><canvas id="roomChart"></canvas></div>
                            <?php else: ?>
                                <div class="chart-empty">ยังไม่มีการจองห้องในเดือนนี้</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pnl">
                <div class="pnl-hd">
                    <h6><i class="bi bi-list-check me-1" style="color:var(--gold)"></i>ตารางงาน</h6>
                    <div class="seg ms-auto" id="evSeg">
                        <button type="button" class="seg-btn active" data-ev="today">วันนี้ (<?= $today_events_list->num_rows ?>)</button>
                        <button type="button" class="seg-btn" data-ev="upcoming">กำลังจะมาถึง (<?= $upcoming_all->num_rows ?>)</button>
                    </div>
                </div>
                <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                    <span><b>วันนี้</b> = งานทั้งหมดของวันที่เลือก เรียงตามเวลาเริ่มงาน ใช้เช็กความพร้อมก่อนงานเริ่ม ·
                        <b>กำลังจะมาถึง</b> = 10 งานถัดไปที่ยืนยันแล้ว เรียงตามวันที่ใกล้ที่สุด กดชื่องานเพื่อเปิดรายละเอียดงาน</span>
                </div>
                <div class="table-responsive" id="evToday">
                    <table class="tbl">
                        <thead><tr><th>ชื่องาน</th><th>ประเภท</th><th>เซลล์</th><th>โรงแรม</th><th>ห้อง</th><th>เวลา</th><th class="text-center">คน</th><th class="text-end">มูลค่า</th></tr></thead>
                        <tbody>
                            <?php if ($today_events_list->num_rows > 0): ?>
                                <?php while ($ev = $today_events_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><a class="lk" href="view.php?id=<?= $ev['id'] ?>"><?= $h($ev['function_name']) ?></a></td>
                                        <td><span class="tag"><?= $h($ev['type_name'] ?: '-') ?></span></td>
                                        <td class="text-dim"><?= $h($ev['staff_name'] ?: '-') ?></td>
                                        <td class="text-dim"><?= $h($ev['company_name'] ?: '-') ?></td>
                                        <td class="text-dim"><?= $h($ev['room_name'] ?: '-') ?></td>
                                        <td class="num"><?= $ev['start_time'] ? date('H:i', strtotime($ev['start_time'])) . '-' . date('H:i', strtotime($ev['end_time'])) : '-' ?></td>
                                        <td class="text-center num"><?= $ev['pax'] ?></td>
                                        <td class="text-end num fw-bold">฿<?= number_format($ev['total_amount']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center text-dim py-4">ไม่มีงานในวันที่เลือก</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive d-none" id="evUpcoming">
                    <table class="tbl">
                        <thead><tr><th>ชื่องาน</th><th>ประเภท</th><th>วันที่</th><th>เวลา</th><th>โรงแรม</th><th>ห้อง</th><th class="text-end">มูลค่า</th></tr></thead>
                        <tbody>
                            <?php if ($upcoming_all->num_rows > 0): ?>
                                <?php while ($ua = $upcoming_all->fetch_assoc()): ?>
                                    <tr>
                                        <td><a class="lk" href="view.php?id=<?= $ua['id'] ?>"><?= $h($ua['function_name']) ?></a></td>
                                        <td><span class="tag"><?= $h($ua['type_name'] ?: '-') ?></span></td>
                                        <td class="num"><?= $ua['start_time'] ? date('d M Y', strtotime($ua['start_time'])) : '-' ?></td>
                                        <td class="num text-dim"><?= $ua['start_time'] ? date('H:i', strtotime($ua['start_time'])) . '-' . date('H:i', strtotime($ua['end_time'])) : '-' ?></td>
                                        <td class="text-dim"><?= $h($ua['company_name'] ?: '-') ?></td>
                                        <td class="text-dim"><?= $h($ua['room_name'] ?: '-') ?></td>
                                        <td class="text-end num fw-bold">฿<?= number_format($ua['total_amount']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-dim py-4">ไม่มีงานที่กำลังจะมาถึง</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ TAB 4: การเงิน ============ -->
        <div class="tab-pane fade" id="tab-finance">
            <div class="row g-3 mb-3">
                <div class="col-xl-7">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-wallet2 me-1" style="color:var(--gold)"></i>เงินมัดจำ</h6><span class="hint ms-auto">คลิกการ์ด "รับเดือนนี้" เพื่อดูรายการโอน</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>เงินมัดจำที่รับเข้ามาจริงในวันนี้และทั้งเดือน · <b>คงเหลือในระบบ</b> คือมัดจำของงานที่ยังไม่ได้จัด ·
                                <b>งานค้างมัดจำ</b> คือจำนวนงานที่ยืนยันแล้วแต่ยังไม่วางเงิน ซึ่งมีความเสี่ยงถูกยกเลิก ควรเร่งติดตาม</span>
                        </div>
                        <div class="pnl-bd">
                            <div class="row g-2">
                                <div class="col-6 col-lg-3"><div class="ministat"><div class="v num" style="color:var(--ok)">฿<?= number_format($deposit_received) ?></div><div class="l">รับวันนี้</div></div></div>
                                <div class="col-6 col-lg-3"><div class="ministat deposit-clickable" style="cursor:pointer"><div class="v num" style="color:var(--ok)">฿<?= number_format($deposit_received_mtd) ?></div><div class="l">รับเดือนนี้</div></div></div>
                                <div class="col-6 col-lg-3"><div class="ministat"><div class="v num" style="color:#9a6a06">฿<?= number_format($total_deposit_balance) ?></div><div class="l">คงเหลือในระบบ</div></div></div>
                                <div class="col-6 col-lg-3"><div class="ministat"><div class="v num" style="color:var(--crit)"><?= $deposit_pending_events ?></div><div class="l">งานค้างมัดจำ</div></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-cash-coin me-1" style="color:var(--gold)"></i>กำไรขั้นต้น (GOP)</h6><span class="hint ms-auto">เดือนนี้</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>คำนวณจากรายการเงินที่บันทึกจริงในเดือนนี้: กำไร = รายรับ − ต้นทุน
                                ส่วน Margin คือกำไรคิดเป็น % ของรายรับ ยิ่งสูงยิ่งดี</span>
                        </div>
                        <div class="pnl-bd">
                            <div class="chart-box" style="height:150px"><canvas id="gopChart"></canvas></div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2" style="border-top:1px solid var(--line)">
                                <span class="text-dim" style="font-size:.78rem">GOP Margin</span>
                                <span class="fw-bold num" style="color:<?= $gop_forecast >= 0 ? 'var(--ok)' : 'var(--crit)' ?>"><?= $gop_margin ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-6">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-credit-card me-1" style="color:var(--gold)"></i>ช่องทางรับชำระเงิน</h6><span class="hint ms-auto">เดือนนี้</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>ยอดเงินที่รับเข้ามาแยกตามวิธีชำระ (เงินสด / โอน / บัตรเครดิต ฯลฯ) กดที่แถวเพื่อดูรายการรับเงินทั้งหมดของช่องทางนั้น</span>
                        </div>
                        <?php if (count($pay_labels) > 0): ?>
                            <div class="table-responsive">
                                <table class="tbl">
                                    <thead><tr><th>ช่องทาง</th><th class="text-end">ยอดเงิน</th><th class="text-end">สัดส่วน</th></tr></thead>
                                    <tbody>
                                        <?php $pay_sum = array_sum($pay_values); foreach ($pay_labels as $i => $pl): ?>
                                            <tr class="drill-pay" data-label="<?= $h($pl) ?>" style="cursor:pointer">
                                                <td><?= $h($pl) ?></td>
                                                <td class="text-end num fw-bold">฿<?= number_format($pay_values[$i]) ?></td>
                                                <td class="text-end num text-dim"><?= $pay_sum > 0 ? round($pay_values[$i] / $pay_sum * 100) : 0 ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="chart-empty">ยังไม่มีรายการรับชำระในเดือนนี้</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="pnl">
                        <div class="pnl-hd"><h6><i class="bi bi-clipboard-data me-1" style="color:var(--gold)"></i>ตัวชี้วัดสำคัญ</h6><span class="hint ms-auto">เดือนนี้</span></div>
                        <div class="pnl-desc"><i class="bi bi-info-circle"></i>
                            <span>สรุปตัวเลขสุขภาพธุรกิจของเดือน — <b>อัตรายกเลิก</b> ถ้าเกิน 10% ถือว่าเสี่ยง ควรตรวจสอบสาเหตุ ·
                                <b>ลูกค้าใช้ซ้ำ</b> ยิ่งสูงยิ่งดี แปลว่าลูกค้าเก่ากลับมาจองอีก</span>
                        </div>
                        <div class="table-responsive">
                            <table class="tbl">
                                <tbody>
                                    <tr><td class="text-dim">รายรับที่บันทึกจริง</td><td class="text-end num fw-bold">฿<?= number_format($total_income_mtd) ?></td></tr>
                                    <tr><td class="text-dim">ต้นทุนที่บันทึกจริง</td><td class="text-end num fw-bold">฿<?= number_format($total_cost_mtd) ?></td></tr>
                                    <tr><td class="text-dim">กำไรขั้นต้น (GOP)</td><td class="text-end num fw-bold" style="color:<?= $gop_forecast >= 0 ? 'var(--ok)' : 'var(--crit)' ?>">฿<?= number_format($gop_forecast) ?></td></tr>
                                    <tr><td class="text-dim">มูลค่าเฉลี่ยต่องาน</td><td class="text-end num fw-bold">฿<?= number_format($avg_deal) ?></td></tr>
                                    <tr><td class="text-dim">อัตราการยกเลิกงาน</td><td class="text-end num fw-bold" style="color:<?= $cancel_rate > 10 ? 'var(--crit)' : 'var(--ok)' ?>"><?= $cancel_rate ?>% (<?= $cancelled_count ?> งาน)</td></tr>
                                    <tr><td class="text-dim">ลูกค้าที่กลับมาใช้ซ้ำ <span class="tag">สะสมทั้งหมด</span></td><td class="text-end num fw-bold"><?= $repeat_pct ?>% (<?= $repeat_cust ?>/<?= $total_cust ?> ราย)</td></tr>
                                    <tr><td class="text-dim">ห้องที่ถูกใช้งาน</td><td class="text-end num fw-bold"><?= $mtd_rooms_used ?> ห้อง</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- DRILL-DOWN MODAL -->
<div class="modal fade" id="drillModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden">
            <div class="modal-header py-2" style="background:#16181d;color:#fff;border:0">
                <h6 class="modal-title fw-bold" style="font-size:.9rem"><i class="bi bi-search me-2" style="color:#b89441"></i><span id="drillTitle">รายละเอียด</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" id="drillBody"></div>
        </div>
    </div>
</div>

<script>
/* =========================================================
   THEME & HELPERS
   ========================================================= */
const SERIES = ['#b89441','#2a78d6','#eb6834','#1baf7a','#4a3aa7','#e87ba4','#008300','#e34948'];
const INK = '#111318', INK2 = '#5b6470', MUTED = '#8a9099', GRID = '#eceef1';

Chart.defaults.font.family = "'Sarabun','Inter',sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = INK2;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(22,24,29,.95)';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };
Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
Chart.defaults.plugins.tooltip.displayColors = false;

const baht  = n => '฿' + Math.round(Number(n) || 0).toLocaleString('th-TH');
const short = n => {
    n = Number(n) || 0;
    const a = Math.abs(n);
    if (a >= 1e6) return '฿' + (n / 1e6).toFixed(a >= 1e7 ? 0 : 1) + 'M';
    if (a >= 1e3) return '฿' + Math.round(n / 1e3) + 'K';
    return '฿' + Math.round(n);
};
const plain = n => Math.round(Number(n) || 0).toLocaleString('th-TH');

/* ป้ายตัวเลขบนแท่ง — ให้อ่านค่าได้โดยไม่ต้องเล็งแกน */
const barValues = {
    id: 'barValues',
    afterDatasetsDraw(chart, args, opts) {
        const fmt = opts.fmt || short;
        const ctx = chart.ctx;
        ctx.save();
        ctx.font = "600 11px 'Sarabun',sans-serif";
        ctx.fillStyle = INK2;
        ctx.textBaseline = 'middle';
        chart.data.datasets.forEach((ds, di) => {
            const meta = chart.getDatasetMeta(di);
            if (meta.hidden) return;
            meta.data.forEach((el, i) => {
                const v = ds.data[i];
                if (!v) return;
                if (chart.options.indexAxis === 'y') {
                    ctx.textAlign = 'left';
                    ctx.fillText(fmt(v), el.x + 7, el.y);
                } else {
                    ctx.textAlign = 'center';
                    ctx.fillText(fmt(v), el.x, el.y - 9);
                }
            });
        });
        ctx.restore();
    }
};

/* ยอดรวมกลางโดนัท */
const donutCenter = {
    id: 'donutCenter',
    afterDraw(chart, args, opts) {
        const total = chart.data.datasets[0].data.reduce((s, v) => s + (Number(v) || 0), 0);
        const { top, bottom, left, right } = chart.chartArea;
        const x = (left + right) / 2, y = (top + bottom) / 2;
        const ctx = chart.ctx;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.fillStyle = MUTED;
        ctx.font = "600 10px 'Sarabun',sans-serif";
        ctx.fillText(opts.label || 'รวม', x, y - 11);
        ctx.fillStyle = INK;
        ctx.font = "700 16px 'Sarabun',sans-serif";
        ctx.fillText(short(total), x, y + 9);
        ctx.restore();
    }
};

const axisMoney = { grid: { color: GRID, drawTicks: false }, border: { display: false }, ticks: { callback: v => short(v), padding: 6 } };
const axisCat   = { grid: { display: false }, border: { display: false }, ticks: { padding: 4, color: INK2, font: { size: 11 } } };

/* =========================================================
   DRILL-DOWN
   ========================================================= */
const drillData = <?= json_encode($dd, JSON_UNESCAPED_UNICODE) ?>;

function showDrill(title, rows, columns) {
    document.getElementById('drillTitle').textContent = title;
    let html;
    if (!rows || rows.length === 0) {
        html = '<div class="text-center py-5" style="color:#8a9099">ไม่มีข้อมูลรายละเอียด</div>';
    } else {
        html = '<div class="table-responsive"><table class="tbl"><thead><tr>';
        columns.forEach(c => { html += '<th' + (c.type === 'money' ? ' class="text-end"' : '') + '>' + c.label + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(r => {
            html += '<tr>';
            columns.forEach(c => {
                let val = (r[c.key] === null || r[c.key] === undefined || r[c.key] === '') ? '-' : r[c.key];
                let cls = '';
                if (c.type === 'money') { val = baht(r[c.key]); cls = ' class="text-end num fw-bold"'; }
                if (c.type === 'link')   val = '<a class="lk" href="view.php?id=' + r.id + '">' + val + '</a>';
                if (c.type === 'q_link') val = '<a class="lk" href="quotation_view.php?id=' + r.id + '">' + val + '</a>';
                html += '<td' + cls + '>' + val + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        const sum = rows.reduce((s, r) => s + Number(r.total_amount || r.grand_total || r.amount || 0), 0);
        html += '<div class="text-end mt-2 px-2" style="font-size:.78rem;color:#5b6470">ทั้งหมด <b>' + rows.length + '</b> รายการ · รวม <b>' + baht(sum) + '</b></div>';
    }
    document.getElementById('drillBody').innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('drillModal')).show();
}
const filterDrill = (key, field, value) => (drillData[key] || []).filter(r => r[field] === value);

const eventCols = [
    { key: 'function_name', label: 'ชื่องาน', type: 'link' },
    { key: 'type_name', label: 'ประเภท' },
    { key: 'company_name', label: 'โรงแรม' },
    { key: 'staff_name', label: 'เซลล์' },
    { key: 'ev_date', label: 'วันที่' },
    { key: 'ev_time', label: 'เวลา' },
    { key: 'pax', label: 'คน' },
    { key: 'total_amount', label: 'มูลค่า', type: 'money' }
];
const eventColsShort = [
    { key: 'function_name', label: 'ชื่องาน', type: 'link' },
    { key: 'type_name', label: 'ประเภท' },
    { key: 'company_name', label: 'โรงแรม' },
    { key: 'ev_date', label: 'วันที่' },
    { key: 'total_amount', label: 'มูลค่า', type: 'money' }
];
const quoteCols = [
    { key: 'quote_no', label: 'เลขที่', type: 'q_link' },
    { key: 'event_name', label: 'ชื่องาน' },
    { key: 'company_name', label: 'โรงแรม' },
    { key: 'status', label: 'สถานะ' },
    { key: 'ev_date', label: 'วันจัดงาน' },
    { key: 'staff_name', label: 'เซลล์' },
    { key: 'grand_total', label: 'มูลค่า', type: 'money' }
];
const depositCols = [
    { key: 'function_name', label: 'ชื่องาน' },
    { key: 'company_name', label: 'โรงแรม' },
    { key: 'detail', label: 'รายละเอียด' },
    { key: 'amount', label: 'จำนวนเงิน', type: 'money' },
    { key: 'payment_method', label: 'ช่องทาง' },
    { key: 'tx_date', label: 'วันที่' }
];

/* =========================================================
   CHART DATA (from PHP)
   ========================================================= */
const D = {
    trend:   <?= json_encode($monthly_trend, JSON_UNESCAPED_UNICODE) ?>,
    type:    <?= json_encode($type_rows, JSON_UNESCAPED_UNICODE) ?>,
    company: <?= json_encode($comp_rows, JSON_UNESCAPED_UNICODE) ?>,
    room:    <?= json_encode($room_rows, JSON_UNESCAPED_UNICODE) ?>,
    gop:     { income: <?= (float) $total_income_mtd ?>, cost: <?= (float) $total_cost_mtd ?>, profit: <?= (float) $gop_forecast ?> },
    mix: {
        source: { title: 'แหล่งลูกค้า', labels: <?= json_encode($mix_source['labels'], JSON_UNESCAPED_UNICODE) ?>, values: <?= json_encode($mix_source['values']) ?>, key: 'source',  field: 'lead_source',    cols: 'short' },
        period: { title: 'ช่วงเวลาจัดงาน', labels: <?= json_encode($mix_period['labels'], JSON_UNESCAPED_UNICODE) ?>, values: <?= json_encode($mix_period['values']) ?>, key: 'period',  field: 'period',         cols: 'full' },
        pay:    { title: 'ช่องทางชำระเงิน', labels: <?= json_encode($mix_pay['labels'], JSON_UNESCAPED_UNICODE) ?>,    values: <?= json_encode($mix_pay['values']) ?>,    key: 'payment', field: 'payment_method', cols: 'deposit' }
    }
};
const COLS = { short: eventColsShort, full: eventCols, deposit: depositCols };

/* =========================================================
   CHART BUILDERS (lazy — สร้างเมื่อแท็บถูกเปิด)
   ========================================================= */
const charts = {};

function buildTrend() {
    const el = document.getElementById('trendChart');
    if (!el) return;
    const labels = D.trend.map(r => r.label), values = D.trend.map(r => r.total);
    const maxIdx = values.indexOf(Math.max(...values));
    const g = el.getContext('2d').createLinearGradient(0, 0, 0, 240);
    g.addColorStop(0, 'rgba(184,148,65,.28)');
    g.addColorStop(1, 'rgba(184,148,65,0)');

    charts.trend = new Chart(el, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'รายได้', data: values,
                borderColor: SERIES[0], backgroundColor: g, borderWidth: 2,
                fill: true, tension: .2, cubicInterpolationMode: 'monotone',
                pointBackgroundColor: SERIES[0], pointBorderColor: '#fff', pointBorderWidth: 2,
                pointRadius: 5, pointHoverRadius: 8, pointHitRadius: 24
            }]
        },
        options: {
            layout: { padding: { top: 24, right: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => 'รายได้ ' + baht(c.parsed.y) } },
                pointTips: { indexes: [maxIdx, values.length - 1] }
            },
            scales: { y: { beginAtZero: true, ...axisMoney }, x: axisCat }
        },
        plugins: [{
            id: 'pointTips',
            afterDatasetsDraw(chart, a, opts) {
                const meta = chart.getDatasetMeta(0), ctx = chart.ctx;
                ctx.save();
                ctx.font = "700 11px 'Sarabun',sans-serif";
                ctx.fillStyle = INK;
                ctx.textAlign = 'center';
                [...new Set(opts.indexes)].forEach(i => {
                    const p = meta.data[i];
                    if (p) ctx.fillText(short(chart.data.datasets[0].data[i]), p.x, p.y - 14);
                });
                ctx.restore();
            }
        }]
    });
    el.onclick = e => {
        const pts = charts.trend.getElementsAtEventForMode(e, 'index', { intersect: false }, true);
        if (!pts.length) return;
        const label = labels[pts[0].index];
        showDrill('งานของเดือน ' + label, (drillData.month || []).filter(r => r.month_label === label), eventColsShort);
    };
}

function hBar(elId, rows, color, fmt, onPick) {
    const el = document.getElementById(elId);
    if (!el || !rows.length) return null;
    const c = new Chart(el, {
        type: 'bar',
        data: {
            labels: rows.map(r => r.label),
            datasets: [{
                data: rows.map(r => r.value !== undefined ? r.value : r.mtd),
                backgroundColor: color, borderRadius: 4, borderSkipped: false,
                barThickness: 14, maxBarThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            layout: { padding: { right: 58 } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => fmt(ctx.parsed.x) } },
                barValues: { fmt }
            },
            scales: {
                x: { beginAtZero: true, display: false, grid: { display: false } },
                y: axisCat
            }
        },
        plugins: [barValues]
    });
    if (onPick) el.onclick = e => {
        const pts = c.getElementsAtEventForMode(e, 'y', { intersect: false }, true);
        if (pts.length) onPick(c.data.labels[pts[0].index]);
    };
    return c;
}

function buildOverview() {
    buildTrend();
    charts.type = hBar('typeChart', D.type.slice(0, 8), SERIES[0], baht,
        label => showDrill('ประเภทงาน: ' + label, filterDrill('type', 'type_name', label), eventColsShort));
    charts.company = hBar('companyChart', D.company, SERIES[1], baht,
        label => showDrill('โรงแรม: ' + label, filterDrill('company', 'company_name', label), eventColsShort));
    buildMix('source');
}

let mixKey = 'source';
function buildMix(which) {
    mixKey = which;
    const m = D.mix[which], el = document.getElementById('mixChart');
    if (!el) return;
    const total = m.values.reduce((s, v) => s + v, 0);

    if (charts.mix) charts.mix.destroy();
    charts.mix = new Chart(el, {
        type: 'doughnut',
        data: {
            labels: m.labels,
            datasets: [{
                data: m.values, backgroundColor: SERIES.slice(0, m.labels.length),
                borderColor: '#fff', borderWidth: 2, hoverOffset: 6
            }]
        },
        options: {
            cutout: '64%',
            plugins: {
                legend: { display: false },
                donutCenter: { label: m.title },
                tooltip: { callbacks: { label: c => c.label + ' · ' + baht(c.parsed) + ' (' + (total ? Math.round(c.parsed / total * 100) : 0) + '%)' } }
            }
        },
        plugins: [donutCenter]
    });

    const lg = document.getElementById('mixLegend');
    lg.innerHTML = m.labels.length
        ? m.labels.map((l, i) => '<li data-label="' + l.replace(/"/g, '&quot;') + '">'
            + '<span class="sw" style="background:' + SERIES[i] + '"></span>'
            + '<span class="nm">' + l + '</span>'
            + '<span class="vl">' + baht(m.values[i]) + '</span>'
            + '<span class="pc">' + (total ? Math.round(m.values[i] / total * 100) : 0) + '%</span></li>').join('')
        : '<li class="text-center d-block" style="color:#8a9099">ไม่มีข้อมูลในเดือนนี้</li>';

    const pick = label => {
        const rows = label === 'อื่นๆ'
            ? (drillData[m.key] || []).filter(r => !m.labels.includes(r[m.field]))
            : filterDrill(m.key, m.field, label);
        showDrill(m.title + ': ' + label, rows, COLS[m.cols]);
    };
    lg.querySelectorAll('li[data-label]').forEach(li => li.onclick = () => pick(li.dataset.label));
    el.onclick = e => {
        const pts = charts.mix.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
        if (pts.length) pick(m.labels[pts[0].index]);
    };
}

function buildOps() {
    charts.room = hBar('roomChart', D.room, SERIES[3], v => plain(v) + ' งาน',
        label => showDrill('ห้องประชุม: ' + label, filterDrill('room', 'room_name', label), eventCols));
}

function buildFinance() {
    const el = document.getElementById('gopChart');
    if (!el) return;
    charts.gop = new Chart(el, {
        type: 'bar',
        data: {
            labels: ['รายรับ', 'ต้นทุน', 'กำไร'],
            datasets: [{
                data: [D.gop.income, D.gop.cost, D.gop.profit],
                backgroundColor: [SERIES[1], SERIES[2], D.gop.profit >= 0 ? '#0ca30c' : '#d03b3b'],
                borderRadius: 4, borderSkipped: false, barThickness: 34
            }]
        },
        options: {
            layout: { padding: { top: 22 } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => baht(c.parsed.y) } },
                barValues: { fmt: short }
            },
            scales: { y: { beginAtZero: true, display: false }, x: axisCat }
        },
        plugins: [barValues]
    });
}

/* =========================================================
   TABS — สร้างกราฟครั้งแรกที่เปิดแท็บ (canvas ในแท็บที่ซ่อนอยู่วัดขนาดไม่ได้)
   ========================================================= */
const builders = { 'tab-overview': buildOverview, 'tab-sales': null, 'tab-ops': buildOps, 'tab-finance': buildFinance };
const built = {};
function buildTab(id) {
    if (built[id]) return;
    built[id] = true;
    if (builders[id]) builders[id]();
}
document.querySelectorAll('#execTab [data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => buildTab(e.target.dataset.bsTarget.replace('#', '')));
});
buildTab('tab-overview');

/* สลับมุมมองสัดส่วนธุรกิจ */
document.querySelectorAll('#mixSeg .seg-btn').forEach(b => b.addEventListener('click', function () {
    document.querySelectorAll('#mixSeg .seg-btn').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    buildMix(this.dataset.mix);
}));

/* สลับตารางงานวันนี้ / กำลังจะมาถึง */
document.querySelectorAll('#evSeg .seg-btn').forEach(b => b.addEventListener('click', function () {
    document.querySelectorAll('#evSeg .seg-btn').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('evToday').classList.toggle('d-none', this.dataset.ev !== 'today');
    document.getElementById('evUpcoming').classList.toggle('d-none', this.dataset.ev !== 'upcoming');
}));

/* =========================================================
   CLICK HANDLERS
   ========================================================= */
document.querySelectorAll('.pipe-card').forEach(c => c.addEventListener('click', function () {
    showDrill('งานที่จะมาถึงภายใน ' + this.dataset.label, drillData.future[this.dataset.days] || [], eventCols);
}));

document.querySelectorAll('.funnel-card').forEach(c => c.addEventListener('click', function () {
    showDrill('ใบเสนอราคา: ' + this.dataset.label, filterDrill('pipeline', 'status', this.dataset.status), quoteCols);
}));

document.querySelectorAll('.alert-clickable').forEach(c => c.addEventListener('click', function () {
    const t = this.dataset.alertType;
    showDrill(this.dataset.alertLabel, drillData[t] || [], t === 'alert_overdue' ? quoteCols : eventColsShort);
}));

document.querySelectorAll('.deposit-clickable').forEach(c => c.addEventListener('click', () => {
    showDrill('รายการรับเงินมัดจำเดือนนี้', drillData.deposit || [], depositCols);
}));

document.querySelectorAll('.drill-type').forEach(r => r.addEventListener('click', function () {
    showDrill('ประเภทงาน: ' + this.dataset.label, filterDrill('type', 'type_name', this.dataset.label), eventColsShort);
}));

document.querySelectorAll('.drill-pay').forEach(r => r.addEventListener('click', function () {
    showDrill('ช่องทางชำระเงิน: ' + this.dataset.label, filterDrill('payment', 'payment_method', this.dataset.label), depositCols);
}));

document.getElementById('tileToday')?.addEventListener('click', () => {
    showDrill('งานวันที่ <?= date('d M Y', strtotime($selected_date)) ?>',
        (drillData.type || []).filter(r => r.ev_date === '<?= $selected_date ?>'), eventCols);
});

document.getElementById('tileMtd')?.addEventListener('click', () => {
    showDrill('ผลงานทีมขายเทียบเป้าหมาย', <?= json_encode(array_map(function ($s) {
        $pct = $s['target_amount'] > 0 ? round(($s['total_revenue'] / $s['target_amount']) * 100, 1) : 0;
        return [
            'function_name' => $s['name'],
            'type_name' => $s['total_events'] . ' งาน',
            'company_name' => $pct . '% ของเป้า',
            'ev_date' => '-',
            'total_amount' => $s['total_revenue']
        ];
    }, $staff_data), JSON_UNESCAPED_UNICODE) ?>, eventColsShort);
});

document.getElementById('tileConv')?.addEventListener('click', () => {
    showDrill('ใบเสนอราคาทั้งหมด', drillData.pipeline || [], quoteCols);
});

document.getElementById('tileDeposit')?.addEventListener('click', () => {
    showDrill('รายการรับเงินมัดจำเดือนนี้', drillData.deposit || [], depositCols);
});
</script>

<?php include "footer.php"; ?>
