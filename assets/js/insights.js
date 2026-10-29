/* =========================================================
   Dashboard + รายงาน — drawer รายละเอียด, tooltip กราฟ, ฟอร์แมตตัวเลข
   ใช้คู่กับ window.INS_DATA ที่หน้าเพจฝังมา:
     <el data-drill="confirmed" data-kind="event" data-field="type" data-value="..." data-title="...">
   ========================================================= */
(function () {
    const TH_M = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const baht = n => '฿' + Math.round(Number(n) || 0).toLocaleString('th-TH');
    const num = n => Math.round(Number(n) || 0).toLocaleString('th-TH');
    const thDate = ymd => {
        if (!ymd) return '-';
        const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number);
        return d + ' ' + TH_M[m] + ' ' + String(y + 543).slice(2);
    };

    const STATUS = {
        Confirmed: ['ยืนยันแล้ว', 'ok'], 'In Progress': ['กำลังจัด', 'info'], Completed: ['จัดเสร็จแล้ว', 'ok'],
        Cancelled: ['ยกเลิก', 'crit'], Pending: ['รออนุมัติ', 'warn']
    };
    const DEAL = { won: ['ได้งาน', 'ok'], eo: ['เปิดงานแล้ว รออนุมัติ', 'info'], open: ['รอลูกค้าตัดสินใจ', 'warn'], lost: ['ไม่ได้งาน', 'crit'] };
    const pill = (map, k) => { const s = map[k] || [k || '-', '']; return '<span class="pill ' + s[1] + '">' + esc(s[0]) + '</span>'; };

    /* คอลัมน์ของแต่ละชนิดข้อมูล: [หัว, ฟังก์ชันสร้างเซลล์, class] */
    const KINDS = {
        event: {
            cols: [
                ['วันที่', r => thDate(r.date) + '<span class="sm">' + esc(r.time) + '</span>', 'nw'],
                ['งาน', r => '<a href="view.php?id=' + r.id + '">' + esc(r.name) + '</a><span class="sm">' + esc(r.customer) + ' · ' + esc(r.type) + '</span>'],
                ['ห้อง / เซลล์', r => esc(r.room) + '<span class="sm">' + esc(r.staff) + '</span>'],
                ['คน', r => num(r.pax), 'c'],
                ['มัดจำ', r => r.deposit > 0 ? baht(r.deposit) : '<span class="pill warn">ยังไม่มี</span>', 'r'],
                ['มูลค่า', r => '<b>' + baht(r.total) + '</b><span class="sm">' + pill(STATUS, r.approve === 2 ? 'Cancelled' : (r.approve === 0 ? 'Pending' : r.status)) + '</span>', 'r']
            ],
            sum: rows => rows.length + ' งาน · ' + num(rows.reduce((s, r) => s + r.pax, 0)) + ' คน · รวม ' + baht(rows.reduce((s, r) => s + r.total, 0))
        },
        deposit: {
            cols: [
                ['วันที่รับ', r => thDate(r.date), 'nw'],
                ['งาน', r => (r.id ? '<a href="view.php?id=' + r.id + '">' + esc(r.name) + '</a>' : (r.qid ? '<a href="quotation_view.php?id=' + r.qid + '">' + esc(r.name) + '</a>' : esc(r.name)))
                    + '<span class="sm">' + esc(r.detail) + (r.cancelled ? ' · <span class="pill crit">งานยกเลิกแล้ว</span>' : '') + '</span>'],
                ['ช่องทาง', r => esc(r.method)],
                ['จำนวนเงิน', r => '<b>' + baht(r.amount) + '</b>', 'r']
            ],
            sum: rows => rows.length + ' รายการ · รวม ' + baht(rows.reduce((s, r) => s + r.amount, 0))
        },
        deal: {
            cols: [
                ['วันจัดงาน', r => thDate(r.date), 'nw'],
                ['ใบเสนอราคา', r => '<a href="quotation_view.php?id=' + r.id + '">' + esc(r.name) + '</a><span class="sm">' + esc(r.quote_no) + (r.versions > 1 ? ' · แก้ไข ' + r.versions + ' ครั้ง' : '') + '</span>'],
                ['ลูกค้า / เซลล์', r => esc(r.customer) + '<span class="sm">' + esc(r.staff) + '</span>'],
                ['สถานะ', r => pill(DEAL, r.state)],
                ['มูลค่า', r => '<b>' + baht(r.total) + '</b>', 'r']
            ],
            sum: rows => rows.length + ' ดีล · รวม ' + baht(rows.reduce((s, r) => s + r.total, 0))
        },
        sale: {
            cols: [
                ['ใบเสนอราคา', r => '<a href="quotation_view.php?id=' + r.id + '">' + esc(r.quote_no) + '</a><span class="sm">' + thDate(r.created) + ' · ' + esc(r.staff) + '</span>', 'nw'],
                ['ลูกค้า / งาน', r => esc(r.customer) + '<span class="sm">' + esc(r.name) + '</span>'],
                ['วันจัดงาน', r => thDate(r.date), 'nw'],
                ['สถานะ', r => '<span class="pill ' + (r.group === 'won' ? 'ok' : r.group === 'lost' ? 'crit' : 'warn') + '">' + esc(r.status) + '</span>'
                    + '<span class="sm"' + (r.overdue ? ' style="color:var(--danger);font-weight:700"' : '') + '>ติดตาม ' + (r.next_follow ? thDate(r.next_follow) : '-') + '</span>'],
                ['ยอดสุทธิ', r => '<b>' + baht(r.total) + '</b><span class="sm">รับแล้ว ' + baht(r.paid) + '</span>', 'r']
            ],
            sum: rows => rows.length + ' ดีล · ยอดสุทธิ ' + baht(rows.reduce((s, r) => s + r.total, 0))
        }
    };

    let drawer, tip;

    function open(title, rows, kind, opts = {}) {
        const k = KINDS[kind] || KINDS.event;
        const el = document.getElementById('insDrawer');
        el.querySelector('.offcanvas-title').textContent = title;
        el.querySelector('.d-sum').textContent = rows.length ? k.sum(rows) : '';
        let html;
        if (!rows.length) {
            html = '<div class="ins-empty">ไม่มีรายการ</div>';
        } else {
            html = '<table class="ins-tbl"><thead><tr>' + k.cols.map(c => '<th class="' + (c[2] || '') + '">' + c[0] + '</th>').join('') + '</tr></thead><tbody>'
                + rows.map(r => '<tr>' + k.cols.map(c => '<td class="' + (c[2] || '') + '">' + c[1](r) + '</td>').join('') + '</tr>').join('')
                + '</tbody></table>';
        }
        el.querySelector('.offcanvas-body').innerHTML = html;
        const foot = el.querySelector('.d-foot');
        foot.innerHTML = opts.link ? '<a class="btn btn-sm btn-outline-secondary" href="' + esc(opts.link) + '">' + esc(opts.linkText || 'ดูรายงาน') + ' <i class="bi bi-arrow-right"></i></a>' : '';
        foot.classList.toggle('d-none', !opts.link);
        drawer = drawer || new bootstrap.Offcanvas(el);
        drawer.show();
    }

    /* คลิกอะไรก็ได้ที่มี data-drill → เปิด drawer */
    document.addEventListener('click', e => {
        const t = e.target.closest('[data-drill]');
        if (!t || e.target.closest('a[href]')) return;
        const d = t.dataset;
        let rows = (window.INS_DATA || {})[d.drill] || [];
        if (d.field) rows = rows.filter(r => String(r[d.field] ?? '') === d.value);
        open(d.title || '', rows, d.kind || 'event', { link: d.link, linkText: d.linkText });
    });
    document.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('li[data-drill], tr[data-drill]')) { e.preventDefault(); e.target.click(); }
    });

    /* tooltip ของกราฟ: data-tip (HTML ที่ escape แล้วฝั่ง PHP) */
    function showTip(t) {
        tip = tip || Object.assign(document.body.appendChild(document.createElement('div')), { className: 'ins-tip' });
        tip.innerHTML = t.dataset.tip;
        const r = t.getBoundingClientRect();
        tip.classList.add('show');
        const w = tip.offsetWidth, h = tip.offsetHeight;
        tip.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2)) + 'px';
        tip.style.top = Math.max(8, r.top - h - 8) + 'px';
    }
    const hideTip = () => tip && tip.classList.remove('show');
    document.addEventListener('mouseover', e => { const t = e.target.closest('[data-tip]'); t ? showTip(t) : hideTip(); });
    document.addEventListener('focusin', e => { const t = e.target.closest('[data-tip]'); t ? showTip(t) : hideTip(); });
    window.addEventListener('scroll', hideTip, true);

    window.Ins = { open, baht, num, thDate, esc };
})();
