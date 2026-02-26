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
        // คำนวณความสูงตาราง
        var dynamicHeight = 'calc(100vh - 240px)';

        var table = $('#banquetTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
            },
            "order": [],
            "pageLength": 10,
            "autoWidth": false,
            "scrollY": dynamicHeight,
            "scrollX": true,
            "scrollCollapse": true,
            "paging": true,
            "fixedColumns": {
                right: 1
            },
            "dom": '<"p-3 d-flex justify-content-between align-items-center"lf>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
            "buttons": [
                { extend: 'excelHtml5', title: 'Banquet_Event_List', exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7] } },
                { extend: 'print', exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7] } }
            ],
            "columnDefs": [
                { "orderable": false, "targets": [0, 8] },
                { "width": "140px", "targets": 8 }
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
        $('#btnExportExcel').click(function () { table.button('.buttons-excel').trigger(); });
        $('#btnExportPrint').click(function () { table.button('.buttons-print').trigger(); });

        // Select All Logic
        $('#selectAll').on('click', function () {
            var rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
            updateDeleteButton();
        });

        $('#banquetTable tbody').on('change', 'input[type="checkbox"]', function () {
            updateDeleteButton();
        });

        function updateDeleteButton() {
            var count = $('.row-checkbox:checked').length;
            $('#selectCount').text(count);
            (count > 0) ? $('#deleteSelected').fadeIn(200) : $('#deleteSelected').fadeOut(200);
        }
    
        // --- 3. ปุ่มอนุมัติ (เปลี่ยนหน้าปกติ) ---
        window.confirmApprove = function (id) {
            if (confirm('ยืนยันการอนุมัติรายการนี้?')) {
                window.location.href = 'approve_event.php?id=' + id;
            }
        };

    });
</script>