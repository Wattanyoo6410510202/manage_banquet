<style>
    .card {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        /* แก้ตรงนี้: ลด margin ล่างให้เหลือแค่ 0 หรือ 2px เพื่อไม่ให้ล้นจอ */
        margin-bottom: 2px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: none;

    }

    /* --- DataTables & Buttons (Active State) --- */
    /* ปุ่ม Pagination หน้าที่กำลังเปิด (Active) */
    .page-item.active .page-link {
        background-color: var(--hotel-gold) !important;
        border-color: var(--hotel-gold) !important;
        color: white !important;
    }

    /* สำหรับ Checkbox ทั่วไป */
    input[type="checkbox"] {
        accent-color: var(--hotel-gold);
        /* กำหนดสีหลักของ Checkbox เป็นสีทอง */
        cursor: pointer;
    }

    /* ถ้าจารใช้คลาส .form-check-input ของ Bootstrap */
    .form-check-input:checked {
        background-color: var(--hotel-gold) !important;
        border-color: var(--hotel-gold) !important;
        box-shadow: 0 0 0 0.25rem var(--hotel-gold-light);
    }

    .form-check-input:focus {
        border-color: var(--hotel-gold);
        box-shadow: 0 0 0 0.25rem var(--hotel-gold-light);
    }

    /* --- 2. ปรับแต่งช่อง Search สีทอง (ตามรูป image_bfa91d.png) --- */
    /* ปรับแต่ง input ของ DataTable */
    .dataTables_filter input {
        border: 1px solid #ddd;
    }

    /* --- 3. ปรับแต่ง Dropdown (Show entries) ให้เป็นสีทองด้วย (ภาพ image_bfa5d2.jpg) --- */
    .dataTables_length select {
        border: 1px solid #ddd;
    }

    .dataTables_length select:focus {
        outline: none !important;
        border-color: var(--hotel-gold) !important;
        box-shadow: 0 0 0 0.25rem var(--hotel-gold-light) !important;
    }

    /* เมื่อคลิกที่ช่อง Search ให้ขอบเป็นสีทอง และมีเงาเรืองทอง */
    .dataTables_filter input:focus {
        outline: none !important;
        border-color: var(--hotel-gold) !important;
        box-shadow: 0 0 0 0.25rem var(--hotel-gold-light) !important;
    }

    /* ปรับแต่งช่อง input ทั่วไปในฟอร์มด้วย */
    .form-control:focus {
        border-color: var(--hotel-gold) !important;
        box-shadow: 0 0 0 0.25rem var(--hotel-gold-light) !important;
    }

    /* เฉพาะ Checkbox ใน DataTable (ถ้ามี) */
    table.dataTable tbody td input[type="checkbox"]:checked {
        background-color: var(--hotel-gold);
    }

    .form-check-input:hover {
        border-color: var(--hotel-gold);
    }

    .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0 !important;
    }

    .dataTables_wrapper {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .dataTables_scrollBody {
        height: calc(100vh - 350px) !important;
        max-height: none !important;
        flex: 1 1 auto;
        border-bottom: 1px solid #eee !important;
    }

    /* ส่วนหัว (Search) */
    .dataTables_wrapper .p-3.d-flex:first-child {
        padding: 0.6rem 1rem !important;
        /* บีบหัวตารางให้แคบลงนิดนึง */
    }

    /* ส่วนท้าย (Pagination) - รีดให้บางที่สุด */
    .dataTables_wrapper .p-3.d-flex:last-child {
        padding: 0.4rem 1rem !important;
        /* ลด padding ลงเหลือ 0.4rem */
        margin-top: auto;
        background: #fff;
        border-top: 1px solid #f1f1f1;
    }

    /* ตกแต่งส่วนอื่นๆ คงเดิม */
    .dataTables_scrollHead {
        position: sticky !important;
        top: 0;
        background: #fff;
        border-bottom: 2px solid #f8f9fa !important;
    }

    .DTFC_RightWrapper,
    .sticky-col {
        background-color: white !important;
        border-left: none !important;
        box-shadow: -5px 0 10px -5px rgba(0, 0, 0, 0.05);
    }

    .hotel-logo-container {
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        background: #f8f9fa;
        border-radius: 4px;
        overflow: hidden;
    }

    .hotel-logo-container img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .text-gold {
        color: #D4AF37;
    }

    .x-small {
        font-size: 0.75rem;
    }

    /* Custom Scrollbar */
    .dataTables_scrollBody::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .dataTables_scrollBody::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .dataTables_scrollBody::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }

    /* ส่วนของ Toolbar */
    .table-toolbar {
        background: #fff;
        padding: 10px 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        /* สำคัญ: ให้ปุ่มลงมาบรรทัดใหม่ได้บนมือถือ */
        gap: 8px;
        /* ระยะห่างระหว่างปุ่ม */
    }

    /* ปรับขนาดปุ่มบนมือถือให้กดง่ายขึ้นแต่ไม่เทอะทะ */
    @media (max-width: 576px) {
        .table-toolbar {
            padding: 8px;
        }

        .table-toolbar .btn {
            flex: 1 1 auto;
            /* ให้ปุ่มยืดขยายเต็มพื้นที่ที่เหลือบนมือถือ */
            font-size: 12px;
            padding: 6px 8px;
        }

        /* ถ้าปุ่มเยอะเกินไป ให้ซ่อนข้อความเหลือแต่ไอคอนในบางปุ่มได้ */
        .btn-text-hide {
            display: none;
        }
    }
