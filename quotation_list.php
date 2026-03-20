<?php
include "config.php";
include "header.php";

// เพิ่มการ JOIN ตาราง customers (c) เพื่อเอาชื่อลูกค้า (cust_name)
$sql = "SELECT q.*, f.function_name, c.cust_name 
        FROM quotations q
        LEFT JOIN functions f ON q.function_id = f.id
        LEFT JOIN customers c ON q.customer_id = c.id
        ORDER BY q.id DESC";

$result = $conn->query($sql);
?>


<div class="container-fluid p-0">
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-card-checklist text-primary"></i> ประวัติใบเสนอราคา</h3>
            <p class="text-muted mb-0">ตรวจสอบและจัดการใบเสนอราคาที่ออกไปแล้วทั้งหมด</p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <a href="add_quote.php" class="btn btn-primary btn-create">
                <i class="bi bi-plus-circle-fill me-2"></i> สร้างใบเสนอราคาใหม่
            </a>
        </div>
    </div>

    <div class="card p-3">
        <div class="table-responsive">
            <table id="quoteDataTable" class="table table-hover responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center" width="15%">เลขที่ใบเสนอราคา</th>
                        <th width="15%">วันที่ออกเอกสาร</th>
                        <th>ชื่อลูกค้า / โครงการ</th>
                        <th class="text-end" width="15%">ยอดสุทธิ</th>
                        <th class="text-center" width="12%">สถานะ</th>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center fw-bold">
                                <span class="text-primary"><?= $row['quote_no'] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= $row['cust_name'] ?></div>
                                <small class="text-muted"><i
                                        class="bi bi-calendar-event me-1"></i><?= $row['function_name'] ?></small>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <?= number_format($row['grand_total'], 2) ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $status_map = [
                                    'Draft' => ['class' => 'bg-secondary-subtle text-secondary', 'text' => 'ฉบับร่าง'],
                                    'Sent' => ['class' => 'bg-info-subtle text-info', 'text' => 'ส่งแล้ว'],
                                    'Approved' => ['class' => 'bg-success-subtle text-success', 'text' => 'อนุมัติแล้ว'],
                                    'Cancelled' => ['class' => 'bg-danger-subtle text-danger', 'text' => 'ยกเลิก']
                                ];
                                $st = $status_map[$row['status']] ?? $status_map['Draft'];
                                ?>
                                <span class="badge border <?= $st['class'] ?> px-3 py-2">
                                    <?= $st['text'] ?>
                                </span>

                                <?php if ($row['status'] == 'Approved' && $row['approved_at']): ?>
                                    <div class="small text-muted mt-1" style="font-size: 0.7rem;">
                                        อนุมัติเมื่อ: <?= date('d/m/y H:i', strtotime($row['approved_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="quotation_view.php?id=<?= $row['id'] ?>"
                                        class="btn btn-outline-primary btn-action" title="พิมพ์/ดู">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="edit_quotation.php?id=<?= $row['id'] ?>"
                                        class="btn btn-outline-warning btn-action" title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-action btn-delete-quote"
                                        data-id="<?= $row['id'] ?>" title="ลบ">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>