<?php
include "config.php";
include "header.php";

$role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'staff', 'gm', 'sale', 'procurement', 'manager'];
if (!in_array($role, $allowed_roles)) {
    echo "<script>window.location.href='access_denied.php';</script>";
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

$today_filter = "AND DATE(f.start_time) = '$selected_date'";
$mtd_filter = "AND DATE(f.start_time) BETWEEN '$month_start' AND '$month_end'";
$yearly_filter = "AND YEAR(f.start_time) = $selected_year";

$mtd_filter_q = "AND DATE(q.event_date) BETWEEN '$month_start' AND '$month_end'";

/* ===== 1. TODAY REVENUE ===== */
$today_total = $conn->query("SELECT COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $today_filter $company_filter $staff_filter")->fetch_assoc()['t'];
$mtd_total = $conn->query("SELECT COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter")->fetch_assoc()['t'];
$year_total = $conn->query("SELECT COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $yearly_filter $company_filter $staff_filter")->fetch_assoc()['t'];

$today_events = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.status NOT IN ('Cancelled') AND f.approve=1 $today_filter $company_filter $staff_filter")->fetch_assoc()['c'];
$mtd_events = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.status NOT IN ('Cancelled') AND f.approve=1 $mtd_filter $company_filter $staff_filter")->fetch_assoc()['c'];

$today_pax = $conn->query("SELECT COALESCE(SUM(f.pax),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $today_filter $company_filter $staff_filter")->fetch_assoc()['t'];
$mtd_pax = $conn->query("SELECT COALESCE(SUM(f.pax),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter")->fetch_assoc()['t'];

/* ===== 2. REVENUE BY TYPE ===== */
$type_names = []; $type_today = []; $type_mtd = [];
$rev_types = $conn->query("SELECT ft.type_name, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND DATE(f.start_time)='$selected_date' THEN f.total_amount ELSE 0 END),0) as today_rev, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND DATE(f.start_time) BETWEEN '$month_start' AND '$month_end' THEN f.total_amount ELSE 0 END),0) as mtd_rev FROM function_types ft LEFT JOIN functions f ON f.function_type_id=ft.id $company_filter $staff_filter GROUP BY ft.id, ft.type_name ORDER BY mtd_rev DESC");
while ($rt = $rev_types->fetch_assoc()) {
    $type_names[] = $rt['type_name'];
    $type_today[] = (float)$rt['today_rev'];
    $type_mtd[] = (float)$rt['mtd_rev'];
}

/* ===== 3. REVENUE BY COMPANY ===== */
$comp_names = []; $comp_today = []; $comp_mtd = [];
$rev_comps = $conn->query("SELECT c.company_name, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND DATE(f.start_time)='$selected_date' THEN f.total_amount ELSE 0 END),0) as today_rev, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') AND DATE(f.start_time) BETWEEN '$month_start' AND '$month_end' THEN f.total_amount ELSE 0 END),0) as mtd_rev FROM companies c LEFT JOIN functions f ON f.company_id=c.id $staff_filter GROUP BY c.id, c.company_name ORDER BY mtd_rev DESC");
while ($rc = $rev_comps->fetch_assoc()) {
    $comp_names[] = $rc['company_name'];
    $comp_today[] = (float)$rc['today_rev'];
    $comp_mtd[] = (float)$rc['mtd_rev'];
}

/* ===== 4. BANQUET & MEETING PERFORMANCE ===== */
$today_avg_revenue = $today_events > 0 ? round($today_total / $today_events) : 0;
$mtd_avg_revenue = $mtd_events > 0 ? round($mtd_total / $mtd_events) : 0;
$today_rooms_used = $conn->query("SELECT COUNT(DISTINCT f.room_id) as c FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') AND f.room_id IS NOT NULL AND f.room_id != 0 $today_filter $company_filter $staff_filter")->fetch_assoc()['c'];
$mtd_rooms_used = $conn->query("SELECT COUNT(DISTINCT f.room_id) as c FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') AND f.room_id IS NOT NULL AND f.room_id != 0 $mtd_filter $company_filter $staff_filter")->fetch_assoc()['c'];

/* ===== 5. MEETING ROOM UTILIZATION ===== */
$room_stats = $conn->query("SELECT mr.room_name, COALESCE(COUNT(f.id),0) as total_bookings, COALESCE(SUM(CASE WHEN f.status NOT IN ('Cancelled','Completed') THEN 1 ELSE 0 END),0) as active_bookings FROM meeting_rooms mr LEFT JOIN functions f ON f.room_id=mr.id AND f.approve=1 $mtd_filter $company_filter $staff_filter WHERE mr.status='active' GROUP BY mr.id, mr.room_name ORDER BY total_bookings DESC");
$room_labels = []; $room_bookings = [];
while ($rs = $room_stats->fetch_assoc()) {
    $room_labels[] = $rs['room_name'];
    $room_bookings[] = (int)$rs['active_bookings'];
}

/* ===== 6. FUTURE BOOKING PIPELINE ===== */
$pipe_7d = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) $company_filter $staff_filter")->fetch_assoc();
$pipe_30d = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) $company_filter $staff_filter")->fetch_assoc();
$pipe_90d = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 90 DAY) $company_filter $staff_filter")->fetch_assoc();
$pipe_180d = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 180 DAY) $company_filter $staff_filter")->fetch_assoc();

/* ===== 7. QUOTATION & CONVERSION ===== */
$q_total = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE 1=1 $mtd_filter_q $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$q_approved = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Approved' $mtd_filter_q $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$q_cancelled = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Cancelled' $mtd_filter_q $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$q_pending = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Sent' AND q.expiry_date >= CURDATE() $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$conversion_rate = $q_total > 0 ? round(($q_approved / $q_total) * 100, 1) : 0;

$q_today_total = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE DATE(q.created_at)='$selected_date' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];

/* ===== 8. DEPOSIT DASHBOARD ===== */
$deposit_received = $conn->query("SELECT COALESCE(SUM(ff.amount),0) as t FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE ff.type='deposit' AND ff.is_post_approval=1 AND DATE(ff.transaction_date)='$selected_date' $company_filter $staff_filter")->fetch_assoc()['t'];
$deposit_received_mtd = $conn->query("SELECT COALESCE(SUM(ff.amount),0) as t FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE ff.type='deposit' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter")->fetch_assoc()['t'];
$total_deposit_balance = $conn->query("SELECT COALESCE(SUM(f.deposit),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') $company_filter $staff_filter")->fetch_assoc()['t'];
$deposit_pending_events = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND (f.deposit IS NULL OR f.deposit=0) $company_filter $staff_filter")->fetch_assoc()['c'];

/* ===== 9. SALES PIPELINE (Funnel) ===== */
$pipe_prospect = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Draft' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$pipe_quotation = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Sent' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$pipe_confirmed = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Approved' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$pipe_lost = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Cancelled' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];

