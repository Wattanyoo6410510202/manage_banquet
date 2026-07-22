<?php
require_once __DIR__ . "/config.php";

// AJAX endpoint for menu detail
if (isset($_GET['ajax_menu']) && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $mid = (int)$_GET['id'];
    $q = db_query($conn, "SELECT m.*, t.type_name, mc.category_name 
        FROM function_menu_details m 
        LEFT JOIN master_menu_types t ON m.menu_type_id = t.id 
        LEFT JOIN master_menu_categories mc ON t.category_id = mc.id 
        WHERE m.id = ?", "i", $mid);
    $row = $q ? $q->fetch_assoc() : null;
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch all categories
$categories = db_fetch_all($conn, "SELECT * FROM master_menu_categories ORDER BY sort_order ASC");

// Fetch all menu types grouped by category
$menu_types = db_fetch_all($conn, "SELECT t.id, t.type_name, t.category_id, c.category_name 
    FROM master_menu_types t 
    LEFT JOIN master_menu_categories c ON t.category_id = c.id 
    ORDER BY c.sort_order ASC, t.id ASC");

// Fetch all menu details
$menus = db_fetch_all($conn, "SELECT m.*, t.type_name, mc.category_name 
    FROM function_menu_details m 
    LEFT JOIN master_menu_types t ON m.menu_type_id = t.id 
    LEFT JOIN master_menu_categories mc ON t.category_id = mc.id 
    ORDER BY mc.sort_order ASC, t.id ASC, m.id DESC");

// Group menus by category
$grouped = [];
foreach ($menus as $m) {
    $cat = $m['category_name'] ?? 'อื่นๆ';
    $type = $m['type_name'] ?? 'ไม่ระบุ';
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    if (!isset($grouped[$cat][$type])) $grouped[$cat][$type] = [];
    $grouped[$cat][$type][] = $m;
}

$menuCount = count($menus);
$catCount = count($categories);
$typeCount = count($menu_types);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0c0c1d">
    <title>เมนูอาหาร - Banquet Menu</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        .stat-chip { flex-shrink: 0; min-width: 120px; background: var(--card); border: 1px solid var(--card-border); border-radius: 14px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
        .stat-chip .icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .stat-chip .label { font-size: 0.7rem; color: var(--text-dim); font-weight: 500; }
        .stat-chip .value { font-size: 1.1rem; font-weight: 700; color: #fff; line-height: 1.2; }

        /* ─── Search ─── */
        .search-bar { padding: 4px 16px 10px; }
        .search-input {
            width: 100%; background: var(--card); border: 1px solid var(--card-border);
            border-radius: 12px; color: var(--text); font-family: inherit; font-size: 0.85rem;
            padding: 10px 14px 10px 38px; outline: none; transition: border-color 0.2s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='rgba(255,255,255,0.35)' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: 12px center;
        }
        .search-input::placeholder { color: var(--text-dim); }
        .search-input:focus { border-color: var(--gold); }

        /* ─── Category Pills ─── */
        .cat-scroll { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding: 0 16px 12px; -webkit-overflow-scrolling: touch; }
        .cat-scroll::-webkit-scrollbar { display: none; }
        .cat-pill {
            flex-shrink: 0; background: var(--card); border: 1px solid var(--card-border);
            border-radius: 12px; color: var(--text); font-family: inherit; font-size: 0.8rem;
            font-weight: 600; padding: 8px 16px; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .cat-pill:hover, .cat-pill.active { background: var(--gold-dim); border-color: var(--gold); color: var(--gold); }
        .cat-pill.active { background: rgba(212,168,75,0.25); }
        .cat-pill .cat-count { background: rgba(255,255,255,0.1); border-radius: 8px; padding: 1px 7px; font-size: 0.68rem; font-weight: 700; }
        .cat-pill.active .cat-count { background: rgba(212,168,75,0.3); }

        /* ─── Menu Section ─── */
        .menu-section { padding: 0 16px 16px; }
        .cat-header {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 0 10px; border-bottom: 1px solid var(--card-border);
            margin-bottom: 12px;
        }
        .cat-header .cat-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--gold), #b89441);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; flex-shrink: 0;
        }
        .cat-header .cat-name { font-size: 0.95rem; font-weight: 700; color: #fff; }
        .cat-header .cat-sub { font-size: 0.72rem; color: var(--text-dim); }

        /* ─── Type Group ─── */
        .type-group { margin-bottom: 16px; }
        .type-label {
            font-size: 0.72rem; font-weight: 700; color: var(--gold);
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 6px 0 8px; display: flex; align-items: center; gap: 6px;
        }
        .type-label::after { content: ''; flex: 1; height: 1px; background: var(--card-border); }

        /* ─── Menu Card ─── */
        .menu-card {
            background: var(--card); border: 1px solid var(--card-border);
            border-radius: var(--radius); padding: 16px;
            margin-bottom: 10px; cursor: pointer; transition: all 0.2s;
        }
        .menu-card:hover { border-color: rgba(212,168,75,0.3); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
        .menu-card:active { transform: scale(0.98); }

        .menu-card .menu-items {
            font-size: 0.82rem; font-weight: 500; color: var(--text);
            line-height: 1.6; margin-bottom: 10px; white-space: pre-line;
        }
        .menu-card .menu-meta {
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        }
        .menu-meta .meta-tag {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.7rem; font-weight: 600; padding: 3px 10px;
            border-radius: 8px;
        }
        .meta-tag.price { background: rgba(212,168,75,0.15); color: var(--gold); }
        .meta-tag.pax { background: rgba(13,202,240,0.12); color: #0dcaf0; }
        .meta-tag.bev { background: rgba(25,135,84,0.12); color: #198754; }
        .meta-tag.cost { background: rgba(220,53,69,0.12); color: #dc3545; }

        /* ─── Bottom Sheet ─── */
        .sheet-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0); z-index: 200; pointer-events: none; transition: background 0.3s; }
        .sheet-overlay.active { background: rgba(0,0,0,0.55); pointer-events: auto; }
        .sheet { position: fixed; bottom: 0; left: 0; right: 0; z-index: 210; background: var(--bg2); border-radius: 20px 20px 0 0; max-height: 85vh; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); will-change: transform; overflow: hidden; display: flex; flex-direction: column; }
        .sheet.active { transform: translateY(0); }
        .sheet-handle { display: flex; justify-content: center; padding: 10px 0 6px; }
        .sheet-handle::after { content: ''; width: 36px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; }
        .sheet-top { background: linear-gradient(135deg, var(--gold), #b89441); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .sheet-top h3 { font-size: 0.95rem; font-weight: 700; color: #fff; margin: 0; }
        .sheet-close { width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .sheet-body { padding: 16px 18px calc(20px + var(--safe-bottom)); overflow-y: auto; -webkit-overflow-scrolling: touch; flex: 1; }

        .sheet-section { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--card-border); }
        .sheet-section:first-child { margin-top: 0; padding-top: 0; border-top: none; }
        .sheet-section-title {
            font-size: 0.72rem; font-weight: 700; color: var(--gold);
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
        }
        .sheet-row { display: flex; align-items: flex-start; gap: 12px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .sheet-row:last-child { border-bottom: none; }
        .sheet-row .row-icon { width: 30px; height: 30px; border-radius: 8px; background: var(--card); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: var(--gold); flex-shrink: 0; margin-top: 1px; }
        .sheet-row .row-content { flex: 1; min-width: 0; }
        .sheet-row .row-label { font-size: 0.65rem; font-weight: 500; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.3px; }
        .sheet-row .row-value { font-size: 0.85rem; font-weight: 600; color: #fff; margin-top: 1px; word-break: break-word; line-height: 1.5; white-space: pre-line; }
        .sheet-row .row-value.gold { color: var(--gold); font-size: 1rem; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .empty-icon { font-size: 3rem; color: rgba(255,255,255,0.08); margin-bottom: 16px; }
        .empty-state .empty-text { font-size: 0.85rem; color: var(--text-dim); }

        .app-footer { text-align: center; padding: 16px; font-size: 0.7rem; color: var(--text-dim); padding-bottom: calc(16px + var(--safe-bottom)); }

        /* ─── Skeleton Loading ─── */
        .skeleton { background: linear-gradient(90deg, var(--card) 25%, rgba(255,255,255,0.06) 50%, var(--card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        @media (min-width: 768px) {
            .topbar { padding: 12px 24px; }
            .stats-scroll { padding: 16px 24px 12px; }
            .stat-chip { min-width: 140px; padding: 14px 18px; }
            .search-bar { padding: 4px 24px 14px; }
            .cat-scroll { padding: 0 24px 14px; gap: 10px; }
            .menu-section { padding: 0 24px 24px; }
            .menu-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .menu-grid .menu-card { margin-bottom: 0; }
            .sheet { max-height: 80vh; border-radius: 24px 24px 0 0; }
            .sheet-body { padding: 20px 28px 28px; max-width: 600px; margin: 0 auto; }
        }
        @media (min-width: 1200px) {
            .menu-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @supports (padding: env(safe-area-inset-top)) { .topbar { padding-top: calc(10px + env(safe-area-inset-top)); } }
    </style>
</head>
<body>

<header class="topbar">
    <a class="topbar-brand" href="public_calendar.php">
        <span class="brand-icon"><i class="bi bi-building"></i></span>
        <span>Banquet <span class="text-gold">Menu</span></span>
    </a>
    <a href="login.php" class="topbar-login"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
</header>

<div class="stats-scroll">
    <div class="stat-chip"><div class="icon bg-warning bg-opacity-25 text-warning"><i class="bi bi-folder"></i></div><div><div class="label">หมวดอาหาร</div><div class="value"><?= $catCount ?></div></div></div>
    <div class="stat-chip"><div class="icon bg-info bg-opacity-25 text-info"><i class="bi bi-grid-3x3-gap"></i></div><div><div class="label">ประเภท</div><div class="value"><?= $typeCount ?></div></div></div>
    <div class="stat-chip"><div class="icon bg-success bg-opacity-25 text-success"><i class="bi bi-cup-hot"></i></div><div><div class="label">รายการเมนู</div><div class="value"><?= $menuCount ?></div></div></div>
</div>

<div class="search-bar">
    <input type="text" class="search-input" id="searchInput" placeholder="ค้นหาเมนูอาหาร..." oninput="filterMenus(this.value)">
</div>

<div class="cat-scroll" id="catScroll">
    <button class="cat-pill active" onclick="filterByCategory('all', this)">
        <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
        <span class="cat-count"><?= $menuCount ?></span>
    </button>
    <?php foreach ($categories as $cat): ?>
        <?php
            $catMenuCount = 0;
            if (isset($grouped[$cat['category_name']])) {
                foreach ($grouped[$cat['category_name']] as $typeMenus) {
                    $catMenuCount += count($typeMenus);
                }
            }
        ?>
        <button class="cat-pill" onclick="filterByCategory('<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>', this)">
            <?= htmlspecialchars($cat['category_name']) ?>
            <span class="cat-count"><?= $catMenuCount ?></span>
        </button>
    <?php endforeach; ?>
    <?php if (isset($grouped['อื่นๆ'])): ?>
        <button class="cat-pill" onclick="filterByCategory('อื่นๆ', this)">
            อื่นๆ
            <span class="cat-count"><?= count($grouped['อื่นๆ']) ?></span>
        </button>
    <?php endif; ?>
</div>

<div class="menu-section" id="menuSection">
    <?php if (empty($grouped)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-cup-hot"></i></div>
            <div class="empty-text">ยังไม่มีเมนูอาหารในระบบ</div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $catName => $types): ?>
            <div class="menu-category" data-category="<?= htmlspecialchars($catName, ENT_QUOTES) ?>">
                <div class="cat-header">
                    <div class="cat-icon"><i class="bi bi-bookmark-fill"></i></div>
                    <div>
                        <div class="cat-name"><?= htmlspecialchars($catName) ?></div>
                        <div class="cat-sub"><?= count($types) ?> ประเภท</div>
                    </div>
                </div>

                <?php foreach ($types as $typeName => $typeMenus): ?>
                    <div class="type-group" data-type="<?= htmlspecialchars($typeName, ENT_QUOTES) ?>">
                        <div class="type-label"><?= htmlspecialchars($typeName) ?></div>
                        <div class="menu-grid">
                            <?php foreach ($typeMenus as $menu): ?>
                                <div class="menu-card" data-search="<?= htmlspecialchars(strtolower($menu['menu_items'] . ' ' . $menu['beverage_detail'] . ' ' . $typeName . ' ' . $catName), ENT_QUOTES) ?>"
                                     onclick="openMenuDetail(<?= htmlspecialchars(json_encode($menu), ENT_QUOTES) ?>)">
                                    <div class="menu-items"><?= htmlspecialchars($menu['menu_items']) ?></div>
                                    <div class="menu-meta">
                                        <span class="meta-tag price"><i class="bi bi-tag"></i> <?= number_format($menu['price_per_pax'], 2) ?> บาท</span>
                                        <span class="meta-tag pax"><i class="bi bi-people"></i> <?= number_format($menu['guarantee_pax']) ?> ท่าน</span>
                                        <?php if (!empty($menu['beverage_detail'])): ?>
                                            <span class="meta-tag bev"><i class="bi bi-cup"></i> เครื่องดื่ม</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="app-footer">Banquet Management &copy; <?= date('Y') ?></div>

<!-- Bottom Sheet -->
<div class="sheet-overlay" id="sheetOverlay" onclick="closeSheet()"></div>
<div class="sheet" id="bottomSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-top">
        <h3><i class="bi bi-cup-hot me-1"></i> รายละเอียดเมนู</h3>
        <button class="sheet-close" onclick="closeSheet()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sheet-body" id="sheetBody"></div>
</div>

<script>
const esc = s => s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
const fBath = v => '฿' + parseFloat(v || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

let activeCategory = 'all';
let searchQuery = '';

function filterByCategory(cat, btn) {
    activeCategory = cat;
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function filterMenus(q) {
    searchQuery = (q || '').toLowerCase();
    applyFilters();
}

function applyFilters() {
    document.querySelectorAll('.menu-category').forEach(catEl => {
        const catName = catEl.dataset.category;
        const catMatch = activeCategory === 'all' || catName === activeCategory;
        
        if (!catMatch && !searchQuery) {
            catEl.style.display = 'none';
            return;
        }

        let hasVisibleItems = false;
        catEl.querySelectorAll('.menu-card').forEach(card => {
            const searchText = card.dataset.search || '';
            const textMatch = !searchQuery || searchText.includes(searchQuery);
            const visible = (catMatch || searchQuery) && textMatch;
            card.style.display = visible ? '' : 'none';
            if (visible) hasVisibleItems = true;
        });

        catEl.style.display = hasVisibleItems ? '' : 'none';
    });

    document.querySelectorAll('.type-group').forEach(tg => {
        const visibleCards = tg.querySelectorAll('.menu-card:not([style*="display: none"])');
        tg.style.display = visibleCards.length > 0 ? '' : 'none';
    });
}

function openMenuDetail(menu) {
    let html = `
        <div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-bookmark-fill"></i> หมวดหมู่</div>
            ${row('bi-folder', 'กลุ่มอาหาร', menu.category_name || '-')}
            ${row('bi-grid-3x3-gap', 'ประเภทอาหาร', menu.type_name || '-')}
        </div>
        <div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-cup-hot-fill"></i> รายการอาหาร</div>
            ${row('bi-list-check', 'เมนู', menu.menu_items || '-', false, true)}
        </div>
        <div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-calculator"></i> ราคาและจำนวน</div>
            ${row('bi-tag', 'ราคาขาย/หน่วย', fBath(menu.price_per_pax), true)}
            ${row('bi-coin', 'ราคาทุน/หน่วย', fBath(menu.cost_per_pax))}
            ${row('bi-people', 'การันตีจำนวน', (menu.guarantee_pax || 0) + ' ท่าน')}
        </div>`;

    if (menu.beverage_detail) {
        html += `<div class="sheet-section">
            <div class="sheet-section-title"><i class="bi bi-cup-fill"></i> เครื่องดื่ม</div>
            ${row('bi-cup-straw', 'รายละเอียด', menu.beverage_detail, false, true)}
        </div>`;
    }

    document.getElementById('sheetBody').innerHTML = html;
    openSheet();
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

function openSheet() {
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

/* Swipe to dismiss */
(function(){
    const sh = document.getElementById('bottomSheet');
    let sy=0, cy=0, dragging=false;
    sh.addEventListener('touchstart', e => { if(sh.scrollTop<=0){ sy=e.touches[0].clientY; dragging=true; } }, {passive:true});
    sh.addEventListener('touchmove', e => { if(!dragging) return; cy=e.touches[0].clientY; const d=cy-sy; if(d>0 && sh.scrollTop<=0) sh.style.transform=`translateY(${d}px)`; }, {passive:true});
    sh.addEventListener('touchend', () => { if(cy-sy>80) closeSheet(); sh.style.transform=''; dragging=false; sy=0; cy=0; }, {passive:true});
})();
</script>

</body>
</html>
