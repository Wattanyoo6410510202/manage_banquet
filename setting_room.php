<?php
include "config.php";
require_once "header.php";

// ==========================================
// 🛡️ API SECTION (CRUD Logic)
// ==========================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == 'save') {
        $id = intval($_POST['id'] ?? 0);
        $room_name = $conn->real_escape_string($_POST['room_name']);
        $floor = $conn->real_escape_string($_POST['floor']);
        $length_m = floatval($_POST['length_m']);
        $width_m = floatval($_POST['width_m']);
        $height_m = floatval($_POST['height_m']);
        $total_sqm = $length_m * $width_m; // คำนวณพื้นที่อัตโนมัติ

        if ($id > 0) {
            $sql = "UPDATE meeting_rooms SET 
                    room_name='$room_name', floor='$floor', length_m='$length_m', 
                    width_m='$width_m', height_m='$height_m', total_sqm='$total_sqm',
                    cap_theatre='".intval($_POST['cap_theatre'])."', cap_classroom='".intval($_POST['cap_classroom'])."',
                    cap_banquet='".intval($_POST['cap_banquet'])."', cap_cocktail='".intval($_POST['cap_cocktail'])."'
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO meeting_rooms (room_name, floor, length_m, width_m, height_m, total_sqm, cap_theatre, cap_classroom, cap_banquet, cap_cocktail) 
                    VALUES ('$room_name', '$floor', '$length_m', '$width_m', '$height_m', '$total_sqm', 
                    '".intval($_POST['cap_theatre'])."', '".intval($_POST['cap_classroom'])."', 
                    '".intval($_POST['cap_banquet'])."', '".intval($_POST['cap_cocktail'])."')";
        }
        if ($conn->query($sql)) {
            echo "<script>alert('บันทึกข้อมูลห้องประชุมสำเร็จ!'); window.location.href='setting_room.php';</script>";
        }
    }
    if ($action == 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM meeting_rooms WHERE id=$id")) echo "success";
        exit;
    }
}
$rooms = $conn->query("SELECT * FROM meeting_rooms ORDER BY id ASC");
?>

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-gold fw-bold">จัดการข้อมูลห้องประชุม</div>
                <div class="card-body">
                    <form id="roomForm" method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="room_id" value="0">
                        
                        <label class="small fw-bold">ชื่อห้อง (Function Room Name)</label>
                        <input type="text" name="room_name" id="room_name" class="form-control mb-2" required>
                        
                        <div class="row">
                            <div class="col-6"><label class="small">ชั้น (Floor)</label><input type="text" name="floor" id="floor" class="form-control mb-2"></div>
                            <div class="col-6"><label class="small">ความสูง (Ceiling)</label><input type="number" step="0.01" name="height_m" id="height_m" class="form-control mb-2"></div>
                        </div>

                        <div class="row">
                            <div class="col-6"><label class="small">ยาว (m)</label><input type="number" step="0.01" name="length_m" id="length_m" class="form-control mb-2"></div>
                            <div class="col-6"><label class="small">กว้าง (m)</label><input type="number" step="0.01" name="width_m" id="width_m" class="form-control mb-2"></div>
                        </div>

                        <hr>
                        <label class="fw-bold small text-primary">Capacity (Number of Persons)</label>
                        <div class="row">
                            <div class="col-6 small">Theatre</div><div class="col-6"><input type="number" name="cap_theatre" id="cap_theatre" class="form-control form-control-sm mb-1"></div>
                            <div class="col-6 small">Classroom</div><div class="col-6"><input type="number" name="cap_classroom" id="cap_classroom" class="form-control form-control-sm mb-1"></div>
                            <div class="col-6 small">Banquet Buffet</div><div class="col-6"><input type="number" name="cap_banquet" id="cap_banquet" class="form-control form-control-sm mb-1"></div>
                            <div class="col-6 small">Cocktail</div><div class="col-6"><input type="number" name="cap_cocktail" id="cap_cocktail" class="form-control form-control-sm mb-1"></div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูลห้อง</button>
                            <button type="button" class="btn btn-light border" onclick="resetForm()">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 text-nowrap">
                <div class="card-header bg-white fw-bold">รายละเอียดห้องพักและห้องประชุม</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr class="small text-center">
                                    <th>Room Name</th>
                                    <th>Floor</th>
                                    <th>Total (sqm)</th>
                                    <th>Theatre</th>
                                    <th>Banquet</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $rooms->fetch_assoc()): ?>
                                <tr class="text-center">
                                    <td class="text-start fw-bold ps-3"><?= $row['room_name'] ?></td>
                                    <td><?= $row['floor'] ?></td>
                                    <td><?= number_format($row['total_sqm'], 2) ?></td>
                                    <td><?= $row['cap_theatre'] ?></td>
                                    <td><?= $row['cap_banquet'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-link" onclick='editRoom(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-link text-danger" onclick="deleteRoom(<?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editRoom(data) {
    document.getElementById('room_id').value = data.id;
    document.getElementById('room_name').value = data.room_name;
    document.getElementById('floor').value = data.floor;
    document.getElementById('length_m').value = data.length_m;
    document.getElementById('width_m').value = data.width_m;
    document.getElementById('height_m').value = data.height_m;
    document.getElementById('cap_theatre').value = data.cap_theatre;
    document.getElementById('cap_classroom').value = data.cap_classroom;
    document.getElementById('cap_banquet').value = data.cap_banquet;
    document.getElementById('cap_cocktail').value = data.cap_cocktail;
}

function resetForm() {
    document.getElementById('roomForm').reset();
    document.getElementById('room_id').value = 0;
}

function deleteRoom(id) {
    if (confirm('ลบห้องนี้ใช่ไหม?')) {
        let fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
        fetch('setting_room.php', { method: 'POST', body: fd })
        .then(res => res.text()).then(data => { if(data.trim() === 'success') window.location.reload(); });
    }
}
</script>

<?php include "footer.php"; ?>