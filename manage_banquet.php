<?php 
include "config.php"; 
include "header.php"; 
?>
<div id="alert-container">
    <?php include "alert.php"; ?>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="row mb-4 align-items-center">
    <div class="col">
        <h4 class="fw-bold text-dark mb-0">
            <i class="bi bi-journal-text me-2 text-gold"></i> Banquet Event List
        </h4>
        <p class="text-muted small mb-0">จัดการรายการจัดเลี้ยงทั้งหมดในระบบ</p>
    </div>
    <div class="col-auto">
        <button type="button" id="btnExportExcel" class="btn btn-success btn-sm me-2 shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </button>
        <button type="button" id="btnExportPrint" class="btn btn-secondary btn-sm me-2 shadow-sm">
            <i class="bi bi-printer me-1"></i> พิมพ์
        </button>

        <button id="deleteSelected" class="btn btn-danger btn-sm me-2 shadow-sm" style="display:none;">
            <i class="bi bi-trash3-fill me-1"></i> ลบที่เลือก (<span id="selectCount">0</span>)
        </button>

        <a href="calendar.php" class="btn btn-hotel-outline btn-sm me-2">
            <i class="bi bi-calendar3 me-1"></i> ดูปฏิทิน
        </a>
        <a href="add_event.php" class="btn btn-dark btn-sm px-3 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> เพิ่มงานใหม่
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="banquetTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th class="ps-4">ชื่องาน (Function)</th>
                        <th>บริษัท</th>
                        <th>ผู้จอง</th>
                        <th>เงินมัดจำ</th>
                        <th>สถานที่</th>
                        <th>สถานะ</th>
                        <th>Sales</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT f.*, c.company_name,
                            (SELECT MIN(schedule_date) FROM function_schedules WHERE function_id = f.id) as event_date 
                            FROM functions f 
                            LEFT JOIN companies c ON f.company_id = c.id
                            ORDER BY f.id DESC"; 
                    $q = mysqli_query($conn, $sql);
                    if($q && mysqli_num_rows($q) > 0) {
                        while($row = mysqli_fetch_assoc($q)) {
                            $display_date = !empty($row['event_date']) ? $row['event_date'] : $row['created_at'];
                            $date = date('d M Y', strtotime($display_date));
                            $deposit = !empty($row['deposit']) ? number_format($row['deposit'], 2) : '0.00';
                            $comp_name = !empty($row['company_name']) ? $row['company_name'] : '-';
                            $status_val = $row['approve'];
                            if ($status_val == 1) {
                                $st_text = "อนุมัติแล้ว"; $st_class = "bg-success-subtle text-success"; $st_icon = "bi-check-circle";
                            } elseif ($status_val == 2) {
                                $st_text = "ยกเลิก"; $st_class = "bg-danger-subtle text-danger"; $st_icon = "bi-x-circle";
                            } else {
                                $st_text = "รออนุมัติ"; $st_class = "bg-warning-subtle text-warning"; $st_icon = "bi-clock-history";
                            }
                    ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox form-check-input"
                                value="<?php echo $row['id']; ?>"></td>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['function_name']); ?></div>
                            <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> <?php echo $date; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($comp_name); ?></td>
                        <td>
                            <div class="text-dark small"><?php echo htmlspecialchars($row['booking_name']); ?></div>
                            <div class="text-muted x-small"><?php echo htmlspecialchars($row['phone']); ?></div>
                        </td>
                        <td><span class="text-primary fw-bold"><?php echo $deposit; ?></span></td>
                        <td>
                            <span class="badge bg-light text-dark border fw-normal">
                                <i class="bi bi-geo-alt me-1 text-gold"></i>
                                <?php echo htmlspecialchars($row['room_name']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $st_class; ?> rounded-pill px-3">
                                <i class="bi <?php echo $st_icon; ?> me-1"></i> <?php echo $st_text; ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-muted small"><?php echo htmlspecialchars($row['created_by'] ?: '-'); ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="btn-group shadow-sm">
                                <?php if ($row['approve'] == 0): ?>
                                <button class="btn btn-sm btn-white border"
                                    onclick="confirmApprove(<?php echo $row['id']; ?>)" title="อนุมัติ"><i
                                        class="bi bi-check-lg text-success"></i></button>
                                <?php endif; ?>
                                <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-white border"
                                    title="ดูรายละเอียด"><i class="bi bi-eye text-primary"></i></a>
                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-white border"
                                    title="แก้ไข"><i class="bi bi-pencil-square text-dark"></i></a>
                                <button type="button" class="btn btn-sm btn-white border btn-delete"
                                    data-id="<?php echo $row['id']; ?>" title="ลบ"><i
                                        class="bi bi-trash text-danger"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#banquetTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
        },
        "order": [],
        "pageLength": 10,
        // ปรับตรงนี้: ใช้ 'lfrtip' (ตัด B ออก เพื่อซ่อนปุ่มมาตรฐานของมัน)
        "dom": '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        "buttons": [{
                extend: 'excelHtml5',
                title: 'Banquet_Event_List',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7]
                }
            }
        ],
        "columnDefs": [{
            "orderable": false,
            "targets": [0, 8]
        }]
    });

    // สั่งให้ปุ่มที่เราสร้างขึ้นด้านบน ไปคลิกปุ่มของ DataTable
    $('#btnExportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    $('#btnExportPrint').on('click', function() {
        table.button('.buttons-print').trigger();
    });
    // ระบบ Select All
    $('#selectAll').on('click', function() {
        var rows = table.rows({
            'search': 'applied'
        }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
        updateDeleteButton();
    });

    $('#banquetTable tbody').on('change', 'input[type="checkbox"]', function() {
        updateDeleteButton();
    });

    function updateDeleteButton() {
        var count = $('.row-checkbox:checked').length;
        $('#selectCount').text(count);
        if (count > 0) {
            $('#deleteSelected').fadeIn();
        } else {
            $('#deleteSelected').fadeOut();
        }
    }

    // Single Delete
    $('#banquetTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        if (confirm('จารแน่ใจนะว่าจะลบรายการนี้?')) {
            $.ajax({
                url: 'delete_function.php',
                type: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        table.row(row).remove().draw(false);
                    }
                }
            });
        }
    });

    // Bulk Delete
    $('#deleteSelected').on('click', function() {
        var ids = [];
        $('.row-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        if (confirm('ลบทั้ง ' + ids.length + ' รายการ?')) {
            $.ajax({
                url: 'delete_multiple.php',
                type: 'POST',
                data: {
                    ids: ids
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') location.reload();
                }
            });
        }
    });
});
</script>

<style>
.text-gold {
    color: #D4AF37;
}

.btn-white {
    background: #fff;
}

.btn-white:hover {
    background: #f8f9fa;
}

.x-small {
    font-size: 0.75rem;
}

.dataTables_length select {
    display: inline-block !important;
    width: auto !important;
    margin: 0 5px !important;
    padding-right: 30px !important;
    border-radius: 8px !important;
}

.dt-buttons.btn-group {
    gap: 8px;
    margin-left: 15px;
}

.dt-buttons .btn {
    border-radius: 6px !important;
    font-weight: 500;
}
</style>

<?php include "footer.php"; ?>