<?php 
include "config.php"; 
include "header.php"; 
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h4 class="fw-bold text-dark mb-0">
            <i class="bi bi-journal-text me-2 text-gold"></i> Banquet Event List
        </h4>
        <p class="text-muted small mb-0">จัดการรายการจัดเลี้ยงทั้งหมดในระบบ</p>
    </div>
    <div class="col-auto">
        <a href="calendar.php" class="btn btn-hotel-outline btn-sm me-2">
            <i class="bi bi-calendar3 me-1"></i> ดูปฏิทิน
        </a>
        <a href="add_event.php" class="btn btn-dark btn-sm px-3 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> เพิ่มงานใหม่
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" width="25%">ชื่องาน (Function)</th>
                        <th width="15%">วันที่จัดงาน</th>
                        <th width="20%">สถานที่ (Room)</th>
                        <th width="15%">ผู้จอง</th>
                        <th width="10%">สถานะ</th>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM events ORDER BY event_date DESC";
                    $q = mysqli_query($conn, $sql);
                    
                    if(mysqli_num_rows($q) > 0) {
                        while($row = mysqli_fetch_assoc($q)) {
                            // แปลงวันที่ให้ดูง่ายขึ้น
                            $date = date('d M Y', strtotime($row['event_date']));
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $row['function_name']; ?></div>
                                <div class="text-muted small">ID: #<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></div>
                            </td>
                            <td>
                                <i class="bi bi-calendar-event me-2 text-muted"></i>
                                <?php echo $date; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal">
                                    <i class="bi bi-geo-alt me-1 text-gold"></i>
                                    <?php echo $row['room_name']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['booking_name']; ?></td>
                            <td>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group shadow-sm">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-white border" title="View Detail">
                                        <i class="bi bi-eye text-primary"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-white border" title="Edit">
                                        <i class="bi bi-pencil-square text-dark"></i>
                                    </a>
                                    <button class="btn btn-sm btn-white border" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลรายการจัดเลี้ยง</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if(confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?')) {
        window.location.href = 'delete_event.php?id=' + id;
    }
}
</script>

<?php include "footer.php"; ?>