$pipe_prospect_val = $conn->query("SELECT COALESCE(SUM(q.grand_total),0) as t FROM quotations q WHERE q.status='Draft' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['t'];
$pipe_quotation_val = $conn->query("SELECT COALESCE(SUM(q.grand_total),0) as t FROM quotations q WHERE q.status='Sent' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['t'];
$pipe_confirmed_val = $conn->query("SELECT COALESCE(SUM(q.grand_total),0) as t FROM quotations q WHERE q.status='Approved' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['t'];
$pipe_lost_val = $conn->query("SELECT COALESCE(SUM(q.grand_total),0) as t FROM quotations q WHERE q.status='Cancelled' $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['t'];

/* ===== 10. TOP 10 EVENTS ===== */
$top_events = $conn->query("SELECT f.function_name, ft.type_name, DATE(f.start_time) as event_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter ORDER BY f.total_amount DESC LIMIT 10");

/* ===== 11. SALES BY SEGMENT (Function Type) ===== */
$seg_labels = []; $seg_values = [];
$seg_data = $conn->query("SELECT ft.type_name, COALESCE(SUM(f.total_amount),0) as total FROM functions f JOIN function_types ft ON f.function_type_id=ft.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') $mtd_filter $company_filter $staff_filter GROUP BY ft.id, ft.type_name ORDER BY total DESC");
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
$staff_q = $conn->query("SELECT u.id, u.name, COUNT(f.id) as total_events, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled') THEN f.total_amount ELSE 0 END),0) as total_revenue, COALESCE(SUM(CASE WHEN f.approve=1 AND f.status NOT IN ('Cancelled','Completed') THEN f.deposit ELSE 0 END),0) as total_deposit, COALESCE((SELECT SUM(st.target_amount) FROM sales_targets st WHERE st.user_id=u.id AND st.target_year=$selected_year AND st.target_month=$selected_month),0) as target_amount FROM users u LEFT JOIN functions f ON f.created_by_id=u.id $company_filter $mtd_filter WHERE u.role IN ('Staff','Admin','Sale','Manager','GM') $staff_filter_user GROUP BY u.id, u.name HAVING total_events > 0 OR target_amount > 0 ORDER BY total_revenue DESC");
while ($sq = $staff_q->fetch_assoc()) {
    $staff_data[] = $sq;
}

/* ===== 15. KPI vs TARGET ===== */
$team_target = $conn->query("SELECT COALESCE(SUM(st.target_amount),0) as t FROM sales_targets st WHERE st.target_year=$selected_year AND st.target_month=$selected_month" . ($selected_staff > 0 ? " AND st.user_id=$selected_staff" : ""))->fetch_assoc()['t'];
$team_target_pct = $team_target > 0 ? round(($mtd_total / $team_target) * 100, 1) : 0;

$avg_deal = $mtd_events > 0 ? round($mtd_total / $mtd_events) : 0;

/* ===== 16. CANCELLATION ===== */
$cancelled_count = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.status='Cancelled' $mtd_filter $company_filter $staff_filter")->fetch_assoc()['c'];
$cancel_rate = ($mtd_events + $cancelled_count) > 0 ? round(($cancelled_count / ($mtd_events + $cancelled_count)) * 100, 1) : 0;

/* ===== 17. REPEAT CUSTOMER ===== */
$repeat_data = $conn->query("SELECT COUNT(DISTINCT customer_id) as total_cust, SUM(CASE WHEN booking_count > 1 THEN 1 ELSE 0 END) as repeat_cust FROM (SELECT customer_id, COUNT(*) as booking_count FROM functions WHERE approve=1 AND status NOT IN ('Cancelled') AND customer_id IS NOT NULL $company_filter_plain $staff_filter_plain GROUP BY customer_id) sub")->fetch_assoc();
$total_cust = (int)($repeat_data['total_cust'] ?? 0);
$repeat_cust = (int)($repeat_data['repeat_cust'] ?? 0);
$repeat_pct = $total_cust > 0 ? round(($repeat_cust / $total_cust) * 100, 1) : 0;

/* ===== 18. GOP FORECAST ===== */
$total_income_mtd = $conn->query("SELECT COALESCE(SUM(ff.amount),0) as t FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE ff.type='income' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter")->fetch_assoc()['t'];
$total_cost_mtd = $conn->query("SELECT COALESCE(SUM(ff.amount),0) as t FROM function_finance ff JOIN functions f ON ff.function_id=f.id WHERE ff.type='cost' AND ff.is_post_approval=1 AND ff.transaction_date BETWEEN '$month_start' AND '$month_end' $company_filter $staff_filter")->fetch_assoc()['t'];
$gop_forecast = $total_income_mtd - $total_cost_mtd;
$gop_margin = $total_income_mtd > 0 ? round(($gop_forecast / $total_income_mtd) * 100, 1) : 0;

/* ===== 19. EXECUTIVE ALERTS ===== */
$alert_no_deposit = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed','Confirmed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND (f.deposit IS NULL OR f.deposit=0) $company_filter $staff_filter")->fetch_assoc()['c'];
$alert_overdue = $conn->query("SELECT COUNT(*) as c FROM quotations q WHERE q.status='Sent' AND q.expiry_date < CURDATE() $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : ""))->fetch_assoc()['c'];
$alert_room_conflict = $conn->query("SELECT COUNT(*) as c FROM (SELECT f.room_id, DATE(f.start_time) as dt, COUNT(*) as cnt FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.room_id IS NOT NULL $company_filter $staff_filter GROUP BY f.room_id, DATE(f.start_time) HAVING cnt > 1) sub")->fetch_assoc()['c'];
$alert_pending_approval = $conn->query("SELECT COUNT(*) as c FROM functions f WHERE f.approve=0 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter")->fetch_assoc()['c'];

/* ===== 20. UPCOMING TODAY ===== */
$today_events_list = $conn->query("SELECT f.id, f.function_name, f.start_time, f.end_time, c.company_name, r.room_name, u.name as staff_name, f.total_amount, f.pax, ft.type_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN meeting_rooms r ON f.room_id=r.id LEFT JOIN users u ON f.created_by_id=u.id LEFT JOIN function_types ft ON f.function_type_id=ft.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND DATE(f.start_time)='$selected_date' $company_filter $staff_filter ORDER BY f.start_time ASC");

/* ===== 21. MONTHLY TREND ===== */
$monthly_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $tm = date('m', strtotime("-$i months"));
    $ty = date('Y', strtotime("-$i months"));
    $tr = $conn->query("SELECT COALESCE(SUM(f.total_amount),0) as t FROM functions f WHERE f.approve=1 AND f.status NOT IN ('Cancelled') AND MONTH(f.start_time)=$tm AND YEAR(f.start_time)=$ty $company_filter $staff_filter")->fetch_assoc();
    $monthly_trend[] = ['label' => date('M', strtotime("-$i months")), 'total' => (float)$tr['t']];
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
$expiring_quotes = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.expiry_date, q.grand_total, c.company_name FROM quotations q LEFT JOIN companies c ON q.company_id=c.id WHERE q.status='Sent' AND q.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY q.expiry_date ASC LIMIT 5");

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

// 7) Events by Month (for trend)
$dd_month = [];
for ($i = 5; $i >= 0; $i--) {
    $tm = date('m', strtotime("-$i months"));
    $ty = date('Y', strtotime("-$i months"));
    $ml = date('M', strtotime("-$i months"));
    $mr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled') AND MONTH(f.start_time)=$tm AND YEAR(f.start_time)=$ty $company_filter $staff_filter ORDER BY f.start_time DESC");
    while ($r = $mr->fetch_assoc()) { $r['month_label'] = $ml; $dd_month[] = $r; }
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

// 10) Future booking details
$dd_future = [];
foreach (['7'=>7, '30'=>30, '90'=>90, '180'=>180] as $key => $days) {
    $fr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, ft.type_name, c.company_name, u.name as staff_name, f.pax FROM functions f LEFT JOIN function_types ft ON f.function_type_id=ft.id LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL $days DAY) $company_filter $staff_filter ORDER BY f.start_time ASC");
    $dd_future[$key] = [];
    while ($r = $fr->fetch_assoc()) $dd_future[$key][] = $r;
}
$dd['future'] = $dd_future;

// 11) Alerts detail
$dd['alert_no_deposit'] = [];
$adr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed','Confirmed') AND f.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND (f.deposit IS NULL OR f.deposit=0) $company_filter $staff_filter ORDER BY f.start_time ASC");
while ($r = $adr->fetch_assoc()) $dd['alert_no_deposit'][] = $r;

$dd['alert_overdue'] = [];
$odr = $conn->query("SELECT q.id, q.quote_no, q.event_name, q.expiry_date, q.grand_total, c.company_name, u.name as staff_name FROM quotations q LEFT JOIN companies c ON q.company_id=c.id LEFT JOIN users u ON q.created_by=u.id WHERE q.status='Sent' AND q.expiry_date < CURDATE() $company_filter_plain" . ($selected_staff > 0 ? " AND q.created_by=$selected_staff" : "") . " ORDER BY q.expiry_date ASC");
while ($r = $odr->fetch_assoc()) $dd['alert_overdue'][] = $r;

$dd['alert_pending'] = [];
$apr = $conn->query("SELECT f.id, f.function_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name, u.name as staff_name FROM functions f LEFT JOIN companies c ON f.company_id=c.id LEFT JOIN users u ON f.created_by_id=u.id WHERE f.approve=0 AND f.status NOT IN ('Cancelled') $company_filter $staff_filter ORDER BY f.created_at DESC");
while ($r = $apr->fetch_assoc()) $dd['alert_pending'][] = $r;

$dd['alert_room_conflict'] = [];
$rcr = $conn->query("SELECT f.id, f.function_name, mr.room_name, DATE(f.start_time) as ev_date, f.total_amount, c.company_name FROM functions f LEFT JOIN meeting_rooms mr ON f.room_id=mr.id LEFT JOIN companies c ON f.company_id=c.id WHERE f.approve=1 AND f.status NOT IN ('Cancelled','Completed') AND f.room_id IS NOT NULL AND (f.room_id, DATE(f.start_time)) IN (SELECT f2.room_id, DATE(f2.start_time) FROM functions f2 WHERE f2.approve=1 AND f2.status NOT IN ('Cancelled','Completed') AND f2.room_id IS NOT NULL $company_filter $staff_filter GROUP BY f2.room_id, DATE(f2.start_time) HAVING COUNT(*)>1) $company_filter $staff_filter ORDER BY f.start_time ASC");
while ($r = $rcr->fetch_assoc()) $dd['alert_room_conflict'][] = $r;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
.exec-card { border-left: 4px solid #b89441; transition: transform 0.2s; }
.exec-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.exec-card .stat-value { font-size: 1.3rem; font-weight: 700; color: #1a1a1a; }
.exec-card .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }
.exec-card .stat-sub { font-size: 0.72rem; color: #6c757d; }
.section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #b89441; border-bottom: 2px solid #b89441; padding-bottom: 6px; margin-bottom: 16px; }
.alert-badge { font-size: 0.75rem; }
.pipeline-stage { text-align: center; padding: 12px 8px; border-radius: 8px; }
.pipeline-stage .stage-num { font-size: 1.8rem; font-weight: 800; }
.pipeline-stage .stage-label { font-size: 0.7rem; text-transform: uppercase; }
.kpi-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
.kpi-row:last-child { border-bottom: none; }
.kpi-row .kpi-label { font-size: 0.8rem; color: #495057; }
.kpi-row .kpi-val { font-weight: 700; font-size: 0.85rem; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
.dot-green { background: #198754; }
.dot-yellow { background: #ffc107; }
.dot-red { background: #dc3545; }
@media print {
    .no-print { display: none !important; }
    .exec-card { break-inside: avoid; }
}
</style>

<div class="container-fluid px-0">
    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
        <div>
            <h5 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-gold me-2"></i>Daily Hotel Sales Executive Dashboard</h5>
            <small class="text-muted">ประจำวันที่ <?= date('d M Y', strtotime($selected_date)) ?> <?= ($selected_year + 543) ?></small>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap" id="execFilter">
            <label class="small text-muted fw-bold">วันที่</label>
            <input type="date" name="date" value="<?= $selected_date ?>" class="form-control form-control-sm" style="width:150px">
            <label class="small text-muted fw-bold">เดือน</label>
            <select name="month" class="form-select form-select-sm" style="width:110px">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:90px">
                <?php for ($y = date('Y')-2; $y <= date('Y')+1; $y++): ?>
                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y+543 ?></option>
                <?php endfor; ?>
            </select>
            <label class="small text-muted fw-bold">โรงแรม</label>
            <select name="company_id" class="form-select form-select-sm" style="width:160px">
                <option value="0">ทั้งหมด</option>
                <?php $companies->data_seek(0); while ($c = $companies->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $selected_company == $c['id'] ? 'selected' : '' ?>><?= $c['company_name'] ?></option>
                <?php endwhile; ?>
            </select>
            <label class="small text-muted fw-bold">เซลล์</label>
            <select name="staff_id" class="form-select form-select-sm" style="width:130px" <?= $role === 'staff' ? 'disabled' : '' ?>>
                <option value="0">ทั้งหมด</option>
                <?php $staff_list->data_seek(0); while ($s = $staff_list->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>" <?= $selected_staff == $s['id'] ? 'selected' : '' ?>><?= $s['name'] ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-filter"></i></button>
        </form>
    </div>

    <!-- ===== SECTION 1: EXECUTIVE SUMMARY ===== -->
    <div class="section-title"><i class="bi bi-bar-chart-line me-1"></i> 1. Executive Summary</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100" id="cardTodayTotal" style="cursor:pointer">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">รายได้รวมวันนี้</div>
                    <div class="stat-value text-success">฿<?= number_format($today_total, 0) ?></div>
                    <div class="stat-sub">MTD: ฿<?= number_format($mtd_total, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">งานวันนี้</div>
                    <div class="stat-value text-primary"><?= $today_events ?></div>
                    <div class="stat-sub">MTD: <?= $mtd_events ?> งาน | สะสมปี: <?= number_format($year_total, 0) ?> บาท</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">จำนวนคนวันนี้</div>
                    <div class="stat-value text-info"><?= number_format($today_pax) ?></div>
                    <div class="stat-sub">MTD: <?= number_format($mtd_pax) ?> คน</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">เงินมัดจำรวม</div>
                    <div class="stat-value text-warning">฿<?= number_format($total_deposit_balance, 0) ?></div>
                    <div class="stat-sub">รับวันนี้: ฿<?= number_format($deposit_received, 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 2: REVENUE BY TYPE ===== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-graph-up text-gold me-1"></i>2. รายได้ตามประเภทงาน (Today vs MTD)</h6>
                </div>
                <div class="card-body">
                    <?php if (count($type_names) > 0): ?>
                    <canvas id="typeRevChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-4 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-pie-chart text-gold me-1"></i>สัดส่วนรายได้ MTD</h6>
                </div>
                <div class="card-body">
                    <?php if (count($seg_labels) > 0): ?>
                    <canvas id="segPieChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-4 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 3: BANQUET & MEETING PERFORMANCE ===== -->
    <div class="section-title"><i class="bi bi-calendar-event me-1"></i> 3. Banquet & Meeting Performance</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">จำนวนงาน MTD</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= $mtd_events ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">รายได้ MTD</div>
                    <div class="stat-value" style="font-size:1.1rem">฿<?= number_format($mtd_total, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">ผู้เข้าร่วม MTD</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= number_format($mtd_pax) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">เฉลี่ยต่องาน</div>
                    <div class="stat-value" style="font-size:1.1rem">฿<?= number_format($mtd_avg_revenue, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">ห้องใช้งาน MTD</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= $mtd_rooms_used ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">งานยกเลิก MTD</div>
                    <div class="stat-value text-danger" style="font-size:1.1rem"><?= $cancelled_count ?></div>
                    <div class="stat-sub"><?= $cancel_rate ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 4: FUTURE BOOKING PIPELINE ===== -->
    <div class="section-title"><i class="bi bi-calendar-check me-1"></i> 4. Future Booking Pipeline</div>
    <div class="row g-2 mb-4" id="pipelineCards">
        <?php
        $pipes = [
            ['label' => '7 วัน', 'cnt' => $pipe_7d['c'], 'val' => $pipe_7d['t'], 'color' => 'success', 'days' => '7'],
            ['label' => '30 วัน', 'cnt' => $pipe_30d['c'], 'val' => $pipe_30d['t'], 'color' => 'primary', 'days' => '30'],
            ['label' => '90 วัน', 'cnt' => $pipe_90d['c'], 'val' => $pipe_90d['t'], 'color' => 'info', 'days' => '90'],
            ['label' => '180 วัน', 'cnt' => $pipe_180d['c'], 'val' => $pipe_180d['t'], 'color' => 'secondary', 'days' => '180'],
        ];
        foreach ($pipes as $p): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 pipe-card" data-days="<?= $p['days'] ?>" data-label="<?= $p['label'] ?>" style="cursor:pointer">
                <div class="card-body py-3 text-center">
                    <div class="small fw-bold text-muted">ภายใน <?= $p['label'] ?></div>
                    <div class="fw-bold text-<?= $p['color'] ?>" style="font-size:1.5rem"><?= $p['cnt'] ?> งาน</div>
                    <div class="small">฿<?= number_format($p['val'], 0) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== SECTION 5: QUOTATION & CONVERSION ===== -->
    <div class="section-title"><i class="bi bi-file-text me-1"></i> 5. Quotation & Conversion</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">ใบเสนอราคา MTD</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= $q_total ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">วันนี้</div>
                    <div class="stat-value" style="font-size:1.1rem"><?= $q_today_total ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">ยืนยัน MTD</div>
                    <div class="stat-value text-success" style="font-size:1.1rem"><?= $q_approved ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">Conversion Rate</div>
                    <div class="stat-value text-primary" style="font-size:1.1rem"><?= $conversion_rate ?>%</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">รอตอบ</div>
                    <div class="stat-value text-warning" style="font-size:1.1rem"><?= $q_pending ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-2 text-center">
                    <div class="stat-label">Lost MTD</div>
                    <div class="stat-value text-danger" style="font-size:1.1rem"><?= $q_cancelled ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 6: DEPOSIT DASHBOARD ===== -->
    <div class="section-title"><i class="bi bi-wallet2 me-1"></i> 6. Deposit Dashboard</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">รับเงินมัดจำวันนี้</div>
                    <div class="stat-value text-success">฿<?= number_format($deposit_received, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100 deposit-clickable" style="cursor:pointer">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">รับเงินมัดจำ MTD</div>
                    <div class="stat-value text-success">฿<?= number_format($deposit_received_mtd, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">คงเหลือเงินมัดจำ</div>
                    <div class="stat-value text-warning">฿<?= number_format($total_deposit_balance, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card exec-card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="stat-label">งานค้างชำระมัดจำ</div>
                    <div class="stat-value text-danger"><?= $deposit_pending_events ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 7: SALES PIPELINE (Funnel) ===== -->
    <div class="section-title"><i class="bi bi-funnel me-1"></i> 7. Sales Pipeline</div>
    <div class="row g-2 mb-4" id="funnelCards">
        <?php
        $funnel = [
            ['label' => 'Draft/ Prospect', 'cnt' => $pipe_prospect, 'val' => $pipe_prospect_val, 'color' => '#6c757d', 'bg' => 'bg-secondary-subtle', 'status' => 'Draft'],
            ['label' => 'Quotation Sent', 'cnt' => $pipe_quotation, 'val' => $pipe_quotation_val, 'color' => '#0d6efd', 'bg' => 'bg-primary-subtle', 'status' => 'Sent'],
            ['label' => 'Confirmed', 'cnt' => $pipe_confirmed, 'val' => $pipe_confirmed_val, 'color' => '#198754', 'bg' => 'bg-success-subtle', 'status' => 'Approved'],
            ['label' => 'Lost', 'cnt' => $pipe_lost, 'val' => $pipe_lost_val, 'color' => '#dc3545', 'bg' => 'bg-danger-subtle', 'status' => 'Cancelled'],
        ];
        foreach ($funnel as $f): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 <?= $f['bg'] ?> funnel-card" data-status="<?= $f['status'] ?>" data-label="<?= $f['label'] ?>" style="cursor:pointer">
                <div class="card-body py-3 text-center">
                    <div class="small fw-bold" style="color:<?= $f['color'] ?>"><?= $f['label'] ?></div>
                    <div class="fw-bold" style="font-size:1.6rem;color:<?= $f['color'] ?>"><?= $f['cnt'] ?></div>
                    <div class="small text-muted">฿<?= number_format($f['val'], 0) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== SECTION 8 & 9: TOP 10 + SALES BY SEGMENT ===== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-trophy text-gold me-1"></i>8. Top 10 งานตามมูลค่า</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle small">
                            <thead class="table-light">
                                <tr><th>ลูกค้า</th><th>ประเภท</th><th>วันที่</th><th class="text-end">มูลค่า</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($te = $top_events->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-truncate" style="max-width:200px"><?= $te['function_name'] ?></td>
                                    <td><span class="badge bg-dark-subtle text-dark"><?= $te['type_name'] ?: '-' ?></span></td>
                                    <td><?= $te['event_date'] ? date('d M', strtotime($te['event_date'])) : '-' ?></td>
                                    <td class="text-end fw-bold">฿<?= number_format($te['total_amount'], 0) ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-people text-gold me-1"></i>9. Sales Pipeline Funnel</h6>
                </div>
                <div class="card-body">
                    <canvas id="funnelChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 10 & 11: SALES BY SOURCE + PERIOD ===== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-diagram-3 text-gold me-1"></i>10. แหล่งลูกค้า MTD</h6>
                </div>
                <div class="card-body">
                    <?php if (count($src_labels) > 0): ?>
                    <canvas id="sourceChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-clock text-gold me-1"></i>11. เช้า/บ่าย/เย็น MTD</h6>
                </div>
                <div class="card-body">
                    <?php if (count($period_labels_arr) > 0): ?>
                    <canvas id="periodChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-credit-card text-gold me-1"></i>12. ช่องทางชำระเงิน MTD</h6>
                </div>
                <div class="card-body">
                    <?php if (count($pay_labels) > 0): ?>
                    <canvas id="payChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 13: SALES BY PERSON ===== -->
    <div class="section-title"><i class="bi bi-person-lines-fill me-1"></i> 13. Sales by Salesperson (MTD)</div>
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>พนักงาน</th>
                            <th class="text-center">งาน</th>
                            <th class="text-end">รายได้</th>
                            <th class="text-end">มัดจำ</th>
                            <th class="text-end">เป้าหมาย</th>
                            <th class="text-end">% เป้าหมาย</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($staff_data) > 0): ?>
                        <?php foreach ($staff_data as $sd): ?>
                        <?php $pct = $sd['target_amount'] > 0 ? round(($sd['total_revenue'] / $sd['target_amount']) * 100, 1) : 0; ?>
                        <tr>
                            <td class="fw-medium"><?= $sd['name'] ?></td>
                            <td class="text-center"><?= $sd['total_events'] ?></td>
                            <td class="text-end fw-bold text-primary">฿<?= number_format($sd['total_revenue'], 0) ?></td>
                            <td class="text-end">฿<?= number_format($sd['total_deposit'], 0) ?></td>
                            <td class="text-end">฿<?= number_format($sd['target_amount'], 0) ?></td>
                            <td class="text-end" style="min-width:120px">
                                <div class="d-flex align-items-center gap-2 justify-content-end">
                                    <span class="fw-bold <?= $pct >= 100 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $pct ?>%</span>
                                    <div class="progress" style="width:60px;height:6px">
                                        <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= min($pct, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 14: KPI vs TARGET ===== -->
    <div class="section-title"><i class="bi bi-bullseye me-1"></i> 14. KPI Dashboard</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100" id="kpiTargetCard" style="cursor:pointer">
                <div class="card-body py-3 px-3">
                    <div class="kpi-row"><span class="kpi-label">รายได้รวม MTD</span><span class="kpi-val">฿<?= number_format($mtd_total, 0) ?></span></div>
                    <div class="kpi-row"><span class="kpi-label">เป้าหมายรวม</span><span class="kpi-val">฿<?= number_format($team_target, 0) ?></span></div>
                    <div class="kpi-row"><span class="kpi-label">% สำเร็จ</span><span class="kpi-val <?= $team_target_pct >= 100 ? 'text-success' : ($team_target_pct >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $team_target_pct ?>%</span></div>
                    <div class="progress mt-1" style="height:10px"><div class="progress-bar <?= $team_target_pct >= 100 ? 'bg-success' : ($team_target_pct >= 50 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= min($team_target_pct, 100) ?>%"></div></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-3 px-3">
                    <div class="kpi-row"><span class="kpi-label">Conversion Rate</span><span class="kpi-val"><?= $conversion_rate ?>%</span></div>
                    <div class="kpi-row"><span class="kpi-label">เฉลี่ยต่องาน</span><span class="kpi-val">฿<?= number_format($avg_deal, 0) ?></span></div>
                    <div class="kpi-row"><span class="kpi-label">อัตรายกเลิก</span><span class="kpi-val <?= $cancel_rate > 10 ? 'text-danger' : 'text-success' ?>"><?= $cancel_rate ?>%</span></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-3 px-3">
                    <div class="kpi-row"><span class="kpi-label">ลูกค้าซ้ำ</span><span class="kpi-val"><?= $repeat_pct ?>% (<?= $repeat_cust ?>/<?= $total_cust ?>)</span></div>
                    <div class="kpi-row"><span class="kpi-label">GOP Forecast MTD</span><span class="kpi-val <?= $gop_forecast >= 0 ? 'text-success' : 'text-danger' ?>">฿<?= number_format($gop_forecast, 0) ?></span></div>
                    <div class="kpi-row"><span class="kpi-label">GOP Margin</span><span class="kpi-val"><?= $gop_margin ?>%</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 15: GOP + MONTHLY TREND ===== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-bar-chart-line text-gold me-1"></i>15. แนวโน้มรายได้ 6 เดือน</h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem"><i class="bi bi-building text-gold me-1"></i>16. รายได้ตามโรงแรม MTD</h6>
                </div>
                <div class="card-body">
                    <?php if (count($comp_names) > 0): ?>
                    <canvas id="companyChart" height="180"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 14: MEETING ROOM UTILIZATION ===== -->
    <div class="section-title"><i class="bi bi-door-open me-1"></i> 17. Meeting Room Utilization (MTD)</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-8">
            <div class="card border shadow-sm h-100">
                <div class="card-body">
                    <?php if (count($room_labels) > 0): ?>
                    <canvas id="roomChart" height="140"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-2 border-bottom">
                    <h6 class="fw-bold mb-0" style="font-size:0.85rem">Quotation ใกล้หมดอายุ</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($expiring_quotes->num_rows > 0): ?>
                    <div class="list-group list-group-flush small" style="font-size:0.75rem">
                        <?php while ($eq = $expiring_quotes->fetch_assoc()): ?>
                        <a href="quotation_view.php?id=<?= $eq['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-1">
                            <span class="text-truncate me-1"><?= $eq['event_name'] ?: $eq['quote_no'] ?></span>
                            <small class="text-danger flex-shrink-0 fw-bold">฿<?= number_format($eq['grand_total'], 0) ?></small>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3 small">ไม่มีใบเสนอราคาใกล้หมดอายุ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 15: EXECUTIVE ALERT ===== -->
    <div class="section-title"><i class="bi bi-exclamation-triangle me-1"></i> 18. Executive Alert</div>
    <div class="row g-2 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 border-<?= $alert_no_deposit > 0 ? 'warning' : 'success' ?> alert-clickable" data-alert-type="alert_no_deposit" data-alert-label="งานยังไม่ชำระมัดจำ (7 วัน)" style="cursor:pointer">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <span class="status-dot <?= $alert_no_deposit > 0 ? 'dot-yellow' : 'dot-green' ?>"></span>
                    <div>
                        <div class="small fw-bold">งานยังไม่ชำระมัดจำ (7 วัน)</div>
                        <div class="fw-bold" style="font-size:1.1rem"><?= $alert_no_deposit ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 border-<?= $alert_room_conflict > 0 ? 'danger' : 'success' ?> alert-clickable" data-alert-type="alert_room_conflict" data-alert-label="ห้องประชุมถูกจองซ้ำ" style="cursor:pointer">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <span class="status-dot <?= $alert_room_conflict > 0 ? 'dot-red' : 'dot-green' ?>"></span>
                    <div>
                        <div class="small fw-bold">ห้องประชุมถูกจองซ้ำ</div>
                        <div class="fw-bold" style="font-size:1.1rem"><?= $alert_room_conflict ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 border-<?= $alert_pending_approval > 0 ? 'warning' : 'success' ?> alert-clickable" data-alert-type="alert_pending" data-alert-label="งานรออนุมัติ" style="cursor:pointer">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <span class="status-dot <?= $alert_pending_approval > 0 ? 'dot-yellow' : 'dot-green' ?>"></span>
                    <div>
                        <div class="small fw-bold">งานรออนุมัติ</div>
                        <div class="fw-bold" style="font-size:1.1rem"><?= $alert_pending_approval ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border shadow-sm h-100 border-<?= $alert_overdue > 0 ? 'danger' : 'success' ?> alert-clickable" data-alert-type="alert_overdue" data-alert-label="ใบเสนอราคาเกินกำหนด" style="cursor:pointer">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                    <span class="status-dot <?= $alert_overdue > 0 ? 'dot-red' : 'dot-green' ?>"></span>
                    <div>
                        <div class="small fw-bold">ใบเสนอราคาเกินกำหนด</div>
                        <div class="fw-bold" style="font-size:1.1rem"><?= $alert_overdue ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 16: TODAY EVENTS LIST ===== -->
    <div class="section-title"><i class="bi bi-calendar-week me-1"></i> 19. งานวันนี้</div>
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th>ชื่องาน</th><th>ประเภท</th><th>เซลล์</th><th>โรงแรม</th><th>ห้อง</th><th>เวลา</th><th>คน</th><th class="text-end">มูลค่า</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($today_events_list->num_rows > 0): ?>
                        <?php while ($ev = $today_events_list->fetch_assoc()): ?>
                        <tr>
                            <td><a href="view.php?id=<?= $ev['id'] ?>" class="text-decoration-none fw-medium"><?= $ev['function_name'] ?></a></td>
                            <td><span class="badge bg-dark-subtle text-dark"><?= $ev['type_name'] ?: '-' ?></span></td>
                            <td><?= $ev['staff_name'] ?: '-' ?></td>
                            <td><?= $ev['company_name'] ?: '-' ?></td>
                            <td><?= $ev['room_name'] ?: '-' ?></td>
                            <td><?= $ev['start_time'] ? date('H:i', strtotime($ev['start_time'])).'-'.date('H:i', strtotime($ev['end_time'])) : '-' ?></td>
                            <td class="text-center"><?= $ev['pax'] ?></td>
                            <td class="text-end fw-bold">฿<?= number_format($ev['total_amount'], 0) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">ไม่มีงานวันนี้</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 17: UPCOMING EVENTS ===== -->
    <div class="section-title"><i class="bi bi-calendar2-week me-1"></i> 20. งานที่กำลังจะมาถึง</div>
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th>ชื่องาน</th><th>ประเภท</th><th>วันที่</th><th>เวลา</th><th>โรงแรม</th><th>ห้อง</th><th class="text-end">มูลค่า</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($upcoming_all->num_rows > 0): ?>
                        <?php while ($ua = $upcoming_all->fetch_assoc()): ?>
                        <tr>
                            <td><a href="view.php?id=<?= $ua['id'] ?>" class="text-decoration-none fw-medium"><?= $ua['function_name'] ?></a></td>
                            <td><span class="badge bg-dark-subtle text-dark"><?= $ua['type_name'] ?: '-' ?></span></td>
                            <td><?= $ua['start_time'] ? date('d M Y', strtotime($ua['start_time'])) : '-' ?></td>
                            <td><?= $ua['start_time'] ? date('H:i', strtotime($ua['start_time'])).'-'.date('H:i', strtotime($ua['end_time'])) : '-' ?></td>
                            <td><?= $ua['company_name'] ?: '-' ?></td>
                            <td><?= $ua['room_name'] ?: '-' ?></td>
                            <td class="text-end fw-bold">฿<?= number_format($ua['total_amount'], 0) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">ไม่มีงานที่กำลังจะมาถึง</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DRILL-DOWN MODAL -->
<div class="modal fade" id="drillModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#1a1a1a;color:#fff">
                <h6 class="modal-title fw-bold"><i class="bi bi-search text-gold me-2"></i><span id="drillTitle">รายละเอียด</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" id="drillBody"></div>
        </div>
    </div>
</div>

<script>
const chartColors = ['#b89441','#0d6efd','#198754','#dc3545','#6f42c1','#fd7e14','#20c997','#d63384','#0dcaf0','#6c757d'];
const drillData = <?= json_encode($dd, JSON_UNESCAPED_UNICODE) ?>;

function showDrill(title, rows, columns) {
    document.getElementById('drillTitle').textContent = title;
    let html = '';
    if (!rows || rows.length === 0) {
        html = '<div class="text-center text-muted py-5">ไม่มีข้อมูลรายละเอียด</div>';
    } else {
        html = '<div class="table-responsive"><table class="table table-hover table-sm align-middle mb-0" style="font-size:0.8rem"><thead class="table-light"><tr>';
        columns.forEach(c => { html += '<th>' + c.label + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(r => {
            html += '<tr>';
            columns.forEach(c => {
                let val = r[c.key] || '-';
                if (c.type === 'money') val = '฿' + Number(val).toLocaleString();
                if (c.type === 'link') val = '<a href="view.php?id=' + r.id + '" class="text-decoration-none fw-medium">' + val + '</a>';
                if (c.type === 'q_link') val = '<a href="quotation_view.php?id=' + r.id + '" class="text-decoration-none fw-medium">' + val + '</a>';
                html += '<td>' + val + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += '<div class="text-end small text-muted mt-1">ทั้งหมด ' + rows.length + ' รายการ | รวม ฿' + rows.reduce((s,r) => s + Number(r.total_amount||r.grand_total||r.amount||0), 0).toLocaleString() + '</div>';
    }
    document.getElementById('drillBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('drillModal')).show();
}

function filterDrill(key, field, value) {
    return drillData[key].filter(r => r[field] === value);
}

const eventCols = [
    {key:'function_name', label:'ชื่องาน', type:'link'},
    {key:'type_name', label:'ประเภท'},
    {key:'company_name', label:'โรงแรม'},
    {key:'staff_name', label:'เซลล์'},
    {key:'ev_date', label:'วันที่'},
    {key:'ev_time', label:'เวลา'},
    {key:'pax', label:'คน'},
    {key:'total_amount', label:'มูลค่า', type:'money'}
];
const eventColsShort = [
    {key:'function_name', label:'ชื่องาน', type:'link'},
    {key:'type_name', label:'ประเภท'},
    {key:'company_name', label:'โรงแรม'},
    {key:'ev_date', label:'วันที่'},
    {key:'total_amount', label:'มูลค่า', type:'money'}
];
const quoteCols = [
    {key:'quote_no', label:'เลขที่', type:'q_link'},
    {key:'event_name', label:'ชื่องาน'},
    {key:'company_name', label:'โรงแรม'},
    {key:'status', label:'สถานะ'},
    {key:'ev_date', label:'วันจัดงาน'},
    {key:'staff_name', label:'เซลล์'},
    {key:'grand_total', label:'มูลค่า', type:'money'}
];
const depositCols = [
    {key:'function_name', label:'ชื่องาน'},
    {key:'company_name', label:'โรงแรม'},
    {key:'detail', label:'รายละเอียด'},
    {key:'amount', label:'จำนวนเงิน', type:'money'},
    {key:'payment_method', label:'ช่องทาง'},
    {key:'tx_date', label:'วันที่'}
];

<?php if (count($type_names) > 0): ?>
var typeRevChart = new Chart(document.getElementById('typeRevChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($type_names as $tn): ?>'<?= addslashes($tn) ?>',<?php endforeach; ?>],
        datasets: [
            { label: 'วันนี้', data: [<?php foreach ($type_today as $tt): ?><?= $tt ?>,<?php endforeach; ?>], backgroundColor: '#b89441', borderRadius: 4 },
            { label: 'MTD', data: [<?php foreach ($type_mtd as $tm): ?><?= $tm ?>,<?php endforeach; ?>], backgroundColor: '#0d6efd', borderRadius: 4 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } }, cursor: { pointer: true } }, scales: { y: { beginAtZero: true, ticks: { callback: v => '฿'+v.toLocaleString() } } } }
});
document.getElementById('typeRevChart').onclick = function(e) {
    var pts = typeRevChart.getElementsAtEventForMode(e, 'index', {intersect:true}, true);
    if (pts.length > 0) {
        var label = typeRevChart.data.labels[pts[0].index];
        var rows = filterDrill('type', 'type_name', label);
        showDrill('รายได้ตามประเภท: ' + label + ' (MTD)', rows, eventColsShort);
    }
};
<?php endif; ?>

<?php if (count($seg_labels) > 0): ?>
var segPieChart = new Chart(document.getElementById('segPieChart'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($seg_labels as $sl): ?>'<?= addslashes($sl) ?>',<?php endforeach; ?>],
        datasets: [{ data: [<?php foreach ($seg_values as $sv): ?><?= $sv ?>,<?php endforeach; ?>], backgroundColor: chartColors, borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
});
document.getElementById('segPieChart').onclick = function(e) {
    var pts = segPieChart.getElementsAtEventForMode(e, 'nearest', {intersect:true}, true);
    if (pts.length > 0) {
        var label = segPieChart.data.labels[pts[0].index];
        var rows = filterDrill('type', 'type_name', label);
        showDrill('สัดส่วนรายได้: ' + label, rows, eventColsShort);
    }
};
<?php endif; ?>

var funnelChart = new Chart(document.getElementById('funnelChart'), {
    type: 'bar',
    data: {
        labels: ['Draft/Prospect','Quotation Sent','Confirmed','Lost'],
        datasets: [{ data: [<?= $pipe_prospect ?>,<?= $pipe_quotation ?>,<?= $pipe_confirmed ?>,<?= $pipe_lost ?>], backgroundColor: ['#6c757d','#0d6efd','#198754','#dc3545'], borderRadius: 4 }]
    },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
var pipeStatusMap = {0:'Draft', 1:'Sent', 2:'Approved', 3:'Cancelled'};
document.getElementById('funnelChart').onclick = function(e) {
    var pts = funnelChart.getElementsAtEventForMode(e, 'index', {intersect:true}, true);
    if (pts.length > 0) {
        var idx = pts[0].index;
        var status = pipeStatusMap[idx];
        var label = funnelChart.data.labels[idx];
        var rows = filterDrill('pipeline', 'status', status);
        showDrill('Sales Pipeline: ' + label, rows, quoteCols);
    }
};

<?php if (count($src_labels) > 0): ?>
var sourceChart = new Chart(document.getElementById('sourceChart'), {
    type: 'pie',
    data: {
        labels: [<?php foreach ($src_labels as $sl): ?>'<?= addslashes($sl) ?>',<?php endforeach; ?>],
        datasets: [{ data: [<?php foreach ($src_values as $sv): ?><?= $sv ?>,<?php endforeach; ?>], backgroundColor: chartColors, borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
});
document.getElementById('sourceChart').onclick = function(e) {
    var pts = sourceChart.getElementsAtEventForMode(e, 'nearest', {intersect:true}, true);
    if (pts.length > 0) {
        var label = sourceChart.data.labels[pts[0].index];
        var rows = filterDrill('source', 'lead_source', label);
        showDrill('แหล่งลูกค้า: ' + label, rows, eventColsShort);
    }
};
<?php endif; ?>

<?php if (count($period_labels_arr) > 0): ?>
var periodChart = new Chart(document.getElementById('periodChart'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($period_labels_arr as $pl): ?>'<?= $pl ?>',<?php endforeach; ?>],
        datasets: [{ data: [<?php foreach ($period_rev_arr as $pr): ?><?= $pr ?>,<?php endforeach; ?>], backgroundColor: ['#ffc107','#0d6efd','#6f42c1'], borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
});
document.getElementById('periodChart').onclick = function(e) {
    var pts = periodChart.getElementsAtEventForMode(e, 'nearest', {intersect:true}, true);
    if (pts.length > 0) {
        var label = periodChart.data.labels[pts[0].index];
        var rows = filterDrill('period', 'period', label);
        showDrill('ช่วงเวลา: ' + label + ' (MTD)', rows, eventCols);
    }
};
<?php endif; ?>

<?php if (count($pay_labels) > 0): ?>
var payChart = new Chart(document.getElementById('payChart'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($pay_labels as $pl): ?>'<?= addslashes($pl) ?>',<?php endforeach; ?>],
        datasets: [{ data: [<?php foreach ($pay_values as $pv): ?><?= $pv ?>,<?php endforeach; ?>], backgroundColor: chartColors, borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
});
document.getElementById('payChart').onclick = function(e) {
    var pts = payChart.getElementsAtEventForMode(e, 'nearest', {intersect:true}, true);
    if (pts.length > 0) {
        var label = payChart.data.labels[pts[0].index];
        var rows = filterDrill('payment', 'payment_method', label);
        showDrill('ช่องทางชำระเงิน: ' + label, rows, depositCols);
    }
};
<?php endif; ?>

var trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($monthly_trend as $mt): ?>'<?= $mt['label'] ?>',<?php endforeach; ?>],
        datasets: [{ label: 'รายได้ (บาท)', data: [<?php foreach ($monthly_trend as $mt): ?><?= $mt['total'] ?>,<?php endforeach; ?>], backgroundColor: '#b89441', borderColor: '#b89441', borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => '฿'+v.toLocaleString() } } } }
});
var monthLabels = [<?php foreach ($monthly_trend as $mt): ?>'<?= $mt['label'] ?>',<?php endforeach; ?>];
document.getElementById('trendChart').onclick = function(e) {
    var pts = trendChart.getElementsAtEventForMode(e, 'index', {intersect:true}, true);
    if (pts.length > 0) {
        var label = trendChart.data.labels[pts[0].index];
        var rows = drillData['month'].filter(r => r.month_label === label);
        showDrill('แนวโน้มรายได้เดือน ' + label, rows, eventColsShort);
    }
};

<?php if (count($comp_names) > 0): ?>
var companyChart = new Chart(document.getElementById('companyChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($comp_names as $cn): ?>'<?= addslashes($cn) ?>',<?php endforeach; ?>],
        datasets: [{ label: 'รายได้ MTD', data: [<?php foreach ($comp_mtd as $cm): ?><?= $cm ?>,<?php endforeach; ?>], backgroundColor: '#0d6efd', borderRadius: 4 }]
    },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => '฿'+v.toLocaleString() } } } }
});
document.getElementById('companyChart').onclick = function(e) {
    var pts = companyChart.getElementsAtEventForMode(e, 'index', {intersect:true}, true);
    if (pts.length > 0) {
        var label = companyChart.data.labels[pts[0].index];
        var rows = filterDrill('company', 'company_name', label);
        showDrill('รายได้ตามโรงแรม: ' + label, rows, eventColsShort);
    }
};
<?php endif; ?>

<?php if (count($room_labels) > 0): ?>
var roomChart = new Chart(document.getElementById('roomChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($room_labels as $rl): ?>'<?= addslashes($rl) ?>',<?php endforeach; ?>],
        datasets: [{ label: 'จำนวนงาน MTD', data: [<?php foreach ($room_bookings as $rb): ?><?= $rb ?>,<?php endforeach; ?>], backgroundColor: '#20c997', borderRadius: 4 }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
document.getElementById('roomChart').onclick = function(e) {
    var pts = roomChart.getElementsAtEventForMode(e, 'index', {intersect:true}, true);
    if (pts.length > 0) {
        var label = roomChart.data.labels[pts[0].index];
        var rows = filterDrill('room', 'room_name', label);
        showDrill('ห้องประชุม: ' + label, rows, eventCols);
    }
};
<?php endif; ?>

// ===== CARD CLICK HANDLERS =====
// Pipeline cards
document.querySelectorAll('#pipelineCards .pipe-card').forEach(function(card) {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function() {
        var days = this.dataset.days;
        var label = this.dataset.label;
        var rows = drillData['future'][days] || [];
        showDrill('งานที่จะมาถึงภายใน ' + label, rows, eventCols);
    });
});

// Funnel pipeline cards
document.querySelectorAll('#funnelCards .funnel-card').forEach(function(card) {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function() {
        var status = this.dataset.status;
        var label = this.dataset.label;
        var rows = filterDrill('pipeline', 'status', status);
        showDrill('Sales Pipeline: ' + label, rows, quoteCols);
    });
});

// Alert cards
document.querySelectorAll('.alert-clickable').forEach(function(card) {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function() {
        var type = this.dataset.alertType;
        var label = this.dataset.alertLabel;
        var rows = drillData[type] || [];
        var cols = type === 'alert_overdue' ? quoteCols : eventColsShort;
        showDrill(label, rows, cols);
    });
});

// Deposit cards
document.querySelectorAll('.deposit-clickable').forEach(function(card) {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function() {
        var rows = drillData['deposit'] || [];
        showDrill('รายการรับเงินมัดจำ MTD', rows, depositCols);
    });
});

// KPI target card
document.getElementById('kpiTargetCard') && (document.getElementById('kpiTargetCard').style.cursor = 'pointer');
document.getElementById('kpiTargetCard') && document.getElementById('kpiTargetCard').addEventListener('click', function() {
    showDrill('รายชื่อพนักงาน vs เป้าหมาย', <?= json_encode(array_map(function($s) use ($selected_year, $selected_month) {
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

// Stat cards - today total
document.getElementById('cardTodayTotal') && document.getElementById('cardTodayTotal').addEventListener('click', function() {
    var rows = drillData['type'].filter(r => r.ev_date === '<?= $selected_date ?>');
    showDrill('งานวันนี้ <?= date('d M Y', strtotime($selected_date)) ?>', rows, eventCols);
});
</script>

<?php include "footer.php"; ?>
