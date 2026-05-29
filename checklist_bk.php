<?php
include "header.php";
access_control(['admin', 'banquet_staff']);
include "config.php"; 

// จัดการการเพิ่ม/ลบ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $conn->query("INSERT INTO master_checklist_bk (task_detail) VALUES ('$name')");
        $msg = "เพิ่มรายการเรียบร้อย";
    } elseif (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM master_checklist_bk WHERE id = $id");
        $msg = "ลบรายการเรียบร้อย";
    }
}

$items = $conn->query("SELECT * FROM master_checklist_bk ORDER BY id ASC");
?>
<div class="container-fluid">
    <h4 class="mb-4"><i class="bi bi-calendar-event"></i> จัดการรายการตรวจสอบ (จัดเลี้ยง)</h4>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="name" class="form-control" placeholder="ชื่อรายการ..." required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add" class="btn btn-primary w-100">เพิ่ม</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อรายการ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items): ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['task_detail']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('ยืนยันการลบ?');">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>