</style>
<script>
    $(document).ready(function () {
            // ── จัดสไตล์ไฟล์ Excel: หัวตาราง/ชื่อรายงาน ตัวหนา, แถบสีตามสถานะ, มูลค่าเป็นตัวเลข ฿ ──
            // หมายเหตุ: buttons.html5 2.x ส่ง xlsx.xl.* เข้ามาเป็น XML Document (parse ด้วย $.parseXML แล้ว)
            // จึงต้องแก้ไขผ่าน DOM API เท่านั้น ห้ามเขียนทับด้วย string
            function styleBanquetExcel(xlsx) {
                var SS_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                var stylesDoc = xlsx.xl['styles.xml'];
                var sheet = xlsx.xl.worksheets['sheet1.xml'];

                var GOLD = 'FFB89441', DARK = 'FF212529';
                var STATUS_STYLES = [
                    { text: 'อนุมัติแล้ว', fg: 'FFD1E7DD', font: 'FF0A3622' },
                    { text: 'ดำเนินการ',   fg: 'FFCFF4FC', font: 'FF055160' },
                    { text: 'จบงานแล้ว',   fg: 'FFCFE2FF', font: 'FF084299' },
                    { text: 'ยกเลิก',      fg: 'FFF8D7DA', font: 'FF842029' },
                    { text: 'รออนุมัติ',    fg: 'FFFFF3CD', font: 'FF664D03' }
                ];

                /* ── 1) styles.xml: เพิ่ม numFmt เงินบาท ── */
                var MONEY_FMT_ID = 176;
                var numFmtsEl = stylesDoc.getElementsByTagName('numFmts')[0];
                if (!numFmtsEl) {
                    numFmtsEl = stylesDoc.createElementNS(SS_NS, 'numFmts');
                    numFmtsEl.setAttribute('count', '0');
                    stylesDoc.documentElement.insertBefore(numFmtsEl, stylesDoc.documentElement.firstChild);
                }
                var nf = stylesDoc.createElementNS(SS_NS, 'numFmt');
                nf.setAttribute('numFmtId', MONEY_FMT_ID);
                nf.setAttribute('formatCode', '"฿"#,##0.00');
                numFmtsEl.appendChild(nf);
                numFmtsEl.setAttribute('count', parseInt(numFmtsEl.getAttribute('count'), 10) + 1);

                /* ── 2) styles.xml: เพิ่มฟอนต์ ── */
                var fontsEl = stylesDoc.getElementsByTagName('fonts')[0];
                var nextFont = parseInt(fontsEl.getAttribute('count'), 10);
                function addFont(bold, size, color) {
                    var f = stylesDoc.createElementNS(SS_NS, 'font');
                    if (bold) f.appendChild(stylesDoc.createElementNS(SS_NS, 'b'));
                    var sz = stylesDoc.createElementNS(SS_NS, 'sz'); sz.setAttribute('val', size); f.appendChild(sz);
                    var cl = stylesDoc.createElementNS(SS_NS, 'color'); cl.setAttribute('rgb', color); f.appendChild(cl);
                    var nm = stylesDoc.createElementNS(SS_NS, 'name'); nm.setAttribute('val', 'Calibri'); f.appendChild(nm);
                    fontsEl.appendChild(f);
                    return nextFont++;
                }

                /* ── 3) styles.xml: เพิ่มสีพื้นหลัง ── */
                var fillsEl = stylesDoc.getElementsByTagName('fills')[0];
                var nextFill = parseInt(fillsEl.getAttribute('count'), 10);
                function addFill(rgb) {
                    var fill = stylesDoc.createElementNS(SS_NS, 'fill');
                    var pf = stylesDoc.createElementNS(SS_NS, 'patternFill');
                    pf.setAttribute('patternType', 'solid');
                    var fg = stylesDoc.createElementNS(SS_NS, 'fgColor'); fg.setAttribute('rgb', rgb); pf.appendChild(fg);
                    var bg = stylesDoc.createElementNS(SS_NS, 'bgColor'); bg.setAttribute('indexed', '64'); pf.appendChild(bg);
                    fill.appendChild(pf);
                    fillsEl.appendChild(fill);
                    return nextFill++;
                }

                var fontTitle = addFont(true, 16, GOLD);
                var fontHeader = addFont(true, 11, 'FFFFFFFF');
                var statusFonts = [];
                STATUS_STYLES.forEach(function (st) { statusFonts.push(addFont(true, 11, st.font)); });

                var fillHeader = addFill(DARK);
                var statusFills = [];
                STATUS_STYLES.forEach(function (st) { statusFills.push(addFill(st.fg)); });

                // อัปเดต count ให้ตรงจำนวนจริง (Excel อ่าน count ตอน parse styles)
                fontsEl.setAttribute('count', String(nextFont));
                fillsEl.setAttribute('count', String(nextFill));

                /* ── 4) styles.xml: เพิ่ม cellXfs (สูตร style ของ cell) ── */
                var xfsEl = stylesDoc.getElementsByTagName('cellXfs')[0];
                var nextXf = parseInt(xfsEl.getAttribute('count'), 10);
                function addXf(fontId, fillId, numFmtId, align, wrap) {
                    var xf = stylesDoc.createElementNS(SS_NS, 'xf');
                    xf.setAttribute('numFmtId', numFmtId || 0);
                    xf.setAttribute('fontId', fontId);
                    xf.setAttribute('fillId', fillId);
                    xf.setAttribute('borderId', '0');
                    xf.setAttribute('xfId', '0');
                    xf.setAttribute('applyFont', '1');
                    if (fillId > 0) xf.setAttribute('applyFill', '1');
                    if (numFmtId) xf.setAttribute('applyNumberFormat', '1');
                    xf.setAttribute('applyAlignment', '1');
                    var al = stylesDoc.createElementNS(SS_NS, 'alignment');
                    al.setAttribute('horizontal', align || 'left');
                    al.setAttribute('vertical', 'center');
                    if (wrap) al.setAttribute('wrapText', '1');
                    xf.appendChild(al);
                    xfsEl.appendChild(xf);
                    return nextXf++;
                }

                var S = { status: {} };
                S.header = addXf(fontHeader, fillHeader, 0, 'center', true);
                S.title = addXf(fontTitle, 0, 0, 'left', false);
                STATUS_STYLES.forEach(function (st, i) {
                    S.status[st.text] = addXf(statusFonts[i], statusFills[i], 0, 'center', false);
                });
                S.money = addXf(fontHeader, 0, MONEY_FMT_ID, 'right', false);
                xfsEl.setAttribute('count', String(nextXf));

                /* ── 5) sheet1.xml: แปะ style ลง cell ── */
                function cellText(c) {
                    var t = c.getElementsByTagName('t')[0];
                    if (t && t.textContent) return t.textContent.trim();
                    var v = c.getElementsByTagName('v')[0];
                    return v ? String(v.textContent).trim() : '';
                }

                var rows = sheet.getElementsByTagName('row');

                // ชื่อรายงาน (แถวแรก)
                if (rows.length) {
                    var tc = rows[0].getElementsByTagName('c');
                    for (var i = 0; i < tc.length; i++) tc[i].setAttribute('s', S.title);
                }

                // Excel เว้น cell ว่างไม่เขียนลง XML เลย ตำแหน่ง index ใน cs[] จึงไม่ตรงกับคอลัมน์จริง
                // ต้องอ้างอิงด้วย "ตัวอักษรคอลัมน์" จาก attribute r (เช่น "F3" -> "F") แทน index
                function colLetter(cellEl) {
                    var r = cellEl.getAttribute('r') || '';
                    var m = r.match(/^[A-Z]+/);
                    return m ? m[0] : '';
                }

                // หาแถวหัวตาราง + ตัวอักษรคอลัมน์ "สถานะ" / "มูลค่า"
                var headerRow = null, statusColLetter = '', moneyColLetter = '';
                for (var r = 0; r < rows.length && !headerRow; r++) {
                    var cells = rows[r].getElementsByTagName('c');
                    for (var ci = 0; ci < cells.length; ci++) {
                        var txt = cellText(cells[ci]);
                        if (txt === 'สถานะ') { statusColLetter = colLetter(cells[ci]); headerRow = rows[r]; }
                        else if (txt === 'มูลค่า') moneyColLetter = colLetter(cells[ci]);
                    }
                }

                if (headerRow) {
                    var hc = headerRow.getElementsByTagName('c');
                    for (i = 0; i < hc.length; i++) hc[i].setAttribute('s', S.header);

                    for (r = 0; r < rows.length; r++) {
                        if (rows[r] === headerRow || r === 0) continue;
                        var cs = rows[r].getElementsByTagName('c');

                        for (var ci2 = 0; ci2 < cs.length; ci2++) {
                            var cell = cs[ci2];
                            var letter = colLetter(cell);

                            // สถานะ -> ใส่สีพื้น/สีตัวอักษรตามสถานะ
                            if (statusColLetter && letter === statusColLetter) {
                                var stTxt = cellText(cell);
                                if (S.status[stTxt]) cell.setAttribute('s', S.status[stTxt]);
                            }

                            // มูลค่า -> แปลงเป็นตัวเลขจริง + รูปแบบ ฿#,##0.00
                            // ตัดเฉพาะตัวเลขรูปแบบเงิน (เช่น "16,650.00") ออกมา อย่าลบอักขระทั้งก้อน
                            // เพราะข้อความต่อท้าย (เช่น "Draft V2") มีตัวเลขปนอยู่ด้วย
                            if (moneyColLetter && letter === moneyColLetter) {
                                var raw = cellText(cell);
                                var mnum = raw.match(/[\d,]*\d\.\d{2}/);
                                var num = mnum ? parseFloat(mnum[0].replace(/,/g, '')) : NaN;
                                if (!isNaN(num)) {
                                    while (cell.firstChild) cell.removeChild(cell.firstChild);
                                    cell.removeAttribute('t');
                                    cell.setAttribute('s', S.money);
                                    var v = sheet.createElementNS(SS_NS, 'v');
                                    v.textContent = String(num);
                                    cell.appendChild(v);
                                }
                            }
                        }
                    }
                }
            }

            if (typeof initDataTable !== 'function') {
            window.initDataTable = function() {
                // คำนวณความสูงตาราง
                var dynamicHeight = 'calc(100vh - 240px)';

                var table = $('#banquetTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
                    },
                    "order": [],
                    "pageLength": -1,
                    "autoWidth": false,
                    "scrollY": dynamicHeight,
                    "scrollX": true,
                    "scrollCollapse": true,
                    "paging": true,
                    "dom": '<"p-3 d-flex justify-content-between align-items-center"lf>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
                    "buttons": [
                        {
                            extend: 'excelHtml5',
                            title: 'รายการจัดเลี้ยง',
                            exportOptions: { columns: ':not(:first-child):not(:last-child)', rows: function(idx, data, node) { return !$(node).hasClass('draft-sub-row'); } },
                            customize: function (xlsx) {
                                try { styleBanquetExcel(xlsx); } catch (e) { console.error('Excel style error', e); }
                            }
                        },
                        { extend: 'print', exportOptions: { columns: ':not(:first-child):not(:last-child)', rows: function(idx, data, node) { return !$(node).hasClass('draft-sub-row'); } }, title: 'รายการจัดเลี้ยง' }
                    ],
                    "columnDefs": [
                        { "orderable": false, "targets": [0, -1] },
                        // คอลัมน์รายละเอียดสำหรับ Export (หน้าไหนไม่กำหนด window.exportOnlyCols = ไม่ซ่อนอะไร)
                        { "visible": false, "targets": (window.exportOnlyCols || []), "searchable": false }
                    ],
                    "initComplete": function () {
                        $(window).on('resize', function () {
                            table.columns.adjust();
                        });
                        setTimeout(function () {
                            table.columns.adjust();
                        }, 500);
                    }
                });

                // คุมปุ่ม Export
                $('#btnExportExcel').off('click').on('click', function () { table.button('.buttons-excel').trigger(); });
                $('#btnExportPrint').off('click').on('click', function () { table.button('.buttons-print').trigger(); });

                // Select All Logic
                $('#selectAll').off('click').on('click', function () {
                    var rows = table.rows({ 'search': 'applied' }).nodes();
                    $('input[type="checkbox"]', rows).prop('checked', this.checked);
                    if (typeof updateDeleteButton === 'function') updateDeleteButton();
                });

                $('#banquetTable tbody').off('change', 'input[type="checkbox"]').on('change', 'input[type="checkbox"]', function () {
                    if (typeof updateDeleteButton === 'function') updateDeleteButton();
                });

                return table;
            };
        }

        window.banquetTable = initDataTable();
    
        // --- 3. ปุ่มอนุมัติ (เปลี่ยนหน้าปกติ) ---
        window.confirmApprove = function (id) {
            if (confirm('ยืนยันการอนุมัติรายการนี้?')) {
                window.location.href = 'approve_event.php?id=' + id;
            }
        };

    });
</script>