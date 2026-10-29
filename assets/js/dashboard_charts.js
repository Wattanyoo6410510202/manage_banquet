/* =========================================================
   กราฟของหน้า Dashboard & รายงาน (Chart.js)
   ข้อมูลมาจาก window.INS_CHARTS, รายการสำหรับ drawer มาจาก window.INS_DATA
   ทุกกราฟกดได้ → เปิด drawer รายการงานที่อยู่เบื้องหลังตัวเลขนั้น
   ========================================================= */
(function () {
    const C = window.INS_CHARTS, D = window.INS_DATA;
    if (!C || !window.Chart) return;

    const css = getComputedStyle(document.documentElement);
    const tok = (n, f) => (css.getPropertyValue(n) || '').trim() || f;
    const GOLD = tok('--gold', '#b89441'), GOLD_SOFT = tok('--gold-200', '#e9d8ad');
    const INK = tok('--ink', '#15181c'), MUTED = tok('--muted', '#50565f'), LINE = tok('--line', '#e8e4db');
    const OTHER = '#b9b6ae';
    // ลำดับสีหมวดหมู่คงที่ (ผ่านการตรวจ CVD แล้ว) — ห้ามสลับลำดับ
    const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    const STATE = { open: tok('--warning', '#965a00'), eo: tok('--info', '#1d5fa8'), won: tok('--success', '#1c7a48'), lost: tok('--danger', '#b42318') };

    Chart.defaults.font.family = tok('--font', 'sans-serif');
    Chart.defaults.font.size = 12;
    Chart.defaults.color = MUTED;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.display = false;
    Object.assign(Chart.defaults.plugins.tooltip, {
        backgroundColor: INK, padding: 10, cornerRadius: 8, displayColors: false,
        titleFont: { weight: '600' }
    });

    const { baht, num } = window.Ins;
    const short = n => {
        n = Number(n) || 0;
        if (n >= 1e6) return '฿' + (n / 1e6).toFixed(n >= 1e7 ? 0 : 1) + 'M';
        if (n >= 1e3) return '฿' + Math.round(n / 1e3) + 'K';
        return '฿' + Math.round(n);
    };
    const gridY = { grid: { color: LINE, drawTicks: false }, border: { display: false }, ticks: { padding: 6, callback: v => short(v) } };
    const plainX = { grid: { display: false }, border: { color: LINE }, ticks: { padding: 4, autoSkip: true, maxRotation: 0 } };

    /* ป้ายค่าบนแท่ง — อ่านได้ทันทีโดยไม่ต้องชี้ */
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart, _a, o) {
            if (!o || !o.fmt) return;
            const { ctx } = chart, horiz = chart.options.indexAxis === 'y';
            ctx.save();
            ctx.font = '600 11px ' + Chart.defaults.font.family;
            ctx.fillStyle = INK;
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((el, i) => {
                const v = chart.data.datasets[0].data[i];
                if (!v) return;
                const t = o.fmt(v, i);
                if (horiz) { ctx.textAlign = 'left'; ctx.fillText(t, el.x + 6, el.y); }
                else if (!o.sparse || chart.data.labels.length <= 14) { ctx.textAlign = 'center'; ctx.fillText(t, el.x, el.y - 9); }
            });
            ctx.restore();
        }
    };

    const rowsFor = (key, field, value, allValues) => {
        let rows = D[key] || [];
        if (!field) return rows;
        if (value === '__other__') return rows.filter(r => !allValues.includes(r[field]));
        return rows.filter(r => String(r[field] ?? '') === String(value));
    };
    const pickIndex = (chart, evt, mode) => {
        const pts = chart.getElementsAtEventForMode(evt, mode || 'index', { intersect: false }, true);
        return pts.length ? pts[0].index : -1;
    };
    const pointer = (evt, els) => { evt.native.target.style.cursor = els.length ? 'pointer' : 'default'; };

    /* ---------- 1) รายได้ตามช่วง (แท่ง) ---------- */
    const t = C.trend, elT = document.getElementById('cTrend');
    if (elT) {
        const chart = new Chart(elT, {
            type: 'bar',
            data: {
                labels: t.labels,
                datasets: [{
                    data: t.values, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom',
                    backgroundColor: t.values.map((_, i) => (t.current < 0 || i === t.current) ? GOLD : GOLD_SOFT),
                    hoverBackgroundColor: GOLD, maxBarThickness: 40, minBarLength: 0
                }]
            },
            options: {
                layout: { padding: { top: 20 } },
                onHover: pointer,
                plugins: {
                    tooltip: { callbacks: { title: i => t.titles[i[0].dataIndex], label: c => 'รายได้ ' + baht(c.parsed.y) + ' · ' + t.counts[c.dataIndex] + ' งาน' } },
                    valueLabels: { fmt: v => short(v), sparse: true }
                },
                scales: { y: { beginAtZero: true, ...gridY }, x: plainX }
            },
            plugins: [valueLabels]
        });
        elT.onclick = e => {
            const i = pickIndex(chart, e, 'index');
            if (i >= 0) Ins.open(t.titles[i], D[t.keys[i]] || [], 'event', { link: t.links[i], linkText: t.labels.length === 12 ? 'ดูรายเดือน' : 'ดูรายวัน' });
        };
    }

    /* ---------- 2) ยอดสะสมเทียบช่วงก่อน (เส้น) ---------- */
    const cu = C.cum, elC = document.getElementById('cCum');
    if (cu && elC) {
        const ds = [
            { label: 'ช่วงที่เลือก', data: cu.cur, borderColor: GOLD, backgroundColor: GOLD, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: .2, spanGaps: false },
            { label: C.prevWord, data: cu.prev, borderColor: OTHER, backgroundColor: OTHER, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: .2 }
        ];
        if (cu.target) ds.push({ label: 'เป้า', data: cu.target, borderColor: MUTED, borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 0 });
        const chart = new Chart(elC, {
            type: 'line',
            data: { labels: cu.labels, datasets: ds },
            options: {
                interaction: { mode: 'index', intersect: false },
                onHover: (e, els) => pointer(e, els),
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 14, boxHeight: 2, padding: 14, color: MUTED } },
                    tooltip: { displayColors: true, callbacks: { title: i => t.titles[i[0].dataIndex], label: c => ' ' + c.dataset.label + ' ' + (c.parsed.y == null ? '-' : baht(c.parsed.y)) } }
                },
                scales: { y: { beginAtZero: true, ...gridY }, x: plainX }
            }
        });
        elC.onclick = e => {
            const i = pickIndex(chart, e, 'index');
            if (i >= 0) Ins.open(t.titles[i], D[t.keys[i]] || [], 'event', { link: t.links[i], linkText: 'ดูรายละเอียด' });
        };
    }

    /* ---------- 3) ผลการเสนอราคา (แท่งนอน สีตามสถานะ) ---------- */
    const f = C.funnel, elF = document.getElementById('cFunnel');
    if (elF) {
        const chart = new Chart(elF, {
            type: 'bar',
            data: { labels: f.labels, datasets: [{ data: f.values, backgroundColor: f.fv.map(k => STATE[k]), borderRadius: 4, borderSkipped: 'start', maxBarThickness: 26 }] },
            options: {
                indexAxis: 'y', layout: { padding: { right: 110 } }, onHover: pointer,
                plugins: {
                    tooltip: { callbacks: { label: c => c.parsed.x + ' ใบ · ' + baht(f.money[c.dataIndex]) } },
                    valueLabels: { fmt: (v, i) => v + ' ใบ · ' + short(f.money[i]) }
                },
                scales: { x: { display: false, beginAtZero: true }, y: { grid: { display: false }, border: { display: false }, ticks: { color: INK } } }
            },
            plugins: [valueLabels]
        });
        elF.onclick = e => {
            const i = pickIndex(chart, e, 'y');
            if (i >= 0) Ins.open('ใบเสนอราคา: ' + f.labels[i] + ' · ' + C.label, rowsFor('deals', 'state', f.fv[i]), 'deal');
        };
    }

    /* ---------- 4) โดนัทสัดส่วน + คำอธิบายที่กดได้ ---------- */
    function donut(id, g, what) {
        const el = document.getElementById(id);
        if (!el || !g.labels.length) return;
        const colors = g.fv.map((v, i) => v === '__other__' ? OTHER : SERIES[i % SERIES.length]);
        const total = g.values.reduce((s, v) => s + v, 0);
        const open = i => Ins.open(what + ': ' + g.labels[i] + ' · ' + C.label, rowsFor('confirmed', g.field, g.fv[i], g.fv), 'event');
        const chart = new Chart(el, {
            type: 'doughnut',
            data: { labels: g.labels, datasets: [{ data: g.values, backgroundColor: colors, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
            options: {
                cutout: '66%', onHover: pointer,
                plugins: { tooltip: { callbacks: { label: c => c.label + ' · ' + baht(c.parsed) + ' (' + (total ? Math.round(c.parsed / total * 100) : 0) + '%)' } } }
            }
        });
        el.onclick = e => { const i = pickIndex(chart, e, 'nearest'); if (i >= 0) open(i); };
        const lg = document.getElementById(id + 'Lgd');
        lg.innerHTML = g.labels.map((l, i) => '<li tabindex="0" data-i="' + i + '"><span class="sw" style="background:' + colors[i] + '"></span>'
            + '<span class="nm">' + Ins.esc(l) + '</span><span class="vl">' + short(g.values[i]) + '</span>'
            + '<span class="pc">' + (total ? Math.round(g.values[i] / total * 100) : 0) + '%</span></li>').join('');
        lg.querySelectorAll('li').forEach(li => {
            li.onclick = () => open(+li.dataset.i);
            li.onkeydown = e => { if (e.key === 'Enter') open(+li.dataset.i); };
        });
    }
    donut('cType', C.type, 'ประเภทงาน');
    donut('cCompany', C.company, 'โรงแรม');
    donut('cPeriod', C.period, 'ช่วงเวลา');

    /* ---------- 5) แท่งนอน: เซลล์ / ห้อง ---------- */
    function hbar(id, g, fmt, what, pad) {
        const el = document.getElementById(id);
        if (!el || !g.labels.length) return;
        const chart = new Chart(el, {
            type: 'bar',
            data: { labels: g.labels, datasets: [{ data: g.values, backgroundColor: g.fv.map(v => v === '__other__' ? OTHER : GOLD), borderRadius: 4, borderSkipped: 'start', maxBarThickness: 20 }] },
            options: {
                indexAxis: 'y', layout: { padding: { right: pad || 70 } }, onHover: pointer,
                plugins: { tooltip: { callbacks: { label: c => fmt(c.parsed.x, c.dataIndex) } }, valueLabels: { fmt } },
                scales: { x: { display: false, beginAtZero: true }, y: { grid: { display: false }, border: { display: false }, ticks: { color: INK, callback(v) { const s = this.getLabelForValue(v); return s.length > 18 ? s.slice(0, 17) + '…' : s; } } } }
            },
            plugins: [valueLabels]
        });
        el.onclick = e => {
            const i = pickIndex(chart, e, 'y');
            if (i >= 0) Ins.open(what + ': ' + g.labels[i] + ' · ' + C.label, rowsFor('confirmed', g.field, g.fv[i], g.fv), 'event');
        };
    }
    hbar('cStaff', C.staff, (v, i) => short(v) + (C.staffPct[i] != null ? ' (' + C.staffPct[i] + '%)' : ''), 'เซลล์', 90);
    hbar('cRoom', C.room, v => num(v) + ' งาน', 'ห้อง');

    /* ---------- 6) ขนาดงาน (แท่งตั้ง) ---------- */
    const s = C.size, elS = document.getElementById('cSize');
    if (elS) {
        const chart = new Chart(elS, {
            type: 'bar',
            data: { labels: s.labels, datasets: [{ data: s.values, backgroundColor: GOLD, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 48 }] },
            options: {
                layout: { padding: { top: 20 } }, onHover: pointer,
                plugins: { tooltip: { callbacks: { label: c => c.parsed.y + ' งาน' } }, valueLabels: { fmt: v => v + ' งาน' } },
                scales: { y: { display: false, beginAtZero: true }, x: { grid: { display: false }, border: { color: LINE }, ticks: { color: INK } } }
            },
            plugins: [valueLabels]
        });
        elS.onclick = e => {
            const i = pickIndex(chart, e, 'index');
            if (i >= 0) Ins.open('งานขนาด ' + s.labels[i] + ' · ' + C.label, rowsFor('confirmed', 'size', s.fv[i]), 'event');
        };
    }
})();
