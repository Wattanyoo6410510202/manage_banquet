<?php
require_once __DIR__ . "/config.php";

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$rooms = $conn->query("SELECT id, room_name, company_id FROM meeting_rooms WHERE status = 'active' ORDER BY room_name ASC");
$rooms_json = [];
if ($rooms) while($r = $rooms->fetch_assoc()) { $rooms_json[] = $r; }

// AJAX endpoint for full EO detail
if (isset($_GET['ajax_detail']) && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $fid = (int)$_GET['id'];
    $data = [];

    // Main function
    $q = $conn->query("SELECT f.*, r.room_name, c.cust_name, c.cust_phone, c.cust_address, c.cust_email,
        comp.company_name,
        ft.type_name,
        u.name as creator_name,
        ua.name as approver_name
        FROM functions f
        LEFT JOIN meeting_rooms r ON f.room_id=r.id
        LEFT JOIN customers c ON f.customer_id=c.id
        LEFT JOIN companies comp ON f.company_id=comp.id
        LEFT JOIN function_types ft ON f.function_type_id=ft.id
        LEFT JOIN users u ON f.created_by_id=u.id
        LEFT JOIN users ua ON f.approve_by=ua.id
        WHERE f.id=$fid");
    if($q && $q->num_rows > 0) {
        $data['func'] = $q->fetch_assoc();
    }

    // Menus
    $mq = $conn->query("SELECT fm.*, mt.type_name as menu_type_name, mc.category_name FROM function_menus fm LEFT JOIN master_menu_types mt ON fm.menu_set_id=mt.id LEFT JOIN master_menu_categories mc ON mt.category_id=mc.id WHERE fm.function_id=$fid ORDER BY fm.id ASC");
    $data['menus'] = [];
    if($mq) while($r=$mq->fetch_assoc()) $data['menus'][]=$r;

    // Kitchens
    $kq = $conn->query("SELECT fk.*, bt.type_name as k_type_name FROM function_kitchens fk LEFT JOIN master_break_types bt ON fk.k_type_id=bt.id WHERE fk.function_id=$fid ORDER BY fk.k_date ASC, fk.id ASC");
    $data['kitchens'] = [];
    if($kq) while($r=$kq->fetch_assoc()) $data['kitchens'][]=$r;

    // Schedules
    $sq = $conn->query("SELECT * FROM function_schedules WHERE function_id=$fid ORDER BY schedule_date ASC, schedule_hour ASC");
    $data['schedules'] = [];
    if($sq) while($r=$sq->fetch_assoc()) $data['schedules'][]=$r;

    // Finance
    $fq = $conn->query("SELECT * FROM function_finance WHERE function_id=$fid ORDER BY created_at ASC");
    $data['finance'] = [];
    if($fq) while($r=$fq->fetch_assoc()) $data['finance'][]=$r;

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0c0c1d">
    <title>ปฏิทินจัดเลี้ยง</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <style>
        :root {
            --gold: #d4a84b;
            --gold-dim: rgba(212,168,75,0.15);
            --bg: #0c0c1d;
            --bg2: #12122a;
            --bg3: #1a1a3e;
            --card: rgba(255,255,255,0.04);
            --card-border: rgba(255,255,255,0.06);
            --text: #e8e8f0;
            --text-dim: rgba(255,255,255,0.45);
            --radius: 16px;
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }
        .text-gold { color: var(--gold) !important; }

        /* ─── Topbar ─── */
        .topbar {
            position: sticky; top: 0; z-index: 100;
            background: rgba(12,12,29,0.85);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--card-border);
            padding: 10px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar-brand { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1rem; text-decoration: none; color: #fff; }
        .topbar-brand .brand-icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--gold), #b89441); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
        .topbar-login { font-size: 0.8rem; font-weight: 600; color: var(--gold); background: var(--gold-dim); border: 1px solid rgba(212,168,75,0.25); padding: 6px 14px; border-radius: 20px; text-decoration: none; transition: all 0.2s; }
        .topbar-login:active { transform: scale(0.95); }

        /* ─── Stats ─── */
        .stats-scroll { display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; padding: 12px 16px 8px; -webkit-overflow-scrolling: touch; }
        .stats-scroll::-webkit-scrollbar { display: none; }
        .stat-chip { flex-shrink: 0; min-width: 130px; background: var(--card); border: 1px solid var(--card-border); border-radius: 14px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
        .stat-chip .icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .stat-chip .label { font-size: 0.7rem; color: var(--text-dim); font-weight: 500; }
        .stat-chip .value { font-size: 1.1rem; font-weight: 700; color: #fff; line-height: 1.2; }

        /* ─── Filters ─── */
        .filters-bar { display: flex; gap: 8px; padding: 4px 16px 10px; }
        .filter-pill { flex: 1; min-width: 0; background: var(--card); border: 1px solid var(--card-border); border-radius: 12px; color: var(--text); font-family: inherit; font-size: 0.82rem; font-weight: 500; padding: 9px 12px; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='rgba(255,255,255,0.4)' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 30px; }
        .filter-pill option { background: var(--bg2); color: var(--text); }
        .filter-pill:focus { outline: none; border-color: var(--gold); }

        /* ─── Calendar Card ─── */
        .cal-section { padding: 0 12px; }
        .cal-card { background: #fff; border-radius: var(--radius); overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.3); }
        .cal-header { background: var(--bg2); padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--gold); }
        .cal-header h2 { font-size: 0.9rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 6px; }
        .cal-legend { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; }
        .cal-legend::-webkit-scrollbar { display: none; }
        .cal-legend span { flex-shrink: 0; display: inline-flex; align-items: center; gap: 4px; font-size: 0.6rem; font-weight: 600; color: rgba(255,255,255,0.6); }
        .cal-legend span::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .cal-legend .lg-pending::before { background: #ffc107; }
        .cal-legend .lg-approved::before { background: #0dcaf0; }
        .cal-legend .lg-progress::before { background: #0d6efd; }
        .cal-legend .lg-done::before { background: #198754; }
        .cal-legend .lg-quote::before { background: #fd7e14; }

        .cal-card .fc { --fc-border-color: #eee; }
        .cal-card .fc .fc-toolbar { flex-wrap: wrap; gap: 4px; padding: 0 4px; }
        .cal-card .fc .fc-toolbar-center { order: -1; width: 100%; text-align: center; margin-bottom: 2px; }
        .cal-card .fc .fc-toolbar-title { font-size: 1rem !important; font-weight: 700; color: #222; }
        .cal-card .fc .fc-toolbar-chunk { display: flex; gap: 4px; }
        .cal-card .fc .fc-button { font-family: 'Sarabun', sans-serif; font-size: 0.72rem !important; font-weight: 600; padding: 5px 10px !important; border-radius: 8px !important; background: var(--bg2) !important; border-color: var(--bg2) !important; color: #fff !important; }
        .cal-card .fc .fc-button:hover, .cal-card .fc .fc-button-active { background: var(--gold) !important; border-color: var(--gold) !important; }
        .cal-card .fc .fc-col-header-cell-cushion { font-size: 0.7rem; font-weight: 600; color: #555; padding: 6px 0; }
        .cal-card .fc .fc-daygrid-day-number { font-size: 0.78rem; font-weight: 600; color: #333; padding: 3px 6px; }
        .cal-card .fc .fc-daygrid-day-frame { min-height: 48px; }
        .cal-card .fc .fc-daygrid-day-top { padding-top: 2px; }
        .cal-card .fc .fc-daygrid-more-link { font-size: 0.62rem; font-weight: 600; color: var(--gold) !important; }
        .cal-card .fc .fc-event { border-radius: 4px; border: none; }
        .cal-card .fc .fc-day-today { background: rgba(212,168,75,0.06) !important; }
        .cal-card .fc .fc-list-event-title a { font-size: 0.82rem; font-weight: 600; color: #222; }
        .cal-card .fc .fc-list-event-time { font-size: 0.78rem; color: #666; }
        .cal-card .fc .fc-list-day-cushion { background: #f8f9fa; padding: 6px 14px; }
        .cal-card .fc .fc-list-day-text { font-size: 0.8rem; font-weight: 600; color: #333; }
        .cal-card .fc .fc-scrollgrid td, .cal-card .fc .fc-scrollgrid th { border-color: #eee; }
        .cal-card .fc-daygrid-dot-event .fc-event-title { font-size: 0.68rem; font-weight: 600; color: #333; }

        /* ─── Bottom Sheet ─── */
        .sheet-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0); z-index: 200; pointer-events: none; transition: background 0.3s; }
        .sheet-overlay.active { background: rgba(0,0,0,0.55); pointer-events: auto; }
        .sheet { position: fixed; bottom: 0; left: 0; right: 0; z-index: 210; background: var(--bg2); border-radius: 20px 20px 0 0; max-height: 90vh; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); will-change: transform; overflow: hidden; display: flex; flex-direction: column; }
        .sheet.active { transform: translateY(0); }
        .sheet-handle { display: flex; justify-content: center; padding: 10px 0 6px; }
        .sheet-handle::after { content: ''; width: 36px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; }
        .sheet-top { background: linear-gradient(135deg, var(--gold), #b89441); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .sheet-top h3 { font-size: 0.95rem; font-weight: 700; color: #fff; margin: 0; }
        .sheet-close { width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .sheet-body { padding: 16px 18px calc(20px + var(--safe-bottom)); overflow-y: auto; -webkit-overflow-scrolling: touch; flex: 1; }

        /* Sheet title */
        .sheet-title-area { text-align: center; padding-bottom: 14px; border-bottom: 1px solid var(--card-border); margin-bottom: 14px; }
        .sheet-title-area h4 { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 6px 0 2px; line-height: 1.3; }
        .sheet-title-area .badge-st { display: inline-block; font-size: 0.68rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-bottom: 4px; }
        .sheet-title-area .period-tag { font-size: 0.72rem; font-weight: 600; color: var(--gold); }
        .sheet-title-area .eo-code { font-size: 0.7rem; color: var(--text-dim); margin-top: 2px; }

        /* Section dividers */
        .sheet-section {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--card-border);
        }
        .sheet-section-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Rows */
        .sheet-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .sheet-row:last-child { border-bottom: none; }
        .sheet-row .row-icon { width: 30px; height: 30px; border-radius: 8px; background: var(--card); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: var(--gold); flex-shrink: 0; margin-top: 1px; }
        .sheet-row .row-content { flex: 1; min-width: 0; }
        .sheet-row .row-label { font-size: 0.65rem; font-weight: 500; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.3px; }
        .sheet-row .row-value { font-size: 0.88rem; font-weight: 600; color: #fff; margin-top: 1px; word-break: break-word; line-height: 1.4; }
        .sheet-row .row-value.gold { color: var(--gold); font-size: 1rem; }
        .sheet-row .row-value.light { font-weight: 400; color: rgba(255,255,255,0.7); font-size: 0.82rem; }

        /* Menu/kitchen/schedule tables */
        .mini-table { width: 100%; font-size: 0.78rem; border-collapse: collapse; }
        .mini-table th { text-align: left; font-size: 0.65rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid var(--card-border); }
        .mini-table td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.03); color: rgba(255,255,255,0.85); font-weight: 500; vertical-align: top; }
        .mini-table tr:last-child td { border-bottom: none; }

        /* Empty state */
        .empty-msg { text-align: center; padding: 16px; font-size: 0.78rem; color: var(--text-dim); }

        /* Loading */
        .sheet-loading { text-align: center; padding: 40px 20px; }
        .sheet-loading .spinner { width: 28px; height: 28px; border: 3px solid var(--card-border); border-top-color: var(--gold); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .app-footer { text-align: center; padding: 16px; font-size: 0.7rem; color: var(--text-dim); }

        @media (min-width: 768px) {
            .topbar { padding: 12px 24px; }
            .stats-scroll { padding: 16px 24px 12px; }
            .stat-chip { min-width: 160px; padding: 14px 18px; }
            .stat-chip .value { font-size: 1.2rem; }
            .filters-bar { padding: 6px 24px 14px; gap: 12px; }
            .cal-section { padding: 0 24px; }
            .cal-card .fc .fc-toolbar-title { font-size: 1.2rem !important; }
            .cal-card .fc .fc-button { font-size: 0.8rem !important; padding: 6px 14px !important; }
            .cal-card .fc .fc-daygrid-day-frame { min-height: 60px; }
            .cal-legend span { font-size: 0.7rem; }
            .cal-legend span::before { width: 8px; height: 8px; }
            .sheet { max-height: 80vh; border-radius: 24px 24px 0 0; }
            .sheet-body { padding: 20px 28px 28px; max-width: 600px; margin: 0 auto; }
        }
        @supports (padding: env(safe-area-inset-top)) { .topbar { padding-top: calc(10px + env(safe-area-inset-top)); } }
    </style>
</head>
<body>

<header class="topbar">
    <a class="topbar-brand" href="#">
        <span class="brand-icon"><i class="bi bi-building"></i></span>
        <span>Banquet <span class="text-gold">Calendar</span></span>
    </a>
    <a href="login.php" class="topbar-login"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
</header>

<div class="stats-scroll">
    <div class="stat-chip"><div class="icon bg-primary bg-opacity-25 text-primary"><i class="bi bi-calendar-check"></i></div><div><div class="label">วันนี้</div><div class="value" id="statToday">-</div></div></div>
    <div class="stat-chip"><div class="icon bg-info bg-opacity-25 text-info"><i class="bi bi-calendar-month"></i></div><div><div class="label">เดือนนี้</div><div class="value" id="statMonth">-</div></div></div>
    <div class="stat-chip"><div class="icon bg-warning bg-opacity-25 text-warning"><i class="bi bi-people"></i></div><div><div class="label">PAX รวม</div><div class="value" id="statPax">-</div></div></div>
    <div class="stat-chip"><div class="icon bg-success bg-opacity-25 text-success"><i class="bi bi-wallet2"></i></div><div><div class="label">ยอดรวม</div><div class="value" id="statRevenue">-</div></div></div>
</div>

<div class="filters-bar">
    <select id="companyFilter" class="filter-pill" onchange="filterRooms()">
        <option value="all">ทุกโรงแรม</option>
        <?php while ($c = $companies->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
        <?php endwhile; ?>
    </select>
    <select id="roomFilter" class="filter-pill" onchange="updateCalendarEvents()">
        <option value="all">ทุกห้อง</option>
    </select>
</div>

<div class="cal-section">
    <div class="cal-card">
        <div class="cal-header">
            <h2><i class="bi bi-calendar3 text-gold"></i> ปฏิทินจัดเลี้ยง</h2>
            <div class="cal-legend">
                <span class="lg-pending">รออนุมัติ</span>
                <span class="lg-approved">อนุมัติ</span>
                <span class="lg-progress">ดำเนินการ</span>
                <span class="lg-done">จบงาน</span>
                <span class="lg-quote">เสนอราคา</span>
            </div>
        </div>
        <div style="padding:4px;"><div id="calendar"></div></div>
    </div>
</div>

<div class="app-footer">Banquet Management &copy; <?= date('Y') ?></div>

<!-- Bottom Sheet -->
<div class="sheet-overlay" id="sheetOverlay" onclick="closeSheet()"></div>
<div class="sheet" id="bottomSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-top">
        <h3><i class="bi bi-info-circle me-1"></i> รายละเอียด EO</h3>
        <button class="sheet-close" onclick="closeSheet()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sheet-body" id="sheetBody">
        <div class="empty-msg" style="padding:40px 20px;">
            <i class="bi bi-calendar2-week" style="font-size:2.5rem;color:rgba(255,255,255,0.15);display:block;margin-bottom:10px;"></i>
            แตะที่งานบนปฏิทินเพื่อดูรายละเอียด
        </div>
    </div>
</div>

<script src='https://code.jquery.com/jquery-3.7.0.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let calendar;
const allRooms = <?= json_encode($rooms_json) ?>;
const mob = () => window.innerWidth < 768;
const fmt = v => { if(!v||v==='0000-00-00'||v==='0000-00-00 00:00:00') return '-'; const d=new Date(v.includes(' ')?v.replace(' ','T'):v+'T00:00:00'); return isNaN(d)?'-':d.toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'numeric'}); };
const fmtDT = v => { if(!v||v==='0000-00-00 00:00:00'||v==='0000-00-00') return '-'; const d=new Date(v.replace(' ','T')); return isNaN(d)?'-':d.toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'numeric'})+' '+d.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'}); };
const esc = s => s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
const nz = v => { const n=parseFloat((v||'0').toString().replace(/,/g,'')); return isNaN(n)?0:n; };
const fBath = v => '฿'+nz(v).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

function calOpts() {
    const m = mob();
    return {
        initialView: m ? 'listMonth' : 'dayGridMonth',
        height: 'auto', locale: 'th',
        headerToolbar: m
            ? { left: 'prev,next', center: 'title', right: 'listMonth' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth' },
        buttonText: { today:'วันนี้', month:'เดือน', week:'สัปดาห์', day:'วัน', list:'รายการ' },
        dayMaxEvents: m ? 2 : 4,
        moreLinkText: n => '+' + n,
        navLinks: !m,
        listDayFormat: { weekday:'short', day:'numeric', month:'short', year:'numeric' },
    };
}

function makeCal() {
    return new FullCalendar.Calendar(document.getElementById('calendar'), Object.assign({}, calOpts(), {
        datesSet() { updateStats(); },
        eventContent(arg) {
            const p = arg.event.extendedProps;
            const t = arg.event.startStr?.includes('T') ? arg.event.startStr.split('T')[1].substring(0,5) : '';
            const s = (p.status||'').toLowerCase();
            let c = '#0dcaf0';
            if(s==='pending') c='#ffc107'; else if(s==='in progress') c='#0d6efd'; else if(s==='completed') c='#198754';
            else if(p.status==='QT (อนุมัติ)') c='#fd7e14'; else if(p.status==='QT (Draft)') c='#6c757d';
            const fs = mob() ? '0.62rem' : '0.75rem';
            return { html: `<div style="display:flex;align-items:center;gap:3px;font-size:${fs};line-height:1.2;color:#222;">
                <span style="width:5px;height:5px;border-radius:50%;background:${c};flex-shrink:0;"></span>
                ${t ? `<b style="font-size:0.6rem;">${t}</b>` : ''}
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${arg.event.title||''}</span>
            </div>` };
        },
        events: {
            url: 'api/public_calendar_events.php',
            method: 'GET',
            extraParams: function () { return { _: Date.now() }; },
            failure: function () { alert('load failed'); }
        },
        eventClick(info) {
            const p = info.event.extendedProps;
            const id = info.event.id.replace(/^(gen_|sched_|qt_)/,'');
            const isQ = info.event.id.startsWith('qt_');
            const s = p.status.toLowerCase();
            let bc = 'bg-secondary';
            if(isQ) bc=s.includes('draft')?'bg-secondary':'bg-warning text-dark';
            else { if(s==='pending')bc='bg-warning text-dark';else if(s==='confirmed'||s==='approved')bc='bg-info text-dark';else if(s==='in progress')bc='bg-primary';else if(s==='completed')bc='bg-success'; }

            // Show loading immediately
            openSheet(`<div class="sheet-loading"><div class="spinner"></div><div style="font-size:0.82rem;color:var(--text-dim);">กำลังโหลดข้อมูล EO...</div></div>`);

            if (isQ) {
                openSheet(buildQuoteHtml(id, p, bc));
            } else {
                // AJAX fetch full EO detail
                fetch(`public_calendar.php?ajax_detail=1&id=${id}`)
                    .then(r => r.json())
                    .then(d => { openSheet(buildEoHtml(d, p, bc)); })
                    .catch(() => { openSheet(buildEoFallback(id, p, bc)); });
            }
        }
    }));
}

/* ═══ Build EO Sheet HTML ═══ */
function buildEoHtml(d, p, bc) {
    const f = d.func || {};
    const menus = d.menus || [];
    const kitchens = d.kitchens || [];
    const schedules = d.schedules || [];
    const finance = d.finance || [];

    let html = `<div class="sheet-title-area">
        <span class="badge-st ${bc}">${esc(f.status||p.status)}</span>
        <h4>${esc(f.function_name||p.mainTitle)}</h4>
        ${f.function_code ? `<div class="eo-code">${esc(f.function_code)}</div>` : ''}
    </div>`;

    // ── 1. ข้อมูลการจองทั่วไป ──
    html += `<div class="sheet-section">
        <div class="sheet-section-title"><i class="bi bi-clipboard-data"></i> 1. ข้อมูลการจองทั่วไป</div>
        ${row('bi-building','โรงแรม / บริษัท',f.company_name||p.company)}
        ${row('bi-person','ชื่อลูกค้า',f.cust_name||p.customer)}
        ${row('bi-person-badge','ผู้จอง',f.booking_name)}
        ${row('bi-telephone','เบอร์โทร',f.phone||f.cust_phone||p.phone)}
        ${row('bi-envelope','อีเมล',f.cust_email)}
        ${row('bi-building','องค์กร / ที่อยู่',f.organization||f.cust_address)}
        ${row('bi-tag','ประเภทงาน',f.type_name||p.function_type)}
        ${row('bi-calendar-event','วันที่/เวลา เริ่ม',fmtDT(f.start_time||p.start_time))}
        ${row('bi-calendar-check','วันที่/เวลา สิ้นสุด',fmtDT(f.end_time||p.end_time))}
        ${row('bi-clock','ช่วงเวลา',p.period||'-')}
        ${row('bi-door-open','ห้องประชุม',f.room_name||p.room)}
        ${row('bi-hash','ห้องบุ๊คกิ้ง',f.booking_room)}
        ${row('bi-people','จำนวนคน (PAX)',f.pax||p.pax)}
        ${row('bi-info-circle','สถานะ',f.status||p.status)}
        ${f.version_no ? row('bi-copy','เวอร์ชัน','Draft V'+f.version_no+(f.draft_name?' ('+esc(f.draft_name)+')':'')) : ''}
        ${f.is_approved==1 ? '<div class="sheet-row"><div class="row-icon" style="color:#198754;"><i class="bi bi-check-circle-fill"></i></div><div class="row-content"><div class="row-label">สถานะเวอร์ชัน</div><div class="row-value" style="color:#198754;">อนุมัติแล้ว</div></div></div>' : ''}
        ${row('bi-cash-stack','มัดจำ (Deposit)',fBath(f.deposit||p.deposit), true)}
        ${row('bi-wallet2','มูลค่างานทั้งหมด',fBath(f.total_amount||p.total), true)}
    </div>`;

    // ── 2. ตารางกำหนดการ (Schedule) ──
    if (schedules.length > 0) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-list-task"></i> 2. ตารางกำหนดการ (${schedules.length})</div>
            <table class="mini-table">
                <thead><tr><th>วันที่</th><th>เวลา</th><th>รายละเอียด</th><th class="text-end">จำนวน</th></tr></thead>
                <tbody>`;
        schedules.forEach(s => {
            html += `<tr>
                <td style="white-space:nowrap;">${fmt(s.schedule_date)}</td>
                <td style="white-space:nowrap;">${esc(s.schedule_hour||'-')}</td>
                <td>${esc(s.schedule_function||'-')}</td>
                <td class="text-end">${esc(s.schedule_guarantee||'-')}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }

    // ── 3. รายการเบรก (Kitchens) ──
    if (kitchens.length > 0) {
        const kTotal = kitchens.reduce((s,k)=>s+parseFloat(k.k_qty||0)*parseFloat(k.k_price||0),0);
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-cup-fill"></i> 3. รายการเบรก (${kitchens.length})</div>
            <table class="mini-table">
                <thead><tr><th>วันที่</th><th>ประเภท</th><th>รายการ</th><th class="text-end">จำนวน</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">รวม</th></tr></thead>
                <tbody>`;
        kitchens.forEach(k => {
            const sub = parseFloat(k.k_qty||0)*parseFloat(k.k_price||0);
            html += `<tr>
                <td style="white-space:nowrap;">${esc(k.k_date||'-')}</td>
                <td style="white-space:nowrap;">${esc(k.k_type_name||'-')}</td>
                <td>${esc(k.k_item||'-')}${k.k_remark ? '<br><small style="color:var(--text-dim);">'+esc(k.k_remark)+'</small>' : ''}</td>
                <td class="text-end">${k.k_qty||'-'}</td>
                <td class="text-end" style="white-space:nowrap;">${fBath(k.k_price)}</td>
                <td class="text-end" style="white-space:nowrap;">${fBath(sub)}</td>
            </tr>`;
        });
        html += `<tfoot><tr><td colspan="5" class="text-end" style="font-weight:600;">รวมทั้งหมด</td><td class="text-end" style="font-weight:700;color:var(--gold);">${fBath(kTotal)}</td></tr></tfoot>`;
        html += `</tbody></table></div>`;
    }

    // ── 4. รายการเมนูอาหาร ──
    if (menus.length > 0) {
        const mTotal = menus.reduce((s,m)=>s+parseFloat(m.menu_qty||0)*parseFloat(m.menu_price||0),0);
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-cup-hot-fill"></i> 4. รายการเมนูอาหารและเครื่องดื่ม (${menus.length})</div>
            <table class="mini-table">
                <thead><tr><th>วันที่</th><th>หมวด</th><th>ประเภท</th><th>รายการ</th><th class="text-end">จำนวน</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">รวม</th></tr></thead>
                <tbody>`;
        menus.forEach(m => {
            const sub = parseFloat(m.menu_qty||0)*parseFloat(m.menu_price||0);
            html += `<tr>
                <td style="white-space:nowrap;">${esc(m.menu_time||'-')}</td>
                <td style="white-space:nowrap;">${esc(m.category_name||'-')}</td>
                <td style="white-space:nowrap;">${esc(m.menu_type_name||'-')}</td>
                <td>${esc(m.menu_detail||'-')}</td>
                <td class="text-end">${esc(m.menu_qty||'-')}</td>
                <td class="text-end" style="white-space:nowrap;">${fBath(m.menu_price)}</td>
                <td class="text-end" style="white-space:nowrap;">${fBath(sub)}</td>
            </tr>`;
        });
        html += `<tfoot><tr><td colspan="6" class="text-end" style="font-weight:600;">รวมทั้งหมด</td><td class="text-end" style="font-weight:700;color:var(--gold);">${fBath(mTotal)}</td></tr></tfoot>`;
        html += `</tbody></table></div>`;
    }

    // ── 5. รูปแบบการจัดงาน (SET-UP) ──
    if (f.banquet_style) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-grid-3x3"></i> 5. รูปแบบการจัดงาน (SET-UP)</div>
            ${row('bi-easel','การจัดงานเลี้ยง',f.banquet_style, false, true)}
        </div>`;
    }

    // ── 6. ระบบวิศวกรรม (TECHNICAL) ──
    if (f.equipment) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-tools"></i> 6. ระบบวิศวกรรม (TECHNICAL)</div>
            ${row('bi-camera-video','งานช่างและภาพเสียง',f.equipment, false, true)}
        </div>`;
    }

    // ── 7. การตกแต่งและการดูแลทำความสะอาด ──
    const hasDecor = f.backdrop_detail || f.hk_florist_detail;
    if (hasDecor) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-palette-fill"></i> 7. การตกแต่งและการดูแลทำความสะอาด</div>
            ${f.backdrop_detail ? row('bi-image','รายละเอียดฉากหลังและป้าย',f.backdrop_detail, false, true) : ''}
            ${f.hk_florist_detail ? row('bi-flower1','พนักงานทำความสะอาดและจัดดอกไม้',f.hk_florist_detail, false, true) : ''}
        </div>`;
    }

    // ── หมายเหตุ ──
    const hasRemarks = f.remark || f.main_kitchen_remark;
    if (hasRemarks) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-sticky"></i> หมายเหตุ</div>
            ${f.remark ? row('bi-chat-left-text','หมายเหตุทั่วไป',f.remark, false, true) : ''}
            ${f.main_kitchen_remark ? row('bi-fire','ครัว / F&B',f.main_kitchen_remark, false, true) : ''}
        </div>`;
    }

    // ── การเงิน (Finance Details) ──
    html += `<div class="sheet-section">
        <div class="sheet-section-title"><i class="bi bi-wallet2"></i> สรุปการเงิน</div>
        ${row('bi-cash-stack','มัดจำ (Deposit)',fBath(f.deposit||p.deposit), true)}
        ${row('bi-wallet2','มูลค่ารวมทั้งหมด',fBath(f.total_amount||p.total), true)}
    </div>`;

    if (finance.length > 0) {
        const finIncome = finance.filter(x=>x.type==='income');
        const finCost = finance.filter(x=>x.type==='cost');
        const finDeposit = finance.filter(x=>x.type==='deposit');
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-receipt"></i> รายละเอียดการเงิน (${finance.length} รายการ)</div>
            <table class="mini-table">
                <thead><tr><th>วันที่</th><th>ประเภท</th><th>รายละเอียด</th><th class="text-end">จำนวนเงิน</th></tr></thead>
                <tbody>`;
        finance.forEach(fn => {
            const typeLabel = fn.type==='income'?'รายรับ':(fn.type==='cost'?'รายจ่าย':'มัดจำ');
            const typeColor = fn.type==='income'?'#198754':(fn.type==='cost'?'#dc3545':'#ffc107');
            html += `<tr>
                <td style="white-space:nowrap;">${fmt(fn.transaction_date)}</td>
                <td style="white-space:nowrap;"><span style="color:${typeColor};">${esc(typeLabel)}</span></td>
                <td>${esc(fn.detail||'-')}${fn.payment_method?'<br><small style="color:var(--text-dim);">'+esc(fn.payment_method)+'</small>':''}</td>
                <td class="text-end" style="white-space:nowrap;color:${typeColor};">${fBath(fn.amount)}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }

    // ── ข้อมูลการขาย ──
    const hasSales = f.lead_source || f.result || f.inspection_date || f.follow_up_date;
    if (hasSales) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-graph-up"></i> ข้อมูลการขาย</div>
            ${row('bi-signpost','ที่มา Lead',f.lead_source)}
            ${row('bi-trophy','ผลการดำเนินงาน',f.result)}
            ${row('bi-binoculars','วันที่ Inspection',fmt(f.inspection_date))}
            ${row('bi-clock-history','วันที่ Follow Up',fmt(f.follow_up_date))}
        </div>`;
    }

    // ── ข้อมูลระบบ ──
    html += `<div class="sheet-section">
        <div class="sheet-section-title"><i class="bi bi-person-check"></i> ข้อมูลระบบ</div>
        ${row('bi-person-badge','สร้างโดย',f.creator_name||f.created_by)}
        ${row('bi-calendar-plus','สร้างเมื่อ',fmtDT(f.created_at))}
        ${f.approver_name ? row('bi-shield-check','อนุมัติโดย',f.approver_name) : ''}
        ${f.approve_date ? row('bi-check-circle','อนุมัติเมื่อ',fmtDT(f.approve_date)) : ''}
        ${f.modify ? row('bi-pencil','แก้ไขล่าสุด',fmtDT(f.modify)) : ''}
        ${f.cancel_reason ? row('bi-x-octagon','เหตุผลยกเลิก',f.cancel_reason, false, true) : ''}
    </div>`;

    // ── ไฟล์แนบ ──
    const att1 = f.file_attachment1, att2 = f.file_attachment2, att3 = f.file_attachment3;
    if (att1 || att2 || att3) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-paperclip"></i> ไฟล์แนบ</div>`;
        if (att1) html += `<div class="sheet-row"><div class="row-icon"><i class="bi bi-file-earmark"></i></div><div class="row-content"><div class="row-value"><a href="uploads/${esc(att1)}" target="_blank" style="color:var(--gold);text-decoration:none;">📎 ${esc(att1.split('/').pop())}</a></div></div></div>`;
        if (att2) html += `<div class="sheet-row"><div class="row-icon"><i class="bi bi-file-earmark"></i></div><div class="row-content"><div class="row-value"><a href="uploads/${esc(att2)}" target="_blank" style="color:var(--gold);text-decoration:none;">📎 ${esc(att2.split('/').pop())}</a></div></div></div>`;
        if (att3) html += `<div class="sheet-row"><div class="row-icon"><i class="bi bi-file-earmark"></i></div><div class="row-content"><div class="row-value"><a href="uploads/${esc(att3)}" target="_blank" style="color:var(--gold);text-decoration:none;">📎 ${esc(att3.split('/').pop())}</a></div></div></div>`;
        html += `</div>`;
    }

    return html;
}

function buildEoFallback(id, p, bc) {
    return `<div class="sheet-title-area">
        <span class="badge-st ${bc}">${esc(p.status)}</span>
        <h4>${esc(p.mainTitle)}</h4>
    </div>
    ${row('bi-person','ลูกค้า',p.customer||'ไม่ได้ระบุ')}
    ${row('bi-telephone','เบอร์โทร',p.phone)}
    ${row('bi-geo-alt','ห้อง',p.room)}
    ${row('bi-people','จำนวนคน',p.pax&&p.pax!=='0'?p.pax+' คน':'-')}
    ${row('bi-wallet2','มูลค่ารวม',fBath(p.total),true)}
    ${row('bi-cash-stack','มัดจำ',fBath(p.deposit))}
    ${p.function_type?row('bi-tag','ประเภทงาน',p.function_type):''}`;
}

/* ═══ Build Quotation Sheet ═══ */
function buildQuoteHtml(id, p, bc) {
    return `<div class="sheet-title-area">
        <span class="badge-st ${bc}">${esc(p.status)}</span>
        <h4>${esc(p.mainTitle)}</h4>
        <div class="eo-code">ใบเสนอราคา #${id}</div>
    </div>
    ${row('bi-person','ลูกค้า',p.customer||'ไม่ได้ระบุ')}
    ${row('bi-telephone','เบอร์โทร',p.phone)}
    ${row('bi-wallet2','มูลค่ารวม',fBath(p.total),true)}`;
}

function row(icon, label, value, isGold, isLight) {
    if (!value || value === '-') return '';
    return `<div class="sheet-row">
        <div class="row-icon"><i class="bi ${icon}"></i></div>
        <div class="row-content">
            <div class="row-label">${esc(label)}</div>
            <div class="row-value${isGold?' gold':''}${isLight?' light':''}">${isLight ? esc(value).replace(/\n/g,'<br>') : esc(value)}</div>
        </div>
    </div>`;
}

/* ═══ Sheet open/close ═══ */
function openSheet(html) {
    document.getElementById('sheetBody').innerHTML = html;
    const ov = document.getElementById('sheetOverlay');
    const sh = document.getElementById('bottomSheet');
    requestAnimationFrame(() => { ov.classList.add('active'); sh.classList.add('active'); });
    document.body.style.overflow = 'hidden';
}
function closeSheet() {
    const ov = document.getElementById('sheetOverlay');
    const sh = document.getElementById('bottomSheet');
    sh.classList.remove('active');
    setTimeout(() => ov.classList.remove('active'), 350);
    document.body.style.overflow = '';
}

/* ═══ Swipe to dismiss ═══ */
(function(){
    const sh = document.getElementById('bottomSheet');
    let sy=0, cy=0, dragging=false;
    sh.addEventListener('touchstart', e => { if(sh.scrollTop<=0){ sy=e.touches[0].clientY; dragging=true; } }, {passive:true});
    sh.addEventListener('touchmove', e => { if(!dragging) return; cy=e.touches[0].clientY; const d=cy-sy; if(d>0 && sh.scrollTop<=0) sh.style.transform=`translateY(${d}px)`; }, {passive:true});
    sh.addEventListener('touchend', () => { if(cy-sy>80) closeSheet(); sh.style.transform=''; dragging=false; sy=0; cy=0; }, {passive:true});
})();

function updateStats() {
    if(!calendar) return;
    const ev = calendar.getEvents().filter(e=>e.display!=='none'&&!(e.id.startsWith('qt_')&&e.extendedProps.status.toLowerCase().includes('draft')));
    const now=new Date(), ts=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
    const ms=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0');
    const td=ev.filter(e=>e.startStr.startsWith(ts)), mt=ev.filter(e=>e.startStr.startsWith(ms));
    document.getElementById('statToday').textContent=td.length+' งาน';
    document.getElementById('statMonth').textContent=mt.length+' งาน';
    document.getElementById('statPax').textContent=mt.reduce((s,e)=>s+(parseInt(e.extendedProps.pax)||0),0)+' คน';
    const rev=mt.reduce((s,e)=>s+nz(e.extendedProps.total),0);
    document.getElementById('statRevenue').textContent='฿'+rev.toLocaleString('en-US',{minimumFractionDigits:0});
}

function filterRooms(){
    const cid=document.getElementById('companyFilter').value;
    const rs=document.getElementById('roomFilter');
    rs.innerHTML='<option value="all">ทุกห้อง</option>';
    allRooms.forEach(r=>{ if(cid==='all'||r.company_id==cid){ const o=document.createElement('option'); o.value=r.id; o.text=r.room_name; rs.add(o); }});
    updateCalendarEvents();
}
function updateCalendarEvents(){
    if(!calendar) return;
    const rid=document.getElementById('roomFilter').value;
    calendar.getEvents().forEach(e=>{ e.setProp('display',(rid==='all'||e.extendedProps.room_id==rid)?'auto':'none'); });
    updateStats();
}

document.addEventListener('DOMContentLoaded', () => { calendar=makeCal(); calendar.render(); updateStats(); });
let rT, lM=mob();
window.addEventListener('resize', ()=>{ clearTimeout(rT); rT=setTimeout(()=>{ const n=mob(); if(n===lM) return; lM=n; calendar.destroy(); calendar=makeCal(); calendar.render(); updateStats(); }, 400); });
</script>
</body>
</html>
